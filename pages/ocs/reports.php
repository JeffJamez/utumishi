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

        $reportType = $_GET['type'] ?? '';
        $reportData = [];
        $reportGenerated = false;
        $error = '';

        if ($reportType) {
            try {
                switch ($reportType) {
                    case 'annual':
                        $year = $_GET['year'] ?? date('Y');
                        $reportData = $reportManager->generateAnnualReport($year, $stationId);
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
                    
                    <!-- Annual Report -->
                    <div class="card">
                        <div class="card-body text-center">

                             <h5>Comprehensive Annual Statistics and Analysis</h5>
                             <p class="text-muted">Detailed yearly case statistics and monthly trends</p>
                            <form method="GET" class="mb-3">
                                <input type="hidden" name="type" value="annual">
                                <div class="d-flex gap-2 justify-content-center">
                                    <select name="year" class="form-control" style="width: auto;">
                                        <?php 
                                        $currentYear = date('Y');
                                        $selectedYear = $_GET['year'] ?? $currentYear;
                                        for ($y = $currentYear; $y >= $currentYear - 2; $y--): 
                                        ?>
                                            <option value="<?php echo $y; ?>" <?php echo $y == $selectedYear ? 'selected' : ''; ?>>
                                                <?php echo $y; ?>
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
                                    <?php $selectedTimeframe = $_GET['timeframe'] ?? 30; ?>
                                    <option value="7" <?php echo $selectedTimeframe == 7 ? 'selected' : ''; ?>>Last 7 days</option>
                                    <option value="30" <?php echo $selectedTimeframe == 30 ? 'selected' : ''; ?>>Last 30 days</option>
                                    <option value="90" <?php echo $selectedTimeframe == 90 ? 'selected' : ''; ?>>Last 90 days</option>
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
                                    <?php $selectedTimeframe = $_GET['timeframe'] ?? 30; ?>
                                    <option value="30" <?php echo $selectedTimeframe == 30 ? 'selected' : ''; ?>>Last 30 days</option>
                                    <option value="60" <?php echo $selectedTimeframe == 60 ? 'selected' : ''; ?>>Last 60 days</option>
                                    <option value="90" <?php echo $selectedTimeframe == 90 ? 'selected' : ''; ?>>Last 90 days</option>
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
                
                 <?php if ($reportType === 'annual'): ?>
                      <div class="mb-4">
                          <h4>Comprehensive Annual Statistics and Analysis - <?php echo $reportData['period']['year_name']; ?></h4>
                         
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
                         
                          <?php if (!empty($reportData['monthly_trends'])): ?>
                          <h5>Monthly Trends</h5>
                          <div style="height: 300px; margin-bottom: 2rem;">
                              <canvas id="monthlyChart"></canvas>
                          </div>
                          <p class="text-muted mb-4" style="font-size: 0.85rem;">
                              <strong>What this means for you:</strong> This chart shows monthly case trends throughout the year. Identify seasonal patterns or changes in crime activity.
                          </p>
                          <?php else: ?>
                          <p class="text-muted">No monthly trend data available for this year.</p>
                          <?php endif; ?>
                         
                          <?php if (!empty($reportData['category_breakdown'])): ?>
                          <h5>Category Breakdown</h5>
                          <div style="height: 400px; margin-bottom: 2rem;">
                              <canvas id="categoryChart"></canvas>
                          </div>
                          <p class="text-muted mb-4" style="font-size: 0.85rem;">
                              <strong>What this means for you:</strong> This chart shows which crime types are most common throughout the year. Focus resources on the top categories to improve response times.
                          </p>
                          <?php else: ?>
                          <p class="text-muted">No category breakdown data available for this year.</p>
                          <?php endif; ?>
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
                          <div style="height: 400px; margin-bottom: 2rem;">
                              <canvas id="performanceChart"></canvas>
                          </div>
                          <p class="text-muted mb-4" style="font-size: 0.85rem;">
                              <strong>What this means for you:</strong> This chart shows resolution rates by crime category. Categories with lower rates may need additional resources or process improvements.
                          </p>
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
                          <div style="height: 400px; margin-bottom: 2rem;">
                              <canvas id="hotspotChart"></canvas>
                          </div>
                          <p class="text-muted mb-4" style="font-size: 0.85rem;">
                              <strong>What this means for you:</strong> This chart shows which locations have the highest incident counts. Focus patrols and preventive measures on these areas.
                          </p>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Render charts when report is generated
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($reportGenerated && $reportType === 'annual' && !empty($reportData['monthly_trends'])): ?>
            const monthlyData = <?php echo json_encode($reportData['monthly_trends']); ?>;
            if (monthlyData && monthlyData.length > 0) {
                const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const monthlyLabels = monthlyData.map(m => monthNames[m.month - 1]);
                const monthlyCases = monthlyData.map(m => parseInt(m.case_count));
                const monthlyResolved = monthlyData.map(m => parseInt(m.resolved_count));
            
                new Chart(document.getElementById('monthlyChart'), {
                    type: 'bar',
                    data: {
                        labels: monthlyLabels,
                        datasets: [
                            {
                                label: 'Total Cases',
                                data: monthlyCases,
                                backgroundColor: '#3b82f6',
                                borderColor: '#3b82f6',
                                borderWidth: 1
                            },
                            {
                                label: 'Resolved Cases',
                                data: monthlyResolved,
                                backgroundColor: '#22c55e',
                                borderColor: '#22c55e',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: true, position: 'top' },
                            tooltip: {
                                enabled: true
                            }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
            <?php endif; ?>
            
            <?php if ($reportGenerated && $reportType === 'annual' && !empty($reportData['category_breakdown'])): ?>
            const categoryData = <?php echo json_encode($reportData['category_breakdown']); ?>;
            if (categoryData && categoryData.length > 0) {
                const categoryLabels = categoryData.map(c => c.category);
                const categoryCases = categoryData.map(c => parseInt(c.case_count));
                const colors = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#ec4899', '#6b7280'];
                
                new Chart(document.getElementById('categoryChart'), {
                    type: 'bar',
                    data: {
                        labels: categoryLabels,
                        datasets: [{
                            label: 'Total Cases',
                            data: categoryCases,
                            backgroundColor: categoryLabels.map((_, i) => colors[i % colors.length]),
                            borderColor: categoryLabels.map((_, i) => colors[i % colors.length]),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: true,
                                callbacks: {
                                    label: function(context) {
                                        return 'Cases: ' + context.parsed.y;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
            <?php endif; ?>
            
            <?php if ($reportGenerated && $reportType === 'performance' && !empty($reportData['category_breakdown'])): ?>
            const perfData = <?php echo json_encode($reportData['category_breakdown']); ?>;
            const perfLabels = perfData.map(c => c.category);
            const perfRates = perfData.map(c => parseFloat(c.resolution_rate));
            
            const perfChartColors = {
                success: '#22c55e',
                warning: '#f59e0b',
                danger: '#ef4444'
            };
            
            new Chart(document.getElementById('performanceChart'), {
                type: 'bar',
                data: {
                    labels: perfLabels,
                    datasets: [{
                        label: 'Resolution Rate (%)',
                        data: perfRates,
                        backgroundColor: perfLabels.map((_, i) => 
                            perfRates[i] >= 80 ? perfChartColors.success : 
                            perfRates[i] >= 50 ? perfChartColors.warning : perfChartColors.danger
                        ),
                        borderColor: perfLabels.map((_, i) => 
                            perfRates[i] >= 80 ? perfChartColors.success : 
                            perfRates[i] >= 50 ? perfChartColors.warning : perfChartColors.danger
                        ),
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: true,
                            callbacks: {
                                label: function(context) {
                                    return 'Resolution Rate: ' + context.parsed.x.toFixed(1) + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
            
            <?php if ($reportGenerated && $reportType === 'crime_analysis' && !empty($reportData['hotspots'])): ?>
            const hotspotData = <?php echo json_encode($reportData['hotspots']); ?>;
            const hotspotLabels = hotspotData.map(h => h.location);
            const hotspotCases = hotspotData.map(h => parseInt(h.case_count));
            
            const hotspotChartColors = {
                danger: '#ef4444'
            };
            
            new Chart(document.getElementById('hotspotChart'), {
                type: 'bar',
                data: {
                    labels: hotspotLabels,
                    datasets: [{
                        label: 'Case Count',
                        data: hotspotCases,
                        backgroundColor: hotspotChartColors.danger,
                        borderColor: hotspotChartColors.danger,
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: true,
                            callbacks: {
                                label: function(context) {
                                    return 'Cases: ' + context.parsed.x;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { beginAtZero: true }
                    }
                }
            });
            <?php endif; ?>
        });

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