# Modelo Relacional de la Base de Datos - LiquorSoft

Este documento detalla el **Modelo Relacional Físico** de la base de datos de **LiquorSoft** (MySQL 8+ / MariaDB 10.4+ InnoDB, charset `utf8mb4_unicode_ci`).

---

## 1. Diagrama Físico del Modelo Relacional (Mermaid)

```mermaid
erDiagram
    roles ||--o{ usuarios : "fk_usuario_rol"
    categorias ||--o{ productos : "fk_producto_categoria"
    marcas ||--o{ productos : "fk_producto_marca"
    proveedores ||--o{ productos : "fk_producto_proveedor"
    proveedores ||--o{ compras : "fk_compras_proveedor"
    usuarios ||--o{ compras : "fk_compras_usuario"
    compras ||--|{ detalle_compra : "fk_detalle_compra"
    productos ||--o{ detalle_compra : "fk_detalle_compra_producto"
    lotes ||--o{ detalle_compra : "fk_detalle_compra_lote"
    productos ||--o{ lotes : "fk_lotes_producto"
    proveedores ||--o{ lotes : "fk_lotes_proveedor"
    productos ||--o{ movimientos_inventario : "fk_mov_producto"
    usuarios ||--o{ movimientos_inventario : "fk_mov_usuario"
    usuarios ||--o{ pedidos : "fk_pedidos_usuario"
    pedidos ||--|{ detalle_pedido : "fk_detalle_pedido"
    productos ||--o{ detalle_pedido : "fk_detalle_pedido_producto"
    pedidos ||--o{ historial_pedido : "fk_historial_pedido"
    usuarios ||--o{ historial_pedido : "fk_historial_usuario"
    pedidos ||--o| ventas : "fk_ventas_pedido"
    usuarios ||--o{ ventas : "fk_ventas_usuario"
    clientes ||--o{ ventas : "fk_ventas_cliente"
    ventas ||--|{ detalle_venta : "fk_detalle_venta"
    productos ||--o{ detalle_venta : "fk_detalle_producto"
    usuarios ||--o{ notificaciones : "fk_notificaciones_usuario"
    pedidos ||--o{ notificaciones : "fk_notificaciones_pedido"
    usuarios ||--o{ paypal_checkout_intents : "fk_paypal_usuario"

    roles {
        int id PK
        varchar_50 nombre UK
        varchar_255 descripcion
        tinyint_1 estado
        timestamp fecha_creacion
    }

    usuarios {
        int id PK
        int rol_id FK
        varchar_100 nombre
        varchar_100 apellido
        varchar_120 correo UK
        varchar_255 password_hash
        varchar_20 telefono
        varchar_50 usuario UK
        varchar_255 password
        varchar_255 foto
        datetime ultimo_login
        tinyint_1 estado
        timestamp fecha_creacion
    }

    clientes {
        int id PK
        varchar_120 nombre
        varchar_30 identificacion UK
        varchar_20 telefono
        varchar_120 correo
        text direccion
        date fecha_nacimiento
        int puntos
        tinyint_1 estado
        timestamp fecha_creacion
    }

    categorias {
        int id PK
        varchar_80 nombre UK
        text descripcion
        tinyint_1 estado
        timestamp fecha_creacion
    }

    marcas {
        int id PK
        varchar_80 nombre UK
        text descripcion
        tinyint_1 estado
        timestamp fecha_creacion
    }

    proveedores {
        int id PK
        varchar_120 empresa
        varchar_40 nit
        varchar_100 contacto
        varchar_20 telefono
        varchar_120 correo
        text direccion
        varchar_80 ciudad
        varchar_80 pais
        tinyint_1 estado
        timestamp fecha_creacion
    }

    productos {
        int id PK
        int categoria_id FK
        int marca_id FK
        int proveedor_id FK
        varchar_40 codigo UK
        varchar_100 codigo_barras
        varchar_150 nombre
        text descripcion
        varchar_60 presentacion
        int contenido_ml
        decimal_5_2 grado_alcohol
        int stock
        int stock_minimo
        int stock_maximo
        decimal_12_2 costo
        decimal_12_2 precio
        varchar_255 imagen
        tinyint_1 estado
        timestamp fecha_creacion
    }

    lotes {
        int id PK
        int producto_id FK
        int proveedor_id FK
        varchar_80 codigo
        date fecha_ingreso
        date fecha_vencimiento
        int cantidad_inicial
        int cantidad_disponible
    }

    movimientos_inventario {
        int id PK
        int producto_id FK
        int usuario_id FK
        enum_tipo tipo
        int cantidad
        int stock_anterior
        int stock_nuevo
        int lote_id FK
        varchar_80 referencia
        varchar_200 motivo
        timestamp creado_en
    }

    compras {
        int id PK
        varchar_80 numero_factura
        int proveedor_id FK
        int usuario_id FK
        date fecha_compra
        decimal_12_2 subtotal
        decimal_12_2 impuestos
        decimal_12_2 total
        enum_estado estado
        varchar_500 observacion
        timestamp creado_en
    }

    detalle_compra {
        int id PK
        int compra_id FK
        int producto_id FK
        int lote_id FK
        int cantidad
        decimal_12_2 costo_unitario
    }

    pedidos {
        int id PK
        varchar_30 numero UK
        int usuario_id FK
        timestamp creado_en
        decimal_12_2 subtotal
        decimal_12_2 descuento
        decimal_12_2 total
        enum_estado estado
        varchar_30 metodo_pago
        varchar_30 metodo_entrega
        varchar_255 direccion_entrega
        varchar_500 notas
        char_36 clave_idempotencia UK
        varchar_20 estado_pago
        varchar_100 referencia_pago
        char_3 moneda
    }

    detalle_pedido {
        int id PK
        int pedido_id FK
        int producto_id FK
        int cantidad
        decimal_12_2 precio_unitario
        varchar_150 nombre_producto
    }

    historial_pedido {
        bigint id PK
        int pedido_id FK
        int usuario_id FK
        varchar_40 estado_anterior
        varchar_40 estado_nuevo
        varchar_30 metodo_entrega_anterior
        varchar_30 metodo_entrega_nuevo
        varchar_255 detalle_entrega
        timestamp creado_en
    }

    ventas {
        bigint id PK
        decimal_12_2 total
        timestamp creado_en
        decimal_12_2 subtotal
        decimal_12_2 descuento
        enum_estado estado
        varchar_30 metodo_pago
        varchar_500 notas
        int usuario_id FK
        int cliente_id FK
        int pedido_id FK
    }

    detalle_venta {
        int id PK
        bigint venta_id FK
        int producto_id FK
        int cantidad
        decimal_12_2 precio_unitario
    }

    paypal_checkout_intents {
        varchar_64 paypal_order_id PK
        int usuario_id FK
        char_36 clave_idempotencia UK
        decimal_12_2 amount_cop
        decimal_12_2 amount_usd
        json items_json
        varchar_255 direccion_entrega
        varchar_500 notas
        enum_estado estado
        varchar_64 capture_id
        timestamp creado_en
        timestamp actualizado_en
    }

    notificaciones {
        int id PK
        int usuario_id FK
        varchar_50 tipo
        varchar_150 titulo
        text mensaje
        int pedido_id FK
        tinyint_1 leida
        timestamp creado_en
        timestamp leida_en
    }
```

---

## 2. Esquema Relacional Formal (Notación de Álgebra Relacional / Codd)

Las claves primarias se indican con **`[PK]`**, las foráneas con **`[FK -> Tabla.columna]`** y los campos con restricción de unicidad con **`[UK]`**:

1. **`roles`** (
   **id** `[PK]`, nombre `[UK]`, descripcion, estado, fecha_creacion
)
2. **`usuarios`** (
   **id** `[PK]`, *rol_id* `[FK -> roles.id]`, nombre, apellido, correo `[UK]`, password_hash, telefono, usuario `[UK]`, password, foto, ultimo_login, estado, fecha_creacion
)
3. **`clientes`** (
   **id** `[PK]`, nombre, identificacion `[UK]`, telefono, correo, direccion, fecha_nacimiento, puntos, estado, fecha_creacion
)
4. **`categorias`** (
   **id** `[PK]`, nombre `[UK]`, descripcion, estado, fecha_creacion
)
5. **`marcas`** (
   **id** `[PK]`, nombre `[UK]`, descripcion, estado, fecha_creacion
)
6. **`proveedores`** (
   **id** `[PK]`, empresa, nit, contacto, telefono, correo, direccion, ciudad, pais, estado, fecha_creacion
)
7. **`productos`** (
   **id** `[PK]`, *categoria_id* `[FK -> categorias.id]`, *marca_id* `[FK -> marcas.id]`, *proveedor_id* `[FK -> proveedores.id]`, codigo `[UK]`, codigo_barras, nombre, descripcion, presentacion, contenido_ml, grado_alcohol, stock, stock_minimo, stock_maximo, costo, precio, imagen, estado, fecha_creacion
)
8. **`lotes`** (
   **id** `[PK]`, *producto_id* `[FK -> productos.id]`, *proveedor_id* `[FK -> proveedores.id]`, codigo, fecha_ingreso, fecha_vencimiento, cantidad_inicial, cantidad_disponible
)
9. **`movimientos_inventario`** (
   **id** `[PK]`, *producto_id* `[FK -> productos.id]`, *usuario_id* `[FK -> usuarios.id]`, tipo, cantidad, stock_anterior, stock_nuevo, *lote_id* `[FK -> lotes.id]`, referencia, motivo, creado_en
)
10. **`compras`** (
    **id** `[PK]`, numero_factura, *proveedor_id* `[FK -> proveedores.id]`, *usuario_id* `[FK -> usuarios.id]`, fecha_compra, subtotal, impuestos, total, estado, observacion, creado_en
)
11. **`detalle_compra`** (
    **id** `[PK]`, *compra_id* `[FK -> compras.id ON DELETE CASCADE]`, *producto_id* `[FK -> productos.id]`, *lote_id* `[FK -> lotes.id ON DELETE SET NULL]`, cantidad, costo_unitario
)
12. **`pedidos`** (
    **id** `[PK]`, numero `[UK]`, *usuario_id* `[FK -> usuarios.id]`, creado_en, subtotal, descuento, total, estado, metodo_pago, metodo_entrega, direccion_entrega, notas, clave_idempotencia `[UK]`, estado_pago, referencia_pago, moneda
)
13. **`detalle_pedido`** (
    **id** `[PK]`, *pedido_id* `[FK -> pedidos.id ON DELETE CASCADE]`, *producto_id* `[FK -> productos.id]`, cantidad, precio_unitario, nombre_producto
)
14. **`historial_pedido`** (
    **id** `[PK]`, *pedido_id* `[FK -> pedidos.id ON DELETE CASCADE]`, *usuario_id* `[FK -> usuarios.id ON DELETE SET NULL]`, estado_anterior, estado_nuevo, metodo_entrega_anterior, metodo_entrega_nuevo, detalle_entrega, creado_en
)
15. **`ventas`** (
    **id** `[PK]`, total, creado_en, subtotal, descuento, estado, metodo_pago, notas, *usuario_id* `[FK -> usuarios.id]`, *cliente_id* `[FK -> clientes.id]`, *pedido_id* `[FK -> pedidos.id]`
)
16. **`detalle_venta`** (
    **id** `[PK]`, *venta_id* `[FK -> ventas.id]`, *producto_id* `[FK -> productos.id]`, cantidad, precio_unitario
)
17. **`paypal_checkout_intents`** (
    **paypal_order_id** `[PK]`, *usuario_id* `[FK -> usuarios.id]`, clave_idempotencia `[UK (usuario_id, clave_idempotencia)]`, amount_cop, amount_usd, items_json, direccion_entrega, notas, estado, capture_id, creado_en, actualizado_en
)
18. **`notificaciones`** (
    **id** `[PK]`, *usuario_id* `[FK -> usuarios.id ON DELETE CASCADE]`, tipo, titulo, mensaje, *pedido_id* `[FK -> pedidos.id ON DELETE SET NULL]`, leida, creado_en, leida_en
)

---

## 3. Matriz de Integridad Referencial y Restricciones Físicas

| Tabla Origen (Foreign Key) | Columna FK | Tabla Destino | Columna PK | Acción ON DELETE | Acción ON UPDATE |
|---|---|---|---|---|---|
| `usuarios` | `rol_id` | `roles` | `id` | RESTRICT | CASCADE |
| `productos` | `categoria_id` | `categorias` | `id` | RESTRICT | CASCADE |
| `productos` | `marca_id` | `marcas` | `id` | RESTRICT | CASCADE |
| `productos` | `proveedor_id` | `proveedores` | `id` | RESTRICT | CASCADE |
| `compras` | `proveedor_id` | `proveedores` | `id` | RESTRICT | NO ACTION |
| `compras` | `usuario_id` | `usuarios` | `id` | RESTRICT | NO ACTION |
| `detalle_compra` | `compra_id` | `compras` | `id` | **CASCADE** | NO ACTION |
| `detalle_compra` | `producto_id` | `productos` | `id` | RESTRICT | NO ACTION |
| `detalle_compra` | `lote_id` | `lotes` | `id` | **SET NULL** | NO ACTION |
| `lotes` | `producto_id` | `productos` | `id` | RESTRICT | NO ACTION |
| `lotes` | `proveedor_id` | `proveedores` | `id` | **SET NULL** | NO ACTION |
| `detalle_pedido` | `pedido_id` | `pedidos` | `id` | **CASCADE** | NO ACTION |
| `detalle_pedido` | `producto_id` | `productos` | `id` | RESTRICT | NO ACTION |
| `historial_pedido` | `pedido_id` | `pedidos` | `id` | **CASCADE** | NO ACTION |
| `historial_pedido` | `usuario_id` | `usuarios` | `id` | **SET NULL** | NO ACTION |
| `notificaciones` | `usuario_id` | `usuarios` | `id` | **CASCADE** | NO ACTION |
| `notificaciones` | `pedido_id` | `pedidos` | `id` | **SET NULL** | NO ACTION |
| `ventas` | `pedido_id` | `pedidos` | `id` | SET NULL / NO ACTION | NO ACTION |
| `ventas` | `usuario_id` | `usuarios` | `id` | SET NULL / NO ACTION | NO ACTION |
| `detalle_venta` | `venta_id` | `ventas` | `id` | NO ACTION | NO ACTION |
| `detalle_venta` | `producto_id` | `productos` | `id` | RESTRICT | NO ACTION |

---

## 4. Índices de Rendimiento y Llaves Únicas

1. **`pedidos.uq_pedidos_idempotencia`** (`clave_idempotencia`): Previene pagos dobles ante reintentos de red del cliente.
2. **`paypal_checkout_intents.uq_paypal_intent_usuario_idempotencia`** (`usuario_id`, `clave_idempotencia`): Asocia unívocamente la orden de PayPal al carrito del usuario.
3. **`notificaciones.idx_notificaciones_usuario_leida`** (`usuario_id`, `leida`, `creado_en`): Optimiza la consulta en tiempo real de la campana de notificaciones.
4. **`historial_pedido.idx_historial_pedido_fecha`** (`pedido_id`, `creado_en`): Acelera la reconstrucción de la línea de tiempo del pedido en el panel administrativo.
5. **`lotes.idx_lotes_vencimiento`** (`fecha_vencimiento`): Permite priorizar la salida de inventario por fecha de expiración próxima.
