<?php
session_start();
require 'db.php';
require 'auth.php';
require 'layout.php';

$stmt = $conn->prepare("SELECT * FROM bookings WHERE is_archived = 1 ORDER BY updated_at DESC");
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

layout_head('Archive');
?>
<div class="card">
  <div class="card-header">
    <span class="card-title">Archived Bookings <span style="font-size:13px;color:var(--text3);font-weight:400;">(<?php echo count($rows); ?>)</span></span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Ref</th><th>Customer</th><th>Driver</th><th>Date</th><th>Fare</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($rows): foreach ($rows as $row):
          $init  = strtoupper(substr($row['customer_name'], 0, 1));
          $words = explode(' ', $row['customer_name']);
          if (count($words) > 1) $init = strtoupper($words[0][0] . end($words)[0]);
        ?>
        <tr>
          <td style="color:var(--text3);font-size:12px;"><?php echo $row['id']; ?></td>
          <td><span class="ref-code"><?php echo htmlspecialchars($row['booking_ref']); ?></span></td>
          <td>
            <div class="name-cell">
              <div class="avatar"><?php echo $init; ?></div>
              <?php echo htmlspecialchars($row['customer_name']); ?>
            </div>
          </td>
          <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
          <td><?php echo htmlspecialchars($row['ride_date']); ?></td>
          <td class="fare-val">₱<?php echo number_format($row['fare'], 2); ?></td>
          <td><span class="badge badge-<?php echo strtolower($row['ride_status']); ?>"><?php echo htmlspecialchars($row['ride_status']); ?></span></td>
          <td>
            <div style="display:flex;gap:5px;">
              <?php if (isAdmin()): ?>
              <button class="btn btn-gray btn-sm"
                onclick="confirmAction('Restore Booking', <?php echo json_encode('Move ' . $row['booking_ref'] . ' back to active?'); ?>, () => restoreRow(<?php echo $row['id']; ?>), 'Restore')">
                Restore
              </button>
              <button class="btn btn-danger btn-sm"
                onclick="confirmAction('Permanently Delete', <?php echo json_encode('This will permanently delete ' . $row['booking_ref'] . '. This cannot be undone.'); ?>, () => deleteRow(<?php echo $row['id']; ?>), 'Delete')">
                Delete
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <svg width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
            <p>No archived bookings.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
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

function restoreRow(id) {
  showLoading();
  post('action=restore&id=' + id)
    .then(d => {
      hideLoading();
      if (d.ok) { showToast('Booking restored', 'success'); setTimeout(() => location.reload(), 600); }
      else showToast(d.err || 'Error', 'error');
    })
    .catch(() => { hideLoading(); showToast('Network error', 'error'); });
}

function deleteRow(id) {
  showLoading();
  post('action=delete&id=' + id)
    .then(d => {
      hideLoading();
      if (d.ok) { showToast('Booking permanently deleted', 'success'); setTimeout(() => location.reload(), 600); }
      else showToast(d.err || 'Error', 'error');
    })
    .catch(() => { hideLoading(); showToast('Network error', 'error'); });
}
</script>

<?php layout_foot(); ?>
