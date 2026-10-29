import { signal } from '@angular/core';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { vi } from 'vitest';
import { AuthService } from '../../auth.service';
import { CartItem, CartService } from '../../cart.service';
import { NotificationService } from '../../notification.service';
import { Checkout } from './checkout';

interface PayPalSdkMock {
  Buttons: (options: PayPalButtonCallbacks) => { render: (selector: string) => Promise<void> };
}

interface PayPalButtonCallbacks {
  createOrder: () => Promise<string>;
  onApprove: (data: { orderID: string }) => void;
  onCancel: () => void;
  onError: (error: unknown) => void;
}

describe('Checkout', () => {
  const cartItems = signal<CartItem[]>([
    { id: 1, name: 'Producto de prueba', price: 10000, icon: '🥃', quantity: 1, stock: 2 },
  ]);
  const renderButtons = vi.fn().mockResolvedValue(undefined);
  let buttonCallbacks: PayPalButtonCallbacks | undefined;
  const createButtons = vi.fn((options: PayPalButtonCallbacks) => {
    buttonCallbacks = options;
    return { render: renderButtons };
  });
  const paypalSdk: PayPalSdkMock = {
    Buttons: createButtons,
  };
  const clearCart = vi.fn(() => cartItems.set([]));
  const notifications = {
    showSuccess: vi.fn(),
    showWarning: vi.fn(),
    showError: vi.fn(),
  };
  const originalPayPal = (window as Window & { paypal?: PayPalSdkMock }).paypal;

  beforeEach(async () => {
    sessionStorage.clear();
    localStorage.clear();
    cartItems.set([{ id: 1, name: 'Producto de prueba', price: 10000, icon: '🥃', quantity: 1, stock: 2 }]);
    renderButtons.mockClear();
    createButtons.mockClear();
    buttonCallbacks = undefined;
    clearCart.mockClear();
    notifications.showSuccess.mockClear();
    notifications.showWarning.mockClear();
    notifications.showError.mockClear();
    (window as Window & { paypal?: PayPalSdkMock }).paypal = paypalSdk;
    await TestBed.configureTestingModule({
      imports: [Checkout],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        {
          provide: AuthService,
          useValue: {
            user: signal({ id: 1, name: 'Usuario de prueba', email: 'test@example.invalid', role: 'Cliente', roleId: 2 }),
          },
        },
        {
          provide: CartService,
          useValue: {
            items: cartItems,
            count: () => cartItems().reduce((total, item) => total + item.quantity, 0),
            total: () => cartItems().reduce((total, item) => total + item.price * item.quantity, 0),
            clear: clearCart,
          },
        },
        { provide: NotificationService, useValue: notifications },
      ],
    }).compileComponents();
  });

  afterEach(() => {
    TestBed.inject(HttpTestingController).verify();
    sessionStorage.clear();
    (window as Window & { paypal?: PayPalSdkMock }).paypal = originalPayPal;
    TestBed.resetTestingModule();
  });

  it('renders PayPal as selectable and mounts its button after the payment config loads', async () => {
    const fixture = TestBed.createComponent(Checkout);
    const http = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    http.expectOne('/api/payment-config.php').flush({
      simulatorEnabled: true,
      paypalEnabled: true,
      paypalStatus: 'configured',
      paypalClientId: 'sandbox-client-id',
      paypalCurrency: 'USD',
      paypalCopPerUsd: 4000,
    });
    http.expectOne('/api/products.php').flush({ products: [{ id: 1, stock: 2 }] });
    fixture.detectChanges();
    await fixture.whenStable();

    const checkout = fixture.nativeElement as HTMLElement;
    const paypalOption = Array.from(checkout.querySelectorAll('button'))
      .find(button => button.textContent?.includes('PayPal Sandbox'));
    if (!paypalOption) throw new Error('PayPal payment option was not rendered.');
    expect(paypalOption.getAttribute('aria-pressed')).toBe('false');

    paypalOption.click();
    fixture.detectChanges();
    await new Promise(resolve => setTimeout(resolve, 0));

    expect(paypalOption.getAttribute('aria-pressed')).toBe('true');
    expect(fixture.nativeElement.querySelector('#paypal-button-container')).toBeTruthy();
    expect(renderButtons).toHaveBeenCalledWith('#paypal-button-container');
  });

  it('retries verification of the same PayPal order and clears the cart only after backend confirmation', async () => {
    const fixture = TestBed.createComponent(Checkout);
    const http = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    http.expectOne('/api/payment-config.php').flush({
      simulatorEnabled: true,
      paypalEnabled: true,
      paypalStatus: 'configured',
      paypalClientId: 'sandbox-client-id',
      paypalCurrency: 'USD',
      paypalCopPerUsd: 4000,
    });
    http.expectOne('/api/products.php').flush({ products: [{ id: 1, stock: 2 }] });
    fixture.detectChanges();
    await fixture.whenStable();

    const checkout = fixture.nativeElement as HTMLElement;
    const paypalOption = Array.from(checkout.querySelectorAll('button'))
      .find(button => button.textContent?.includes('PayPal Sandbox'));
    if (!paypalOption) throw new Error('PayPal payment option was not rendered.');
    paypalOption.click();
    fixture.detectChanges();
    await new Promise(resolve => setTimeout(resolve, 0));

    const address = checkout.querySelector<HTMLInputElement>('[autocomplete="street-address"]');
    const birthDate = checkout.querySelector<HTMLInputElement>('input[type="date"]');
    if (!address || !birthDate || !buttonCallbacks) throw new Error('Checkout fields or PayPal callbacks are missing.');
    address.value = 'Calle de prueba 123';
    address.dispatchEvent(new Event('input'));
    birthDate.value = '1990-01-01';
    birthDate.dispatchEvent(new Event('input'));
    fixture.detectChanges();

    const orderPromise = buttonCallbacks.createOrder();
    const createOrderRequest = http.expectOne('/api/paypal/create-order.php');
    const idempotencyKey = createOrderRequest.request.body.idempotencyKey as string;
    createOrderRequest.flush({ orderId: 'SANDBOX-ORDER-123', currency: 'USD', amount: '2.50' });
    await expect(orderPromise).resolves.toBe('SANDBOX-ORDER-123');

    buttonCallbacks.onApprove({ orderID: 'SANDBOX-ORDER-123' });
    const failedConfirmation = http.expectOne('/api/purchase.php');
    expect(failedConfirmation.request.body.paypalOrderId).toBe('SANDBOX-ORDER-123');
    expect(failedConfirmation.request.body.idempotencyKey).toBe(idempotencyKey);
    failedConfirmation.flush(
      { message: 'No fue posible procesar el pedido.' },
      { status: 502, statusText: 'Bad Gateway' },
    );
    fixture.detectChanges();
    expect(clearCart).not.toHaveBeenCalled();
    expect(notifications.showSuccess).not.toHaveBeenCalled();
    expect(checkout.querySelector('input[type="date"]')).toBeTruthy();

    const verifyButton = Array.from(checkout.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Volver a verificar'));
    if (!verifyButton) throw new Error('Payment verification retry action was not rendered.');
    verifyButton.click();
    fixture.detectChanges();
    const retryConfirmation = http.expectOne('/api/purchase.php');
    expect(retryConfirmation.request.body.paypalOrderId).toBe('SANDBOX-ORDER-123');
    expect(retryConfirmation.request.body.idempotencyKey).toBe(idempotencyKey);
    retryConfirmation.flush({
      success: true,
      saleId: 7,
      orderId: 12,
      orderNumber: 'PED-TEST',
      total: 10000,
      paymentMethod: 'paypal',
      paymentStatus: 'CAPTURADO',
      orderStatus: 'CONFIRMADO',
      invoiceAvailable: true,
      message: '¡Compra completada correctamente! Tu pago ha sido confirmado y tu pedido ha quedado registrado.',
    });
    fixture.detectChanges();

    expect(clearCart).toHaveBeenCalledOnce();
    expect(notifications.showSuccess).toHaveBeenCalledWith(
      '¡Compra completada correctamente! Tu pago ha sido confirmado y tu pedido ha quedado registrado.',
    );
    expect(checkout.textContent).toContain('Compra confirmada');
  });

  it('restores an approved payment after reload without creating a new PayPal order', async () => {
    sessionStorage.setItem('liquorsoft-paypal-idempotency-key', 'test-idempotency-key');
    sessionStorage.setItem('liquorsoft-paypal-order-id', 'SANDBOX-ORDER-RECOVER');
    sessionStorage.setItem('liquorsoft-paypal-order-status', 'approved');
    const fixture = TestBed.createComponent(Checkout);
    const http = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    http.expectOne('/api/payment-config.php').flush({
      simulatorEnabled: true,
      paypalEnabled: true,
      paypalStatus: 'configured',
      paypalClientId: 'sandbox-client-id',
      paypalCurrency: 'USD',
      paypalCopPerUsd: 4000,
    });
    http.expectOne('/api/products.php').flush({ products: [{ id: 1, stock: 2 }] });
    fixture.detectChanges();
    await fixture.whenStable();

    const checkout = fixture.nativeElement as HTMLElement;
    expect(checkout.querySelector('#paypal-button-container')).toBeNull();
    const birthDate = checkout.querySelector<HTMLInputElement>('input[type="date"]');
    const verifyButton = Array.from(checkout.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Verificar pago pendiente'));
    if (!birthDate || !verifyButton) throw new Error('The pending PayPal recovery controls are missing.');
    birthDate.value = '1990-01-01';
    birthDate.dispatchEvent(new Event('input'));
    fixture.detectChanges();
    verifyButton.click();
    fixture.detectChanges();

    const verification = http.expectOne('/api/purchase.php');
    expect(verification.request.body.paypalOrderId).toBe('SANDBOX-ORDER-RECOVER');
    expect(verification.request.body.idempotencyKey).toBe('test-idempotency-key');
    verification.flush({
      success: true,
      saleId: 8,
      orderId: 13,
      orderNumber: 'PED-RECOVERED',
      total: 10000,
      paymentMethod: 'paypal',
      paymentStatus: 'CAPTURADO',
      orderStatus: 'CONFIRMADO',
      invoiceAvailable: true,
      message: '¡Compra completada correctamente! Tu pago ha sido confirmado y tu pedido ha quedado registrado.',
    });
    fixture.detectChanges();

    expect(clearCart).toHaveBeenCalledOnce();
    expect(sessionStorage.getItem('liquorsoft-paypal-order-id')).toBeNull();
    expect(checkout.textContent).toContain('Compra confirmada');
  });

  it('prefills the delivery details from the stored checkout draft', async () => {
    localStorage.setItem(
      'liquorsoft-checkout-draft',
      JSON.stringify({ deliveryAddress: 'Calle 8 #12 - 30', birthDate: '1990-05-05', notes: 'Porton rojo' }),
    );
    const fixture = TestBed.createComponent(Checkout);
    const http = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    http.expectOne('/api/payment-config.php').flush({
      simulatorEnabled: true,
      paypalEnabled: false,
      paypalStatus: 'missing_credentials',
      paypalClientId: null,
      paypalCurrency: 'USD',
      paypalCopPerUsd: null,
    });
    http.expectOne('/api/products.php').flush({ products: [{ id: 1, stock: 2 }] });
    fixture.detectChanges();
    await fixture.whenStable();

    const checkout = fixture.nativeElement as HTMLElement;
    const address = checkout.querySelector<HTMLInputElement>('[autocomplete="street-address"]');
    const birthDate = checkout.querySelector<HTMLInputElement>('input[type="date"]');
    const notes = checkout.querySelector<HTMLTextAreaElement>('textarea');
    if (!address || !birthDate || !notes) throw new Error('Checkout delivery fields are missing.');
    expect(address.value).toBe('Calle 8 #12 - 30');
    expect(birthDate.value).toBe('1990-05-05');
    expect(notes.value).toBe('Porton rojo');
  });

  it('keeps the delivery details in the checkout draft while typing', async () => {
    const fixture = TestBed.createComponent(Checkout);
    const http = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    http.expectOne('/api/payment-config.php').flush({
      simulatorEnabled: true,
      paypalEnabled: false,
      paypalStatus: 'missing_credentials',
      paypalClientId: null,
      paypalCurrency: 'USD',
      paypalCopPerUsd: null,
    });
    http.expectOne('/api/products.php').flush({ products: [{ id: 1, stock: 2 }] });
    fixture.detectChanges();
    await fixture.whenStable();

    const checkout = fixture.nativeElement as HTMLElement;
    const address = checkout.querySelector<HTMLInputElement>('[autocomplete="street-address"]');
    const notes = checkout.querySelector<HTMLTextAreaElement>('textarea');
    if (!address || !notes) throw new Error('Checkout delivery fields are missing.');

    address.value = 'Calle 9 #10 - 20';
    address.dispatchEvent(new Event('input'));
    notes.value = 'Entrega en portero';
    notes.dispatchEvent(new Event('input'));
    fixture.detectChanges();

    const draft = JSON.parse(localStorage.getItem('liquorsoft-checkout-draft') ?? 'null');
    expect(draft?.deliveryAddress).toBe('Calle 9 #10 - 20');
    expect(draft?.notes).toBe('Entrega en portero');
  });

  it('confirms the simulated purchase and clears the checkout draft', async () => {
    const fixture = TestBed.createComponent(Checkout);
    const http = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    http.expectOne('/api/payment-config.php').flush({
      simulatorEnabled: true,
      paypalEnabled: false,
      paypalStatus: 'missing_credentials',
      paypalClientId: null,
      paypalCurrency: 'USD',
      paypalCopPerUsd: null,
    });
    http.expectOne('/api/products.php').flush({ products: [{ id: 1, stock: 2 }] });
    fixture.detectChanges();
    await fixture.whenStable();

    const checkout = fixture.nativeElement as HTMLElement;
    const address = checkout.querySelector<HTMLInputElement>('[autocomplete="street-address"]');
    const birthDate = checkout.querySelector<HTMLInputElement>('input[type="date"]');
    const confirmButton = Array.from(checkout.querySelectorAll('button')).find((button) =>
      button.textContent?.includes('Confirmar compra'),
    );
    if (!address || !birthDate || !confirmButton) throw new Error('Checkout confirm action is missing.');

    address.value = 'Calle de prueba 123';
    address.dispatchEvent(new Event('input'));
    birthDate.value = '1990-01-01';
    birthDate.dispatchEvent(new Event('input'));
    fixture.detectChanges();

    confirmButton.click();
    fixture.detectChanges();
    const purchaseRequest = http.expectOne('/api/purchase.php');
    purchaseRequest.flush({
      success: true,
      saleId: 7,
      orderId: 12,
      orderNumber: 'PED-TEST',
      total: 10000,
      paymentMethod: 'nequi_simulado',
      paymentStatus: 'APROBADO',
      orderStatus: 'CONFIRMADO',
      invoiceAvailable: true,
      message: 'Pago de prueba aprobado por el simulador de desarrollo. El pedido fue confirmado.',
    });
    fixture.detectChanges();

    expect(clearCart).toHaveBeenCalledOnce();
    expect(localStorage.getItem('liquorsoft-checkout-draft')).toBeNull();
  });
});
