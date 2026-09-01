import { inject, Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { catchError, finalize, map, shareReplay, tap } from 'rxjs/operators';
import { Router } from '@angular/router';

export interface CurrentUser { id: number; name: string; email: string; role: string; roleId: number; }
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient); private readonly router = inject(Router);
  readonly user = signal<CurrentUser | null>(null); readonly loading = signal(true);
  private refreshRequest: Observable<CurrentUser | null> | null = null;
  constructor() { this.refresh().subscribe(); }
  can(permission: string): boolean {
    const role = this.user()?.role.toLowerCase();
    const permissions: Record<string, string[]> = {
      administrador: ['dashboard', 'productos', 'inventario', 'ventas'],
      supervisor: ['dashboard', 'productos', 'inventario', 'ventas'],
      vendedor: ['dashboard', 'ventas'],
      bodega: ['dashboard', 'inventario'],
    };
    return !!role && (permissions[role] ?? []).includes(permission);
  }
  refresh(): Observable<CurrentUser | null> {
    if (this.refreshRequest) return this.refreshRequest;
    this.refreshRequest = this.http.get<{ user: CurrentUser }>('/api/auth/me.php', { withCredentials: true }).pipe(
      map(r => r.user),
      tap(user => { this.user.set(user); this.loading.set(false); }),
      catchError((error) => { console.error('LiquorSoft: no se pudo validar la sesión.', error); this.user.set(null); this.loading.set(false); return of(null); }),
      finalize(() => { this.refreshRequest = null; }),
      shareReplay({ bufferSize: 1, refCount: true }),
    );
    return this.refreshRequest;
  }
  logout(): void { this.http.post('/api/auth/logout.php', {}, { withCredentials: true }).subscribe({ next: () => { this.user.set(null); this.router.navigate(['/inicio']); }, error: () => { this.user.set(null); this.router.navigate(['/inicio']); } }); }
}
