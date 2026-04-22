<?php
session_start();
require 'db.php';
require 'auth.php';
require 'layout.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// Staff can only view their own bookings; admins can view any
$user = currentUser();
if ($user['role'] === 'admin') {
    $stmt = $conn->prepare(
        "SELECT b.*, u.full_name AS creator FROM bookings b LEFT JOIN users u ON b.created_by = u.id WHERE b.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare(
        "SELECT b.*, u.full_name AS creator FROM bookings b LEFT JOIN users u ON b.created_by = u.id WHERE b.id = ? AND b.created_by = ? LIMIT 1"
    );
    $stmt->bind_param('ii', $id, $user['id']);
}
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) { header('Location: index.php'); exit; }

layout_head('Booking ' . htmlspecialchars($row['booking_ref']));
$rsClass = 'badge-' . strtolower($row['ride_status']);
$ppClass = 'badge-' . strtolower($row['payment_status']);
?>
<div style="max-width:660px;">
  <div style="margin-bottom:18px;display:flex;align-items:center;gap:10px;">
    <a href="index.php" class="btn btn-gray btn-sm">← Back</a>
    <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-primary btn-sm">Edit</a>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">Booking Detail</span>
      <span class="ref-code"><?php echo htmlspecialchars($row['booking_ref']); ?></span>
    </div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
        <?php
        $fields = [
            'Customer'   => $row['customer_name'],
            'Driver'     => $row['driver_name'],
            'Pickup'     => $row['pickup_location'],
            'Drop-off'   => $row['drop_off_location'],
            'Ride Date'  => $row['ride_date'],
            'Fare'       => '₱' . number_format($row['fare'], 2),
            'Created By' => $row['creator'] ?? '—',
            'Created At' => $row['created_at'],
        ];
        foreach ($fields as $k => $v): ?>
        <div>
          <div style="font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;"><?php echo $k; ?></div>
          <div style="font-size:14px;color:var(--text);"><?php echo htmlspecialchars($v); ?></div>
        </div>
        <?php endforeach; ?>
        <div>
          <div style="font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;">Ride Status</div>
          <span class="badge <?php echo $rsClass; ?>"><?php echo htmlspecialchars($row['ride_status']); ?></span>
        </div>
        <div>
          <div style="font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;">Payment Status</div>
          <span class="badge <?php echo $ppClass; ?>"><?php echo htmlspecialchars($row['payment_status']); ?></span>
        </div>
        <?php if ($row['notes']): ?>
        <div style="grid-column:1/-1;">
          <div style="font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;">Notes</div>
          <div style="font-size:14px;color:var(--text);background:var(--surface2);padding:10px 14px;border-radius:var(--radius);border:1px solid var(--border);">
            <?php echo nl2br(htmlspecialchars($row['notes'])); ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php layout_foot(); ?>
