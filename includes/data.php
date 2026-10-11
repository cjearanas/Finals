<?php
/**
 * Data layer, authentication and helpers.
 *
 * Accounts and reports are stored in JSON files in the data folder so that every
 * user (and the admin) sees the same information. To move to MySQL, replace the
 * db_read / db_update functions and the user and report functions below with
 * PDO queries.
 */
session_start();
date_default_timezone_set('Asia/Manila');

const STATUSES = [
    'Under Review' => ['badge' => 'secondary', 'tab' => 'review',   'icon' => 'bi-bell'],
    'In Progress'  => ['badge' => 'warning',   'tab' => 'progress', 'icon' => 'bi-arrow-repeat'],
    'Resolved'     => ['badge' => 'success',   'tab' => 'resolved', 'icon' => 'bi-check-circle'],
];

const ROLES_USER  = ['Student', 'Faculty'];
const ROLES_STAFF = ['Staff'];
const DEPARTMENTS = [
    'COI', 'CET', 'CBAA', 'CSW', 'CCJ', 'CAS', 'CNHS', 'CHTM', 'College of Law', 'Graduate Studies',
    'ICTC', 'GSO', 'NSTP', 'Clinic', 'Treasury', 'Auditing', 'Registrar',
    'SHS', 'JHS', 'Elementary', 'Kiddie College',
];

/** Departments a maintenance staff account can belong to */
const DEPARTMENTS_STAFF = ['ICTC', 'GSO'];

/** Escape output */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------------
// JSON file storage (with locking so two people can't overwrite each other)
// ---------------------------------------------------------------------------
function db_path(string $name): string
{
    return __DIR__ . '/../data/' . $name . '.json';
}

/** First-run sample data */
function db_default(string $name): array
{
    if ($name === 'users') {
        $blank = ['avatar' => null, 'active' => true, 'contact' => '09XX-XXX-XXXX'];
        return [
            $blank + ['id' => 1, 'role' => 'Administrator', 'last_name' => 'Admin', 'first_name' => 'Admin', 'mi' => '',
                      'email' => 'admin@pcu.edu.ph', 'school_id' => 'ADMIN-001', 'department' => 'ICTC',
                      'password_hash' => password_hash('admin123', PASSWORD_DEFAULT)],
            $blank + ['id' => 2, 'role' => 'Student', 'last_name' => 'User', 'first_name' => 'User', 'mi' => 'U',
                      'email' => 'user@pcu.edu.ph', 'school_id' => '2026-001', 'department' => 'COI',
                      'password_hash' => password_hash('user123', PASSWORD_DEFAULT)],
            $blank + ['id' => 3, 'role' => 'Staff', 'last_name' => 'maintenance', 'first_name' => 'Staff', 'mi' => 'M',
                      'email' => 'mstaff@pcu.edu.ph', 'school_id' => 'STF-001', 'department' => 'GSO',
                      'password_hash' => password_hash('staff123', PASSWORD_DEFAULT)],
        ];
    }
    // No sample reports: the list starts empty and fills up as people file reports
    return [];
}

/** Read-modify-write a data file under an exclusive lock. Returns the saved data. */
function db_update(string $name, callable $fn): array
{
    $path = db_path($name);
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    $fh = fopen($path, 'c+');
    flock($fh, LOCK_EX);
    $raw  = stream_get_contents($fh);
    $data = $raw === '' ? null : json_decode($raw, true);
    if (!is_array($data)) {
        $data = db_default($name);
    }
    $data = $fn($data);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $data;
}

function db_read(string $name): array
{
    $path = db_path($name);
    if (is_file($path) && filesize($path) > 0) {
        $fh = fopen($path, 'r');
        flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return db_update($name, fn($d) => $d);   // creates the file with sample data
}

// ---------------------------------------------------------------------------
// Users + authentication
// ---------------------------------------------------------------------------
function users_all(): array
{
    return db_read('users');
}

function user_find(int $id): ?array
{
    static $index = null;
    if ($index === null) {
        $index = [];
        foreach (users_all() as $u) {
            $index[$u['id']] = $u;
        }
    }
    return $index[$id] ?? null;
}

/** Find by email or student/staff ID (case-insensitive) */
function user_by_login(string $login): ?array
{
    foreach (users_all() as $u) {
        if (strcasecmp($u['email'], $login) === 0 || strcasecmp($u['school_id'], $login) === 0) {
            return $u;
        }
    }
    return null;
}

function user_update(int $id, array $changes): void
{
    db_update('users', function (array $users) use ($id, $changes) {
        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                $u = array_merge($u, $changes);
            }
        }
        unset($u);
        return $users;
    });
}

function user_create(array $new): int
{
    $id = 0;
    db_update('users', function (array $users) use ($new, &$id) {
        $id = 1 + max(array_column($users, 'id') ?: [0]);
        $users[] = ['id' => $id] + $new;
        return $users;
    });
    return $id;
}

function full_name(array $u): string
{
    return $u['last_name'] . ', ' . $u['first_name'] . ($u['mi'] !== '' ? ' ' . $u['mi'] . '.' : '');
}

function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    if (!empty($_SESSION['uid'])) {
        $u = user_find((int)$_SESSION['uid']);
        if ($u && $u['active']) {
            $cache = $u;
        }
    }
    return $cache;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'Administrator';
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('Location: dashboard.php');
        exit;
    }
}

$__me = current_user();
$currentUser = $__me ? [
    'name'     => full_name($__me),
    'greeting' => $__me['first_name'],
    'initials' => strtoupper(mb_substr($__me['first_name'], 0, 1) . mb_substr($__me['last_name'], 0, 1)),
    'avatar'   => $__me['avatar'],
    'role'     => $__me['role'],
] : ['name' => '', 'greeting' => '', 'initials' => '', 'avatar' => null, 'role' => ''];
unset($__me);

// ---------------------------------------------------------------------------
// Campus layout + concern items
// ---------------------------------------------------------------------------
/** Building => Floor => Rooms. Edit this to match your campus. */
function campus_layout(): array
{
    $floors = ['1st Floor' => 1, '2nd Floor' => 2, '3rd Floor' => 3];
    $layout = [];
    foreach (['New Academic Building', 'College Building', 'Junior High School', 'Elementary Building'] as $building) {
        foreach ($floors as $floor => $n) {
            $rooms = [];
            for ($i = 1; $i <= 5; $i++) {
                $rooms[] = 'Room ' . $n . '0' . $i;
            }
            if ($building === 'New Academic Building' && $floor === '3rd Floor') {
                array_push($rooms, 'Computer Laboratory 7', 'Computer Laboratory 8', 'Computer Laboratory 9');
            }
            $rooms[] = 'Comfort Room';
            $layout[$building][$floor] = $rooms;
        }
    }
    return $layout;
}

/** Concern type => Items. Edit freely. */
function concern_items(): array
{
    return [
        'Air Conditioning' => ['Air Conditioner Unit', 'Remote Control', 'Thermostat', 'Vent / Duct', 'Other'],
        'Leaking Pipe'     => ['Faucet', 'Toilet', 'Sink', 'Water Pipe', 'Drainage', 'Other'],
        'Broken Window'    => ['Window Glass', 'Window Frame', 'Window Lock', 'Curtain / Blinds', 'Other'],
        'Electrical'       => ['Light Fixture', 'Power Outlet', 'Switch', 'Electric Fan', 'Projector', 'Circuit Breaker', 'Other'],
        'Furniture'        => ['Chair', 'Desk', 'Whiteboard', 'Cabinet', 'Door', 'Other'],
        'Computer / Network' => ['Desktop Computer', 'Monitor', 'Keyboard', 'Mouse', 'Internet / Network', 'Printer', 'Other'],
        'Other'            => ['Other'],
    ];
}

/**
 * Which concern types make sense for a room. Edit freely.
 * A comfort room has plumbing but no air conditioning; other rooms have no plumbing;
 * computer laboratories also get the Computer / Network concern.
 */
/** Which maintenance department handles a concern: technology goes to ICTC, everything else to GSO */
function concern_department(string $type): string
{
    return $type === 'Computer / Network' ? 'ICTC' : 'GSO';
}

/** Can this maintenance staff account work on this report? (department must match the concern) */
function staff_can_handle(?array $user, array $report): bool
{
    return $user && ($user['department'] ?? '') === concern_department((string)($report['type'] ?? ''));
}

function room_concerns(string $room): array
{
    if (stripos($room, 'Comfort Room') !== false) {
        return ['Leaking Pipe', 'Electrical', 'Broken Window', 'Furniture', 'Other'];
    }
    if (stripos($room, 'Computer Laboratory') !== false) {
        return ['Computer / Network', 'Air Conditioning', 'Electrical', 'Broken Window', 'Furniture', 'Other'];
    }
    return ['Air Conditioning', 'Electrical', 'Broken Window', 'Furniture', 'Other'];
}

// ---------------------------------------------------------------------------
// Reports
// ---------------------------------------------------------------------------
/** Admins see every report. Staff see their own, the ones assigned to them and open unassigned tasks. Others see only their own. */
function get_reports(?string $status = null, ?string $view = null): array
{
    $reports = db_read('reports');
    $me = current_user();

    if (!is_admin()) {
        $reports = array_values(array_filter($reports, fn($r) => $me && (
            in_array($me['id'], $r['reporters'] ?? [], true)
            || ($r['assigned_to'] ?? null) === $me['id']
            || ($r['request_from'] ?? null) === $me['id']
            || (is_staff() && empty($r['assigned_to']) && ($r['status'] ?? '') !== 'Resolved' && staff_can_handle($me, $r))   // open tasks for their department
        )));
    }
    // Staff shortcuts: only the tasks assigned to the logged-in account
    if ($view === 'assigned') {
        $reports = array_values(array_filter($reports, fn($r) => $me && ($r['assigned_to'] ?? null) === $me['id']));
    }
    if ($status !== null) {
        $reports = array_values(array_filter($reports, fn($r) => $r['status'] === $status));
    }
    // Priority order: unresolved first, then most-reported, then most recent
    usort($reports, function ($a, $b) {
        return (($a['status'] === 'Resolved') <=> ($b['status'] === 'Resolved'))
            ?: (($b['count'] ?? 1) <=> ($a['count'] ?? 1))
            ?: strcmp($b['date'], $a['date']);
    });
    return $reports;
}

function count_reports(?string $status = null, ?string $view = null): int
{
    return count(get_reports($status, $view));
}

/**
 * Add a report. If the same issue (building, floor, room, type, item) is already open,
 * it is merged into it. Returns ['status' => 'new'|'merged'|'self', 'count' => n]:
 *   new    = a brand-new report
 *   merged = someone else already reported it, so the report count went up
 *   self   = this user already reported it, so only the new details/photos were added
 */
function add_report(string $type, string $item, string $building, string $floor, string $room,
                    string $description, array $files, int $userId): array
{
    $result = ['status' => 'new', 'count' => 1];

    db_update('reports', function (array $reports) use (&$result, $type, $item, $building, $floor, $room,
                                                        $description, $files, $userId) {
        foreach ($reports as &$r) {
            if (($r['status'] ?? '') !== 'Resolved'
                && ($r['building'] ?? null) === $building && ($r['floor'] ?? null) === $floor
                && ($r['room'] ?? null) === $room && ($r['type'] ?? null) === $type
                && ($r['item'] ?? null) === $item) {

                $already = in_array($userId, $r['reporters'] ?? [], true);
                if (!$already) {
                    $r['reporters'][] = $userId;
                    $r['count'] = ($r['count'] ?? 1) + 1;
                    $r['date']  = date('Y-m-d');
                }
                $r['files'] = array_merge($r['files'] ?? [], $files);
                if ($description !== '') {
                    $r['notes'][] = ['date' => date('Y-m-d'), 'text' => $description];
                }
                $result = ['status' => $already ? 'self' : 'merged', 'count' => $r['count'] ?? 1];
                unset($r);
                return $reports;
            }
        }
        unset($r);

        $max = 0;
        foreach ($reports as $x) {
            $max = max($max, (int)substr($x['code'], 4));
        }
        array_unshift($reports, [
            'code'        => sprintf('RPT-%03d', $max + 1),
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
            'filed'       => date('Y-m-d'),
            'count'       => 1,
            'reporters'   => [$userId],
        ]);
        return $reports;
    });

    return $result;
}

/** Most unresolved jobs a maintenance staff member can hold at the same time */
const MAX_ACTIVE_JOBS = 2;

/**
 * Unresolved jobs a staff member holds: reports assigned to them that the admin has not
 * marked Resolved yet (a job marked "resolved" by staff still counts until the admin confirms it).
 * $exceptCode leaves one report out, e.g. the report that is being reassigned.
 */
function active_jobs(int $uid, ?array $reports = null, ?string $exceptCode = null): int
{
    $n = 0;
    foreach ($reports ?? db_read('reports') as $r) {
        if (($r['assigned_to'] ?? null) === $uid && ($r['status'] ?? '') !== 'Resolved' && $r['code'] !== $exceptCode) {
            $n++;
        }
    }
    return $n;
}

/** Open tasks this staff member has asked to take and the admin has not answered yet */
function pending_requests(int $uid, ?array $reports = null): int
{
    $n = 0;
    foreach ($reports ?? db_read('reports') as $r) {
        if (($r['request_from'] ?? null) === $uid && empty($r['assigned_to'])) {
            $n++;
        }
    }
    return $n;
}

/** Jobs plus open requests the current staff member already has (cached for the page) */
function my_job_load(): int
{
    static $load = null;
    if ($load === null) {
        $me = current_user();
        $reports = db_read('reports');
        $load = $me ? active_jobs($me['id'], $reports) + pending_requests($me['id'], $reports) : 0;
    }
    return $load;
}

/** All maintenance staff accounts, sorted by name */
function staff_accounts(): array
{
    $staff = array_values(array_filter(users_all(), fn($u) => in_array($u['role'], ROLES_STAFF, true)));
    usort($staff, fn($a, $b) => strcasecmp($a['last_name'] . $a['first_name'], $b['last_name'] . $b['first_name']));
    return $staff;
}

function is_staff(): bool
{
    $u = current_user();
    return $u !== null && in_array($u['role'], ROLES_STAFF, true);
}

/** Find a report by code, ignoring who is allowed to see it (for server-side checks) */
function report_find(string $code): ?array
{
    foreach (db_read('reports') as $r) {
        if ($r['code'] === $code) {
            return $r;
        }
    }
    return null;
}

/**
 * Update a report's status, and optionally who it is assigned to.
 *   $opts['assignee']         => ?int   (key present = change the assignment; null = unassign)
 *   $opts['only_assigned_to'] => int    (only update if the report is assigned to this user)
 *   $opts['require_state']    => string (only update if the assignment is in this state)
 * An assignment takes effect right away: staff cannot reject a task the admin gives them.
 * Assigning an "Under Review" report moves it to "In Progress".
 */
function update_report(string $code, string $status, array $opts = []): bool
{
    $done = false;
    db_update('reports', function (array $reports) use ($code, $status, $opts, &$done) {
        foreach ($reports as &$r) {
            if ($r['code'] !== $code) {
                continue;
            }
            if (isset($opts['only_assigned_to']) && ($r['assigned_to'] ?? null) !== $opts['only_assigned_to']) {
                continue;
            }
            if (isset($opts['require_state']) && assign_state($r) !== $opts['require_state']) {
                continue;
            }
            if (($r['status'] ?? '') !== $status) {
                $r['resolve_request'] = null;      // admin changed the status, so the request is settled
            }
            $r['status'] = $status;
            if (array_key_exists('assignee', $opts) && ($r['assigned_to'] ?? null) !== $opts['assignee']) {
                $r['assigned_to']  = $opts['assignee'];
                $r['assigned_at']  = $opts['assignee'] === null ? null : date('Y-m-d');
                $r['assign_state'] = $opts['assignee'] === null ? null : 'accepted';
                if ($opts['assignee'] !== null && $r['status'] === 'Under Review') {
                    $r['status'] = 'In Progress';
                }
                $r['request_from'] = null;
                $r['resolve_request'] = null;
            }
            $done = true;
        }
        unset($r);
        return $reports;
    });
    return $done;
}

/** '' (not assigned) or 'accepted' (assigned; there is no pending step, staff cannot reject a task) */
function assign_state(array $r): string
{
    if (empty($r['assigned_to'])) {
        return '';
    }
    return 'accepted';                            // older records that were still "pending" count as assigned too
}

/**
 * Task workflow actions. Returns a success message, or null if the action isn't allowed.
 *   staff : take (ask for an open task), cancel_request,
 *           request_resolve (say the work is finished; the admin must confirm)
 *   admin : approve_request, reject_request, confirm_resolved, reject_resolved
 */
function report_workflow(string $code, string $action, int $uid, bool $admin, ?string &$error = null, array $proof = [], string $remarks = ''): ?string
{
    $msg = null;
    db_update('reports', function (array $reports) use ($code, $action, $uid, $admin, $proof, $remarks, &$msg, &$error) {
        foreach ($reports as &$r) {
            if ($r['code'] !== $code) {
                continue;
            }
            $assigned = $r['assigned_to'] ?? null;
            $state    = assign_state($r);
            $req      = $r['request_from'] ?? null;
            $open     = ($r['status'] ?? '') !== 'Resolved';

            if ($action === 'take' && !$admin && $assigned === null && $req === null && $open) {
                if (!staff_can_handle(user_find($uid), $r)) {
                    $error = 'This task belongs to ' . concern_department($r['type']) . ', so only ' . concern_department($r['type']) . ' staff can take it.';
                } elseif (active_jobs($uid, $reports) + pending_requests($uid, $reports) >= MAX_ACTIVE_JOBS) {
                    $error = 'You can only have ' . MAX_ACTIVE_JOBS . ' jobs at a time (counting tasks you asked for). '
                           . 'Resolve one of your jobs before taking another.';
                } else {
                    $r['request_from'] = $uid;
                    $msg = 'Request sent for ' . $code . '. It becomes yours once the admin approves it.';
                }

            } elseif ($action === 'cancel_request' && !$admin && $req === $uid) {
                $r['request_from'] = null;
                $msg = 'Your request for ' . $code . ' was cancelled.';

            } elseif ($action === 'approve_request' && $admin && $req !== null && $assigned === null) {
                $person = user_find((int)$req);
                if ($person && !staff_can_handle($person, $r)) {
                    $error = full_name($person) . ' is not in ' . concern_department($r['type']) . ', so they cannot handle this task.';
                } elseif ($person && active_jobs((int)$req, $reports) >= MAX_ACTIVE_JOBS) {
                    $error = full_name($person) . ' already has ' . MAX_ACTIVE_JOBS . ' unresolved jobs and cannot take another one.';
                } elseif ($person && $person['active'] && in_array($person['role'], ROLES_STAFF, true)) {
                    $r['assigned_to']  = (int)$req;
                    $r['assigned_at']  = date('Y-m-d');
                    $r['assign_state'] = 'accepted';
                    $r['request_from'] = null;
                    if ($r['status'] === 'Under Review') {
                        $r['status'] = 'In Progress';
                    }
                    $msg = 'Request approved. ' . $code . ' is now assigned to ' . rtrim(full_name($person), '.') . '.';
                }

            } elseif ($action === 'reject_request' && $admin && $req !== null) {
                $person = user_find((int)$req);
                $r['request_from'] = null;
                $msg = 'Request from ' . ($person ? full_name($person) : 'the staff member') . ' for ' . $code . ' was rejected.';

            } elseif ($action === 'request_resolve' && !$admin && $assigned === $uid && $state === 'accepted'
                      && $open && empty($r['resolve_request'])) {
                if ($remarks === '') {
                    $error = 'Add your remarks about the work done before marking ' . $code . ' as resolved.';
                } elseif (!$proof) {
                    $error = 'Add a photo or video of the finished work before marking ' . $code . ' as resolved.';
                } else {
                    $r['resolve_request'] = ['by' => $uid, 'date' => date('Y-m-d'), 'files' => $proof, 'remarks' => $remarks];
                    $r['sent_back']       = null;
                    $msg = $code . ' was marked as resolved. Waiting for the admin to confirm.';
                }

            } elseif ($action === 'confirm_resolved' && $admin && !empty($r['resolve_request'])) {
                $r['status']          = 'Resolved';
                $r['resolved_at']     = date('Y-m-d');
                $r['proof']           = $r['resolve_request']['files'] ?? [];   // keep the proof of work
                $r['remarks']         = $r['resolve_request']['remarks'] ?? '';
                $r['resolve_request'] = null;
                $msg = $code . ' was confirmed as resolved.';

            } elseif ($action === 'reject_resolved' && $admin && !empty($r['resolve_request'])) {
                $by = user_find((int)$r['resolve_request']['by']);
                $r['resolve_request'] = null;
                $r['sent_back']       = date('Y-m-d');
                $msg = $code . ' was sent back to ' . ($by ? full_name($by) : 'the staff member') . '. It stays In Progress.';
            }
        }
        unset($r);
        return $reports;
    });
    return $msg;
}

/** Open tasks assigned to the current user */
function count_assigned_to_me(): int
{
    $me = current_user();
    return $me ? count(array_filter(get_reports(), fn($r) => ($r['assigned_to'] ?? null) === $me['id'] && $r['status'] !== 'Resolved')) : 0;
}

/** Tasks the current user accepted and has not finished yet */
function count_active_tasks(): int
{
    $me = current_user();
    return $me ? count(array_filter(get_reports(), fn($r) => ($r['assigned_to'] ?? null) === $me['id']
                                                          && assign_state($r) === 'accepted' && $r['status'] !== 'Resolved')) : 0;
}

function count_resolved_by_me(): int
{
    $me = current_user();
    return $me ? count(array_filter(get_reports(), fn($r) => ($r['assigned_to'] ?? null) === $me['id'] && $r['status'] === 'Resolved')) : 0;
}

/** Open reports nobody is assigned to yet */
function count_available_tasks(): int
{
    return count(array_filter(get_reports(), fn($r) => empty($r['assigned_to']) && $r['status'] !== 'Resolved'));
}

/** Issues the current user filed themselves */
function count_reported_by_me(): int
{
    $me = current_user();
    return $me ? count(array_filter(db_read('reports'), fn($r) => in_array($me['id'], $r['reporters'] ?? [], true))) : 0;
}

/** Banner for things waiting on the admin: staff requests and tasks marked as resolved */
function render_task_alerts(): void
{
    if (is_admin()) {
        $n = count(array_filter(get_reports(), fn($r) => !empty($r['request_from']) && empty($r['assigned_to'])));
        if ($n > 0) { ?>
          <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><i class="bi bi-bell-fill me-2"></i><strong><?= $n ?></strong> maintenance staff request<?= $n > 1 ? 's are' : ' is' ?> waiting for your approval.</span>
            <a class="btn btn-sm btn-info" href="maintenance-log.php?q=requested">Review</a>
          </div>
        <?php }

        $m = count(array_filter(get_reports(), fn($r) => !empty($r['resolve_request'])));
        if ($m > 0) { ?>
          <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><i class="bi bi-check2-circle me-2"></i><strong><?= $m ?></strong> task<?= $m > 1 ? 's' : '' ?> marked as resolved <?= $m > 1 ? 'are' : 'is' ?> waiting for your confirmation.</span>
            <a class="btn btn-sm btn-success" href="maintenance-log.php?q=awaiting+your+confirmation">Review</a>
          </div>
        <?php }
    }
}

/** Render one report row. $borderClass lets the dashboard use a fixed colour. */
function render_report(array $r, ?string $borderClass = null): void
{
    $badge  = STATUSES[$r['status']]['badge'] ?? 'secondary';
    $border = $borderClass ?? 'border-' . $badge;
    $me     = current_user();
    $admin  = is_admin();
    $staff  = is_staff();
    $open   = $r['status'] !== 'Resolved';

    $reporters = [];
    if ($admin) {                                       // only admins see who reported
        foreach ($r['reporters'] ?? [] as $uid) {
            if ($usr = user_find((int)$uid)) {
                $reporters[] = full_name($usr) . ' (' . $usr['role'] . ')';
            }
        }
    }

    // Assignment and task-request info
    $state    = assign_state($r);
    $assignee = !empty($r['assigned_to']) ? user_find((int)$r['assigned_to']) : null;
    $mine     = $me && ($r['assigned_to'] ?? null) === $me['id'];
    $reqId    = $r['request_from'] ?? null;
    $reqUser  = $reqId ? user_find((int)$reqId) : null;
    $reqByMe  = $me && $reqId === $me['id'];

    $line = '';
    $icon = 'bi-person-gear';
    if (!empty($r['assigned_to'])) {
        $who  = $mine ? 'you' : ($assignee ? full_name($assignee) : 'unknown');
        $line = 'Assigned to ' . $who;
    } elseif ($reqId) {
        $icon = 'bi-hourglass-split';
        if ($admin) {
            $line = ($reqUser ? full_name($reqUser) : 'A staff member') . ' requested to take this task';
        } elseif ($reqByMe) {
            $line = 'You requested to take this task — awaiting admin approval';
        } elseif ($staff) {
            $line = 'Another staff member requested this task';
        }
    } elseif ($open && ($admin || $staff)) {
        $icon = 'bi-person-plus';
        $line = 'Unassigned' . ($staff ? ' — available to take' : '');
    }

    // Staff said the work is finished and the admin still has to confirm it
    $rr     = $r['resolve_request'] ?? null;
    $rrUser = $rr ? user_find((int)$rr['by']) : null;
    $rrByMe = $rr && $me && (int)$rr['by'] === $me['id'];
    $line2  = '';
    if ($rr && $admin) {
        $line2 = 'Marked resolved by ' . ($rrUser ? full_name($rrUser) : 'a staff member') . ' — awaiting your confirmation';
    } elseif ($rrByMe) {
        $line2 = 'You marked this as resolved — awaiting admin confirmation';
    }

    $texts = array_merge([$r['description'] ?? ''], array_column($r['notes'] ?? [], 'text'));
    $detail = [
        'code'          => $r['code'] ?? '',
        'type'          => $r['type'],
        'item'          => $r['item'] ?? '',
        'location'      => $r['location'],
        'filed'         => date('M j, Y', strtotime($r['filed'] ?? $r['date'])),
        'status'        => $r['status'],
        'badge'         => $badge,
        'count'         => (int)($r['count'] ?? 1),
        'descriptions'  => array_values(array_filter($texts, fn($t) => $t !== '')),
        'files'         => $r['files'] ?? [],
        'reporters'     => $reporters,
        'viewer'        => $admin ? 'admin' : ($staff ? 'staff' : 'user'),
        'assigned'      => $mine ? 'You' : ($assignee ? full_name($assignee) : ''),
        'assignedId'    => (int)($r['assigned_to'] ?? 0),
        'assignState'   => $state,
        'mine'          => (bool)$mine,
        'hasRequest'    => (bool)$reqId,
        'requestName'   => $admin ? ($reqUser ? full_name($reqUser) : 'A staff member') : ($reqByMe ? 'You' : ''),
        'requestedByMe' => (bool)$reqByMe,
        'canTake'       => $staff && $open && empty($r['assigned_to']) && !$reqId && staff_can_handle($me, $r) && my_job_load() < MAX_ACTIVE_JOBS,
        'atLimit'       => $staff && $open && empty($r['assigned_to']) && !$reqId && staff_can_handle($me, $r) && my_job_load() >= MAX_ACTIVE_JOBS,
        'dept'          => concern_department((string)$r['type']),
        'maxJobs'       => MAX_ACTIVE_JOBS,
        'resolvePending' => (bool)$rr,
        'proof'         => $rr['files'] ?? ($r['proof'] ?? []),
        'remarks'       => $rr['remarks'] ?? ($r['remarks'] ?? ''),
        'resolveBy'     => $admin ? ($rrUser ? full_name($rrUser) : 'A staff member') : ($rrByMe ? 'You' : ''),
        'sentBack'      => $mine && !empty($r['sent_back']),
        'canUpdate'     => $admin,
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
        <?php if ($line !== ''): ?>
          <div class="small text-body-secondary"><i class="bi <?= e($icon) ?> me-1"></i><?= e($line) ?></div>
        <?php endif; ?>
        <?php if ($line2 !== ''): ?>
          <div class="small text-body-secondary"><i class="bi bi-check2-circle me-1"></i><?= e($line2) ?></div>
        <?php endif; ?>
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

// ---------------------------------------------------------------------------
// CSRF, flash messages
// ---------------------------------------------------------------------------
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

// ---------------------------------------------------------------------------
// Uploads: report photos/videos (50MB) and profile pictures (5MB)
// ---------------------------------------------------------------------------
const UPLOAD_MAX = 50 * 1024 * 1024;
const ALLOWED_MIME = [
    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
    'video/mp4'  => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
];

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

function avatar_url(?string $file): string
{
    return 'uploads/' . rawurlencode((string)$file);
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

/** Label for the ID number of an account: User ID, Staff ID or Admin ID */
function id_label(string $role): string
{
    if ($role === 'Administrator') {
        return 'Admin ID';
    }
    return in_array($role, ROLES_STAFF, true) ? 'Staff ID' : 'User ID';
}