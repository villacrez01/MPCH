<?php
/**
 * API SSE (Server-Sent Events) para tiempo real
 * Sistema OTI - Stream de datos en vivo
 * Version optimizada: Dirty flag + APCu cache
 */

session_start();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

if (!isset($_SESSION['user'])) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'No autorizado']);
    echo "\n\n";
    flush();
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Cache\Store as Cache;

session_write_close();

$isOtiAdmin = \App\Services\AuthService::isAdmin();
$userId = $_SESSION['user']['id'] ?? null;
$scope = $_GET['scope'] ?? 'admin';

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) return 'Hace un momento';
    if ($diff < 3600) return 'Hace ' . floor($diff / 60) . 'm';
    if ($diff < 86400) return 'Hace ' . floor($diff / 3600) . 'h';
    if ($diff < 604800) return 'Hace ' . floor($diff / 86400) . 'd';
    return date('d/m', $timestamp);
}

function getStats($pdo, $isOtiAdmin, $userId, $scope) {
    $response = [];

    try {
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

            foreach ($rawActivity as &$item) {
                $item['tiempo'] = timeAgo($item['fecha']);
            }
            unset($item);

            $response['actividad_reciente'] = $rawActivity;

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

        $response['timestamp'] = time();

    } catch (Exception $e) {
        error_log($e->getMessage());
        $response['error'] = 'Error interno';
    }

    return $response;
}

echo "event: connected\n";
echo "data: " . json_encode(['status' => 'connected', 'timestamp' => time()]) . "\n\n";
flush();

set_time_limit(0);
ignore_user_abort(false);

$statsCacheKey = 'sse_stats_' . (($isOtiAdmin && $scope !== 'user') ? 'admin' : 'user_' . $userId);

while (!connection_aborted()) {
    try {
        if (Cache::dirty('dashboard')) {
            $data = Cache::remember($statsCacheKey, 5, function () use ($isOtiAdmin, $userId, $scope) {
                $pdo = Database::connect();
                return getStats($pdo, $isOtiAdmin, $userId, $scope);
            });

            echo "event: update\n";
            echo "data: " . json_encode($data) . "\n\n";
        } else {
            echo ": heartbeat\n\n";
        }

        flush();

        if (ob_get_level() > 0) {
            ob_end_flush();
        }

    } catch (Exception $e) {
        error_log($e->getMessage());
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'Error interno']) . "\n\n";
        flush();
    }

    sleep(5);
}

echo "event: close\n";
echo "data: " . json_encode(['status' => 'disconnected']) . "\n\n";
flush();
