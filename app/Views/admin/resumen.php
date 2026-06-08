<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;

$isOtiAdmin = \App\Services\AuthService::isAdmin();

$tituloPagina = 'Mi Resumen - Sistema OTI';
$paginaActual = 'admin-resumen';
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<style>
        .summary-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .summary-card { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-light); padding: 24px; }
        .summary-title { font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        .summary-title svg { width: 20px; height: 20px; fill: var(--primary); }
        .summary-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border-light); }
        .summary-item:last-child { border-bottom: none; }
        .item-label { color: var(--text-muted); }
        .item-value { font-weight: 600; color: var(--text-primary); }
    </style>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

    <main id="main-content" class="main-content">
        <h1 class="page-title">Mi Resumen</h1>
        <p class="page-subtitle">Resumen de mis actividades</p>
        
        <div class="summary-grid">
            <div class="summary-card">
                <h3 class="summary-title">
                    <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                    Mis Tickets
                </h3>
                <div class="summary-item"><span class="item-label">Total de tickets</span><span class="item-value">12</span></div>
                <div class="summary-item"><span class="item-label">Abiertos</span><span class="item-value" style="color: var(--danger);">3</span></div>
                <div class="summary-item"><span class="item-label">En proceso</span><span class="item-value" style="color: var(--warning);">2</span></div>
                <div class="summary-item"><span class="item-label">Resueltos</span><span class="item-value" style="color: var(--success);">7</span></div>
            </div>
            <div class="summary-card">
                <h3 class="summary-title">
                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                    Mi Actividad
                </h3>
                <div class="summary-item"><span class="item-label">Último acceso</span><span class="item-value">Hoy 09:15</span></div>
                <div class="summary-item"><span class="item-label">Tickets este mes</span><span class="item-value">5</span></div>
                <div class="summary-item"><span class="item-label">Promedio resolución</span><span class="item-value">18 horas</span></div>
            </div>
        </div>
    </main>
<?php require __DIR__ . '/../partials/footer.php'; ?>