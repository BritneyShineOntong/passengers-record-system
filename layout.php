<?php
// layout.php — call layout_head($title) and layout_foot()

function layout_head(string $title = 'Passengers Record System'): void {
    $user = currentUser();
    $nav  = [
        ['href' => 'index.php',     'label' => 'Bookings',  'icon' => 'grid'],
        ['href' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => 'bar-chart'],
        ['href' => 'archive.php',   'label' => 'Archive',   'icon' => 'archive'],
    ];
    if ($user['role'] === 'admin') {
        $nav[] = ['href' => 'users.php', 'label' => 'Users',     'icon' => 'users'];
        $nav[] = ['href' => 'audit.php', 'label' => 'Audit Log', 'icon' => 'shield'];
    }
    // Use SCRIPT_FILENAME for reliable current-page detection
    $cur = basename($_SERVER['SCRIPT_FILENAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($title); ?> — PRS</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root{
  --bg:#f4f6fb;--surface:#fff;--surface2:#f8fafc;
  --border:#e5e9f0;--border2:#d0d7e3;
  --blue:#2563eb;--blue-light:#eff4ff;--blue-dark:#1d4ed8;
  --green:#059669;--green-light:#ecfdf5;
  --amber:#d97706;--amber-light:#fffbeb;
  --red:#dc2626;--red-light:#fef2f2;
  --purple:#7c3aed;--purple-light:#f5f3ff;
  --gray:#6b7280;--gray-light:#f3f4f6;
  --text:#111827;--text2:#374151;--text3:#6b7280;
  --radius:10px;--radius-lg:14px;
  --shadow:0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.05);
  --shadow-md:0 4px 12px rgba(0,0,0,.10);
  font-family:'DM Sans',sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;}

/* ── Sidebar ── */
.sidebar{width:220px;background:var(--surface);border-right:1px solid var(--border);
  display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;
  transition:transform .25s ease;}
.sidebar-logo{padding:20px 18px 14px;border-bottom:1px solid var(--border);}
.sidebar-logo h2{font-size:15px;font-weight:600;color:var(--text);line-height:1.3;}
.sidebar-logo span{font-size:12px;color:var(--text3);}
.sidebar-nav{flex:1;padding:12px 10px;overflow-y:auto;}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:var(--radius);
  font-size:14px;font-weight:500;color:var(--text2);text-decoration:none;transition:.15s;}
.nav-item:hover{background:var(--blue-light);color:var(--blue);}
.nav-item.active{background:var(--blue-light);color:var(--blue);}
.nav-item svg{width:17px;height:17px;flex-shrink:0;}
.sidebar-user{padding:14px 16px;border-top:1px solid var(--border);}
.sidebar-user .name{font-size:13px;font-weight:600;color:var(--text);}
.sidebar-user .role-badge{font-size:11px;background:var(--blue-light);color:var(--blue);
  border-radius:20px;padding:1px 8px;font-weight:600;display:inline-block;margin-top:2px;}
.sidebar-user a{font-size:12px;color:var(--red);text-decoration:none;display:block;margin-top:8px;}
.sidebar-user a:hover{text-decoration:underline;}

/* ── Mobile sidebar toggle ── */
.menu-toggle{display:none;position:fixed;top:14px;left:14px;z-index:200;
  background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
  padding:7px 10px;cursor:pointer;box-shadow:var(--shadow);}
.menu-toggle svg{display:block;}

/* ── Main ── */
.main{margin-left:220px;flex:1;display:flex;flex-direction:column;}
.topbar{background:var(--surface);border-bottom:1px solid var(--border);
  padding:14px 28px;display:flex;align-items:center;justify-content:space-between;}
.topbar-title{font-size:17px;font-weight:600;color:var(--text);}
.page-content{padding:28px;flex:1;}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--radius);
  font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;border:none;
  transition:.15s;white-space:nowrap;font-family:inherit;}
.btn:active{transform:scale(.97);}
.btn-primary{background:var(--blue);color:#fff;}
.btn-primary:hover{background:var(--blue-dark);}
.btn-success{background:var(--green);color:#fff;}
.btn-success:hover{filter:brightness(1.1);}
.btn-danger{background:var(--red-light);color:var(--red);border:1px solid #fca5a5;}
.btn-danger:hover{background:#fecaca;}
.btn-gray{background:var(--gray-light);color:var(--text2);border:1px solid var(--border);}
.btn-gray:hover{background:var(--border);}
.btn-sm{padding:5px 11px;font-size:12px;}
.btn-icon{width:32px;height:32px;padding:0;border-radius:8px;justify-content:center;}

/* ── Cards ── */
.card{background:var(--surface);border-radius:var(--radius-lg);border:1px solid var(--border);
  box-shadow:var(--shadow);}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.card-title{font-size:15px;font-weight:600;color:var(--text);}
.card-body{padding:20px;}

/* ── Stat cards ── */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin-bottom:24px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);
  padding:18px 20px;box-shadow:var(--shadow);}
.stat-label{font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;}
.stat-value{font-size:26px;font-weight:600;color:var(--text);margin-top:4px;}
.stat-sub{font-size:12px;color:var(--text3);margin-top:2px;}
.stat-card.blue .stat-value{color:var(--blue);}
.stat-card.green .stat-value{color:var(--green);}
.stat-card.amber .stat-value{color:var(--amber);}
.stat-card.purple .stat-value{color:var(--purple);}

/* ── Forms ── */
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-group.full{grid-column:1/-1;}
label{font-size:13px;font-weight:600;color:var(--text2);}
input,select,textarea{padding:8px 11px;border:1px solid var(--border2);border-radius:var(--radius);
  font-size:14px;font-family:inherit;color:var(--text);background:var(--surface);
  outline:none;transition:.2s;width:100%;}
input:focus,select:focus,textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.12);}
textarea{resize:vertical;min-height:80px;}
.form-hint{font-size:12px;color:var(--text3);}

/* ── Table ── */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead th{background:var(--surface2);padding:10px 14px;font-size:12px;font-weight:700;
  color:var(--text3);text-transform:uppercase;letter-spacing:.5px;text-align:left;
  border-bottom:1px solid var(--border);cursor:pointer;user-select:none;white-space:nowrap;}
thead th:hover{color:var(--blue);}
thead th.sorted-asc::after{content:' ▲';font-size:10px;}
thead th.sorted-desc::after{content:' ▼';font-size:10px;}
tbody td{padding:12px 14px;font-size:14px;color:var(--text2);border-bottom:1px solid var(--border);vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover{background:var(--surface2);}

/* ── Badges ── */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;}
.badge-pending  {background:#fff7ed;color:#c2410c;}
.badge-ongoing  {background:var(--blue-light);color:var(--blue);}
.badge-completed{background:var(--green-light);color:var(--green);}
.badge-cancelled{background:var(--gray-light);color:var(--gray);}
.badge-paid     {background:var(--green-light);color:var(--green);}
.badge-unpaid   {background:var(--red-light);color:var(--red);}
.badge-refunded {background:var(--purple-light);color:var(--purple);}
.badge-admin    {background:var(--blue-light);color:var(--blue);}
.badge-staff    {background:var(--gray-light);color:var(--gray);}

/* ── Search / filter bar ── */
.filter-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.search-wrap{position:relative;flex:1;min-width:200px;}
.search-wrap svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text3);pointer-events:none;}
.search-wrap input{padding-left:34px;}

/* ── Pagination ── */
.pagination{display:flex;align-items:center;gap:4px;padding:14px 20px;border-top:1px solid var(--border);}
.page-btn{min-width:32px;height:32px;padding:0 8px;border-radius:var(--radius);border:1px solid var(--border);
  background:var(--surface);font-size:13px;cursor:pointer;display:inline-flex;align-items:center;
  justify-content:center;color:var(--text2);text-decoration:none;transition:.15s;}
.page-btn:hover{background:var(--blue-light);color:var(--blue);border-color:var(--blue);}
.page-btn.active{background:var(--blue);color:#fff;border-color:var(--blue);}
.page-btn.disabled{opacity:.4;pointer-events:none;}

/* ── Toast ── */
#toast-container{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;}
.toast{background:var(--text);color:#fff;padding:12px 18px;border-radius:var(--radius);
  font-size:14px;font-weight:500;box-shadow:var(--shadow-md);animation:toastIn .25s ease;max-width:320px;}
.toast.success{background:#065f46;}
.toast.error{background:#991b1b;}
.toast.info{background:#1e40af;}
@keyframes toastIn{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}

/* ── Modal ── */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;
  display:none;align-items:center;justify-content:center;}
.modal-backdrop.open{display:flex;}
.modal{background:var(--surface);border-radius:var(--radius-lg);padding:28px;width:100%;max-width:420px;
  box-shadow:var(--shadow-md);animation:modalIn .2s ease;}
@keyframes modalIn{from{opacity:0;transform:scale(.95);}to{opacity:1;transform:scale(1);}}
.modal-title{font-size:17px;font-weight:600;margin-bottom:8px;}
.modal-body{font-size:14px;color:var(--text2);margin-bottom:22px;line-height:1.6;}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;}

/* ── Spinner ── */
.spinner{width:20px;height:20px;border:2.5px solid var(--border);border-top-color:var(--blue);
  border-radius:50%;animation:spin .6s linear infinite;display:inline-block;}
@keyframes spin{to{transform:rotate(360deg);}}

/* ── Loading overlay ── */
#loading{position:fixed;inset:0;background:rgba(255,255,255,.7);z-index:9000;display:none;align-items:center;justify-content:center;}
#loading.show{display:flex;}

/* ── Misc ── */
.ref-code{font-family:'DM Mono',monospace;font-size:12px;background:var(--gray-light);padding:2px 7px;border-radius:5px;color:var(--text2);}
.empty-state{text-align:center;padding:56px 20px;color:var(--text3);}
.empty-state svg{opacity:.3;margin-bottom:12px;}
.name-cell{display:flex;align-items:center;gap:10px;}
.avatar{width:32px;height:32px;border-radius:50%;background:var(--blue-light);color:var(--blue);
  font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.location-pill{display:inline-flex;align-items:center;gap:4px;background:var(--gray-light);border-radius:6px;padding:2px 8px;font-size:13px;}
.fare-val{font-family:'DM Mono',monospace;font-size:13px;font-weight:500;color:var(--green);}
.alert{padding:11px 16px;border-radius:var(--radius);font-size:14px;margin-bottom:16px;}
.alert-danger{background:var(--red-light);color:var(--red);border:1px solid #fca5a5;}
.alert-success{background:var(--green-light);color:var(--green);}
.alert-info{background:var(--blue-light);color:var(--blue);}
.duplicate-warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:var(--radius);
  padding:10px 14px;font-size:13px;margin-top:8px;display:none;}

/* ── Mobile ── */
@media(max-width:768px){
  .menu-toggle{display:flex;}
  .sidebar{transform:translateX(-100%);}
  .sidebar.open{transform:translateX(0);}
  .main{margin-left:0;}
  .topbar{padding-left:60px;}
}
</style>
</head>
<body>

<!-- Mobile nav toggle -->
<button class="menu-toggle" id="menu-toggle" aria-label="Toggle navigation">
  <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
  </svg>
</button>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <h2>Passengers Record System</h2>
    <span>Ride Booking Manager</span>
  </div>
  <nav class="sidebar-nav">
<?php foreach ($nav as $n): $active = ($cur === basename($n['href'])) ? 'active' : ''; ?>
    <a href="<?php echo htmlspecialchars($n['href']); ?>" class="nav-item <?php echo $active; ?>">
      <?php echo nav_icon($n['icon']); ?>
      <?php echo htmlspecialchars($n['label']); ?>
    </a>
<?php endforeach; ?>
  </nav>
  <div class="sidebar-user">
    <div class="name"><?php echo htmlspecialchars($user['name']); ?></div>
    <span class="role-badge"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
    <a href="logout.php">Sign out</a>
  </div>
</aside>

<!-- Loading overlay -->
<div id="loading"><div class="spinner" style="width:36px;height:36px;"></div></div>

<!-- Toast container -->
<div id="toast-container"></div>

<!-- Confirm modal -->
<div class="modal-backdrop" id="confirm-modal">
  <div class="modal">
    <div class="modal-title" id="modal-title">Confirm Action</div>
    <div class="modal-body"  id="modal-body">Are you sure?</div>
    <div class="modal-actions">
      <button class="btn btn-gray" onclick="closeModal()">Cancel</button>
      <button class="btn btn-danger" id="modal-confirm-btn">Confirm</button>
    </div>
  </div>
</div>

<div class="main">
<div class="topbar">
  <span class="topbar-title"><?php echo htmlspecialchars($title); ?></span>
  <div style="display:flex;gap:8px;align-items:center;">
    <?php if (!empty($_SESSION['toast'])): ?>
    <script>document.addEventListener('DOMContentLoaded', () =>
      showToast(<?php echo json_encode($_SESSION['toast']['msg']); ?>, <?php echo json_encode($_SESSION['toast']['type']); ?>)
    );</script>
    <?php unset($_SESSION['toast']); endif; ?>
    <span style="font-size:13px;color:var(--text3);"><?php echo date('D, d M Y'); ?></span>
  </div>
</div>
<div class="page-content">
<?php
}

function layout_foot(): void { ?>
</div><!-- page-content -->
</div><!-- main -->

<script>
// ── Toast ──────────────────────────────────────────────────
function showToast(msg, type = 'info', ms = 3500) {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = 'toast ' + (['success','error','info'].includes(type) ? type : 'info');
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, ms);
}

// ── Modal ──────────────────────────────────────────────────
let _modalCb = null;
function confirmAction(title, body, cb, btnLabel = 'Delete') {
  document.getElementById('modal-title').textContent = title;
  document.getElementById('modal-body').textContent  = body;
  document.getElementById('modal-confirm-btn').textContent = btnLabel;
  _modalCb = cb;
  document.getElementById('confirm-modal').classList.add('open');
}
document.getElementById('modal-confirm-btn').addEventListener('click', () => {
  closeModal();
  if (_modalCb) _modalCb();
});
function closeModal() { document.getElementById('confirm-modal').classList.remove('open'); }
document.getElementById('confirm-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });

// ── Loading ────────────────────────────────────────────────
function showLoading() { document.getElementById('loading').classList.add('show'); }
function hideLoading() { document.getElementById('loading').classList.remove('show'); }

// ── Mobile sidebar ─────────────────────────────────────────
const sidebar    = document.getElementById('sidebar');
const menuToggle = document.getElementById('menu-toggle');
menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
document.addEventListener('click', e => {
  if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) sidebar.classList.remove('open');
});

// ── Column sort ────────────────────────────────────────────
document.querySelectorAll('thead th[data-col]').forEach(th => {
  th.addEventListener('click', () => {
    const url = new URL(location.href);
    const cur = url.searchParams.get('sort');
    const dir = url.searchParams.get('dir');
    const col = th.dataset.col;
    url.searchParams.set('sort', col);
    url.searchParams.set('dir', (cur === col && dir === 'asc') ? 'desc' : 'asc');
    url.searchParams.set('page', 1);
    showLoading();
    location.href = url.toString();
  });
});

// ── Search auto-submit on clear ────────────────────────────
const si = document.getElementById('search-input');
if (si) si.addEventListener('search', () => { if (si.value === '') si.closest('form').submit(); });
</script>
</body></html>
<?php
}

// ── Inline SVG icons ───────────────────────────────────────
function nav_icon(string $name): string {
    $icons = [
        'grid'      => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
        'bar-chart' => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/></svg>',
        'archive'   => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>',
        'users'     => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'shield'    => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    ];
    return $icons[$name] ?? '';
}
