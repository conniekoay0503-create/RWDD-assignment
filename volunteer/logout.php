<?php
/* Logout: destroy the session and go back to the login page */
require 'includes/functions.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

session_start();                         // new empty session just to carry the message
set_flash('success', 'You have been logged out.');
redirect('login.php');
