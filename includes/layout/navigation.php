<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

$navigation_menus = [
    'officer' => [
        ['title' => 'Dashboard', 'url' => '/pages/officer/dashboard.php', 'icon' => '🏠'],
        ['title' => 'Record New Case', 'url' => '/pages/officer/record_case.php', 'icon' => '➕'],
        ['title' => 'My Cases', 'url' => '/pages/officer/my_cases.php', 'icon' => '📋'],
        ['title' => 'Update Cases', 'url' => '/pages/officer/update_case.php', 'icon' => '✏️'],
        ['title' => 'Evidence Management', 'url' => '/pages/officer/evidence.php', 'icon' => '🔎'],
        ['title' => 'My Profile', 'url' => '/pages/officer/profile.php', 'icon' => '👤']
    ],

    'admin' => [
        ['title' => 'Dashboard', 'url' => '/pages/admin/dashboard.php', 'icon' => '🏠'],
        ['title' => 'Manage Officers', 'url' => '/pages/admin/manage_officers.php', 'icon' => '👮'],
        ['title' => 'Manage Stations', 'url' => '/pages/admin/manage_stations.php', 'icon' => '🏢'],
        ['title' => 'Budget Allocation', 'url' => '/pages/admin/budget_allocation.php', 'icon' => '💰'],
        ['title' => 'National Reports', 'url' => '/pages/admin/national_reports.php', 'icon' => '📊'],
    ],

    'ocs' => [
        ['title' => 'Dashboard', 'url' => '/pages/ocs/dashboard.php', 'icon' => '🏠'],
        ['title' => 'Crime Heatmap', 'url' => '/pages/ocs/crime_heatmap.php', 'icon' => '🗺️'],
        ['title' => 'Officer Management', 'url' => '/pages/ocs/officer_workload.php', 'icon' => '👥'],
        ['title' => 'Station Cases', 'url' => '/pages/ocs/station_cases.php', 'icon' => '📋'],
        ['title' => 'Predictive Analysis', 'url' => '/pages/ocs/predictive_analytics.php', 'icon' => '📋'],
        ['title' => 'Community Events', 'url' => '/pages/ocs/events.php', 'icon' => '📅'],
        ['title' => 'Reports', 'url' => '/pages/ocs/reports.php', 'icon' => '📊']
    ],

    'citizen' => [
        ['title' => 'Dashboard', 'url' => '/pages/citizen/dashboard.php', 'icon' => '🏠'],
        ['title' => 'Track My Case', 'url' => '/pages/citizen/track_case.php', 'icon' => '🔍'],
        ['title' => 'Crime Statistics', 'url' => '/pages/citizen/public_stats.php', 'icon' => '📊'],
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
        return '<p>No navigation available</p>';
    }

    $menu = $navigation_menus[$role];
    $html = '<nav><ul class="nav-menu">';

    foreach ($menu as $item) {
        $active = isActive($item['url']);
        $fullUrl = BASE_URL . $item['url'];

        $html .= '<li class="nav-item">';
        $html .= '<a href="' . $fullUrl . '" class="nav-link ' . $active . '" data-icon="' . $item['icon'] . '">';
        $html .= $item['title'];
        $html .= '</a>';
        $html .= '</li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

function renderHeader($role = null) {
    if (!$role) {
        $role = $_SESSION['role'] ?? 'citizen';
    }

    $userName = $_SESSION['user_name'] ?? 'User';
    $dashboardUrl = BASE_URL . "/pages/{$role}/dashboard.php";

    $roleDisplay = [
        'officer' => 'Officer',
        'admin' => 'System Administrator', 
        'ocs' => 'OCS',
        'citizen' => 'Citizen'
    ];

    $html = '<header class="app-header">';
    $html .= '<a href="' . $dashboardUrl . '" class="header-brand">Utumishi</a>';
    $html .= '<div class="header-user">';
    $html .= '<div class="user-info">';
    $html .= '<div class="user-name">' . htmlspecialchars($userName) . '</div>';
    $html .= '<div class="user-role ' . $role . '">' . ($roleDisplay[$role] ?? ucfirst($role)) . '</div>';
    $html .= '</div>';
    $html .= '<a href="' . BASE_URL . '/pages/auth/login.php?logout=1" class="btn btn-sm btn-outline btn-primary">Logout</a>';
    $html .= '</div>';
    $html .= '</header>';

    return $html;
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
