<?php
define('UTUMISHI_WEB_APP', true);

session_start();
require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';
require_once __DIR__ . '/../../includes/classes/PredictiveAnalytics.php';

requireRole(ROLE_OCS);

$currentUser = getCurrentUser();
$stationId = $currentUser['station_id'];
$predictiveAnalytics = new PredictiveAnalytics();

// Get prediction timeframe from user input
$forecastDays = $_GET['days'] ?? 7;
$forecastDays = min(30, max(1, (int)$forecastDays)); // Limit between 1-30 days

$predictions = [];
$error = '';

try {
    $predictions = $predictiveAnalytics->getDashboardPredictions($stationId, $forecastDays);
} catch (Exception $e) {
    error_log("Predictive Analytics Error: " . $e->getMessage());
    $error = "Unable to generate predictions";
}

$pageTitle = "Predictive Crime Analytics";

require_once __DIR__ . '/../../includes/layout/layout.php';

?>

    <main class="app-main">

            <div class="mb-4">
                <h1>🔮 Predictive Crime Analytics</h1>
                <p class="text-muted">AI-powered crime prediction and resource optimization for proactive policing</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Forecast Controls -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Prediction Settings</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="d-flex gap-3 align-items-end">
                        <div>
                            <label for="days" class="form-label">Forecast Period</label>
                            <select name="days" id="days" class="form-control" onchange="this.form.submit()">
                                <option value="7" <?php echo $forecastDays == 7 ? 'selected' : ''; ?>>Next 7 days</option>
                                <option value="14" <?php echo $forecastDays == 14 ? 'selected' : ''; ?>>Next 14 days</option>
                                <option value="30" <?php echo $forecastDays == 30 ? 'selected' : ''; ?>>Next 30 days</option>
                            </select>
                        </div>
                        <div>
                            <span class="badge status-info">Last updated: <?php echo date('Y-m-d H:i'); ?></span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Early Warning Alerts -->
            <?php if (!empty($predictions['early_warnings'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>🚨 Early Warning System</h3>
                    <span class="badge status-danger"><?php echo count($predictions['early_warnings']); ?> alerts</span>
                </div>
                <div class="card-body">
                    <?php foreach (array_slice($predictions['early_warnings'], 0, 5) as $warning): ?>
                        <div class="alert alert-<?php echo $warning['severity'] === 'critical' ? 'danger' : ($warning['severity'] === 'high' ? 'warning' : 'info'); ?> mb-2">
                            <div class="d-flex justify-between items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($warning['title']); ?></strong><br>
                                    <?php echo htmlspecialchars($warning['message']); ?>
                                    
                                    <?php if (isset($warning['probability'])): ?>
                                        <div class="mt-1">
                                            <small><strong>Probability:</strong> <?php echo $warning['probability']; ?>%</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="badge status-<?php echo $warning['severity'] === 'critical' ? 'danger' : ($warning['severity'] === 'high' ? 'warning' : 'info'); ?>">
                                    <?php echo ucfirst($warning['severity']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Crime Forecast -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3>📈 Crime Volume Forecast</h3>
                    <span class="text-muted">Next <?php echo $forecastDays; ?> days prediction</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($predictions['crime_forecast'])): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Predicted Cases</th>
                                        <th>Risk Level</th>
                                        <th>Confidence</th>
                                        <th>Peak Hours</th>
                                        <th>Top Category</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($predictions['crime_forecast'] as $forecast): ?>
                                        <tr class="<?php echo $forecast['risk_level'] === 'high' ? 'table-warning' : ''; ?>">
                                            <td>
                                                <strong><?php echo date('M d', strtotime($forecast['date'])); ?></strong><br>
                                                <small><?php echo $forecast['day_name']; ?></small>
                                            </td>
                                            <td>
                                                <span class="h5"><?php echo $forecast['predicted_cases']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge status-<?php echo $forecast['risk_level'] === 'high' ? 'danger' : ($forecast['risk_level'] === 'medium' ? 'warning' : 'success'); ?>">
                                                    <?php echo ucfirst($forecast['risk_level']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $forecast['confidence_level']; ?>%</td>
                                            <td>
                                                <?php if (!empty($forecast['peak_hours'])): ?>
                                                    <?php echo implode(', ', array_slice($forecast['peak_hours'], 0, 2)); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($forecast['category_breakdown'])): ?>
                                                    <?php echo htmlspecialchars(array_keys($forecast['category_breakdown'])[0] ?? 'Mixed'); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Mixed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <div style="font-size: 3rem;">📊</div>
                            <h4>Generating Predictions...</h4>
                            <p class="text-muted">Crime forecasting requires historical data analysis.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hotspot Predictions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3>🗺️ Emerging Hotspot Predictions</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($predictions['hotspot_predictions'])): ?>
                        <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                            <?php foreach (array_slice($predictions['hotspot_predictions'], 0, 6) as $hotspot): ?>
                                <div class="alert alert-<?php echo $hotspot['confidence_score'] > 80 ? 'danger' : ($hotspot['confidence_score'] > 60 ? 'warning' : 'info'); ?>">
                                    <div class="d-flex justify-between items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($hotspot['location']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($hotspot['predicted_category']); ?></small>
                                            <div class="mt-2">
                                                <strong>Timeline:</strong> <?php echo htmlspecialchars($hotspot['estimated_timeline'] ?? 'Next 7-14 days'); ?><br>
                                                <strong>Trend:</strong> <?php echo htmlspecialchars($hotspot['current_trend'] ?? 'Increasing'); ?>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="h4 mb-0"><?php echo $hotspot['confidence_score']; ?>%</div>
                                            <small>Confidence</small>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($hotspot['recommended_actions'])): ?>
                                        <div class="mt-2">
                                            <small><strong>Actions:</strong> <?php echo htmlspecialchars(implode(', ', array_slice($hotspot['recommended_actions'], 0, 2))); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <div style="font-size: 3rem;">✅</div>
                            <h4>No Emerging Hotspots Predicted</h4>
                            <p class="text-muted">Current patterns suggest stable crime distribution.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resource Predictions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3>👥 Resource Demand Forecast</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($predictions['resource_predictions'])): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Expected Cases</th>
                                        <th>Officers Needed</th>
                                        <th>Available</th>
                                        <th>Gap</th>
                                        <th>Workload Level</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($predictions['resource_predictions'] as $resource): ?>
                                        <tr class="<?php echo $resource['officer_gap'] > 0 ? 'table-warning' : ''; ?>">
                                            <td>
                                                <strong><?php echo date('M d', strtotime($resource['date'])); ?></strong><br>
                                                <small><?php echo $resource['day_name']; ?></small>
                                            </td>
                                            <td><?php echo $resource['expected_cases']; ?></td>
                                            <td><?php echo $resource['required_officers']; ?></td>
                                            <td><?php echo $resource['available_officers']; ?></td>
                                            <td>
                                                <?php if ($resource['officer_gap'] > 0): ?>
                                                    <span class="text-danger">-<?php echo $resource['officer_gap']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-success">✓</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge status-<?php echo $resource['workload_level'] === 'high' ? 'danger' : ($resource['workload_level'] === 'medium' ? 'warning' : 'success'); ?>">
                                                    <?php echo ucfirst($resource['workload_level']); ?>
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

            <!-- Patrol Optimization -->
            <div class="card">
                <div class="card-header">
                    <h3>🚓 Optimized Patrol Recommendations</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($predictions['patrol_optimization'])): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Area</th>
                                        <th>Risk Score</th>
                                        <th>Optimal Times</th>
                                        <th>Frequency</th>
                                        <th>Patrol Type</th>
                                        <th>Officers Needed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($predictions['patrol_optimization'], 0, 8) as $patrol): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($patrol['area']); ?></td>
                                            <td>
                                                <span class="badge status-<?php echo $patrol['risk_score'] > 70 ? 'danger' : ($patrol['risk_score'] > 40 ? 'warning' : 'success'); ?>">
                                                    <?php echo $patrol['risk_score']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($patrol['optimal_patrol_times'])): ?>
                                                    <?php echo implode(', ', array_slice($patrol['optimal_patrol_times'], 0, 3)); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Flexible</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($patrol['recommended_frequency'] ?? 'Standard'); ?></td>
                                            <td><?php echo htmlspecialchars($patrol['patrol_type'] ?? 'Vehicle'); ?></td>
                                            <td><?php echo $patrol['officer_requirements'] ?? 2; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <div style="font-size: 3rem;">🚓</div>
                            <h4>Standard Patrol Schedule Recommended</h4>
                            <p class="text-muted">Current risk levels suggest maintaining regular patrol patterns.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
    
    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>
        // Auto-refresh predictions every 30 minutes
        setInterval(function() {
            if (!document.hidden) {
                location.reload();
            }
        }, 1800000);
        
        // Highlight high-risk days
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.table-warning').forEach(row => {
                row.style.borderLeft = '4px solid var(--warning-orange)';
            });
            
            // Add tooltips for confidence scores
            document.querySelectorAll('[data-confidence]').forEach(element => {
                element.title = `Prediction confidence: ${element.dataset.confidence}%`;
            });
        });
        
        // Prediction accuracy indicator
        function showPredictionInfo() {
            alert('Predictions are based on historical crime patterns, seasonal trends, and resource availability. Confidence levels indicate the reliability of each forecast.');
        }
    </script>
    
    <style>
        .alert {
            transition: all 0.3s ease;
        }
        
        .alert:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .badge {
            font-size: 0.8em;
            padding: 0.3em 0.6em;
        }
        
        .table-warning {
            background-color: rgba(255, 193, 7, 0.1);
        }
        
        @media (max-width: 768px) {
            .d-grid[style*="auto-fit"] {
                grid-template-columns: 1fr !important;
            }
            
            .table-responsive {
                font-size: 0.8rem;
            }
        }
    </style>
</body>
</html>