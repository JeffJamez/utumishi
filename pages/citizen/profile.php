<?php
define('UTUMISHI_WEB_APP', true);
session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';

requireRole(ROLE_CITIZEN);

$currentUser = getCurrentUser();
$db = Database::getInstance();

$pageTitle = "My Profile";

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validateCSRF($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid request. Please try again.');
        }

        $name = sanitizeText(trim($_POST['name'] ?? ''));
        $email = sanitizeText(trim($_POST['email'] ?? ''));
        $phone = sanitizeText(trim($_POST['phone'] ?? ''));

        if (empty($name)) {
            $errors['name'] = 'Name is required';
        }

        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($phone)) {
            $errors['phone'] = 'Phone number is required';
        }

        if (empty($errors)) {
            $updateData = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone
            ];

            $result = $db->update('users', $updateData, 'id = :id', ['id' => $currentUser['id']]);

            if ($result) {
                $success = 'Profile updated successfully';
                $currentUser = getCurrentUser(); // Refresh user data
                setFlashMessage('success', 'Profile updated successfully');
            } else {
                throw new Exception('Failed to update profile');
            }
        }

    } catch (Exception $e) {
        error_log("Citizen Profile Update Error: " . $e->getMessage());
        $errors['general'] = 'Failed to update profile. Please try again.';
    }
}

require_once __DIR__ . '/../../includes/layout/layout.php';
?>

        <main class="app-main">
            <?php flashMessage(); ?>

            <div class="mb-4">
                <h2>My Profile</h2>
                <p class="text-muted">Update your personal information</p>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3>Personal Information</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <?php echo csrfField(); ?>

                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text"
                                           class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>"
                                           id="name"
                                           name="name"
                                           value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>"
                                           required>
                                    <?php if (isset($errors['name'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email"
                                           class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                           id="email"
                                           name="email"
                                           value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>"
                                           required>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['email']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel"
                                           class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                                           id="phone"
                                           name="phone"
                                           value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>"
                                           required>
                                    <?php if (isset($errors['phone'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3>Account Information</h3>
                        </div>
                        <div class="card-body">
                            <p><strong>Role:</strong> Citizen</p>
                            <p><strong>Member Since:</strong> <?php echo date('M j, Y', strtotime($currentUser['created_at'] ?? 'now')); ?></p>
                            <p><strong>Last Login:</strong> <?php echo date('M j, Y H:i', strtotime($currentUser['last_login'] ?? 'now')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </main>