<?php
declare(strict_types=1);

namespace App\Controller\v2;

use App\Controller\BaseController;
use App\Models\User;
use App\Core\Database;

class UserController extends BaseController
{
    public function list(): void
    {
        $this->redirectIfNotAdmin();

        $search = $this->getQuery('search', '');
        $page = max(1, (int)($this->getQuery('page', '1')));
        $pageSize = min(100, max(1, (int)($this->getQuery('pageSize', '20'))));

        $users = User::getAll($search, $page, $pageSize);
        $totalCount = User::getCount($search);
        $totalPages = (int)ceil($totalCount / $pageSize);
        $stats = User::getStats();

        $this->json([
            'success' => true,
            'users' => $users,
            'stats' => $stats,
            'pagination' => [
                'currentPage' => $page,
                'pageSize' => $pageSize,
                'totalCount' => $totalCount,
                'totalPages' => $totalPages,
                'hasNextPage' => $page < $totalPages,
                'hasPrevPage' => $page > 1
            ]
        ]);
    }

    public function get(): void
    {
        $this->redirectIfNotAdmin();

        $id = (int)($this->getQuery('id', '0'));
        if ($id <= 0) {
            $this->error('ID de usuario requerido');
        }

        $user = User::findById($id);
        if (!$user) {
            $this->error('Usuario no encontrado', 404);
        }

        $assignedEquipment = User::getAssignedEquipment($id);
        $availableEquipment = [];
        $locationId = $user['location_id'] ?? null;
        if ($locationId) {
            $availableEquipment = User::getAvailableEquipment($locationId);
        }

        $this->json([
            'success' => true,
            'user' => $user,
            'equipment' => [
                'assigned' => $assignedEquipment,
                'available' => $availableEquipment
            ]
        ]);
    }

    public function create(): void
    {
        $this->redirectIfNotAdmin();

        $nombre = trim($this->getPost('nombre', ''));
        $apellidos = trim($this->getPost('apellidos', ''));
        $email = trim($this->getPost('email', ''));
        $password = $this->getPost('password', '');
        $dni = trim($this->getPost('dni', ''));
        $phone = trim($this->getPost('phone', ''));
        $locationId = $this->getPost('location_id');
        $positionId = $this->getPost('position_id');
        $esAdmin = $this->getPost('es_admin') === '1' || $this->getPost('es_admin') === 'true';
        $rolId = (int)($this->getPost('rol_id', '0'));

        $errors = $this->validate(
            compact('nombre', 'apellidos', 'email', 'password'),
            ['nombre' => 'required', 'apellidos' => 'required', 'email' => 'required|email', 'password' => 'required|min:6']
        );

        if ($errors) {
            $this->error('Datos inválidos: ' . json_encode($errors));
        }

        if (User::findByEmail($email)) {
            $this->error('El email ya está registrado');
        }

        if ($dni && User::findByDni($dni)) {
            $this->error('El DNI ya está registrado');
        }

        $pdo = Database::connect();
        try {
            $pdo->beginTransaction();

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO admin.usuarios (nombre, apellidos, email, password_hash, activo, es_admin, created_at, updated_at)
                VALUES (:nombre, :apellidos, :email, :password, true, :es_admin, NOW(), NOW())
                RETURNING id
            ");
            $stmt->execute([
                'nombre' => $nombre,
                'apellidos' => $apellidos,
                'email' => $email,
                'password' => $hashedPassword,
                'es_admin' => $esAdmin ? 't' : 'f'
            ]);
            $userId = $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                INSERT INTO oti.user_profiles (user_id, dni, phone, location_id, position_id)
                VALUES (:user_id, :dni, :phone, :location_id, :position_id)
            ");
            $stmt->execute([
                'user_id' => $userId,
                'dni' => $dni ?: null,
                'phone' => $phone ?: null,
                'location_id' => $locationId ? (int)$locationId : null,
                'position_id' => $positionId ? (int)$positionId : null
            ]);

            if ($rolId > 0) {
                $sistemaStmt = $pdo->prepare("SELECT id FROM admin.sistemas WHERE slug = 'oti' LIMIT 1");
                $sistemaStmt->execute();
                $sistemaId = $sistemaStmt->fetchColumn();

                if ($sistemaId) {
                    $stmt = $pdo->prepare("INSERT INTO admin.usuario_rol (usuario_id, rol_id, sistema_id) VALUES (:uid, :rid, :sid)");
                    $stmt->execute(['uid' => $userId, 'rid' => $rolId, 'sid' => $sistemaId]);
                }
            }

            $pdo->commit();
            $this->success(['id' => $userId], 'Usuario creado exitosamente');
        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            $this->error('Error al crear usuario', 500);
        }
    }

    public function update(): void
    {
        $this->redirectIfNotAdmin();

        $id = (int)($this->getPost('id', '0'));
        if ($id <= 0) {
            $this->error('ID de usuario requerido');
        }

        $nombre = trim($this->getPost('nombre', ''));
        $apellidos = trim($this->getPost('apellidos', ''));
        $email = trim($this->getPost('email', ''));
        $password = $this->getPost('password', '');
        $dni = trim($this->getPost('dni', ''));
        $phone = trim($this->getPost('phone', ''));
        $locationId = $this->getPost('location_id');
        $positionId = $this->getPost('position_id');
        $esAdmin = $this->getPost('es_admin') === '1' || $this->getPost('es_admin') === 'true';
        $activo = $this->getPost('activo') === '1' || $this->getPost('activo') !== '0';

        $pdo = Database::connect();
        try {
            $pdo->beginTransaction();

            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admin.usuarios SET nombre=:nombre, apellidos=:apellidos, email=:email, password_hash=:password, es_admin=:es_admin, activo=:activo, updated_at=NOW() WHERE id=:id");
                $stmt->execute([
                    'nombre' => $nombre, 'apellidos' => $apellidos, 'email' => $email,
                    'password' => $hashed, 'es_admin' => $esAdmin ? 't' : 'f',
                    'activo' => $activo ? 't' : 'f', 'id' => $id
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE admin.usuarios SET nombre=:nombre, apellidos=:apellidos, email=:email, es_admin=:es_admin, activo=:activo, updated_at=NOW() WHERE id=:id");
                $stmt->execute([
                    'nombre' => $nombre, 'apellidos' => $apellidos, 'email' => $email,
                    'es_admin' => $esAdmin ? 't' : 'f', 'activo' => $activo ? 't' : 'f', 'id' => $id
                ]);
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM oti.user_profiles WHERE user_id = :id");
            $stmt->execute(['id' => $id]);
            $hasProfile = (int)$stmt->fetchColumn() > 0;

            if ($hasProfile) {
                $stmt = $pdo->prepare("UPDATE oti.user_profiles SET dni=:dni, phone=:phone, location_id=:location_id, position_id=:position_id WHERE user_id=:user_id");
            } else {
                $stmt = $pdo->prepare("INSERT INTO oti.user_profiles (user_id, dni, phone, location_id, position_id) VALUES (:user_id, :dni, :phone, :location_id, :position_id)");
            }
            $stmt->execute([
                'user_id' => $id,
                'dni' => $dni ?: null,
                'phone' => $phone ?: null,
                'location_id' => $locationId ? (int)$locationId : null,
                'position_id' => $positionId ? (int)$positionId : null
            ]);

            $pdo->commit();
            $this->success(null, 'Usuario actualizado');
        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            $this->error('Error al actualizar usuario', 500);
        }
    }

    public function delete(): void
    {
        $this->redirectIfNotAdmin();

        $id = (int)($this->getQuery('id', '0'));
        if ($id <= 0) {
            $this->error('ID de usuario requerido');
        }

        $pdo = Database::connect();
        try {
            $stmt = $pdo->prepare("UPDATE oti.equipment SET assigned_user_id = NULL, updated_at = NOW() WHERE assigned_user_id = :id");
            $stmt->execute(['id' => $id]);
            $stmt = $pdo->prepare("UPDATE admin.usuarios SET activo = false, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $this->success(null, 'Usuario desactivado');
        } catch (\Exception $e) {
            error_log($e->getMessage());
            $this->error('Error al desactivar usuario', 500);
        }
    }

    public function reactivate(): void
    {
        $this->redirectIfNotAdmin();

        $id = (int)($this->getQuery('id', '0'));
        if ($id <= 0) {
            $this->error('ID de usuario requerido');
        }

        $pdo = Database::connect();
        try {
            $stmt = $pdo->prepare("UPDATE admin.usuarios SET activo = true, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $this->success(null, 'Usuario reactivado');
        } catch (\Exception $e) {
            error_log($e->getMessage());
            $this->error('Error al reactivar usuario', 500);
        }
    }

    public function roles(): void
    {
        $this->redirectIfNotAdmin();
        $this->json(User::getAllRoles());
    }

    public function positions(): void
    {
        $this->redirectIfNotAdmin();
        $this->json(User::getAllPositions());
    }

    public function locations(): void
    {
        $this->redirectIfNotAdmin();
        $this->json(\App\Models\Location::getAll());
    }

    public function getUser(): void
    {
        $this->redirectIfNotAdmin();

        $id = (int)($this->getQuery('id', '0'));
        if ($id <= 0) {
            $this->error('ID requerido');
        }

        $user = User::findById($id);
        if (!$user) {
            $this->error('Usuario no encontrado', 404);
        }

        $profile = User::getUserProfile($id);

        $this->json([
            'success' => true,
            'user' => $user,
            'profile' => $profile
        ]);
    }

    public function renderListView(): void
    {
        $this->redirectIfNotAdmin();

        $users = User::getAllWithDetails();
        $stats = User::getStats();
        $locations = \App\Models\Location::getAll();
        $hierarchy = User::getLocationsHierarchy();

        $this->view('v2/users/index.php', [
            'users' => $users,
            'stats' => $stats,
            'locations' => $locations,
            'hierarchy' => $hierarchy,
            'tituloPagina' => 'Usuarios V2 - Sistema OTI',
            'paginaActual' => 'admin-usuarios-v2'
        ]);
    }
}
