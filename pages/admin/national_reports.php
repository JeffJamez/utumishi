<?php
define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';
require_once __DIR__ . '/../../includes/classes/NationalReportsManager.php';

requireRole(ROLE_ADMIN);

$currentUser = getCurrentUser();
$reportsManager = new NationalReportsManager();

// Handle report generation and export
$reportType = $_GET['type'] ?? '';
$reportData = [];
$reportGenerated = false;
$error = '';

if ($_GET['action'] ?? '' === 'export' && $reportType) {
    try {
        switch ($reportType) {
            case 'national':
                $timeframe = $_GET['timeframe'] ?? 30;
                $reportData = $reportsManager->generateNationalReport($timeframe);
                $reportsManager->exportAsCSV($reportData, "national_report_{$timeframe}days_" . date('Y-m-d') . '.csv');
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Export failed: ' . $e->getMessage();
    }
}

if ($reportType) {
    try {
        switch ($reportType) {
            case 'national':
                $timeframe = $_GET['timeframe'] ?? 30;
                $reportData = $reportsManager->generateNationalReport($timeframe);
                break;
                
            case 'resource':
                $reportData = $reportsManager->generateResourceReport();
                break;
                
            case 'officer_performance':
                $timeframe = $_GET['timeframe'] ?? 30;
                $reportData = $reportsManager->generateOfficerPerformanceReport($timeframe);
                break;
                
            case 'hotspots':
                $timeframe = $_GET['timeframe'] ?? 30;
                $reportData = $reportsManager->generateHotspotsReport($timeframe);
                break;
        }
        $reportGenerated = true;
    } catch (Exception $e) {
        error_log("National Reports Error: " . $e->getMessage());
        $error = 'Failed to generate report: ' . $e->getMessage();
    }
}

$pageTitle = "National Reports";
require_once __DIR__ . '/../../includes/layout/layout.php';

?>

     <main class="app-main">

            <div class="mb-4">
                <h2>National Reports</h2>
                <p class="text-muted">Generate comprehensive reports on national crime statistics and police performance</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Report Generation Cards -->
            <style>
                .reports-container {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 20px;
                    justify-content: center;
                    padding: 20px;
                }

                .report-card {
                    flex: 1 1 320px;
                    max-width: 380px;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    overflow: hidden;
                    background: white;
                    transition: transform 0.3s ease, box-shadow 0.3s ease;
                }

                .report-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                }

                .report-header {
                    background: linear-gradient(135deg, #007bff, #0056b3);
                    color: white;
                    padding: 15px;
                    text-align: center;
                }

                .report-body {
                    padding: 20px;
                    text-align: center;
                }

                .report-icon {
                    font-size: 48px;
                    margin-bottom: 10px;
                    opacity: 0.9;
                }

                .report-title {
                    font-size: 18px;
                    font-weight: bold;
                    margin-bottom: 8px;
                }

                .report-description {
                    color: #666;
                    margin-bottom: 15px;
                    line-height: 1.4;
                    font-size: 14px;
                }

                .report-form {
                    margin-top: 15px;
                }

                .report-form select {
                    width: 100%;
                    padding: 8px;
                    margin-bottom: 10px;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    font-size: 14px;
                }

                .report-form label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: 600;
                    color: #333;
                }

                .btn-report {
                    background: #28a745;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 14px;
                    width: 100%;
                    transition: background 0.3s ease;
                }

                .btn-report:hover {
                    background: #218838;
                }

                @media (max-width: 768px) {
                    .reports-container {
                        padding: 10px;
                        gap: 15px;
                    }

                    .report-card {
                        flex: 1 1 100%;
                        max-width: none;
                    }
                }
            </style>

            <div class="reports-container">
                <!-- National Crime Report Card -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-icon">📊</div>
                        <div class="report-title">National Crime Report</div>
                    </div>
                    <div class="report-body">
                        <div class="report-description">Comprehensive analysis of crime statistics across all counties and stations</div>
                        <div class="report-form">
                            <form method="GET">
                                <input type="hidden" name="type" value="national">
                                <label>Time Period</label>
                                <select name="timeframe">
                                    <option value="7">Last 7 days</option>
                                    <option value="30" selected>Last 30 days</option>
                                    <option value="90">Last 90 days</option>
                                    <option value="365">Last year</option>
                                </select>
                                <button type="submit" class="btn-report">Generate Report</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Resource Allocation Report Card -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-icon">📋</div>
                        <div class="report-title">Resource Allocation</div>
                    </div>
                    <div class="report-body">
                        <div class="report-description">Analysis of resource distribution and station performance across stations</div>
                        <div class="report-form">
                            <form method="GET">
                                <input type="hidden" name="type" value="resource">
                                <button type="submit" class="btn-report">Generate Report</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Officer Performance Report Card -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-icon">👮</div>
                        <div class="report-title">Officer Performance</div>
                    </div>
                    <div class="report-body">
                        <div class="report-description">National overview of officer workload and performance metrics</div>
                        <div class="report-form">
                            <form method="GET">
                                <input type="hidden" name="type" value="officer_performance">
                                <label>Time Period</label>
                                <select name="timeframe">
                                    <option value="30" selected>Last 30 days</option>
                                    <option value="90">Last 90 days</option>
                                    <option value="365">Last year</option>
                                </select>
                                <button type="submit" class="btn-report">Generate Report</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Crime Hotspots Report Card -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-icon">🔥</div>
                        <div class="report-title">Crime Hotspots</div>
                    </div>
                    <div class="report-body">
                        <div class="report-description">Identification of high-crime areas requiring increased attention</div>
                        <div class="report-form">
                            <form method="GET">
                                <input type="hidden" name="type" value="hotspots">
                                <label>Time Period</label>
                                <select name="timeframe">
                                    <option value="30" selected>Last 30 days</option>
                                    <option value="60">Last 60 days</option>
                                    <option value="90">Last 90 days</option>
                                </select>
                                <button type="submit" class="btn-report">Generate Report</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Generated Report Display -->
            <?php if ($reportGenerated && !empty($reportData)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><?php echo htmlspecialchars($reportData['type']); ?></h3>
                    <div>
                        <button onclick="window.print()" class="btn btn-sm btn-outline btn-primary">Print Report</button>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['action' => 'export'])); ?>" 
                        class="btn btn-sm btn-outline btn-secondary">Export CSV</a>
                    </div>
                </div>
                <div class="card-body" id="report-content">
                    
                    <?php if ($reportType === 'national'): ?>
                        <!-- National Crime Report -->
                        <div class="mb-4">
                            <h4>National Crime Report - <?php echo htmlspecialchars($reportData['period_description']); ?></h4>
                            
                            <!-- Overall Statistics -->
                            <div class="kpi-grid mb-4">
                                <div class="kpi-card">
                                    <div class="kpi-value"><?php echo number_format($reportData['overall_statistics']['total_cases'] ?? 0); ?></div>
                                    <div class="kpi-label">Total Cases</div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-value"><?php echo $reportData['overall_statistics']['resolution_rate'] ?? 0; ?>%</div>
                                    <div class="kpi-label">Resolution Rate</div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-value"><?php echo $reportData['overall_statistics']['active_stations'] ?? 0; ?></div>
                                    <div class="kpi-label">Active Stations</div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-value"><?php echo $reportData['overall_statistics']['affected_counties'] ?? 0; ?></div>
                                    <div class="kpi-label">Counties</div>
                                </div>
                            </div>
                            
                            <!-- County Breakdown -->
                            <h5>County Performance</h5>
                            <div class="table-responsive mb-4">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>County</th>
                                            <th>Total Cases</th>
                                            <th>Resolved</th>
                                            <th>Resolution Rate</th>
                                            <th>Avg Time</th>
                                            <th>% of National</th>
                                            <th>Stations</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['county_breakdown'] as $county): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($county['county']); ?></strong></td>
                                                <td><?php echo number_format($county['total_cases']); ?></td>
                                                <td><?php echo number_format($county['resolved_cases']); ?></td>
                                                <td>
                                                    <span class="badge status-<?php echo $county['resolution_rate'] >= 70 ? 'success' : ($county['resolution_rate'] >= 50 ? 'warning' : 'danger'); ?>">
                                                        <?php echo $county['resolution_rate']; ?>%
                                                    </span>
                                                </td>
                                                <td><?php echo round($county['avg_resolution_time'] ?? 0, 1); ?>h</td>
                                                <td><?php echo $county['percentage_of_national']; ?>%</td>
                                                <td><?php echo $county['station_count']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Crime Categories -->
                            <h5>Crime Category Analysis</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Cases</th>
                                            <th>Resolved</th>
                                            <th>Resolution Rate</th>
                                            <th>% of Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['category_trends'] as $category): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($category['category']); ?></td>
                                                <td><?php echo number_format($category['total_cases']); ?></td>
                                                <td><?php echo number_format($category['resolved_cases']); ?></td>
                                                <td><?php echo $category['resolution_rate']; ?>%</td>
                                                <td><?php echo $category['percentage_of_total']; ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                    <?php elseif ($reportType === 'resource'): ?>
                        <!-- Resource Allocation Report -->
                        <div class="mb-4">
                            <h4>Resource Allocation Report</h4>
                            

                            
                            <!-- Station Resources -->
                            <h5>Station Resource Allocation</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Station</th>
                                            <th>County</th>
                                            <th>Officers</th>
                                            <th>Cases (30 days)</th>
                                            <th>Cases/Officer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['station_resources'] as $station): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($station['station_name']); ?></td>
                                                <td><?php echo htmlspecialchars($station['county']); ?></td>
                                                <td><?php echo $station['officer_count']; ?></td>
                                                <td><?php echo $station['cases_handled']; ?></td>
                                                <td><?php echo $station['cases_per_officer']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                    <?php elseif ($reportType === 'officer_performance'): ?>
                        <!-- Officer Performance Report -->
                        <div class="mb-4">
                            <h4>Officer Performance Report</h4>
                            
                            <!-- National Summary -->
                            <div class="kpi-grid mb-4">
                                <div class="kpi-card">
                                    <div class="kpi-value"><?php echo number_format($reportData['national_summary']['total_officers_national'] ?? 0); ?></div>
                                    <div class="kpi-label">Total Officers</div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-value"><?php echo round($reportData['national_summary']['national_avg_case_load'] ?? 0, 1); ?></div>
                                    <div class="kpi-label">Avg Case Load</div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-value"><?php echo $reportData['national_summary']['total_overloaded'] ?? 0; ?></div>
                                    <div class="kpi-label">Overloaded Officers</div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-value"><?php echo $reportData['national_summary']['max_case_load'] ?? 0; ?></div>
                                    <div class="kpi-label">Max Case Load</div>
                                </div>
                            </div>
                            
                            <!-- Performance by Station -->
                            <h5>Station Performance Overview</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Station</th>
                                            <th>County</th>
                                            <th>Officers</th>
                                            <th>Avg Case Load</th>
                                            <th>Avg Resolution Time</th>
                                            <th>Overloaded</th>
                                            <th>Idle</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['performance_by_station'] as $station): ?>
                                            <?php
                                            $avgLoad = $station['avg_case_load'];
                                            $status = $avgLoad > 12 ? 'High Load' : ($avgLoad > 8 ? 'Normal' : 'Light Load');
                                            $statusClass = $avgLoad > 12 ? 'warning' : ($avgLoad > 8 ? 'success' : 'info');
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($station['station_name']); ?></td>
                                                <td><?php echo htmlspecialchars($station['county']); ?></td>
                                                <td><?php echo $station['total_officers']; ?></td>
                                                <td><?php echo round($avgLoad, 1); ?></td>
                                                <td><?php echo round($station['avg_resolution_time'] ?? 0, 1); ?>h</td>
                                                <td><?php echo $station['overloaded_officers']; ?></td>
                                                <td><?php echo $station['idle_officers']; ?></td>
                                                <td>
                                                    <span class="badge status-<?php echo $statusClass; ?>">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                    <?php elseif ($reportType === 'hotspots'): ?>
                        <!-- Crime Hotspots Report -->
                        <div class="mb-4">
                            <h4>Crime Hotspots Report - <?php echo $reportData['timeframe']; ?> days</h4>
                            
                            <div class="alert alert-info mb-4">
                                <strong>Analysis Criteria:</strong> Areas with 5+ cases in the selected timeframe, ranked by case volume and frequency.
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Location</th>
                                            <th>Crime Type</th>
                                            <th>Cases</th>
                                            <th>Cases/Month</th>
                                            <th>% of County</th>
                                            <th>Severity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['hotspots'] as $index => $hotspot): ?>
                                            <?php
                                            $rank = $index + 1;
                                            $casesPerMonth = $hotspot['cases_per_month'];
                                            $severity = $casesPerMonth >= 15 ? 'Critical' : ($casesPerMonth >= 10 ? 'High' : 'Medium');
                                            $severityClass = $casesPerMonth >= 15 ? 'danger' : ($casesPerMonth >= 10 ? 'warning' : 'info');
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo $rank; ?></strong>
                                                     <?php if ($rank <= 3): ?>
                                                         #<?php echo $rank; ?>
                                                     <?php endif; ?>
                                                </td>
                                                 <td>
                                                      <strong><?php echo htmlspecialchars($hotspot['incident_location_constituency']); ?><?php if ($hotspot['incident_local_area']): ?>, <?php echo htmlspecialchars($hotspot['incident_local_area']); ?><?php endif; ?></strong><br>
                                                      <small class="text-muted"><?php echo htmlspecialchars($hotspot['incident_location_county']); ?> County</small>
                                                 </td>
                                                <td><?php echo htmlspecialchars($hotspot['category']); ?></td>
                                                <td><?php echo $hotspot['case_count']; ?></td>
                                                <td><?php echo $casesPerMonth; ?></td>
                                                <td><?php echo $hotspot['percentage_of_county']; ?>%</td>
                                                <td>
                                                    <span class="badge status-<?php echo $severityClass; ?>">
                                                        <?php echo $severity; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-warning mt-4">
                                <strong>Recommended Actions:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Deploy additional patrols to Critical and High severity areas</li>
                                    <li>Increase community engagement in top-ranked hotspots</li>
                                    <li>Consider specialized units for recurring crime types</li>
                                    <li>Coordinate with local authorities for environmental improvements</li>
                                </ul>
                            </div>
                        </div>
                        
                    <?php endif; ?>
                    
                    <div class="mt-4 text-muted">
                        <small>Report generated on <?php echo $reportData['generated_at']; ?> by <?php echo htmlspecialchars($currentUser['name']); ?></small>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
    
    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>
        // Auto-refresh prevention for reports
        let reportGenerated = <?php echo $reportGenerated ? 'true' : 'false'; ?>;
        
        if (reportGenerated) {
            // Disable auto-refresh when viewing reports
            window.addEventListener('beforeunload', function(e) {
                e.preventDefault();
                e.returnValue = '';
            });
        }
        
        // Print functionality
        function printReport() {
            const printContent = document.getElementById('report-content');
            if (!printContent) return;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>National Police Report</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin: 1rem 0; }
                            .kpi-card { padding: 1rem; border: 1px solid #ddd; text-align: center; }
                            .kpi-value { font-size: 2rem; font-weight: bold; color: #333; }
                            .kpi-label { color: #666; margin-top: 0.5rem; }
                            table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
                            th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                            th { background-color: #f2f2f2; }
                            .badge { padding: 2px 6px; border-radius: 3px; font-size: 0.8em; }
                            .status-success { background-color: #d4edda; color: #155724; }
                            .status-warning { background-color: #fff3cd; color: #856404; }
                            .status-danger { background-color: #f8d7da; color: #721c24; }
                            .status-info { background-color: #d1ecf1; color: #0c5460; }
                            .alert { padding: 1rem; border-radius: 4px; margin: 1rem 0; }
                            .alert-info { background-color: #d1ecf1; border: 1px solid #bee5eb; }
                            .alert-warning { background-color: #fff3cd; border: 1px solid #ffeaa7; }
                            h4, h5 { color: #333; margin-top: 2rem; margin-bottom: 1rem; }
                            @page { margin: 1in; }
                        </style>
                    </head>
                    <body>
                        <h1>Kenya Police Service - National Report</h1>
                        ${printContent.innerHTML}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        // Replace default print button action
        document.addEventListener('DOMContentLoaded', function() {
            const printBtn = document.querySelector('button[onclick="window.print()"]');
            if (printBtn) {
                printBtn.onclick = printReport;
            }
        });
        
        // Auto-select timeframe based on report type
        document.querySelectorAll('select[name="timeframe"]').forEach(select => {
            select.addEventListener('change', function() {
                // Auto-submit form when timeframe changes (optional)
                // this.form.submit();
            });
        });
    </script>
    
    <style>
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .kpi-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .kpi-value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        
        .kpi-label {
            color: #666;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        
        @media print {
            .btn, .card-header .btn, .no-print {
                display: none !important;
            }
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            .kpi-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>