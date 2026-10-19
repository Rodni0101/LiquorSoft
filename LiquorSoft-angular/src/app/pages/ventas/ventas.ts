import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { AdminShell } from '../../admin-shell';
import { formatCop } from '../../money';
import { NotificationService } from '../../notification.service';

interface SaleItem { name: string; quantity: number; price: number; }
interface Sale { id: number; total: number; status: string; createdAt: string; customer: string; paymentMethod: string; items: SaleItem[]; }

interface OrderHistory {
  previousStatus: string;
  status: string;
  fulfillmentMethod: string;
  fulfillmentDetails: string;
  createdAt: string;
  actor: string;
}

interface AdminOrder {
  id: number;
  number: string;
  customer: string;
  total: number;
  status: string;
  paymentStatus: string;
  createdAt: string;
  fulfillmentMethod: string;
  fulfillmentDetails: string;
  notes: string;
  items: { name: string; quantity: number; price: number }[];
  history: OrderHistory[];
}

@Component({
  selector: 'app-ventas',
  standalone: true,
  imports: [CommonModule, FormsModule, AdminShell],
  templateUrl: './ventas.html',
  styleUrl: './ventas.css'
})
export class Ventas implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);
  private readonly notification = inject(NotificationService);

  protected activeTab: 'pedidos' | 'ventas' = 'pedidos';

  // Pedidos
  protected orders: AdminOrder[] = [];
  protected loadingOrders = true;
  protected selectedOrder: AdminOrder | null = null;
  protected updatingStatus = false;
  protected nextStatus = '';
  protected nextMethod = 'POR_COORDINAR';
  protected nextDetails = '';
  protected orderSearch = '';
  protected orderStatusFilter = 'todos';

  // Ventas
  protected sales: Sale[] = [];
  protected loadingSales = true;
  protected errorMessage = '';
  protected successMessage = '';
  protected saleSearch = '';
  protected saleStatusFilter = 'todos';

  ngOnInit(): void {
    this.loadOrders();
    this.loadSales();
  }

  protected switchTab(tab: 'pedidos' | 'ventas'): void {
    this.activeTab = tab;
    this.selectedOrder = null;
    this.errorMessage = '';
    this.successMessage = '';
  }

  protected loadOrders(): void {
    this.loadingOrders = true;
    this.errorMessage = '';
    this.http.get<{ orders: AdminOrder[] }>('/api/admin/orders.php', { withCredentials: true }).subscribe({
      next: response => {
        this.orders = response.orders;
        this.loadingOrders = false;
        if (this.selectedOrder) {
          this.selectedOrder = this.orders.find(o => o.id === this.selectedOrder?.id) ?? null;
        }
        this.changeDetector.markForCheck();
      },
      error: error => {
        this.errorMessage = error.error?.message ?? 'No fue posible cargar los pedidos.';
        this.loadingOrders = false;
        this.changeDetector.markForCheck();
      },
    });
  }

  protected loadSales(): void {
    this.loadingSales = true;
    this.http.get<{ sales: Sale[] }>('/api/admin/sales.php', { withCredentials: true }).subscribe({
      next: response => {
        this.sales = response.sales;
        this.loadingSales = false;
        this.changeDetector.markForCheck();
      },
      error: error => {
        this.errorMessage = error.error?.message ?? 'No fue posible cargar las ventas.';
        this.loadingSales = false;
        this.changeDetector.markForCheck();
      },
    });
  }

  protected selectOrder(order: AdminOrder): void {
    this.selectedOrder = order;
    this.nextMethod = order.fulfillmentMethod || 'POR_COORDINAR';
    this.nextDetails = order.fulfillmentDetails || '';
    const available = this.getAllowedTransitions(order.status);
    this.nextStatus = available[0] ?? order.status;
    this.errorMessage = '';
    this.successMessage = '';
  }

  protected closeOrderModal(): void {
    this.selectedOrder = null;
    this.errorMessage = '';
    this.successMessage = '';
  }

  protected getAllowedTransitions(currentStatus: string): string[] {
    const transitions: Record<string, string[]> = {
      CONFIRMADO: ['PREPARANDO', 'CANCELADO'],
      PREPARANDO: ['LISTO_PARA_COORDINAR', 'CANCELADO'],
      LISTO_PARA_COORDINAR: ['COMPLETADO', 'ENVIADO', 'CANCELADO'],
      ENVIADO: ['ENTREGADO', 'CANCELADO'],
      COMPLETADO: [],
      ENTREGADO: [],
      CANCELADO: [],
      PENDIENTE: ['CANCELADO'],
    };
    return transitions[currentStatus] ?? [];
  }

  protected async updateOrderStatus(): Promise<void> {
    if (!this.selectedOrder || !this.nextStatus) return;

    if (this.nextMethod !== 'POR_COORDINAR' && !this.nextDetails.trim()) {
      this.errorMessage = 'Indica la dirección o el punto de recogida acordado.';
      return;
    }

    const transitions = this.getAllowedTransitions(this.selectedOrder.status);
    if (!transitions.includes(this.nextStatus)) {
      this.errorMessage = 'La transición de estado seleccionada no es válida.';
      return;
    }

    const confirmado = await this.notification.confirm({
      title: 'Confirmar cambio de estado',
      message: `¿Cambiar el pedido #${this.selectedOrder.number} de "${this.selectedOrder.status}" a "${this.nextStatus}"? El cliente recibirá una notificación automática.`,
      acceptLabel: 'Sí, cambiar estado',
    });
    if (!confirmado) return;

    this.updatingStatus = true;
    this.errorMessage = '';
    this.successMessage = '';

    const payload = {
      orderId: this.selectedOrder.id,
      status: this.nextStatus,
      fulfillmentMethod: this.nextMethod,
      fulfillmentDetails: this.nextDetails.trim(),
    };

    this.http.patch<{ message: string; order: unknown }>('/api/admin/orders.php', payload, { withCredentials: true }).subscribe({
      next: (res) => {
        this.updatingStatus = false;
        this.successMessage = `${res.message} Se envió una notificación automática al cliente.`;
        this.notification.showSuccess(this.successMessage);
        this.loadOrders();
        this.closeOrderModal();
      },
      error: (err) => {
        this.updatingStatus = false;
        this.errorMessage = err.error?.message ?? 'No fue posible actualizar el estado del pedido.';
        this.notification.showError(this.errorMessage);
        this.changeDetector.markForCheck();
      },
    });
  }

  protected filteredOrders(): AdminOrder[] {
    let result = this.orders;
    if (this.orderSearch.trim()) {
      const term = this.orderSearch.trim().toLowerCase();
      result = result.filter(o =>
        o.number.toLowerCase().includes(term) ||
        o.customer.toLowerCase().includes(term) ||
        o.status.toLowerCase().includes(term)
      );
    }
    if (this.orderStatusFilter !== 'todos') {
      result = result.filter(o => o.status.toLowerCase() === this.orderStatusFilter);
    }
    return result;
  }

  protected filteredSales(): Sale[] {
    let result = this.sales;
    if (this.saleSearch.trim()) {
      const term = this.saleSearch.trim().toLowerCase();
      result = result.filter(s =>
        s.customer.toLowerCase().includes(term) ||
        s.status.toLowerCase().includes(term) ||
        s.paymentMethod.toLowerCase().includes(term)
      );
    }
    if (this.saleStatusFilter !== 'todos') {
      result = result.filter(s => s.status.toLowerCase() === this.saleStatusFilter);
    }
    return result;
  }

  protected formatPrice(value: number): string { return formatCop(value); }
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
    return labels[status] || status;
  }
}
