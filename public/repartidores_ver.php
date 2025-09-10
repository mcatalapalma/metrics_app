<?php
declare(strict_types=1);
$active='repartidores'; $title='Detalle de repartidor';

require __DIR__.'/../includes/header.php';
require __DIR__.'/../config/db.php';

// Helpers
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo '<div class="alert alert-danger">ID inválido.</div>';
  require __DIR__.'/../includes/footer.php'; exit;
}

/** Detectar columna de teléfono y cargar repartidor **/
$cols = $pdo->query("SHOW COLUMNS FROM repartidores")->fetchAll(PDO::FETCH_COLUMN);
$telCol = null;
foreach (['telefono','phone','telefono1','tel','movil','mobile'] as $cand) {
  if (in_array($cand, $cols, true)) { $telCol = $cand; break; }
}
$selectTelefono = $telCol ? "$telCol AS telefono" : "NULL AS telefono";

$st = $pdo->prepare("SELECT id, nombre, apellido, email, $selectTelefono, city, estado FROM repartidores WHERE id = :id LIMIT 1");
$st->execute([':id'=>$id]);
$rep = $st->fetch(PDO::FETCH_ASSOC);

if (!$rep) {
  http_response_code(404);
  echo '<div class="alert alert-warning">No se encontró el repartidor solicitado.</div>';
  require __DIR__.'/../includes/footer.php'; exit;
}

/** Intentar obtener documentos desde BD (si la tabla existe) **/
$docs = [];
$docMap = [
  'DNI_FRONT' => 'DNI/NIE - Front',
  'DNI_BACK' => 'DNI/NIE - Back',
  'PERMISO_CONDUCIR_FRONT' => 'Perm. Conducir - Front',
  'PERMISO_CONDUCIR_BACK' => 'Perm. Conducir - Back',
  'CV' => 'CV',
];

try {
  $colsDoc = $pdo->query("SHOW COLUMNS FROM repartidor_documentos")->fetchAll(PDO::FETCH_COLUMN);
  if ($colsDoc) {
    $colRepId = in_array('repartidor_id',$colsDoc,true) ? 'repartidor_id' : (in_array('courier_id',$colsDoc,true)?'courier_id':null);
    $colTipo  = in_array('tipo',$colsDoc,true) ? 'tipo' : null;
    $colFile  = in_array('file',$colsDoc,true) ? 'file' : (in_array('filename',$colsDoc,true)?'filename':(in_array('ruta',$colsDoc,true)?'ruta':(in_array('path',$colsDoc,true)?'path':null)));

    if ($colRepId && $colTipo && $colFile) {
      $sql = "SELECT $colTipo AS tipo, $colFile AS fichero FROM repartidor_documentos WHERE $colRepId=:id ORDER BY 1";
      $s2 = $pdo->prepare($sql); $s2->execute([':id'=>$id]); $docs = $s2->fetchAll(PDO::FETCH_ASSOC);
    }
  }
} catch (\Throwable $e) { /* tabla no existe o sin permisos */ }

/** Construir rutas a ficheros en uploads si los tenemos **/
$uploadsBaseFs  = dirname(__DIR__,1).'/uploads/repartidores/'.$id;
$uploadsBaseUrl = '/metricas/metrics_app/uploads/repartidores/'.$id; // ajusta si tu base cambia

function file_url_if_exists(string $pathFs, string $baseUrl, string $fileName): ?string {
  $full = $pathFs.'/'.$fileName;
  return is_file($full) ? ($baseUrl.'/'.rawurlencode($fileName)) : null;
}

// Si no hay documentos en BD, intentamos listar del filesystem
if (!$docs) {
  if (is_dir($uploadsBaseFs)) {
    $list = glob($uploadsBaseFs.'/*.*') ?: [];
    foreach ($list as $full) {
      $docs[] = ['tipo'=>basename($full, '.'.pathinfo($full, PATHINFO_EXTENSION)), 'fichero'=>basename($full)];
    }
  }
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Repartidor: <?= h(trim(($rep['nombre']??'').' '.($rep['apellido']??''))) ?></h3>
  <a class="btn btn-outline-secondary" href="repartidores.php">← Volver</a>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-8">
    <div class="card p-3">
      <h6 class="text-muted">Datos</h6>
      <div class="row">
        <div class="col-12 col-md-6">
          <div class="mb-2"><strong>Email:</strong> <?= h($rep['email'] ?? '—') ?></div>
          <div class="mb-2"><strong>Teléfono:</strong> <?= h($rep['telefono'] ?? '—') ?></div>
        </div>
        <div class="col-12 col-md-6">
          <div class="mb-2"><strong>Ciudad:</strong> <span class="badge badge-soft"><?= h($rep['city'] ?? '—') ?></span></div>
          <div class="mb-2"><strong>Estado:</strong> <?= h($rep['estado'] ?? '—') ?></div>
        </div>
      </div>
    </div>

    <div class="card p-3 mt-3">
      <h6 class="mb-2">Documentos</h6>
      <?php if (!$docs): ?>
        <div class="text-muted">No hay documentos registrados.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Tipo</th><th>Archivo</th><th class="text-end">Acción</th></tr></thead>
            <tbody>
            <?php foreach ($docs as $d):
              $tipo = (string)($d['tipo'] ?? '');
              $label = $docMap[$tipo] ?? $tipo;
              $file  = (string)($d['fichero'] ?? '');
              $url   = $file ? file_url_if_exists($uploadsBaseFs, $uploadsBaseUrl, $file) : null;
            ?>
              <tr>
                <td><?= h($label ?: 'Documento') ?></td>
                <td><?= h($file ?: '—') ?></td>
                <td class="text-end">
                  <?php if ($url): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= h($url) ?>" target="_blank" rel="noopener">Ver/Descargar</a>
                  <?php else: ?>
                    <span class="text-muted">No disponible</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card p-3">
      <h6 class="mb-2">Acciones rápidas</h6>
      <div class="d-grid gap-2">
        <!-- Botones placeholder para próximas iteraciones -->
        <button class="btn btn-outline-primary" disabled>Editar (próximamente)</button>
        <button class="btn btn-outline-danger" disabled>Dar de baja (próximamente)</button>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__.'/../includes/footer.php'; ?>
