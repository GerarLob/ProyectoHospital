<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Registra una acción en la bitácora. Ignora fallos para no romper el flujo.
 */
function audit_log(?int $userId, string $action, array $meta = []): void {
    try {
        $pdo = DatabaseConnection::getConnection();
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, meta) VALUES (?,?,?)');
        $payload = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $stmt->execute([$userId, $action, $payload]);
    } catch (Throwable $e) {
        // No-op
    }
}

?>


