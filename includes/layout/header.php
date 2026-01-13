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
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo APP_NAME; ?></title>
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
                             <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="m10.135 21l-.362-2.892q-.479-.145-1.035-.454q-.557-.31-.947-.664l-2.668 1.135l-1.865-3.25l2.306-1.739q-.045-.27-.073-.558q-.03-.288-.03-.559q0-.252.03-.53q.028-.278.073-.626L3.258 9.126l1.865-3.212L7.771 7.03q.448-.373.97-.673q.52-.3 1.013-.464L10.134 3h3.732l.361 2.912q.575.202 1.016.463t.909.654l2.725-1.115l1.865 3.211l-2.382 1.796q.082.31.092.569t.01.51q0 .233-.02.491q-.019.259-.088.626l2.344 1.758l-1.865 3.25l-2.681-1.154q-.467.393-.94.673t-.985.445L13.866 21zm1.838-6.5q1.046 0 1.773-.727T14.473 12t-.727-1.773t-1.773-.727q-1.052 0-1.776.727T9.473 12t.724 1.773t1.776.727"/></svg>
                         </button>
                         <div class="dropdown-menu">
                             <a href="<?php echo BASE_URL; ?>/pages/<?php echo $userRole; ?>/dashboard.php" class="dropdown-item">
                                 <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                 Dashboard
                             </a>
                        <?php if (in_array($userRole, [ROLE_OFFICER, ROLE_CITIZEN])): ?>
                            <a href="<?php echo BASE_URL; ?>/pages/<?php echo $userRole; ?>/profile.php" class="dropdown-item">
                                My Profile
                            </a>
                        <?php endif; ?>

                             <a href="#" onclick="changePassword()" class="dropdown-item">
                                 <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><circle cx="12" cy="16" r="1"></circle><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                 Change Password
                             </a>
                             <div class="dropdown-divider"></div>
                             <a href="<?php echo BASE_URL; ?>/pages/auth/logout.php" class="dropdown-item text-danger">
                                 <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                 Logout
                             </a>
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

            if (newPassword.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                alert('Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long!');
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
