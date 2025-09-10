<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/helpers.php';
include __DIR__.'/../includes/header.php';

// Ensure settings table
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(64) PRIMARY KEY,
  `value` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Load current rule
$rule = $pdo->query("SELECT `value` FROM settings WHERE `key`='extra_rule'")->fetchColumn();
if ($rule === false) { $rule = 'p_extra'; } // default

$msg=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(($_POST['accion']??'')==='guardar'){
    $vehiculo=strtoupper(trim((string)($_POST['vehiculo']??'')));
    $contrato=intv($_POST['contrato']??0);
    $base=dec($_POST['base']??0);
    $p_min=intv($_POST['p_min']??0);
    $p_extra=intv($_POST['p_extra']??0);
    if($vehiculo===''||$contrato<=0){ $msg=['type'=>'error','text'=>'Vehículo y contrato son obligatorios.']; }
    else{
      $sql="INSERT INTO bases (vehiculo,contrato,base,p_min,p_extra)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE base=VALUES(base), p_min=VALUES(p_min), p_extra=VALUES(p_extra)";
      $pdo->prepare($sql)->execute([$vehiculo,$contrato,$base,$p_min,$p_extra]);
      $msg=['type'=>'ok','text'=>'Base guardada/actualizada.'];
    }
  } elseif(($_POST['accion']??'')==='borrar'){
    $vehiculo=strtoupper(trim((string)($_POST['vehiculo']??'')));
    $contrato=intv($_POST['contrato']??0);
    if($vehiculo!=='' && $contrato>0){
      $pdo->prepare("DELETE FROM bases WHERE vehiculo=? AND contrato=?")->execute([$vehiculo,$contrato]);
      $msg=['type'=>'ok','text'=>'Base eliminada.'];
    } else $msg=['type'=>'error','text'=>'Datos insuficientes para borrar.'];
  } elseif(($_POST['accion']??'')==='guardar_regla'){
    $new_rule = ($_POST['extra_rule'] ?? 'p_extra');
    if(!in_array($new_rule, ['p_extra','p_min'], true)) $new_rule = 'p_extra';
    $stmt = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('extra_rule', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    $stmt->execute([$new_rule]);
    $rule = $new_rule;
    $msg=['type'=>'ok','text'=>'Regla de extra orders actualizada.'];
  }
}
$rows=$pdo->query("SELECT vehiculo,contrato,base,p_min,p_extra FROM bases ORDER BY vehiculo, contrato DESC")->fetchAll();
?>
<div class="card">
  <h1>Ajustes • Bases</h1>
  <?php if($msg): ?><div class="card <?php echo $msg['type']==='ok'?'':'warn'; ?>"><?php echo h($msg['text']); ?></div><?php endif; ?>

  <div class="card">
    <h2>Regla para calcular "extra orders"</h2>
    <form method="post" class="row" style="gap:12px;align-items:center">
      <input type="hidden" name="accion" value="guardar_regla">
      <label><input type="radio" name="extra_rule" value="p_extra" <?php echo $rule==='p_extra'?'checked':''; ?>> Usar <code>bases.p_extra</code></label>
      <label><input type="radio" name="extra_rule" value="p_min" <?php echo $rule==='p_min'?'checked':''; ?>> Usar <code>bases.p_min</code></label>
      <button class="btn" type="submit">Guardar</button>
    </form>
    <p class="muted">extra orders = max(0, orders - umbral), donde el <em>umbral</em> es <code>p_extra</code> o <code>p_min</code> según esta opción.</p>
  </div>

  <form method="post" class="grid">
    <input type="hidden" name="accion" value="guardar">
    <div><label>Vehículo<br><select name="vehiculo" required>
      <option value="">-- Elige --</option>
      <?php foreach(['COCHE','MOTO','BICI','PETINETE'] as $v) echo "<option>$v</option>"; ?>
    </select></label></div>
    <div><label>Contrato (h)<br><input type="number" name="contrato" min="1" required></label></div>
    <div><label>Base (€)<br><input type="text" name="base" required placeholder="Ej: 707,5"></label></div>
    <div><label>P. mínimo<br><input type="number" name="p_min" min="0" required></label></div>
    <div><label>P. extra<br><input type="number" name="p_extra" min="0" required></label></div>
    <div style="align-self:end"><button class="btn">Guardar / Actualizar</button></div>
  </form>
  <div style="overflow:auto;border-radius:12px;border:1px solid var(--b);margin-top:12px">
    <table>
      <thead><tr><th>Vehículo</th><th>Contrato</th><th>Base</th><th>P. mín</th><th>P. extra</th><th></th></tr></thead>
      <tbody>
        <?php foreach($rows as $b): ?>
        <tr>
          <td><?php echo h($b['vehiculo']); ?></td>
          <td><?php echo h($b['contrato']); ?></td>
          <td><?php echo number_format((float)$b['base'],2,',','.'); ?></td>
          <td><?php echo h($b['p_min']); ?></td>
          <td><?php echo h($b['p_extra']); ?></td>
          <td>
            <form method="post" onsubmit="return confirm('¿Eliminar <?php echo h($b['vehiculo']); ?>/<?php echo h($b['contrato']); ?>?');">
              <input type="hidden" name="accion" value="borrar">
              <input type="hidden" name="vehiculo" value="<?php echo h($b['vehiculo']); ?>">
              <input type="hidden" name="contrato" value="<?php echo h($b['contrato']); ?>">
              <button class="btn-danger">Borrar</button>
            </form>
          </td>
        </tr>
        <?php endforeach; if(!$rows): ?>
        <tr><td colspan="6" class="muted">Aún no hay bases.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
