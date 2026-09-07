import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../auth.service';
import { CartService } from '../../cart.service';

interface PublicProduct { id?: number; name: string; category: string; description?: string; price: number; icon: string; featured?: boolean; stock?: number; }

@Component({ selector: 'app-inicio', standalone: true, imports: [FormsModule, RouterLink], templateUrl: './inicio.html', styleUrl: './inicio.css' })
export class Inicio implements OnInit {
  private readonly http = inject(HttpClient); private readonly changeDetector = inject(ChangeDetectorRef);
  protected readonly auth = inject(AuthService); protected readonly cart = inject(CartService);
  protected products: PublicProduct[] = []; protected loading = true; protected error = false; protected menuOpen = false; protected searchTerm = '';
  protected showAgeGate = localStorage.getItem('liquorsoft-age-verified') !== 'true';
  protected readonly whatsappNumber = '57XXXXXXXXXX';
  ngOnInit(): void { this.http.get<{ products: PublicProduct[] }>('/api/products.php').subscribe({ next: r => { this.products = r.products; this.loading = false; this.changeDetector.markForCheck(); }, error: () => { this.error = true; this.loading = false; this.changeDetector.markForCheck(); } }); }
  protected get categories(): string[] { return [...new Set(this.products.map(p => p.category))]; }
  protected categoryIcon(category: string): string { return this.products.find(p => p.category === category)?.icon || '✦'; }
  protected get featuredProducts(): PublicProduct[] { return this.products.filter(p => p.featured).slice(0, 4); }
  protected get offerProducts(): PublicProduct[] { return this.products.filter(p => p.stock && p.stock < 10).slice(0, 4); }
  protected formatPrice(v: number): string { return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v); }
  protected addToCart(p: PublicProduct): void { this.cart.add({ id: p.id, name: p.name, price: p.price, icon: p.icon }); }
  protected verifyAge(): void { localStorage.setItem('liquorsoft-age-verified', 'true'); this.showAgeGate = false; }
  protected exitAgeGate(): void { window.location.href = 'https://www.google.com'; }
  protected whatsappUrl(): string { return `https://wa.me/${this.whatsappNumber}?text=${encodeURIComponent('Hola, necesito ayuda con un producto de LiquorSoft.')}`; }
}
