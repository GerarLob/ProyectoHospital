<?php
require_once __DIR__ . '/../lib/auth.php';
require_roles(['web_master','admin','operador']);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/audit.php';

$pdo = DatabaseConnection::getConnection();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
$stmt->execute([$id]);
$e = $stmt->fetch();
if (!$e) { echo 'Empleado no encontrado'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $dpi = preg_replace('/[^0-9]/', '', $_POST['dpi'] ?? '');
    $service = trim($_POST['service'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $hasVehicle = isset($_POST['has_vehicle']) ? 1 : 0;
    $vehiclesCount = (int)($_POST['vehicles_count'] ?? 0);
    $plate = trim($_POST['plate_number'] ?? '');

    if (strlen($dpi) !== 13) {
        $error = 'El DPI debe tener 13 dígitos';
    } elseif (!$firstName || !$lastName || !$service || !$region) {
        $error = 'Completa todos los campos obligatorios';
    } else {
        $stmt = $pdo->prepare('UPDATE employees SET first_name=?, last_name=?, dpi=?, service=?, region=?, has_vehicle=?, vehicles_count=?, plate_number=? WHERE id=?');
        $stmt->execute([$firstName,$lastName,$dpi,$service,$region,$hasVehicle,$vehiclesCount,$plate,$id]);

        // Foto opcional (archivo o cámara)
        if (!empty($_FILES['photo']['tmp_name']) || !empty($_POST['photo_data'])) {
            $targetDir = __DIR__ . '/uploads/';
            if (!is_dir($targetDir)) { @mkdir($targetDir, 0775, true); }
            $dest = $targetDir . 'emp_' . $id . '.jpg';
            $imageData = null;
            if (!empty($_FILES['photo']['tmp_name'])) {
                $imageData = file_get_contents($_FILES['photo']['tmp_name']);
            } elseif (!empty($_POST['photo_data'])) {
                $raw = preg_replace('/^data:image\/(png|jpeg);base64,/', '', $_POST['photo_data']);
                $imageData = base64_decode($raw);
            }
            $img = @imagecreatefromstring($imageData);
            if ($img) {
                $w = imagesx($img); $h = imagesy($img);
                $max = 900; $scale = min($max / max($w,$h), 1);
                $nw = (int)($w*$scale); $nh = (int)($h*$scale);
                $dst = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagejpeg($dst, $dest, 85);
                imagedestroy($dst); imagedestroy($img);
                $rel = 'uploads/' . basename($dest);
                $pdo->prepare('UPDATE employees SET photo_path = ? WHERE id = ?')->execute([$rel, $id]);
            }
        }

        audit_log($_SESSION['user_id'] ?? null, 'employee.update', ['id' => $id]);
        header('Location: employees.php?msg=Empleado%20actualizado');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar empleado</title>
    <link rel="stylesheet" href="styles.css">
    <style>.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}</style>
</head>
<body>
<header>
    <h1>Editar empleado</h1>
    <nav>
        <a href="employees.php">Volver</a>
    </nav>
</header>
<main>
    <?php if (!empty($error)): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <section class="card">
        <form method="post" enctype="multipart/form-data">
            <div class="grid">
                <label>Nombre
                    <input type="text" name="first_name" value="<?= htmlspecialchars($e['first_name']) ?>" required>
                </label>
                <label>Apellidos
                    <input type="text" name="last_name" value="<?= htmlspecialchars($e['last_name']) ?>" required>
                </label>
                <label>DPI
                    <input type="text" name="dpi" maxlength="13" pattern="[0-9]{13}" value="<?= htmlspecialchars($e['dpi']) ?>" required>
                </label>
                <label>Servicio/Área
                    <input type="text" name="service" value="<?= htmlspecialchars($e['service']) ?>" required>
                </label>
                <label>Región
                    <input type="text" name="region" value="<?= htmlspecialchars($e['region']) ?>" required>
                </label>
                <div>
                    <strong>Foto</strong>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                        <button type="button" id="tab_upload" style="padding:6px 10px">Subir archivo</button>
                        <button type="button" id="tab_camera" style="padding:6px 10px">Usar cámara</button>
                    </div>
                    <div id="upload_box" style="margin-top:8px">
                        <input type="file" name="photo" accept="image/*">
                    </div>
                    <div id="camera_box" style="display:none;margin-top:8px">
                        <video id="cam" autoplay playsinline style="width:240px;height:180px;border-radius:8px;background:#000"></video>
                        <div>
                            <button type="button" id="btn_capture">Tomar foto</button>
                            <input type="hidden" name="photo_data" id="photo_data">
                        </div>
                        <canvas id="canvas" style="display:none"></canvas>
                    </div>
                </div>
                <label><input id="has_vehicle" type="checkbox" name="has_vehicle" value="1" <?= $e['has_vehicle'] ? 'checked' : '' ?>> Tiene vehículo</label>
                <div id="vehicle_fields" style="display:none">
                    <label>Cantidad vehículos
                        <input type="number" name="vehicles_count" min="0" value="<?= (int)$e['vehicles_count'] ?>">
                    </label>
                    <label>Placa
                        <input type="text" name="plate_number" value="<?= htmlspecialchars($e['plate_number'] ?? '') ?>">
                    </label>
                </div>
            </div>
            <button type="submit">Guardar cambios</button>
        </form>
    </section>
</main>
<script>
const hasVehicle = document.getElementById('has_vehicle');
const vehicleFields = document.getElementById('vehicle_fields');
function toggleVehicle(){ vehicleFields.style.display = hasVehicle.checked ? 'block' : 'none'; }
hasVehicle.addEventListener('change', toggleVehicle);
toggleVehicle();

// Tabs Foto y cámara
const tabUpload = document.getElementById('tab_upload');
const tabCamera = document.getElementById('tab_camera');
const uploadBox = document.getElementById('upload_box');
const cameraBox = document.getElementById('camera_box');
let stream;
function showUpload(){
    uploadBox.style.display = 'block';
    cameraBox.style.display = 'none';
    if (stream) { stream.getTracks().forEach(t=>t.stop()); stream = null; }
}
async function showCamera(){
    uploadBox.style.display = 'none';
    cameraBox.style.display = 'block';
    try { stream = await navigator.mediaDevices.getUserMedia({ video: true }); document.getElementById('cam').srcObject = stream; }
    catch(e){ alert('No se pudo acceder a la cámara: ' + e.message); showUpload(); }
}
tabUpload.addEventListener('click', showUpload);
tabCamera.addEventListener('click', showCamera);
showUpload();

const btnCapture = document.getElementById('btn_capture');
const canvas = document.getElementById('canvas');
const cam = document.getElementById('cam');
const photoData = document.getElementById('photo_data');
if (btnCapture) {
  btnCapture.addEventListener('click', () => {
    const w = cam.videoWidth || 640; const h = cam.videoHeight || 480;
    canvas.width = w; canvas.height = h; const ctx = canvas.getContext('2d');
    ctx.drawImage(cam,0,0,w,h); photoData.value = canvas.toDataURL('image/jpeg',0.9);
    alert('Foto capturada. Puedes guardar los cambios.');
  });
}
</script>
</body>
</html>


