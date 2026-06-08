<?php
/**
 * API de Estructura Orgánica (Locations)
 * Sistema OTI - Datos en tiempo real
 */

error_reporting(0);

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
use App\Models\Location;
use App\Models\User;

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
session_write_close();

$action = $_GET['action'] ?? 'list';

try {
    $pdo = Database::connect();
    
    switch ($action) {
        case 'list':
            listLocations($pdo);
            break;
            
        case 'get-tree':
            getHierarchyTree($pdo);
            break;
            
        case 'create':
            createLocation($pdo);
            break;
            
        case 'update':
            updateLocation($pdo);
            break;
            
        case 'delete':
            deleteLocation($pdo);
            break;
            
        case 'get-users-available':
            getUsersAvailable($pdo);
            break;
            
        case 'assign-user':
            assignUserToLocation($pdo);
            break;
            
        case 'unassign-user':
            unassignUserFromLocation($pdo);
            break;
            
        case 'search':
            searchLocations($pdo);
            break;
            
        case 'get-detail':
            getLocationDetail($pdo);
            break;
            
        case 'get-by-parent':
            getByParent($pdo);
            break;
            
        case 'delete-cascade':
            deleteCascade($pdo);
            break;
            
        case 'get-types':
            echo json_encode(['types' => Location::getTypes()]);
            break;
            
        default:
            echo json_encode(['error' => 'Acción no válida']);
            break;
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}

function listLocations($pdo) {
    $stmt = $pdo->query("
        SELECT l.*, 
               (SELECT name FROM oti.locations WHERE id = l.parent_id) as parent_name,
               u.nombre as manager_name,
               u.apellidos as manager_lastname
        FROM oti.locations l
        LEFT JOIN admin.usuarios u ON l.manager_id = u.id
        ORDER BY l.type, l.nivel, l.name
    ");
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['locations' => $locations]);
}

function getHierarchyTree($pdo) {
    $locations = Location::getAll(false);
    $tree = buildTree($locations);
    echo json_encode(['tree' => $tree]);
}

function buildTree($locations, $parentId = null) {
    $result = [];
    foreach ($locations as $loc) {
        if ($loc['parent_id'] == $parentId) {
            $children = buildTree($locations, $loc['id']);
            $loc['children'] = $children;
            $loc['usuarios_count'] = User::countByLocation($loc['id']);
            $result[] = $loc;
        }
    }
    return $result;
}

function checkCsrf() {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $token = $data['_token'] ?? $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf($token)) {
        echo json_encode(['success' => false, 'error' => 'Token CSRF inválido. Recargue la página.']);
        exit;
    }
    return $data;
}

function createLocation($pdo) {
    $data = checkCsrf();
    
    $name = $data['name'] ?? '';
    $type = $data['type'] ?? 'AREA'; // SEDE, AREA, SUBAREA, PISO
    $parent_id = $data['parent_id'] ?? null;
    $description = $data['description'] ?? '';
    $floor = $data['floor'] ?? '';
    $building = $data['building'] ?? '';
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'El nombre es requerido']);
        return;
    }

    $normalizedType = strtoupper(trim($type));
    if ($normalizedType !== 'SEDE' && empty($parent_id)) {
        echo json_encode(['success' => false, 'error' => 'Este tipo de ubicación requiere un padre válido']);
        return;
    }
    
    // Determinar nivel según tipo
    $nivel = match($normalizedType) {
        'SEDE' => 1,
        'DIRECCION' => 2,
        'AREA' => 3,
        'SUBAREA' => 4,
        'PISO' => 5,
        'OFICINA' => 6,
        default => 2
    };
    
    $stmt = $pdo->prepare("
        INSERT INTO oti.locations (name, type, parent_id, description, floor, building, nivel, active, created_at)
        VALUES (:name, :type, :parent_id, :description, :floor, :building, :nivel, true, NOW())
    ");
    
    $stmt->execute([
        'name' => $name,
        'type' => $normalizedType,
        'parent_id' => $parent_id ?: null,
        'description' => $description,
        'floor' => $floor,
        'building' => $building,
        'nivel' => $nivel
    ]);
    
    $id = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'id' => $id]);
}

function updateLocation($pdo) {
    $data = checkCsrf();
    $id = $data['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID requerido']);
        return;
    }
    
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $floor = $data['floor'] ?? '';
    $building = $data['building'] ?? '';
    $active = $data['active'] ?? true;
    
    $stmt = $pdo->prepare("
        UPDATE oti.locations 
        SET name = :name, description = :description, floor = :floor, building = :building, active = :active, updated_at = NOW()
        WHERE id = :id
    ");
    
    $stmt->execute([
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'floor' => $floor,
        'building' => $building,
        'active' => $active
    ]);
    
    echo json_encode(['success' => true]);
}

function deleteLocation($pdo) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $token = $data['_token'] ?? $_GET['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf($token)) {
        echo json_encode(['success' => false, 'error' => 'Token CSRF inválido. Recargue la página.']);
        exit;
    }
    $id = $_GET['id'] ?? $data['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID requerido']);
        return;
    }
    
    // Verificar si tiene hijos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM oti.locations WHERE parent_id = :id");
    $stmt->execute(['id' => $id]);
    $childrenCount = $stmt->fetchColumn();
    
    if ($childrenCount > 0) {
        echo json_encode(['success' => false, 'error' => 'No se puede eliminar: tiene ubicaciones dependientes']);
        return;
    }
    
    // Desactivar en lugar de eliminar (soft delete)
    $stmt = $pdo->prepare("UPDATE oti.locations SET active = false WHERE id = :id");
    $stmt->execute(['id' => $id]);
    
    echo json_encode(['success' => true]);
}

function getUsersAvailable($pdo) {
    // Usuarios sin ubicación asignada
    $stmt = $pdo->query("
        SELECT u.id, u.nombre, u.apellidos, u.email, up.dni
        FROM admin.usuarios u
        JOIN oti.user_profiles up ON u.id = up.user_id
        WHERE up.location_id IS NULL AND u.activo = true
        ORDER BY u.nombre, u.apellidos
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['users' => $users]);
}

function assignUserToLocation($pdo) {
    $data = checkCsrf();
    $userId = $data['user_id'] ?? null;
    $locationId = $data['location_id'] ?? null;
    
    if (!$userId || !$locationId) {
        echo json_encode(['success' => false, 'error' => 'Usuario y ubicación requeridos']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE oti.user_profiles SET location_id = :location_id WHERE user_id = :user_id");
    $stmt->execute(['location_id' => $locationId, 'user_id' => $userId]);
    
    echo json_encode(['success' => true]);
}

function unassignUserFromLocation($pdo) {
    $data = checkCsrf();
    $userId = $data['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Usuario requerido']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE oti.user_profiles SET location_id = NULL WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);
    
    echo json_encode(['success' => true]);
}

function searchLocations($pdo) {
    $query = $_GET['q'] ?? '';
    $type = $_GET['type'] ?? '';
    $sede_id = $_GET['sede_id'] ?? '';
    
    $sql = "SELECT l.*, (SELECT name FROM oti.locations WHERE id = l.parent_id) as parent_name 
            FROM oti.locations l WHERE l.active = true";
    $params = [];
    
    if ($query) {
        $sql .= " AND l.name LIKE :query ESCAPE '\\'";
        $params['query'] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
    }
    
    if ($type) {
        $sql .= " AND l.type = :type";
        $params['type'] = $type;
    }
    
    if ($sede_id) {
        $sql .= " AND l.parent_id = :sede_id";
        $params['sede_id'] = $sede_id;
    }
    
    $sql .= " ORDER BY l.nivel, l.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['locations' => $locations]);
}

function getLocationDetail($pdo) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['error' => 'ID requerido']);
        return;
    }
    
    // Validar formato de ID - aceptar UUID o integer para compatibilidad
    $idValid = false;
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
        $idValid = true;
    } elseif (is_numeric($id)) {
        // Permitir IDs numéricos para compatibilidad
        $idValid = true;
    }
    
    if (!$idValid) {
        echo json_encode(['error' => 'ID con formato inválido']);
        return;
    }
    
    // Obtener ubicación
    try {
        $stmt = $pdo->prepare("SELECT * FROM oti.locations WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        // Manejar error de tipo UUID
        if ($e->getCode() == '22P02') {
            echo json_encode(['error' => 'El ID proporcionado no es un formato UUID válido']);
            return;
        }
        throw $e;
    }
    
    if (!$location) {
        echo json_encode(['error' => 'Ubicación no encontrada']);
        return;
    }
    
    // Obtener usuarios asignados
    $stmt = $pdo->prepare("
        SELECT u.id, u.nombre, u.apellidos, u.email, up.dni, p.name as position_name
        FROM admin.usuarios u
        JOIN oti.user_profiles up ON u.id = up.user_id
        LEFT JOIN oti.positions p ON up.position_id = p.id
        WHERE up.location_id = :location_id AND u.activo = true
        ORDER BY u.nombre
    ");
    $stmt->execute(['location_id' => $id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener equipos
    $stmt = $pdo->prepare("
        SELECT id, name, serial_number as serial, patrimonial_code, status
        FROM oti.equipment
        WHERE location_id = :location_id AND is_deleted = false
        ORDER BY name
    ");
    $stmt->execute(['location_id' => $id]);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener path (breadcrumb)
    $path = [];
    $currentId = $location['parent_id'];
    while ($currentId) {
        $stmt = $pdo->prepare("SELECT id, name, type, parent_id FROM oti.locations WHERE id = :id");
        $stmt->execute(['id' => $currentId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($parent) {
            array_unshift($path, $parent);
            $currentId = $parent['parent_id'] ?? null;
        } else {
            break;
        }
    }
    
    echo json_encode([
        'success' => true,
        'location' => $location,
        'users' => $users,
        'equipment' => $equipment,
        'path' => $path
    ]);
}

function getByParent($pdo) {
    $parentId = $_GET['parent_id'] ?? null;
    if (!$parentId) {
        echo json_encode(['error' => 'parent_id requerido']);
        return;
    }
    $children = Location::getChildren($parentId);
    echo json_encode(['locations' => $children]);
}

function deleteCascade($pdo) {
    $data = checkCsrf();
    $id = $data['id'] ?? $_GET['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID requerido']);
        return;
    }
    // Recursively collect all descendant IDs
    $idsToDelete = [];
    $stack = [$id];
    while (!empty($stack)) {
        $current = array_pop($stack);
        $idsToDelete[] = $current;
        $stmt = $pdo->prepare("SELECT id FROM oti.locations WHERE parent_id = :pid AND active = true");
        $stmt->execute(['pid' => $current]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $child) {
            $stack[] = $child['id'];
        }
    }
    // Soft-delete all collected IDs
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE oti.locations SET active = false, updated_at = NOW() WHERE id = :id");
        foreach ($idsToDelete as $delId) {
            $stmt->execute(['id' => $delId]);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'deleted_count' => count($idsToDelete)]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al eliminar en cascada']);
    }
}