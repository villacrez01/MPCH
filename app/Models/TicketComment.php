<?php
/**
 * Modelo de Comentarios de Tickets
 * Sistema OTI - Gestión de comentarios
 */

namespace App\Models;

use App\Core\Model;

class TicketComment extends Model
{
    /**
     * Obtiene comentarios de un ticket
     */
    public static function getByTicketId($ticketId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT tc.*, u.nombre, u.apellidos, u.avatar
            FROM oti.ticket_comments tc
            JOIN admin.usuarios u ON tc.user_id = u.id
            WHERE tc.ticket_id = :ticket_id
            ORDER BY tc.created_at ASC
        ");
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll();
    }

    /**
     * Crea un nuevo comentario
     */
    public static function create($ticketId, $userId, $comment)
    {
        $pdo = self::db();
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO oti.ticket_comments (ticket_id, user_id, comment)
                VALUES (:ticket_id, :user_id, :comment)
                RETURNING id
            ");
            
            $stmt->execute([
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'comment' => strip_tags(trim($comment))
            ]);
            
            return ['success' => true, 'id' => $stmt->fetchColumn()];
        } catch (\Exception $e) {
            error_log("Error creando comentario: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al crear el comentario'];
        }
    }

    /**
     * Elimina un comentario
     */
    public static function delete($commentId, $userId)
    {
        $pdo = self::db();
        
        $stmt = $pdo->prepare("
            DELETE FROM oti.ticket_comments 
            WHERE id = :id AND user_id = :user_id
            RETURNING id
        ");
        
        $stmt->execute(['id' => $commentId, 'user_id' => $userId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Cuenta comentarios de un ticket
     */
    public static function countByTicketId($ticketId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM oti.ticket_comments WHERE ticket_id = :ticket_id");
        $stmt->execute(['ticket_id' => $ticketId]);
        return (int)$stmt->fetchColumn();
    }
}