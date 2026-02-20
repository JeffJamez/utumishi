<?php
define('UTUMISHI_WEB_APP', true);
session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';
require_once __DIR__ . '/../../includes/classes/Officer.php';
require_once __DIR__ . '/../../includes/classes/CaseManager.php';

requireRole(ROLE_OFFICER);

$currentUser = getCurrentUser();
$officer = new Officer($currentUser['id']);
$db = Database::getInstance();

$pageTitle = "Search Cases";
$searchResults = [];
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ob_number'])) {
    $obNumber = sanitizeText(trim($_POST['ob_number']));
    
    if (empty($obNumber)) {
        $error = "Please enter an OB number";
    } elseif (!preg_match('/^OB-[A-Z0-9]+-\d{4}-\d{5}$/', $obNumber)) {
        $error = "Invalid OB number format";
    } else {
        try {
            // Query case by OB number
            $case = $db->fetchOne("
                SELECT c.*, 
                       u1.name as reporter_name,
                       u2.name as recorded_by_name,
                       u3.name as assigned_officer_name,
                       o.badge_number,
                       s.name as station_name
                FROM cases c
                LEFT JOIN users u1 ON c.reported_by_citizen_id = u1.id
                LEFT JOIN users u2 ON c.recorded_by_officer_id = u2.id
                LEFT JOIN officers o ON c.assigned_officer_id = o.id
                LEFT JOIN users u3 ON o.user_id = u3.id
                LEFT JOIN stations s ON c.station_id = s.id
                WHERE c.ob_number = ?
            ", [$obNumber]);
            
            if ($case) {
                // Check access permissions
                $officerId = $officer->getOfficerData()['id'] ?? null;
                $hasFullAccess = ($case['assigned_officer_id'] == $officerId || 
                                $case['recorded_by_officer_id'] == $currentUser['id']);
                
                if ($hasFullAccess) {
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

require_once __DIR__ . '/../../includes/layout/layout.php';
?>

        <main class="app-main">
            <?php flashMessage(); ?>

            <div class="mb-4">
                <h2>Search Cases</h2>
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
                    <form method="POST" action="">
                        <div class="d-flex justify-content-center align-items-center">
                            <label for="ob_number" style="margin-right: 16px; margin-bottom: 16px; padding-top: 8px; font-weight: bold;">OB Number</label>
                            <input type="text"
                                   class="form-control"
                                   id="ob_number"
                                   name="ob_number"
                                   placeholder="OB-NRB-2025-00001"
                                   value="<?php echo htmlspecialchars($_POST['ob_number'] ?? ''); ?>"
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
                <div class="card">
                    <div class="card-header">
                        <h3>Search Results</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($searchResults as $case): ?>
                            <div class="case-card mb-3 p-3 border rounded">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="mb-0" style="margin-right: 5px;"><?php echo htmlspecialchars($case['ob_number']); ?></h5>
                                    <span class="badge status-<?php echo strtolower(str_replace('_', '-', $case['status'])); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                    </span>
                                </div>
                                
                                <h4><?php echo htmlspecialchars($case['title']); ?></h4>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Category:</strong> <?php echo htmlspecialchars($case['category']); ?></p>
                                         <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($case['incident_location_county']); ?>, <?php echo htmlspecialchars($case['incident_location_constituency']); ?></p>
                                        <p class="mb-1"><strong>Station:</strong> <?php echo htmlspecialchars($case['station_name']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?></p>
                                        <?php if (isset($case['limited_access']) && $case['limited_access']): ?>
                                            <p class="text-muted small">Limited access </p>
                                        <?php else: ?>
                                            <p class="mb-1"><strong>Reporter:</strong> <?php echo htmlspecialchars($case['reporter_name'] ?? 'N/A'); ?></p>
                                            <p class="mb-1"><strong>Recorded by:</strong> <?php echo htmlspecialchars($case['recorded_by_name'] ?? 'N/A'); ?></p>
                                            <p class="mb-1"><strong>Assigned to:</strong> <?php echo htmlspecialchars($case['assigned_officer_name'] ?? 'Unassigned'); ?> (<?php echo htmlspecialchars($case['badge_number'] ?? 'N/A'); ?>)</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!isset($case['limited_access']) || !$case['limited_access']): ?>
                                    <div class="mt-3">
                                        <a href="<?php echo BASE_URL; ?>/pages/officer/view_case.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-outline-primary">View Full Details</a>
                                        <a href="<?php echo BASE_URL; ?>/pages/officer/update_case.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-outline-secondary">Update Case</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>