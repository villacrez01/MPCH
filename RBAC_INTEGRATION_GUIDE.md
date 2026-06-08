# 🔗 Guía de Integración - Conectar RBAC en el Sistema

**Versión:** 1.0  
**Objetivo:** Conectar el sistema RBAC con las rutas y vistas existentes

---

## 1. Integración en index.php (Router Principal)

### 1.1 Actualizar rutas para API de permisos

En tu archivo `index.php` o en tu router, agregar estas rutas:

```php
<?php
// Rutas de API de permisos
$router->post('/api/permissions/check/:permission', 'PermissionsController@check');
$router->get('/api/permissions/current', 'PermissionsController@current');
$router->post('/api/permissions/refresh', 'PermissionsController@refresh');
$router->get('/api/permissions/list', 'PermissionsController@list_permissions');
$router->get('/api/permissions/user/:userId', 'PermissionsController@user_permissions');

// Rutas de Tickets con middleware de autorización
$router->get('/tickets', function() {
    AuthorizationMiddleware::requirePermission('tickets.ver');
    // ... código de controlador
});

$router->post('/tickets', function() {
    AuthorizationMiddleware::requirePermission('tickets.crear');
    // ... código de controlador
});

$router->put('/tickets/:id', function($id) {
    AuthorizationMiddleware::requirePermission('tickets.editar');
    // ... código de controlador
});

$router->delete('/tickets/:id', function($id) {
    AuthorizationMiddleware::requirePermission('tickets.eliminar');
    // ... código de controlador
});
?>
```

---

## 2. Integración en Middleware Global

### 2.1 Agregar verificación de sesión y timeout

En tu archivo de bootstrap (antes de procesar rutas):

```php
<?php
// index.php o bootstrap.php

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true,
    'cookie_samesite' => 'Lax',
    'gc_maxlifetime' => 3600,
]);

// Middleware de autenticación y timeout
use App\Services\AuthService;

// Verificar sesión activa
if (!AuthService::check()) {
    // Algunas rutas pueden ser públicas (login, register)
    $publicRoutes = ['/login', '/register', '/forgot-password'];
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    if (!in_array($currentPath, $publicRoutes)) {
        header('Location: /login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

// Middleware global de CORS (si necesario)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}
?>
```

---

## 3. Integración en Vistas Existentes

### 3.1 Incluir scripts de permisos

En tu layout principal (header o footer):

```html
<!DOCTYPE html>
<html>
<head>
    <!-- ... otros estilos ... -->
    <link rel="stylesheet" href="/css/buttons-rbac.css">
</head>
<body>
    <!-- ... contenido ... -->
    
    <!-- Scripts de permisos (al final de body) -->
    <script src="/js/PermissionManager.js"></script>
    <script src="/js/RealtimeSync.js"></script>
    
    <script>
        // Cargar permisos cuando document esté listo
        document.addEventListener('DOMContentLoaded', () => {
            window.permissions.load().catch(console.error);
        });
    </script>
</body>
</html>
```

### 3.2 Usar data-permission en botones existentes

```html
<!-- Antes (sin RBAC) -->
<button class="btn btn-primary" onclick="crearTicket()">
    Crear Ticket
</button>

<!-- Después (con RBAC) -->
<button class="btn btn-primary" 
        data-permission="tickets.crear"
        onclick="crearTicket()">
    Crear Ticket
</button>
```

### 3.3 Ocultar/mostrar secciones según permisos

```html
<!-- Mostrar solo si tiene el permiso -->
<div data-permission-group="tickets.asignar,tickets.editar">
    <section class="admin-panel">
        <h3>Panel de Gestión Avanzada</h3>
        <!-- Opciones solo para admin/técnicos -->
    </section>
</div>

<!-- Mostrar si NO tiene el permiso -->
<div data-permission-hide="admin.ver-auditoria">
    <div class="info-box">
        <p>💡 Para ver auditoría, necesitas permisos de administrador</p>
    </div>
</div>
```

---

## 4. Integración en Controladores

### 4.1 Agregar validación de permisos

```php
<?php
// app/Controller/TicketController.php

namespace App\Controller;

use App\Middleware\AuthorizationMiddleware;
use App\Services\AuthService;
use App\Models\Ticket;

class TicketController
{
    /**
     * Listar tickets
     */
    public function list()
    {
        try {
            AuthorizationMiddleware::requirePermission('tickets.ver');
            
            $tickets = Ticket::all();
            View::render('tickets/list', ['tickets' => $tickets]);
            
        } catch (\Exception $e) {
            http_response_code(403);
            View::render('errors/forbidden', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Crear ticket
     */
    public function store()
    {
        try {
            AuthorizationMiddleware::requirePermission('tickets.crear');
            
            $data = $_POST;
            $ticket = Ticket::create($data);
            
            View::json([
                'success' => true,
                'ticket_id' => $ticket->id
            ]);
            
        } catch (\Exception $e) {
            http_response_code(403);
            View::json(['error' => $e->getMessage()]);
        }
    }

    /**
     * Editar ticket (con validación de propiedad)
     */
    public function update($id)
    {
        try {
            $ticket = Ticket::find($id);
            if (!$ticket) {
                throw new \Exception('Ticket no encontrado', 404);
            }
            
            // Validación: editar solo mis tickets o ser admin
            AuthorizationMiddleware::requirePermission('tickets.editar', function($user) use ($ticket) {
                // Admin siempre puede
                if (AuthService::isAdmin()) return true;
                // Usuario solo sus propios tickets
                return $ticket->usuario_id === $user['id'];
            });
            
            $ticket->update($_POST);
            
            View::json(['success' => true]);
            
        } catch (\Exception $e) {
            http_response_code(403);
            View::json(['error' => $e->getMessage()]);
        }
    }
}
?>
```

---

## 5. Ejecutar la Migración

### 5.1 Pasos para aplicar schema RBAC

```bash
# 1. Conectar a PostgreSQL
psql -U postgres -d sistema_soporte

# 2. Ejecutar migración
\i /path/to/database/migrations/rbac_permissions.sql

# 3. Verificar tablas creadas
\dt admin.permisos
\dt admin.rol_permiso
\dt admin.usuario_permiso_especial

# 4. Verificar datos cargados
SELECT COUNT(*) FROM admin.permisos;
SELECT COUNT(*) FROM admin.rol_permiso;
```

### 5.2 O via PHP

```php
<?php
$db = \App\Core\Database::connect();
$sql = file_get_contents('database/migrations/rbac_permissions.sql');
$statements = explode(';', $sql);

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
        $db->exec($statement);
    }
}

echo "✅ Migración completada\n";
?>
```

---

## 6. Testing de Integración

### 6.1 Verificar endpoints API

```bash
# Obtener permisos del usuario actual
curl -X GET http://localhost/api/permissions/current \
  -H "Cookie: PHPSESSID=xxx"

# Respuesta esperada:
# {
#   "success": true,
#   "user": {...},
#   "permissions": ["tickets.ver", "tickets.crear", ...],
#   "timestamp": 1234567890
# }
```

### 6.2 Verificar middleware en controlador

```php
<?php
// En un test file
$_SESSION['user'] = ['id' => 1, 'name' => 'Test User'];

try {
    AuthorizationMiddleware::requirePermission('tickets.crear');
    echo "✅ Permiso verificado correctamente\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
```

### 6.3 Verificar botones en browser

```javascript
// En la consola del navegador
window.permissions.load().then(perms => {
    console.log('Permisos cargados:', perms.permissions);
    console.log('¿Puede crear tickets?', window.permissions.can('tickets.crear'));
});
```

---

## 7. Casos de Uso Frecuentes

### 7.1 Mostrar panel solo si es admin

```html
<?php if (AuthService::isAdmin()): ?>
    <div class="admin-panel">
        <!-- Opciones solo para admin -->
    </div>
<?php endif; ?>
```

### 7.2 Mostrar botón solo si tiene permiso específico

```html
<?php if (AuthService::hasPermission('tickets.eliminar')): ?>
    <button class="btn btn-danger" onclick="eliminarTicket()">
        Eliminar
    </button>
<?php endif; ?>
```

### 7.3 Redirigir si no tiene permisos

```php
<?php
if (!AuthService::hasPermission('admin.ver-auditoria')) {
    header('Location: /dashboard');
    exit;
}

// Mostrar página de auditoría
View::render('admin/auditoria');
?>
```

### 7.4 Crear permiso temporal para usuario

```php
<?php
$db = \App\Core\Database::connect();

$stmt = $db->prepare("
    INSERT INTO admin.usuario_permiso_especial 
    (usuario_id, permiso_id, razon, expira_en)
    VALUES (:user_id, :perm_id, :reason, NOW() + INTERVAL '7 days')
");

$stmt->execute([
    'user_id' => 42,
    'perm_id' => 5,  // ID del permiso
    'reason' => 'Permiso temporal para migración'
]);

// Invalidar caché
AuthService::invalidatePermissionCache(42);
?>
```

---

## 8. Troubleshooting

### Problema: "No autenticado" en API

**Solución:** Verificar que cookies se envían correctamente
```javascript
// En frontend
fetch('/api/permissions/current', {
    credentials: 'include' // IMPORTANTE!
});
```

### Problema: Botones siempre deshabilitados

**Solución:** Verificar que PermissionManager cargó permisos
```javascript
console.log(window.permissions.permissions);
console.log(window.permissions.isAdmin);
```

### Problema: Cambios de rol no se reflejan

**Solución:** Refrescar permisos después de cambiar rol
```php
// En controlador después de asignar rol
AuthService::invalidatePermissionCache($userId);

// En frontend, llamar:
window.permissions.refresh();
```

---

## 9. Roadmap de Implementación

### Sprint 1 (Esta semana)
- [x] Crear tablas de BD
- [x] Implementar AuthService y Middleware
- [x] Crear API de permisos
- [ ] Integrar en index.php

### Sprint 2 (Próxima semana)
- [ ] Crear archivos CSS y JS
- [ ] Integrar en vistas existentes
- [ ] Testing de flujos completos
- [ ] Capacitar team

### Sprint 3
- [ ] Performance testing
- [ ] Security audit
- [ ] Deploy a producción
- [ ] Monitoreo en prod

---

**Última actualización:** 2026-05-27  
**Contacto:** dev@oti.local

