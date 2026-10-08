# Registro de implementación

## Iteración 1 — Bloqueo de pedidos sin pago confirmado

### Objetivo

Evitar que el checkout registre pedidos o descuente inventario mientras las
pasarelas de pago no estén integradas y no exista una confirmación positiva.

### Problema

El checkout permitía pasar de un estado de pago `pending` a registrar el pedido
y descontar stock, aunque `PaymentService` declaraba que no se había realizado
ningún cobro.

### Solución

- Se eliminó de la interfaz la acción de registrar pedido desde el estado
  `pending`.
- Se actualizó el mensaje del checkout para dejar claro que el pedido solo se
  registrará después de confirmar el pago.
- Se añadió una guarda defensiva en `registerPurchase()` para rechazar cualquier
  intento sin estado `success`.
- Se registró el interceptor de credenciales en la configuración HTTP global.

### Archivos modificados

- `src/app/pages/checkout/checkout.html`
- `src/app/pages/checkout/checkout.ts`
- `src/app/app.config.ts`

### Base de datos

Sin cambios. El backend y las migraciones no están presentes en este checkout.

### Pruebas

- `tsc -p tsconfig.app.json --noEmit`: correcto.
- `git diff --check`: correcto.
- Prettier sobre los archivos modificados: correcto.
- `npm run build` y `npm test`: bloqueados por Node.js 18.19.1; Angular CLI 22
  requiere Node.js 22.22.3 o superior.

### Riesgo restante

La integración real de pagos y la transacción backend que debe crear el pedido
y descontar inventario siguen pendientes hasta recuperar el backend PHP y la
base de datos.

## Iteración 2 — Protección de sesión y operaciones mutables

### Objetivo

Reducir el riesgo de solicitudes mutables forjadas desde otro sitio y asegurar
que el backend no trate pagos externos no configurados como ventas completadas.

### Cambios realizados

- Se añadió token CSRF de sesión y validación mediante `X-CSRF-Token`.
- Se endurecieron las cookies de sesión con `HttpOnly`, `SameSite=Lax`, modo
  estricto y soporte para `LIQURSOFT_COOKIE_SECURE=1`.
- El interceptor Angular lee el token CSRF y lo adjunta automáticamente.
- Se protegieron logout, categorías, productos, proveedores, compras,
  inventario, usuarios y pedidos.
- `purchase.php` rechaza Nequi y PayPal mientras no exista integración real;
  no crea venta ni descuenta stock.

### Archivos afectados

- `src/app/credentials.interceptor.ts`
- `src/app/app.config.ts`
- `README.md`
- `backend/.env.example`
- `backend/api/config/database.php`
- `backend/api/auth/Login.php`
- `backend/api/auth/logout.php`
- `backend/api/categories.php`
- `backend/api/admin/*.php`
- `backend/api/purchase.php`

### Pruebas

- Sintaxis de todos los PHP: correcta.
- TypeScript sin emisión: correcto.
- Prettier: correcto.
- El servidor PHP no pudo abrir puertos locales en este entorno.
- Las llamadas directas al backend alcanzaron el código, pero MySQL no estaba
  disponible; respondieron con servicio no disponible.

### Pendientes

- Integrar una pasarela de pago real antes de habilitar ventas externas.
- Ejecutar pruebas HTTP contra PHP y MySQL activos.
- Ejecutar `npm run build` y `npm test` con Node.js 22.22.3 o superior.
