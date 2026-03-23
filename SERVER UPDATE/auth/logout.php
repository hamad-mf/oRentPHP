<?php
require_once __DIR__ . '/../config/db.php';

if (function_exists('app_log')) {
    app_log('ACTION', 'User logged out');
}

session_unset();
session_destroy();
header('Location: ../auth/login.php');
exit;
