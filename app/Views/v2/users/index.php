<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$isOtiAdmin = \App\Services\AuthService::isAdmin();
$tituloPagina = $tituloPagina ?? 'Usuarios V2 - Sistema OTI';
$paginaActual = $paginaActual ?? 'admin-usuarios-v2';
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
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 28px; }
        .stat-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 20px 24px; border: 1px solid var(--border); display: flex; align-items: center; gap: 16px; box-shadow: var(--shadow-sm); }
        .stat-icon { width: 48px; height: 48px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .stat-icon svg { width: 22px; height: 22px; fill: currentColor; }
        .stat-icon.total { background: var(--primary-soft); color: var(--primary); }
        .stat-icon.active { background: var(--success-soft); color: var(--success); }
        .stat-icon.inactive { background: var(--danger-soft); color: var(--danger); }
        .stat-info { display: flex; flex-direction: column; gap: 2px; }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--text-primary); line-height: 1; }
        .stat-label { font-size: 13px; color: var(--text-muted); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 24px; font-weight: 700; color: var(--text-primary); margin: 0; }
        .page-subtitle { font-size: 14px; color: var(--text-muted); margin: 4px 0 0 0; }
        .table-wrapper { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 16px; font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; background: #f8fafc; border-bottom: 1px solid var(--border); white-space: nowrap; }
        td { padding: 14px 16px; font-size: 14px; color: var(--text-primary); border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }
        .badge { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: var(--radius-full); font-size: 12px; font-weight: 600; }
        .badge.active { background: var(--success-soft); color: var(--success); }
        .badge.inactive { background: #f1f5f9; color: #64748b; }
        .badge.admin { background: var(--primary-soft); color: var(--primary); }
        .avatar { width: 30px; height: 30px; border-radius: 50%; background: var(--primary-soft); color: var(--primary); font-size: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .user-cell { display: flex; align-items: center; gap: 8px; }
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
                <h1 class="page-title">Usuarios</h1>
                <p class="page-subtitle">Gestión de usuarios (V2)</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total"><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></div>
                <div class="stat-info"><div class="stat-value" id="stat-total">0</div><div class="stat-label">Total</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon active"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg></div>
                <div class="stat-info"><div class="stat-value" id="stat-activos">0</div><div class="stat-label">Activos</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon inactive"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h10v2z"/></svg></div>
                <div class="stat-info"><div class="stat-value" id="stat-inactivos">0</div><div class="stat-label">Inactivos</div></div>
            </div>
        </div>

        <div id="users-container">
            <div style="text-align:center;padding:40px;color:var(--text-muted);"><p>Cargando usuarios...</p></div>
        </div>
    </main>

    <script>
    var BASE_URL   = '<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>';
    var pagePerms  = <?= json_encode(getPagePermissions(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    document.addEventListener('DOMContentLoaded', function() { recargar(); });

    function escapeHtml(t) { if (!t) return ''; var m={'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}; return t.toString().replace(/[&<>"']/g,function(c){return m[c];}); }

    function recargar() {
        var container = document.getElementById('users-container');
        container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);"><p>Cargando...</p></div>';

        fetch(BASE_URL + 'admin/usuarios-v2/?action=list')
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.success) {
                    container.innerHTML = '<div class="empty-state"><div class="empty-title">Error</div><div class="empty-text">' + (res.error || '') + '</div></div>';
                    return;
                }
                document.getElementById('stat-total').innerText = res.stats.total;
                document.getElementById('stat-activos').innerText = res.stats.activos;
                document.getElementById('stat-inactivos').innerText = res.stats.inactivos;

                var users = res.users;
                if (!users || users.length === 0) {
                    container.innerHTML = '<div class="empty-state"><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><div class="empty-title">No hay usuarios</div></div>';
                    return;
                }

                var html = '<div class="table-wrapper"><table><thead><tr><th>Usuario</th><th>Email</th><th>Rol</th><th>Estado</th><th>Administrador</th></tr></thead><tbody>';
                users.forEach(function(u) {
                    var name = (u.nombre || '') + ' ' + (u.apellidos || '');
                    var initial = name.trim().charAt(0).toUpperCase();
                    var statusClass = u.activo === true || u.activo === 't' || u.activo === '1' ? 'active' : 'inactive';
                    var statusText = statusClass === 'active' ? 'Activo' : 'Inactivo';
                    html += '<tr>';
                    html += '<td><div class="user-cell"><span class="avatar">' + initial + '</span><span>' + escapeHtml(name.trim()) + '</span></div></td>';
                    html += '<td>' + escapeHtml(u.email || '-') + '</td>';
                    html += '<td>' + escapeHtml(u.role_name || u.position_name || '-') + '</td>';
                    html += '<td><span class="badge ' + statusClass + '">' + statusText + '</span></td>';
                    html += '<td>' + ((u.es_admin === true || u.es_admin === 't' || u.es_admin === '1') ? '<span class="badge admin">Administrador</span>' : '-') + '</td>';
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
