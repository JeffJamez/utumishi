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
        if ($_POST['action'] === 'create_officer') {
            $userData = [
                'national_id' => $_POST['national_id'],
                'name' => $_POST['name'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'password' => $_POST['password'],
                'station_id' => $_POST['station_id']
            ];
            
            $officerData = [
                'badge_number' => $_POST['badge_number'],
                'expertise_categories' => $_POST['expertise_categories'] ?? [],
                'joined_date' => $_POST['joined_date']
            ];
            
            $result = $adminManager->createOfficer($userData, $officerData);
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
        }
        
        if ($_POST['action'] === 'update_officer') {
            $userData = [];
            $officerData = [];
            
            if (!empty($_POST['name'])) $userData['name'] = $_POST['name'];
            if (!empty($_POST['email'])) $userData['email'] = $_POST['email'];
            if (!empty($_POST['phone'])) $userData['phone'] = $_POST['phone'];
            if (isset($_POST['station_id'])) $userData['station_id'] = $_POST['station_id'];
            
            if (!empty($_POST['badge_number'])) $officerData['badge_number'] = $_POST['badge_number'];
            if (isset($_POST['expertise_categories'])) $officerData['expertise_categories'] = $_POST['expertise_categories'];
            
            $result = $adminManager->updateOfficer($_POST['officer_id'], $userData, $officerData);
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
        }
        
        if ($_POST['action'] === 'transfer_officer') {
            $result = $adminManager->transferOfficer($_POST['officer_id'], $_POST['new_station_id'], $_POST['transfer_reason'] ?? '');
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
        }
        
        if ($_POST['action'] === 'deactivate_officer') {
            $result = $adminManager->deactivateOfficer($_POST['officer_id'], $_POST['deactivation_reason'] ?? '');
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Operation failed: ' . $e->getMessage();
    }
}

// Get data
$filters = [
    'station_id' => $_GET['station_id'] ?? '',
    'status' => $_GET['status'] ?? 'active'
];

$officers = [];
$stations = [];
$error = '';

try {
    $officers = $adminManager->getAllOfficers($filters);
    $stations = $adminManager->getAllStations();
    
} catch (Exception $e) {
    error_log("Manage Officers Error: " . $e->getMessage());
    $error = "Unable to load officers data";
}

$pageTitle = "Manage Officers";
require_once __DIR__ . '/../../includes/layout/layout.php';

?>

   <main class="app-main"> 
        <div class="mb-4">
            <h1>Manage Officers</h1>
            <p class="text-muted">Add, edit, and manage police officers across all stations</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Create New Officer -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Add New Officer</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create_officer">
                    
                    <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                        <div>
                            <label for="national_id" class="form-label">National ID</label>
                            <input type="text" name="national_id" id="national_id" class="form-control" required>
                        </div>
                        
                        <div>
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        
                        <div>
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        
                        <div>
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" name="phone" id="phone" class="form-control" required>
                        </div>
                        
                        <div>
                            <label for="badge_number" class="form-label">Badge Number</label>
                            <input type="text" name="badge_number" id="badge_number" class="form-control" required>
                        </div>
                        
                        <div>
                            <label for="station_id" class="form-label">Station</label>
                            <select name="station_id" id="station_id" class="form-control" required>
                                <option value="">Select Station</option>
                                <?php foreach ($stations as $station): ?>
                                    <option value="<?php echo $station['id']; ?>">
                                        <?php echo htmlspecialchars($station['name']); ?> - <?php echo htmlspecialchars($station['county']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="joined_date" class="form-label">Joined Date</label>
                            <input type="date" name="joined_date" id="joined_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div>
                            <label for="password" class="form-label">Initial Password</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label for="expertise_categories" class="form-label">Expertise Categories</label>
                        <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem;">
                            <?php 
                            $categories = ['Theft', 'Assault', 'Domestic Violence', 'Cybercrime', 'Drug Offenses', 'Traffic Offenses', 'Fraud', 'Public Order'];
                            foreach ($categories as $category): 
                            ?>
                                <label class="form-check">
                                    <input type="checkbox" name="expertise_categories[]" value="<?php echo $category; ?>" class="form-check-input">
                                    <?php echo $category; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Create Officer</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Filter Officers</h3>
            </div>
            <div class="card-body">
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
                    
                    <div>
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
            <div class="card-body">
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
                                    <th>Actions</th>
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
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline btn-secondary dropdown-toggle" data-toggle="dropdown">
                                                    Actions
                                                </button>
                                                <div class="dropdown-menu">
                                                    <button class="dropdown-item" onclick="editOfficer(<?php echo $officer['id']; ?>, '<?php echo htmlspecialchars($officer['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($officer['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($officer['phone'], ENT_QUOTES); ?>')">
                                                        Edit Details
                                                    </button>
                                                    <button class="dropdown-item" onclick="transferOfficer(<?php echo $officer['id']; ?>, '<?php echo htmlspecialchars($officer['name'], ENT_QUOTES); ?>')">
                                                        Transfer Station
                                                    </button>
                                                    <?php if ($officer['is_active']): ?>
                                                        <button class="dropdown-item text-danger" onclick="deactivateOfficer(<?php echo $officer['id']; ?>, '<?php echo htmlspecialchars($officer['name'], ENT_QUOTES); ?>')">
                                                            Deactivate
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
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