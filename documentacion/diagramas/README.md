# Documentación Técnica de Diagramas - LiquorSoft

Esta carpeta contiene el conjunto completo de diagramas de ingeniería y arquitectura del sistema **LiquorSoft**, generados tras el análisis exhaustivo del código fuente real (Frontend Angular 22, Backend PHP 8 y Base de Datos MySQL/MariaDB).

---

## Índice de Diagramas Generados

| N° | Archivo | Tipo de Diagrama | Formatos Disponibles |
|:---:|---|---|:---:|
| **1** | [1_casos_de_uso.md](file:///c:/Users/SENA/LiquorSoft/documentacion/diagramas/1_casos_de_uso.md) | Diagrama de Casos de Uso UML | Mermaid + PlantUML |
| **2** | [2_clases_uml.md](file:///c:/Users/SENA/LiquorSoft/documentacion/diagramas/2_clases_uml.md) | Diagrama de Clases UML | Mermaid + PlantUML |
| **3** | [3_entidad_relacion_er.md](file:///c:/Users/SENA/LiquorSoft/documentacion/diagramas/3_entidad_relacion_er.md) | Diagrama Entidad-Relación (E-R) | Mermaid (Crow's Foot) + PlantUML |
| **4** | [4_modelo_relacional.md](file:///c:/Users/SENA/LiquorSoft/documentacion/diagramas/4_modelo_relacional.md) | Modelo Relacional de la Base de Datos | Mermaid Físico + Álgebra Relacional + Tablas |
| **5** | [5_arquitectura_componentes.md](file:///c:/Users/SENA/LiquorSoft/documentacion/diagramas/5_arquitectura_componentes.md) | Diagrama de Arquitectura y Componentes | Mermaid + PlantUML Component |

---

## Cómo importar o visualizar los diagramas en Eraser (eraser.io)

**Eraser** es una herramienta moderna de documentación técnica y diagramas como código (*Diagram-as-Code*). Soporta nativamente sintaxis **Mermaid** y formato **Markdown**. No necesitas subir ningún archivo ZIP. Tienes dos métodos sencillos y directos:

### Método 1: Pegar directamente como Bloque Mermaid en Eraser (Recomendado y más rápido)

1. Ingresa a tu espacio de trabajo en [Eraser.io](https://app.eraser.io/).
2. Crea un nuevo **Canvas / Documento** (botón `+ New` -> `New File`).
3. En el editor de texto o en el canvas:
   - Presiona `/` (Slash command) y escribe `mermaid` o selecciona **Mermaid Diagram**.
   - O bien, escribe tres comillas invertidas seguidas de `mermaid` y presiona Enter:
     ````markdown
     ```mermaid
     ...
     ```
     ````
4. Abre cualquiera de los archivos `.md` de esta carpeta (por ejemplo, [3_entidad_relacion_er.md](file:///c:/Users/SENA/LiquorSoft/documentacion/diagramas/3_entidad_relacion_er.md)).
5. Copia únicamente el bloque de código que está dentro de ```` ```mermaid ```` y pégalo en Eraser.
6. **Eraser renderizará inmediatamente el diagrama interactivo**. Podrás moverlo, cambiarle el zoom, aplicarle temas de color, alternar entre vista de código y vista gráfica, o exportarlo a PNG/SVG.

---

### Método 2: Importar el Documento Markdown Completo en Eraser

Eraser permite importar documentos en formato Markdown manteniendo encabezados, tablas explicativas y bloques de diagramas:

1. En Eraser, abre un documento de notas (`Doc`).
2. Copia todo el contenido de cualquiera de los archivos `.md` de esta carpeta.
3. Pégalo directamente en el cuerpo del documento en Eraser (`Ctrl + V`).
4. Eraser interpretará automáticamente la estructura:
   - Convertirá los títulos, tablas comparativas y listas con su formato enriquecido.
   - Convertirá automáticamente los bloques ```` ```mermaid ```` en **diagramas visuales interactivos embebidos**.

---

### Visualización alternativa en otras herramientas

Si deseas visualizarlos fuera de Eraser:
- **VS Code / Antigravity IDE**: Instala la extensión **Markdown Preview Mermaid Support** o **PlantUML** para previsualizarlos en tiempo real pulsando `Ctrl + Shift + V`.
- **Mermaid Live Editor**: Abre [mermaid.live](https://mermaid.live/) y pega directamente el código Mermaid.
- **PlantText / PlantUML Online**: Abre [planttext.com](https://www.planttext.com/) y pega los bloques PlantUML correspondientes.
