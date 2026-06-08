# Propuestas de Rediseño — `usuarios.php`

**Fecha:** 2026-05-27  
**Archivo:** `app/Views/admin/usuarios.php` (1059 líneas)  
**Contexto:** Sistema OTI municipal — PHP 8.2, PostgreSQL, CSS custom properties (`app.css`), Lucide icons, sin build tools, sin npm.

---

## P1 — IIFE Namespace + SSR / `data-action` Delegation + `<template>` Modals + Closure State

### Dimensiones
- **A1** — IIFE namespace (`const UsuariosApp = {}`)
- **B1** — SSR table (PHP `getAllWithDetails()`), AJAX solo para modals
- **C3** — Modales via `<template>` (como equipos.php)
- **D1** — Closure variables (`let selectedUsers = []`, `let currentUserId = null`)

### Arquitectura
Envolver todo en IIFE `;(function() { 'use strict'; const UsuariosApp = { ... }; })();` para aislar del scope global. Migrar todos los `onclick="..."` inline a `data-action` attributes. Un solo event listener por delegación en `#main-content` (`click`) y en `document` (`keydown` para ESC/focus trap). Los modales se definen como `<template id="modal-create-user">...</template>` en el HTML estático y se clonan/insertan al abrir. Las variables de estado (`selectedUsers`, `searchTimeout`, `currentPermissionsUserId`) son closures dentro del IIFE. El filtrado sigue siendo client-side sobre las filas SSR (sin llamada AJAX). CSV export y dropdowns se mueven a `data-action`.

### Mejoras clave
- Elimina todas las funciones globales y manejadores inline (1059 → ~700 líneas)
- `<template>` + `data-action` alineado con el patrón de `equipos.php`
- Modal confirm en lugar de `confirm()` nativo (modal de desactivar/eliminar)
- ESC cierra modal, focus trap dentro del modal, `data-modal` para identificar overlays
- `lucide.createIcons()` se llama una sola vez post-clonado de template
- Botón close con `<i data-lucide="x">` en vez de `×`
- Se añade `prefers-reduced-motion` via `matchMedia` para desactivar animaciones

### Lo que se conserva
- SSR total de la tabla (`$usuariosData`), stats, filtros location/status/role
- `getAllWithDetails()`, `getStats()`, `getLocationsHierarchy()` sin cambios backend
- Estructura PHP de partials (`head`, `sidebar`, `header`, `footer`)
- `realtime.js` incluido al final
- Funcionalidad completa: 5 modales, bulk actions, permisos, CSV export, equipment assign/unassign

### Trade-offs
| Pros | Contras |
|------|---------|
| Mínimo riesgo de regresión (la tabla sigue siendo SSR) | No hay paginación AJAX — tabla sigue cargando todos los usuarios |
| Patrón ya probado en `equipos.php` (consistencia) | Las mutaciones siguen usando `location.reload()` (sin update reactivo) |
| Migración sencilla, cambios localizados en JS | Si hay 1000+ usuarios, SSR se vuelve lento |
| Sin cambios en backend API | Los filtros client-side no escalan a datasets grandes |

### Riesgo y complejidad
- **Probabilidad de ser la mejor:** 0.50
- **Complejidad:** Baja
- **Riesgo:** Bajo

---

## P2 — Clase Controller + Hybrid SSR/AJAX + Modales JS-render + State Object

### Dimensiones
- **A2** — Class-based (`class UsuariosController { constructor() { ... } }`)
- **B2** — Híbrido: SSR inicial + AJAX pagination en futuras cargas
- **C2** — Modales renderizados desde JS (template literals, sin `<template>`)
- **D2** — `state` object con `setState()` + `render()` pattern

### Arquitectura
Definir `class UsuariosController` con `state = { users: [], stats: {}, filters: {...}, pagination: { page: 1, page_size: 25, total: 0 }, loading: false }`. En `init()`, los datos SSR iniciales se inyectan via `data-initial` JSON en un `<script>` o atributo `data-initial="..."` en el `#main-content`. El controller renderiza la tabla desde `state.users` (JS template literals), no desde el HTML PHP. Los filtros disparan `this.loadData(1)` que llama a `app/api/usuarios.php?action=list&page=N&filters=...` (requiere agregar `action=list` con paginación al API existente). Los modales se construyen dinámicamente en JS con `innerHTML` y se insertan al `body`; se cachean en un `Map` después de la primera creación. `setState(partial)` hace merge, dispara `render()` que actualiza tabla + stats + paginación sin `location.reload()`. Cada modal abierto guarda `_previousFocus` y lo restaura al cerrar. Se implementa `prefers-reduced-motion` en el state inicial.

### Mejoras clave
- Sin `location.reload()` en ninguna operación — todo se actualiza vía `render()`
- Paginación server-side: 25 usuarios por página vs. los 1000+ actuales
- Skeleton loading en stats y tabla mientras carga (`class="skeleton-row"` ya definido en equipos.php)
- Contadores animados en stats (`requestAnimationFrame` de 0 al valor final)
- Modales con focus trap, ESC, y restauración de foco (accesibilidad completa)
- Estado centralizado: una sola fuente de verdad para usuarios, filtros, paginación
- Las mutaciones (create/delete/update) refetch la página actual sin recargar

### Lo que se conserva
- Backend API `app/api/usuarios.php` sin cambios (se reusa `action=get`, `action=create`, etc.)
- Se añade solo `action=list` al API para paginación filtrada (cambio mínimo)
- Stats iniciales vía `$initialStats` (como equipos.php)
- CSS classes existentes: `stat-card`, `filter-select`, `search-input`, `user-cell`, `status-badge`, `role-badge`, etc.
- Realtime.js sigue funcionando (escucha eventos independientes)
- CSV export refactorizado a método del controller

### Trade-offs
| Pros | Contras |
|------|---------|
| Experiencia reactiva sin recargas de página | Requiere añadir `action=list` al backend API (cambio pequeño pero necesario) |
| Paginación real para escalabilidad | Mayor volumen de JS (~500 líneas en la clase) |
| Skeleton + contadores animados mejoran UX percibida | Más complejo de debuggear que P1 |
| Alineado con patrón equipos.php pero mejorado con clase | Mayor riesgo de regresión en la lógica de filtros |
| Accesibilidad mejorada (focus management, ESC, reduced-motion) | Las template literals hacen más difícil encontrar markup (vs. `<template>` HTML) |

### Riesgo y complejidad
- **Probabilidad de ser la mejor:** 0.30
- **Complejidad:** Media
- **Riesgo:** Medio

---

## P3 — Progressive Enhancement (SSR-first + Event Delegation + DOM-as-State)

### Dimensiones
- **A3** — Progressive enhancement: `<script defer>` con event delegation simple, sin namespace ni clase
- **B3** — SSR total (sin AJAX para tabla), AJAX solo para modals (como P1 pero más ligero)
- **C1** — Modales PHP estáticos (como ahora, pero limpiados)
- **D3** — DOM como estado (`data-*` attributes, clases, textContent)

### Arquitectura
Enfoque minimalista: mantener el HTML SSR casi intacto, pero reemplazar todos los `onclick`, `onchange`, `onkeyup` inline por un solo event listener en `document.addEventListener('click', handleAction)` con `e.target.closest('[data-action]')`. Los modales permanecen como HTML estático en el PHP (no `<template>`, no JS-render), solo se limpia su markup: los `×` se cambian a `<button data-action="close-modal" data-modal="..."><i data-lucide="x"></i></button>` y los botones de confirmar usan `data-action="confirm-delete"`. No hay objeto state ni clase — todo se lee del DOM: `selectedUsers` se obtiene de `.user-checkbox:checked`, `currentUserId` de `data-user-id` en el `tr`, `sortDir` de `data-sort-dir` en el `<th>`. Las mutaciones siguen usando `location.reload()` (como ahora). Los `confirm()` se reemplazan por un modal de confirmación PHP estático reutilizable (un solo modal `#modal-confirm` con `data-action` que cambia el texto según el contexto). Accesibilidad mínima: ESC cierra modal via listener en `document`, no hay focus trap completo (solo `autofocus` en el primer input del modal). `prefers-reduced-motion` se aplica con una regla CSS `@media (prefers-reduced-motion: reduce) { * { animation-duration: 0.01ms !important; } }` en el `<style>` del footer.

### Mejoras clave
- Elimina todos los `onclick`/`onchange`/`onkeyup` → ~50 atributos eliminados
- Event delegation con `data-action` simple (sin switch complejo, solo `if/else if`)
- Modal de confirmación único reutilizable en vez de `confirm()` nativo
- Botones close con Lucide en vez de `×`
- Código JS mucho más corto (~200 líneas) y fácil de mantener
- Sin cambios en backend API ni en estructura HTML
- `lucide.createIcons()` una sola vez al final

### Lo que se conserva
- **Todo** el HTML PHP existente: modales, tabla SSR, stats, filtros, bulk bar
- `getAllWithDetails()`, `getStats()`, `getLocationsHierarchy()` — cero cambios
- `location.reload()` después de mutaciones (sin changes a la lógica de datos)
- Filtrado client-side sobre filas SSR (el mismo `applyFilters()` refactorizado)
- CSV export, dropdowns, debounce, toast — todo migrado a `data-action` pero idéntico en comportamiento
- `realtime.js` sin cambios
- Sin dependencias nuevas, sin cambios de build, sin npm

### Trade-offs
| Pros | Contras |
|------|---------|
| Riesgo mínimo: cambios puramente cosméticos en JS | Sigue recargando la página en cada mutación |
| Migración trivial (~2 horas de trabajo) | Sin paginación, sin skeleton, sin contadores animados |
| Código mucho más mantenible que el original | Sin state management — propenso a bugs si el DOM no refleja el estado real |
| Sin cambios backend de ningún tipo | Sin focus trap completo (solo ESC + autofocus) |
| Fácil de auditar y entender por cualquier dev | Accesibilidad limitada comparada con P2 |

### Riesgo y complejidad
- **Probabilidad de ser la mejor:** 0.20
- **Complejidad:** Baja
- **Riesgo:** Bajo

---

## Matriz Comparativa

| Aspecto | P1 (IIFE + SSR + Template) | P2 (Class + Hybrid + State) | P3 (SSR-first + DOM-state) |
|---------|---------------------------|-----------------------------|---------------------------|
| **JS Lines** | ~350 | ~500 | ~200 |
| **PHP Lines** | ~400 (modales a template) | ~300 (tabla dinámica) | ~600 (modales estáticos) |
| **Backend changes** | Ninguno | `action=list` (1 endpoint) | Ninguno |
| **location.reload()** | Sí | No | Sí |
| **Accesibilidad** | Media (focus trap) | Alta (focus trap + restore) | Baja (solo ESC) |
| **Skeleton / Anim stats** | No | Sí | No |
| **Paginación** | No | Sí (25/page) | No |
| **Consistencia con equipos.php** | Alta (mismo patrón IIFE + template) | Media (misma idea, clase propia) | Baja |
| **Tiempo estimado** | 4–6 h | 8–12 h | 2–3 h |
| **Probabilidad de ser best** | **0.50** | **0.30** | **0.20** |

## Recomendación

**P1** es la opción con mejor relación esfuerzo/impacto: elimina toda la deuda técnica de inline handlers, alinea el patrón con `equipos.php`, introduce `<template>` y event delegation, y mantiene el risk bajo al no tocar backend. P2 es la apuesta a futuro (escalabilidad, accesibilidad completa, UX reactiva) pero requiere más tiempo y un cambio mínimo en API. P3 es la solución "quick win" si solo se busca limpiar el código sin cambiar comportamiento.
