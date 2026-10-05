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

    if ($report && isset(STATUSES[$status])) {
        $prev     = $report['assigned_to'] ?? null;
        $raw      = (string)($_POST['assigned_to'] ?? '');
        $assignee = $raw === '' ? null : (int)$raw;
        $valid    = $assignee === null || $assignee === $prev;              // unchanged is always fine

        if (!$valid) {
            $person = user_find($assignee);
            $valid  = $person && $person['active'] && in_array($person['role'], ROLES_STAFF, true);
        }
        if ($valid && update_report($code, $status, ['assignee' => $assignee])) {
            $done = true;
            if ($assignee !== $prev) {
                $msg = $assignee === null
                    ? 'Assignment removed from ' . $code . '.'
                    : $code . ' was assigned to ' . full_name(user_find($assignee)) . '. Waiting for them to accept the task.';
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