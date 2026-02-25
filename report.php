<?php
require 'db.php';

$search = trim($_GET['q']      ?? '');
$filter = trim($_GET['filter'] ?? 'all');

$where  = [];
$params = [];
$types  = '';

if ($search) {
    $where[]  = "(name LIKE ? OR email LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($filter === 'complete') $where[] = "dob IS NOT NULL";
if ($filter === 'pending')  $where[] = "dob IS NULL";

$sql = "SELECT * FROM user_report";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY created_at DESC";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$stats = $conn->query("
    SELECT COUNT(*) AS total,
           SUM(dob IS NOT NULL) AS complete,
           SUM(dob IS NULL) AS pending
    FROM user_report
")->fetch_assoc();

$pct = $stats['total'] > 0 ? round(($stats['complete'] / $stats['total']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports — UserDB</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@300;400;500;600&family=Barlow+Mono&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #0a0a0f; --panel: #111118; --card: #16161f; --border: #222230;
  --accent: #f0c040; --red: #ff4d4d; --green: #3dffa0;
  --text: #dde0ee; --muted: #555570;
  --font-head: 'Bebas Neue', sans-serif;
  --font-body: 'Barlow', sans-serif;
  --font-mono: 'Barlow Mono', monospace;
}
body { background: var(--bg); color: var(--text); font-family: var(--font-body); min-height: 100vh;
       display: grid; grid-template-columns: 260px 1fr; grid-template-rows: auto 1fr; }

/* sidebar (same as admin) */
.sidebar { grid-row: 1/3; background: var(--panel); border-right: 1px solid var(--border); padding: 2rem 1.5rem;
           display: flex; flex-direction: column; gap: 2rem; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
.logo-text { font-family: var(--font-head); font-size: 2.4rem; letter-spacing: 0.08em; color: var(--accent); line-height: 1; }
.logo-sub  { font-family: var(--font-mono); font-size: 0.65rem; color: var(--muted); letter-spacing: 0.1em; text-transform: uppercase; }
.role-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(240,192,64,0.1); border: 1px solid rgba(240,192,64,0.3); border-radius: 6px; padding: 6px 12px; font-family: var(--font-mono); font-size: 0.7rem; color: var(--accent); }
.role-dot { width: 7px; height: 7px; background: var(--accent); border-radius: 50%; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }
.nav-section label { display: block; font-family: var(--font-mono); font-size: 0.6rem; color: var(--muted); letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 0.75rem; }
.nav-link { display: flex; align-items: center; gap: 10px; padding: 0.6rem 0.85rem; border-radius: 7px; text-decoration: none; font-size: 0.88rem; font-weight: 500; color: var(--muted); transition: all 0.2s; margin-bottom: 3px; }
.nav-link:hover { background: var(--card); color: var(--text); }
.nav-link.active { background: rgba(240,192,64,0.12); color: var(--accent); }
.nav-icon { font-size: 1rem; width: 20px; text-align: center; }
.sidebar-footer { margin-top: auto; font-family: var(--font-mono); font-size: 0.62rem; color: var(--muted); }

/* topbar */
.topbar { background: var(--panel); border-bottom: 1px solid var(--border); padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; }
.page-title { font-family: var(--font-head); font-size: 1.6rem; letter-spacing: 0.06em; }
.btn-outline { padding: 0.45rem 1rem; border-radius: 6px; border: 1px solid var(--border); background: transparent; color: var(--muted); font-family: var(--font-body); font-size: 0.8rem; cursor: pointer; text-decoration: none; transition: all 0.2s; }
.btn-outline:hover { border-color: var(--text); color: var(--text); }

.main { padding: 2rem; overflow-y: auto; }

/* Progress bar */
.progress-section { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; }
.progress-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1rem; }
.progress-label { font-family: var(--font-head); font-size: 1rem; letter-spacing: 0.06em; color: var(--text); }
.progress-pct { font-family: var(--font-head); font-size: 2rem; color: var(--green); letter-spacing: 0.04em; }
.progress-bar { height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; }
.progress-fill { height: 100%; background: linear-gradient(90deg, var(--green), var(--accent)); border-radius: 3px; transition: width 0.6s ease; }
.progress-sub { font-family: var(--font-mono); font-size: 0.68rem; color: var(--muted); margin-top: 0.6rem; }

/* Stats */
.stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-bottom: 2rem; }
.stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem 1.5rem; }
.stat-label { font-family: var(--font-mono); font-size: 0.62rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; }
.stat-num { font-family: var(--font-head); font-size: 2.5rem; line-height: 1; margin-top: 6px; }
.col-yellow { color: var(--accent); }
.col-green  { color: var(--green); }
.col-red    { color: var(--red); }

/* SQL display */
.sql-block { background: var(--panel); border: 1px solid var(--border); border-left: 3px solid var(--accent); border-radius: 8px; padding: 0.85rem 1.1rem; font-family: var(--font-mono); font-size: 0.72rem; color: var(--muted); margin-bottom: 1.5rem; white-space: nowrap; overflow-x: auto; line-height: 1.8; }
.kw   { color: #7dd3fc; }
.view { color: var(--accent); }
.val  { color: #fca5a5; }

/* Filter */
.filter-row { display: flex; gap: 0.75rem; margin-bottom: 1.25rem; flex-wrap: wrap; align-items: center; }
.filter-row form { display: flex; gap: 0.5rem; flex: 1; flex-wrap: wrap; }
.filter-row input { flex: 1; min-width: 180px; background: var(--card); border: 1px solid var(--border); border-radius: 7px; padding: 0.55rem 0.9rem; color: var(--text); font-family: var(--font-mono); font-size: 0.8rem; outline: none; transition: border-color 0.2s; }
.filter-row input:focus { border-color: var(--accent); }
.filter-row input::placeholder { color: var(--muted); }
.filter-row select { background: var(--card); border: 1px solid var(--border); border-radius: 7px; padding: 0.55rem 0.9rem; color: var(--text); font-family: var(--font-mono); font-size: 0.8rem; outline: none; cursor: pointer; }
.btn-search { background: var(--accent); color: #000; border: none; border-radius: 7px; padding: 0.55rem 1.1rem; font-family: var(--font-head); font-size: 0.95rem; letter-spacing: 0.05em; cursor: pointer; }
.btn-clear  { background: var(--card); color: var(--muted); border: 1px solid var(--border); border-radius: 7px; padding: 0.55rem 0.9rem; font-family: var(--font-mono); font-size: 0.75rem; cursor: pointer; text-decoration: none; }
.found-count { font-family: var(--font-mono); font-size: 0.7rem; color: var(--muted); white-space: nowrap; }

/* Table */
.table-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.table-header { padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.table-title { font-family: var(--font-head); font-size: 1.1rem; letter-spacing: 0.06em; }
.table-hint { font-family: var(--font-mono); font-size: 0.62rem; color: var(--muted); }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-family: var(--font-mono); font-size: 0.79rem; }
thead th { background: rgba(255,255,255,0.02); padding: 0.75rem 1rem; text-align: left; font-size: 0.6rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); white-space: nowrap; }
thead th.fe { color: var(--green); }
thead th.fe::before { content: '⌨ '; }
tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,0.02); }
tbody td { padding: 0.85rem 1rem; vertical-align: middle; }
.id-c  { color: var(--muted); font-size: 0.72rem; }
.dob-f { color: var(--green); font-weight: 500; }
.dob-e { color: var(--muted); font-style: italic; }
.age-v { color: var(--green); }
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.62rem; letter-spacing: 0.06em; font-weight: 600; }
.b-complete { background: rgba(61,255,160,0.1); color: var(--green); border: 1px solid rgba(61,255,160,0.2); }
.b-pending  { background: rgba(255,77,77,0.08);  color: var(--red);   border: 1px solid rgba(255,77,77,0.2); }
.empty-row td { text-align: center; padding: 3rem; color: var(--muted); }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="logo">
    <div class="logo-text">UserDB</div>
    <div class="logo-sub">Management System</div>
  </div>
  <div class="role-badge"><div class="role-dot"></div> ADMIN ACCESS</div>
  <div class="nav-section">
    <label>Navigation</label>
    <a href="admin.php" class="nav-link"><span class="nav-icon">⊞</span> Dashboard</a>
    <a href="report.php" class="nav-link active"><span class="nav-icon">◈</span> Reports</a>
    <a href="user.php" class="nav-link" target="_blank"><span class="nav-icon">↗</span> User Portal</a>
  </div>
  <div class="sidebar-footer">Querying: user_report VIEW<br>Tables: users ⟕ user_inputs</div>
</aside>

<div class="topbar">
  <div class="page-title">Reports</div>
  <a href="admin.php" class="btn-outline">← Dashboard</a>
</div>

<main class="main">

  <!-- DOB Completion Progress -->
  <div class="progress-section">
    <div class="progress-header">
      <div class="progress-label">DOB Completion Rate</div>
      <div class="progress-pct"><?= $pct ?>%</div>
    </div>
    <div class="progress-bar">
      <div class="progress-fill" style="width: <?= $pct ?>%"></div>
    </div>
    <div class="progress-sub"><?= $stats['complete'] ?> of <?= $stats['total'] ?> users have submitted their date of birth</div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-label">Total Records</div>
      <div class="stat-num col-yellow"><?= $stats['total'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">DOB Complete</div>
      <div class="stat-num col-green"><?= $stats['complete'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">DOB Pending</div>
      <div class="stat-num col-red"><?= $stats['pending'] ?></div>
    </div>
  </div>

  <!-- SQL being run -->
  <div class="sql-block">
    <span class="kw">SELECT</span> * <span class="kw">FROM</span> <span class="view">user_report</span>
    <?php if ($search || $filter !== 'all'): ?>
      <span class="kw">WHERE</span>
      <?php if ($search): ?>(name <span class="kw">LIKE</span> <span class="val">'%<?= htmlspecialchars($search) ?>%'</span> <span class="kw">OR</span> email <span class="kw">LIKE</span> <span class="val">'%<?= htmlspecialchars($search) ?>%'</span>)<?php endif; ?>
      <?php if ($search && $filter !== 'all'): ?><span class="kw"> AND </span><?php endif; ?>
      <?php if ($filter === 'complete'): ?>dob <span class="kw">IS NOT NULL</span><?php endif; ?>
      <?php if ($filter === 'pending'):  ?>dob <span class="kw">IS NULL</span><?php endif; ?>
    <?php endif; ?>
    <span class="kw">ORDER BY</span> created_at <span class="kw">DESC</span>;
  </div>

  <!-- Filters -->
  <div class="filter-row">
    <form method="GET" action="report.php">
      <input type="text" name="q" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>" />
      <select name="filter">
        <option value="all"      <?= $filter==='all'      ?'selected':'' ?>>All records</option>
        <option value="complete" <?= $filter==='complete' ?'selected':'' ?>>DOB Complete</option>
        <option value="pending"  <?= $filter==='pending'  ?'selected':'' ?>>DOB Pending</option>
      </select>
      <button type="submit" class="btn-search">SEARCH</button>
      <?php if ($search || $filter !== 'all'): ?>
        <a href="report.php" class="btn-clear">✕ Clear</a>
      <?php endif; ?>
    </form>
    <span class="found-count"><?= $result ? $result->num_rows : 0 ?> result(s)</span>
  </div>

  <!-- Full Report Table -->
  <div class="table-card">
    <div class="table-header">
      <div class="table-title">Full Report</div>
      <div class="table-hint">users ⟕ user_inputs via user_report VIEW</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th class="fe">Date of Birth</th>
            <th class="fe">Age</th>
            <th>Status</th>
            <th>Registered</th>
            <th>DOB Updated</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td class="id-c">#<?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['email']) ?></td>
              <td class="<?= $row['dob'] ? 'dob-f' : 'dob-e' ?>">
                <?= $row['dob'] ? date('d M Y', strtotime($row['dob'])) : 'NULL — pending' ?>
              </td>
              <td class="<?= $row['age'] !== null ? 'age-v' : 'dob-e' ?>">
                <?= $row['age'] !== null ? $row['age'] . ' yrs' : '—' ?>
              </td>
              <td>
                <span class="badge <?= $row['dob'] ? 'b-complete' : 'b-pending' ?>">
                  <?= $row['dob'] ? 'Complete' : 'Pending' ?>
                </span>
              </td>
              <td class="dob-e"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
              <td class="dob-e">
                <?= $row['dob_updated_at'] ? date('d M Y, H:i', strtotime($row['dob_updated_at'])) : '—' ?>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr class="empty-row"><td colspan="8">No records found<?= ($search || $filter !== 'all') ? ' for your filters' : '' ?>.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
</body>
</html>