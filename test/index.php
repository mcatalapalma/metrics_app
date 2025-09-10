<?php
require_once __DIR__ . '/db.php';

/* =========================
   Seguridad / utilidades
   ========================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function dec($s){ return (float)str_replace(',', '.', trim((string)$s)); }
function intv($s){ return (int)preg_replace('/[^\d\-]/', '', (string)$s); }

/* =========================
   Asegurar tablas
   ========================= */
$pdo->exec("
CREATE TABLE IF NOT EXISTS repartidores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre   VARCHAR(120) NOT NULL,
  dni      VARCHAR(32)  DEFAULT NULL,
  ss       VARCHAR(32)  DEFAULT NULL,
  tel      VARCHAR(32)  DEFAULT NULL,
  iban     VARCHAR(34)  DEFAULT NULL,
  vehiculo VARCHAR(50)  DEFAULT NULL,
  contrato VARCHAR(50)  DEFAULT NULL,
  f_alta   DATE         DEFAULT NULL,
  email    VARCHAR(255) DEFAULT NULL,
  notas    TEXT         DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_nombre (nombre),
  KEY idx_dni (dni),
  KEY idx_tel (tel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cuentas_glovo (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_gd INT NOT NULL,
  id_glovo VARCHAR(64) DEFAULT NULL,
  email VARCHAR(255) AS (CONCAT('gestdriver2025+', id_gd, '@gmail.com')) STORED,
  pass VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_id_gd (id_gd),
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehiculo VARCHAR(20) NOT NULL,
  contrato INT NOT NULL,
  base DECIMAL(10,2) NOT NULL,
  p_min INT NOT NULL,
  p_extra INT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tipo (vehiculo, contrato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* =========================
   POST: cuentas_glovo
   ========================= */
$message_glovo = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_cuenta_glovo') {
    $id_gd = intv($_POST['id_gd'] ?? 0);
    $id_glovo = trim((string)($_POST['id_glovo'] ?? ''));
    $pass = trim((string)($_POST['pass'] ?? ''));
    if ($id_gd <= 0 || $pass === '') {
        $message_glovo = ['type'=>'error', 'text'=>'id_gd y pass son obligatorios.'];
    } else {
        $sql = "INSERT INTO cuentas_glovo (id_gd, id_glovo, pass)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE id_glovo = VALUES(id_glovo), pass = VALUES(pass)";
        $pdo->prepare($sql)->execute([$id_gd, $id_glovo, $pass]);
        $message_glovo = ['type'=>'ok', 'text'=>'Cuenta guardada/actualizada.'];
    }
}

/* =========================
   POST: bases (crear/actualizar/borrar)
   ========================= */
$message_bases = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'guardar_base') {
        $vehiculo = strtoupper(trim((string)($_POST['vehiculo'] ?? '')));
        $contrato = intv($_POST['contrato'] ?? 0);
        $baseSal  = dec($_POST['base'] ?? 0);
        $p_min    = intv($_POST['p_min'] ?? 0);
        $p_extra  = intv($_POST['p_extra'] ?? 0);

        if ($vehiculo === '' || $contrato <= 0) {
            $message_bases = ['type'=>'error', 'text'=>'Vehículo y contrato son obligatorios.'];
        } else {
            $sql = "INSERT INTO bases (vehiculo, contrato, base, p_min, p_extra)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE base=VALUES(base), p_min=VALUES(p_min), p_extra=VALUES(p_extra)";
            $pdo->prepare($sql)->execute([$vehiculo, $contrato, $baseSal, $p_min, $p_extra]);
            $message_bases = ['type'=>'ok', 'text'=>"Base guardada para $vehiculo / $contrato h."];
        }
    } elseif ($_POST['accion'] === 'borrar_base') {
        $vehiculo = strtoupper(trim((string)($_POST['vehiculo'] ?? '')));
        $contrato = intv($_POST['contrato'] ?? 0);
        if ($vehiculo !== '' && $contrato > 0) {
            $pdo->prepare("DELETE FROM bases WHERE vehiculo=? AND contrato=?")->execute([$vehiculo, $contrato]);
            $message_bases = ['type'=>'ok', 'text'=>"Eliminado $vehiculo / $contrato h."];
        } else {
            $message_bases = ['type'=>'error', 'text'=>'Faltan datos para borrar.'];
        }
    }
}

/* =========================
   GET: Repartidores búsqueda + páginas
   ========================= */
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE (nombre LIKE ? OR dni LIKE ? OR tel LIKE ?)";
    $like = "%" . $q . "%";
    $params = [$like, $like, $like];
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM repartidores $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $per_page));

$sql = "SELECT id, nombre, dni, tel, vehiculo, contrato, DATE_FORMAT(f_alta, '%Y-%m-%d') AS f_alta, email
        FROM repartidores
        $where
        ORDER BY nombre ASC
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$repartidores = $stmt->fetchAll();

$cuentas = $pdo->query("SELECT id, id_gd, id_glovo, email FROM cuentas_glovo ORDER BY id_gd ASC")->fetchAll();
$bases = $pdo->query("SELECT vehiculo, contrato, base, p_min, p_extra FROM bases ORDER BY vehiculo ASC, contrato DESC")->fetchAll();

?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Panel | Repartidores, Cuentas, Bases</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    :root{--bg:#0f172a;--card:#111827;--muted:#94a3b8;--text:#e5e7eb;--accent:#22c55e;--danger:#ef4444;}
    *{box-sizing:border-box;font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial;}
    body{margin:0;background:linear-gradient(135deg,#0b1022,#0f172a 40%,#0b1022);color:var(--text);}
    header{padding:20px 16px;border-bottom:1px solid #1f2937;background:rgba(17,24,39,.6);backdrop-filter:saturate(180%) blur(10px);position:sticky;top:0;z-index:10;}
    h1{margin:0;font-size:20px;font-weight:600;letter-spacing:.3px}
    .container{max-width:1250px;margin:24px auto;padding:0 16px;display:grid;grid-template-columns:1.1fr .9fr;gap:20px}
    .card{background:radial-gradient(80% 140% at 10% 10%,rgba(34,197,94,.14),transparent 40%),#0b1220;border:1px solid #1f2937;border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,.35);padding:18px}
    .card h2{margin:0 0 14px 0;font-size:16px}
    .muted{color:var(--muted);font-size:13px}
    .row{display:flex;gap:8px;align-items:center}
    input,button,select{border-radius:10px;border:1px solid #253247;background:#0b1325;color:var(--text);padding:10px 12px;font-size:14px}
    input,select{outline:none}
    button{cursor:pointer}
    .btn{background:linear-gradient(180deg,#16a34a,#15803d);border-color:#14532d}
    .btn:hover{filter:saturate(1.2) brightness(1.05)}
    .btn-danger{background:linear-gradient(180deg,#ef4444,#b91c1c);border-color:#7f1d1d}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{padding:10px;border-bottom:1px solid #1f2937;text-align:left;font-size:13px}
    th{color:#cbd5e1;font-weight:600}
    .pill{background:#0a172a;border:1px solid #1f2937;border-radius:999px;padding:2px 8px;font-size:12px;color:#cbd5e1}
    .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
    .message{margin-bottom:10px;padding:10px;border-radius:10px}
    .ok{background:rgba(34,197,94,.1);border:1px solid #14532d}
    .error{background:rgba(239,68,68,.1);border:1px solid #7f1d1d}
    nav{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
    nav a{padding:8px 10px;border:1px solid #1f2937;border-radius:8px;text-decoration:none;color:#d1d5db}
    nav a.active{background:#111827}
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:18px}
  </style>
</head>
<body>
<header><h1>Gestión de Repartidores • Cuentas Glovo • Bases</h1></header>

<div class="container">

  <!-- Repartidores -->
  <section class="card">
    <h2>Repartidores</h2>
    <form method="get" class="row">
      <input type="text" name="q" placeholder="Buscar por nombre, DNI o teléfono" value="<?php echo h($q); ?>">
      <button class="btn" type="submit">Buscar</button>
    </form>
    <p class="muted"><?php echo $total; ?> resultado(s) • página <?php echo $page; ?> de <?php echo $pages; ?></p>
    <div style="overflow:auto;border-radius:12px;border:1px solid #1f2937">
    <table>
      <thead>
        <tr>
          <th>Nombre</th><th>DNI</th><th>Tel</th><th>Vehículo</th><th>Contrato</th><th>Alta</th><th>Email</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($repartidores as $r): ?>
          <tr>
            <td><?php echo h($r['nombre']); ?></td>
            <td><?php echo h($r['dni']); ?></td>
            <td><?php echo h($r['tel']); ?></td>
            <td><span class="pill"><?php echo h($r['vehiculo']); ?></span></td>
            <td><?php echo h($r['contrato']); ?></td>
            <td><?php echo h($r['f_alta']); ?></td>
            <td><?php echo h($r['email']); ?></td>
          </tr>
        <?php endforeach; if (!$repartidores): ?>
          <tr><td colspan="7" class="muted">Sin resultados.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
    <nav>
      <?php $base = '?q=' . urlencode($q) . '&page=';
        for ($p=1; $p <= $pages; $p++) {
          $cls = $p === $page ? 'active' : '';
          echo '<a class="'.$cls.'" href="'.$base.$p.'">'.$p.'</a>';
        } ?>
    </nav>
  </section>

  <!-- Columna derecha: Cuentas y Bases -->
  <aside class="two-col">
    <!-- Cuentas Glovo -->
    <div class="card">
      <h2>Cuentas Glovo</h2>
      <?php if ($message_glovo): ?>
        <div class="message <?php echo $message_glovo['type']==='ok'?'ok':'error'; ?>"><?php echo h($message_glovo['text']); ?></div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="accion" value="guardar_cuenta_glovo">
        <div class="grid">
          <div><label>ID GD<br><input type="number" name="id_gd" min="1" required></label></div>
          <div><label>ID Glovo<br><input type="text" name="id_glovo" placeholder="Opcional"></label></div>
          <div><label>Contraseña<br><input type="text" name="pass" required></label></div>
          <div><label>Email (auto)<br><input type="text" disabled id="email_preview" placeholder="gestdriver2025+IDGD@gmail.com"></label></div>
        </div>
        <div style="margin-top:10px"><button class="btn" type="submit">Guardar / Actualizar</button></div>
      </form>
      <p class="muted" style="margin-top:10px">Email: <code>gestdriver2025+&lt;id_gd&gt;@gmail.com</code></p>
      <div style="max-height:260px;overflow:auto;border-radius:12px;border:1px solid #1f2937;margin-top:10px">
        <table>
          <thead><tr><th>ID GD</th><th>ID Glovo</th><th>Email</th></tr></thead>
          <tbody>
            <?php foreach ($cuentas as $c): ?>
              <tr><td><?php echo h($c['id_gd']); ?></td><td><?php echo h($c['id_glovo']); ?></td><td><?php echo h($c['email']); ?></td></tr>
            <?php endforeach; if (!$cuentas): ?>
              <tr><td colspan="3" class="muted">No hay cuentas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Bases -->
    <div class="card">
      <h2>Bases</h2>
      <?php if ($message_bases): ?>
        <div class="message <?php echo $message_bases['type']==='ok'?'ok':'error'; ?>"><?php echo h($message_bases['text']); ?></div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="accion" value="guardar_base">
        <div class="grid">
          <div><label>Vehículo<br>
            <select name="vehiculo" required>
              <option value="">-- Elige --</option>
              <?php foreach (['COCHE','MOTO','BICI','PETINETE'] as $v) echo "<option>$v</option>"; ?>
            </select>
          </label></div>
          <div><label>Contrato (h)<br><input type="number" name="contrato" min="1" required></label></div>
          <div><label>Base (€)<br><input type="text" name="base" placeholder="Ej: 707,5 o 707.5" required></label></div>
          <div><label>P. mínimo<br><input type="number" name="p_min" min="0" required></label></div>
          <div><label>P. extra<br><input type="number" name="p_extra" min="0" required></label></div>
        </div>
        <div style="margin-top:10px"><button class="btn" type="submit">Guardar / Actualizar</button></div>
      </form>

      <div style="max-height:260px;overflow:auto;border-radius:12px;border:1px solid #1f2937;margin-top:12px">
        <table>
          <thead><tr><th>Vehículo</th><th>Contrato</th><th>Base (€)</th><th>P. mín</th><th>P. extra</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($bases as $b): ?>
              <tr>
                <td><?php echo h($b['vehiculo']); ?></td>
                <td><?php echo h($b['contrato']); ?></td>
                <td><?php echo number_format((float)$b['base'], 2, ',', '.'); ?></td>
                <td><?php echo h($b['p_min']); ?></td>
                <td><?php echo h($b['p_extra']); ?></td>
                <td>
                  <form method="post" onsubmit="return confirm('¿Eliminar <?php echo h($b['vehiculo']); ?> / <?php echo h($b['contrato']); ?> h?');" style="display:inline">
                    <input type="hidden" name="accion" value="borrar_base">
                    <input type="hidden" name="vehiculo" value="<?php echo h($b['vehiculo']); ?>">
                    <input type="hidden" name="contrato" value="<?php echo h($b['contrato']); ?>">
                    <button class="btn-danger" type="submit">Borrar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; if (!$bases): ?>
              <tr><td colspan="6" class="muted">Aún no hay bases.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </aside>

</div>

<script>
// Vista previa email autogenerado
const idInput = document.querySelector('input[name="id_gd"]');
const prev = document.getElementById('email_preview');
function updateEmail(){ const v = parseInt((idInput?.value||'0'),10); prev && (prev.value = v>0 ? `gestdriver2025+${v}@gmail.com` : ''); }
idInput && idInput.addEventListener('input', updateEmail); updateEmail();
</script>
</body>
</html>
