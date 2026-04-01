<?php $lang = current_lang(); ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars(t('page_title_register')) ?></title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="icon" type="image/png" href="/favicon.php">
  <link rel="apple-touch-icon" href="/favicon.php">
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
      max-width: 400px;
    }
    .logo { font-size: 22px; font-weight: 700; letter-spacing: -0.5px; color: var(--text); margin: 0 0 .25rem; }
    .logo span { color: var(--accent); }
    .subtitle { color: var(--text-muted); font-size: 13px; margin: 0 0 2rem; }
    label {
      display: block; font-size: 12px; font-weight: 500; color: var(--text-muted);
      text-transform: uppercase; letter-spacing: .06em; margin-bottom: .4rem;
    }
    input[type=text], input[type=email], input[type=password] {
      width: 100%; padding: .625rem .875rem; background: var(--surface-2);
      border: 1px solid var(--border); border-radius: var(--radius-md);
      color: var(--text); font-family: inherit; font-size: 14px; outline: none;
      transition: border-color .15s; margin-bottom: 1rem;
    }
    input:focus { border-color: var(--accent); }
    .btn {
      width: 100%; padding: .7rem 1rem; background: var(--accent); color: #fff;
      font-family: inherit; font-size: 14px; font-weight: 600; border: none;
      border-radius: var(--radius-md); cursor: pointer; transition: background .15s; margin-top: .25rem;
    }
    .btn:hover { background: var(--accent-hover); }
    .error {
      background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3);
      color: #fca5a5; border-radius: var(--radius-md); padding: .625rem .875rem;
      font-size: 13px; margin-bottom: 1.25rem;
    }
    .lang-switcher { display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem; }
    .lang-switcher form { display: inline; }
    .lang-btn {
      background: none; border: 1px solid var(--border); border-radius: 6px;
      color: var(--text-muted); font-family: inherit; font-size: 11px; font-weight: 600;
      letter-spacing: 0.05em; padding: 0.2rem 0.55rem; cursor: pointer; transition: all .15s;
    }
    .lang-btn:hover, .lang-btn.active { border-color: var(--accent); color: var(--accent); background: rgba(99,102,241,0.1); }
  </style>
</head>
<body>
  <div class="card">
    <p class="logo">Plot<span>ly</span></p>
    <p class="subtitle"><?= htmlspecialchars(t('register_subtitle')) ?></p>

    <?php if (!$invite): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
      <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" action="/register/<?= htmlspecialchars($token) ?>">
        <label for="name"><?= htmlspecialchars(t('full_name')) ?></label>
        <input type="text" id="name" name="name"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autofocus>

        <label for="email"><?= htmlspecialchars(t('email')) ?></label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

        <label for="password"><?= htmlspecialchars(t('password')) ?></label>
        <input type="password" id="password" name="password" autocomplete="new-password" required>

        <label for="password_confirm"><?= htmlspecialchars(t('confirm_password')) ?></label>
        <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>

        <button type="submit" class="btn"><?= htmlspecialchars(t('create_account')) ?></button>
      </form>
    <?php endif; ?>

    <div class="lang-switcher">
      <form method="post" action="/set-lang">
        <input type="hidden" name="lang" value="en">
        <button type="submit" class="lang-btn<?= $lang === 'en' ? ' active' : '' ?>"><?= t('lang_en') ?></button>
      </form>
      <form method="post" action="/set-lang">
        <input type="hidden" name="lang" value="cs">
        <button type="submit" class="lang-btn<?= $lang === 'cs' ? ' active' : '' ?>"><?= t('lang_cs') ?></button>
      </form>
    </div>
  </div>
</body>
</html>
