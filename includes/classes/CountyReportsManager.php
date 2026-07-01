<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class CountyReportsManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

   
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

   
    public function getCountyStatistics($timeframe = 30, $county = null) {
        $where = "created_at >= DATE_SUB(CURDATE(), INTERVAL :timeframe DAY)";
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
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time,
                COUNT(DISTINCT station_id) as active_stations,
                COUNT(DISTINCT incident_location_constituency) as affected_constituencies
            FROM cases
            WHERE $where
        ", $params);
    }

    
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

    
    public function getCategoryTrends($timeframe = 30, $county = null) {
        $where = "created_at >= DATE_SUB(CURDATE(), INTERVAL :timeframe DAY)";
        $params = ['timeframe' => $timeframe];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        $totalCases = $this->db->fetchOne("SELECT COUNT(*) as total FROM cases WHERE $where", $params)['total'] ?? 0;

        if ($totalCases == 0) {
            return [];
        }

        $categoryTrends = $this->db->fetchAll("
            SELECT
                category,
                COUNT(*) as case_count,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_count,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time,
                ROUND(COUNT(*) * 100.0 / :total, 2) as percentage_of_total
            FROM cases
            WHERE $where
            GROUP BY category
            ORDER BY case_count DESC
        ", array_merge($params, ['total' => $totalCases]));

        return $categoryTrends;
    }

   
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
            LEFT JOIN officers o ON s.id = o.station_id
            LEFT JOIN users u ON o.user_id = u.id AND u.is_active = 1
            WHERE $where
            GROUP BY s.id, s.name, s.county, s.constituency
            ORDER BY resolution_rate DESC, total_cases DESC
        ", $params);
    }

    
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

   
    public function generateAnnualCountyReport($year, $county = null) {
        $where = "YEAR(created_at) = :year";
        $params = ['year' => $year];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        $overallStats = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                COUNT(CASE WHEN status = 'reported' THEN 1 END) as pending_cases,
                COUNT(CASE WHEN status IN ('assigned', 'in_progress') THEN 1 END) as active_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time,
                COUNT(DISTINCT station_id) as active_stations,
                COUNT(DISTINCT incident_location_constituency) as affected_constituencies
            FROM cases
            WHERE $where
        ", $params);

        $monthlyTrends = $this->db->fetchAll("
            SELECT
                MONTH(created_at) as month,
                COUNT(*) as case_count,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_count,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as resolution_rate
            FROM cases
            WHERE $where
            GROUP BY MONTH(created_at)
            ORDER BY month ASC
        ", $params);

        $categoryBreakdown = $this->db->fetchAll("
            SELECT
                category,
                COUNT(*) as case_count,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_count,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
            FROM cases
            WHERE $where
            GROUP BY category
            ORDER BY case_count DESC
        ", $params);

        $constituencyHotspots = $this->db->fetchAll("
            SELECT
                incident_location_constituency as location,
                COUNT(*) as case_count
            FROM cases
            WHERE $where
            GROUP BY incident_location_constituency
            ORDER BY case_count DESC
            LIMIT 5
        ", $params);

        $nestedHotspots = [];
        foreach ($constituencyHotspots as $constituency) {
            $constituencyName = $constituency['location'];
            $localAreas = $this->db->fetchAll("
                SELECT
                    incident_local_area as location,
                    COUNT(*) as case_count
                FROM cases
                WHERE $where AND incident_location_constituency = :constituency
                GROUP BY incident_local_area
                HAVING incident_local_area IS NOT NULL AND incident_local_area != ''
                ORDER BY case_count DESC
                LIMIT 8
            ", array_merge($params, ['constituency' => $constituencyName]));

            $nestedHotspots[] = [
                'constituency' => $constituencyName,
                'total_cases' => (int)$constituency['case_count'],
                'local_areas' => $localAreas
            ];
        }

        return [
            'type' => 'Annual County Report',
            'period' => [
                'year' => $year,
                'year_name' => $year
            ],
            'overall_stats' => $overallStats,
            'monthly_trends' => $monthlyTrends,
            'category_breakdown' => $categoryBreakdown,
            'hotspots' => $nestedHotspots,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

   
    public function generateCountyPerformanceReport($county = null, $timeframe = 30) {
        $where = "created_at >= DATE_SUB(CURDATE(), INTERVAL :timeframe DAY)";
        $params = ['timeframe' => $timeframe];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        $stationStats = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as resolution_rate,
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

   
    public function generateCountyCrimeAnalysisReport($county = null, $timeframe = 30) {
        $where = "created_at >= DATE_SUB(CURDATE(), INTERVAL :timeframe DAY)";
        $params = ['timeframe' => $timeframe];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        $totalCases = $this->db->fetchOne("SELECT COUNT(*) as total FROM cases WHERE $where", $params)['total'] ?? 0;

        $constituencyHotspots = $this->db->fetchAll("
            SELECT
                incident_location_constituency as location,
                COUNT(*) as case_count
            FROM cases
            WHERE $where
            GROUP BY incident_location_constituency
            ORDER BY case_count DESC
            LIMIT 5
        ", $params);

        $nestedHotspots = [];
        foreach ($constituencyHotspots as $constituency) {
            $constituencyName = $constituency['location'];
            $localAreas = $this->db->fetchAll("
                SELECT
                    incident_local_area as location,
                    COUNT(*) as case_count
                FROM cases
                WHERE $where AND incident_location_constituency = :constituency
                GROUP BY incident_local_area
                HAVING incident_local_area IS NOT NULL AND incident_local_area != ''
                ORDER BY case_count DESC
                LIMIT 8
            ", array_merge($params, ['constituency' => $constituencyName]));

            $nestedHotspots[] = [
                'constituency' => $constituencyName,
                'total_cases' => (int)$constituency['case_count'],
                'local_areas' => $localAreas
            ];
        }

        $mostCommonCategory = $this->db->fetchOne("
            SELECT category, COUNT(*) as count
            FROM cases
            WHERE $where
            GROUP BY category
            ORDER BY count DESC
            LIMIT 1
        ", $params);

        return [
            'type' => 'County Crime Analysis Report',
            'period' => "Last {$timeframe} days",
            'overall_stats' => [
                'total_cases' => $totalCases
            ],
            'hotspots' => $nestedHotspots,
            'most_common_category' => $mostCommonCategory['category'] ?? 'N/A',
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