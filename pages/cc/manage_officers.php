<?php
define('UTUMISHI_WEB_APP', true);

session_start();

// Prevent caching to ensure fresh data
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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
if (!$userDetails) {
    $error = "User account not found in database. Please contact system administrator.";
} else {
    $county = $userDetails['county_in_charge'] ?? null;
}

// For county commanders, county must be assigned
if ($currentUser['role'] === 'county_commander' && !$county) {
    $error = "Your account is not properly configured with a county assignment. Please contact system administrator.";
}

$filters = [
    'station_id' => $_GET['station_id'] ?? '',
    'status' => $_GET['status'] ?? 'active'
];

if ($county) {
    $filters['county'] = $county;
}

error_log("manage_officers.php - userID: {$currentUser['id']}, county: $county, filters: " . json_encode($filters));

$officers = [];
$stations = [];
$error = '';

try {
    $officers = $adminManager->getAllOfficers($filters);
    $stations = $adminManager->getAllStations($county);
    
} catch (Exception $e) {
    error_log("Manage Officers Error: " . $e->getMessage());
    $error = "Unable to load officers data";
}

$pageTitle = "Manage Officers";
require_once __DIR__ . '/../../includes/layout/layout.php';

?>

    <main class="app-main">
         <div class="mb-4">
             <h2>Police Officers<?php if ($county) echo " - $county County"; ?></h2>
             <p class="text-muted">View police officers in your county</p>
         </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>



         <!-- Filters -->
         <div class="card mb-4">
             <div class="card-header">
                 <h3>Filter Officers (<?php echo htmlspecialchars($county); ?> County Only)</h3>
             </div>
            <div class="card-body" >
                <form method="GET" class="d-flex gap-3 align-items-end">
                    <div>
                        <label for="station_filter" class="form-label">Station</label>
                        <select name="station_id" id="station_filter" class="form-control">
                            <option value="">All Stations</option>
                            <?php foreach ($stations as $station): ?>
                                <option value="<?php echo $station['id']; ?>" <?php echo $filters['station_id'] == $station['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($station['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status_filter" class="form-label">Status</label>
                        <select name="status" id="status_filter" class="form-control">
                            <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="" <?php echo $filters['status'] === '' ? 'selected' : ''; ?>>All</option>
                        </select>
                    </div>
                    
                    <div style="margin-top: 35px;">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Officers Table -->
        <div class="card">
            <div class="card-header">
                <h3>Officers (<?php echo count($officers); ?>)</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (!empty($officers)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Officer</th>
                                    <th>Badge</th>
                                    <th>Station</th>
                                    <th>Case Load</th>
                                    <th>Resolved</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                     <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($officers as $officer): ?>
                                    <tr class="<?php echo !$officer['is_active'] ? 'table-secondary' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($officer['name']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($officer['email']); ?><br>
                                                <?php echo htmlspecialchars($officer['phone']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($officer['badge_number']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($officer['station_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($officer['county']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $officer['current_case_load'] > 15 ? 'danger' : ($officer['current_case_load'] > 10 ? 'warning' : 'info'); ?>">
                                                <?php echo $officer['current_case_load']; ?> cases
                                            </span>
                                        </td>
                                        <td><?php echo $officer['total_cases_resolved']; ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $officer['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $officer['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($officer['last_login']): ?>
                                                <?php echo date('M d, Y', strtotime($officer['last_login'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </td>
                                          <td>
                                              <a href="<?php echo BASE_URL; ?>/pages/cc/officer_details.php?id=<?php echo $officer['officer_id']; ?>" class="btn btn-sm btn-primary">
                                                  View
                                              </a>
                                          </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4">
                        <div style="font-size: 3rem;">👮</div>
                        <h4>No Officers Found</h4>
                        <p class="text-muted">No officers match your current filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Edit Officer Modal -->
        <div id="editOfficerModal" class="modal" style="display: none;">
            <div class="modal-content">
                <h3>Edit Officer</h3>
                <form method="POST" id="editOfficerForm">
                    <input type="hidden" name="action" value="update_officer">
                    <input type="hidden" name="officer_id" id="edit_officer_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone</label>
                        <input type="tel" name="phone" id="edit_phone" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_station_id" class="form-label">Station</label>
                        <select name="station_id" id="edit_station_id" class="form-control" required>
                            <?php foreach ($stations as $station): ?>
                                <option value="<?php echo $station['id']; ?>">
                                    <?php echo htmlspecialchars($station['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Officer</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editOfficerModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        </main>
    </div>
    
    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>
        function editOfficer(id, name, email, phone) {
            document.getElementById('edit_officer_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('editOfficerModal').style.display = 'block';
        }
        
        function transferOfficer(id, name) {
            const newStationId = prompt(`Transfer ${name} to which station? Enter station ID:`);
            if (newStationId) {
                const reason = prompt('Transfer reason (optional):') || '';
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="transfer_officer">
                    <input type="hidden" name="officer_id" value="${id}">
                    <input type="hidden" name="new_station_id" value="${newStationId}">
                    <input type="hidden" name="transfer_reason" value="${reason}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deactivateOfficer(id, name) {
            if (confirm(`Are you sure you want to deactivate ${name}? This action will prevent them from logging in.`)) {
                const reason = prompt('Deactivation reason (optional):') || '';
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="deactivate_officer">
                    <input type="hidden" name="officer_id" value="${id}">
                    <input type="hidden" name="deactivation_reason" value="${reason}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
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