<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$isOtiAdmin = \App\Services\AuthService::isAdmin();

$tituloPagina = 'Mis Tickets - Sistema OTI';
$paginaActual = 'user-tickets';

require_once __DIR__ . '/../../Models/Ticket.php';
$tickets = \App\Models\Ticket::getByUserId($userId);

$statusCount = ['total' => count($tickets)];
$statusCount['abiertos'] = count(array_filter($tickets, fn($t) => strtolower($t['status_name'] ?? '') === 'abierto'));
$statusCount['proceso'] = count(array_filter($tickets, fn($t) => strtolower($t['status_name'] ?? '') === 'en proceso'));
$statusCount['resueltos'] = count(array_filter($tickets, fn($t) => in_array(strtolower($t['status_name'] ?? ''), ['resuelto', 'cerrado'])));
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<style>
:root {
  --tkt-radius: 14px; --tkt-transition: 200ms cubic-bezier(0.4,0,0.2,1); --tkt-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
@keyframes spin { to { transform: rotate(360deg); } }

.stagger-1 { animation: fadeUp 0.35s ease forwards; }
.stagger-2 { animation: fadeUp 0.35s ease 0.08s forwards; opacity: 0; }
.stagger-3 { animation: fadeUp 0.35s ease 0.16s forwards; opacity: 0; }

.page-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.btn-new {
  display: inline-flex; align-items: center; gap: 8px; padding: 11px 22px;
  border-radius: 10px; border: none; cursor: pointer; text-decoration: none;
  background: linear-gradient(135deg, var(--primary) 0%, #3730a3 100%);
  color: #fff; font-weight: 600; font-size: 14px; font-family: inherit;
  transition: all var(--tkt-transition);
  box-shadow: 0 4px 12px rgba(67,56,202,0.25);
}
.btn-new:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(67,56,202,0.3); }
.btn-new svg { width: 18px; height: 18px; fill: currentColor; }

.btn-outline-sm {
  display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px;
  border-radius: 9px; border: 1px solid var(--border-light); cursor: pointer;
  background: var(--bg-card); color: var(--text-secondary); font-weight: 500;
  font-size: 13px; font-family: inherit; transition: all var(--tkt-transition);
}
.btn-outline-sm:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-soft); }
.btn-outline-sm svg { width: 16px; height: 16px; fill: currentColor; }

.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card {
  background: var(--bg-card); border-radius: var(--tkt-radius);
  border: 1px solid var(--border-light); padding: 0; overflow: hidden;
  box-shadow: var(--tkt-shadow); transition: all var(--tkt-transition);
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.06); }
.stat-accent { height: 3px; width: 100%; }
.stat-accent.total { background: linear-gradient(90deg, var(--primary), #818cf8); }
.stat-accent.open { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.stat-accent.process { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.stat-accent.resolved { background: linear-gradient(90deg, #22c55e, #4ade80); }
.stat-content { display: flex; align-items: center; gap: 14px; padding: 18px 20px; }
.stat-icon {
  width: 44px; height: 44px; border-radius: 11px; display: flex;
  align-items: center; justify-content: center; flex-shrink: 0;
}
.stat-icon svg { width: 20px; height: 20px; fill: currentColor; }
.stat-icon.total { background: var(--primary-soft); color: var(--primary); }
.stat-icon.open { background: var(--warning-soft); color: #d97706; }
.stat-icon.process { background: var(--info-soft); color: #2563eb; }
.stat-icon.resolved { background: var(--success-soft); color: #16a34a; }
.stat-body { display: flex; flex-direction: column; gap: 1px; }
.stat-value { font-size: 26px; font-weight: 700; color: var(--text-primary); line-height: 1.1; }
.stat-label { font-size: 13px; color: var(--text-muted); }

.filters { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
.filter-btn {
  display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px;
  border-radius: 999px; border: 1px solid var(--border-light); text-decoration: none;
  background: var(--bg-card); color: var(--text-secondary); font-size: 13px;
  font-weight: 500; cursor: pointer; transition: all var(--tkt-transition);
  font-family: inherit;
}
.filter-btn:hover { border-color: var(--primary); color: var(--primary); }
.filter-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 2px 8px rgba(15,41,66,0.2); }
.filter-count {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 20px; height: 20px; border-radius: 10px; font-size: 11px; font-weight: 600;
  background: var(--border-light); color: var(--text-muted); padding: 0 6px;
}
.filter-btn.active .filter-count { background: rgba(255,255,255,0.25); color: #fff; }

.tickets-wrapper {
  background: var(--bg-card); border-radius: var(--tkt-radius);
  border: 1px solid var(--border-light); overflow: hidden;
  box-shadow: var(--tkt-shadow); transition: all var(--tkt-transition);
}
.tickets-table { width: 100%; border-collapse: collapse; }
.tickets-table th {
  text-align: left; padding: 13px 16px; font-size: 11px; font-weight: 600;
  color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px;
  background: #f8fafc; border-bottom: 1px solid var(--border-light); white-space: nowrap;
}
.tickets-table td { padding: 14px 16px; font-size: 14px; color: var(--text-primary); border-bottom: 1px solid var(--border-light); vertical-align: middle; }
.tickets-table tr:last-child td { border-bottom: none; }
.tickets-table tbody tr { cursor: pointer; transition: background 150ms; }
.tickets-table tbody tr:hover { background: var(--primary-soft); }
.tickets-table tbody tr:hover .row-arrow { color: var(--primary); transform: translateX(4px); }

.ticket-code { font-weight: 600; color: var(--primary); font-size: 13px; font-family: monospace; }
.ticket-title { font-weight: 500; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ticket-date { color: var(--text-muted); font-size: 13px; white-space: nowrap; }

.status-badge {
  display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px;
  border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap;
}
.status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
.status-badge.abierto { background: var(--warning-soft); color: #d97706; }
.status-badge.abierto::before { background: #d97706; }
.status-badge.en-proceso { background: var(--info-soft); color: #2563eb; }
.status-badge.en-proceso::before { background: #2563eb; }
.status-badge.resuelto { background: var(--success-soft); color: #16a34a; }
.status-badge.resuelto::before { background: #16a34a; }
.status-badge.cerrado { background: #f1f5f9; color: #64748b; }
.status-badge.cerrado::before { background: #64748b; }
.status-badge.cancelado { background: var(--danger-soft); color: var(--danger); }
.status-badge.cancelado::before { background: var(--danger); }

.priority-badge {
  display: inline-flex; padding: 4px 10px; border-radius: 999px;
  font-size: 12px; font-weight: 600; white-space: nowrap;
}
.priority-badge.critica { background: var(--danger-soft); color: var(--danger); }
.priority-badge.alta { background: #fef3c7; color: #b45309; }
.priority-badge.media { background: var(--primary-soft); color: var(--primary); }
.priority-badge.baja { background: #f1f5f9; color: #64748b; }
.priority-badge.sin-prioridad { background: #f1f5f9; color: #94a3b8; }

.row-arrow { display: flex; align-items: center; justify-content: center; color: var(--text-muted); transition: all 150ms; }
.row-arrow svg { width: 18px; height: 18px; fill: currentColor; }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-state svg { width: 56px; height: 56px; fill: var(--text-muted); opacity: 0.4; margin-bottom: 14px; }
.empty-title { font-size: 17px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; }
.empty-text { font-size: 14px; color: var(--text-muted); margin-bottom: 20px; }

.card { background: var(--bg-card); border-radius: var(--tkt-radius); border: 1px solid var(--border-light); box-shadow: var(--tkt-shadow); }
.card-body { padding: 24px; }

.modal-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
  display: none; align-items: center; justify-content: center; z-index: 5000; padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal {
  background: #fff; border-radius: 16px; width: 100%; max-width: 680px;
  max-height: 90vh; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.2);
  display: flex; flex-direction: column;
  animation: fadeUp 0.25s ease;
}
.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 24px; border-bottom: 1px solid var(--border-light); flex-shrink: 0;
}
.modal-title {
  display: flex; align-items: center; gap: 10px; font-size: 17px;
  font-weight: 600; color: var(--text-primary); margin: 0;
}
.modal-title svg { width: 22px; height: 22px; fill: var(--primary); }
.modal-close {
  background: none; border: none; font-size: 24px; color: var(--text-muted);
  cursor: pointer; width: 36px; height: 36px; display: flex;
  align-items: center; justify-content: center; border-radius: 9px;
  transition: all 150ms; font-family: inherit;
}
.modal-close:hover { background: #f1f5f9; color: var(--text-primary); }
.modal-body { padding: 24px; overflow-y: auto; flex: 1; }
.modal-footer {
  display: flex; gap: 12px; justify-content: flex-end;
  padding: 16px 24px; border-top: 1px solid var(--border-light);
  background: #f8fafc; flex-shrink: 0;
}

.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.detail-section-title {
  display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600;
  color: var(--text-primary); margin: 0 0 10px 0; grid-column: span 2;
  padding-bottom: 8px; border-bottom: 1px solid var(--border-light);
}
.detail-section-title svg { width: 17px; height: 17px; fill: var(--text-muted); }
.detail-item { padding: 14px; background: #f8fafc; border-radius: 10px; }
.detail-label { font-size: 12px; color: var(--text-muted); margin-bottom: 4px; }
.detail-value { font-size: 14px; font-weight: 500; color: var(--text-primary); }
.detail-description { grid-column: span 2; padding: 14px; background: #f8fafc; border-radius: 10px; }
.detail-description .detail-value { white-space: pre-wrap; }

.btn-secondary {
  display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;
  border-radius: 9px; border: 1px solid var(--border-light); cursor: pointer;
  background: #fff; color: var(--text-secondary); font-weight: 500; font-size: 14px;
  font-family: inherit; transition: all 150ms;
}
.btn-secondary:hover { background: #f1f5f9; }

.toast-container {
  position: fixed; bottom: 24px; right: 24px; z-index: 99999;
  display: flex; flex-direction: column; gap: 10px; pointer-events: none;
}
.toast {
  display: flex; align-items: center; gap: 12px; padding: 14px 18px;
  background: #fff; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15);
  min-width: 300px; max-width: 420px; pointer-events: auto;
  animation: slideIn 0.3s ease; border-left: 4px solid; overflow: hidden;
}
.toast.success { border-color: #22c55e; }
.toast.error { border-color: #ef4444; }
.toast.warning { border-color: #f59e0b; }
.toast.info { border-color: #3b82f6; }
.toast-icon { width: 20px; height: 20px; flex-shrink: 0; }
.toast.success .toast-icon { fill: #22c55e; }
.toast.error .toast-icon { fill: #ef4444; }
.toast.warning .toast-icon { fill: #f59e0b; }
.toast.info .toast-icon { fill: #3b82f6; }
.toast-message { font-size: 14px; color: var(--text-primary); font-weight: 500; flex: 1; }
.toast-progress {
  position: absolute; bottom: 0; left: 0; height: 3px;
  background: currentColor; opacity: 0.2; animation: shrink 4s linear forwards;
}
@keyframes shrink { from { width: 100%; } to { width: 0%; } }

@media (max-width: 768px) {
  .stats-grid { grid-template-columns: 1fr; gap: 12px; }
  .tickets-table th:nth-child(3), .tickets-table td:nth-child(3) { display: none; }
  .tickets-table th { font-size: 10px; padding: 10px 10px; }
  .tickets-table td { padding: 11px 10px; font-size: 13px; }
  .ticket-title { max-width: 160px; }
  .modal { margin: 10px; max-height: 85vh; }
  .detail-grid { grid-template-columns: 1fr; }
  .detail-section-title { grid-column: span 1; }
  .detail-description { grid-column: span 1; }
  .page-actions { width: 100%; }
  .btn-new { flex: 1; justify-content: center; }
}
@media (max-width: 480px) {
  .tickets-table th:nth-child(4), .tickets-table td:nth-child(4) { display: none; }
  .ticket-title { max-width: 120px; }
}
</style>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<main id="main-content" class="main-content">
  <div class="page-header">
    <div class="page-header-left">
      <h1 class="page-title">Mis Tickets</h1>
      <p class="page-subtitle">Seguimiento de tus solicitudes de soporte</p>
    </div>
    <div class="page-actions">
      <a href="<?= $baseUrl ?>user/reportar" class="btn-new stagger-1">
        <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        Nuevo Ticket
      </a>
      <button class="btn-outline-sm stagger-2" onclick="exportTableToCSV('.tickets-table', 'mis-tickets')" aria-label="Exportar tickets a CSV">
        <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15.01l1.41 1.41L11 14.84V19h2v-4.16l1.59 1.59L16 15.01 12 11l-4 4.01z"/></svg>
        Exportar CSV
      </button>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card stagger-1">
      <div class="stat-accent total"></div>
      <div class="stat-content">
        <div class="stat-icon total">
          <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value" id="stat-total"><?= $statusCount['total'] ?></div>
          <div class="stat-label">Total de Tickets</div>
        </div>
      </div>
    </div>
    <div class="stat-card stagger-2">
      <div class="stat-accent open"></div>
      <div class="stat-content">
        <div class="stat-icon open">
          <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h10v2z"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value" id="stat-abiertos"><?= $statusCount['abiertos'] ?></div>
          <div class="stat-label">Abiertos</div>
        </div>
      </div>
    </div>
    <div class="stat-card stagger-3">
      <div class="stat-accent resolved"></div>
      <div class="stat-content">
        <div class="stat-icon resolved">
          <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value" id="stat-resueltos"><?= $statusCount['resueltos'] ?></div>
          <div class="stat-label">Resueltos / Cerrados</div>
        </div>
      </div>
    </div>
  </div>

  <div class="filters stagger-3">
    <a href="<?= $baseUrl ?>user/tickets" class="filter-btn <?= !isset($_GET['status']) ? 'active' : '' ?>">
      Todos
      <span class="filter-count"><?= $statusCount['total'] ?></span>
    </a>
    <a href="<?= $baseUrl ?>user/tickets?status=abiertos" class="filter-btn <?= (isset($_GET['status']) && $_GET['status'] === 'abiertos') ? 'active' : '' ?>">
      Abiertos
      <span class="filter-count"><?= $statusCount['abiertos'] ?></span>
    </a>
    <a href="<?= $baseUrl ?>user/tickets?status=proceso" class="filter-btn <?= (isset($_GET['status']) && $_GET['status'] === 'proceso') ? 'active' : '' ?>">
      En Proceso
      <span class="filter-count"><?= $statusCount['proceso'] ?></span>
    </a>
    <a href="<?= $baseUrl ?>user/tickets?status=resueltos" class="filter-btn <?= (isset($_GET['status']) && $_GET['status'] === 'resueltos') ? 'active' : '' ?>">
      Resueltos
      <span class="filter-count"><?= $statusCount['resueltos'] ?></span>
    </a>
  </div>

  <?php
  $filteredTickets = $tickets;
  if (isset($_GET['status'])) {
    $statusFilter = $_GET['status'];
    $filteredTickets = array_filter($tickets, function($t) use ($statusFilter) {
      $sn = strtolower($t['status_name'] ?? '');
      if ($statusFilter === 'abiertos') return $sn === 'abierto';
      if ($statusFilter === 'proceso') return $sn === 'en proceso';
      if ($statusFilter === 'resueltos') return in_array($sn, ['resuelto', 'cerrado']);
      return true;
    });
  }
  ?>

  <?php if (empty($filteredTickets)): ?>
  <div class="card">
    <div class="card-body">
      <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
        <div class="empty-title">No tienes tickets registrados</div>
        <div class="empty-text">&iquest;Tienes alg&uacute;n problema? Crea un nuevo ticket de soporte</div>
        <a href="<?= $baseUrl ?>user/reportar" class="btn-new">Crear Ticket</a>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="tickets-wrapper stagger-3">
    <table class="tickets-table">
      <thead>
        <tr>
          <th>C&oacute;digo</th>
          <th>Asunto</th>
          <th>Fecha</th>
          <th>Estado</th>
          <th>Prioridad</th>
          <th style="width: 40px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($filteredTickets as $ticket): ?>
        <?php
          $statusClass = strtolower(str_replace(' ', '-', str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $ticket['status_name'] ?? 'abierto')));
          $priorityClass = strtolower(str_replace(' ', '-', str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $ticket['priority_name'] ?? 'media')));
        ?>
        <tr onclick="window.location.href='<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>user/ticket-detalle?id=<?= $ticket['id'] ?>'">
          <td><span class="ticket-code"><?= htmlspecialchars($ticket['code']) ?></span></td>
          <td><span class="ticket-title" title="<?= htmlspecialchars($ticket['title']) ?>"><?= htmlspecialchars($ticket['title']) ?></span></td>
          <td><span class="ticket-date"><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></span></td>
          <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($ticket['status_name'] ?? 'Abierto') ?></span></td>
          <td><span class="priority-badge <?= $priorityClass ?>"><?= $ticket['priority_name'] ?? 'Media' ?></span></td>
          <td><div class="row-arrow"><svg viewBox="0 0 24 24"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg></div></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</main>

<div class="modal-overlay" id="modal-ver-ticket">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">
        <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
        Detalles del Ticket
      </h3>
      <button class="modal-close" onclick="cerrarModalTicket()" aria-label="Cerrar">&times;</button>
    </div>
    <div class="modal-body" id="ticket-modal-body"></div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="cerrarModalTicket()">Cerrar</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toast-container"></div>

<script>
var BASE_URL = window.location.origin + '/OTI/';

function showToast(message, type) {
  var container = document.getElementById('toast-container');
  var icons = {
    success: '<svg class="toast-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>',
    error: '<svg class="toast-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
    warning: '<svg class="toast-icon" viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>',
    info: '<svg class="toast-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>'
  };
  var toast = document.createElement('div');
  toast.className = 'toast ' + type;
  toast.innerHTML = icons[type] + '<span class="toast-message">' + message + '</span><div class="toast-progress"></div>';
  container.appendChild(toast);
  setTimeout(function() {
    toast.style.animation = 'slideOut 0.3s ease forwards';
    setTimeout(function() { toast.remove(); }, 300);
  }, 4000);
}

function getStatusClass(statusId) {
  switch(statusId) {
    case 1: return 'abierto';
    case 2: return 'en-proceso';
    case 3: return 'resuelto';
    case 4: return 'cerrado';
    default: return 'abierto';
  }
}

function escapeHtml(text) {
  if (!text) return '';
  var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

function cerrarModalTicket() {
  document.getElementById('modal-ver-ticket').classList.remove('active');
}

function verTicket(id) {
  var modal = document.getElementById('modal-ver-ticket');
  var body = document.getElementById('ticket-modal-body');

  modal.classList.add('active');
  body.innerHTML = '<div style="text-align: center; padding: 30px; color: var(--text-muted); display: flex; flex-direction: column; align-items: center; gap: 12px;"><div style="width: 32px; height: 32px; border: 3px solid var(--border-light); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.7s linear infinite;"></div><span>Cargando informaci&oacute;n del ticket...</span></div>';

  fetch(BASE_URL + 'app/api/user_tickets.php?action=get-ticket&id=' + id)
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.error) {
        body.innerHTML = '<div style="color: var(--danger); padding: 20px; text-align: center;">Error: ' + data.error + '</div>';
        return;
      }

      var html = '<div class="detail-grid">';
      html += '<div class="detail-description" style="background: var(--primary-soft); border-left: 4px solid var(--primary); margin-bottom: 6px;">';
      html += '  <div style="color: var(--primary); font-weight: 600; font-size: 15px; margin-bottom: 4px;">&iexcl;Hola, ' + escapeHtml(data.user_name || '') + '!</div>';
      html += '  <div style="color: var(--text-secondary); font-size: 13px;">Aqu&iacute; puedes revisar todos los detalles y el estado actual de tu solicitud.</div>';
      html += '</div>';

      var statusClass = getStatusClass(data.status_id);
      html += '<div class="detail-item"><div class="detail-label">C&oacute;digo de Ticket</div><div class="detail-value" style="font-weight: 600; color: var(--primary); font-family: monospace;">' + escapeHtml(data.code || '-') + '</div></div>';
      html += '<div class="detail-item"><div class="detail-label">Estado</div><div class="detail-value"><span class="status-badge ' + statusClass + '">' + escapeHtml(data.status_name || 'Abierto') + '</span></div></div>';
      html += '<div class="detail-description"><div class="detail-label">Asunto</div><div class="detail-value" style="font-weight: 600;">' + escapeHtml(data.title || '-') + '</div></div>';
      html += '<div class="detail-description"><div class="detail-label">Descripci&oacute;n del Problema</div><div class="detail-value" style="white-space: pre-wrap;">' + escapeHtml(data.description || '-') + '</div></div>';

      var equipoStr = data.equipment_name ? data.equipment_name : 'Ninguno';
      if (data.equipment_code) equipoStr += ' (' + data.equipment_code + ')';
      html += '<div class="detail-description" style="background: var(--success-soft); border: 1px solid #bbf7d0;"><div class="detail-label" style="color: #16a34a; font-weight: 600;">Equipo Asignado / Involucrado</div><div class="detail-value" style="color: #15803d; font-weight: 600;">' + escapeHtml(equipoStr) + '</div></div>';

      html += '<div class="detail-section-title"><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>Tu Informaci&oacute;n</div>';
      html += '<div class="detail-item"><div class="detail-label">Nombre Completo</div><div class="detail-value">' + escapeHtml((data.user_name || '') + ' ' + (data.user_lastname || '')) + '</div></div>';
      html += '<div class="detail-item"><div class="detail-label">Correo Electr&oacute;nico</div><div class="detail-value">' + escapeHtml(data.user_email || '-') + '</div></div>';
      html += '<div class="detail-item"><div class="detail-label">Tel&eacute;fono</div><div class="detail-value">' + escapeHtml(data.user_phone || '-') + '</div></div>';
      html += '<div class="detail-item"><div class="detail-label">Oficina / &Aacute;rea</div><div class="detail-value">' + escapeHtml(data.location_name || '-') + ' (' + escapeHtml(data.area_name || '-') + ')</div></div>';

      if (data.response_message) {
        html += '<div class="detail-description" style="background: var(--warning-soft); border: 1px solid #fde68a;">';
        html += '  <div class="detail-label" style="color: #d97706; font-weight: 600;">Respuesta de Soporte</div>';
        html += '  <div class="detail-value" style="white-space: pre-wrap; margin-top: 5px;">' + escapeHtml(data.response_message) + '</div>';
        html += '</div>';
      }

      html += '</div>';
      body.innerHTML = html;
    })
    .catch(function(err) {
      body.innerHTML = '<div style="color: var(--danger); padding: 20px; text-align: center;">Error de conexi&oacute;n. Intente nuevamente.</div>';
    });
}

function exportTableToCSV(tableSelector, filename) {
  var table = document.querySelector(tableSelector);
  if (!table) return;
  var rows = table.querySelectorAll('tr');
  var csv = [];
  rows.forEach(function(row) {
    var cols = row.querySelectorAll('th, td');
    var rowData = [];
    cols.forEach(function(col) {
      var text = col.textContent.trim().replace(/,/g, ';').replace(/\n/g, ' ');
      rowData.push('"' + text + '"');
    });
    csv.push(rowData.join(','));
  });
  var csvContent = csv.join('\n');
  var blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
  var link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename + '_' + new Date().toISOString().slice(0, 10) + '.csv';
  link.click();
  URL.revokeObjectURL(link.href);
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') cerrarModalTicket();
});
</script>
<script src="<?= htmlspecialchars($baseUrl) ?>public/assets/js/realtime.js"></script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
