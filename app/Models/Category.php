<?php
/**
 * Modelo de Categorías
 * Sistema OTI - Gestión de categorías de tickets
 */

namespace App\Models;

use App\Core\Model;

class Category extends Model
{
    /**
     * Obtiene todas las categorías
     */
    public static function getAll($includeInactive = false)
    {
        $pdo = self::db();
        
        $where = $includeInactive ? "" : "WHERE active = true";
        
        $stmt = $pdo->query("
            SELECT c.*, 
                   (SELECT name FROM oti.categories WHERE id = c.parent_id) as parent_name
            FROM oti.categories c
            {$where}
            ORDER BY c.name
        ");
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una categoría por ID
     */
    public static function findById($id)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT * FROM oti.categories WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Obtiene categorías principales (sin padre)
     */
    public static function getParentCategories()
    {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT * FROM oti.categories 
            WHERE parent_id IS NULL AND active = true 
            ORDER BY name
        ");
        return $stmt->fetchAll();
    }

    /**
     * Obtiene las subcategorías de una categoría
     */
    public static function getSubcategories($parentId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT * FROM oti.categories 
            WHERE parent_id = :parent_id AND active = true 
            ORDER BY name
        ");
        $stmt->execute(['parent_id' => $parentId]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene el árbol de categorías
     */
    public static function getTree()
    {
        $categories = self::getAll(true);
        
        $tree = [];
        foreach ($categories as $cat) {
            if ($cat['parent_id'] === null) {
                $tree[$cat['id']] = [
                    'category' => $cat,
                    'children' => []
                ];
            }
        }
        
        foreach ($categories as $cat) {
            if ($cat['parent_id'] !== null && isset($tree[$cat['parent_id']])) {
                $tree[$cat['parent_id']]['children'][] = $cat;
            }
        }
        
        return $tree;
    }

    /**
     * Cuenta tickets por categoría
     */
    public static function countTickets($categoryId)
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM oti.tickets WHERE category_id = :category_id");
        $stmt->execute(['category_id' => $categoryId]);
        return (int)$stmt->fetchColumn();
    }
}