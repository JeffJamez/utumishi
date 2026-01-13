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
$canEdit = false;

if (!empty($_GET['id'])) {
    $caseId = (int)$_GET['id'];

    try {
        $case = $caseManager->getCaseById($caseId, $currentUser['id']);

        if (!$case) {
            $errors['general'] = 'Case not found.';
        } else {
            // Check if officer can view: recorded or assigned
            $isRecorder = $case['recorded_by_officer_id'] == $currentUser['id'];
            $isAssigned = $case['assigned_officer_id'] == $currentUser['id'];

            if (!$isRecorder && !$isAssigned) {
                $errors['general'] = 'You do not have permission to view this case.';
            } else {
                $canEdit = $isAssigned; // Can edit if assigned
                $caseUpdates = $caseManager->getCaseUpdates($caseId);
                $caseEvidence = getCaseEvidence($caseId);
            }
        }
    } catch (Exception $e) {
        error_log("Case Load Error: " . $e->getMessage());
        $errors['general'] = 'Unable to load case details.';
    }
} else {
    $errors['general'] = 'No case ID provided.';
}

$pageTitle = "View Case";

require_once __DIR__ . '/../../includes/layout/layout.php';

?>
        <main class="app-main">
            <?php flashMessage(); ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php elseif ($case): ?>
                <div class="mb-4">
                    <h2>Case Details - <?php echo htmlspecialchars($case['ob_number']); ?></h2>
                    <p class="text-muted">View case information</p>
                </div>

                <?php if ($canEdit): ?>
                    <div class="mb-4">
                        <a href="<?php echo BASE_URL; ?>/pages/officer/update_case.php?id=<?php echo $caseId; ?>" class="btn btn-primary">
                            Edit Case
                        </a>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Case Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Basic Details</h5>
                                <p><strong>OB Number:</strong> <?php echo htmlspecialchars($case['ob_number']); ?></p>
                                <p><strong>Title:</strong> <?php echo htmlspecialchars($case['title']); ?></p>
                                <p><strong>Category:</strong> <?php echo htmlspecialchars(CRIME_CATEGORIES[$case['category']] ?? $case['category']); ?></p>
                                <p><strong>Status:</strong>
                                    <span class="badge <?php echo STATUS_COLORS[$case['status']] ?? 'status-reported'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                    </span>
                                </p>
                                <p><strong>Reported:</strong> <?php echo htmlspecialchars($case['created_at']); ?></p>
                                <p><strong>Last Updated:</strong> <?php echo htmlspecialchars($case['updated_at'] ?? $case['created_at']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Description</h5>
                                <p><?php echo nl2br(htmlspecialchars($case['description'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Location Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Incident Location</h5>
                                <p><strong>County:</strong> <?php echo htmlspecialchars($case['location_county']); ?></p>
                                <p><strong>Constituency:</strong> <?php echo htmlspecialchars($case['location_constituency']); ?></p>
                                <p><strong>Local Area:</strong> <?php echo htmlspecialchars($case['incident_local_area'] ?: 'Not specified'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Reporter Residence</h5>
                                <p><strong>County:</strong> <?php echo htmlspecialchars($case['reporter_county']); ?></p>
                                <p><strong>Constituency:</strong> <?php echo htmlspecialchars($case['reporter_constituency']); ?></p>
                                <p><strong>Local Area:</strong> <?php echo htmlspecialchars($case['reporter_local_area'] ?: 'Not specified'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Reporter Information</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($case['reporter_name']); ?></p>
                        <p><strong>National ID:</strong> <?php echo htmlspecialchars($case['reporter_national_id'] ?? 'Not available'); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($case['reporter_phone']); ?></p>
                    </div>
                </div>

                <?php if (!empty($caseEvidence)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3>Evidence Files</h3>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <?php foreach ($caseEvidence as $evidence): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-between items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($evidence['original_filename']); ?></strong>
                                                <br><small class="text-muted">Uploaded by <?php echo htmlspecialchars($evidence['uploaded_by_name']); ?> on <?php echo htmlspecialchars($evidence['uploaded_at']); ?></small>
                                            </div>
                                            <a href="<?php echo BASE_URL; ?>/pages/officer/download_evidence.php?id=<?php echo $evidence['id']; ?>" class="btn btn-sm btn-outline btn-secondary">
                                                Download
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($caseUpdates)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3>Case Updates</h3>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php foreach ($caseUpdates as $update): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <p><?php echo nl2br(htmlspecialchars($update['update_text'])); ?></p>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($update['officer_name']); ?> (<?php echo htmlspecialchars($update['badge_number'] ?? 'N/A'); ?>) • <?php echo htmlspecialchars($update['created_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card-footer">
                    <a href="<?php echo BASE_URL; ?>/pages/officer/my_cases.php" class="btn btn-secondary">
                        ← Back to My Cases
                    </a>
                </div>
            <?php endif; ?>
        </main>

        <style>
            .timeline {
                position: relative;
                padding-left: 30px;
            }

            .timeline-item {
                position: relative;
                margin-bottom: 20px;
            }

            .timeline-marker {
                position: absolute;
                left: -35px;
                top: 5px;
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: var(--primary-green);
            }

            .timeline-content {
                background: var(--light-gray);
                padding: 15px;
                border-radius: 8px;
            }

            .row {
                display: flex;
                flex-wrap: wrap;
                margin: 0 -15px;
            }

            .col-md-6 {
                flex: 0 0 50%;
                max-width: 50%;
                padding: 0 15px;
            }

            @media (max-width: 768px) {
                .col-md-6 {
                    flex: 0 0 100%;
                    max-width: 100%;
                }
            }
        </style>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
</body>
</html>