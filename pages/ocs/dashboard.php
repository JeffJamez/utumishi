<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';
require_once __DIR__ . '/../../includes/classes/Officer.php';
require_once __DIR__ . '/../../includes/classes/CaseManager.php';
require_once __DIR__ . '/../../includes/classes/CrimeAnalyzer.php';

requireRole(ROLE_OCS);

$currentUser = getCurrentUser();
$user = new User($currentUser['id']);
$caseManager = new CaseManager();
$crimeAnalyzer = new CrimeAnalyzer();

// FIX 2: Initialize variables as empty arrays to prevent undefined errors
$stationStats = [];
$recentCases = [];
$officerPerformance = [];
$hotspots = [];
$stationHotspots = [];
$recommendations = [];
$alerts = [];
$stationInfo = [];

try {
    $stationId = $currentUser['station_id'];

    $stationStats = $caseManager->getCaseStatistics(['station_id' => $stationId]);
    $recentCases = $caseManager->getCasesForStation($stationId, date('Y-m-d', strtotime('-7 days')));
    $officerPerformance = Officer::getPerformanceRanking($stationId, 30);
    
    $hotspots = $crimeAnalyzer->findHotspots(30, 5);
    $stationHotspots = array_filter($hotspots, function($spot) use ($currentUser) {
        $stationInfo = getDB()->fetchOne("SELECT county, constituency FROM stations WHERE id = :id", 
            ['id' => $currentUser['station_id']]);
        return $spot['incident_location_county'] === $stationInfo['county'] ||
                $spot['incident_location_constituency'] === $stationInfo['constituency'];
    });

    $recommendations = $crimeAnalyzer->recommendDeployment($stationId, 30);
    $alerts = $crimeAnalyzer->generateAlerts($stationId);

    $stationInfo = getDB()->fetchOne(
        "SELECT s.*, u.name as ocs_name FROM stations s 
         LEFT JOIN users u ON s.ocs_id = u.id 
         WHERE s.id = :id", 
        ['id' => $stationId]
    );

} catch (Exception $e) {
    error_log("OCS Dashboard Error: " . $e->getMessage());
    $error = "Unable to load dashboard data";
}

$pageTitle = "OCS Dashboard";

require_once __DIR__ . '/../../includes/layout/layout.php';

?>
        <main class="app-main">
            <?php flashMessage(); ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <h2>Station Command Dashboard</h2>
                <p class="text-muted">
                    <?php echo htmlspecialchars($stationInfo['name'] ?? 'Police Station'); ?> • 
                    <?php echo htmlspecialchars($stationInfo['county'] ?? ''); ?>, 
                    <?php echo htmlspecialchars($stationInfo['constituency'] ?? ''); ?>
                </p>
            </div>

            <?php if (!empty($alerts)): ?>
                <?php foreach (array_slice($alerts, 0, 3) as $alert): ?>
                    <?php if ($alert['severity'] === 'high' || $alert['severity'] === 'critical'): ?>
                        <div class="alert alert-<?php echo $alert['severity'] === 'critical' ? 'danger' : 'warning'; ?>">
                            <strong><?php echo htmlspecialchars($alert['title']); ?>:</strong>
                            <?php echo htmlspecialchars($alert['message']); ?>
                            <?php if ($alert['action_required']): ?>
                                <br><small><strong>Action Required:</strong> Review and take appropriate measures</small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo $stationStats['total_cases'] ?? 0; ?></div>
                    <div class="kpi-label">Total Cases This Month</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value"><?php echo count($officerPerformance); ?></div>
                    <div class="kpi-label">Active Officers</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value"><?php echo $stationStats['in_progress_cases'] + $stationStats['assigned_cases'] ?? 0; ?></div>
                    <div class="kpi-label">Active Cases</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value"><?php echo count($recommendations); ?></div>
                    <div class="kpi-label">Recommendations</div>
                </div>
            </div>

            <div class="d-grid" style="grid-template-columns: 2fr 1fr; gap: 2rem;">

                <div class="card">
                    <div class="card-header" style="display: flex; gap: 10px;">
                        <div>
                            <h3>Resource Deployment Recommendations</h3>
                        </div>
                        
                        <div>

                            <?php if (count($recommendations) > 0): ?>
                                <span class="badge status-progress"><?php echo count($recommendations); ?> recommendations</span>
                                <?php endif; ?>
                            </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recommendations)): ?>
                            <?php foreach (array_slice($recommendations, 0, 5) as $recommendation): ?>
                                <div class="alert alert-<?php echo $recommendation['severity'] === 'high' ? 'warning' : 'info'; ?> mb-3">
                                    <div class="d-flex justify-between items-start">
                                        <div style="flex: 1;">
                                            <strong><?php echo htmlspecialchars($recommendation['area']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($recommendation['crime_type']); ?> • 
                                                <?php echo $recommendation['cases_per_month']; ?> cases/month
                                            </small>
                                            <div class="mt-2">
                                                <strong>Recommended Action:</strong><br>
                                                <?php echo htmlspecialchars($recommendation['action']); ?>
                                            </div>
                                            <?php if (!empty($recommendation['peak_hours'])): ?>
                                                <div class="mt-1">
                                                    <strong>Peak Hours:</strong> <?php echo htmlspecialchars($recommendation['peak_hours']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right">
                                            <span class="badge <?php echo $recommendation['severity'] === 'high' ? 'status-progress' : 'status-assigned'; ?>">
                                                Priority: <?php echo $recommendation['priority']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($recommendations) > 5): ?>
                                <div class="text-center">
                                    <a href="<?php echo BASE_URL; ?>/pages/ocs/reports.php" class="btn btn-outline btn-primary">
                                        View All Recommendations (<?php echo count($recommendations); ?>)
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center p-4">

                                <h4>No Critical Recommendations</h4>
                                <p class="text-muted">Current resource deployment appears optimal based on crime patterns.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div><div class="card">
                        <div class="card-header">
                            <h3>Station Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Station:</strong><br>
                                <?php echo htmlspecialchars($stationInfo['name'] ?? ''); ?>
                            </div>
                            <div class="mb-3">
                                <strong>Location:</strong><br>
                                <?php echo htmlspecialchars($stationInfo['constituency'] ?? ''); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($stationInfo['county'] ?? ''); ?> County</small>
                            </div>
                            <div class="mb-3">
                                <strong>Contact:</strong><br>
                                <?php echo htmlspecialchars($stationInfo['contact_phone'] ?? 'Not set'); ?>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

             <div class="card">
                <div class="card-header">
                    <h3>Recent Cases (Last 7 Days)</h3>
                    <a href="<?php echo BASE_URL; ?>/pages/ocs/station_cases.php" class="btn btn-sm btn-outline btn-primary">
                        View All Cases
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentCases)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>OB Number</th>
                                        <th>Case Details</th>
                                        <th>Reporter</th>
                                        <th>Assigned Officer</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($recentCases, 0, 10) as $case): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($case['ob_number']); ?></strong>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($case['title']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($case['category']); ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($case['reporter_anonymized'])): ?>
                                                    <span style="color: #dc3545; font-weight: bold;">ANONYMIZED</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($case['reporter_name']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($case['assigned_officer']): ?>
                                                    <?php echo htmlspecialchars($case['assigned_officer']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo STATUS_COLORS[$case['status']] ?? 'status-reported'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($case['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <p class="text-muted">No recent cases to display.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Officer Performance Overview</h3>
                    <a href="<?php echo BASE_URL; ?>/pages/ocs/officer_workload.php" class="btn btn-sm btn-outline btn-primary">
                        View Details
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($officerPerformance)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Officer</th>
                                        <th>Badge</th>
                                        <th>Cases Handled</th>
                                        <th>Resolution Rate</th>
                                        <th>Workload Status</th>
                                        <th>Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($officerPerformance, 0, 8) as $officer): ?>
                                        <?php
                                        $resolutionRate = $officer['resolution_rate'] ?? 0;
                                        $casesHandled = $officer['cases_handled'] ?? 0;

                                        if ($resolutionRate >= 85 && $casesHandled >= 5) {
                                            $performance = ['label' => 'Excellent', 'class' => 'success'];
                                        } elseif ($resolutionRate >= 70 && $casesHandled >= 3) {
                                            $performance = ['label' => 'Good', 'class' => 'warning'];
                                        } elseif ($resolutionRate >= 50) {
                                            $performance = ['label' => 'Fair', 'class' => 'info'];
                                        } else {
                                            $performance = ['label' => 'Needs Improvement', 'class' => 'danger'];
                                        }

                                        if ($casesHandled > 15) {
                                            $workload = ['label' => 'Overloaded', 'class' => 'danger'];
                                        } elseif ($casesHandled > 10) {
                                            $workload = ['label' => 'High', 'class' => 'warning'];
                                        } elseif ($casesHandled > 5) {
                                            $workload = ['label' => 'Normal', 'class' => 'success'];
                                        } else {
                                            $workload = ['label' => 'Light', 'class' => 'success'];
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($officer['name']); ?></td>
                                            <td><?php echo htmlspecialchars($officer['badge_number']); ?></td>
                                            <td><?php echo $casesHandled; ?></td>
                                            <td><?php echo $resolutionRate; ?>%</td>
                                            <td>
                                                <span class="badge status-<?php echo $workload['class']; ?>">
                                                    <?php echo $workload['label']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge status-<?php echo $performance['class']; ?>">
                                                    <?php echo $performance['label']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <p class="text-muted">No officer performance data available.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

           
        </main>
    </div>


    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>

        setInterval(function() {
            if (!document.hidden) {
                location.reload();
            }
        }, 300000);

        function checkForUpdates() {
            fetch('<?php echo BASE_URL; ?>/api/ocs_updates.php')
                .then(response => response.json())
                .then(data => {
                    if (data.new_cases > 0) {
                        showNotification('New Cases', `${data.new_cases} new case(s) reported at your station`);
                    }

                    if (data.urgent_cases > 0) {
                        highlightUrgentItems();
                    }
                })
                .catch(error => console.log('Update check failed:', error));
        }

        setInterval(checkForUpdates, 120000);

        function showNotification(title, message) {
            if (Notification.permission === 'granted') {
                new Notification(title, { 
                    body: message,
                    icon: '<?php echo BASE_URL; ?>/assets/images/police-badge.png'
                });
            }
        }

        function highlightUrgentItems() {
            document.querySelectorAll('.alert-warning, .alert-danger').forEach(alert => {
                alert.style.animation = 'pulse 2s ease-in-out 3';
            });
        }

        if (Notification.permission === 'default') {
            Notification.requestPermission();
        }

        document.addEventListener('DOMContentLoaded', function() {

            document.querySelectorAll('.kpi-card').forEach(card => {
                card.addEventListener('click', function() {

                    const label = this.querySelector('.kpi-label').textContent;
                    if (label.includes('Cases')) {
                        window.location.href = '<?php echo BASE_URL; ?>/pages/ocs/station_cases.php';
                    } else if (label.includes('Officers')) {
                        window.location.href = '<?php echo BASE_URL; ?>/pages/ocs/officer_workload.php';
                    } else if (label.includes('Recommendations')) {
                        window.location.href = '<?php echo BASE_URL; ?>/pages/ocs/reports.php';
                    }
                });

                card.style.cursor = 'pointer';
            });

            document.querySelectorAll('.badge').forEach(badge => {
                if (badge.textContent.includes('Overloaded')) {
                    badge.title = 'Officer handling more than 15 cases - consider redistribution';
                } else if (badge.textContent.includes('Excellent')) {
                    badge.title = 'High performance officer - above 85% resolution rate';
                }
            });
        });

        function printDashboard() {
            window.print();
        }

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'r':
                        e.preventDefault();
                        location.reload();
                        break;
                    case 'p':
                        e.preventDefault();
                        printDashboard();
                        break;
                }
            }
        });
    </script>

    <style>
        .kpi-grid {
            grid-template-columns: repeat(4, 1fr);
        }

        .status-success { background-color: var(--success-green); color: white; }
        .status-warning { background-color: var(--warning-orange); color: var(--primary-black); }
        .status-info { background-color: var(--info-blue); color: white; }
        .status-danger { background-color: var(--danger-red); color: white; }

        .kpi-card:hover {
            cursor: pointer;
        }

        .performance-excellent { color: var(--success-green); }
        .performance-good { color: var(--info-blue); }
        .performance-fair { color: var(--warning-orange); }
        .performance-poor { color: var(--danger-red); }

        @media (max-width: 768px) {
            .d-grid[style*="2fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</body>
</html>
