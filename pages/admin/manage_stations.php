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

// Handle form submissions
if ($_POST) {
    try {
        if ($_POST['action'] === 'create_station') {
            $stationData = [
                'name' => $_POST['name'],
                'station_code' => $_POST['station_code'],
                'county' => $_POST['county'],
                'constituency' => $_POST['constituency'],
                'address' => $_POST['address'],
                'contact_phone' => $_POST['contact_phone'],
                'budget_allocated' => $_POST['budget_allocated'] ?? 0,
                'commander_id' => !empty($_POST['commander_id']) ? $_POST['commander_id'] : null
            ];
            
            $result = $adminManager->createStation($stationData);
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
        }
        
        if ($_POST['action'] === 'update_station') {
            $stationData = [
                'name' => $_POST['name'],
                'county' => $_POST['county'],
                'constituency' => $_POST['constituency'],
                'address' => $_POST['address'],
                'contact_phone' => $_POST['contact_phone'],
                'budget_allocated' => $_POST['budget_allocated'],
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
    $stations = $adminManager->getAllStations();
    
    // Get potential OCS officers (officers without current command)
    $db = getDB();
    $ocsOfficers = $db->fetchAll("
        SELECT u.id, u.name, o.badge_number, s.name as current_station
        FROM users u
        JOIN officers o ON u.id = o.user_id
        LEFT JOIN stations s ON u.station_id = s.id
        WHERE u.role IN ('officer', 'ocs') 
        AND u.is_active = 1
        ORDER BY u.name
    ");
    
} catch (Exception $e) {
    error_log("Manage Stations Error: " . $e->getMessage());
    $error = "Unable to load stations data";
}

$pageTitle = "Manage Stations";

require_once __DIR__ . '/../../includes/layout/layout.php';

?>

        <main class="app-main">
            <div class="mb-4">
                <h1>Manage Police Stations</h1>
                <p class="text-muted">Add, edit, and manage police stations across Kenya</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Create New Station -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Add New Station</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_station">
                        
                        <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                            <div>
                                <label for="name" class="form-label">Station Name</label>
                                <input type="text" name="name" id="name" class="form-control" required 
                                    placeholder="e.g., Nairobi Central Police Station">
                            </div>
                            
                            <div>
                                <label for="station_code" class="form-label">Station Code</label>
                                <input type="text" name="station_code" id="station_code" class="form-control" required 
                                    placeholder="e.g., NRB" maxlength="10">
                            </div>
                            
                            <div>
                                <label for="county" class="form-label">County</label>
                                <input type="text" name="county" id="county" class="form-control" required 
                                    placeholder="e.g., Nairobi">
                            </div>
                            
                            <div>
                                <label for="constituency" class="form-label">Constituency</label>
                                <input type="text" name="constituency" id="constituency" class="form-control" required 
                                    placeholder="e.g., Starehe">
                            </div>
                            
                            <div>
                                <label for="contact_phone" class="form-label">Contact Phone</label>
                                <input type="tel" name="contact_phone" id="contact_phone" class="form-control" 
                                    placeholder="+254...">
                            </div>
                            
                            <div>
                                <label for="budget_allocated" class="form-label">Budget Allocated (KES)</label>
                                <input type="number" name="budget_allocated" id="budget_allocated" class="form-control" 
                                    step="0.01" min="0" placeholder="0.00">
                            </div>
                            
                            <div>
                                <label for="commander_id" class="form-label">Station Commander (OCS)</label>
                                <select name="commander_id" id="commander_id" class="form-control">
                                    <option value="">Select Commander</option>
                                    <?php foreach ($ocsOfficers as $officer): ?>
                                        <option value="<?php echo $officer['id']; ?>">
                                            <?php echo htmlspecialchars($officer['name']); ?> 
                                            (<?php echo htmlspecialchars($officer['badge_number']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label for="address" class="form-label">Full Address</label>
                            <textarea name="address" id="address" class="form-control" rows="3" required 
                                    placeholder="Enter complete station address"></textarea>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Create Station</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Stations Overview -->
            <div class="kpi-grid mb-4">
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo count($stations); ?></div>
                    <div class="kpi-label">Total Stations</div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo count(array_filter($stations, function($s) { return $s['commander_name']; })); ?></div>
                    <div class="kpi-label">Stations with OCS</div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo count(array_unique(array_column($stations, 'county'))); ?></div>
                    <div class="kpi-label">Counties Covered</div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-value">KES <?php echo number_format(array_sum(array_column($stations, 'budget_allocated')), 0); ?></div>
                    <div class="kpi-label">Total Budget</div>
                </div>
            </div>

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
                                        <th>Budget</th>
                                        <th>Performance</th>
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
                                                KES <?php echo number_format($station['budget_allocated'], 0); ?>
                                            </td>
                                            <td>
                                                <?php if ($station['avg_resolution_time']): ?>
                                                    <?php echo round($station['avg_resolution_time'], 1); ?>h avg
                                                <?php else: ?>
                                                    <span class="text-muted">No data</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline btn-primary" 
                                                        onclick="editStation(<?php echo $station['id']; ?>, '<?php echo htmlspecialchars($station['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($station['county'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($station['constituency'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($station['address'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($station['contact_phone'], ENT_QUOTES); ?>', '<?php echo $station['budget_allocated']; ?>', '<?php echo $station['commander_id']; ?>')">
                                                    Edit
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

            <!-- Edit Station Modal -->
            <div id="editStationModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <h3>Edit Station</h3>
                    <form method="POST" id="editStationForm">
                        <input type="hidden" name="action" value="update_station">
                        <input type="hidden" name="station_id" id="edit_station_id">
                        
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Station Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        
                        <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="mb-3">
                                <label for="edit_county" class="form-label">County</label>
                                <input type="text" name="county" id="edit_county" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_constituency" class="form-label">Constituency</label>
                                <input type="text" name="constituency" id="edit_constituency" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_address" class="form-label">Address</label>
                            <textarea name="address" id="edit_address" class="form-control" rows="3" required></textarea>
                        </div>
                        
                        <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="mb-3">
                                <label for="edit_contact_phone" class="form-label">Contact Phone</label>
                                <input type="tel" name="contact_phone" id="edit_contact_phone" class="form-control">
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_budget_allocated" class="form-label">Budget (KES)</label>
                                <input type="number" name="budget_allocated" id="edit_budget_allocated" class="form-control" step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_commander_id" class="form-label">Station Commander</label>
                            <select name="commander_id" id="edit_commander_id" class="form-control">
                                <option value="">Select Commander</option>
                                <?php foreach ($ocsOfficers as $officer): ?>
                                    <option value="<?php echo $officer['id']; ?>">
                                        <?php echo htmlspecialchars($officer['name']); ?> 
                                        (<?php echo htmlspecialchars($officer['badge_number']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Update Station</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('editStationModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

        </main>
    </div>
    
    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>
        function editStation(id, name, county, constituency, address, phone, budget, commanderId) {
            document.getElementById('edit_station_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_county').value = county;
            document.getElementById('edit_constituency').value = constituency;
            document.getElementById('edit_address').value = address;
            document.getElementById('edit_contact_phone').value = phone;
            document.getElementById('edit_budget_allocated').value = budget;
            document.getElementById('edit_commander_id').value = commanderId || '';
            document.getElementById('editStationModal').style.display = 'block';
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
        
        // Auto-generate station code from name
        document.getElementById('name').addEventListener('input', function() {
            const name = this.value;
            const words = name.split(' ');
            let code = '';
            
            if (words.length >= 2) {
                code = words[0].substring(0, 3).toUpperCase();
            } else if (words.length === 1) {
                code = words[0].substring(0, 3).toUpperCase();
            }
            
            document.getElementById('station_code').value = code;
        });
    </script>
    
    <style>
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