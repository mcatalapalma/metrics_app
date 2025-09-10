<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/helpers.php';
include __DIR__.'/../includes/header.php';

// Repartidores para selector
$repart_opts = $pdo->query("SELECT id, nombre, dni FROM repartidores ORDER BY nombre")->fetchAll();

$message=null;
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion']??'')==='guardar'){
  $id_gd = intv($_POST['id_gd'] ?? 0);
  $id_glovo = trim((string)($_POST['id_glovo'] ?? ''));
  $pass = trim((string)($_POST['pass'] ?? ''));
  $repartidor = intv($_POST['repartidor'] ?? 0) ?: null;

  if($id_gd<=0 || $pass===''){
    $message=['type'=>'error','text'=>'id_gd y pass son obligatorios.'];
  }else{
    $sql="INSERT INTO cuentas_glovo (id_gd,id_glovo,pass,repartidor)
          VALUES (?,?,?,?)
          ON DUPLICATE KEY UPDATE id_glovo=VALUES(id_glovo), pass=VALUES(pass), repartidor=VALUES(repartidor)";
    $stmt=$pdo->prepare($sql); $stmt->execute([$id_gd,$id_glovo,$pass,$repartidor]);
    $message=['type'=>'ok','text'=>'Cuenta guardada/actualizada.'];
  }
}

$rows=$pdo->query("
  SELECT c.id, c.id_gd, c.id_glovo, c.email, r.nombre AS repartidor_nombre
  FROM cuentas_glovo c
  LEFT JOIN repartidores r ON r.id=c.repartidor
  ORDER BY c.id_gd ASC")->fetchAll();
?>
<div class="card">
  <h1>Cuentas Glovo</h1>
  <?php if($message): ?><div class="card <?php echo $message['type']==='ok'?'':'warn'; ?>"><?php echo h($message['text']); ?></div><?php endif; ?>
  <form method="post" class="grid">
    <input type="hidden" name="accion" value="guardar">
    <div><label>ID GD<br><input type="number" name="id_gd" min="1" required></label></div>
    <div><label>ID Glovo<br><input type="text" name="id_glovo" placeholder="Opcional"></label></div>
    <div><label>Contraseña<br><input type="text" name="pass" required></label></div>
    <div><label>Repartidor<br>
      <select name="repartidor">
        <option value="">-- Sin asignar --</option>
        <?php foreach($repart_opts as $r): ?>
          <option value="<?php echo $r['id']; ?>"><?php echo h($r['nombre']." (".$r['dni'].")"); ?></option>
        <?php endforeach; ?>
      </select>
    </label></div>
    <div><label>Email (auto)<br><input type="text" disabled id="email_preview" placeholder="gestdriver2025+IDGD@gmail.com"></label></div>
    <div style="align-self:end"><button class="btn" type="submit">Guardar / Actualizar</button></div>
  </form>

  <div style="overflow:auto;border-radius:12px;border:1px solid var(--b);margin-top:12px">
    <table>
      <thead><tr><th>ID GD</th><th>ID Glovo</th><th>Email</th><th>Repartidor</th></tr></thead>
      <tbody>
        <?php foreach($rows as $c): ?>
          <tr>
            <td><?php echo h($c['id_gd']); ?></td>
            <td><?php echo h($c['id_glovo']); ?></td>
            <td><?php echo h($c['email']); ?></td>
            <td><?php echo h($c['repartidor_nombre'] ?: '—'); ?></td>
          </tr>
        <?php endforeach; if(!$rows): ?>
          <tr><td colspan="4" class="muted">No hay cuentas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
const idInput=document.querySelector('input[name="id_gd"]'); const prev=document.getElementById('email_preview');
function updateEmail(){const v=parseInt((idInput?.value||'0'),10); prev && (prev.value=v>0?`gestdriver2025+${v}@gmail.com`:'');}
idInput && idInput.addEventListener('input',updateEmail); updateEmail();
</script>
<?php include __DIR__.'/../includes/footer.php'; ?>
