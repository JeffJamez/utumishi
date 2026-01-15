<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

$navigation_menus = [
    'officer' => [
        [
            'title' => 'Dashboard', 
            'url' => '/pages/officer/dashboard.php', 
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>'
        ],
        [
            'title' => 'Record New Case', 
            'url' => '/pages/officer/record_case.php', 
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>'
        ],
        [
            'title' => 'My Cases',
            'url' => '/pages/officer/my_cases.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>'
        ],
        [
            'title' => 'Search Cases',
            'url' => '/pages/officer/search_case.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>'
        ],

        [
            'title' => 'Evidence Management', 
            'url' => '/pages/officer/evidence.php', 
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>'
        ],
        [
            'title' => 'My Profile', 
            'url' => '/pages/officer/profile.php', 
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>'
        ]
    ],

    'admin' => [
        [
            'title' => 'Dashboard',
            'url' => '/pages/cc/dashboard.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>'
        ],
        [
            'title' => 'Manage Officers',
            'url' => '/pages/cc/manage_officers.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>'
        ],
        [
            'title' => 'Manage Stations',
            'url' => '/pages/cc/manage_stations.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>'
        ],

        [
            'title' => 'County Reports',
            'url' => '/pages/cc/county_reports.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>'
        ],
    ],

    'county_commander' => [
        [
            'title' => 'Dashboard',
            'url' => '/pages/cc/dashboard.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>'
        ],
        [
            'title' => 'Officers Overview',
            'url' => '/pages/cc/manage_officers.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>'
        ],
        [
            'title' => 'Stations Overview',
            'url' => '/pages/cc/manage_stations.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>'
        ],
        [
            'title' => 'Search Case',
            'url' => '/pages/cc/case_search.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>'
        ],
        [
            'title' => 'Crime Density',
            'url' => '/pages/cc/crime_density.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>'
        ],
        [
            'title' => 'Predictive Analysis',
            'url' => '/pages/cc/predictive_analysis.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path></svg>'
        ],
        [
            'title' => 'County Reports',
            'url' => '/pages/cc/county_reports.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>'
        ],
    ],

    'ocs' => [
        [
            'title' => 'Dashboard', 
            'url' => '/pages/ocs/dashboard.php', 
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>'
        ],
        [
            'title' => 'Crime Density', 
            'url' => '/pages/ocs/crime_heatmap.php', 
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>'
        ],
         [
            'title' => 'Predictive Analysis', 
            'url' => '/pages/ocs/predictive_analytics.php', 
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>'
        ],
        [
            'title' => 'Officer Management', 
            'url' => '/pages/ocs/officer_workload.php', 
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>'
        ],
        [
            'title' => 'Station Cases', 
            'url' => '/pages/ocs/station_cases.php', 
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>'
        ],
       

        [
            'title' => 'Reports', 
            'url' => '/pages/ocs/reports.php', 
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>'
        ]
    ],

    'citizen' => [
        [
            'title' => 'Dashboard', 
            'url' => '/pages/citizen/dashboard.php', 
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>'
        ],
        [
            'title' => 'Track My Case',
            'url' => '/pages/citizen/track_case.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>'
        ],
    ]
];

function getCurrentPage() {
    return $_SERVER['REQUEST_URI'] ?? '';
}

function isActive($url) {
    return strpos(getCurrentPage(), $url) !== false ? 'active' : '';
}


function renderNavigation($role = null) {
    global $navigation_menus;

    if (!$role) {
        $role = $_SESSION['role'] ?? 'citizen';
    }

    if (!isset($navigation_menus[$role])) {
        echo '<p>No navigation available</p>';
        return;
    }

    $menu = $navigation_menus[$role];
    ?>
    <nav>
        <ul class="nav-menu">
            <?php foreach ($menu as $item): ?>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL . $item['url']; ?>" 
                       class="nav-link <?php echo isActive($item['url']); ?>">
                        <span class="nav-icon"><?php echo $item['icon']; ?></span>
                        <span class="nav-text"><?php echo $item['title']; ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php
}

function renderHeader($role = null, $currentUser = null) {
    if (!$role) {
        $role = $_SESSION['role'] ?? 'citizen';
    }

    if (!$currentUser && function_exists('getCurrentUser')) {
        $currentUser = getCurrentUser();
    } else if (!$currentUser) {
        $currentUser = [
            'name' => $_SESSION['user_name'] ?? 'User',
            'role' => $role,
            'station_id' => $_SESSION['station_id'] ?? null
        ];
    }

    $userName = $currentUser['name'] ?? 'User';
    $userRole = $currentUser['role'] ?? $role;
    $dashboardUrl = BASE_URL . "/pages/{$userRole}/dashboard.php";

    $stationInfo = null;
    if (in_array($userRole, [ROLE_OFFICER, ROLE_OCS]) && isset($currentUser['station_id']) && $currentUser['station_id']) {
        try {
            $db = Database::getInstance();
            $stationInfo = $db->fetchOne(
                "SELECT name, county FROM stations WHERE id = :id",
                ['id' => $currentUser['station_id']]
            );
        } catch (Exception $e) {
            error_log("Failed to fetch station info: " . $e->getMessage());
        }
    }

    $roleDisplay = [
        'officer' => 'Officer',
        'admin' => 'System Administrator',
        'county_commander' => 'County Commander', 
        'ocs' => 'OCS',
        'citizen' => 'Citizen'
    ];
    ?>
    
    <header class="app-header">
        <a href="<?php echo $dashboardUrl; ?>" class="header-brand">
            Utumishi
        </a>
        
        <div class="header-user">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="user-role <?php echo $userRole; ?>">
                    <?php echo $roleDisplay[$userRole] ?? ucfirst($userRole); ?>
                    <?php if ($stationInfo): ?>
                        • <?php echo htmlspecialchars($stationInfo['name']); ?>
                    <?php endif; ?>
                    <?php if ($userRole === ROLE_OFFICER && isset($currentUser['badge_number'])): ?>
                        • Badge: <?php echo htmlspecialchars($currentUser['badge_number']); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="header-actions">
                <div class="dropdown">
                    <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                       <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="m10.135 21l-.362-2.892q-.479-.145-1.035-.454q-.557-.31-.947-.664l-2.668 1.135l-1.865-3.25l2.306-1.739q-.045-.27-.073-.558q-.03-.288-.03-.559q0-.252.03-.53q.028-.278.073-.626L3.258 9.126l1.865-3.212L7.771 7.03q.448-.373.97-.673q.52-.3 1.013-.464L10.134 3h3.732l.361 2.912q.575.202 1.016.463t.909.654l2.725-1.115l1.865 3.211l-2.382 1.796q.082.31.092.569t.01.51q0 .233-.02.491q-.019.259-.088.626l2.344 1.758l-1.865 3.25l-2.681-1.154q-.467.393-.94.673t-.985.445L13.866 21zm1.838-6.5q1.046 0 1.773-.727T14.473 12t-.727-1.773t-1.773-.727q-1.052 0-1.776.727T9.473 12t.724 1.773t1.776.727"/></svg>
                    </button>
                    <div class="dropdown-menu">
                        <?php if ($userRole === ROLE_OFFICER): ?>
                            <a href="<?php echo BASE_URL; ?>/pages/officer/profile.php" class="dropdown-item">
                                My Profile
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo BASE_URL; ?>/pages/auth/change_password.php"  
                          class="dropdown-item"
                        >
                            Change Password
                        </a>
                        
                        <div class="dropdown-divider"></div>
                        
                        <a href="<?php echo BASE_URL; ?>/pages/auth/logout.php" class="dropdown-item text-danger">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <?php
}

function renderFlashMessage() {
    if (!function_exists('getFlashMessage')) {
        return;
    }
    
    $flashMessage = getFlashMessage();
    if (!$flashMessage) {
        return;
    }
    ?>
    
    <div class="flash-message flash-<?php echo $flashMessage['type']; ?>" id="flashMessage">
        <?php echo htmlspecialchars($flashMessage['message']); ?>
        <button onclick="closeFlashMessage()" class="flash-close">&times;</button>
    </div>
    
    <?php
}


function renderHeaderStyles() {
    ?>
    <style>
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--text-color);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .nav-link:hover {
            background-color: #f0f0f0;
            color: #333;
        }

        .nav-link.active {
            background-color: #006b3f;
            color: white;
        }

        .nav-icon {
            margin-right: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-text {
            font-weight: 500;
        }

        .dropdown {
            position: relative;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 160px;
            padding: 0.5rem 0;
            margin: 0;
            list-style: none;
        }

        .dropdown-item {
            display: block;
            padding: 0.5rem 1rem;
            color: #333;
            text-decoration: none;
        }

        .dropdown-item:hover {
            background-color: #f0f0f0;
            color: #333;
        }

        .dropdown-divider {
            height: 1px;
            margin: 0.25rem 0;
            background-color: #ddd;
        }

    </style>
    <?php
}

function renderAllStyles() {
    ?>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/dashboard.css">
    <?php renderHeaderStyles(); ?>
    <?php
}

function renderHeaderScripts() {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.querySelector('.dropdown-toggle');
            const dropdownMenu = document.querySelector('.dropdown-menu');

            if (dropdownToggle && dropdownMenu) {
                dropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
                });

                document.addEventListener('click', function(e) {
                    if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
                        dropdownMenu.style.display = 'none';
                    }
                });
            }
        });

        function closeFlashMessage() {
            const flashMessage = document.getElementById('flashMessage');
            if (flashMessage) {
                flashMessage.style.opacity = '0';
                setTimeout(() => flashMessage.remove(), 300);
            }
        }

        setTimeout(closeFlashMessage, 5000);
    </script>
    <?php
}


function renderCompleteHeader($role = null, $currentUser = null) {
    renderHeaderStyles();
    renderHeader($role, $currentUser);
    renderFlashMessage();
    renderHeaderScripts();
}

function addMenuItem($role, $title, $url, $icon) {
    global $navigation_menus;

    if (!isset($navigation_menus[$role])) {
        $navigation_menus[$role] = [];
    }

    $navigation_menus[$role][] = [
        'title' => $title,
        'url' => $url, 
        'icon' => $icon
    ];
}
?>