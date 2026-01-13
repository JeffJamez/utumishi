<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';

requireLogin();

$currentUser = getCurrentUser();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = "All fields are required";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match";
    } elseif (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        try {
            $db = getDb();
            
            $user = $db->fetchOne(
                "SELECT password FROM users WHERE id = :id",
                ['id' => $currentUser['id']]
            );
            
            if (!password_verify($currentPassword, $user['password'])) {
                $error = "Current password is incorrect";
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $db->execute(
                    "UPDATE users SET password = :password WHERE id = :id",
                    [
                        'password' => $hashedPassword,
                        'id' => $currentUser['id']
                    ]
                );
                
                setFlashMessage('success', 'Password changed successfully!');
                header('Location: ' . BASE_URL . '/pages/' . $currentUser['role'] . '/dashboard.php');
                exit;
            }
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            $error = "An error occurred. Please try again.";
        }
    }
}

$pageTitle = "Change Password";

require_once __DIR__ . '/../../includes/layout/layout.php';
?>

<main class="app-main">
    <div class="mb-4">
        <h1>Change Password</h1>
        <p class="text-muted">Update your account password</p>
    </div>

    <div class="card" style="max-width: 600px;">
        <div class="card-header">
            <h3>Change Your Password</h3>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="changePasswordForm">
                <div class="form-group">
                    <label for="current_password">Current Password <span class="text-danger">*</span></label>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="current_password" 
                        name="current_password" 
                        required
                        autocomplete="current-password"
                    >
                </div>

                <div class="form-group">
                    <label for="new_password">New Password <span class="text-danger">*</span></label>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="new_password" 
                        name="new_password" 
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                    <small class="form-text text-muted">
                        Password must be at least 8 characters long
                    </small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password <span class="text-danger">*</span></label>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                </div>

                <div class="form-group">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            Change Password
                        </button>
                        <a href="<?php echo BASE_URL; ?>/pages/<?php echo $currentUser['role']; ?>/dashboard.php" class="btn btn-outline btn-secondary">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="max-width: 600px; margin-top: 2rem;">
        <div class="card-header">
            <h3>Password Requirements</h3>
        </div>
        <div class="card-body">
            <ul>
                <li>Minimum 8 characters long</li>
                <li>Should contain a mix of letters and numbers</li>
                <li>Avoid using common words or personal information</li>
                <li>Don't reuse old passwords</li>
            </ul>
        </div>
    </div>
</main>

<style>
    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--dark-gray);
    }

    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
        font-size: 1rem;
        transition: var(--transition);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-green);
        box-shadow: 0 0 0 3px rgba(0, 107, 63, 0.1);
    }

    .form-text {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.875rem;
    }

    .text-muted {
        color: var(--medium-gray);
    }

    .text-danger {
        color: var(--danger-red);
    }

    .d-flex {
        display: flex;
    }

    .gap-2 {
        gap: 1rem;
    }

    .card {
        background: var(--primary-white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        margin-bottom: 2rem;
    }

    .card-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--light-gray);
    }

    .card-header h3 {
        margin: 0;
        color: var(--primary-black);
    }

    .card-body {
        padding: 1.5rem;
    }

    .card-body ul {
        margin: 0;
        padding-left: 1.5rem;
    }

    .card-body ul li {
        margin-bottom: 0.5rem;
        color: var(--dark-gray);
    }

    .alert {
        padding: 1rem;
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
    }

    .alert-danger {
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }

    .alert-success {
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: var(--border-radius);
        font-size: 1rem;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        display: inline-block;
    }

    .btn-primary {
        background-color: var(--primary-green);
        color: var(--primary-white);
    }

    .btn-primary:hover {
        background-color: #005a3c;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .btn-outline {
        /* background-color: transparent; */
        border: 1px solid var(--medium-gray);
    }

    .btn-secondary {
        color: var(--dark-gray);
    }

    .btn-outline:hover {
        background-color: var(--light-gray);
    }

    @media (max-width: 768px) {
        .card {
            margin-left: 1rem;
            margin-right: 1rem;
        }

        .d-flex {
            flex-direction: column;
        }

        .btn {
            width: 100%;
            text-align: center;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('changePasswordForm');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');

    form.addEventListener('submit', function(e) {
        if (newPassword.value !== confirmPassword.value) {
            e.preventDefault();
            alert('New passwords do not match!');
            confirmPassword.focus();
            return false;
        }

        if (newPassword.value.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters long!');
            newPassword.focus();
            return false;
        }
    });

    // Real-time password match validation
    confirmPassword.addEventListener('input', function() {
        if (newPassword.value && confirmPassword.value) {
            if (newPassword.value === confirmPassword.value) {
                confirmPassword.style.borderColor = 'var(--success-green)';
            } else {
                confirmPassword.style.borderColor = 'var(--danger-red)';
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/layout/layout.php'; ?>