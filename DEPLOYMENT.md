# Despliegue de LiquorSoft en hosting gratuito

Fecha de análisis: 2026-10-20.

LiquorSoft es una SPA de Angular 22 + API PHP 8 + MySQL/MariaDB. Node.js solo se
usa para compilar; en el hosting **no se necesita Node, SSH ni Composer**. Basta
con PHP, MySQL y soporte de `.htaccess`.

## 1. Proveedor recomendado

| Proveedor | Disco | PHP / BD | Panel | SSL | SSH | Veredicto |
|---|---|---|---|---|---|---|
| **InfinityFree** | 5 GB | PHP 8.4; MySQL 8.0 / MariaDB 11.4; 400 BD | VistaPanel + phpMyAdmin | Sí, gratis | No | **Recomendado** |
| GoogieHost | 1000 MB | PHP 8.x; 1 BD MySQL | DirectAdmin | Sí, gratis | No | Alternativa |
| Byet.host | 5 GB | PHP 8.3; MySQL 8 / MariaDB 11.4 | VistaPanel | Sí en subdominios | No | Alternativa |
| WebsitePlanet | — | — | — | — | — | **No es un hosting**: es un sitio de reseñas/afiliados |

Fuentes oficiales:
- https://www.infinityfree.com/ (5 GB, PHP 8.4, MySQL 8.0/MariaDB 11.4, .htaccess, SSL)
- https://forum.infinityfree.com/c/documentation/fair-usage-limits/19 (límites de uso)
- https://googiehost.com/free-hosting.html (1000 MB, DirectAdmin, PHP 8, MySQL, FTP, SSL)
- https://byet.host/ (5 GB, PHP 8.3, MySQL, FTP/File Manager, SSL)

**Recomendación:** InfinityFree. Tiene el mayor espacio, PHP 8.4 y soporte completo
de `.htaccess`. El paquete pesa ~2 MB (67 archivos); el archivo más grande es
`main-*.js` con 312 KB, por debajo del límite de 1 MB para JS de InfinityFree.

Límites relevantes de InfinityFree: 50.000 hits/día, límite de inodos, procesos
de entrada (entry processes), sin SSH. Para una demo y tráfico bajo es suficiente.
Las conexiones salientes a PayPal/OpenAI usan HTTPS (443), permitido; aun así, si
el firewall las bloquea, el chat usa su respaldo local y el simulador de pago no
depende de Internet.

## 2. Paquete de despliegue

Se genera con:

```bash
./deploy/build.sh
```

Resultado:

- `deploy/htdocs/` → contenido a subir a `htdocs` / `public_html`.
- `deploy/liquorsoft-htdocs.zip` → mismo contenido comprimido.
- `.htaccess` incluido (SPA, cachés, bloqueo de `.env`/`.sql`).
- `api/` con el backend; **no** incluye `backend/tests`, `backend/database` ni `.env`.

El script compila Angular en modo producción y valida la sintaxis de todos los
PHP. La compilación no depende de Internet (`optimization.fonts=false`).

## 3. Crear la cuenta y la base de datos

Estos pasos los debe hacer una persona (registro, correo, CAPTCHA y aceptación de
términos). El agente no puede crear cuentas ni aceptar condiciones legales.

1. Crear la cuenta en InfinityFree y un subdominio gratuito (p. ej. `liquorsoft.rf.gd`).
2. En el panel, crear una base de datos MySQL. Anotar:
   - Host (suele ser `sqlXXX.infinityfree.com`, **no** `localhost`)
   - Nombre de la BD (p. ej. `if0_12345678_liquorsoft`)
   - Usuario y contraseña
3. Abrir phpMyAdmin, seleccionar esa base de datos e importar
   `backend/database/schema.sql`.
   - El archivo no contiene `CREATE DATABASE` ni `USE`: se importa sobre la BD ya
     seleccionada.
   - No ejecutar las migraciones de `backend/database/migrations/` en una
     instalación nueva; ya están reflejadas en `schema.sql`.
   - Crea roles, 5 categorías, 27 marcas, 3 proveedores y 29 productos con
     imágenes locales.

## 4. Subir los archivos

1. Subir `deploy/liquorsoft-htdocs.zip` a `htdocs` (o `public_html`) con el
   Administrador de archivos o FTP.
2. Extraer el ZIP **en la raíz**, de forma que quede:
   ```
   htdocs/index.html
   htdocs/main-XXXX.js
   htdocs/assets/...
   htdocs/api/...
   htdocs/.htaccess
   ```
3. Verificar que `.htaccess` exista. Algunos administradores de archivos ocultan
   los archivos que empiezan por punto; si falta, crear el archivo manualmente
   con el contenido de `deploy/htaccess.txt`.
4. Crear `.env` en `htdocs` (junto a `api/`) copiando
   `deploy/env.production.example` y completar los datos reales.
   - `LIQURSOFT_DB_*` (sin la O) es el nombre correcto; así lo lee el código.
   - `LIQUORSOFT_COOKIE_SECURE=1` cuando el sitio ya tenga HTTPS.
   - Para esta demo: `LIQUORSOFT_APP_ENV=development` y
     `LIQUORSOFT_PAYMENT_SIMULATOR=1` mantienen el pago simulado.
5. Permisos habituales: carpetas `755`, archivos `644`. No hace falta hacer
   escribible todo el sitio; las sesiones usan la carpeta temporal del sistema.

## 5. Crear el administrador

`schema.sql` no incluye usuarios por seguridad y el hosting gratuito no tiene SSH.
La vía más simple:

1. Entrar a `https://TU-SUBDOMINIO/register` y crear una cuenta normal.
2. En phpMyAdmin, ejecutar (cambiando el correo):
   ```sql
   UPDATE usuarios
   SET rol_id = (SELECT id FROM roles WHERE nombre = 'Administrador' LIMIT 1)
   WHERE correo = 'tu-correo@dominio.com';
   ```
3. Cerrar sesión y volver a entrar. El panel estará en `/admin/dashboard`.

Alternativa local (solo si tienes PHP CLI): `php backend/database/seed_test_users.php`
crea `admin@liquorsoft.com` / `Admin1234*` y `cliente@liquorsoft.com` /
`Cliente1234*`. **No uses esas claves en un sitio público.**

## 6. Activar HTTPS y verificar

1. Activar el certificado SSL gratuito en el panel del hosting.
2. Abrir `https://TU-SUBDOMINIO/` y comprobar que carga el catálogo.
3. Verificaciones rápidas:
   ```bash
   curl -I https://TU-SUBDOMINIO/
   curl -s https://TU-SUBDOMINIO/api/products.php | head -c 300
   curl -s https://TU-SUBDOMINIO/api/payment-config.php
   curl -s https://TU-SUBDOMINIO/api/site-config.php
   ```
   `payment-config.php` debe responder con `"simulatorEnabled":true` y
   `"methods":["nequi_simulado"]` en modo demo. `site-config.php` debe incluir
   el número de WhatsApp configurado; si está vacío, el botón no se muestra.
4. Recorrido manual:
   - Inicio, catálogo, detalle de producto, carrito.
   - Checkout con el simulador (aprobado, rechazado, pendiente).
   - Registro e inicio de sesión.
   - Panel admin: dashboard, productos, inventario, ventas, usuarios.
   - Botón de WhatsApp y enlaces de contacto.
   - Recargar una ruta interna (p. ej. `/catalogo`) para comprobar el rewrite.
   - `https://TU-SUBDOMINIO/.env` debe devolver **403**.
   - La API debe incluir `X-Content-Type-Options`, `X-Frame-Options` y
     `Referrer-Policy`; compruébalo con `curl -I`.

## 7. Pagos y servicios externos

- **Modo demo:** `LIQUORSOFT_APP_ENV=development` + `LIQUORSOFT_PAYMENT_SIMULATOR=1`.
  Es la única forma de tener checkout sin credenciales de PayPal. No procesa
  dinero real.
- **PayPal Sandbox (opcional):** completar `PAYPAL_CLIENT_ID`,
  `PAYPAL_CLIENT_SECRET`, `PAYPAL_ENVIRONMENT=sandbox`,
  `PAYPAL_COP_PER_USD` y `PAYPAL_WEBHOOK_ID`; cambiar
  `LIQUORSOFT_APP_ENV=production`. Sin credenciales, el checkout queda sin
  métodos de pago si el simulador está apagado.
- **PayPal Live (cobros reales):** usar credenciales de una app Live, poner
  `PAYPAL_ENVIRONMENT=live`, `LIQUORSOFT_APP_ENV=production`,
  `LIQUORSOFT_COOKIE_SECURE=1` y una tasa COP/USD revisada. El botón mostrará
  “PayPal” y procesará dinero real; prueba primero con un importe pequeño.
- **Webhook de PayPal:** en PayPal Developer crea un webhook apuntando a
  `https://TU-DOMINIO/api/paypal/webhook.php`, suscribe los eventos
  `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED` y
  `PAYMENT.CAPTURE.REFUNDED`, y copia el ID en `PAYPAL_WEBHOOK_ID`. Sin ese ID
  el endpoint responde 503 y PayPal reintenta. El webhook verifica la firma
  contra la API de PayPal y guarda cada evento en `paypal_webhook_events`.
- **WhatsApp y contacto:** `LIQUORSOFT_WHATSAPP_NUMBER` en formato
  internacional sin signos (ej. `573001234567`), `LIQUORSOFT_WHATSAPP_MESSAGE`,
  `LIQUORSOFT_BUSINESS_NAME` y `LIQUORSOFT_SUPPORT_EMAIL`. Si el número está
  vacío, el botón flotante se oculta.
- **Asistente IA (opcional):** `LIQUORSOFT_OPENAI_API_KEY`. Si falta o es inválida,
  `chat.php` responde con el asistente local; no rompe la tienda.
- Nunca subir `backend/.env` ni claves reales al repositorio.

## 8. Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| 503 en toda la API | `.env` ausente o credenciales incorrectas | Crear `htdocs/.env`, revisar host/nombre/usuario/clave |
| 500 en PHP | Versión de PHP o extensión faltante | Seleccionar PHP 8.3/8.4; verificar `mysqli`, `curl`, `mbstring` |
| 404 al recargar `/catalogo` | `.htaccess` no subido o rewrite desactivado | Subir `.htaccess`; InfinityFree soporta mod_rewrite |
| Login no persiste | `LIQUORSOFT_COOKIE_SECURE=1` sin HTTPS | Activar SSL o poner 0 temporalmente |
| Imágenes de productos rotas | URLs externas con hotlink blocking | Reemplazar la imagen desde el panel admin |
| `.env` visible | `.htaccess` ausente | Subirlo; comprobar que `/.env` devuelve 403 |

## 9. Actualizaciones y rollback

- Para actualizar: ejecutar `./deploy/build.sh`, subir el nuevo ZIP y
  sobrescribir. La base de datos no se toca si no hay cambios de esquema.
- Antes de actualizar, exportar la BD desde phpMyAdmin (respaldo).
- Para volver atrás: restaurar los archivos anteriores y, si cambió el esquema,
  importar el respaldo.
- Los cambios de esquema futuros deben reflejarse en `schema.sql` y, si aplica,
  en una migración nueva. Las migraciones `017_real_catalog_refresh.sql` y
  `018_paypal_webhook_events.sql` ya están incluidas en `schema.sql`; no las
  ejecutes por separado en una instalación nueva.
