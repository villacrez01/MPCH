<?php
/**
 * Tickets del Administrador - Todos los tickets
 * Diseño profesional con UX mejorada
 */

$baseUrl = base_url();

$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';

$isOtiAdmin = \App\Services\AuthService::isAdmin();

$tituloPagina = 'Todos los Tickets - Sistema OTI';
$paginaActual = 'admin-tickets';

$tickets = $tickets ?? [];
$statusFilter = $_GET['status'] ?? '';
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>
    <style>
        :root {
            --ticket-primary: #1e3f5f;
            --ticket-primary-light: #4a7ba8;
            --ticket-success: #059669;
            --ticket-warning: #d97706;
            --ticket-danger: #dc2626;
            --ticket-info: #0284c7;
            --ticket-text: #0f172a;
            --ticket-text-secondary: #475569;
            --ticket-text-muted: #94a3b8;
            --ticket-border: #e2e8f0;
            --ticket-bg-card: #ffffff;
            --ticket-bg-soft: #f8fafc;
            --ticket-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --ticket-shadow-hover: 0 4px 12px rgba(0,0,0,0.08);
            --ticket-radius: 12px;
            --ticket-radius-sm: 8px;
            --ticket-radius-full: 9999px;
            --ticket-transition: 150ms ease;
        }

        .page-header-left .page-title { font-size: 24px; font-weight: 700; color: var(--ticket-text); margin: 0; }
        .page-header-left .page-subtitle { font-size: 14px; color: var(--ticket-text-muted); margin: 4px 0 0 0; }

        .search-bar-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
        .search-input-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 14px;
            background: var(--ticket-bg-card);
            border: 1.5px solid var(--ticket-border);
            border-radius: var(--ticket-radius);
            transition: all var(--ticket-transition);
            box-shadow: var(--ticket-shadow);
        }
        .search-input-wrapper:focus-within {
            border-color: var(--ticket-primary);
            box-shadow: 0 0 0 3px rgba(67,56,202,0.1), var(--ticket-shadow);
        }
        .search-input-wrapper svg {
            width: 18px; height: 18px;
            fill: var(--ticket-text-muted);
            flex-shrink: 0;
        }
        .search-input-wrapper .search-input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 14px;
            padding: 12px 0;
            font-family: inherit;
            color: var(--ticket-text);
            background: transparent;
        }
        .search-input-wrapper .search-input::placeholder { color: var(--ticket-text-muted); }
        .search-clear {
            display: none;
            align-items: center;
            justify-content: center;
            width: 28px; height: 28px;
            border: none;
            background: #f1f5f9;
            border-radius: 50%;
            cursor: pointer;
            color: var(--ticket-text-muted);
            transition: all var(--ticket-transition);
            flex-shrink: 0;
            padding: 0;
        }
        .search-clear:hover { background: #e2e8f0; color: var(--ticket-text); }
        .search-clear svg { width: 14px; height: 14px; fill: currentColor; }

        .filter-toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--ticket-bg-card);
            border: 1.5px solid var(--ticket-border);
            border-radius: var(--ticket-radius);
            color: var(--ticket-text-secondary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--ticket-transition);
            font-family: inherit;
            white-space: nowrap;
            box-shadow: var(--ticket-shadow);
        }
        .filter-toggle-btn:hover {
            border-color: var(--ticket-primary);
            color: var(--ticket-primary);
            background: #f8faff;
        }
        .filter-toggle-btn svg { fill: currentColor; }
        .filter-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px; height: 20px;
            padding: 0 6px;
            background: var(--ticket-primary);
            color: white;
            border-radius: var(--ticket-radius-full);
            font-size: 11px;
            font-weight: 700;
        }

        .advanced-filters {
            display: none;
            flex-wrap: wrap;
            gap: 16px;
            padding: 20px;
            margin-bottom: 20px;
            background: var(--ticket-bg-card);
            border: 1px solid var(--ticket-border);
            border-radius: var(--ticket-radius);
            box-shadow: var(--ticket-shadow);
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 160px;
        }
        .filter-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--ticket-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .filter-select, .filter-input {
            padding: 9px 12px;
            border: 1.5px solid var(--ticket-border);
            border-radius: var(--ticket-radius-sm);
            font-size: 14px;
            font-family: inherit;
            color: var(--ticket-text);
            background: var(--ticket-bg-card);
            transition: border-color var(--ticket-transition);
            outline: none;
        }
        .filter-select:focus, .filter-input:focus {
            border-color: var(--ticket-primary);
            box-shadow: 0 0 0 3px rgba(67,56,202,0.1);
        }
        .clear-filters-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            background: var(--ticket-bg-card);
            border: 1.5px solid var(--ticket-border);
            border-radius: var(--ticket-radius-sm);
            color: var(--ticket-text-secondary);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            font-family: inherit;
            transition: all var(--ticket-transition);
        }
        .clear-filters-btn:hover {
            border-color: var(--ticket-danger);
            color: var(--ticket-danger);
            background: #fef2f2;
        }
        .clear-filters-btn svg { width: 14px; height: 14px; fill: currentColor; }

        .keyboard-hint {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
            padding: 10px 16px;
            background: #f8fafc;
            border: 1px solid var(--ticket-border);
            border-radius: var(--ticket-radius-sm);
        }
        .keyboard-hint-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--ticket-text-muted);
        }
        .keyboard-hint-item kbd {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            padding: 2px 6px;
            background: var(--ticket-bg-card);
            border: 1px solid var(--ticket-border);
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            color: var(--ticket-text-secondary);
            font-family: inherit;
            box-shadow: 0 1px 0 var(--ticket-border);
        }

        .tickets-table-wrapper {
            background: var(--ticket-bg-card);
            border-radius: var(--ticket-radius);
            border: 1px solid var(--ticket-border);
            box-shadow: var(--ticket-shadow);
            overflow: hidden;
        }
        .tickets-table { width: 100%; border-collapse: collapse; }
        .tickets-table thead th {
            text-align: left;
            padding: 14px 16px;
            font-size: 12px;
            font-weight: 600;
            color: var(--ticket-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #f8fafc;
            border-bottom: 1px solid var(--ticket-border);
            white-space: nowrap;
        }
        .tickets-table tbody td {
            padding: 14px 16px;
            font-size: 14px;
            color: var(--ticket-text);
            border-bottom: 1px solid var(--ticket-border);
            vertical-align: middle;
        }
        .tickets-table tbody tr:last-child td { border-bottom: none; }
        .tickets-table tbody tr {
            transition: background var(--ticket-transition);
        }
        .tickets-table tbody tr:hover td {
            background: #f8fafc;
        }
        .tickets-table tbody tr.selected-row td {
            background: #eef2ff;
        }
        .tickets-table tbody tr.selected-row .ticket-code-cell {
            color: var(--ticket-primary);
        }

        .ticket-code-cell {
            font-weight: 600;
            color: var(--ticket-primary);
            font-size: 13px;
        }
        .ticket-title-cell {
            font-weight: 500;
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ticket-date-cell {
            color: var(--ticket-text-muted);
            font-size: 13px;
            white-space: nowrap;
        }

        .ticket-user-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ticket-user-avatar {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: #eef2ff;
            color: var(--ticket-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            flex-shrink: 0;
        }
        .ticket-user-name {
            font-size: 14px;
            color: var(--ticket-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 160px;
        }

        .action-cell {
            width: 130px;
            text-align: center;
        }
        .action-btn-group { display: flex; align-items: center; gap: 6px; justify-content: center; }

        .filters .filter-btn {
            padding: 8px 18px;
            border-radius: var(--ticket-radius-full);
            border: 1.5px solid var(--ticket-border);
            background: var(--ticket-bg-card);
            color: var(--ticket-text-secondary);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--ticket-transition);
            text-decoration: none;
            font-family: inherit;
        }
        .filters .filter-btn:hover {
            border-color: var(--ticket-primary);
            color: var(--ticket-primary);
            background: #f8faff;
        }
        .filters .filter-btn.active {
            background: var(--ticket-primary);
            color: white;
            border-color: var(--ticket-primary);
        }

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
            border-radius: var(--ticket-radius);
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
            display: flex;
            flex-direction: column;
        }
        .modal.large { max-width: 900px; }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid var(--ticket-border);
            flex-shrink: 0;
        }
        .modal-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
            color: var(--ticket-text);
            margin: 0;
        }
        .modal-title svg { width: 20px; height: 20px; fill: var(--ticket-primary); }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--ticket-text-muted);
            cursor: pointer;
            width: 36px; height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--ticket-radius-sm);
            transition: all var(--ticket-transition);
        }
        .modal-close:hover { background: #f1f5f9; color: var(--ticket-text); }
        .modal-body { padding: 24px; overflow-y: auto; flex: 1; }
        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding: 16px 24px;
            border-top: 1px solid var(--ticket-border);
            background: #f8fafc;
            flex-shrink: 0;
        }

        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .detail-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--ticket-text);
            margin: 0 0 12px 0;
            grid-column: span 2;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--ticket-border);
        }
        .detail-section-title svg { width: 18px; height: 18px; fill: var(--ticket-text-muted); }
        .detail-item { padding: 14px; background: #f8fafc; border-radius: var(--ticket-radius-sm); }
        .detail-label { font-size: 12px; color: var(--ticket-text-muted); margin-bottom: 4px; }
        .detail-value { font-size: 14px; font-weight: 500; color: var(--ticket-text); }
        .detail-description { grid-column: span 2; padding: 14px; background: #f8fafc; border-radius: var(--ticket-radius-sm); }
        .detail-description .detail-value { white-space: pre-wrap; }

        .edit-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .edit-left { padding-right: 20px; border-right: 1px solid var(--ticket-border); }
        .edit-left .detail-section-title { margin-top: 0; border-bottom: 1px solid var(--ticket-border); padding-bottom: 8px; margin-bottom: 12px; }
        .edit-left .detail-item { padding: 10px 14px; }
        .edit-left .detail-description { }
        .edit-right .detail-section-title { margin-top: 0; border-bottom: 1px solid var(--ticket-border); padding-bottom: 8px; margin-bottom: 12px; }

        .detail-card { background: var(--ticket-bg); border: 1px solid var(--ticket-border); border-radius: var(--ticket-radius); padding: 16px; }
        .detail-card-title { font-size: 14px; font-weight: 600; color: var(--ticket-text); display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
        .detail-card-title svg { width: 18px; height: 18px; fill: var(--ticket-text-muted); }

        .timeline { position: relative; padding-left: 36px; }
        .timeline::before {
            content: ''; position: absolute; left: 11px; top: 4px; bottom: 0;
            width: 2px; background: linear-gradient(to bottom, var(--ticket-primary), var(--ticket-border) 80%);
        }
        .timeline-item { position: relative; padding-bottom: 20px; }
        .timeline-item:last-child { padding-bottom: 0; }
        .timeline-dot {
            position: absolute; left: -28px; top: 4px; width: 14px; height: 14px;
            border-radius: 50%; border: 3px solid #fff;
            box-shadow: 0 0 0 2px var(--ticket-primary-soft); z-index: 1;
        }
        .timeline-dot.creado { background: var(--ticket-primary); box-shadow: 0 0 0 2px var(--ticket-primary-soft); }
        .timeline-dot.visto { background: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.2); }
        .timeline-dot.proceso { background: #f59e0b; box-shadow: 0 0 0 2px rgba(245,158,11,0.2); }
        .timeline-dot.resuelto { background: #22c55e; box-shadow: 0 0 0 2px rgba(34,197,94,0.2); }
        .timeline-dot.cerrado { background: #6b7280; box-shadow: 0 0 0 2px rgba(107,114,128,0.2); }
        .timeline-dot.cancelado { background: var(--ticket-danger); box-shadow: 0 0 0 2px rgba(239,68,68,0.2); }
        .timeline-content {
            background: var(--ticket-bg); border-radius: 10px; padding: 14px 16px;
            border: 1px solid var(--ticket-border); transition: border-color 150ms;
        }
        .timeline-content:hover { border-color: var(--ticket-primary-soft); }
        .timeline-title { font-size: 14px; font-weight: 600; color: var(--ticket-text); margin-bottom: 3px; }
        .timeline-desc { font-size: 13px; color: var(--ticket-text-secondary); line-height: 1.5; }
        .timeline-time { font-size: 11px; color: var(--ticket-text-muted); margin-top: 8px; display: flex; align-items: center; gap: 6px; }
        .timeline-time::before { content: ''; width: 3px; height: 3px; border-radius: 50%; background: var(--ticket-border); display: inline-block; }

        @media (max-width: 768px) {
            .edit-layout { grid-template-columns: 1fr; }
            .edit-left { padding-right: 0; border-right: none; border-bottom: 1px solid var(--ticket-border); padding-bottom: 16px; margin-bottom: 16px; }
        }

        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 500; color: var(--ticket-text-secondary); margin-bottom: 6px; }
        .form-select, .form-textarea, .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid var(--ticket-border);
            border-radius: var(--ticket-radius-sm);
            font-size: 14px;
            color: var(--ticket-text);
            background: white;
            box-sizing: border-box;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .form-select:focus, .form-textarea:focus, .form-input:focus {
            border-color: var(--ticket-primary);
            box-shadow: 0 0 0 3px rgba(67,56,202,0.1);
        }
        .form-textarea { min-height: 100px; resize: vertical; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .btn-primary, .btn-secondary, .btn-danger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--ticket-radius-sm);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--ticket-transition);
            font-family: inherit;
            border: none;
            white-space: nowrap;
        }
        .btn-primary {
            background: var(--ticket-primary);
            color: white;
            box-shadow: 0 2px 8px rgba(67,56,202,0.25);
        }
        .btn-primary:hover { background: #3730a3; box-shadow: 0 4px 14px rgba(67,56,202,0.35); transform: translateY(-1px); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-secondary {
            background: white;
            color: var(--ticket-text-secondary);
            border: 1.5px solid var(--ticket-border);
        }
        .btn-secondary:hover { border-color: var(--ticket-primary); color: var(--ticket-primary); background: #f8faff; }
        .btn-danger {
            background: var(--ticket-danger);
            color: white;
        }
        .btn-danger:hover { background: #b91c1c; box-shadow: 0 4px 14px rgba(220,38,38,0.3); transform: translateY(-1px); }

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
            border-radius: var(--ticket-radius-sm);
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            min-width: 300px;
            max-width: 420px;
            pointer-events: auto;
            animation: toastSlideIn 0.3s ease;
            border-left: 4px solid;
        }
        .toast.success { border-color: var(--ticket-success); }
        .toast.error { border-color: var(--ticket-danger); }
        .toast.warning { border-color: var(--ticket-warning); }
        .toast.info { border-color: var(--ticket-info); }
        .toast-icon { width: 20px; height: 20px; flex-shrink: 0; }
        .toast.success .toast-icon { color: var(--ticket-success); }
        .toast.error .toast-icon { color: var(--ticket-danger); }
        .toast.warning .toast-icon { color: var(--ticket-warning); }
        .toast.info .toast-icon { color: var(--ticket-info); }
        .toast-message { font-size: 14px; color: var(--ticket-text); font-weight: 500; flex: 1; }
        .toast-close {
            background: none; border: none; color: var(--ticket-text-muted);
            cursor: pointer; font-size: 18px; padding: 0;
            width: 24px; height: 24px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 4px;
            transition: all var(--ticket-transition);
        }
        .toast-close:hover { background: #f1f5f9; color: var(--ticket-text); }
        @keyframes toastSlideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes toastSlideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        .stats-grid .stat-card {
            cursor: default;
            transition: all var(--ticket-transition);
        }
        .stats-grid .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--ticket-shadow-hover);
        }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .form-row { grid-template-columns: 1fr; }
            .detail-grid { grid-template-columns: 1fr; }
            .detail-description, .detail-section-title { grid-column: span 1; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .search-bar-container { flex-direction: column; }
            .search-input-wrapper { width: 100%; }
            .filter-toggle-btn { width: 100%; justify-content: center; }
            .advanced-filters { flex-direction: column; }
            .filter-group { min-width: 100%; }
            .ticket-title-cell { max-width: 180px; }
            .ticket-user-name { max-width: 100px; }
            .modal { max-width: 100%; margin: 10px; }
            .modal-header { padding: 16px 18px; }
            .modal-body { padding: 18px; }
            .modal-footer { padding: 14px 18px; }
            .keyboard-hint { display: none; }
            .toast { min-width: auto; max-width: calc(100vw - 32px); }
            .toast-container { right: 16px; left: 16px; bottom: 16px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .tickets-table-wrapper { overflow-x: auto; }
            .tickets-table { min-width: 700px; }
        }
    </style>

    <!-- Contenido Principal -->
    <main id="main-content" class="main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h1 class="page-title">Todos los Tickets</h1>
                <p class="page-subtitle">Gestión completa de tickets del sistema</p>
            </div>
            <button class="btn-secondary" onclick="exportTableToCSV('.tickets-table', 'tickets')" aria-label="Exportar tickets a CSV">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15.01l1.41 1.41L11 14.84V19h2v-4.16l1.59 1.59L16 15.01 12 11l-4 4.01z"/></svg>
                Exportar CSV
            </button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="stat-total">0</div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon open">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h5v2z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="stat-abiertos">0</div>
                    <div class="stat-label">Abiertos</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon process">
                    <svg viewBox="0 0 24 24"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="stat-proceso">0</div>
                    <div class="stat-label">En Proceso</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon resolved">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="stat-resueltos">0</div>
                    <div class="stat-label">Resueltos</div>
                </div>
            </div>
        </div>

        <div class="search-bar-container">
            <div class="search-input-wrapper">
                <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" id="search-tickets" class="search-input" placeholder="Buscar por código, título, descripción o usuario..." />
                <button class="search-clear" id="search-clear" onclick="clearSearch()" style="display: none;">
                    <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                </button>
            </div>
            <button class="btn-primary filter-toggle-btn" onclick="toggleAdvancedFilters()">
                <svg viewBox="0 0 24 24" width="18" height="18"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
                Filtros
                <span class="filter-badge" id="filter-badge" style="display: none;">0</span>
            </button>
        </div>

        <div class="advanced-filters" id="advanced-filters" style="display: none;">
            <div class="filter-group">
                <label class="filter-label">Prioridad</label>
                <select id="filter-priority" class="filter-select" onchange="aplicarFiltrosAvanzados()">
                    <option value="">Todas</option>
                    <option value="1">Sin Prioridad</option>
                    <option value="2">Baja</option>
                    <option value="3">Media</option>
                    <option value="4">Alta</option>
                    <option value="5">Crítica</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Fecha desde</label>
                <input type="date" id="filter-date-from" class="filter-input" onchange="aplicarFiltrosAvanzados()" />
            </div>
            <div class="filter-group">
                <label class="filter-label">Fecha hasta</label>
                <input type="date" id="filter-date-to" class="filter-input" onchange="aplicarFiltrosAvanzados()" />
            </div>
            <div class="filter-group">
                <label class="filter-label">Mostrar</label>
                <select id="filter-page-size" class="filter-select" onchange="cambiarPageSize()">
                    <option value="10">10 por página</option>
                    <option value="20" selected>20 por página</option>
                    <option value="50">50 por página</option>
                    <option value="100">100 por página</option>
                </select>
            </div>
            <button class="btn-secondary clear-filters-btn" onclick="clearAllFilters()">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                Limpiar
            </button>
        </div>

        <div class="keyboard-hint">
            <div class="keyboard-hint-item"><kbd>J</kbd> siguiente</div>
            <div class="keyboard-hint-item"><kbd>K</kbd> anterior</div>
            <div class="keyboard-hint-item"><kbd>Enter</kbd> acciones</div>
            <div class="keyboard-hint-item"><kbd>O</kbd> abrir</div>
            <div class="keyboard-hint-item"><kbd>/</kbd> búsqueda</div>
        </div>

        <div class="filters">
            <a href="javascript:void(0)" class="filter-btn active" onclick="filtrarEstado('', this)">Todos</a>
            <a href="javascript:void(0)" class="filter-btn" onclick="filtrarEstado(1, this)">Abiertos</a>
            <a href="javascript:void(0)" class="filter-btn" onclick="filtrarEstado(2, this)">En Proceso</a>
            <a href="javascript:void(0)" class="filter-btn" onclick="filtrarEstado(3, this)">Resueltos</a>
            <a href="javascript:void(0)" class="filter-btn" onclick="filtrarEstado(4, this)">Cerrados</a>
        </div>

        <div id="tickets-container">
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <p>Cargando tickets...</p>
            </div>
        </div>

        <div class="pagination-container" id="pagination-container" style="display: none;">
            <div class="pagination-info" id="pagination-info"></div>
            <div class="pagination-controls">
                <button class="pagination-btn" id="btn-prev" onclick="cambiarPagina(-1)">
                    <svg viewBox="0 0 24 24" width="18" height="18"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                    Anterior
                </button>
                <div class="pagination-pages" id="pagination-pages"></div>
                <button class="pagination-btn" id="btn-next" onclick="cambiarPagina(1)">
                    Siguiente
                    <svg viewBox="0 0 24 24" width="18" height="18"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                </button>
            </div>
        </div>
    </main>

    <!-- Modal Ver Ticket -->
    <div class="modal-overlay" id="modal-ver">
        <div class="modal large">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg viewBox="0 0 24 24" width="22" height="22"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                    Detalles del Ticket
                </h3>
                <button class="modal-close" onclick="cerrarModal('ver')" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body" id="ver-contenido"></div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="cerrarModal('ver')">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal Editar Ticket -->
    <div class="modal-overlay" id="modal-editar">
        <div class="modal large">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg viewBox="0 0 24 24" width="22" height="22"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                    Responder Ticket
                </h3>
                <button class="modal-close" onclick="cerrarModal('editar')" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body" id="editar-contenido"></div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="cerrarModal('editar')">Cancelar</button>
                <button class="btn-primary" onclick="guardarRespuesta()">Enviar Respuesta</button>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar Ticket -->
    <div class="modal-overlay" id="modal-eliminar">
        <div class="modal" style="max-width: 420px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg viewBox="0 0 24 24" width="22" height="22"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                    Confirmar Eliminación
                </h3>
                <button class="modal-close" onclick="cerrarModal('eliminar')" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin: 0; color: var(--text-secondary); font-size: 14px;">¿Estás seguro de que deseas eliminar este ticket? Esta acción no se puede deshacer.</p>
                <input type="hidden" id="ticket-a-eliminar">
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="cerrarModal('eliminar')">Cancelar</button>
                <button class="btn-danger" onclick="confirmarEliminar()">Eliminar</button>
            </div>
        </div>
    </div>

    <!-- Modal Asignar Técnico -->
    <div class="modal-overlay" id="modal-asignar">
        <div class="modal" style="max-width: 440px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg viewBox="0 0 24 24" width="22" height="22"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    Asignar Técnico
                </h3>
                <button class="modal-close" onclick="cerrarModal('asignar')" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Selecciona un técnico para asignar este ticket:</label>
                    <select id="asignar-usuario" class="form-select"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="cerrarModal('asignar')">Cancelar</button>
                <button class="btn-primary" onclick="confirmarAsignar()">Asignar</button>
            </div>
        </div>
    </div>

    <!-- Modal Cambiar Prioridad -->
    <div class="modal-overlay" id="modal-prioridad">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg viewBox="0 0 24 24" width="22" height="22"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    Cambiar Prioridad
                </h3>
                <button class="modal-close" onclick="cerrarModal('prioridad')" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nueva prioridad:</label>
                    <select id="cambiar-prioridad" class="form-select"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="cerrarModal('prioridad')">Cancelar</button>
                <button class="btn-primary" onclick="confirmarPrioridad()">Guardar</button>
            </div>
        </div>
    </div>

    <!-- Modal Ver Historial -->
    <div class="modal-overlay" id="modal-historial">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg viewBox="0 0 24 24" width="22" height="22"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9z"/></svg>
                    Historial del Ticket
                </h3>
                <button class="modal-close" onclick="cerrarModal('historial')" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body" id="historial-contenido"></div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="cerrarModal('historial')">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <script>
    var BASE_URL = window.location.origin + '/OTI/';
    var pagePerms = <?= json_encode(
        \App\Services\AuthService::isAdmin()
            ? ['tickets:view' => true, 'tickets:edit' => true, 'tickets:delete' => true]
            : getPagePermissions(),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    console.log('TICKETS: pagePerms loaded', pagePerms);
    if (typeof pagePerms !== 'object' || pagePerms === null) {
        console.error('TICKETS: ERROR - pagePerms is not a valid object!', pagePerms);
        pagePerms = {};
    }
    var ticketActualId = null;
    var todosLosTickets = [];
    var currentStatusFilter = '';
    var currentSearchFilter = '';
    var searchTimeout = null;

    function can(perm) {
        var result = !!(pagePerms && pagePerms[perm]);
        console.log('can(' + perm + ') =', result);
        return result;
    }

    document.addEventListener('DOMContentLoaded', function() {
        cargarTickets();
        var el = document.getElementById('search-tickets');
        if (el) el.addEventListener('input', filtrarBusqueda);
    });

    var currentPage = 1;
    var paginationData = null;

    function showToast(message, type, undoCallback) {
        var container = document.getElementById('toast-container');
        var icons = {
            success: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>',
            error: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
            warning: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>',
            info: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>'
        };
        var toast = document.createElement('div');
        toast.className = 'toast ' + type;
        var html = '<span class="toast-icon">' + icons[type] + '</span><span class="toast-message">' + message + '</span>';
        if (undoCallback) {
            html += '<button class="toast-undo" onclick="undoCallback()">Deshacer</button>';
        }
        html += '<button class="toast-close" onclick="this.parentElement.remove()">&times;</button>';
        toast.innerHTML = html;
        container.appendChild(toast);
        setTimeout(function() {
            toast.style.animation = 'toastSlideOut 0.3s ease forwards';
            setTimeout(function() { toast.remove(); }, 300);
        }, 5000);
    }

    function cargarTickets(page) {
        if (page === undefined) page = 1;
        currentPage = page;

        var container = document.getElementById('tickets-container');
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-muted);"><div class="spinner" style="margin: 0 auto 12px;"></div><p>Cargando tickets...</p></div>';

        var url = BASE_URL + 'app/api/tickets.php?action=list&page=' + page + '&pageSize=' + currentPageSize;
        if (currentStatusFilter) url += '&status_id=' + currentStatusFilter;
        if (currentSearchFilter) url += '&search=' + encodeURIComponent(currentSearchFilter);
        if (currentPriorityFilter) url += '&priority_id=' + currentPriorityFilter;
        if (currentDateFromFilter) url += '&date_from=' + currentDateFromFilter;
        if (currentDateToFilter) url += '&date_to=' + currentDateToFilter;

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;

            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        todosLosTickets = Array.isArray(res.tickets) ? res.tickets : [];
                        paginationData = res.pagination || null;
                        actualizarEstadisticas(todosLosTickets);
                        renderTicketsList(todosLosTickets);
                        actualizarPaginacion();
                    } else {
                        container.innerHTML = '<div class="card"><div class="card-body"><div class="empty-state"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">Error cargando tickets</div><div class="empty-text">' + escapeHtml(res.error || 'Desconocido') + '</div></div></div></div>';
                    }
                } catch(e) {
                    container.innerHTML = '<div class="card"><div class="card-body"><div class="empty-state"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">Error de datos</div><div class="empty-text">No se pudieron procesar los tickets</div></div></div></div>';
                }
            } else {
                container.innerHTML = '<div class="card"><div class="card-body"><div class="empty-state"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">Error de servidor</div><div class="empty-text">HTTP ' + xhr.status + '</div></div></div></div>';
            }
        };
        xhr.onerror = function() {
            container.innerHTML = '<div class="card"><div class="card-body"><div class="empty-state"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">Error de conexión</div><div class="empty-text">No se pudo conectar al servidor</div></div></div></div>';
        };
        xhr.ontimeout = function() {
            container.innerHTML = '<div class="card"><div class="card-body"><div class="empty-state"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">Tiempo agotado</div><div class="empty-text">El servidor no respondió a tiempo</div></div></div></div>';
        };
        xhr.timeout = 10000;
        xhr.send();
    }

    function actualizarPaginacion() {
        var container = document.getElementById('pagination-container');
        var info = document.getElementById('pagination-info');
        var pagesContainer = document.getElementById('pagination-pages');
        var btnPrev = document.getElementById('btn-prev');
        var btnNext = document.getElementById('btn-next');

        if (!paginationData || paginationData.totalPages <= 1) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'flex';

        var start = (paginationData.currentPage - 1) * paginationData.pageSize + 1;
        var end = Math.min(paginationData.currentPage * paginationData.pageSize, paginationData.totalCount);
        info.innerHTML = 'Mostrando <strong>' + start + '-' + end + '</strong> de <strong>' + paginationData.totalCount + '</strong> tickets';

        btnPrev.disabled = !paginationData.hasPrevPage;
        btnNext.disabled = !paginationData.hasNextPage;

        var totalPages = paginationData.totalPages;
        var currentPage = paginationData.currentPage;
        var pagesHtml = '';

        if (totalPages <= 7) {
            for (var i = 1; i <= totalPages; i++) {
                pagesHtml += '<span class="pagination-page' + (i === currentPage ? ' active' : '') + '" onclick="goToPage(' + i + ')">' + i + '</span>';
            }
        } else {
            if (currentPage <= 4) {
                for (var i = 1; i <= 5; i++) {
                    pagesHtml += '<span class="pagination-page' + (i === currentPage ? ' active' : '') + '" onclick="goToPage(' + i + ')">' + i + '</span>';
                }
                pagesHtml += '<span class="pagination-ellipsis">...</span>';
                pagesHtml += '<span class="pagination-page" onclick="goToPage(' + totalPages + ')">' + totalPages + '</span>';
            } else if (currentPage >= totalPages - 3) {
                pagesHtml += '<span class="pagination-page" onclick="goToPage(1)">1</span>';
                pagesHtml += '<span class="pagination-ellipsis">...</span>';
                for (var i = totalPages - 4; i <= totalPages; i++) {
                    pagesHtml += '<span class="pagination-page' + (i === currentPage ? ' active' : '') + '" onclick="goToPage(' + i + ')">' + i + '</span>';
                }
            } else {
                pagesHtml += '<span class="pagination-page" onclick="goToPage(1)">1</span>';
                pagesHtml += '<span class="pagination-ellipsis">...</span>';
                for (var i = currentPage - 1; i <= currentPage + 1; i++) {
                    pagesHtml += '<span class="pagination-page' + (i === currentPage ? ' active' : '') + '" onclick="goToPage(' + i + ')">' + i + '</span>';
                }
                pagesHtml += '<span class="pagination-ellipsis">...</span>';
                pagesHtml += '<span class="pagination-page" onclick="goToPage(' + totalPages + ')">' + totalPages + '</span>';
            }
        }

        pagesContainer.innerHTML = pagesHtml;
    }

    function cambiarPagina(direction) {
        if (paginationData) {
            var newPage = paginationData.currentPage + direction;
            if (newPage >= 1 && newPage <= paginationData.totalPages) {
                cargarTickets(newPage);
            }
        }
    }

    function goToPage(page) {
        cargarTickets(page);
    }

    function actualizarEstadisticas(tickets) {
        document.getElementById('stat-total').innerText = tickets.length;
        document.getElementById('stat-abiertos').innerText = tickets.filter(function(t) { return t.status_id == 1; }).length;
        document.getElementById('stat-proceso').innerText = tickets.filter(function(t) { return t.status_id == 2; }).length;
        document.getElementById('stat-resueltos').innerText = tickets.filter(function(t) { return t.status_id == 3; }).length;
    }

    var currentPriorityFilter = '';
    var currentDateFromFilter = '';
    var currentDateToFilter = '';
    var currentPageSize = 20;

    function toggleAdvancedFilters() {
        var panel = document.getElementById('advanced-filters');
        panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
    }

    function clearSearch() {
        document.getElementById('search-tickets').value = '';
        document.getElementById('search-clear').style.display = 'none';
        currentSearchFilter = '';
        cargarTickets(1);
    }

    function clearAllFilters() {
        document.getElementById('search-tickets').value = '';
        document.getElementById('search-clear').style.display = 'none';
        document.getElementById('filter-priority').value = '';
        document.getElementById('filter-date-from').value = '';
        document.getElementById('filter-date-to').value = '';
        document.getElementById('filter-badge').style.display = 'none';
        
        currentSearchFilter = '';
        currentStatusFilter = '';
        currentPriorityFilter = '';
        currentDateFromFilter = '';
        currentDateToFilter = '';
        
        document.querySelectorAll('.filters .filter-btn').forEach(function(b) { b.classList.remove('active'); });
        document.querySelector('.filters .filter-btn').classList.add('active');
        
        cargarTickets(1);
    }

    function aplicarFiltrosAvanzados() {
        currentPriorityFilter = document.getElementById('filter-priority').value;
        currentDateFromFilter = document.getElementById('filter-date-from').value;
        currentDateToFilter = document.getElementById('filter-date-to').value;
        
        var count = 0;
        if (currentPriorityFilter) count++;
        if (currentDateFromFilter) count++;
        if (currentDateToFilter) count++;
        
        var badge = document.getElementById('filter-badge');
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline';
        } else {
            badge.style.display = 'none';
        }
        
        cargarTickets(1);
    }

    function cambiarPageSize() {
        currentPageSize = document.getElementById('filter-page-size').value;
        cargarTickets(1);
    }

    function filtrarEstado(status, btn) {
        currentStatusFilter = status;
        document.querySelectorAll('.filters .filter-btn').forEach(function(b) { b.classList.remove('active'); });
        if (btn) btn.classList.add('active');
        cargarTickets(1);
    }

    function filtrarBusqueda() {
        var input = document.getElementById('search-tickets');
        var clearBtn = document.getElementById('search-clear');
        
        if (input.value.length > 0) {
            clearBtn.style.display = 'flex';
        } else {
            clearBtn.style.display = 'none';
        }
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            currentSearchFilter = input.value;
            cargarTickets(1);
        }, 300);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        var day = String(d.getDate()).padStart(2, '0');
        var month = String(d.getMonth() + 1).padStart(2, '0');
        var year = d.getFullYear();
        var hours = String(d.getHours()).padStart(2, '0');
        var minutes = String(d.getMinutes()).padStart(2, '0');
        return day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
    }

    function getStatusClass(statusId) {
        switch(parseInt(statusId)) {
            case 1: return 'abierto';
            case 2: return 'en-proceso';
            case 3: return 'resuelto';
            case 4: return 'cerrado';
            case 5: return 'cancelado';
            default: return 'abierto';
        }
    }

    function getStatusName(statusId) {
        switch(parseInt(statusId)) {
            case 1: return 'Abierto';
            case 2: return 'En Proceso';
            case 3: return 'Resuelto';
            case 4: return 'Cerrado';
            case 5: return 'Cancelado';
            default: return 'Abierto';
        }
    }

    function getPriorityClass(priorityId) {
        switch(parseInt(priorityId)) {
            case 1: return 'sin-prioridad';
            case 2: return 'baja';
            case 3: return 'media';
            case 4: return 'alta';
            case 5: return 'critica';
            default: return 'media';
        }
    }

    function renderTicketsList(tickets) {
        var container = document.getElementById('tickets-container');
        console.log('renderTicketsList: rendering', tickets?.length, 'tickets');
        if (tickets.length === 0) {
            container.innerHTML = '<div class="card"><div class="card-body"><div class="empty-state"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">No hay tickets</div><div class="empty-text">No se encontraron tickets con los filtros seleccionados</div></div></div></div>';
            return;
        }

        var html = '<div class="tickets-table-wrapper"><table class="tickets-table">';
        html += '<thead><tr>';
        html += '<th>Código</th>';
        html += '<th>Asunto</th>';
        html += '<th>Solicitante</th>';
        html += '<th>Fecha</th>';
        html += '<th>Estado</th>';
        html += '<th>Prioridad</th>';
            html += '<th style="width: 155px;">Acciones</th>';
        html += '</tr></thead><tbody>';

        tickets.forEach(function(ticket) {
            var userName = (ticket.user_name || '') + ' ' + (ticket.user_lastname || '');
            var userInitial = userName.trim().charAt(0).toUpperCase();
            var statusClass = getStatusClass(ticket.status_id);
            var priorityClass = getPriorityClass(ticket.priority_id);

            html += '<tr>';
            html += '<td><span class="ticket-code-cell">' + escapeHtml(ticket.code || '') + '</span></td>';
            html += '<td class="ticket-title-cell">' + escapeHtml(ticket.title || '') + '</td>';
            html += '<td><div class="ticket-user-cell"><span class="ticket-user-avatar">' + userInitial + '</span><span class="ticket-user-name">' + escapeHtml(userName.trim()) + '</span></div></td>';
            html += '<td class="ticket-date-cell">' + formatDate(ticket.created_at) + '</td>';
            html += '<td><span class="status-badge ' + statusClass + '">' + escapeHtml(getStatusName(ticket.status_id)) + '</span></td>';
            html += '<td><span class="priority-badge ' + priorityClass + '">' + escapeHtml(ticket.priority_name || 'Media') + '</span></td>';

            html += '<td class="action-cell">';
            if (can('tickets:view'))
                html += '<button class="action-btn sm view" data-action="view" data-id="' + ticket.id + '" onclick="verTicket(\'' + ticket.id + '\')" title="Ver"><i data-lucide="eye"></i></button>';
            if (can('tickets:edit'))
                html += '<button class="action-btn sm edit" data-action="edit" data-id="' + ticket.id + '" onclick="editarTicket(\'' + ticket.id + '\')" title="Editar"><i data-lucide="pencil"></i></button>';
            if (can('tickets:delete'))
                html += '<button class="action-btn sm delete" data-action="delete" data-id="' + ticket.id + '" onclick="eliminarTicket(\'' + ticket.id + '\')" title="Eliminar"><i data-lucide="trash-2"></i></button>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    var selectedTicketIndex = -1;

    document.addEventListener('keydown', function(e) {
        if (isInputFocused()) return;
        
        var modal = document.querySelector('.modal-overlay.active');
        if (modal) return;

        var container = document.getElementById('tickets-container');
        var rows = container.querySelectorAll('.tickets-table tbody tr');
        
        if (rows.length === 0) return;

        if (e.key === 'j' || e.key === 'J') {
            e.preventDefault();
            selectNextTicket(rows);
        } else if (e.key === 'k' || e.key === 'K') {
            e.preventDefault();
            selectPrevTicket(rows);
        } else if (e.key === 'Enter' && selectedTicketIndex >= 0) {
            e.preventDefault();
            var selectedRow = rows[selectedTicketIndex];
            if (selectedRow) {
                var viewBtn = selectedRow.querySelector('.action-btn.view');
                if (viewBtn) viewBtn.click();
            }
        } else if (e.key === 'o' || e.key === 'O') {
            if (selectedTicketIndex >= 0) {
                var row = rows[selectedTicketIndex];
                var viewBtn = row.querySelector('.action-btn.view');
                if (viewBtn) viewBtn.click();
            }
        }
    });

    function cerrarTicket(id) {
        if (!confirm('\u00bfEst\u00e1s seguro de cerrar este ticket?')) return;
        var f = new FormData();
        f.append('id', id);
        f.append('estado', 'cerrado');
        f.append('respuesta', 'Ticket cerrado por el administrador');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', BASE_URL + 'app/api/tickets.php?action=update-ticket', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    showToast('Ticket cerrado con \u00e9xito', 'success');
                    cargarTickets(currentPage);
                } else {
                    showToast('Error al cerrar ticket', 'error');
                }
            }
        };
        xhr.send(f);
    }

    function reabrirTicket(id) {
        var f = new FormData();
        f.append('id', id);
        f.append('estado', 'abierto');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', BASE_URL + 'app/api/tickets.php?action=update-ticket', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    showToast('Ticket reabierto con \u00e9xito', 'success');
                    cargarTickets(currentPage);
                } else {
                    showToast('Error al reabrir ticket', 'error');
                }
            }
        };
        xhr.send(f);
    }

    function abrirAsignar(id) {
        ticketActualId = id;
        var modal = document.getElementById('modal-asignar');
        var select = document.getElementById('asignar-usuario');
        select.innerHTML = '<option value="">Cargando...</option>';
        modal.classList.add('active');

        fetch(BASE_URL + 'app/api/tickets.php?action=get-usuarios')
            .then(function(r) { return r.json(); })
            .then(function(usuarios) {
                var html = '<option value="">-- Sin asignar --</option>';
                usuarios.forEach(function(u) {
                    html += '<option value="' + u.id + '">' + escapeHtml(u.nombre + ' ' + u.apellidos) + '</option>';
                });
                select.innerHTML = html;
            });
    }

    function confirmarAsignar() {
        var id = ticketActualId;
        var asignado = document.getElementById('asignar-usuario').value;
        var f = new FormData();
        f.append('id', id);
        f.append('asignado', asignado);
        f.append('estado', 'en_proceso');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', BASE_URL + 'app/api/tickets.php?action=update-ticket', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        showToast('T\u00e9cnico asignado correctamente', 'success');
                        cerrarModal('asignar');
                        cargarTickets(currentPage);
                    } else {
                        showToast('Error: ' + (res.error || ''), 'error');
                    }
                } catch(e) {
                    showToast('Error al asignar', 'error');
                }
            }
        };
        xhr.send(f);
    }

    function abrirPrioridad(id) {
        ticketActualId = id;
        var modal = document.getElementById('modal-prioridad');
        var select = document.getElementById('cambiar-prioridad');
        select.innerHTML = '<option value="">Cargando...</option>';
        modal.classList.add('active');

        fetch(BASE_URL + 'app/api/tickets.php?action=get-priorities')
            .then(function(r) { return r.json(); })
            .then(function(priorities) {
                var html = '';
                priorities.forEach(function(p) {
                    html += '<option value="' + p.id + '">' + escapeHtml(p.name) + '</option>';
                });
                select.innerHTML = html;
            });
    }

    function confirmarPrioridad() {
        var id = ticketActualId;
        var prioridad = document.getElementById('cambiar-prioridad').value;
        if (!prioridad) { showToast('Selecciona una prioridad', 'warning'); return; }
        var f = new FormData();
        f.append('id', id);
        f.append('prioridad', prioridad);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', BASE_URL + 'app/api/tickets.php?action=update-ticket', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        showToast('Prioridad actualizada', 'success');
                        cerrarModal('prioridad');
                        cargarTickets(currentPage);
                    } else {
                        showToast('Error: ' + (res.error || ''), 'error');
                    }
                } catch(e) {
                    showToast('Error al cambiar prioridad', 'error');
                }
            }
        };
        xhr.send(f);
    }

    function verHistorial(id) {
        var modal = document.getElementById('modal-historial');
        var contenido = document.getElementById('historial-contenido');
        modal.classList.add('active');
        contenido.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text-muted);"><div class="spinner" style="margin:0 auto 12px;"></div><p>Cargando historial...</p></div>';

        fetch(BASE_URL + 'app/api/user_tickets.php?action=get-activities&id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.activities || data.activities.length === 0) {
                    contenido.innerHTML = '<div style="text-align:center;padding:32px;color:var(--text-muted);font-size:14px;">No hay actividades registradas para este ticket</div>';
                    return;
                }
                var html = '<div style="max-height:400px;overflow-y:auto;">';
                data.activities.forEach(function(a) {
                    var dot = getTimelineDotClass(a.action);
                    var label = getActionLabel(a.action);
                    html += '<div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--ticket-border);">';
                    html += '<div style="width:10px;height:10px;border-radius:50%;background:var(--' + dot + ');margin-top:4px;flex-shrink:0;"></div>';
                    html += '<div style="flex:1;"><div style="font-weight:600;font-size:14px;color:var(--ticket-text);">' + escapeHtml(label) + '</div>';
                    html += '<div style="font-size:13px;color:var(--ticket-text-muted);margin-top:2px;">' + escapeHtml(a.description || '') + '</div>';
                    html += '<div style="font-size:11px;color:var(--ticket-text-muted);margin-top:4px;">' + formatDate(a.created_at) + '</div></div></div>';
                });
                html += '</div>';
                contenido.innerHTML = html;
            })
            .catch(function() {
                contenido.innerHTML = '<div style="text-align:center;padding:32px;color:var(--danger);font-size:14px;">Error al cargar el historial</div>';
            });
    }

    function getTimelineDotClass(action) {
        switch(action) {
            case 'creado': return 'ticket-primary';
            case 'visto': return 'ticket-info';
            case 'proceso': case 'asignado': return 'ticket-warning';
            case 'resuelto': case 'cerrado': return 'ticket-success';
            case 'cancelado': return 'ticket-danger';
            case 'comentario': return 'ticket-primary';
            default: return 'ticket-primary';
        }
    }

    function getActionLabel(action) {
        var labels = {
            'creado': 'Ticket creado',
            'visto': 'Visto por el usuario',
            'visto_admin': 'Revisado por el administrador',
            'proceso': 'En proceso',
            'asignado': 'T\u00e9cnico asignado',
            'resuelto': 'Resuelto',
            'cerrado': 'Cerrado',
            'cancelado': 'Cancelado',
            'comentario': 'Comentario',
            'actualizado': 'Actualizado',
            'prioridad': 'Prioridad cambiada',
            'reabierto': 'Reabierto'
        };
        return labels[action] || action;
    }

    function imprimirTicket(id) {
        fetch(BASE_URL + 'app/api/tickets.php?action=get-ticket&id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(ticket) {
                if (ticket.error) { showToast(ticket.error, 'error'); return; }
                var w = window.open('', '_blank');
                var name = (ticket.user_name || '') + ' ' + (ticket.user_lastname || '');
                w.document.write('<!DOCTYPE html><html lang=\"es\"><head><meta charset=\"UTF-8\"><title>Ticket ' + escapeHtml(ticket.code) + '</title>');
                w.document.write('<style>body{font-family:Outfit,sans-serif;padding:40px;color:#111a2e;}.header{text-align:center;margin-bottom:32px;border-bottom:2px solid #0f2942;padding-bottom:16px;}.code{font-size:28px;font-weight:700;color:#0f2942;}.title{font-size:18px;font-weight:600;margin:4px 0;}.label{font-size:12px;color:#7e92a9;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px;}.value{font-size:14px;font-weight:500;margin-bottom:12px;}.grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;}.section{margin-top:24px;border-top:1px solid #dfe6f0;padding-top:16px;}.footer{text-align:center;margin-top:40px;font-size:12px;color:#7e92a9;border-top:1px solid #dfe6f0;padding-top:16px;}@media print{body{padding:20px;}}</style></head><body>');
                w.document.write('<div class="header"><div class="code">' + escapeHtml(ticket.code) + '</div><div class="title">' + escapeHtml(ticket.title) + '</div></div>');
                w.document.write('<div class="grid"><div><div class="label">Estado</div><div class="value">' + getStatusName(ticket.status_id) + '</div></div>');
                w.document.write('<div><div class="label">Prioridad</div><div class="value">' + escapeHtml(ticket.priority_name || 'Media') + '</div></div>');
                w.document.write('<div><div class="label">Solicitante</div><div class="value">' + escapeHtml(name) + '</div></div>');
                w.document.write('<div><div class="label">Email</div><div class="value">' + escapeHtml(ticket.user_email || '-') + '</div></div>');
                w.document.write('<div><div class="label">Ubicaci\u00f3n</div><div class="value">' + escapeHtml(ticket.location_name || '-') + '</div></div>');
                w.document.write('<div><div class="label">Fecha de creaci\u00f3n</div><div class="value">' + formatDate(ticket.created_at) + '</div></div></div>');
                if (ticket.description) {
                    w.document.write('<div class="section"><div class="label">Descripci\u00f3n</div><div class="value" style="white-space:pre-wrap;">' + escapeHtml(ticket.description) + '</div></div>');
                }
                if (ticket.response_message) {
                    w.document.write('<div class="section"><div class="label">Respuesta de soporte</div><div class="value" style="white-space:pre-wrap;">' + escapeHtml(ticket.response_message) + '</div></div>');
                }
                w.document.write('<div class="footer">Documento generado por el Sistema OTI</div>');
                w.document.write('</body></html>');
                w.document.close();
                setTimeout(function() { w.print(); }, 300);
            })
            .catch(function() { showToast('Error al obtener datos del ticket', 'error'); });
    }

    function selectNextTicket(rows) {
        if (selectedTicketIndex < rows.length - 1) {
            if (selectedTicketIndex >= 0) rows[selectedTicketIndex].classList.remove('selected-row');
            selectedTicketIndex++;
            rows[selectedTicketIndex].classList.add('selected-row');
            rows[selectedTicketIndex].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function selectPrevTicket(rows) {
        if (selectedTicketIndex > 0) {
            rows[selectedTicketIndex].classList.remove('selected-row');
            selectedTicketIndex--;
            rows[selectedTicketIndex].classList.add('selected-row');
            rows[selectedTicketIndex].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function isInputFocused() {
        var activeElement = document.activeElement;
        return activeElement && (activeElement.tagName === 'INPUT' || 
                               activeElement.tagName === 'TEXTAREA' || 
                               activeElement.isContentEditable);
    }

    function escapeHtml(text) {
        if (!text) return '';
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function exportTableToCSV(selector, filename) {
        var table = document.querySelector(selector);
        if (!table) return;
        var rows = table.querySelectorAll('tr');
        var csv = '';
        rows.forEach(function(row) {
            var cols = row.querySelectorAll('th, td');
            var rowData = [];
            cols.forEach(function(col) {
                var text = col.textContent.trim().replace(/,/g, ';').replace(/"/g, '""');
                rowData.push('"' + text + '"');
            });
            csv += rowData.join(',') + '\n';
        });
        var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function cerrarModal(tipo) {
        var modal = document.getElementById('modal-' + tipo);
        if (modal) modal.classList.remove('active');
    }

    function verTicket(id) {
        console.log('verTicket called with id:', id);
        var modal = document.getElementById('modal-ver');
        var contenido = document.getElementById('ver-contenido');
        modal.classList.add('active');
        contenido.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--text-muted);">Cargando información del ticket...</div>';

        // Marcar como visto por el administrador
        fetch(BASE_URL + 'app/api/tickets.php?action=mark-viewed&id=' + id).catch(function() {});

        fetch(BASE_URL + 'app/api/tickets.php?action=get-ticket&id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(ticket) {
                if (ticket.error) {
                    contenido.innerHTML = '<p style="color: var(--danger);">' + escapeHtml(ticket.error) + '</p>';
                    return;
                }
                var html = '<div class="detail-grid">';
                html += '<div class="detail-section-title"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>Información del Ticket</div>';
                html += '<div class="detail-item"><div class="detail-label">Código</div><div class="detail-value" style="color: var(--primary); font-weight: 600;">' + escapeHtml(ticket.code || '-') + '</div></div>';
                html += '<div class="detail-item"><div class="detail-label">Estado</div><div class="detail-value"><span class="status-badge ' + getStatusClass(ticket.status_id) + '">' + escapeHtml(getStatusName(ticket.status_id)) + '</span></div></div>';
                html += '<div class="detail-item"><div class="detail-label">Prioridad</div><div class="detail-value"><span class="priority-badge ' + getPriorityClass(ticket.priority_id) + '">' + escapeHtml(ticket.priority_name || 'Media') + '</span></div></div>';
                var hrs = ticket.resolution_time_hours || 0;
                var tiempoText = 'Sin estimar';
                if (hrs > 0) {
                    if (hrs % 720 === 0) { tiempoText = (hrs / 720) + ' Mes(es)'; }
                    else if (hrs % 168 === 0) { tiempoText = (hrs / 168) + ' Semana(s)'; }
                    else if (hrs % 24 === 0) { tiempoText = (hrs / 24) + ' D\u00eda(s)'; }
                    else { tiempoText = hrs + ' Hora(s)'; }
                }
                html += '<div class="detail-item"><div class="detail-label">Tiempo Estimado</div><div class="detail-value">' + escapeHtml(tiempoText) + '</div></div>';
                var responsable = 'Sin asignar';
                if (ticket.assigned_name) responsable = ticket.assigned_name + ' ' + (ticket.assigned_lastname || '');
                html += '<div class="detail-item"><div class="detail-label">Responsable</div><div class="detail-value">' + escapeHtml(responsable) + '</div></div>';
                html += '<div class="detail-description"><div class="detail-label">Asunto</div><div class="detail-value" style="font-weight: 600;">' + escapeHtml(ticket.title || '-') + '</div></div>';
                html += '<div class="detail-description"><div class="detail-label">Descripci\u00f3n</div><div class="detail-value">' + escapeHtml(ticket.description || '-') + '</div></div>';
                html += '<div class="detail-section-title"><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>Datos del Solicitante</div>';
                html += '<div class="detail-item"><div class="detail-label">Nombre Completo</div><div class="detail-value">' + escapeHtml((ticket.user_name || '') + ' ' + (ticket.user_lastname || '')) + '</div></div>';
                html += '<div class="detail-item"><div class="detail-label">Email</div><div class="detail-value">' + escapeHtml(ticket.user_email || '-') + '</div></div>';
                html += '<div class="detail-item"><div class="detail-label">Tel\u00e9fono</div><div class="detail-value">' + escapeHtml(ticket.user_phone || '-') + '</div></div>';
html += '<div class="detail-item"><div class="detail-label">Ubicaci\u00f3n</div><div class="detail-value">' + escapeHtml(ticket.location_name || '-') + '</div></div>';
                    html += '<div class="detail-item"><div class="detail-label">Categor\u00eda</div><div class="detail-value">' + escapeHtml(ticket.category_name || '-') + '</div></div>';
                if (ticket.response_message) {
                    html += '<div class="detail-description" style="background: var(--primary-soft); border: 1px solid #c7d2fe;"><div class="detail-label" style="color: var(--primary); font-weight: 600;">Respuesta del Administrador</div><div class="detail-value">' + escapeHtml(ticket.response_message) + '</div></div>';
                }
                html += '</div>';
                html += '<div class="detail-card" style="margin-top: 20px;"><div class="detail-card-title"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9z"/></svg>Historial de Actividad</div><div id="admin-timeline-container"><div class="loading" style="padding:20px;text-align:center;color:var(--text-muted);">Cargando historial...</div></div></div>';
                contenido.innerHTML = html;
                if (typeof lucide !== 'undefined') lucide.createIcons();
                loadAdminActivities(id);
            })
            .catch(function() {
                contenido.innerHTML = '<p style="color: var(--danger);">Error al cargar datos del ticket</p>';
            });
    }

    function loadAdminActivities(ticketId) {
        fetch(BASE_URL + 'app/api/tickets.php?action=get-activities&id=' + ticketId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var container = document.getElementById('admin-timeline-container');
                if (!container) return;
                if (data.activities && data.activities.length > 0) {
                    var html = '<div class="timeline">';
                    data.activities.forEach(function(activity) {
                        var dotClass = getTimelineDotClass(activity.action);
                        html += '<div class="timeline-item"><div class="timeline-dot ' + dotClass + '"></div>';
                        html += '<div class="timeline-content"><div class="timeline-title">' + escapeHtml(getActionLabel(activity.action)) + '</div>';
                        html += '<div class="timeline-desc">' + escapeHtml(activity.description || '') + '</div>';
                        html += '<div class="timeline-time">' + formatDate(activity.created_at) + '</div></div></div>';
                    });
                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:20px;font-size:13px;">No hay actividades registradas</div>';
                }
            })
            .catch(function() {
                var container = document.getElementById('admin-timeline-container');
                if (container) container.innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:20px;font-size:13px;">Error al cargar actividades</div>';
            });
    }

    function getTimelineDotClass(action) {
        var a = (action || '').toLowerCase();
        if (a.includes('creado') || a === 'created') return 'creado';
        if (a.includes('visto')) return 'visto';
        if (a.includes('proceso') || a.includes('asignado') || a.includes('progress') || a === 'assigned') return 'proceso';
        if (a.includes('resuelto') || a.includes('resolved')) return 'resuelto';
        if (a.includes('cerrado') || a.includes('closed')) return 'cerrado';
        if (a.includes('cancelado') || a.includes('cancelled')) return 'cancelado';
        return 'creado';
    }

    function editarTicket(id) {
        var modal = document.getElementById('modal-editar');
        var contenido = document.getElementById('editar-contenido');
        modal.classList.add('active');
        contenido.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--text-muted);">Cargando formulario...</div>';

        Promise.all([
            fetch(BASE_URL + 'app/api/tickets.php?action=get-ticket&id=' + id).then(function(r) { return r.json(); }),
            fetch(BASE_URL + 'app/api/tickets.php?action=get-priorities').then(function(r) { return r.json(); }),
            fetch(BASE_URL + 'app/api/tickets.php?action=get-usuarios').then(function(r) { return r.json(); })
        ]).then(function(results) {
            var ticket = results[0];
            var priorities = results[1];
            var usuarios = results[2];

            if (ticket.error) {
                contenido.innerHTML = '<p style="color: var(--danger);">' + ticket.error + '</p>';
                return;
            }

            var hrs = ticket.resolution_time_hours || 0;
            var val = hrs;
            var unit = 'horas';
            if (hrs > 0) {
                if (hrs % 720 === 0) { val = hrs / 720; unit = 'meses'; }
                else if (hrs % 168 === 0) { val = hrs / 168; unit = 'semanas'; }
                else if (hrs % 24 === 0) { val = hrs / 24; unit = 'dias'; }
            }

            var tiempoText = 'Sin estimar';
            if (hrs > 0) {
                if (hrs % 720 === 0) { tiempoText = (hrs / 720) + ' Mes(es)'; }
                else if (hrs % 168 === 0) { tiempoText = (hrs / 168) + ' Semana(s)'; }
                else if (hrs % 24 === 0) { tiempoText = (hrs / 24) + ' D\u00eda(s)'; }
                else { tiempoText = hrs + ' Hora(s)'; }
            }
            var responsable = 'Sin asignar';
            if (ticket.assigned_name) responsable = ticket.assigned_name + ' ' + (ticket.assigned_lastname || '');

            var html = '<input type="hidden" id="edit-ticket-id" value="' + id + '">';
            html += '<div class="edit-layout">';
            // Left column: ticket details
            html += '<div class="edit-left">';
            html += '<div class="detail-section-title"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>Detalles del Ticket</div>';
            html += '<div class="detail-item"><div class="detail-label">Código</div><div class="detail-value" style="color: var(--primary); font-weight: 600;">' + escapeHtml(ticket.code || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="detail-label">Estado</div><div class="detail-value"><span class="status-badge ' + getStatusClass(ticket.status_id) + '">' + escapeHtml(getStatusName(ticket.status_id)) + '</span></div></div>';
            html += '<div class="detail-item"><div class="detail-label">Prioridad</div><div class="detail-value"><span class="priority-badge ' + getPriorityClass(ticket.priority_id) + '">' + escapeHtml(ticket.priority_name || 'Media') + '</span></div></div>';
            html += '<div class="detail-item"><div class="detail-label">Solicitante</div><div class="detail-value">' + escapeHtml((ticket.user_name || '') + ' ' + (ticket.user_lastname || '')) + '</div></div>';
            html += '<div class="detail-item"><div class="detail-label">Ubicaci\u00f3n</div><div class="detail-value">' + escapeHtml(ticket.location_name || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="detail-label">Tiempo Est.</div><div class="detail-value">' + escapeHtml(tiempoText) + '</div></div>';
            html += '<div class="detail-item"><div class="detail-label">Responsable</div><div class="detail-value">' + escapeHtml(responsable) + '</div></div>';
            html += '<div class="detail-description" style="margin-top:8px;"><div class="detail-label">Asunto</div><div class="detail-value" style="font-weight:600;">' + escapeHtml(ticket.title || '-') + '</div></div>';
            if (ticket.description) {
                html += '<div class="detail-description"><div class="detail-label">Descripción</div><div class="detail-value">' + escapeHtml(ticket.description) + '</div></div>';
            }
            if (ticket.response_message) {
                html += '<div class="detail-description" style="background:var(--primary-soft);border:1px solid #c7d2fe;margin-top:8px;"><div class="detail-label" style="color:var(--primary);font-weight:600;">Respuesta Anterior</div><div class="detail-value">' + escapeHtml(ticket.response_message) + '</div></div>';
            }
            html += '</div>';
            // Right column: form
            html += '<div class="edit-right">';
            html += '<div class="detail-section-title"><svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>Responder Ticket</div>';
            html += '<div class="form-group"><label class="form-label">Estado:</label><select id="edit-estado" class="form-select">';
            html += '<option value="abierto"' + (ticket.status_id == 1 ? ' selected' : '') + '>Abierto</option>';
            html += '<option value="en_proceso"' + (ticket.status_id == 2 ? ' selected' : '') + '>En Proceso</option>';
            html += '<option value="resuelto"' + (ticket.status_id == 3 ? ' selected' : '') + '>Resuelto</option>';
            html += '<option value="cerrado"' + (ticket.status_id == 4 ? ' selected' : '') + '>Cerrado</option>';
            html += '</select></div>';
            html += '<div class="form-group"><label class="form-label">Prioridad:</label><select id="edit-prioridad" class="form-select">';
            priorities.forEach(function(p) {
                html += '<option value="' + p.id + '"' + (ticket.priority_id == p.id ? ' selected' : '') + '>' + escapeHtml(p.name) + '</option>';
            });
            html += '</select></div>';
            html += '<div class="form-group"><label class="form-label">Responsable:</label><select id="edit-asignado" class="form-select">';
            html += '<option value="">-- Sin asignar --</option>';
            usuarios.forEach(function(u) {
                var fullname = u.nombre + ' ' + u.apellidos;
                html += '<option value="' + u.id + '"' + (ticket.assigned_admin_id == u.id ? ' selected' : '') + '>' + escapeHtml(fullname) + '</option>';
            });
            html += '</select></div>';
            html += '<div class="form-group"><label class="form-label">Tiempo Estimado:</label><div class="form-row"><input type="number" id="edit-tiempo-valor" value="' + (val || '') + '" min="1" placeholder="Ej. 3" class="form-input"><select id="edit-tiempo-unidad" class="form-select">';
            html += '<option value="horas"' + (unit === 'horas' ? ' selected' : '') + '>Horas</option>';
            html += '<option value="dias"' + (unit === 'dias' ? ' selected' : '') + '>D\u00edas</option>';
            html += '<option value="semanas"' + (unit === 'semanas' ? ' selected' : '') + '>Semanas</option>';
            html += '<option value="meses"' + (unit === 'meses' ? ' selected' : '') + '>Meses</option>';
            html += '</select></div></div>';
            html += '<div class="form-group"><label class="form-label">Respuesta / Comentarios:</label><textarea id="edit-respuesta" class="form-textarea" placeholder="Escribe detalles del estado actual o solución...">' + escapeHtml(ticket.response_message || '') + '</textarea></div>';
            html += '<div style="display: flex; align-items: center; gap: 8px; background: var(--primary-soft); padding: 12px; border-radius: var(--radius-sm); border: 1px solid #c7d2fe;">';
            html += '<input type="checkbox" id="edit-enviar-mensaje" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">';
            html += '<label for="edit-enviar-mensaje" style="font-size: 13px; font-weight: 500; color: var(--primary); cursor: pointer;">Enviar notificación al usuario</label>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            contenido.innerHTML = html;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }).catch(function(err) {
            contenido.innerHTML = '<p style="color: var(--danger);">Error cargando datos: ' + err.message + '</p>';
        });
    }

    function guardarRespuesta() {
        var btn = document.querySelector('#modal-editar .modal-footer .btn-primary');
        if (btn.disabled) return;
        btn.disabled = true;
        btn.innerHTML = '<svg class="spinner-inline" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-dasharray="31.4 31.4" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.8s" repeatCount="indefinite"/></circle></svg> Guardando...';

        var id = document.getElementById('edit-ticket-id').value;
        var estado = document.getElementById('edit-estado').value;
        var prioridad = document.getElementById('edit-prioridad').value;
        var asignado = document.getElementById('edit-asignado').value;
        var tiempo_valor = document.getElementById('edit-tiempo-valor').value;
        var tiempo_unidad = document.getElementById('edit-tiempo-unidad').value;
        var respuesta = document.getElementById('edit-respuesta').value;
        var enviar_mensaje = document.getElementById('edit-enviar-mensaje').checked ? '1' : '0';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', BASE_URL + 'app/api/tickets.php?action=update-ticket', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                btn.disabled = false;
                btn.innerHTML = 'Enviar Respuesta';
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            showToast('Ticket actualizado con éxito', 'success');
                            cerrarModal('editar');
                            cargarTickets();
                        } else {
                            showToast('Error: ' + (res.error || 'No se pudo actualizar'), 'error');
                        }
                    } catch(e) {
                        showToast('Error al procesar respuesta del servidor', 'error');
                    }
                } else {
                    showToast('Error de conexión al servidor', 'error');
                }
            }
        };
        var postData = 'id=' + encodeURIComponent(id) + '&estado=' + encodeURIComponent(estado) + '&prioridad=' + encodeURIComponent(prioridad) + '&asignado=' + encodeURIComponent(asignado) + '&tiempo_valor=' + encodeURIComponent(tiempo_valor) + '&tiempo_unidad=' + encodeURIComponent(tiempo_unidad) + '&respuesta=' + encodeURIComponent(respuesta) + '&enviar_mensaje=' + encodeURIComponent(enviar_mensaje);
        xhr.send(postData);
    }

    function eliminarTicket(id) {
        document.getElementById('ticket-a-eliminar').value = id;
        document.getElementById('modal-eliminar').classList.add('active');
    }

    function confirmarEliminar() {
        var btn = document.querySelector('#modal-eliminar .modal-footer .btn-danger');
        if (btn.disabled) return;
        btn.disabled = true;
        btn.innerHTML = 'Eliminando...';

        var id = document.getElementById('ticket-a-eliminar').value;
        var xhr = new XMLHttpRequest();
        xhr.open('DELETE', BASE_URL + 'app/api/tickets.php?action=delete-ticket&id=' + id, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                btn.disabled = false;
                btn.innerHTML = 'Eliminar';
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            showToast('Ticket eliminado con éxito', 'success');
                            cerrarModal('eliminar');
                            cargarTickets();
                        } else {
                            showToast('Error: ' + (res.error || 'No se pudo eliminar'), 'error');
                        }
                    } catch(e) {
                        showToast('Error al procesar la respuesta', 'error');
                    }
                } else {
                    showToast('Error de conexión al servidor', 'error');
                }
            }
        };
        xhr.send();
    }
    </script>
    <script>
    // Fallback: delegación de eventos para botones de acción (por si los handlers inline no están disponibles)
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.action-btn');
        if (!btn) return;
        var action = btn.getAttribute('data-action') || '';
        var id = btn.getAttribute('data-id');
        if (!action || !id) return;

        try {
            if (action === 'view') {
                if (typeof window.verTicket === 'function') return window.verTicket(id);
            }
            if (action === 'edit') {
                if (typeof window.editarTicket === 'function') return window.editarTicket(id);
            }
            if (action === 'delete') {
                if (typeof window.eliminarTicket === 'function') return window.eliminarTicket(id);
            }
        } catch (err) {
            console.error('Action delegation error:', err);
        }
    }, false);
    </script>
    <script src="<?= htmlspecialchars($baseUrl) ?>public/assets/js/search.js"></script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
