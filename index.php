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

$cStmt = $conn->prepare("SELECT COUNT(*) FROM bookings b $whereSQL");
if ($types) $cStmt->bind_param($types, ...$params);
$cStmt->execute();
$total = $cStmt->get_result()->fetch_row()[0];

$totalPages = max(1, (int)ceil($total / $PER_PAGE));
$page       = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset     = ($page - 1) * $PER_PAGE;

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

<style>
  @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@300;400;500&display=swap');

  :root {
    --bg:        #0a0b0f;
    --surface:   #111318;
    --surface2:  #181b22;
    --border:    #1e2130;
    --border2:   #252a3a;
    --accent:    #4f6ef7;
    --accent2:   #7c3aed;
    --text1:     #e8eaf2;
    --text2:     #8b90a8;
    --text3:     #4e5368;
    --green:     #22c55e;
    --orange:    #f59e0b;
    --red:       #ef4444;
    --cyan:      #06b6d4;
    --radius:    10px;
    --radius-sm: 6px;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: var(--bg);
    color: var(--text1);
    font-family: 'Syne', sans-serif;
    min-height: 100vh;
  }

  /* ── Page wrapper ── */
  .page-wrap {
    max-width: 1400px;
    margin: 0 auto;
    padding: 32px 24px;
  }

  /* ── Page header ── */
  .page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 28px;
    gap: 16px;
    flex-wrap: wrap;
  }
  .page-header-left h1 {
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -0.5px;
    line-height: 1;
    background: linear-gradient(135deg, #e8eaf2 0%, #8b90a8 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }
  .page-header-left p {
    font-size: 13px;
    color: var(--text3);
    margin-top: 6px;
    font-family: 'DM Mono', monospace;
    font-weight: 300;
  }
  .header-actions {
    display: flex;
    gap: 8px;
    align-items: center;
  }

  /* ── Buttons ── */
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: 'Syne', sans-serif;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: var(--radius-sm);
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s ease;
    white-space: nowrap;
    letter-spacing: 0.2px;
  }
  .btn-primary {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
  }
  .btn-primary:hover { background: #3d5be0; border-color: #3d5be0; transform: translateY(-1px); }
  .btn-ghost {
    background: transparent;
    color: var(--text2);
    border-color: var(--border2);
  }
  .btn-ghost:hover { background: var(--surface2); color: var(--text1); border-color: var(--border2); }
  .btn-danger {
    background: transparent;
    color: var(--red);
    border-color: rgba(239,68,68,.3);
  }
  .btn-danger:hover { background: rgba(239,68,68,.1); }
  .btn-sm { padding: 6px 12px; font-size: 12px; }
  .btn-icon { padding: 6px 8px; }

  /* ── Filter bar ── */
  .filter-bar {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
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
    color: var(--text3);
    pointer-events: none;
  }
  .search-wrap input {
    width: 100%;
    padding: 9px 12px 9px 34px;
    background: var(--surface2);
    border: 1px solid var(--border2);
    border-radius: var(--radius-sm);
    color: var(--text1);
    font-family: 'Syne', sans-serif;
    font-size: 13px;
    outline: none;
    transition: border-color .15s;
  }
  .search-wrap input:focus { border-color: var(--accent); }
  .search-wrap input::placeholder { color: var(--text3); }

  select, input[type="date"] {
    background: var(--surface2);
    border: 1px solid var(--border2);
    border-radius: var(--radius-sm);
    color: var(--text1);
    font-family: 'Syne', sans-serif;
    font-size: 13px;
    padding: 9px 12px;
    outline: none;
    cursor: pointer;
    transition: border-color .15s;
    appearance: none;
  }
  select:focus, input[type="date"]:focus { border-color: var(--accent); }
  input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.5); }

  .filter-divider {
    width: 1px;
    height: 28px;
    background: var(--border2);
    flex-shrink: 0;
  }

  /* ── Table card ── */
  .table-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
  }
  .table-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    gap: 12px;
    flex-wrap: wrap;
  }
  .table-title {
    font-size: 15px;
    font-weight: 700;
    letter-spacing: -0.2px;
  }
  .table-count {
    font-family: 'DM Mono', monospace;
    font-size: 12px;
    color: var(--text3);
    font-weight: 300;
    margin-left: 8px;
  }
  .table-actions { display: flex; gap: 8px; align-items: center; }

  .table-wrap { overflow-x: auto; }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }
  thead tr {
    border-bottom: 1px solid var(--border);
  }
  th {
    padding: 10px 14px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    color: var(--text3);
    letter-spacing: 0.6px;
    text-transform: uppercase;
    white-space: nowrap;
    user-select: none;
    cursor: default;
  }
  th[data-col] {
    cursor: pointer;
    transition: color .15s;
  }
  th[data-col]:hover { color: var(--text1); }
  th.sorted-asc::after  { content: ' ↑'; color: var(--accent); }
  th.sorted-desc::after { content: ' ↓'; color: var(--accent); }

  tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .1s;
  }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: var(--surface2); }

  td {
    padding: 11px 14px;
    color: var(--text1);
    vertical-align: middle;
  }

  /* ── Row ID ── */
  .row-id {
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    color: var(--text3);
  }

  /* ── Booking ref ── */
  .ref-chip {
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    font-weight: 500;
    background: rgba(79,110,247,.12);
    color: #7a96f9;
    border: 1px solid rgba(79,110,247,.2);
    padding: 3px 8px;
    border-radius: 4px;
    letter-spacing: 0.5px;
    white-space: nowrap;
  }

  /* ── Name cell ── */
  .name-cell {
    display: flex;
    align-items: center;
    gap: 9px;
  }
  .avatar {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    letter-spacing: 0.5px;
  }
  .name-text { font-weight: 600; font-size: 13px; }

  /* ── Location ── */
  .loc {
    font-size: 12px;
    color: var(--text2);
    max-width: 140px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* ── Fare ── */
  .fare-val {
    font-family: 'DM Mono', monospace;
    font-size: 13px;
    font-weight: 500;
    color: var(--green);
  }

  /* ── Badges ── */
  .badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 20px;
    letter-spacing: 0.3px;
    white-space: nowrap;
  }
  .badge::before {
    content: '';
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: currentColor;
    opacity: .7;
  }
  .badge-pending   { background: rgba(245,158,11,.12); color: #fbbf24; border: 1px solid rgba(245,158,11,.2); }
  .badge-ongoing   { background: rgba(6,182,212,.12);  color: #22d3ee; border: 1px solid rgba(6,182,212,.2); }
  .badge-completed { background: rgba(34,197,94,.12);  color: #4ade80; border: 1px solid rgba(34,197,94,.2); }
  .badge-cancelled { background: rgba(239,68,68,.12);  color: #f87171; border: 1px solid rgba(239,68,68,.2); }
  .badge-unpaid    { background: rgba(239,68,68,.1);   color: #f87171; border: 1px solid rgba(239,68,68,.2); }
  .badge-paid      { background: rgba(34,197,94,.12);  color: #4ade80; border: 1px solid rgba(34,197,94,.2); }
  .badge-refunded  { background: rgba(124,58,237,.12); color: #a78bfa; border: 1px solid rgba(124,58,237,.2); }

  /* ── Row actions ── */
  .row-actions { display: flex; gap: 4px; }
  .action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border2);
    background: transparent;
    color: var(--text2);
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
  }
  .action-btn:hover { background: var(--surface2); color: var(--text1); border-color: var(--border2); }
  .action-btn.danger:hover { background: rgba(239,68,68,.1); color: var(--red); border-color: rgba(239,68,68,.3); }

  /* ── Empty state ── */
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    color: var(--text3);
    gap: 14px;
  }
  .empty-state svg { opacity: .3; }
  .empty-state p { font-size: 14px; font-weight: 500; }

  /* ── Pagination ── */
  .pagination {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 14px 16px;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
  }
  .page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 10px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid var(--border2);
    background: transparent;
    color: var(--text2);
    transition: all .15s;
    font-family: 'DM Mono', monospace;
  }
  .page-btn:hover:not(.disabled):not(.active) { background: var(--surface2); color: var(--text1); }
  .page-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }
  .page-btn.disabled { opacity: .3; pointer-events: none; }
  .page-info {
    font-size: 12px;
    color: var(--text3);
    margin-left: 8px;
    font-family: 'DM Mono', monospace;
    font-weight: 300;
  }

  /* ── Animations ── */
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .filter-bar, .table-card { animation: fadeIn .25s ease both; }
  .table-card { animation-delay: .05s; }

  tbody tr {
    animation: fadeIn .2s ease both;
  }
  <?php for ($i = 1; $i <= 15; $i++): ?>
  tbody tr:nth-child(<?php echo $i; ?>) { animation-delay: <?php echo $i * 0.02; ?>s; }
  <?php endfor; ?>
</style>

<div class="page-wrap">

  <!-- Page header -->
  <div class="page-header">
    <div class="page-header-left">
      <h1>Booking Records</h1>
      <p><?php echo $total; ?> total entries <?php echo ($search || $status || $payment || $date_from || $date_to) ? '· filtered' : ''; ?></p>
    </div>
    <div class="header-actions">
      <a href="?<?php echo http_build_query(array_merge($qBase, ['export' => 'csv'])); ?>" class="btn btn-ghost btn-sm">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export CSV
      </a>
      <?php if (isAdmin()): ?>
      <button class="btn btn-danger btn-sm" onclick="startDeleteAll()">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
        Delete All
      </button>
      <?php endif; ?>
      <a href="create.php" class="btn btn-primary btn-sm">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Booking
      </a>
    </div>
  </div>

  <!-- Filter bar -->
  <form method="GET" id="filter-form" class="filter-bar">
    <div class="search-wrap">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="search" name="search" id="search-input" placeholder="Search name, driver, location, ref…" value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="filter-divider"></div>
    <select name="ride_status" onchange="this.form.submit()">
      <option value="">All Statuses</option>
      <?php foreach (['Pending','Ongoing','Completed','Cancelled'] as $s): ?>
        <option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
      <?php endforeach; ?>
    </select>
    <select name="payment_status" onchange="this.form.submit()">
      <option value="">All Payments</option>
      <?php foreach (['Unpaid','Paid','Refunded'] as $s): ?>
        <option value="<?php echo $s; ?>" <?php echo $payment === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" title="From date" onchange="this.form.submit()">
    <input type="date" name="date_to"   value="<?php echo htmlspecialchars($date_to); ?>"   title="To date"   onchange="this.form.submit()">
    <button type="submit" class="btn btn-primary btn-sm">Search</button>
    <?php if ($search || $status || $payment || $date_from || $date_to): ?>
      <a href="index.php" class="btn btn-ghost btn-sm">✕ Clear</a>
    <?php endif; ?>
    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
    <input type="hidden" name="dir"  value="<?php echo htmlspecialchars($dir); ?>">
  </form>

  <!-- Table card -->
  <div class="table-card">
    <div class="table-card-header">
      <div>
        <span class="table-title">Bookings</span>
        <span class="table-count"><?php echo $total; ?> records</span>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <?php
            $cols = [
              '#'        => 'id',
              'REF'      => 'booking_ref',
              'CUSTOMER' => 'customer_name',
              'DRIVER'   => 'driver_name',
              'PICKUP'   => 'pickup_location',
              'DROP-OFF' => 'drop_off_location',
              'DATE'     => 'ride_date',
              'FARE'     => 'fare',
              'STATUS'   => 'ride_status',
              'PAYMENT'  => 'payment_status',
              'ACTIONS'  => '',
            ];
            foreach ($cols as $label => $col):
              $cls = ($sort === $col) ? ('sorted-' . $dir) : '';
            ?>
            <th <?php echo $col ? 'data-col="' . $col . '" class="' . $cls . '"' : ''; ?>><?php echo $label; ?></th>
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
            <td><span class="row-id"><?php echo $row['id']; ?></span></td>
            <td><span class="ref-chip"><?php echo htmlspecialchars($row['booking_ref']); ?></span></td>
            <td>
              <div class="name-cell">
                <div class="avatar"><?php echo htmlspecialchars($init); ?></div>
                <span class="name-text"><?php echo htmlspecialchars($row['customer_name']); ?></span>
              </div>
            </td>
            <td style="color:var(--text2);font-size:13px;"><?php echo htmlspecialchars($row['driver_name']); ?></td>
            <td><span class="loc" title="<?php echo htmlspecialchars($row['pickup_location']); ?>">📍 <?php echo htmlspecialchars($row['pickup_location']); ?></span></td>
            <td><span class="loc" title="<?php echo htmlspecialchars($row['drop_off_location']); ?>">🏁 <?php echo htmlspecialchars($row['drop_off_location']); ?></span></td>
            <td style="font-family:'DM Mono',monospace;font-size:12px;color:var(--text2);white-space:nowrap;"><?php echo htmlspecialchars($row['ride_date']); ?></td>
            <td><span class="fare-val">₱<?php echo number_format($row['fare'], 2); ?></span></td>
            <td><span class="badge <?php echo $rsClass; ?>"><?php echo htmlspecialchars($row['ride_status']); ?></span></td>
            <td><span class="badge <?php echo $ppClass; ?>"><?php echo htmlspecialchars($row['payment_status']); ?></span></td>
            <td>
              <div class="row-actions">
                <a href="view.php?id=<?php echo $row['id']; ?>" class="action-btn" title="View">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </a>
                <a href="edit.php?id=<?php echo $row['id']; ?>" class="action-btn" title="Edit">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                </a>
                <?php if (isAdmin()): ?>
                <button class="action-btn danger" title="Archive"
                  onclick="confirmAction('Archive Booking', <?php echo json_encode('Move booking ' . $row['booking_ref'] . ' to archive?'); ?>, () => archiveRow(<?php echo $row['id']; ?>), 'Archive')">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="11">
            <div class="empty-state">
              <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 17H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3"/><path d="m15 14 2 2 4-4"/><rect x="9" y="11" width="10" height="10" rx="2"/></svg>
              <p><?php echo $search ? 'No bookings matched your search.' : 'No bookings yet. Create your first one.'; ?></p>
              <?php if (!$search): ?>
              <a href="create.php" class="btn btn-primary btn-sm" style="margin-top:4px;">+ New Booking</a>
              <?php endif; ?>
            </div>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <a href="?<?php echo http_build_query(array_merge($qBase, ['page' => max(1, $page - 1)])); ?>"
         class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">‹</a>
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <?php if ($p <= 2 || $p >= $totalPages - 1 || abs($p - $page) <= 1): ?>
          <a href="?<?php echo http_build_query(array_merge($qBase, ['page' => $p])); ?>"
             class="page-btn <?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
        <?php elseif (abs($p - $page) === 2): ?>
          <span class="page-btn disabled">…</span>
        <?php endif; ?>
      <?php endfor; ?>
      <a href="?<?php echo http_build_query(array_merge($qBase, ['page' => min($totalPages, $page + 1)])); ?>"
         class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">›</a>
      <span class="page-info">page <?php echo $page; ?> / <?php echo $totalPages; ?></span>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
const CSRF = <?php echo json_encode(csrfToken()); ?>;

// Column sort
document.querySelectorAll('th[data-col]').forEach(th => {
  th.addEventListener('click', () => {
    const col = th.dataset.col;
    const cur = <?php echo json_encode($sort); ?>;
    const curDir = <?php echo json_encode($dir); ?>;
    const newDir = (col === cur && curDir === 'asc') ? 'desc' : 'asc';
    const params = new URLSearchParams(window.location.search);
    params.set('sort', col);
    params.set('dir', newDir);
    params.delete('page');
    window.location = '?' + params.toString();
  });
});

// Debounced search
const searchInput = document.getElementById('search-input');
let debounce;
searchInput.addEventListener('input', () => {
  clearTimeout(debounce);
  debounce = setTimeout(() => document.getElementById('filter-form').submit(), 420);
});

function post(body) {
  return fetch('actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body + '&csrf_token=' + encodeURIComponent(CSRF),
  }).then(r => r.json());
}

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