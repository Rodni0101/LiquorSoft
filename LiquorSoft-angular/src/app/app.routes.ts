import { Routes } from '@angular/router';
import { authGuard } from './auth.guard';
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
    path: 'productos',
    loadComponent: () => import('./pages/catalogo/catalogo').then(m => m.Catalogo),
  },

  {
    path: 'productos/:id',
    loadComponent: () => import('./pages/producto/producto').then(m => m.Producto),
  },

  {
    path: 'carrito',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/carrito/carrito').then(m => m.Carrito),
  },

  {
    path: 'checkout',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/checkout/checkout').then(m => m.Checkout),
  },

  {
    path: 'pedidos',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/pedidos/pedidos').then(m => m.Pedidos),
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
    path: 'admin/dashboard',
    canActivate: [permissionGuard], data: { permission: 'dashboard' },
    loadComponent: () =>
      import('./pages/dashboard/dashboard')
        .then(m => m.Dashboard)
  },

  {
    path: 'admin/productos',
    canActivate: [permissionGuard], data: { permission: 'productos' },
    loadComponent: () =>
      import('./pages/productos/productos')
        .then(m => m.Productos)
  },

  {
    path: 'admin/inventario',
    canActivate: [permissionGuard], data: { permission: 'inventario' },
    loadComponent: () =>
      import('./pages/inventario/inventario')
        .then(m => m.Inventario)
  },

  {
    path: 'admin/ventas',
    canActivate: [permissionGuard], data: { permission: 'ventas' },
    loadComponent: () =>
      import('./pages/ventas/ventas')
        .then(m => m.Ventas)
  },

  {
    path: 'admin/proveedores',
    canActivate: [permissionGuard], data: { permission: 'proveedores' },
    loadComponent: () => import('./pages/proveedores/proveedores').then(m => m.Proveedores),
  },

  {
    path: 'admin/compras',
    canActivate: [permissionGuard], data: { permission: 'compras' },
    loadComponent: () => import('./pages/compras/compras').then(m => m.Compras),
  },

  {
    path: 'admin/categorias',
    canActivate: [permissionGuard], data: { permission: 'categorias' },
    loadComponent: () => import('./pages/categorias/categorias').then(m => m.Categorias),
  },

  {
    path: 'admin/usuarios',
    canActivate: [permissionGuard], data: { permission: 'usuarios' },
    loadComponent: () => import('./pages/usuarios/usuarios').then(m => m.Usuarios),
  }

];
