<?php
declare(strict_types=1);

namespace App\Controller\v2;

use App\Controller\BaseController;
use App\Models\Ticket;
use App\Core\Database;

class TicketController extends BaseController
{
    public function list(): void
    {
        $this->redirectIfNotAdmin();

        $filters = [];
        $page = max(1, (int)($this->getQuery('page', '1')));
        $pageSize = min(100, max(1, (int)($this->getQuery('pageSize', '20'))));

        $statusId = $this->getQuery('status_id');
        if ($statusId) $filters['status_id'] = (int)$statusId;

        $search = $this->getQuery('search');
        if ($search) $filters['search'] = $search;

        $priorityId = $this->getQuery('priority_id');
        if ($priorityId) $filters['priority_id'] = (int)$priorityId;

        $assignedAdminId = $this->getQuery('assigned_admin_id');
        if ($assignedAdminId) $filters['assigned_admin_id'] = (int)$assignedAdminId;

        $dateFrom = $this->getQuery('date_from');
        if ($dateFrom) $filters['date_from'] = $dateFrom;

        $dateTo = $this->getQuery('date_to');
        if ($dateTo) $filters['date_to'] = $dateTo;

        $ticketsList = Ticket::getAll($filters, $page, $pageSize);
        $totalCount = Ticket::getTotalCount($filters);
        $totalPages = (int)ceil($totalCount / $pageSize);

        $this->json([
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
    }

    public function get(): void
    {
        $this->redirectIfNotAuth();
        $id = (int)($this->getQuery('id', '0'));
        if ($id <= 0) {
            $this->error('ID de ticket requerido');
        }

        $pdo = Database::connect();
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
        $ticket = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($ticket) {
            $this->json($ticket);
        } else {
            $this->error('Ticket no encontrado', 404);
        }
    }

    public function update(): void
    {
        $this->redirectIfNotAdmin();

        $id = (int)($this->getPost('id', '0'));
        if ($id <= 0) {
            $this->error('ID de ticket requerido');
        }

        $statusMap = [
            'abierto' => 1,
            'en_proceso' => 2,
            'resuelto' => 3,
            'cerrado' => 4
        ];

        $estado = $this->getPost('estado', 'abierto');
        $prioridad = $this->getPost('prioridad');
        $asignado = $this->getPost('asignado');
        $respuesta = $this->getPost('respuesta', '');
        $enviarMensaje = $this->getPost('enviar_mensaje') === '1' || $this->getPost('enviar_mensaje') === 'true';

        $tiempoValor = max(0, (int)($this->getPost('tiempo_valor', '0')));
        $tiempoUnidad = $this->getPost('tiempo_unidad', 'horas');

        $updates = [];
        $params = ['id' => $id];

        $updates[] = "status_id = :status_id";
        $params['status_id'] = $statusMap[$estado] ?? 1;

        if ($prioridad) {
            $updates[] = "priority_id = :prioridad";
            $params['prioridad'] = (int)$prioridad;
        }

        if ($asignado !== null) {
            $updates[] = "assigned_admin_id = :asignado";
            $params['asignado'] = $asignado ? (int)$asignado : null;
        }

        $hours = 0;
        if ($tiempoValor > 0) {
            $hours = match ($tiempoUnidad) {
                'dias' => $tiempoValor * 24,
                'semanas' => $tiempoValor * 168,
                'meses' => $tiempoValor * 720,
                default => $tiempoValor
            };
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

        $pdo = Database::connect();
        try {
            $pdo->beginTransaction();

            $sql = "UPDATE oti.tickets SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if (!empty($respuesta)) {
                $commentStmt = $pdo->prepare("INSERT INTO oti.ticket_comments (ticket_id, user_id, comment, created_at) VALUES (:ticket_id, :user_id, :comment, NOW())");
                $commentStmt->execute([
                    'ticket_id' => $id,
                    'user_id' => $this->getUserId(),
                    'comment' => strip_tags($respuesta)
                ]);
            }

            if ($estado === 'cerrado' && empty($respuesta)) {
                $commentStmt = $pdo->prepare("INSERT INTO oti.ticket_comments (ticket_id, user_id, comment, created_at) VALUES (:ticket_id, :user_id, 'Ticket cerrado por el administrador', NOW())");
                $commentStmt->execute([
                    'ticket_id' => $id,
                    'user_id' => $this->getUserId()
                ]);
            }

            $stmtUser = $pdo->prepare("SELECT user_id, code FROM oti.tickets WHERE id = :id");
            $stmtUser->execute(['id' => $id]);
            $ticketData = $stmtUser->fetch(\PDO::FETCH_ASSOC);

            if ($ticketData) {
                $notifMessage = $enviarMensaje && !empty($respuesta)
                    ? "El administrador ha respondido a tu ticket {$ticketData['code']}: \"{$respuesta}\""
                    : "Tu ticket {$ticketData['code']} ha sido actualizado por el administrador";

                \App\Models\Notification::create(
                    $ticketData['user_id'],
                    $enviarMensaje && !empty($respuesta) ? 'Respuesta de Soporte' : 'Ticket Actualizado',
                    $notifMessage,
                    $enviarMensaje && !empty($respuesta) ? 'ticket_response' : 'ticket_updated',
                    $id
                );
            }

            $pdo->commit();
            $this->success(null, 'Ticket actualizado');
        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            $this->error('Error al actualizar ticket', 500);
        }
    }

    public function delete(): void
    {
        $this->redirectIfNotAdmin();

        $id = (int)($this->getQuery('id', '0'));
        if ($id <= 0) {
            $this->error('ID de ticket requerido');
        }

        $pdo = Database::connect();
        try {
            $pdo->beginTransaction();
            $stmtComments = $pdo->prepare("DELETE FROM oti.ticket_comments WHERE ticket_id = :id");
            $stmtComments->execute(['id' => $id]);
            $stmt = $pdo->prepare("DELETE FROM oti.tickets WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $pdo->commit();
            $this->success(null, 'Ticket eliminado');
        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            $this->error('Error al eliminar ticket', 500);
        }
    }

    public function priorities(): void
    {
        $this->redirectIfNotAdmin();
        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT id, name FROM oti.ticket_priorities ORDER BY id");
        $this->json($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function usuarios(): void
    {
        $this->redirectIfNotAdmin();
        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM admin.usuarios u WHERE u.activo = true ORDER BY u.nombre");
        $this->json($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function renderListView(): void
    {
        $this->redirectIfNotAdmin();
        $filters = [];
        $statusParam = $this->getQuery('status');
        if ($statusParam) {
            $statusMap = ['abiertos' => 1, 'proceso' => 2, 'resueltos' => 3, 'cerrados' => 4];
            if (isset($statusMap[$statusParam])) {
                $filters['status_id'] = $statusMap[$statusParam];
            }
        }
        $tickets = Ticket::getAll($filters);
        $this->view('v2/tickets/index.php', [
            'tickets' => $tickets,
            'tituloPagina' => 'Tickets - Sistema OTI',
            'paginaActual' => 'admin-tickets'
        ]);
    }
}
