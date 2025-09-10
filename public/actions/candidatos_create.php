<?php
// public/actions/candidatos_create.php
declare(strict_types=1);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/validators.php';

csrf_verify();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método no permitido'); }

// === Campos exactamente como en tu candidato_nuevo.php ===
$nombre           = v_text($_POST['nombre'] ?? '', 80);
$apellidos        = v_text($_POST['apellidos'] ?? '', 120);
$email            = v_email($_POST['email'] ?? '');
$telefono_raw     = $_POST['telefono'] ?? '';
$telefono         = v_phone($telefono_raw);
$dni              = v_text($_POST['dni'] ?? '', 30);
$fecha_nacimiento = v_text($_POST['fecha_nacimiento'] ?? '', 20);
$ciudad           = v_text($_POST['ciudad'] ?? '', 60);
$estado           = $_POST['estado'] ?? 'CANDIDATO';

$experiencia_anios = (int)($_POST['experiencia_anios'] ?? 0);
$vehiculo          = $_POST['vehiculo'] ?? 'no';
$licencia_tipo     = v_text($_POST['licencia_tipo'] ?? '', 30);
$disponibilidad    = isset($_POST['disponibilidad']) ? (array)$_POST['disponibilidad'] : [];
$observaciones     = v_text($_POST['observaciones'] ?? '', 255);

// Validación esencial (como el paso 1 original)
$err = [];
if (!$nombre)        $err[]='Nombre requerido';
if (!$apellidos)     $err[]='Apellidos requeridos';
if ($email===null)   $err[]='Email no válido';
if ($telefono===null)$err[]='Teléfono no válido';
if (!$dni)           $err[]='DNI/NIE requerido';

if ($err) {
  http_response_code(400);
  echo 'Errores:<ul><li>'.implode('</li><li>', array_map('htmlspecialchars',$err)).'</li></ul><a href="javascript:history.back()">Volver</a>';
  exit;
}

// === 1) Guardar carpeta candidatos + datos.json (igual que tu script) ===
$baseUpload = dirname(__DIR__,2) . '/uploads/candidatos';
if (!is_dir($baseUpload)) { @mkdir($baseUpload, 0775, true); }
$folder = $baseUpload . '/' . date('Ymd') . '_' . bin2hex(random_bytes(4));
@mkdir($folder, 0775, true);

// Reglas de ficheros (idénticas)
$rules = [
  'cv'           => ['types' => ['application/pdf'],                     'name' => 'CV',           'max' => 10*1024*1024],
  'dni_frente'   => ['types' => ['image/jpeg','image/png','application/pdf'], 'name' => 'DNI_frente', 'max' =>  8*1024*1024],
  'antecedentes' => ['types' => ['application/pdf'],                     'name' => 'Antecedentes', 'max' => 10*1024*1024],
];
$up_ok = true; $up_errors = [];

foreach ($rules as $key => $r) {
  if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) continue;
  if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) { $up_ok=false; $up_errors[] = "Error al subir $key"; continue; }
  if ($_FILES[$key]['size'] > $r['max']) { $up_ok=false; $up_errors[] = "$key supera el tamaño máximo"; continue; }
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime  = finfo_file($finfo, $_FILES[$key]['tmp_name']); finfo_close($finfo);
  if (!in_array($mime, $r['types'], true)) { $up_ok=false; $up_errors[] = "$key tipo no permitido ($mime)"; continue; }

  $ext = ($mime === 'image/png') ? '.png' : (($mime === 'image/jpeg') ? '.jpg' : '.pdf');
  $target = $folder . '/' . $r['name'] . $ext;
  if (!move_uploaded_file($_FILES[$key]['tmp_name'], $target)) { $up_ok=false; $up_errors[] = "No se pudo guardar $key"; }
}

// Guardar datos como JSON (similar a tu script original)
$data = [
  'personales' => [
    'nombre' => $nombre, 'apellidos' => $apellidos, 'email' => $email, 'telefono' => $telefono,
    'dni' => $dni, 'fecha_nacimiento' => $fecha_nacimiento, 'ciudad' => $ciudad,
  ],
  'profesionales' => [
    'experiencia_anios' => $experiencia_anios, 'vehiculo' => $vehiculo, 'licencia_tipo' => $licencia_tipo,
    'disponibilidad' => $disponibilidad, 'observaciones' => $observaciones,
  ],
  'creado_en' => date('c'),
];
@file_put_contents($folder . '/datos.json', json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

// === 2) Intentar crear también el repartidor en BD (estado CANDIDATO) ===
// Detectar columna teléfono en 'repartidores'
$colsRep = $pdo->query("SHOW COLUMNS FROM repartidores")->fetchAll(PDO::FETCH_COLUMN);
$telCol = null;
foreach (['telefono','phone','telefono1','tel','movil','mobile'] as $cand) {
  if (in_array($cand, $colsRep, true)) { $telCol = $cand; break; }
}

// Construir INSERT dinámico (solo columnas que existan)
$fields = ['nombre','apellido','email','city','estado'];
$values = [$nombre, $apellidos, $email, $ciudad, 'CANDIDATO'];
if ($telCol) { $fields[] = $telCol; $values[] = $telefono; }
$placeholders = implode(',', array_fill(0, count($fields), '?'));
$pdo->prepare("INSERT INTO repartidores (".implode(',', $fields).") VALUES ($placeholders)")->execute($values);
$repartidorId = (int)$pdo->lastInsertId();

// === 3) Si existe repartidor_documentos, registrar archivos allí también (flexible con nombres de columnas) ===
$hasDocsTable = false; $map = [];
try {
  $colsDoc = $pdo->query("SHOW COLUMNS FROM repartidor_documentos")->fetchAll(PDO::FETCH_COLUMN);
  if ($colsDoc) {
    $hasDocsTable = true;
    $map['repartidor_id'] = in_array('repartidor_id',$colsDoc,true) ? 'repartidor_id' : (in_array('courier_id',$colsDoc,true)?'courier_id':null);
    $map['tipo']          = in_array('tipo',$colsDoc,true)          ? 'tipo'          : null;
    $map['file']          = in_array('file',$colsDoc,true)          ? 'file'          : (in_array('filename',$colsDoc,true)?'filename':(in_array('ruta',$colsDoc,true)?'ruta':(in_array('path',$colsDoc,true)?'path':null)));
    $map['original_name'] = in_array('original_name',$colsDoc,true) ? 'original_name' : null;
    $map['mime']          = in_array('mime',$colsDoc,true)          ? 'mime'          : (in_array('mime_type',$colsDoc,true)?'mime_type':null);
    $map['size']          = in_array('size',$colsDoc,true)          ? 'size'          : null;
    $map['created_at']    = in_array('created_at',$colsDoc,true)    ? 'created_at'    : (in_array('fecha_subida',$colsDoc,true)?'fecha_subida':null);
  }
} catch (\Throwable $e) { /* tabla no existe */ }

// Mapear archivos guardados en $folder (si existen) a tipos
$docFiles = [
  'CV'           => glob($folder . '/CV.*'),
  'DNI_frente'   => glob($folder . '/DNI_frente.*'),
  'Antecedentes' => glob($folder . '/Antecedentes.*'),
];

if ($hasDocsTable && $map['repartidor_id'] && $map['tipo'] && $map['file']) {
  foreach ($docFiles as $tipo => $arr) {
    foreach ($arr as $fullpath) {
      $basename = basename($fullpath);
      $cols = [$map['repartidor_id'], $map['tipo'], $map['file']];
      $vals = [$repartidorId, $tipo, $basename];
      if ($map['original_name']) { $cols[]=$map['original_name']; $vals[]=$basename; }
      if ($map['mime'])          { $cols[]=$map['mime'];          $vals[]=(mime_content_type($fullpath) ?: null); }
      if ($map['size'])          { $cols[]=$map['size'];          $vals[]=@filesize($fullpath) ?: null; }
      if ($map['created_at'])    { $cols[]=$map['created_at'];    $vals[]=(new DateTime())->format('Y-m-d H:i:s'); }
      $ph = implode(',', array_fill(0, count($cols), '?'));
      $pdo->prepare("INSERT INTO repartidor_documentos (".implode(',',$cols).") VALUES ($ph)")->execute($vals);
    }
  }
}

// Redirigir a la lista (modal se cierra al navegar)
header('Location: /metricas/metrics_app/public/repartidores.php');
exit;
