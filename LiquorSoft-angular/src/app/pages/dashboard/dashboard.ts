import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router, RouterLink } from '@angular/router';
import { AdminShell } from '../../admin-shell';

interface DashboardStats { totalProducts: number; availableProducts: number; outOfStock: number; totalUnits: number; inventoryValue: number; lowStock: number; salesToday: number; salesThisMonth: number; revenueToday: number; revenueThisMonth: number; activeUsers: number; }
interface RecentProduct { name: string; category: string; stock: number; price: number; }
interface DashboardResponse { stats: DashboardStats; recentProducts: RecentProduct[]; }

@Component({ selector: 'app-dashboard', standalone: true, imports: [RouterLink, AdminShell], styleUrl: './dashboard.css', templateUrl: './dashboard.html' })
export class Dashboard implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  private readonly changeDetector = inject(ChangeDetectorRef);
  protected loading = true;
  protected errorMessage = '';
  protected data: DashboardResponse | null = null;

  ngOnInit(): void {
    this.http.get<DashboardResponse>('/api/dashboard.php', { withCredentials: true }).subscribe({
      next: (data) => { this.data = data; this.loading = false; this.changeDetector.markForCheck(); },
      error: (error) => {
        this.loading = false;
        if (error.status === 401) { this.router.navigate(['/login']); return; }
        this.errorMessage = 'No fue posible cargar las métricas. Verifica la conexión con la base de datos.';
        this.changeDetector.markForCheck();
      },
    });
  }

  protected formatPrice(value: number): string {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value);
  }
}
