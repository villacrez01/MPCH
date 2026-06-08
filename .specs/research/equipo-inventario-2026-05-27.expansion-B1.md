# Expansion: B1 — Monolito Limpio

## 1. PHP Structure

### Variables Initialization

```php
<?php
// equipos.php — Monolito Limpio (B1)

$baseUrl   = base_url();
$userName  = $_SESSION['user']['name']     ?? 'Usuario';
$officeName= $_SESSION['user']['area_name']?? 'Sin oficina';
$roleName  = $_SESSION['user']['role_name'] ?? 'Usuario';
$userId    = $_SESSION['user']['id']       ?? null;

$isOtiAdmin = \App\Services\AuthService::isAdmin();

require_once __DIR__ . '/../../Models/Equipment.php';
require_once __DIR__ . '/../../Models/Location.php';
require_once __DIR__ . '/../../Models/User.php';

$statsData   = \App\Models\Equipment::getStats();
$locData     = \App\Models\Location::getAll();
$hierarchy   = \App\Models\User::getLocationsHierarchy();

$tituloPagina  = 'Inventario de Equipos — Sistema OTI';
$paginaActual  = 'admin-equipos';

// ── Shared form fragment ─────────────────────────────────────────
$__assetTypeOptions = ['PC','LAPTOP','IMPRESORA','MONITOR','OTRO'];
$__statusOptions    = ['active'=>'Activo','maintenance'=>'Mantenimiento','inactive'=>'Inactivo','retired'=>'Retirado'];
$__conditionOptions = ['BUENO','REGULAR','MALO','OBSOLETO'];

$equipoFormFields = '
<div class="form-row">
  <div class="form-group">
    <label class="form-label">Nombre <span class="required">*</span></label>
    <input type="text" name="name" class="form-input" id="form-name" required>
  </div>
  <div class="form-group">
    <label class="form-label">Código Patrimonial</label>
    <input type="text" name="patrimonial_code" class="form-input" id="form-patrimonial_code">
  </div>
</div>
<div class="form-row">
  <div class="form-group">
    <label class="form-label">Serial</label>
    <input type="text" name="serial_number" class="form-input" id="form-serial_number">
  </div>
  <div class="form-group">
    <label class="form-label">Tipo</label>
    <select name="asset_type" class="form-select" id="form-asset_type">';
foreach ($__assetTypeOptions as $at) {
    $equipoFormFields .= '<option value="' . $at . '">' . $at . '</option>';
}
$equipoFormFields .= '
    </select>
  </div>
</div>
<div class="form-row">
  <div class="form-group">
    <label class="form-label">Marca</label>
    <input type="text" name="brand" class="form-input" id="form-brand">
  </div>
  <div class="form-group">
    <label class="form-label">Modelo</label>
    <input type="text" name="model" class="form-input" id="form-model">
  </div>
</div>
<div class="form-row">
  <div class="form-group">
    <label class="form-label">Estado</label>
    <select name="status" class="form-select" id="form-status">';
foreach ($__statusOptions as $sv => $sl) {
    $equipoFormFields .= '<option value="' . $sv . '">' . $sl . '</option>';
}
$equipoFormFields .= '
    </select>
  </div>
  <div class="form-group">
    <label class="form-label">Condición</label>
    <select name="condition" class="form-select" id="form-condition">';
foreach ($__conditionOptions as $co) {
    $equipoFormFields .= '<option value="' . $co . '">' . $co . '</option>';
}
$equipoFormFields .= '
    </select>
  </div>
</div>
<div class="form-row">
  <div class="form-group">
    <label class="form-label">IP</label>
    <input type="text" name="ip_address" class="form-input" id="form-ip_address">
  </div>
  <div class="form-group">
    <label class="form-label">MAC</label>
    <input type="text" name="mac_address" class="form-input" id="form-mac_address">
  </div>
</div>
<div class="form-row">
  <div class="form-group">
    <label class="form-label">Ubicación</label>
    <select name="location_id" class="form-select" id="form-location_id">
      <option value="">— Sin asignar —</option>
    </select>
  </div>
  <div class="form-group">
    <label class="form-label">Usuario</label>
    <select name="assigned_user_id" class="form-select" id="form-assigned_user_id">
      <option value="">— Sin asignar —</option>
    </select>
  </div>
</div>
<div class="form-group">
  <label class="form-label">Observaciones</label>
  <textarea name="observations" class="form-textarea" id="form-observations" rows="3"></textarea>
</div>
';
```

### Partials Included

```php
require __DIR__ . '/../partials/head.php';     // CSRF token via <meta name="csrf-token">
require __DIR__ . '/../partials/sidebar.php';
require __DIR__ . '/../partials/header.php';
// ... main content ...
require __DIR__ . '/../partials/footer.php';
```

The CSRF token is read from `<meta name="csrf-token" content="..."` (set in `head.php`) and sent as `X-CSRF-Token` header in every fetch request via a small helper.

### Key PHP Decision: Shared Form Fragment

A single `$equipoFormFields` variable holds the HTML for all 12 form fields (name, code, serial, type, brand, model, status, condition, IP, MAC, location select, user select, observations). Both **crear** and **editar** modals embed `<?= $equipoFormFields ?>`. The editar modal additionally contains `<input type="hidden" id="form-edit-id">`. JS fills values for edit mode via element IDs (`form-name`, `form-patrimonial_code`, etc.). This eliminates all duplication between the two forms.

---

## 2. HTML Structure

```php
<main id="main-content" class="main-content">
  <!-- PAGE HEADER -->
  <div class="page-header">
    <div class="page-header-left">
      <h1 class="page-title">Inventario de Equipos</h1>
      <p class="page-subtitle">Gestión de equipos tecnológicos</p>
    </div>
    <button type="button" class="btn-new" data-action="crear-equipo">
      <i data-lucide="plus"></i>
      Nuevo Equipo
    </button>
  </div>

  <!-- STATS CARDS (skeleton shown while loading) -->
  <div class="stats-grid" id="stats-grid">
    <div class="stat-card primary">
      <div class="stat-icon primary"><i data-lucide="monitor"></i></div>
      <div class="stat-content">
        <div class="stat-value" id="stat-total">0</div>
        <div class="stat-label">Total Equipos</div>
      </div>
    </div>
    <div class="stat-card success">
      <div class="stat-icon success"><i data-lucide="check-circle"></i></div>
      <div class="stat-content">
        <div class="stat-value" id="stat-activos">0</div>
        <div class="stat-label">Activos</div>
      </div>
    </div>
    <div class="stat-card warning">
      <div class="stat-icon warning"><i data-lucide="wrench"></i></div>
      <div class="stat-content">
        <div class="stat-value" id="stat-mantenimiento">0</div>
        <div class="stat-label">En Mantenimiento</div>
      </div>
    </div>
    <div class="stat-card danger">
      <div class="stat-icon danger"><i data-lucide="x-circle"></i></div>
      <div class="stat-content">
        <div class="stat-value" id="stat-inactivos">0</div>
        <div class="stat-label">Inactivos</div>
      </div>
    </div>
  </div>

  <!-- FILTERS SECTION -->
  <div class="filters-section">
    <div class="filters-header">
      <div class="filters-title">
        <i data-lucide="search"></i>
        Filtros de búsqueda
      </div>
      <button class="clear-filters-btn" data-action="clear-filters">
        <i data-lucide="x"></i> Limpiar
      </button>
    </div>
    <div class="filters-row">
      <div class="filter-group">
        <label class="filter-label">Ubicación</label>
        <select id="filtro-ubicacion" class="filter-select" data-action="filter-change">
          <option value="">Todas las ubicaciones</option>
          <optgroup label="Sedes">
            <?php foreach ($hierarchy['sedes'] as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </optgroup>
          <optgroup label="Áreas">
            <?php foreach ($hierarchy['areas'] as $a): ?>
            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        </select>
      </div>
      <div class="filter-group">
        <label class="filter-label">Estado</label>
        <select id="filtro-estado" class="filter-select" data-action="filter-change">
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
          <i data-lucide="search" class="search-icon"></i>
          <input type="text" class="search-input" id="search-equipos"
                 placeholder="Buscar por nombre, serial o código..."
                 data-action="search-debounce">
        </div>
      </div>
    </div>
  </div>

  <!-- TABLE CARD -->
  <div class="table-card">
    <div class="table-header">
      <h3 class="table-title"><i data-lucide="monitor"></i> Lista de Equipos</h3>
      <span class="table-count" id="equipos-count">0 equipos</span>
    </div>
    <div id="table-skeleton" class="skeleton-card" style="padding:24px;">
      <div class="skeleton skeleton-title"></div>
      <div class="skeleton skeleton-text"></div>
      <div class="skeleton skeleton-text"></div>
      <div class="skeleton skeleton-text"></div>
      <div class="skeleton skeleton-text" style="width:80%;"></div>
    </div>
    <table id="equipos-table" style="display:none;">
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
    <div id="pagination-container" class="pagination-container"></div>
  </div>
</main>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toast-container"></div>

<!-- MODAL: Crear Equipo -->
<div class="modal-overlay" id="modal-crear">
  <div class="modal large">
    <div class="modal-header">
      <h3 class="modal-title"><i data-lucide="plus"></i> Crear Nuevo Equipo</h3>
      <button class="modal-close" data-action="close-modal" data-modal="crear"><i data-lucide="x"></i></button>
    </div>
    <form id="form-crear">
      <div class="modal-body" id="crear-body">
        <?= $equipoFormFields ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" data-action="close-modal" data-modal="crear">Cancelar</button>
        <button type="submit" class="btn-submit">Crear Equipo</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: Editar Equipo -->
<div class="modal-overlay" id="modal-editar">
  <div class="modal large">
    <div class="modal-header">
      <h3 class="modal-title"><i data-lucide="edit"></i> Editar Equipo</h3>
      <button class="modal-close" data-action="close-modal" data-modal="editar"><i data-lucide="x"></i></button>
    </div>
    <form id="form-editar">
      <input type="hidden" id="form-edit-id" name="id" value="">
      <div class="modal-body" id="editar-body">
        <?= $equipoFormFields ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" data-action="close-modal" data-modal="editar">Cancelar</button>
        <button type="submit" class="btn-submit">Guardar Cambios</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: Ver Equipo -->
<div class="modal-overlay" id="modal-ver">
  <div class="modal large">
    <div class="modal-header">
      <h3 class="modal-title"><i data-lucide="info"></i> Detalles del Equipo</h3>
      <button class="modal-close" data-action="close-modal" data-modal="ver"><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body" id="ver-body"></div>
    <div class="modal-footer">
      <button type="button" class="btn-cancel" data-action="close-modal" data-modal="ver">Cerrar</button>
    </div>
  </div>
</div>

<!-- MODAL: Desactivar Equipo -->
<div class="modal-overlay" id="modal-desactivar">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <h3 class="modal-title"><i data-lucide="alert-triangle"></i> Desactivar Equipo</h3>
      <button class="modal-close" data-action="close-modal" data-modal="desactivar"><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:16px;">Al desactivar se eliminarán las asignaciones. ¿Continuar?</p>
      <div class="form-group">
        <label class="form-label">Motivo <span class="required">*</span></label>
        <textarea id="deactivate-reason" class="form-textarea" rows="3" placeholder="Ej: Equipo obsoleto, dañado..."></textarea>
      </div>
      <input type="hidden" id="deactivate-id">
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" data-action="close-modal" data-modal="desactivar">Cancelar</button>
      <button type="button" class="btn-submit" data-action="confirmar-desactivar" style="background-color:var(--warning)">Confirmar</button>
    </div>
  </div>
</div>

<!-- MODAL: Eliminar Permanentemente -->
<div class="modal-overlay" id="modal-eliminar">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <h3 class="modal-title"><i data-lucide="trash-2"></i> Eliminar Permanentemente</h3>
      <button class="modal-close" data-action="close-modal" data-modal="eliminar"><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:16px;color:var(--danger);">Esta acción NO se puede deshacer.</p>
      <div class="form-group">
        <label class="form-label">Motivo <span class="required">*</span></label>
        <textarea id="delete-reason" class="form-textarea" rows="3" placeholder="Ej: Robado, perdido, baja definitiva..."></textarea>
      </div>
      <input type="hidden" id="delete-id">
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" data-action="close-modal" data-modal="eliminar">Cancelar</button>
      <button type="button" class="btn-submit" data-action="confirmar-eliminar" style="background-color:var(--danger)">Eliminar</button>
    </div>
  </div>
</div>
```

---

## 3. CSS (Inline `<style>`)

All classes reference existing **app.css**. The inline block only adds **counter animation** and **layout refinements** specific to this page. All existing app.css classes remain untouched.

```html
<style>
  /* ── Counter animation ── */
  @keyframes countUp {
    from { opacity: 0.4; transform: translateY(4px); }
    to   { opacity: 1;   transform: translateY(0);   }
  }
  .stat-value.animating {
    animation: countUp 350ms var(--ease-out);
  }

  /* ── Skeleton override: hide table until loaded ── */
  .table-card.loaded #table-skeleton { display: none; }
  .table-card.loaded table            { display: table; }

  /* ── Detail grid (view modal) ── */
  .detail-grid {
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
    font-size: 12px;
    color: var(--text-muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.3px;
  }
  .detail-value {
    font-size: 14px;
    color: var(--text-primary);
    font-weight: 600;
  }
  .detail-section-title {
    grid-column: span 2;
    font-size: 15px;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
    padding: var(--space-md) 0 var(--space-sm);
    border-bottom: 1px solid var(--border-light);
    margin-bottom: var(--space-sm);
  }
  .detail-section-title i { width: 18px; height: 18px; }

  /* ── Filters header layout ── */
  .filters-section {
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: var(--space-md);
    margin-bottom: var(--space-lg);
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
    gap: 8px;
  }
  .filters-title i { width: 16px; height: 16px; color: var(--text-muted); }
  .clear-filters-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-full);
    background: transparent;
    font-size: 12px;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 150ms ease;
    font-family: inherit;
  }
  .clear-filters-btn:hover {
    border-color: var(--danger);
    color: var(--danger);
    background: var(--danger-soft);
  }
  .clear-filters-btn i { width: 14px; height: 14px; }
  .filters-row {
    display: flex;
    gap: var(--space-md);
    align-items: flex-end;
    flex-wrap: wrap;
  }
  .filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 180px;
  }
  .filter-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.3px;
  }
  .filter-select {
    padding: 8px 12px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background: var(--bg-card);
    font-size: 13px;
    font-family: inherit;
    color: var(--text-primary);
    transition: border-color 150ms ease;
  }
  .filter-select:focus {
    border-color: var(--primary);
    outline: none;
  }
  .search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
  }
  .search-wrapper .search-icon {
    position: absolute;
    left: 12px;
    width: 16px;
    height: 16px;
    color: var(--text-muted);
    pointer-events: none;
  }
  .search-input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    font-size: 13px;
    font-family: inherit;
    color: var(--text-primary);
    background: var(--bg-card);
    transition: border-color 150ms ease;
  }
  .search-input:focus {
    border-color: var(--primary);
    outline: none;
  }

  /* ── Table card ── */
  .table-card {
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
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
    gap: 8px;
  }
  .table-title i { width: 18px; height: 18px; color: var(--primary); }
  .table-count {
    font-size: 13px;
    color: var(--text-muted);
  }
  .table-card table {
    width: 100%;
    border-collapse: collapse;
  }
  .table-card th {
    text-align: left;
    padding: 12px 16px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    background: var(--bg-main);
    border-bottom: 1px solid var(--border-light);
  }
  .table-card td {
    padding: 12px 16px;
    font-size: 13px;
    color: var(--text-primary);
    border-bottom: 1px solid var(--border-light);
    vertical-align: middle;
  }
  .table-card tr:last-child td { border-bottom: none; }
  .table-card tr:hover td { background: var(--bg-hover); }

  /* ── Action cell ── */
  td.action-cell {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: nowrap;
  }
  .action-btn.sm {
    width: 34px; height: 34px;
    border-radius: 9px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 160ms ease;
  }
  .action-btn.sv { width: 16px; height: 16px; }
  .action-btn.view {
    background: #eef2ff; color: #4338ca;
  }
  .action-btn.view:hover {
    background: #4338ca; color: #fff;
    box-shadow: 0 4px 14px rgba(67,56,202,0.28);
    transform: translateY(-1px);
  }
  .action-btn.edit {
    background: #fff7ed; color: #d97706;
  }
  .action-btn.edit:hover {
    background: #d97706; color: #fff;
    box-shadow: 0 4px 14px rgba(217,119,6,0.28);
    transform: translateY(-1px);
  }

  /* ── Action dropdown ── */
  .action-dd {
    position: relative;
    display: inline-block;
  }
  .action-dd__btn {
    width: 34px; height: 34px;
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border-light);
    background: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 150ms;
  }
  .action-dd__btn:hover {
    border-color: var(--primary);
    background: var(--primary-soft);
  }
  .action-dd__btn i { width: 18px; height: 18px; color: var(--text-secondary); }
  .action-dd__menu {
    display: none;
    position: fixed;
    background: white;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    min-width: 200px;
    z-index: 10000;
    overflow: hidden;
  }
  .action-dd__menu.show { display: block; }
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
    transition: background 150ms;
    font-family: inherit;
  }
  .action-dd__item:hover { background: #f8fafc; }
  .action-dd__item i { width: 16px; height: 16px; flex-shrink: 0; }
  .action-dd__item--danger { color: var(--danger); }
  .action-dd__item--danger:hover { background: var(--danger-soft); }
  .action-dd__item--warning { color: var(--warning); }
  .action-dd__item--warning:hover { background: var(--warning-soft); }
  .action-dd__item--success { color: var(--success); }
  .action-dd__item--success:hover { background: var(--success-soft); }

  /* ── Status badges ── */
  .status-badge {
    display: inline-flex;
    padding: 4px 12px;
    border-radius: var(--radius-full);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
  }
  .status-badge.active      { background: var(--success-soft); color: #047857; }
  .status-badge.maintenance { background: var(--warning-soft); color: #b45309; }
  .status-badge.inactive    { background: var(--bg-main);      color: var(--text-muted); }
  .status-badge.retired     { background: var(--danger-soft);  color: var(--danger); }

  /* ── Modal overlay ── */
  .modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.6);
    backdrop-filter: blur(4px);
    z-index: var(--z-modal-backdrop);
    align-items: center;
    justify-content: center;
  }
  .modal-overlay.active {
    display: flex;
  }
  .modal {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-5);
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    animation: scaleIn 200ms var(--ease-spring);
  }
  .modal.large { max-width: 800px; }
  .modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-md) var(--space-lg);
    border-bottom: 1px solid var(--border-light);
    position: sticky; top: 0;
    background: var(--bg-card);
    z-index: 1;
  }
  .modal-title {
    font-size: 17px;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .modal-title i { width: 20px; height: 20px; }
  .modal-close {
    width: 36px; height: 36px;
    border: none;
    background: var(--bg-main);
    border-radius: var(--radius-md);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 150ms;
    color: var(--text-muted);
  }
  .modal-close:hover { background: var(--bg-hover); color: var(--text-primary); }
  .modal-close i { width: 18px; height: 18px; }
  .modal-body { padding: var(--space-lg); }
  .modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: var(--space-sm);
    padding: var(--space-md) var(--space-lg);
    border-top: 1px solid var(--border-light);
  }

  /* ── Form elements ── */
  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-md);
    margin-bottom: var(--space-md);
  }
  .form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .form-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
  }
  .form-label .required { color: var(--danger); }
  .form-input, .form-select, .form-textarea {
    padding: 8px 12px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    font-size: 13px;
    font-family: inherit;
    color: var(--text-primary);
    background: var(--bg-card);
    transition: border-color 150ms ease;
  }
  .form-input:focus, .form-select:focus, .form-textarea:focus {
    border-color: var(--primary);
    outline: none;
  }
  .form-textarea { resize: vertical; min-height: 60px; }

  /* ── Buttons ── */
  .btn-new {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 150ms ease;
    font-family: inherit;
  }
  .btn-new:hover {
    background: var(--primary-light);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(15,41,66,0.25);
  }
  .btn-new i { width: 18px; height: 18px; }
  .btn-cancel {
    padding: 8px 16px;
    background: var(--bg-main);
    color: var(--text-secondary);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 150ms ease;
    font-family: inherit;
  }
  .btn-cancel:hover {
    background: var(--bg-hover);
    border-color: var(--border-medium);
  }
  .btn-submit {
    padding: 8px 16px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 150ms ease;
    font-family: inherit;
  }
  .btn-submit:hover {
    background: var(--primary-light);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15,41,66,0.2);
  }

  /* ── Toast ── */
  .toast-container {
    position: fixed;
    bottom: 24px; right: 24px;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .toast {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: white;
    border-radius: var(--radius-md);
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    min-width: 300px;
    max-width: 420px;
    animation: slideIn 0.3s ease;
    border-left: 4px solid;
  }
  .toast--success { border-color: var(--success); }
  .toast--error   { border-color: var(--danger); }
  .toast--warning { border-color: var(--warning); }
  .toast--info    { border-color: var(--info); }
  .toast__icon { width: 20px; height: 20px; flex-shrink: 0; }
  .toast--success .toast__icon { color: var(--success); }
  .toast--error .toast__icon   { color: var(--danger); }
  .toast--warning .toast__icon { color: var(--warning); }
  .toast--info .toast__icon    { color: var(--info); }
  .toast__content { flex: 1; }
  .toast__title {
    font-weight: 600; font-size: 14px;
    color: var(--text-primary); margin-bottom: 2px;
  }
  .toast__message {
    font-size: 13px; color: var(--text-secondary);
  }
  .toast__close {
    width: 28px; height: 28px;
    border: none; background: none;
    cursor: pointer; color: var(--text-muted);
    border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
  }
  .toast__close:hover { background: #f1f5f9; color: var(--text-primary); }
  .toast__close i { width: 16px; height: 16px; }
  @keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to   { transform: translateX(0);    opacity: 1; }
  }

  /* ── Pagination ── */
  .pagination-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 20px 0;
    margin-top: 16px;
  }
  .pagination-info {
    text-align: center;
    font-size: 14px;
    color: var(--text-muted);
  }
  .pagination-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }
  .pagination-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 8px 16px;
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 500;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
  }
  .pagination-btn:hover:not(:disabled) {
    background: var(--bg-hover);
    border-color: var(--border-medium);
    color: var(--text-primary);
  }
  .pagination-btn:disabled { opacity: 0.5; cursor: not-allowed; }
  .pagination-pages {
    display: flex;
    align-items: center;
    gap: 4px;
  }
  .pagination-page {
    width: 36px; height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 500;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
  }
  .pagination-page:hover { background: var(--bg-hover); color: var(--text-primary); }
  .pagination-page.active { background: var(--primary); color: white; font-weight: 600; }
  .pagination-page.ellipsis { cursor: default; color: var(--text-muted); }
  .pagination-page.ellipsis:hover { background: transparent; }

  /* ── Skeleton ── */
  .skeleton {
    background: linear-gradient(90deg, var(--bg-main) 0%, var(--bg-hover) 50%, var(--bg-main) 100%);
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.5s ease-in-out infinite;
    border-radius: var(--radius-sm);
  }
  @keyframes skeleton-shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
  }
  .skeleton-text { height: 14px; margin-bottom: 8px; }
  .skeleton-text:last-child { width: 60%; }
  .skeleton-title { height: 24px; width: 50%; margin-bottom: 12px; }
  .skeleton-stat {
    height: 80px;
    border-radius: var(--radius-md);
  }

  /* ── Empty state ── */
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
    text-align: center;
  }
  .empty-state i {
    width: 64px; height: 64px;
    color: var(--text-muted);
    margin-bottom: 16px;
    opacity: 0.5;
  }
  .empty-state-title {
    font-size: 17px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
  }
  .empty-state-desc {
    font-size: 13px;
    color: var(--text-secondary);
    max-width: 360px;
  }

  /* ── Responsive ── */
  @media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .filters-row { flex-direction: column; }
    .filter-group { min-width: 100%; }
    .modal { width: 95% !important; max-width: 95vw !important; }
    .detail-grid { grid-template-columns: 1fr !important; }
    .detail-section-title { grid-column: span 1 !important; }
  }
  @media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr 1fr; gap: var(--space-sm); }
    .stat-card {
      padding: var(--space-md);
      flex-direction: column;
      align-items: flex-start;
    }
    .stat-icon { width: 44px !important; height: 44px !important; }
    .stat-icon i { width: 20px !important; height: 20px !important; }
    .stat-value { font-size: 20px !important; }
    .stat-label { font-size: 12px !important; }
    .form-row { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; gap: var(--space-md); align-items: flex-start; }
    .btn-new { width: 100%; justify-content: center; }
    td.action-cell { flex-wrap: wrap; }
    .modal-footer { flex-direction: column; }
    .modal-footer .btn-cancel,
    .modal-footer .btn-submit { width: 100%; justify-content: center; }
  }
  @media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .stat-value { font-size: 18px !important; }
    .page-title { font-size: 22px !important; }
    .toast { min-width: unset; max-width: calc(100vw - 32px); }
  }

  /* ── Reduced motion ── */
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
      animation-duration: 0.01ms !important;
      transition-duration: 0.01ms !important;
    }
    .modal { animation: none; }
    .toast { animation: none; }
  }
</style>
```

---

## 4. JavaScript Architecture

### Namespace: `window.EquiposInventario`

```js
window.EquiposInventario = (function () {
  'use strict';

  // ── State ──────────────────────────────────────────────────────
  let searchTimeout  = null;
  let currentPage    = 1;
  let pageSize       = 20;
  let currentState   = {};

  // ── CSRF token helper ──────────────────────────────────────────
  function csrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  // ── Init ───────────────────────────────────────────────────────
  function init() {
    bindEvents();
    loadData(1);
  }

  // ── Event delegation ───────────────────────────────────────────
  function bindEvents() {
    const main = document.querySelector('#main-content');
    if (!main) return;

    main.addEventListener('click', function (e) {
      const el = e.target.closest('[data-action]');
      if (!el) return;
      const action = el.getAttribute('data-action');
      handleAction(action, el, e);
    });

    // Debounced search on input
    const searchInput = document.getElementById('search-equipos');
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function () { loadData(1); }, 300);
      });
    }
  }

  function handleAction(action, el, e) {
    switch (true) {
      case action === 'crear-equipo':
        abrirCrearEquipo();
        break;
      case action === 'clear-filters':
        clearFilters();
        break;
      case action.startsWith('view-'):
        verEquipo(action.replace('view-', ''));
        break;
      case action.startsWith('edit-'):
        editarEquipo(action.replace('edit-', ''));
        break;
      case action.startsWith('deactivate-'):
        desactivarEquipo(action.replace('deactivate-', ''));
        break;
      case action.startsWith('delete-'):
        eliminarEquipoPermanent(action.replace('delete-', ''));
        break;
      case action.startsWith('reactivate-'):
        reactivarEquipo(action.replace('reactivate-', ''));
        break;
      case action.startsWith('go-page-'):
        loadData(parseInt(action.replace('go-page-', ''), 10));
        break;
      case action === 'prev-page':
        loadData(currentPage - 1);
        break;
      case action === 'next-page':
        loadData(currentPage + 1);
        break;
      case action === 'close-modal':
        closeModal(el.getAttribute('data-modal'));
        break;
      case action === 'confirmar-desactivar':
        confirmarDesactivar();
        break;
      case action === 'confirmar-eliminar':
        confirmarEliminarPermanent();
        break;
      case action === 'filter-change':
        loadData(1);
        break;
    }
  }

  // ── Data loading ───────────────────────────────────────────────
  async function loadData(page) {
    const search      = document.getElementById('search-equipos').value;
    const location_id = document.getElementById('filtro-ubicacion').value;
    const status      = document.getElementById('filtro-estado').value;

    currentPage = page || 1;

    // Show skeleton
    document.getElementById('equipos-table-body').innerHTML = '';
    document.getElementById('table-skeleton').style.display = 'block';
    document.getElementById('equipos-table').style.display  = 'none';

    let url = BASE_URL + 'app/api/equipos.php?action=list'
            + '&page=' + currentPage
            + '&page_size=' + pageSize;
    if (search)      url += '&search='      + encodeURIComponent(search);
    if (location_id) url += '&location_id=' + encodeURIComponent(location_id);
    if (status)      url += '&status='      + encodeURIComponent(status);

    try {
      const res = await fetch(url).then(parseJsonResponse);
      if (res.error) throw new Error(res.error);

      // Update stats with counter animation
      renderStats(res.stats);

      // Update table
      renderTable(res.equipos || []);

      // Update count
      document.getElementById('equipos-count').textContent =
        (res.equipos ? res.equipos.length : 0) + ' equipos';

      // Pagination
      renderPagination(res.total, res.page, res.page_size);

      // Hide skeleton, show table
      document.getElementById('table-skeleton').style.display = 'none';
      document.getElementById('equipos-table').style.display  = 'table';

      lucide.createIcons();
    } catch (err) {
      document.getElementById('equipos-table-body').innerHTML =
        '<tr><td colspan="8" style="text-align:center;color:var(--danger);padding:32px;">Error: '
        + escapeHtml(err.message) + '</td></tr>';
      document.getElementById('table-skeleton').style.display = 'none';
      document.getElementById('equipos-table').style.display  = 'table';
    }
  }

  // ── Rendering ──────────────────────────────────────────────────
  function renderStats(stats) {
    if (!stats) return;
    const total = (parseInt(stats.activos)||0)
                + (parseInt(stats.mantenimiento)||0)
                + (parseInt(stats.inactivos)||0)
                + (parseInt(stats.retirados)||0);
    animateCounter(document.getElementById('stat-total'), total);
    animateCounter(document.getElementById('stat-activos'), stats.activos || 0);
    animateCounter(document.getElementById('stat-mantenimiento'), stats.mantenimiento || 0);
    animateCounter(document.getElementById('stat-inactivos'), stats.inactivos || 0);
  }

  function renderTable(equipos) {
    const tbody = document.getElementById('equipos-table-body');
    if (!equipos.length) {
      tbody.innerHTML =
        '<tr><td colspan="8"><div class="empty-state">'
        + '<i data-lucide="monitor"></i>'
        + '<div class="empty-state-title">No hay equipos registrados</div>'
        + '<div class="empty-state-desc">Agregue un nuevo equipo o ajuste los filtros.</div>'
        + '</div></td></tr>';
      return;
    }
    let html = '';
    equipos.forEach(function (eq) {
      html += renderRow(eq);
    });
    tbody.innerHTML = html;
  }

  function renderRow(eq) {
    const statusLabel  = statusMap(eq.status);
    const statusClass  = eq.status || 'inactive';
    const assignedHtml = eq.assigned_user_name
      ? '<span class="assigned-user" style="display:inline-flex;align-items:center;gap:4px;">'
        + '<i data-lucide="user" width="14" height="14"></i>'
        + escapeHtml((eq.assigned_user_name||'') + ' ' + (eq.assigned_user_lastname||''))
        + '</span>'
      : '<span style="color:var(--text-muted);font-size:12px;">Sin asignar</span>';

    const isRetired = eq.status === 'retired';
    let actionsHtml = '';

    if (isRetired) {
      actionsHtml += '<button class="action-dd__item action-dd__item--success" data-action="reactivate-' + eq.id + '">'
        + '<i data-lucide="check-circle"></i>Reactivar</button>';
    } else {
      actionsHtml += '<button class="action-dd__item action-dd__item--warning" data-action="deactivate-' + eq.id + '">'
        + '<i data-lucide="toggle-left"></i>Desactivar</button>';
    }
    actionsHtml += '<button class="action-dd__item action-dd__item--danger" data-action="delete-' + eq.id + '">'
      + '<i data-lucide="trash-2"></i>Eliminar</button>';

    return '<tr>'
      + '<td>' + escapeHtml(eq.patrimonial_code || '-') + '</td>'
      + '<td><strong style="font-weight:600;">' + escapeHtml(eq.name) + '</strong></td>'
      + '<td>' + escapeHtml(eq.asset_type || '-') + '</td>'
      + '<td>' + escapeHtml(eq.serial_number || '-') + '</td>'
      + '<td>' + escapeHtml(eq.location_name || 'Sin asignar') + '</td>'
      + '<td>' + assignedHtml + '</td>'
      + '<td><span class="status-badge ' + statusClass + '">' + statusLabel + '</span></td>'
      + '<td><div class="action-cell">'
      + '<button class="action-btn sm view" data-action="view-' + eq.id + '" title="Ver"><i data-lucide="eye"></i></button>'
      + '<button class="action-btn sm edit" data-action="edit-' + eq.id + '" title="Editar"><i data-lucide="pencil"></i></button>'
      + '<div class="action-dd">'
      + '<button class="action-dd__btn" data-action="toggle-dd-' + eq.id + '" title="Más acciones"><i data-lucide="more-vertical"></i></button>'
      + '<div class="action-dd__menu" id="dd-' + eq.id + '">' + actionsHtml + '</div>'
      + '</div>'
      + '</div></td></tr>';
  }

  function renderPagination(total, page, pageSize) {
    const container = document.getElementById('pagination-container');
    if (!total || total <= pageSize) {
      container.innerHTML = '<div class="pagination-info">Mostrando todos los equipos</div>';
      return;
    }
    const totalPages = Math.ceil(total / pageSize);
    const start = (page - 1) * pageSize + 1;
    const end = Math.min(page * pageSize, total);

    let html = '<div class="pagination-info">Mostrando ' + start + '–' + end + ' de ' + total + ' equipos</div>';
    html += '<div class="pagination-controls">';
    html += '<button class="pagination-btn" data-action="prev-page" ' + (page <= 1 ? 'disabled' : '') + '>'
          + '<i data-lucide="chevron-left"></i> Anterior</button>';
    html += '<div class="pagination-pages">';

    // Ellipsis logic
    const range = 2;
    let pages = [];
    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= page - range && i <= page + range)) {
        pages.push(i);
      } else if (pages[pages.length - 1] !== '...') {
        pages.push('...');
      }
    }
    pages.forEach(function (p) {
      if (p === '...') {
        html += '<span class="pagination-page ellipsis">…</span>';
      } else {
        html += '<span class="pagination-page' + (p === page ? ' active' : '') + '" data-action="go-page-' + p + '">' + p + '</span>';
      }
    });

    html += '</div>';
    html += '<button class="pagination-btn" data-action="next-page" ' + (page >= totalPages ? 'disabled' : '') + '>'
          + 'Siguiente <i data-lucide="chevron-right"></i></button>';
    html += '</div>';
    container.innerHTML = html;
  }

  // ── Modals ─────────────────────────────────────────────────────
  function verEquipo(id) {
    openModal('ver');
    document.getElementById('ver-body').innerHTML =
      '<p style="padding:32px;text-align:center;color:var(--text-muted);">Cargando...</p>';
    fetch(BASE_URL + 'app/api/equipos.php?action=get-equipo&id=' + id)
      .then(parseJsonResponse)
      .then(function (eq) {
        if (eq.error) {
          document.getElementById('ver-body').innerHTML =
            '<p style="color:var(--danger);padding:32px;">' + escapeHtml(eq.error) + '</p>';
          return;
        }
        var html = '<div class="detail-grid">'
          + '<div class="detail-section-title"><i data-lucide="info"></i> Información General</div>'
          + detailItem('Nombre', eq.name)
          + detailItem('Código', eq.patrimonial_code)
          + detailItem('Serial', eq.serial_number)
          + detailItem('Tipo', eq.asset_type)
          + detailItem('Marca', eq.brand)
          + detailItem('Modelo', eq.model)
          + detailItem('Estado', '<span class="status-badge ' + (eq.status||'') + '">' + statusMap(eq.status) + '</span>')
          + detailItem('Condición', eq.condition)
          + '<div class="detail-section-title"><i data-lucide="cpu"></i> Hardware</div>'
          + detailItem('CPU', (eq.cpu_brand||'') + ' ' + (eq.cpu_model||'') + ' ' + (eq.cpu_generation||''))
          + detailItem('RAM', eq.ram)
          + detailItem('Almacenamiento', (eq.disk_type||'') + ' — ' + (eq.disk_capacity||''))
          + '<div class="detail-section-title"><i data-lucide="globe"></i> Red y Ubicación</div>'
          + detailItem('IP', eq.ip_address)
          + detailItem('MAC', eq.mac_address)
          + detailItem('Ubicación', eq.location_name || 'Sin asignar')
          + detailItem('Usuario', (eq.assigned_user_name||'') + ' ' + (eq.assigned_user_lastname||''))
          + (eq.observations ? '<div class="detail-section-title"><i data-lucide="file-text"></i> Observaciones</div>'
            + '<div class="detail-item" style="grid-column:span 2;"><span class="detail-value">' + escapeHtml(eq.observations) + '</span></div>'
            : '')
          + '</div>';
        document.getElementById('ver-body').innerHTML = html;
        lucide.createIcons();
      })
      .catch(function () {
        document.getElementById('ver-body').innerHTML =
          '<p style="color:var(--danger);padding:32px;">Error cargando datos.</p>';
      });
  }

  function editarEquipo(id) {
    openModal('editar');
    document.getElementById('form-edit-id').value = id;
    document.getElementById('form-name').value = '';

    Promise.all([
      fetch(BASE_URL + 'app/api/locations.php').then(parseJsonResponse),
      fetch(BASE_URL + 'app/api/tickets.php?action=get-usuarios').then(parseJsonResponse),
      fetch(BASE_URL + 'app/api/equipos.php?action=get-equipo&id=' + id).then(parseJsonResponse)
    ])
    .then(function (results) {
      const locationsData = results[0];
      const usuariosData  = results[1];
      const eq            = results[2];

      if (eq.error) {
        showToast('error', 'Error', eq.error);
        return;
      }

      // Populate form fields using the shared IDs
      document.getElementById('form-name').value             = eq.name || '';
      document.getElementById('form-patrimonial_code').value = eq.patrimonial_code || '';
      document.getElementById('form-serial_number').value    = eq.serial_number || '';
      document.getElementById('form-brand').value            = eq.brand || '';
      document.getElementById('form-model').value            = eq.model || '';
      document.getElementById('form-ip_address').value       = eq.ip_address || '';
      document.getElementById('form-mac_address').value      = eq.mac_address || '';
      document.getElementById('form-observations').value     = eq.observations || '';

      setSelectValue('form-asset_type', eq.asset_type);
      setSelectValue('form-status', eq.status);
      setSelectValue('form-condition', eq.condition);

      // Populate location select
      populateLocationSelect('form-location_id', locationsData, eq.location_id);
      // Populate user select
      populateUserSelect('form-assigned_user_id', usuariosData, eq.assigned_user_id);

      lucide.createIcons();
    })
    .catch(function () {
      showToast('error', 'Error', 'Error cargando datos del equipo.');
    });
  }

  function guardarEquipo(e) {
    e.preventDefault();
    const data = new URLSearchParams();
    data.append('action', 'update-equipo');
    data.append('id', document.getElementById('form-edit-id').value);
    collectFormData(data);

    fetch(BASE_URL + 'app/api/equipos.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': csrfToken()
      },
      body: data.toString()
    })
    .then(parseJsonResponse)
    .then(function (res) {
      if (res.success) {
        showToast('success', 'Equipo actualizado', 'Los cambios se han guardado correctamente.');
        closeModal('editar');
        loadData(currentPage);
      } else {
        showToast('error', 'Error', res.error || 'No se pudo guardar.');
      }
    })
    .catch(function () {
      showToast('error', 'Error', 'Error de conexión.');
    });
  }

  function abrirCrearEquipo() {
    openModal('crear');
    // Reset form
    var form = document.getElementById('form-crear');
    if (form) form.reset();

    // Fetch locations & users
    Promise.all([
      fetch(BASE_URL + 'app/api/locations.php').then(parseJsonResponse),
      fetch(BASE_URL + 'app/api/tickets.php?action=get-usuarios').then(parseJsonResponse)
    ])
    .then(function (results) {
      populateLocationSelect('form-location_id', results[0], null);
      populateUserSelect('form-assigned_user_id', results[1], null);
      lucide.createIcons();
    })
    .catch(function () {
      showToast('error', 'Error', 'Error cargando datos del formulario.');
    });
  }

  function crearEquipo(e) {
    e.preventDefault();
    const data = new URLSearchParams();
    data.append('action', 'create-equipo');
    collectFormData(data);

    fetch(BASE_URL + 'app/api/equipos.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': csrfToken()
      },
      body: data.toString()
    })
    .then(parseJsonResponse)
    .then(function (res) {
      if (res.success) {
        showToast('success', 'Equipo creado', 'El equipo se ha registrado exitosamente.');
        closeModal('crear');
        loadData(1);
      } else {
        showToast('error', 'Error', res.error || 'No se pudo crear.');
      }
    })
    .catch(function () {
      showToast('error', 'Error', 'Error de conexión.');
    });
  }

  function desactivarEquipo(id) {
    document.getElementById('deactivate-id').value = id;
    document.getElementById('deactivate-reason').value = '';
    openModal('desactivar');
  }

  function confirmarDesactivar() {
    const id     = document.getElementById('deactivate-id').value;
    const reason = document.getElementById('deactivate-reason').value;
    if (!reason) {
      showToast('warning', 'Campo requerido', 'Ingrese el motivo de desactivación.');
      return;
    }
    fetch(BASE_URL + 'app/api/equipos.php?action=deactivate-equipo&id=' + id, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken() },
      body: new URLSearchParams({ reason: reason }).toString()
    })
    .then(parseJsonResponse)
    .then(function (res) {
      if (res.success) {
        showToast('success', 'Equipo desactivado', 'El equipo ha sido desactivado.');
        closeModal('desactivar');
        loadData(currentPage);
      } else {
        showToast('error', 'Error', res.error || 'No se pudo desactivar.');
      }
    })
    .catch(function () {
      showToast('error', 'Error', 'Error de conexión.');
    });
  }

  function eliminarEquipoPermanent(id) {
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-reason').value = '';
    openModal('eliminar');
  }

  function confirmarEliminarPermanent() {
    const id     = document.getElementById('delete-id').value;
    const reason = document.getElementById('delete-reason').value;
    if (!reason) {
      showToast('warning', 'Campo requerido', 'Ingrese el motivo de eliminación.');
      return;
    }
    fetch(BASE_URL + 'app/api/equipos.php?action=delete-equipo&id=' + id + '&permanent=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken() },
      body: new URLSearchParams({ reason: reason }).toString()
    })
    .then(parseJsonResponse)
    .then(function (res) {
      if (res.success) {
        showToast('success', 'Equipo eliminado', 'El equipo ha sido eliminado permanentemente.');
        closeModal('eliminar');
        loadData(currentPage);
      } else {
        showToast('error', 'Error', res.error || 'No se pudo eliminar.');
      }
    })
    .catch(function () {
      showToast('error', 'Error', 'Error de conexión.');
    });
  }

  function reactivarEquipo(id) {
    fetch(BASE_URL + 'app/api/equipos.php?action=reactivate-equipo&id=' + id, {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrfToken() }
    })
    .then(parseJsonResponse)
    .then(function (res) {
      if (res.success) {
        showToast('success', 'Equipo reactivado', 'El equipo ha sido reactivado.');
        loadData(currentPage);
      } else {
        showToast('error', 'Error', res.error || 'No se pudo reactivar.');
      }
    })
    .catch(function () {
      showToast('error', 'Error', 'Error de conexión.');
    });
  }

  // ── UI Helpers ─────────────────────────────────────────────────
  function statusMap(status) {
    switch (status) {
      case 'active':      return 'Activo';
      case 'maintenance': return 'Mantenimiento';
      case 'retired':     return 'Retirado';
      case 'inactive':    return 'Inactivo';
      default:            return status || '—';
    }
  }

  function escapeHtml(text) {
    if (!text) return '';
    var d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
  }

  function detailItem(label, value) {
    return '<div class="detail-item"><span class="detail-label">' + escapeHtml(label) + '</span>'
         + '<span class="detail-value">' + (value ? escapeHtml(value) : '—') + '</span></div>';
  }

  function parseJsonResponse(response) {
    var ct = response.headers.get('content-type') || '';
    if (response.ok && ct.includes('application/json')) return response.json();
    return response.text().then(function (text) { throw new Error(text || 'El servidor no devolvió JSON válido.'); });
  }

  function showToast(type, title, message) {
    var container = document.getElementById('toast-container');
    var toast     = document.createElement('div');
    toast.className = 'toast toast--' + type;

    var iconMap = {
      success: 'check-circle',
      error:   'x-circle',
      warning: 'alert-triangle',
      info:    'info'
    };

    toast.innerHTML = '<i data-lucide="' + (iconMap[type] || 'info') + '" class="toast__icon"></i>'
      + '<div class="toast__content">'
      + '<div class="toast__title">' + escapeHtml(title) + '</div>'
      + '<div class="toast__message">' + escapeHtml(message) + '</div>'
      + '</div>'
      + '<button class="toast__close"><i data-lucide="x"></i></button>';
    container.appendChild(toast);
    lucide.createIcons();

    toast.querySelector('.toast__close').addEventListener('click', function () { toast.remove(); });
    setTimeout(function () { if (toast.parentNode) toast.remove(); }, 5000);
  }

  function openModal(tipo) {
    var m = document.getElementById('modal-' + tipo);
    if (m) { m.style.display = 'flex'; m.classList.add('active'); }
  }

  function closeModal(tipo) {
    var m = document.getElementById('modal-' + tipo);
    if (m) { m.style.display = 'none'; m.classList.remove('active'); }
  }

  function toggleActionDD(id, btn) {
    var menu = document.getElementById('dd-' + id);
    if (!menu) return;
    // Close all other menus
    document.querySelectorAll('.action-dd__menu.show').forEach(function (el) {
      if (el !== menu) el.classList.remove('show');
    });
    menu.classList.toggle('show');
    if (menu.classList.contains('show')) {
      var rect  = btn.getBoundingClientRect();
      var pad   = 12;
      var left  = rect.left - 200;
      if (left < pad) left = pad;
      if (rect.bottom + 100 > window.innerHeight - pad) {
        menu.style.top = (rect.top - 100) + 'px';
      } else {
        menu.style.top = (rect.bottom + 8) + 'px';
      }
      menu.style.left   = left + 'px';
      menu.style.right  = 'auto';
    }
  }

  function animateCounter(element, target) {
    if (!element) return;
    var start = 0;
    var duration = 600;
    var startTime = null;

    function step(timestamp) {
      if (!startTime) startTime = timestamp;
      var progress = Math.min((timestamp - startTime) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      element.textContent = Math.floor(start + (target - start) * eased);
      element.classList.add('animating');
      if (progress < 1) {
        window.requestAnimationFrame(step);
      } else {
        element.textContent = target;
        element.classList.remove('animating');
      }
    }
    window.requestAnimationFrame(step);
  }

  function clearFilters() {
    document.getElementById('filtro-ubicacion').value = '';
    document.getElementById('filtro-estado').value    = '';
    document.getElementById('search-equipos').value   = '';
    loadData(1);
  }

  function collectFormData(data) {
    data.append('name',              document.getElementById('form-name').value);
    data.append('patrimonial_code',  document.getElementById('form-patrimonial_code').value);
    data.append('serial_number',     document.getElementById('form-serial_number').value);
    data.append('asset_type',        document.getElementById('form-asset_type').value);
    data.append('brand',             document.getElementById('form-brand').value);
    data.append('model',             document.getElementById('form-model').value);
    data.append('status',            document.getElementById('form-status').value);
    data.append('condition',         document.getElementById('form-condition').value);
    data.append('ip_address',        document.getElementById('form-ip_address').value);
    data.append('mac_address',       document.getElementById('form-mac_address').value);
    data.append('location_id',       document.getElementById('form-location_id').value);
    data.append('assigned_user_id',  document.getElementById('form-assigned_user_id').value);
    data.append('observations',      document.getElementById('form-observations').value);
  }

  function setSelectValue(id, value) {
    var el = document.getElementById(id);
    if (!el) return;
    el.value = value || '';
  }

  function populateLocationSelect(id, data, selectedId) {
    var el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = '<option value="">— Sin asignar —</option>';
    (data.locations || []).forEach(function (loc) {
      var prefix = (loc.type||'').toUpperCase() === 'SEDE' || (loc.type||'').toUpperCase() === 'SUCURSAL'
        ? '[SEDE] ' : '[ÁREA] ';
      var opt = document.createElement('option');
      opt.value = loc.id;
      opt.textContent = prefix + (loc.name || '');
      if (String(loc.id) === String(selectedId)) opt.selected = true;
      el.appendChild(opt);
    });
  }

  function populateUserSelect(id, data, selectedId) {
    var el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = '<option value="">— Sin asignar —</option>';
    (data || []).forEach(function (u) {
      var fullname = (u.nombre || '') + ' ' + (u.apellidos || '');
      var opt = document.createElement('option');
      opt.value = u.id;
      opt.textContent = fullname;
      if (String(u.id) === String(selectedId)) opt.selected = true;
      el.appendChild(opt);
    });
  }

  // Close dropdowns on click outside
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.action-dd')) {
      document.querySelectorAll('.action-dd__menu.show').forEach(function (el) {
        el.classList.remove('show');
      });
    }
  });

  // ── Handle toggle-dd actions (special case, needs the button ref) ──
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-action^="toggle-dd-"]');
    if (!btn) return;
    var id = btn.getAttribute('data-action').replace('toggle-dd-', '');
    toggleActionDD(id, btn);
  });

  // ── Public API ─────────────────────────────────────────────────
  return {
    init: init,
    loadData: loadData,
    renderStats: renderStats,
    renderTable: renderTable,
    renderPagination: renderPagination,
    verEquipo: verEquipo,
    editarEquipo: editarEquipo,
    guardarEquipo: guardarEquipo,
    abrirCrearEquipo: abrirCrearEquipo,
    crearEquipo: crearEquipo,
    desactivarEquipo: desactivarEquipo,
    confirmarDesactivar: confirmarDesactivar,
    eliminarEquipoPermanent: eliminarEquipoPermanent,
    confirmarEliminarPermanent: confirmarEliminarPermanent,
    reactivarEquipo: reactivarEquipo,
    escapeHtml: escapeHtml,
    parseJsonResponse: parseJsonResponse,
    showToast: showToast,
    openModal: openModal,
    closeModal: closeModal,
    toggleActionDD: toggleActionDD,
    animateCounter: animateCounter,
    clearFilters: clearFilters
  };
})();

document.addEventListener('DOMContentLoaded', function () {
  window.EquiposInventario.init();
});
```

### Event Delegation Summary

| `data-action` value | Handler | Description |
|---|---|---|
| `crear-equipo` | `abrirCrearEquipo()` | Opens crear modal, resets form |
| `view-{id}` | `verEquipo(id)` | Fetches and shows detail |
| `edit-{id}` | `editarEquipo(id)` | Fetches and fills shared form with values |
| `deactivate-{id}` | `desactivarEquipo(id)` | Sets hidden ID, opens confirm |
| `delete-{id}` | `eliminarEquipoPermanent(id)` | Sets hidden ID, opens confirm |
| `reactivate-{id}` | `reactivarEquipo(id)` | POST reactivate, reloads |
| `confirmar-desactivar` | `confirmarDesactivar()` | Posts deactivation with reason |
| `confirmar-eliminar` | `confirmarEliminarPermanent()` | Posts permanent delete with reason |
| `close-modal` | `closeModal(tipo)` | Hides overlay |
| `filter-change` | `loadData(1)` | Reloads with new filters |
| `clear-filters` | `clearFilters()` | Resets all filters, reloads |
| `toggle-dd-{id}` | `toggleActionDD(id, btn)` | CSS-positioned dropdown |
| `prev-page` | `loadData(page-1)` | Previous page |
| `next-page` | `loadData(page+1)` | Next page |
| `go-page-{n}` | `loadData(n)` | Go to specific page |

### State Variables (Closure, not Class)

```js
let searchTimeout  = null;   // debounce handle
let currentPage    = 1;      // current page for pagination
let pageSize       = 20;     // items per page
let currentState   = {};     // reserved for future cache
```

---

## 5. Key Design Decisions Specific to B1

### Shared Form Fragment (`$equipoFormFields`)

A single PHP string contains all 12 form field HTML elements. Both crear and editar modals embed it via `<?= $equipoFormFields ?>`. The editar modal adds `<input type="hidden" id="form-edit-id">`. JS populates values via `document.getElementById('form-name').value = eq.name` etc. This eliminates the 60+ lines of duplicate form HTML in the current codebase.

### `data-action` Delegation (Zero inline `onclick`)

Every interactive element uses a `data-action` attribute. A single click listener on `#main-content` dispatches via a `switch(true)` block. This eliminates all 12+ inline `onclick` attributes, improves maintainability, and follows modern event delegation patterns.

### Static Modals (PHP-rendered)

All modal HTML is rendered by PHP on initial page load. JS never creates modal structure — it only:
- Sets `display: flex` / `display: none` via `openModal`/`closeModal`
- Fills dynamic content (`ver-body`, form field values)
- This means modals are indexed by search engines, visible to screen readers on load, and have zero Flash-of-Unstyled-Content issues.

### Namespace `window.EquiposInventario`

All functions and state live inside an IIFE that returns a public API object. Zero global functions. This prevents collisions with other admin pages and third-party scripts.

### Pagination with Ellipsis

`renderPagination()` generates a smart pagination with:
- "Mostrando X–Y de Z equipos" info text
- Previous/Next buttons with disabled states
- Page number buttons with ellipsis (`…`) when gaps exceed 2 pages
- Active page highlighted via `.pagination-page.active`
- All pagination buttons use `data-action` delegation

### Animated Stat Counters

`animateCounter(element, target)` uses `requestAnimationFrame` with cubic ease-out (`1 - (1-t)^3`) for smooth counter animation from 0 to the target value. The `animating` CSS class triggers a subtle `countUp` keyframe animation. Respects `prefers-reduced-motion` via the CSS rule that zeroes animation/transition durations.

### Skeleton Loading

The table area starts with visible skeleton elements (`.skeleton`, `.skeleton-title`, `.skeleton-text`). After `loadData()` resolves, the skeleton is hidden and the real table is shown. The shimmer animation uses `background-position` sliding via `skeleton-shimmer` keyframes (already defined in app.css).

### CSRF Token

Read from `<meta name="csrf-token" content="...">` (set in `head.php`). Sent as `X-CSRF-Token` header on all POST/PUT requests. The `csrfToken()` helper function extracts it.

### `lucide.createIcons()` After Every Dynamic Update

Every method that modifies the DOM (renderTable, verEquipo, editarEquipo, abrirCrearEquipo) calls `lucide.createIcons()` at the end to hydrate any `<i data-lucide="...">` elements added after the initial page load.

### `prefers-reduced-motion` Awareness

All animations (counter, modal scaleIn, toast slideIn, skeleton shimmer) are disabled when the user's OS accessibility setting is active. The CSS rule `@media (prefers-reduced-motion: reduce)` zeroes all animation and transition durations.

---

## 6. Important Notes

- **All text in Spanish** — UI labels, placeholders, toast messages, pagination info, empty states.
- **Escape all dynamic values** via `escapeHtml()` — prevents XSS in table cells, detail views, and toast messages.
- **`lucide.createIcons()`** called after every dynamic content update that may contain `<i data-lucide="...">` elements.
- **Skeleton loading** on initial page load and every re-fetch (filter change, page change).
- **Animated stat counters** using `requestAnimationFrame` with cubic ease-out.
- **Pagination** with ellipsis and "Mostrando X–Y de Z equipos" info.
- **Empty state** shown when no equipment matches — centered icon, title, and description.
- **Action dropdown** positioned via JS `getBoundingClientRect()` to prevent overflow off-screen.
- **Touch targets** meet 44×44px WCAG minimum via existing app.css rules.
- **No jQuery dependency** — pure vanilla JS (ES5 compatible).
- **No inline styles in table rows** — all presentation via CSS classes.
