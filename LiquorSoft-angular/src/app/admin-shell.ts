import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { AuthService } from './auth.service';
import { ThemeToggle } from './theme-toggle';

@Component({
  selector: 'app-admin-shell',
  standalone: true,
  imports: [RouterLink, RouterLinkActive, ThemeToggle],
  template: `
    <div class="admin-shell">
      <aside class="sidebar">
        <a routerLink="/inicio" class="brand">Liquor<span>Soft</span></a>
        <div class="admin-identity">
          <span class="status-dot" aria-hidden="true"></span>
          <div>
            <strong>{{ auth.user()?.name }}</strong>
            <small>{{ auth.user()?.role || 'Sesión' }}</small>
          </div>
        </div>

        @if (auth.can('dashboard')) {
          <p class="side-label">Principal</p>
          <nav>
            <a routerLink="/admin/dashboard" routerLinkActive="active"><span>▦</span><span>Dashboard</span></a>
          </nav>
        }

        @if (auth.can('productos') || auth.can('categorias') || auth.can('inventario')) {
          <p class="side-label">Inventario</p>
          <nav>
            @if (auth.can('productos')) {
              <a routerLink="/admin/productos" routerLinkActive="active"><span>▣</span><span>Productos</span></a>
            }
            @if (auth.can('categorias')) {
              <a routerLink="/admin/categorias" routerLinkActive="active"><span>◈</span><span>Categorías</span></a>
            }
            @if (auth.can('inventario')) {
              <a routerLink="/admin/inventario" routerLinkActive="active"><span>◫</span><span>Stock</span></a>
            }
          </nav>
        }

        @if (auth.can('ventas') || auth.can('compras')) {
          <p class="side-label">Comercial</p>
          <nav>
            @if (auth.can('ventas')) {
              <a routerLink="/admin/ventas" routerLinkActive="active"><span>◌</span><span>Historial de ventas</span></a>
            }
            @if (auth.can('compras')) {
              <a routerLink="/admin/compras" routerLinkActive="active"><span>▤</span><span>Compras</span></a>
            }
          </nav>
        }

        @if (auth.can('proveedores') || auth.can('usuarios')) {
          <p class="side-label">Gestión</p>
          <nav>
            @if (auth.can('proveedores')) {
              <a routerLink="/admin/proveedores" routerLinkActive="active"><span>▥</span><span>Proveedores</span></a>
            }
            @if (auth.can('usuarios')) {
              <a routerLink="/admin/usuarios" routerLinkActive="active"><span>♙</span><span>Usuarios</span></a>
            }
          </nav>
        }

        <div class="sidebar-foot">
          <app-theme-toggle class="admin-theme-toggle"></app-theme-toggle>
          <a routerLink="/productos" class="back-link">← Ver tienda</a>
          <button type="button" class="logout" (click)="auth.logout()">Cerrar sesión</button>
        </div>
      </aside>
      <main class="admin-content"><ng-content></ng-content></main>
    </div>
  `,
  styles: [`
    :host { display: block; min-height: 100vh; }
    .admin-shell { min-height: 100vh; display: flex; color: var(--color-text); background: var(--color-background); }
    .sidebar {
      width: 260px; flex: none; display: flex; flex-direction: column;
      padding: 24px 16px; border-right: 1px solid var(--color-border); background: var(--color-surface);
    }
    .brand { margin: 0 10px 20px; color: var(--color-text); font-size: 1.4rem; font-weight: 800; }
    .brand span { color: var(--color-primary); }
    .admin-identity {
      display: flex; gap: 10px; align-items: center; margin: 0 6px 22px; padding: 10px 12px;
      border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-surface-2);
    }
    .admin-identity strong, .admin-identity small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .admin-identity strong { font-size: .78rem; }
    .admin-identity small { margin-top: 2px; color: var(--color-muted); font-size: .65rem; }
    .status-dot { width: 8px; height: 8px; flex: none; border-radius: 50%; background: var(--color-success); }
    .side-label {
      margin: 14px 10px 8px; color: var(--color-muted); font-size: .62rem; font-weight: 800; letter-spacing: 1.6px; text-transform: uppercase;
    }
    nav { display: grid; gap: 4px; }
    nav a {
      display: flex; gap: 10px; align-items: center; padding: 9px 12px; border-radius: 8px;
      color: var(--color-muted); font-size: .84rem;
    }
    nav a:hover, nav a.active { color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 12%, transparent); }
    .sidebar-foot { margin-top: auto; display: grid; gap: 8px; padding-top: 16px; }
    .back-link, .logout { color: var(--color-muted); font-size: .73rem; text-align: left; }
    .logout { padding: 6px 0; border: 0; color: var(--color-danger); background: transparent; cursor: pointer; }
    .admin-content { flex: 1; min-width: 0; }
    @media (max-width: 800px) {
      .admin-shell { display: block; }
      .sidebar { width: 100%; padding: 14px; }
      .brand { display: inline-block; margin: 0 10px 12px 0; }
      .admin-identity { margin-bottom: 12px; }
      nav { display: flex; overflow: auto; }
      nav a { white-space: nowrap; }
      .sidebar-foot { grid-template-columns: auto 1fr auto; align-items: center; padding-top: 10px; }
    }
  `],
})
export class AdminShell {
  protected readonly auth = inject(AuthService);
}
