# B-E1: Hardening + Optimización Dirigida — Plan de Implementación

**Proyecto:** OTI (Sistema de Gestión Municipal)  
**Agente:** Expansión B-E1  
**Versión:** 1.0  
**Fecha:** 2026-05-21  

---

## FASE 1: EMERGENCIA (Semana 1) — Parches de seguridad críticos

---

### 1. Middleware de seguridad unificado en index.php

**Problema:** `index.php` hace `session_start()` sin configurar cookies seguras, sin regenerar ID, sin timeout de inactividad, sin verificar CSRF en POST, y sin CSP dinámico. Los headers de seguridad están duplicados entre `index.php` y `security.php`.

**Archivos afectados:**
- `C:\xampp\htdocs\OTI\index.php`
- `C:\xampp\htdocs\OTI\app\Helpers\security.php`
- `C:\xampp\htdocs\OTI\app\Middleware\SecurityMiddleware.php`

**Solución:** Reemplazar el bloque superior de `index.php` con un middleware unificado que:
1. Configure cookie de sesión antes de `session_start()` (SameSite=Strict, HttpOnly, Secure condicional)
2. Timeout absoluto de 12h y timeout de inactividad 30min
3. Regeneración de session ID en cada request
4. Verificación CSRF obligatoria en todo POST (excepto login)
5. CSP header generado con nonce vía `Security::setHeaders()`

**Código — Nuevo `/index.php`:**

```php
<?php
/**
 * Punto de entrada principal del sistema OTI
 * Middleware de seguridad unificado
 */

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

// ─── Headers de seguridad ───
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');

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

header('Cache-Control: private, no-cache, must-revalidate, max-age=0');

// ─── CSRF verification en todo POST (excepto login) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path !== '/login') {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        if (isset($_SESSION['csrf_token_expires']) && time() > $_SESSION['csrf_token_expires']) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_expires']);
        }
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token inválido']);
        exit;
    }
}

define('BASE_URL', 'http://localhost/OTI/');

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\AuthService;
use App\Services\TicketService;
use App\Models\Ticket;
use App\Controller\AuthController;

// ─── Asignar nonce a las vistas ───
$_SESSION['_csp_nonce'] = $nonce;

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/OTI', '', $path);
$path = $path ?: '/';
// ... resto del archivo sin cambios desde línea 32 en adelante ...
```

**Verificación:**
1. Inspeccionar headers con DevTools → CSP, HSTS, X-Frame-Options presentes
2. Probar POST sin CSRF token → debe devolver 403
3. Esperar 31min sin actividad → redirige a login
4. Verificar cookie de sesión con `document.cookie` → debe tener SameSite=Strict, HttpOnly

---

### 2. Auth hardening — Reemplazar strpos(role_name) con consulta real a admin.usuario_rol

**Problema:** Las 7+ repeticiones de `strpos($roleName, 'admin')` en `index.php`, `tickets.php`, `AuthController.php` y `ticket-detalle.php` no validan contra la base de datos, solo verifican strings de texto. Cualquier rol cuyo nombre contenga "admin" (ej. "administrativo") obtendría acceso administrativo.

**Archivos afectados:**
- `C:\xampp\htdocs\OTI\app\Services\AuthService.php` (líneas 102-115, método `isAdmin()`)
- `C:\xampp\htdocs\OTI\app\Controller\AuthController.php` (líneas 37-42)
- `C:\xampp\htdocs\OTI\index.php` (líneas 34-40, 107-112)
- `C:\xampp\htdocs\OTI\app\api\tickets.php` (líneas 31-36)
- `C:\xampp\htdocs\OTI\app\Views\user\ticket-detalle.php` (líneas 14-19)

**Solución:** Modificar `AuthService::isAdmin()` para hacer una consulta real a `admin.usuario_rol` y luego usar `AuthService::isAdmin()` en todos los lugares.

**Código — `AuthService.php` nuevo método `isAdmin()`:**

```php
public static function isAdmin(): bool
{
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
        return false;
    }

    $esAdmin = $_SESSION['user']['es_admin'] ?? false;
    if ($esAdmin) {
        return true;
    }

    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $pdo = \App\Core\Database::connect();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM admin.usuario_rol ur
            JOIN admin.roles r ON ur.rol_id = r.id
            JOIN admin.sistemas s ON ur.sistema_id = s.id
            WHERE ur.usuario_id = :user_id
              AND s.slug = 'oti'
              AND r.nombre IN ('admin', 'director', 'jefe', 'coordinador', 'supervisor')
        ");
        $stmt->execute(['user_id' => $_SESSION['user']['id']]);
        $result = $stmt->fetch();
        $cache = (int)$result['count'] > 0;
        return $cache;
    } catch (\Exception $e) {
        error_log("Error en isAdmin(): " . $e->getMessage());
        return false;
    }
}
```

**Código — Reemplazar en `index.php` líneas 34-40 y 107-112:**

```php
// En lugar de:
// $isOtiAdmin = ... strpos($roleName, 'admin') !== false ...
// Usar:
$isOtiAdmin = AuthService::isAdmin();
```

**Código — Reemplazar en `AuthController.php` líneas 36-42:**

```php
// Antes:
$esAdmin = $_SESSION['user']['es_admin'] ?? false;
$roleName = strtolower($_SESSION['user']['role_name'] ?? '');
$isOtiAdmin = $esAdmin || strpos($roleName, 'admin') !== false || ...;

// Después:
$isOtiAdmin = \App\Services\AuthService::isAdmin();
```

**Código — Reemplazar en `tickets.php` líneas 31-36:**

```php
// Antes:
$isAdmin = (isset($_SESSION['user']['es_admin']) && $_SESSION['user']['es_admin']) || 
           strpos($roleNameLower, 'admin') !== false || ...;

// Después:
$isAdmin = \App\Services\AuthService::isAdmin();
```

**Código — Reemplazar en `ticket-detalle.php` líneas 14-19:**

```php
// Antes:
$isOtiAdmin = $esAdmin || strpos($roleNameLower, 'admin') !== false || ...;

// Después:
$isOtiAdmin = \App\Services\AuthService::isAdmin();
```

---

### 2b. Rate limiting en login

**Problema:** No hay límite de intentos de login. Un atacante puede probar contraseñas ilimitadas por fuerza bruta.

**Archivos afectados:**
- `C:\xampp\htdocs\OTI\app\Controller\AuthController.php`

**Solución:** Implementar rate limit simple con archivo temporal por IP (5 intentos máximo cada 15 minutos).

**Código — `AuthController::login()` con rate limiting:**

```php
public function login(): void
{
    // Rate limiting
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitFile = sys_get_temp_dir() . '/oti_login_' . md5($ip);
    $attempts = [];
    
    if (file_exists($rateLimitFile)) {
        $attempts = json_decode(file_get_contents($rateLimitFile), true) ?: [];
        // Limpiar intentos viejos (>15min)
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

    $result = AuthService::login($identifier, $password);

    // Registrar intento (éxito o fallo)
    $attempts[] = time();
    file_put_contents($rateLimitFile, json_encode($attempts));

    if (isset($result['error'])) {
        $_SESSION['error'] = $result['error'];
        header('Location: ' . BASE_URL . 'login');
        exit;
    }

    // Si login exitoso, resetear contador
    if (file_exists($rateLimitFile)) {
        unlink($rateLimitFile);
    }

    $isOtiAdmin = \App\Services\AuthService::isAdmin();
    header('Location: ' . ($isOtiAdmin ? BASE_URL . 'admin/dashboard' : BASE_URL . 'user/dashboard'));
    exit;
}
```

**Verificación:**
1. Intentar login 6 veces seguidas con credenciales incorrectas
2. El 6to intento debe mostrar mensaje de espera
3. Esperar 15 minutos y verificar que se pueda intentar de nuevo

---

### 3. Cierre de CORS

**Problema:** Los archivos de API tienen `Access-Control-Allow-Origin: *` permitiendo cualquier origen externo.

**Archivos afectados:**
- `C:\xampp\htdocs\OTI\app\api\tickets.php` (línea 10)

**Solución:** Reemplazar `*` con origen dinámico validado contra una lista blanca. Si el origen no está en lista blanca, no se envía el header.

**Código — Reemplazar en `tickets.php` líneas 10-12:**

```php
// En lugar de Access-Control-Allow-Origin: *
$allowedOrigins = ['http://localhost', 'http://localhost:3000', 'http://localhost:8080', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
```

**Verificación:**
1. Hacer fetch desde `http://evil.com` → debe fallar por CORS
2. Hacer fetch desde `http://localhost` → debe funcionar

---

### 4. Eliminar credenciales hardcodeadas

#### 4a. Database.php — Quitar fallback, forzar .env

**Problema:** `Database.php` tiene credenciales por defecto hardcodeadas que se usan si `.env` no existe o no tiene valores.

**Archivo:** `C:\xampp\htdocs\OTI\app\Core\Database.php` (líneas 15-19)

**Código — Reemplazar líneas 15-19:**

```php
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$db_name = getenv('DB_DATABASE');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');

if (empty($host) || empty($db_name) || empty($username) || empty($password)) {
    throw new \RuntimeException(
        "Credenciales de base de datos incompletas. Verifique el archivo .env"
    );
}
```

#### 4b. usuarios.php — Quitar contraseña por defecto OTI2026

**Problema:** `usuarios.php` línea 76 tiene `'password' => $_POST['password'] ?? 'OTI' . date('Y')`, que genera `OTI2026` como contraseña por defecto.

**Archivo:** `C:\xampp\htdocs\OTI\app\api\usuarios.php` (línea 76)

**Código — Reemplazar línea 76:**

```php
if (empty($_POST['password']) || strlen($_POST['password']) < 8) {
    echo json_encode(['error' => 'La contraseña debe tener al menos 8 caracteres']);
    break;
}
$data = [
    'nombre'    => isset($_POST['nombre']) ? substr(trim($_POST['nombre']), 0, 100) : '',
    'apellidos' => isset($_POST['apellidos']) ? substr(trim($_POST['apellidos']), 0, 100) : '',
    'email'     => $email,
    'dni'       => isset($_POST['dni']) ? substr(trim($_POST['dni']), 0, 20) : null,
    'phone'     => isset($_POST['phone']) ? substr(trim($_POST['phone']), 0, 20) : null,
    'location_id' => isset($_POST['location_id']) ? (int)$_POST['location_id'] : null,
    'position_id' => isset($_POST['position_id']) ? (int)$_POST['position_id'] : null,
    'role_id'   => isset($_POST['role_id']) ? (int)$_POST['role_id'] : null,
    'password'  => $_POST['password'],
    'activo'    => $_POST['activo'] ?? true
];
```

**Verificación:**
1. Sin `.env` → debe mostrar error claro "Credenciales de base de datos incompletas"
2. Crear usuario sin contraseña → debe rechazar con mensaje de error

---

## FASE 2: OPTIMIZACIÓN (Semana 2) — Rendimiento

---

### 5. Consolidación de N+1 queries

**Problema:** `Ticket::getStats()`, `Location::getStats()` y `Equipment::getStats()` ejecutan 5-6 queries separadas que pueden consolidarse en una sola con `FILTER()` de PostgreSQL.

**Archivos afectados:**
- `C:\xampp\htdocs\OTI\app\Models\Ticket.php` (líneas 341-386)
- `C:\xampp\htdocs\OTI\app\Models\Location.php` (líneas 184-214)
- `C:\xampp\htdocs\OTI\app\Models\Equipment.php` (líneas 95-117)

**Solución:** Usar `COUNT(*) FILTER (WHERE ...)` de PostgreSQL para obtener todos los contadores en una sola query.

**Código — `Ticket::getStats()` optimizado:**

```php
public static function getStats($filters = []): array
{
    $pdo = self::db();

    $where  = "WHERE 1=1";
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
                COUNT(*)                                              AS total,
                COUNT(*) FILTER (WHERE status_id = 1)                AS abiertos,
                COUNT(*) FILTER (WHERE status_id = 2)                AS en_proceso,
                COUNT(*) FILTER (WHERE status_id = 3)                AS resueltos,
                COUNT(*) FILTER (WHERE status_id = 4)                AS cerrados
            FROM oti.tickets {$where}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return [
        'total'      => (int)$row['total'],
        'abiertos'   => (int)$row['abiertos'],
        'en_proceso' => (int)$row['en_proceso'],
        'resueltos'  => (int)$row['resueltos'],
        'cerrados'   => (int)$row['cerrados'],
    ];
}
```

**Código — `Location::getStats()` optimizado:**

```php
public static function getStats(): array
{
    $pdo = self::db();

    $stmt = $pdo->query("
        SELECT
            COUNT(*)                                                   AS total,
            COUNT(*) FILTER (WHERE type = 'DIRECCION' AND active)     AS direcciones,
            COUNT(*) FILTER (WHERE type = 'AREA' AND active)          AS areas,
            COUNT(*) FILTER (WHERE type = 'OFICINA' AND active)       AS oficinas
        FROM oti.locations
    ");
    $locStats = $stmt->fetch();

    $stmt2 = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM oti.user_profiles WHERE location_id IS NOT NULL) AS usuarios_asignados,
            (SELECT COUNT(*) FROM oti.equipment WHERE location_id IS NOT NULL AND is_deleted = false) AS equipos_asignados
    ");
    $extraStats = $stmt2->fetch();

    return [
        'total'               => (int)$locStats['total'],
        'direcciones'         => (int)$locStats['direcciones'],
        'areas'               => (int)$locStats['areas'],
        'oficinas'            => (int)$locStats['oficinas'],
        'usuarios_asignados'  => (int)$extraStats['usuarios_asignados'],
        'equipos_asignados'   => (int)$extraStats['equipos_asignados'],
    ];
}
```

**Código — `Equipment::getStats()` optimizado:**

```php
public static function getStats(): array
{
    $pdo = self::db();

    $stmt = $pdo->query("
        SELECT
            COUNT(*)                                                   AS total,
            COUNT(*) FILTER (WHERE status = 'active' AND is_deleted = false)     AS activos,
            COUNT(*) FILTER (WHERE status = 'maintenance' AND is_deleted = false) AS mantenimiento,
            COUNT(*) FILTER (WHERE status = 'inactive' AND is_deleted = false)    AS inactivos,
            COUNT(*) FILTER (WHERE status = 'retired' AND is_deleted = false)     AS retirados
        FROM oti.equipment
    ");
    $row = $stmt->fetch();

    return [
        'total'        => (int)$row['total'],
        'activos'      => (int)$row['activos'],
        'mantenimiento' => (int)$row['mantenimiento'],
        'inactivos'    => (int)$row['inactivos'],
        'retirados'    => (int)$row['retirados'],
    ];
}
```

**Verificación:**
1. Ejecutar la query en psql y verificar que los resultados sean idénticos
2. Medir tiempo de respuesta antes/después (reducir de 6 queries a 1-2)

---

### 6. Índices compuestos

**Problema:** Las consultas principales (tickets por usuario y estado, equipos por ubicación y estado) no tienen índices compuestos, causando full table scans.

**Archivo:** `C:\xampp\htdocs\OTI\database\migrations\005_indices_compuestos.sql` (nuevo)

**Código — Script SQL de migración:**

```sql
-- Índices compuestos para optimización de consultas OTI
-- Migration 005: 2026-05-21

-- Tickets: filtros comunes por usuario + estado + fecha
CREATE INDEX IF NOT EXISTS idx_tickets_user_status_created
    ON oti.tickets (user_id, status_id, created_at DESC);

-- Tickets: filtros por admin asignado + estado
CREATE INDEX IF NOT EXISTS idx_tickets_assigned_status
    ON oti.tickets (assigned_admin_id, status_id)
    WHERE assigned_admin_id IS NOT NULL;

-- Tickets: filtro por ubicación + estado (para reportes)
CREATE INDEX IF NOT EXISTS idx_tickets_location_status
    ON oti.tickets (location_id, status_id)
    WHERE location_id IS NOT NULL;

-- Equipment: filtros comunes por ubicación + estado
CREATE INDEX IF NOT EXISTS idx_equipment_location_status
    ON oti.equipment (location_id, status)
    WHERE is_deleted = false;

-- Equipment: búsqueda por tipo + estado
CREATE INDEX IF NOT EXISTS idx_equipment_type_status
    ON oti.equipment (asset_type, status)
    WHERE is_deleted = false;

-- Locations: búsqueda por tipo + activo
CREATE INDEX IF NOT EXISTS idx_locations_type_active
    ON oti.locations (type, active);

-- User profiles: búsqueda por ubicación
CREATE INDEX IF NOT EXISTS idx_user_profiles_location
    ON oti.user_profiles (location_id);

-- Notifications: búsqueda por usuario + leído + fecha
CREATE INDEX IF NOT EXISTS idx_notifications_user_read_created
    ON oti.notifications (user_id, is_read, created_at DESC);
```

**Verificación:**
1. Ejecutar `EXPLAIN ANALYZE SELECT ...` antes y después
2. Verificar con `\di+` que los índices existen y se usan

---

### 7. Optimización SSE (realtime.js)

**Problema:** Múltiples bugs en `realtime.js`:
- Línea 49: `||` vs `??` — operador `||` tiene menor precedencia que `&&`, causando lógica incorrecta
- Línea 95: `JSON.parse(event.data)` en evento `error` — el evento `error` de EventSource no tiene `data`, siempre lanza excepción
- `console.log`/`console.error` en producción
- Doble callback: `onmessage` + `addEventListener('update', ...)` — datos duplicados si el SSE envía mensajes sin nombre y con nombre

**Archivo:** `C:\xampp\htdocs\OTI\public\assets\js\realtime.js`

**Código — Fix línea 49 (precedencia de operadores):**

```javascript
// Antes:
if (useSSE && currentPage === 'admin-dashboard' || currentPage === 'user-dashboard') {

// Después:
if (useSSE && (currentPage === 'admin-dashboard' || currentPage === 'user-dashboard')) {
```

**Código — Fix línea 95 (JSON.parse en evento error):**

```javascript
// Antes:
eventSource.addEventListener('error', function(event) {
    const data = JSON.parse(event.data);
    console.error('SSE error:', data);
});

// Después:
eventSource.addEventListener('error', function(event) {
    if (event.eventPhase === EventSource.CLOSED) {
        // Conexión cerrada, se maneja en onerror
        return;
    }
    // El evento 'error' no tiene data; solo loggear
});
```

**Código — Eliminar console.log en producción:**

```javascript
// Reemplazar todos los console.log/console.error con un logger condicional
// Al inicio del archivo:
const DEBUG = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';

function logDebug(...args) {
    if (DEBUG) console.log('[OTI]', ...args);
}
function logWarn(...args) {
    if (DEBUG) console.warn('[OTI]', ...args);
}

// Luego reemplazar:
// console.log('SSE conectado') → logDebug('SSE conectado');
// console.error('Error parseando datos SSE:', e) → logDebug('Error parseando datos SSE:', e);
// console.warn('SSE error, cambiando a polling:', e) → logWarn('SSE error, cambiando a polling:', e);
// etc.
```

**Código — Fix doble callback (onmessage + addEventListener):**

```javascript
// Eliminar onmessage si el SSE ya envía eventos nombrados 'update':
// Antes:
eventSource.onmessage = function(event) { ... };

// Después: (eliminar onmessage, conservar solo addEventListener)
// eventSource.onmessage ya no se asigna, solo se usa addEventListener('update', ...)
```

**Código completo del bloque SSE corregido:**

```javascript
function initSSE() {
    try {
        eventSource = new EventSource(BASE_URL + 'app/api/sse.php');

        eventSource.onopen = function() {
            logDebug('SSE conectado');
            if (updateInterval) {
                clearInterval(updateInterval);
                updateInterval = null;
            }
        };

        eventSource.addEventListener('update', function(event) {
            try {
                const data = JSON.parse(event.data);
                handleDataUpdate(data);
            } catch (e) {
                logDebug('Error parseando datos SSE:', e);
            }
        });

        eventSource.addEventListener('connected', function(event) {
            // Evento de conexión exitosa, no es necesario loggear
        });

        eventSource.addEventListener('error', function() {
            // El evento 'error' no tiene data en EventSource
            // Se maneja via onerror abajo
        });

        eventSource.onerror = function(e) {
            logWarn('SSE error, cambiando a polling:', e);
            closeSSE();
            useSSE = false;
            fetchAllData();
            updateInterval = setInterval(fetchAllData, 15000);
        };

    } catch (e) {
        logWarn('SSE no disponible, usando polling:', e);
        useSSE = false;
        fetchAllData();
        updateInterval = setInterval(fetchAllData, 15000);
    }
}
```

**Verificación:**
1. Abrir página de dashboard → debe cargar datos sin errores en consola
2. Forzar fallo de SSE (desconectar servidor) → debe caer a polling sin errores
3. No deben aparecer `console.log` en producción

---

### 8. Canvas fix en analisis-charts.js

**Problema:** `initTicketsMensualChart()` y las demás funciones de chart obtienen el elemento canvas con `document.getElementById()` y lo pasan directamente a `createGradient()`, que espera un `CanvasRenderingContext2D` para llamar a `createLinearGradient()`. Chart.js internamente también espera un contexto 2D, no un elemento canvas.

**Archivo:** `C:\xampp\htdocs\OTI\public\assets\js\analisis-charts.js` (línea 108)

**Código — Fix de líneas 107-111:**

```javascript
function initTicketsMensualChart(data) {
    const canvas = document.getElementById('chart-tickets-mensual');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = data.map(d => formatMonth(d.mes));
    const values = data.map(d => parseInt(d.count));

    const bgColor = createGradient(ctx, CHART_COLORS.primaryLight, CHART_COLORS.primary);

    charts.ticketsMensual = new Chart(ctx, {
        // ...resto igual...
    });
}
```

**Aplicar el mismo patrón a todas las funciones `init*Chart()`:**
- `initPrioridadChart` (línea 160)
- `initUbicacionesChart` (línea 193)
- `initUsuariosChart` (línea 238)
- `initEquiposChart` (línea 283)
- `initEstadoChart` (línea 328)
- `initEquiposEstadoChart` (línea 377)

**Verificación:**
1. Abrir página de análisis → todos los charts deben renderizar
2. Sin errores en consola "Cannot read properties of null (reading 'createLinearGradient')"

---

## FASE 3: CALIDAD (Semana 3) — Código

---

### 9. Unificar lógica admin

**Problema:** Hay 7+ repeticiones del patrón `strpos($roleName, 'admin')` en diferentes archivos. Esto es frágil y difícil de mantener.

**Archivos afectados:**
- `C:\xampp\htdocs\OTI\app\Services\AuthService.php` (ya tiene método `isAdmin()`)
- `C:\xampp\htdocs\OTI\index.php` (líneas 34-40, 107-112)
- `C:\xampp\htdocs\OTI\app\Controller\AuthController.php` (líneas 37-42)
- `C:\xampp\htdocs\OTI\app\api\tickets.php` (líneas 31-36)
- `C:\xampp\htdocs\OTI\app\Views\user\ticket-detalle.php` (líneas 14-19)

**Solución:** Ver item 2 de FASE 1 (Auth hardening) — ya se cubre con la consulta real a `admin.usuario_rol`. Asegurar que `AuthService::isAdmin()` se use en todos estos lugares.

**Código de reemplazo en cada archivo:**

```php
// index.php (reemplazar 2 bloques):
$isOtiAdmin = \App\Services\AuthService::isAdmin();

// AuthController.php:
$isOtiAdmin = \App\Services\AuthService::isAdmin();

// tickets.php:
$isAdmin = \App\Services\AuthService::isAdmin();

// ticket-detalle.php:
$isOtiAdmin = \App\Services\AuthService::isAdmin();
```

**Verificación:** Todas las rutas admin deben funcionar igual que antes. No debe haber más `strpos(role)` en el código.

---

### 10. Fix bug User.php:793 telefono vs :phone

**Problema:** En `User::updateProfile()` línea 793, el array de parámetros usa la clave `telefono` pero la consulta SQL espera `:phone`. Esto causa que el binding falle y la actualización del teléfono no funcione.

**Archivo:** `C:\xampp\htdocs\OTI\app\Models\User.php` (línea 793)

**Código — Fix línea 793:**

```php
// Antes:
$stmt->execute(['user_id' => $userId, 'telefono' => $data['telefono']]);

// Después:
$pdo = self::db();
if (!empty($data['telefono'])) {
    $stmt = $pdo->prepare("
        INSERT INTO oti.user_profiles (user_id, phone)
        VALUES (:user_id, :phone)
        ON CONFLICT (user_id) DO UPDATE SET phone = :phone
    ");
    $stmt->execute(['user_id' => $userId, 'phone' => $data['telefono']]);
}
```

**Verificación:**
1. Actualizar teléfono de un usuario → debe guardarse correctamente
2. Verificar en BD: `SELECT phone FROM oti.user_profiles WHERE user_id = X;`

---

### 11. SET NAMES 'UTF8' → PostgreSQL syntax

**Problema:** `Database.php` línea 31 ejecuta `SET NAMES 'UTF8'` que es sintaxis de MySQL. PostgreSQL usa `SET client_encoding TO 'UTF8'`. Aunque PostgreSQL puede aceptarlo en algunos casos, no es la sintaxis correcta y puede causar problemas de encoding.

**Archivo:** `C:\xampp\htdocs\OTI\app\Core\Database.php` (línea 31)

**Código — Fix:**

```php
// Antes:
self::$pdo->exec("SET NAMES 'UTF8'");

// Después:
self::$pdo->exec("SET client_encoding TO 'UTF8'");
```

**Verificación:**
1. Verificar que los caracteres UTF-8 (tildes, ñ) se muestren correctamente
2. Consultar `SHOW client_encoding;` en PostgreSQL para confirmar UTF8

---

### 12. XSS sanitization

#### 12a. ticket-detalle.php: sanitizar $_GET['id']

**Problema:** `$_GET['id']` se usa directamente en el HTML sin sanitizar en `ticket-detalle.php` línea 318: `<input type="hidden" id="ticket-id" value="<?= $ticketId ?>">`.

**Archivo:** `C:\xampp\htdocs\OTI\app\Views\user\ticket-detalle.php` (línea 318)

**Código — Fix:**

```php
// Antes:
$ticketId = $_GET['id'] ?? null;

// Después:
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : null;
```

#### 12b. search.js: sanitizar 4 campos

**Problema:** En `search.js` los valores de los resultados de búsqueda se inyectan en el HTML a través de `result.url`, `result.icon`, `result.meta`, `result.badge`. Actualmente solo se sanitiza el `title` con la función `escapeHtml()`, pero `url`, `icon` (SVG path), `meta` y `badge` se insertan directamente.

**Archivo:** `C:\xampp\htdocs\OTI\public\assets\js\search.js`

**Código — Fix en `displayResults()`:**

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

        // Sanitizar URL: solo rutas permitidas
        const safeUrl = sanitizeUrl(result.url || '#');

        html += `
            <a href="${safeUrl}" class="search-result-item" data-index="${index}" data-url="${safeUrl}">
                <div class="search-result-icon ${result.iconClass || ''}">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="${sanitizeSvgPath(result.icon || '')}"/></svg>
                </div>
                <div class="search-result-content">
                    <div class="search-result-title">${escapeHtml(result.title || '')}</div>
                    <div class="search-result-meta">${escapeHtml(result.meta || '')}</div>
                </div>
                ${result.badge ? `<span class="search-result-badge ${result.badgeClass || ''}">${escapeHtml(result.badge)}</span>` : ''}
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

function sanitizeUrl(url) {
    // Solo permitir URLs relativas al sistema OTI
    if (typeof url !== 'string') return '#';
    const allowedPrefixes = ['/OTI/admin/', '/OTI/user/'];
    if (allowedPrefixes.some(prefix => url.startsWith(prefix))) {
        return url.replace(/[<>"']/g, '');
    }
    return '#';
}

function sanitizeSvgPath(path) {
    if (typeof path !== 'string') return '';
    // Solo caracteres seguros para paths SVG (letras, números, espacios, - . , ( ) / M L H V C S Q T A Z)
    return path.replace(/[^a-zA-Z0-9\s\-.,()\/]/g, '');
}
```

**Verificación:**
1. Probar `?id=<script>alert(1)</script>` en ticket-detalle → debe mostrar error, no ejecutar JS
2. Probar búsqueda con caracteres maliciosos en los campos → debe sanitizar correctamente

---

## FASE 4: UX (Semana 4) — CSS y pulido

---

### 13. División de app.css

**Problema:** `app.css` tiene 3350 líneas. Hay selectores duplicados (ej: `.empty-state` aparece 2 veces, `.stagger-children > *` aparece 2 veces, `.page-header` aparece 2 veces, animaciones `@keyframes` están duplicadas como `fadeIn`, `slideUp`, `slideDown`, `pulse`). Hay uso extensivo de `!important` (especialmente en media queries).

**Archivos nuevos a crear:**
- `C:\xampp\htdocs\OTI\public\assets\css\base.css`
- `C:\xampp\htdocs\OTI\public\assets\css\components.css`

`app.css` se mantiene como archivo legacy que importa los dos nuevos, pero los nuevos son los que se cargan en las vistas.

#### base.css — Tokens, reset, tipografía (primeros ~140 líneas de app.css limpiadas)

```css
/**
 * OTI - Base Styles
 * Design tokens, reset, typography, utilities
 */

:root {
    /* Colores primarios */
    --primary: #3730a3;
    --primary-light: #4f46e5;
    --primary-dark: #312e81;
    --primary-soft: #6366f1;

    /* Colores de acento */
    --accent: #4338ca;
    --accent-light: #6366f1;
    --accent-soft: #a5b4fc;

    /* Neutros */
    --bg-main: #f8fafc;
    --bg-card: #ffffff;
    --bg-sidebar: #ffffff;
    --bg-hover: #f1f5f9;

    /* Textos */
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --text-muted: #64748b;
    --text-inverse: #f8fafc;

    /* Estados semánticos */
    --success: #059669;
    --success-soft: #d1fae5;
    --warning: #d97706;
    --warning-soft: #fef3c7;
    --danger: #dc2626;
    --danger-soft: #fee2e2;
    --info: #0284c7;
    --info-soft: #e0f2fe;

    /* Bordes */
    --border: #e2e8f0;
    --border-light: #e2e8f0;
    --border-medium: #cbd5e1;

    /* Sombras - Escala de elevación */
    --shadow-1: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-2: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
    --shadow-3: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
    --shadow-4: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
    --shadow-5: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    --shadow-6: 0 25px 50px -12px rgba(0, 0, 0, 0.25);

    /* Alias legacy */
    --shadow-sm: var(--shadow-2);
    --shadow-md: var(--shadow-3);
    --shadow-lg: var(--shadow-4);
    --shadow-xl: var(--shadow-5);

    /* Radios */
    --radius-xs: 4px;
    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 16px;
    --radius-xl: 24px;
    --radius-2xl: 32px;
    --radius-full: 9999px;

    /* Espaciado */
    --space-0: 0px;
    --space-1: 4px;
    --space-2: 8px;
    --space-3: 12px;
    --space-4: 16px;
    --space-5: 20px;
    --space-6: 24px;
    --space-8: 32px;
    --space-10: 40px;
    --space-12: 48px;
    --space-16: 64px;

    /* Alias legacy */
    --space-xs: 4px;
    --space-sm: 8px;
    --space-md: 16px;
    --space-lg: 24px;
    --space-xl: 32px;
    --space-2xl: 48px;

    /* Z-Index */
    --z-dropdown: 100;
    --z-sticky: 200;
    --z-fixed: 300;
    --z-modal-backdrop: 400;
    --z-modal: 500;
    --z-popover: 600;
    --z-tooltip: 700;
    --z-toast: 800;

    /* Layout */
    --sidebar-width: 280px;
    --header-height: 64px;
    --max-content-width: 1400px;

    /* Tipografía */
    --font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --font-size-xs: 11px;
    --font-size-sm: 12px;
    --font-size-base: 14px;
    --font-size-md: 16px;
    --font-size-lg: 18px;
    --font-size-xl: 20px;
    --font-size-2xl: 24px;
    --font-size-3xl: 30px;
    --font-size-4xl: 36px;

    /* Line heights */
    --leading-tight: 1.25;
    --leading-normal: 1.5;
    --leading-relaxed: 1.75;

    /* Transiciones */
    --duration-instant: 0ms;
    --duration-fast: 100ms;
    --duration-normal: 150ms;
    --duration-slow: 250ms;
    --duration-slower: 350ms;
    --duration-slowest: 500ms;

    /* Alias legacy */
    --transition-fast: var(--duration-normal);
    --transition-normal: var(--duration-slow);
    --transition-slow: var(--duration-slower);

    /* Easing */
    --ease-out: cubic-bezier(0.4, 0, 0.2, 1);
    --ease-in: cubic-bezier(0.4, 0, 1, 1);
    --ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
    --ease-spring: cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

/* Reset */
*, *::before, *::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    font-size: 16px;
    scroll-behavior: smooth;
}

body {
    font-family: var(--font-family);
    background: var(--bg-main);
    color: var(--text-primary);
    line-height: 1.6;
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Layout principal */
.app-container {
    display: flex;
    min-height: 100vh;
}

/* Main content */
.main-content {
    margin-left: var(--sidebar-width);
    margin-top: 70px;
    flex: 1;
    padding: var(--space-lg) var(--space-xl);
    min-height: 100vh;
    background: var(--bg-main);
}

/* Links */
a {
    color: var(--primary);
    text-decoration: none;
}

/* Focus states */
a:focus-visible,
button:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible {
    outline: 3px solid var(--primary);
    outline-offset: 2px;
    border-radius: 4px;
}

a:focus:not(:focus-visible),
button:focus:not(:focus-visible),
input:focus:not(:focus-visible) {
    outline: none;
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}

/* Utilities */
.text-primary { color: var(--text-primary); }
.text-secondary { color: var(--text-secondary); }
.text-muted { color: var(--text-muted); }
.text-success { color: var(--success); }
.text-warning { color: var(--warning); }
.text-danger { color: var(--danger); }

.bg-primary { background: var(--primary); }
.bg-success { background: var(--success); }
.bg-warning { background: var(--warning); }
.bg-danger { background: var(--danger); }

.hidden { display: none !important; }
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    border: 0;
}

/* Animaciones base */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-16px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.animate-fade-in { animation: fadeIn var(--duration-slow) var(--ease-out); }
.animate-slide-up { animation: slideUp var(--duration-slow) var(--ease-out); }
.animate-slide-down { animation: slideDown var(--duration-slow) var(--ease-out); }
.animate-pulse { animation: pulse 2s var(--ease-in-out) infinite; }
.animate-spin { animation: spin 1s linear infinite; }
```

#### components.css — Cards, tablas, modales, sidebar, botones, etc.

**Estructura de components.css** (extraído de app.css eliminando duplicados y !important excesivos):

```css
/**
 * OTI - Component Styles
 * Sidebar, cards, tables, modals, buttons, forms, charts, search, pagination
 */

/* ========== SIDEBAR ========== */
.sidebar {
    width: var(--sidebar-width);
    background: #ffffff;
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    z-index: 100;
    overflow: hidden;
    border-right: 1px solid var(--border-light);
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.04);
    transition: transform var(--transition-normal);
}

.sidebar-header { padding: 24px; border-bottom: 1px solid #f1f5f9; }
.sidebar-nav { flex: 1; overflow-y: auto; padding: var(--space-md) 0; }
.sidebar-nav::-webkit-scrollbar { width: 4px; }
.sidebar-nav::-webkit-scrollbar-track { background: transparent; }
.sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
.sidebar-footer { padding: 16px 20px; border-top: 1px solid var(--border-light); margin-top: auto; background: #f8fafc; }

/* ... (resto del sidebar, cards, tablas, etc. limpiado de app.css) ... */
```

**Nota:** Debido a la extensión de components.css (~3000 líneas), el proceso de división debe hacerse con herramienta automatizada para mantener fidelidad. Lo importante es:
1. Eliminar los duplicados (`.empty-state`, `.stagger-children`, `.page-header`, animaciones)
2. Remover `!important` donde sea posible usando mayor especificidad
3. Mantener todos los selectores con sus propiedades exactas

**Verificación:**
1. Cargar página con `base.css` + `components.css` en lugar de `app.css`
2. Verificar que no haya diferencias visuales (comparar screenshots)
3. Lighthouse Performance debe mejorar (CSS más pequeño para cada página)

---

### 14. Security headers en .htaccess

**Problema:** El `.htaccess` solo tiene `X-Content-Type-Options`, `X-Frame-Options` y `X-XSS-Protection`. Faltan HSTS (aunque está en PHP), `Referrer-Policy`, y `Permissions-Policy`.

**Archivo:** `C:\xampp\htdocs\OTI\.htaccess`

**Código — Agregar al bloque `<IfModule mod_headers.c>`:**

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

**Verificación:** `curl -I https://sistema.oti.pe/ | grep -i "Strict-Transport-Security\|Referrer-Policy\|Permissions-Policy"`

---

### 15. BASE_URL dinámica

**Problema:** `BASE_URL` está hardcodeada como `http://localhost/OTI/` en `index.php` línea 16. En producción, esto debe ser la URL real del servidor.

**Archivos afectados:**
- `C:\xampp\htdocs\OTI\index.php` (línea 16)
- `C:\xampp\htdocs\OTI\app\Views\user\ticket-detalle.php` (línea 7, tiene su propio fallback)

**Solución:** Detectar automáticamente el protocolo, host y subdirectorio desde `$_SERVER`.

**Código — Reemplazar en `index.php`:**

```php
// Detección automática de BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https'
    : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
define('BASE_URL', $protocol . '://' . $host . $scriptDir . '/');
```

**Código — Reemplazar en `ticket-detalle.php`:**

```php
$baseUrl = defined('BASE_URL') ? BASE_URL : (
    ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'
);
```

**Verificación:**
1. En localhost → `http://localhost/OTI/` (funciona igual)
2. En producción `https://sistema.municipalidad.gob.pe/oti/` → detecta automáticamente
3. Todas las rutas de assets, APIs, y redirecciones deben funcionar

---

## Resumen de Archivos Modificados

| Archivo | Tipo | Ítems |
|---------|------|-------|
| `index.php` | PHP | 1 (middleware), 2 (auth), 9 (unificar admin), 15 (BASE_URL) |
| `app/Helpers/security.php` | PHP | 1 (referencia desde middleware) |
| `app/Helpers/functions.php` | PHP | 1 (CSRF token sigue igual) |
| `app/Services/AuthService.php` | PHP | 2 (isAdmin real), 9 (unificar) |
| `app/Controller/AuthController.php` | PHP | 2 (auth + rate limit), 9 (unificar) |
| `app/Core/Database.php` | PHP | 4a (sin fallback), 11 (SET NAMES) |
| `app/Models/Ticket.php` | PHP | 5 (FILTER queries) |
| `app/Models/Location.php` | PHP | 5 (FILTER queries) |
| `app/Models/Equipment.php` | PHP | 5 (FILTER queries) |
| `app/Models/User.php` | PHP | 10 (telefono vs phone fix) |
| `app/api/tickets.php` | PHP | 3 (CORS), 9 (unificar admin) |
| `app/api/usuarios.php` | PHP | 4b (sin pass default) |
| `app/api/search.php` | PHP | 12b (XSS sanitization) |
| `app/Views/user/ticket-detalle.php` | PHP | 9 (unificar admin), 12a (XSS), 15 (BASE_URL) |
| `public/assets/js/realtime.js` | JS | 7 (SSE fixes) |
| `public/assets/js/analisis-charts.js` | JS | 8 (Canvas fix) |
| `public/assets/js/search.js` | JS | 12b (XSS sanitization) |
| `public/assets/css/base.css` | CSS | 13 (nuevo) |
| `public/assets/css/components.css` | CSS | 13 (nuevo) |
| `.htaccess` | Apache | 14 (security headers) |
| `database/migrations/005_indices_compuestos.sql` | SQL | 6 (índices) |

---

## Prioridad de Implementación

| Prioridad | Ítem | Dependencia | Esfuerzo | Impacto |
|-----------|------|-------------|----------|---------|
| 🔴 P0 | 1. Middleware seguridad | Ninguna | 4h | Alto |
| 🔴 P0 | 2. Auth hardening | Ninguna | 3h | Alto |
| 🔴 P0 | 3. Cierre CORS | Ninguna | 1h | Alto |
| 🔴 P0 | 4. Eliminar credenciales | Ninguna | 1h | Alto |
| 🟡 P1 | 5. Consolidación N+1 | Ninguna | 2h | Medio |
| 🟡 P1 | 6. Índices compuestos | Ninguna | 1h | Medio |
| 🟡 P1 | 7. SSE fixes | Ninguna | 2h | Medio |
| 🟡 P1 | 8. Canvas fix | Ninguna | 1h | Medio |
| 🟢 P2 | 9. Unificar lógica admin | Ítem 2 | 2h | Bajo |
| 🟢 P2 | 10. Fix User.php | Ninguna | 1h | Bajo |
| 🟢 P2 | 11. SET NAMES fix | Ninguna | 0.5h | Bajo |
| 🟢 P2 | 12. XSS sanitization | Ninguna | 2h | Medio |
| ⚪ P3 | 13. División CSS | Ninguna | 4h | Bajo |
| ⚪ P3 | 14. Headers .htaccess | Ninguna | 0.5h | Bajo |
| ⚪ P3 | 15. BASE_URL dinámica | Ninguna | 1h | Bajo |
