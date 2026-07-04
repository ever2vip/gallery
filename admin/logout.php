<?php
require_once __DIR__ . '/../includes/functions.php';

if (isAdminLoggedIn()) {
    logActivity('logout', 'تسجيل خروج');
}

session_unset();
session_destroy();

header('Location: login.php');
exit;
