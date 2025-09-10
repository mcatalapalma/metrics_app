<?php
$active = 'reportes';
$title  = 'Reportes';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-12 col-lg-8">
    <div class="card p-3">
      <h5 class="mb-2">KPIs (placeholder)</h5>
      <ul class="mb-0">
        <li>Pedidos por ciudad y fecha</li>
        <li>Tiempo medio de entrega</li>
        <li>Reasignaciones</li>
        <li>Propinas</li>
      </ul>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card p-3">
      <h6 class="mb-2">Descargas</h6>
      <a class="btn btn-primary btn-sm" href="export_kpis_csv.php">Exportar CSV</a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
