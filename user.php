<?php
require 'db.php';

$success = '';
$error   = '';
$user    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ── STEP 1: Lookup by email — read-only from user_report VIEW ──
    if ($_POST['action'] === 'lookup') {
        $email = trim($_POST['email'] ?? '');
        if (!$email) {
            $error = 'Please enter your email address.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM user_report WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                $error = "No account found for \"$email\". Please contact your administrator.";
            } else {
                $user = $res->fetch_assoc();
            }
            $stmt->close();
        }
    }

    // ── STEP 2: Save DOB — only touches user_inputs, never users ──
    if ($_POST['action'] === 'save_dob') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $dob = trim($_POST['dob'] ?? '');

        if (!$uid) {
            $error = 'Invalid session. Please search again.';
        } elseif (!$dob) {
            $error = 'Please select your date of birth.';
        } else {
            $safe_dob = $conn->real_escape_string($dob);

            // Check if user_inputs row already exists for this user_id
            $check = $conn->query("SELECT user_id FROM user_inputs WHERE user_id = $uid");

            if ($check && $check->num_rows > 0) {
                // Row exists → just UPDATE the dob
                $ok = $conn->query("UPDATE user_inputs SET dob = '$safe_dob' WHERE user_id = $uid");
            } else {
                // No row yet → INSERT (should rarely happen since admin creates it)
                $ok = $conn->query("INSERT INTO user_inputs (user_id, dob) VALUES ($uid, '$safe_dob')");
            }

            if ($ok) {
                $success = 'Your date of birth has been saved successfully!';
            } else {
                $error = 'Save failed: ' . $conn->error;
            }
        }

        // Re-fetch the updated user from VIEW (so DOB reflects what was just saved)
        if ($uid) {
            $stmt = $conn->prepare("SELECT * FROM user_report WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — UserDB</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #f5f3ef; --white: #ffffff; --border: #e0dbd2;
  --accent: #2d6a4f; --accent-light: #e8f4f0; --accent2: #d4a017;
  --text: #1a1a1a; --muted: #888880; --danger: #c0392b;
  --font-head: 'Playfair Display', serif; --font-body: 'Lato', sans-serif;
}
body { background: var(--bg); color: var(--text); font-family: var(--font-body); min-height: 100vh; display: flex; flex-direction: column; }
.top-stripe { background: var(--accent); height: 4px; }
header { background: var(--white); border-bottom: 1px solid var(--border); padding: 1.25rem 2rem; display: flex; align-items: center; justify-content: space-between; }
.brand { font-family: var(--font-head); font-size: 1.5rem; color: var(--text); }
.brand span { color: var(--accent); }
.user-badge { display: flex; align-items: center; gap: 8px; background: var(--accent-light); border: 1px solid rgba(45,106,79,0.2); border-radius: 30px; padding: 6px 14px; font-size: 0.78rem; color: var(--accent); font-weight: 700; letter-spacing: 0.05em; }
.user-dot { width: 7px; height: 7px; background: var(--accent); border-radius: 50%; }
.page { flex: 1; display: flex; align-items: center; justify-content: center; padding: 3rem 1.5rem; }
.container { width: 100%; max-width: 500px; }
.welcome-card { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 24px rgba(0,0,0,0.06); margin-bottom: 1.5rem; }
.card-eyebrow { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--accent); margin-bottom: 0.5rem; }
.card-title { font-family: var(--font-head); font-size: 1.8rem; line-height: 1.2; margin-bottom: 0.75rem; color: var(--text); }
.card-desc { font-size: 0.88rem; color: var(--muted); line-height: 1.6; margin-bottom: 2rem; }
.field { margin-bottom: 1.25rem; }
.field label { display: block; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text); margin-bottom: 6px; }
.field input { width: 100%; background: var(--bg); border: 1.5px solid var(--border); border-radius: 8px; padding: 0.8rem 1rem; color: var(--text); font-family: var(--font-body); font-size: 0.95rem; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
.field input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(45,106,79,0.1); }
.field input[type="date"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.5; }
.btn-primary { width: 100%; background: var(--accent); color: var(--white); border: none; border-radius: 8px; padding: 0.9rem; font-family: var(--font-body); font-size: 0.95rem; font-weight: 700; letter-spacing: 0.04em; cursor: pointer; transition: background 0.2s, transform 0.15s; }
.btn-primary:hover { background: #235c42; transform: translateY(-1px); }
.btn-secondary { width: 100%; background: transparent; color: var(--muted); border: 1.5px solid var(--border); border-radius: 8px; padding: 0.75rem; font-family: var(--font-body); font-size: 0.85rem; cursor: pointer; margin-top: 0.75rem; transition: all 0.2s; }
.btn-secondary:hover { border-color: var(--text); color: var(--text); }
.alert { padding: 0.9rem 1.1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.25rem; display: flex; align-items: flex-start; gap: 10px; }
.alert-ok  { background: #eaf7f0; border: 1px solid #b7e4cc; color: #1e6641; }
.alert-err { background: #fdf0ef; border: 1px solid #f0c0bb; color: var(--danger); }
.profile-card { background: var(--white); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
.profile-header { background: var(--accent); padding: 1.75rem 2rem; display: flex; align-items: center; gap: 1.25rem; }
.avatar { width: 56px; height: 56px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: var(--font-head); font-size: 1.5rem; color: #fff; flex-shrink: 0; }
.profile-name  { font-family: var(--font-head); font-size: 1.3rem; color: #fff; }
.profile-email { font-size: 0.8rem; color: rgba(255,255,255,0.7); margin-top: 2px; }
.profile-body  { padding: 1.5rem 2rem; }
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 0.9rem 0; border-bottom: 1px solid var(--border); }
.info-row:last-of-type { border-bottom: none; }
.info-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
.info-value { font-size: 0.92rem; color: var(--text); }
.info-value.filled { color: var(--accent); font-weight: 700; }
.info-value.empty  { color: var(--muted); font-style: italic; }
.status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 30px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em; }
.status-done { background: #eaf7f0; color: var(--accent); border: 1px solid #b7e4cc; }
.status-pend { background: #fff8e6; color: var(--accent2); border: 1px solid #f0dfa0; }
.dob-section { background: var(--bg); border: 1.5px dashed var(--border); border-radius: 10px; padding: 1.25rem; margin-top: 1.25rem; }
.dob-section-title { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--accent); margin-bottom: 1rem; }
.dob-row { display: flex; gap: 0.75rem; align-items: center; }
.dob-row input { flex: 1; background: var(--white); border: 1.5px solid var(--border); border-radius: 7px; padding: 0.65rem 0.9rem; color: var(--text); font-family: var(--font-body); font-size: 0.9rem; outline: none; transition: border-color 0.2s; }
.dob-row input:focus { border-color: var(--accent); }
.dob-row button { background: var(--accent); color: #fff; border: none; border-radius: 7px; padding: 0.65rem 1.25rem; font-family: var(--font-body); font-weight: 700; font-size: 0.85rem; cursor: pointer; white-space: nowrap; transition: background 0.2s; }
.dob-row button:hover { background: #235c42; }
footer { text-align: center; padding: 1.5rem; font-size: 0.75rem; color: var(--muted); border-top: 1px solid var(--border); background: var(--white); }
</style>
</head>
<body>
<div class="top-stripe"></div>
<header>
  <div class="brand">User<span>DB</span></div>
  <div class="user-badge"><div class="user-dot"></div> USER PORTAL</div>
</header>
<div class="page">
  <div class="container">

    <?php if ($success): ?>
      <div class="alert alert-ok">✓ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-err">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$user): ?>
    <div class="welcome-card">
      <div class="card-eyebrow">Welcome</div>
      <div class="card-title">Find Your Account</div>
      <div class="card-desc">Enter the email address your administrator registered you with to access your profile and submit your date of birth.</div>
      <form method="POST" action="user.php">
        <input type="hidden" name="action" value="lookup">
        <div class="field">
          <label>Your Email Address</label>
          <input type="email" name="email" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus />
        </div>
        <button type="submit" class="btn-primary">Find My Account →</button>
      </form>
    </div>

    <?php else: ?>
    <div class="profile-card">
      <div class="profile-header">
        <div class="avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div>
          <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
          <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
        </div>
      </div>
      <div class="profile-body">
        <div class="info-row">
          <span class="info-label">User ID</span>
          <span class="info-value">#<?= $user['id'] ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Name</span>
          <span class="info-value"><?= htmlspecialchars($user['name']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Email</span>
          <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Date of Birth</span>
          <span class="info-value <?= $user['dob'] ? 'filled' : 'empty' ?>">
            <?= $user['dob'] ? date('d F Y', strtotime($user['dob'])) : 'Not entered yet' ?>
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">Age</span>
          <span class="info-value <?= $user['age'] !== null ? 'filled' : 'empty' ?>">
            <?= $user['age'] !== null ? $user['age'] . ' years old' : '—' ?>
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">Profile Status</span>
          <span class="status-pill <?= $user['dob'] ? 'status-done' : 'status-pend' ?>">
            <?= $user['dob'] ? '✓ Complete' : '⏳ Pending DOB' ?>
          </span>
        </div>

        <!-- DOB input — only writes to user_inputs, never creates a new user -->
        <div class="dob-section">
          <div class="dob-section-title">
            <?= $user['dob'] ? '✎ Update Your Date of Birth' : '⌨ Please Enter Your Date of Birth' ?>
          </div>
          <form method="POST" action="user.php">
            <input type="hidden" name="action" value="save_dob">
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            <div class="dob-row">
              <input type="date" name="dob" value="<?= htmlspecialchars($user['dob'] ?? '') ?>" max="<?= date('Y-m-d') ?>" required />
              <button type="submit">Save DOB</button>
            </div>
          </form>
        </div>

        <form method="GET" action="user.php" style="margin-top:1rem">
          <button type="submit" class="btn-secondary">← Search another account</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
<footer>UserDB &mdash; User Portal &nbsp;|&nbsp; Contact your administrator if you have issues</footer>
</body>
</html>