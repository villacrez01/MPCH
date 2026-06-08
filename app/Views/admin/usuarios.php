<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;

$isOtiAdmin = \App\Services\AuthService::isAdmin();

$tituloPagina = 'Control de Usuarios - Sistema OTI';
$paginaActual = 'admin-usuarios';

require_once __DIR__ . '/../../Models/User.php';
require_once __DIR__ . '/../../Models/Location.php';

$usuariosData = \App\Models\User::getAllWithDetails();
$statsData = \App\Models\User::getStats();
$locationsData = \App\Models\Location::getAll();
$hierarchyData = \App\Models\User::getLocationsHierarchy();

$totalEquipos = 0;
foreach ($usuariosData as $u) { $totalEquipos += ($u['equipos_count'] ?? 0); }
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<main id="main-content" class="main-content" data-initial-loaded="true">

  <div class="page-header">
    <div class="page-header-left">
      <h1 class="page-title">Control de Usuarios</h1>
      <p class="page-subtitle">Administraci&oacute;n de usuarios, ubicaci&oacute;n y equipos</p>
    </div>
    <div class="page-header-actions">
      <button type="button" class="btn-new" data-action="open-modal" data-modal="create-user">
        <i data-lucide="plus"></i>
        <span>Nuevo Usuario</span>
      </button>
      <button type="button" class="btn-secondary" data-action="export-csv" aria-label="Exportar usuarios a CSV">
        <i data-lucide="download"></i>
        Exportar CSV
      </button>
    </div>
  </div>

  <div class="stats-grid" id="stats-grid">
    <div class="stat-card total">
      <div class="stat-icon-wrap">
        <i data-lucide="users"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value" data-stat="total"><?= (int)($statsData['total'] ?? 0) ?></div>
        <div class="stat-label">Total Usuarios</div>
        <div class="stat-trend neutral"><?= count($usuariosData) ?> registrados</div>
      </div>
    </div>
    <div class="stat-card active">
      <div class="stat-icon-wrap">
        <i data-lucide="check-circle"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value" data-stat="activos"><?= (int)($statsData['activos'] ?? 0) ?></div>
        <div class="stat-label">Usuarios Activos</div>
        <div class="stat-trend up">
          <i data-lucide="chevron-up" style="width:12px;height:12px;"></i>
          En l&iacute;nea ahora
        </div>
      </div>
    </div>
    <div class="stat-card inactive">
      <div class="stat-icon-wrap">
        <i data-lucide="toggle-left"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value" data-stat="inactivos"><?= (int)($statsData['inactivos'] ?? 0) ?></div>
        <div class="stat-label">Inactivos</div>
        <div class="stat-trend neutral">Pueden ser reactivados</div>
      </div>
    </div>
    <div class="stat-card equipment">
      <div class="stat-icon-wrap">
        <i data-lucide="monitor"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value" data-stat="equipos"><?= $totalEquipos ?></div>
        <div class="stat-label">Equipos Asignados</div>
        <div class="stat-trend neutral">Distribuci&oacute;n total</div>
      </div>
    </div>
  </div>

  <div class="bulk-actions-bar" id="bulk-actions-bar">
    <div class="bulk-info">
      <i data-lucide="check-circle"></i>
      <span id="bulk-count">0 seleccionados</span>
    </div>
    <div class="bulk-buttons">
      <button type="button" class="bulk-bar-btn activate" data-action="bulk-activate">
        <i data-lucide="check-circle"></i>
        Activar
      </button>
      <button type="button" class="bulk-bar-btn deactivate" data-action="bulk-deactivate">
        <i data-lucide="toggle-left"></i>
        Desactivar
      </button>
      <button type="button" class="bulk-bar-btn destroy" data-action="bulk-delete">
        <i data-lucide="trash-2"></i>
        Eliminar
      </button>
      <button type="button" class="bulk-bar-btn" data-action="clear-selection">
        <i data-lucide="x"></i>
        Limpiar
      </button>
    </div>
  </div>

  <div class="filters-section">
    <div class="filters-header">
      <div class="filters-title">
        <i data-lucide="filter"></i>
        Filtros de b&uacute;squeda
      </div>
      <button type="button" class="clear-filters-btn" data-action="clear-filters">
        <i data-lucide="x"></i>
        Limpiar filtros
      </button>
    </div>
    <div class="filters-row">
      <div class="filter-group">
        <label class="filter-label">Ubicaci&oacute;n</label>
        <select class="filter-select" data-filter="location">
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
          <optgroup label="Oficinas">
            <?php foreach ($hierarchyData['oficinas'] as $oficina): ?>
            <option value="<?= $oficina['id'] ?>"><?= htmlspecialchars($oficina['name']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        </select>
      </div>
      <div class="filter-group">
        <label class="filter-label">Estado</label>
        <select class="filter-select" data-filter="status">
          <option value="">Todos</option>
          <option value="true">Activos</option>
          <option value="false">Inactivos</option>
        </select>
      </div>
      <div class="filter-group">
        <label class="filter-label">Rol</label>
        <select class="filter-select" data-filter="role">
          <option value="">Todos los roles</option>
          <?php
          $roles = array_unique(array_column($usuariosData, 'role_name'));
          foreach ($roles as $role): if ($role): ?>
          <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
          <?php endif; endforeach; ?>
        </select>
      </div>
      <div class="filter-group search-group">
        <label class="filter-label">Buscar</label>
        <div class="search-wrapper">
          <i data-lucide="search" class="search-icon"></i>
          <input type="text" class="search-input" data-filter="search" placeholder="Buscar por nombre, email o DNI..." autocomplete="off">
        </div>
      </div>
    </div>
  </div>

  <div class="table-card">
    <div class="table-header">
      <div class="table-title">
        <i data-lucide="users"></i>
        Usuarios Registrados
      </div>
      <span class="table-count" id="user-count"><?= count($usuariosData) ?> usuarios</span>
    </div>
    <div class="table-wrapper"><table class="usuarios-table">
      <thead>
        <tr>
          <th class="checkbox-col">
            <div class="checkbox-wrapper">
              <input type="checkbox" id="select-all" data-action="toggle-select-all">
              <div class="checkbox-custom"></div>
            </div>
          </th>
          <th class="sortable" data-action="sort-table" data-col="name">Usuario</th>
          <th>Email</th>
          <th class="sortable" data-action="sort-table" data-col="location">Ubicaci&oacute;n</th>
          <th>Rol</th>
          <th>Equipos</th>
          <th class="sortable" data-action="sort-table" data-col="status">Estado</th>
          <th style="min-width: 140px;">Acciones</th>
        </tr>
      </thead>
      <tbody id="users-table-body">
        <?php if (empty($usuariosData)): ?>
          <tr>
            <td colspan="8">
              <div class="empty-state">
                <i data-lucide="users"></i>
                <div class="empty-state-title">No hay usuarios registrados</div>
                <div class="empty-state-text">Comienza creando tu primer usuario haciendo clic en el bot&oacute;n &quot;Nuevo Usuario&quot;.</div>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($usuariosData as $user): ?>
          <tr data-user-id="<?= $user['id'] ?>">
            <td class="checkbox-col">
              <div class="checkbox-wrapper">
                <input type="checkbox" class="user-checkbox" value="<?= $user['id'] ?>" data-action="update-selection">
                <div class="checkbox-custom"></div>
              </div>
            </td>
            <td data-label="Usuario">
              <div class="user-cell">
                <div class="user-avatar"><?= strtoupper(substr($user['nombre'] ?? 'U', 0, 1)) ?></div>
                <div class="user-info-cell">
                  <div class="user-name-cell"><?= htmlspecialchars(($user['nombre'] ?? '') . ' ' . ($user['apellidos'] ?? '')) ?></div>
                  <div class="user-dni-cell"><?= htmlspecialchars($user['dni'] ?? 'Sin DNI') ?></div>
                </div>
              </div>
            </td>
            <td data-label="Email"><?= htmlspecialchars($user['email'] ?? '-') ?></td>
            <td data-label="Ubicaci&oacute;n">
              <div class="location-info">
                <i data-lucide="map-pin"></i>
                <?= htmlspecialchars($user['location_name'] ?? 'Sin asignar') ?>
              </div>
            </td>
            <td data-label="Rol">
              <span class="role-badge"><?= htmlspecialchars($user['role_name'] ?? 'Usuario') ?></span>
            </td>
            <td data-label="Equipos">
              <span class="equipment-badge">
                <i data-lucide="monitor"></i>
                <?= $user['equipos_count'] ?? 0 ?>
              </span>
            </td>
            <td data-label="Estado">
              <?php if ($user['activo']): ?>
              <span class="status-badge active">Activo</span>
              <?php else: ?>
              <span class="status-badge inactive">Inactivo</span>
              <?php endif; ?>
            </td>
            <td data-label="Acciones">
              <div class="action-cell">
                <button type="button" class="action-btn view" data-action="view-user" data-id="<?= $user['id'] ?>" title="Ver detalle" aria-label="Ver detalle">
                  <i data-lucide="eye"></i>
                </button>
                <button type="button" class="action-btn edit" data-action="open-permissions" data-id="<?= $user['id'] ?>" title="Editar permisos" aria-label="Editar permisos">
                  <i data-lucide="pencil"></i>
                </button>
                <div class="action-dd">
                  <button type="button" class="action-dd__btn" data-action="toggle-dd" data-id="<?= $user['id'] ?>" title="M&aacute;s acciones">
                    <i data-lucide="more-vertical"></i>
                  </button>
                  <div class="action-dd__menu" id="action-dd-<?= $user['id'] ?>">
                    <?php if ($user['activo']): ?>
                    <button type="button" class="action-dd__item action-dd__item--warning" data-action="deactivate-user" data-id="<?= $user['id'] ?>">
                      <i data-lucide="toggle-left"></i>
                      Desactivar
                    </button>
                    <?php else: ?>
                    <button type="button" class="action-dd__item action-dd__item--success" data-action="reactivate-user" data-id="<?= $user['id'] ?>">
                      <i data-lucide="check-circle"></i>
                      Reactivar
                    </button>
                    <?php endif; ?>
                    <button type="button" class="action-dd__item action-dd__item--danger" data-action="delete-user" data-id="<?= $user['id'] ?>">
                      <i data-lucide="trash-2"></i>
                      Eliminar permanentemente
                    </button>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table></div>
  </div>
</main>

<div class="toast-container" id="toast-container"></div>

<!-- Modal: Create User -->
<div class="modal-overlay" id="modal-create-user" data-modal="create-user" role="dialog" aria-modal="true" aria-labelledby="create-user-title">
  <div class="modal" style="max-width:620px;">
    <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-soft) 0%, var(--bg-card) 100%); border-bottom: 1px solid var(--border-light);">
      <div style="display:flex; align-items:center; gap:14px;">
        <div style="width:44px;height:44px;border-radius:var(--radius-md);background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i data-lucide="user-plus" style="width:22px;height:22px;color:white;"></i>
        </div>
        <div>
          <h3 class="modal-title" id="create-user-title" style="margin:0;font-size:18px;">Registrar Nuevo Usuario</h3>
          <p style="margin:2px 0 0;font-size:12px;color:var(--text-muted);">Complete los datos del nuevo usuario</p>
        </div>
      </div>
      <button type="button" class="modal-close" data-action="close-modal" data-modal="create-user" aria-label="Cerrar">
        <i data-lucide="x"></i>
      </button>
    </div>
    <form id="form-create-user">
       <div class="modal-body" style="padding:28px;">
         <div class="form-row">
           <div class="form-group">
             <label class="form-label">Nombre <span class="required">*</span></label>
             <input type="text" name="nombre" id="new-user-nombre" class="form-input" placeholder="Ej: Juan" required autocomplete="given-name">
           </div>
           <div class="form-group">
             <label class="form-label">Apellidos</label>
             <input type="text" name="apellidos" id="new-user-apellidos" class="form-input" placeholder="Ej: García Torres" autocomplete="family-name">
           </div>
         </div>
         <div class="form-row">
           <div class="form-group">
             <label class="form-label">Email institucional <span class="required">*</span></label>
             <input type="email" name="email" id="new-user-email" class="form-input" placeholder="Ej: usuario@municipalidad.gob.pe" required autocomplete="email">
           </div>
           <div class="form-group">
             <label class="form-label">DNI / N° Documento</label>
             <input type="text" name="dni" id="new-user-dni" class="form-input" placeholder="Ej: 12345678" maxlength="20">
           </div>
         </div>
         <div class="form-row">
           <div class="form-group" style="grid-column: 1 / -1;">
             <label class="form-label">Teléfono</label>
             <input type="tel" name="phone" id="new-user-phone" class="form-input" placeholder="Ej: 999 999 999" maxlength="20">
             <input type="hidden" name="rol" id="new-user-rol" value="Usuario">
           </div>
         </div>
         <div class="form-row single">
           <div class="form-group">
             <label class="form-label">Contraseña <span class="required">*</span></label>
             <div style="position:relative;">
               <input type="password" name="password" id="new-user-password" class="form-input" placeholder="Mínimo 8 caracteres" required style="padding-right:42px;">
               <button type="button" id="toggle-password-btn" onclick="(function(){var i=document.getElementById('new-user-password');i.type=i.type==='password'?'text':'password';})()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);display:flex;align-items:center;" tabindex="-1" aria-label="Mostrar/ocultar contraseña">
                 <i data-lucide="eye" style="width:16px;height:16px;"></i>
               </button>
             </div>
           </div>
         </div>
       </div>
       <div class="modal-footer">
         <button type="button" class="btn-cancel" data-action="close-modal" data-modal="create-user">Cancelar</button>
         <button type="submit" class="btn-submit" id="btn-save-new-user">
           <i data-lucide="user-check"></i>
           Registrar Usuario
         </button>
       </div>
     </form>
  </div>
</div>


<!-- Modal: User Detail -->
<div class="modal-overlay" id="modal-user-detail" data-modal="user-detail" role="dialog" aria-modal="true" aria-labelledby="user-detail-title">
  <div class="modal" style="max-width:700px;">
    <div class="modal-header">
      <h3 class="modal-title" id="user-detail-title">
        <i data-lucide="user"></i>
        Detalle del Usuario
      </h3>
      <button type="button" class="modal-close" data-action="close-modal" data-modal="user-detail" aria-label="Cerrar">
        <i data-lucide="x"></i>
      </button>
    </div>
    <div class="modal-body" id="user-detail-content">
      <div class="skeleton-detail">
        <i data-lucide="loader-2" style="width:32px;height:32px;color:var(--text-muted);animation:spin 1s linear infinite;"></i>
        <p style="color:var(--text-muted);margin-top:12px;">Cargando...</p>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-cancel" data-action="close-modal" data-modal="user-detail">Cerrar</button>
    </div>
  </div>
</div>

<!-- Modal: Edit Permissions -->
<div class="modal-overlay" id="modal-edit-permissions" data-modal="edit-permissions" role="dialog" aria-modal="true" aria-labelledby="edit-permissions-title">
  <div class="modal" style="max-width:600px;">
    <div class="modal-header">
      <h3 class="modal-title" id="edit-permissions-title">
        <i data-lucide="shield"></i>
        Permisos de Usuario
      </h3>
      <button type="button" class="modal-close" data-action="close-modal" data-modal="edit-permissions" aria-label="Cerrar">
        <i data-lucide="x"></i>
      </button>
    </div>
    <div class="modal-body" id="permissions-body">
      <div style="text-align:center;padding:40px;color:var(--text-muted);">Cargando permisos...</div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-cancel" data-action="close-modal" data-modal="edit-permissions">Cancelar</button>
      <button type="button" class="btn-submit" id="save-permissions-btn" data-action="save-permissions">Guardar Permisos</button>
    </div>
  </div>
</div>

<!-- Modal: Deactivate User -->
<div class="modal-overlay" id="modal-deactivate-user" data-modal="deactivate-user" role="dialog" aria-modal="true" aria-labelledby="deactivate-user-title">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <h3 class="modal-title" id="deactivate-user-title">
        <i data-lucide="alert-triangle"></i>
        Desactivar Usuario
      </h3>
      <button type="button" class="modal-close" data-action="close-modal" data-modal="deactivate-user" aria-label="Cerrar">
        <i data-lucide="x"></i>
      </button>
    </div>
    <div class="modal-body">
      <p>Al desactivar se eliminar&aacute;n las asignaciones de equipos. &iquest;Continuar?</p>
      <div class="form-group">
        <label class="form-label">Motivo <span class="required">*</span></label>
        <textarea id="deactivate-user-reason" class="form-textarea" rows="3" placeholder="Ej: Baja del personal, cambio de &aacute;rea..."></textarea>
      </div>
      <input type="hidden" id="deactivate-user-id">
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-cancel" data-action="close-modal" data-modal="deactivate-user">Cancelar</button>
      <button type="button" class="btn-submit" data-action="confirm-deactivate" style="background-color:var(--warning);">Confirmar Desactivaci&oacute;n</button>
    </div>
  </div>
</div>

<!-- Modal: Delete User Permanent -->
<div class="modal-overlay" id="modal-delete-user" data-modal="delete-user" role="dialog" aria-modal="true" aria-labelledby="delete-user-title">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <h3 class="modal-title" id="delete-user-title">
        <i data-lucide="trash-2"></i>
        Eliminar Permanentemente
      </h3>
      <button type="button" class="modal-close" data-action="close-modal" data-modal="delete-user" aria-label="Cerrar">
        <i data-lucide="x"></i>
      </button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:16px;color:var(--danger);">Esta acci&oacute;n NO se puede deshacer.</p>
      <div class="form-group">
        <label class="form-label">Motivo <span class="required">*</span></label>
        <textarea id="delete-user-reason" class="form-textarea" rows="3" placeholder="Ej: Robo, p&eacute;rdida, duplicado..."></textarea>
      </div>
      <input type="hidden" id="delete-user-id">
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-cancel" data-action="close-modal" data-modal="delete-user">Cancelar</button>
      <button type="button" class="btn-submit" data-action="confirm-delete" style="background-color:var(--danger);">Eliminar</button>
    </div>
  </div>
</div>

<style>
/* ── Stat Counter Animation ── */
@keyframes countUp {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.stat-value { animation: countUp var(--duration-slow) var(--ease-out); }

/* ── Skeleton Shimmer ── */
@keyframes skeleton-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.skeleton-row { display: contents; }
.skeleton-cell { padding: 12px var(--space-md); }
.skeleton-cell .skeleton {
  height: 16px;
  border-radius: var(--radius-xs);
  background: linear-gradient(90deg, var(--bg-main) 0%, var(--bg-hover) 50%, var(--bg-main) 100%);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.5s ease-in-out infinite;
}
.skeleton-detail {
  text-align: center;
  padding: 40px;
}

/* ── Spin ── */
@keyframes spin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

/* ── Toast Animation ── */
@keyframes toastIn {
  from { opacity: 0; transform: translateX(100%); }
  to   { opacity: 1; transform: translateX(0); }
}

/* ── Bulk Bar Slide ── */
@keyframes bulkSlideIn {
  from { opacity: 0; transform: translateY(-12px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Modal Overlay ── */
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

/* ── Modal Tabs ── */
.modal-tabs {
  display: flex;
  gap: 4px;
  margin-bottom: var(--space-md);
  border-bottom: 1px solid var(--border-light);
  padding-bottom: 0;
}
.modal-tab {
  padding: 10px 18px;
  border: none;
  background: transparent;
  color: var(--text-secondary);
  font-size: var(--font-size-base);
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: all var(--duration-fast);
  margin-bottom: -1px;
}
.modal-tab:hover { color: var(--text-primary); }
.modal-tab.active {
  color: var(--primary);
  border-bottom-color: var(--primary);
  font-weight: 600;
}
.tab-content { display: block; }
.tab-content[style*="display:none"],
.tab-content[style*="display: none"] { display: none !important; }

/* ── Stats Grid ── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-md);
  margin-bottom: var(--space-lg);
}
.stat-card {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-lg);
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-1);
  transition: all var(--duration-normal);
}
.stat-card:hover { box-shadow: var(--shadow-3); transform: translateY(-1px); }
.stat-icon-wrap {
  width: 52px; height: 52px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.stat-card.total .stat-icon-wrap { background: var(--primary-soft); }
.stat-card.total .stat-icon-wrap svg { color: var(--primary); }
.stat-card.active .stat-icon-wrap { background: var(--success-soft); }
.stat-card.active .stat-icon-wrap svg { color: var(--success); }
.stat-card.inactive .stat-icon-wrap { background: var(--info-soft); }
.stat-card.inactive .stat-icon-wrap svg { color: var(--info); }
.stat-card.equipment .stat-icon-wrap { background: var(--gold-soft); }
.stat-card.equipment .stat-icon-wrap svg { color: var(--gold); }
.stat-icon-wrap svg { width: 24px; height: 24px; }
.stat-content { flex: 1; min-width: 0; }
.stat-value {
  font-size: var(--font-size-2xl);
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.1;
}
.stat-label {
  font-size: var(--font-size-sm);
  color: var(--text-secondary);
  font-weight: 500;
  margin-top: 2px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.stat-trend {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: var(--font-size-xs);
  margin-top: 6px;
  font-weight: 500;
}
.stat-trend.up { color: var(--success); }
.stat-trend.neutral { color: var(--text-muted); }
.stat-trend.down { color: var(--danger); }

/* ── Bulk Actions Bar ── */
.bulk-actions-bar {
  display: none;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-sm) var(--space-md);
  background: var(--bg-card);
  border: 1px solid var(--primary);
  border-radius: var(--radius-md);
  margin-bottom: var(--space-md);
  animation: bulkSlideIn var(--duration-normal) var(--ease-out);
}
.bulk-actions-bar.show { display: flex; }
.bulk-info {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: var(--font-size-base);
  font-weight: 600;
  color: var(--text-primary);
}
.bulk-info svg { width: 18px; height: 18px; color: var(--primary); }
.bulk-buttons { display: flex; gap: var(--space-sm); flex-wrap: wrap; }
.bulk-bar-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border: 1.5px solid var(--border-light);
  border-radius: var(--radius-md);
  background: var(--bg-card);
  font-size: var(--font-size-sm);
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  color: var(--text-primary);
  transition: all var(--duration-fast);
}
.bulk-bar-btn:hover { border-color: var(--border-medium); background: var(--bg-hover); }
.bulk-bar-btn svg { width: 16px; height: 16px; }
.bulk-bar-btn.activate:hover { border-color: var(--success); color: var(--success); background: var(--success-soft); }
.bulk-bar-btn.deactivate:hover { border-color: var(--warning); color: var(--warning); background: var(--warning-soft); }
.bulk-bar-btn.destroy:hover { border-color: var(--danger); color: var(--danger); background: var(--danger-soft); }

/* ── Filters Section ── */
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
.filter-group.search-group { flex: 1; min-width: 220px; }
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

/* ── Action Cell ── */
.action-cell {
  display: flex;
  align-items: center;
  gap: 4px;
  position: relative;
  white-space: nowrap;
  flex-wrap: nowrap;
}
.action-cell .action-dd {
  position: relative;
  display: inline-block;
  flex-shrink: 0;
}
.action-dd__btn {
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
.action-dd__btn:hover { border-color: var(--border-light); background: var(--bg-hover); }
.action-dd__btn svg { width: 16px; height: 16px; }

.action-dd__menu {
  position: fixed;
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-lg);
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

/* ── Table Card ── */
.table-card {
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-1);
  overflow: visible;
}
.table-wrapper {
  overflow: visible;
  -webkit-overflow-scrolling: touch;
}
.table-card table {
  min-width: 800px;
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
.table-card table {
  width: 100%;
  border-collapse: collapse;
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
.table-card thead th.sortable {
  cursor: pointer;
  user-select: none;
  transition: color var(--duration-fast);
}
.table-card thead th.sortable:hover { color: var(--primary); }
.table-card tbody td {
  padding: 12px var(--space-md);
  font-size: var(--font-size-base);
  color: var(--text-primary);
  border-bottom: 1px solid var(--border-light);
  vertical-align: middle;
}
.table-card tbody tr:hover { background: var(--primary-soft); }
.table-card tbody tr.selected { background: var(--primary-soft); box-shadow: inset 3px 0 0 var(--primary); }
.table-card tbody tr:last-child td { border-bottom: none; }
.checkbox-col { width: 40px; }
.checkbox-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
}
.checkbox-wrapper input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--primary);
  cursor: pointer;
}

/* ── User Cell ── */
.user-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}
.user-avatar {
  width: 38px;
  height: 38px;
  border-radius: var(--radius-full);
  background: var(--primary);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--font-size-sm);
  font-weight: 700;
  flex-shrink: 0;
  text-transform: uppercase;
}
.user-info-cell { min-width: 0; }
.user-name-cell {
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.user-dni-cell {
  font-size: var(--font-size-sm);
  color: var(--text-muted);
  margin-top: 1px;
}

/* ── Location Info ── */
.location-info {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: var(--font-size-base);
  color: var(--text-secondary);
}
.location-info svg { width: 14px; height: 14px; color: var(--text-muted); flex-shrink: 0; }

/* ── Role Badge ── */
.role-badge {
  display: inline-block;
  padding: 3px 10px;
  background: var(--gold-soft);
  color: var(--gold-dark);
  border-radius: var(--radius-full);
  font-size: var(--font-size-sm);
  font-weight: 600;
}
.equipment-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: var(--font-size-base);
  font-weight: 600;
  color: var(--text-primary);
}
.equipment-badge svg { width: 14px; height: 14px; color: var(--primary); }

/* ── Status Badge ── */
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
.status-badge.inactive { background: var(--info-soft); color: var(--info); }
.status-badge svg { width: 14px; height: 14px; }

/* ── Action Cell - botones específicos de usuarios ── */
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

/* ── Equipment Items ── */
.equipment-list { display: flex; flex-direction: column; gap: 8px; }
.equipment-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px;
  background: var(--bg-main);
  border-radius: var(--radius-md);
  border: 1px solid var(--border-light);
  transition: border-color var(--duration-fast);
}
.equipment-item:hover { border-color: var(--border-medium); }
.equipment-item label {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  flex: 1;
  min-width: 0;
}
.equipment-item label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--primary);
  flex-shrink: 0;
}
.equipment-name {
  font-weight: 600;
  color: var(--text-primary);
  font-size: var(--font-size-base);
}
.equipment-code {
  font-size: var(--font-size-sm);
  color: var(--text-muted);
  margin-top: 1px;
}

/* ── Permission Checkbox Grid ── */
.permissions-section {
  margin-bottom: var(--space-lg);
}
.permissions-section-title {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: var(--font-size-md);
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: var(--space-sm);
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border-light);
}
.permissions-section-title svg {
  width: 18px;
  height: 18px;
  color: var(--primary);
}
.permissions-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.permission-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  background: var(--bg-main);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: background var(--duration-fast);
}
.permission-item:hover { background: var(--bg-hover); }
.permission-item input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--primary);
  flex-shrink: 0;
}
.permission-label {
  font-size: var(--font-size-base);
  color: var(--text-primary);
  font-weight: 500;
}

/* ── User Detail Sections ── */
.user-detail-section {
  margin-bottom: var(--space-lg);
}
.user-detail-section:last-child { margin-bottom: 0; }
.user-detail-title {
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
.user-detail-title svg { width: 18px; height: 18px; color: var(--primary); }
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
.empty-state-text {
  font-size: var(--font-size-base);
  color: var(--text-secondary);
  margin-top: 6px;
}

/* ── Page Header ── */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-md);
  margin-bottom: var(--space-lg);
}
.page-header-left { min-width: 0; }
.page-title {
  font-size: var(--font-size-xl);
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}
.page-subtitle {
  font-size: var(--font-size-base);
  color: var(--text-secondary);
  margin-top: 4px;
}
.page-header-actions {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

/* ── Button Classes ── */
.btn-new {
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
  padding: 10px 22px;
  background: var(--primary);
  color: var(--text-inverse);
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
.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
  padding: 10px 22px;
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
.btn-secondary:hover { border-color: var(--border-medium); color: var(--text-primary); background: var(--bg-hover); }
.btn-secondary svg { width: 16px; height: 16px; }
.btn-submit {
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
  padding: 10px 24px;
  background: var(--primary);
  color: var(--text-inverse);
  border: none;
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: all var(--duration-fast);
}
.btn-submit:hover { background: var(--primary-light); transform: translateY(-1px); box-shadow: var(--shadow-3); }
.btn-submit:disabled { opacity: 0.5; cursor: default; transform: none; box-shadow: none; }
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

/* ── Form Classes ── */
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-md);
  margin-bottom: var(--space-md);
}
.form-row.single { grid-template-columns: 1fr; }
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
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

/* ── Responsive ── */
@media (max-width: 1024px) {
  .filters-row { flex-direction: column; align-items: stretch; }
  .filter-group, .filter-select, .search-wrapper { width: 100%; min-width: 0; }
  .filter-select { min-width: 0; }
  .form-row { grid-template-columns: 1fr; }
  .detail-grid { grid-template-columns: 1fr; }
  .table-wrapper { overflow-x: auto; }
}
@media (max-width: 768px) {
  .stats-grid { grid-template-columns: 1fr 1fr; gap: var(--space-sm); }
  .stat-card { padding: var(--space-md); }
  .stat-icon-wrap { width: 44px; height: 44px; }
  .stat-icon-wrap svg { width: 20px; height: 20px; }
  .stat-value { font-size: 20px; }
  .stat-label { font-size: 12px; }
  .modal { max-width: 95vw; margin: 10px auto; }
  .modal-body { padding: var(--space-md); }
  .modal-header { padding: var(--space-md) var(--space-md) var(--space-sm); }
  .modal-footer { padding: var(--space-sm) var(--space-md); flex-direction: column; }
  .modal-footer .btn-cancel,
  .modal-footer .btn-submit { width: 100%; justify-content: center; }
  .permissions-grid { grid-template-columns: 1fr; }
  .table-wrapper { border: none; padding: 0; background: transparent; overflow: visible; }
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
  .table-card .table-wrapper td.checkbox-col,
  .table-card .table-wrapper td[data-label="Acciones"] {
    display: flex;
    justify-content: flex-end;
    padding-top: var(--space-sm);
    margin-top: var(--space-sm);
    border-top: 1px solid var(--border-light);
  }
  .table-card .table-wrapper td.checkbox-col::before,
  .table-card .table-wrapper td[data-label="Acciones"]::before { display: none; }
  .table-card .table-wrapper td.checkbox-col { order: -1; margin-top: 0; border-top: none; }
}
@media (max-width: 480px) {
  .stats-grid { grid-template-columns: 1fr; }
  .page-header { flex-direction: column; align-items: stretch; }
  .page-header-actions { flex-direction: column; }
  .page-header-actions .btn-new,
  .page-header-actions .btn-secondary { width: 100%; justify-content: center; }
  .modal { max-width: 98vw; }
  .modal-footer { flex-direction: column; }
  .modal-footer .btn-cancel,
  .modal-footer .btn-submit { width: 100%; justify-content: center; }
  .filters-section { padding: var(--space-sm) var(--space-md); }
  .table-header { flex-direction: column; align-items: flex-start; gap: 6px; }
}
@media (prefers-reduced-motion: reduce) {
  .stat-value { animation: none; }
  .skeleton-cell .skeleton,
  .skeleton-stat-icon,
  .skeleton-stat-line { animation: none; background: var(--bg-hover); }
  .action-dd__menu { animation: none; }
  .modal-overlay.active .modal { animation: none; }
  .stat-card { transition: none; }
  .toast { animation: none; }
  .bulk-actions-bar { animation: none; }
  * { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
}
</style>

<script>
;(function() {
'use strict';

const BASE_URL = '<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const USERS_STRING = '<?= json_encode(array_map(function($u) {
    return ['id' => $u['id'], 'nombre' => $u['nombre'] ?? '', 'apellidos' => $u['apellidos'] ?? '', 'email' => $u['email'] ?? '', 'dni' => $u['dni'] ?? '', 'phone' => $u['phone'] ?? '', 'rol' => $u['role_name'] ?? 'Usuario', 'location_name' => $u['location_name'] ?? 'Sin asignar', 'activo' => (bool)$u['activo'], 'equipos_count' => $u['equipos_count'] ?? 0, 'role_name' => $u['role_name'] ?? 'Usuario']; }, $usuariosData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>';

const UsuariosApp = {
  selectedUsers: [],
  searchTimeout: null,
  currentPermissionsUserId: null,
  sortState: { col: '', dir: 'asc' },
  _previousFocus: null,

  init() {
    this.usersData = (function() {
      try { return JSON.parse(USERS_STRING); } catch(e) { return []; }
    })();
    this.bindEvents();
    this.animateAllStats();
    if (typeof lucide !== 'undefined') lucide.createIcons();
  },

  animateAllStats() {
    document.querySelectorAll('[data-stat]').forEach(el => {
      const target = parseInt(el.textContent) || 0;
      this.animateCounter(el, target);
    });
  },

  animateCounter(element, target) {
    const current = parseInt(element.textContent) || 0;
    const duration = 400;
    const startTime = performance.now();
    const animate = (now) => {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const value = Math.round(current + (target - current) * eased);
      element.textContent = value;
      if (progress < 1) {
        requestAnimationFrame(animate);
      } else {
        element.textContent = target;
      }
    };
    requestAnimationFrame(animate);
  },

  escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
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
    if (typeof lucide !== 'undefined') lucide.createIcons();
    toast.querySelector('[data-action="toast-close"]').addEventListener('click', function() { toast.remove(); });
    setTimeout(function() { if (toast.parentElement) toast.remove(); }, 5000);
  },

  bindEvents() {
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-action]');
      
      if (!btn || btn.getAttribute('data-action') !== 'toggle-dd') {
        document.querySelectorAll('.action-dd__menu.active').forEach(m => m.classList.remove('active'));
      }

      if (!btn) return;
      const action = btn.getAttribute('data-action');
      switch (action) {
        case 'open-modal':          this.openModal(btn.getAttribute('data-modal')); break;
        case 'close-modal':         this.closeModal(btn.getAttribute('data-modal')); break;
        case 'toggle-select-all':   this.toggleSelectAll(); break;
        case 'update-selection':    this.updateBulkBar(); break;
        case 'view-user':           this.openUserDetail(btn.getAttribute('data-id')); break;
        case 'open-permissions':    this.openPermissionsModal(btn.getAttribute('data-id')); break;
        case 'save-permissions':    this.savePermissions(); break;
        case 'deactivate-user':     this.openDeactivateModal(btn.getAttribute('data-id')); break;
        case 'confirm-deactivate':  this.confirmDeactivate(); break;
        case 'reactivate-user':     this.reactivateUser(btn.getAttribute('data-id')); break;
        case 'delete-user':         this.openDeleteModal(btn.getAttribute('data-id')); break;
        case 'confirm-delete':      this.confirmDeletePermanent(); break;
        case 'export-csv':          this.exportTableToCSV('.usuarios-table', 'usuarios'); break;
        case 'clear-filters':       this.clearFilters(); break;
        case 'sort-table':          this.sortTable(btn.getAttribute('data-col')); break;
        case 'clear-selection':     this.clearSelection(); break;
        case 'bulk-activate':       this.bulkActivate(); break;
        case 'bulk-deactivate':     this.bulkDeactivate(); break;
        case 'bulk-delete':         this.bulkDelete(); break;
        case 'switch-tab':          this.switchTab(btn, btn.getAttribute('data-tab')); break;
        case 'toast-close':         break;
        case 'toggle-dd':           this.toggleDropdown(btn.getAttribute('data-id')); break;
      }
    });

    document.querySelectorAll('[data-filter]').forEach(el => {
      const eventType = el.tagName === 'SELECT' ? 'change' : 'input';
      el.addEventListener(eventType, () => {
        if (eventType === 'input') {
          clearTimeout(this.searchTimeout);
          this.searchTimeout = setTimeout(() => this.applyFilters(), 300);
        } else {
          this.applyFilters();
        }
      });
    });

     document.getElementById('form-create-user')?.addEventListener('submit', (e) => this.createUser(e));

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        this.closeAllModals();
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

    const wrapper = document.querySelector('.table-wrapper');
    if (wrapper) {
      wrapper.addEventListener('scroll', () => {
        document.querySelectorAll('.action-dd__menu.active').forEach(m => m.classList.remove('active'));
      }, { passive: true });
    }
    
    window.addEventListener('scroll', () => {
      document.querySelectorAll('.action-dd__menu.active').forEach(m => m.classList.remove('active'));
    }, { passive: true });
  },

  applyFilters() {
    const location = document.querySelector('[data-filter="location"]').value;
    const status = document.querySelector('[data-filter="status"]').value;
    const role = document.querySelector('[data-filter="role"]').value;
    const search = document.querySelector('[data-filter="search"]').value.toLowerCase();

    const rows = document.querySelectorAll('#users-table-body tr[data-user-id]');
    let visibleCount = 0;

    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      const locOption = document.querySelector('[data-filter="location"] option[value="' + location + '"]');
      const locationMatch = !location || (locOption && text.includes(locOption.textContent.toLowerCase()));
      const statusMatch = !status || (status === 'true' && text.includes('activo')) || (status === 'false' && text.includes('inactivo'));
      const roleMatch = !role || text.includes(role.toLowerCase());
      const searchMatch = !search || text.includes(search);

      if (locationMatch && statusMatch && roleMatch && searchMatch) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });

    document.getElementById('user-count').textContent = visibleCount + ' usuarios';
  },

  clearFilters() {
    document.querySelector('[data-filter="location"]').value = '';
    document.querySelector('[data-filter="status"]').value = '';
    document.querySelector('[data-filter="role"]').value = '';
    document.querySelector('[data-filter="search"]').value = '';
    this.applyFilters();
  },

  sortTable(col) {
    if (this.sortState.col === col) {
      this.sortState.dir = this.sortState.dir === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortState.col = col;
      this.sortState.dir = 'asc';
    }
    const tbody = document.getElementById('users-table-body');
    const rows = Array.from(tbody.querySelectorAll('tr[data-user-id]'));
    const dir = this.sortState.dir === 'asc' ? 1 : -1;

    rows.sort((a, b) => {
      let valA, valB;
      switch (col) {
        case 'name':
          valA = a.querySelector('.user-name-cell')?.textContent?.trim().toLowerCase() || '';
          valB = b.querySelector('.user-name-cell')?.textContent?.trim().toLowerCase() || '';
          return valA.localeCompare(valB) * dir;
        case 'location':
          valA = a.querySelector('.location-info')?.textContent?.trim().toLowerCase() || '';
          valB = b.querySelector('.location-info')?.textContent?.trim().toLowerCase() || '';
          return valA.localeCompare(valB) * dir;
        case 'status':
          valA = a.querySelector('.status-badge')?.textContent?.trim() || '';
          valB = b.querySelector('.status-badge')?.textContent?.trim() || '';
          return valA.localeCompare(valB) * dir;
        default:
          return 0;
      }
    });

    rows.forEach(row => tbody.appendChild(row));
  },

  toggleSelectAll() {
    const checked = document.getElementById('select-all').checked;
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = checked);
    this.updateBulkBar();
  },

  updateBulkBar() {
    this.selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
    const bar = document.getElementById('bulk-actions-bar');
    const count = document.getElementById('bulk-count');
    if (this.selectedUsers.length > 0) {
      bar.classList.add('show');
      count.textContent = this.selectedUsers.length + ' seleccionado' + (this.selectedUsers.length > 1 ? 's' : '');
    } else {
      bar.classList.remove('show');
    }
    document.querySelectorAll('#users-table-body tr[data-user-id]').forEach(row => {
      const cb = row.querySelector('.user-checkbox');
      row.classList.toggle('selected', cb?.checked);
    });
  },

  clearSelection() {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('select-all').checked = false;
    this.updateBulkBar();
  },

  async bulkActivate() {
    if (this.selectedUsers.length === 0) return;
    if (!confirm('¿Activar ' + this.selectedUsers.length + ' usuario(s)?')) return;
    for (const id of this.selectedUsers) {
      await fetch(BASE_URL + 'app/api/usuarios.php?action=reactivate', {
        method: 'POST',
        body: new URLSearchParams({ id: id })
      });
    }
    this.showToast('success', 'Usuarios activados', this.selectedUsers.length + ' usuario(s) activado(s) exitosamente.');
    this.clearSelection();
    await this.refreshAll();
  },

  async bulkDeactivate() {
    if (this.selectedUsers.length === 0) return;
    if (!confirm('¿Desactivar ' + this.selectedUsers.length + ' usuario(s)?')) return;
    for (const id of this.selectedUsers) {
      await fetch(BASE_URL + 'app/api/usuarios.php?action=delete', {
        method: 'POST',
        body: new URLSearchParams({ id: id })
      });
    }
    this.showToast('success', 'Usuarios desactivados', this.selectedUsers.length + ' usuario(s) desactivado(s) exitosamente.');
    this.clearSelection();
    await this.refreshAll();
  },

  async bulkDelete() {
    if (this.selectedUsers.length === 0) return;
    if (!confirm('¿Eliminar PERMANENTEMENTE ' + this.selectedUsers.length + ' usuario(s)? Esta acci\u00f3n no se puede deshacer.')) return;
    for (const id of this.selectedUsers) {
      await fetch(BASE_URL + 'app/api/usuarios.php?action=delete-permanent', {
        method: 'POST',
        body: new URLSearchParams({ id: id })
      });
    }
    this.showToast('success', 'Usuarios eliminados', this.selectedUsers.length + ' usuario(s) eliminado(s) permanentemente.');
    this.clearSelection();
    await this.refreshAll();
  },

  openModal(modalId) {
    const overlay = document.getElementById('modal-' + modalId);
    if (!overlay) return;
    this._previousFocus = document.activeElement;
    overlay.classList.add('active');
    setTimeout(() => {
      const focusable = overlay.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (focusable.length > 0) focusable[0].focus();
    }, 50);
  },

  closeModal(modalId) {
    const overlay = document.getElementById('modal-' + modalId);
    if (!overlay) return;
    overlay.classList.remove('active');
    if (this._previousFocus && this._previousFocus.focus) {
      this._previousFocus.focus();
    }
    this._previousFocus = null;
  },

  closeAllModals() {
    document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    if (this._previousFocus && this._previousFocus.focus) {
      this._previousFocus.focus();
    }
    this._previousFocus = null;
  },

  switchTab(btn, tabId) {
    const parent = btn.closest('.modal-body');
    if (!parent) return;
    parent.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    parent.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
    const target = parent.querySelector('#' + tabId);
    if (target) target.style.display = 'block';
  },

  toggleDropdown(id) {
    const menu = document.getElementById('action-dd-' + id);
    if (!menu) return;
    
    const isActive = menu.classList.contains('active');
    
    document.querySelectorAll('.action-dd__menu').forEach(m => {
      m.classList.remove('active');
      m.style.top = '';
      m.style.left = '';
    });
    
    if (!isActive) {
      const btn = document.querySelector(`[data-action="toggle-dd"][data-id="${id}"]`);
      if (btn) {
        menu.classList.add('active');
        const btnRect = btn.getBoundingClientRect();
        
        requestAnimationFrame(() => {
          const menuRect = menu.getBoundingClientRect();
          let top = btnRect.bottom + 4;
          let left = btnRect.right - menuRect.width;
          
          if (top + menuRect.height > window.innerHeight) {
            top = btnRect.top - menuRect.height - 4;
          }
          if (left < 0) left = 10;
          
          menu.style.top = top + 'px';
          menu.style.left = left + 'px';
        });
      }
    }
  },

  async loadAvailableEquipment(locationId, container) {
    try {
      const res = await fetch(BASE_URL + 'app/api/usuarios.php?action=equipment-available&location_id=' + encodeURIComponent(locationId));
      const data = await res.json();
      if (data.equipos && data.equipos.length > 0) {
        let html = '';
        data.equipos.forEach(function(eq) {
          html += '<div class="equipment-item"><label><input type="checkbox" name="equipment_ids" value="' + eq.id + '"><div><div class="equipment-name">' + this.escapeHtml(eq.name) + '</div><div class="equipment-code">' + this.escapeHtml(eq.patrimonial_code || eq.serial_number || 'Sin c\u00f3digo') + '</div></div></label></div>';
        }.bind(this));
        container.innerHTML = html;
      } else {
        container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px;">No hay equipos disponibles en esta ubicaci\u00f3n</div>';
      }
      if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (e) {
      container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--danger);font-size:13px;">Error cargando equipos</div>';
    }
  },

  async createUser(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    const password = formData.get('password');
    if (!password || password.length < 8) {
      this.showToast('error', 'Contraseña muy corta', 'La contraseña debe tener al menos 8 caracteres.');
      return;
    }

    const btn = document.getElementById('btn-save-new-user');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i data-lucide="loader-2" style="animation:spin 1s linear infinite;width:16px;height:16px;"></i> Registrando...'; if (typeof lucide !== 'undefined') lucide.createIcons(); }

    try {
      const res = await fetch(BASE_URL + 'app/api/usuarios.php?action=create', {
        method: 'POST',
        body: formData
      });
      const result = await res.json();
      if (result.success) {
        this.showToast('success', 'Usuario registrado', 'El usuario ha sido creado exitosamente. Recuerda asignarle una ubicación desde Estructura Orgánica.');
        form.reset();
        this.closeModal('create-user');
        await this.refreshAll();
      } else {
        this.showToast('error', 'Error al crear', result.error || 'No se pudo crear el usuario.');
      }
    } catch (e) {
      this.showToast('error', 'Error de conexión', 'No se pudo conectar al servidor.');
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="user-check"></i> Registrar Usuario'; if (typeof lucide !== 'undefined') lucide.createIcons(); }
    }
  },


  async refreshAll() {
    try {
      const params = new URLSearchParams({ action: 'list', page: '1', pageSize: '9999' });
      const res = await fetch(BASE_URL + 'app/api/usuarios.php?' + params.toString());
      const data = await res.json();
      if (data.usuarios) {
        this.usersData = data.usuarios;
        this.renderTable(data.usuarios);
        this.renderStats(data.stats);
      }
    } catch (e) {
      console.error('Error refreshing data:', e);
    }
  },

  renderStats(stats) {
    if (!stats) return;
    const map = { total: stats.total, activos: stats.activos, inactivos: stats.inactivos };
    let equiposCount = 0;
    this.usersData.forEach(function(u) { equiposCount += (u.equipos_count || 0); });
    map.equipos = equiposCount;

    Object.keys(map).forEach(function(key) {
      const el = document.querySelector('[data-stat="' + key + '"]');
      if (el) {
        el.textContent = map[key] || 0;
        this.animateCounter(el, map[key] || 0);
      }
    }.bind(this));

    const countEl = document.getElementById('user-count');
    if (countEl) countEl.textContent = this.usersData.length + ' usuarios';
  },

  renderTable(usuarios) {
    const tbody = document.getElementById('users-table-body');
    if (!usuarios || usuarios.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state"><i data-lucide="users" width="56" height="56"></i><div class="empty-state-title">No hay usuarios registrados</div><div class="empty-state-text">Comienza creando tu primer usuario haciendo clic en el bot\u00f3n "Nuevo Usuario".</div></div></td></tr>';
      if (typeof lucide !== 'undefined') lucide.createIcons();
      return;
    }

    let html = '';
    usuarios.forEach(function(user) {
      const name = (user.nombre || '') + ' ' + (user.apellidos || '');
      const initial = (user.nombre || 'U').charAt(0).toUpperCase();
      const avatar = user.location_name || 'Sin asignar';
      const statusHtml = user.activo
        ? '<span class="status-badge active">Activo</span>'
        : '<span class="status-badge inactive">Inactivo</span>';
      const ddItems = user.activo
        ? '<button type="button" class="action-dd__item action-dd__item--warning" data-action="deactivate-user" data-id="' + user.id + '"><i data-lucide="toggle-left"></i>Desactivar</button>'
        : '<button type="button" class="action-dd__item action-dd__item--success" data-action="reactivate-user" data-id="' + user.id + '"><i data-lucide="check-circle"></i>Reactivar</button>';
      ddItems += '<button type="button" class="action-dd__item action-dd__item--danger" data-action="delete-user" data-id="' + user.id + '"><i data-lucide="trash-2"></i>Eliminar permanentemente</button>';

      html += '<tr data-user-id="' + user.id + '">';
      html += '<td class="checkbox-col"><div class="checkbox-wrapper"><input type="checkbox" class="user-checkbox" value="' + user.id + '" data-action="update-selection"><div class="checkbox-custom"></div></div></td>';
      html += '<td data-label="Usuario"><div class="user-cell"><div class="user-avatar">' + initial + '</div><div class="user-info-cell"><div class="user-name-cell">' + this.escapeHtml(name) + '</div><div class="user-dni-cell">' + this.escapeHtml(user.dni || 'Sin DNI') + '</div></div></div></td>';
      html += '<td data-label="Email">' + this.escapeHtml(user.email || '-') + '</td>';
      html += '<td data-label="Ubicaci\u00f3n"><div class="location-info"><i data-lucide="map-pin"></i>' + this.escapeHtml(user.location_name || 'Sin asignar') + '</div></td>';
      html += '<td data-label="Rol"><span class="role-badge">' + this.escapeHtml(user.role_name || 'Usuario') + '</span></td>';
      html += '<td data-label="Equipos"><span class="equipment-badge"><i data-lucide="monitor"></i>' + (user.equipos_count || 0) + '</span></td>';
      html += '<td data-label="Estado">' + statusHtml + '</td>';
      html += '<td data-label="Acciones"><div class="action-cell">';
      html += '<button type="button" class="action-btn view" data-action="view-user" data-id="' + user.id + '" title="Ver detalle" aria-label="Ver detalle"><i data-lucide="eye"></i></button>';
      html += '<button type="button" class="action-btn edit" data-action="open-permissions" data-id="' + user.id + '" title="Editar permisos" aria-label="Editar permisos"><i data-lucide="pencil"></i></button>';
      html += '<div class="action-dd"><button type="button" class="action-dd__btn" data-action="toggle-dd" data-id="' + user.id + '" title="M\u00e1s acciones" aria-label="M\u00e1s acciones"><i data-lucide="more-vertical"></i></button>';
      html += '<div class="action-dd__menu" id="action-dd-' + user.id + '">' + ddItems + '</div></div>';
      html += '</div></td></tr>';
    }.bind(this));
    tbody.innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
  },

  async openUserDetail(userId) {
    const container = document.getElementById('user-detail-content');
    container.innerHTML = '<div style="text-align:center;padding:40px;"><i data-lucide="loader-2" style="width:32px;height:32px;color:var(--text-muted);animation:spin 1s linear infinite;"></i><p style="color:var(--text-muted);margin-top:12px;">Cargando...</p></div>';
    this.openModal('user-detail');
    if (typeof lucide !== 'undefined') lucide.createIcons();

    try {
      const res = await fetch(BASE_URL + 'app/api/usuarios.php?action=get&id=' + encodeURIComponent(userId));
      const data = await res.json();
      if (data.error || !data.user) {
        container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--danger);">' + this.escapeHtml(data.error || 'Usuario no encontrado') + '</div>';
        return;
      }
      const user = data.user;
      const equipos = data.equipos || [];

      let equiposHtml = '';
      if (equipos.length > 0) {
        equiposHtml = equipos.map(function(eq) {
          return '<div class="equipment-item" style="margin-bottom:8px;"><div style="display:flex;align-items:center;gap:10px;"><i data-lucide="monitor" style="width:20px;height:20px;color:var(--text-muted);flex-shrink:0;"></i><div><div class="equipment-name">' + this.escapeHtml(eq.name) + '</div><div class="equipment-code">' + this.escapeHtml((eq.patrimonial_code || eq.serial_number || 'Sin c\u00f3digo') + ' - ' + (eq.location_name || '')) + '</div></div></div><button type="button" class="btn-cancel" style="padding:6px 12px;font-size:12px;color:var(--danger);flex-shrink:0;" data-action="unassign-equipment" data-equip="' + eq.id + '" data-id="' + user.id + '">Desvincular</button></div>';
        }.bind(this)).join('');
      } else {
        equiposHtml = '<div style="color:var(--text-muted);text-align:center;padding:20px;">No tiene equipos asignados</div>';
      }

      container.innerHTML =
        '<div class="user-detail-section"><div class="user-detail-title"><i data-lucide="user"></i> Informaci\u00f3n Personal</div><div class="detail-grid">' +
        '<div class="detail-item"><span class="detail-label">Nombre completo</span><span class="detail-value">' + this.escapeHtml((user.nombre || '') + ' ' + (user.apellidos || '')) + '</span></div>' +
        '<div class="detail-item"><span class="detail-label">Email</span><span class="detail-value">' + this.escapeHtml(user.email || '-') + '</span></div>' +
        '<div class="detail-item"><span class="detail-label">DNI</span><span class="detail-value">' + this.escapeHtml(user.dni || '-') + '</span></div>' +
        '<div class="detail-item"><span class="detail-label">Tel\u00e9fono</span><span class="detail-value">' + this.escapeHtml(user.phone || '-') + '</span></div>' +
        '<div class="detail-item"><span class="detail-label">Estado</span><span class="detail-value">' + (user.activo ? '<span class="status-badge active">Activo</span>' : '<span class="status-badge inactive">Inactivo</span>') + '</span></div>' +
        '<div class="detail-item"><span class="detail-label">\u00daltimo acceso</span><span class="detail-value">' + this.escapeHtml(user.ultimo_acceso || 'Nunca') + '</span></div></div></div>' +

        '<div class="user-detail-section"><div class="user-detail-title"><i data-lucide="map-pin"></i> Ubicaci\u00f3n</div><div class="detail-grid">' +
        '<div class="detail-item"><span class="detail-label">Ubicaci\u00f3n actual</span><span class="detail-value">' + this.escapeHtml(user.location_name || 'Sin asignar') + '</span></div>' +
        '<div class="detail-item"><span class="detail-label">Cargo</span><span class="detail-value">' + this.escapeHtml(user.position_name || '-') + '</span></div></div>' +
        '<div style="margin-top:16px;"><label class="form-label">Cambiar ubicaci\u00f3n:</label><select class="form-select" id="new-location" style="margin-top:6px;"><option value="">Seleccionar...</option>' +
        (function() {
          let opts = '';
          try {
            const locs = <?= json_encode($locationsData, JSON_HEX_TAG) ?>;
            locs.forEach(function(loc) {
              const selected = (user.location_id == loc.id) ? 'selected' : '';
              opts += '<option value="' + loc.id + '" ' + selected + '>' + this.escapeHtml(loc.name) + '</option>';
            }.bind(this));
          } catch(e) {}
          return opts;
        }.bind(this)()) +
        '</select><button type="button" class="btn-submit" style="margin-top:10px;padding:10px 20px;" data-action="update-location" data-id="' + user.id + '">Actualizar</button></div></div>' +

        '<div class="user-detail-section"><div class="user-detail-title"><i data-lucide="monitor"></i> Equipos Asignados (' + equipos.length + ')</div>' + equiposHtml + '</div>';

      if (typeof lucide !== 'undefined') lucide.createIcons();

      document.querySelector('[data-action="update-location"]')?.addEventListener('click', function() {
        this.updateUserLocation(user.id);
      }.bind(this));

      document.querySelectorAll('[data-action="unassign-equipment"]').forEach(function(btn) {
        btn.addEventListener('click', function() {
          this.unassignEquipment(btn.getAttribute('data-equip'), btn.getAttribute('data-id'));
        }.bind(this));
      }.bind(this));

    } catch (e) {
      container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--danger);">Error al cargar datos del usuario.</div>';
    }
  },

  async updateUserLocation(userId) {
    const locationId = document.getElementById('new-location')?.value;
    if (!locationId) {
      this.showToast('warning', 'Seleccionar ubicaci\u00f3n', 'Por favor seleccione una ubicaci\u00f3n.');
      return;
    }
    try {
      const res = await fetch(BASE_URL + 'app/api/usuarios.php?action=update-location', {
        method: 'POST',
        body: new URLSearchParams({ user_id: userId, location_id: locationId })
      });
      const result = await res.json();
      if (result.success) {
        this.showToast('success', 'Ubicaci\u00f3n actualizada', 'La ubicaci\u00f3n del usuario ha sido actualizada.');
        this.openUserDetail(userId);
        await this.refreshAll();
      } else {
        this.showToast('error', 'Error', result.error || 'No se pudo actualizar.');
      }
    } catch (e) {
      this.showToast('error', 'Error', 'Error de conexi\u00f3n.');
    }
  },

  async unassignEquipment(equipmentId, userId) {
    if (!confirm('\u00bfDesvincular este equipo?')) return;
    try {
      const res = await fetch(BASE_URL + 'app/api/usuarios.php?action=unassign-equipment', {
        method: 'POST',
        body: new URLSearchParams({ equipment_id: equipmentId })
      });
      const result = await res.json();
      if (result.success) {
        this.showToast('success', 'Equipo desvinculado', 'El equipo ha sido desvinculado del usuario.');
        this.openUserDetail(userId);
        await this.refreshAll();
      } else {
        this.showToast('error', 'Error', result.error || 'No se pudo desvincular.');
      }
    } catch (e) {
      this.showToast('error', 'Error', 'Error de conexi\u00f3n.');
    }
  },

  async openPermissionsModal(userId) {
    this.currentPermissionsUserId = userId;
    const body = document.getElementById('permissions-body');
    body.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);">Cargando permisos...</div>';
    this.openModal('edit-permissions');

    const PERMISSIONS_USER = [
      { key: 'dashboard:view', label: 'Ver Principal (Dashboard)' },
      { key: 'dashboard:tickets_recientes', label: 'Ver Tickets Recientes' },
      { key: 'dashboard:mis_equipos', label: 'Ver Mis Equipos' },
      { key: 'tickets:create', label: 'Reportar accidentes (Nuevo ticket)' },
      { key: 'tickets:own', label: 'Ver mis tickets' },
      { key: 'tickets:view', label: 'Ver todos los tickets' },
      { key: 'tickets:edit', label: 'Editar tickets' },
      { key: 'tickets:delete', label: 'Eliminar tickets' },
      { key: 'notifications:view', label: 'Ver notificaciones' },
      { key: 'notifications:send', label: 'Enviar notificaciones' },
      { key: 'profile:view', label: 'Ver mi perfil' }
    ];

    const PERMISSIONS_ADMIN = [
      { key: 'equipos:view', label: 'Ver inventario de equipos' },
      { key: 'equipos:assign', label: 'Asignar equipos' },
      { key: 'users:view', label: 'Ver control de usuarios' },
      { key: 'users:manage', label: 'Gestionar usuarios' },
      { key: 'structure:edit', label: 'Modificar estructura org\u00e1nica' }
    ];

    try {
      const res = await fetch(BASE_URL + 'app/api/usuarios.php?action=get-permissions&id=' + encodeURIComponent(userId));
      const data = await res.json();
      const userPerms = data.permissions || [];
      
      let html = '';
      
      const renderGroup = (title, icon, perms) => {
        let groupHtml = '<div class="permissions-section"><div class="permissions-section-title"><i data-lucide="' + icon + '"></i> ' + title + '</div><div class="permissions-grid">';
        perms.forEach(function(p) {
          const checked = userPerms.includes(p.key) ? 'checked' : '';
          groupHtml += '<label class="permission-item"><input type="checkbox" name="perm" value="' + p.key + '" ' + checked + '><span class="permission-label">' + p.label + '</span></label>';
        });
        groupHtml += '</div></div>';
        return groupHtml;
      };

      html += renderGroup('Permisos de Usuario', 'user', PERMISSIONS_USER);
      html += renderGroup('Permisos de Administrador', 'shield', PERMISSIONS_ADMIN);

      body.innerHTML = html;
      if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (e) {
      body.innerHTML = '<div style="color:var(--danger);">Error cargando permisos</div>';
    }
  },

  async savePermissions() {
    if (!this.currentPermissionsUserId) return;
    const checked = Array.from(document.querySelectorAll('input[name="perm"]:checked')).map(function(cb) { return cb.value; });
    const btn = document.getElementById('save-permissions-btn');
    btn.disabled = true;
    try {
      const res = await fetch(BASE_URL + 'app/api/usuarios.php?action=update-permissions', {
        method: 'POST',
        body: new URLSearchParams({ user_id: this.currentPermissionsUserId, permissions: JSON.stringify(checked) })
      });
      const data = await res.json();
      if (data.success) {
        this.showToast('success', 'Permisos guardados', 'Los permisos han sido actualizados.');
        this.closeModal('edit-permissions');
      } else {
        this.showToast('error', 'Error', data.error || 'No se pudieron guardar.');
      }
    } catch (e) {
      this.showToast('error', 'Error', 'Error de conexi\u00f3n.');
    } finally {
      btn.disabled = false;
    }
  },

  openDeactivateModal(userId) {
    document.getElementById('deactivate-user-id').value = userId;
    document.getElementById('deactivate-user-reason').value = '';
    this.openModal('deactivate-user');
  },

  async confirmDeactivate() {
    const userId = document.getElementById('deactivate-user-id').value;
    const reason = document.getElementById('deactivate-user-reason').value;
    if (!reason) {
      this.showToast('warning', 'Campo requerido', 'Ingrese el motivo de desactivaci\u00f3n.');
      return;
    }
    const btn = document.querySelector('[data-action="confirm-deactivate"]');
    btn.classList.add('loading');
    try {
      const res = await fetch(BASE_URL + 'app/api/usuarios.php?action=delete', {
        method: 'POST',
        body: new URLSearchParams({ id: userId })
      });
      const data = await res.json();
      if (data.success) {
        this.showToast('success', 'Usuario desactivado', 'El usuario ha sido desactivado.');
        this.closeModal('deactivate-user');
        await this.refreshAll();
      } else {
        this.showToast('error', 'Error', data.error || 'No se pudo desactivar.');
      }
    } catch (e) {
      this.showToast('error', 'Error', 'Error de conexi\u00f3n.');
    } finally {
      btn.classList.remove('loading');
    }
  },

  async reactivateUser(userId) {
    try {
      const res = await fetch(BASE_URL + 'app/api/usuarios.php?action=reactivate', {
        method: 'POST',
        body: new URLSearchParams({ id: userId })
      });
      const data = await res.json();
      if (data.success) {
        this.showToast('success', 'Usuario reactivado', 'El usuario ha sido reactivado.');
        await this.refreshAll();
      } else {
        this.showToast('error', 'Error', data.error || 'No se pudo reactivar.');
      }
    } catch (e) {
      this.showToast('error', 'Error', 'Error de conexi\u00f3n.');
    }
  },

  openDeleteModal(userId) {
    document.getElementById('delete-user-id').value = userId;
    document.getElementById('delete-user-reason').value = '';
    this.openModal('delete-user');
  },

  async confirmDeletePermanent() {
    const userId = document.getElementById('delete-user-id').value;
    const reason = document.getElementById('delete-user-reason').value;
    if (!reason) {
      this.showToast('warning', 'Campo requerido', 'Ingrese el motivo de eliminaci\u00f3n.');
      return;
    }
    const btn = document.querySelector('[data-action="confirm-delete"]');
    btn.classList.add('loading');
    try {
      const res = await fetch(BASE_URL + 'app/api/usuarios.php?action=delete-permanent', {
        method: 'POST',
        body: new URLSearchParams({ id: userId })
      });
      const data = await res.json();
      if (data.success) {
        this.showToast('success', 'Usuario eliminado', 'El usuario ha sido eliminado permanentemente.');
        this.closeModal('delete-user');
        await this.refreshAll();
      } else {
        this.showToast('error', 'Error', data.error || 'No se pudo eliminar.');
      }
    } catch (e) {
      this.showToast('error', 'Error', 'Error de conexi\u00f3n.');
    } finally {
      btn.classList.remove('loading');
    }
  },

  renderSkeleton(container) {
    container.innerHTML = '<div style="text-align:center;padding:40px;"><i data-lucide="loader-2" style="width:32px;height:32px;color:var(--text-muted);animation:spin 1s linear infinite;"></i><p style="color:var(--text-muted);margin-top:12px;">Cargando...</p></div>';
  },

  exportTableToCSV(selector, filename) {
    const table = document.querySelector(selector);
    if (!table) return;
    const rows = table.querySelectorAll('tr');
    const csv = [];
    rows.forEach(function(row) {
      const cells = row.querySelectorAll('th, td');
      const line = Array.from(cells).map(function(c) {
        let text = c.textContent.trim().replace(/"/g, '""');
        if (text.includes(',') || text.includes('"') || text.includes('\n')) text = '"' + text + '"';
        return text;
      }).join(',');
      csv.push(line);
    });
    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
  }
};

document.addEventListener('DOMContentLoaded', function() {
  UsuariosApp.init();
});

})();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
