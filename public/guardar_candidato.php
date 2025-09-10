<?php
declare(strict_types=1);
$pageTitle='Candidato guardado';$activeMenu='repartidores';
require_once __DIR__.'/../config/db.php';include __DIR__.'/../includes/header.php';
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)($v ?? '')); } };
function save_doc($pdo, $docs_table, $rid, $type, $key){
  if(!isset($_FILES[$key]) || !is_uploaded_file($_FILES[$key]['tmp_name'])) return;
  $f=$_FILES[$key]; $name=$f['name']; $ext=strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $safeExt=in_array($ext,['pdf','jpg','jpeg','png'])?$ext:'dat';
  $uniq = $rid . '_' . preg_replace('/[^A-Z0-9]+/','_', strtoupper($type)) . '_' . bin2hex(random_bytes(4)) . '.' . $safeExt;
  $dest = __DIR__ . '/../storage/' . $uniq;
  if(move_uploaded_file($f['tmp_name'],$dest)){
    $stmt=$pdo->prepare("INSERT INTO {$docs_table} (repartidor_id, fecha, tipo, archivo, size_bytes) VALUES (:rid, NOW(), :t, :a, :s)");
    $stmt->execute([':rid'=>$rid,':t'=>$type,':a'=>$uniq,':s'=>$f['size']??null]);
  }
}

try{
  $pdo->beginTransaction();
  $docs_table = 'repartidor_documentos';
  try{ $pdo->query("SELECT 1 FROM repartidor_documentos LIMIT 1"); }
  catch(Throwable $e){ $docs_table = 'repartidore_documentos'; }

  $nombre=trim($_POST['nombre']??'');
  $apellido=trim($_POST['apellido']??'');
  $dni=trim($_POST['dni']??'');
  $ss=trim($_POST['ss']??'');
  $tel=trim($_POST['tel']??'');
  $email=trim($_POST['email']??'');
  $iban=trim($_POST['iban']??'');
  $vehiculo=trim($_POST['vehiculo']??'');
  $contrato=trim($_POST['contrato']??'');
  $city=trim($_POST['city']??'');

  if(!$nombre||!$apellido||!$dni||!$ss||!$tel||!$iban||!$vehiculo||!$contrato||!$city){
    throw new Exception('Faltan campos obligatorios');
  }

  $stmt=$pdo->prepare("INSERT INTO repartidores (nombre,apellido,dni,ss,tel,email,iban,vehiculo,contrato,city,estado,f_alta) 
                       VALUES (:nombre,:apellido,:dni,:ss,:tel,:email,:iban,:vehiculo,:contrato,:city,'CANDIDATO',NOW())");
  $stmt->execute([
    ':nombre'=>$nombre, ':apellido'=>$apellido, ':dni'=>$dni, ':ss'=>$ss, ':tel'=>$tel, ':email'=>$email,
    ':iban'=>$iban, ':vehiculo'=>$vehiculo, ':contrato'=>$contrato, ':city'=>$city
  ]);
  $rid=(int)$pdo->lastInsertId();

  save_doc($pdo,$docs_table,$rid,'DNI/NIE FRONT','dni_front');
  save_doc($pdo,$docs_table,$rid,'DNI/NIE BACK','dni_back');
  save_doc($pdo,$docs_table,$rid,'PERM FRONT','perm_front');
  save_doc($pdo,$docs_table,$rid,'PERM BACK','perm_back');
  save_doc($pdo,$docs_table,$rid,'CV','cv');

  $pdo->commit(); $ok=true;
}catch(Throwable $e){
  if($pdo->inTransaction()) $pdo->rollBack(); $ok=false; $error=$e->getMessage();
}
?>
<div class="card">
  <div class="card-body">
  <?php if(!empty($ok)):?>
    <h1 class="h6 mb-2"><i class="fa-solid fa-circle-check me-1 text-success"></i> Candidato registrado</h1>
    <p class="muted mb-3">Se ha guardado correctamente.</p>
    <a href="repartidores_ver.php?id=<?= (int)$rid ?>" class="btn btn-primary"><i class="fa-regular fa-eye me-1"></i> Ver ficha</a>
    <a href="repartidores.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
  <?php else:?>
    <h1 class="h6 mb-2"><i class="fa-solid fa-triangle-exclamation me-1 text-danger"></i> Error al guardar</h1>
    <p class="muted mb-3"><?= h($error??'Error') ?></p>
    <a href="repartidores_nuevo.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
  <?php endif;?>
  </div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
