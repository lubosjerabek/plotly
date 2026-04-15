// ── Language dropdown ────────────────────────────────────────────────────────
function toggleLangDropdown(e) {
  e.stopPropagation();
  const dd = document.getElementById('langDropdown');
  const open = dd.classList.toggle('is-open');
  dd.querySelector('.lang-dropdown__btn').setAttribute('aria-expanded', open);
}
document.addEventListener('click', () => {
  const dd = document.getElementById('langDropdown');
  if (dd && dd.classList.contains('is-open')) {
    dd.classList.remove('is-open');
    dd.querySelector('.lang-dropdown__btn').setAttribute('aria-expanded', 'false');
  }
});

// ── User menu ────────────────────────────────────────────────────────────────
function toggleUserMenu(e) {
  e.stopPropagation();
  const m = document.getElementById('userMenu');
  const open = m.classList.toggle('is-open');
  e.currentTarget.setAttribute('aria-expanded', open);
}
document.addEventListener('click', () => {
  const m = document.getElementById('userMenu');
  if (m && m.classList.contains('is-open')) {
    m.classList.remove('is-open');
    m.querySelector('.user-menu__trigger').setAttribute('aria-expanded', 'false');
  }
});

// ── State ────────────────────────────────────────────────────────────────────
const state = { projects: [] };
let _editingProjectId = null;

// ── API ──────────────────────────────────────────────────────────────────────
const h = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const api = {
  getProjects:   ()         => fetch('/api/projects').then(r => r.json()),
  createProject: (data)     => fetch('/api/projects', { method: 'POST', headers: h, body: JSON.stringify(data) }),
  updateProject: (id, data) => fetch(`/api/projects/${id}`, { method: 'PUT', headers: h, body: JSON.stringify(data) }),
  deleteProject: (id)       => fetch(`/api/projects/${id}`, { method: 'DELETE', headers: h }),
};

// ── Render ───────────────────────────────────────────────────────────────────
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

// ── Project Modal ─────────────────────────────────────────────────────────────
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
  const nameEl = document.getElementById('pm_name');
  nameEl.classList.remove('is-invalid');
  nameEl.parentElement.querySelector('.field-error')?.remove();
}

async function submitProjectModal() {
  const name = document.getElementById('pm_name').value.trim();
  const description = document.getElementById('pm_desc').value.trim();
  // Clear previous error
  const nameEl = document.getElementById('pm_name');
  nameEl.classList.remove('is-invalid');
  nameEl.parentElement.querySelector('.field-error')?.remove();
  if (!name) {
    nameEl.classList.add('is-invalid');
    const p = document.createElement('p');
    p.className = 'field-error';
    p.textContent = T.error_name_required || 'Name is required';
    nameEl.parentElement.appendChild(p);
    nameEl.focus();
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

// ── Confirm ───────────────────────────────────────────────────────────────────
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

// ── Toast ─────────────────────────────────────────────────────────────────────
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

// ── Utilities ─────────────────────────────────────────────────────────────────
function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// ── Keyboard shortcuts ────────────────────────────────────────────────────────
document.getElementById('projectModal').addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitProjectModal(); }
});
document.getElementById('projectModal').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeProjectModal();
});
document.getElementById('confirmModal').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeConfirm();
});

// ── Calendar Sync ─────────────────────────────────────────────────────────────
async function openCalSyncModal() {
  document.getElementById('calSyncModal').classList.add('is-open');
  const resp = await fetch('/api/settings/ics-token').then(r => r.json());
  document.getElementById('calSyncUrl').value = resp.url;
}

function closeCalSyncModal() {
  document.getElementById('calSyncModal').classList.remove('is-open');
}

function copyCalUrl() {
  const input = document.getElementById('calSyncUrl');
  const url = input.value;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => toast.success(T.toast_url_copied));
  } else {
    input.select();
    toast.info(T.toast_copy_manual);
  }
}

async function rotateCalToken() {
  const btn = document.getElementById('calRotateBtn');
  btn.disabled = true;
  try {
    const resp = await fetch('/api/settings/ics-token/rotate', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'} });
    if (resp.ok) {
      const data = await resp.json();
      document.getElementById('calSyncUrl').value = data.url;
      toast.success(T.toast_token_rotated);
    } else {
      toast.error(T.toast_token_rotate_failed);
    }
  } finally {
    btn.disabled = false;
  }
}

document.getElementById('calSyncModal').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeCalSyncModal();
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeProjectModal(); closeConfirm(); closeCalSyncModal(); }
});

// ── Init ──────────────────────────────────────────────────────────────────────
async function refresh() {
  state.projects = await api.getProjects();
  renderProjects();
}

refresh();
