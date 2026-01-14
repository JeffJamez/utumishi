<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/validation.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';
require_once __DIR__ . '/../../includes/classes/Officer.php';
require_once __DIR__ . '/../../includes/classes/CaseManager.php';
require_once __DIR__ . '/../../includes/utils/file_upload.php';

requireRole(ROLE_OFFICER);

$currentUser = getCurrentUser();
$officer = new Officer($currentUser['id']);
$caseManager = new CaseManager();

$case = null;
$caseUpdates = null;
$caseEvidence = null;
$errors = [];
$success = '';
$caseId = null;

if (!empty($_GET['id'])) {
    $caseId = (int)$_GET['id'];

    try {
        $case = $caseManager->getCaseById($caseId, $currentUser['id']);

        if (!$case) {
            $errors['general'] = 'Case not found.';
        } elseif (!$officer->canPerformAction('update_case', $caseId)) {
            $errors['general'] = 'You do not have permission to update this case.';
        } else {
            $caseUpdates = $caseManager->getCaseUpdates($caseId);
            $caseEvidence = getCaseEvidence($caseId);
        }
        } catch (Exception $e) {
        error_log("Case Load Error: " . $e->getMessage());
        $errors['general'] = 'Unable to load case details.';

        echo '<div style="font-family:monospace; background:#fef0f0; color:#721c24; border:2px solid #f5c6cb; padding:20px; margin:20px; border-radius:8px; white-space:pre-wrap;">';
        echo '<h3 style="margin-top:0; color:#842029;">🚨 Dashboard Error (Debug Mode)</h3>';
        echo '<strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '<br><br>';
        echo '<strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '<br><br>';
        echo '<strong>Line:</strong> ' . $e->getLine() . '<br><br>';
        echo '<strong>Trace:</strong><br><pre style="background:#fff; padding:10px; border:1px solid #f5c6cb; overflow:auto;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';

        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $case && empty($errors['general'])) {
    try {
        if (!validateCSRF($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid request. Please try again.');
        }

        $action = sanitizeText($_POST['action'] ?? '');

        if ($action === 'update_case') {
            $updateData = [
                'status' => sanitizeText($_POST['status'] ?? ''),
                'update_notes' => sanitizeText($_POST['update_notes'] ?? '')
            ];

            if (empty($updateData['status'])) {
                $errors['status'] = 'Status is required';
            } else {
                $statusValidation = validateCaseStatus($updateData['status']);
                if (!$statusValidation['valid']) {
                    $errors['status'] = $statusValidation['message'];
                }
            }

            if (empty($updateData['update_notes'])) {
                $errors['update_notes'] = 'Update notes are required';
            }

            if (empty($errors)) {
                $result = $caseManager->updateCase($caseId, $updateData, $currentUser['id']);

                if ($result['success']) {
                    $success = $result['message'];
                    $case = $caseManager->getCaseById($caseId, $currentUser['id']);
                    $caseUpdates = $caseManager->getCaseUpdates($caseId);
                    setFlashMessage('success', 'Case updated successfully');
                } else {
                    $errors['general'] = $result['message'];
                }
            }
        } elseif ($action === 'request_closure') {
            // Check if case is resolved
            if ($case['status'] !== CASE_RESOLVED) {
                $errors['general'] = 'Only resolved cases can be requested for closure.';
            } else {
                // Check if already requested
                $existing = $db->fetchOne("SELECT id FROM closure_requests WHERE case_id = ? AND status = 'pending'", [$caseId]);
                if ($existing) {
                    $errors['general'] = 'Closure request already pending for this case.';
                } else {
                    $db->insert('closure_requests', [
                        'case_id' => $caseId,
                        'requested_by' => $currentUser['id']
                    ]);
                    $success = 'Closure request submitted successfully. Awaiting OCS approval.';
                    setFlashMessage('success', $success);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Case Update Error: " . $e->getMessage());
        $errors['general'] = $e->getMessage();
    }
}

$availableStatuses = [
    CASE_ASSIGNED => 'Assigned',
    CASE_IN_PROGRESS => 'In Progress',
    CASE_RESOLVED => 'Resolved'
];

if ($case) {
    switch ($case['status']) {
        case CASE_REPORTED:
            $availableStatuses = [
                CASE_ASSIGNED => 'Assigned',
                CASE_IN_PROGRESS => 'In Progress'
            ];
            break;
        case CASE_ASSIGNED:
            $availableStatuses = [
                CASE_IN_PROGRESS => 'In Progress',
                CASE_RESOLVED => 'Resolved'
            ];
            break;
        case CASE_IN_PROGRESS:
            $availableStatuses = [
                CASE_RESOLVED => 'Resolved'
            ];
            break;
        case CASE_RESOLVED:
            $availableStatuses = [];
            break;
    }
}

$pageTitle = $case ? "Update Case - " . $case['ob_number'] : "Update Case";

require_once __DIR__ . '/../../includes/layout/layout.php';

?>

        <main class="app-main">
            <?php flashMessage(); ?>

            <div class="mb-4">
                <h2>Update Station Case</h2>
                <p class="text-muted">
                    <?php if ($case): ?>
                        Update status and add information for case <?php echo htmlspecialchars($case['ob_number']); ?>
                    <?php else: ?>
                        Select a case to update from your assigned cases or cases in your station
                    <?php endif; ?>
                </p>
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

            <?php if (!$case && empty($errors['general'])): ?>

                <div class="card">
                    <div class="card-header">
                        <h3>Select Case to Update</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $assignedCases = $officer->getAssignedCases();
                        } catch (Exception $e) {
                            $assignedCases = [];
                        }
                        ?>

                        <?php if (!empty($assignedCases)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>OB Number</th>
                                            <th>Case Details</th>
                                            <th>Status</th>
                                            <!-- <th>Time Since Reported</th> -->
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignedCases as $assignedCase): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($assignedCase['ob_number']); ?></strong>
                                                </td>
                                                <td>
                                                    <div><?php echo htmlspecialchars($assignedCase['title']); ?></div>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($assignedCase['category']); ?> • 
                                                        <?php echo htmlspecialchars($assignedCase['reporter_name']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo STATUS_COLORS[$assignedCase['status']] ?? 'status-reported'; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $assignedCase['status'])); ?>
                                                    </span>
                                                </td>
                                               <!--  <td>
                                                    <?php 
                                                    $hours = round($assignedCase['hours_since_reported']);
                                                    $isOverdue = $hours > $assignedCase['estimated_resolution_hours'];
                                                    ?>
                                                    <div class="<?php echo $isOverdue ? 'text-danger' : ''; ?>">
                                                        <?php echo $hours; ?>h ago
                                                        <?php if ($isOverdue): ?>
                                                            <br><small><strong>Overdue</strong></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td> -->
                                                <td>
                                                    <a href="<?php echo BASE_URL; ?>/pages/officer/update_case.php?id=<?php echo $assignedCase['id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        Update Case
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
                                <h4>No Cases Assigned</h4>
                                <p class="text-muted">You don't have any cases assigned to update.</p>
                                <a href="<?php echo BASE_URL; ?>/pages/officer/dashboard.php" class="btn btn-primary">
                                    Return to Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($case): ?>

                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: 2rem;">

                    <div class="card">
                        <div class="card-header">
                            <h3>Case Information - <?php echo htmlspecialchars($case['ob_number']); ?></h3>
                            <span class="badge <?php echo STATUS_COLORS[$case['status']] ?? 'status-reported'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Title:</strong><br>
                                <?php echo htmlspecialchars($case['title']); ?>
                            </div>

                            <div class="mb-3">
                                <strong>Description:</strong><br>
                                <div class="p-2" style="background: var(--light-gray); border-radius: var(--border-radius); white-space: pre-line;">
                                    <?php echo htmlspecialchars($case['description']); ?>
                                </div>
                            </div>

                            <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <strong>Reporter:</strong><br>
                                    <?php echo htmlspecialchars($case['reporter_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($case['reporter_phone']); ?></small>
                                </div>
                                <div>
                                    <strong>Category:</strong><br>
                                    <span class="badge status-assigned"><?php echo htmlspecialchars($case['category']); ?></span>
                                </div>
                            </div>

                            <div class="mt-3">
                                <strong>Location:</strong><br>
                                 <?php echo htmlspecialchars($case['incident_location_constituency']); ?>,
                                 <?php echo htmlspecialchars($case['incident_location_county']); ?>
                            </div>

                            <div class="mt-3">
                                <strong>Dates:</strong><br>
                                <small>
                                    Reported: <?php echo date('M d, Y \a\t H:i', strtotime($case['created_at'])); ?><br>
                                    Last Updated: <?php echo date('M d, Y \a\t H:i', strtotime($case['updated_at'])); ?>
                                    <?php if ($case['closed_at']): ?>
                                        <br>Closed: <?php echo date('M d, Y \a\t H:i', strtotime($case['closed_at'])); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Update Case Status</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($availableStatuses)): ?>
                                <form method="POST" action="" id="updateCaseForm">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="update_case">

                                    <div class="form-group">
                                        <label for="status" class="form-label">New Status *</label>
                                        <select
                                            id="status"
                                            name="status"
                                            class="form-control form-select <?php echo isset($errors['status']) ? 'error' : ''; ?>"
                                            required
                                        >
                                            <option value="">Select new status...</option>
                                            <?php foreach ($availableStatuses as $value => $label): ?>
                                                <option value="<?php echo htmlspecialchars($value); ?>">
                                                    <?php echo htmlspecialchars($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['status'])): ?>
                                            <div class="form-error"><?php echo htmlspecialchars($errors['status']); ?></div>
                                        <?php endif; ?>
                                        <div class="form-help">Select the new status for this case</div>
                                    </div>

                                    <div class="form-group">
                                        <label for="update_notes" class="form-label">Update Notes *</label>
                                        <textarea
                                            id="update_notes"
                                            name="update_notes"
                                            class="form-control form-textarea <?php echo isset($errors['update_notes']) ? 'error' : ''; ?>"
                                            placeholder="Describe what actions were taken, findings, next steps, etc.&#10;&#10;Example:&#10;- Interviewed witness at scene&#10;- Collected CCTV footage from nearby shops&#10;- Suspect identified through fingerprints&#10;- Case forwarded to court"
                                            rows="6"
                                            maxlength="1000"
                                            required
                                        ></textarea>
                                        <?php if (isset($errors['update_notes'])): ?>
                                            <div class="form-error"><?php echo htmlspecialchars($errors['update_notes']); ?></div>
                                        <?php endif; ?>
                                        <div class="form-help">Provide detailed notes about the case progress</div>
                                    </div>

                                    <button type="submit" class="btn btn-success btn-block">
                                        Update Case Status
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <div style="font-size: 3rem;">✅</div>
                                    <h4>Case Resolved</h4>
                                    <p class="text-muted">This case has been resolved. Request closure for final approval.</p>
                                    <form method="POST" action="" style="display: inline;">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="request_closure">
                                        <button type="submit" class="btn btn-primary">
                                            Request Case Closure
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Evidence Management</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-grid" style="grid-template-columns: 1fr; gap: 2rem;">

                            <div class="text-center">
                                <p class="text-muted">Evidence management is handled through the dedicated Evidence page.</p>
                                <a href="<?php echo BASE_URL; ?>/pages/officer/evidence.php?case_id=<?php echo $caseId; ?>" class="btn btn-primary">
                                    Manage Evidence
                                </a>
                            </div>

                            <div>
                                <h4>Existing Evidence</h4>
                                <?php if (!empty($caseEvidence)): ?>
                                    <div class="evidence-list">
                                        <?php foreach ($caseEvidence as $evidence): ?>
                                            <div class="alert alert-info mb-2">
                                                <div class="d-flex justify-between items-center">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($evidence['original_filename']); ?></strong>
                                                        <?php if ($evidence['description']): ?>
                                                            <br><small><?php echo htmlspecialchars($evidence['description']); ?></small>
                                                        <?php endif; ?>
                                                        <br><small class="text-muted">
                                                            Uploaded by <?php echo htmlspecialchars($evidence['uploaded_by_name']); ?> 
                                                            on <?php echo date('M d, Y', strtotime($evidence['uploaded_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <div class="text-right">
                                                        <a href="<?php echo BASE_URL; ?>/api/download_evidence.php?id=<?php echo $evidence['id']; ?>" 
                                                           class="btn btn-sm btn-outline btn-primary"
                                                           target="_blank">
                                                            Download
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center p-3" style="background: var(--light-gray); border-radius: var(--border-radius);">
                                        <p class="text-muted mb-0">No evidence uploaded yet for this case.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($caseUpdates)): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3>Case Timeline</h3>
                        </div>
                        <div class="card-body">
                            <div class="case-timeline">
                                <?php foreach (array_reverse($caseUpdates) as $update): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-date">
                                            <?php echo date('M d, Y \a\t H:i', strtotime($update['created_at'])); ?>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-between items-center mb-2">
                                                <strong>
                                                    Status: <?php echo ucfirst(str_replace('_', ' ', $update['status_before'])); ?> 
                                                    → <?php echo ucfirst(str_replace('_', ' ', $update['status_after'])); ?>
                                                </strong>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($update['officer_name']); ?>
                                                    <?php if ($update['badge_number']): ?>
                                                        (<?php echo htmlspecialchars($update['badge_number']); ?>)
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <div style="white-space: pre-line;"><?php echo htmlspecialchars($update['update_text']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <div class="timeline-item">
                                    <div class="timeline-date">
                                        <?php echo date('M d, Y \a\t H:i', strtotime($case['created_at'])); ?>
                                    </div>
                                    <div class="timeline-content">
                                        <strong>Case Reported</strong><br>
                                        Initial report filed by <?php echo htmlspecialchars($case['reporter_name']); ?> 
                                        at <?php echo htmlspecialchars($case['station_name']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>

        document.getElementById('updateCaseForm')?.addEventListener('submit', function(e) {
            const status = document.getElementById('status').value;
            const notes = document.getElementById('update_notes').value.trim();

            if (!status) {
                e.preventDefault();
                alert('Please select a status for the case update');
                return false;
            }

            if (!notes) {
                e.preventDefault();
                alert('Please provide update notes describing what actions were taken');
                return false;
            }

            if (notes.length < 10) {
                e.preventDefault();
                alert('Please provide more detailed update notes (at least 10 characters)');
                return false;
            }

            if (status === 'closed') {
                if (!confirm('Are you sure you want to close this case? This action should only be taken when the case is fully resolved and all procedures completed.')) {
                    e.preventDefault();
                    return false;
                }
            } else if (status === 'resolved') {
                if (!confirm('Mark this case as resolved? This indicates the investigation is complete but final administrative steps may remain.')) {
                    e.preventDefault();
                    return false;
                }
            }
        });



        function saveDraft() {
            const notes = document.getElementById('update_notes')?.value;
            const status = document.getElementById('status')?.value;

            if (notes || status) {
                localStorage.setItem('case_update_draft_<?php echo $caseId; ?>', JSON.stringify({
                    notes: notes,
                    status: status,
                    timestamp: Date.now()
                }));
            }
        }

        function loadDraft() {
            const draft = localStorage.getItem('case_update_draft_<?php echo $caseId; ?>');
            if (draft) {
                try {
                    const data = JSON.parse(draft);
                    const ageHours = (Date.now() - data.timestamp) / (1000 * 60 * 60);

                    if (ageHours < 24) {
                        if (data.notes && document.getElementById('update_notes')) {
                            document.getElementById('update_notes').value = data.notes;
                        }
                        if (data.status && document.getElementById('status')) {
                            document.getElementById('status').value = data.status;
                        }
                    }
                } catch (e) {
                    console.log('Could not load draft');
                }
            }
        }

        setInterval(saveDraft, 30000);

        document.addEventListener('DOMContentLoaded', function() {
            loadDraft();

            document.getElementById('updateCaseForm')?.addEventListener('submit', function() {
                setTimeout(() => {
                    localStorage.removeItem('case_update_draft_<?php echo $caseId; ?>');
                }, 1000);
            });
        });

        const notesField = document.getElementById('update_notes');
        if (notesField) {
            function updateCharCount() {
                const current = notesField.value.length;
                const max = 1000;

                let countElement = document.getElementById('notes-char-count');
                if (!countElement) {
                    countElement = document.createElement('div');
                    countElement.id = 'notes-char-count';
                    countElement.className = 'form-help';
                    notesField.parentNode.appendChild(countElement);
                }

                countElement.textContent = `${current}/${max} characters`;
                countElement.style.color = current > max * 0.9 ? 'var(--danger-red)' : 'var(--medium-gray)';
            }

            notesField.addEventListener('input', updateCharCount);
            updateCharCount();
        }
    </script>

    <style>
        .case-timeline {
            position: relative;
            padding-left: 2rem;
        }

        .case-timeline::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--primary-green);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            background: var(--primary-white);
            padding: 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            /* border-left: 3px solid var(--light-gray); */
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 1rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-green);
            border: 3px solid var(--primary-white);
        }

        .timeline-item:last-child::before {
            background: var(--medium-gray);
        }

        .timeline-date {
            font-size: 0.875rem;
            color: var(--medium-gray);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .timeline-content {
            color: var(--dark-gray);
            line-height: 1.5;
        }

        .evidence-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .form-file {
            padding: 0.5rem;
            cursor: pointer;
        }

        .form-file:hover {
            border-color: var(--primary-green);
        }

        .status-update {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        @media (max-width: 768px) {
            .d-grid[style*="1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            .d-grid[style*="1fr 2fr"] {
                grid-template-columns: 1fr !important;
            }

            .timeline-item {
                padding: 0.75rem;
            }

            .evidence-list {
                max-height: 300px;
            }
        }

        @media print {
            .btn, .form-control, .no-print {
                display: none !important;
            }

            .timeline-item {
                break-inside: avoid;
                border: 1px solid #ddd;
            }

            .card {
                border: 1px solid #000;
                box-shadow: none;
            }
        }

        .uploading {
            opacity: 0.6;
            pointer-events: none;
        }

        .uploading::after {
            content: 'Uploading...';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--primary-white);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }

        .success-flash {
            animation: successFlash 2s ease-in-out;
        }

        @keyframes successFlash {
            0% { background-color: transparent; }
            50% { background-color: rgba(40, 167, 69, 0.2); }
            100% { background-color: transparent; }
        }

        .form-control:valid {
            border-color: var(--success-green);
        }

        .form-control:invalid:not(:focus):not(:placeholder-shown):not(select) {
            border-color: var(--danger-red);
        }

        .file-drop-zone {
            border: 2px dashed var(--medium-gray);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }

        .file-drop-zone:hover,
        .file-drop-zone.dragover {
            border-color: var(--primary-green);
            background-color: rgba(0, 107, 63, 0.05);
        }

        .form-control:focus,
        .btn:focus {
            box-shadow: 0 0 0 3px rgba(0, 107, 63, 0.25);
        }

        [title] {
            position: relative;
        }

        [title]:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark-gray);
            color: var(--primary-white);
            padding: 0.5rem;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            white-space: nowrap;
            z-index: 1000;
            opacity: 0;
            animation: fadeIn 0.3s ease-in-out forwards;
        }

        @keyframes fadeIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }

        .priority-high {
            /* border-left: 4px solid var(--danger-red); */
        }

        .priority-normal {
            /* border-left: 4px solid var(--warning-orange); */
        }

        .priority-low {
            /* border-left: 4px solid var(--success-green); */
        }

        .status-progression {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .status-step {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            opacity: 0.3;
        }

        .status-step.completed {
            opacity: 1;
            background-color: var(--success-green);
            color: white;
        }

        .status-step.current {
            opacity: 1;
            background-color: var(--primary-green);
            color: white;
        }

        .status-step::after {
            content: '→';
            margin-left: 0.5rem;
            opacity: 0.5;
        }

        .status-step:last-child::after {
            display: none;
        }
    </style>
</body>
</html>
