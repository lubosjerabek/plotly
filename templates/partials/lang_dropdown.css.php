<?php

/* Language dropdown — shared CSS. Include inside a <style> block. */ ?>
    .lang-dropdown { position: relative; }
    .lang-dropdown__btn {
      display: inline-flex; align-items: center; gap: 0.3em;
      background: none;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      color: var(--text-muted);
      font-family: inherit; font-size: 11px; font-weight: 600;
      letter-spacing: 0.05em; padding: 0.25rem 0.5rem;
      cursor: pointer; transition: all var(--t-fast);
    }
    .lang-dropdown__btn:hover { border-color: var(--border-hover); color: var(--text); }
    .lang-dropdown__chevron { width: 10px; height: 10px; fill: currentColor; transition: transform var(--t-fast); }
    .lang-dropdown.is-open .lang-dropdown__chevron { transform: rotate(180deg); }
    .lang-dropdown.is-open .lang-dropdown__btn { border-color: var(--accent); color: var(--accent); background: rgba(99,102,241,0.1); }
    .lang-dropdown__menu {
      display: none; position: absolute; top: calc(100% + 6px); right: 0;
      background: var(--surface-2); border: 1px solid var(--border);
      border-radius: var(--radius-md); box-shadow: var(--shadow-md);
      overflow: hidden; min-width: 130px; z-index: 200;
    }
    .lang-dropdown.is-open .lang-dropdown__menu { display: block; }
    .lang-dropdown__item-form { margin: 0; }
    .lang-dropdown__item {
      display: flex; align-items: center; gap: 0.5rem; width: 100%;
      padding: 0.55rem 0.85rem; background: none; border: none;
      color: var(--text-muted); font-family: inherit; font-size: 13px;
      cursor: pointer; transition: background var(--t-fast), color var(--t-fast);
      text-align: left;
    }
    .lang-dropdown__item:hover { background: var(--surface-3); color: var(--text); }
    .lang-dropdown__item.is-active { color: var(--accent); }
    .lang-dropdown__code { font-size: 11px; font-weight: 700; letter-spacing: 0.05em; min-width: 1.8em; }
