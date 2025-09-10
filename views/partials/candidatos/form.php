<?php
// views/partials/candidatos/form.php
// Espera opcionalmente: $cities (array), $estados (array)
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
require_once __DIR__ . '/../../../includes/csrf.php';
$cities  = $cities  ?? [];
$estados = $estados ?? ['CANDIDATO'=>'Candidato','ACTIVO'=>'Activo','INACTIVO'=>'Inactivo','BAJA'=>'Baja'];
?>
<form id="form-candidato" method="post" enctype="multipart/form-data"
      action="/metricas/metrics_app/public/actions/candidatos_create.php" novalidate>

  <?= csrf_field() ?>

  <!-- Stepper -->
  <ul class="nav nav-pills mb-3" id="cand-steps" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="step1-tab" data-bs-toggle="pill" data-bs-target="#step1" type="button" role="tab">1. Datos personales</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="step2-tab" data-bs-toggle="pill" data-bs-target="#step2" type="button" role="tab">2. Datos laborables</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="step3-tab" data-bs-toggle="pill" data-bs-target="#step3" type="button" role="tab">3. Documentos</button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- Paso 1: Datos personales -->
    <div class="tab-pane fade show active" id="step1" role="tabpanel">
      <div class="row g-2">
        <div class="col-6">
          <label class="form-label">Nombre*</label>
          <input name="nombre" class="form-control" required>
          <div class="invalid-feedback">Obligatorio</div>
        </div>
        <div class="col-6">
          <label class="form-label">Apellido</label>
          <input name="apellido" class="form-control">
        </div>
        <div class="col-6">
          <label class="form-label">Email</label>
          <input name="email" type="email" class="form-control" placeholder="nombre@dominio.com">
          <div class="invalid-feedback">Email no válido</div>
        </div>
        <div class="col-6">
          <label class="form-label">Teléfono</label>
          <input name="telefono" class="form-control" placeholder="+34 600 000 000">
        </div>
        <div class="col-6">
          <label class="form-label">Ciudad</label>
          <input name="city" class="form-control" list="cities">
          <?php if ($cities): ?>
          <datalist id="cities">
            <?php foreach ($cities as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?>
          </datalist>
          <?php endif; ?>
        </div>
        <div class="col-6">
          <label class="form-label">Estado</label>
          <select name="estado" class="form-select">
            <?php foreach ($estados as $k=>$label): ?>
              <option value="<?= h($k) ?>" <?= $k==='CANDIDATO'?'selected':''; ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="d-flex justify-content-end mt-3">
        <button type="button" class="btn btn-primary" data-next="#step2-tab">Siguiente</button>
      </div>
    </div>

    <!-- Paso 2: Datos laborables -->
    <div class="tab-pane fade" id="step2" role="tabpanel">
      <div class="row g-2">
        <div class="col-6">
          <label class="form-label">Vehículo</label>
          <select name="vehiculo" class="form-select">
            <option value="">—</option>
            <option>MOTO</option><option>BICI</option><option>COCHE</option><option>PEATÓN</option>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Tipo de contrato</label>
          <select name="contrato" class="form-select">
            <option value="">—</option>
            <option>AUTONOMO</option><option>LABORAL</option><option>OTRO</option>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Disponibilidad (horas/semana)</label>
          <input name="disp_horas" type="number" min="0" class="form-control" placeholder="Ej: 20">
        </div>
        <div class="col-6">
          <label class="form-label">Observaciones</label>
          <input name="observaciones" class="form-control" maxlength="255">
        </div>
      </div>
      <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-outline-secondary" data-prev="#step1-tab">Atrás</button>
        <button type="button" class="btn btn-primary" data-next="#step3-tab">Siguiente</button>
      </div>
    </div>

    <!-- Paso 3: Documentos -->
    <div class="tab-pane fade" id="step3" role="tabpanel">
      <p class="text-muted small mb-2">Formatos permitidos: PDF/JPG/PNG. Máx 5 MB por archivo.</p>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">DNI</label>
          <input type="file" name="docs[DNI][]" class="form-control" multiple>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Permiso de conducir</label>
          <input type="file" name="docs[PERMISO_CONDUCIR][]" class="form-control" multiple>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">CV</label>
          <input type="file" name="docs[CV][]" class="form-control" multiple>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Otros</label>
          <input type="file" name="docs[OTRO][]" class="form-control" multiple>
        </div>
      </div>
      <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-outline-secondary" data-prev="#step2-tab">Atrás</button>
        <button class="btn btn-primary">Guardar candidato</button>
      </div>
    </div>

  </div>
</form>

<script>
(function(){
  const form = document.getElementById('form-candidato');
  function go(tabBtnSel){ document.querySelector(tabBtnSel).click(); }
  document.querySelectorAll('[data-next]').forEach(b=>{
    b.addEventListener('click', ()=>{
      // Validación mínima del paso actual (HTML5)
      const pane = b.closest('.tab-pane');
      if (pane && !pane.checkValidity()) { pane.querySelectorAll('input,select,textarea').forEach(el=>el.reportValidity()); return; }
      go(b.getAttribute('data-next'));
    });
  });
  document.querySelectorAll('[data-prev]').forEach(b=>{
    b.addEventListener('click', ()=> go(b.getAttribute('data-prev')));
  });
})();
</script>
