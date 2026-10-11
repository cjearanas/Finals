<?php
/**
 * Notification bell endpoints.
 *   notifications.php?json=1   list + unread count (the bell refreshes from this)
 *   notifications.php?go=ID    mark one as read, then open the report it is about
 *   POST action=read_all       mark everything as read
 */
require_once __DIR__ . '/includes/data.php';
require_login();
$me = current_user();
$uid = (int)$me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (csrf_valid($_POST['csrf'] ?? null) && ($_POST['action'] ?? '') === 'read_all') {
        notifications_mark($uid);
        echo json_encode(['ok' => true, 'unread' => 0]);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false]);
    }
    exit;
}

if (isset($_GET['go'])) {
    $code = notifications_mark($uid, (int)$_GET['go']);
    header('Location: ' . ($code !== '' ? 'maintenance-log.php?q=' . rawurlencode($code) : 'dashboard.php'));
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-store');
$items = array_map(fn($n) => [
    'id'   => (int)$n['id'],
    'text' => $n['text'],
    'icon' => $n['icon'] ?? 'bell',
    'time' => time_ago($n['created']),
    'read' => !empty($n['read']),
], notifications_for($uid, 15));
echo json_encode(['unread' => unread_count($uid), 'items' => $items]);
