<?php
define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';
require_once __DIR__ . '/../../includes/classes/AdminManager.php';

requireRole(ROLE_ADMIN);

$currentUser = getCurrentUser();
$adminManager = new AdminManager();

// Fetch county_in_charge from database
$db = Database::getInstance();
$userDetails = $db->fetchOne("SELECT county_in_charge FROM users WHERE id = :id", ['id' => $currentUser['id']]);
$county = $userDetails['county_in_charge'] ?? null;

if ($_POST) {
    try {
        if ($_POST['action'] === 'update_station') {
            $stationData = [
                'name' => $_POST['name'],
                'county' => $_POST['county'],
                'constituency' => $_POST['constituency'],
                'address' => $_POST['address'],
                'contact_phone' => $_POST['contact_phone'],
                'commander_id' => !empty($_POST['commander_id']) ? $_POST['commander_id'] : null
            ];
            
            $result = $adminManager->updateStation($_POST['station_id'], $stationData);
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Operation failed: ' . $e->getMessage();
    }
}

// Get data
$stations = [];
$ocsOfficers = [];
$error = '';

try {
    $stations = $adminManager->getAllStations($county);
    
    // Get potential OCS officers (officers without current command)
    $db = getDB();
    $ocsWhere = "u.role IN ('officer', 'ocs') AND u.is_active = 1";
    $ocsParams = [];

    if ($county) {
        $ocsWhere .= " AND s.county = :county";
        $ocsParams['county'] = $county;
    }

    $ocsOfficers = $db->fetchAll("
        SELECT u.id, u.name, o.badge_number, s.name as current_station
        FROM users u
        JOIN officers o ON u.id = o.user_id
        LEFT JOIN stations s ON u.station_id = s.id
        WHERE $ocsWhere
        ORDER BY u.name
    ", $ocsParams);
    
} catch (Exception $e) {
    error_log("Manage Stations Error: " . $e->getMessage());
    $error = "Unable to load stations data";
}

$pageTitle = "Manage Stations";

require_once __DIR__ . '/../../includes/layout/layout.php';

?>

        <main class="app-main">
            <div class="mb-4">
                <h2>Police Stations</h2>
                <p class="text-muted">View and manage police stations in your county</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>




            <!-- Stations Table -->
            <div class="card">
                <div class="card-header">
                    <h3>Police Stations</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($stations)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                     <tr>
                                         <th>Station</th>
                                         <th>Location</th>
                                         <th>Commander (OCS)</th>
                                         <th>Officers</th>
                                         <th>Cases</th>
                                         <th>Actions</th>
                                     </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stations as $station): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($station['name']); ?></strong><br>
                                                <small class="text-muted">
                                                    Code: <?php echo htmlspecialchars($station['station_code']); ?><br>
                                                    <?php echo htmlspecialchars($station['contact_phone'] ?: 'No phone'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($station['constituency']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($station['county']); ?> County</small>
                                            </td>
                                            <td>
                                                <?php if ($station['commander_name']): ?>
                                                    <?php echo htmlspecialchars($station['commander_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-warning">No Commander</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $station['officer_count']; ?> officers</span>
                                            </td>
                                             <td>
                                                 <span class="badge badge-secondary"><?php echo $station['total_cases']; ?> cases</span>
                                             </td>
                                             <td>
                                                 <button class="btn btn-sm btn-outline btn-primary"
                                                         onclick="viewStationDetails(<?php echo $station['id']; ?>)">
                                                     View Details
                                                 </button>
                                             </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <div style="font-size: 3rem;">🏢</div>
                            <h4>No Stations Found</h4>
                            <p class="text-muted">Start by adding your first police station.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- View Station Modal -->
            <div id="viewStationModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <h3>Station Details</h3>
                </div>
                <div class="card-body">
                    <div id="stationDetailsContent">
                        <!-- Content will be loaded here -->
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('viewStationModal')">Close</button>
                    </div>
                </div>
            </div>

        </main>
    </div>
    
    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>
        function viewStationDetails(id) {
            // Find station data from the table
            const stations = <?php echo json_encode($stations); ?>;
            const station = stations.find(s => s.id == id);

            if (station) {
                const commanderName = station.commander_name || 'No Commander Assigned';
                const content = `
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Station Information</h5>
                            <p><strong>Name:</strong> ${station.name}</p>
                            <p><strong>Code:</strong> ${station.station_code}</p>
                            <p><strong>County:</strong> ${station.county}</p>
                            <p><strong>Constituency:</strong> ${station.constituency}</p>
                            <p><strong>Contact Phone:</strong> ${station.contact_phone || 'Not provided'}</p>
                            <p><strong>Address:</strong> ${station.address || 'Not provided'}</p>
                            <p><strong>Commander:</strong> ${commanderName}</p>
                        </div>
                        <div class="col-md-6">
                            <h5>Assigned Officers</h5>
                            <p><strong>Total Officers:</strong> ${station.officer_count}</p>
                            <p><strong>Total Cases:</strong> ${station.total_cases}</p>
                        </div>
                    </div>
                `;
                document.getElementById('stationDetailsContent').innerHTML = content;
            }
            document.getElementById('viewStationModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

    </script>
    
    <style>
        .kpi-grid {
            grid-template-columns: repeat(4, 1fr);
        }

        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .badge-info {
            background-color: var(--info-blue);
            color: white;
        }
        
        .badge-secondary {
            background-color: var(--medium-gray);
            color: white;
        }
    </style>
</body>
</html>