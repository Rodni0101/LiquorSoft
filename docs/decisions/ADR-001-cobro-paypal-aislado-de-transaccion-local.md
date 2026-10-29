# ADR-001: Aislar el cobro de PayPal de la transacción local del checkout

## Estado

Aceptado — 2026-10-29 (commit `6eb53ab`)

## Contexto

`POST /api/purchase.php` confirma una compra: valida el carrito, cobra en PayPal,
crea el pedido, la venta, descuenta inventario y registra movimientos e
historial.

La implementación anterior hacía **todo dentro de una única transacción MySQL
larga**, con `FOR UPDATE` sobre `productos` y `paypal_checkout_intents`, e
incluso ejecutaba la llamada HTTP de captura a PayPal **con la transacción
abierta**. Esto producía tres problemas concretos:

1. **Locks prolongados.** Una llamada de red a PayPal (cientos de milisegundos
   o más) mantenía filas de productos bloqueadas, serializando checkout
   concurrentes y arriesgando agotamiento de `innodb_lock_wait_timeout`.
2. **Cobro sin pedido.** Si la respuesta de PayPal se perdía o la transacción
   local fallaba después de la captura, el cliente quedaba **cobrado sin
   pedido ni venta**, sin mecanismo de compensación.
3. **Sin conciliación.** No había registro persistente de los eventos de PayPal:
   si el webhook no llegaba o la respuesta HTTP se perdía, no quedaba rastro
   auditable.

Además, la validación de idempotencia dependía de que el `INSERT` de `pedidos`
fallara con `errno 1062` dentro de la transacción, lo que hacía difícil
reintentar una compra a medias sin duplicar efectos.

## Decisión

Separar el proceso en **tres fases con responsabilidades distintas**:

1. **Fase de validación (sin transacción).** Verificar clave idempotente,
   carrito, productos, precios e importe USD con lecturas simples, sin
   `FOR UPDATE`. Si el pedido ya está finalizado, responder con la respuesta
   existente y no cobrar.
2. **Fase de cobro remota (fuera de la base de datos).** Obtener o capturar la
   orden en PayPal sin ninguna transacción abierta. La red no puede bloquear
   filas.
3. **Fase de persistencia (transacción corta).** Abrir la transacción,
   bloquear el pedido y los productos, **revalidar precio y stock**, crear
   pedido/venta/detalle/movimientos, marcar la intención como `COMPLETED` y
   hacer `commit`.

A esta decisión se acompaña un mecanismo de **compensación**:

- Si la fase 3 falla **después** de haber capturado en PayPal, se intenta
  `paypalRefundCapture()`.
- Si el reembolso falla, se registra un log `CRITICAL` y se notifica a los
  administradores (`PAYPAL_REEMBOLSO_FALLIDO`) para reembolso manual.
- Nunca se reembolsa una captura que ya tenga una venta local confirmada.

Y una capa de **conciliación asíncrona** vía webhook
(`POST /api/paypal/webhook.php`), con verificación de firma, tabla
`paypal_webhook_events` y procesamiento idempotente y transaccional.

## Alternativas consideradas

### Mantener la transacción única (enfoque anterior)

- **A favor:** un solo sitio donde ocurre la verdad; sin código de compensación.
- **En contra:** bloqueos sostenidos durante I/O de red; un fallo tardío deja
  dinero cobrado sin pedido; sin resiliencia ante pérdida de respuesta.
- **Rechazada:** es la causa directa de los incidentes que motivaron este cambio.

### Reservar stock antes de cobrar

- **A favor:** elimina el fallo "el inventario cambió después de cobrar".
- **En contra:** exige un sistema de reservas con expiración, concurrencia adicional
  y reglas de liberación; el proyecto no tiene tabla de reservas y el volumen
  actual no lo justifica.
- **Rechazada por ahora:** se mantiene la revalidación dentro de la transacción
  corta + reembolso automático, que resuelve el caso con mucha menos
  superficie. Puede revisarse si el volumen crece.

### Cola de eventos para el cobro

- **A favor:** total desacoplamiento y reintento garantizado.
- **En contra:** introduce infraestructura de mensajes que el despliegue actual
  (hosting compartido con PHP + MySQL) no tiene.
- **Rechazada:** desproporcionada para el tamaño del sistema.

### Confiar únicamente en el webhook

- **A favor:** una sola ruta de confirmación.
- **En contra:** el webhook puede tardar o perderse; el usuario espera
  confirmación en pantalla y el inventario debe reflejarse pronto.
- **Rechazada:** el webhook queda como **conciliación y auditoría**, no como
  vía principal.

## Consecuencias

**Positivas**

- El tiempo con locks de fila abiertos deja de incluir latencia de red.
- Un fallo local posterior a la captura ya no deja dinero cobrado sin pedido:
  se reembolsa o se notifica con la referencia exacta (`capture_id`).
- Reintentos y peticiones concurrentes con la misma clave son seguros
  (respuesta idempotente, sin duplicar ventas).
- Los eventos de PayPal quedan auditados en `paypal_webhook_events`, con
  `processed`, `error` y `procesado_en`.
- `checkoutStage` permite localizar la fase exacta de un fallo en los logs.

**Negativas / riesgos**

- El flujo tiene más estados y más caminos de error; exige pruebas de
  concurrencia y de reembolso que aún **no existen**.
- Aparece código de compensación (reembolso) que debe mantenerse alineado con
  la API de PayPal.
- La revalidación tardía significa que el usuario puede recibir un
  **422 por precio/stock cambado** después de haber aprobado el pago en PayPal
  (mitigado con el reembolso automático, pero visible).
- El webhook exige una configuración operativa adicional
  (`PAYPAL_WEBHOOK_ID`); sin ella, PayPal reintenta de forma continua.

## Referencias

- Cambio completo: [`docs/cambios/2026-10-29-6eb53ab.md`](../cambios/2026-10-29-6eb53ab.md)
- Contrato y pruebas: [`docs/checkout-pagos-y-comprobantes.md`](../checkout-pagos-y-comprobantes.md)
- Código: `backend/api/purchase.php`, `backend/api/paypal/create-order.php`,
  `backend/api/paypal/webhook.php`, `backend/api/config/paypal.php`
- Esquema: `backend/database/migrations/018_paypal_webhook_events.sql`
