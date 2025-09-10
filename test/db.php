<?php
// db.php - configuración de PDO
$host = 'localhost';   // cámbialo si usas otro host
$db   = 'metrics_app'; // base de datos
$user = 'root';        // usuario
$pass = '';            // contraseña
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
    die('No se pudo conectar a MySQL: ' . $e->getMessage());
}
