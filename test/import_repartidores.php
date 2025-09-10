<?php
// import_repartidores.php
// Permite subir un XLSX/CSV y cargarlo a la tabla 'repartidores'.
// Requiere PHP >= 7.4 y, para XLSX, la librería PhpSpreadsheet (composer require phpoffice/phpspreadsheet).
// Si no tienes Composer, guarda desde Excel a CSV (;) y también funciona.

$host = '127.0.0.1';
$db   = 'metrics_app';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Conexión fallida: ' . $e->getMessage());
}

// Crear tablas si no existen (idénticas al SQL de creación)
$pdo->exec("
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
");

function toDateISO($s) {
    $s = trim((string)$s);
    if ($s === '') return null;
    // Aceptar formatos comunes
    $ts = strtotime(str_replace('/', '-', $s));
    if ($ts === false) return null;
    return date('Y-m-d', $ts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['file'])) {
        die('No se subió archivo.');
    }
    $tmp = $_FILES['file']['tmp_name'];
    $name = $_FILES['file']['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    $rows = [];
    if ($ext === 'csv') {
        if (($h = fopen($tmp, 'r')) !== false) {
            $header = fgetcsv($h, 0, ';');
            while (($r = fgetcsv($h, 0, ';')) !== false) {
                $rows[] = $r;
            }
            fclose($h);
        }
    } else {
        // Intentar XLSX con PhpSpreadsheet
        require_once __DIR__ . '/vendor/autoload.php';
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp);
        $spreadsheet = $reader->load($tmp);
        $sheet = $spreadsheet->getActiveSheet();
        $header = [];
        $first = true;
        foreach ($sheet->toArray(null, true, true, true) as $row) {
            if ($first) { $header = array_values($row); $first = false; continue; }
            $rows[] = array_values($row);
        }
    }

    // Normalizar cabecera -> índices
    $map = ['nombre','dni','ss','tel','iban','vehiculo','contrato','f_alta','email','notas'];
    $headerLower = array_map(fn($x)=>strtolower(trim((string)$x)), $header ?? []);
    $idx = [];
    foreach ($map as $m) {
        $pos = array_search($m, $headerLower, true);
        $idx[$m] = $pos === false ? null : $pos;
    }

    $sql = "INSERT INTO repartidores (nombre,dni,ss,tel,iban,vehiculo,contrato,f_alta,email,notas)
            VALUES (?,?,?,?,?,?,?,?,?,?)";
    $stmt = $pdo->prepare($sql);

    $inserted = 0;
    foreach ($rows as $r) {
        $vals = [];
        foreach ($map as $m) {
            $pos = $idx[$m];
            $vals[$m] = $pos===null ? null : (isset($r[$pos]) ? trim((string)$r[$pos]) : null);
        }
        $vals['f_alta'] = toDateISO($vals['f_alta'] ?? '');

        // nombre es obligatorio
        if (($vals['nombre'] ?? '') === '') continue;

        $stmt->execute([
            $vals['nombre'] ?? null,
            $vals['dni'] ?? null,
            $vals['ss'] ?? null,
            $vals['tel'] ?? null,
            $vals['iban'] ?? null,
            $vals['vehiculo'] ?? null,
            $vals['contrato'] ?? null,
            $vals['f_alta'] ?? null,
            $vals['email'] ?? null,
            $vals['notas'] ?? null,
        ]);
        $inserted++;
    }

    echo "Importadas $inserted filas en 'repartidores'.";
    exit;
}

?>
<!doctype html>
<html>
  <head><meta charset="utf-8"><title>Importar repartidores</title></head>
  <body>
    <h1>Importar repartidores</h1>
    <form method="POST" enctype="multipart/form-data">
      <input type="file" name="file" accept=".csv,.xlsx" required>
      <button type="submit">Subir e importar</button>
    </form>
    <p>Para CSV, usa separador ';' y cabecera: nombre;dni;ss;tel;iban;vehiculo;contrato;f_alta;email;notas</p>
  </body>
</html>
