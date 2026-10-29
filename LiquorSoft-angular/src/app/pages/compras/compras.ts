import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { AdminShell } from '../../admin-shell';
import { formatCop } from '../../money';

interface Product {
  id: number;
  name: string;
  stock: number;
  purchasePrice?: number;
  active?: boolean;
}

interface Provider {
  id: number;
  name: string;
  active: boolean;
}

interface Purchase {
  id: number;
  invoice: string;
  purchaseDate: string;
  total: number;
  status: string;
  supplier: string;
}

interface PurchaseLine {
  productId: number;
  name: string;
  quantity: number;
  cost: number;
  batch: string;
  expiryDate: string;
}

@Component({
  selector: 'app-compras',
  standalone: true,
  imports: [AdminShell, FormsModule],
  templateUrl: './compras.html',
  styleUrl: './compras.css',
})
export class Compras implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);

  protected products: Product[] = [];
  protected providers: Provider[] = [];
  protected purchases: Purchase[] = [];
  protected lines: PurchaseLine[] = [];
  protected loading = true;
  protected saving = false;
  protected errorMessage = '';
  protected successMessage = '';

  protected supplierId = 0;
  protected productId = 0;
  protected quantity = 1;
  protected cost = 0;
  protected batch = '';
  protected expiryDate = '';
  protected invoice = '';
  protected purchaseDate = this.today();

  private today(): string {
    const now = new Date();
    return new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  }
  protected observation = '';

  ngOnInit(): void {
    this.load();
  }

  protected load(): void {
    this.loading = true;
    this.http.get<{ products: Product[] }>('/api/admin/products.php', { withCredentials: true }).subscribe({
      next: (response) => {
        this.products = response.products ?? [];
        this.changeDetector.markForCheck();
      },
      error: () => {
        this.errorMessage = 'No fue posible cargar los productos.';
        this.changeDetector.markForCheck();
      },
    });

    this.http.get<{ providers: Provider[] }>('/api/admin/providers.php', { withCredentials: true }).subscribe({
      next: (response) => {
        this.providers = response.providers ?? [];
        this.changeDetector.markForCheck();
      },
      error: () => {
        this.errorMessage = 'No fue posible cargar los proveedores.';
        this.changeDetector.markForCheck();
      },
    });

    this.http.get<{ purchases: Purchase[] }>('/api/admin/purchases.php', { withCredentials: true }).subscribe({
      next: (response) => {
        this.purchases = response.purchases ?? [];
        this.loading = false;
        this.changeDetector.markForCheck();
      },
      error: (error) => {
        this.errorMessage = error.error?.message ?? 'No fue posible cargar las compras.';
        this.loading = false;
        this.changeDetector.markForCheck();
      },
    });
  }

  protected get activeProducts(): Product[] {
    return this.products.filter(product => product.active !== false);
  }

  protected get activeProviders(): Provider[] {
    return this.providers.filter(provider => provider.active);
  }

  protected get purchaseTotal(): number {
    return this.lines.reduce((total, line) => total + line.quantity * line.cost, 0);
  }

  protected onProductChange(): void {
    const product = this.products.find(item => item.id === this.productId);
    if (product) {
      this.cost = product.purchasePrice ?? 0;
    }
  }

  protected addLine(): void {
    this.errorMessage = '';
    this.successMessage = '';
    const product = this.products.find(item => item.id === this.productId);
    if (!product) {
      this.errorMessage = 'Selecciona un producto para agregar a la compra.';
      return;
    }
    if (!Number.isInteger(this.quantity) || this.quantity < 1) {
      this.errorMessage = 'La cantidad debe ser un entero mayor o igual a 1.';
      return;
    }
    if (!Number.isFinite(this.cost) || this.cost < 0) {
      this.errorMessage = 'El costo unitario debe ser un valor no negativo.';
      return;
    }
    if (this.lines.some(line => line.productId === product.id)) {
      this.errorMessage = `"${product.name}" ya está en la compra. Elimina la línea para cambiarla.`;
      return;
    }

    this.lines = [...this.lines, {
      productId: product.id,
      name: product.name,
      quantity: this.quantity,
      cost: this.cost,
      batch: this.batch.trim(),
      expiryDate: this.expiryDate,
    }];

    this.productId = 0;
    this.quantity = 1;
    this.cost = 0;
    this.batch = '';
    this.expiryDate = '';
  }

  protected removeLine(index: number): void {
    this.lines = this.lines.filter((_, position) => position !== index);
  }

  protected clearLines(): void {
    this.lines = [];
  }

  protected save(): void {
    this.errorMessage = '';
    this.successMessage = '';

    if (!this.supplierId) {
      this.errorMessage = 'Selecciona un proveedor.';
      return;
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(this.purchaseDate)) {
      this.errorMessage = 'La fecha de compra no es válida.';
      return;
    }
    if (!this.lines.length) {
      this.errorMessage = 'Agrega al menos un producto a la compra.';
      return;
    }

    this.saving = true;
    this.http.post<{ message?: string }>('/api/admin/purchases.php', {
      supplierId: this.supplierId,
      invoice: this.invoice.trim(),
      purchaseDate: this.purchaseDate,
      observation: this.observation.trim(),
      items: this.lines.map(line => ({
        productId: line.productId,
        quantity: line.quantity,
        cost: line.cost,
        batch: line.batch,
        expiryDate: line.expiryDate,
      })),
    }, { withCredentials: true }).subscribe({
      next: (response) => {
        this.saving = false;
        this.successMessage = response.message ?? 'Compra registrada y stock actualizado.';
        this.lines = [];
        this.invoice = '';
        this.observation = '';
        this.load();
        this.changeDetector.markForCheck();
      },
      error: (error) => {
        this.saving = false;
        this.errorMessage = error.error?.message ?? 'No fue posible registrar la compra.';
        this.changeDetector.markForCheck();
      },
    });
  }

  protected statusLabel(status: string): string {
    const labels: Record<string, string> = {
      PENDIENTE: 'Pendiente',
      RECIBIDA: 'Recibida',
      CANCELADA: 'Cancelada',
    };
    return labels[(status ?? '').toUpperCase()] ?? status;
  }

  protected formatPrice(value: number): string {
    return formatCop(value);
  }

  protected formatDate(value: string): string {
    if (!value) return '';
    try {
      return new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium' }).format(new Date(value.replace(' ', 'T')));
    } catch {
      return value;
    }
  }
}
