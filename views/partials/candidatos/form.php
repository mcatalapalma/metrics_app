<?php
// views/partials/candidatos/form.php
// Opcionalmente: $cities, $estados (si los pasas desde la vista)
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
require_once __DIR__ . '/../../../includes/csrf.php';
$cities  = $cities  ?? [];
$estados = $estados ?? ['CANDIDATO'=>'Candidato','ACTIVO'=>'Activo','INACTIVO'=>'Inactivo','BAJA'=>'Baja'];

// Reglas de subida (igual que en tu código original)
$ALLOWED = [
  'cv'           => ['types' => ['application/pdf'],                     'max' => 10*1024*1024],
  'dni_frente'   => ['types' => ['image/jpeg','image/png','application/pdf'], 'max' =>  8*1024*1024],
  'antecedentes' => ['types' => ['application/pdf'],                     'max' => 10*1024*1024],
];
?>
<form id="form-candidato" method="post" enctype="multipart/form-data"
      action="/metricas/metrics_app/public/actions/candidatos_create.php" novalidate>

  <!-- Importante: emitimos el token también como _token para ser 100% compatibles -->
  <?= csrf_field_as('_token') ?>

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

    <!-- Paso 1: Datos personales (campos y nombres EXACTOS a tu archivo) -->
    <div class="tab-pane fade show active" id="step1" role="tabpanel">
      <div class="row g-2">
        <div class="col-6">
          <label class="form-label">Nombre*</label>
          <input name="nombre" class="form-control" required>
        </div>
        <div class="col-6">
          <label class="form-label">Apellidos*</label>
          <input name="apellidos" class="form-control" required>
        </div>
        <div class="col-6">
          <label class="form-label">Email*</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="col-6">
          <label class="form-label">Teléfono*</label>
          <input name="telefono" class="form-control" required>
        </div>
        <div class="col-6">
          <label class="form-label">DNI/NIE*</label>
          <input name="dni" class="form-control" required>
        </div>
        <div class="col-6">
          <label class="form-label">Fecha de nacimiento</label>
          <input type="date" name="fecha_nacimiento" class="form-control">
        </div>
        <div class="col-6">
          <label class="form-label">Ciudad</label>
          <input name="ciudad" class="form-control" list="cities">
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

    <!-- Paso 2: Datos laborables (nombres de inputs según tu archivo) -->
    <div class="tab-pane fade" id="step2" role="tabpanel">
      <div class="row g-2">
        <div class="col-6">
          <label class="form-label">Años de experiencia</label>
          <input type="number" min="0" name="experiencia_anios" class="form-control">
        </div>
        <div class="col-6">
          <label class="form-label">Vehículo propio</label>
          <select name="vehiculo" class="form-select">
            <option value="no">No</option>
            <option value="si">Sí</option>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Tipo de licencia</label>
          <input name="licencia_tipo" class="form-control" placeholder="AM / A1 / B / ...">
        </div>
        <div class="col-12">
          <label class="form-label d-block">Disponibilidad</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" id="disp-mediodia" type="checkbox" name="disponibilidad[]" value="mediodia">
            <label class="form-check-label" for="disp-mediodia">Mediodía</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" id="disp-noche" type="checkbox" name="disponibilidad[]" value="noche">
            <label class="form-check-label" for="disp-noche">Noche</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" id="disp-finsemana" type="checkbox" name="disponibilidad[]" value="finsemana">
            <label class="form-check-label" for="disp-finsemana">Fin de semana</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <textarea name="observaciones" rows="3" class="form-control"></textarea>
        </div>
      </div>
      <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-outline-secondary" data-prev="#step1-tab">Atrás</button>
        <button type="button" class="btn btn-primary" data-next="#step3-tab">Siguiente</button>
      </div>
    </div>

    <!-- Paso 3: Documentos (mismas reglas que tu archivo) -->
    <div class="tab-pane fade" id="step3" role="tabpanel">
      <p class="text-muted small mb-2">
        Formatos: CV/Antecedentes en PDF (máx 10MB). DNI: JPG/PNG/PDF (máx 8MB).
      </p>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">CV (PDF)</label>
          <input type="file" name="cv" class="form-control" accept="application/pdf">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">DNI/NIE - frente (JPG/PNG/PDF)</label>
          <input type="file" name="dni_frente" class="form-control" accept="image/*,application/pdf">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Certificado de antecedentes (PDF)</label>
          <input type="file" name="antecedentes" class="form-control" accept="application/pdf">
        </div>
      </div>
      <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-outline-secondary" data-prev="#step2-tab">Atrás</button>
        <button class="btn btn-primary">Finalizar alta</button>
      </div>
    </div>

  </div>
</form>

<script>
(function(){
  function go(tabBtnSel){ const el=document.querySelector(tabBtnSel); if(el) el.click(); }
  document.querySelectorAll('[data-next]').forEach(b=>{
    b.addEventListener('click', ()=>{
      const pane = b.closest('.tab-pane');
      if (pane) {
        const inputs = pane.querySelectorAll('input,select,textarea');
        for (const el of inputs) {
          if (!el.checkValidity()) { el.reportValidity(); return; }
        }
      }
      go(b.getAttribute('data-next'));
    });
  });
  document.querySelectorAll('[data-prev]').forEach(b=>{
    b.addEventListener('click', ()=> go(b.getAttribute('data-prev')));
  });
})();
</script>
