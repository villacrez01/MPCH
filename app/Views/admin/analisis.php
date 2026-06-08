<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;

$isOtiAdmin = \App\Services\AuthService::isAdmin();

$tituloPagina = 'Análisis de Datos - Sistema OTI';
$paginaActual = 'admin-analisis';

require_once __DIR__ . '/../../Core/Database.php';
use App\Core\Database;
use App\Models\Ticket;
use App\Models\Equipment;

$initialData = [];
$kpiResueltos = 0;
try {
    $pdo = Database::connect();

    /* ── Modelos centralizados ── */
    $initialData['stats']         = Ticket::getStats();
    $initialData['por_prioridad'] = Ticket::getByPriority();
    $initialData['por_estado']    = Ticket::getByStatus();
    $initialData['equipos_por_tipo'] = Equipment::getByType();
    $initialData['equipos']       = Equipment::getStats();
    $initialData['tasas_equipos'] = Equipment::getByCondition();

    /* ── Tiempo promedio de resolución ── */
    $initialData['tiempo_promedio'] = (float)($pdo->query(
        "SELECT AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 3600) as h
         FROM oti.tickets WHERE resolved_at IS NOT NULL"
    )->fetch()['h'] ?? 0);

    /* ── Tickets por mes (últimos 6 meses) ── */
    $initialData['tickets_por_mes'] = $pdo->query(
        "SELECT TO_CHAR(created_at, 'YYYY-MM') AS mes, COUNT(*) AS count
         FROM oti.tickets
         WHERE created_at >= NOW() - INTERVAL '6 months'
         GROUP BY TO_CHAR(created_at, 'YYYY-MM')
         ORDER BY mes"
    )->fetchAll(PDO::FETCH_ASSOC);

    /* ── Tasa de resolución ── */
    $stats      = $initialData['stats'];
    $total      = (int)($stats['total'] ?? 0);
    $resueltos  = (int)($stats['resueltos'] ?? 0) + (int)($stats['cerrados'] ?? 0);
    $initialData['tasa_resolucion'] = $total > 0 ? round(($resueltos / $total) * 100, 1) : 0;
    $kpiResueltos = $resueltos;

    /* ── Queries específicas sin modelo ── */
    $initialData['por_ubicacion'] = $pdo->query("
        SELECT l.name, COUNT(t.id) AS count
        FROM oti.tickets t
        JOIN oti.locations l ON t.location_id = l.id
        GROUP BY l.name
        ORDER BY count DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $initialData['top_usuarios'] = $pdo->query("
        SELECT u.nombre || ' ' || COALESCE(u.apellidos, '') AS name, COUNT(t.id) AS count
        FROM oti.tickets t
        JOIN admin.usuarios u ON t.user_id = u.id
        GROUP BY u.nombre, u.apellidos
        ORDER BY count DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log($e->getMessage());
}
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>public/assets/css/analisis.css?v=20260606">
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

    <main id="main-content" class="main-content">
        <div class="analisis-container">
            <div class="page-header">
                <div class="page-title-group">
                    <h1>Análisis de Datos</h1>
                    <p>Visualización en tiempo real del sistema OTI</p>
                </div>
                <div class="analisis-header-actions">
                    <span class="analisis-last-update" id="analisis-last-update">
                        <span class="pulse-dot" aria-hidden="true"></span>
                        Actualizado hace un momento
                    </span>
                    <button type="button" class="btn-refresh" id="btn-refresh-analisis" onclick="refreshAnalisis()" aria-label="Actualizar gráficos">
                        <i data-lucide="refresh-cw" aria-hidden="true"></i>
                        Actualizar
                    </button>
                </div>
            </div>
            
            <!-- KPI Cards -->
            <div class="kpi-grid">
                <div class="kpi-card primary">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H7v-2h5v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                        </div>
                        <div class="kpi-trend up">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M7 14l5-5 5 5z"/></svg>
                            Activo
                        </div>
                    </div>
                    <div class="kpi-value" id="kpi-total"><?= $initialData['stats']['total'] ?? 0 ?></div>
                    <div class="kpi-label">Total de Incidencias</div>
                </div>
                
                <div class="kpi-card success">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                        </div>
                        <div class="kpi-trend up">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M7 14l5-5 5 5z"/></svg>
                            +12%
                        </div>
                    </div>
                    <div class="kpi-value" id="kpi-resueltos"><?= $kpiResueltos ?? 0 ?></div>
                    <div class="kpi-label">Resueltos y Cerrados</div>
                </div>
                
                <div class="kpi-card warning">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                        </div>
                        <div class="kpi-trend down">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
                            -5%
                        </div>
                    </div>
                    <div class="kpi-value" id="kpi-tasa"><?= $initialData['tasa_resolucion'] ?? 0 ?>%</div>
                    <div class="kpi-label">Tasa de Resolución</div>
                </div>
                
                <div class="kpi-card info">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                        </div>
                    </div>
                    <div class="kpi-value" id="kpi-tiempo"><?= $initialData['tiempo_promedio'] ?? 0 ?>h</div>
                    <div class="kpi-label">Tiempo Promedio</div>
                </div>
            </div>
            
            <h2 class="charts-section-title"><i data-lucide="line-chart" width="20" height="20" style="vertical-align:middle;margin-right:6px;"></i> Tendencias y distribución</h2>
            <div class="charts-row-1">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title-group">
                            <div class="chart-icon">
                                <svg viewBox="0 0 24 24"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>
                            </div>
                            <div>
                                <div class="chart-title">Incidencias por Mes</div>
                                <div class="chart-subtitle">Últimos 6 meses</div>
                            </div>
                        </div>
                        <span class="chart-badge">Tendencia</span>
                    </div>
                    <div class="chart-body">
                        <canvas id="chart-tickets-mensual" role="img" aria-label="Gráfico de incidencias mensuales - tendencia"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title-group">
                            <div class="chart-icon" style="background: rgba(16, 185, 129, 0.1);">
                                <svg viewBox="0 0 24 24" style="fill: #10b981;"><path d="M11 2v20c-5.07-.5-9-4.79-9-10s3.93-9.5 9-10zm2.03 0v8.99H22c-.47-4.74-4.24-8.52-8.97-8.99zm0 11.01V22c4.74-.47 8.5-4.25 8.97-8.99h-8.97z"/></svg>
                            </div>
                            <div>
                                <div class="chart-title">Incidencias por Prioridad</div>
                                <div class="chart-subtitle">Distribución general</div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="chart-prioridad" role="img" aria-label="Gráfico de distribución de incidencias por prioridad"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Row 2: 3 Bar Charts -->
            <div class="charts-row-2">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title-group">
                            <div class="chart-icon" style="background: rgba(245, 158, 11, 0.1);">
                                <svg viewBox="0 0 24 24" style="fill: #f59e0b;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                            </div>
                            <div>
                                <div class="chart-title">Top Ubicaciones</div>
                                <div class="chart-subtitle">5 ubicaciones con más incidencias</div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="chart-ubicaciones" role="img" aria-label="Gráfico de top 5 ubicaciones con más incidencias"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title-group">
                            <div class="chart-icon" style="background: rgba(59, 130, 246, 0.1);">
                                <svg viewBox="0 0 24 24" style="fill: #3b82f6;"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                            </div>
                            <div>
                                <div class="chart-title">Top Usuarios</div>
                                <div class="chart-subtitle">5 usuarios con más reportes</div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="chart-usuarios" role="img" aria-label="Gráfico de top 5 usuarios con más reportes"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title-group">
                            <div class="chart-icon" style="background: rgba(139, 92, 246, 0.1);">
                                <svg viewBox="0 0 24 24" style="fill: #8b5cf6;"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z"/></svg>
                            </div>
                            <div>
                                <div class="chart-title">Equipos por Tipo</div>
                                <div class="chart-subtitle">Inventario general</div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="chart-equipos" role="img" aria-label="Gráfico de equipos por tipo - inventario general"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Row 3: Estado + Servicios -->
            <div class="charts-row-3">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title-group">
                            <div class="chart-icon" style="background: rgba(236, 72, 153, 0.1);">
                                <svg viewBox="0 0 24 24" style="fill: #ec4899;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            </div>
                            <div>
                                <div class="chart-title">Incidencias por Estado</div>
                                <div class="chart-subtitle">Estado actual del sistema</div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="chart-estado" role="img" aria-label="Gráfico de incidencias por estado actual"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title-group">
                            <div class="chart-icon" style="background: rgba(6, 182, 212, 0.1);">
                                <svg viewBox="0 0 24 24" style="fill: #06b6d4;"><path d="M12 2l-5.5 9h11L12 2zm0 3.84L13.93 9h-3.87L12 5.84zM17.5 13c-2.49 0-4.5 2.01-4.5 4.5s2.01 4.5 4.5 4.5 4.5-2.01 4.5-4.5-2.01-4.5-4.5-4.5zm0 7c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5zM3 21.5h8v-8H3v8zm2-6h4v4H5v-4z"/></svg>
                            </div>
                            <div>
                                <div class="chart-title">Equipos del Sistema</div>
                                <div class="chart-subtitle">Estado del inventario</div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="chart-equipos-estado" role="img" aria-label="Gráfico de estado del inventario de equipos"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title-group">
                            <div class="chart-icon" style="background: rgba(34, 197, 94, 0.1);">
                                <svg viewBox="0 0 24 24" style="fill: #22c55e;"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                            </div>
                            <div>
                                <div class="chart-title">Resumen Rapido</div>
                                <div class="chart-subtitle">Metricas clave</div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body chart-body--summary">
                        <div id="resumen-rapido" class="resumen-grid">
                            <div class="resumen-item primary">
                                <div class="resumen-value" id="res-abiertos"><?= $initialData['stats']['abiertos'] ?? 0 ?></div>
                                <div class="resumen-label">Abiertos</div>
                            </div>
                            <div class="resumen-item warning">
                                <div class="resumen-value" id="res-proceso"><?= $initialData['stats']['en_proceso'] ?? 0 ?></div>
                                <div class="resumen-label">En Proceso</div>
                            </div>
                            <div class="resumen-item success">
                                <div class="resumen-value" id="res-resueltos"><?= $initialData['stats']['resueltos'] ?? 0 ?></div>
                                <div class="resumen-label">Resueltos</div>
                            </div>
                            <div class="resumen-item muted">
                                <div class="resumen-value" id="res-cerrados"><?= $initialData['stats']['cerrados'] ?? 0 ?></div>
                                <div class="resumen-label">Cerrados</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Chart.js – cdnjs, sin SRI (hash auto-rotado por CDN) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>

    <script>
    window.analisisInitialData = <?= json_encode($initialData, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="<?= $baseUrl ?>public/assets/js/realtime.js"></script>
    <script src="<?= $baseUrl ?>public/assets/js/analisis-charts.js"></script>
<?php require __DIR__ . '/../partials/footer.php'; ?>