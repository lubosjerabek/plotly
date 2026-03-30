<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Plotly — Sign In</title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:        #0f0f13;
      --surface:   #16161d;
      --surface-2: #1e1e2a;
      --border:    rgba(255,255,255,0.08);
      --accent:    #6366f1;
      --accent-hover: #4f51d4;
      --danger:    #ef4444;
      --text:      #f1f5f9;
      --text-muted:#94a3b8;
      --radius-md: 10px;
      --radius-lg: 16px;
      --shadow-lg: 0 8px 32px rgba(0,0,0,0.6);
    }
    *, *::before, *::after { box-sizing: border-box; }
    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
    }
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      padding: 2.5rem 2rem;
      width: 100%;
      max-width: 380px;
    }
    .logo {
      font-size: 22px;
      font-weight: 700;
      letter-spacing: -0.5px;
      color: var(--text);
      margin: 0 0 .25rem;
    }
    .logo span { color: var(--accent); }
    .subtitle {
      color: var(--text-muted);
      font-size: 13px;
      margin: 0 0 2rem;
    }
    label {
      display: block;
      font-size: 12px;
      font-weight: 500;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: .06em;
      margin-bottom: .4rem;
    }
    input[type=text], input[type=password] {
      width: 100%;
      padding: .625rem .875rem;
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      color: var(--text);
      font-family: inherit;
      font-size: 14px;
      outline: none;
      transition: border-color .15s;
      margin-bottom: 1rem;
    }
    input[type=text]:focus, input[type=password]:focus {
      border-color: var(--accent);
    }
    .btn {
      width: 100%;
      padding: .7rem 1rem;
      background: var(--accent);
      color: #fff;
      font-family: inherit;
      font-size: 14px;
      font-weight: 600;
      border: none;
      border-radius: var(--radius-md);
      cursor: pointer;
      transition: background .15s;
      margin-top: .25rem;
    }
    .btn:hover { background: var(--accent-hover); }
    .error {
      background: rgba(239,68,68,.12);
      border: 1px solid rgba(239,68,68,.3);
      color: #fca5a5;
      border-radius: var(--radius-md);
      padding: .625rem .875rem;
      font-size: 13px;
      margin-bottom: 1.25rem;
    }
  </style>
</head>
<body>
  <div class="card">
    <p class="logo">Plot<span>ly</span></p>
    <p class="subtitle">Sign in to continue</p>

    <?php if (!empty($error)): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" autocomplete="username" required autofocus>

      <label for="password">Password</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>

      <button type="submit" class="btn">Sign In</button>
    </form>
  </div>
</body>
</html>
