<?php
define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/validation.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';
require_once __DIR__ . '/../../includes/classes/CaseManager.php';
require_once __DIR__ . '/../../includes/classes/Station.php';
require_once __DIR__ . '/../../includes/classes/WorkloadManager.php';

requireRole(ROLE_OCS);

$currentUser = getCurrentUser();
$stationId = $currentUser['station_id'];
$caseManager = new CaseManager();

$caseId = (int)($_GET['id'] ?? 0);
$caseDetails = null;
$caseUpdates = [];
$error = '';
$success = '';

if ($caseId > 0) {
    $caseDetails = $caseManager->getCaseById($caseId);

    if (!$caseDetails || $caseDetails['station_id'] != $stationId) {
        $error = 'Case not found or you do not have permission to view it';
        $caseDetails = null;
    } else {
        $caseUpdates = $caseManager->getCaseUpdates($caseId);
        
        $station = new Station($stationId);
        $stationOfficers = $station->getOfficers();
    }
} else {
    $error = 'Invalid case ID';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$caseDetails) {
        throw new Exception('Invalid case - cannot process update');
    }

    try {
        if (!validateCSRF($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid request. Please try again.');
        }

        $action = sanitizeText($_POST['action'] ?? '');

        if ($action === 'close_case') {
            $updateText = sanitizeText($_POST['update_text'] ?? 'Case closed');
            $caseManager->updateCaseStatus($caseId, 'closed', $currentUser['id'], $updateText);
            $success = 'Case closed successfully';

        } elseif ($action === 'reopen_case') {
            $updateText = sanitizeText($_POST['update_text'] ?? 'Case reopened');
            $caseManager->updateCaseStatus($caseId, 'in_progress', $currentUser['id'], $updateText);
            $success = 'Case reopened successfully';

        } elseif ($action === 'add_update') {
            $updateText = sanitizeText($_POST['update_text']);
            if (empty($updateText)) {
                throw new Exception('Please enter update text');
            }

            $currentStatus = $caseDetails['status'];
            $caseManager->addCaseUpdate($caseId, $currentUser['id'], $updateText, $currentStatus, $currentStatus);
            $success = 'Case update added successfully';
            
        } elseif ($action === 'reassign_case') {
            $workloadManager = new WorkloadManager();
            $toOfficerId = (int)$_POST['to_officer_id'];
            $reason = sanitizeText($_POST['reason'] ?? '');
            $fromOfficerId = $caseDetails['assigned_officer_id'];
            
            if (!$fromOfficerId) {
                throw new Exception('Case is not assigned to any officer');
            }
            
            $result = $workloadManager->reassignCase($caseId, $fromOfficerId, $toOfficerId, $currentUser['id'], $reason);
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $caseDetails = $caseManager->getCaseById($caseId);
            $success = 'Case reassigned to ' . htmlspecialchars($caseDetails['assigned_officer_name']);
        }

        $caseDetails = $caseManager->getCaseById($caseId);
        $caseUpdates = $caseManager->getCaseUpdates($caseId);

    } catch (Exception $e) {
        error_log("OCS Case Details Error: " . $e->getMessage());
        $error = $e->getMessage();
    }
}


$pageTitle = "Case Details";
require_once __DIR__ . '/../../includes/layout/layout.php';

?>

        <main class="app-main">
            <div class="mb-4">
                <h1>Case Details</h1>
                <p class="text-muted">View and manage case information</p>
            </div>

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

            <?php if ($caseDetails): ?>
                <!-- Case Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Case Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($caseDetails['status'] !== 'closed'): ?>
                                <form method="POST" style="display: inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="close_case">
                                    <input type="hidden" name="update_text" value="Case closed by OCS">
                                    <button type="submit"
                                            style="background-color: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; min-width: 120px;">
                                        Close Case
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="reopen_case">
                                    <input type="hidden" name="update_text" value="Case reopened by OCS">
                                    <button type="submit"
                                            style="background-color: #ffc107; color: black; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; min-width: 120px;">
                                        Reopen Case
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($caseDetails['assigned_officer_id'] && $caseDetails['status'] !== 'closed'): ?>
                                <button type="button" class="btn btn-primary" style="padding: 8px 16px; border-radius: 4px; font-size: 14px; min-width: 120px;" onclick="openModal('reassignModal')">
                                    Reassign
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

<!-- Reassign Case Modal -->
                <?php if ($caseDetails['assigned_officer_id'] && $caseDetails['status'] !== 'closed'): ?>
                <div id="reassignModal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title">Reassign Case</h3>
                            <button type="button" class="btn-close" onclick="closeModal('reassignModal')">&times;</button>
                        </div>
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <div class="modal-body">
                                <p><strong>Current Officer:</strong> <?php echo htmlspecialchars($caseDetails['assigned_officer_name']); ?></p>
                                <p><strong>OB Number:</strong> <?php echo htmlspecialchars($caseDetails['ob_number']); ?></p>
                                
                                <div class="mb-3">
                                    <label for="to_officer_id" class="form-label">Reassign to Officer *</label>
                                    <select name="to_officer_id" id="to_officer_id" class="form-control" required onchange="toggleReassignButton()">
                                        <option value="">Select Officer</option>
                                        <?php if (!empty($stationOfficers)): ?>
                                            <?php foreach ($stationOfficers as $officer): ?>
                                                <?php if ($officer['id'] != $caseDetails['assigned_officer_id']): ?>
                                                    <option value="<?php echo $officer['id']; ?>">
                                                        <?php echo htmlspecialchars($officer['name']); ?> (<?php echo htmlspecialchars($officer['badge_number']); ?>) - <?php echo $officer['current_case_load']; ?> cases
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="reason" class="form-label">Reason (optional)</label>
                                    <textarea name="reason" id="reason" class="form-control" rows="2" placeholder="Enter reason for reassignment..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('reassignModal')">Cancel</button>
                                <button type="submit" name="action" value="reassign_case" class="btn btn-primary" id="confirmReassignBtn" disabled>Confirm Reassignment</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                 <div class="col-md-4">
                        <!-- Case Details Panel -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4>Case Details</h4>
                            </div>
                            <div class="card-body" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem 2rem;">
                                <div>
                                    <div class="mb-3">
                                        <strong>Current Status</strong><br>
                                        <span class="badge <?php echo STATUS_COLORS[$caseDetails['status']] ?? 'status-reported'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $caseDetails['status'])); ?>
                                        </span>
                                    </div>

                                    <div class="mb-3">
                                        <strong>Reporter</strong><br>
                                        <?php if (!empty($caseDetails['reporter_anonymized'])): ?>
                                            <span class="text-danger" style="font-weight: bold;">ANONYMIZED</span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($caseDetails['reporter_name'] ?? 'N/A'); ?><br>
                                            <?php if ($caseDetails['reporter_phone']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($caseDetails['reporter_phone']); ?></small>
                                            <?php endif; ?>
                                            <?php if ($caseDetails['reporter_national_id']): ?>
                                                <br><small class="text-muted">ID: <?php echo htmlspecialchars($caseDetails['reporter_national_id']); ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <strong>Location</strong><br>
                                        <small><?php echo htmlspecialchars($caseDetails['incident_location_constituency']); ?>, <?php echo htmlspecialchars($caseDetails['incident_location_county']); ?></small>
                                    </div>
                                </div>
                                <div>
                                    <div class="mb-3">
                                        <strong>Assigned Officer</strong><br>
                                        <?php if ($caseDetails['assigned_officer_name']): ?>
                                            <?php echo htmlspecialchars($caseDetails['assigned_officer_name']); ?><br>
                                            <small class="text-muted">Badge: <?php echo htmlspecialchars($caseDetails['badge_number']); ?></small>
                                            <?php if ($caseDetails['assigned_officer_national_id']): ?>
                                                <br><small class="text-muted">ID: <?php echo htmlspecialchars($caseDetails['assigned_officer_national_id']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not yet assigned</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <strong>Assigned At</strong><br>
                                        <?php if ($caseDetails['assigned_at']): ?>
                                            <small><?php echo date('M d, Y \a\t H:i', strtotime($caseDetails['assigned_at'])); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <strong>Recorded By</strong><br>
                                        <small><?php echo htmlspecialchars($caseDetails['recorded_by_name']); ?></small>
                                    </div>
                                </div>
                                <div>
                                    <div class="mb-3">
                                        <strong>Occurred</strong><br>
                                        <?php if ($caseDetails['occurred_at']): ?>
                                            <small><?php echo date('M d, Y \a\t H:i', strtotime($caseDetails['occurred_at'])); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <strong>Created</strong><br>
                                        <small><?php echo date('M d, Y \a\t H:i', strtotime($caseDetails['created_at'])); ?></small>
                                    </div>

                                    <div class="mb-3">
                                        <strong>Last Updated</strong><br>
                                        <small><?php echo date('M d, Y \a\t H:i', strtotime($caseDetails['updated_at'])); ?></small>
                                    </div>

                                    <?php if ($caseDetails['status'] === 'closed' && $caseDetails['closed_at']): ?>
                                        <div class="mb-3">
                                            <strong>Closed</strong><br>
                                            <small><?php echo date('M d, Y \a\t H:i', strtotime($caseDetails['closed_at'])); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>                       
                    </div>

                <!-- Case Information -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4>Case Information</h4>
                            </div>
                            <div class="card-body">

                                <div class="mb-3">
                                    <strong>Description:</strong>
                                    <div class="mt-1 p-2" style="background: var(--light-gray); border-radius: var(--border-radius); white-space: pre-line;">
                                        <?php echo htmlspecialchars($caseDetails['description']); ?>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <strong>Category:</strong>
                                            <span class="badge status-assigned"><?php echo htmlspecialchars($caseDetails['category']); ?></span>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Location:</strong>
                                             <?php echo htmlspecialchars($caseDetails['incident_location_county']); ?>, <?php echo htmlspecialchars($caseDetails['incident_location_constituency']); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <strong>OB Number:</strong>
                                            <span style="font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($caseDetails['ob_number']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                         <!-- Add Update Form -->
                        <div class="card">
                            <div class="card-header">
                                <h4>Add Case Update</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="add_update">

                                    <div class="mb-3">
                                        <label for="update_text" class="form-label">Update Details</label>
                                        <textarea name="update_text" id="update_text" class="form-control" rows="3" required></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Add Update</button>
                                </form>
                            </div>
                        </div>

                        <!-- Case Updates/Timeline -->
                        <?php if (!empty($caseUpdates)): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h4>Case Timeline</h4>
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
                                                            by <?php echo htmlspecialchars($update['officer_name']); ?>
                                                        </small>
                                                    </div>
                                                    <div><?php echo nl2br(htmlspecialchars($update['update_text'])); ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                        <div class="timeline-item">
                                            <div class="timeline-date">
                                                <?php echo date('M d, Y \a\t H:i', strtotime($caseDetails['created_at'])); ?>
                                            </div>
                                            <div class="timeline-content">
                                                <strong>Case Reported</strong><br>
                                                Initial report filed at <?php echo htmlspecialchars($caseDetails['station_name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php else: ?>
                    <div class="text-center p-5">
                        <h4>Case Not Found</h4>
                        <p class="text-muted">The requested case could not be found or you do not have permission to view it.</p>
                        <a href="<?php echo BASE_URL; ?>/pages/ocs/station_cases.php" class="btn btn-primary">Back to Cases</a>
                    </div>
                <?php endif; ?>
        </main>
    </div>
    
    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>
        setInterval(function() {
            if (!document.hidden) {
                location.reload();
            }
        }, 180000);
        
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function toggleReassignButton() {
            var select = document.getElementById('to_officer_id');
            var btn = document.getElementById('confirmReassignBtn');
            btn.disabled = select.value === '';
        }
        
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.table-danger').forEach(row => {
                row.style.borderLeft = '4px solid var(--danger-red)';
            });
            
            document.querySelectorAll('.table-warning').forEach(row => {
                row.style.borderLeft = '4px solid var(--warning-orange)';
            });
            
            document.querySelectorAll('.table-info').forEach(row => {
                row.style.borderLeft = '4px solid var(--info-blue)';
            });
        });
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
            border: 1px solid var(--light-gray);
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

        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 500;
        }

        .btn-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
    </style>
</body>
</html>