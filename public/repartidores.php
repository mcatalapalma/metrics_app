<?php
declare(strict_types=1);
$pageTitle='Repartidores'; $activeMenu='repartidores';
require_once __DIR__.'/../config/db.php';
include __DIR__.'/../includes/header.php';
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)($v ?? '')); } };
$per_page=(int)($_GET['per_page']??25); if(!in_array($per_page,[25,50,100])) $per_page=25;
$page=max(1,(int)($_GET['page']??1));
$f_estado=$_GET['estado']??''; $f_city=trim($_GET['city']??'');

$sql_count="SELECT COUNT(*) FROM repartidores WHERE 1=1"; $params=[];
if($f_estado&&in_array($f_estado,['CANDIDATO','ACTIVO','INACTIVO','BAJA'],true)){ $sql_count.=" AND estado=:e"; $params[':e']=$f_estado; }
if($f_city){ $sql_count.=" AND city LIKE :c"; $params[':c']='%'.$f_city.'%'; }
$st=$pdo->prepare($sql_count); $st->execute($params); $total=(int)$st->fetchColumn();

$offset=($page-1)*$per_page;
$sql="SELECT * FROM repartidores WHERE 1=1";
if($f_estado&&in_array($f_estado,['CANDIDATO','ACTIVO','INACTIVO','BAJA'],true)) $sql.=" AND estado=:e";
if($f_city) $sql.=" AND city LIKE :c";
$sql.=" ORDER BY id DESC LIMIT :o,:l";
$st=$pdo->prepare($sql);
foreach($params as $k=>$v) $st->bindValue($k,$v);
$st->bindValue(':o',$offset,PDO::PARAM_INT); $st->bindValue(':l',$per_page,PDO::PARAM_INT);
$st->execute(); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

function estado_badge($e){ $e=strtoupper($e??''); $cls='badge-secondary';
  if($e==='CANDIDATO') $cls='badge-candidato';
  if($e==='ACTIVO') $cls='badge-activo';
  if($e==='INACTIVO') $cls='badge-inactivo';
  if($e==='BAJA') $cls='badge-baja';
  return '<span class="badge '.$cls.'">'.$e.'</span>';
}
$total_pages=max(1,ceil($total/$per_page)); $from=$offset+1; $to=min($offset+$per_page,$total);
?>
<div class="card mb-3">
  <div class="card-body d-flex justify-content-between align-items-center">
    <h1 class="h5 m-0"><i class="fa-solid fa-user-group me-2"></i>Repartidores</h1>
    <a class="btn btn-primary" href="repartidores_nuevo.php"><i class="fa-solid fa-plus me-1"></i>Nuevo Candidato</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-3" method="get" action="repartidores.php">
      <input type="hidden" name="page" value="1">
      <div class="col-md-3">
        <label class="form-label">Estado</label>
        <select name="estado" class="form-select">
          <option value="">Todos</option>
          <?php foreach(['CANDIDATO','ACTIVO','INACTIVO','BAJA'] as $opt){ $sel=$f_estado===$opt?'selected':''; echo "<option $sel>$opt</option>"; } ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">City</label>
        <input type="text" name="city" value="<?= h($f_city) ?>" class="form-control" placeholder="Palma, Ibiza…">
      </div>
      <div class="col-md-3">
        <label class="form-label">Por página</label>
        <select name="per_page" class="form-select">
          <?php foreach([25,50,100] as $n){ $sel=$per_page==$n?'selected':''; echo "<option $sel>$n</option>"; } ?>
        </select>
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <button class="btn btn-ghost w-100"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="text-muted small">Mostrando <?= (int)$from ?>–<?= (int)$to ?> de <?= (int)$total ?> (Página <?= (int)$page ?> de <?= (int)$total_pages ?>)</div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr><th>ID</th><th>Nombre</th><th>DNI</th><th>Tel</th><th>Email</th><th>City</th><th>Vehículo</th><th>Contrato</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): $id=(int)($r['id']??0); ?>
          <tr>
            <td><?= $id ?></td>
            <td><a href="repartidores_ver.php?id=<?= $id ?>" class="link-primary"><?= h(($r['nombre']??'').' '.($r['apellido']??'')) ?></a></td>
            <td><?= h($r['dni']??'') ?></td>
            <td><?= h($r['tel']??'') ?></td>
            <td><?= h($r['email']??'') ?></td>
            <td><?= h($r['city']??'') ?></td>
            <td><?= h($r['vehiculo']??'') ?></td>
            <td><?= h($r['contrato']??'') ?></td>
            <td><?= estado_badge($r['estado']??'') ?></td>
            <td><a class="btn btn-ghost btn-sm" href="repartidores_ver.php?id=<?= $id ?>"><i class="fa-regular fa-eye me-1"></i>Ver</a></td>
          </tr>
          <?php endforeach; if(empty($rows)): ?>
            <tr><td colspan="10" class="text-center muted">Sin resultados</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <nav aria-label="Paginación">
      <ul class="pagination mb-0">
        <?php $q=http_build_query(array_merge($_GET,['per_page'=>$per_page]));
        if($page>1){$prev=$page-1; echo "<li class='page-item'><a class='page-link' href='?{$q}&page={$prev}'>Anterior</a></li>";}
        for($p=1;$p<=$total_pages;$p++){ $active=$p==$page?' active':''; echo "<li class='page-item{$active}'><a class='page-link' href='?{$q}&page={$p}'>{$p}</a></li>";}
        if($page<$total_pages){$next=$page+1; echo "<li class='page-item'><a class='page-link' href='?{$q}&page={$next}'>Siguiente</a></li>";} ?>
      </ul>
    </nav>
  </div>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
