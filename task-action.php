<?php
/**
 * Task workflow buttons from the Report details popup:
 *   staff: take (ask for) an open task, cancel_request, request_resolve
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
    $error  = null;
    $action = (string)($_POST['action'] ?? '');
    $proof  = [];
    $remarks = trim((string)($_POST['remarks'] ?? ''));
    $remarks = function_exists('mb_substr') ? mb_substr($remarks, 0, 1000) : substr($remarks, 0, 1000);

    // Marking a job resolved needs a photo or video of the finished work
    if ($action === 'request_resolve') {
        $upErrors = [];
        $proof    = handle_uploads($_FILES['proof'] ?? [], $upErrors);
        if ($upErrors) {
            $error = implode(' ', $upErrors);
        } elseif ($remarks === '') {
            $error = 'Add your remarks about the work done before marking the job as resolved.';
        }
    }
    $msg = $error ? null : report_workflow((string)($_POST['code'] ?? ''), $action, $me['id'], is_admin(), $error, $proof, $remarks);
    if ($msg === null && $proof) {                       // action refused: don't keep the uploaded files
        foreach ($proof as $pf) {
            @unlink(__DIR__ . '/uploads/' . $pf['file']);
        }
    }
    flash_set($msg ? 'success' : 'warning', $msg ?? $error ?? 'That action is no longer available for this task.');
}

header('Location: ' . $back);
exit;