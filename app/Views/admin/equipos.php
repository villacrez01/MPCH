<?php
$baseUrl = base_url();
$userId = $_SESSION['user']['id'] ?? null;
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$isOtiAdmin = \App\Services\AuthService::isAdmin();

require_once __DIR__ . '/../../Models/Equipment.php';
require_once __DIR__ . '/../../Models/Location.php';
require_once __DIR__ . '/../../Models/User.php';

$initialStats = \App\Models\Equipment::getStats();
$locationsData = \App\Models\Location::getAll();
$hierarchyData = \App\Models\User::getLocationsHierarchy();

$tituloPagina = 'Inventario de Equipos - Sistema OTI';
$paginaActual = 'admin-equipos';
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>public/assets/css/equipos.css?v=20260606a">
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<main id="main-content" class="main-content equipos-page">
  <div class="page-header">
    <div class="page-header-left">
      <h1 class="page-title">Inventario de Equipos</h1>
      <p class="page-subtitle">Gestión de equipos tecnológicos</p>
    </div>
    <button type="button" class="btn-new" data-action="create">
      <i data-lucide="plus"></i> Nuevo Equipo
    </button>
  </div>

  <div class="stats-grid" id="stats-grid">
    <div class="stat-card primary">
      <div class="stat-icon primary">
        <i data-lucide="monitor" width="24" height="24"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value" data-stat="total"><?= (int)($initialStats['total'] ?? 0) ?></div>
        <div class="stat-label">Total Equipos</div>
      </div>
    </div>
    <div class="stat-card success">
      <div class="stat-icon success">
        <i data-lucide="check-circle" width="24" height="24"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value" data-stat="activos"><?= (int)($initialStats['activos'] ?? 0) ?></div>
        <div class="stat-label">Activos</div>
      </div>
    </div>
    <div class="stat-card warning">
      <div class="stat-icon warning">
        <i data-lucide="wrench" width="24" height="24"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value" data-stat="mantenimiento"><?= (int)($initialStats['mantenimiento'] ?? 0) ?></div>
        <div class="stat-label">En Mantenimiento</div>
      </div>
    </div>
    <div class="stat-card danger">
      <div class="stat-icon danger">
        <i data-lucide="x-circle" width="24" height="24"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value" data-stat="inactivos"><?= (int)($initialStats['inactivos'] ?? 0) ?></div>
        <div class="stat-label">Inactivos</div>
      </div>
    </div>
  </div>

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
            <th style="min-width: 140px;">Acciones</th>
          </tr>
        </thead>
        <tbody id="equipos-table-body"></tbody>
      </table>
    </div>
    <div class="pagination-container" id="pagination-container"></div>
  </div>
</main>

<div class="toast-container" id="toast-container"></div>

<!-- Modal: Ver Equipo -->
<div class="modal-overlay" id="modal-ver-equipo" data-modal="ver-equipo" role="dialog" aria-modal="true" aria-labelledby="modal-title-ver">
  <div class="modal large">
    <div class="modal-header">
      <h3 class="modal-title" id="modal-title-ver"><i data-lucide="info"></i> Detalles del Equipo</h3>
      <button class="modal-close" data-action="close-modal" data-modal="ver-equipo" aria-label="Cerrar">
        <i data-lucide="x"></i>
      </button>
    </div>
    <div class="modal-body" id="ver-contenido-equipo"></div>
    <div class="modal-footer">
      <button class="btn-cancel" data-action="close-modal" data-modal="ver-equipo">Cerrar</button>
    </div>
  </div>
</div>

<!-- Modal: Editar Equipo -->
<div class="modal-overlay" id="modal-editar-equipo" data-modal="editar-equipo" role="dialog" aria-modal="true" aria-labelledby="modal-title-editar">
  <div class="modal modal-equipo large">
    <div class="modal-header modal-equipo-header">
      <div class="modal-equipo-header-text">
        <span class="modal-equipo-kicker">Inventario</span>
        <h3 class="modal-title" id="modal-title-editar"><i data-lucide="pencil" aria-hidden="true"></i> Editar equipo</h3>
      </div>
      <button type="button" class="modal-close" data-action="close-modal" data-modal="editar-equipo" aria-label="Cerrar">
        <i data-lucide="x"></i>
      </button>
    </div>
    <form id="form-editar-equipo" data-action="submit-edit">
      <div class="modal-body modal-equipo-body" id="editar-contenido-equipo"></div>
      <div class="modal-footer modal-equipo-footer">
        <button type="button" class="btn-cancel" data-action="close-modal" data-modal="editar-equipo">
          <i data-lucide="x" aria-hidden="true"></i> Cancelar
        </button>
        <button type="submit" class="btn-submit" id="btn-save-edit">
          <i data-lucide="save" aria-hidden="true"></i> Guardar cambios
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Crear Equipo -->
<div class="modal-overlay" id="modal-crear-equipo" data-modal="crear-equipo" role="dialog" aria-modal="true" aria-labelledby="modal-title-crear">
  <div class="modal modal-equipo modal-equipo--create large">
    <div class="modal-header modal-equipo-header">
      <div class="modal-equipo-header-text">
        <span class="modal-equipo-kicker">Inventario</span>
        <h3 class="modal-title" id="modal-title-crear">
          <i data-lucide="monitor-smartphone" aria-hidden="true"></i>
          Crear nuevo equipo
        </h3>
        <p class="modal-equipo-subtitle">Registre un activo tecnológico en el inventario municipal</p>
      </div>
      <button type="button" class="modal-close" data-action="close-modal" data-modal="crear-equipo" aria-label="Cerrar">
        <i data-lucide="x" aria-hidden="true"></i>
      </button>
    </div>
    <form id="form-crear-equipo" data-action="submit-create" novalidate>
      <div class="modal-body modal-equipo-body" id="crear-contenido-equipo"></div>
      <div class="modal-footer modal-equipo-footer">
        <button type="button" class="btn-cancel" data-action="close-modal" data-modal="crear-equipo">
          <i data-lucide="x" aria-hidden="true"></i> Cancelar
        </button>
        <button type="submit" class="btn-submit btn-submit--gold" id="btn-crear-equipo">
          <i data-lucide="plus" aria-hidden="true"></i> Crear equipo
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Desactivar Equipo -->
<div class="modal-overlay" id="modal-desactivar-equipo" data-modal="desactivar-equipo" role="dialog" aria-modal="true" aria-labelledby="modal-title-desactivar">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <h3 class="modal-title" id="modal-title-desactivar"><i data-lucide="alert-triangle"></i> Desactivar Equipo</h3>
      <button class="modal-close" data-action="close-modal" data-modal="desactivar-equipo" aria-label="Cerrar">
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
<div class="modal-overlay" id="modal-eliminar-permanent-equipo" data-modal="eliminar-permanent-equipo" role="dialog" aria-modal="true" aria-labelledby="modal-title-eliminar">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <h3 class="modal-title" id="modal-title-eliminar"><i data-lucide="trash-2"></i> Eliminar Permanentemente</h3>
      <button class="modal-close" data-action="close-modal" data-modal="eliminar-permanent-equipo" aria-label="Cerrar">
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
  overflow: visible !important;
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
  overflow-y: visible;
  -webkit-overflow-scrolling: touch;
  min-height: 200px;
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
  flex-wrap: nowrap;
}

.action-btn {
  width: 34px; height: 34px;
  border-radius: var(--radius-sm);
  border: 1.5px solid transparent;
  background: transparent;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all var(--duration-fast);
  color: var(--text-secondary);
}
.action-btn:hover { border-color: var(--border-light); background: var(--bg-hover); }
.action-btn svg { width: 16px; height: 16px; }
.action-btn.view:hover { border-color: var(--primary); background: var(--primary-soft); color: var(--primary); }
.action-btn.edit:hover { border-color: var(--warning); background: var(--warning-soft); color: var(--warning); }
.action-cell .action-dd {
  position: relative;
  display: inline-block;
  flex-shrink: 0;
}
.action-dd__menu {
  position: fixed;
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-5);
  min-width: 150px;
  z-index: 9999;
  display: none;
  flex-direction: column;
  padding: 4px 0;
}
.action-dd__menu.active { display: flex; }
.action-dd__item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 16px;
  background: transparent;
  border: none;
  font-size: var(--font-size-sm);
  color: var(--text-primary);
  text-align: left;
  cursor: pointer;
}
.action-dd__item:hover { background: var(--bg-hover); }
.action-dd__item svg { width: 16px; height: 16px; }
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
.modal > form {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}
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
.form-row.three-col { grid-template-columns: 1fr 1fr 1fr; }
.form-row.single-col { grid-template-columns: 1fr; }
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
.btn-submit.loading { opacity: 0.7; pointer-events: none; }
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
.skeleton-stat {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-md);
}
.skeleton-stat-icon {
  width: 48px; height: 48px;
  border-radius: 12px;
  background: linear-gradient(90deg, var(--bg-main) 0%, var(--bg-hover) 50%, var(--bg-main) 100%);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.5s ease-in-out infinite;
  flex-shrink: 0;
}
.skeleton-stat-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.skeleton-stat-line {
  height: 14px;
  border-radius: var(--radius-xs);
  background: linear-gradient(90deg, var(--bg-main) 0%, var(--bg-hover) 50%, var(--bg-main) 100%);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.5s ease-in-out infinite;
}
.skeleton-stat-line:first-child { width: 60px; }
.skeleton-stat-line:last-child { width: 100px; }
@keyframes skeleton-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── Pagination ── */
.pagination-container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-md);
  padding: var(--space-md) var(--space-lg);
  border-top: 1px solid var(--border-light);
}
.pagination-info {
  font-size: var(--font-size-sm);
  color: var(--text-muted);
}
.pagination-controls {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}
.pagination-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: var(--bg-card);
  border: 1.5px solid var(--border-light);
  border-radius: var(--radius-md);
  color: var(--text-primary);
  font-size: var(--font-size-sm);
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  transition: all var(--duration-fast);
}
.pagination-btn:hover:not(:disabled) { border-color: var(--primary); background: var(--primary-soft); color: var(--primary); }
.pagination-btn:disabled { opacity: 0.4; cursor: default; }
.pagination-btn svg { width: 16px; height: 16px; }
.pagination-pages {
  display: flex;
  align-items: center;
  gap: 2px;
}
.pagination-page {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px; height: 36px;
  border-radius: var(--radius-md);
  border: 1.5px solid transparent;
  background: transparent;
  color: var(--text-secondary);
  font-size: var(--font-size-sm);
  font-weight: 500;
  cursor: pointer;
  transition: all var(--duration-fast);
}
.pagination-page:hover { border-color: var(--border-light); background: var(--bg-hover); color: var(--text-primary); }
.pagination-page.active { background: var(--primary); color: white; border-color: var(--primary); }
.pagination-ellipsis {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px; height: 36px;
  color: var(--text-muted);
  font-size: var(--font-size-sm);
}

/* ── Toast ── */
.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: var(--z-toast);
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  max-width: 400px;
  width: 100%;
  pointer-events: none;
}
.toast {
  display: flex;
  align-items: flex-start;
  gap: var(--space-sm);
  padding: var(--space-md);
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-5);
  animation: toastIn var(--duration-normal) var(--ease-out);
  pointer-events: auto;
}
.toast--success { border-left: 4px solid var(--success); }
.toast--error { border-left: 4px solid var(--danger); }
.toast--warning { border-left: 4px solid var(--warning); }
.toast--info { border-left: 4px solid var(--primary); }
@keyframes toastIn {
  from { opacity: 0; transform: translateX(100%); }
  to { opacity: 1; transform: translateX(0); }
}
.toast__icon { flex-shrink: 0; }
.toast__icon svg { width: 20px; height: 20px; }
.toast--success .toast__icon svg { color: var(--success); }
.toast--error .toast__icon svg { color: var(--danger); }
.toast--warning .toast__icon svg { color: var(--warning); }
.toast--info .toast__icon svg { color: var(--primary); }
.toast__content { flex: 1; min-width: 0; }
.toast__title { font-size: var(--font-size-base); font-weight: 600; color: var(--text-primary); }
.toast__message { font-size: var(--font-size-sm); color: var(--text-secondary); margin-top: 2px; }
.toast__close {
  flex-shrink: 0;
  width: 24px; height: 24px;
  border: none;
  background: transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  border-radius: var(--radius-full);
  transition: all var(--duration-fast);
}
.toast__close:hover { background: var(--bg-hover); color: var(--text-primary); }
.toast__close svg { width: 16px; height: 16px; }

/* ── Responsive ── */
@media (max-width: 1024px) {
  .filters-row { flex-direction: column; align-items: stretch; }
  .filter-group, .filter-select, .search-wrapper { width: 100%; min-width: 0; }
  .filter-select { min-width: 0; }
  .form-row,
  .form-row.three-col,
  .form-row.single-col { grid-template-columns: 1fr; }
  .table-wrapper { overflow-x: auto; }
}
@media (max-width: 768px) {
  .stats-grid { grid-template-columns: 1fr 1fr !important; gap: var(--space-sm) !important; }
  .stat-card { padding: var(--space-md) !important; }
  .stat-icon { width: 44px !important; height: 44px !important; }
  .stat-icon svg { width: 20px !important; height: 20px !important; }
  .stat-value { font-size: 20px !important; }
  .stat-label { font-size: 12px !important; }
  .detail-row { grid-template-columns: 1fr; }
  .modal { width: 95%; max-width: 95vw; margin: 10px auto; }
  .modal-body { padding: var(--space-md); }
  .modal-header { padding: var(--space-md) var(--space-md) var(--space-sm); }
  .modal-footer { padding: var(--space-sm) var(--space-md); flex-direction: column; }
  .modal-footer .btn-cancel,
  .modal-footer .btn-submit { width: 100%; justify-content: center; }
  .pagination-container { flex-direction: column; align-items: center; }
  .table-wrapper { border: none; padding: 0; background: transparent; overflow: visible !important; }
  .table-wrapper thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
  .table-card { border: none; background: transparent; box-shadow: none; padding: 0; }
  .table-card table { min-width: 0; }
  .table-wrapper tbody,
  .table-wrapper tr,
  .table-wrapper td { display: block; }
  .table-wrapper tr {
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: var(--space-md);
    margin-bottom: var(--space-sm);
    box-shadow: var(--shadow-sm);
  }
  .table-card .table-wrapper td {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-xs) 0;
    border: none;
    font-size: var(--font-size-sm);
  }
  .table-card .table-wrapper td::before {
    content: attr(data-label);
    font-weight: 600;
    color: var(--text-muted);
    min-width: 90px;
    flex-shrink: 0;
    font-size: var(--font-size-xs);
    text-transform: uppercase;
    letter-spacing: 0.3px;
  }
  .table-card .table-wrapper td[data-label="Acciones"] {
    display: flex;
    justify-content: flex-end;
    padding-top: var(--space-sm);
    margin-top: var(--space-sm);
    border-top: 1px solid var(--border-light);
  }
  .table-card .table-wrapper td[data-label="Acciones"]::before { display: none; }
}
@media (max-width: 480px) {
  .stats-grid { grid-template-columns: 1fr !important; }
  .page-header { flex-direction: column !important; align-items: stretch !important; }
  .page-header .btn-new { width: 100% !important; justify-content: center !important; }
  .modal { width: 98% !important; }
  .modal-footer { flex-direction: column !important; }
  .modal-footer .btn-cancel,
  .modal-footer .btn-submit { width: 100% !important; justify-content: center !important; }
  .filters-section { padding: var(--space-sm) var(--space-md); }
  .table-header { flex-direction: column; align-items: flex-start; gap: 6px; }
}
@media (prefers-reduced-motion: reduce) {
  .skeleton-cell .skeleton,
  .skeleton-stat-icon,
  .skeleton-stat-line { animation: none; background: var(--bg-hover); }
  .action-dd__menu { animation: none; }
  .modal-overlay.active .modal { animation: none; }
  .stat-card { transition: none; }
  .toast { animation: none; }
}

/* ── Stat Card Lucide ── */
.stat-icon [data-lucide] { stroke: currentColor; fill: none; }
.stat-icon.primary [data-lucide] { color: var(--primary); }
.stat-icon.success [data-lucide] { color: var(--success); }
.stat-icon.warning [data-lucide] { color: var(--warning); }
.stat-icon.danger [data-lucide] { color: var(--danger); }
</style>

<script>
;(function() {
'use strict';

const BASE_URL = '<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>';
let CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
(function refreshCSRF() {
  fetch(BASE_URL + 'app/api/csrf_refresh.php')
    .then(r => r.json())
    .then(d => {
      if (d.token) {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', d.token);
        CSRF_TOKEN = d.token;
      }
    })
    .catch(() => {});
  setTimeout(refreshCSRF, 30 * 60 * 1000);
})();

const EquiposApp = {

  state: {
    equipos: [],
    stats: { total: 0, activos: 0, mantenimiento: 0, inactivos: 0, retirados: 0 },
    filters: { search: '', location_id: '', status: '' },
    pagination: { page: 1, page_size: 20, total: 0 },
    loading: false,
    error: null
  },

  searchTimeout: null,
  _previousFocus: null,

  async init() {
    this.bindEvents();
    this.syncFiltersFromDOM();
    await this.loadData(1);
  },

  bindEvents() {
    document.addEventListener('click', (e) => {
      const overlay = e.target.closest('.modal-overlay');
      if (overlay && e.target === overlay) {
        const name = overlay.getAttribute('data-modal');
        if (name) this.closeModal(name);
        return;
      }

      const btn = e.target.closest('[data-action]');

      if (!btn || btn.getAttribute('data-action') !== 'toggle-dd') {
        document.querySelectorAll('.action-dd__menu.active').forEach(m => {
          m.classList.remove('active');
          m.style.top = '';
          m.style.left = '';
        });
      }

      if (!btn) return;

      const action = btn.getAttribute('data-action');
      const payload = btn.dataset;

      if (action === 'toggle-dd') {
        e.stopPropagation();
      }

      switch (action) {
        case 'create':              this.abrirCrearEquipo(); break;
        case 'view':                this.verEquipo(payload.id); break;
        case 'edit':                this.editarEquipo(payload.id); break;
        case 'deactivate':          this.desactivarEquipo(payload.id); break;
        case 'delete-permanent':    this.eliminarEquipoPermanent(payload.id); break;
        case 'reactivate':          this.reactivarEquipo(payload.id); break;
        case 'toggle-dd':           this.toggleDropdown(payload.id); break;
        case 'close-modal':         this.closeModal(payload.modal); break;
        case 'clear-filters':       this.clearFilters(); break;
        case 'confirm-deactivate':  this.confirmarDesactivar(); break;
        case 'confirm-delete-permanent': this.confirmarEliminarPermanent(); break;
        case 'page':                this.onPageClick(btn); break;
      }
    });

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

    document.getElementById('form-editar-equipo').addEventListener('submit', (e) => this.guardarEquipo(e));
    document.getElementById('form-crear-equipo').addEventListener('submit', (e) => this.crearEquipo(e));

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => {
          const name = m.getAttribute('data-modal');
          if (name) this.closeModal(name);
        });
      }
      if (e.key === 'Tab') {
        const activeModal = document.querySelector('.modal-overlay.active');
        if (!activeModal) return;
        const focusable = activeModal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey) {
          if (document.activeElement === first) {
            e.preventDefault();
            last.focus();
          }
        } else {
          if (document.activeElement === last) {
            e.preventDefault();
            first.focus();
          }
        }
      }
    });

    const wrapper = document.getElementById('table-wrapper');
    if (wrapper) {
      wrapper.addEventListener('scroll', () => {
        document.querySelectorAll('.action-dd__menu.active').forEach(m => m.classList.remove('active'));
      }, { passive: true });
    }
    
    window.addEventListener('scroll', () => {
      document.querySelectorAll('.action-dd__menu.active').forEach(m => m.classList.remove('active'));
    }, { passive: true });
  },

  onPageClick(btn) {
    const p = parseInt(btn.getAttribute('data-page'));
    const totalPages = Math.max(1, Math.ceil(this.state.pagination.total / this.state.pagination.page_size));
    if (p >= 1 && p <= totalPages && p !== this.state.pagination.page) {
      this.loadData(p);
    }
  },

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

  async loadData(page) {
    this.state.loading = true;
    this.state.pagination.page = page;
    this.renderSkeleton();
    this.renderSkeletonStats();

    try {
      const params = new URLSearchParams({
        action: 'list',
        page: String(page),
        page_size: String(this.state.pagination.page_size)
      });
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

  render() {
    this.renderStats(this.state.stats);
    this.renderTable(this.state.equipos);
    this.renderPagination(this.state.pagination.total, this.state.pagination.page, this.state.pagination.page_size);
    this.refreshIcons();
  },

  refreshIcons(root) {
    if (window.OTI && window.OTI.refreshIcons) {
      window.OTI.refreshIcons(root || document);
    } else if (typeof lucide !== 'undefined' && lucide.createIcons) {
      lucide.createIcons({ attrs: { 'stroke-width': '1.75' }, root: root || document });
    }
  },

  renderSkeleton() {
    const tbody = document.getElementById('equipos-table-body');
    let html = '';
    for (let i = 0; i < 5; i++) {
      html += '<tr class="skeleton-row">';
      for (let j = 0; j < 8; j++) {
        html += '<td class="skeleton-cell"><div class="skeleton"></div></td>';
      }
      html += '</tr>';
    }
    tbody.innerHTML = html;
  },

  renderSkeletonStats() {
    const grid = document.getElementById('stats-grid');
    grid.innerHTML = [
      '<div class="stat-card primary"><div class="stat-icon primary"><i data-lucide="monitor"></i></div><div class="stat-content"><div class="stat-value" data-stat="total"></div><div class="stat-label">Total Equipos</div></div></div>',
      '<div class="stat-card success"><div class="stat-icon success"><i data-lucide="check-circle"></i></div><div class="stat-content"><div class="stat-value" data-stat="activos"></div><div class="stat-label">Activos</div></div></div>',
      '<div class="stat-card warning"><div class="stat-icon warning"><i data-lucide="wrench"></i></div><div class="stat-content"><div class="stat-value" data-stat="mantenimiento"></div><div class="stat-label">En Mantenimiento</div></div></div>',
      '<div class="stat-card danger"><div class="stat-icon danger"><i data-lucide="x-circle"></i></div><div class="stat-content"><div class="stat-value" data-stat="inactivos"></div><div class="stat-label">Inactivos</div></div></div>'
    ].join('');
    this.refreshIcons(grid);
  },

  renderError(msg) {
    const tbody = document.getElementById('equipos-table-body');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--danger);">' +
      this.escapeHtml(msg) + '</td></tr>';
    document.getElementById('equipos-count').textContent = 'Error';
  },

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

  renderTable(equipos) {
    const tbody = document.getElementById('equipos-table-body');

    if (!equipos || equipos.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state">' +
        '<i data-lucide="monitor" width="56" height="56"></i>' +
        '<div class="empty-state-title">No hay equipos registrados</div>' +
        '<div class="empty-state-desc">Utilice el botón "Nuevo Equipo" para agregar uno.</div>' +
        '</div></td></tr>';
      document.getElementById('equipos-count').textContent = '0 equipos';
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
      html += '<td data-label="Código">' + this.escapeHtml(eq.patrimonial_code || '-') + '</td>';
      html += '<td data-label="Nombre"><strong style="font-weight:600;">' + this.escapeHtml(eq.name) + '</strong></td>';
      html += '<td data-label="Tipo">' + this.escapeHtml(eq.asset_type || '-') + '</td>';
      html += '<td data-label="Serial">' + this.escapeHtml(eq.serial_number || '-') + '</td>';
      html += '<td data-label="Ubicación">' + this.escapeHtml(eq.location_name || 'Sin asignar') + '</td>';
      html += '<td data-label="Usuario">' + assigned + '</td>';
      html += '<td data-label="Estado"><span class="status-badge ' + statusClass + '">' + statusLabel + '</span></td>';
      html += '<td data-label="Acciones"><div class="action-cell">';
      html += '<button type="button" class="action-btn view" data-action="view" data-id="' + eq.id + '" title="Ver" aria-label="Ver"><i data-lucide="eye"></i></button>';
      html += '<button type="button" class="action-btn edit" data-action="edit" data-id="' + eq.id + '" title="Editar" aria-label="Editar"><i data-lucide="pencil"></i></button>';
      html += '<div class="action-dd">';
      html += '<button type="button" class="equipos-dd-btn" data-action="toggle-dd" data-id="' + eq.id + '" title="Más acciones" aria-label="Más acciones" aria-expanded="false"><i data-lucide="more-vertical"></i></button>';
      html += '<div class="action-dd__menu" id="action-dd-' + eq.id + '">' + ddItems + '</div>';
      html += '</div></div></td></tr>';
    });
    tbody.innerHTML = html;
    document.getElementById('equipos-count').textContent = equipos.length + ' equipos';
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

  renderPagination(total, page, pageSize) {
    const container = document.getElementById('pagination-container');
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    if (page > totalPages) page = totalPages;
    const start = total === 0 ? 0 : (page - 1) * pageSize + 1;
    const end = Math.min(page * pageSize, total);

    let html = '<div class="pagination-info">Mostrando ' + start + '&ndash;' + end + ' de ' + total + ' equipos</div>';
    html += '<div class="pagination-controls">';

    html += '<button class="pagination-btn" data-action="page" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>';
    html += '<i data-lucide="chevron-left" width="16" height="16"></i> Anterior</button>';

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

    html += '<button class="pagination-btn" data-action="page" data-page="' + (page + 1) + '"' + (page >= totalPages ? ' disabled' : '') + '>';
    html += 'Siguiente <i data-lucide="chevron-right" width="16" height="16"></i></button>';

    html += '</div>';
    container.innerHTML = html;
  },

  openModal(name) {
    const overlay = document.querySelector(`[data-modal="${name}"]`);
    if (!overlay) return;

    this._previousFocus = document.activeElement;
    document.body.classList.add('modal-equipos-open');

    overlay.classList.add('active');
    overlay.style.display = 'flex';

    this.refreshIcons(overlay);

    setTimeout(() => {
      const focusable = overlay.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (focusable.length > 0) focusable[0].focus();
    }, 80);
  },

  closeModal(name) {
    const overlay = document.querySelector(`[data-modal="${name}"]`);
    if (!overlay) return;
    overlay.classList.remove('active');
    overlay.style.display = 'none';

    if (!document.querySelector('.modal-overlay.active')) {
      document.body.classList.remove('modal-equipos-open');
    }

    if (name === 'crear-equipo') {
      const form = document.getElementById('form-crear-equipo');
      if (form) form.reset();
      document.getElementById('crear-contenido-equipo').innerHTML = '';
    }

    if (this._previousFocus && this._previousFocus.focus) {
      this._previousFocus.focus();
    }
    this._previousFocus = null;
  },

  toggleDropdown(id) {
    const menu = document.getElementById('action-dd-' + id);
    if (!menu) return;

    const isActive = menu.classList.contains('active');

    document.querySelectorAll('.action-dd__menu.active').forEach(m => {
      m.classList.remove('active');
      m.style.top = '';
      m.style.left = '';
    });
    document.querySelectorAll('.equipos-dd-btn[aria-expanded="true"]').forEach(b => {
      b.setAttribute('aria-expanded', 'false');
    });

    if (!isActive) {
      const btn = document.querySelector(`.equipos-dd-btn[data-action="toggle-dd"][data-id="${id}"]`);
      if (btn) {
        menu.classList.add('active');
        btn.setAttribute('aria-expanded', 'true');
        const btnRect = btn.getBoundingClientRect();

        requestAnimationFrame(() => {
          const menuRect = menu.getBoundingClientRect();
          let top = btnRect.bottom + 6;
          let left = btnRect.right - menuRect.width;

          if (top + menuRect.height > window.innerHeight - 8) {
            top = Math.max(8, btnRect.top - menuRect.height - 6);
          }
          if (left < 8) left = 8;
          if (left + menuRect.width > window.innerWidth - 8) {
            left = window.innerWidth - menuRect.width - 8;
          }

          menu.style.top = top + 'px';
          menu.style.left = left + 'px';
        });
        this.refreshIcons(menu);
      }
    }
  },

  async verEquipo(id) {
    this.openModal('ver-equipo');
    const content = document.getElementById('ver-contenido-equipo');
    content.innerHTML = '<div style="padding:32px;text-align:center;color:var(--text-muted);"><i data-lucide="loader" width="24" height="24" style="animation:spin 1s linear infinite;"></i><p style="margin-top:12px;">Cargando detalles...</p></div>';

    try {
      const eq = await this.parseJsonResponse(
        await fetch(BASE_URL + 'app/api/equipos.php?action=get-equipo&id=' + encodeURIComponent(id))
      );

      if (eq.error) {
        content.innerHTML = '<p style="color:var(--danger);padding:32px;">' + this.escapeHtml(eq.error) + '</p>';
        return;
      }

      this.renderDetailView(content, eq);
      this.refreshIcons(content);

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
      'assigned_user': ((eq.assigned_user_name||'') + ' ' + (eq.assigned_user_lastname||'')).trim() || 'Sin asignar',
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

  async editarEquipo(id) {
    this.openModal('editar-equipo');
    const content = document.getElementById('editar-contenido-equipo');
    content.innerHTML = '<div style="padding:32px;text-align:center;color:var(--text-muted);"><i data-lucide="loader" width="24" height="24" style="animation:spin 1s linear infinite;"></i><p style="margin-top:12px;">Cargando formulario...</p></div>';

    try {
      const [locationsData, usuariosData, eq] = await Promise.all([
        this.parseJsonResponse(fetch(BASE_URL + 'app/api/locations.php?action=list')),
        this.parseJsonResponse(fetch(BASE_URL + 'app/api/tickets.php?action=get-usuarios')),
        this.parseJsonResponse(fetch(BASE_URL + 'app/api/equipos.php?action=get-equipo&id=' + encodeURIComponent(id)))
      ]);

      if (eq.error) {
        content.innerHTML = '<p style="color:var(--danger);padding:32px;">' + this.escapeHtml(eq.error) + '</p>';
        return;
      }

      this.renderEditForm(content, eq, locationsData, usuariosData);
      this.refreshIcons(content);

    } catch (err) {
      content.innerHTML = '<p style="color:var(--danger);padding:32px;">Error cargando datos.</p>';
    }
  },

  fieldInput(name, label, value, required) {
    return '<div class="form-group"><label class="form-label">' + label + (required ? ' <span class="required">*</span>' : '') +
      '</label><input type="text" name="' + name + '" class="form-input" value="' + this.escapeHtml(value||'') + '"' +
      (required ? ' required' : '') + '></div>';
  },

  formSection(title, icon, innerHtml) {
    return '<section class="equipo-form-section">' +
      '<div class="equipo-form-section-head"><i data-lucide="' + icon + '" aria-hidden="true"></i><span>' + title + '</span></div>' +
      '<div class="equipo-form-section-body">' + innerHtml + '</div></section>';
  },

  normalizeUsuarios(data) {
    if (Array.isArray(data)) return data;
    if (data && Array.isArray(data.usuarios)) return data.usuarios;
    return [];
  },

  locationLabel(l) {
    const t = (l.type || '').toUpperCase();
    const prefix = (t === 'SEDE' || t === 'SUCURSAL') ? '[SEDE] ' : '[ÁREA] ';
    return prefix + (l.name || '');
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

  getLocationPath(locationId, locations) {
    const path = [];
    let current = locations.find(l => String(l.id) === String(locationId));
    while (current) {
      path.unshift(current);
      current = locations.find(l => String(l.id) === String(current.parent_id));
    }
    return path;
  },

  setupCascadingListeners(content, locations, usuarios) {
    const isCreate = !!content.querySelector('#sede-select-create');
    const sedeSelect = content.querySelector(isCreate ? '#sede-select-create' : '#sede-select');
    const areaSelect = content.querySelector(isCreate ? '#area-select-create' : '#area-select');
    const pisoSelect = content.querySelector(isCreate ? '#piso-select-create' : '#piso-select');
    const userSelect = content.querySelector('[name="assigned_user_id"]');

    const getChildren = (parentId) => locations.filter(l => String(l.parent_id) === String(parentId));
    const escape = (v) => { const d = document.createElement('div'); d.textContent = v; return d.innerHTML; };

    const filterArea = () => {
      const sedeId = sedeSelect.value;
      const children = sedeId ? getChildren(sedeId) : [];
      const areas = children.filter(l => l.type !== 'PISO' && l.type !== 'piso');
      areaSelect.innerHTML = '<option value="">-- Sin área --</option>';
      areas.forEach(a => {
        areaSelect.innerHTML += '<option value="' + a.id + '">' + escape(a.name) + '</option>';
      });
      areaSelect.dispatchEvent(new Event('change'));
    };

    const filterPiso = () => {
      const areaId = areaSelect.value;
      const children = areaId ? getChildren(areaId) : [];
      pisoSelect.innerHTML = '<option value="">-- Sin piso --</option>';
      children.forEach(p => {
        pisoSelect.innerHTML += '<option value="' + p.id + '">' + escape(p.name) + '</option>';
      });
    };

    sedeSelect.addEventListener('change', filterArea);
    areaSelect.addEventListener('change', filterPiso);

    if (userSelect) {
      userSelect.addEventListener('change', () => {
        const userId = userSelect.value;
        if (!userId) return;
        const user = usuarios.find(u => String(u.id) === String(userId));
        if (!user || !user.location_id) return;

        const path = this.getLocationPath(user.location_id, locations);
        const sede = path.find(l => l.type === 'SEDE' || l.type === 'sede');
        const area = path.find(l => l.type === 'AREA' || l.type === 'area' || l.type === 'DIRECCION' || l.type === 'direccion');
        const piso = path.find(l => l.type === 'PISO' || l.type === 'piso');

        if (sede) {
          sedeSelect.value = sede.id;
          sedeSelect.dispatchEvent(new Event('change'));
          if (area) {
            areaSelect.value = area.id;
            areaSelect.dispatchEvent(new Event('change'));
            if (piso) {
              pisoSelect.value = piso.id;
            }
          }
        }
      });
    }
  },

  renderEditForm(content, eq, locationsData, usuariosData) {
    const locations = locationsData.locations || [];
    const usuarios = this.normalizeUsuarios(usuariosData);

    let html = '<input type="hidden" name="id" value="' + eq.id + '">';

    html += '<div class="form-row">';
    html += this.fieldInput('name', 'Nombre', eq.name, true);
    html += this.fieldInput('patrimonial_code', 'Código Patrimonial', eq.patrimonial_code);
    html += '</div>';

    html += '<div class="form-row">';
    html += this.fieldInput('serial_number', 'Serial', eq.serial_number);
    html += this.fieldSelect('asset_type', 'Tipo', ['PC','LAPTOP','IMPRESORA','MONITOR','OTRO'], eq.asset_type);
    html += '</div>';

    html += '<div class="form-row">';
    html += this.fieldInput('brand', 'Marca', eq.brand);
    html += this.fieldInput('model', 'Modelo', eq.model);
    html += '</div>';

    html += '<div class="form-row">';
    html += this.fieldSelect('status', 'Estado', ['active','maintenance','inactive','retired'], eq.status,
      ['Activo','Mantenimiento','Inactivo','Retirado']);
    html += this.fieldSelect('condition', 'Condición', ['BUENO','REGULAR','MALO','OBSOLETO'], eq.condition);
    html += '</div>';

    html += '<div class="form-row">';
    html += this.fieldInput('ip_address', 'IP', eq.ip_address);
    html += this.fieldInput('mac_address', 'MAC', eq.mac_address);
    html += '</div>';

    const sedes = locations.filter(l => l.type === 'SEDE' || l.type === 'sede');
    const getChildren = (parentId) => locations.filter(l => String(l.parent_id) === String(parentId));
    const currentPath = this.getLocationPath(eq.location_id, locations);
    const currentSede = currentPath.find(l => l.type === 'SEDE' || l.type === 'sede');
    const currentArea = currentPath.find(l => l.type === 'AREA' || l.type === 'area' || l.type === 'DIRECCION' || l.type === 'direccion');
    const currentPiso = currentPath.find(l => l.type === 'PISO' || l.type === 'piso');

    html += '<div class="form-row three-col">';
    html += '<div class="form-group"><label class="form-label">Sede <span class="required">*</span></label>';
    html += '<select name="sede_id" id="sede-select" class="form-select" required>';
    html += '<option value="">-- Seleccionar Sede --</option>';
    sedes.forEach(s => {
      html += '<option value="' + s.id + '"' + (currentSede && String(s.id) === String(currentSede.id) ? ' selected' : '') + '>' + this.escapeHtml(s.name) + '</option>';
    });
    html += '</select></div>';
    html += '<div class="form-group"><label class="form-label">Área <span style="color:var(--text-muted);font-weight:400;">(opcional)</span></label>';
    html += '<select name="area_id" id="area-select" class="form-select">';
    html += '<option value="">-- Sin área --</option>';
    const areaSedeId = currentSede ? currentSede.id : '';
    const areaOptions = areaSedeId ? getChildren(areaSedeId).filter(l => l.type !== 'PISO' && l.type !== 'piso') : [];
    areaOptions.forEach(a => {
      html += '<option value="' + a.id + '"' + (currentArea && String(a.id) === String(currentArea.id) ? ' selected' : '') + '>' + this.escapeHtml(a.name) + '</option>';
    });
    html += '</select></div>';
    html += '<div class="form-group"><label class="form-label">Piso <span style="color:var(--text-muted);font-weight:400;">(opcional)</span></label>';
    html += '<select name="piso_id" id="piso-select" class="form-select">';
    html += '<option value="">-- Sin piso --</option>';
    const pisoAreaId = currentArea ? currentArea.id : '';
    const pisoOptions = pisoAreaId ? getChildren(pisoAreaId) : [];
    pisoOptions.forEach(p => {
      html += '<option value="' + p.id + '"' + (currentPiso && String(p.id) === String(currentPiso.id) ? ' selected' : '') + '>' + this.escapeHtml(p.name) + '</option>';
    });
    html += '</select></div>';
    html += '</div>';

    html += '<div class="form-row single-col">';
    html += this.fieldSelect('assigned_user_id', 'Usuario', usuarios.map(u => u.id), eq.assigned_user_id,
      usuarios.map(u => (u.nombre||'') + ' ' + (u.apellidos||'')),
      [{ value: '', label: '-- Sin asignar --' }]
    );
    html += '</div>';

    html += '<div class="form-group full">';
    html += '<label class="form-label">Observaciones</label>';
    html += '<textarea name="observations" class="form-textarea" rows="3">' + this.escapeHtml(eq.observations||'') + '</textarea>';
    html += '</div>';

    content.innerHTML = html;
    this.setupCascadingListeners(content, locations, usuarios);
  },

  async guardarEquipo(e) {
    e.preventDefault();
    const form = document.getElementById('form-editar-equipo');
    const formData = new FormData(form);
    const sede = formData.get('sede_id');
    const area = formData.get('area_id');
    const piso = formData.get('piso_id');
    formData.set('location_id', piso || area || sede || '');
    formData.append('action', 'update-equipo');
    formData.append('_token', CSRF_TOKEN);

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
      this.showToast('error', 'Error', err.message || 'Error de conexión.');
    } finally {
      btn.classList.remove('loading');
    }
  },

  async abrirCrearEquipo() {
    this.openModal('crear-equipo');
    const content = document.getElementById('crear-contenido-equipo');
    content.innerHTML = '<div class="equipo-form-loading"><i data-lucide="loader" aria-hidden="true"></i><p>Cargando formulario…</p></div>';
    this.refreshIcons(content);

    try {
      const [locationsData, usuariosData] = await Promise.all([
        this.parseJsonResponse(fetch(BASE_URL + 'app/api/locations.php?action=list')),
        this.parseJsonResponse(fetch(BASE_URL + 'app/api/tickets.php?action=get-usuarios&exclude_with_equipment=1'))
      ]);

      if (locationsData.error) {
        throw new Error(locationsData.error);
      }

      this.renderCreateForm(content, locationsData, usuariosData);
      this.refreshIcons(content);
    } catch (err) {
      content.innerHTML = '<p style="color:var(--danger);padding:24px;text-align:center;">' +
        this.escapeHtml(err.message || 'No se pudo cargar el formulario.') + '</p>';
    }
  },

  renderCreateForm(content, locationsData, usuariosData) {
    const locations = locationsData.locations || [];
    const usuarios = this.normalizeUsuarios(usuariosData);
    const locIds = locations.map(l => l.id);
    const locLabels = locations.map(l => this.locationLabel(l));
    const userIds = usuarios.map(u => u.id);
    const userLabels = usuarios.map(u => ((u.nombre || '') + ' ' + (u.apellidos || '')).trim());

    let html = '<div class="equipo-form-sections">';

    html += this.formSection('Identificación', 'fingerprint',
      '<p class="equipo-form-hint">Los campos marcados con * son obligatorios.</p>' +
      '<div class="form-row">' +
      this.fieldInput('name', 'Nombre del equipo', '', true) +
      this.fieldInput('patrimonial_code', 'Código patrimonial', '') +
      '</div>' +
      '<div class="form-row">' +
      this.fieldInput('serial_number', 'Número de serie', '', true) +
      this.fieldSelect('asset_type', 'Tipo de activo',
        ['PC', 'LAPTOP', 'IMPRESORA', 'MONITOR', 'SERVIDOR', 'OTRO'], 'PC',
        ['PC de escritorio', 'Laptop', 'Impresora', 'Monitor', 'Servidor', 'Otro']) +
      '</div>'
    );

    html += this.formSection('Especificaciones', 'cpu',
      '<div class="form-row">' +
      this.fieldInput('brand', 'Marca', '') +
      this.fieldInput('model', 'Modelo', '') +
      '</div>' +
      '<div class="form-row">' +
      this.fieldInput('cpu_brand', 'CPU (marca)', '') +
      this.fieldInput('cpu_model', 'CPU (modelo)', '') +
      '</div>' +
      '<div class="form-row">' +
      this.fieldInput('ram', 'Memoria RAM', '') +
      this.fieldInput('disk_capacity', 'Almacenamiento', '') +
      '</div>'
    );

    html += this.formSection('Estado y red', 'activity',
      '<div class="form-row">' +
      this.fieldSelect('status', 'Estado operativo',
        ['active', 'maintenance', 'inactive'], 'active',
        ['Activo', 'En mantenimiento', 'Inactivo']) +
      this.fieldSelect('condition', 'Condición física',
        ['BUENO', 'REGULAR', 'MALO', 'OBSOLETO'], 'BUENO',
        ['Bueno', 'Regular', 'Malo', 'Obsoleto']) +
      '</div>' +
      '<div class="form-row">' +
      this.fieldInput('ip_address', 'Dirección IP', '') +
      this.fieldInput('mac_address', 'Dirección MAC', '') +
      '</div>'
    );

    const sedes = locations.filter(l => l.type === 'SEDE' || l.type === 'sede');
    const getChildren = (parentId) => locations.filter(l => String(l.parent_id) === String(parentId));

    html += this.formSection('Ubicación y asignación', 'map-pin',
      '<div class="form-row three-col">' +
      '<div class="form-group"><label class="form-label">Sede <span class="required">*</span></label>' +
      '<select name="sede_id" id="sede-select-create" class="form-select" required>' +
      '<option value="">-- Seleccionar Sede --</option>' +
      sedes.map(s => '<option value="' + s.id + '">' + this.escapeHtml(s.name) + '</option>').join('') +
      '</select></div>' +
      '<div class="form-group"><label class="form-label">Área <span style="color:var(--text-muted);font-weight:400;">(opcional)</span></label>' +
      '<select name="area_id" id="area-select-create" class="form-select">' +
      '<option value="">-- Sin área --</option>' +
      '</select></div>' +
      '<div class="form-group"><label class="form-label">Piso <span style="color:var(--text-muted);font-weight:400;">(opcional)</span></label>' +
      '<select name="piso_id" id="piso-select-create" class="form-select">' +
      '<option value="">-- Sin piso --</option>' +
      '</select></div>' +
      '</div>' +
      '<div class="form-row single-col">' +
      this.fieldSelect('assigned_user_id', 'Usuario asignado', userIds, '', userLabels,
        [{ value: '', label: '— Sin asignar —' }]) +
      '</div>' +
      '<div class="form-group full">' +
      '<label class="form-label">Observaciones</label>' +
      '<textarea name="observations" class="form-textarea" rows="3" placeholder="Notas adicionales sobre el equipo…"></textarea>' +
      '</div>'
    );

    html += '</div>';
    content.innerHTML = html;
    this.setupCascadingListeners(content, locations, usuarios);
  },

  async crearEquipo(e) {
    e.preventDefault();
    const form = document.getElementById('form-crear-equipo');
    if (!form.reportValidity()) return;

    const formData = new FormData(form);
    const sede = formData.get('sede_id');
    const area = formData.get('area_id');
    const piso = formData.get('piso_id');
    formData.set('location_id', piso || area || sede || '');
    formData.append('action', 'create-equipo');
    formData.append('_token', CSRF_TOKEN);

    const btn = document.getElementById('btn-crear-equipo') || form.querySelector('.btn-submit');
    if (!btn) return;
    btn.disabled = true;
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
      this.showToast('error', 'Error', err.message || 'Error de conexión.');
    } finally {
      btn.disabled = false;
      btn.classList.remove('loading');
    }
  },

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
          body: new URLSearchParams({ reason: reason, _token: CSRF_TOKEN }).toString()
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
      this.showToast('error', 'Error', err.message || 'Error de conexión.');
    } finally {
      btn.classList.remove('loading');
    }
  },

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
        await fetch(BASE_URL + 'app/api/equipos.php?action=delete-permanent-equipo&id=' + encodeURIComponent(id), {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ reason: reason, _token: CSRF_TOKEN }).toString()
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
      this.showToast('error', 'Error', err.message || 'Error de conexión.');
    } finally {
      btn.classList.remove('loading');
    }
  },

  async reactivarEquipo(id) {
    try {
      const res = await this.parseJsonResponse(
        await fetch(BASE_URL + 'app/api/equipos.php?action=reactivate-equipo&id=' + encodeURIComponent(id), {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN },
          body: new URLSearchParams({ _token: CSRF_TOKEN }).toString()
        })
      );
      if (res.success) {
        this.showToast('success', 'Equipo reactivado', 'El equipo ha sido reactivado.');
        await this.loadData(this.state.pagination.page);
      } else {
        this.showToast('error', 'Error', res.error || 'No se pudo reactivar.');
      }
    } catch (err) {
      this.showToast('error', 'Error', err.message || 'Error de conexión.');
    }
  },

  escapeHtml(text) {
    if (!text && text !== 0) return '';
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
      '<button class="toast__close" data-action="toast-close"><i data-lucide="x" width="16" height="16"></i></button>';
    container.appendChild(toast);
    this.refreshIcons(toast);
    toast.querySelector('[data-action="toast-close"]').addEventListener('click', () => toast.remove());
    setTimeout(() => { if (toast.parentElement) toast.remove(); }, 5000);
  }
};

document.addEventListener('DOMContentLoaded', () => {
  EquiposApp.init().then(() => EquiposApp.refreshIcons());
});

})();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
