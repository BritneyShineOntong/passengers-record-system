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

// Busiest route
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
$colorMap       = ['Pending' => '#f59e0b', 'Ongoing' => '#22d3ee', 'Completed' => '#10b981', 'Cancelled' => '#374151'];
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

<style>
  /* ── Google Fonts ── */
  @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=IBM+Plex+Mono:wght@400;500&family=Outfit:wght@300;400;500;600&display=swap');

  /* ── Design tokens ── */
  :root {
    --bg:        #0a0c10;
    --bg2:       #111318;
    --bg3:       #181b22;
    --border:    #1f2330;
    --border2:   #2a2f40;
    --amber:     #f59e0b;
    --amber-dim: rgba(245,158,11,.12);
    --cyan:      #22d3ee;
    --cyan-dim:  rgba(34,211,238,.10);
    --green:     #10b981;
    --green-dim: rgba(16,185,129,.12);
    --red:       #f43f5e;
    --red-dim:   rgba(244,63,94,.12);
    --text:      #e8eaf0;
    --text2:     #9ca3b0;
    --text3:     #545c70;
    --font-head: 'Syne', sans-serif;
    --font-body: 'Outfit', sans-serif;
    --font-mono: 'IBM Plex Mono', monospace;
    --radius:    10px;
    --radius-lg: 16px;
  }

  /* ── Reset / Base ── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--font-body);
    font-size: 14px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
  }

  /* ── Dashboard wrapper ── */
  .dash-wrap {
    max-width: 1280px;
    margin: 0 auto;
    padding: 32px 24px 64px;
  }

  /* ── Page header ── */
  .page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 36px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
  }
  .page-header h1 {
    font-family: var(--font-head);
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -.5px;
    color: var(--text);
    line-height: 1;
  }
  .page-header h1 span { color: var(--amber); }
  .page-header .sub {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--text3);
    margin-top: 6px;
    letter-spacing: .5px;
  }
  .live-badge {
    display: flex;
    align-items: center;
    gap: 7px;
    background: var(--bg3);
    border: 1px solid var(--border2);
    border-radius: 20px;
    padding: 6px 14px;
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--text2);
    letter-spacing: .5px;
  }
  .live-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: var(--green);
    box-shadow: 0 0 0 3px var(--green-dim);
    animation: pulse 2s ease-in-out infinite;
  }
  @keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 3px var(--green-dim); }
    50%       { box-shadow: 0 0 0 6px rgba(16,185,129,.05); }
  }

  /* ── Section labels ── */
  .section-label {
    font-family: var(--font-mono);
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--text3);
    margin-bottom: 14px;
  }

  /* ── Stat cards ── */
  .stat-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px;
    margin-bottom: 28px;
  }
  @media (max-width: 1100px) { .stat-grid { grid-template-columns: repeat(3, 1fr); } }
  @media (max-width: 640px)  { .stat-grid { grid-template-columns: repeat(2, 1fr); } }

  .stat-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px 18px;
    position: relative;
    overflow: hidden;
    transition: border-color .2s, transform .2s;
    cursor: default;
  }
  .stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: var(--accent, var(--border2));
    border-radius: var(--radius) var(--radius) 0 0;
    transition: opacity .2s;
  }
  .stat-card:hover { border-color: var(--border2); transform: translateY(-2px); }
  .stat-card.amber { --accent: var(--amber); }
  .stat-card.cyan  { --accent: var(--cyan);  }
  .stat-card.green { --accent: var(--green); }
  .stat-card.red   { --accent: var(--red);   }

  .stat-icon {
    font-size: 18px;
    margin-bottom: 12px;
    display: block;
    opacity: .7;
  }
  .stat-label {
    font-family: var(--font-mono);
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--text3);
    margin-bottom: 6px;
  }
  .stat-value {
    font-family: var(--font-head);
    font-size: 26px;
    font-weight: 700;
    color: var(--text);
    line-height: 1;
    margin-bottom: 6px;
  }
  .stat-value.mono { font-family: var(--font-mono); font-size: 18px; font-weight: 500; }
  .stat-sub {
    font-size: 11px;
    color: var(--text3);
  }

  /* ── Highlight cards ── */
  .highlight-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 28px;
  }
  @media (max-width: 640px) { .highlight-grid { grid-template-columns: 1fr; } }

  .highlight-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px 22px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: border-color .2s;
  }
  .highlight-card:hover { border-color: var(--border2); }
  .highlight-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
  }
  .highlight-icon.amber { background: var(--amber-dim); }
  .highlight-icon.cyan  { background: var(--cyan-dim);  }
  .highlight-card-label {
    font-family: var(--font-mono);
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--text3);
    margin-bottom: 4px;
  }
  .highlight-card-value {
    font-family: var(--font-head);
    font-size: 15px;
    font-weight: 600;
    color: var(--text);
    line-height: 1.3;
  }
  .highlight-card-count {
    font-size: 12px;
    color: var(--text3);
    margin-top: 2px;
    font-family: var(--font-mono);
  }

  /* ── Card base ── */
  .card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
  }
  .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 22px 14px;
    border-bottom: 1px solid var(--border);
  }
  .card-title {
    font-family: var(--font-head);
    font-size: 13px;
    font-weight: 700;
    letter-spacing: .3px;
    color: var(--text);
    text-transform: uppercase;
  }
  .card-badge {
    font-family: var(--font-mono);
    font-size: 10px;
    letter-spacing: 1px;
    color: var(--text3);
    background: var(--bg3);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 2px 8px;
  }
  .card-body { padding: 20px 22px; }

  /* ── Charts layout ── */
  .charts-main {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
  }
  @media (max-width: 900px) { .charts-main { grid-template-columns: 1fr; } }

  .chart-full { margin-bottom: 16px; }

  /* ── Doughnut centering ── */
  .donut-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px 22px 22px;
  }

  /* ── Leaderboard table ── */
  .table-wrap { overflow-x: auto; }
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }
  thead th {
    font-family: var(--font-mono);
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--text3);
    padding: 12px 20px;
    text-align: left;
    background: var(--bg3);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
  }
  tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .15s;
  }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: var(--bg3); }
  tbody td {
    padding: 14px 20px;
    color: var(--text2);
    vertical-align: middle;
  }

  .rank-num {
    font-family: var(--font-mono);
    font-size: 12px;
    font-weight: 500;
  }
  .rank-gold   { color: var(--amber); }
  .rank-silver { color: #94a3b8; }
  .rank-bronze { color: #b45309; }
  .rank-other  { color: var(--text3); }

  .name-cell {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .avatar {
    width: 32px; height: 32px;
    border-radius: 8px;
    background: var(--bg3);
    border: 1px solid var(--border2);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-head);
    font-size: 12px;
    font-weight: 700;
    color: var(--text2);
    flex-shrink: 0;
  }
  .driver-name {
    font-weight: 500;
    color: var(--text);
    font-family: var(--font-body);
  }

  .fare-val {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--green);
    font-weight: 500;
  }

  .progress-track {
    flex: 1;
    background: var(--bg3);
    border: 1px solid var(--border);
    border-radius: 20px;
    height: 5px;
    overflow: hidden;
  }
  .progress-fill {
    height: 100%;
    border-radius: 20px;
    background: linear-gradient(90deg, var(--cyan), var(--green));
    transition: width .4s ease;
  }
  .progress-pct {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--text3);
    min-width: 34px;
    text-align: right;
  }

  /* ── Fade-in animation ── */
  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .stat-card, .highlight-card, .card {
    animation: fadeUp .4s ease both;
  }
  .stat-card:nth-child(1) { animation-delay: .04s; }
  .stat-card:nth-child(2) { animation-delay: .08s; }
  .stat-card:nth-child(3) { animation-delay: .12s; }
  .stat-card:nth-child(4) { animation-delay: .16s; }
  .stat-card:nth-child(5) { animation-delay: .20s; }
  .stat-card:nth-child(6) { animation-delay: .24s; }
</style>

<div class="dash-wrap">

  <!-- Page header -->
  <div class="page-header">
    <div>
      <h1>Operations <span>Overview</span></h1>
      <div class="sub">DASHBOARD · <?php echo strtoupper(date('D, d M Y')); ?></div>
    </div>
    <div class="live-badge">
      <div class="live-dot"></div>
      LIVE DATA
    </div>
  </div>

  <!-- Stat cards -->
  <div class="section-label">Key Metrics</div>
  <div class="stat-grid">
    <div class="stat-card cyan">
      <span class="stat-icon">🚗</span>
      <div class="stat-label">Total Bookings</div>
      <div class="stat-value"><?php echo number_format($statsRow['total_bookings']); ?></div>
      <div class="stat-sub">Active records</div>
    </div>
    <div class="stat-card green">
      <span class="stat-icon">💰</span>
      <div class="stat-label">Total Revenue</div>
      <div class="stat-value mono">₱<?php echo number_format($statsRow['total_revenue'], 2); ?></div>
      <div class="stat-sub">From paid rides</div>
    </div>
    <div class="stat-card amber">
      <span class="stat-icon">📅</span>
      <div class="stat-label">This Week</div>
      <div class="stat-value"><?php echo $statsRow['this_week']; ?></div>
      <div class="stat-sub">Last 7 days</div>
    </div>
    <div class="stat-card amber">
      <span class="stat-icon">⏳</span>
      <div class="stat-label">Pending</div>
      <div class="stat-value"><?php echo $statsRow['pending']; ?></div>
      <div class="stat-sub">Awaiting ride</div>
    </div>
    <div class="stat-card green">
      <span class="stat-icon">✅</span>
      <div class="stat-label">Completed</div>
      <div class="stat-value"><?php echo $statsRow['completed']; ?></div>
      <div class="stat-sub">Finished rides</div>
    </div>
    <div class="stat-card red">
      <span class="stat-icon">⚠️</span>
      <div class="stat-label">Unpaid</div>
      <div class="stat-value" style="color:var(--red);"><?php echo $statsRow['unpaid']; ?></div>
      <div class="stat-sub">Need collection</div>
    </div>
  </div>

  <!-- Highlight cards -->
  <div class="section-label" style="margin-top:4px;">Highlights</div>
  <div class="highlight-grid">
    <div class="highlight-card">
      <div class="highlight-icon amber">🏆</div>
      <div>
        <div class="highlight-card-label">Top Driver</div>
        <div class="highlight-card-value">
          <?php echo $topDriver ? htmlspecialchars($topDriver['driver_name']) : '—'; ?>
        </div>
        <div class="highlight-card-count">
          <?php echo $topDriver ? $topDriver['cnt'] . ' bookings this period' : ''; ?>
        </div>
      </div>
    </div>
    <div class="highlight-card">
      <div class="highlight-icon cyan">📍</div>
      <div>
        <div class="highlight-card-label">Busiest Route</div>
        <div class="highlight-card-value">
          <?php echo $busyRoute ? htmlspecialchars($busyRoute['route']) : '—'; ?>
        </div>
        <div class="highlight-card-count">
          <?php echo $busyRoute ? $busyRoute['cnt'] . ' trips recorded' : ''; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts row -->
  <div class="section-label" style="margin-top:4px;">Analytics</div>
  <div class="charts-main">
    <div class="card">
      <div class="card-header">
        <span class="card-title">Bookings</span>
        <span class="card-badge">LAST 14 DAYS</span>
      </div>
      <div class="card-body">
        <canvas id="bookingsChart" height="120"></canvas>
      </div>
    </div>
    <div class="card">
      <div class="card-header">
        <span class="card-title">Status</span>
        <span class="card-badge">BREAKDOWN</span>
      </div>
      <div class="donut-wrap">
        <canvas id="statusChart" height="200" width="200"></canvas>
      </div>
    </div>
  </div>

  <div class="card chart-full">
    <div class="card-header">
      <span class="card-title">Revenue (Paid Fares)</span>
      <span class="card-badge">LAST 14 DAYS</span>
    </div>
    <div class="card-body">
      <canvas id="revenueChart" height="90"></canvas>
    </div>
  </div>

  <!-- Leaderboard -->
  <div class="section-label" style="margin-top:4px;">Driver Leaderboard</div>
  <div class="card">
    <div class="card-header">
      <span class="card-title">Top Drivers</span>
      <span class="card-badge">TOP 10</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Driver</th>
            <th>Total</th>
            <th>Completed</th>
            <th>Revenue</th>
            <th>Completion Rate</th>
          </tr>
        </thead>
        <tbody>
          <?php $rank = 1; while ($r = $leaderboard->fetch_assoc()):
            $rate       = $r['total'] > 0 ? round(($r['completed'] / $r['total']) * 100) : 0;
            $rankClass  = match(true) { $rank === 1 => 'rank-gold', $rank === 2 => 'rank-silver', $rank === 3 => 'rank-bronze', default => 'rank-other' };
          ?>
          <tr>
            <td>
              <span class="rank-num <?php echo $rankClass; ?>">
                <?php echo sprintf('%02d', $rank); ?>
              </span>
            </td>
            <td>
              <div class="name-cell">
                <div class="avatar"><?php echo strtoupper(substr($r['driver_name'], 0, 1)); ?></div>
                <span class="driver-name"><?php echo htmlspecialchars($r['driver_name']); ?></span>
              </div>
            </td>
            <td style="font-family:var(--font-mono);font-size:13px;color:var(--text);">
              <?php echo $r['total']; ?>
            </td>
            <td style="font-family:var(--font-mono);font-size:13px;color:var(--cyan);">
              <?php echo $r['completed']; ?>
            </td>
            <td class="fare-val">₱<?php echo number_format($r['revenue'], 2); ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:10px;min-width:140px;">
                <div class="progress-track">
                  <div class="progress-fill" style="width:<?php echo $rate; ?>%;"></div>
                </div>
                <span class="progress-pct"><?php echo $rate; ?>%</span>
              </div>
            </td>
          </tr>
          <?php $rank++; endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /dash-wrap -->

<script>
const labels = <?php echo json_encode($chartLabels); ?>;
const counts = <?php echo json_encode($chartCounts); ?>;
const rev    = <?php echo json_encode($revCounts); ?>;

Chart.defaults.font.family = "'Outfit', sans-serif";
Chart.defaults.font.size   = 11;
Chart.defaults.color       = '#545c70';

// ── Bookings bar chart ──────────────────────────────────────
new Chart(document.getElementById('bookingsChart'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: 'Bookings',
      data: counts,
      backgroundColor: 'rgba(34,211,238,.18)',
      borderColor: '#22d3ee',
      borderWidth: 1.5,
      borderRadius: 5,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false }, tooltip: { backgroundColor: '#111318', borderColor: '#1f2330', borderWidth: 1, titleColor: '#e8eaf0', bodyColor: '#9ca3b0', padding: 10 } },
    scales: {
      x: { grid: { color: 'rgba(31,35,48,.6)' }, ticks: { maxRotation: 0 } },
      y: { beginAtZero: true, grid: { color: 'rgba(31,35,48,.6)' }, ticks: { stepSize: 1 } }
    }
  }
});

// ── Revenue line chart ──────────────────────────────────────
new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels,
    datasets: [{
      label: 'Revenue ₱',
      data: rev,
      borderColor: '#10b981',
      backgroundColor: 'rgba(16,185,129,.06)',
      tension: .4,
      fill: true,
      pointRadius: 4,
      pointBackgroundColor: '#10b981',
      pointBorderColor: '#0a0c10',
      pointBorderWidth: 2,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false }, tooltip: { backgroundColor: '#111318', borderColor: '#1f2330', borderWidth: 1, titleColor: '#e8eaf0', bodyColor: '#9ca3b0', padding: 10 } },
    scales: {
      x: { grid: { color: 'rgba(31,35,48,.6)' } },
      y: { beginAtZero: true, grid: { color: 'rgba(31,35,48,.6)' } }
    }
  }
});

// ── Status doughnut ─────────────────────────────────────────
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?php echo json_encode($statusLabels); ?>,
    datasets: [{
      data: <?php echo json_encode($statusCounts); ?>,
      backgroundColor: <?php echo json_encode($statusColors); ?>,
      borderColor: '#111318',
      borderWidth: 3,
      hoverOffset: 6,
    }]
  },
  options: {
    cutout: '68%',
    plugins: {
      legend: {
        position: 'bottom',
        labels: { boxWidth: 10, boxHeight: 10, padding: 14, color: '#9ca3b0', font: { family: "'IBM Plex Mono', monospace", size: 10 } }
      },
      tooltip: { backgroundColor: '#111318', borderColor: '#1f2330', borderWidth: 1, titleColor: '#e8eaf0', bodyColor: '#9ca3b0', padding: 10 }
    }
  }
});
</script>

<?php layout_foot(); ?>