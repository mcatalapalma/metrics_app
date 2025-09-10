<?php
require_once __DIR__ . '/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Consulta principal: conteo por vehiculo/contrato y unión con la tabla bases
$sql = "SELECT
          r.vehiculo,
          CAST(r.contrato AS UNSIGNED) AS contrato,
          b.base,
          b.p_min,
          b.p_extra,
          COUNT(*) AS n_repartidores,
          ROUND(COUNT(*) * IFNULL(b.base, 0), 2) AS total_base
        FROM repartidores r
        LEFT JOIN bases b
          ON b.vehiculo = r.vehiculo AND b.contrato = r.contrato
        GROUP BY r.vehiculo, CAST(r.contrato AS UNSIGNED), b.base, b.p_min, b.p_extra
        ORDER BY r.vehiculo ASC, contrato DESC";
$rows = $pdo->query($sql)->fetchAll();

// Detectar combinaciones sin base definida
$sin_base = array_filter($rows, fn($x)=> $x['base'] === null);

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=resumen_bases.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['vehiculo','contrato','base','p_min','p_extra','n_repartidores','total_base'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['vehiculo'],
            $r['contrato'],
            $r['base'],
            $r['p_min'],
            $r['p_extra'],
            $r['n_repartidores'],
            $r['total_base'],
        ], ';');
    }
    fclose($out);
    exit;
}

?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Resumen • Bases y Salarios</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    :root{--bg:#0f172a;--card:#111827;--muted:#94a3b8;--text:#e5e7eb;--accent:#22c55e;--danger:#ef4444;}
    *{box-sizing:border-box;font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial;}
    body{margin:0;background:linear-gradient(135deg,#0b1022,#0f172a 40%,#0b1022);color:var(--text);}
    header{padding:20px 16px;border-bottom:1px solid #1f2937;background:rgba(17,24,39,.6);backdrop-filter:saturate(180%) blur(10px)}
    .container{max-width:1100px;margin:24px auto;padding:0 16px}
    .card{background:radial-gradient(80% 140% at 10% 10%,rgba(34,197,94,.14),transparent 40%),#0b1220;border:1px solid #1f2937;border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,.35);padding:18px}
    h1{margin:0;font-size:20px}
    h2{margin:0 0 12px 0;font-size:16px}
    .muted{color:var(--muted);font-size:13px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{padding:10px;border-bottom:1px solid #1f2937;text-align:left;font-size:13px}
    th{color:#cbd5e1;font-weight:600}
    .row{display:flex;gap:10px;align-items:center;justify-content:space-between}
    a.btn{display:inline-block;background:linear-gradient(180deg,#16a34a,#15803d);border:1px solid #14532d;border-radius:10px;padding:8px 12px;color:#fff;text-decoration:none}
    .warn{background:rgba(239,68,68,.07);border:1px solid #7f1d1d;border-radius:12px;padding:10px;margin:10px 0}
    .right{text-align:right}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .tile{background:#0b1325;border:1px solid #1f2937;border-radius:14px;padding:12px}
    .tile h3{margin:0 0 8px 0;font-size:14px;color:#cbd5e1}
    .big{font-size:20px;font-weight:600}
  </style>
</head>
<body>
<header>
  <div class="row">
    <h1>Resumen de bases y salarios</h1>
    <div>
      <a class="btn" href="index.php">Volver al panel</a>
      <a class="btn" href="?export=csv">Exportar CSV</a>
    </div>
  </div>
</header>
<div class="container">

  <div class="grid" style="margin-bottom:16px">
    <?php
      // Totales globales
      $total_reps = 0; $total_nomina = 0.0;
      foreach ($rows as $r){ $total_reps += (int)$r['n_repartidores']; $total_nomina += (float)$r['total_base']; }
    ?>
    <div class="tile">
      <h3>Total repartidores</h3>
      <div class="big"><?php echo number_format($total_reps, 0, ',', '.'); ?></div>
    </div>
    <div class="tile">
      <h3>Coste base estimado (€)</h3>
      <div class="big"><?php echo number_format($total_nomina, 2, ',', '.'); ?></div>
    </div>
  </div>

  <?php if ($sin_base): ?>
    <div class="warn">
      <strong>Aviso:</strong> hay combinaciones de <em>vehículo/contrato</em> sin valor de <code>base</code> en la tabla <code>bases</code>.
      Completa esos registros para un cálculo correcto.
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>Detalle por vehículo y contrato</h2>
    <table>
      <thead>
        <tr>
          <th>Vehículo</th>
          <th>Contrato (h)</th>
          <th>Base (€)</th>
          <th>P. mínimo</th>
          <th>P. extra</th>
          <th># Repartidores</th>
          <th class="right">Total base (€)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo h($r['vehiculo']); ?></td>
            <td><?php echo h($r['contrato']); ?></td>
            <td><?php echo $r['base']===null ? '<span class="muted">—</span>' : number_format((float)$r['base'], 2, ',', '.'); ?></td>
            <td><?php echo $r['p_min']===null ? '<span class="muted">—</span>' : h($r['p_min']); ?></td>
            <td><?php echo $r['p_extra']===null ? '<span class="muted">—</span>' : h($r['p_extra']); ?></td>
            <td><?php echo h($r['n_repartidores']); ?></td>
            <td class="right"><?php echo number_format((float)$r['total_base'], 2, ',', '.'); ?></td>
          </tr>
        <?php endforeach; if (!$rows): ?>
          <tr><td colspan="7" class="muted">Sin datos.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="muted" style="margin-top:10px">
    Nota: El <strong>Coste base estimado</strong> es un cálculo simple <code>#repartidores × base</code> por vehículo/contrato.
    Puedes ajustar las bases y precios mínimos/extras en el panel principal (sección Bases).
  </div>
</div>
</body>
</html>
