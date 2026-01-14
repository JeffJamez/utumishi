<?php
define('UTUMISHI_WEB_APP', true);

session_start();
require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';
require_once __DIR__ . '/../../includes/classes/CrimeAnalyzer.php';

requireRole(ROLE_OCS);

$currentUser = getCurrentUser();
$crimeAnalyzer = new CrimeAnalyzer();

$hotspots = [];
$densityData = [];
$recommendations = [];
$timeframe = $_GET['timeframe'] ?? 30;
$category = $_GET['category'] ?? '';
$error = '';

try {
    $stationId = $currentUser['station_id'];
    
    $stationInfo = getDB()->fetchOne(
        "SELECT county, constituency FROM stations WHERE id = :id",
        ['id' => $stationId]
    );
    
    $hotspots = $crimeAnalyzer->findHotspots($timeframe, 3);
    
    $stationHotspots = array_filter($hotspots, function($spot) use ($stationInfo) {
        return $spot['incident_location_county'] === $stationInfo['county'] ||
                $spot['incident_location_constituency'] === $stationInfo['constituency'];
    });
    
    $filters = [
        'timeframe' => $timeframe,
        'county' => $stationInfo['county']
    ];
    if ($category) {
        $filters['category'] = $category;
    }
    
    $densityData = $crimeAnalyzer->getCrimeDensityMap($filters);
    
    $recommendations = $crimeAnalyzer->recommendDeployment($stationId, $timeframe);
    
    $categories = getDB()->fetchAll(
        "SELECT DISTINCT category FROM cases WHERE station_id = :station_id ORDER BY category",
        ['station_id' => $stationId]
    );
    
} catch (Exception $e) {
    error_log("Crime Heatmap Error: " . $e->getMessage());
    $error = "Unable to load crime analysis data";
}

$pageTitle = "Crime Density & Analysis";
require_once __DIR__ . '/../../includes/layout/layout.php';
?>

        <main class="app-main">

            <div class="mb-4">
                <h2>Crime Density & Analysis</h2>
                <p class="text-muted">
                    Crime pattern analysis for <?php echo htmlspecialchars($stationInfo['constituency'] ?? ''); ?>, 
                    <?php echo htmlspecialchars($stationInfo['county'] ?? ''); ?>
                </p>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Analysis Filters</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="d-flex gap-3 align-items-end">
                        <div>
                            <label for="timeframe" class="form-label">Time Period</label>
                            <select name="timeframe" id="timeframe" class="form-control">
                                <option value="7" <?php echo $timeframe == 7 ? 'selected' : ''; ?>>Last 7 days</option>
                                <option value="30" <?php echo $timeframe == 30 ? 'selected' : ''; ?>>Last 30 days</option>
                                <option value="90" <?php echo $timeframe == 90 ? 'selected' : ''; ?>>Last 90 days</option>
                            </select>
                        </div>
                        <div>
                            <label for="category" class="form-label">Crime Category</label>
                            <select name="category" id="category" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                            <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="margin-top: 40px;">
                            <button type="submit" class="btn btn-primary">Update Analysis</button>
                        </div>
                    </form>
                </div>
            </div>


            <!-- Crime Density Map -->
            <div class="card">
                <div class="card-header">
                    <h3>Crime Density Analysis</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($densityData)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                     <tr>
                                         <th>Area</th>
                                         <th>Crime Category</th>
                                         <th>Case Count</th>
                                         <th>Monthly Rate</th>
                                         <th>Density Level</th>
                                     </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($densityData as $area): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($area['constituency']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($area['county']); ?></small>
                                            </td>
                                             <td><?php echo htmlspecialchars($area['category']); ?></td>
                                             <td><?php echo $area['case_count']; ?></td>
                                             <td><?php echo round($area['cases_per_month']); ?></td>
                                             <td>
                                                <span class="badge" style="background-color: <?php echo $area['color']; ?>; color: white;">
                                                    <?php echo ucfirst(str_replace('_', ' ', $area['density_level'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <p class="text-muted">No density data available for the selected filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>


            <!-- Hotspots Overview -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3> Crime Hotspots (Last <?php echo $timeframe; ?> days)</h3>
                    <span class="badge status-danger"><?php echo count($stationHotspots); ?> hotspots</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($stationHotspots)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                     <tr>
                                         <th>Area</th>
                                         <th>Local Area</th>
                                         <th>Crime Type</th>
                                        <th>Total Cases</th>
                                        <th>Cases/Month</th>
                                        <th>% of Total</th>
                                        <th>Severity</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                     <?php foreach ($stationHotspots as $hotspot): ?>
                                         <?php $caseId = $crimeAnalyzer->getHotspotCaseId($hotspot['incident_location_county'], $hotspot['incident_location_constituency'], $hotspot['category']); ?>
                                         <tr>
                                              <td>
                                                   <strong><?php echo htmlspecialchars($hotspot['incident_location_constituency']); ?></strong><br>
                                                   <small class="text-muted"><?php echo htmlspecialchars($hotspot['incident_location_county']); ?></small>
                                              </td>
                                              <td><?php echo htmlspecialchars($hotspot['incident_local_area'] ?? 'General'); ?></td>
                                              <td><?php echo htmlspecialchars($hotspot['category']); ?></td>
                                             <td><?php echo $hotspot['case_count']; ?></td>
                                             <td><?php echo round($hotspot['cases_per_month'], 1); ?></td>
                                             <td><?php echo $hotspot['percentage_of_total']; ?>%</td>
                                             <td>
                                                 <span class="badge status-<?php echo $hotspot['severity'] === 'critical' ? 'danger' : ($hotspot['severity'] === 'high' ? 'warning' : 'info'); ?>">
                                                     <?php echo ucfirst($hotspot['severity']); ?>
                                                 </span>
                                             </td>
                                             <td>
                                                 <?php if ($caseId): ?>
                                                     <a class="btn btn-sm btn-outline btn-primary" href="case_details.php?id=<?php echo $caseId; ?>">
                                                         Details
                                                     </a>
                                                 <?php else: ?>
                                                     <button class="btn btn-sm btn-outline btn-secondary" disabled>No Cases</button>
                                                 <?php endif; ?>
                                             </td>
                                         </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <h4>No Crime Hotspots Detected</h4>
                            <p class="text-muted">No areas show significantly elevated crime patterns for the selected period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Deployment Recommendations -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Resource Deployment Recommendations</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($recommendations)): ?>
                        <?php foreach ($recommendations as $rec): ?>
                            <div class="alert alert-<?php echo $rec['severity'] === 'high' ? 'warning' : 'info'; ?> mb-3">
                                <div class="d-flex justify-between items-start">
                                    <div style="flex: 1;">
                                        <strong><?php echo htmlspecialchars($rec['area']); ?></strong>
                                        <span class="badge badge-<?php echo $rec['severity'] === 'high' ? 'warning' : 'info'; ?> ml-2">
                                            Priority: <?php echo $rec['priority']; ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($rec['crime_type']); ?> • 
                                            <?php echo $rec['cases_per_month']; ?> cases/month
                                        </small>
                                        <div class="mt-2">
                                            <strong>Recommended Action:</strong><br>
                                            <?php echo htmlspecialchars($rec['action']); ?>
                                        </div>
                                        <?php if (!empty($rec['peak_hours'])): ?>
                                            <div class="mt-1">
                                                <strong>Peak Hours:</strong> <?php echo htmlspecialchars($rec['peak_hours']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <h4>No Immediate Deployment Changes Needed</h4>
                            <p class="text-muted">Current resource allocation appears optimal for detected crime patterns.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
    
     <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
     </script>
</body>
</html>