# Checkout, pagos de prueba y comprobantes

## Estado de la integración

El checkout calcula los precios desde MySQL y soporta **Nequi simulado** en desarrollo y **PayPal Checkout** cuando el servidor tiene credenciales y tasa COP/USD configuradas. PayPal crea y captura la orden desde el backend; el secreto no se envía al navegador. Con `PAYPAL_ENVIRONMENT=sandbox` usa dinero de prueba; con `live` procesa cobros reales y la interfaz muestra “PayPal” en lugar de “PayPal Sandbox”.

## Activar la simulación local

1. Edita el `backend/.env` existente y configura allí las credenciales locales de MySQL. No se requiere ni se debe crear un archivo de ejemplo. Los comentarios `#` se ignoran; no hace falta quitarlos del archivo.
2. Añade o conserva en el `.env` local:

   ```dotenv
   LIQUORSOFT_APP_ENV=development
   LIQUORSOFT_PAYMENT_SIMULATOR=1
   ```

3. Para esta base heredada, aplica las migraciones aditivas en orden después de respaldar la base de datos. `008` prepara las tablas de checkout que faltaban; `009`–`011` añaden pagos, historial y PayPal:

   ```bash
   mysql -u liquorsoft_app -p liquorsoft < backend/database/migrations/008_checkout_foundation_legacy.sql
   mysql -u liquorsoft_app -p liquorsoft < backend/database/migrations/009_payment_checkout.sql
   mysql -u liquorsoft_app -p liquorsoft < backend/database/migrations/010_order_fulfillment_status.sql
   mysql -u liquorsoft_app -p liquorsoft < backend/database/migrations/011_paypal_checkout_intents.sql
   ```

4. Inicia el backend con `php -S 127.0.0.1:8000 -t backend` y Angular con el proxy habitual del proyecto.
5. Inicia sesión, añade productos al carrito, completa la dirección y elige un resultado. Usa “Pago aprobado” para verificar compra, inventario, historial y PDF.

La simulación se apaga si el entorno no es exactamente `development` y el indicador no es exactamente `1`. PayPal se habilita de manera independiente con credenciales válidas, `PAYPAL_ENVIRONMENT=sandbox` o `live`, extensión PHP cURL y tasa COP/USD positiva. En `sandbox` no se mueve dinero real; en `live` sí, así que exige HTTPS, credenciales Live, webhook configurado y una prueba con importe pequeño antes de abrir la tienda.

## Configurar PayPal

1. En PayPal Developer crea una aplicación **Sandbox** (pruebas) o **Live** (cobros reales) y copia el Client ID y Secret de esa misma aplicación. No mezcles credenciales de ambientes distintos.
2. En `backend/.env` configura las credenciales, el ambiente y una tasa fija manual:

   ```dotenv
   PAYPAL_CLIENT_ID=tu_client_id
   PAYPAL_CLIENT_SECRET=tu_secret
   PAYPAL_ENVIRONMENT=sandbox
   PAYPAL_COP_PER_USD=4000
   PAYPAL_WEBHOOK_ID=tu_webhook_id
   ```

   `PAYPAL_COP_PER_USD` es un ejemplo, no una cotización recomendada. Ajusta y revisa la tasa que LiquorSoft usará. Los precios se mantienen en COP; el total se convierte a USD, se redondea a centavos y se presenta antes de aprobar en PayPal. La tasa y el importe se vuelven a validar al capturar el pago.
3. Crea un webhook en PayPal Developer apuntando a
   `https://TU-DOMINIO/api/paypal/webhook.php`, con los eventos
   `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED` y
   `PAYMENT.CAPTURE.REFUNDED`. Copia su ID en `PAYPAL_WEBHOOK_ID`. El webhook
   verifica la firma con PayPal y registra cada evento en
   `paypal_webhook_events`; si el ID falta, responde 503 y PayPal reintenta.
4. Instala la extensión PHP cURL y reinicia el servidor PHP. `/api/payment-config.php` publica el Client ID, el ambiente y el estado del método; nunca devuelve el Secret.
5. Aplica las migraciones después de respaldar la base de datos:

   ```bash
   mysql -u liquorsoft_app -p liquorsoft < backend/database/migrations/011_paypal_checkout_intents.sql
   mysql -u liquorsoft_app -p liquorsoft < backend/database/migrations/018_paypal_webhook_events.sql
   ```

6. Usa cuentas Sandbox de comprador para probar. Para pasar a Live usa credenciales Live, `PAYPAL_ENVIRONMENT=live`, `LIQUORSOFT_APP_ENV=production`, `LIQUORSOFT_COOKIE_SECURE=1`, un webhook Live y una tasa revisada; haz primero una compra real pequeña.
7. Un administrador autenticado puede comprobar el OAuth desde `GET /api/admin/paypal/diagnostics.php`. La respuesta solo indica el estado, ambiente y HTTP; no devuelve el Client Secret ni token.

## Probar OAuth y Orders API con cURL

En Bash, escribe las credenciales de Sandbox en los prompts; no las pegues en el comando ni ejecutes con `set -x`. El token y las respuestas se mantienen en variables de la sesión, y el ejemplo solo imprime el resultado, el ID de orden y el enlace de aprobación.

```bash
paypal_sandbox_test() {
read -rp "PayPal Sandbox Client ID: " PAYPAL_CLIENT_ID
read -rsp "PayPal Sandbox Client Secret: " PAYPAL_CLIENT_SECRET
printf '\n'

oauth_response=$(curl --silent --show-error \
  --user "$PAYPAL_CLIENT_ID:$PAYPAL_CLIENT_SECRET" \
  --header "Accept: application/json" \
  --header "Accept-Language: en_US" \
  --header "Content-Type: application/x-www-form-urlencoded" \
  --data "grant_type=client_credentials" \
  --write-out $'\n__HTTP_STATUS__:%{http_code}' \
  https://api-m.sandbox.paypal.com/v1/oauth2/token) || {
    echo "OAuth no pudo conectar con PayPal Sandbox."
    unset PAYPAL_CLIENT_ID PAYPAL_CLIENT_SECRET
    return 1
  }
oauth_status=${oauth_response##*$'\n__HTTP_STATUS__:'}
oauth_body=${oauth_response%$'\n__HTTP_STATUS__:'*}
if [[ "$oauth_status" != "200" ]]; then
  echo "OAuth falló con HTTP $oauth_status (401 suele indicar credenciales incorrectas)."
  unset PAYPAL_CLIENT_ID PAYPAL_CLIENT_SECRET oauth_response oauth_body
  return 1
fi
paypal_access_token=$(python3 -c 'import json,sys; print(json.load(sys.stdin).get("access_token",""))' <<<"$oauth_body")
if [[ -z "$paypal_access_token" ]]; then
  echo "PayPal respondió HTTP 200, pero no devolvió un access_token válido."
  unset PAYPAL_CLIENT_ID PAYPAL_CLIENT_SECRET oauth_response oauth_body
  return 1
fi
echo "OAuth Sandbox: autenticación correcta (HTTP 200; token no mostrado)."

create_response=$(curl --silent --show-error \
  --request POST https://api-m.sandbox.paypal.com/v2/checkout/orders \
  --header "Authorization: Bearer $paypal_access_token" \
  --header "Content-Type: application/json" \
  --header "PayPal-Request-Id: $(python3 -c 'import uuid; print(uuid.uuid4())')" \
  --data '{"intent":"CAPTURE","purchase_units":[{"amount":{"currency_code":"USD","value":"1.00"},"description":"LiquorSoft Sandbox smoke test"}]}' \
  --write-out $'\n__HTTP_STATUS__:%{http_code}') || {
    echo "No fue posible crear una orden Sandbox."
    unset PAYPAL_CLIENT_ID PAYPAL_CLIENT_SECRET paypal_access_token oauth_response oauth_body
    return 1
  }
create_status=${create_response##*$'\n__HTTP_STATUS__:'}
create_body=${create_response%$'\n__HTTP_STATUS__:'*}
if [[ "$create_status" != "201" ]]; then
  echo "La creación de orden falló con HTTP $create_status."
  unset PAYPAL_CLIENT_ID PAYPAL_CLIENT_SECRET paypal_access_token oauth_response oauth_body create_response create_body
  return 1
fi
paypal_order_id=$(python3 -c 'import json,sys; print(json.load(sys.stdin).get("id",""))' <<<"$create_body")
approval_url=$(python3 -c 'import json,sys; d=json.load(sys.stdin); print(next((x["href"] for x in d.get("links",[]) if x.get("rel")=="approve"),""))' <<<"$create_body")
echo "Orden creada: $paypal_order_id (HTTP 201)."
echo "Apruébala con una cuenta compradora Sandbox: $approval_url"

unset PAYPAL_CLIENT_ID PAYPAL_CLIENT_SECRET oauth_response oauth_body create_response create_body approval_url
}
paypal_sandbox_test
```

Abre el enlace con una cuenta compradora Sandbox y aprueba el pago. Luego ejecuta:

```bash
paypal_sandbox_capture() {
order_response=$(curl --silent --show-error \
  --header "Authorization: Bearer $paypal_access_token" \
  --header "Accept: application/json" \
  "https://api-m.sandbox.paypal.com/v2/checkout/orders/$paypal_order_id" \
  --write-out $'\n__HTTP_STATUS__:%{http_code}')
order_status=${order_response##*$'\n__HTTP_STATUS__:'}
order_body=${order_response%$'\n__HTTP_STATUS__:'*}
if [[ "$order_status" != "200" ]]; then
  echo "Consulta de orden falló con HTTP $order_status."
  return 1
fi
paypal_order_state=$(python3 -c 'import json,sys; print(json.load(sys.stdin).get("status",""))' <<<"$order_body")
if [[ "$paypal_order_state" != "APPROVED" ]]; then
  echo "La orden todavía no está aprobada (estado: $paypal_order_state). No se capturó."
  return 1
fi

capture_response=$(curl --silent --show-error \
  --request POST "https://api-m.sandbox.paypal.com/v2/checkout/orders/$paypal_order_id/capture" \
  --header "Authorization: Bearer $paypal_access_token" \
  --header "Content-Type: application/json" \
  --header "PayPal-Request-Id: liquorsoft-capture-$paypal_order_id" \
  --data '{}' \
  --write-out $'\n__HTTP_STATUS__:%{http_code}')
capture_status=${capture_response##*$'\n__HTTP_STATUS__:'}
capture_body=${capture_response%$'\n__HTTP_STATUS__:'*}
if [[ "$capture_status" != "201" ]] || ! python3 -c 'import json,sys; d=json.load(sys.stdin); sys.exit(0 if d.get("status")=="COMPLETED" and d.get("purchase_units",[{}])[0].get("payments",{}).get("captures",[{}])[0].get("status")=="COMPLETED" else 1)' <<<"$capture_body"; then
  echo "La captura no se confirmó (HTTP $capture_status); no la marques como pagada."
  return 1
fi
echo "Captura confirmada: HTTP 201, order COMPLETED, capture COMPLETED."
unset paypal_access_token paypal_order_id order_response order_body capture_response capture_body
}
paypal_sandbox_capture
```

No captures antes de aprobar. No compartas la salida sin quitar el order ID y enlace personales.

## Colección Postman

Importa `docs/postman/PayPal-Sandbox.postman_collection.json` y `docs/postman/PayPal-Sandbox.postman_environment.json`. Selecciona el entorno importado y completa localmente `paypal_client_id` y `paypal_client_secret` (son variables `secret`). Ejecuta OAuth y luego Create order; revisa el enlace de aprobación en la respuesta de creación, aprueba la orden con un comprador Sandbox, y ejecuta Get order y Capture. No exportes el entorno con credenciales o token cargados. La colección también incluye una consulta a un ID inexistente.

## Resultado de auditoría local (2026-10-17)

- OAuth real Sandbox con las credenciales que ya estaban configuradas: HTTP 200 y `access_token` válido; no se mostró ni guardó el token en el informe.
- OAuth negativo real con Client ID alterado y con Client Secret alterado: ambos respondieron HTTP 401.
- Orders API Sandbox: se creó inicialmente una orden de USD 1.00 (HTTP 201, `CREATED`). Después se aprobaron desde el checkout dos órdenes de prueba distintas; PayPal confirma ambas con orden y captura `COMPLETED`, por USD 51.20 y USD 94.90.
- Configuración local: `PAYPAL_ENVIRONMENT` estaba como URL, no como `sandbox`; se corrigió en el `backend/.env` existente sin modificar Client ID ni Secret. La tasa fija COP/USD se sincronizó con la que ya publicaba el backend activo.
- La API local `/api/payment-config.php` responde `methods: ["nequi_simulado", "paypal"]`. La captura proporcionada de “sin métodos” no corresponde a la configuración actual de la API; recarga forzada/reinicia Angular para cargar el bundle actualizado.
- La base local MySQL 8 tenía solo tablas heredadas de usuarios/productos/ventas y carecía de tablas de pedidos/inventario que el checkout ya necesitaba. Se aplicaron las migraciones aditivas 008–011: crearon las tablas faltantes y agregaron campos de venta, sin cambiar los conteos de usuarios (2), productos (7) o ventas (0). La migración 008 evita claves foráneas nuevas porque los IDs de la base heredada son signed y los del esquema limpio son unsigned. No se ejecutó `schema.sql`, que contiene `DROP TABLE`.
- PHPUnit no forma parte de las dependencias; las pruebas PHP ejecutables con `php` pasaron. `npm run build` y las 16 pruebas Angular pasaron. Los cinco casos de clasificación OAuth (éxito, credenciales inválidas, servicio, red y respuesta malformada) están cubiertos por mocks locales.

| Caso | Resultado |
|---|---|
| Credenciales Sandbox correctas | Aprobado: HTTP 200 real |
| Client ID inválido | Aprobado: HTTP 401 real |
| Client Secret inválido | Aprobado: HTTP 401 real |
| Variables incompletas / tasa ausente | Aprobado: PayPal queda deshabilitado |
| Timeout/red/servicio/respuesta OAuth inesperada | Aprobado con pruebas unitarias mock; no se simuló una caída remota |
| Crear orden Sandbox | Aprobado: HTTP 201, `CREATED` |
| Aprobar con cuenta Sandbox | Aprobado: la orden del checkout pasó a `COMPLETED` |
| Captura correcta en Sandbox | Aprobado: dos capturas reales de Sandbox, ambas `COMPLETED`; importes USD 51.20 y USD 94.90. Ambas se verificaron consultando Orders API |
| Captura rechazada/doble captura en Sandbox | No ejecutado; las dos compras tienen claves idempotentes distintas y no son una repetición de captura de la misma orden |
| Alteración del importe/propiedad de orden/carrito vacío/stock | Protección revisada en backend; pruebas HTTP bloqueadas por las tablas base faltantes |
| Migraciones de checkout y PayPal | Aprobado en la base local: 008–011 aplicadas, conteos de datos existentes sin cambios |
| Persistencia del pedido/inventario después de captura | Aprobado tras reconciliar: dos pedidos confirmados, dos ventas completadas y tres movimientos de salida, sin duplicar movimientos al reintentar la primera orden |
| Postman | CLI instalado; colección y entorno JSON válidos; la ejecución con entorno de plantilla vacío respondió 401 por credenciales ausentes y no se considera una prueba aprobada |

## Persistencia y consistencia

`POST /api/purchase.php` requiere sesión autenticada, clave idempotente UUID, artículos, dirección y fecha de nacimiento que cumpla 18 años. La fecha se valida en el backend y no se persiste. Esta declaración no sustituye la comprobación de identidad/edad al entregar la compra. La ley colombiana prohíbe vender bebidas alcohólicas a menores de edad ([Ley 124 de 1994](https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php/norma_pdf.php?i=291)). Para PayPal, `POST /api/paypal/create-order.php` valida productos, stock y precios, crea la orden en PayPal fuera de la transacción y persiste una intención ligada al usuario, carrito, dirección y clave idempotente. El navegador recibe el ID de PayPal, nunca el Secret.

Al aprobar, `purchase.php` valida la intención, el carrito, el importe USD y la moneda sin abrir transacción; después obtiene o captura el cobro en PayPal, también fuera de la base de datos. Solo entonces abre una transacción local corta, bloquea producto y pedido, revalida precios/stock, crea pedido, venta, detalle y movimientos, y marca la intención como `COMPLETED`. Si esa transacción falla después de una captura, el backend intenta reembolsar la captura automáticamente; si el reembolso también falla, registra un log crítico y notifica a los administradores para reembolso manual.

Solo un cobro completado por PayPal o el resultado simulado `approved` confirma el pedido, crea la venta y sus detalles, actualiza el stock de forma condicional y registra los movimientos en la transacción local. Rechazo, pendiente, cancelación y error no crean venta ni descuentan inventario. No hay reservas de stock: el inventario se verifica de nuevo dentro de la transacción final. Si se pierde la respuesta o falla la persistencia tras un cobro remoto, el checkout conserva el ID PayPal y la clave idempotente; “Volver a verificar” consulta la misma orden y no inicia otro pago. Si la base de datos continúa indisponible, no vuelvas a pagar hasta que se haya reconciliado el mismo pedido.

## Verificación adicional del checkout (2026-10-17)

- La navegación directa anónima a `/checkout` fue reproducida en Firefox headless. El guard redirige a `/login`; el backend responde HTTP 401 a `/api/auth/me.php` sin sesión, que es el comportamiento esperado. La causa del retorno al inicio era que el login no conservaba la ruta original. Ahora el guard transmite `returnUrl` y, después de autenticar y confirmar la sesión, el login vuelve a la ruta interna solicitada.
- El checkout ya recibe `paypalEnabled: true` y `methods: ["nequi_simulado", "paypal"]` del backend activo. Una prueba Angular verifica que PayPal aparezca seleccionable después de recibir esa configuración.
- Una prueba Angular simula una respuesta fallida al confirmar y verifica que el reintento use el mismo ID de orden y la misma clave idempotente. Otra restaura una orden aprobada después de recrear el checkout y verifica que no se cree una orden PayPal nueva. La cesta no se limpia ni aparece la confirmación de éxito hasta que el backend devuelve `CAPTURADO`.
- El inventario se actualiza dentro de la transacción local antes de llamar a captura, con bloqueo de filas y decremento condicional; si captura o persistencia falla, la transacción se revierte. La captura completada se puede reconsultar por el mismo ID PayPal para reintentar la persistencia.
- El build Angular final y sus 21 pruebas pasan; las pruebas PHP de configuración, OAuth y validación de orden/captura también pasan. OAuth Sandbox volvió a responder HTTP 200. `backend/.env` continúa ignorado por Git.
- Recuperación de la primera compra Sandbox: la captura de USD 51.20 quedó validada contra la intención local y se persistió al reintentar con la misma orden y clave idempotente. Después se creó y capturó otra orden Sandbox distinta por USD 94.90; no se puede determinar solo desde los registros si fue intencional. No se inició otra captura para ninguna de esas órdenes.
- Verificación final de PayPal y MySQL: ambas órdenes y capturas responden `COMPLETED`; hay dos pedidos (`CONFIRMADO`/`CAPTURADO`) y dos ventas completadas. Sus totales son COP 204.800 y COP 379.600, respectivamente. Se registraron tres movimientos de inventario: el producto interno 5 pasó de 9 a 8 unidades y el producto 6 de 21 a 16 (una unidad y cuatro unidades vendidas, respectivamente). No se detectaron movimientos repetidos para una misma compra. Los dos pagos son Sandbox, no cargos reales.
- La causa exacta de la primera falla no quedó en un log disponible. Para evitar que una respuesta ambigua de `POST capture` deje el pago separado de la base, el backend consulta después el estado canónico de la orden en PayPal; si la captura ya quedó `COMPLETED`, continúa persistiendo esa misma compra. El endpoint registra ahora una etapa interna sanitizada para diagnosticar fallos futuros sin incluir tokens, credenciales ni datos personales en logs.

## Comprobante PDF

`GET /api/invoice.php?id=<pedido>` permite descargar un PDF solo para el comprador autenticado o un administrador, y únicamente si el pago figura como aprobado. Usa pedido y líneas persistidas, incluidos nombre y precio capturados al comprar. El número `FAC-<número de pedido>` es estable y no se emiten comprobantes duplicados en la base.

El PDF actual es un **comprobante comercial de prueba**, no una factura electrónica ni un documento fiscal autorizado. Faltan razón social, NIT, dirección legal y cualquier integración de facturación DIAN. Los datos inexistentes se indican como pendientes y no se inventan.

## Webhook y conciliación

`POST /api/paypal/webhook.php` exige `PAYPAL_WEBHOOK_ID`, valida las cabeceras
`PAYPAL-*` y verifica la firma contra PayPal antes de procesar el evento. Los
eventos se guardan en `paypal_webhook_events` con clave única por `event_id`.

- `PAYMENT.CAPTURE.COMPLETED`: si encuentra pedido local, avisa a administradores
  cuando el pedido no estaba en `CAPTURADO`; si no lo encuentra, crea una
  notificación de reconciliación para revisar o reembolsar en PayPal.
- `PAYMENT.CAPTURE.DENIED`: marca el pago como `RECHAZADO` solo si seguía
  pendiente y registra historial.
- `PAYMENT.CAPTURE.REFUNDED`: marca el pago como `REEMBOLSADO` y registra historial.

El procesamiento es transaccional: si falla, se revierte y el evento queda con
`processed = 0`; el siguiente reintento de PayPal vuelve a procesarlo. Un evento
ya procesado responde `duplicate: true` sin repetir efectos.

## Pruebas manuales sugeridas

- Aprobado: pedido confirmado, venta, movimiento de salida y descarga del PDF.
- Rechazado, pendiente, cancelado y error: pedido con su estado de pago, sin venta ni cambio de stock.
- Repetir la misma solicitud con el UUID: recuperar el pedido existente sin segundo descuento.
- Cambiar el ID de la descarga: el endpoint debe responder 404 para pedidos de otra persona.
- Configurar entorno production: `/api/payment-config.php` debe devolver `simulatorEnabled: false` y purchase debe rechazar la simulación.
- Con PayPal Sandbox: validar que el botón no aparece sin configuración completa, probar aprobación/cancelación, confirmar la moneda USD y cotejar que solo una captura completada genera venta y descuento de inventario.
- Con `PAYPAL_ENVIRONMENT=live`: la interfaz debe mostrar “PayPal”, la compra debe ser real y el webhook debe quedar registrado en `paypal_webhook_events`; prueba con un importe pequeño y reembolsa manualmente si algo falla.

## Notificaciones

La aplicación incorpora `NotificationService` con métodos `showSuccess`, `showError`, `showWarning`, `showInfo` y `confirm`. La confirmación del borrado de productos usa un diálogo accesible; las notificaciones globales reemplazan avisos breves. No se añadió Bootstrap porque no forma parte de las dependencias existentes; los componentes usan Angular y CSS del proyecto.
