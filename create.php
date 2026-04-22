<?php
session_start();
require 'db.php';
require 'auth.php';
require 'layout.php';

$errors  = [];
$hasDup  = false;
$dupRef  = '';
$data    = [
    'customer_name'    => '',
    'driver_name'      => '',
    'pickup_location'  => '',
    'drop_off_location'=> '',
    'ride_date'        => date('Y-m-d'),
    'fare'             => '',
    'ride_status'      => 'Pending',
    'payment_status'   => 'Unpaid',
    'notes'            => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $data = array_map('trim', array_intersect_key($_POST, $data));

    // Validation
    if (!$data['customer_name'])     $errors[] = 'Customer name is required.';
    if (!$data['driver_name'])       $errors[] = 'Driver name is required.';
    if (!$data['pickup_location'])   $errors[] = 'Pickup location is required.';
    if (!$data['drop_off_location']) $errors[] = 'Drop-off location is required.';
    if (!$data['ride_date'])         $errors[] = 'Ride date is required.';
    if (!is_numeric($data['fare']) || $data['fare'] < 0) $errors[] = 'Fare must be a valid non-negative number.';

    // Whitelist enum values
    $validRideStatus    = ['Pending', 'Ongoing', 'Completed', 'Cancelled'];
    $validPaymentStatus = ['Unpaid', 'Paid', 'Refunded'];
    if (!in_array($data['ride_status'], $validRideStatus))       $data['ride_status']    = 'Pending';
    if (!in_array($data['payment_status'], $validPaymentStatus)) $data['payment_status'] = 'Unpaid';

    // Duplicate detection — only run when base validation passes
    $dupRow = null;
    if (empty($errors)) {
        $dup = $conn->prepare(
            "SELECT id, booking_ref FROM bookings WHERE customer_name = ? AND driver_name = ? AND ride_date = ? AND is_archived = 0 LIMIT 1"
        );
        $dup->bind_param('sss', $data['customer_name'], $data['driver_name'], $data['ride_date']);
        $dup->execute();
        $dupRow = $dup->get_result()->fetch_assoc();
    }

    $force = !empty($_POST['force_save']);

    if ($dupRow && !$force) {
        $hasDup = true;
        $dupRef = $dupRow['booking_ref'];
        // Don't add to $errors — handled separately in the template
    } elseif (empty($errors)) {
        $ref  = generateRef($conn);
        $uid  = currentUser()['id'];
        $stmt = $conn->prepare("INSERT INTO bookings
            (booking_ref, customer_name, driver_name, pickup_location, drop_off_location,
             ride_date, fare, ride_status, payment_status, notes, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssssdsssi',
            $ref, $data['customer_name'], $data['driver_name'],
            $data['pickup_location'], $data['drop_off_location'],
            $data['ride_date'], $data['fare'],
            $data['ride_status'], $data['payment_status'],
            $data['notes'], $uid);
        $stmt->execute();

        $action = $force ? 'FORCE_CREATE' : 'CREATE';
        auditLog($conn, $action, $ref, "Created booking for {$data['customer_name']}" . ($force ? ' (forced duplicate)' : ''));
        setToast("Booking $ref created successfully!", 'success');
        header('Location: index.php');
        exit;
    }
}

layout_head('Add Booking');
?>

<div style="max-width:760px;">
  <div style="margin-bottom:18px;">
    <a href="index.php" class="btn btn-gray btn-sm">← Back</a>
  </div>

  <?php if ($errors): ?>
  <div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
  <?php endif; ?>

  <?php if ($hasDup): ?>
  <div class="alert alert-danger">
    ⚠️ A similar booking already exists (<strong><?php echo htmlspecialchars($dupRef); ?></strong>) with the same customer, driver, and date.
    Check below to save anyway.
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header"><span class="card-title">New Booking</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
        <div class="form-grid">
          <div class="form-group">
            <label>Customer Name *</label>
            <input type="text" name="customer_name" value="<?php echo htmlspecialchars($data['customer_name']); ?>" id="cust-name" autocomplete="off">
          </div>
          <div class="form-group">
            <label>Driver Name *</label>
            <input type="text" name="driver_name" value="<?php echo htmlspecialchars($data['driver_name']); ?>" id="drv-name" autocomplete="off">
          </div>
          <div class="form-group">
            <label>Pickup Location *</label>
            <input type="text" name="pickup_location" value="<?php echo htmlspecialchars($data['pickup_location']); ?>">
          </div>
          <div class="form-group">
            <label>Drop-off Location *</label>
            <input type="text" name="drop_off_location" value="<?php echo htmlspecialchars($data['drop_off_location']); ?>">
          </div>
          <div class="form-group">
            <label>Ride Date *</label>
            <input type="date" name="ride_date" value="<?php echo htmlspecialchars($data['ride_date']); ?>" id="ride-date">
          </div>
          <div class="form-group">
            <label>Fare (₱) *</label>
            <input type="number" name="fare" step="0.01" min="0" value="<?php echo htmlspecialchars($data['fare']); ?>">
          </div>
          <div class="form-group">
            <label>Ride Status</label>
            <select name="ride_status">
              <?php foreach (['Pending','Ongoing','Completed','Cancelled'] as $s): ?>
              <option value="<?php echo $s; ?>" <?php echo $data['ride_status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Payment Status</label>
            <select name="payment_status">
              <?php foreach (['Unpaid','Paid','Refunded'] as $s): ?>
              <option value="<?php echo $s; ?>" <?php echo $data['payment_status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group full">
            <label>Notes / Remarks</label>
            <textarea name="notes" placeholder="Any special instructions or comments…"><?php echo htmlspecialchars($data['notes']); ?></textarea>
          </div>
        </div>

        <div id="dup-warn" class="duplicate-warn" <?php echo $hasDup ? 'style="display:block"' : ''; ?>>
          ⚠️ Duplicate detected. Check this box to save anyway:
          <label style="display:inline-flex;align-items:center;gap:6px;margin-left:8px;cursor:pointer;">
            <input type="checkbox" name="force_save" value="1"> Save duplicate
          </label>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px;">
          <button type="submit" class="btn btn-primary">Create Booking</button>
          <a href="index.php" class="btn btn-gray">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const custEl = document.getElementById('cust-name');
const drvEl  = document.getElementById('drv-name');
const dateEl = document.getElementById('ride-date');
const warn   = document.getElementById('dup-warn');
let debounce;
function checkDup() {
  clearTimeout(debounce);
  debounce = setTimeout(() => {
    const c = custEl.value.trim(), d = drvEl.value.trim(), dt = dateEl.value;
    if (!c || !d || !dt) { warn.style.display = 'none'; return; }
    fetch('actions.php?action=check_dup&customer=' + encodeURIComponent(c) +
          '&driver=' + encodeURIComponent(d) + '&date=' + encodeURIComponent(dt))
      .then(r => r.json())
      .then(data => { warn.style.display = data.dup ? 'block' : 'none'; })
      .catch(() => {});
  }, 400);
}
[custEl, drvEl, dateEl].forEach(el => el.addEventListener('input', checkDup));
</script>

<?php layout_foot(); ?>
