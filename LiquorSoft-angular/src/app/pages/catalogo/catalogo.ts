import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../auth.service';

interface CatalogProduct {
  name: string;
  category: string;
  description: string;
  price: number;
  stock?: number;
  icon: string;
  featured?: boolean;
}

@Component({
  selector: 'app-catalogo',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './catalogo.html',
  styleUrl: './catalogo.css',
})
export class Catalogo implements OnInit {
  private readonly http = inject(HttpClient);
  protected readonly auth = inject(AuthService);
  protected readonly categories = ['Todos', 'Licores', 'Vinos', 'Cervezas', 'Mixers'];
  protected readonly selectedCategory = signal('Todos');
  protected readonly searchTerm = signal('');

  protected readonly products = signal<CatalogProduct[]>([]);
  protected loading = true;
  protected errorMessage = '';

  ngOnInit(): void {
    this.http.get<{ products: CatalogProduct[] }>('/api/products.php').subscribe({
      next: (response) => { this.products.set(response.products); this.loading = false; },
      error: () => { this.errorMessage = 'No fue posible cargar el catálogo. Verifica la conexión con la base de datos.'; this.loading = false; },
    });
  }

  protected readonly filteredProducts = computed(() => {
    const category = this.selectedCategory();
    const term = this.searchTerm().trim().toLowerCase();
    return this.products().filter((product) => {
      const matchesCategory = category === 'Todos' || product.category === category;
      const matchesSearch = !term || `${product.name} ${product.description} ${product.category}`.toLowerCase().includes(term);
      return matchesCategory && matchesSearch;
    });
  });

  protected selectCategory(category: string): void {
    this.selectedCategory.set(category);
  }

  protected updateSearch(event: Event): void {
    this.searchTerm.set((event.target as HTMLInputElement).value);
  }

  protected formatPrice(price: number): string {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(price);
  }
}
