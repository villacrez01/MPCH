<?php
/**
 * Modelo de Ubicaciones
 * Sistema OTI - Gestión de ubicaciones/áreas
 */

namespace App\Models;

use App\Core\Model;

class Location extends Model
{
    /**
     * Obtiene todas las ubicaciones
     */
    public static function getAll($includeInactive = false)
    {
        $pdo = self::db();
        
        $where = $includeInactive ? "" : "WHERE active = true";
        
        $stmt = $pdo->query("
            SELECT l.*, 
                   (SELECT name FROM oti.locations WHERE id = l.parent_id) as parent_name,
                   u.nombre as manager_name,
                   u.apellidos as manager_lastname
            FROM oti.locations l
            LEFT JOIN admin.usuarios u ON l.manager_id = u.id
            {$where}
            ORDER BY l.type, l.name
        ");
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una ubicación por ID
     */
    public static function findById($id)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT l.*, 
                   (SELECT name FROM oti.locations WHERE id = l.parent_id) as parent_name,
                   u.nombre as manager_name,
                   u.apellidos as manager_lastname
            FROM oti.locations l
            LEFT JOIN admin.usuarios u ON l.manager_id = u.id
            WHERE l.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Obtiene las ubicaciones principales (sedes)
     */
    public static function getHeadquarters()
    {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT * FROM oti.locations 
            WHERE type = 'sede' AND active = true 
            ORDER BY name
        ");
        return $stmt->fetchAll();
    }

    /**
     * Obtiene las ubicaciones por tipo
     */
    public static function getByType($type)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT * FROM oti.locations 
            WHERE type = :type AND active = true 
            ORDER BY name
        ");
        $stmt->execute(['type' => $type]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene las ubicaciones de un padre
     */
    public static function getChildren($parentId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT * FROM oti.locations 
            WHERE parent_id = :parent_id AND active = true 
            ORDER BY name
        ");
        $stmt->execute(['parent_id' => $parentId]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene el árbol de ubicaciones
     */
    public static function getTree($includeInactive = false)
    {
        $locations = self::getAll($includeInactive);
        $tree = [];
        $nodes = [];

        foreach ($locations as $location) {
            $location['children'] = [];
            $nodes[$location['id']] = $location;
        }

        foreach ($nodes as $id => $location) {
            if ($location['parent_id'] && isset($nodes[$location['parent_id']])) {
                $nodes[$location['parent_id']]['children'][] = &$nodes[$id];
            } else {
                $tree[] = &$nodes[$id];
            }
        }

        return $tree;
    }

    /**
     * Obtiene la ruta de una ubicación (para breadcrumb)
     */
    public static function getPath($locationId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            WITH RECURSIVE path AS (
                SELECT id, name, parent_id, type, 1 as depth
                FROM oti.locations WHERE id = ?
                UNION ALL
                SELECT l.id, l.name, l.parent_id, l.type, p.depth + 1
                FROM oti.locations l
                INNER JOIN path p ON l.id = p.parent_id
                WHERE p.depth < 20
            )
            SELECT * FROM path ORDER BY depth DESC
        ");
        $stmt->execute([$locationId]);
        return $stmt->fetchAll();
    }

    /**
     * Alias para getHeadquarters
     */
    public static function getSedes()
    {
        return self::getHeadquarters();
    }

    /**
     * Obtiene pisos bajo una sede específica
     */
    public static function getFloorsBySede($sedeId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT * FROM oti.locations 
            WHERE parent_id = :pid AND type = 'piso' AND active = true 
            ORDER BY name
        ");
        $stmt->execute(['pid' => $sedeId]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene tipos distintos existentes en la BD
     */
    public static function getTypesFromDB(): array
    {
        $pdo = self::db();
        $stmt = $pdo->query("SELECT DISTINCT type FROM oti.locations WHERE active = true ORDER BY type");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Tipos de ubicación disponibles
     */
    public static function getTypes()
    {
        return ['sede', 'sucursal', 'piso', 'area'];
    }

    /**
     * Cuenta equipos por ubicación
     */
    public static function countEquipment($locationId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM oti.equipment 
            WHERE location_id = :location_id AND is_deleted = false
        ");
        $stmt->execute(['location_id' => $locationId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Cuenta usuarios por ubicación
     */
    public static function countUsers($locationId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM oti.user_profiles 
            WHERE location_id = :location_id
        ");
        $stmt->execute(['location_id' => $locationId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Obtiene estadísticas de ubicaciones
     */
    public static function getStats(): array
    {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT
                COUNT(*) FILTER (WHERE active = true) AS total,
                COUNT(*) FILTER (WHERE type = 'DIRECCION' AND active = true) AS direcciones,
                COUNT(*) FILTER (WHERE type = 'AREA' AND active = true) AS areas,
                COUNT(*) FILTER (WHERE type = 'OFICINA' AND active = true) AS oficinas,
                (SELECT COUNT(*) FROM oti.user_profiles WHERE location_id IS NOT NULL) AS usuarios_asignados,
                (SELECT COUNT(*) FROM oti.equipment WHERE location_id IS NOT NULL AND is_deleted = false) AS equipos_asignados
            FROM oti.locations
        ");
        $row = $stmt->fetch();
        return [
            'total' => (int)$row['total'],
            'direcciones' => (int)$row['direcciones'],
            'areas' => (int)$row['areas'],
            'oficinas' => (int)$row['oficinas'],
            'usuarios_asignados' => (int)$row['usuarios_asignados'],
            'equipos_asignados' => (int)$row['equipos_asignados'],
        ];
    }
    
    /**
     * Obtiene usuarios en una ubicación específica
     */
    public static function getUsers($locationId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.apellidos, u.email, up.dni, up.phone, p.name as position_name
            FROM admin.usuarios u
            JOIN oti.user_profiles up ON u.id = up.user_id
            LEFT JOIN oti.positions p ON up.position_id = p.id
            WHERE up.location_id = :location_id AND u.activo = true
            ORDER BY u.nombre ASC
        ");
        $stmt->execute(['location_id' => $locationId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obtiene equipos en una ubicación específica
     */
    public static function getEquipment($locationId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT e.*, u.nombre as assigned_user_name, u.apellidos as assigned_user_lastname
            FROM oti.equipment e
            LEFT JOIN admin.usuarios u ON e.assigned_user_id = u.id
            WHERE e.location_id = :location_id AND e.is_deleted = false
            ORDER BY e.name ASC
        ");
        $stmt->execute(['location_id' => $locationId]);
        return $stmt->fetchAll();
    }
    
    public static function getById($id)
    {
        if (!$id) return [];

        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT l.*,
                   p.id AS parent_id, p.name AS parent_name, p.type AS parent_type,
                   g.id AS grandparent_id, g.name AS grandparent_name, g.type AS grandparent_type
            FROM oti.locations l
            LEFT JOIN oti.locations p ON l.parent_id = p.id AND p.active = true
            LEFT JOIN oti.locations g ON p.parent_id = g.id AND g.active = true
            WHERE l.id = :id AND l.active = true
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $location = $stmt->fetch();

        if (!$location) return [];

        if ($location['type'] === 'oficina' || $location['type'] === 'area') {
            $location['area_name'] = $location['parent_name'] ?? '';
            $location['area_id'] = $location['parent_id'] ?? null;
            $location['sede_name'] = $location['grandparent_name'] ?? '';
            $location['sede_id'] = $location['grandparent_id'] ?? null;
        } elseif ($location['type'] === 'sede') {
            $location['sede_name'] = $location['name'];
            $location['sede_id'] = $location['id'];
        }

        return $location;
    }
}