<?php
define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';
require_once __DIR__ . '/../../includes/classes/ReportManager.php';

requireRole(ROLE_OCS);

$currentUser = getCurrentUser();
$stationId = $currentUser['station_id'];
$reportManager = new ReportManager();

// Handle report generation
$reportType = $_GET['type'] ?? '';
$reportData = [];
$reportGenerated = false;
$error = '';

if ($reportType) {
    try {
        switch ($reportType) {
            case 'monthly':
                $year = $_GET['year'] ?? date('Y');
                $month = $_GET['month'] ?? date('m');
                $reportData = $reportManager->generateMonthlyReport($year, $month, $stationId);
                break;
                
            case 'performance':
                $timeframe = $_GET['timeframe'] ?? 30;
                $reportData = $reportManager->generatePerformanceReport($stationId, $timeframe);
                break;
                
            case 'crime_analysis':
                $timeframe = $_GET['timeframe'] ?? 30;
                $reportData = $reportManager->generateCrimeAnalysisReport($stationId, $timeframe);
                break;
                

        }
        $reportGenerated = true;
    } catch (Exception $e) {
        error_log("Report Generation Error: " . $e->getMessage());
        $error = 'Failed to generate report: ' . $e->getMessage();
    }
}

$pageTitle = "Station Reports";
require_once __DIR__ . '/../../includes/layout/layout.php';
?>

    <main class="app-main">
        <div class="mb-4">
            <h2>Station Reports</h1>
            <p class="text-muted">Generate and view detailed reports for your station</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <h3>Generate Report</h3>
            </div>
            <div class="card-body">
                <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    
                    <!-- Monthly Report -->
                    <div class="card">
                        <div class="card-body text-center">

                             <h5>Comprehensive Monthly Statistics and Analysis</h5>
                             <p class="text-muted">Detailed monthly case statistics and trends</p>
                            <form method="GET" class="mb-3">
                                <input type="hidden" name="type" value="monthly">
                                <div class="d-flex gap-2">
                                    <select name="year" class="form-control">
                                        <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <select name="month" class="form-control">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo sprintf('%02d', $m); ?>" <?php echo $m == date('m') ? 'selected' : ''; ?>>
                                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary mt-2">Generate</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Performance Report -->
                    <div class="card">
                        <div class="card-body text-center">

                             <h5>Station Performance Metrics and Trends</h5>
                             <p class="text-muted">Performance analysis and efficiency metrics</p>
                            <form method="GET" class="mb-3">
                                <input type="hidden" name="type" value="performance">
                                <select name="timeframe" class="form-control mb-2">
                                    <option value="7">Last 7 days</option>
                                    <option value="30" selected>Last 30 days</option>
                                    <option value="90">Last 90 days</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Generate</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Crime Analysis Report -->
                    <div class="card">
                        <div class="card-body text-center">

                             <h5>Crime Patterns and Hotspot Analysis</h5>
                             <p class="text-muted">Crime trends and hotspot identification</p>
                            <form method="GET" class="mb-3">
                                <input type="hidden" name="type" value="crime_analysis">
                                <select name="timeframe" class="form-control mb-2">
                                    <option value="30" selected>Last 30 days</option>
                                    <option value="60">Last 60 days</option>
                                    <option value="90">Last 90 days</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Generate</button>
                            </form>
                        </div>
                    </div>
                    

                </div>
            </div>
        </div>

        <?php if ($reportGenerated && !empty($reportData)): ?>
        <div class="card">
            <div class="card-header">
                <h3><?php echo htmlspecialchars($reportData['type']); ?></h3>
                <div>
                    <button onclick="window.print()" class="btn btn-sm btn-outline btn-primary">Print Report</button>
                    <button onclick="exportReport()" class="btn btn-sm btn-outline btn-secondary">Export</button>
                </div>
            </div>
            <div class="card-body" id="report-content">
                
                 <?php if ($reportType === 'monthly'): ?>
                     <div class="mb-4">
                         <h4>Comprehensive Monthly Statistics and Analysis - <?php echo $reportData['period']['month_name'] . ' ' . $reportData['period']['year']; ?></h4>
                        
                        <div class="kpi-grid mb-4">
                            <div class="kpi-card">
                                <div class="kpi-value"><?php echo $reportData['overall_stats']['total_cases'] ?? 0; ?></div>
                                <div class="kpi-label">Total Cases</div>
                            </div>
                            <div class="kpi-card">
                                <div class="kpi-value"><?php echo $reportData['overall_stats']['resolved_cases'] ?? 0; ?></div>
                                <div class="kpi-label">Resolved Cases</div>
                            </div>
                            <div class="kpi-card">
                                <div class="kpi-value"><?php echo $reportData['overall_stats']['resolution_rate'] ?? 0; ?>%</div>
                                <div class="kpi-label">Resolution Rate</div>
                            </div>
                            <div class="kpi-card">
                                 <div class="kpi-value"><?php echo round($reportData['overall_stats']['avg_resolution_time'] ?? 0, 1); ?>h</div>
                                <div class="kpi-label">Avg Resolution Time</div>
                            </div>
                        </div>
                        
                         <h5>Category Breakdown</h5>
                         <table class="table">
                             <thead>
                                 <tr>
                                     <th>Category</th>
                                     <th>Cases</th>
                                     <th>Resolved</th>
                                     <th>Avg Resolution Time (weeks)</th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <?php foreach ($reportData['category_breakdown'] as $cat): ?>
                                     <tr>
                                         <td><?php echo htmlspecialchars($cat['category']); ?></td>
                                         <td><?php echo $cat['case_count']; ?></td>
                                         <td><?php echo $cat['resolved_count']; ?></td>
                                         <td><?php echo round(($cat['avg_resolution_time'] ?? 0) / 168, 1); ?> weeks</td>
                                     </tr>
                                 <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                 <?php elseif ($reportType === 'performance'): ?>
                     <div class="mb-4">
                         <h4>Station Performance Metrics and Trends - <?php echo $reportData['period']; ?></h4>

                         <div class="kpi-grid mb-4">
                             <div class="kpi-card">
                                 <div class="kpi-value"><?php echo $reportData['station_stats']['total_cases'] ?? 0; ?></div>
                                 <div class="kpi-label">Total Cases</div>
                             </div>
                             <div class="kpi-card">
                                 <div class="kpi-value"><?php echo $reportData['station_stats']['resolution_rate'] ?? 0; ?>%</div>
                                 <div class="kpi-label">Resolution Rate</div>
                             </div>
                             <div class="kpi-card">
                                 <div class="kpi-value"><?php echo round($reportData['station_stats']['avg_resolution_time'] ?? 0, 1); ?>h</div>
                                 <div class="kpi-label">Avg Resolution Time</div>
                             </div>
                         </div>

                         <h5>Performance by Category</h5>
                         <table class="table">
                             <thead>
                                 <tr>
                                     <th>Category</th>
                                     <th>Cases</th>
                                     <th>Resolved</th>
                                     <th>Avg Resolution Time (weeks)</th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <?php foreach ($reportData['category_breakdown'] as $cat): ?>
                                     <tr>
                                         <td><?php echo htmlspecialchars($cat['category']); ?></td>
                                         <td><?php echo $cat['case_count']; ?></td>
                                         <td><?php echo $cat['resolved_count']; ?></td>
                                         <td><?php echo round(($cat['avg_resolution_time'] ?? 0) / 168, 1); ?> weeks</td>
                                     </tr>
                                 <?php endforeach; ?>
                             </tbody>
                         </table>
                     </div>

                 <?php elseif ($reportType === 'crime_analysis'): ?>
                     <div class="mb-4">
                         <h4>Crime Patterns and Hotspot Analysis - <?php echo $reportData['period']; ?></h4>

                         <div class="kpi-grid mb-4">
                             <div class="kpi-card">
                                 <div class="kpi-value"><?php echo $reportData['total_cases'] ?? 0; ?></div>
                                 <div class="kpi-label">Total Cases</div>
                             </div>
                             <div class="kpi-card">
                                 <div class="kpi-value"><?php echo count($reportData['hotspots'] ?? []); ?></div>
                                 <div class="kpi-label">Hotspots Identified</div>
                             </div>
                             <div class="kpi-card">
                                 <div class="kpi-value"><?php echo $reportData['most_common_category'] ?? 'N/A'; ?></div>
                                 <div class="kpi-label">Most Common Crime</div>
                             </div>
                         </div>

                         <h5>Hotspot Locations</h5>
                         <table class="table">
                             <thead>
                                 <tr>
                                     <th>Location</th>
                                     <th>Case Count</th>
                                     <th>Primary Crime</th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <?php foreach ($reportData['hotspots'] ?? [] as $hotspot): ?>
                                     <tr>
                                         <td><?php echo htmlspecialchars($hotspot['location']); ?></td>
                                         <td><?php echo $hotspot['case_count']; ?></td>
                                         <td><?php echo htmlspecialchars($hotspot['category']); ?></td>
                                     </tr>
                                 <?php endforeach; ?>
                             </tbody>
                         </table>
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
        function exportReport() {
            const content = document.getElementById('report-content');
            if (!content) return;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Station Report</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin: 1rem 0; }
                            .kpi-card { padding: 1rem; border: 1px solid #ddd; text-align: center; }
                            .kpi-value { font-size: 2rem; font-weight: bold; }
                            .kpi-label { color: #666; }
                            table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
                            th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                            th { background-color: #f2f2f2; }
                            .badge { padding: 2px 6px; border-radius: 3px; font-size: 0.8em; }
                            .status-success { background-color: #d4edda; color: #155724; }
                            .status-warning { background-color: #fff3cd; color: #856404; }
                            .status-danger { background-color: #f8d7da; color: #721c24; }
                        </style>
                    </head>
                    <body>
                        ${content.innerHTML}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
    </script>
    
    <style>
        @media print {
            .btn, .card-header .btn, .no-print {
                display: none !important;
            }
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</body>
</html>