<?php
/**
 * API de Permisos RBAC
 * Endpoint: /app/api/permissions.php
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, private');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/OTI/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\AuthService;

if (!AuthService::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = AuthService::getCurrentUserId();

switch ($action) {
    case 'get-user-permissions':
        $permissions = getUserPermissions($userId);
        $roleHierarchy = getRoleHierarchy(AuthService::getRoleId());
        
        echo json_encode([
            'success' => true,
            'permissions' => $permissions,
            'role_hierarchy' => $roleHierarchy,
            'cached_at' => time(),
            'expires_in' => 300
        ]);
        break;

    case 'check-permission':
        $permission = $_GET['permission'] ?? '';
        $hasAccess = AuthService::hasPermission($permission);
        echo json_encode(['has_access' => $hasAccess]);
        break;

    case 'get-all-permissions':
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Permiso denegado']);
            exit;
        }
        
        $permissions = getAllPermissions();
        echo json_encode(['success' => true, 'permissions' => $permissions]);
        break;

    case 'invalidate-cache':
        AuthService::invalidatePermissionCache($userId);
        echo json_encode(['success' => true, 'message' => 'Caché invalidado']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida']);
}

function getUserPermissions(int $userId): array {
    try {
        $db = \App\Core\Database::connect();
        
        $stmt = $db->prepare("
            SELECT DISTINCT p.codigo, p.nombre, p.modulo, p.accion
            FROM admin.permisos p
            WHERE 
                p.id IN (
                    SELECT DISTINCT rp.permiso_id 
                    FROM admin.usuario_rol ur
                    JOIN admin.rol_permiso rp ON ur.rol_id = rp.rol_id
                    WHERE ur.usuario_id = :user_id 
                    AND ur.sistema_id = (SELECT id FROM admin.sistemas WHERE slug = 'oti' LIMIT 1)
                    AND (rp.expira_en IS NULL OR rp.expira_en > NOW())
                )
                OR
                p.id IN (
                    SELECT permiso_id 
                    FROM admin.usuario_permiso_especial
                    WHERE usuario_id = :user_id AND tipo = 'otorgar'
                    AND (expira_en IS NULL OR expira_en > NOW())
                )
            ORDER BY p.modulo, p.accion
        ");
        
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
    } catch (\Throwable $e) {
        error_log("Error fetching permissions: " . $e->getMessage());
        return [];
    }
}

function getRoleHierarchy(?int $roleId): array {
    if (!$roleId) return [];
    
    $db = \App\Core\Database::connect();
    
    $stmt = $db->prepare("
        SELECT r.nombre as role_name
        FROM admin.roles r
        WHERE r.id = :role_id
    ");
    
    $stmt->execute(['role_id' => $roleId]);
    $role = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    // Definir jerarquías: admin > gestor > usuario
    $hierarchy = [
        'admin' => ['priority' => 100],
        'gestor' => ['priority' => 50],
        'usuario' => ['priority' => 10]
    ];
    
    return $hierarchy[$role['role_name'] ?? 'usuario'] ?? ['priority' => 10];
}

function getAllPermissions(): array {
    $db = \App\Core\Database::connect();
    
    $stmt = $db->query("
        SELECT id, codigo, nombre, modulo, accion, categoria, nivel
        FROM admin.permisos
        ORDER BY modulo, accion
    ");
    
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}