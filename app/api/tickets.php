<?php
/**
 * API de Tickets
 * Sistema OTI - Gestión de tickets del admin
 */

error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\Ticket;
use App\Models\User;
use App\Models\TicketActivity;
use App\Core\Database;
use App\Cache\Store as Cache;

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$roleName = $_SESSION['user']['role_name'] ?? '';
$isAdmin = \App\Services\AuthService::isAdmin();

if (!$isAdmin) {
    echo json_encode(['error' => 'No es admin']);
    exit;
}
session_write_close();

$action = $_GET['action'] ?? 'list';

try {
    $pdo = Database::connect();
    
    switch ($action) {
        case 'list':
            $filters = [];
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $pageSize = isset($_GET['pageSize']) ? min(100, max(1, (int)$_GET['pageSize'])) : 20;

            if (!empty($_GET['status_id'])) {
                $filters['status_id'] = (int)$_GET['status_id'];
            }
            if (!empty($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }
            if (!empty($_GET['priority_id'])) {
                $filters['priority_id'] = (int)$_GET['priority_id'];
            }
            if (!empty($_GET['assigned_admin_id'])) {
                $filters['assigned_admin_id'] = (int)$_GET['assigned_admin_id'];
            }
            if (!empty($_GET['date_from'])) {
                $filters['date_from'] = $_GET['date_from'];
            }
            if (!empty($_GET['date_to'])) {
                $filters['date_to'] = $_GET['date_to'];
            }

            // Auto-apply area filter for admin views when no explicit filters are set
            // This ensures admins see only tickets from their area by default
            $hasExplicitFilters = !empty($_GET['status_id']) || !empty($_GET['search']) || 
                               !empty($_GET['priority_id']) || !empty($_GET['assigned_admin_id']) ||
                               !empty($_GET['date_from']) || !empty($_GET['date_to']);
            
            if (!$hasExplicitFilters && !empty($_SESSION['user']['area_name'])) {
                // Apply area filter only if no explicit filters are set
                $filters['area_name'] = $_SESSION['user']['area_name'];
            }
            
            $ticketsList = Ticket::getAll($filters, $page, $pageSize);
            $totalCount = Ticket::getTotalCount($filters);
            $totalPages = ceil($totalCount / $pageSize);
            
            echo json_encode([
                'success' => true, 
                'tickets' => $ticketsList,
                'pagination' => [
                    'currentPage' => $page,
                    'pageSize' => $pageSize,
                    'totalCount' => $totalCount,
                    'totalPages' => $totalPages,
                    'hasNextPage' => $page < $totalPages,
                    'hasPrevPage' => $page > 1
                ]
            ]);
            break;

        case 'get-ticket':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("
                SELECT t.*, 
                       u.nombre as user_name, u.apellidos as user_lastname, u.email as user_email,
                       up.phone as user_phone,
                       t.code, t.title, t.description, ts.name as status_name, tp.name as priority_name, c.name as category_name,
                       l.name as location_name,
                       l2.name as area_name,
                       l3.name as sede_name,
                       au.nombre as assigned_name, au.apellidos as assigned_lastname
                FROM oti.tickets t
                LEFT JOIN admin.usuarios u ON t.user_id = u.id
                LEFT JOIN oti.user_profiles up ON u.id = up.user_id
                LEFT JOIN oti.locations l ON t.location_id = l.id
                LEFT JOIN oti.locations l2 ON l.parent_id = l2.id
                LEFT JOIN oti.locations l3 ON l2.parent_id = l3.id
                LEFT JOIN oti.ticket_statuses ts ON t.status_id = ts.id
                LEFT JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
                LEFT JOIN oti.categories c ON t.category_id = c.id
                LEFT JOIN admin.usuarios au ON t.assigned_admin_id = au.id
                WHERE t.id = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ticket) {
                echo json_encode($ticket);
            } else {
                echo json_encode(['error' => 'Ticket no encontrado']);
            }
            break;
            
        case 'get-priorities':
            $stmt = $pdo->query("SELECT id, name FROM oti.ticket_priorities ORDER BY id");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'get-usuarios':
            $excludeWithEquipment = isset($_GET['exclude_with_equipment']) && $_GET['exclude_with_equipment'] === '1';

            $sql = "SELECT u.id, u.nombre, u.apellidos, up.location_id
                    FROM admin.usuarios u
                    LEFT JOIN oti.user_profiles up ON u.id = up.user_id
                    WHERE u.activo = true";

            if ($excludeWithEquipment) {
                $sql .= " AND NOT EXISTS (
                    SELECT 1 FROM oti.equipment e
                    WHERE e.assigned_user_id = u.id
                      AND e.is_deleted = false
                )";
            }

            $sql .= " ORDER BY u.nombre";
            $stmt = $pdo->query($sql);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'get-activities':
            $id = $_GET['id'] ?? 0;
            if (!$id) {
                echo json_encode(['error' => 'ID requerido']);
                break;
            }
            $activities = TicketActivity::getByTicketId($id);
            echo json_encode(['activities' => $activities]);
            break;

        case 'mark-viewed':
            $id = $_GET['id'] ?? 0;
            if (!$id) {
                echo json_encode(['error' => 'ID requerido']);
                break;
            }
            TicketActivity::create($id, $_SESSION['user']['id'], 'visto_admin', 'Ticket revisado por el administrador');
            echo json_encode(['success' => true]);
            break;

        case 'update-ticket':
            $id = $_POST['id'] ?? $_GET['id'] ?? 0;
            $estado = $_POST['estado'] ?? 'abierto';
            $prioridad = $_POST['prioridad'] ?? null;
            $asignado = $_POST['asignado'] ?? null;
            $respuesta = $_POST['respuesta'] ?? '';
            $enviar_mensaje = isset($_POST['enviar_mensaje']) && ($_POST['enviar_mensaje'] === '1' || $_POST['enviar_mensaje'] === 'true');
            
            $tiempo_valor = isset($_POST['tiempo_valor']) ? (int)$_POST['tiempo_valor'] : 0;
            $tiempo_unidad = $_POST['tiempo_unidad'] ?? 'horas';
            
            $statusMap = [
                'abierto' => 1,
                'en_proceso' => 2,
                'resuelto' => 3,
                'cerrado' => 4
            ];
            
            $updates = [];
            $params = ['id' => $id];
            
            if ($estado) {
                $updates[] = "status_id = :status_id";
                $params['status_id'] = $statusMap[$estado] ?? 1;
            }
            
            if ($prioridad) {
                $updates[] = "priority_id = :prioridad";
                $params['prioridad'] = $prioridad;
            }
            
            if ($asignado !== null) {
                $updates[] = "assigned_admin_id = :asignado";
                $params['asignado'] = $asignado ?: null;
            }
            
            $hours = 0;
            if ($tiempo_valor > 0) {
                if ($tiempo_unidad === 'horas') {
                    $hours = $tiempo_valor;
                } elseif ($tiempo_unidad === 'dias') {
                    $hours = $tiempo_valor * 24;
                } elseif ($tiempo_unidad === 'semanas') {
                    $hours = $tiempo_valor * 168;
                } elseif ($tiempo_unidad === 'meses') {
                    $hours = $tiempo_valor * 720;
                }
            }
            
            if ($hours > 0) {
                $updates[] = "resolution_time_hours = :resolution_time_hours";
                $params['resolution_time_hours'] = $hours;
            }
            
            if ($estado === 'cerrado' || $estado === 'resuelto') {
                $updates[] = "closed_at = NOW()";
                $updates[] = "resolved_at = NOW()";
            }
            
            $updates[] = "updated_at = NOW()";
            
            $sql = "UPDATE oti.tickets SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            Cache::markDirty('dashboard');
            
            if (!empty($respuesta)) {
                // Agregar comentario del administrador
                $commentStmt = $pdo->prepare("INSERT INTO oti.ticket_comments (ticket_id, user_id, comment, created_at) VALUES (:ticket_id, :user_id, :comment, NOW())");
                $commentStmt->execute([
                    'ticket_id' => $id,
                    'user_id' => $_SESSION['user']['id'],
                    'comment' => $respuesta
                ]);
            }
            
            if ($estado === 'cerrado' && empty($respuesta)) {
                // Agregar comentario de cierre automático
                $commentStmt = $pdo->prepare("INSERT INTO oti.ticket_comments (ticket_id, user_id, comment, created_at) VALUES (:ticket_id, :user_id, 'Ticket cerrado por el administrador', NOW())");
                $commentStmt->execute([
                    'ticket_id' => $id,
                    'user_id' => $_SESSION['user']['id']
                ]);
            }
            
            // Registrar actividades en el timeline
            $adminId = $_SESSION['user']['id'];

            if (!empty($respuesta)) {
                TicketActivity::create($id, $adminId, 'comentario', 'Respuesta enviada al usuario');
            }

            if ($estado === 'en_proceso') {
                TicketActivity::create($id, $adminId, 'proceso', 'Ticket en proceso');
            } elseif ($estado === 'resuelto') {
                TicketActivity::create($id, $adminId, 'resuelto', 'Ticket resuelto');
            } elseif ($estado === 'cerrado') {
                TicketActivity::create($id, $adminId, 'cerrado', 'Ticket cerrado por el administrador');
            } elseif ($estado === 'abierto' && !$respuesta) {
                TicketActivity::create($id, $adminId, 'reabierto', 'Ticket reabierto');
            }

            if ($asignado !== null && $asignado !== '') {
                TicketActivity::create($id, $adminId, 'asignado', 'Técnico asignado al ticket');
            }

            if ($prioridad) {
                TicketActivity::create($id, $adminId, 'prioridad', 'Prioridad actualizada');
            }

            // Send real-time notification to the user
            $stmtUser = $pdo->prepare("SELECT user_id, code FROM oti.tickets WHERE id = :id");
            $stmtUser->execute(['id' => $id]);
            $ticketData = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($ticketData) {
                $ticketUserId = $ticketData['user_id'];
                $ticketCode = $ticketData['code'];
                
                if ($enviar_mensaje && !empty($respuesta)) {
                    \App\Models\Notification::create(
                        $ticketUserId,
                        'Respuesta de Soporte',
                        "El administrador ha respondido a tu ticket {$ticketCode}: \"{$respuesta}\"",
                        'ticket_response',
                        $id
                    );
                } else {
                    \App\Models\Notification::create(
                        $ticketUserId,
                        'Ticket Actualizado',
                        "Tu ticket {$ticketCode} ha sido actualizado por el administrador",
                        'ticket_updated',
                        $id
                    );
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Ticket actualizado']);
            break;
            
        case 'delete-ticket':
            $id = $_GET['id'] ?? 0;
            
            // Registrar actividad antes de eliminar
            TicketActivity::create($id, $_SESSION['user']['id'], 'cancelado', 'Ticket cancelado/eliminado por el administrador');
            
            $pdo->beginTransaction();
            
            // Delete associated comments first to avoid foreign key violations
            $stmtComments = $pdo->prepare("DELETE FROM oti.ticket_comments WHERE ticket_id = :id");
            $stmtComments->execute(['id' => $id]);
            
            // Delete the ticket itself
            $stmt = $pdo->prepare("DELETE FROM oti.tickets WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            $pdo->commit();
            Cache::markDirty('dashboard');
            
            echo json_encode(['success' => true, 'message' => 'Ticket eliminado']);
            break;
            
        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}