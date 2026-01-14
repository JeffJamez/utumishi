<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/navigation.php';

$userRole = $_SESSION['role'] ?? 'citizen';
$currentUser = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - Utumishi</title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/dashboard.css">
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <div class="app-layout">

        <?php renderCompleteHeader($userRole, $currentUser); ?>

         <aside class="app-sidebar">
             <?php echo renderNavigation($userRole); ?>
         </aside>
         <main class="app-main">


