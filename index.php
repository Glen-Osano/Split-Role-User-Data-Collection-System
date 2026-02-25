<?php
require 'db.php';

$success = '';
$error   = '';

// ─── Handle Form Submission ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $dob   = trim($_POST['dob']   ?? '');

    if (!$name || !$email) {
        $error = 'Name and Email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // ── Insert name + email into users table ──
        $stmt = $conn->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
        if (!$stmt) {
            $error = "Prepare error: " . $conn->error;
        } else {
            $stmt->bind_param("ss", $name, $email);
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                $stmt->close();

                // ── Insert DOB into user_inputs (NULL if left blank) ──
                if ($dob) {
                    $safe_dob = $conn->real_escape_string($dob);
                    $q = "INSERT INTO user_inputs (user_id, dob) VALUES ($user_id, '$safe_dob')";
                } else {
                    $q = "INSERT INTO user_inputs (user_id, dob) VALUES ($user_id, NULL)";
                }

                if ($conn->query($q)) {
                    $success = "Record saved! ID = $user_id";
                } else {
                    $error = "user_inputs error: " . $conn->error;
                }
            } else {
                $error = "users insert error: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

// ─── Fetch recent 10 records from the VIEW ────────────────────
$recent = $conn->query("SELECT * FROM user_report ORDER BY created_at DESC LIMIT 10");
if (!$recent) {
    $view_error = "VIEW query error: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UserDB — Add Record</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0d0f14; --surface: #161920; --surface2: #1e222d;
    --border: #2a2f3d; --accent: #00e5a0; --danger: #ff6b6b;
    --text: #e8eaf0; --muted: #6b7280;
    --font: 'Syne', sans-serif; --mono: 'DM Mono', monospace;
  }
  body { background: var(--bg); color: var(--text); font-family: var(--font); min-height: 100vh; padding: 2rem; }
  body::before {
    content: ''; position: fixed; inset: 0;
    background-image: linear-gradient(var(--border) 1px, transparent 1px), linear-gradient(90deg, var(--border) 1px, transparent 1px);
    background-size: 40px 40px; opacity: 0.15; pointer-events: none; z-index: 0;
  }
  .wrapper { max-width: 1000px; margin: 0 auto; position: relative; z-index: 1; }
  header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 2.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border); }
  header h1 { font-size: 2rem; font-weight: 800; letter-spacing: -0.04em; }
  header h1 span { color: var(--accent); }
  header p { font-family: var(--mono); font-size: 0.72rem; color: var(--muted); margin-top: 4px; }
  nav { display: flex; gap: 4px; background: var(--surface); padding: 4px; border-radius: 10px; border: 1px solid var(--border); }
  nav a { padding: 0.5rem 1.25rem; border-radius: 7px; text-decoration: none; font-size: 0.85rem; font-weight: 600; color: var(--muted); transition: all 0.2s; }
  nav a.active { background: var(--accent); color: #000; }
  nav a:not(.active):hover { color: var(--text); }
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 2rem; margin-bottom: 1.5rem; }
  .card-title { font-family: var(--mono); font-size: 0.68rem; color: var(--accent); letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 1.5rem; }

  /* Who fills what legend */
  .legend { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
  .leg-item { display: flex; align-items: center; gap: 8px; font-family: var(--mono); font-size: 0.72rem; }
  .leg-dot { width: 10px; height: 10px; border-radius: 50%; }
  .leg-dot.backend  { background: #7dd3fc; }
  .leg-dot.frontend { background: var(--accent); }

  .form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
  @media(max-width:640px){ .form-grid { grid-template-columns: 1fr; } }
  .field label { display: block; font-family: var(--mono); font-size: 0.68rem; color: var(--muted); letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 0.5rem; }
  .field input { width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; padding: 0.65rem 0.9rem; color: var(--text); font-family: var(--mono); font-size: 0.85rem; outline: none; transition: border-color 0.2s; }
  .field input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(0,229,160,0.08); }
  .field input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.5); cursor: pointer; }
  .field.frontend-field input { border-color: rgba(0,229,160,0.35); }
  .field.frontend-field input:focus { border-color: var(--accent); }
  .tag { display: inline-flex; align-items: center; gap: 5px; border-radius: 5px; padding: 2px 8px; font-family: var(--mono); font-size: 0.6rem; margin-bottom: 6px; font-weight: 500; }
  .tag.be { background: rgba(125,211,252,0.1); border: 1px solid rgba(125,211,252,0.25); color: #7dd3fc; }
  .tag.fe { background: rgba(0,229,160,0.08); border: 1px solid rgba(0,229,160,0.25); color: var(--accent); }

  .btn { display: inline-flex; align-items: center; gap: 8px; background: var(--accent); color: #000; border: none; border-radius: 8px; padding: 0.7rem 1.5rem; font-family: var(--font); font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: opacity 0.2s, transform 0.15s; }
  .btn:hover { opacity: 0.85; transform: translateY(-1px); }
  .alert { padding: 0.85rem 1.1rem; border-radius: 8px; font-family: var(--mono); font-size: 0.82rem; margin-bottom: 1.25rem; }
  .alert-success { background: rgba(0,229,160,0.1); border: 1px solid rgba(0,229,160,0.3); color: var(--accent); }
  .alert-error   { background: rgba(255,107,107,0.1); border: 1px solid rgba(255,107,107,0.3); color: var(--danger); }
  .table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border); }
  table { width: 100%; border-collapse: collapse; font-family: var(--mono); font-size: 0.81rem; }
  thead th { background: var(--surface2); padding: 0.8rem 1rem; text-align: left; font-size: 0.63rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); white-space: nowrap; }
  thead th.fe-col { color: var(--accent); }
  thead th.fe-col::after { content: ' ⌨'; }
  tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: var(--surface2); }
  tbody td { padding: 0.85rem 1rem; }
  .dob-val { color: var(--accent); font-weight: 500; }
  .null-val { color: var(--muted); font-style: italic; }
  .row-num { color: var(--muted); font-size: 0.7rem; }
  .empty { text-align: center; padding: 2.5rem; color: var(--muted); font-size: 0.8rem; }
</style>
</head>
<body>
<div class="wrapper">
  <header>
    <div>
      <h1>User<span>DB</span></h1>
      <p>// connected to MySQL · XAMPP</p>
    </div>
    <nav>
      <a href="index.php" class="active">Add Record</a>
      <a href="report.php">Reports</a>
    </nav>
  </header>

  <?php if ($success): ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if (!empty($view_error)): ?>
    <div class="alert alert-error">⚠ <?= htmlspecialchars($view_error) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-title">// new record entry</div>

    <!-- Legend: who fills what -->
    <div class="legend">
      <div class="leg-item"><div class="leg-dot backend"></div> <span style="color:#7dd3fc">Backend / System fills this</span></div>
      <div class="leg-item"><div class="leg-dot frontend"></div> <span style="color:var(--accent)">Front-end / User fills this</span></div>
    </div>

    <form method="POST" action="index.php">
      <div class="form-grid">

        <!-- BACKEND FIELD: Name (entered by admin/system/operator) -->
        <div class="field">
          <div class="tag be">⚙ backend / admin</div>
          <label>Full Name *</label>
          <input type="text" name="name" placeholder="e.g. Jane Doe"
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required />
        </div>

        <!-- BACKEND FIELD: Email -->
        <div class="field">
          <div class="tag be">⚙ backend / admin</div>
          <label>Email Address *</label>
          <input type="email" name="email" placeholder="jane@example.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required />
        </div>

        <!-- FRONTEND FIELD: DOB — user fills this themselves -->
        <div class="field frontend-field">
          <div class="tag fe">⌨ user fills this</div>
          <label>Date of Birth</label>
          <input type="date" name="dob"
                 value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>" />
        </div>

      </div>
      <button type="submit" class="btn">＋ Save Record</button>
    </form>
  </div>

  <div class="card">
    <div class="card-title">// recent 10 entries — queried from user_report VIEW</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email</th>
            <th class="fe-col">Date of Birth</th>
            <th class="fe-col">Age</th>
            <th>Added</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($recent && $recent->num_rows > 0): $i = 1; ?>
            <?php while ($row = $recent->fetch_assoc()): ?>
              <tr>
                <td class="row-num"><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td class="<?= $row['dob'] ? 'dob-val' : 'null-val' ?>">
                  <?= $row['dob'] ? date('d M Y', strtotime($row['dob'])) : 'not entered yet' ?>
                </td>
                <td class="<?= $row['age'] !== null ? 'dob-val' : 'null-val' ?>">
                  <?= $row['age'] !== null ? $row['age'] . ' yrs' : '—' ?>
                </td>
                <td class="null-val"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6" class="empty">No records yet. Fill the form above and click Save.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>