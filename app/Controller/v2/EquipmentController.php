<?php
declare(strict_types=1);

namespace App\Controller\v2;

use App\Controller\BaseController;
use App\Models\Equipment;
use App\Models\User;
use App\Core\Database;

class EquipmentController extends BaseController
{
    public function list(): void
    {
        $this->redirectIfNotAdmin();

        $filters = [];
        $page = max(1, (int)($this->getQuery('page', '1')));
        $pageSize = min(100, max(1, (int)($this->getQuery('pageSize', '20'))));

        $status = $this->getQuery('status');
        if ($status) $filters['status'] = $status;

        $assetType = $this->getQuery('asset_type');
        if ($assetType) $filters['asset_type'] = $assetType;

        $locationId = $this->getQuery('location_id');
        if ($locationId) $filters['location_id'] = (int)$locationId;

        $assignedUserId = $this->getQuery('assigned_user_id');
        if ($assignedUserId) $filters['assigned_user_id'] = (int)$assignedUserId;

        $search = $this->getQuery('search');
        if ($search) $filters['search'] = $search;

        $equipmentList = Equipment::getAll($filters, $page, $pageSize);
        $totalCount = Equipment::getTotalCount($filters);
        $totalPages = (int)ceil($totalCount / $pageSize);
        $stats = Equipment::getStats();

        $this->json([
            'success' => true,
            'equipment' => $equipmentList,
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
            $this->error('ID de equipo requerido');
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT e.*, l.name as location_name,
                   u.nombre as assigned_user_name, u.apellidos as assigned_user_lastname
            FROM oti.equipment e
            LEFT JOIN oti.locations l ON e.location_id = l.id
            LEFT JOIN admin.usuarios u ON e.assigned_user_id = u.id
            WHERE e.id = :id AND e.is_deleted = false
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $equipment = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($equipment) {
            $this->json($equipment);
        } else {
            $this->error('Equipo no encontrado', 404);
        }
    }

    public function create(): void
    {
        $this->redirectIfNotAdmin();

        $name = trim($this->getPost('name', ''));
        $assetType = trim($this->getPost('asset_type', ''));
        $brand = trim($this->getPost('brand', ''));
        $model = trim($this->getPost('model', ''));
        $serialNumber = trim($this->getPost('serial_number', ''));
        $patrimonialCode = trim($this->getPost('patrimonial_code', ''));
        $locationId = $this->getPost('location_id');
        $assignedUserId = $this->getPost('assigned_user_id');
        $status = $this->getPost('status', 'active');
        $notes = trim($this->getPost('observations', ''));

        $errors = $this->validate(
            compact('name', 'asset_type', 'serial_number'),
            ['name' => 'required', 'asset_type' => 'required']
        );
        if ($errors) {
            $this->error('Campos requeridos: ' . implode(', ', array_keys($errors)));
        }

        $pdo = Database::connect();
        try {
             $stmt = $pdo->prepare("
                 INSERT INTO oti.equipment (name, asset_type, brand, model, serial_number, patrimonial_code,
                     location_id, assigned_user_id, status, observations, is_deleted, created_at, updated_at)
                 VALUES (:name, :asset_type, :brand, :model, :serial_number, :patrimonial_code,
                     :location_id, :assigned_user_id, :status, :observations, false, NOW(), NOW())
                RETURNING id
            ");
            $stmt->execute([
                'name' => $name,
                'asset_type' => $assetType,
                'brand' => $brand ?: null,
                'model' => $model ?: null,
                'serial_number' => $serialNumber ?: null,
                'patrimonial_code' => $patrimonialCode ?: null,
                'location_id' => $locationId ? (int)$locationId : null,
                'assigned_user_id' => $assignedUserId ? (int)$assignedUserId : null,
                'status' => $status,
                'observations' => $notes ?: null
            ]);
            $equipmentId = $stmt->fetchColumn();
            $this->success(['id' => $equipmentId], 'Equipo creado exitosamente');
        } catch (\Exception $e) {
            error_log($e->getMessage());
            $this->error('Error al crear equipo', 500);
        }
    }

    public function update(): void
    {
        $this->redirectIfNotAdmin();

        $id = (int)($this->getPost('id', '0'));
        if ($id <= 0) {
            $this->error('ID de equipo requerido');
        }

        $fields = ['name', 'asset_type', 'brand', 'model', 'serial_number', 'patrimonial_code',
                   'status', 'observations'];
        $updates = [];
        $params = ['id' => $id];

        foreach ($fields as $field) {
            $val = $this->getPost($field);
            if ($val !== null) {
                $updates[] = "$field = :$field";
                $params[$field] = trim($val) ?: null;
            }
        }

        $locationId = $this->getPost('location_id');
        if ($locationId !== null) {
            $updates[] = "location_id = :location_id";
            $params['location_id'] = $locationId ? (int)$locationId : null;
        }

        $assignedUserId = $this->getPost('assigned_user_id');
        if ($assignedUserId !== null) {
            $updates[] = "assigned_user_id = :assigned_user_id";
            $params['assigned_user_id'] = $assignedUserId ? (int)$assignedUserId : null;
        }

        if (empty($updates)) {
            $this->error('No hay campos para actualizar');
        }

        $updates[] = "updated_at = NOW()";

        $pdo = Database::connect();
        try {
            $sql = "UPDATE oti.equipment SET " . implode(', ', $updates) . " WHERE id = :id AND is_deleted = false";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $this->success(null, 'Equipo actualizado');
        } catch (\Exception $e) {
            error_log($e->getMessage());
            $this->error('Error al actualizar equipo', 500);
        }
    }

    public function delete(): void
    {
        $this->redirectIfNotAdmin();

        $id = (int)($this->getQuery('id', '0'));
        if ($id <= 0) {
            $this->error('ID de equipo requerido');
        }

        $pdo = Database::connect();
        try {
            $stmt = $pdo->prepare("UPDATE oti.equipment SET is_deleted = true, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $this->success(null, 'Equipo eliminado');
        } catch (\Exception $e) {
            error_log($e->getMessage());
            $this->error('Error al eliminar equipo', 500);
        }
    }

    public function types(): void
    {
        $this->redirectIfNotAdmin();

        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT DISTINCT asset_type FROM oti.equipment WHERE is_deleted = false ORDER BY asset_type");
        $this->json($stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function users(): void
    {
        $this->redirectIfNotAdmin();
        $this->json(User::getAll());
    }

    public function renderListView(): void
    {
        $this->redirectIfNotAdmin();

        $equipment = Equipment::getAll([], 1, 100);
        $stats = Equipment::getStats();

        $this->view('v2/equipment/index.php', [
            'equipment' => $equipment,
            'stats' => $stats,
            'tituloPagina' => 'Equipos V2 - Sistema OTI',
            'paginaActual' => 'admin-equipos-v2'
        ]);
    }
}
