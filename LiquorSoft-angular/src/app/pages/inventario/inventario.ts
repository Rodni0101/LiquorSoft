import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { AdminShell } from '../../admin-shell';
import { formatCop } from '../../money';

interface InventoryItem { id: number; name: string; category: string; stock: number; minStock: number; status: 'ok' | 'bajo' | 'agotado'; icon: string; }
interface Movement { id: number; quantity: number; reason: string; createdAt: string; productName: string; userName: string; }

@Component({
  imports: [AdminShell, FormsModule],
  selector: 'app-inventario',
  styleUrl: './inventario.css',
  templateUrl: './inventario.html',
})
export class Inventario implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);
  protected items: InventoryItem[] = [];
  protected movements: Movement[] = [];
  protected loading = true;
  protected saving = false;
  protected errorMessage = '';
  protected successMessage = '';
  protected selectedProductId = 0;
  protected movementType: 'entrada' | 'salida' = 'entrada';
  protected quantity = 1;
  protected reason = '';

  ngOnInit(): void { this.load(); }

  protected load(): void {
    this.loading = true;
    this.http.get<{ items: InventoryItem[]; movements: Movement[] }>('/api/admin/inventory.php', { withCredentials: true }).subscribe({
      next: response => { this.items = response.items; this.movements = response.movements; this.loading = false; this.changeDetector.markForCheck(); },
      error: error => { this.errorMessage = error.error?.message ?? 'No fue posible cargar el inventario.'; this.loading = false; this.changeDetector.markForCheck(); },
    });
  }

  protected submitAdjustment(): void {
    this.errorMessage = ''; this.successMessage = '';
    if (!this.selectedProductId || !Number.isInteger(this.quantity) || this.quantity < 1 || this.reason.trim().length < 3) {
      this.errorMessage = 'Selecciona un producto, una cantidad válida y un motivo de al menos 3 caracteres.';
      return;
    }
    this.saving = true;
    const signedQuantity = this.movementType === 'entrada' ? this.quantity : -this.quantity;
    this.http.post<{ message?: string }>('/api/admin/inventory.php', { productId: this.selectedProductId, quantity: signedQuantity, reason: this.reason.trim() }, { withCredentials: true }).subscribe({
      next: response => { this.saving = false; this.successMessage = response.message ?? 'Inventario actualizado.'; this.quantity = 1; this.reason = ''; this.load(); },
      error: error => { this.saving = false; this.errorMessage = error.error?.message ?? 'No fue posible actualizar el inventario.'; this.changeDetector.markForCheck(); },
    });
  }

  protected formatPrice(value: number): string { return formatCop(value); }
  protected formatDate(value: string): string { return new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)); }
}
