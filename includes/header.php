<?php
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle ?? 'Entregalia Palma') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{
      --brand:#DE4C8A; --bg:#f6f8fb; --card:#ffffff; --text:#111827;
      --muted:#6b7280; --border:#e5e7eb; --ring:rgba(222,76,138,.25);
      --sidebar-w: 260px;
    }
    *{ box-sizing:border-box; }
    body{ background:var(--bg); color:var(--text); }
    .topbar{ position:sticky; top:0; z-index:1030; border-bottom:1px solid var(--border); background:#fff; }
    .brand{ color:var(--text); text-decoration:none; font-weight:700; }
    .btn-primary{ background:var(--brand); border-color:var(--brand); }
    .btn-primary:hover{ filter:brightness(.95); }
    .btn-ghost{ border:1px solid var(--border); background:#fff; color:#374151; }
    .btn-ghost:hover{ background:#f9fafb; }
    .card{ background:var(--card); border:1px solid var(--border); border-radius:16px;
           box-shadow:0 1px 2px rgba(17,24,39,.04), 0 8px 24px rgba(17,24,39,.06); }
    .card > .card-body{ padding:16px 16px; }
    .form-control,.form-select{ border-radius:12px; border-color:var(--border); }
    .form-control:focus,.form-select:focus{ border-color:var(--brand); box-shadow:0 0 0 .25rem var(--ring); }
    .table{ --bs-table-bg:transparent; }
    .table-hover tbody tr:hover{ background:#fafafa; }
    .table thead th{ background:#fafbfe; }
    .muted{ color:var(--muted); }

    .layout{ display:flex; }
    .sidebar{ width:var(--sidebar-w); flex:0 0 var(--sidebar-w); border-right:1px solid var(--border); background:#fff; min-height:calc(100vh - 56px); position:sticky; top:56px; }
    .sidebar .section-title{ font-size:.75rem; letter-spacing:.08em; color:#6b7280; margin:16px 20px 8px; text-transform:uppercase; }
    .menu{ list-style:none; padding:8px 8px 16px; margin:0; }
    .menu a{ display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; color:#374151; text-decoration:none; margin:4px 8px; }
    .menu a.active, .menu a:hover{ background:#f8f0f4; color:#7A103D; }
    .main{ flex:1; padding:16px; }

    .badge-candidato{ background:#EFB2CB; color:#7A103D; }
    .badge-activo{ background:#DCFCE7; color:#14532D; }
    .badge-inactivo{ background:#F3F4F6; color:#374151; }
    .badge-baja{ background:#FEE2E2; color:#7F1D1D; }

    @media (max-width: 992px){
      .sidebar{ position:fixed; top:56px; left:-100%; height:calc(100vh - 56px); z-index:1031; transition:left .2s ease; }
      .sidebar.show{ left:0; }
      .overlay{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.25); z-index:1030; }
      .overlay.show{ display:block; }
      .main{ padding:16px; }
    }
  </style>
</head>
<body>

<nav class="navbar topbar navbar-expand-lg">
  <div class="container-fluid">
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-ghost d-lg-none" id="btnSidebar"><i class="fa-solid fa-bars"></i></button>
      <a href="/public/index.php" class="brand">Entregalia Palma</a>
    </div>
    <div class="d-flex align-items-center gap-3">
      <i class="fa-regular fa-bell"></i>
      <i class="fa-regular fa-envelope"></i>
      <div class="dropdown">
        <a href="#" class="text-decoration-none text-dark dropdown-toggle" data-bs-toggle="dropdown">
          <i class="fa-regular fa-user me-1"></i> <?= htmlspecialchars($currentUserName ?? 'Usuario') ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="#"><i class="fa-solid fa-gear me-2"></i> Ajustes</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="#"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="section-title">Menú</div>
    <ul class="menu">
      <li><a class="<?= ($activeMenu??'')==='dashboard'?'active':'' ?>" href="index.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
      <li><a class="<?= ($activeMenu??'')==='repartidores'?'active':'' ?>" href="repartidores.php"><i class="fa-solid fa-user-group"></i> Repartidores</a></li>
    </ul>
  </aside>
  <div class="overlay" id="overlay"></div>
  <main class="main">
