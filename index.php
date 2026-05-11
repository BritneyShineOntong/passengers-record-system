<?php
session_start();
require 'db.php';
require 'auth.php';
require 'layout.php';

$PER_PAGE  = 15;
$search    = trim($_GET['search'] ?? '');
$status    = $_GET['ride_status']    ?? '';
$payment   = $_GET['payment_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to']   ?? '';

$allowedSort = ['id','customer_name','driver_name','ride_date','fare','ride_status','payment_status'];
$sort = in_array($_GET['sort'] ?? '', $allowedSort) ? $_GET['sort'] : 'id';
$dir  = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$where  = ['b.is_archived = 0'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(b.customer_name LIKE ? OR b.driver_name LIKE ? OR b.pickup_location LIKE ? OR b.drop_off_location LIKE ? OR b.booking_ref LIKE ?)';
    $like    = "%$search%";
    array_push($params, $like, $like, $like, $like, $like);
    $types  .= 'sssss';
}
if ($status    !== '') { $where[] = 'b.ride_status = ?';    $params[] = $status;    $types .= 's'; }
if ($payment   !== '') { $where[] = 'b.payment_status = ?'; $params[] = $payment;   $types .= 's'; }
if ($date_from !== '') { $where[] = 'b.ride_date >= ?';     $params[] = $date_from; $types .= 's'; }
if ($date_to   !== '') { $where[] = 'b.ride_date <= ?';     $params[] = $date_to;   $types .= 's'; }

$whereSQL = 'WHERE ' . implode(' AND ', $where);

/* ── Stats ── */
$sStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(fare) AS revenue,
        SUM(ride_status = 'Completed') AS completed,
        SUM(ride_status = 'Pending')   AS pending,
        SUM(ride_status = 'Ongoing')   AS ongoing,
        SUM(ride_status = 'Cancelled') AS cancelled
    FROM bookings b WHERE b.is_archived = 0
");
$sStmt->execute();
$stats = $sStmt->get_result()->fetch_assoc();

/* ── CSV Export ── */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="bookings_' . date('Ymd') . '.csv"');
    $out   = fopen('php://output', 'w');
    fputcsv($out, ['Ref','Customer','Driver','Pickup','Drop-off','Date','Fare','Ride Status','Payment','Notes']);
    $eStmt = $conn->prepare("SELECT * FROM bookings b $whereSQL ORDER BY b.`$sort` $dir");
    if ($types) $eStmt->bind_param($types, ...$params);
    $eStmt->execute();
    while ($r = $eStmt->get_result()->fetch_assoc()) {
        fputcsv($out, [
            $r['booking_ref'], $r['customer_name'], $r['driver_name'],
            $r['pickup_location'], $r['drop_off_location'], $r['ride_date'],
            $r['fare'], $r['ride_status'], $r['payment_status'], $r['notes'],
        ]);
    }
    fclose($out);
    exit;
}

/* ── Pagination ── */
$cStmt = $conn->prepare("SELECT COUNT(*) FROM bookings b $whereSQL");
if ($types) $cStmt->bind_param($types, ...$params);
$cStmt->execute();
$total = $cStmt->get_result()->fetch_row()[0];

$totalPages = max(1, (int)ceil($total / $PER_PAGE));
$page       = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset     = ($page - 1) * $PER_PAGE;

/* ── Rows ── */
$sql  = "SELECT b.*, u.full_name AS creator FROM bookings b LEFT JOIN users u ON b.created_by = u.id $whereSQL ORDER BY b.`$sort` $dir LIMIT $PER_PAGE OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$qBase = array_filter([
    'search'         => $search,
    'ride_status'    => $status,
    'payment_status' => $payment,
    'date_from'      => $date_from,
    'date_to'        => $date_to,
    'sort'           => $sort,
    'dir'            => $dir,
]);

layout_head('Bookings');
?>
<!---------------- STYLES ---------------->
<style>
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@300;400;500&display=swap');

:root {
  --bg:      #0C0D11;
  --s1:      #141519;
  --s2:      #1A1C23;
  --s3:      #21242E;
  --b1:      #1E2130;
  --b2:      #2A2F42;
  --b3:      #353B52;
  --t1:      #E2E4EF;
  --t2:      #8B90A8;
  --t3:      #4A5068;
  --accent:  #5B7BF8;
  --accent2: #8B5CF6;
  --green:   #22C55E;
  --amber:   #F59E0B;
  --red:     #EF4444;
  --cyan:    #06B6D4;
  --r:       10px;
  --r-sm:    6px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  background: var(--bg);
  color: var(--t1);
  font-family: 'Space Grotesk', sans-serif;
  min-height: 100vh;
}

/* ─── Layout ─── */
.pw {
  max-width: 1460px;
  margin: 0 auto;
  padding: 32px 28px;
}

/* ─── Page header ─── */
.ph {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 28px;
  gap: 16px;
  flex-wrap: wrap;
}
.ph-left h1 {
  font-size: 26px;
  font-weight: 700;
  letter-spacing: -0.5px;
  color: var(--t1);
}
.ph-left p {
  font-size: 11px;
  color: var(--t3);
  margin-top: 5px;
  font-family: 'JetBrains Mono', monospace;
  font-weight: 300;
  letter-spacing: 0.3px;
}
.ph-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

/* ─── Buttons ─── */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-family: 'Space Grotesk', sans-serif;
  font-size: 12px;
  font-weight: 600;
  padding: 8px 15px;
  border-radius: var(--r-sm);
  border: 1px solid transparent;
  cursor: pointer;
  text-decoration: none;
  transition: all .15s ease;
  white-space: nowrap;
  letter-spacing: 0.2px;
}
.btn svg { flex-shrink: 0; }
.btn-primary  { background: var(--accent); color: #fff; border-color: var(--accent); }
.btn-primary:hover { background: #4a6ae3; border-color: #4a6ae3; transform: translateY(-1px); }
.btn-ghost    { background: transparent; color: var(--t2); border-color: var(--b2); }
.btn-ghost:hover { background: var(--s2); color: var(--t1); }
.btn-danger   { background: transparent; color: var(--red); border-color: rgba(239,68,68,.25); }
.btn-danger:hover { background: rgba(239,68,68,.08); }
.btn-sm { padding: 6px 12px; font-size: 11px; }

/* ─── Stats grid ─── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 22px;
}
.stat-card {
  background: var(--s1);
  border: 1px solid var(--b1);
  border-radius: var(--r);
  padding: 18px 20px;
  position: relative;
  overflow: hidden;
  transition: border-color .2s;
}
.stat-card:hover { border-color: var(--b2); }
.stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
}
.stat-card.sc-total::before  { background: linear-gradient(90deg, var(--accent), var(--accent2)); }
.stat-card.sc-rev::before    { background: linear-gradient(90deg, var(--green), #16a34a); }
.stat-card.sc-done::before   { background: linear-gradient(90deg, #4ade80, var(--cyan)); }
.stat-card.sc-pend::before   { background: linear-gradient(90deg, var(--amber), #d97706); }
.stat-icon {
  width: 34px; height: 34px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 12px;
}
.stat-icon svg { width: 16px; height: 16px; }
.sc-total .stat-icon { background: rgba(91,123,248,.15); color: var(--accent); }
.sc-rev   .stat-icon { background: rgba(34,197,94,.12);  color: var(--green); }
.sc-done  .stat-icon { background: rgba(6,182,212,.12);  color: var(--cyan); }
.sc-pend  .stat-icon { background: rgba(245,158,11,.12); color: var(--amber); }
.stat-lbl {
  font-size: 10px;
  font-weight: 600;
  color: var(--t3);
  letter-spacing: .7px;
  text-transform: uppercase;
  margin-bottom: 6px;
}
.stat-val {
  font-size: 24px;
  font-weight: 700;
  letter-spacing: -0.5px;
  line-height: 1;
  font-family: 'JetBrains Mono', monospace;
}
.stat-sub {
  font-size: 11px;
  color: var(--t3);
  margin-top: 6px;
  font-family: 'JetBrains Mono', monospace;
  font-weight: 300;
}
.stat-sub.up   { color: #4ade80; }
.stat-sub.warn { color: var(--amber); }

/* ─── Filter bar ─── */
.filter-bar {
  background: var(--s1);
  border: 1px solid var(--b1);
  border-radius: var(--r);
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 18px;
}
.search-wrap {
  position: relative;
  flex: 1;
  min-width: 200px;
}
.search-wrap svg {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--t3);
  pointer-events: none;
  width: 13px; height: 13px;
}
.search-wrap input {
  width: 100%;
  padding: 8px 12px 8px 32px;
  background: var(--s2);
  border: 1px solid var(--b2);
  border-radius: var(--r-sm);
  color: var(--t1);
  font-family: 'Space Grotesk', sans-serif;
  font-size: 12px;
  outline: none;
  transition: border-color .15s;
}
.search-wrap input:focus { border-color: var(--accent); }
.search-wrap input::placeholder { color: var(--t3); }

.f-divider { width: 1px; height: 26px; background: var(--b2); flex-shrink: 0; }

select, input[type="date"] {
  background: var(--s2);
  border: 1px solid var(--b2);
  border-radius: var(--r-sm);
  color: var(--t2);
  font-family: 'Space Grotesk', sans-serif;
  font-size: 12px;
  padding: 8px 12px;
  outline: none;
  cursor: pointer;
  transition: border-color .15s;
  appearance: none;
}
select:focus, input[type="date"]:focus { border-color: var(--accent); color: var(--t1); }
input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.4); }

/* ─── Table card ─── */
.table-card {
  background: var(--s1);
  border: 1px solid var(--b1);
  border-radius: var(--r);
  overflow: hidden;
}
.tc-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 20px;
  border-bottom: 1px solid var(--b1);
  gap: 12px;
  flex-wrap: wrap;
}
.tc-title { font-size: 14px; font-weight: 700; }
.tc-count {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  color: var(--t3);
  font-weight: 300;
  margin-left: 8px;
}
.tc-actions { display: flex; gap: 8px; align-items: center; }

.tw { overflow-x: auto; }

table { width: 100%; border-collapse: collapse; font-size: 12px; }
thead tr { border-bottom: 1px solid var(--b1); }
th {
  padding: 9px 14px;
  text-align: left;
  font-size: 10px;
  font-weight: 600;
  color: var(--t3);
  letter-spacing: .7px;
  text-transform: uppercase;
  white-space: nowrap;
  user-select: none;
}
th[data-col] { cursor: pointer; transition: color .15s; }
th[data-col]:hover { color: var(--t1); }
th.sorted-asc::after  { content: ' ↑'; color: var(--accent); }
th.sorted-desc::after { content: ' ↓'; color: var(--accent); }

tbody tr {
  border-bottom: 1px solid var(--b1);
  transition: background .1s;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: var(--s2); }

td {
  padding: 10px 14px;
  color: var(--t1);
  vertical-align: middle;
}

/* ─── Row cells ─── */
.row-id {
  font-family: 'JetBrains Mono', monospace;
  font-size: 10px;
  color: var(--t3);
  font-weight: 400;
}
.ref-chip {
  font-family: 'JetBrains Mono', monospace;
  font-size: 10px;
  font-weight: 500;
  background: rgba(91,123,248,.12);
  color: #8AABFB;
  border: 1px solid rgba(91,123,248,.2);
  padding: 2px 8px;
  border-radius: 4px;
  letter-spacing: .4px;
  white-space: nowrap;
}
.name-cell { display: flex; align-items: center; gap: 8px; }
.avatar {
  width: 28px; height: 28px;
  border-radius: 7px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  display: flex; align-items: center; justify-content: center;
  font-size: 10px;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
  letter-spacing: .5px;
}
.name-txt { font-weight: 600; font-size: 12px; }
.loc {
  font-size: 11px;
  color: var(--t2);
  max-width: 135px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  display: block;
}
.fare-val {
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  font-weight: 500;
  color: var(--green);
}

/* ─── Badges ─── */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 9px;
  border-radius: 20px;
  letter-spacing: .3px;
  white-space: nowrap;
}
.badge::before {
  content: '';
  width: 4px; height: 4px;
  border-radius: 50%;
  background: currentColor;
  opacity: .7;
}
.badge-pending   { background: rgba(245,158,11,.12); color: #FBBF24; border: 1px solid rgba(245,158,11,.2); }
.badge-ongoing   { background: rgba(6,182,212,.12);  color: #22D3EE; border: 1px solid rgba(6,182,212,.2); }
.badge-completed { background: rgba(34,197,94,.12);  color: #4ADE80; border: 1px solid rgba(34,197,94,.2); }
.badge-cancelled { background: rgba(239,68,68,.12);  color: #F87171; border: 1px solid rgba(239,68,68,.2); }
.badge-unpaid    { background: rgba(239,68,68,.1);   color: #F87171; border: 1px solid rgba(239,68,68,.2); }
.badge-paid      { background: rgba(34,197,94,.12);  color: #4ADE80; border: 1px solid rgba(34,197,94,.2); }
.badge-refunded  { background: rgba(139,92,246,.12); color: #A78BFA; border: 1px solid rgba(139,92,246,.2); }

/* ─── Row actions ─── */
.row-acts { display: flex; gap: 3px; }
.act-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px; height: 26px;
  border-radius: var(--r-sm);
  border: 1px solid var(--b2);
  background: transparent;
  color: var(--t2);
  cursor: pointer;
  text-decoration: none;
  transition: all .15s;
}
.act-btn:hover         { background: var(--s2); color: var(--t1); }
.act-btn.danger:hover  { background: rgba(239,68,68,.08); color: var(--red); border-color: rgba(239,68,68,.3); }
.act-btn svg { width: 12px; height: 12px; }

/* ─── Empty state ─── */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 64px 20px;
  color: var(--t3);
  gap: 14px;
}
.empty-state svg { opacity: .25; width: 44px; height: 44px; }
.empty-state p { font-size: 13px; font-weight: 500; }

/* ─── Pagination ─── */
.pager {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 13px 16px;
  border-top: 1px solid var(--b1);
  flex-wrap: wrap;
}
.pg-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 30px; height: 30px;
  padding: 0 8px;
  border-radius: var(--r-sm);
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  border: 1px solid var(--b2);
  background: transparent;
  color: var(--t2);
  transition: all .15s;
  font-family: 'JetBrains Mono', monospace;
}
.pg-btn:hover:not(.disabled):not(.active) { background: var(--s2); color: var(--t1); }
.pg-btn.active  { background: var(--accent); color: #fff; border-color: var(--accent); }
.pg-btn.disabled { opacity: .3; pointer-events: none; }
.pg-info {
  font-size: 11px;
  color: var(--t3);
  margin-left: 8px;
  font-family: 'JetBrains Mono', monospace;
  font-weight: 300;
}

/* ─── Animations ─── */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
.stats-grid  { animation: fadeUp .28s ease both; }
.filter-bar  { animation: fadeUp .28s ease .06s both; }
.table-card  { animation: fadeUp .28s ease .10s both; }

tbody tr { animation: fadeUp .2s ease both; }
<?php for ($i = 1; $i <= 15; $i++): ?>
tbody tr:nth-child(<?= $i ?>) { animation-delay: <?= $i * 0.025 ?>s; }
<?php endfor; ?>

/* ─── Responsive ─── */
@media (max-width: 900px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 560px) {
  .stats-grid { grid-template-columns: 1fr; }
  .pw { padding: 20px 14px; }
}
</style>

<div class="pw">

  <!-- ── Page header ── -->
  <div class="ph">
    <div class="ph-left">
      <h1>Booking Records</h1>
      <p>
        <?= $stats['total'] ?> total entries
        <?= ($search || $status || $payment || $date_from || $date_to) ? '· filtered view' : '· all records' ?>
      </p>
    </div>
    <div class="ph-actions">
      <a href="?<?= http_build_query(array_merge($qBase, ['export' => 'csv'])) ?>" class="btn btn-ghost btn-sm">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export CSV
      </a>
      <?php if (isAdmin()): ?>
      <button class="btn btn-danger btn-sm" onclick="startDeleteAll()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
        Delete All
      </button>
      <?php endif; ?>
      <a href="create.php" class="btn btn-primary btn-sm">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Booking
      </a>
    </div>
  </div>

  <!-- ── Stats ── -->
  <div class="stats-grid">
    <div class="stat-card sc-total">
      <div class="stat-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 17H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3"/><path d="m15 14 2 2 4-4"/><rect x="9" y="11" width="10" height="10" rx="2"/></svg>
      </div>
      <div class="stat-lbl">Total Bookings</div>
      <div class="stat-val" style="color:var(--t1)"><?= number_format($stats['total']) ?></div>
      <div class="stat-sub"><?= $stats['ongoing'] ?> currently ongoing</div>
    </div>
    <div class="stat-card sc-rev">
      <div class="stat-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      </div>
      <div class="stat-lbl">Total Revenue</div>
      <div class="stat-val" style="color:var(--green)">₱<?= number_format($stats['revenue'] ?? 0, 0) ?></div>
      <div class="stat-sub up">from all completed rides</div>
    </div>
    <div class="stat-card sc-done">
      <div class="stat-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div class="stat-lbl">Completed</div>
      <div class="stat-val" style="color:#4ADE80"><?= number_format($stats['completed']) ?></div>
      <div class="stat-sub">
        <?= $stats['total'] > 0 ? round($stats['completed'] / $stats['total'] * 100) : 0 ?>% completion rate
      </div>
    </div>
    <div class="stat-card sc-pend">
      <div class="stat-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div class="stat-lbl">Pending</div>
      <div class="stat-val" style="color:#FBBF24"><?= number_format($stats['pending']) ?></div>
      <div class="stat-sub warn"><?= $stats['cancelled'] ?> cancelled total</div>
    </div>
  </div>

  <!-- ── Filters ── -->
  <form method="GET" id="filter-form" class="filter-bar">
    <div class="search-wrap">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="search" name="search" id="search-input"
             placeholder="Search customer, driver, location, ref…"
             value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="f-divider"></div>
    <select name="ride_status" onchange="this.form.submit()">
      <option value="">All Statuses</option>
      <?php foreach (['Pending','Ongoing','Completed','Cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <select name="payment_status" onchange="this.form.submit()">
      <option value="">All Payments</option>
      <?php foreach (['Unpaid','Paid','Refunded'] as $s): ?>
        <option value="<?= $s ?>" <?= $payment === $s ? 'selected' : '' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>"
           title="From date" onchange="this.form.submit()">
    <input type="date" name="date_to"   value="<?= htmlspecialchars($date_to) ?>"
           title="To date"   onchange="this.form.submit()">
    <button type="submit" class="btn btn-primary btn-sm">
      <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      Search
    </button>
    <?php if ($search || $status || $payment || $date_from || $date_to): ?>
      <a href="index.php" class="btn btn-ghost btn-sm">✕ Clear</a>
    <?php endif; ?>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
    <input type="hidden" name="dir"  value="<?= htmlspecialchars($dir) ?>">
  </form>

  <!-- ── Table ── -->
  <div class="table-card">
    <div class="tc-head">
      <div>
        <span class="tc-title">Bookings</span>
        <span class="tc-count"><?= $total ?> records</span>
      </div>
      <div class="tc-actions">
        <span style="font-size:11px;color:var(--t3);">Sort by:</span>
        <select style="padding:5px 10px;font-size:11px;" onchange="location='?'+new URLSearchParams({...Object.fromEntries(new URLSearchParams(location.search)),...{sort:this.value.split(':')[0],dir:this.value.split(':')[1]||'desc',page:1}}).toString()">
          <?php
          $sortOpts = [
            'id:desc'          => 'Newest first',
            'id:asc'           => 'Oldest first',
            'fare:desc'        => 'Fare (high–low)',
            'fare:asc'         => 'Fare (low–high)',
            'customer_name:asc'=> 'Customer A–Z',
            'ride_date:desc'   => 'Date (latest)',
          ];
          foreach ($sortOpts as $val => $lbl):
            $sel = ($val === "$sort:$dir") ? 'selected' : '';
          ?>
          <option value="<?= $val ?>" <?= $sel ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="tw">
      <table>
        <thead>
          <tr>
            <?php
            $cols = [
              '#'        => 'id',
              'REF'      => '',
              'CUSTOMER' => 'customer_name',
              'DRIVER'   => 'driver_name',
              'PICKUP'   => '',
              'DROP-OFF' => '',
              'DATE'     => 'ride_date',
              'FARE'     => 'fare',
              'STATUS'   => 'ride_status',
              'PAYMENT'  => 'payment_status',
              'ACTIONS'  => '',
            ];
            foreach ($cols as $label => $col):
              $cls = ($sort === $col && $col !== '') ? ('sorted-' . $dir) : '';
            ?>
            <th <?= $col ? 'data-col="'.$col.'" class="'.$cls.'"' : '' ?>><?= $label ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows): foreach ($rows as $row):
            $words = explode(' ', $row['customer_name']);
            $init  = strtoupper(substr($row['customer_name'], 0, 1));
            if (count($words) > 1) $init = strtoupper($words[0][0] . end($words)[0]);
            $rsClass = 'badge-' . strtolower($row['ride_status']);
            $ppClass = 'badge-' . strtolower($row['payment_status']);
          ?>
          <tr>
            <td><span class="row-id"><?= $row['id'] ?></span></td>
            <td><span class="ref-chip"><?= htmlspecialchars($row['booking_ref']) ?></span></td>
            <td>
              <div class="name-cell">
                <div class="avatar"><?= htmlspecialchars($init) ?></div>
                <span class="name-txt"><?= htmlspecialchars($row['customer_name']) ?></span>
              </div>
            </td>
            <td style="color:var(--t2);font-size:12px;"><?= htmlspecialchars($row['driver_name']) ?></td>
            <td><span class="loc" title="<?= htmlspecialchars($row['pickup_location']) ?>">📍 <?= htmlspecialchars($row['pickup_location']) ?></span></td>
            <td><span class="loc" title="<?= htmlspecialchars($row['drop_off_location']) ?>">🏁 <?= htmlspecialchars($row['drop_off_location']) ?></span></td>
            <td style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--t2);white-space:nowrap;">
              <?= htmlspecialchars($row['ride_date']) ?>
            </td>
            <td><span class="fare-val">₱<?= number_format($row['fare'], 2) ?></span></td>
            <td><span class="badge <?= $rsClass ?>"><?= htmlspecialchars($row['ride_status']) ?></span></td>
            <td><span class="badge <?= $ppClass ?>"><?= htmlspecialchars($row['payment_status']) ?></span></td>
            <td>
              <div class="row-acts">
                <a href="view.php?id=<?= $row['id'] ?>" class="act-btn" title="View">
                  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </a>
                <a href="edit.php?id=<?= $row['id'] ?>" class="act-btn" title="Edit">
                  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                </a>
                <?php if (isAdmin()): ?>
                <button class="act-btn danger" title="Archive"
                  onclick="confirmAction('Archive Booking',<?= json_encode('Move booking ' . $row['booking_ref'] . ' to archive?') ?>,()=>archiveRow(<?= $row['id'] ?>),'Archive')">
                  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="11">
            <div class="empty-state">
              <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M9 17H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3"/>
                <path d="m15 14 2 2 4-4"/><rect x="9" y="11" width="10" height="10" rx="2"/>
              </svg>
              <p><?= $search ? 'No bookings matched your search.' : 'No bookings yet. Create your first one.' ?></p>
              <?php if (!$search): ?>
                <a href="create.php" class="btn btn-primary btn-sm" style="margin-top:4px;">+ New Booking</a>
              <?php endif; ?>
            </div>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- ── Pagination ── -->
    <?php if ($totalPages > 1): ?>
    <div class="pager">
      <a href="?<?= http_build_query(array_merge($qBase, ['page' => max(1, $page - 1)])) ?>"
         class="pg-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹</a>
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <?php if ($p <= 2 || $p >= $totalPages - 1 || abs($p - $page) <= 1): ?>
          <a href="?<?= http_build_query(array_merge($qBase, ['page' => $p])) ?>"
             class="pg-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php elseif (abs($p - $page) === 2): ?>
          <span class="pg-btn disabled">…</span>
        <?php endif; ?>
      <?php endfor; ?>
      <a href="?<?= http_build_query(array_merge($qBase, ['page' => min($totalPages, $page + 1)])) ?>"
         class="pg-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">›</a>
      <span class="pg-info">page <?= $page ?> / <?= $totalPages ?></span>
    </div>
    <?php endif; ?>
  </div>

</div>

<!---------------- SCRIPTS ---------------->
<script>
const CSRF = <?= json_encode(csrfToken()) ?>;

/* ── Column sort ── */
document.querySelectorAll('th[data-col]').forEach(th => {
  th.addEventListener('click', () => {
    const col    = th.dataset.col;
    const cur    = <?= json_encode($sort) ?>;
    const curDir = <?= json_encode($dir) ?>;
    const newDir = (col === cur && curDir === 'asc') ? 'desc' : 'asc';
    const p = new URLSearchParams(window.location.search);
    p.set('sort', col);
    p.set('dir', newDir);
    p.delete('page');
    window.location = '?' + p.toString();
  });
});

/* ── Debounced search ── */
const si = document.getElementById('search-input');
let dbt;
si.addEventListener('input', () => {
  clearTimeout(dbt);
  dbt = setTimeout(() => document.getElementById('filter-form').submit(), 420);
});

/* ── AJAX helper ── */
function post(body) {
  return fetch('actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body + '&csrf_token=' + encodeURIComponent(CSRF),
  }).then(r => r.json());
}

/* ── Archive row ── */
function archiveRow(id) {
  showLoading();
  post('action=archive&id=' + id)
    .then(d => {
      hideLoading();
      if (d.ok) { showToast('Booking archived', 'success'); setTimeout(() => location.reload(), 600); }
      else showToast(d.err || 'Error', 'error');
    })
    .catch(() => { hideLoading(); showToast('Network error', 'error'); });
}

/* ── Delete all ── */
function startDeleteAll() {
  post('action=delete_all_nonce')
    .then(d => {
      if (!d.nonce) { showToast('Could not get confirmation token', 'error'); return; }
      confirmAction(
        'Delete All Bookings',
        'This will permanently delete ALL active bookings. This cannot be undone.',
        () => deleteAll(d.nonce),
        'Delete All'
      );
    })
    .catch(() => showToast('Network error', 'error'));
}

function deleteAll(nonce) {
  showLoading();
  post('action=delete_all&nonce=' + encodeURIComponent(nonce))
    .then(d => {
      hideLoading();
      if (d.ok) { showToast('All bookings deleted', 'success'); setTimeout(() => location.reload(), 600); }
      else showToast(d.err || 'Error', 'error');
    })
    .catch(() => { hideLoading(); showToast('Network error', 'error'); });
}
</script>

<?php layout_foot(); ?>