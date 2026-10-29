import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../auth.service';
import { formatCop } from '../../money';
import { ThemeToggle } from '../../theme-toggle';
import { NotificationBell } from '../../notification-bell';

interface OrderItem {
  name: string;
  quantity: number;
  price: number;
}

interface Order {
  id: number;
  number: string;
  total: number;
  status: string;
  createdAt: string;
  paymentMethod: string;
  paymentStatus: string;
  invoiceUrl: string | null;
  items: OrderItem[];
}

@Component({
  selector: 'app-pedidos',
  standalone: true,
  imports: [RouterLink, ThemeToggle, NotificationBell],
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
    if (!value) return '';
    try {
      return new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value.replace(' ', 'T')));
    } catch {
      return value;
    }
  }

  protected statusLabel(status: string): string {
    const labels: Record<string, string> = {
      PENDIENTE: 'Pendiente',
      CONFIRMADO: 'Confirmado',
      PREPARANDO: 'Preparando',
      LISTO_PARA_COORDINAR: 'Listo para coordinar',
      COMPLETADO: 'Completado',
      ENVIADO: 'Enviado',
      ENTREGADO: 'Entregado',
      CANCELADO: 'Cancelado',
    };
    return labels[(status ?? '').toUpperCase()] || status;
  }

  protected paymentLabel(status: string): string {
    const labels: Record<string, string> = {
      PENDIENTE: 'Pago pendiente',
      APROBADO: 'Aprobado',
      CAPTURADO: 'Pagado',
      RECHAZADO: 'Rechazado',
      REEMBOLSADO: 'Reembolsado',
      CANCELADO: 'Cancelado',
    };
    return labels[(status ?? '').toUpperCase()] || status;
  }

  protected statusClass(status: string): string {
    return 'status-' + (status ?? '').toLowerCase();
  }

  protected openInvoice(url: string): void {
    window.location.assign(url);
  }
}
