<?php
require 'db.php';

$success = '';
$error   = '';

// ── Handle Add User ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!$name || !$email) {
            $error = 'Both Name and Email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $email);
            if ($stmt->execute()) {
                $uid = $stmt->insert_id;
                // Create empty user_inputs row (DOB pending from user)
                $conn->query("INSERT INTO user_inputs (user_id, dob) VALUES ($uid, NULL)");
                $success = "User '$name' created. Awaiting DOB from user. (ID: $uid)";
            } else {
                $error = "Insert failed: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if ($_POST['action'] === 'delete') {
        $del_id = (int)$_POST['del_id'];
        $conn->query("DELETE FROM users WHERE id = $del_id");
        $success = "User deleted.";
    }
}

// ── Fetch all records from VIEW ──────────────────────────────
$records = $conn->query("SELECT * FROM user_report ORDER BY created_at DESC");
$stats   = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(dob IS NOT NULL) AS complete,
        SUM(dob IS NULL) AS pending
    FROM user_report
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel — UserDB</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@300;400;500;600&family=Barlow+Mono&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #0a0a0f;
  --panel: #111118;
  --card: #16161f;
  --border: #222230;
  --accent: #f0c040;
  --red: #ff4d4d;
  --green: #3dffa0;
  --text: #dde0ee;
  --muted: #555570;
  --font-head: 'Bebas Neue', sans-serif;
  --font-body: 'Barlow', sans-serif;
  --font-mono: 'Barlow Mono', monospace;
}

body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--font-body);
  min-height: 100vh;
  display: grid;
  grid-template-columns: 260px 1fr;
  grid-template-rows: auto 1fr;
}

/* ── Sidebar ── */
.sidebar {
  grid-row: 1 / 3;
  background: var(--panel);
  border-right: 1px solid var(--border);
  padding: 2rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 2rem;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow-y: auto;
}

.logo {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.logo-text {
  font-family: var(--font-head);
  font-size: 2.4rem;
  letter-spacing: 0.08em;
  color: var(--accent);
  line-height: 1;
}
.logo-sub {
  font-family: var(--font-mono);
  font-size: 0.65rem;
  color: var(--muted);
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.role-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(240,192,64,0.1);
  border: 1px solid rgba(240,192,64,0.3);
  border-radius: 6px;
  padding: 6px 12px;
  font-family: var(--font-mono);
  font-size: 0.7rem;
  color: var(--accent);
  letter-spacing: 0.08em;
}
.role-dot { width: 7px; height: 7px; background: var(--accent); border-radius: 50%; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }

.nav-section label {
  display: block;
  font-family: var(--font-mono);
  font-size: 0.6rem;
  color: var(--muted);
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin-bottom: 0.75rem;
}
.nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0.6rem 0.85rem;
  border-radius: 7px;
  text-decoration: none;
  font-size: 0.88rem;
  font-weight: 500;
  color: var(--muted);
  transition: all 0.2s;
  margin-bottom: 3px;
}
.nav-link:hover { background: var(--card); color: var(--text); }
.nav-link.active { background: rgba(240,192,64,0.12); color: var(--accent); }
.nav-icon { font-size: 1rem; width: 20px; text-align: center; }

.sidebar-stats {
  margin-top: auto;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 1.1rem;
}
.sidebar-stats .ss-label { font-family: var(--font-mono); font-size: 0.6rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.75rem; }
.ss-row { display: flex; justify-content: space-between; align-items: center; padding: 5px 0; border-bottom: 1px solid var(--border); font-family: var(--font-mono); font-size: 0.75rem; }
.ss-row:last-child { border-bottom: none; }
.ss-val { font-weight: 600; }
.ss-val.yellow { color: var(--accent); }
.ss-val.green  { color: var(--green); }
.ss-val.red    { color: var(--red); }

/* ── Main content ── */
.topbar {
  background: var(--panel);
  border-bottom: 1px solid var(--border);
  padding: 1rem 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.page-title { font-family: var(--font-head); font-size: 1.6rem; letter-spacing: 0.06em; }
.topbar-actions { display: flex; gap: 0.75rem; }
.btn-outline {
  padding: 0.45rem 1rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--muted);
  font-family: var(--font-body);
  font-size: 0.8rem;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s;
}
.btn-outline:hover { border-color: var(--text); color: var(--text); }
.btn-outline.accent { border-color: var(--accent); color: var(--accent); }
.btn-outline.accent:hover { background: var(--accent); color: #000; }

.main { padding: 2rem; overflow-y: auto; }

/* Alerts */
.alert { padding: 0.85rem 1.1rem; border-radius: 8px; font-family: var(--font-mono); font-size: 0.8rem; margin-bottom: 1.5rem; }
.alert-ok  { background: rgba(61,255,160,0.07); border: 1px solid rgba(61,255,160,0.25); color: var(--green); }
.alert-err { background: rgba(255,77,77,0.07);  border: 1px solid rgba(255,77,77,0.25);  color: var(--red); }

/* Stats row */
.stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-bottom: 2rem; }
.stat-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1.25rem 1.5rem;
  position: relative;
  overflow: hidden;
}
.stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
}
.stat-card.s-total::before  { background: var(--accent); }
.stat-card.s-done::before   { background: var(--green); }
.stat-card.s-pend::before   { background: var(--red); }
.stat-label { font-family: var(--font-mono); font-size: 0.62rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; }
.stat-num { font-family: var(--font-head); font-size: 3rem; letter-spacing: 0.02em; line-height: 1; margin-top: 6px; }
.s-total .stat-num { color: var(--accent); }
.s-done  .stat-num { color: var(--green); }
.s-pend  .stat-num { color: var(--red); }

/* Add form card */
.form-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 2rem;
}
.form-card-title {
  font-family: var(--font-head);
  font-size: 1.1rem;
  letter-spacing: 0.06em;
  color: var(--accent);
  margin-bottom: 1.25rem;
  display: flex;
  align-items: center;
  gap: 10px;
}
.form-grid { display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end; }
.field label { display: block; font-family: var(--font-mono); font-size: 0.62rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px; }
.field input {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 7px;
  padding: 0.65rem 0.9rem;
  color: var(--text);
  font-family: var(--font-mono);
  font-size: 0.85rem;
  outline: none;
  transition: border-color 0.2s;
}
.field input:focus { border-color: var(--accent); }
.note { font-family: var(--font-mono); font-size: 0.65rem; color: var(--muted); margin-top: 0.75rem; }
.note span { color: var(--accent); }

.btn-add {
  background: var(--accent);
  color: #000;
  border: none;
  border-radius: 7px;
  padding: 0.65rem 1.5rem;
  font-family: var(--font-head);
  font-size: 1rem;
  letter-spacing: 0.06em;
  cursor: pointer;
  white-space: nowrap;
  transition: opacity 0.2s, transform 0.15s;
  height: fit-content;
}
.btn-add:hover { opacity: 0.85; transform: translateY(-1px); }

/* Table */
.table-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.table-header { padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.table-title { font-family: var(--font-head); font-size: 1.1rem; letter-spacing: 0.06em; }
.record-count { font-family: var(--font-mono); font-size: 0.7rem; color: var(--muted); }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-family: var(--font-mono); font-size: 0.8rem; }
thead th {
  background: rgba(255,255,255,0.02);
  padding: 0.75rem 1rem;
  text-align: left;
  font-size: 0.6rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--muted);
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
thead th.fe { color: var(--green); }
thead th.fe::before { content: '⌨ '; }
tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,0.02); }
tbody td { padding: 0.85rem 1rem; vertical-align: middle; }
.id-cell { color: var(--muted); font-size: 0.72rem; }
.dob-filled { color: var(--green); }
.dob-empty  { color: var(--muted); font-style: italic; }
.age-val { color: var(--green); }
.badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 0.62rem;
  letter-spacing: 0.06em;
  font-weight: 600;
}
.badge-complete { background: rgba(61,255,160,0.1); color: var(--green); border: 1px solid rgba(61,255,160,0.2); }
.badge-pending  { background: rgba(255,77,77,0.08);  color: var(--red);   border: 1px solid rgba(255,77,77,0.2); }
.btn-del {
  background: none;
  border: 1px solid transparent;
  border-radius: 5px;
  color: var(--muted);
  cursor: pointer;
  font-family: var(--font-mono);
  font-size: 0.72rem;
  padding: 4px 8px;
  transition: all 0.15s;
}
.btn-del:hover { border-color: var(--red); color: var(--red); }
.empty-row td { text-align: center; padding: 3rem; color: var(--muted); font-size: 0.8rem; }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="logo">
    <div class="logo-text">UserDB</div>
    <div class="logo-sub">Management System</div>
  </div>

  <div class="role-badge">
    <div class="role-dot"></div>
    ADMIN ACCESS
  </div>

  <div class="nav-section">
    <label>Navigation</label>
    <a href="admin.php" class="nav-link active"><span class="nav-icon">⊞</span> Dashboard</a>
    <a href="report.php" class="nav-link"><span class="nav-icon">◈</span> Reports</a>
    <a href="user.php" class="nav-link" target="_blank"><span class="nav-icon">↗</span> User Portal</a>
  </div>

  <div class="sidebar-stats">
    <div class="ss-label">Quick Stats</div>
    <div class="ss-row"><span>Total Users</span><span class="ss-val yellow"><?= $stats['total'] ?? 0 ?></span></div>
    <div class="ss-row"><span>DOB Complete</span><span class="ss-val green"><?= $stats['complete'] ?? 0 ?></span></div>
    <div class="ss-row"><span>DOB Pending</span><span class="ss-val red"><?= $stats['pending'] ?? 0 ?></span></div>
  </div>
</aside>

<!-- TOP BAR -->
<div class="topbar">
  <div class="page-title">Admin Dashboard</div>
  <div class="topbar-actions">
    <a href="report.php" class="btn-outline">View Reports</a>
    <a href="user.php" class="btn-outline accent" target="_blank">↗ Open User Portal</a>
  </div>
</div>

<!-- MAIN -->
<main class="main">

  <?php if ($success): ?>
    <div class="alert alert-ok">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-err">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card s-total">
      <div class="stat-label">Total Users</div>
      <div class="stat-num"><?= $stats['total'] ?? 0 ?></div>
    </div>
    <div class="stat-card s-done">
      <div class="stat-label">DOB Complete</div>
      <div class="stat-num"><?= $stats['complete'] ?? 0 ?></div>
    </div>
    <div class="stat-card s-pend">
      <div class="stat-label">DOB Pending</div>
      <div class="stat-num"><?= $stats['pending'] ?? 0 ?></div>
    </div>
  </div>

  <!-- Add User Form — Admin fills Name + Email only -->
  <div class="form-card">
    <div class="form-card-title">⊕ Add New User</div>
    <form method="POST" action="admin.php">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="field">
          <label>Full Name *</label>
          <input type="text" name="name" placeholder="e.g. Jane Doe" required />
        </div>
        <div class="field">
          <label>Email Address *</label>
          <input type="email" name="email" placeholder="jane@example.com" required />
        </div>
        <button type="submit" class="btn-add">ADD USER</button>
      </div>
      <div class="note">
        Admin provides <span>Name</span> and <span>Email</span> only.
        DOB is left <span>NULL</span> — user fills it via the User Portal.
      </div>
    </form>
  </div>

  <!-- Records Table -->
  <div class="table-card">
    <div class="table-header">
      <div class="table-title">All Users</div>
      <div class="record-count"><?= $records ? $records->num_rows : 0 ?> record(s) — queried from user_report VIEW</div>
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
            <th>Created</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($records && $records->num_rows > 0): ?>
            <?php while ($row = $records->fetch_assoc()): ?>
            <tr>
              <td class="id-cell">#<?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['email']) ?></td>
              <td class="<?= $row['dob'] ? 'dob-filled' : 'dob-empty' ?>">
                <?= $row['dob'] ? date('d M Y', strtotime($row['dob'])) : 'awaiting user input' ?>
              </td>
              <td class="<?= $row['age'] !== null ? 'age-val' : 'dob-empty' ?>">
                <?= $row['age'] !== null ? $row['age'] . ' yrs' : '—' ?>
              </td>
              <td>
                <span class="badge <?= $row['dob'] ? 'badge-complete' : 'badge-pending' ?>">
                  <?= $row['dob'] ? 'Complete' : 'Pending' ?>
                </span>
              </td>
              <td class="dob-empty"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
              <td>
                <form method="POST" action="admin.php" onsubmit="return confirm('Delete this user?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="del_id" value="<?= $row['id'] ?>">
                  <button type="submit" class="btn-del">✕ delete</button>
                </form>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr class="empty-row"><td colspan="8">No users yet. Add one above.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
</body>
</html>