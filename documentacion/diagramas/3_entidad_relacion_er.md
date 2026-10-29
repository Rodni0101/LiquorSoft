# Diagrama Entidad-Relación (E-R) - LiquorSoft

Este documento presenta el **Diagrama Entidad-Relación (E-R)** conceptual y lógico del sistema **LiquorSoft**. Se deriva exclusivamente de las tablas, claves foráneas y flujos de datos reales implementados en `backend/database/schema.sql` y las migraciones de `backend/database/migrations/`.

---

## 1. Definición de Entidades del Dominio

| Entidad | Tipo | Propósito en el Negocio |
|---|---|---|
| **ROL** | Fuerte | Define los perfiles y permisos de acceso (Administrador, Trabajador, Cliente, etc.). |
| **USUARIO** | Fuerte | Almacena las cuentas autenticables para clientes de la tienda y personal operativo. |
| **CLIENTE** | Fuerte | Registro de terceros/compradores para ventas de mostrador y fidelización por puntos. |
| **CATEGORIA** | Fuerte | Clasificación de licores (Licores, Vinos, Cervezas, Mixers). |
| **MARCA** | Fuerte | Identificación de la casa comercial fabricante de los licores. |
| **PROVEEDOR** | Fuerte | Empresas distribuidoras a quienes se compran los productos y lotes. |
| **PRODUCTO** | Fuerte | Catálogo de bebidas alcohólicas con stock, precios, contenido y graduación. |
| **LOTE** | Débil | Trazabilidad de existencias por fecha de ingreso y fecha de vencimiento. |
| **COMPRA** | Fuerte | Registro de adquisición de mercancía ante un proveedor. |
| **DETALLE_COMPRA** | Débil | Renglones de productos, cantidades y costos asociados a una compra. |
| **MOVIMIENTO_INVENTARIO**| Débil | Kardex de movimientos de stock (ENTRADA, SALIDA, AJUSTE) con justificación. |
| **PEDIDO** | Fuerte | Órdenes de compra generadas por los usuarios desde la tienda virtual. |
| **DETALLE_PEDIDO** | Débil | Ítems de productos y precios unitarios históricos vinculados a un pedido. |
| **HISTORIAL_PEDIDO** | Débil | Registro cronológico y auditoría de transiciones de estado y entrega de un pedido. |
| **VENTA** | Fuerte | Facturación y liquidación contable de órdenes completadas o ventas directas. |
| **DETALLE_VENTA** | Débil | Líneas de producto y valor cobrado en una venta formal. |
| **INTENTO_PAYPAL** | Débil | Control de idempotencia y captura de órdenes en la pasarela PayPal Sandbox. |
| **NOTIFICACION** | Débil | Alertas enviadas a usuarios y administradores sobre estados de compra. |

---

## 2. Diagrama Entidad-Relación (Mermaid con Notación Crow's Foot)

```mermaid
erDiagram
    %% Entidades y Relaciones Principales

    %% Seguridad y Cuentas
    ROL ||--o{ USUARIO : "asigna perfil a (1:N)"

    %% Catálogo y Proveedores
    CATEGORIA ||--o{ PRODUCTO : "clasifica a (1:N)"
    MARCA ||--o{ PRODUCTO : "identifica a (1:N)"
    PROVEEDOR ||--o{ PRODUCTO : "suministra (1:N)"

    %% Compras y Lotes
    PROVEEDOR ||--o{ COMPRA : "expide factura a (1:N)"
    USUARIO ||--o{ COMPRA : "recibe / registra (1:N)"
    COMPRA ||--|{ DETALLE_COMPRA : "contiene (1:N)"
    PRODUCTO ||--o{ DETALLE_COMPRA : "es renglon en (1:N)"
    PRODUCTO ||--o{ LOTE : "controla existencias en (1:N)"
    PROVEEDOR ||--o{ LOTE : "despacha (1:N)"
    LOTE ||--o{ DETALLE_COMPRA : "respalda ingreso de (0..1:N)"

    %% Inventario y Kardex
    PRODUCTO ||--o{ MOVIMIENTO_INVENTARIO : "registra variacion de (1:N)"
    USUARIO ||--o{ MOVIMIENTO_INVENTARIO : "autoriza movimiento (0..1:N)"
    LOTE ||--o{ MOVIMIENTO_INVENTARIO : "afecta stock de (0..1:N)"

    %% Pedidos y Cumplimiento
    USUARIO ||--o{ PEDIDO : "realiza (1:N)"
    PEDIDO ||--|{ DETALLE_PEDIDO : "incluye (1:N)"
    PRODUCTO ||--o{ DETALLE_PEDIDO : "se vende en (1:N)"
    PEDIDO ||--o{ HISTORIAL_PEDIDO : "audita cambios en (1:N)"
    USUARIO ||--o{ HISTORIAL_PEDIDO : "ejecuta cambio en (0..1:N)"

    %% Ventas y Facturación
    USUARIO ||--o{ VENTA : "atiende / factura a (0..1:N)"
    CLIENTE ||--o{ VENTA : "compra en mostrador (0..1:N)"
    PEDIDO ||--o| VENTA : "origina factura de (0..1:1)"
    VENTA ||--|{ DETALLE_VENTA : "desglosa renglones en (1:N)"
    PRODUCTO ||--o{ DETALLE_VENTA : "es item de (1:N)"

    %% Pasarela y Notificaciones
    USUARIO ||--o{ INTENTO_PAYPAL : "inicia pago (1:N)"
    USUARIO ||--o{ NOTIFICACION : "recibe avisos de (1:N)"
    PEDIDO ||--o{ NOTIFICACION : "genera alertas de (0..1:N)"

    %% Atributos Principales de Entidades
    ROL {
        int id PK
        string nombre UK
        string descripcion
        boolean estado
    }

    USUARIO {
        int id PK
        int rol_id FK
        string nombre
        string apellido
        string correo UK
        string usuario UK
        string password_hash
        boolean estado
    }

    CLIENTE {
        int id PK
        string nombre
        string identificacion UK
        string telefono
        string correo
        int puntos
        boolean estado
    }

    CATEGORIA {
        int id PK
        string nombre UK
        string descripcion
        boolean estado
    }

    MARCA {
        int id PK
        string nombre UK
        string descripcion
        boolean estado
    }

    PROVEEDOR {
        int id PK
        string empresa
        string nit
        string contacto
        string telefono
        string correo
        boolean estado
    }

    PRODUCTO {
        int id PK
        int categoria_id FK
        int marca_id FK
        int proveedor_id FK
        string codigo UK
        string nombre
        decimal costo
        decimal precio
        int stock
        int stock_minimo
        boolean estado
    }

    LOTE {
        int id PK
        int producto_id FK
        int proveedor_id FK
        string codigo
        date fecha_ingreso
        date fecha_vencimiento
        int cantidad_inicial
        int cantidad_disponible
    }

    COMPRA {
        int id PK
        int proveedor_id FK
        int usuario_id FK
        string numero_factura
        date fecha_compra
        decimal subtotal
        decimal total
        string estado
    }

    DETALLE_COMPRA {
        int id PK
        int compra_id FK
        int producto_id FK
        int lote_id FK
        int cantidad
        decimal costo_unitario
    }

    MOVIMIENTO_INVENTARIO {
        int id PK
        int producto_id FK
        int usuario_id FK
        string tipo
        int cantidad
        int stock_anterior
        int stock_nuevo
        string referencia
        string motivo
    }

    PEDIDO {
        int id PK
        int usuario_id FK
        string numero UK
        decimal total
        string estado
        string metodo_pago
        string metodo_entrega
        string direccion_entrega
        string clave_idempotencia UK
        string estado_pago
    }

    DETALLE_PEDIDO {
        int id PK
        int pedido_id FK
        int producto_id FK
        int cantidad
        decimal precio_unitario
        string nombre_producto
    }

    HISTORIAL_PEDIDO {
        bigint id PK
        int pedido_id FK
        int usuario_id FK
        string estado_anterior
        string estado_nuevo
        string metodo_entrega_nuevo
        string detalle_entrega
        timestamp creado_en
    }

    VENTA {
        bigint id PK
        int usuario_id FK
        int cliente_id FK
        int pedido_id FK
        decimal total
        string estado
        string metodo_pago
    }

    DETALLE_VENTA {
        int id PK
        bigint venta_id FK
        int producto_id FK
        int cantidad
        decimal precio_unitario
    }

    INTENTO_PAYPAL {
        string paypal_order_id PK
        int usuario_id FK
        string clave_idempotencia UK
        decimal amount_cop
        decimal amount_usd
        string estado
        string capture_id
    }

    NOTIFICACION {
        int id PK
        int usuario_id FK
        int pedido_id FK
        string tipo
        string titulo
        string mensaje
        boolean leida
    }
```

---

## 3. Diagrama E-R en PlantUML

```plantuml
@startuml
skinparam linetype ortho
skinparam roundcorner 5

entity "ROL" as ROL {
  * id : int <<PK>>
  --
  nombre : varchar(50) <<UK>>
  descripcion : varchar(255)
  estado : tinyint(1)
}

entity "USUARIO" as USUARIO {
  * id : int <<PK>>
  --
  * rol_id : int <<FK>>
  nombre : varchar(100)
  apellido : varchar(100)
  correo : varchar(120) <<UK>>
  usuario : varchar(50) <<UK>>
  password_hash : varchar(255)
  estado : tinyint(1)
}

entity "CATEGORIA" as CATEGORIA {
  * id : int <<PK>>
  --
  nombre : varchar(80) <<UK>>
  descripcion : text
  estado : tinyint(1)
}

entity "MARCA" as MARCA {
  * id : int <<PK>>
  --
  nombre : varchar(80) <<UK>>
  descripcion : text
  estado : tinyint(1)
}

entity "PROVEEDOR" as PROVEEDOR {
  * id : int <<PK>>
  --
  empresa : varchar(120)
  nit : varchar(40)
  telefono : varchar(20)
  correo : varchar(120)
  estado : tinyint(1)
}

entity "PRODUCTO" as PRODUCTO {
  * id : int <<PK>>
  --
  * categoria_id : int <<FK>>
  * marca_id : int <<FK>>
  * proveedor_id : int <<FK>>
  codigo : varchar(40) <<UK>>
  nombre : varchar(150)
  costo : decimal(12,2)
  precio : decimal(12,2)
  stock : int
  stock_minimo : int
  estado : tinyint(1)
}

entity "LOTE" as LOTE {
  * id : int <<PK>>
  --
  * producto_id : int <<FK>>
  proveedor_id : int <<FK>>
  codigo : varchar(80)
  fecha_ingreso : date
  fecha_vencimiento : date
  cantidad_inicial : int
  cantidad_disponible : int
}

entity "COMPRA" as COMPRA {
  * id : int <<PK>>
  --
  * proveedor_id : int <<FK>>
  * usuario_id : int <<FK>>
  numero_factura : varchar(80)
  fecha_compra : date
  total : decimal(12,2)
  estado : enum
}

entity "DETALLE_COMPRA" as DETALLE_COMPRA {
  * id : int <<PK>>
  --
  * compra_id : int <<FK>>
  * producto_id : int <<FK>>
  lote_id : int <<FK>>
  cantidad : int
  costo_unitario : decimal(12,2)
}

entity "MOVIMIENTO_INVENTARIO" as MOVIMIENTO {
  * id : int <<PK>>
  --
  * producto_id : int <<FK>>
  usuario_id : int <<FK>>
  tipo : enum('ENTRADA','SALIDA','AJUSTE')
  cantidad : int
  stock_anterior : int
  stock_nuevo : int
  motivo : varchar(200)
}

entity "PEDIDO" as PEDIDO {
  * id : int <<PK>>
  --
  * usuario_id : int <<FK>>
  numero : varchar(30) <<UK>>
  total : decimal(12,2)
  estado : enum
  metodo_pago : varchar(30)
  metodo_entrega : varchar(30)
  clave_idempotencia : char(36) <<UK>>
  estado_pago : varchar(20)
}

entity "DETALLE_PEDIDO" as DETALLE_PEDIDO {
  * id : int <<PK>>
  --
  * pedido_id : int <<FK>>
  * producto_id : int <<FK>>
  cantidad : int
  precio_unitario : decimal(12,2)
  nombre_producto : varchar(150)
}

entity "HISTORIAL_PEDIDO" as HISTORIAL_PEDIDO {
  * id : bigint <<PK>>
  --
  * pedido_id : int <<FK>>
  usuario_id : int <<FK>>
  estado_anterior : varchar(40)
  estado_nuevo : varchar(40)
  metodo_entrega_nuevo : varchar(30)
  detalle_entrega : varchar(255)
}

entity "VENTA" as VENTA {
  * id : bigint <<PK>>
  --
  usuario_id : int <<FK>>
  cliente_id : int <<FK>>
  pedido_id : int <<FK>>
  total : decimal(12,2)
  estado : enum
  metodo_pago : varchar(30)
}

entity "DETALLE_VENTA" as DETALLE_VENTA {
  * id : int <<PK>>
  --
  * venta_id : bigint <<FK>>
  * producto_id : int <<FK>>
  cantidad : int
  precio_unitario : decimal(12,2)
}

entity "NOTIFICACION" as NOTIFICACION {
  * id : int <<PK>>
  --
  * usuario_id : int <<FK>>
  pedido_id : int <<FK>>
  tipo : varchar(50)
  titulo : varchar(150)
  mensaje : text
  leida : tinyint(1)
}

' Relaciones
ROL ||--o{ USUARIO
CATEGORIA ||--o{ PRODUCTO
MARCA ||--o{ PRODUCTO
PROVEEDOR ||--o{ PRODUCTO
PROVEEDOR ||--o{ COMPRA
USUARIO ||--o{ COMPRA
COMPRA ||--|{ DETALLE_COMPRA
PRODUCTO ||--o{ DETALLE_COMPRA
PRODUCTO ||--o{ LOTE
LOTE ||--o{ DETALLE_COMPRA
PRODUCTO ||--o{ MOVIMIENTO
USUARIO ||--o{ MOVIMIENTO
USUARIO ||--o{ PEDIDO
PEDIDO ||--|{ DETALLE_PEDIDO
PRODUCTO ||--o{ DETALLE_PEDIDO
PEDIDO ||--o{ HISTORIAL_PEDIDO
USUARIO ||--o{ HISTORIAL_PEDIDO
PEDIDO ||--o| VENTA
USUARIO ||--o{ VENTA
VENTA ||--|{ DETALLE_VENTA
PRODUCTO ||--o{ DETALLE_VENTA
USUARIO ||--o{ NOTIFICACION
PEDIDO ||--o{ NOTIFICACION
@enduml
```

---

## 4. Cardinalidades y Reglas de Negocio E-R

1. **Un Usuario pertenece exactamente a un Rol** (`ROL 1:N USUARIO`). Los usuarios que compran en la tienda se auto-registran con el rol `Cliente` (id 6). Los usuarios operativos (`Administrador`, `Trabajador`, etc.) son gestionados por el administrador.
2. **Un Producto pertenece a una Categoría, una Marca y un Proveedor** (`1:N`). Si una categoría o proveedor se elimina, se bloquea la operación (`ON DELETE RESTRICT`) para no dejar productos huérfanos.
3. **Control estricto de Lotes y Vencimientos** (`PRODUCTO 1:N LOTE 0..1:N DETALLE_COMPRA`): Al ingresar compras, se generan lotes con fecha de vencimiento y existencias disponibles para garantizar rotación por fecha.
4. **Desacoplamiento entre Pedido y Venta** (`PEDIDO 0..1:1 VENTA`): Un pedido pasa por estados preparatorios (`PENDIENTE`, `CONFIRMADO`, `PREPARANDO`). Solo cuando el pago es aprobado/capturado se genera el registro contable en `VENTA`, se descuenta el stock en `PRODUCTO` y se emite el movimiento de Kardex en `MOVIMIENTO_INVENTARIO`.
5. **Auditoría e Historial de Cumplimiento** (`PEDIDO 1:N HISTORIAL_PEDIDO`): Cada avance de estado o acuerdo en la forma de entrega (`POR_COORDINAR` -> `DOMICILIO`/`RECOGIDA`) registra el actor, la fecha exacta y las notas de entrega acordadas.
