<?php
define('UTUMISHI_WEB_APP', true);
session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';
require_once __DIR__ . '/../../includes/classes/Officer.php';
require_once __DIR__ . '/../../includes/classes/CaseManager.php';

// County Commander access - no role check needed

$currentUser = getCurrentUser();
$db = Database::getInstance();

// Get county for filtering
$userDetails = $db->fetchOne("SELECT county_in_charge FROM users WHERE id = :id", ['id' => $currentUser['id']]);
$county = $userDetails['county_in_charge'] ?? null;

$pageTitle = "Search Cases";
$searchResults = [];
$error = null;
$success = null;

// Support both POST and GET for case search (GET used when linked from station_cases.php)
$obNumberInput = $_POST['ob_number'] ?? ($_GET['ob_number'] ?? ($_GET['search'] ?? ''));

if ($obNumberInput) {
    // Pre-fill for GET requests but process as POST logic
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['ob_number'] = $obNumberInput;
    }
    
    $obNumber = sanitizeText(trim($obNumberInput));
    
    if (empty($obNumber)) {
        $error = "Please enter an OB number";
    } elseif (!preg_match('/^OB-[A-Z0-9]+-\d{4}-\d{5}$/', $obNumber)) {
        $error = "Invalid OB number format";
    } else {
        try {
            // Query case by OB number with county filter
            $case = $db->fetchOne("
                SELECT c.*,
                       u1.name as reporter_name,
                       c.reporter_anonymized,
                       u2.name as recorded_by_name,
                       u3.name as assigned_officer_name,
                       o.badge_number,
                       s.name as station_name,
                       u1.national_id as reporter_national_id,
                       u1.phone as reporter_phone
                FROM cases c
                LEFT JOIN users u1 ON c.reported_by_citizen_id = u1.id
                LEFT JOIN users u2 ON c.recorded_by_officer_id = u2.id
                LEFT JOIN officers o ON c.assigned_officer_id = o.id
                LEFT JOIN users u3 ON o.user_id = u3.id
                LEFT JOIN stations s ON c.station_id = s.id
                WHERE c.ob_number = ? AND c.incident_location_county = ?
            ", [$obNumber, $county]);
            
            if ($case) {
                // For county commanders, full access to cases in their county
                $hasFullAccess = true;

                if ($hasFullAccess) {
                    // Fetch additional data: case updates and evidence
                    $caseUpdates = $db->fetchAll("
                        SELECT cu.*, u.name as officer_name, o.badge_number
                        FROM case_updates cu
                        LEFT JOIN users u ON cu.officer_id = u.id
                        LEFT JOIN officers o ON u.id = o.user_id
                        WHERE cu.case_id = :case_id
                        ORDER BY cu.created_at DESC
                    ", ['case_id' => $case['id']]);

                    $caseEvidence = $db->fetchAll("
                        SELECT ce.*, u.name as uploaded_by_name
                        FROM case_evidence ce
                        LEFT JOIN users u ON ce.uploaded_by_officer_id = u.id
                        WHERE ce.case_id = :case_id
                        ORDER BY ce.uploaded_at DESC
                    ", ['case_id' => $case['id']]);

                    $case['caseUpdates'] = $caseUpdates;
                    $case['caseEvidence'] = $caseEvidence;

                    $searchResults = [$case];
                    $success = "Case found with full details";
                } else {
                    // Limited access - show minimal info
                    $searchResults = [[
                        'id' => $case['id'],
                        'ob_number' => $case['ob_number'],
                        'title' => $case['title'],
                        'category' => $case['category'],
                        'status' => $case['status'],
                         'incident_location_county' => $case['incident_location_county'],
                         'incident_location_constituency' => $case['incident_location_constituency'],
                        'created_at' => $case['created_at'],
                        'station_name' => $case['station_name'],
                        'limited_access' => true
                    ]];
                    $success = "Case found (limited details - not assigned to you)";
                }
            } else {
                $error = "Case not found";
            }
        } catch (Exception $e) {
            error_log("Search case error: " . $e->getMessage());
            $error = "Search failed. Please try again.";
        }
    }
}

$pageTitle = "Search Cases";
?>

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
        font-weight: bold;
        color: var(--primary-green);
        margin-bottom: 0.5rem;
    }

    .timeline-content {
        line-height: 1.5;
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

<?php
require_once __DIR__ . '/../../includes/layout/layout.php';
?>

        <main class="app-main">
            <?php flashMessage(); ?>

            <div class="mb-4">
                <h2>Search County Cases</h2>
                <p class="text-muted">Search for cases by OB number</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h3>Search by OB Number</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="d-flex justify-content-center align-items-center">
                            <label for="ob_number" style="margin-right: 16px; margin-bottom: 16px; padding-top: 8px; font-weight: bold;">OB Number</label>
                            <input type="text"
                                   class="form-control"
                                   id="ob_number"
                                   name="ob_number"
                                   placeholder="OB-NRB-2025-00001"
                                   value="<?php echo htmlspecialchars($obNumberInput); ?>"
                                   required
                                   style="width: 350px; margin-right: 24px;">
                            <button type="submit" class="btn btn-primary">
                                Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($searchResults)): ?>
                <?php foreach ($searchResults as $case): ?>
                    <div class="mb-4">
                        <h2>Case Details - <?php echo htmlspecialchars($case['ob_number']); ?></h2>
                        <p class="text-muted">Full case information</p>
                    </div>


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
                                    <p><strong>County:</strong> <?php echo htmlspecialchars($case['incident_location_county']); ?></p>
                                    <p><strong>Constituency:</strong> <?php echo htmlspecialchars($case['incident_location_constituency']); ?></p>
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
                            <p><strong>Name:</strong> <?php echo !empty($case['reporter_anonymized']) ? '<span style="color:#dc3545;font-weight:bold;">ANONYMIZED</span>' : htmlspecialchars($case['reporter_name']); ?></p>
                            <p><strong>National ID:</strong> <?php echo htmlspecialchars($case['reporter_national_id'] ?? 'Not available'); ?></p>
                            <p><strong>Phone:</strong> <?php echo !empty($case['reporter_phone']) ? htmlspecialchars($case['reporter_phone']) : 'Not available'; ?></p>
                        </div>
                    </div>

                    <?php if (!empty($case['caseEvidence'])): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3>Evidence Files</h3>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <?php foreach ($case['caseEvidence'] as $evidence): ?>
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

                    <?php if (!empty($case['caseUpdates'])): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3>Case Updates</h3>
                            </div>
                            <div class="card-body">
                                <div class="case-timeline">
                                    <?php foreach (array_reverse($case['caseUpdates']) as $update): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-date">
                                                <?php echo date('M d, Y \a\t H:i', strtotime($update['created_at'])); ?>
                                            </div>
                                            <div class="timeline-content">
                                                <div class="d-flex justify-between items-center mb-2">
                                                    <strong>
                                                        Status Change
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
                                            <?php echo date('M d, Y \a\t H:i', strtotime($case['created_at'])); ?>
                                        </div>
                                        <div class="timeline-content">
                                            <strong>Case Reported</strong><br>
                                            Initial report filed at <?php echo htmlspecialchars($case['station_name']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card-footer">
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                            ← Back to Search
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>