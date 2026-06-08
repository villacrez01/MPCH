<?php
/**
 * API de Perfil de Usuario
 * Sistema OTI
 */

require_once __DIR__ . '/../../Models/User.php';
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user']['id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

switch ($action) {
    case 'update-profile':
        $email = $_POST['email'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'El correo es obligatorio']);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Correo inválido']);
            exit;
        }
        
        $result = \App\Models\User::updateUserProfile($userId, [
            'email' => $email,
            'telefono' => $telefono
        ]);
        
        if ($result) {
            $_SESSION['user']['email'] = $email;
            echo json_encode(['success' => true, 'message' => 'Perfil actualizado']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
        }
        break;
        
    case 'change-password':
        $actual = $_POST['actual'] ?? '';
        $nueva = $_POST['nueva'] ?? '';
        
        if (empty($actual) || empty($nueva)) {
            echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
            exit;
        }
        
        $pdo = \App\Core\Database::connect();
        $stmt = $pdo->prepare("SELECT password_hash FROM admin.usuarios WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($actual, $user['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta']);
            exit;
        }
        
        $result = \App\Models\User::changeUserPassword($userId, $nueva);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Contraseña cambiada correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al cambiar contraseña']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}