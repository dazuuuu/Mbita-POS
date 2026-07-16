<?php
// public/super/dashboard/index.php — TRACK-style dynamic dashboard
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();

$pdo = Database::pdo();
$tenantId = (int) TenantContext::tenantId();
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);
$stats = (new DashboardStatsService($pdo, $tenantId))->build();

unset($_SESSION['first_login']);
$page_title = 'Dashboard';
$shop = $__tenant['name'] ?? 'your shop';
$currency = $stats['currency'];

ob_start();
?>
<!-- Row 1: Summary stat cards -->
<div class="cd-stat-row">
  <?php foreach ($stats['cards'] as $card): ?>
  <div class="cd-stat-card">
    <div class="cd-stat-icon" style="background:<?php echo htmlspecialchars($card['color']); ?>;">
      <i class="fas <?php echo htmlspecialchars($card['icon']); ?>"></i>
    </div>
    <div class="cd-stat-body">
      <div class="cd-stat-value"><?php echo htmlspecialchars((string) $card['value']); ?></div>
      <div class="cd-stat-label"><?php echo htmlspecialchars($card['label']); ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Row 2: Circular progress -->
<div class="cd-ring-row">
  <?php foreach ($stats['rings'] as $i => $ring): ?>
  <div class="cd-ring-card">
    <div class="cd-ring-title"><?php echo htmlspecialchars($ring['label']); ?></div>
    <div class="cd-ring-wrap">
      <canvas id="ring<?php echo $i; ?>"></canvas>
      <div class="cd-ring-pct" id="ringPct<?php echo $i; ?>" style="color:<?php echo htmlspecialchars($ring['color']); ?>;">
        <?php echo (int) $ring['pct']; ?>%
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Row 3: Charts -->
<div class="cd-chart-row">
  <div class="cd-chart-card">
    <h3>Revenue — last 7 days (<?php echo htmlspecialchars($currency); ?>)</h3>
    <div class="cd-chart-canvas"><canvas id="lineChart"></canvas></div>
  </div>
  <div class="cd-chart-card">
    <h3>Top performers — last 30 days (<?php echo htmlspecialchars($currency); ?>)</h3>
    <div class="cd-chart-canvas"><canvas id="barChart"></canvas></div>
  </div>
</div>

<?php
$lineJson = json_encode($stats['line']);
$barJson = json_encode($stats['bar']);
$ringsJson = json_encode($stats['rings']);
$extra_js = <<<JS
<script>
(function(){
  var rings = {$ringsJson};
  rings.forEach(function(r, i){
    var el = document.getElementById('ring'+i);
    if (!el) return;
    new Chart(el, {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [r.pct, Math.max(0, 100 - r.pct)],
          backgroundColor: [r.color, '#ecf0f1'],
          borderWidth: 0
        }]
      },
      options: {
        cutout: '78%',
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false }, tooltip: { enabled: false } }
      }
    });
  });

  var line = {$lineJson};
  var lineEl = document.getElementById('lineChart');
  if (lineEl) {
    new Chart(lineEl, {
      type: 'line',
      data: {
        labels: line.labels,
        datasets: [
          { label: 'POS Sales', data: line.pos, borderColor: '#2ecc71', backgroundColor: 'rgba(46,204,113,.1)', tension: .35, fill: true, pointRadius: 4, pointBackgroundColor: '#2ecc71' },
          { label: 'Agent Sales', data: line.commission, borderColor: '#95a5a6', backgroundColor: 'transparent', tension: .35, borderDash: [4,4], pointRadius: 4, pointBackgroundColor: '#95a5a6' }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, grid: { color: '#f0f0f0' } }, x: { grid: { display: false } } },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
      }
    });
  }

  var bar = {$barJson};
  var barEl = document.getElementById('barChart');
  if (barEl && bar.labels.length) {
    new Chart(barEl, {
      type: 'bar',
      data: {
        labels: bar.labels,
        datasets: [
          { label: 'Revenue', data: bar.values, backgroundColor: '#bdc3c7', borderRadius: 2, barThickness: 18 },
          { label: 'Target', data: bar.values.map(function(v){ return v * 0.85; }), backgroundColor: '#2ecc71', borderRadius: 2, barThickness: 18 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, grid: { color: '#f0f0f0' } }, x: { grid: { display: false } } },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
      }
    });
  } else if (barEl) {
    barEl.parentElement.innerHTML += '<p class="text-muted small text-center py-5 mb-0">No sales data yet for the chart.</p>';
  }
})();
</script>
JS;

$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
