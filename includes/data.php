<?php
/**
 * Data layer + helpers.
 * Reports are stored in the PHP session for now (no database needed).
 * To move to MySQL, replace get_reports() and add_report() with PDO queries.
 */
session_start();
date_default_timezone_set('Asia/Manila');

const STATUSES = [
    'Under Review' => ['badge' => 'secondary', 'tab' => 'review',   'icon' => 'bi-bell'],
    'In Progress'  => ['badge' => 'warning',   'tab' => 'progress', 'icon' => 'bi-arrow-repeat'],
    'Resolved'     => ['badge' => 'success',   'tab' => 'resolved', 'icon' => 'bi-check-circle'],
];

/** Building => Floor => Rooms.*/
function campus_layout(): array
{
    $floors = ['1st Floor' => 1, '2nd Floor' => 2, '3rd Floor' => 3];
    $layout = [];
    foreach (['Main Building', 'Academic Building', 'Science Building'] as $building) {
        foreach ($floors as $floor => $n) {
            $rooms = [];
            for ($i = 1; $i <= 5; $i++) {
                $rooms[] = 'Room ' . $n . '0' . $i;
            }
            $rooms[] = 'Comfort Room';
            $layout[$building][$floor] = $rooms;
        }
    }
    return $layout;
}

/** Concern type => Items. */
function concern_items(): array
{
    return [
        'Air Conditioning' => ['Air Conditioner Unit', 'Remote Control', 'Thermostat', 'Vent / Duct', 'Other'],
        'Leaking Pipe'     => ['Faucet', 'Toilet', 'Sink', 'Water Pipe', 'Drainage', 'Other'],
        'Broken Window'    => ['Window Glass', 'Window Frame', 'Window Lock', 'Curtain / Blinds', 'Other'],
        'Electrical'       => ['Light Fixture', 'Power Outlet', 'Switch', 'Electric Fan', 'Projector', 'Circuit Breaker', 'Other'],
        'Furniture'        => ['Chair', 'Desk', 'Whiteboard', 'Cabinet', 'Door', 'Other'],
        'Other'            => ['Other'],
    ];
}

/** Upload rules: images and videos only, 50MB each */
const UPLOAD_MAX = 50 * 1024 * 1024;
const ALLOWED_MIME = [
    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
    'video/mp4'  => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
];

if (!isset($_SESSION['reports'])) {
    $_SESSION['reports'] = [
        ['code' => 'RPT-003', 'type' => 'Air Conditioning', 'item' => 'Air Conditioner Unit',
         'building' => 'Academic Building', 'floor' => '3rd Floor', 'room' => 'Room 302',
         'location' => 'Room 302, 3rd Floor, Academic Building',
         'status' => 'In Progress',  'date' => '2026-09-28', 'filed' => '2026-09-28', 'count' => 1, 'files' => []],
        ['code' => 'RPT-002', 'type' => 'Leaking Pipe', 'item' => 'Faucet',
         'building' => 'Science Building', 'floor' => '1st Floor', 'room' => 'Comfort Room',
         'location' => 'Comfort Room, 1st Floor, Science Building',
         'status' => 'Under Review', 'date' => '2026-09-25', 'filed' => '2026-09-25', 'count' => 1, 'files' => []],
        ['code' => 'RPT-001', 'type' => 'Broken Window', 'item' => 'Window Glass',
         'building' => 'Main Building', 'floor' => '1st Floor', 'room' => 'Room 101',
         'location' => 'Room 101, Main Building',
         'status' => 'Resolved',     'date' => '2026-09-20', 'filed' => '2026-09-20', 'count' => 1, 'files' => []],
    ];
    $_SESSION['report_seq'] = 3;
}

$_SESSION['report_seq'] = $_SESSION['report_seq'] ?? 0;
for ($__i = count($_SESSION['reports']) - 1; $__i >= 0; $__i--) {   // oldest first
    $__r =& $_SESSION['reports'][$__i];
    if (empty($__r['code'])) {
        $__r['code'] = sprintf('RPT-%03d', ++$_SESSION['report_seq']);
    }
    if (empty($__r['filed'])) {
        $__r['filed'] = $__r['date'];
    }
    unset($__r);
}

/** Escape output */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** All reports, newest first */
function get_reports(?string $status = null): array
{
    $reports = $_SESSION['reports'];
    // Priority order: unresolved first, then most-reported, then most recent
    usort($reports, function ($a, $b) {
        return (($a['status'] === 'Resolved') <=> ($b['status'] === 'Resolved'))
            ?: (($b['count'] ?? 1) <=> ($a['count'] ?? 1))
            ?: strcmp($b['date'], $a['date']);
    });
    if ($status !== null) {
        $reports = array_values(array_filter($reports, fn($r) => $r['status'] === $status));
    }
    return $reports;
}

function count_reports(?string $status = null): int
{
    return count(get_reports($status));
}

/**
 * Add a report. If the same issue (building, floor, room, concern type, item) is
 * already open (Under Review / In Progress), the report is merged into it and its
 * count goes up instead of creating a duplicate.
 * Returns the report count for that issue (1 = brand new).
 */
function add_report(string $type, string $item, string $building, string $floor, string $room,
                    string $description = '', array $files = []): int
{
    foreach ($_SESSION['reports'] as &$r) {
        if (($r['status'] ?? '') !== 'Resolved'
            && ($r['building'] ?? null) === $building
            && ($r['floor'] ?? null)    === $floor
            && ($r['room'] ?? null)     === $room
            && ($r['type'] ?? null)     === $type
            && ($r['item'] ?? null)     === $item) {

            $r['count'] = ($r['count'] ?? 1) + 1;
            $r['date']  = date('Y-m-d');                       // latest report date
            $r['files'] = array_merge($r['files'] ?? [], $files);
            if ($description !== '') {
                $r['notes'][] = ['date' => date('Y-m-d'), 'text' => $description];
            }
            $total = $r['count'];
            unset($r);
            return $total;
        }
    }
    unset($r);

    array_unshift($_SESSION['reports'], [
        'code'        => sprintf('RPT-%03d', ++$_SESSION['report_seq']),
        'type'        => $type,
        'item'        => $item,
        'building'    => $building,
        'floor'       => $floor,
        'room'        => $room,
        'location'    => $room . ', ' . $floor . ', ' . $building,
        'description' => $description,
        'files'       => $files,
        'status'      => 'Under Review',
        'date'        => date('Y-m-d'),
        'count'       => 1,
        'filed'       => date('Y-m-d'),
    ]);
    return 1;
}

/**
 * Validate then save uploaded files (images/videos only, max 50MB each).
 * Returns saved file info, or [] and fills $errors if anything is invalid.
 */
function handle_uploads(array $f, array &$errors): array
{
    if (empty($f['name']) || !is_array($f['name'])) {
        return [];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $valid = [];

    foreach ($f['name'] as $i => $orig) {
        $err = $f['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || $f['size'][$i] > UPLOAD_MAX) {
            $errors[] = '"' . $orig . '" is larger than 50MB.';
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $errors[] = '"' . $orig . '" failed to upload.';
            continue;
        }
        $mime = $finfo->file($f['tmp_name'][$i]);   // checks real content, not the filename
        if (!isset(ALLOWED_MIME[$mime])) {
            $errors[] = '"' . $orig . '" is not an image or video.';
            continue;
        }
        $valid[] = ['tmp' => $f['tmp_name'][$i], 'orig' => $orig, 'mime' => $mime];
    }
    if ($errors) {
        return [];
    }

    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $saved = [];
    foreach ($valid as $v) {
        $name = bin2hex(random_bytes(12)) . '.' . ALLOWED_MIME[$v['mime']];
        if (move_uploaded_file($v['tmp'], $dir . '/' . $name)) {
            $saved[] = [
                'name' => $v['orig'],
                'file' => $name,
                'kind' => str_starts_with($v['mime'], 'image/') ? 'image' : 'video',
            ];
        }
    }
    return $saved;
}

/** Render one report row. $borderClass lets the dashboard use a fixed colour. */
function render_report(array $r, ?string $borderClass = null): void
{
    $badge  = STATUSES[$r['status']]['badge'] ?? 'secondary';
    $border = $borderClass ?? 'border-' . $badge;

    // Everything the "Report details" popup needs
    $texts = array_merge([$r['description'] ?? ''], array_column($r['notes'] ?? [], 'text'));
    $detail = [
        'code'         => $r['code'] ?? '',
        'type'         => $r['type'],
        'item'         => $r['item'] ?? '',
        'location'     => $r['location'],
        'filed'        => date('M j, Y', strtotime($r['filed'] ?? $r['date'])),
        'status'       => $r['status'],
        'badge'        => $badge,
        'count'        => (int)($r['count'] ?? 1),
        'descriptions' => array_values(array_filter($texts, fn($t) => $t !== '')),
        'files'        => $r['files'] ?? [],
    ];
    ?>
    <div class="list-group-item list-group-item-action report-row border-start border-3 <?= e($border) ?> d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
         role="button" tabindex="0" style="cursor:pointer"
         data-bs-toggle="modal" data-bs-target="#reportModal"
         data-report="<?= e(json_encode($detail, JSON_UNESCAPED_UNICODE)) ?>">
      <div>
        <div class="small text-body-secondary"><?= e($r['code'] ?? '') ?></div>
        <div><strong>Concern Type:</strong> <?= e($r['type']) ?><?= !empty($r['item']) ? ' &mdash; ' . e($r['item']) : '' ?>
          <?php if (!empty($r['files'])): ?><span class="text-body-secondary small ms-1"><i class="bi bi-paperclip"></i><?= count($r['files']) ?></span><?php endif; ?></div>
        <div class="text-body-secondary"><strong>Location:</strong> <?= e($r['location']) ?></div>
      </div>
      <div>
        <?php if (($r['count'] ?? 1) > 1): ?>
          <span class="badge text-bg-danger me-2"><i class="bi bi-people-fill me-1"></i><?= (int)$r['count'] ?> reports</span>
        <?php endif; ?>
        <span class="badge text-bg-<?= e($badge) ?> me-2"><?= e($r['status']) ?></span>
        <span class="text-body-secondary"><?= e(date('M j, Y', strtotime($r['date']))) ?></span>
      </div>
    </div>
    <?php
}

/** Simple CSRF helpers for the report form */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
}
// Logged-in user
// Demo password: password123
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [
        'last_name'     => 'Lastname',
        'first_name'    => 'Firstname',
        'mi'            => 'M',
        'email'         => 'firstname.lastname@pcu-d.edu.ph',
        'school_id'     => '2022-00123',
        'department'    => 'Computer Science',
        'contact'       => '09XX-XXX-XXXX',
        'role'          => 'Student',
        'avatar'        => null,
        'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
    ];
}

$__u = $_SESSION['user'];
$currentUser = [
    'name'     => $__u['last_name'] . ', ' . $__u['first_name'] . ($__u['mi'] !== '' ? ' ' . $__u['mi'] . '.' : ''),
    'greeting' => $__u['first_name'],
    'initials' => strtoupper(mb_substr($__u['first_name'], 0, 1) . mb_substr($__u['last_name'], 0, 1)),
    'avatar'   => $__u['avatar'],
];
unset($__u);

/** Public URL of a profile picture */
function avatar_url(?string $file): string
{
    return 'uploads/' . rawurlencode((string)$file);
}

/** One-time messages shown after a redirect */
function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

const AVATAR_MAX  = 5 * 1024 * 1024;
const AVATAR_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

/** Validate + save a profile picture (images only, max 5MB). Returns file name or null. */
function save_avatar(array $f, array &$errors): ?string
{
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please choose a picture.';
        return null;
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || $f['size'] > AVATAR_MAX) {
        $errors[] = 'The picture is larger than 5MB.';
        return null;
    }
    if ($err !== UPLOAD_ERR_OK) {
        $errors[] = 'The picture failed to upload.';
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset(AVATAR_MIME[$mime])) {
        $errors[] = 'Only JPG, PNG, WebP or GIF pictures are allowed.';
        return null;
    }
    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = 'avatar_' . bin2hex(random_bytes(10)) . '.' . AVATAR_MIME[$mime];
    return move_uploaded_file($f['tmp_name'], $dir . '/' . $name) ? $name : null;
}

function delete_avatar_file(?string $file): void
{
    if ($file && ($path = __DIR__ . '/../uploads/' . basename($file)) && is_file($path)) {
        unlink($path);
    }
}