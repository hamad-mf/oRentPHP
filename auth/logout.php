<?php
require_once __DIR__ . '/../config/db.php';
app_log('ACTION', 'User logged out');
session_destroy();
header('Location: ../auth/login.php');
exit;
