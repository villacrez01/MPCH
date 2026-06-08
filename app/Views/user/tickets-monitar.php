<?php
/**
 * Vista de Tickets - Monitoreo en Vivo
 * Sistema OTI - Diseño enfocado en monitoreo con actualización automática
 */

$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$isOtiAdmin = \App\Services\AuthService::isAdmin();

$tituloPagina = 'Monitoreo de Tickets - Sistema OTI';
$paginaActual = 'user-tickets-monitar';
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<style>
    .page-header-monitor {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }
    .header-left h1 { margin: 0; }
    .last-update {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: var(--primary-soft);
        border-radius: var(--radius-sm);
        font-size: 13px;
        color: var(--primary);
    }
    .last-update.dot::before {
        content: '';
        width: 8px;
        height: 8px;
        background: var(--primary);
        border-radius: 50%;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    .tickets-table-monitor { width: 100%; border-collapse: collapse; }
    .tickets-table-monitor th, .tickets-table-monitor td {
        padding: 12px 16px;
        text-align: left;
        border-bottom: 1px solid var(--border);
        font-size: 14px;
    }
    .tickets-table-monitor th {
        background: #f8fafc;
        font-weight: 600;
        color: var(--text-muted);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .tickets-table-monitor tbody tr:hover { background: #f8fafc; }
    .ticket-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: var(--radius-full);
        font-size: 11px;
        font-weight: 600;
        background: var(--warning-soft);
        color: var(--warning);
    }
    .ticket-badge.new { background: var(--danger-soft); color: var(--danger); }
    .empty-monitor { text-align: center; padding: 80px 20px; }
    .empty-monitor svg { width: 80px; height: 80px; fill: var(--text-muted); margin-bottom: 16px; }
</style>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<main id="main-content" class="main-content">
    <div class="page-header page-header-monitor">
        <div class="header-left">
            <h1>Monitoreo de Tickets</h1>
            <p class="page-subtitle">Actualización automática cada 30 segundos</p>
        </div>
        <div class="header-right">
            <div class="last-update dot" id="last-update">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.37-1.9-1.37h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                <span id="update-text">Última actualización: --</span>
            </div>
        </div>
    </div>

    <div class="stats-grid" id="stats-grid" style="margin-bottom: 24px;">
        <div class="stat-card primary">
            <div class="stat-icon primary">
                <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="stat-total">--</div>
                <div class="stat-label">Total de Tickets</div>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon warning">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h10v2z"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="stat-abiertos">--</div>
                <div class="stat-label">Abiertos</div>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon info">
                <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="stat-proceso">--</div>
                <div class="stat-label">En Proceso</div>
            </div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon success">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="stat-resueltos">--</div>
                <div class="stat-label">Resueltos</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                Lista de Tickets
            </h3>
        </div>
        <div class="card-body">
            <div id="tickets-container">
                <div class="empty-monitor">
                    <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                    <div class="empty-title">Cargando tickets...</div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
var BASE_URL = window.location.origin + '/OTI/';
var refreshInterval = null;

function fetchTicketsLive() {
    var container = document.getElementById('tickets-container');
    var updateText = document.getElementById('update-text');
    
    fetch(BASE_URL + 'app/api/user_tickets_live.php')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.error) {
                container.innerHTML = '<div class="empty-monitor"><div class="empty-title">Error al cargar tickets</div></div>';
                return;
            }
            
            updateStats(data.stats);
            renderTickets(data.tickets, data.last_update);
        })
        .catch(function(err) {
            container.innerHTML = '<div class="empty-monitor"><div class="empty-title">Error de conexión</div></div>';
        });
}

function updateStats(stats) {
    document.getElementById('stat-total').textContent = stats.total || 0;
    document.getElementById('stat-abiertos').textContent = stats.abiertos || 0;
    document.getElementById('stat-proceso').textContent = stats.en_proceso || 0;
    document.getElementById('stat-resueltos').textContent = stats.resueltos || 0;
}

function renderTickets(tickets, lastUpdate) {
    var container = document.getElementById('tickets-container');
    var updateText = document.getElementById('update-text');
    
    if (!tickets || tickets.length === 0) {
        container.innerHTML = '<div class="empty-monitor"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">No tienes tickets</div></div>';
        updateText.textContent = 'Última actualización: ' + (lastUpdate || '--');
        return;
    }
    
    var now = new Date();
    var html = '<table class="tickets-table-monitor"><thead><tr><th>Código</th><th>Asunto</th><th>Estado</th><th>Prioridad</th><th>Fecha</th><th style="width: 50px;"></th></tr></thead><tbody>';
    
    tickets.forEach(function(ticket, index) {
        var statusClass = getStatusClass(ticket.status_name);
        var priorityClass = getPriorityClass(ticket.priority_name);
        var isNew = isNewTicket(ticket.created_at);
        
        html += '<tr onclick="window.location.href=\'' + BASE_URL + 'user/ticket-detalle?id=' + ticket.id + '\'">';
        html += '<td><span class="ticket-code-cell">' + escapeHtml(ticket.code) + '</span>' + (isNew ? '<span class="ticket-badge new">Nuevo</span>' : '') + '</td>';
        html += '<td class="ticket-title-cell">' + escapeHtml(ticket.title) + '</td>';
        html += '<td><span class="status-badge ' + statusClass + '">' + escapeHtml(ticket.status_name || 'Abierto') + '</span></td>';
        html += '<td><span class="priority-badge ' + priorityClass + '">' + (ticket.priority_name || 'Media') + '</span></td>';
        html += '<td class="ticket-date-cell">' + formatDate(ticket.created_at) + '</td>';
        html += '<td><div class="row-arrow"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg></div></td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    container.innerHTML = html;
    updateText.textContent = 'Última actualización: ' + (lastUpdate || '--');
}

function isNewTicket(createdAt) {
    if (!createdAt) return false;
    var created = new Date(createdAt);
    var now = new Date();
    var diffMs = now - created;
    var diffMins = Math.floor(diffMs / 60000);
    return diffMins < 5;
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
    var date = new Date(dateStr);
    return date.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(text) {
    if (!text) return '';
    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

document.addEventListener('DOMContentLoaded', function() {
    fetchTicketsLive();
    refreshInterval = setInterval(fetchTicketsLive, 30000);
});

window.addEventListener('beforeunload', function() {
    if (refreshInterval) clearInterval(refreshInterval);
});
</script>
<script src="<?= htmlspecialchars($baseUrl) ?>public/assets/js/realtime.js"></script>
<?php require __DIR__ . '/../partials/footer.php'; ?>