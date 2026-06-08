<?php
/**
 * API de Equipos
 * Sistema OTI - Datos en tiempo real
 */

error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
$allowedOrigins = ['http://localhost', 'https://oti.intranet'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Cache\Store as Cache;

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
session_write_close();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Permission mappings RBAC
        $permissionMap = [
            'list' => null, // Todos pueden ver
            'get-equipo' => null, // Todos pueden ver detalle
            'create-equipo' => 'equipos.crear',
            'update-equipo' => 'equipos.editar',
            'delete-equipo' => 'equipos.eliminar',
            'deactivate-equipo' => 'equipos.editar',
            'reactivate-equipo' => 'equipos.editar',
            'delete-permanent-equipo' => 'equipos.eliminar'
        ];
        
        // Admin siempre tiene acceso
        $isAdmin = \App\Services\AuthService::isAdmin();
        
        // Verificar permiso RBAC
        $requiredPermission = $permissionMap[$action] ?? null;
        if ($requiredPermission && !$isAdmin) {
            // Cargar AuthService para verificar permiso granular
            $hasPermission = \App\Services\AuthService::hasPermission($requiredPermission);
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permiso: ' . $requiredPermission]);
                exit;
            }
        }
        
        // Legacy fallback removed — usar permissionMap + AuthService arriba

// Input validation function
function validateEquipmentData($data) {
    $errors = [];

    // Validate IP address if provided
    if (!empty($data['ip_address'])) {
        if (!filter_var($data['ip_address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            $errors['ip_address'] = 'Dirección IP no válida';
        }
    }

    // Validate MAC address if provided
    if (!empty($data['mac_address'])) {
        // Regex for MAC address with colon or hyphen separators
        if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $data['mac_address'])) {
            $errors['mac_address'] = 'Dirección MAC no válida';
        }
    }

    // You can add more validations here (e.g., for serial number length, etc.)

    return $errors;
}

// CSRF check helper
function checkCsrfOrFail() {
    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido o ausente']);
        exit;
    }
}

try {
    $pdo = Database::connect();
    
    switch ($action) {
        case 'list':
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $type = $_GET['type'] ?? '';
            $location_id = $_GET['location_id'] ?? '';
            
            $page = max(1, $page);
            $pageSize = isset($_GET['page_size']) ? (int)$_GET['page_size'] : 20;
            $pageSize = max(1, min(100, $pageSize));
            $offset = ($page - 1) * $pageSize;
            
            $where = "WHERE e.is_deleted = false";
            $params = [];
            
            if ($search) {
                $where .= " AND (e.name ILIKE :search OR e.serial_number ILIKE :search OR e.patrimonial_code ILIKE :search OR e.brand ILIKE :search OR e.model ILIKE :search)";
                $params['search'] = '%' . $search . '%';
            }
            
            if ($status) {
                $where .= " AND e.status = :status";
                $params['status'] = $status;
            }
            
            if ($type) {
                $where .= " AND e.asset_type = :type";
                $params['type'] = $type;
            }
            
            $withClause = "";
            if ($location_id) {
                 $withClause = "WITH RECURSIVE locs AS (SELECT id FROM oti.locations WHERE id = :loc_id UNION ALL SELECT l.id FROM oti.locations l INNER JOIN locs pc ON l.parent_id = pc.id) ";
                 $where .= " AND e.location_id IN (SELECT id FROM locs)";
                 $params['loc_id'] = $location_id;
             }
            
            // Total
            $stmt = $pdo->prepare("{$withClause}SELECT COUNT(*) FROM oti.equipment e LEFT JOIN oti.locations l ON e.location_id = l.id {$where}");
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();
            
            // Datos
            $stmt = $pdo->prepare(
                "{$withClause}SELECT e.*, 
                       l.name as location_name,
                       u.nombre as assigned_user_name,
                       u.apellidos as assigned_user_lastname
                FROM oti.equipment e
                LEFT JOIN oti.locations l ON e.location_id = l.id
                LEFT JOIN admin.usuarios u ON e.assigned_user_id = u.id
                {$where}
                ORDER BY e.name ASC
                LIMIT :limit OFFSET :offset"
            );
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            // bind filter params
            foreach ($params as $key => $val) {
                $paramName = ':' . ltrim($key, ':');
                $stmt->bindValue($paramName, $val);
            }
            $stmt->execute();
            $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Stats (aplicando mismos filtros)
            $statsQuery = "{$withClause}SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as activos,
                    SUM(CASE WHEN e.status = 'maintenance' THEN 1 ELSE 0 END) as mantenimiento,
                    SUM(CASE WHEN e.status = 'inactive' THEN 1 ELSE 0 END) as inactivos,
                    SUM(CASE WHEN e.status = 'retired' THEN 1 ELSE 0 END) as retirados
                FROM oti.equipment e
                LEFT JOIN oti.locations l ON e.location_id = l.id
                {$where}
            ";
            $statsStmt = $pdo->prepare($statsQuery);
            // bind same params
            foreach ($params as $key => $val) {
                $paramName = ':' . ltrim($key, ':');
                $statsStmt->bindValue($paramName, $val);
            }
            $statsStmt->execute();
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'equipos' => $equipos,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'stats' => $stats
            ]);
            break;
            
        case 'get-equipo':
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT e.*, 
                       l.name as location_name,
                       u.nombre as assigned_user_name,
                       u.apellidos as assigned_user_lastname
                FROM oti.equipment e
                LEFT JOIN oti.locations l ON e.location_id = l.id
                LEFT JOIN admin.usuarios u ON e.assigned_user_id = u.id
                WHERE e.id = :id AND e.is_deleted = false
                LIMIT 1
            ");
            $stmt->execute(['id' => $id]);
            $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($equipo) {
                echo json_encode($equipo);
            } else {
                echo json_encode(['error' => 'Equipo no encontrado']);
            }
            break;
            
        case 'update-equipo':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Método no permitido']);
                exit;
            }
            checkCsrfOrFail();
            
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado']);
                exit;
            }
            
            // Recoger campos POST
            $name = $_POST['name'] ?? '';
            $patrimonial_code = $_POST['patrimonial_code'] ?? null;
            $serial_number = $_POST['serial_number'] ?? null;
            $asset_type = $_POST['asset_type'] ?? 'OTRO';
            $status = $_POST['status'] ?? 'inactive';
            $condition = $_POST['condition'] ?? 'BUENO';
            $brand = $_POST['brand'] ?? null;
            $model = $_POST['model'] ?? null;
            
            $ip_address = $_POST['ip_address'] ?? null;
            $mac_address = $_POST['mac_address'] ?? null;
            $connection_type = $_POST['connection_type'] ?? null;
            
            $cpu_brand = $_POST['cpu_brand'] ?? null;
            $cpu_model = $_POST['cpu_model'] ?? null;
            $cpu_generation = $_POST['cpu_generation'] ?? null;
            $cpu_spec = $_POST['cpu_spec'] ?? null;
            $ram = $_POST['ram'] ?? null;
            $disk_type = $_POST['disk_type'] ?? null;
            $disk_capacity = $_POST['disk_capacity'] ?? null;
            $screen_size = $_POST['screen_size'] ?? null;
            
            $location_id = $_POST['location_id'] ?? null;
            if ($location_id === '') $location_id = null;
            
            $assigned_user_id = $_POST['assigned_user_id'] ?? null;
            if ($assigned_user_id === '') $assigned_user_id = null;
            
             $observations = $_POST['observations'] ?? null;

             // Validate input data
             $validationErrors = validateEquipmentData([
                 'ip_address' => $ip_address,
                 'mac_address' => $mac_address,
             ]);
             if (!empty($validationErrors)) {
                 echo json_encode(['success' => false, 'errors' => $validationErrors]);
                 exit;
             }

             // Ejecutar UPDATE
             $stmt = $pdo->prepare("
                 UPDATE oti.equipment
                 SET name = :name,
                     patrimonial_code = :patrimonial_code,
                     serial_number = :serial_number,
                     asset_type = :asset_type,
                     status = :status,
                     condition = :condition,
                     brand = :brand,
                     model = :model,
                     ip_address = :ip_address,
                     mac_address = :mac_address,
                     connection_type = :connection_type,
                     cpu_brand = :cpu_brand,
                     cpu_model = :cpu_model,
                     cpu_generation = :cpu_generation,
                     cpu_spec = :cpu_spec,
                     ram = :ram,
                     disk_type = :disk_type,
                     disk_capacity = :disk_capacity,
                     screen_size = :screen_size,
                     location_id = :location_id,
                     assigned_user_id = :assigned_user_id,
                     observations = :observations,
                     updated_at = NOW()
                 WHERE id = :id
             ");
            
             $success = $stmt->execute([
                 'id' => $id,
                 'name' => $name,
                 'patrimonial_code' => $patrimonial_code,
                 'serial_number' => $serial_number,
                 'asset_type' => $asset_type,
                 'status' => $status,
                 'condition' => $condition,
                 'brand' => $brand,
                 'model' => $model,
                 'ip_address' => $ip_address,
                 'mac_address' => $mac_address,
                 'connection_type' => $connection_type,
                 'cpu_brand' => $cpu_brand,
                 'cpu_model' => $cpu_model,
                 'cpu_generation' => $cpu_generation,
                 'cpu_spec' => $cpu_spec,
                 'ram' => $ram,
                 'disk_type' => $disk_type,
                 'disk_capacity' => $disk_capacity,
                 'screen_size' => $screen_size,
                 'location_id' => $location_id,
                 'assigned_user_id' => $assigned_user_id,
                 'observations' => $observations
             ]);
            Cache::markDirty('dashboard');
            
            echo json_encode(['success' => $success]);
            break;

        case 'create-equipo':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Método no permitido']);
                exit;
            }
            checkCsrfOrFail();

            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                echo json_encode(['error' => 'El nombre del equipo es obligatorio']);
                exit;
            }

            $patrimonial_code = $_POST['patrimonial_code'] ?? null;
            $serial_number = trim($_POST['serial_number'] ?? '');
            if ($serial_number === '') {
                echo json_encode(['error' => 'El número de serie es obligatorio']);
                exit;
            }
            $asset_type = $_POST['asset_type'] ?? 'OTRO';
            $status = $_POST['status'] ?? 'inactive';
            $condition = $_POST['condition'] ?? 'BUENO';
            $brand = $_POST['brand'] ?? null;
            $model = $_POST['model'] ?? null;
            $ip_address = $_POST['ip_address'] ?? null;
            $mac_address = $_POST['mac_address'] ?? null;
            $connection_type = $_POST['connection_type'] ?? null;
            $cpu_brand = $_POST['cpu_brand'] ?? null;
            $cpu_model = $_POST['cpu_model'] ?? null;
            $cpu_generation = $_POST['cpu_generation'] ?? null;
            $cpu_spec = $_POST['cpu_spec'] ?? null;
            $ram = $_POST['ram'] ?? null;
            $disk_type = $_POST['disk_type'] ?? null;
            $disk_capacity = $_POST['disk_capacity'] ?? null;
            $screen_size = $_POST['screen_size'] ?? null;
            $location_id = $_POST['location_id'] ?? null;
            if ($location_id === '') $location_id = null;
            $assigned_user_id = $_POST['assigned_user_id'] ?? null;
            if ($assigned_user_id === '') $assigned_user_id = null;
            $observations = $_POST['observations'] ?? null;

            // Validate input data
            $validationErrors = validateEquipmentData([
                'ip_address' => $ip_address,
                'mac_address' => $mac_address,
                // Add other fields if needed
            ]);
             if (!empty($validationErrors)) {
                echo json_encode(['success' => false, 'errors' => $validationErrors]);
                exit;
            }

            if (!empty($serial_number)) {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM oti.equipment WHERE serial_number = :serial_number AND is_deleted = false");
                $checkStmt->execute(['serial_number' => $serial_number]);
                if ($checkStmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'errors' => ['serial_number' => 'El número de serie ya está registrado en el sistema']]);
                    exit;
                }
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO oti.equipment (
                    name,
                    patrimonial_code,
                    serial_number,
                    asset_type,
                    status,
                    condition,
                    brand,
                    model,
                    ip_address,
                    mac_address,
                    connection_type,
                    cpu_brand,
                    cpu_model,
                    cpu_generation,
                    cpu_spec,
                    ram,
                    disk_type,
                    disk_capacity,
                    screen_size,
                    location_id,
                    assigned_user_id,
                    observations,
                    created_at,
                    updated_at,
                    is_deleted
                ) VALUES (
                    :name,
                    :patrimonial_code,
                    :serial_number,
                    :asset_type,
                    :status,
                    :condition,
                    :brand,
                    :model,
                    :ip_address,
                    :mac_address,
                    :connection_type,
                    :cpu_brand,
                    :cpu_model,
                    :cpu_generation,
                    :cpu_spec,
                    :ram,
                    :disk_type,
                    :disk_capacity,
                    :screen_size,
                    :location_id,
                    :assigned_user_id,
                    :observations,
                    NOW(),
                    NOW(),
                    false
                )");

                $stmt->execute([
                    'name' => $name,
                    'patrimonial_code' => $patrimonial_code,
                    'serial_number' => $serial_number,
                    'asset_type' => $asset_type,
                    'status' => $status,
                    'condition' => $condition,
                    'brand' => $brand,
                    'model' => $model,
                    'ip_address' => $ip_address,
                    'mac_address' => $mac_address,
                    'connection_type' => $connection_type,
                    'cpu_brand' => $cpu_brand,
                    'cpu_model' => $cpu_model,
                    'cpu_generation' => $cpu_generation,
                    'cpu_spec' => $cpu_spec,
                    'ram' => $ram,
                    'disk_type' => $disk_type,
                    'disk_capacity' => $disk_capacity,
                    'screen_size' => $screen_size,
                    'location_id' => $location_id,
                    'assigned_user_id' => $assigned_user_id,
                    'observations' => $observations
                ]);

                Cache::markDirty('dashboard');
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                error_log($e->getMessage());
                if ($e->getCode() === '23505') {
                    echo json_encode(['success' => false, 'errors' => ['serial_number' => 'El número de serie ya está registrado en el sistema']]);
                } else {
                    echo json_encode(['error' => 'Error interno del servidor']);
                }
            }
            break;

        case 'delete-equipo':
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE oti.equipment SET is_deleted = true, updated_at = NOW() WHERE id = :id");
            $success = $stmt->execute(['id' => $id]);
            Cache::markDirty('dashboard');
            echo json_encode(['success' => $success]);
            break;
            
        case 'deactivate-equipo':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Método no permitido']);
                exit;
            }
            checkCsrfOrFail();
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            $reason = $_POST['reason'] ?? '';
            if (empty($id)) {
                echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
                exit;
            }
            try {
                // Desactivar equipo: cambiar status a retired, guardar motivo y limpiar relaciones
                $stmt = $pdo->prepare("
                    UPDATE oti.equipment 
                    SET status = 'retired', 
                        deactivation_reason = :reason,
                        deactivated_by = :user_id,
                        assigned_user_id = NULL,
                        location_id = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $userId = $_SESSION['user']['id'] ?? null;
                $success = $stmt->execute([
                    'id' => $id,
                    'reason' => $reason,
                    'user_id' => $userId
                ]);
                Cache::markDirty('dashboard');
                echo json_encode(['success' => $success]);
            } catch (Exception $e) {
                error_log($e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
            }
            break;
            
        case 'reactivate-equipo':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Método no permitido']);
                exit;
            }
            checkCsrfOrFail();
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            if (empty($id)) {
                echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
                exit;
            }
            try {
                // Reactivar equipo: cambiar status a active y limpiar motivos
                $stmt = $pdo->prepare("
                    UPDATE oti.equipment 
                    SET status = 'active', 
                        deactivation_reason = NULL,
                        deactivated_by = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $success = $stmt->execute(['id' => $id]);
                Cache::markDirty('dashboard');
                echo json_encode(['success' => $success]);
            } catch (Exception $e) {
                error_log($e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
            }
            break;
            
        case 'delete-permanent-equipo':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Método no permitido']);
                exit;
            }
            checkCsrfOrFail();
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            $reason = $_POST['reason'] ?? '';
            if (empty($id)) {
                echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
                exit;
            }
            try {
                $userId = $_SESSION['user']['id'] ?? null;
                $stmt = $pdo->prepare("
                    UPDATE oti.equipment
                    SET is_deleted = true,
                        deactivation_reason = :reason,
                        deactivated_by = :user_id,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $success = $stmt->execute(['id' => $id, 'reason' => $reason, 'user_id' => $userId]);
                Cache::markDirty('dashboard');
                echo json_encode(['success' => $success]);
            } catch (Exception $e) {
                error_log($e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Acción no válida']);
            break;
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}