<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_login(bool $json = false): void {
    if (isset($_SESSION['user']['id'])) {
        return;
    }

    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Silakan login untuk melanjutkan.']);
    } else {
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $basePath = str_replace('\\', '/', dirname($scriptPath));
        $basePath = preg_replace('~/(?:controllers|views)(?:/.*)?$~', '', $basePath);
        $basePath = rtrim($basePath, '/');
        header('Location: ' . $basePath . '/login.php');
    }
    exit;
}

function require_valid_csrf(bool $json = false): void {
    $providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (is_string($providedToken) && $sessionToken !== '' && hash_equals($sessionToken, $providedToken)) {
        return;
    }

    if ($json) {
        http_response_code(419);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.']);
    } else {
        http_response_code(419);
        echo 'Token keamanan tidak valid. Muat ulang halaman.';
    }
    exit;
}
