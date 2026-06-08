# C-E3: Optimización Progresiva — Refactor por Módulos + Feature Flags

## Objetivo
Refactor progresivo del sistema OTI módulo por módulo usando **feature flags** para activar/desactivar cada nuevo componente, con **fallback automático** a V1 si algo falla. Esto permite desplegar continuamente sin arriesgar la estabilidad del sistema.

---

## Sistema de Feature Flags (base transversal)

### 1. `app/Helpers/FeatureFlag.php` — Clase completa

**Problema:** No existe un mecanismo centralizado para activar/desactivar funcionalidades nuevas. Las rutas V2 se mezclan con V1 sin control.

**Archivos afectados:** (nuevo) `app/Helpers/FeatureFlag.php`

**Solución:** Clase con caché estática, lectura desde `.env`, TTL opcional.

**Feature Flag:** `MODULE_TICKETS_V2`, `MODULE_USERS_V2`, `MODULE_EQUIPMENT_V2`, `MODULE_CSRF_STRICT`

**Código:**

```php
<?php
declare(strict_types=1);

namespace App\Helpers;

class FeatureFlag
{
    private static array $cache = [];
    private static array $expiresAt = [];

    public static function isActive(string $flag, ?int $ttl = null): bool
    {
        $key = 'MODULE_' . strtoupper($flag);

        if (isset(self::$cache[$key]) && (self::$expiresAt[$key] ?? 0) > time()) {
            return self::$cache[$key];
        }

        $value = getenv($key);

        if ($value === false) {
            $defaults = self::defaults();
            $active = $defaults[$key] ?? false;
        } else {
            $active = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        self::$cache[$key] = $active;
        self::$expiresAt[$key] = $ttl !== null ? time() + $ttl : PHP_INT_MAX;

        return $active;
    }

    public static function all(): array
    {
        $flags = [];
        foreach (array_keys(self::defaults()) as $key) {
            $shortName = str_replace('MODULE_', '', $key);
            $flags[$shortName] = self::isActive($shortName);
        }
        return $flags;
    }

    public static function flush(): void
    {
        self::$cache = [];
        self::$expiresAt = [];
    }

    private static function defaults(): array
    {
        return [
            'MODULE_TICKETS_V2' => false,
            'MODULE_USERS_V2' => false,
            'MODULE_EQUIPMENT_V2' => false,
            'MODULE_CSRF_STRICT' => false,
            'MODULE_SEARCH_V2' => false,
        ];
    }
}
```

**Verificación:**
```bash
# CLI test
php -r "require 'vendor/autoload.php'; echo \App\Helpers\FeatureFlag::isActive('TICKETS_V2') ? 'on' : 'off';"
```

---

### 2. `app/Controller/BaseController.php` — Controlador base

**Problema:** No hay estandarización en controladores: cada endpoint maneja JSON, validación y errores de forma distinta.

**Archivos afectados:** (nuevo) `app/Controller/BaseController.php`

**Solución:** Clase abstracta con métodos utilitarios que todos los controladores V2 extenderán.

**Feature Flag:** (ninguno — es infraestructura)

**Código:**

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helpers\FeatureFlag;

abstract class BaseController
{
    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    protected function error(string $message, int $status = 400): void
    {
        $this->json(['success' => false, 'error' => $message], $status);
    }

    protected function success(mixed $data = null, string $message = 'OK'): void
    {
        $this->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    protected function view(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }
        require $viewPath;
        exit;
    }

    protected function redirect(string $url): void
    {
        $baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/OTI/';
        header('Location: ' . $baseUrl . ltrim($url, '/'));
        exit;
    }

    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $rules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;

            foreach ($rules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                switch ($rule) {
                    case 'required':
                        if ($value === null || $value === '') {
                            $errors[$field][] = "El campo {$field} es requerido";
                        }
                        break;
                    case 'email':
                        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = "El campo {$field} debe ser un email válido";
                        }
                        break;
                    case 'min':
                        $min = (int)($params[0] ?? 0);
                        if (is_string($value) && strlen($value) < $min) {
                            $errors[$field][] = "El campo {$field} debe tener al menos {$min} caracteres";
                        }
                        break;
                    case 'max':
                        $max = (int)($params[0] ?? 255);
                        if (is_string($value) && strlen($value) > $max) {
                            $errors[$field][] = "El campo {$field} no debe exceder {$max} caracteres";
                        }
                        break;
                    case 'int':
                        if ($value !== null && $value !== '' && !ctype_digit((string)$value)) {
                            $errors[$field][] = "El campo {$field} debe ser un número entero";
                        }
                        break;
                    case 'numeric':
                        if ($value !== null && $value !== '' && !is_numeric($value)) {
                            $errors[$field][] = "El campo {$field} debe ser numérico";
                        }
                        break;
                    case 'in':
                        $allowed = $params;
                        if ($value !== null && $value !== '' && !in_array((string)$value, $allowed, true)) {
                            $errors[$field][] = "El campo {$field} contiene un valor no válido";
                        }
                        break;
                }
            }

            $validated[$field] = $value;
        }

        if (!empty($errors)) {
            $this->json(['success' => false, 'errors' => $errors], 422);
        }

        return $validated;
    }

    protected function csrf(): string
    {
        return csrf_token();
    }

    protected function verifyCsrf(): void
    {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verify_csrf($token)) {
            $this->error('CSRF token inválido', 419);
        }
    }

    protected function isFeatureActive(string $flag): bool
    {
        return FeatureFlag::isActive($flag);
    }
}
```

---

### 3. Fallback automático en `index.php`

**Problema:** Si una ruta V2 falla, el usuario ve un error 500. No hay redirección automática a la funcionalidad V1 equivalente.

**Archivos afectados:** `index.php`

**Solución:** Envolver handlers V2 en try/catch con log y redirect a V1.

**Feature Flag:** `MODULE_TICKETS_V2`, `MODULE_USERS_V2`, `MODULE_EQUIPMENT_V2`

**Código (añadir en `index.php` antes del sistema de rutas actual, después de `session_start()`):**

```php
// === FEATURE FLAG FALLBACK SYSTEM ===
require_once __DIR__ . '/app/Helpers/FeatureFlag.php';
use App\Helpers\FeatureFlag;

// === CSRF STRICT CHECK ===
if (FeatureFlag::isActive('CSRF_STRICT') && $requestMethod === 'POST') {
    $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf($csrfToken)) {
        $_SESSION['error'] = 'Token de seguridad inválido. Intente nuevamente.';
        $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL . 'login';
        header('Location: ' . $referer);
        exit;
    }
}
```

**Código (función helper para fallback — añadir antes del switch de rutas admin):**

```php
function runV2WithFallback(callable $v2Fn, string $fallbackUrl): void
{
    try {
        $v2Fn();
    } catch (\Throwable $e) {
        error_log("[V2_FALLBACK] Error en módulo V2: {$e->getMessage()} en {$e->getFile()}:{$e->getLine()}");
        $_SESSION['warning'] = 'El módulo mejorado no está disponible momentáneamente. Redirigiendo...';
        header('Location: ' . $fallbackUrl);
        exit;
    }
}
```

**Uso en rutas (ejemplo para tickets):**

```php
// Dentro del switch de admin, reemplazar caso tickets:
case '/admin/tickets':
    if (FeatureFlag::isActive('TICKETS_V2')) {
        runV2WithFallback(
            function () {
                $controller = new \App\Controller\v2\TicketController();
                $controller->index();
            },
            BASE_URL . 'admin/dashboard'
        );
    } else {
        $filters = [];
        if (!empty($_GET['status'])) {
            $statusMap = ['abiertos' => 1, 'proceso' => 2, 'resueltos' => 3, 'cerrados' => 4];
            if (isset($statusMap[$_GET['status']])) {
                $filters['status_id'] = $statusMap[$_GET['status']];
            }
        }
        $tickets = Ticket::getAll($filters);
        require __DIR__ . '/app/Views/admin/tickets.php';
    }
    exit;
```

---

## SPRINT 0: Parches de seguridad urgentes (días 1-3)

### 4. CSRF en todos los POST

**Problema:** Aunque existe `csrf_token()` y `verify_csrf()`, no se invocan en `index.php` para las rutas POST. Cualquier formulario POST es vulnerable a CSRF.

**Archivos afectados:** `index.php`, `app/Views/auth/login.php`, `app/Views/user/reportar.php`, `app/Views/user/ticket-detalle.php` (línea 526), `app/Views/partials/head.php`

**Solución:** Activar `verify_csrf()` en `index.php` cuando `MODULE_CSRF_STRICT` está activo, y añadir `csrf_token()` en todos los formularios.

**Feature Flag:** `MODULE_CSRF_STRICT`

**Código en `index.php` (reemplazar bloque CSRF mostrado arriba con este más completo):**

```php
// === CSRF STRICT CHECK (protege todos los POST) ===
if ($requestMethod === 'POST' && FeatureFlag::isActive('CSRF_STRICT')) {
    $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf($csrfToken)) {
        $_SESSION['error'] = 'Token de seguridad inválido. Intente nuevamente.';
        $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
        // Si es API, devolver JSON
        if (strpos($path, '/api/') === 0) {
            header('Content-Type: application/json');
            http_response_code(419);
            echo json_encode(['error' => 'CSRF token inválido']);
            exit;
        }
        header('Location: ' . $referer);
        exit;
    }
}
```

**Código para formularios — añadir campo oculto después de cada `<form>` con method POST:**

```php
<!-- Añadir en login.php, reportar.php, y cualquier otro formulario POST -->
<input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
```

**Verificación:**
1. Activar `MODULE_CSRF_STRICT=true` en `.env`
2. Enviar POST sin `_csrf_token` → debe devolver error 419 o redirect con mensaje de error
3. Enviar POST con `_csrf_token` correcto → debe funcionar normalmente

---

### 5. Auth bypass fix (strpos centralizado)

**Problema:** El patrón `strpos($roleName, 'admin') !== false || strpos($roleName, 'director') !== false || ...` se repite en 6 lugares: `index.php:35-40`, `index.php:107-112`, `tickets.php:31-36`, `ticket-detalle.php:14-19`, `realtime.js:27-31`, `AuthController.php:37-42`, `AuthService.php:109-114`. Un rol llamado "administrativo" matchearía falsamente como admin. Además, si se cambia la lógica de roles, hay que actualizar 6 lugares.

**Archivos afectados:** `app/Services/AuthService.php`, `index.php`, `app/api/tickets.php`, `app/api/usuarios.php`, `app/Views/user/ticket-detalle.php`, `app/Controller/AuthController.php`

**Solución:** Centralizar en `AuthService::isAdmin()` y reemplazar todas las ocurrencias. Además, reemplazar `strpos` con una consulta real a BD que verifique el `es_admin` flag + tabla de roles via join.

**Feature Flag:** (ninguno — es fix de seguridad obligatorio)

**Código — `AuthService::isAdmin()` mejorado:**

```php
public static function isAdmin(): bool
{
    if (!isset($_SESSION['user'])) {
        return false;
    }

    // Si ya está marcado como admin en sesión, devolver true
    if (!empty($_SESSION['user']['es_admin'])) {
        return true;
    }

    // Verificar contra BD para evitar manipulación de sesión
    try {
        $pdo = \App\Core\Database::connect();
        $stmt = $pdo->prepare("
            SELECT u.es_admin
            FROM admin.usuarios u
            WHERE u.id = :id AND u.activo = true
            LIMIT 1
        ");
        $stmt->execute(['id' => $_SESSION['user']['id']]);
        $user = $stmt->fetch();

        if ($user && !empty($user['es_admin'])) {
            $_SESSION['user']['es_admin'] = true;
            return true;
        }

        // También verificar por rol
        $stmt = $pdo->prepare("
            SELECT 1 FROM admin.usuarios u
            JOIN admin.roles r ON u.role_id = r.id
            WHERE u.id = :id
              AND u.activo = true
              AND (u.es_admin = true OR r.es_admin = true)
            LIMIT 1
        ");
        $stmt->execute(['id' => $_SESSION['user']['id']]);
        if ($stmt->fetch()) {
            $_SESSION['user']['es_admin'] = true;
            return true;
        }

        return false;
    } catch (\Throwable $e) {
        error_log("[AUTH] Error verificando admin: " . $e->getMessage());
        // Fallback seguro: no admin
        return false;
    }
}
```

**Código — helper global `is_oti_admin()` para reemplazar strpos en todos lados:**

```php
// Añadir en app/Helpers/functions.php
if (!function_exists('is_oti_admin')) {
    function is_oti_admin(): bool
    {
        return \App\Services\AuthService::isAdmin();
    }
}
```

**Reemplazar en `index.php` (líneas 34-40 y 107-112):**

```php
// Reemplazar bloque en línea 34-40:
if ($path === '/login' && $requestMethod === 'GET') {
    if (isset($_SESSION['user'])) {
        $isOtiAdmin = \App\Services\AuthService::isAdmin();
        header('Location: ' . ($isOtiAdmin ? BASE_URL . 'admin/dashboard' : BASE_URL . 'user/dashboard'));
        exit;
    }
    require __DIR__ . '/app/Views/auth/login.php';
    exit;
}

// Reemplazar bloque en línea 102-112:
$isOtiAdmin = \App\Services\AuthService::isAdmin();
```

**Reemplazar en `app/api/tickets.php` (líneas 29-36):**

```php
$isAdmin = \App\Services\AuthService::isAdmin();
```

**Reemplazar en `app/Views/user/ticket-detalle.php` (líneas 13-19):**

```php
$isOtiAdmin = \App\Services\AuthService::isAdmin();
```

**Reemplazar en `app/Controller/AuthController.php` (líneas 35-42):**

```php
$isOtiAdmin = \App\Services\AuthService::isAdmin();
```

**Reemplazar en `public/assets/js/realtime.js` (líneas 23-33):**

```javascript
// Ya no confiar en texto de rol desde JS. Backend debe indicar es_admin vía data attribute.
// En su lugar, el HTML debe tener:
// <input type="hidden" id="is-admin" value="<?= $isOtiAdmin ? '1' : '0' ?>">
// Y JS solo leer ese valor:
isAdmin = adminElement ? adminElement.value === '1' : false;
```

**Verificación:**
1. Crear un usuario con rol que contenga "admin" como substring (ej: "administrativo")
2. Antes del fix: ese usuario sería tratado como admin (falso positivo)
3. Después del fix: solo usuarios con `es_admin=true` en BD o con `r.es_admin=true` son admin

---

### 6. CORS restrictivo

**Problema:** `app/api/tickets.php:10` tiene `header('Access-Control-Allow-Origin: *')`. sse.php también (línea 13). Esto permite que cualquier sitio externo haga peticiones AJAX a la API.

**Archivos afectados:** `app/api/tickets.php`, `app/api/sse.php`

**Solución:** Implementar whitelist dinámica basada en `APP_URL` del `.env`.

**Feature Flag:** (ninguno — seguridad)

**Código — reemplazar en `app/api/tickets.php`:**

```php
// Reemplazar líneas 10-12:
$allowedOrigin = rtrim(getenv('APP_URL') ?: 'http://localhost/OTI', '/');
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');
```

**Código — reemplazar en `app/api/sse.php`:**

```php
// Reemplazar líneas 13-15:
$allowedOrigin = rtrim(getenv('APP_URL') ?: 'http://localhost/OTI', '/');
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
```

**Verificación:**
1. Intentar fetch desde `http://evil.com` → debe ser bloqueado por el navegador
2. Intentar fetch desde `http://localhost/OTI` → debe funcionar

---

### 7. Eliminar credenciales hardcodeadas

**Problema:** `app/Core/Database.php:19` tiene `$password = getenv('DB_PASSWORD') ?: '123456789'` — si no se carga `.env`, la contraseña por defecto es `123456789`. Además en `usuarios.php:76` la contraseña por defecto es `'OTI' . date('Y')`.

**Archivos afectados:** `app/Core/Database.php`, `app/api/usuarios.php`

**Solución:** Eliminar defaults inseguros. Si no hay `.env`, la conexión debe fallar explícitamente.

**Feature Flag:** (ninguno — seguridad)

**Código — `Database.php` líneas 15-19:**

```php
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$db_name = getenv('DB_DATABASE');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');

if (empty($host) || empty($db_name) || empty($username) || empty($password)) {
    throw new \RuntimeException(
        'Credenciales de base de datos no configuradas. '
        . 'Verifique el archivo .env con DB_HOST, DB_DATABASE, DB_USERNAME y DB_PASSWORD.'
    );
}
```

**Código — `usuarios.php` línea 76:**

```php
'password' => $_POST['password'] ?? bin2hex(random_bytes(16)),
```

**Verificación:**
1. Temporalmente renombrar `.env` a `.env.bak`
2. Acceder al sistema → debe mostrar error claro de configuración, no usar default `123456789`

---

## SPRINT 1: Módulo Tickets V2 (semana 2)

### 8. `app/Controller/v2/TicketController.php` — Controlador V2 completo

**Problema:** La lógica de tickets está dispersa entre `index.php` (routing), `app/api/tickets.php` (API cruda) y `TicketService::create()` (service). No hay validación centralizada, no hay CSRF automático.

**Archivos afectados:** (nuevo) `app/Controller/v2/TicketController.php`, `index.php`

**Solución:** Controlador V2 que extiende `BaseController`, con CRUD completo, validación, CSRF y prepared statements.

**Feature Flag:** `MODULE_TICKETS_V2`

**Código:**

```php
<?php
declare(strict_types=1);

namespace App\Controller\v2;

use App\Controller\BaseController;
use App\Models\Ticket;
use App\Models\Notification;
use App\Services\AuthService;

class TicketController extends BaseController
{
    private ?int $userId;
    private bool $isAdmin;

    public function __construct()
    {
        $this->userId = $_SESSION['user']['id'] ?? null;
        $this->isAdmin = AuthService::isAdmin();
    }

    public function index(): void
    {
        if ($this->isAdmin) {
            $this->adminIndex();
        } else {
            $this->userIndex();
        }
    }

    private function adminIndex(): void
    {
        $filters = $this->parseFilters();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($_GET['pageSize'] ?? 20)));

        if ($this->isAjax()) {
            $this->json([
                'success' => true,
                'tickets' => Ticket::getAll($filters, $page, $pageSize),
                'pagination' => $this->paginationData(
                    Ticket::getTotalCount($filters), $page, $pageSize
                ),
            ]);
        }

        $this->view('v2/tickets/tickets', [
            'tickets' => Ticket::getAll($filters, $page, $pageSize),
            'pagination' => $this->paginationData(
                Ticket::getTotalCount($filters), $page, $pageSize
            ),
        ]);
    }

    private function userIndex(): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($_GET['pageSize'] ?? 20)));
        $tickets = Ticket::getByUserId($this->userId, $page, $pageSize);

        $this->view('v2/tickets/tickets', [
            'tickets' => $tickets,
            'isUserView' => true,
        ]);
    }

    public function show(int $id): void
    {
        $ticket = Ticket::findById($id);
        if (!$ticket) {
            $this->error('Ticket no encontrado', 404);
        }

        if (!$this->isAdmin && (int)$ticket['user_id'] !== $this->userId) {
            $this->error('No autorizado', 403);
        }

        $activities = Ticket::getActivities($id);

        $this->view('v2/tickets/ticket-detalle', [
            'ticket' => $ticket,
            'activities' => $activities,
            'isAdmin' => $this->isAdmin,
            'canCancel' => $ticket['status_id'] == 1 && (int)$ticket['user_id'] === $this->userId,
        ]);
    }

    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->view('v2/tickets/ticket-form', [
                'isEdit' => false,
                'ticket' => [],
            ]);
        }

        $this->verifyCsrf();
        $data = $this->validate($_POST, [
            'title' => 'required|min:5|max:200',
            'description' => 'required|min:10',
            'service_type_id' => 'int',
            'equipment_id' => 'int',
            'location_id' => 'int',
        ]);

        $result = \App\Services\TicketService::create($this->userId, $data);

        if ($result['success']) {
            $_SESSION['success'] = "Ticket creado exitosamente. Código: {$result['code']}";
            $this->redirect('user/tickets');
        }

        $_SESSION['error'] = $result['error'] ?? 'Error al crear el ticket';
        $this->redirect('user/reportar');
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $this->isAdminOrFail();

        $data = $this->validate($_POST, [
            'estado' => 'in:abierto,en_proceso,resuelto,cerrado',
            'prioridad' => 'int',
            'asignado' => 'int',
            'respuesta' => 'max:5000',
            'tiempo_valor' => 'int',
            'tiempo_unidad' => 'in:horas,dias,semanas,meses',
        ]);

        $statusMap = [
            'abierto' => 1, 'en_proceso' => 2, 'resuelto' => 3, 'cerrado' => 4,
        ];

        $updates = [];
        $params = ['id' => $id];

        if (!empty($data['estado'])) {
            $updates[] = "status_id = :status_id";
            $params['status_id'] = $statusMap[$data['estado']] ?? 1;
        }
        if (!empty($data['prioridad'])) {
            $updates[] = "priority_id = :prioridad";
            $params['prioridad'] = (int)$data['prioridad'];
        }
        if (isset($data['asignado'])) {
            $updates[] = "assigned_admin_id = :asignado";
            $params['asignado'] = $data['asignado'] ? (int)$data['asignado'] : null;
        }

        $hours = $this->calculateHours(
            (int)($data['tiempo_valor'] ?? 0),
            $data['tiempo_unidad'] ?? 'horas'
        );
        if ($hours > 0) {
            $updates[] = "resolution_time_hours = :resolution_time_hours";
            $params['resolution_time_hours'] = $hours;
        }

        if (in_array($data['estado'] ?? '', ['cerrado', 'resuelto'], true)) {
            $updates[] = "closed_at = NOW()";
            $updates[] = "resolved_at = NOW()";
        }

        $updates[] = "updated_at = NOW()";

        $pdo = \App\Core\Database::connect();
        $sql = "UPDATE oti.tickets SET " . implode(', ', $updates) . " WHERE id = :id";
        $pdo->prepare($sql)->execute($params);

        if (!empty($data['respuesta'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO oti.ticket_comments (ticket_id, user_id, comment, created_at)
                 VALUES (:ticket_id, :user_id, :comment, NOW())"
            );
            $stmt->execute([
                'ticket_id' => $id,
                'user_id' => $this->userId,
                'comment' => $data['respuesta'],
            ]);
        }

        $this->notifyUser($id, $data);

        $this->success(null, 'Ticket actualizado');
    }

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        $this->isAdminOrFail();

        $pdo = \App\Core\Database::connect();
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM oti.ticket_comments WHERE ticket_id = :id")->execute(['id' => $id]);
        $pdo->prepare("DELETE FROM oti.tickets WHERE id = :id")->execute(['id' => $id]);
        $pdo->commit();

        $this->success(null, 'Ticket eliminado');
    }

    public function cancel(int $id): void
    {
        $this->verifyCsrf();
        $result = Ticket::cancel($id, $this->userId);

        if ($result['success']) {
            $_SESSION['success'] = 'Ticket cancelado correctamente';
        } else {
            $_SESSION['error'] = $result['error'];
        }

        $this->redirect('user/ticket-detalle?id=' . $id);
    }

    public function stats(): void
    {
        $filters = [];
        if (!$this->isAdmin) {
            $filters['user_id'] = $this->userId;
        }
        $this->json(['success' => true, 'stats' => Ticket::getStats($filters)]);
    }

    // --- Métodos privados ---

    private function parseFilters(): array
    {
        $filters = [];
        foreach (['status_id', 'priority_id', 'assigned_admin_id', 'user_id', 'search', 'date_from', 'date_to'] as $key) {
            if (!empty($_GET[$key])) {
                $filters[$key] = in_array($key, ['status_id', 'priority_id', 'assigned_admin_id', 'user_id'])
                    ? (int)$_GET[$key]
                    : $_GET[$key];
            }
        }
        return $filters;
    }

    private function paginationData(int $total, int $page, int $pageSize): array
    {
        $totalPages = max(1, (int)ceil($total / $pageSize));
        return [
            'currentPage' => $page,
            'pageSize' => $pageSize,
            'totalCount' => $total,
            'totalPages' => $totalPages,
        ];
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            || !empty($_GET['ajax']);
    }

    private function isAdminOrFail(): void
    {
        if (!$this->isAdmin) {
            $this->error('Acceso denegado', 403);
        }
    }

    private function calculateHours(int $valor, string $unidad): int
    {
        return match ($unidad) {
            'horas' => $valor,
            'dias' => $valor * 24,
            'semanas' => $valor * 168,
            'meses' => $valor * 720,
            default => 0,
        };
    }

    private function notifyUser(int $ticketId, array $data): void
    {
        $pdo = \App\Core\Database::connect();
        $stmt = $pdo->prepare("SELECT user_id, code FROM oti.tickets WHERE id = :id");
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) return;

        $enviarMensaje = !empty($_POST['enviar_mensaje'])
            && in_array($_POST['enviar_mensaje'], ['1', 'true'], true);
        $hasResponse = !empty($data['respuesta']);

        if ($enviarMensaje && $hasResponse) {
            Notification::create(
                $ticket['user_id'],
                'Respuesta de Soporte',
                "El administrador ha respondido a tu ticket {$ticket['code']}",
                'ticket_response',
                $ticketId
            );
        } else {
            Notification::create(
                $ticket['user_id'],
                'Ticket Actualizado',
                "Tu ticket {$ticket['code']} ha sido actualizado por el administrador",
                'ticket_updated',
                $ticketId
            );
        }
    }
}
```

---

### 9. `Ticket::getStats()` optimizado

**Problema:** `Ticket::getStats()` (Ticket.php:341-386) ejecuta **5 consultas separadas** a la BD (COUNT para total, abiertos, en_proceso, resueltos, cerrados). Esto es ~5ms de latencia de red por query.

**Archivos afectados:** `app/Models/Ticket.php`

**Solución:** Una sola query con `FILTER` (PostgreSQL 9.4+).

**Feature Flag:** (ninguno — optimización permanente)

**Código — reemplazar `getStats()` (líneas 341-386):**

```php
public static function getStats($filters = []): array
{
    $pdo = self::db();

    $where = "WHERE 1=1";
    $params = [];

    if (!empty($filters['user_id'])) {
        $where .= " AND user_id = :user_id";
        $params['user_id'] = $filters['user_id'];
    }
    if (!empty($filters['assigned_admin_id'])) {
        $where .= " AND assigned_admin_id = :assigned_admin_id";
        $params['assigned_admin_id'] = $filters['assigned_admin_id'];
    }
    if (!empty($filters['location_id'])) {
        $where .= " AND location_id = :location_id";
        $params['location_id'] = $filters['location_id'];
    }

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*)                                         AS total,
            COUNT(*) FILTER (WHERE status_id = 1)            AS abiertos,
            COUNT(*) FILTER (WHERE status_id = 2)            AS en_proceso,
            COUNT(*) FILTER (WHERE status_id = 3)            AS resueltos,
            COUNT(*) FILTER (WHERE status_id = 4)            AS cerrados,
            COUNT(*) FILTER (WHERE status_id = 5)            AS cancelados
        FROM oti.tickets
        {$where}
    ");
    $stmt->execute($params);
    $row = $stmt->fetch();

    return [
        'total' => (int)($row['total'] ?? 0),
        'abiertos' => (int)($row['abiertos'] ?? 0),
        'en_proceso' => (int)($row['en_proceso'] ?? 0),
        'resueltos' => (int)($row['resueltos'] ?? 0),
        'cerrados' => (int)($row['cerrados'] ?? 0),
        'cancelados' => (int)($row['cancelados'] ?? 0),
    ];
}
```

**Verificación:**
```sql
-- Probar la query directamente en PostgreSQL
SELECT COUNT(*) AS total,
       COUNT(*) FILTER (WHERE status_id = 1) AS abiertos,
       COUNT(*) FILTER (WHERE status_id = 2) AS en_proceso
FROM oti.tickets WHERE user_id = 1;
```

---

### 10. Vistas V2 — `app/Views/v2/tickets/`

**Problema:** Las vistas actuales mezclan lógica de presentación con inline CSS y JS. No tienen CSRF token en formularios.

**Archivos afectados:** (nuevos) `app/Views/v2/tickets/tickets.php`, `app/Views/v2/tickets/ticket-detalle.php`, `app/Views/v2/tickets/ticket-form.php`

**Solución:** Vistas limpias con sanitización de salida, CSRF token, y separación de responsabilidades.

**Feature Flag:** `MODULE_TICKETS_V2`

**Código — `app/Views/v2/tickets/tickets.php`:**

```php
<?php
/** @var array $tickets Lista de tickets */
/** @var array $pagination Datos de paginación */
/** @var bool $isUserView Vista de usuario (opcional) */
$baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/OTI/';
$tituloPagina = 'Tickets - Sistema OTI';
$paginaActual = $isUserView ? 'user-tickets' : 'admin-tickets';
?>
<?php require __DIR__ . '/../../Views/partials/head.php'; ?>
<?php require __DIR__ . '/../../Views/partials/sidebar.php'; ?>
<?php require __DIR__ . '/../../Views/partials/header.php'; ?>

<main id="main-content" class="main-content">
    <div class="page-header">
        <h1 class="page-title"><?= $isUserView ? 'Mis Tickets' : 'Gestión de Tickets' ?></h1>
        <?php if ($isUserView): ?>
        <a href="<?= $baseUrl ?>user/reportar" class="btn btn-primary">
            + Nuevo Ticket
        </a>
        <?php endif; ?>
    </div>

    <?php if (empty($tickets)): ?>
    <div class="empty-state">
        <p>No hay tickets registrados.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Título</th>
                    <th>Estado</th>
                    <th>Prioridad</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?= htmlspecialchars($ticket['code'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($ticket['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="status-badge status-<?= strtolower($ticket['status_name'] ?? 'abierto') ?>">
                            <?= htmlspecialchars($ticket['status_name'] ?? 'Abierto', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($ticket['priority_name'] ?? 'Media', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($ticket['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <a href="<?= $baseUrl . ($isUserView ? 'user' : 'admin') ?>/tickets?id=<?= (int)$ticket['id'] ?>"
                           class="btn btn-sm">
                            Ver
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($pagination) && $pagination['totalPages'] > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
        <a href="?page=<?= $i ?>" class="page-link <?= ($pagination['currentPage'] ?? 1) === $i ? 'active' : '' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../../Views/partials/footer.php'; ?>
```

**Código — `app/Views/v2/tickets/ticket-detalle.php`:**

```php
<?php
/** @var array $ticket Datos del ticket */
/** @var array $activities Actividades */
/** @var bool $isAdmin */
/** @var bool $canCancel */
$baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/OTI/';
$tituloPagina = 'Detalle de Ticket - Sistema OTI';
$paginaActual = $isAdmin ? 'admin-tickets' : 'user-detalle';
?>
<?php require __DIR__ . '/../../Views/partials/head.php'; ?>
<?php require __DIR__ . '/../../Views/partials/sidebar.php'; ?>
<?php require __DIR__ . '/../../Views/partials/header.php'; ?>

<main id="main-content" class="main-content">
    <div class="page-header">
        <a href="<?= $baseUrl . ($isAdmin ? 'admin' : 'user') ?>/tickets" class="back-btn">
            &larr; Volver
        </a>
        <h1 class="page-title">Ticket <?= htmlspecialchars($ticket['code'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
    </div>

    <div class="detail-grid">
        <div class="card">
            <h3>Información del Ticket</h3>
            <p><strong>Asunto:</strong> <?= htmlspecialchars($ticket['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Descripción:</strong> <?= nl2br(htmlspecialchars($ticket['description'] ?? '', ENT_QUOTES, 'UTF-8')) ?></p>
            <p><strong>Estado:</strong> <?= htmlspecialchars($ticket['status_name'] ?? 'Abierto', ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Prioridad:</strong> <?= htmlspecialchars($ticket['priority_name'] ?? 'Media', ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Ubicación:</strong> <?= htmlspecialchars($ticket['location_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>

            <?php if ($canCancel): ?>
            <form method="POST" action="<?= $baseUrl ?>user/ticket/<?= (int)$ticket['id'] ?>/cancelar">
                <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                <button type="submit" class="btn btn-danger"
                        onclick="return confirm('¿Cancelar este ticket?')">
                    Cancelar Ticket
                </button>
            </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Actividad</h3>
            <?php if (empty($activities)): ?>
            <p class="text-muted">Sin actividad registrada.</p>
            <?php else: ?>
            <ul class="timeline">
                <?php foreach ($activities as $act): ?>
                <li>
                    <strong><?= htmlspecialchars($act['action'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                    <p><?= htmlspecialchars($act['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                    <small><?= htmlspecialchars($act['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../../Views/partials/footer.php'; ?>
```

**Código — `app/Views/v2/tickets/ticket-form.php`:**

```php
<?php
/** @var bool $isEdit */
/** @var array $ticket */
$baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/OTI/';
$tituloPagina = $isEdit ? 'Editar Ticket' : 'Nuevo Ticket - Sistema OTI';
$paginaActual = 'user-reportar';
?>
<?php require __DIR__ . '/../../Views/partials/head.php'; ?>
<?php require __DIR__ . '/../../Views/partials/sidebar.php'; ?>
<?php require __DIR__ . '/../../Views/partials/header.php'; ?>

<main id="main-content" class="main-content">
    <div class="page-header">
        <h1 class="page-title"><?= $isEdit ? 'Editar Ticket' : 'Reportar Incidente' ?></h1>
    </div>

    <form method="POST" action="<?= $baseUrl ?>user/ticket/crear" class="form">
        <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

        <div class="form-group">
            <label for="title">Título *</label>
            <input type="text" id="title" name="title" required minlength="5" maxlength="200"
                   value="<?= htmlspecialchars($ticket['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label for="description">Descripción *</label>
            <textarea id="description" name="description" required minlength="10"
                      rows="5"><?= htmlspecialchars($ticket['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="service_type_id">Tipo de Servicio</label>
                <select id="service_type_id" name="service_type_id">
                    <option value="">Seleccione...</option>
                </select>
            </div>
            <div class="form-group">
                <label for="location_id">Ubicación</label>
                <select id="location_id" name="location_id">
                    <option value="">Seleccione...</option>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $isEdit ? 'Guardar Cambios' : 'Enviar Ticket' ?>
            </button>
            <a href="<?= $baseUrl ?>user/tickets" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</main>

<?php require __DIR__ . '/../../Views/partials/footer.php'; ?>
```

---

## SPRINT 2: Módulo Usuarios V2 (semana 3)

### 11. `app/Controller/v2/UserController.php` — Manejo de roles real

**Problema:** No hay un controlador de usuarios V2. El API actual (`usuarios.php`) usa consultas directas sin abstracción, y la verificación de permisos se basa en `strpos` sobre nombres de rol.

**Archivos afectados:** (nuevo) `app/Controller/v2/UserController.php`

**Solución:** UserController V2 con CRUD completo, asignación de equipos, y verificación real de permisos usando la columna `es_admin` de la BD.

**Feature Flag:** `MODULE_USERS_V2`

**Código:**

```php
<?php
declare(strict_types=1);

namespace App\Controller\v2;

use App\Controller\BaseController;
use App\Models\User;
use App\Models\Location;
use App\Services\AuthService;

class UserController extends BaseController
{
    private bool $isAdmin;

    public function __construct()
    {
        $this->isAdmin = AuthService::isAdmin();
    }

    public function index(): void
    {
        $this->isAdminOrFail();

        $filters = [
            'location_id' => $_GET['location_id'] ?? null,
            'search' => $_GET['search'] ?? '',
            'activo' => $_GET['activo'] ?? '',
        ];

        $usuarios = User::getAllWithDetails($filters);

        $pdo = \App\Core\Database::connect();
        $stmt = $pdo->query("
            SELECT
                COUNT(*)                                  AS total,
                COUNT(*) FILTER (WHERE u.activo = true)   AS activos,
                COUNT(*) FILTER (WHERE u.activo = false)  AS inactivos
            FROM admin.usuarios u
        ");
        $stats = $stmt->fetch();

        if ($this->isAjax()) {
            $this->json(['success' => true, 'usuarios' => $usuarios, 'stats' => $stats]);
        }

        $this->view('v2/users/users', [
            'usuarios' => $usuarios,
            'stats' => $stats,
        ]);
    }

    public function show(int $id): void
    {
        $this->isAdminOrFail();

        $user = User::findById($id);
        if (!$user) {
            $this->error('Usuario no encontrado', 404);
        }

        $equipos = User::getAssignedEquipment($id);
        $equiposDisponibles = User::getAvailableEquipment($user['location_id'] ?? null);

        if ($this->isAjax()) {
            $this->json([
                'success' => true,
                'user' => $user,
                'equipos' => $equipos ?? [],
                'equipos_disponibles' => $equiposDisponibles ?? [],
            ]);
        }

        $this->view('v2/users/user-detail', [
            'user' => $user,
            'equipos' => $equipos,
            'equiposDisponibles' => $equiposDisponibles,
        ]);
    }

    public function create(): void
    {
        $this->isAdminOrFail();

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->view('v2/users/user-form', [
                'isEdit' => false,
                'user' => [],
                'locations' => Location::getAll(),
            ]);
        }

        $this->verifyCsrf();
        $data = $this->validate($_POST, [
            'nombre' => 'required|min:2|max:100',
            'apellidos' => 'required|min:2|max:100',
            'email' => 'required|email',
            'dni' => 'max:20',
            'phone' => 'max:20',
            'location_id' => 'int',
            'position_id' => 'int',
            'role_id' => 'int',
            'password' => 'min:6',
        ]);

        $data['password'] = $_POST['password'] ?? bin2hex(random_bytes(16));
        $data['activo'] = !isset($_POST['activo']) || $_POST['activo'] !== '0';

        $result = User::createWithProfile($data);
        $this->json($result);
    }

    public function update(int $id): void
    {
        $this->isAdminOrFail();
        $this->verifyCsrf();

        $pdo = \App\Core\Database::connect();
        $pdo->beginTransaction();

        try {
            if (!empty($_POST['email'])) {
                $stmt = $pdo->prepare(
                    "UPDATE admin.usuarios SET email = :email WHERE id = :id"
                );
                $stmt->execute(['email' => $_POST['email'], 'id' => $id]);
            }

            if (!empty($_POST['telefono'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO oti.user_profiles (user_id, phone)
                    VALUES (:user_id, :phone)
                    ON CONFLICT (user_id) DO UPDATE SET phone = :phone
                ");
                $stmt->execute([
                    'user_id' => $id,
                    'phone' => $_POST['telefono'],
                ]);
            }

            $pdo->commit();
            $this->success(null, 'Usuario actualizado');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->error('Error al actualizar usuario: ' . $e->getMessage(), 500);
        }
    }

    public function delete(int $id): void
    {
        $this->isAdminOrFail();
        $this->verifyCsrf();

        $pdo = \App\Core\Database::connect();
        $pdo->prepare("UPDATE admin.usuarios SET activo = false WHERE id = :id")
            ->execute(['id' => $id]);

        $this->success(null, 'Usuario desactivado');
    }

    public function reactivate(int $id): void
    {
        $this->isAdminOrFail();
        $this->verifyCsrf();

        $pdo = \App\Core\Database::connect();
        $pdo->prepare("UPDATE admin.usuarios SET activo = true WHERE id = :id")
            ->execute(['id' => $id]);

        $this->success(null, 'Usuario reactivado');
    }

    public function assignEquipment(int $userId): void
    {
        $this->isAdminOrFail();
        $this->verifyCsrf();

        $equipmentId = (int)($_POST['equipment_id'] ?? 0);
        if (!$equipmentId) {
            $this->error('ID de equipo requerido');
        }

        $result = User::assignEquipment($equipmentId, $userId);
        $this->json(['success' => (bool)$result]);
    }

    public function unassignEquipment(): void
    {
        $this->isAdminOrFail();
        $this->verifyCsrf();

        $equipmentId = (int)($_POST['equipment_id'] ?? 0);
        if (!$equipmentId) {
            $this->error('ID de equipo requerido');
        }

        $result = User::unassignEquipment($equipmentId);
        $this->json(['success' => (bool)$result]);
    }

    public function locations(): void
    {
        $this->isAdminOrFail();
        $locations = Location::getAll();
        $hierarchy = User::getLocationsHierarchy();
        $this->json(['success' => true, 'locations' => $locations, 'hierarchy' => $hierarchy]);
    }

    private function isAdminOrFail(): void
    {
        if (!$this->isAdmin) {
            $this->error('Acceso denegado', 403);
        }
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            || !empty($_GET['ajax']);
    }
}
```

### 12. Fix bug User.php:793 — `telefono` vs `:phone`

**Problema:** En `User.php:793` el placeholder `:phone` se usa en la query pero la clave del array `execute()` es `'telefono'` en lugar de `'phone'`. Esto causa un error PDO porque el placeholder `:phone` nunca se llena, y `:telefono` se pasa pero no se usa.

**Archivos afectados:** `app/Models/User.php` (línea 793)

**Solución:** Reemplazar la clave del array para que coincida con el placeholder.

**Feature Flag:** (ninguno — es un bug fix)

**Código — línea 793 actual:**

```php
$stmt->execute(['user_id' => $userId, 'telefono' => $data['telefono']]);
```

**Código — reemplazar con:**

```php
$stmt->execute(['user_id' => $userId, 'phone' => $data['telefono']]);
```

**Verificación:**
1. Editar perfil de usuario con un teléfono
2. Antes del fix: error PDO "Missing parameter :phone"
3. Después del fix: el teléfono se guarda correctamente

---

### 13. Vista V2 de usuarios — `app/Views/v2/users/users.php`

**Código:**

```php
<?php
/** @var array $usuarios */
/** @var array $stats */
$baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/OTI/';
$tituloPagina = 'Usuarios - Sistema OTI';
$paginaActual = 'admin-usuarios';
?>
<?php require __DIR__ . '/../../Views/partials/head.php'; ?>
<?php require __DIR__ . '/../../Views/partials/sidebar.php'; ?>
<?php require __DIR__ . '/../../Views/partials/header.php'; ?>

<main id="main-content" class="main-content">
    <div class="page-header">
        <h1 class="page-title">Gestión de Usuarios</h1>
        <button class="btn btn-primary" onclick="location.href='<?= $baseUrl ?>admin/usuarios/crear'">
            + Nuevo Usuario
        </button>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <span class="stat-value"><?= (int)($stats['total'] ?? 0) ?></span>
            <span class="stat-label">Total</span>
        </div>
        <div class="stat-card stat-success">
            <span class="stat-value"><?= (int)($stats['activos'] ?? 0) ?></span>
            <span class="stat-label">Activos</span>
        </div>
        <div class="stat-card stat-danger">
            <span class="stat-value"><?= (int)($stats['inactivos'] ?? 0) ?></span>
            <span class="stat-label">Inactivos</span>
        </div>
    </div>

    <?php if (empty($usuarios)): ?>
    <div class="empty-state"><p>No hay usuarios registrados.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= htmlspecialchars(($u['nombre'] ?? '') . ' ' . ($u['apellidos'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($u['role_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge badge-<?= !empty($u['activo']) ? 'success' : 'secondary' ?>">
                            <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?= $baseUrl ?>admin/usuarios/<?= (int)$u['id'] ?>"
                           class="btn btn-sm">Ver</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../../Views/partials/footer.php'; ?>
```

---

## SPRINT 3: Módulo Equipos y Estructura V2 (semana 4)

### 14. `app/Controller/v2/EquipmentController.php` + `LocationController.php`

**Problema:** No hay controladores V2 para equipos ni ubicaciones. La lógica está en APIs planas sin abstracción.

**Archivos afectados:** (nuevos) `app/Controller/v2/EquipmentController.php`, `app/Controller/v2/LocationController.php`

**Solución:** Controladores V2 con CRUD completo, prepared statements y validación centralizada.

**Feature Flag:** `MODULE_EQUIPMENT_V2`

**Código — `EquipmentController.php`:**

```php
<?php
declare(strict_types=1);

namespace App\Controller\v2;

use App\Controller\BaseController;
use App\Models\Equipment;
use App\Services\AuthService;

class EquipmentController extends BaseController
{
    private bool $isAdmin;

    public function __construct()
    {
        $this->isAdmin = AuthService::isAdmin();
    }

    public function index(): void
    {
        $this->isAdminOrFail();

        $filters = [];
        foreach (['status', 'asset_type', 'location_id', 'assigned_user_id', 'search'] as $key) {
            if (!empty($_GET[$key])) {
                $filters[$key] = $_GET[$key];
            }
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($_GET['pageSize'] ?? 20)));

        if ($this->isAjax()) {
            $this->json([
                'success' => true,
                'equipos' => Equipment::getAll($filters, $page, $pageSize),
            ]);
        }

        $this->view('v2/equipment/equipment', [
            'equipos' => Equipment::getAll($filters, $page, $pageSize),
            'stats' => Equipment::getStats(),
            'assetTypes' => Equipment::getAssetTypes(),
            'statuses' => Equipment::getStatuses(),
        ]);
    }

    public function show(int $id): void
    {
        $this->isAdminOrFail();
        $equipo = Equipment::findById($id);

        if (!$equipo) {
            $this->error('Equipo no encontrado', 404);
        }

        $this->json(['success' => true, 'equipo' => $equipo]);
    }

    public function assign(int $id): void
    {
        $this->isAdminOrFail();
        $this->verifyCsrf();

        $userId = (int)($_POST['user_id'] ?? 0);
        $userName = $_POST['user_name'] ?? '';

        if (!$userId || !$userName) {
            $this->error('Parámetros requeridos');
        }

        $result = Equipment::assignToUser($id, $userId, $userName);
        $this->json(['success' => $result]);
    }

    public function updateLocation(int $id): void
    {
        $this->isAdminOrFail();
        $this->verifyCsrf();

        $locationId = (int)($_POST['location_id'] ?? 0);
        if (!$locationId) {
            $this->error('Ubicación requerida');
        }

        $result = Equipment::updateLocation($id, $locationId);
        $this->json(['success' => $result]);
    }

    public function updateStatus(int $id): void
    {
        $this->isAdminOrFail();
        $this->verifyCsrf();

        $status = $_POST['status'] ?? '';
        if (!in_array($status, Equipment::getStatuses(), true)) {
            $this->error('Estado no válido');
        }

        $result = Equipment::updateStatus($id, $status);
        $this->json(['success' => $result]);
    }

    private function isAdminOrFail(): void
    {
        if (!$this->isAdmin) {
            $this->error('Acceso denegado', 403);
        }
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            || !empty($_GET['ajax']);
    }
}
```

**Código — `LocationController.php`:**

```php
<?php
declare(strict_types=1);

namespace App\Controller\v2;

use App\Controller\BaseController;
use App\Models\Location;
use App\Services\AuthService;

class LocationController extends BaseController
{
    private bool $isAdmin;

    public function __construct()
    {
        $this->isAdmin = AuthService::isAdmin();
    }

    public function index(): void
    {
        $this->isAdminOrFail();
        $locations = Location::getAll();

        if ($this->isAjax()) {
            $this->json(['success' => true, 'locations' => $locations]);
        }

        $this->view('v2/locations/locations', [
            'locations' => $locations,
            'stats' => Location::getStats(),
        ]);
    }

    public function tree(): void
    {
        $this->isAdminOrFail();
        $tree = Location::getTree();
        $this->json(['success' => true, 'tree' => $tree]);
    }

    public function show(int $id): void
    {
        $this->isAdminOrFail();
        $location = Location::getById($id);

        if (empty($location)) {
            $this->error('Ubicación no encontrada', 404);
        }

        $children = Location::getChildren($id);
        $users = Location::getUsers($id);
        $equipment = Location::getEquipment($id);

        $this->json([
            'success' => true,
            'location' => $location,
            'children' => $children,
            'users' => $users,
            'equipment' => $equipment,
        ]);
    }

    public function stats(): void
    {
        $this->isAdminOrFail();
        $this->json(['success' => true, 'stats' => Location::getStats()]);
    }

    private function isAdminOrFail(): void
    {
        if (!$this->isAdmin) {
            $this->error('Acceso denegado', 403);
        }
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            || !empty($_GET['ajax']);
    }
}
```

---

### 15. N+1 queries optimizadas

**Problema:** `Location::getStats()` ejecuta 6 queries separadas. `Equipment::getStats()` ejecuta 5 queries separadas. Cada una hace un round-trip a PostgreSQL.

**Archivos afectados:** `app/Models/Location.php`, `app/Models/Equipment.php`

**Solución:** Una sola query con `FILTER` y `COUNT` agrupado.

**Feature Flag:** (ninguno — optimización)

**Código — `Location::getStats()` optimizado (reemplazar líneas 184-214):**

```php
public static function getStats(): array
{
    $pdo = self::db();
    $stmt = $pdo->query("
        SELECT
            COUNT(*)                                                   AS total,
            COUNT(*) FILTER (WHERE type = 'DIRECCION' AND active)     AS direcciones,
            COUNT(*) FILTER (WHERE type = 'AREA' AND active)          AS areas,
            COUNT(*) FILTER (WHERE type = 'OFICINA' AND active)       AS oficinas,
            (SELECT COUNT(*) FROM oti.user_profiles WHERE location_id IS NOT NULL) AS usuarios_asignados,
            (SELECT COUNT(*) FROM oti.equipment WHERE location_id IS NOT NULL AND is_deleted = false) AS equipos_asignados
        FROM oti.locations
        WHERE active = true
    ");
    $row = $stmt->fetch();

    return [
        'total' => (int)($row['total'] ?? 0),
        'direcciones' => (int)($row['direcciones'] ?? 0),
        'areas' => (int)($row['areas'] ?? 0),
        'oficinas' => (int)($row['oficinas'] ?? 0),
        'usuarios_asignados' => (int)($row['usuarios_asignados'] ?? 0),
        'equipos_asignados' => (int)($row['equipos_asignados'] ?? 0),
    ];
}
```

**Código — `Equipment::getStats()` optimizado (reemplazar líneas 95-117):**

```php
public static function getStats()
{
    $pdo = self::db();
    $stmt = $pdo->query("
        SELECT
            COUNT(*)                                              AS total,
            COUNT(*) FILTER (WHERE status = 'active')             AS activos,
            COUNT(*) FILTER (WHERE status = 'maintenance')        AS mantenimiento,
            COUNT(*) FILTER (WHERE status = 'inactive')           AS inactivos,
            COUNT(*) FILTER (WHERE status = 'retired')            AS retirados
        FROM oti.equipment
        WHERE is_deleted = false
    ");
    $row = $stmt->fetch();

    return [
        'total' => (int)($row['total'] ?? 0),
        'activos' => (int)($row['activos'] ?? 0),
        'mantenimiento' => (int)($row['mantenimiento'] ?? 0),
        'inactivos' => (int)($row['inactivos'] ?? 0),
        'retirados' => (int)($row['retirados'] ?? 0),
    ];
}
```

---

### 16. Índices compuestos SQL

**Problema:** Las consultas más frecuentes (`getAll` con filtros, `getStats`) no están respaldadas por índices, lo que causará degradación a medida que crezcan los datos.

**Archivos afectados:** (script SQL) `.specs/research/indices.sql` — para aplicar en BD

**Solución:** Índices compuestos para los patrones de acceso más comunes.

**Código SQL:**

```sql
-- ==========================================
-- Índices para oti.tickets
-- ==========================================

-- Búsqueda principal: listado de tickets con filtros
CREATE INDEX IF NOT EXISTS idx_tickets_user_status
    ON oti.tickets (user_id, status_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_tickets_admin_status
    ON oti.tickets (assigned_admin_id, status_id, created_at DESC);

-- Filtro por rango de fechas (stats, dashboard)
CREATE INDEX IF NOT EXISTS idx_tickets_created_at
    ON oti.tickets (created_at DESC);

-- Búsqueda por código o título (search)
CREATE INDEX IF NOT EXISTS idx_tickets_code_title
    ON oti.tickets (code, title);

-- Text search con ILIKE (requiere trgm para rendimiento)
CREATE INDEX IF NOT EXISTS idx_tickets_title_trgm
    ON oti.tickets USING gin (title gin_trgm_ops);

CREATE INDEX IF NOT EXISTS idx_tickets_code_trgm
    ON oti.tickets USING gin (code gin_trgm_ops);

-- ==========================================
-- Índices para admin.usuarios
-- ==========================================

CREATE INDEX IF NOT EXISTS idx_usuarios_activo
    ON admin.usuarios (activo, nombre);

CREATE INDEX IF NOT EXISTS idx_usuarios_email
    ON admin.usuarios (email);

-- ==========================================
-- Índices para oti.equipment
-- ==========================================

CREATE INDEX IF NOT EXISTS idx_equipment_status
    ON oti.equipment (status, is_deleted);

CREATE INDEX IF NOT EXISTS idx_equipment_location
    ON oti.equipment (location_id, is_deleted);

CREATE INDEX IF NOT EXISTS idx_equipment_assigned_user
    ON oti.equipment (assigned_user_id, is_deleted);

-- ==========================================
-- Índices para oti.locations
-- ==========================================

CREATE INDEX IF NOT EXISTS idx_locations_parent
    ON oti.locations (parent_id);

CREATE INDEX IF NOT EXISTS idx_locations_type_active
    ON oti.locations (type, active);

-- ==========================================
-- Extensión requerida para búsqueda de texto
-- ==========================================
CREATE EXTENSION IF NOT EXISTS pg_trgm;
```

**Verificación:**
```sql
EXPLAIN ANALYZE SELECT * FROM oti.tickets WHERE user_id = 1 AND status_id = 1 ORDER BY created_at DESC;
-- Debe mostrar "Index Scan using idx_tickets_user_status"
```

---

## SPRINT 4: JS/CSS y pulido final (semana 5)

### 17. Fix SSE en `realtime.js`

**Problemas identificados:**

1. **Línea 49:** Operador `||` vs `??` — `if (useSSE && currentPage === 'admin-dashboard' || currentPage === 'user-dashboard')` se evalúa como `if ((useSSE && currentPage === 'admin-dashboard') || currentPage === 'user-dashboard')` debido a precedencia de operadores. Cuando `currentPage === 'user-dashboard'` siempre es verdadero independientemente de `useSSE`.

2. **Línea 96:** `JSON.parse(event.data)` en el handler de evento `error` — el evento `error` de SSE no siempre tiene datos JSON válidos (a veces es nulo). Causa crash.

3. **Línea 97:** `console.error('SSE error:', data)` — muestra datos sensibles en consola del navegador en producción.

4. **Múltiples `console.log`/`console.warn`** que exponen información interna.

**Archivos afectados:** `public/assets/js/realtime.js`

**Solución:** Corregir precedencia, manejar error SSE sin `JSON.parse`, eliminar `console.*` en producción.

**Feature Flag:** (ninguno — bug fix)

**Código — reemplazar bloque SSE (líneas 49-113):**

```javascript
// Línea 49: corregir precedencia con paréntesis
if (useSSE && (currentPage === 'admin-dashboard' || currentPage === 'user-dashboard')) {
    initSSE();
} else {
    fetchAllData();
    updateInterval = setInterval(fetchAllData, 15000);
}

// ...

function initSSE() {
    try {
        eventSource = new EventSource(BASE_URL + 'app/api/sse.php');

        eventSource.onopen = function() {
            if (updateInterval) {
                clearInterval(updateInterval);
                updateInterval = null;
            }
        };

        eventSource.onmessage = function(event) {
            try {
                var data = JSON.parse(event.data);
                handleDataUpdate(data);
            } catch (e) {
                // Silently ignore malformed data
            }
        };

        eventSource.addEventListener('update', function(event) {
            try {
                var data = JSON.parse(event.data);
                handleDataUpdate(data);
            } catch (e) {
                // Silently ignore
            }
        });

        eventSource.addEventListener('connected', function(event) {
            try {
                JSON.parse(event.data);
            } catch (e) {
                // Silently ignore
            }
        });

        eventSource.addEventListener('error', function() {
            // El evento 'error' de SSE no tiene datos JSON parseables
            // Simplemente hacer fallback a polling
            closeSSE();
            useSSE = false;
            fetchAllData();
            updateInterval = setInterval(fetchAllData, 15000);
        });

        eventSource.onerror = function() {
            closeSSE();
            useSSE = false;
            fetchAllData();
            updateInterval = setInterval(fetchAllData, 15000);
        };

    } catch (e) {
        useSSE = false;
        fetchAllData();
        updateInterval = setInterval(fetchAllData, 15000);
    }
}
```

**Verificación:**
1. Abrir página con SSE (dashboard)
2. Forzar evento error SSE (matar proceso PHP)
3. Antes del fix: crash con "JSON.parse: unexpected character"
4. Después del fix: fallback silencioso a polling

---

### 18. Fix Canvas en `analisis-charts.js:108`

**Problema:** En `analisis-charts.js:108`, `const ctx = document.getElementById('chart-tickets-mensual');` obtiene el elemento canvas. Luego en `initTicketsMensualChart()`, se pasa `ctx` a `createGradient(ctx, ...)` que espera un canvas context 2D, no un elemento DOM. El método `ctx.createLinearGradient` no existe en un HTMLElement. Lo mismo aplica para `createBarGradient` en líneas 192, 242, etc.

El código de `createGradient` (línea 487) intenta acceder a `ctx.width` y `ctx.height`, que en un canvas element son propiedades (canvas.width, canvas.height), no en un context 2D.

**Archivos afectados:** `public/assets/js/analisis-charts.js`

**Solución:** Obtener el `CanvasRenderingContext2D` con `canvas.getContext('2d')` antes de pasarlo a las funciones gradient.

**Feature Flag:** (ninguno — bug fix)

**Código — reemplazar `initTicketsMensualChart()` (líneas 107-157):**

```javascript
function initTicketsMensualChart(data) {
    var canvas = document.getElementById('chart-tickets-mensual');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');

    var labels = data.map(function(d) { return formatMonth(d.mes); });
    var values = data.map(function(d) { return parseInt(d.count); });

    var bgColor = createGradient(ctx, CHART_COLORS.primaryLight, CHART_COLORS.primary);

    charts.ticketsMensual = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Tickets',
                data: values,
                borderColor: CHART_COLORS.primary,
                backgroundColor: bgColor,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: CHART_COLORS.primary,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointHoverRadius: 6
            }]
        },
        options: {
            ...chartDefaults,
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } }
            }
        }
    });
}
```

**Código — corregir `createGradient` (líneas 486-498):**

```javascript
function createGradient(ctx, colorStart, colorEnd) {
    try {
        if (!ctx || !ctx.canvas || ctx.canvas.width === 0 || ctx.canvas.height === 0) {
            return colorStart + '40';
        }
        var gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, colorStart + '40');
        gradient.addColorStop(1, colorEnd + '10');
        return gradient;
    } catch (e) {
        return colorStart + '40';
    }
}
```

**Código — corregir `createBarGradient` (líneas 500-512):**

```javascript
function createBarGradient(ctx, color) {
    try {
        if (!ctx || !ctx.canvas || ctx.canvas.width === 0 || ctx.canvas.height === 0) {
            return color;
        }
        var gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, color);
        gradient.addColorStop(1, color + '80');
        return gradient;
    } catch (e) {
        return color;
    }
}
```

**Aplicar mismo patrón a todas las funciones init*Chart que usan createBarGradient:**

```javascript
// En initUbicacionesChart, initUsuariosChart, etc. reemplazar:
var ctx = document.getElementById('chart-...');
// con:
var canvas = document.getElementById('chart-...');
if (!canvas) return;
var ctx = canvas.getContext('2d');
```

**Verificación:**
1. Navegar a `/admin/analisis`
2. Antes del fix: error en consola "ctx.createLinearGradient is not a function"
3. Después del fix: gráficos se renderizan correctamente

---

### 19. División de `app.css` + Security Headers en `.htaccess`

**Problema:** `app.css` es un archivo monolítico (probablemente >2000 líneas). Los security headers están duplicados entre `index.php` (líneas 8-12), `Security.php` (líneas 22-38) y `.htaccess` (líneas 64-68).

**Archivos afectados:** `.htaccess`, `public/assets/css/` (nuevos archivos)

**Solución:**
1. Dividir `app.css` en módulos: `base.css`, `components.css`, `utilities.css`
2. Consolidar security headers en `.htaccess`
3. Eliminar headers duplicados de `index.php`

**Feature Flag:** (ninguno — refactor)

**Código — `.htaccess` (añadir security headers al final):**

```apache
# ========== SECURITY HEADERS ==========
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), camera=(), microphone=()"
    
    # CSP - Content Security Policy (ajustar según necesidad)
    # Header always set Content-Security-Policy "default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; base-uri 'self'; form-action 'self'; object-src 'none'"
</IfModule>
```

**Código — `index.php` (eliminar líneas 8-12):**

```php
// Eliminar estas líneas de index.php (ahora están en .htaccess):
// header('X-Content-Type-Options: nosniff');
// header('X-Frame-Options: SAMEORIGIN');
// header('X-XSS-Protection: 1; mode=block');
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
// header('Cache-Control: private, no-cache, must-revalidate, max-age=0');
```

**Referencia de división CSS (archivos a crear):**

| Archivo | Contenido |
|---------|-----------|
| `public/assets/css/base.css` | Reset, variables CSS, tipografía, layout grid |
| `public/assets/css/components.css` | Cards, tablas, botones, formularios, badges, navbar, sidebar, modales |
| `public/assets/css/utilities.css` | Clases helper, animaciones, responsive utilities |

En `head.php`, reemplazar:
```php
<link rel="stylesheet" href="<?= $baseUrl ?>public/assets/css/app.css">
```
con:
```php
<link rel="stylesheet" href="<?= $baseUrl ?>public/assets/css/base.css">
<link rel="stylesheet" href="<?= $baseUrl ?>public/assets/css/components.css">
<link rel="stylesheet" href="<?= $baseUrl ?>public/assets/css/utilities.css">
```

**Verificación:**
1. Verificar que todos los security headers se envían via `.htaccess`
2. Verificar que la página se ve igual con CSS dividido
3. Usar `curl -I http://localhost/OTI/` para inspeccionar headers

---

### 20. BASE_URL dinámica

**Problema:** `define('BASE_URL', 'http://localhost/OTI/')` en `index.php:16` está hardcodeado. En producción, la URL será diferente. También hay referencias a `/OTI/` hardcodeadas en JS (`window.location.origin + '/OTI/'`) y en `.htaccess` (`RewriteBase /OTI/`).

**Archivos afectados:** `index.php`, `.htaccess`, `public/assets/js/realtime.js`, `public/assets/js/analisis-charts.js`, `public/assets/js/search.js`, `app/Views/user/ticket-detalle.php`

**Solución:** Leer BASE_URL de `.env` y propagar a JS mediante una variable global.

**Feature Flag:** (ninguno — configuración)

**Código — `index.php` línea 16:**

```php
$baseUrlFromEnv = rtrim(getenv('APP_URL') ?: 'http://localhost/OTI', '/') . '/';
define('BASE_URL', $baseUrlFromEnv);
```

**Código — añadir en `app/Views/partials/head.php`:**

```html
<script>
window.BASE_URL = '<?= BASE_URL ?>';
</script>
```

**Código — `realtime.js` (reemplazar línea 9):**

```javascript
var BASE_URL = window.BASE_URL || window.location.origin + '/OTI/';
```

**Código — `analisis-charts.js` (reemplazar línea 9):**

```javascript
var BASE_URL = window.BASE_URL || window.location.origin + '/OTI/';
```

**Código — `search.js` (reemplazar línea 9):**

```javascript
var BASE_URL = window.BASE_URL || window.location.origin + '/OTI/';
```

**Código — `app/Views/user/ticket-detalle.php` (línea 342):**

```javascript
var BASE_URL = window.BASE_URL || window.location.origin + '/OTI/';
```

**Código — `.htaccess` (línea 2):**

```apache
RewriteBase /
```

Luego ajustar todas las reglas para que no incluyan `/OTI/`:

```apache
RewriteEngine On

# Allow direct access to API files
RewriteCond %{REQUEST_URI} ^/app/api/ [NC]
RewriteRule ^ - [L]

# Allow direct access to public assets
RewriteCond %{REQUEST_URI} ^/public/ [NC]
RewriteRule ^ - [L]

# Allow direct access to SSE endpoint
RewriteCond %{REQUEST_URI} ^/app/api/sse\.php [NC]
RewriteRule ^ - [L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L,QSA]
```

**Verificación:**
1. Cambiar `APP_URL` en `.env` a `http://localhost/OTI` (o `http://midominio.gob.pe`)
2. Todas las URLs generadas deben usar ese valor
3. JS debe usar `window.BASE_URL` correctamente

---

## Resumen de Feature Flags

| Flag | Default | Controla | Sprint |
|------|---------|----------|--------|
| `MODULE_TICKETS_V2` | `false` | TicketController V2 + vistas V2 | Sprint 1 |
| `MODULE_USERS_V2` | `false` | UserController V2 + vistas V2 | Sprint 2 |
| `MODULE_EQUIPMENT_V2` | `false` | EquipmentController + LocationController V2 | Sprint 3 |
| `MODULE_CSRF_STRICT` | `false` | CSRF check obligatorio en todos los POST | Sprint 0 |
| `MODULE_SEARCH_V2` | `false` | Búsqueda global mejorada | (futuro) |

Para activar un módulo, añadir en `.env`:
```
MODULE_TICKETS_V2=true
MODULE_CSRF_STRICT=true
```

## Diagrama de arquitectura V2

```
index.php
├── FeatureFlag::isActive('TICKETS_V2') ? TicketControllerV2 : ruta V1
├── FeatureFlag::isActive('USERS_V2') ? UserControllerV2 : ruta V1
├── FeatureFlag::isActive('EQUIPMENT_V2') ? EquipmentControllerV2 : ruta V1
└── FeatureFlag::isActive('CSRF_STRICT') ? verify_csrf() para POST : sin check

Controladores V2 (extienden BaseController)
├── BaseController
│   ├── json(), error(), success()
│   ├── view(), redirect()
│   ├── validate(data, rules)
│   ├── csrf(), verifyCsrf()
│   └── isFeatureActive(flag)
├── v2/TicketController
├── v2/UserController
├── v2/EquipmentController
└── v2/LocationController

Fallback automático: try/catch en cada handler V2 → redirect a V1
```

## Pipeline de despliegue recomendado

```
Sprint 0 (días 1-3)  → Parches seguridad + FeatureFlag + BaseController + Fallback
Sprint 1 (días 4-10)  → Tickets V2 (flag=false por defecto, activar en testing)
Sprint 2 (días 11-17) → Usuarios V2 (flag=false por defecto)
Sprint 3 (días 18-24) → Equipos V2 + índices (flag=false por defecto)
Sprint 4 (días 25-30) → Fixes JS/CSS + BASE_URL dinámica + .htaccess

En cada sprint:
1. Desarrollar con flag=false
2. Activar flag en staging para QA
3. Si todo OK, activar en producción
4. Si algo falla, desactivar flag = rollback instantáneo
```

## Criterios de aceptación generales

- [ ] Cada módulo V2 tiene su feature flag, desactivado por defecto
- [ ] Activar un flag no rompe ninguna funcionalidad V1 existente
- [ ] Si un módulo V2 lanza excepción, el sistema redirige a V1 automáticamente
- [ ] Todos los formularios POST tienen CSRF (cuando `MODULE_CSRF_STRICT=true`)
- [ ] No hay `strpos` para determinar roles en ningún lado
- [ ] `Database.php` no tiene passwords hardcodeados
- [ ] `getStats()` en Ticket, Location, Equipment usan una sola query con FILTER
- [ ] SSE maneja errores sin crashear
- [ ] Chart.js recibe context 2D correcto
- [ ] Security headers consolidados en `.htaccess`
- [ ] `BASE_URL` dinámica desde `.env`
- [ ] `User.php:793` bug: `:phone` → `'phone'` corregido
