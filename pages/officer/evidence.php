<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/validation.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';
require_once __DIR__ . '/../../includes/utils/file_upload.php';
require_once __DIR__ . '/../../includes/classes/Officer.php';
require_once __DIR__ . '/../../includes/classes/CaseManager.php';
require_once __DIR__ . '/../../includes/classes/Station.php';

if (!in_array($_SESSION['role'] ?? '', [ROLE_OFFICER, ROLE_OCS])) {
    die('Access denied');
}

$currentUser = getCurrentUser();
$caseManager = new CaseManager();
$isOCS = ($_SESSION['role'] ?? '') === ROLE_OCS;

if ($isOCS) {
    $station = new Station($currentUser['station_id']);
} else {
    $officer = new Officer($currentUser['id']);
}

$errors = [];
$success = '';
$selectedCase = null;
$caseEvidence = [];

$caseId = (int)($_GET['case_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        if (!validateCSRF($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid request. Please try again.');
        }

        $action = sanitizeText($_POST['action'] ?? '');

        if ($action === 'upload_evidence') {
            if ($isOCS) {
                throw new Exception('OCS users cannot upload evidence');
            }

            $uploadCaseId = (int)$_POST['case_id'];
            $description = sanitizeText($_POST['description'] ?? '');

            if (!$uploadCaseId) {
                throw new Exception('Please select a case');
            }

            if (!$officer->canPerformAction('upload_evidence', $uploadCaseId)) {
                throw new Exception('You do not have permission to upload evidence to this case');
            }

            if (!empty($_FILES['evidence_file']) && $_FILES['evidence_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = uploadEvidence($_FILES['evidence_file'], $uploadCaseId, $currentUser['id'], $description);

                if ($uploadResult['success']) {
                    $success = $uploadResult['message'];
                    setFlashMessage('success', 'Evidence uploaded successfully');
                    $caseId = $uploadCaseId;
                    $currentStatus = $caseManager->getCaseById($uploadCaseId, $currentUser['id'])['status'];
                    $caseManager->addCaseUpdate(
                        $uploadCaseId,
                        $currentUser['id'],
                        'Evidence uploaded: ' . htmlspecialchars($uploadResult['original_filename'] ?? 'File'),
                        $currentStatus,
                        $currentStatus
                    );
                } else {
                    $errors['upload'] = $uploadResult['message'];
                }
            } else {
                $errors['upload'] = 'Please select a file to upload';
            }

        } elseif ($action === 'delete_evidence') {
            if ($isOCS) {
                throw new Exception('OCS users cannot delete evidence');
            }

            $evidenceId = (int)$_POST['evidence_id'];

            if ($evidenceId) {
                $evidenceRec = Database::getInstance()->fetchOne(
                    "SELECT case_id FROM case_evidence WHERE id = :id",
                    ['id' => $evidenceId]
                );
                $deleteCaseId = $evidenceRec['case_id'] ?? 0;
                $deleteResult = deleteEvidence($evidenceId, $currentUser['id']);

                if ($deleteResult['success']) {
                    $success = $deleteResult['message'];
                    setFlashMessage('success', 'Evidence deleted successfully');
                    if ($deleteCaseId) {
                        $currentStatus = $caseManager->getCaseById($deleteCaseId, $currentUser['id'])['status'];
                        $caseManager->addCaseUpdate(
                            $deleteCaseId,
                            $currentUser['id'],
                            'Evidence file deleted by officer',
                            $currentStatus,
                            $currentStatus
                        );
                    }
                } else {
                    $errors['delete'] = $deleteResult['message'];
                }
            }
        }

    } catch (Exception $e) {
        error_log("Evidence Management Error: " . $e->getMessage());
        $errors['general'] = $e->getMessage();
    }
}

try {
    if ($isOCS) {
        $officerCases = $station->getCases([], 50); // Get recent cases for OCS
    } else {
        $officerCases = $officer->getAssignedCases();
    }
} catch (Exception $e) {
    $officerCases = [];
}

if ($caseId > 0) {
    try {
        $selectedCase = $caseManager->getCaseById($caseId);

        if ($selectedCase) {
            if ($isOCS) {
                if ($selectedCase['station_id'] == $currentUser['station_id']) {
                    $caseEvidence = getCaseEvidence($caseId);
                } else {
                    $selectedCase = null;
                    $errors['case'] = 'Case not found or you do not have permission to view it';
                }
            } else {
                if ($officer->canPerformAction('view_case', $caseId)) {
                    $caseEvidence = getCaseEvidence($caseId);
                } else {
                    $selectedCase = null;
                    $errors['case'] = 'Case not found or you do not have permission to view it';
                }
            }
        } else {
            $selectedCase = null;
            $errors['case'] = 'Case not found';
        }
    } catch (Exception $e) {
        error_log("Case Evidence Error: " . $e->getMessage());
        $errors['case'] = 'Unable to load case evidence';
    }
}

$pageTitle = "Evidence Management";

require_once __DIR__ . '/../../includes/layout/layout.php';
?>
        <main class="app-main">
            <?php flashMessage(); ?>

            <div class="mb-4">
                <h2>Evidence Management</h2>
                <p class="text-muted">Upload and manage evidence files for your assigned cases</p>
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

        
            <?php if (!$selectedCase && empty($caseId)): ?>

                <div class="card">
                     <div class="card-header">
                         <h3> <?php echo $isOCS ? 'Station Cases' : 'My Cases'; ?> - Select to View Evidence</h3>
                     </div>
                    <div class="card-body">
                        <?php if (!empty($officerCases)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>OB Number</th>
                                            <th>Case Title</th>
                                            <th>Status</th>
                                            <th>Evidence Count</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($officerCases as $case): ?>
                                            <?php

                                            try {
                                                $evidenceCount = count(getCaseEvidence($case['id']));
                                            } catch (Exception $e) {
                                                $evidenceCount = 0;
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($case['ob_number']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($case['title']); ?><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($case['category']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo STATUS_COLORS[$case['status']] ?? 'status-reported'; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $evidenceCount > 0 ? 'status-progress' : 'status-assigned'; ?>">
                                                        <?php echo $evidenceCount; ?> file(s)
                                                    </span>
                                                </td>
                                                <td>
                                             <a href="<?php echo BASE_URL; ?>/pages/<?php echo $isOCS ? 'ocs' : 'officer'; ?>/evidence.php?case_id=<?php echo $case['id']; ?>" 
                                                class="btn btn-sm btn-primary">
                                                 View Evidence
                                             </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                             <div class="text-center p-4">
                                 <div style="font-size: 3rem;"></div>
                                 <h4><?php echo $isOCS ? 'No Cases in Station' : 'No Cases Assigned'; ?></h4>
                                 <p class="text-muted"><?php echo $isOCS ? 'There are no cases in your station yet.' : 'You don\'t have any cases assigned for evidence management.'; ?></p>
                                 <a href="<?php echo BASE_URL; ?>/pages/<?php echo $isOCS ? 'ocs' : 'officer'; ?>/dashboard.php" class="btn btn-primary">
                                     Return to Dashboard
                                 </a>
                             </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($selectedCase): ?>

                <div class="card">
                <div class="card-header">
                    <h3>Evidence for Case: <?php echo htmlspecialchars($selectedCase['ob_number']); ?></h3>
                    <a href="<?php echo BASE_URL; ?>/pages/<?php echo $isOCS ? 'ocs/station_cases.php' : 'officer/evidence.php'; ?>" class="btn btn-sm btn-outline btn-secondary">
                        Back to Cases
                    </a>
                </div>
                    <div class="card-body">

                        <div class="alert alert-info mb-4">
                            <strong><?php echo htmlspecialchars($selectedCase['title']); ?></strong><br>
                            <small>
                                Category: <?php echo htmlspecialchars($selectedCase['category']); ?> • 
                                Reporter: <?php echo !empty($selectedCase['reporter_anonymized']) ? '<span style="color:#dc3545;font-weight:bold;">ANONYMIZED</span>' : htmlspecialchars($selectedCase['reporter_name']); ?> • 
                                Status: <?php echo ucfirst(str_replace('_', ' ', $selectedCase['status'])); ?>
                            </small>
                        </div>

                        <?php if (!empty($errors['case'])): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($errors['case']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors['delete'])): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($errors['delete']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($caseEvidence)): ?>
                            <div class="evidence-grid">
                                <?php foreach ($caseEvidence as $evidence): ?>
                                    <div class="evidence-item">
                                        <div class="evidence-icon">
                                            <?php
                                            $ext = strtolower($evidence['file_type']);
                                            if ($ext === 'pdf') {
                                                echo '📄';
                                            } elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                                                echo '🖼️';
                                            } else {
                                                echo '📎';
                                            }
                                            ?>
                                        </div>
                                        <div class="evidence-info">
                                            <div class="evidence-filename">
                                                <?php echo htmlspecialchars($evidence['original_filename']); ?>
                                            </div>
                                            <?php if ($evidence['description']): ?>
                                                <div class="evidence-description">
                                                    <?php echo htmlspecialchars($evidence['description']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="evidence-meta">
                                                <small class="text-muted">
                                                    Uploaded by <?php echo htmlspecialchars($evidence['uploaded_by_name']); ?><br>
                                                    <?php echo date('M d, Y \a\t H:i', strtotime($evidence['uploaded_at'])); ?>
                                                </small>
                </div>
             </div>
<div class="evidence-actions">
                                            <a href="<?php echo BASE_URL; ?>/pages/officer/download_evidence.php?id=<?php echo $evidence['id']; ?>"
                                               class="btn btn-sm btn-outline btn-primary"
                                               style="height: 14px;"
                                               target="_blank">
                                                Download
                                            </a>
                                            <?php if ($evidence['uploaded_by_officer_id'] == $currentUser['id']): ?>
                                                <?php if (!$isOCS): ?>
                                                    <form method="POST" style="display: inline;"
                                                          onsubmit="return confirm('Are you sure you want to delete this evidence file? This action cannot be undone.')">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="action" value="delete_evidence">
                                                        <input type="hidden" name="evidence_id" value="<?php echo $evidence['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline btn-danger">
                                                            Delete
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4" style="background: var(--light-gray); border-radius: var(--border-radius);">
                                <h4>No Evidence Files</h4>
                                <p class="text-muted">No evidence has been uploaded for this case yet.</p>
                                <p><small>Use the upload form above to add evidence files.</small></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>


                <div class="card mb-4" style="<?php echo $isOCS ? 'display: none;' : ''; ?>">
                <div class="card-header">
                    <h3> Upload New Evidence</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors['upload'])): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($errors['upload']); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="upload_evidence">

                        <div class="d-grid" style="grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                            <div>
                                <div class="form-group">
                                    <label for="case_id" class="form-label">Select Case *</label>
                                    <select 
                                        id="case_id" 
                                        name="case_id" 
                                        class="form-control form-select"
                                        required
                                    >
                                        <option value="">Choose a case to upload evidence...</option>
                                        <?php foreach ($officerCases as $case): ?>
                                             <?php if (in_array($case['status'], ['resolved', 'closed'])) continue; ?>
                                            <option value="<?php echo $case['id']; ?>" 
                                                    <?php echo $case['id'] == $caseId ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($case['ob_number']); ?> - 
                                                <?php echo htmlspecialchars($case['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-help">Select the case to associate this evidence with</div>
                                </div>

                                <div class="form-group">
                                    <label for="evidence_file" class="form-label">Evidence File *</label>
                                    <input 
                                        type="file" 
                                        id="evidence_file" 
                                        name="evidence_file" 
                                        class="form-control form-file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        required
                                    >
                                    <div class="form-help">PDF, JPG, or PNG files only. Maximum size: 5MB</div>
                                </div>

                                <div class="form-group">
                                    <label for="description" class="form-label">Description</label>
                                    <input 
                                        type="text" 
                                        id="description" 
                                        name="description" 
                                        class="form-control"
                                        placeholder="e.g., Description of the documents povided as eveidence e.g. witness statement, stolen items photo"
                                        maxlength="200"
                                    >
                                    <div class="form-help">Brief description of the evidence (optional)</div>
                                </div>
                            </div>

                            <div class="evidence-guidelines">
                                <h5> Evidence Guidelines</h5>
                                <ul style="font-size: 0.9rem; line-height: 1.4;">
                                    <li>Only upload relevant case evidence</li>
                                    <li>Ensure files are clear and readable</li>
                                    <li>Do not upload duplicate files</li>
                                    <li>Include descriptive information</li>
                                    <li>Maximum file size: 5MB</li>
                                    <li>Supported formats: PDF, JPG, PNG</li>
                                </ul>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-success btn-block">
                                         Upload Evidence
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </main>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const caseId = document.getElementById('case_id').value;
            const fileInput = document.getElementById('evidence_file');
            const file = fileInput.files[0];

            if (!caseId) {
                e.preventDefault();
                alert('Please select a case for the evidence');
                return false;
            }

            if (!file) {
                e.preventDefault();
                alert('Please select a file to upload');
                return false;
            }

            if (file.size > 5 * 1024 * 1024) {
                e.preventDefault();
                alert('File size must be less than 5MB');
                return false;
            }

            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                e.preventDefault();
                alert('Only PDF, JPG, and PNG files are allowed');
                return false;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = '⏳ Uploading...';
            submitBtn.disabled = true;

            setTimeout(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }, 10000);
        });

        document.getElementById('evidence_file').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {

                const fileInfo = document.getElementById('file-info') || createFileInfoDiv();
                const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                fileInfo.innerHTML = `Selected: ${file.name} (${sizeMB}MB)`;

                if (file.size > 5 * 1024 * 1024) {
                    fileInfo.style.color = 'var(--danger-red)';
                    fileInfo.innerHTML += ' - File too large!';
                } else {
                    fileInfo.style.color = 'var(--success-green)';
                }
            }
        });

        function createFileInfoDiv() {
            const div = document.createElement('div');
            div.id = 'file-info';
            div.className = 'form-help';
            document.getElementById('evidence_file').parentNode.appendChild(div);
            return div;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const caseId = urlParams.get('case_id');

            if (caseId) {
                const caseSelect = document.getElementById('case_id');
                if (caseSelect) {
                    caseSelect.value = caseId;
                }
            }
        });

        let uploading = false;

        document.getElementById('uploadForm').addEventListener('submit', function() {
            uploading = true;
        });

        window.addEventListener('beforeunload', function(e) {
            if (uploading) {
                e.returnValue = 'File upload in progress. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        window.addEventListener('load', function() {
            uploading = false;
        });
    </script>

    <style>
        .evidence-guidelines {
            background: var(--light-gray);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            /* border-left: 4px solid var(--primary-green); */
        }

        .evidence-guidelines h5 {
            color: var(--primary-green);
            margin-bottom: 1rem;
        }

        .evidence-guidelines ul {
            margin-bottom: 0;
            padding-left: 1.2rem;
        }

        .evidence-grid {
            display: grid;
            gap: 1rem;
        }

        .evidence-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            background: var(--primary-white);
            align-items: center;
        }

        .evidence-icon {
            font-size: 2rem;
            text-align: center;
        }

        .evidence-filename {
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 0.25rem;
        }

        .evidence-description {
            color: var(--medium-gray);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            font-style: italic;
        }

        .evidence-meta {
            font-size: 0.8rem;
        }

.evidence-actions {
            display: inline-flex;
            gap: 0.5rem;
            align-items: center;
        }

        .evidence-actions .btn-sm {
            min-width: 80px;
            min-height: 38px;
        }

        .evidence-actions form {
            display: inline-flex;
            align-items: center;
        }

        .evidence-actions .btn {
            /* width: 80px; */
            text-align: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .d-grid[style*="2fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            .evidence-item {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .evidence-actions {
                flex-direction: row;
                justify-content: center;
            }
        }

        .form-file {
            padding: 0.5rem;
            border: 2px dashed var(--light-gray);
            transition: var(--transition);
        }

        .form-file:hover {
            border-color: var(--primary-green);
            background: rgba(0, 107, 63, 0.05);
        }

        .form-file:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 107, 63, 0.25);
        }
    </style>
</body>
</html>




