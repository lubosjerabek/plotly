<?php
// English translations
return [
    // ── Meta / page titles ─────────────────────────────────────
    'app_name'              => 'Plotly',
    'page_title_login'      => 'Plotly — Sign In',
    'page_title_projects'   => 'Plotly — Project Manager',
    'page_title_project'    => 'Project — Plotly',

    // ── Auth ───────────────────────────────────────────────────
    'sign_in'               => 'Sign In',
    'sign_out'              => 'Sign out',
    'sign_in_subtitle'      => 'Sign in to continue',
    'username'              => 'Username',
    'password'              => 'Password',

    // ── General buttons ────────────────────────────────────────
    'cancel'                => 'Cancel',
    'close'                 => 'Close',
    'save'                  => 'Save',
    'save_changes'          => 'Save Changes',
    'delete'                => 'Delete',
    'edit'                  => 'Edit',
    'apply'                 => 'Apply',
    'copy'                  => 'Copy',
    'create'                => 'Create',
    'submit'                => 'Submit',
    'add'                   => '+ Add',
    'subscribe'             => 'Subscribe',

    // ── Projects page ──────────────────────────────────────────
    'projects'              => 'Projects',
    'new_project'           => 'New Project',
    'no_projects_title'     => 'No projects yet',
    'no_projects_body'      => 'Create your first project to get started tracking phases and milestones.',
    'no_description'        => 'No description',
    'project_name'          => 'Project Name',
    'description'           => 'Description',
    'description_optional'  => 'Optional description',
    'project_name_example'  => 'e.g. Website Redesign',
    'confirm_deletion'      => 'Confirm Deletion',
    'confirm'               => 'Confirm',
    'open_arrow'            => 'Open →',

    // ── Toast messages — projects ──────────────────────────────
    'toast_project_created'         => 'Project created',
    'toast_project_updated'         => 'Project updated',
    'toast_project_deleted'         => 'Project deleted',
    'toast_project_delete_failed'   => 'Failed to delete project',
    'toast_something_wrong'         => 'Something went wrong',

    // ── Project page ───────────────────────────────────────────
    'dashboard'             => '← Dashboard',
    'phases_tab'            => 'Phases',
    'timeline_tab'          => 'Timeline',
    'add_phase'             => 'Add Phase',
    'project_wide'          => 'Project-wide',
    'no_description_provided' => 'No description provided.',
    'gantt_view_label'      => 'View:',
    'gantt_day'             => 'Day',
    'gantt_week'            => 'Week',
    'gantt_month'           => 'Month',

    // ── Phase card / phases panel ──────────────────────────────
    'milestones'            => 'Milestones',
    'events'                => 'Events',
    'none'                  => 'None',
    'no_phases'             => 'No phases yet. Add your first phase to get started.',
    'no_phases_gantt'       => 'No phases to display.',
    'status_active'         => 'Active',
    'status_upcoming'       => 'Upcoming',
    'status_past'           => 'Past',
    'after_prefix'          => 'After',
    'expand_phase'          => 'Expand phase',
    'collapse_phase'        => 'Collapse phase',

    // ── Phase labels in Gantt / dependency ─────────────────────
    'milestones_on'         => 'milestones on',  // e.g. "3 milestones on Jan 1, 2026"

    // ── Fields ─────────────────────────────────────────────────
    'phase_name'            => 'Phase Name',
    'milestone_name'        => 'Milestone Name',
    'milestone_name_label'  => 'Milestone Name',
    'event_name'            => 'Event Name',
    'target_date'           => 'Target Date',
    'start_date'            => 'Start Date',
    'end_date'              => 'End Date',
    'color'                 => 'Color',
    'dependency_label'      => 'This phase starts after…',
    'select_milestone'      => 'Select milestone',

    // ── Modal titles ───────────────────────────────────────────
    'modal_add_phase'               => 'Add Phase',
    'modal_edit_phase'              => 'Edit Phase',
    'modal_set_dependency'          => 'Set Phase Dependency',
    'modal_add_milestone'           => 'Add Milestone',
    'modal_add_project_milestone'   => 'Add Project Milestone',
    'modal_edit_milestone'          => 'Edit Milestone',   // followed by ": {name}"
    'modal_add_event'               => 'Add Event',
    'modal_add_project_event'       => 'Add Project Event',
    'modal_edit_event'              => 'Edit Event',
    'modal_edit_project'            => 'Edit Project',
    'modal_confirm_change'          => 'Confirm Change',
    'modal_confirm_deletion'        => 'Confirm Deletion',
    'modal_edit_milestone_label'    => 'Edit Milestone',

    // ── Confirmation messages ──────────────────────────────────
    'confirm_delete_project'         => 'Delete this entire project and all its phases? This cannot be undone.',
    'confirm_delete_phase'           => 'Delete phase "%s" and all its milestones and events? This cannot be undone.',
    'confirm_delete_milestone'       => 'Delete milestone "%s"?',
    'confirm_delete_event'           => 'Delete event "%s"?',
    'confirm_delete_project_index'   => 'Delete "%s" and all its phases? This cannot be undone.',

    // ── Impact / dependency dialog ─────────────────────────────
    'impact_shifts'         => 'shifts',
    'impact_day'            => 'day',
    'impact_days'           => 'days',
    'impact_also_shifts'    => 'Also shifts',
    'impact_dependent'      => 'dependent phase',
    'impact_dependents'     => 'dependent phases',

    // ── Toast messages — phases ────────────────────────────────
    'toast_phase_added'             => 'Phase added',
    'toast_phase_updated'           => 'Phase updated',
    'toast_phase_deleted'           => 'Phase deleted',
    'toast_phase_add_failed'        => 'Failed to add phase',
    'toast_phase_update_failed'     => 'Failed to update phase',
    'toast_phase_delete_failed'     => 'Failed to delete phase',
    'toast_dep_updated'             => 'Dependency updated',
    'toast_dep_update_failed'       => 'Failed to update dependency',
    'toast_save_failed'             => 'Failed to save',

    // ── Toast messages — milestones ────────────────────────────
    'toast_milestone_added'         => 'Milestone added',
    'toast_milestone_updated'       => 'Milestone updated',
    'toast_milestone_deleted'       => 'Milestone deleted',
    'toast_milestone_add_failed'    => 'Failed to add milestone',
    'toast_milestone_update_failed' => 'Failed to update milestone',
    'toast_milestone_delete_failed' => 'Failed to delete milestone',

    // ── Toast messages — events ────────────────────────────────
    'toast_event_added'             => 'Event added',
    'toast_event_updated'           => 'Event updated',
    'toast_event_deleted'           => 'Event deleted',
    'toast_event_add_failed'        => 'Failed to add event',
    'toast_event_update_failed'     => 'Failed to update event',
    'toast_event_delete_failed'     => 'Failed to delete event',

    // ── Toast messages — misc ──────────────────────────────────
    'toast_load_failed'     => 'Failed to load project',
    'toast_url_copied'      => 'URL copied to clipboard',
    'toast_copy_manual'     => 'Select all and copy manually',

    // ── Subscribe / ICS modal ──────────────────────────────────
    'subscribe_title'       => 'Subscribe to Calendar',
    'ics_feed_label'        => 'ICS Feed URL (this project)',
    'subscribe_instructions' => 'In Google Calendar: <strong>Other calendars → From URL</strong> → paste the URL above → <strong>Add calendar</strong>.<br>The feed refreshes automatically. Changes you make here appear in Google Calendar within a few hours.',

    // ── Date / count plurals ───────────────────────────────────
    'months'                => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    'n_projects'            => '%d project',       // singular
    'n_projects_plural'     => '%d projects',      // plural
    'no_projects_count'     => 'No projects yet',
    'n_phases'              => '%d phase',
    'n_phases_plural'       => '%d phases',

    // ── Tooltips ───────────────────────────────────────────────
    'tooltip_edit_project'        => 'Edit project',
    'tooltip_delete_project'      => 'Delete project',
    'tooltip_edit_phase'          => 'Edit phase',
    'tooltip_set_dependency'      => 'Set dependency',
    'tooltip_delete_phase'        => 'Delete phase',
    'tooltip_edit_milestone'      => 'Edit milestone date',
    'tooltip_delete_milestone'    => 'Delete milestone',
    'tooltip_delete_event'        => 'Delete event',
    'tooltip_copy_url'            => 'Copy URL',

    // ── Language switcher ──────────────────────────────────────
    'lang_en'               => 'EN',
    'lang_cs'               => 'CS',
    'lang_label'            => 'Language',
];
