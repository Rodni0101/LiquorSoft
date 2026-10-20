# LiquorSoft

Tienda online de licores con panel administrativo, construida con Angular, PHP
y MySQL. La aplicación contempla los roles `Cliente` y `Administrador`; las
rutas y endpoints privados validan la sesión PHP y el rol correspondiente.

## Catálogo público

La ruta raíz (`/`) abre el catálogo público de LiquorSoft. También está
disponible directamente en `/catalogo` y puede ser consultado sin iniciar
sesión. Incluye:

- Productos organizados por categorías: licores, vinos, cervezas y mixers.
- Búsqueda por nombre, categoría o descripción.
- Filtro rápido por categoría.
- Tarjetas responsive con precio, descripción y productos destacados.
- Fotografía por producto si `productos.imagen` contiene una ruta/URL válida;
  cuando falta o no carga, se muestra una ilustración SVG local según la
  categoría. Las ilustraciones son recursos genéricos, no fotos ni logos de
  marcas.

La portada continúa disponible en `/inicio`, la tienda en `/productos` y el
flujo administrativo protegido en `/admin/dashboard`.

Los recursos alternativos viven en `LiquorSoft-angular/public/assets/products/`
y los renderiza el componente reutilizable `ProductVisual`. Está integrado en
catálogo, inicio, detalle, recomendaciones, carrito, chat y lista del panel
administrativo. Para usar una foto real, asigna su ruta o URL desde el campo de
imagen del formulario administrativo; ese campo no carga archivos.

## Dashboard y datos

El dashboard privado en `/dashboard` consume métricas reales desde
`backend/api/dashboard.php`: productos activos, unidades disponibles, valor del
inventario, alertas de stock bajo y ventas del día. El catálogo consume
`backend/api/products.php`, por lo que los productos sembrados en la base de
datos se reflejan automáticamente en la vista pública.

El archivo `backend/database/schema.sql` crea el esquema relacional completo
(usuarios, categorías, proveedores, clientes, productos, lotes, pedidos,
compras, ventas, movimientos y configuración) e inserta categorías/productos
de muestra. **Es destructivo**: elimina las tablas indicadas al inicio y solo
debe usarse para una base descartable vacía.

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS liquorsoft CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p liquorsoft < backend/database/schema.sql
php -S 127.0.0.1:8000 -t backend
```

Las credenciales se configuran con `LIQURSOFT_DB_HOST`, `LIQURSOFT_DB_USER`,
`LIQURSOFT_DB_PASSWORD` y `LIQURSOFT_DB_NAME` en `backend/.env` o en el
entorno del proceso. Angular reenvía `/api` al
backend mediante `proxy.conf.json` durante el desarrollo.

En producción configura también `LIQURSOFT_COOKIE_SECURE=1` y sirve el frontend
y backend sobre HTTPS. Las operaciones autenticadas usan una protección CSRF
mediante la cookie `liquorsoft_csrf` y el encabezado `X-CSRF-Token`.

Si MySQL tiene contraseña para `root`, debes exportarla antes de iniciar PHP
(o usar un usuario dedicado):

```bash
export LIQURSOFT_DB_HOST=127.0.0.1
export LIQURSOFT_DB_USER=root
export LIQURSOFT_DB_PASSWORD=''
export LIQURSOFT_DB_NAME=liquorsoft
php -S 127.0.0.1:8000 -t backend
```

No uses ni guardes credenciales dentro del código. Copia `.env.example` (en la raíz
del repositorio) a `backend/.env` y reemplaza los valores localmente. El archivo
`.env` no debe versionarse. Para desplegar en un hosting gratuito consulta
`DEPLOYMENT.md`.

## Chat del catálogo

El widget envía preguntas a `POST /api/chat.php`. El backend consulta `productos`
y `categorias` con consultas preparadas, limita resultados a seis artículos y
solo recomienda productos activos con stock positivo. Conserva el criterio de
categoría y precio en la sesión PHP para preguntas de seguimiento. El estado de
pedidos se devuelve únicamente para el usuario de la sesión actual.

El chatbot admite respuestas generativas con OpenAI Responses API cuando se
configura `LIQUORSOFT_OPENAI_API_KEY` en `backend/.env`; el modelo se elige con
`LIQUORSOFT_OPENAI_MODEL` (por defecto `gpt-4.1-mini`). La clave solo se lee en
PHP y nunca se envía al navegador. Sin clave, el endpoint conserva las
respuestas locales. OpenAI puede pedir al backend búsquedas del catálogo,
conteos y consulta de pedidos; PHP ejecuta únicamente esas herramientas de
solo lectura, con consultas preparadas y pedidos limitados al usuario de la
sesión. La conversación se mantiene en la sesión PHP y la API se llama con
`store: false`; no se guarda una transcripción en la base de datos. Configura
límites y facturación en el proyecto de OpenAI si habilitas la clave. No hay
integración con n8n en el repositorio.

## Estado de auditoría local

La conexión MySQL solo se considera comprobada tras consultar el servidor con
las credenciales del entorno. Si MySQL no está iniciado, el backend responde
503 y el catálogo/chat no pueden consultar productos. No ejecutes
`backend/database/schema.sql` para solucionar conectividad: puede borrar datos.
La migración `003_reset_users_create_admin.sql` está deprecada porque borraba
usuarios y contenía una credencial compartida; no la ejecutes.

This project was generated using [Angular CLI](https://github.com/angular/angular-cli) version 22.1.5.

## Development server

To start a local development server, run:

```bash
ng serve
```

El registro público crea cuentas de cliente. Las cuentas administrativas se
gestionan desde el panel protegido. El proxy de Angular reenvía `/api` al
backend, por lo que no es necesario habilitar CORS localmente.

Para alinear una base existente, ejecuta las migraciones en orden, terminando
con `006_admin_only_operational_schema.sql`, `007_customer_role.sql` y
`008_orders_purchases_lots.sql`. El esquema completo está pensado para una
instalación limpia; las migraciones permiten conservar datos existentes.

Once the server is running, open your browser and navigate to `http://localhost:4200/`. The application will automatically reload whenever you modify any of the source files.

## Code scaffolding

Angular CLI includes powerful code scaffolding tools. To generate a new component, run:

```bash
ng generate component component-name
```

For a complete list of available schematics (such as `components`, `directives`, or `pipes`), run:

```bash
ng generate --help
```

## Building

To build the project run:

```bash
ng build
```

This will compile your project and store the build artifacts in the `dist/` directory. By default, the production build optimizes your application for performance and speed.

## Running unit tests

To execute unit tests with the [Vitest](https://vitest.dev/) test runner, use the following command:

```bash
ng test
```

## Running end-to-end tests

For end-to-end (e2e) testing, run:

```bash
ng e2e
```

Angular CLI does not come with an end-to-end testing framework by default. You can choose one that suits your needs.

## Additional Resources

For more information on using the Angular CLI, including detailed command references, visit the [Angular CLI Overview and Command Reference](https://angular.dev/tools/cli) page. 
