<?php
/*
 * Shared helper functions used by every page.
 * Every page includes config/db.php first, then this file.
 */

require_once __DIR__ . '/icons.php';   // icon() helper for SVG icons

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

/* ---------- Roles and access control ---------- */

// Figure 2: check the role tables in order admins -> officers -> volunteers -> students
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
    if ($_SESSION['role'] !== $role) {
        redirect($root . home_for($_SESSION['role']));
    }
}
