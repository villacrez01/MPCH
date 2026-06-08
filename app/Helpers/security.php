<?php
declare(strict_types=1);

namespace App\Helpers;

class Security
{
    private static string $nonce = '';

    public static function getNonce(): string
    {
        if (self::$nonce === '') {
            self::$nonce = bin2hex(random_bytes(16));
        }
        return self::$nonce;
    }

    public static function setHeaders(): void
    {
        $nonce = self::getNonce();

        header("X-Frame-Options: SAMEORIGIN");
        header("X-Content-Type-Options: nosniff");
        header("X-XSS-Protection: 1; mode=block");
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

        header("Content-Security-Policy: default-src 'self'; " .
            "script-src 'self' 'nonce-" . $nonce . "' https://cdn.jsdelivr.net; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "img-src 'self' data:; " .
            "base-uri 'self'; " .
            "form-action 'self'; " .
            "object-src 'none';");

        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
    }
}
