<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class CountyReportsManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Generate comprehensive county crime report
     */
    public function generateCountyReport($timeframe = 30, $county = null) {
        $overallStats = $this->getCountyStatistics($timeframe, $county);
        $stationBreakdown = $this->getStationStatistics($timeframe, $county);
        $categoryTrends = $this->getCategoryTrends($timeframe, $county);
        $stationPerformance = $this->getStationPerformanceRanking($timeframe, $county);
        $monthlyTrends = $this->getMonthlyTrends($county);

        return [
            'type' => 'County Crime Report',
            'timeframe' => $timeframe,
            'period_description' => "Last {$timeframe} days",
            'overall_statistics' => $overallStats,
            'station_breakdown' => $stationBreakdown,
            'category_trends' => $categoryTrends,
            'station_performance' => $stationPerformance,
            'monthly_trends' => $monthlyTrends,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get county crime statistics
     */
    public function getCountyStatistics($timeframe = 30, $county = null) {
        $where = "COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)";
        $params = ['timeframe' => $timeframe];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        return $this->db->fetchOne("
            SELECT
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                COUNT(CASE WHEN status = 'reported' THEN 1 END) as pending_cases,
                COUNT(CASE WHEN status IN ('assigned', 'in_progress') THEN 1 END) as active_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time,
                COUNT(DISTINCT station_id) as active_stations,
                COUNT(DISTINCT incident_location_constituency) as affected_constituencies
            FROM cases
            WHERE $where
        ", $params);
    }

    /**
     * Get station statistics for county
     */
    public function getStationStatistics($timeframe = 30, $county = null) {
        $where = "COALESCE(c.occurred_at, c.created_at) >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)";
        $params = ['timeframe' => $timeframe];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        return $this->db->fetchAll("
            SELECT
                s.name as station,
                s.station_code,
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time,
                COUNT(DISTINCT category) as crime_types
            FROM cases c
            JOIN stations s ON c.station_id = s.id
            WHERE $where
            GROUP BY s.id, s.name, s.station_code
            ORDER BY total_cases DESC
        ", $params);
    }

    /**
     * Get category trends
     */
    public function getCategoryTrends($timeframe = 30, $county = null) {
        $where = "COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)";
        $params = ['timeframe' => $timeframe];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        $totalCases = $this->db->fetchOne("SELECT COUNT(*) as total FROM cases WHERE $where", $params)['total'];

        $categoryTrends = $this->db->fetchAll("
            SELECT
                category,
                COUNT(*) as case_count,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_count,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time,
                ROUND(COUNT(*) * 100.0 / :total, 2) as percentage_of_total
            FROM cases
            WHERE $where
            GROUP BY category
            ORDER BY case_count DESC
        ", array_merge($params, ['total' => $totalCases ?: 1]));

        return $categoryTrends;
    }

    /**
     * Get station performance ranking for county
     */
    public function getStationPerformanceRanking($timeframe = 30, $county = null) {
        $where = "s.county = :county";
        $params = ['timeframe' => $timeframe, 'county' => $county];

        return $this->db->fetchAll("
            SELECT
                s.name as station_name,
                s.county,
                s.constituency,
                COUNT(c.id) as total_cases,
                COUNT(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                ROUND(COUNT(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(c.id), 0), 1) as resolution_rate,
                AVG(CASE WHEN c.actual_resolution_hours IS NOT NULL THEN c.actual_resolution_hours END) as avg_resolution_time,
                COUNT(DISTINCT o.id) as officer_count,
                ROUND(COUNT(c.id) / NULLIF(COUNT(DISTINCT o.id), 0), 1) as cases_per_officer
            FROM stations s
            LEFT JOIN cases c ON s.id = c.station_id AND COALESCE(c.occurred_at, c.created_at) >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)
            LEFT JOIN users u ON s.id = u.station_id AND u.role = 'officer' AND u.is_active = 1
            LEFT JOIN officers o ON u.id = o.user_id
            WHERE $where
            GROUP BY s.id, s.name, s.county, s.constituency
            ORDER BY resolution_rate DESC, total_cases DESC
        ", $params);
    }

    /**
     * Get monthly trends
     */
    public function getMonthlyTrends($county = null) {
        $where = "COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        $params = [];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        return $this->db->fetchAll("
            SELECT
                DATE_FORMAT(COALESCE(occurred_at, created_at), '%Y-%m') as month_year,
                COUNT(*) as case_count,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_count
            FROM cases
            WHERE $where
            GROUP BY DATE_FORMAT(COALESCE(occurred_at, created_at), '%Y-%m')
            ORDER BY month_year DESC
            LIMIT 12
        ", $params);
    }

    /**
     * Generate monthly county report
     */
    public function generateMonthlyCountyReport($year, $month, $county = null) {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $where = "DATE(COALESCE(occurred_at, created_at)) BETWEEN :start AND :end";
        $params = ['start' => $startDate, 'end' => $endDate];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        $overallStats = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
            FROM cases
            WHERE $where
        ", $params);

        $categoryBreakdown = $this->getCategoryTrends(30, $county); // For monthly, use recent trends

        return [
            'type' => 'Monthly County Report',
            'period' => [
                'year' => $year,
                'month' => $month,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year))
            ],
            'overall_stats' => $overallStats,
            'category_breakdown' => $categoryBreakdown,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate county performance report
     */
    public function generateCountyPerformanceReport($county = null, $timeframe = 30) {
        $where = "created_at >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)";
        $params = ['timeframe' => $timeframe];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        $stationStats = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
            FROM cases
            WHERE $where
        ", $params);

        $categoryBreakdown = $this->getCategoryTrends($timeframe, $county);

        return [
            'type' => 'County Performance Report',
            'period' => "Last {$timeframe} days",
            'overall_stats' => $stationStats,
            'category_breakdown' => $categoryBreakdown,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate county crime analysis report
     */
    public function generateCountyCrimeAnalysisReport($county = null, $timeframe = 30) {
        $where = "created_at >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)";
        $params = ['timeframe' => $timeframe];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        $totalCases = $this->db->fetchOne("SELECT COUNT(*) as total FROM cases WHERE $where", $params)['total'];

        $hotspots = $this->db->fetchAll("
            SELECT
                incident_location_constituency as location,
                COUNT(*) as case_count,
                category
            FROM cases
            WHERE $where
            GROUP BY incident_location_constituency, category
            HAVING case_count >= 3
            ORDER BY case_count DESC
            LIMIT 10
        ", $params);

        $mostCommonCategory = $this->db->fetchOne("
            SELECT category, COUNT(*) as count
            FROM cases
            WHERE $where
            GROUP BY category
            ORDER BY count DESC
            LIMIT 1
        ", $params)['category'] ?? 'N/A';

        return [
            'type' => 'County Crime Analysis Report',
            'period' => "Last {$timeframe} days",
            'overall_stats' => [
                'total_cases' => $totalCases
            ],
            'hotspots' => $hotspots,
            'most_common_category' => $mostCommonCategory,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}

if (!function_exists('array_sort')) {
    function array_sort($array, $callback) {
        uasort($array, $callback);
        return $array;
    }
}
?>