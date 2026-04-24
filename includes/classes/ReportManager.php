<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/CrimeAnalyzer.php';

class ReportManager {
    private $db;
    private $crimeAnalyzer;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->crimeAnalyzer = new CrimeAnalyzer();
    }

    /**
     * Generate monthly report for a station
     */
    public function generateMonthlyReport($year, $month, $stationId = null) {
        $whereConditions = ["YEAR(COALESCE(occurred_at, created_at)) = :year", "MONTH(COALESCE(occurred_at, created_at)) = :month"];
        $params = ['year' => $year, 'month' => $month];

        if ($stationId) {
            $whereConditions[] = "station_id = :station_id";
            $params['station_id'] = $stationId;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $overallStats = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
            FROM cases
            WHERE $whereClause", $params);

        $categoryStats = $this->db->fetchAll("
            SELECT
                category,
                COUNT(*) as case_count,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_count,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
                ROUND(AVG(COALESCE(estimated_resolution_hours, 72)), 1) as avg_resolution_time
            FROM cases
            WHERE $whereClause
            GROUP BY category
            ORDER BY case_count DESC", $params);

        $locationStats = $this->db->fetchAll("
            SELECT
                incident_location_county as county,
                incident_location_constituency as constituency,
                COUNT(*) as case_count
            FROM cases
            WHERE $whereClause
            GROUP BY incident_location_county, incident_location_constituency
            ORDER BY case_count DESC
            LIMIT 10", $params);

        return [
            'type' => 'Monthly Report',
            'period' => [
                'year' => $year,
                'month' => $month,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year))
            ],
            'overall_stats' => $overallStats,
            'category_breakdown' => $categoryStats,
            'location_breakdown' => $locationStats,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate annual report for a station
     */
    public function generateAnnualReport($year, $stationId = null) {
        $whereConditions = ["YEAR(COALESCE(occurred_at, created_at)) = :year"];
        $params = ['year' => $year];

        if ($stationId) {
            $whereConditions[] = "station_id = :station_id";
            $params['station_id'] = $stationId;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $overallStats = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
            FROM cases
            WHERE $whereClause", $params);

        $categoryStats = $this->db->fetchAll("
            SELECT
                category,
                COUNT(*) as case_count,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_count,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as resolution_rate,
                ROUND(AVG(COALESCE(estimated_resolution_hours, 72)), 1) as avg_resolution_time
            FROM cases
            WHERE $whereClause
            GROUP BY category
            ORDER BY case_count DESC", $params);

        $monthlyTrends = $this->db->fetchAll("
            SELECT
                MONTH(COALESCE(occurred_at, created_at)) as month,
                COUNT(*) as case_count,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_count,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as resolution_rate
            FROM cases
            WHERE $whereClause
            GROUP BY MONTH(COALESCE(occurred_at, created_at))
            ORDER BY month ASC", $params);

        $hotspots = $this->db->fetchAll("
            SELECT
                incident_location_constituency as location,
                category,
                COUNT(*) as case_count
            FROM cases
            WHERE $whereClause
            GROUP BY incident_location_constituency, category
            HAVING case_count > 0
            ORDER BY case_count DESC
            LIMIT 10", $params);

        return [
            'type' => 'Annual Report',
            'period' => [
                'year' => $year,
                'year_name' => $year
            ],
            'overall_stats' => $overallStats,
            'category_breakdown' => $categoryStats,
            'monthly_trends' => $monthlyTrends,
            'hotspots' => $hotspots,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate performance report for a station
     */
    public function generatePerformanceReport($stationId, $timeframe = 30) {
        $params = ['station_id' => $stationId, 'timeframe' => $timeframe];

        $stationStats = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
            FROM cases
            WHERE station_id = :station_id
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL :timeframe DAY)
        ", $params);

        $categoryBreakdown = $this->db->fetchAll("
            SELECT
                category,
                COUNT(*) as case_count,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_count,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as resolution_rate,
                ROUND(AVG(COALESCE(estimated_resolution_hours, 72)), 1) as avg_resolution_time
            FROM cases
            WHERE station_id = :station_id
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL :timeframe DAY)
            GROUP BY category
            ORDER BY case_count DESC
        ", $params);

        return [
            'type' => 'Station Performance Report',
            'period' => "Last {$timeframe} days",
            'total_cases' => $stationStats['total_cases'] ?? 0,
            'station_stats' => $stationStats,
            'category_breakdown' => $categoryBreakdown,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

/**
     * Generate crime analysis report
     */
    public function generateCrimeAnalysisReport($stationId, $timeframe = 30) {
        $params = ['station_id' => $stationId, 'timeframe' => $timeframe];

        $trends = $this->db->fetchAll("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as case_count,
                category
            FROM cases
            WHERE station_id = :station_id
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL :timeframe DAY)
            GROUP BY DATE(created_at), category
            ORDER BY date DESC
        ", $params);

        $constituencyHotspots = $this->db->fetchAll("
            SELECT
                incident_location_constituency as location,
                COUNT(*) as case_count
            FROM cases
            WHERE station_id = :station_id
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL :timeframe DAY)
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
                WHERE station_id = :station_id
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL :timeframe DAY)
                AND incident_location_constituency = :constituency
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

        $totalCases = $this->db->fetchOne("SELECT COUNT(*) as total FROM cases WHERE station_id = :station_id AND created_at >= DATE_SUB(CURDATE(), INTERVAL :timeframe DAY)", $params)['total'] ?? 0;

        $mostCommon = $this->db->fetchOne("
            SELECT category, COUNT(*) as count
            FROM cases
            WHERE station_id = :station_id
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL :timeframe DAY)
            GROUP BY category
            ORDER BY count DESC
            LIMIT 1
        ", $params);

        return [
            'type' => 'Crime Analysis Report',
            'period' => "Last {$timeframe} days",
            'total_cases' => $totalCases,
            'most_common_category' => $mostCommon['category'] ?? 'N/A',
            'trends' => $trends,
            'hotspots' => $nestedHotspots,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate officer workload report
     */
    public function generateOfficerWorkloadReport($stationId) {
        $officers = $this->db->fetchAll("
            SELECT 
                u.name,
                o.badge_number,
                o.current_case_load,
                o.total_cases_resolved,
                COUNT(c.id) as active_cases,
                ROUND(COUNT(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 END) * 100.0 / 
                      NULLIF(COUNT(c.id), 0), 1) as resolution_rate
            FROM officers o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN cases c ON o.id = c.assigned_officer_id
            WHERE o.station_id = :station_id AND u.is_active = 1
            GROUP BY u.name, o.badge_number, o.current_case_load, o.total_cases_resolved
            ORDER BY o.current_case_load DESC
        ", ['station_id' => $stationId]);

        $workloadSummary = $this->db->fetchOne("
            SELECT 
                COUNT(DISTINCT o.id) as total_officers,
                AVG(o.current_case_load) as avg_case_load,
                MAX(o.current_case_load) as max_case_load,
                COUNT(CASE WHEN o.current_case_load > 10 THEN 1 END) as overloaded_officers
            FROM officers o
            JOIN users u ON o.user_id = u.id
            WHERE o.station_id = :station_id AND u.is_active = 1
        ", ['station_id' => $stationId]);

        return [
            'type' => 'Officer Workload Report',
            'officers' => $officers,
            'summary' => $workloadSummary,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate comprehensive station report
     */
    public function generateStationOverviewReport($stationId) {
        // Get station information
        $stationInfo = $this->db->fetchOne("
            SELECT s.*, u.name as ocs_name, cc.name as county_commander_name 
            FROM stations s 
            LEFT JOIN users u ON s.ocs_id = u.id 
            LEFT JOIN users cc ON s.county_commander_id = cc.id
            WHERE s.id = :station_id
        ", ['station_id' => $stationId]);

        // Get basic statistics (last 30 days)
        $stats = $this->generatePerformanceReport($stationId, 30);
        
        // Get officer workload
        $workload = $this->generateOfficerWorkloadReport($stationId);
        
        // Get recent trends
        $trends = $this->generateCrimeAnalysisReport($stationId, 30);

        return [
            'type' => 'Station Overview Report',
            'station_info' => $stationInfo,
            'performance' => $stats,
            'officer_workload' => $workload,
            'crime_trends' => $trends,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Export report data as JSON
     */
    public function exportReportAsJson($reportData) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="police_report_' . date('Y-m-d') . '.json"');
        return json_encode($reportData, JSON_PRETTY_PRINT);
    }

    /**
     * Get available report types
     */
    public function getAvailableReportTypes() {
        return [
            'monthly' => [
                'name' => 'Monthly Report',
                'description' => 'Comprehensive monthly statistics and analysis',
                'icon' => '',
                'parameters' => ['year', 'month']
            ],
            'performance' => [
                'name' => 'Performance Report',
                'description' => 'Station performance metrics and trends',
                'icon' => '',
                'parameters' => ['timeframe']
            ],
            'crime_analysis' => [
                'name' => 'Crime Analysis',
                'description' => 'Crime patterns and hotspot analysis',
                'icon' => '',
                'parameters' => ['timeframe']
            ],
            'officer_workload' => [
                'name' => 'Officer Workload',
                'description' => 'Current officer assignments and performance',
                'icon' => '',
                'parameters' => []
            ],
            'station_overview' => [
                'name' => 'Station Overview',
                'description' => 'Comprehensive station report',
                'icon' => '',
                'parameters' => []
            ]
        ];
    }
}
?>