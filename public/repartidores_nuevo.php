<?php
declare(strict_types=1);
$pageTitle='Nuevo Candidato'; $activeMenu='repartidores';
include __DIR__.'/../includes/header.php';
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)($v ?? '')); } };
?>
<div class="card mb-3">
  <div class="card-body d-flex justify-content-between align-items-center">
    <h1 class="h5 m-0"><i class="fa-solid fa-user-plus me-2"></i>Nuevo Candidato</h1>
    <a href="repartidores.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between small muted mb-2">
      <span>Paso <span id="pbStep">1</span>/3</span><span id="pbPct">0%</span>
    </div>
    <div class="progress" role="progressbar" aria-label="Progreso">
      <div class="progress-bar" id="pb" style="width:0%; background:var(--brand)"></div>
    </div>
  </div>
</div>

<form id="formCandidato" method="post" action="guardar_candidato.php" enctype="multipart/form-data" novalidate>
  <div class="card mb-3 step" data-step="1">
    <div class="card-body">
      <h2 class="h6 mb-3">Datos personales</h2>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Nombre *</label><input type="text" name="nombre" class="form-control" required><div class="invalid-feedback">Campo obligatorio.</div></div>
        <div class="col-md-4"><label class="form-label">Apellidos *</label><input type="text" name="apellido" class="form-control" required><div class="invalid-feedback">Campo obligatorio.</div></div>
        <div class="col-md-4"><label class="form-label">DNI/NIE *</label><input type="text" name="dni" class="form-control" required><div class="invalid-feedback">Campo obligatorio.</div></div>
        <div class="col-md-4"><label class="form-label">Nº S.S. *</label><input type="text" name="ss" class="form-control" required><div class="invalid-feedback">Campo obligatorio.</div></div>
        <div class="col-md-4"><label class="form-label">Teléfono *</label><input type="tel" name="tel" class="form-control" required><div class="invalid-feedback">Campo obligatorio.</div></div>
        <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control"><div class="invalid-feedback">Formato inválido.</div></div>
        <div class="col-12"><label class="form-label">IBAN *</label><input type="text" name="iban" class="form-control" required><div class="invalid-feedback">Campo obligatorio.</div></div>
      </div>
    </div>
  </div>

  <div class="card mb-3 step" data-step="2" style="display:none">
    <div class="card-body">
      <h2 class="h6 mb-3">Datos profesionales</h2>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Vehículo *</label><select name="vehiculo" class="form-select" required><option value="">Selecciona…</option><option value="BICI">BICI</option><option value="MOTO">MOTO</option><option value="COCHE">COCHE</option></select><div class="invalid-feedback">Selecciona una opción.</div></div>
        <div class="col-md-4"><label class="form-label">Contrato (horas) *</label><select name="contrato" class="form-select" required><option value="">Selecciona…</option><option>40</option><option>30</option><option>20</option></select><div class="invalid-feedback">Selecciona una opción.</div></div>
        <div class="col-md-4"><label class="form-label">City *</label><input type="text" name="city" class="form-control" required><div class="invalid-feedback">Campo obligatorio.</div></div>
      </div>
    </div>
  </div>

  <div class="card mb-3 step" data-step="3" style="display:none">
    <div class="card-body">
      <h2 class="h6 mb-3">Resumen</h2>
      <div class="table-responsive"><table class="table"><tbody id="resumeTable"></tbody></table></div>
      <hr />
      <h2 class="h6 mb-3">Documentos</h2>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">DNI/NIE frontal *</label><input type="file" name="dni_front" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required><div class="invalid-feedback">Obligatorio (PDF/JPG/PNG, máx 5MB).</div></div>
        <div class="col-md-6"><label class="form-label">DNI/NIE trasero *</label><input type="file" name="dni_back" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required><div class="invalid-feedback">Obligatorio (PDF/JPG/PNG, máx 5MB).</div></div>
        <div class="col-md-6"><label class="form-label">Permiso conducir frontal</label><input type="file" name="perm_front" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
        <div class="col-md-6"><label class="form-label">Permiso conducir trasero</label><input type="file" name="perm_back" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
        <div class="col-12"><label class="form-label">CV (PDF)</label><input type="file" name="cv" class="form-control" accept=".pdf"></div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-between mb-5">
    <button type="button" class="btn btn-ghost" id="btnBack">← Atrás</button>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-ghost" id="btnSaveDraft">Guardar borrador</button>
      <button type="button" class="btn btn-primary" id="btnNext">Siguiente →</button>
      <button type="submit" class="btn btn-primary" id="btnSubmit" style="display:none">Enviar</button>
    </div>
  </div>
</form>

<script>
(function(){
  const steps = Array.from(document.querySelectorAll('.step'));
  let i = 0;
  const pb = document.getElementById('pb'), pbStep=document.getElementById('pbStep'), pbPct=document.getElementById('pbPct');
  const btnNext=document.getElementById('btnNext'), btnBack=document.getElementById('btnBack'), btnSubmit=document.getElementById('btnSubmit');
  const form=document.getElementById('formCandidato'); const btnSaveDraft=document.getElementById('btnSaveDraft');

  function renderProgress(){ const pct=Math.round(((i+1)/steps.length)*100); pb.style.width=pct+'%'; pbStep.textContent=(i+1); pbPct.textContent=pct+'%'; }
  function showStep(index){ steps.forEach((s,idx)=> s.style.display=(idx===index)?'':'none'); btnBack.style.visibility=index===0?'hidden':'visible'; btnNext.style.display=(index===steps.length-1)?'none':''; btnSubmit.style.display=(index===steps.length-1)?'':'none'; renderProgress(); if(index===2) fillResume(); }
  function setInvalid(el,msg){ el.classList.add('is-invalid'); const fb=el.parentElement.querySelector('.invalid-feedback'); if(fb) fb.textContent=msg||'Campo obligatorio'; }
  function clearInvalid(el){ el.classList.remove('is-invalid'); }
  function validateStep(index){ let valid=true; const inputs=steps[index].querySelectorAll('input,select'); inputs.forEach(el=>{ clearInvalid(el); if(el.hasAttribute('required')){ if(el.type==='file'){ if(!el.files||el.files.length===0){ setInvalid(el,'Obligatorio'); valid=false; } else if(el.files[0].size>5*1024*1024){ setInvalid(el,'Máx 5MB'); valid=false; } } else if(!el.value){ setInvalid(el,'Obligatorio'); valid=false; } } if(el.type==='email'&&el.value){ const ok=/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value); if(!ok){ setInvalid(el,'Email no válido'); valid=false; } } }); return valid; }
  function fillResume(){ const map={'nombre':'Nombre','apellido':'Apellidos','dni':'DNI','ss':'Nº S.S.','tel':'Teléfono','email':'Email','iban':'IBAN','vehiculo':'Vehículo','contrato':'Contrato','city':'City'}; const tbody=document.getElementById('resumeTable'); tbody.innerHTML=''; for(const key in map){ const el=form.elements[key]; if(!el) continue; const val=el.value||''; const icon=val?'<i class="fa-solid fa-circle-check text-success me-1"></i>':'<i class="fa-solid fa-circle-xmark text-danger me-1"></i>'; tbody.insertAdjacentHTML('beforeend','<tr><td class="text-muted small">'+map[key]+'</td><td>'+icon+(val?val.replace(/</g,'&lt;'):'<span class="text-danger">Falta</span>')+'</td></tr>'); } }
  btnNext.addEventListener('click', ()=>{ if(!validateStep(i)) return; i=Math.min(i+1,steps.length-1); showStep(i); });
  btnBack.addEventListener('click', ()=>{ i=Math.max(i-1,0); showStep(i); });
  btnSaveDraft.addEventListener('click', ()=>{ const data=new FormData(form); const obj={}; data.forEach((v,k)=>{ if(!(v instanceof File)) obj[k]=v; }); localStorage.setItem('draft_candidato', JSON.stringify(obj)); alert('Borrador guardado.'); });
  try{ const draft=JSON.parse(localStorage.getItem('draft_candidato')||'{}'); Object.keys(draft).forEach(k=>{ const el=form.elements[k]; if(el&&el.type!=='file') el.value=draft[k]; }); }catch(e){}
  showStep(0);
})();
</script>

<?php include __DIR__.'/../includes/footer.php'; ?>
