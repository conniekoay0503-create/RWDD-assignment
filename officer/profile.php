<?php
/*
 * My profile (recycling officer). The saving and the page are shared with the student:
 * includes/profile_actions.php (save details / change password) + includes/profile_view.php (the tabs).
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');
$me = $_SESSION['tp_number'];

require '../includes/profile_actions.php';   // handles the forms, then loads $user

// What the "My role" tab shows: who made this person an officer, and when
$role = db_one($conn, "SELECT o.assigned_at, u.name AS assigned_by_name
                       FROM officers o LEFT JOIN users u ON u.tp_number = o.assigned_by
                       WHERE o.tp_number = ?", 's', [$me]);
$role_name = 'Recycling Officer';
$role_rows = [
    'Assigned by' => $role['assigned_by_name'] ?? '-',
    'Assigned on' => $role ? nice_date($role['assigned_at']) : '-',
];
$role_text = 'Officers weigh and recycle devices, and dispose of devices that were not collected in time.';

$page_title = 'My profile';
$active = '';
require '../includes/officer_top.php';
officer_page_head('user', 'My profile', 'Your details and password.');
require '../includes/profile_view.php';
require '../includes/officer_bottom.php';
