# Rediseño Vista de Inventario de Equipos — Propuestas de Alto Nivel

**Proyecto:** Sistema OTI — Módulo de Administración de Equipos  
**Archivo objetivo:** `app/Views/admin/equipos.php` (393 líneas, estilos/JS inline)  
**Fecha:** 2026-05-27  
**Contexto:** PHP 8.2 + PostgreSQL + CSS custom properties (app.css) + Lucide icons + layout parcial (head/sidebar/header/footer)

---

## Descomposición del Problema

| Aspecto | Detalle |
|---|---|
| **Problema central** | La vista actual mezcla HTML, CSS inline y JS en un solo archivo de 393 líneas, dificultando mantenimiento, escalabilidad y la adopción del sistema de diseño unificado (app.css). |
| **Restricciones clave** | No modificar API (`app/api/equipos.php`) ni Modelo (`Equipment.php`); usar solo CSS custom properties de app.css; texto 100% español; mobile-first responsive; preservar toda la lógica CRUD existente. |
| **Subproblemas** | (1) Organización del markup, (2) Consistencia visual con el sistema de diseño, (3) Experiencia de filtrado/búsqueda responsiva, (4) Modales CRUD reutilizables, (5) Renderizado de tabla con acciones en fila, (6) Notificaciones toast, (7) Paginación, (8) Manejo de estados vacío/carga/error. |
| **Criterios de éxito** | Cero regresiones funcionales, código legible y mantenible, uso exclusivo de tokens CSS, tiempo de carga perceptual mejorado, experiencia móvil utilizable. |

---

## Mapeo del Espacio de Soluciones

**Dimensiones de variación:**
1. **Densidad de contenido** — tabla compacta vs. tarjetas visuales vs. paneles divididos
2. **Modelo de interacción** — CRUD modal vs. inline vs. panel lateral vs. wizard
3. **Arquitectura de información** — lista plana vs. columnas kanban vs. árbol jerárquico vs. línea de tiempo
4. **Jerarquía visual** — dashboard con resúmenes vs. grid de datos puro vs. explorador

**Ejes de trade-off:**
- Rendimiento perceptual ↔ Riqueza visual
- Familiaridad del usuario ↔ Innovación de interacción
- Densidad de información ↔ Claridad/legibilidad
- Esfuerzo de implementación ✅ Versus impacto en mantenibilidad

---

## Propuesta 1 — «Dashboard Compacto» (Probabilidad: ~0.85)

**Resumen:** Evolución directa del diseño actual: tabla de datos pulida con barra de filtros flotante, tarjetas de estadísticas animadas y modales optimizados, todo apoyado en los tokens de app.css.

**Descripción detallada:**

El layout mantiene la secuencia actual: **stats-grid** (4 cards con iconos Lucide y animación de conteo), **filters-section** (fila responsiva con select de ubicación, select de estado y búsqueda con debounce) y **table-card** envolviendo la tabla. La tabla usa el sistema de diseño unificado: `action-btn` (view/edit), `action-dd` (dropdown con deactivate/reactivate/delete), `status-badge` con colores semánticos. Los modales se renderizan con clases como `modal-overlay` + `modal large` reutilizando los estilos de app.css sin inline styles.

El JS se organiza en un solo bloque `<script>` con funciones nombradas (ya existentes, refactorizadas mínimamente) y se eliminan todos los SVGs inline reemplazándolos por `<i data-lucide="...">`. Los formularios CRUD se generan con template strings JS limpias, extrayendo las opciones de tipo/estado/condición a arrays JS reutilizables. La paginación usa los estilos predefinidos `.pagination-container` + `.pagination-btn` + `.pagination-page`.

**Decisiones clave:**
- Priorizar la cobertura completa del sistema de diseño existente sobre innovación visual
- Mantener la familiaridad: los usuarios ya conocen este flujo de trabajo
- Los modales siguen siendo la interfaz para CRUD (no se cambia el paradigma)

**Trade-offs:**
| Ganancia | Sacrificio |
|---|---|
| Migración de bajo riesgo y rápida | Poca innovación en UX |
| Cero fricción para usuarios existentes | No resuelve problemas de densidad de datos |
| Consistencia visual inmediata | La tabla sigue siendo el cuello de botella en mobile |

**Complejidad:** Baja  
**Riesgos:** Ninguno significativo; es una refactorización conservadora.

---

## Propuesta 2 — «DataGrid Profesional» (Probabilidad: ~0.80)

**Resumen:** Tabla avanzada con ordenamiento por columnas, selección de página/tamaño, exportación y barra de herramientas superior, similar a paneles de administración modernos (shadcn/ui, Radix).

**Descripción detallada:**

La vista se reorganiza en tres zonas. **Zona superior:** stats-grid (idéntica a la actual). **Zona media:** toolbar horizontal con (1) botón "Nuevo Equipo", (2) selector de tamaño de página (`10 / 20 / 50 / 100`), (3) botón de exportar CSV, (4) conteo total de resultados. **Zona inferior:** tabla completa con headers cliqueables para orden ascendente/descendente (JS nativo, sin librerías externas). Cada columna tiene un indicador visual de orden `▲/▼` y el estado de orden se mantiene en un objeto JS.

Las acciones de fila se compactan: en desktop, 3 iconos visibles (view, edit, more); en mobile, solo un botón "more vertical" que despliega un menú _bottom sheet_ nativo (no modal, sino un div con posición fixed que simula un sheet desde abajo). La paginación se ubica _dentro_ del card de la tabla (antes del footer del card), mostrando "Mostrando X-Y de Z equipos" + botones de página.

Los modales se simplifican: "Ver" y "Editar" comparten el mismo modal, alternando entre modo lectura y modo formulario con un toggle "Editar" dentro del modal.

**Decisiones clave:**
- Ordenamiento por columna es la feature más solicitada en tablas de inventario
- Exportación a CSV sin librerías (generación manual con Blob)
- Bottom sheet nativo para mobile evita problemas de posicionamiento de dropdown

**Trade-offs:**
| Ganancia | Sacrificio |
|---|---|
| Experiencia de exploración de datos superior | Mayor complejidad JS (sort, export, pagination state) |
| Útil para inventarios grandes (100+ equipos) | Overhead de mantener estado de orden |
| Percepción profesional ("enterprise-grade") | Riesgo de bugs en el sort cross-browser |

**Complejidad:** Media  
**Riesgos:** Ordenamiento cliente-side puede desincronizarse con filtros del servidor; requiere reiniciar sort al recargar datos.

---

## Propuesta 3 — «Kanban Visual» (Probabilidad: ~0.85)

**Resumen:** Vista basada en tarjetas organizadas en 4 columnas kanban (Activos / Mantenimiento / Inactivos / Retirados) con arrastre entre columnas para cambiar estado, más una tabla opcional como vista secundaria.

**Descripción detallada:**

Se reemplaza la tabla por un **kanban-board** de 4 columnas en scroll horizontal. Cada columna tiene un header con el nombre del estado, un contador y un color semántico de borde superior (success, warning, danger, muted). Las tarjetas (`equipo-card`) muestran: código patrimonial, nombre, tipo (con icono Lucide), ubicación, usuario asignado y badges de condición. Las tarjetas son arrastrables (`dragstart/dragover/drop` nativos) entre columnas, lo que dispara una llamada `POST` a `equipos.php?action=update-status` con el nuevo status.

Las estadísticas se integran en el header del kanban como parte de cada columna, eliminando el grid de 4 cards superior (ahora el total es la suma de los contadores de columna). Los filtros (ubicación, búsqueda) siguen existiendo como una barra compacta arriba del kanban. Un toggle "Vista Tabla / Vista Kanban" permite cambiar entre el kanban y una tabla tradicional (más compacta para exportación mental). Los modales CRUD se mantienen idénticos.

El drag & drop usa la API nativa HTML5 (sin librerías). Se persiste el estado de la vista seleccionada en `localStorage`.

**Decisiones clave:**
- Kanban es natural para equipos: el estado representa el "flujo de vida" del activo
- El toggle de vista es crucial para usuarios que prefieren tabla
- Drag & drop nativo evita dependencias externas

**Trade-offs:**
| Ganancia | Sacrificio |
|---|---|
| Experiencia visual altamente intuitiva | No escala bien a >50 equipos por columna |
| Cambio de status con un gesto físico | Drag & drop en mobile es problemático |
| Diferenciación clara del resto del sistema | Mayor consumo de espacio vertical |

**Complejidad:** Alta  
**Riesgos:** Drag & drop en pantallas táctiles requiere fallback (botones "Mover a..."); rendimiento con muchos equipos.

---

## Propuesta 4 — «Panel Dividido / Inspector» (Probabilidad: ~0.08)

**Resumen:** Vista maestro-detalle tipo Outlook/Gmail: una lista compacta de equipos a la izquierda y un panel de inspección/detalle a la derecha, eliminando la necesidad de modales para ver y editar.

**Descripción detallada:**

El layout principal se divide en dos secciones: **panel izquierdo** (35-40% del ancho) con una lista compacta de equipos (`equipo-list-item`) y **panel derecho** (60-65%) con la vista de detalle del equipo seleccionado. No hay tabla: cada item de la lista muestra código, nombre, tipo (icono), estado (badge) y un indicador de asignación. Al hacer clic en un item, el panel derecho se actualiza sin recargar la página.

El panel derecho tiene tres modos intercambiables con tabs locales: **"Detalle"** (grid de información similar al modal `ver-equipo` actual), **"Editar"** (formulario inline en el mismo panel), y **"Historial"** (actividad reciente del equipo, consumiendo de un endpoint futuro o del log de cambios). Los botones de acción (desactivar, eliminar) aparecen como `action-btn` en el header del panel derecho. Los filtros están en el panel izquierdo como una sección plegable arriba de la lista.

En mobile (<1024px), el panel derecho se oculta inicialmente y la lista ocupa todo el ancho; al seleccionar un equipo, la lista se desliza hacia la izquierda (CSS transform) y el detalle entra desde la derecha — similar a la navegación de email en iOS.

**Decisiones clave:**
- Modales cero (solo desactivar/eliminar usan modal de confirmación)
- Panel derecho como lienzo dinámico: detalle ↔ edición sin cambio de ruta
- Transiciones CSS para mobile navigation stack

**Trade-offs:**
| Ganancia | Sacrificio |
|---|---|
| Navegación extremadamente fluida | Mayor complejidad de layout (flexbox avanzado) |
| Sin modales = menos clipping/z-index issues | Panel derecho inutilizable en pantallas <360px |
| Vista de detalle siempre disponible | Ocupa más espacio horizontal |

**Complejidad:** Media-Alta  
**Riesgos:** Estado de selección debe sincronizarse con recargas AJAX; en mobile la navegación tipo stack requiere manejo de historia.

---

## Propuesta 5 — «Registro Cronológico / Línea de Tiempo» (Probabilidad: ~0.05)

**Resumen:** Vista organizada por actividad cronológica donde cada equipo aparece como un evento en una línea de tiempo agrupada por fecha de última modificación, ideal para auditoría y seguimiento de cambios.

**Descripción detallada:**

Se abandona la tabla en favor de un **timeline-registry**: los equipos se agrupan por período de actividad ("Hoy", "Esta semana", "Este mes", "Anteriores"). Cada grupo es un acordeón colapsable. Dentro de cada grupo, cada equipo se representa como un **timeline-item** con: punto de estado (círculo coloreado), nombre del equipo, código, tipo de cambio (ej: "Estado cambiado a Mantenimiento", "Asignado a Juan Pérez"), usuario que realizó el cambio, y timestamp relativo.

Las estadísticas se muestran como un grid compacto de 4 mini-tarjetas arriba (similar al actual pero más pequeño). Los filtros (ubicación, estado, búsqueda) se mantienen como una barra plegable. Un toggle "Timeline / Tabla" permite a los usuarios cambiar a la vista tradicional si la necesitan. Las acciones CRUD siguen siendo modales.

Para implementar la línea de tiempo se necesita que el backend ya registre actividad (asumiendo que `equipos.php` expone o expondrá logs). Si no hay logs, se puede simular agrupando por `updated_at` del equipo y mostrando el status actual como el "último evento conocido". El diseño visual usa los estilos `.timeline`, `.timeline-item`, `.timeline-dot` y `.timeline-content` ya definidos en `app.css`.

**Decisiones clave:**
- La línea de tiempo ya tiene estilos predefinidos en app.css (`.timeline-*`)
- Auditable: cada cambio de estado queda registrado visualmente
- El acordeón evita scroll infinito en equipos inactivos

**Trade-offs:**
| Ganancia | Sacrificio |
|---|---|
| Excelente para seguimiento y auditoría | Pobre para búsqueda de equipos específicos |
| Aprovecha estilos existentes de app.css | Depende de datos de actividad (timestamps) |
| Visualmente único en el sistema | No escala a miles de equipos |

**Complejidad:** Media  
**Riesgos:** Sin datos de auditoría en el backend, la línea de tiempo sería artificial; requiere un endpoint adicional o asumir `updated_at`.

---

## Propuesta 6 — «Explorador Jerárquico / Árbol + Tarjetas» (Probabilidad: ~0.03)

**Resumen:** Navegación tipo explorador de archivos: panel izquierdo con árbol de ubicaciones (Sedes → Áreas), panel central con tarjetas de equipos de la ubicación seleccionada, más vista de detalle bajo demanda.

**Descripción detallada:**

Tres paneles: **Izquierdo (tree-panel, 220px):** árbol colapsable de ubicaciones usando la jerarquía de `$hierarchyData['sedes']` y `$hierarchyData['areas']` ya disponible en el PHP. Cada nodo muestra un conteo de equipos en esa ubicación. **Central (cards-panel, flexible):** grilla responsive de tarjetas de equipos (`grid-template-columns: repeat(auto-fill, minmax(280px, 1fr))`) para la ubicación seleccionada, con un pequeño breadcrumb arriba ("Todas → Sede Central → Sistemas"). **Panel de detalle:** modal o slide-in que se abre al hacer clic en una tarjeta.

Las tarjetas (`equipo-card`) son más visuales que la tabla: muestran un icono grande del tipo de equipo (PC, LAPTOP, IMPRESORA, etc.), nombre, código patrimonial, serial, estado como badge grande y un botón de acciones (tres puntos). Al hacer hover, la tarjeta se eleva (`box-shadow` + `translateY`).

Los filtros globales (estado, búsqueda) se ubican como una barra sobre el panel central. Las estadísticas se muestran en un mini-grid horizontal compacto en el header del panel central. La navegación por árbol actualiza el panel central vía AJAX con el mismo endpoint `equipos.php?action=list&location_id=X`.

**Decisiones clave:**
- La jerarquía de ubicaciones ya existe en `$hierarchyData` (nada nuevo que implementar)
- Navegación natural para usuarios municipales que piensan en términos de sedes/áreas
- Las tarjetas son más touch-friendly que las tablas para mobile
- Sin cambios en el backend: solo se pasa `location_id` como filtro

**Trade-offs:**
| Ganancia | Sacrificio |
|---|---|
| Metáfora familiar (explorador de archivos) | No hay vista de "todos los equipos" sin seleccionar |
| Ideal para ubicaciones con muchos equipos | Árbol puede crecer y necesitar scroll |
| Navegación sin modales para el día a día | Más clicks para llegar a un equipo específico |

**Complejidad:** Alta  
**Riesgos:** Estado del árbol debe persistir entre recargas; el breadcrumb debe sincronizarse con la altura del árbol; en mobile los 3 paneles compiten por espacio.

---

## Verificación de Diversidad

| Dimensión | Prop1 | Prop2 | Prop3 | Prop4 | Prop5 | Prop6 |
|---|---|---|---|---|---|---|
| **Densidad** | Compacta (tabla) | Compacta (tabla+) | Espaciosa (cards) | Mixta (lista+detalle) | Espaciosa (timeline) | Espaciosa (cards) |
| **Interacción** | Modal CRUD | Sort + toolbar | Drag & drop | Master-detail inline | Acordeón timeline | Árbol + cards |
| **Info. architecture** | Lista plana | Lista plana ordenable | Columnas kanban | Lista + detalle | Temporal (agrupado) | Jerárquico (árbol) |
| **Metáfora** | Dashboard | Hoja de cálculo | Tablero ágil | Cliente email | Bitácora | Explorador archivos |
| **Breakpoint crítico** | 480px colapsa tabla | 640px bottom sheet | 768px kanban → tabla | 1024px stack nav | 640px acordeón | 768px árbol → dropdown |
| **Riesgo principal** | Mobile table scroll | Sort/API sync | DnD touch | Panel stacking | Sin audit logs | Árbol profundo |

Las 6 propuestas son **genuinamente diferentes**: ninguna es una variación menor de otra. Cubren desde refactorización conservadora (P1) hasta exploración radical (P6), pasando por optimización de datos (P2), visual (P3), interacción (P4) y navegación (P5, P6).

---

## Recomendación para Implementación Inmediata

Si se elige un único enfoque para la implementación final en `equipos.php`, la **Propuesta 1 (Dashboard Compacto)** ofrece el mejor ratio de valor/esfuerzo: migra todo el código a usar los tokens de app.css, elimina estilos/JS inline, preserva la experiencia de usuario existente y sienta las bases para evolucionar hacia cualquiera de las otras propuestas en el futuro sin deuda técnica.
