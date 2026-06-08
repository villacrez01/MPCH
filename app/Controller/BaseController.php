<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helpers\FeatureFlag;

abstract class BaseController
{
    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function success(mixed $data = null, string $message = 'Operación exitosa'): void
    {
        $this->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    protected function error(string $message, int $status = 400): void
    {
        $this->json(['success' => false, 'error' => $message], $status);
    }

    protected function view(string $path, array $data = []): void
    {
        extract($data);
        $baseUrl = base_url();
        require __DIR__ . '/../Views/' . $path;
        exit;
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function csrf(): string
    {
        return csrf_token();
    }

    protected function verifyCsrf(): bool
    {
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return verify_csrf($token);
    }

    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $data[$field] ?? null;

            foreach ($rules as $rule) {
                if ($rule === 'required' && (empty($value) && $value !== '0')) {
                    $errors[$field][] = "El campo $field es requerido";
                }
                if ($rule === 'numeric' && $value !== null && $value !== '' && !is_numeric($value)) {
                    $errors[$field][] = "El campo $field debe ser numérico";
                }
                if ($rule === 'email' && $value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "El campo $field debe ser un email válido";
                }
                if (str_starts_with($rule, 'max:') && $value !== null) {
                    $max = (int)substr($rule, 4);
                    if (is_string($value) && mb_strlen($value) > $max) {
                        $errors[$field][] = "El campo $field no debe exceder $max caracteres";
                    }
                }
                if (str_starts_with($rule, 'min:') && $value !== null) {
                    $min = (int)substr($rule, 3);
                    if (is_string($value) && mb_strlen($value) < $min) {
                        $errors[$field][] = "El campo $field debe tener al menos $min caracteres";
                    }
                }
            }
        }
        return $errors;
    }

    protected function isFeatureActive(string $flag): bool
    {
        return FeatureFlag::isActive($flag);
    }

    protected function redirectIfNotAdmin(): void
    {
        if (!\App\Services\AuthService::isAdmin()) {
            $this->error('Acceso denegado', 403);
        }
    }

    protected function redirectIfNotAuth(): void
    {
        if (!isset($_SESSION['user'])) {
            $this->error('No autorizado', 401);
        }
    }

    protected function getUserId(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    }

    protected function getPost(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    protected function getQuery(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
}
