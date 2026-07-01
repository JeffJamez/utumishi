<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/validation.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';
require_once __DIR__ . '/../../includes/classes/Officer.php';

requireAnyRole([ROLE_OFFICER, ROLE_ADMIN, ROLE_OCS]);

$currentUser = getCurrentUser();
$viewOfficerId = $_GET['id'] ?? $currentUser['id'];

if ($currentUser['role'] === 'county_commander' && $viewOfficerId !== $currentUser['id']) {
    $userDetails = getDB()->fetchOne("SELECT county_in_charge FROM users WHERE id = :id", ['id' => $currentUser['id']]);
    $county = $userDetails['county_in_charge'] ?? null;

    $officerStation = getDB()->fetchOne("SELECT s.county FROM officers o JOIN stations s ON o.station_id = s.id WHERE o.user_id = :id", ['id' => $viewOfficerId]);
    if (!$officerStation || $officerStation['county'] !== $county) {
        die("Access denied: Officer not in your county.");
    }
}

$officer = new Officer($viewOfficerId);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        if (!validateCSRF($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid request. Please try again.');
        }

        $action = sanitizeText($_POST['action'] ?? '');

        if ($action === 'update_profile') {

            $profileData = [
                'name' => sanitizeName($_POST['name'] ?? ''),
                'phone' => sanitizePhone($_POST['phone'] ?? ''),
                'email' => sanitizeEmail($_POST['email'] ?? ''),
                'expertise_categories' => $_POST['expertise_categories'] ?? []
            ];

            if (empty($profileData['name'])) {
                $errors['name'] = 'Name is required';
            }

            if (empty($profileData['phone'])) {
                $errors['phone'] = 'Phone number is required';
            } else {
                $phoneValidation = validatePhone($profileData['phone']);
                if (!$phoneValidation['valid']) {
                    $errors['phone'] = $phoneValidation['message'];
                }
            }

            if (!empty($profileData['email'])) {
                $emailValidation = validateEmail($profileData['email']);
                if (!$emailValidation['valid']) {
                    $errors['email'] = $emailValidation['message'];
                }
            }

            if (empty($errors)) {
                $result = $officer->updateOfficerProfile($profileData);

                if ($result['success']) {
                    $success = $result['message'];
                    setFlashMessage('success', 'Profile updated successfully');
                } else {
                    $errors['general'] = $result['message'];
                }
            }

        } elseif ($action === 'change_password') {

            $oldPassword = $_POST['old_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($oldPassword)) {
                $errors['old_password'] = 'Current password is required';
            }

            if (empty($newPassword)) {
                $errors['new_password'] = 'New password is required';
            } else {
                $passwordValidation = validatePassword($newPassword);
                if (!$passwordValidation['valid']) {
                    $errors['new_password'] = $passwordValidation['message'];
                }
            }

            if ($newPassword !== $confirmPassword) {
                $errors['confirm_password'] = 'Passwords do not match';
            }

            if (empty($errors)) {
                $result = $officer->changePassword($oldPassword, $newPassword);

                if ($result['success']) {
                    $success = $result['message'];
                    setFlashMessage('success', 'Password changed successfully');
                } else {
                    $errors['password_general'] = $result['message'];
                }
            }
        }

    } catch (Exception $e) {
        error_log("Profile Update Error: " . $e->getMessage());
        $errors['general'] = $e->getMessage();
    }
}

try {
    $performance = $officer->getPerformance();
    $workload = $officer->getWorkload();
    $officerData = $officer->getOfficerData();
} catch (Exception $e) {
    error_log("Profile Data Error: " . $e->getMessage());
    $performance = [];
    $workload = [];
    $officerData = [];
}

$pageTitle = "My Profile";

require_once __DIR__ . '/../../includes/layout/layout.php';

?>

        <main class="app-main">
            <?php flashMessage(); ?>

            <div class="mb-4">
                <h2>My Profile</h2>
                <p class="text-muted">Manage your profile information and view your performance</p>
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

            <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: 2rem;">

                <div class="card">
                    <div class="card-header">
                        <h3>Profile Information</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="profileForm">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="update_profile">

                            <div class="form-group">
                                <label for="badge_number" class="form-label">Badge Number</label>
                                <input 
                                    type="text" 
                                    id="badge_number" 
                                    class="form-control" 
                                    value="<?php echo htmlspecialchars($officerData['badge_number'] ?? ''); ?>"
                                    readonly
                                >
                                <div class="form-help">Badge number cannot be changed</div>
                            </div>

                            <div class="form-group">
                                <label for="name" class="form-label">Full Name *</label>
                                <input 
                                    type="text" 
                                    id="name" 
                                    name="name" 
                                    class="form-control <?php echo isset($errors['name']) ? 'error' : ''; ?>"
                                    value="<?php echo htmlspecialchars($currentUser['name']); ?>"
                                    maxlength="100"
                                    required
                                >
                                <?php if (isset($errors['name'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($errors['name']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="phone" class="form-label">Phone Number *</label>
                                <input 
                                    type="tel" 
                                    id="phone" 
                                    name="phone" 
                                    class="form-control <?php echo isset($errors['phone']) ? 'error' : ''; ?>"
                                    value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>"
                                    required
                                >
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($errors['phone']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="email" class="form-label">Email Address</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="form-control <?php echo isset($errors['email']) ? 'error' : ''; ?>"
                                    value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>"
                                >
                                <?php if (isset($errors['email'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($errors['email']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="station" class="form-label">Station</label>
                                <input 
                                    type="text" 
                                    id="station" 
                                    class="form-control" 
                                    value="<?php echo htmlspecialchars($officerData['station_name'] ?? ''); ?>"
                                    readonly
                                >
                                <div class="form-help">Station assignment is managed by administration</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Areas of Expertise</label>
                                <?php 
                                $currentExpertise = $officer->getExpertiseCategories();
                                ?>
                                <div class="expertise-checkboxes">
                                    <?php foreach (CRIME_CATEGORIES as $value => $label): ?>
                                        <label class="d-flex items-center gap-2 mb-2">
                                            <input 
                                                type="checkbox" 
                                                name="expertise_categories[]" 
                                                value="<?php echo htmlspecialchars($value); ?>"
                                                <?php echo in_array($value, $currentExpertise) ? 'checked' : ''; ?>
                                            >
                                            <span><?php echo htmlspecialchars($label); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-help">Select your areas of expertise for case assignment</div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">
                                 Update Profile
                            </button>
                        </form>
                    </div>
                </div>

                <div>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3>My Performance</h3>
                        </div>
                        <div class="card-body">
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $workload['current_case_load'] ?? 0; ?></div>
                                    <div class="stat-label">Active Cases</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $performance['total_cases_resolved'] ?? 0; ?></div>
                                    <div class="stat-label">Total Resolved</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $performance['resolution_rate'] ?? 0; ?>%</div>
                                    <div class="stat-label">Resolution Rate</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo round($performance['avg_resolution_time_hours'] ?? 0, 1); ?>h</div>
                                    <div class="stat-label">Avg Resolution Time</div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <strong>Service Information:</strong><br>
                                <small class="text-muted">
                                    Joined: <?php echo date('M d, Y', strtotime($officerData['joined_date'] ?? 'now')); ?><br>
                                    On-time Rate: <?php echo $performance['on_time_rate'] ?? 0; ?>%
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Change Password</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($errors['password_general'])): ?>
                                <div class="alert alert-danger">
                                    <?php echo htmlspecialchars($errors['password_general']); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="" id="passwordForm">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="change_password">

                                <div class="form-group">
                                    <label for="old_password" class="form-label">Current Password *</label>
                                    <input 
                                        type="password" 
                                        id="old_password" 
                                        name="old_password" 
                                        class="form-control <?php echo isset($errors['old_password']) ? 'error' : ''; ?>"
                                        required
                                    >
                                    <?php if (isset($errors['old_password'])): ?>
                                        <div class="form-error"><?php echo htmlspecialchars($errors['old_password']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label for="new_password" class="form-label">New Password *</label>
                                    <input 
                                        type="password" 
                                        id="new_password" 
                                        name="new_password" 
                                        class="form-control <?php echo isset($errors['new_password']) ? 'error' : ''; ?>"
                                        minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                                        required
                                    >
                                    <?php if (isset($errors['new_password'])): ?>
                                        <div class="form-error"><?php echo htmlspecialchars($errors['new_password']); ?></div>
                                    <?php endif; ?>
                                    <div class="form-help">At least <?php echo PASSWORD_MIN_LENGTH; ?> characters with letters and numbers</div>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                    <input 
                                        type="password" 
                                        id="confirm_password" 
                                        name="confirm_password" 
                                        class="form-control <?php echo isset($errors['confirm_password']) ? 'error' : ''; ?>"
                                        minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                                        required
                                    >
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <div class="form-error"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn-warning btn-block">
                                    Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>

        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const email = document.getElementById('email').value.trim();

            if (!name) {
                e.preventDefault();
                alert('Name is required');
                return false;
            }

            if (!phone) {
                e.preventDefault();
                alert('Phone number is required');
                return false;
            }

            const phonePattern = /^(\+254|254|0)[17]\d{8}$/;
            if (!phonePattern.test(phone.replace(/[\s-]/g, ''))) {
                e.preventDefault();
                alert('Please enter a valid Kenyan phone number');
                return false;
            }

            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
        });

        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const oldPassword = document.getElementById('old_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (!oldPassword) {
                e.preventDefault();
                alert('Current password is required');
                return false;
            }

            if (newPassword.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                e.preventDefault();
                alert('New password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters');
                return false;
            }

            if (!/[A-Za-z]/.test(newPassword) || !/\d/.test(newPassword)) {
                e.preventDefault();
                alert('Password must contain at least one letter and one number');
                return false;
            }

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match');
                return false;
            }

            if (oldPassword === newPassword) {
                e.preventDefault();
                alert('New password must be different from current password');
                return false;
            }
        });

        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;

            if (confirmPassword && newPassword !== confirmPassword) {
                this.classList.add('error');
                let errorDiv = this.parentNode.querySelector('.form-error');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'form-error';
                    this.parentNode.appendChild(errorDiv);
                }
                errorDiv.textContent = 'Passwords do not match';
            } else {
                this.classList.remove('error');
                const errorDiv = this.parentNode.querySelector('.form-error');
                if (errorDiv) errorDiv.remove();
            }
        });

        document.getElementById('phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^\d\+\s\-]/g, '');
        });

        document.getElementById('name').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^A-Za-z\s\-\'\.]/g, '');
        });
    </script>

    <style>
        .expertise-checkboxes {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            padding: 1rem;
            background: var(--primary-white);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: var(--light-gray);
            border-radius: var(--border-radius);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-green);
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--medium-gray);
            margin-top: 0.25rem;
        }

        @media (max-width: 768px) {
            .d-grid[style*="1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</body>
</html>
