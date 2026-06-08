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

if (!function_exists('clean')) {
    function clean($data)
    {
        if (is_array($data)) {
            return array_map('clean', $data);
        }
        return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    function base_url(): string
    {
        if (defined('BASE_URL')) {
            return BASE_URL;
        }
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            return $protocol . '://' . $_SERVER['HTTP_HOST'] . '/OTI/';
        }
        return 'http://localhost/OTI/';
    }
}

if (!function_exists('getUserPermissions')) {
     function getUserPermissions(int $userId): array
     {
         try {
             $pdo = \App\Core\Database::connect();
             $stmt = $pdo->prepare("SELECT permissions FROM oti.user_profiles WHERE user_id = :uid");
             $stmt->execute(['uid' => $userId]);
             $row = $stmt->fetch(\PDO::FETCH_ASSOC);
             if ($row && !empty($row['permissions'])) {
                 $perms = json_decode($row['permissions'], true);
                 return is_array($perms) ? $perms : [];
             }
         } catch (\Throwable $e) {
             error_log('getUserPermissions error: ' . $e->getMessage());
         }
         return [];
     }
 }

 if (!function_exists('getPagePermissions')) {
     /**
      * Devuelve el mapa de permisos del usuario actual como array simple
      * para pasar al contexto JS de las vistas V2 (renderizadas en cliente).
      * Keys: 'tickets:view', 'tickets:edit', 'tickets:delete', etc.
      */
     function getPagePermissions(): array
     {
         $userId = $_SESSION['user']['id'] ?? null;
         if (!$userId) return [];
         if (\App\Services\AuthService::isAdmin()) {
             return [
                 'notifications:view' => true,
                 'tickets:view'       => true,
                 'tickets:edit'       => true,
                 'tickets:delete'     => true,
                 'equipos:view'       => true,
                 'equipos:assign'     => true,
                 'users:view'         => true,
                 'users:manage'       => true,
                 'structure:edit'     => true,
             ];
         }
         $perms = getUserPermissions($userId);
         $all = [
             'notifications:view',
             'tickets:view', 'tickets:edit', 'tickets:delete',
             'equipos:view', 'equipos:assign',
             'users:view', 'users:manage',
             'structure:edit',
         ];
         $map = [];
         foreach ($all as $key) {
             $map[$key] = in_array($key, $perms, true);
         }
         return $map;
     }
 }

 // Aliases globales para compatibilidad con vistas (sin namespace)
 if (!class_exists('AuthService')) {
     class_alias(\App\Services\AuthService::class, 'AuthService');
 }
 if (!class_exists('SecurityMiddleware')) {
     class_alias(\App\Middleware\SecurityMiddleware::class, 'SecurityMiddleware');
 }
 if (!class_exists('User')) {
     class_alias(\App\Models\User::class, 'User');
 }

