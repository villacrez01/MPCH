<?php
namespace App\Models;

use App\Core\Model;

class User extends Model
{
    public static function findByEmail($email)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT u.*, (u.nombre || ' ' || COALESCE(u.apellidos, '')) as name,
                   (CASE WHEN u.activo THEN 'active' ELSE 'inactive' END) as status,
                   u.ultimo_acceso as last_login,
                   r.nombre as role_name, p.name as position_name,
                   up.avatar_filename as avatar, up.phone, up.dni, up.location_id, up.position_id
            FROM admin.usuarios u
            LEFT JOIN admin.usuario_rol ur ON u.id = ur.usuario_id AND ur.sistema_id = (SELECT id FROM admin.sistemas WHERE slug = 'oti' LIMIT 1)
            LEFT JOIN admin.roles r ON ur.rol_id = r.id
            LEFT JOIN oti.user_profiles up ON u.id = up.user_id
            LEFT JOIN oti.positions p ON up.position_id = p.id
            WHERE u.email ILIKE :email LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public static function findByDni($dni)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT u.*, (u.nombre || ' ' || COALESCE(u.apellidos, '')) as name,
                   (CASE WHEN u.activo THEN 'active' ELSE 'inactive' END) as status,
                   u.ultimo_acceso as last_login,
                   r.nombre as role_name, p.name as position_name,
                   up.avatar_filename as avatar, up.phone, up.dni, up.location_id, up.position_id
            FROM admin.usuarios u
            LEFT JOIN oti.user_profiles up ON u.id = up.user_id
            LEFT JOIN admin.usuario_rol ur ON u.id = ur.usuario_id AND ur.sistema_id = (SELECT id FROM admin.sistemas WHERE slug = 'oti' LIMIT 1)
            LEFT JOIN admin.roles r ON ur.rol_id = r.id
            LEFT JOIN oti.positions p ON up.position_id = p.id
            WHERE up.dni = :dni LIMIT 1
        ");
        $stmt->execute(['dni' => $dni]);
        return $stmt->fetch();
    }

    public static function findByIdentifier($identifier)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.apellidos, u.email, u.username, u.password_hash, u.activo, u.es_admin, u.ultimo_acceso, u.created_at,
                   (u.nombre || ' ' || COALESCE(u.apellidos, '')) as name,
                   (CASE WHEN u.activo THEN 'active' ELSE 'inactive' END) as status,
                   u.ultimo_acceso as last_login,
                   r.nombre as role_name, r.id as role_id, p.name as position_name,
                   l.name as area_name, l.id as location_id, up.avatar_filename as avatar, up.phone, up.dni, up.position_id
            FROM admin.usuarios u
            LEFT JOIN oti.user_profiles up ON u.id = up.user_id
            LEFT JOIN admin.usuario_rol ur ON u.id = ur.usuario_id AND ur.sistema_id = (SELECT id FROM admin.sistemas WHERE slug = 'oti' LIMIT 1)
            LEFT JOIN admin.roles r ON ur.rol_id = r.id
            LEFT JOIN oti.positions p ON up.position_id = p.id
            LEFT JOIN oti.locations l ON up.location_id = l.id
            WHERE u.email ILIKE :id OR up.dni = :id OR u.username ILIKE :id OR u.nombre ILIKE :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $identifier]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Solo usar el campo es_admin de la base de datos si existe
            // No determinar por nombre del rol
            if (!isset($user['es_admin'])) {
                $user['es_admin'] = false;
            }
        }
        
        return $user;
    }

    public static function findById($id)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.apellidos, u.email, u.username, u.password_hash, u.activo, u.es_admin, u.ultimo_acceso, u.created_at,
                   (u.nombre || ' ' || COALESCE(u.apellidos, '')) as name,
                   (CASE WHEN u.activo THEN 'active' ELSE 'inactive' END) as status,
                   u.ultimo_acceso as last_login,
                   r.nombre as role_name, r.id as role_id, p.name as position_name,
                   l.name as area_name, l.id as location_id, up.avatar_filename as avatar, up.phone, up.dni, up.position_id,
            (
                SELECT COUNT(*)::integer
                FROM admin.usuarios u2
                WHERE u2.created_at < u.created_at
                   OR (u2.created_at = u.created_at AND u2.id <= u.id)
            ) + 1 as user_number
            FROM admin.usuarios u
            LEFT JOIN oti.user_profiles up ON u.id = up.user_id
            LEFT JOIN admin.usuario_rol ur ON u.id = ur.usuario_id AND ur.sistema_id = (SELECT id FROM admin.sistemas WHERE slug = 'oti' LIMIT 1)
            LEFT JOIN admin.roles r ON ur.rol_id = r.id
            LEFT JOIN oti.positions p ON up.position_id = p.id
            LEFT JOIN oti.locations l ON up.location_id = l.id
            WHERE u.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Solo usar el campo es_admin de la base de datos si existe
            if (!isset($user['es_admin'])) {
                $user['es_admin'] = false;
            }
        }
        
        return $user;
    }

    public static function getAll($search = null, $page = 1, $pageSize = 50)
    {
        try {
            $pdo = self::db();

            $page = max(1, (int)$page);
            $pageSize = min(100, max(1, (int)$pageSize));
            $offset = ($page - 1) * $pageSize;

            $query = "
                SELECT u.id, (u.nombre || ' ' || COALESCE(u.apellidos, '')) as name, u.email,
                       u.es_admin,
                       (CASE WHEN u.activo THEN 'active' ELSE 'inactive' END) as status,
                       u.ultimo_acceso as last_login, r.nombre as role_name, r.id as role_id,
                       p.name as position_name, up.position_id,
                       l.name as area_name, up.location_id as area_id,
                       up.dni, up.phone, up.avatar_filename as avatar,
                ROW_NUMBER() OVER (ORDER BY u.nombre ASC) as user_number
                FROM admin.usuarios u
                LEFT JOIN oti.user_profiles up ON u.id = up.user_id
                LEFT JOIN admin.usuario_rol ur ON u.id = ur.usuario_id AND ur.sistema_id = (SELECT id FROM admin.sistemas WHERE slug = 'oti' LIMIT 1)
                LEFT JOIN admin.roles r ON ur.rol_id = r.id
                LEFT JOIN oti.positions p ON up.position_id = p.id
                LEFT JOIN oti.locations l ON up.location_id = l.id
            ";

            $params = [];
            if ($search) {
                $query .= " WHERE u.nombre ILIKE :search OR u.email ILIKE :search ";
                $params[':search'] = '%' . $search . '%';
            }

            $query .= " ORDER BY u.nombre ASC ";
            $query .= " LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':limit', $pageSize, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            $stmt->execute();
            $result = $stmt->fetchAll();

            return $result;
        } catch (\PDOException $e) {
            error_log("Error en User::getAll: " . $e->getMessage());
            return [];
        }
    }

    public static function getCount($search = null): int
    {
        $pdo = self::db();
        $query = "SELECT COUNT(*) FROM admin.usuarios u";
        $params = [];

        if ($search) {
            $query .= " WHERE u.nombre ILIKE :search OR u.email ILIKE :search ";
            $params[':search'] = '%' . $search . '%';
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function getPagination($page = 1, $pageSize = 50, $search = null): array
    {
        $total = self::getCount($search);
        $page = max(1, (int)$page);
        $pageSize = min(100, max(1, (int)$pageSize));
        $totalPages = ceil($total / $pageSize);

        return [
            'current_page' => $page,
            'page_size' => $pageSize,
            'total_records' => $total,
            'total_pages' => (int)$totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ];
    }

    public static function create($data)
    {
        $pdo = self::db();

        $existingEmail = self::findByEmail($data['email']);
        if ($existingEmail) {
            return false;
        }

        $roleId = $data['role_id'] ?? null;
        $locationId = $data['area_id'] ?? null;
        $positionId = $data['position_id'] ?? null;

        if ($roleId === '' || $roleId === 'null') $roleId = null;
        if ($locationId === '' || $locationId === 'null') $locationId = null;
        if ($positionId === '' || $positionId === 'null') $positionId = null;

        $dni = !empty($data['dni']) ? $data['dni'] : null;
        $phone = !empty($data['phone']) ? $data['phone'] : null;
        $username = $data['username'] ?? explode('@', $data['email'])[0];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO admin.usuarios (nombre, apellidos, email, username, password_hash, activo) VALUES (:nombre, :apellidos, :email, :username, :password_hash, TRUE) RETURNING id");
            $stmt->execute([
                'nombre' => strip_tags(trim($data['name'])),
                'apellidos' => strip_tags(trim($data['apellidos'] ?? '')),
                'email' => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
                'username' => $username,
                'password_hash' => $data['password_hash']
            ]);
            $userId = $stmt->fetchColumn();

            if ($userId) {
                $stmtProfile = $pdo->prepare("INSERT INTO oti.user_profiles (user_id, phone, dni, position_id, location_id, avatar_filename) VALUES (:user_id, :phone, :dni, :position_id, :location_id, :avatar)");
                $stmtProfile->execute([
                    'user_id' => $userId,
                    'phone' => $phone,
                    'dni' => $dni,
                    'position_id' => $positionId,
                    'location_id' => $locationId,
                    'avatar' => $data['avatar'] ?? null
                ]);

                if ($roleId) {
                    $stmtRol = $pdo->prepare("INSERT INTO admin.usuario_rol (usuario_id, sistema_id, rol_id) VALUES (:user_id, (SELECT id FROM admin.sistemas WHERE slug = 'oti' LIMIT 1), :rol_id)");
                    $stmtRol->execute(['user_id' => $userId, 'rol_id' => $roleId]);
                }
            }

            $pdo->commit();
            return (int)$userId;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }

    public static function updatePassword($id, $newPassword)
    {
        $pdo = self::db();
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE admin.usuarios SET password_hash = :password_hash WHERE id = :id");
        return $stmt->execute([
            'password_hash' => $hashedPassword,
            'id' => $id
        ]);
    }

    public static function updateAvatar($id, $avatarName)
    {
        $pdo = self::db();
        try {
            $pdo->beginTransaction();

            $stmtU = $pdo->prepare("UPDATE admin.usuarios SET avatar = :avatar WHERE id = :id");
            $stmtU->execute(['avatar' => $avatarName, 'id' => $id]);

            $stmtP = $pdo->prepare("INSERT INTO oti.user_profiles (user_id, avatar_filename) VALUES (:user_id, :avatar) ON CONFLICT (user_id) DO UPDATE SET avatar_filename = :avatar");
            $stmtP->execute(['user_id' => $id, 'avatar' => $avatarName]);

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return false;
        }
    }

    public static function update($id, $data)
    {
        $pdo = self::db();
        try {
            $pdo->beginTransaction();

            $userFields = [];
            $userParams = [':id' => $id];
            $profileFields = [];
            $profileParams = [':id' => $id];

            $mapping = [
                'name' => ['table' => 'u', 'col' => 'nombre'],
                'apellidos' => ['table' => 'u', 'col' => 'apellidos'],
                'email' => ['table' => 'u', 'col' => 'email'],
                'username' => ['table' => 'u', 'col' => 'username'],
                'activo' => ['table' => 'u', 'col' => 'activo'],
                'dni' => ['table' => 'p', 'col' => 'dni'],
                'phone' => ['table' => 'p', 'col' => 'phone'],
                'location_id' => ['table' => 'p', 'col' => 'location_id'],
                'position_id' => ['table' => 'p', 'col' => 'position_id']
            ];

            foreach ($data as $key => $value) {
                if (isset($mapping[$key])) {
                    if ($mapping[$key]['table'] === 'u') {
                        $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $mapping[$key]['col']);
                        $userFields[] = "{$safeCol} = :{$key}";
                        $userParams[":{$key}"] = $value;
                    } else {
                        $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $mapping[$key]['col']);
                        $profileFields[] = "{$safeCol} = :{$key}";
                        $profileParams[":{$key}"] = $value;
                    }
                }
            }

            if (!empty($userFields)) {
                $stmt = $pdo->prepare("UPDATE admin.usuarios SET " . implode(', ', $userFields) . " WHERE id = :id");
                $stmt->execute($userParams);
            }

            if (!empty($profileFields)) {
                $stmt = $pdo->prepare("UPDATE oti.user_profiles SET " . implode(', ', $profileFields) . " WHERE user_id = :id");
                $stmt->execute($profileParams);
            }

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return false;
        }
    }

    public static function updateProfile($id, $data)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("UPDATE admin.usuarios SET nombre = :name WHERE id = :id");
        return $stmt->execute([
            'name' => $data['name'],
            'id' => $id
        ]);
    }

    public static function deleteOldAvatar($avatarName)
    {
        $safeName = basename($avatarName);
        $baseDir = __DIR__ . '/../../public/uploads/avatars/';

        if (empty($safeName) || $safeName !== $avatarName) {
            return false;
        }

        $filePath = $baseDir . $safeName;

        if (file_exists($filePath) && is_file($filePath)) {
            unlink($filePath);
            return true;
        }
        return false;
    }

    public static function delete($id)
    {
        $user = self::findById($id);
        if (!$user || \App\Services\AuthService::isAdmin()) {
            return false;
        }

        if (!empty($user['avatar'])) {
            self::deleteOldAvatar($user['avatar']);
        }

        $pdo = self::db();
        $stmt = $pdo->prepare("DELETE FROM admin.usuarios WHERE id = :id AND es_admin = FALSE");
        return $stmt->execute(['id' => $id]);
    }

    public static function getAvatar($userId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT avatar_filename FROM oti.user_profiles WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();
        return $result['avatar_filename'] ?? null;
    }

    public static function getAdmins()
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT u.id, (u.nombre || ' ' || COALESCE(u.apellidos, '')) as name, u.email
            FROM admin.usuarios u
            WHERE u.es_admin = TRUE
            ORDER BY u.nombre ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getAllRoles()
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT * FROM admin.roles ORDER BY nombre ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getAllPositions()
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT * FROM oti.positions ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function isAdmin($userId): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT es_admin FROM admin.usuarios WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function isGerente($userId): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT EXISTS(SELECT 1 FROM oti.locations WHERE manager_id = :id)");
        $stmt->execute(['id' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function getStats(): array
    {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE activo = true) as activos,
                COUNT(*) FILTER (WHERE activo = false) as inactivos
            FROM admin.usuarios
        ");
        return $stmt->fetch() ?: ['total' => 0, 'activos' => 0, 'inactivos' => 0];
    }

    /**
     * Obtiene usuarios con su ubicación y equipos asignados
     */
    public static function getAllWithDetails($filters = [], $page = 1, $pageSize = 50)
    {
        $pdo = self::db();

        $where = "WHERE 1=1";
        $params = [];

        if (!empty($filters['location_id'])) {
            $where .= " AND up.location_id = :location_id";
            $params['location_id'] = $filters['location_id'];
        }

        if (!empty($filters['search'])) {
            $where .= " AND (u.nombre ILIKE :search OR u.apellidos ILIKE :search OR u.email ILIKE :search OR up.dni ILIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['activo']) && $filters['activo'] !== '') {
            $where .= " AND u.activo = :activo";
            $params['activo'] = filter_var($filters['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? ($filters['activo'] === 'true');
        }

        $page     = max(1, (int)$page);
        $pageSize = min(100, max(1, (int)$pageSize));
        $offset   = ($page - 1) * $pageSize;

        $stmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.apellidos, u.email, u.activo, u.es_admin, u.ultimo_acceso,
                   up.dni, up.phone, up.location_id, up.position_id, up.permissions,
                   l.name as location_name, l.type as location_type,
                   p.name as position_name,
                   r.nombre as role_name,
                   COALESCE(e.equipos_count, 0) as equipos_count
            FROM admin.usuarios u
            LEFT JOIN oti.user_profiles up ON u.id = up.user_id
            LEFT JOIN oti.locations l ON up.location_id = l.id
            LEFT JOIN oti.positions p ON up.position_id = p.id
            LEFT JOIN admin.usuario_rol ur ON u.id = ur.usuario_id AND ur.sistema_id = (SELECT id FROM admin.sistemas WHERE slug = 'oti' LIMIT 1)
            LEFT JOIN admin.roles r ON ur.rol_id = r.id
            LEFT JOIN (
                SELECT assigned_user_id, COUNT(*) as equipos_count
                FROM oti.equipment
                WHERE is_deleted = false AND assigned_user_id IS NOT NULL
                GROUP BY assigned_user_id
            ) e ON u.id = e.assigned_user_id
            {$where}
            ORDER BY u.nombre ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $pageSize, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene los equipos asignados a un usuario
     */
    public static function getAssignedEquipment($userId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT e.*, l.name as location_name
            FROM oti.equipment e
            LEFT JOIN oti.locations l ON e.location_id = l.id
            WHERE e.assigned_user_id = :user_id AND e.is_deleted = false
            ORDER BY e.name ASC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Asigna un equipo a un usuario
     */
    public static function assignEquipment($equipmentId, $userId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            UPDATE oti.equipment 
            SET assigned_user_id = :user_id, updated_at = NOW()
            WHERE id = :id AND is_deleted = false
            RETURNING id
        ");
        $stmt->execute(['id' => $equipmentId, 'user_id' => $userId]);
        return $stmt->fetch();
    }

    /**
     * Desasigna un equipo de un usuario
     */
    public static function unassignEquipment($equipmentId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            UPDATE oti.equipment 
            SET assigned_user_id = NULL, updated_at = NOW()
            WHERE id = :id AND is_deleted = false
            RETURNING id
        ");
        $stmt->execute(['id' => $equipmentId]);
        return $stmt->fetch();
    }

    /**
     * Obtiene equipos disponibles para asignar (sin usuario asignado)
     */
    public static function getAvailableEquipment($locationId = null)
    {
        $pdo = self::db();
        
        $where = "WHERE e.is_deleted = false AND e.assigned_user_id IS NULL AND e.status = 'active'";
        $params = [];
        
        if ($locationId) {
            $where .= " AND e.location_id = :location_id";
            $params['location_id'] = $locationId;
        }
        
        $stmt = $pdo->prepare("
            SELECT e.*, l.name as location_name
            FROM oti.equipment e
            LEFT JOIN oti.locations l ON e.location_id = l.id
            {$where}
            ORDER BY e.name ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene las ubicaciones organizadas jerárquicamente
     */
    public static function getLocationsHierarchy()
    {
        $pdo = self::db();
        
        $stmt = $pdo->query("
            SELECT id, name, type, parent_id, nivel
            FROM oti.locations
            WHERE active = true
            ORDER BY type, nivel, name
        ");
        $locations = $stmt->fetchAll();
        
        $hierarchy = ['sedes' => [], 'areas' => [], 'oficinas' => []];
        
        foreach ($locations as $loc) {
            $type = strtolower($loc['type'] ?? 'oficina');
            if (strpos($type, 'sede') !== false) {
                $hierarchy['sedes'][] = $loc;
            } elseif (strpos($type, 'area') !== false) {
                $hierarchy['areas'][] = $loc;
            } else {
                $hierarchy['oficinas'][] = $loc;
            }
        }
        
        return $hierarchy;
    }

    /**
     * Crea un nuevo usuario con perfil
     */
    public static function createWithProfile($data)
    {
        $pdo = self::db();
        
        try {
            $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
            $username = $data['username'] ?? explode('@', $email)[0];
            
            $stmtCheck = $pdo->prepare("
                SELECT id FROM admin.usuarios 
                WHERE email = :email OR username = :username
            ");
            $stmtCheck->execute(['email' => $email, 'username' => $username]);
            if ($stmtCheck->fetch()) {
                return ['success' => false, 'error' => 'El email o nombre de usuario ya existe'];
            }
            
            if (!empty($data['dni'])) {
                $stmtDni = $pdo->prepare("SELECT user_id FROM oti.user_profiles WHERE dni = :dni");
                $stmtDni->execute(['dni' => $data['dni']]);
                if ($stmtDni->fetch()) {
                    return ['success' => false, 'error' => 'El DNI ya está registrado'];
                }
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO admin.usuarios (nombre, apellidos, email, username, password_hash, activo)
                VALUES (:nombre, :apellidos, :email, :username, :password_hash, :activo)
                RETURNING id
            ");
            
            $passwordHash = password_hash($data['password'] ?? 'changeme123', PASSWORD_BCRYPT);
            
            $stmt->execute([
                'nombre' => strip_tags(trim($data['nombre'])),
                'apellidos' => strip_tags(trim($data['apellidos'] ?? '')),
                'email' => $email,
                'username' => $username,
                'password_hash' => $passwordHash,
                'activo' => $data['activo'] ?? true
            ]);
            
            $userId = $stmt->fetchColumn();
            
            if ($userId && !empty($data['location_id'])) {
                $stmtProfile = $pdo->prepare("
                    INSERT INTO oti.user_profiles (user_id, dni, phone, location_id, position_id)
                    VALUES (:user_id, :dni, :phone, :location_id, :position_id)
                    ON CONFLICT (user_id) DO UPDATE SET 
                        dni = EXCLUDED.dni,
                        phone = EXCLUDED.phone,
                        location_id = EXCLUDED.location_id,
                        position_id = EXCLUDED.position_id
                ");
                
                $stmtProfile->execute([
                    'user_id' => $userId,
                    'dni' => $data['dni'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'location_id' => $data['location_id'],
                    'position_id' => $data['position_id'] ?? null
                ]);
            }
            
            if ($userId && !empty($data['role_id'])) {
                $stmtRol = $pdo->prepare("
                    INSERT INTO admin.usuario_rol (usuario_id, sistema_id, rol_id)
                    VALUES (:user_id, (SELECT id FROM admin.sistemas WHERE slug = 'oti' LIMIT 1), :role_id)
                    ON CONFLICT DO NOTHING
                ");
                $stmtRol->execute(['user_id' => $userId, 'role_id' => $data['role_id']]);
            }
            
            $pdo->commit();
            return ['success' => true, 'id' => $userId];
            
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Error creando usuario: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Actualiza la ubicación de un usuario
     */
    public static function updateLocation($userId, $locationId, $positionId = null)
    {
        $pdo = self::db();
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO oti.user_profiles (user_id, location_id, position_id)
                VALUES (:user_id, :location_id, :position_id)
                ON CONFLICT (user_id) DO UPDATE SET 
                    location_id = EXCLUDED.location_id,
                    position_id = COALESCE(EXCLUDED.position_id, oti.user_profiles.position_id)
            ");
            $stmt->execute([
                'user_id' => $userId,
                'location_id' => $locationId,
                'position_id' => $positionId
            ]);
            
            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return false;
        }
    }
    
    public static function getUserProfile($userId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.apellidos, u.email, u.activo, u.ultimo_acceso,
                   up.phone, up.dni, up.location_id, up.position_id
            FROM admin.usuarios u
            LEFT JOIN oti.user_profiles up ON u.id = up.user_id
            WHERE u.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch() ?: [];
    }
    
    public static function updateUserProfile($userId, $data)
    {
        $pdo = self::db();
        
        try {
            $pdo->beginTransaction();
            
            if (!empty($data['email'])) {
                $stmt = $pdo->prepare("UPDATE admin.usuarios SET email = :email WHERE id = :id");
                $stmt->execute(['email' => $data['email'], 'id' => $userId]);
            }
            
            if (!empty($data['telefono'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO oti.user_profiles (user_id, phone) 
                    VALUES (:user_id, :phone)
                    ON CONFLICT (user_id) DO UPDATE SET phone = :phone
                ");
                $stmt->execute(['user_id' => $userId, 'phone' => $data['telefono']]);
            }
            
            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return false;
        }
    }
    
    public static function changeUserPassword($userId, $newPassword)
    {
        $pdo = self::db();
        
        try {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin.usuarios SET password_hash = :password WHERE id = :id");
            $stmt->execute(['password' => $passwordHash, 'id' => $userId]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Cuenta usuarios asignados a una ubicación
     */
    public static function countByLocation($locationId): int
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM oti.user_profiles WHERE location_id = :location_id");
        $stmt->execute(['location_id' => $locationId]);
        return (int)$stmt->fetchColumn();
    }
}
