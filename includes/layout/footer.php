<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}
?>

   <footer class="app-footer">
        <div class="footer-content">
            <div class="footer-left">
                <p>&copy; <?php echo date('Y'); ?> Utumishi - Kenya Police Service Digital Platform</p>
            </div>
            <div class="footer-right">
                <span class="footer-links">
                    <a href="<?php echo BASE_URL; ?>/pages/help.php">Help</a>
                    <a href="<?php echo BASE_URL; ?>/pages/contact.php">Contact</a>
                    <a href="<?php echo BASE_URL; ?>/pages/privacy.php">Privacy</a>
                </span>
                <span class="footer-version">v1.0</span>
            </div>
        </div>
    </footer>