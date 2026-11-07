<?php
/*
 * Rewards (volunteer). The page and the Redeem button are shared with the student:
 * includes/rewards_actions.php (redeem + data) and includes/rewards_view.php (the page).
 * Only $earn_rules is different: how a VOLUNTEER earns points.
 */
require '../config/db.php';
require '../includes/functions.php';
require_once '../includes/volunteer_logic.php';
require_role($conn, 'volunteer', '../');
$me = $_SESSION['tp_number'];
$self = 'points.php';

require '../includes/rewards_actions.php';

$earn_rules = [
    ['wrench',  'You repair a device', 'You earn the basic points (× 1) when you press "Mark as fixed".'],
    ['users',   'The owner earns too',  'The owner of the device earns double (× 2) for the same repair.'],
    ['recycle', 'Recycled devices',     'Devices that cannot be fixed earn points for their owner only, not for the volunteer.'],
];

$page_title = 'Rewards';
$active = 'points';
require '../includes/volunteer_top.php';
page_head('gift', 'Rewards', 'Earn points for every device you repair, then swap them for APCard cashback.');
require '../includes/rewards_view.php';
require '../includes/volunteer_bottom.php';
