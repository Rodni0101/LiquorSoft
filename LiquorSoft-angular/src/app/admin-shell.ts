import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { AuthService } from './auth.service';

interface ManagedUser { id: number; name: string; email: string; role: string; roleId: number; active: boolean; }
interface Role { id: number; name: string; }

@Component({
  selector: 'app-admin-shell',
  standalone: true,
  imports: [RouterLink, RouterLinkActive],
  template: `<div class="admin-shell"><aside class="sidebar"><a routerLink="/inicio" class="brand">Liquor<span>Soft</span></a><p class="side-label">GESTIÓN</p><nav>@if (auth.can('dashboard')) {<a routerLink="/dashboard" routerLinkActive="active"><span>▦</span><span>Resumen</span></a>}@if (auth.can('productos')) {<a routerLink="/productos" routerLinkActive="active"><span>▣</span><span>Productos</span></a>}@if (auth.can('inventario')) {<a routerLink="/inventario" routerLinkActive="active"><span>◫</span><span>Inventario</span></a>}@if (auth.can('ventas')) {<a routerLink="/ventas" routerLinkActive="active"><span>◌</span><span>Ventas</span></a>}</nav>@if (auth.isAdmin()) {<section class="users-section"><h2>USUARIOS</h2>@if (usersLoading) {<small>Cargando usuarios…</small>} @if (usersError) {<small class="users-error">{{ usersError }}</small>} @for (user of users; track user.id) {<div class="user-row"><span class="user-name">{{ user.name }}</span><small>{{ user.email }}</small><select [value]="user.roleId" (change)="changeRole(user, $any($event.target).value)" [attr.aria-label]="'Rol de ' + user.name">@for (role of roles; track role.id) {<option [value]="role.id">{{ role.name }}</option>}</select></div>}</section>}<a routerLink="/catalogo" class="back-link">← Ver catálogo público</a><button type="button" class="logout" (click)="auth.logout()">Cerrar sesión</button></aside><main class="admin-content"><ng-content></ng-content></main></div>`,
  styles: [`:host{display:block;min-height:100vh}.admin-shell{min-height:100vh;display:flex;color:#f8fafc;background:#0b1120}.sidebar{width:270px;flex:none;display:flex;flex-direction:column;padding:30px 18px;border-right:1px solid rgba(255,255,255,.07);background:#0f172a}.brand{margin:0 14px 58px;color:#fff;font-size:1.5rem;font-weight:800;text-decoration:none}.brand span{color:#f59e0b}.side-label{margin:0 14px 14px;color:#64748b;font-size:.64rem;font-weight:800;letter-spacing:1.8px}nav{display:grid;gap:7px}nav a{display:flex;gap:13px;align-items:center;padding:12px 14px;border-radius:9px;color:#94a3b8;font-size:.88rem;text-decoration:none}nav a:hover,nav a.active{color:#fbbf24;background:rgba(245,158,11,.1)}.users-section{margin:28px 8px 0;padding-top:18px;border-top:1px solid rgba(255,255,255,.07)}.users-section h2{margin:0 6px 12px;color:#64748b;font-size:.64rem;letter-spacing:1.8px}.users-section>small{display:block;margin:0 6px 10px;color:#94a3b8;font-size:.68rem}.users-error{color:#fca5a5!important}.user-row{display:grid;gap:3px;padding:9px 6px;border-bottom:1px solid rgba(255,255,255,.05)}.user-name{overflow:hidden;color:#e2e8f0;font-size:.74rem;text-overflow:ellipsis;white-space:nowrap}.user-row small{overflow:hidden;color:#64748b;font-size:.62rem;text-overflow:ellipsis;white-space:nowrap}.user-row select{width:100%;margin-top:4px;padding:4px 6px;border:1px solid #334155;border-radius:5px;color:#cbd5e1;background:#1e293b;font:inherit;font-size:.65rem}.back-link{margin:auto 14px 10px;color:#64748b;font-size:.73rem;text-decoration:none}.logout{margin:0 14px;padding:9px 0;border:0;color:#fca5a5;background:transparent;text-align:left;font:inherit;font-size:.73rem;cursor:pointer}.admin-content{flex:1;min-width:0}@media(max-width:600px){.admin-shell{display:block}.sidebar{width:100%;padding:18px}.brand{display:inline-block;margin:0 0 18px}.side-label,.users-section,.back-link,.logout{display:none}nav{display:flex;overflow:auto}nav a{white-space:nowrap}}`],
})
export class AdminShell implements OnInit {
  protected readonly auth = inject(AuthService);
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);
  protected users: ManagedUser[] = [];
  protected roles: Role[] = [];
  protected usersLoading = false;
  protected usersError = '';

  ngOnInit(): void { if (this.auth.isAdmin()) this.loadUsers(); }
  private loadUsers(): void {
    this.usersLoading = true;
    this.http.get<{ users: ManagedUser[]; roles: Role[] }>('/api/admin/users.php', { withCredentials: true }).subscribe({
      next: response => { this.users = response.users; this.roles = response.roles; this.usersLoading = false; this.changeDetector.markForCheck(); },
      error: () => { this.usersError = 'No fue posible cargar la lista.'; this.usersLoading = false; this.changeDetector.markForCheck(); },
    });
  }
  protected changeRole(user: ManagedUser, roleIdValue: string): void {
    const roleId = Number(roleIdValue);
    if (!roleId || roleId === user.roleId) return;
    const previousRoleId = user.roleId;
    this.http.patch<{ role: Role }>('/api/admin/users.php', { userId: user.id, roleId }, { withCredentials: true }).subscribe({
      next: response => { user.roleId = response.role.id; user.role = response.role.name; this.changeDetector.markForCheck(); },
      error: () => { user.roleId = previousRoleId; this.usersError = 'No se pudo actualizar el rol.'; this.changeDetector.markForCheck(); },
    });
  }
}
