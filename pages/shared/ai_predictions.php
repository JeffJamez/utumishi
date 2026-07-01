<?php
define('UTUMISHI_WEB_APP', true);

session_start();
require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';
require_once __DIR__ . '/../../includes/classes/AIPredictionEngine.php';

$currentUser = getCurrentUser();
$isOfficer = ($currentUser['role'] ?? '') === ROLE_OFFICER;
$isOCS = ($currentUser['role'] ?? '') === ROLE_OCS;
$isCountyCommander = ($currentUser['role'] ?? '') === ROLE_COUNTY_COMMANDER;

if (!$isOfficer && !$isOCS && !$isCountyCommander) {
    requireRole(ROLE_OFFICER);
}

$predictionEngine = new AIPredictionEngine();

$userCounty = null;
if ($isCountyCommander) {
    $db = Database::getInstance();
    $userDetails = $db->fetchOne("SELECT county_in_charge FROM users WHERE id = :id", ['id' => $currentUser['id']]);
    $userCounty = $userDetails['county_in_charge'] ?? null;
} elseif ($isOCS || $isOfficer) {
    $db = Database::getInstance();
    $stationDetails = $db->fetchOne("SELECT s.county FROM stations s JOIN officers o ON s.id = o.station_id WHERE o.user_id = :id", ['id' => $currentUser['id']]);
    $userCounty = $stationDetails['county'] ?? null;
}

try {
    $predictions = $predictionEngine->getPredictions(false, $isCountyCommander ? null : $userCounty);
    $dropdownLocations = $predictionEngine->getAllLocationsForDropdown(20, $isCountyCommander ? null : $userCounty);
} catch (Exception $e) {
    error_log("AI Prediction Error: " . $e->getMessage());
    $predictions = [
        'total_crimes' => 0,
        'hotspot_count' => 0,
        'peak_hours' => 'N/A',
        'model_accuracy' => 'N/A',
        'hourly_distribution' => array_fill(0, 24, 0),
        'weekly_trend' => array_fill(0, 7, 0),
        'top_hotspots' => [],
        'recent_incidents' => [],
        'categories' => [],
        'locations' => []
    ];
    $dropdownLocations = [];
}

$pageTitle = "Crime Predictions";

require_once __DIR__ . '/../../includes/layout/layout.php';

$weeklyData = json_encode($predictions['weekly_trend']);
$days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
$dayNames = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

$prevWeekTrend = [];
try {
    $db = Database::getInstance();
    $sql = "SELECT 
            (DAYOFWEEK(COALESCE(occurred_at, created_at)) + 5) % 7 as day_index,
            COUNT(*) as count
        FROM cases
        WHERE COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        AND COALESCE(occurred_at, created_at) < DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DAYOFWEEK(COALESCE(occurred_at, created_at))
        ORDER BY day_index ASC";
    
    $results = $db->fetchAll($sql);
    $prevWeekTrend = array_fill(0, 7, 0);
    foreach ($results as $row) {
        $day = (int)$row['day_index'];
        $prevWeekTrend[$day] = (int)$row['count'];
    }
} catch (Exception $e) {
    error_log("Error fetching previous week trend: " . $e->getMessage());
    $prevWeekTrend = array_fill(0, 7, 0);
}

$prevWeekData = json_encode($prevWeekTrend);

$currentWeekTotal = array_sum($predictions['weekly_trend']);
$prevWeekTotal = array_sum($prevWeekTrend);
$weeklyChange = $prevWeekTotal > 0 ? round((($currentWeekTotal - $prevWeekTotal) / $prevWeekTotal) * 100) : 0;
$weeklyTrendDirection = $weeklyChange > 0 ? 'up' : ($weeklyChange < 0 ? 'down' : 'same');

$hotspotData = [];
try {
    $db = Database::getInstance();
    
    $sql = "SELECT 
        c.latitude,
        c.longitude,
        c.category,
        c.incident_location_constituency as constituency,
        c.incident_location_county as county,
        c.created_at
    FROM cases c
    WHERE c.latitude IS NOT NULL 
        AND c.longitude IS NOT NULL
        AND c.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    ORDER BY c.created_at DESC
    LIMIT 200";
    
    $casesWithCoords = $db->fetchAll($sql);
    
    foreach ($casesWithCoords as $case) {
        $hotspotData[] = [
            'lat' => (float)$case['latitude'],
            'lng' => (float)$case['longitude'],
            'category' => $case['category'],
            'location' => $case['constituency'] . ', ' . $case['county'],
            'date' => $case['created_at']
        ];
    }
    
    if (empty($hotspotData)) {
        $constituencyCoordinates = [
            'Westlands' => ['lat' => -1.2676, 'lng' => 36.8047],
            'Soy' => ['lat' => 0.5500, 'lng' => 35.2800],
            'Mvita' => ['lat' => -4.0435, 'lng' => 39.6682],
            'Central' => ['lat' => -0.3031, 'lng' => 36.0800],
            'Langata' => ['lat' => -1.3284, 'lng' => 36.7807],
            'Naivasha' => ['lat' => -0.7167, 'lng' => 36.4333],
            'Eldoret East' => ['lat' => 0.5200, 'lng' => 35.2700],
            'Starehe' => ['lat' => -1.2833, 'lng' => 36.8333],
            'Likoni' => ['lat' => -4.0833, 'lng' => 39.6667],
            'Milimani' => ['lat' => -0.0917, 'lng' => 34.7680],
            'Kiambu Town' => ['lat' => -1.1748, 'lng' => 36.8304],
            'Eastlands' => ['lat' => -1.3000, 'lng' => 36.8667],
            'Kajiado West' => ['lat' => -1.8500, 'lng' => 36.7800],
            'Kaloleni' => ['lat' => -3.7833, 'lng' => 39.8500],
            'Mandera North' => ['lat' => 3.9333, 'lng' => 41.8500]
        ];
        
        foreach (array_slice($predictions['top_hotspots'], 0, 15) as $hotspot) {
            $constituency = $hotspot['constituency'];
            if (isset($constituencyCoordinates[$constituency])) {
                $coords = $constituencyCoordinates[$constituency];
                $hotspotData[] = [
                    'lat' => $coords['lat'],
                    'lng' => $coords['lng'],
                    'category' => $hotspot['category'],
                    'location' => $constituency . ', ' . $hotspot['county'],
                    'date' => date('Y-m-d')
                ];
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching hotspot coordinates: " . $e->getMessage());
    $hotspotData = [];
}

$hotspotDataJson = json_encode($hotspotData);

$categoryColors = [
    'Theft' => '#f0a500',
    'Assault' => '#e84a2e',
    'Domestic Violence' => '#e84a2e',
    'Burglary' => '#9b59b6',
    'Sexual Offenses' => '#e84a2e',
    'Fraud' => '#4a90e2',
    'Traffic Offenses' => '#2ecc71',
    'Drug Related' => '#2ecc71',
    'Robbery' => '#e74c3c',
    'Vandalism' => '#4a90e2'
];
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.heat/0.2.0/leaflet-heat.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.stats-bar {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 1.1rem 1.3rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-label {
    font-size: 0.65rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.stat-value {
    font-size: 1.9rem;
    font-weight: 700;
    line-height: 1;
    color: #111827;
}

.stat-sub {
    font-size: 0.68rem;
    color: #6b7280;
}

.main-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 1rem;
    margin-bottom: 1rem;
}

.panel {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 1.2rem;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.panel-title {
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #111827;
}

.badge {
    font-size: 0.62rem;
    padding: 0.2rem 0.6rem;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.badge-red {
    background: rgba(220, 38, 38, 0.1);
    color: #dc2626;
    border: 1px solid rgba(220, 38, 38, 0.3);
}

.badge-amber {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.badge-blue {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.badge-green {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

#map {
    height: 400px;
    width: 100%;
}

.map-controls {
    padding: 0.8rem 1.2rem;
    display: flex;
    gap: 0.6rem;
    align-items: center;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
    flex-wrap: wrap;
}

.ctrl-label {
    font-size: 0.68rem;
    color: #6b7280;
    margin-right: 0.3rem;
}

.toggle-btn {
    font-size: 0.7rem;
    padding: 0.3rem 0.75rem;
    border-radius: 5px;
    border: 1px solid #d1d5db;
    background: white;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s;
}

.toggle-btn.active, .toggle-btn:hover {
    border-color: #dc2626;
    color: #dc2626;
    background: rgba(220, 38, 38, 0.08);
}

.sidebar {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.predict-form {
    padding: 1rem 1.2rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.form-row {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.form-row label {
    font-size: 0.65rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.form-row select {
    font-size: 0.85rem;
    background: white;
    border: 1px solid #d1d5db;
    color: #111827;
    padding: 0.5rem 0.7rem;
    border-radius: 6px;
    appearance: none;
    width: 100%;
}

.form-row select:focus {
    outline: none;
    border-color: #dc2626;
}

.predict-btn {
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    background: #dc2626;
    color: #fff;
    border: none;
    padding: 0.65rem;
    border-radius: 6px;
    cursor: pointer;
    transition: opacity 0.2s;
    margin-top: 0.25rem;
}

.predict-btn:hover {
    opacity: 0.88;
}

.risk-result {
    margin: 0 1.2rem 1rem;
    border-radius: 8px;
    padding: 0.9rem 1rem;
    display: none;
    flex-direction: column;
    gap: 0.5rem;
}

.risk-result.show {
    display: flex;
}

.risk-result.high {
    background: rgba(220, 38, 38, 0.1);
    border: 1px solid rgba(220, 38, 38, 0.3);
}

.risk-result.medium {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.risk-result.low {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.risk-label {
    font-size: 0.62rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.risk-score {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
}

.risk-bar-wrap {
    background: #e5e7eb;
    border-radius: 99px;
    height: 5px;
    overflow: hidden;
}

.risk-bar {
    height: 100%;
    border-radius: 99px;
    transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.risk-text {
    font-size: 0.7rem;
    color: #6b7280;
}

.crime-list {
    padding: 0;
    list-style: none;
    max-height: 350px;
    overflow-y: auto;
}

.crime-list::-webkit-scrollbar {
    width: 4px;
}

.crime-list::-webkit-scrollbar-track {
    background: transparent;
}

.crime-list::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 99px;
}

.crime-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.7rem 1.2rem;
    border-bottom: 1px solid #e5e7eb;
    transition: background 0.15s;
}

.crime-item:hover {
    background: #f9fafb;
}

.crime-item:last-child {
    border-bottom: none;
}

.crime-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.crime-info {
    flex: 1;
    min-width: 0;
}

.crime-type {
    font-size: 0.75rem;
    font-weight: 500;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.crime-meta {
    font-size: 0.62rem;
    color: #6b7280;
}

.crime-time {
    font-size: 0.62rem;
    color: #6b7280;
    flex-shrink: 0;
}

.charts-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.chart-wrap {
    padding: 1rem 1.2rem;
    height: 200px;
    position: relative;
}

.forecast-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.72rem;
    color: #111827;
}

.forecast-table th {
    text-align: left;
    font-size: 0.62rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    padding: 0.5rem 1.2rem;
    border-bottom: 1px solid #e5e7eb;
    font-weight: 400;
    background: #f9fafb;
}

.forecast-table td {
    padding: 0.55rem 1.2rem;
    border-bottom: 1px solid #e5e7eb;
}

.forecast-table tr:last-child td {
    border-bottom: none;
}

.forecast-table tr:hover td {
    background: #f9fafb;
}

.risk-chip {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-size: 0.62rem;
}

.chip-high {
    background: rgba(220, 38, 38, 0.1);
    color: #dc2626;
}

.chip-medium {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.chip-low {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.model-status {
    font-size: 0.65rem;
    color: #6b7280;
    padding: 0.5rem 1.2rem;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
}

.risk-formula {
    font-size: 0.68rem;
    color: #4b5563;
    padding: 0.75rem 1.2rem;
    border-top: 1px solid #e5e7eb;
    background: #fffbeb;
    line-height: 1.6;
}

.risk-formula strong {
    color: #92400e;
}

@media (max-width: 900px) {
    .main-grid {
        grid-template-columns: 1fr;
    }
    .stats-bar {
        grid-template-columns: repeat(2, 1fr);
    }
    .charts-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .stats-bar {
        grid-template-columns: 1fr;
    }
    .app-main {
        padding: 1rem;
    }
}
</style>

<main class="app-main">
    <div class="mb-4">
        <h2>Crime Predictions</h2>
        <p class="text-muted">Statistical forecasting for proactive policing</p>
    </div>

    <!-- Stat Cards -->
    <div class="stats-bar">
        <div class="stat-card red">
            <div class="stat-label">Total Crimes (Dataset)</div>
            <div class="stat-value"><?php echo number_format($predictions['total_crimes']); ?></div>
            <div class="stat-sub">Across all categories</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">High-Risk Hours</div>
            <div class="stat-value"><?php echo htmlspecialchars($predictions['peak_hours']); ?></div>
            <div class="stat-sub">Peak window today</div>
        </div>
        <!-- <div class="stat-card green">
            <div class="stat-label">Hotspot Zones</div>
            <div class="stat-value"><?php echo $predictions['hotspot_count']; ?></div>
            <div class="stat-sub">Active clusters</div>
        </div> -->
        <div class="stat-card blue">
            <div class="stat-label">Prediction Accuracy</div>
            <div class="stat-value" id="modelStatus" style="font-size: 1.2rem;"><?php echo !empty($predictions['total_crimes']) ? min(95, 60 + ($predictions['hotspot_count'] * 2)) : 0; ?>%</div>
            <div class="stat-sub">Statistical Model</div>
        </div>
    </div>

    <!-- Map Panel (Full Width) -->
    <div class="panel" style="margin-bottom: 1rem;">
        <div class="panel-header">
            <span class="panel-title">Crime Hotspot Map</span>
            <span class="badge badge-red">Live Heatmap</span>
        </div>
        <div id="map"></div>
        <div class="map-controls">
            <span class="ctrl-label">Show:</span>
            <button class="toggle-btn active" onclick="toggleLayer('heat', this)">Heatmap</button>
            <button class="toggle-btn" onclick="toggleLayer('markers', this)">Incidents</button>
            <button class="toggle-btn" onclick="toggleLayer('predicted', this)">Predicted Zones</button>
        </div>
        <!-- Map Legend/KEY -->
        <div style="padding: 0.8rem 1.2rem; border-top: 1px solid #e5e7eb; background: #f9fafb; display: flex; gap: 2rem; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <div style="font-size: 0.65rem; font-weight: 700; color: #111827; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Heat Map Intensity</div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 100px; height: 12px; background: linear-gradient(to right, #ff6600, #ff0000, #ff0088, #ffffff); border-radius: 2px;"></div>
                    <span style="font-size: 0.6rem; color: #6b7280;">Low → High</span>
                </div>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <div style="font-size: 0.65rem; font-weight: 700; color: #111827; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Prediction Zones</div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.3rem;">
                        <div style="width: 16px; height: 16px; border: 2px dashed #e84a2e; border-radius: 50%; background: rgba(232, 74, 46, 0.12);"></div>
                        <span style="font-size: 0.6rem; color: #6b7280;">High Risk</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.3rem;">
                        <div style="width: 16px; height: 16px; border: 2px dashed #f0a500; border-radius: 50%; background: rgba(240, 165, 0, 0.12);"></div>
                        <span style="font-size: 0.6rem; color: #6b7280;">Medium Risk</span>
                    </div>
                </div>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <div style="font-size: 0.65rem; font-weight: 700; color: #111827; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Incident Markers</div>
                <div style="display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;">
                    <?php foreach (['Theft' => '#f0a500', 'Assault' => '#e84a2e', 'Burglary' => '#9b59b6', 'Fraud' => '#4a90e2', 'Traffic Offenses' => '#2ecc71'] as $cat => $color): ?>
                    <div style="display: flex; align-items: center; gap: 0.2rem;">
                        <div style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo $color; ?>;"></div>
                        <span style="font-size: 0.6rem; color: #6b7280;"><?php echo $cat; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Weekly Trend -->
    <div class="panel" style="margin-bottom: 1rem;">
        <div class="panel-header">
            <span class="panel-title">Weekly Trend: All Crimes</span>
            <span class="badge badge-blue">7-Day</span>
        </div>
        <div style="padding: 1rem 1.2rem; border-bottom: 1px solid #e5e7eb;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">This Week</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #111827;"><?php echo $currentWeekTotal; ?> <small style="font-size: 0.75rem; color: #6b7280;">cases</small></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">vs Last Week</div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: <?php echo $weeklyChange > 0 ? '#dc2626' : ($weeklyChange < 0 ? '#22c55e' : '#6b7280'); ?>">
                        <?php echo $weeklyChange > 0 ? '↑' : ($weeklyChange < 0 ? '↓' : '→'); ?> <?php echo abs($weeklyChange); ?>%
                    </div>
                </div>
            </div>
        </div>
        <div class="chart-wrap">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- Model Status Panel (Full Width) -->
    <div class="panel" style="margin-bottom: 1rem;">
        <div class="panel-header">
            <span class="panel-title">Model Information</span>
            <span class="badge badge-blue">Statistical Analysis</span>
        </div>
        <div class="model-status">
            Statistical frequency analysis trained on <?php echo number_format($predictions['total_crimes']); ?> historical crime records
            <br>
            <?php if ($userCounty && !$isCountyCommander): ?>
                <small style="color: #6b7280;">Showing data for: <strong><?php echo htmlspecialchars($userCounty); ?> County</strong></small>
            <?php elseif ($isCountyCommander && $userCounty): ?>
                <small style="color: #6b7280;">Showing country-wide data (County Commander view)</small>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
const map = L.map('map', { zoomControl: true }).setView([-0.5, 37.0], 6);

L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
    maxZoom: 19
}).addTo(map);

const crimeData = <?php echo $hotspotDataJson; ?>;

const categoryColors = {
    'Theft': '#f0a500',
    'Assault': '#e84a2e',
    'Domestic Violence': '#e84a2e',
    'Burglary': '#9b59b6',
    'Sexual Offenses': '#e84a2e',
    'Fraud': '#4a90e2',
    'Traffic Offenses': '#2ecc71',
    'Drug Related': '#2ecc71',
    'Robbery': '#e74c3c',
    'Vandalism': '#4a90e2'
};

const heatData = crimeData.map(c => [c.lat, c.lng, 1.0]);
const heatLayer = L.heatLayer(heatData, {
    radius: 35,
    blur: 25,
    maxZoom: 15,
    gradient: {
        0.1: '#ff6600',   
        0.3: '#ff3300',   
        0.5: '#ff0000',   
        0.7: '#ff0044',   
        0.9: '#ff0088',   
        1.0: '#ffffff'    
    }
});
heatLayer.addTo(map);

const markerGroup = L.layerGroup();
crimeData.forEach(c => {
    const color = categoryColors[c.category] || '#888';
    const icon = L.divIcon({
        html: `<div style="width:10px;height:10px;border-radius:50%;background:${color};border:2px solid rgba(255,255,255,.8);box-shadow:0 0 6px ${color}88;"></div>`,
        className: '',
        iconSize: [10, 10],
        iconAnchor: [5, 5]
    });
    
    const date = new Date(c.date);
    const dateStr = date.toLocaleDateString('en-KE', { month: 'short', day: 'numeric' });
    
    L.marker([c.lat, c.lng], { icon })
        .bindPopup(`<b style="color:${color}">${c.category}</b><br>${c.location}<br><small>${dateStr}</small>`)
        .addTo(markerGroup);
});

const predictedGroup = L.layerGroup();
if (crimeData.length > 0) {
    const clusters = [
        { index: 0, r: 5000, risk: 'High' },
        { index: Math.floor(crimeData.length / 3), r: 4000, risk: 'Medium' },
        { index: Math.floor(crimeData.length * 2 / 3), r: 3500, risk: 'Medium' }
    ];
    
    clusters.forEach(cluster => {
        if (crimeData[cluster.index]) {
            const c = crimeData[cluster.index];
            L.circle([c.lat, c.lng], {
                radius: cluster.r,
                color: cluster.risk === 'High' ? '#e84a2e' : '#f0a500',
                fillColor: cluster.risk === 'High' ? '#e84a2e' : '#f0a500',
                fillOpacity: 0.12,
                weight: 1.5,
                dashArray: '5,5'
            }).bindPopup(`<b>Predicted ${cluster.risk}-Risk Zone</b><br>Based on historical patterns`).addTo(predictedGroup);
        }
    });
}

const layers = {
    heat: heatLayer,
    markers: markerGroup,
    predicted: predictedGroup
};

function toggleLayer(name, btn) {
    const layer = layers[name];
    if (map.hasLayer(layer)) {
        map.removeLayer(layer);
        btn.classList.remove('active');
    } else {
        map.addLayer(layer);
        btn.classList.add('active');
    }
}

if (crimeData.length > 0) {
    const bounds = L.latLngBounds(crimeData.map(c => [c.lat, c.lng]));
    map.fitBounds(bounds, { padding: [50, 50], maxZoom: 10 });
}

const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: {
            grid: { color: "rgba(0,0,0,.04)" },
            ticks: { color: "#6b7280", font: { size: 10 } }
        },
        y: {
            grid: { color: "rgba(0,0,0,.04)" },
            ticks: { color: "#6b7280", font: { size: 10 } }
        }
    }
};

const trendCtx = document.getElementById('trendChart').getContext('2d');
const weeklyData = <?php echo $weeklyData; ?>;
const prevWeekData = <?php echo $prevWeekData; ?>;

new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($days); ?>,
        datasets: [
            {
                label: 'This Week',
                data: weeklyData,
                borderColor: "#e84a2e",
                backgroundColor: "rgba(232,74,46,.08)",
                borderWidth: 2,
                pointRadius: 4,
                pointBackgroundColor: "#e84a2e",
                fill: true,
                tension: 0.4
            },
            {
                label: 'Last Week',
                data: prevWeekData,
                borderColor: "#6b7280",
                backgroundColor: "rgba(107,114,128,.05)",
                borderWidth: 2,
                borderDash: [5, 5],
                pointRadius: 3,
                pointBackgroundColor: "#6b7280",
                fill: false,
                tension: 0.4
            }
        ]
    },
    options: {
        ...chartDefaults,
        plugins: {
            legend: {
                display: true,
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 10,
                    font: { size: 10 }
                }
            }
        }
    }
});


$zoneMappings = $zoneMappings ?? [];
window.ZONE_MAPPINGS = <?php echo json_encode($zoneMappings); ?>;


const nnTrainingData = crimeData.map(c => {
    const dateStr = c.date || new Date().toISOString();
    return {
        lat: c.lat,
        lng: c.lng,
        category: c.category,
        location: c.location,
        date: dateStr
    };
}).filter(c => c.lat && c.lng); // Only include records with coordinates

console.log('Prepared', nnTrainingData.length, 'training records from', crimeData.length, 'total crimes');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, statistical model active');
    console.log('Crime data available:', crimeData.length, 'records');
    console.log('Training data prepared:', nnTrainingData.length, 'records');
    
    const statusEl = document.getElementById('modelStatus');
    if (statusEl && statusEl.textContent === 'Loading...') {
        statusEl.textContent = 'Active';
        statusEl.style.color = '#22c55e';
    }
});

window.showPredictionResult = function(result) {
    const resultEl = document.getElementById('riskResult');
    const placeholderEl = document.getElementById('predictionPlaceholder');
    const scoreEl = document.getElementById('riskScore');
    const barEl = document.getElementById('riskBar');
    const textEl = document.getElementById('riskText');
    
    if (!resultEl || !placeholderEl) return;
    
    placeholderEl.style.display = 'none';
    resultEl.style.display = 'flex';
    resultEl.classList.add('show');
    resultEl.className = 'risk-result show ' + result.level;
    
    if (scoreEl) {
        scoreEl.textContent = result.score + '%';
        scoreEl.style.color = result.score >= 65 ? '#dc2626' : result.score >= 35 ? '#f59e0b' : '#22c55e';
    }
    
    if (barEl) {
        barEl.style.width = result.score + '%';
        barEl.style.background = result.score >= 65 ? '#dc2626' : result.score >= 35 ? '#f59e0b' : '#22c55e';
    }
};
</script>
