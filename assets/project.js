// ── Language dropdown ────────────────────────────────────────────────────────
/** Toggle the language picker dropdown and keep its aria-expanded attribute in sync */
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
/** Toggle the user account menu and keep its trigger aria-expanded attribute in sync */
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
const state = {
  project: null,
  activeTab: 'phases',
  ganttView: 'Month',
  ganttInstance: null,
  phasesView: localStorage.getItem('plotly_phases_view') || 'grouped'
};

// ── API ──────────────────────────────────────────────────────────────────────
const H = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const api = {
  getProject:        (id)       => fetch(`/api/projects/${id}`).then(r => r.json()),
  updateProject:     (id, data) => fetch(`/api/projects/${id}`, { method: 'PUT', headers: H, body: JSON.stringify(data) }),
  deleteProject:     (id)       => fetch(`/api/projects/${id}`, { method: 'DELETE', headers: H }),
  createPhaseGroup:  (pid, data)=> fetch(`/api/phase-groups?project_id=${pid}`, { method: 'POST', headers: H, body: JSON.stringify(data) }),
  updatePhaseGroup:  (id, data) => fetch(`/api/phase-groups/${id}`, { method: 'PUT', headers: H, body: JSON.stringify(data) }),
  deletePhaseGroup:  (id)       => fetch(`/api/phase-groups/${id}`, { method: 'DELETE', headers: H }),
  createPhase:       (pid, data)=> fetch(`/api/phases?project_id=${pid}`, { method: 'POST', headers: H, body: JSON.stringify(data) }),
  updatePhase:       (id, data) => fetch(`/api/phases/${id}`, { method: 'PUT', headers: H, body: JSON.stringify(data) }),
  deletePhase:       (id)       => fetch(`/api/phases/${id}`, { method: 'DELETE', headers: H }),
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

/** Escape a string for safe interpolation into HTML attribute values and text content */
function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/** Format a YYYY-MM-DD string using the localised month names from window.T */
function fmtDate(d) {
  if (!d) return '';
  const [y, m, day] = d.split('-');
  const months = T.months;
  return `${months[parseInt(m,10)-1]} ${parseInt(day,10)}, ${y}`;
}

/** Truncate a "HH:MM:SS" time string to "HH:MM"; returns empty string for null/undefined */
function fmtTime(t) {
  // "HH:MM:SS" → "HH:MM"; handle null/undefined
  if (!t) return '';
  return t.slice(0, 5);
}

/** Format an event's date/time range as a compact string for display in phase cards and lists */
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

/** Convert a Date object to a YYYY-MM-DD string using local time (not UTC) */
function dateToYMD(d) {
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

/** Parse a YYYY-MM-DD string into a Date using local midnight (avoids UTC-offset day shifts) */
function parseDateLocal(str) {
  const [y, m, d] = str.split('-').map(Number);
  return new Date(y, m - 1, d);
}

/** Return a flat array of ALL phases (standalone + grouped) for a project. */
function allPhasesFlat(project) {
  if (!project) return [];
  const standalone = project.phases || [];
  const grouped    = (project.groups || []).flatMap(g => g.phases || []);
  return [...standalone, ...grouped];
}

/** Add or subtract days from a YYYY-MM-DD string and return the new date string */
function shiftDateStr(str, days) {
  const d = parseDateLocal(str);
  d.setDate(d.getDate() + days);
  return dateToYMD(d);
}

/**
 * Recursively collect all phases that cascade from a moved phase.
 * @param {number} rootId  ID of the phase that was moved
 * @param {number} delta   Day offset to apply
 * @param {Array}  phases  Full flat phase list (use allPhasesFlat)
 * @returns {Array} Each dependent with name, newStart, and newEnd
 */
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

/** Build an HTML summary of a drag-to-reschedule change, listing the moved item and any cascaded dependents */
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

/** Return 'past', 'active', or 'upcoming' based on how today falls relative to start/end dates */
function getPhaseStatus(start, end) {
  const today = new Date(); today.setHours(0,0,0,0);
  const s = new Date(start), e = new Date(end);
  if (e < today) return 'past';
  if (s > today) return 'upcoming';
  return 'active';
}

/** Render a coloured status badge span for a phase status string ('past', 'active', 'upcoming') */
function statusBadge(status) {
  const labels = { past: T.status_past, active: T.status_active, upcoming: T.status_upcoming };
  return `<span class="badge badge-${status}">${labels[status]}</span>`;
}

// ── Render ───────────────────────────────────────────────────────────────────
/** Update the page title, header fields, and all tab content from a full project object */
function renderProject(p) {
  document.title = `${p.name} — Plotly`;
  document.getElementById('pName').textContent = p.name;
  document.getElementById('pDesc').textContent = p.description || T.no_description_provided;
  document.getElementById('topbarTitle').textContent = p.name;
  // Count all phases: standalone + those inside groups
  const pc = allPhasesFlat(p).length;
  let phasesLabel;
  if (pc === 1) {
    phasesLabel = T.n_phases.replace('%d', pc);
  } else if (pc >= 5 && T.n_phases_plural5) {
    phasesLabel = T.n_phases_plural5.replace('%d', pc);
  } else {
    phasesLabel = (T.n_phases_plural || T.n_phases).replace('%d', pc);
  }
  document.getElementById('phaseCount').textContent = phasesLabel;
  document.querySelectorAll('#phasesViewBtns button').forEach(b => b.classList.toggle('active', b.dataset.view === state.phasesView));
  renderProjectItems(p.milestones || [], p.events || []);
  renderPhases(p.phases, p.groups || []);
  if (state.activeTab === 'timeline') requestAnimationFrame(() => renderGantt(p));
}

/** Render the project-level milestones and events card (items not attached to any phase) */
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

/** Build a phase card element (used for standalone phases and phases inside groups). */
function buildPhaseCard(phase, phaseMap, msMap, wasCollapsed, wasExpanded, hadState) {
  const status = getPhaseStatus(phase.start_date, phase.end_date);
  const color  = phase.color || '#6366f1';
  const depName   = phase.depends_on_id           ? phaseMap[phase.depends_on_id]           : null;
  const depMsName = phase.depends_on_milestone_id ? msMap[phase.depends_on_milestone_id]    : null;
  const collapsed = hadState ? !wasExpanded.has(phase.id) : status !== 'active';

  const group = (state.phasesView === 'flow' && phase.group_id && state.project && state.project.groups)
    ? state.project.groups.find(g => g.id === phase.group_id)
    : null;
  const groupBadge = group
    ? `<span class="badge" style="background:${group.color || '#6366f1'}15;color:${group.color || '#6366f1'};border:1px solid ${group.color || '#6366f1'}30;">⌗ ${escHtml(group.name)}</span>`
    : '';

  const card = document.createElement('div');
  card.className = 'phase-card' + (collapsed ? ' is-collapsed' : '');
  card.dataset.phaseId = phase.id;
  card.dataset.status  = status;

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
            ${groupBadge}
            ${depName   ? `<span class="badge badge-dep">↳ ${T.after_prefix} ${escHtml(depName)}</span>`   : ''}
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

  return card;
}

/**
 * Re-render the phase list, preserving each card's collapsed/expanded state across refreshes.
 * Renders phase groups first (with member phases nested inside), then standalone phases.
 */
function renderPhases(phases, groups = []) {
  const list = document.getElementById('phasesList');

  // Preserve collapse state across re-renders (phase cards + group cards)
  const wasCollapsed = new Set();
  const wasExpanded  = new Set();
  list.querySelectorAll('.phase-card[data-phase-id]').forEach(c => {
    const pid = parseInt(c.dataset.phaseId);
    (c.classList.contains('is-collapsed') ? wasCollapsed : wasExpanded).add(pid);
  });
  const wasGroupCollapsed = new Set();
  const wasGroupExpanded  = new Set();
  list.querySelectorAll('.phase-group-card[data-group-id]').forEach(c => {
    const gid = parseInt(c.dataset.groupId);
    (c.classList.contains('is-collapsed') ? wasGroupCollapsed : wasGroupExpanded).add(gid);
  });
  const hadState      = wasCollapsed.size + wasExpanded.size > 0;
  const hadGroupState = wasGroupCollapsed.size + wasGroupExpanded.size > 0;

  list.innerHTML = '';

  const allPhases = [...phases, ...groups.flatMap(g => g.phases || [])];

  if (allPhases.length === 0 && groups.length === 0) {
    list.innerHTML = `<div class="item-empty" style="text-align:center;padding:2rem;">${T.no_phases}</div>`;
    return;
  }

  const phaseMap = {};
  allPhases.forEach(p => { phaseMap[p.id] = p.name; });

  // Build milestone map for dependency display
  const msMap = {};
  (state.project.milestones || []).forEach(m => { msMap[m.id] = m.name; });
  allPhases.forEach(p => (p.milestones || []).forEach(m => { msMap[m.id] = m.name; }));

  // ── Render phases conditional on view preference ──
  if (state.phasesView === 'flow') {
    // Sort all phases chronologically
    const sortedPhases = [...allPhases].sort((a, b) => {
      if (a.start_date !== b.start_date) {
        return a.start_date.localeCompare(b.start_date);
      }
      if (a.end_date !== b.end_date) {
        return a.end_date.localeCompare(b.end_date);
      }
      return a.name.localeCompare(b.name);
    });

    sortedPhases.forEach(phase => {
      list.appendChild(buildPhaseCard(phase, phaseMap, msMap, wasCollapsed, wasExpanded, hadState));
    });
  } else {
    // ── Render groups first ──
    groups.forEach(group => {
      const memberPhases = group.phases || [];
      // Compute group date span
      let groupStart = null, groupEnd = null;
      memberPhases.forEach(p => {
        if (!groupStart || p.start_date < groupStart) groupStart = p.start_date;
        if (!groupEnd   || p.end_date   > groupEnd)   groupEnd   = p.end_date;
      });
      const groupStatus = groupStart && groupEnd ? getPhaseStatus(groupStart, groupEnd) : 'upcoming';
      const color       = group.color || '#6366f1';

      // Default: collapse if all members are non-active, or if had previous state honour it
      const groupCollapsed = hadGroupState
        ? !wasGroupExpanded.has(group.id)
        : groupStatus !== 'active';

      const groupCard = document.createElement('div');
      groupCard.className    = 'phase-group-card' + (groupCollapsed ? ' is-collapsed' : '');
      groupCard.dataset.groupId = group.id;
      groupCard.dataset.status  = groupStatus;

      const segCount = memberPhases.length;
      const segLabel = segCount === 1 ? (T.segment_singular || '1 segment') : `${segCount} ${T.segments_plural || 'segments'}`;

      groupCard.innerHTML = `
        <div class="phase-group-card__header" onclick="toggleGroup(${group.id})">
          <svg class="phase-group-card__chevron"><use href="#icon-chevron-down"/></svg>
          <div class="phase-card__color-dot" style="background:${color};--dot-color:${color};margin-top:3px;flex-shrink:0;"></div>
          <div class="phase-group-card__title-area">
            <h3 class="phase-group-card__name">${escHtml(group.name)}</h3>
            <div class="phase-group-card__meta">
              ${statusBadge(groupStatus)}
              ${groupStart ? `<span class="phase-group-card__dates">${fmtDate(groupStart)} → ${fmtDate(groupEnd)}</span>` : ''}
              <span class="badge-group">${segLabel}</span>
            </div>
          </div>
          ${canEdit ? `
          <div class="phase-group-card__actions" onclick="event.stopPropagation()">
            <button class="btn btn-icon btn-ghost" title="${T.tooltip_edit_group || 'Edit group'}" onclick="editGroup(${group.id})">
              <svg><use href="#icon-pencil"/></svg>
            </button>
            <button class="btn btn-icon btn-danger-outline" title="${T.tooltip_delete_group || 'Delete group'}" onclick="confirmDeleteGroup(${group.id}, '${escHtml(group.name).replace(/'/g,"\\'")}')">
              <svg><use href="#icon-trash"/></svg>
            </button>
          </div>` : ''}
        </div>
        <div class="phase-group-card__body" id="group-body-${group.id}"></div>`;

      list.appendChild(groupCard);

      // Render member phases into the group body
      const body = document.getElementById(`group-body-${group.id}`);
      if (memberPhases.length === 0) {
        body.innerHTML = `<p class="phase-group-card__empty">${T.group_empty || 'No phases in this group yet.'}</p>`;
      } else {
        memberPhases.forEach(phase => {
          body.appendChild(buildPhaseCard(phase, phaseMap, msMap, wasCollapsed, wasExpanded, hadState));
        });
      }
    });

    // ── Render standalone phases ──
    phases.forEach(phase => {
      list.appendChild(buildPhaseCard(phase, phaseMap, msMap, wasCollapsed, wasExpanded, hadState));
    });
  }
}

// ── Collaborators ─────────────────────────────────────────────────────────────
/** Fetch and render the collaborator list; hides the Add button for non-owners */
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
            ${c.role === 'owner' ? `
              <span style="font-size:11px;font-weight:700;color:var(--accent);background:var(--accent-muted);padding:.2rem .55rem;border-radius:20px;text-transform:uppercase;letter-spacing:.04em">${T.role_owner || 'Owner'}</span>
            ` : (isOwner ? `
              <select onchange="changeCollaboratorRole(${c.id}, this.value)" style="background:var(--surface-2);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:.2rem .5rem;font-size:12px">
                <option value="viewer" ${c.role==='viewer'?'selected':''}>${T.role_viewer}</option>
                <option value="editor" ${c.role==='editor'?'selected':''}>${T.role_editor}</option>
              </select>` : `<span style="font-size:12px;color:var(--text-muted)">${c.role === 'editor' ? T.role_editor : T.role_viewer}</span>`)}
          </td>
          ${isOwner ? `
          <td style="padding:.65rem .75rem;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.04)">
            ${c.role !== 'owner' ? `
            <button class="btn btn-ghost" style="font-size:12px;padding:.25rem .6rem" onclick="removeCollaborator(${c.id}, '${escHtml(c.name).replace(/'/g,"\\'")}')">
              ${T.revoke}
            </button>` : ''}
          </td>` : ''}
        </tr>
      `).join('')}
    </tbody>
  </table>`;
}

/** Update a collaborator's role via the API and show a toast on success */
async function changeCollaboratorRole(userId, role) {
  await api.updateCollaborator(projectId, userId, { role });
  toast.success(T.toast_collaborator_updated);
}

/** Confirm and remove a collaborator from the project, then re-render the list */
async function removeCollaborator(userId, name) {
  if (!confirm(T.confirm_remove_collaborator.replace('%s', name))) return;
  const res = await api.removeCollaborator(projectId, userId);
  if (res.ok) {
    toast.success(T.toast_collaborator_removed);
    renderCollaborators();
  }
}

/** Open the generic modal to add a collaborator by email and role */
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

/**
 * Render the Frappe Gantt chart for a project.
 * Builds task rows for groups (summary bars), grouped phases, standalone phases,
 * same-day-merged milestones, and events. Wires up drag-to-reschedule with an
 * impact confirmation dialog and click-to-edit handlers.
 */
function renderGantt(project) {
  // Collect all phases: standalone + grouped
  const standalonePhases = (project && project.phases) ? project.phases : [];
  const groups           = (project && project.groups) ? project.groups : [];
  const allPhases        = allPhasesFlat(project);
  const container        = document.querySelector('.gantt-container');

  // Collect all milestones and events
  const allMilestones = [
    ...(project.milestones || []),
    ...allPhases.flatMap(p => p.milestones || []),
  ];
  const allEvents = [
    ...(project.events || []),
    ...allPhases.flatMap(p => p.events || []),
  ];

  if (allPhases.length === 0 && allMilestones.length === 0 && allEvents.length === 0) {
    container.innerHTML = `<div class="item-empty" style="text-align:center;padding:2rem;">${T.no_phases_gantt}</div>`;
    return;
  }

  const styleId = 'gantt-phase-colors';
  let styleTag = document.getElementById(styleId);
  if (!styleTag) { styleTag = document.createElement('style'); styleTag.id = styleId; document.head.appendChild(styleTag); }
  styleTag.textContent = allPhases.map(p =>
    `.gantt .phase-bar-${p.id} .bar { fill: ${p.color || '#6366f1'} !important; }` +
    `.gantt .phase-bar-${p.id} .bar-progress { fill: ${p.color || '#6366f1'} !important; opacity: 0.7; }`
  ).join('\n');

  // Build tasks: groups get a virtual summary bar, then their member phases follow
  const tasks = [];

  // 1. Groups (summary bar + member phases)
  groups.forEach(group => {
    const memberPhases = group.phases || [];
    if (memberPhases.length === 0) return;
    let groupStart = memberPhases[0].start_date, groupEnd = memberPhases[0].end_date;
    memberPhases.forEach(p => {
      if (p.start_date < groupStart) groupStart = p.start_date;
      if (p.end_date   > groupEnd)   groupEnd   = p.end_date;
    });
    // Summary bar — cosmetic only, no interaction
    tasks.push({
      id: 'grp' + group.id,
      name: '▸ ' + group.name,
      start: groupStart,
      end: groupEnd,
      progress: 0,
      dependencies: '',
      custom_class: 'gantt-group-bar',
    });
    // Member phase bars
    memberPhases.forEach(p => {
      let deps = 'grp' + group.id; // visual dependency on group bar
      if (p.depends_on_id) deps = 'p' + p.depends_on_id;
      if (p.depends_on_milestone_id) deps = 'ms' + p.depends_on_milestone_id;
      tasks.push({
        id: 'p' + p.id,
        name: p.name,
        start: p.start_date,
        end: p.end_date,
        progress: getPhaseStatus(p.start_date, p.end_date) === 'past' ? 100 : 0,
        dependencies: deps,
        custom_class: 'phase-bar-' + p.id + ' gantt-grouped-phase',
      });
    });
  });

  // 2. Standalone phases
  standalonePhases.forEach(p => {
    let deps = '';
    if (p.depends_on_id) deps = 'p' + p.depends_on_id;
    if (p.depends_on_milestone_id) deps = 'ms' + p.depends_on_milestone_id;
    tasks.push({
      id: 'p' + p.id,
      name: p.name,
      start: p.start_date,
      end: p.end_date,
      progress: getPhaseStatus(p.start_date, p.end_date) === 'past' ? 100 : 0,
      dependencies: deps,
      custom_class: 'phase-bar-' + p.id,
    });
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

      // Group summary bars are cosmetic — ignore drags on them
      if (task.id.startsWith('grp')) { return revert(); }

      if (task.id.startsWith('p')) {
        const phaseId = parseInt(task.id.slice(1));
        const phase   = allPhasesFlat(state.project).find(p => p.id === phaseId);
        if (!phase) return revert();
        const newStart = dateToYMD(start), newEnd = dateToYMD(end);
        const delta      = Math.round((parseDateLocal(newStart) - parseDateLocal(phase.start_date)) / 86400000);
        if (delta === 0) return;
        const dependents = collectPhaseDependents(phaseId, delta, allPhasesFlat(state.project));
        showImpactConfirm(
          buildImpactHTML(phase.name, delta, dependents),
          async () => {
            const r = await api.updatePhase(phaseId, {
              name: phase.name, description: phase.description,
              start_date: newStart, end_date: newEnd,
              color: phase.color || '#6366f1',
              group_id: phase.group_id ?? null,
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
          ...allPhasesFlat(state.project).flatMap(p => p.milestones || []),
        ];
        const group     = allMs.filter(m => m.target_date === origDate);
        const delta     = Math.round((parseDateLocal(newDate) - parseDateLocal(origDate)) / 86400000);
        // collect phases that depend on any milestone in the group
        const msDeps = group.flatMap(m => {
          return allPhasesFlat(state.project)
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
        const allEvs   = [...(state.project.events || []), ...allPhasesFlat(state.project).flatMap(p => p.events || [])];
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
      if (task.id.startsWith('grp')) {
        // Click on group summary bar — open group edit
        editGroup(parseInt(task.id.slice(3)));

      } else if (task.id.startsWith('p')) {
        editPhase(parseInt(task.id.slice(1)));

      } else if (task.id.startsWith('ms-')) {
        const date = task.id.slice(3);
        const allMs = [
          ...(state.project.milestones || []),
          ...allPhasesFlat(state.project).flatMap(p => p.milestones || []),
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
      // grp clicks already handled above; ignore any other prefixes
    },
  });
  requestAnimationFrame(() => { addGanttDateLabels(tasks); addTodayLine(); });
  } catch (err) {
    console.error('Gantt render failed:', err);
    container.innerHTML = `<div class="item-empty" style="text-align:center;padding:2rem;">${T.gantt_render_error || 'Failed to render timeline. Check the browser console for details.'}</div>`;
  }
}

/** Append SVG text labels showing the date range of each Gantt bar, positioned after the bar-label text */
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
/** Draw a vertical "Today" line and label on the Gantt SVG; falls back to gantt_start math when no today-highlight element is present */
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
/** Switch the active tab panel, update aria-selected on tab buttons, and lazy-render the Gantt or collaborator list if needed */
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

/** Change the Gantt view mode (Day/Week/Month), update the active button, and refresh date labels */
function setGanttView(view) {
  state.ganttView = view;
  document.querySelectorAll('#ganttViewBtns button').forEach(b => b.classList.toggle('active', b.dataset.view === view));
  if (state.ganttInstance) {
    state.ganttInstance.change_view_mode(view);
    requestAnimationFrame(() => { addGanttDateLabels(state.ganttTasks || []); addTodayLine(); });
  }
}

/** Change the Phases view mode (grouped/flow), update the active button, and re-render */
function setPhasesView(view) {
  state.phasesView = view;
  localStorage.setItem('plotly_phases_view', view);
  document.querySelectorAll('#phasesViewBtns button').forEach(b => b.classList.toggle('active', b.dataset.view === view));
  if (state.project) {
    renderPhases(state.project.phases, state.project.groups || []);
  }
}

// ── Subscribe / ICS Modal ─────────────────────────────────────────────────────
/** Open the ICS subscribe modal, pre-filling the per-project calendar URL for the current user's token */
function openSubscribeModal() {
  const url = window.location.origin + '/project/' + projectId + '/calendar.ics?token=' + encodeURIComponent(icsToken);
  document.getElementById('icsUrl').value = url;
  document.getElementById('subscribeModal').classList.add('is-open');
}

/** Close the ICS subscribe modal */
function closeSubscribeModal() {
  document.getElementById('subscribeModal').classList.remove('is-open');
}

/** Copy the ICS feed URL to the clipboard via the Clipboard API, falling back to select-and-notify */
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

/** Remove all is-invalid classes and inline error messages from the generic modal fields */
function clearFieldErrors() {
  document.querySelectorAll('#genericModal .is-invalid').forEach(el => el.classList.remove('is-invalid'));
  document.querySelectorAll('#genericModal .field-error').forEach(el => el.remove());
}

/** Mark a modal field as invalid and append a visible error message below it */
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

/**
 * Open the generic modal with dynamically generated fields.
 * Supports text, date, time, color (with swatch palette), select, textarea, and checkbox field types.
 * @param {string}   title        Modal heading
 * @param {Array}    fields       Field descriptor objects
 * @param {Function} callback     Called when the user clicks Submit
 * @param {string}   [submitLabel] Label for the submit button (default: 'Save')
 * @param {string}   [subtitle]   Optional explanatory subtitle under modal heading
 */
function showModal(title, fields, callback, submitLabel = 'Save', subtitle = '') {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalSubmitBtn').textContent = submitLabel;
  const subEl = document.getElementById('modalSubtitle');
  if (subEl) {
    if (subtitle) {
      subEl.textContent = subtitle;
      subEl.style.display = 'block';
    } else {
      subEl.style.display = 'none';
    }
  }
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

/** Open the Structure Guide modal */
function openHelpModal() {
  document.getElementById('helpModal').classList.add('is-open');
}

/** Close the Structure Guide modal */
function closeHelpModal() {
  document.getElementById('helpModal').classList.remove('is-open');
}

/** Render the HTML for a single modal field descriptor; used by showModal to populate the fields container */
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
    const renderOpt = o =>
      `<option value="${escHtml(String(o.value))}"${String(o.value) === (f.defaultValue ?? '') ? ' selected' : ''}>${escHtml(o.text)}</option>`;
    const isGrouped = (f.options || []).length > 0 && f.options[0].options !== undefined;
    const opts = isGrouped
      ? f.options.map(g =>
          `<optgroup label="${escHtml(g.label)}">${g.options.map(renderOpt).join('')}</optgroup>`
        ).join('')
      : (f.options || []).map(renderOpt).join('');
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

/** Close the generic modal and clear the pending submit callback */
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

/**
 * Open the confirmation modal with a "Delete" OK button.
 * @param {string}   message    Body text shown to the user
 * @param {Function} onConfirm  Called when the user clicks Delete
 * @param {string}   [title]    Modal heading; defaults to T.modal_confirm_deletion
 */
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

/**
 * Open the confirmation modal with an "Apply" OK button for Gantt drag-reschedule previews.
 * onCancel is invoked when the modal is dismissed without confirming (reverts the drag).
 */
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

/** Close the confirmation modal and invoke the cancel callback (if any) to revert tentative changes */
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

// ── Phase / group collapse / expand ──────────────────────────────────────────
/** Toggle the collapsed/expanded state of a phase card and update its toggle-area title */
function togglePhase(phaseId) {
  const card = document.querySelector(`.phase-card[data-phase-id="${phaseId}"]`);
  if (!card) return;
  const collapsing = !card.classList.contains('is-collapsed');
  card.classList.toggle('is-collapsed', collapsing);
  const toggleArea = card.querySelector('.phase-card__toggle-area');
  if (toggleArea) toggleArea.title = collapsing ? T.expand_phase : T.collapse_phase;
}

/** Toggle the collapsed/expanded state of a phase group card */
function toggleGroup(groupId) {
  const card = document.querySelector(`.phase-group-card[data-group-id="${groupId}"]`);
  if (!card) return;
  card.classList.toggle('is-collapsed');
}

// ── Phase Group Actions ───────────────────────────────────────────────────────
/** Open the modal to create a new phase group for this project */
function addGroup() {
  showModal(T.modal_add_group || 'Add Group', [
    { id: 'name',  label: T.group_name  || 'Group name',   type: 'text' },
    { id: 'desc',  label: T.description,                   type: 'textarea' },
    { id: 'color', label: T.color,                         type: 'color', defaultValue: '#6366f1' },
  ], async () => {
    const name  = document.getElementById('modal_input_name').value.trim();
    const desc  = document.getElementById('modal_input_desc').value.trim();
    const color = document.getElementById('modal_input_color').value;
    clearFieldErrors();
    if (!name) { setFieldError('name', T.error_name_required); return; }
    const btn = document.getElementById('modalSubmitBtn');
    btn.disabled = true;
    try {
      const resp = await api.createPhaseGroup(projectId, { name, description: desc || null, color });
      if (resp.ok) { toast.success(T.toast_group_added || 'Group added'); closeModal(); await refresh(); }
      else toast.error(T.toast_group_add_failed || 'Failed to add group');
    } finally { btn.disabled = false; }
  }, T.modal_add_group || 'Add Group', T.modal_add_group_sub || '');
}

/** Open the modal to edit an existing phase group's name, description, and colour */
function editGroup(groupId) {
  const group = (state.project.groups || []).find(g => g.id === groupId);
  if (!group) return;
  showModal(T.modal_edit_group || 'Edit Group', [
    { id: 'name',  label: T.group_name  || 'Group name',   type: 'text',     defaultValue: group.name },
    { id: 'desc',  label: T.description,                   type: 'textarea', defaultValue: group.description || '' },
    { id: 'color', label: T.color,                         type: 'color',    defaultValue: group.color || '#6366f1' },
  ], async () => {
    const name  = document.getElementById('modal_input_name').value.trim();
    const desc  = document.getElementById('modal_input_desc').value.trim();
    const color = document.getElementById('modal_input_color').value;
    clearFieldErrors();
    if (!name) { setFieldError('name', T.error_name_required); return; }
    const btn = document.getElementById('modalSubmitBtn');
    btn.disabled = true;
    try {
      const resp = await api.updatePhaseGroup(groupId, { name, description: desc || null, color });
      if (resp.ok) { toast.success(T.toast_group_updated || 'Group updated'); closeModal(); await refresh(); }
      else toast.error(T.toast_group_update_failed || 'Failed to update group');
    } finally { btn.disabled = false; }
  }, T.save_changes);
}

/** Confirm and delete a phase group; member phases become standalone (not deleted) */
function confirmDeleteGroup(groupId, name) {
  const msg = (T.confirm_delete_group || 'Delete group "%s"? Member phases will become standalone.')
    .replace('%s', name);
  showConfirm(msg, async () => {
    const resp = await api.deletePhaseGroup(groupId);
    if (resp.ok) { toast.success(T.toast_group_deleted || 'Group deleted'); await refresh(); }
    else toast.error(T.toast_group_delete_failed || 'Failed to delete group');
  });
}

// ── Phase Actions ─────────────────────────────────────────────────────────────
/** Open the modal to add a new phase to this project */
function addPhase() {
  const groups = state.project.groups || [];
  const groupFields = groups.length > 0 ? [
    { id: 'group', label: T.group_label || 'Group', type: 'select',
      options: groups.map(g => ({ value: String(g.id), text: g.name })) },
  ] : [];
  showModal(T.modal_add_phase, [
    { id: 'name',  label: T.phase_name,   type: 'text' },
    { id: 'desc',  label: T.description,  type: 'textarea' },
    { id: 'start', label: T.start_date,   type: 'date', defaultValue: todayStr() },
    { id: 'end',   label: T.end_date,     type: 'date', defaultValue: todayStr() },
    { id: 'color', label: T.color,        type: 'color', defaultValue: '#6366f1' },
    ...groupFields,
  ], async () => {
    const name  = document.getElementById('modal_input_name').value.trim();
    const desc  = document.getElementById('modal_input_desc').value.trim();
    const start = document.getElementById('modal_input_start').value;
    const end   = document.getElementById('modal_input_end').value;
    const color = document.getElementById('modal_input_color').value;
    const groupEl = document.getElementById('modal_input_group');
    const group_id = groupEl && groupEl.value ? parseInt(groupEl.value) : null;
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
      const resp = await api.createPhase(projectId, { name, description: desc || null, start_date: start, end_date: end, color, group_id });
      if (resp.ok) { toast.success(T.toast_phase_added); closeModal(); await refresh(); }
      else toast.error(T.toast_phase_add_failed);
    } finally { btn.disabled = false; }
  }, T.modal_add_phase, T.modal_add_phase_sub || '');
}

/**
 * Open the modal to edit an existing phase.
 * Automatically shifts the end date whenever start date changes, preserving the original duration.
 * If the project has groups, includes a group assignment select field.
 */
function editPhase(phaseId) {
  const phase = allPhasesFlat(state.project).find(p => p.id === phaseId);
  if (!phase) return;
  const groups = state.project.groups || [];
  const groupFields = groups.length > 0 ? [
    { id: 'group', label: T.group_label || 'Group', type: 'select',
      defaultValue: phase.group_id ? String(phase.group_id) : '',
      options: groups.map(g => ({ value: String(g.id), text: g.name })) },
  ] : [];
  showModal(T.modal_edit_phase, [
    { id: 'name',  label: T.phase_name,   type: 'text',     defaultValue: phase.name },
    { id: 'desc',  label: T.description,  type: 'textarea', defaultValue: phase.description || '' },
    { id: 'start', label: T.start_date,   type: 'date',     defaultValue: phase.start_date },
    { id: 'end',   label: T.end_date,     type: 'date',     defaultValue: phase.end_date },
    { id: 'color', label: T.color,        type: 'color',    defaultValue: phase.color || '#6366f1' },
    ...groupFields,
  ], async () => {
    const name  = document.getElementById('modal_input_name').value.trim();
    const desc  = document.getElementById('modal_input_desc').value.trim();
    const start = document.getElementById('modal_input_start').value;
    const end   = document.getElementById('modal_input_end').value;
    const color = document.getElementById('modal_input_color').value;
    const groupEl = document.getElementById('modal_input_group');
    const group_id = groupEl && groupEl.value ? parseInt(groupEl.value) : null;
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
        group_id,
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

/** Open a modal to set or clear the depends_on_id / depends_on_milestone_id for a phase */
function setDependency(phaseId) {
  const groups     = state.project.groups || [];
  const standalone = (state.project.phases || []).filter(p => p.id !== phaseId);
  const toPhaseOpt = p => ({ value: 'phase:' + p.id, text: p.name });
  const toMsOpt    = m => ({ value: 'ms:' + m.id, text: m.name + ' (' + fmtDate(m.target_date) + ')' });

  const opts = [];
  if (standalone.length) {
    const pOpts = standalone.map(toPhaseOpt);
    const mOpts = standalone.flatMap(p => (p.milestones || []).map(toMsOpt));
    const projectMs = (state.project.milestones || []).map(toMsOpt);
    opts.push({ label: T.group_standalone || 'Standalone', options: [...pOpts, ...mOpts, ...projectMs] });
  } else {
    const projectMs = (state.project.milestones || []).map(toMsOpt);
    if (projectMs.length) opts.push({ label: T.group_standalone || 'Standalone', options: projectMs });
  }
  for (const g of groups) {
    const pOpts = (g.phases || []).filter(p => p.id !== phaseId).map(toPhaseOpt);
    const mOpts = (g.phases || []).flatMap(p => (p.milestones || []).map(toMsOpt));
    if (pOpts.length || mOpts.length) opts.push({ label: g.name, options: [...pOpts, ...mOpts] });
  }
  const phase = allPhasesFlat(state.project).find(p => p.id === phaseId);
  const currentVal = phase.depends_on_milestone_id
    ? 'ms:' + phase.depends_on_milestone_id
    : (phase.depends_on_id ? 'phase:' + phase.depends_on_id : '');
  showModal(T.modal_set_dependency, [
    { id: 'target', label: T.dependency_label, type: 'select', options: opts, defaultValue: currentVal },
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
    if (resp.ok) { toast.success(T.toast_dep_updated); closeModal(); await refresh(); }
    else toast.error(T.toast_dep_update_failed);
  }, 'Save');
}

/** Confirm and delete a phase (and its milestones/events via server-side cascade) */
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

/** Get smart default date for a milestone (latest milestone/event date in phase/project, or phase start_date, or today) */
function getMilestoneDefaultDate(phaseId = null) {
  if (phaseId && state.project) {
    const phase = allPhasesFlat(state.project).find(p => p.id === phaseId);
    if (phase) {
      let latest = null;
      (phase.milestones || []).forEach(m => {
        if (m.target_date && (!latest || m.target_date > latest)) latest = m.target_date;
      });
      (phase.events || []).forEach(e => {
        const d = e.end_date || e.start_date;
        if (d && (!latest || d > latest)) latest = d;
      });
      if (latest) return latest;
      if (phase.start_date) return phase.start_date;
    }
  } else if (state.project) {
    let latest = null;
    (state.project.milestones || []).forEach(m => {
      if (m.target_date && (!latest || m.target_date > latest)) latest = m.target_date;
    });
    (state.project.events || []).forEach(e => {
      const d = e.end_date || e.start_date;
      if (d && (!latest || d > latest)) latest = d;
    });
    if (latest) return latest;
  }
  return todayStr();
}

/** Get smart default date for an event (latest event/milestone date in phase/project, or phase start_date, or today) */
function getEventDefaultDate(phaseId = null) {
  if (phaseId && state.project) {
    const phase = allPhasesFlat(state.project).find(p => p.id === phaseId);
    if (phase) {
      let latest = null;
      (phase.events || []).forEach(e => {
        const d = e.end_date || e.start_date;
        if (d && (!latest || d > latest)) latest = d;
      });
      (phase.milestones || []).forEach(m => {
        if (m.target_date && (!latest || m.target_date > latest)) latest = m.target_date;
      });
      if (latest) return latest;
      if (phase.start_date) return phase.start_date;
    }
  } else if (state.project) {
    let latest = null;
    (state.project.events || []).forEach(e => {
      const d = e.end_date || e.start_date;
      if (d && (!latest || d > latest)) latest = d;
    });
    (state.project.milestones || []).forEach(m => {
      if (m.target_date && (!latest || m.target_date > latest)) latest = m.target_date;
    });
    if (latest) return latest;
  }
  return todayStr();
}

// ── Project-level Actions ─────────────────────────────────────────────────────
/** Open the modal to add a project-level milestone (not attached to any phase) */
function addProjectMilestone() {
  showModal(T.modal_add_project_milestone, [
    { id: 'name',   label: T.milestone_name, type: 'text' },
    { id: 'target', label: T.target_date,    type: 'date', defaultValue: getMilestoneDefaultDate(null) },
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
  }, T.modal_add_milestone, T.modal_add_milestone_sub || '');
}

/** Open the modal to add a project-level event (not attached to any phase) */
function addProjectEvent() {
  const defDate = getEventDefaultDate(null);
  _openEventModal(T.modal_add_project_event, { start_date: defDate, end_date: defDate }, async (data) => {
    const resp = await api.createProjectEvent(projectId, data);
    if (resp.ok) { toast.success(T.toast_event_added); closeModal(); await refresh(); }
    else toast.error(T.toast_event_add_failed);
  }, T.modal_add_event, T.modal_add_event_sub || '');
}

// ── Milestone Actions ─────────────────────────────────────────────────────────
/** Open the modal to add a milestone attached to a specific phase */
function addMilestone(phaseId) {
  showModal(T.modal_add_milestone, [
    { id: 'name',   label: T.milestone_name, type: 'text' },
    { id: 'target', label: T.target_date,    type: 'date', defaultValue: getMilestoneDefaultDate(phaseId) },
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
  }, T.modal_add_milestone, T.modal_add_milestone_sub || '');
}

/** Open the modal to rename a milestone and/or change its target date */
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

/** Confirm and delete a milestone */
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

/** Shared event modal builder with all-day / time toggle. Also auto-shifts end date when start date changes. */
function _openEventModal(title, defaults, onSave, submitLabel, subtitle = '') {
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
  }, submitLabel, subtitle);

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

/** Open the event modal to add a new event attached to a specific phase */
function addEvent(phaseId) {
  const defDate = getEventDefaultDate(phaseId);
  _openEventModal(T.modal_add_event, { start_date: defDate, end_date: defDate }, async (data) => {
    const resp = await api.createEvent(phaseId, data);
    if (resp.ok) { toast.success(T.toast_event_added); closeModal(); await refresh(); }
    else toast.error(T.toast_event_add_failed);
  }, T.modal_add_event, T.modal_add_event_sub || '');
}

/** Open the event modal pre-filled with the existing event data for editing */
function editEvent(evId) {
  const allEvents = [
    ...(state.project.events || []),
    ...allPhasesFlat(state.project).flatMap(p => p.events || []),
  ];
  const ev = allEvents.find(e => e.id === evId);
  if (!ev) return;
  _openEventModal(T.modal_edit_event, ev, async (data) => {
    const resp = await api.updateEvent(evId, data);
    if (resp.ok) { toast.success(T.toast_event_updated); closeModal(); await refresh(); }
    else toast.error(T.toast_event_update_failed);
  }, T.save_changes);
}

/** Confirm and delete an event */
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
/** Open the modal to edit this project's name and description */
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

/** Confirm and delete this project, then redirect to the dashboard */
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
  if (e.key === 'Escape') { closeModal(); closeConfirm(); closeSubscribeModal(); closeHelpModal(); }
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
document.getElementById('helpModal')?.addEventListener('click', e => {
  if (e.target === e.currentTarget) closeHelpModal();
});

// ── Init ──────────────────────────────────────────────────────────────────────
/** Fetch the current project from the API and re-render the entire page */
async function refresh() {
  try {
    state.project = await api.getProject(projectId);
    renderProject(state.project);
  } catch (err) {
    toast.error(T.toast_load_failed);
  }
}

refresh();
