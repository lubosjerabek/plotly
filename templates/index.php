<?php $page_title = t('page_title_projects');
require __DIR__ . '/partials/head.php'; ?>
  <link rel="stylesheet" href="/assets/base.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/base.css') ?>">
  <style>
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

    /* ── Language dropdown ── */
    <?php require __DIR__ . '/partials/lang_dropdown.css.php' ?>

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

    /* ── Calendar sync modal ── */
    .cal-url-row {
      display: flex;
      gap: 0.5rem;
      align-items: center;
    }
    .cal-url-input {
      flex: 1;
      padding: 0.6rem 0.75rem;
      background: var(--surface-3);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      color: var(--text-muted);
      font-family: 'Courier New', monospace;
      font-size: 12px;
      word-break: break-all;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      cursor: default;
      user-select: all;
    }
    .cal-instructions {
      font-size: 12px;
      color: var(--text-muted);
      line-height: 1.6;
      margin: 0;
    }
    .cal-rotate-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 0.75rem;
      border-top: 1px solid var(--border);
      margin-top: 0.25rem;
    }
    .cal-rotate-label {
      font-size: 12px;
      color: var(--text-subtle);
    }

    @media (max-width: 600px) {
      .page { padding: 1.5rem 1rem; }
      .topbar { padding: 0 0.75rem; gap: 0.5rem; }
      .topbar__brand { font-size: 14px; }
      .topbar__right { gap: 0.35rem; }
      .topbar__right .btn-ghost:not(.btn-icon) { font-size: 11px; padding: 0.35rem 0.6rem; }
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
  <symbol id="icon-calendar" viewBox="0 0 16 16">
    <path d="M4.75 0a.75.75 0 0 1 .75.75V2h5V.75a.75.75 0 0 1 1.5 0V2h1.25c.966 0 1.75.784 1.75 1.75v10.5A1.75 1.75 0 0 1 13.25 16H2.75A1.75 1.75 0 0 1 1 14.25V3.75C1 2.784 1.784 2 2.75 2H4V.75A.75.75 0 0 1 4.75 0zm0 3.5h-2a.25.25 0 0 0-.25.25V6h11V3.75a.25.25 0 0 0-.25-.25h-2V5a.75.75 0 0 1-1.5 0V3.5h-5V5a.75.75 0 0 1-1.5 0V3.5zm-2.25 4v6.75c0 .138.112.25.25.25h10.5a.25.25 0 0 0 .25-.25V7.5H2.5z"/>
  </symbol>
  <symbol id="icon-refresh" viewBox="0 0 16 16">
    <path d="M1.705 8.005a.75.75 0 0 1 .834.656 5.5 5.5 0 0 0 9.592 2.97l-1.204-1.204a.25.25 0 0 1 .177-.427h3.646a.25.25 0 0 1 .25.25v3.646a.25.25 0 0 1-.427.177l-1.38-1.38A7.002 7.002 0 0 1 1.05 8.84a.75.75 0 0 1 .656-.834zM8 2.5a5.487 5.487 0 0 0-4.131 1.869l1.204 1.204A.25.25 0 0 1 4.896 6H1.25A.25.25 0 0 1 1 5.75V2.104a.25.25 0 0 1 .427-.177l1.38 1.38A7.002 7.002 0 0 1 14.95 7.16a.75.75 0 0 1-1.49.178A5.5 5.5 0 0 0 8 2.5z"/>
  </symbol>
</svg>

<nav class="topbar">
  <div class="topbar__brand">
    <div class="topbar__brand-dot"></div>
    <?= htmlspecialchars(t('app_name')) ?>
  </div>
  <div class="topbar__right">
    <?php require __DIR__ . '/partials/lang_dropdown.html.php' ?>
    <button class="btn btn-ghost btn-icon" title="<?= htmlspecialchars(t('cal_sync_button_title')) ?>" onclick="openCalSyncModal()">
      <svg><use href="#icon-calendar"/></svg>
    </button>
    <?php
      $u = current_user();
      $nm = $u['name'];
      $parts = explode(' ', $nm, 2);
      $initials = mb_strtoupper(mb_substr($parts[0], 0, 1))
                . (isset($parts[1]) ? mb_strtoupper(mb_substr($parts[1], 0, 1)) : '');
    ?>
    <div class="user-menu" id="userMenu">
      <button class="user-menu__trigger" onclick="toggleUserMenu(event)" aria-haspopup="true" aria-expanded="false">
        <span class="user-menu__initials"><?= htmlspecialchars($initials) ?></span>
        <svg class="user-menu__chevron" viewBox="0 0 16 16" aria-hidden="true">
          <path d="M4.427 7.427l3.396 3.396a.25.25 0 0 0 .354 0l3.396-3.396A.25.25 0 0 0 11.396 7H4.604a.25.25 0 0 0-.177.427z"/>
        </svg>
      </button>
      <div class="user-menu__dropdown" role="menu">
        <div class="user-menu__name"><?= htmlspecialchars($nm) ?></div>
        <?php if ($u['role'] === 'admin'): ?>
        <a class="user-menu__item" href="/admin/users" role="menuitem"><?= htmlspecialchars(t('admin')) ?></a>
        <?php endif; ?>
        <div class="user-menu__sep"></div>
        <a class="user-menu__item user-menu__item--danger" href="/logout" role="menuitem"><?= htmlspecialchars(t('sign_out')) ?></a>
      </div>
    </div>
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

<!-- Calendar Sync Modal -->
<div class="modal-overlay" id="calSyncModal" role="dialog" aria-modal="true" aria-labelledby="calSyncTitle">
  <div class="modal">
    <div class="modal__header">
      <h2 class="modal__title" id="calSyncTitle"><?= htmlspecialchars(t('cal_sync_title')) ?></h2>
      <button class="modal__close" onclick="closeCalSyncModal()" aria-label="<?= htmlspecialchars(t('close')) ?>">✕</button>
    </div>
    <div class="modal__body" style="display:flex;flex-direction:column;gap:1rem;">
      <div>
        <span class="field-label"><?= htmlspecialchars(t('cal_sync_all_projects_label')) ?></span>
        <div class="cal-url-row">
          <input class="cal-url-input" id="calSyncUrl" type="text" readonly value="">
          <button class="btn btn-ghost" onclick="copyCalUrl()" title="<?= htmlspecialchars(t('tooltip_copy_url')) ?>"><?= htmlspecialchars(t('copy')) ?></button>
        </div>
      </div>
      <p class="cal-instructions"><?= t('cal_sync_instructions') ?></p>
      <div class="cal-rotate-row">
        <span class="cal-rotate-label"><?= htmlspecialchars(t('cal_rotate_confirm')) ?></span>
        <button class="btn btn-danger" id="calRotateBtn" onclick="rotateCalToken()">
          <svg><use href="#icon-refresh"/></svg>
          <?= htmlspecialchars(t('cal_rotate_token')) ?>
        </button>
      </div>
    </div>
    <div class="modal__footer">
      <button class="btn btn-ghost" onclick="closeCalSyncModal()"><?= htmlspecialchars(t('close')) ?></button>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer" aria-live="polite"></div>

<script>
  const T = <?= t_js() ?>;
</script>
<script src="/assets/dashboard.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/dashboard.js') ?>"></script>
</body>
</html>
