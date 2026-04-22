<?php
session_start();
require 'db.php';
require 'auth.php';
require 'layout.php';
requireAdmin();

$PER_PAGE = 30;

$totalStmt = $conn->prepare("SELECT COUNT(*) FROM audit_log");
$totalStmt->execute();
$total = $totalStmt->get_result()->fetch_row()[0];

$totalPages = max(1, (int)ceil($total / $PER_PAGE));
$page       = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset     = ($page - 1) * $PER_PAGE;

$logStmt = $conn->prepare("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT ? OFFSET ?");
$logStmt->bind_param('ii', $PER_PAGE, $offset);
$logStmt->execute();
$logs = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);

layout_head('Audit Log');
?>
<div class="card">
  <div class="card-header">
    <span class="card-title">Audit Log <span style="font-size:13px;color:var(--text3);font-weight:400;">(<?php echo $total; ?> entries)</span></span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Time</th><th>User</th><th>Action</th><th>Target</th><th>Detail</th></tr></thead>
      <tbody>
        <?php if ($logs): foreach ($logs as $l):
          $actionColors = [
            'CREATE'      => 'badge-completed',
            'FORCE_CREATE'=> 'badge-ongoing',
            'UPDATE'      => 'badge-ongoing',
            'DELETE'      => 'badge-unpaid',
            'ARCHIVE'     => 'badge-pending',
            'RESTORE'     => 'badge-completed',
            'DELETE_ALL'  => 'badge-unpaid',
            'CREATE_USER' => 'badge-completed',
            'DELETE_USER' => 'badge-unpaid',
          ];
          $cls = $actionColors[$l['action']] ?? 'badge-staff';
        ?>
        <tr>
          <td style="color:var(--text3);font-size:12px;"><?php echo $l['id']; ?></td>
          <td style="font-size:12px;color:var(--text3);white-space:nowrap;"><?php echo htmlspecialchars($l['created_at']); ?></td>
          <td style="font-size:13px;"><?php echo htmlspecialchars($l['username'] ?? '—'); ?></td>
          <td><span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($l['action']); ?></span></td>
          <td><span class="ref-code"><?php echo htmlspecialchars($l['target'] ?? ''); ?></span></td>
          <td style="font-size:13px;color:var(--text2);"><?php echo htmlspecialchars($l['detail'] ?? ''); ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6"><div class="empty-state"><p>No audit entries yet.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a href="?page=<?php echo $p; ?>" class="page-btn <?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php layout_foot(); ?>
