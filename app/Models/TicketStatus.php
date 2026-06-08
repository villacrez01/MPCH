<?php
/**
 * Modelo de Estados, Prioridades y Severidades de Tickets
 * Sistema OTI
 */

namespace App\Models;

use App\Core\Model;

class TicketStatus extends Model
{
    public static function getAll()
    {
        $pdo = self::db();
        $stmt = $pdo->query("SELECT * FROM oti.ticket_statuses ORDER BY id");
        return $stmt->fetchAll();
    }
}