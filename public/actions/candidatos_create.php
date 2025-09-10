<?php
// public/actions/candidatos_create.php
declare(strict_types=1);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/validators.php';

csrf_verify();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método no permitido'); }

// Detectar columna teléfono real en repartidores
$colsRep = $pdo->query("SHOW COLUMNS FROM repartidores")->fetchAll(PDO::FETCH_COLUMN);
$telCol = null;
foreach (['telefono','phone','telefono1','tel','movil','mobile'] as $cand) {
  if (in_array($cand, $colsRep, true)) { $telCol = $cand; break; }
}

// Datos base
$nombre   = v_text($_POST['nombre'] ?? '', 80);
$apellido = v_text($_POST['apellido'] ?? '', 120);
$email    = v_email($_POST['email'] ?? '');
$tel      = v_phone($_POST['telefono'] ?? '');
$city     = v_text($_POST['city'] ?? '', 60);
$estado   = $_POST['estado'] ?? 'CANDIDATO';

$err=[];
if (!$nombre) $err[]='Nombre requerido';
if ($email===null && isset($_POST['email']) && $_POST['email']!=='') $err[]='Email no válido';
if ($telCol && $tel===null && ($_POST['telefono'] ?? '')!=='') $err[]='Teléfono no válido';

if ($err) {
  http_response_code(400);
  echo 'Errores:<ul><li>'.implode('</li><li>', array_map('htmlspecialchars',$err)).'</li></ul><a href="javascript:history.back()">Volver</a>';
  exit;
}

// INSERT dinámico repartidores
$fields = ['nombre','apellido','email','city','estado'];
$values = [$nombre,$apellido,$email,$city,$estado];
if ($telCol) { $fields[]=$telCol; $values[]=$tel; }

$placeholders = implode(',', array_fill(0, count($fields), '?'));
$sql = "INSERT INTO repartidores (".implode(',', $fields).") VALUES ($placeholders)";
$st  = $pdo->prepare($sql);
$st->execute($values);
$repartidorId = (int)$pdo->lastInsertId();

// Guardar documentos si hay
$hasDocs = isset($_FILES['docs']) && is_array($_FILES['docs']['name']);
if ($hasDocs) {
  $baseDir = dirname(__DIR__,2).'/uploads/repartidores/'.$repartidorId;
  if (!is_dir($baseDir)) @mkdir($baseDir, 0775, true);

  // Descubrir esquema de repartidor_documentos si existe
  $hasDocsTable = false; $map = [];
  try {
    $colsDoc = $pdo->query("SHOW COLUMNS FROM repartidor_documentos")->fetchAll(PDO::FETCH_COLUMN);
    if ($colsDoc) {
      $hasDocsTable = true;
      // mapear columnas posibles
      $map['repartidor_id'] = in_array('repartidor_id',$colsDoc,true) ? 'repartidor_id' : (in_array('courier_id',$colsDoc,true)?'courier_id':null);
      $map['tipo']          = in_array('tipo',$colsDoc,true)          ? 'tipo'          : null;
      $map['file']          = in_array('file',$colsDoc,true)          ? 'file'          : (in_array('filename',$colsDoc,true)?'filename':(in_array('ruta',$colsDoc,true)?'ruta':(in_array('path',$colsDoc,true)?'path':null)));
      $map['original_name'] = in_array('original_name',$colsDoc,true) ? 'original_name' : null;
      $map['mime']          = in_array('mime',$colsDoc,true)          ? 'mime'          : (in_array('mime_type',$colsDoc,true)?'mime_type':null);
      $map['size']          = in_array('size',$colsDoc,true)          ? 'size'          : null;
      $map['created_at']    = in_array('created_at',$colsDoc,true)    ? 'created_at'    : (in_array('fecha_subida',$colsDoc,true)?'fecha_subida':null);
    }
  } catch (\Throwable $e) { /* tabla no existe o sin permisos */ }

  foreach ($_FILES['docs']['name'] as $tipo => $names) {
    if (!is_array($names)) continue;
    $count = count($names);
    for ($i=0; $i<$count; $i++) {
      $name = $names[$i];
      $tmp  = $_FILES['docs']['tmp_name'][$tipo][$i] ?? null;
      $errF = $_FILES['docs']['error'][$tipo][$i] ?? UPLOAD_ERR_NO_FILE;
      $size = (int)($_FILES['docs']['size'][$tipo][$i] ?? 0);
      if ($errF !== UPLOAD_ERR_OK || !$tmp) continue;
      if ($size > 5*1024*1024) continue; // 5MB max

      // Seguridad básica de extensión/MIME
      $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      $okExt = in_array($ext, ['pdf','jpg','jpeg','png']);
      if (!$okExt) continue;

      // Guardar con nombre único
      $safeName = preg_replace('/[^a-zA-Z0-9._-]/','_', $name);
      $target   = $baseDir.'/'.uniqid($tipo.'_').'.'.$ext;
      if (!move_uploaded_file($tmp, $target)) continue;

      // Insert en repartidor_documentos si existe
      if ($hasDocsTable && $map['repartidor_id'] && $map['tipo'] && $map['file']) {
        $cols = [$map['repartidor_id'], $map['tipo'], $map['file']];
        $vals = [$repartidorId, $tipo, basename($target)];

        if ($map['original_name']) { $cols[]=$map['original_name']; $vals[]=$safeName; }
        if ($map['mime'])          { $cols[]=$map['mime'];          $vals[]=(mime_content_type($target) ?: null); }
        if ($map['size'])          { $cols[]=$map['size'];          $vals[]=$size; }
        if ($map['created_at'])    { $cols[]=$map['created_at'];    $vals[]=(new DateTime())->format('Y-m-d H:i:s'); }

        $ph = implode(',', array_fill(0, count($cols), '?'));
        $pdo->prepare("INSERT INTO repartidor_documentos (".implode(',',$cols).") VALUES ($ph)")->execute($vals);
      }
    }
  }
}

// Redirigir a la lista
header('Location: /metricas/metrics_app/public/repartidores.php');
exit;
