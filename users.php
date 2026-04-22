<?php
session_start();
require 'db.php';
require 'auth.php';
require 'layout.php';
requireAdmin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['act'] ?? '';

    if ($act === 'add') {
        $username  = trim($_POST['username']  ?? '');
        $password  = $_POST['password']       ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin', 'staff']) ? $_POST['role'] : 'staff';

        if (!$username || !$password || !$full_name) {
            $errors[] = 'All fields are required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?,?,?,?)");
            $stmt->bind_param('ssss', $username, $hash, $role, $full_name);
            if ($stmt->execute()) {
                auditLog($conn, 'CREATE_USER', $username, "Created user $username ($role)");
                setToast("User $username created.", 'success');
                header('Location: users.php');
                exit;
            } else {
                $errors[] = 'Username already exists.';
            }
        }
    }

    if ($act === 'delete') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid === currentUser()['id']) {
            $errors[] = 'Cannot delete your own account.';
        } elseif ($uid > 0) {
            $sel = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
            $sel->bind_param('i', $uid);
            $sel->execute();
            $uRow = $sel->get_result()->fetch_assoc();

            if (!$uRow) {
                $errors[] = 'User not found.';
            } else {
                $del = $conn->prepare("DELETE FROM users WHERE id = ?");
                $del->bind_param('i', $uid);
                $del->execute();
                if ($del->affected_rows > 0) {
                    auditLog($conn, 'DELETE_USER', $uRow['username'], 'Deleted user');
                    setToast('User deleted.', 'success');
                    header('Location: users.php');
                    exit;
                } else {
                    $errors[] = 'Delete failed.';
                }
            }
        }
    }
}

$usersStmt = $conn->prepare("SELECT id, full_name, username, role, created_at FROM users ORDER BY id");
$usersStmt->execute();
$users = $usersStmt->get_result()->fetch_all(MYSQLI_ASSOC);

layout_head('User Management');
?>
<div style="max-width:900px;">
  <?php if ($errors): ?><div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div><?php endif; ?>

  <!-- Add user -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header"><span class="card-title">Add New User</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
        <input type="hidden" name="act" value="add">
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" required>
          </div>
          <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required>
          </div>
          <div class="form-group">
            <label>Password <span style="font-size:11px;color:var(--text3);">(min. 8 chars)</span></label>
            <input type="password" name="password" required minlength="8">
          </div>
          <div class="form-group">
            <label>Role</label>
            <select name="role">
              <option value="staff">Staff</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:14px;">Add User</button>
      </form>
    </div>
  </div>

  <!-- Users table -->
  <div class="card">
    <div class="card-header"><span class="card-title">All Users</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Full Name</th><th>Username</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td style="color:var(--text3);font-size:12px;"><?php echo $u['id']; ?></td>
            <td>
              <div class="name-cell">
                <div class="avatar"><?php echo strtoupper(substr($u['full_name'], 0, 1)); ?></div>
                <?php echo htmlspecialchars($u['full_name']); ?>
              </div>
            </td>
            <td style="font-family:'DM Mono',monospace;font-size:13px;"><?php echo htmlspecialchars($u['username']); ?></td>
            <td><span class="badge badge-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
            <td style="font-size:13px;color:var(--text3);"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
            <td>
              <?php if ($u['id'] !== currentUser()['id']): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="uid" value="<?php echo $u['id']; ?>">
                <button type="button" class="btn btn-danger btn-sm"
                  onclick="confirmAction('Delete User', <?php echo json_encode('Delete user ' . $u['username'] . '?'); ?>, () => this.closest('form').submit(), 'Delete')">
                  Delete
                </button>
              </form>
              <?php else: ?>
              <span style="font-size:12px;color:var(--text3);">You</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php layout_foot(); ?>
