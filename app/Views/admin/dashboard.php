<?php
/**
 * Dashboard del Administrador - Tiempo Real
 * Sistema OTI - Diseño Moderno
 */

$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$isOtiAdmin = \App\Services\AuthService::isAdmin();

$tituloPagina = 'Dashboard - Sistema OTI';
$paginaActual = 'admin-dashboard';
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

    <!-- Contenido Principal -->
    <main id="main-content" class="main-content">
        <div class="page-header">
            <div class="page-title-group">
                <h1>Panel Principal</h1>
                <p>Resumen general del sistema en tiempo real</p>
            </div>
            <div class="realtime-indicator">
                <span class="realtime-dot"></span>
                <span>Tiempo real</span>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid stagger-children">
            <a href="<?= $baseUrl ?>admin/tickets" class="stat-card primary">
                <div class="stat-icon primary">
                    <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="stat-total">0</div>
                    <div class="stat-label">Total de Tickets</div>
                </div>
            </a>
            <a href="<?= $baseUrl ?>admin/tickets?status=abiertos" class="stat-card danger">
                <div class="stat-icon danger">
                    <svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="stat-abiertos">0</div>
                    <div class="stat-label">Abiertos</div>
                </div>
            </a>
            <a href="<?= $baseUrl ?>admin/tickets?status=proceso" class="stat-card warning">
                <div class="stat-icon warning">
                    <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="stat-proceso">0</div>
                    <div class="stat-label">En Proceso</div>
                </div>
            </a>
            <a href="<?= $baseUrl ?>admin/tickets?status=resueltos" class="stat-card success">
                <div class="stat-icon success">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="stat-resueltos">0</div>
                    <div class="stat-label">Resueltos</div>
                </div>
            </a>
        </div>

        <!-- Content Grid - Layout FASE 1 -->
        <div class="content-grid-fase1">
            <!-- Timeline Column -->
            <div class="card timeline-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" fill="none"/></svg>
                        Actividad Reciente
                    </h2>
                </div>
                <div class="card-body">
                    <div class="timeline-container" id="timeline-container">
                        <div class="empty-state compact" id="timeline-skeleton">
                            <div class="skeleton-list-item">
                                <div class="skeleton" style="width: 40px; height: 40px;"></div>
                                <div style="flex: 1;">
                                    <div class="skeleton skeleton-text" style="width: 80%;"></div>
                                    <div class="skeleton skeleton-text" style="width: 50%;"></div>
                                </div>
                            </div>
                            <div class="skeleton-list-item">
                                <div class="skeleton" style="width: 40px; height: 40px;"></div>
                                <div style="flex: 1;">
                                    <div class="skeleton skeleton-text" style="width: 70%;"></div>
                                    <div class="skeleton skeleton-text" style="width: 40%;"></div>
                                </div>
                            </div>
                            <div class="skeleton-list-item">
                                <div class="skeleton" style="width: 40px; height: 40px;"></div>
                                <div style="flex: 1;">
                                    <div class="skeleton skeleton-text" style="width: 60%;"></div>
                                    <div class="skeleton skeleton-text" style="width: 45%;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="empty-state" id="timeline-empty" style="display: none;">
                            <div class="empty-icon">
                                <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" fill="none"/></svg>
                            </div>
                            <div class="empty-title">Sin actividad reciente</div>
                            <div class="empty-description">Los tickets y cambios aparecerán aquí en tiempo real.</div>
                            <a href="<?= $baseUrl ?>user/reportar" class="empty-action">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                Crear Ticket
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-col-fase1">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z" stroke="currentColor" stroke-width="2" fill="none"/></svg>
                            Acciones Rápidas
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="<?= $baseUrl ?>admin/tickets?status=abiertos" class="qa-btn">
                                <div class="qa-icon danger">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                                </div>
                                <div class="qa-text">
                                    <div class="qa-title">Ver Abiertos</div>
                                    <div class="qa-subtitle">Tickets sin atender</div>
                                </div>
                                <span class="qa-arrow">→</span>
                            </a>
                            <a href="<?= $baseUrl ?>user/reportar" class="qa-btn">
                                <div class="qa-icon primary">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/></svg>
                                </div>
                                <div class="qa-text">
                                    <div class="qa-title">Nuevo Ticket</div>
                                    <div class="qa-subtitle">Reportar incidencia</div>
                                </div>
                                <span class="qa-arrow">→</span>
                            </a>
                            <a href="<?= $baseUrl ?>admin/equipos" class="qa-btn">
                                <div class="qa-icon info">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z"/></svg>
                                </div>
                                <div class="qa-text">
                                    <div class="qa-title">Inventario</div>
                                    <div class="qa-subtitle">Ver equipos</div>
                                </div>
                                <span class="qa-arrow">→</span>
                            </a>
                            <a href="<?= $baseUrl ?>admin/analisis" class="qa-btn">
                                <div class="qa-icon success">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                                </div>
                                <div class="qa-text">
                                    <div class="qa-title">Análisis</div>
                                    <div class="qa-subtitle">Estadísticas</div>
                                </div>
                                <span class="qa-arrow">→</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Equipos Donut Chart -->
                <div class="card" style="margin-top: 16px;">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z" stroke="currentColor" stroke-width="2" fill="none"/></svg>
                            Equipos por Estado
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="donut-chart-container" id="equipos-donut">
                            <div class="donut-chart-wrapper">
                                <svg class="donut-chart" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="38" stroke="#e2e8f0" stroke-width="18"/>
                                    <circle id="donut-equipos-circle" cx="50" cy="50" r="38" stroke="var(--primary)" stroke-dasharray="0 238.76"/>
                                </svg>
                                <div class="donut-center">
                                    <div class="donut-center-value" id="equipos-total-value">0</div>
                                    <div class="donut-center-label">Equipos</div>
                                </div>
                            </div>
                            <div class="donut-legend">
                                <div class="donut-legend-item">
                                    <div class="donut-legend-color" style="background: var(--success)"></div>
                                    <div class="donut-legend-info">
                                        <div class="donut-legend-name">Activos</div>
                                    </div>
                                    <div class="donut-legend-value" id="equipos-activos-value">0</div>
                                </div>
                                <div class="donut-legend-item">
                                    <div class="donut-legend-color" style="background: var(--warning)"></div>
                                    <div class="donut-legend-info">
                                        <div class="donut-legend-name">Mantenimiento</div>
                                    </div>
                                    <div class="donut-legend-value" id="equipos-mantenimiento-value">0</div>
                                </div>
                                <div class="donut-legend-item">
                                    <div class="donut-legend-color" style="background: var(--text-muted)"></div>
                                    <div class="donut-legend-info">
                                        <div class="donut-legend-name">Inactivos</div>
                                    </div>
                                    <div class="donut-legend-value" id="equipos-inactivos-value">0</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usuarios Horizontal Bar -->
                <div class="card" style="margin-top: 16px;">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z" stroke="currentColor" stroke-width="2" fill="none"/></svg>
                            Usuarios del Sistema
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="horizontal-bar-chart" id="usuarios-bar">
                            <div class="hbar-item">
                                <div class="hbar-label">Total</div>
                                <div class="hbar-track">
                                    <div class="hbar-fill primary" id="hbar-total" style="width: 0%">
                                        <span class="hbar-value" id="hbar-total-value">0</span>
                                    </div>
                                </div>
                            </div>
                            <div class="hbar-item">
                                <div class="hbar-label">Activos</div>
                                <div class="hbar-track">
                                    <div class="hbar-fill success" id="hbar-activos" style="width: 0%">
                                        <span class="hbar-value" id="hbar-activos-value">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isOtiAdmin): ?>
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h2 class="card-title">
                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                    Estadísticas Adicionales
                </h2>
            </div>
            <div class="card-body">
                <div class="admin-stats">
                    <a href="<?= $baseUrl ?>admin/equipos" class="admin-stat-card">
                        <div class="admin-stat-title">
                            <svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z"/></svg>
                            Total Equipos
                        </div>
                        <div class="admin-stat-value" id="equipos-total">0</div>
                    </a>
                    <a href="<?= $baseUrl ?>admin/equipos?status=active" class="admin-stat-card">
                        <div class="admin-stat-title">
                            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            Equipos Activos
                        </div>
                        <div class="admin-stat-value" id="equipos-activos">0</div>
                    </a>
                    <a href="<?= $baseUrl ?>admin/equipos?status=maintenance" class="admin-stat-card">
                        <div class="admin-stat-title">
                            <svg viewBox="0 0 24 24"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/></svg>
                            En Mantenimiento
                        </div>
                        <div class="admin-stat-value" id="equipos-mantenimiento">0</div>
                    </a>
                    <a href="<?= $baseUrl ?>admin/usuarios" class="admin-stat-card">
                        <div class="admin-stat-title">
                            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3z"/></svg>
                            Total Usuarios
                        </div>
                        <div class="admin-stat-value" id="usuarios-total">0</div>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

<?php require __DIR__ . '/../partials/footer.php'; ?>