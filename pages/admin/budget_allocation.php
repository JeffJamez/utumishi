<?php
define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';
require_once __DIR__ . '/../../includes/classes/BudgetManager.php';

requireRole(ROLE_ADMIN);

$currentUser = getCurrentUser();
$budgetManager = new BudgetManager();

// Handle form submissions
if ($_POST) {
    try {
        if ($_POST['action'] === 'allocate_budget') {
            $allocations = [];
            $categories = $budgetManager->getBudgetCategories();
            
            foreach ($categories as $category => $label) {
                if (isset($_POST[$category]) && $_POST[$category] > 0) {
                    $allocations[$category] = $_POST[$category];
                }
            }
            
            $result = $budgetManager->allocateBudget($_POST['station_id'], $_POST['year'], $allocations);
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
        }
        
        if ($_POST['action'] === 'record_expenditure') {
            $result = $budgetManager->recordExpenditure(
                $_POST['station_id'], 
                $_POST['year'], 
                $_POST['category'], 
                $_POST['amount'], 
                $_POST['description'] ?? ''
            );
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
        }
        
        if ($_POST['action'] === 'transfer_budget') {
            $result = $budgetManager->transferBudget(
                $_POST['from_station_id'],
                $_POST['from_category'],
                $_POST['to_station_id'],
                $_POST['to_category'],
                $_POST['amount'],
                $_POST['year'],
                $_POST['reason'] ?? ''
            );
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
        }
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?year=' . ($_POST['year'] ?? date('Y')));
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Operation failed: ' . $e->getMessage();
    }
}

// Get data
$year = $_GET['year'] ?? date('Y');
$budgetOverview = [];
$analytics = [];
$recommendations = [];
$stations = [];
$categories = [];
$error = '';

try {
    $budgetOverview = $budgetManager->getBudgetOverview($year);
    $analytics = $budgetManager->getBudgetAnalytics($year);
    $recommendations = $budgetManager->generateBudgetRecommendations($year);
    $categories = $budgetManager->getBudgetCategories();
    
    // Get stations for dropdowns
    $db = getDB();
    $stations = $db->fetchAll("SELECT id, name, county FROM stations ORDER BY county, name");
    
} catch (Exception $e) {
    error_log("Budget Allocation Error: " . $e->getMessage());
    $error = "Unable to load budget data";
}

$pageTitle = "Budget Allocation";
require_once __DIR__ . '/../../includes/layout/layout.php';

?>

 <main class="app-main">

        <div class="mb-4">
            <h1>Budget Allocation & Management</h1>
            <p class="text-muted">Manage police service budget allocation across stations and categories</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Year Selector -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Budget Year: <?php echo $year; ?></h3>
            </div>
            <div class="card-body">
                <div class="d-flex gap-2">
                    <?php for ($y = date('Y') + 1; $y >= date('Y') - 2; $y--): ?>
                        <a href="?year=<?php echo $y; ?>" 
                        class="btn btn-<?php echo $y == $year ? 'primary' : 'outline'; ?> btn-sm">
                            <?php echo $y; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Budget Overview -->
        <div class="kpi-grid mb-4">
            <div class="kpi-card">
                <div class="kpi-value">KES <?php echo number_format($analytics['summary']['total_allocated'] ?? 0, 0); ?></div>
                <div class="kpi-label">Total Allocated</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value">KES <?php echo number_format($analytics['summary']['total_spent'] ?? 0, 0); ?></div>
                <div class="kpi-label">Total Spent</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $analytics['summary']['overall_utilization'] ?? 0; ?>%</div>
                <div class="kpi-label">Utilization Rate</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $analytics['summary']['stations_with_budget'] ?? 0; ?></div>
                <div class="kpi-label">Stations with Budget</div>
            </div>
        </div>

        <!-- Budget Recommendations -->
        <?php if (!empty($recommendations)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h3>Budget Recommendations</h3>
                <span class="badge status-info"><?php echo count($recommendations); ?> recommendations</span>
            </div>
            <div class="card-body">
                <?php foreach (array_slice($recommendations, 0, 5) as $rec): ?>
                    <div class="alert alert-<?php echo $rec['priority'] === 'high' ? 'warning' : 'info'; ?> mb-2">
                        <strong><?php echo ucfirst($rec['type']); ?> - <?php echo ucfirst($rec['priority']); ?> Priority:</strong><br>
                        <?php echo htmlspecialchars($rec['message']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
            <!-- Allocate Budget -->
            <div class="card">
                <div class="card-header">
                    <h3>Allocate Budget</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="allocate_budget">
                        <input type="hidden" name="year" value="<?php echo $year; ?>">
                        
                        <div class="mb-3">
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
                        
                        <?php foreach ($categories as $category => $label): ?>
                            <div class="mb-3">
                                <label for="<?php echo $category; ?>" class="form-label"><?php echo $label; ?></label>
                                <input type="number" name="<?php echo $category; ?>" id="<?php echo $category; ?>" 
                                    class="form-control" step="0.01" min="0" placeholder="0.00">
                            </div>
                        <?php endforeach; ?>
                        
                        <button type="submit" class="btn btn-primary">Allocate Budget</button>
                    </form>
                </div>
            </div>
            
            <!-- Record Expenditure -->
            <div class="card">
                <div class="card-header">
                    <h3>Record Expenditure</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="record_expenditure">
                        <input type="hidden" name="year" value="<?php echo $year; ?>">
                        
                        <div class="mb-3">
                            <label for="exp_station_id" class="form-label">Station</label>
                            <select name="station_id" id="exp_station_id" class="form-control" required>
                                <option value="">Select Station</option>
                                <?php foreach ($stations as $station): ?>
                                    <option value="<?php echo $station['id']; ?>">
                                        <?php echo htmlspecialchars($station['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="exp_category" class="form-label">Category</label>
                            <select name="category" id="exp_category" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category => $label): ?>
                                    <option value="<?php echo $category; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="exp_amount" class="form-label">Amount (KES)</label>
                            <input type="number" name="amount" id="exp_amount" class="form-control" 
                                step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="exp_description" class="form-label">Description</label>
                            <textarea name="description" id="exp_description" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Record Expenditure</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Budget by Category -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Budget by Category</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($analytics['by_category'])): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Total Allocated</th>
                                    <th>Total Spent</th>
                                    <th>Utilization Rate</th>
                                    <th>Stations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analytics['by_category'] as $cat): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($categories[$cat['category']] ?? $cat['category']); ?></strong></td>
                                        <td>KES <?php echo number_format($cat['total_allocated'], 0); ?></td>
                                        <td>KES <?php echo number_format($cat['total_spent'], 0); ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $cat['utilization_rate'] > 90 ? 'danger' : ($cat['utilization_rate'] > 70 ? 'warning' : 'success'); ?>">
                                                <?php echo $cat['utilization_rate']; ?>%
                                            </span>
                                        </td>
                                        <td><?php echo $cat['station_count']; ?> stations</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Budget by Station -->
        <div class="card">
            <div class="card-header">
                <h3>Budget by Station</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($budgetOverview)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Station</th>
                                    <th>County</th>
                                    <th>Budget Allocated</th>
                                    <th>Amount Spent</th>
                                    <th>Utilization</th>
                                    <th>Officers</th>
                                    <th>Cases</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($budgetOverview as $station): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($station['station_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($station['county']); ?></td>
                                        <td>KES <?php echo number_format($station['budget_allocated'], 0); ?></td>
                                        <td>KES <?php echo number_format($station['total_spent'], 0); ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $station['utilization_rate'] > 90 ? 'danger' : ($station['utilization_rate'] > 70 ? 'warning' : 'success'); ?>">
                                                <?php echo $station['utilization_rate'] ?? 0; ?>%
                                            </span>
                                        </td>
                                        <td><?php echo $station['officer_count']; ?></td>
                                        <td><?php echo $station['case_count']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline btn-primary" 
                                                    onclick="viewStationDetails(<?php echo $station['station_id']; ?>, '<?php echo htmlspecialchars($station['station_name'], ENT_QUOTES); ?>')">
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
                        <div style="font-size: 3rem;">💰</div>
                        <h4>No Budget Data</h4>
                        <p class="text-muted">Start by allocating budgets to stations.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        </main>
    </div>
    
    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>
        function viewStationDetails(stationId, stationName) {
            // For now, show an alert. In a real app, this could open a modal or navigate to a detail page
            alert(`Detailed budget breakdown for ${stationName} would be displayed here.\n\nThis could include:\n• Category-wise allocation and spending\n• Monthly expenditure trends\n• Comparison with previous years\n• Detailed transaction history`);
        }
        
        // Auto-calculate total allocation
        document.querySelectorAll('input[type="number"]').forEach(input => {
            if (input.name !== 'amount' && input.name !== 'station_id') {
                input.addEventListener('input', calculateTotal);
            }
        });
        
        function calculateTotal() {
            const form = document.querySelector('form[action="allocate_budget"]');
            if (!form) return;
            
            let total = 0;
            form.querySelectorAll('input[type="number"]').forEach(input => {
                if (input.value && input.name !== 'station_id') {
                    total += parseFloat(input.value) || 0;
                }
            });
            
            // You could display this total somewhere
            console.log('Total allocation:', total);
        }
    </script>
</body>
</html>