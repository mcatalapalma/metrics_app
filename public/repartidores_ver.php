<?php
declare(strict_types=1);
$pageTitle='Repartidor — Detalle'; $activeMenu='repartidores';
require_once __DIR__.'/../config/db.php';
include __DIR__.'/../includes/header.php';
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)($v ?? '')); } };
$id=(int)($_GET['id']??0);
$st=$pdo->prepare("SELECT * FROM repartidores WHERE id=:id"); $st->execute([':id'=>$id]); $rep=$st->fetch(PDO::FETCH_ASSOC);
if(!$rep){ echo '<div class="alert alert-warning">Repartidor no encontrado.</div>'; include __DIR__.'/../includes/footer.php'; exit; }

$docs_table = 'repartidor_documentos';
try{ $pdo->query("SELECT 1 FROM repartidor_documentos LIMIT 1"); }
catch(Throwable $e){ $docs_table = 'repartidore_documentos'; }

$docs=$pdo->prepare("SELECT id, repartidor_id, fecha, tipo, archivo, size_bytes FROM {$docs_table} WHERE repartidor_id=:id ORDER BY fecha DESC");
$docs->execute([':id'=>$id]); $docs=$docs->fetchAll(PDO::FETCH_ASSOC);

function estado_badge($e){ $e=strtoupper($e??''); $cls='badge-secondary';
  if($e==='CANDIDATO') $cls='badge-candidato';
  if($e==='ACTIVO') $cls='badge-activo';
  if($e==='INACTIVO') $cls='badge-inactivo';
  if($e==='BAJA') $cls='badge-baja';
  return '<span class="badge '.$cls.'">'.$e.'</span>';
}
// ---------- MÉTRICAS ----------
// Leer regla global: p_extra (default) o p_min
$rule = $pdo->query("SELECT `value` FROM settings WHERE `key`='extra_rule'")->fetchColumn();
if ($rule === false) { $rule = 'p_extra'; }

// Obtener umbral según regla desde bases
$threshold = null;
if (!empty($rep['vehiculo']) && !empty($rep['contrato'])) {
  $col = $rule === 'p_min' ? 'p_min' : 'p_extra';
  $st = $pdo->prepare("SELECT $col FROM bases WHERE vehiculo=? AND contrato=?");
  $st->execute([$rep['vehiculo'], $rep['contrato']]);
  $threshold = $st->fetchColumn();
  if ($threshold !== false) { $threshold = (int)$threshold; } else { $threshold = null; }
}

$userIds = [];
$rows = $pdo->prepare("SELECT DISTINCT id_gd FROM cuentas_glovo WHERE repartidor=? AND id_gd IS NOT NULL");
$rows->execute([$id]);
$userIds = array_map(fn($r)=> (int)$r['id_gd'], $rows->fetchAll());

// Filtro mes/año + límite por fecha de alta
$month = isset($_GET['month']) ? max(1, min(12, (int)$_GET['month'])) : (int)date('m');
$year  = isset($_GET['year'])  ? max(2000, min(2100, (int)$_GET['year'])) : (int)date('Y');

$from_month = sprintf('%04d-%02d-01', $year, $month);
$to         = date('Y-m-d', strtotime("$from_month +1 month"));
$hire       = $rep['f_alta'];
$from       = ($hire && $hire > $from_month) ? $hire : $from_month;

$metrics = []; 
$tot = [
  'orders'=>0,'tips'=>0.0,'km'=>0.0,'hours'=>0.0,'extra'=>0,
  'reassignments'=>0, 'adt_sum'=>0.0, 'adt_n'=>0
];

if ($userIds) {
  $in = implode(',', array_fill(0, count($userIds), '?'));
  $sql = "SELECT user_id, metric_date, city, reassignments, orders, avg_delivery_time_min, tips, km, hours
          FROM courier_metrics
          WHERE user_id IN ($in)
            AND metric_date >= ? AND metric_date < ?
          ORDER BY metric_date ASC";
  $params = array_merge($userIds, [$from, $to]);
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $metrics = $st->fetchAll();

  foreach ($metrics as &$m) {
    $orders = (int)$m['orders'];
    $extra = ($threshold !== null) ? max(0, $orders - (int)$threshold) : 0;
    $m['extra'] = $extra;

    $tot['orders'] += $orders;
    $tot['tips']   += (float)$m['tips'];
    $tot['km']     += (float)$m['km'];
    $tot['hours']  += (float)$m['hours'];
    $tot['extra']  += $extra;

    $tot['reassignments'] += isset($m['reassignments']) ? (int)$m['reassignments'] : 0;

    if (isset($m['avg_delivery_time_min']) && $m['avg_delivery_time_min'] !== null && $m['avg_delivery_time_min'] !== '') {
      $tot['adt_sum'] += (float)$m['avg_delivery_time_min'];
      $tot['adt_n']++;
    }
  }
  unset($m);
}

$avg_delivery_time = $tot['adt_n'] > 0 ? $tot['adt_sum'] / $tot['adt_n'] : 0.0;

// Export CSV (con nuevos campos)
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=metrics_'.$id.'_'.$year.'-'.$month.'.csv');
  $out = fopen('php://output','w');
  fputcsv($out, ['user_id','metric_date','orders','extra orders','tips','km','hours','reassignments','avg_delivery_time_min'], ';');
  foreach ($metrics as $m) {
    fputcsv($out, [
      $m['user_id'],
      date('d/m/Y', strtotime($m['metric_date'])),
      $m['orders'],
      $m['extra'] ?? 0,
      number_format((float)$m['tips'],2,'.',''),
      number_format((float)$m['km'],2,'.',''),
      number_format((float)$m['hours'],2,'.',''),
      isset($m['reassignments']) ? (int)$m['reassignments'] : 0,
      isset($m['avg_delivery_time_min']) && $m['avg_delivery_time_min']!==null && $m['avg_delivery_time_min']!=='' 
        ? number_format((float)$m['avg_delivery_time_min'],2,'.','') : '0.00',
    ], ';');
  }
  fputcsv($out, [
    'TOTAL','',
    $tot['orders'],
    $tot['extra'],
    number_format($tot['tips'],2,'.',''),
    number_format($tot['km'],2,'.',''),
    number_format($tot['hours'],2,'.',''),
    (int)$tot['reassignments'],
    number_format($avg_delivery_time,2,'.','')
  ], ';');
  fclose($out);
  exit;
}
?>
<div class="card mb-3">
  <div class="card-body d-flex justify-content-between align-items-center">
    <h1 class="h5 m-0"><?= h(($rep['nombre']??'').' '.($rep['apellido']??'')) ?></h1>
    <a href="repartidores.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <!-- Card: Datos personales -->
  <div class="col-12 col-lg-6">
    <div class="card-alt card-info h-100">
      <div class="card-header p-3">
        <div class="card-title"><i class="fa-regular fa-id-card me-2"></i>Datos personales</div>
      </div>
      <div class="card-body p-3">
        <div class="kv">
          <div class="k">Nombre</div><div class="v"><?= h($rep['nombre']??'') ?></div>
          <div class="k">Apellidos</div><div class="v"><?= h($rep['apellido']??'') ?></div>
          <div class="k">DNI</div><div class="v"><?= h($rep['dni']??'') ?></div>
          <div class="k">Nº S.S.</div><div class="v"><?= h($rep['ss']??'') ?></div>
          <div class="k">Teléfono</div><div class="v"><?= h($rep['tel']??'') ?></div>
          <div class="k">Email</div><div class="v"><?= h($rep['email']??'') ?></div>
          <div class="k">IBAN</div><div class="v"><?= h($rep['iban']??'') ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card: Datos laborables -->
  <div class="col-12 col-lg-6">
    <div class="card-alt card-info h-100">
      <div class="card-header p-3">
        <div class="card-title"><i class="fa-solid fa-briefcase me-2"></i>Datos laborables</div>
      </div>
      <div class="card-body p-3">
        <div class="kv">
          <div class="k">Vehículo</div><div class="v"><?= h($rep['vehiculo']??'') ?></div>
          <div class="k">Contrato (h)</div><div class="v"><?= h($rep['contrato']??'') ?></div>
          <div class="k">City</div><div class="v"><span class="badge badge-soft"><?= h($rep['city']??'') ?></span></div>
          <div class="k">Estado</div><div class="v"><?= estado_badge($rep['estado']??'') ?></div>
          <div class="k">Fecha alta</div><div class="v"><?= h($rep['f_alta']??'') ?></div>
          <div class="k">Notas</div><div class="v"><?= h($rep['notas']??'') ?></div>
        </div>
      </div>
    </div>
  </div>
</div>


<div class="card my-3">
  <div class="card-body">
    <h2 class="h6 mb-3">Documentos</h2>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead><tr><th>Tipo</th><th>Fecha</th><th>Tamaño</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
          <?php foreach($docs as $d): $file='../storage/'.($d['archivo']??''); ?>
          <tr>
            <td><?= h($d['tipo']??'') ?></td>
            <td><?= h($d['fecha']??'') ?></td>
            <td><?= h(isset($d['size_bytes']) ? number_format((int)$d['size_bytes']/1024, 1).' KB' : '') ?></td>
            <td class="text-end">
              <button type="button" class="btn btn-ghost btn-sm" data-bs-toggle="modal" data-bs-target="#docModal" data-file="<?= h($file) ?>">
                <i class="fa-regular fa-eye me-1"></i> Ver
              </button>
            </td>
          </tr>
          <?php endforeach; if(empty($docs)): ?>
            <tr><td colspan="4" class="text-center muted">Sin documentos</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>


<!-- Sección MÉTRICAS -->
<div class="card">
  <div class="card-body">
  <div class="row" style="justify-content:space-between;align-items:center">
    <h2 class="h6 mb-3">Métricas</h2>
    <div class="metrics-toolbar d-flex flex-wrap align-items-center w-100 m-2 p-3">
  <form method="get" class="row w-100 gy-2 gx-2 align-items-center">
    <input type="hidden" name="id" value="<?= (int)$id; ?>">

    <!-- Mes -->
    <div class="col-auto">
      <label for="month" class="form-label mb-0 small">Mes</label>
      <select name="month" id="month" class="form-select form-select-sm">
        <?php
          for ($m=1; $m<=12; $m++) {
            $sel = ($m === $month) ? 'selected' : '';
            echo "<option value=\"$m\" $sel>".date('m', strtotime("2020-$m-01"))."</option>";
          }
        ?>
      </select>
    </div>

    <!-- Año (actual hasta -5) -->
    <div class="col-auto">
      <label for="year" class="form-label mb-0 small">Año</label>
      <select name="year" id="year" class="form-select form-select-sm" style="min-width:110px">
        <?php
          $currentYear = (int)date('Y');
          for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
            $sel = ($y === $year) ? 'selected' : '';
            echo "<option value=\"$y\" $sel>$y</option>";
          }
        ?>
      </select>
    </div>

    <!-- Botones a la derecha -->
    <div class="col-auto ms-auto d-flex gap-2">
      <button class="btn btn-primary btn-sm" type="submit">
        <i class="fa-solid fa-filter me-1"></i> Filtrar
      </button>
      <a class="btn btn-outline-secondary btn-sm"
         href="?id=<?= (int)$id; ?>&month=<?= (int)$month; ?>&year=<?= (int)$year; ?>&export=csv">
        <i class="fa-regular fa-file-excel me-1"></i> Exportar CSV
      </a>
    </div>
  </form>
</div>


  </div>
  <?php if (!$userIds): ?>
    <p class="muted">No hay <code>user_id</code> vinculado. Asocia una cuenta en <a href="cuentas_glovo.php">Cuentas Glovo</a> para ver métricas.</p>
  <?php else: ?>
    <p class="muted">
      Regla: <code><?php echo h($rule); ?></code>
      • Umbral: <?php echo $threshold!==null ? (int)$threshold : '—'; ?>
      • User IDs: <?php echo implode(', ', array_map('intval', $userIds)); ?>
      • Rango: <?php echo date('d/m/Y', strtotime($from)); ?> a <?php echo date('d/m/Y', strtotime("$to -1 day")); ?>
      <?php if ($hire && $from===$hire): ?> (filtrado por fecha de alta)<?php endif; ?>
    </p>

    <?php if ($metrics): 
      $valor_extra_orders = $tot['extra'] * 3.5;
      $valor_km = $tot['km'] * 0.03;
      $total_extras = $tot['tips'] + $valor_extra_orders + $valor_km;
    ?>
    <div class="card" style="margin-bottom:12px">
      <div class="card-body">
      <p><strong>Total tips:</strong> <?php echo number_format($tot['tips'], 2, ',', '.'); ?> €</p>
      <p><strong>Total extra orders:</strong> <?php echo number_format($tot['extra'], 0, ',', '.'); ?> × 3,50 € = <?php echo number_format($valor_extra_orders, 2, ',', '.'); ?> €</p>
      <p><strong>Total km:</strong> <?php echo number_format($tot['km'], 2, ',', '.'); ?> × 0,03 € = <?php echo number_format($valor_km, 2, ',', '.'); ?> €</p>
      <p><strong>Total Extras:</strong> <?php echo number_format($total_extras, 2, ',', '.'); ?> €</p>
      <p><strong>Total hours:</strong> <?php echo number_format($tot['hours'], 2, ',', '.'); ?> h</p>
      <hr style="border:0;border-top:1px solid #1f2937;margin:10px 0">
      <p><strong>Total reasignaciones:</strong> <?php echo number_format((int)$tot['reassignments'], 0, ',', '.'); ?></p>
      <p><strong>Media de Entrega (min):</strong> <?php echo number_format((float)$avg_delivery_time, 2, ',', '.'); ?> min</p>
    </div></div>
    <?php endif; ?>

    <div class="card" style="overflow:auto;border-radius:12px;border:1px solid var(--b)">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>user_id</th>
            <th>metric_date</th>
            <th class="right">orders</th>
            <th class="right">extra orders</th>
            <th class="right">tips</th>
            <th class="right">km</th>
            <th class="right">hours</th>
            <th class="right">reassignments</th>
            <th class="right">avg deliver (min)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($metrics as $m): $extra=(int)($m['extra'] ?? 0); $trcls=$extra>0?' class="tr-extra"':''; ?>
          <tr<?php echo $trcls; ?>>
            <td><?php echo (int)$m['user_id']; ?></td>
            <td><?php echo date('d/m/Y', strtotime($m['metric_date'])); ?></td>
            <td class="right"><?php echo number_format((int)$m['orders'], 0, ',', '.'); ?></td>
            <td class="right"><?php echo number_format($extra, 0, ',', '.'); ?></td>
            <td class="right"><?php echo number_format((float)$m['tips'], 2, ',', '.'); ?> €</td>
            <td class="right"><?php echo number_format((float)$m['km'], 2, ',', '.'); ?></td>
            <td class="right"><?php echo number_format((float)$m['hours'], 2, ',', '.'); ?></td>
            <td class="right"><?php echo isset($m['reassignments']) ? number_format((int)$m['reassignments'], 0, ',', '.') : '0'; ?></td>
            <td class="right">
              <?php echo isset($m['avg_delivery_time_min']) && $m['avg_delivery_time_min']!==null && $m['avg_delivery_time_min']!==''
                ? number_format((float)$m['avg_delivery_time_min'], 2, ',', '.')
                : '0,00'; ?>
            </td>
          </tr>
          <?php endforeach; if (!$metrics): ?>
          <tr><td colspan="9" class="muted">No hay métricas en el rango seleccionado.</td></tr>
          <?php endif; ?>
        </tbody>
        <?php if ($metrics): ?>
        <tfoot>
          <tr>
            <th>TOTAL</th><th></th>
            <th class="right"><?php echo number_format((int)$tot['orders'], 0, ',', '.'); ?></th>
            <th class="right"><?php echo number_format((int)$tot['extra'], 0, ',', '.'); ?></th>
            <th class="right"><?php echo number_format((float)$tot['tips'], 2, ',', '.'); ?> €</th>
            <th class="right"><?php echo number_format((float)$tot['km'], 2, ',', '.'); ?></th>
            <th class="right"><?php echo number_format((float)$tot['hours'], 2, ',', '.'); ?></th>
            <th class="right"><?php echo number_format((int)$tot['reassignments'], 0, ',', '.'); ?></th>
            <th class="right"><?php echo number_format((float)$avg_delivery_time, 2, ',', '.'); ?></th>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  <?php endif; ?>
</div></div>

<div class="modal fade" id="docModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Vista previa documento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="docContent">
        <div class="text-center muted">Cargando…</div>
      </div>
      <div class="modal-footer">
        <a id="docDownload" class="btn btn-primary" href="#" download>Descargar</a>
        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<script>
const docModal = document.getElementById('docModal');
docModal.addEventListener('show.bs.modal', function (event) { 
  const btn = event.relatedTarget;
  const file = btn.getAttribute('data-file');
  const content = document.getElementById('docContent');
  const download = document.getElementById('docDownload');
  download.href = file || '#';
  if(!file){ content.innerHTML = '<div class="text-center muted">Archivo no disponible</div>'; return; }
  if(/\.pdf$/i.test(file)){
    content.innerHTML = '<embed src="'+file+'" type="application/pdf" width="100%" height="650px">';
  } else if(/\.(jpg|jpeg|png)$/i.test(file)){
    content.innerHTML = '<img src="'+file+'" class="img-fluid" />';
  } else {
    content.innerHTML = '<p>Formato no soportado. <a class="btn btn-ghost btn-sm" href="'+file+'" target="_blank">Abrir</a></p>';
  }
});
</script>

<?php include __DIR__.'/../includes/footer.php'; ?>
