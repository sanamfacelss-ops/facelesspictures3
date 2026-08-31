<?php
require_once __DIR__ . '/../app/config/config.php';

// Clear admin session
session_unset();
session_destroy();

// Redirect to admin login
header('Location: /admin-login');
exit;
