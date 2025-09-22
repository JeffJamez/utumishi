<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/navigation.php';

$userRole = $_SESSION['role'] ?? 'citizen';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - Utumishi</title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
</head>
<body>
    <div class="app-layout">

        <?php echo renderHeader($userRole); ?>

        <aside class="app-sidebar">
            <?php echo renderNavigation($userRole); ?>
        </aside>

