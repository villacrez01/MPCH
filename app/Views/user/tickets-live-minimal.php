<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$isOtiAdmin = \App\Services\AuthService::isAdmin();
$tituloPagina = 'Tickets en Vivo - Sistema OTI';
$paginaActual = 'user-tickets-live';
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<script>var BASE_URL = <?= json_encode($baseUrl) ?>;</script>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<main id="main-content" class="main-content">
    <div class="page-header">
        <div class="page-title-group">
            <h1>Tickets en Vivo</h1>
            <p>Actualización automática cada 30 segundos</p>
        </div>
        <div class="last-update dot" id="last-update">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.37-1.9-1.37h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
            <span id="update-text">Última actualización: --</span>
        </div>
    </div>

    <div class="stats-grid stagger-children" id="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="stat-total">--</div>
                <div class="stat-label">Total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h10v2z"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="stat-abiertos">--</div>
                <div class="stat-label">Abiertos</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info">
                <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="stat-proceso">--</div>
                <div class="stat-label">En Proceso</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="stat-resueltos">--</div>
                <div class="stat-label">Resueltos</div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                Lista de Tickets
            </h3>
        </div>
        <div class="card-body">
            <div id="tickets-container">
                <div class="empty-view">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="var(--text-muted)"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                    <div class="empty-title">Cargando tickets...</div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
(function () {
    'use strict';

    function fetchTicketsLive() {
        var container = document.getElementById('tickets-container');
        if (!container) return;

        fetch(BASE_URL + 'app/api/user_tickets_live.php')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.error) {
                    container.innerHTML = '<div class="empty-view"><div class="empty-title">Error al cargar tickets</div></div>';
                    return;
                }
                updateStats(data.stats);
                renderTickets(data.tickets, data.last_update);
            })
            .catch(function () {
                container.innerHTML = '<div class="empty-view"><div class="empty-title">Error de conexión</div></div>';
            });
    }

    function updateStats(stats) {
        if (!stats) return;
        var map = { total: 'stat-total', abiertos: 'stat-abiertos', en_proceso: 'stat-proceso', resueltos: 'stat-resueltos' };
        Object.keys(map).forEach(function (key) {
            var el = document.getElementById(map[key]);
            if (el) el.textContent = stats[key] != null ? stats[key] : 0;
        });
    }

    function renderTickets(tickets, lastUpdate) {
        var container = document.getElementById('tickets-container');
        var updateText = document.getElementById('update-text');
        if (!container) return;

        if (!tickets || tickets.length === 0) {
            container.innerHTML = '<div class="empty-view"><svg viewBox="0 0 24 24" width="48" height="48" fill="var(--text-muted)"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">No tienes tickets</div></div>';
            if (updateText) updateText.textContent = 'Última actualización: ' + (lastUpdate || '--');
            return;
        }

        var html = '<div class="table-wrapper"><table class="tickets-table"><thead><tr><th>Código</th><th>Asunto</th><th>Estado</th><th>Prioridad</th><th>Fecha</th><th></th></tr></thead><tbody>';

        tickets.forEach(function (ticket) {
            var statusClass = getStatusClass(ticket.status_name);
            var priorityClass = getPriorityClass(ticket.priority_name);
            var isNew = isNewTicket(ticket.created_at);

            html += '<tr onclick="window.location.href=\'' + BASE_URL + 'user/ticket-detalle?id=' + ticket.id + '\'">';
            html += '<td><span class="ticket-code">' + escapeHtml(ticket.code) + '</span>';
            if (isNew) html += '<span class="ticket-badge new">Nuevo</span>';
            html += '</td>';
            html += '<td class="ticket-title">' + escapeHtml(ticket.title) + '</td>';
            html += '<td><span class="status-badge ' + statusClass + '">' + escapeHtml(ticket.status_name || 'Abierto') + '</span></td>';
            html += '<td><span class="priority-badge ' + priorityClass + '">' + escapeHtml(ticket.priority_name || 'Media') + '</span></td>';
            html += '<td class="ticket-meta">' + formatDate(ticket.created_at) + '</td>';
            html += '<td><span class="ticket-arrow" aria-hidden="true">›</span></td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
        if (updateText) updateText.textContent = 'Última actualización: ' + (lastUpdate || '--');
    }

    function isNewTicket(createdAt) {
        if (!createdAt) return false;
        return (Date.now() - new Date(createdAt).getTime()) < 5 * 60 * 1000;
    }

    function getStatusClass(status) {
        var map = { 'Abierto': 'abierto', 'En Proceso': 'en-proceso', 'Resuelto': 'resuelto', 'Cerrado': 'cerrado', 'Cancelado': 'cancelado' };
        return map[status] || 'abierto';
    }

    function getPriorityClass(priority) {
        var p = (priority || '').toLowerCase();
        if (p.includes('crítica') || p.includes('critica') || p === 'critical') return 'critica';
        if (p.includes('alta') || p === 'high') return 'alta';
        if (p.includes('baja') || p === 'low') return 'baja';
        return 'media';
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        return new Date(dateStr).toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(text) {
        if (!text) return '';
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    document.addEventListener('DOMContentLoaded', function () {
        fetchTicketsLive();
        setInterval(fetchTicketsLive, 30000);
    });
})();
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
