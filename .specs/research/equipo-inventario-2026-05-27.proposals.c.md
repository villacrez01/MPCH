# Equipos Inventory View — Redesign Proposals

**Project:** Sistema OTI — Admin Equipment Inventory
**Date:** 2026-05-27
**Context:** Single-file PHP view (393 lines) with inline `<style>`/`<script>`, raw SVG icons (not Lucide), and imperative DOM manipulation. Must be rewritten to use `app.css` design tokens, Lucide CDN icons, existing component classes, and modern JavaScript while preserving all backend API contracts.

---

## Step 1: Problem Decomposition

### Core Problem
The current `equipos.php` is a monolithic, hard-to-maintain file that mixes presentation logic, inline styles, business logic, and imperative JavaScript. It does not leverage the project's existing CSS framework (`app.css`), uses raw SVG instead of the standard Lucide icon set, and has no clear separation between data fetching, rendering, and interaction handling.

### Key Constraints
1. **Backend immutability** — `app/api/equipos.php` and `app/Models/Equipment.php` must not change
2. **Partial shell** — `head.php`, `sidebar.php`, `header.php`, `footer.php` provide the page wrapper
3. **Lucide icons** — must use `<i data-lucide="icon-name">` (loaded via CDN in head.php)
4. **CSS variables** — all styling must use existing `--primary`, `--bg-card`, `--text-primary`, etc.
5. **API contract** — `?action=list` returns `{equipos[], stats{}, total, page, page_size}`
6. **PHP 8.2.12** — no modern JS frameworks allowed; vanilla JS only
7. **Spanish UI** — all user-facing strings in Spanish

### Subproblems Any Solution Must Address
1. **Stats grid** — 4 cards (total/activos/mantenimiento/inactivos) with semantic colors
2. **Filter system** — location select (hierarchy optgroups), status select, search with debounce
3. **Equipment table** — 8 columns with responsive overflow, dynamic row rendering
4. **Action system** — view/edit buttons + 3-dot dropdown (deactivate/reactivate/delete-permanent)
5. **CRUD modals** — crear-equipo, ver-equipo, editar-equipo, desactivar-equipo, eliminar-permanent-equipo
6. **Toast notifications** — success/error/warning with auto-dismiss
7. **Pagination** — API-driven with page/page_size params
8. **Real-time updates** — integration with existing realtime.js

### Evaluation Criteria
- **Maintainability** — separation of concerns, readable structure
- **Performance** — efficient rendering, no layout thrashing
- **Mobile responsiveness** — table overflow, stacked modals, card breakpoints
- **Consistency** — uses same patterns as other admin views in the project
- **Accessibility** — keyboard navigation, screen reader support, focus management
- **Error resilience** — graceful degradation on API failure

---

## Step 2: Solution Space Mapping

### Architecture Dimensions
| Dimension | Range |
|---|---|
| **JS organization** | Monolithic `<script>` block → External module pattern → IIFE namespaced objects |
| **Rendering strategy** | InnerHTML concatenation → Template literals → DOM API → Virtual DOM via htmx |
| **CSS approach** | All inline → Component classes from app.css → Utility-first |
| **Modal strategy** | CSS visibility toggles → Dynamic DOM injection → Portal pattern |
| **Data flow** | Imperative fetch → Async state object → Reactive proxies |
| **Table approach** | Static table → Horizontal scroll → Card grid on mobile → Dual-pane |

### Trade-off Axes
- **Simplicity vs. Flexibility** — flat code is easy to write but hard to extend
- **Consistency vs. Innovation** — matching existing patterns vs. introducing better ones
- **Desktop power vs. Mobile UX** — data-dense table vs. card-based exploration
- **Upfront structure vs. Speed of change** — architecting now vs. refactoring later

---

## Step 3: Six High-Level Approaches

---

### Approach A: "Component-Class Refactor" (Conservative)
**Summary:** Keep the current monolithic structure but replace all inline styles with `app.css` component classes, swap raw SVGs for Lucide icons, and modernize the JS with `async/await` and a simple state object.

**Probability:** 0.85  
**Complexity:** Low  
**Risk:** Low

#### Description
This is the minimum-viable redesign. The file retains its single-PHP-file structure with one `<style>` block (removed) and one `<script>` block (refactored). The visual layout stays identical: stats grid (4-column), filters row, table card, modals. Every raw SVG is replaced with `<i data-lucide="icon-name">`. Every CSS class is replaced with the corresponding `app.css` class: `.stat-card.primary`, `.action-btn.sm.view`, `.action-dd`, `.toast.toast--success`, `.modal-overlay`, `.modal.large`, `.pagination-container`, etc.

The JavaScript is restructured into an `EquiposApp` object with methods: `init()`, `fetchData()`, `renderTable()`, `renderStats()`, `openModal()`, `closeModal()`, `showToast()`. Internal state (current filters, page, timeout ID) lives as object properties. `fetch/async-await` replaces the `.then()` chains. A single `render()` method orchestrates both stats and table updates.

Modals load their content from hidden `<template>` elements in the HTML rather than building HTML strings in JS. The form data for create/edit is serialized via `new FormData(form)` consistently. The action dropdown positioning uses CSS `position: absolute` inside `position: relative` cells instead of the current `position: fixed` JS math.

#### Key Design Decisions
1. Use `<template>` tags for modal content — avoids HTML-in-JS strings
2. One `render()` function — single entry point for UI updates
3. State object — filters, pagination, and data live in one place
4. Existing `app.css` classes — zero new CSS beyond what's already there

#### Trade-offs
- **Gain:** Fastest path to production; lowest risk; maximum reuse of existing patterns
- **Sacrifice:** Still a monolithic file; JS/DOM structure is not truly modular; mobiles get the exact same table (just scrollable)

#### Risks
- Existing table-responsive media queries in `app.css` at 1024px may need verification
- Template elements require careful escaping for user-supplied values

---

### Approach B: "Card-First Responsive" (Mobile-Priority)
**Summary:** Replace the table with a responsive card grid on mobile (<768px) and a horizontally scrollable table on desktop, using CSS Grid and `@container` queries if available.

**Probability:** 0.82  
**Complexity:** Medium  
**Risk:** Low-Medium

#### Description
This approach introduces a dual-presentation strategy: on viewports ≥768px, the equipment list renders as a traditional table with 8 columns using `overflow-x: auto` on the wrapper. On viewports <768px, each equipment row becomes a visually rich card with labeled fields, status badge, and action buttons laid out in a 2-column grid.

The PHP file structure is identical to Approach A, but the `renderTable()` JS function generates different markup based on `window.innerWidth` (or a CSS `display` toggle via two parallel containers). The cards show: name + code as the title row, serial + type in a 2-col grid, location + user below, and a footer row with status + actions. This gives mobile users a tappable, readable interface instead of a tiny horizontally-scrolling table.

The mobile action dropdown becomes a full-bottom sheet (or a simple row of icon buttons) for better thumb reachability. Stats grid collapses to 2-column on tablet and 1-column on phone, with smaller icons and tighter padding as defined in existing `app.css` media queries.

#### Key Design Decisions
1. Two rendering paths — one for table, one for cards, toggled by a single `isMobile` check
2. Cards use `app.css` existing `.equipo-item` / `.equipo-icon` / `.equipo-info` patterns from the dashboard
3. On tablet (768-1024px), show a condensed 5-column table (hide serial, type, user)

#### Trade-offs
- **Gain:** Excellent mobile UX; readable on any device; progressive enhancement
- **Sacrifice:** Double rendering logic; more JS code; potential for visual inconsistency between table and card views

#### Risks
- JS-based breakpoint detection can cause flash on resize; CSS `matchMedia` listener mitigates this
- Two sets of markup to maintain if the data schema changes
- Table-to-card transition may feel jarring without animation

---

### Approach C: "Skeleton-First with Optimistic Updates" (UX-Focused)
**Summary:** Introduce skeleton loading screens for every async operation, optimistic UI updates for status changes (deactivate/reactivate/delete), and a "toast undo" pattern for destructive actions.

**Probability:** 0.80  
**Complexity:** Medium-High  
**Risk:** Medium

#### Description
This approach prioritizes perceived performance and user feedback. Every data-fetching state shows skeleton placeholders using the existing `app.css` skeleton classes (`.skeleton`, `.skeleton-card`, `.skeleton-text`, `.skeleton-stat`). The stats grid shows pulsing gray rectangles, the table body shows 5 skeleton rows, and modals show skeleton forms while data loads.

When a user performs an action (deactivate, reactivate, delete), the UI updates **immediately** without waiting for the server response. The row's status badge flips, the action dropdown items change, and a toast appears with an "Deshacer" (Undo) button. If the server call fails, the UI reverts to the previous state and shows an error toast. This requires an `actionHistory` stack in the state object and a rollback mechanism.

The filter section gets a "loading" overlay (not a full-page spinner) that dims the table area while preserving scroll position during data refresh. A small "Última actualización: hace X segundos" timestamp appears in the table header.

#### Key Design Decisions
1. All async states are explicit: `idle → loading → success | error`
2. Optimistic updates use a snapshot pattern: save previous state, apply, rollback on failure
3. Skeleton uses `app.css` `.skeleton` / `.skeleton-shimmer` animation

#### Trade-offs
- **Gain:** Feels fast and polished; user gets immediate feedback; undo reduces anxiety about mistakes
- **Sacrifice:** Significantly more JS code; complex state management; rollback logic is error-prone

#### Risks
- Optimistic updates can cause data inconsistency if multiple users operate simultaneously
- Skeleton loading adds visual noise if requests complete quickly (<300ms)
- Undo window timeout must be carefully chosen (5-8s recommended)

---

### Approach D: "Data-Table Pro" (Enterprise Grid)
**Summary:** Replace the simple table with a feature-rich data grid: sortable columns, column visibility toggles, bulk selection, export button, and a compact "density" toggle — all within the existing CSS system.

**Probability:** 0.08  
**Complexity:** High  
**Risk:** High

#### Description
This transforms the equipment table into an enterprise-style grid. Columns are sortable (click header to toggle asc/desc, visual indicator via Lucide `arrow-up`/`arrow-down`). A "Configurar columnas" button opens a small popover where users check/uncheck visible columns (stored in `localStorage`). A "Vista compacta" toggle reduces row padding and font size for dense data review.

A bulk action bar appears when multiple rows are selected via checkboxes: "Seleccionados: 3 | Desactivar | Reactivar | Eliminar". An "Exportar CSV" button streams the current filtered data as a downloadable file (via a hidden `<a>` with data URI). The table header is sticky within the card using `position: sticky; top: 0`.

Pagination includes a "Resultados por página" selector (10/25/50/100) stored in session state. The page info reads "Mostrando 1-20 de 156 equipos" with a total count link. Current sort column and direction are sent to the API as `&sort_by=name&sort_dir=asc` (requires minimal API change, but constraint says no backend changes — so sorting is done client-side on the fetched data).

#### Key Design Decisions
1. Client-side sorting — avoids backend changes; works on current page's data only
2. `localStorage` for column preferences — persistent across sessions
3. Checkbox state managed via `Set` object for O(1) add/remove/has

#### Trade-offs
- **Gain:** Power-user features; competitive with commercial tools; column flexibility
- **Sacrifice:** Heavy JS payload; client-side sort only works on one page; bulk actions add surface area for bugs

#### Risks
- Client-side sorting over only 20 rows is not "real" sorting — users may expect server-side sort
- Bulk select "select all" across pages is complex without server support
- Column toggle may break layout for narrow columns

---

### Approach E: "htmx Hypermedia" (Declarative Dynamic)
**Summary:** Replace all custom JavaScript with htmx attributes, letting the server render HTML fragments for table rows, modals, and stats — turning the PHP backend into a partial-rendering engine.

**Probability:** 0.05  
**Complexity:** Medium  
**Risk:** High

#### Description
This approach is a radical departure from the current architecture. Instead of fetching JSON and rendering HTML in JS, the server returns HTML fragments. The equipos.php view becomes mostly static HTML with `hx-get`, `hx-post`, `hx-trigger`, `hx-target`, `hx-swap` attributes.

The stats grid uses `hx-get="app/api/equipos.php?action=list-stats"` with `hx-trigger="load, every 30s"`. The table body is `<tbody hx-get="app/api/equipos.php?action=list-html&page=1" hx-trigger="load" hx-swap="innerHTML">`. Filter changes send `hx-get` with `hx-include="#filtros"`. Form submissions use `hx-post` with `hx-target="#toast-container"` and trigger a `hx-trigger="from:body"` event to refresh the table.

This means adding new server endpoints that return HTML (not JSON) — technically a backend change, but only adding new controller methods, not modifying the model. Or alternatively, adding a query parameter `?format=html` to the existing API.

Modals open via `hx-get` into a modal container. Action dropdowns become server-rendered menus. Toast messages can be server-side rendered and injected.

#### Key Design Decisions
1. Server-rendered HTML fragments — zero client-side rendering logic
2. Existing API can be extended with `?format=html` parameter
3. htmx triggers replace all event listeners

#### Trade-offs
- **Gain:** Almost no hand-written JS; HTML is the "programming language"; accessibility built-in
- **Sacrifice:** Requires backend changes (new HTML endpoints); heavier network payloads (HTML vs JSON); htmx is an additional external dependency

#### Risks
- htmx CDN must match project's CDN loading strategy
- Real-time updates with realtime.js would need integration with htmx's event system
- Server load increases because every interaction hits the backend (even sorting/filtering a small set)
- Learning curve for team unfamiliar with hypermedia architecture

---

### Approach F: "Virtual-Scroll Command-Bar" (Power-User Terminal)
**Summary:** Replace the traditional inventory view with a search-centric, command-palette-driven interface: focused keyboard-first interaction, virtual scrolling for thousands of rows, and a quick-action command bar (`Ctrl+K`) for navigation and operations.

**Probability:** 0.03  
**Complexity:** High  
**Risk:** Very High

#### Description
This reimagines the inventory page as a power-user tool. The dominant UI element is a **global search bar** (similar to the existing `.search-modal` in `app.css`) that opens with `Ctrl+K` or `/`. Typing searches across name, serial, code, and user with instant results rendered in a virtual list (only DOM nodes for visible rows + buffer).

Below the search bar, the page shows a compact **summary strip** (stats as inline badges with counts), then a **virtual-scroll container** using `IntersectionObserver` and a fixed-height row pool. Only ~20 rows are in the DOM at any time, but scrolling feels infinite (the container has a tall spacer div). This enables smooth browsing of thousands of equipment records without pagination overhead.

Every action (create, view, edit, deactivate, delete) is accessible via keyboard shortcuts displayed in the command palette. The palette shows available commands: `N` (nuevo), `V` (ver seleccionado), `E` (editar), `D` (desactivar), `Del` (eliminar), `R` (reactivar). A status bar at the bottom shows "156 equipos • Filtro: activos • Seleccionados: 3".

#### Key Design Decisions
1. Virtual scrolling — custom implementation using `IntersectionObserver` or a lightweight library
2. Command palette as the primary interaction mode — keyboard over mouse
3. URL hash for navigation state — `#status=active&search=laptop` for shareable filters

#### Trade-offs
- **Gain:** Handles thousands of records with no DOM overhead; extremely fast for keyboard users; novel, memorable UX
- **Sacrifice:** Unconventional; high initial complexity; non-obvious for casual users; custom virtual scroll is bug-prone

#### Risks
- `Ctrl+K` conflicts with browser shortcuts on some platforms
- Virtual scroll with variable-height rows (due to action buttons) is significantly harder
- Team maintenance burden for a custom virtual-scroll implementation
- Accessibility challenges with virtual DOM (focus management, screen reader navigation)

---

## Step 4: Diversity Verification

| Dimension | A | B | C | D | E | F |
|---|---|---|---|---|---|---|
| **JS complexity** | Low | Med | Med-High | High | Very Low | Very High |
| **New CSS needed** | None | Moderate | Minimal | Minimal | None | Moderate |
| **Mobile UX** | Fair | Excellent | Good | Fair | Good | Poor |
| **Desktop UX** | Good | Good | Good | Excellent | Good | Excellent |
| **Backend changes** | None | None | None | None | Required | None |
| **Risk level** | Low | Low-Med | Med | High | High | Very High |
| **Dev time estimate** | 4-6h | 8-12h | 10-16h | 12-20h | 6-10h | 20-40h |
| **Team familiarity** | High | High | Med | Med | Low | Very Low |

Approaches A, B, C cluster around conservative-to-moderate changes with high probability of success (0.80–0.85). Approaches D, E, F explore the high-risk/high-reward regions: D for feature density, E for architectural purity, F for radical UX innovation. All six are genuinely different — they differ in JS strategy (imperative vs declarative vs virtual-DOM), CSS strategy (reuse vs dual-render vs minimal), and interaction paradigm (form-based vs search-centric vs server-driven).

### Recommendation for Next Phase
Approach **B (Card-First Responsive)** combined with Approach **A's class refactoring** offers the best balance of UX improvement, maintainability gain, and implementation safety. Start with Approach A as the base structural refactor, then layer B's responsive card pattern on mobile breakpoints, and optionally add C's skeleton loading for polish.
