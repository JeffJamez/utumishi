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

requireRole(ROLE_ADMIN);

$currentUser = getCurrentUser();
$caseManager = new CaseManager();
$crimeAnalyzer = new CrimeAnalyzer();

try {
    $db = Database::getInstance();

    $nationalStats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_cases,
            COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
            COUNT(CASE WHEN status = 'reported' THEN 1 END) as pending_cases,
            COUNT(CASE WHEN status IN ('assigned', 'in_progress') THEN 1 END) as active_cases,
            ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
            AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
        FROM cases 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    $stationPerformance = $db->fetchAll("
        SELECT 
            s.name as station_name,
            s.county,
            COUNT(c.id) as total_cases,
            COUNT(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
            ROUND(COUNT(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(c.id), 0), 1) as resolution_rate,
            COUNT(DISTINCT o.id) as officer_count,
            ROUND(COUNT(c.id) / NULLIF(COUNT(DISTINCT o.id), 0), 1) as cases_per_officer
        FROM stations s
        LEFT JOIN cases c ON s.id = c.station_id AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        LEFT JOIN users u ON s.id = u.station_id AND u.role = 'officer' AND u.is_active = 1
        LEFT JOIN officers o ON u.id = o.user_id
        GROUP BY s.id, s.name, s.county
        ORDER BY resolution_rate DESC, total_cases DESC
    ");

    $crimeTrends = $db->fetchAll("
        SELECT 
            category,
            COUNT(*) as total_cases,
            COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
            ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
            AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
        FROM cases 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY category
        ORDER BY total_cases DESC
    ");

    $countyStats = $db->fetchAll("
        SELECT 
            incident_location_county as county,
            COUNT(*) as total_cases,
            COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
            ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
            COUNT(DISTINCT station_id) as station_count
        FROM cases 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY incident_location_county
        ORDER BY total_cases DESC
        LIMIT 10
    ");

    $systemTotals = $db->fetchOne("
        SELECT 
            (SELECT COUNT(*) FROM stations) as total_stations,
            (SELECT COUNT(*) FROM users WHERE role = 'officer' AND is_active = 1) as total_officers,
            (SELECT COUNT(*) FROM users WHERE role = 'citizen') as total_citizens,
            (SELECT COUNT(*) FROM cases) as total_cases_ever
    ");

    $alerts = $crimeAnalyzer->generateAlerts();
    $criticalAlerts = array_filter($alerts, function($alert) {
        return $alert['severity'] === 'critical' || $alert['severity'] === 'high';
    });

    $monthlyTrends = $db->fetchAll("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as cases,
            COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved
        FROM cases 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");

} catch (Exception $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    $error = "Unable to load dashboard data";
}

$pageTitle = "Admin Dashboard";

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
                <h1>National Command Center</h1>
                <p class="text-muted">Kenya Police Service - Strategic Overview & Management</p>
            </div>

            <?php if (!empty($criticalAlerts)): ?>
                <div class="alert alert-danger mb-4">
                    <strong>Critical System Alerts:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach (array_slice($criticalAlerts, 0, 3) as $alert): ?>
                            <li><?php echo htmlspecialchars($alert['message']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (count($criticalAlerts) > 3): ?>
                        <small>And <?php echo count($criticalAlerts) - 3; ?> more alerts requiring attention.</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo number_format($nationalStats['total_cases'] ?? 0); ?></div>
                    <div class="kpi-label">Cases This Month</div>
                    <div class="kpi-change">
                        Resolution: <span class="positive"><?php echo $nationalStats['resolution_rate'] ?? 0; ?>%</span>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value"><?php echo number_format($systemTotals['total_stations'] ?? 0); ?></div>
                    <div class="kpi-label">Active Stations</div>
                    <div class="kpi-change">
                        Officers: <span class="positive"><?php echo number_format($systemTotals['total_officers'] ?? 0); ?></span>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value"><?php echo number_format($nationalStats['active_cases'] ?? 0); ?></div>
                    <div class="kpi-label">Active Cases</div>
                    <div class="kpi-change">
                        Pending: <span class="<?php echo ($nationalStats['pending_cases'] ?? 0) > 100 ? 'negative' : 'positive'; ?>">
                            <?php echo number_format($nationalStats['pending_cases'] ?? 0); ?>
                        </span>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value"><?php echo round($nationalStats['avg_resolution_time'] ?? 0, 1); ?>h</div>
                    <div class="kpi-label">Avg Resolution Time</div>
                    <div class="kpi-change">
                        Citizens: <span class="positive"><?php echo number_format($systemTotals['total_citizens'] ?? 0); ?></span>
                    </div>
                </div>
            </div>

            <div class="d-grid" style="grid-template-columns: 1fr; gap: 2rem;">
                 <div class="card mb-3">
                        <div class="card-header">
                            <h3> Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column gap-2">
                                <a href="<?php echo BASE_URL; ?>/pages/admin/manage_officers.php" class="btn btn-outline btn-primary btn-block">
                                    Manage Officers
                                </a>
                                <a href="<?php echo BASE_URL; ?>/pages/admin/manage_stations.php" class="btn btn-outline btn-primary btn-block">
                                    Station Management
                                </a>

                                    Budget Allocation
                                </a>
                                <a href="<?php echo BASE_URL; ?>/pages/admin/national_reports.php" class="btn btn-outline btn-primary btn-block">
                                    Generate Reports
                                </a>
                                <a href="<?php echo BASE_URL; ?>/pages/admin/system_settings.php" class="btn btn-outline btn-primary btn-block">
                                    System Settings
                                </a>
                            </div>
                        </div>
                    </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Station Performance Ranking</h3>
                        <span class="text-muted">Last 30 days</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stationPerformance)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Station</th>
                                            <th>County</th>
                                            <th>Cases</th>
                                            <th>Resolution Rate</th>
                                            <th>Officers</th>
                                            <th>Workload</th>
                                            <th>Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($stationPerformance, 0, 10) as $index => $station): ?>
                                            <?php
                                            $rank = $index + 1;
                                            $resolutionRate = $station['resolution_rate'] ?? 0;
                                            $casesPerOfficer = $station['cases_per_officer'] ?? 0;

                                            if ($resolutionRate >= 85) {
                                                $performance = ['label' => 'Excellent', 'class' => 'success'];
                                            } elseif ($resolutionRate >= 70) {
                                                $performance = ['label' => 'Good', 'class' => 'warning'];
                                            } elseif ($resolutionRate >= 50) {
                                                $performance = ['label' => 'Fair', 'class' => 'info'];
                                            } else {
                                                $performance = ['label' => 'Poor', 'class' => 'danger'];
                                            }

                                            if ($casesPerOfficer > 15) {
                                                $workload = ['label' => 'Overloaded', 'class' => 'danger'];
                                            } elseif ($casesPerOfficer > 10) {
                                                $workload = ['label' => 'High', 'class' => 'warning'];
                                            } elseif ($casesPerOfficer > 5) {
                                                $workload = ['label' => 'Normal', 'class' => 'success'];
                                            } else {
                                                $workload = ['label' => 'Light', 'class' => 'success'];
                                            }
                                            ?>
                                            <tr class="<?php echo $rank <= 3 ? 'table-success' : ''; ?>">
                                                <td>
                                                     <strong><?php echo $rank; ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($station['station_name']); ?></td>
                                                <td><?php echo htmlspecialchars($station['county']); ?></td>
                                                <td><?php echo $station['total_cases']; ?></td>
                                                <td>
                                                    <strong><?php echo $resolutionRate; ?>%</strong>
                                                </td>
                                                <td><?php echo $station['officer_count'] ?? 0; ?></td>
                                                <td>
                                                    <span class="badge status-<?php echo $workload['class']; ?>">
                                                        <?php echo $casesPerOfficer; ?> cases/officer
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

                            <div class="text-center mt-3">
                                <a href="<?php echo BASE_URL; ?>/pages/admin/manage_stations.php" class="btn btn-outline btn-primary">
                                    View All Stations
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <p class="text-muted">No station performance data available.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div>                   

                    <div class="card">
                        <div class="card-header">
                            <h3>System Alerts</h3>
                            <?php if (count($alerts) > 0): ?>
                                <span class="badge status-progress"><?php echo count($alerts); ?> alerts</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($alerts)): ?>
                                <?php foreach (array_slice($alerts, 0, 5) as $alert): ?>
                                    <div class="alert alert-<?php echo $alert['severity'] === 'critical' ? 'danger' : ($alert['severity'] === 'high' ? 'warning' : 'info'); ?> mb-2 p-2">
                                        <small>
                                            <strong><?php echo htmlspecialchars($alert['title']); ?></strong><br>
                                            <?php echo htmlspecialchars($alert['message']); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (count($alerts) > 5): ?>
                                    <div class="text-center">
                                        <small class="text-muted">
                                            <?php echo count($alerts) - 5; ?> more alerts...
                                        </small>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-center p-2">
                                    <small class="text-muted">No system alerts</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h3>Crime Category Analysis (Last 30 Days)</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($crimeTrends)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Crime Category</th>
                                        <th>Total Cases</th>
                                        <th>Resolved</th>
                                        <th>Resolution Rate</th>
                                        <th>Avg Resolution Time</th>
                                        <th>Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($crimeTrends as $trend): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($trend['category']); ?></strong></td>
                                            <td><?php echo number_format($trend['total_cases']); ?></td>
                                            <td><?php echo number_format($trend['resolved_cases']); ?></td>
                                            <td>
                                                <span class="<?php echo $trend['resolution_rate'] >= 70 ? 'text-success' : ($trend['resolution_rate'] >= 50 ? 'text-warning' : 'text-danger'); ?>">
                                                    <?php echo $trend['resolution_rate']; ?>%
                                                </span>
                                            </td>
                                            <td><?php echo round($trend['avg_resolution_time'] ?? 0, 1); ?>h</td>
                                            <td>
                                                <?php
                                                $cases = $trend['total_cases'];
                                                if ($cases >= 50) {
                                                    echo '<span class="text-danger"> High Volume</span>';
                                                } elseif ($cases >= 20) {
                                                    echo '<span class="text-warning"> Moderate</span>';
                                                } else {
                                                    echo '<span class="text-success"> Low Volume</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>County Performance Overview</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($countyStats)): ?>
                        <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                            <?php foreach ($countyStats as $county): ?>
                                <div class="alert alert-<?php echo $county['resolution_rate'] >= 70 ? 'success' : ($county['resolution_rate'] >= 50 ? 'warning' : 'danger'); ?>">
                                    <div class="d-flex justify-between items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($county['county']); ?> County</strong><br>
                                            <small>
                                                <?php echo $county['total_cases']; ?> cases • 
                                                <?php echo $county['station_count']; ?> stations
                                            </small>
                                        </div>
                                        <div class="text-right">
                                            <div class="h4 mb-0"><?php echo $county['resolution_rate']; ?>%</div>
                                            <small>Resolution Rate</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
        }, 600000);

        function checkSystemStatus() {
            fetch('<?php echo BASE_URL; ?>/api/system_status.php')
                .then(response => response.json())
                .then(data => {
                    updateSystemStatus(data);
                })
                .catch(error => console.log('System status check failed:', error));
        }

        function updateSystemStatus(data) {
            if (data.critical_alerts > 0) {
                showCriticalAlert(`${data.critical_alerts} critical system alerts require immediate attention`);
            }

            const kpiCards = document.querySelectorAll('.kpi-card .kpi-value');
            if (kpiCards.length >= 4) {
                if (Math.abs(parseInt(kpiCards[0].textContent.replace(/,/g, '')) - data.total_cases) > 10) {
                    location.reload();
                }
            }
        }

        function showCriticalAlert(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger';
            alertDiv.innerHTML = `<strong> System Alert:</strong> ${message}`;

            const main = document.querySelector('.app-main');
            main.insertBefore(alertDiv, main.firstChild);

            setTimeout(() => alertDiv.remove(), 10000);
        }

        setInterval(checkSystemStatus, 300000);

        document.addEventListener('DOMContentLoaded', function() {

            document.querySelectorAll('.kpi-card').forEach((card, index) => {
                card.addEventListener('click', function() {
                    const routes = [
                        '<?php echo BASE_URL; ?>/pages/admin/national_reports.php',
                        '<?php echo BASE_URL; ?>/pages/admin/manage_stations.php',
                        '<?php echo BASE_URL; ?>/pages/admin/national_reports.php',
                        '<?php echo BASE_URL; ?>/pages/admin/national_reports.php'
                    ];

                    if (routes[index]) {
                        window.location.href = routes[index];
                    }
                });

                card.style.cursor = 'pointer';
            });

            document.querySelectorAll('.table-success').forEach(row => {
                row.style.boxShadow = '0 0 0 2px var(--success-green)';
            });
        });

        function exportDashboardData() {
            const data = {
                timestamp: new Date().toISOString(),
                national_stats: <?php echo json_encode($nationalStats); ?>,
                station_performance: <?php echo json_encode($stationPerformance); ?>,
                crime_trends: <?php echo json_encode($crimeTrends); ?>
            };

            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `police_dashboard_${new Date().toISOString().split('T')[0]}.json`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const header = document.querySelector('h1');
            const exportBtn = document.createElement('button');
            exportBtn.innerHTML = 'Export Data';
            exportBtn.className = 'btn btn-sm btn-outline btn-secondary ml-2';
            exportBtn.onclick = exportDashboardData;
            header.parentNode.appendChild(exportBtn);
        });

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'r':
                        e.preventDefault();
                        location.reload();
                        break;
                    case 'e':
                        e.preventDefault();
                        exportDashboardData();
                        break;
                    case '1':
                        e.preventDefault();
                        window.location.href = '<?php echo BASE_URL; ?>/pages/admin/manage_officers.php';
                        break;
                    case '2':
                        e.preventDefault();
                        window.location.href = '<?php echo BASE_URL; ?>/pages/admin/manage_stations.php';
                        break;
                }
            }
        });
    </script>

    <style>
        .table-success {
            background-color: rgba(40, 167, 69, 0.1) !important;
        }

        .kpi-card:hover {
            cursor: pointer;
            box-shadow: var(--shadow-lg);
        }

        .admin-metric {
            text-align: center;
            padding: 1rem;
            border-radius: var(--border-radius);
            background: var(--light-gray);
        }

        .admin-metric h3 {
            color: var(--primary-green);
            margin-bottom: 0.5rem;
        }

        .rank-medal {
            font-size: 1.2em;
            margin-left: 0.5rem;
        }

        .kpi-grid {
            grid-template-columns: repeat(4, 1fr);
        }

        @media (max-width: 768px) {
            .d-grid[style*="2fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .table-responsive {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            .btn, .no-print {
                display: none !important;
            }

            .kpi-card {
                break-inside: avoid;
            }

            .table {
                font-size: 0.8rem;
            }
        }
    </style>
</body>
</html>
