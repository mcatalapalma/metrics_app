<?php
// views/partials/candidatos/form.php
// Se puede pasar $cities y $estados desde la vista si quieres listas/deafults.
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
require_once __DIR__ . '/../../../includes/csrf.php';
$cities  = $cities  ?? [];
$estados = $estados ?? ['CANDIDATO'=>'Candidato','ACTIVO'=>'Activo','INACTIVO'=>'Inactivo','BAJA'=>'Baja'];
?>
<form id="wizard-candidato" class="needs-validation" novalidate
      action="/metricas/metrics_app/public/actions/candidatos_create.php"
      method="post" enctype="multipart/form-data">

  <?= csrf_field_as('_token') ?>

  <!-- Barra de progreso (tal cual UX de wizard) -->
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

    <!-- PASO 1: DATOS PERSONALES (MISMO NOMBRE DE CAMPOS) -->
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
          <input name="ciudad" class="form-control" list="cities">
          <?php if ($cities): ?>
          <datalist id="cities">
            <?php foreach ($cities as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?>
          </datalist>
          <?php endif; ?>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Estado</label>
          <select name="estado" class="form-select">
            <?php foreach ($estados as $k=>$label): ?>
              <option value="<?= h($k) ?>" <?= $k==='CANDIDATO'?'selected':''; ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="d-flex justify-content-end mt-3">
        <button type="button" class="btn btn-primary" data-next>Guardar y continuar</button>
      </div>
    </div>

    <!-- PASO 2: DATOS LABORABLES (MISMO NOMBRE DE CAMPOS) -->
    <div class="tab-pane fade" id="step2" role="tabpanel">
      <div class="row g-2">
        <div class="col-12 col-md-6">
          <label class="form-label">Años de experiencia</label>
          <input type="number" min="0" name="experiencia_anios" class="form-control">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Vehículo propio</label>
          <select name="vehiculo" class="form-select">
            <option value="no">No</option>
            <option value="si">Sí</option>
          </select>
        </div>
        <div class="col-12 col-md-6">
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
        <button type="button" class="btn btn-outline-secondary" data-prev>← Volver</button>
        <button type="button" class="btn btn-primary" data-next>Guardar y continuar</button>
      </div>
    </div>

    <!-- PASO 3: DOCUMENTOS (MISMO NOMBRE DE CAMPOS) -->
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
    // progreso: 1/3, 2/3, 3/3
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

  // Inicial
  show(0);
})();
</script>
