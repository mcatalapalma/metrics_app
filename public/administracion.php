<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/helpers.php';
include __DIR__.'/../includes/header.php';

$msg = null;

// ====== IMPORTACIÓN DE MÉTRICAS (robusta con sniff de delimitador) ======
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['file'])){
  $name=$_FILES['file']['name']; $tmp=$_FILES['file']['tmp_name'];
  $ext=strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $dest=__DIR__."/../uploads/metrics/".date('Ymd_His')."_".$name;
  if(!is_dir(__DIR__."/../uploads/metrics")) mkdir(__DIR__."/../uploads/metrics",0777,true);

  if(move_uploaded_file($tmp,$dest)){
    $rows=[]; $header=[];

    if($ext==='csv'){
      // Leer archivo entero para detectar separador (coma o punto y coma) y manejar BOM
      $raw = file_get_contents($dest);
      if (substr($raw,0,3) === "\xEF\xBB\xBF") $raw = substr($raw,3); // quitar BOM UTF-8
      $lines = preg_split("/\r\n|\n|\r/", $raw);
      $first = isset($lines[0]) ? $lines[0] : '';
      $countComma = substr_count($first, ',');
      $countSemi  = substr_count($first, ';');
      $sep = ($countComma >= $countSemi) ? ',' : ';';

      $stream = fopen('php://memory','r+');
      fwrite($stream, $raw);
      rewind($stream);

      $header = fgetcsv($stream, 0, $sep);
      while(($r = fgetcsv($stream, 0, $sep)) !== false){
        if (count($r)===1 && ($r[0]===null || $r[0]==='')) continue;
        $rows[] = $r;
      }
      fclose($stream);

    } else {
      // XLSX
      require_once __DIR__.'/vendor/autoload.php';
      $reader=\PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($dest);
      $spreadsheet=$reader->load($dest);
      $sheet=$spreadsheet->getActiveSheet();
      $firstRow=true;
      foreach($sheet->toArray(null,true,true,true) as $row){
        if($firstRow){ $header=array_values($row); $firstRow=false; continue; }
        $rows[] = array_values($row);
      }
    }

    // Normalizador de cabeceras
    $norm = function($s){
      $s = strtolower(trim((string)$s));
      $s = preg_replace('/\s+/', ' ', $s);
      $rep = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','€'=>''];
      $s = strtr($s,$rep);
      return $s;
    };
    $hdr = array_map($norm, $header);

    // Alias esperados
    $wanted = [
      'user'                     => ['user','user id','userid','id user','id_gd','id'],
      'date'                     => ['date','fecha','metric_date','dia'],
      'city'                     => ['city','ciudad'],
      'reassignments'            => ['reassignments','reasignaciones'],
      'orders'                   => ['orders','pedidos'],
      'average_delivery_time_min'=> ['average delivery time (min)','avg delivery time (min)','avg time (min)','media de entrega (min)','delivery time (avg)'],
      'tips'                     => ['tips','propinas'],
      'km'                       => ['km','kms','kilometros','kilometers'],
      'hours'                    => ['hours','horas']
    ];

    $idx = [];
    foreach($wanted as $key=>$aliases){
      $idx[$key] = null;
      foreach($aliases as $alias){
        $pos = array_search($norm($alias), $hdr, true);
        if($pos !== false){ $idx[$key] = $pos; break; }
      }
    }

    // Requeridas mínimas
    foreach (['user','date','orders','tips','km','hours'] as $req) {
      if ($idx[$req] === null) {
        $miss = strtoupper($req);
        $msg=['type'=>'error','text'=>"Cabecera requerida ausente: $miss"];
        break;
      }
    }

    if (!$msg) {
      $stmt=$pdo->prepare("
        INSERT INTO courier_metrics
          (user_id, metric_date, city, reassignments, orders, avg_delivery_time_min, tips, km, hours)
        VALUES (?,?,?,?,?,?,?,?,?)
      ");

      $ins=0;
      foreach($rows as $r){
        $user  = ($idx['user']!==null)  ? intv($r[$idx['user']] ?? 0) : 0;
        $dateS = ($idx['date']!==null)  ? toDateISO($r[$idx['date']] ?? '') : null;
        if(!$user || !$dateS) continue;

        $city  = ($idx['city']!==null)  ? trim((string)($r[$idx['city']] ?? '')) : '';
        $reasg = ($idx['reassignments']!==null) ? intv($r[$idx['reassignments']] ?? 0) : 0;
        $orders= ($idx['orders']!==null) ? intv($r[$idx['orders']] ?? 0) : 0;
        $adt   = ($idx['average_delivery_time_min']!==null) ? dec($r[$idx['average_delivery_time_min']] ?? 0) : null;
        $tips  = ($idx['tips']!==null) ? dec($r[$idx['tips']] ?? 0) : 0.0;
        $km    = ($idx['km']!==null) ? dec($r[$idx['km']] ?? 0) : 0.0;
        $hours = ($idx['hours']!==null) ? dec($r[$idx['hours']] ?? 0) : 0.0;

        $stmt->execute([$user, $dateS, $city, $reasg, $orders, $adt, $tips, $km, $hours]);
        $ins++;
      }

      $msg=['type'=>'ok','text'=>"Archivo importado. Filas insertadas: $ins"];
    }
  } else {
    $msg=['type'=>'error','text'=>'No se pudo subir el archivo.'];
  }
}
?>
<div class="card">
  <h1>Administración • Importar métricas</h1>
  <?php if($msg): ?><div class="card <?php echo $msg['type']==='ok'?'':'warn'; ?>"><?php echo h($msg['text']); ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="file" name="file" required>
    <button class="btn">Importar</button>
  </form>
  <p class="muted">Cabeceras esperadas (ejemplo): <code>user,date,city,reassignments,orders,average delivery time (min),tips,km,hours</code></p>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
