# Diagrama de Arquitectura y Componentes - LiquorSoft

Este documento describe la **Arquitectura de Software** y el **Diagrama de Componentes** de **LiquorSoft**, reflejando la distribución en capas (Frontend SPA, Backend API RESTful en PHP, Base de Datos MySQL/MariaDB y Servicios Externos).

---

## 1. Visión General de la Arquitectura

LiquorSoft implementa un patrón desacoplado **Cliente-Servidor (SPA + API REST)**:

1. **Capa de Presentación (Frontend SPA)**:
   - Construida con **Angular 22** (arquitectura standalone, TypeScript, Angular Signals para reactividad, y enrutamiento con protección de guardias).
   - Empaquetada como archivos estáticos (`dist/`) que se sirven a través del servidor web.
2. **Capa de Aplicación y Servicios (Backend API)**:
   - Desarrollada en **PHP 8** con tipado estricto (`declare(strict_types=1)`).
   - Expone endpoints REST en formato JSON.
   - Gestiona autenticación basada en sesiones seguras del servidor (`PHPSESSID`), tokens CSRF (`X-CSRF-Token`) y control de acceso basado en roles (RBAC).
3. **Capa de Persistencia (Base de Datos Relacional)**:
   - Motor **MySQL 8.0 / MariaDB 10.4+** con motor de almacenamiento **InnoDB**.
   - Garantiza consistencia transaccional (ACID), claves foráneas con restricciones de integridad y aislamiento con bloqueo pesimista (`FOR UPDATE`).
4. **Capa de Integraciones Externas**:
   - **PayPal Sandbox API**: Autenticación OAuth 2.0 y gestión de órdenes v2.
   - **OpenAI API**: Asistente conversacional con mecanismo de contingencia (*fallback*) a motor de reglas local.

---

## 2. Diagrama de Componentes del Sistema (Mermaid)

```mermaid
flowchart TB
    %% ==========================================
    %% CLIENTE / NAVEGADOR WEB
    %% ==========================================
    subgraph Cliente ["Capa Cliente (Navegador Web / Angular SPA)"]
        subgraph VistasPublicas ["Vistas de Tienda / Cliente"]
            V_Catalogo["Catálogo y Detalle\n(Catalogo, Producto)"]
            V_Carrito["Carrito y Checkout\n(Carrito, Checkout)"]
            V_Pedidos["Seguimiento de Pedidos\n(Pedidos)"]
            V_Auth["Autenticación\n(Login, Register)"]
            V_Chat["Widget de Asistencia\n(Chatbot)"]
        end

        subgraph VistasAdmin ["Vistas de Administración"]
            V_AdminShell["Admin Shell (Layout)"]
            V_Dashboard["Dashboard / KPIs"]
            V_CrudProd["Gestión Productos / Categorías"]
            V_Inventario["Inventario y Ajustes"]
            V_Compras["Compras y Lotes"]
            V_Ventas["Reporte de Ventas"]
            V_Usuarios["Usuarios y Roles"]
        end

        subgraph ServiciosFront ["Servicios Núcleo Frontend"]
            S_Auth["AuthService\n(Signals, Roles, Sesión)"]
            S_Cart["CartService\n(Signals, LocalStorage)"]
            S_Purchase["PurchaseService\n(Checkout, PayPal Intent)"]
            S_Notif["UserNotificationsService\n(Polling, Badge)"]
            S_Theme["ThemeService\n(Dark/Light)"]
        end

        subgraph InfraFront ["Infraestructura Frontend"]
            Guards["Guards & Interceptors\n(authGuard, permissionGuard, CredentialsInterceptor)"]
            HttpCliente["Angular HttpClient"]
        end
    end

    %% ==========================================
    %% SERVIDOR WEB Y API BACKEND
    %% ==========================================
    subgraph ServidorAPI ["Capa Servidor Web / Backend (PHP 8 + Apache/Nginx)"]
        subgraph Gateways ["Puntos de Entrada API"]
            API_Auth["/api/auth/*\n(Login, Register, Logout, Me)"]
            API_Store["/api/*\n(products, categories, public-summary)"]
            API_Orders["/api/*\n(purchase, orders, invoice, notifications)"]
            API_PayPal["/api/paypal/create-order.php"]
            API_Chat["/api/chat.php"]
            API_Admin["/api/admin/*\n(orders, inventory, purchases, products, sales, users)"]
        end

        subgraph NucleoSeguridad ["Módulo de Seguridad y Sesión"]
            Mid_Session["Session Manager\n(Cookies HttpOnly, Strict)"]
            Mid_CSRF["CSRF Protection\n(Double-Submit Cookie & Header)"]
            Mid_RBAC["Role-Based Access Control\n(requireRole, currentUser)"]
        end

        subgraph ServiciosNegocio ["Procesadores y Lógica de Negocio"]
            Biz_Checkout["Checkout & Transacciones\n(Bloqueo FOR UPDATE, Validación Stock)"]
            Biz_Fulfillment["Gestión de Cumplimiento\n(Historial, Método de Entrega)"]
            Biz_Inventory["Gestor de Inventario & Kardex\n(Lotes, Entradas, Salidas, Ajustes)"]
            Biz_Notifications["Despachador de Notificaciones\n(createNotification, notifyAdmins)"]
            Biz_Invoice["Generador de Facturas\n(invoice.php - HTML Imprimible)"]
            Biz_PayPal["Cliente PayPal SDK/cURL\n(OAuth2, Capturas, Idempotencia)"]
            Biz_ChatEngine["Motor Conversacional\n(OpenAI / Reglas locales)"]
        end

        subgraph DatosConexion ["Acceso a Datos"]
            DB_Driver["MySQLi Connection Pool\n(Prepared Statements, Transacciones)"]
            Env_Config["Environment Config\n(liquorsoftEnv, .env)"]
        end
    end

    %% ==========================================
    %% BASE DE DATOS Y EXTERNOS
    %% ==========================================
    subgraph Persistencia ["Capa de Datos (MySQL 8 / MariaDB 10.4+)"]
        DB_LiquorSoft[("Base de Datos: liquorsoft\n18 tablas InnoDB\n- Catalogo y Stock\n- Pedidos y Ventas\n- Compras y Lotes\n- Usuarios, Auditoría y Notificaciones")]
    end

    subgraph ServiciosExternos ["Servicios y Pasarelas Externas"]
        Ext_PayPal["API PayPal Sandbox\n(api-m.sandbox.paypal.com)"]
        Ext_OpenAI["API OpenAI GPT\n(api.openai.com)"]
    end

    %% Conexiones Frontend
    VistasPublicas --> ServiciosFront
    VistasAdmin --> ServiciosFront
    ServiciosFront --> Guards
    Guards --> HttpCliente
    HttpCliente -->|HTTP / JSON + Cookies| Gateways

    %% Conexiones Backend Internas
    Gateways --> NucleoSeguridad
    NucleoSeguridad --> ServiciosNegocio
    ServiciosNegocio --> DatosConexion
    DatosConexion -->|TCP 3306| DB_LiquorSoft

    %% Conexiones a Servicios Externos
    Biz_PayPal -->|HTTPS / REST v2| Ext_PayPal
    Biz_ChatEngine -->|HTTPS / REST| Ext_OpenAI
```

---

## 3. Diagrama de Arquitectura de Componentes en PlantUML

```plantuml
@startuml
skinparam componentStyle uml2
skinparam packageStyle rectangle

package "Navegador Web (Cliente SPA)" {
  [Angular 22 Standalone App] as AngularApp
  [Auth & Permission Guards] as Guards
  [Cart & State Services (Signals)] as StateServices
  [HTTP Client & Interceptor] as HttpClient

  AngularApp --> StateServices
  AngularApp --> Guards
  StateServices --> HttpClient
}

cloud "Red / Internet" {
  () "HTTP / REST API" as HttpInterface
}

package "Servidor Web (Apache / PHP 8)" {
  component "API Router & Endpoints\n(/api/* & /api/admin/*)" as Router
  component "Security Middleware\n(CSRF, Session, RBAC)" as Security
  component "Checkout & Fulfillment Manager" as Checkout
  component "Inventory & Batch Manager" as Inventory
  component "Notification Dispatcher" as Notifications
  component "Invoice Generator" as Invoice
  component "PayPal cURL Client" as PayPalClient
  component "AI Assistant Engine" as AIEngine
  component "Database Access Layer\n(MySQLi Prepared Statements)" as DAL

  Router --> Security
  Security --> Checkout
  Security --> Inventory
  Security --> Notifications
  Security --> Invoice
  Security --> AIEngine
  Checkout --> PayPalClient
  Checkout --> Notifications
  Checkout --> DAL
  Inventory --> DAL
  Notifications --> DAL
  Invoice --> DAL
  AIEngine --> DAL
}

database "MySQL 8 / MariaDB" {
  [liquorsoft Database\n(18 Tablas Relacionales)] as Database
}

cloud "Servicios Externos" {
  [PayPal Sandbox Orders v2 API] as PayPalService
  [OpenAI API] as OpenAIService
}

HttpClient --> HttpInterface
HttpInterface --> Router
DAL --> Database : TCP 3306
PayPalClient --> PayPalService : HTTPS / OAuth2
AIEngine --> OpenAIService : HTTPS / JSON
@enduml
```

---

## 4. Desglose de Capas y Responsabilidades

### A. Capa Frontend (Angular 22 SPA)
- **Desacoplamiento total**: No se incluye renderizado por servidor (SSR); el bundle estático se ejecuta completamente en el navegador.
- **Manejo de estado**: Basado en **Angular Signals**, lo que proporciona un flujo unidireccional y reactivo sin estados mutables descontrolados.
- **Protección de rutas**: 
  - `authGuard`: Redirige a `/login` si no hay sesión activa.
  - `permissionGuard`: Comprueba si el rol del usuario cuenta con el permiso requerido (`dashboard`, `productos`, `inventario`, `ventas`, `compras`, `proveedores`, `usuarios`).

### B. Capa de Servicios y Lógica de Negocio (Backend PHP)
- **Seguridad en capas**:
  - `requireRole($conn, $roles)`: Verifica privilegios en cada endpoint antes de ejecutar cualquier consulta.
  - `requireCsrfToken()`: Compara la cookie `liquorsoft_csrf` con la cabecera `X-CSRF-Token` para bloquear ataques de tipo falsificación de petición en sitios cruzados.
  - `password_hash()` y `password_verify()`: Criptografía estándar para credenciales de acceso.
- **Transacciones y Concurrencia**:
  - `purchase.php` y `admin/inventory.php` emplean `begin_transaction()` y `SELECT ... FOR UPDATE` para evitar problemas de concurrencia y sobreventa de stock.

### C. Capa de Almacenamiento (MySQL 8 / MariaDB)
- **Persistencia Transaccional**: Tablas de órdenes, ventas, detalles y movimientos operan bajo InnoDB.
- **Integridad y Auditoría**: Tablas como `historial_pedido` y `movimientos_inventario` retienen la trazabilidad operativa histórica, incluso si otros registros cambian.

### D. Capa de Servicios Externos
- **PayPal Sandbox**: El backend orquesta la creación y captura de las órdenes usando identificadores de idempotencia únicos (`UUIDv4`).
- **OpenAI**: Asistente virtual para responder consultas sobre licores y recomendaciones. Si la clave no está configurada o hay fallos de red, el sistema activa automáticamente un motor de expresiones regulares local para mantener la disponibilidad.
