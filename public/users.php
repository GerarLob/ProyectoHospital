<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['role'], ['web_master','admin'])) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

$pdo = DatabaseConnection::getConnection();

// Crear usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'operador';
    if ($username && $password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, active) VALUES (?,?,?,1)');
        try {
            $stmt->execute([$username, $hash, $role]);
            $message = 'Usuario creado';
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Completa todos los campos';
    }
}

// Activar / desactivar
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare('UPDATE users SET active = IF(active=1,0,1) WHERE id = ?')->execute([$id]);
    header('Location: users.php');
    exit;
}

$users = $pdo->query('SELECT id, username, role, active, created_at FROM users ORDER BY id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>Usuarios</h1>
        <nav>
            <a href="index.php">Inicio</a>
            <a href="logout.php">Salir</a>
        </nav>
    </header>
    <main>
        <?php if (!empty($message)): ?><div class="alert" style="background:#e7f7ee;border-color:#b4e2c6;color:#0a6d2a;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <section class="card" style="margin-bottom:20px;">
            <h2>Crear usuario</h2>
            <form method="post">
                <label>Usuario
                    <input type="text" name="username" required>
                </label>
                <label>Contraseña
                    <input type="password" name="password" required>
                </label>
                <label>Rol
                    <select name="role">
                        <option value="operador">Operador</option>
                        <option value="admin">Administrador</option>
                        <option value="web_master">Web Master</option>
                        <option value="visor">Visor</option>
                    </select>
                </label>
                <button type="submit">Guardar</button>
            </form>
        </section>

        <section>
            <h2>Lista de usuarios</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Activo</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['role']) ?></td>
                        <td><?= $u['active'] ? 'Sí' : 'No' ?></td>
                        <td><?= htmlspecialchars($u['created_at']) ?></td>
                        <td class="actions">
                            <a href="?toggle=<?= (int)$u['id'] ?>"><?= $u['active'] ? 'Desactivar' : 'Activar' ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>


