<?php
declare(strict_types=1);

namespace App\Helpers;

class FeatureFlag
{
    private static array $cache = [];
    private static bool $loaded = false;

    public static function isActive(string $flag): bool
    {
        if (!self::$loaded) {
            self::loadFlags();
        }
        return self::$cache[$flag] ?? false;
    }

    public static function all(): array
    {
        if (!self::$loaded) {
            self::loadFlags();
        }
        return self::$cache;
    }

    public static function set(string $flag, bool $value): void
    {
        self::$cache[$flag] = $value;
    }

    private static function loadFlags(): void
    {
        self::$cache = [
            'MODULE_TICKETS_V2' => filter_var(getenv('MODULE_TICKETS_V2') ?: 'false', FILTER_VALIDATE_BOOLEAN),
            'MODULE_USERS_V2' => filter_var(getenv('MODULE_USERS_V2') ?: 'false', FILTER_VALIDATE_BOOLEAN),
            'MODULE_EQUIPMENT_V2' => filter_var(getenv('MODULE_EQUIPMENT_V2') ?: 'false', FILTER_VALIDATE_BOOLEAN),
            'CSRF_STRICT' => filter_var(getenv('CSRF_STRICT') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'SESSION_HARDENING' => filter_var(getenv('SESSION_HARDENING') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        ];
        self::$loaded = true;
    }
}
