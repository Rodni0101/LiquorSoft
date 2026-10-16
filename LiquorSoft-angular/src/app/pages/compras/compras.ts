import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { AdminShell } from '../../admin-shell';
import { formatCop } from '../../money';

interface Product { id: number; name: string; stock: number; }
interface Provider { id: number; name: string; }
interface Purchase { id: number; invoice: string; purchaseDate: string; total: number; status: string; supplier: string; }
@Component({ selector: 'app-compras', standalone: true, imports: [AdminShell, FormsModule], templateUrl: './compras.html', styleUrl: './compras.css' })
export class Compras implements OnInit {
  private readonly http = inject(HttpClient); private readonly changeDetector = inject(ChangeDetectorRef);
  protected products: Product[] = []; protected providers: Provider[] = []; protected purchases: Purchase[] = []; protected loading = true; protected saving = false; protected errorMessage = ''; protected successMessage = '';
  protected supplierId = 0; protected productId = 0; protected quantity = 1; protected cost = 0; protected batch = ''; protected expiryDate = ''; protected invoice = ''; protected purchaseDate = new Date().toISOString().slice(0, 10);
  ngOnInit(): void { this.load(); }
  protected load(): void { this.http.get<{ products: Product[] }>('/api/products.php').subscribe({ next: response => this.products = response.products, error: () => this.errorMessage = 'No fue posible cargar los productos.' }); this.http.get<{ providers: Provider[] }>('/api/admin/providers.php', { withCredentials: true }).subscribe({ next: response => this.providers = response.providers, error: () => this.errorMessage = 'No fue posible cargar los proveedores.' }); this.http.get<{ purchases: Purchase[] }>('/api/admin/purchases.php', { withCredentials: true }).subscribe({ next: response => { this.purchases = response.purchases; this.loading = false; this.changeDetector.markForCheck(); }, error: error => { this.errorMessage = error.error?.message ?? 'No fue posible cargar las compras.'; this.loading = false; this.changeDetector.markForCheck(); } }); }
  protected save(): void { this.errorMessage = ''; this.successMessage = ''; if (!this.supplierId || !this.productId || this.quantity < 1 || this.cost < 0) { this.errorMessage = 'Completa proveedor, producto, cantidad y costo.'; return; } this.saving = true; this.http.post<{ message: string }>('/api/admin/purchases.php', { supplierId: this.supplierId, invoice: this.invoice, purchaseDate: this.purchaseDate, items: [{ productId: this.productId, quantity: this.quantity, cost: this.cost, batch: this.batch, expiryDate: this.expiryDate }] }, { withCredentials: true }).subscribe({ next: response => { this.saving = false; this.successMessage = response.message; this.quantity = 1; this.cost = 0; this.batch = ''; this.expiryDate = ''; this.load(); }, error: error => { this.saving = false; this.errorMessage = error.error?.message ?? 'No fue posible registrar la compra.'; this.changeDetector.markForCheck(); } }); }
  protected formatPrice(value: number): string { return formatCop(value); }
}
