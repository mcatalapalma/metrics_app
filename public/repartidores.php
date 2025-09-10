<?php
declare(strict_types=1);
$active='repartidores'; $title='Repartidores';

require __DIR__.'/../includes/header.php';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
require __DIR__.'/../includes/validators.php';
require __DIR__.'/../includes/pagination.php';

/** Detectar columnas reales de la tabla repartidores **/
$cols = $pdo->query("SHOW COLUMNS FROM repartidores")->fetchAll(PDO::FETCH_COLUMN);
$hasTelefono = in_array('telefono', $cols, true);
$phoneAlt = null;
if (!$hasTelefono) {
  foreach (['phone','telefono1','tel','movil','mobile'] as $cand) {
    if (in_array($cand, $cols, true)) { $phoneAlt = $cand; break; }
  }
}
$colTelefonoSQL = $hasTelefono ? 'telefono' : ($phoneAlt ?: null);

// ---------- Filtros ----------
$estado = $_GET['estado'] ?? 'ALL'; // ACTIVO | INACTIVO | BAJA | CANDIDATO | ALL
$city   = $_GET['city']   ?? 'ALL';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 20;

$estados = [
  'ALL'=>'Todos','ACTIVO'=>'Activo','INACTIVO'=>'Inactivo','BAJA'=>'Baja','CANDIDATO'=>'Candidato'
];

// Derivar ciudades desde datos si no tienes tabla cities
$cities = $pdo->query("SELECT DISTINCT city FROM repartidores WHERE city IS NOT NULL AND city<>'' ORDER BY city")
              ->fetchAll(PDO::FETCH_COLUMN);

// ---------- Insert/Update (crear) ----------
csrf_verify();
$flash = null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $op = $_POST['op'] ?? '';
  if ($op==='add') {
    $nombre   = v_text($_POST['nombre'] ?? '', 80);
    $apellido = v_text($_POST['apellido'] ?? '', 120);
    $email    = v_email($_POST['email'] ?? '');
    $telInput = $_POST['telefono'] ?? '';
    $tel      = v_phone($telInput);
    $city_i   = v_text($_POST['city'] ?? '', 60);
    $estado_i = $_POST['estado'] ?? 'CANDIDATO';

    $err = [];
    if (!$nombre)  { $err[]='Nombre requerido'; }
    if ($email===null && isset($_POST['email']) && $_POST['email']!=='') { $err[]='Email no válido'; }
    if ($colTelefonoSQL && $tel===null && $telInput!=='') { $err[]='Teléfono no válido'; }

    if (empty($err)) {
      // Construir INSERT dinámico según columnas disponibles
      $fields = ['nombre','apellido','email','city','estado'];
      $values = [$nombre,$apellido,$email,$city_i,$estado_i];

      if ($colTelefonoSQL) {
        $fields[] = $colTelefonoSQL;
        $values[] = $tel;
      }

      $placeholders = implode(',', array_fill(0, count($fields), '?'));
      $sql = "INSERT INTO repartidores (".implode(',', $fields).") VALUES ($placeholders)";
      $st = $pdo->prepare($sql);
      $st->execute($values);

      $flash='Repartidor creado correctamente.';
      header('Location: repartidores.php'); exit;
    } else {
      echo '<div class="alert alert-danger mt-3"><ul class="mb-0">';
      foreach ($err as $e) echo '<li>'.htmlspecialchars($e, ENT_QUOTES, 'UTF-8').'</li>';
      echo '</ul></div>';
    }
  }
}

// ---------- Listado ----------
$where = [];
$params = [];
if ($estado !== 'ALL') { $where[] = "estado = :estado"; $params[':estado']=$estado; }
if ($city   !== 'ALL') { $where[] = "city = :city";     $params[':city']=$city; }
if ($q!=='') {
  $busca = "(nombre LIKE :q OR apellido LIKE :q OR email LIKE :q";
  if ($colTelefonoSQL) { $busca .= " OR $colTelefonoSQL LIKE :q"; }
  $busca .= ")";
  $where[] = $busca;
  $params[':q'] = "%$q%";
}
$sqlWhere = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// total
$st = $pdo->prepare("SELECT COUNT(*) FROM repartidores $sqlWhere");
$st->execute($params);
$total = (int)$st->fetchColumn();

$pg = paginate($total, $page, $per);

// datos: aliasar teléfono si existe; si no, devolver NULL AS telefono
$selectTelefono = $colTelefonoSQL ? "$colTelefonoSQL AS telefono" : "NULL AS telefono";

$st = $pdo->prepare("
  SELECT id, nombre, apellido, email, $selectTelefono, city, estado
  FROM repartidores
  $sqlWhere
  ORDER BY nombre, apellido
  LIMIT :lim OFFSET :off
");
foreach ($params as $k=>$v) { $st->bindValue($k, $v); }
$st->bindValue(':lim', $pg['limit'], PDO::PARAM_INT);
$st->bindValue(':off', $pg['offset'], PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// QS para paginación
$qs = ['estado'=>$estado,'city'=>$city,'q'=>$q];
?>
<div class="row g-3 align-items-end">
  <div class="col-12 col-lg-9">
    <form class="row g-2">
      <div class="col-12 col-sm-3">
        <label class="form-label">Estado</label>
        <select name="estado" class="form-select">
          <?php foreach($estados as $k=>$label): ?>
            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= $estado===$k?'selected':'' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-sm-3">
        <label class="form-label">Ciudad</label>
        <select name="city" class="form-select">
          <option value="ALL" <?= $city==='ALL'?'selected':'' ?>>Todas</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>" <?= $city===$c?'selected':'' ?>>
              <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-sm-4">
        <label class="form-label">Buscar</label>
        <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="Nombre, email<?= $colTelefonoSQL?', teléfono':''; ?>...">
      </div>
      <div class="col-12 col-sm-2">
        <label class="form-label">&nbsp;</label>
        <button class="btn btn-primary w-100">Filtrar</button>
      </div>
    </form>
  </div>
  <div class="col-12 col-lg-3 text-lg-end">
  <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAdd">+ Nuevo Candidato</button>
</div>

</div>

<?php if (!empty($flash)): ?>
  <div class="alert alert-success mt-3"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card p-3 mt-3">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Nombre</th><th>Email</th><th><?= $colTelefonoSQL?'Teléfono':'Teléfono (n/d)'; ?></th><th>Ciudad</th><th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5" class="text-center text-muted">Sin resultados</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars(trim(($r['nombre']??'').' '.($r['apellido']??'')), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($r['email'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($r['telefono'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
            <td><span class="badge badge-soft"><?= htmlspecialchars($r['city'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><?= htmlspecialchars($r['estado'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="d-flex justify-content-between align-items-center">
    <div class="small text-muted">Total: <?= number_format($total) ?></div>
    <?= render_pagination($pg['pages'], $pg['page'], $qs) ?>
  </div>
</div>

<!-- Modal nuevo repartidor -->
<!-- Modal: Nuevo Candidato -->
<div class="modal fade" id="modalAdd" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nuevo Candidato</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
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
                    No encuentro <code>views/partials/candidatos/form.php</code>. ¿Lo creo por ti?
                  </div>';
          }
        ?>
      </div>
    </div>
  </div>
</div>



<?php require __DIR__.'/../includes/footer.php'; ?>
