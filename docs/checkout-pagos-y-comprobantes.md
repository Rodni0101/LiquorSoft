# Checkout, pagos de prueba y comprobantes

## Estado de la integración

El checkout usa el catálogo y la sesión de LiquorSoft y calcula precios desde MySQL. En desarrollo se puede seleccionar **Nequi simulado** y elegir aprobación, rechazo, pendiente, cancelación o error. Es una simulación local; no se conecta a Nequi ni mueve dinero.

PayPal Sandbox y Nequi real no están integrados. No hay credenciales ni una configuración de proveedor disponible en este repositorio. No se debe presentar el simulador como pago real.

## Activar la simulación local

1. Copia `backend/.env.example` a `backend/.env` y configura las credenciales locales de MySQL. Los comentarios `#` se ignoran; no hace falta quitarlos del archivo.
2. Añade o conserva en el `.env` local:

   ```dotenv
   LIQUORSOFT_APP_ENV=development
   LIQUORSOFT_PAYMENT_SIMULATOR=1
   ```

3. Aplica la migración aditiva tras respaldar la base de datos:

   ```bash
   mysql -u liquorsoft_app -p liquorsoft < backend/database/migrations/009_payment_checkout.sql
   ```

4. Inicia el backend con `php -S 127.0.0.1:8000 -t backend` y Angular con el proxy habitual del proyecto.
5. Inicia sesión, añade productos al carrito, completa la dirección y elige un resultado. Usa “Pago aprobado” para verificar compra, inventario, historial y PDF.

La simulación se apaga si el entorno no es exactamente `development` y el indicador no es exactamente `1`. En entornos que no cumplan ambas condiciones, tanto el endpoint de configuración como el de compra la deshabilitan. No habilites estas variables en producción.

## Persistencia y consistencia

`POST /api/purchase.php` requiere sesión autenticada, una clave idempotente UUID, artículos, dirección, una fecha de nacimiento que cumpla 18 años y un resultado de simulación permitido. La fecha se valida en el backend y no se persiste. Esta declaración no sustituye la comprobación de identidad/edad al entregar la compra. La ley colombiana prohíbe vender bebidas alcohólicas a menores de edad ([Ley 124 de 1994](https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php/norma_pdf.php?i=291)). El servidor bloquea los productos, obtiene nombres/precios oficiales y crea pedido y detalles en una transacción. No acepta importes del navegador. La restricción única de `clave_idempotencia` devuelve el pedido existente al repetir una solicitud.

Solo el resultado `approved` confirma el pedido, crea la venta y sus detalles, actualiza el stock de forma condicional y registra los movimientos, todo en la misma transacción. Rechazo, pendiente, cancelación y error no crean venta ni descuentan inventario. No hay reservas de stock: el inventario se verifica de nuevo al aprobar.

## Comprobante PDF

`GET /api/invoice.php?id=<pedido>` permite descargar un PDF solo para el comprador autenticado o un administrador, y únicamente si el pago figura como aprobado. Usa pedido y líneas persistidas, incluidos nombre y precio capturados al comprar. El número `FAC-<número de pedido>` es estable y no se emiten comprobantes duplicados en la base.

El PDF actual es un **comprobante comercial de prueba**, no una factura electrónica ni un documento fiscal autorizado. Faltan razón social, NIT, dirección legal y cualquier integración de facturación DIAN. Los datos inexistentes se indican como pendientes y no se inventan.

## Pruebas manuales sugeridas

- Aprobado: pedido confirmado, venta, movimiento de salida y descarga del PDF.
- Rechazado, pendiente, cancelado y error: pedido con su estado de pago, sin venta ni cambio de stock.
- Repetir la misma solicitud con el UUID: recuperar el pedido existente sin segundo descuento.
- Cambiar el ID de la descarga: el endpoint debe responder 404 para pedidos de otra persona.
- Configurar entorno production: `/api/payment-config.php` debe devolver `simulatorEnabled: false` y purchase debe rechazar la simulación.

## Notificaciones

La aplicación incorpora `NotificationService` con métodos `showSuccess`, `showError`, `showWarning`, `showInfo` y `confirm`. La confirmación del borrado de productos usa un diálogo accesible; las notificaciones globales reemplazan avisos breves. No se añadió Bootstrap porque no forma parte de las dependencias existentes; los componentes usan Angular y CSS del proyecto.
