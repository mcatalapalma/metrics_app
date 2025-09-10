<?php
// public/actions/candidatos_create.php
declare(strict_types=1);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/validators.php';

csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Método no permitido');
}

// Detectar columna teléfono real en repartidores
$cols = $pdo->query("SHOW COLUMNS FROM repartidores")->fetchAll(PDO::FETCH_COLUMN);
$hasTelefono = in_array('telefono', $cols, true);
$phoneAlt = null;
if (!$hasTelefono) {
  foreach (['phone','telefono1','tel','movil','mobile'] as $cand) {
    if (in_array($cand, $cols, true)) { $phoneAlt = $cand; break; }
  }
}
$colTelefonoSQL = $hasTelefono ? 'telefono' : ($phoneAlt ?: null);

// Validaciones
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

if ($err) {
  // Vuelve a la página anterior con mensaje; podrías usar flash en sesión si quieres algo más fino
  http_response_code(400);
  echo 'Errores: <ul><li>' . implode('</li><li>', array_map('htmlspecialchars',$err)) . '</li></ul>';
  echo '<a href="javascript:history.back()">Volver</a>';
  exit;
}

// Insert dinámico
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

// Redirigir a la lista
header('Location: /metricas/metrics_app/public/repartidores.php'); // ajusta si tu base path es distinto
exit;
