<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/CrimeAnalyzer.php';

requireRole(ROLE_CITIZEN);

$currentUser = getCurrentUser();
$crimeAnalyzer = new CrimeAnalyzer();

$filters = [
    'timeframe' => (int)($_GET['timeframe'] ?? 30),
    'county' => sanitizeText($_GET['county'] ?? ''),
    'category' => sanitizeText($_GET['category'] ?? '')
];

if (!in_array($filters['timeframe'], [7, 30, 90, 365])) {
    $filters['timeframe'] = 30;
}

try {
    $db = Database::getInstance();

    $nationalStats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_cases,
            COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
            ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
            AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time,
            COUNT(DISTINCT station_id) as active_stations
        FROM cases 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)",
        ['timeframe' => $filters['timeframe']]
    );

    $categoryStats = $db->fetchAll("
        SELECT 
            category,
            COUNT(*) as total_cases,
            COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
            ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate
        FROM cases 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)
        GROUP BY category
        ORDER BY total_cases DESC",
        ['timeframe' => $filters['timeframe']]
    );

    $countyStats = $db->fetchAll("
        SELECT 
            location_county as county,
            COUNT(*) as total_cases,
            COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
            ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
            COUNT(DISTINCT station_id) as station_count
        FROM cases 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)
        GROUP BY location_county
        ORDER BY total_cases DESC
        LIMIT 15",
        ['timeframe' => $filters['timeframe']]
    );

    $monthlyTrends = $db->fetchAll("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_cases,
            COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases
        FROM cases 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month",
        []
    );

    $safetyRatings = [];
    foreach ($countyStats as $county) {
        $casesPerStation = $county['station_count'] > 0 ? $county['total_cases'] / $county['station_count'] : $county['total_cases'];
        $resolutionRate = $county['resolution_rate'];

        $safetyScore = 100;

        if ($casesPerStation > 50) $safetyScore -= 30;
        elseif ($casesPerStation > 30) $safetyScore -= 20;
        elseif ($casesPerStation > 15) $safetyScore -= 10;

        if ($resolutionRate < 50) $safetyScore -= 25;
        elseif ($resolutionRate < 70) $safetyScore -= 15;
        elseif ($resolutionRate < 80) $safetyScore -= 5;

        $safetyScore = max(0, min(100, $safetyScore));

        if ($safetyScore >= 80) $safetyLevel = 'Very Safe';
        elseif ($safetyScore >= 65) $safetyLevel = 'Safe';
        elseif ($safetyScore >= 50) $safetyLevel = 'Moderate';
        elseif ($safetyScore >= 35) $safetyLevel = 'Caution Advised';
        else $safetyLevel = 'High Alert';

        $safetyRatings[$county['county']] = [
            'score' => $safetyScore,
            'level' => $safetyLevel,
            'cases_per_station' => round($casesPerStation, 1)
        ];
    }

} catch (Exception $e) {
    error_log("Public Stats Error: " . $e->getMessage());
    $error = "Unable to load statistics";
}

$pageTitle = "Public Crime Statistics";

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
                <h2>Public Crime Statistics</h2>
                <p class="text-muted">Transparent crime data and safety information for Kenya</p>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h3> Data Filters</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="d-flex gap-3" style="align-items: flex-end;">
                        <div class="form-group mb-0">
                            <label for="timeframe" class="form-label">Time Period</label>
                            <select id="timeframe" name="timeframe" class="form-control form-select">
                                <option value="7" <?php echo $filters['timeframe'] == 7 ? 'selected' : ''; ?>>Last 7 days</option>
                                <option value="30" <?php echo $filters['timeframe'] == 30 ? 'selected' : ''; ?>>Last 30 days</option>
                                <option value="90" <?php echo $filters['timeframe'] == 90 ? 'selected' : ''; ?>>Last 3 months</option>
                                <option value="365" <?php echo $filters['timeframe'] == 365 ? 'selected' : ''; ?>>Last year</option>
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <label for="county" class="form-label">County (Optional)</label>
                            <select id="county" name="county" class="form-control form-select">
                                <option value="">All Counties</option>
                                <?php foreach (array_keys(KENYAN_COUNTIES) as $county): ?>
                                    <option value="<?php echo htmlspecialchars($county); ?>" 
                                            <?php echo $filters['county'] === $county ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($county); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <label for="category" class="form-label">Crime Type (Optional)</label>
                            <select id="category" name="category" class="form-control form-select">
                                <option value="">All Crime Types</option>
                                <?php foreach (array_keys(CRIME_CATEGORIES) as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>"
                                            <?php echo $filters['category'] === $category ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                             Update Statistics
                        </button>
                    </form>
                </div>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo number_format($nationalStats['total_cases'] ?? 0); ?></div>
                    <div class="kpi-label">Total Cases Reported</div>
                    <div class="kpi-change">
                        Last <?php echo $filters['timeframe']; ?> days
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value"><?php echo $nationalStats['resolution_rate'] ?? 0; ?>%</div>
                    <div class="kpi-label">National Resolution Rate</div>
                    <div class="kpi-change">
                        <span class="<?php echo ($nationalStats['resolution_rate'] ?? 0) >= 70 ? 'positive' : 'negative'; ?>">
                            <?php echo ($nationalStats['resolution_rate'] ?? 0) >= 70 ? 'Good performance' : 'Needs improvement'; ?>
                        </span>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value"><?php echo round($nationalStats['avg_resolution_time'] ?? 0, 1); ?>h</div>
                    <div class="kpi-label">Average Resolution Time</div>
                    <div class="kpi-change">
                        Target: 48-72 hours
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value"><?php echo $nationalStats['active_stations'] ?? 0; ?></div>
                    <div class="kpi-label">Active Police Stations</div>
                    <div class="kpi-change">
                        Serving nationwide
                    </div>
                </div>
            </div>

            <div class="d-grid" style="grid-template-columns: 2fr 1fr; gap: 2rem;">

                <div class="card">
                    <div class="card-header">
                        <h3> Crime Category Breakdown</h3>
                        <span class="text-muted">Last <?php echo $filters['timeframe']; ?> days</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($categoryStats)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Crime Type</th>
                                            <th>Cases</th>
                                            <th>Resolved</th>
                                            <th>Resolution Rate</th>
                                            <th>Trend</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categoryStats as $category): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($category['category']); ?></strong></td>
                                                <td><?php echo number_format($category['total_cases']); ?></td>
                                                <td><?php echo number_format($category['resolved_cases']); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress-bar" style="width: 60px; height: 8px; background: #eee; border-radius: 4px; overflow: hidden; margin-right: 8px;">
                                                            <div style="width: <?php echo $category['resolution_rate']; ?>%; height: 100%; background: <?php echo $category['resolution_rate'] >= 70 ? 'var(--success-green)' : ($category['resolution_rate'] >= 50 ? 'var(--warning-orange)' : 'var(--danger-red)'); ?>"></div>
                                                        </div>
                                                        <span class="<?php echo $category['resolution_rate'] >= 70 ? 'text-success' : ($category['resolution_rate'] >= 50 ? 'text-warning' : 'text-danger'); ?>">
                                                            <?php echo $category['resolution_rate']; ?>%
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $trend = '';
                                                    $cases = $category['total_cases'];
                                                    if ($cases >= 100) $trend = 'Very High';
                                                    elseif ($cases >= 50) $trend = 'High';
                                                    elseif ($cases >= 20) $trend = 'Moderate';
                                                    else $trend = 'Low';
                                                    echo $trend;
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <p class="text-muted">No crime data available for the selected period.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3> Monthly Trends</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($monthlyTrends)): ?>
                            <div class="chart-container" id="monthlyChart">

                                <?php
                                $maxCases = max(array_column($monthlyTrends, 'total_cases'));
                                ?>
                                <?php foreach (array_slice($monthlyTrends, -6) as $trend): ?>
                                    <?php
                                    $height = $maxCases > 0 ? ($trend['total_cases'] / $maxCases) * 100 : 0;
                                    $month = date('M', strtotime($trend['month'] . '-01'));
                                    ?>
                                    <div class="chart-bar" style="margin-bottom: 1rem;">
                                        <div class="d-flex justify-between items-center mb-1">
                                            <small><strong><?php echo $month; ?></strong></small>
                                            <small><?php echo $trend['total_cases']; ?> cases</small>
                                        </div>
                                        <div style="width: 100%; height: 20px; background: #eee; border-radius: 10px; overflow: hidden;">
                                            <div style="width: <?php echo $height; ?>%; height: 100%; background: linear-gradient(90deg, var(--primary-green), var(--primary-red)); transition: width 0.5s ease;"></div>
                                        </div>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <?php echo round(($trend['resolved_cases'] / max($trend['total_cases'], 1)) * 100); ?>% resolved
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-3">
                                <p class="text-muted">No trend data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3> County Safety Assessment</h3>
                    <p class="text-muted mb-0">Based on crime rates, resolution efficiency, and police coverage</p>
                </div>
                <div class="card-body">
                    <?php if (!empty($safetyRatings)): ?>
                        <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                            <?php foreach ($safetyRatings as $county => $rating): ?>
                                <?php
                                $colorClass = '';
                                if ($rating['score'] >= 80) $colorClass = 'success';
                                elseif ($rating['score'] >= 65) $colorClass = 'info';
                                elseif ($rating['score'] >= 50) $colorClass = 'warning';
                                else $colorClass = 'danger';
                                ?>
                                <div class="alert alert-<?php echo $colorClass; ?>">
                                    <div class="d-flex justify-between items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($county); ?> County</strong>
                                            <div class="mt-1">
                                                <span class="badge status-<?php echo $colorClass; ?>"><?php echo $rating['level']; ?></span>
                                            </div>
                                            <small class="mt-1 d-block">
                                                <?php echo $rating['cases_per_station']; ?> cases per station
                                            </small>
                                        </div>
                                        <div class="text-right">
                                            <div class="h2 mb-0"><?php echo $rating['score']; ?></div>
                                            <small>Safety Score</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Understanding These Statistics</h3>
                </div>
                <div class="card-body">
                    <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        <div>
                            <h5> What These Numbers Mean</h5>
                            <ul class="mb-0">
                                <li><strong>Total Cases:</strong> All crimes reported to police in the time period</li>
                                <li><strong>Resolution Rate:</strong> Percentage of cases that have been resolved or closed</li>
                                <li><strong>Average Resolution Time:</strong> How long it takes to resolve cases on average</li>
                            </ul>
                        </div>

                        <div>
                            <h5> Safety Scores Explained</h5>
                            <ul class="mb-0">
                                <li><strong>80-100:</strong> Very Safe - Low crime, high resolution rates</li>
                                <li><strong>65-79:</strong> Safe - Manageable crime levels, good policing</li>
                                <li><strong>50-64:</strong> Moderate - Average crime rates, room for improvement</li>
                                <li><strong>Below 50:</strong> Caution/High Alert - Higher crime rates or lower resolution</li>
                            </ul>
                        </div>

                        <div>
                            <h5> How to Stay Safe</h5>
                            <ul class="mb-0">
                                <li>Stay informed about your area's crime trends</li>
                                <li>Report crimes promptly to help improve statistics</li>
                                <li>Follow safety guidelines for high-risk areas</li>
                                <li>Support community policing initiatives</li>
                            </ul>
                        </div>

                        <div>
                            <h5> Emergency Contacts</h5>
                            <ul class="mb-0">
                                <li><strong>Emergency:</strong> 999 or 911</li>
                                <li><strong>Police Hotline:</strong> 999</li>
                                <li><strong>DCI Hotline:</strong> 0800 722 203</li>
                                <li><strong>Crime Stoppers:</strong> 0800 CRIME</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>

        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', function() {

                if (this.closest('form')) {
                    this.closest('form').submit();
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-bar div');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });

            const kpiCards = document.querySelectorAll('.kpi-card');
            kpiCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            const chartBars = document.querySelectorAll('.chart-bar div[style*="width"]');
            chartBars.forEach((bar, index) => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.transition = 'width 1s ease';
                    bar.style.width = width;
                }, 500 + (index * 100));
            });

            document.querySelectorAll('.alert').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                    this.style.transition = 'transform 0.2s ease';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });

        function shareStats() {
            if (navigator.share) {
                navigator.share({
                    title: 'Kenya Crime Statistics',
                    text: 'Check out the latest public crime statistics and safety information',
                    url: window.location.href
                }).catch(console.error);
            } else {

                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Statistics URL copied to clipboard');
                });
            }
        }

        function exportStats() {
            const data = {
                timeframe: <?php echo $filters['timeframe']; ?>,
                national_stats: <?php echo json_encode($nationalStats ?? []); ?>,
                category_stats: <?php echo json_encode($categoryStats ?? []); ?>,
                county_stats: <?php echo json_encode($countyStats ?? []); ?>,
                generated_at: new Date().toISOString()
            };

            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `crime_statistics_${data.timeframe}days_${new Date().toISOString().split('T')[0]}.json`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function printStats() {
            window.print();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const header = document.querySelector('h1').parentNode;
            const actionDiv = document.createElement('div');
            actionDiv.className = 'd-flex gap-2 mt-2';
            actionDiv.innerHTML = `
                <button onclick="shareStats()" class="btn btn-sm btn-outline btn-primary no-print">📤 Share</button>
                <button onclick="exportStats()" class="btn btn-sm btn-outline btn-secondary no-print">💾 Export</button>
                <button onclick="printStats()" class="btn btn-sm btn-outline btn-secondary no-print">🖨️ Print</button>
            `;
            header.appendChild(actionDiv);
        });

        function updatePageTitle() {
            const timeframe = <?php echo $filters['timeframe']; ?>;
            const county = '<?php echo $filters['county']; ?>';
            const category = '<?php echo $filters['category']; ?>';

            let title = 'Kenya Crime Statistics';
            if (county) title += ` - ${county} County`;
            if (category) title += ` - ${category}`;
            title += ` (${timeframe} days)`;

            document.title = title + ' - Utumishi';
        }

        updatePageTitle();

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'p':
                        e.preventDefault();
                        printStats();
                        break;
                    case 's':
                        e.preventDefault();
                        shareStats();
                        break;
                    case 'e':
                        e.preventDefault();
                        exportStats();
                        break;
                }
            }
        });

        document.addEventListener('DOMContentLoaded', function() {

            document.querySelectorAll('.progress-bar').forEach(bar => {
                const percentage = bar.nextElementSibling.textContent.trim();
                bar.setAttribute('role', 'progressbar');
                bar.setAttribute('aria-valuenow', percentage.replace('%', ''));
                bar.setAttribute('aria-valuemin', '0');
                bar.setAttribute('aria-valuemax', '100');
                bar.setAttribute('aria-label', `Resolution rate: ${percentage}`);
            });

            document.querySelectorAll('.alert').forEach(alert => {
                const county = alert.querySelector('strong').textContent;
                const score = alert.querySelector('.h2').textContent;
                const level = alert.querySelector('.badge').textContent;
                alert.setAttribute('title', `${county}: Safety score ${score}/100 (${level})`);
            });
        });

        setInterval(function() {
            if (!document.hidden) {
                location.reload();
            }
        }, 600000);

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

        .chart-container {
            padding: 1rem;
        }

        .chart-bar {
            margin-bottom: 1rem;
        }

        .progress-bar {
            border-radius: 10px;
            overflow: hidden;
            background-color: #e9ecef;
            transition: all 0.3s ease;
        }

        .progress-bar:hover {
            box-shadow: 0 0 0 2px rgba(0, 107, 63, 0.25);
        }

        .kpi-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .kpi-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-green);
            transition: color 0.3s ease;
        }

        .kpi-card:hover .kpi-value {
            color: var(--primary-red);
        }

        .alert {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .alert:hover {
            transform: scale(1.02);
        }

        .alert-success {
            border-left: 4px solid var(--success-green);
        }

        .alert-info {
            border-left: 4px solid var(--info-blue);
        }

        .alert-warning {
            border-left: 4px solid var(--warning-orange);
        }

        .alert-danger {
            border-left: 4px solid var(--danger-red);
        }

        .table tbody tr:hover {
            background-color: rgba(0, 107, 63, 0.05);
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 107, 63, 0.25);
        }

        @media (max-width: 768px) {
            .d-grid[style*="2fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .d-flex.gap-3 {
                flex-direction: column;
                gap: 1rem;
            }

            .chart-container {
                padding: 0.5rem;
            }

            .kpi-value {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }

            .table-responsive {
                font-size: 0.85rem;
            }

            .d-grid[style*="repeat(auto-fit, minmax(300px, 1fr))"] {
                grid-template-columns: 1fr !important;
            }
        }

        @media print {
            .no-print, .btn, .form-control, .app-sidebar, .app-header {
                display: none !important;
            }

            .app-layout {
                grid-template-areas: "main";
                grid-template-columns: 1fr;
            }

            .card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #000;
            }

            .alert {
                border: 1px solid #000;
                background: transparent !important;
            }

            .kpi-card {
                border: 1px solid #000;
                background: transparent !important;
            }

            .chart-bar div {
                background: #000 !important;
            }

            .progress-bar div {
                background: #000 !important;
            }
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading::after {
            content: 'Loading...';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--primary-white);
            padding: 1rem 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
        }

        .btn:focus,
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(0, 107, 63, 0.25);
        }

        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
        }

        .metric-excellent { color: var(--success-green); }
        .metric-good { color: var(--info-blue); }
        .metric-fair { color: var(--warning-orange); }
        .metric-poor { color: var(--danger-red); }

        [data-tooltip] {
            position: relative;
        }

        [data-tooltip]:hover::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark-gray);
            color: var(--primary-white);
            padding: 0.5rem;
            border-radius: var(--border-radius);
            font-size: 0.8rem;
            white-space: nowrap;
            z-index: 1000;
            opacity: 0;
            animation: fadeIn 0.3s ease forwards;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }
    </style>

     <?php renderHeaderScripts(); ?>
</body>
</html>
