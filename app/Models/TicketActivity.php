<?php
/**
 * Modelo de Actividades de Tickets
 * Sistema OTI - Historial de cambios
 */

namespace App\Models;

use App\Core\Model;

class TicketActivity extends Model
{
    /**
     * Obtiene actividades de un ticket
     */
    public static function getByTicketId($ticketId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT ta.*, u.nombre, u.apellidos
            FROM oti.ticket_activities ta
            LEFT JOIN admin.usuarios u ON ta.user_id = u.id
            WHERE ta.ticket_id = :ticket_id
            ORDER BY ta.created_at ASC
        ");
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll();
    }

    /**
     * Registra una actividad
     */
    public static function create($ticketId, $userId, $action, $description = null)
    {
        $pdo = self::db();
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO oti.ticket_activities (ticket_id, user_id, action, description)
                VALUES (:ticket_id, :user_id, :action, :description)
            ");
            
            $stmt->execute([
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'action' => $action,
                'description' => $description
            ]);
            
            return ['success' => true];
        } catch (\Exception $e) {
            error_log("Error creando actividad: " . $e->getMessage());
            return ['success' => false];
        }
    }

    /**
     * Obtiene actividades recientes
     */
    public static function getRecent($limit = 10)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT ta.*, t.code as ticket_code, t.title as ticket_title, u.nombre, u.apellidos
            FROM oti.ticket_activities ta
            JOIN oti.tickets t ON ta.ticket_id = t.id
            LEFT JOIN admin.usuarios u ON ta.user_id = u.id
            ORDER BY ta.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}