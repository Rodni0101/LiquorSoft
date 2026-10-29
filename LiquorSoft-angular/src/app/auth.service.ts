import { inject, Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { catchError, finalize, map, shareReplay, tap } from 'rxjs/operators';
import { Router } from '@angular/router';

export interface CurrentUser { id: number; name: string; email: string; role: string; roleId: number; permissions: string[]; }
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient); private readonly router = inject(Router);
  readonly user = signal<CurrentUser | null>(null); readonly loading = signal(true);
  private refreshRequest: Observable<CurrentUser | null> | null = null;
  constructor() { this.refresh().subscribe(); }
  can(permission: string): boolean {
    return this.user()?.permissions?.includes(permission) ?? false;
  }
  isAdmin(): boolean { return this.user()?.role.trim().toLowerCase() === 'administrador'; }
  isClient(): boolean { return this.user()?.role.trim().toLowerCase() === 'cliente'; }

  /** Primera vista permitida para el usuario: evita enviar al staff a la tienda o a una ruta sin permiso. */
  homeUrl(): string {
    const permissions = this.user()?.permissions ?? [];
    if (permissions.includes('dashboard')) return '/admin/dashboard';
    const candidates: Array<[string, string]> = [
      ['ventas', '/admin/ventas'],
      ['inventario', '/admin/inventario'],
      ['productos', '/admin/productos'],
      ['compras', '/admin/compras'],
      ['proveedores', '/admin/proveedores'],
      ['categorias', '/admin/categorias'],
      ['usuarios', '/admin/usuarios'],
    ];
    for (const [permission, url] of candidates) {
      if (permissions.includes(permission)) return url;
    }
    return '/inicio';
  }
  refresh(): Observable<CurrentUser | null> {
    if (this.refreshRequest) return this.refreshRequest;
    this.refreshRequest = this.http.get<{ user: CurrentUser }>('/api/auth/me.php', { withCredentials: true }).pipe(
      map(r => r.user),
      tap(user => { this.user.set(user); this.loading.set(false); }),
      catchError((error) => {
        if (error.status !== 401) console.error('LiquorSoft: no se pudo validar la sesión.', error);
        this.user.set(null);
        this.loading.set(false);
        return of(null);
      }),
      finalize(() => { this.refreshRequest = null; }),
      shareReplay({ bufferSize: 1, refCount: true }),
    );
    return this.refreshRequest;
  }
  logout(): void { this.http.post('/api/auth/logout.php', {}, { withCredentials: true }).subscribe({ next: () => { this.user.set(null); this.router.navigate(['/inicio']); }, error: () => { this.user.set(null); this.router.navigate(['/inicio']); } }); }
}
