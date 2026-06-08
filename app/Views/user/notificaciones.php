<?php
/**
 * Notificaciones del Usuario - Sistema OTI
 */

$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$userEmail = $_SESSION['user']['email'] ?? '';

$isOtiAdmin = \App\Services\AuthService::isAdmin();

$tituloPagina = 'Notificaciones - Sistema OTI';
$paginaActual = 'user-notificaciones';
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<main id="main-content" class="main-content">
  <div class="page-header">
    <div>
      <h1 class="page-title">Notificaciones</h1>
      <p class="page-subtitle">Centro de mensajes y seguimiento de tickets</p>
    </div>
  </div>

  <div class="notif-page">
    <div class="notif-toolbar">
      <div class="notif-tabs" id="notif-tabs">
        <button class="notif-tab active" data-filter="all" onclick="filterNotif('all', this)">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
          Todas
          <span class="badge" id="badge-all">0</span>
        </button>
        <button class="notif-tab" data-filter="unread" onclick="filterNotif('unread', this)">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
          No leídas
          <span class="badge" id="badge-unread">0</span>
        </button>
      </div>
      <div class="notif-actions">
        <button class="btn-mark-read" onclick="marcarTodasLeidas()">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
          Marcar todas leídas
        </button>
      </div>
    </div>

    <div class="notif-list" id="notif-list">
      <div class="loading"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" style="animation:spin 1s linear infinite;margin-bottom:8px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/></svg><br>Cargando notificaciones...</div>
    </div>
  </div>
</main>

<!-- Modal Confirmar Eliminación -->
<div class="modal-overlay" id="modal-delete">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3 class="modal-title" style="color:var(--danger);"><svg viewBox="0 0 24 24" fill="var(--danger)"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>Eliminar Notificación</h3>
      <button class="modal-close" onclick="cerrarModal('modal-delete')">&times;</button>
    </div>
    <div class="modal-body">
      <p style="font-size:14px;color:var(--text-secondary);">¿Estás seguro de eliminar esta notificación? Esta acción no se puede deshacer.</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="cerrarModal('modal-delete')">Cancelar</button>
      <button class="btn btn-danger" onclick="confirmarEliminarNotificacion()">Sí, Eliminar</button>
    </div>
  </div>
</div>

<style>
.notif-btn-icon svg {
  width: 17px;
  height: 17px;
  fill: currentColor;
  pointer-events: none;
}
</style>

<!-- Toast Container -->
<div class="toast-container" id="toast-container"></div>

<script>
const BASE_URL = window.location.origin + '/OTI/';
let currentFilter = 'all';
let deleteNotifId = null;

function showToast(message, type) {
  const container = document.getElementById('toast-container');
  const icons = {
    success: '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>',
    error: '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
    warning: '<svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>',
    info: '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>'
  };
  const toast = document.createElement('div');
  toast.className = 'toast ' + type;
  toast.innerHTML = '<span class="toast-icon">' + icons[type] + '</span><span class="toast-message">' + message + '</span><button class="toast-close" onclick="this.parentElement.remove()">&times;</button>';
  container.appendChild(toast);
  setTimeout(() => { toast.style.animation = 'slideOut 0.3s ease forwards'; setTimeout(() => toast.remove(), 300); }, 4000);
}

function escapeHtml(text) {
  if (!text) return '';
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return text.toString().replace(/[&<>"']/g, m => map[m]);
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function timeAgo(dateStr) {
  if (!dateStr) return '';
  const diff = Date.now() - new Date(dateStr).getTime();
  if (diff < 60000) return 'Ahora';
  if (diff < 3600000) return Math.floor(diff / 60000) + 'm';
  if (diff < 86400000) return Math.floor(diff / 3600000) + 'h';
  if (diff < 604800000) return Math.floor(diff / 86400000) + 'd';
  return formatDate(dateStr);
}

function filterNotif(type, btn) {
  currentFilter = type;
  document.querySelectorAll('.notif-tab').forEach(t => t.classList.remove('active'));
  if (btn) btn.classList.add('active');
  renderCards(window._notifData || []);
}

function renderCards(data) {
  const container = document.getElementById('notif-list');
  const filtered = data.filter(n => {
    if (currentFilter === 'all') return true;
    if (currentFilter === 'unread') return !n.is_read && n.is_read !== 't';
    return true;
  });

  if (filtered.length === 0) {
    container.innerHTML = '<div class="empty-state"><svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg><div class="empty-title">Sin notificaciones</div><div class="empty-text">' + (currentFilter === 'unread' ? 'No tienes notificaciones sin leer' : 'No hay notificaciones disponibles') + '</div></div>';
    return;
  }

  let html = '';
  filtered.forEach(n => {
    const isUnread = !n.is_read || n.is_read === 'f' || n.is_read === false;
    const iconType = n.tipo === 'alerta' ? 'alert' : (n.ticket_id ? 'ticket' : 'message');
    html += '<div class="notif-card' + (isUnread ? ' unread' : '') + '" data-id="' + n.id + '">';
    html += '<div class="row-top">';
    html += '<div class="notif-icon ' + iconType + '">';
    if (iconType === 'alert') {
      html += '<svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>';
    } else if (iconType === 'ticket') {
      html += '<svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>';
    } else {
      html += '<svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>';
    }
    html += '</div>';
    html += '<div class="notif-body">';
    html += '<div class="notif-title">' + (isUnread ? '<span class="unread-dot"></span>' : '') + escapeHtml(n.titulo || n.title || 'Notificación') + '</div>';
    html += '<div class="notif-message">' + escapeHtml(n.mensaje || n.message || '') + '</div>';
    html += '<div class="notif-meta">';
    html += '<svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/></svg>';
    html += '<span title="' + formatDate(n.created_at) + '">' + timeAgo(n.created_at) + '</span>';
    if (n.ticket_code) {
      html += '<span>· ' + escapeHtml(n.ticket_code) + '</span>';
    }
    html += '</div>';
    html += '</div>';
    html += '<div class="notif-card-actions">';
    html += '<button class="notif-btn-icon" onclick="irATicket(' + (n.ticket_id || 'null') + ', ' + n.id + ')" title="Ver detalle"><svg viewBox="0 0 24 24" width="17" height="17"><path fill="currentColor" d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/></svg></button>';
    html += '<button class="notif-btn-icon danger" onclick="eliminarNotificacion(' + n.id + ')" title="Eliminar"><svg viewBox="0 0 24 24" width="17" height="17"><path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>';
    html += '</div>';
    html += '</div></div>';
  });
  container.innerHTML = html;
}

function loadNotificaciones() {
  fetch(BASE_URL + 'app/api/notifications.php')
    .then(res => res.json())
    .then(data => {
      window._notifData = data.notifications || [];
      const unreadCount = data.unread_count || 0;
      document.getElementById('badge-all').textContent = (data.notifications || []).length;
      document.getElementById('badge-unread').textContent = unreadCount;
      renderCards(window._notifData);
    })
    .catch(() => {
      document.getElementById('notif-list').innerHTML = '<div class="error-msg">Error al cargar notificaciones</div>';
    });
}

function irATicket(ticketId, notifId) {
  if (ticketId) {
    if (notifId) {
      fetch(BASE_URL + 'app/api/notifications.php?action=mark-read&id=' + notifId, {
        method: 'POST',
        body: 'id=' + notifId,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
      });
    }
    window.location.href = BASE_URL + 'user/ticket-detalle?id=' + ticketId;
  } else if (notifId) {
    fetch(BASE_URL + 'app/api/notifications.php?action=mark-read&id=' + notifId, {
      method: 'POST',
      body: 'id=' + notifId,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    })
      .then(() => {
        showToast('Notificación marcada como leída', 'success');
        loadNotificaciones();
      });
  }
}

function eliminarNotificacion(id) {
  deleteNotifId = id;
  document.getElementById('modal-delete').classList.add('active');
}

function confirmarEliminarNotificacion() {
  if (!deleteNotifId) return;
  fetch(BASE_URL + 'app/api/notifications.php?action=delete-notification', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'id=' + encodeURIComponent(deleteNotifId)
  })
    .then(res => res.json())
    .then(r => {
      if (r.success) { showToast('Notificación eliminada', 'success'); cerrarModal('modal-delete'); deleteNotifId = null; loadNotificaciones(); }
      else { showToast(r.error || 'Error al eliminar', 'error'); }
    })
    .catch(() => { showToast('Error de conexión', 'error'); });
}

function marcarTodasLeidas() {
  fetch(BASE_URL + 'app/api/notifications.php?action=mark-all-read', { method: 'POST' })
    .then(res => res.json())
    .then(r => {
      if (r.success) { showToast('Todas marcadas como leídas', 'success'); loadNotificaciones(); }
    })
    .catch(() => { showToast('Error al marcar', 'error'); });
}

function cerrarModal(id) {
  document.getElementById(id).classList.remove('active');
}

loadNotificaciones();
setInterval(loadNotificaciones, 480000);
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
