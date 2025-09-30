<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';
require_once __DIR__ . '/../../includes/classes/CaseManager.php';

requireRole(ROLE_CITIZEN);

$currentUser = getCurrentUser();
$user = new User($currentUser['id']);

$caseManager = new CaseManager();

try {
    $dashboardData = $user->getDashboardData();
    $recentCases = $caseManager->getCasesForCitizen($currentUser['id'], 5);

    $db = Database::getInstance();

    $countyStats = $db->fetchAll("
        SELECT 
            location_county as county, 
            COUNT(*) as case_count,
            COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_count,
            ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate
        FROM cases 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY location_county  
        ORDER BY case_count DESC
        LIMIT 10
    ");

} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error = "Unable to load dashboard data";
}

$pageTitle = "Citizen Dashboard";

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
                <h2>Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?></h2>
                <p class="text-muted">Here's an overview of your cases and public safety information.</p>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo $dashboardData['statistics']['total_cases_reported'] ?? 0; ?></div>
                    <div class="kpi-label">Total Cases Reported</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value"><?php echo $dashboardData['statistics']['active_cases'] ?? 0; ?></div>
                    <div class="kpi-label">Active Cases</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value"><?php echo $dashboardData['statistics']['resolved_cases'] ?? 0; ?></div>
                    <div class="kpi-label">Resolved Cases</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value"><?php echo round($publicStats['national_resolution_rate'] ?? 0, 1); ?>%</div>
                    <div class="kpi-label">National Resolution Rate</div>
                </div>
            </div>
             <div>
                <!-- <div class="card mb-3">
                    <div class="card-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-2">
                            <a href="<?php echo BASE_URL; ?>/pages/citizen/track_case.php" class="btn btn-outline btn-primary btn-block">
                                Track Case
                            </a>
                            <a href="<?php echo BASE_URL; ?>/pages/citizen/public_stats.php" class="btn btn-outline btn-primary btn-block">
                                View Crime Stats
                            </a>
                        </div>
                    </div>
                </div> -->

                    <div class="card">
                        <div class="card-header">
                            <h3>Safety Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Emergency Numbers:</strong><br>
                                Police: 999 or 911<br>
                                Fire: 999<br>
                                Medical: 999
                            </div>

                            <p><strong>Reporting a Crime:</strong></p>
                            <ol style="font-size: 0.9rem;">
                                <li>Visit the nearest police station</li>
                                <li>Provide your National ID</li>
                                <li>Give detailed information</li>
                                <li>Get your OB Number</li>
                                <li>Track progress here</li>
                            </ol>
                        </div>
                    </div>
                </div>

            <div class="d-grid" style="">

                <div class="card">
                    <div class="card-header">
                        <h3>My Recent Cases</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentCases)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>OB Number</th>
                                            <th>Case Type</th>
                                            <th>Status</th>
                                            <th>Assigned Officer</th>
                                            <th>Date Reported</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentCases as $case): ?>
                                            <tr>
                                                <td>
                                                    <a href="<?php echo BASE_URL; ?>/pages/citizen/track_case.php?ob=<?php echo urlencode($case['ob_number']); ?>" 
                                                       class="text-primary">
                                                        <?php echo htmlspecialchars($case['ob_number']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($case['category']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo STATUS_COLORS[$case['status']] ?? 'status-reported'; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($case['assigned_officer'] ?: 'Not assigned'); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($case['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="text-center mt-3">
                                <a href="<?php echo BASE_URL; ?>/pages/citizen/track_case.php" class="btn btn-primary">
                                    View All My Cases
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <p class="text-muted">You haven't reported any cases yet.</p>
                                <p><small>Cases are reported in-person at police stations. Once reported, you can track them here.</small></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

               
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>National Crime Statistics (Last 30 Days)</h3>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($publicStats['total_national_cases'] ?? 0); ?></div>
                            <div class="stat-label">Total Cases Reported</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($publicStats['resolved_cases'] ?? 0); ?></div>
                            <div class="stat-label">Cases Resolved</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo round($publicStats['national_resolution_rate'] ?? 0, 1); ?>%</div>
                            <div class="stat-label">Resolution Rate</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo round($publicStats['avg_resolution_time'] ?? 0, 1); ?>h</div>
                            <div class="stat-label">Avg Resolution Time</div>
                        </div>
                    </div>

                    <?php if (!empty($countyStats)): ?>
                    <h4 class="mt-4 mb-3">Resolution Rates by County</h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>County</th>
                                    <th>Cases</th>
                                    <th>Resolved</th>
                                    <th>Resolution Rate</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($countyStats, 0, 8) as $county): ?>
                                    <?php
                                    $rate = $county['resolution_rate'];
                                    $status = $rate >= 80 ? 'Excellent' : ($rate >= 70 ? 'Good' : ($rate >= 60 ? 'Fair' : 'Needs Improvement'));
                                    $statusColor = $rate >= 80 ? 'success' : ($rate >= 70 ? 'warning' : 'danger');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($county['county']); ?></td>
                                        <td><?php echo number_format($county['case_count']); ?></td>
                                        <td><?php echo number_format($county['resolved_count']); ?></td>
                                        <td><?php echo $rate; ?>%</td>
                                        <td>
                                            <span class="badge badge-<?php echo $statusColor; ?>">
                                                <?php echo $status; ?>
                                            </span>
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
                    <h3>Safety Tips & Crime Prevention</h3>
                </div>
                <div class="card-body">
                    <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <div class="alert alert-info">
                            <h5> Home Security</h5>
                            <ul style="margin-bottom: 0; font-size: 0.9rem;">
                                <li>Install proper lighting around your property</li>
                                <li>Use deadbolts and security cameras</li>
                                <li>Don't advertise valuables or travel plans</li>
                                <li>Know your neighbors and join community watch</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning">
                            <h5> Vehicle Safety</h5>
                            <ul style="margin-bottom: 0; font-size: 0.9rem;">
                                <li>Always lock your vehicle</li>
                                <li>Park in well-lit, busy areas</li>
                                <li>Don't leave valuables visible</li>
                                <li>Be aware of your surroundings</li>
                            </ul>
                        </div>

                        <div class="alert alert-success">
                            <h5> Cyber Security</h5>
                            <ul style="margin-bottom: 0; font-size: 0.9rem;">
                                <li>Use strong, unique passwords</li>
                                <li>Be cautious with M-Pesa transactions</li>
                                <li>Verify requests before sharing personal info</li>
                                <li>Report suspicious online activities</li>
                            </ul>
                        </div>

                        <div class="alert alert-danger">
                            <h5> Personal Safety</h5>
                            <ul style="margin-bottom: 0; font-size: 0.9rem;">
                                <li>Stay alert in public places</li>
                                <li>Avoid walking alone at night</li>
                                <li>Trust your instincts</li>
                                <li>Keep emergency contacts handy</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- <div class="card">
                <div class="card-header">
                    <h3>System Updates & Announcements</h3>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-date"><?php echo date('M d, Y'); ?></div>
                            <div class="timeline-content">
                                <strong>System Enhancement:</strong> Case tracking system has been improved with real-time updates and mobile-friendly interface.
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-date"><?php echo date('M d, Y', strtotime('-3 days')); ?></div>
                            <div class="timeline-content">
                                <strong>New Feature:</strong> Citizens can now view detailed crime statistics and safety reports for their areas.
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-date"><?php echo date('M d, Y', strtotime('-1 week')); ?></div>
                            <div class="timeline-content">
                                <strong>Service Notice:</strong> Digital OB system now generates automatic OB numbers for all reported cases.
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->
        </main>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>

        setInterval(function() {

            fetch('<?php echo BASE_URL; ?>/api/check_case_updates.php')
                .then(response => response.json())
                .then(data => {
                    if (data.updates && data.updates.length > 0) {
                        showNotification('Case Update', 'You have new updates on your cases');
                    }
                })
                .catch(error => console.log('Update check failed:', error));
        }, 300000);

        function showNotification(title, message) {
            if (Notification.permission === 'granted') {
                new Notification(title, { 
                    body: message,
                    icon: '<?php echo BASE_URL; ?>/assets/images/police-icon.png'
                });
            } else {

                alert(title + ': ' + message);
            }
        }

        if (Notification.permission === 'default') {
            Notification.requestPermission();
        }

        document.addEventListener('DOMContentLoaded', function() {

            document.querySelectorAll('.kpi-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    if (this.href && !this.href.includes('#')) {
                        this.innerHTML += ' <span class="spinner"></span>';
                        this.disabled = true;
                    }
                });
            });

            document.querySelectorAll('.badge.status-reported').forEach(badge => {
                const row = badge.closest('tr');
                const dateCell = row.cells[row.cells.length - 1];
                const caseDate = new Date(dateCell.textContent);
                const daysDiff = (new Date() - caseDate) / (1000 * 60 * 60 * 24);

                if (daysDiff > 3) {
                    badge.classList.add('badge-warning');
                    badge.title = 'Case reported ' + Math.floor(daysDiff) + ' days ago';
                }
            });
        });

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>

    <style>
        .timeline {
            position: relative;
            padding-left: 2rem;
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
            margin-bottom: 1.5rem;
            background: var(--primary-white);
            padding: 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
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

        .timeline-date {
            font-size: 0.875rem;
            color: var(--medium-gray);
            margin-bottom: 0.5rem;
        }

        .timeline-content {
            color: var(--dark-gray);
            line-height: 1.5;
        }

        .badge-success { background-color: var(--success-green); }
        .badge-warning { background-color: var(--warning-orange); color: var(--primary-black); }
        .badge-danger { background-color: var(--danger-red); }

        .alert ul {
            padding-left: 1.2rem;
            margin: 0.5rem 0;
        }

        .alert ul li {
            margin-bottom: 0.25rem;
        }

        @media (max-width: 768px) {
            .app-layout {
                grid-template-areas: 
                    "header"
                    "main";
                grid-template-columns: 1fr;
            }

            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .d-grid[style*="2fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-responsive {
                font-size: 0.8rem;
            }

            .alert {
                padding: 0.75rem;
            }
        }
    </style>

     <?php renderHeaderScripts(); ?>
</body>
</html>
