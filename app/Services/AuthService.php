<?php
/**
 * Servicio de autenticación del sistema OTI
 * Maneja login, logout y verificación de sesión
 */

namespace App\Services;

use App\Models\User;

class AuthService
{
    /**
     * Autentica un usuario en el sistema
     */
    public static function login($credential, $password)
    {
        $user = User::findByIdentifier($credential);

        if (!$user) {
            return ['error' => 'Credenciales inválidas'];
        }

        if ($user['status'] !== 'active') {
            return ['error' => 'Credenciales inválidas'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return ['error' => 'Credenciales inválidas'];
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'role_id' => $user['role_id'] ?? null,
            'role_name' => $user['role_name'] ?? null,
            'avatar' => $user['avatar'] ?? null,
            'email' => $user['email'],
            'dni' => $user['dni'] ?? null,
            'es_admin' => $user['es_admin'] ?? false,
            'area_name' => $user['area_name'] ?? null,
            'login_time' => time(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        $_SESSION['last_activity'] = time();

        return ['success' => true];
    }

    /**
     * Verifica si hay una sesión activa
     */
    public static function check()
    {
        if (!isset($_SESSION['user'])) {
            return false;
        }

        $timeout = 30 * 60;
        if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $timeout) {
            self::logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Cierra la sesión del usuario actual
     */
    public static function logout()
    {
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        $baseUrl = base_url();
        
        session_unset();
        session_destroy();
        
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
        header("Location: " . $baseUrl . "login");
        exit;
    }

    /**
     * Verifica si el usuario es administrador
     */
    public static function isAdmin(): bool
    {
        return isset($_SESSION['user']) && ($_SESSION['user']['es_admin'] ?? false);
    }

    /**
     * Verifica si el usuario es gerente
     */
    public static function isGerente()
    {
        if (!isset($_SESSION['user'])) return false;
        if (self::isAdmin()) return true;
        return User::isGerente($_SESSION['user']['id']);
    }

    /**
     * Verifica si el usuario es usuario regular
     */
    public static function isUser()
    {
        return isset($_SESSION['user']) && !self::isAdmin();
    }

    /**
     * Obtiene el ID del rol del usuario
     */
    public static function getRoleId()
    {
        return $_SESSION['user']['role_id'] ?? null;
    }

    /**
     * Obtiene el nombre del rol del usuario
     */
    public static function getRoleName()
    {
        return $_SESSION['user']['role_name'] ?? null;
    }

    /**
     * Requiere que el usuario sea administrador
     */
    public static function requireAdmin()
    {
        if (!self::check() || !self::isAdmin()) {
            http_response_code(403);
            echo "403 - Acceso denegado";
            exit;
        }
    }

    /**
     * Requiere que el usuario esté autenticado
     */
    public static function requireAuth()
    {
        if (!self::check()) {
            $baseUrl = base_url();
            header("Location: " . $baseUrl . "login");
            exit;
        }
    }

    /**
     * Requiere que el usuario NO sea administrador
     */
    public static function requireNotAdmin()
    {
        if (self::check() && self::isAdmin()) {
            $baseUrl = base_url();
            header("Location: " . $baseUrl . "admin/dashboard");
            exit;
        }
    }

    // ========================================================================
    // SECCIÓN RBAC - Control de Acceso Basado en Roles
    // ========================================================================

    private static array $permissionCache = [];
    private const CACHE_TTL = 300; // 5 minutos

    /**
     * Obtiene el ID del usuario actual
     */
    public static function getCurrentUserId(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    /**
     * Obtiene los datos del usuario actual
     */
    public static function getCurrentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

/**
      * Verifica si el usuario actual tiene un permiso específico
      * @param string $permissionCode Código del permiso (ej: 'tickets.editar')
      * @param int|null $userId ID del usuario (opcional, usa usuario actual si null)
      */
    public static function hasPermission(string $permissionCode, ?int $userId = null): bool
    {
        if (!self::check()) {
            return false;
        }

        $userId = $userId ?? self::getCurrentUserId();
        if (!$userId) {
            return false;
        }

        // Admin tiene todos los permisos
        if (self::isAdmin()) {
            return true;
        }

        // Verificar caché en memoria
        $cacheKey = "perms_{$userId}";
        if (isset(self::$permissionCache[$cacheKey])) {
            $cached = self::$permissionCache[$cacheKey];
            if ($cached['expires'] > time()) {
                return in_array($permissionCode, $cached['permissions'], true);
            }
        }

        // Consultar permisos desde BD
        $permissions = self::getPermissionsFromDB($userId);

        // Cachear
        self::$permissionCache[$cacheKey] = [
            'permissions' => $permissions,
            'expires' => time() + self::CACHE_TTL
        ];

        return in_array($permissionCode, $permissions, true);
    }

    /**
     * Verifica si usuario tiene TODOS los permisos
     */
    public static function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!self::hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Verifica si usuario tiene ALGUNO de los permisos
     */
    public static function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene todos los permisos del usuario desde la BD
     */
    private static function getPermissionsFromDB(int $userId): array
    {
        try {
            $db = \App\Core\Database::connect();

            $stmt = $db->prepare("
                SELECT DISTINCT p.codigo
                FROM admin.permisos p
                WHERE 
                    p.id IN (
                        SELECT DISTINCT rp.permiso_id
                        FROM admin.usuario_rol ur
                        JOIN admin.rol_permiso rp ON ur.rol_id = rp.rol_id
                        WHERE ur.usuario_id = :user_id 
                          AND ur.sistema_id = (SELECT id FROM admin.sistemas WHERE slug = 'oti' LIMIT 1)
                          AND (rp.expira_en IS NULL OR rp.expira_en > NOW())
                    )
                    OR
                    p.id IN (
                        SELECT DISTINCT permiso_id
                        FROM admin.usuario_permiso_especial
                        WHERE usuario_id = :user_id
                          AND (expira_en IS NULL OR expira_en > NOW())
                    )
                ORDER BY p.codigo
            ");

            $stmt->execute(['user_id' => $userId]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return array_map(fn($row) => $row['codigo'], $results);

        } catch (\Throwable $e) {
            error_log("Error fetching permissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Invalida caché de permisos
     */
    public static function invalidatePermissionCache(int $userId): void
    {
        $cacheKey = "perms_{$userId}";
        unset(self::$permissionCache[$cacheKey]);
    }

    /**
     * Requiere un permiso específico
     */
    public static function requirePermission(string $permission)
    {
        if (!self::check()) {
            http_response_code(401);
            echo "401 - No autenticado";
            exit;
        }

        if (!self::hasPermission($permission)) {
            http_response_code(403);
            echo "403 - Permiso denegado: {$permission}";
            exit;
        }
    }

    /**
     * Obtiene todos los permisos del usuario actual
     */
    public static function getPermissions(): array
    {
        if (!self::check()) {
            return [];
        }

        $userId = self::getCurrentUserId();
        if (!$userId) {
            return [];
        }

        return self::getPermissionsFromDB($userId);
    }
}