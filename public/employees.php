<?php
require_once __DIR__ . '/../lib/auth.php';
require_roles(['web_master','admin','operador']);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/audit.php';

$pdo = DatabaseConnection::getConnection();

// Crear / editar empleado
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
        $employeeCode = 'EMP-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare('INSERT INTO employees (employee_code, first_name, last_name, dpi, service, region, has_vehicle, vehicles_count, plate_number, active) VALUES (?,?,?,?,?,?,?,?,?,1)');
        try {
            $stmt->execute([$employeeCode, $firstName, $lastName, $dpi, $service, $region, $hasVehicle, $vehiclesCount, $plate]);
            $employeeId = (int)$pdo->lastInsertId();

            // Cargar foto si viene por archivo o por cámara (photo_data)
            if (!empty($_FILES['photo']['tmp_name']) || !empty($_POST['photo_data'])) {
                $targetDir = __DIR__ . '/uploads/';
                if (!is_dir($targetDir)) { @mkdir($targetDir, 0775, true); }
                $dest = $targetDir . 'emp_' . $employeeId . '.jpg';
                $imageData = null;
                if (!empty($_FILES['photo']['tmp_name'])) {
                    $imageData = file_get_contents($_FILES['photo']['tmp_name']);
                } elseif (!empty($_POST['photo_data'])) {
                    $raw = preg_replace('/^data:image\/(png|jpeg);base64,/', '', $_POST['photo_data']);
                    $imageData = base64_decode($raw);
                }
                if ($imageData !== false) {
                    // Re-escalar básico
                    $img = imagecreatefromstring($imageData);
                    if ($img) {
                        $w = imagesx($img); $h = imagesy($img);
                        $max = 900; $scale = min($max / max($w,$h), 1);
                        $nw = (int)($w*$scale); $nh = (int)($h*$scale);
                        $dst = imagecreatetruecolor($nw, $nh);
                        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        imagejpeg($dst, $dest, 85);
                        imagedestroy($dst); imagedestroy($img);
                        $rel = 'uploads/' . basename($dest);
                        $pdo->prepare('UPDATE employees SET photo_path = ? WHERE id = ?')->execute([$rel, $employeeId]);
                    }
                }
            }

            audit_log($_SESSION['user_id'] ?? null, 'employee.create', ['id' => $employeeId]);
            $message = 'Empleado creado';
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Activar/desactivar
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare('UPDATE employees SET active = IF(active=1,0,1) WHERE id = ?')->execute([$id]);
    audit_log($_SESSION['user_id'] ?? null, 'employee.toggle', ['id' => $id]);
    header('Location: employees.php');
    exit;
}

$employees = $pdo->query('SELECT * FROM employees ORDER BY id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados</title>
    <link rel="stylesheet" href="styles.css">
    <style>.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}</style>
    </head>
<body>
<header>
    <h1>Empleados</h1>
    <nav>
        <a href="index.php">Inicio</a>
        <a href="logout.php">Salir</a>
    </nav>
</header>
<main>
    <?php $msg = $_GET['msg'] ?? ''; if (!empty($message) || $msg): ?><div class="alert" style="background:#e7f7ee;border-color:#b4e2c6;color:#0a6d2a;"><?= htmlspecialchars($message ?: $msg) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="card" style="margin-bottom:20px;">
        <h2>Nuevo empleado</h2>
        <form method="post" enctype="multipart/form-data">
            <div class="grid">
                <label>Nombre
                    <input type="text" name="first_name" required>
                </label>
                <label>Apellidos
                    <input type="text" name="last_name" required>
                </label>
                <label>DPI (13 dígitos)
                    <input type="text" name="dpi" maxlength="13" pattern="[0-9]{13}" required>
                </label>
                <label>Servicio/Área
                    <input type="text" name="service" required>
                </label>
                <label>Región
                    <input type="text" name="region" required>
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
                <label><input id="has_vehicle" type="checkbox" name="has_vehicle" value="1"> Tiene vehículo</label>
                <div id="vehicle_fields" style="display:none">
                    <label>Cantidad vehículos
                        <input type="number" name="vehicles_count" min="0" value="0">
                    </label>
                    <label>Placa
                        <input type="text" name="plate_number">
                    </label>
                </div>
            </div>
            <button type="submit">Guardar</button>
        </form>
    </section>
    <script>
    const hasVehicle = document.getElementById('has_vehicle');
    const vehicleFields = document.getElementById('vehicle_fields');
    function toggleVehicle(){ vehicleFields.style.display = hasVehicle.checked ? 'block' : 'none'; }
    hasVehicle.addEventListener('change', toggleVehicle);
    toggleVehicle();

    // Tabs Foto: subir o cámara
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
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true });
            document.getElementById('cam').srcObject = stream;
        } catch (e) {
            alert('No se pudo acceder a la cámara: ' + e.message);
            showUpload();
        }
    }
    tabUpload.addEventListener('click', showUpload);
    tabCamera.addEventListener('click', showCamera);
    showUpload();

    // Capturar foto
    const btnCapture = document.getElementById('btn_capture');
    const canvas = document.getElementById('canvas');
    const cam = document.getElementById('cam');
    const photoData = document.getElementById('photo_data');
    if (btnCapture) {
        btnCapture.addEventListener('click', () => {
            const w = cam.videoWidth || 640; const h = cam.videoHeight || 480;
            canvas.width = w; canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(cam, 0, 0, w, h);
            photoData.value = canvas.toDataURL('image/jpeg', 0.9);
            alert('Foto capturada. Puedes guardar el formulario.');
        });
    }
    </script>

    <section>
        <h2>Lista de empleados</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>DPI</th>
                    <th>Servicio</th>
                    <th>Región</th>
                    <th>Vehículos</th>
                    <th>Activo</th>
                    <th>Foto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $e): ?>
                <tr>
                    <td><?= (int)$e['id'] ?></td>
                    <td><?= htmlspecialchars($e['employee_code']) ?></td>
                    <td><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></td>
                    <td><?= htmlspecialchars($e['dpi']) ?></td>
                    <td><?= htmlspecialchars($e['service']) ?></td>
                    <td><?= htmlspecialchars($e['region']) ?></td>
                    <td><?= $e['has_vehicle'] ? ((int)$e['vehicles_count'] . ' (' . htmlspecialchars($e['plate_number'] ?? '') . ')') : 'No' ?></td>
                    <td><?= $e['active'] ? 'Sí' : 'No' ?></td>
                    <td><?php if ($e['photo_path']): ?><img src="<?= htmlspecialchars($e['photo_path']) ?>" alt="foto" style="height:40px;border-radius:6px"><?php endif; ?></td>
                    <td class="actions">
                        <a href="employee_edit.php?id=<?= (int)$e['id'] ?>">Editar</a>
                        <a href="employee_delete.php?id=<?= (int)$e['id'] ?>">Eliminar</a>
                        <a href="employees.php?toggle=<?= (int)$e['id'] ?>"><?= $e['active'] ? 'Desactivar' : 'Activar' ?></a>
                        <a href="card.php?id=<?= (int)$e['id'] ?>&o=vertical" target="_blank">Carné V</a>
                        <a href="card.php?id=<?= (int)$e['id'] ?>&o=horizontal" target="_blank">Carné H</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>


