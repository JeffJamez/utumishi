<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/validation.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';
require_once __DIR__ . '/../../includes/utils/ob_generator.php';
require_once __DIR__ . '/../../includes/classes/CaseManager.php';

requireRole(ROLE_OFFICER);

$currentUser = getCurrentUser();
$caseManager = new CaseManager();

$errors = [];
$success = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        if (!validateCSRF($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid request. Please try again.');
        }

        $formData = [
            'citizen_national_id' => sanitizeNationalId($_POST['citizen_national_id'] ?? ''),
            'citizen_name' => sanitizeName($_POST['citizen_name'] ?? ''),
            'citizen_phone' => sanitizePhone($_POST['citizen_phone'] ?? ''),
            'title' => sanitizeText($_POST['title'] ?? ''),
            'description' => sanitizeDescription($_POST['description'] ?? ''),
            'category' => sanitizeText($_POST['category'] ?? ''),
            'location_county' => sanitizeText($_POST['location_county'] ?? ''),
            'location_constituency' => sanitizeText($_POST['location_constituency'] ?? ''),
        ];

        if (empty($formData['citizen_national_id'])) {
            $errors['citizen_national_id'] = 'Citizen National ID is required';
        } else {
            $nationalIdValidation = validateNationalId($formData['citizen_national_id']);
            if (!$nationalIdValidation['valid']) {
                $errors['citizen_national_id'] = $nationalIdValidation['message'];
            }
        }

        if (empty($formData['citizen_name'])) {
            $errors['citizen_name'] = 'Citizen name is required';
        } else {
            $nameValidation = validateName($formData['citizen_name']);
            if (!$nameValidation['valid']) {
                $errors['citizen_name'] = $nameValidation['message'];
            }
        }

        if (empty($formData['citizen_phone'])) {
            $errors['citizen_phone'] = 'Citizen phone number is required';
        } else {
            $phoneValidation = validatePhone($formData['citizen_phone']);
            if (!$phoneValidation['valid']) {
                $errors['citizen_phone'] = $phoneValidation['message'];
            }
        }

        if (empty($formData['title'])) {
            $errors['title'] = 'Case title is required';
        }

        if (empty($formData['description'])) {
            $errors['description'] = 'Case description is required';
        }

        if (empty($formData['category'])) {
            $errors['category'] = 'Crime category is required';
        } else {
            $categoryValidation = validateCrimeCategory($formData['category']);
            if (!$categoryValidation['valid']) {
                $errors['category'] = $categoryValidation['message'];
            }
        }

        if (empty($formData['location_county']) || empty($formData['location_constituency'])) {
            $errors['location'] = 'Location (county and constituency) is required';
        } else {
            $locationValidation = validateLocation($formData['location_county'], $formData['location_constituency']);
            if (!$locationValidation['valid']) {
                $errors['location'] = $locationValidation['message'];
            }
        }

        if (empty($errors)) {
            $db = Database::getInstance();

            $citizen = $db->fetchOne(
                "SELECT id FROM users WHERE national_id = :national_id AND role = 'citizen'",
                ['national_id' => $formData['citizen_national_id']]
            );

            if (!$citizen) {

                $citizenData = [
                    'national_id' => $formData['citizen_national_id'],
                    'name' => $formData['citizen_name'],
                    'phone' => $formData['citizen_phone'],
                    'password' => password_hash($formData['citizen_national_id'], PASSWORD_DEFAULT),
                    'role' => ROLE_CITIZEN,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $citizenId = $db->insert('users', $citizenData);

                if (!$citizenId) {
                    throw new Exception('Failed to create citizen record');
                }
            } else {
                $citizenId = $citizen['id'];

                $db->update('users', [
                    'name' => $formData['citizen_name'],
                    'phone' => $formData['citizen_phone']
                ], 'id = :id', ['id' => $citizenId]);
            }

            $caseData = [
                'title' => $formData['title'],
                'description' => $formData['description'],
                'category' => $formData['category'],
                'location_county' => $formData['location_county'],
                'location_constituency' => $formData['location_constituency'],
                'reported_by_citizen_id' => $citizenId,
                'recorded_by_officer_id' => $currentUser['id'],
                'station_id' => $currentUser['station_id']
            ];

            $result = $caseManager->createCase($caseData);

            if ($result['success']) {
                $success = $result['message'];
                $obNumber = $result['ob_number'];

                $formData = [];

                setFlashMessage('success', "Case successfully recorded. OB Number: {$obNumber}");

                header('Location: ' . BASE_URL . '/pages/officer/dashboard.php');
                exit;
            } else {
                $errors['general'] = $result['message'];
            }
        }

    } catch (Exception $e) {
        error_log("Record Case Error: " . $e->getMessage());
        $errors['general'] = $e->getMessage();
    }
}

$pageTitle = "Record New Case";

require_once __DIR__ . '/../../includes/layout/layout.php';

?>
        <main class="app-main">
            <?php flashMessage(); ?>

            <div class="mb-4">
                <h1>Digital Occurrence Book - Record New Case</h1>
                <p class="text-muted">Record a new crime case reported by a citizen at the station</p>
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

            <form method="POST" action="" id="recordCaseForm" class="card">
                <div class="card-header">
                    <h3>Case Information</h3>
                    <p class="text-muted mb-0">Fill in all required information accurately</p>
                </div>

                <div class="card-body">
                    <?php echo csrfField(); ?>

                    <fieldset class="mb-4">
                        <legend class="h4 mb-3">Citizen Information (Reporter)</legend>

                        <div class="d-grid" style="grid-template-columns: 1fr 2fr; gap: 1.5rem;">
                            <div class="form-group">
                                <label for="citizen_national_id" class="form-label">National ID *</label>
                                <input 
                                    type="text" 
                                    id="citizen_national_id" 
                                    name="citizen_national_id" 
                                    class="form-control <?php echo isset($errors['citizen_national_id']) ? 'error' : ''; ?>"
                                    placeholder="12345678"
                                    value="<?php echo htmlspecialchars($formData['citizen_national_id'] ?? ''); ?>"
                                    maxlength="8"
                                    pattern="[0-9]{8}"
                                    required
                                >
                                <?php if (isset($errors['citizen_national_id'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($errors['citizen_national_id']); ?></div>
                                <?php endif; ?>
                                <div class="form-help">8-digit National ID of the person reporting</div>
                            </div>

                            <div class="d-grid" style="grid-template-columns: 2fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label for="citizen_name" class="form-label">Full Name *</label>
                                    <input 
                                        type="text" 
                                        id="citizen_name" 
                                        name="citizen_name" 
                                        class="form-control <?php echo isset($errors['citizen_name']) ? 'error' : ''; ?>"
                                        placeholder="Enter full name as on ID"
                                        value="<?php echo htmlspecialchars($formData['citizen_name'] ?? ''); ?>"
                                        maxlength="100"
                                        required
                                    >
                                    <?php if (isset($errors['citizen_name'])): ?>
                                        <div class="form-error"><?php echo htmlspecialchars($errors['citizen_name']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label for="citizen_phone" class="form-label">Phone Number *</label>
                                    <input 
                                        type="tel" 
                                        id="citizen_phone" 
                                        name="citizen_phone" 
                                        class="form-control <?php echo isset($errors['citizen_phone']) ? 'error' : ''; ?>"
                                        placeholder="0701234567"
                                        value="<?php echo htmlspecialchars($formData['citizen_phone'] ?? ''); ?>"
                                        required
                                    >
                                    <?php if (isset($errors['citizen_phone'])): ?>
                                        <div class="form-error"><?php echo htmlspecialchars($errors['citizen_phone']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend class="h4 mb-3">Case Details</legend>

                        <div class="form-group">
                            <label for="title" class="form-label">Case Title *</label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title" 
                                class="form-control <?php echo isset($errors['title']) ? 'error' : ''; ?>"
                                placeholder="Brief summary of the case (e.g., Theft of Mobile Phone at Bus Stop)"
                                value="<?php echo htmlspecialchars($formData['title'] ?? ''); ?>"
                                maxlength="200"
                                required
                            >
                            <?php if (isset($errors['title'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['title']); ?></div>
                            <?php endif; ?>
                            <div class="form-help">Clear, concise title describing the incident</div>
                        </div>

                        <div class="form-group">
                            <label for="category" class="form-label">Crime Category *</label>
                            <select 
                                id="category" 
                                name="category" 
                                class="form-control form-select <?php echo isset($errors['category']) ? 'error' : ''; ?>"
                                required
                            >
                                <option value="">Select crime category...</option>
                                <?php foreach (CRIME_CATEGORIES as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" 
                                            <?php echo ($formData['category'] ?? '') === $value ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['category'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['category']); ?></div>
                            <?php endif; ?>
                            <div class="form-help">Select the most appropriate category for this case</div>
                        </div>

                        <div class="form-group">
                            <label for="description" class="form-label">Detailed Description *</label>
                            <textarea 
                                id="description" 
                                name="description" 
                                class="form-control form-textarea <?php echo isset($errors['description']) ? 'error' : ''; ?>"
                                placeholder="Provide detailed description of the incident including:&#10;- What happened?&#10;- When did it happen?&#10;- Who was involved?&#10;- Any witnesses?&#10;- Items lost/damaged?"
                                rows="6"
                                maxlength="2000"
                                required
                            ><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                            <?php if (isset($errors['description'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['description']); ?></div>
                            <?php endif; ?>
                            <div class="form-help">Include all relevant details as provided by the reporter</div>
                        </div>
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend class="h4 mb-3">Location of Incident</legend>

                        <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div class="form-group">
                                <label for="location_county" class="form-label">County *</label>
                                <select 
                                    id="location_county" 
                                    name="location_county" 
                                    class="form-control form-select <?php echo isset($errors['location']) ? 'error' : ''; ?>"
                                    required
                                >
                                    <option value="">Select county...</option>
                                    <?php foreach (KENYAN_COUNTIES as $county => $constituencies): ?>
                                        <option value="<?php echo htmlspecialchars($county); ?>"
                                                <?php echo ($formData['location_county'] ?? '') === $county ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($county); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="location_constituency" class="form-label">Constituency *</label>
                                <select 
                                    id="location_constituency" 
                                    name="location_constituency" 
                                    class="form-control form-select <?php echo isset($errors['location']) ? 'error' : ''; ?>"
                                    required
                                >
                                    <option value="">Select constituency...</option>

                                </select>
                            </div>
                        </div>

                        <?php if (isset($errors['location'])): ?>
                            <div class="form-error"><?php echo htmlspecialchars($errors['location']); ?></div>
                        <?php endif; ?>
                        <div class="form-help">Select the county and constituency where the incident occurred</div>
                    </fieldset>
                </div>

                <div class="card-footer">
                    <div class="d-flex justify-between items-center">
                        <a href="<?php echo BASE_URL; ?>/pages/officer/dashboard.php" class="btn btn-secondary">
                            ← Cancel
                        </a>
                        <button type="submit" class="btn btn-success btn-lg">
                            📝 Record Case in Digital OB
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>

        const kenyanCounties = <?php echo json_encode(KENYAN_COUNTIES); ?>;

        document.getElementById('location_county').addEventListener('change', function() {
            const county = this.value;
            const constituencySelect = document.getElementById('location_constituency');

            constituencySelect.innerHTML = '<option value="">Select constituency...</option>';

            if (county && kenyanCounties[county]) {
                kenyanCounties[county].forEach(constituency => {
                    const option = document.createElement('option');
                    option.value = constituency;
                    option.textContent = constituency;

                    if ('<?php echo $formData['location_constituency']     ?? ''; ?>' === constituency) {
                        option.selected = true;
                    }

                    constituencySelect.appendChild(option);
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const selectedCounty = document.getElementById('location_county').value;
            if (selectedCounty) {
                document.getElementById('location_county').dispatchEvent(new Event('change'));
            }
        });

        document.getElementById('recordCaseForm').addEventListener('submit', function(e) {
            const formData = {
                nationalId: document.getElementById('citizen_national_id').value.trim(),
                name: document.getElementById('citizen_name').value.trim(),
                phone: document.getElementById('citizen_phone').value.trim(),
                title: document.getElementById('title').value.trim(),
                description: document.getElementById('description').value.trim(),
                category: document.getElementById('category').value,
                county: document.getElementById('location_county').value,
                constituency: document.getElementById('location_constituency').value
            };

            let errors = [];

            if (!formData.nationalId) {
                errors.push('Citizen National ID is required');
            } else if (!/^\d{8}$/.test(formData.nationalId)) {
                errors.push('National ID must be exactly 8 digits');
            }

            if (!formData.name) {
                errors.push('Citizen name is required');
            } else if (formData.name.length < 2) {
                errors.push('Name must be at least 2 characters');
            }

            if (!formData.phone) {
                errors.push('Phone number is required');
            } else {
                const phonePattern = /^(\+254|254|0)[17]\d{8}$/;
                if (!phonePattern.test(formData.phone.replace(/[\s-]/g, ''))) {
                    errors.push('Invalid Kenyan phone number format');
                }
            }

            if (!formData.title) {
                errors.push('Case title is required');
            }

            if (!formData.description) {
                errors.push('Case description is required');
            } else if (formData.description.length < 20) {
                errors.push('Please provide a more detailed description (at least 20 characters)');
            }

            if (!formData.category) {
                errors.push('Crime category is required');
            }

            if (!formData.county || !formData.constituency) {
                errors.push('Location (county and constituency) is required');
            }

            if (errors.length > 0) {
                e.preventDefault();
                alert('Please correct the following errors:\n\n' + errors.join('\n'));
                return false;
            }

            const confirmMessage = `Please confirm the case details:\n\n` +
                `Reporter: ${formData.name} (ID: ${formData.nationalId})\n` +
                `Case: ${formData.title}\n` +
                `Category: ${formData.category}\n` +
                `Location: ${formData.constituency}, ${formData.county}\n\n` +
                `Once recorded, this case will be assigned an OB number and cannot be easily deleted. Continue?`;

            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = '⏳ Recording Case...';
            submitBtn.disabled = true;

            setTimeout(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }, 10000);
        });

        document.getElementById('citizen_national_id').addEventListener('input', function(e) {

            this.value = this.value.replace(/\D/g, '').substring(0, 8);
        });

        document.getElementById('citizen_phone').addEventListener('input', function(e) {

            this.value = this.value.replace(/[^\d\+\s\-]/g, '');
        });

        document.getElementById('citizen_name').addEventListener('input', function(e) {

            this.value = this.value.replace(/[^A-Za-z\s\-\'\.]/g, '');
        });

        document.getElementById('title').addEventListener('input', function(e) {

            this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
        });

        const descriptionField = document.getElementById('description');
        const maxLength = 2000;

        function updateCharCount() {
            const current = descriptionField.value.length;
            const remaining = maxLength - current;

            let countElement = document.getElementById('desc-char-count');
            if (!countElement) {
                countElement = document.createElement('div');
                countElement.id = 'desc-char-count';
                countElement.className = 'form-help';
                descriptionField.parentNode.appendChild(countElement);
            }

            countElement.textContent = `${current}/${maxLength} characters`;
            countElement.style.color = remaining < 100 ? 'var(--danger-red)' : 'var(--medium-gray)';
        }

        descriptionField.addEventListener('input', updateCharCount);
        updateCharCount();

        const formFields = ['citizen_national_id', 'citizen_name', 'citizen_phone', 'title', 'description', 'category', 'location_county', 'location_constituency'];

        function saveFormData() {
            const data = {};
            formFields.forEach(field => {
                const element = document.getElementById(field);
                if (element) {
                    data[field] = element.value;
                }
            });
            sessionStorage.setItem('recordCaseForm', JSON.stringify(data));
        }

        function loadFormData() {
            try {
                const saved = sessionStorage.getItem('recordCaseForm');
                if (saved) {
                    const data = JSON.parse(saved);
                    formFields.forEach(field => {
                        const element = document.getElementById(field);
                        if (element && data[field]) {
                            element.value = data[field];

                            if (element.tagName === 'SELECT') {
                                element.dispatchEvent(new Event('change'));
                            }
                        }
                    });
                }
            } catch (e) {
                console.log('Could not load saved form data');
            }
        }

        function clearSavedData() {
            sessionStorage.removeItem('recordCaseForm');
        }

        setInterval(saveFormData, 30000);

        formFields.forEach(field => {
            const element = document.getElementById(field);
            if (element) {
                element.addEventListener('input', saveFormData);
                element.addEventListener('change', saveFormData);
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const isEmpty = formFields.every(field => {
                const element = document.getElementById(field);
                return !element || !element.value.trim();
            });

            if (isEmpty) {
                loadFormData();
            }
        });

        document.getElementById('recordCaseForm').addEventListener('submit', function() {
            setTimeout(clearSavedData, 1000);
        });

        window.addEventListener('beforeunload', function(e) {
            const hasUnsavedData = formFields.some(field => {
                const element = document.getElementById(field);
                return element && element.value.trim();
            });

            if (hasUnsavedData) {
                e.returnValue = 'You have unsaved case data. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 's':
                        e.preventDefault();
                        saveFormData();
                        alert('Form data saved locally');
                        break;
                    case 'Enter':
                        if (e.shiftKey) {
                            e.preventDefault();
                            document.getElementById('recordCaseForm').submit();
                        }
                        break;
                }
            }
        });

        document.getElementById('citizen_national_id').title = 'Enter the 8-digit National ID exactly as shown on the citizen\'s ID card';
        document.getElementById('title').title = 'Write a clear, brief summary that someone can understand quickly';
        document.getElementById('description').title = 'Include the 5 W\'s: Who, What, When, Where, Why. Be specific and factual.';

        document.getElementById('citizen_national_id').focus();

        const tabOrder = ['citizen_national_id', 'citizen_name', 'citizen_phone', 'title', 'category', 'description', 'location_county', 'location_constituency'];

        tabOrder.forEach((fieldId, index) => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.setAttribute('tabindex', index + 1);
            }
        });
    </script>

    <style>
        fieldset {
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        legend {
            color: var(--primary-green);
            font-weight: 600;
            padding: 0 0.5rem;
            margin-bottom: 1rem;
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .char-count {
            font-size: 0.8rem;
            color: var(--medium-gray);
            text-align: right;
            margin-top: 0.25rem;
        }

        .form-grid {
            display: grid;
            gap: 1.5rem;
        }

        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }

            .form-grid.three-col {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }

        .required::after {
            content: ' *';
            color: var(--danger-red);
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .form-control:valid {
            border-color: var(--success-green);
        }

        .form-control:invalid:not(:focus) {
            border-color: var(--danger-red);
        }

        .autosave-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--success-green);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-size: 0.8rem;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .autosave-indicator.show {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .d-grid[style*="2fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            .d-grid[style*="1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            fieldset {
                padding: 1rem;
            }

            .card-footer .d-flex {
                flex-direction: column;
                gap: 1rem;
            }

            .btn-lg {
                width: 100%;
            }
        }

        @media print {
            .app-header, .app-sidebar, .btn, .form-help {
                display: none !important;
            }

            .app-layout {
                grid-template-areas: "main";
                grid-template-columns: 1fr;
            }

            .card {
                border: 1px solid #000;
                box-shadow: none;
            }

            .form-control {
                border: none;
                border-bottom: 1px solid #000;
                background: transparent;
            }
        }
    </style>
</body>
</html>
