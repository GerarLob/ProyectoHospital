<?php
session_start();

function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_roles(array $roles): void {
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles)) {
        http_response_code(403);
        echo 'No autorizado';
        exit;
    }
}

?>


