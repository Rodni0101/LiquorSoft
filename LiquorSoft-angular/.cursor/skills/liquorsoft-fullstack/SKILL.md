---
name: liquorsoft-fullstack
description: >-
  Desarrolla LiquorSoft (Angular 22 + PHP/MySQL). Usar al cambiar catálogo,
  auth, inventario, ventas, checkout, proxy /api o el backend en
  Documentos/Liquorsoft/backend.
---

# LiquorSoft fullstack

## Arquitectura

- Frontend: `LiquorSoft-angular` (Angular 22, standalone, signals).
- Backend: `../backend` (PHP, MySQL `liquorsoft`).
- El proxy `proxy.conf.json` reenvía `/api` a `http://localhost:8000`.
- Toda petición HTTP debe ir con cookies de sesión (`credentialsInterceptor`).

## Arranque local

```bash
mysql -u root -p < ../backend/database/schema.sql
php -S 127.0.0.1:8000 -t ../backend
ng serve
```

Credenciales de admin de desarrollo: ver `../NOTAS`. No las copies al código.

## Contratos

- Público: `GET /api/products.php`, `GET /api/categories.php`, `GET /api/public-summary.php`.
- Auth: `POST /api/auth/Login.php`, `POST /api/auth/Registro/Register.php` (rol Cliente + sesión), `GET /api/auth/me.php`, `POST /api/auth/logout.php`.
- Compra: `POST /api/purchase.php` con `{ paymentMethod, notes, items: [{id, quantity}] }`. Descuenta stock; no cobra pasarela.
- Admin: ` /api/admin/products.php`, `/api/admin/inventory.php`, `/api/admin/sales.php`, `/api/admin/users.php`.
- Cliente: `GET /api/orders.php`.

Roles: Administrador, Supervisor, Vendedor, Bodega, Cliente.

## Reglas de producto

- No fingir pagos Nequi/PayPal como exitosos.
- El registro público no puede crear administradores.
- El catálogo público no lista productos inactivos.
- Carrito y checkout deben existir en `app.routes.ts`.
- Inventario y ventas no pueden ser placeholders: consumen API.
