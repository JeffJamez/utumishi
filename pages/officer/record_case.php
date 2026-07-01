<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/validation.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';
require_once __DIR__ . '/../../includes/utils/ob_generator.php';
require_once __DIR__ . '/../../includes/utils/file_upload.php';
require_once __DIR__ . '/../../includes/classes/CaseManager.php';
require_once __DIR__ . '/../../includes/classes/User.php';

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
            'description' => sanitizeText($_POST['description'] ?? ''),
            'category' => sanitizeText($_POST['category'] ?? ''),
            'occurred_at' => $_POST['occurred_at'] ?? '',
             'incident_location_county' => sanitizeText($_POST['incident_location_county'] ?? ''),
             'incident_location_constituency' => sanitizeText($_POST['incident_location_constituency'] ?? ''),
            'incident_local_area' => sanitizeText($_POST['incident_local_area'] ?? ''),
            'reporter_county' => sanitizeText($_POST['reporter_county'] ?? ''),
            'reporter_constituency' => sanitizeText($_POST['reporter_constituency'] ?? ''),
            'reporter_local_area' => sanitizeText($_POST['reporter_local_area'] ?? ''),
            'reporter_anonymized' => isset($_POST['reporter_anonymized']) ? 1 : 0,
            'gender' => sanitizeText($_POST['gender'] ?? ''),
            'is_minor' => isset($_POST['is_minor']) ? 1 : 0,
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

        if (empty($formData['occurred_at'])) {
            $errors['occurred_at'] = 'Date and time when the crime occurred is required';
        } else {
            $datetimeValidation = Validator::validateDateTime($formData['occurred_at'], 'Date and time of incident', false);
            if (!$datetimeValidation['valid']) {
                $errors['occurred_at'] = $datetimeValidation['message'];
            }
        }

        if (empty($formData['incident_location_county']) || empty($formData['incident_location_constituency'])) {
            $errors['location'] = 'Location (county and constituency) is required';
        } else {
            $locationValidation = validateLocation($formData['incident_location_county'], $formData['incident_location_constituency']);
            if (!$locationValidation['valid']) {
                $errors['location'] = $locationValidation['message'];
            }
        }

        if (empty($formData['reporter_county']) || empty($formData['reporter_constituency'])) {
            $errors['reporter_location'] = 'Reporter residence (county and constituency) is required';
        } else {
            $reporterLocationValidation = validateLocation($formData['reporter_county'], $formData['reporter_constituency']);
            if (!$reporterLocationValidation['valid']) {
                $errors['reporter_location'] = $reporterLocationValidation['message'];
            }
        }

        if (empty($errors)) {
            $db = Database::getInstance();

            $citizenIdDocumentPath = null;
            if (isset($_FILES['citizen_id_document']) && $_FILES['citizen_id_document']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = uploadCitizenIdDocument($_FILES['citizen_id_document'], $formData['citizen_national_id']);
                if (!$uploadResult['success']) {
                    $errors['citizen_id_document'] = $uploadResult['message'];
                } else {
                    $citizenIdDocumentPath = $uploadResult['file_path'];
                }
            }

            if (empty($errors)) {
                $citizenDetails = [
                    'name' => $formData['citizen_name'],
                    'phone' => $formData['citizen_phone'],
                    'id_document_path' => $citizenIdDocumentPath,
                    'email' => null,
                    'gender' => $formData['gender'] ?: null,
                    'is_minor' => $formData['is_minor'],
                ];

                $citizenResult = User::findOrCreateCitizen($formData['citizen_national_id'], $citizenDetails);

                if (!$citizenResult) {
                    throw new Exception('Failed to process citizen record');
                }

                $citizenId = $citizenResult['user_id'];

                $caseData = [
                    'title' => $formData['title'],
                    'description' => $formData['description'],
                    'category' => $formData['category'],
                    'occurred_at' => $formData['occurred_at'],
                    'incident_location_county' => $formData['incident_location_county'],
                    'incident_location_constituency' => $formData['incident_location_constituency'],
                    'incident_local_area' => $formData['incident_local_area'],
                    'reporter_county' => $formData['reporter_county'],
                    'reporter_constituency' => $formData['reporter_constituency'],
                    'reporter_local_area' => $formData['reporter_local_area'],
                    'reporter_anonymized' => $formData['reporter_anonymized'],
                    'reported_by_citizen_id' => $citizenId,
                    'recorded_by_officer_id' => $currentUser['id'],
                    'station_id' => $currentUser['station_id'],
                    'latitude' => !empty($_POST['incident_latitude']) ? (float)$_POST['incident_latitude'] : null,
                    'longitude' => !empty($_POST['incident_longitude']) ? (float)$_POST['incident_longitude'] : null
                ];

                $result = $caseManager->createCase($caseData);

                if ($result['success']) {
                    $success = $result['message'];
                    $obNumber = $result['ob_number'];

                    $formData = [];

                    setFlashMessage('success', "Case successfully recorded. OB Number: {$obNumber}");

                    header('Location: ' . BASE_URL . '/pages/officer/my_cases.php');
                    exit;
                } else {
                    $errors['general'] = $result['message'];
                }
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
                <h2>Digital Occurrence Book - Record New Occurence</h2>
                <p class="text-muted">Record a new occurence reported by a citizen at the station</p>
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

            <form method="POST" action="" id="recordCaseForm" enctype="multipart/form-data" class="card">
                <div class="card-header">
                    <h3>Occurrence Information</h3>
                    <p class="text-muted mb-0">Fill in all required information accurately</p>
                </div>

                <div class="card-body">
                    <?php echo csrfField(); ?>

                    <fieldset class="mb-4">
                        <legend class="h4 mb-3">Citizen Information (Reporter)</legend>

                    <div class="d-grid" style="grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                         <div class="form-group">
                                    <label for="citizen_name" class="form-label">Full Name *</label>
                                    <input 
                                        type="text" 
                                        id="citizen_name" 
                                        name="citizen_name" 
                                        autocomplete="off"
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
                                        autocomplete="off"
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

                        <div class="d-grid" style="grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                            <div class="form-group">
                                <label for="citizen_national_id" class="form-label">National ID *</label>
                                <input 
                                    type="text" 
                                    id="citizen_national_id" 
                                    name="citizen_national_id" 
                                    autocomplete="off"
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

                             <div class="form-group">
                                 <label for="citizen_id_document" class="form-label">National ID Document (PDF)</label>
                                 <input
                                     type="file"
                                     id="citizen_id_document"
                                     name="citizen_id_document"
                                     class="form-control <?php echo isset($errors['citizen_id_document']) ? 'error' : ''; ?>"
                                     accept=".pdf"
                                 >
                                 <?php if (isset($errors['citizen_id_document'])): ?>
                                     <div class="form-error"><?php echo htmlspecialchars($errors['citizen_id_document']); ?></div>
                                 <?php endif; ?>
                                 <div class="form-help">Upload a PDF copy of the citizen's National ID for verification (For minors, upload a parent's / guardian's ID)</div>
                             </div>  

                              <div class="d-grid" style="grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                             <div class="form-group">
                                 <label class="form-label">Gender</label>
                                 <div style="display: flex; gap: 1.5rem; padding-top: 0.5rem;">
                                     <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                         <input type="radio" name="gender" value="Male"
                                                style="width: 18px; height: 18px;"
                                                <?php echo ($formData['gender'] ?? '') === 'Male' ? 'checked' : ''; ?>>
                                         <span>Male</span>
                                     </label>
                                     <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                         <input type="radio" name="gender" value="Female"
                                                style="width: 18px; height: 18px;"
                                                <?php echo ($formData['gender'] ?? '') === 'Female' ? 'checked' : ''; ?>>
                                         <span>Female</span>
                                     </label>
                                 </div>
                             </div>
                         </div>
                             
                              <div class="form-group">
                                   <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                                       <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                           <input type="checkbox" id="reporter_anonymized" name="reporter_anonymized" value="1" style="width: 18px; height: 18px;">
                                           <span style="font-weight: 500;">Anonymize reporter details</span>
                                       </label>
                                       <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                           <input type="checkbox" id="reporter_is_minor" name="is_minor" value="1" style="width: 18px; height: 18px;"
                                                  <?php echo !empty($formData['is_minor']) ? 'checked' : ''; ?>>
                                           <span style="font-weight: 500;">Reporter is a minor</span>
                                       </label>
                                   </div>
                                   <div class="form-help">When checked, reporter information will be hidden from case details for safety purposes</div>
                               </div>
                         </div>
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend class="h4 mb-3">Case Details</legend>

                        <div class="d-grid" style="grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
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
                        </div>                       

                        <div class="form-group">
                            <label for="occurred_at" class="form-label">Date and Time of Incident *</label>
                            <input 
                                type="datetime-local" 
                                id="occurred_at" 
                                name="occurred_at" 
                                class="form-control <?php echo isset($errors['occurred_at']) ? 'error' : ''; ?>"
                                value="<?php echo htmlspecialchars($formData['occurred_at'] ?? ''); ?>"
                                max="<?php echo date('Y-m-d\TH:i'); ?>"
                                required
                            >
                            <?php if (isset($errors['occurred_at'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['occurred_at']); ?></div>
                            <?php endif; ?>
                            <div class="form-help">When did the crime actually occur? Format: Jan 15, 2026 at 3:30 PM. Cannot be in the future.</div>
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

                        <!-- Hidden inputs for form submission -->
                        <input type="hidden" name="incident_location_county" id="incident_location_county" value="<?php echo htmlspecialchars($formData['incident_location_county'] ?? ''); ?>">
                        <input type="hidden" name="incident_location_constituency" id="incident_location_constituency" value="<?php echo htmlspecialchars($formData['incident_location_constituency'] ?? ''); ?>">
                        <input type="hidden" name="incident_local_area" id="incident_local_area" value="<?php echo htmlspecialchars($formData['incident_local_area'] ?? ''); ?>">
                        
                        <!-- Google Places Autocomplete for Incident Location -->
                        <div class="mb-3">
                            <label for="incident_place_search" class="form-label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: text-bottom; margin-right: 4px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                Search Incident Location
                            </label>
                            <input 
                                type="text" 
                                id="incident_place_search" 
                                class="form-control"
                                placeholder="Start typing to search location in Kenya..."
                                autocomplete="off"
                            >
                            <div class="form-help">Use Google Places to search and capture location automatically</div>
                            <div id="incident_place_details" class="place-details" style="display: none; margin-top: 0.5rem; padding: 0.75rem; background: #f0f9ff; border-radius: 6px; border: 1px solid #3b82f6; font-size: 0.85rem;">
                                <strong style="color: #1e40af;">Selected:</strong> <span id="incident_place_name" style="color: #1e40af;"></span><br>
                                <small style="color: #6b7280;">GPS: <span id="incident_coords"></span></small>
                            </div>
                            <!-- Hidden fields for coordinates -->
                            <input type="hidden" name="incident_latitude" id="incident_latitude" value="<?php echo htmlspecialchars($formData['incident_latitude'] ?? ''); ?>">
                            <input type="hidden" name="incident_longitude" id="incident_longitude" value="<?php echo htmlspecialchars($formData['incident_longitude'] ?? ''); ?>">
                        </div>

                        <!-- Location Details Display (auto-populated from Google) -->
                        <div id="location_details_display" style="display: none; margin-top: 1rem; padding: 1rem; background: #f0fdf4; border-radius: 8px; border: 1px solid #22c55e;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong style="color: #166534;">Location Captured</strong>
                                <button type="button" onclick="clearLocation()" style="background: none; border: 1px solid #999; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Clear & Reselect</button>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; font-size: 0.9rem;">
                                <div>
                                    <strong style="color: #666;">County</strong><br>
                                    <span id="display_county" style="color: #333;"></span>
                                </div>
                                <div>
                                    <strong style="color: #666;">Constituency</strong><br>
                                    <span id="display_constituency" style="color: #333;"></span>
                                </div>
                                <div>
                                    <strong style="color: #666;">Local Area</strong><br>
                                    <span id="display_local_area" style="color: #333;"></span>
                                </div>
                            </div>
                        </div>

                          <?php if (isset($errors['location'])): ?>
                              <div class="form-error"><?php echo htmlspecialchars($errors['location']); ?></div>
                          <?php endif; ?>
                      </fieldset>

                      <fieldset class="mb-4">
                          <legend class="h4 mb-3">Reporter's Residence</legend>

                         <div class="d-grid" style="grid-template-columns: 1fr 1fr 2fr; gap: 1.5rem;">
                             <div class="form-group">
                                 <label for="reporter_county" class="form-label">County *</label>
                                 <select
                                     id="reporter_county"
                                     name="reporter_county"
                                     class="form-control form-select <?php echo isset($errors['reporter_location']) ? 'error' : ''; ?>"
                                     required
                                 >
                                     <option value="">Select county...</option>
                                     <?php foreach (KENYAN_COUNTIES as $county => $constituencies): ?>
                                         <option value="<?php echo htmlspecialchars($county); ?>"
                                                 <?php echo ($formData['reporter_county'] ?? '') === $county ? 'selected' : ''; ?>>
                                             <?php echo htmlspecialchars($county); ?>
                                         </option>
                                     <?php endforeach; ?>
                                 </select>
                             </div>

                             <div class="form-group">
                                 <label for="reporter_constituency" class="form-label">Constituency *</label>
                                 <select
                                     id="reporter_constituency"
                                     name="reporter_constituency"
                                     class="form-control form-select <?php echo isset($errors['reporter_location']) ? 'error' : ''; ?>"
                                     required
                                 >
                                     <option value="">Select constituency...</option>
                                 </select>
                             </div>

<div class="form-group">
                                  <label for="reporter_local_area" class="form-label">Local Area</label>
                                  <input
                                      type="text"
                                      id="reporter_local_area"
                                      name="reporter_local_area"
                                      class="form-control"
                                      placeholder="e.g., Estate name, village, street"
                                      value="<?php echo htmlspecialchars($formData['reporter_local_area'] ?? ''); ?>"
                                      maxlength="100"
                                  >
                                  <div class="form-help">Optional: Specific area or landmark</div>
                              </div>
                              
                              
                          </div>

                           <?php if (isset($errors['reporter_location'])): ?>
                               <div class="form-error"><?php echo htmlspecialchars($errors['reporter_location']); ?></div>
                           <?php endif; ?>
                           <div class="form-help">Select the county and constituency where the reporter resides</div>
                       </fieldset>
                 </div>

                 <div class="card-footer">
                    <div class="d-flex justify-between items-center">
                        <a href="<?php echo BASE_URL; ?>/pages/officer/dashboard.php" class="btn btn-secondary">
                            ← Cancel
                        </a>
                        <button type="submit" class="btn btn-success btn-lg" style="padding:7px;">
                             Record Case
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>

        const kenyanCounties = <?php echo json_encode(KENYAN_COUNTIES); ?>;

        document.getElementById('incident_location_county').addEventListener('change', function() {
            const county = this.value;
            const constituencySelect = document.getElementById('incident_location_constituency');

            constituencySelect.innerHTML = '<option value="">Select constituency...</option>';

            if (county && kenyanCounties[county]) {
                kenyanCounties[county].forEach(constituency => {
                    const option = document.createElement('option');
                    option.value = constituency;
                    option.textContent = constituency;

                     if ('<?php echo $formData['incident_location_constituency']     ?? ''; ?>' === constituency) {
                        option.selected = true;
                    }

                    constituencySelect.appendChild(option);
                });
            }
        });

        document.getElementById('reporter_county').addEventListener('change', function() {
            const county = this.value;
            const constituencySelect = document.getElementById('reporter_constituency');

            constituencySelect.innerHTML = '<option value="">Select constituency...</option>';

            if (county && kenyanCounties[county]) {
                kenyanCounties[county].forEach(constituency => {
                    const option = document.createElement('option');
                    option.value = constituency;
                    option.textContent = constituency;

                    if ('<?php echo $formData['reporter_constituency'] ?? ''; ?>' === constituency) {
                        option.selected = true;
                    }

                    constituencySelect.appendChild(option);
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            if (sessionStorage.getItem('recordCaseSaved') === 'true') {
                sessionStorage.removeItem('recordCaseSaved');
                sessionStorage.removeItem('recordCaseForm');
                return; 
            }

            const selectedCounty = document.getElementById('incident_location_county').value;
            if (selectedCounty) {
                document.getElementById('incident_location_county').dispatchEvent(new Event('change'));
            }

            const selectedReporterCounty = document.getElementById('reporter_county').value;
            if (selectedReporterCounty) {
                document.getElementById('reporter_county').dispatchEvent(new Event('change'));
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
                occurredAt: document.getElementById('occurred_at').value,
                 county: document.getElementById('incident_location_county').value,
                 constituency: document.getElementById('incident_location_constituency').value,
                incidentLocalArea: document.getElementById('incident_local_area').value.trim(),
                reporterCounty: document.getElementById('reporter_county').value,
                reporterConstituency: document.getElementById('reporter_constituency').value,
                reporterLocalArea: document.getElementById('reporter_local_area').value.trim()
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

            if (!formData.occurredAt) {
                errors.push('Date and time of incident is required');
            } else {
                const occurredDate = new Date(formData.occurredAt);
                const now = new Date();
                if (occurredDate > now) {
                    errors.push('Date and time of incident cannot be in the future');
                }
            }

            if (!formData.county || !formData.constituency) {
                errors.push('Incident location (county and constituency) is required');
            }

            if (!formData.reporterCounty || !formData.reporterConstituency) {
                errors.push('Reporter residence (county and constituency) is required');
            }

            if (errors.length > 0) {
                e.preventDefault();
                alert('Please correct the following errors:\n\n' + errors.join('\n'));
                return false;
            }



            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = ' Recording Case...';
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

        document.addEventListener('DOMContentLoaded', function() {
            const selectedCounty = document.getElementById('incident_location_county').value;
            if (selectedCounty) {
                document.getElementById('incident_location_county').dispatchEvent(new Event('change'));
            }

            const selectedReporterCounty = document.getElementById('reporter_county').value;
            if (selectedReporterCounty) {
                document.getElementById('reporter_county').dispatchEvent(new Event('change'));
            }
        });

        document.getElementById('citizen_national_id').title = 'Enter the 8-digit National ID exactly as shown on the citizen\'s ID card';
        document.getElementById('title').title = 'Write a clear, brief summary that someone can understand quickly';
        document.getElementById('description').title = 'Include the 5 W\'s: Who, What, When, Where, Why. Be specific and factual.';

        document.getElementById('citizen_national_id').focus();

        const tabOrder = ['citizen_national_id', 'citizen_name', 'citizen_phone', 'title', 'category', 'occurred_at', 'description', 'incident_location_county', 'incident_location_constituency', 'incident_local_area', 'reporter_county', 'reporter_constituency', 'reporter_local_area'];

        tabOrder.forEach((fieldId, index) => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.setAttribute('tabindex', index + 1);
            }
        });
    </script>

    <style>
        fieldset {
            border: 1px solid #737678;
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

        .form-control:invalid:not(:focus):not(:placeholder-shown):not(select):not(input[type="datetime-local"]) {
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

        /* Google Places Autocomplete Styling */
        .pac-container {
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-family: inherit;
            border: 1px solid var(--light-gray);
            margin-top: 4px;
            z-index: 9999 !important;
        }

        .pac-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }

        .pac-item:hover {
            background-color: #f0f9ff;
        }

        .pac-icon {
            margin-right: 8px;
        }

        .pac-item-query {
            font-weight: 500;
            color: #111827;
        }

        .pac-matched {
            font-weight: 700;
            color: var(--primary-green);
        }

        .google-places-group input {
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%236b7280" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }

        .google-places-group input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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

    <!-- Google Places Autocomplete Script -->
    <script>
       
        function initGooglePlaces() {
            const kenyaBounds = new google.maps.LatLngBounds(
                new google.maps.LatLng(-4.8, 33.9),  // Southwest
                new google.maps.LatLng(5.5, 42.0)    // Northeast
            );

            const incidentInput = document.getElementById('incident_place_search');
            if (incidentInput && typeof google !== 'undefined') {
                const incidentAutocomplete = new google.maps.places.Autocomplete(incidentInput, {
                    bounds: kenyaBounds,
                    componentRestrictions: { country: 'ke' },
                    fields: ['address_components', 'geometry', 'name', 'formatted_address'],
                    types: ['geocode', 'establishment']
                });

                incidentAutocomplete.addListener('place_changed', function() {
                    const place = incidentAutocomplete.getPlace();
                    if (place.geometry) {
                        const lat = place.geometry.location.lat();
                        const lng = place.geometry.location.lng();

                        const addressComponents = place.address_components;
                        let county = '';
                        let subCounty = '';
                        let localArea = '';
                        let locality = '';

                        addressComponents.forEach(component => {
                            if (component.types.includes('administrative_area_level_1')) {
                                county = component.long_name;
                            }
                            if (component.types.includes('administrative_area_level_2')) {
                                subCounty = component.long_name;
                            }
                            if (component.types.includes('sublocality') || component.types.includes('sublocality_level_1')) {
                                localArea = component.long_name;
                            }
                            if (component.types.includes('locality')) {
                                locality = component.long_name;
                            }
                        });

                        if (!localArea) {
                            addressComponents.forEach(component => {
                                if (component.types.includes('route')) {
                                    localArea = component.long_name;
                                }
                            });
                        }

                        document.getElementById('incident_latitude').value = lat.toFixed(8);
                        document.getElementById('incident_longitude').value = lng.toFixed(8);

                        document.getElementById('incident_place_name').textContent = place.name || place.formatted_address;
                        document.getElementById('incident_coords').textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
                        document.getElementById('incident_place_details').style.display = 'block';

                        let finalCounty = county || '';
                        let finalConstituency = subCounty || '';
                        let finalLocalArea = localArea || locality || (place.formatted_address ? place.formatted_address.split(',')[0] : '');

                        let normalizedCounty = finalCounty.replace(/County$/i, '').trim();

                        if (!normalizedCounty) {
                            normalizedCounty = 'Nairobi';
                            finalConstituency = 'Westlands';
                        }

                        if (!finalConstituency && normalizedCounty) {
                            const kenyanCounties = <?php echo json_encode(KENYAN_COUNTIES); ?>;
                            if (kenyanCounties[normalizedCounty] && kenyanCounties[normalizedCounty].length > 0) {
                                finalConstituency = kenyanCounties[normalizedCounty][0];
                            }
                        }

                        document.getElementById('incident_location_county').value = normalizedCounty;
                        document.getElementById('incident_location_constituency').value = finalConstituency;
                        document.getElementById('incident_local_area').value = finalLocalArea || '';

                        document.getElementById('display_county').textContent = finalCounty; // Show original for user
                        document.getElementById('display_constituency').textContent = finalConstituency;
                        document.getElementById('display_local_area').textContent = finalLocalArea || 'N/A';
                        document.getElementById('location_details_display').style.display = 'block';

                        console.log('Incident location selected:', place.name, 'Lat:', lat, 'Lng:', lng, 'County:', finalCounty, 'Constituency:', finalConstituency);
                    }
                });
            }
        }

       
        function clearLocation() {
            document.getElementById('incident_place_search').value = '';
            document.getElementById('incident_location_county').value = '';
            document.getElementById('incident_location_constituency').value = '';
            document.getElementById('incident_local_area').value = '';
            document.getElementById('incident_latitude').value = '';
            document.getElementById('incident_longitude').value = '';
            document.getElementById('incident_place_details').style.display = 'none';
            document.getElementById('location_details_display').style.display = 'none';
            document.getElementById('display_county').textContent = '';
            document.getElementById('display_constituency').textContent = '';
            document.getElementById('display_local_area').textContent = '';
        }

       
        function matchAndSelectCounty(selectId, googleCounty) {
            const select = document.getElementById(selectId);
            if (!select) return;

            const normalizedGoogleCounty = googleCounty.toLowerCase().replace(/county/g, '').trim();

            for (let i = 0; i < select.options.length; i++) {
                const optionText = select.options[i].text.toLowerCase().replace(/county/g, '').trim();
                const optionValue = select.options[i].value.toLowerCase().replace(/county/g, '').trim();

                if (optionText === normalizedGoogleCounty ||
                    optionValue === normalizedGoogleCounty ||
                    optionText.includes(normalizedGoogleCounty) ||
                    normalizedGoogleCounty.includes(optionText)) {
                    select.selectedIndex = i;
                    select.dispatchEvent(new Event('change'));
                    console.log('Auto-selected county:', select.options[i].text);
                    break;
                }
            }
        }

       
        function matchAndSelectConstituency(selectId, googleSubCounty) {
            const select = document.getElementById(selectId);
            if (!select || select.options.length <= 1) return; // No options to match against

            const countySelect = document.getElementById('incident_location_county');
            const currentCounty = countySelect ? countySelect.value : '';

            const normalizedGoogle = googleSubCounty.toLowerCase()
                .replace(/sub-county/g, '')
                .replace(/sub county/g, '')
                .replace(/constituency/g, '')
                .trim();

            let bestMatch = null;
            let bestScore = 0;

            for (let i = 0; i < select.options.length; i++) {
                const optionText = select.options[i].text.toLowerCase().trim();
                const optionValue = select.options[i].value.toLowerCase().trim();

                if (!optionValue) continue; // Skip placeholder option

                // Calculate match score (higher is better)
                let score = 0;

                // Exact match gets highest score
                if (optionText === normalizedGoogle || optionValue === normalizedGoogle) {
                    score = 100;
                }
                else if (optionText.includes(normalizedGoogle) || normalizedGoogle.includes(optionText)) {
                    score = 80;
                }
                else {
                    const googleWords = normalizedGoogle.split(/\s+/).filter(w => w.length > 2);
                    const optionWords = optionText.split(/\s+/).filter(w => w.length > 2);
                    const matchingWords = googleWords.filter(gw => 
                        optionWords.some(ow => ow.includes(gw) || gw.includes(ow))
                    );
                    if (matchingWords.length > 0) {
                        score = 50 + (matchingWords.length * 10);
                    }
                }

                if (score > bestScore) {
                    bestScore = score;
                    bestMatch = i;
                }
            }

            if (bestMatch !== null && bestScore >= 30) {
                select.selectedIndex = bestMatch;
                select.dispatchEvent(new Event('change'));
                console.log('Auto-selected constituency:', select.options[bestMatch].text, '(score:', bestScore + ')');
            } else {
                console.log('Could not match constituency:', googleSubCounty, '(best score:', bestScore + ')');
            }
        }

        window.gm_authFailure = function() {
            console.warn('Google Maps API authentication failed. Autocomplete features will be disabled.');
            const inputs = document.querySelectorAll('#incident_place_search');
            inputs.forEach(input => {
                input.disabled = true;
                input.placeholder = 'Location search unavailable';
                input.title = 'Google Places API not configured. Please enter location manually.';
            });
        };
    </script>

    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&libraries=places&callback=initGooglePlaces" async defer onerror="console.warn('Failed to load Google Maps API');"></script>
</body>
</html>
