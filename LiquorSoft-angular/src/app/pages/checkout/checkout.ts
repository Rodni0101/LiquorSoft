import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { CartService } from '../../cart.service';
import { AuthService } from '../../auth.service';
import { PaymentMethod, PaymentService, PaymentStatus } from '../../payment.service';
import { PurchaseResponse, PurchaseService } from '../../purchase.service';
import { formatCop } from '../../money';
import { ThemeToggle } from '../../theme-toggle';
import { NotificationService } from '../../notification.service';

interface CheckoutProduct {
  id?: number;
  stock?: number;
}

interface PaymentConfig {
  simulatorEnabled: boolean;
  paypalEnabled: boolean;
  paypalStatus: string;
  paypalEnvironment?: 'sandbox' | 'live';
  paypalClientId: string | null;
  paypalCurrency: 'USD';
  paypalCopPerUsd: number | null;
}

interface PayPalButtons {
  render(selector: string): Promise<void>;
}

interface PayPalSdk {
  Buttons(options: {
    createOrder: () => Promise<string>;
    onApprove: (data: { orderID: string }) => void;
    onCancel: () => void;
    onError: (error: unknown) => void;
  }): PayPalButtons;
}

@Component({
  selector: 'app-checkout',
  standalone: true,
  imports: [RouterLink, FormsModule, ThemeToggle],
  templateUrl: './checkout.html',
  styleUrl: './checkout.css',
})
export class Checkout implements OnInit {
  protected readonly cart = inject(CartService);
  protected readonly auth = inject(AuthService);
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);
  protected readonly payment = inject(PaymentService);
  private readonly purchase = inject(PurchaseService);
  private readonly notifications = inject(NotificationService);
  private readonly paypalIdempotencyStorageKey = 'liquorsoft-paypal-idempotency-key';
  private readonly paypalOrderStorageKey = 'liquorsoft-paypal-order-id';
  private readonly paypalStatusStorageKey = 'liquorsoft-paypal-order-status';
  private readonly restoredPayPalAttempt = this.readPayPalAttempt();
  private readonly checkoutDraftStorageKey = 'liquorsoft-checkout-draft';
  private readonly restoredCheckoutDraft = this.readCheckoutDraft();
  protected method: PaymentMethod = this.restoredPayPalAttempt.orderId ? 'paypal' : 'nequi_simulado';
  protected simulationOutcome = 'approved';
  protected simulatorEnabled = false;
  protected paypalEnabled = false;
  protected paypalStatus = 'missing_credentials';
  protected paypalEnvironment: 'sandbox' | 'live' = 'sandbox';
  protected paypalClientId: string | null = null;
  protected paypalCopPerUsd: number | null = null;
  protected paypalOrderApproved = this.restoredPayPalAttempt.status === 'approved';
  protected status: PaymentStatus | 'ready' = 'ready';
  protected message = '';
  protected stockError = '';
  protected purchaseSubmitting = false;
  protected notes = this.restoredCheckoutDraft.notes;
  protected deliveryAddress = this.restoredCheckoutDraft.deliveryAddress;
  protected birthDate = this.restoredCheckoutDraft.birthDate;
  protected orderNumber = '';
  protected orderId: number | null = null;
  protected invoiceUrl = '';
  protected paypalOrderId = this.restoredPayPalAttempt.orderId;
  private idempotencyKey = this.restoredPayPalAttempt.idempotencyKey;
  private paypalButtonsRendered = false;

  private readPayPalAttempt(): { idempotencyKey: string; orderId: string; status: string } {
    const idempotencyKey = sessionStorage.getItem(this.paypalIdempotencyStorageKey);
    const orderId = sessionStorage.getItem(this.paypalOrderStorageKey);
    return {
      idempotencyKey: idempotencyKey ?? crypto.randomUUID(),
      orderId: idempotencyKey && orderId ? orderId : '',
      status: idempotencyKey && orderId ? sessionStorage.getItem(this.paypalStatusStorageKey) ?? '' : '',
    };
  }

  private savePayPalAttempt(orderId = '', status = ''): void {
    sessionStorage.setItem(this.paypalIdempotencyStorageKey, this.idempotencyKey);
    if (orderId) {
      sessionStorage.setItem(this.paypalOrderStorageKey, orderId);
      sessionStorage.setItem(this.paypalStatusStorageKey, status);
    } else {
      sessionStorage.removeItem(this.paypalOrderStorageKey);
      sessionStorage.removeItem(this.paypalStatusStorageKey);
    }
  }

  private clearPayPalAttempt(): void {
    sessionStorage.removeItem(this.paypalIdempotencyStorageKey);
    sessionStorage.removeItem(this.paypalOrderStorageKey);
    sessionStorage.removeItem(this.paypalStatusStorageKey);
  }

  private readCheckoutDraft(): { deliveryAddress: string; birthDate: string; notes: string } {
    try {
      const stored = localStorage.getItem(this.checkoutDraftStorageKey);
      if (!stored) {
        return { deliveryAddress: '', birthDate: '', notes: '' };
      }
      const parsed: unknown = JSON.parse(stored);
      const draft = (parsed ?? {}) as Partial<{ deliveryAddress: string; birthDate: string; notes: string }>;
      return {
        deliveryAddress: typeof draft.deliveryAddress === 'string' ? draft.deliveryAddress : '',
        birthDate: typeof draft.birthDate === 'string' ? draft.birthDate : '',
        notes: typeof draft.notes === 'string' ? draft.notes : '',
      };
    } catch {
      return { deliveryAddress: '', birthDate: '', notes: '' };
    }
  }

  private saveCheckoutDraft(): void {
    try {
      localStorage.setItem(this.checkoutDraftStorageKey, JSON.stringify({
        deliveryAddress: this.deliveryAddress,
        birthDate: this.birthDate,
        notes: this.notes,
      }));
    } catch {
    }
  }

  private clearCheckoutDraft(): void {
    try {
      localStorage.removeItem(this.checkoutDraftStorageKey);
    } catch {
    }
  }

  protected onDeliveryAddressChange(value: string): void {
    this.deliveryAddress = value ?? '';
    this.saveCheckoutDraft();
    this.message = '';
  }

  protected onBirthDateChange(value: string): void {
    this.birthDate = value ?? '';
    this.saveCheckoutDraft();
    this.message = '';
  }

  protected onNotesChange(value: string): void {
    this.notes = value ?? '';
    this.saveCheckoutDraft();
    this.message = '';
  }

  private focusCheckoutField(id: string): void {
    const element = document.getElementById(id);
    if (element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement) {
      element.focus();
    }
  }

  ngOnInit(): void {
    this.validateStock();
    this.http.get<PaymentConfig>('/api/payment-config.php').subscribe({
      next: config => {
        this.simulatorEnabled = config.simulatorEnabled;
        this.paypalEnabled = config.paypalEnabled;
        this.paypalStatus = config.paypalStatus;
        this.paypalEnvironment = config.paypalEnvironment ?? 'sandbox';
        this.paypalClientId = config.paypalClientId;
        this.paypalCopPerUsd = config.paypalCopPerUsd;
        if (this.paypalEnabled && !this.simulatorEnabled) this.method = 'paypal';
        if (this.paypalEnabled && this.paypalClientId) this.loadPayPalSdk(this.paypalClientId);
        this.changeDetector.markForCheck();
      },
      error: () => {
        this.simulatorEnabled = false;
        this.paypalEnabled = false;
        this.changeDetector.markForCheck();
      },
    });
  }

  protected formatPrice(value: number): string {
    return formatCop(value);
  }

  protected formatUsd(value: number): string {
    const rate = this.paypalCopPerUsd;
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'USD' })
      .format(rate ? Math.round((value / rate) * 100) / 100 : 0);
  }

  protected paypalUnavailableMessage(): string {
    return ({
      missing_credentials: 'faltan las credenciales del servidor.',
      invalid_environment: 'PAYPAL_ENVIRONMENT debe ser sandbox o live.',
      invalid_exchange_rate: 'falta una tasa COP por USD positiva en backend/.env.',
      curl_unavailable: 'PHP necesita la extensión cURL.',
    } as Record<string, string>)[this.paypalStatus]
      ?? 'revisa la configuración de PayPal del servidor.';
  }

  protected selectPaymentMethod(method: PaymentMethod): void {
    if (this.method === 'paypal' && method !== 'paypal') this.paypalButtonsRendered = false;
    this.method = method;
    if (method === 'paypal') {
      const sdk = (window as Window & { paypal?: PayPalSdk }).paypal;
      if (sdk) setTimeout(() => this.renderPayPalButtons(sdk));
    }
  }

  protected validateStock(): void {
    if (!this.cart.items().length) return;
    this.http.get<{ products: CheckoutProduct[] }>('/api/products.php').subscribe({
      next: (response) => {
        const problems = this.cart.items().flatMap((item) => {
          const product = response.products.find((candidate) => candidate.id === item.id);
          return !product
            ? [`${item.name} ya no está disponible.`]
            : item.quantity > (product.stock ?? 0)
              ? [`${item.name}: quedan ${product.stock ?? 0} unidades.`]
              : [];
        });
        this.stockError = problems.join(' ');
        this.changeDetector.markForCheck();
      },
      error: () => {
        this.stockError = 'No fue posible validar el stock. Intenta nuevamente.';
        this.changeDetector.markForCheck();
      },
    });
  }

  protected submitPurchase(): void {
    if (this.purchaseSubmitting) return;
    if (this.stockError) {
      this.message = this.stockError;
      return;
    }
    if (!this.deliveryAddress.trim()) {
      this.message = 'Indica la dirección de entrega para registrar tu compra.';
      this.focusCheckoutField('deliveryAddress');
      return;
    }
    if (!this.birthDate) {
      this.message = 'Selecciona la fecha de nacimiento para confirmar que eres mayor de 18 años.';
      this.focusCheckoutField('birthDate');
      return;
    }
    this.registerPurchase();
  }

  protected registerPurchase(): void {
    if (
      this.purchaseSubmitting ||
      !this.simulatorEnabled ||
      this.method !== 'nequi_simulado' ||
      this.stockError ||
      !this.cart.items().length ||
      !this.deliveryAddress.trim() ||
      !this.birthDate
    )
      return;
    this.purchaseSubmitting = true;
    this.status = 'processing';
    this.purchase.checkout(this.cart.items(), this.method, this.simulationOutcome, this.idempotencyKey, this.deliveryAddress, this.birthDate, this.notes).subscribe({
      next: (response) => {
        this.purchaseSubmitting = false;
        this.applyResponse(response);
        if (response.success) this.notifications.showSuccess(response.message);
        else this.notifications.showWarning(response.message);
      },
      error: (error) => {
        this.purchaseSubmitting = false;
        this.status = 'error';
        this.message = error.error?.message ?? 'No fue posible registrar el pedido.';
        this.notifications.showError(this.message);
      },
    });
  }

  private applyResponse(response: PurchaseResponse): void {
    this.message = response.message;
    this.orderNumber = response.orderNumber ?? '';
    this.orderId = response.orderId;
    this.invoiceUrl = response.invoiceAvailable ? `/api/invoice.php?id=${response.orderId}` : '';
    this.status = response.paymentStatus === 'APROBADO' || response.paymentStatus === 'CAPTURADO' ? 'success'
      : response.paymentStatus === 'RECHAZADO' ? 'rejected'
        : response.paymentStatus === 'CANCELADO' ? 'cancelled'
          : response.paymentStatus === 'ERROR' ? 'error' : 'pending';
    if (response.success) {
      this.cart.clear();
      this.clearPayPalAttempt();
      this.clearCheckoutDraft();
    } else if (response.paymentMethod !== 'paypal') this.idempotencyKey = crypto.randomUUID();
    this.changeDetector.markForCheck();
  }

  protected retry(): void {
    this.purchaseSubmitting = false;
    if (this.method === 'paypal' && this.paypalOrderId && this.paypalOrderApproved) {
      if (!this.birthDate) {
        this.message = 'Ingresa tu fecha de nacimiento para verificar el pago pendiente.';
        this.changeDetector.markForCheck();
        return;
      }
      this.confirmPayPalOrder(this.paypalOrderId);
      return;
    }
    this.status = 'ready';
    this.paypalButtonsRendered = false;
    const sdk = (window as Window & { paypal?: PayPalSdk }).paypal;
    if (sdk) {
      this.changeDetector.detectChanges();
      setTimeout(() => this.renderPayPalButtons(sdk));
    } else if (this.paypalEnabled && this.paypalClientId) {
      this.loadPayPalSdk(this.paypalClientId);
    }
    this.validateStock();
  }

  private loadPayPalSdk(clientId: string): void {
    const sdk = (window as Window & { paypal?: PayPalSdk }).paypal;
    if (sdk) {
      this.changeDetector.detectChanges();
      this.renderPayPalButtons(sdk);
      return;
    }
    if (document.getElementById('paypal-sdk')) return;
    const script = document.createElement('script');
    script.id = 'paypal-sdk';
    script.src = `https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(clientId)}&currency=USD&intent=capture`;
    script.async = true;
    script.onload = () => {
      const loadedSdk = (window as Window & { paypal?: PayPalSdk }).paypal;
      if (loadedSdk) {
        this.changeDetector.detectChanges();
        this.renderPayPalButtons(loadedSdk);
      }
      else this.failPayPal('No fue posible cargar el botón de PayPal.');
    };
    script.onerror = () => {
      script.remove();
      this.failPayPal('No fue posible conectar con PayPal. Revisa la conexión e inténtalo de nuevo.');
    };
    document.head.appendChild(script);
  }

  private renderPayPalButtons(sdk: PayPalSdk): void {
    if (this.method !== 'paypal' || this.paypalButtonsRendered || !document.getElementById('paypal-button-container')) return;
    this.paypalButtonsRendered = true;
    sdk.Buttons({
      createOrder: async () => {
        if (!this.deliveryAddress.trim() || !this.birthDate || this.stockError) {
          throw new Error('Completa la dirección, la fecha de nacimiento y revisa el stock antes de pagar.');
        }
        if (this.purchaseSubmitting) throw new Error('Ya hay una solicitud de pago en curso.');
        this.savePayPalAttempt();
        this.purchaseSubmitting = true;
        this.changeDetector.markForCheck();
        try {
          const response = await firstValueFrom(this.purchase.createPayPalOrder(
            this.cart.items(),
            this.idempotencyKey,
            this.deliveryAddress,
            this.birthDate,
            this.notes,
          ));
          this.paypalOrderId = response.orderId;
          this.savePayPalAttempt(response.orderId, 'created');
          this.changeDetector.markForCheck();
          return response.orderId;
        } finally {
          this.purchaseSubmitting = false;
          this.changeDetector.markForCheck();
        }
      },
      onApprove: data => this.confirmPayPalOrder(data.orderID),
      onCancel: () => {
        this.purchaseSubmitting = false;
        this.idempotencyKey = crypto.randomUUID();
        this.paypalOrderId = '';
        this.paypalOrderApproved = false;
        this.savePayPalAttempt();
        this.message = 'Has cancelado el pago. No se ha confirmado la compra ni se ha descontado inventario.';
        this.notifications.showWarning(this.message);
        this.changeDetector.markForCheck();
      },
      onError: error => {
        const description = error instanceof HttpErrorResponse
          ? error.error?.message ?? 'No fue posible iniciar el pago con PayPal.'
          : error instanceof Error ? error.message : 'No fue posible iniciar el pago con PayPal.';
        this.failPayPal(description);
      },
    }).render('#paypal-button-container').catch(() => this.failPayPal('No fue posible mostrar el botón de PayPal.'));
  }

  private confirmPayPalOrder(orderId: string): void {
    if (this.purchaseSubmitting) return;
    this.paypalOrderId = orderId;
    this.paypalOrderApproved = true;
    this.savePayPalAttempt(orderId, 'approved');
    this.purchaseSubmitting = true;
    this.status = 'processing';
    this.changeDetector.markForCheck();
    this.purchase.checkout(
      this.cart.items(),
      'paypal',
      '',
      this.idempotencyKey,
      this.deliveryAddress,
      this.birthDate,
      this.notes,
      orderId,
    ).subscribe({
      next: response => {
        this.purchaseSubmitting = false;
        this.applyResponse(response);
        if (response.success) {
          this.paypalOrderId = '';
          this.clearPayPalAttempt();
          this.notifications.showSuccess(response.message);
        } else this.notifications.showWarning(response.message);
      },
      error: error => {
        const detail = error instanceof HttpErrorResponse ? error.error?.message : null;
        const message = detail
          ? `No fue posible registrar el pedido: ${detail}`
          : 'No fue posible verificar el estado del pago. Estamos comprobándolo; no vuelvas a pagar hasta verificar el resultado.';
        this.purchaseSubmitting = false;
        this.failPayPal(message);
      },
    });
  }

  private failPayPal(message: string): void {
    this.purchaseSubmitting = false;
    this.status = 'error';
    this.message = message;
    this.notifications.showError(message);
    this.changeDetector.markForCheck();
  }
}
