# Diagrama de Casos de Uso UML - LiquorSoft

Este documento describe y formaliza el **Diagrama de Casos de Uso UML** del sistema **LiquorSoft**, obtenido directamente del análisis del código fuente (rutas de Angular `app.routes.ts`, controladores de la API PHP en `backend/api/`, lógica de permisos en `AuthService` y `permission.guard.ts`, y roles del esquema de base de datos).

---

## 1. Actores del Sistema

A partir del modelo de roles y las rutas operativas reales del proyecto:

1. **Cliente / Comprador (E-commerce)**:
   - Usuario registrado con rol `Cliente` que navega el catálogo, compra licores, realiza pagos en línea y hace seguimiento a sus pedidos y notificaciones.
2. **Administrador**:
   - Usuario con rol `Administrador` que tiene control total sobre el catálogo de productos, inventario, compras a proveedores, ventas, asignación de pedidos, usuarios y diagnósticos técnicos.
3. **Trabajador / Operador de Pedidos**:
   - Usuario con rol `Trabajador` que interviene en la logística interna de pedidos: avanza los estados operativos (`PREPARANDO`, `LISTO_PARA_COORDINAR`, `COMPLETADO`) y coordina la forma de entrega (`POR_COORDINAR`, `DOMICILIO`, `RECOGIDA`).
4. **Pasarela de Pagos (PayPal Sandbox / Simulador Nequi)**:
   - Sistema externo que procesa intenciones de cobro, aprueba transacciones y devuelve códigos de captura e idempotencia.
5. **Servicio de Inteligencia Artificial (OpenAI / Asistente Local)**:
   - Servicio externo (o motor de reglas local de respaldo) para la asistencia conversacional y recomendación de productos en `chat.php`.

---

## 2. Diagrama de Casos de Uso (Formato Mermaid)

```mermaid
flowchart LR
    %% Actores
    actorCliente(["👤 Cliente"])
    actorAdmin(["👤 Administrador"])
    actorTrabajador(["👤 Trabajador / Operador"])
    actorPayPal(["💳 Pasarela PayPal / Simulador"])
    actorAI(["🤖 Servicio Chatbot / OpenAI"])

    %% Subgraph Módulo Público y Tienda
    subgraph Tienda ["Tienda y Compras (Frontend Cliente)"]
        UC01(["Explorar Catálogo y Filtrar Productos"])
        UC02(["Consultar Ficha Técnica de Producto"])
        UC03(["Gestionar Carrito de Compras"])
        UC04(["Iniciar Sesión / Registrarse"])
        UC05(["Realizar Checkout / Compra"])
        UC06(["Pagar con PayPal Sandbox"])
        UC07(["Pagar con Simulador Nequi"])
        UC08(["Consultar Historial de Pedidos"])
        UC09(["Ver / Descargar Factura Digital"])
        UC10(["Recibir Notificaciones de Pedido"])
        UC11(["Consultar Asistente Virtual / Chat"])
    end

    %% Subgraph Módulo Operativo y Logística
    subgraph Logistica ["Cumplimiento y Logística de Pedidos"]
        UC12(["Listar Pedidos en Curso"])
        UC13(["Avanzar Estado Operativo del Pedido"])
        UC14(["Coordinar Método y Punto de Entrega"])
        UC15(["Auditar Historial de Estados"])
    end

    %% Subgraph Módulo Administrativo
    subgraph Admin ["Panel Administrativo (LiquorSoft Admin)"]
        UC16(["Consultar Dashboard y Métricas"])
        UC17(["Gestionar Catálogo de Productos (CRUD)"])
        UC18(["Gestionar Categorías"])
        UC19(["Gestionar Proveedores"])
        UC20(["Registrar Compras a Proveedores"])
        UC21(["Ingresar Lotes con Vencimiento"])
        UC22(["Ajustar Stock de Inventario"])
        UC23(["Consultar Movimientos de Kardex"])
        UC24(["Consultar Registro de Ventas"])
        UC25(["Gestionar Usuarios y Roles"])
        UC26(["Ejecutar Diagnóstico PayPal"])
    end

    %% Relaciones Cliente
    actorCliente --> UC01
    actorCliente --> UC02
    actorCliente --> UC03
    actorCliente --> UC04
    actorCliente --> UC05
    actorCliente --> UC08
    actorCliente --> UC09
    actorCliente --> UC10
    actorCliente --> UC11

    %% Inclusions / Extensions Checkout
    UC05 -.->|<<include>>| UC04
    UC05 -.->|<<include>>| UC03
    UC06 -.->|<<extend>>| UC05
    UC07 -.->|<<extend>>| UC05
    UC06 --- actorPayPal
    UC07 --- actorPayPal
    UC11 --- actorAI

    %% Relaciones Trabajador
    actorTrabajador --> UC12
    actorTrabajador --> UC13
    actorTrabajador --> UC14
    actorTrabajador --> UC15

    %% Relaciones Administrador
    actorAdmin --> UC12
    actorAdmin --> UC13
    actorAdmin --> UC14
    actorAdmin --> UC15
    actorAdmin --> UC16
    actorAdmin --> UC17
    actorAdmin --> UC18
    actorAdmin --> UC19
    actorAdmin --> UC20
    actorAdmin --> UC22
    actorAdmin --> UC23
    actorAdmin --> UC24
    actorAdmin --> UC25
    actorAdmin --> UC26

    %% Inclusiones Administrativas
    UC20 -.->|<<include>>| UC21
    UC20 -.->|<<include>>| UC23
    UC22 -.->|<<include>>| UC23
```

---

## 3. Diagrama de Casos de Uso (Formato PlantUML)

```plantuml
@startuml
left to right direction
skinparam packageStyle rectangle
skinparam actorStyle awesome

actor "Cliente" as Cliente
actor "Trabajador / Operador" as Trabajador
actor "Administrador" as Admin
actor "Pasarela PayPal / Simulador" as PayPal <<System>>
actor "Servicio IA / OpenAI" as AI <<System>>

rectangle "Sistema LiquorSoft" {
  package "Módulo de Tienda y E-commerce" {
    usecase "Explorar Catálogo y Filtrar" as UC_Catalogo
    usecase "Ver Detalle de Producto" as UC_Detalle
    usecase "Gestionar Carrito" as UC_Carrito
    usecase "Autenticarse / Registrarse" as UC_Auth
    usecase "Realizar Checkout" as UC_Checkout
    usecase "Pagar con PayPal Sandbox" as UC_Paypal
    usecase "Pagar con Simulador Nequi" as UC_Simulador
    usecase "Consultar Mis Pedidos" as UC_MisPedidos
    usecase "Descargar Comprobante / Factura" as UC_Factura
    usecase "Consultar Notificaciones" as UC_Notificaciones
    usecase "Interactuar con Chatbot Asistente" as UC_Chat
  }

  package "Módulo Operativo de Pedidos" {
    usecase "Listar Pedidos" as UC_ListarPedidos
    usecase "Avanzar Estado del Pedido\n(Confirmado -> Preparando -> Listo -> Completado)" as UC_AvanzarEstado
    usecase "Coordinar Método y Punto de Entrega\n(Por coordinar / Domicilio / Recogida)" as UC_CoordinarEntrega
    usecase "Consultar Historial de Cambios" as UC_Historial
  }

  package "Módulo Administrativo y Gestión" {
    usecase "Visualizar Dashboard de Métricas" as UC_Dashboard
    usecase "Gestionar Productos (CRUD)" as UC_CrudProductos
    usecase "Gestionar Categorías" as UC_Categorias
    usecase "Gestionar Proveedores" as UC_Proveedores
    usecase "Registrar Compra a Proveedor" as UC_Compras
    usecase "Registrar Lote y Vencimiento" as UC_Lotes
    usecase "Ajustar Stock de Inventario" as UC_AjustarStock
    usecase "Consultar Movimientos de Inventario" as UC_Movimientos
    usecase "Consultar Reporte de Ventas" as UC_Ventas
    usecase "Gestionar Usuarios y Roles" as UC_Usuarios
    usecase "Diagnóstico Técnico de PayPal" as UC_DiagPayPal
  }
}

' Relaciones de Cliente
Cliente --> UC_Catalogo
Cliente --> UC_Detalle
Cliente --> UC_Carrito
Cliente --> UC_Auth
Cliente --> UC_Checkout
Cliente --> UC_MisPedidos
Cliente --> UC_Factura
Cliente --> UC_Notificaciones
Cliente --> UC_Chat

UC_Checkout .> UC_Auth : <<include>>
UC_Checkout .> UC_Carrito : <<include>>
UC_Paypal .> UC_Checkout : <<extend>>
UC_Simulador .> UC_Checkout : <<extend>>

UC_Paypal -- PayPal
UC_Simulador -- PayPal
UC_Chat -- AI

' Relaciones de Trabajador
Trabajador --> UC_ListarPedidos
Trabajador --> UC_AvanzarEstado
Trabajador --> UC_CoordinarEntrega
Trabajador --> UC_Historial

' Relaciones de Administrador (Hereda permisos operativos)
Admin --|> Trabajador
Admin --> UC_Dashboard
Admin --> UC_CrudProductos
Admin --> UC_Categorias
Admin --> UC_Proveedores
Admin --> UC_Compras
Admin --> UC_AjustarStock
Admin --> UC_Movimientos
Admin --> UC_Ventas
Admin --> UC_Usuarios
Admin --> UC_DiagPayPal

UC_Compras .> UC_Lotes : <<include>>
UC_Compras .> UC_Movimientos : <<include>>
UC_AjustarStock .> UC_Movimientos : <<include>>
@enduml
```

---

## 4. Descripción Detallada de Casos de Uso Clave

| ID | Caso de Uso | Actor Principal | Precondición | Flujo Principal / Resultado |
|---|---|---|---|---|
| **UC-05** | Realizar Checkout | Cliente | Carrito con ítems, usuario autenticado, confirmación mayor de 18 años. | Valida stock con bloqueo transaccional (`FOR UPDATE`), calcula total en COP/USD, descuenta stock y registra la venta, pedido y notificación. |
| **UC-06** | Pagar con PayPal | Cliente / PayPal | Credenciales Sandbox activas y clave de idempotencia de 36 caracteres. | Crea orden en PayPal API v2, captura cobro con idempotencia y valida que el monto pagado coincida exactamente con el carrito. |
| **UC-13** | Avanzar Estado de Pedido | Trabajador / Admin | Pedido con pago aprobado (`APROBADO` o `CAPTURADO`). | Transiciona: `CONFIRMADO` → `PREPARANDO` → `LISTO_PARA_COORDINAR` → `COMPLETADO`. Genera registro en `historial_pedido` y notificación al cliente. |
| **UC-14** | Coordinar Entrega | Trabajador / Admin | Pedido en estado `LISTO_PARA_COORDINAR`. | Define si la entrega es `DOMICILIO` o `RECOGIDA`, exigiendo la dirección o punto acordado en `fulfillmentDetails`. |
| **UC-20** | Registrar Compra | Administrador | Proveedor activo registrado. | Guarda encabezado en `compras`, renglones en `detalle_compra`, crea registro en `lotes`, suma stock en `productos` y crea `movimientos_inventario` tipo `ENTRADA`. |
| **UC-22** | Ajustar Stock | Administrador | Producto existente y motivo entre 3 y 200 caracteres. | Aplica ajuste en `productos.stock` y registra la justificación en `movimientos_inventario` (`ENTRADA` o `SALIDA`). |
