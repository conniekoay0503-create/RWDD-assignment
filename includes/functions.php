<?php
/*
 * Shared helper functions used by every page.
 * Every page includes config/db.php first, then this file.
 */

require_once __DIR__ . '/icons.php';                  // icon() helper for SVG icons
require_once __DIR__ . '/collection_deadlines.php';   // check_collection_deadlines(), called by require_role()

// "Keep me signed in" keeps the session for up to 30 days, so the server must not delete it earlier
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30);
session_start();

/* ---------- Output ---------- */

// Escape text before printing it in HTML (prevents XSS)
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function rm($amount)
{
    return 'RM ' . number_format((float) $amount, 2);
}

function nice_date($datetime, $withTime = false)
{
    if (!$datetime) return '-';
    return date($withTime ? 'j M Y, g:i A' : 'j M Y', strtotime($datetime));
}

/* ---------- Flash messages (shown once after a redirect) ---------- */

function set_flash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function show_flash()
{
    if (empty($_SESSION['flash'])) return;
    foreach ($_SESSION['flash'] as $f) {
        $ico = $f['type'] === 'success' ? icon('check') : icon('alert');
        // data-flash lets main.js also show error messages with alert()
        echo '<div class="alert alert-' . e($f['type']) . '" role="alert" data-flash="' . e($f['type']) . '">' . $ico . '<span>' . e($f['msg']) . '</span></div>';
    }
    unset($_SESSION['flash']);
}

/* ---------- Database helpers (all use prepared statements) ---------- */

// Run a SELECT and return all rows as an array
function db_all($conn, $sql, $types = '', $params = [])
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Run a SELECT and return the first row (or null)
function db_one($conn, $sql, $types = '', $params = [])
{
    $rows = db_all($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

// Run a SELECT and return a single value, e.g. COUNT(*)
function db_value($conn, $sql, $types = '', $params = [])
{
    $row = db_one($conn, $sql, $types, $params);
    return $row ? array_values($row)[0] : null;
}

// Run INSERT / UPDATE / DELETE and return the number of rows affected
function db_exec($conn, $sql, $types = '', $params = [])
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->affected_rows;
}

// Make the next ID for a table, e.g. next_id($conn, 'announcements', 'announcementID', 'A') -> 'A003'.
// It finds the biggest number already used after the prefix and adds 1.
// $table / $column / $prefix are always written by us in the code (never typed by a user), so they are safe in the SQL.
function next_id($conn, $table, $column, $prefix)
{
    $start = strlen($prefix) + 1;   // where the number starts, e.g. 'CH001' -> position 3
    $last = db_value($conn, "SELECT COALESCE(MAX(CAST(SUBSTRING($column, $start) AS UNSIGNED)), 0) FROM $table");
    return $prefix . str_pad($last + 1, 3, '0', STR_PAD_LEFT);   // 7 -> 'A007', 1000 -> 'A1000'
}

// Send a personal notification to one user ($by = admin tp_number, or null for system)
function notify($conn, $tp_number, $title, $message, $by = null)
{
    $id = next_id($conn, 'notifications', 'notificationID', 'N');
    db_exec($conn, "INSERT INTO notifications (notificationID, tp_number, created_by, title, message) VALUES (?, ?, ?, ?, ?)",
        'sssss', [$id, $tp_number, $by, $title, $message]);
}

// Send the same notification to every active admin.
// Teammates call this when a student asks for something an admin must handle,
// e.g. notify_admins($conn, 'New cashback request', 'Daniel Wong redeemed RM2 Cashback.');
function notify_admins($conn, $title, $message)
{
    $admins = db_all($conn, "SELECT a.tp_number FROM admins a JOIN users u ON u.tp_number = a.tp_number WHERE u.is_active = 1");
    foreach ($admins as $a) {
        notify($conn, $a['tp_number'], $title, $message);
    }
}

/* ---------- Small page helpers (used by every role) ---------- */

// Friendly message when a list is empty
function empty_state($icon_name, $text, $action = '')
{
    echo '<div class="empty-state">' . icon($icon_name, 'icon-float') . '<p>' . e($text) . '</p>' . $action . '</div>';
}

// One table row that says "nothing here yet" (so the message sits INSIDE the table, under the headings)
function empty_row($colspan, $icon_name, $text)
{
    echo '<tr><td colspan="' . (int) $colspan . '" class="empty-msg muted">' . icon($icon_name) . ' ' . e($text) . '</td></tr>';
}

// "Pending Consent" -> "status-pending-consent" (the badge colour class)
function status_class($status)
{
    return 'status-' . strtolower(str_replace(' ', '-', $status));
}

/* ---------- Points (one rule for the whole website) ---------- */

// Points for one device = weight x multiplier (x 2 if repaired), rounded DOWN, but never more than the max.
// round(..., 2) first: computers store 0.29 * 100 as 28.999999..., and floor() alone would give 28 instead of 29.
// Used by the volunteer pages (repaired) and the officer pages (recycled). main.js uses the same rule for the calculator.
function calc_points($kg, $multiplier, $max, $repaired = false)
{
    $raw = (float) $kg * (float) $multiplier * ($repaired ? 2 : 1);
    return (int) min(floor(round($raw, 2)), (int) $max);
}

/* ---------- Report period (Month / Year / Date range) - used by every role's report ---------- */

// Reads the period from the URL (GET, so a report can be bookmarked) and checks it.
// Every report query then uses   date >= $r['start'] AND date < $r['end']   so the last day is fully included.
// The form for it is includes/period_form.php.
function report_period()
{
    $r = [
        'period' => $_GET['period'] ?? 'month',
        'month'  => $_GET['month'] ?? date('Y-m'),
        'year'   => (int) ($_GET['year'] ?? date('Y')),
        'from'   => $_GET['from'] ?? date('Y-m-01'),
        'to'     => $_GET['to'] ?? date('Y-m-d'),
        'error'  => '',
        'previewing' => isset($_GET['preview']),   // the report only appears after "Preview" is pressed
    ];
    foreach (['month', 'from', 'to'] as $k) if (!is_string($r[$k])) $r[$k] = '';

    if ($r['period'] === 'year') {
        if ($r['year'] < 2000 || $r['year'] > 2100) $r['year'] = (int) date('Y');
        $r['start'] = $r['year'] . '-01-01';
        $r['end']   = ($r['year'] + 1) . '-01-01';
        $r['label'] = 'Year ' . $r['year'];
    } elseif ($r['period'] === 'range') {
        if (!strtotime($r['from']) || !strtotime($r['to']) || $r['to'] < $r['from']) {
            $r['error'] = 'Please choose a valid date range (the "to" date must be on or after the "from" date).';
            $r['from'] = date('Y-m-01');
            $r['to']   = date('Y-m-d');
        }
        $r['start'] = date('Y-m-d', strtotime($r['from']));
        $r['end']   = date('Y-m-d', strtotime($r['to'] . ' +1 day'));   // +1 day so the "to" date is included
        $r['label'] = nice_date($r['start']) . ' – ' . nice_date($r['to']);
    } else {
        $r['period'] = 'month';
        if (!preg_match('/^\d{4}-\d{2}$/', $r['month'])) $r['month'] = date('Y-m');
        $r['start'] = $r['month'] . '-01';
        $r['end']   = date('Y-m-d', strtotime($r['start'] . ' +1 month'));
        $r['label'] = date('F Y', strtotime($r['start']));
    }
    $r['last_day'] = date('Y-m-d', strtotime($r['end'] . ' -1 day'));   // the last day INSIDE the period (for showing)
    return $r;
}

/* ---------- Redeem a reward (one rule for students AND volunteers) ---------- */

// Returns [true, message] or [false, message]. Everything is ONE transaction:
// redemption + points deduction + stock, all or nothing.
// Danger: two clicks at once could spend the same points twice. Fix: lock this user's row first
// (FOR UPDATE), so the second request waits and then sees the NEW balance.
function redeem_reward($conn, $tp, $reward_id)
{
    if (!is_string($reward_id) || !preg_match('/^RW[0-9]{3,7}$/', $reward_id)) {
        return [false, 'Invalid reward.'];
    }
    $conn->begin_transaction();
    try {
        db_one($conn, "SELECT tp_number FROM users WHERE tp_number = ? FOR UPDATE", 's', [$tp]);

        $reward = db_one($conn, "SELECT reward_name, points_required, cash_value FROM rewards WHERE rewardID = ?", 's', [$reward_id]);
        if (!$reward) throw new RuntimeException('That reward does not exist.');

        $need = (int) $reward['points_required'];
        $balance = (int) db_value($conn, "SELECT COALESCE(SUM(points), 0) FROM points_transactions WHERE tp_number = ?", 's', [$tp]);
        if ($balance < $need) throw new RuntimeException('You do not have enough points for ' . $reward['reward_name'] . '.');

        // Take 1 from the stock now, so two people cannot get the last one (the admin's Reject puts it back)
        if (db_exec($conn, "UPDATE rewards SET stock = stock - 1 WHERE rewardID = ? AND stock > 0", 's', [$reward_id]) !== 1) {
            throw new RuntimeException($reward['reward_name'] . ' is out of stock.');
        }

        $red_id = next_id($conn, 'redemptions', 'redemptionID', 'R');
        db_exec($conn, "INSERT INTO redemptions (redemptionID, tp_number, rewardID, points_used, cash_value, status) VALUES (?, ?, ?, ?, ?, 'pending')",
            'sssid', [$red_id, $tp, $reward_id, $need, (float) $reward['cash_value']]);
        db_exec($conn, "INSERT INTO points_transactions (transactionID, tp_number, redemptionID, points, txn_type, description) VALUES (?, ?, ?, ?, 'redeem', ?)",
            'sssis', [next_id($conn, 'points_transactions', 'transactionID', 'T'), $tp, $red_id, -$need, 'Redeemed ' . $reward['reward_name']]);

        $who = db_value($conn, "SELECT name FROM users WHERE tp_number = ?", 's', [$tp]);
        notify_admins($conn, 'New cashback request',
            $who . ' redeemed ' . $reward['reward_name'] . ' (' . $need . ' points). Please pay it to the APCard.');

        $conn->commit();
        return [true, $reward['reward_name'] . ' requested. It will be paid to your APCard after the admin approves it.'];
    } catch (Throwable $e) {
        $conn->rollback();
        if (!($e instanceof RuntimeException)) error_log('redeem_reward: ' . $e->getMessage());
        return [false, $e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong. Please try again.'];
    }
}

/* ---------- Deadlines (one rule for the whole website) ---------- */

const DEADLINE_DAYS = 7;   // a device may wait this many days before it counts as overdue

// The device is at the Green Hub counter and the OWNER must come and collect it
const WAITING_FOR_OWNER = ['Ready for Collection', 'Returned Unrepaired', 'Returned Unclaimed'];
// Someone must decide or act: the owner (recycle or return?) or the volunteer (take it to the counter)
const WAITING_FOR_ACTION = ['Pending Consent', 'Return Requested'];
// How the Green Hub gets rid of a device (officer: recycle, confirm recycling, dispose). One list for every page.
const DISPOSAL_METHODS = ['Certified e-waste recycler', 'Battery take-back programme', 'Metal recovery', 'Harvest parts for repair stock'];

// Before an officer may dispose of a device, the owner must have been reminded at least this long ago
const DISPOSE_WAIT_HOURS = 24;

// The device is finished: nothing more will happen to it
const FINISHED_STATUSES = ['Cancelled', 'Completed', 'Collected Unrepaired', 'Recycled', 'Disposed'];

// How many of this student's devices are NOT finished yet.
// Used before a student becomes a volunteer / officer: the student pages are the only place where the
// owner can choose "recycle or return" or press "Confirm collection", so those devices would get stuck.
function open_devices_count($conn, $tp_number)
{
    return (int) db_value($conn,
        "SELECT COUNT(*) FROM devices WHERE submitted_by = ? AND status NOT IN (" . sql_list(FINISHED_STATUSES) . ")",
        's', [$tp_number]);
}

// ['A', 'B'] -> 'A', 'B'  for an SQL IN (...) list.
// Only use it with our own constants (never with text typed by a user), so it is safe inside the SQL.
function sql_list($values)
{
    return "'" . implode("', '", $values) . "'";
}

// SQL condition for "overdue", e.g. overdue_sql(WAITING_FOR_OWNER, 'd.')
// -> d.status IN ('Ready for Collection', ...) AND d.status_changed_at < NOW() - INTERVAL 7 DAY
function overdue_sql($statuses, $alias = '')
{
    return $alias . 'status IN (' . sql_list($statuses) . ') AND ' . $alias . 'status_changed_at < NOW() - INTERVAL ' . DEADLINE_DAYS . ' DAY';
}

/* ---------- Challenges (one rule for the whole website) ---------- */

// A device counts for a challenge when it was repaired (and collected) or recycled during the challenge.
// 'Disposed' does not count: the owner never came back for it.
const CHALLENGE_STATUSES = ['Completed', 'Recycled'];

// kg one student reached in one challenge ($challenge = a row from the challenges table)
function challenge_kg($conn, $challenge, $tp_number)
{
    return (float) db_value($conn,
        "SELECT COALESCE(SUM(weight_kg), 0) FROM devices
          WHERE submitted_by = ? AND status IN (" . sql_list(CHALLENGE_STATUSES) . ")
            AND status_changed_at >= ? AND status_changed_at < DATE_ADD(?, INTERVAL 1 DAY)",
        'sss', [$tp_number, $challenge['start_date'], $challenge['end_date']]);
}

// The whole campus in one challenge, with the SAME rule as challenge_kg():
//   kg      = kg of all students' devices together (for information only)
//   reached = how many students already reached the target (each of them gets the bonus)
// The target is PER STUDENT, so the campus kg is not compared with the target.
function challenge_summary($conn, $challenge)
{
    $per_student = db_all($conn,
        "SELECT d.submitted_by, SUM(d.weight_kg) AS kg FROM devices d
          JOIN students s ON s.tp_number = d.submitted_by
          JOIN users u ON u.tp_number = d.submitted_by AND u.is_active = 1   -- same people as the admin's Award bonus
          WHERE d.status IN (" . sql_list(CHALLENGE_STATUSES) . ")
            AND d.status_changed_at >= ? AND d.status_changed_at < DATE_ADD(?, INTERVAL 1 DAY)
          GROUP BY d.submitted_by",
        'ss', [$challenge['start_date'], $challenge['end_date']]);

    $kg = 0.0;
    $reached = 0;
    foreach ($per_student as $row) {
        $kg += (float) $row['kg'];
        if ((float) $row['kg'] >= (float) $challenge['target_kg']) $reached++;
    }
    return ['kg' => $kg, 'reached' => $reached, 'students' => count($per_student)];
}

/* ---------- CSRF protection (one token for the whole website) ---------- */

// CSRF = a bad website makes the browser of a logged-in user send a form to OUR site.
// The browser sends our cookie automatically, so the session alone cannot tell the difference.
// Fix: every form carries a secret token that only our pages know. A bad website cannot read it.

// Put this inside every <form method="post">
function csrf_field()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));   // 64 random characters, made once per session
    }
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

// Called by require_role() (and by login.php / register.php), so every page is checked automatically.
// The token stays the same for the whole session, so two open tabs both keep working.
function check_csrf()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $sent = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf']) || !is_string($sent) || !hash_equals($_SESSION['csrf'], $sent)) {
        set_flash('error', 'Your session has expired. Please try again.');
        redirect($_SERVER['REQUEST_URI']);   // back to the same page as a normal GET, nothing is saved
    }
}

/* ---------- Roles and access control ---------- */

function find_role($conn, $tp_number)
{
    $tables = ['admins' => 'admin', 'officers' => 'officer', 'volunteers' => 'volunteer', 'students' => 'student'];
    foreach ($tables as $table => $role) {
        $found = db_value($conn, "SELECT COUNT(*) FROM $table WHERE tp_number = ?", 's', [$tp_number]);
        if ($found > 0) return $role;
    }
    return null;
}

function home_for($role)
{
    $homes = ['admin' => 'admin/index.php', 'officer' => 'officer/index.php',
              'volunteer' => 'volunteer/index.php', 'student' => 'student/index.php'];
    return $homes[$role] ?? 'login.php';
}

// Put this at the top of every protected page. $root = path back to the project folder.
function require_role($conn, $role, $root)
{
    if (empty($_SESSION['tp_number'])) {
        redirect($root . 'login.php');
    }
    // Log the user out if an admin deactivated the account while they were signed in
    $active = db_value($conn, "SELECT is_active FROM users WHERE tp_number = ?", 's', [$_SESSION['tp_number']]);
    if ((int) $active !== 1) {
        session_unset();
        set_flash('error', 'Your account has been deactivated. Please contact an Admin.');
        redirect($root . 'login.php');
    }
    // Check the role again on every page: if an admin approved this student as a volunteer
    // (or assigned them as an officer) while they were logged in, they move to the new role straight away.
    $_SESSION['role'] = find_role($conn, $_SESSION['tp_number']);
    if ($_SESSION['role'] !== $role) {
        redirect($root . home_for($_SESSION['role']));
    }
    check_csrf();                        // every POST form must carry the token
    check_collection_deadlines($conn);   // overdue notifications (XAMPP has no scheduler, so we check on every page)
}

// Malaysian mobile number: 01 + 8 or 9 digits. Spaces and dashes are removed first.
function clean_phone($phone) {
    return preg_replace('/[\s-]/', '', trim((string) $phone));
}
function valid_phone($phone) {
    return preg_match('/^01[0-9]{8,9}$/', $phone) === 1;
}

// Only APU student emails can register
function valid_apu_email($email) {
    return preg_match('/^[a-z0-9._%+-]+@mail\.apu\.edu\.my$/', $email) === 1;
}
