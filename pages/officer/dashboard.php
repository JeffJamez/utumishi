<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';
require_once __DIR__ . '/../../includes/classes/Officer.php';
require_once __DIR__ . '/../../includes/classes/CaseManager.php';

requireRole(ROLE_OFFICER);

$currentUser = getCurrentUser();
$officer = new Officer($currentUser['id']);
$db = Database::getInstance();
$badgeData = $db->fetchOne("SELECT badge_number FROM officers WHERE user_id = ?", [$currentUser['id']]);
$badgeNumber = $badgeData ? $badgeData['badge_number'] : 'N/A';

$caseManager = new CaseManager();

$dashboardData = [];
$assignedCases = [];
$urgentCases = [];
$casesRequiringAttention = [];

try {
    $dashboardData = $officer->getOfficerDashboardData();
    $assignedCases = $officer->getAssignedCases(null);
    $urgentCases = $officer->getUrgentCases();
    $casesRequiringAttention = $officer->getCasesRequiringAttention();

} catch (Exception $e) {
    error_log("Officer Dashboard Error: " . $e->getMessage());
    $error = "Unable to load dashboard data";

    echo '<div style="font-family:monospace; background:#fef0f0; color:#721c24; border:2px solid #f5c6cb; padding:20px; margin:20px; border-radius:8px; white-space:pre-wrap;">';
    echo '<h3 style="margin-top:0; color:#842029;">🚨 Dashboard Error (Debug Mode)</h3>';
    echo '<strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '<br><br>';
    echo '<strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '<br><br>';
    echo '<strong>Line:</strong> ' . $e->getLine() . '<br><br>';
    echo '<strong>Trace:</strong><br><pre style="background:#fff; padding:10px; border:1px solid #f5c6cb; overflow:auto;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</div>';

    exit;

}

$pageTitle = "Officer Dashboard";

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

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
                <h2>Officer Dashboard</h2>
                <p class="text-muted">
                    Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?> - Badge No <?php echo htmlspecialchars($badgeNumber); ?>. 
                    Station: <?php echo htmlspecialchars($officer->station_name); ?>
                </p>
            </div>

            <?php 
            $workloadStatus = $dashboardData['workload_status'] ?? ['status' => 'Unknown', 'level' => 0];
            if ($workloadStatus['level'] >= 3): 
            ?>
                <div class="alert alert-warning">
                    <strong> High Workload Alert:</strong> 
                    You currently have <?php echo $dashboardData['workload']['current_case_load'] ?? 0; ?> active cases assigned. 
                    Consider prioritizing urgent cases and requesting assistance if needed.
                </div>
            <?php endif; ?>

             <div class="kpi-grid">
                 <div class="kpi-card">
                     <div class="kpi-value"><?php echo $dashboardData['total_assigned'] ?? 0; ?></div>
                     <div class="kpi-label">Total Cases Assigned</div>
                     <div class="kpi-change">
                         Cases currently assigned to you
                     </div>
                 </div>

                 <div class="kpi-card">
                     <div class="kpi-value"><?php echo $dashboardData['total_recorded'] ?? 0; ?></div>
                     <div class="kpi-label">Total Cases Recorded</div>
                     <div class="kpi-change">
                         Cases you have recorded in the system
                     </div>
                 </div>

                 <div class="kpi-card">
                     <div class="kpi-value"><?php echo $dashboardData['total_closed'] ?? 0; ?></div>
                     <div class="kpi-label">Total Cases Closed</div>
                     <div class="kpi-change">
                         Cases you have successfully closed
                     </div>
                 </div>

                 <div class="kpi-card">
                     <div class="kpi-value"><?php echo $dashboardData['total_open'] ?? 0; ?></div>
                     <div class="kpi-label">Total Cases Open</div>
                     <div class="kpi-change">
                         Cases currently open/under investigation
                     </div>
                 </div>
             </div>

           <!--  <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: 2rem;">

                <div class="card">
                    <div class="card-header">
                        <h3>Cases Requiring Attention</h3>
                        <?php if (count($casesRequiringAttention) > 0): ?>
                            <span class="badge status-reported"><?php echo count($casesRequiringAttention); ?> cases</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($casesRequiringAttention)): ?>
                            <?php foreach (array_slice($casesRequiringAttention, 0, 5) as $case): ?>
                                <div class="alert alert-<?php echo $case['attention_level'] === 'overdue' ? 'danger' : ($case['attention_level'] === 'high_priority' ? 'warning' : 'info'); ?> mb-2">
                                    <div class="d-flex justify-between items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($case['ob_number']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($case['category']); ?> • <?php echo htmlspecialchars($case['reporter_name']); ?></small>
                                        </div>
                                        <div class="text-right">
                                            <?php if ($case['attention_level'] === 'overdue'): ?>

                                            <?php elseif ($case['attention_level'] === 'due_soon'): ?>
                                                <span class="text-warning">Due soon</span>
                                            <?php elseif ($case['attention_level'] === 'high_priority'): ?>
                                                <span class="text-warning">High Priority</span>
                                            <?php endif; ?>
                                            <br>
                                            <a href="<?php echo BASE_URL; ?>/pages/officer/update_case.php?id=<?php echo $case['id']; ?>" 
                                               class="btn btn-sm btn-primary">Update</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($casesRequiringAttention) > 5): ?>
                                <div class="text-center mt-3">
                                    <a href="<?php echo BASE_URL; ?>/pages/officer/my_cases.php?filter=attention" class="btn btn-outline btn-primary">
                                        View All (<?php echo count($casesRequiringAttention); ?> total)
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <div style="font-size: 3rem;"></div>
                                <p class="text-muted">All cases are up to date!</p>
                                <p><small>Great job staying on top of your caseload.</small></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Recent Activity</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($dashboardData['recent_activity'])): ?>
                            <div class="timeline">
                                <?php foreach ($dashboardData['recent_activity'] as $activity): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-date">
                                            <?php echo date('M d, H:i', strtotime($activity['activity_date'])); ?>
                                        </div>
                                        <div class="timeline-content">
                                            <?php if ($activity['activity_type'] === 'case_assigned'): ?>
                                                <strong>Case Assigned:</strong> 
                                                <?php echo htmlspecialchars($activity['ob_number']); ?><br>
                                                <small><?php echo htmlspecialchars($activity['title']); ?> • <?php echo htmlspecialchars($activity['category']); ?></small>
                                            <?php elseif ($activity['activity_type'] === 'case_updated'): ?>
                                                <strong>Case Updated:</strong> 
                                                <?php echo htmlspecialchars($activity['ob_number']); ?><br>
                                                <small><?php echo htmlspecialchars($activity['title']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <p class="text-muted">No recent activity to display.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>My Active Cases</h3>
                    <div>
                        <a href="<?php echo BASE_URL; ?>/pages/officer/record_case.php" class="btn btn-sm btn-success">
                            Record New Case
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($assignedCases)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>OB Number</th>
                                        <th>Case Details</th>
                                        <th>Reporter</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($assignedCases, 0, 8) as $case): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($case['ob_number']); ?></strong>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($case['title']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($case['category']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($case['reporter_name']); ?></td>
                                            <td>
                                                <span class="badge <?php echo STATUS_COLORS[$case['status']] ?? 'status-reported'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div><?php echo round($case['hours_since_reported']); ?>h ago</div>
                                                <?php if ($case['hours_since_reported'] > $case['estimated_resolution_hours']): ?>
                                                    <small class="text-danger">Overdue</small>
                                                <?php else: ?>
                                                    <small class="text-muted">
                                                        <?php echo $case['estimated_resolution_hours'] - $case['hours_since_reported']; ?>h left
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="<?php echo BASE_URL; ?>/pages/officer/update_case.php?id=<?php echo $case['id']; ?>" 
                                                       class="btn btn-sm btn-primary">Update</a>
                                                    <a href="<?php echo BASE_URL; ?>/pages/officer/evidence.php?case_id=<?php echo $case['id']; ?>" 
                                                       class="btn btn-sm btn-outline btn-secondary">Evidence</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (count($assignedCases) > 8): ?>
                            <div class="text-center mt-3">
                                <a href="<?php echo BASE_URL; ?>/pages/officer/my_cases.php" class="btn btn-outline btn-primary">
                                    View All My Cases (<?php echo count($assignedCases); ?> total)
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <div style="font-size: 3rem;"></div>
                            <p class="text-muted">No active cases assigned.</p>
                            <p><small>New cases will appear here when assigned to you.</small></p>
                            <a href="<?php echo BASE_URL; ?>/pages/officer/record_case.php" class="btn btn-primary">
                                Record New Case
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <a href="<?php echo BASE_URL; ?>/pages/officer/record_case.php" class="btn btn-outline btn-success btn-block">
                             Record New Case
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/officer/my_cases.php?status=in_progress" class="btn btn-outline btn-warning btn-block">
                            Cases In Progress
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/officer/evidence.php" class="btn btn-outline btn-info btn-block">
                             Manage Evidence
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/officer/profile.php" class="btn btn-outline btn-secondary btn-block">
                             Update Profile
                        </a>
                    </div>
                </div>
            </div> -->
        </main>
    </div>




    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>

         document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.querySelector('.dropdown-toggle');
            const dropdownMenu = document.querySelector('.dropdown-menu');

            if (dropdownToggle && dropdownMenu) {
                dropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
                });

                document.addEventListener('click', function(e) {
                    if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
                        dropdownMenu.style.display = 'none';
                    }
                });
            }
        });

        function checkForUpdates() {
            fetch('<?php echo BASE_URL; ?>/api/officer_updates.php')
                .then(response => response.json())
                .then(data => {
                    if (data.new_assignments && data.new_assignments > 0) {
                        showNotification('New Case Assignment', `You have ${data.new_assignments} new case(s) assigned`);
                    }

                    if (data.urgent_cases && data.urgent_cases > 0) {
                        updateUrgentCaseBadge(data.urgent_cases);
                    }
                })
                .catch(error => console.log('Update check failed:', error));
        }

        function showNotification(title, message) {
            if (Notification.permission === 'granted') {
                new Notification(title, { 
                    body: message,
                    icon: '<?php echo BASE_URL; ?>/assets/images/police-badge.png'
                });
            }
        }

        function updateUrgentCaseBadge(count) {
            const badge = document.querySelector('.kpi-card .kpi-value');
            if (badge && parseInt(badge.textContent) !== count) {
                badge.textContent = count;
                badge.parentElement.classList.add('updated');
                setTimeout(() => badge.parentElement.classList.remove('updated'), 3000);
            }
        }

        setInterval(checkForUpdates, 120000);

        if (Notification.permission === 'default') {
            Notification.requestPermission();
        }

        document.addEventListener('DOMContentLoaded', function() {

            setInterval(function() {
                location.reload();
            }, 300000);

            document.querySelectorAll('tr').forEach(row => {
                const timeCell = row.querySelector('td:nth-child(5)');
                if (timeCell && timeCell.textContent.includes('Overdue')) {
                    row.style.borderLeft = '4px solid var(--danger-red)';
                    row.style.backgroundColor = 'rgba(220, 53, 69, 0.05)';
                }
            });

            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('mouseenter', function() {
                    if (!this.disabled) {
                        this.style.transform = 'translateY(-1px)';
                    }
                });

                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            updateCaseCountdowns();
            setInterval(updateCaseCountdowns, 60000);
        });

        function updateCaseCountdowns() {
            document.querySelectorAll('[data-deadline]').forEach(element => {
                const deadline = new Date(element.dataset.deadline);
                const now = new Date();
                const diff = deadline - now;

                if (diff > 0) {
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    element.textContent = `${hours}h ${minutes}m left`;
                } else {
                    element.textContent = 'Overdue';
                    element.style.color = 'var(--danger-red)';
                }
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'n':
                        e.preventDefault();
                        window.location.href = '<?php echo BASE_URL; ?>/pages/officer/record_case.php';
                        break;
                    case 'm':
                        e.preventDefault();
                        window.location.href = '<?php echo BASE_URL; ?>/pages/officer/my_cases.php';
                        break;
                    case 'u':
                        e.preventDefault();
                        window.location.href = '<?php echo BASE_URL; ?>/pages/officer/update_case.php';
                        break;
                }
            }
        });
    </script>

    <style>
        .updated {
            animation: pulse 1s ease-in-out;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .timeline {
            position: relative;
            padding-left: 2rem;
            max-height: 400px;
            overflow-y: auto;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--light-gray);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: var(--light-gray);
            border-radius: var(--border-radius);
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0.75rem;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary-green);
            border: 2px solid var(--primary-white);
        }

        .timeline-date {
            font-size: 0.75rem;
            color: var(--medium-gray);
            margin-bottom: 0.25rem;
        }

        .timeline-content {
            font-size: 0.875rem;
            line-height: 1.4;
        }

        .alert-overdue {
            /* border-left: 4px solid var(--danger-red); */
            background-color: rgba(220, 53, 69, 0.1);
        }

        .alert-due-soon {
            /* border-left: 4px solid var(--warning-orange); */
            background-color: rgba(255, 193, 7, 0.1);
        }

        .workload-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .workload-low { background-color: var(--success-green); }
        .workload-normal { background-color: var(--warning-orange); }
        .workload-high { background-color: var(--danger-red); }

        @media (max-width: 768px) {
            .d-grid[style*="1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .table-responsive {
                font-size: 0.8rem;
            }

            .btn-group {
                flex-direction: column;
            }

            .timeline {
                max-height: 300px;
            }
        }

        @media (max-width: 480px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }

            .d-flex.gap-1 {
                flex-direction: column;
                gap: 0.25rem;
            }

            .btn-sm {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
        }

        @media print {
            .app-header, .app-sidebar, .btn, .no-print {
                display: none !important;
            }

            .app-layout {
                grid-template-areas: "main";
                grid-template-columns: 1fr;
            }

            .card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .alert {
                border: 1px solid #ddd;
                background: transparent !important;
            }
        }

        .btn:focus {
            box-shadow: 0 0 0 3px rgba(0, 107, 63, 0.25);
        }

        .table tr:hover {
            background-color: rgba(0, 107, 63, 0.05);
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top: 2px solid var(--primary-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
        }

        .badge {
            transition: var(--transition);
        }

        .badge:hover {
            transform: scale(1.05);
        }

        .kpi-card {
            transition: var(--transition);
            cursor: pointer;
        }

        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .kpi-card:hover .kpi-value {
            color: var(--primary-black);
        }
    </style>
</body>
</html>
