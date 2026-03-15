<?php
define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';
require_once __DIR__ . '/../../includes/classes/WorkloadManager.php';
require_once __DIR__ . '/../../includes/utils/scope_validation.php';

requireRole(ROLE_OCS);

$currentUser = getCurrentUser();
$stationId = $currentUser['station_id'];
$workloadManager = new WorkloadManager();

if ($_POST) {
    try {
        if ($_POST['action'] === 'assign_case') {
            // Validate scope before assignment
            if (!validateAssignmentScope($_POST['case_id'], $_POST['officer_id'], $currentUser)) {
                throw new Exception("Access denied: Case or officer outside your jurisdiction");
            }
            
            $result = $workloadManager->assignCase($_POST['case_id'], $_POST['officer_id'], $currentUser['id']);
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
        }
        
        if ($_POST['action'] === 'reassign_case') {
            // Validate scope before reassignment
            if (!validateAssignmentScope($_POST['case_id'], $_POST['from_officer_id'], $currentUser) ||
                !validateAssignmentScope($_POST['case_id'], $_POST['to_officer_id'], $currentUser)) {
                throw new Exception("Access denied: Case or officers outside your jurisdiction");
            }
            
            $reason = $_POST['reason'] ?? '';
            $result = $workloadManager->reassignCase($_POST['case_id'], $_POST['from_officer_id'], $_POST['to_officer_id'], $currentUser['id'], $reason);
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
        }
        
        if ($_POST['action'] === 'auto_assign') {
            $maxCases = $_POST['max_cases'] ?? 12;
            $result = $workloadManager->autoAssignCases($stationId, $currentUser['id'], $maxCases);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                if (!empty($result['errors'])) {
                    $_SESSION['warning'] = 'Some assignments failed: ' . implode(', ', $result['errors']);
                }
            } else {
                $_SESSION['error'] = $result['message'];
            }
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Operation failed: ' . $e->getMessage();
    }
}

$workloadData = [];
$recommendations = [];
$error = '';

try {
    $workloadData = $workloadManager->getStationWorkloadData($stationId);
    $recommendations = $workloadManager->getWorkloadRecommendations($stationId);
    
} catch (Exception $e) {
    error_log("Officer Workload Error: " . $e->getMessage());
    $error = "Unable to load workload data";
}

$officers = $workloadData['officers'] ?? [];
$unassignedCases = $workloadData['unassigned_cases'] ?? [];
$workloadStats = $workloadData['workload_stats'] ?? [];

$pageTitle = "Officer Workload Management";
require_once __DIR__ . '/../../includes/layout/layout.php';

?>

        <main class="app-main">

            <div class="mb-4">
                <h2>Officer Workload Management</h2>
                <p class="text-muted">Manage case assignments and monitor officer performance</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

          
            <?php if (count($unassignedCases) > 0): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-3 align-items-end">
                        <div>
                            <p class="mb-2">Auto-assign pending cases to available officers based on expertise and workload:</p>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="action" value="auto_assign">
                                <div>
                                    <label for="max_cases" class="form-label">Max cases per officer:</label>
                                    <input type="number" name="max_cases" id="max_cases" value="12" min="1" max="20" class="form-control">
                                </div>
                                <div style="align-self: end;">
                                    <button type="submit" class="btn btn-primary" onclick="return confirm('Auto-assign <?php echo count($unassignedCases); ?> pending cases?')">
                                        Auto-Assign Cases
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="kpi-grid mb-4">
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo $workloadStats['total_officers'] ?? 0; ?></div>
                    <div class="kpi-label">Total Officers</div>
                    <div class="kpi-change">
                    </div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo round($workloadStats['avg_case_load'] ?? 0, 1); ?></div>
                    <div class="kpi-label">Average Case Load</div>
                    <div class="kpi-change">
                    </div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo count($unassignedCases); ?></div>
                    <div class="kpi-label">Unassigned Cases</div>
                    <div class="kpi-change">
                        <?php 
                        $urgentUnassigned = array_filter($unassignedCases, function($case) {
                            return $case['urgency_level'] === 'critical' || $case['urgency_level'] === 'high';
                        });
                        ?>

                    </div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo $workloadStats['overloaded_officers'] ?? 0; ?></div>
                    <div class="kpi-label">Overloaded Officers</div>
                    <div class="kpi-change">
                    </div>
                </div>
            </div>

            <?php if (!empty($recommendations)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Workload Recommendations</h3>
                    <span class="badge status-<?php echo array_filter($recommendations, function($r) { return $r['priority'] === 'critical'; }) ? 'danger' : 'warning'; ?>">
                        <?php echo count($recommendations); ?> recommendations
                    </span>
                </div>
                <div class="card-body">
                    <?php foreach ($recommendations as $rec): ?>
                        <div class="alert alert-<?php echo $rec['priority'] === 'critical' ? 'danger' : ($rec['priority'] === 'high' ? 'warning' : 'info'); ?> mb-3">
                            <div class="d-flex justify-between items-start">
                                <div>
                                    <strong><?php echo ucwords(str_replace('_', ' ', $rec['type'])); ?> - <?php echo ucfirst($rec['priority']); ?> Priority:</strong><br>
                                    <?php echo htmlspecialchars($rec['message']); ?>
                                    
                                    <?php if (isset($rec['impact'])): ?>
                                        <div class="mt-1">
                                            <small class="text-muted"><strong>Impact:</strong> <?php echo htmlspecialchars($rec['impact']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($rec['type'] === 'redistribute' && isset($rec['to_officers'])): ?>
                                        <div class="mt-2">
                                            <strong>Available officers:</strong>
                                            <?php foreach ($rec['to_officers'] as $officer): ?>
                                                <span class="badge status-success"><?php echo htmlspecialchars($officer['name']); ?> (<?php echo $officer['current_case_load']; ?> cases)</span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h3>Officer Performance & Workload</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($officers)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Officer</th>
                                        <th>Badge</th>
                                        <th>Current Load</th>
                                        <th>Total Resolved</th>
                                        <th>Resolution Rate</th>
                                        <th>Avg Time</th>
                                        <th>Status</th>
                                        <th>Contact</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($officers as $officer): ?>
                                        <?php
                                        $caseLoad = $officer['current_case_load'];
                                        $resolutionRate = $officer['resolution_rate'] ?? 0;
                                        $statusClass = $officer['workload_status'];
                                        
                                        $statusLabels = [
                                            'overloaded' => ['label' => 'Overloaded', 'class' => 'danger'],
                                            'high' => ['label' => 'High Load', 'class' => 'warning'],
                                            'normal' => ['label' => 'Normal', 'class' => 'success'],
                                            'light' => ['label' => 'Light Load', 'class' => 'info'],
                                            'available' => ['label' => 'Available', 'class' => 'success']
                                        ];
                                        $status = $statusLabels[$statusClass] ?? ['label' => 'Unknown', 'class' => 'secondary'];
                                        ?>
                                        <tr class="<?php echo $caseLoad > 15 ? 'table-warning' : ''; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($officer['name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($officer['badge_number']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $caseLoad > 15 ? 'danger' : ($caseLoad > 10 ? 'warning' : 'info'); ?>">
                                                    <?php echo $caseLoad; ?> cases
                                                </span>
                                            </td>
                                            <td><?php echo $officer['total_cases_resolved']; ?></td>
                                            <td><?php echo $resolutionRate; ?>%</td>
                                            <td><?php echo round(($officer['current_avg_time'] ?? 0) / 24); ?> days</td>
                                            <td>
                                                <span class="badge status-<?php echo $status['class']; ?>">
                                                    <?php echo $status['label']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo htmlspecialchars($officer['phone']); ?><br>
                                                    <?php echo htmlspecialchars($officer['email']); ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <p class="text-muted">No officers found at this station.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Unassigned Cases</h3>
                    <span class="badge status-warning"><?php echo count($unassignedCases); ?> pending</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($unassignedCases)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>OB Number</th>
                                        <th>Case Details</th>
                                        <th>Reporter</th>
                                        <th>Time Pending</th>
                                        <th>Urgency</th>
                                        <th>Assign To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unassignedCases as $case): ?>
                                        <?php
                                        $urgencyClass = [
                                            'critical' => 'table-danger',
                                            'high' => 'table-warning',
                                            'medium' => 'table-info',
                                            'normal' => ''
                                        ];
                                        ?>
                                        <tr class="<?php echo $urgencyClass[$case['urgency_level']] ?? ''; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($case['ob_number']); ?></strong>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($case['title']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($case['category']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($case['reporter_name']); ?></td>
                                            <td>
                                                <?php echo $case['hours_pending']; ?>h
                                                <?php if ($case['hours_pending'] > 48): ?>
                                                    <br><small class="text-danger">Critical</small>
                                                <?php elseif ($case['hours_pending'] > 24): ?>
                                                    <br><small class="text-warning">Overdue</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge status-<?php echo $case['urgency_level'] === 'critical' ? 'danger' : ($case['urgency_level'] === 'high' ? 'warning' : 'info'); ?>">
                                                    <?php echo ucfirst($case['urgency_level']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-flex gap-2">
                                                    <input type="hidden" name="action" value="assign_case">
                                                    <input type="hidden" name="case_id" value="<?php echo $case['id']; ?>">
                                                    <select name="officer_id" class="form-control form-control-sm" required>
                                                        <option value="">Select Officer</option>
                                                        <?php foreach ($officers as $officer): ?>
                                                            <option value="<?php echo $officer['officer_id']; ?>" 
                                                                    <?php echo $officer['current_case_load'] > 15 ? 'style="color: red;"' : ''; ?>>
                                                                <?php echo htmlspecialchars($officer['name']); ?> 
                                                                (<?php echo $officer['current_case_load']; ?> cases)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">Assign</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <div style="font-size: 3rem;"></div>
                            <h4>All Cases Assigned</h4>
                            <p class="text-muted">No cases pending assignment at this time.</p>
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
        }, 120000);
        
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
        
        document.querySelectorAll('form').forEach(form => {
            if (form.querySelector('input[name="action"][value="assign_case"]')) {
                form.addEventListener('submit', function(e) {
                    const officerSelect = this.querySelector('select[name="officer_id"]');
                    const selectedOption = officerSelect.options[officerSelect.selectedIndex];
                    
                    if (selectedOption.style.color === 'red') {
                        if (!confirm('This officer already has a high case load (>15 cases). Are you sure you want to assign this case?')) {
                            e.preventDefault();
                        }
                    }
                });
            }
        });
        
        document.querySelectorAll('.badge').forEach(badge => {
            if (badge.textContent.includes('Overloaded')) {
                badge.title = 'Officer handling more than 15 cases - consider redistribution';
            } else if (badge.textContent.includes('Available')) {
                badge.title = 'Officer available for new case assignments';
            } else if (badge.textContent.includes('Critical')) {
                badge.title = 'Case pending for more than 48 hours - immediate attention required';
            }
        });
    </script>
    
    <style>
        .table-danger {
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .table-warning {
            background-color: rgba(255, 193, 7, 0.1);
        }
        
        .table-info {
            background-color: rgba(13, 202, 240, 0.1);
        }
        
        .badge-danger {
            background-color: var(--danger-red);
            color: white;
        }
        
        .badge-warning {
            background-color: var(--warning-orange);
            color: var(--primary-black);
        }
        
        .badge-info {
            background-color: var(--info-blue);
            color: white;
        }
        
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .kpi-grid {
            grid-template-columns: repeat(4, 1fr);
        }

        @media (max-width: 768px) {
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table-responsive {
                font-size: 0.8rem;
            }
            
            .d-flex.gap-2 {
                flex-direction: column;
                gap: 0.25rem;
            }
        }
    </style>
</body>
</html>