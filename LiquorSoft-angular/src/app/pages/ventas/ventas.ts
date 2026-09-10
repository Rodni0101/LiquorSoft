import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AdminShell } from '../../admin-shell';
import { formatCop } from '../../money';

interface SaleItem { name: string; quantity: number; price: number; }
interface Sale { id: number; total: number; status: string; createdAt: string; customer: string; paymentMethod: string; items: SaleItem[]; }

@Component({ imports: [AdminShell], selector: 'app-ventas', styleUrl: './ventas.css', templateUrl: './ventas.html' })
export class Ventas implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);
  protected sales: Sale[] = [];
  protected loading = true;
  protected errorMessage = '';

  ngOnInit(): void { this.load(); }

  protected load(): void {
    this.loading = true;
    this.http.get<{ sales: Sale[] }>('/api/admin/sales.php', { withCredentials: true }).subscribe({
      next: response => { this.sales = response.sales; this.loading = false; this.changeDetector.markForCheck(); },
      error: error => { this.errorMessage = error.error?.message ?? 'No fue posible cargar las ventas.'; this.loading = false; this.changeDetector.markForCheck(); },
    });
  }

  protected formatPrice(value: number): string { return formatCop(value); }
  protected formatDate(value: string): string { return new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)); }
}
