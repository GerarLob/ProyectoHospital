<?php
// Utilidades de autenticación y autorización
require_once __DIR__ . '/../config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_user_role(): ?string {
    return $_SESSION['role'] ?? null;
}

function has_any_role(array $roles): bool {
    $role = $_SESSION['role'] ?? '';
    return in_array($role, $roles, true);
}

function require_login(): void {
    if (!is_logged_in()) {
        $loginPath = rtrim(APP_BASE_PATH, '/') . '/login.php';
        header('Location: ' . $loginPath);
        exit;
    }
}

function require_roles(array $roles): void {
    require_login();
    if (!has_any_role($roles)) {
        http_response_code(403);
        echo 'No autorizado';
        exit;
    }
}

?>


