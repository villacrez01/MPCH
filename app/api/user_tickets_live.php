<?php
/**
 * API de Tickets en Vivo para el Usuario
 * Sistema OTI - Retorna tickets con stats para actualización automática
 */

error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Models\Ticket;

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
session_write_close();

try {
    $pdo = Database::connect();
    $userId = $_SESSION['user']['id'];
    
    $stmt = $pdo->prepare("
        SELECT t.id, t.code, t.title, t.description, t.created_at, t.updated_at,
               t.status_id, t.priority_id, t.location_id, t.category_id,
               t.assigned_admin_id, t.response_message,
               ts.name as status_name, tp.name as priority_name,
               c.name as category_name,
               l.name as location_name,
               u.nombre as user_name, u.apellidos as user_lastname,
               au.nombre as assigned_name, au.apellidos as assigned_lastname
        FROM oti.tickets t
        LEFT JOIN admin.usuarios u ON t.user_id = u.id
        LEFT JOIN oti.ticket_statuses ts ON t.status_id = ts.id
        LEFT JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
        LEFT JOIN oti.categories c ON t.category_id = c.id
        LEFT JOIN oti.locations l ON t.location_id = l.id
        LEFT JOIN admin.usuarios au ON t.assigned_admin_id = au.id
        WHERE t.user_id = :user_id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute(['user_id' => $userId]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats = Ticket::getStats(['user_id' => $userId]);
    
    $response = [
        'tickets' => $tickets,
        'stats' => $stats,
        'last_update' => date('Y-m-d H:i:s'),
        'total' => count($tickets)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}