<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/validation.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';

if (isset($_GET['logout'])) {
    $auth = getAuth();
    $auth->logout();
    setFlashMessage('success', 'You have been logged out successfully.');
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$auth = getAuth();

if ($auth->isLoggedIn()) {
    $user = getCurrentUser();
    $redirectUrls = [
        ROLE_ADMIN => BASE_URL . '/pages/cc/dashboard.php',
        ROLE_OCS => BASE_URL . '/pages/ocs/dashboard.php',
        ROLE_OFFICER => BASE_URL . '/pages/officer/dashboard.php',
        ROLE_CITIZEN => BASE_URL . '/pages/citizen/dashboard.php'
    ];

    $redirectUrl = $redirectUrls[$user['role']] ?? BASE_URL . '/index.php';
    header("Location: $redirectUrl");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        if (!validateCSRF($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid request. Please try again.');
        }

        $nationalId = sanitizeText($_POST['national_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = sanitizeText($_POST['role'] ?? '');

        if (empty($nationalId)) {
            throw new Exception('National ID is required');
        }

        if (empty($password)) {
            throw new Exception('Password is required');
        }

        $nationalIdValidation = validateNationalId($nationalId);
        if (!$nationalIdValidation['valid']) {
            throw new Exception($nationalIdValidation['message']);
        }

        if (!empty($role)) {
            $roleValidation = validateRole($role);
            if (!$roleValidation['valid']) {
                throw new Exception($roleValidation['message']);
            }
        }

        $loginResult = $auth->login($nationalId, $password, $role);

        if ($loginResult['success']) {

            header('Location: ' . $loginResult['redirect']);
            exit;
        } else {
            $error = $loginResult['message'];
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$flashMessage = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
</head>
<body>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo"></div>
                <h1 class="login-title"><?php echo APP_NAME; ?></h1>
                <p class="login-subtitle">Predictive Crime Analysis & Digital OB</p>
            </div>

            <?php if ($flashMessage): ?>
                <div class="flash-<?php echo htmlspecialchars($flashMessage['type']); ?>">
                    <?php echo htmlspecialchars($flashMessage['message']); ?>
                </div>
            <?php endif; ?>

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

            <form method="POST" action="" id="loginForm">
                <?php echo csrfField(); ?>

                <div class="form-group">
                    <label for="national_id" class="form-label">National ID</label>
                    <input 
                        type="text" 
                        id="national_id" 
                        name="national_id" 
                        class="form-control"
                        placeholder="Enter your 8-digit National ID"
                        value="<?php echo htmlspecialchars($_POST['national_id'] ?? ''); ?>"
                        maxlength="8"
                        pattern="[0-9]{8}"
                        required
                    >
                    <div class="form-help">Enter your 8-digit National ID number</div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control"
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="role" class="form-label">Login As (Optional)</label>
                    <select id="role" name="role" class="form-control form-select">
                        <option value="">Auto-detect role</option>
                        <option value="<?php echo ROLE_CITIZEN; ?>" <?php echo (($_POST['role'] ?? '') === ROLE_CITIZEN) ? 'selected' : ''; ?>>Citizen</option>
                        <option value="<?php echo ROLE_OFFICER; ?>" <?php echo (($_POST['role'] ?? '') === ROLE_OFFICER) ? 'selected' : ''; ?>>Police Officer</option>
                        <option value="<?php echo ROLE_OCS; ?>" <?php echo (($_POST['role'] ?? '') === ROLE_OCS) ? 'selected' : ''; ?>>OCS (Station Commander)</option>
                        <option value="<?php echo ROLE_ADMIN; ?>" <?php echo (($_POST['role'] ?? '') === ROLE_ADMIN) ? 'selected' : ''; ?>>Admin</option>
                    </select>
                    <div class="form-help">Leave as is, to auto-detect your role</div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Login
                </button>
            </form>

            <div class="text-center">
                <span class="text-muted">New citizen?</span>
                <a href="<?php echo BASE_URL; ?>/pages/auth/register_citizen.php">
                    Register as Citizen
                </a>
            </div>

        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const nationalId = document.getElementById('national_id').value.trim();
            const password = document.getElementById('password').value;

            if (!/^\d{8}$/.test(nationalId)) {
                e.preventDefault();
                alert('Please enter a valid 8-digit National ID');
                document.getElementById('national_id').focus();
                return false;
            }

            if (password.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                e.preventDefault();
                alert('Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters');
                document.getElementById('password').focus();
                return false;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Logging in...';
            submitBtn.disabled = true;

            setTimeout(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });

        document.getElementById('national_id').addEventListener('input', function(e) {

            this.value = this.value.replace(/\D/g, '');

            if (this.value.length > 8) {
                this.value = this.value.substring(0, 8);
            }
        });

        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('error');
                const errorDiv = this.parentNode.querySelector('.form-error');
                if (errorDiv) {
                    errorDiv.remove();
                }
            });
        });

        const flashMessages = document.querySelectorAll('[class^="flash-"]');
        flashMessages.forEach(msg => {
            setTimeout(() => {
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 300);
            }, 5000);
        });
    </script>
</body>
</html>
