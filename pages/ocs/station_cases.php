<?php
define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';
require_once __DIR__ . '/../../includes/classes/Station.php';

requireRole(ROLE_OCS);

$currentUser = getCurrentUser();
$stationId = $currentUser['station_id'];
$station = new Station($stationId);

// Handle filters from GET parameters
$filters = [
    'status' => $_GET['status'] ?? 'all',
    'category' => $_GET['category'] ?? '',
    'officer_id' => $_GET['officer'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Handle search
$searchTerm = $_GET['search'] ?? '';

// Initialize data
$cases = [];
$caseStats = [];
$officers = [];
$categories = [];
$error = '';

try {
    // Get filtered cases
    if ($searchTerm) {
        $cases = $station->searchCases($searchTerm, $filters);
    } else {
        $cases = $station->getCases($filters);
    }
    
    // Get case statistics (filtered)
    $caseStats = $station->getCaseStatistics();
    
    // Get officers and categories for dropdowns
    $officers = $station->getOfficers();
    $categories = $station->getCaseCategories();
    
    // Get performance metrics
    $performanceData = $station->getPerformanceMetrics(30);
    
} catch (Exception $e) {
    error_log("Station Cases Error: " . $e->getMessage());
    $error = "Unable to load case data";
}

$pageTitle = "Station Cases";
require_once __DIR__ . '/../../includes/layout/layout.php';

?>
  <main class="app-main">
        <div class="mb-4">
            <h1>Station Cases</h1>
            <p class="text-muted">Manage and monitor all cases at <?php echo htmlspecialchars($station->getStationData('name')); ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Search and Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Search & Filter Cases</h3>
            </div>
            <div class="card-body">
                <!-- Search Bar -->
                <form method="GET" class="mb-3">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search by OB number, title, description, or reporter name..." 
                            value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <?php if ($searchTerm): ?>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                    <!-- Preserve other filters in search -->
                    <?php foreach ($filters as $key => $value): ?>
                        <?php if ($value && $value !== 'all'): ?>
                            <input type="hidden" name="<?php echo htmlspecialchars($key === 'officer_id' ? 'officer' : $key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </form>
                
                <!-- Filters -->
                <form method="GET" class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <!-- Preserve search term -->
                    <?php if ($searchTerm): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <?php endif; ?>
                    
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
                    
                    <div style="display: flex; flex-direction: column; justify-content: end;">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Case Statistics -->
        <div class="kpi-grid mb-4">
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $caseStats['total_cases'] ?? 0; ?></div>
                <div class="kpi-label">Total Cases</div>
                <div class="kpi-change">
                    <?php if ($searchTerm || array_filter($filters)): ?>
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
            
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $caseStats['reported_cases'] ?? 0; ?></div>
                <div class="kpi-label">Unassigned</div>
                <div class="kpi-change">
                    <?php if (($caseStats['reported_cases'] ?? 0) > 0): ?>
                        <span class="negative">Needs attention</span>
                    <?php else: ?>
                        <span class="positive">All assigned</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $caseStats['resolution_rate'] ?? 0; ?>%</div>
                <div class="kpi-label">Resolution Rate</div>
                <div class="kpi-change">
                    Avg Time: <span class="neutral"><?php echo round($caseStats['avg_resolution_time'] ?? 0, 1); ?>h</span>
                </div>
            </div>
        </div>

        <!-- Performance Alert -->
        <?php if (isset($performanceData['overdue_cases']) && count($performanceData['overdue_cases']) > 0): ?>
            <div class="alert alert-warning mb-4">
                <strong>⚠️ Performance Alert:</strong> 
                <?php echo count($performanceData['overdue_cases']); ?> cases are overdue their estimated resolution time.
                <a href="?status=in_progress" class="btn btn-sm btn-outline btn-primary ml-2">View Overdue Cases</a>
            </div>
        <?php endif; ?>

        <!-- Cases Table -->
        <div class="card">
            <div class="card-header">
                <h3>Cases</h3>
                <div class="d-flex gap-2">
                    <?php if ($searchTerm): ?>
                        <span class="badge status-info">Search: "<?php echo htmlspecialchars($searchTerm); ?>"</span>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/pages/officer/record_case.php" class="btn btn-sm btn-success">
                        Add New Case
                    </a>
                </div>
            </div>
        <div class="card-body">
                <?php if (!empty($cases)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>OB Number</th>
                                    <th>Case Details</th>
                                    <th>Reporter</th>
                                    <th>Assigned Officer</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cases as $case): ?>
                                    <?php
                                    $isOverdue = $case['hours_since_reported'] > $case['estimated_resolution_hours'];
                                    $rowClass = $isOverdue && !in_array($case['status'], ['resolved', 'closed']) ? 'table-warning' : '';
                                    ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($case['ob_number']); ?></strong>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($case['title']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($case['category']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($case['reporter_name']); ?></td>
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
                                            <div><?php echo round($case['hours_since_reported']); ?>h ago</div>
                                            <?php if ($isOverdue && !in_array($case['status'], ['resolved', 'closed'])): ?>
                                                <small class="text-danger">Overdue</small>
                                            <?php else: ?>
                                                <small class="text-muted">
                                                    <?php if ($case['status'] === 'closed' && $case['actual_resolution_hours']): ?>
                                                        Resolved in <?php echo round($case['actual_resolution_hours']); ?>h
                                                    <?php elseif (!in_array($case['status'], ['resolved', 'closed'])): ?>
                                                        <?php echo max(0, $case['estimated_resolution_hours'] - $case['hours_since_reported']); ?>h left
                                                    <?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="<?php echo BASE_URL; ?>/pages/officer/update_case.php?id=<?php echo $case['id']; ?>" 
                                                class="btn btn-sm btn-primary">View</a>
                                                <?php if (!in_array($case['status'], ['resolved', 'closed'])): ?>
                                                    <a href="<?php echo BASE_URL; ?>/pages/officer/evidence.php?case_id=<?php echo $case['id']; ?>" 
                                                    class="btn btn-sm btn-outline btn-secondary">Evidence</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Results Summary -->
                    <div class="mt-3">
                        <small class="text-muted">
                            Showing <?php echo count($cases); ?> cases
                            <?php if ($searchTerm): ?>
                                for search term "<?php echo htmlspecialchars($searchTerm); ?>"
                            <?php endif; ?>
                            <?php if (array_filter($filters, function($v) { return $v && $v !== 'all'; })): ?>
                                with applied filters
                            <?php endif; ?>
                        </small>
                    </div>
                    
                <?php else: ?>
                    <div class="text-center p-4">
                        <div style="font-size: 3rem;">🔍</div>
                        <h4>No Cases Found</h4>
                        <?php if ($searchTerm): ?>
                            <p class="text-muted">No cases match your search term "<?php echo htmlspecialchars($searchTerm); ?>".</p>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline btn-primary">Clear Search</a>
                        <?php elseif (array_filter($filters, function($v) { return $v && $v !== 'all'; })): ?>
                            <p class="text-muted">No cases match your current filters.</p>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline btn-primary">Clear Filters</a>
                        <?php else: ?>
                            <p class="text-muted">No cases have been recorded at this station yet.</p>
                            <a href="<?php echo BASE_URL; ?>/pages/officer/record_case.php" class="btn btn-primary">Record First Case</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        </main>
    </div>
    
    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>
        // Auto-refresh every 3 minutes
        setInterval(function() {
            if (!document.hidden) {
                location.reload();
            }
        }, 180000);
        
        // Highlight overdue cases
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.table-warning').forEach(row => {
                row.style.borderLeft = '4px solid var(--warning-orange)';
            });
            
            // Add tooltips for status badges
            document.querySelectorAll('.badge').forEach(badge => {
                const status = badge.textContent.toLowerCase();
                switch(status) {
                    case 'reported':
                        badge.title = 'Case reported but not yet assigned to an officer';
                        break;
                    case 'assigned':
                        badge.title = 'Case assigned to an officer but work not started';
                        break;
                    case 'in progress':
                        badge.title = 'Officer is actively working on this case';
                        break;
                    case 'resolved':
                        badge.title = 'Case has been resolved but not yet closed';
                        break;
                    case 'closed':
                        badge.title = 'Case is completely closed';
                        break;
                }
            });
            
            // Enhance search functionality
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        this.form.submit();
                    }
                });
            }
            
            // Form enhancement for better UX
            const filterForm = document.querySelector('form[method="GET"]:not(:first-child)');
            if (filterForm) {
                filterForm.addEventListener('change', function(e) {
                    if (e.target.matches('select')) {
                        // Optional: Auto-submit on select change
                        // this.submit();
                    }
                });
            }
        });
        
        // Export functionality (basic implementation)
        function exportCases() {
            const table = document.querySelector('.table');
            if (!table) return;
            
            let csv = 'OB Number,Title,Category,Reporter,Officer,Status,Hours Since Reported\n';
            
            table.querySelectorAll('tbody tr').forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 6) {
                    const obNumber = cells[0].textContent.trim();
                    const title = cells[1].querySelector('div').textContent.trim();
                    const category = cells[1].querySelector('small').textContent.trim();
                    const reporter = cells[2].textContent.trim();
                    const officer = cells[3].textContent.trim();
                    const status = cells[4].textContent.trim();
                    const time = cells[5].querySelector('div').textContent.trim();
                    
                    csv += `"${obNumber}","${title}","${category}","${reporter}","${officer}","${status}","${time}"\n`;
                }
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `station_cases_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        // Add export button to header (if needed)
        document.addEventListener('DOMContentLoaded', function() {
            const cardHeader = document.querySelector('.card:last-child .card-header');
            if (cardHeader && document.querySelectorAll('.table tbody tr').length > 0) {
                const exportBtn = document.createElement('button');
                exportBtn.innerHTML = '📊 Export CSV';
                exportBtn.className = 'btn btn-sm btn-outline btn-secondary';
                exportBtn.onclick = exportCases;
                cardHeader.querySelector('div').appendChild(exportBtn);
            }
        });
        
        // Performance monitoring
        if (performance.navigation) {
            const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
            if (loadTime > 3000) {
                console.warn('Page load time is slow:', loadTime + 'ms');
            }
        }
    </script>
    
    <style>
        .table-warning {
            background-color: rgba(255, 193, 7, 0.1);
        }
        
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        .badge:hover {
            transform: scale(1.05);
            transition: transform 0.1s ease;
        }
        
        .search-highlight {
            background-color: yellow;
            padding: 1px 2px;
            border-radius: 2px;
        }
        
        @media (max-width: 768px) {
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .table-responsive {
                font-size: 0.85rem;
            }
            
            .d-flex.gap-1 {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .btn-sm {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header h3 {
                font-size: 1.1rem;
            }
            
            .card-header .d-flex {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start;
            }
        }
        
        /* Print styles */
        @media print {
            .btn, .card-header .btn, .no-print {
                display: none !important;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .table {
                font-size: 0.8rem;
            }
            
            .kpi-grid {
                display: none;
            }
        }
    </style>
</body>
</html>