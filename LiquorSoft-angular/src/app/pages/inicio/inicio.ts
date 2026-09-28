import { ChangeDetectorRef, Component, HostListener, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AuthService } from '../../auth.service';
import { RevealDirective } from '../../reveal.directive';
import { ThemeToggle } from '../../theme-toggle';

interface PublicSummary {
  products: number;
  monthlySales: number;
  availability: number;
  units: number;
}

interface PublicProduct {
  name: string;
  category: string;
  price: number;
  icon: string;
  featured?: boolean;
  stock?: number;
}

@Component({
  selector: 'app-inicio',
  standalone: true,
  imports: [RouterLink, RevealDirective, ThemeToggle],
  templateUrl: './inicio.html',
  styleUrl: './inicio.css'
})
export class Inicio implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);
  private readonly route = inject(ActivatedRoute);
  protected readonly auth = inject(AuthService);
  protected summary: PublicSummary | null = null;
  protected summaryLoading = true;
  protected summaryError = false;
  protected products: PublicProduct[] = [];
  protected productsLoading = true;
  protected productsError = false;
  protected menuOpen = false;
  protected activeSection = 'inicio';

  ngOnInit(): void {
    this.route.fragment.subscribe(fragment => {
      this.activeSection = fragment && ['inicio', 'productos', 'contacto'].includes(fragment) ? fragment : 'inicio';
      this.changeDetector.markForCheck();
    });
    this.http.get<PublicSummary>('/api/public-summary.php').subscribe({
      next: (summary) => { this.summary = summary; this.summaryLoading = false; this.changeDetector.markForCheck(); },
      error: () => { this.summaryError = true; this.summaryLoading = false; this.changeDetector.markForCheck(); },
    });
    this.http.get<{ products: PublicProduct[] }>('/api/products.php').subscribe({
      next: (response) => { this.products = response.products; this.productsLoading = false; this.changeDetector.markForCheck(); },
      error: (error) => { console.error('LiquorSoft: no se pudieron cargar los productos.', error); this.productsError = true; this.productsLoading = false; this.changeDetector.markForCheck(); },
    });
  }

  protected selectSection(section: string): void {
    this.activeSection = section;
    this.menuOpen = false;
  }

  protected closeMenu(): void {
    this.menuOpen = false;
  }

  @HostListener('window:scroll')
  protected updateActiveSection(): void {
    const marker = 140;
    const current = ['inicio', 'productos', 'contacto']
      .map(id => document.getElementById(id))
      .find(section => section && section.getBoundingClientRect().top <= marker && section.getBoundingClientRect().bottom > marker);
    const nextSection = current?.id ?? 'inicio';
    if (nextSection !== this.activeSection) {
      this.activeSection = nextSection;
      this.changeDetector.markForCheck();
    }
  }

  protected formatPrice(value: number): string {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value);
  }
}
