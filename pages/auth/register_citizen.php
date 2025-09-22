<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/validation.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';

$auth = getAuth();

if ($auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$errors = [];
$success = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        if (!validateCSRF($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid request. Please try again.');
        }

        $formData = [
            'national_id' => sanitizeNationalId($_POST['national_id'] ?? ''),
            'name' => sanitizeName($_POST['name'] ?? ''),
            'phone' => sanitizePhone($_POST['phone'] ?? ''),
            'email' => sanitizeEmail($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? ''
        ];

        $validationRules = [
            'national_id' => ['required', 'national_id'],
            'name' => ['required', 'min_length' => 2, 'max_length' => 100],
            'phone' => ['required', 'phone'],
            'email' => ['email'],
            'password' => ['required', 'min_length' => PASSWORD_MIN_LENGTH],
        ];

        $validation = Validator::validateFields($formData, $validationRules);
        if (!$validation['valid']) {
            $errors = array_merge($errors, $validation['errors']);
        }

        if ($formData['password'] !== $formData['confirm_password']) {
            $errors['confirm_password'] = 'Passwords do not match';
        }

        if (empty($errors)) {

            $db = Database::getInstance();
            if ($db->exists('users', 'national_id = :national_id', ['national_id' => $formData['national_id']])) {
                $errors['national_id'] = 'This National ID is already registered';
            }

            if ($db->exists('users', 'phone = :phone', ['phone' => $formData['phone']])) {
                $errors['phone'] = 'This phone number is already registered';
            }

            if (!empty($formData['email']) && $db->exists('users', 'email = :email', ['email' => $formData['email']])) {
                $errors['email'] = 'This email address is already registered';
            }
        }

        if (empty($errors)) {
            $registrationResult = $auth->registerCitizen($formData);

            if ($registrationResult['success']) {

                $success = $registrationResult['message'];

                $formData = [];

                setFlashMessage('success', 'Registration successful! You can now login with your credentials.');

                header('refresh:3;url=' . BASE_URL . '/pages/auth/login.php');
            } else {
                $errors['general'] = $registrationResult['message'];
            }
        }

    } catch (Exception $e) {
        $errors['general'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Registration - Utumishi</title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card" style="max-width: 500px;">
            <div class="login-header">
                    <h1 class="login-title">Citizen Registration</h1>
                <p class="login-subtitle">Register to track cases and access public services</p>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <br><small>Redirecting to login page...</small>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" action="" id="registrationForm">
                <?php echo csrfField(); ?>

                <div class="form-group">
                    <label for="national_id" class="form-label">National ID *</label>
                    <input 
                        type="text" 
                        id="national_id" 
                        name="national_id" 
                        class="form-control <?php echo isset($errors['national_id']) ? 'error' : ''; ?>"
                        placeholder="Enter your 8-digit National ID"
                        value="<?php echo htmlspecialchars($formData['national_id'] ?? ''); ?>"
                        maxlength="8"
                        pattern="[0-9]{8}"
                        required
                    >
                    <?php if (isset($errors['national_id'])): ?>
                        <div class="form-error"><?php echo htmlspecialchars($errors['national_id']); ?></div>
                    <?php endif; ?>
                    <div class="form-help">Your official 8-digit National ID number</div>
                </div>

                <div class="form-group">
                    <label for="name" class="form-label">Full Name *</label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        class="form-control <?php echo isset($errors['name']) ? 'error' : ''; ?>"
                        placeholder="Enter your full name"
                        value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>"
                        maxlength="100"
                        required
                    >
                    <?php if (isset($errors['name'])): ?>
                        <div class="form-error"><?php echo htmlspecialchars($errors['name']); ?></div>
                    <?php endif; ?>
                    <div class="form-help">Enter your name as it appears on your National ID</div>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number *</label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        class="form-control <?php echo isset($errors['phone']) ? 'error' : ''; ?>"
                        placeholder="e.g., +254701234567 or 0701234567"
                        value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>"
                        required
                    >
                    <?php if (isset($errors['phone'])): ?>
                        <div class="form-error"><?php echo htmlspecialchars($errors['phone']); ?></div>
                    <?php endif; ?>
                    <div class="form-help">Kenyan mobile number for case updates and notifications</div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address (Optional)</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control <?php echo isset($errors['email']) ? 'error' : ''; ?>"
                        placeholder="your.email@example.com"
                        value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                    >
                    <?php if (isset($errors['email'])): ?>
                        <div class="form-error"><?php echo htmlspecialchars($errors['email']); ?></div>
                    <?php endif; ?>
                    <div class="form-help">Optional email for case updates and notifications</div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password *</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control <?php echo isset($errors['password']) ? 'error' : ''; ?>"
                        placeholder="Create a secure password"
                        minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                        required
                    >
                    <?php if (isset($errors['password'])): ?>
                        <div class="form-error"><?php echo htmlspecialchars($errors['password']); ?></div>
                    <?php endif; ?>
                    <div class="form-help">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters, include letters and numbers</div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        class="form-control <?php echo isset($errors['confirm_password']) ? 'error' : ''; ?>"
                        placeholder="Re-enter your password"
                        minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                        required
                    >
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="form-error"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                    <?php endif; ?>
                    <div class="form-help">Must match the password above</div>
                </div>

                <div class="form-group">
                    <label class="d-flex items-center gap-2">
                        <input type="checkbox" id="terms" required>
                        <span>I agree to the <a href="#" onclick="showTerms()">Terms of Service</a> and <a href="#" onclick="showPrivacy()">Privacy Policy</a> *</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Register Account
                </button>
            </form>
            <?php endif; ?>

            <div class="text-center mt-3">
                <p class="text-muted">Already have an account?</p>
                <a href="<?php echo BASE_URL; ?>/pages/auth/login.php" class="btn btn-outline btn-primary">
                    Login Here
                </a>
            </div>

            <div class="text-center mt-4">
                <div class="form-help">
                    <small>
                        <strong>Note:</strong> This registration is for citizens only.<br>
                        Police officers and officials are registered through official channels.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>

        document.getElementById('registrationForm')?.addEventListener('submit', function(e) {
            const formData = {
                nationalId: document.getElementById('national_id').value.trim(),
                name: document.getElementById('name').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                email: document.getElementById('email').value.trim(),
                password: document.getElementById('password').value,
                confirmPassword: document.getElementById('confirm_password').value,
                terms: document.getElementById('terms').checked
            };

            let errors = [];

            if (!/^\d{8}$/.test(formData.nationalId)) {
                errors.push('Please enter a valid 8-digit National ID');
            }

            if (formData.name.length < 2) {
                errors.push('Name must be at least 2 characters');
            }

            const phonePattern = /^(\+254|254|0)[17]\d{8}$/;
            if (!phonePattern.test(formData.phone.replace(/[\s-]/g, ''))) {
                errors.push('Please enter a valid Kenyan phone number');
            }

            if (formData.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
                errors.push('Please enter a valid email address');
            }

            if (formData.password.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                errors.push('Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters');
            }

            if (!/[A-Za-z]/.test(formData.password) || !/\d/.test(formData.password)) {
                errors.push('Password must contain at least one letter and one number');
            }

            if (formData.password !== formData.confirmPassword) {
                errors.push('Passwords do not match');
            }

            if (!formData.terms) {
                errors.push('You must agree to the Terms of Service and Privacy Policy');
            }

            if (errors.length > 0) {
                e.preventDefault();
                alert('Please correct the following errors:\n\n' + errors.join('\n'));
                return false;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Creating Account...';
            submitBtn.disabled = true;

            setTimeout(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }, 10000);
        });

        document.getElementById('national_id')?.addEventListener('input', function(e) {

            this.value = this.value.replace(/\D/g, '').substring(0, 8);
        });

        document.getElementById('phone')?.addEventListener('input', function(e) {

            this.value = this.value.replace(/[^\d\+\s\-]/g, '');
        });

        document.getElementById('name')?.addEventListener('input', function(e) {

            this.value = this.value.replace(/[^A-Za-z\s\-\'\.]/g, '');
        });

        document.getElementById('password')?.addEventListener('input', function(e) {
            const password = this.value;
            const strengthDiv = document.getElementById('password-strength') || createPasswordStrengthDiv();

            let strength = 0;
            let feedback = [];

            if (password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>) strength++;
            else feedback.push('At least <?php echo PASSWORD_MIN_LENGTH; ?> characters');

            if (/[A-Za-z]/.test(password)) strength++;
            else feedback.push('Include letters');

            if (/\d/.test(password)) strength++;
            else feedback.push('Include numbers');

            if (/[^A-Za-z\d]/.test(password)) strength++;

            const strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'][strength];
            const strengthColor = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#28a745'][strength];

            strengthDiv.innerHTML = `Password Strength: <span style="color: ${strengthColor};">${strengthText}</span>`;
            if (feedback.length > 0) {
                strengthDiv.innerHTML += `<br><small>${feedback.join(', ')}</small>`;
            }
        });

        function createPasswordStrengthDiv() {
            const div = document.createElement('div');
            div.id = 'password-strength';
            div.className = 'form-help';
            document.getElementById('password').parentNode.appendChild(div);
            return div;
        }

        document.getElementById('confirm_password')?.addEventListener('input', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;

            if (confirmPassword && password !== confirmPassword) {
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

        function showTerms() {
            alert('Terms of Service:\n\n1. This system is for official police business only.\n2. Misuse of the system is prohibited.\n3. All activities are logged and monitored.\n4. Users must keep login credentials secure.\n5. Report suspicious activities immediately.');
        }

        function showPrivacy() {
            alert('Privacy Policy:\n\n1. Personal information is protected and confidential.\n2. Data is used only for official police purposes.\n3. Information is not shared with unauthorized parties.\n4. Security measures are in place to protect data.\n5. Users can request access to their data.');
        }
    </script>
</body>
</html>
