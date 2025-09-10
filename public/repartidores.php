<?php
  require_once __DIR__ . '/../includes/view.php';
  $partial = __DIR__ . '/../views/partials/candidatos/form.php';
  if (is_file($partial)) {
    render_partial($partial, [
      'cities'  => $cities,
      'estados' => ['CANDIDATO'=>'Candidato','ACTIVO'=>'Activo','INACTIVO'=>'Inactivo','BAJA'=>'Baja']
    ]);
  } else {
    echo '<div class="alert alert-warning">
            No encuentro <code>views/partials/candidatos/form.php</code>.
          </div>';
  }
?>
