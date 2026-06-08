<?php
/**
 * API de Notificaciones en tiempo real
 * Sistema OTI
 */

error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');
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
session_write_close();

$userId = $_SESSION['user']['id'] ?? null;
$action = $_GET['action'] ?? '';

try {
    $pdo = Database::connect();

    switch ($action) {
        case 'mark-read':
            $id = $_POST['id'] ?? $_GET['id'] ?? 0;
            \App\Models\Notification::markAsRead($id, $userId);
            echo json_encode(['success' => true]);
            break;

        case 'mark-all-read':
            \App\Models\Notification::markAllAsRead($userId);
            echo json_encode(['success' => true]);
            break;

        case 'delete-notification':
            $id = $_POST['id'] ?? $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("DELETE FROM oti.notifications WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
            echo json_encode(['success' => true]);
            break;

        case 'update-notification':
            $id = $_POST['id'] ?? $_GET['id'] ?? 0;
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            if ($id && $title && $message) {
                $stmt = $pdo->prepare("UPDATE oti.notifications SET title = :title, message = :message WHERE id = :id AND user_id = :user_id");
                $stmt->execute(['title' => $title, 'message' => $message, 'id' => $id, 'user_id' => $userId]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            }
            break;

        case 'get-detail':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("
                SELECT n.*, t.code as ticket_code, t.title as ticket_title
                FROM oti.notifications n
                LEFT JOIN oti.tickets t ON n.ticket_id = t.id
                WHERE n.id = :id AND n.user_id = :user_id
                LIMIT 1
            ");
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
            $notif = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($notif) {
                echo json_encode($notif);
            } else {
                echo json_encode(['error' => 'Notificación no encontrada']);
            }
            break;

        default:
            // Obtener notificaciones
            $stmt = $pdo->prepare("
                SELECT n.*, t.code as ticket_code, t.title as ticket_title
                FROM oti.notifications n
                LEFT JOIN oti.tickets t ON n.ticket_id = t.id
                WHERE n.user_id = :user_id
                ORDER BY n.created_at DESC
                LIMIT 50
            ");
            $stmt->execute(['user_id' => $userId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Contar no leídas
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM oti.notifications WHERE user_id = :user_id AND is_read = false");
            $stmt->execute(['user_id' => $userId]);
            $unreadCount = (int)$stmt->fetchColumn();

            echo json_encode([
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}