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
$caseManager = new CaseManager();

$statusFilter = sanitizeText($_GET['status'] ?? '');
$filterType = sanitizeText($_GET['filter'] ?? '');

try {

    if ($statusFilter && $statusFilter !== 'all') {
        $myCases = $officer->getAssignedCases($statusFilter);
    } else {
        $myCases = $officer->getAssignedCases();
    }

    if ($filterType === 'attention') {
        $myCases = array_filter($myCases, function($case) {
            $hoursOverdue = $case['hours_since_reported'] - $case['estimated_resolution_hours'];
            return $hoursOverdue > 0 || in_array($case['category'], ['Domestic Violence', 'Sexual Offenses']);
        });
    } elseif ($filterType === 'urgent') {
        $myCases = $officer->getUrgentCases();
    }

} catch (Exception $e) {
    error_log("My Cases Error: " . $e->getMessage());
    $error = "Unable to load cases";
    $myCases = [];
}

$pageTitle = "My Cases";

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
                <h1>My Cases</h1>
                <p class="text-muted">Manage and track all cases assigned to you</p>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h3>Filter Cases</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?php echo BASE_URL; ?>/pages/officer/my_cases.php" 
                           class="btn btn-<?php echo empty($statusFilter) && empty($filterType) ? 'primary' : 'outline btn-secondary'; ?>">
                            All Cases
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/officer/my_cases.php?status=assigned" 
                           class="btn btn-<?php echo $statusFilter === 'assigned' ? 'primary' : 'outline btn-secondary'; ?>">
                            Assigned
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/officer/my_cases.php?status=in_progress" 
                           class="btn btn-<?php echo $statusFilter === 'in_progress' ? 'primary' : 'outline btn-secondary'; ?>">
                            In Progress
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/officer/my_cases.php?status=resolved" 
                           class="btn btn-<?php echo $statusFilter === 'resolved' ? 'primary' : 'outline btn-secondary'; ?>">
                            Resolved
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/officer/my_cases.php?filter=urgent" 
                           class="btn btn-<?php echo $filterType === 'urgent' ? 'danger' : 'outline btn-danger'; ?>">
                            🚨 Urgent Cases
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/officer/my_cases.php?filter=attention" 
                           class="btn btn-<?php echo $filterType === 'attention' ? 'warning' : 'outline btn-warning'; ?>">
                            ⚠️ Needs Attention
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>
                        <?php 
                        if ($filterType === 'urgent') {
                            echo '🚨 Urgent Cases';
                        } elseif ($filterType === 'attention') {
                            echo '⚠️ Cases Requiring Attention';
                        } elseif ($statusFilter) {
                            echo ucfirst(str_replace('_', ' ', $statusFilter)) . ' Cases';
                        } else {
                            echo 'All My Cases';
                        }
                        ?>
                    </h3>
                    <span class="badge status-progress"><?php echo count($myCases); ?> case(s)</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($myCases)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>OB Number</th>
                                        <th>Case Details</th>
                                        <th>Reporter</th>
                                        <th>Status</th>
                                        <th>Time Status</th>
                                        <th>Location</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($myCases as $case): ?>
                                        <?php
                                        $hoursOverdue = $case['hours_since_reported'] - $case['estimated_resolution_hours'];
                                        $isOverdue = $hoursOverdue > 0;
                                        $isUrgent = in_array($case['category'], ['Domestic Violence', 'Sexual Offenses', 'Assault']);
                                        $rowClass = '';

                                        if ($isOverdue) {
                                            $rowClass = 'table-danger';
                                        } elseif ($isUrgent) {
                                            $rowClass = 'table-warning';
                                        }
                                        ?>
                                        <tr class="<?php echo $rowClass; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($case['ob_number']); ?></strong>
                                                <?php if ($isUrgent): ?>
                                                    <br><small class="text-danger">🚨 High Priority</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="mb-1">
                                                    <strong><?php echo htmlspecialchars($case['title']); ?></strong>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($case['category']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($case['reporter_name']); ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo STATUS_COLORS[$case['status']] ?? 'status-reported'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="<?php echo $isOverdue ? 'text-danger' : ''; ?>">
                                                    <?php echo round($case['hours_since_reported']); ?>h ago
                                                </div>
                                                <?php if ($isOverdue): ?>
                                                    <small class="text-danger">
                                                        <strong>⚠️ <?php echo round($hoursOverdue); ?>h overdue</strong>
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-muted">
                                                        <?php echo round($case['estimated_resolution_hours'] - $case['hours_since_reported']); ?>h remaining
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo htmlspecialchars($case['location_constituency'] ?? 'N/A'); ?><br>
                                                    <?php echo htmlspecialchars($case['location_county'] ?? 'N/A'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    <a href="<?php echo BASE_URL; ?>/pages/officer/update_case.php?id=<?php echo $case['id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        Update
                                                    </a>
                                                    <a href="<?php echo BASE_URL; ?>/pages/officer/evidence.php?case_id=<?php echo $case['id']; ?>" 
                                                       class="btn btn-sm btn-outline btn-secondary">
                                                        Evidence
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <div style="font-size: 3rem;">📋</div>
                            <h4>No Cases Found</h4>
                            <?php if ($statusFilter || $filterType): ?>
                                <p class="text-muted">No cases match the selected filter.</p>
                                <a href="<?php echo BASE_URL; ?>/pages/officer/my_cases.php" class="btn btn-secondary">
                                    View All Cases
                                </a>
                            <?php else: ?>
                                <p class="text-muted">You don't have any cases assigned yet.</p>
                                <a href="<?php echo BASE_URL; ?>/pages/officer/dashboard.php" class="btn btn-primary">
                                    Return to Dashboard
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($myCases)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Case Statistics</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $stats = [
                            'total' => count($myCases),
                            'assigned' => 0,
                            'in_progress' => 0,
                            'resolved' => 0,
                            'overdue' => 0,
                            'urgent' => 0
                        ];

                        foreach ($myCases as $case) {
                            $stats[$case['status']] = ($stats[$case['status']] ?? 0) + 1;

                            if ($case['hours_since_reported'] > $case['estimated_resolution_hours']) {
                                $stats['overdue']++;
                            }

                            if (in_array($case['category'], ['Domestic Violence', 'Sexual Offenses', 'Assault'])) {
                                $stats['urgent']++;
                            }
                        }
                        ?>

                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">Total Cases</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['assigned'] ?? 0; ?></div>
                                <div class="stat-label">Assigned</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['in_progress'] ?? 0; ?></div>
                                <div class="stat-label">In Progress</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['resolved'] ?? 0; ?></div>
                                <div class="stat-label">Resolved</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number text-danger"><?php echo $stats['overdue']; ?></div>
                                <div class="stat-label">Overdue</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number text-warning"><?php echo $stats['urgent']; ?></div>
                                <div class="stat-label">Urgent</div>
                            </div>
                        </div>
                    </div>
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
        }, 120000);

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.table-danger').forEach(row => {
                row.style.borderLeft = '4px solid var(--danger-red)';
            });

            document.querySelectorAll('.table-warning').forEach(row => {
                row.style.borderLeft = '4px solid var(--warning-orange)';
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'u':
                        e.preventDefault();
                        window.location.href = '<?php echo BASE_URL; ?>/pages/officer/my_cases.php?filter=urgent';
                        break;
                    case 'a':
                        e.preventDefault();
                        window.location.href = '<?php echo BASE_URL; ?>/pages/officer/my_cases.php?filter=attention';
                        break;
                }
            }
        });
    </script>

    <style>
        .table-danger {
            background-color: rgba(220, 53, 69, 0.1) !important;
        }

        .table-warning {
            background-color: rgba(255, 193, 7, 0.1) !important;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .d-flex.gap-2 {
                flex-direction: column;
            }

            .d-flex.flex-column.gap-1 {
                flex-direction: row;
                gap: 0.25rem;
            }
        }
    </style>
</body>
</html>
