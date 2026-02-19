<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class Station {
    private $db;
    private $stationId;
    private $stationData;

    public function __construct($stationId = null) {
        $this->db = Database::getInstance();
        $this->stationId = $stationId;
        
        if ($stationId) {
            $this->loadStationData();
        }
    }

    /**
     * Load station information
     */
    private function loadStationData() {
        $this->stationData = $this->db->fetchOne("
            SELECT s.*, u.name as commander_name 
            FROM stations s 
            LEFT JOIN users u ON s.commander_id = u.id 
            WHERE s.id = :station_id
        ", ['station_id' => $this->stationId]);
    }

    /**
     * Get station data
     */
    public function getStationData($property = null) {
        if ($property) {
            return $this->stationData[$property] ?? null;
        }
        return $this->stationData;
    }

    /**
     * Get all cases for this station with filtering options
     */
    public function getCases($filters = [], $limit = null, $offset = 0) {
        $whereConditions = ['c.station_id = :station_id'];
        $params = ['station_id' => $this->stationId];
        
        // Apply filters
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $whereConditions[] = 'c.status = :status';
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['category'])) {
            $whereConditions[] = 'c.category = :category';
            $params['category'] = $filters['category'];
        }
        
        if (!empty($filters['officer_id'])) {
            $whereConditions[] = 'c.assigned_officer_id = :officer_id';
            $params['officer_id'] = $filters['officer_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'DATE(COALESCE(c.occurred_at, c.created_at)) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'DATE(COALESCE(c.occurred_at, c.created_at)) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['constituency'])) {
            $whereConditions[] = 'c.incident_location_constituency = :constituency';
            $params['constituency'] = $filters['constituency'];
        }

        if (!empty($filters['county'])) {
            $whereConditions[] = 'c.incident_location_county = :county';
            $params['county'] = $filters['county'];
        }

        $whereClause = implode(' AND ', $whereConditions);
        
        return $this->db->fetchAll("
            SELECT 
                c.id,
                c.ob_number,
                c.title,
                c.category,
                c.status,
                c.created_at,
                c.estimated_resolution_hours,
                c.actual_resolution_hours,
                c.closed_at,
                u1.name as reporter_name,
                u2.name as recorded_by_name,
                CONCAT(u3.name, ' (', o.badge_number, ')') as assigned_officer,
                TIMESTAMPDIFF(HOUR, c.created_at, NOW()) as hours_since_reported
            FROM cases c
            JOIN users u1 ON c.reported_by_citizen_id = u1.id
            JOIN users u2 ON c.recorded_by_officer_id = u2.id
            LEFT JOIN officers o ON c.assigned_officer_id = o.id
            LEFT JOIN users u3 ON o.user_id = u3.id
             WHERE $whereClause
             ORDER BY COALESCE(c.occurred_at, c.created_at) DESC
              " . ($limit ? " LIMIT :limit OFFSET :offset" : ""), array_merge($params, $limit ? ['limit' => $limit, 'offset' => $offset] : []));
    }

    public function getCasesCount($filters = []) {
        $whereConditions = ['c.station_id = :station_id'];
        $params = ['station_id' => $this->stationId];

        // Apply filters (same as getCases)
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $whereConditions[] = 'c.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $whereConditions[] = 'c.category = :category';
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['officer_id'])) {
            $whereConditions[] = 'c.assigned_officer_id = :officer_id';
            $params['officer_id'] = $filters['officer_id'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'DATE(c.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'DATE(c.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['constituency'])) {
            $whereConditions[] = 'c.incident_location_constituency = :constituency';
            $params['constituency'] = $filters['constituency'];
        }

        if (!empty($filters['county'])) {
            $whereConditions[] = 'c.incident_location_county = :county';
            $params['county'] = $filters['county'];
        }

        $whereClause = implode(' AND ', $whereConditions);

        return $this->db->fetchOne("
            SELECT COUNT(*) as total
            FROM cases c
            WHERE $whereClause
        ", $params)['total'];
    }

    /**
     * Get case statistics for the station
     */
    public function getCaseStatistics($timeframe = null) {
        $whereConditions = ['station_id = :station_id'];
        $params = ['station_id' => $this->stationId];

        if ($timeframe) {
            $whereConditions[] = 'COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)';
            $params['timeframe'] = $timeframe;
        }

        $whereClause = implode(' AND ', $whereConditions);

        return $this->db->fetchOne("
            SELECT
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status = 'reported' THEN 1 END) as reported_cases,
                COUNT(CASE WHEN status = 'assigned' THEN 1 END) as assigned_cases,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_cases,
                COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_cases,
                COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
            FROM cases
            WHERE $whereClause
        ", $params);
    }

    /**
     * Get cases by category breakdown
     */
    public function getCasesByCategory($timeframe = 30) {
        return $this->db->fetchAll("
            SELECT
                category,
                COUNT(*) as case_count,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_count,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
            FROM cases
            WHERE station_id = :station_id
            AND COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)
            GROUP BY category
            ORDER BY case_count DESC
        ", ['station_id' => $this->stationId, 'timeframe' => $timeframe]);
    }

    /**
     * Get overdue cases for the station
     */
    public function getOverdueCases() {
        return $this->db->fetchAll("
            SELECT 
                c.id,
                c.ob_number,
                c.title,
                c.category,
                c.status,
                c.created_at,
                c.estimated_resolution_hours,
                u1.name as reporter_name,
                CONCAT(u3.name, ' (', o.badge_number, ')') as assigned_officer,
                TIMESTAMPDIFF(HOUR, c.created_at, NOW()) as hours_since_reported,
                TIMESTAMPDIFF(HOUR, c.created_at, NOW()) - c.estimated_resolution_hours as hours_overdue
            FROM cases c
            JOIN users u1 ON c.reported_by_citizen_id = u1.id
            LEFT JOIN officers o ON c.assigned_officer_id = o.id
            LEFT JOIN users u3 ON o.user_id = u3.id
            WHERE c.station_id = :station_id 
            AND c.status NOT IN ('resolved', 'closed')
            AND TIMESTAMPDIFF(HOUR, c.created_at, NOW()) > c.estimated_resolution_hours
            ORDER BY hours_overdue DESC
        ", ['station_id' => $this->stationId]);
    }

    /**
     * Get recent cases (last 7 days)
     */
    public function getRecentCases($days = 7) {
        return $this->db->fetchAll("
            SELECT
                c.id,
                c.ob_number,
                c.title,
                c.category,
                c.status,
                c.created_at,
                c.occurred_at,
                u1.name as reporter_name,
                CONCAT(u3.name, ' (', o.badge_number, ')') as assigned_officer
            FROM cases c
            JOIN users u1 ON c.reported_by_citizen_id = u1.id
            LEFT JOIN officers o ON c.assigned_officer_id = o.id
            LEFT JOIN users u3 ON o.user_id = u3.id
            WHERE c.station_id = :station_id
            AND COALESCE(c.occurred_at, c.created_at) >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY COALESCE(c.occurred_at, c.created_at) DESC
        ", ['station_id' => $this->stationId, 'days' => $days]);
    }

    /**
     * Get officers assigned to this station
     */
    public function getOfficers() {
        return $this->db->fetchAll("
            SELECT 
                o.id, 
                u.name, 
                o.badge_number,
                o.current_case_load,
                o.total_cases_resolved
            FROM officers o
            JOIN users u ON o.user_id = u.id
            WHERE u.station_id = :station_id AND u.is_active = 1
            ORDER BY u.name
        ", ['station_id' => $this->stationId]);
    }

    /**
     * Get available case categories for this station
     */
    public function getCaseCategories() {
        return $this->db->fetchAll("
            SELECT DISTINCT category
            FROM cases
            WHERE station_id = :station_id
            ORDER BY category
        ", ['station_id' => $this->stationId]);
    }

    /**
     * Get station performance metrics
     */
    public function getPerformanceMetrics($timeframe = 30) {
    $caseStats = $this->getCaseStatistics($timeframe);
    $overdueCases = $this->getOverdueCases();
    $categoryBreakdown = $this->getCasesByCategory($timeframe);
    
    // Calculate performance score (0-100) with division by zero protection
    $resolutionRate = $caseStats['resolution_rate'] ?? 0;
    $avgResolutionTime = $caseStats['avg_resolution_time'] ?? 0;
    $overdueCount = count($overdueCases);
    $totalCases = max(1, $caseStats['total_cases'] ?? 1); // Prevent division by zero
    
    $timeScore = $avgResolutionTime > 0 ? max(0, 100 - ($avgResolutionTime / 72 * 100)) : 100;
    $overdueScore = max(0, 100 - ($overdueCount / $totalCases * 100));
    
    $performanceScore = round(($resolutionRate + $timeScore + $overdueScore) / 3, 1);
    
    return [
        'case_statistics' => $caseStats,
        'overdue_cases' => $overdueCases,
        'category_breakdown' => $categoryBreakdown,
        'performance_score' => $performanceScore,
        'metrics' => [
            'resolution_rate' => $resolutionRate,
            'time_performance' => $timeScore,
            'overdue_performance' => $overdueScore
        ]
    ];
}

    /**
     * Get case trends for the station
     */
    public function getCaseTrends($days = 30) {
        $dailyTrends = $this->db->fetchAll("
            SELECT
                DATE(COALESCE(occurred_at, created_at)) as date,
                COUNT(*) as case_count,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_count
            FROM cases
            WHERE station_id = :station_id
            AND COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY DATE(COALESCE(occurred_at, created_at))
            ORDER BY date ASC
        ", ['station_id' => $this->stationId, 'days' => $days]);

        $categoryTrends = $this->db->fetchAll("
            SELECT
                category,
                DATE(COALESCE(occurred_at, created_at)) as date,
                COUNT(*) as case_count
            FROM cases
            WHERE station_id = :station_id
            AND COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY category, DATE(COALESCE(occurred_at, created_at))
            ORDER BY date ASC, category ASC
        ", ['station_id' => $this->stationId, 'days' => $days]);

        return [
            'daily_trends' => $dailyTrends,
            'category_trends' => $categoryTrends
        ];
    }

    /**
     * Search cases with advanced filters
     */
    public function searchCases($searchTerm, $filters = [], $limit = null, $offset = 0) {
        $whereConditions = ['c.station_id = :station_id'];
        $params = ['station_id' => $this->stationId];

        // Add search conditions
        if ($searchTerm) {
            $whereConditions[] = '(c.ob_number LIKE :search_ob OR c.title LIKE :search_title OR c.description LIKE :search_desc OR u1.name LIKE :search_name)';
            $params['search_ob'] = "%{$searchTerm}%";
            $params['search_title'] = "%{$searchTerm}%";
            $params['search_desc'] = "%{$searchTerm}%";
            $params['search_name'] = "%{$searchTerm}%";
        }
        
        // Apply additional filters
        foreach ($filters as $key => $value) {
            if (!empty($value) && $value !== 'all') {
                switch ($key) {
                    case 'status':
                        $whereConditions[] = 'c.status = :status';
                        $params['status'] = $value;
                        break;
                    case 'category':
                        $whereConditions[] = 'c.category = :category';
                        $params['category'] = $value;
                        break;
                }
            }
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        return $this->db->fetchAll("
            SELECT 
                c.id,
                c.ob_number,
                c.title,
                c.category,
                c.status,
                c.created_at,
                u1.name as reporter_name,
                CONCAT(u3.name, ' (', o.badge_number, ')') as assigned_officer
            FROM cases c
            JOIN users u1 ON c.reported_by_citizen_id = u1.id
            LEFT JOIN officers o ON c.assigned_officer_id = o.id
            LEFT JOIN users u3 ON o.user_id = u3.id
             WHERE $whereClause
             ORDER BY c.created_at DESC
             " . ($limit ? " LIMIT :limit OFFSET :offset" : " LIMIT 50"), array_merge($params, $limit ? ['limit' => $limit, 'offset' => $offset] : []));
    }

    public function getSearchCasesCount($searchTerm, $filters = []) {
        $whereConditions = ['c.station_id = :station_id'];
        $params = ['station_id' => $this->stationId];

        // Add search conditions
        if ($searchTerm) {
            $whereConditions[] = '(c.ob_number LIKE :search_ob OR c.title LIKE :search_title OR c.description LIKE :search_desc OR u1.name LIKE :search_name)';
            $params['search_ob'] = "%{$searchTerm}%";
            $params['search_title'] = "%{$searchTerm}%";
            $params['search_desc'] = "%{$searchTerm}%";
            $params['search_name'] = "%{$searchTerm}%";
        }

        // Apply additional filters
        foreach ($filters as $key => $value) {
            if (!empty($value) && $value !== 'all') {
                switch ($key) {
                    case 'status':
                        $whereConditions[] = 'c.status = :status';
                        $params['status'] = $value;
                        break;
                    case 'category':
                        $whereConditions[] = 'c.category = :category';
                        $params['category'] = $value;
                        break;
                }
            }
        }

        $whereClause = implode(' AND ', $whereConditions);

        return $this->db->fetchOne("
            SELECT COUNT(*) as total
            FROM cases c
            JOIN users u1 ON c.reported_by_citizen_id = u1.id
            WHERE $whereClause
        ", $params)['total'];
    }

    /**
     * Get station dashboard data
     */
    public function getDashboardData() {
        $performanceMetrics = $this->getPerformanceMetrics(30);
        $recentCases = $this->getRecentCases(7);
        $officers = $this->getOfficers();
        $trends = $this->getCaseTrends(7);
        
        return [
            'station_info' => $this->stationData,
            'performance' => $performanceMetrics,
            'recent_cases' => $recentCases,
            'officers' => $officers,
            'trends' => $trends,
            'summary' => [
                'total_officers' => count($officers),
                'total_cases_7days' => count($recentCases),
                'overdue_cases' => count($performanceMetrics['overdue_cases']),
                'performance_score' => $performanceMetrics['performance_score']
            ]
        ];
    }

    /**
     * Generate station report
     */
    public function generateReport($type = 'monthly', $parameters = []) {
        switch ($type) {
            case 'monthly':
                return $this->generateMonthlyReport($parameters);
            case 'performance':
                return $this->generatePerformanceReport($parameters);
            case 'category_analysis':
                return $this->generateCategoryAnalysisReport($parameters);
            default:
                throw new Exception("Unknown report type: {$type}");
        }
    }

    /**
     * Generate monthly report
     */
    private function generateMonthlyReport($parameters) {
        $year = $parameters['year'] ?? date('Y');
        $month = $parameters['month'] ?? date('m');

        $monthlyStats = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
            FROM cases
            WHERE station_id = :station_id
            AND YEAR(COALESCE(occurred_at, created_at)) = :year
            AND MONTH(COALESCE(occurred_at, created_at)) = :month
        ", [
            'station_id' => $this->stationId,
            'year' => $year,
            'month' => $month
        ]);

        $categoryBreakdown = $this->db->fetchAll("
            SELECT
                category,
                COUNT(*) as case_count,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_count
            FROM cases
            WHERE station_id = :station_id
            AND YEAR(COALESCE(occurred_at, created_at)) = :year
            AND MONTH(COALESCE(occurred_at, created_at)) = :month
            GROUP BY category
            ORDER BY case_count DESC
        ", [
            'station_id' => $this->stationId,
            'year' => $year,
            'month' => $month
        ]);
        
        return [
            'type' => 'Monthly Station Report',
            'period' => date('F Y', mktime(0, 0, 0, $month, 1, $year)),
            'station' => $this->stationData,
            'statistics' => $monthlyStats,
            'category_breakdown' => $categoryBreakdown,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate performance report
     */
    private function generatePerformanceReport($parameters) {
        $timeframe = $parameters['timeframe'] ?? 30;
        return [
            'type' => 'Station Performance Report',
            'timeframe' => "{$timeframe} days",
            'station' => $this->stationData,
            'performance' => $this->getPerformanceMetrics($timeframe),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate category analysis report
     */
    private function generateCategoryAnalysisReport($parameters) {
        $timeframe = $parameters['timeframe'] ?? 90;
        $categoryData = $this->getCasesByCategory($timeframe);
        
        return [
            'type' => 'Category Analysis Report',
            'timeframe' => "{$timeframe} days",
            'station' => $this->stationData,
            'category_analysis' => $categoryData,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Static method to get all stations
     */
    public static function getAllStations() {
        $db = Database::getInstance();
        return $db->fetchAll("
            SELECT
                s.*,
                u.name as commander_name,
                COUNT(DISTINCT o.id) as officer_count,
                COUNT(c.id) as total_cases
            FROM stations s
            LEFT JOIN users u ON s.commander_id = u.id
            LEFT JOIN users u2 ON s.id = u2.station_id AND u2.role = 'officer' AND u2.is_active = 1
            LEFT JOIN officers o ON u2.id = o.user_id
            LEFT JOIN cases c ON s.id = c.station_id AND COALESCE(c.occurred_at, c.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY s.id
            ORDER BY s.name
        ");
    }
}
?>