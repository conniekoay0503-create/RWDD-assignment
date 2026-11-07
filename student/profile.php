<?php
/*
 * My profile (student). The saving and the page are shared with the officer:
 * includes/profile_actions.php (save details / change password) + includes/profile_view.php (the tabs).
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');
$me = $_SESSION['tp_number'];

require '../includes/profile_actions.php';   // handles the forms, then loads $user

// What the "My role" tab shows
$role_name = 'Student';
$role_rows = ['Joined' => nice_date($user['created_at'])];
$role_text = 'Students submit devices for repair or recycling, earn points and swap them for APCard cashback.';

$page_title = 'My profile';
require '../includes/student_top.php';
page_head('user', 'My profile', 'Your details and password.');
require '../includes/profile_view.php';
require '../includes/student_bottom.php';
