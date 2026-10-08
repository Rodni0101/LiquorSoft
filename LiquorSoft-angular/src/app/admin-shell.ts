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
      <aside class="sidebar" aria-label="Navegación administrativa">
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
          <nav aria-label="Principal">
            <a routerLink="/admin/dashboard" routerLinkActive="active"><span>▦</span><span>Dashboard</span></a>
          </nav>
        }

        @if (auth.can('productos') || auth.can('categorias') || auth.can('inventario')) {
          <p class="side-label">Inventario</p>
          <nav aria-label="Inventario">
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
          <nav aria-label="Comercial">
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
          <nav aria-label="Gestión">
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
    :host { display:block; min-height:100vh; }
    .admin-shell { display:flex; min-height:100vh; color:var(--color-text); background:var(--color-background); }
    .sidebar { position:sticky; top:0; display:flex; flex:none; flex-direction:column; width:264px; height:100vh; padding:26px 15px 18px; overflow:auto; border-right:1px solid var(--color-border); background:color-mix(in srgb,var(--color-surface) 94%,transparent); }
    .brand { display:flex; align-items:center; gap:9px; margin:0 11px 26px; color:var(--color-text); font:700 1.15rem var(--font-display); letter-spacing:-.055em; }
    .brand::before { content:'LS'; display:grid; place-items:center; width:28px; height:28px; border-radius:50%; color:#151515; background:var(--color-primary); font:800 .55rem var(--font-ui); letter-spacing:-.08em; }
    .brand span { color:var(--color-primary); }
    .admin-identity { display:flex; gap:11px; align-items:center; margin:0 5px 25px; padding:12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); background:var(--color-surface-2); }
    .admin-identity strong,.admin-identity small { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }.admin-identity strong { font-size:.78rem; }.admin-identity small { margin-top:3px; color:var(--color-muted); font-size:.64rem; }.status-dot { width:8px; height:8px; flex:none; border-radius:50%; background:var(--color-success); box-shadow:0 0 0 4px color-mix(in srgb,var(--color-success) 15%,transparent); }
    .side-label { margin:17px 10px 8px; color:var(--color-muted); font-size:.59rem; font-weight:800; letter-spacing:.16em; text-transform:uppercase; }
    nav { display:grid; gap:3px; } nav a { display:flex; gap:11px; align-items:center; padding:10px 12px; border:1px solid transparent; border-radius:var(--radius-sm); color:var(--color-muted); font-size:.8rem; }.sidebar nav a span:first-child { width:18px; color:var(--color-muted); text-align:center; font-size:.95rem; }.sidebar nav a:hover,.sidebar nav a.active { color:var(--color-text); border-color:color-mix(in srgb,var(--color-primary) 25%,transparent); background:color-mix(in srgb,var(--color-primary) 10%,transparent); }.sidebar nav a.active span:first-child { color:var(--color-primary); }
    .sidebar-foot { display:grid; gap:7px; margin-top:auto; padding-top:20px; border-top:1px solid var(--color-border); }.back-link,.logout { color:var(--color-muted); font-size:.72rem; text-align:left; }.back-link:hover { color:var(--color-text); }.logout { padding:7px 0; border:0; color:var(--color-danger); background:transparent; cursor:pointer; text-align:left; }.admin-content { flex:1; min-width:0; }
    @media (max-width:800px) { .admin-shell { display:block; }.sidebar { position:relative; width:100%; height:auto; min-height:0; padding:15px; }.brand { display:inline-flex; margin:0 0 15px 5px; }.admin-identity { margin-bottom:13px; } .sidebar nav { display:flex; overflow:auto; }.sidebar nav a { white-space:nowrap; }.side-label { margin-top:13px; }.sidebar-foot { grid-template-columns:auto 1fr auto; align-items:center; padding-top:12px; }.sidebar-foot .theme-toggle { width:max-content; } }
  `],
})
export class AdminShell {
  protected readonly auth = inject(AuthService);
}
