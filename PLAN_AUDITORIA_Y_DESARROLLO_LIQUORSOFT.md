# Prompt maestro — Auditoría técnica de LiquorSoft y plan de desarrollo

> **Instrucción para OpenCode:** Lee este documento completo antes de actuar. Sigue sus límites y entrega primero la auditoría y el plan. No implementes cambios hasta recibir autorización explícita.

## 1. Misión

Realiza una auditoría técnica, funcional y arquitectónica del repositorio disponible de LiquorSoft, una aplicación web relacionada con la venta de bebidas alcohólicas.

Tu objetivo es determinar, con evidencias, el estado real del proyecto y entregar una hoja de ruta priorizada, verificable y ejecutable para llevarlo a un estado estable, seguro, mantenible y, cuando corresponda, preparado para producción.

No supongas que una funcionalidad existe porque aparece en la interfaz. No declares que algo funciona sin comprobarlo. No inventes errores, tecnologías, resultados, versiones, métricas ni capacidades del entorno.

La auditoría termina con el informe y el plan. **No implementes ninguna corrección ni modifiques archivos.** Espera mi autorización explícita antes de comenzar a cambiar el código.

## 2. Principios de trabajo

- Basa las conclusiones en el repositorio y las herramientas realmente disponibles.
- Adapta el análisis a la arquitectura detectada; no presupongas frameworks, lenguaje, motor de base de datos ni proveedor de hosting.
- Separa hechos confirmados, hipótesis y aspectos pendientes de verificar.
- Distingue entre funcionalidad implementada, parcial, simulada, rota, inexistente y no verificable.
- No recomiendes reescrituras, microservicios, cambios de tecnología ni patrones complejos sin una justificación concreta.
- Evita hallazgos duplicados: agrúpalos y conserva las referencias a todos los componentes afectados.
- No afirmes que el proyecto está listo para producción si no puedes demostrarlo.
- Mantén un registro de cobertura: qué inspeccionaste, qué verificaste y qué quedó fuera de alcance.
- Si no puedes terminar en una sola respuesta, entrega el informe por partes numeradas, conserva los identificadores y la cobertura, y no omitas hallazgos para acortarlo.

## 3. Límites y seguridad de la auditoría

La inspección inicial es de solo lectura:

- No edites, crees, elimines ni sobrescribas archivos.
- No ejecutes migraciones ni alteres bases de datos.
- No cambies configuraciones, permisos, cuentas o datos.
- No instales ni actualices dependencias o herramientas.
- No realices despliegues ni pruebas destructivas.
- No uses datos reales de clientes, credenciales o pagos.
- No contactes servicios de producción ni sistemas de terceros.
- No reveles contraseñas, tokens, claves API, cookies ni valores de secretos. Si encuentras uno, indica únicamente el archivo y el tipo de exposición; recomienda su rotación cuando corresponda.

Antes de ejecutar comandos, revisa qué hacen y si podrían escribir archivos, modificar datos, instalar paquetes o contactar servicios externos. Ejecuta únicamente comprobaciones seguras y no destructivas. Si una comprobación necesaria puede tener efectos secundarios, no la ejecutes: explica el riesgo y solicita autorización.

No hagas explotación, fuerza bruta, extracción de datos ni pruebas contra sistemas no autorizados. Para riesgos de seguridad, limita la validación al análisis defensivo del código y a pruebas seguras en un entorno autorizado.

## 4. Método de auditoría

Trabaja en este orden y registra el resultado de cada etapa:

1. **Reconocimiento:** identifica la estructura del repositorio, archivos de configuración, documentación y estado inicial de cambios, sin mostrar secretos.
2. **Inventario técnico:** determina lenguajes, frameworks, dependencias, scripts, servicios y componentes realmente presentes.
3. **Mapa de arquitectura:** describe módulos, límites, flujos de datos e integraciones observables.
4. **Trazado de funcionalidades:** sigue los flujos importantes desde la interfaz hasta el backend, persistencia e integraciones; no infieras la implementación a partir de nombres o comentarios.
5. **Verificación segura:** ejecuta análisis estáticos, pruebas o compilaciones existentes únicamente si son seguras y no requieren modificar datos, instalar dependencias ni alterar el repositorio.
6. **Registro de hallazgos:** documenta cada problema con evidencia concreta, alcance, impacto y una forma verificable de corregirlo.
7. **Planificación:** ordena las correcciones por prioridad, dependencias, riesgos y criterios de aceptación.

Si falta acceso a un archivo, servicio, base de datos, herramienta o entorno, indícalo expresamente. No conviertas una limitación de acceso en un hallazgo confirmado.

## 5. Alcance de la revisión

Inspecciona los componentes que existan en el repositorio, incluidos, cuando corresponda:

### Arquitectura y calidad del código

- Estructura, separación de responsabilidades, acoplamiento, cohesión y dependencias entre módulos.
- Duplicación, código muerto, funciones excesivamente complejas y errores de manejo.
- Tipos, contratos, configuración y convenciones.
- Riesgo de regresiones y deuda técnica que dificulte cambios futuros.

### Frontend

- Rutas, enlaces, formularios, botones y acciones.
- Integración con APIs y correspondencia entre datos esperados y recibidos.
- Validación, estados de carga, error, éxito y vacío.
- Catálogo, detalle de producto, carrito, checkout y administración, si existen.
- Adaptación móvil, accesibilidad, navegación por teclado, legibilidad e inconsistencias visuales.
- Datos simulados o estáticos que puedan presentarse como información real.

### Backend y API

- Rutas, métodos HTTP, contratos, validaciones y códigos de respuesta.
- Reglas de negocio, consultas, transacciones, errores e idempotencia.
- Autenticación y autorización en el servidor.
- Paginación, búsqueda, filtros, carga de archivos e integraciones.
- Protección contra abuso y exposición de información interna.

### Base de datos y persistencia

Determina primero qué motor se usa y si puedes inspeccionarlo. Compara el esquema disponible con modelos, consultas y flujos del sistema. Revisa, según corresponda:

- Tablas, columnas, tipos, valores por defecto, claves, relaciones, restricciones e índices.
- Diferencias entre esquema y código, migraciones y scripts de inicialización.
- Nulos, duplicados, registros huérfanos y riesgos de pérdida o corrupción.
- Consultas costosas, transacciones incompletas, concurrencia, codificación y zona horaria.
- Datos de prueba frente a datos operativos.

No accedas ni modifiques datos reales. Si no puedes consultar la base de datos, limita las conclusiones a los archivos disponibles. No recomiendes cambiar de motor sin evidencia y análisis de compatibilidad.

### Productos, inventario y compras

Reconstruye el flujo de compra de extremo a extremo y verifica, hasta donde permitan las evidencias:

- Catálogo, detalle, cantidades, carrito y autenticación.
- Precios, moneda, totales y validaciones calculadas por el servidor.
- Disponibilidad, reservas y actualización de inventario.
- Creación del pedido, pago real o simulado, confirmaciones, factura y notificaciones.
- Estados del pedido, consultas posteriores y acciones administrativas.
- Doble envío, pedidos duplicados, stock insuficiente, confirmaciones repetidas y fallos parciales.

Diferencia claramente las integraciones reales de las simuladas. No afirmes que existe una pasarela, pago, factura o notificación funcional si el código solo simula ese comportamiento.

### Seguridad

Realiza una revisión defensiva del código y la configuración. Considera, según la arquitectura:

- Inyección, XSS, CSRF, validación insuficiente y carga de archivos.
- Autenticación, sesiones, contraseñas, autorización y acceso a recursos ajenos.
- Escalada de privilegios y protección de rutas administrativas.
- CORS, cookies, secretos, logs y errores que revelen información.
- Dependencias vulnerables y configuración de producción.
- Integraciones, límites de peticiones y protección de respaldos.

No reproduzcas secretos ni proporciones instrucciones de explotación. Para cada riesgo, describe el impacto y una mitigación concreta.

### Roles y administración

Identifica los roles implementados y sus permisos efectivos. Compara lo que muestra la interfaz con lo que valida el backend y lo que definen los modelos o esquemas. No crees usuarios ni cambies permisos.

### Dependencias, pruebas y operación

- Revisa manifiestos, scripts de ejecución, compilación, lint, tipado y pruebas existentes.
- Registra los comandos ejecutados y sus resultados reales.
- Examina Docker, CI/CD, variables de entorno, HTTPS, logs, respaldos, health checks, persistencia de archivos y despliegue, solo si existen.
- Evalúa rendimiento con evidencias. No declares cuellos de botella sin mediciones o pruebas que los sustenten.
- No actualices dependencias automáticamente ni asumas capacidades de un hosting no identificado.

### UX y requisitos del dominio

Evalúa claridad, consistencia y accesibilidad sin proponer un rediseño completo por defecto. Si el proyecto involucra venta de alcohol, señala requisitos de edad, ubicación o regulación únicamente cuando el código, la documentación o el contexto permitan evaluarlos. No presentes conclusiones legales sin jurisdicción y evidencia suficientes.

## 6. Formato de cada hallazgo

Asigna identificadores consecutivos y estables: `AUD-001`, `AUD-002`, etc. Cada hallazgo debe incluir:

- **Título y área**
- **Estado de evidencia:** confirmado, probable o pendiente de verificar
- **Severidad y prioridad**
- **Confianza:** alta, media o baja, con una breve justificación
- **Ubicación:** archivo y líneas, función, endpoint, tabla o componente, cuando sea posible
- **Evidencia:** observación concreta, sin copiar secretos ni bloques de código innecesarios
- **Problema y causa raíz:** separa lo comprobado de lo inferido
- **Impacto técnico y de negocio**
- **Corrección recomendada**
- **Dependencias**
- **Prueba de verificación**
- **Criterio objetivo de aceptación**

Clasificación de severidad:

- **Crítica:** riesgo inmediato de compromiso, pérdida grave de datos o fallo esencial.
- **Alta:** afecta compras, pagos, inventario, permisos o funcionalidades centrales.
- **Media:** provoca errores relevantes, deuda técnica o fallos recurrentes.
- **Baja:** defecto menor o mejora acotada de mantenibilidad o UX.
- **Informativa:** observación o recomendación preventiva sin defecto demostrado.

Clasificación de prioridad:

- **P0:** contención inmediata o bloqueo de una operación esencial.
- **P1:** corrección prioritaria para proteger datos, seguridad o flujos críticos.
- **P2:** corrección importante, pero no bloqueante a corto plazo.
- **P3:** mejora de menor urgencia.

No uses severidad y prioridad como sinónimos. Justifica las prioridades y dependencias; no asignes P0 por defecto.

## 7. Informe final

Entrega el informe en este orden:

1. **Resumen ejecutivo:** estado comprobado, principales riesgos y límites de la auditoría.
2. **Inventario técnico:** tecnologías, componentes, scripts e integraciones detectados.
3. **Arquitectura actual:** módulos y flujos observados, con referencias.
4. **Mapa de funcionalidades:** implementadas, parciales, simuladas, rotas, inexistentes o no verificables.
5. **Registro de cobertura:** áreas y archivos revisados, comprobaciones realizadas y elementos pendientes.
6. **Matriz maestra de hallazgos**, con columnas:  
   `ID | Área | Hallazgo | Evidencia | Estado | Severidad | Prioridad | Impacto | Dependencias | Corrección | Prueba`
7. **Detalle de hallazgos:** fichas completas para cada problema, siguiendo el formato de la sección 6.
8. **Análisis transversal y causas raíz:** patrones compartidos, sin repetir hallazgos.
9. **Auditorías específicas:** base de datos; seguridad; proceso de compra; roles y permisos; operación y rendimiento, según aplique.
10. **Plan de desarrollo por fases:** ordenado según el estado real del proyecto, no según una plantilla fija.
11. **Backlog ejecutable:** tareas concretas vinculadas a los hallazgos.
12. **Matriz de pruebas:** pruebas necesarias para confirmar las correcciones.
13. **Decisiones pendientes y preguntas para una persona responsable.**
14. **Limitaciones:** accesos, archivos, herramientas, servicios o pruebas no disponibles.
15. **Recomendación final:** primeras acciones, riesgos que requieren contención y métricas o evidencias para medir el avance.

En el registro de comandos, incluye solo comandos realmente ejecutados, su resultado y cualquier limitación. No presentes como ejecutada una prueba que solo recomiendas.

## 8. Plan de desarrollo y tareas

Adapta las fases a los hallazgos. No propongas rehacer áreas que ya estén correctamente resueltas.

Considera, cuando sean pertinentes, estas etapas:

- Línea base y estabilización.
- Contención de riesgos críticos.
- Integridad de datos y persistencia.
- Backend, API y permisos.
- Frontend y experiencia.
- Flujos de negocio: autenticación, pedidos, inventario, pagos y notificaciones.
- Seguridad y rendimiento.
- Pruebas y automatización.
- Despliegue, operación y recuperación.
- Documentación y mantenimiento.

Cada tarea debe contener:

- ID de tarea y hallazgos relacionados.
- Objetivo y problema que resuelve.
- Archivos o módulos afectados, si están identificados.
- Dependencias y orden.
- Pasos de implementación a nivel suficiente para ejecutarla.
- Riesgos y estrategia de reversión cuando aplique.
- Pruebas y criterios de aceptación verificables.
- Prioridad y estimación de esfuerzo en rango, solo si existe información suficiente.

Indica la ruta crítica y qué tareas podrían ejecutarse en paralelo. No inventes precisión en estimaciones ni detalles de implementación que dependan de decisiones aún pendientes.

La matriz de pruebas debe asociar cada prueba con uno o más hallazgos o tareas e incluir precondiciones, acción, resultado esperado y entorno seguro. Considera, cuando aplique, usuarios sin sesión, usuarios sin permisos, datos inválidos, recursos inexistentes, fallos de red o base de datos, doble envío, stock insuficiente, cambios de precio, pagos rechazados y confirmaciones duplicadas. Nunca uses datos reales para pruebas destructivas.

## 9. Condición de cierre

Tu trabajo termina al entregar la auditoría, sus límites y el plan priorizado. **No edites archivos, no implementes soluciones y no conviertas el informe en cambios de código.**

Después de presentar los resultados, espera mi autorización explícita para implementar. Si la recibes en una solicitud posterior, trabaja por tareas pequeñas, conserva los cambios preexistentes, ejecuta las pruebas adecuadas y comunica exactamente qué se modificó y qué se verificó.

## 10. Inicio

Comienza inspeccionando el repositorio disponible. Determina primero qué existe y qué se puede verificar de forma segura. Construye el informe únicamente con evidencias del proyecto real; no presupongas que LiquorSoft está bien o mal construido.
