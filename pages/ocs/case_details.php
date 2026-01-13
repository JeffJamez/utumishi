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

requireRole(ROLE_OCS);

$currentUser = getCurrentUser();
$stationId = $currentUser['station_id'];
$caseManager = new CaseManager();

$caseId = (int)($_GET['id'] ?? 0);
$caseDetails = null;
$caseUpdates = [];
$officers = [];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validateCSRF($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid request. Please try again.');
        }

        $action = sanitizeText($_POST['action'] ?? '');

        if ($action === 'update_status') {
            $newStatus = sanitizeText($_POST['new_status']);
            $updateText = sanitizeText($_POST['update_text'] ?? '');

            $validStatuses = ['reported', 'assigned', 'in_progress', 'resolved', 'closed'];
            if (!in_array($newStatus, $validStatuses)) {
                throw new Exception('Invalid status selected');
            }

            $caseManager->updateCaseStatus($caseId, $newStatus, $currentUser['id'], $updateText);
            $success = 'Case status updated successfully';

        } elseif ($action === 'reassign_officer') {
            $officerId = (int)$_POST['officer_id'];
            if (!$officerId) {
                throw new Exception('Please select an officer');
            }

            $caseManager->reassignCase($caseId, $officerId, $currentUser['id']);
            $success = 'Case reassigned successfully';

        } elseif ($action === 'add_update') {
            $updateText = sanitizeText($_POST['update_text']);
            if (empty($updateText)) {
                throw new Exception('Please enter update text');
            }

            $caseManager->addCaseUpdate($caseId, $currentUser['id'], $updateText);
            $success = 'Case update added successfully';
        }

        // Refresh case data
        $caseDetails = $caseManager->getCaseById($caseId);
        $caseUpdates = $caseManager->getCaseUpdates($caseId);

    } catch (Exception $e) {
        error_log("OCS Case Details Error: " . $e->getMessage());
        $error = $e->getMessage();
    }
}

try {
    if ($caseId > 0) {
        $caseDetails = $caseManager->getCaseById($caseId);

        if (!$caseDetails || $caseDetails['station_id'] != $stationId) {
            $error = 'Case not found or you do not have permission to view it';
            $caseDetails = null;
        } else {
            $caseUpdates = $caseManager->getCaseUpdates($caseId);
        }
    } else {
        $error = 'Invalid case ID';
    }

    // Get officers for reassignment
    $station = new Station($stationId);
    $officers = $station->getOfficers();

} catch (Exception $e) {
    error_log("Load Case Details Error: " . $e->getMessage());
    $error = 'Unable to load case details';
}

$pageTitle = $caseDetails ? "Case Details - " . htmlspecialchars($caseDetails['ob_number']) : "Case Details";

require_once __DIR__ . '/../../includes/layout/layout.php';
?>

        <main class="app-main">
            <?php flashMessage(); ?>

            <div class="mb-4">
                <h2>Case Details</h2>
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3><?php echo htmlspecialchars($caseDetails['ob_number']); ?></h3>
                        <span class="badge <?php echo STATUS_COLORS[$caseDetails['status']] ?? 'status-reported'; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $caseDetails['status'])); ?>
                        </span>
                    </div>
                    <div>
                        <a href="<?php echo BASE_URL; ?>/pages/ocs/station_cases.php" class="btn btn-outline btn-secondary">
                            ← Back to Cases
                        </a>
                    </div>
                </div>

                <!-- Case Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Case Actions</h4>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; justify-content: flex-start;">
                            <form method="POST" style="display: inline;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="new_status" value="closed">
                                <button type="submit"
                                        style="background-color: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; min-width: 120px;"
                                        <?php echo $caseDetails['status'] === 'closed' ? 'disabled style="background-color: #6c757d; cursor: not-allowed;"' : ''; ?>>
                                    Close Case
                                </button>
                            </form>

                            <form method="POST" style="display: inline;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="new_status" value="in_progress">
                                <button type="submit"
                                        style="background-color: #ffc107; color: black; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; min-width: 120px;"
                                        <?php echo in_array($caseDetails['status'], ['closed', 'in_progress']) ? 'disabled style="background-color: #6c757d; cursor: not-allowed;"' : ''; ?>>
                                    Reopen Case
                                </button>
                            </form>

                            <button type="button"
                                    style="background-color: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; min-width: 120px;"
                                    data-toggle="modal" data-target="#statusModal">
                                Update Status
                            </button>

                            <button type="button"
                                    style="background-color: #17a2b8; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; min-width: 120px;"
                                    data-toggle="modal" data-target="#assignModal">
                                Reassign Officer
                            </button>
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
                                            <?php echo htmlspecialchars($caseDetails['location_county']); ?>, <?php echo htmlspecialchars($caseDetails['location_constituency']); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <strong>Station:</strong>
                                            <?php echo htmlspecialchars($caseDetails['station_name']); ?>
                                        </div>
                                        <div class="mb-3">
                                            <strong>OB Number:</strong>
                                            <?php echo htmlspecialchars($caseDetails['ob_number']); ?>
                                        </div>
                                    </div>
                                </div>
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

                    <div class="col-md-4">
                        <!-- Case Details Panel -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4>Case Details</h4>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Current Status:</strong>
                                    <span class="badge <?php echo STATUS_COLORS[$caseDetails['status']] ?? 'status-reported'; ?>" style="font-size: 13px;">
                                        <?php echo ucfirst(str_replace('_', ' ', $caseDetails['status'])); ?>
                                    </span>
                                </div>

                                <div class="mb-3">
                                    <strong>Assigned Officer:</strong>
                                    <?php if ($caseDetails['assigned_officer_name']): ?>
                                        <div><?php echo htmlspecialchars($caseDetails['assigned_officer_name']); ?></div>
                                        <small class="text-muted">Badge: <?php echo htmlspecialchars($caseDetails['badge_number']); ?></small>
                                    <?php else: ?>
                                        <div class="text-muted">Not yet assigned</div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <strong>Reporter:</strong>
                                    <div><?php echo htmlspecialchars($caseDetails['reporter_name'] ?? 'N/A'); ?></div>
                                    <?php if ($caseDetails['reporter_phone']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($caseDetails['reporter_phone']); ?></small>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <strong>Created:</strong>
                                    <div><?php echo date('M d, Y \a\t H:i', strtotime($caseDetails['created_at'])); ?></div>
                                </div>

                                <div class="mb-3">
                                    <strong>Last Updated:</strong>
                                    <div><?php echo date('M d, Y \a\t H:i', strtotime($caseDetails['updated_at'])); ?></div>
                                </div>

                                <?php if ($caseDetails['status'] === 'closed' && $caseDetails['closed_at']): ?>
                                    <div class="mb-3">
                                        <strong>Closed:</strong>
                                        <div><?php echo date('M d, Y \a\t H:i', strtotime($caseDetails['closed_at'])); ?></div>
                                    </div>
                                <?php endif; ?>
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
                    </div>
                </div>

                <!-- Status Update Modal -->
                <div class="modal fade" id="statusModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Update Case Status</h5>
                                <button type="button" class="btn-close" data-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="update_status">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="new_status" class="form-label">New Status</label>
                                        <select name="new_status" id="new_status" class="form-control" required>
                                            <option value="reported" <?php echo $caseDetails['status'] === 'reported' ? 'selected' : ''; ?>>Reported</option>
                                            <option value="assigned" <?php echo $caseDetails['status'] === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                            <option value="in_progress" <?php echo $caseDetails['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="resolved" <?php echo $caseDetails['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                            <option value="closed" <?php echo $caseDetails['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="status_update_text" class="form-label">Update Notes (Optional)</label>
                                        <textarea name="update_text" id="status_update_text" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Status</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Officer Assignment Modal -->
                <div class="modal fade" id="assignModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Reassign Case</h5>
                                <button type="button" class="btn-close" data-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="reassign_officer">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="officer_id" class="form-label">Select Officer</label>
                                        <select name="officer_id" id="officer_id" class="form-control" required>
                                            <option value="">Choose an officer...</option>
                                            <?php foreach ($officers as $officer): ?>
                                                <option value="<?php echo $officer['id']; ?>"
                                                        <?php echo $caseDetails['assigned_officer_id'] == $officer['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($officer['name']); ?> (Badge: <?php echo htmlspecialchars($officer['badge_number']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Reassign Case</button>
                                </div>
                            </form>
                        </div>
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

            .btn-block {
                width: 100%;
            }
        </style>

        <script>
            // Auto-refresh case data every 2 minutes if case is open
            <?php if ($caseDetails && $caseDetails['status'] !== 'closed'): ?>
            setInterval(function() {
                if (!document.hidden) {
                    location.reload();
                }
            }, 120000);
            <?php endif; ?>
        </script>