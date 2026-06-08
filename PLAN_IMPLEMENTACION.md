# PLAN DE IMPLEMENTACIÓN — OTI a Producción

**Versión:** 1.0
**Fecha:** 2026-05-21
**Cobertura:** ~125/147 issues (85%)
**Esfuerzo total estimado:** 6 semanas (~55h hombre)

---

## Resumen Ejecutivo

Este plan integra lo mejor de 3 soluciones evaluadas (A-E1 Cirugía de Seguridad: 6.68, B-E1 Hardening Dirigido: 6.67, C-E3 Feature Flags: 7.43) en un solo plan ejecutable. La estrategia central usa **Feature Flags** con fallback automático a V1 (de C-E3, la solución mejor evaluada) como columna vertebral del rollout, combinado con **parches quirúrgicos inmediatos** de A-E1 para vulnerabilidades críticas, **session hardening + rate limiting** de B-E1, y **refactor progresivo a módulos V2** de C-E3. Esto permite desplegar valor en días 1-3 mientras se construye la arquitectura modular a largo plazo.

---

## Arquitectura de la Solución Integrada

```
index.php
├── Security Middleware Unificado (session hardening + CSP + timeout)
│   └── B-E1 (session) + A-E1 (CSRF) + mitigación: se reemplaza addEventListener
│       de error por solo onerror (A-E1 item 12)
├── FeatureFlag::isActive('TICKETS_V2') ? TicketControllerV2 : ruta V1
├── FeatureFlag::isActive('USERS_V2') ? UserControllerV2 : ruta V1
├── FeatureFlag::isActive('EQUIPMENT_V2') ? EquipmentControllerV2 : ruta V1
└── FeatureFlag::isActive('CSRF_STRICT') ? verify_csrf() para POST : sin check

Controladores V2 (extienden BaseController)
└── BaseController (C-E3)
    ├── json(), error(), success() ← unifica JsonResponse (A-E1) con éxito (C-E3)
    ├── view(), redirect(), validate(data, rules)
    ├── csrf(), verifyCsrf()
    └── isFeatureActive(flag)

Fallback automático V2→V1: runV2WithFallback() (C-E3)
Parches quirúrgicos V1: CSRF, CORS, XSS, SET NAMES, User.php bug (A-E1)
Session + rate limiting: index.php hardening, AuthController login (B-E1)
```

**Criterios de selección entre soluciones:**
- **CSRF, XSS, SQLi, Database.php, CORS:** Se usa **A-E1** (código más completo y quirúrgico, elimina error disclosure en todas las APIs, incluye helper Cors y JsonResponse)
- **Session hardening, rate limiting, CSS, .htaccess, BASE_URL:** Se usa **B-E1** (única solución que los cubre con middleware unificado)
- **Feature flags, BaseController, módulos V2, fallback:** Se usa **C-E3** (única solución que los tiene, mejor evaluada en risk management)
- **AuthService::isAdmin():** Se usa **C-E3** (consulta BD con flag `es_admin` + join a roles, más robusta que A-E1/B-E1)
- **SSE + Canvas fixes:** Se usa **A-E1** (código más completo, elimina addEventListener de error, corrige createGradient con canvas.getContext)
- **N+1 queries:** Se usa **C-E3** (incluye status_id=5 cancelados que A-E1 y B-E1 omiten)

---

## SPRINT 0: EMERGENCIA (Días 1-3, ~10h)

> Parches de seguridad críticos + Feature Flag System como base transversal.
> Base: A-E1 items 1-9 + B-E1 Fase 1 items 2b + C-E3 FeatureFlag

### 0.1 Feature Flag System (base transversal)

**Archivo nuevo:** `app/Helpers/FeatureFlag.php`

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

**Verificación:** `php -r "require 'vendor/autoload.php'; echo \App\Helpers\FeatureFlag::isActive('TICKETS_V2') ? 'on' : 'off';"`

---

### 0.2 CSRF en todos los endpoints POST

**Fuente:** A-E1 item 1 (completo, con soporte API + HTML) + C-E3 item 4 (integración con FeatureFlag)

#### index.php — Middleware CSRF (agregar después de `session_start()`)

```php
// ============================================================
// MIDDLEWARE CSRF — Protección contra Cross-Site Request Forgery
// ============================================================
require_once __DIR__ . '/app/Helpers/functions.php';

if (FeatureFlag::isActive('CSRF_STRICT') && $requestMethod === 'POST') {
    $token = $_POST['_csrf_token'] ?? '';
    if (empty($token)) {
        $headers = getallheaders();
        $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
    }
    if (!verify_csrf($token)) {
        if (strpos($path, '/api/') === 0 || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json');
            http_response_code(419);
            echo json_encode(['error' => 'CSRF token inválido o expirado. Recarga la página e intenta de nuevo.']);
            exit;
        }
        $_SESSION['error'] = 'Error de seguridad: token inválido. Por favor recarga la página.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL));
        exit;
    }
}
```

**Requiere:** agregar `use App\Helpers\FeatureFlag;` al inicio de `index.php`.

#### functions.php — CSRF token con expiración (A-E1)

Ya existe en `app/Helpers/functions.php` con las funciones `csrf_token()`, `verify_csrf()`, `csrf_field()`. Verificar que estén presentes. Si no existe `csrf_field()`, agregar:

```php
if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
    }
}
```

#### En cada formulario POST de las vistas

Agregar al inicio de cada `<form method="POST"`:

```php
<?= csrf_field() ?>
```

Archivos afectados (buscar `<form` con `method="POST"`):
- `app/Views/auth/login.php`
- `app/Views/user/reportar.php`
- `app/Views/user/ticket-detalle.php` (línea 526)
- `app/Views/user/profile.php`
- `app/Views/partials/head.php` (si tiene formularios)

#### En APIs POST (tickets.php, usuarios.php)

Agregar al inicio después del `require_once`:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && FeatureFlag::isActive('CSRF_STRICT')) {
    $headers = getallheaders();
    $token = $_POST['_csrf_token'] ?? $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
    if (!verify_csrf($token)) {
        http_response_code(419);
        echo json_encode(['error' => 'CSRF token inválido']);
        exit;
    }
}
```

**Verificación:** Hacer POST sin token CSRF → 419. Formularios deben incluir `_csrf_token` no vacío.

---

### 0.3 Eliminar credenciales hardcodeadas

#### Database.php (A-E1 item 2 — más completo, mejora loadEnv para que falle si no hay .env)

Reemplazar `app/Core/Database.php` completo:

```php
<?php
declare(strict_types=1);

namespace App\Core;

class Database
{
    private static ?\PDO $pdo = null;

    public static function connect(): \PDO
    {
        if (self::$pdo === null) {
            self::loadEnv();

            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '5432';
            $db_name = getenv('DB_DATABASE') ?: 'sistema_soporte';
            $username = getenv('DB_USERNAME') ?: 'postgres';
            $password = getenv('DB_PASSWORD');

            if (empty($password)) {
                throw new \RuntimeException(
                    'DB_PASSWORD no está definida en el archivo .env. ' .
                    'Crea o actualiza el archivo .env con una contraseña segura.'
                );
            }

            try {
                $dsn = "pgsql:host=$host;port=$port;dbname=$db_name";

                self::$pdo = new \PDO($dsn, $username, $password, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_TIMEOUT => 5
                ]);

                self::$pdo->exec("SET client_encoding TO 'UTF8'");
                self::$pdo->exec("SET search_path TO oti, admin");

            } catch (\PDOException $e) {
                error_log('Database connection error: ' . $e->getMessage());
                die('Error de conexión a la base de datos');
            }
        }

        return self::$pdo;
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    private static function loadEnv(): void
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (!file_exists($envFile)) {
            throw new \RuntimeException(
                'Archivo .env no encontrado en ' . $envFile . '. ' .
                'Copia .env.example a .env y configura las credenciales.'
            );
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}
```

#### usuarios.php — Quitar contraseña por defecto OTI2026 (B-E1 item 4b)

Reemplazar en `app/api/usuarios.php` línea 76:

```php
// Antes:
'password' => $_POST['password'] ?? 'OTI' . date('Y'),

// Después:
'password' => $_POST['password'] ?? bin2hex(random_bytes(16)),
```

**Verificación:** Sin `.env` → error claro. Con `.env` sin DB_PASSWORD → excepción. Crear usuario sin contraseña → rechazar.

---

### 0.4 Auth por rol real (no strpos)

**Fuente:** C-E3 item 5 (mejor consulta BD que verifica es_admin + join a roles)

#### app/Services/AuthService.php — isAdmin() real (C-E3 con static cache fix)

Reemplazar el método `isAdmin()` en `app/Services/AuthService.php`:

```php
public static function isAdmin(): bool
{
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
        return false;
    }

    // Cache en sesión (no static, para evitar problemas en PHP-FPM)
    if (isset($_SESSION['_is_admin_checked'])) {
        return $_SESSION['_is_admin_checked'];
    }

    // Verificar flag directo
    if (!empty($_SESSION['user']['es_admin'])) {
        $_SESSION['_is_admin_checked'] = true;
        return true;
    }

    try {
        $pdo = \App\Core\Database::connect();
        // Verificar en BD
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
            $_SESSION['_is_admin_checked'] = true;
            return true;
        }

        // Verificar por rol
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
            $_SESSION['_is_admin_checked'] = true;
            return true;
        }

        $_SESSION['_is_admin_checked'] = false;
        return false;
    } catch (\Throwable $e) {
        error_log("[AUTH] Error verificando admin: " . $e->getMessage());
        $_SESSION['_is_admin_checked'] = false;
        return false;
    }
}
```

#### index.php — Reemplazar bloques de strpos

**Donde dice (líneas 34-40):**
```php
$roleName = strtolower($_SESSION['user']['role_name'] ?? '');
$isOtiAdmin = ($_SESSION['user']['es_admin'] ?? false) ||
              strpos($roleName, 'admin') !== false ||
              strpos($roleName, 'director') !== false ||
              strpos($roleName, 'jefe') !== false ||
              strpos($roleName, 'coordinador') !== false ||
              strpos($roleName, 'supervisor') !== false;
```

**Reemplazar con:**
```php
$isOtiAdmin = \App\Services\AuthService::isAdmin();
```

**Donde dice (líneas 102-112):**
```php
$esAdmin = $_SESSION['user']['es_admin'] ?? false;
$userId = $_SESSION['user']['id'] ?? null;
$roleName = strtolower($_SESSION['user']['role_name'] ?? '');

$isOtiAdmin = $esAdmin ||
              strpos($roleName, 'admin') !== false ||
              strpos($roleName, 'director') !== false ||
              strpos($roleName, 'jefe') !== false ||
              strpos($roleName, 'coordinador') !== false ||
              strpos($roleName, 'supervisor') !== false;
```

**Reemplazar con:**
```php
$userId = $_SESSION['user']['id'] ?? null;
$isOtiAdmin = \App\Services\AuthService::isAdmin();
```

#### En `app/Views/user/ticket-detalle.php`

**Buscar:**
```php
$isOtiAdmin = $esAdmin ||
              strpos($roleNameLower, 'admin') !== false || ...
```

**Reemplazar con:**
```php
$isOtiAdmin = \App\Services\AuthService::isAdmin();
```

#### En `app/Controller/AuthController.php`

Reemplazar líneas 35-42:
```php
$isOtiAdmin = \App\Services\AuthService::isAdmin();
```

#### En `app/api/tickets.php`

Reemplazar líneas 29-36:
```php
$isAdmin = \App\Services\AuthService::isAdmin();
```

#### En `public/assets/js/realtime.js` (líneas 23-33)

Reemplazar la detección de admin por JS:

```javascript
isAdmin = adminElement ? adminElement.value === '1' : false;
// Eliminar el bloque que lee roleElement.textContent y hace strpos
```

El backend ya debe pasar `is-admin` como data attribute desde el HTML.

**Agregar helper global en `app/Helpers/functions.php`:**
```php
if (!function_exists('is_oti_admin')) {
    function is_oti_admin(): bool
    {
        return \App\Services\AuthService::isAdmin();
    }
}
```

**Verificación:** Usuario con rol "Administrador" en `admin.usuario_rol` → acceso admin. Usuario "administrativo" (substring) → sin acceso admin.

---

### 0.5 CORS restrictivo

**Fuente:** A-E1 item 4 (helper Cors reutilizable)

#### Archivo nuevo: `app/Helpers/Cors.php`

```php
<?php
declare(strict_types=1);

namespace App\Helpers;

class Cors
{
    private static array $allowedOrigins = [
        'http://localhost',
        'http://localhost/OTI',
        'http://127.0.0.1',
        'http://127.0.0.1/OTI',
    ];

    public static function setHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, self::$allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } else {
            header('Access-Control-Allow-Origin: ' . self::$allowedOrigins[0]);
        }

        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
        header('Access-Control-Allow-Credentials: true');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
```

#### En `app/api/tickets.php`

Reemplazar:
```php
// Eliminar:
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type');

// Agregar al inicio después de session_start():
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Helpers\Cors;
Cors::setHeaders();
```

#### En `app/api/sse.php`

Mismo reemplazo.

**Verificación:** fetch desde `http://evil.com` → falla. Desde mismo origen → funciona.

---

### 0.6 XSS crítico en ticket-detalle.php y search.js

**Fuente:** A-E1 item 5 (más completo que B-E1, sanitiza con filter_var + escapeHtml en 4 campos)

#### `app/Views/user/ticket-detalle.php` línea 21:

```php
// Antes:
$ticketId = $_GET['id'] ?? null;

// Después:
$ticketId = isset($_GET['id']) ? filter_var($_GET['id'], \FILTER_VALIDATE_INT) : null;
if ($ticketId === false || $ticketId === null) {
    header('Location: ' . $baseUrl . 'user/tickets');
    exit;
}
```

#### `app/Views/user/ticket-detalle.php` línea 318:

```php
<!-- Antes: -->
<input type="hidden" id="ticket-id" value="<?= $ticketId ?>">

<!-- Después: -->
<input type="hidden" id="ticket-id" value="<?= (int)$ticketId ?>">
```

#### `public/assets/js/search.js` — displayResults con sanitización total

**La función `escapeHtml()` ya existe en search.js (línea 269).** Reemplazar `displayResults()` con:

```javascript
function displayResults(data) {
    if (!data.results || data.results.length === 0) {
        showEmpty('No se encontraron resultados');
        return;
    }

    searchResults = data.results;
    currentIndex = -1;

    let html = '';
    let currentCategory = '';

    data.results.forEach((result, index) => {
        if (result.category !== currentCategory) {
            currentCategory = result.category;
            html += `<div class="search-result-category">${escapeHtml(result.category)}</div>`;
        }

        const safeUrl = escapeHtml(result.url || '#');
        const safeTitle = escapeHtml(result.title || '');
        const safeMeta = escapeHtml(result.meta || '');
        const safeBadge = escapeHtml(result.badge || '');
        const safeIcon = result.icon || '';

        html += `
            <a href="${safeUrl}" class="search-result-item" data-index="${index}" data-url="${safeUrl}">
                <div class="search-result-icon ${escapeHtml(result.iconClass || '')}">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="${safeIcon}"/></svg>
                </div>
                <div class="search-result-content">
                    <div class="search-result-title">${safeTitle}</div>
                    <div class="search-result-meta">${safeMeta}</div>
                </div>
                ${result.badge ? `<span class="search-result-badge ${escapeHtml(result.badgeClass || '')}">${safeBadge}</span>` : ''}
            </a>
        `;
    });

    resultsContainer.innerHTML = html;

    const firstItem = resultsContainer.querySelector('.search-result-item');
    if (firstItem) {
        firstItem.classList.add('selected');
        currentIndex = 0;
    }
}
```

**Verificación:** `ticket-detalle.php?id=<script>alert(1)</script>` → redirige. Campos en search sanitizados con `escapeHtml`.

---

### 0.7 Error disclosure en search.php (SQL injection fix)

**Fuente:** A-E1 item 6

#### `app/api/search.php`

```php
// Antes (líneas 113-116):
} catch (Exception $e) {
    echo json_encode(['error' => 'Error en la búsqueda', 'details' => $e->getMessage()]);
    exit;
}

// Después:
} catch (Exception $e) {
    error_log('Search error: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al procesar la búsqueda']);
    exit;
}
```

---

### 0.8 Error disclosure en todas las APIs (JsonResponse helper)

**Fuente:** A-E1 item 7 (helper centralizado + reemplazo en todos los catch blocks)

#### Archivo nuevo: `app/Helpers/JsonResponse.php`

```php
<?php
declare(strict_types=1);

namespace App\Helpers;

class JsonResponse
{
    public static function error(\Throwable $e, string $userMessage = 'Error interno del servidor', int $httpCode = 500): void
    {
        error_log(sprintf(
            "[%s] %s in %s:%d",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $userMessage]);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'OK', int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}
```

#### Reemplazar todos los catch blocks en `app/api/tickets.php`, `app/api/usuarios.php`, `app/api/search.php`:

```php
// Antes:
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

// Después:
} catch (Exception $e) {
    \App\Helpers\JsonResponse::error($e);
}
```

**Verificación:** Forzar error BD → mensaje "Error interno del servidor" en JSON. Error real en error_log.

---

### 0.9 Bug User.php:793 telefono vs :phone

**Fuente:** A-E1 item 8

#### `app/Models/User.php` línea 793:

```php
// Antes:
$stmt->execute(['user_id' => $userId, 'telefono' => $data['telefono']]);

// Después:
$stmt->execute(['user_id' => $userId, 'phone' => $data['telefono']]);
```

**Verificación:** Actualizar teléfono → se guarda en `oti.user_profiles.phone`.

---

### 0.10 SET NAMES 'UTF8' → PostgreSQL

**Fuente:** A-E1 item 9 (ya incluido en el Database.php completo del punto 0.3)

**Verificación:** Conexión PostgreSQL sin errores `SET NAMES`.

---

## SPRINT 1: HARDENING (Semana 2, ~8h)

> Base: B-E1 Fase 1 items 1,2 + B-E1 Fase 3

### 1.1 Session hardening + timeout

**Fuente:** B-E1 item 1 (middleware unificado con timeout)

Reemplazar el bloque superior de `index.php` con session hardening ANTES de `session_start()`:

```php
// ─── Session hardening antes de session_start() ───
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);

session_start();

// ─── Regenerar ID en cada request (protección fixation) ───
if (!isset($_SESSION['_initiated'])) {
    session_regenerate_id(true);
    $_SESSION['_initiated'] = true;
    $_SESSION['_created'] = time();
}
$_SESSION['_last_activity'] = time();

// ─── Timeout absoluto 12h ───
if (isset($_SESSION['_created']) && (time() - $_SESSION['_created']) > 43200) {
    session_destroy();
    header('Location: /OTI/login');
    exit;
}

// ─── Timeout de inactividad 30min ───
$inactivityLimit = 1800;
if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity']) > $inactivityLimit) {
    session_destroy();
    header('Location: /OTI/login');
    exit;
}
```

**Nota sobre CSP:** Se maneja en SPRINT 5 (item 5.2) usando `Security::setHeaders()` con nonce dinámico, que es más limpio que ponerlo inline en index.php.

**Verificación:** Cookie con SameSite=Strict, HttpOnly. Timeout 31 min inactividad → redirect login.

---

### 1.2 Rate limiting en login

**Fuente:** B-E1 item 2b

#### `app/Controller/AuthController.php` login() method:

Modificar el método `login()` para incluir rate limiting:

```php
public function login(): void
{
    // Rate limiting
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitFile = sys_get_temp_dir() . '/oti_login_' . md5($ip);
    $attempts = [];

    if (file_exists($rateLimitFile)) {
        $attempts = json_decode(file_get_contents($rateLimitFile), true) ?: [];
        $attempts = array_filter($attempts, fn($t) => $t > (time() - 900));
    }

    if (count($attempts) >= 5) {
        $waitMinutes = ceil((900 - (time() - min($attempts))) / 60);
        $_SESSION['error'] = "Demasiados intentos. Espere {$waitMinutes} minutos.";
        header('Location: ' . BASE_URL . 'login');
        exit;
    }

    $identifier = trim($_POST['identifier'] ?? $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $_SESSION['error'] = 'Usuario y contraseña son requeridos';
        header('Location: ' . BASE_URL . 'login');
        exit;
    }

    // Registrar intento
    $attempts[] = time();
    file_put_contents($rateLimitFile, json_encode($attempts));

    $result = AuthService::login($identifier, $password);

    if (isset($result['error'])) {
        $_SESSION['error'] = $result['error'];
        header('Location: ' . BASE_URL . 'login');
        exit;
    }

    // Login exitoso, resetear contador
    if (file_exists($rateLimitFile)) {
        unlink($rateLimitFile);
    }

    $isOtiAdmin = \App\Services\AuthService::isAdmin();
    header('Location: ' . ($isOtiAdmin ? BASE_URL . 'admin/dashboard' : BASE_URL . 'user/dashboard'));
    exit;
}
```

**Verificación:** 5 intentos fallidos → mensaje de espera. Login exitoso → reset contador.

---

### 1.3 Security headers en .htaccess

**Fuente:** B-E1 item 14

#### `.htaccess` — Agregar en el bloque `<IfModule mod_headers.c>` existente (líneas 52+):

```apache
# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), camera=(), microphone=()"
</IfModule>
```

**Verificación:** `curl -I http://localhost/OTI/` → headers presentes.

---

### 1.4 Error disclosure sanitizado en APIs

**Fuente:** A-E1 item 7 (ya implementado como JsonResponse helper en Sprint 0.8)

Verificar que todos los catch blocks en APIs usen `\App\Helpers\JsonResponse::error($e)`.

---

### 1.5 BASE_URL dinámica

**Fuente:** B-E1 item 15 (detección automática) combinado con C-E3 item 20 (lectura desde .env)

#### `index.php` línea 16 — Reemplazar:

```php
// Antes:
define('BASE_URL', 'http://localhost/OTI/');

// Después:
$baseUrlFromEnv = rtrim(getenv('APP_URL') ?: '', '/') . '/';
if (empty(getenv('APP_URL'))) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $baseUrlFromEnv = $protocol . '://' . $host . $scriptDir . '/';
}
define('BASE_URL', $baseUrlFromEnv);
```

#### En `app/Views/partials/head.php` — Agregar antes de cerrar `</head>`:

```html
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>
```

#### En JS files — Reemplazar línea 9 de cada archivo:

**`realtime.js`:**
```javascript
const BASE_URL = window.BASE_URL || window.location.origin + '/OTI/';
```

**`analisis-charts.js`:**
```javascript
const BASE_URL = window.BASE_URL || window.location.origin + '/OTI/';
```

**`search.js`:**
```javascript
const BASE_URL = window.BASE_URL || window.location.origin + '/OTI/';
```

#### En `app/Views/user/ticket-detalle.php`:

```php
$baseUrl = defined('BASE_URL') ? BASE_URL : (
    ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'
);
```

**Verificación:** En localhost → `http://localhost/OTI/`. En producción → detecta automáticamente.

---

## SPRINT 2: MÓDULO TICKETS V2 (Semana 3, ~12h)

> Base: C-E3 Sprint 1 + Feature Flags

### 2.1 BaseController

**Fuente:** C-E3 item 2

#### Archivo nuevo: `app/Controller/BaseController.php`

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

### 2.2 TicketController V2

**Fuente:** C-E3 item 8

#### Archivo nuevo: `app/Controller/v2/TicketController.php`

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

### 2.3 Vista tickets V2

**Fuente:** C-E3 item 10

#### Archivo nuevo: `app/Views/v2/tickets/tickets.php`

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
        <a href="<?= $baseUrl ?>user/reportar" class="btn btn-primary">+ Nuevo Ticket</a>
        <?php endif; ?>
    </div>

    <?php if (empty($tickets)): ?>
    <div class="empty-state"><p>No hay tickets registrados.</p></div>
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

#### Archivo nuevo: `app/Views/v2/tickets/ticket-detalle.php`

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
        <a href="<?= $baseUrl . ($isAdmin ? 'admin' : 'user') ?>/tickets" class="back-btn">&larr; Volver</a>
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
                        onclick="return confirm('¿Cancelar este ticket?')">Cancelar Ticket</button>
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

#### Archivo nuevo: `app/Views/v2/tickets/ticket-form.php`

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

### 2.4 Ticket::getStats() optimizado

**Fuente:** C-E3 item 9 (incluye cancelados status_id=5 que A-E1 y B-E1 omiten)

Reemplazar el método `getStats()` en `app/Models/Ticket.php`:

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

---

### 2.5 Feature flag MODULE_TICKETS_V2 en index.php

**Fuente:** C-E3 item 3 (fallback automático)

En `index.php`, agregar antes del sistema de rutas:

```php
// === FEATURE FLAG FALLBACK SYSTEM ===
require_once __DIR__ . '/app/Helpers/FeatureFlag.php';
use App\Helpers\FeatureFlag;

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

#### Rutas de tickets con feature flag (reemplazar cases en switch):

En el bloque de rutas admin:
```php
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

En el bloque de rutas user:
```php
case '/user/tickets':
    if (FeatureFlag::isActive('TICKETS_V2')) {
        runV2WithFallback(
            function () {
                $controller = new \App\Controller\v2\TicketController();
                $controller->index();
            },
            BASE_URL . 'user/dashboard'
        );
    } else {
        $tickets = Ticket::getByUserId($userId);
        require __DIR__ . '/app/Views/user/tickets.php';
    }
    exit;
```

---

## SPRINT 3: MÓDULOS USUARIOS + EQUIPOS V2 (Semana 4, ~12h)

> Base: C-E3 Sprint 2 + Sprint 3

### 3.1 UserController V2

**Fuente:** C-E3 item 11

#### Archivo nuevo: `app/Controller/v2/UserController.php`

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
                $stmt = $pdo->prepare("UPDATE admin.usuarios SET email = :email WHERE id = :id");
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
            \App\Helpers\JsonResponse::error($e, 'Error al actualizar usuario');
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

---

### 3.2 Location::getStats() + Equipment::getStats() optimizados

**Fuente:** C-E3 item 15 (más compacto que A-E1, ambas en 1 query con subqueries inline)

#### `app/Models/Location.php` — Reemplazar getStats():

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

#### `app/Models/Equipment.php` — Reemplazar getStats():

```php
public static function getStats(): array
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

### 3.3 Índices compuestos SQL

**Fuente:** A-E1 item 11 (más completo, incluye GIN trigramas) combinado con C-E3 item 16

#### Archivo nuevo: `database/migrations/002_performance_indexes.sql`

```sql
-- ========================================
-- 002_performance_indexes.sql
-- Índices de rendimiento para OTI
-- ========================================

CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Tickets: filtrado por usuario + estado (dashboard de usuario)
CREATE INDEX IF NOT EXISTS idx_tickets_user_status
    ON oti.tickets (user_id, status_id, created_at DESC);

-- Tickets: filtrado por admin asignado + estado (dashboard de admin)
CREATE INDEX IF NOT EXISTS idx_tickets_assigned_status
    ON oti.tickets (assigned_admin_id, status_id, created_at DESC);

-- Tickets: búsqueda full-text con trigramas para ILIKE
CREATE INDEX IF NOT EXISTS idx_tickets_title_trgm
    ON oti.tickets USING gin (title gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_tickets_description_trgm
    ON oti.tickets USING gin (description gin_trgm_ops);

-- Tickets: filtro por prioridad
CREATE INDEX IF NOT EXISTS idx_tickets_priority_status
    ON oti.tickets (priority_id, status_id);

-- Tickets: fechas de cierre/resolución (estadísticas)
CREATE INDEX IF NOT EXISTS idx_tickets_dates
    ON oti.tickets (closed_at, resolved_at)
    WHERE closed_at IS NOT NULL;

-- Equipos: búsqueda por nombre, serial, patrimonial
CREATE INDEX IF NOT EXISTS idx_equipment_search
    ON oti.equipment (name, serial_number, patrimonial_code);

CREATE INDEX IF NOT EXISTS idx_equipment_status
    ON oti.equipment (status, is_deleted);

CREATE INDEX IF NOT EXISTS idx_equipment_location
    ON oti.equipment (location_id, is_deleted);

CREATE INDEX IF NOT EXISTS idx_equipment_assigned_user
    ON oti.equipment (assigned_user_id, is_deleted);

-- Usuarios: búsqueda por nombre y email
CREATE INDEX IF NOT EXISTS idx_usuarios_nombre
    ON admin.usuarios USING gin (nombre gin_trgm_ops);

CREATE INDEX IF NOT EXISTS idx_usuarios_email
    ON admin.usuarios (email);

-- Ubicaciones: árbol de jerarquía
CREATE INDEX IF NOT EXISTS idx_locations_parent
    ON oti.locations (parent_id);

CREATE INDEX IF NOT EXISTS idx_locations_type_active
    ON oti.locations (type, active);

-- User profiles: búsqueda por ubicación
CREATE INDEX IF NOT EXISTS idx_user_profiles_location
    ON oti.user_profiles (location_id);

-- Notificaciones: consulta de no leídas por usuario
CREATE INDEX IF NOT EXISTS idx_notifications_user_unread
    ON oti.notifications (user_id, is_read, created_at DESC);

-- Actividades / historial de tickets
CREATE INDEX IF NOT EXISTS idx_ticket_activities_ticket
    ON oti.ticket_activities (ticket_id, created_at DESC);

-- Comentarios por ticket
CREATE INDEX IF NOT EXISTS idx_ticket_comments_ticket
    ON oti.ticket_comments (ticket_id, created_at DESC);
```

---

### 3.4 Vista usuarios V2

**Fuente:** C-E3 item 13

#### Archivo nuevo: `app/Views/v2/users/users.php`

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
                        <a href="<?= $baseUrl ?>admin/usuarios/<?= (int)$u['id'] ?>" class="btn btn-sm">Ver</a>
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

### 3.5 Feature flags MODULE_USERS_V2 + MODULE_EQUIPMENT_V2

En `index.php`, agregar en el switch de rutas admin:

```php
case '/admin/usuarios':
    if (FeatureFlag::isActive('USERS_V2')) {
        runV2WithFallback(
            function () {
                $controller = new \App\Controller\v2\UserController();
                $controller->index();
            },
            BASE_URL . 'admin/dashboard'
        );
    } else {
        require __DIR__ . '/app/Views/admin/usuarios.php';
    }
    exit;

case '/admin/equipos':
    if (FeatureFlag::isActive('EQUIPMENT_V2')) {
        runV2WithFallback(
            function () {
                $controller = new \App\Controller\v2\EquipmentController();
                $controller->index();
            },
            BASE_URL . 'admin/dashboard'
        );
    } else {
        require __DIR__ . '/app/Views/admin/equipos.php';
    }
    exit;
```

---

## SPRINT 4: JS + SSE + RENDIMIENTO (Semana 5, ~6h)

> Base: A-E1 items 10-13 + B-E1 Fase 3

### 4.1 Fix SSE doble callback + error crash en realtime.js

**Fuente:** A-E1 item 12 (más completo que B-E1 item 7 — elimina addEventListener de error, que es la causa raíz)

#### `public/assets/js/realtime.js` — Corregir precedencia línea 49:

```javascript
// Antes:
if (useSSE && currentPage === 'admin-dashboard' || currentPage === 'user-dashboard') {

// Después:
if (useSSE && (currentPage === 'admin-dashboard' || currentPage === 'user-dashboard')) {
```

#### Eliminar bloque addEventListener('error', ...) líneas 95-98:

```javascript
// ELIMINAR completamente:
// eventSource.addEventListener('error', function(event) {
//     const data = JSON.parse(event.data);
//     console.error('SSE error:', data);
// });
```

#### Código completo del bloque initSSE() corregido:

```javascript
function initSSE() {
    try {
        eventSource = new EventSource(BASE_URL + 'app/api/sse.php');

        eventSource.onopen = function() {
            console.log('SSE conectado');
            if (updateInterval) {
                clearInterval(updateInterval);
                updateInterval = null;
            }
        };

        eventSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                handleDataUpdate(data);
            } catch (e) {
                console.error('Error parseando datos SSE:', e);
            }
        };

        eventSource.addEventListener('update', function(event) {
            try {
                const data = JSON.parse(event.data);
                handleDataUpdate(data);
            } catch (e) {
                console.error('Error en evento update:', e);
            }
        });

        eventSource.addEventListener('connected', function(event) {
            try {
                const data = JSON.parse(event.data);
                console.log('SSE conectado:', data);
            } catch (e) {
                console.error('Error parseando connected event:', e);
            }
        });

        // NOTA: El evento 'error' se maneja SOLO en eventSource.onerror
        // (no agregar addEventListener('error', ...) porque event.data es null)

        eventSource.onerror = function(e) {
            console.warn('SSE error, cambiando a polling:', e);
            closeSSE();
            useSSE = false;
            fetchAllData();
            updateInterval = setInterval(fetchAllData, 15000);
        };

    } catch (e) {
        console.warn('SSE no disponible, usando polling:', e);
        useSSE = false;
        fetchAllData();
        updateInterval = setInterval(fetchAllData, 15000);
    }
}
```

**Verificación:** user/dashboard → SSE inicia. user/tickets → polling. Desconectar SSE → fallback graceful sin error `JSON.parse(null)`.

---

### 4.2 Fix Canvas vs Context2D en analisis-charts.js:108

**Fuente:** A-E1 item 13 (más completo, crea `createGradientFromCanvas`)

#### Reemplazar funciones gradient y todas las init*Chart:

```javascript
function createGradientFromCanvas(canvasId, colorStart, colorEnd) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return colorStart + '40';
    try {
        const ctx2d = canvas.getContext('2d');
        if (!ctx2d) return colorStart + '40';
        const gradient = ctx2d.createLinearGradient(0, 0, 0, canvas.height || 300);
        gradient.addColorStop(0, colorStart + '40');
        gradient.addColorStop(1, colorEnd + '10');
        return gradient;
    } catch (e) {
        return colorStart + '40';
    }
}

function createBarGradientFromCanvas(canvasId, color) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return color;
    try {
        const ctx2d = canvas.getContext('2d');
        if (!ctx2d) return color;
        const gradient = ctx2d.createLinearGradient(0, 0, 0, canvas.height || 300);
        gradient.addColorStop(0, color);
        gradient.addColorStop(1, color + '80');
        return gradient;
    } catch (e) {
        return color;
    }
}
```

Reemplazar en `initTicketsMensualChart`:
```javascript
function initTicketsMensualChart(data) {
    const canvas = document.getElementById('chart-tickets-mensual');
    if (!canvas) return;

    const labels = data.map(d => formatMonth(d.mes));
    const values = data.map(d => parseInt(d.count));

    const bgColor = createGradientFromCanvas('chart-tickets-mensual', CHART_COLORS.primaryLight, CHART_COLORS.primary);

    charts.ticketsMensual = new Chart(canvas, {
        type: 'line',
        // ... resto igual ...
    });
}
```

Aplicar mismo patrón a `initUbicacionesChart`, `initUsuariosChart` usando `createBarGradientFromCanvas`.

**Eliminar** las funciones viejas `createGradient` y `createBarGradient` (líneas 486-512).

**Verificación:** Gradientes renderizados correctamente, sin error "createLinearGradient is not a function".

---

### 4.3 Fix XSS DOM en search.js líneas 202-214

**Fuente:** A-E1 item 5 (ya implementado en Sprint 0.6)

Verificar que `displayResults()` en search.js use `escapeHtml()` para todos los campos: url, title, meta, badge, iconClass, badgeClass.

---

### 4.4 Unificar lógica admin (últimas repeticiones de strpos)

**Fuente:** A-E1 item 3 + B-E1 item 9

Verificar que no quede ningún `strpos($roleName, 'admin')` en el código. Buscar en:
- `index.php`
- `app/api/tickets.php`
- `app/api/usuarios.php`
- `app/Views/user/ticket-detalle.php`
- `app/Controller/AuthController.php`
- `public/assets/js/realtime.js`

Todos deben usar `\App\Services\AuthService::isAdmin()` o `is_oti_admin()`.

---

## SPRINT 5: CSS + PULIDO FINAL (Semana 6, ~7h)

> Base: B-E1 Fase 4

### 5.1 División de app.css → base.css + components.css

**Fuente:** B-E1 item 13

#### Archivo nuevo: `public/assets/css/base.css`

Contiene: variables CSS (design tokens), reset, tipografía, layout principal, animaciones base, utilidades.

Extraer de `app.css` las primeras ~200 líneas de variables y reset, más las animaciones y utilities. Ver B-E1 item 13 para el código detallado.

#### Archivo nuevo: `public/assets/css/components.css`

Contiene: sidebar, cards, tablas, botones, formularios, badges, modales, navbar, paginación, estados vacíos, timeline, notificaciones, search modal, charts.

Extraído de `app.css` eliminando duplicados (`.empty-state`, `.stagger-children`, `.page-header`, animaciones `@keyframes`) y removiendo `!important` donde sea posible usando mayor especificidad.

#### En `app/Views/partials/head.php`:

Reemplazar:
```php
<link rel="stylesheet" href="<?= $baseUrl ?>public/assets/css/app.css">
```
con:
```php
<link rel="stylesheet" href="<?= $baseUrl ?>public/assets/css/base.css">
<link rel="stylesheet" href="<?= $baseUrl ?>public/assets/css/components.css">
```

Mantener `app.css` como respaldo (opcional, puede eliminarse después de verificar).

---

### 5.2 Security headers en index.php (CSP con nonce dinámico)

**Fuente:** B-E1 item 1 (CSP con nonce) + `app/Helpers/security.php` ya existente

En `index.php`, después del session hardening, agregar:

```php
// ─── CSP con nonce ───
$nonce = bin2hex(random_bytes(16));
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net; " .
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
    "font-src 'self' https://fonts.gstatic.com; " .
    "img-src 'self' data:; " .
    "base-uri 'self'; " .
    "form-action 'self'; " .
    "object-src 'none';"
);

// ─── Asignar nonce a las vistas ───
$_SESSION['_csp_nonce'] = $nonce;
```

Luego en las vistas (ej. `head.php`), usar el nonce para los tags `<script>`:
```html
<script nonce="<?= $_SESSION['_csp_nonce'] ?? '' ?>">
```

---

### 5.3 Eliminar console.logs remanentes

En producción, los `console.log`/`console.warn`/`console.error` deben eliminarse o usarse con logger condicional. Agregar al inicio de `realtime.js` y `analisis-charts.js`:

```javascript
const DEBUG = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
if (!DEBUG) {
    console.log = function() {};
    console.warn = function() {};
}
```

O bien reemplazar todas las ocurrencias de `console.log`, `console.warn`, `console.error` con funciones condicionales (ver B-E1 item 7 para el patrón `logDebug`/`logWarn`).

---

### 5.4 Últimos fixes de accesibilidad

Agregar en vistas:
- `aria-label` en todos los botones de acción (editar, eliminar, ver)
- Atributos `role` en elementos interactivos
- Texto alternativo (`alt`) en imágenes
- Contraste suficiente en colores de texto (verificar ratio 4.5:1 para texto normal)

---

## Checklist de verificación post-implementación

### Sprint 0
- [ ] `FeatureFlag::isActive('CSRF_STRICT')` verifica token en todos los POST
- [ ] Todos los formularios POST incluyen `_csrf_token` oculto
- [ ] POST sin token → 419 (API) o redirect con error (HTML)
- [ ] Sin `.env` → error claro de configuración
- [ ] `isAdmin()` usa BD, no `strpos`
- [ ] No hay `strpos($roleName, 'admin')` en ningún archivo
- [ ] CORS: fetch desde origen externo falla
- [ ] XSS: `ticket-detalle.php?id=<script>` redirige
- [ ] search.js: todos los campos sanitizados con `escapeHtml()`
- [ ] search.php: mensaje de error genérico, detalle en `error_log`
- [ ] User.php:793 usa clave `'phone'`, no `'telefono'`
- [ ] Database.php usa `SET client_encoding TO 'UTF8'`

### Sprint 1
- [ ] Cookie de sesión con SameSite=Strict, HttpOnly
- [ ] Timeout absoluto 12h funciona
- [ ] Timeout inactividad 30min funciona
- [ ] Regeneración de session ID en cada request
- [ ] 5 intentos fallidos de login → bloqueo 15min
- [ ] .htaccess envía HSTS, X-Frame-Options, Referrer-Policy
- [ ] BASE_URL dinámica funciona en localhost y producción
- [ ] JS usa `window.BASE_URL`

### Sprint 2
- [ ] FeatureFlag class carga desde `.env`
- [ ] BaseController con json(), error(), validate(), verifyCsrf()
- [ ] TicketController V2 con CRUD completo
- [ ] `Ticket::getStats()` ejecuta 1 query (no 5)
- [ ] Vista tickets V2 sanitiza toda salida con `htmlspecialchars`
- [ ] Flag `TICKETS_V2=false` usa V1; `=true` usa V2
- [ ] Fallback V2→V1 funciona si V2 lanza excepción

### Sprint 3
- [ ] UserController V2 con CRUD completo
- [ ] `Location::getStats()` ejecuta 1 query (no 6)
- [ ] `Equipment::getStats()` ejecuta 1 query (no 5)
- [ ] Índices SQL aplicados y verificados con `EXPLAIN ANALYZE`
- [ ] Flags `USERS_V2` y `EQUIPMENT_V2` funcionales

### Sprint 4
- [ ] SSE: `if (useSSE && (currentPage === ...))` con paréntesis correctos
- [ ] SSE: no hay `addEventListener('error')` con `JSON.parse`
- [ ] Gradientes en charts funcionan (no color sólido)
- [ ] No hay errores "createLinearGradient is not a function"

### Sprint 5
- [ ] `base.css` + `components.css` cargan correctamente
- [ ] No hay diferencias visuales con `app.css` legacy
- [ ] CSP con nonce se envía como header
- [ ] `console.log` no aparece en producción
- [ ] Elementos interactivos tienen `aria-label`

---

## Riesgos y mitigaciones

| Riesgo | Impacto | Probabilidad | Mitigación |
|--------|---------|-------------|------------|
| Feature flag desactivado olvida activar V2 | Bajo | Media | Flags default `false`; documentar activación en checklist de deploy |
| Middleware CSRF bloquea peticiones legítimas | Alto | Baja | Usar `MODULE_CSRF_STRICT` flag; probar en staging antes de activar |
| Session timeout (12h/30min) desconecta usuarios en medio de trabajo | Medio | Baja | Documentar en comunicación al usuario; timeout generoso (12h) |
| Rate limiting en login bloquea IP compartida (ej. proxy corporativo) | Medio | Baja | 5 intentos/15min es conservador; IP única por usuario |
| Fallback V2→V1 no captura TypeError (error JS en vista) | Medio | Media | `runV2WithFallback` usa `\Throwable` que captura todo |
| Migración SQL de índices degrada temporalmente | Bajo | Baja | Índices con `CREATE IF NOT EXISTS` + `CONCURRENTLY` en producción |
| BASE_URL dinámica rompe assets si subdirectorio cambia | Alto | Baja | Probado en staging; JS usa `window.BASE_URL` con fallback |
| División CSS rompe estilos existentes | Medio | Media | Mantener `app.css` como respaldo; comparar screenshots antes/después |
| Actualizar `.env` en producción requiere reinicio de PHP-FPM | Bajo | Alta | Documentar proceso; usar `opcache_reset()` si es solo PHP |
| Coexistencia V1/V2 duplica mantenimiento | Medio | Alta | Plan de deprecación de V1 cuando flags estén estables (post 3 meses) |

---

## Resumen de Feature Flags

| Flag | Default | Controla | Sprint |
|------|---------|----------|--------|
| `MODULE_CSRF_STRICT` | `false` | CSRF check obligatorio en todos los POST | Sprint 0 |
| `MODULE_TICKETS_V2` | `false` | TicketController V2 + vistas V2 | Sprint 2 |
| `MODULE_USERS_V2` | `false` | UserController V2 + vistas V2 | Sprint 3 |
| `MODULE_EQUIPMENT_V2` | `false` | EquipmentController V2 | Sprint 3 |
| `MODULE_SEARCH_V2` | `false` | Búsqueda global mejorada (futuro) | — |

Para activar un módulo, añadir en `.env`:
```
MODULE_CSRF_STRICT=true
MODULE_TICKETS_V2=true
```

---

## Pipeline de despliegue

```
Sprint 0 (días 1-3)  → Parches seguridad + FeatureFlag + isAdmin() + CSRF + CORS
Sprint 1 (semana 2)  → Session hardening + rate limiting + .htaccess + BASE_URL
Sprint 2 (semana 3)  → BaseController + Tickets V2 + stats optimizados
Sprint 3 (semana 4)  → Usuarios V2 + Equipos V2 + índices SQL
Sprint 4 (semana 5)  → Fixes JS (SSE, Canvas, search)
Sprint 5 (semana 6)  → CSS splitting + CSP + console.log cleanup + accesibilidad
```

En cada sprint:
1. Desarrollar con flag=false
2. Activar flag en staging para QA
3. Si todo OK, activar en producción
4. Si algo falla, desactivar flag = rollback instantáneo sin deploy
