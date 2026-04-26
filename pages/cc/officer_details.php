<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';
require_once __DIR__ . '/../../includes/classes/AdminManager.php';

requireRole(ROLE_ADMIN);

$currentUser = getCurrentUser();
$adminManager = new AdminManager();

// Fetch county_in_charge from database
$db = Database::getInstance();
$userDetails = $db->fetchOne("SELECT county_in_charge FROM users WHERE id = :id", ['id' => $currentUser['id']]);
$county = $userDetails['county_in_charge'] ?? null;

$officerId = $_GET['id'] ?? null;
if (!$officerId) {
    header('Location: ' . BASE_URL . '/pages/cc/manage_officers.php');
    exit;
}

// Check if officer belongs to county
$officer = $adminManager->getOfficerById($officerId);
if (!$officer || ($county && $officer['county'] !== $county)) {
    header('Location: ' . BASE_URL . '/pages/cc/manage_officers.php');
    exit;
}

// Get officer's cases
$officerCases = $adminManager->getOfficerCases($officerId);

$pageTitle = "Officer Details - " . htmlspecialchars($officer['name']);
require_once __DIR__ . '/../../includes/layout/layout.php';

?>

        <main class="app-main">

            <div class="mb-4">
                <h2><?php echo htmlspecialchars($county); ?> County Command Center</h2>
                <p class="text-muted">Officer Details & Performance Review</p>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h3><?php echo htmlspecialchars($officer['name']); ?> - Officer Profile</h3>
                    <div class="d-flex gap-2">
                        <a href="<?php echo BASE_URL; ?>/pages/cc/manage_officers.php" class="btn btn-sm btn-outline btn-secondary">← Back to Officers</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                        <div>
                            <h4>Basic Information</h4>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($officer['name']); ?></p>
                            <p><strong>Badge Number:</strong> <?php echo htmlspecialchars($officer['badge_number']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($officer['email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($officer['phone']); ?></p>
                        </div>
                        <div>
                            <h4>Station & County</h4>
                            <p><strong>Station:</strong> <?php echo htmlspecialchars($officer['station_name']); ?></p>
                            <p><strong>County:</strong> <?php echo htmlspecialchars($officer['county']); ?></p>
                            <p><strong>Status:</strong> <span class="badge status-<?php echo $officer['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $officer['is_active'] ? 'Active' : 'Inactive'; ?></span></p>
                            <p><strong>Last Login:</strong> <?php echo $officer['last_login'] ? date('M d, Y H:i', strtotime($officer['last_login'])) : 'Never'; ?></p>
                        </div>
                        <div>
                            <h4>Performance Metrics</h4>
                            <p><strong>Current Case Load:</strong> <?php echo $officer['current_case_load']; ?> cases</p>
                            <p><strong>Total Resolved:</strong> <?php echo $officer['total_cases_resolved']; ?> cases</p>
                            <p><strong>Resolution Rate:</strong> <?php echo round($officer['resolution_rate'] ?? 0, 1); ?>%</p>
                            <p><strong>Avg Resolution Time:</strong> <?php echo round(($officer['avg_resolution_time_hours'] ?? 0) / 24, 1); ?> days</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h3>Current Cases (<?php echo $officer['current_case_load']; ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($officerCases['current'])): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>OB Number</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Assigned Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($officerCases['current'] as $case): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($case['ob_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($case['title']); ?></td>
                                            <td><?php echo htmlspecialchars($case['category']); ?></td>
                                            <td>
                                                <span class="badge status-<?php echo $case['status'] === 'in_progress' ? 'warning' : 'info'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($case['assigned_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <p class="text-muted">No current cases assigned.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Case Resolution History</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($officerCases['resolved'])): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>OB Number</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Resolution Time</th>
                                        <th>Resolved Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($officerCases['resolved'] as $case): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($case['ob_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($case['title']); ?></td>
                                            <td><?php echo htmlspecialchars($case['category']); ?></td>
                                            <td><?php echo floor($case['actual_resolution_hours'] / 24); ?> days</td>
                                            <td><?php echo date('M d, Y', strtotime($case['closed_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <p class="text-muted">No resolved cases yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
</body>
</html>