<?php
/*
 * Rewards (student). The page and the Redeem button are shared with the volunteer:
 * includes/rewards_actions.php (redeem + data) and includes/rewards_view.php (the page).
 * Only $earn_rules is different: how a STUDENT earns points.
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');
$me = $_SESSION['tp_number'];
$self = 'rewards.php';

require '../includes/rewards_actions.php';

$earn_rules = [
    ['recycle', 'Your device is recycled', 'You earn the basic points (× 1) when the officer recycles it.'],
    ['wrench',  'Your device is repaired', 'You earn DOUBLE points (× 2) when you collect it and press "Confirm Collection", because a repaired device keeps being used.'],
    ['flag',    'You finish a challenge',  'Reach a challenge target before it ends to earn its bonus points.'],
];

$page_title = 'Rewards';
require '../includes/student_top.php';
page_head('gift', 'Rewards', 'Earn points for every device you bring in, then swap them for APCard cashback.');
require '../includes/rewards_view.php';
require '../includes/student_bottom.php';
