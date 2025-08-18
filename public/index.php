<?php
session_start();

// Redirige al login si no hay sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/config.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel - Hospital</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>Panel principal</h1>
        <nav>
            <a href="users.php">Usuarios</a>
            <a href="employees.php">Empleados</a>
            <a href="visitors.php">Visitantes</a>
            <a href="logout.php">Cerrar sesión</a>
        </nav>
    </header>
    <main>
        <section>
            <h2>Bienvenido</h2>
            <p>Usa el menú para administrar el sistema de carnés y personal.</p>
        </section>
    </main>
</body>
</html>


