<?php
/**
 * API de Tickets para el Usuario
 * Sistema OTI
 */

error_reporting(0);
ini_set('display_errors', 0);

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
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketComment;
use App\Cache\Store as Cache;

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
session_write_close();

$action = $_GET['action'] ?? 'get-ticket';

try {
    $pdo = Database::connect();
    $userId = $_SESSION['user']['id'];
    
    switch ($action) {
        case 'get-ticket':
            $id = $_GET['id'] ?? 0;
            $userId = $_SESSION['user']['id'];
            
            $stmt = $pdo->prepare("
                SELECT t.id, t.code, t.title, t.description, t.status_id, t.priority_id,
                       t.location_id, t.category_id, t.equipment_id, t.assigned_admin_id,
                       t.response_message, t.admin_response_date, t.created_at, t.updated_at,
                       t.closed_at, t.resolved_at,
                       u.nombre as user_name, u.apellidos as user_lastname, u.email as user_email,
                       ts.name as status_name, ts.id as status_id,
                       tp.name as priority_name,
                       c.name as category_name,
                       l.name as location_name,
                       l2.name as area_name,
                       au.nombre as assigned_name, au.apellidos as assigned_lastname,
                       e.name as equipment_name
                FROM oti.tickets t
                LEFT JOIN admin.usuarios u ON t.user_id = u.id
                LEFT JOIN oti.ticket_statuses ts ON t.status_id = ts.id
                LEFT JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
                LEFT JOIN oti.categories c ON t.category_id = c.id
                LEFT JOIN oti.locations l ON t.location_id = l.id
                LEFT JOIN oti.locations l2 ON l.parent_id = l2.id
                LEFT JOIN oti.equipment e ON t.equipment_id = e.id
                LEFT JOIN admin.usuarios au ON t.assigned_admin_id = au.id
                WHERE t.id = :id AND t.user_id = :user_id
                LIMIT 1
            ");
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ticket) {
                $ticket['can_cancel'] = Ticket::canCancel($id);
                $ticket['has_been_seen'] = Ticket::hasBeenSeen($id);
                echo json_encode($ticket);
            } else {
                echo json_encode(['error' => 'Ticket no encontrado o no tienes permiso para verlo']);
            }
            break;
            
        case 'get-activities':
            $id = $_GET['id'] ?? 0;
            
            $stmt = $pdo->prepare("SELECT user_id FROM oti.tickets WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Ticket no encontrado']);
                break;
            }
            
            $activities = Ticket::getActivities($id);
            echo json_encode(['activities' => $activities]);
            break;
            
        case 'get-comments':
            $id = $_GET['id'] ?? 0;
            
            $stmt = $pdo->prepare("SELECT user_id FROM oti.tickets WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Ticket no encontrado']);
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT tc.*, u.nombre, u.apellidos
                FROM oti.ticket_comments tc
                LEFT JOIN admin.usuarios u ON tc.user_id = u.id
                WHERE tc.ticket_id = :ticket_id
                ORDER BY tc.created_at ASC
            ");
            $stmt->execute(['ticket_id' => $id]);
            $comments = $stmt->fetchAll();
            echo json_encode(['comments' => $comments]);
            break;
            
        case 'is-visto':
            $id = $_GET['id'] ?? 0;
            
            $stmt = $pdo->prepare("SELECT user_id FROM oti.tickets WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Ticket no encontrado']);
                break;
            }
            
            $hasBeenSeen = Ticket::hasBeenSeen($id);
            echo json_encode(['visto' => $hasBeenSeen]);
            break;
            
        case 'cancel-ticket':
            $id = $_POST['id'] ?? $_GET['id'] ?? 0;
            $reason = $_POST['reason'] ?? '';
            
            $result = Ticket::cancel($id, $userId);
            Cache::markDirty('dashboard');
            echo json_encode($result);
            break;
            
        case 'delete-ticket':
            $id = $_POST['id'] ?? $_GET['id'] ?? 0;
            
            $result = Ticket::deleteTicket($id, $userId);
            Cache::markDirty('dashboard');
            echo json_encode($result);
            break;
            
        case 'get-user-equipment':
            $equipment = \App\Models\Equipment::getByUserId($userId);
            echo json_encode(['equipment' => $equipment]);
            break;

        case 'send-notification':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Método no permitido']);
                break;
            }
            $ticketId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            $sendEmail = $_POST['send_email'] ?? '0';

            if ($ticketId <= 0 || !$message) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                break;
            }

            $stmt = $pdo->prepare("SELECT t.user_id, t.title, u.nombre, u.apellidos, u.email FROM oti.tickets t LEFT JOIN admin.usuarios u ON t.user_id = u.id WHERE t.id = :id");
            $stmt->execute(['id' => $ticketId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) {
                echo json_encode(['success' => false, 'error' => 'Ticket no encontrado']);
                break;
            }

            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM oti.tickets WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $ticketId, 'user_id' => $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso para modificar este ticket']);
                break;
            }

            try {
                // Update ticket response_message
                $stmt = $pdo->prepare("UPDATE oti.tickets SET response_message = :message, updated_at = NOW() WHERE id = :id");
                $stmt->execute(['message' => $message, 'id' => $ticketId]);

                // Create in-app notification for the user
                $fullMsg = 'Has actualizado tu ticket: ' . $message;
                \App\Models\Notification::create($ticket['user_id'], 'Actualización de Ticket', $fullMsg, 'ticket_response', $ticketId);

                // Create activity
                \App\Models\TicketActivity::create($ticketId, $userId, 'notificacion_enviada', 'Notificación: ' . $message);

                // Optionally log email intent
                if ($sendEmail === '1') {
                    error_log("[Notificación] Se enviaría email a " . $ticket['email'] . " sobre ticket " . $ticketId);
                }

                \App\Cache\Store::markDirty('dashboard');
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                error_log($e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Error al enviar notificación']);
            }
            break;

        case 'update-ticket':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Método no permitido']);
                break;
            }
            $ticketId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            $responseMessage = trim($_POST['response_message'] ?? '');
            $statusId = (int)($_POST['status_id'] ?? 0);

            if ($ticketId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de ticket inválido']);
                break;
            }

            // Verify ownership
            $stmt = $pdo->prepare("SELECT user_id, status_id, title FROM oti.tickets WHERE id = :id");
            $stmt->execute(['id' => $ticketId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticket || $ticket['user_id'] != $userId) {
                echo json_encode(['success' => false, 'error' => 'Ticket no encontrado o no tienes permiso']);
                break;
            }

            try {
                $updateFields = [];
                $params = ['id' => $ticketId];

                if ($responseMessage) {
                    $updateFields[] = "response_message = :response_message";
                    $params['response_message'] = $responseMessage;
                }

                if ($statusId > 0) {
                    $updateFields[] = "status_id = :status_id";
                    $params['status_id'] = $statusId;
                }

                if (!empty($updateFields)) {
                    $updateFields[] = "updated_at = NOW()";
                    $query = "UPDATE oti.tickets SET " . implode(', ', $updateFields) . " WHERE id = :id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                }

                // Create activity
                TicketActivity::create($ticketId, $userId, 'actualizado', 'Ticket actualizado por el usuario');

                // Send self-notification
                if ($responseMessage) {
                    \App\Models\Notification::create($userId, 'Ticket Actualizado: ' . $ticket['title'], 'Has agregado información a tu ticket: ' . $responseMessage, 'ticket_updated', $ticketId);
                }

                \App\Cache\Store::markDirty('dashboard');
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                error_log($e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Error al actualizar el ticket']);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}
