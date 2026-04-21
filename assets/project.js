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
const state = { project: null, activeTab: 'phases', ganttView: 'Month', ganttInstance: null };

// ── API ──────────────────────────────────────────────────────────────────────
const H = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const api = {
  getProject:      (id)       => fetch(`/api/projects/${id}`).then(r => r.json()),
  updateProject:   (id, data) => fetch(`/api/projects/${id}`, { method: 'PUT', headers: H, body: JSON.stringify(data) }),
  deleteProject:   (id)       => fetch(`/api/projects/${id}`, { method: 'DELETE', headers: H }),
  createPhase:     (pid, data)=> fetch(`/api/phases?project_id=${pid}`, { method: 'POST', headers: H, body: JSON.stringify(data) }),
  updatePhase:     (id, data) => fetch(`/api/phases/${id}`, { method: 'PUT', headers: H, body: JSON.stringify(data) }),
  deletePhase:     (id)       => fetch(`/api/phases/${id}`, { method: 'DELETE', headers: H }),
  createMilestone:        (phid, d)  => fetch(`/api/phases/${phid}/milestones`, { method: 'POST', headers: H, body: JSON.stringify(d) }),
  deleteMilestone:        (id)       => fetch(`/api/milestones/${id}`, { method: 'DELETE', headers: H }),
  createEvent:            (phid, d)  => fetch(`/api/phases/${phid}/events`, { method: 'POST', headers: H, body: JSON.stringify(d) }),
  deleteEvent:            (id)       => fetch(`/api/events/${id}`, { method: 'DELETE', headers: H }),
  createProjectMilestone: (pid, d)   => fetch(`/api/projects/${pid}/milestones`, { method: 'POST', headers: H, body: JSON.stringify(d) }),
  createProjectEvent:     (pid, d)   => fetch(`/api/projects/${pid}/events`, { method: 'POST', headers: H, body: JSON.stringify(d) }),
  updateMilestone:        (id, d)    => fetch(`/api/milestones/${id}`, { method: 'PATCH', headers: H, body: JSON.stringify(d) }),
  updateEvent:            (id, d)    => fetch(`/api/events/${id}`,     { method: 'PATCH', headers: H, body: JSON.stringify(d) }),
  getCollaborators:       (pid)      => fetch(`/api/projects/${pid}/collaborators`).then(r => r.json()),
  addCollaborator:        (pid, d)   => fetch(`/api/projects/${pid}/collaborators`, { method: 'POST', headers: H, body: JSON.stringify(d) }),
  updateCollaborator:     (pid, uid, d) => fetch(`/api/projects/${pid}/collaborators/${uid}`, { method: 'PATCH', headers: H, body: JSON.stringify(d) }),
  removeCollaborator:     (pid, uid) => fetch(`/api/projects/${pid}/collaborators/${uid}`, { method: 'DELETE', headers: H }),
};

// ── Utilities ────────────────────────────────────────────────────────────────
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

function fmtTime(t) {
  // "HH:MM:SS" → "HH:MM"; handle null/undefined
  if (!t) return '';
  return t.slice(0, 5);
}

function fmtEventMeta(e) {
  const s = fmtDate(e.start_date), en = fmtDate(e.end_date);
  const st = fmtTime(e.start_time), et = fmtTime(e.end_time);
  if (st) {
    // timed event
    if (s === en) return `${s} ${st}${et && et !== st ? ' – ' + et : ''}`;
    return `${s} ${st} → ${en}${et ? ' ' + et : ''}`;
  }
  return s === en ? s : `${s} → ${en}`;
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

// ── Render ───────────────────────────────────────────────────────────────────
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
  if (state.activeTab === 'timeline') requestAnimationFrame(() => renderGantt(p));
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
          <button class="btn btn-icon btn-ghost" title="Edit milestone" style="width:22px;height:22px;padding:2px;"
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
          <span class="item-list__meta">${fmtEventMeta(e)}</span>
          ${canEdit ? `
          <button class="btn btn-icon btn-ghost" title="${T.tooltip_edit_event}" style="width:22px;height:22px;padding:2px;"
            onclick="editEvent(${e.id})">
            <svg><use href="#icon-pencil"/></svg>
          </button>
          <button class="btn btn-icon btn-danger-outline" title="${T.tooltip_delete_event}" style="width:22px;height:22px;padding:2px;"
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
            <button class="btn btn-icon btn-ghost" title="Edit milestone" style="width:22px;height:22px;padding:2px;"
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
            <span class="item-list__meta">${fmtEventMeta(e)}</span>
            ${canEdit ? `
            <button class="btn btn-icon btn-ghost" title="${T.tooltip_edit_event}" style="width:22px;height:22px;padding:2px;"
              onclick="editEvent(${e.id})">
              <svg><use href="#icon-pencil"/></svg>
            </button>
            <button class="btn btn-icon btn-danger-outline" title="${T.tooltip_delete_event}" style="width:22px;height:22px;padding:2px;"
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

// ── Collaborators ─────────────────────────────────────────────────────────────
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
    start_time: e.start_time || null,
    end_time: e.end_time   || null,
    progress: 0,
    dependencies: '',
    custom_class: 'gantt-event',
  }));

  container.innerHTML = '<svg id="gantt"></svg>';

  state.ganttTasks = tasks;
  try {
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
  requestAnimationFrame(() => { addGanttDateLabels(tasks); addTodayLine(); });
  } catch (err) {
    console.error('Gantt render failed:', err);
    container.innerHTML = `<div class="item-empty" style="text-align:center;padding:2rem;">${T.gantt_render_error || 'Failed to render timeline. Check the browser console for details.'}</div>`;
  }
}

function addGanttDateLabels(tasks) {
  const svg = document.querySelector('#gantt');
  if (!svg) return;
  svg.querySelectorAll('.gantt-date-label').forEach(el => el.remove());

  const taskMap = {};
  tasks.forEach(t => {
    if (t.id.startsWith('ms-')) {
      taskMap[t.id] = fmtDate(t.start);
    } else if (t.id.startsWith('ev') && t.start_time) {
      // Timed event: show date + time range
      const st = fmtTime(t.start_time), et = fmtTime(t.end_time);
      const s = fmtDate(t.start), e = fmtDate(t.end);
      if (s === e) {
        taskMap[t.id] = `${s} ${st}${et && et !== st ? ` – ${et}` : ''}`;
      } else {
        taskMap[t.id] = `${s} ${st} → ${e}${et ? ` ${et}` : ''}`;
      }
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

    let x = barX + barWidth + 6;
    const barLabel = wrapper.querySelector('.bar-label');
    if (barLabel) {
      const labelX = parseFloat(barLabel.getAttribute('x') || 0);
      if (labelX > barX + barWidth) {
        x = labelX + (barLabel.getComputedTextLength() || 0) + 6;
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

// ── Today line ────────────────────────────────────────────────────────────────
function addTodayLine() {
  const svg = document.querySelector('#gantt');
  if (!svg) return;
  svg.querySelectorAll('.gantt-today-line, .gantt-today-label').forEach(el => el.remove());

  let x;
  const highlight = svg.querySelector('.today-highlight');
  if (highlight) {
    const hx = parseFloat(highlight.getAttribute('x') || 0);
    const hw = parseFloat(highlight.getAttribute('width') || 0);
    x = hx + hw / 2;
  } else {
    const gantt = state.ganttInstance;
    if (!gantt || !gantt.gantt_start) return;
    const msPerHour = 3600000;
    const hours = (Date.now() - gantt.gantt_start.getTime()) / msPerHour;
    x = (hours / gantt.options.step) * gantt.options.column_width;
    const svgW = parseFloat(svg.getAttribute('width')) || svg.getBoundingClientRect().width;
    if (x < 0 || x > svgW) return;
  }

  const svgH   = parseFloat(svg.getAttribute('height') || svg.getBoundingClientRect().height || 500);
  const headerH = 50;

  const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
  line.setAttribute('x1', x); line.setAttribute('x2', x);
  line.setAttribute('y1', headerH); line.setAttribute('y2', svgH);
  line.setAttribute('class', 'gantt-today-line');

  const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
  label.setAttribute('x', x + 4);
  label.setAttribute('y', headerH - 4);
  label.setAttribute('class', 'gantt-today-label');
  label.textContent = T.today || 'Today';

  svg.appendChild(line);
  svg.appendChild(label);
  try {
    const bb = label.getBBox();
    const bg = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
    bg.setAttribute('x', bb.x - 2);
    bg.setAttribute('y', bb.y - 1);
    bg.setAttribute('width',  bb.width  + 4);
    bg.setAttribute('height', bb.height + 2);
    bg.setAttribute('rx', 2);
    bg.setAttribute('class', 'gantt-today-label-bg');
    svg.insertBefore(bg, label);
  } catch (_) { /* getBBox unavailable (hidden SVG) — skip background */ }
}

// ── Tabs ──────────────────────────────────────────────────────────────────────
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
  if (tab === 'timeline'      && state.project) requestAnimationFrame(() => renderGantt(state.project));
  if (tab === 'collaborators') renderCollaborators();
}

function setGanttView(view) {
  state.ganttView = view;
  document.querySelectorAll('#ganttViewBtns button').forEach(b => b.classList.toggle('active', b.dataset.view === view));
  if (state.ganttInstance) {
    state.ganttInstance.change_view_mode(view);
    requestAnimationFrame(() => { addGanttDateLabels(state.ganttTasks || []); addTodayLine(); });
  }
}

// ── Subscribe / ICS Modal ─────────────────────────────────────────────────────
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

// ── Generic Modal ─────────────────────────────────────────────────────────────
let _modalCallback = null;

function clearFieldErrors() {
  document.querySelectorAll('#genericModal .is-invalid').forEach(el => el.classList.remove('is-invalid'));
  document.querySelectorAll('#genericModal .field-error').forEach(el => el.remove());
}

function setFieldError(id, msg) {
  const el = document.getElementById('modal_input_' + id);
  if (!el) return;
  el.classList.add('is-invalid');
  if (!el.parentElement.querySelector('.field-error')) {
    const p = document.createElement('p');
    p.className = 'field-error';
    p.textContent = msg;
    el.parentElement.appendChild(p);
  }
}

function showModal(title, fields, callback, submitLabel = 'Save') {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalSubmitBtn').textContent = submitLabel;
  const container = document.getElementById('modalFields');
  container.innerHTML = '';
  fields.forEach(f => {
    const wrap = document.createElement('div');
    wrap.className = 'modal-field' + (f.wrapClass ? ' ' + f.wrapClass : '');
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
  const _startEl = document.getElementById('modal_input_start');
  const _endEl   = document.getElementById('modal_input_end');
  if (_startEl && _endEl) {
    _startEl.addEventListener('change', () => {
      if (_endEl.value && _endEl.value < _startEl.value) {
        _endEl.value = _startEl.value;
      }
    });
  }
  setTimeout(() => {
    clearFieldErrors();
    container.querySelector('input, select')?.focus();
  }, 50);
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
  if (f.type === 'checkbox') {
    return `<label class="modal-checkbox-label" for="modal_input_${f.id}">
      <input type="checkbox" id="modal_input_${f.id}" ${f.defaultValue ? 'checked' : ''}>
      <span>${escHtml(f.label)}</span>
    </label>`;
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

// ── Confirmation Modal ────────────────────────────────────────────────────────
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

// ── Phase collapse / expand ───────────────────────────────────────────────────
function togglePhase(phaseId) {
  const card = document.querySelector(`.phase-card[data-phase-id="${phaseId}"]`);
  if (!card) return;
  const collapsing = !card.classList.contains('is-collapsed');
  card.classList.toggle('is-collapsed', collapsing);
  const toggleArea = card.querySelector('.phase-card__toggle-area');
  if (toggleArea) toggleArea.title = collapsing ? T.expand_phase : T.collapse_phase;
}

// ── Phase Actions ─────────────────────────────────────────────────────────────
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
    clearFieldErrors();
    { let ok = true;
      if (!name)  { setFieldError('name',  T.error_name_required);    ok = false; }
      if (!start) { setFieldError('start', T.error_date_required);     ok = false; }
      if (!end)   { setFieldError('end',   T.error_date_required);     ok = false; }
      if (start && end && end < start) { setFieldError('end', T.error_end_before_start); ok = false; }
      if (!ok) return; }
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
    clearFieldErrors();
    { let ok = true;
      if (!name)  { setFieldError('name',  T.error_name_required);    ok = false; }
      if (!start) { setFieldError('start', T.error_date_required);     ok = false; }
      if (!end)   { setFieldError('end',   T.error_date_required);     ok = false; }
      if (start && end && end < start) { setFieldError('end', T.error_end_before_start); ok = false; }
      if (!ok) return; }
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

  // showModal builds the DOM synchronously, so we can attach immediately.
  // Listen to both 'input' and 'change': 'input' covers programmatic changes
  // (Playwright fill, spinners); 'change' covers the native date picker and
  // keyboard segment editing followed by Tab-out.
  const startEl = document.getElementById('modal_input_start');
  const endEl   = document.getElementById('modal_input_end');
  if (startEl && endEl) {
    const origStart = phase.start_date, origEnd = phase.end_date;
    const shiftEnd = () => {
      if (!startEl.value) return;
      const delta = Math.round((parseDateLocal(startEl.value) - parseDateLocal(origStart)) / 86400000);
      endEl.value = shiftDateStr(origEnd, delta);
    };
    startEl.addEventListener('input',  shiftEnd);
    startEl.addEventListener('change', shiftEnd);
  }
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

// ── Project-level Actions ─────────────────────────────────────────────────────
function addProjectMilestone() {
  showModal(T.modal_add_project_milestone, [
    { id: 'name',   label: T.milestone_name, type: 'text' },
    { id: 'target', label: T.target_date,    type: 'date', defaultValue: todayStr() },
  ], async () => {
    const name = document.getElementById('modal_input_name').value.trim();
    const date = document.getElementById('modal_input_target').value;
    clearFieldErrors();
    { let ok = true;
      if (!name) { setFieldError('name',   T.error_name_required); ok = false; }
      if (!date) { setFieldError('target', T.error_date_required); ok = false; }
      if (!ok) return; }
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
  _openEventModal(T.modal_add_project_event, {}, async (data) => {
    const resp = await api.createProjectEvent(projectId, data);
    if (resp.ok) { toast.success(T.toast_event_added); closeModal(); await refresh(); }
    else toast.error(T.toast_event_add_failed);
  }, T.modal_add_event);
}

// ── Milestone Actions ─────────────────────────────────────────────────────────
function addMilestone(phaseId) {
  showModal(T.modal_add_milestone, [
    { id: 'name',   label: T.milestone_name, type: 'text' },
    { id: 'target', label: T.target_date,    type: 'date', defaultValue: todayStr() },
  ], async () => {
    const name = document.getElementById('modal_input_name').value.trim();
    const date = document.getElementById('modal_input_target').value;
    clearFieldErrors();
    { let ok = true;
      if (!name) { setFieldError('name',   T.error_name_required); ok = false; }
      if (!date) { setFieldError('target', T.error_date_required); ok = false; }
      if (!ok) return; }
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
    { id: 'name',   label: T.milestone_name, type: 'text', defaultValue: name },
    { id: 'target', label: T.target_date,    type: 'date', defaultValue: currentDate },
  ], async () => {
    const newName = document.getElementById('modal_input_name').value.trim();
    const date    = document.getElementById('modal_input_target').value;
    if (!newName || !date) return;
    const btn = document.getElementById('modalSubmitBtn');
    btn.disabled = true;
    try {
      const resp = await api.updateMilestone(id, { name: newName, target_date: date });
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

// ── Event Actions ─────────────────────────────────────────────────────────────

/** Shared event modal builder with all-day / time toggle. */
function _openEventModal(title, defaults, onSave, submitLabel) {
  const isAllDay = !defaults.start_time;
  showModal(title, [
    { id: 'name',       label: T.event_name,       type: 'text',     defaultValue: defaults.name       || '' },
    { id: 'start',      label: T.start_date,        type: 'date',     defaultValue: defaults.start_date || todayStr() },
    { id: 'end',        label: T.end_date,          type: 'date',     defaultValue: defaults.end_date   || todayStr() },
    { id: 'all_day',    label: T.event_all_day,     type: 'checkbox', defaultValue: isAllDay },
    { id: 'start_time', label: T.event_start_time,  type: 'time',     defaultValue: fmtTime(defaults.start_time) || '09:00', wrapClass: 'event-time-field' },
    { id: 'end_time',   label: T.event_end_time,    type: 'time',     defaultValue: fmtTime(defaults.end_time)   || '17:00', wrapClass: 'event-time-field' },
  ], async () => {
    const name   = document.getElementById('modal_input_name').value.trim();
    const start  = document.getElementById('modal_input_start').value;
    const end    = document.getElementById('modal_input_end').value;
    clearFieldErrors();
    { let ok = true;
      if (!name)  { setFieldError('name',  T.error_name_required);    ok = false; }
      if (!start) { setFieldError('start', T.error_date_required);     ok = false; }
      if (!end)   { setFieldError('end',   T.error_date_required);     ok = false; }
      if (start && end && end < start) { setFieldError('end', T.error_end_before_start); ok = false; }
      if (!ok) return; }
    const allDay = document.getElementById('modal_input_all_day').checked;
    const btn = document.getElementById('modalSubmitBtn');
    btn.disabled = true;
    try {
      await onSave({
        name, start_date: start, end_date: end, all_day: allDay,
        start_time: allDay ? null : (document.getElementById('modal_input_start_time').value || null),
        end_time:   allDay ? null : (document.getElementById('modal_input_end_time').value   || null),
      });
    } finally { btn.disabled = false; }
  }, submitLabel);

  // Wire up the all-day toggle after the modal DOM is built
  // showModal builds the DOM synchronously — attach the date-shift listener immediately.
  // Listen to both 'input' and 'change': 'input' covers programmatic changes
  // (Playwright fill, spinners); 'change' covers the native date picker and
  // keyboard segment editing followed by Tab-out.
  const startEl = document.getElementById('modal_input_start');
  const endEl   = document.getElementById('modal_input_end');
  if (startEl && endEl) {
    const origStart = defaults.start_date || todayStr();
    const origEnd   = defaults.end_date   || todayStr();
    const shiftEnd = () => {
      if (!startEl.value) return;
      const delta = Math.round((parseDateLocal(startEl.value) - parseDateLocal(origStart)) / 86400000);
      endEl.value = shiftDateStr(origEnd, delta);
    };
    startEl.addEventListener('input',  shiftEnd);
    startEl.addEventListener('change', shiftEnd);
  }

  setTimeout(() => {
    const cb     = document.getElementById('modal_input_all_day');
    const fields = document.querySelectorAll('#genericModal .event-time-field');
    function sync() { fields.forEach(f => { f.style.display = cb.checked ? 'none' : ''; }); }
    if (cb) { cb.addEventListener('change', sync); sync(); }
  }, 0);
}

function addEvent(phaseId) {
  _openEventModal(T.modal_add_event, {}, async (data) => {
    const resp = await api.createEvent(phaseId, data);
    if (resp.ok) { toast.success(T.toast_event_added); closeModal(); await refresh(); }
    else toast.error(T.toast_event_add_failed);
  }, T.modal_add_event);
}

function editEvent(evId) {
  const allEvents = [
    ...(state.project.events || []),
    ...state.project.phases.flatMap(p => p.events || []),
  ];
  const ev = allEvents.find(e => e.id === evId);
  if (!ev) return;
  _openEventModal(T.modal_edit_event, ev, async (data) => {
    const resp = await api.updateEvent(evId, data);
    if (resp.ok) { toast.success(T.toast_event_updated); closeModal(); await refresh(); }
    else toast.error(T.toast_event_update_failed);
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

// ── Project Actions ───────────────────────────────────────────────────────────
function editProject() {
  const p = state.project;
  showModal(T.modal_edit_project, [
    { id: 'name', label: T.project_name, type: 'text', defaultValue: p.name },
    { id: 'desc', label: T.description,  type: 'text', defaultValue: p.description || '' },
  ], async () => {
    const name = document.getElementById('modal_input_name').value.trim();
    const description = document.getElementById('modal_input_desc').value.trim();
    clearFieldErrors();
    if (!name) { setFieldError('name', T.error_name_required); return; }
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

// ── Keyboard Shortcuts ────────────────────────────────────────────────────────
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

// ── Init ──────────────────────────────────────────────────────────────────────
async function refresh() {
  try {
    state.project = await api.getProject(projectId);
    renderProject(state.project);
  } catch (err) {
    toast.error(T.toast_load_failed);
  }
}

refresh();
