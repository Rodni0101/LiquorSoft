import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { AuthService } from './auth.service';
import { ThemeToggle } from './theme-toggle';
import { NotificationBell } from './notification-bell';

@Component({
selector: 'app-admin-shell',
standalone: true,
imports: [RouterLink, RouterLinkActive, ThemeToggle, NotificationBell],
template: ` <div class="admin-shell"> <aside class="sidebar"> <a routerLink="/inicio" class="brand logo" aria-label="LiquorSoft, inicio">
<span class="logo-mark" aria-hidden="true">LS</span><span class="logo-word">Liquor<span>Soft</span></span> </a>


    <div class="admin-identity">
      <span class="status-dot" aria-hidden="true"></span>
      <div class="identity-info">
        <strong>{{ auth.user()?.name }}</strong>
        <small>{{ auth.user()?.role || 'Usuario' }}</small>
      </div>
      <app-notification-bell class="admin-bell"></app-notification-bell>
    </div>

    <p class="side-label">GESTIÓN</p>

    <nav aria-label="Menú de administración">
      @if (auth.can('dashboard')) {
        <a routerLink="/admin/dashboard" routerLinkActive="active" [routerLinkActiveOptions]="{ exact: true }">
          <span class="nav-icon"><i class="bi bi-speedometer2"></i></span>
          <span>Resumen</span>
        </a>
      }

      @if (auth.can('productos')) {
        <a routerLink="/admin/productos" routerLinkActive="active">
          <span class="nav-icon"><i class="bi bi-box"></i></span>
          <span>Productos</span>
        </a>
      }

      @if (auth.can('categorias')) {
        <a routerLink="/admin/categorias" routerLinkActive="active">
          <span class="nav-icon"><i class="bi bi-tag"></i></span>
          <span>Categorías</span>
        </a>
      }

      @if (auth.can('inventario')) {
        <a routerLink="/admin/inventario" routerLinkActive="active">
          <span class="nav-icon"><i class="bi bi-box-seam"></i></span>
          <span>Inventario</span>
        </a>
      }

      @if (auth.can('ventas')) {
        <a routerLink="/admin/ventas" routerLinkActive="active">
          <span class="nav-icon"><i class="bi bi-cash-coin"></i></span>
          <span>Ventas</span>
        </a>
      }

      @if (auth.can('compras')) {
        <a routerLink="/admin/compras" routerLinkActive="active">
          <span class="nav-icon"><i class="bi bi-cart-fill"></i></span>
          <span>Compras</span>
        </a>
      }

      @if (auth.can('proveedores')) {
        <a routerLink="/admin/proveedores" routerLinkActive="active">
          <span class="nav-icon"><i class="bi bi-building"></i></span>
          <span>Proveedores</span>
        </a>
      }

      @if (auth.can('usuarios')) {
        <a routerLink="/admin/usuarios" routerLinkActive="active">
          <span class="nav-icon"><i class="bi bi-people"></i></span>
          <span>Usuarios</span>
        </a>
      }
    </nav>

    <div class="sidebar-footer">
      <app-theme-toggle class="admin-theme-toggle"></app-theme-toggle>

      <a routerLink="/productos" class="back-link">
        <i class="bi bi-arrow-left"></i>
        <span>Ver tienda</span>
      </a>

      <button type="button" class="logout" (click)="auth.logout()">
        <i class="bi bi-box-arrow-right"></i>
        <span>Cerrar sesión</span>
      </button>
    </div>
  </aside>

  <main class="admin-content">
    <ng-content></ng-content>
  </main>
</div>


`,
  styles: [`
:host {
display: block;
min-height: 100vh;
color: #F8FAFC;
}


* {
  box-sizing: border-box;
}

.admin-shell {
  --admin-wine: #6D0F1A;
  --admin-wine-hover: #8D1B30;
  --admin-rose: #B83D5B;
  --sidebar-width: 270px;

  min-height: 100vh;
  color: #F8FAFC;
  background: #0B0B0B;
}

.sidebar {
  position: fixed;
  inset: 0 auto 0 0;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  width: var(--sidebar-width);
  height: 100vh;
  height: 100dvh;
  min-height: 0;
  padding: 28px 18px 20px;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-width: thin;
  scrollbar-color: #80545F #171014;
  background: #171014;
  border-right: 1px solid rgba(184, 61, 91, 0.24);
}

.brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  width: fit-content;
  margin: 0 14px 28px;
  color: #FFFFFF;
  text-decoration: none;
}

.admin-identity {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
  margin: 0 4px 30px;
  padding: 14px 12px;
  background: rgba(184, 61, 91, 0.09);
  border: 1px solid rgba(184, 61, 91, 0.25);
  border-radius: 12px;
}

.identity-info {
  min-width: 0;
  flex: 1;
}

.admin-identity strong,
.admin-identity small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.admin-identity strong {
  color: #FFFFFF;
  font-size: 0.82rem;
  font-weight: 700;
}

.admin-identity small {
  margin-top: 5px;
  color: #D6BFC7;
  font-size: 0.7rem;
}

.status-dot {
  width: 9px;
  height: 9px;
  flex: 0 0 9px;
  border-radius: 50%;
  background: #D99AA9;
  box-shadow: 0 0 0 4px rgba(184, 61, 91, 0.15);
}

.side-label {
  margin: 0 14px 13px;
  color: #C7AEB7;
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 1.8px;
}

nav {
  display: grid;
  flex: 0 0 auto;
  gap: 7px;
}

nav a {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 46px;
  padding: 11px 13px;
  color: #D0C2C8;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 10px;
  font-size: 0.86rem;
  font-weight: 550;
  text-decoration: none;
  transition: background 160ms ease, color 160ms ease, border-color 160ms ease, transform 160ms ease;
}

.nav-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 23px;
  height: 23px;
  flex: 0 0 23px;
  color: #D99AA9;
  font-size: 1.05rem;
  border-radius: 7px;
  transition: color 160ms ease, background 160ms ease;
}

nav a:hover {
  transform: translateX(2px);
  color: #FFFFFF;
  background: rgba(141, 27, 48, 0.25);
  border-color: rgba(184, 61, 91, 0.38);
}

nav a:hover .nav-icon {
  color: #FFFFFF;
  background: rgba(255, 255, 255, 0.1);
}

nav a.active {
  color: #FFFFFF;
  background: linear-gradient(110deg, #6D0F1A, #45131D);
  border-color: rgba(184, 61, 91, 0.65);
  font-weight: 700;
  box-shadow: inset 3px 0 #E58DA3, 0 4px 14px rgba(109, 15, 26, 0.2);
}

nav a.active .nav-icon {
  color: #FFFFFF;
  background: rgba(255, 255, 255, 0.12);
}

nav a:focus-visible,
.brand:focus-visible,
.back-link:focus-visible,
.logout:focus-visible {
  outline: 2px solid #E58DA3;
  outline-offset: 3px;
}

.sidebar-footer {
  display: flex;
  flex-direction: column;
  gap: 7px;
  margin-top: auto;
  padding-top: 24px;
}

.admin-theme-toggle {
  display: block;
  margin: 0 10px 14px;
}

.back-link,
.logout {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 40px;
  margin: 0;
  padding: 10px 13px;
  border-radius: 9px;
  font-family: inherit;
  font-size: 0.78rem;
  text-decoration: none;
  text-align: left;
  transition: background 160ms ease, color 160ms ease;
}

.back-link {
  color: #D0C2C8;
  background: transparent;
}

.back-link:hover {
  color: #FFFFFF;
  background: rgba(184, 61, 91, 0.12);
}

.logout {
  width: 100%;
  color: #FFD7DE;
  background: linear-gradient(110deg, rgba(109, 15, 26, 0.42), rgba(69, 19, 29, 0.72));
  border: 1px solid rgba(255, 184, 195, 0.25);
  font-weight: 700;
  cursor: pointer;
}

.logout:hover {
  color: #FFFFFF;
  background: linear-gradient(110deg, #8D1B30, #64101F);
  border-color: rgba(255, 184, 195, 0.55);
  box-shadow: 0 4px 14px rgba(109, 15, 26, 0.25);
}

.admin-content {
  min-width: 0;
  min-height: 100vh;
  margin-left: var(--sidebar-width);
  background: #0B0B0B;
}

:host-context([data-theme='light']) .admin-shell {
  color: #29151C;
  background: #F7F3F5;
}

:host-context([data-theme='light']) .sidebar {
  background: #FFFFFF;
  border-right-color: #DFCBD3;
  box-shadow: 4px 0 18px rgba(66, 24, 40, 0.04);
  scrollbar-color: #C8A6AF #FFFFFF;
}

:host-context([data-theme='light']) .brand {
  color: #29151C;
}

:host-context([data-theme='light']) .admin-identity {
  background: #F9EDF1;
  border-color: #E7CCD5;
}

:host-context([data-theme='light']) .admin-identity strong {
  color: #29151C;
}

:host-context([data-theme='light']) .admin-identity small {
  color: #62515A;
}

:host-context([data-theme='light']) .side-label {
  color: #70505D;
}

:host-context([data-theme='light']) nav a {
  color: #51404A;
}

:host-context([data-theme='light']) .nav-icon {
  color: #6D0F1A;
}

:host-context([data-theme='light']) nav a:hover {
  color: #6D0F1A;
  background: #F9EDF1;
  border-color: #E7CCD5;
}

:host-context([data-theme='light']) nav a:hover .nav-icon {
  color: #6D0F1A;
}

:host-context([data-theme='light']) nav a.active {
  color: #FFFFFF;
  background: linear-gradient(110deg, #6D0F1A, #45131D);
  border-color: #6D0F1A;
}

:host-context([data-theme='light']) nav a.active .nav-icon {
  color: #FFFFFF;
}

:host-context([data-theme='light']) .back-link {
  color: #51404A;
}

:host-context([data-theme='light']) .back-link:hover {
  color: #6D0F1A;
  background: #F9EDF1;
}

:host-context([data-theme='light']) .logout {
  color: #8F243A;
  background: #F9EDF1;
  border-color: #E7CCD5;
}

:host-context([data-theme='light']) .logout:hover {
  color: #FFFFFF;
  background: #6D0F1A;
  border-color: #6D0F1A;
}

:host-context([data-theme='light']) .admin-content {
  background: #F7F3F5;
}

@media (max-width: 900px) {
  .admin-shell {
    --sidebar-width: 230px;
  }

  .sidebar {
    padding-right: 13px;
    padding-left: 13px;
  }
}

@media (max-width: 600px) {
  .admin-shell {
    --sidebar-width: 0px;
  }

  .sidebar {
    position: relative;
    width: 100%;
    height: auto;
    min-height: 0;
    max-height: none;
    padding: 18px 14px 12px;
    overflow: visible;
    border-right: 0;
    border-bottom: 1px solid rgba(184, 61, 91, 0.24);
  }

  .brand {
    margin: 0 0 16px;
    font-size: 1.4rem;
  }

  .admin-identity {
    margin: 0 0 18px;
  }

  .side-label {
    margin-bottom: 10px;
  }

  nav {
    display: flex;
    gap: 7px;
    overflow-x: auto;
    padding: 2px 1px 8px;
    scrollbar-width: thin;
  }

  nav a {
    flex: 0 0 auto;
    min-height: 40px;
    padding: 10px 12px;
    white-space: nowrap;
  }

  .sidebar-footer {
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(184, 61, 91, 0.18);
  }

  .admin-theme-toggle {
    grid-column: 1 / -1;
    margin: 4px 8px 8px;
  }

  .back-link,
  .logout {
    justify-content: center;
    padding: 9px 7px;
    font-size: 0.72rem;
  }

  .admin-content {
    min-height: auto;
    margin-left: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  nav a,
  .back-link,
  .logout {
    transition: none;
  }
}


`],
})
export class AdminShell {
protected readonly auth = inject(AuthService);
}
