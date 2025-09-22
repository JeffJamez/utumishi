<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

requireLogin();

$currentUser = getCurrentUser();
$userRole = $currentUser['role'];
$userName = $currentUser['name'];
$stationInfo = null;

if (in_array($userRole, [ROLE_OFFICER, ROLE_OCS]) && $currentUser['station_id']) {
    $db = Database::getInstance();
    $stationInfo = $db->fetchOne(
        "SELECT name, county FROM stations WHERE id = :id",
        ['id' => $currentUser['station_id']]
    );
}

if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Utumishi</title>
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
        <header class="app-header">
            <div class="header-brand">
                <a href="<?php echo BASE_URL; ?>/index.php" class="header-brand">
                    Utumishi
                </a>
            </div>

            <div class="header-user">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                    <div class="user-role <?php echo $userRole; ?>">
                        <?php echo strtoupper($userRole); ?>
                        <?php if ($stationInfo): ?>
                            - <?php echo htmlspecialchars($stationInfo['name']); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="header-actions">
                    <div class="dropdown">
                        <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                            ⚙️
                        </button>
                        <div class="dropdown-menu">
                            <?php if ($userRole === ROLE_OFFICER): ?>
                                <a href="<?php echo BASE_URL; ?>/pages/officer/profile.php" class="dropdown-item">My Profile</a>
                            <?php endif; ?>

                            <a href="#" onclick="changePassword()" class="dropdown-item">Change Password</a>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo BASE_URL; ?>/pages/auth/logout.php" class="dropdown-item text-danger">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <?php 
        $flashMessage = getFlashMessage();
        if ($flashMessage): 
        ?>
            <div class="flash-message flash-<?php echo $flashMessage['type']; ?>" id="flashMessage">
                <?php echo htmlspecialchars($flashMessage['message']); ?>
                <button onclick="closeFlashMessage()" class="flash-close">&times;</button>
            </div>
        <?php endif; ?>

        <script>

        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.querySelector('.dropdown-toggle');
            const dropdownMenu = document.querySelector('.dropdown-menu');

            if (dropdownToggle && dropdownMenu) {
                dropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
                });

                document.addEventListener('click', function(e) {
                    if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
                        dropdownMenu.style.display = 'none';
                    }
                });
            }
        });

        function closeFlashMessage() {
            const flashMessage = document.getElementById('flashMessage');
            if (flashMessage) {
                flashMessage.style.opacity = '0';
                setTimeout(() => flashMessage.remove(), 300);
            }
        }

        setTimeout(closeFlashMessage, 5000);

        function changePassword() {
            const currentPassword = prompt('Enter your current password:');
            if (!currentPassword) return;

            const newPassword = prompt('Enter your new password:');
            if (!newPassword) return;

            const confirmPassword = prompt('Confirm your new password:');
            if (newPassword !== confirmPassword) {
                alert('Passwords do not match!');
                return;
            }

            fetch('<?php echo BASE_URL; ?>/pages/auth/change_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    current_password: currentPassword,
                    new_password: newPassword,
                    csrf_token: '<?php echo csrfToken(); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Password changed successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while changing password');
            });
        }
        </script>

        <style>
        .flash-message {
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            padding: 1rem 2rem 1rem 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
        }

        .flash-close {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: inherit;
            font-size: 1.2em;
            cursor: pointer;
            opacity: 0.7;
        }

        .flash-close:hover {
            opacity: 1;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            min-width: 160px;
            background: var(--primary-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            z-index: 1000;
            padding: 0.5rem 0;
            margin-top: 0.25rem;
        }

        .dropdown-item {
            display: block;
            padding: 0.5rem 1rem;
            color: var(--dark-gray);
            text-decoration: none;
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background-color: var(--light-gray);
            color: var(--primary-black);
        }

        .dropdown-item.text-danger {
            color: var(--danger-red);
        }

        .dropdown-divider {
            height: 1px;
            background-color: #e0e0e0;
            margin: 0.5rem 0;
        }
        </style>
