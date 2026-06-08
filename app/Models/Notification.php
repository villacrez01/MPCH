<?php
/**
 * Modelo de Notificaciones
 * Sistema OTI - Gestión de notificaciones
 */

namespace App\Models;

use App\Core\Model;

class Notification extends Model
{
    /**
     * Obtiene notificaciones de un usuario
     */
    public static function getByUserId($userId, $limit = 20)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT id, user_id, title as titulo, message as mensaje, type as tipo, 
                   is_read as leida, ticket_id, created_at
            FROM oti.notifications
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        // Bind and execute both parameters together to avoid driver-specific bind issues
        $limit = (int)$limit;
        $stmt->execute(['user_id' => $userId, 'limit' => $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene notificaciones no leídas
     */
    public static function getUnreadByUserId($userId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT * FROM oti.notifications
            WHERE user_id = :user_id AND is_read = false
            ORDER BY created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Cuenta notificaciones no leídas
     */
    public static function countUnread($userId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM oti.notifications
            WHERE user_id = :user_id AND is_read = false
        ");
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Crea una notificación
     */
    public static function create($userId, $title, $message, $type = 'info', $ticketId = null)
    {
        $pdo = self::db();
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO oti.notifications (user_id, title, message, type, ticket_id)
                VALUES (:user_id, :title, :message, :type, :ticket_id)
            ");
            
            $stmt->execute([
                'user_id' => $userId,
                'title' => strip_tags($title),
                'message' => strip_tags($message),
                'type' => $type,
                'ticket_id' => $ticketId
            ]);
            
            return ['success' => true];
        } catch (\Exception $e) {
            error_log("Error creando notificación: " . $e->getMessage());
            return ['success' => false];
        }
    }

    /**
     * Marca una notificación como leída
     */
    public static function markAsRead($notificationId, $userId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            UPDATE oti.notifications 
            SET is_read = true 
            WHERE id = :id AND user_id = :user_id
        ");
        return $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    }

    /**
     * Marca todas las notificaciones como leídas
     */
    public static function markAllAsRead($userId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("UPDATE oti.notifications SET is_read = true WHERE user_id = :user_id");
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Notifica al usuario sobre un nuevo ticket
     */
    public static function notifyNewTicket($ticketId, $adminId)
    {
        return self::create(
            $adminId,
            'Nuevo Ticket',
            'Se ha creado un nuevo ticket que requiere atención',
            'ticket_created',
            $ticketId
        );
    }

    /**
     * Notifica al usuario sobre respuesta a su ticket
     */
    public static function notifyTicketResponse($ticketId, $userId)
    {
        return self::create(
            $userId,
            'Respuesta a Ticket',
            'Se ha respondido a tu ticket',
            'ticket_response',
            $ticketId
        );
    }

    /**
     * Notifica al usuario sobre cambio de estado
     */
    public static function notifyStatusChange($ticketId, $userId, $newStatus)
    {
        return self::create(
            $userId,
            'Estado de Ticket Actualizado',
            "Tu ticket ha cambiado a: {$newStatus}",
            'ticket_updated',
            $ticketId
        );
    }
}