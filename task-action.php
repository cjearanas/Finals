<?php
/**
 * Task workflow buttons from the Report details popup:
 *   staff: accept / decline an assigned task, take (ask for) an open task, cancel_request
 *   admin: approve_request / reject_request
 */
require_once __DIR__ . '/includes/data.php';
require_login();

if (!is_admin() && !is_staff()) {
    header('Location: dashboard.php');
    exit;
}
$me   = current_user();
$back = ($_POST['back'] ?? '') === 'dashboard.php' ? 'dashboard.php' : 'maintenance-log.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid($_POST['csrf'] ?? null)) {
    $msg = report_workflow((string)($_POST['code'] ?? ''), (string)($_POST['action'] ?? ''), $me['id'], is_admin());
    flash_set($msg ? 'success' : 'warning', $msg ?? 'That action is no longer available for this task.');
}

header('Location: ' . $back);
exit;
