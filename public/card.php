<?php
require_once __DIR__ . '/../lib/auth.php';
require_roles(['web_master','admin','operador','visor']);
require_once __DIR__ . '/../config/db.php';

$pdo = DatabaseConnection::getConnection();
$id = (int)($_GET['id'] ?? 0);
$o = ($_GET['o'] ?? 'vertical') === 'horizontal' ? 'horizontal' : 'vertical';
$stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
$stmt->execute([$id]);
$e = $stmt->fetch();
if (!$e) { echo 'Carné no encontrado'; exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carné</title>
    <style>
        body{margin:0;display:grid;place-items:center;background:#e5e7eb}
        .card{width:<?= $o==='horizontal' ? '320px' : '220px' ?>;height:<?= $o==='horizontal' ? '220px' : '320px' ?>;background:#fff;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.1);padding:14px;display:flex;flex-direction:<?= $o==='horizontal' ? 'row' : 'column' ?>;gap:10px;}
        img.photo{width:<?= $o==='horizontal' ? '40%' : '100%' ?>;height:<?= $o==='horizontal' ? '100%' : '40%' ?>;object-fit:cover;border-radius:10px}
        .info{flex:1;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif}
        .name{font-weight:700}
        .small{font-size:12px;color:#111827}
        .code{font-family:monospace;background:#111827;color:#fff;padding:4px 6px;border-radius:6px;display:inline-block;margin-top:4px}
        @media print{body{background:#fff} .print-hide{display:none}}
    </style>
</head>
<body>
    <div class="card">
        <?php if ($e['photo_path']): ?><img class="photo" src="<?= htmlspecialchars($e['photo_path']) ?>"><?php endif; ?>
        <div class="info">
            <div class="name"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></div>
            <div class="small">DPI: <?= htmlspecialchars($e['dpi']) ?></div>
            <div class="small">Servicio: <?= htmlspecialchars($e['service']) ?></div>
            <div class="small">Región: <?= htmlspecialchars($e['region']) ?></div>
            <div class="code">Código: <?= htmlspecialchars($e['employee_code']) ?></div>
            <p class="small">(Agregaremos QR en una siguiente iteración)</p>
        </div>
    </div>
    <button class="print-hide" onclick="window.print()">Imprimir</button>
</body>
</html>


