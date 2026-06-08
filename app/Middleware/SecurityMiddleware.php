<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Security;

class SecurityMiddleware
{
    public static function setHeaders(): void
    {
        Security::setHeaders();
    }

    public static function getNonce(): string
    {
        return Security::getNonce();
    }
}
