<?php
/**
 * Punto de entrada principal del sistema OTI
 * Maneja todas las rutas de la aplicación
 */

$sessionTimeout = 43200; // 12h absolute
$inactivityTimeout = 1800; // 30min

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/OTI/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

if (isset($_SESSION['user'])) {
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $sessionTimeout) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['error'] = 'Sesión expirada por tiempo máximo. Inicie sesión nuevamente.';
        header('Location: ' . ((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/OTI/login'));
        exit;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $inactivityTimeout) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['error'] = 'Sesión expirada por inactividad. Inicie sesión nuevamente.';
        header('Location: ' . ((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/OTI/login'));
        exit;
    }
    $_SESSION['last_activity'] = time();
    if (empty($_SESSION['_last_regenerated']) || $_SESSION['_last_regenerated'] < time() - 300) {
        session_regenerate_id();
        $_SESSION['_last_regenerated'] = time();
    }
}

// Headers de seguridad y rendimiento
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Cache-Control: private, no-cache, must-revalidate, max-age=0');

define('BASE_URL', (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/OTI/');

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\AuthService;
use App\Services\TicketService;
use App\Models\Ticket;
use App\Controller\AuthController;
use App\Helpers\FeatureFlag;
use App\Cache\Store as Cache;

// CSRF Middleware: verify token on all POST/PUT/DELETE requests
$requestMethod = $_SERVER['REQUEST_METHOD'];
if (in_array($requestMethod, ['POST', 'PUT', 'DELETE']) && FeatureFlag::isActive('CSRF_STRICT')) {
    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
    if (!verify_csrf($token)) {
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || strpos($_SERVER['REQUEST_URI'], '/-v2/') !== false) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF inválido o expirado. Recargue la página e intente nuevamente.']);
        } else {
            $_SESSION['error'] = 'Token CSRF inválido o expirado. Recargue la página e intente nuevamente.';
            $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
            header('Location: ' . $referer);
        }
        exit;
    }
}

$requestUri = $_SERVER['REQUEST_URI'];

$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/OTI', '', $path);
$path = $path ?: '/';

if ($path === '/login' && $requestMethod === 'GET') {
    if (isset($_SESSION['user'])) {
        $isOtiAdmin = \App\Services\AuthService::isAdmin();
        header('Location: ' . ($isOtiAdmin ? BASE_URL . 'admin/dashboard' : BASE_URL . 'user/dashboard'));
        exit;
    }
    require __DIR__ . '/app/Views/auth/login.php';
    exit;
}

if ($path === '/login' && $requestMethod === 'POST') {
    $controller = new AuthController();
    $controller->login();
    exit;
}

if ($path === '/logout') {
    $controller = new AuthController();
    $controller->logout();
    exit;
}

if ($path === '/user/ticket/crear' && $requestMethod === 'POST') {
    if (!isset($_SESSION['user'])) {
        header('Location: ' . BASE_URL . 'login');
        exit;
    }
    
    $userId = $_SESSION['user']['id'] ?? null;
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $serviceTypeId = $_POST['service_type_id'] ?? null;
    $equipmentId = $_POST['equipment_id'] ?? null;
    $locationId = $_POST['location_id'] ?? null;

    if (empty($title) || empty($description)) {
        $_SESSION['error'] = 'Por favor complete todos los campos requeridos';
        header('Location: ' . BASE_URL . 'user/reportar');
        exit;
    }

    $result = TicketService::create($userId, [
        'title' => $title,
        'description' => $description,
        'service_type_id' => $serviceTypeId,
        'equipment_id' => $equipmentId,
        'location_id' => $locationId
    ]);

    if ($result['success']) {
        Cache::markDirty('dashboard');
        $_SESSION['success'] = 'Ticket creado exitosamente. Código: ' . $result['code'];
        header('Location: ' . BASE_URL . 'user/tickets');
    } else {
        $_SESSION['error'] = 'Error al crear el ticket';
        header('Location: ' . BASE_URL . 'user/reportar');
    }
    exit;
}

if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'login');
    exit;
}

$userId = $_SESSION['user']['id'] ?? null;

// Determinar si es admin de OTI basado en el rol
$isOtiAdmin = \App\Services\AuthService::isAdmin();

// Rutas de API para usuarios (solo admin)
if ($isOtiAdmin && strpos($path, '/api/usuarios') === 0) {
    require __DIR__ . '/app/api/usuarios.php';
    exit;
}

// Rutas de gestión de usuarios (solo admin)
if ($isOtiAdmin && $path === '/admin/usuarios/crear' && $requestMethod === 'POST') {
    require __DIR__ . '/app/api/usuarios.php';
    exit;
}

// ============================================================
// RUTAS DE ADMINISTRACIÓN (solo para admin)
// ============================================================
if ($isOtiAdmin && strpos($path, '/admin/') === 0) {
    // V2 Controller API routing (under admin path for admin access)
    if (FeatureFlag::isActive('MODULE_TICKETS_V2') && strpos($path, '/admin/tickets-v2/') === 0) {
        $action = $_GET['action'] ?? 'renderListView';
        $controller = new \App\Controller\v2\TicketController();
        if (method_exists($controller, $action)) {
            $controller->$action();
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Acción no encontrada']);
        }
        exit;
    }
    if (FeatureFlag::isActive('MODULE_USERS_V2') && strpos($path, '/admin/usuarios-v2/') === 0) {
        $action = $_GET['action'] ?? 'renderListView';
        $controller = new \App\Controller\v2\UserController();
        if (method_exists($controller, $action)) {
            $controller->$action();
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Acción no encontrada']);
        }
        exit;
    }
    if (FeatureFlag::isActive('MODULE_EQUIPMENT_V2') && strpos($path, '/admin/equipos-v2/') === 0) {
        $action = $_GET['action'] ?? 'renderListView';
        $controller = new \App\Controller\v2\EquipmentController();
        if (method_exists($controller, $action)) {
            $controller->$action();
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Acción no encontrada']);
        }
        exit;
    }

    switch ($path) {
        case '/admin/dashboard':
            require __DIR__ . '/app/Views/admin/dashboard.php';
            exit;

        case '/admin/tickets':
            if (FeatureFlag::isActive('MODULE_TICKETS_V2')) {
                $controller = new \App\Controller\v2\TicketController();
                $controller->renderListView();
                exit;
            }
            $filters = [];
            if (!empty($_GET['status'])) {
                $statusMap = ['abiertos' => 1, 'proceso' => 2, 'resueltos' => 3, 'cerrados' => 4];
                if (isset($statusMap[$_GET['status']])) {
                    $filters['status_id'] = $statusMap[$_GET['status']];
                }
            }
            $tickets = Ticket::getAll($filters);
            require __DIR__ . '/app/Views/admin/tickets.php';
            exit;

        case '/admin/equipos':
            if (FeatureFlag::isActive('MODULE_EQUIPMENT_V2')) {
                $controller = new \App\Controller\v2\EquipmentController();
                $controller->renderListView();
                exit;
            }
            require __DIR__ . '/app/Views/admin/equipos.php';
            exit;

        case '/admin/usuarios':
            if (FeatureFlag::isActive('MODULE_USERS_V2')) {
                $controller = new \App\Controller\v2\UserController();
                $controller->renderListView();
                exit;
            }
            require __DIR__ . '/app/Views/admin/usuarios.php';
            exit;

        case '/admin/estructura':
            require __DIR__ . '/app/Views/admin/estructura.php';
            exit;

        case '/admin/analisis':
            require __DIR__ . '/app/Views/admin/analisis.php';
            exit;

        case '/admin/resumen':
            require __DIR__ . '/app/Views/admin/resumen.php';
            exit;

        default:
            header('Location: ' . BASE_URL . 'admin/dashboard');
            exit;
    }
}

// ============================================================
// RUTAS DE USUARIO (admin y usuario regular)
// ============================================================
if (strpos($path, '/user/') === 0) {
    switch ($path) {
        case '/user/dashboard':
            require __DIR__ . '/app/Views/user/dashboard.php';
            exit;

        case '/user/reportar':
            require __DIR__ . '/app/Views/user/reportar.php';
            exit;

        case '/user/tickets':
            $tickets = Ticket::getByUserId($userId);
            require __DIR__ . '/app/Views/user/tickets.php';
            exit;
            
        case '/user/tickets-monitar':
            require __DIR__ . '/app/Views/user/tickets-monitar.php';
            exit;
            
        case '/user/tickets-live':
            require __DIR__ . '/app/Views/user/tickets-live-minimal.php';
            exit;
            
        case '/user/ticket-detalle':
            require __DIR__ . '/app/Views/user/ticket-detalle.php';
            exit;
            
        case '/user/notificaciones':
            require __DIR__ . '/app/Views/user/notificaciones.php';
            exit;
            
        case '/user/profile':
            require __DIR__ . '/app/Views/user/profile.php';
            exit;

        default:
            header('Location: ' . BASE_URL . 'user/dashboard');
            exit;
    }
}

// ============================================================
// REDIRECCIÓN POR DEFECTO
// ============================================================
if ($isOtiAdmin) {
    header('Location: ' . BASE_URL . 'admin/dashboard');
} else {
    header('Location: ' . BASE_URL . 'user/dashboard');
}
exit;