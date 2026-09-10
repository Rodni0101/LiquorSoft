import { Component, inject, OnInit } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { CartService } from '../../cart.service';

interface Product { id: number; name: string; category: string; description: string; price: number; stock: number; icon: string; }

@Component({ selector: 'app-producto', standalone: true, imports: [RouterLink], templateUrl: './producto.html', styleUrl: './producto.css' })
export class Producto implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  protected readonly cart = inject(CartService);
  protected product: Product | null = null;
  protected related: Product[] = [];
  protected quantity = 1;
  protected loading = true;
  protected errorMessage = '';
  protected cartMessage = '';

  ngOnInit(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    this.http.get<{ products: Product[] }>('/api/products.php').subscribe({
      next: response => { this.product = response.products.find(item => item.id === id) ?? null; this.related = response.products.filter(item => item.id !== id && item.category === this.product?.category).slice(0, 3); this.loading = false; if (!this.product) this.errorMessage = 'No encontramos ese producto.'; },
      error: () => { this.loading = false; this.errorMessage = 'No fue posible cargar el producto.'; },
    });
  }

  protected addToCart(): void {
    if (!this.product) return;
    this.cart.add({ id: this.product.id, name: this.product.name, price: this.product.price, icon: this.product.icon, stock: this.product.stock });
    this.cartMessage = `${this.product.name} está en tu carrito.`;
    window.setTimeout(() => this.cartMessage = '', 3000);
  }
  protected increase(): void { if (this.product) this.quantity = Math.min(this.quantity + 1, this.product.stock); }
  protected decrease(): void { this.quantity = Math.max(1, this.quantity - 1); }
  protected formatPrice(value: number): string { return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value); }
}
