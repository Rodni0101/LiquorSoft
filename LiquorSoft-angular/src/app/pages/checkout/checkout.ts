import { Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
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
  protected readonly payment = inject(PaymentService);
  private readonly purchase = inject(PurchaseService);
  private readonly notifications = inject(NotificationService);
  protected method: PaymentMethod = 'nequi_simulado';
  protected simulationOutcome = 'approved';
  protected simulatorEnabled = false;
  protected status: PaymentStatus | 'ready' = 'ready';
  protected message = '';
  protected stockError = '';
  protected purchaseSubmitting = false;
  protected notes = '';
  protected deliveryAddress = '';
  protected birthDate = '';
  protected orderNumber = '';
  protected orderId: number | null = null;
  protected invoiceUrl = '';
  private idempotencyKey = crypto.randomUUID();

  ngOnInit(): void {
    this.validateStock();
    this.http.get<{ simulatorEnabled: boolean }>('/api/payment-config.php').subscribe({
      next: config => this.simulatorEnabled = config.simulatorEnabled,
      error: () => this.simulatorEnabled = false,
    });
  }

  protected formatPrice(value: number): string {
    return formatCop(value);
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
      },
      error: () => {
        this.stockError = 'No fue posible validar el stock. Intenta nuevamente.';
      },
    });
  }

  protected registerPurchase(): void {
    if (
      this.purchaseSubmitting ||
      !this.simulatorEnabled ||
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
    this.status = response.paymentStatus === 'APROBADO' ? 'success'
      : response.paymentStatus === 'RECHAZADO' ? 'rejected'
        : response.paymentStatus === 'CANCELADO' ? 'cancelled'
          : response.paymentStatus === 'ERROR' ? 'error' : 'pending';
    if (response.success) this.cart.clear();
    else this.idempotencyKey = crypto.randomUUID();
  }

  protected retry(): void {
    this.status = 'ready';
    this.validateStock();
  }
}
