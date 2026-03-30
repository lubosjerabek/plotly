<?php
$lang = current_lang();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars(t('page_title_projects')) ?></title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:          #0f0f13;
      --surface:     #16161d;
      --surface-2:   #1e1e2a;
      --surface-3:   #252535;
      --border:      rgba(255,255,255,0.08);
      --border-hover:rgba(255,255,255,0.16);
      --accent:      #6366f1;
      --accent-hover:#4f51d4;
      --accent-muted:rgba(99,102,241,0.15);
      --danger:      #ef4444;
      --danger-muted:rgba(239,68,68,0.12);
      --success:     #22c55e;
      --text:        #f1f5f9;
      --text-muted:  #94a3b8;
      --text-subtle: #64748b;
      --radius-sm:   6px;
      --radius-md:   10px;
      --radius-lg:   16px;
      --radius-xl:   24px;
      --shadow-md:   0 4px 20px rgba(0,0,0,0.5);
      --shadow-lg:   0 8px 32px rgba(0,0,0,0.6);
      --shadow-glow: 0 0 0 1px var(--accent), 0 0 20px rgba(99,102,241,0.2);
      --t-fast:      0.15s ease;
      --t-base:      0.25s ease;
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      background: var(--bg);
      color: var(--text);
      margin: 0;
      min-height: 100vh;
      font-size: 14px;
      line-height: 1.6;
    }

    /* ── Topbar ── */
    .topbar {
      position: sticky;
      top: 0;
      z-index: 100;
      background: rgba(15,15,19,0.85);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 2rem;
      height: 56px;
    }
    .topbar__brand {
      font-size: 17px;
      font-weight: 700;
      color: var(--text);
      letter-spacing: -0.02em;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .topbar__brand-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--accent);
      box-shadow: 0 0 8px var(--accent);
    }
    .topbar__right {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    /* ── Page ── */
    .page {
      max-width: 1100px;
      margin: 0 auto;
      padding: 2.5rem 2rem;
    }
    .page__header {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      margin-bottom: 2rem;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .page__title-area h1 {
      font-size: 1.75rem;
      font-weight: 700;
      margin: 0 0 0.25rem;
      letter-spacing: -0.03em;
    }
    .page__subtitle {
      color: var(--text-muted);
      margin: 0;
      font-size: 13px;
    }

    /* ── Buttons ── */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 0.4em;
      padding: 0.5rem 1rem;
      border-radius: var(--radius-sm);
      font-family: inherit;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all var(--t-fast);
      border: none;
      text-decoration: none;
      white-space: nowrap;
    }
    .btn-primary {
      background: var(--accent);
      color: #fff;
    }
    .btn-primary:hover { background: var(--accent-hover); }
    .btn-ghost {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text-muted);
    }
    .btn-ghost:hover { border-color: var(--border-hover); color: var(--text); background: var(--surface-2); }
    .btn-danger {
      background: transparent;
      border: 1px solid transparent;
      color: var(--danger);
    }
    .btn-danger:hover { background: var(--danger-muted); border-color: var(--danger); }
    .btn-icon {
      padding: 0.35rem;
      width: 28px;
      height: 28px;
      justify-content: center;
    }
    .btn svg { width: 14px; height: 14px; fill: currentColor; flex-shrink: 0; }

    /* ── Language switcher ── */
    .lang-switcher {
      display: flex;
      gap: 0.25rem;
    }
    .lang-switcher form { display: inline; }
    .lang-btn {
      background: none;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      color: var(--text-muted);
      font-family: inherit;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.05em;
      padding: 0.25rem 0.5rem;
      cursor: pointer;
      transition: all var(--t-fast);
    }
    .lang-btn:hover,
    .lang-btn.active { border-color: var(--accent); color: var(--accent); background: rgba(99,102,241,0.1); }

    /* ── Project Grid ── */
    .projects-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1.25rem;
    }

    /* ── Project Card ── */
    .project-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 1.25rem 1.5rem;
      display: flex;
      flex-direction: column;
      gap: 0.6rem;
      transition: border-color var(--t-base), box-shadow var(--t-base);
      position: relative;
    }
    .project-card:hover {
      border-color: var(--accent);
      box-shadow: var(--shadow-glow);
    }
    .project-card__header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 0.5rem;
    }
    .project-card__name {
      font-size: 15px;
      font-weight: 600;
      margin: 0;
      color: var(--text);
      word-break: break-word;
    }
    .project-card__actions {
      display: flex;
      gap: 0.2rem;
      flex-shrink: 0;
      opacity: 0;
      transition: opacity var(--t-fast);
    }
    .project-card:hover .project-card__actions { opacity: 1; }
    .project-card__desc {
      color: var(--text-muted);
      font-size: 13px;
      margin: 0;
      flex: 1;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      min-height: 2.4em;
    }
    .project-card__footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 0.4rem;
      padding-top: 0.75rem;
      border-top: 1px solid var(--border);
    }
    .project-card__meta {
      font-size: 12px;
      color: var(--text-subtle);
    }

    /* ── Empty State ── */
    .empty-state {
      grid-column: 1 / -1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 4rem 2rem;
      text-align: center;
      border: 1px dashed var(--border);
      border-radius: var(--radius-lg);
      color: var(--text-muted);
    }
    .empty-state__icon {
      width: 48px; height: 48px;
      background: var(--surface-2);
      border-radius: var(--radius-md);
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 1rem;
    }
    .empty-state__icon svg { width: 24px; height: 24px; fill: var(--text-subtle); }
    .empty-state h3 { margin: 0 0 0.5rem; font-size: 15px; color: var(--text); }
    .empty-state p { margin: 0 0 1.5rem; font-size: 13px; }

    /* ── Modal ── */
    .modal-overlay {
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.7);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 500;
      padding: 1rem;
    }
    .modal-overlay.is-open { display: flex; }
    .modal {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-xl);
      width: 100%; max-width: 440px;
      box-shadow: var(--shadow-lg);
      animation: slideUp var(--t-base) forwards;
      overflow: hidden;
    }
    .modal--sm { max-width: 360px; }
    .modal__header {
      display: flex; justify-content: space-between; align-items: center;
      padding: 1.25rem 1.5rem 0;
    }
    .modal__title { margin: 0; font-size: 1.05rem; font-weight: 600; }
    .modal__close {
      background: none; border: none; color: var(--text-muted); cursor: pointer;
      font-size: 18px; line-height: 1; padding: 4px; border-radius: var(--radius-sm);
      transition: color var(--t-fast);
    }
    .modal__close:hover { color: var(--text); }
    .modal__body { padding: 1.25rem 1.5rem; }
    .modal__footer {
      display: flex; justify-content: flex-end; gap: 0.5rem;
      padding: 0 1.5rem 1.25rem;
    }
    .modal-field { margin-bottom: 1rem; }
    .modal-field:last-child { margin-bottom: 0; }
    .field-label {
      display: block; margin-bottom: 0.4rem;
      font-size: 12px; font-weight: 500; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;
    }
    .modal-field input, .modal-field select, .modal-field textarea {
      width: 100%; padding: 0.65rem 0.75rem;
      background: var(--surface-3); border: 1px solid var(--border);
      border-radius: var(--radius-sm); color: var(--text);
      font-family: inherit; font-size: 14px;
      transition: border-color var(--t-fast), box-shadow var(--t-fast);
    }
    .modal-field input:focus, .modal-field select:focus {
      outline: none; border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-muted);
    }
    .confirm-message { color: var(--text-muted); margin: 0; line-height: 1.6; }

    /* ── Toasts ── */
    .toast-container {
      position: fixed; bottom: 1.5rem; right: 1.5rem;
      z-index: 1000; display: flex; flex-direction: column; gap: 0.5rem;
      pointer-events: none;
    }
    .toast {
      pointer-events: all;
      display: flex; align-items: center; gap: 0.75rem;
      background: var(--surface-2); border: 1px solid var(--border);
      border-radius: var(--radius-md); padding: 0.75rem 1rem;
      min-width: 260px; max-width: 400px; box-shadow: var(--shadow-md);
      font-size: 14px; animation: toastIn 0.2s ease forwards;
    }
    .toast--success { border-left: 3px solid var(--success); }
    .toast--error   { border-left: 3px solid var(--danger); }
    .toast--info    { border-left: 3px solid var(--accent); }
    .toast__body { flex: 1; }
    .toast__close {
      background: none; border: none; color: var(--text-subtle);
      cursor: pointer; padding: 0; font-size: 16px; line-height: 1;
    }
    .toast.is-dismissing { animation: toastOut 0.2s ease forwards; }

    @keyframes slideUp {
      from { transform: translateY(16px); opacity: 0; }
      to   { transform: translateY(0);    opacity: 1; }
    }
    @keyframes toastIn {
      from { transform: translateX(110%); opacity: 0; }
      to   { transform: translateX(0);    opacity: 1; }
    }
    @keyframes toastOut {
      from { transform: translateX(0);    opacity: 1; }
      to   { transform: translateX(110%); opacity: 0; }
    }

    @media (max-width: 600px) {
      .page { padding: 1.5rem 1rem; }
      .topbar { padding: 0 1rem; }
      .page__header { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body>

<!-- SVG icon sprite -->
<svg style="display:none" aria-hidden="true">
  <symbol id="icon-pencil" viewBox="0 0 16 16">
    <path d="M11.013 1.427a1.75 1.75 0 0 1 2.474 0l1.086 1.086a1.75 1.75 0 0 1 0 2.474l-8.61 8.61c-.21.21-.47.364-.756.445l-3.251.93a.75.75 0 0 1-.927-.928l.929-3.25c.081-.286.235-.547.445-.758l8.61-8.61zm1.414 1.06a.25.25 0 0 0-.354 0L10.811 3.75l1.439 1.44 1.263-1.263a.25.25 0 0 0 0-.354l-1.086-1.086zM11.189 6.25 9.75 4.81 3.847 10.714a.25.25 0 0 0-.064.108l-.558 1.953 1.953-.558a.249.249 0 0 0 .108-.064L11.189 6.25z"/>
  </symbol>
  <symbol id="icon-trash" viewBox="0 0 16 16">
    <path d="M11 1.75V3h2.25a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1 0-1.5H5V1.75C5 .784 5.784 0 6.75 0h2.5C10.216 0 11 .784 11 1.75zM4.496 6.675l.66 6.6a.25.25 0 0 0 .249.225h5.19a.25.25 0 0 0 .249-.225l.66-6.6a.75.75 0 0 1 1.492.149l-.66 6.6A1.748 1.748 0 0 1 10.595 15h-5.19a1.75 1.75 0 0 1-1.741-1.575l-.66-6.6a.75.75 0 1 1 1.492-.15zM6.5 1.75V3h3V1.75a.25.25 0 0 0-.25-.25h-2.5a.25.25 0 0 0-.25.25z"/>
  </symbol>
  <symbol id="icon-folder" viewBox="0 0 16 16">
    <path d="M1.75 1A1.75 1.75 0 0 0 0 2.75v10.5C0 14.216.784 15 1.75 15h12.5A1.75 1.75 0 0 0 16 13.25v-8.5A1.75 1.75 0 0 0 14.25 3H7.5a.25.25 0 0 1-.2-.1l-.9-1.2C6.07 1.26 5.55 1 5 1H1.75z"/>
  </symbol>
  <symbol id="icon-plus" viewBox="0 0 16 16">
    <path d="M7.75 2a.75.75 0 0 1 .75.75V7h4.25a.75.75 0 0 1 0 1.5H8.5v4.25a.75.75 0 0 1-1.5 0V8.5H2.75a.75.75 0 0 1 0-1.5H7V2.75A.75.75 0 0 1 7.75 2z"/>
  </symbol>
</svg>

<nav class="topbar">
  <div class="topbar__brand">
    <div class="topbar__brand-dot"></div>
    <?= htmlspecialchars(t('app_name')) ?>
  </div>
  <div class="topbar__right">
    <!-- Language switcher -->
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
    <a class="btn btn-ghost" href="/logout" style="font-size:12px"><?= htmlspecialchars(t('sign_out')) ?></a>
  </div>
</nav>

<main class="page">
  <div class="page__header">
    <div class="page__title-area">
      <h1><?= htmlspecialchars(t('projects')) ?></h1>
      <p class="page__subtitle" id="pageSubtitle"></p>
    </div>
    <button class="btn btn-primary" id="btnNewProject" onclick="openNewProjectModal()">
      <svg><use href="#icon-plus"/></svg>
      <?= htmlspecialchars(t('new_project')) ?>
    </button>
  </div>

  <div class="projects-grid" id="projectsGrid"></div>
</main>

<!-- New / Edit Project Modal -->
<div class="modal-overlay" id="projectModal" role="dialog" aria-modal="true" aria-labelledby="projectModalTitle">
  <div class="modal">
    <div class="modal__header">
      <h2 class="modal__title" id="projectModalTitle"><?= htmlspecialchars(t('new_project')) ?></h2>
      <button class="modal__close" onclick="closeProjectModal()" aria-label="<?= htmlspecialchars(t('close')) ?>">✕</button>
    </div>
    <div class="modal__body">
      <div class="modal-field">
        <label class="field-label" for="pm_name"><?= htmlspecialchars(t('project_name')) ?></label>
        <input type="text" id="pm_name" placeholder="<?= htmlspecialchars(t('project_name_example')) ?>" autocomplete="off">
      </div>
      <div class="modal-field">
        <label class="field-label" for="pm_desc"><?= htmlspecialchars(t('description')) ?></label>
        <input type="text" id="pm_desc" placeholder="<?= htmlspecialchars(t('description_optional')) ?>" autocomplete="off">
      </div>
    </div>
    <div class="modal__footer">
      <button class="btn btn-ghost" onclick="closeProjectModal()"><?= htmlspecialchars(t('cancel')) ?></button>
      <button class="btn btn-primary" id="projectModalSubmit" onclick="submitProjectModal()"><?= htmlspecialchars(t('create')) ?></button>
    </div>
  </div>
</div>

<!-- Confirmation Modal -->
<div class="modal-overlay" id="confirmModal" role="alertdialog" aria-modal="true" aria-labelledby="confirmTitle">
  <div class="modal modal--sm">
    <div class="modal__header">
      <h2 class="modal__title" id="confirmTitle"><?= htmlspecialchars(t('confirm')) ?></h2>
    </div>
    <div class="modal__body">
      <p class="confirm-message" id="confirmMessage"></p>
    </div>
    <div class="modal__footer">
      <button class="btn btn-ghost" onclick="closeConfirm()"><?= htmlspecialchars(t('cancel')) ?></button>
      <button class="btn btn-danger" id="confirmOkBtn"><?= htmlspecialchars(t('delete')) ?></button>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer" aria-live="polite"></div>

<script>
  // ── Translations (PHP-injected) ──────────────────────────────
  const T = <?= t_js() ?>;

  // ── State ────────────────────────────────────────────────────
  const state = { projects: [] };
  let _editingProjectId = null;

  // ── API ──────────────────────────────────────────────────────
  const h = { 'Content-Type': 'application/json' };
  const api = {
    getProjects:   ()         => fetch('/api/projects').then(r => r.json()),
    createProject: (data)     => fetch('/api/projects', { method: 'POST', headers: h, body: JSON.stringify(data) }),
    updateProject: (id, data) => fetch(`/api/projects/${id}`, { method: 'PUT', headers: h, body: JSON.stringify(data) }),
    deleteProject: (id)       => fetch(`/api/projects/${id}`, { method: 'DELETE' }),
  };

  // ── Render ───────────────────────────────────────────────────
  function renderProjects() {
    const grid = document.getElementById('projectsGrid');
    const subtitle = document.getElementById('pageSubtitle');
    grid.innerHTML = '';

    const count = state.projects.length;
    if (count === 0) {
      subtitle.textContent = T.no_projects_count;
    } else {
      subtitle.textContent = count === 1
        ? T.n_phases.replace('%d', count)   // reuse singular pattern
        : (T.n_projects_plural || T.n_projects).replace('%d', count);
      // prefer dedicated project plural key
      subtitle.textContent = count === 1
        ? T.n_projects.replace('%d', count)
        : (T.n_projects_plural5 && count >= 5 ? T.n_projects_plural5 : T.n_projects_plural || T.n_projects).replace('%d', count);
    }

    if (count === 0) {
      grid.innerHTML = `
        <div class="empty-state">
          <div class="empty-state__icon">
            <svg><use href="#icon-folder"/></svg>
          </div>
          <h3>${escHtml(T.no_projects_title)}</h3>
          <p>${escHtml(T.no_projects_body)}</p>
          <button class="btn btn-primary" onclick="openNewProjectModal()">
            <svg><use href="#icon-plus"/></svg>
            ${escHtml(T.new_project)}
          </button>
        </div>`;
      return;
    }

    state.projects.forEach(p => {
      const phaseCount = (p.phases || []).length;
      let phasesLabel;
      if (phaseCount === 1) {
        phasesLabel = (T.n_phases || '%d phase').replace('%d', phaseCount);
      } else if (phaseCount >= 5 && T.n_phases_plural5) {
        phasesLabel = T.n_phases_plural5.replace('%d', phaseCount);
      } else {
        phasesLabel = (T.n_phases_plural || T.n_phases || '%d phases').replace('%d', phaseCount);
      }

      const card = document.createElement('article');
      card.className = 'project-card';
      card.dataset.id = p.id;
      card.innerHTML = `
        <div class="project-card__header">
          <h2 class="project-card__name">${escHtml(p.name)}</h2>
          <div class="project-card__actions">
            <button class="btn btn-icon btn-ghost" title="${escHtml(T.tooltip_edit_project)}" onclick="openEditProjectModal(${p.id})">
              <svg><use href="#icon-pencil"/></svg>
            </button>
            <button class="btn btn-icon btn-danger" title="${escHtml(T.tooltip_delete_project)}" onclick="confirmDeleteProject(${p.id}, '${escHtml(p.name).replace(/'/g, "\\'")}')">
              <svg><use href="#icon-trash"/></svg>
            </button>
          </div>
        </div>
        <p class="project-card__desc">${escHtml(p.description || T.no_description)}</p>
        <div class="project-card__footer">
          <span class="project-card__meta">${phasesLabel}</span>
          <a class="btn btn-ghost" href="/project/${p.id}">${escHtml(T.open_arrow)}</a>
        </div>`;
      grid.appendChild(card);
    });
  }

  // ── Project Modal ────────────────────────────────────────────
  function openNewProjectModal() {
    _editingProjectId = null;
    document.getElementById('projectModalTitle').textContent = T.new_project;
    document.getElementById('projectModalSubmit').textContent = T.create;
    document.getElementById('pm_name').value = '';
    document.getElementById('pm_desc').value = '';
    document.getElementById('projectModal').classList.add('is-open');
    setTimeout(() => document.getElementById('pm_name').focus(), 50);
  }

  function openEditProjectModal(id) {
    const p = state.projects.find(x => x.id === id);
    if (!p) return;
    _editingProjectId = id;
    document.getElementById('projectModalTitle').textContent = T.modal_edit_project;
    document.getElementById('projectModalSubmit').textContent = T.save;
    document.getElementById('pm_name').value = p.name;
    document.getElementById('pm_desc').value = p.description || '';
    document.getElementById('projectModal').classList.add('is-open');
    setTimeout(() => document.getElementById('pm_name').focus(), 50);
  }

  function closeProjectModal() {
    document.getElementById('projectModal').classList.remove('is-open');
    _editingProjectId = null;
  }

  async function submitProjectModal() {
    const name = document.getElementById('pm_name').value.trim();
    const description = document.getElementById('pm_desc').value.trim();
    if (!name) {
      document.getElementById('pm_name').focus();
      return;
    }
    const btn = document.getElementById('projectModalSubmit');
    btn.disabled = true;

    try {
      let resp;
      if (_editingProjectId) {
        resp = await api.updateProject(_editingProjectId, { name, description });
      } else {
        resp = await api.createProject({ name, description });
      }
      if (resp.ok) {
        closeProjectModal();
        toast.success(_editingProjectId ? T.toast_project_updated : T.toast_project_created);
        await refresh();
      } else {
        toast.error(T.toast_something_wrong);
      }
    } finally {
      btn.disabled = false;
    }
  }

  // ── Confirm ──────────────────────────────────────────────────
  let _confirmCallback = null;

  function showConfirm(message, onConfirm, title) {
    title = title || T.confirm_deletion;
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    _confirmCallback = onConfirm;
    document.getElementById('confirmModal').classList.add('is-open');
  }

  function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('is-open');
    _confirmCallback = null;
  }

  document.getElementById('confirmOkBtn').addEventListener('click', () => {
    if (_confirmCallback) _confirmCallback();
    closeConfirm();
  });

  function confirmDeleteProject(id, name) {
    showConfirm(
      T.confirm_delete_project_index.replace('%s', name),
      async () => {
        const resp = await api.deleteProject(id);
        if (resp.ok) { toast.success(T.toast_project_deleted); await refresh(); }
        else toast.error(T.toast_project_delete_failed);
      }
    );
  }

  // ── Toast ────────────────────────────────────────────────────
  const toast = {
    _show(message, type) {
      const el = document.createElement('div');
      el.className = `toast toast--${type}`;
      el.innerHTML = `<span class="toast__body">${escHtml(message)}</span>
        <button class="toast__close" onclick="this.closest('.toast').remove()" aria-label="Dismiss">✕</button>`;
      document.getElementById('toastContainer').appendChild(el);
      setTimeout(() => {
        el.classList.add('is-dismissing');
        el.addEventListener('animationend', () => el.remove(), { once: true });
      }, 4000);
    },
    success: (msg) => toast._show(msg, 'success'),
    error:   (msg) => toast._show(msg, 'error'),
    info:    (msg) => toast._show(msg, 'info'),
  };

  // ── Utilities ────────────────────────────────────────────────
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Keyboard shortcuts ───────────────────────────────────────
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeProjectModal(); closeConfirm(); }
  });
  document.getElementById('projectModal').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitProjectModal(); }
  });
  document.getElementById('projectModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeProjectModal();
  });
  document.getElementById('confirmModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeConfirm();
  });

  // ── Init ─────────────────────────────────────────────────────
  async function refresh() {
    state.projects = await api.getProjects();
    renderProjects();
  }

  refresh();
</script>
</body>
</html>
