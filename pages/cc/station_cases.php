<?php
define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';
require_once __DIR__ . '/../../includes/classes/User.php';
require_once __DIR__ . '/../../includes/classes/AdminManager.php';

requireRole(ROLE_ADMIN);

$currentUser = getCurrentUser();
$adminManager = new AdminManager();
$db = Database::getInstance();

$userDetails = $db->fetchOne("SELECT county_in_charge FROM users WHERE id = :id", ['id' => $currentUser['id']]);
$county = $userDetails['county_in_charge'] ?? null;

if (!$county) {
    $error = "Your account is not configured with a county. Please contact system administrator.";
}

$filters = [
    'status' => $_GET['status'] ?? 'all',
    'category' => $_GET['category'] ?? '',
    'officer_id' => $_GET['officer'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'constituency' => $_GET['constituency'] ?? ''
];

$selectedStationId = $_GET['station_id'] ?? '';
$searchTerm = $_GET['search'] ?? '';

$limit = 20;
$page = (int)($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;

$cases = [];
$caseStats = [];
$totalCases = 0;
$totalPages = 0;
$stations = [];
$officers = [];
$categories = [];
$constituencies = [];
$error = '';

try {
    $stations = $adminManager->getAllStations($county);
    
    $stationIdParam = $selectedStationId ? (int)$selectedStationId : null;
    
    $whereConditions = [];
    $params = [];
    
    $whereConditions[] = "c.incident_location_county = :county";
    $params['county'] = $county;
    
    if ($stationIdParam) {
        $whereConditions[] = "c.station_id = :station_id";
        $params['station_id'] = $stationIdParam;
    }
    
    if ($searchTerm) {
        $whereConditions[] = "(c.ob_number LIKE :search1 OR c.title LIKE :search2 OR c.description LIKE :search3)";
        $params['search1'] = "%$searchTerm%";
        $params['search2'] = "%$searchTerm%";
        $params['search3'] = "%$searchTerm%";
    }
    
    if ($filters['status'] && $filters['status'] !== 'all') {
        $whereConditions[] = "c.status = :status";
        $params['status'] = $filters['status'];
    }
    
    if ($filters['category']) {
        $whereConditions[] = "c.category = :category";
        $params['category'] = $filters['category'];
    }
    
    if ($filters['officer_id']) {
        $whereConditions[] = "c.assigned_officer_id = :officer_id";
        $params['officer_id'] = $filters['officer_id'];
    }
    
    if ($filters['date_from']) {
        $whereConditions[] = "DATE(c.created_at) >= :date_from";
        $params['date_from'] = $filters['date_from'];
    }
    
    if ($filters['date_to']) {
        $whereConditions[] = "DATE(c.created_at) <= :date_to";
        $params['date_to'] = $filters['date_to'];
    }
    
    if ($filters['constituency']) {
        $whereConditions[] = "c.incident_location_constituency = :constituency";
        $params['constituency'] = $filters['constituency'];
    }
    
    $whereClause = implode(" AND ", $whereConditions);
    
    $totalCases = $db->fetchOne("SELECT COUNT(*) as total FROM cases c WHERE $whereClause", $params)['total'] ?? 0;
    $totalPages = ceil($totalCases / $limit);
    
    $cases = $db->fetchAll("
        SELECT c.*, 
               s.name as station_name,
               u1.name as reporter_name,
               c.reporter_anonymized,
               u2.name as assigned_officer,
               o.badge_number
        FROM cases c
        LEFT JOIN stations s ON c.station_id = s.id
        LEFT JOIN users u1 ON c.reported_by_citizen_id = u1.id
        LEFT JOIN officers o ON c.assigned_officer_id = o.id
        LEFT JOIN users u2 ON o.user_id = u2.id
        WHERE $whereClause
        ORDER BY c.created_at DESC
        LIMIT :limit OFFSET :offset
    ", array_merge($params, ['limit' => $limit, 'offset' => $offset]));
    
    $caseStats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_cases,
            COUNT(CASE WHEN status = 'reported' THEN 1 END) as reported_cases,
            COUNT(CASE WHEN status = 'assigned' THEN 1 END) as assigned_cases,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_cases,
            COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
            ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as resolution_rate,
            AVG(CASE WHEN status IN ('resolved', 'closed') AND actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
        FROM cases c
        WHERE c.incident_location_county = :county_stat
    ", ['county_stat' => $county]) ?? [];
    
    if ($selectedStationId) {
        $officers = $db->fetchAll("
            SELECT o.id, o.badge_number, u.name
            FROM officers o
            JOIN users u ON o.user_id = u.id
            WHERE o.station_id = :station_id AND u.is_active = 1
        ", ['station_id' => $selectedStationId]);
    } else {
        $officers = $db->fetchAll("
            SELECT DISTINCT o.id, o.badge_number, u.name
            FROM officers o
            JOIN users u ON o.user_id = u.id
            JOIN stations s ON o.station_id = s.id
            WHERE s.county = :county_officers AND u.is_active = 1
        ", ['county_officers' => $county]);
    }
    
    $categories = $db->fetchAll("
        SELECT DISTINCT category FROM cases 
        WHERE incident_location_county = :county_cat 
        ORDER BY category
    ", ['county_cat' => $county]);
    
    $constituencies = $db->fetchAll("
        SELECT DISTINCT incident_location_constituency as constituency 
        FROM cases 
        WHERE incident_location_county = :county_const
        ORDER BY incident_location_constituency
    ", ['county_const' => $county]);

} catch (Exception $e) {
    error_log("County Station Cases Error: " . $e->getMessage());
    $error = "Unable to load case data";
}

$pageTitle = "Station Cases";
require_once __DIR__ . '/../../includes/layout/layout.php';
?>

    <main class="app-main">
        <div class="mb-4">
            <h1>Station Cases - <?php echo htmlspecialchars($county); ?> County</h1>
            <p class="text-muted">View and filter cases across stations in your county</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <h3>Search & Filter Cases</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-3">
                    <div class="input-group" style="display:flex; gap: 8px;">
                        <input type="text" name="search" class="form-control" style="max-width: 50%" placeholder="Search by OB number, title, or description..."
                            value="<?php echo htmlspecialchars($searchTerm); ?>" style="height: 3rem;">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <?php if ($searchTerm): ?>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                    <?php foreach ($filters as $key => $value): ?>
                        <?php if ($value && $value !== 'all'): ?>
                            <input type="hidden" name="<?php echo htmlspecialchars($key === 'officer_id' ? 'officer' : $key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($selectedStationId): ?>
                        <input type="hidden" name="station_id" value="<?php echo htmlspecialchars($selectedStationId); ?>">
                    <?php endif; ?>
                </form>

                <form method="GET" class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <?php if ($searchTerm): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <?php endif; ?>
                    
                    <div>
                        <label for="station_id" class="form-label">Station</label>
                        <select name="station_id" id="station_id" class="form-control" onchange="this.form.submit()">
                            <option value="">All Stations in <?php echo htmlspecialchars($county); ?></option>
                            <?php foreach ($stations as $station): ?>
                                <option value="<?php echo $station['id']; ?>" <?php echo $selectedStationId == $station['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($station['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="all" <?php echo $filters['status'] === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="reported" <?php echo $filters['status'] === 'reported' ? 'selected' : ''; ?>>Reported</option>
                            <option value="assigned" <?php echo $filters['status'] === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="in_progress" <?php echo $filters['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="resolved" <?php echo $filters['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="closed" <?php echo $filters['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="category" class="form-label">Category</label>
                        <select name="category" id="category" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                        <?php echo $filters['category'] === $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="officer" class="form-label">Assigned Officer</label>
                        <select name="officer" id="officer" class="form-control">
                            <option value="">All Officers</option>
                            <?php foreach ($officers as $off): ?>
                                <option value="<?php echo $off['id']; ?>" 
                                        <?php echo $filters['officer_id'] == $off['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($off['name']); ?> (<?php echo htmlspecialchars($off['badge_number']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                    </div>
                    
                    <div>
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                    </div>

                    <div>
                        <label for="constituency" class="form-label">Constituency</label>
                        <select name="constituency" id="constituency" class="form-control">
                            <option value="">All Constituencies</option>
                            <?php foreach ($constituencies as $const): ?>
                                <option value="<?php echo htmlspecialchars($const['constituency']); ?>"
                                        <?php echo $filters['constituency'] === $const['constituency'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($const['constituency']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; justify-content: end;">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="kpi-grid mb-4">
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $caseStats['total_cases'] ?? 0; ?></div>
                <div class="kpi-label">Total Cases</div>
                <div class="kpi-change">
                    <?php if ($searchTerm || array_filter($filters) || $selectedStationId): ?>
                        <small class="text-muted">Filtered results</small>
                    <?php else: ?>
                        <small class="text-muted">All time</small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value"><?php echo ($caseStats['assigned_cases'] ?? 0) + ($caseStats['in_progress_cases'] ?? 0); ?></div>
                <div class="kpi-label">Active Cases</div>
                <div class="kpi-change">
                    In Progress: <span class="positive"><?php echo $caseStats['in_progress_cases'] ?? 0; ?></span>
                </div>
            </div>
            
           <!--  <div class="kpi-card">
                <div class="kpi-value"><?php echo $caseStats['reported_cases'] ?? 0; ?></div>
                <div class="kpi-label">Unassigned</div>
                <div class="kpi-change">
                    <?php if (($caseStats['reported_cases'] ?? 0) > 0): ?>
                        <span class="negative">Needs attention</span>
                    <?php else: ?>
                        <span class="positive">All assigned</span>
                    <?php endif; ?>
                </div>
            </div> -->
            
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $caseStats['resolution_rate'] ?? 0; ?>%</div>
                <div class="kpi-label">Resolution Rate</div>
                <div class="kpi-change">
                    Avg Time: <span class="neutral"><?php echo round($caseStats['avg_resolution_time'] ?? 0, 1); ?>h</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Cases</h3>
                <div class="d-flex gap-2">
                    <?php if ($searchTerm): ?>
                        <span class="badge status-info">Search: "<?php echo htmlspecialchars($searchTerm); ?>"</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (!empty($cases)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>OB Number</th>
                                    <th>Case Details</th>
                                    <th>Station</th>
                                    <th>Reporter</th>
                                    <th>Assigned Officer</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cases as $case): ?>
                                    <?php $rowClass = ($case['status'] === 'resolved' || $case['status'] === 'closed') ? 'table-success' : ''; ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($case['ob_number']); ?></strong>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($case['title']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($case['category']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($case['station_name']); ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($case['reporter_anonymized'])): ?>
                                                <span style="color: #dc3545; font-weight: bold;">ANONYMIZED</span>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($case['reporter_name']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($case['assigned_officer']): ?>
                                                <?php echo htmlspecialchars($case['assigned_officer']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo STATUS_COLORS[$case['status']] ?? 'status-reported'; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/pages/cc/case_search.php?ob_number=<?php echo urlencode($case['ob_number']); ?>"
                                            class="btn btn-sm btn-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            Showing <?php echo count($cases); ?> of <?php echo $totalCases; ?> cases
                            <?php if ($totalPages > 1): ?>
                                | Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Case pagination" style="display: flex; justify-content: center; margin-top: 20px;">
                        <ul style="display: flex; list-style: none; padding: 0; margin: 0; border: 1px solid #ddd; border-radius: 5px; overflow: hidden;">
                            <?php if ($page > 1): ?>
                                <li style="margin: 0;">
                                    <a style="display: block; padding: 5px 10px; text-decoration: none; background-color: #3b82f6; color: white; border-right: 1px solid #ddd;" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                </li>
                            <?php endif; ?>

                            <li style="margin: 0;">
                                <span style="display: block; padding: 5px 10px; background-color: #f8f9fa; color: #6c757d; border-right: 1px solid #ddd;">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalCases; ?> total)</span>
                            </li>

                            <?php if ($page < $totalPages): ?>
                                <li style="margin: 0;">
                                    <a style="display: block; padding: 5px 10px; text-decoration: none; background-color: #3b82f6; color: white;" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center p-4">
                        <div style="font-size: 3rem;">📁</div>
                        <h4>No Cases Found</h4>
                        <p class="text-muted">No cases match your current filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>

<style>
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
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
    }
    .kpi-change {
        margin-top: 0.5rem;
        font-size: 0.85rem;
    }
    .positive { color: #22c55e; }
    .negative { color: #dc3545; }
    .neutral { color: #f59e0b; }
    .status-info { background: #3b82f6; }
</style>
</body>
</html>