<?php
require_once __DIR__ . '/../lib/auth.php';
require_roles(['web_master','admin']);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/audit.php';

$pdo = DatabaseConnection::getConnection();
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM employees WHERE id = ?');
    $stmt->execute([$id]);
    audit_log($_SESSION['user_id'] ?? null, 'employee.delete', ['id' => $id]);
    header('Location: carnes.php?msg=Empleado%20eliminado');
    exit;
}

$stmt = $pdo->prepare('SELECT id, first_name, last_name FROM employees WHERE id = ?');
$stmt->execute([$id]);
$e = $stmt->fetch();
if (!$e) { echo 'Empleado no encontrado'; exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar empleado</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header>
    <h1>Eliminar empleado</h1>
    <nav>
        <a href="carnes.php">Cancelar</a>
    </nav>
</header>
<main>
    <section class="card">
        <p>¿Seguro que deseas eliminar al empleado <strong><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></strong>?</p>
        <form method="post">
            <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
            <button type="submit" style="background:#d7263d">Eliminar definitivamente</button>
        </form>
    </section>
</main>
</body>
</html>


