<?php
declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    protected static function db(): \PDO
    {
        return Database::connect();
    }
}
