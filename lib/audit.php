<?php
require_once __DIR__ . '/../config/db.php';

function audit_log(?int $userId, string $action, array $meta = []): void {
    try {
        $pdo = DatabaseConnection::getConnection();
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, meta) VALUES (?,?,?)');
        $stmt->execute([$userId, $action, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]);
    } catch (Throwable $e) {
        // Evita romper el flujo por errores de bitácora
    }
}

?>


