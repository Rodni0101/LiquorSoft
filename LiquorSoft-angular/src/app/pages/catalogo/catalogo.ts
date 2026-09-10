import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RouterLink, ActivatedRoute } from '@angular/router';
import { AuthService } from '../../auth.service';
import { CartService } from '../../cart.service';

interface CatalogProduct {
  id?: number;
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
  private readonly route = inject(ActivatedRoute);
  protected readonly auth = inject(AuthService);
  protected readonly cart = inject(CartService);
  protected categories = ['Todos'];
  protected readonly selectedCategory = signal('Todos');
  protected readonly searchTerm = signal('');

  protected readonly products = signal<CatalogProduct[]>([]);
  protected loading = true;
  protected errorMessage = '';
  protected cartMessage = '';

  ngOnInit(): void {
    this.http.get<{ products: CatalogProduct[] }>('/api/products.php').subscribe({
      next: (response) => {
        this.products.set(response.products);
        this.categories = ['Todos', ...new Set(response.products.map(product => product.category))];
        const params = this.route.snapshot.queryParamMap;
        const category = params.get('category'); const search = params.get('search');
        if (category && this.categories.includes(category)) this.selectedCategory.set(category);
        if (search) this.searchTerm.set(search);
        this.loading = false;
      },
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

  protected addToCart(product: CatalogProduct): void {
    this.cart.add({ id: product.id, name: product.name, price: product.price, icon: product.icon, stock: product.stock });
    this.cartMessage = product.stock ? `${product.name} está en tu carrito.` : `${product.name} está agotado.`;
    window.setTimeout(() => this.cartMessage = '', 2600);
  }
}
