import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../auth.service';
import { formatCop } from '../../money';

interface OrderItem {
  name: string;
  quantity: number;
  price: number;
}

interface Order {
  id: number;
  total: number;
  status: string;
  createdAt: string;
  paymentMethod: string;
  items: OrderItem[];
}

@Component({
  selector: 'app-pedidos',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './pedidos.html',
  styleUrl: './pedidos.css',
})
export class Pedidos implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);
  protected readonly auth = inject(AuthService);
  protected orders: Order[] = [];
  protected loading = true;
  protected errorMessage = '';

  ngOnInit(): void {
    this.http.get<{ orders: Order[] }>('/api/orders.php').subscribe({
      next: response => {
        this.orders = response.orders;
        this.loading = false;
        this.changeDetector.markForCheck();
      },
      error: error => {
        this.loading = false;
        this.errorMessage = error.error?.message ?? 'No fue posible cargar tus pedidos.';
        this.changeDetector.markForCheck();
      },
    });
  }

  protected formatPrice(value: number): string {
    return formatCop(value);
  }

  protected formatDate(value: string): string {
    return new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
  }
}
