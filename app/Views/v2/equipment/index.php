<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$isOtiAdmin = \App\Services\AuthService::isAdmin();
$tituloPagina = $tituloPagina ?? 'Equipos V2 - Sistema OTI';
$paginaActual = $paginaActual ?? 'admin-equipos-v2';
?>
<?php require __DIR__ . '/../../partials/head.php'; ?>
    <style>
        :root {
            --primary: #4338ca; --primary-soft: #e0e7ff; --success: #059669; --success-soft: #dcfce7;
            --warning: #d97706; --warning-soft: #fef3c7; --danger: #dc2626; --danger-soft: #fee2e2;
            --info: #0284c7; --info-soft: #e0f2fe; --text-primary: #0f172a; --text-secondary: #475569;
            --text-muted: #94a3b8; --border: #e2e8f0; --bg-card: #ffffff; --bg-main: #f8fafc;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05); --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px; --radius-full: 9999px;
        }
        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 16px 20px; border: 1px solid var(--border); text-align: center; box-shadow: var(--shadow-sm); }
        .stat-value { font-size: 26px; font-weight: 700; color: var(--text-primary); line-height: 1; }
        .stat-label { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .stat-card.total { border-top: 3px solid var(--primary); }
        .stat-card.active { border-top: 3px solid var(--success); }
        .stat-card.maintenance { border-top: 3px solid var(--warning); }
        .stat-card.inactive { border-top: 3px solid var(--text-muted); }
        .stat-card.retired { border-top: 3px solid var(--danger); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 24px; font-weight: 700; color: var(--text-primary); margin: 0; }
        .page-subtitle { font-size: 14px; color: var(--text-muted); margin: 4px 0 0 0; }
        .table-wrapper { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 16px; font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; background: #f8fafc; border-bottom: 1px solid var(--border); white-space: nowrap; }
        td { padding: 14px 16px; font-size: 14px; color: var(--text-primary); border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }
        .badge { display: inline-flex; padding: 4px 10px; border-radius: var(--radius-full); font-size: 12px; font-weight: 600; }
        .badge.active { background: var(--success-soft); color: var(--success); }
        .badge.maintenance { background: var(--warning-soft); color: var(--warning); }
        .badge.inactive { background: #f1f5f9; color: #64748b; }
        .badge.retired { background: var(--danger-soft); color: var(--danger); }
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state svg { width: 64px; height: 64px; fill: var(--text-muted); margin-bottom: 16px; }
        .empty-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px; }
        .empty-text { font-size: 14px; color: var(--text-muted); }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2,1fr); } }
    </style>
<?php require __DIR__ . '/../../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../../partials/header.php'; ?>

    <main id="main-content" class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Equipos</h1>
                <p class="page-subtitle">Gestión de equipos (V2)</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card total"><div class="stat-value" id="stat-total">0</div><div class="stat-label">Total</div></div>
            <div class="stat-card active"><div class="stat-value" id="stat-activos">0</div><div class="stat-label">Activos</div></div>
            <div class="stat-card maintenance"><div class="stat-value" id="stat-mantenimiento">0</div><div class="stat-label">Mantenimiento</div></div>
            <div class="stat-card inactive"><div class="stat-value" id="stat-inactivos">0</div><div class="stat-label">Inactivos</div></div>
            <div class="stat-card retired"><div class="stat-value" id="stat-retirados">0</div><div class="stat-label">Retirados</div></div>
        </div>

        <div id="equipment-container">
            <div style="text-align:center;padding:40px;color:var(--text-muted);"><p>Cargando equipos...</p></div>
        </div>
    </main>

    <script>
    var BASE_URL   = '<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>';
    var pagePerms  = <?= json_encode(getPagePermissions(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    document.addEventListener('DOMContentLoaded', function() { recargar(); });

    function escapeHtml(t) { if (!t) return ''; var m={'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}; return t.toString().replace(/[&<>"']/g,function(c){return m[c];}); }

    function statusClass(s) { return ['', 'active', 'maintenance', 'inactive', 'retired'].includes(s) ? s : 'active'; }
    function statusLabel(s) { var m = { active: 'Activo', maintenance: 'Mantenimiento', inactive: 'Inactivo', retired: 'Retirado' }; return m[s] || 'Activo'; }

    function recargar() {
        var container = document.getElementById('equipment-container');
        container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);"><p>Cargando...</p></div>';

        fetch(BASE_URL + 'admin/equipos-v2/?action=list')
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.success) {
                    container.innerHTML = '<div class="empty-state"><div class="empty-title">Error</div><div class="empty-text">' + escapeHtml(res.error || '') + '</div></div>';
                    return;
                }

                var s = res.stats;
                document.getElementById('stat-total').innerText = s.total;
                document.getElementById('stat-activos').innerText = s.activos;
                document.getElementById('stat-mantenimiento').innerText = s.mantenimiento;
                document.getElementById('stat-inactivos').innerText = s.inactivos;
                document.getElementById('stat-retirados').innerText = s.retirados;

                var items = res.equipment;
                if (!items || items.length === 0) {
                    container.innerHTML = '<div class="empty-state"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">No hay equipos</div></div>';
                    return;
                }

                var html = '<div class="table-wrapper"><table><thead><tr><th>Nombre</th><th>Tipo</th><th>Serial</th><th>Ubicación</th><th>Estado</th><th>Asignado a</th></tr></thead><tbody>';
                items.forEach(function(e) {
                    var assigned = (e.assigned_user_name || '') + ' ' + (e.assigned_user_lastname || '');
                    html += '<tr>';
                    html += '<td><strong>' + escapeHtml(e.name || '') + '</strong></td>';
                    html += '<td>' + escapeHtml(e.asset_type || '-') + '</td>';
                    html += '<td>' + escapeHtml(e.serial_number || '-') + '</td>';
                    html += '<td>' + escapeHtml(e.location_name || '-') + '</td>';
                    html += '<td><span class="badge ' + statusClass(e.status) + '">' + statusLabel(e.status) + '</span></td>';
                    html += '<td>' + escapeHtml(assigned.trim() || 'Sin asignar') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                container.innerHTML = html;
            })
            .catch(function() {
                container.innerHTML = '<div class="empty-state"><div class="empty-title">Error de conexión</div></div>';
            });
    }
    </script>
</body>
</html>
