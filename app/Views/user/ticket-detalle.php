<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$isOtiAdmin = \App\Services\AuthService::isAdmin();

$ticketId = isset($_GET['id']) ? $_GET['id'] : null;

$tituloPagina = 'Detalle de Ticket - Sistema OTI';
$paginaActual = 'user-detalle';
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<style>
:root {
  --dt-radius: 14px; --dt-transition: 200ms cubic-bezier(0.4,0,0.2,1); --dt-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
@keyframes spin { to { transform: rotate(360deg); } }

.back-btn {
  display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px;
  border-radius: 10px; text-decoration: none; font-weight: 500; font-size: 14px;
  background: var(--bg-card); color: var(--text-secondary);
  border: 1px solid var(--border-light); transition: all var(--dt-transition);
  margin-bottom: 16px;
}
.back-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
.back-btn svg { width: 18px; height: 18px; fill: currentColor; }

.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.detail-card {
  background: var(--bg-card); border-radius: var(--dt-radius);
  border: 1px solid var(--border-light); padding: 24px;
  box-shadow: var(--dt-shadow); animation: fadeUp 0.35s ease forwards;
}
.detail-card.full { grid-column: span 2; }
.detail-card-title {
  font-size: 15px; font-weight: 600; color: var(--text-primary);
  margin-bottom: 18px; display: flex; align-items: center; gap: 10px;
}
.detail-card-title svg { width: 20px; height: 20px; fill: var(--primary); flex-shrink: 0; }

.status-badge {
  display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px;
  border-radius: 999px; font-size: 13px; font-weight: 600;
}
.status-badge::before { content: ''; width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.status-badge.abierto { background: var(--warning-soft); color: #d97706; }
.status-badge.abierto::before { background: #d97706; box-shadow: 0 0 6px rgba(217,119,6,0.4); }
.status-badge.en-proceso { background: var(--info-soft); color: #2563eb; }
.status-badge.en-proceso::before { background: #2563eb; box-shadow: 0 0 6px rgba(37,99,235,0.4); }
.status-badge.resuelto { background: var(--success-soft); color: #16a34a; }
.status-badge.resuelto::before { background: #16a34a; }
.status-badge.cerrado { background: #f1f5f9; color: #64748b; }
.status-badge.cerrado::before { background: #64748b; }
.status-badge.cancelado { background: var(--danger-soft); color: var(--danger); }
.status-badge.cancelado::before { background: var(--danger); }

.priority-badge {
  display: inline-flex; padding: 5px 12px; border-radius: 999px;
  font-size: 12px; font-weight: 600;
}
.priority-badge.critica { background: var(--danger-soft); color: var(--danger); }
.priority-badge.alta { background: #fef3c7; color: #b45309; }
.priority-badge.media { background: var(--primary-soft); color: var(--primary); }
.priority-badge.baja { background: #f1f5f9; color: #64748b; }
.priority-badge.sin-prioridad { background: #f1f5f9; color: #94a3b8; }

.info-item {
  display: flex; align-items: flex-start; gap: 12px; padding: 10px 0;
  border-bottom: 1px solid var(--border-light); margin: 0;
}
.info-item:last-child { border-bottom: none; }
.info-icon {
  width: 32px; height: 32px; border-radius: 8px; background: var(--primary-soft);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.info-icon svg { width: 16px; height: 16px; fill: var(--primary); }
.info-content { flex: 1; min-width: 0; }
.info-label { font-size: 11px; color: var(--text-muted); margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.4px; font-weight: 600; }
.info-value { font-size: 14px; font-weight: 500; color: var(--text-primary); }

.visto-indicator {
  display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px;
  border-radius: 999px; margin-bottom: 14px; font-size: 12px; font-weight: 500;
}
.visto-indicator svg { width: 14px; height: 14px; flex-shrink: 0; }
.visto-indicator.visto { background: var(--success-soft); color: #15803d; }
.visto-indicator.visto svg { fill: #22c55e; }
.visto-indicator.no-visto { background: var(--warning-soft); color: #b45309; }
.visto-indicator.no-visto svg { fill: #f59e0b; }

.compact-timeline { position: relative; padding-left: 18px; }
.compact-timeline::before {
  content: ''; position: absolute; left: 4px; top: 4px; bottom: 0;
  width: 2px; background: var(--border-light); border-radius: 2px;
}
.compact-timeline-item {
  position: relative; padding: 0 0 6px 0;
  display: flex; align-items: center;
}
.compact-timeline-item:last-child { padding-bottom: 0; }
.compact-timeline-dot {
  position: absolute; left: -14px; top: 50%; transform: translateY(-50%);
  width: 8px; height: 8px; border-radius: 50%; z-index: 1;
}
.compact-timeline-dot.creado { background: var(--primary); }
.compact-timeline-dot.visto { background: #3b82f6; }
.compact-timeline-dot.proceso { background: #f59e0b; }
.compact-timeline-dot.resuelto { background: #22c55e; }
.compact-timeline-dot.cerrado { background: #6b7280; }
.compact-timeline-dot.cancelado { background: var(--danger); }
.compact-timeline-content {
  display: flex; align-items: center; gap: 6px; width: 100%;
  font-size: 12px; padding: 4px 8px; border-radius: 6px;
  transition: background 0.15s; line-height: 1.4;
}
.compact-timeline-content:hover { background: var(--primary-soft); }
.ct-action { font-weight: 500; color: var(--text-primary); min-width: 130px; flex-shrink: 0; }
.ct-actor { color: var(--text-secondary); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ct-time { color: var(--text-muted); font-size: 11px; white-space: nowrap; flex-shrink: 0; }
.ct-final {
  text-align: center; padding: 8px 12px; margin-top: 8px;
  background: var(--success-soft); border-radius: 8px;
  font-size: 12px; font-weight: 600; color: var(--success);
  display: flex; align-items: center; justify-content: center; gap: 6px;
}

.action-buttons { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; }

.cancel-message {
  background: var(--warning-soft); border: 1px solid #fde68a;
  border-radius: 10px; padding: 16px; margin-top: 16px; display: none;
}
.cancel-message.active { display: block; }
.cancel-message p { font-size: 14px; color: #92400e; margin: 0 0 12px 0; }
.cancel-message textarea {
  width: 100%; padding: 12px; border: 1px solid #fcd34d; border-radius: 9px;
  font-size: 14px; resize: vertical; min-height: 80px; box-sizing: border-box;
  font-family: inherit; background: #fff;
}
.cancel-message textarea:focus { outline: none; border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.15); }
.cancel-message-buttons { display: flex; gap: 10px; margin-top: 12px; }
.cancel-confirm {
  background: var(--danger); color: #fff; padding: 10px 18px;
  border-radius: 9px; border: none; font-weight: 500; cursor: pointer;
  font-size: 14px; font-family: inherit;
}
.cancel-confirm:hover { background: #991b1b; }
.cancel-cancel {
  background: #fff; color: var(--text-secondary); padding: 10px 18px;
  border-radius: 9px; border: 1px solid var(--border-light); font-weight: 500;
  cursor: pointer; font-size: 14px; font-family: inherit;
}
.cancel-cancel:hover { background: #f1f5f9; }

.loading { text-align: center; padding: 40px; color: var(--text-muted); display: flex; flex-direction: column; align-items: center; gap: 12px; }
.loading::before { content: ''; width: 28px; height: 28px; border: 3px solid var(--border-light); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.7s linear infinite; }
.error-msg { background: var(--danger-soft); border: 1px solid #fecaca; border-radius: 10px; padding: 16px; color: var(--danger); text-align: center; font-weight: 500; }

.modal-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
  display: none; align-items: center; justify-content: center; z-index: 5000; padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal {
  background: #fff; border-radius: 16px; width: 100%; max-width: 520px;
  max-height: 85vh; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.2);
  display: flex; flex-direction: column; animation: modalIn 200ms ease;
}
.modal.large { max-width: 640px; }
.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 22px; border-bottom: 1px solid var(--border-light); flex-shrink: 0;
}
.modal-title {
  display: flex; align-items: center; gap: 10px; font-size: 16px;
  font-weight: 600; color: var(--text-primary); margin: 0;
}
.modal-title svg { width: 20px; height: 20px; fill: var(--primary); flex-shrink: 0; }
.modal-close {
  background: none; border: none; font-size: 22px; color: var(--text-muted);
  cursor: pointer; width: 34px; height: 34px; display: flex;
  align-items: center; justify-content: center; border-radius: 9px;
  transition: all 150ms; font-family: inherit;
}
.modal-close:hover { background: #f1f5f9; color: var(--text-primary); }
.modal-body { padding: 22px; overflow-y: auto; flex: 1; }
.modal-footer {
  display: flex; gap: 10px; justify-content: flex-end;
  padding: 14px 22px; border-top: 1px solid var(--border-light);
  background: #f8fafc; flex-shrink: 0;
}

.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; }
.form-textarea {
  width: 100%; padding: 10px 14px; border: 1px solid var(--border-light);
  border-radius: 9px; font-size: 14px; font-family: inherit; resize: vertical;
  min-height: 80px; transition: border-color 150ms; box-sizing: border-box;
}
.form-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }

.btn {
  display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px;
  border-radius: 9px; font-size: 13px; font-weight: 600; cursor: pointer;
  transition: all 150ms; font-family: inherit; border: none;
}
.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15,41,66,0.2); }
.btn-secondary { background: #fff; color: var(--text-secondary); border: 1px solid var(--border-light); }
.btn-secondary:hover { border-color: var(--primary); color: var(--primary); }
.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover { background: #991b1b; }

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

@media (max-width: 1024px) {
  .detail-grid { grid-template-columns: 1fr; }
  .detail-card.full { grid-column: span 1; }
}
@media (max-width: 768px) {
  .action-buttons .action-btn { flex: 1; justify-content: center; }
  .modal { margin: 10px; max-height: 85vh; }
}
</style>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>
<input type="hidden" id="user-id" value="<?= $userId ?>">
<input type="hidden" id="ticket-id" value="<?= $ticketId ?>">
<input type="hidden" id="is-admin" value="<?= $isOtiAdmin ? '1' : '0' ?>">

<main id="main-content" class="main-content">
  <div class="page-header">
    <div class="page-header-left">
      <a href="<?= htmlspecialchars($baseUrl) ?>user/tickets" class="back-btn">
        <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
        Volver a Mis Tickets
      </a>
      <h1 class="page-title">Detalle del Ticket</h1>
      <p class="page-subtitle">C&oacute;digo: <span id="ticket-code" style="font-weight:600;color:var(--primary);font-family:monospace;">Cargando...</span></p>
    </div>
  </div>

  <div id="ticket-content">
    <div class="loading">Cargando informaci&oacute;n del ticket...</div>
  </div>
</main>

<div class="modal-overlay" id="modal-notificar">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">
        <svg viewBox="0 0 24 24" fill="#7c3aed"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
        Enviar Notificaci&oacute;n
      </h3>
      <button class="modal-close" onclick="cerrarModalNotificar()">&times;</button>
    </div>
    <div class="modal-body" id="notificar-content"><div class="loading">Cargando...</div></div>
  </div>
</div>

<div class="modal-overlay" id="modal-editar">
  <div class="modal large">
    <div class="modal-header">
      <h3 class="modal-title">
        <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z"/></svg>
        Editar Ticket
      </h3>
      <button class="modal-close" onclick="cerrarModalEditar()">&times;</button>
    </div>
    <div class="modal-body" id="editar-contenido"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="cerrarModalEditar()">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarEdicion()">Guardar Cambios</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-delete">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3 class="modal-title" style="color:var(--danger);">
        <svg viewBox="0 0 24 24" fill="var(--danger)"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
        Eliminar Ticket
      </h3>
      <button class="modal-close" onclick="cerrarModalDelete()">&times;</button>
    </div>
    <div class="modal-body">
      <p style="font-size:14px;color:var(--text-secondary);margin:0;">&iquest;Est&aacute;s seguro de eliminar este ticket? Esta acci&oacute;n no se puede deshacer.</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="cerrarModalDelete()">Cancelar</button>
      <button class="btn btn-danger" onclick="confirmDeleteAction()">S&iacute;, Eliminar</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toast-container"></div>

<script>
var BASE_URL = window.location.origin + '/OTI/';
var ticketId = document.getElementById('ticket-id').value;
var notifUserEmail = '';

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
  toast.innerHTML = icons[type] + '<span class="toast-message">' + message + '</span>';
  container.appendChild(toast);
  setTimeout(function() {
    toast.style.animation = 'slideOut 0.3s ease forwards';
    setTimeout(function() { toast.remove(); }, 300);
  }, 4000);
}

function escapeHtml(text) {
  if (!text) return '';
  var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

function getStatusClass(statusName) {
  var s = (statusName || '').toLowerCase();
  if (s.includes('abierto') || s === 'open' || s === 'pending') return 'abierto';
  if (s.includes('proceso') || s.includes('progress') || s === 'in_progress') return 'en-proceso';
  if (s.includes('resuelto') || s.includes('resolved')) return 'resuelto';
  if (s.includes('cerrado') || s.includes('closed')) return 'cerrado';
  if (s.includes('cancelado') || s.includes('cancelled')) return 'cancelado';
  return 'abierto';
}

function getPriorityClass(priority) {
  var p = (priority || '').toLowerCase();
  if (p.includes('crítica') || p.includes('critica') || p === 'critical') return 'critica';
  if (p.includes('alta') || p === 'high') return 'alta';
  if (p.includes('media') || p === 'medium') return 'media';
  if (p.includes('baja') || p === 'low') return 'baja';
  return 'media';
}

function formatDate(dateStr) {
  if (!dateStr) return '-';
  var date = new Date(dateStr);
  return date.toLocaleDateString('es-PE', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
  });
}

function getActionLabel(action) {
  var labels = {
    'creado': 'Creado', 'created': 'Creado',
    'visto': 'Visto por soporte', 'seen': 'Visto por soporte',
    'visto_admin': 'Revisado por el administrador',
    'en_proceso': 'En Proceso', 'in_progress': 'En Proceso', 'proceso': 'En Proceso',
    'asignado': 'Asignado', 'assigned': 'Asignado',
    'resuelto': 'Resuelto', 'resolved': 'Resuelto',
    'cerrado': 'Cerrado', 'closed': 'Cerrado',
    'cancelado': 'Cancelado', 'cancelled': 'Cancelado',
    'notificacion_enviada': 'Notificación Enviada', 'notification_sent': 'Notificación Enviada',
    'actualizado': 'Actualizado', 'updated': 'Actualizado',
    'comentario': 'Comentario',
    'prioridad': 'Prioridad cambiada',
    'reabierto': 'Reabierto',
    'status_changed': 'Estado cambiado'
  };
  return labels[(action || '').toLowerCase().trim()] || action;
}

function getTimelineDotClass(action) {
  var a = (action || '').toLowerCase();
  if (a.includes('creado') || a === 'created') return 'creado';
  if (a.includes('visto')) return 'visto';
  if (a.includes('proceso') || a.includes('asignado') || a.includes('progress') || a === 'assigned' || a.includes('status_changed')) return 'proceso';
  if (a.includes('resuelto') || a.includes('resolved')) return 'resuelto';
  if (a.includes('cerrado') || a.includes('closed')) return 'cerrado';
  if (a.includes('cancelado') || a.includes('cancelled')) return 'cancelado';
  return 'creado';
}

var pollingInterval = null;

function loadTicket() {
  fetch(BASE_URL + 'app/api/user_tickets.php?action=get-ticket&id=' + ticketId)
    .then(function(res) { return res.json(); })
    .then(function(ticket) {
      if (ticket.error) {
        document.getElementById('ticket-content').innerHTML = '<div class="error-msg">' + escapeHtml(ticket.error) + '</div>';
        return;
      }

      document.getElementById('ticket-code').textContent = ticket.code || '-';
      window._currentTicket = ticket;

      var statusName = (ticket.status_name || '').toLowerCase();
      var isFinal = statusName.includes('cerrado') || statusName.includes('resuelto') || statusName.includes('cancelado') || statusName === 'closed' || statusName === 'resolved' || statusName === 'cancelled';

      if (isFinal && pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
        document.getElementById('ticket-final-status').style.display = 'flex';
      }

      var statusClass = getStatusClass(ticket.status_name);
      var priorityClass = getPriorityClass(ticket.priority_name);
      notifUserEmail = ticket.user_email || '';

      var vistoHtml = ticket.has_been_seen
        ? '<div class="visto-indicator visto"><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg><span>El equipo de soporte ha visto tu ticket</span></div>'
        : '<div class="visto-indicator no-visto"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/></svg><span>Tu ticket est&aacute; siendo revisado por el equipo de soporte</span></div>';

      var equipoStr = ticket.equipment_name
        ? escapeHtml(ticket.equipment_name) + (ticket.equipment_code ? ' (' + escapeHtml(ticket.equipment_code) + ')' : '')
        : 'No especificado';

      var html = '<div class="detail-grid">';
      html += '<div class="detail-card">';
      html += '<div class="detail-card-title"><svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/></svg>Informaci&oacute;n del Ticket</div>';
      html += vistoHtml;
      html += '<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;">';
      html += '<span class="status-badge ' + statusClass + '" id="ticket-status">' + escapeHtml(ticket.status_name || 'Abierto') + '</span>';
      html += '<span class="priority-badge ' + priorityClass + '">' + escapeHtml(ticket.priority_name || 'Media') + '</span>';
      html += '</div>';

      html += '<div class="info-item"><div class="info-icon"><svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg></div><div class="info-content"><div class="info-label">Asunto</div><div class="info-value">' + escapeHtml(ticket.title || '-') + '</div></div></div>';
      html += '<div class="info-item"><div class="info-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg></div><div class="info-content"><div class="info-label">Descripci&oacute;n</div><div class="info-value" style="white-space:pre-wrap;">' + escapeHtml(ticket.description || '-') + '</div></div></div>';
      html += '<div class="info-item"><div class="info-icon"><svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4z"/></svg></div><div class="info-content"><div class="info-label">Equipo</div><div class="info-value">' + equipoStr + '</div></div></div>';
      html += '<div class="info-item"><div class="info-icon"><svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg></div><div class="info-content"><div class="info-label">Ubicaci&oacute;n</div><div class="info-value">' + escapeHtml(ticket.location_name || '-') + ' (' + escapeHtml(ticket.area_name || '-') + ')</div></div></div>';

      if (ticket.response_message) {
        html += '<div class="info-item" style="background:var(--warning-soft);border:1px solid #fde68a;padding:10px 12px;border-radius:8px;">';
        html += '<div class="info-icon" style="background:#fef3c7;"><svg viewBox="0 0 24 24" style="fill:#f59e0b;"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg></div>';
        html += '<div class="info-content"><div class="info-label" style="color:#d97706;font-weight:600;">Respuesta de Soporte</div><div class="info-value" style="white-space:pre-wrap;">' + escapeHtml(ticket.response_message) + '</div>';
        if (ticket.assigned_name) {
          html += '<div style="font-size:11px;color:#92400e;margin-top:4px;">Atendido por: ' + escapeHtml(ticket.assigned_name) + ' ' + escapeHtml(ticket.assigned_lastname || '') + '</div>';
        }
        html += '</div></div>';
      }

      var isOpen = statusName.includes('abierto') || statusName === 'open' || statusName === 'pending' || statusName.includes('pendiente');
      // Mostrar botones solo si el ticket está abierto y NO ha sido visto por el administrador
      if (isOpen && !ticket.has_been_seen) {
        html += '<div class="action-buttons">';
        html += '<button class="action-btn full edit" onclick="editarTicketDetalle()" title="Editar ticket"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z"/></svg>Editar</button>';
        html += '<button class="action-btn full notify" onclick="enviarNotificacion()" title="Enviar notificaci&oacute;n"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>Notificar</button>';
        html += '<button class="action-btn full deactivate" onclick="showCancelMessage()"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 19 17.59 13.41 12z"/></svg>Cancelar</button>';
        html += '<button class="action-btn full delete" onclick="confirmDelete()" title="Eliminar ticket"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>Eliminar</button>';
        html += '</div>';
        html += '<div class="cancel-message" id="cancel-message">';
        html += '<p>&iquest;Est&aacute;s seguro de que deseas cancelar este ticket? Esta acci&oacute;n no se puede deshacer.</p>';
        html += '<textarea id="cancel-reason" placeholder="Opcional: Describe brevemente por qu&eacute; cancelas el ticket..."></textarea>';
        html += '<div class="cancel-message-buttons">';
        html += '<button class="cancel-confirm" onclick="confirmCancel()">S&iacute;, Cancelar Ticket</button>';
        html += '<button class="cancel-cancel" onclick="hideCancelMessage()">No, Mantener</button>';
        html += '</div></div>';
      }
      html += '</div>';

      html += '<div class="detail-card">';
      html += '<div class="detail-card-title"><svg viewBox="0 0 24 24"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9z"/></svg>Actividad</div>';
      html += '<div id="ticket-final-status" style="display:none;"></div>';
      html += '<div id="timeline-container"><div class="loading">Cargando historial...</div></div>';
      html += '</div></div>';

      document.getElementById('ticket-content').innerHTML = html;
      loadActivities();
    })
    .catch(function() {
      document.getElementById('ticket-content').innerHTML = '<div class="error-msg">Error al cargar el ticket</div>';
    });
}

function loadActivities() {
  fetch(BASE_URL + 'app/api/user_tickets.php?action=get-activities&id=' + ticketId)
    .then(function(res) { return res.json(); })
    .then(function(data) {
      var container = document.getElementById('timeline-container');
      var finalStatus = document.getElementById('ticket-final-status');
      if (!container) return;

      var finalActions = ['cerrado', 'closed', 'resuelto', 'resolved', 'cancelado', 'cancelled'];
      var foundFinal = false;

      if (data.activities && data.activities.length > 0) {
        var html = '<div class="compact-timeline">';
        data.activities.forEach(function(activity) {
          var dotClass = getTimelineDotClass(activity.action);
          var actor = activity.nombre ? (activity.nombre + (activity.apellidos ? ' ' + activity.apellidos : '')) : '';
          var actionLabel = escapeHtml(getActionLabel(activity.action));
          var createdAt = formatDate(activity.created_at);

          var a = (activity.action || '').toLowerCase();
          if (finalActions.some(function(f) { return a.includes(f); })) foundFinal = true;

          html += '<div class="compact-timeline-item">';
          html += '<div class="compact-timeline-dot ' + dotClass + '"></div>';
          html += '<div class="compact-timeline-content">';
          html += '<span class="ct-action">' + actionLabel + '</span>';
          if (actor) html += '<span class="ct-actor">' + escapeHtml(actor) + '</span>';
          html += '<span class="ct-time">' + createdAt + '</span>';
          html += '</div></div>';
        });
        html += '</div>';
        container.innerHTML = html;
      } else {
        container.innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:12px;font-size:13px;">No hay actividades registradas</div>';
      }

      if (foundFinal && finalStatus) {
        finalStatus.innerHTML = '<div class="ct-final"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Ticket cerrado &mdash; fin del seguimiento</div>';
        finalStatus.style.display = 'block';
      } else if (finalStatus) {
        finalStatus.style.display = 'none';
      }
    })
    .catch(function() {
      var container = document.getElementById('timeline-container');
      if (container) container.innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:12px;font-size:13px;">Error al cargar actividades</div>';
    });
}

function showCancelMessage() { var el = document.getElementById('cancel-message'); if (el) el.classList.add('active'); }
function hideCancelMessage() { var el = document.getElementById('cancel-message'); if (el) el.classList.remove('active'); }

function confirmCancel() {
  var reason = document.getElementById('cancel-reason').value;
  fetch(BASE_URL + 'app/api/user_tickets.php?action=cancel-ticket', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'id=' + encodeURIComponent(ticketId) + '&reason=' + encodeURIComponent(reason)
  })
    .then(function(res) { return res.json(); })
    .then(function(result) {
      if (result.success) {
        showToast('Ticket cancelado correctamente', 'success');
        setTimeout(function() { window.location.href = BASE_URL + 'user/tickets'; }, 1500);
      } else {
        showToast(result.error || 'Error al cancelar el ticket', 'error');
      }
    })
    .catch(function() { showToast('Error de conexi&oacute;n', 'error'); });
}

function confirmDelete() { document.getElementById('modal-delete').classList.add('active'); }
function cerrarModalDelete() { document.getElementById('modal-delete').classList.remove('active'); }

function confirmDeleteAction() {
  fetch(BASE_URL + 'app/api/user_tickets.php?action=delete-ticket', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'id=' + encodeURIComponent(ticketId)
  })
    .then(function(res) { return res.json(); })
    .then(function(result) {
      if (result.success) {
        showToast('Ticket eliminado correctamente', 'success');
        setTimeout(function() { window.location.href = BASE_URL + 'user/tickets'; }, 1500);
      } else {
        showToast(result.error || 'Error al eliminar el ticket', 'error');
      }
    })
    .catch(function() { showToast('Error de conexi&oacute;n', 'error'); });
}

function enviarNotificacion() {
  var notifModal = document.getElementById('modal-notificar');
  var content = document.getElementById('notificar-content');
  notifModal.classList.add('active');
  content.innerHTML = '<div class="loading">Cargando...</div>';

  fetch(BASE_URL + 'app/api/user_tickets.php?action=get-ticket&id=' + ticketId)
    .then(function(res) { return res.json(); })
    .then(function(ticket) {
      if (ticket.error) {
        content.innerHTML = '<div class="error-msg">' + escapeHtml(ticket.error) + '</div>';
        return;
      }
      notifUserEmail = ticket.user_email || '';
      content.innerHTML = '<div style="font-size:14px;margin-bottom:16px;">'
        + '<p style="margin:0 0 12px 0;color:var(--text-secondary);font-size:13px;">Enviar&aacute;s una notificaci&oacute;n a:</p>'
        + '<div style="background:var(--primary-soft);padding:12px;border-radius:8px;font-weight:600;color:var(--primary);">'
        + escapeHtml((ticket.user_name||'') + ' ' + (ticket.user_lastname||''))
        + '</div>'
        + '<div style="font-size:12px;color:var(--text-muted);margin-top:4px;">' + escapeHtml(ticket.user_email||'') + '</div>'
        + '</div>'
        + '<div class="form-group"><label class="form-label">Mensaje de notificaci&oacute;n:</label>'
        + '<textarea id="notif-mensaje" class="form-textarea" rows="4" placeholder="Escribe el mensaje..."></textarea></div>'
        + '<div class="form-group" style="margin-top:12px;"><label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">'
        + '<input type="checkbox" id="notif-enviar-email" checked style="width:18px;height:18px;accent-color:var(--primary);">Enviar tambi&eacute;n por correo electr&oacute;nico</label></div>'
        + '<div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">'
        + '<button class="btn btn-secondary" onclick="cerrarModalNotificar()">Cancelar</button>'
        + '<button class="btn btn-primary" onclick="confirmarEnviarNotificacion()">'
        + '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>'
        + 'Enviar Notificaci&oacute;n</button></div>';
    })
    .catch(function() {
      content.innerHTML = '<div class="error-msg">Error al cargar datos</div>';
    });
}

function cerrarModalNotificar() { document.getElementById('modal-notificar').classList.remove('active'); }

function confirmarEnviarNotificacion() {
  var mensaje = document.getElementById('notif-mensaje').value;
  var enviarEmail = document.getElementById('notif-enviar-email').checked ? '1' : '0';
  if (!mensaje || mensaje.trim() === '') {
    showToast('Ingresa un mensaje para la notificaci&oacute;n', 'warning');
    return;
  }
  fetch(BASE_URL + 'app/api/user_tickets.php?action=send-notification&id=' + ticketId, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'id=' + encodeURIComponent(ticketId) + '&message=' + encodeURIComponent(mensaje) + '&send_email=' + encodeURIComponent(enviarEmail)
  })
    .then(function(res) { return res.json(); })
    .then(function(result) {
      if (result.success) {
        showToast('Notificaci&oacute;n enviada correctamente', 'success');
        cerrarModalNotificar();
        loadTicket();
      } else {
        showToast(result.error || 'Error al enviar la notificaci&oacute;n', 'error');
      }
    })
    .catch(function() { showToast('Error de conexi&oacute;n', 'error'); });
}

function editarTicketDetalle() {
  var editModal = document.getElementById('modal-editar');
  var contenido = document.getElementById('editar-contenido');
  editModal.classList.add('active');
  contenido.innerHTML = '<div class="loading">Cargando formulario...</div>';

  fetch(BASE_URL + 'app/api/user_tickets.php?action=get-ticket&id=' + ticketId)
    .then(function(res) { return res.json(); })
    .then(function(ticket) {
      if (ticket.error) {
        contenido.innerHTML = '<div class="error-msg">' + escapeHtml(ticket.error) + '</div>';
        return;
      }
      var html = '<input type="hidden" id="edit-ticket-id" value="' + ticket.id + '">';
      html += '<div style="margin-bottom:16px;padding:14px;background:var(--primary-soft);border-radius:8px;border:1px solid var(--border-light);">';
      html += '<div style="font-size:12px;font-weight:600;color:var(--primary);margin-bottom:8px;">Ticket de:</div>';
      html += '<div style="font-size:13px;color:var(--text-primary);"><strong>' + escapeHtml((ticket.user_name||'') + ' ' + (ticket.user_lastname||'')) + '</strong></div>';
      html += '<div style="font-size:12px;color:var(--text-secondary);margin-top:4px;">' + escapeHtml(ticket.code || '') + '</div>';
      html += '<div style="font-size:12px;color:var(--text-secondary);margin-top:4px;"><em>Datos del solicitante &mdash; solo lectura</em></div>';
      html += '</div>';
      html += '<div class="form-group"><label class="form-label">Agregar comentario / respuesta:</label>';
      html += '<textarea id="edit-respuesta" class="form-textarea" rows="4" placeholder="Escribe detalles del estado actual o soluci&oacute;n..." style="min-height:100px;">' + escapeHtml(ticket.response_message||'') + '</textarea></div>';
      html += '<div style="display:flex;align-items:center;gap:10px;margin-top:12px;background:var(--primary-soft);padding:12px;border-radius:8px;border:1px solid var(--border-light);">';
      html += '<input type="checkbox" id="edit-enviar-mensaje" value="1" checked style="width:18px;height:18px;cursor:pointer;accent-color:var(--primary);">';
      html += '<label for="edit-enviar-mensaje" style="font-size:13px;font-weight:500;color:var(--primary);cursor:pointer;">Enviar notificaci&oacute;n en tiempo real</label>';
      html += '</div>';
      contenido.innerHTML = html;
    })
    .catch(function() {
      contenido.innerHTML = '<div class="error-msg">Error cargando datos.</div>';
    });
}

function cerrarModalEditar() { document.getElementById('modal-editar').classList.remove('active'); }

function guardarEdicion() {
  var id = document.getElementById('edit-ticket-id');
  var respuesta = document.getElementById('edit-respuesta');
  if (!id || !respuesta) { showToast('Error al obtener datos del formulario', 'error'); return; }

  var message = respuesta.value;
  var sendEmail = document.getElementById('edit-enviar-mensaje') ? document.getElementById('edit-enviar-mensaje').checked : true;

  var url = sendEmail
    ? BASE_URL + 'app/api/user_tickets.php?action=send-notification&id=' + id.value
    : BASE_URL + 'app/api/user_tickets.php?action=update-ticket';

  var body = sendEmail
    ? 'id=' + encodeURIComponent(id.value) + '&message=' + encodeURIComponent(message) + '&send_email=1'
    : 'id=' + encodeURIComponent(id.value) + '&response_message=' + encodeURIComponent(message);

  fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body
  })
    .then(function(res) { return res.json(); })
    .then(function(result) {
      if (result.success) {
        showToast('Ticket actualizado correctamente', 'success');
        cerrarModalEditar();
        loadTicket();
      } else {
        showToast(result.error || 'Error al actualizar el ticket', 'error');
      }
    })
    .catch(function() { showToast('Error de conexi&oacute;n', 'error'); });
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    if (document.getElementById('modal-notificar').classList.contains('active')) cerrarModalNotificar();
    if (document.getElementById('modal-editar').classList.contains('active')) cerrarModalEditar();
    if (document.getElementById('modal-delete').classList.contains('active')) cerrarModalDelete();
  }
});

loadTicket();

pollingInterval = setInterval(function() { loadTicket(); }, 120000);
</script>
<script src="<?= htmlspecialchars($baseUrl) ?>public/assets/js/realtime.js"></script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
