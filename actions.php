<?php
session_start();
require 'db.php';
require 'auth.php';

header('Content-Type: application/json');

// All state-mutating actions require CSRF verification
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action !== 'check_dup') {
    verifyCsrf();
}

switch ($action) {

    case 'archive':
        requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok' => false, 'err' => 'Invalid ID']); break; }

        $stmt = $conn->prepare("SELECT booking_ref FROM bookings WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) { echo json_encode(['ok' => false, 'err' => 'Booking not found']); break; }

        $upd = $conn->prepare("UPDATE bookings SET is_archived = 1 WHERE id = ?");
        $upd->bind_param('i', $id);
        $upd->execute();
        if ($upd->affected_rows === 0) { echo json_encode(['ok' => false, 'err' => 'Update failed']); break; }

        auditLog($conn, 'ARCHIVE', $row['booking_ref'], 'Archived booking');
        echo json_encode(['ok' => true]);
        break;

    case 'restore':
        requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok' => false, 'err' => 'Invalid ID']); break; }

        $stmt = $conn->prepare("SELECT booking_ref FROM bookings WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) { echo json_encode(['ok' => false, 'err' => 'Booking not found']); break; }

        $upd = $conn->prepare("UPDATE bookings SET is_archived = 0 WHERE id = ?");
        $upd->bind_param('i', $id);
        $upd->execute();
        if ($upd->affected_rows === 0) { echo json_encode(['ok' => false, 'err' => 'Update failed']); break; }

        auditLog($conn, 'RESTORE', $row['booking_ref'], 'Restored booking');
        echo json_encode(['ok' => true]);
        break;

    case 'delete':
        requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok' => false, 'err' => 'Invalid ID']); break; }

        $stmt = $conn->prepare("SELECT booking_ref FROM bookings WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) { echo json_encode(['ok' => false, 'err' => 'Booking not found']); break; }

        $del = $conn->prepare("DELETE FROM bookings WHERE id = ?");
        $del->bind_param('i', $id);
        $del->execute();
        if ($del->affected_rows === 0) { echo json_encode(['ok' => false, 'err' => 'Delete failed']); break; }

        auditLog($conn, 'DELETE', $row['booking_ref'], 'Permanently deleted booking');
        echo json_encode(['ok' => true]);
        break;

    case 'delete_all':
        requireAdmin();
        // Require a one-time confirmation nonce passed from the modal
        $nonce = $_POST['nonce'] ?? '';
        if (empty($_SESSION['delete_all_nonce']) || !hash_equals($_SESSION['delete_all_nonce'], $nonce)) {
            echo json_encode(['ok' => false, 'err' => 'Invalid confirmation nonce']);
            break;
        }
        unset($_SESSION['delete_all_nonce']);

        $conn->query("DELETE FROM bookings WHERE is_archived = 0");
        auditLog($conn, 'DELETE_ALL', 'bookings', 'Deleted all active bookings');
        echo json_encode(['ok' => true]);
        break;

    case 'delete_all_nonce':
        a// Issues a single-use nonce for the delete_all confirmation
        requireAdmin();
        $_SESSION['delete_all_nonce'] = bin2hex(random_bytes(16));
        echo json_encode(['nonce' => $_SESSION['delete_all_nonce']]);
        break;

    case 'check_dup':
        $c  = trim($_GET['customer'] ?? '');
        $d  = trim($_GET['driver']   ?? '');
        $dt = trim($_GET['date']     ?? '');
        $excludeId = (int)($_GET['exclude'] ?? 0);
        $stmt = $conn->prepare(
            "SELECT id FROM bookings WHERE customer_name = ? AND driver_name = ? AND ride_date = ? AND is_archived = 0 AND id != ? LIMIT 1"
        );
        $stmt->bind_param('sssi', $c, $d, $dt, $excludeId);
        $stmt->execute();
        echo json_encode(['dup' => $stmt->get_result()->num_rows > 0]);
        break;

    default:
        echo json_encode(['ok' => false, 'err' => 'Unknown action']);
}
