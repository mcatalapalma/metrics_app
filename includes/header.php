<?php
// includes/header.php
// Variables esperadas por cada vista:
// $active = 'dashboard' | 'repartidores' | 'cuentas' | 'admin' | 'ajustes' | 'reportes'
// $title  = 'Título de la página'
if (!isset($active)) { $active = ''; }
if (!isset($title))  { $title  = 'Inicio'; }

// Helper de escape por si no existe (evita warnings)
if (!function_exists('h')) {
  function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Si sirves el proyecto desde DocumentRoot=public, usa rutas relativas (index.php, repartidores.php, ...)
// Si lo sirves desde /metrics_app/public como subcarpeta, también funcionan.
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h($title) ?> · Metrics App</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/app.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold text-primary" href="index.php">Metrics App</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link <?= $active==='dashboard'?'active':'' ?>" href="index.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?= $active==='repartidores'?'active':'' ?>" href="repartidores.php">Repartidores</a></li>
        <li class="nav-item"><a class="nav-link <?= $active==='cuentas'?'active':'' ?>" href="cuentas_glovo.php">Cuentas Glovo</a></li>
        <li class="nav-item"><a class="nav-link <?= $active==='admin'?'active':'' ?>" href="administracion.php">Administración</a></li>
        <li class="nav-item"><a class="nav-link <?= $active==='ajustes'?'active':'' ?>" href="ajustes.php">Ajustes</a></li>
        <li class="nav-item"><a class="nav-link <?= $active==='reportes'?'active':'' ?>" href="reportes.php">Reportes</a></li>
      </ul>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="resumen.php">Resumen</a>
      </div>
    </div>
  </div>
</nav>
<main class="py-4">
  <div class="container">
