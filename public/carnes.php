<?php
require_once __DIR__ . '/../lib/auth.php';
require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/audit.php';

$role = $_SESSION['role'] ?? '';
$canManage = in_array($role, ['web_master','admin','operador']);

$pdo = DatabaseConnection::getConnection();

// Configuración del carné (persistida en JSON)
$configPath = __DIR__ . '/uploads/card_config.json';
$cardConfig = ['front_title'=>'','front_subtitle'=>'','back_notes'=>'','layoutFront'=>[],'layoutBack'=>[]];
if (is_file($configPath)) {
	$data = json_decode(@file_get_contents($configPath), true);
	if (is_array($data)) { $cardConfig = array_merge($cardConfig, $data); }
}

// Acciones (solo gestores)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
	$action = $_POST['action'] ?? '';
	switch ($action) {
		case 'create_employee':
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
							if (function_exists('imagecreatefromstring')) {
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
									$pdo->prepare('UPDATE employees SET photo_path = ? WHERE id = ?')->execute([$rel, $employeeId]);
								}
							}
							else {
								file_put_contents($dest, $imageData);
								$rel = 'uploads/' . basename($dest);
								$pdo->prepare('UPDATE employees SET photo_path = ? WHERE id = ?')->execute([$rel, $employeeId]);
							}
						}

					}

					audit_log($_SESSION['user_id'] ?? null, 'employee.create', ['id' => $employeeId]);
					$message = 'Registro creado';
				} catch (PDOException $e) {
					$error = 'Error: ' . $e->getMessage();
				}
			}
			break;
		case 'upload_logo':
			if (!empty($_FILES['company_logo']['tmp_name'])) {
				$targetDir = __DIR__ . '/uploads/';
				if (!is_dir($targetDir)) { @mkdir($targetDir, 0775, true); }
				$dest = $targetDir . 'company_logo.png';
				$rawData = file_get_contents($_FILES['company_logo']['tmp_name']);
				if ($rawData !== false) {
					if (function_exists('imagecreatefromstring')) {
						$img = @imagecreatefromstring($rawData);
						if ($img) {
							$w = imagesx($img); $h = imagesy($img);
							$max = 600; $scale = min($max / max($w,$h), 1);
							$nw = (int)($w*$scale); $nh = (int)($h*$scale);
							$dst = imagecreatetruecolor($nw, $nh);
							$white = imagecolorallocate($dst, 255,255,255);
							imagefilledrectangle($dst, 0,0,$nw,$nh, $white);
							imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
							imagepng($dst, $dest, 6);
							imagedestroy($dst); imagedestroy($img);
							$message = 'Logo actualizado';
						} else { $error = 'No se pudo procesar la imagen del logo'; }
					} else {
						if (!@move_uploaded_file($_FILES['company_logo']['tmp_name'], $dest)) {
							file_put_contents($dest, $rawData);
						}
						$message = 'Logo actualizado';
					}
				} else { $error = 'No se recibió archivo de logo'; }
			}
			break;
		case 'save_front':
			$cardConfig['front_title'] = trim($_POST['front_title'] ?? '');
			$cardConfig['front_subtitle'] = trim($_POST['front_subtitle'] ?? '');
			@file_put_contents($configPath, json_encode($cardConfig, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
			$message = 'Configuración frontal guardada';
			break;
		case 'save_back':
			$cardConfig['back_notes'] = trim($_POST['back_notes'] ?? '');
			// logo e imagen trasera ya gestionados arriba...
			@file_put_contents($configPath, json_encode($cardConfig, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
			$message = 'Configuración trasera guardada';
			break;
		case 'save_layout':
			$raw = $_POST['layout_json'] ?? '';
			$data = json_decode($raw, true);
			if (is_array($data)) {
				$cardConfig['layoutFront'] = $data['front'] ?? [];
				$cardConfig['layoutBack'] = $data['back'] ?? [];
				@file_put_contents($configPath, json_encode($cardConfig, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
				$message = 'Distribución guardada';
			} else { $error = 'No se pudo guardar la distribución'; }
			break;
		default:
			// no-op
	}
}

// Activar/desactivar (solo gestores)
if ($canManage && isset($_GET['toggle'])) {
	$id = (int)$_GET['toggle'];
	$pdo->prepare('UPDATE employees SET active = IF(active=1,0,1) WHERE id = ?')->execute([$id]);
	audit_log($_SESSION['user_id'] ?? null, 'employee.toggle', ['id' => $id]);
	header('Location: carnes.php');
	exit;
}

$employees = $pdo->query('SELECT * FROM employees ORDER BY id DESC')->fetchAll();

// Logo actual
$companyLogoRel = null;
$companyLogoAbs = __DIR__ . '/uploads/company_logo.png';
if (is_file($companyLogoAbs)) { $companyLogoRel = 'uploads/company_logo.png?v=' . filemtime($companyLogoAbs); }
$backLogoRel = null; $backLogoAbs = __DIR__ . '/uploads/company_back.png';
if (is_file($backLogoAbs)) { $backLogoRel = 'uploads/company_back.png?v=' . filemtime($backLogoAbs); }
$backImageRel = null; $backImageAbs = __DIR__ . '/uploads/card_back_image.png';
if (is_file($backImageAbs)) { $backImageRel = 'uploads/card_back_image.png?v=' . filemtime($backImageAbs); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Carnés</title>
	<link rel="stylesheet" href="styles.css">
	<style>.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
	.draggable{cursor:move;user-select:none;padding:6px 8px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;margin-bottom:6px}
	.preview-card{width:220px;height:320px;background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.1);padding:10px}
	</style>
</head>
<body>
<header>
	<h1>Carnés</h1>
	<nav>
		<a href="index.php">Inicio</a>
		<a href="logout.php">Salir</a>
	</nav>
</header>
<main>
	<?php $msg = $_GET['msg'] ?? ''; if (!empty($message) || $msg): ?><div class="alert" style="background:#e7f7ee;border-color:#b4e2c6;color:#0a6d2a;"><?= htmlspecialchars($message ?: $msg) ?></div><?php endif; ?>
	<?php if (!empty($error)): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

	<?php if ($canManage): ?>
	<section class="card glass card--fluid" style="margin-bottom:20px;">
		<h2 class="title" style="margin-top:0">Configuración del carné</h2>
		<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
			<div style="display:flex;align-items:center;gap:8px">
				<strong>Distribución</strong>
				<button id="btn_open_layout" type="button" class="btn btn-outline" title="Ajustar posiciones">⚙️</button>
			</div>
		</div>
		<!-- Modal/Panel simple -->
		<div id="layout_panel" style="display:none;margin-top:10px;border:1px solid #e5e7eb;border-radius:12px;padding:12px">
			<div style="display:flex;gap:16px;flex-wrap:wrap">
				<div>
					<h3 style="margin:4px 0">Frontal</h3>
					<div id="front_zone" style="width:240px">
						<div class="draggable" data-key="photo">Foto</div>
						<div class="draggable" data-key="name">Nombre</div>
						<div class="draggable" data-key="dpi">DPI</div>
						<div class="draggable" data-key="service">Servicio</div>
						<div class="draggable" data-key="region">Región</div>
						<div class="draggable" data-key="code">Código</div>
						<div class="draggable" data-key="qr">QR</div>
					</div>
				</div>
				<div>
					<h3 style="margin:4px 0">Vista previa</h3>
					<div class="preview-card" id="front_preview"></div>
				</div>
				<div>
					<h3 style="margin:4px 0">Trasera</h3>
					<div id="back_zone" style="width:240px">
						<div class="draggable" data-key="notes">Notas</div>
						<div class="draggable" data-key="backImage">Imagen</div>
						<div class="draggable" data-key="backLogo">Logo</div>
						<div class="draggable" data-key="qr">QR</div>
					</div>
				</div>
				<div>
					<h3 style="margin:4px 0">Vista previa</h3>
					<div class="preview-card" id="back_preview"></div>
				</div>
			</div>
			<form method="post" style="margin-top:10px">
				<input type="hidden" name="action" value="save_layout">
				<input type="hidden" id="layout_json" name="layout_json">
				<button class="btn" type="submit">Guardar distribución</button>
			</form>
		</div>
		<script>
		const panel=document.getElementById('layout_panel');
		document.getElementById('btn_open_layout').addEventListener('click',()=>{panel.style.display=panel.style.display==='none'?'block':'none'});
		function makePreview(zoneId, previewId){
			const zone=document.getElementById(zoneId); const prev=document.getElementById(previewId);
			prev.innerHTML='';
			[...zone.querySelectorAll('.draggable')].forEach(el=>{
				const div=document.createElement('div'); div.className='draggable'; div.textContent=el.textContent; prev.appendChild(div);
			});
		}
		function serialize(){
			const front=[...document.querySelectorAll('#front_zone .draggable')].map(el=>el.dataset.key);
			const back=[...document.querySelectorAll('#back_zone .draggable')].map(el=>el.dataset.key);
			document.getElementById('layout_json').value=JSON.stringify({front,back});
			makePreview('front_zone','front_preview'); makePreview('back_zone','back_preview');
		}
		['front_zone','back_zone'].forEach(id=>{
			const z=document.getElementById(id);
			z.addEventListener('dragstart',e=>{ if(e.target.classList.contains('draggable')){ e.dataTransfer.setData('text/plain',e.target.dataset.key); e.target.classList.add('dragging'); } });
			z.addEventListener('dragend',e=>{ if(e.target.classList.contains('draggable')){ e.target.classList.remove('dragging'); serialize(); } });
			z.addEventListener('dragover',e=>{ e.preventDefault(); const dragging=z.querySelector('.dragging'); const after=[...z.querySelectorAll('.draggable:not(.dragging)')][0]; if(dragging){ z.appendChild(dragging); }});
			[...z.children].forEach(c=>{ c.setAttribute('draggable','true'); });
		});
		serialize();
		</script>
		<div class="grid">
			<div>
				<h3 style="margin:6px 0">Frontal</h3>
				<form method="post" enctype="multipart/form-data" style="margin-bottom:12px">
					<input type="hidden" name="action" value="save_front">
					<label>Título
						<input type="text" name="front_title" value="<?= htmlspecialchars($cardConfig['front_title']) ?>">
					</label>
					<label>Subtítulo
						<input type="text" name="front_subtitle" value="<?= htmlspecialchars($cardConfig['front_subtitle']) ?>">
					</label>
					<label>Logo (opcional)
						<input type="file" name="company_logo" accept="image/*">
					</label>
					<?php if ($companyLogoRel): ?><div style="margin-top:8px"><img src="<?= htmlspecialchars($companyLogoRel) ?>" alt="logo" style="height:48px"></div><?php endif; ?>
					<button class="btn" type="submit">Guardar frontal</button>
				</form>
			</div>
			<div>
				<h3 style="margin:6px 0">Trasera</h3>
				<form method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" value="save_back">
					<label>Notas/Recomendaciones
						<input type="text" name="back_notes" value="<?= htmlspecialchars($cardConfig['back_notes']) ?>">
					</label>
					<label>Logo trasero (opcional)
						<input type="file" name="back_logo" accept="image/*">
					</label>
					<label>Imagen trasera (opcional)
						<input type="file" name="back_image" accept="image/*">
					</label>
					<?php if ($backImageRel): ?>
						<div style="margin-top:8px"><img src="<?= htmlspecialchars($backImageRel) ?>" alt="img" style="height:80px"></div>
					<?php elseif ($backLogoRel): ?>
						<div style="margin-top:8px"><img src="<?= htmlspecialchars($backLogoRel) ?>" alt="logo" style="height:48px"></div>
					<?php endif; ?>
					<button class="btn" type="submit">Guardar trasera</button>
				</form>
			</div>
		</div>
	</section>
	<section class="card glass card--fluid" style="margin-bottom:20px;">
		<h2 class="title" style="margin-top:0">Nuevo carné</h2>
		<form method="post" enctype="multipart/form-data">
			<input type="hidden" name="action" value="create_employee">
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
						<button class="btn btn-outline" type="button" id="tab_upload" style="padding:6px 10px">Subir archivo</button>
						<button class="btn btn-outline" type="button" id="tab_camera" style="padding:6px 10px">Usar cámara</button>
					</div>
					<div id="upload_box" style="margin-top:8px">
						<input type="file" name="photo" accept="image/*">
					</div>
					<div id="camera_box" style="display:none;margin-top:8px">
						<video id="cam" autoplay playsinline style="width:240px;height:180px;border-radius:8px;background:#000"></video>
						<div>
							<button class="btn btn-outline" type="button" id="btn_capture">Tomar foto</button>
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
			<button class="btn" type="submit">Guardar</button>
		</form>
	</section>
	<?php endif; ?>

	<section class="card glass card--fluid" style="margin-bottom:20px;">
		<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px;flex-wrap:wrap">
			<h2 class="title" style="margin:0">Lista de carnés</h2>
			<input class="search-input" id="search" type="text" placeholder="Buscar por nombre, DPI o código">
		</div>
		<div class="table-scroll" style="width:100%">
		<table class="table-modern" id="emp_table" style="width:100%">
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
					<td data-label="ID"><?= (int)$e['id'] ?></td>
					<td data-label="Código"><?= htmlspecialchars($e['employee_code']) ?></td>
					<td data-label="Nombre"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></td>
					<td data-label="DPI"><?= htmlspecialchars($e['dpi']) ?></td>
					<td data-label="Servicio"><?= htmlspecialchars($e['service']) ?></td>
					<td data-label="Región"><?= htmlspecialchars($e['region']) ?></td>
					<td data-label="Vehículos"><?= $e['has_vehicle'] ? ((int)$e['vehicles_count'] . ' (' . htmlspecialchars($e['plate_number'] ?? '') . ')') : 'No' ?></td>
					<td data-label="Activo"><span class="badge" style="background:<?= $e['active'] ? '#dcfce7' : '#fee2e2' ?>;color:<?= $e['active'] ? '#166534' : '#991b1b' ?>;border:1px solid <?= $e['active'] ? '#86efac' : '#fecaca' ?>;"><?= $e['active'] ? 'Activo' : 'Inactivo' ?></span></td>
					<td data-label="Foto"><?php if ($e['photo_path']): ?><img src="<?= htmlspecialchars($e['photo_path']) ?>" alt="foto" style="height:40px;border-radius:6px"><?php endif; ?></td>
					<td class="actions" data-label="Acciones">
						<?php if ($canManage): ?>
							<a class="btn btn-outline" href="employee_edit.php?id=<?= (int)$e['id'] ?>">Editar</a>
							<a class="btn btn-outline" href="employee_delete.php?id=<?= (int)$e['id'] ?>">Eliminar</a>
							<a class="btn btn-outline" href="carnes.php?toggle=<?= (int)$e['id'] ?>"><?= $e['active'] ? 'Desactivar' : 'Activar' ?></a>
							<a class="btn" href="card.php?id=<?= (int)$e['id'] ?>&o=vertical" target="_blank">Carné V</a>
							<a class="btn" href="card.php?id=<?= (int)$e['id'] ?>&o=horizontal" target="_blank">Carné H</a>
							<a class="btn" href="card.php?id=<?= (int)$e['id'] ?>&o=horizontal&s=back" target="_blank">Carné T</a>
						<?php else: ?>
							<a class="btn" href="card.php?id=<?= (int)$e['id'] ?>&o=vertical" target="_blank">Ver V</a>
							<a class="btn" href="card.php?id=<?= (int)$e['id'] ?>&o=horizontal" target="_blank">Ver H</a>
							<a class="btn" href="card.php?id=<?= (int)$e['id'] ?>&o=horizontal&s=back" target="_blank">Ver T</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
	</section>
</main>
<script>
// UI opcional
const hasVehicle = document.getElementById('has_vehicle');
const vehicleFields = document.getElementById('vehicle_fields');
if (hasVehicle && vehicleFields) {
	function toggleVehicle(){ vehicleFields.style.display = hasVehicle.checked ? 'block' : 'none'; }
	hasVehicle.addEventListener('change', toggleVehicle);
	toggleVehicle();
}

const tabUpload = document.getElementById('tab_upload');
const tabCamera = document.getElementById('tab_camera');
const uploadBox = document.getElementById('upload_box');
const cameraBox = document.getElementById('camera_box');
let stream;
if (tabUpload && tabCamera && uploadBox && cameraBox) {
	function showUpload(){ uploadBox.style.display = 'block'; cameraBox.style.display = 'none'; if (stream) { stream.getTracks().forEach(t=>t.stop()); stream = null; } }
	async function showCamera(){ uploadBox.style.display = 'none'; cameraBox.style.display = 'block'; try { stream = await navigator.mediaDevices.getUserMedia({ video: true }); const camEl = document.getElementById('cam'); if (camEl) camEl.srcObject = stream; } catch(e){ alert('No se pudo acceder a la cámara: ' + e.message); showUpload(); } }
	tabUpload.addEventListener('click', showUpload);
	tabCamera.addEventListener('click', showCamera);
	showUpload();
}

const btnCapture = document.getElementById('btn_capture');
const canvas = document.getElementById('canvas');
const cam = document.getElementById('cam');
const photoData = document.getElementById('photo_data');
if (btnCapture && canvas && cam && photoData) {
	btnCapture.addEventListener('click', () => {
		const w = cam.videoWidth || 640; const h = cam.videoHeight || 480;
		canvas.width = w; canvas.height = h;
		const ctx = canvas.getContext('2d');
		ctx.drawImage(cam, 0, 0, w, h);
		photoData.value = canvas.toDataURL('image/jpeg', 0.9);
		alert('Foto capturada. Puedes guardar el formulario.');
	});
}

// Búsqueda
const search = document.getElementById('search');
const table = document.getElementById('emp_table');
if (search && table) {
	search.addEventListener('input', () => {
		const q = search.value.toLowerCase();
		table.querySelectorAll('tbody tr').forEach(tr => {
			const text = tr.innerText.toLowerCase();
			tr.style.display = text.includes(q) ? '' : 'none';
		});
	});
}
</script>
</body>
</html>


