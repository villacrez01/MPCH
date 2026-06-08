<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$isOtiAdmin = \App\Services\AuthService::isAdmin();
$tituloPagina = $tituloPagina ?? 'Tickets V2 - Sistema OTI';
$paginaActual = $paginaActual ?? 'admin-tickets';
$tickets = $tickets ?? [];
?>
<?php require __DIR__ . '/../../partials/head.php'; ?>
    <style>
        :root {
            --primary: #4338ca;
            --primary-light: #6366f1;
            --primary-soft: #e0e7ff;
            --success: #059669;
            --success-soft: #dcfce7;
            --warning: #d97706;
            --warning-soft: #fef3c7;
            --danger: #dc2626;
            --danger-soft: #fee2e2;
            --info: #0284c7;
            --info-soft: #e0f2fe;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --bg-card: #ffffff;
            --bg-main: #f8fafc;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-full: 9999px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stat-icon svg { width: 22px; height: 22px; fill: currentColor; }
        .stat-icon.total { background: var(--primary-soft); color: var(--primary); }
        .stat-icon.open { background: var(--warning-soft); color: var(--warning); }
        .stat-icon.process { background: var(--info-soft); color: var(--info); }
        .stat-icon.resolved { background: var(--success-soft); color: var(--success); }
        .stat-info { display: flex; flex-direction: column; gap: 2px; }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--text-primary); line-height: 1; }
        .stat-label { font-size: 13px; color: var(--text-muted); }
        .stat-card { transition: all 150ms; cursor: default; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 24px; font-weight: 700; color: var(--text-primary); margin: 0; }
        .page-subtitle { font-size: 14px; color: var(--text-muted); margin: 4px 0 0 0; }

        .filters-row { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-btn {
            padding: 8px 16px;
            border-radius: var(--radius-full);
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 150ms;
            text-decoration: none;
            font-family: inherit;
        }
        .filter-btn:hover { border-color: var(--primary); color: var(--primary); }
        .filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

        .table-wrapper {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            padding: 14px 16px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        td {
            padding: 14px 16px;
            font-size: 14px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }

        .code-cell { font-weight: 600; color: var(--primary); font-size: 13px; }
        .user-cell { display: flex; align-items: center; gap: 8px; }
        .user-avatar {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .date-cell { color: var(--text-muted); font-size: 13px; white-space: nowrap; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.abierto { background: var(--warning-soft); color: var(--warning); }
        .status-badge.en-proceso { background: var(--info-soft); color: var(--info); }
        .status-badge.resuelto { background: var(--success-soft); color: var(--success); }
        .status-badge.cerrado { background: #f1f5f9; color: #64748b; }
        .status-badge.cancelado { background: #f1f5f9; color: #94a3b8; }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 600;
        }
        .priority-badge.critica { background: var(--danger-soft); color: var(--danger); }
        .priority-badge.alta { background: #fef3c7; color: #b45309; }
        .priority-badge.media { background: var(--primary-soft); color: var(--primary); }
        .priority-badge.sin-prioridad { background: #f1f5f9; color: #94a3b8; }
        .priority-badge.baja { background: #f1f5f9; color: #64748b; }

        .action-cell { width: 130px; text-align: center; }
        .action-btn-group { display: flex; align-items: center; gap: 6px; justify-content: center; }

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state svg { width: 64px; height: 64px; fill: var(--text-muted); margin-bottom: 16px; }
        .empty-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px; }
        .empty-text { font-size: 14px; color: var(--text-muted); }

        .pagination-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            background: #f8fafc;
        }
        .pagination-info { font-size: 13px; color: var(--text-muted); }
        .pagination-pages { display: flex; gap: 4px; }
        .page-num {
            width: 34px; height: 34px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: white;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 150ms;
            font-family: inherit;
        }
        .page-num:hover { border-color: var(--primary); color: var(--primary); }
        .page-num.active { background: var(--primary); color: white; border-color: var(--primary); }
        .page-num:disabled { opacity: 0.4; cursor: not-allowed; }

        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 5000;
            padding: 20px;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }
        .modal-overlay.active {
            display: flex;
            animation: modalFadeIn 0.2s ease;
        }
        .modal-overlay.active .modal {
            animation: modalScaleIn 0.2s ease;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes modalScaleIn {
            from { opacity: 0; transform: scale(0.95) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal {
            background: white;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            display: flex;
            flex-direction: column;
        }
        .modal.large { max-width: 900px; }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .modal-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 10px; }
        .modal-title svg { fill: var(--primary); }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-muted);
            cursor: pointer;
            width: 32px; height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
        }
        .modal-close:hover { background: #f1f5f9; color: var(--text-primary); }
        .modal-body { padding: 24px; overflow-y: auto; flex: 1; }
        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            background: #f8fafc;
            flex-shrink: 0;
        }

        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 12px 0;
            grid-column: span 2;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }
        .section-title svg { width: 18px; height: 18px; fill: var(--text-muted); }
        .detail-item { padding: 14px; background: #f8fafc; border-radius: var(--radius-sm); }
        .detail-label { font-size: 12px; color: var(--text-muted); margin-bottom: 4px; }
        .detail-value { font-size: 14px; font-weight: 500; color: var(--text-primary); }
        .detail-full { grid-column: span 2; padding: 14px; background: #f8fafc; border-radius: var(--radius-sm); }
        .detail-full .detail-value { white-space: pre-wrap; }

        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px; }
        .form-select, .form-textarea, .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            color: var(--text-primary);
            background: white;
            box-sizing: border-box;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-select:focus, .form-textarea:focus, .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67,56,202,0.1);
        }
        .form-textarea { min-height: 100px; resize: vertical; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: background 150ms;
            font-size: 14px;
            font-family: inherit;
        }
        .btn-primary:hover { background: #3730a3; }
        .btn-secondary {
            background: white;
            color: var(--text-secondary);
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            font-weight: 500;
            cursor: pointer;
            transition: all 150ms;
            font-size: 14px;
            font-family: inherit;
        }
        .btn-secondary:hover { background: #f1f5f9; }
        .btn-danger {
            background: var(--danger);
            color: white;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: background 150ms;
            font-size: 14px;
            font-family: inherit;
        }
        .btn-danger:hover { background: #b91c1c; }

        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }
        .toast {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            min-width: 300px;
            max-width: 420px;
            pointer-events: auto;
            animation: slideIn 0.3s ease;
            border-left: 4px solid;
        }
        .toast.success { border-color: var(--success); }
        .toast.error { border-color: var(--danger); }
        .toast-icon { width: 20px; height: 20px; flex-shrink: 0; }
        .toast.success .toast-icon { fill: var(--success); }
        .toast.error .toast-icon { fill: var(--danger); }
        .toast-message { font-size: 14px; color: var(--text-primary); font-weight: 500; flex: 1; }
        .toast-close {
            background: none; border: none; color: var(--text-muted);
            cursor: pointer; font-size: 18px; padding: 0;
            width: 20px; height: 20px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 4px;
        }
        .toast-close:hover { background: #f1f5f9; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .form-row { grid-template-columns: 1fr; }
            .detail-grid { grid-template-columns: 1fr; }
            .detail-full, .section-title { grid-column: span 1; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
        }
    </style>
<?php require __DIR__ . '/../../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../../partials/header.php'; ?>

    <main id="main-content" class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Tickets</h1>
                <p class="page-subtitle">Gestión de tickets (V2)</p>
            </div>
            <button class="btn-primary" onclick="recargarTickets()" title="Recargar">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
            </button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg></div>
                <div class="stat-info"><div class="stat-value" id="stat-total">0</div><div class="stat-label">Total</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon open"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h5v2z"/></svg></div>
                <div class="stat-info"><div class="stat-value" id="stat-abiertos">0</div><div class="stat-label">Abiertos</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon process"><svg viewBox="0 0 24 24"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg></div>
                <div class="stat-info"><div class="stat-value" id="stat-proceso">0</div><div class="stat-label">En Proceso</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon resolved"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg></div>
                <div class="stat-info"><div class="stat-value" id="stat-resueltos">0</div><div class="stat-label">Resueltos</div></div>
            </div>
        </div>

        <div class="filters-row">
            <a href="javascript:void(0)" class="filter-btn active" onclick="filtrarEstado('', this)">Todos</a>
            <a href="javascript:void(0)" class="filter-btn" onclick="filtrarEstado('1', this)">Abiertos</a>
            <a href="javascript:void(0)" class="filter-btn" onclick="filtrarEstado('2', this)">En Proceso</a>
            <a href="javascript:void(0)" class="filter-btn" onclick="filtrarEstado('3', this)">Resueltos</a>
            <a href="javascript:void(0)" class="filter-btn" onclick="filtrarEstado('4', this)">Cerrados</a>
        </div>

        <div id="tickets-container">
            <div style="text-align:center;padding:40px;color:var(--text-muted);"><p>Cargando tickets...</p></div>
        </div>
    </main>

    <div class="modal-overlay" id="modal-view">
        <div class="modal large">
            <div class="modal-header">
                <h3 class="modal-title"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg> Detalles del Ticket</h3>
                <button class="modal-close" onclick="cerrarModal('view')">&times;</button>
            </div>
            <div class="modal-body" id="view-content"></div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="cerrarModal('view')">Cerrar</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modal-edit">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg> Responder Ticket</h3>
                <button class="modal-close" onclick="cerrarModal('edit')">&times;</button>
            </div>
            <div class="modal-body" id="edit-content"></div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="cerrarModal('edit')">Cancelar</button>
                <button class="btn-primary" onclick="guardarRespuesta()">Enviar Respuesta</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <script>
    var BASE_URL   = '<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>';
    var pagePerms  = <?= json_encode(getPagePermissions(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var currentStatusFilter = '';
    var currentPage = 1;

    document.addEventListener('DOMContentLoaded', function() {
        recargarTickets();
    });

    function can(perm) {
        return !!(pagePerms && pagePerms[perm]);
    }

    function showToast(message, type) {
        var container = document.getElementById('toast-container');
        var icons = {
            success: '<svg class="toast-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>',
            error: '<svg class="toast-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>'
        };
        var toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.innerHTML = icons[type] + '<span class="toast-message">' + escapeHtml(message) + '</span><button class="toast-close" onclick="this.parentElement.remove()">&times;</button>';
        container.appendChild(toast);
        setTimeout(function() {
            toast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(function() { toast.remove(); }, 300);
        }, 4000);
    }

    function recargarTickets() {
        var container = document.getElementById('tickets-container');
        container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);"><p>Cargando tickets...</p></div>';

        var url = BASE_URL + 'admin/tickets-v2/?action=list&page=' + currentPage + '&pageSize=20';
        if (currentStatusFilter) url += '&status_id=' + currentStatusFilter;

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    renderTickets(res.tickets);
                    renderStats(res.tickets);
                } else {
                    container.innerHTML = '<div class="empty-state"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">Error</div><div class="empty-text">' + escapeHtml(res.error || 'Error al cargar tickets') + '</div></div>';
                }
            })
            .catch(function() {
                container.innerHTML = '<div class="empty-state"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">Error de conexión</div><div class="empty-text">No se pudo conectar con el servidor</div></div>';
            });
    }

    function renderStats(tickets) {
        var total = tickets.length;
        var abiertos = tickets.filter(function(t) { return t.status_id == 1; }).length;
        var proceso = tickets.filter(function(t) { return t.status_id == 2; }).length;
        var resueltos = tickets.filter(function(t) { return t.status_id == 3; }).length;
        document.getElementById('stat-total').innerText = total;
        document.getElementById('stat-abiertos').innerText = abiertos;
        document.getElementById('stat-proceso').innerText = proceso;
        document.getElementById('stat-resueltos').innerText = resueltos;
    }

    function statusClass(id) {
        return ['','abierto','en-proceso','resuelto','cerrado','cancelado'][id] || 'abierto';
    }

    function statusName(id) {
        return ['','Abierto','En Proceso','Resuelto','Cerrado','Cancelado'][id] || 'Abierto';
    }

    function priorityClass(id) {
        return ['','sin-prioridad','baja','media','alta','critica'][id] || 'media';
    }

    function formatDate(d) {
        if (!d) return '-';
        var date = new Date(d);
        return String(date.getDate()).padStart(2,'0') + '/' + String(date.getMonth()+1).padStart(2,'0') + '/' + date.getFullYear() + ' ' + String(date.getHours()).padStart(2,'0') + ':' + String(date.getMinutes()).padStart(2,'0');
    }

    function escapeHtml(text) {
        if (!text) return '';
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function renderTickets(tickets) {
        var container = document.getElementById('tickets-container');
        if (!tickets || tickets.length === 0) {
            container.innerHTML = '<div class="empty-state"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">No hay tickets</div><div class="empty-text">No se encontraron tickets con los filtros seleccionados</div></div>';
            return;
        }

        var html = '<div class="table-wrapper"><table><thead><tr>';
        html += '<th>Código</th><th>Asunto</th><th>Solicitante</th><th>Fecha</th><th>Estado</th><th>Prioridad</th><th style="width:155px;">Acciones</th>';
        html += '</tr></thead><tbody>';

        tickets.forEach(function(t) {
            var name = (t.user_name || '') + ' ' + (t.user_lastname || '');
            var initial = name.trim().charAt(0).toUpperCase();
            html += '<tr>';
            html += '<td><span class="code-cell">' + escapeHtml(t.code || '') + '</span></td>';
            html += '<td>' + escapeHtml(t.title || '') + '</td>';
            html += '<td><div class="user-cell"><span class="user-avatar">' + initial + '</span><span>' + escapeHtml(name.trim()) + '</span></div></td>';
            html += '<td class="date-cell">' + formatDate(t.created_at) + '</td>';
            html += '<td><span class="status-badge ' + statusClass(t.status_id) + '">' + statusName(t.status_id) + '</span></td>';
            html += '<td><span class="priority-badge ' + priorityClass(t.priority_id) + '">' + escapeHtml(t.priority_name || 'Media') + '</span></td>';
            html += '<td class="action-cell">';
            if (can('tickets:view'))
                html += '<button class="action-btn sm view" onclick="verTicket(\'' + t.id + '\')" title="Ver"><i data-lucide="eye"></i></button>';
            if (can('tickets:edit'))
                html += '<button class="action-btn sm edit" onclick="editarTicket(\'' + t.id + '\')" title="Editar"><i data-lucide="pencil"></i></button>';
            if (can('tickets:delete'))
                html += '<button class="action-btn sm delete" onclick="eliminarTicket(\'' + t.id + '\')" title="Eliminar"><i data-lucide="trash-2"></i></button>';
            html += '</td></tr>';
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') { lucide.createIcons(); }
    }

    function filtrarEstado(status, btn) {
        currentStatusFilter = status;
        currentPage = 1;
        document.querySelectorAll('.filters-row .filter-btn').forEach(function(b) { b.classList.remove('active'); });
        if (btn) btn.classList.add('active');
        recargarTickets();
    }

    function cerrarModal(tipo) {
        document.getElementById('modal-' + tipo).classList.remove('active');
    }

    function verTicket(id) {
        var modal = document.getElementById('modal-view');
        var content = document.getElementById('view-content');
        modal.classList.add('active');
        content.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">Cargando...</div>';

        fetch(BASE_URL + 'admin/tickets-v2/?action=get&id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(t) {
                if (t.error) {
                    content.innerHTML = '<p style="color:var(--danger);">' + escapeHtml(t.error) + '</p>';
                    return;
                }
                var hrs = t.resolution_time_hours || 0;
                var tiempo = 'Sin estimar';
                if (hrs > 0) {
                    if (hrs % 720 === 0) tiempo = (hrs/720) + ' mes(es)';
                    else if (hrs % 168 === 0) tiempo = (hrs/168) + ' semana(s)';
                    else if (hrs % 24 === 0) tiempo = (hrs/24) + ' día(s)';
                    else tiempo = hrs + ' hora(s)';
                }
                var resp = (t.assigned_name || '') + ' ' + (t.assigned_lastname || '');
                var html = '<div class="detail-grid">';
                html += '<div class="section-title"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>Información del Ticket</div>';
                html += '<div class="detail-item"><div class="detail-label">Código</div><div class="detail-value" style="color:var(--primary);font-weight:600;">' + escapeHtml(t.code || '-') + '</div></div>';
                html += '<div class="detail-item"><div class="detail-label">Estado</div><div class="detail-value"><span class="status-badge ' + statusClass(t.status_id) + '">' + statusName(t.status_id) + '</span></div></div>';
                html += '<div class="detail-item"><div class="detail-label">Prioridad</div><div class="detail-value"><span class="priority-badge ' + priorityClass(t.priority_id) + '">' + escapeHtml(t.priority_name || 'Media') + '</span></div></div>';
                html += '<div class="detail-item"><div class="detail-label">Tiempo Estimado</div><div class="detail-value">' + escapeHtml(tiempo) + '</div></div>';
                html += '<div class="detail-item"><div class="detail-label">Responsable</div><div class="detail-value">' + escapeHtml(resp.trim() || 'Sin asignar') + '</div></div>';
                html += '<div class="detail-full"><div class="detail-label">Asunto</div><div class="detail-value" style="font-weight:600;">' + escapeHtml(t.title || '-') + '</div></div>';
                html += '<div class="detail-full"><div class="detail-label">Descripción</div><div class="detail-value">' + escapeHtml(t.description || '-') + '</div></div>';
                html += '<div class="section-title"><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>Solicitante</div>';
                html += '<div class="detail-item"><div class="detail-label">Nombre</div><div class="detail-value">' + escapeHtml((t.user_name || '') + ' ' + (t.user_lastname || '')) + '</div></div>';
                html += '<div class="detail-item"><div class="detail-label">Email</div><div class="detail-value">' + escapeHtml(t.user_email || '-') + '</div></div>';
                html += '<div class="detail-item"><div class="detail-label">Teléfono</div><div class="detail-value">' + escapeHtml(t.user_phone || '-') + '</div></div>';
                html += '<div class="detail-item"><div class="detail-label">Ubicación</div><div class="detail-value">' + escapeHtml(t.location_name || '-') + '</div></div>';
                html += '<div class="detail-item"><div class="detail-label">Categoría</div><div class="detail-value">' + escapeHtml(t.category_name || '-') + '</div></div>';
                html += '</div>';
                content.innerHTML = html;
            })
            .catch(function() {
                content.innerHTML = '<p style="color:var(--danger);">Error al cargar datos</p>';
            });
    }

    function editarTicket(id) {
        document.querySelectorAll('.ticket-actions-menu.show').forEach(function(el) { el.classList.remove('show'); });
        var modal = document.getElementById('modal-edit');
        var content = document.getElementById('edit-content');
        modal.classList.add('active');
        content.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">Cargando formulario...</div>';

        var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        var p1 = fetch(BASE_URL + 'admin/tickets-v2/?action=get&id=' + id).then(function(r) { return r.json(); });
        var p2 = fetch(BASE_URL + 'admin/tickets-v2/?action=priorities').then(function(r) { return r.json(); });

        Promise.all([p1, p2])
            .then(function(results) {
                var ticket = results[0];
                var priorities = results[1];

                if (ticket.error) {
                    content.innerHTML = '<p style="color:var(--danger);">' + escapeHtml(ticket.error) + '</p>';
                    return;
                }

                var states = [
                    { value: 'abierto', text: 'Abierto', selected: ticket.status_id == 1 },
                    { value: 'en_proceso', text: 'En Proceso', selected: ticket.status_id == 2 },
                    { value: 'resuelto', text: 'Resuelto', selected: ticket.status_id == 3 },
                    { value: 'cerrado', text: 'Cerrado', selected: ticket.status_id == 4 }
                ];

                var hrs = ticket.resolution_time_hours || 0;
                var val = hrs;
                var unit = 'horas';
                if (hrs > 0) {
                    if (hrs % 720 === 0) { val = hrs/720; unit = 'meses'; }
                    else if (hrs % 168 === 0) { val = hrs/168; unit = 'semanas'; }
                    else if (hrs % 24 === 0) { val = hrs/24; unit = 'dias'; }
                }

                var html = '<input type="hidden" id="edit-ticket-id" value="' + id + '">';
                html += '<div class="form-row"><div class="form-group"><label class="form-label">Estado:</label><select id="edit-estado" class="form-select">';
                states.forEach(function(s) {
                    html += '<option value="' + s.value + '"' + (s.selected ? ' selected' : '') + '>' + s.text + '</option>';
                });
                html += '</select></div>';
                html += '<div class="form-group"><label class="form-label">Prioridad:</label><select id="edit-prioridad" class="form-select">';
                priorities.forEach(function(p) {
                    html += '<option value="' + p.id + '"' + (ticket.priority_id == p.id ? ' selected' : '') + '>' + escapeHtml(p.name) + '</option>';
                });
                html += '</select></div></div>';

                var tokenHtml = '<input type="hidden" name="_token" value="' + token + '">';
                html += '<div class="form-group"><label class="form-label">Tiempo:</label><div class="form-row"><input type="number" id="edit-tiempo-valor" value="' + (val || '') + '" min="1" placeholder="Ej. 3" class="form-input"><select id="edit-tiempo-unidad" class="form-select">';
                var units = ['horas','dias','semanas','meses'];
                units.forEach(function(u) {
                    html += '<option value="' + u + '"' + (unit === u ? ' selected' : '') + '>' + u.charAt(0).toUpperCase() + u.slice(1) + '</option>';
                });
                html += '</select></div></div>';
                html += '<div class="form-group"><label class="form-label">Respuesta / Comentarios:</label><textarea id="edit-respuesta" class="form-textarea" placeholder="Detalles del estado...">' + escapeHtml(ticket.response_message || '') + '</textarea></div>';
                html += '<div style="display:flex;align-items:center;gap:8px;margin-top:12px;background:var(--primary-soft);padding:12px;border-radius:var(--radius-sm);border:1px solid #c7d2fe;">';
                html += '<input type="checkbox" id="edit-enviar-mensaje" value="1" checked style="width:18px;height:18px;cursor:pointer;">';
                html += '<label for="edit-enviar-mensaje" style="font-size:13px;font-weight:500;color:var(--primary);cursor:pointer;">Notificar al usuario</label>';
                html += '</div>' + tokenHtml;
                content.innerHTML = html;
            })
            .catch(function(err) {
                    content.innerHTML = '<p style="color:var(--danger);">Error: ' + escapeHtml(err.message) + '</p>';
            });
    }

    function guardarRespuesta() {
        var btn = document.querySelector('#modal-edit .modal-footer .btn-primary');
        if (btn.disabled) return;
        btn.disabled = true;
        btn.innerHTML = 'Guardando...';

        var id = document.getElementById('edit-ticket-id').value;
        var estado = document.getElementById('edit-estado').value;
        var prioridad = document.getElementById('edit-prioridad').value;
        var tiempo_valor = document.getElementById('edit-tiempo-valor').value;
        var tiempo_unidad = document.getElementById('edit-tiempo-unidad').value;
        var respuesta = document.getElementById('edit-respuesta').value;
        var enviar = document.getElementById('edit-enviar-mensaje').checked ? '1' : '0';
        var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        var params = new URLSearchParams();
        params.append('id', id);
        params.append('estado', estado);
        params.append('prioridad', prioridad);
        params.append('tiempo_valor', tiempo_valor);
        params.append('tiempo_unidad', tiempo_unidad);
        params.append('respuesta', respuesta);
        params.append('enviar_mensaje', enviar);
        params.append('_token', token || '');

        fetch(BASE_URL + 'admin/tickets-v2/?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btn.disabled = false;
            btn.innerHTML = 'Enviar Respuesta';
            if (res.success) {
                showToast('Ticket actualizado', 'success');
                cerrarModal('edit');
                recargarTickets();
            } else {
                    showToast('Error: ' + escapeHtml(res.error || 'No se pudo actualizar'), 'error');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = 'Enviar Respuesta';
            showToast('Error de conexión', 'error');
        });
    }

    function eliminarTicket(id) {
        if (!confirm('¿Eliminar este ticket? Esta acción no se puede deshacer.')) return;
        var btn = document.querySelector('.action-btn.delete[onclick*="eliminarTicket(' + id + ')"]');
        if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; }
        fetch(BASE_URL + 'admin/tickets-v2/?action=delete&id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (btn) { btn.disabled = false; btn.style.opacity = ''; }
                if (res.success) {
                    showToast('Ticket eliminado', 'success');
                    recargarTickets();
                } else {
                    showToast('Error: ' + escapeHtml(res.error || 'No se pudo eliminar'), 'error');
                }
            })
            .catch(function() {
                if (btn) { btn.disabled = false; btn.style.opacity = ''; }
                showToast('Error de conexión', 'error');
            });
    }
    </script>
    <script src="<?= $baseUrl ?>public/assets/js/search.js"></script>
</body>
</html>
