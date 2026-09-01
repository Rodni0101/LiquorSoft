import { Routes } from '@angular/router';
import { permissionGuard } from './permission.guard';

export const routes: Routes = [

  {
    path: '',
    redirectTo: 'inicio',
    pathMatch: 'full'
  },

  {
    path: 'catalogo',
    loadComponent: () =>
      import('./pages/catalogo/catalogo')
        .then(m => m.Catalogo)
  },

  {
    path: 'inicio',
    loadComponent: () =>
      import('./pages/inicio/inicio')
        .then(m => m.Inicio)
  },

  {
    path: 'login',
    loadComponent: () =>
      import('./pages/login/login')
        .then(m => m.Login)
  },

  {
    path: 'register',
    loadComponent: () =>
      import('./pages/register/register')
        .then(m => m.Register)
  },

  {
    path: 'dashboard',
    canActivate: [permissionGuard], data: { permission: 'dashboard' },
    loadComponent: () =>
      import('./pages/dashboard/dashboard')
        .then(m => m.Dashboard)
  },

  {
    path: 'productos',
    canActivate: [permissionGuard], data: { permission: 'productos' },
    loadComponent: () =>
      import('./pages/productos/productos')
        .then(m => m.Productos)
  },

  {
    path: 'inventario',
    canActivate: [permissionGuard], data: { permission: 'inventario' },
    loadComponent: () =>
      import('./pages/inventario/inventario')
        .then(m => m.Inventario)
  },

  {
    path: 'ventas',
    canActivate: [permissionGuard], data: { permission: 'ventas' },
    loadComponent: () =>
      import('./pages/ventas/ventas')
        .then(m => m.Ventas)
  }

];
