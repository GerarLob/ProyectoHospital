<?php
require_once __DIR__ . '/../lib/auth.php';
require_roles(['web_master','admin','operador']);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/audit.php';

$pdo = DatabaseConnection::getConnection();

// Inicializar 1..100 por tipo si no existen
if (isset($_GET['init']) && $_GET['init'] === '1') {
    $types = [
        ['cuidador', '#00a6fb'],
        ['tramitador', '#f59e0b'],
        ['visitante', '#10b981'],
    ];
    foreach ($types as [$type, $color]) {
        for ($i = 1; $i <= 100; $i++) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO visitor_badges (badge_number, badge_type, color) VALUES (?,?,?)');
            $stmt->execute([$i, $type, $color]);
        }
    }
    audit_log($_SESSION['user_id'] ?? null, 'visitor_badges.init');
    header('Location: visitors.php');
    exit;
}

// Entregar (check-out) o devolver (check-in)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign'])) {
        $badgeId = (int)$_POST['badge_id'];
        $dpi = trim($_POST['dpi'] ?? '');
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $cat = $_POST['person_category'] ?? 'Visitante';
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE visitor_badges SET status = 'ocupado' WHERE id = ? AND status = 'disponible'")->execute([$badgeId]);
        if ($pdo->query('SELECT ROW_COUNT()')->fetchColumn() > 0) {
            $pdo->prepare('INSERT INTO visitor_sessions (badge_id, dpi, first_name, last_name, person_category) VALUES (?,?,?,?,?)')
                ->execute([$badgeId, $dpi, $first, $last, $cat]);
            $pdo->commit();
            audit_log($_SESSION['user_id'] ?? null, 'visitor.assign', ['badge_id' => $badgeId]);
            $message = 'Carné asignado';
        } else {
            $pdo->rollBack();
            $error = 'El carné ya está ocupado';
        }
    }
    if (isset($_POST['return'])) {
        $badgeId = (int)$_POST['badge_id'];
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE visitor_badges SET status = 'disponible' WHERE id = ?")->execute([$badgeId]);
        $pdo->prepare('UPDATE visitor_sessions SET ended_at = NOW() WHERE badge_id = ? AND ended_at IS NULL')
            ->execute([$badgeId]);
        $pdo->commit();
        audit_log($_SESSION['user_id'] ?? null, 'visitor.return', ['badge_id' => $badgeId]);
        $message = 'Carné devuelto';
    }
}

$type = $_GET['type'] ?? 'visitante';
$badges = $pdo->prepare('SELECT * FROM visitor_badges WHERE badge_type = ? ORDER BY badge_number');
$badges->execute([$type]);
$badges = $badges->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitantes</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .badge{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:10px;margin:6px;color:#fff;font-weight:700}
        .grid{display:flex;flex-wrap:wrap}
        .row{display:flex;gap:10px;align-items:end}
    </style>
</head>
<body>
<header>
    <h1>Gestión de Visitantes</h1>
    <nav>
        <a href="index.php">Inicio</a>
        <a href="visitors.php?init=1">Inicializar 1..100</a>
        <a href="logout.php">Salir</a>
    </nav>
</header>
<main>
    <?php if (!empty($message)): ?><div class="alert" style="background:#e7f7ee;border-color:#b4e2c6;color:#0a6d2a;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="card" style="margin-bottom:20px;">
        <h2>Asignar carné</h2>
        <form method="post" class="row">
            <label>Carné
                <select name="badge_id" required>
                    <?php foreach ($badges as $b): if ($b['status'] !== 'disponible') continue; ?>
                        <option value="<?= (int)$b['id'] ?>">#<?= (int)$b['badge_number'] ?> (<?= htmlspecialchars($b['badge_type']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>DPI
                <input type="text" name="dpi">
            </label>
            <label>Nombre
                <input type="text" name="first_name">
            </label>
            <label>Apellido
                <input type="text" name="last_name">
            </label>
            <label>Tipo persona
                <select name="person_category">
                    <option>Cuidador</option>
                    <option>Tramitador</option>
                    <option selected>Visitante</option>
                </select>
            </label>
            <button type="submit" name="assign">Entregar</button>
        </form>
    </section>

    <section class="card" style="margin-bottom:20px;">
        <h2>Devolver carné</h2>
        <form method="post" class="row">
            <label>Carné ocupado
                <select name="badge_id" required>
                    <?php foreach ($badges as $b): if ($b['status'] !== 'ocupado') continue; ?>
                        <option value="<?= (int)$b['id'] ?>">#<?= (int)$b['badge_number'] ?> (<?= htmlspecialchars($b['badge_type']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" name="return">Devolver</button>
        </form>
    </section>

    <section>
        <h2>Carnés de tipo: <?= htmlspecialchars($type) ?></h2>
        <div class="grid">
            <?php foreach ($badges as $b): ?>
                <div class="badge" style="background: <?= htmlspecialchars($b['color']) ?>;opacity: <?= $b['status']==='ocupado' ? '0.4' : '1' ?>" title="<?= htmlspecialchars($b['status']) ?>"><?= (int)$b['badge_number'] ?></div>
            <?php endforeach; ?>
        </div>
    </section>
</main>
</body>
</html>


