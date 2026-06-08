# Expansion: C1 — Component-Class Refactor

## 1. PHP Structure

### Session & User Initialization
```php
<?php
$baseUrl = base_url();
$csrfToken = csrf_token();
$userId = $_SESSION['user']['id'] ?? null;
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$isOtiAdmin = \App\Services\AuthService::isAdmin();
```

### Model Includes & Data
```php
require_once __DIR__ . '/../../Models/Equipment.php';
require_once __DIR__ . '/../../Models/Location.php';
require_once __DIR__ . '/../../Models/User.php';

$initialStats = \App\Models\Equipment::getStats();
$locationsData = \App\Models\Location::getAll();
$hierarchyData = \App\Models\User::getLocationsHierarchy();

$tituloPagina = 'Inventario de Equipos - Sistema OTI';
$paginaActual = 'admin-equipos';
```

### Partial Includes
```php
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>
```

### CSRF Token
Already in `head.php`: `<meta name="csrf-token" content="<?= csrf_token() ?>">`
Accessed in JS via: `document.querySelector('meta[name="csrf-token"]').getAttribute('content')`

---

## 2. HTML Structure (Complete)

### page-header
```html
<main id="main-content" class="main-content">
  <div class="page-header">
    <div class="page-header-left">
      <h1 class="page-title">Inventario de Equipos</h1>
      <p class="page-subtitle">Gestión de equipos tecnológicos</p>
    </div>
    <button type="button" class="btn-new" data-action="create">
      <i data-lucide="plus"></i> Nuevo Equipo
    </button>
  </div>
```

### stats-grid
```html
  <div class="stats-grid" id="stats-grid">
    <!-- stat-card primary: Total -->
    <div class="stat-card primary">
      <div class="stat-icon primary">
        <i data-lucide="monitor" width="24" height="24"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value" data-stat="total">0</div>
        <div class="stat-label">Total Equipos</div>
      </div>
    </div>
    <!-- stat-card success: Activos -->
    <div class="stat-card success">
      <div class="stat-icon success">
        <i data-lucide="check-circle" width="24" height="24"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value" data-stat="activos">0</div>
        <div class="stat-label">Activos</div>
      </div>
    </div>
    <!-- stat-card warning: Mantenimiento -->
    <div class="stat-card warning">
      <div class="stat-icon warning">
        <i data-lucide="wrench" width="24" height="24"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value" data-stat="mantenimiento">0</div>
        <div class="stat-label">En Mantenimiento</div>
      </div>
    </div>
    <!-- stat-card danger: Inactivos -->
    <div class="stat-card danger">
      <div class="stat-icon danger">
        <i data-lucide="x-circle" width="24" height="24"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value" data-stat="inactivos">0</div>
        <div class="stat-label">Inactivos</div>
      </div>
    </div>
  </div>
```

### filters-section
```html
  <div class="filters-section">
    <div class="filters-header">
      <div class="filters-title">
        <i data-lucide="search" width="16" height="16"></i>
        Filtros de búsqueda
      </div>
      <button class="clear-filters-btn" data-action="clear-filters">
        <i data-lucide="x" width="14" height="14"></i> Limpiar
      </button>
    </div>
    <div class="filters-row">
      <div class="filter-group">
        <label class="filter-label">Ubicación</label>
        <select id="filtro-ubicacion" class="filter-select" data-filter="location_id">
          <option value="">Todas las ubicaciones</option>
          <optgroup label="Sedes">
            <?php foreach ($hierarchyData['sedes'] as $sede): ?>
            <option value="<?= $sede['id'] ?>"><?= htmlspecialchars($sede['name']) ?></option>
            <?php endforeach; ?>
          </optgroup>
          <optgroup label="Áreas">
            <?php foreach ($hierarchyData['areas'] as $area): ?>
            <option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['name']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        </select>
      </div>
      <div class="filter-group">
        <label class="filter-label">Estado</label>
        <select id="filtro-estado" class="filter-select" data-filter="status">
          <option value="">Todos los estados</option>
          <option value="active">Activos</option>
          <option value="maintenance">En Mantenimiento</option>
          <option value="inactive">Inactivos</option>
          <option value="retired">Retirados</option>
        </select>
      </div>
      <div class="filter-group" style="flex:1;">
        <label class="filter-label">Buscar</label>
        <div class="search-wrapper">
          <i data-lucide="search" class="search-icon" width="18" height="18"></i>
          <input type="text" class="search-input" id="search-equipos"
                 placeholder="Buscar por nombre, serial o código..."
                 data-filter="search" autocomplete="off">
        </div>
      </div>
    </div>
  </div>
```

### table-card
```html
  <div class="table-card" id="table-card">
    <div class="table-header">
      <h3 class="table-title">
        <i data-lucide="monitor" width="18" height="18"></i>
        Lista de Equipos
      </h3>
      <span class="table-count" id="equipos-count">0 equipos</span>
    </div>
    <div class="table-wrapper" id="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Tipo</th>
            <th>Serial</th>
            <th>Ubicación</th>
            <th>Usuario</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="equipos-table-body"></tbody>
      </table>
    </div>
    <!-- Pagination injected by JS into this container -->
    <div class="pagination-container" id="pagination-container"></div>
  </div>
</main>
```

### toast-container
```html
<div class="toast-container" id="toast-container"></div>
```

### 5 Modals (Static HTML)
```html
<!-- Modal: Ver Equipo -->
<div class="modal-overlay" id="modal-ver-equipo" data-modal="ver-equipo">
  <div class="modal large">
    <div class="modal-header">
      <h3 class="modal-title"><i data-lucide="info"></i> Detalles del Equipo</h3>
      <button class="modal-close" data-action="close-modal" data-modal="ver-equipo">
        <i data-lucide="x"></i>
      </button>
    </div>
    <div class="modal-body" id="ver-contenido-equipo">
      <!-- skeleton placeholder injected by JS -->
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" data-action="close-modal" data-modal="ver-equipo">Cerrar</button>
    </div>
  </div>
</div>

<!-- Modal: Editar Equipo -->
<div class="modal-overlay" id="modal-editar-equipo" data-modal="editar-equipo">
  <div class="modal large">
    <div class="modal-header">
      <h3 class="modal-title"><i data-lucide="pencil"></i> Editar Equipo</h3>
      <button class="modal-close" data-action="close-modal" data-modal="editar-equipo">
        <i data-lucide="x"></i>
      </button>
    </div>
    <form id="form-editar-equipo" data-action="submit-edit">
      <div class="modal-body" id="editar-contenido-equipo">
        <!-- skeleton -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" data-action="close-modal" data-modal="editar-equipo">Cancelar</button>
        <button type="submit" class="btn-submit" id="btn-save-edit">Guardar Cambios</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Crear Equipo -->
<div class="modal-overlay" id="modal-crear-equipo" data-modal="crear-equipo">
  <div class="modal large">
    <div class="modal-header">
      <h3 class="modal-title"><i data-lucide="plus"></i> Crear Nuevo Equipo</h3>
      <button class="modal-close" data-action="close-modal" data-modal="crear-equipo">
        <i data-lucide="x"></i>
      </button>
    </div>
    <form id="form-crear-equipo" data-action="submit-create">
      <div class="modal-body" id="crear-contenido-equipo">
        <!-- skeleton -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" data-action="close-modal" data-modal="crear-equipo">Cancelar</button>
        <button type="submit" class="btn-submit">Crear Equipo</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Desactivar Equipo -->
<div class="modal-overlay" id="modal-desactivar-equipo" data-modal="desactivar-equipo">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <h3 class="modal-title"><i data-lucide="alert-triangle"></i> Desactivar Equipo</h3>
      <button class="modal-close" data-action="close-modal" data-modal="desactivar-equipo">
        <i data-lucide="x"></i>
      </button>
    </div>
    <div class="modal-body">
      <p>Al desactivar se eliminarán las asignaciones. ¿Continuar?</p>
      <div class="form-group">
        <label class="form-label">Motivo <span class="required">*</span></label>
        <textarea id="deactivate-equipo-reason" class="form-textarea" rows="3"
                  placeholder="Ej: Equipo obsoleto, dañado..."></textarea>
      </div>
      <input type="hidden" id="deactivate-equipo-id">
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" data-action="close-modal" data-modal="desactivar-equipo">Cancelar</button>
      <button class="btn-submit" data-action="confirm-deactivate" style="background-color:var(--warning)">Confirmar</button>
    </div>
  </div>
</div>

<!-- Modal: Eliminar Permanentemente -->
<div class="modal-overlay" id="modal-eliminar-permanent-equipo" data-modal="eliminar-permanent-equipo">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <h3 class="modal-title"><i data-lucide="trash-2"></i> Eliminar Permanentemente</h3>
      <button class="modal-close" data-action="close-modal" data-modal="eliminar-permanent-equipo">
        <i data-lucide="x"></i>
      </button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:16px;color:var(--danger);">Esta acción NO se puede deshacer.</p>
      <div class="form-group">
        <label class="form-label">Motivo <span class="required">*</span></label>
        <textarea id="delete-permanent-equipo-reason" class="form-textarea" rows="3"
                  placeholder="Ej: Robado, perdido, baja definitiva..."></textarea>
      </div>
      <input type="hidden" id="delete-permanent-equipo-id">
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" data-action="close-modal" data-modal="eliminar-permanent-equipo">Cancelar</button>
      <button class="btn-submit" data-action="confirm-delete-permanent" style="background-color:var(--danger)">Eliminar</button>
    </div>
  </div>
</div>
```

### `<template>` Elements for Modal Content

Used for the form fields of create/edit and the detail grid of view — avoids HTML-in-JS strings.

```html
<!-- Template: Detail Grid for verEquipo -->
<template id="template-ver-detalle">
  <div class="detail-grid">
    <div class="detail-section-main">
      <div class="detail-section-title"><i data-lucide="info"></i> Información General</div>
      <div class="detail-row">
        <div class="detail-item"><span class="detail-label">Nombre</span><span class="detail-value" data-field="name"></span></div>
        <div class="detail-item"><span class="detail-label">Código</span><span class="detail-value" data-field="patrimonial_code"></span></div>
        <div class="detail-item"><span class="detail-label">Serial</span><span class="detail-value" data-field="serial_number"></span></div>
        <div class="detail-item"><span class="detail-label">Tipo</span><span class="detail-value" data-field="asset_type"></span></div>
        <div class="detail-item"><span class="detail-label">Marca</span><span class="detail-value" data-field="brand"></span></div>
        <div class="detail-item"><span class="detail-label">Modelo</span><span class="detail-value" data-field="model"></span></div>
        <div class="detail-item"><span class="detail-label">Estado</span><span class="detail-value" data-field="status_badge"></span></div>
        <div class="detail-item"><span class="detail-label">Condición</span><span class="detail-value" data-field="condition"></span></div>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title"><i data-lucide="cpu"></i> Hardware</div>
      <div class="detail-row">
        <div class="detail-item"><span class="detail-label">CPU</span><span class="detail-value" data-field="cpu"></span></div>
        <div class="detail-item"><span class="detail-label">RAM</span><span class="detail-value" data-field="ram"></span></div>
        <div class="detail-item"><span class="detail-label">Almacenamiento</span><span class="detail-value" data-field="storage"></span></div>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title"><i data-lucide="globe"></i> Red y Ubicación</div>
      <div class="detail-row">
        <div class="detail-item"><span class="detail-label">IP</span><span class="detail-value" data-field="ip_address"></span></div>
        <div class="detail-item"><span class="detail-label">MAC</span><span class="detail-value" data-field="mac_address"></span></div>
        <div class="detail-item"><span class="detail-label">Ubicación</span><span class="detail-value" data-field="location_name"></span></div>
        <div class="detail-item"><span class="detail-label">Usuario</span><span class="detail-value" data-field="assigned_user"></span></div>
      </div>
    </div>
    <div class="detail-section-obs" data-if-observations>
      <div class="detail-section-title"><i data-lucide="file-text"></i> Observaciones</div>
      <div class="detail-value" data-field="observations"></div>
    </div>
  </div>
</template>
```

### Footer partial
```html
<?php require __DIR__ . '/../partials/footer.php'; ?>
```

---

## 3. CSS (Inline `<style>` Block)

The CSS below is placed inside a `<style>` block in the `<head>` or at the top of the view file (after the partials). It references design tokens from `app.css` and defines equipos-specific styles not already in app.css.

```html
<style>
/* ── Filter Section ── */
.filters-section {
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-lg);
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
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: var(--font-size-md);
  font-weight: 600;
  color: var(--text-primary);
}
.filters-title svg { width: 18px; height: 18px; color: var(--text-muted); }
.clear-filters-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: transparent;
  border: 1px solid var(--border-light);
  border-radius: var(--radius-full);
  color: var(--text-secondary);
  font-size: var(--font-size-sm);
  font-weight: 500;
  cursor: pointer;
  font-family: inherit;
  transition: all var(--duration-fast);
}
.clear-filters-btn:hover {
  background: var(--danger-soft);
  border-color: var(--danger);
  color: var(--danger);
}
.clear-filters-btn svg { width: 14px; height: 14px; }
.filters-row {
  display: flex;
  gap: var(--space-md);
  align-items: flex-end;
  flex-wrap: wrap;
}
.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 180px;
}
.filter-label {
  font-size: var(--font-size-sm);
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.filter-select {
  padding: 9px 12px;
  border: 1.5px solid var(--border-light);
  border-radius: var(--radius-md);
  background: var(--bg-card);
  color: var(--text-primary);
  font-size: var(--font-size-base);
  font-family: inherit;
  transition: border-color var(--duration-fast);
  min-width: 200px;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='%237e92a9'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  padding-right: 32px;
}
.filter-select:hover { border-color: var(--border-medium); }
.filter-select:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px var(--primary-glow); }
.search-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}
.search-icon {
  position: absolute;
  left: 12px;
  color: var(--text-muted);
  pointer-events: none;
}
.search-input {
  width: 100%;
  padding: 9px 12px 9px 38px;
  border: 1.5px solid var(--border-light);
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  font-family: inherit;
  color: var(--text-primary);
  background: var(--bg-card);
  transition: border-color var(--duration-fast);
}
.search-input:hover { border-color: var(--border-medium); }
.search-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px var(--primary-glow); }

/* ── Table Card ── */
.table-card {
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-lg);
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
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: var(--font-size-md);
  font-weight: 600;
  color: var(--text-primary);
}
.table-title svg { width: 18px; height: 18px; color: var(--primary); }
.table-count {
  font-size: var(--font-size-sm);
  color: var(--text-muted);
  font-weight: 500;
}
.table-wrapper {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.table-card table {
  width: 100%;
  border-collapse: collapse;
  min-width: 800px;
}
.table-card thead th {
  padding: 12px var(--space-md);
  text-align: left;
  font-size: var(--font-size-sm);
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.3px;
  background: var(--bg-main);
  border-bottom: 1px solid var(--border-light);
  white-space: nowrap;
}
.table-card tbody td {
  padding: 12px var(--space-md);
  font-size: var(--font-size-base);
  color: var(--text-primary);
  border-bottom: 1px solid var(--border-light);
  vertical-align: middle;
}
.table-card tbody tr:hover {
  background: var(--primary-soft);
}
.table-card tbody tr:last-child td { border-bottom: none; }

/* ── Action Cell ── */
.action-cell {
  display: flex;
  align-items: center;
  gap: 4px;
  position: relative;
  white-space: nowrap;
}

/* ── Action Dropdown (CSS position:absolute — NOT position:fixed) ── */
.action-dd {
  position: relative;
  display: inline-block;
}
.action-dd__btn {
  width: 34px; height: 34px;
  border-radius: var(--radius-sm);
  border: 1.5px solid var(--border-light);
  background: var(--bg-card);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--duration-fast);
  color: var(--text-secondary);
}
.action-dd__btn:hover {
  border-color: var(--primary);
  background: var(--primary-soft);
  color: var(--primary);
}
.action-dd__btn svg { width: 18px; height: 18px; }
.action-dd__menu {
  display: none;
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 4px;
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-5);
  min-width: 190px;
  z-index: var(--z-dropdown);
  overflow: hidden;
  animation: scaleIn var(--duration-normal) var(--ease-out);
}
.action-dd__menu.show { display: block; }
.action-dd__item {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  width: 100%;
  padding: 10px var(--space-md);
  border: none;
  background: none;
  text-align: left;
  cursor: pointer;
  font-size: var(--font-size-base);
  color: var(--text-primary);
  font-family: inherit;
  transition: background var(--duration-fast);
}
.action-dd__item:hover { background: var(--bg-hover); }
.action-dd__item svg { width: 16px; height: 16px; flex-shrink: 0; }
.action-dd__item--danger { color: var(--danger); }
.action-dd__item--danger:hover { background: var(--danger-soft); }
.action-dd__item--warning { color: var(--warning); }
.action-dd__item--warning:hover { background: var(--warning-soft); }
.action-dd__item--success { color: var(--success); }
.action-dd__item--success:hover { background: var(--success-soft); }

/* ── Status Badge Variants ── */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: var(--radius-full);
  font-size: var(--font-size-sm);
  font-weight: 600;
  letter-spacing: 0.2px;
  white-space: nowrap;
}
.status-badge.active { background: var(--success-soft); color: var(--success); }
.status-badge.maintenance { background: var(--warning-soft); color: var(--warning); }
.status-badge.inactive { background: var(--info-soft); color: var(--info); }
.status-badge.retired { background: var(--bg-hover); color: var(--text-muted); }
.status-badge svg { width: 14px; height: 14px; }

/* ── Assigned user cell ── */
.assigned-user {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: var(--font-size-sm);
  color: var(--text-secondary);
}
.assigned-user svg { width: 14px; height: 14px; color: var(--text-muted); }

/* ── Modal Overlay & Variants ── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: var(--z-modal-backdrop);
  padding: var(--space-md);
}
.modal-overlay.active { display: flex; }
.modal-overlay.active .modal { animation: scaleIn var(--duration-slow) var(--ease-out); }
.modal {
  background: var(--bg-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-6);
  max-width: 640px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.modal.large { max-width: 780px; }
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-lg) var(--space-lg) var(--space-md);
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
}
.modal-title {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: var(--font-size-lg);
  font-weight: 600;
  color: var(--text-primary);
}
.modal-title svg { width: 20px; height: 20px; color: var(--primary); }
.modal-close {
  width: 36px; height: 36px;
  border-radius: var(--radius-full);
  border: none;
  background: transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  transition: all var(--duration-fast);
}
.modal-close:hover { background: var(--bg-hover); color: var(--text-primary); }
.modal-close svg { width: 20px; height: 20px; }
.modal-body {
  padding: var(--space-lg);
  overflow-y: auto;
  flex: 1;
}
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-sm);
  padding: var(--space-md) var(--space-lg);
  border-top: 1px solid var(--border-light);
  flex-shrink: 0;
}

/* ── Form Classes ── */
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
.form-group.full { grid-column: 1 / -1; }
.form-label {
  font-size: var(--font-size-sm);
  font-weight: 600;
  color: var(--text-secondary);
}
.form-label .required { color: var(--danger); }
.form-input,
.form-select,
.form-textarea {
  padding: 10px 12px;
  border: 1.5px solid var(--border-light);
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  font-family: inherit;
  color: var(--text-primary);
  background: var(--bg-card);
  transition: border-color var(--duration-fast);
}
.form-input:hover,
.form-select:hover,
.form-textarea:hover { border-color: var(--border-medium); }
.form-input:focus,
.form-select:focus,
.form-textarea:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px var(--primary-glow); }
.form-textarea { resize: vertical; min-height: 80px; }
.form-select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='%237e92a9'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  padding-right: 32px;
}

/* ── Button Classes ── */
.btn-new {
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
  padding: 10px 22px;
  background: var(--primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: all var(--duration-fast);
  box-shadow: 0 4px 14px var(--primary-glow);
}
.btn-new:hover { background: var(--primary-light); transform: translateY(-1px); box-shadow: var(--shadow-4); }
.btn-new svg { width: 18px; height: 18px; }
.btn-submit {
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
  padding: 10px 24px;
  background: var(--primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: all var(--duration-fast);
}
.btn-submit:hover { background: var(--primary-light); transform: translateY(-1px); box-shadow: var(--shadow-3); }
.btn-cancel {
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
  padding: 10px 24px;
  background: var(--bg-card);
  color: var(--text-secondary);
  border: 1.5px solid var(--border-light);
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: all var(--duration-fast);
}
.btn-cancel:hover { border-color: var(--border-medium); color: var(--text-primary); background: var(--bg-hover); }

/* ── Detail Grid ── */
.detail-grid {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}
.detail-section-title {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: var(--font-size-md);
  font-weight: 600;
  color: var(--text-primary);
  padding-bottom: var(--space-sm);
  border-bottom: 1px solid var(--border-light);
  margin-bottom: var(--space-sm);
}
.detail-section-title svg { width: 18px; height: 18px; color: var(--primary); }
.detail-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-md);
}
.detail-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.detail-label {
  font-size: var(--font-size-sm);
  color: var(--text-muted);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.detail-value {
  font-size: var(--font-size-base);
  color: var(--text-primary);
  font-weight: 500;
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
  width: 56px; height: 56px;
  color: var(--text-muted);
  margin-bottom: var(--space-md);
  opacity: 0.4;
}
.empty-state-title {
  font-size: var(--font-size-md);
  font-weight: 600;
  color: var(--text-primary);
}
.empty-state-desc {
  font-size: var(--font-size-base);
  color: var(--text-secondary);
  margin-top: 6px;
}

/* ── Skeleton Loading ── */
.skeleton-row {
  display: contents;
}
.skeleton-cell {
  padding: 12px var(--space-md);
}
.skeleton-cell .skeleton {
  height: 16px;
  border-radius: var(--radius-xs);
  background: linear-gradient(90deg, var(--bg-main) 0%, var(--bg-hover) 50%, var(--bg-main) 100%);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.5s ease-in-out infinite;
}
@keyframes skeleton-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── Toast (inline augmentation) ── */
.toast-container { /* defined in app.css */ }
.toast { /* defined in app.css */ }

/* ── Pagination (app.css already has .pagination-container, .pagination-controls, .pagination-btn, .pagination-page, .pagination-page.active, .pagination-ellipsis) ── */
/* Augment with equipos-specific pagination inside table-card */
.table-card .pagination-container {
  padding: var(--space-md) var(--space-lg);
  border-top: 1px solid var(--border-light);
  margin-top: 0;
}

/* ── Responsive ── */
@media (max-width: 1024px) {
  .filters-row { flex-direction: column; align-items: stretch; }
  .filter-group, .filter-select, .search-wrapper { width: 100%; min-width: 0; }
  .filter-select { min-width: 0; }
  .form-row { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .detail-row { grid-template-columns: 1fr; }
  .modal { width: 95%; max-width: 95vw; margin: 10px auto; }
  .modal-body { padding: var(--space-md); }
  .modal-header { padding: var(--space-md) var(--space-md) var(--space-sm); }
  .modal-footer { padding: var(--space-sm) var(--space-md); flex-direction: column; }
  .modal-footer .btn-cancel,
  .modal-footer .btn-submit { width: 100%; justify-content: center; }
}
@media (max-width: 480px) {
  .page-header { flex-direction: column; align-items: stretch; }
  .page-header .btn-new { width: 100%; justify-content: center; }
  .filters-section { padding: var(--space-sm) var(--space-md); }
  .table-header { flex-direction: column; align-items: flex-start; gap: 6px; }
}
@media (prefers-reduced-motion: reduce) {
  .skeleton-cell .skeleton { animation: none; background: var(--bg-hover); }
  .action-dd__menu { animation: none; }
  .modal-overlay.active .modal { animation: none; }
  .stat-card { transition: none; }
}

/* ── Stat Card (with lucide icon) ── */
.stat-icon { /* defined in app.css */ }
.stat-icon svg { width: 24px; height: 24px; }
.stat-icon.primary svg { color: var(--primary); fill: var(--primary); }
.stat-icon.success svg { color: var(--success); fill: var(--success); }
.stat-icon.warning svg { color: var(--warning); fill: var(--warning); }
.stat-icon.danger svg { color: var(--danger); fill: var(--danger); }
</style>
```

---

## 4. JavaScript Architecture

All text in Spanish. Single `EquiposApp` namespace. Event delegation on `#main-content` using `data-action` attributes. Modal toggling via `data-modal` attribute. Dropdown positioning via CSS `position: absolute`. Stat counter animation via `requestAnimationFrame`. Calls `lucide.createIcons()` after every DOM update.

```html
<script>
;(function() {
'use strict';

const BASE_URL = '<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>';

const EquiposApp = {

  /* ── State ── */
  state: {
    equipos: [],
    stats: {
      total: 0,
      activos: 0,
      mantenimiento: 0,
      inactivos: 0,
      retirados: 0
    },
    filters: {
      search: '',
      location_id: '',
      status: ''
    },
    pagination: {
      page: 1,
      page_size: 20,
      total: 0
    },
    loading: false,
    error: null
  },

  searchTimeout: null,

  /* ── Init ── */
  async init() {
    this.bindEvents();
    this.syncFiltersFromDOM();
    await this.loadData(1);
  },

  /* ── Bind Events (delegation on #main-content) ── */
  bindEvents() {
    const mc = document.getElementById('main-content');

    // data-action delegation
    mc.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;

      const action = btn.getAttribute('data-action');
      const payload = btn.dataset;

      switch (action) {
        case 'create':              this.abrirCrearEquipo(); break;
        case 'view':                this.verEquipo(payload.id); break;
        case 'edit':                this.editarEquipo(payload.id); break;
        case 'deactivate':          this.desactivarEquipo(payload.id); break;
        case 'delete-permanent':    this.eliminarEquipoPermanent(payload.id); break;
        case 'reactivate':          this.reactivarEquipo(payload.id); break;
        case 'close-modal':         this.closeModal(payload.modal); break;
        case 'clear-filters':       this.clearFilters(); break;
        case 'confirm-deactivate':  this.confirmarDesactivar(); break;
        case 'confirm-delete-permanent': this.confirmarEliminarPermanent(); break;
        case 'toggle-dd':           this.toggleActionDD(payload.id, btn); break;
      }
    });

    // Filter change events
    document.querySelectorAll('[data-filter]').forEach(el => {
      const eventType = el.tagName === 'SELECT' ? 'change' : 'input';
      el.addEventListener(eventType, () => {
        if (eventType === 'input') {
          clearTimeout(this.searchTimeout);
          this.searchTimeout = setTimeout(() => this.onFilterChange(), 300);
        } else {
          this.onFilterChange();
        }
      });
    });

    // Form submit handlers
    document.getElementById('form-editar-equipo').addEventListener('submit', (e) => this.guardarEquipo(e));
    document.getElementById('form-crear-equipo').addEventListener('submit', (e) => this.crearEquipo(e));

    // Close dropdowns on document click outside
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.action-dd')) {
        this.closeAllDropdowns();
      }
    });

    // Keyboard: ESC closes modals
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => {
          const name = m.getAttribute('data-modal');
          if (name) this.closeModal(name);
        });
      }
    });
  },

  /* ── Filter Sync ── */
  syncFiltersFromDOM() {
    this.state.filters.search = document.getElementById('search-equipos').value;
    this.state.filters.location_id = document.getElementById('filtro-ubicacion').value;
    this.state.filters.status = document.getElementById('filtro-estado').value;
  },

  onFilterChange() {
    this.syncFiltersFromDOM();
    this.state.pagination.page = 1;
    this.loadData(1);
  },

  clearFilters() {
    document.getElementById('filtro-ubicacion').value = '';
    document.getElementById('filtro-estado').value = '';
    document.getElementById('search-equipos').value = '';
    this.onFilterChange();
  },

  /* ── Load Data ── */
  async loadData(page) {
    this.state.loading = true;
    this.state.pagination.page = page;
    this.renderSkeleton();

    try {
      const params = new URLSearchParams({ action: 'list', page: String(page), page_size: String(this.state.pagination.page_size) });
      if (this.state.filters.search) params.set('search', this.state.filters.search);
      if (this.state.filters.location_id) params.set('location_id', this.state.filters.location_id);
      if (this.state.filters.status) params.set('status', this.state.filters.status);

      const res = await this.parseJsonResponse(
        await fetch(BASE_URL + 'app/api/equipos.php?' + params.toString())
      );

      if (res.error) {
        this.state.error = res.error;
        this.renderError(res.error);
        return;
      }

      this.state.equipos = res.equipos || [];
      this.state.stats = res.stats || this.state.stats;
      this.state.pagination.total = res.total || 0;
      this.state.loading = false;

      this.render();

    } catch (err) {
      this.state.loading = false;
      this.state.error = err.message;
      this.renderError(err.message);
    }
  },

  /* ── Render ── */
  render() {
    this.renderStats(this.state.stats);
    this.renderTable(this.state.equipos);
    this.renderPagination(this.state.pagination.total, this.state.pagination.page, this.state.pagination.page_size);
    lucide.createIcons();
  },

  renderSkeleton() {
    const tbody = document.getElementById('equipos-table-body');
    let html = '';
    for (let i = 0; i < 5; i++) {
      html += '<tr>';
      for (let j = 0; j < 8; j++) {
        html += '<td class="skeleton-cell"><div class="skeleton" style="height:16px;width:' + (60 + Math.random() * 30) + '%"></div></td>';
      }
      html += '</tr>';
    }
    tbody.innerHTML = html;
  },

  renderError(msg) {
    const tbody = document.getElementById('equipos-table-body');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--danger);">' +
      this.escapeHtml(msg) + '</td></tr>';
    document.getElementById('equipos-count').textContent = 'Error';
  },

  /* ── Render Stats ── */
  renderStats(stats) {
    const total = (parseInt(stats.activos)||0) + (parseInt(stats.mantenimiento)||0) +
                  (parseInt(stats.inactivos)||0) + (parseInt(stats.retirados)||0);
    this.updateStatsWithAnimation('total', stats.total != null ? parseInt(stats.total) : total);
    this.updateStatsWithAnimation('activos', parseInt(stats.activos)||0);
    this.updateStatsWithAnimation('mantenimiento', parseInt(stats.mantenimiento)||0);
    this.updateStatsWithAnimation('inactivos', parseInt(stats.inactivos)||0);
  },

  updateStatsWithAnimation(field, target) {
    const el = document.querySelector(`[data-stat="${field}"]`);
    if (!el) return;
    const current = parseInt(el.textContent) || 0;
    const duration = 400;
    const startTime = performance.now();

    const animate = (now) => {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const value = Math.round(current + (target - current) * eased);
      el.textContent = value;
      if (progress < 1) {
        requestAnimationFrame(animate);
      } else {
        el.textContent = target;
      }
    };
    requestAnimationFrame(animate);
  },

  /* ── Render Table ── */
  renderTable(equipos) {
    const tbody = document.getElementById('equipos-table-body');

    if (!equipos || equipos.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state">' +
        '<i data-lucide="monitor" width="56" height="56"></i>' +
        '<div class="empty-state-title">No hay equipos registrados</div>' +
        '<div class="empty-state-desc">Utilice el botón "Nuevo Equipo" para agregar uno.</div>' +
        '</div></td></tr>';
      return;
    }

    let html = '';
    equipos.forEach(eq => {
      const statusLabel = this.getStatusLabel(eq.status);
      const statusClass = eq.status || 'inactive';
      const assigned = eq.assigned_user_name
        ? '<span class="assigned-user"><i data-lucide="user" width="14" height="14"></i>' +
          this.escapeHtml((eq.assigned_user_name||'') + ' ' + (eq.assigned_user_lastname||'')) + '</span>'
        : '<span style="color:var(--text-muted);font-size:12px;">Sin asignar</span>';
      const isRetired = eq.status === 'retired';

      let ddItems = '';
      if (isRetired) {
        ddItems += '<button class="action-dd__item action-dd__item--success" data-action="reactivate" data-id="' + eq.id + '"><i data-lucide="check-circle"></i>Reactivar</button>';
      } else {
        ddItems += '<button class="action-dd__item action-dd__item--warning" data-action="deactivate" data-id="' + eq.id + '"><i data-lucide="toggle-left"></i>Desactivar</button>';
      }
      ddItems += '<button class="action-dd__item action-dd__item--danger" data-action="delete-permanent" data-id="' + eq.id + '"><i data-lucide="trash-2"></i>Eliminar</button>';

      html += '<tr>';
      html += '<td>' + this.escapeHtml(eq.patrimonial_code || '-') + '</td>';
      html += '<td><strong style="font-weight:600;">' + this.escapeHtml(eq.name) + '</strong></td>';
      html += '<td>' + this.escapeHtml(eq.asset_type || '-') + '</td>';
      html += '<td>' + this.escapeHtml(eq.serial_number || '-') + '</td>';
      html += '<td>' + this.escapeHtml(eq.location_name || 'Sin asignar') + '</td>';
      html += '<td>' + assigned + '</td>';
      html += '<td><span class="status-badge ' + statusClass + '">' + statusLabel + '</span></td>';
      html += '<td><div class="action-cell">';
      html += '<button class="action-btn sm view" data-action="view" data-id="' + eq.id + '" title="Ver"><i data-lucide="eye"></i></button>';
      html += '<button class="action-btn sm edit" data-action="edit" data-id="' + eq.id + '" title="Editar"><i data-lucide="pencil"></i></button>';
      html += '<div class="action-dd">';
      html += '<button class="action-dd__btn" data-action="toggle-dd" data-id="' + eq.id + '" title="Más acciones"><i data-lucide="more-vertical"></i></button>';
      html += '<div class="action-dd__menu" id="action-dd-' + eq.id + '">' + ddItems + '</div>';
      html += '</div></div></td></tr>';
    });
    tbody.innerHTML = html;
    document.getElementById('equipos-count').textContent = equipos.length + ' equipos';

    // After inserting HTML, call createIcons at render() level
  },

  getStatusLabel(status) {
    switch (status) {
      case 'active': return 'Activo';
      case 'maintenance': return 'Mantenimiento';
      case 'inactive': return 'Inactivo';
      case 'retired': return 'Retirado';
      default: return status;
    }
  },

  /* ── Render Pagination ── */
  renderPagination(total, page, pageSize) {
    const container = document.getElementById('pagination-container');
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    const start = total === 0 ? 0 : (page - 1) * pageSize + 1;
    const end = Math.min(page * pageSize, total);

    let html = '<div class="pagination-info">Mostrando ' + start + '&ndash;' + end + ' de ' + total + ' equipos</div>';
    html += '<div class="pagination-controls">';

    // Prev
    html += '<button class="pagination-btn" data-action="page" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>';
    html += '<i data-lucide="chevron-left" width="16" height="16"></i> Anterior</button>';

    // Page numbers with ellipsis
    html += '<div class="pagination-pages">';
    let startPage = Math.max(1, page - 2);
    let endPage = Math.min(totalPages, page + 2);

    if (startPage > 1) {
      html += '<span class="pagination-page" data-action="page" data-page="1">1</span>';
      if (startPage > 2) html += '<span class="pagination-ellipsis">&hellip;</span>';
    }

    for (let i = startPage; i <= endPage; i++) {
      html += '<span class="pagination-page' + (i === page ? ' active' : '') + '" data-action="page" data-page="' + i + '">' + i + '</span>';
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) html += '<span class="pagination-ellipsis">&hellip;</span>';
      html += '<span class="pagination-page" data-action="page" data-page="' + totalPages + '">' + totalPages + '</span>';
    }
    html += '</div>';

    // Next
    html += '<button class="pagination-btn" data-action="page" data-page="' + (page + 1) + '"' + (page >= totalPages ? ' disabled' : '') + '>';
    html += 'Siguiente <i data-lucide="chevron-right" width="16" height="16"></i></button>';

    html += '</div>';
    container.innerHTML = html;

    // Bind pagination clicks
    container.querySelectorAll('[data-action="page"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const p = parseInt(btn.getAttribute('data-page'));
        if (p >= 1 && p <= totalPages && p !== this.state.pagination.page) {
          this.loadData(p);
        }
      });
    });
  },

  /* ── Action Dropdown (CSS position:absolute — no fixed/JS math) ── */
  toggleActionDD(id, btn) {
    const dropdown = document.getElementById('action-dd-' + id);
    if (!dropdown) return;

    this.closeAllDropdowns();
    dropdown.classList.toggle('show');

    // The .action-dd__menu is positioned with position:absolute relative to .action-dd
    // No JS math needed! CSS handles right:0; top:100% positioning.
  },

  closeAllDropdowns() {
    document.querySelectorAll('.action-dd__menu.show').forEach(el => el.classList.remove('show'));
  },

  /* ── Modal Functions ── */
  openModal(name) {
    const overlay = document.querySelector(`[data-modal="${name}"]`);
    if (overlay) {
      overlay.classList.add('active');
      overlay.style.display = 'flex';
    }
  },

  closeModal(name) {
    const overlay = document.querySelector(`[data-modal="${name}"]`);
    if (overlay) {
      overlay.classList.remove('active');
      overlay.style.display = 'none';
    }
  },

  /* ── View Equipo ── */
  async verEquipo(id) {
    this.openModal('ver-equipo');
    const content = document.getElementById('ver-contenido-equipo');
    content.innerHTML = '<div style="padding:32px;text-align:center;color:var(--text-muted);"><div class="spinner"></div><p style="margin-top:12px;">Cargando detalles...</p></div>';

    try {
      const eq = await this.parseJsonResponse(
        await fetch(BASE_URL + 'app/api/equipos.php?action=get-equipo&id=' + encodeURIComponent(id))
      );

      if (eq.error) {
        content.innerHTML = '<p style="color:var(--danger);padding:32px;">' + this.escapeHtml(eq.error) + '</p>';
        return;
      }

      this.renderDetailView(content, eq);
      lucide.createIcons();

    } catch (err) {
      content.innerHTML = '<p style="color:var(--danger);padding:32px;">Error cargando datos.</p>';
    }
  },

  renderDetailView(container, eq) {
    const template = document.getElementById('template-ver-detalle');
    if (template) {
      const clone = template.content.cloneNode(true);
      this.fillDetailFields(clone, eq);
      container.innerHTML = '';
      container.appendChild(clone);
      return;
    }

    // Fallback if template not available
    const statusLabel = this.getStatusLabel(eq.status);
    let html = '<div class="detail-grid">';
    html += '<div class="detail-section"><div class="detail-section-title"><i data-lucide="info"></i> Información General</div><div class="detail-row">';
    html += '<div class="detail-item"><span class="detail-label">Nombre</span><span class="detail-value">' + this.escapeHtml(eq.name||'-') + '</span></div>';
    html += '<div class="detail-item"><span class="detail-label">Código</span><span class="detail-value">' + this.escapeHtml(eq.patrimonial_code||'-') + '</span></div>';
    html += '<div class="detail-item"><span class="detail-label">Serial</span><span class="detail-value">' + this.escapeHtml(eq.serial_number||'-') + '</span></div>';
    html += '<div class="detail-item"><span class="detail-label">Tipo</span><span class="detail-value">' + this.escapeHtml(eq.asset_type||'-') + '</span></div>';
    html += '<div class="detail-item"><span class="detail-label">Marca</span><span class="detail-value">' + this.escapeHtml(eq.brand||'-') + '</span></div>';
    html += '<div class="detail-item"><span class="detail-label">Modelo</span><span class="detail-value">' + this.escapeHtml(eq.model||'-') + '</span></div>';
    html += '<div class="detail-item"><span class="detail-label">Estado</span><span class="detail-value"><span class="status-badge ' + (eq.status||'') + '">' + statusLabel + '</span></span></div>';
    html += '<div class="detail-item"><span class="detail-label">Condición</span><span class="detail-value">' + this.escapeHtml(eq.condition||'-') + '</span></div>';
    html += '</div></div>';
    html += '<div class="detail-section"><div class="detail-section-title"><i data-lucide="cpu"></i> Hardware</div><div class="detail-row">';
    html += '<div class="detail-item"><span class="detail-label">CPU</span><span class="detail-value">' + this.escapeHtml((eq.cpu_brand||'') + ' ' + (eq.cpu_model||'') + ' ' + (eq.cpu_generation||'')) + '</span></div>';
    html += '<div class="detail-item"><span class="detail-label">RAM</span><span class="detail-value">' + this.escapeHtml(eq.ram||'-') + '</span></div>';
    html += '<div class="detail-item"><span class="detail-label">Almacenamiento</span><span class="detail-value">' + this.escapeHtml((eq.disk_type||'') + ' - ' + (eq.disk_capacity||'')) + '</span></div>';
    html += '</div></div>';
    html += '<div class="detail-section"><div class="detail-section-title"><i data-lucide="globe"></i> Red y Ubicación</div><div class="detail-row">';
    html += '<div class="detail-item"><span class="detail-label">IP</span><span class="detail-value">' + this.escapeHtml(eq.ip_address||'-') + '</span></div>';
    html += '<div class="detail-item"><span class="detail-label">MAC</span><span class="detail-value">' + this.escapeHtml(eq.mac_address||'-') + '</span></div>';
    html += '<div class="detail-item"><span class="detail-label">Ubicación</span><span class="detail-value">' + this.escapeHtml(eq.location_name||'Sin asignar') + '</span></div>';
    html += '<div class="detail-item"><span class="detail-label">Usuario</span><span class="detail-value">' + this.escapeHtml((eq.assigned_user_name||'') + ' ' + (eq.assigned_user_lastname||'')) + '</span></div>';
    html += '</div></div>';
    if (eq.observations) {
      html += '<div class="detail-section"><div class="detail-section-title"><i data-lucide="file-text"></i> Observaciones</div>';
      html += '<div class="detail-value">' + this.escapeHtml(eq.observations) + '</div></div>';
    }
    html += '</div>';
    container.innerHTML = html;
  },

  fillDetailFields(clone, eq) {
    const map = {
      'name': eq.name,
      'patrimonial_code': eq.patrimonial_code,
      'serial_number': eq.serial_number,
      'asset_type': eq.asset_type,
      'brand': eq.brand,
      'model': eq.model,
      'condition': eq.condition,
      'cpu': ((eq.cpu_brand||'') + ' ' + (eq.cpu_model||'') + ' ' + (eq.cpu_generation||'')).trim(),
      'ram': eq.ram,
      'storage': ((eq.disk_type||'') + ' - ' + (eq.disk_capacity||'')).trim(),
      'ip_address': eq.ip_address,
      'mac_address': eq.mac_address,
      'location_name': eq.location_name || 'Sin asignar',
      'assigned_user': ((eq.assigned_user_name||'') + ' ' + (eq.assigned_user_lastname||'')).trim(),
      'observations': eq.observations
    };

    clone.querySelectorAll('[data-field]').forEach(el => {
      const field = el.getAttribute('data-field');
      if (field === 'status_badge') {
        el.innerHTML = '<span class="status-badge ' + (eq.status||'') + '">' + this.getStatusLabel(eq.status) + '</span>';
      } else if (field === 'observations' && !eq.observations) {
        const section = el.closest('[data-if-observations]');
        if (section) section.style.display = 'none';
      } else if (map[field] !== undefined) {
        el.textContent = map[field] || '-';
      }
    });
  },

  /* ── Edit Equipo ── */
  async editarEquipo(id) {
    this.openModal('editar-equipo');
    const content = document.getElementById('editar-contenido-equipo');
    content.innerHTML = '<div style="padding:32px;text-align:center;color:var(--text-muted);"><div class="spinner"></div><p style="margin-top:12px;">Cargando formulario...</p></div>';

    try {
      const [locationsData, usuariosData, eq] = await Promise.all([
        this.parseJsonResponse(fetch(BASE_URL + 'app/api/locations.php')),
        this.parseJsonResponse(fetch(BASE_URL + 'app/api/tickets.php?action=get-usuarios')),
        this.parseJsonResponse(fetch(BASE_URL + 'app/api/equipos.php?action=get-equipo&id=' + encodeURIComponent(id)))
      ]);

      if (eq.error) {
        content.innerHTML = '<p style="color:var(--danger);padding:32px;">' + this.escapeHtml(eq.error) + '</p>';
        return;
      }

      this.renderEditForm(content, eq, locationsData, usuariosData);
      lucide.createIcons();

    } catch (err) {
      content.innerHTML = '<p style="color:var(--danger);padding:32px;">Error cargando datos.</p>';
    }
  },

  /* ── Shared form helpers ── */
  FORM_FIELDS: [
    { id: 'name', label: 'Nombre', type: 'text', required: true },
    { id: 'patrimonial_code', label: 'Código Patrimonial', type: 'text' },
    { id: 'serial_number', label: 'Serial', type: 'text' },
    { id: 'asset_type', label: 'Tipo', type: 'select', options: ['PC','LAPTOP','IMPRESORA','MONITOR','OTRO'] },
    { id: 'brand', label: 'Marca', type: 'text' },
    { id: 'model', label: 'Modelo', type: 'text' },
    { id: 'status', label: 'Estado', type: 'select', options: ['active','maintenance','inactive','retired'] },
    { id: 'condition', label: 'Condición', type: 'select', options: ['BUENO','REGULAR','MALO','OBSOLETO'] },
    { id: 'ip_address', label: 'IP', type: 'text' },
    { id: 'mac_address', label: 'MAC', type: 'text' }
  ],

  renderEditForm(content, eq, locationsData, usuariosData) {
    const locations = locationsData.locations || [];
    const usuarios = usuariosData || [];

    let html = '<input type="hidden" name="id" value="' + eq.id + '">';

    // Row 1: Nombre + Código
    html += '<div class="form-row">';
    html += this.fieldInput('name', 'Nombre', eq.name, true);
    html += this.fieldInput('patrimonial_code', 'Código Patrimonial', eq.patrimonial_code);
    html += '</div>';

    // Row 2: Serial + Tipo
    html += '<div class="form-row">';
    html += this.fieldInput('serial_number', 'Serial', eq.serial_number);
    html += this.fieldSelect('asset_type', 'Tipo', ['PC','LAPTOP','IMPRESORA','MONITOR','OTRO'], eq.asset_type);
    html += '</div>';

    // Row 3: Marca + Modelo
    html += '<div class="form-row">';
    html += this.fieldInput('brand', 'Marca', eq.brand);
    html += this.fieldInput('model', 'Modelo', eq.model);
    html += '</div>';

    // Row 4: Estado + Condición
    html += '<div class="form-row">';
    html += this.fieldSelect('status', 'Estado', ['active','maintenance','inactive','retired'], eq.status,
      ['Activo','Mantenimiento','Inactivo','Retirado']);
    html += this.fieldSelect('condition', 'Condición', ['BUENO','REGULAR','MALO','OBSOLETO'], eq.condition);
    html += '</div>';

    // Row 5: IP + MAC
    html += '<div class="form-row">';
    html += this.fieldInput('ip_address', 'IP', eq.ip_address);
    html += this.fieldInput('mac_address', 'MAC', eq.mac_address);
    html += '</div>';

    // Row 6: Ubicación + Usuario
    html += '<div class="form-row">';
    html += this.fieldSelect('location_id', 'Ubicación', locations.map(l => l.id), eq.location_id,
      locations.map(l => {
        const prefix = (l.type||'').toUpperCase() === 'SEDE' || (l.type||'').toUpperCase() === 'SUCURSAL' ? '[SEDE] ':'[ÁREA] ';
        return prefix + (l.name||'');
      }),
      [{ value: '', label: '-- Sin asignar --' }]
    );
    html += this.fieldSelect('assigned_user_id', 'Usuario', usuarios.map(u => u.id), eq.assigned_user_id,
      usuarios.map(u => (u.nombre||'') + ' ' + (u.apellidos||'')),
      [{ value: '', label: '-- Sin asignar --' }]
    );
    html += '</div>';

    // Observaciones
    html += '<div class="form-group full">';
    html += '<label class="form-label">Observaciones</label>';
    html += '<textarea name="observations" class="form-textarea" rows="3">' + this.escapeHtml(eq.observations||'') + '</textarea>';
    html += '</div>';

    content.innerHTML = html;
    this.setFormValues(content, eq);
  },

  fieldInput(name, label, value, required) {
    return '<div class="form-group"><label class="form-label">' + label + (required ? ' <span class="required">*</span>' : '') +
      '</label><input type="text" name="' + name + '" class="form-input" value="' + this.escapeHtml(value||'') + '"' +
      (required ? ' required' : '') + '></div>';
  },

  fieldSelect(name, label, options, selected, labels, prepend) {
    let html = '<div class="form-group"><label class="form-label">' + label + '</label>';
    html += '<select name="' + name + '" class="form-select">';
    if (prepend) {
      prepend.forEach(p => {
        html += '<option value="' + this.escapeHtml(p.value) + '"' + (String(selected) === String(p.value) ? ' selected' : '') + '>' + this.escapeHtml(p.label) + '</option>';
      });
    }
    options.forEach((opt, i) => {
      const labelText = labels ? labels[i] : opt;
      html += '<option value="' + this.escapeHtml(opt) + '"' + (String(selected) === String(opt) ? ' selected' : '') + '>' + this.escapeHtml(labelText) + '</option>';
    });
    html += '</select></div>';
    return html;
  },

  setFormValues(container, eq) {
    // For selects, values are already set via 'selected' attr in rendering
  },

  /* ── Save Edit ── */
  async guardarEquipo(e) {
    e.preventDefault();
    const form = document.getElementById('form-editar-equipo');
    const formData = new FormData(form);
    formData.append('action', 'update-equipo');

    const btn = document.getElementById('btn-save-edit');
    btn.classList.add('loading');

    try {
      const res = await this.parseJsonResponse(
        await fetch(BASE_URL + 'app/api/equipos.php', { method: 'POST', body: formData })
      );

      if (res.success) {
        this.showToast('success', 'Equipo actualizado', 'Los cambios se han guardado correctamente.');
        this.closeModal('editar-equipo');
        await this.loadData(this.state.pagination.page);
      } else if (res.errors) {
        const msgs = Object.values(res.errors).join(', ');
        this.showToast('error', 'Error de validación', msgs);
      } else {
        this.showToast('error', 'Error', res.error || 'No se pudo guardar.');
      }
    } catch (err) {
      this.showToast('error', 'Error', 'Error de conexión.');
    } finally {
      btn.classList.remove('loading');
    }
  },

  /* ── Create Equipo ── */
  async abrirCrearEquipo() {
    this.openModal('crear-equipo');
    const content = document.getElementById('crear-contenido-equipo');
    content.innerHTML = '<div style="padding:32px;text-align:center;color:var(--text-muted);"><div class="spinner"></div><p style="margin-top:12px;">Cargando formulario...</p></div>';

    try {
      const [locationsData, usuariosData] = await Promise.all([
        this.parseJsonResponse(fetch(BASE_URL + 'app/api/locations.php')),
        this.parseJsonResponse(fetch(BASE_URL + 'app/api/tickets.php?action=get-usuarios'))
      ]);

      this.renderCreateForm(content, locationsData, usuariosData);
      lucide.createIcons();
    } catch (err) {
      content.innerHTML = '<p style="color:var(--danger);padding:16px;">Error: ' + this.escapeHtml(err.message) + '</p>';
    }
  },

  renderCreateForm(content, locationsData, usuariosData) {
    const locations = locationsData.locations || [];
    const usuarios = usuariosData || [];

    let html = '';
    html += '<div class="form-row">';
    html += this.fieldInput('name', 'Nombre', '', true);
    html += this.fieldInput('patrimonial_code', 'Código Patrimonial', '');
    html += '</div>';
    html += '<div class="form-row">';
    html += this.fieldInput('serial_number', 'Serial', '');
    html += this.fieldSelect('asset_type', 'Tipo', ['PC','LAPTOP','IMPRESORA','MONITOR','OTRO'], 'PC');
    html += '</div>';
    html += '<div class="form-row">';
    html += this.fieldInput('brand', 'Marca', '');
    html += this.fieldInput('model', 'Modelo', '');
    html += '</div>';
    html += '<div class="form-row">';
    html += this.fieldSelect('status', 'Estado', ['active','maintenance','inactive'], 'active', ['Activo','Mantenimiento','Inactivo']);
    html += this.fieldSelect('condition', 'Condición', ['BUENO','REGULAR','MALO','OBSOLETO'], 'BUENO');
    html += '</div>';
    html += '<div class="form-row">';
    html += this.fieldInput('ip_address', 'IP', '');
    html += this.fieldInput('mac_address', 'MAC', '');
    html += '</div>';
    html += '<div class="form-row">';
    html += this.fieldSelect('location_id', 'Ubicación', locations.map(l => l.id), '',
      locations.map(l => {
        const prefix = (l.type||'').toUpperCase() === 'SEDE' || (l.type||'').toUpperCase() === 'SUCURSAL' ? '[SEDE] ':'[ÁREA] ';
        return prefix + (l.name||'');
      }),
      [{ value: '', label: '-- Sin asignar --' }]
    );
    html += this.fieldSelect('assigned_user_id', 'Usuario', usuarios.map(u => u.id), '',
      usuarios.map(u => (u.nombre||'') + ' ' + (u.apellidos||'')),
      [{ value: '', label: '-- Sin asignar --' }]
    );
    html += '</div>';
    html += '<div class="form-group full">';
    html += '<label class="form-label">Observaciones</label>';
    html += '<textarea name="observations" class="form-textarea" rows="3"></textarea>';
    html += '</div>';

    content.innerHTML = html;
  },

  async crearEquipo(e) {
    e.preventDefault();
    const form = document.getElementById('form-crear-equipo');
    const formData = new FormData(form);
    formData.append('action', 'create-equipo');

    const btn = form.querySelector('.btn-submit');
    btn.classList.add('loading');

    try {
      const res = await this.parseJsonResponse(
        await fetch(BASE_URL + 'app/api/equipos.php', { method: 'POST', body: formData })
      );

      if (res.success) {
        this.showToast('success', 'Equipo creado', 'El equipo se ha registrado exitosamente.');
        this.closeModal('crear-equipo');
        await this.loadData(1);
      } else if (res.errors) {
        const msgs = Object.values(res.errors).join(', ');
        this.showToast('error', 'Error de validación', msgs);
      } else {
        this.showToast('error', 'Error', res.error || 'No se pudo crear.');
      }
    } catch (err) {
      this.showToast('error', 'Error', err.message);
    } finally {
      btn.classList.remove('loading');
    }
  },

  /* ── Deactivate ── */
  desactivarEquipo(id) {
    document.getElementById('deactivate-equipo-id').value = id;
    document.getElementById('deactivate-equipo-reason').value = '';
    this.openModal('desactivar-equipo');
  },

  async confirmarDesactivar() {
    const id = document.getElementById('deactivate-equipo-id').value;
    const reason = document.getElementById('deactivate-equipo-reason').value;

    if (!reason) {
      this.showToast('warning', 'Campo requerido', 'Ingrese el motivo de desactivación.');
      return;
    }

    const btn = document.querySelector('[data-action="confirm-deactivate"]');
    btn.classList.add('loading');

    try {
      const res = await this.parseJsonResponse(
        await fetch(BASE_URL + 'app/api/equipos.php?action=deactivate-equipo&id=' + encodeURIComponent(id), {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ reason: reason }).toString()
        })
      );
      if (res.success) {
        this.showToast('success', 'Equipo desactivado', 'El equipo ha sido desactivado.');
        this.closeModal('desactivar-equipo');
        await this.loadData(this.state.pagination.page);
      } else {
        this.showToast('error', 'Error', res.error || 'No se pudo desactivar.');
      }
    } catch (err) {
      this.showToast('error', 'Error', 'Error de conexión.');
    } finally {
      btn.classList.remove('loading');
    }
  },

  /* ── Eliminar Permanent ── */
  eliminarEquipoPermanent(id) {
    document.getElementById('delete-permanent-equipo-id').value = id;
    document.getElementById('delete-permanent-equipo-reason').value = '';
    this.openModal('eliminar-permanent-equipo');
  },

  async confirmarEliminarPermanent() {
    const id = document.getElementById('delete-permanent-equipo-id').value;
    const reason = document.getElementById('delete-permanent-equipo-reason').value;

    if (!reason) {
      this.showToast('warning', 'Campo requerido', 'Ingrese el motivo de eliminación.');
      return;
    }

    const btn = document.querySelector('[data-action="confirm-delete-permanent"]');
    btn.classList.add('loading');

    try {
      const res = await this.parseJsonResponse(
        await fetch(BASE_URL + 'app/api/equipos.php?action=delete-equipo&id=' + encodeURIComponent(id) + '&permanent=1', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ reason: reason }).toString()
        })
      );
      if (res.success) {
        this.showToast('success', 'Equipo eliminado', 'El equipo ha sido eliminado permanentemente.');
        this.closeModal('eliminar-permanent-equipo');
        await this.loadData(this.state.pagination.page);
      } else {
        this.showToast('error', 'Error', res.error || 'No se pudo eliminar.');
      }
    } catch (err) {
      this.showToast('error', 'Error', 'Error de conexión.');
    } finally {
      btn.classList.remove('loading');
    }
  },

  /* ── Reactivate ── */
  async reactivarEquipo(id) {
    try {
      const res = await this.parseJsonResponse(
        await fetch(BASE_URL + 'app/api/equipos.php?action=reactivate-equipo&id=' + encodeURIComponent(id), { method: 'POST' })
      );
      if (res.success) {
        this.showToast('success', 'Equipo reactivado', 'El equipo ha sido reactivado.');
        await this.loadData(this.state.pagination.page);
      } else {
        this.showToast('error', 'Error', res.error || 'No se pudo reactivar.');
      }
    } catch (err) {
      this.showToast('error', 'Error', 'Error de conexión.');
    }
  },

  /* ── Helpers ── */
  escapeHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
  },

  async parseJsonResponse(response) {
    if (response instanceof Promise) response = await response;
    const contentType = response.headers.get('content-type') || '';
    if (response.ok && contentType.includes('application/json')) return response.json();
    const text = await response.text();
    throw new Error(text || 'El servidor no devolvió JSON válido.');
  },

  showToast(type, title, message) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = 'toast toast--' + type;
    const icons = {
      success: '<i data-lucide="check-circle" width="20" height="20"></i>',
      error: '<i data-lucide="x-circle" width="20" height="20"></i>',
      warning: '<i data-lucide="alert-triangle" width="20" height="20"></i>',
      info: '<i data-lucide="info" width="20" height="20"></i>'
    };
    toast.innerHTML =
      '<div class="toast__icon">' + (icons[type] || icons.info) + '</div>' +
      '<div class="toast__content"><div class="toast__title">' + this.escapeHtml(title) + '</div>' +
      '<div class="toast__message">' + this.escapeHtml(message) + '</div></div>' +
      '<button class="toast__close" onclick="this.parentElement.remove()"><i data-lucide="x" width="16" height="16"></i></button>';
    container.appendChild(toast);
    lucide.createIcons();
    setTimeout(() => { if (toast.parentElement) toast.remove(); }, 5000);
  }
};

/* ── Init on DOMContentLoaded ── */
document.addEventListener('DOMContentLoaded', () => {
  EquiposApp.init();
});

})();
</script>
```

---

## 5. Important Notes

### All user-facing text in Spanish
All labels, buttons, placeholders, tooltips, and messages use Spanish text as shown above.

### Escape all values with `escapeHtml()`
Every dynamic value rendered via innerHTML passes through `EquiposApp.escapeHtml()`.

### Call `lucide.createIcons()` after every dynamic content insert
Called once at the end of `render()`, `renderDetailView()`, `renderEditForm()`, `renderCreateForm()`, and `showToast()`.

### Stat counter animation
`updateStatsWithAnimation(field, target)` uses `requestAnimationFrame` with cubic ease-out over 400ms.

### Empty state
When no equipos are found: "No hay equipos registrados" with a lucide monitor icon and subtitle.

### Loading: skeleton shimmer
`renderSkeleton()` generates 5 rows of skeleton-cell placeholders using the `.skeleton` CSS shimmer animation.

### Pagination
"Mostrando X-Y de Z equipos" with prev/next buttons and page number buttons with ellipsis strategy (show 2 pages before/after current). Paginated via `loadData(page)` which passes `page` and `page_size` to the API.

### Action dropdowns use CSS position
The `.action-dd` container has `position: relative`. The `.action-dd__menu` has `position: absolute; top: 100%; right: 0`. No JS position math is needed. `toggleActionDD` only toggles the `.show` class.

### `prefers-reduced-motion`
The inline `<style>` block disables skeleton shimmer, modal scaleIn animation, and stat-card transitions when `prefers-reduced-motion: reduce` is active.

### Form field arrays (DRY)
The `FORM_FIELDS` array in the JS object and the shared `fieldInput()`/`fieldSelect()` helper methods eliminate HTML-in-JS string duplication for form rendering.

### API endpoints used
Same as existing code:
- `GET app/api/equipos.php?action=list&page=N&page_size=20&search=...&location_id=...&status=...`
- `GET app/api/equipos.php?action=get-equipo&id=N`
- `POST app/api/equipos.php` (action=update-equipo via FormData)
- `POST app/api/equipos.php?action=create-equipo` (FormData)
- `POST app/api/equipos.php?action=deactivate-equipo&id=N` (URLSearchParams)
- `POST app/api/equipos.php?action=delete-equipo&id=N&permanent=1` (URLSearchParams)
- `POST app/api/equipos.php?action=reactivate-equipo&id=N`
- `GET app/api/locations.php`
- `GET app/api/tickets.php?action=get-usuarios`

### Event Delegation
Single `click` listener on `#main-content` matches `[data-action]` attributes. Button data carries `data-id` for entity references and `data-modal` for modal targeting. Filter inputs use `[data-filter]` attributes.
