<?php
/** Admin: change a report's status and assign the work to a maintenance staff member. */
require_once __DIR__ . '/includes/data.php';
require_admin();

// Only go back to a known page
$back = ($_POST['back'] ?? '') === 'dashboard.php' ? 'dashboard.php' : 'maintenance-log.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid($_POST['csrf'] ?? null)) {
    $status = (string)($_POST['status'] ?? '');
    $code   = (string)($_POST['code'] ?? '');
    $report = report_find($code);
    $done   = false;

    // A job becomes Resolved only when the maintenance staff submit the work and the admin confirms it
    if ($status === 'Resolved' && ($report['status'] ?? '') !== 'Resolved') {
        flash_set('warning', 'A task can only be resolved after the maintenance staff submit the finished work. Use "Confirm resolved" on the task.');
        header('Location: ' . $back);
        exit;
    }

    if ($report && isset(STATUSES[$status])) {
        $prev     = $report['assigned_to'] ?? null;
        $raw      = (string)($_POST['assigned_to'] ?? '');
        $assignee = $raw === '' ? null : (int)$raw;
        $valid    = $assignee === null || $assignee === $prev;              // unchanged is always fine

        $full = false;
        if (!$valid) {
            $person = user_find($assignee);
            $valid  = $person && $person['active'] && in_array($person['role'], ROLES_STAFF, true);

            // a staff member can hold only MAX_ACTIVE_JOBS unresolved jobs at a time
            if ($valid && $status !== 'Resolved' && active_jobs($assignee, null, $code) >= MAX_ACTIVE_JOBS) {
                $valid = false;
                $full  = true;
                flash_set('warning', full_name($person) . ' already has ' . MAX_ACTIVE_JOBS
                    . ' unresolved jobs. Choose another staff member, or wait until one of those jobs is resolved.');
                $done = true;                                              // message already set
            }
        }
        if ($valid && update_report($code, $status, ['assignee' => $assignee])) {
            $done = true;
            if ($assignee !== $prev) {
                $msg = $assignee === null
                    ? 'Assignment removed from ' . $code . '.'
                    : $code . ' was assigned to ' . full_name(user_find($assignee)) . ' and it is now In Progress.';
            } else {
                $msg = $code . ' was updated.';
            }
            flash_set('success', $msg);
        }
    }
    if (!$done) {
        flash_set('warning', 'That change could not be saved.');
    }
}

header('Location: ' . $back);
exit;