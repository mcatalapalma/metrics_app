<?php
// import_cuentas_glovo.php
// Uso CLI: php import_cuentas_glovo.php /ruta/al/cuentas_glovo.csv
// Uso web: php -S localhost:8000  y abrir /import_cuentas_glovo.php para subir el CSV
// CSV esperado (separador ';'): id_gd;id_glovo;pass

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

// Crear tabla si no existe
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
");

function loadCsvRows($path) {
    $rows = [];
    if (!file_exists($path)) {
        throw new RuntimeException("No existe el archivo: $path");
    }
    if (($h = fopen($path, 'r')) === false) {
        throw new RuntimeException("No se pudo abrir el archivo: $path");
    }
    $header = fgetcsv($h, 0, ';');
    if (!$header) {
        fclose($h);
        throw new RuntimeException("CSV vacío o sin cabecera.");
    }
    $expected = ['id_gd','id_glovo','pass'];
    $lower = array_map(fn($x)=>strtolower(trim((string)$x)), $header);
    if ($lower !== $expected) {
        fclose($h);
        throw new RuntimeException("Cabecera inválida. Esperada: id_gd;id_glovo;pass");
    }
    while (($r = fgetcsv($h, 0, ';')) !== false) {
        $rows[] = $r;
    }
    fclose($h);
    return $rows;
}

function importRows($pdo, $rows) {
    $sql = "INSERT INTO cuentas_glovo (id_gd, id_glovo, pass)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
              id_glovo = VALUES(id_glovo),
              pass = VALUES(pass)";
    $stmt = $pdo->prepare($sql);
    $ok = 0; $fail = 0; $errors = [];
    foreach ($rows as $i => $r) {
        $id_gd = isset($r[0]) ? (int)$r[0] : 0;
        $id_glovo = isset($r[1]) ? trim((string)$r[1]) : null;
        $pwd = isset($r[2]) ? trim((string)$r[2]) : null;

        if ($id_gd <= 0 || $pwd === null || $pwd === '') {
            $fail++;
            $errors[] = "Fila ".($i+2).": datos incompletos (id_gd o pass inválidos)";
            continue;
        }
        try {
            $stmt->execute([$id_gd, $id_glovo, $pwd]);
            $ok++;
        } catch (Throwable $e) {
            $fail++;
            $errors[] = "Fila ".($i+2).": ".$e->getMessage();
        }
    }
    return [$ok, $fail, $errors];
}

if (php_sapi_name() === 'cli') {
    $csv = $argv[1] ?? null;
    if (!$csv) {
        fwrite(STDERR, "Uso: php import_cuentas_glovo.php /ruta/al/cuentas_glovo.csv\n");
        exit(1);
    }
    try {
        $rows = loadCsvRows($csv);
        [$ok, $fail, $errors] = importRows($pdo, $rows);
        echo "Importación completada. OK=$ok, Fallos=$fail\n";
        if ($fail) {
            echo "Errores:\n" . implode("\n", $errors) . "\n";
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "Error: ".$e->getMessage()."\n");
        exit(1);
    }
    exit;
}

// Modo web
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['csv'])) {
        echo "No se subió archivo.";
        exit;
    }
    $tmp = $_FILES['csv']['tmp_name'];
    try {
        $rows = loadCsvRows($tmp);
        [$ok, $fail, $errors] = importRows($pdo, $rows);
        echo "<p>Importación completada. OK=$ok, Fallos=$fail</p>";
        if ($fail) {
            echo "<pre>".htmlspecialchars(implode("\n", $errors))."</pre>";
        }
    } catch (Throwable $e) {
        echo "<p>Error: ".htmlspecialchars($e->getMessage())."</p>";
    }
    exit;
}

?><!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Importar cuentas_glovo</title></head>
<body>
  <h1>Importar cuentas_glovo</h1>
  <form method="POST" enctype="multipart/form-data">
    <input type="file" name="csv" accept=".csv" required>
    <button type="submit">Subir e importar</button>
  </form>
  <p>Formato CSV (separador ';'): <code>id_gd;id_glovo;pass</code></p>
</body>
</html>
