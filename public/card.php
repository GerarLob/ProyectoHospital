<?php
require_once __DIR__ . '/../lib/auth.php';
require_roles(['web_master','admin','operador','visor']);
require_once __DIR__ . '/../config/db.php';

$pdo = DatabaseConnection::getConnection();
$id = (int)($_GET['id'] ?? 0);
$o = ($_GET['o'] ?? 'vertical') === 'horizontal' ? 'horizontal' : 'vertical';
$wmm = $o === 'horizontal' ? '86mm' : '54mm';
$hmm = $o === 'horizontal' ? '54mm' : '86mm';
$side = (($_GET['s'] ?? 'front') === 'back') ? 'back' : 'front';
$stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
$stmt->execute([$id]);
$e = $stmt->fetch();
if (!$e) { echo 'Carné no encontrado'; exit; }

// Logo de la empresa (opcional)
$companyLogoRel = null;
$companyLogoAbs = __DIR__ . '/uploads/company_logo.png';
if (is_file($companyLogoAbs)) {
    $companyLogoRel = 'uploads/company_logo.png?v=' . filemtime($companyLogoAbs);
}

// Configuración de textos y logo trasero
$configPath = __DIR__ . '/uploads/card_config.json';
$cardConfig = ['front_title'=>'','front_subtitle'=>'','back_notes'=>''];
if (is_file($configPath)) {
    $data = json_decode(@file_get_contents($configPath), true);
    if (is_array($data)) { $cardConfig = array_merge($cardConfig, $data); }
}
$companyBackRel = null; $companyBackAbs = __DIR__ . '/uploads/company_back.png';
if (is_file($companyBackAbs)) { $companyBackRel = 'uploads/company_back.png?v=' . filemtime($companyBackAbs); }
$backImageRel = null; $backImageAbs = __DIR__ . '/uploads/card_back_image.png';
if (is_file($backImageAbs)) { $backImageRel = 'uploads/card_back_image.png?v=' . filemtime($backImageAbs); }

// Texto para QR (incluye datos básicos)
$qrText = 'EMP:' . ($e['employee_code'] ?? '')
    . ';NOMBRE:' . trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''))
    . ';DPI:' . ($e['dpi'] ?? '')
    . ';SERVICIO:' . ($e['service'] ?? '')
    . ';REGION:' . ($e['region'] ?? '');
$qrSize = $o === 'horizontal' ? 130 : 140;
$role = $_SESSION['role'] ?? '';
$isViewer = ($role === 'visor');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carné</title>
    <style>
        body{margin:0;display:grid;place-items:center;background:#eef2ff}
        .card{position:relative;width:<?= $o==='horizontal' ? '340px' : '230px' ?>;height:<?= $o==='horizontal' ? '230px' : '340px' ?>;background:linear-gradient(180deg,#ffffff 60%,#f8fafc);border-radius:14px;box-shadow:0 10px 28px rgba(0,0,0,.12);padding:14px;display:flex;flex-direction:<?= $o==='horizontal' ? 'row' : 'column' ?>;gap:10px;border:1px solid #e5e7eb}
        img.photo{width:<?= $o==='horizontal' ? '42%' : '100%' ?>;height:<?= $o==='horizontal' ? '100%' : '42%' ?>;object-fit:cover;border-radius:12px;border:1px solid #d1d5db}
        .info{flex:1;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif}
        .name{font-weight:800;letter-spacing:.2px;color:#0f172a}
        .small{font-size:12px;color:#334155;margin-top:2px}
        .code{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;background:#111827;color:#fff;padding:4px 8px;border-radius:8px;display:inline-block;margin-top:6px}
        .brand{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
        .brand .title{font-weight:900;color:#0f172a;letter-spacing:.3px}
        .brand img.logo{height:<?= $o==='horizontal' ? '28px' : '32px' ?>;object-fit:contain;filter:drop-shadow(0 1px 1px rgba(0,0,0,.05))}
        .qr{width:<?= $qrSize ?>px;height:<?= $qrSize ?>px;border-radius:10px;background:#fff;border:1px solid #e5e7eb}
        .qr img,.qr canvas{width:100%;height:100%;border-radius:10px}
        @page { size: letter; margin: 10mm; }
        @media print{
            body{background:#fff}
            .print-hide{display:none}
            .card{width: <?= $wmm ?>; height: <?= $hmm ?>; box-shadow:none; border:0.3mm dashed #9ca3af; border-radius:3mm; padding:4mm; background:#fff}
            img.photo{border-radius:2mm;border:0.3mm solid #d1d5db}
            .qr{width:24mm;height:24mm;border:0.3mm solid #d1d5db;border-radius:2mm}
            .name{font-size:12pt}
            .small{font-size:9pt}
            .code{font-size:9pt}
        }
        .back{display:flex;flex-direction:column;justify-content:space-between}
        .back .notes{font-size:12px;color:#0f172a;background:#f7fbff;border:1px dashed #cbd5e1;border-radius:10px;padding:8px;min-height:68px}
        <?php if ($isViewer): ?>
        /* Bloquear impresión para VISOR */
        @media print{
            .card{display:none !important}
            .viewer-print-message{display:block !important}
        }
        .viewer-print-message{display:none;color:#0f172a;text-align:center;padding:40px;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif}
        <?php endif; ?>
    </style>
</head>
<body>
    <div class="card">
        <?php if ($side === 'front'): ?>
            <?php if ($e['photo_path']): ?><img class="photo" src="<?= htmlspecialchars($e['photo_path']) ?>"><?php endif; ?>
            <div class="info">
                <div class="brand">
                    <div style="font-weight:700;color:#111827"><?= htmlspecialchars($cardConfig['front_title'] ?: 'IDENTIFICACIÓN') ?></div>
                    <?php if ($companyLogoRel): ?><img class="logo" src="<?= htmlspecialchars($companyLogoRel) ?>" alt="logo"><?php endif; ?>
                </div>
                <?php if ($cardConfig['front_subtitle']): ?><div class="small" style="margin-bottom:6px;color:#2563eb;font-weight:600"><?= htmlspecialchars($cardConfig['front_subtitle']) ?></div><?php endif; ?>
                <div class="name"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></div>
                <div class="small">DPI: <?= htmlspecialchars($e['dpi']) ?></div>
                <div class="small">Servicio: <?= htmlspecialchars($e['service']) ?></div>
                <div class="small">Región: <?= htmlspecialchars($e['region']) ?></div>
                <div class="code">Código: <?= htmlspecialchars($e['employee_code']) ?></div>
                <div style="margin-top:8px"><div id="qr" class="qr"></div></div>
            </div>
        <?php else: ?>
            <div class="info back">
                <div>
                    <div class="brand" style="justify-content:space-between">
                        <div style="font-weight:700;color:#111827">Reverso</div>
                        <?php if (!$backImageRel && $companyBackRel): ?><img class="logo" src="<?= htmlspecialchars($companyBackRel) ?>" alt="logo"><?php endif; ?>
                    </div>
                    <?php if ($backImageRel): ?><div style="margin:6px 0"><img src="<?= htmlspecialchars($backImageRel) ?>" alt="img" style="width:100%;border-radius:8px"></div><?php endif; ?>
                    <div class="notes"><?= nl2br(htmlspecialchars($cardConfig['back_notes'] ?: 'Uso exclusivo institucional. Si encuentra este carné, favor devolver a recursos humanos.')) ?></div>
                </div>
                <div style="margin-top:8px;align-self:center"><div id="qr" class="qr"></div></div>
            </div>
        <?php endif; ?>
    </div>
    <?php if (!$isViewer): ?>
    <button class="print-hide" onclick="window.print()">Imprimir</button>
    <?php endif; ?>
    <?php if ($isViewer): ?>
    <div class="viewer-print-message">Impresión deshabilitada para usuarios con rol VISOR.</div>
    <?php endif; ?>
    <script src="qrcode.min.js"></script>
    <script>
    (function(){
        var text = <?= json_encode($qrText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        var size = <?= (int)$qrSize ?>;
        var el = document.getElementById('qr');
        if (el && window.QRCode) {
            new QRCode(el, { text: text, width: size, height: size, correctLevel: QRCode.CorrectLevel.M });
        }
    })();
    </script>
</body>
</html>


