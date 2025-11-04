<?php
// php/auth/logout.php
require_once '../../config/config.php';

// Destroy session and redirect
session_destroy();

// Clear any remember me cookies if they exist
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

$_SESSION['success_message'] = SUCCESS_LOGOUT;
redirectTo('../../login.php');
?>