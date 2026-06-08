# A-E1: Cirugía de Seguridad + Optimización Selectiva

> Plan de implementación detallado con código concreto para resolver 13 issues críticos y de alta prioridad en el sistema OTI municipal.

---

## Prioridad CRÍTICA (implementar primero)

---

### 1. CSRF real en todos los endpoints POST

**Problema:** Las funciones `csrf_token()` y `verify_csrf()` existen en `app/Helpers/functions.php` pero **nunca se ejecutan**. El `index.php` no verifica tokens CSRF en ningún POST. Todos los formularios carecen de campo `_token`. Cualquier sitio externo puede hacer ataques CSRF contra todos los endpoints POST del sistema.

**Archivos afectados:**
- `index.php` (líneas 48-94, múltiples rutas POST)
- `app/Helpers/functions.php` (funciones ya existen)
- `app/api/tickets.php`
- `app/api/usuarios.php`
- Todas las vistas con formularios POST

**Solución propuesta:**
1. Agregar un middleware CSRF en `index.php` que intercepte TODAS las peticiones POST antes de cualquier lógica de ruta.
2. Agregar campo `_token` en todos los formularios de todas las vistas.
3. En APIs que reciben POST, verificar el token CSRF desde el header `X-CSRF-Token`.

**Código:**

#### index.php — Middleware CSRF global (agregar después de `session_start()` línea 14):

```php
// ============================================================
// MIDDLEWARE CSRF — Protección contra Cross-Site Request Forgery
// ============================================================
require_once __DIR__ . '/app/Helpers/functions.php';

if ($requestMethod === 'POST') {
    $token = $_POST['_token'] ?? '';
    if (empty($token)) {
        $headers = getallheaders();
        $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
    }
    if (!verify_csrf($token)) {
        http_response_code(419);
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'CSRF token inválido o expirado. Recarga la página e intenta de nuevo.']);
            exit;
        }
        $_SESSION['error'] = 'Error de seguridad: token inválido. Por favor recarga la página.';
        header('Location: ' . $_SERVER['HTTP_REFERER'] ?? BASE_URL);
        exit;
    }
}
```

#### CÓDIGO COMPLETO de functions.php con CSRF fix:

```php
<?php
declare(strict_types=1);

use App\Core\Config;

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        $expires = (int)Config::get('CSRF_TOKEN_EXPIRES', 3600);

        if (empty($_SESSION['csrf_token']) || (isset($_SESSION['csrf_token_expires']) && time() > $_SESSION['csrf_token_expires'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_expires'] = time() + $expires;
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool
    {
        if (empty($token) || !isset($_SESSION['csrf_token'])) {
            return false;
        }

        if (isset($_SESSION['csrf_token_expires']) && time() > $_SESSION['csrf_token_expires']) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_expires']);
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

// ... resto de funciones igual (clean, sanitize_html, is_valid_uuid, etc.)
```

#### En cada formulario POST de las vistas, agregar al inicio del `<form>`:

```html
<?= csrf_field() ?>
```

Ejemplo para `app/Views/auth/login.php`:
```html
<form method="POST" action="<?= BASE_URL ?>login" class="login-form">
    <?= csrf_field() ?>
    <!-- resto del formulario -->
</form>
```

#### En APIs POST (tickets.php, usuarios.php) — Verificar también desde header:

En `app/api/tickets.php` y `app/api/usuarios.php`, agregar al inicio después del `require_once`:

```php
// CSRF verification for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = getallheaders();
    $token = $_POST['_token'] ?? $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
    if (!verify_csrf($token)) {
        http_response_code(419);
        echo json_encode(['error' => 'CSRF token inválido']);
        exit;
    }
}
```

**Verificación:** Hacer un POST a cualquier endpoint sin token CSRF debe devolver 419. Los formularios deben incluir el campo `_token` con valor no vacío.

---

### 2. Eliminar contraseña hardcodeada en Database.php

**Problema:** `Database.php:19` tiene `$password = getenv('DB_PASSWORD') ?: '123456789'`. Esta contraseña por defecto es extremadamente débil y queda expuesta en el código. Si `.env` no se carga (falla silenciosa por `return;` en loadEnv), el sistema usa esta contraseña insegura.

**Archivos afectados:**
- `app/Core/Database.php`

**Solución propuesta:**
Forzar que la contraseña DEBE venir de una variable de entorno. Si no existe, lanzar una excepción en lugar de usar un fallback. Además, mejorar `loadEnv()` para que falle si no encuentra el archivo.

**Código completo de Database.php:**

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

**Verificación:** Eliminar `.env` y recargar — debe mostrar error claro. Poner `DB_PASSWORD` vacío en `.env` — debe fallar con el mensaje de excepción. Con `.env` correcto, debe conectar normalmente.

---

### 3. Auth por rol real (no strpos)

**Problema:** El patrón `strpos($roleName, 'admin')` se repite **111 veces en 19+ archivos**. Es frágil (cualquier rol con "admin" en el nombre gana acceso) y está duplicado en cada archivo de vista y API. Si se cambia un nombre de rol, hay que modificarlo en 19 sitios. El campo `es_admin` de `$_SESSION['user']` se setea desde un flag booleano en BD pero se ignora en favor del strpos.

**Archivos afectados:** 19+ archivos incluyendo:
- `index.php` (2 veces)
- `app/Services/AuthService.php` (1 vez, método `isAdmin()`)
- `app/Controller/AuthController.php` (1 vez)
- `app/api/tickets.php`, `app/api/usuarios.php`, `app/api/stats.php`, `app/api/sse.php`
- `app/Views/admin/*.php` (7 vistas)
- `app/Views/user/*.php` (6 vistas)

**Solución propuesta:**
1. Reescribir `AuthService::isAdmin()` para que consulte la tabla `admin.usuario_rol` (o la tabla de roles) y no use `strpos`.
2. Eliminar `strpos` de `index.php` y reemplazar con llamadas a `AuthService::isAdmin()`.
3. Eliminar las variables `$isOtiAdmin` redundantes de todas las vistas y usar `AuthService::isAdmin()` directamente.

**Código:**

#### app/Services/AuthService.php — isAdmin() real:

```php
public static function isAdmin(): bool
{
    if (!isset($_SESSION['user'])) {
        return false;
    }

    // Primero verificar el flag booleano directo (más rápido)
    if (!empty($_SESSION['user']['es_admin'])) {
        return true;
    }

    // Consultar roles desde la BD con cache en sesión
    if (!isset($_SESSION['user']['_is_admin_checked'])) {
        $pdo = \App\Core\Database::connect();
        try {
            // Buscar si el usuario tiene un rol administrativo en admin.usuario_rol
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as cnt
                FROM admin.usuario_rol ur
                JOIN admin.roles r ON ur.role_id = r.id
                WHERE ur.user_id = :user_id
                  AND r.name IN ('Administrador', 'Director', 'Jefe', 'Coordinador', 'Supervisor')
            ");
            $stmt->execute(['user_id' => $_SESSION['user']['id']]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $isAdmin = (int)$result['cnt'] > 0;
        } catch (\Exception $e) {
            // Fallback: si la tabla no existe, usar el flag es_admin
            $isAdmin = !empty($_SESSION['user']['es_admin']);
        }
        $_SESSION['user']['_is_admin_checked'] = $isAdmin;
        return $isAdmin;
    }

    return $_SESSION['user']['_is_admin_checked'];
}
```

#### index.php — Reemplazar los bloques de strpos:

**Donde dice (líneas 34-40 y 107-112):**
```php
$isOtiAdmin = $esAdmin ||
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

Y eliminar las líneas 104 y 106-112 que definen `$roleName` y `$isOtiAdmin` redundantes, ya que `AuthService::isAdmin()` lo hace internamente:

```php
// index.php después de session_start y require_once:
$esAdmin = $_SESSION['user']['es_admin'] ?? false;
$userId = $_SESSION['user']['id'] ?? null;

// Determinar si es admin de OTI usando AuthService centralizado
$isOtiAdmin = \App\Services\AuthService::isAdmin();
```

#### En cada archivo de vista (ej. `app/Views/admin/dashboard.php`):

**Buscar:**
```php
$isOtiAdmin = $esAdmin ||
              strpos($roleNameLower, 'admin') !== false ||
              strpos($roleNameLower, 'director') !== false ||
              strpos($roleNameLower, 'jefe') !== false ||
              strpos($roleNameLower, 'coordinador') !== false ||
              strpos($roleNameLower, 'supervisor') !== false;
```

**Reemplazar con:**
```php
$isOtiAdmin = \App\Services\AuthService::isAdmin();
```

**Verificación:** Iniciar sesión con un usuario que tiene rol "Administrador" en `admin.usuario_rol` → debe tener acceso admin. Usuario con rol "Usuario" → sin acceso admin. Verificar que el flag `_is_admin_checked` se guarda en sesión.

---

### 4. CORS restrictivo

**Problema:** `app/api/tickets.php:10` y `app/api/sse.php:13` tienen `Access-Control-Allow-Origin: *`, lo que permite que cualquier sitio web externo haga peticiones AJAX al sistema OTI. Esto expone datos internos a orígenes no autorizados.

**Archivos afectados:**
- `app/api/tickets.php`
- `app/api/sse.php`

**Solución propuesta:**
Implementar un sistema de whitelist dinámico. En producción, solo permitir el origen exacto del sistema. En desarrollo, permitir localhost.

**Código — Helper centralizado:**

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
        // En producción, agregar el dominio real:
        // 'https://oti.municipalidad.gob.pe',
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

#### En `app/api/tickets.php` — reemplazar headers:

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

**Verificación:** Hacer fetch desde `http://evil.com` al API debe fallar por CORS. Hacer fetch desde el mismo origen debe funcionar.

---

### 5. XSS en ticket-detalle.php y search.js

**Problema:**
- `app/Views/user/ticket-detalle.php:21`: `$ticketId = $_GET['id'] ?? null;` — se usa directamente sin sanitizar en línea 318: `<input type="hidden" id="ticket-id" value="<?= $ticketId ?>">`.
- `public/assets/js/search.js:202-214`: Los campos `result.title`, `result.meta`, `result.badge` se renderizan con `escapeHtml()` — esto está bien. Sin embargo, `result.url` y `result.icon` no se sanitizan y pueden contener XSS si el query malicioso logra inyectarlos.

**Archivos afectados:**
- `app/Views/user/ticket-detalle.php`
- `public/assets/js/search.js`

**Solución propuesta:**
- Sanitizar `$_GET['id']` con `filter_var` + validación de que sea numérico o UUID
- En search.js, sanitizar TODOS los campos incluyendo `url` e `icon`

**Código:**

#### ticket-detalle.php línea 21:

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

#### app/Views/user/ticket-detalle.php línea 318:

```php
<!-- Antes: -->
<input type="hidden" id="ticket-id" value="<?= $ticketId ?>">

<!-- Después: -->
<input type="hidden" id="ticket-id" value="<?= (int)$ticketId ?>">
```

#### search.js — displayResults con sanitización total:

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

**Verificación:** Intentar `ticket-detalle.php?id=<script>alert(1)</script>` debe redirigir o mostrar error. El campo `id` en el hidden input debe ser solo numérico.

---

### 6. SQL injection en search.php

**Problema:** `app/api/search.php:14` expone `$e->getMessage()` en el error JSON (línea 114). Si bien usa prepared statements (correcto), el mensaje de error de la excepción puede contener información sensible de la estructura de la BD.

**Archivos afectados:**
- `app/api/search.php`

**Solución propuesta:**
Nunca exponer `$e->getMessage()` al cliente. Usar mensajes genéricos y loguear el error real.

**Código:**

```php
// Línea 113-116: Reemplazar:
} catch (Exception $e) {
    echo json_encode(['error' => 'Error en la búsqueda', 'details' => $e->getMessage()]);
    exit;
}

// Con:
} catch (Exception $e) {
    error_log('Search error: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al procesar la búsqueda']);
    exit;
}
```

**Verificación:** Forzar un error de BD y verificar que el mensaje JSON sea genérico. Revisar `error_log` para ver el detalle real.

---

### 7. Error disclosure en APIs

**Problema:** Múltiples catch blocks en todas las APIs exponen `$e->getMessage()` directamente al cliente:
- `api/tickets.php:276`: `echo json_encode(['error' => $e->getMessage()]);`
- `api/usuarios.php:110,161,180,216,232,253,262`: todos exponen `$e->getMessage()`
- `api/search.php:114`: expone `$e->getMessage()` en `details`
- Varios más.

**Archivos afectados:**
- `app/api/tickets.php`
- `app/api/usuarios.php`
- `app/api/search.php`

**Solución propuesta:**
Crear un helper `json_error_safe()` que siempre loguee el error real y devuelva un mensaje genérico al cliente.

**Código del helper:**

```php
<?php
declare(strict_types=1);

namespace App\Helpers;

class JsonResponse
{
    /**
     * Envía una respuesta JSON de error sin exponer detalles internos.
     * El error real se loguea en error_log.
     */
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

    /**
     * Envía una respuesta JSON de éxito.
     */
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

#### Reemplazo en todos los catch blocks de todas las APIs:

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

**Verificación:** Provocar un error de BD y verificar que el mensaje JSON sea "Error interno del servidor" en lugar del mensaje real de PDO. Revisar error_log para confirmar que el error real se registró.

---

### 8. Bug telefono vs :phone en User.php:793

**Problema:** `app/Models/User.php:793` ejecuta:
```php
$stmt->execute(['user_id' => $userId, 'telefono' => $data['telefono']]);
```
Pero la consulta preparada espera los parámetros `:user_id` y `:phone` (no `:telefono`):
```sql
INSERT INTO oti.user_profiles (user_id, phone) VALUES (:user_id, :phone)
ON CONFLICT (user_id) DO UPDATE SET phone = :phone
```

El parámetro `'telefono'` es ignorado por PDO porque no coincide con ningún placeholder (`:telefono` no existe). La inserción/actualización del teléfono **nunca funciona**.

**Archivos afectados:**
- `app/Models/User.php`

**Solución propuesta:**
Cambiar `'telefono'` por `'phone'` para que coincida con el placeholder.

**Código del fix:**

```php
// Línea 793 — Antes:
$stmt->execute(['user_id' => $userId, 'telefono' => $data['telefono']]);

// Después:
$stmt->execute(['user_id' => $userId, 'phone' => $data['telefono']]);
```

**Verificación:** Actualizar el teléfono de un usuario desde el perfil, luego consultar `oti.user_profiles` directamente y verificar que el campo `phone` se actualizó.

---

### 9. SET NAMES 'UTF8' → PostgreSQL

**Problema:** `Database.php:31` usa sintaxis de MySQL:
```php
self::$pdo->exec("SET NAMES 'UTF8'");
```

PostgreSQL no reconoce `SET NAMES`. Debe ser:
```sql
SET client_encoding TO 'UTF8'
```

**Archivos afectados:**
- `app/Core/Database.php`

**Solución propuesta:**
Cambiar a la sintaxis correcta de PostgreSQL y usar `SET NAMES` solo si el driver es MySQL.

**Código del fix:**

```php
// Línea 31 — Antes:
self::$pdo->exec("SET NAMES 'UTF8'");

// Después (incluido en el código completo del punto 2):
self::$pdo->exec("SET client_encoding TO 'UTF8'");
```

**Verificación:** La conexión a PostgreSQL debe funcionar sin errores. Verificar que `SHOW client_encoding` devuelva UTF8.

---

## Prioridad ALTA

---

### 10. N+1 queries en getStats()

**Problema:** Tres modelos ejecutan consultas N+1:

| Modelo | Consultas separadas |
|--------|-------------------|
| `Ticket::getStats()` | 5 queries (total, abiertos, en_proceso, resueltos, cerrados) |
| `Location::getStats()` | 6 queries (total, direcciones, areas, oficinas, usuarios_asignados, equipos_asignados) |
| `Equipment::getStats()` | 5 queries (total, activos, mantenimiento, inactivos, retirados) |

Cada una ejecuta una consulta `SELECT COUNT(*)` independiente, causando 16 queries adicionales por página.

**Archivos afectados:**
- `app/Models/Ticket.php` (método `getStats`, líneas 341-386)
- `app/Models/Location.php` (método `getStats`, líneas 184-214)
- `app/Models/Equipment.php` (método `getStats`, líneas 95-117)

**Solución propuesta:**
Reescribir cada método con una sola consulta usando `COUNT(*) FILTER (WHERE ...)`.

**Código:**

#### Ticket::getStats() — UNA query:

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

    $sql = "SELECT
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE status_id = 1) as abiertos,
                COUNT(*) FILTER (WHERE status_id = 2) as en_proceso,
                COUNT(*) FILTER (WHERE status_id = 3) as resueltos,
                COUNT(*) FILTER (WHERE status_id = 4) as cerrados
            FROM oti.tickets {$where}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    return [
        'total' => (int)$row['total'],
        'abiertos' => (int)$row['abiertos'],
        'en_proceso' => (int)$row['en_proceso'],
        'resueltos' => (int)$row['resueltos'],
        'cerrados' => (int)$row['cerrados'],
    ];
}
```

#### Location::getStats() — UNA query:

```php
public static function getStats(): array
{
    $pdo = self::db();

    $stmt = $pdo->query("
        SELECT
            COUNT(*) FILTER (WHERE active = true) as total,
            COUNT(*) FILTER (WHERE type = 'DIRECCION' AND active = true) as direcciones,
            COUNT(*) FILTER (WHERE type = 'AREA' AND active = true) as areas,
            COUNT(*) FILTER (WHERE type = 'OFICINA' AND active = true) as oficinas
        FROM oti.locations
    ");
    $locationStats = $stmt->fetch(\PDO::FETCH_ASSOC);

    $stmt2 = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM oti.user_profiles WHERE location_id IS NOT NULL) as usuarios_asignados,
            (SELECT COUNT(*) FROM oti.equipment WHERE location_id IS NOT NULL AND is_deleted = false) as equipos_asignados
    ");
    $relationStats = $stmt2->fetch(\PDO::FETCH_ASSOC);

    return [
        'total' => (int)$locationStats['total'],
        'direcciones' => (int)$locationStats['direcciones'],
        'areas' => (int)$locationStats['areas'],
        'oficinas' => (int)$locationStats['oficinas'],
        'usuarios_asignados' => (int)$relationStats['usuarios_asignados'],
        'equipos_asignados' => (int)$relationStats['equipos_asignados']
    ];
}
```

#### Equipment::getStats() — UNA query:

```php
public static function getStats(): array
{
    $pdo = self::db();

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE status = 'active' AND is_deleted = false) as activos,
            COUNT(*) FILTER (WHERE status = 'maintenance' AND is_deleted = false) as mantenimiento,
            COUNT(*) FILTER (WHERE status = 'inactive' AND is_deleted = false) as inactivos,
            COUNT(*) FILTER (WHERE status = 'retired' AND is_deleted = false) as retirados
        FROM oti.equipment
        WHERE is_deleted = false
    ");
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    return [
        'total' => (int)$row['total'],
        'activos' => (int)$row['activos'],
        'mantenimiento' => (int)$row['mantenimiento'],
        'inactivos' => (int)$row['inactivos'],
        'retirados' => (int)$row['retirados'],
    ];
}
```

**Verificación:** Habilitar logging de queries en PostgreSQL y verificar que getStats() ejecute solo 1 query (Equipment, Ticket) o 2 queries (Location) en lugar de 5-6. Comparar resultados numéricos antes/después: deben ser idénticos.

---

### 11. Índices compuestos en BD

**Problema:** El sistema no tiene índices optimizados. Las consultas más frecuentes (`WHERE user_id`, `WHERE status_id`, `WHERE code`, búsquedas con `LIKE`) realizan sequential scans en tablas que crecerán con el tiempo.

**Archivos a crear:**
- `database/migrations/001_performance_indexes.sql`

**Solución propuesta:**
Crear índices compuestos para las consultas más comunes.

**Código SQL:**

```sql
-- ========================================
-- 001_performance_indexes.sql
-- Índices de rendimiento para OTI
-- ========================================

-- Tickets: filtrado por usuario + estado (dashboard de usuario)
CREATE INDEX IF NOT EXISTS idx_tickets_user_status
    ON oti.tickets (user_id, status_id, created_at DESC);

-- Tickets: filtrado por admin asignado + estado (dashboard de admin)
CREATE INDEX IF NOT EXISTS idx_tickets_assigned_status
    ON oti.tickets (assigned_admin_id, status_id, created_at DESC);

-- Tickets: búsqueda por código (más rápido que LIKE)
CREATE INDEX IF NOT EXISTS idx_tickets_code
    ON oti.tickets (code);

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

-- Usuarios: búsqueda por nombre
CREATE INDEX IF NOT EXISTS idx_usuarios_nombre
    ON admin.usuarios USING gin (nombre gin_trm_ops);

CREATE INDEX IF NOT EXISTS idx_usuarios_email
    ON admin.usuarios (email);

-- Ubicaciones: árbol de jerarquía (consultas parent_id recurrentes)
CREATE INDEX IF NOT EXISTS idx_locations_parent
    ON oti.locations (parent_id);

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

-- ========================================
-- NOTA: Requiere extensión pg_trgm para índices GIN trigram
-- ========================================
CREATE EXTENSION IF NOT EXISTS pg_trgm;
```

**Verificación:** Ejecutar `EXPLAIN ANALYZE SELECT ...` en las consultas objetivo y confirmar que usan los índices (Index Scan en lugar de Seq Scan).

---

### 12. SSE doble callback y error crash en realtime.js

**Problema 1 — Operador `||` vs `??` (línea 49):**
```javascript
if (useSSE && currentPage === 'admin-dashboard' || currentPage === 'user-dashboard') {
```
Por precedencia de operadores, esto se evalúa como:
```javascript
if ((useSSE && currentPage === 'admin-dashboard') || (currentPage === 'user-dashboard'))
```
Es decir, SIEMPRE entra en `initSSE()` cuando está en `user-dashboard`, incluso si `useSSE` es `false`. Esto rompe el fallback a polling.

**Problema 2 — JSON.parse en evento `error` (línea 95-98):**
```javascript
eventSource.addEventListener('error', function(event) {
    const data = JSON.parse(event.data); // event.data es null en errores SSE
    console.error('SSE error:', data);
});
```
El evento `error` de SSE no tiene `event.data`. Hacer `JSON.parse(null)` lanza una excepción que no se captura. La línea 100 `eventSource.onerror` sí maneja el error correctamente, pero el listener en línea 95 se ejecuta primero y CRASHA.

**Archivos afectados:**
- `public/assets/js/realtime.js`

**Solución propuesta:**
- Corregir la precedencia de operadores con paréntesis explícitos
- Eliminar el `addEventListener('error', ...)` que hace JSON.parse inválido (es redundante porque `onerror` ya maneja la reconexión)

**Código completo del fix:**

#### Línea 49 — Corregir precedencia:

```javascript
// Antes:
if (useSSE && currentPage === 'admin-dashboard' || currentPage === 'user-dashboard') {

// Después:
if (useSSE && (currentPage === 'admin-dashboard' || currentPage === 'user-dashboard')) {
```

#### Líneas 95-98 — Eliminar listener de error inválido:

```javascript
// Antes:
eventSource.addEventListener('error', function(event) {
    const data = JSON.parse(event.data);
    console.error('SSE error:', data);
});

// Después:
// (eliminar completamente este bloque — el error se maneja en eventSource.onerror)
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

**Verificación:**
1. Navegar a `user/dashboard` — debe iniciar SSE (no entrar en polling).
2. Navegar a `user/tickets` — debe usar polling (no SSE).
3. Desconectar el servidor SSE — debe caer gracefulmente a polling sin errores en consola.
4. El error `JSON.parse(null)` no debe aparecer nunca en la consola del navegador.

---

### 13. Canvas vs Context2D en analisis-charts.js:108

**Problema:** `initTicketsMensualChart` en `analisis-charts.js:108` hace:
```javascript
const ctx = document.getElementById('chart-tickets-mensual');
```
`ctx` es un `HTMLCanvasElement`. Luego en línea 114:
```javascript
const bgColor = createGradient(ctx, CHART_COLORS.primaryLight, CHART_COLORS.primary);
```
Y `createGradient()` (línea 486-498) intenta:
```javascript
const gradient = ctx.createLinearGradient(0, 0, 0, 300);
```
Pero `HTMLCanvasElement` no tiene método `createLinearGradient`. Ese método pertenece a `CanvasRenderingContext2D`. El gradiente **nunca funciona**, siempre cae al fallback `colorStart + '40'`.

**Archivos afectados:**
- `public/assets/js/analisis-charts.js`

**Solución propuesta:**
Obtener el contexto 2D del canvas antes de crear el gradiente. Además, hacer que el alto del canvas sea real (usar `ctx.height` en lugar del número mágico 300).

**Código completo del fix:**

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

#### En `initTicketsMensualChart`:

```javascript
function initTicketsMensualChart(data) {
    const canvas = document.getElementById('chart-tickets-mensual');
    if (!canvas) return;

    const labels = data.map(d => formatMonth(d.mes));
    const values = data.map(d => parseInt(d.count));

    const bgColor = createGradientFromCanvas('chart-tickets-mensual', CHART_COLORS.primaryLight, CHART_COLORS.primary);

    charts.ticketsMensual = new Chart(canvas, {
        type: 'line',
        // ... resto igual
    });
}
```

#### En `initUbicacionesChart`:

```javascript
const bgColor = createBarGradientFromCanvas('chart-ubicaciones', CHART_COLORS.warning);
```

#### En `initUsuariosChart`:

```javascript
const bgColor = createBarGradientFromCanvas('chart-usuarios', CHART_COLORS.info);
```

#### Reemplazar las funciones `createGradient` y `createBarGradient` originales (líneas 486-512) con versiones que no se usan más (dejar solo las nuevas funciones arriba, eliminar las viejas):

```javascript
// ELIMINAR las funciones viejas (líneas 486-512):
// function createGradient(ctx, colorStart, colorEnd) { ... }
// function createBarGradient(ctx, color) { ... }
```

**Verificación:** Abrir la página de análisis (`/admin/analisis`) y verificar que los gradientes en los charts de línea y barra se rendericen correctamente (no color sólido). Usar las DevTools para verificar que `createLinearGradient` se llame sin errores.

---

## Resumen de implementación

| # | Prioridad | Cambio | Archivos a modificar | Tipo |
|---|-----------|--------|---------------------|------|
| 1 | CRÍTICA | CSRF en todos los POST | index.php, vistas, APIs | PHP |
| 2 | CRÍTICA | Eliminar password hardcodeada | Database.php | PHP |
| 3 | CRÍTICA | Auth por rol real | AuthService.php, index.php, 19 vistas, APIs | PHP |
| 4 | CRÍTICA | CORS restrictivo | tickets.php, sse.php, nuevo Cors.php | PHP |
| 5 | CRÍTICA | XSS en ticket-detalle y search | ticket-detalle.php, search.js | PHP/JS |
| 6 | CRÍTICA | Error disclosure search.php | search.php | PHP |
| 7 | CRÍTICA | Error disclosure APIs | JsonResponse.php, 3 APIs | PHP |
| 8 | CRÍTICA | Bug telefono/phone | User.php | PHP |
| 9 | CRÍTICA | SET NAMES UTF8 | Database.php | PHP |
| 10 | ALTA | N+1 queries | Ticket.php, Location.php, Equipment.php | PHP/SQL |
| 11 | ALTA | Índices compuestos | migrations/001_performance_indexes.sql | SQL |
| 12 | ALTA | SSE doble callback | realtime.js | JS |
| 13 | ALTA | Canvas vs Context2D | analisis-charts.js | JS |

**Orden sugerido de implementación:**
1. Database.php (fixes 2, 9)
2. User.php (fix 8)
3. AuthService.php + index.php + vistas (fixes 1, 3)
4. Cors.php + APIs (fixes 4, 6, 7)
5. ticket-detalle.php + search.js (fix 5)
6. modelos (fix 10)
7. realtime.js + analisis-charts.js (fixes 12, 13)
8. migrations SQL (fix 11)
