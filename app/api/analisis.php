<?php
/**
 * API de Análisis de Datos
 * Sistema OTI - Estadísticas completas en tiempo real
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

try {
    $pdo = Database::connect();
    
    // Stats generales de tickets
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE status_id = 1) as abiertos,
            COUNT(*) FILTER (WHERE status_id = 2) as en_proceso,
            COUNT(*) FILTER (WHERE status_id = 3) as resueltos,
            COUNT(*) FILTER (WHERE status_id = 4) as cerrados
        FROM oti.tickets
    ");
    $ticketStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Tickets por prioridad
    $stmt = $pdo->query("
        SELECT tp.name, COUNT(t.id) as count
        FROM oti.tickets t
        JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
        GROUP BY tp.name, tp.id
        ORDER BY tp.id
    ");
    $ticketsPorPrioridad = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tickets por estado
    $stmt = $pdo->query("
        SELECT ts.name, COUNT(t.id) as count
        FROM oti.tickets t
        JOIN oti.ticket_statuses ts ON t.status_id = ts.id
        GROUP BY ts.name, ts.id
        ORDER BY ts.id
    ");
    $ticketsPorEstado = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tickets por tipo de servicio
    $stmt = $pdo->query("
        SELECT st.name, COUNT(t.id) as count
        FROM oti.tickets t
        JOIN oti.service_types st ON t.service_type_id = st.id
        GROUP BY st.name
        ORDER BY count DESC
        LIMIT 10
    ");
    $ticketsPorServicio = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tickets por ubicación
    $stmt = $pdo->query("
        SELECT l.name, COUNT(t.id) as count
        FROM oti.tickets t
        JOIN oti.locations l ON t.location_id = l.id
        GROUP BY l.name
        ORDER BY count DESC
        LIMIT 10
    ");
    $ticketsPorUbicacion = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tickets últimos 30 días
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM oti.tickets
        WHERE created_at >= NOW() - INTERVAL '30 days'
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $ticketsUltimos30Dias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tickets por mes (últimos 6 meses)
    $stmt = $pdo->query("
        SELECT TO_CHAR(created_at, 'YYYY-MM') as mes, COUNT(*) as count
        FROM oti.tickets
        WHERE created_at >= NOW() - INTERVAL '6 months'
        GROUP BY TO_CHAR(created_at, 'YYYY-MM')
        ORDER BY mes
    ");
    $ticketsPorMes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top usuarios con más tickets
    $stmt = $pdo->query("
        SELECT u.nombre, u.apellidos, COUNT(t.id) as count
        FROM oti.tickets t
        JOIN admin.usuarios u ON t.user_id = u.id
        GROUP BY u.nombre, u.apellidos
        ORDER BY count DESC
        LIMIT 10
    ");
    $topUsuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tiempo promedio de resolución
    $stmt = $pdo->query("
        SELECT AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 3600) as horas_promedio
        FROM oti.tickets
        WHERE resolved_at IS NOT NULL
    ");
    $tiempoPromedio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Stats de equipos
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE status = 'active') as activos,
            COUNT(*) FILTER (WHERE status = 'maintenance') as mantenimiento,
            COUNT(*) FILTER (WHERE status = 'inactive') as inactivos
        FROM oti.equipment WHERE is_deleted = false
    ");
    $equipmentStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Equipos por tipo
    $stmt = $pdo->query("
        SELECT asset_type, COUNT(*) as count
        FROM oti.equipment
        WHERE is_deleted = false
        GROUP BY asset_type
        ORDER BY count DESC
    ");
    $equiposPorTipo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Stats de usuarios
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE activo = true) as activos
        FROM admin.usuarios
    ");
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Tasa de resolución
    $tasaResolucion = $ticketStats['total'] > 0 
        ? round(($ticketStats['resueltos'] + $ticketStats['cerrados']) / $ticketStats['total'] * 100, 1)
        : 0;
    
    echo json_encode([
        'ticket_stats' => $ticketStats,
        'tickets_por_prioridad' => $ticketsPorPrioridad,
        'tickets_por_estado' => $ticketsPorEstado,
        'tickets_por_servicio' => $ticketsPorServicio,
        'tickets_por_ubicacion' => $ticketsPorUbicacion,
        'tickets_ultimos_30_dias' => $ticketsUltimos30Dias,
        'tickets_por_mes' => $ticketsPorMes,
        'top_usuarios' => $topUsuarios,
        'tiempo_promedio_horas' => round($tiempoPromedio['horas_promedio'] ?? 0, 1),
        'tasa_resolucion' => $tasaResolucion,
        'equipment_stats' => $equipmentStats,
        'equipos_por_tipo' => $equiposPorTipo,
        'user_stats' => $userStats
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}