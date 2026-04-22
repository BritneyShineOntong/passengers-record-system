<?php
session_start();
require 'db.php';
require 'auth.php';
require 'layout.php';


// ── Stats (single query) ────────────────────────────────────
$statsRow = $conn->query("
    SELECT
        COUNT(*)                                                         AS total_bookings,
        COALESCE(SUM(CASE WHEN payment_status='Paid' THEN fare END), 0) AS total_revenue,
        SUM(ride_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))           AS this_week,
        SUM(ride_status  = 'Pending')                                   AS pending,
        SUM(ride_status  = 'Completed')                                 AS completed,
        SUM(payment_status = 'Unpaid')                                  AS unpaid
    FROM bookings WHERE is_archived = 0
")->fetch_assoc();

// Most active driver
$topDriver = $conn->query("
    SELECT driver_name, COUNT(*) AS cnt FROM bookings WHERE is_archived = 0
    GROUP BY driver_name ORDER BY cnt DESC LIMIT 1
")->fetch_assoc();

// Busiest route (group by raw columns, not alias)
$busyRouteRow = $conn->query("
    SELECT pickup_location, drop_off_location, COUNT(*) AS cnt
    FROM bookings WHERE is_archived = 0
    GROUP BY pickup_location, drop_off_location
    ORDER BY cnt DESC LIMIT 1
")->fetch_assoc();
$busyRoute = $busyRouteRow
    ? ['route' => $busyRouteRow['pickup_location'] . ' → ' . $busyRouteRow['drop_off_location'], 'cnt' => $busyRouteRow['cnt']]
    : null;

// ── Bookings per day (last 14 days) ─────────────────────────
$chartData = $conn->query("
    SELECT ride_date AS d, COUNT(*) AS cnt
    FROM bookings WHERE is_archived = 0 AND ride_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY ride_date ORDER BY ride_date
");
$map = [];
while ($r = $chartData->fetch_assoc()) $map[$r['d']] = $r['cnt'];
$chartLabels = [];
$chartCounts = [];
for ($i = 13; $i >= 0; $i--) {
    $d             = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('d M', strtotime($d));
    $chartCounts[] = $map[$d] ?? 0;
}

// ── Revenue per day (last 14 days) ──────────────────────────
$revData = $conn->query("
    SELECT ride_date AS d, COALESCE(SUM(fare), 0) AS total
    FROM bookings WHERE is_archived = 0 AND payment_status = 'Paid' AND ride_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY ride_date ORDER BY ride_date
");
$revMap = [];
while ($r = $revData->fetch_assoc()) $revMap[$r['d']] = $r['total'];
$revCounts = [];
for ($i = 13; $i >= 0; $i--) {
    $d           = date('Y-m-d', strtotime("-$i days"));
    $revCounts[] = $revMap[$d] ?? 0;
}

// ── Driver leaderboard ──────────────────────────────────────
$leaderboard = $conn->query("
    SELECT driver_name,
           COUNT(*) AS total,
           SUM(ride_status = 'Completed') AS completed,
           COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN fare ELSE 0 END), 0) AS revenue
    FROM bookings WHERE is_archived = 0
    GROUP BY driver_name ORDER BY total DESC LIMIT 10
");

// ── Ride status breakdown ────────────────────────────────────
$validStatuses  = ['Pending', 'Ongoing', 'Completed', 'Cancelled'];
$colorMap       = ['Pending' => '#f97316', 'Ongoing' => '#2563eb', 'Completed' => '#059669', 'Cancelled' => '#9ca3af'];
$statusBreak    = $conn->query("SELECT ride_status, COUNT(*) AS cnt FROM bookings WHERE is_archived = 0 GROUP BY ride_status");
$statusLabels   = [];
$statusCounts   = [];
$statusColors   = [];
while ($r = $statusBreak->fetch_assoc()) {
    $s = in_array($r['ride_status'], $validStatuses) ? $r['ride_status'] : 'Cancelled';
    $statusLabels[] = $s;
    $statusCounts[] = (int)$r['cnt'];
    $statusColors[] = $colorMap[$s] ?? '#6b7280';
}

layout_head('Dashboard');
?>

<!-- Stat cards -->
<div class="stat-grid">
  <div class="stat-card blue">
    <div class="stat-label">Total Bookings</div>
    <div class="stat-value"><?php echo number_format($statsRow['total_bookings']); ?></div>
    <div class="stat-sub">Active records</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Total Revenue</div>
    <div class="stat-value" style="font-size:20px;">₱<?php echo number_format($statsRow['total_revenue'], 2); ?></div>
    <div class="stat-sub">From paid rides</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">This Week</div>
    <div class="stat-value"><?php echo $statsRow['this_week']; ?></div>
    <div class="stat-sub">Last 7 days</div>
  </div>
  <div class="stat-card amber">
    <div class="stat-label">Pending</div>
    <div class="stat-value"><?php echo $statsRow['pending']; ?></div>
    <div class="stat-sub">Awaiting ride</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Completed</div>
    <div class="stat-value"><?php echo $statsRow['completed']; ?></div>
    <div class="stat-sub">Finished rides</div>
  </div>
  <div class="stat-card" style="border-left:3px solid var(--red);">
    <div class="stat-label">Unpaid</div>
    <div class="stat-value" style="color:var(--red);"><?php echo $statsRow['unpaid']; ?></div>
    <div class="stat-sub">Need collection</div>
  </div>
</div>

<!-- Highlight cards -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
  <div class="card">
    <div class="card-body" style="padding:16px 20px;">
      <div class="stat-label">🏆 Top Driver</div>
      <div style="font-size:16px;font-weight:600;color:var(--text);margin-top:6px;">
        <?php echo $topDriver ? htmlspecialchars($topDriver['driver_name']) : '—'; ?>
      </div>
      <div style="font-size:13px;color:var(--text3);"><?php echo $topDriver ? $topDriver['cnt'] . ' bookings' : ''; ?></div>
    </div>
  </div>
  <div class="card">
    <div class="card-body" style="padding:16px 20px;">
      <div class="stat-label">📍 Busiest Route</div>
      <div style="font-size:14px;font-weight:600;color:var(--text);margin-top:6px;">
        <?php echo $busyRoute ? htmlspecialchars($busyRoute['route']) : '—'; ?>
      </div>
      <div style="font-size:13px;color:var(--text3);"><?php echo $busyRoute ? $busyRoute['cnt'] . ' trips' : ''; ?></div>
    </div>
  </div>
</div>

<!-- Charts row -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px;">
  <div class="card">
    <div class="card-header"><span class="card-title">Bookings — Last 14 Days</span></div>
    <div class="card-body"><canvas id="bookingsChart" height="110"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title">Status Breakdown</span></div>
    <div class="card-body" style="display:flex;align-items:center;justify-content:center;">
      <canvas id="statusChart" height="180" width="180"></canvas>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:24px;">
  <div class="card-header"><span class="card-title">Revenue (Paid) — Last 14 Days</span></div>
  <div class="card-body"><canvas id="revenueChart" height="90"></canvas></div>
</div>

<!-- Driver Leaderboard -->
<div class="card">
  <div class="card-header"><span class="card-title">Driver Leaderboard</span></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Driver</th><th>Total Bookings</th><th>Completed</th><th>Revenue (Paid)</th><th>Completion Rate</th></tr>
      </thead>
      <tbody>
        <?php $rank = 1; while ($r = $leaderboard->fetch_assoc()):
          $rate = $r['total'] > 0 ? round(($r['completed'] / $r['total']) * 100) : 0;
        ?>
        <tr>
          <td style="font-weight:600;color:<?php echo $rank <= 3 ? 'var(--amber)' : 'var(--text3)'; ?>;"><?php echo $rank++; ?></td>
          <td>
            <div class="name-cell">
              <div class="avatar"><?php echo strtoupper(substr($r['driver_name'], 0, 1)); ?></div>
              <?php echo htmlspecialchars($r['driver_name']); ?>
            </div>
          </td>
          <td><?php echo $r['total']; ?></td>
          <td><?php echo $r['completed']; ?></td>
          <td class="fare-val">₱<?php echo number_format($r['revenue'], 2); ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="flex:1;background:var(--border);border-radius:20px;height:6px;">
                <div style="width:<?php echo $rate; ?>%;background:var(--green);border-radius:20px;height:6px;"></div>
              </div>
              <span style="font-size:12px;color:var(--text3);min-width:32px;"><?php echo $rate; ?>%</span>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const labels = <?php echo json_encode($chartLabels); ?>;
const counts = <?php echo json_encode($chartCounts); ?>;
const rev    = <?php echo json_encode($revCounts); ?>;

Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#6b7280';

new Chart(document.getElementById('bookingsChart'), {
  type: 'bar',
  data: { labels, datasets: [{ label: 'Bookings', data: counts, backgroundColor: '#93c5fd', borderRadius: 5 }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: { labels, datasets: [{ label: 'Revenue ₱', data: rev, borderColor: '#059669', backgroundColor: 'rgba(5,150,105,.08)', tension: .35, fill: true, pointRadius: 4, pointBackgroundColor: '#059669' }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?php echo json_encode($statusLabels); ?>,
    datasets: [{ data: <?php echo json_encode($statusCounts); ?>, backgroundColor: <?php echo json_encode($statusColors); ?>, borderWidth: 2 }]
  },
  options: { cutout: '65%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
});
</script>

<?php layout_foot(); ?>
