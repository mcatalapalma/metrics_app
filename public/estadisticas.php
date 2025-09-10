<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/helpers.php';
include __DIR__.'/../includes/header.php';

// Filtro mes/año
$month = isset($_GET['month']) ? max(1, min(12, (int)$_GET['month'])) : (int)date('m');
$year  = isset($_GET['year'])  ? max(2000, min(2100, (int)$_GET['year'])) : (int)date('Y');
$from  = sprintf('%04d-%02d-01', $year, $month);
$to    = date('Y-m-d', strtotime("$from +1 month"));

// Regla para extra (settings.extra_rule: p_extra | p_min)
$rule = $pdo->query("SELECT `value` FROM settings WHERE `key`='extra_rule'")->fetchColumn();
if ($rule === false) { $rule = 'p_extra'; }
$col = ($rule === 'p_min') ? 'p_min' : 'p_extra';

// Consulta agregada por id_gd / repartidor, respetando fecha de alta
$sql = "
SELECT 
  cg.id_gd AS user_id,
  r.nombre,
  SUM(COALESCE(cm.orders,0)) AS total_orders,
  SUM(COALESCE(GREATEST(cm.orders - COALESCE(b.$col,0), 0),0)) AS extra_orders,
  SUM(COALESCE(cm.tips,0)) AS total_tips,
  SUM(COALESCE(cm.km,0)) AS total_km,
  SUM(COALESCE(cm.hours,0)) AS total_hours,
  SUM(COALESCE(cm.reassignments,0)) AS total_reassignments,
  AVG(cm.avg_delivery_time_min) AS avg_delivery_time
FROM cuentas_glovo cg
JOIN repartidores r ON r.id = cg.repartidor
LEFT JOIN bases b ON b.vehiculo = r.vehiculo AND b.contrato = r.contrato
LEFT JOIN courier_metrics cm
  ON cm.user_id = cg.id_gd
  AND cm.metric_date >= (CASE WHEN r.f_alta IS NOT NULL AND r.f_alta > :from1 THEN r.f_alta ELSE :from2 END)
  AND cm.metric_date < :to
GROUP BY cg.id_gd, r.nombre
ORDER BY cg.id_gd ASC;
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':from1'=>$from, ':from2'=>$from, ':to'=>$to]);
$rows = $stmt->fetchAll();

// Totales generales
$grand = [
  'orders'=>0, 'extra'=>0, 'tips'=>0.0, 'km'=>0.0, 'hours'=>0.0, 'reassignments'=>0
];
foreach ($rows as $r) {
  $grand['orders'] += (int)$r['total_orders'];
  $grand['extra']  += (int)$r['extra_orders'];
  $grand['tips']   += (float)$r['total_tips'];
  $grand['km']     += (float)$r['total_km'];
  $grand['hours']  += (float)$r['total_hours'];
  $grand['reassignments'] += (int)$r['total_reassignments'];
}

// Export CSV si se solicita
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=estadisticas_'.$year.'-'.$month.'.csv');
  $out = fopen('php://output','w');
  fputcsv($out, ['id_gd','repartidor','total_orders','extra_orders','extra_value_eur','total_tips_eur','km_total','km_value_eur','total_reassignments','avg_delivery_time_min','total_hours'], ';');
  foreach ($rows as $r) {
    $extra_value = (int)$r['extra_orders'] * 3.5;
    $km_value = (float)$r['total_km'] * 0.03;
    fputcsv($out, [
      $r['user_id'],
      $r['nombre'],
      (int)$r['total_orders'],
      (int)$r['extra_orders'],
      number_format($extra_value,2,'.',''),
      number_format((float)$r['total_tips'],2,'.',''),
      number_format((float)$r['total_km'],2,'.',''),
      number_format($km_value,2,'.',''),
      (int)$r['total_reassignments'],
      $r['avg_delivery_time'] !== null ? number_format((float)$r['avg_delivery_time'],2,'.','') : '0.00',
      number_format((float)$r['total_hours'],2,'.',''),
    ], ';');
  }
  fclose($out);
  exit;
}
?>
<style>
.right{text-align:right}
</style>

<div class="card">
  <h1>Estadísticas mensuales</h1>
  <form method="get" class="row" style="align-items:center;gap:8px">
    <select name="month">
      <?php for($m=1;$m<=12;$m++){ $sel=$m===$month?'selected':''; echo "<option value='$m' $sel>".date('m',strtotime("2020-$m-01"))."</option>"; } ?>
    </select>
    <input type="number" name="year" value="<?php echo $year; ?>" min="2000" max="2100" style="width:110px">
    <button class="btn" type="submit">Filtrar</button>
    <a class="btn" href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&export=csv">Exportar CSV</a>
  </form>
  <p class="muted">Regla extra: <code><?php echo h($rule); ?></code> • Periodo: <?php echo date('d/m/Y', strtotime($from)); ?> a <?php echo date('d/m/Y', strtotime("$to -1 day")); ?> • Los datos previos a la <em>fecha de alta</em> de cada repartidor no se cuentan.</p>

  <div style="overflow:auto;border:1px solid var(--b);border-radius:12px">
    <table>
      <thead>
        <tr>
          <th>id_gd</th>
          <th>Repartidor</th>
          <th class="right">total.orders</th>
          <th class="right">total.extraorders × 3,50€</th>
          <th class="right">total.tips</th>
          <th class="right">total.km × 0,03€</th>
          <th class="right">total.reassignments</th>
          <th class="right">avg delivery (min)</th>
          <th class="right">total.hours</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): 
          $extra_count = (int)$r['extra_orders'];
          $extra_value = $extra_count * 3.5;
          $km_value = (float)$r['total_km'] * 0.03;
        ?>
        <tr>
          <td><?php echo (int)$r['user_id']; ?></td>
          <td><?php echo h($r['nombre']); ?></td>
          <td class="right"><?php echo number_format((int)$r['total_orders'], 0, ',', '.'); ?></td>
          <td class="right"><?php echo number_format($extra_count, 0, ',', '.'); ?> × 3,50 € = <?php echo number_format($extra_value, 2, ',', '.'); ?> €</td>
          <td class="right"><?php echo number_format((float)$r['total_tips'], 2, ',', '.'); ?> €</td>
          <td class="right"><?php echo number_format((float)$r['total_km'], 2, ',', '.'); ?> × 0,03 € = <?php echo number_format($km_value, 2, ',', '.'); ?> €</td>
          <td class="right"><?php echo number_format((int)$r['total_reassignments'], 0, ',', '.'); ?></td>
          <td class="right"><?php echo $r['avg_delivery_time'] !== null ? number_format((float)$r['avg_delivery_time'], 2, ',', '.') : '0,00'; ?></td>
          <td class="right"><?php echo number_format((float)$r['total_hours'], 2, ',', '.'); ?></td>
        </tr>
        <?php endforeach; if (!$rows): ?>
        <tr><td colspan="9" class="muted">No hay datos para el periodo seleccionado.</td></tr>
        <?php endif; ?>
      </tbody>
      <?php if ($rows): $grand_extra_value = $grand['extra'] * 3.5; $grand_km_value = $grand['km'] * 0.03; ?>
      <tfoot>
        <tr>
          <th colspan="2">TOTAL</th>
          <th class="right"><?php echo number_format((int)$grand['orders'], 0, ',', '.'); ?></th>
          <th class="right"><?php echo number_format((int)$grand['extra'], 0, ',', '.'); ?> × 3,50 € = <?php echo number_format($grand_extra_value, 2, ',', '.'); ?> €</th>
          <th class="right"><?php echo number_format((float)$grand['tips'], 2, ',', '.'); ?> €</th>
          <th class="right"><?php echo number_format((float)$grand['km'], 2, ',', '.'); ?> × 0,03 € = <?php echo number_format($grand_km_value, 2, ',', '.'); ?> €</th>
          <th class="right"><?php echo number_format((int)$grand['reassignments'], 0, ',', '.'); ?></th>
          <th class="right">—</th>
          <th class="right"><?php echo number_format((float)$grand['hours'], 2, ',', '.'); ?></th>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
