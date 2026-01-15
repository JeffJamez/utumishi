<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/includes/config/constants.php';
require_once __DIR__ . '/includes/core/db.php';
require_once __DIR__ . '/includes/core/auth.php';

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    die('System temporarily unavailable. Please try again later.');
}

$auth = getAuth();

if ($auth->isLoggedIn()) {

    $user = getCurrentUser();

    switch ($user['role']) {
        case ROLE_ADMIN:
            header('Location: ' . BASE_URL . '/pages/cc/dashboard.php');
            break;
        case ROLE_OCS:
            header('Location: ' . BASE_URL . '/pages/ocs/dashboard.php');
            break;
        case ROLE_OFFICER:
            header('Location: ' . BASE_URL . '/pages/officer/dashboard.php');
            break;
        case ROLE_CITIZEN:
            header('Location: ' . BASE_URL . '/pages/citizen/dashboard.php');
            break;
        default:

            logout();
            header('Location: ' . BASE_URL . '/pages/auth/login.php');
    }
    exit;
} else {

    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}
?>
