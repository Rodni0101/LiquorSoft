# LiquorSoftAngular

## Catálogo público

La ruta raíz (`/`) abre el catálogo público de LiquorSoft. También está
disponible directamente en `/catalogo` y puede ser consultado sin iniciar
sesión. Incluye:

- Productos organizados por categorías: licores, vinos, cervezas y mixers.
- Búsqueda por nombre, categoría o descripción.
- Filtro rápido por categoría.
- Tarjetas responsive con precio, descripción y productos destacados.

La vista administrativa/presentación original continúa disponible en `/inicio`.

## Dashboard y datos

El dashboard privado en `/dashboard` consume métricas reales desde
`backend/api/dashboard.php`: productos activos, unidades disponibles, valor del
inventario, alertas de stock bajo y ventas del día. El catálogo consume
`backend/api/products.php`, por lo que los productos sembrados en la base de
datos se reflejan automáticamente en la vista pública.

El archivo `backend/database/schema.sql` crea las tablas `productos` y `ventas`
e inserta una selección inicial de ocho productos. Ejecútalo una vez sobre
MySQL antes de iniciar el backend PHP:

```bash
mysql -u root -p < backend/database/schema.sql
php -S localhost:8000 -t backend
```

Las credenciales se configuran con `LIQURSOFT_DB_HOST`, `LIQURSOFT_DB_USER`,
`LIQURSOFT_DB_PASSWORD` y `LIQURSOFT_DB_NAME`. Angular reenvía `/api` al
backend mediante `proxy.conf.json` durante el desarrollo.

Si MySQL tiene contraseña para `root`, debes exportarla antes de iniciar PHP
(o usar un usuario dedicado):

```bash
export LIQURSOFT_DB_HOST=127.0.0.1
export LIQURSOFT_DB_USER=root
export LIQURSOFT_DB_PASSWORD=''
export LIQURSOFT_DB_NAME=liquorsoft
php -S 127.0.0.1:8000 -t backend
```

No uses ni guardes la contraseña dentro del código. Consulta
`backend/.env.example` como referencia.

This project was generated using [Angular CLI](https://github.com/angular/angular-cli) version 22.1.5.

## Development server

To start a local development server, run:

```bash
ng serve
```

Para habilitar el registro durante el desarrollo, ejecuta el backend PHP desde
la raíz del repositorio (`php -S localhost:8000 -t backend`) y crea la base de
datos con `backend/database/schema.sql`. El proxy de Angular reenvía `/api` al
backend, por lo que no es necesario habilitar CORS localmente.

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
