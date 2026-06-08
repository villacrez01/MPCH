# Propuestas de Rediseño — Vista de Inventario de Equipos (admin/equipos.php)

**Proyecto:** Sistema OTI — Gestión Municipal de Tickets  
**Archivo objetivo:** `app/Views/admin/equipos.php` (393 líneas, estilos y JS en línea)  
**Fecha:** 2026-05-27  
**Lenguaje:** PHP 8.2.12, CSS (custom properties), JavaScript, PostgreSQL  
**Contexto:** Vista de inventario de equipos tecnológicos para administradores. Consume API `app/api/equipos.php?action=list`, utiliza `app.css` para tokens de diseño y Lucide icons vía CDN.

---

## 1. Descomposición del problema

### Problema central
La vista actual (393 líneas) mezcla PHP, HTML inline, CSS inline y JavaScript en un único bloque monolítico. No utiliza los tokens de diseño definidos en `app.css`, lo que provoca inconsistencia visual y duplicación de estilos. La lógica de negocio está intacta en el backend (API + Modelo), pero la presentación carece de mantenibilidad, accesibilidad y capacidad responsive.

### Subproblemas que toda solución debe abordar

| # | Subproblema | Descripción |
|---|---|---|
| P1 | **Arquitectura CSS** | Eliminar estilos inline; utilizar clases BEM o sistemáticas que consuman los tokens `--primary`, `--bg-card`, `--shadow-{1-6}`, `--radius-*`, `--space-*`, etc. |
| P2 | **Arquitectura JS** | Reemplazar funciones sueltas y `onclick` en HTML por un módulo organizado (objeto/class/delegación de eventos). |
| P3 | **Rendering de modales** | Decidir si los modales son HTML estático (cargado en PHP) o generados por JS. Los formularios de crear/editar son ~60 líneas de HTML cada uno. |
| P4 | **Rendering de tabla** | Generar filas de tabla vía JS con template literals. Decidir si la paginación se incluye en el mismo flujo. |
| P5 | **Estados vacío/carga/error** | 3-4 estados distintos para tabla, stats y modales. |
| P6 | **Responsive** | Stats grid (4→2→1 columna), tabla (scroll horizontal), modales (full-width en mobile), filtros (vertical stack). |
| P7 | **Accesibilidad** | Roles ARIA, foco manejado, `aria-live`, `prefers-reduced-motion`, contraste de color. |
| P8 | **Mantenibilidad** | Un solo archivo PHP de salida, sin dependencias externas más allá de Lucide y app.css. |

### Criterios de evaluación

1. **Claridad/Separación** — ¿Qué tan fácil es leer y modificar el archivo?
2. **Consistencia visual** — ¿Utiliza correctamente los tokens de `app.css`?
3. **Performance** — ¿Carga inicial mínima? ¿Renders eficientes?
4. **Robustez** — ¿Maneja correctamente errores de red, respuestas no-JSON, estados vacíos?
5. **Responsive** — ¿Funciona en 360px, 768px, 1024px, 1920px?
6. **Accesibilidad** — ¿ARIA, foco, teclado, lectores de pantalla?
7. **Cobertura funcional** — ¿Preserva las 5 operaciones CRUD + desactivar/reactivar + notificaciones?

---

## 2. Mapeo del espacio de soluciones

### Dimensiones de variación

```
Eje A — Estructura JS
  Suelto (onclick + funciones globales) ─── Organizado (class/namespace/module)

Eje B — Rendering de modales
  HTML estático (PHP) ─── JS dinámico (template literals)

Eje C — Organización CSS
  Inline ─── Clases BEM completas ─── Utility-first híbrido

Eje D — Estrategia de datos
  Fetch + string-append ─── State object + redraw completo

Eje E — Interactividad
  onclick directo ─── Delegación de eventos ─── Web components

Eje F — Complejidad
  Minimalista (< 8 funciones) ─── Media (~15 métodos) ─── Alta (30+ métodos, state manager)
```

### Trade-offs principales

| Eje | Ganancia | Sacrificio |
|---|---|---|
| JS organizado vs. suelto | Mantenibilidad, testing | +líneas, +curva de aprendizaje |
| Modales estáticos vs. dinámicos | Carga inicial más rápida, contenido indexable | +HTML duplicado (crear/editar casi idéntico) |
| CSS BEM vs. utility | Legibilidad semántica | +clases únicas, -reutilización entre vistas |
| State object vs. DOM queries | Predictibilidad, facilidad de debug | +código de estado |
| Delegación vs. onclick directo | Performance, menos event listeners | Requiere estructura HTML consistente |

---

## 3. Seis propuestas de alto nivel

---

### Propuesta A — "Monolito Limpio" (Evolución directa)

**Resumen:** Conserva la estructura actual pero elimina todo CSS inline, organiza las funciones JS en un objeto namespace y utiliza las clases de `app.css` de manera sistemática.

**Descripción detallada:**  
La página mantiene el esqueleto actual: partials `head`, `sidebar`, `header`, `footer` proveen la cáscara. El PHP inyecta `$statsData`, `$hierarchyData` para el render inicial. Todos los modales se definen como HTML estático en el markup PHP. Los formularios de crear y editar son idénticos (difieren solo en valores por defecto y action), por lo que se extrae un fragmento compartido.

El CSS utiliza exclusivamente las clases existentes de `app.css`: `.stat-card`, `.stat-icon`, `.stat-content`, `.stat-value`, `.stat-label` para las tarjetas; `.filter-select`, `.search-wrapper`, `.search-input`, `.filter-group` para los filtros; `.action-btn.view`, `.action-btn.edit`, `.action-dd`, `.action-dd__btn`, `.action-dd__menu`, `.action-dd__item` para la columna de acciones; `.toast`, `.toast--success`, `.toast-content`, `.toast__title`, `.toast__message` para notificaciones. Se añaden ~20 nuevas clases específicas de equipos (prefijo `eq-`) registradas en `app.css` (o en un bloque `<style>` en el head).

El JavaScript se organiza en un objeto `window.EquiposInventario = { init, loadData, renderTable, renderStats, openModal, closeModal, ... }`. Los `onclick` en HTML se reemplazan por `data-action` atributos + delegación de eventos en el `main-content`. Las funciones de modal (ver, editar, crear) cargan contenido dinámico en el body del modal mediante `fetch` + template literals.

**Decisiones clave:**
- Modales como HTML estático (mejor SEO, carga inicial más rápida)
- Formularios compartidos via fragmento PHP reutilizable
- Delegación de eventos en el contenedor principal
- Namespace único para evitar colisiones globales

**Trade-offs:**
- (+S) Baja barrera de entrada para otros desarrolladores
- (+S) Fácil depuración, stack traces claros
- (-S) Archivo único aún largo (~450 líneas)
- (-S) Los formularios crear/editar duplican HTML (aunque compartan fragmento)

**Probabilidad de ser la mejor opción:** 0.85  
**Complejidad:** Baja  
**Riesgos:** Que el fragmento de formulario compartido sea frágil si los campos de crear y editar divergen en el futuro.

---

### Propuesta B — "Controlador JS con State Object"

**Resumen:** Introduce un objeto de estado (`state`) que centraliza todos los datos visibles y un único método `render()` que redibuja la UI completa a partir del estado.

**Descripción detallada:**  
Se define una clase `EquiposController` con un constructor que inicializa `this.state = { equipos: [], stats: {}, filters: { search: '', location_id: '', status: '' }, pagination: { page: 1, page_size: 50, total: 0 }, loading: false, error: null }`. Todos los cambios de UI pasan por `this.setState(partial)`, que actualiza el estado y llama a `render()`. El método `render()` es una función pura que, dado el estado, produce HTML para cada sección (stats, tabla, filtros) y lo inserta en el DOM.

Los modales se definen como HTML estático en PHP. Los formularios se rellenan con JS al abrir el modal. La tabla usa template literals con un helper `renderRow(equipo)`.

La paginación se implementa con botones "Anterior/Siguiente" más números de página (limitados a 7 visibles con ellipsis). Cada clic de página actualiza `state.pagination.page` y llama a `loadData()`.

**Decisiones clave:**
- State object inmutable (nuevo objeto en cada `setState`)
- Render completo es simple y predecible (no parcheo del DOM)
- `loadData()` es la única fuente de verdad externa
- Paginación inline (no se requiere librería externa)

**Trade-offs:**
- (+S) Comportamiento predecible: mismo estado → mismo DOM
- (+S) Fácil depuración: `console.log(state)` en cualquier momento
- (-S) Render completo puede ser más lento con 500+ equipos (pero la API page_size lo limita)
- (-S) Mayor cantidad de código de estado que la Propuesta A

**Probabilidad de ser la mejor opción:** 0.82  
**Complejidad:** Media  
**Riesgos:** Si la API devuelve page_size grande (ej. 500+), el render completo podría tener jank. Solución: virtual scrolling o `requestAnimationFrame` para render diferido.

---

### Propuesta C — "HTML Primero con Mejora Progresiva"

**Resumen:** El servidor PHP renderiza la tabla inicial completa, los stats y los modales; el JavaScript solo añade interactividad (AJAX, modales dinámicos, búsqueda).

**Descripción detallada:**  
A diferencia de las propuestas A y B (donde la tabla se carga vía AJAX en blanco), aquí el PHP genera el `<tbody>` completo con los equipos usando `$statsData` y `$locationsData` cargados en el servidor. El JavaScript luego "mejora" la experiencia: al aplicar filtros, se hace fetch al API y se reemplaza solo el `tbody` (no toda la tabla). Los modales de crear/editar se abren con contenido ya servido o se carga vía fetch solo si es necesario.

La ventaja principal: la página funciona sin JavaScript (aunque con funcionalidad reducida). Los formularios de crear/editar son páginas separadas que se abren en el modal via fetch (o iframe ligero). La búsqueda con debounce y los filtros usan `history.replaceState` para mantener la URL sincronizada con los filtros activos.

**Decisiones clave:**
- Server-side rendering inicial (SSR) para la primera carga
- JS solo para filtros, paginación y operaciones asíncronas
- Formularios como páginas separadas cargadas en el modal
- Sincronización de filtros con URL (deep-linkable)

**Trade-offs:**
- (+S) Carga inicial percibida más rápida (contenido visible de inmediato)
- (+S) Funciona sin JS (progressive enhancement real)
- (+S) Mejor SEO (aunque es admin, ayuda a debugging)
- (-S) El PHP de la vista es más complejo (condicionales para SSR vs AJAX)
- (-S) El modal de crear/editar requiere un endpoint PHP separado para el formulario

**Probabilidad de ser la mejor opción:** 0.80  
**Complejidad:** Media-Alta  
**Riesgos:** La duplicación de lógica de renderizado (PHP + JS) puede derivar en inconsistencias. Si el API cambia, hay que actualizar ambos lados.

---

### Propuesta D — "Tablero Kanban con Vista Alterna"

**Resumen:** Además de la tabla, se añade una vista kanban (tablero de columnas por estado) como modo alterno de visualización, con toggle entre tabla y kanban.

**Descripción detallada:**  
La página ofrece dos modos de visualización: la tabla clásica (similar a la actual) y un tablero kanban con 4 columnas (Activos, Mantenimiento, Inactivos, Retirados). Un toggle pill-switch en el encabezado permite cambiar entre modos. El estado activo se guarda en `localStorage`.

En modo kanban, cada equipo es una tarjeta que muestra: nombre, código, serial, tipo, usuario asignado. Las tarjetas son arrastrables (drag & drop nativo HTML5) para cambiar el estado. Al soltar una tarjeta en otra columna, se dispara `fetch` al API con `action=update-status`. Animaciones de transición entre columnas.

La tabla sigue existiendo como modo por defecto. Los filtros (ubicación, búsqueda) aplican a ambos modos. Los modales CRUD son idénticos en ambas vistas. Las stats cards se mantienen siempre visibles.

**Decisiones clave:**
- Dos modos de visualización: tabla y kanban
- Drag & drop nativo (no requiere librería externa)
- Estado de vista persistido en `localStorage`
- Las tarjetas kanban son más informativas que las filas de tabla (más campos visibles)

**Trade-offs:**
- (+S) Experiencia novedosa y visualmente atractiva
- (+S) Útil para inventarios pequeños-medianos (< 200 equipos)
- (-S) El doble de código de renderizado (tabla + kanban)
- (-S) Drag & drop en mobile es problemático (requiere fallback táctil)
- (-S) No escala bien con +500 equipos en kanban

**Probabilidad de ser la mejor opción:** 0.09  
**Complejidad:** Alta  
**Riesgos:** El drag & drop puede introducir bugs de estado. La vista kanban puede ser confusa para administradores acostumbrados a la tabla. Posible sobreingeniería para un módulo de inventario.

---

### Propuesta E — "Web Component Nativo para la Tabla"

**Resumen:** Se encapsula toda la tabla de equipos (con filtros, paginación y acciones) en un Custom Element `<equipment-table>` con Shadow DOM, completamente auto-contenido.

**Descripción detallada:**  
Se define `<equipment-table>` como un Web Component v1 nativo (sin polyfill ni librería). El elemento recibe atributos `api-endpoint`, `page-size`, `filters` y expone métodos `.load()`, `.setFilter(name, value)`, `.refresh()`. Internamente maneja su propio fetch, estado, renderizado de filas y paginación.

El Shadow DOM encapsula estilos (usando `adoptedStyleSheets` con las variables CSS de `app.css` heredadas vía `@inherit` o `part` exports). El componente emite eventos `equipment:view`, `equipment:edit`, `equipment:delete`, `equipment:deactivate`, `equipment:reactivate` que el contenedor padre (el PHP view) escucha para abrir los modales correspondientes.

Las stats cards y los modales permanecen fuera del web component (en el DOM principal), pero se actualizan escuchando el evento `equipment:data-loaded` que el componente dispara después de cada fetch exitoso, llevando `{ stats, total }` en el detalle.

**Decisiones clave:**
- Encapsulación total usando Shadow DOM
- API basada en eventos personalizados
- `adoptedStyleSheets` para compartir tokens de diseño
- El componente es reutilizable en otras vistas (ej. dashboard)

**Trade-offs:**
- (+S) Aislamiento completo: estilos y comportamiento no se filtran
- (+S) Reutilizable en cualquier página del sistema
- (-S) Shadow DOM rompe estilos globales de `app.css` (requiere `@part` o inheritable properties)
- (-S) Curva de aprendizaje: no todos los desarrolladores del equipo conocen Web Components
- (-S) Mayor complejidad de debugging (eventos, shadow boundaries)
- (-S) `adoptedStyleSheets` no soportado en algunos navegadores antiguos

**Probabilidad de ser la mejor opción:** 0.08  
**Complejidad:** Alta  
**Riesgos:** Incompatibilidad parcial con navegadores legacy (si el municipio usa IE11 o navegadores desactualizados). El Shadow DOM puede complicar la integración con Lucide icons (requiere slot o light DOM).

---

### Propuesta F — "SPA Ligero con Render Diferido"

**Resumen:** La página funciona como una mini-SPA: un shell HTML vacío, y todo el contenido (stats, filtros, tabla, paginación) se renderiza desde JavaScript utilizando un pequeño motor de templates con lazy rendering para la tabla (solo renderiza las filas visibles en el viewport).

**Descripción detallada:**  
El archivo PHP contiene solo los partials, el shell `<main>` vacío con un `<div id="app">`, y los modales como HTML estático (sin contenido dinámico). Un script `equipos-app.js` (inline por restricción de archivo único, pero organizado como un módulo IIFE) gestiona todo el ciclo de vida:

1. `init()`: fetch inicial a `equipos.php?action=list` con page=1
2. `build()`: construye todo el DOM (stats, filters, tabla, paginación) usando template functions
3. Para la tabla, implementa **lazy rendering**: solo construye nodos DOM para las filas dentro del viewport. Usa un IntersectionObserver en un "centinel" al final para cargar más filas (infinite scroll virtual).
4. Los filtros y búsqueda reinician la página a 1 y disparan un nuevo fetch.
5. La paginación es "cargar más" (infinite scroll) combinado con botones de página para saltos grandes.

Los modales (ver, editar, crear) cargan su contenido mediante fetch al API específico. El formulario de crear/editar se construye con funciones helper (`buildFormField(type, name, value, options, ...)`).

El estado se maneja con una simple closure: `const state = { equipos: [], page: 1, total: 0, loading: false, filters: {} }`. No hay objeto complejo, solo variables capturadas en closure.

**Decisiones clave:**
- Shell vacío, todo renderizado por JS
- Lazy rendering de filas (IntersectionObserver)
- Infinite scroll + paginación numérica híbrida
- Form helpers para construir formularios consistentemente
- Sin dependencias externas

**Trade-offs:**
- (+S) Carga inicial extremadamente rápida (HTML mínimo)
- (+S) La tabla es performante con conjuntos grandes (solo renderiza filas visibles)
- (+S) Experiencia SPA sin framework
- (-S) No funciona sin JavaScript (a diferencia de Propuesta C)
- (-S) Mayor complejidad: IntersectionObserver, lazy rendering, manejo de scroll
- (-S) El contenido no es indexable (irrelevante para admin pero útil para debugging)
- (-S) El "salto" al hacer scroll puede ser confuso si el usuario espera una tabla de páginas

**Probabilidad de ser la mejor opción:** 0.06  
**Complejidad:** Alta  
**Riesgos:** El infinite scroll combinado con filtros puede causar estados inconsistentes (ej. aplicar filtro mientras se cargan datos en segundo plano). Requiere manejo cuidadoso de race conditions en las peticiones fetch (usar `AbortController`).

---

## 4. Verificación de diversidad

| Eje | A | B | C | D | E | F |
|---|---|---|---|---|---|---|
| Estructura JS | Namespace | Class/State | Mínimo | Class | Web Component | Closure/IIFE |
| Rendering modales | Estático | Estático + JS fill | SSR + fetch | Estático + JS fill | Event-driven | Form helpers |
| CSS | Clases existentes | Clases existentes | Clases + SSR | Clases + kanban | Shadow DOM | Clases existentes |
| Estrategia datos | Fetch + parche | State + redraw | SSR inicial | Fetch + parche | Event-based | Fetch + lazy |
| Interactividad | Delegación | setState → render | onclick mínimo | Drag & drop | Custom Events | IntersectionObs. |
| Complejidad | Baja | Media | Media-Alta | Alta | Alta | Alta |

**¿Son genuinamente diferentes?** Sí. Cada propuesta ocupa una región distinta del espacio de soluciones:
- **A** es la evolución conservadora
- **B** introduce un patrón state → render
- **C** invierte el flujo (SSR primero)
- **D** cambia la metáfora visual (kanban)
- **E** encapsula en Web Component
- **F** es lazy/SPA progresivo

**Cobertura del espacio:** Las 6 propuestas cubren desde lo más conservador (A) hasta lo más experimental (E, F), pasando por enfoques intermedios con distintas estrategias de estado y rendering.

---

## 5. Recomendación preliminar

Para un proyecto municipal PHP+PostgreSQL con un equipo de desarrollo pequeño, la **Propuesta A (Monolito Limpio)** ofrece el mejor balance entre mantenibilidad, simplicidad y robustez. Si el equipo tiene experiencia con patrones de estado, la **Propuesta B (Controlador JS)** es preferible por su previsibilidad. Las propuestas D, E y F son interesantes pero introducen complejidad que no se justifica para un módulo de inventario estándar.

**Recomendación:** Proceder con Propuesta A como baseline, incorporando el state object simple de la Propuesta B si el equipo lo considera útil. Las Propuestas C-F pueden servir como inspiración para futuras iteraciones.
