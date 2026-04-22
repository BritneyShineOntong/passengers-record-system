<?php
// ── Auth helpers ───────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: index.php?err=noperm');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'       => $_SESSION['user_id']   ?? 0,
        'username' => $_SESSION['username']  ?? '',
        'role'     => $_SESSION['role']      ?? 'staff',
        'name'     => $_SESSION['full_name'] ?? '',
    ];
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

// ── CSRF helpers ───────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

// ── Audit logger ───────────────────────────────────────────
function auditLog(mysqli $conn, string $action, string $target = '', string $detail = ''): void {
    $u   = currentUser();
    $uid = $u['id'] ?: null;
    $un  = $u['username'];
    $stmt = $conn->prepare(
        "INSERT INTO audit_log (user_id, username, action, target, detail) VALUES (?,?,?,?,?)"
    );
    $stmt->bind_param('issss', $uid, $un, $action, $target, $detail);
    $stmt->execute();
}

// ── Booking-ref generator ──────────────────────────────────
function generateRef(mysqli $conn): string {
    $stmt = $conn->prepare("SELECT id FROM bookings WHERE booking_ref = ? LIMIT 1");
    do {
        $ref = 'BK-' . date('Y') . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $stmt->bind_param('s', $ref);
        $stmt->execute();
        $stmt->store_result();
    } while ($stmt->num_rows > 0);
    return $ref;
}

// ── Toast helper ───────────────────────────────────────────
function setToast(string $msg, string $type = 'success'): void {
    $allowed = ['success', 'error', 'info'];
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['toast'] = [
        'msg'  => $msg,
        'type' => in_array($type, $allowed) ? $type : 'info',
    ];
}
