<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Plotly Setup — Generate Password Hash</title>
  <style>
    body { font-family: monospace; background: #0f0f13; color: #f1f5f9; padding: 2rem; }
    label { display: block; margin-bottom: .5rem; color: #94a3b8; }
    input[type=password] { padding: .5rem .75rem; border-radius: 6px; border: 1px solid #333; background: #1e1e2a; color: #f1f5f9; font-family: monospace; font-size: 14px; width: 320px; }
    button { margin-top: 1rem; padding: .5rem 1.25rem; background: #6366f1; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; }
    .result { margin-top: 1.5rem; background: #1e1e2a; border: 1px solid #333; border-radius: 8px; padding: 1rem 1.25rem; }
    .hash { word-break: break-all; font-size: 13px; color: #22c55e; }
    .warn { color: #ef4444; margin-top: 1.5rem; font-size: 13px; }
  </style>
</head>
<body>
<h2>Plotly — One-time Password Hash Generator</h2>
<p>Enter the password you want to use for the admin login. Copy the hash into <code>config.php</code> as <code>AUTH_PASS_HASH</code>, then <strong>delete this file</strong>.</p>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    if (strlen($pw) < 8) {
        echo '<p style="color:#ef4444">Password must be at least 8 characters.</p>';
    } else {
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        echo '<div class="result">';
        echo '<p>Paste this into <code>config.php</code> as <code>AUTH_PASS_HASH</code>:</p>';
        echo '<p class="hash">' . htmlspecialchars($hash) . '</p>';
        echo '</div>';
    }
}
?>

<form method="post">
  <label for="pw">Password (min 8 characters)</label>
  <input type="password" id="pw" name="password" required minlength="8">
  <br>
  <button type="submit">Generate Hash</button>
</form>

<p class="warn">⚠ Delete this file via FTP after you have copied the hash into config.php.</p>
</body>
</html>
