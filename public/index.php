<?php
// public/index.php
declare(strict_types=1);

$active = 'dashboard';
$title  = 'Dashboard';

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/metrics.php';

// Rango por defecto: últimos 14 días
$today = (new DateTime('today'))->format('Y-m-d');
$default_from = (new DateTime('today -13 days'))->format('Y-m-d');

$from = parse_date_or_default($_GET['from'] ?? null, $default_from);
$to   = parse_date_or_default($_GET['to']   ?? null, $today);
$city = isset($_GET['city']) && $_GET['city'] !== '' ? $_GET['city'] : 'ALL';

// Para el selector de ciudades
$cities = list_cities($pdo);

// KPIs y tendencia
$kpis   = fetch_kpis($pdo, $from, $to, $city);
$trend  = fetch_trend($pdo, $from, $to, $city);
$tops   = fetch_top_couriers($pdo, $from, $to, $city, 10);

// Preparar arrays para Chart.js
$labels = array_column($trend, 'metric_date');
$orders = array_map(fn($r) => (int)$r['orders_sum'], $trend);
$avgts  = array_map(fn($r) => $r['avg_time_w'] !== null ? round((float)$r['avg_time_w'], 2) : null, $trend);
?>
<div class="row g-3 align-items-end">
  <div class="col-12 col-lg-8">
    <form class="row g-2">
      <div class="col-12 col-sm-4">
        <label class="form-label">Desde</label>
        <input type="date" name="from" value="<?= h($from) ?>" class="form-control">
      </div>
      <div class="col-12 col-sm-4">
        <label class="form-label">Hasta</label>
        <input type="date" name="to" value="<?= h($to) ?>" class="form-control">
      </div>
      <div class="col-12 col-sm-4">
        <label class="form-label">Ciudad</label>
        <select name="city" class="form-select">
          <option value="ALL" <?= $city==='ALL'?'selected':''; ?>>Todas</option>
          <?php foreach ($cities as $c): ?>
          <option value="<?= h($c) ?>" <?= $city===$c?'selected':''; ?>><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <button class="btn btn-primary">Aplicar</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-6 col-lg-3">
    <div class="card p-3">
      <div class="text-muted small">Pedidos</div>
      <div class="fs-3 fw-bold"><?= number_format((int)($kpis['orders_sum'] ?? 0)) ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card p-3">
      <div class="text-muted small">Tiempo medio (min)</div>
      <div class="fs-3 fw-bold">
        <?= $kpis['avg_time_w'] !== null ? number_format((float)$kpis['avg_time_w'], 1) : '—' ?>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card p-3">
      <div class="text-muted small">Propinas (€)</div>
      <div class="fs-3 fw-bold"><?= number_format((float)($kpis['tips_sum'] ?? 0), 2) ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card p-3">
      <div class="text-muted small">Km / Horas</div>
      <div class="fs-5 fw-bold">
        <?= number_format((float)($kpis['km_sum'] ?? 0), 1) ?> km ·
        <?= number_format((float)($kpis['hours_sum'] ?? 0), 1) ?> h
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12 col-lg-8">
    <div class="card p-3">
      <h6 class="mb-3">Tendencia diaria</h6>
      <canvas id="ordersChart" height="120"></canvas>
      <div class="text-muted small mt-2">
        Pedidos (barras) y tiempo medio (línea) por día
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card p-3">
      <h6 class="mb-3">Top repartidores</h6>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>Repartidor</th><th class="text-end">Pedidos</th><th class="text-end">Propinas</th><th class="text-end">T. medio</th></tr>
          </thead>
          <tbody>
            <?php if (!$tops): ?>
              <tr><td colspan="4" class="text-center text-muted">Sin datos para el filtro</td></tr>
            <?php else: foreach ($tops as $r): ?>
              <tr>
                <td><?= h($r['nombre']) ?></td>
                <td class="text-end"><?= number_format((int)$r['orders_sum']) ?></td>
                <td class="text-end">€ <?= number_format((float)$r['tips_sum'], 2) ?></td>
                <td class="text-end"><?= $r['avg_time_w']!==null ? number_format((float)$r['avg_time_w'], 1).' min' : '—' ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<!-- Chart.js desde CDN (ligero y suficiente) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode($labels) ?>;
const dataOrders = <?= json_encode($orders) ?>;
const dataAvg = <?= json_encode($avgts) ?>;

const ctx = document.getElementById('ordersChart').getContext('2d');
const chart = new Chart(ctx, {
  type: 'bar',
  data: {
    labels,
    datasets: [
      { type: 'bar', label: 'Pedidos', data: dataOrders, yAxisID: 'y' },
      { type: 'line', label: 'T. medio (min)', data: dataAvg, yAxisID: 'y1' },
    ]
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    scales: {
      y:  { beginAtZero: true, title: { display: true, text: 'Pedidos' } },
      y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'Minutos' }, grid: { drawOnChartArea: false } }
    },
    plugins: {
      legend: { display: true },
      tooltip: { callbacks: { label: (ctx) => ctx.formattedValue } }
    }
  }
});
</script>
