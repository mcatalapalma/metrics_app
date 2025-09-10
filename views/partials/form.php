<?php
// views/partials/candidatos/form.php
// Variables esperadas opcionales: $cities (array), $estados (array)
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
require_once __DIR__ . '/../../../includes/csrf.php';
?>
<form method="post" action="/metricas/metrics_app/public/actions/candidatos_create.php" class="row g-2">
  <?= csrf_field() ?>
  <div class="col-6">
    <label class="form-label">Nombre*</label>
    <input name="nombre" class="form-control" required>
  </div>
  <div class="col-6">
    <label class="form-label">Apellido</label>
    <input name="apellido" class="form-control">
  </div>
  <div class="col-6">
    <label class="form-label">Email</label>
    <input name="email" type="email" class="form-control" placeholder="nombre@dominio.com">
  </div>
  <div class="col-6">
    <label class="form-label">Teléfono</label>
    <input name="telefono" class="form-control" placeholder="+34 600 000 000">
  </div>
  <div class="col-6">
    <label class="form-label">Ciudad</label>
    <input name="city" class="form-control" list="cities">
    <?php if (!empty($cities)): ?>
      <datalist id="cities">
        <?php foreach ($cities as $c): ?>
          <option value="<?= h($c) ?>">
        <?php endforeach; ?>
      </datalist>
    <?php endif; ?>
  </div>
  <div class="col-6">
    <label class="form-label">Estado</label>
    <select name="estado" class="form-select">
      <?php
      $estados = $estados ?? ['CANDIDATO'=>'Candidato','ACTIVO'=>'Activo','INACTIVO'=>'Inactivo','BAJA'=>'Baja'];
      foreach ($estados as $k=>$label): ?>
        <option value="<?= h($k) ?>" <?= $k==='CANDIDATO'?'selected':''; ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-12">
    <button class="btn btn-primary">Guardar</button>
  </div>
</form>
