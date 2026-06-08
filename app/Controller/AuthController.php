<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\AuthService;

class AuthController
{
    public function login(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $maxAttempts = (int)getenv('MAX_LOGIN_ATTEMPTS') ?: 5;
        $lockoutTime = (int)getenv('LOCKOUT_TIME') ?: 900;

        $lockFile = sys_get_temp_dir() . '/oti_login_' . md5($ip) . '.lock';
        $attempts = 0;
        $lastAttempt = 0;
        if (file_exists($lockFile)) {
            $data = json_decode(file_get_contents($lockFile), true) ?? [];
            $attempts = $data['attempts'] ?? 0;
            $lastAttempt = $data['time'] ?? 0;

            if ($attempts >= $maxAttempts && (time() - $lastAttempt) < $lockoutTime) {
                $remaining = $lockoutTime - (time() - $lastAttempt);
                $_SESSION['error'] = "Demasiados intentos fallidos. Espere " . ceil($remaining / 60) . " minutos.";
                header('Location: ' . BASE_URL . 'login');
                exit;
            }
            if ((time() - $lastAttempt) >= $lockoutTime) {
                $attempts = 0;
            }
        }

        $identifier = trim($_POST['identifier'] ?? $_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            $_SESSION['error'] = 'Usuario y contraseña son requeridos';
            header('Location: ' . BASE_URL . 'login');
            exit;
        }

        $result = AuthService::login($identifier, $password);

        if (isset($result['error'])) {
            $attempts++;
            file_put_contents($lockFile, json_encode([
                'attempts' => $attempts,
                'time' => time(),
                'ip' => $ip
            ]), LOCK_EX);
            $_SESSION['error'] = $result['error'];
            header('Location: ' . BASE_URL . 'login');
            exit;
        }

        if (file_exists($lockFile)) {
            unlink($lockFile);
        }

        $isOtiAdmin = \App\Services\AuthService::isAdmin();
        header('Location: ' . ($isOtiAdmin ? BASE_URL . 'admin/dashboard' : BASE_URL . 'user/dashboard'));
        exit;
    }

    public function logout(): void
    {
        AuthService::logout();
    }
}