<?php
// import_juliometricas.php
// Uso: php -S localhost:8000 (y abre en el navegador) o ejecuta por CLI: php import_juliometricas.php /ruta/al/archivo.csv

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

function toDecimal($s) {
    // Convierte "12,34" -> "12.34" y cadena vacía -> "0"
    $s = trim((string)$s);
    if ($s === '') return "0";
    return str_replace(',', '.', $s);
}

function toDateISO($s) {
    // Convierte "dd/mm/yyyy" -> "yyyy-mm-dd"
    $s = trim((string)$s);
    if ($s === '') return null;
    $parts = explode('/', $s);
    if (count($parts) !== 3) return null;
    return sprintf('%04d-%02d-%02d', $parts[2], $parts[1], $parts[0]);
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Conexión fallida: ' . $e->getMessage());
}

// Crea tabla si no existe
$pdo->exec("
CREATE TABLE IF NOT EXISTS courier_metrics (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  metric_date DATE NOT NULL,
  city VARCHAR(32) NOT NULL,
  orders INT NOT NULL DEFAULT 0,
  tips DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  km DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  hours DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_date_city (metric_date, city),
  KEY idx_user_date (user_id, metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

if (php_sapi_name() === 'cli') {
    $csvPath = $argv[1] ?? null;
} else {
    // Si se usa vía web, permitir subir el archivo mediante un formulario simple
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
        $csvPath = $_FILES['csv']['tmp_name'];
    } else {
        echo '<form method="POST" enctype="multipart/form-data">';
        echo '<input type="file" name="csv" accept=".csv" required />';
        echo '<button type="submit">Importar</button>';
        echo '</form>';
        exit;
    }
}

if (!$csvPath || !file_exists($csvPath)) {
    die("No se encontró el archivo CSV.\n");
}

$handle = fopen($csvPath, 'r');
if (!$handle) {
    die("No se pudo abrir el CSV.\n");
}

// Leer cabecera
$header = fgetcsv($handle, 0, ';');
$expected = ['user','date','city','orders','tips','km','hours'];
if (!$header || array_map('strtolower', $header) !== $expected) {
    // Intento flexible: mapear por posición si la cabecera difiere en mayúsculas/minúsculas
    $headerLower = array_map('strtolower', $header ?: []);
    $mapOk = count($headerLower) === count($expected);
    if (!$mapOk) {
        die("Cabecera inesperada. Se esperaba: " . implode(',', $expected) . "\n");
    }
}

// Preparar inserción
$sql = "INSERT INTO courier_metrics (user_id, metric_date, city, orders, tips, km, hours)
        VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $pdo->prepare($sql);

$inserted = 0;
while (($row = fgetcsv($handle, 0, ';')) !== false) {
    if (count($row) < 7) continue;

    $user_id = (int)$row[0];
    $metric_date = toDateISO($row[1]);
    $city = trim($row[2]);
    $orders = (int)$row[3];
    $tips = (float)toDecimal($row[4]);
    $km = (float)toDecimal($row[5]);
    $hours = (float)toDecimal($row[6]);

    if ($metric_date === null) continue; // omitir filas corruptas

    $stmt->execute([$user_id, $metric_date, $city, $orders, $tips, $km, $hours]);
    $inserted++;
}

fclose($handle);

echo "Importadas $inserted filas.\n";

?>