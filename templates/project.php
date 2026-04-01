<?php $lang = current_lang(); ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars(t('page_title_project')) ?></title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/frappe-gantt/0.6.1/frappe-gantt.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/frappe-gantt/0.6.1/frappe-gantt.min.js"></script>
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
      --warning:     #f59e0b;
      --status-past: #64748b;
      --text:        #f1f5f9;
      --text-muted:  #94a3b8;
      --text-subtle: #64748b;
      --radius-sm:   6px;
      --radius-md:   10px;
      --radius-lg:   16px;
      --radius-xl:   24px;
      --shadow-md:   0 4px 20px rgba(0,0,0,0.5);
      --shadow-lg:   0 8px 32px rgba(0,0,0,0.6);
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
      background: rgba(15,15,19,0.88);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 2rem;
      height: 56px;
      gap: 1rem;
    }
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
    .btn-primary  { background: var(--accent); color: #fff; }
    .btn-primary:hover { background: var(--accent-hover); }
    .btn-ghost {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text-muted);
    }
    .btn-ghost:hover { border-color: var(--border-hover); color: var(--text); background: var(--surface-2); }
    .btn-danger-outline {
      background: transparent;
      border: 1px solid transparent;
      color: var(--danger);
    }
    .btn-danger-outline:hover { background: var(--danger-muted); border-color: var(--danger); }
    .btn-icon {
      padding: 0.35rem;
      width: 28px; height: 28px;
      justify-content: center;
    }
    .btn-xs { padding: 0.25rem 0.6rem; font-size: 12px; }
    .btn svg { width: 14px; height: 14px; fill: currentColor; flex-shrink: 0; }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }

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
    .gantt .today-highlight { fill: rgba(99,102,241,0.08) !important; }
    .gantt .gantt-milestone .bar { fill: #f59e0b !important; }
    .gantt .gantt-milestone .bar-progress { fill: #d97706 !important; }
    .gantt .gantt-event .bar     { fill: #10b981 !important; }
    .gantt .gantt-event .bar-progress { fill: #059669 !important; }

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
      font-size: 18px; line-height: 1; padding: 4px;
      border-radius: var(--radius-sm); transition: color var(--t-fast);
    }
    .modal__close:hover { color: var(--text); }
    .modal__body { padding: 1.25rem 1.5rem; }
    .modal__footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 0 1.5rem 1.25rem; }
    .modal-field { margin-bottom: 1rem; }
    .modal-field:last-child { margin-bottom: 0; }
    .field-label {
      display: block; margin-bottom: 0.4rem;
      font-size: 11px; font-weight: 600; color: var(--text-muted);
      text-transform: uppercase; letter-spacing: 0.06em;
    }
    .modal-field input, .modal-field select, .modal-field textarea {
      width: 100%; padding: 0.65rem 0.75rem;
      background: var(--surface-3); border: 1px solid var(--border);
      border-radius: var(--radius-sm); color: var(--text);
      font-family: inherit; font-size: 14px;
      transition: border-color var(--t-fast), box-shadow var(--t-fast);
    }
    .modal-field input:focus, .modal-field select:focus, .modal-field textarea:focus {
      outline: none; border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-muted);
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
    .confirm-message { color: var(--text-muted); margin: 0; line-height: 1.6; }

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

    @media (max-width: 640px) {
      .topbar { padding: 0 1rem; }
      .topbar__center { display: none; }
      .page { padding: 1.5rem 1rem 3rem; }
      .project-header { flex-direction: column; }
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

<?php
// inline lang-btn style (reuse project.php's existing CSS vars)
$_lbStyle = 'background:none;border:1px solid var(--border);border-radius:6px;color:var(--text-muted);font-family:inherit;font-size:11px;font-weight:600;letter-spacing:0.05em;padding:0.2rem 0.5rem;cursor:pointer;transition:all .15s;';
$_lbActive = 'border-color:var(--accent);color:var(--accent);background:rgba(99,102,241,0.1);';
?>
<!-- Topbar -->
<nav class="topbar">
  <div class="topbar__left">
    <a class="back-link" href="/"><?= htmlspecialchars(t('dashboard')) ?></a>
  </div>
  <div class="topbar__center" id="topbarTitle"></div>
  <div class="topbar__right">
    <form method="post" action="/set-lang" style="display:inline">
      <input type="hidden" name="lang" value="en">
      <button type="submit" style="<?= $_lbStyle . ($lang === 'en' ? $_lbActive : '') ?>"><?= t('lang_en') ?></button>
    </form>
    <form method="post" action="/set-lang" style="display:inline">
      <input type="hidden" name="lang" value="cs">
      <button type="submit" style="<?= $_lbStyle . ($lang === 'cs' ? $_lbActive : '') ?>"><?= t('lang_cs') ?></button>
    </form>
    <button class="btn btn-ghost" id="subscribeBtn" onclick="openSubscribeModal()">
      <svg><use href="#icon-calendar"/></svg>
      <?= htmlspecialchars(t('subscribe')) ?>
    </button>
    <?php if (is_project_owner($project_id)): ?>
    <button class="btn btn-danger-outline" id="deleteProjectBtn" onclick="confirmDeleteProject()">
      <svg><use href="#icon-trash"/></svg>
      <?= htmlspecialchars(t('delete')) ?>
    </button>
    <?php endif; ?>
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
  // ── Translations (PHP-injected) ──────────────────────────────
  const T = <?= t_js() ?>;

  // ── Constants & State ────────────────────────────────────────
  const projectId = <?= (int)$project_id ?>;
  const canEdit   = <?= can_write_project($project_id) ? 'true' : 'false' ?>;
  const isOwner   = <?= is_project_owner($project_id) ? 'true' : 'false' ?>;
  const icsToken  = <?= json_encode(current_user_ics_token()) ?>;
  const state = { project: null, activeTab: 'phases', ganttView: 'Month', ganttInstance: null };

  // ── API ──────────────────────────────────────────────────────
  const H = { 'Content-Type': 'application/json' };
  const api = {
    getProject:      (id)       => fetch(`/api/projects/${id}`).then(r => r.json()),
    updateProject:   (id, data) => fetch(`/api/projects/${id}`, { method: 'PUT', headers: H, body: JSON.stringify(data) }),
    deleteProject:   (id)       => fetch(`/api/projects/${id}`, { method: 'DELETE' }),
    createPhase:     (pid, data)=> fetch(`/api/phases?project_id=${pid}`, { method: 'POST', headers: H, body: JSON.stringify(data) }),
    updatePhase:     (id, data) => fetch(`/api/phases/${id}`, { method: 'PUT', headers: H, body: JSON.stringify(data) }),
    deletePhase:     (id)       => fetch(`/api/phases/${id}`, { method: 'DELETE' }),
    createMilestone:        (phid, d)  => fetch(`/api/phases/${phid}/milestones`, { method: 'POST', headers: H, body: JSON.stringify(d) }),
    deleteMilestone:        (id)       => fetch(`/api/milestones/${id}`, { method: 'DELETE' }),
    createEvent:            (phid, d)  => fetch(`/api/phases/${phid}/events`, { method: 'POST', headers: H, body: JSON.stringify(d) }),
    deleteEvent:            (id)       => fetch(`/api/events/${id}`, { method: 'DELETE' }),
    createProjectMilestone: (pid, d)   => fetch(`/api/projects/${pid}/milestones`, { method: 'POST', headers: H, body: JSON.stringify(d) }),
    createProjectEvent:     (pid, d)   => fetch(`/api/projects/${pid}/events`, { method: 'POST', headers: H, body: JSON.stringify(d) }),
    updateMilestone:        (id, d)    => fetch(`/api/milestones/${id}`, { method: 'PATCH', headers: H, body: JSON.stringify(d) }),
    updateEvent:            (id, d)    => fetch(`/api/events/${id}`,     { method: 'PATCH', headers: H, body: JSON.stringify(d) }),
    getCollaborators:       (pid)      => fetch(`/api/projects/${pid}/collaborators`).then(r => r.json()),
    addCollaborator:        (pid, d)   => fetch(`/api/projects/${pid}/collaborators`, { method: 'POST', headers: H, body: JSON.stringify(d) }),
    updateCollaborator:     (pid, uid, d) => fetch(`/api/projects/${pid}/collaborators/${uid}`, { method: 'PATCH', headers: H, body: JSON.stringify(d) }),
    removeCollaborator:     (pid, uid) => fetch(`/api/projects/${pid}/collaborators/${uid}`, { method: 'DELETE' }),
  };

  // ── Utilities ────────────────────────────────────────────────
  const todayStr = () => new Date().toISOString().split('T')[0];

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function fmtDate(d) {
    if (!d) return '';
    const [y, m, day] = d.split('-');
    const months = T.months;
    return `${months[parseInt(m,10)-1]} ${parseInt(day,10)}, ${y}`;
  }

  function dateToYMD(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  }

  function parseDateLocal(str) {
    const [y, m, d] = str.split('-').map(Number);
    return new Date(y, m - 1, d);
  }

  function shiftDateStr(str, days) {
    const d = parseDateLocal(str);
    d.setDate(d.getDate() + days);
    return dateToYMD(d);
  }

  // Recursively collect all phases that cascade from a moved phase
  function collectPhaseDependents(rootId, delta, phases) {
    const results = [];
    const visit = id => phases.forEach(p => {
      if (p.depends_on_id === id) {
        results.push({ name: p.name, newStart: shiftDateStr(p.start_date, delta), newEnd: shiftDateStr(p.end_date, delta) });
        visit(p.id);
      }
    });
    visit(rootId);
    return results;
  }

  function buildImpactHTML(movedLabel, delta, dependents) {
    const sign   = delta > 0 ? `+${delta}` : `${delta}`;
    const dUnit  = Math.abs(delta) === 1 ? T.impact_day : T.impact_days;
    let html = `<strong>${escHtml(movedLabel)}</strong> ${T.impact_shifts} <strong>${sign} ${dUnit}</strong>.`;
    if (dependents.length) {
      const depLabel = dependents.length > 1 ? T.impact_dependents : T.impact_dependent;
      html += `<br><br><span style="font-size:12px;color:var(--text-muted)">${T.impact_also_shifts} ${dependents.length} ${depLabel}:</span>`;
      html += `<ul style="margin:.5rem 0 0;padding-left:1.25rem;font-size:13px;color:var(--text-muted);line-height:1.8">`;
      dependents.forEach(d => { html += `<li>${escHtml(d.name)} → ${fmtDate(d.newStart)}</li>`; });
      html += '</ul>';
    }
    return html;
  }

  function getPhaseStatus(start, end) {
    const today = new Date(); today.setHours(0,0,0,0);
    const s = new Date(start), e = new Date(end);
    if (e < today) return 'past';
    if (s > today) return 'upcoming';
    return 'active';
  }

  function statusBadge(status) {
    const labels = { past: T.status_past, active: T.status_active, upcoming: T.status_upcoming };
    return `<span class="badge badge-${status}">${labels[status]}</span>`;
  }

  // ── Render ───────────────────────────────────────────────────
  function renderProject(p) {
    document.title = `${p.name} — Plotly`;
    document.getElementById('pName').textContent = p.name;
    document.getElementById('pDesc').textContent = p.description || T.no_description_provided;
    document.getElementById('topbarTitle').textContent = p.name;
    const pc = p.phases.length;
    let phasesLabel;
    if (pc === 1) {
      phasesLabel = T.n_phases.replace('%d', pc);
    } else if (pc >= 5 && T.n_phases_plural5) {
      phasesLabel = T.n_phases_plural5.replace('%d', pc);
    } else {
      phasesLabel = (T.n_phases_plural || T.n_phases).replace('%d', pc);
    }
    document.getElementById('phaseCount').textContent = phasesLabel;
    renderProjectItems(p.milestones || [], p.events || []);
    renderPhases(p.phases);
    if (state.activeTab === 'timeline') renderGantt(p);
  }

  function renderProjectItems(milestones, events) {
    const card = document.getElementById('projectItemsCard');
    const container = document.getElementById('projectItemsBody');
    if (!card || !container) return;

    const msItems = milestones.length > 0
      ? milestones.map(m => `
          <li>
            <span class="item-list__name">${escHtml(m.name)}</span>
            <span class="item-list__meta">${fmtDate(m.target_date)}</span>
            ${canEdit ? `
            <button class="btn btn-icon btn-ghost" title="Edit milestone date" style="width:22px;height:22px;padding:2px;"
              onclick="editMilestone(${m.id}, '${escHtml(m.name).replace(/'/g,"\\'")}', '${m.target_date}')">
              <svg><use href="#icon-pencil"/></svg>
            </button>
            <button class="btn btn-icon btn-danger-outline" title="Delete milestone" style="width:22px;height:22px;padding:2px;"
              onclick="confirmDeleteMilestone(${m.id}, '${escHtml(m.name).replace(/'/g,"\\'")}')">
              <svg><use href="#icon-trash"/></svg>
            </button>` : ''}
          </li>`).join('')
      : `<li class="item-empty" style="background:none;padding:0.25rem 0;">${T.none}</li>`;

    const evItems = events.length > 0
      ? events.map(e => `
          <li>
            <span class="item-list__name">${escHtml(e.name)}</span>
            <span class="item-list__meta">${fmtDate(e.start_date)} → ${fmtDate(e.end_date)}</span>
            ${canEdit ? `
            <button class="btn btn-icon btn-danger-outline" title="Delete event" style="width:22px;height:22px;padding:2px;"
              onclick="confirmDeleteEvent(${e.id}, '${escHtml(e.name).replace(/'/g,"\\'")}')">
              <svg><use href="#icon-trash"/></svg>
            </button>` : ''}
          </li>`).join('')
      : `<li class="item-empty" style="background:none;padding:0.25rem 0;">${T.none}</li>`;

    container.innerHTML = `
      <div class="phase-section">
        <div class="phase-section__header">
          <span class="phase-section__label">${T.milestones}</span>
          ${canEdit ? `<button class="btn btn-ghost btn-xs" onclick="addProjectMilestone()">${T.add}</button>` : ''}
        </div>
        <ul class="item-list">${msItems}</ul>
      </div>
      <div class="phase-section">
        <div class="phase-section__header">
          <span class="phase-section__label">${T.events}</span>
          ${canEdit ? `<button class="btn btn-ghost btn-xs" onclick="addProjectEvent()">${T.add}</button>` : ''}
        </div>
        <ul class="item-list">${evItems}</ul>
      </div>`;
  }

  function renderPhases(phases) {
    const list = document.getElementById('phasesList');

    // Preserve collapse state across re-renders
    const wasCollapsed = new Set();
    const wasExpanded  = new Set();
    list.querySelectorAll('.phase-card[data-phase-id]').forEach(c => {
      const pid = parseInt(c.dataset.phaseId);
      (c.classList.contains('is-collapsed') ? wasCollapsed : wasExpanded).add(pid);
    });
    const hadState = wasCollapsed.size + wasExpanded.size > 0;

    list.innerHTML = '';
    if (phases.length === 0) {
      list.innerHTML = `<div class="item-empty" style="text-align:center;padding:2rem;">
        ${T.no_phases}
      </div>`;
      return;
    }

    const phaseMap = {};
    phases.forEach(p => { phaseMap[p.id] = p.name; });

    // Build milestone map for dependency display
    const msMap = {};
    (state.project.milestones || []).forEach(m => { msMap[m.id] = m.name; });
    phases.forEach(p => (p.milestones || []).forEach(m => { msMap[m.id] = m.name; }));

    phases.forEach(phase => {
      const status = getPhaseStatus(phase.start_date, phase.end_date);
      const color = phase.color || '#6366f1';
      const depName = phase.depends_on_id ? phaseMap[phase.depends_on_id] : null;
      const depMsName = phase.depends_on_milestone_id ? msMap[phase.depends_on_milestone_id] : null;
      const collapsed = hadState
        ? !wasExpanded.has(phase.id)  // preserve: expanded stays expanded, everything else stays collapsed
        : status !== 'active';        // first render: default by status

      const card = document.createElement('div');
      card.className = 'phase-card' + (collapsed ? ' is-collapsed' : '');
      card.dataset.phaseId = phase.id;
      card.dataset.status = status;

      const msItems = phase.milestones.length > 0
        ? phase.milestones.map(m => `
            <li>
              <span class="item-list__name">${escHtml(m.name)}</span>
              <span class="item-list__meta">${fmtDate(m.target_date)}</span>
              ${canEdit ? `
              <button class="btn btn-icon btn-ghost" title="Edit milestone date" style="width:22px;height:22px;padding:2px;"
                onclick="editMilestone(${m.id}, '${escHtml(m.name).replace(/'/g,"\\'")}', '${m.target_date}')">
                <svg><use href="#icon-pencil"/></svg>
              </button>
              <button class="btn btn-icon btn-danger-outline" title="Delete milestone" style="width:22px;height:22px;padding:2px;"
                onclick="confirmDeleteMilestone(${m.id}, '${escHtml(m.name).replace(/'/g,"\\'")}')">
                <svg><use href="#icon-trash"/></svg>
              </button>` : ''}
            </li>`).join('')
        : `<li class="item-empty" style="background:none;padding:0.25rem 0;">${T.none}</li>`;

      const evItems = phase.events.length > 0
        ? phase.events.map(e => `
            <li>
              <span class="item-list__name">${escHtml(e.name)}</span>
              <span class="item-list__meta">${fmtDate(e.start_date)} → ${fmtDate(e.end_date)}</span>
              ${canEdit ? `
              <button class="btn btn-icon btn-danger-outline" title="Delete event" style="width:22px;height:22px;padding:2px;"
                onclick="confirmDeleteEvent(${e.id}, '${escHtml(e.name).replace(/'/g,"\\'")}')">
                <svg><use href="#icon-trash"/></svg>
              </button>` : ''}
            </li>`).join('')
        : `<li class="item-empty" style="background:none;padding:0.25rem 0;">${T.none}</li>`;

      card.innerHTML = `
        <div class="phase-card__header">
          <div class="phase-card__toggle-area" onclick="togglePhase(${phase.id})" title="${collapsed ? T.expand_phase : T.collapse_phase}">
            <svg class="phase-card__chevron"><use href="#icon-chevron-down"/></svg>
            <div class="phase-card__color-dot" style="background:${color};--dot-color:${color}"></div>
            <div class="phase-card__title-area">
              <h3 class="phase-card__name">${escHtml(phase.name)}</h3>
              <div class="phase-card__meta">
                ${statusBadge(status)}
                <span class="phase-card__dates">${fmtDate(phase.start_date)} → ${fmtDate(phase.end_date)}</span>
                ${depName ? `<span class="badge badge-dep">↳ ${T.after_prefix} ${escHtml(depName)}</span>` : ''}
                ${depMsName ? `<span class="badge badge-dep">◆ ${T.after_prefix} ${escHtml(depMsName)}</span>` : ''}
              </div>
            </div>
          </div>
          ${canEdit ? `
          <div class="phase-card__actions">
            <button class="btn btn-icon btn-ghost" title="${T.tooltip_edit_phase}" onclick="editPhase(${phase.id})">
              <svg><use href="#icon-pencil"/></svg>
            </button>
            <button class="btn btn-icon btn-ghost" title="${T.tooltip_set_dependency}" onclick="setDependency(${phase.id})">
              <svg><use href="#icon-link"/></svg>
            </button>
            <button class="btn btn-icon btn-danger-outline" title="${T.tooltip_delete_phase}" onclick="confirmDeletePhase(${phase.id}, '${escHtml(phase.name).replace(/'/g,"\\'")}')">
              <svg><use href="#icon-trash"/></svg>
            </button>
          </div>` : ''}
        </div>

        <div class="phase-card__body">
          ${phase.description ? `<p class="phase-description">${escHtml(phase.description)}</p>` : ''}
          <div class="phase-section">
            <div class="phase-section__header">
              <span class="phase-section__label">${T.milestones}</span>
              ${canEdit ? `<button class="btn btn-ghost btn-xs" onclick="addMilestone(${phase.id})">${T.add}</button>` : ''}
            </div>
            <ul class="item-list" id="ms-list-${phase.id}">${msItems}</ul>
          </div>

          <div class="phase-section">
            <div class="phase-section__header">
              <span class="phase-section__label">${T.events}</span>
              ${canEdit ? `<button class="btn btn-ghost btn-xs" onclick="addEvent(${phase.id})">${T.add}</button>` : ''}
            </div>
            <ul class="item-list" id="ev-list-${phase.id}">${evItems}</ul>
          </div>
        </div>`;

      list.appendChild(card);
    });
  }

  // ── Collaborators ────────────────────────────────────────────
  async function renderCollaborators() {
    const list = document.getElementById('collaboratorsList');
    const addBtn = document.getElementById('addCollaboratorBtn');
    if (!list) return;
    if (addBtn) addBtn.style.display = isOwner ? '' : 'none';

    const collaborators = await api.getCollaborators(projectId);
    if (!collaborators.length) {
      list.innerHTML = `<div class="item-empty" style="text-align:center;padding:2rem;color:var(--text-subtle)">${T.no_collaborators}</div>`;
      return;
    }
    list.innerHTML = `<table style="width:100%;border-collapse:collapse">
      <thead><tr>
        <th style="font-size:11px;font-weight:600;color:var(--text-subtle);text-transform:uppercase;letter-spacing:.06em;padding:.5rem .75rem;text-align:left;border-bottom:1px solid var(--border)">${T.user_name}</th>
        <th style="font-size:11px;font-weight:600;color:var(--text-subtle);text-transform:uppercase;letter-spacing:.06em;padding:.5rem .75rem;text-align:left;border-bottom:1px solid var(--border)">${T.user_email}</th>
        <th style="font-size:11px;font-weight:600;color:var(--text-subtle);text-transform:uppercase;letter-spacing:.06em;padding:.5rem .75rem;text-align:left;border-bottom:1px solid var(--border)">${T.collaborator_role}</th>
        ${isOwner ? `<th style="font-size:11px;font-weight:600;color:var(--text-subtle);text-transform:uppercase;letter-spacing:.06em;padding:.5rem .75rem;text-align:left;border-bottom:1px solid var(--border)">${T.actions}</th>` : ''}
      </tr></thead>
      <tbody>
        ${collaborators.map(c => `
          <tr>
            <td style="padding:.65rem .75rem;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.04)">${escHtml(c.name)}</td>
            <td style="padding:.65rem .75rem;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.04);color:var(--text-muted)">${escHtml(c.email)}</td>
            <td style="padding:.65rem .75rem;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.04)">
              ${isOwner ? `
                <select onchange="changeCollaboratorRole(${c.id}, this.value)" style="background:var(--surface-2);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:.2rem .5rem;font-size:12px">
                  <option value="viewer" ${c.role==='viewer'?'selected':''}>${T.role_viewer}</option>
                  <option value="editor" ${c.role==='editor'?'selected':''}>${T.role_editor}</option>
                </select>` : `<span style="font-size:12px;color:var(--text-muted)">${c.role === 'editor' ? T.role_editor : T.role_viewer}</span>`}
            </td>
            ${isOwner ? `
            <td style="padding:.65rem .75rem;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.04)">
              <button class="btn btn-ghost" style="font-size:12px;padding:.25rem .6rem" onclick="removeCollaborator(${c.id}, '${escHtml(c.name).replace(/'/g,"\\'")}')">
                ${T.revoke}
              </button>
            </td>` : ''}
          </tr>
        `).join('')}
      </tbody>
    </table>`;
  }

  async function changeCollaboratorRole(userId, role) {
    await api.updateCollaborator(projectId, userId, { role });
    toast.success(T.toast_collaborator_updated);
  }

  async function removeCollaborator(userId, name) {
    if (!confirm(T.confirm_remove_collaborator.replace('%s', name))) return;
    const res = await api.removeCollaborator(projectId, userId);
    if (res.ok) {
      toast.success(T.toast_collaborator_removed);
      renderCollaborators();
    }
  }

  function openAddCollaboratorModal() {
    showModal(T.add_collaborator, [
      { id: 'collab_email', label: T.collaborator_email, type: 'text', defaultValue: '' },
      { id: 'collab_role',  label: T.collaborator_role,  type: 'select',
        options: [{value:'viewer',text:T.role_viewer},{value:'editor',text:T.role_editor}] },
    ], async () => {
      const email = document.getElementById('modal_input_collab_email').value.trim();
      const role  = document.getElementById('modal_input_collab_role').value || 'viewer';
      const res = await api.addCollaborator(projectId, { email, role });
      if (res.ok) {
        toast.success(T.toast_collaborator_added);
        closeModal();
        renderCollaborators();
      } else {
        const err = await res.json();
        toast.error(err.detail || T.toast_collaborator_add_failed);
      }
    }, T.add_collaborator);
  }

  function renderGantt(project) {
    const phases = (project && project.phases) ? project.phases : [];
    const container = document.querySelector('.gantt-container');

    // Collect all milestones and events
    const allMilestones = [
      ...(project.milestones || []),
      ...phases.flatMap(p => p.milestones || []),
    ];
    const allEvents = [
      ...(project.events || []),
      ...phases.flatMap(p => p.events || []),
    ];

    if (phases.length === 0 && allMilestones.length === 0 && allEvents.length === 0) {
      container.innerHTML = `<div class="item-empty" style="text-align:center;padding:2rem;">${T.no_phases_gantt}</div>`;
      return;
    }

    const styleId = 'gantt-phase-colors';
    let styleTag = document.getElementById(styleId);
    if (!styleTag) { styleTag = document.createElement('style'); styleTag.id = styleId; document.head.appendChild(styleTag); }
    styleTag.textContent = phases.map(p =>
      `.gantt .phase-bar-${p.id} .bar { fill: ${p.color || '#6366f1'} !important; }` +
      `.gantt .phase-bar-${p.id} .bar-progress { fill: ${p.color || '#6366f1'} !important; opacity: 0.7; }`
    ).join('\n');

    const tasks = phases.map(p => {
      let deps = '';
      if (p.depends_on_id) deps = 'p' + p.depends_on_id;
      if (p.depends_on_milestone_id) deps = 'ms' + p.depends_on_milestone_id;
      return {
        id: 'p' + p.id,
        name: p.name,
        start: p.start_date,
        end: p.end_date,
        progress: getPhaseStatus(p.start_date, p.end_date) === 'past' ? 100 : 0,
        dependencies: deps,
        custom_class: 'phase-bar-' + p.id,
      };
    });

    // Group milestones by date so same-day milestones share one row
    const msByDate = {};
    allMilestones.forEach(m => {
      if (!msByDate[m.target_date]) msByDate[m.target_date] = [];
      msByDate[m.target_date].push(m);
    });
    Object.entries(msByDate).forEach(([date, group]) => {
      tasks.push({
        id: 'ms-' + date,
        name: '◆ ' + group.map(m => m.name).join(' · '),
        start: date,
        end: date,
        progress: 0,
        dependencies: '',
        custom_class: 'gantt-milestone',
      });
    });

    // Update phase dependencies to point at the grouped milestone row id
    tasks.forEach(t => {
      if (t.dependencies && t.dependencies.startsWith('ms')) {
        const msId = parseInt(t.dependencies.slice(2));
        const ms = allMilestones.find(m => m.id === msId);
        if (ms) t.dependencies = 'ms-' + ms.target_date;
      }
    });

    allEvents.forEach(e => tasks.push({
      id: 'ev' + e.id,
      name: '▸ ' + e.name,
      start: e.start_date,
      end: e.end_date,
      progress: 0,
      dependencies: '',
      custom_class: 'gantt-event',
    }));

    container.innerHTML = '<svg id="gantt"></svg>';

    state.ganttTasks = tasks;
    state.ganttInstance = new Gantt('#gantt', tasks, {
      header_height: 50,
      column_width: 30,
      step: 24,
      bar_height: 22,
      bar_corner_radius: 4,
      arrow_curve: 5,
      padding: 18,
      view_mode: state.ganttView,
      view_modes: ['Day', 'Week', 'Month'],

      on_date_change(task, start, end) {
        const revert = () => renderGantt(state.project);

        if (task.id.startsWith('p')) {
          const phaseId = parseInt(task.id.slice(1));
          const phase   = state.project.phases.find(p => p.id === phaseId);
          if (!phase) return revert();
          const newStart = dateToYMD(start), newEnd = dateToYMD(end);
          const delta      = Math.round((parseDateLocal(newStart) - parseDateLocal(phase.start_date)) / 86400000);
          if (delta === 0) return;
          const dependents = collectPhaseDependents(phaseId, delta, state.project.phases);
          showImpactConfirm(
            buildImpactHTML(phase.name, delta, dependents),
            async () => {
              const r = await api.updatePhase(phaseId, {
                name: phase.name, description: phase.description,
                start_date: newStart, end_date: newEnd,
                color: phase.color || '#6366f1',
                depends_on_id: phase.depends_on_id ?? null,
                depends_on_milestone_id: phase.depends_on_milestone_id ?? null,
              });
              if (r.ok) refresh(); else { toast.error(T.toast_save_failed); revert(); }
            },
            revert
          );

        } else if (task.id.startsWith('ms-')) {
          const origDate = task.id.slice(3);
          const newDate  = dateToYMD(start);
          if (newDate === origDate) return;
          const allMs     = [
            ...(state.project.milestones || []),
            ...state.project.phases.flatMap(p => p.milestones || []),
          ];
          const group     = allMs.filter(m => m.target_date === origDate);
          const delta     = Math.round((parseDateLocal(newDate) - parseDateLocal(origDate)) / 86400000);
          // collect phases that depend on any milestone in the group
          const msDeps = group.flatMap(m => {
            const sign = delta > 0 ? `+${delta}` : `${delta}`;
            return state.project.phases
              .filter(p => p.depends_on_milestone_id === m.id)
              .map(p => ({ name: p.name, newStart: shiftDateStr(p.start_date, delta), newEnd: shiftDateStr(p.end_date, delta) }));
          });
          const label = group.length === 1 ? group[0].name : `${group.length} milestones on ${fmtDate(origDate)}`;
          showImpactConfirm(
            buildImpactHTML(label, delta, msDeps),
            async () => {
              await Promise.all(group.map(m => api.updateMilestone(m.id, { target_date: newDate })));
              refresh();
            },
            revert
          );

        } else if (task.id.startsWith('ev')) {
          const evId     = parseInt(task.id.slice(2));
          const allEvs   = [...(state.project.events || []), ...state.project.phases.flatMap(p => p.events || [])];
          const ev       = allEvs.find(e => e.id === evId);
          if (!ev) return revert();
          const newStart = dateToYMD(start), newEnd = dateToYMD(end);
          const delta    = Math.round((parseDateLocal(newStart) - parseDateLocal(ev.start_date)) / 86400000);
          if (delta === 0) return;
          showImpactConfirm(
            buildImpactHTML(ev.name, delta, []),
            async () => {
              const r = await api.updateEvent(evId, { start_date: newStart, end_date: newEnd });
              if (r.ok) refresh(); else { toast.error(T.toast_save_failed); revert(); }
            },
            revert
          );
        }
      },

      on_click(task) {
        if (task.id.startsWith('p')) {
          editPhase(parseInt(task.id.slice(1)));

        } else if (task.id.startsWith('ms-')) {
          const date = task.id.slice(3);
          const allMs = [
            ...(state.project.milestones || []),
            ...state.project.phases.flatMap(p => p.milestones || []),
          ];
          const group = allMs.filter(m => m.target_date === date);
          if (group.length === 1) {
            editMilestone(group[0].id, group[0].name, group[0].target_date);
          } else {
            showModal('Edit Milestone', [
              { id: 'ms_pick', label: 'Select milestone', type: 'select',
                options: group.map(m => ({ value: m.id, text: m.name })) },
            ], () => {
              const id = parseInt(document.getElementById('modal_input_ms_pick').value);
              const ms = group.find(m => m.id === id);
              if (ms) { closeModal(); editMilestone(ms.id, ms.name, ms.target_date); }
            }, 'Edit');
          }

        } else if (task.id.startsWith('ev')) {
          editEvent(parseInt(task.id.slice(2)));
        }
      },
    });
    addGanttDateLabels(tasks);
  }

  function addGanttDateLabels(tasks) {
    const svg = document.querySelector('#gantt');
    if (!svg) return;
    svg.querySelectorAll('.gantt-date-label').forEach(el => el.remove());

    const taskMap = {};
    tasks.forEach(t => {
      if (t.id.startsWith('ms-')) {
        taskMap[t.id] = fmtDate(t.start);
      } else {
        const s = fmtDate(t.start), e = fmtDate(t.end);
        taskMap[t.id] = s === e ? s : `${s} → ${e}`;
      }
    });

    svg.querySelectorAll('.bar-wrapper').forEach(wrapper => {
      const id = wrapper.getAttribute('data-id');
      if (!id || !taskMap[id]) return;
      const bar = wrapper.querySelector('.bar');
      if (!bar) return;
      const barX      = parseFloat(bar.getAttribute('x')      || 0);
      const barWidth  = parseFloat(bar.getAttribute('width')  || 0);
      const barY      = parseFloat(bar.getAttribute('y')      || 0);
      const barHeight = parseFloat(bar.getAttribute('height') || 22);

      // Default: right of bar. If the bar-label overflowed outside the bar,
      // push the date label past the bar-label's rendered bounding box instead.
      let x = barX + barWidth + 6;
      const barLabel = wrapper.querySelector('.bar-label');
      if (barLabel) {
        const labelX = parseFloat(barLabel.getAttribute('x') || 0);
        if (labelX > barX + barWidth) {
          try { const bb = barLabel.getBBox(); x = bb.x + bb.width + 6; } catch(e) {}
        }
      }
      const y = barY + barHeight / 2;
      const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      text.setAttribute('x', x);
      text.setAttribute('y', y);
      text.setAttribute('dominant-baseline', 'middle');
      text.setAttribute('class', 'gantt-date-label');
      text.style.fill = '#94a3b8';
      text.style.fontSize = '10px';
      text.style.fontFamily = 'inherit';
      text.style.pointerEvents = 'none';
      text.textContent = taskMap[id];
      wrapper.appendChild(text);
    });
  }

  // ── Tabs ─────────────────────────────────────────────────────
  function switchTab(tab) {
    state.activeTab = tab;
    document.querySelectorAll('.tab-btn').forEach(b => {
      const active = b.dataset.tab === tab;
      b.classList.toggle('active', active);
      b.setAttribute('aria-selected', active);
    });
    document.getElementById('tab-phases').style.display        = tab === 'phases'        ? '' : 'none';
    document.getElementById('tab-timeline').style.display      = tab === 'timeline'      ? '' : 'none';
    document.getElementById('tab-collaborators').style.display = tab === 'collaborators' ? '' : 'none';
    if (tab === 'timeline'      && state.project) renderGantt(state.project);
    if (tab === 'collaborators') renderCollaborators();
  }

  function setGanttView(view) {
    state.ganttView = view;
    document.querySelectorAll('#ganttViewBtns button').forEach(b => b.classList.toggle('active', b.dataset.view === view));
    if (state.ganttInstance) {
      state.ganttInstance.change_view_mode(view);
      addGanttDateLabels(state.ganttTasks || []);
    }
  }

  // ── Subscribe / ICS Modal ────────────────────────────────────
  function openSubscribeModal() {
    const url = window.location.origin + '/project/' + projectId + '/calendar.ics?token=' + encodeURIComponent(icsToken);
    document.getElementById('icsUrl').value = url;
    document.getElementById('subscribeModal').classList.add('is-open');
  }

  function closeSubscribeModal() {
    document.getElementById('subscribeModal').classList.remove('is-open');
  }

  async function copyIcsUrl() {
    const url = document.getElementById('icsUrl').value;
    try {
      await navigator.clipboard.writeText(url);
      toast.success(T.toast_url_copied);
    } catch {
      document.getElementById('icsUrl').select();
      toast.info(T.toast_copy_manual);
    }
  }

  // ── Generic Modal ────────────────────────────────────────────
  let _modalCallback = null;

  function showModal(title, fields, callback, submitLabel = 'Save') {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalSubmitBtn').textContent = submitLabel;
    const container = document.getElementById('modalFields');
    container.innerHTML = '';
    fields.forEach(f => {
      const wrap = document.createElement('div');
      wrap.className = 'modal-field';
      wrap.innerHTML = buildFieldHTML(f);
      container.appendChild(wrap);
    });
    // colour swatches: click to select
    fields.filter(f => f.type === 'color').forEach(f => {
      const swatches = document.querySelectorAll(`[data-swatch-for="${f.id}"]`);
      swatches.forEach(sw => sw.addEventListener('click', () => {
        document.getElementById(`modal_input_${f.id}`).value = sw.dataset.color;
        swatches.forEach(s => s.classList.toggle('is-selected', s === sw));
      }));
    });
    _modalCallback = callback;
    document.getElementById('genericModal').classList.add('is-open');
    setTimeout(() => container.querySelector('input, select')?.focus(), 50);
  }

  function buildFieldHTML(f) {
    const label = `<label class="field-label" for="modal_input_${f.id}">${escHtml(f.label)}</label>`;
    if (f.type === 'color') {
      const palette = [
        '#6366f1','#8b5cf6','#ec4899','#ef4444',
        '#f97316','#f59e0b','#eab308','#84cc16',
        '#22c55e','#10b981','#14b8a6','#06b6d4',
        '#3b82f6','#0ea5e9','#64748b','#94a3b8',
      ];
      const selected = f.defaultValue || palette[0];
      const swatchHTML = palette.map(c =>
        `<button type="button" class="color-swatch${c === selected ? ' is-selected' : ''}"
          data-swatch-for="${f.id}" data-color="${c}"
          style="background:${c}" title="${c}" aria-label="${c}"></button>`
      ).join('');
      return `${label}<input type="hidden" id="modal_input_${f.id}" value="${escHtml(selected)}">
        <div class="color-swatches">${swatchHTML}</div>`;
    }
    if (f.type === 'select') {
      const opts = (f.options || []).map(o =>
        `<option value="${escHtml(String(o.value))}"${String(o.value) === (f.defaultValue ?? '') ? ' selected' : ''}>${escHtml(o.text)}</option>`
      ).join('');
      return `${label}<select id="modal_input_${f.id}"><option value=""${!f.defaultValue ? ' selected' : ''}>— None —</option>${opts}</select>`;
    }
    if (f.type === 'textarea') {
      return `${label}<textarea id="modal_input_${f.id}" rows="3" autocomplete="off">${escHtml(f.defaultValue || '')}</textarea>`;
    }
    return `${label}<input type="${f.type || 'text'}" id="modal_input_${f.id}" value="${escHtml(f.defaultValue || '')}" autocomplete="off">`;
  }

  function closeModal() {
    document.getElementById('genericModal').classList.remove('is-open');
    _modalCallback = null;
  }

  document.getElementById('modalSubmitBtn').addEventListener('click', () => {
    if (_modalCallback) _modalCallback();
  });

  // ── Confirmation Modal ───────────────────────────────────────
  let _confirmCallback       = null;
  let _confirmCancelCallback = null;

  function showConfirm(message, onConfirm, title) {
    title = title || T.modal_confirm_deletion;
    const okBtn = document.getElementById('confirmOkBtn');
    okBtn.textContent = 'Delete';
    okBtn.className = 'btn btn-danger-outline';
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    _confirmCallback       = onConfirm;
    _confirmCancelCallback = null;
    document.getElementById('confirmModal').classList.add('is-open');
  }

  function showImpactConfirm(htmlMessage, onApply, onCancel, title) {
    title = title || T.modal_confirm_change;
    const okBtn = document.getElementById('confirmOkBtn');
    okBtn.textContent = 'Apply';
    okBtn.className = 'btn btn-primary';
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').innerHTML = htmlMessage;
    _confirmCallback       = onApply;
    _confirmCancelCallback = onCancel;
    document.getElementById('confirmModal').classList.add('is-open');
  }

  function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('is-open');
    const cancelCb = _confirmCancelCallback;
    _confirmCallback       = null;
    _confirmCancelCallback = null;
    // reset button to delete style for next showConfirm call
    const okBtn = document.getElementById('confirmOkBtn');
    okBtn.textContent = 'Delete';
    okBtn.className = 'btn btn-danger-outline';
    if (cancelCb) cancelCb();
  }

  document.getElementById('confirmOkBtn').addEventListener('click', () => {
    const cb = _confirmCallback;
    _confirmCallback       = null;
    _confirmCancelCallback = null;
    document.getElementById('confirmModal').classList.remove('is-open');
    const okBtn = document.getElementById('confirmOkBtn');
    okBtn.textContent = 'Delete';
    okBtn.className = 'btn btn-danger-outline';
    if (cb) cb();
  });

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

  // ── Phase collapse / expand ──────────────────────────────────
  function togglePhase(phaseId) {
    const card = document.querySelector(`.phase-card[data-phase-id="${phaseId}"]`);
    if (!card) return;
    const collapsing = !card.classList.contains('is-collapsed');
    card.classList.toggle('is-collapsed', collapsing);
    const toggleArea = card.querySelector('.phase-card__toggle-area');
    if (toggleArea) toggleArea.title = collapsing ? T.expand_phase : T.collapse_phase;
  }

  // ── Phase Actions ────────────────────────────────────────────
  function addPhase() {
    showModal(T.modal_add_phase, [
      { id: 'name',  label: T.phase_name,   type: 'text' },
      { id: 'desc',  label: T.description,  type: 'textarea' },
      { id: 'start', label: T.start_date,   type: 'date', defaultValue: todayStr() },
      { id: 'end',   label: T.end_date,     type: 'date', defaultValue: todayStr() },
      { id: 'color', label: T.color,        type: 'color', defaultValue: '#6366f1' },
    ], async () => {
      const name  = document.getElementById('modal_input_name').value.trim();
      const desc  = document.getElementById('modal_input_desc').value.trim();
      const start = document.getElementById('modal_input_start').value;
      const end   = document.getElementById('modal_input_end').value;
      const color = document.getElementById('modal_input_color').value;
      if (!name || !start || !end) return;
      const btn = document.getElementById('modalSubmitBtn');
      btn.disabled = true;
      try {
        const resp = await api.createPhase(projectId, { name, description: desc || null, start_date: start, end_date: end, color });
        if (resp.ok) { toast.success(T.toast_phase_added); closeModal(); await refresh(); }
        else toast.error(T.toast_phase_add_failed);
      } finally { btn.disabled = false; }
    }, T.modal_add_phase);
  }

  function editPhase(phaseId) {
    const phase = state.project.phases.find(p => p.id === phaseId);
    if (!phase) return;
    showModal(T.modal_edit_phase, [
      { id: 'name',  label: T.phase_name,   type: 'text',     defaultValue: phase.name },
      { id: 'desc',  label: T.description,  type: 'textarea', defaultValue: phase.description || '' },
      { id: 'start', label: T.start_date,   type: 'date',     defaultValue: phase.start_date },
      { id: 'end',   label: T.end_date,     type: 'date',     defaultValue: phase.end_date },
      { id: 'color', label: T.color,        type: 'color',    defaultValue: phase.color || '#6366f1' },
    ], async () => {
      const name  = document.getElementById('modal_input_name').value.trim();
      const desc  = document.getElementById('modal_input_desc').value.trim();
      const start = document.getElementById('modal_input_start').value;
      const end   = document.getElementById('modal_input_end').value;
      const color = document.getElementById('modal_input_color').value;
      if (!name || !start || !end) return;
      const btn = document.getElementById('modalSubmitBtn');
      btn.disabled = true;
      try {
        const resp = await api.updatePhase(phaseId, {
          name, description: desc || null, start_date: start, end_date: end, color,
          depends_on_id: phase.depends_on_id ?? null,
          depends_on_milestone_id: phase.depends_on_milestone_id ?? null,
        });
        if (resp.ok) { toast.success(T.toast_phase_updated); closeModal(); await refresh(); }
        else toast.error(T.toast_phase_update_failed);
      } finally { btn.disabled = false; }
    }, T.save_changes);
  }

  function setDependency(phaseId) {
    const phaseOpts = (state.project.phases || [])
      .filter(p => p.id !== phaseId)
      .map(p => ({ value: 'phase:' + p.id, text: '📋 ' + p.name }));
    const allMilestones = [
      ...(state.project.milestones || []),
      ...(state.project.phases || []).flatMap(p => p.milestones || []),
    ];
    const msOpts = allMilestones.map(m => ({ value: 'ms:' + m.id, text: '◆ ' + m.name + ' (' + fmtDate(m.target_date) + ')' }));
    const opts = [...phaseOpts, ...msOpts];
    const phase = state.project.phases.find(p => p.id === phaseId);
    const currentVal = phase.depends_on_milestone_id
      ? 'ms:' + phase.depends_on_milestone_id
      : (phase.depends_on_id ? 'phase:' + phase.depends_on_id : '');
    showModal('Set Phase Dependency', [
      { id: 'target', label: 'This phase starts after…', type: 'select', options: opts, defaultValue: currentVal },
    ], async () => {
      const raw = document.getElementById('modal_input_target').value;
      let depends_on_id = null, depends_on_milestone_id = null;
      if (raw.startsWith('phase:')) depends_on_id = parseInt(raw.slice(6));
      else if (raw.startsWith('ms:'))  depends_on_milestone_id = parseInt(raw.slice(3));
      const resp = await api.updatePhase(phaseId, {
        name: phase.name,
        start_date: phase.start_date,
        end_date: phase.end_date,
        color: phase.color || '#6366f1',
        depends_on_id,
        depends_on_milestone_id,
      });
      if (resp.ok) { toast.success('Dependency updated'); closeModal(); await refresh(); }
      else toast.error('Failed to update dependency');
    }, 'Save');
  }

  function confirmDeletePhase(id, name) {
    showConfirm(
      T.confirm_delete_phase.replace('%s', name),
      async () => {
        const resp = await api.deletePhase(id);
        if (resp.ok) { toast.success(T.toast_phase_deleted); await refresh(); }
        else toast.error(T.toast_phase_delete_failed);
      }
    );
  }

  // ── Project-level Actions ────────────────────────────────────
  function addProjectMilestone() {
    showModal(T.modal_add_project_milestone, [
      { id: 'name',   label: T.milestone_name, type: 'text' },
      { id: 'target', label: T.target_date,    type: 'date', defaultValue: todayStr() },
    ], async () => {
      const name = document.getElementById('modal_input_name').value.trim();
      const date = document.getElementById('modal_input_target').value;
      if (!name || !date) return;
      const btn = document.getElementById('modalSubmitBtn');
      btn.disabled = true;
      try {
        const resp = await api.createProjectMilestone(projectId, { name, target_date: date });
        if (resp.ok) { toast.success(T.toast_milestone_added); closeModal(); await refresh(); }
        else toast.error(T.toast_milestone_add_failed);
      } finally { btn.disabled = false; }
    }, T.modal_add_milestone);
  }

  function addProjectEvent() {
    showModal(T.modal_add_project_event, [
      { id: 'name',  label: T.event_name,  type: 'text' },
      { id: 'start', label: T.start_date,  type: 'date', defaultValue: todayStr() },
      { id: 'end',   label: T.end_date,    type: 'date', defaultValue: todayStr() },
    ], async () => {
      const name  = document.getElementById('modal_input_name').value.trim();
      const start = document.getElementById('modal_input_start').value;
      const end   = document.getElementById('modal_input_end').value;
      if (!name || !start || !end) return;
      const btn = document.getElementById('modalSubmitBtn');
      btn.disabled = true;
      try {
        const resp = await api.createProjectEvent(projectId, { name, start_date: start, end_date: end });
        if (resp.ok) { toast.success(T.toast_event_added); closeModal(); await refresh(); }
        else toast.error(T.toast_event_add_failed);
      } finally { btn.disabled = false; }
    }, T.modal_add_event);
  }

  // ── Milestone Actions ────────────────────────────────────────
  function addMilestone(phaseId) {
    showModal(T.modal_add_milestone, [
      { id: 'name',   label: T.milestone_name, type: 'text' },
      { id: 'target', label: T.target_date,    type: 'date', defaultValue: todayStr() },
    ], async () => {
      const name = document.getElementById('modal_input_name').value.trim();
      const date = document.getElementById('modal_input_target').value;
      if (!name || !date) return;
      const btn = document.getElementById('modalSubmitBtn');
      btn.disabled = true;
      try {
        const resp = await api.createMilestone(phaseId, { name, target_date: date });
        if (resp.ok) { toast.success(T.toast_milestone_added); closeModal(); await refresh(); }
        else toast.error(T.toast_milestone_add_failed);
      } finally { btn.disabled = false; }
    }, T.modal_add_milestone);
  }

  function editMilestone(id, name, currentDate) {
    showModal(`${T.modal_edit_milestone}: ${name}`, [
      { id: 'target', label: T.target_date, type: 'date', defaultValue: currentDate },
    ], async () => {
      const date = document.getElementById('modal_input_target').value;
      if (!date) return;
      const btn = document.getElementById('modalSubmitBtn');
      btn.disabled = true;
      try {
        const resp = await api.updateMilestone(id, { target_date: date });
        if (resp.ok) { toast.success(T.toast_milestone_updated); closeModal(); await refresh(); }
        else toast.error(T.toast_milestone_update_failed);
      } finally { btn.disabled = false; }
    }, T.save);
  }

  function confirmDeleteMilestone(id, name) {
    showConfirm(
      T.confirm_delete_milestone.replace('%s', name),
      async () => {
        const resp = await api.deleteMilestone(id);
        if (resp.ok) { toast.success(T.toast_milestone_deleted); await refresh(); }
        else toast.error(T.toast_milestone_delete_failed);
      }
    );
  }

  // ── Event Actions ────────────────────────────────────────────
  function addEvent(phaseId) {
    showModal(T.modal_add_event, [
      { id: 'name',  label: T.event_name,  type: 'text' },
      { id: 'start', label: T.start_date,  type: 'date', defaultValue: todayStr() },
      { id: 'end',   label: T.end_date,    type: 'date', defaultValue: todayStr() },
    ], async () => {
      const name  = document.getElementById('modal_input_name').value.trim();
      const start = document.getElementById('modal_input_start').value;
      const end   = document.getElementById('modal_input_end').value;
      if (!name || !start || !end) return;
      const btn = document.getElementById('modalSubmitBtn');
      btn.disabled = true;
      try {
        const resp = await api.createEvent(phaseId, { name, start_date: start, end_date: end });
        if (resp.ok) { toast.success(T.toast_event_added); closeModal(); await refresh(); }
        else toast.error(T.toast_event_add_failed);
      } finally { btn.disabled = false; }
    }, T.modal_add_event);
  }

  function editEvent(evId) {
    const allEvents = [
      ...(state.project.events || []),
      ...state.project.phases.flatMap(p => p.events || []),
    ];
    const ev = allEvents.find(e => e.id === evId);
    if (!ev) return;
    showModal(T.modal_edit_event, [
      { id: 'name',  label: T.event_name,  type: 'text', defaultValue: ev.name },
      { id: 'start', label: T.start_date,  type: 'date', defaultValue: ev.start_date },
      { id: 'end',   label: T.end_date,    type: 'date', defaultValue: ev.end_date },
    ], async () => {
      const name  = document.getElementById('modal_input_name').value.trim();
      const start = document.getElementById('modal_input_start').value;
      const end   = document.getElementById('modal_input_end').value;
      if (!name || !start || !end) return;
      const btn = document.getElementById('modalSubmitBtn');
      btn.disabled = true;
      try {
        const resp = await api.updateEvent(evId, { name, start_date: start, end_date: end });
        if (resp.ok) { toast.success(T.toast_event_updated); closeModal(); await refresh(); }
        else toast.error(T.toast_event_update_failed);
      } finally { btn.disabled = false; }
    }, T.save_changes);
  }

  function confirmDeleteEvent(id, name) {
    showConfirm(
      T.confirm_delete_event.replace('%s', name),
      async () => {
        const resp = await api.deleteEvent(id);
        if (resp.ok) { toast.success(T.toast_event_deleted); await refresh(); }
        else toast.error(T.toast_event_delete_failed);
      }
    );
  }

  // ── Project Actions ──────────────────────────────────────────
  function editProject() {
    const p = state.project;
    showModal(T.modal_edit_project, [
      { id: 'name', label: T.project_name, type: 'text', defaultValue: p.name },
      { id: 'desc', label: T.description,  type: 'text', defaultValue: p.description || '' },
    ], async () => {
      const name = document.getElementById('modal_input_name').value.trim();
      const description = document.getElementById('modal_input_desc').value.trim();
      if (!name) return;
      const resp = await api.updateProject(projectId, { name, description });
      if (resp.ok) { toast.success(T.toast_project_updated); closeModal(); await refresh(); }
      else toast.error(T.toast_something_wrong);
    }, T.save_changes);
  }

  function confirmDeleteProject() {
    showConfirm(
      T.confirm_delete_project,
      async () => {
        const resp = await api.deleteProject(projectId);
        if (resp.ok) {
          toast.success(T.toast_project_deleted);
          setTimeout(() => { window.location.href = '/'; }, 800);
        } else {
          toast.error(T.toast_project_delete_failed);
        }
      }
    );
  }

  // ── Keyboard Shortcuts ───────────────────────────────────────
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal(); closeConfirm(); closeSubscribeModal(); }
  });
  document.getElementById('genericModal').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey && e.target.tagName !== 'SELECT' && e.target.tagName !== 'TEXTAREA') {
      e.preventDefault();
      document.getElementById('modalSubmitBtn').click();
    }
  });
  document.getElementById('genericModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
  });
  document.getElementById('confirmModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeConfirm();
  });
  document.getElementById('subscribeModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeSubscribeModal();
  });

  // ── Init ─────────────────────────────────────────────────────
  async function refresh() {
    try {
      state.project = await api.getProject(projectId);
      renderProject(state.project);
    } catch (err) {
      toast.error(T.toast_load_failed);
    }
  }

  refresh();
</script>
</body>
</html>
