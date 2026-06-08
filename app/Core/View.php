<?php
declare(strict_types=1);

namespace App\Core;

class View
{
    private static array $shared = [];
    private static string $basePath = '';

    public static function init(string $basePath): void
    {
        self::$basePath = rtrim($basePath, '/') . '/';
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function getShared(string $key = null): mixed
    {
        if ($key === null) return self::$shared;
        return self::$shared[$key] ?? null;
    }

    public static function render(string $view, array $data = []): void
    {
        $viewPath = self::$basePath . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$viewPath}");
        }

        extract(self::$shared);
        extract($data);

        require $viewPath;
    }

    public static function exists(string $view): bool
    {
        return file_exists(self::$basePath . str_replace('.', '/', $view) . '.php');
    }

    public static function renderError(int $code, array $data = []): void
    {
        http_response_code($code);
        $view = 'errors/' . $code;
        if (self::exists($view)) {
            self::render($view, $data);
        } else {
            self::render('errors/fallback', $data);
        }
        exit;
    }
}
