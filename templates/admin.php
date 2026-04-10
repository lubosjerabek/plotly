<?php $page_title = t('page_title_admin'); require __DIR__ . '/partials/head.php'; ?>
  <link rel="stylesheet" href="/assets/base.css">
  <style>
    /* Layout */
    .page{max-width:900px;margin:0 auto;padding:2rem 1.5rem}
    h1{font-size:20px;font-weight:700;margin:0 0 1.5rem}
    /* Tabs */
    .tabs{display:flex;gap:0;border-bottom:1px solid var(--border);margin-bottom:1.5rem}
    .tab-btn{padding:.6rem 1.2rem;background:none;border:none;border-bottom:2px solid transparent;color:var(--text-muted);font-family:inherit;font-size:13px;font-weight:500;cursor:pointer;transition:all var(--t-fast);margin-bottom:-1px}
    .tab-btn.active{color:var(--text);border-bottom-color:var(--accent)}
    .tab-btn:hover:not(.active){color:var(--text)}
    .tab-panel{display:none}.tab-panel.active{display:block}
    /* Table */
    table{width:100%;border-collapse:collapse}
    th{font-size:11px;font-weight:600;color:var(--text-subtle);text-transform:uppercase;letter-spacing:.06em;padding:.5rem .75rem;text-align:left;border-bottom:1px solid var(--border)}
    td{padding:.65rem .75rem;border-bottom:1px solid rgba(255,255,255,0.04);font-size:13px;vertical-align:middle}
    tr:last-child td{border-bottom:none}
    .badge{display:inline-block;padding:.15rem .55rem;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:.03em}
    .badge-admin{background:rgba(99,102,241,.2);color:#a5b4fc}
    .badge-user{background:var(--surface-3);color:var(--text-muted)}
    .badge-active{background:rgba(34,197,94,.15);color:#86efac}
    .badge-inactive{background:var(--surface-3);color:var(--text-subtle)}
    .badge-pending{background:rgba(245,158,11,.15);color:#fcd34d}
    .badge-used{background:var(--surface-3);color:var(--text-subtle)}
    /* Toolbar */
    .toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;gap:1rem}
    /* Admin modal (simple form layout) */
    .modal{border-radius:var(--radius-lg);padding:1.75rem;overflow:visible}
    .modal h2{font-size:16px;font-weight:700;margin:0 0 1.25rem}
    input[type=text],input[type=email],select{width:100%;padding:.6rem .85rem;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-md);color:var(--text);font-family:inherit;font-size:14px;outline:none;transition:border-color var(--t-fast);margin-bottom:1rem}
    input:focus,select:focus{border-color:var(--accent)}
    .modal-actions{display:flex;gap:.75rem;justify-content:flex-end;margin-top:.5rem}
    /* Result box */
    .result-box{display:none;margin-top:1rem;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-md);padding:.75rem;font-size:13px}
    .result-box.visible{display:block}
    .result-url{word-break:break-all;color:var(--text-muted);margin-bottom:.6rem}
    /* Lang dropdown */
    <?php require __DIR__ . '/partials/lang_dropdown.css.php' ?>
    /* Toast (admin uses simpler inline toast) */
    .toast-area{position:fixed;bottom:1.5rem;right:1.5rem;z-index:999;display:flex;flex-direction:column;gap:.5rem}
    .toast-area .toast{min-width:0;max-width:none;background:var(--surface-3);animation:toastIn .2s ease}
    @keyframes toastIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
    .empty-note{color:var(--text-subtle);font-size:13px;padding:1rem .75rem}
  </style>
</head>
<body>
<nav class="topbar">
  <a class="topbar__brand" href="/">
    <div class="topbar__brand-dot"></div>
    <?= htmlspecialchars(t('app_name')) ?>
  </a>
  <div class="topbar__right">
    <?php require __DIR__ . '/partials/lang_dropdown.html.php' ?>
    <a class="btn btn-ghost" href="/" style="font-size:12px"><?= htmlspecialchars(t('projects')) ?></a>
    <a class="btn btn-ghost" href="/logout" style="font-size:12px"><?= htmlspecialchars(t('sign_out')) ?></a>
  </div>
</nav>

<main class="page">
  <h1><?= htmlspecialchars(t('admin')) ?></h1>

  <div class="tabs" role="tablist">
    <button class="tab-btn active" onclick="switchTab('users')"><?= htmlspecialchars(t('users_tab')) ?></button>
    <button class="tab-btn" onclick="switchTab('invites')"><?= htmlspecialchars(t('invites_tab')) ?></button>
    <button class="tab-btn" onclick="switchTab('settings')"><?= htmlspecialchars(t('settings_tab')) ?></button>
  </div>

  <!-- Users tab -->
  <div id="tab-users" class="tab-panel active">
    <div id="usersTable"><p class="empty-note">Loading…</p></div>
  </div>

  <!-- Invites tab -->
  <div id="tab-invites" class="tab-panel">
    <div class="toolbar">
      <span></span>
      <button class="btn btn-primary btn-sm" onclick="openInviteModal()"><?= htmlspecialchars(t('generate_invite')) ?></button>
    </div>
    <div id="invitesTable"><p class="empty-note">Loading…</p></div>
  </div>

  <!-- Settings tab -->
  <div id="tab-settings" class="tab-panel">
    <div style="max-width:480px;padding-top:.5rem">
      <label class="field-label" for="sessionTimeout"><?= htmlspecialchars(t('session_timeout_label')) ?></label>
      <p style="font-size:12px;color:var(--text-muted);margin:-.5rem 0 .75rem"><?= htmlspecialchars(t('session_timeout_desc')) ?></p>
      <select id="sessionTimeout">
        <option value="0"><?= htmlspecialchars(t('session_browser')) ?></option>
        <option value="3600"><?= htmlspecialchars(t('session_1h')) ?></option>
        <option value="14400"><?= htmlspecialchars(t('session_4h')) ?></option>
        <option value="28800"><?= htmlspecialchars(t('session_8h')) ?></option>
        <option value="86400"><?= htmlspecialchars(t('session_24h')) ?></option>
        <option value="604800"><?= htmlspecialchars(t('session_7d')) ?></option>
      </select>
      <div style="display:flex;justify-content:flex-end">
        <button class="btn btn-primary btn-sm" onclick="saveSettings()"><?= htmlspecialchars(t('save_settings')) ?></button>
      </div>
    </div>
  </div>
</main>

<!-- Generate Invite Modal -->
<div class="modal-overlay" id="inviteModal">
  <div class="modal">
    <h2><?= htmlspecialchars(t('generate_invite')) ?></h2>
    <label class="field-label" for="inviteLabel"><?= htmlspecialchars(t('invite_label')) ?></label>
    <input type="text" id="inviteLabel" placeholder="<?= htmlspecialchars(t('invite_label_placeholder')) ?>">

    <label class="field-label" for="inviteDays"><?= htmlspecialchars(t('invite_expires')) ?></label>
    <select id="inviteDays">
      <option value="7"><?= htmlspecialchars(t('invite_7days')) ?></option>
      <option value="30"><?= htmlspecialchars(t('invite_30days')) ?></option>
      <option value="90"><?= htmlspecialchars(t('invite_90days')) ?></option>
    </select>

    <div class="result-box" id="inviteResult">
      <div class="result-url" id="inviteUrl"></div>
      <button class="btn btn-ghost btn-sm" onclick="copyInviteUrl()"><?= htmlspecialchars(t('copy_link')) ?></button>
    </div>

    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeInviteModal()"><?= htmlspecialchars(t('close')) ?></button>
      <button class="btn btn-primary" id="generateInviteBtn" onclick="generateInvite()"><?= htmlspecialchars(t('generate_invite')) ?></button>
    </div>
  </div>
</div>

<!-- Reset Link Result Modal -->
<div class="modal-overlay" id="resetModal">
  <div class="modal">
    <h2><?= htmlspecialchars(t('reset_link')) ?></h2>
    <p style="font-size:13px;color:var(--text-muted);margin:0 0 1rem"><?= htmlspecialchars(t('reset_link_share_note')) ?></p>
    <div class="result-box visible">
      <div class="result-url" id="resetUrl"></div>
      <button class="btn btn-ghost btn-sm" onclick="copyResetUrl()"><?= htmlspecialchars(t('copy_link')) ?></button>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="document.getElementById('resetModal').classList.remove('is-open')"><?= htmlspecialchars(t('close')) ?></button>
    </div>
  </div>
</div>

<!-- Toast area -->
<div class="toast-area" id="toastArea"></div>

<script>
window.T = <?= t_js() ?>;
<?php require __DIR__ . '/partials/lang_dropdown.js.php' ?>

function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach((b, i) => {
    b.classList.toggle('active', ['users','invites','settings'][i] === name);
  });
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  if (name === 'users')    loadUsers();
  if (name === 'invites')  loadInvites();
  if (name === 'settings') loadSettings();
}

// ── Users ────────────────────────────────────────────────────────────────────
async function loadUsers() {
  const res = await fetch('/api/admin/users');
  const users = await res.json();
  const el = document.getElementById('usersTable');
  if (!users.length) { el.innerHTML = '<p class="empty-note">' + T.no_users + '</p>'; return; }
  el.innerHTML = `
    <table>
      <thead><tr>
        <th>${T.user_name}</th><th>${T.user_email}</th>
        <th>${T.user_role}</th><th>${T.user_status}</th>
        <th>${T.user_joined}</th><th>${T.actions}</th>
      </tr></thead>
      <tbody>
        ${users.map(u => `
          <tr>
            <td>${escHtml(u.name)}</td>
            <td>${escHtml(u.email)}</td>
            <td><span class="badge badge-${u.role}">${u.role === 'admin' ? T.role_admin : T.role_user}</span></td>
            <td><span class="badge badge-${u.is_active ? 'active' : 'inactive'}">${u.is_active ? T.active : T.inactive}</span></td>
            <td>${escHtml(u.created_at.slice(0,10))}</td>
            <td style="display:flex;gap:.4rem;flex-wrap:wrap">
              <button class="btn btn-ghost btn-sm" onclick="generateResetLink(${u.id})">${T.generate_reset_link}</button>
              <button class="btn btn-${u.is_active ? 'danger' : 'ghost'} btn-sm" onclick="toggleActive(${u.id}, ${u.is_active})">
                ${u.is_active ? T.deactivate : T.activate}
              </button>
              ${u.role === 'user'
                ? `<button class="btn btn-ghost btn-sm" onclick="promoteUser(${u.id})">→ ${T.role_admin}</button>`
                : `<button class="btn btn-ghost btn-sm" onclick="demoteUser(${u.id})">→ ${T.role_user}</button>`}
            </td>
          </tr>
        `).join('')}
      </tbody>
    </table>`;
}

async function toggleActive(userId, currentlyActive) {
  await fetch('/api/admin/users/' + userId, {
    method: 'PATCH',
    headers: {'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({is_active: !currentlyActive})
  });
  toast(currentlyActive ? T.user_deactivated : T.user_activated);
  loadUsers();
}

async function promoteUser(userId) {
  await fetch('/api/admin/users/' + userId, {
    method: 'PATCH',
    headers: {'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({role: 'admin'})
  });
  toast(T.toast_user_updated);
  loadUsers();
}

async function demoteUser(userId) {
  await fetch('/api/admin/users/' + userId, {
    method: 'PATCH',
    headers: {'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({role: 'user'})
  });
  toast(T.toast_user_updated);
  loadUsers();
}

async function generateResetLink(userId) {
  const res = await fetch('/api/admin/users/' + userId + '/reset-password', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}});
  const data = await res.json();
  document.getElementById('resetUrl').textContent = data.url;
  document.getElementById('resetModal').classList.add('is-open');
}

function copyResetUrl() {
  const url = document.getElementById('resetUrl').textContent;
  navigator.clipboard.writeText(url).then(() => toast(T.toast_url_copied));
}

// ── Invites ──────────────────────────────────────────────────────────────────
async function loadInvites() {
  const res = await fetch('/api/admin/invites');
  const invites = await res.json();
  const el = document.getElementById('invitesTable');
  if (!invites.length) { el.innerHTML = '<p class="empty-note">' + T.no_invites + '</p>'; return; }
  const base = window.location.origin;
  el.innerHTML = `
    <table>
      <thead><tr>
        <th>${T.invite_label_col}</th><th>${T.invite_status}</th>
        <th>${T.invite_created}</th><th>${T.invite_expires_col}</th><th>${T.actions}</th>
      </tr></thead>
      <tbody>
        ${invites.map(inv => {
          const isUsed = !!inv.used_by_email;
          const isExpired = !isUsed && new Date(inv.expires_at) < new Date();
          const status = isUsed ? 'used' : (isExpired ? 'inactive' : 'pending');
          const statusLabel = isUsed ? T.invite_used + ' (' + escHtml(inv.used_by_email) + ')' : (isExpired ? T.invite_expired_label : T.invite_pending);
          return `<tr>
            <td>${escHtml(inv.label || '—')}</td>
            <td><span class="badge badge-${status}">${statusLabel}</span></td>
            <td>${escHtml(inv.created_at.slice(0,10))}</td>
            <td>${escHtml(inv.expires_at.slice(0,10))}</td>
            <td style="display:flex;gap:.4rem">
              ${!isUsed && !isExpired ? `
                <button class="btn btn-ghost btn-sm" onclick="copyLink('${base}/register/${encodeURIComponent(inv.token)}')">${T.copy_link}</button>
                <button class="btn btn-danger btn-sm" onclick="revokeInvite(${inv.id})">${T.revoke}</button>
              ` : ''}
            </td>
          </tr>`;
        }).join('')}
      </tbody>
    </table>`;
}

async function revokeInvite(id) {
  await fetch('/api/admin/invites/' + id, {method:'DELETE', headers:{'X-Requested-With':'XMLHttpRequest'}});
  toast(T.toast_invite_revoked);
  loadInvites();
}

// ── Invite modal ─────────────────────────────────────────────────────────────
function openInviteModal() {
  document.getElementById('inviteLabel').value = '';
  document.getElementById('inviteResult').classList.remove('visible');
  document.getElementById('inviteUrl').textContent = '';
  document.getElementById('generateInviteBtn').disabled = false;
  document.getElementById('inviteModal').classList.add('is-open');
}
function closeInviteModal() {
  document.getElementById('inviteModal').classList.remove('is-open');
  loadInvites();
}

async function generateInvite() {
  const label = document.getElementById('inviteLabel').value.trim();
  const days  = parseInt(document.getElementById('inviteDays').value, 10);
  document.getElementById('generateInviteBtn').disabled = true;
  const res  = await fetch('/api/admin/invites', {
    method: 'POST',
    headers: {'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({label, expires_days: days})
  });
  const data = await res.json();
  document.getElementById('inviteUrl').textContent = data.url;
  document.getElementById('inviteResult').classList.add('visible');
  toast(T.toast_invite_created);
}

function copyInviteUrl() {
  const url = document.getElementById('inviteUrl').textContent;
  navigator.clipboard.writeText(url).then(() => toast(T.toast_url_copied));
}

function copyLink(url) {
  navigator.clipboard.writeText(url).then(() => toast(T.toast_url_copied));
}

// ── Settings ─────────────────────────────────────────────────────────────────
async function loadSettings() {
  const data = await fetch('/api/admin/settings').then(r => r.json());
  document.getElementById('sessionTimeout').value = String(data.session_timeout);
}

async function saveSettings() {
  const timeout = parseInt(document.getElementById('sessionTimeout').value, 10);
  const res = await fetch('/api/admin/settings', {
    method: 'PUT',
    headers: {'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({session_timeout: timeout})
  });
  if (res.ok) toast(T.toast_settings_saved);
}

// ── Misc ─────────────────────────────────────────────────────────────────────
function escHtml(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function toast(msg) {
  const area = document.getElementById('toastArea');
  const el = document.createElement('div');
  el.className = 'toast';
  el.textContent = msg;
  area.appendChild(el);
  setTimeout(() => el.remove(), 3000);
}

// Close modals on backdrop click
document.getElementById('inviteModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeInviteModal(); });
document.getElementById('resetModal').addEventListener('click', e => { if (e.target === e.currentTarget) e.currentTarget.classList.remove('is-open'); });

// Init
loadUsers();
</script>
</body>
</html>
