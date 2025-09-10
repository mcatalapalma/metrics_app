<?php
declare(strict_types=1);
session_start();

// CSRF simple
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
function csrf_field(){ return '<input type="hidden" name="_token" value="'.htmlspecialchars($_SESSION['csrf'], ENT_QUOTES).'">'; }
function csrf_check(){
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = $_POST['_token'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $t)) { http_response_code(400); die('CSRF token inválido'); }
  }
}

$step = isset($_GET['step']) ? max(1, min(3, (int)$_GET['step'])) : 1;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $step = (int)($_POST['step'] ?? 1);
  // Guarda datos del paso en sesión
  if ($step === 1) {
    $_SESSION['candidato']['personales'] = [
      'nombre' => trim($_POST['nombre'] ?? ''),
      'apellidos' => trim($_POST['apellidos'] ?? ''),
      'email' => trim($_POST['email'] ?? ''),
      'telefono' => trim($_POST['telefono'] ?? ''),
      'dni' => trim($_POST['dni'] ?? ''),
      'fecha_nacimiento' => trim($_POST['fecha_nacimiento'] ?? ''),
      'ciudad' => trim($_POST['ciudad'] ?? ''),
    ];
    $step = 2;
  } elseif ($step === 2) {
    $_SESSION['candidato']['profesionales'] = [
      'experiencia_anios' => (int)($_POST['experiencia_anios'] ?? 0),
      'vehiculo' => $_POST['vehiculo'] ?? 'no',
      'licencia_tipo' => trim($_POST['licencia_tipo'] ?? ''),
      'disponibilidad' => $_POST['disponibilidad'] ?? [],
      'observaciones' => trim($_POST['observaciones'] ?? ''),
    ];
    $step = 3;
  } elseif ($step === 3) {
    // Subida de documentos
    $baseUpload = __DIR__ . '/../uploads/candidatos';
    if (!is_dir($baseUpload)) { @mkdir($baseUpload, 0775, true); }
    $folder = $baseUpload . '/' . date('Ymd') . '_' . bin2hex(random_bytes(4));
    @mkdir($folder, 0775, true);

    $ok = true; $errors = [];
    $files = [
      'cv' => ['types' => ['application/pdf'], 'name' => 'CV.pdf', 'max' => 10*1024*1024],
      'dni_frente' => ['types' => ['image/jpeg','image/png','application/pdf'], 'name' => 'DNI_frente', 'max' => 8*1024*1024],
      'antecedentes' => ['types' => ['application/pdf'], 'name' => 'Antecedentes.pdf', 'max' => 10*1024*1024],
    ];

    foreach ($files as $key => $rules) {
      if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) { continue; } // opcional
      if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) { $ok=false; $errors[] = "Error al subir $key"; continue; }
      if ($_FILES[$key]['size'] > $rules['max']) { $ok=false; $errors[] = "$key supera el tamaño máximo"; continue; }
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime = finfo_file($finfo, $_FILES[$key]['tmp_name']);
      finfo_close($finfo);
      if (!in_array($mime, $rules['types'], true)) { $ok=false; $errors[] = "$key tipo no permitido ($mime)"; continue; }
      $ext = ($mime === 'image/png') ? '.png' : (($mime === 'image/jpeg') ? '.jpg' : '.pdf');
      $target = $folder . '/' . $rules['name'] . $ext;
      if (!move_uploaded_file($_FILES[$key]['tmp_name'], $target)) { $ok=false; $errors[] = "No se pudo guardar $key"; }
    }

    // Guarda JSON con datos
    $data = $_SESSION['candidato'] ?? [];
    $data['creado_en'] = date('c');
    @file_put_contents($folder . '/datos.json', json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

    if ($ok) {
      $success = true;
      // Limpia para nuevo alta
      unset($_SESSION['candidato']);
      $step = 4; // pantalla final
    } else {
      $step = 3;
    }
  }
}

// Utilidades UI
$val = function($path, $default=''){
  $parts = explode('.', $path);
  $cur = $_SESSION['candidato'] ?? [];
  foreach ($parts as $p) {
    if (!isset($cur[$p])) return $default;
    $cur = $cur[$p];
  }
  return is_array($cur) ? $cur : htmlspecialchars((string)$cur, ENT_QUOTES);
};

$pageTitle = 'Nuevo Candidato';
$activeMenu = 'repartidores';
$currentUserName = $_SESSION['user_name'] ?? 'Usuario';
include __DIR__.'/../includes/header.php';
?>

<div class="card">
  <h1>Nuevo Candidato</h1>
  <p class="muted">Completa el formulario en 3 pasos.</p>
</div>

<?php if (($step ?? 1) === 1): ?>
<form class="card" action="?step=1" method="post" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="step" value="1">
  <h2>1) Datos personales</h2>
  <div class="grid">
    <div class="row" style="flex-direction:column;">
      <label>Nombre</label>
      <input name="nombre" required value="<?= $val('personales.nombre') ?>">
    </div>
    <div class="row" style="flex-direction:column;">
      <label>Apellidos</label>
      <input name="apellidos" required value="<?= $val('personales.apellidos') ?>">
    </div>
    <div class="row" style="flex-direction:column;">
      <label>Email</label>
      <input type="email" name="email" required value="<?= $val('personales.email') ?>">
    </div>
    <div class="row" style="flex-direction:column;">
      <label>Teléfono</label>
      <input name="telefono" required value="<?= $val('personales.telefono') ?>">
    </div>
    <div class="row" style="flex-direction:column;">
      <label>DNI/NIE</label>
      <input name="dni" required value="<?= $val('personales.dni') ?>">
    </div>
    <div class="row" style="flex-direction:column;">
      <label>Fecha de nacimiento</label>
      <input type="date" name="fecha_nacimiento" value="<?= $val('personales.fecha_nacimiento') ?>">
    </div>
    <div class="row" style="flex-direction:column;">
      <label>Ciudad</label>
      <input name="ciudad" value="<?= $val('personales.ciudad') ?>">
    </div>
  </div>
  <div class="row" style="justify-content:flex-end; gap:8px; margin-top:10px;">
    <a class="btn-ghost" href="repartidores.php">Cancelar</a>
    <button class="btn">Siguiente</button>
  </div>
</form>
<?php elseif ($step === 2): ?>
<form class="card" action="?step=2" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="step" value="2">
  <h2>2) Datos profesionales</h2>
  <div class="grid">
    <div class="row" style="flex-direction:column;">
      <label>Años de experiencia</label>
      <input type="number" min="0" name="experiencia_anios" value="<?= $val('profesionales.experiencia_anios', 0) ?>">
    </div>
    <div class="row" style="flex-direction:column;">
      <label>Vehículo propio</label>
      <select name="vehiculo">
        <?php $veh = $val('profesionales.vehiculo','no'); ?>
        <option value="no" <?= ($veh==='no'?'selected':'') ?>>No</option>
        <option value="si" <?= ($veh==='si'?'selected':'') ?>>Sí</option>
      </select>
    </div>
    <div class="row" style="flex-direction:column;">
      <label>Tipo de licencia</label>
      <input name="licencia_tipo" placeholder="AM / A1 / B / ..." value="<?= $val('profesionales.licencia_tipo') ?>">
    </div>
    <div class="row" style="flex-direction:column;">
      <label>Disponibilidad</label>
      <?php $disp = $val('profesionales.disponibilidad', []); ?>
      <div class="row">
        <label><input type="checkbox" name="disponibilidad[]" value="mediodia" <?= in_array('mediodia',(array)$disp)?'checked':''; ?>> Mediodía</label>
        <label><input type="checkbox" name="disponibilidad[]" value="noche" <?= in_array('noche',(array)$disp)?'checked':''; ?>> Noche</label>
        <label><input type="checkbox" name="disponibilidad[]" value="finsemana" <?= in_array('finsemana',(array)$disp)?'checked':''; ?>> Fin de semana</label>
      </div>
    </div>
    <div class="row" style="flex-direction:column; grid-column:1 / -1;">
      <label>Observaciones</label>
      <textarea name="observaciones" rows="4" style="width:100%; border-radius:12px; border:1px solid var(--border); padding:10px;"><?= $val('profesionales.observaciones') ?></textarea>
    </div>
  </div>
  <div class="row" style="justify-content:space-between; gap:8px; margin-top:10px;">
    <a class="btn-ghost" href="?step=1">← Volver</a>
    <div>
      <a class="btn-ghost" href="repartidores.php">Cancelar</a>
      <button class="btn">Siguiente</button>
    </div>
  </div>
</form>
<?php elseif ($step === 3): ?>
<form class="card" action="?step=3" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="step" value="3">
  <h2>3) Documentos</h2>
  <p class="muted">Formatos permitidos: PDF para CV y antecedentes; JPG/PNG/PDF para DNI.</p>
  <div class="grid">
    <div class="row" style="flex-direction:column;">
      <label>CV (PDF)</label>
      <input type="file" name="cv" accept="application/pdf">
    </div>
    <div class="row" style="flex-direction:column;">
      <label>DNI/NIE - frente (JPG/PNG/PDF)</label>
      <input type="file" name="dni_frente" accept="image/*,application/pdf">
    </div>
    <div class="row" style="flex-direction:column;">
      <label>Certificado de antecedentes (PDF)</label>
      <input type="file" name="antecedentes" accept="application/pdf">
    </div>
  </div>
  <?php if (!empty($errors)): ?>
    <div class="card" style="border-color: var(--danger);">
      <strong>Errores:</strong>
      <ul>
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <div class="row" style="justify-content:space-between; gap:8px; margin-top:10px;">
    <a class="btn-ghost" href="?step=2">← Volver</a>
    <div>
      <a class="btn-ghost" href="repartidores.php">Cancelar</a>
      <button class="btn">Finalizar alta</button>
    </div>
  </div>
</form>
<?php else: ?>
<div class="card">
  <h2>✅ Candidato registrado</h2>
  <p class="muted">Los datos se han guardado y los documentos (si se adjuntaron) están en <code>/uploads/candidatos/</code>.</p>
  <div class="row" style="gap:8px;">
    <a class="btn" href="repartidores.php">Volver a Repartidores</a>
    <a class="btn-ghost" href="candidato_nuevo.php">Crear otro</a>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>
