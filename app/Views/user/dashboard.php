<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$isOtiAdmin = \App\Services\AuthService::isAdmin();

$ticketsUser = \App\Models\Ticket::getByUserId($userId);
$statsUser = \App\Models\Ticket::getStats(['user_id' => $userId]);
$equiposAsignados = \App\Models\User::getAssignedEquipment($userId);
$tituloPagina = 'Principal - Sistema OTI';
$paginaActual = 'user-dashboard';
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<script>
var BASE_URL = window.location.origin + '/OTI/';
var liveApiEndpoint = BASE_URL + 'app/api/user_tickets_live.php';
</script>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<main id="main-content" class="main-content page-dashboard">
  <div class="page-header">
    <div class="page-title-group">
      <h1>Bienvenido, <?= htmlspecialchars(explode(' ', $userName)[0]) ?></h1>
      <p>Panel de control &middot; <?= htmlspecialchars($officeName) ?></p>
    </div>
    <a href="<?= $baseUrl ?>user/reportar" class="btn-reportar">
      <i data-lucide="wrench" aria-hidden="true"></i>
      Reportar Incidencia
    </a>
  </div>

  <div class="stats-grid stagger-children">
    <a href="<?= $baseUrl ?>user/tickets" class="stat-card">
      <div class="stat-icon primary">
        <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
      </div>
      <div class="stat-content">
        <div class="stat-value" id="stat-total"><?= $statsUser['total'] ?? 0 ?></div>
        <div class="stat-label">Mis Tickets</div>
      </div>
    </a>
    <a href="<?= $baseUrl ?>user/tickets?status=abiertos" class="stat-card">
      <div class="stat-icon warning">
        <svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
      </div>
      <div class="stat-content">
        <div class="stat-value" id="stat-abiertos"><?= $statsUser['abiertos'] ?? 0 ?></div>
        <div class="stat-label">Abiertos</div>
      </div>
    </a>
    <a href="<?= $baseUrl ?>user/tickets?status=proceso" class="stat-card">
      <div class="stat-icon info">
        <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
      </div>
      <div class="stat-content">
        <div class="stat-value" id="stat-proceso"><?= $statsUser['en_proceso'] ?? 0 ?></div>
        <div class="stat-label">En Proceso</div>
      </div>
    </a>
    <a href="<?= $baseUrl ?>user/tickets?status=resueltos" class="stat-card">
      <div class="stat-icon success">
        <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
      </div>
      <div class="stat-content">
        <div class="stat-value" id="stat-resueltos"><?= $statsUser['resueltos'] ?? 0 ?></div>
        <div class="stat-label">Resueltos</div>
      </div>
    </a>
  </div>

  <div class="content-grid">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
          Mis Tickets Recientes
        </h3>
        <a href="<?= $baseUrl ?>user/tickets" class="ver-todos">Ver todos &rarr;</a>
      </div>
      <div class="card-body">
        <div class="tickets-list" id="tickets-list-live">
          <?php if (empty($ticketsUser)): ?>
          <div class="empty-state">
            <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
            <div class="empty-title">No tienes tickets</div>
            <div class="empty-text">¿Tienes alg&uacute;n problema? Crea un nuevo ticket</div>
          </div>
          <?php else: ?>
            <?php foreach (array_slice($ticketsUser, 0, 5) as $ticket): ?>
            <div class="ticket-item" onclick="window.location.href='<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>user/ticket-detalle?id=<?= $ticket['id'] ?>'">
              <div class="ticket-status <?= strtolower(str_replace(' ', '-', $ticket['status_name'] ?? 'abierto')) ?>"></div>
              <div class="ticket-info">
                <div class="ticket-code"><?= htmlspecialchars($ticket['code']) ?></div>
                <div class="ticket-title"><?= htmlspecialchars($ticket['title']) ?></div>
                <div class="ticket-meta">
                  <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                  <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                </div>
              </div>
              <span class="ticket-priority <?= strtolower(str_replace(' ', '-', str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $ticket['priority_name'] ?? 'media'))) ?>"><?= $ticket['priority_name'] ?? 'Media' ?></span>
              <span class="ticket-arrow">&rarr;</span>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4z"/></svg>
          Mis Equipos
        </h3>
      </div>
      <div class="card-body">
        <?php if (empty($equiposAsignados)): ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4z"/></svg>
          <div class="empty-title">No tienes equipos asignados</div>
        </div>
        <?php else: ?>
        <div class="equipos-list">
          <?php foreach ($equiposAsignados as $eq): ?>
          <div class="equipo-item">
            <div class="equipo-icon">
              <svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4z"/></svg>
            </div>
            <div class="equipo-info">
              <div class="equipo-name"><?= htmlspecialchars($eq['name']) ?></div>
              <div class="equipo-code"><?= htmlspecialchars($eq['patrimonial_code'] ?? $eq['serial_number'] ?? 'Sin código') ?></div>
            </div>
            <a href="<?= $baseUrl ?>user/reportar?equipo_id=<?= $eq['id'] ?>" class="btn-equipo-reportar" title="Reportar incidencia en este equipo">
              <svg viewBox="0 0 24 24"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/></svg>
              Reportar
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
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

<script>
function verTicket(id) {
  var modal = document.getElementById('modal-ver-ticket');
  var body = document.getElementById('ticket-modal-body');
  modal.classList.add('active');
  body.innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted);font-size:14px;">Cargando informaci&oacute;n del ticket...</div>';

  fetch('<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>app/api/user_tickets.php?action=get-ticket&id=' + id)
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.error) { body.innerHTML = '<div style="color:var(--danger);padding:20px;">Error: ' + escapeHtml(data.error) + '</div>'; return; }
      var html = '<div class="detail-grid">';
      html += '<div class="detail-full" style="background:var(--primary-soft);border-left:4px solid var(--primary);margin-bottom:6px;">';
      html += '<div style="color:var(--primary);font-weight:600;font-size:15px;margin-bottom:4px;">&iexcl;Hola, ' + escapeHtml(data.user_name || '') + '!</div>';
      html += '<div style="color:var(--text-secondary);font-size:13px;">Aqu&iacute; puedes revisar todos los detalles y el estado actual de tu solicitud.</div></div>';
      html += '<div class="detail-item"><div class="detail-label">C&oacute;digo</div><div class="detail-value" style="font-weight:600;color:var(--primary);">' + escapeHtml(data.code || '-') + '</div></div>';
      html += '<div class="detail-item"><div class="detail-label">Estado</div><div class="detail-value">' + escapeHtml(data.status_name || 'Abierto') + '</div></div>';
      html += '<div class="detail-full"><div class="detail-label">Asunto</div><div class="detail-value" style="font-weight:600;">' + escapeHtml(data.title || '-') + '</div></div>';
      html += '<div class="detail-full"><div class="detail-label">Descripci&oacute;n</div><div class="detail-value" style="white-space:pre-wrap;">' + escapeHtml(data.description || '-') + '</div></div>';
      var eqStr = data.equipment_name ? data.equipment_name : 'Ninguno';
      if (data.equipment_code) eqStr += ' (' + data.equipment_code + ')';
      html += '<div class="detail-full" style="background:var(--success-soft);border:1px solid #bbf7d0;"><div class="detail-label" style="color:var(--success);font-weight:600;">Equipo Asignado</div><div class="detail-value" style="color:#15803d;font-weight:600;">' + escapeHtml(eqStr) + '</div></div>';
      html += '<div class="detail-item"><div class="detail-label">Nombre</div><div class="detail-value">' + escapeHtml((data.user_name || '') + ' ' + (data.user_lastname || '')) + '</div></div>';
      html += '<div class="detail-item"><div class="detail-label">Email</div><div class="detail-value">' + escapeHtml(data.user_email || '-') + '</div></div>';
      html += '<div class="detail-item"><div class="detail-label">Tel&eacute;fono</div><div class="detail-value">' + escapeHtml(data.user_phone || '-') + '</div></div>';
      html += '<div class="detail-item"><div class="detail-label">Oficina / &Aacute;rea</div><div class="detail-value">' + escapeHtml(data.location_name || '-') + ' (' + escapeHtml(data.area_name || '-') + ')</div></div>';
      if (data.response_message) {
        html += '<div class="detail-full" style="background:var(--warning-soft);border:1px solid #fde68a;">';
        html += '<div class="detail-label" style="color:var(--warning);font-weight:600;">Respuesta de Soporte</div>';
        html += '<div class="detail-value" style="white-space:pre-wrap;margin-top:5px;">' + escapeHtml(data.response_message) + '</div></div>';
      }
      html += '</div>';
      body.innerHTML = html;
    })
    .catch(function() { body.innerHTML = '<div style="color:var(--danger);padding:20px;">Error de conexi&oacute;n. Intente nuevamente.</div>'; });
}

function cerrarModalTicket() { document.getElementById('modal-ver-ticket').classList.remove('active'); }

function escapeHtml(text) {
  if (!text) return '';
  var m = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return text.toString().replace(/[&<>"']/g, function(c) { return m[c]; });
}

function getStatusClass(status) {
  var map = { 'Abierto': 'abierto', 'En Proceso': 'en-proceso', 'Resuelto': 'resuelto', 'Cerrado': 'cerrado', 'Cancelado': 'cancelado' };
  return map[status] || 'abierto';
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
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function renderTicketsList(tickets) {
  var container = document.getElementById('tickets-list-live');
  if (!container) return;
  if (!tickets || tickets.length === 0) {
    container.innerHTML = '<div class="empty-state"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">No tienes tickets</div><div class="empty-text">¿Tienes alg&uacute;n problema? Crea un nuevo ticket</div></div>';
    return;
  }
  var html = '';
  tickets.forEach(function(t) {
    var sc = getStatusClass(t.status_name);
    var pc = getPriorityClass(t.priority_name);
    html += '<div class="ticket-item" onclick="window.location.href=\'' + BASE_URL + 'user/ticket-detalle?id=' + t.id + '\'"><div class="ticket-status ' + sc + '"></div><div class="ticket-info"><div class="ticket-code">' + escapeHtml(t.code) + '</div><div class="ticket-title">' + escapeHtml(t.title) + '</div><div class="ticket-meta"><svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>' + formatDate(t.created_at) + '</div></div><span class="ticket-priority ' + pc + '">' + (t.priority_name || 'Media') + '</span><span class="ticket-arrow">&rarr;</span></div>';
  });
  container.innerHTML = html;
}

function updateDashboardTickets() {
  var container = document.getElementById('tickets-list-live');
  if (!container) return;
  fetch(liveApiEndpoint)
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.error) return;
      renderTicketsList(data.tickets ? data.tickets.slice(0, 5) : []);
    })
    .catch(function() {});
}

document.addEventListener('DOMContentLoaded', function() { updateDashboardTickets(); });
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
