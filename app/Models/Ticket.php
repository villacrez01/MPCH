<?php
/**
 * Modelo de Tickets
 * Sistema OTI - Gestión de tickets de soporte
 */

namespace App\Models;

use App\Core\Model;
use PDO;

class Ticket extends Model
{
    /**
     * Obtiene un ticket por su ID
     */
    public static function findById($id)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   ts.name as status_name,
                   tp.name as priority_name,
                   tse.name as severity_name,
                   st.name as service_type_name,
                   l.name as location_name,
                   c.name as category_name,
                   e.name as equipment_name,
                   u.nombre as user_name,
                   u.apellidos as user_lastname,
                   u.email as user_email,
                   a.nombre as admin_name,
                   a.apellidos as admin_lastname
            FROM oti.tickets t
            LEFT JOIN oti.ticket_statuses ts ON t.status_id = ts.id
            LEFT JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
            LEFT JOIN oti.ticket_severities tse ON t.severity_id = tse.id
            LEFT JOIN oti.service_types st ON t.service_type_id = st.id
            LEFT JOIN oti.locations l ON t.location_id = l.id
            LEFT JOIN oti.categories c ON t.category_id = c.id
            LEFT JOIN oti.equipment e ON t.equipment_id = e.id
            LEFT JOIN admin.usuarios u ON t.user_id = u.id
            LEFT JOIN admin.usuarios a ON t.assigned_admin_id = a.id
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Obtiene un ticket por su código
     */
    public static function findByCode($code)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT * FROM oti.tickets WHERE code = :code LIMIT 1");
        $stmt->execute(['code' => $code]);
        return $stmt->fetch();
    }

    /**
     * Obtiene todos los tickets (con filtros opcionales)
     */
    public static function getAll($filters = [], $page = 1, $pageSize = 20)
    {
        $pdo = self::db();
        
        $where = "WHERE 1=1";
        $params = [];
        
        // Track if we need location joins for area filtering
        $needsLocationJoins = !empty($filters['area_name']);
        
        if (array_key_exists('status_id', $filters) && $filters['status_id'] !== '') {
            $where .= " AND t.status_id = :status_id";
            $params['status_id'] = $filters['status_id'];
        }
        
        if (array_key_exists('priority_id', $filters) && $filters['priority_id'] !== '') {
            $where .= " AND t.priority_id = :priority_id";
            $params['priority_id'] = $filters['priority_id'];
        }
        
        if (array_key_exists('user_id', $filters) && $filters['user_id'] !== '') {
            $where .= " AND t.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (array_key_exists('assigned_admin_id', $filters) && $filters['assigned_admin_id'] !== '') {
            $where .= " AND t.assigned_admin_id = :assigned_admin_id";
            $params['assigned_admin_id'] = $filters['assigned_admin_id'];
        }
        
        if (!empty($filters['search'])) {
            $where .= " AND (t.title ILIKE :search OR t.code ILIKE :search OR t.description ILIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $where .= " AND t.created_at >= :date_from";
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where .= " AND t.created_at <= :date_to";
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        
        if (!empty($filters['area_name'])) {
            $where .= " AND l2.name = :area_name";
            $params['area_name'] = $filters['area_name'];
        }
        
        $page = max(1, (int)$page);
        $pageSize = min(100, max(1, (int)$pageSize));
        $offset = ($page - 1) * $pageSize;
        
        // Base query with location joins if needed for area filtering
        $query = "
            SELECT t.*, 
                   ts.name as status_name,
                   tp.name as priority_name,
                   u.nombre as user_name,
                   u.apellidos as user_lastname,
                   a.nombre as admin_name
            FROM oti.tickets t
            LEFT JOIN oti.ticket_statuses ts ON t.status_id = ts.id
            LEFT JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
            LEFT JOIN admin.usuarios u ON t.user_id = u.id
            LEFT JOIN admin.usuarios a ON t.assigned_admin_id = a.id";
            
        // Add location joins if needed for area filtering
        if ($needsLocationJoins) {
            $query .= "
                LEFT JOIN oti.locations l ON t.location_id = l.id
                LEFT JOIN oti.locations l2 ON l.parent_id = l2.id";
        }
        
        $query .= "
            {$where}
            ORDER BY t.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene el total de tickets (con filtros opcionales)
     */
    public static function getTotalCount($filters = [])
    {
        $pdo = self::db();
        
        $where = "WHERE 1=1";
        $params = [];
        
        // Track if we need location joins for area filtering
        $needsLocationJoins = !empty($filters['area_name']);
        
        if (array_key_exists('status_id', $filters) && $filters['status_id'] !== '') {
            $where .= " AND t.status_id = :status_id";
            $params['status_id'] = $filters['status_id'];
        }
        
        if (array_key_exists('priority_id', $filters) && $filters['priority_id'] !== '') {
            $where .= " AND t.priority_id = :priority_id";
            $params['priority_id'] = $filters['priority_id'];
        }
        
        if (array_key_exists('user_id', $filters) && $filters['user_id'] !== '') {
            $where .= " AND t.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (array_key_exists('assigned_admin_id', $filters) && $filters['assigned_admin_id'] !== '') {
            $where .= " AND t.assigned_admin_id = :assigned_admin_id";
            $params['assigned_admin_id'] = $filters['assigned_admin_id'];
        }
        
        if (!empty($filters['search'])) {
            $where .= " AND (t.title ILIKE :search OR t.code ILIKE :search OR t.description ILIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $where .= " AND t.created_at >= :date_from";
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where .= " AND t.created_at <= :date_to";
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        
        if (!empty($filters['area_name'])) {
            $where .= " AND l2.name = :area_name";
            $params['area_name'] = $filters['area_name'];
        }
        
        // Base query with location joins if needed for area filtering
        $query = "SELECT COUNT(*) as total FROM oti.tickets t";
        
        // Add location joins if needed for area filtering
        if ($needsLocationJoins) {
            $query .= "
                LEFT JOIN oti.locations l ON t.location_id = l.id
                LEFT JOIN oti.locations l2 ON l.parent_id = l2.id";
        }
        
        $query .= " {$where}";
        
        $stmt = $pdo->prepare($query);
        
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        
        $stmt->execute();
        return (int)$stmt->fetch()['total'];
    }

    /**
     * Obtiene los tickets del usuario actual
     */
    public static function getByUserId($userId, $page = 1, $pageSize = 20)
    {
        return self::getAll(['user_id' => $userId], $page, $pageSize);
    }

    /**
     * Crea un nuevo ticket
     */
    public static function create($data)
    {
        $pdo = self::db();
        
        $code = self::generateCode();
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO oti.tickets (code, user_id, title, description, status_id, priority_id, severity_id, service_type_id, location_id, category_id, equipment_id)
                VALUES (:code, :user_id, :title, :description, :status_id, :priority_id, :severity_id, :service_type_id, :location_id, :category_id, :equipment_id)
                RETURNING id
            ");
            
            $stmt->execute([
                'code' => $code,
                'user_id' => $data['user_id'],
                'title' => strip_tags(trim($data['title'])),
                'description' => strip_tags(trim($data['description'])),
                'status_id' => $data['status_id'] ?? 1,
                'priority_id' => $data['priority_id'] ?? 2,
                'severity_id' => $data['severity_id'] ?? null,
                'service_type_id' => $data['service_type_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'equipment_id' => $data['equipment_id'] ?? null
            ]);
            
            $ticketId = $stmt->fetchColumn();
            
            $pdo->commit();
            return ['success' => true, 'id' => $ticketId, 'code' => $code];
        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log("Error creando ticket: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al crear el ticket'];
        }
    }

    /**
     * Actualiza un ticket
     */
    public static function update($id, $data)
    {
        $pdo = self::db();
        
        $fields = [];
        $params = ['id' => $id];
        
        $allowedFields = ['title', 'description', 'status_id', 'priority_id', 'severity_id', 'service_type_id', 'location_id', 'category_id', 'equipment_id', 'assigned_admin_id', 'response_message', 'admin_response_date'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $query = "UPDATE oti.tickets SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Asigna un ticket a un administrador
     */
    public static function assign($ticketId, $adminId)
    {
        return self::update($ticketId, [
            'assigned_admin_id' => $adminId,
            'admin_response_date' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Cambia el estado de un ticket
     */
    public static function changeStatus($ticketId, $statusId)
    {
        $pdo = self::db();
        
        $stmt = $pdo->prepare("SELECT name FROM oti.ticket_statuses WHERE id = :id");
        $stmt->execute(['id' => $statusId]);
        $statusName = $stmt->fetchColumn();
        
        $finalStatuses = ['Cerrado', 'Resuelto'];
        $updateData = ['status_id' => $statusId];
        
        if (in_array($statusName, $finalStatuses)) {
            $updateData['closed_at'] = date('Y-m-d H:i:s');
            $updateData['resolved_at'] = date('Y-m-d H:i:s');
        }
        
        return self::update($ticketId, $updateData);
    }

    /**
     * Verifica si un estado es final
     */
    public static function isFinalStatus($statusId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT name FROM oti.ticket_statuses WHERE id = :id");
        $stmt->execute(['id' => $statusId]);
        $statusName = $stmt->fetchColumn();
        $finalStatuses = ['Cerrado', 'Resuelto', 'Cancelado'];
        return in_array($statusName, $finalStatuses);
    }

    /**
     * Genera código único para ticket
     */
    private static function generateCode()
    {
        $year = date('Y');
        $pdo = self::db();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) + 1 as next_num 
            FROM oti.tickets 
            WHERE code LIKE :prefix
        ");
        $stmt->execute(['prefix' => "OTI-{$year}%"]);
        $num = $stmt->fetch()['next_num'] ?? 1;
        
        return sprintf("OTI-%s-%04d", $year, $num);
    }

    /**
     * Obtiene estadísticas de tickets
     */
    public static function getStats($filters = [])
    {
        $pdo = self::db();
        
        $where = "WHERE 1=1";
        $params = [];
        
        if (array_key_exists('user_id', $filters) && $filters['user_id'] !== '') {
            $where .= " AND user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (array_key_exists('assigned_admin_id', $filters) && $filters['assigned_admin_id'] !== '') {
            $where .= " AND assigned_admin_id = :assigned_admin_id";
            $params['assigned_admin_id'] = $filters['assigned_admin_id'];
        }
        
        if (array_key_exists('location_id', $filters) && $filters['location_id'] !== '') {
            $where .= " AND location_id = :location_id";
            $params['location_id'] = $filters['location_id'];
        }
        
         $stmt = $pdo->prepare("
             SELECT
                 COUNT(*) AS total,
                 COUNT(*) FILTER (WHERE status_id = 1) AS abiertos,
                 COUNT(*) FILTER (WHERE status_id = 2) AS en_proceso,
                 COUNT(*) FILTER (WHERE status_id = 3) AS resueltos,
                 COUNT(*) FILTER (WHERE status_id = 4) AS cerrados,
                 COUNT(*) FILTER (WHERE status_id = 5) AS cancelados
         FROM oti.tickets {$where}
         ");
        $stmt->execute($params);
        $row = $stmt->fetch();
        
         return [
             'total' => (int)$row['total'],
             'abiertos' => (int)$row['abiertos'],
             'en_proceso' => (int)$row['en_proceso'],
             'resueltos' => (int)$row['resueltos'],
             'cerrados' => (int)$row['cerrados'],
             'cancelados' => (int)$row['cancelados'],
         ];
    }

    /**
     * Obtiene tickets por prioridad
     */
    public static function getByPriority()
    {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT tp.name, COUNT(t.id) as count
            FROM oti.tickets t
            JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
            GROUP BY tp.name, tp.id
            ORDER BY tp.id
        ");
        return $stmt->fetchAll();
    }

    /**
     * Obtiene tickets por estado
     */
    public static function getByStatus()
    {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT ts.name, COUNT(t.id) as count
            FROM oti.tickets t
            JOIN oti.ticket_statuses ts ON t.status_id = ts.id
            GROUP BY ts.name, ts.id
            ORDER BY ts.id
        ");
        return $stmt->fetchAll();
    }

    /**
     * Obtiene tickets de los últimos 30 días
     */
    public static function getLast30Days()
    {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM oti.tickets
            WHERE created_at >= NOW() - INTERVAL '30 days'
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        return $stmt->fetchAll();
    }

    /**
     * Cancela un ticket (solo si está en estado abierto)
     */
    public static function cancel($ticketId, $userId)
    {
        $pdo = self::db();
        
        $ticket = self::findById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'error' => 'Ticket no encontrado'];
        }
        
        if ($ticket['user_id'] != $userId) {
            return ['success' => false, 'error' => 'No tienes permiso para cancelar este ticket'];
        }
        
        if ($ticket['status_id'] != 1) {
            return ['success' => false, 'error' => 'Solo puedes cancelar tickets en estado abierto'];
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE oti.tickets 
                SET status_id = 5, cancelled_at = NOW(), cancelled_by_user_id = :user_id
                WHERE id = :id
            ");
            $stmt->execute(['id' => $ticketId, 'user_id' => $userId]);
            
            TicketActivity::create($ticketId, $userId, 'cancelado', 'Ticket cancelado por el usuario');
            
            return ['success' => true, 'message' => 'Ticket cancelado correctamente'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Error al cancelar el ticket'];
        }
    }

    /**
     * Elimina un ticket (soft delete)
     */
    public static function deleteTicket($ticketId, $userId)
    {
        $pdo = self::db();
        
        $ticket = self::findById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'error' => 'Ticket no encontrado'];
        }
        
        if ($ticket['user_id'] != $userId) {
            return ['success' => false, 'error' => 'No tienes permiso para eliminar este ticket'];
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE oti.tickets 
                SET is_deleted = true, deleted_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute(['id' => $ticketId]);
            
            TicketActivity::create($ticketId, $userId, 'eliminado', 'Ticket eliminado por el usuario');
            
            return ['success' => true, 'message' => 'Ticket eliminado correctamente'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Error al eliminar el ticket'];
        }
    }

    /**
     * Verifica si un ticket ha sido visto por el administrador
     */
    public static function hasBeenSeen($ticketId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT assigned_admin_id, admin_response_date, response_message
            FROM oti.tickets
            WHERE id = :id
        ");
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            return false;
        }
        // Si existe asignado o respuesta del admin, ya fue visto
        if (!empty($ticket['assigned_admin_id']) || !empty($ticket['admin_response_date']) || !empty($ticket['response_message'])) {
            return true;
        }

        // Además, verificar si el administrador marcó el ticket como visto (actividad 'visto_admin' o similar)
        $stmt2 = $pdo->prepare("SELECT 1 FROM oti.ticket_activities WHERE ticket_id = :id AND (action = 'visto_admin' OR action LIKE 'visto%') LIMIT 1");
        $stmt2->execute(['id' => $ticketId]);
        if ($stmt2->fetch()) {
            return true;
        }

        return false;
    }

    /**
     * Verifica si un ticket puede ser cancelado
     */
    public static function canCancel($ticketId)
    {
        $ticket = self::findById($ticketId);
        if (!$ticket) {
            return false;
        }
        
        return $ticket['status_id'] == 1;
    }

    /**
     * Obtiene actividades de un ticket
     */
    public static function getActivities($ticketId)
    {
        return TicketActivity::getByTicketId($ticketId);
    }
}