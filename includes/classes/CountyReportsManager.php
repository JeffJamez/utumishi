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
     * Generate comprehensive national crime report
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
        $where = "created_at >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)";
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
     * Get station-wise crime statistics within county
     */
    public function getStationStatistics($timeframe = 30, $county = null) {
        $where = "created_at >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)";
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
     * Get crime category trends
     */
    public function getCategoryTrends($timeframe = 30, $county = null) {
        $where = "created_at >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)";
        $params = ['timeframe' => $timeframe];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        $subWhere = "created_at >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)";
        $subParams = ['timeframe' => $timeframe];
        if ($county) {
            $subWhere .= " AND incident_location_county = :county";
            $subParams['county'] = $county;
        }

        return $this->db->fetchAll("
            SELECT
                category,
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time,
                ROUND(COUNT(*) * 100.0 / (
                    SELECT COUNT(*) FROM cases
                    WHERE $subWhere
                ), 2) as percentage_of_total
            FROM cases
            WHERE $where
            GROUP BY category
            ORDER BY total_cases DESC
        ", array_merge($params, $subParams));
    }

    /**
     * Get station performance ranking
     */
    public function getStationPerformanceRanking($timeframe = 30, $county = null) {
        $where = "1=1";
        $params = ['timeframe' => $timeframe];

        if ($county) {
            $where .= " AND s.county = :county";
            $params['county'] = $county;
        }

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
            LEFT JOIN cases c ON s.id = c.station_id AND c.created_at >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)
            LEFT JOIN users u ON s.id = u.station_id AND u.role = 'officer' AND u.is_active = 1
            LEFT JOIN officers o ON u.id = o.user_id
            WHERE $where
            GROUP BY s.id, s.name, s.county, s.constituency
            ORDER BY resolution_rate DESC, total_cases DESC
        ", $params);
    }

    /**
     * Get monthly trends for the past year
     */
    public function getMonthlyTrends($county = null) {
        $where = "created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        $params = [];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        return $this->db->fetchAll("
            SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                DATE_FORMAT(created_at, '%M %Y') as month_name,
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate
            FROM cases
            WHERE $where
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
        ", $params);
    }

    /**
     * Generate resource allocation report
     */
    public function generateResourceReport($county = null) {
        $where = "1=1";
        $params = [];

        if ($county) {
            $where .= " AND s.county = :county";
            $params['county'] = $county;
        }

        $stationResources = $this->db->fetchAll("
            SELECT
                s.name as station_name,
                s.county,
                COUNT(DISTINCT o.id) as officer_count,
                COUNT(DISTINCT c.id) as cases_handled,
                ROUND(COUNT(DISTINCT c.id) / NULLIF(COUNT(DISTINCT o.id), 0), 1) as cases_per_officer
            FROM stations s
            LEFT JOIN users u ON s.id = u.station_id AND u.role = 'officer' AND u.is_active = 1
            LEFT JOIN officers o ON u.id = o.user_id
            LEFT JOIN cases c ON s.id = c.station_id AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            WHERE $where
            GROUP BY s.id, s.name, s.county
            ORDER BY s.county ASC, s.name ASC
        ", $params);

        $stationCountWhere = "1=1";
        $stationCountParams = [];
        if ($county) {
            $stationCountWhere .= " AND county = :county";
            $stationCountParams['county'] = $county;
        }

        $stationCount = $this->db->fetchOne("
            SELECT COUNT(*) as total_stations FROM stations WHERE $stationCountWhere
        ", $stationCountParams);

        return [
            'type' => 'Resource Allocation Report',
            'station_resources' => $stationResources,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate officer performance summary
     */
    public function generateOfficerPerformanceReport($timeframe = 30, $county = null) {
        $where = "1=1";
        $params = [];

        if ($county) {
            $where .= " AND s.county = :county";
            $params['county'] = $county;
        }

        $performanceStats = $this->db->fetchAll("
            SELECT
                s.name as station_name,
                s.county,
                COUNT(DISTINCT o.id) as total_officers,
                AVG(o.current_case_load) as avg_case_load,
                AVG(o.total_cases_resolved) as avg_cases_resolved,
                AVG(o.avg_resolution_time_hours) as avg_resolution_time,
                COUNT(CASE WHEN o.current_case_load > 15 THEN 1 END) as overloaded_officers,
                COUNT(CASE WHEN o.current_case_load = 0 THEN 1 END) as idle_officers
            FROM stations s
            LEFT JOIN users u ON s.id = u.station_id AND u.role = 'officer' AND u.is_active = 1
            LEFT JOIN officers o ON u.id = o.user_id
            WHERE $where
            GROUP BY s.id, s.name, s.county
            HAVING total_officers > 0
            ORDER BY avg_case_load DESC
        ", $params);

        $summaryWhere = "1=1";
        $summaryParams = [];
        if ($county) {
            $summaryWhere .= " AND s.county = :county";
            $summaryParams['county'] = $county;
        }

        $nationalSummary = $this->db->fetchOne("
            SELECT
                COUNT(DISTINCT o.id) as total_officers_national,
                AVG(o.current_case_load) as national_avg_case_load,
                COUNT(CASE WHEN o.current_case_load > 15 THEN 1 END) as total_overloaded,
                COUNT(CASE WHEN o.current_case_load = 0 THEN 1 END) as total_idle,
                MAX(o.current_case_load) as max_case_load,
                MIN(o.current_case_load) as min_case_load
            FROM officers o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN stations s ON u.station_id = s.id
            WHERE u.is_active = 1 AND ($summaryWhere)
        ", $summaryParams);

        return [
            'type' => 'Officer Performance Report',
            'timeframe' => $timeframe,
            'performance_by_station' => $performanceStats,
            'county_summary' => $nationalSummary,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate crime hotspots report
     */
    public function generateHotspotsReport($timeframe = 30, $county = null) {
        $where = "created_at >= DATE_SUB(NOW(), INTERVAL :timeframe3 DAY)";
        $params = [
            'timeframe1' => $timeframe,
            'timeframe2' => $timeframe,
            'timeframe3' => $timeframe
        ];

        if ($county) {
            $where .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        $hotspots = $this->db->fetchAll("
            SELECT
                incident_location_county,
                incident_location_constituency,
                category,
                COUNT(*) as case_count,
                ROUND(COUNT(*) / :timeframe1 * 30, 1) as cases_per_month,
                ROUND(COUNT(*) * 100.0 / (
                    SELECT COUNT(*) FROM cases
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL :timeframe2 DAY)
                     AND incident_location_county = c.incident_location_county
                ), 2) as percentage_of_county
            FROM cases c
            WHERE $where
             GROUP BY incident_location_county, incident_location_constituency, category
            HAVING case_count >= 5
            ORDER BY case_count DESC, cases_per_month DESC
            LIMIT 20
        ", $params);

        return [
            'type' => 'Crime Hotspots Report',
            'timeframe' => $timeframe,
            'hotspots' => $hotspots,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Export report data as CSV
     */
    public function exportAsCSV($reportData, $filename = null) {
        if (!$filename) {
            $filename = 'national_report_' . date('Y-m-d') . '.csv';
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Write header
        fputcsv($output, ['National Police Report - Generated: ' . $reportData['generated_at']]);
        fputcsv($output, []);

        // Write overall statistics
        if (isset($reportData['overall_statistics'])) {
            fputcsv($output, ['OVERALL STATISTICS']);
            foreach ($reportData['overall_statistics'] as $key => $value) {
                fputcsv($output, [ucwords(str_replace('_', ' ', $key)), $value]);
            }
            fputcsv($output, []);
        }

        // Write county breakdown
        if (isset($reportData['county_breakdown'])) {
            fputcsv($output, ['COUNTY BREAKDOWN']);
            if (!empty($reportData['county_breakdown'])) {
                $headers = array_keys($reportData['county_breakdown'][0]);
                fputcsv($output, $headers);
                foreach ($reportData['county_breakdown'] as $row) {
                    fputcsv($output, $row);
                }
            }
            fputcsv($output, []);
        }

        fclose($output);
        exit;
    }
}
?>