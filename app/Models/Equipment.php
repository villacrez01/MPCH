<?php
/**
 * Modelo de Equipos
 * Sistema OTI - Inventario de equipos
 */

namespace App\Models;

use App\Core\Model;

class Equipment extends Model
{
    /**
     * Obtiene todos los equipos (con filtros)
     */
    public static function getAll($filters = [], $page = 1, $pageSize = 20)
    {
        $pdo = self::db();
        
        $where = "WHERE e.is_deleted = false";
        $params = [];
        
        if (!empty($filters['status'])) {
            $where .= " AND e.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['asset_type'])) {
            $where .= " AND e.asset_type = :asset_type";
            $params['asset_type'] = $filters['asset_type'];
        }
        
        if (!empty($filters['location_id'])) {
            $where .= " AND e.location_id = :location_id";
            $params['location_id'] = $filters['location_id'];
        }
        
        if (!empty($filters['assigned_user_id'])) {
            $where .= " AND e.assigned_user_id = :assigned_user_id";
            $params['assigned_user_id'] = $filters['assigned_user_id'];
        }
        
        if (!empty($filters['search'])) {
            $where .= " AND (e.name ILIKE :search OR e.serial_number ILIKE :search OR e.patrimonial_code ILIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $page = max(1, (int)$page);
        $pageSize = min(100, max(1, (int)$pageSize));
        $offset = ($page - 1) * $pageSize;
        
        $query = "
            SELECT e.*, l.name as location_name, u.nombre as assigned_user_name, u.apellidos as assigned_user_lastname
            FROM oti.equipment e
            LEFT JOIN oti.locations l ON e.location_id = l.id
            LEFT JOIN admin.usuarios u ON e.assigned_user_id = u.id
            {$where}
            ORDER BY e.name ASC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':limit', $pageSize, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un equipo por ID
     */
    public static function getTotalCount($filters = [])
    {
        $pdo = self::db();
        $where = "WHERE e.is_deleted = false";
        $params = [];

        if (!empty($filters['status'])) {
            $where .= " AND e.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['asset_type'])) {
            $where .= " AND e.asset_type = :asset_type";
            $params['asset_type'] = $filters['asset_type'];
        }
        if (!empty($filters['location_id'])) {
            $where .= " AND e.location_id = :location_id";
            $params['location_id'] = $filters['location_id'];
        }
        if (!empty($filters['assigned_user_id'])) {
            $where .= " AND e.assigned_user_id = :assigned_user_id";
            $params['assigned_user_id'] = $filters['assigned_user_id'];
        }
        if (!empty($filters['search'])) {
            $where .= " AND (e.name ILIKE :search OR e.serial_number ILIKE :search OR e.patrimonial_code ILIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM oti.equipment e {$where}");
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public static function findById($id)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT e.*, l.name as location_name, u.nombre as assigned_user_name, u.apellidos as assigned_user_lastname
            FROM oti.equipment e
            LEFT JOIN oti.locations l ON e.location_id = l.id
            LEFT JOIN admin.usuarios u ON e.assigned_user_id = u.id
            WHERE e.id = :id AND e.is_deleted = false
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Obtiene estadísticas de equipos
     */
    public static function getStats()
    {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT
                COUNT(*) FILTER (WHERE is_deleted = false) AS total,
                COUNT(*) FILTER (WHERE status = 'active' AND is_deleted = false) AS activos,
                COUNT(*) FILTER (WHERE status = 'maintenance' AND is_deleted = false) AS mantenimiento,
                COUNT(*) FILTER (WHERE status = 'inactive' AND is_deleted = false) AS inactivos,
                COUNT(*) FILTER (WHERE status = 'retired' AND is_deleted = false) AS retirados
            FROM oti.equipment
        ");
        $row = $stmt->fetch();
        return [
            'total' => (int)$row['total'],
            'activos' => (int)$row['activos'],
            'mantenimiento' => (int)$row['mantenimiento'],
            'inactivos' => (int)$row['inactivos'],
            'retirados' => (int)$row['retirados'],
        ];
    }

    /**
     * Obtiene equipos por tipo
     */
    public static function getByType()
    {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT asset_type, COUNT(*) as count
            FROM oti.equipment
            WHERE is_deleted = false
            GROUP BY asset_type
            ORDER BY count DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Obtiene equipos por condición
     */
    public static function getByCondition()
    {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT condition, COUNT(*) as count
            FROM oti.equipment
            WHERE is_deleted = false
            GROUP BY condition
            ORDER BY count DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Asigna un equipo a un usuario
     */
    public static function assignToUser($equipmentId, $userId, $userName)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            UPDATE oti.equipment 
            SET assigned_user_id = :user_id, assigned_user_name = :user_name
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $equipmentId,
            'user_id' => $userId,
            'user_name' => $userName
        ]);
    }

    /**
     * Actualiza la ubicación de un equipo
     */
    public static function updateLocation($equipmentId, $locationId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("UPDATE oti.equipment SET location_id = :location_id WHERE id = :id");
        return $stmt->execute(['id' => $equipmentId, 'location_id' => $locationId]);
    }

    /**
     * Cambia el estado de un equipo
     */
    public static function updateStatus($equipmentId, $status)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("UPDATE oti.equipment SET status = :status WHERE id = :id");
        return $stmt->execute(['id' => $equipmentId, 'status' => $status]);
    }

    /**
     * Tipos de activos disponibles
     */
    public static function getAssetTypes()
    {
        return ['PC', 'LAPTOP', 'IMPRESORA', 'SCANNER', 'SWITCH', 'TELEFONO', 'MONITOR', 'PROYECTOR', 'OTRO'];
    }

    /**
     * Estados disponibles
     */
    public static function getStatuses()
    {
        return ['active', 'inactive', 'maintenance', 'retired'];
    }

    public static function getStatusLabels(): array
    {
        return [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'maintenance' => 'Mantenimiento',
            'retired' => 'Retirado',
        ];
    }

    /**
     * Condiciones disponibles
     */
    public static function getConditions()
    {
        return ['BUENO', 'REGULAR', 'MALO', 'OBSOLETO'];
    }

    /**
     * Obtiene los equipos asignados a un usuario
     */
    public static function getByUserId($userId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT e.*, l.name as location_name
            FROM oti.equipment e
            LEFT JOIN oti.locations l ON e.location_id = l.id
            WHERE e.assigned_user_id = :user_id AND e.is_deleted = false
            ORDER BY e.name ASC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Cuenta los equipos asignados a un usuario
     */
    public static function countByUserId($userId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM oti.equipment
            WHERE assigned_user_id = :user_id AND is_deleted = false
        ");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }
}