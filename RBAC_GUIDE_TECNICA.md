# 📘 Guía Técnica Integral - Sistema RBAC de Botones Dinámicos

**Versión:** 1.0  
**Fecha:** 2026-05-27  
**Sistema:** OTI (Gestor de Tickets y Equipos)  
**Objetivo:** Implementar control de acceso basado en roles (RBAC) con componentes visuales dinámicos, seguridad centralizada en servidor y sincronización en tiempo real.

---

## 📑 Tabla de Contenidos

1. [PILAR 1: Diseño UI/UX de Componentes de Acción](#pilar-1-diseño-uiux-de-componentes-de-acción)
2. [PILAR 2: Arquitectura de Implementación RBAC](#pilar-2-arquitectura-de-implementación-rbac)
3. [Mejores Prácticas y Patrones](#mejores-prácticas-y-patrones)
4. [Troubleshooting y FAQ](#troubleshooting-y-faq)

---

# PILAR 1: Diseño UI/UX de Componentes de Acción

## 1.1 Especificación de Botones por Tipo

### 1.1.1 Botón Primario
**Uso:** Acciones principales, llamadas a la acción destacadas.  
**Ejemplos:** "Crear Ticket", "Guardar", "Enviar", "Confirmar"

```html
<button class="btn btn-primary" data-permission="tickets.crear">
  <span class="btn-icon">➕</span>
  <span class="btn-text">Crear Ticket</span>
</button>
```

**Estilos Base (CSS):**
```css
.btn-primary {
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
  color: white;
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  border: none;
  font-size: var(--font-size-base);
  font-weight: 600;
  cursor: pointer;
  transition: all var(--duration-normal) var(--ease-out);
  box-shadow: 0 4px 14px rgba(15, 41, 66, 0.2);
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(15, 41, 66, 0.3);
}

.btn-primary:active:not(:disabled) {
  transform: translateY(0);
  box-shadow: 0 2px 8px rgba(15, 41, 66, 0.2);
}

.btn-primary:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}
```

---

### 1.1.2 Botón Secundario
**Uso:** Acciones opcionales, alternativas.  
**Ejemplos:** "Cancelar", "Cerrar", "Descartar"

```html
<button class="btn btn-secondary" data-permission="tickets.ver">
  <span class="btn-text">Ver Detalles</span>
</button>
```

**Estilos Base:**
```css
.btn-secondary {
  background: var(--bg-card);
  color: var(--text-secondary);
  padding: var(--space-3) var(--space-4);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  font-weight: 500;
  cursor: pointer;
  transition: all var(--duration-normal) var(--ease-out);
}

.btn-secondary:hover:not(:disabled) {
  border-color: var(--primary);
  color: var(--primary);
  background: var(--primary-soft);
}

.btn-secondary:active:not(:disabled) {
  background: rgba(15, 41, 66, 0.12);
}

.btn-secondary:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}

.btn-secondary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
```

---

### 1.1.3 Botón Destructivo
**Uso:** Acciones peligrosas, irreversibles.  
**Ejemplos:** "Eliminar", "Cancelar Ticket", "Limpiar"

```html
<button class="btn btn-danger" data-permission="tickets.eliminar">
  <span class="btn-icon">🗑️</span>
  <span class="btn-text">Eliminar</span>
</button>
```

**Estilos Base:**
```css
.btn-danger {
  background: var(--danger);
  color: white;
  padding: var(--space-3) var(--space-4);
  border: 1px solid var(--danger);
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  font-weight: 600;
  cursor: pointer;
  transition: all var(--duration-normal) var(--ease-out);
  box-shadow: 0 2px 8px rgba(185, 28, 28, 0.2);
}

.btn-danger:hover:not(:disabled) {
  background: #a01a1a;
  box-shadow: 0 4px 12px rgba(185, 28, 28, 0.3);
}

.btn-danger:active:not(:disabled) {
  background: #8f1515;
}

.btn-danger:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
```

---

### 1.1.4 Botón de Carga (Loading)
**Uso:** Durante operaciones asincrónicas.  
**Ejemplos:** Mientras se guarda, se envía, se procesa

```html
<button class="btn btn-primary is-loading" disabled data-permission="tickets.crear">
  <span class="btn-spinner"></span>
  <span class="btn-text">Guardando...</span>
</button>
```

**Estilos:**
```css
.btn.is-loading {
  position: relative;
}

.btn-spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
```

---

## 1.2 Estados de Interacción

### 1.2.1 Estados Visuales Completos

| Estado | Descripción | Aplicar Cuando |
|--------|-------------|----------------|
| **Normal** | Botón visible, interactivo | Usuario tiene permiso |
| **Hover** | Cambio de color/sombra, cursor pointer | Mouse encima |
| **Active/Press** | Presionado, más plano | Clic en progreso |
| **Focus** | Anillo/outline visible | Navegación por teclado |
| **Disabled** | Opacidad reducida, cursor not-allowed | Falta de permiso o datos inválidos |
| **Loading** | Spinner, texto "Cargando...", deshabilitado | Operación en progreso |
| **Success** | Ícono ✓, breve feedback visual | Operación completada |
| **Error** | Icono ✗, color rojo, mensaje | Operación fallida |
| **Oculto** | display: none | Usuario sin permiso |

### 1.2.2 Transiciones Suaves

```css
/* Transición global para todos los botones */
.btn {
  transition: all var(--duration-normal) var(--ease-out);
}

/* Estados específicos con duraciones personalizadas */
.btn.is-loading {
  transition: opacity var(--duration-fast) var(--ease-out);
}

.btn.is-success {
  animation: successPulse 0.6s var(--ease-spring);
}

@keyframes successPulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.05); }
  100% { transform: scale(1); }
}
```

---

## 1.3 Estándares de Accesibilidad (WCAG 2.1)

### 1.3.1 Etiquetado ARIA

```html
<!-- Botón con permiso insuficiente (oculto) -->
<button 
  class="btn btn-primary" 
  data-permission="usuarios.eliminar"
  aria-hidden="true"
  tabindex="-1"
  disabled
  title="Permiso requerido: Eliminar usuarios">
  Eliminar Usuario
</button>

<!-- Botón con indicador de loading -->
<button 
  class="btn btn-primary is-loading" 
  aria-busy="true"
  aria-label="Guardando cambios">
  <span class="btn-spinner" aria-label="Cargando"></span>
  Guardar
</button>

<!-- Botón con menú desplegable -->
<button 
  class="btn btn-secondary" 
  aria-haspopup="menu"
  aria-expanded="false"
  aria-controls="menu-acciones">
  Más opciones
</button>
<menu id="menu-acciones" role="menu">
  <li><a href="#" role="menuitem">Editar</a></li>
  <li><a href="#" role="menuitem">Duplicar</a></li>
</menu>
```

### 1.3.2 Contraste de Colores

**Requisito WCAG:** Mínimo 4.5:1 para texto normal, 3:1 para texto grande

```css
/* Verificar con herramientas: https://webaim.org/resources/contrastchecker/ */

/* ✅ CORRECTO - Contraste 7.2:1 */
.btn-primary {
  background: var(--primary);  /* #0f2942 */
  color: white;                 /* #ffffff */
}

/* ✅ CORRECTO - Contraste 5.1:1 */
.btn-secondary {
  background: var(--bg-card);   /* #ffffff */
  color: var(--text-secondary); /* #4a5e78 */
}

/* ✗ INCORRECTO - Contraste 2.8:1 */
.btn-danger-low-contrast {
  background: var(--danger);    /* #b91c1c */
  color: #ff6b6b;               /* Demasiado similar */
}
```

### 1.3.3 Navegación por Teclado

```html
<!-- Asegurar que los botones son navegables y activables -->
<button class="btn btn-primary" data-permission="tickets.crear">
  Crear Ticket
</button>

<!-- Script para mejorar accesibilidad -->
<script>
document.querySelectorAll('[data-permission]').forEach(btn => {
  // Tab -> navegable (por defecto en botones)
  // Enter/Espacio -> activa (por defecto en botones)
  
  // Prevenir activación si no tiene permiso
  if (btn.hasAttribute('aria-hidden')) {
    btn.setAttribute('tabindex', '-1');
  }
});
</script>
```

### 1.3.4 Etiquetas y Descripciones Claras

```html
<!-- ✅ BIEN: Etiqueta clara y descripción -->
<button 
  class="btn btn-danger" 
  data-permission="tickets.eliminar"
  title="Eliminar este ticket permanentemente (no se puede deshacer)">
  🗑️ Eliminar Ticket
</button>

<!-- ✗ MAL: Ambiguo -->
<button class="btn btn-danger" title="Borrar">X</button>

<!-- ✅ BIEN: Para usuarios sin permiso -->
<button 
  class="btn btn-primary" 
  disabled 
  aria-disabled="true"
  title="Necesitas permiso 'tickets.eliminar' para eliminar">
  Eliminar
</button>
```

---

## 1.4 Sistema de Retroalimentación Visual

### 1.4.1 Toast Notifications (Notificaciones Flotantes)

```html
<!-- Éxito -->
<div class="toast toast-success" role="alert">
  <span class="toast-icon">✓</span>
  <span class="toast-message">Ticket guardado exitosamente</span>
</div>

<!-- Error -->
<div class="toast toast-error" role="alert">
  <span class="toast-icon">✗</span>
  <span class="toast-message">Error: No tienes permiso para eliminar</span>
</div>

<!-- Info -->
<div class="toast toast-info" role="alert">
  <span class="toast-icon">ℹ</span>
  <span class="toast-message">Cambios sincronizados</span>
</div>
```

**Estilos:**
```css
.toast {
  position: fixed;
  bottom: var(--space-4);
  right: var(--space-4);
  background: white;
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-4);
  display: flex;
  align-items: center;
  gap: var(--space-2);
  animation: slideInUp 0.3s var(--ease-out);
  z-index: var(--z-toast);
}

.toast-success {
  border-left: 4px solid var(--success);
  color: var(--success);
}

.toast-error {
  border-left: 4px solid var(--danger);
  color: var(--danger);
}

@keyframes slideInUp {
  from {
    transform: translateY(100%);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}
```

### 1.4.2 Estados de Validación

```html
<!-- Campo con error de validación -->
<div class="form-group">
  <label for="titulo">Título</label>
  <input 
    id="titulo" 
    type="text" 
    class="form-input is-error"
    aria-invalid="true"
    aria-describedby="titulo-error">
  <span id="titulo-error" class="form-error">
    El título es requerido (mínimo 3 caracteres)
  </span>
</div>

<style>
.form-input.is-error {
  border-color: var(--danger);
  background-color: var(--danger-soft);
}

.form-error {
  color: var(--danger);
  font-size: var(--font-size-sm);
  margin-top: var(--space-1);
  display: block;
}
</style>
```

---

## 1.5 Iconografía y Semántica

### 1.5.1 Mapa de Iconos Estándar

```html
<!-- Crear/Agregar -->
<button class="btn btn-primary">
  <span class="btn-icon">➕</span> Crear
</button>

<!-- Editar/Modificar -->
<button class="btn btn-secondary">
  <span class="btn-icon">✏️</span> Editar
</button>

<!-- Eliminar/Borrar -->
<button class="btn btn-danger">
  <span class="btn-icon">🗑️</span> Eliminar
</button>

<!-- Ver/Expandir -->
<button class="btn btn-secondary">
  <span class="btn-icon">👁️</span> Ver
</button>

<!-- Guardar/Confirmar -->
<button class="btn btn-primary">
  <span class="btn-icon">💾</span> Guardar
</button>

<!-- Cancelar/Rechazar -->
<button class="btn btn-secondary">
  <span class="btn-icon">✕</span> Cancelar
</button>

<!-- Descargar/Exportar -->
<button class="btn btn-secondary">
  <span class="btn-icon">⬇️</span> Descargar
</button>

<!-- Compartir -->
<button class="btn btn-secondary">
  <span class="btn-icon">🔗</span> Compartir
</button>
```

**CSS para iconos:**
```css
.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: var(--font-size-md);
  line-height: 1;
}

.btn-primary .btn-icon {
  filter: brightness(1.2);
}
```

---

# PILAR 2: Arquitectura de Implementación RBAC

## 2.1 Diseño de Base de Datos

### 2.1.1 Tablas Principales

```sql
-- 1. Tabla de Permisos (Granulares)
CREATE TABLE IF NOT EXISTS admin.permisos (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(100) UNIQUE NOT NULL,      -- ej: 'tickets.crear', 'usuarios.editar'
    nombre VARCHAR(255) NOT NULL,              -- ej: 'Crear Tickets'
    descripcion TEXT,                          -- Descripción detallada
    modulo VARCHAR(50) NOT NULL,               -- 'tickets', 'usuarios', 'equipos'
    accion VARCHAR(50) NOT NULL,               -- 'crear', 'editar', 'eliminar', 'ver'
    nivel_riesgo ENUM('bajo', 'medio', 'alto') DEFAULT 'medio',
    requiere_confirmacion BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_modulo (modulo)
);

-- 2. Tabla de Roles
CREATE TABLE IF NOT EXISTS admin.roles (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) UNIQUE NOT NULL,      -- ej: 'Administrador', 'Técnico', 'Usuario'
    descripcion TEXT,
    es_predeterminado BOOLEAN DEFAULT FALSE,
    visible_para_admin BOOLEAN DEFAULT TRUE,
    nivel_jerarquia INT DEFAULT 0,            -- 0=Usuario, 100=Superadmin
    sistema_id INT NOT NULL REFERENCES admin.sistemas(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre),
    INDEX idx_sistema_id (sistema_id)
);

-- 3. Tabla de Asignación Rol-Permiso (Many-to-Many)
CREATE TABLE IF NOT EXISTS admin.rol_permiso (
    rol_id INT NOT NULL REFERENCES admin.roles(id) ON DELETE CASCADE,
    permiso_id INT NOT NULL REFERENCES admin.permisos(id) ON DELETE CASCADE,
    otorgado_por INT REFERENCES admin.usuarios(id),
    otorgado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira_en TIMESTAMP,                       -- Permisos temporales (opcional)
    PRIMARY KEY (rol_id, permiso_id),
    INDEX idx_rol_id (rol_id),
    INDEX idx_permiso_id (permiso_id)
);

-- 4. Tabla de Permisos Especiales por Usuario (Override)
CREATE TABLE IF NOT EXISTS admin.usuario_permiso_especial (
    usuario_id INT NOT NULL REFERENCES admin.usuarios(id) ON DELETE CASCADE,
    permiso_id INT NOT NULL REFERENCES admin.permisos(id) ON DELETE CASCADE,
    otorgado_por INT REFERENCES admin.usuarios(id),
    razon TEXT,                                -- Justificación del override
    otorgado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira_en TIMESTAMP,
    PRIMARY KEY (usuario_id, permiso_id),
    INDEX idx_usuario_id (usuario_id)
);

-- 5. Tabla de Auditoría (Cambios de Permisos)
CREATE TABLE IF NOT EXISTS admin.permiso_audit_log (
    id BIGSERIAL PRIMARY KEY,
    usuario_id INT NOT NULL REFERENCES admin.usuarios(id),
    accion VARCHAR(50),                        -- 'asignar_rol', 'revocar_rol', 'crear_permiso'
    tabla_afectada VARCHAR(100),
    id_registro INT,
    cambios JSONB,                             -- Cambios realizados
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_created_at (created_at)
);

-- 6. Caché de Permisos (Para Optimización)
CREATE TABLE IF NOT EXISTS admin.permiso_cache (
    usuario_id INT PRIMARY KEY REFERENCES admin.usuarios(id) ON DELETE CASCADE,
    permisos JSONB NOT NULL,                   -- Array de códigos de permisos
    roles_ids JSONB NOT NULL,                  -- Array de IDs de roles
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira_en TIMESTAMP
);
```

### 2.1.2 Índices para Optimización

```sql
-- Índices compuestos para queries frecuentes
CREATE INDEX idx_usuario_rol_sistema ON admin.usuario_rol(usuario_id, sistema_id);
CREATE INDEX idx_rol_permiso_rol ON admin.rol_permiso(rol_id) INCLUDE (permiso_id);
CREATE INDEX idx_rol_permiso_permiso ON admin.rol_permiso(permiso_id);

-- Índice para audit log con rango de fechas
CREATE INDEX idx_audit_log_usuario_fecha 
  ON admin.permiso_audit_log(usuario_id, created_at DESC);
```

### 2.1.3 Estructura de Permisos Estándar (Seeds)

```sql
-- Ejemplo de permisos para módulo 'tickets'
INSERT INTO admin.permisos (codigo, nombre, descripcion, modulo, accion, nivel_riesgo) VALUES
('tickets.crear', 'Crear Tickets', 'Permite crear nuevos tickets de soporte', 'tickets', 'crear', 'bajo'),
('tickets.editar', 'Editar Tickets', 'Permite editar tickets existentes', 'tickets', 'editar', 'medio'),
('tickets.eliminar', 'Eliminar Tickets', 'Permite eliminar tickets (irreversible)', 'tickets', 'eliminar', 'alto'),
('tickets.ver', 'Ver Tickets', 'Permite ver lista y detalles de tickets', 'tickets', 'ver', 'bajo'),
('tickets.asignar', 'Asignar Tickets', 'Permite asignar tickets a técnicos', 'tickets', 'asignar', 'medio'),
('tickets.comentar', 'Comentar Tickets', 'Permite agregar comentarios a tickets', 'tickets', 'comentar', 'bajo'),
('tickets.cerrar', 'Cerrar Tickets', 'Permite cerrar tickets resueltos', 'tickets', 'cerrar', 'medio'),
('tickets.reabrir', 'Reabrir Tickets', 'Permite reabrir tickets cerrados', 'tickets', 'reabrir', 'medio');

-- Ejemplo de roles estándar
INSERT INTO admin.roles (nombre, descripcion, nivel_jerarquia, sistema_id) VALUES
('Superadministrador', 'Acceso total al sistema', 100, 1),
('Administrador', 'Gestión de usuarios y configuración', 80, 1),
('Técnico Senior', 'Gestión completa de tickets', 60, 1),
('Técnico', 'Gestión básica de tickets', 40, 1),
('Usuario', 'Crear y ver sus propios tickets', 10, 1);
```

---

## 2.2 Backend - Capas de Seguridad

### 2.2.1 Middleware de Autorización

**Archivo:** `app/Middleware/AuthorizationMiddleware.php`

```php
<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use App\Helpers\Logger;

class AuthorizationMiddleware
{
    /**
     * Verifica que el usuario actual tenga el permiso requerido
     * 
     * @param string $requiredPermission - Código del permiso ej: 'tickets.crear'
     * @param ?callable $callback - Callback customizado de autorización
     * @return bool
     * @throws \Exception Si el usuario no tiene permiso
     */
    public static function requirePermission(
        string $requiredPermission, 
        ?callable $callback = null
    ): bool {
        // 1. Verificar que el usuario esté autenticado
        if (!AuthService::isAuthenticated()) {
            http_response_code(401);
            throw new \Exception('No autenticado', 401);
        }

        $userId = AuthService::getCurrentUserId();
        $user = AuthService::getCurrentUser();

        // 2. Superadmin bypass automático
        if (AuthService::isAdmin($userId)) {
            return true;
        }

        // 3. Verificar permiso
        if (!AuthService::hasPermission($userId, $requiredPermission)) {
            // Log del intento fallido
            Logger::logAuthFailure(
                userId: $userId,
                attemptedPermission: $requiredPermission,
                userEmail: $user['email'] ?? 'unknown',
                ipAddress: $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            );

            http_response_code(403);
            throw new \Exception(
                "No tienes permiso para: $requiredPermission",
                403
            );
        }

        // 4. Callback customizado (opcional)
        if ($callback && !$callback($user)) {
            http_response_code(403);
            throw new \Exception('Validación customizada fallida', 403);
        }

        return true;
    }

    /**
     * Verifica permisos múltiples (OR logic)
     */
    public static function requireAnyPermission(array $permissions): bool
    {
        $userId = AuthService::getCurrentUserId();

        foreach ($permissions as $permission) {
            if (AuthService::hasPermission($userId, $permission)) {
                return true;
            }
        }

        http_response_code(403);
        throw new \Exception('No tienes ninguno de los permisos requeridos', 403);
    }

    /**
     * Verifica permisos múltiples (AND logic)
     */
    public static function requireAllPermissions(array $permissions): bool
    {
        $userId = AuthService::getCurrentUserId();

        foreach ($permissions as $permission) {
            if (!AuthService::hasPermission($userId, $permission)) {
                http_response_code(403);
                throw new \Exception("No tienes permiso: $permission", 403);
            }
        }

        return true;
    }
}
```

### 2.2.2 Servicio de Autenticación/Autorización

**Archivo:** `app/Services/AuthService.php`

```php
<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class AuthService
{
    private static array $permissionCache = [];
    private static const CACHE_TTL = 300; // 5 minutos

    /**
     * Verifica si el usuario actual está autenticado
     */
    public static function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Obtiene el ID del usuario autenticado
     */
    public static function getCurrentUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Obtiene los datos completos del usuario actual
     */
    public static function getCurrentUser(): ?array
    {
        $userId = self::getCurrentUserId();
        if (!$userId) {
            return null;
        }

        return self::getUserById($userId);
    }

    /**
     * Obtiene usuario por ID con datos de rol y permisos
     */
    public static function getUserById(int $userId): ?array
    {
        $pdo = Database::connect();
        
        $stmt = $pdo->prepare("
            SELECT 
                u.id, u.nombre, u.apellidos, u.email, u.es_admin,
                r.id as role_id, r.nombre as role_name, r.nivel_jerarquia
            FROM admin.usuarios u
            LEFT JOIN admin.usuario_rol ur ON u.id = ur.usuario_id
            LEFT JOIN admin.roles r ON ur.rol_id = r.id
            WHERE u.id = :user_id AND u.activo = TRUE
            LIMIT 1
        ");

        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Verifica si un usuario es superadministrador
     */
    public static function isAdmin(int $userId): bool
    {
        $user = self::getUserById($userId);
        
        // Dos formas de ser admin:
        // 1. Flag es_admin en la tabla usuarios
        // 2. Rol con nivel_jerarquia >= 100
        
        return ($user['es_admin'] ?? false) || ($user['nivel_jerarquia'] ?? 0) >= 100;
    }

    /**
     * Verifica si usuario tiene un permiso específico
     */
    public static function hasPermission(int $userId, string $permissionCode): bool
    {
        // 1. Si es admin, tiene todos los permisos
        if (self::isAdmin($userId)) {
            return true;
        }

        // 2. Verificar caché
        $cacheKey = "user_perms_{$userId}";
        if (isset(self::$permissionCache[$cacheKey])) {
            $cached = self::$permissionCache[$cacheKey];
            if (isset($cached['expires']) && $cached['expires'] > time()) {
                return in_array($permissionCode, $cached['permissions'], true);
            }
        }

        // 3. Consultar BD
        $permissions = self::getUserPermissions($userId);

        // 4. Cachear resultado
        self::$permissionCache[$cacheKey] = [
            'permissions' => $permissions,
            'expires' => time() + self::CACHE_TTL
        ];

        return in_array($permissionCode, $permissions, true);
    }

    /**
     * Obtiene todos los permisos de un usuario
     */
    public static function getUserPermissions(int $userId): array
    {
        $pdo = Database::connect();

        // Si es admin, retorna array especial
        if (self::isAdmin($userId)) {
            return ['*']; // Wildcard para todos los permisos
        }

        // Query: Permisos a través de roles + permisos especiales
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.codigo
            FROM admin.permisos p
            WHERE 
                -- Permisos a través de roles asignados
                p.id IN (
                    SELECT DISTINCT rp.permiso_id
                    FROM admin.usuario_rol ur
                    JOIN admin.rol_permiso rp ON ur.rol_id = rp.rol_id
                    WHERE ur.usuario_id = :user_id 
                      AND ur.sistema_id = (SELECT id FROM admin.sistemas WHERE slug = 'oti')
                      AND (rp.expira_en IS NULL OR rp.expira_en > NOW())
                )
                OR
                -- Permisos especiales por usuario
                p.id IN (
                    SELECT DISTINCT permiso_id
                    FROM admin.usuario_permiso_especial
                    WHERE usuario_id = :user_id
                      AND (expira_en IS NULL OR expira_en > NOW())
                )
            ORDER BY p.codigo
        ");

        $stmt->execute(['user_id' => $userId]);
        $results = $stmt->fetchAll();

        return array_map(fn($row) => $row['codigo'], $results);
    }

    /**
     * Invalidar caché de permisos de un usuario
     */
    public static function invalidatePermissionCache(int $userId): void
    {
        $cacheKey = "user_perms_{$userId}";
        unset(self::$permissionCache[$cacheKey]);

        // También limpiar en base de datos si se usa caché persistente
        $pdo = Database::connect();
        $pdo->prepare("
            DELETE FROM admin.permiso_cache 
            WHERE usuario_id = :user_id
        ")->execute(['user_id' => $userId]);
    }

    /**
     * Cierra la sesión del usuario
     */
    public static function logout(): void
    {
        $userId = self::getCurrentUserId();
        if ($userId) {
            self::invalidatePermissionCache($userId);
        }

        session_destroy();
        unset($_SESSION);
    }
}
```

### 2.2.3 API de Sincronización de Permisos

**Archivo:** `app/Controller/Api/PermissionsController.php`

```php
<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Services\AuthService;
use App\Middleware\AuthorizationMiddleware;
use App\Core\View;

class PermissionsController
{
    /**
     * GET /api/permissions/current
     * Retorna los permisos del usuario actual
     */
    public function current()
    {
        if (!AuthService::isAuthenticated()) {
            http_response_code(401);
            View::json(['error' => 'No autenticado']);
            return;
        }

        try {
            $userId = AuthService::getCurrentUserId();
            $user = AuthService::getCurrentUser();
            $permissions = AuthService::getUserPermissions($userId);

            // Header de caché - permitir caché de 5 minutos
            header('Cache-Control: public, max-age=300');
            header('ETag: "' . md5(json_encode($permissions)) . '"');

            http_response_code(200);
            View::json([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'nombre' => $user['nombre'],
                    'email' => $user['email'],
                    'is_admin' => AuthService::isAdmin($userId)
                ],
                'permissions' => $permissions,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            View::json(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/permissions/refresh
     * Fuerza actualización de permisos del usuario (invalida caché)
     */
    public function refresh()
    {
        if (!AuthService::isAuthenticated()) {
            http_response_code(401);
            View::json(['error' => 'No autenticado']);
            return;
        }

        try {
            $userId = AuthService::getCurrentUserId();

            // Invalidar caché
            AuthService::invalidatePermissionCache($userId);

            // Obtener permisos frescos
            $permissions = AuthService::getUserPermissions($userId);

            http_response_code(200);
            View::json([
                'success' => true,
                'permissions' => $permissions,
                'message' => 'Permisos actualizados',
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            View::json(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/permissions/check/:permission
     * Verifica si usuario tiene un permiso específico
     */
    public function check(string $permission = '')
    {
        if (!AuthService::isAuthenticated()) {
            http_response_code(401);
            View::json(['error' => 'No autenticado']);
            return;
        }

        try {
            $userId = AuthService::getCurrentUserId();
            $has_permission = AuthService::hasPermission($userId, $permission);

            http_response_code(200);
            View::json([
                'success' => true,
                'permission' => $permission,
                'has_permission' => $has_permission
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            View::json(['error' => $e->getMessage()]);
        }
    }
}
```

---

## 2.3 Frontend - Gestión de Permisos

### 2.3.1 PermissionManager en JavaScript

**Archivo:** `public/js/PermissionManager.js`

```javascript
/**
 * PermissionManager - Gestor centralizado de permisos en cliente
 * 
 * Responsabilidades:
 * - Cargar permisos del servidor
 * - Cachear localmente
 * - Mostrar/ocultar elementos según permisos
 * - Sincronizar cambios en tiempo real
 */
class PermissionManager {
  constructor(options = {}) {
    this.permissions = [];
    this.user = null;
    this.isAdmin = false;
    this.cacheTTL = options.cacheTTL || 5 * 60 * 1000; // 5 minutos
    this.listeners = [];
    this.refreshing = false;

    this._initCache();
    this._bindElements();
  }

  /**
   * Inicializa el sistema de caché local
   */
  _initCache() {
    const cached = localStorage.getItem('_perms_cache');
    if (cached) {
      try {
        const data = JSON.parse(cached);
        if (data.expires > Date.now()) {
          this.permissions = data.permissions;
          this.user = data.user;
          this.isAdmin = data.isAdmin;
          return;
        }
      } catch (e) {
        console.warn('Cache corrupted, clearing', e);
      }
    }
    localStorage.removeItem('_perms_cache');
  }

  /**
   * Guarda en caché local
   */
  _saveToCache() {
    localStorage.setItem('_perms_cache', JSON.stringify({
      permissions: this.permissions,
      user: this.user,
      isAdmin: this.isAdmin,
      expires: Date.now() + this.cacheTTL
    }));
  }

  /**
   * Vincula elementos HTML con permisos
   */
  _bindElements() {
    document.addEventListener('DOMContentLoaded', () => {
      this._updateAllElements();
    });
  }

  /**
   * Carga permisos del servidor
   */
  async load() {
    if (this.refreshing) return;

    try {
      this.refreshing = true;
      const response = await fetch('/api/permissions/current');

      if (!response.ok) {
        if (response.status === 401) {
          this._handleUnauthorized();
        }
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      
      this.permissions = data.permissions || [];
      this.user = data.user || {};
      this.isAdmin = data.user?.is_admin || false;

      this._saveToCache();
      this._updateAllElements();
      this._notifyListeners('loaded');

      return data;

    } catch (error) {
      console.error('Error loading permissions:', error);
      this._notifyListeners('error', error);
      throw error;

    } finally {
      this.refreshing = false;
    }
  }

  /**
   * Verifica si usuario tiene un permiso
   */
  can(permission) {
    if (this.isAdmin) return true;
    if (this.permissions.includes('*')) return true;
    return this.permissions.includes(permission);
  }

  /**
   * Verifica si usuario tiene TODOS los permisos
   */
  canAll(...permissions) {
    return permissions.every(p => this.can(p));
  }

  /**
   * Verifica si usuario tiene ALGUNO de los permisos
   */
  canAny(...permissions) {
    return permissions.some(p => this.can(p));
  }

  /**
   * Actualiza la UI según permisos
   */
  _updateAllElements() {
    // Actualizar botones con data-permission
    document.querySelectorAll('[data-permission]').forEach(el => {
      const permission = el.dataset.permission;
      const hasPermission = this.can(permission);

      if (hasPermission) {
        el.removeAttribute('disabled');
        el.removeAttribute('aria-hidden');
        el.setAttribute('tabindex', '0');
        el.classList.remove('btn-disabled');
      } else {
        el.setAttribute('disabled', 'disabled');
        el.setAttribute('aria-hidden', 'true');
        el.setAttribute('tabindex', '-1');
        el.classList.add('btn-disabled');
        el.title = `Permiso requerido: ${permission}`;
      }
    });

    // Actualizar contenedores con data-permission-group
    document.querySelectorAll('[data-permission-group]').forEach(el => {
      const permissions = el.dataset.permissionGroup.split(',');
      const hasPermission = this.canAny(...permissions);
      el.style.display = hasPermission ? '' : 'none';
    });

    // Actualizar elementos condicionales
    document.querySelectorAll('[data-permission-hide]').forEach(el => {
      const permission = el.dataset.permissionHide;
      const hasPermission = this.can(permission);
      el.style.display = hasPermission ? 'none' : '';
    });
  }

  /**
   * Refresca permisos desde servidor
   */
  async refresh() {
    try {
      const response = await fetch('/api/permissions/refresh', {
        method: 'POST'
      });

      if (!response.ok) throw new Error('Refresh failed');

      const data = await response.json();
      this.permissions = data.permissions || [];
      this._saveToCache();
      this._updateAllElements();
      this._notifyListeners('refreshed');

      return data;

    } catch (error) {
      console.error('Error refreshing permissions:', error);
      this._notifyListeners('refresh_error', error);
    }
  }

  /**
   * Registra listener para cambios de permisos
   */
  onChange(callback) {
    this.listeners.push(callback);
  }

  /**
   * Notifica cambios a listeners
   */
  _notifyListeners(event, data = null) {
    this.listeners.forEach(cb => {
      try {
        cb({ event, data, timestamp: Date.now() });
      } catch (e) {
        console.error('Listener error:', e);
      }
    });
  }

  /**
   * Manejo de sesión expirada
   */
  _handleUnauthorized() {
    localStorage.removeItem('_perms_cache');
    this.permissions = [];
    this.user = null;
    window.location.href = '/login';
  }

  /**
   * Limpia caché y permisos (logout)
   */
  clear() {
    localStorage.removeItem('_perms_cache');
    this.permissions = [];
    this.user = null;
    this.isAdmin = false;
    this._notifyListeners('cleared');
  }
}

// Instancia global
window.permissions = new PermissionManager();

// Cargar permisos al iniciar
document.addEventListener('DOMContentLoaded', () => {
  window.permissions.load().catch(console.error);
});
```

### 2.3.2 Componente de Botón Dinámico

**Archivo:** `public/components/DynamicButton.html`

```html
<!-- 
  Componente de botón dinámico que se adapta según permisos
  Uso: <div data-component="dynamic-button" 
             data-permission="tickets.crear"
             data-label="Crear Ticket"
             data-variant="primary"
             data-on-click="crearTicket">
-->

<template id="dynamic-button-template">
  <button class="btn" 
          data-permission="" 
          data-loading="false"
          aria-busy="false">
    <span class="btn-spinner" style="display:none;"></span>
    <span class="btn-icon" style="display:none;"></span>
    <span class="btn-text"></span>
  </button>
</template>

<script>
class DynamicButton extends HTMLElement {
  constructor() {
    super();
    this.isLoading = false;
  }

  connectedCallback() {
    this.render();
    this.attachEventListeners();
    
    // Observar cambios de permisos
    if (window.permissions) {
      window.permissions.onChange(({ event }) => {
        if (event === 'loaded' || event === 'refreshed') {
          this.updateState();
        }
      });
    }
  }

  render() {
    const template = document.querySelector('#dynamic-button-template');
    const clone = template.content.cloneNode(true);
    
    const button = clone.querySelector('button');
    button.className = `btn btn-${this.variant}`;
    button.dataset.permission = this.permission;
    button.textContent = this.label;

    if (this.icon) {
      const iconSpan = clone.querySelector('.btn-icon');
      iconSpan.textContent = this.icon;
      iconSpan.style.display = 'inline';
    }

    this.innerHTML = '';
    this.appendChild(clone);
  }

  attachEventListeners() {
    const button = this.querySelector('button');
    button.addEventListener('click', async (e) => {
      if (this.isLoading || button.disabled) return;

      // Callback customizado si existe
      if (this.onclick) {
        try {
          this.setLoading(true);
          await this.onclick(e);
          this.showSuccess();
        } catch (error) {
          this.showError(error.message);
          console.error('Button callback error:', error);
        } finally {
          this.setLoading(false);
        }
      }
    });

    this.updateState();
  }

  updateState() {
    const button = this.querySelector('button');
    if (!button) return;

    const hasPermission = window.permissions?.can(this.permission);
    
    if (hasPermission) {
      button.removeAttribute('disabled');
      button.removeAttribute('aria-hidden');
    } else {
      button.setAttribute('disabled', 'disabled');
      button.setAttribute('aria-hidden', 'true');
    }
  }

  setLoading(loading) {
    this.isLoading = loading;
    const button = this.querySelector('button');
    const spinner = this.querySelector('.btn-spinner');
    
    if (loading) {
      button.setAttribute('aria-busy', 'true');
      spinner.style.display = 'inline-block';
      button.disabled = true;
    } else {
      button.setAttribute('aria-busy', 'false');
      spinner.style.display = 'none';
      button.disabled = false;
    }
  }

  showSuccess() {
    const button = this.querySelector('button');
    button.classList.add('is-success');
    setTimeout(() => {
      button.classList.remove('is-success');
    }, 2000);
  }

  showError(message) {
    const button = this.querySelector('button');
    button.classList.add('is-error');
    button.title = message;
    setTimeout(() => {
      button.classList.remove('is-error');
    }, 3000);
  }

  get permission() {
    return this.dataset.permission || '';
  }

  get label() {
    return this.dataset.label || 'Botón';
  }

  get variant() {
    return this.dataset.variant || 'primary';
  }

  get icon() {
    return this.dataset.icon || null;
  }
}

customElements.define('dynamic-button', DynamicButton);
</script>
```

---

## 2.4 Integración con WebSockets (Tiempo Real)

### 2.4.1 RealtimeSync.js para Sincronización

**Archivo:** `public/js/RealtimeSync.js`

```javascript
/**
 * RealtimeSync - Sincronización en tiempo real de cambios de permisos
 * 
 * Utiliza WebSocket para notificar cambios de rol/permiso sin recargar
 */
class RealtimeSync {
  constructor(options = {}) {
    this.wsUrl = options.wsUrl || this._buildWsUrl();
    this.ws = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 10;
    this.reconnectDelay = 1000;
    this.listeners = [];
  }

  _buildWsUrl() {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    return `${protocol}//${window.location.host}/ws/permissions`;
  }

  /**
   * Conecta al servidor WebSocket
   */
  connect() {
    try {
      this.ws = new WebSocket(this.wsUrl);

      this.ws.addEventListener('open', () => {
        console.log('WebSocket connected');
        this.reconnectAttempts = 0;
        this._notify('connected');
      });

      this.ws.addEventListener('message', (event) => {
        this._handleMessage(event.data);
      });

      this.ws.addEventListener('error', (error) => {
        console.error('WebSocket error:', error);
        this._notify('error', error);
      });

      this.ws.addEventListener('close', () => {
        console.log('WebSocket disconnected');
        this._notify('disconnected');
        this._attemptReconnect();
      });

    } catch (error) {
      console.error('Failed to connect WebSocket:', error);
      this._attemptReconnect();
    }
  }

  /**
   * Maneja mensajes del servidor
   */
  _handleMessage(data) {
    try {
      const message = JSON.parse(data);

      switch (message.type) {
        case 'permission_changed':
          this._handlePermissionChange(message);
          break;
        case 'role_updated':
          this._handleRoleUpdate(message);
          break;
        case 'session_expired':
          this._handleSessionExpired();
          break;
        case 'ping':
          this._sendPong();
          break;
      }

      this._notify('message', message);

    } catch (error) {
      console.error('Failed to parse WebSocket message:', error);
    }
  }

  /**
   * Maneja cambios de permisos
   */
  _handlePermissionChange(message) {
    if (window.permissions) {
      window.permissions.refresh().catch(console.error);
    }

    this._notify('permission_changed', message);
  }

  /**
   * Maneja actualizaciones de rol
   */
  _handleRoleUpdate(message) {
    if (window.permissions) {
      window.permissions.refresh().catch(console.error);
    }

    this._notify('role_updated', message);
  }

  /**
   * Maneja sesión expirada
   */
  _handleSessionExpired() {
    if (window.permissions) {
      window.permissions.clear();
    }
    window.location.href = '/login?reason=session_expired';
  }

  /**
   * Envía pong
   */
  _sendPong() {
    if (this.ws?.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify({ type: 'pong' }));
    }
  }

  /**
   * Intenta reconectar con backoff exponencial
   */
  _attemptReconnect() {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.error('Max reconnect attempts reached');
      this._notify('max_reconnects_reached');
      return;
    }

    this.reconnectAttempts++;
    const delay = Math.min(
      this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1),
      30000 // máximo 30 segundos
    );

    console.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);
    setTimeout(() => this.connect(), delay);
  }

  /**
   * Registra listener
   */
  on(event, callback) {
    this.listeners.push({ event, callback });
  }

  /**
   * Notifica listeners
   */
  _notify(event, data = null) {
    this.listeners
      .filter(l => l.event === event)
      .forEach(l => {
        try {
          l.callback(data);
        } catch (e) {
          console.error(`Listener error for ${event}:`, e);
        }
      });
  }

  /**
   * Desconecta
   */
  disconnect() {
    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }
  }
}

// Instancia global
window.realtimeSync = new RealtimeSync();

// Conectar automáticamente
document.addEventListener('DOMContentLoaded', () => {
  window.realtimeSync.connect();
});
```

---

# Mejores Prácticas y Patrones

## 3.1 Flujo de Superadministrador

```php
/**
 * El superadministrador tiene dos características:
 * 1. Flag es_admin en tabla usuarios
 * 2. O rol con nivel_jerarquia >= 100
 */

// En AuthService::isAdmin()
public static function isAdmin(int $userId): bool {
    $user = self::getUserById($userId);
    return ($user['es_admin'] ?? false) || ($user['nivel_jerarquia'] ?? 0) >= 100;
}

// Bypass automático en hasPermission()
public static function hasPermission(int $userId, string $permissionCode): bool {
    if (self::isAdmin($userId)) {
        return true; // Siempre tiene todos los permisos
    }
    // ... verificar permisos normales
}

// Logging especial para actions de admin
Logger::logAuthAction(
    userId: $userId,
    action: 'delete_user',
    resource: 'usuarios',
    resourceId: $deletedUserId,
    isAdmin: true
);
```

## 3.2 Gestión de Sesión Segura

```php
// 1. Inicializar con opciones seguras
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true,      // HTTPS only
    'cookie_samesite' => 'Lax',
    'gc_maxlifetime' => 3600,      // 1 hora
    'cookie_lifetime' => 0         // Sessión de navegador
]);

// 2. Timeout automático
if (isset($_SESSION['last_activity']) && 
    (time() - $_SESSION['last_activity']) > 3600) {
    AuthService::logout();
    header('Location: /login?reason=timeout');
    exit;
}
$_SESSION['last_activity'] = time();

// 3. Refresh de token
if (isset($_SESSION['token_expires']) && 
    $_SESSION['token_expires'] < time()) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
    $_SESSION['token_expires'] = time() + 3600;
}

// 4. Limpieza al logout
AuthService::logout(); // Invalida caché + session_destroy
```

## 3.3 Caché y Sincronización

```javascript
// Estrategia de caché multi-nivel

// 1. LocalStorage (5 minutos)
// - Persiste entre navegación de páginas
// - Se limpia al logout
// - Tiene ETag para validación

// 2. WebSocket (Tiempo real)
// - Notificaciones push de cambios
// - Reconexión automática
// - Fallback a polling cada 30s

// 3. API Refresh (On-demand)
// - Forzar sincronización POST /api/permissions/refresh
// - Cuando el usuario explícitamente lo solicita
// - Después de operaciones críticas

// Ejemplo de sincronización después de asignar rol
async function asignarRol(usuarioId, rolId) {
  const response = await fetch('/api/usuarios/asignar-rol', {
    method: 'POST',
    body: JSON.stringify({ usuario_id: usuarioId, rol_id: rolId })
  });

  if (response.ok) {
    // Refrescar permisos del usuario afectado
    if (usuarioId === window.permissions.user.id) {
      await window.permissions.refresh();
    }

    // Si WebSocket está activo, el servidor enviará notificación
    // Si no, se refrescará cuando el usuario recargue
  }
}
```

---

# Troubleshooting y FAQ

## 4.1 Preguntas Frecuentes

**P: ¿Qué pasa si un botón tiene `data-permission` pero no existe ese permiso en BD?**

R: El botón se mostrará como deshabilitado. Se debe crear el permiso en la tabla `admin.permisos` primero, luego asignarlo a roles.

**P: ¿Cómo agrego un nuevo permiso?**

R: 
```sql
INSERT INTO admin.permisos (codigo, nombre, descripcion, modulo, accion)
VALUES ('equipos.reasignar', 'Reasignar Equipos', '...', 'equipos', 'reasignar');

-- Luego asignarlo a roles necesarios
INSERT INTO admin.rol_permiso (rol_id, permiso_id)
VALUES (3, LASTVAL()); -- ID del rol técnico
```

**P: ¿Qué pasa si WebSocket falla?**

R: El sistema falla gracefully:
1. Se reconecta automáticamente con backoff exponencial
2. Si fallan 10 intentos, emite evento `max_reconnects_reached`
3. Mientras tanto, el caché local sigue funcionando
4. Los cambios se sincronizarán cuando el usuario recargue

**P: ¿Puedo hacer que un permiso expire?**

R: Sí, en las tablas `rol_permiso` y `usuario_permiso_especial` hay campo `expira_en`. La query de permisos verifica `expira_en > NOW()`.

**P: ¿Cómo implemento permisos específicos por contexto (ej: editar solo mis tickets)?**

R: Implementar en el middleware o controlador:
```php
AuthorizationMiddleware::requirePermission('tickets.editar', function($user) {
    // Verificar que sea el creador del ticket
    return $ticket->usuario_id === $user['id'];
});
```

---

## 4.2 Troubleshooting Común

| Problema | Causa Probable | Solución |
|----------|----------------|----------|
| Botón aparece deshabilitado para todos | Permiso no existe en BD | Crear permiso: `INSERT INTO admin.permisos (codigo, ...)` |
| Cambios de rol no se reflejan | Caché no se invalidó | Llamar `AuthService::invalidatePermissionCache($userId)` |
| WebSocket desconecta frecuentemente | Servidor cerró conexión | Revisar logs del servidor, verificar timeout |
| Usuario ve permisos de otro usuario | Sesión corrupta o error en query | Limpiar caché: `localStorage.removeItem('_perms_cache')` |
| Performance lenta con muchos permisos | N+1 queries en BD | Usar JOIN óptimo en `getUserPermissions()` |

---

## 4.3 Checklist de Implementación

- [ ] Crear tablas en BD (permisos, rol_permiso, etc.)
- [ ] Implementar middleware de autorización
- [ ] Crear AuthService con métodos de validación
- [ ] Crear API `/api/permissions/current` y `refresh`
- [ ] Implementar PermissionManager.js
- [ ] Crear componente DynamicButton
- [ ] Implementar WebSocket para sincronización
- [ ] Agregar permisos estándar a BD (seeds)
- [ ] Testing: Verificar botones deshabilitados correctamente
- [ ] Testing: Verificar cambios en tiempo real con WebSocket
- [ ] Documentación: Guía para agregar nuevos permisos
- [ ] Documentación: Guía para agregar nuevos roles

---

**Fin de la Guía Técnica**

*Última actualización: 2026-05-27*  
*Versión: 1.0*  
*Mantenedor: Equipo de Desarrollo OTI*

