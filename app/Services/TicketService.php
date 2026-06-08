<?php
/**
 * Servicio de Tickets
 * Sistema OTI - Lógica de negocio para tickets
 */

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketActivity;
use App\Models\Notification;

class TicketService
{
    /**
     * Crea un nuevo ticket
     */
    public static function create($userId, $data)
    {
        $result = Ticket::create([
            'user_id' => $userId,
            'title' => $data['title'],
            'description' => $data['description'],
            'priority_id' => $data['priority_id'] ?? 2,
            'severity_id' => $data['severity_id'] ?? null,
            'service_type_id' => $data['service_type_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'equipment_id' => $data['equipment_id'] ?? null
        ]);

        if ($result['success']) {
            TicketActivity::create($result['id'], $userId, 'created', 'Ticket creado');
        }

        return $result;
    }

    /**
     * Asigna un ticket a un administrador
     */
    public static function assign($ticketId, $adminId, $assignerId)
    {
        $ticket = Ticket::findById($ticketId);
        
        if (!$ticket) {
            return ['success' => false, 'error' => 'Ticket no encontrado'];
        }

        $result = Ticket::assign($ticketId, $adminId);

        if ($result) {
            TicketActivity::create($ticketId, $assignerId, 'assigned', "Ticket asignado a administrador");
            
            Notification::create(
                $ticket['user_id'],
                'Ticket Asignado',
                'Tu ticket ha sido asignado a un técnico',
                'ticket_assigned',
                $ticketId
            );
        }

        return ['success' => $result];
    }

    /**
     * Responde a un ticket
     */
    public static function respond($ticketId, $adminId, $response, $assignToAdmin = true)
    {
        $ticket = Ticket::findById($ticketId);
        
        if (!$ticket) {
            return ['success' => false, 'error' => 'Ticket no encontrado'];
        }

        $updateData = [
            'response_message' => $response,
            'admin_response_date' => date('Y-m-d H:i:s')
        ];

        if ($assignToAdmin && empty($ticket['assigned_admin_id'])) {
            $updateData['assigned_admin_id'] = $adminId;
            $updateData['status_id'] = 2;
        }

        $result = Ticket::update($ticketId, $updateData);

        if ($result) {
            TicketActivity::create($ticketId, $adminId, 'response', 'Respuesta agregada al ticket');
            
            Notification::notifyTicketResponse($ticketId, $ticket['user_id']);
        }

        return ['success' => $result];
    }

    /**
     * Cambia el estado de un ticket
     */
    public static function changeStatus($ticketId, $statusId, $userId)
    {
        $ticket = Ticket::findById($ticketId);
        
        if (!$ticket) {
            return ['success' => false, 'error' => 'Ticket no encontrado'];
        }

        $result = Ticket::changeStatus($ticketId, $statusId);

        if ($result) {
            $statuses = TicketStatus::getAll();
            $statusName = '';
            foreach ($statuses as $s) {
                if ($s['id'] == $statusId) {
                    $statusName = $s['name'];
                    break;
                }
            }

            TicketActivity::create($ticketId, $userId, 'status_changed', "Estado cambiado a: {$statusName}");
            
            Notification::notifyStatusChange($ticketId, $ticket['user_id'], $statusName);
        }

        return ['success' => $result];
    }

    /**
     * Agrega un comentario a un ticket
     */
    public static function addComment($ticketId, $userId, $comment)
    {
        $result = TicketComment::create($ticketId, $userId, $comment);

        if ($result['success']) {
            TicketActivity::create($ticketId, $userId, 'comment', 'Nuevo comentario agregado');
            
            $ticket = Ticket::findById($ticketId);
            if ($ticket['assigned_admin_id'] && $ticket['user_id'] != $userId) {
                Notification::create(
                    $ticket['assigned_admin_id'],
                    'Nuevo Comentario',
                    'Se ha agregado un comentario a un ticket',
                    'comment_added',
                    $ticketId
                );
            }
        }

        return $result;
    }

    /**
     * Obtiene estadísticas para dashboard
     */
    public static function getDashboardStats($userId = null, $isAdmin = false)
    {
        $filters = [];
        
        if (!$isAdmin && $userId) {
            $filters['user_id'] = $userId;
        } elseif ($isAdmin && $userId) {
            $filters['assigned_admin_id'] = $userId;
        }

        $stats = Ticket::getStats($filters);
        
        $stats['por_prioridad'] = Ticket::getByPriority();
        $stats['por_estado'] = Ticket::getByStatus();
        $stats['ultimos_30_dias'] = Ticket::getLast30Days();

        return $stats;
    }

    /**
     * Obtiene tickets recientes
     */
    public static function getRecent($limit = 5, $userId = null, $isAdmin = false)
    {
        $filters = [];
        
        if (!$isAdmin && $userId) {
            $filters['user_id'] = $userId;
        } elseif ($isAdmin && $userId) {
            $filters['assigned_admin_id'] = $userId;
        }

        return Ticket::getAll($filters, 1, $limit);
    }
}

class TicketStatus
{
    public static function getAll()
    {
        $pdo = \App\Core\Database::connect();
        $stmt = $pdo->query("SELECT * FROM oti.ticket_statuses ORDER BY id");
        return $stmt->fetchAll() ?: [];
    }
    
    public static function findById($id)
    {
        $pdo = \App\Core\Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM oti.ticket_statuses WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    public static function isFinal($statusId)
    {
        $status = self::findById($statusId);
        return $status && ($status['is_final'] ?? false);
    }
}