<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/helpers.php';

$sql = "SELECT
          r.vehiculo,
          CAST(r.contrato AS UNSIGNED) AS contrato,
          b.base, b.p_min, b.p_extra,
          COUNT(*) AS n_repartidores,
          ROUND(COUNT(*) * IFNULL(b.base, 0), 2) AS total_base
        FROM repartidores r
        LEFT JOIN bases b
          ON b.vehiculo = r.vehiculo AND b.contrato = r.contrato
        GROUP BY r.vehiculo, CAST(r.contrato AS UNSIGNED), b.base, b.p_min, b.p_extra
        ORDER BY r.vehiculo ASC, contrato DESC";
$rows = $pdo->query($sql)->fetchAll();

if(isset($_GET['export']) && $_GET['export']==='csv'){
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=resumen_bases.csv');
  $out=fopen('php://output','w');
  fputcsv($out,['vehiculo','contrato','base','p_min','p_extra','n_repartidores','total_base'],';');
  foreach($rows as $r){
    fputcsv($out,[$r['vehiculo'],$r['contrato'],$r['base'],$r['p_min'],$r['p_extra'],$r['n_repartidores'],$r['total_base']],';');
  }
  fclose($out); exit;
}

include __DIR__.'/../includes/header.php';
$total_reps=0; $total_nomina=0.0;
foreach($rows as $r){ $total_reps+=(int)$r['n_repartidores']; $total_nomina+=(float)$r['total_base']; }
?>
<div class="card">
  <h1>Resumen Bases y Salarios</h1>
  <div class="grid">
    <div class="card"><h2>Total repartidores</h2><div style="font-size:22px;font-weight:600"><?php echo number_format($total_reps,0,',','.'); ?></div></div>
    <div class="card"><h2>Coste base estimado (€)</h2><div style="font-size:22px;font-weight:600"><?php echo number_format($total_nomina,2,',','.'); ?></div></div>
  </div>
  <p><a class="btn" href="?export=csv">Exportar CSV</a></p>
  <div style="overflow:auto;border-radius:12px;border:1px solid var(--b)">
    <table>
      <thead><tr><th>Vehículo</th><th>Contrato</th><th>Base</th><th>P. mín</th><th>P. extra</th><th># Reps</th><th>Total base</th></tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td><?php echo h($r['vehiculo']); ?></td>
          <td><?php echo h($r['contrato']); ?></td>
          <td><?php echo $r['base']===null ? '—' : number_format((float)$r['base'],2,',','.'); ?></td>
          <td><?php echo $r['p_min']===null ? '—' : h($r['p_min']); ?></td>
          <td><?php echo $r['p_extra']===null ? '—' : h($r['p_extra']); ?></td>
          <td><?php echo h($r['n_repartidores']); ?></td>
          <td><?php echo number_format((float)$r['total_base'],2,',','.'); ?></td>
        </tr>
        <?php endforeach; if(!$rows): ?>
        <tr><td colspan="7" class="muted">Sin datos.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
