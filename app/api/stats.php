<?php
/**
 * API de estadisticas en tiempo real
 * Sistema OTI - Devuelve datos JSON para actualizar el dashboard
 * Version optimizada: APCu cache + ETag/304
 */

error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=15, must-revalidate');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Cache\Store as Cache;

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
session_write_close();

$isOtiAdmin = \App\Services\AuthService::isAdmin();
$userId = $_SESSION['user']['id'] ?? null;
$scope = $_GET['scope'] ?? 'admin';

try {
    $cacheKey = 'stats_admin_' . (($isOtiAdmin && $scope !== 'user') ? 'admin' : 'user_' . $userId);
    $response = Cache::remember($cacheKey, 15, function () use ($isOtiAdmin, $userId, $scope) {
        $pdo = Database::connect();
        $response = [];

        if ($isOtiAdmin && $scope !== 'user') {
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE status_id = 1) as abiertos,
                    COUNT(*) FILTER (WHERE status_id = 2) as en_proceso,
                    COUNT(*) FILTER (WHERE status_id = 3) as resueltos,
                    COUNT(*) FILTER (WHERE status_id = 4) as cerrados
                FROM oti.tickets
            ");
            $response['stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("
                SELECT tp.name, COUNT(t.id) as count,
                CASE tp.id
                    WHEN 1 THEN '#dc2626'
                    WHEN 2 THEN '#f59e0b'
                    WHEN 3 THEN '#10b981'
                    ELSE '#6366f1'
                END as color
                FROM oti.tickets t
                JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
                GROUP BY tp.name, tp.id
                ORDER BY tp.id
            ");
            $response['por_prioridad'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("
                SELECT ts.name, COUNT(t.id) as count
                FROM oti.tickets t
                JOIN oti.ticket_statuses ts ON t.status_id = ts.id
                GROUP BY ts.name, ts.id
                ORDER BY ts.id
            ");
            $response['por_estado'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("
                SELECT t.id, t.code, t.title, t.created_at, 
                       ts.name as status_name, tp.name as priority_name
                FROM oti.tickets t
                JOIN oti.ticket_statuses ts ON t.status_id = ts.id
                JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
                ORDER BY t.created_at DESC
                LIMIT 10
            ");
            $response['tickets_recientes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE status = 'active') as activos,
                    COUNT(*) FILTER (WHERE status = 'maintenance') as mantenimiento,
                    COUNT(*) FILTER (WHERE status = 'inactive') as inactivos
                FROM oti.equipment
                WHERE is_deleted = false
            ");
            $response['equipos'] = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE activo = true) as activos
                FROM admin.usuarios
            ");
            $response['usuarios'] = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("
                SELECT * FROM (
                    SELECT 
                        'ticket' as tipo,
                        t.id,
                        t.code as codigo,
                        t.title as titulo,
                        ts.name as status_name,
                        CASE ts.id
                            WHEN 1 THEN 'ticket-open'
                            WHEN 2 THEN 'ticket-process'
                            WHEN 3 THEN 'ticket-resolved'
                            ELSE 'ticket'
                        END as status_class,
                        CASE ts.id
                            WHEN 1 THEN 'Abierto'
                            WHEN 2 THEN 'En Proceso'
                            WHEN 3 THEN 'Resuelto'
                            ELSE ts.name
                        END as badge,
                        t.created_at as fecha,
                        (u.nombre || ' ' || COALESCE(u.apellidos, '')) as usuario,
                        t.description as descripcion
                    FROM oti.tickets t
                    JOIN oti.ticket_statuses ts ON t.status_id = ts.id
                    LEFT JOIN admin.usuarios u ON t.user_id = u.id
                    ORDER BY t.created_at DESC
                    LIMIT 8
                ) sub
                ORDER BY sub.fecha DESC
            ");
            $rawActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $now = time();
            foreach ($rawActivity as &$item) {
                $diff = $now - strtotime($item['fecha']);
                if ($diff < 60) $item['tiempo'] = 'Hace un momento';
                elseif ($diff < 3600) $item['tiempo'] = 'Hace ' . floor($diff / 60) . 'm';
                elseif ($diff < 86400) $item['tiempo'] = 'Hace ' . floor($diff / 3600) . 'h';
                elseif ($diff < 604800) $item['tiempo'] = 'Hace ' . floor($diff / 86400) . 'd';
                else $item['tiempo'] = date('d/m', strtotime($item['fecha']));
            }
            unset($item);

            $response['actividad_reciente'] = $rawActivity;

            $stmt = $pdo->query("
                SELECT l.name, COUNT(t.id) as count
                FROM oti.tickets t
                JOIN oti.locations l ON t.location_id = l.id
                GROUP BY l.name
                ORDER BY count DESC
                LIMIT 5
            ");
            $response['por_ubicacion'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("
                SELECT u.nombre || ' ' || COALESCE(u.apellidos, '') as name, COUNT(t.id) as count
                FROM oti.tickets t
                JOIN admin.usuarios u ON t.user_id = u.id
                GROUP BY u.nombre, u.apellidos
                ORDER BY count DESC
                LIMIT 5
            ");
            $response['top_usuarios'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("
                SELECT asset_type, COUNT(*) as count
                FROM oti.equipment
                WHERE is_deleted = false
                GROUP BY asset_type
                ORDER BY count DESC
            ");
            $response['equipos_por_tipo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("
                SELECT TO_CHAR(created_at, 'YYYY-MM') as mes, COUNT(*) as count
                FROM oti.tickets
                WHERE created_at >= NOW() - INTERVAL '6 months'
                GROUP BY TO_CHAR(created_at, 'YYYY-MM')
                ORDER BY mes
            ");
            $response['tickets_por_mes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE status_id = 1) as abiertos,
                    COUNT(*) FILTER (WHERE status_id = 2) as en_proceso,
                    COUNT(*) FILTER (WHERE status_id = 3) as resueltos,
                    COUNT(*) FILTER (WHERE status_id = 4) as cerrados
                FROM oti.tickets
                WHERE user_id = :user_id
            ");
            $stmt->execute(['user_id' => $userId]);
            $response['stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
                SELECT t.id, t.code, t.title, t.created_at, 
                       ts.name as status_name, tp.name as priority_name
                FROM oti.tickets t
                JOIN oti.ticket_statuses ts ON t.status_id = ts.id
                JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
                WHERE t.user_id = :user_id
                ORDER BY t.created_at DESC
                LIMIT 10
            ");
            $stmt->execute(['user_id' => $userId]);
            $response['tickets_recientes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $pdo->query("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM oti.tickets
            WHERE created_at >= NOW() - INTERVAL '30 days'
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $response['ultimos_30_dias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['timestamp'] = time();

        return $response;
    });

    $etag = '"' . md5(json_encode($response)) . '"';
    header('ETag: ' . $etag);

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
        http_response_code(304);
        exit;
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}
