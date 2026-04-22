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

// ── Build WHERE ─────────────────────────────────────────────
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

// ── Export CSV (before paginated query) ─────────────────────
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

// ── Count ────────────────────────────────────────────────────
$cStmt = $conn->prepare("SELECT COUNT(*) FROM bookings b $whereSQL");
if ($types) $cStmt->bind_param($types, ...$params);
$cStmt->execute();
$total = $cStmt->get_result()->fetch_row()[0];

$totalPages = max(1, (int)ceil($total / $PER_PAGE));
$page       = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset     = ($page - 1) * $PER_PAGE;

// ── Fetch page ───────────────────────────────────────────────
$sql  = "SELECT b.*, u.full_name AS creator FROM bookings b LEFT JOIN users u ON b.created_by = u.id $whereSQL ORDER BY b.`$sort` $dir LIMIT $PER_PAGE OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Safe query-string base for pagination/export links ───────
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

<!-- Filter bar -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-body" style="padding:14px 20px;">
    <form method="GET" id="filter-form" class="filter-bar">
      <div class="search-wrap">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="search" name="search" id="search-input" placeholder="Search name, driver, location, ref…" value="<?php echo htmlspecialchars($search); ?>">
      </div>
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
      <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" title="From date" style="width:145px;" onchange="this.form.submit()">
      <input type="date" name="date_to"   value="<?php echo htmlspecialchars($date_to); ?>"   title="To date"   style="width:145px;" onchange="this.form.submit()">
      <button type="submit" class="btn btn-primary">Search</button>
      <?php if ($search || $status || $payment || $date_from || $date_to): ?>
        <a href="index.php" class="btn btn-gray">Clear</a>
      <?php endif; ?>
      <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
      <input type="hidden" name="dir"  value="<?php echo htmlspecialchars($dir); ?>">
    </form>
  </div>
</div>

<!-- Table card -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Booking Records <span style="font-size:13px;color:var(--text3);font-weight:400;">(<?php echo $total; ?> total)</span></span>
    <div style="display:flex;gap:8px;">
      <a href="?<?php echo http_build_query(array_merge($qBase, ['export' => 'csv'])); ?>" class="btn btn-gray btn-sm">⬇ Export CSV</a>
      <a href="create.php" class="btn btn-primary btn-sm">+ Add Booking</a>
      <?php if (isAdmin()): ?>
      <button class="btn btn-danger btn-sm" onclick="startDeleteAll()">Delete All</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <?php
          $cols = ['#'=>'id','Ref'=>'booking_ref','Customer'=>'customer_name','Driver'=>'driver_name',
                   'Pickup'=>'pickup_location','Drop-off'=>'drop_off_location','Date'=>'ride_date',
                   'Fare'=>'fare','Status'=>'ride_status','Payment'=>'payment_status','Actions'=>''];
          foreach ($cols as $label => $col):
            $cls = ($sort === $col) ? ('sorted-' . $dir) : '';
          ?>
          <th <?php echo $col ? 'data-col="' . $col . '" class="' . $cls . '"' : ''; ?>><?php echo $label; ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows): foreach ($rows as $row):
          $init  = strtoupper(substr($row['customer_name'], 0, 1));
          $words = explode(' ', $row['customer_name']);
          if (count($words) > 1) $init = strtoupper($words[0][0] . end($words)[0]);
          $rsClass = 'badge-' . strtolower($row['ride_status']);
          $ppClass = 'badge-' . strtolower($row['payment_status']);
        ?>
        <tr>
          <td style="color:var(--text3);font-size:12px;"><?php echo $row['id']; ?></td>
          <td><span class="ref-code"><?php echo htmlspecialchars($row['booking_ref']); ?></span></td>
          <td>
            <div class="name-cell">
              <div class="avatar"><?php echo htmlspecialchars($init); ?></div>
              <?php echo htmlspecialchars($row['customer_name']); ?>
            </div>
          </td>
          <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
          <td><span class="location-pill">📍 <?php echo htmlspecialchars($row['pickup_location']); ?></span></td>
          <td><span class="location-pill">🏁 <?php echo htmlspecialchars($row['drop_off_location']); ?></span></td>
          <td style="white-space:nowrap;"><?php echo htmlspecialchars($row['ride_date']); ?></td>
          <td><span class="fare-val">₱<?php echo number_format($row['fare'], 2); ?></span></td>
          <td><span class="badge <?php echo $rsClass; ?>"><?php echo htmlspecialchars($row['ride_status']); ?></span></td>
          <td><span class="badge <?php echo $ppClass; ?>"><?php echo htmlspecialchars($row['payment_status']); ?></span></td>
          <td>
            <div style="display:flex;gap:5px;">
              <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-gray btn-sm btn-icon" title="View">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </a>
              <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-gray btn-sm btn-icon" title="Edit">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
              </a>
              <?php if (isAdmin()): ?>
              <button class="btn btn-danger btn-sm btn-icon" title="Archive"
                onclick="confirmAction('Archive Booking', <?php echo json_encode('Move booking ' . $row['booking_ref'] . ' to archive?'); ?>, () => archiveRow(<?php echo $row['id']; ?>), 'Archive')">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="11">
          <div class="empty-state">
            <svg width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 17H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3"/><path d="m15 14 2 2 4-4"/><rect x="9" y="11" width="10" height="10" rx="2"/></svg>
            <p><?php echo $search ? 'No bookings matched your search.' : 'No bookings yet.'; ?></p>
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
    <span style="font-size:13px;color:var(--text3);margin-left:8px;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
  </div>
  <?php endif; ?>
</div>

<script>
const CSRF = <?php echo json_encode(csrfToken()); ?>;

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
  // First fetch a one-time nonce, then show confirmation modal
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
