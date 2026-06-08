# Expansion: A1 — Dashboard Compacto

**Date:** 2026-05-27
**Target:** `app/Views/admin/equipos.php`
**Strategy:** Conservative table-first evolution — animated stat cards, Lucide icons, responsive filters, 5 CRUD modals, toast notifications, pagination, skeleton loading, all using existing `app.css` tokens.

---

## 1. PHP Structure

### Variables and Initialization (top of file)

```php
<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;

$isOtiAdmin = \App\Services\AuthService::isAdmin();

require_once __DIR__ . '/../../Models/Equipment.php';
require_once __DIR__ . '/../../Models/Location.php';
require_once __DIR__ . '/../../Models/User.php';

$statsData = \App\Models\Equipment::getStats();
$locationsData = \App\Models\Location::getAll();
$hierarchyData = \App\Models\User::getLocationsHierarchy();

$tituloPagina = 'Inventario de Equipos - Sistema OTI';
$paginaActual = 'admin-equipos';
?>
```

### Partial Includes

```php
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>
```

### CSRF Token

Available from `head.php` as:
```html
<meta name="csrf-token" content="<?= csrf_token() ?>">
```
Read from JS via: `document.querySelector('meta[name="csrf-token"]').getAttribute('content')`

### PHP-Rendered Static Content

The hierarchy `optgroup` elements for the location filter are rendered from `$hierarchyData['sedes']` and `$hierarchyData['areas']`. All modal footer buttons and static modal text are rendered in PHP.

---

## 2. HTML Structure (Complete)

### 2.1 Page Header

```html
<main id="main-content" class="main-content">
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Inventario de Equipos</h1>
            <p class="page-subtitle">Gesti&oacute;n de equipos tecnol&oacute;gicos</p>
        </div>
        <button type="button" class="btn-new" onclick="abrirCrearEquipo()">
            <i data-lucide="plus"></i>
            Nuevo Equipo
        </button>
    </div>
```

### 2.2 Stats Grid (4 stat-cards)

```html
<div class="stats-grid" id="stats-grid">
    <div class="stat-card primary">
        <div class="stat-icon primary"><i data-lucide="monitor" width="24" height="24"></i></div>
        <div class="stat-content">
            <div class="stat-value" id="eq-total">0</div>
            <div class="stat-label">Total Equipos</div>
        </div>
    </div>
    <div class="stat-card success">
        <div class="stat-icon success"><i data-lucide="check-circle" width="24" height="24"></i></div>
        <div class="stat-content">
            <div class="stat-value" id="eq-activos">0</div>
            <div class="stat-label">Activos</div>
        </div>
    </div>
    <div class="stat-card warning">
        <div class="stat-icon warning"><i data-lucide="wrench" width="24" height="24"></i></div>
        <div class="stat-content">
            <div class="stat-value" id="eq-mantenimiento">0</div>
            <div class="stat-label">En Mantenimiento</div>
        </div>
    </div>
    <div class="stat-card danger">
        <div class="stat-icon danger"><i data-lucide="x-circle" width="24" height="24"></i></div>
        <div class="stat-content">
            <div class="stat-value" id="eq-inactivos">0</div>
            <div class="stat-label">Inactivos</div>
        </div>
    </div>
</div>
```

### 2.3 Filters Section

```html
<div class="filters-section">
    <div class="filters-header">
        <span class="filters-title">
            <i data-lucide="filter" width="16" height="16"></i>
            Filtros de b&uacute;squeda
        </span>
        <button type="button" class="clear-filters-btn" onclick="clearFilters()">
            <i data-lucide="x" width="14" height="14"></i>
            Limpiar
        </button>
    </div>
    <div class="filters-row">
        <div class="filter-group">
            <label class="filter-label">Ubicaci&oacute;n</label>
            <select id="filtro-ubicacion" class="filter-select" onchange="cargarEquipos()">
                <option value="">Todas las ubicaciones</option>
                <optgroup label="Sedes">
                    <?php foreach ($hierarchyData['sedes'] as $sede): ?>
                    <option value="<?= $sede['id'] ?>"><?= htmlspecialchars($sede['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="&Aacute;reas">
                    <?php foreach ($hierarchyData['areas'] as $area): ?>
                    <option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Estado</label>
            <select id="filtro-estado" class="filter-select" onchange="cargarEquipos()">
                <option value="">Todos los estados</option>
                <option value="active">Activos</option>
                <option value="maintenance">En Mantenimiento</option>
                <option value="inactive">Inactivos</option>
                <option value="retired">Retirados</option>
            </select>
        </div>
        <div class="filter-group search-filter">
            <label class="filter-label">Buscar</label>
            <div class="search-wrapper">
                <i data-lucide="search" class="search-icon" width="18" height="18"></i>
                <input type="text" class="search-input" id="search-equipos"
                       placeholder="Buscar por nombre, serial o c&oacute;digo..."
                       onkeyup="debounceSearch()">
            </div>
        </div>
    </div>
</div>
```

### 2.4 Table Card

```html
<div class="table-card">
    <div class="table-header">
        <h3 class="table-title">
            <i data-lucide="monitor" width="18" height="18"></i>
            Lista de Equipos
        </h3>
        <span class="table-count" id="equipos-count">0 equipos</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>C&oacute;digo</th>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Serial</th>
                <th>Ubicaci&oacute;n</th>
                <th>Usuario</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="equipos-table-body"></tbody>
    </table>
    <div class="pagination-container" id="pagination-container"></div>
</div>
```

### 2.5 Toast Container (before modals)

```html
<div class="toast-container" id="toast-container"></div>
```

### 2.6 All 5 Modals

#### 2.6.1 View Modal (`ver-equipo`)

```html
<div class="modal-overlay" id="modal-ver-equipo">
    <div class="modal large">
        <div class="modal-header">
            <h3 class="modal-title"><i data-lucide="info" width="20" height="20"></i>Detalles del Equipo</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('ver-equipo')">
                <i data-lucide="x" width="20" height="20"></i>
            </button>
        </div>
        <div class="modal-body" id="ver-contenido-equipo"></div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="cerrarModal('ver-equipo')">Cerrar</button>
        </div>
    </div>
</div>
```

#### 2.6.2 Edit Modal (`editar-equipo`)

```html
<div class="modal-overlay" id="modal-editar-equipo">
    <div class="modal large">
        <div class="modal-header">
            <h3 class="modal-title"><i data-lucide="pencil" width="20" height="20"></i>Editar Equipo</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('editar-equipo')">
                <i data-lucide="x" width="20" height="20"></i>
            </button>
        </div>
        <form id="form-editar-equipo" onsubmit="guardarEquipo(event)">
            <div class="modal-body" id="editar-contenido-equipo"></div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModal('editar-equipo')">Cancelar</button>
                <button type="submit" class="btn-submit">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
```

#### 2.6.3 Create Modal (`crear-equipo`)

```html
<div class="modal-overlay" id="modal-crear-equipo">
    <div class="modal large">
        <div class="modal-header">
            <h3 class="modal-title"><i data-lucide="plus" width="20" height="20"></i>Crear Nuevo Equipo</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('crear-equipo')">
                <i data-lucide="x" width="20" height="20"></i>
            </button>
        </div>
        <form id="form-crear-equipo" onsubmit="crearEquipo(event)">
            <div class="modal-body" id="crear-contenido-equipo">
                <p class="loading-text">Cargando formulario...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModal('crear-equipo')">Cancelar</button>
                <button type="submit" class="btn-submit">Crear Equipo</button>
            </div>
        </form>
    </div>
</div>
```

#### 2.6.4 Deactivate Modal (`desactivar-equipo`)

```html
<div class="modal-overlay" id="modal-desactivar-equipo">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title"><i data-lucide="alert-triangle" width="20" height="20"></i>Desactivar Equipo</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('desactivar-equipo')">
                <i data-lucide="x" width="20" height="20"></i>
            </button>
        </div>
        <div class="modal-body">
            <p class="modal-desc">Al desactivar se eliminar&aacute;n las asignaciones. &iquest;Continuar?</p>
            <div class="form-group">
                <label class="form-label">Motivo <span class="required">*</span></label>
                <textarea id="deactivate-equipo-reason" class="form-textarea" rows="3"
                          placeholder="Ej: Equipo obsoleto, da&ntilde;ado..."></textarea>
            </div>
            <input type="hidden" id="deactivate-equipo-id">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="cerrarModal('desactivar-equipo')">Cancelar</button>
            <button type="button" class="btn-submit" onclick="confirmarDesactivar()"
                    style="background-color:var(--warning)">Confirmar</button>
        </div>
    </div>
</div>
```

#### 2.6.5 Permanent Delete Modal (`eliminar-permanent-equipo`)

```html
<div class="modal-overlay" id="modal-eliminar-permanent-equipo">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title"><i data-lucide="trash-2" width="20" height="20"></i>Eliminar Permanentemente</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('eliminar-permanent-equipo')">
                <i data-lucide="x" width="20" height="20"></i>
            </button>
        </div>
        <div class="modal-body">
            <p class="modal-desc" style="color:var(--danger);">Esta acci&oacute;n NO se puede deshacer.</p>
            <div class="form-group">
                <label class="form-label">Motivo <span class="required">*</span></label>
                <textarea id="delete-permanent-equipo-reason" class="form-textarea" rows="3"
                          placeholder="Ej: Robado, perdido, baja definitiva..."></textarea>
            </div>
            <input type="hidden" id="delete-permanent-equipo-id">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="cerrarModal('eliminar-permanent-equipo')">Cancelar</button>
            <button type="button" class="btn-submit" onclick="confirmarEliminarPermanent()"
                    style="background-color:var(--danger)">Eliminar</button>
        </div>
    </div>
</div>
```

---

## 3. CSS (Inline `<style>` block)

Add inside `<style>` tags before the closing `</head>` or at the top of the page content. All classes use `app.css` tokens; no hardcoded colors.

```css
/* ── Stats Grid (override default 4-column) ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-md);
    margin-bottom: var(--space-xl);
}

.stat-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    padding: var(--space-lg);
    display: flex;
    align-items: center;
    gap: var(--space-md);
    text-decoration: none;
    color: inherit;
    transition: all var(--duration-slow) var(--ease-out);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    opacity: 0;
    transition: opacity var(--duration-normal) var(--ease-out);
}

.stat-card:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow-3);
    transform: translateY(-2px);
}

.stat-card:hover::before {
    opacity: 1;
}

.stat-card.primary::before { background: var(--primary); }
.stat-card.success::before { background: var(--success); }
.stat-card.warning::before { background: var(--warning); }
.stat-card.danger::before { background: var(--danger); }

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon.primary { background: rgba(99, 102, 241, 0.1); }
.stat-icon.primary svg { fill: var(--primary); }
.stat-icon.success { background: var(--success-soft); }
.stat-icon.success svg { fill: var(--success); }
.stat-icon.warning { background: var(--warning-soft); }
.stat-icon.warning svg { fill: var(--warning); }
.stat-icon.danger { background: var(--danger-soft); }
.stat-icon.danger svg { fill: var(--danger); }

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
    letter-spacing: -1px;
}

.stat-label {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 4px;
}

/* ── Animated counter keyframes ── */
@keyframes countUp {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}

.stat-value.animating {
    animation: countUp var(--duration-slower) var(--ease-out);
}

/* ── Filters Section ── */
.filters-section {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    padding: var(--space-md) var(--space-lg);
    margin-bottom: var(--space-lg);
    box-shadow: var(--shadow-1);
}

.filters-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--space-md);
}

.filters-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.filters-title svg {
    width: 16px;
    height: 16px;
    fill: var(--text-muted);
}

.clear-filters-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: transparent;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all var(--duration-fast) var(--ease-out);
    font-family: inherit;
}

.clear-filters-btn:hover {
    background: var(--danger-soft);
    border-color: var(--danger);
    color: var(--danger);
}

.clear-filters-btn svg {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
}

.filters-row {
    display: flex;
    gap: var(--space-md);
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-group.search-filter {
    flex: 1;
}

.filter-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.filter-select {
    padding: 10px 14px;
    background: var(--bg-main);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    font-size: 13px;
    font-family: inherit;
    color: var(--text-primary);
    cursor: pointer;
    min-width: 200px;
    transition: border-color var(--duration-fast) var(--ease-out);
    appearance: auto;
    -webkit-appearance: auto;
}

.filter-select:focus {
    border-color: var(--primary-light);
    outline: none;
    box-shadow: 0 0 0 3px var(--primary-glow);
}

.search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-wrapper .search-icon {
    position: absolute;
    left: 12px;
    pointer-events: none;
    color: var(--text-muted);
}

.search-input {
    width: 100%;
    padding: 10px 14px 10px 40px;
    background: var(--bg-main);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    font-size: 13px;
    font-family: inherit;
    color: var(--text-primary);
    transition: border-color var(--duration-fast) var(--ease-out);
}

.search-input:focus {
    border-color: var(--primary-light);
    outline: none;
    box-shadow: 0 0 0 3px var(--primary-glow);
}

.search-input::placeholder {
    color: var(--text-muted);
}

/* ── Table Card ── */
.table-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    box-shadow: var(--shadow-1);
    overflow: hidden;
}

.table-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-md) var(--space-lg);
    border-bottom: 1px solid var(--border-light);
}

.table-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    margin: 0;
}

.table-title svg {
    width: 18px;
    height: 18px;
    fill: var(--primary);
}

.table-count {
    font-size: 13px;
    color: var(--text-muted);
    font-weight: 500;
}

.table-card table {
    width: 100%;
    border-collapse: collapse;
}

.table-card thead th {
    padding: 12px var(--space-md);
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    text-align: left;
    background: var(--bg-main);
    border-bottom: 1px solid var(--border-light);
}

.table-card tbody td {
    padding: 12px var(--space-md);
    font-size: 13px;
    color: var(--text-primary);
    border-bottom: 1px solid var(--border-light);
    vertical-align: middle;
}

.table-card tbody tr:last-child td {
    border-bottom: none;
}

.table-card tbody tr:hover {
    background: var(--primary-soft);
}

/* ── Action Cell ── */
.action-cell {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: nowrap;
    position: relative;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-family: inherit;
    transition: all var(--duration-normal) var(--ease-out);
}

.action-btn.sm {
    width: 34px;
    height: 34px;
    padding: 0;
}

.action-btn.sm svg {
    width: 16px;
    height: 16px;
}

.action-btn.sm.view {
    background: #eef2ff;
    color: #4338ca;
}

.action-btn.sm.view:hover {
    background: #4338ca;
    color: #fff;
    box-shadow: 0 4px 14px rgba(67, 56, 202, 0.28);
    transform: translateY(-1px);
}

.action-btn.sm.edit {
    background: #fff7ed;
    color: #d97706;
}

.action-btn.sm.edit:hover {
    background: #d97706;
    color: #fff;
    box-shadow: 0 4px 14px rgba(217, 119, 6, 0.28);
    transform: translateY(-1px);
}

/* ── Action Dropdown ── */
.action-dd {
    position: relative;
    display: inline-block;
    z-index: var(--z-dropdown);
    overflow: visible;
}

.action-dd__btn {
    width: 34px;
    height: 34px;
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border-light);
    background: var(--bg-card);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--duration-fast) var(--ease-out);
}

.action-dd__btn:hover {
    border-color: var(--primary);
    background: var(--primary-soft);
}

.action-dd__btn svg {
    width: 18px;
    height: 18px;
    color: var(--text-secondary);
}

.action-dd__menu {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-5);
    min-width: 200px;
    z-index: var(--z-popover);
    overflow: hidden;
}

.action-dd__menu.show {
    display: block;
    animation: fadeIn var(--duration-normal) var(--ease-out);
}

.action-dd__item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 12px 16px;
    border: none;
    background: none;
    text-align: left;
    cursor: pointer;
    font-size: 13px;
    color: var(--text-primary);
    transition: background var(--duration-fast);
    font-family: inherit;
}

.action-dd__item:hover {
    background: var(--bg-hover);
}

.action-dd__item svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.action-dd__item--danger {
    color: var(--danger);
}

.action-dd__item--danger:hover {
    background: var(--danger-soft);
}

.action-dd__item--warning {
    color: var(--warning);
}

.action-dd__item--warning:hover {
    background: var(--warning-soft);
}

.action-dd__item--success {
    color: var(--success);
}

.action-dd__item--success:hover {
    background: var(--success-soft);
}

/* ── Status Badge ── */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: var(--radius-full);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.status-badge.active {
    background: var(--success-soft);
    color: var(--success);
}

.status-badge.maintenance {
    background: var(--warning-soft);
    color: var(--warning);
}

.status-badge.inactive {
    background: var(--info-soft);
    color: var(--info);
}

.status-badge.retired {
    background: var(--bg-hover);
    color: var(--text-muted);
}

.status-badge svg {
    width: 12px;
    height: 12px;
}

/* ── Modal System ── */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: var(--z-modal-backdrop);
    align-items: center;
    justify-content: center;
    padding: var(--space-lg);
}

.modal-overlay.active {
    display: flex;
    animation: fadeIn var(--duration-normal) var(--ease-out);
}

.modal {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-6);
    width: 100%;
    max-width: 520px;
    max-height: 90vh;
    overflow-y: auto;
    animation: scaleIn var(--duration-slow) var(--ease-out);
}

.modal.large {
    max-width: 720px;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-md) var(--space-lg);
    border-bottom: 1px solid var(--border-light);
}

.modal-title {
    font-size: 17px;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    margin: 0;
}

.modal-title svg {
    width: 20px;
    height: 20px;
    fill: var(--primary);
}

.modal-close {
    width: 36px;
    height: 36px;
    border: none;
    background: transparent;
    border-radius: var(--radius-sm);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    transition: all var(--duration-fast) var(--ease-out);
}

.modal-close:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.modal-close svg {
    width: 20px;
    height: 20px;
}

.modal-body {
    padding: var(--space-lg);
}

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: var(--space-sm);
    padding: var(--space-md) var(--space-lg);
    border-top: 1px solid var(--border-light);
}

.modal-desc {
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: var(--space-md);
    line-height: 1.5;
}

.loading-text {
    padding: var(--space-lg);
    text-align: center;
    color: var(--text-muted);
    font-size: 14px;
}

/* ── Toast System ── */
.toast-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: calc(var(--z-toast) + 1);
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
}

.toast {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: var(--bg-card);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-5);
    min-width: 300px;
    max-width: 420px;
    pointer-events: auto;
    border-left: 4px solid;
    animation: slideIn var(--duration-slow) var(--ease-out);
}

.toast--success { border-left-color: var(--success); }
.toast--error   { border-left-color: var(--danger); }
.toast--warning { border-left-color: var(--warning); }
.toast--info    { border-left-color: var(--info); }

.toast__icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

.toast--success .toast__icon { color: var(--success); }
.toast--error   .toast__icon { color: var(--danger); }
.toast--warning .toast__icon { color: var(--warning); }
.toast--info    .toast__icon { color: var(--info); }

.toast__content { flex: 1; }

.toast__title {
    font-weight: 600;
    font-size: 14px;
    color: var(--text-primary);
    margin-bottom: 2px;
}

.toast__message {
    font-size: 13px;
    color: var(--text-secondary);
}

.toast__close {
    width: 28px;
    height: 28px;
    border: none;
    background: none;
    cursor: pointer;
    color: var(--text-muted);
    border-radius: var(--radius-xs);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all var(--duration-fast) var(--ease-out);
}

.toast__close:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.toast__close svg {
    width: 16px;
    height: 16px;
}

/* ── Pagination ── */
.pagination-container {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
    padding: var(--space-md) var(--space-lg);
    border-top: 1px solid var(--border-light);
}

.pagination-info {
    text-align: center;
    font-size: 13px;
    color: var(--text-muted);
}

.pagination-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-sm);
}

.pagination-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 8px 14px;
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all var(--duration-fast) var(--ease-out);
    font-family: inherit;
}

.pagination-btn:hover:not(:disabled) {
    background: var(--bg-hover);
    border-color: var(--border-medium);
    color: var(--text-primary);
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-btn svg {
    width: 16px;
    height: 16px;
}

.pagination-pages {
    display: flex;
    align-items: center;
    gap: 4px;
}

.pagination-page {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all var(--duration-fast) var(--ease-out);
    border: none;
    background: none;
    font-family: inherit;
}

.pagination-page:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.pagination-page.active {
    background: var(--primary);
    color: white;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(15, 41, 66, 0.25);
}

.pagination-page.ellipsis {
    cursor: default;
    color: var(--text-muted);
}

.pagination-page.ellipsis:hover {
    background: transparent;
}

/* ── Detail Grid (View Modal) ── */
.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-md);
}

.detail-section-title {
    grid-column: 1 / -1;
    font-size: 14px;
    font-weight: 600;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding-bottom: 4px;
    border-bottom: 1px solid var(--border-light);
    margin-bottom: 4px;
}

.detail-section-title svg {
    width: 16px;
    height: 16px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.detail-value {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
}

/* ── Forms ── */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-md);
    margin-bottom: var(--space-md);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
}

.form-label .required {
    color: var(--danger);
}

.form-input,
.form-select,
.form-textarea {
    padding: 10px 14px;
    background: var(--bg-main);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    font-size: 13px;
    font-family: inherit;
    color: var(--text-primary);
    transition: border-color var(--duration-fast) var(--ease-out);
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    border-color: var(--primary-light);
    outline: none;
    box-shadow: 0 0 0 3px var(--primary-glow);
}

.form-select {
    cursor: pointer;
    appearance: auto;
    -webkit-appearance: auto;
}

.form-textarea {
    resize: vertical;
    min-height: 80px;
}

/* ── Buttons ── */
.btn-new {
    display: inline-flex;
    align-items: center;
    gap: var(--space-sm);
    padding: 12px 24px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: all var(--duration-normal) var(--ease-out);
    box-shadow: 0 4px 14px rgba(15, 41, 66, 0.25);
}

.btn-new:hover {
    background: var(--primary-light);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(15, 41, 66, 0.35);
}

.btn-new svg {
    width: 18px;
    height: 18px;
}

.btn-cancel {
    display: inline-flex;
    align-items: center;
    gap: var(--space-sm);
    padding: 10px 20px;
    background: var(--bg-main);
    color: var(--text-secondary);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    transition: all var(--duration-fast) var(--ease-out);
}

.btn-cancel:hover {
    border-color: var(--border-medium);
    background: var(--bg-hover);
    color: var(--text-primary);
}

.btn-submit {
    display: inline-flex;
    align-items: center;
    gap: var(--space-sm);
    padding: 10px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: all var(--duration-normal) var(--ease-out);
}

.btn-submit:hover {
    background: var(--primary-light);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(15, 41, 66, 0.25);
}

/* ── Skeleton Loading ── */
.skeleton {
    background: linear-gradient(
        90deg,
        var(--bg-main) 0%,
        var(--bg-hover) 50%,
        var(--bg-main) 100%
    );
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.5s ease-in-out infinite;
    border-radius: var(--radius-sm);
}

.skeleton-text {
    height: 14px;
    margin-bottom: 8px;
}

.skeleton-text:last-child {
    width: 60%;
}

.skeleton-stat {
    height: 80px;
    border-radius: var(--radius-md);
}

.skeleton-row {
    display: grid;
    grid-template-columns: 80px 1fr 80px 100px 120px 120px 80px 80px;
    gap: var(--space-sm);
    padding: 12px var(--space-md);
    align-items: center;
}

.skeleton-row span {
    height: 14px;
    border-radius: var(--radius-xs);
}

/* ── Empty State ── */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
    text-align: center;
}

.empty-state svg {
    width: 64px;
    height: 64px;
    color: var(--text-muted);
    margin-bottom: 16px;
    opacity: 0.4;
}

.empty-state .empty-state-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.empty-state .empty-state-desc {
    font-size: 13px;
    color: var(--text-muted);
    max-width: 360px;
}

/* ── Assigned User Badge ── */
.assigned-user {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    color: var(--text-primary);
}

.assigned-user svg {
    width: 14px;
    height: 14px;
    color: var(--text-muted);
}

/* ── Mobile Responsive ── */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-sm);
    }
    .filters-row {
        flex-wrap: wrap;
    }
    .filter-select {
        min-width: 150px;
    }
    .filter-group.search-filter {
        flex: 1 1 100%;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
    .detail-grid {
        grid-template-columns: 1fr;
    }
    .detail-item.full-width {
        grid-column: 1;
    }
    .skeleton-row {
        grid-template-columns: 60px 1fr 60px 80px 100px 100px 60px 60px;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: var(--space-sm);
    }
    .stat-card {
        padding: var(--space-md);
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-sm);
    }
    .stat-icon {
        width: 44px;
        height: 44px;
    }
    .stat-icon svg {
        width: 20px;
        height: 20px;
    }
    .stat-value {
        font-size: 20px;
    }
    .stat-label {
        font-size: 12px;
    }
    .filters-row {
        flex-direction: column;
        align-items: stretch;
        gap: var(--space-sm);
    }
    .filter-select,
    .search-input {
        width: 100%;
        min-width: 100%;
    }
    .table-card {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .table-card table {
        min-width: 700px;
    }
    .modal {
        width: 95%;
        max-width: 95vw;
        margin: 10px auto;
    }
    .pagination-controls {
        flex-wrap: wrap;
    }
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-md);
    }
    .btn-new {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
        gap: var(--space-sm);
    }
    .stat-card {
        flex-direction: row;
        padding: var(--space-md);
    }
    .stat-icon {
        width: 40px;
        height: 40px;
    }
    .stat-value {
        font-size: 18px;
    }
    .pagination-page {
        width: 32px;
        height: 32px;
        font-size: 12px;
    }
    .pagination-btn {
        padding: 6px 10px;
        font-size: 12px;
    }
    .modal {
        width: 98%;
        padding: 0;
    }
    .modal-body {
        padding: var(--space-md);
    }
    .toast {
        min-width: auto;
        max-width: calc(100vw - 32px);
    }
    .page-title {
        font-size: 22px;
    }
}

/* ── Reduced Motion ── */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
    .stat-value.animating {
        animation: none;
    }
    .skeleton {
        animation: none;
        background: var(--bg-main);
    }
}
```

---

## 4. JavaScript Architecture (Complete `<script>` block)

All user-facing strings in **Spanish**. All function signatures specified below.

### 4.1 State Variables

```javascript
const BASE_URL = '<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

let searchTimeout = null;
let currentPage = 1;
const pageSize = 20;
let currentSearch = '';
let currentLocation = '';
let currentStatus = '';
```

### 4.2 Utility Functions

```javascript
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

async function parseJsonResponse(response) {
    const contentType = response.headers.get('content-type') || '';
    if (response.ok && contentType.includes('application/json')) {
        return response.json();
    }
    const text = await response.text();
    throw new Error(text || 'El servidor no devolvi&oacute; JSON v&aacute;lido.');
}

function debounce(func, delay) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}
```

### 4.3 Toast System

```javascript
function showToast(type, title, message) {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const icons = {
        success: '<i data-lucide="check-circle" class="toast__icon"></i>',
        error:   '<i data-lucide="x-circle" class="toast__icon"></i>',
        warning: '<i data-lucide="alert-triangle" class="toast__icon"></i>',
        info:    '<i data-lucide="info" class="toast__icon"></i>'
    };
    const toast = document.createElement('div');
    toast.className = 'toast toast--' + type;
    toast.innerHTML =
        icons[type] +
        '<div class="toast__content">' +
            '<div class="toast__title">' + escapeHtml(title) + '</div>' +
            '<div class="toast__message">' + escapeHtml(message) + '</div>' +
        '</div>' +
        '<button class="toast__close" onclick="this.closest(\'.toast\').remove()">' +
            '<i data-lucide="x"></i>' +
        '</button>';
    container.appendChild(toast);
    lucide.createIcons();
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}
```

### 4.4 Modal System

```javascript
function mostrarModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.style.display = 'flex';
    // force reflow for animation
    m.offsetHeight;
    m.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function cerrarModal(tipo) {
    const m = document.getElementById('modal-' + tipo);
    if (!m) return;
    m.style.display = 'none';
    m.classList.remove('active');
    document.body.style.overflow = '';
}
```

### 4.5 Search Debounce

```javascript
function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage = 1;
        cargarEquipos();
    }, 300);
}

function clearFilters() {
    document.getElementById('filtro-ubicacion').value = '';
    document.getElementById('filtro-estado').value = '';
    document.getElementById('search-equipos').value = '';
    currentPage = 1;
    cargarEquipos();
}
```

### 4.6 Main Data Fetch: `cargarEquipos(page)`

```javascript
function cargarEquipos(page) {
    page = page || currentPage;
    currentPage = page;

    const search = document.getElementById('search-equipos').value;
    const location_id = document.getElementById('filtro-ubicacion').value;
    const status = document.getElementById('filtro-estado').value;

    currentSearch = search;
    currentLocation = location_id;
    currentStatus = status;

    // Show skeleton loading
    renderSkeletonStats();
    renderSkeletonTable();

    let url = BASE_URL + 'app/api/equipos.php?action=list';
    if (search)      url += '&search=' + encodeURIComponent(search);
    if (location_id) url += '&location_id=' + encodeURIComponent(location_id);
    if (status)      url += '&status=' + encodeURIComponent(status);
    url += '&page=' + page + '&page_size=' + pageSize;

    fetch(url)
        .then(r => parseJsonResponse(r))
        .then(res => {
            if (res.error) {
                renderError(res.error);
                return;
            }
            renderStats(res.stats);
            renderTable(res.equipos || []);
            renderPagination(res.total || 0, res.page || 1, res.page_size || pageSize);
            lucide.createIcons();
        })
        .catch(err => {
            renderError(err.message || 'Error de conexi&oacute;n.');
        });
}
```

### 4.7 Skeleton Rendering

```javascript
function renderSkeletonStats() {
    const grid = document.getElementById('stats-grid');
    if (!grid) return;
    grid.innerHTML = '';
    for (let i = 0; i < 4; i++) {
        const sk = document.createElement('div');
        sk.className = 'skeleton skeleton-stat';
        grid.appendChild(sk);
    }
    grid.style.display = 'grid';
}

function renderSkeletonTable() {
    const tbody = document.getElementById('equipos-table-body');
    if (!tbody) return;
    let html = '';
    for (let i = 0; i < 5; i++) {
        html += '<tr><td colspan="8"><div class="skeleton-row">' +
            '<span class="skeleton skeleton-text"></span>' +
            '<span class="skeleton skeleton-text"></span>' +
            '<span class="skeleton skeleton-text" style="width:60px;"></span>' +
            '<span class="skeleton skeleton-text" style="width:80px;"></span>' +
            '<span class="skeleton skeleton-text" style="width:100px;"></span>' +
            '<span class="skeleton skeleton-text" style="width:100px;"></span>' +
            '<span class="skeleton skeleton-text" style="width:60px;"></span>' +
            '<span class="skeleton skeleton-text" style="width:60px;"></span>' +
        '</div></td></tr>';
    }
    tbody.innerHTML = html;
}
```

### 4.8 Stats Rendering: `renderStats(stats)`

```javascript
function renderStats(stats) {
    const total = (parseInt(stats.activos)||0) +
                  (parseInt(stats.mantenimiento)||0) +
                  (parseInt(stats.inactivos)||0) +
                  (parseInt(stats.retirados)||0);

    animateCounter('eq-total', total);
    animateCounter('eq-activos', parseInt(stats.activos)||0);
    animateCounter('eq-mantenimiento', parseInt(stats.mantenimiento)||0);
    animateCounter('eq-inactivos', parseInt(stats.inactivos)||0);
}

function animateCounter(elementId, finalValue) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const startValue = parseInt(el.textContent) || 0;
    const duration = 600;
    const startTime = performance.now();

    function step(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        // ease-out cubic
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = Math.round(startValue + (finalValue - startValue) * eased);
        el.textContent = current;
        if (progress < 1) {
            requestAnimationFrame(step);
        } else {
            el.textContent = finalValue;
            el.classList.add('animating');
        }
    }
    requestAnimationFrame(step);
}
```

### 4.9 Table Rendering: `renderTable(equipos)`

```javascript
function renderTable(equipos) {
    const tbody = document.getElementById('equipos-table-body');
    const count = document.getElementById('equipos-count');
    if (!tbody) return;

    if (!equipos || equipos.length === 0) {
        tbody.innerHTML =
            '<tr><td colspan="8">' +
                '<div class="empty-state">' +
                    '<i data-lucide="monitor" width="64" height="64"></i>' +
                    '<div class="empty-state-title">No hay equipos registrados</div>' +
                    '<div class="empty-state-desc">' +
                        'No se encontraron equipos con los filtros aplicados. ' +
                        'Presione "Nuevo Equipo" para agregar uno.' +
                    '</div>' +
                '</div>' +
            '</td></tr>';
        if (count) count.textContent = '0 equipos';
        lucide.createIcons();
        return;
    }

    if (count) {
        count.textContent = equipos.length + ' equipos';
    }

    let html = '';
    equipos.forEach(eq => {
        const statusLabel = eq.status === 'active'      ? 'Activo' :
                            eq.status === 'maintenance' ? 'Mantenimiento' :
                            eq.status === 'retired'     ? 'Retirado' :
                            'Inactivo';
        const statusClass = eq.status === 'active'      ? 'active' :
                            eq.status === 'maintenance' ? 'maintenance' :
                            eq.status === 'retired'     ? 'retired' :
                            'inactive';

        const assignedHtml = eq.assigned_user_name
            ? '<span class="assigned-user">' +
                '<i data-lucide="user" width="14" height="14"></i>' +
                escapeHtml((eq.assigned_user_name || '') + ' ' + (eq.assigned_user_lastname || '')) +
              '</span>'
            : '<span class="text-muted" style="font-size:12px;color:var(--text-muted);">Sin asignar</span>';

        const isRetired = eq.status === 'retired';

        html += '<tr>';
        html += '<td>' + escapeHtml(eq.patrimonial_code || '-') + '</td>';
        html += '<td><strong>' + escapeHtml(eq.name) + '</strong></td>';
        html += '<td>' + escapeHtml(eq.asset_type || '-') + '</td>';
        html += '<td>' + escapeHtml(eq.serial_number || '-') + '</td>';
        html += '<td>' + escapeHtml(eq.location_name || 'Sin asignar') + '</td>';
        html += '<td>' + assignedHtml + '</td>';
        html += '<td><span class="status-badge ' + statusClass + '">' + statusLabel + '</span></td>';
        html += '<td><div class="action-cell">';
        html += '<button class="action-btn sm view" onclick="verEquipo(\'' + eq.id + '\')" title="Ver detalle">' +
                    '<i data-lucide="eye"></i>' +
                '</button>';
        html += '<button class="action-btn sm edit" onclick="editarEquipo(\'' + eq.id + '\')" title="Editar">' +
                    '<i data-lucide="pencil"></i>' +
                '</button>';

        let actionsHtml = '';
        if (isRetired) {
            actionsHtml += '<button class="action-dd__item action-dd__item--success" onclick="reactivarEquipo(\'' + eq.id + '\')">' +
                '<i data-lucide="check-circle"></i>Reactivar' +
            '</button>';
        } else {
            actionsHtml += '<button class="action-dd__item action-dd__item--warning" onclick="desactivarEquipo(\'' + eq.id + '\')">' +
                '<i data-lucide="toggle-left"></i>Desactivar' +
            '</button>';
        }
        actionsHtml += '<button class="action-dd__item action-dd__item--danger" onclick="eliminarEquipoPermanent(\'' + eq.id + '\')">' +
            '<i data-lucide="trash-2"></i>Eliminar' +
        '</button>';

        html += '<div class="action-dd">' +
            '<button class="action-dd__btn" onclick="event.stopPropagation();toggleActionDD(\'' + eq.id + '\', this)">' +
                '<i data-lucide="more-vertical"></i>' +
            '</button>' +
            '<div class="action-dd__menu" id="action-dd-' + eq.id + '">' + actionsHtml + '</div>' +
        '</div>';
        html += '</div></td></tr>';
    });

    tbody.innerHTML = html;
}
```

### 4.10 Pagination Rendering: `renderPagination(total, page, pageSize)`

```javascript
function renderPagination(total, page, pageSize) {
    const container = document.getElementById('pagination-container');
    if (!container) return;

    const totalPages = Math.ceil(total / pageSize) || 1;
    const startItem = (page - 1) * pageSize + 1;
    const endItem = Math.min(page * pageSize, total);

    let html = '<div class="pagination-info">' +
        'Mostrando ' + startItem + '&ndash;' + endItem + ' de ' + total + ' equipos' +
    '</div>';

    if (totalPages > 1) {
        html += '<div class="pagination-controls">';
        // Previous
        html += '<button class="pagination-btn" onclick="cargarEquipos(' + (page - 1) + ')"' +
            (page <= 1 ? ' disabled' : '') + '>' +
            '<i data-lucide="chevron-left" width="16" height="16"></i> Anterior' +
        '</button>';

        html += '<div class="pagination-pages">';

        // Build visible pages with ellipsis (max 7 visible)
        const pages = [];
        const maxVisible = 7;
        let startPage = Math.max(1, page - 3);
        let endPage = Math.min(totalPages, page + 3);

        if (endPage - startPage + 1 < maxVisible) {
            if (startPage === 1) {
                endPage = Math.min(totalPages, startPage + maxVisible - 1);
            } else {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }
        }

        if (startPage > 1) {
            pages.push(1);
            if (startPage > 2) pages.push('...');
        }

        for (let i = startPage; i <= endPage; i++) {
            pages.push(i);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) pages.push('...');
            pages.push(totalPages);
        }

        pages.forEach(p => {
            if (p === '...') {
                html += '<span class="pagination-page ellipsis">&hellip;</span>';
            } else {
                html += '<button class="pagination-page' + (p === page ? ' active' : '') + '"' +
                    ' onclick="cargarEquipos(' + p + ')">' + p + '</button>';
            }
        });

        html += '</div>'; // .pagination-pages

        // Next
        html += '<button class="pagination-btn" onclick="cargarEquipos(' + (page + 1) + ')"' +
            (page >= totalPages ? ' disabled' : '') + '>' +
            'Siguiente <i data-lucide="chevron-right" width="16" height="16"></i>' +
        '</button>';

        html += '</div>'; // .pagination-controls
    }

    container.innerHTML = html;
}
```

### 4.11 View Modal: `verEquipo(id)`

```javascript
function verEquipo(id) {
    mostrarModal('modal-ver-equipo');
    const container = document.getElementById('ver-contenido-equipo');
    container.innerHTML = '<p style="padding:32px;text-align:center;color:var(--text-muted);">Cargando...</p>';
    lucide.createIcons();

    fetch(BASE_URL + 'app/api/equipos.php?action=get-equipo&id=' + encodeURIComponent(id))
        .then(r => parseJsonResponse(r))
        .then(eq => {
            if (eq.error) {
                container.innerHTML = '<p style="color:var(--danger);padding:32px;">' + escapeHtml(eq.error) + '</p>';
                return;
            }
            const statusLabel = eq.status === 'active'      ? 'Activo' :
                                eq.status === 'maintenance' ? 'Mantenimiento' :
                                eq.status === 'retired'     ? 'Retirado' :
                                'Inactivo';
            const statusClass = eq.status || 'inactive';

            let html = '<div class="detail-grid">';

            // General info section
            html += '<div class="detail-section-title"><i data-lucide="info" width="16" height="16"></i>Informaci&oacute;n General</div>';
            addDetailItem(html, 'Nombre', eq.name, '-');
            addDetailItem(html, 'C&oacute;digo', eq.patrimonial_code, '-');
            addDetailItem(html, 'Serial', eq.serial_number, '-');
            addDetailItem(html, 'Tipo', eq.asset_type, '-');
            addDetailItem(html, 'Marca', eq.brand, '-');
            addDetailItem(html, 'Modelo', eq.model, '-');

            html += '<div class="detail-item"><span class="detail-label">Estado</span>' +
                '<span class="detail-value"><span class="status-badge ' + statusClass + '">' + statusLabel + '</span></span></div>';

            addDetailItem(html, 'Condici&oacute;n', eq.condition, '-');

            // Hardware section
            html += '<div class="detail-section-title"><i data-lucide="cpu" width="16" height="16"></i>Hardware</div>';
            addDetailItem(html, 'CPU', (eq.cpu_brand||'') + ' ' + (eq.cpu_model||'') + ' ' + (eq.cpu_generation||''), '-');
            addDetailItem(html, 'RAM', eq.ram, '-');
            addDetailItem(html, 'Almacenamiento', (eq.disk_type||'') + ' - ' + (eq.disk_capacity||''), '-');

            // Location section
            html += '<div class="detail-section-title"><i data-lucide="map-pin" width="16" height="16"></i>Red y Ubicaci&oacute;n</div>';
            addDetailItem(html, 'IP', eq.ip_address, '-');
            addDetailItem(html, 'MAC', eq.mac_address, '-');
            addDetailItem(html, 'Ubicaci&oacute;n', eq.location_name, 'Sin asignar');
            addDetailItem(html, 'Usuario', (eq.assigned_user_name||'') + ' ' + (eq.assigned_user_lastname||''), 'Sin asignar');

            if (eq.observations) {
                html += '<div class="detail-section-title"><i data-lucide="file-text" width="16" height="16"></i>Observaciones</div>';
                html += '<div class="detail-item full-width"><span class="detail-value">' + escapeHtml(eq.observations) + '</span></div>';
            }

            html += '</div>'; // .detail-grid
            container.innerHTML = html;
            lucide.createIcons();
        })
        .catch(() => {
            container.innerHTML = '<p style="color:var(--danger);padding:32px;">Error cargando datos.</p>';
        });
}

function addDetailItem(html, label, value, placeholder) {
    // Helper used inside verEquipo - appends to html string
    return html + '<div class="detail-item">' +
        '<span class="detail-label">' + label + '</span>' +
        '<span class="detail-value">' + escapeHtml(value || placeholder) + '</span>' +
    '</div>';
}
```

### 4.12 Edit Modal: `editarEquipo(id)`

```javascript
function editarEquipo(id) {
    mostrarModal('modal-editar-equipo');
    const container = document.getElementById('editar-contenido-equipo');
    container.innerHTML = '<p class="loading-text">Cargando...</p>';

    const locationsPromise = fetch(BASE_URL + 'app/api/locations.php').then(r => parseJsonResponse(r));
    const usuariosPromise  = fetch(BASE_URL + 'app/api/tickets.php?action=get-usuarios').then(r => parseJsonResponse(r));
    const equipoPromise    = fetch(BASE_URL + 'app/api/equipos.php?action=get-equipo&id=' + encodeURIComponent(id))
                                .then(r => parseJsonResponse(r));

    Promise.all([locationsPromise, usuariosPromise, equipoPromise])
        .then(([locationsData, usuariosData, eq]) => {
            if (eq.error) {
                container.innerHTML = '<p style="color:var(--danger);padding:32px;">' + escapeHtml(eq.error) + '</p>';
                return;
            }

            let html = '<input type="hidden" id="edit-id" value="' + eq.id + '">';

            html += buildFormRow(
                buildFormField('Nombre', 'text', 'edit-name', eq.name||'', true),
                buildFormField('C&oacute;digo Patrimonial', 'text', 'edit-patrimonial_code', eq.patrimonial_code||'')
            );
            html += buildFormRow(
                buildFormField('Serial', 'text', 'edit-serial_number', eq.serial_number||''),
                buildFormSelect('Tipo', 'edit-asset_type', TIPOS_EQUIPO, eq.asset_type||'')
            );
            html += buildFormRow(
                buildFormField('Marca', 'text', 'edit-brand', eq.brand||''),
                buildFormField('Modelo', 'text', 'edit-model', eq.model||'')
            );
            html += buildFormRow(
                buildFormSelect('Estado', 'edit-status', STATUS_OPTIONS, eq.status||''),
                buildFormSelect('Condici&oacute;n', 'edit-condition', CONDITION_OPTIONS, eq.condition||'')
            );
            html += buildFormRow(
                buildFormField('IP', 'text', 'edit-ip_address', eq.ip_address||''),
                buildFormField('MAC', 'text', 'edit-mac_address', eq.mac_address||'')
            );

            // Location select
            let locHtml = '<div class="form-group"><label class="form-label">Ubicaci&oacute;n</label>';
            locHtml += '<select id="edit-location_id" class="form-select"><option value="">-- Sin asignar --</option>';
            (locationsData.locations || []).forEach(loc => {
                const prefix = (loc.type||'').toUpperCase() === 'SEDE' || (loc.type||'').toUpperCase() === 'SUCURSAL'
                    ? '[SEDE] ' : '[&Aacute;REA] ';
                locHtml += '<option value="' + loc.id + '"' +
                    (eq.location_id == loc.id ? ' selected' : '') + '>' +
                    prefix + escapeHtml(loc.name) + '</option>';
            });
            locHtml += '</select></div>';

            // User select
            let userHtml = '<div class="form-group"><label class="form-label">Usuario</label>';
            userHtml += '<select id="edit-assigned_user_id" class="form-select"><option value="">-- Sin asignar --</option>';
            (usuariosData || []).forEach(u => {
                const fullname = (u.nombre||'') + ' ' + (u.apellidos||'');
                userHtml += '<option value="' + u.id + '"' +
                    (eq.assigned_user_id == u.id ? ' selected' : '') + '>' +
                    escapeHtml(fullname) + '</option>';
            });
            userHtml += '</select></div>';

            html += buildFormRow(locHtml, userHtml);

            html += '<div class="form-group"><label class="form-label">Observaciones</label>' +
                '<textarea id="edit-observations" class="form-textarea" rows="3">' +
                escapeHtml(eq.observations||'') +
                '</textarea></div>';

            container.innerHTML = html;
            lucide.createIcons();
        })
        .catch(() => {
            container.innerHTML = '<p style="color:var(--danger);padding:32px;">Error cargando datos.</p>';
        });
}
```

### 4.13 Save Equipment: `guardarEquipo(e)`

```javascript
function guardarEquipo(e) {
    e.preventDefault();
    const postData = new URLSearchParams();
    postData.append('action', 'update-equipo');
    postData.append('id', document.getElementById('edit-id').value);
    postData.append('name', document.getElementById('edit-name').value);
    postData.append('patrimonial_code', document.getElementById('edit-patrimonial_code').value);
    postData.append('serial_number', document.getElementById('edit-serial_number').value);
    postData.append('asset_type', document.getElementById('edit-asset_type').value);
    postData.append('brand', document.getElementById('edit-brand').value);
    postData.append('model', document.getElementById('edit-model').value);
    postData.append('status', document.getElementById('edit-status').value);
    postData.append('condition', document.getElementById('edit-condition').value);
    postData.append('ip_address', document.getElementById('edit-ip_address').value);
    postData.append('mac_address', document.getElementById('edit-mac_address').value);
    postData.append('location_id', document.getElementById('edit-location_id').value);
    postData.append('assigned_user_id', document.getElementById('edit-assigned_user_id').value);
    postData.append('observations', document.getElementById('edit-observations').value);

    fetch(BASE_URL + 'app/api/equipos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: postData.toString()
    })
        .then(r => parseJsonResponse(r))
        .then(res => {
            if (res.success) {
                showToast('success', 'Equipo actualizado', 'Los cambios se han guardado correctamente.');
                cerrarModal('editar-equipo');
                cargarEquipos(currentPage);
            } else {
                showToast('error', 'Error', res.error || 'No se pudo guardar.');
            }
        })
        .catch(() => showToast('error', 'Error', 'Error de conexi&oacute;n.'));
}
```

### 4.14 Create Equipment: `abrirCrearEquipo()` and `crearEquipo(e)`

```javascript
function abrirCrearEquipo() {
    mostrarModal('modal-crear-equipo');
    const container = document.getElementById('crear-contenido-equipo');
    container.innerHTML = '<p class="loading-text">Cargando formulario...</p>';

    const locationsPromise = fetch(BASE_URL + 'app/api/locations.php').then(r => parseJsonResponse(r));
    const usuariosPromise  = fetch(BASE_URL + 'app/api/tickets.php?action=get-usuarios').then(r => parseJsonResponse(r));

    Promise.all([locationsPromise, usuariosPromise])
        .then(([locationsData, usuariosData]) => {
            let html = '';
            html += buildFormRow(
                buildFormField('Nombre *', 'text', 'create-name', '', true, 'name'),
                buildFormField('C&oacute;digo Patrimonial', 'text', 'create-patrimonial_code', '', false, 'patrimonial_code')
            );
            html += buildFormRow(
                buildFormField('Serial', 'text', 'create-serial_number', '', false, 'serial_number'),
                buildFormSelect('Tipo', 'create-asset_type', TIPOS_EQUIPO, 'PC', 'asset_type')
            );
            html += buildFormRow(
                buildFormField('Marca', 'text', 'create-brand', '', false, 'brand'),
                buildFormField('Modelo', 'text', 'create-model', '', false, 'model')
            );
            html += buildFormRow(
                buildFormSelect('Estado', 'create-status', STATUS_OPTIONS, 'active', 'status'),
                buildFormSelect('Condici&oacute;n', 'create-condition', CONDITION_OPTIONS, 'BUENO', 'condition')
            );
            html += buildFormRow(
                buildFormField('IP', 'text', 'create-ip_address', '', false, 'ip_address'),
                buildFormField('MAC', 'text', 'create-mac_address', '', false, 'mac_address')
            );

            // Location select
            let locHtml = '<div class="form-group"><label class="form-label">Ubicaci&oacute;n</label>';
            locHtml += '<select name="location_id" class="form-select"><option value="">-- Sin asignar --</option>';
            (locationsData.locations || []).forEach(loc => {
                const prefix = (loc.type||'').toUpperCase() === 'SEDE' || (loc.type||'').toUpperCase() === 'SUCURSAL'
                    ? '[SEDE] ' : '[&Aacute;REA] ';
                locHtml += '<option value="' + loc.id + '">' + prefix + escapeHtml(loc.name) + '</option>';
            });
            locHtml += '</select></div>';

            // User select
            let userHtml = '<div class="form-group"><label class="form-label">Usuario</label>';
            userHtml += '<select name="assigned_user_id" class="form-select"><option value="">-- Sin asignar --</option>';
            (usuariosData || []).forEach(u => {
                const fullname = (u.nombre||'') + ' ' + (u.apellidos||'');
                userHtml += '<option value="' + u.id + '">' + escapeHtml(fullname) + '</option>';
            });
            userHtml += '</select></div>';

            html += buildFormRow(locHtml, userHtml);
            html += '<div class="form-group"><label class="form-label">Observaciones</label>' +
                '<textarea name="observations" class="form-textarea" rows="3"></textarea></div>';

            container.innerHTML = html;
            lucide.createIcons();
        })
        .catch(err => {
            container.innerHTML = '<p style="color:var(--danger);padding:16px;">Error: ' + escapeHtml(err.message) + '</p>';
        });
}

function crearEquipo(e) {
    e.preventDefault();
    const form = document.getElementById('form-crear-equipo');
    const formData = new FormData(form);
    formData.append('action', 'create-equipo');

    fetch(BASE_URL + 'app/api/equipos.php', {
        method: 'POST',
        body: formData
    })
        .then(r => parseJsonResponse(r))
        .then(result => {
            if (result.success) {
                showToast('success', 'Equipo creado', 'El equipo se ha registrado exitosamente.');
                cerrarModal('crear-equipo');
                cargarEquipos(1);
            } else {
                showToast('error', 'Error', result.error || 'No se pudo crear.');
            }
        })
        .catch(err => showToast('error', 'Error', err.message));
}
```

### 4.15 Deactivate Equipment: `desactivarEquipo(id)` and `confirmarDesactivar()`

```javascript
function desactivarEquipo(id) {
    document.getElementById('deactivate-equipo-id').value = id;
    document.getElementById('deactivate-equipo-reason').value = '';
    mostrarModal('modal-desactivar-equipo');
}

function confirmarDesactivar() {
    const id = document.getElementById('deactivate-equipo-id').value;
    const reason = document.getElementById('deactivate-equipo-reason').value;
    if (!reason) {
        showToast('warning', 'Campo requerido', 'Ingrese el motivo de desactivaci&oacute;n.');
        return;
    }

    fetch(BASE_URL + 'app/api/equipos.php?action=deactivate-equipo&id=' + encodeURIComponent(id), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ reason: reason }).toString()
    })
        .then(r => parseJsonResponse(r))
        .then(res => {
            if (res.success) {
                showToast('success', 'Equipo desactivado', 'El equipo ha sido desactivado.');
                cerrarModal('desactivar-equipo');
                cargarEquipos(currentPage);
            } else {
                showToast('error', 'Error', res.error || 'No se pudo desactivar.');
            }
        })
        .catch(() => showToast('error', 'Error', 'Error de conexi&oacute;n.'));
}
```

### 4.16 Permanent Delete: `eliminarEquipoPermanent(id)` and `confirmarEliminarPermanent()`

```javascript
function eliminarEquipoPermanent(id) {
    document.getElementById('delete-permanent-equipo-id').value = id;
    document.getElementById('delete-permanent-equipo-reason').value = '';
    mostrarModal('modal-eliminar-permanent-equipo');
}

function confirmarEliminarPermanent() {
    const id = document.getElementById('delete-permanent-equipo-id').value;
    const reason = document.getElementById('delete-permanent-equipo-reason').value;
    if (!reason) {
        showToast('warning', 'Campo requerido', 'Ingrese el motivo de eliminaci&oacute;n.');
        return;
    }

    fetch(BASE_URL + 'app/api/equipos.php?action=delete-equipo&id=' + encodeURIComponent(id) + '&permanent=1', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ reason: reason }).toString()
    })
        .then(r => parseJsonResponse(r))
        .then(res => {
            if (res.success) {
                showToast('success', 'Equipo eliminado', 'El equipo ha sido eliminado permanentemente.');
                cerrarModal('eliminar-permanent-equipo');
                cargarEquipos(currentPage);
            } else {
                showToast('error', 'Error', res.error || 'No se pudo eliminar.');
            }
        })
        .catch(() => showToast('error', 'Error', 'Error de conexi&oacute;n.'));
}
```

### 4.17 Reactivate: `reactivarEquipo(id)`

```javascript
function reactivarEquipo(id) {
    if (!confirm('&iquest;Est&aacute; seguro de reactivar este equipo?')) return;

    fetch(BASE_URL + 'app/api/equipos.php?action=reactivate-equipo&id=' + encodeURIComponent(id), {
        method: 'POST'
    })
        .then(r => parseJsonResponse(r))
        .then(res => {
            if (res.success) {
                showToast('success', 'Equipo reactivado', 'El equipo ha sido reactivado.');
                cargarEquipos(currentPage);
            } else {
                showToast('error', 'Error', res.error || 'No se pudo reactivar.');
            }
        })
        .catch(() => showToast('error', 'Error', 'Error de conexi&oacute;n.'));
}
```

### 4.18 Action Dropdown Toggle: `toggleActionDD(equipoId, btnElement)`

```javascript
function toggleActionDD(equipoId, btnElement) {
    const dropdown = document.getElementById('action-dd-' + equipoId);
    if (!dropdown) return;

    // Close all other dropdowns
    document.querySelectorAll('.action-dd__menu.show').forEach(el => {
        if (el !== dropdown) el.classList.remove('show');
    });

    dropdown.classList.toggle('show');

    if (dropdown.classList.contains('show')) {
        // Position relative to the button within the action-cell
        const rect = btnElement.getBoundingClientRect();
        const padding = 8;
        // Default: position below and right-aligned
        let top = rect.bottom + 4;
        let left = rect.right - dropdown.offsetWidth;

        // Adjust if off-screen right
        if (left < padding) left = padding;
        // Adjust if off-screen left
        if (left + dropdown.offsetWidth > window.innerWidth - padding) {
            left = window.innerWidth - dropdown.offsetWidth - padding;
        }
        // Adjust if off-screen bottom
        if (top + dropdown.offsetHeight > window.innerHeight - padding) {
            top = rect.top - dropdown.offsetHeight - 4;
        }
        // Adjust if off-screen top
        if (top < padding) {
            top = padding;
        }

        dropdown.style.position = 'fixed';
        dropdown.style.top = top + 'px';
        dropdown.style.left = left + 'px';
        dropdown.style.right = 'auto';
    }
}
```

### 4.19 Event Delegation (close dropdowns on document click)

```javascript
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dd')) {
        document.querySelectorAll('.action-dd__menu.show').forEach(el => el.classList.remove('show'));
    }
});
```

### 4.20 DOMContentLoaded

```javascript
document.addEventListener('DOMContentLoaded', function() {
    cargarEquipos(1);
});
```

### 4.21 Reusable Form Builders (DRY helpers)

```javascript
// Reusable option arrays
const TIPOS_EQUIPO = [
    { value: 'PC', label: 'PC' },
    { value: 'LAPTOP', label: 'LAPTOP' },
    { value: 'IMPRESORA', label: 'IMPRESORA' },
    { value: 'MONITOR', label: 'MONITOR' },
    { value: 'OTRO', label: 'OTRO' }
];

const STATUS_OPTIONS = [
    { value: 'active', label: 'Activo' },
    { value: 'maintenance', label: 'Mantenimiento' },
    { value: 'inactive', label: 'Inactivo' },
    { value: 'retired', label: 'Retirado' }
];

const CONDITION_OPTIONS = [
    { value: 'BUENO', label: 'BUENO' },
    { value: 'REGULAR', label: 'REGULAR' },
    { value: 'MALO', label: 'MALO' },
    { value: 'OBSOLETO', label: 'OBSOLETO' }
];

function buildFormField(label, type, id, value, required, name) {
    const requiredAttr = required ? ' required' : '';
    const nameAttr = name ? ' name="' + name + '"' : ' id="' + id + '"';
    return '<div class="form-group"><label class="form-label">' + label +
        (required ? ' <span class="required">*</span>' : '') +
        '</label><input type="' + type + '" id="' + id + '"' + nameAttr +
        ' class="form-input" value="' + escapeHtml(value) + '"' + requiredAttr + '></div>';
}

function buildFormSelect(label, id, options, selectedValue, name) {
    const nameAttr = name ? ' name="' + name + '"' : ' id="' + id + '"';
    let html = '<div class="form-group"><label class="form-label">' + label + '</label>';
    html += '<select id="' + id + '"' + nameAttr + ' class="form-select">';
    options.forEach(opt => {
        html += '<option value="' + opt.value + '"' +
            (opt.value === selectedValue ? ' selected' : '') + '>' +
            opt.label + '</option>';
    });
    html += '</select></div>';
    return html;
}

function buildFormRow(col1, col2) {
    return '<div class="form-row">' + col1 + col2 + '</div>';
}
```

### 4.22 Error Rendering

```javascript
function renderError(message) {
    const tbody = document.getElementById('equipos-table-body');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--danger);padding:32px;">' +
            escapeHtml(message) + '</td></tr>';
    }
    const count = document.getElementById('equipos-count');
    if (count) count.textContent = '0 equipos';
}
```

---

## 5. Important Implementation Notes

### 5.1 Spanish-Only Strings
All user-facing text is in Spanish. No English strings appear in toasts, placeholders, labels, empty states, or button text.

### 5.2 XSS Prevention
All user-supplied values rendered as HTML use `escapeHtml()` which relies on `textContent` + `innerHTML` chaining. Every `eq.*` property accessed in template literals is wrapped with `escapeHtml()`.

### 5.3 Lucide Re-initialization
Call `lucide.createIcons()` after EVERY dynamic content insert: table render, modal body load, toast creation. The `<i data-lucide="...">` syntax is used consistently.

### 5.4 Stat Counter Animation
`animateCounter()` uses `requestAnimationFrame` with a cubic ease-out curve. Duration is 600ms. The `.animating` class triggers the `countUp` CSS keyframe for a subtle fade-in at first render.

### 5.5 Action Dropdown Positioning
Dropdowns use `position: fixed` (not absolute) calculated from `getBoundingClientRect()` to avoid overflow clipping from parent containers. The `action-cell` has `position: relative` as a fallback container context. `event.stopPropagation()` on the toggle button prevents the document-level close handler from immediately hiding the dropdown.

### 5.6 Pagination
`renderPagination()` shows "Mostrando X-Y de Z equipos" with up to 7 visible page buttons and ellipsis. Uses `<button>` elements (not `<a>`) for SPA-style navigation.

### 5.7 Empty State
When `equipos.length === 0`, shows a full-column empty state with Lucide icon and descriptive text. When an API error occurs, renders the error message in `var(--danger)` color.

### 5.8 Loading State (Skeleton)
`renderSkeletonStats()` replaces the 4 stat cards with `.skeleton-stat` divs. `renderSkeletonTable()` inserts 5 skeleton rows with `.skeleton-row` grid layout matching the 8-column table structure. The shimmer animation comes from `app.css` `@keyframes skeleton-shimmer`.

### 5.9 CSRF Token
Read from `<meta name="csrf-token">` (rendered in `head.php`). Stored in `CSRF_TOKEN` constant but not currently appended to API calls since the API uses session-based auth. Can be added to POST bodies if needed later.

### 5.10 API Response Shape
The `?action=list` endpoint returns:
```json
{
    "success": true,
    "equipos": [{...}],
    "total": 50,
    "page": 1,
    "page_size": 20,
    "stats": { "total": 100, "activos": 40, "mantenimiento": 20, "inactivos": 25, "retirados": 15 }
}
```

### 5.11 Form Field DRY Arrays
Three reusable option arrays (`TIPOS_EQUIPO`, `STATUS_OPTIONS`, `CONDITION_OPTIONS`) are declared once and reused across create/edit form builders to avoid duplication.

### 5.12 Responsive Breakpoints
- `1024px`: stats 2-col, filters wrap, form rows single column
- `768px`: stats 2-col (compact), table scrollable, pagination wrap, page header stacked
- `480px`: stats 1-col, pagination compact, modal full-width

### 5.13 Reduced Motion
The `@media (prefers-reduced-motion: reduce)` query disables all animations and transitions including the skeleton shimmer and stat counter animation, falling back to instant display of final values.

### 5.14 Zero Backend Changes
No modifications to `app/api/equipos.php`, `app/Models/Equipment.php`, or partials. The entire change is contained within `app/Views/admin/equipos.php`.
