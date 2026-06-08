<?php
/**
 * API de Usuarios
 * Sistema OTI - Datos en tiempo real con filtros
 */

// Configurar para mostrar errores solo en desarrollo
error_reporting(0);
ini_set('display_errors', '0');

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

use App\Models\User;
use App\Models\Location;
use App\Models\Equipment;
use App\Core\Database;

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
            $filters = [
                'location_id' => $_GET['location_id'] ?? null,
                'search'      => $_GET['search'] ?? '',
                'activo'      => $_GET['activo'] ?? ''
            ];
            $page     = max(1, (int)($_GET['page'] ?? 1));
            $pageSize = min(100, max(1, (int)($_GET['pageSize'] ?? 50)));

            $usuarios = User::getAllWithDetails($filters, $page, $pageSize);

            $stmt = $pdo->query("
                SELECT COUNT(*) as total,
                       COUNT(*) FILTER (WHERE u.activo = true) as activos,
                       COUNT(*) FILTER (WHERE u.activo = false) as inactivos
                FROM admin.usuarios u
                LEFT JOIN oti.user_profiles up ON u.id = up.user_id
            ");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'usuarios'       => $usuarios,
                'stats'          => $stats,
                'current_page'   => $page,
                'page_size'      => $pageSize,
            ]);
            break;
            
        case 'create':
            $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
            if (!$email) {
                echo json_encode(['error' => 'Email inválido']);
                break;
            }
             $data = [
                 'nombre' => isset($_POST['nombre']) ? substr(trim($_POST['nombre']), 0, 100) : '',
                 'apellidos' => isset($_POST['apellidos']) ? substr(trim($_POST['apellidos']), 0, 100) : '',
                 'email' => $email,
                 'dni' => isset($_POST['dni']) ? substr(trim($_POST['dni']), 0, 20) : null,
                 'phone' => isset($_POST['phone']) ? substr(trim($_POST['phone']), 0, 20) : null,
                 'location_id' => isset($_POST['location_id']) && $_POST['location_id'] !== '' ? $_POST['location_id'] : null,
                 'position_id' => isset($_POST['position_id']) && $_POST['position_id'] !== '' ? $_POST['position_id'] : null,
                 'role_id' => isset($_POST['role_id']) && $_POST['role_id'] !== '' ? (int)$_POST['role_id'] : null,
                  'password' => $_POST['password'] ?? null,
                  'activo' => $_POST['activo'] ?? true
              ];
             if (!isset($_POST['password']) || empty(trim($_POST['password']))) {
                 echo json_encode(['error' => 'Contraseña requerida']);
                 break;
             }
            
            if (empty($data['nombre'])) {
                echo json_encode(['error' => 'Nombre requerido']);
                break;
            }
            
            $result = User::createWithProfile($data);
            echo json_encode($result);
            break;
            
        case 'get':
            $userId = $_GET['id'] ?? null;
            if (!$userId) {
                echo json_encode(['error' => 'ID requerido']);
                exit;
            }
            try {
                $user = User::findById($userId);
                if (!$user) {
                    echo json_encode(['error' => 'Usuario no encontrado']);
                    exit;
                }
                $equipos = User::getAssignedEquipment($userId);
                $equiposDisponibles = User::getAvailableEquipment($user['location_id'] ?? null);
                
                echo json_encode([
                    'user' => $user,
                    'equipos' => $equipos ?? [],
                    'equipos_disponibles' => $equiposDisponibles ?? []
                ]);
            } catch (Exception $e) {
                error_log($e->getMessage());
                echo json_encode(['error' => 'Error al obtener usuario']);
            }
            break;

        case 'get-permissions':
            $userId = $_GET['id'] ?? null;
            if (!$userId) {
                echo json_encode(['error' => 'ID requerido']);
                exit;
            }
            try {
                $perms = getUserPermissions((int)$userId);
                echo json_encode(['permissions' => $perms]);
            } catch (Exception $e) {
                error_log($e->getMessage());
                echo json_encode(['error' => 'Error al obtener permisos']);
            }
            break;

        case 'update-permissions':
            $userId = $_POST['user_id'] ?? null;
            $perms = $_POST['permissions'] ?? null; // expected JSON string or array
            if (!$userId) {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                exit;
            }
            if (is_string($perms)) {
                // already JSON string from fetch, keep
            } elseif (is_array($perms)) {
                $perms = json_encode($perms);
            } else {
                $perms = json_encode([]);
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO oti.user_profiles (user_id, permissions) VALUES (:user_id, :permissions) ON CONFLICT (user_id) DO UPDATE SET permissions = EXCLUDED.permissions");
                $stmt->execute(['user_id' => $userId, 'permissions' => $perms]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                error_log($e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
            }
            break;
            
        case 'equipment-available':
            $locationId = $_GET['location_id'] ?? null;
            $equipos = User::getAvailableEquipment($locationId);
            echo json_encode(['equipos' => $equipos]);
            break;
            
        case 'update-location':
            $userId = $_POST['user_id'] ?? null;
            $locationId = $_POST['location_id'] ?? null;
            $positionId = $_POST['position_id'] ?? null;
            
            if (!$userId || !$locationId) {
                echo json_encode(['success' => false, 'error' => 'Parámetros requeridos']);
                exit;
            }
            
            $result = User::updateLocation($userId, $locationId, $positionId);
            echo json_encode(['success' => $result]);
            break;

        case 'delete':
            // Soft-delete: marcar usuario como inactivo
            $userId = $_POST['id'] ?? $_GET['id'] ?? null;
            if (!$userId) {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                exit;
            }
            try {
                $pdo->beginTransaction();
                // Desvincular equipos
                $stmtEq = $pdo->prepare("UPDATE oti.equipment SET assigned_user_id = NULL, updated_at = NOW() WHERE assigned_user_id = :id");
                $stmtEq->execute(['id' => $userId]);
                // Marcar usuario inactivo
                $stmt = $pdo->prepare("UPDATE admin.usuarios SET activo = false, updated_at = NOW() WHERE id = :id");
                $stmt->execute(['id' => $userId]);
                $pdo->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Error al desactivar usuario']);
            }
            break;
            
        case 'reactivate':
            // Reactivar usuario (volver a activar)
            $userId = $_POST['id'] ?? $_GET['id'] ?? null;
            if (!$userId) {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                exit;
            }
            try {
                $stmt = $pdo->prepare("UPDATE admin.usuarios SET activo = true WHERE id = :id");
                $stmt->execute(['id' => $userId]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                error_log($e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
            }
            break;
            
        case 'delete-permanent':
            // Eliminar usuario permanentemente de la base de datos
            $userId = $_POST['id'] ?? $_GET['id'] ?? null;
            if (!$userId) {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                exit;
            }
            try {
                $pdo->beginTransaction();

                // Desvincular equipos asignados
                $stmtEq = $pdo->prepare("UPDATE oti.equipment SET assigned_user_id = NULL WHERE assigned_user_id = :user_id");
                $stmtEq->execute(['user_id' => $userId]);

                // Limpiar referencias que bloquearían el DELETE (NO ACTION y RESTRICT)
                $pdo->prepare("UPDATE oti.equipment SET deactivated_by = NULL WHERE deactivated_by = :id")->execute(['id' => $userId]);
                $pdo->prepare("UPDATE oti.equipment_audit SET changed_by = NULL WHERE changed_by = :id")->execute(['id' => $userId]);
                $pdo->prepare("UPDATE oti.locations SET manager_id = NULL WHERE manager_id = :id")->execute(['id' => $userId]);
                $pdo->prepare("DELETE FROM oti.live_feed WHERE user_id = :id")->execute(['id' => $userId]);
                $pdo->prepare("UPDATE oti.tickets SET user_id = NULL, assigned_admin_id = NULL WHERE user_id = :id OR assigned_admin_id = :id2")->execute(['id' => $userId, 'id2' => $userId]);
                $pdo->prepare("UPDATE oti.ticket_comments SET user_id = NULL WHERE user_id = :id")->execute(['id' => $userId]);
                $pdo->prepare("UPDATE oti.ticket_activities SET user_id = NULL WHERE user_id = :id")->execute(['id' => $userId]);
                $pdo->prepare("UPDATE oti.employee_salary SET user_id = NULL WHERE user_id = :id")->execute(['id' => $userId]);
                $pdo->prepare("UPDATE admin.audit_log SET usuario_id = NULL WHERE usuario_id = :id")->execute(['id' => $userId]);

                // Eliminar usuario (CASCADE y SET NULL cubren el resto de tablas)
                $stmt = $pdo->prepare("DELETE FROM admin.usuarios WHERE id = :id");
                $stmt->execute(['id' => $userId]);

                $pdo->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}