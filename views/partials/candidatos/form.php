<?php
// views/partials/candidatos/form.php
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
require_once __DIR__ . '/../../../includes/csrf.php';
?>
<form id="wizard-candidato" class="needs-validation" novalidate
      action="/metricas/metrics_app/public/actions/candidatos_create.php"
      method="post" enctype="multipart/form-data">

  <?= csrf_field_as('_token') ?>

  <!-- Barra de progreso -->
  <div class="mb-3">
    <div class="d-flex justify-content-between small fw-semibold mb-1">
      <span>1. Datos personales</span>
      <span>2. Datos laborables</span>
      <span>3. Documentos</span>
    </div>
    <div class="progress" style="height: 8px;">
      <div id="wiz-progress" class="progress-bar" role="progressbar" style="width: 33%;" aria-valuenow="33" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
  </div>

  <div class="tab-content">

    <!-- PASO 1: DATOS PERSONALES -->
    <div class="tab-pane fade show active" id="step1" role="tabpanel">
      <div class="row g-2">
        <div class="col-12 col-md-6">
          <label class="form-label">Nombre*</label>
          <input name="nombre" class="form-control" required>
          <div class="invalid-feedback">Obligatorio</div>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Apellidos*</label>
          <input name="apellidos" class="form-control" required>
          <div class="invalid-feedback">Obligatorio</div>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Email*</label>
          <input type="email" name="email" class="form-control" required>
          <div class="invalid-feedback">Email no válido</div>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Teléfono*</label>
          <input name="telefono" class="form-control" required>
          <div class="invalid-feedback">Obligatorio</div>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">DNI/NIE*</label>
          <input name="dni" class="form-control" required>
          <div class="invalid-feedback">Obligatorio</div>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Fecha de nacimiento</label>
          <input type="date" name="fecha_nacimiento" class="form-control">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Ciudad</label>
          <select name="ciudad" class="form-select">
            <option value="">—</option>
            <option value="PAL">PAL</option>
            <option value="BCN">BCN</option>
            <option value="INC">INC</option>
          </select>
        </div>
        <!-- Estado eliminado del formulario; se guardará como CANDIDATO por defecto -->
      </div>
      <div class="d-flex justify-content-end mt-3">
        <button type="button" class="btn btn-primary" data-next>Guardar y continuar</button>
      </div>
    </div>

    <!-- PASO 2: DATOS LABORABLES -->
    <div class="tab-pane fade" id="step2" role="tabpanel">
      <div class="row g-2">
        <div class="col-12 col-md-6">
          <label class="form-label">Tipo de contrato</label>
          <select name="contrato" class="form-select">
            <option value="">—</option>
            <option value="40">40</option>
            <option value="30">30</option>
            <option value="20">20</option>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Vehículo propio</label>
          <select name="vehiculo" id="vehiculo-propio" class="form-select">
            <option value="no">No</option>
            <option value="si">Sí</option>
          </select>
        </div>
        <div class="col-12 col-md-6" id="tipo-vehiculo-wrap" style="display:none;">
          <label class="form-label">Tipo de vehículo</label>
          <select name="vehiculo_tipo" id="tipo-vehiculo" class="form-select">
            <option value="">—</option>
            <option value="Moto">Moto</option>
            <option value="Coche">Coche</option>
            <option value="Bici">Bici</option>
            <option value="Patinete">Patinete</option>
          </select>
        </div>
      </div>
      <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-outline-secondary" data-prev>← Volver</button>
        <button type="button" class="btn btn-primary" data-next>Guardar y continuar</button>
      </div>
    </div>

    <!-- PASO 3: DOCUMENTOS -->
    <div class="tab-pane fade" id="step3" role="tabpanel">
      <p class="text-muted small mb-2">
        DNI/Permiso: JPG/PNG/PDF (máx 8MB). CV: PDF (máx 10MB).
      </p>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">DNI/NIE - Front</label>
          <input type="file" name="dni_front" class="form-control" accept="image/*,application/pdf">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">DNI/NIE - Back</label>
          <input type="file" name="dni_back" class="form-control" accept="image/*,application/pdf">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Perm. Conducir - Front</label>
          <input type="file" name="permiso_front" class="form-control" accept="image/*,application/pdf">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Perm. Conducir - Back</label>
          <input type="file" name="permiso_back" class="form-control" accept="image/*,application/pdf">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">CV (PDF)</label>
          <input type="file" name="cv" class="form-control" accept="application/pdf">
        </div>
      </div>
      <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-outline-secondary" data-prev>← Volver</button>
        <button class="btn btn-primary">Finalizar alta</button>
      </div>
    </div>

  </div>
</form>

<script>
(function(){
  const form = document.getElementById('wizard-candidato');
  const steps = ['step1','step2','step3'];
  let idx = 0;

  function show(i){
    idx = Math.max(0, Math.min(steps.length-1, i));
    steps.forEach((id, n)=>{
      document.getElementById(id).classList.toggle('show', n===idx);
      document.getElementById(id).classList.toggle('active', n===idx);
    });
    const pct = Math.round(((idx+1)/steps.length)*100);
    const bar = document.getElementById('wiz-progress');
    if (bar){ bar.style.width = pct+'%'; bar.setAttribute('aria-valuenow', pct); }
  }

  function validateCurrent(){
    const pane = document.getElementById(steps[idx]);
    const controls = pane.querySelectorAll('input,select,textarea');
    for (const el of controls) {
      if (!el.checkValidity()) { el.reportValidity(); return false; }
    }
    return true;
  }

  form.querySelectorAll('[data-next]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      if (!validateCurrent()) return;
      show(idx+1);
    });
  });
  form.querySelectorAll('[data-prev]').forEach(btn=>{
    btn.addEventListener('click', ()=> show(idx-1));
  });

  // Mostrar/ocultar "Tipo de vehículo"
  const vehSel = document.getElementById('vehiculo-propio');
  const tveh   = document.getElementById('tipo-vehiculo-wrap');
  function syncVeh(){ tveh.style.display = (vehSel.value === 'si') ? '' : 'none'; }
  vehSel.addEventListener('change', syncVeh);
  syncVeh();

  show(0);
})();
</script>
