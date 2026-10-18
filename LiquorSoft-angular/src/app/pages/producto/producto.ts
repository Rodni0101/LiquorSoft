import { ChangeDetectorRef, Component, inject, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subscription } from 'rxjs';
import { CartService } from '../../cart.service';
import { AuthService } from '../../auth.service';
import { ThemeToggle } from '../../theme-toggle';
import { ProductVisual } from '../../product-visual';
import { NotificationBell } from '../../notification-bell';

export interface Product {
  id: number;
  name: string;
  category: string;
  description: string;
  price: number;
  stock: number;
  icon: string;
  image?: string | null;
  featured?: boolean;
}

interface ProductApiResponse {
  product: Product;
  related?: Product[];
}

@Component({
  selector: 'app-producto',
  standalone: true,
  imports: [CommonModule, RouterLink, ThemeToggle, ProductVisual, NotificationBell],
  templateUrl: './producto.html',
  styleUrl: './producto.css'
})
export class Producto implements OnInit, OnDestroy {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  private readonly changeDetector = inject(ChangeDetectorRef);
  protected readonly cart = inject(CartService);
  protected readonly auth = inject(AuthService);

  protected product: Product | null = null;
  protected related: Product[] = [];
  protected quantity = 1;
  protected loading = true;
  protected errorType: 'not_found' | 'connection' | null = null;
  protected errorMessage = '';
  protected cartMessage = '';
  private currentId = 0;
  private routeSub: Subscription | null = null;

  ngOnInit(): void {
    // Escucha cambios reactivos en los parámetros de ruta (por si se navega entre productos)
    this.routeSub = this.route.paramMap.subscribe(params => {
      const idParam = params.get('id');
      const id = Number(idParam);
      if (!idParam || isNaN(id) || id <= 0) {
        this.loading = false;
        this.errorType = 'not_found';
        this.errorMessage = 'El identificador de producto no es válido.';
        this.changeDetector.markForCheck();
        return;
      }
      this.currentId = id;
      this.loadProduct(id);
    });
  }

  ngOnDestroy(): void {
    if (this.routeSub) {
      this.routeSub.unsubscribe();
    }
  }

  protected loadProduct(id: number): void {
    this.loading = true;
    this.errorType = null;
    this.errorMessage = '';
    this.product = null;
    this.related = [];
    this.quantity = 1;

    this.http.get<ProductApiResponse>(`/api/products.php?id=${id}`).subscribe({
      next: (response) => {
        this.product = response.product;
        this.related = response.related ?? [];
        this.loading = false;
        this.changeDetector.markForCheck();
      },
      error: (error) => {
        this.loading = false;
        if (error.status === 404 || error.status === 422) {
          this.errorType = 'not_found';
          this.errorMessage = error.error?.message ?? 'El producto solicitado no existe o fue descontinuado.';
        } else {
          this.errorType = 'connection';
          this.errorMessage = 'No fue posible cargar el producto. Comprueba tu conexión con el servidor.';
        }
        this.changeDetector.markForCheck();
      },
    });
  }

  protected retry(): void {
    if (this.currentId > 0) {
      this.loadProduct(this.currentId);
    }
  }

  protected addToCart(): void {
    if (!this.product || this.product.stock <= 0) return;

    let added = 0;
    for (let index = 0; index < this.quantity; index += 1) {
      const success = this.cart.add({
        id: this.product.id,
        name: this.product.name,
        price: this.product.price,
        icon: this.product.icon,
        stock: this.product.stock,
        image: this.product.image,
        category: this.product.category
      });
      if (success) added += 1;
    }

    this.cartMessage = added
      ? `${added} ${added === 1 ? 'unidad añadida' : 'unidades añadidas'} de ${this.product.name} al carrito.`
      : `${this.product.name} ya alcanzó el límite de stock en tu carrito.`;

    this.changeDetector.markForCheck();
    window.setTimeout(() => {
      this.cartMessage = '';
      this.changeDetector.markForCheck();
    }, 3500);
  }

  protected increase(): void {
    if (this.product && this.quantity < this.product.stock) {
      this.quantity += 1;
    }
  }

  protected decrease(): void {
    if (this.quantity > 1) {
      this.quantity -= 1;
    }
  }

  protected formatPrice(value: number): string {
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      maximumFractionDigits: 0
    }).format(value);
  }
}
