# Expansión A-E1: Unificación de Canales en Tiempo Real + Cache Condicional

**Propuesta original:** A-E1
**Puntaje pruning:** 88.00
**Complejidad:** Baja-Media
**Tiempo estimado:** 5-7 días

## Arquitectura de la Solución

```
┌─────────────────────────────────────────────────────────────────────┐
│                        NAVE GADOR (antes)                            │
│                                                                     │
│  realtime.js ──setInterval(15s)──▶ stats.php ──6 queries──▶ PostgreSQL │
│  sse.php     ──while(true) 5s───▶ getStats()──6 queries──▶ PostgreSQL │
│  analisis-charts ──setInterval(10s)─▶ stats.php ──6 queries──▶ PostgreSQL│
│                                                                     │
│  ~~1,320 queries/min con 20 admins~~                                │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                       DESPUÉS (con A-E1)                             │
│                                                                     │
│  sse.php ──while(5s)──▶ cache_heartbeat(1 query, 0 bytes)           │
│     │                                                               │
│     │ si heartbeat cambió:                                           │
│     └──▶ dashboard-poll.php ──(6 queries, pero solo cuando cambia)   │
│     └──▶ SSE event ──▶ SSEClient ──▶ realtime.js (eventos)           │
│                                   └─▶ analisis-charts (eventos)      │
│                                                                     │
│  ~~~30 queries/min (heartbeat 12/min + cambios esporádicos)~~       │
│  ~~~97.7% menos de carga en BD~~~                                    │
└─────────────────────────────────────────────────────────────────────┘
```

### Flujo detallado

```
1. SSE abre conexión, consulta heartbeat cada 5s
2. Si heartbeat no cambió → envía ": heartbeat\n\n" (comentario SSE)
3. Si heartbeat cambió → ejecuta dashboard-poll, envía "event: dashboard_update"
4. SSEClient.js recibe el evento, lo despacha a suscriptores
5. realtime.js recibe 'dashboard:update' → updateUI(data)
6. analisis-charts.js recibe 'dashboard:update' → updateChartsIfChanged(data)
7. polling de realtime.js y analisis-charts.js se eliminan por completo
```

## Componentes a Implementar

### 1. Endpoint Unificado `/api/v1/dashboard-poll`

**Archivo nuevo:** `app/api/v1/dashboard-poll.php`

```php
<?php
/**
 * API Unificada de Dashboard con ETag/304
 * Sistema OTI - Reemplaza stats.php como único endpoint de polling
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, If-None-Match');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Core\Database;
use App\Services\AuthService;

$isAdmin = AuthService::isAdmin();
$userId = $_SESSION['user']['id'] ?? null;

$channel = $_GET['channel'] ?? 'stats';

try {
    $pdo = Database::connect();
    $response = [];

    if ($isAdmin) {
        // Stats generales (1 query con FILTER)
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE status_id = 1) as abiertos,
                COUNT(*) FILTER (WHERE status_id = 2) as en_proceso,
                COUNT(*) FILTER (WHERE status_id = 3) as resueltos,
                COUNT(*) FILTER (WHERE status_id = 4) as cerrados
            FROM oti.tickets
        ");
        $response['stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Tickets por prioridad
        $stmt = $pdo->query("
            SELECT tp.name, COUNT(t.id) as count,
            CASE tp.id
                WHEN 1 THEN '#dc2626'
                WHEN 2 THEN '#f59e0b'
                WHEN 3 THEN '#10b981'
                ELSE '#6366f1'
            END as color
            FROM oti.tickets t
            JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
            GROUP BY tp.name, tp.id
            ORDER BY tp.id
        ");
        $response['por_prioridad'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tickets por estado
        $stmt = $pdo->query("
            SELECT ts.name, COUNT(t.id) as count
            FROM oti.tickets t
            JOIN oti.ticket_statuses ts ON t.status_id = ts.id
            GROUP BY ts.name, ts.id
            ORDER BY ts.id
        ");
        $response['por_estado'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tickets recientes
        $stmt = $pdo->query("
            SELECT t.id, t.code, t.title, t.created_at, 
                   ts.name as status_name, tp.name as priority_name
            FROM oti.tickets t
            JOIN oti.ticket_statuses ts ON t.status_id = ts.id
            JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
            ORDER BY t.created_at DESC
            LIMIT 10
        ");
        $response['tickets_recientes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Stats de equipos
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE status = 'active') as activos,
                COUNT(*) FILTER (WHERE status = 'maintenance') as mantenimiento,
                COUNT(*) FILTER (WHERE status = 'inactive') as inactivos
            FROM oti.equipment
            WHERE is_deleted = false
        ");
        $response['equipos'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Stats de usuarios
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE activo = true) as activos
            FROM admin.usuarios
        ");
        $response['usuarios'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Actividad reciente
        $stmt = $pdo->query("
            SELECT * FROM (
                SELECT 
                    'ticket' as tipo,
                    t.id, t.code as codigo, t.title as titulo,
                    ts.name as status_name,
                    CASE ts.id
                        WHEN 1 THEN 'ticket-open'
                        WHEN 2 THEN 'ticket-process'
                        WHEN 3 THEN 'ticket-resolved'
                        ELSE 'ticket'
                    END as status_class,
                    CASE ts.id
                        WHEN 1 THEN 'Abierto'
                        WHEN 2 THEN 'En Proceso'
                        WHEN 3 THEN 'Resuelto'
                        ELSE ts.name
                    END as badge,
                    t.created_at as fecha,
                    (u.nombre || ' ' || COALESCE(u.apellidos, '')) as usuario,
                    t.description as descripcion
                FROM oti.tickets t
                JOIN oti.ticket_statuses ts ON t.status_id = ts.id
                LEFT JOIN admin.usuarios u ON t.user_id = u.id
                ORDER BY t.created_at DESC
                LIMIT 8
            ) sub
            ORDER BY sub.fecha DESC
        ");
        $rawActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rawActivity as &$item) {
            $item['tiempo'] = timeAgo($item['fecha']);
        }
        unset($item);
        $response['actividad_reciente'] = $rawActivity;

        // Tickets por ubicación
        $stmt = $pdo->query("
            SELECT l.name, COUNT(t.id) as count
            FROM oti.tickets t
            JOIN oti.locations l ON t.location_id = l.id
            GROUP BY l.name
            ORDER BY count DESC
            LIMIT 5
        ");
        $response['por_ubicacion'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top usuarios
        $stmt = $pdo->query("
            SELECT u.nombre || ' ' || COALESCE(u.apellidos, '') as name, COUNT(t.id) as count
            FROM oti.tickets t
            JOIN admin.usuarios u ON t.user_id = u.id
            GROUP BY u.nombre, u.apellidos
            ORDER BY count DESC
            LIMIT 5
        ");
        $response['top_usuarios'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Equipos por tipo
        $stmt = $pdo->query("
            SELECT asset_type, COUNT(*) as count
            FROM oti.equipment
            WHERE is_deleted = false
            GROUP BY asset_type
            ORDER BY count DESC
        ");
        $response['equipos_por_tipo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tickets por mes (ultimos 6 meses)
        $stmt = $pdo->query("
            SELECT TO_CHAR(created_at, 'YYYY-MM') as mes, COUNT(*) as count
            FROM oti.tickets
            WHERE created_at >= NOW() - INTERVAL '6 months'
            GROUP BY TO_CHAR(created_at, 'YYYY-MM')
            ORDER BY mes
        ");
        $response['tickets_por_mes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // Stats del usuario
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE status_id = 1) as abiertos,
                COUNT(*) FILTER (WHERE status_id = 2) as en_proceso,
                COUNT(*) FILTER (WHERE status_id = 3) as resueltos,
                COUNT(*) FILTER (WHERE status_id = 4) as cerrados
            FROM oti.tickets
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $response['stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT t.id, t.code, t.title, t.created_at, 
                   ts.name as status_name, tp.name as priority_name
            FROM oti.tickets t
            JOIN oti.ticket_statuses ts ON t.status_id = ts.id
            JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
            WHERE t.user_id = :user_id
            ORDER BY t.created_at DESC
            LIMIT 10
        ");
        $stmt->execute(['user_id' => $userId]);
        $response['tickets_recientes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Tickets últimos 30 días para gráfico
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM oti.tickets
        WHERE created_at >= NOW() - INTERVAL '30 days'
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $response['ultimos_30_dias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Incluir etag del heartbeat para que el frontend pueda comparar
    $stmt = $pdo->query("SELECT etag FROM oti.cache_heartbeat WHERE key = 'dashboard'");
    $heartbeat = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['_etag'] = $heartbeat['etag'] ?? '';
    $response['_timestamp'] = time();

    // Calcular ETag de la respuesta
    $json = json_encode($response);
    $etag = md5($json);

    // Cache condicional HTTP: verificar If-None-Match
    if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
        $clientEtag = trim($_SERVER['HTTP_IF_NONE_MATCH'], '"');
        if ($clientEtag === $etag) {
            http_response_code(304);
            header('ETag: "' . $etag . '"');
            header('Cache-Control: public, max-age=15');
            exit;
        }
    }

    header('ETag: "' . $etag . '"');
    header('Cache-Control: public, max-age=15');
    echo $json;

} catch (Exception $e) {
    error_log('dashboard-poll error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) return 'Hace un momento';
    if ($diff < 3600) return 'Hace ' . floor($diff / 60) . 'm';
    if ($diff < 86400) return 'Hace ' . floor($diff / 3600) . 'h';
    if ($diff < 604800) return 'Hace ' . floor($diff / 86400) . 'd';
    return date('d/m', $timestamp);
}
```

**.htaccess — Agregar regla para el nuevo endpoint:**

Agregar en `C:\xampp\htdocs\OTI\.htaccess` antes de `RewriteRule ^(.*)$ index.php`:

```apache
# Allow direct access to v1 API endpoints
RewriteCond %{REQUEST_URI} ^/OTI/app/api/v1/ [NC]
RewriteRule ^ - [L]
```

### 2. Tabla y Triggers de Heartbeat

**Archivo nuevo:** `database/migration_004_heartbeat.sql`

```sql
-- Sprint 4: Heartbeat para cache condicional (A-E1)
-- Ejecutar: psql -U user -d dbname -f database/migration_004_heartbeat.sql

BEGIN;

-- ============================================================
-- Tabla de heartbeat para cache condicional
-- ============================================================

CREATE TABLE IF NOT EXISTS oti.cache_heartbeat (
    key VARCHAR(100) PRIMARY KEY,
    etag VARCHAR(64) NOT NULL DEFAULT md5(random()::text || clock_timestamp()::text),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO oti.cache_heartbeat (key) VALUES ('dashboard')
ON CONFLICT (key) DO NOTHING;

-- ============================================================
-- Función trigger genérica para actualizar heartbeat
-- ============================================================

CREATE OR REPLACE FUNCTION oti.update_heartbeat()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE oti.cache_heartbeat
    SET etag = md5(random()::text || clock_timestamp()::text),
        updated_at = NOW()
    WHERE key = 'dashboard';
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ============================================================
-- Triggers en tablas que afectan el dashboard
-- ============================================================

-- oti.tickets: INSERT, UPDATE, DELETE
DROP TRIGGER IF EXISTS trg_tickets_heartbeat ON oti.tickets;
CREATE TRIGGER trg_tickets_heartbeat
AFTER INSERT OR UPDATE OR DELETE ON oti.tickets
FOR EACH STATEMENT EXECUTE FUNCTION oti.update_heartbeat();

-- oti.equipment: INSERT, UPDATE, DELETE
DROP TRIGGER IF EXISTS trg_equipment_heartbeat ON oti.equipment;
CREATE TRIGGER trg_equipment_heartbeat
AFTER INSERT OR UPDATE OR DELETE ON oti.equipment
FOR EACH STATEMENT EXECUTE FUNCTION oti.update_heartbeat();

-- oti.locations: INSERT, UPDATE, DELETE
DROP TRIGGER IF EXISTS trg_locations_heartbeat ON oti.locations;
CREATE TRIGGER trg_locations_heartbeat
AFTER INSERT OR UPDATE OR DELETE ON oti.locations
FOR EACH STATEMENT EXECUTE FUNCTION oti.update_heartbeat();

-- oti.ticket_activity: INSERT (nueva actividad)
DROP TRIGGER IF EXISTS trg_ticket_activity_heartbeat ON oti.ticket_activity;
CREATE TRIGGER trg_ticket_activity_heartbeat
AFTER INSERT ON oti.ticket_activity
FOR EACH STATEMENT EXECUTE FUNCTION oti.update_heartbeat();

-- admin.usuarios: INSERT, UPDATE, DELETE
DROP TRIGGER IF EXISTS trg_usuarios_heartbeat ON admin.usuarios;
CREATE TRIGGER trg_usuarios_heartbeat
AFTER INSERT OR UPDATE OR DELETE ON admin.usuarios
FOR EACH STATEMENT EXECUTE FUNCTION oti.update_heartbeat();

COMMIT;
```

### 3. SSE Modificado (Ligero — solo heartbeat)

**Archivo existente:** `app/api/sse.php` (reescritura completa)

```php
<?php
/**
 * API SSE (Server-Sent Events) — Versión Ligera
 * Sistema OTI - Solo consulta heartbeat, no ejecuta getStats()
 * Los datos completos se obtienen vía dashboard-poll.php cuando hay cambios
 */

session_start();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Methods: GET');

if (!isset($_SESSION['user'])) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'No autorizado']) . "\n\n";
    flush();
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Services\AuthService;

$isAdmin = AuthService::isAdmin();
$userId = $_SESSION['user']['id'] ?? null;
$lastEtag = '';
$pollUrl = '/OTI/app/api/v1/dashboard-poll.php';

function fetchDashboardData($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_HTTPHEADER => [
            'Cookie: ' . session_name() . '=' . session_id()
        ]
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 304) return null;
    if ($httpCode === 200) return json_decode($data, true);
    return null;
}

echo "event: connected\n";
echo "data: " . json_encode(['status' => 'connected', 'timestamp' => time()]) . "\n\n";
flush();

set_time_limit(0);
ignore_user_abort(false);

while (!connection_aborted()) {
    try {
        $pdo = Database::connect();

        $stmt = $pdo->query("SELECT etag, updated_at FROM oti.cache_heartbeat WHERE key = 'dashboard'");
        $heartbeat = $stmt->fetch(PDO::FETCH_ASSOC);

        Database::disconnect();

        if (!$heartbeat) {
            sleep(5);
            continue;
        }

        $currentEtag = $heartbeat['etag'];

        if ($currentEtag !== $lastEtag) {
            $lastEtag = $currentEtag;

            $data = fetchDashboardData($pollUrl);

            if ($data !== null) {
                echo "event: dashboard_update\n";
                echo "data: " . json_encode($data) . "\n\n";
            }
        } else {
            // Heartbeat vacío para mantener conexión viva
            echo ": heartbeat " . $heartbeat['updated_at'] . "\n\n";
        }

        flush();
        if (ob_get_level() > 0) {
            ob_end_flush();
        }

    } catch (Exception $e) {
        error_log('SSE heartbeat error: ' . $e->getMessage());
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'Error interno']) . "\n\n";
        flush();
    }

    sleep(5);
}

echo "event: close\n";
echo "data: " . json_encode(['status' => 'disconnected']) . "\n\n";
flush();
```

### 4. SSEClient Central (JavaScript)

**Archivo nuevo:** `public/assets/js/sse-client.js`

```javascript
/**
 * SSEClient — Cliente SSE central para OTI
 * Maneja la conexión SSE y distribuye eventos a suscriptores
 */
class SSEClient {
    constructor(url) {
        this.url = url;
        this.eventSource = null;
        this.listeners = {};
        this.lastEtag = null;
        this.reconnectTimeout = 3000;
        this.connect();
    }

    connect() {
        if (this.eventSource) {
            this.eventSource.close();
        }

        try {
            this.eventSource = new EventSource(this.url);

            this.eventSource.addEventListener('connected', (e) => {
                console.log('[SSEClient] Conectado');
            });

            this.eventSource.addEventListener('dashboard_update', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    if (data._etag && data._etag !== this.lastEtag) {
                        this.lastEtag = data._etag;
                        this._dispatch('dashboard:update', data);
                    }
                } catch (err) {
                    console.error('[SSEClient] Error parseando dashboard_update:', err);
                }
            });

            this.eventSource.onerror = () => {
                console.warn('[SSEClient] Error de conexión, reconectando en ' + this.reconnectTimeout + 'ms');
                this.eventSource.close();
                setTimeout(() => this.connect(), this.reconnectTimeout);
                this.reconnectTimeout = Math.min(this.reconnectTimeout * 2, 30000);
            };

            this.eventSource.onopen = () => {
                this.reconnectTimeout = 3000;
            };

        } catch (e) {
            console.warn('[SSEClient] Error al crear EventSource:', e);
            setTimeout(() => this.connect(), this.reconnectTimeout);
        }
    }

    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }

    off(event, callback) {
        if (!this.listeners[event]) return;
        this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
    }

    _dispatch(event, data) {
        (this.listeners[event] || []).forEach(cb => {
            try {
                cb(data);
            } catch (e) {
                console.error('[SSEClient] Error en listener ' + event + ':', e);
            }
        });
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }
}
```

### 5. realtime.js Modificado (Escucha Eventos, Sin Polling)

**Archivo existente:** `public/assets/js/realtime.js`

Los cambios son quirúrgicos:

1. Eliminar `setInterval(fetchAllData, 15000)` (línea 53)
2. Eliminar `fetchAllData()` como función de polling (líneas 140-152) — pero mantenerla para carga inicial
3. Eliminar `eventSource` management (SSE lo maneja SSEClient ahora)
4. Suscribirse a eventos de SSEClient global

```diff
--- a/public/assets/js/realtime.js
+++ b/public/assets/js/realtime.js
@@ -1,89 +1,71 @@
 /**
- * Sistema de tiempo real para OTI
- * Versión optimizada: SSE (Server-Sent Events) con fallback a polling
+ * Sistema de tiempo real para OTI (V2)
+ * Escucha eventos de SSEClient central — sin polling propio
  */

 (function() {
     'use strict';

     const BASE_URL = window.location.origin + '/OTI/';
     let isAdmin = false;
     let currentPage = '';
-    let eventSource = null;
-    let updateInterval = null;
-    let useSSE = true;
-    let lastData = null;

     function init() {
         const adminElement = document.getElementById('is-admin');
         const userElement = document.getElementById('user-id');
-        
+
         isAdmin = adminElement ? adminElement.value === '1' : false;
-        
+
         if (!isAdmin && userElement) {
             const roleElement = document.getElementById('user-role');
             if (roleElement) {
                 const roleText = roleElement.textContent.toLowerCase();
-                isAdmin = roleText.includes('admin') || 
-                         roleText.includes('director') || 
+                isAdmin = roleText.includes('admin') ||
+                         roleText.includes('director') ||
                          roleText.includes('jefe') ||
                          roleText.includes('coordinador') ||
                          roleText.includes('supervisor');
             }
         }
-        
+
         const path = window.location.pathname;
         if (path.includes('admin/dashboard')) currentPage = 'admin-dashboard';
         else if (path.includes('admin/tickets')) currentPage = 'admin-tickets';
-        else if (path.includes('admin/equipos')) currentPage = 'admin-equipos';
-        else if (path.includes('admin/usuarios')) currentPage = 'admin-usuarios';
-        else if (path.includes('admin/estructura')) currentPage = 'admin-estructura';
         else if (path.includes('admin/analisis')) currentPage = 'admin-analisis';
         else if (path.includes('user/dashboard')) currentPage = 'user-dashboard';
-        else if (path.includes('user/tickets')) currentPage = 'user-tickets';
-        else if (path.includes('user/ticket-detalle')) currentPage = 'user-ticket-detalle';
-        else if (path.includes('user/reportar')) currentPage = 'user-reportar';

         injectMobileMenu();

-        if (useSSE && (currentPage === 'admin-dashboard' || currentPage === 'user-dashboard')) {
-            initSSE();
-        } else {
-            fetchAllData();
-            updateInterval = setInterval(fetchAllData, 15000);
-        }
-        
+        // Carga inicial de datos
+        fetchAllData();
+
+        // Suscribirse al SSEClient global para actualizaciones en tiempo real
+        if (window.otiSSE) {
+            const dashboardPages = ['admin-dashboard', 'admin-analisis', 'user-dashboard'];
+            if (dashboardPages.includes(currentPage)) {
+                window.otiSSE.on('dashboard:update', handleDataUpdate);
+            }
+        }
+
         fetchNotifications();
         setInterval(fetchNotifications, 30000);
     }

-    function initSSE() {
-        try {
-            eventSource = new EventSource(BASE_URL + 'app/api/sse.php');
-            
-            eventSource.onopen = function() {
-                console.log('SSE conectado');
-                if (updateInterval) {
-                    clearInterval(updateInterval);
-                    updateInterval = null;
-                }
-            };
-            
-            eventSource.onmessage = function(event) {
-                try {
-                    const data = JSON.parse(event.data);
-                    handleDataUpdate(data);
-                } catch (e) {
-                    console.error('Error parseando datos SSE:', e);
-                }
-            };
-            
-            eventSource.addEventListener('update', function(event) {
-                try {
-                    const data = JSON.parse(event.data);
-                    handleDataUpdate(data);
-                } catch (e) {
-                    console.error('Error en evento update:', e);
-                }
-            });
-            
-            eventSource.addEventListener('connected', function(event) {
-                const data = JSON.parse(event.data);
-                console.log('SSE conectado:', data);
-            });
-            
-            eventSource.addEventListener('error', function() {
-                console.warn('SSE error event received, fallback to onerror handler');
-            });
-            
-            eventSource.onerror = function(e) {
-                console.warn('SSE error, cambiando a polling:', e);
-                closeSSE();
-                useSSE = false;
-                fetchAllData();
-                updateInterval = setInterval(fetchAllData, 15000);
-            };
-            
-        } catch (e) {
-            console.warn('SSE no disponible, usando polling:', e);
-            useSSE = false;
-            fetchAllData();
-            updateInterval = setInterval(fetchAllData, 15000);
-        }
-    }
-
-    function closeSSE() {
-        if (eventSource) {
-            eventSource.close();
-            eventSource = null;
-        }
-    }

     function handleDataUpdate(data) {
         if (data.error) return;
-        
-        lastData = data;
-        
+
         updateStats(data);
-        
+
         if (currentPage === 'admin-dashboard') {
             updateAdminDashboard(data);
         } else if (currentPage === 'user-dashboard') {
             updateUserDashboard(data);
-        } else if (currentPage === 'user-ticket-detalle') {
-            updateUserTicketDetail();
-        } else if (currentPage === 'admin-usuarios' && typeof window.refreshUsers === 'function') {
-            window.refreshUsers();
         }
     }
 
@@ -140,10 +98,8 @@
                 }
             }
         } catch (error) {
             console.warn('Error fetching data:', error);
         }
     }

     async function fetchNotifications() {
         try {
             const notifRes = await fetch(BASE_URL + 'app/api/notifications.php');
```

**Archivo final completo** del nuevo `realtime.js`:

```javascript
/**
 * Sistema de tiempo real para OTI (V2)
 * Escucha eventos de SSEClient central — sin polling propio
 */

(function() {
    'use strict';

    const BASE_URL = window.location.origin + '/OTI/';
    let isAdmin = false;
    let currentPage = '';

    function init() {
        const adminElement = document.getElementById('is-admin');
        const userElement = document.getElementById('user-id');

        isAdmin = adminElement ? adminElement.value === '1' : false;

        if (!isAdmin && userElement) {
            const roleElement = document.getElementById('user-role');
            if (roleElement) {
                const roleText = roleElement.textContent.toLowerCase();
                isAdmin = roleText.includes('admin') ||
                         roleText.includes('director') ||
                         roleText.includes('jefe') ||
                         roleText.includes('coordinador') ||
                         roleText.includes('supervisor');
            }
        }

        const path = window.location.pathname;
        if (path.includes('admin/dashboard')) currentPage = 'admin-dashboard';
        else if (path.includes('admin/tickets')) currentPage = 'admin-tickets';
        else if (path.includes('admin/analisis')) currentPage = 'admin-analisis';
        else if (path.includes('user/dashboard')) currentPage = 'user-dashboard';

        injectMobileMenu();

        // Carga inicial de datos
        fetchAllData();

        // Suscribirse al SSEClient global para actualizaciones en tiempo real
        if (window.otiSSE) {
            const dashboardPages = ['admin-dashboard', 'admin-analisis', 'user-dashboard'];
            if (dashboardPages.includes(currentPage)) {
                window.otiSSE.on('dashboard:update', handleDataUpdate);
            }
        }

        fetchNotifications();
        setInterval(fetchNotifications, 30000);
    }

    function handleDataUpdate(data) {
        if (data.error) return;
        updateStats(data);
        if (currentPage === 'admin-dashboard') {
            updateAdminDashboard(data);
        } else if (currentPage === 'user-dashboard') {
            updateUserDashboard(data);
        }
    }

    async function fetchAllData() {
        try {
            const res = await fetch(BASE_URL + 'app/api/v1/dashboard-poll.php');
            if (res.ok && res.headers.get('content-type')?.includes('application/json')) {
                const data = await res.json();
                if (!data.error) {
                    handleDataUpdate(data);
                }
            }
        } catch (error) {
            console.warn('Error fetching data:', error);
        }
    }

    async function fetchNotifications() {
        try {
            const notifRes = await fetch(BASE_URL + 'app/api/notifications.php');
            if (notifRes.ok && notifRes.headers.get('content-type')?.includes('application/json')) {
                const notifData = await notifRes.json();
                updateNotifications(notifData);
            }
        } catch (error) {
            console.warn('Error fetching notifications:', error);
        }
    }
    // ... el resto de funciones (updateStats, updateAdminDashboard, etc.) se mantienen IGUAL
```

**Nota:** Las funciones `updateStats()`, `updateAdminDashboard()`, `updateUserDashboard()`, `updateTicketsList()`, `renderTimeline()`, `renderEquiposDonut()`, `renderUsuariosBar()`, `updateNotifications()`, `animateValue()`, `getStatusClass()`, `getPriorityClass()`, `formatDate()`, `escapeHtml()`, `injectMobileMenu()` y `updateValue()` se mantienen **sin cambios** del archivo original. El cambio es solo eliminar `initSSE()`, `closeSSE()`, `updateInterval`, `eventSource`, y las variables relacionadas.

### 6. analisis-charts.js Modificado (Carga Única + Eventos)

**Archivo existente:** `public/assets/js/analisis-charts.js`

```diff
--- a/public/assets/js/analisis-charts.js
+++ b/public/assets/js/analisis-charts.js
@@ -5,7 +5,7 @@

 (function() {
     'use strict';
-    
+
     const BASE_URL = window.location.origin + '/OTI/';
     let charts = {};
-    let updateInterval = null;
     let isInitialized = false;
-    
+
     const CHART_COLORS = {
         primary: '#6366f1',
         primaryLight: '#818cf8',
@@ -33,7 +32,7 @@
         gray: '#64748b',
         grayLight: '#94a3b8'
     };
-    
+
     const chartDefaults = {
         responsive: true,
         maintainAspectRatio: false,
@@ -78,24 +77,23 @@
             }
         }
     };
-    
+
     function init() {
         if (typeof Chart === 'undefined') {
             console.error('Chart.js no cargado');
             return;
         }
-        
+
         if (isInitialized) return;
         isInitialized = true;
-        
+
         const initialData = window.analisisInitialData || {};
-        
-        initTicketsMensualChart(initialData.tickets_por_mes || []);
-        initPrioridadChart(initialData.por_prioridad || []);
-        initUbicacionesChart(initialData.por_ubicacion || []);
-        initUsuariosChart(initialData.top_usuarios || []);
-        initEquiposChart(initialData.equipos_por_tipo || []);
-        initEstadoChart(initialData.por_estado || []);
+
+        initTicketsMensualChart(initialData.tickets_por_mes || []);
+        initPrioridadChart(initialData.por_prioridad || []);
+        initUbicacionesChart(initialData.por_ubicacion || []);
+        initUsuariosChart(initialData.top_usuarios || []);
+        initEquiposChart(initialData.equipos_por_tipo || []);
+        initEstadoChart(initialData.por_estado || []);
         initEquiposEstadoChart(initialData.equipos || {
             total: 0,
             activos: 0,
@@ -103,10 +101,14 @@
             inactivos: 0
         });
-        
-        fetchAndUpdateCharts();
-        
-        updateInterval = setInterval(fetchAndUpdateCharts, 10000);
+
+        // Suscripción al SSEClient global
+        if (window.otiSSE) {
+            window.otiSSE.on('dashboard:update', function(data) {
+                if (data.stats) updateKPIs(data.stats);
+                if (data.por_estado || data.ultimos_30_dias || data.equipos) updateCharts(data);
+            });
+        }
     }
-    
+
     function initTicketsMensualChart(data) {
         const ctx = document.getElementById('chart-tickets-mensual');
         if (!ctx) return;
@@ -396,19 +398,6 @@
             }
         });
     }
-    
-    function fetchAndUpdateCharts() {
-        fetch(BASE_URL + 'app/api/stats.php')
-            .then(res => {
-                if (!res.ok) throw new Error('Error fetching');
-                return res.json();
-            })
-            .then(data => {
-                if (data.error) return;
-                updateKPIs(data.stats || {});
-                updateCharts(data);
-            })
-            .catch(err => {});
-    }
-    
+
     function updateKPIs(stats) {
         const kpiElements = {
             'kpi-total': stats.total || 0,
```

**Archivo final completo** de `analisis-charts.js` (solo cambios relevantes):

El archivo se mantiene igual, solo se eliminan:
1. `let updateInterval = null;` (línea 11)
2. `updateInterval = setInterval(fetchAndUpdateCharts, 10000);` (línea 104)
3. La función completa `fetchAndUpdateCharts()` (líneas 402-416)
4. Se agrega la suscripción a `window.otiSSE.on('dashboard:update', ...)` después de inicializar los charts

### 7. Integración en Vistas

**`app/Views/partials/footer.php`** — Modificar para cargar sse-client.js antes que realtime.js:

```php
    <script>
    function toggleProfileMenu() {
        const dropdown = document.getElementById('profile-dropdown');
        dropdown.classList.toggle('active');
        const notifDropdown = document.getElementById('notif-dropdown');
        if (notifDropdown) notifDropdown.classList.remove('active');
    }
    document.addEventListener('click', function(e) {
        const profileBtn = document.querySelector('.profile-btn');
        const profileDropdown = document.getElementById('profile-dropdown');
        if (profileBtn && profileDropdown && !profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.classList.remove('active');
        }
    });
    </script>
    <script src="<?= $baseUrl ?>public/assets/js/sse-client.js"></script>
    <script>
    // Inicializar SSEClient global antes de realtime.js
    window.otiSSE = new SSEClient('<?= $baseUrl ?>app/api/sse.php');
    </script>
    <script src="<?= $baseUrl ?>public/assets/js/realtime.js"></script>
</body>
</html>
```

**`app/Views/admin/analisis.php`** — Cambiar la línea del script realtime.js y cargar sse-client.js:

```php
    // En lugar de (líneas 610-611):
    // <script src="<?= $baseUrl ?>public/assets/js/realtime.js"></script>
    // <script src="<?= $baseUrl ?>public/assets/js/analisis-charts.js"></script>
    
    // Nuevo:
    <script src="<?= $baseUrl ?>public/assets/js/sse-client.js"></script>
    <script>
    window.otiSSE = new SSEClient('<?= $baseUrl ?>app/api/sse.php');
    </script>
    <script src="<?= $baseUrl ?>public/assets/js/realtime.js"></script>
    <script src="<?= $baseUrl ?>public/assets/js/analisis-charts.js"></script>
```

### 8. Eliminar archivo stats.php (opcional, post-migración)

**Archivo:** `app/api/stats.php`

Una vez que todos los clientes usen dashboard-poll.php, stats.php puede quedar como deprecated. Se recomienda mantenerlo 1 semana en paralelo con un header `X-Deprecated: true` y luego eliminarlo.

```php
// Agregar al inicio de stats.php (línea 9):
header('X-Deprecated: true');
header('X-Replacement: /OTI/app/api/v1/dashboard-poll.php');
```

## Plan de Implementación (Semanas 1-2)

| Día | Tareas | Archivos |
|-----|--------|----------|
| Día 1 | Tabla heartbeat + triggers SQL | `database/migration_004_heartbeat.sql` |
| Día 1 | .htaccess regla para v1 | `.htaccess` |
| Día 2 | Endpoint dashboard-poll con ETag | `app/api/v1/dashboard-poll.php` |
| Día 3 | SSE ligero modificado | `app/api/sse.php` (reescritura) |
| Día 4 | SSEClient JS + footer.php | `public/assets/js/sse-client.js`, `app/Views/partials/footer.php` |
| Día 5 | realtime.js refactor (eliminar polling) | `public/assets/js/realtime.js` |
| Día 6 | analisis-charts.js refactor | `public/assets/js/analisis-charts.js` |
| Día 7 | Integración final + pruebas | Dashboard, analisis.php, footer.php |

## Rollback Plan

Cada cambio es reversible independientemente:

| Componente | Cómo revertir |
|------------|---------------|
| **Triggers heartbeat** | `DROP TRIGGER IF EXISTS trg_tickets_heartbeat ON oti.tickets;` (y así con las demás tablas); `DROP TABLE IF EXISTS oti.cache_heartbeat;` |
| **dashboard-poll.php** | Eliminar el archivo y la regla .htaccess |
| **SSE modificado** | Reemplazar `app/api/sse.php` con el backup del original |
| **sse-client.js** | Eliminar el archivo; restaurar footer.php original |
| **realtime.js** | Restaurar el backup (incluía initSSE y polling) |
| **analisis-charts.js** | Restaurar el backup (incluía fetchAndUpdateCharts) |

**Procedimiento de rollback rápido (< 5 min):**
```bash
# Revertir todo en un solo paso
git checkout -- app/api/sse.php app/api/stats.php public/assets/js/realtime.js public/assets/js/analisis-charts.js app/Views/partials/footer.php app/Views/admin/analisis.php .htaccess

# Revertir BD
psql -U user -d dbname -c "DROP TABLE IF EXISTS oti.cache_heartbeat CASCADE;"

# Eliminar archivos nuevos
rm -f app/api/v1/dashboard-poll.php public/assets/js/sse-client.js database/migration_004_heartbeat.sql
```

## Pruebas

### Pruebas Unitarias
1. **Heartbeat trigger:** Hacer INSERT en oti.tickets → verificar que `cache_heartbeat.etag` cambió
2. **ETag 304:** Llamar a dashboard-poll.php con `If-None-Match: <etag_actual>` → debe responder 304 sin cuerpo
3. **ETag 200:** Llamar sin header o con etag obsoleto → debe responder 200 con JSON completo
4. **SSE heartbeat:** Conectar a sse.php → verificar que recibe eventos `: heartbeat` cada 5s
5. **SSE dashboard_update:** Hacer INSERT en tickets → verificar que SSE envía `event: dashboard_update` en menos de 5s

### Pruebas de Carga
1. **20 conexiones SSE concurrentes:** Cada una consulta heartbeat cada 5s → debe mantener ~12 queries/min total (solo 1 por ciclo para heartbeat)
2. **Sin cambios en BD:** 5 minutos sin actividad → SSE solo envía heartbeats vacíos, dashboard-poll no se llama
3. **Ráfaga de cambios:** 100 tickets creados en 1 segundo → heartbeat cambia una vez (trigger FOR EACH STATEMENT), dashboard-poll se ejecuta una vez

### Pruebas de Integración
1. **Dashboard admin:** Cargar página → ver datos iniciales → crear ticket → ver contadores actualizados en < 5s
2. **Dashboard user:** Cargar página → ver datos del usuario → no debe hacer polling a stats.php
3. **Analisis:** Cargar página → charts se cargan con datos iniciales → cambio en BD → charts se actualizan vía SSEClient
4. **Reconexión SSE:** Matar proceso SSE → cliente reconecta en 3s → sigue recibiendo actualizaciones
5. **Fallback sin SSE:** Bloquear sse.php → realtime.js carga datos iniciales y notificaciones siguen funcionando

### Verificación de Reducción de Queries
```sql
-- Antes de implementar:
SELECT count(*) FROM pg_stat_activity WHERE state = 'active' AND query NOT LIKE '%pg_stat%';
-- (esperado: ~22 queries activas con 20 admins)

-- Después de implementar:
SELECT count(*) FROM pg_stat_activity WHERE state = 'active' AND query NOT LIKE '%pg_stat%';
-- (esperado: ~2-3 queries activas: heartbeats + cambios esporádicos)
```

## Resumen de Reducción de Carga

| Métrica | Antes | Después | Reducción |
|---------|-------|---------|-----------|
| Queries/min con 20 admins | ~1,320 | ~30 | **97.7%** |
| Queries por carga de dashboard | 11 | 1 (inicial) + heartbeats | **~90%** |
| Polling JS | 3 canales (15s, 5s, 10s) | 1 canal SSE (5s heartbeat) | **1 canal** |
| Conexiones BD por SSE | 1 persistente (abierta siempre) | 1 efímera por ciclo (5s) | **~0 conexiones ociosas** |
| Ancho de banda SSE | ~6KB cada 5s (~72KB/min) | ~100 bytes cada 5s (~1.2KB/min) | **98.3%** |
