<?php
// public/actions/candidatos_create.php
declare(strict_types=1);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/validators.php';

csrf_verify();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método no permitido'); }

// === Paso 1: personales ===
$nombre           = v_text($_POST['nombre'] ?? '', 80);
$apellidos        = v_text($_POST['apellidos'] ?? '', 120);
$email            = v_email($_POST['email'] ?? '');
$telefono_raw     = $_POST['telefono'] ?? '';
$telefono         = v_phone($telefono_raw);
$dni              = v_text($_POST['dni'] ?? '', 30);
$fecha_nacimiento = v_text($_POST['fecha_nacimiento'] ?? '', 20);
$ciudad           = v_text($_POST['ciudad'] ?? '', 10); // PAL/BCN/INC

// === Paso 2: laborables ===
$contrato      = v_text($_POST['contrato'] ?? '', 3); // 40/30/20 o vacío
$vehiculo      = ($_POST['vehiculo'] ?? 'no') === 'si' ? 'si' : 'no';
$vehiculo_tipo = $vehiculo === 'si' ? v_text($_POST['vehiculo_tipo'] ?? '', 20) : null;

// Validación esencial del paso 1
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

// === 1) Guardar carpeta y JSON (trazabilidad) ===
$baseUpload = dirname(__DIR__,2) . '/uploads/candidatos';
if (!is_dir($baseUpload)) { @mkdir($baseUpload, 0775, true); }
$folder = $baseUpload . '/' . date('Ymd') . '_' . bin2hex(random_bytes(4));
@mkdir($folder, 0775, true);

// === 2) Guardar documentos ===
// Reglas: DNI/Permiso JPG/PNG/PDF (8MB); CV PDF (10MB)
function save_upload($key, array $rules, string $prefix, string $folder, array &$errors): ?string {
  if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) return null;
  if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) { $errors[] = "Error al subir $key"; return null; }
  if ($_FILES[$key]['size'] > $rules['max']) { $errors[] = "$key supera el tamaño máximo"; return null; }
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime  = finfo_file($finfo, $_FILES[$key]['tmp_name']); finfo_close($finfo);
  if (!in_array($mime, $rules['types'], true)) { $errors[] = "$key tipo no permitido ($mime)"; return null; }
  $ext = ($mime === 'image/png') ? '.png' : (($mime === 'image/jpeg') ? '.jpg' : '.pdf');
  $target = $folder . '/' . $prefix . $ext;
  if (!move_uploaded_file($_FILES[$key]['tmp_name'], $target)) { $errors[] = "No se pudo guardar $key"; return null; }
  return basename($target);
}

$errors = [];
$docMap = [
  'dni_front'    => ['prefix' => 'DNI_front',    'types' => ['image/jpeg','image/png','application/pdf'], 'max' => 8*1024*1024],
  'dni_back'     => ['prefix' => 'DNI_back',     'types' => ['image/jpeg','image/png','application/pdf'], 'max' => 8*1024*1024],
  'permiso_front'=> ['prefix' => 'Permiso_front','types' => ['image/jpeg','image/png','application/pdf'], 'max' => 8*1024*1024],
  'permiso_back' => ['prefix' => 'Permiso_back', 'types' => ['image/jpeg','image/png','application/pdf'], 'max' => 8*1024*1024],
  'cv'           => ['prefix' => 'CV',           'types' => ['application/pdf'],                            'max' =>10*1024*1024],
];

$savedFiles = [];
foreach ($docMap as $key=>$r) {
  $fn = save_upload($key, ['types'=>$r['types'],'max'=>$r['max']], $r['prefix'], $folder, $errors);
  if ($fn) $savedFiles[$key] = $fn;
}

// Guardar datos como JSON
$data = [
  'personales' => [
    'nombre' => $nombre, 'apellidos' => $apellidos, 'email' => $email, 'telefono' => $telefono,
    'dni' => $dni, 'fecha_nacimiento' => $fecha_nacimiento, 'ciudad' => $ciudad,
  ],
  'laborables' => [
    'contrato' => $contrato, 'vehiculo' => $vehiculo, 'vehiculo_tipo' => $vehiculo_tipo,
  ],
  'archivos'   => $savedFiles,
  'creado_en'  => date('c'),
];
@file_put_contents($folder . '/datos.json', json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

// === 3) Insertar también en BD si existen columnas compatibles ===
// Detectar columna teléfono real en repartidores
$colsRep = $pdo->query("SHOW COLUMNS FROM repartidores")->fetchAll(PDO::FETCH_COLUMN);
$telCol = null;
foreach (['telefono','phone','telefono1','tel','movil','mobile'] as $cand) {
  if (in_array($cand, $colsRep, true)) { $telCol = $cand; break; }
}

$fields = ['nombre','apellido','email','city','estado'];
$values = [$nombre, $apellidos, $email, $ciudad, 'CANDIDATO']; // estado fijo CANDIDATO
if ($telCol) { $fields[]=$telCol; $values[]=$telefono; }

$ph = implode(',', array_fill(0, count($fields), '?'));
$pdo->prepare("INSERT INTO repartidores (".implode(',', $fields).") VALUES ($ph)")->execute($values);
$repartidorId = (int)$pdo->lastInsertId();

// Registrar documentos en repartidor_documentos si existe
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
} catch (\Throwable $e) {}

if ($hasDocsTable && $map['repartidor_id'] && $map['tipo'] && $map['file']) {
  // Mapeo tipo -> archivo
  $typeToFiles = [
    'DNI_FRONT'           => $savedFiles['dni_front']    ?? null,
    'DNI_BACK'            => $savedFiles['dni_back']     ?? null,
    'PERMISO_CONDUCIR_FRONT' => $savedFiles['permiso_front'] ?? null,
    'PERMISO_CONDUCIR_BACK'  => $savedFiles['permiso_back']  ?? null,
    'CV'                  => $savedFiles['cv']           ?? null,
  ];
  foreach ($typeToFiles as $tipo => $file) {
    if (!$file) continue;
    $fullpath = $folder . '/' . $file;
    $cols = [$map['repartidor_id'], $map['tipo'], $map['file']];
    $vals = [$repartidorId, $tipo, $file];
    if ($map['original_name']) { $cols[]=$map['original_name']; $vals[]=$file; }
    if ($map['mime'])          { $cols[]=$map['mime'];          $vals[]=(mime_content_type($fullpath) ?: null); }
    if ($map['size'])          { $cols[]=$map['size'];          $vals[]=@filesize($fullpath) ?: null; }
    if ($map['created_at'])    { $cols[]=$map['created_at'];    $vals[]=(new DateTime())->format('Y-m-d H:i:s'); }
    $pdo->prepare("INSERT INTO repartidor_documentos (".implode(',',$cols).") VALUES (".implode(',', array_fill(0,count($cols),'?')).")")->execute($vals);
  }
}

// OK → volver a la lista
header('Location: /metricas/metrics_app/public/repartidores.php');
exit;
