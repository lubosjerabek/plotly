<?php $page_title = t('page_title_project');
require __DIR__ . '/partials/head.php'; ?>
  <link rel="stylesheet" href="/assets/base.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/base.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/frappe-gantt/0.6.1/frappe-gantt.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/frappe-gantt/0.6.1/frappe-gantt.min.js"></script>
  <style>
    /* ── Topbar extras (project-specific) ── */
    .topbar { gap: 1rem; }
    .topbar__left, .topbar__right {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-shrink: 0;
    }
    .topbar__center {
      flex: 1;
      text-align: center;
      font-size: 14px;
      font-weight: 600;
      color: var(--text-muted);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .back-link {
      color: var(--text-muted);
      text-decoration: none;
      font-size: 13px;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 0.3em;
      padding: 0.35rem 0.6rem;
      border-radius: var(--radius-sm);
      transition: color var(--t-fast), background var(--t-fast);
    }
    .back-link:hover { color: var(--text); background: var(--surface-2); }

    /* ── Language dropdown ── */
    <?php require __DIR__ . '/partials/lang_dropdown.css.php' ?>

    /* ── Page layout ── */
    .page {
      max-width: 900px;
      margin: 0 auto;
      padding: 2rem 2rem 4rem;
    }

    /* ── Project Header ── */
    .project-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 1rem;
      margin-bottom: 2rem;
    }
    .project-header h1 {
      font-size: 1.75rem;
      font-weight: 700;
      margin: 0 0 0.25rem;
      letter-spacing: -0.03em;
    }
    .project-header__desc {
      color: var(--text-muted);
      margin: 0;
      font-size: 14px;
    }

    /* ── Tabs ── */
    .tabs {
      display: flex;
      gap: 0;
      border-bottom: 1px solid var(--border);
      margin-bottom: 1.75rem;
    }
    .tab-btn {
      padding: 0.75rem 1.25rem;
      background: none; border: none;
      color: var(--text-muted);
      font-family: inherit; font-size: 14px; font-weight: 500;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      margin-bottom: -1px;
      transition: color var(--t-fast), border-color var(--t-fast);
    }
    .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
    .tab-btn:hover:not(.active) { color: var(--text); }

    /* ── Panel toolbar ── */
    .panel-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.25rem;
    }
    .panel-toolbar__label {
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-subtle);
    }

    /* ── Phase card ── */
    .phase-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: 1.125rem 1.25rem;
      margin-bottom: 0.875rem;
      transition: border-color var(--t-base);
    }
    .phase-card:hover { border-color: rgba(255,255,255,0.14); }
    .phase-card[data-status="active"]   { border-left: 3px solid var(--success); }
    .phase-card[data-status="upcoming"] { border-left: 3px solid var(--warning); }
    .phase-card[data-status="past"]     { border-left: 3px solid var(--status-past); opacity: 0.78; }

    .phase-card__header {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
    }
    .phase-card__color-dot {
      width: 10px; height: 10px;
      border-radius: 50%;
      margin-top: 5px;
      flex-shrink: 0;
      box-shadow: 0 0 6px var(--dot-color, transparent);
    }
    .phase-card__title-area { flex: 1; min-width: 0; }
    .phase-card__name {
      margin: 0 0 0.3rem;
      font-size: 15px;
      font-weight: 600;
    }
    .phase-card__meta {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.4rem;
    }
    .phase-card__dates {
      font-size: 12px;
      color: var(--text-subtle);
    }
    .phase-card__actions {
      display: flex;
      gap: 0.2rem;
      flex-shrink: 0;
    }

    /* ── Badges ── */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 0.15em 0.55em;
      border-radius: 999px;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }
    .badge-active   { background: rgba(34,197,94,0.15);  color: var(--success); }
    .badge-upcoming { background: rgba(245,158,11,0.15); color: var(--warning); }
    .badge-past     { background: rgba(100,116,139,0.15);color: var(--status-past); }
    .badge-dep      { background: var(--accent-muted);   color: var(--accent); }

    /* ── Phase collapse/expand ── */
    .phase-card__toggle-area {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      flex: 1;
      min-width: 0;
      cursor: pointer;
      user-select: none;
    }
    .phase-card__chevron {
      width: 16px; height: 16px;
      fill: var(--text-subtle);
      flex-shrink: 0;
      margin-top: 3px;
      transition: transform 0.2s ease;
    }
    .phase-card.is-collapsed .phase-card__chevron {
      transform: rotate(-90deg);
    }
    .phase-card__body {
      overflow: hidden;
      max-height: 1000px;
      margin-top: 1rem;
      transition: max-height 0.25s ease, margin-top 0.2s ease, opacity 0.2s ease;
      opacity: 1;
    }
    .phase-card.is-collapsed .phase-card__body {
      max-height: 0;
      margin-top: 0;
      opacity: 0;
      pointer-events: none;
    }

    /* ── Phase description ── */
    .phase-description {
      font-size: 13px;
      color: var(--text-muted);
      margin: 0 0 1rem;
      line-height: 1.55;
    }

    /* ── Phase sections (milestones / events) ── */
    .phase-section {
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid var(--border);
    }
    .phase-section__header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.5rem;
    }
    .phase-section__label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-subtle);
    }
    .item-list {
      list-style: none;
      padding: 0; margin: 0;
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }
    .item-list li {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: var(--surface-2);
      border-radius: var(--radius-sm);
      padding: 0.45rem 0.75rem;
      font-size: 13px;
      gap: 0.5rem;
    }
    .item-list__name { flex: 1; font-weight: 500; }
    .item-list__meta { color: var(--text-subtle); font-size: 11px; white-space: nowrap; }
    .item-empty {
      font-size: 12px;
      color: var(--text-subtle);
      padding: 0.35rem 0;
      font-style: italic;
    }

    /* ── Gantt overrides ── */
    #tab-timeline {
      overflow: visible;
      width: 100vw;
      position: relative;
      left: 50%;
      transform: translateX(-50%);
      padding: 0 2rem;
      box-sizing: border-box;
    }
    .gantt-toolbar {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }
    .gantt-toolbar span { font-size: 12px; color: var(--text-muted); }
    .gantt-view-btns { display: flex; gap: 0.25rem; }
    .gantt-view-btns button {
      padding: 0.3rem 0.7rem;
      font-size: 12px; font-weight: 500;
      font-family: inherit;
      background: var(--surface-2); border: 1px solid var(--border);
      border-radius: var(--radius-sm); color: var(--text-muted);
      cursor: pointer; transition: all var(--t-fast);
    }
    .gantt-view-btns button.active,
    .gantt-view-btns button:hover { background: var(--accent-muted); border-color: var(--accent); color: var(--accent); }
    .gantt-container {
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      overflow-x: auto;
      background: var(--surface);
    }
    .gantt .grid-background { fill: var(--surface) !important; }
    .gantt .grid-header { fill: var(--surface-2) !important; }
    .gantt .grid-row { fill: transparent !important; }
    .gantt .grid-row:nth-child(even) { fill: rgba(255,255,255,0.015) !important; }
    .gantt .tick { stroke: var(--border) !important; }
    .gantt .lower-text, .gantt .upper-text { fill: var(--text-muted) !important; }
    .gantt .bar { fill: var(--accent) !important; }
    .gantt .bar-progress { fill: var(--accent-hover) !important; }
    .gantt .bar-label { fill: #fff !important; font-size: 11px !important; }
    .gantt .arrow { stroke: var(--text-subtle) !important; }
    .gantt .today-highlight { fill: rgba(99,102,241,0.06) !important; }
    .gantt-today-line { stroke: var(--accent); stroke-width: 1.5px; stroke-dasharray: 4 3; opacity: 0.75; pointer-events: none; }
    .gantt-today-label { fill: var(--accent); font-size: 10px; font-weight: 600; font-family: 'Inter', system-ui, sans-serif; opacity: 0.85; pointer-events: none; }
    .gantt .gantt-milestone .bar { fill: #f59e0b !important; }
    .gantt .gantt-milestone .bar-progress { fill: #d97706 !important; }
    .gantt .gantt-event .bar     { fill: #10b981 !important; }
    .gantt .gantt-event .bar-progress { fill: #059669 !important; }

    /* ── Modal extras (project-specific) ── */
    .modal-checkbox-label {
      display: flex; align-items: center; gap: 0.6rem;
      font-size: 13px; color: var(--text-muted); cursor: pointer;
      padding: 0.4rem 0;
    }
    .modal-checkbox-label input[type="checkbox"] {
      width: 15px; height: 15px; accent-color: var(--accent); cursor: pointer; flex-shrink: 0;
    }
    .modal-field textarea {
      resize: vertical;
      min-height: 80px;
      line-height: 1.5;
    }
    .color-swatches {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
    .color-swatch {
      width: 28px; height: 28px;
      border-radius: 50%;
      cursor: pointer;
      border: 2px solid transparent;
      transition: transform 0.1s, border-color 0.1s;
      flex-shrink: 0;
    }
    .color-swatch:hover { transform: scale(1.15); }
    .color-swatch.is-selected {
      border-color: #fff;
      box-shadow: 0 0 0 2px rgba(255,255,255,0.4);
      transform: scale(1.15);
    }
    .gantt-today-label-bg { fill: var(--surface-3); }

    /* ── Subscribe modal ── */
    .subscribe-url-row {
      display: flex;
      gap: 0.5rem;
      align-items: center;
    }
    .subscribe-url-row input[type="text"] {
      flex: 1;
      background: var(--surface-3);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      color: var(--text-muted);
      font-family: 'SF Mono', 'Fira Code', monospace;
      font-size: 12px;
      padding: 0.5rem 0.75rem;
    }
    .subscribe-instructions {
      font-size: 12px;
      color: var(--text-muted);
      margin: 0.75rem 0 0;
      line-height: 1.6;
    }

    @media (max-width: 640px) {
      .topbar { padding: 0 0.75rem; gap: 0.4rem; }
      .topbar__center { display: none; }
      .topbar__left, .topbar__right { gap: 0.3rem; }
      .page { padding: 1.5rem 1rem 3rem; }
      .project-header { flex-direction: column; }
      /* Shrink text buttons to icon-only on small screens */
      #subscribeBtn .btn-label,
      #deleteProjectBtn .btn-label { display: none; }
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
  <symbol id="icon-link" viewBox="0 0 16 16">
    <path d="M7.775 3.275a.75.75 0 0 0 1.06 1.06l1.25-1.25a2 2 0 1 1 2.83 2.83l-2.5 2.5a2 2 0 0 1-2.83 0 .75.75 0 0 0-1.06 1.06 3.5 3.5 0 0 0 4.95 0l2.5-2.5a3.5 3.5 0 0 0-4.95-4.95l-1.25 1.25zm-4.69 9.64a2 2 0 0 1 0-2.83l2.5-2.5a2 2 0 0 1 2.83 0 .75.75 0 0 0 1.06-1.06 3.5 3.5 0 0 0-4.95 0l-2.5 2.5a3.5 3.5 0 0 0 4.95 4.95l1.25-1.25a.75.75 0 0 0-1.06-1.06l-1.25 1.25a2 2 0 0 1-2.83 0z"/>
  </symbol>
  <symbol id="icon-calendar" viewBox="0 0 16 16">
    <path d="M4.75 0a.75.75 0 0 1 .75.75V2h5V.75a.75.75 0 0 1 1.5 0V2h1.25c.966 0 1.75.784 1.75 1.75v10.5A1.75 1.75 0 0 1 13.25 16H2.75A1.75 1.75 0 0 1 1 14.25V3.75C1 2.784 1.784 2 2.75 2H4V.75A.75.75 0 0 1 4.75 0zM2.5 7.5v6.75c0 .138.112.25.25.25h10.5a.25.25 0 0 0 .25-.25V7.5H2.5zm10.75-4H2.75a.25.25 0 0 0-.25.25V6h11V3.75a.25.25 0 0 0-.25-.25H13.25z"/>
  </symbol>
  <symbol id="icon-plus" viewBox="0 0 16 16">
    <path d="M7.75 2a.75.75 0 0 1 .75.75V7h4.25a.75.75 0 0 1 0 1.5H8.5v4.25a.75.75 0 0 1-1.5 0V8.5H2.75a.75.75 0 0 1 0-1.5H7V2.75A.75.75 0 0 1 7.75 2z"/>
  </symbol>
  <symbol id="icon-chevron-down" viewBox="0 0 16 16">
    <path d="M4.427 7.427l3.396 3.396a.25.25 0 0 0 .354 0l3.396-3.396A.25.25 0 0 0 11.396 7H4.604a.25.25 0 0 0-.177.427z"/>
  </symbol>
  <symbol id="icon-copy" viewBox="0 0 16 16">
    <path d="M0 6.75C0 5.784.784 5 1.75 5h1.5a.75.75 0 0 1 0 1.5h-1.5a.25.25 0 0 0-.25.25v7.5c0 .138.112.25.25.25h7.5a.25.25 0 0 0 .25-.25v-1.5a.75.75 0 0 1 1.5 0v1.5A1.75 1.75 0 0 1 9.25 16h-7.5A1.75 1.75 0 0 1 0 14.25v-7.5z"/><path d="M5 1.75C5 .784 5.784 0 6.75 0h7.5C15.216 0 16 .784 16 1.75v7.5A1.75 1.75 0 0 1 14.25 11h-7.5A1.75 1.75 0 0 1 5 9.25v-7.5zm1.75-.25a.25.25 0 0 0-.25.25v7.5c0 .138.112.25.25.25h7.5a.25.25 0 0 0 .25-.25v-7.5a.25.25 0 0 0-.25-.25h-7.5z"/>
  </symbol>
</svg>

<!-- Topbar -->
<nav class="topbar">
  <div class="topbar__left">
    <a class="back-link" href="/"><?= htmlspecialchars(t('dashboard')) ?></a>
  </div>
  <div class="topbar__center" id="topbarTitle"></div>
  <div class="topbar__right">
    <?php require __DIR__ . '/partials/lang_dropdown.html.php' ?>
    <button class="btn btn-ghost" id="subscribeBtn" onclick="openSubscribeModal()">
      <svg><use href="#icon-calendar"/></svg>
      <span class="btn-label"><?= htmlspecialchars(t('subscribe')) ?></span>
    </button>
    <?php if (is_project_owner($project_id)): ?>
    <button class="btn btn-danger-outline" id="deleteProjectBtn" onclick="confirmDeleteProject()">
      <svg><use href="#icon-trash"/></svg>
      <span class="btn-label"><?= htmlspecialchars(t('delete')) ?></span>
    </button>
    <?php endif; ?>
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

<!-- Page -->
<main class="page">
  <!-- Project header -->
  <div class="project-header">
    <div>
      <h1 id="pName">Loading…</h1>
      <p class="project-header__desc" id="pDesc"></p>
    </div>
    <?php if (can_write_project($project_id)): ?>
    <button class="btn btn-ghost" onclick="editProject()">
      <svg><use href="#icon-pencil"/></svg>
        <?= htmlspecialchars(t('edit')) ?>
    </button>
    <?php endif; ?>
  </div>

  <!-- Tabs -->
  <div class="tabs" role="tablist">
    <button class="tab-btn active" data-tab="phases" role="tab" aria-selected="true" onclick="switchTab('phases')"><?= htmlspecialchars(t('phases_tab')) ?></button>
    <button class="tab-btn" data-tab="timeline" role="tab" aria-selected="false" onclick="switchTab('timeline')"><?= htmlspecialchars(t('timeline_tab')) ?></button>
    <button class="tab-btn" data-tab="collaborators" role="tab" aria-selected="false" onclick="switchTab('collaborators')"><?= htmlspecialchars(t('collaborators')) ?></button>
  </div>

  <!-- Phases tab -->
  <div id="tab-phases" role="tabpanel">
    <div class="panel-toolbar">
      <span class="panel-toolbar__label" id="phaseCount"></span>
      <?php if (can_write_project($project_id)): ?>
      <button class="btn btn-primary" onclick="addPhase()">
        <svg><use href="#icon-plus"/></svg>
            <?= htmlspecialchars(t('add_phase')) ?>
      </button>
      <?php endif; ?>
    </div>
    <!-- Project-wide milestones & events (not tied to any phase) -->
    <div id="projectItemsCard" class="phase-card" style="border-left:3px solid var(--accent);margin-bottom:1.25rem;">
      <div class="phase-card__header">
        <div style="display:flex;align-items:center;gap:0.6rem;flex:1;">
          <div class="phase-card__color-dot" style="background:var(--accent);--dot-color:var(--accent)"></div>
          <div class="phase-card__title-area">
            <h3 class="phase-card__name"><?= htmlspecialchars(t('project_wide')) ?></h3>
          </div>
        </div>
        <?php if (can_write_project($project_id)): ?>
        <div class="phase-card__actions">
          <button class="btn btn-ghost btn-xs" onclick="addProjectMilestone()">+ <?= htmlspecialchars(t('milestones')) ?></button>
          <button class="btn btn-ghost btn-xs" onclick="addProjectEvent()">+ <?= htmlspecialchars(t('events')) ?></button>
        </div>
        <?php endif; ?>
      </div>
      <div id="projectItemsBody" class="phase-card__body" style="max-height:none;opacity:1;"></div>
    </div>

    <div id="phasesList"></div>
  </div>

  <!-- Timeline tab -->
  <div id="tab-timeline" role="tabpanel" style="display:none">
    <div class="gantt-toolbar">
      <span><?= htmlspecialchars(t('gantt_view_label')) ?></span>
      <div class="gantt-view-btns" id="ganttViewBtns">
        <button data-view="Day"   onclick="setGanttView('Day')"><?= htmlspecialchars(t('gantt_day')) ?></button>
        <button data-view="Week"  onclick="setGanttView('Week')"><?= htmlspecialchars(t('gantt_week')) ?></button>
        <button class="active" data-view="Month" onclick="setGanttView('Month')"><?= htmlspecialchars(t('gantt_month')) ?></button>
      </div>
    </div>
    <div class="gantt-container">
      <svg id="gantt"></svg>
    </div>
  </div>

  <!-- Collaborators tab -->
  <div id="tab-collaborators" role="tabpanel" style="display:none">
    <div class="panel-toolbar">
      <span class="panel-toolbar__label"><?= htmlspecialchars(t('collaborators')) ?></span>
      <button class="btn btn-primary" id="addCollaboratorBtn" onclick="openAddCollaboratorModal()" style="display:none">
        <svg><use href="#icon-plus"/></svg>
        <?= htmlspecialchars(t('add_collaborator')) ?>
      </button>
    </div>
    <div id="collaboratorsList"></div>
  </div>
</main>

<!-- Generic Modal -->
<div class="modal-overlay" id="genericModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
  <div class="modal">
    <div class="modal__header">
      <h2 class="modal__title" id="modalTitle"></h2>
      <button class="modal__close" onclick="closeModal()" aria-label="Close">✕</button>
    </div>
    <div class="modal__body" id="modalFields"></div>
    <div class="modal__footer">
      <button class="btn btn-ghost" onclick="closeModal()"><?= htmlspecialchars(t('cancel')) ?></button>
      <button class="btn btn-primary" id="modalSubmitBtn"><?= htmlspecialchars(t('submit')) ?></button>
    </div>
  </div>
</div>

<!-- Subscribe / ICS Modal -->
<div class="modal-overlay" id="subscribeModal" role="dialog" aria-modal="true" aria-labelledby="subscribeTitle">
  <div class="modal">
    <div class="modal__header">
      <h2 class="modal__title" id="subscribeTitle"><?= htmlspecialchars(t('subscribe_title')) ?></h2>
      <button class="modal__close" onclick="closeSubscribeModal()" aria-label="<?= htmlspecialchars(t('close')) ?>">✕</button>
    </div>
    <div class="modal__body">
      <label class="field-label"><?= htmlspecialchars(t('ics_feed_label')) ?></label>
      <div class="subscribe-url-row">
        <input type="text" id="icsUrl" readonly>
        <button class="btn btn-ghost btn-xs" onclick="copyIcsUrl()" title="<?= htmlspecialchars(t('tooltip_copy_url')) ?>">
          <svg><use href="#icon-copy"/></svg>
          <?= htmlspecialchars(t('copy')) ?>
        </button>
      </div>
      <p class="subscribe-instructions">
        <?= t('subscribe_instructions') ?>
      </p>
    </div>
    <div class="modal__footer">
      <button class="btn btn-ghost" onclick="closeSubscribeModal()"><?= htmlspecialchars(t('close')) ?></button>
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
      <button class="btn btn-danger-outline" id="confirmOkBtn"><?= htmlspecialchars(t('delete')) ?></button>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer" aria-live="polite"></div>

<script>
  const T = <?= t_js() ?>;
  const projectId = <?= (int)$project_id ?>;
  const canEdit = <?= can_write_project($project_id) ? 'true' : 'false' ?>;
  const isOwner = <?= is_project_owner($project_id) ? 'true' : 'false' ?>;
  const icsToken = <?= json_encode(current_user_ics_token()) ?>;
</script>
<script src="/assets/project.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/project.js') ?>"></script>
</body>
</html>
