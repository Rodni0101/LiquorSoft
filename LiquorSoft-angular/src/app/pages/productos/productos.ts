import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { AdminShell } from '../../admin-shell';

interface Product { id: number; name: string; category: string; description: string; price: number; stock: number; featured: boolean; }
@Component({ selector: 'app-productos', standalone: true, imports: [RouterLink, AdminShell], styleUrl: './productos.css', templateUrl: './productos.html' })
export class Productos implements OnInit {
  private readonly http = inject(HttpClient); private readonly changeDetector = inject(ChangeDetectorRef); protected products: Product[] = []; protected loading = true; protected errorMessage = '';
  ngOnInit(): void { this.http.get<{ products: Product[] }>('/api/products.php').subscribe({ next: (r) => { this.products = r.products; this.loading = false; this.changeDetector.markForCheck(); }, error: (error) => { console.error('LiquorSoft: no se pudieron cargar los productos.', error); this.errorMessage = 'No fue posible cargar los productos.'; this.loading = false; this.changeDetector.markForCheck(); } }); }
  protected formatPrice(value: number): string { return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value); }
}
