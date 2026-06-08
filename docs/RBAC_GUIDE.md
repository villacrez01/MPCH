# Guía Técnica RBAC - Sistema de Botones Dinámicos

## Pilares del Sistema RBAC

---

## 1. DISEÑO DE UI/UX DE COMPONENTES DE ACCIÓN

### 1.1 Estructura Visual de Botones

```css
:root {
  /* Paleta principal */
  --primary: #4338ca;
  --primary-hover: #3730a3;
  --primary-soft: #e0e7ff;
  --success: #10b981;
  --warning: #f59e0b;
  --danger: #ef4444;
  --info: #06b6d4;
  --text-primary: #1e293b;
  --text-secondary: #64748b;
  --text-muted: #94a3b8;
  --border: #e2e8f0;
  --radius-sm: 6px;
  --radius-md: 8px;
  --radius-lg: 12px;
  --radius-full: 9999px;
  --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}

.action-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: var(--radius-sm);
  font-weight: 500;
  font-size: 14px;
  font-family: inherit;
  cursor: pointer;
  transition: all 150ms ease;
  border: 1px solid transparent;
  text-decoration: none;
}

/* Variantes por semántica */
.action-button--primary { background: var(--primary); color: white; }
.action-button--secondary { background: white; color: var(--text-secondary); border-color: var(--border); }
.action-button--success { background: var(--success); color: white; }
.action-button--warning { background: var(--warning); color: white; }
.action-button--danger { background: var(--danger); color: white; }
.action-button--info { background: var(--info); color: white; }
```

### 1.2 Estados de Interacción

| Estado | Propiedades | CSS |
|--------|-------------|-----|
| **Default** | Apariencia base | `opacity: 1; transform: scale(1);` |
| **Hover** | Elevación sutil | `transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1);` |
| **Active** | Presión visual | `transform: scale(0.98); transition: transform 100ms;` |
| **Disabled** | No interactuable | `opacity: 0.5; cursor: not-allowed; pointer-events: none;` |
| **Loading** | Estado de procesamiento | `cursor: wait; opacity: 0.8;` + spinner SVG |

### 1.3 Estándares de Accesibilidad (WCAG 2.1)

```html
<!-- Estructura accesible -->
<button 
  class="action-button action-button--primary"
  aria-label="Crear nuevo ticket"
  aria-describedby="button-description"
  data-permission="tickets.crear"
  data-loading="false"
  type="button">
  <svg aria-hidden="true"><!-- icono --></svg>
  <span>Crear Ticket</span>
</button>
<span id="button-description" class="sr-only">Crear un nuevo ticket de soporte</span>
```

**Requisitos:**
- Contraste mínimo 4.5:1 (texto) / 3:1 (elementos UI)
- Tamaño táctil mínimo 44x44px
- Focus visible con outline de 2px mínimo
- ARIA labels descriptivos
- Soporte completo para teclado (Tab, Enter, Space)

### 1.4 Componente de Botón con Permisos (HTML + JS)

```html
<rbac-button 
  permission="tickets.editar" 
  action="redirect" 
  target="/admin/tickets/1/edit"
  variant="primary"
  icon="edit">
  Editar Ticket
</rbac-button>
```

---

## 2. ARQUITECTURA DE IMPLEMENTACIÓN RBAC

### 2.1 Modelado de Base de Datos

#### Tablas del Sistema RBAC

```sql
-- Sistemas (multi-tenant)
CREATE TABLE admin.sistemas (
  id SERIAL PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  slug VARCHAR(50) UNIQUE NOT NULL
);

-- Permisos granulares con jerarquía
CREATE TABLE admin.permisos (
  id SERIAL PRIMARY KEY,
  codigo VARCHAR(100) UNIQUE NOT NULL, -- ej: 'usuarios.editar'
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT,
  modulo VARCHAR(50), -- ej: 'usuarios', 'tickets'
  accion VARCHAR(50), -- ej: 'crear', 'editar', 'eliminar', 'ver'
  categoria VARCHAR(50), -- ej: 'administracion', 'operativo'
  nivel INTEGER DEFAULT 1, -- 1: lectura, 2: escritura, 3: crítico
  created_at TIMESTAMP DEFAULT NOW()
);

-- Roles predefinidos
CREATE TABLE admin.roles (
  id SERIAL PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  descripcion TEXT,
  es_sistema BOOLEAN DEFAULT FALSE, -- true si es rol fijo (admin, gerente)
  created_at TIMESTAMP DEFAULT NOW()
);

-- Asignación de roles a usuarios (por sistema)
CREATE TABLE admin.usuario_rol (
  usuario_id INTEGER REFERENCES admin.usuarios(id),
  sistema_id INTEGER REFERENCES admin.sistemas(id),
  rol_id INTEGER REFERENCES admin.roles(id),
  fecha_asignacion TIMESTAMP DEFAULT NOW(),
  expira_en TIMESTAMP NULL,
  asignado_por INTEGER REFERENCES admin.usuarios(id),
  PRIMARY KEY (usuario_id, sistema_id)
);

-- Permisos por rol (con expiración)
CREATE TABLE admin.rol_permiso (
  rol_id INTEGER REFERENCES admin.roles(id),
  permiso_id INTEGER REFERENCES admin.permisos(id),
  expira_en TIMESTAMP NULL,
  creado_por INTEGER REFERENCES admin.usuarios(id),
  created_at TIMESTAMP DEFAULT NOW(),
  PRIMARY KEY (rol_id, permiso_id)
);

-- Permisos individuales (excepciones)
CREATE TABLE admin.usuario_permiso_especial (
  usuario_id INTEGER REFERENCES admin.usuarios(id),
  permiso_id INTEGER REFERENCES admin.permisos(id),
  tipo ENUM('otorgar', 'denegar') DEFAULT 'otorgar',
  expira_en TIMESTAMP NULL,
  motivo TEXT,
  creado_por INTEGER REFERENCES admin.usuarios(id),
  created_at TIMESTAMP DEFAULT NOW(),
  PRIMARY KEY (usuario_id, permiso_id)
);

-- Bitácora de cambios de permisos
CREATE TABLE admin.permiso_auditoria (
  id SERIAL PRIMARY KEY,
  usuario_id INTEGER,
  permiso_codigo VARCHAR(100),
  accion VARCHAR(20), -- 'asignar', 'revocar', 'suspender'
  rol_id INTEGER NULL,
  motivo TEXT,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT NOW()
);
```

#### Permisos por Defecto del Sistema

```sql
INSERT INTO admin.permisos (codigo, nombre, modulo, accion, categoria, nivel) VALUES
  ('usuarios.crear', 'Crear Usuarios', 'usuarios', 'crear', 'administracion', 3),
  ('usuarios.editar', 'Editar Usuarios', 'usuarios', 'editar', 'administracion', 3),
  ('usuarios.eliminar', 'Eliminar Usuarios', 'usuarios', 'eliminar', 'administracion', 3),
  ('usuarios.ver', 'Ver Usuarios', 'usuarios', 'ver', 'administracion', 2),
  ('tickets.crear', 'Crear Tickets', 'tickets', 'crear', 'operativo', 1),
  ('tickets.editar', 'Editar Tickets', 'tickets', 'editar', 'operativo', 2),
  ('tickets.eliminar', 'Eliminar Tickets', 'tickets', 'eliminar', 'operativo', 3),
  ('tickets.asignar', 'Asignar Tickets', 'tickets', 'asignar', 'operativo', 2),
  ('tickets.resolver', 'Resolver Tickets', 'tickets', 'resolver', 'operativo', 2),
  ('equipos.ver', 'Ver Equipos', 'equipos', 'ver', 'operativo', 1),
  ('equipos.editar', 'Editar Equipos', 'equipos', 'editar', 'administracion', 2),
  ('equipos.eliminar', 'Eliminar Equipos', 'equipos', 'eliminar', 'administracion', 3),
  ('reportes.ver', 'Ver Reportes', 'reportes', 'ver', 'administracion', 1),
  ('reportes.exportar', 'Exportar Reportes', 'reportes', 'exportar', 'administracion', 2);

-- Roles predefinidos
INSERT INTO admin.roles (nombre, descripcion, es_sistema) VALUES
  ('superadmin', 'Acceso total al sistema', TRUE),
  ('admin', 'Administrador de OTI', TRUE),
  ('gestor', 'Gestor de tickets y equipos', FALSE),
  ('usuario', 'Usuario estándar', FALSE);

-- Permisos por rol
INSERT INTO admin.rol_permiso (rol_id, permiso_id) VALUES
  -- admin: todos los permisos
  ((SELECT id FROM admin.roles WHERE nombre = 'admin'), 
   (SELECT id FROM admin.permisos WHERE codigo = 'usuarios.crear')),
  ((SELECT id FROM admin.roles WHERE nombre = 'admin'), 
   (SELECT id FROM admin.permisos WHERE codigo = 'tickets.crear')),
  ((SELECT id FROM admin.roles WHERE nombre = 'admin'), 
   (SELECT id FROM admin.permisos WHERE codigo = 'tickets.asignar'));

-- usuario: solo operaciones básicas
INSERT INTO admin.rol_permiso (rol_id, permiso_id) VALUES
  ((SELECT id FROM admin.roles WHERE nombre = 'usuario'), 
   (SELECT id FROM admin.permisos WHERE codigo = 'tickets.crear')),
  ((SELECT id FROM admin.roles WHERE nombre = 'usuario'), 
   (SELECT id FROM admin.permisos WHERE codigo = 'tickets.ver'));
```

### 2.2 Backend (Seguridad)

#### Middleware de Autorización

```php
<?php
// app/Middleware/AuthorizationMiddleware.php

class AuthorizationMiddleware 
{
    /**
     * Valida permiso en cada petición HTTP
     * La seguridad REQUIERE verificación en backend, no solo frontend
     */
    public static function authorize(string $permission): void 
    {
        // 1. Verificar autenticación
        if (!AuthService::check()) {
            http_response_code(401);
            jsonResponse(['error' => 'No autenticado'], 401);
        }

        $userId = AuthService::getCurrentUserId();

        // 2. Superadmin: acceso total (bypass de seguridad)
        if (self::isSuperAdmin($userId)) {
            return; // Autorizado
        }

        // 3. Verificar permiso en BD (consulta directa)
        if (!self::userHasPermission($userId, $permission)) {
            // 4. Log de auditoría
            self::logUnauthorizedAccess($userId, $permission);
            
            http_response_code(403);
            jsonResponse(['error' => 'Permiso denegado: ' . $permission], 403);
        }

        // 5. Refresh de caché en segundo plano (no bloquear)
        self::refreshPermissionCacheAsync($userId);
    }

    /**
     * Verificación directa contra base de datos (sin caché)
     */
    private static function userHasPermission(int $userId, string $permissionCode): bool 
    {
        $db = Database::connect();
        
        // Query optimizada con índices
        $stmt = $db->prepare("
            SELECT 1 FROM admin.permisos p
            WHERE p.codigo = :codigo
            AND p.id IN (
                SELECT DISTINCT rp.permiso_id FROM admin.usuario_rol ur
                JOIN admin.rol_permiso rp ON ur.rol_id = rp.rol_id
                WHERE ur.usuario_id = :user_id
                AND (rp.expira_en IS NULL OR rp.expira_en > NOW())
                UNION
                SELECT permiso_id FROM admin.usuario_permiso_especial
                WHERE usuario_id = :user_id AND tipo = 'otorgar'
                AND (expira_en IS NULL OR expira_en > NOW())
            )
            LIMIT 1
        ");
        
        $stmt->execute([
            ':codigo' => $permissionCode,
            ':user_id' => $userId
        ]);
        
        return (bool) $stmt->fetchColumn();
    }
}
```

#### API Endpoint con Autorización

```php
<?php
// app/api/tickets.php

// Aplicar middleware de autorización
AuthorizationMiddleware::authorize('tickets.editar');

// Procesar petición segura
switch ($_GET['action'] ?? '') {
    case 'update':
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validación adicional: propietario o admin
        if (!AuthorizationMiddleware::requireOwnerOrAdmin($ticket['usuario_id'])) {
            http_response_code(403);
            exit;
        }
        
        Ticket::update($_GET['id'], $data);
        jsonResponse(['success' => true]);
        break;
}
```

### 2.3 Frontend (Experiencia de Usuario)

#### Store de Permisos (Contexto Reactivo)

```javascript
// public/assets/js/rbac-store.js

class RBACStore {
    constructor() {
        this.permissions = new Set();
        this.roleHierarchy = {};
        this.cache = {
            data: null,
            timestamp: 0,
            ttl: 300000 // 5 minutos
        };
        this.subscribers = new Set();
        this.ws = null;
        this.initWebSocket();
    }

    async init() {
        // Cargar permisos al iniciar
        const response = await fetch('/app/api/permissions.php?action=get-user-permissions');
        const data = await response.json();
        
        this.permissions = new Set(data.permissions || []);
        this.roleHierarchy = data.role_hierarchy || {};
        this.cache.data = data;
        this.cache.timestamp = Date.now();
        
        this.notify();
    }

    can(permissionCode) {
        // Admin siempre tiene acceso
        if (this.permissions.has('*')) return true;
        
        // Verificar permiso directo
        if (this.permissions.has(permissionCode)) return true;
        
        // Verificar jerarquía (ej: 'tickets.*' incluye 'tickets.editar')
        const parts = permissionCode.split('.');
        for (let i = parts.length - 1; i > 0; i--) {
            const wildcard = parts.slice(0, i).join('.') + '.*';
            if (this.permissions.has(wildcard)) return true;
        }
        
        return false;
    }

    canAny(permissions) {
        return permissions.some(p => this.can(p));
    }

    canAll(permissions) {
        return permissions.every(p => this.can(p));
    }

    initWebSocket() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}/app/api/ws-permissions.php`;
        
        this.ws = new WebSocket(wsUrl);
        
        this.ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            
            switch (data.type) {
                case 'PERMISSION_UPDATED':
                    this.handlePermissionUpdate(data.payload);
                    break;
                case 'ROLE_CHANGED':
                    this.init(); // Recargar todos los permisos
                    break;
            }
        };
    }

    handlePermissionUpdate(payload) {
        payload.permissions.forEach(({ permission, action }) => {
            if (action === 'grant') {
                this.permissions.add(permission);
            } else if (action === 'revoke') {
                this.permissions.delete(permission);
            }
        });
        
        this.cache.timestamp = 0; // Invalidar caché
        this.notify();
    }

    subscribe(callback) {
        this.subscribers.add(callback);
        return () => this.subscribers.delete(callback);
    }

    notify() {
        this.subscribers.forEach(cb => cb(this.getSnapshot()));
    }

    getSnapshot() {
        return {
            permissions: Array.from(this.permissions),
            role: this.currentRole
        };
    }
}

// Instancia global
window.rbac = new RBACStore();
```

#### Componente Botón Dinámico

```html
<!-- public/components/rbac-button.html -->
<script>
class RBACButton extends HTMLElement {
    constructor() {
        super();
        this.permission = this.getAttribute('permission');
        this.variant = this.getAttribute('variant') || 'primary';
        this.loading = false;
    }

    connectedCallback() {
        this.render();
        this.setupPermissionListener();
    }

    render() {
        const hasPermission = window.rbac.can(this.permission);
        
        this.innerHTML = `
            <button 
                class="action-button action-button--${this.variant}"
                aria-disabled="${!hasPermission}"
                data-permission="${this.permission}"
                style="${!hasPermission ? 'display: none;' : ''}"
                type="button">
                ${this.getIcon(this.getAttribute('icon'))}
                <span><slot></slot></span>
            </button>
        `;

        if (hasPermission) {
            this.addEventListener('click', () => this.handleClick());
        }
    }

    setupPermissionListener() {
        window.rbac.subscribe(() => this.render());
    }

    handleClick() {
        const action = this.getAttribute('action');
        const target = this.getAttribute('target');
        
        this.setLoading(true);
        
        switch (action) {
            case 'redirect':
                window.location.href = target;
                break;
            case 'submit':
                this.submitForm();
                break;
            case 'api':
                this.callAPI(target);
                break;
        }
    }

    setLoading(isLoading) {
        this.loading = isLoading;
        const btn = this.querySelector('button');
        if (isLoading) {
            btn.disabled = true;
            btn.style.cursor = 'wait';
        }
    }
}

customElements.define('rbac-button', RBACButton);
</script>
```

#### Directiva Blade/Twig para PHP

```php
<?php
// app/Helpers/PermissionHelper.php

function renderButton(string $permission, string $variant, string $text, array $attrs = []) {
    $userId = AuthService::getCurrentUserId();
    $isAdmin = AuthService::isAdmin();
    
    // Verificación rápida sin caché
    $visible = $isAdmin ? true : AuthService::hasPermission($userId, $permission);
    
    if (!$visible) {
        return ''; // No renderizar
    }
    
    $attrString = '';
    foreach ($attrs as $key => $value) {
        $attrString .= "{$key}=\"{$value}\" ";
    }
    
    return "<button class=\"action-button action-button--{$variant}\" {$attrString}>{$text}</button>";
}
?>
```

#### Uso en Templates

```php
<!-- app/Views/admin/tickets.php -->
<td>
    <?= renderButton('tickets.editar', 'primary', 'Editar', ['onclick' => "editTicket({$ticket['id']})"]) ?>
    <?= renderButton('tickets.eliminar', 'danger', 'Eliminar', ['onclick' => "deleteTicket({$ticket['id']})"]) ?>
</td>
```

### 2.4 Manejo de Superadministrador

```php
<?php
// app/Services/AuthService.php - Método isSuperAdmin

public static function isSuperAdmin(): bool {
    // Verificar flag en sesión
    if (!isset($_SESSION['user']['es_admin'])) {
        return false;
    }
    
    // Doble verificación en BD (evitar manipulación de sesión)
    $userId = self::getCurrentUserId();
    $stmt = self::db()->prepare("
        SELECT es_admin FROM admin.usuarios WHERE id = :id LIMIT 1
    ");
    $stmt->execute(['id' => $userId]);
    
    return (bool) $stmt->fetchColumn();
}
?>
```

### 2.5 Gestión de Sesión y Sincronización

```php
<?php
// app/api/permissions.php

header('Content-Type: application/json');

if (!AuthService::check()) {
    jsonResponse(['error' => 'No autenticado'], 401);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get-user-permissions':
        $userId = AuthService::getCurrentUserId();
        
        // Forzar refresh desde BD
        AuthService::invalidatePermissionCache($userId);
        $permissions = AuthService::getPermissions($userId);
        
        jsonResponse([
            'permissions' => $permissions,
            'role_hierarchy' => getRoleHierarchy(),
            'expires_at' => time() + 300
        ]);
        break;

    case 'check-permission':
        $permission = $_GET['permission'] ?? '';
        $hasAccess = AuthService::hasPermission($permission);
        jsonResponse(['has_access' => $hasAccess]);
        break;
}
?>
```

#### WebSocket para Actualizaciones en Tiempo Real

```php
<?php
// app/api/ws-permissions.php

// Server-Sent Events como alternativa ligera
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$userId = $_SESSION['user_id'] ?? null;

while (true) {
    // Consultar cambios en BD (polling cada 30s)
    $changes = checkPermissionChanges($userId);
    
    if ($changes) {
        echo "data: " . json_encode($changes) . "\n\n";
        ob_flush();
        flush();
    }
    
    sleep(30);
}
?>
```

---

## 3. MEJORES PRÁCTICAS

### 3.1 Principio de Menor Privilegio

- Asignar solo los permisos necesarios para la función
- Usar roles basados en funciones, no en personas
- Revisar y rotar permisos periódicamente

### 3.2 Cache y Rendimiento

```php
// TTL corto para caché de permisos (5 min)
// Invalidar caché al modificar roles/permisos
// Usar Redis/Memcached para cache distribuido en multi-server
```

### 3.3 Auditoría y Seguridad

- Log de todos los cambios de permisos
- Detección de anomalías (ej: muchos intentos fallidos)
- Alertas por cambios críticos de permisos

### 3.4 Escalabilidad

- Índices en columnas `codigo`, `usuario_id`, `rol_id`
- Queries optimizadas con `EXISTS` vs `IN` para grandes datasets
- Paginación en listados de permisos

---

## 4. FLUJO DE TRABAJO RECOMENDADO

1. **Definir permisos** en `admin.permisos`
2. **Crear roles** y asignar permisos en `admin.rol_permiso`
3. **Asignar roles** a usuarios en `admin.usuario_rol`
4. **Frontend** consume `/api/permissions.php?action=get-user-permissions`
5. **Botones dinámicos** se renderizan según permisos
6. **WebSocket** notifica cambios en tiempo real
7. **Middleware** valida en cada petición backend
8. **Auditoría** registra todo acceso denegado