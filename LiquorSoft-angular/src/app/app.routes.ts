import { Routes } from '@angular/router';

export const routes: Routes = [

  {
    path: '',
    redirectTo: 'inicio',
    pathMatch: 'full'
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
    loadComponent: () =>
      import('./pages/dashboard/dashboard')
        .then(m => m.Dashboard)
  },

  {
    path: 'productos',
    loadComponent: () =>
      import('./pages/productos/productos')
        .then(m => m.Productos)
  },

  {
    path: 'inventario',
    loadComponent: () =>
      import('./pages/inventario/inventario')
        .then(m => m.Inventario)
  },

  {
    path: 'ventas',
    loadComponent: () =>
      import('./pages/ventas/ventas')
        .then(m => m.Ventas)
  }

];