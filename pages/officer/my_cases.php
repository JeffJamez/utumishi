<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';
require_once __DIR__ . '/../../includes/classes/Officer.php';
require_once __DIR__ . '/../../includes/classes/CaseManager.php';

requireRole(ROLE_OFFICER);

$currentUser = getCurrentUser();
$officer = new Officer($currentUser['id']);
$caseManager = new CaseManager();

$caseFilter = sanitizeText($_GET['filter'] ?? 'assigned');

try {
    if ($caseFilter === 'recorded') {
        $myCases = $officer->getRecordedCases();
    } else {
        $myCases = $officer->getAssignedCases();
    }

} catch (Exception $e) {
    error_log("My Cases Error: " . $e->getMessage());
    $error = "Unable to load cases";
    $myCases = [];
}

$pageTitle = "My Cases";

require_once __DIR__ . '/../../includes/layout/layout.php';

?>
        <main class="app-main">
            <?php flashMessage(); ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <h2>My Cases</h2>
                <p class="text-muted">Manage and track all cases you recorded /  assigned to you</p>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h3>Filter Cases</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?php echo BASE_URL; ?>/pages/officer/my_cases.php?filter=assigned"
                           class="btn btn-<?php echo $caseFilter === 'assigned' ? 'primary' : 'outline btn-secondary'; ?>">
                            Assigned
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/officer/my_cases.php?filter=recorded"
                           class="btn btn-<?php echo $caseFilter === 'recorded' ? 'primary' : 'outline btn-secondary'; ?>">
                            Recorded
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                 <div class="card-header">
                                     <h3><?php echo ucfirst($caseFilter); ?> Cases</h3>
                     <span class="badge status-progress"><?php echo count($myCases); ?> case(s)</span>
                 </div>

                 <div class="card-body">
                     <?php if (!empty($myCases)): ?>
                         <div class="table-responsive">
                             <table class="table">
                                 <thead>
                                     <tr>
                                         <th>OB Number</th>
                                          <th>Title</th>
                                          <th>Reporter</th>
                                          <th>Status</th>
                                          <th>Actions</th>
                                     </tr>
                                 </thead>
                                 <tbody>
                                     <?php foreach ($myCases as $case): ?>
                                         <tr>
                                             <td><?php echo htmlspecialchars($case['ob_number'] ?? 'N/A'); ?></td>
                                             <td><?php echo htmlspecialchars($case['title'] ?? 'No title'); ?></td>
                                             <td>
                                                 <?php if (!empty($case['reporter_anonymized'])): ?>
                                                     <span style="color: #dc3545; font-weight: bold;">ANONYMIZED</span>
                                                 <?php else: ?>
                                                     <?php echo htmlspecialchars($case['reporter_name'] ?? 'Unknown'); ?>
                                                 <?php endif; ?>
                                             </td>
                                         <td>
                                                  <span class="badge <?php echo STATUS_COLORS[$case['status']] ?? 'status-reported'; ?>">
                                                      <?php echo ucfirst(str_replace('_', ' ', $case['status'] ?? 'unknown')); ?>
                                                  </span>
                                              </td>
                                              <td>
                                                  <div class="d-flex flex-column gap-1">
                                                      <a href="<?php echo BASE_URL; ?>/pages/officer/view_case.php?id=<?php echo $case['id']; ?>"
                                                         class="btn btn-sm btn-outline btn-secondary">
                                                          View
                                                      </a>
                                                      <?php if ($caseFilter === 'assigned'): ?>
                                                          <a href="<?php echo BASE_URL; ?>/pages/officer/update_case.php?id=<?php echo $case['id']; ?>"
                                                             class="btn btn-sm btn-primary">
                                                              Update
                                                          </a>
                                                      <?php endif; ?>
                                                  </div>
                                              </td>
                                         </tr>
                                     <?php endforeach; ?>
                                 </tbody>
                             </table>
                         </div>
                     <?php else: ?>
                     <div class="text-center p-4">
                              <h4>No Cases Found</h4>
                              <p class="text-muted">No cases match the selected filter.</p>
                              <a href="<?php echo BASE_URL; ?>/pages/officer/dashboard.php" class="btn btn-primary">
                                  Return to Dashboard
                              </a>
                          </div>
                     <?php endif; ?>
                 </div>
             </div>
        </main>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>

        setInterval(function() {
            if (!document.hidden) {
                location.reload();
            }
        }, 120000);

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.table-danger').forEach(row => {
                row.style.borderLeft = '4px solid var(--danger-red)';
            });

            document.querySelectorAll('.table-warning').forEach(row => {
                row.style.borderLeft = '4px solid var(--warning-orange)';
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'a':
                        e.preventDefault();
                        window.location.href = '<?php echo BASE_URL; ?>/pages/officer/my_cases.php?filter=assigned';
                        break;
                    case 'r':
                        e.preventDefault();
                        window.location.href = '<?php echo BASE_URL; ?>/pages/officer/my_cases.php?filter=recorded';
                        break;
                }
            }
        });
    </script>

    <style>
        .table-danger {
            background-color: rgba(220, 53, 69, 0.1) !important;
        }

        .table-warning {
            background-color: rgba(255, 193, 7, 0.1) !important;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .d-flex.gap-2 {
                flex-direction: column;
            }

            .d-flex.flex-column.gap-1 {
                flex-direction: row;
                gap: 0.25rem;
            }
        }
    </style>
</body>
</html>
