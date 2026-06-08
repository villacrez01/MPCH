<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;

$isOtiAdmin = \App\Services\AuthService::isAdmin();

require_once __DIR__ . '/../../Models/Location.php';
$statsData = \App\Models\Location::getStats();

$tituloPagina = 'Estructura Orgánica - Sistema OTI';
$paginaActual = 'admin-estructura';
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<style>
:root {
  --c-bg: #f1f5f9;
  --c-surface: #ffffff;
  --c-surface-hover: #f8fafc;
  --c-border: #e2e8f0;
  --c-border-hover: #cbd5e1;
  --c-text: #0f172a;
  --c-text-muted: #64748b;
  --c-primary: #1e3f5f;
  --c-primary-light: rgba(30, 63, 95, 0.08);
  --c-primary-hover: #0f2638;
  --c-success: #10b981;
  --c-warning: #f59e0b;
  --c-danger: #ef4444;
  --c-info: #0ea5e9;
  --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
  --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
  --radius-md: 8px;
  --radius-lg: 12px;
  --radius-xl: 16px;
  --radius-pill: 999px;
  --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
* { box-sizing: border-box; }

/* ─── Page Header ─── */
.page-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 24px; gap: 16px;
}
.page-title-group h1 {
  margin: 0 0 4px; font-size: 26px; font-weight: 800;
  letter-spacing: -0.02em;
  background: linear-gradient(135deg, #0f172a 0%, #4f46e5 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.page-title-group p { margin: 0; font-size: 14px; color: var(--c-text-muted); }

/* ─── Stats Grid ─── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px; margin-bottom: 24px;
}
.stat-card {
  display: flex; align-items: center; gap: 14px;
  padding: 18px 20px;
  background: var(--c-surface);
  border-radius: var(--radius-xl);
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-sm);
  transition: var(--transition);
}
.stat-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-lg);
  border-color: var(--c-border-hover);
}
.stat-icon {
  width: 44px; height: 44px;
  border-radius: var(--radius-lg);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.stat-card.primary .stat-icon { background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; }
.stat-card.success .stat-icon { background: linear-gradient(135deg, #34d399, #10b981); color: white; }
.stat-card.warning .stat-icon { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.stat-card.info .stat-icon { background: linear-gradient(135deg, #38bdf8, #0ea5e9); color: white; }
.stat-content { flex: 1; }
.stat-value { font-size: 24px; font-weight: 800; line-height: 1.2; }
.stat-label { font-size: 12px; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }

/* ─── Filter Bar ─── */
.filter-bar {
  display: flex; gap: 10px; margin-bottom: 0; flex-wrap: wrap;
  align-items: center;
}
.filter-bar .form-input,
.filter-bar .form-select {
  padding: 9px 12px; border: 1px solid var(--c-border);
  border-radius: var(--radius-md); font-size: 13px;
  background: var(--c-surface); color: var(--c-text);
  transition: var(--transition);
}
.filter-bar .form-input:focus,
.filter-bar .form-select:focus {
  outline: none; border-color: var(--c-primary);
  box-shadow: 0 0 0 3px var(--c-primary-light);
}
.filter-bar .form-input { flex: 1; min-width: 180px; }

/* ─── Advanced Filter Toggle ─── */
.advanced-toggle {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 14px; border: 1px solid var(--c-border);
  border-radius: var(--radius-md); background: var(--c-surface);
  color: var(--c-text-muted); font-size: 13px; font-weight: 500;
  cursor: pointer; transition: var(--transition); white-space: nowrap;
}
.advanced-toggle:hover {
  border-color: var(--c-primary); color: var(--c-primary);
  background: var(--c-primary-light);
}
.advanced-toggle.active {
  border-color: var(--c-primary); color: var(--c-primary);
  background: var(--c-primary-light);
}
.advanced-panel {
  overflow: hidden; max-height: 0; opacity: 0;
  transition: max-height 0.35s ease, opacity 0.3s ease, margin 0.3s ease;
  margin-bottom: 0;
}
.advanced-panel.open {
  max-height: 160px; opacity: 1; margin-bottom: 20px;
}
.advanced-panel-inner {
  display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
  padding: 16px 20px; margin-top: 12px;
  background: var(--c-surface); border: 1px solid var(--c-border);
  border-radius: var(--radius-xl); box-shadow: var(--shadow-sm);
}
.advanced-panel-inner .filter-group {
  display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 160px;
}
.advanced-panel-inner .filter-group label {
  font-size: 11px; font-weight: 600; color: var(--c-text-muted);
  text-transform: uppercase; letter-spacing: 0.04em;
}
.advanced-panel-inner .form-select {
  padding: 9px 12px; border: 1px solid var(--c-border);
  border-radius: var(--radius-md); font-size: 13px;
  background: var(--c-surface); color: var(--c-text);
  transition: var(--transition);
}
.advanced-panel-inner .form-select:focus {
  outline: none; border-color: var(--c-primary);
  box-shadow: 0 0 0 3px var(--c-primary-light);
}

/* ─── Buttons ─── */
.btn { border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer;
  font-size: 13px; transition: var(--transition); display: inline-flex;
  align-items: center; gap: 6px; padding: 9px 16px; white-space: nowrap; }
.btn-primary {
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: white; box-shadow: 0 3px 10px rgba(79,70,229,0.2);
}
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(79,70,229,0.3); }
.btn-secondary {
  background: white; color: var(--c-text);
  border: 1px solid var(--c-border);
}
.btn-secondary:hover { background: var(--c-surface-hover); border-color: var(--c-border-hover); }
.btn-danger {
  background: white; color: var(--c-danger);
  border: 1px solid #fecaca;
}
.btn-danger:hover { background: #fef2f2; border-color: var(--c-danger); }
.btn-sm { padding: 6px 10px; font-size: 12px; gap: 4px; }

/* ─── Layout ─── */
.org-layout {
  display: flex; gap: 20px; align-items: flex-start;
}
.tree-panel {
  flex: 0 0 380px; width: 380px;
  background: var(--c-surface);
  border-radius: var(--radius-xl);
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-sm);
  height: calc(100vh - 310px);
  overflow-y: auto;
  padding: 20px;
}
.detail-panel {
  flex: 1; min-width: 0;
  background: var(--c-surface);
  border-radius: var(--radius-xl);
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-md);
  height: calc(100vh - 310px);
  overflow-y: auto;
  position: relative;
}

/* ─── Tree ─── */
.org-tree { list-style: none; padding: 0; margin: 0; }
.org-tree ul {
  list-style: none; padding-left: 24px; margin: 4px 0;
  position: relative;
}
.org-tree ul::before {
  content: ''; position: absolute; left: 12px;
  top: -6px; bottom: 6px; width: 2px;
  background: var(--c-border); border-radius: 2px;
}
.tree-node {
  display: flex; align-items: flex-start; gap: 8px;
  padding: 8px 10px; border-radius: var(--radius-md);
  cursor: pointer; transition: var(--transition);
  margin-bottom: 2px; border: 1px solid transparent;
  position: relative;
}
.tree-node:hover {
  background: var(--c-surface-hover);
  border-color: var(--c-border);
}
.tree-node.selected {
  background: var(--c-primary-light);
  border-color: #a5b4fc;
}
.tree-toggle {
  width: 20px; height: 20px; display: flex; align-items: center;
  justify-content: center; background: transparent; border: none;
  cursor: pointer; color: var(--c-text-muted);
  transition: transform 0.2s; padding: 0; flex-shrink: 0; margin-top: 2px;
}
.tree-toggle::after {
  content: ""; display: block;
  width: 5px; height: 5px;
  border-right: 2px solid currentColor;
  border-bottom: 2px solid currentColor;
  transform: rotate(-45deg);
}
.tree-toggle.open { transform: rotate(45deg); }
.tree-node-icon {
  width: 32px; height: 32px; border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-size: 14px;
  transition: var(--transition);
}
.tree-node-icon.sede { background: #e0e7ff; color: #4338ca; }
.tree-node-icon.direccion { background: #dbeafe; color: #2563eb; }
.tree-node-icon.area { background: #fce7f3; color: #db2777; }
.tree-node-icon.subarea { background: #fef3c7; color: #d97706; }
.tree-node-icon.sucursal { background: #f0fdf4; color: #16a34a; }
.tree-node-icon.piso { background: #d1fae5; color: #059669; }
.tree-node-icon.oficina { background: #ede9fe; color: #7c3aed; }
.tree-node-icon.default { background: #f1f5f9; color: #64748b; }
.tree-node-body { flex: 1; min-width: 0; }
.tree-node-name {
  font-weight: 600; font-size: 13px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;
}
.tree-node-meta { font-size: 11px; color: var(--c-text-muted); margin-top: 1px; }
.tree-node-actions {
  display: flex; gap: 2px; flex-shrink: 0; opacity: 0;
  transition: var(--transition);
}
.tree-node:hover .tree-node-actions { opacity: 1; }
.tree-node-action {
  width: 26px; height: 26px; border: none; border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: 12px; background: transparent;
  transition: var(--transition); color: var(--c-text-muted);
}
.tree-node-action:hover { background: var(--c-border); }
.tree-node-action.assign:hover { background: var(--c-primary-light); color: var(--c-primary); }
.tree-node-action.delete:hover { background: #fef2f2; color: var(--c-danger); }
.tree-children { display: none; }
.tree-children.open { display: block; }

/* ─── Detail Panel ─── */
.detail-empty {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; height: 100%; gap: 12px;
  color: var(--c-text-muted); padding: 40px;
}
.detail-cover {
  background: linear-gradient(135deg, #f8fafc, #e2e8f0);
  padding: 28px 28px 20px; border-bottom: 1px solid var(--c-border);
  position: relative;
}
.detail-cover::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.7), transparent 60%);
  pointer-events: none;
}
.detail-badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 10px; border-radius: var(--radius-pill);
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.05em; margin-bottom: 8px; position: relative;
}
.detail-badge.sede { background: #4f46e5; color: white; }
.detail-badge.direccion { background: #0ea5e9; color: white; }
.detail-badge.area { background: #e11d48; color: white; }
.detail-badge.subarea { background: #ea580c; color: white; }
.detail-badge.piso { background: #10b981; color: white; }
.detail-badge.oficina { background: #8b5cf6; color: white; }
.detail-badge.default { background: #64748b; color: white; }
.detail-name { margin: 0; font-size: 24px; font-weight: 800; position: relative; }
.detail-breadcrumb {
  display: flex; align-items: center; gap: 4px; flex-wrap: wrap;
  margin-top: 10px; font-size: 12px; color: var(--c-text-muted); position: relative;
}
.detail-breadcrumb a {
  color: var(--c-primary); text-decoration: none;
}
.detail-breadcrumb a:hover { text-decoration: underline; }
.detail-body { padding: 24px 28px; }
.detail-section { margin-bottom: 20px; }
.detail-section-header {
  display: flex; align-items: center; gap: 6px;
  margin-bottom: 12px; padding-bottom: 8px;
  border-bottom: 1px solid var(--c-border);
  font-size: 14px; font-weight: 700; color: var(--c-text);
}
.detail-users, .detail-equipment {
  display: grid; gap: 8px;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
}
.detail-item {
  padding: 10px 14px; border: 1px solid var(--c-border);
  border-radius: var(--radius-md);
  display: flex; flex-direction: column; gap: 2px;
  transition: var(--transition);
}
.detail-item:hover {
  border-color: var(--c-primary-light);
  box-shadow: var(--shadow-sm);
}
.detail-item strong { font-size: 13px; }
.detail-item .meta { font-size: 11px; color: var(--c-text-muted); }

/* ─── Info cards (assign modal) ─── */
.info-card {
  background: var(--c-surface-hover);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-lg);
  padding: 14px 16px;
  margin-top: 10px;
  animation: fadeSlideIn 0.2s ease;
}
@keyframes fadeSlideIn {
  from { opacity:0; transform:translateY(-4px); }
  to   { opacity:1; transform:translateY(0); }
}
.info-card .row {
  display: flex; align-items: center; gap: 10px;
  flex-wrap: wrap;
}
.info-card .row + .row { margin-top: 8px; }
.info-badge {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 12px; color: var(--c-text-muted);
  background: var(--c-surface);
  padding: 3px 10px; border-radius: 999px;
  border: 1px solid var(--c-border);
}
.info-badge strong { color: var(--c-text); font-weight: 600; }
.section-label {
  font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;
  color: var(--c-text-muted); font-weight: 600; margin-bottom: 4px;
}
.path-breadcrumb {
  display: flex; align-items: center; gap: 4px;
  flex-wrap: wrap; font-size: 13px;
}
.path-breadcrumb .sep { color: var(--c-text-muted); font-size: 11px; }
.path-breadcrumb .item {
  background: var(--c-surface); padding: 2px 8px;
  border-radius: 6px; border: 1px solid var(--c-border);
  font-size: 12px; font-weight: 500;
}
.assign-detail-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
  margin-top: 10px;
}
.assign-detail-grid .cell {
  display: flex; align-items: center; gap: 6px;
  font-size: 12px; color: var(--c-text-muted);
}
.assign-detail-grid .cell strong { color: var(--c-text); font-weight: 600; }
.manager-chip {
  display: inline-flex; align-items: center; gap: 5px;
  background: linear-gradient(135deg, #eef2ff, #e0e7ff);
  color: #4338ca; padding: 4px 12px; border-radius: 999px;
  font-size: 12px; font-weight: 500;
}

/* ─── Modals ─── */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,0.45);
  backdrop-filter: blur(6px);
  display: none; align-items: center; justify-content: center;
  z-index: 1000;
}
.modal-overlay.active { display: flex; }
.modal {
  background: var(--c-surface);
  border-radius: var(--radius-xl);
  width: 100%; max-width: 520px; max-height: 90vh;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
  display: flex; flex-direction: column;
}
.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 24px; border-bottom: 1px solid var(--c-border);
  background: var(--c-surface-hover); flex-shrink: 0;
}
.modal-title {
  font-size: 17px; font-weight: 700; margin: 0;
  display: flex; align-items: center; gap: 10px;
}
.modal-close {
  background: transparent; border: none; color: var(--c-text-muted);
  cursor: pointer; width: 32px; height: 32px;
  border-radius: var(--radius-pill);
  display: flex; align-items: center; justify-content: center;
  transition: var(--transition);
}
.modal-close:hover { background: var(--c-border); color: var(--c-text); }
.modal-body { padding: 20px 24px; overflow-y: auto; flex: 1; }
.modal-footer {
  display: flex; gap: 10px; justify-content: flex-end;
  padding: 14px 24px; border-top: 1px solid var(--c-border);
  background: var(--c-surface-hover); flex-shrink: 0;
}
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; }
.form-input, .form-select, .form-textarea {
  width: 100%; padding: 10px 14px;
  border: 1px solid var(--c-border); border-radius: var(--radius-md);
  font-size: 13px; background: var(--c-surface); color: var(--c-text);
  transition: var(--transition);
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
  outline: none; border-color: var(--c-primary);
  box-shadow: 0 0 0 3px var(--c-primary-light);
}
.form-textarea { min-height: 80px; resize: vertical; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.required { color: var(--c-danger); margin-left: 1px; }
.form-group.locked .form-label::after { content: ' 🔒'; font-size: 11px; }

/* ─── Toast ─── */
#toast-container {
  position: fixed; top: 20px; right: 20px; z-index: 9999;
  display: flex; flex-direction: column; gap: 8px;
}
.toast {
  padding: 14px 18px; background: white; border-radius: var(--radius-lg);
  box-shadow: 0 6px 20px rgba(0,0,0,0.12);
  border-left: 4px solid var(--c-primary);
  display: flex; align-items: flex-start; gap: 10px;
  max-width: 400px; animation: toastIn 0.25s ease-out;
}
.toast.toast-success { border-left-color: var(--c-success); }
.toast.toast-error { border-left-color: var(--c-danger); }
.toast.toast-warning { border-left-color: var(--c-warning); }
@keyframes toastIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
.spinner { width: 32px; height: 32px; border-radius: 50%; border: 3px solid var(--c-border); border-top-color: var(--c-primary); animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 1024px) {
  .org-layout { flex-direction: column; }
  .tree-panel { width: 100%; flex: none; height: 350px; }
  .detail-panel { height: auto; min-height: 350px; }
  .form-row { grid-template-columns: 1fr; }
}
</style>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<div id="toast-container"></div>

<main id="main-content" class="main-content">
  <div class="page-header">
    <div class="page-title-group">
      <h1><i data-lucide="building-2" width="24" height="24" style="display:inline;vertical-align:middle;margin-right:8px;"></i>Estructura Orgánica</h1>
      <p>Ubicaciones y áreas de la municipalidad</p>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-grid" id="stats-grid">
    <div class="stat-card primary">
      <div class="stat-icon"><i data-lucide="layers" width="22" height="22"></i></div>
      <div class="stat-content">
        <div class="stat-value" id="stat-total"><?= $statsData['total'] ?? 0 ?></div>
        <div class="stat-label">Total Ubicaciones</div>
      </div>
    </div>
    <div class="stat-card success">
      <div class="stat-icon"><i data-lucide="building" width="22" height="22"></i></div>
      <div class="stat-content">
        <div class="stat-value" id="stat-sedes"><?= $statsData['direcciones'] ?? 0 ?></div>
        <div class="stat-label">Direcciones / Sedes</div>
      </div>
    </div>
    <div class="stat-card warning">
      <div class="stat-icon"><i data-lucide="folder-tree" width="22" height="22"></i></div>
      <div class="stat-content">
        <div class="stat-value" id="stat-areas"><?= $statsData['areas'] ?? 0 ?></div>
        <div class="stat-label">Áreas / Sub-áreas</div>
      </div>
    </div>
    <div class="stat-card info">
      <div class="stat-icon"><i data-lucide="door-open" width="22" height="22"></i></div>
      <div class="stat-content">
        <div class="stat-value" id="stat-oficinas"><?= $statsData['oficinas'] ?? 0 ?></div>
        <div class="stat-label">Oficinas / Pisos</div>
      </div>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="filter-bar">
    <input type="text" id="filter-search" class="form-input" placeholder="Buscar ubicación...">
    <button class="advanced-toggle" id="advanced-toggle-btn" onclick="toggleAdvancedFilters()">
      <i data-lucide="sliders-horizontal" width="14" height="14"></i>
      <span>Filtros Avanzados</span>
      <i data-lucide="chevron-down" width="14" height="14" id="advanced-chevron"></i>
    </button>
    <button class="btn btn-primary" onclick="showCreateModalFromSelected()"><i data-lucide="plus" width="14" height="14"></i>Nuevo</button>
    <?php if ($isOtiAdmin): ?>
    <button class="btn btn-secondary" onclick="showAssignModal()"><i data-lucide="user-plus" width="14" height="14"></i>Asignar</button>
    <?php endif; ?>
  </div>

  <!-- Advanced Filters Panel -->
  <div class="advanced-panel" id="advanced-panel">
    <div class="advanced-panel-inner">
      <div class="filter-group">
        <label><i data-lucide="building-2" width="12" height="12"></i> Sede</label>
        <select id="filter-sede" class="form-select">
          <option value="">Todas las sedes</option>
        </select>
      </div>
      <div class="filter-group">
        <label><i data-lucide="layers" width="12" height="12"></i> Piso</label>
        <select id="filter-piso" class="form-select">
          <option value="">Todos los pisos</option>
        </select>
      </div>
      <div class="filter-group">
        <label><i data-lucide="folder" width="12" height="12"></i> Área</label>
        <select id="filter-area" class="form-select">
          <option value="">Todas las áreas</option>
        </select>
      </div>
    </div>
  </div>

  <!-- Layout -->
  <div class="org-layout">
    <div class="tree-panel" id="tree-container">
      <div id="tree-loading" style="text-align:center;padding:40px;"><div class="spinner" style="margin:0 auto 12px;"></div><span style="color:var(--c-text-muted);font-size:13px;">Cargando estructura...</span></div>
      <ul class="org-tree" id="org-tree" style="display:none;"></ul>
    </div>
    <div class="detail-panel" id="detail-panel">
      <div class="detail-empty" id="detail-empty">
        <i data-lucide="mouse-pointer-2" width="40" height="40" style="opacity:0.4;"></i>
        <span>Seleccione una ubicación para ver sus detalles</span>
      </div>
      <div id="detail-content" style="display:none;"></div>
    </div>
  </div>

  <!-- Usuarios sin asignar -->
  <div class="card" id="unassigned-card" style="margin-top: 24px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
      <h2 class="card-title" style="display:flex;align-items:center;gap:8px;">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
        Usuarios Sin Asignar
        <span class="unassigned-count" id="unassigned-count" style="font-size:13px;font-weight:400;color:var(--c-text-muted);">(0)</span>
      </h2>
      <input type="text" id="unassigned-search" class="form-input" placeholder="Buscar usuario..." style="max-width:260px;font-size:13px;padding:6px 10px;">
    </div>
    <div class="card-body">
      <div id="unassigned-list" style="max-height:300px;overflow-y:auto;">
        <div style="text-align:center;padding:20px;color:var(--c-text-muted);font-size:13px;">Cargando...</div>
      </div>
    </div>
  </div>
</main>

<!-- Create Modal -->
<div class="modal-overlay" id="create-modal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i data-lucide="plus-circle" width="20" height="20" style="color:var(--c-primary);"></i>Nueva Ubicación</h3>
      <button class="modal-close" onclick="closeModal('create-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <form id="create-form" onsubmit="event.preventDefault();createLocation();">
        <div class="form-group">
          <label class="form-label">Nombre <span class="required">*</span></label>
          <input type="text" name="name" class="form-input" required placeholder="Ej: Dirección de Sistemas">
        </div>
        <div class="form-row">
          <div class="form-group" id="create-type-group">
            <label class="form-label">Tipo <span class="required">*</span></label>
            <select name="type" class="form-select" required id="create-type">
              <option value="SEDE">Sede</option>
              <option value="SUCURSAL">Sucursal</option>
              <option value="DIRECCION">Dirección</option>
              <option value="AREA">Área</option>
              <option value="SUBAREA">Sub-área</option>
              <option value="PISO">Piso</option>
              <option value="OFICINA">Oficina</option>
            </select>
          </div>
          <div class="form-group" id="create-parent-group">
            <label class="form-label">Padre</label>
            <select name="parent_id" class="form-select" id="create-parent">
              <option value="">— Raíz —</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Piso</label>
            <input type="text" name="floor" class="form-input" placeholder="Ej: 1er piso">
          </div>
          <div class="form-group">
            <label class="form-label">Edificio</label>
            <input type="text" name="building" class="form-input" placeholder="Ej: Edificio Central">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Descripción</label>
          <textarea name="description" class="form-textarea" placeholder="Descripción opcional..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('create-modal')">Cancelar</button>
      <button class="btn btn-primary" onclick="createLocation()"><i data-lucide="check" width="14" height="14"></i>Crear</button>
    </div>
  </div>
</div>

<!-- Assign Modal -->
<div class="modal-overlay" id="assign-modal">
  <div class="modal" style="max-width:600px;">
    <div class="modal-header">
      <h3 class="modal-title"><i data-lucide="user-plus" width="20" height="20" style="color:var(--c-primary);"></i>Asignar Usuario</h3>
      <button class="modal-close" onclick="closeModal('assign-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Usuario <span class="required">*</span></label>
        <select id="assign-user" class="form-select" required>
          <option value="">— Seleccione —</option>
        </select>
        <div id="assign-user-info" style="display:none;"></div>
      </div>
      <hr style="border:none;border-top:1px solid var(--c-border);margin:14px 0;">
      <div class="section-label">Ubicación destino</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Sede <span class="required">*</span></label>
          <select id="assign-sede" class="form-select">
            <option value="">— Seleccione —</option>
          </select>
        </div>
        <div class="form-group" id="assign-piso-group" style="display:none;">
          <label class="form-label">Piso</label>
          <select id="assign-piso" class="form-select">
            <option value="">— Seleccione —</option>
          </select>
        </div>
      </div>
      <div class="form-group" id="assign-area-group" style="display:none;">
        <label class="form-label">Área / Dirección</label>
        <select id="assign-area" class="form-select">
          <option value="">— Seleccione —</option>
        </select>
      </div>
      <div id="assign-location-info" style="display:none;"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('assign-modal')">Cancelar</button>
      <button class="btn btn-primary" onclick="assignUser()"><i data-lucide="check" width="14" height="14"></i>Asignar</button>
    </div>
  </div>
</div>

<script>
const BASE_URL = window.location.origin + '/OTI/';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
let treeData = [];

// ─── Toast ───
function showToast(type, title, msg) {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.innerHTML = '<div style="flex:1;"><strong style="display:block;font-size:13px;">' + esc(title) + '</strong><span style="font-size:12px;color:#64748b;">' + esc(msg||'') + '</span></div><button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:0;line-height:1;">&times;</button>';
  c.appendChild(t);
  setTimeout(() => { if (t.parentElement) t.remove(); }, 5000);
}
function esc(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

// ─── Modal helpers ───
function showModal(id) { document.getElementById(id).classList.add('active'); if (typeof lucide !== 'undefined') lucide.createIcons(); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active');
});

// ─── API request with CSRF ───
function api(url, data, method) {
  method = method || (data ? 'POST' : 'GET');
  const opts = { method, headers: {} };
  if (data) {
    data._token = CSRF_TOKEN;
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(data);
  }
  return fetch(url, opts).then(r => r.json());
}

// ─── Load & render tree ───
function loadTree() {
  document.getElementById('tree-loading').style.display = 'block';
  document.getElementById('org-tree').style.display = 'none';
  api(BASE_URL + 'app/api/locations.php?action=get-tree').then(data => {
    treeData = data.tree || [];
    renderTree(treeData);
    populateFilters(treeData);
  }).catch(() => {
    document.getElementById('tree-loading').innerHTML = '<span style="color:var(--c-danger);">Error al cargar</span>';
  });
}

function renderTree(nodes, parentUl) {
  const ul = parentUl || document.getElementById('org-tree');
  ul.innerHTML = '';
  nodes.forEach(n => {
    const li = document.createElement('li');
    const hasCh = n.children && n.children.length > 0;
    const type = (n.type || '').toLowerCase();
    const showManager = type === 'area' || type === 'oficina';
    const showAssign = type === 'area' || type === 'piso';
    const showDelete = type === 'sede' || type === 'area' || type === 'piso';
    const manager = showManager ? ((n.manager_name || '') + ' ' + (n.manager_lastname || '')).trim() : '';
    let actionsHtml = '';
    if (showAssign || showDelete) {
      actionsHtml = '<div class="tree-node-actions">';
      if (showAssign) {
        actionsHtml +=
          '<button class="tree-node-action assign" title="Asignar usuario aquí" onclick="event.stopPropagation();quickAssign(\'' + n.id + '\',\'' + esc(n.name) + '\')">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
              '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>' +
              '<circle cx="9" cy="7" r="4"/>' +
              '<line x1="19" y1="8" x2="19" y2="14"/>' +
              '<line x1="22" y1="11" x2="16" y2="11"/>' +
            '</svg>' +
          '</button>';
      }
      if (showDelete) {
        actionsHtml +=
          '<button class="tree-node-action delete" title="Eliminar en cascada" onclick="event.stopPropagation();deleteCascade(\'' + n.id + '\',\'' + esc(n.name) + '\')">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
              '<polyline points="3 6 5 6 21 6"/>' +
              '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>' +
              '<line x1="10" y1="11" x2="10" y2="17"/>' +
              '<line x1="14" y1="11" x2="14" y2="17"/>' +
            '</svg>' +
          '</button>';
      }
      actionsHtml += '</div>';
    }
    li.innerHTML =
      '<div class="tree-node" data-id="' + n.id + '" data-type="' + type + '">' +
        '<button type="button" class="tree-toggle' + (hasCh ? ' open' : '') + '" onclick="event.stopPropagation();toggleTree(this)"></button>' +
        '<div class="tree-node-icon ' + type + '"><i data-lucide="' + getIcon(type) + '" width="16" height="16"></i></div>' +
        '<div class="tree-node-body" onclick="selectNode(this)">' +
          '<span class="tree-node-name">' + esc(n.name || '') + '</span>' +
          (manager ? '<span class="tree-node-meta"><i data-lucide="user" width="10" height="10"></i> ' + esc(manager) + '</span>' : '') +
        '</div>' +
        actionsHtml +
      '</div>';
    if (hasCh) {
      const childUl = document.createElement('ul');
      childUl.className = 'tree-children open';
      li.appendChild(childUl);
      renderTree(n.children, childUl);
    }
    ul.appendChild(li);
  });
  if (typeof lucide !== 'undefined') lucide.createIcons();
  document.getElementById('tree-loading').style.display = 'none';
  document.getElementById('org-tree').style.display = 'block';
}

function getIcon(type) {
  const m = { sede:'building-2', sucursal:'store', direccion:'building', area:'folder', subarea:'folder-open', piso:'layers', oficina:'door-open' };
  return m[type] || 'map-pin';
}

function toggleTree(btn) {
  const li = btn.closest('li');
  const ch = li?.querySelector(':scope > ul');
  if (!ch) return;
  ch.classList.toggle('open');
  btn.classList.toggle('open');
}

// ─── Select node & show detail ───
function selectNode(el) {
  document.querySelectorAll('.tree-node.selected').forEach(x => x.classList.remove('selected'));
  const card = el.closest('.tree-node');
  card.classList.add('selected');
  showDetail(card.dataset.id, card.querySelector('.tree-node-name').textContent);
}

function showDetail(id, name) {
  document.getElementById('detail-empty').style.display = 'none';
  document.getElementById('detail-content').style.display = 'block';
  document.getElementById('detail-content').innerHTML = '<div style="text-align:center;padding:60px;"><div class="spinner" style="margin:0 auto 12px;"></div><p style="color:var(--c-text-muted);font-size:13px;">Cargando detalles...</p></div>';

  api(BASE_URL + 'app/api/locations.php?action=get-detail&id=' + encodeURIComponent(id)).then(data => {
    if (!data || !data.success) {
      document.getElementById('detail-content').innerHTML = '<div class="detail-empty"><i data-lucide="alert-triangle" width="32" height="32" style="color:var(--c-danger);"></i><span>' + esc(data?.error || 'Error') + '</span></div>';
      if (typeof lucide !== 'undefined') lucide.createIcons();
      return;
    }
    renderDetail(data);
  }).catch(() => {
    document.getElementById('detail-content').innerHTML = '<div class="detail-empty"><i data-lucide="alert-triangle" width="32" height="32" style="color:var(--c-danger);"></i><span>Error de conexión</span></div>';
    if (typeof lucide !== 'undefined') lucide.createIcons();
  });
}

function renderDetail(data) {
  const loc = data.location || {};
  const type = (loc.type || '').toLowerCase();
  let html =
    '<div class="detail-cover">' +
      '<div class="detail-badge ' + type + '"><i data-lucide="' + getIcon(type) + '" width="12" height="12"></i>' + esc(loc.type || '') + '</div>' +
      '<h2 class="detail-name">' + esc(loc.name || '') + '</h2>';

  if (data.path && data.path.length) {
    html += '<div class="detail-breadcrumb">' +
      data.path.map((p, i) => {
        return (i < data.path.length - 1 ? '<a href="#" onclick="event.preventDefault();quickFilter(\'' + p.id + '\',\'' + esc(p.type || '') + '\')">' + esc(p.name) + '</a>' : '<span>' + esc(p.name) + '</span>');
      }).join(' <i data-lucide="chevron-right" width="12" height="12"></i> ') +
    '</div>';
  }
  html += '</div><div class="detail-body">';

  // Manager
  if (loc.manager_name && (type === 'area' || type === 'oficina')) {
    html += '<div class="detail-section"><div class="detail-section-header"><i data-lucide="user-check" width="16" height="16" style="color:var(--c-primary);"></i>Encargado</div>' +
      '<div class="detail-item"><strong>' + esc(loc.manager_name + ' ' + (loc.manager_lastname || '')) + '</strong></div></div>';
  }

  // Users
  html += '<div class="detail-section"><div class="detail-section-header"><i data-lucide="users" width="16" height="16" style="color:var(--c-primary);"></i>Usuarios (' + (data.users?.length || 0) + ')</div>';
  if (data.users && data.users.length) {
    html += '<div class="detail-users">';
    data.users.forEach(u => {
      html += '<div class="detail-item"><strong>' + esc((u.nombre||'') + ' ' + (u.apellidos||'')) + '</strong><span class="meta">' + esc(u.email || '') + (u.position_name ? ' &middot; ' + esc(u.position_name) : '') + '</span></div>';
    });
    html += '</div>';
  } else {
    html += '<p style="color:var(--c-text-muted);font-size:13px;">No hay usuarios asignados</p>';
  }
  html += '</div>';

  // Equipment
  html += '<div class="detail-section"><div class="detail-section-header"><i data-lucide="monitor" width="16" height="16" style="color:var(--c-primary);"></i>Equipos (' + (data.equipment?.length || 0) + ')</div>';
  if (data.equipment && data.equipment.length) {
    html += '<div class="detail-equipment">';
    data.equipment.forEach(eq => {
      html += '<div class="detail-item"><strong>' + esc(eq.name || '') + '</strong><span class="meta">' + esc(eq.serial || '') + (eq.patrimonial_code ? ' &middot; ' + esc(eq.patrimonial_code) : '') + '</span></div>';
    });
    html += '</div>';
  } else {
    html += '<p style="color:var(--c-text-muted);font-size:13px;">No hay equipos asignados</p>';
  }
  html += '</div></div>';

  document.getElementById('detail-content').innerHTML = html;
  if (typeof lucide !== 'undefined') lucide.createIcons();
}

// ─── Advanced Filters Toggle ───
function toggleAdvancedFilters() {
  const panel = document.getElementById('advanced-panel');
  const btn = document.getElementById('advanced-toggle-btn');
  const chv = document.getElementById('advanced-chevron');
  panel.classList.toggle('open');
  btn.classList.toggle('active');
  chv.style.transform = panel.classList.contains('open') ? 'rotate(180deg)' : '';
  if (typeof lucide !== 'undefined') lucide.createIcons();
}

function quickFilter(id, type) {
  const map = { sede:'filter-sede', sucursal:'filter-sede', direccion:'filter-sede', area:'filter-area', piso:'filter-piso', oficina:'filter-piso' };
  const sel = document.getElementById(map[type] || 'filter-sede');
  if (sel) { sel.value = id; sel.dispatchEvent(new Event('change')); }
  // auto-open advanced panel
  const panel = document.getElementById('advanced-panel');
  if (!panel.classList.contains('open')) toggleAdvancedFilters();
}

// ─── Filters ───
function populateFilters(nodes) {
  const sedeEl = document.getElementById('filter-sede');
  sedeEl.innerHTML = '<option value="">Todas las sedes</option>';
  function walk(list) {
    list.forEach(n => {
      if ((n.type || '').toUpperCase() === 'SEDE' || (n.type || '').toUpperCase() === 'SUCURSAL' || (n.type || '').toUpperCase() === 'DIRECCION') {
        sedeEl.innerHTML += '<option value="' + n.id + '">' + esc(n.name) + '</option>';
      }
      if (n.children) walk(n.children);
    });
  }
  walk(nodes);
}

document.getElementById('filter-sede').addEventListener('change', function() {
  applyFilters();
  loadCascadeFilter('filter-piso', this.value, ['PISO']);
  loadCascadeFilter('filter-area', this.value, ['AREA','DIRECCION']);
});
document.getElementById('filter-piso').addEventListener('change', applyFilters);
document.getElementById('filter-area').addEventListener('change', applyFilters);
document.getElementById('filter-search').addEventListener('input', applyFilters);

function loadCascadeFilter(selectId, parentId, types) {
  const el = document.getElementById(selectId);
  const placeholder = el.options[0]?.text || 'Todas';
  el.innerHTML = '<option value="">' + placeholder + '</option>';
  if (!parentId) return;
  api(BASE_URL + 'app/api/locations.php?action=get-by-parent&parent_id=' + encodeURIComponent(parentId)).then(data => {
    (data.locations || []).forEach(l => {
      if (types.includes((l.type || '').toUpperCase())) {
        el.innerHTML += '<option value="' + l.id + '">' + esc(l.name) + '</option>';
      }
    });
  }).catch(() => {});
}

function applyFilters() {
  const q = document.getElementById('filter-search').value.toLowerCase().trim();
  const sede = document.getElementById('filter-sede').value;
  const piso = document.getElementById('filter-piso').value;
  const area = document.getElementById('filter-area').value;

  document.querySelectorAll('.tree-node').forEach(card => {
    const name = (card.querySelector('.tree-node-name')?.textContent || '').toLowerCase();
    let show = true;

    if (q && name.indexOf(q) === -1) show = false;

    card.style.display = show ? 'flex' : 'none';
    if (show && q) {
      let p = card.closest('ul.tree-children');
      while (p) {
        p.classList.add('open');
        const toggle = p.closest('li')?.querySelector('.tree-toggle');
        if (toggle) toggle.classList.add('open');
        p = p.closest('li')?.closest('ul.tree-children');
      }
    }
  });

  // Advanced filter by node ancestry
  const activeSelectors = [];
  if (sede) activeSelectors.push(sede);
  if (piso) activeSelectors.push(piso);
  if (area) activeSelectors.push(area);

  if (activeSelectors.length > 0) {
    document.querySelectorAll('.tree-node').forEach(card => {
      if (card.style.display === 'none') return;
      const id = card.dataset.id;
      if (activeSelectors.indexOf(id) === -1) {
        let found = false;
        activeSelectors.forEach(sid => {
          const selCard = document.querySelector('.tree-node[data-id="' + sid + '"]');
          if (selCard && selCard.closest('li').contains(card.closest('li'))) found = true;
        });
        if (!found) card.style.display = 'none';
      }
    });
  }
}

// ─── Create Location ───
function getSelectedNode() {
  const card = document.querySelector('.tree-node.selected');
  return card ? { id: card.dataset.id, type: (card.dataset.type || '').toLowerCase() } : null;
}

function showCreateModalFromSelected() {
  const sel = getSelectedNode();
  showCreateModal(sel ? sel.id : null, sel ? sel.type : null);
}

function showCreateModal(parentId, parentType) {
  const typeSelect = document.getElementById('create-type');
  const parentSelect = document.getElementById('create-parent');
  parentSelect.innerHTML = '<option value="">— Raíz —</option>';
  document.getElementById('create-form').reset();
  // Clear any previous context lock
  window._createContext = null;
  // Restore fields to editable
  [typeSelect, parentSelect].forEach(el => {
    el.style.pointerEvents = '';
    el.style.opacity = '';
  });
  document.getElementById('create-type-group')?.classList.remove('locked');
  document.getElementById('create-parent-group')?.classList.remove('locked');
  // Remove hidden inputs if any
  document.querySelectorAll('.ctx-hidden').forEach(el => el.remove());

  showModal('create-modal');

  const childTypeMap = { sede:'PISO', direccion:'AREA', piso:'AREA', area:'SUBAREA', subarea:null, oficina:null };

  // Lock context if parent provided
  if (parentId && parentType) {
    const childType = childTypeMap[parentType];
    if (!childType) {
      showToast('warning', 'Aviso', 'No se pueden crear sub-ubicaciones en ' + parentType);
      closeModal('create-modal');
      return;
    }
    window._createContext = { parent_id: parentId, parent_type: parentType, child_type: childType };
    // Lock type select
    typeSelect.style.pointerEvents = 'none';
    typeSelect.style.opacity = '0.6';
    document.getElementById('create-type-group')?.classList.add('locked');
    // Lock parent select
    parentSelect.style.pointerEvents = 'none';
    parentSelect.style.opacity = '0.6';
    document.getElementById('create-parent-group')?.classList.add('locked');
    // Hidden inputs to carry locked values
    const hi1 = document.createElement('input');
    hi1.type = 'hidden'; hi1.name = 'type'; hi1.value = childType; hi1.className = 'ctx-hidden';
    const hi2 = document.createElement('input');
    hi2.type = 'hidden'; hi2.name = 'parent_id'; hi2.value = parentId; hi2.className = 'ctx-hidden';
    document.getElementById('create-form').appendChild(hi1);
    document.getElementById('create-form').appendChild(hi2);
  }

  api(BASE_URL + 'app/api/locations.php?action=get-tree').then(data => {
    function walk(nodes, depth) {
      nodes.forEach(n => {
        const opt = document.createElement('option');
        opt.value = n.id;
        opt.textContent = '—'.repeat(depth + 1) + ' ' + n.name + ' (' + (n.type || '') + ')';
        opt.setAttribute('data-type', (n.type || '').toLowerCase());
        parentSelect.appendChild(opt);
        if (n.children) walk(n.children, depth + 1);
      });
    }
    walk(data.tree || [], 0);
    filterParentOptions();
    // After populating, select the pre-set parent & type
    if (parentId && parentType) {
      typeSelect.value = childTypeMap[parentType];
      parentSelect.value = parentId;
      filterParentOptions();
    }
  });
}

document.getElementById('create-type').addEventListener('change', filterParentOptions);
function filterParentOptions() {
  const type = document.getElementById('create-type').value.toLowerCase();
  const compatible = { sede:[], sucursal:['sede','direccion'], direccion:['sede','direccion'], area:['sede','direccion','piso'], subarea:['area','direccion'], piso:['sede','direccion'], oficina:['area','piso','direccion'] };
  const allowed = compatible[type] || [];
  const root = document.querySelector('#create-parent option[value=""]');
  if (root) root.style.display = type === 'sede' ? 'block' : 'none';
  document.querySelectorAll('#create-parent option[value!=""]').forEach(opt => {
    const t = (opt.getAttribute('data-type') || '').toLowerCase();
    opt.style.display = allowed.length === 0 || allowed.indexOf(t) > -1 ? 'block' : 'none';
  });
}

function createLocation() {
  const form = document.getElementById('create-form');
  const fd = new FormData(form);
  const data = {};
  fd.forEach((v, k) => data[k] = v);

  // If context is locked, hidden inputs already provide type/parent_id,
  // but FormData may include visible selects with stale values — override
  if (window._createContext) {
    data.type = window._createContext.child_type;
    data.parent_id = window._createContext.parent_id;
  }

  if (!data.name || !data.type) { showToast('error', 'Validación', 'Nombre y tipo requeridos'); return; }
  data.type = data.type.toUpperCase();
  data.parent_id = data.parent_id || null;
  if (data.type !== 'SEDE' && data.type !== 'SUCURSAL' && !data.parent_id) { showToast('warning', 'Aviso', 'Tipo no raíz requiere padre'); return; }

  api(BASE_URL + 'app/api/locations.php?action=create', data).then(r => {
    if (r.success) {
      closeModal('create-modal');
      window._createContext = null;
      showToast('success', 'Éxito', 'Ubicación creada');
      loadTree();
      refreshStats();
      // If assign modal is open, refresh its sede dropdown
      if (document.getElementById('assign-modal').classList.contains('active')) {
        refreshAssignSedes();
      }
    } else {
      showToast('error', 'Error', r.error || 'Error desconocido');
    }
  }).catch(() => showToast('error', 'Error', 'Error de conexión'));
}

// ─── Unassigned Users List ───
function loadUnassignedUsers() {
  const list = document.getElementById('unassigned-list');
  const count = document.getElementById('unassigned-count');
  api(BASE_URL + 'app/api/locations.php?action=get-users-available').then(data => {
    const users = data.users || [];
    if (count) count.textContent = '(' + users.length + ')';
    if (!users.length) {
      list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--c-text-muted);font-size:13px;">Todos los usuarios están asignados.</div>';
      return;
    }
    const search = (document.getElementById('unassigned-search').value || '').toLowerCase().trim();
    let html = '<div class="unassigned-users-list" style="display:flex;flex-direction:column;gap:4px;">';
    let shown = 0;
    users.forEach(u => {
      const fullName = (u.nombre || '') + ' ' + (u.apellidos || '');
      const email = u.email || '';
      if (search && !fullName.toLowerCase().includes(search) && !email.toLowerCase().includes(search)) return;
      shown++;
      html += '<div class="unassigned-user-item" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-radius:6px;background:var(--c-surface-hover, #f8fafc);transition:background .15s;">' +
        '<div style="display:flex;flex-direction:column;">' +
          '<strong style="font-size:13px;">' + esc(fullName.trim()) + '</strong>' +
          '<span style="font-size:12px;color:var(--c-text-muted);">' + esc(email) + '</span>' +
        '</div>' +
        '<button class="btn-secondary" style="padding:4px 12px;font-size:12px;" onclick="showAssignModal(\'' + u.id + '\')">Asignar</button>' +
      '</div>';
    });
    if (!shown) {
      html = '<div style="text-align:center;padding:20px;color:var(--c-text-muted);font-size:13px;">Ningún usuario coincide con la búsqueda.</div>';
    } else {
      html += '</div>';
    }
    list.innerHTML = html;
  }).catch(() => {
    list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--danger);font-size:13px;">Error al cargar usuarios.</div>';
  });
}

function showAssignModal(preselectUserId) {
  document.getElementById('assign-sede').innerHTML = '<option value="">— Seleccione —</option>';
  document.getElementById('assign-piso').innerHTML = '<option value="">— Seleccione —</option>';
  document.getElementById('assign-area').innerHTML = '<option value="">— Seleccione —</option>';
  document.getElementById('assign-piso-group').style.display = 'none';
  document.getElementById('assign-area-group').style.display = 'none';
  document.getElementById('assign-user-info').style.display = 'none';
  document.getElementById('assign-user-info').innerHTML = '';
  document.getElementById('assign-location-info').style.display = 'none';
  document.getElementById('assign-location-info').innerHTML = '';
  showModal('assign-modal');
  refreshAssignUsers();
  refreshAssignSedes();
  if (preselectUserId) {
    setTimeout(() => {
      const sel = document.getElementById('assign-user');
      if (sel && sel.querySelector('option[value="' + preselectUserId + '"]')) {
        sel.value = preselectUserId;
        sel.dispatchEvent(new Event('change'));
      }
    }, 600);
  }
}

function refreshAssignUsers() {
  const sel = document.getElementById('assign-user');
  sel.innerHTML = '<option value="">Cargando...</option>';
  api(BASE_URL + 'app/api/locations.php?action=get-users-available').then(data => {
    sel.innerHTML = '<option value="">— Seleccione —</option>';
    (data.users || []).forEach(u => {
      sel.innerHTML += '<option value="' + u.id + '"'
        + ' data-nombre="' + esc(u.nombre) + '"'
        + ' data-apellidos="' + esc(u.apellidos || '') + '"'
        + ' data-email="' + esc(u.email || '') + '"'
        + ' data-dni="' + esc(u.dni || '') + '">'
        + esc(u.nombre + ' ' + (u.apellidos || '')) + ' (' + esc(u.email || '') + ')</option>';
    });
    if (!data.users?.length) sel.innerHTML = '<option value="">No hay usuarios disponibles</option>';
    // Re-trigger user info card if user is already selected
    if (sel.value) sel.dispatchEvent(new Event('change'));
  });
}

function refreshAssignSedes() {
  const prevValue = document.getElementById('assign-sede').value;
  const sel = document.getElementById('assign-sede');
  api(BASE_URL + 'app/api/locations.php?action=get-tree').then(data => {
    sel.innerHTML = '<option value="">— Seleccione —</option>';
    function walk(nodes) {
      nodes.forEach(n => {
        const t = (n.type || '').toUpperCase();
        if (t === 'SEDE' || t === 'SUCURSAL' || t === 'DIRECCION') {
          sel.innerHTML += '<option value="' + n.id + '" data-children=\'' + JSON.stringify(n.children || []) + '\'>' + esc(n.name) + '</option>';
        }
        if (n.children) walk(n.children);
      });
    }
    walk(data.tree || []);
    // Restore previous selection if still valid
    if (prevValue) {
      const stillExists = sel.querySelector('option[value="' + prevValue + '"]');
      if (stillExists) { sel.value = prevValue; }
    }
  });
}

// ─── User select -> show info card ───
document.getElementById('assign-user').addEventListener('change', function() {
  const opt = this.selectedOptions[0];
  const cont = document.getElementById('assign-user-info');
  if (!opt || !opt.value) {
    cont.style.display = 'none';
    cont.innerHTML = '';
    return;
  }
  renderUserInfo(opt);
});

function renderUserInfo(opt) {
  const cont = document.getElementById('assign-user-info');
  const nombre = opt.dataset.nombre || '';
  const apellidos = opt.dataset.apellidos || '';
  const email = opt.dataset.email || '';
  const dni = opt.dataset.dni || '';
  cont.innerHTML = '<div class="info-card">'
    + '<div class="row">'
    + '<i data-lucide="user" width="18" height="18" style="color:var(--c-primary);flex-shrink:0;"></i>'
    + '<div style="flex:1;min-width:0;">'
    + '<strong style="font-size:14px;">' + esc(nombre + ' ' + apellidos) + '</strong>'
    + '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">'
    + '<span class="info-badge"><i data-lucide="mail" width="12" height="12"></i> ' + esc(email) + '</span>'
    + (dni ? '<span class="info-badge"><i data-lucide="fingerprint" width="12" height="12"></i> DNI: <strong>' + esc(dni) + '</strong></span>' : '')
    + '</div></div></div></div>';
  cont.style.display = 'block';
  if (typeof lucide !== 'undefined') lucide.createIcons();
}

document.getElementById('assign-sede').addEventListener('change', function() {
  const opt = this.selectedOptions[0];
  document.getElementById('assign-piso-group').style.display = 'none';
  document.getElementById('assign-area-group').style.display = 'none';

  if (opt && opt.value) {
    const children = JSON.parse(opt.dataset.children || '[]');
    populateAssignCascade(children, 'assign-piso', ['PISO'], true);
    populateAssignCascade(children, 'assign-area', ['AREA','DIRECCION'], true);
  }
  updateAssignLocationDetail();
});

document.getElementById('assign-piso').addEventListener('change', updateAssignLocationDetail);
document.getElementById('assign-area').addEventListener('change', updateAssignLocationDetail);

function updateAssignLocationDetail() {
  const locId = getDeepestSelectedLocation();
  const cont = document.getElementById('assign-location-info');
  if (!locId) {
    cont.style.display = 'none';
    cont.innerHTML = '';
    return;
  }
  api(BASE_URL + 'app/api/locations.php?action=get-detail&id=' + locId).then(data => {
    if (data.location) {
      renderLocationInfo(data);
    } else {
      cont.style.display = 'none';
      cont.innerHTML = '';
    }
  }).catch(() => {});
}

function getDeepestSelectedLocation() {
  const area = document.getElementById('assign-area').value;
  if (area) return area;
  const piso = document.getElementById('assign-piso').value;
  if (piso) return piso;
  return document.getElementById('assign-sede').value || null;
}

function renderLocationInfo(data) {
  const loc = data.location;
  const path = data.path || [];
  const users = data.users || [];
  const equip = data.equipment || [];
  const t = (loc.type || '').toUpperCase();
  const showManager = t === 'AREA' || t === 'OFICINA';
  const manager = showManager ? (loc.manager_name ? loc.manager_name + ' ' + (loc.manager_lastname || '') : null) : null;

  const cont = document.getElementById('assign-location-info');

  // Breadcrumb
  let bread = '';
  if (path.length) {
    bread = '<div class="path-breadcrumb">';
    path.forEach((p, i) => {
      if (i > 0) bread += '<span class="sep">›</span>';
      const icon = {SEDE:'building-2',DIRECCION:'building-2',PISO:'layers',AREA:'layout-grid',SUBAREA:'folder',OFICINA:'building'}[p.type] || 'map-pin';
      bread += '<span class="item"><i data-lucide="' + icon + '" width="12" height="12" style="margin-right:2px;"></i>' + esc(p.name) + '</span>';
    });
    bread += '</div>';
  } else {
    bread = '<div class="path-breadcrumb"><span class="item">' + esc(loc.name) + '</span></div>';
  }

  // Manager
  let mgrHtml = '';
  if (showManager) {
    if (manager) {
      mgrHtml = '<div class="cell"><i data-lucide="crown" width="14" height="14" style="color:#f59e0b;"></i> Encargado: <strong>' + esc(manager) + '</strong></div>';
    } else {
      mgrHtml = '<div class="cell"><i data-lucide="crown" width="14" height="14" style="color:#94a3b8;"></i> Encargado: <span style="color:#94a3b8;">Sin asignar</span></div>';
    }
  }

  cont.innerHTML = '<div class="info-card">'
    + bread
    + '<div class="assign-detail-grid">'
    + mgrHtml
    + '<div class="cell"><i data-lucide="users" width="14" height="14"></i> Usuarios: <strong>' + (users.length || 0) + '</strong></div>'
    + '<div class="cell"><i data-lucide="monitor" width="14" height="14"></i> Equipos: <strong>' + (equip.length || 0) + '</strong></div>'
    + (loc.floor ? '<div class="cell"><i data-lucide="layers" width="14" height="14"></i> Piso: <strong>' + esc(loc.floor) + '</strong></div>' : '')
    + (loc.building ? '<div class="cell"><i data-lucide="building" width="14" height="14"></i> Edificio: <strong>' + esc(loc.building) + '</strong></div>' : '')
    + '</div></div>';
  cont.style.display = 'block';
  if (typeof lucide !== 'undefined') lucide.createIcons();
}

function populateAssignCascade(nodes, selectId, types, showGroup) {
  const sel = document.getElementById(selectId);
  sel.innerHTML = '<option value="">— Seleccione —</option>';
  let has = false;
  function walk(list) {
    list.forEach(n => {
      if (types.indexOf((n.type || '').toUpperCase()) > -1) {
        sel.innerHTML += '<option value="' + n.id + '" data-children=\'' + JSON.stringify(n.children || []) + '\'>' + esc(n.name) + '</option>';
        has = true;
      }
      if (n.children) walk(n.children);
    });
  }
  walk(nodes);
  const grp = document.getElementById(selectId + '-group');
  if (grp) grp.style.display = (showGroup && has) ? 'block' : 'none';
}

function assignUser() {
  const uid = document.getElementById('assign-user').value;
  const sede = document.getElementById('assign-sede').value;
  const piso = document.getElementById('assign-piso').value;
  const area = document.getElementById('assign-area').value;
  const locId = area || piso || sede;

  if (!uid) { showToast('warning', 'Atención', 'Seleccione un usuario'); return; }
  if (!locId) { showToast('warning', 'Atención', 'Seleccione una ubicación'); return; }

  api(BASE_URL + 'app/api/locations.php?action=assign-user', { user_id: uid, location_id: locId }).then(r => {
    if (r.success) {
      closeModal('assign-modal');
      showToast('success', 'Éxito', 'Usuario asignado');
      loadUnassignedUsers();
      // Refresh detail if open
      const sel = document.querySelector('.tree-node.selected');
      if (sel) showDetail(sel.dataset.id, '');
    } else {
      showToast('error', 'Error', r.error || 'Error');
    }
  }).catch(() => showToast('error', 'Error', 'Error de conexión'));
}

// ─── Quick Assign from tree ───
function quickAssign(id, name) {
  showAssignModal();
  // Pre-select the sede if possible
  const card = document.querySelector('.tree-node[data-id="' + id + '"]');
  if (card) {
    const type = (card.dataset.type || '').toUpperCase();
    const map = { SEDE:'assign-sede', DIRECCION:'assign-sede', AREA:'assign-area', PISO:'assign-piso' };
    const target = map[type];
    // We need to wait for the modal to load, so set a timeout
    setTimeout(() => {
      const el = document.getElementById(target);
      if (el) { el.value = id; el.dispatchEvent(new Event('change')); }
    }, 500);
  }
}

// ─── Delete Cascade ───
function deleteCascade(id, name) {
  if (!confirm('¿Eliminar "' + name + '" y todas sus ubicaciones dependientes?\n\nEsta acción no se puede deshacer.')) return;
  api(BASE_URL + 'app/api/locations.php?action=delete-cascade', { id: id }).then(r => {
    if (r.success) {
      showToast('success', 'Eliminado', 'Se eliminaron ' + (r.deleted_count || 0) + ' ubicaciones');
      loadTree();
      refreshStats();
      document.getElementById('detail-content').style.display = 'none';
      document.getElementById('detail-empty').style.display = 'flex';
    } else {
      showToast('error', 'Error', r.error || 'Error');
    }
  }).catch(() => showToast('error', 'Error', 'Error de conexión'));
}

// ─── Refresh stats ───
function refreshStats() {
  api(BASE_URL + 'app/api/locations.php?action=list').then(data => {
    // We'll just reload from the API
    const locs = data.locations || [];
    const total = locs.length;
    const sedes = locs.filter(l => (l.type||'').toUpperCase() === 'SEDE' || (l.type||'').toUpperCase() === 'SUCURSAL' || (l.type||'').toUpperCase() === 'DIRECCION').length;
    const areas = locs.filter(l => (l.type||'').toUpperCase() === 'AREA' || (l.type||'').toUpperCase() === 'SUBAREA').length;
    const oficinas = locs.filter(l => (l.type||'').toUpperCase() === 'OFICINA' || (l.type||'').toUpperCase() === 'PISO').length;
    document.getElementById('stat-total').textContent = total;
    document.getElementById('stat-sedes').textContent = sedes;
    document.getElementById('stat-areas').textContent = areas;
    document.getElementById('stat-oficinas').textContent = oficinas;
  }).catch(() => {});
}

// ─── Init ───
document.addEventListener('DOMContentLoaded', function() {
  loadTree();
  loadUnassignedUsers();
  document.getElementById('unassigned-search').addEventListener('input', function() {
    loadUnassignedUsers();
  });
  if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
