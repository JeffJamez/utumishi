<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/validation.php';
require_once __DIR__ . '/../../includes/utils/sanitization.php';
require_once __DIR__ . '/../../includes/classes/CaseManager.php';

requireRole(ROLE_CITIZEN);

$currentUser = getCurrentUser();
$caseManager = new CaseManager();

$searchResults = null;
$caseDetails = null;
$caseUpdates = null;
$error = '';
$searchTerm = '';

if (!empty($_GET['ob']) || !empty($_POST['search_term'])) {
    $searchTerm = sanitizeText($_GET['ob'] ?? $_POST['search_term'] ?? '');

    if (!empty($searchTerm)) {
        try {

            if (preg_match('/^OB-[A-Z]{3}-\d{4}-\d{5}$/', strtoupper($searchTerm))) {
                $caseDetails = $caseManager->getCaseByOBNumber(strtoupper($searchTerm));

                if ($caseDetails) {

                    if ($caseDetails['reported_by_citizen_id'] != $currentUser['id']) {
                        $error = 'You can only view cases that you reported. This case belongs to another citizen.';
                        $caseDetails = null;
                    } else {

                        $caseUpdates = $caseManager->getCaseUpdates($caseDetails['id']);
                    }
                } else {
                    $error = 'Case not found. Please check the OB number and try again.';
                }
            } else {
                $error = 'Invalid OB number format. Please use format: OB-XXX-YYYY-NNNNN';
            }

        } catch (Exception $e) {
            error_log("Case Tracking Error: " . $e->getMessage());
            $error = 'Unable to search for case. Please try again.';
        }
    }
} else {

    try {
        $searchResults = $caseManager->getCasesForCitizen($currentUser['id'], 20);
    } catch (Exception $e) {
        error_log("Get Cases Error: " . $e->getMessage());
        $error = 'Unable to load your cases.';
    }
}

$pageTitle = "Track My Case";

require_once __DIR__ . '/../../includes/layout/layout.php';
?>

        <main class="app-main">
            <?php flashMessage(); ?>

            <div class="mb-4">
                <h1>Track My Case</h1>
                <p class="text-muted">Search for your case using the OB number or browse your reported cases</p>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h3>🔍 Search by OB Number</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="d-flex gap-2" style="align-items: flex-end;">
                        <div class="form-group mb-0" style="flex: 1;">
                            <label for="search_term" class="form-label">OB Number</label>
                            <input 
                                type="text" 
                                id="search_term" 
                                name="search_term" 
                                class="form-control"
                                placeholder="e.g., OB-NRB-2025-00123"
                                value="<?php echo htmlspecialchars($searchTerm); ?>"
                                pattern="OB-[A-Z]{3}-\d{4}-\d{5}"
                                maxlength="20"
                            >
                            <div class="form-help">Enter the complete OB number from your case report</div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            🔍 Search Case
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($caseDetails): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Case Details - <?php echo htmlspecialchars($caseDetails['ob_number']); ?></h3>
                        <span class="badge <?php echo STATUS_COLORS[$caseDetails['status']] ?? 'status-reported'; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $caseDetails['status'])); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="d-grid" style="grid-template-columns: 2fr 1fr; gap: 2rem;">
                            <div>
                                <h4><?php echo htmlspecialchars($caseDetails['title']); ?></h4>

                                <div class="mb-3">
                                    <strong>Case Description:</strong>
                                    <div class="mt-1 p-2" style="background: var(--light-gray); border-radius: var(--border-radius); white-space: pre-line;">
                                        <?php echo htmlspecialchars($caseDetails['description']); ?>
                                    </div>
                                </div>

                                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div>
                                        <strong>Crime Category:</strong><br>
                                        <span class="badge status-assigned"><?php echo htmlspecialchars($caseDetails['category']); ?></span>
                                    </div>
                                    <div>
                                        <strong>Location:</strong><br>
                                        <?php echo htmlspecialchars($caseDetails['location_constituency']); ?>, 
                                        <?php echo htmlspecialchars($caseDetails['location_county']); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="case-info-panel">
                                <div class="mb-3">
                                    <strong>Current Status:</strong><br>
                                    <span class="badge <?php echo STATUS_COLORS[$caseDetails['status']] ?? 'status-reported'; ?>" style="font-size: 1rem;">
                                        <?php echo ucfirst(str_replace('_', ' ', $caseDetails['status'])); ?>
                                    </span>
                                </div>

                                <div class="mb-3">
                                    <strong>Assigned Officer:</strong><br>
                                    <?php if ($caseDetails['assigned_officer_name']): ?>
                                        <?php echo htmlspecialchars($caseDetails['assigned_officer_name']); ?><br>
                                        <small class="text-muted">Badge: <?php echo htmlspecialchars($caseDetails['badge_number']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Not yet assigned</span>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <strong>Station:</strong><br>
                                    <?php echo htmlspecialchars($caseDetails['station_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($caseDetails['station_county']); ?></small>
                                </div>

                                <div class="mb-3">
                                    <strong>Date Reported:</strong><br>
                                    <?php echo date('M d, Y \a\t H:i', strtotime($caseDetails['created_at'])); ?>
                                </div>

                                <div class="mb-3">
                                    <strong>Last Updated:</strong><br>
                                    <?php echo date('M d, Y \a\t H:i', strtotime($caseDetails['updated_at'])); ?>
                                </div>

                                <?php if ($caseDetails['status'] === 'closed' && $caseDetails['closed_at']): ?>
                                    <div class="mb-3">
                                        <strong>Date Closed:</strong><br>
                                        <?php echo date('M d, Y \a\t H:i', strtotime($caseDetails['closed_at'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($caseUpdates)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>📋 Case Timeline & Updates</h3>
                        </div>
                        <div class="card-body">
                            <div class="case-timeline">
                                <?php foreach (array_reverse($caseUpdates) as $update): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-date">
                                            <?php echo date('M d, Y \a\t H:i', strtotime($update['created_at'])); ?>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-between items-center mb-2">
                                                <strong>
                                                    Status: <?php echo ucfirst(str_replace('_', ' ', $update['status_before'])); ?> 
                                                    → <?php echo ucfirst(str_replace('_', ' ', $update['status_after'])); ?>
                                                </strong>
                                                <small class="text-muted">
                                                    by <?php echo htmlspecialchars($update['officer_name']); ?>
                                                    <?php if ($update['badge_number']): ?>
                                                        (<?php echo htmlspecialchars($update['badge_number']); ?>)
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <div><?php echo nl2br(htmlspecialchars($update['update_text'])); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <div class="timeline-item">
                                    <div class="timeline-date">
                                        <?php echo date('M d, Y \a\t H:i', strtotime($caseDetails['created_at'])); ?>
                                    </div>
                                    <div class="timeline-content">
                                        <strong>Case Reported</strong><br>
                                        Initial report filed at <?php echo htmlspecialchars($caseDetails['station_name']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <p class="text-muted">No updates available for this case yet.</p>
                            <p><small>Updates will appear here as the investigation progresses.</small></p>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif (!empty($searchResults)): ?>

                <div class="card">
                    <div class="card-header">
                        <h3>📋 My Reported Cases</h3>
                        <span class="text-muted"><?php echo count($searchResults); ?> case(s) found</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($searchResults)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>OB Number</th>
                                            <th>Case Details</th>
                                            <th>Status</th>
                                            <th>Assigned Officer</th>
                                            <th>Date Reported</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($searchResults as $case): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($case['ob_number']); ?></strong>
                                                </td>
                                                <td>
                                                    <div class="mb-1">
                                                        <strong><?php echo htmlspecialchars($case['title']); ?></strong>
                                                    </div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($case['category']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo STATUS_COLORS[$case['status']] ?? 'status-reported'; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($case['assigned_officer']): ?>
                                                        <?php echo htmlspecialchars($case['assigned_officer']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($case['created_at'])); ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php
                                                        $daysAgo = floor((time() - strtotime($case['created_at'])) / (24 * 60 * 60));
                                                        echo $daysAgo . ' day' . ($daysAgo != 1 ? 's' : '') . ' ago';
                                                        ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <a href="<?php echo BASE_URL; ?>/pages/citizen/track_case.php?ob=<?php echo urlencode($case['ob_number']); ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        View Details
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <div style="font-size: 3rem;">📋</div>
                                <h4>No Cases Found</h4>
                                <p class="text-muted">You haven't reported any cases yet.</p>
                                <p><small>Cases are reported in-person at police stations. Once reported, they will appear here for tracking.</small></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif (empty($searchTerm)): ?>

                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🔍</div>
                        <h3>Track Your Cases</h3>
                        <p class="text-muted mb-4">
                            Use the search box above to find a specific case by OB number, or view all your reported cases below.
                        </p>

                        <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 2rem;">
                            <div class="alert alert-info">
                                <h5>📝 How to Get Your OB Number</h5>
                                <p class="mb-0">When you report a case at any police station, you'll receive an OB number. Keep this number safe for tracking your case.</p>
                            </div>

                            <div class="alert alert-success">
                                <h5>🔄 Case Status Updates</h5>
                                <p class="mb-0">Your case status will be updated as investigation progresses: Reported → Assigned → In Progress → Resolved → Closed</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>❓ Need Help?</h3>
                </div>
                <div class="card-body">
                    <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        <div>
                            <h5>📞 Contact Information</h5>
                            <p><strong>Emergency:</strong> 999 or 911</p>
                            <p><strong>Police Hotline:</strong> 999</p>
                            <p><strong>DCI Hotline:</strong> 0800 722 203</p>
                        </div>

                        <div>
                            <h5>🏢 Visit Police Station</h5>
                            <p>For case updates, evidence submission, or statements, visit the station handling your case.</p>
                            <p><strong>Bring:</strong> National ID and OB Number</p>
                        </div>

                        <div>
                            <h5>📋 Case Status Meanings</h5>
                            <ul style="font-size: 0.9rem;">
                                <li><strong>Reported:</strong> Case recorded, awaiting assignment</li>
                                <li><strong>Assigned:</strong> Officer assigned to investigate</li>
                                <li><strong>In Progress:</strong> Active investigation ongoing</li>
                                <li><strong>Resolved:</strong> Investigation complete</li>
                                <li><strong>Closed:</strong> Case officially closed</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>

        document.getElementById('search_term').addEventListener('input', function(e) {
            let value = this.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '');

            if (value.length >= 2 && !value.startsWith('OB-')) {
                if (value.startsWith('OB')) {
                    value = 'OB-' + value.substring(2);
                }
            }

            this.value = value;
        });

        document.querySelector('form').addEventListener('submit', function(e) {
            const searchTerm = document.getElementById('search_term').value.trim();

            if (!searchTerm) {
                e.preventDefault();
                alert('Please enter an OB number to search');
                document.getElementById('search_term').focus();
                return false;
            }

            const obPattern = /^OB-[A-Z]{3}-\d{4}-\d{5}$/;
            if (!obPattern.test(searchTerm)) {
                e.preventDefault();
                alert('Please enter a valid OB number format: OB-XXX-YYYY-NNNNN\n\nExample: OB-NRB-2025-00123');
                document.getElementById('search_term').focus();
                return false;
            }
        });

        <?php if ($caseDetails): ?>
        setInterval(function() {

            <?php if ($caseDetails['status'] !== 'closed'): ?>
                location.reload();
            <?php endif; ?>
        }, 120000);
        <?php endif; ?>

        function printCaseDetails() {
            window.print();
        }

        <?php if ($caseDetails): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const caseHeader = document.querySelector('.card-header h3');
            if (caseHeader) {
                const printBtn = document.createElement('button');
                printBtn.innerHTML = '🖨️ Print';
                printBtn.className = 'btn btn-sm btn-outline btn-secondary no-print';
                printBtn.onclick = printCaseDetails;
                caseHeader.parentNode.appendChild(printBtn);
            }
        });
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('tbody tr').forEach(row => {
                const statusBadge = row.querySelector('.badge');
                const dateCell = row.cells[4];

                if (statusBadge && dateCell) {
                    const reportedDate = dateCell.textContent.trim();
                    const dayMatch = reportedDate.match(/(\d+) days? ago/);

                    if (dayMatch && parseInt(dayMatch[1]) > 7) {
                        row.style.borderLeft = '4px solid var(--warning-orange)';
                        row.title = 'Case reported more than 7 days ago';
                    }

                    if (statusBadge.classList.contains('status-reported') && dayMatch && parseInt(dayMatch[1]) > 2) {
                        statusBadge.style.animation = 'pulse 2s infinite';
                        statusBadge.title = 'Case awaiting assignment for ' + dayMatch[1] + ' days';
                    }
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('search_term').focus();
                document.getElementById('search_term').select();
            }
        });

        function copyOBNumber(obNumber) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(obNumber).then(function() {
                    alert('OB Number copied to clipboard: ' + obNumber);
                });
            } else {

                const textArea = document.createElement('textarea');
                textArea.value = obNumber;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('OB Number copied to clipboard: ' + obNumber);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('td:first-child strong').forEach(obElement => {
                obElement.style.cursor = 'pointer';
                obElement.title = 'Click to copy OB number';
                obElement.addEventListener('click', function() {
                    copyOBNumber(this.textContent);
                });
            });
        });

        if (window.location.href.includes('ob=')) {
            const main = document.querySelector('.app-main');
            main.style.opacity = '0.8';

            window.addEventListener('load', function() {
                main.style.opacity = '1';
            });
        }

        function saveSearchTerm(term) {
            if (term && term.match(/^OB-[A-Z]{3}-\d{4}-\d{5}$/)) {
                let searches = JSON.parse(localStorage.getItem('recentOBSearches') || '[]');
                searches = searches.filter(s => s !== term);
                searches.unshift(term);
                searches = searches.slice(0, 5);
                localStorage.setItem('recentOBSearches', JSON.stringify(searches));
            }
        }

        function loadRecentSearches() {
            const searches = JSON.parse(localStorage.getItem('recentOBSearches') || '[]');
            const searchInput = document.getElementById('search_term');

            if (searches.length > 0) {
                const datalist = document.createElement('datalist');
                datalist.id = 'recentSearches';

                searches.forEach(search => {
                    const option = document.createElement('option');
                    option.value = search;
                    datalist.appendChild(option);
                });

                searchInput.setAttribute('list', 'recentSearches');
                document.body.appendChild(datalist);
            }
        }

        document.querySelector('form').addEventListener('submit', function() {
            const searchTerm = document.getElementById('search_term').value.trim();
            saveSearchTerm(searchTerm);
        });

        loadRecentSearches();

        document.addEventListener('DOMContentLoaded', function() {
            const badges = document.querySelectorAll('.badge');
            badges.forEach(badge => {
                if (badge.textContent.includes('In Progress') || badge.textContent.includes('Resolved')) {
                    badge.style.animation = 'statusPulse 3s ease-in-out infinite';
                }
            });
        });
    </script>

    <style>
        .case-timeline {
            position: relative;
            padding-left: 2rem;
        }

        .case-timeline::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--primary-green);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            background: var(--primary-white);
            padding: 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--light-gray);
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 1rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-green);
            border: 3px solid var(--primary-white);
        }

        .timeline-item:last-child::before {
            background: var(--medium-gray);
        }

        .timeline-date {
            font-size: 0.875rem;
            color: var(--medium-gray);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .timeline-content {
            color: var(--dark-gray);
            line-height: 1.5;
        }

        .case-info-panel {
            background: var(--light-gray);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-green);
        }

        .case-info-panel strong {
            color: var(--dark-gray);
            display: block;
            margin-bottom: 0.25rem;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        @keyframes statusPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @media print {
            .app-header, .app-sidebar, .btn, .no-print, .form-help {
                display: none !important;
            }

            .app-layout {
                grid-template-areas: "main";
                grid-template-columns: 1fr;
            }

            .card {
                break-inside: avoid;
                border: 1px solid #000;
                box-shadow: none;
            }

            .timeline-item {
                break-inside: avoid;
            }

            .badge {
                border: 1px solid #000;
                color: #000 !important;
                background: transparent !important;
            }
        }

        @media (max-width: 768px) {
            .d-grid[style*="2fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            .case-info-panel {
                margin-top: 1rem;
            }

            .d-flex.gap-2 {
                flex-direction: column;
                gap: 1rem;
            }

            .table-responsive {
                font-size: 0.85rem;
            }

            .timeline-item {
                padding: 0.75rem;
                margin-bottom: 1rem;
            }
        }

        .btn:focus, .form-control:focus {
            box-shadow: 0 0 0 3px rgba(0, 107, 63, 0.25);
        }

        .table tr:hover {
            background-color: rgba(0, 107, 63, 0.05);
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: fixed;
            top: 50%;
            left: 50%;
            width: 40px;
            height: 40px;
            margin: -20px 0 0 -20px;
            border: 3px solid var(--light-gray);
            border-top: 3px solid var(--primary-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            z-index: 1000;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .timeline-item.recent {
            border-left: 4px solid var(--primary-green);
            background: rgba(0, 107, 63, 0.05);
        }

        [data-copyable] {
            cursor: pointer;
            transition: var(--transition);
        }

        [data-copyable]:hover {
            color: var(--primary-green);
            text-decoration: underline;
        }
    </style>
</body>
</html>
