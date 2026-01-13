<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';

$auth = getAuth();
$auth->logout();

setFlashMessage('success', 'You have been logged out successfully.');

header('Location: ' . BASE_URL . '/pages/auth/login.php');
exit;
?>
