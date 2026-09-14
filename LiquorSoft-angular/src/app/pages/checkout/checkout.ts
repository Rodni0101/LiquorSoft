import { Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { CartService } from '../../cart.service';
import { AuthService } from '../../auth.service';
import { PaymentMethod, PaymentService, PaymentStatus } from '../../payment.service';
import { PurchaseService } from '../../purchase.service';
import { formatCop } from '../../money';

interface CheckoutProduct {
  id?: number;
  stock?: number;
}

@Component({
  selector: 'app-checkout',
  standalone: true,
  imports: [RouterLink, FormsModule],
  templateUrl: './checkout.html',
  styleUrl: './checkout.css',
})
export class Checkout implements OnInit {
  protected readonly cart = inject(CartService);
  protected readonly auth = inject(AuthService);
  private readonly http = inject(HttpClient);
  protected readonly payment = inject(PaymentService);
  private readonly purchase = inject(PurchaseService);
  protected method: PaymentMethod = 'nequi';
  protected status: PaymentStatus | 'ready' = 'ready';
  protected message = '';
  protected stockError = '';
  protected purchaseSubmitting = false;
  protected notes = '';

  ngOnInit(): void {
    this.validateStock();
  }

  protected formatPrice(value: number): string {
    return formatCop(value);
  }

  protected validateStock(): void {
    if (!this.cart.items().length) return;
    this.http.get<{ products: CheckoutProduct[] }>('/api/products.php').subscribe({
      next: response => {
        const problems = this.cart.items().flatMap(item => {
          const product = response.products.find(candidate => candidate.id === item.id);
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

  protected preparePayment(): void {
    if (!this.cart.items().length || this.stockError) return;
    const result = this.payment.preparePayment({
      method: this.method,
      amount: this.cart.total(),
      items: this.cart.items().map(item => ({ id: item.id, quantity: item.quantity })),
    });
    this.status = result.status;
    this.message = result.message;
  }

  protected registerPurchase(): void {
    if (this.purchaseSubmitting || this.stockError || !this.cart.items().length) return;
    this.purchaseSubmitting = true;
    this.purchase.checkout(this.cart.items(), this.method, this.notes).subscribe({
      next: response => {
        this.purchaseSubmitting = false;
        this.status = 'success';
        this.message = `${response.message} Número de pedido: ${response.orderNumber ?? response.saleId}.`;
        this.cart.clear();
      },
      error: error => {
        this.purchaseSubmitting = false;
        this.status = 'error';
        this.message = error.error?.message ?? 'No fue posible registrar el pedido.';
      },
    });
  }
}
