<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class CrimeAnalyzer {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findHotspots($timeframe = 90, $minCases = 10) {
    $sql = "SELECT 
                location_county,
                location_constituency, 
                category,
                COUNT(*) as case_count,
                COUNT(*) / :timeframe1 * 30 as cases_per_month,
                ROUND(COUNT(*) * 100.0 / (
                    SELECT COUNT(*) FROM cases 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL :timeframe2 DAY)
                ), 2) as percentage_of_total
            FROM cases 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :timeframe3 DAY)
            GROUP BY location_county, location_constituency, category
            HAVING case_count >= :min_cases
            ORDER BY case_count DESC, cases_per_month DESC";

    $hotspots = $this->db->fetchAll($sql, [
        'timeframe1' => $timeframe,
        'timeframe2' => $timeframe, 
        'timeframe3' => $timeframe,
        'min_cases' => $minCases
    ]);

    foreach ($hotspots as &$hotspot) {
        $hotspot['severity'] = $this->classifyHotspotSeverity($hotspot['cases_per_month']);
        $hotspot['color'] = $this->getHotspotColor($hotspot['severity']);
    }

    return $hotspots;
}

    public function analyzePeakTimes($category = null, $location = null, $days = 30) {
        $whereConditions = ["created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)"];
        $params = ['days' => $days];

        if ($category) {
            $whereConditions[] = "category = :category";
            $params['category'] = $category;
        }

        if ($location) {
            $whereConditions[] = "(location_county = :location OR location_constituency = :location)";
            $params['location'] = $location;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT 
                    HOUR(created_at) as hour_of_day,
                    DAYOFWEEK(created_at) as day_of_week,
                    COUNT(*) as case_count,
                    category,
                    COUNT(*) / :days as daily_average
                FROM cases 
                WHERE $whereClause
                GROUP BY HOUR(created_at), DAYOFWEEK(created_at), category
                ORDER BY case_count DESC";

        $timeAnalysis = $this->db->fetchAll($sql, $params);

        $hourlyTrends = [];
        foreach ($timeAnalysis as $record) {
            $hour = $record['hour_of_day'];
            if (!isset($hourlyTrends[$hour])) {
                $hourlyTrends[$hour] = ['hour' => $hour, 'total_cases' => 0, 'categories' => []];
            }
            $hourlyTrends[$hour]['total_cases'] += $record['case_count'];
            $hourlyTrends[$hour]['categories'][$record['category']] = ($hourlyTrends[$hour]['categories'][$record['category']] ?? 0) + $record['case_count'];
        }

        $peakHours = array_slice(array_values(array_sort($hourlyTrends, function($a, $b) {
            return $b['total_cases'] - $a['total_cases'];
        })), 0, 5);

        return [
            'hourly_trends' => array_values($hourlyTrends),
            'peak_hours' => $peakHours,
            'raw_data' => $timeAnalysis
        ];
    }

    public function recommendDeployment($stationId = null, $timeframe = 30) {
        $recommendations = [];

        $hotspots = $this->findHotspots($timeframe, 5);

        if ($stationId) {
            $station = $this->db->fetchOne("SELECT county, constituency FROM stations WHERE id = :id", ['id' => $stationId]);
            if ($station) {
                $hotspots = array_filter($hotspots, function($hotspot) use ($station) {
                    return $hotspot['location_county'] === $station['county'] || 
                           $hotspot['location_constituency'] === $station['constituency'];
                });
            }
        }

        $currentAllocation = $this->getCurrentResourceAllocation($stationId);

        foreach ($hotspots as $hotspot) {
            if ($hotspot['severity'] === 'high' || $hotspot['severity'] === 'critical') {
                $recommendation = [
                    'area' => $hotspot['location_constituency'] . ', ' . $hotspot['location_county'],
                    'crime_type' => $hotspot['category'],
                    'case_count' => $hotspot['case_count'],
                    'cases_per_month' => round($hotspot['cases_per_month'], 1),
                    'severity' => $hotspot['severity'],
                    'action' => $this->generateRecommendationAction($hotspot, $currentAllocation),
                    'priority' => $this->calculatePriority($hotspot)
                ];

                $peakTimes = $this->analyzePeakTimes($hotspot['category'], $hotspot['location_constituency'], $timeframe);
                $recommendation['peak_hours'] = $this->formatPeakHours($peakTimes['peak_hours']);

                $recommendations[] = $recommendation;
            }
        }

        usort($recommendations, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });

        return array_slice($recommendations, 0, 10);
    }

    public function analyzeTrends($filters = []) {
        $timeframe = $filters['timeframe'] ?? 90;
        $comparison_period = $filters['comparison_period'] ?? 90;

        $currentStats = $this->getPeriodStats($timeframe);

        $previousStats = $this->getPeriodStats($comparison_period, $timeframe);

        $trends = [
            'current_period' => $currentStats,
            'previous_period' => $previousStats,
            'changes' => []
        ];

        foreach ($currentStats as $key => $current) {
            $previous = $previousStats[$key] ?? 0;
            $change = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;

            $trends['changes'][$key] = [
                'value' => $change,
                'direction' => $change > 0 ? 'increase' : ($change < 0 ? 'decrease' : 'stable'),
                'percentage' => round(abs($change), 1)
            ];
        }

        return $trends;
    }

    public function analyzeResolutionRates($filters = []) {
        $whereConditions = ['1=1'];
        $params = [];

        if (!empty($filters['station_id'])) {
            $whereConditions[] = "station_id = :station_id";
            $params['station_id'] = $filters['station_id'];
        }

        if (!empty($filters['timeframe'])) {
            $whereConditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)";
            $params['timeframe'] = $filters['timeframe'];
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT 
                    category,
                    COUNT(*) as total_cases,
                    COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                    ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
                    AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time,
                    COUNT(CASE WHEN actual_resolution_hours <= estimated_resolution_hours THEN 1 END) as on_time_resolutions,
                    ROUND(COUNT(CASE WHEN actual_resolution_hours <= estimated_resolution_hours THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN status = 'closed' THEN 1 END), 0), 1) as on_time_rate
                FROM cases 
                WHERE $whereClause
                GROUP BY category
                ORDER BY resolution_rate DESC";

        $analysis = $this->db->fetchAll($sql, $params);

        foreach ($analysis as &$item) {
            $item['performance'] = $this->classifyPerformance($item['resolution_rate'], $item['on_time_rate']);
        }

        return $analysis;
    }

    public function generateAlerts($stationId = null) {
        $alerts = [];

        $crimeSpikes = $this->detectCrimeSpikes($stationId);
        foreach ($crimeSpikes as $spike) {
            $alerts[] = [
                'type' => 'crime_spike',
                'severity' => 'high',
                'title' => 'Crime Spike Detected',
                'message' => "Significant increase in {$spike['category']} cases in {$spike['area']} ({$spike['increase']}% increase)",
                'area' => $spike['area'],
                'category' => $spike['category'],
                'action_required' => true,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        $resourceShortages = $this->detectResourceShortages($stationId);
        foreach ($resourceShortages as $shortage) {
            $alerts[] = [
                'type' => 'resource_shortage',
                'severity' => 'medium',
                'title' => 'Resource Shortage Alert',
                'message' => "High case load detected: {$shortage['officer_count']} officers handling {$shortage['case_count']} active cases",
                'station_id' => $shortage['station_id'],
                'station_name' => $shortage['station_name'],
                'action_required' => true,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        $resolutionAlerts = $this->detectResolutionDelays($stationId);
        foreach ($resolutionAlerts as $alert) {
            $alerts[] = [
                'type' => 'resolution_delay',
                'severity' => 'medium',
                'title' => 'Resolution Delay Alert',
                'message' => "Average resolution time exceeding targets in {$alert['category']} cases ({$alert['avg_time']} hours vs {$alert['target']} hours target)",
                'category' => $alert['category'],
                'action_required' => false,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        usort($alerts, function($a, $b) {
            $severityOrder = ['critical' => 3, 'high' => 2, 'medium' => 1, 'low' => 0];
            $severityDiff = $severityOrder[$b['severity']] - $severityOrder[$a['severity']];
            return $severityDiff !== 0 ? $severityDiff : strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $alerts;
    }

    private function classifyHotspotSeverity($casesPerMonth) {
        if ($casesPerMonth >= 20) return 'critical';
        if ($casesPerMonth >= 15) return 'high';
        if ($casesPerMonth >= 10) return 'medium';
        return 'low';
    }

    private function getHotspotColor($severity) {
        return [
            'critical' => '#8B0000',
            'high' => '#DC143C',
            'medium' => '#FF8C00',
            'low' => '#32CD32'
        ][$severity] ?? '#32CD32';
    }

    private function generateRecommendationAction($hotspot, $currentAllocation) {
        $actions = [];

        if ($hotspot['severity'] === 'critical') {
            $actions[] = "Deploy additional 2-3 patrol units immediately";
            $actions[] = "Increase foot patrols by 50%";
        } elseif ($hotspot['severity'] === 'high') {
            $actions[] = "Increase patrol frequency by 40%";
            $actions[] = "Deploy 1-2 additional officers during peak hours";
        }

        if (in_array($hotspot['category'], ['Domestic Violence', 'Sexual Offenses'])) {
            $actions[] = "Assign specialized response team";
        }

        if (in_array($hotspot['category'], ['Theft', 'Burglary'])) {
            $actions[] = "Coordinate with community watch groups";
            $actions[] = "Increase surveillance in commercial areas";
        }

        return implode('; ', $actions);
    }

    private function calculatePriority($hotspot) {
        $priority = $hotspot['cases_per_month'] * 2;

        if (in_array($hotspot['category'], ['Assault', 'Domestic Violence', 'Sexual Offenses'])) {
            $priority *= 1.5;
        }

        if ($hotspot['severity'] === 'critical') {
            $priority *= 2;
        } elseif ($hotspot['severity'] === 'high') {
            $priority *= 1.3;
        }

        return round($priority);
    }

    private function getCurrentResourceAllocation($stationId) {
        $sql = "SELECT 
                    COUNT(o.id) as total_officers,
                    AVG(o.current_case_load) as avg_case_load,
                    COUNT(CASE WHEN o.current_case_load > 10 THEN 1 END) as overloaded_officers
                FROM officers o
                JOIN users u ON o.user_id = u.id";

        $params = [];
        if ($stationId) {
            $sql .= " WHERE u.station_id = :station_id";
            $params['station_id'] = $stationId;
        }

        return $this->db->fetchOne($sql, $params);
    }

    private function getPeriodStats($days, $offset = 0) {
        $sql = "SELECT 
                    COUNT(*) as total_cases,
                    COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                    COUNT(CASE WHEN category = 'Theft' THEN 1 END) as theft_cases,
                    COUNT(CASE WHEN category = 'Assault' THEN 1 END) as assault_cases,
                    COUNT(CASE WHEN category = 'Domestic Violence' THEN 1 END) as domestic_violence_cases,
                    AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
                FROM cases 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :end_offset DAY)
                AND created_at <= DATE_SUB(NOW(), INTERVAL :start_offset DAY)";

        return $this->db->fetchOne($sql, [
            'end_offset' => $offset,
            'start_offset' => $offset + $days
        ]);
    }

    private function detectCrimeSpikes($stationId) {
    $sql = "SELECT 
                location_constituency as area,
                category,
                COUNT(*) as recent_cases,
                (SELECT COUNT(*) FROM cases c2 
                 WHERE c2.location_constituency = c1.location_constituency 
                 AND c2.category = c1.category
                 AND c2.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                 AND c2.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)) as previous_cases
            FROM cases c1
            WHERE c1.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

    $params = [];
    if ($stationId) {
        $sql .= " AND c1.station_id = :station_id";
        $params['station_id'] = $stationId;
    }

    $sql .= " GROUP BY location_constituency, category
              HAVING COUNT(*) >= 3 AND previous_cases > 0
              AND (COUNT(*) / previous_cases) >= 1.5
              ORDER BY (COUNT(*) / previous_cases) DESC";

    $spikes = $this->db->fetchAll($sql, $params);

    foreach ($spikes as &$spike) {
        $spike['increase'] = round((($spike['recent_cases'] - $spike['previous_cases']) / $spike['previous_cases']) * 100, 1);
    }

    return $spikes;
}

    private function detectResourceShortages($stationId) {
        $sql = "SELECT 
                    s.id as station_id,
                    s.name as station_name,
                    COUNT(DISTINCT o.id) as officer_count,
                    COUNT(c.id) as case_count,
                    ROUND(COUNT(c.id) / NULLIF(COUNT(DISTINCT o.id), 0), 1) as cases_per_officer
                FROM stations s
                LEFT JOIN users u ON s.id = u.station_id AND u.role = 'officer' AND u.is_active = 1
                LEFT JOIN officers o ON u.id = o.user_id
                LEFT JOIN cases c ON o.id = c.assigned_officer_id AND c.status NOT IN ('closed')";

        $params = [];
        if ($stationId) {
            $sql .= " WHERE s.id = :station_id";
            $params['station_id'] = $stationId;
        }

        $sql .= " GROUP BY s.id, s.name
                  HAVING cases_per_officer > 12 OR (officer_count > 0 AND case_count > officer_count * 10)
                  ORDER BY cases_per_officer DESC";

        return $this->db->fetchAll($sql, $params);
    }

    private function detectResolutionDelays($stationId) {
    $sql = "SELECT 
                category,
                AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(closed_at, NOW()))) as avg_time,
                AVG(estimated_resolution_hours) as target,
                COUNT(*) as case_count
            FROM cases
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

    $params = [];
    if ($stationId) {
        $sql .= " AND station_id = :station_id";
        $params['station_id'] = $stationId;
    }

    $sql .= " GROUP BY category
              HAVING AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(closed_at, NOW()))) > AVG(estimated_resolution_hours) * 1.2 
              AND COUNT(*) >= 5
              ORDER BY (AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(closed_at, NOW()))) / AVG(estimated_resolution_hours)) DESC";

    $delays = $this->db->fetchAll($sql, $params);

    foreach ($delays as &$delay) {
        $delay['avg_time'] = round($delay['avg_time'], 1);
        $delay['target'] = round($delay['target'], 1);
    }

    return $delays;
}

    private function formatPeakHours($peakHours) {
        $formatted = [];
        foreach ($peakHours as $hour) {
            $startHour = $hour['hour'];
            $endHour = ($startHour + 1) % 24;
            $formatted[] = sprintf("%02d:00-%02d:00", $startHour, $endHour);
        }
        return implode(', ', $formatted);
    }

    private function classifyPerformance($resolutionRate, $onTimeRate) {
        if ($resolutionRate >= EXCELLENT_RESOLUTION_RATE && $onTimeRate >= 80) {
            return 'excellent';
        } elseif ($resolutionRate >= GOOD_RESOLUTION_RATE && $onTimeRate >= 70) {
            return 'good';
        } elseif ($resolutionRate >= POOR_RESOLUTION_RATE) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    public function getCrimeDensityMap($filters = []) {
    $timeframe = $filters['timeframe'] ?? 30;

    $whereConditions = ["created_at >= DATE_SUB(NOW(), INTERVAL :timeframe1 DAY)"];
    $params = ['timeframe1' => $timeframe, 'timeframe2' => $timeframe];

    if (!empty($filters['category'])) {
        $whereConditions[] = "category = :category";
        $params['category'] = $filters['category'];
    }

    if (!empty($filters['county'])) {
        $whereConditions[] = "location_county = :county";
        $params['county'] = $filters['county'];
    }

    $whereClause = implode(' AND ', $whereConditions);

    // FIX: Use unique parameter names for each timeframe usage
    $sql = "SELECT 
                location_county as county,
                location_constituency as constituency,
                category,
                COUNT(*) as case_count,
                COUNT(*) / :timeframe2 * 30 as cases_per_month,
                ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(closed_at, NOW()))), 1) as avg_resolution_hours
            FROM cases 
            WHERE $whereClause
            GROUP BY location_county, location_constituency, category
            ORDER BY case_count DESC";

    $densityData = $this->db->fetchAll($sql, $params);

    foreach ($densityData as &$area) {
        $area['density_level'] = $this->classifyDensityLevel($area['cases_per_month']);
        $area['color'] = $this->getDensityColor($area['density_level']);
    }

    return $densityData;
}

    private function classifyDensityLevel($casesPerMonth) {
        if ($casesPerMonth >= 25) return 'very_high';
        if ($casesPerMonth >= 20) return 'high';
        if ($casesPerMonth >= 15) return 'medium';
        if ($casesPerMonth >= 10) return 'low';
        return 'very_low';
    }

    private function getDensityColor($level) {
        return [
            'very_high' => '#800000',
            'high' => '#FF0000',
            'medium' => '#FF8C00',
            'low' => '#FFD700',
            'very_low' => '#32CD32'
        ][$level] ?? '#32CD32';
    }

    public function generateMonthlyReport($year = null, $month = null, $stationId = null) {
        $year = $year ?? date('Y');
        $month = $month ?? date('m');

        $whereConditions = ["YEAR(created_at) = :year", "MONTH(created_at) = :month"];
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
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate
            FROM cases 
            WHERE $whereClause
            GROUP BY category
            ORDER BY case_count DESC", $params);

        $locationStats = $this->db->fetchAll("
            SELECT 
                location_county as county,
                location_constituency as constituency,
                COUNT(*) as case_count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM cases WHERE $whereClause), 1) as percentage
            FROM cases 
            WHERE $whereClause
            GROUP BY location_county, location_constituency
            ORDER BY case_count DESC
            LIMIT 10", $params);

        $dailyTrends = $this->db->fetchAll("
            SELECT 
                DAY(created_at) as day,
                COUNT(*) as case_count
            FROM cases 
            WHERE $whereClause
            GROUP BY DAY(created_at)
            ORDER BY DAY(created_at)", $params);

        return [
            'period' => [
                'year' => $year,
                'month' => $month,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year))
            ],
            'overall_stats' => $overallStats,
            'category_breakdown' => $categoryStats,
            'location_breakdown' => $locationStats,
            'daily_trends' => $dailyTrends,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    public function predictTrends($category = null, $location = null, $forecastDays = 30) {
        $historicalData = $this->getHistoricalTrendData($category, $location, 90);

        if (count($historicalData) < 7) {
            return ['error' => 'Insufficient historical data for prediction'];
        }

        $predictions = [];
        $days = array_keys($historicalData);
        $values = array_values($historicalData);

        $n = count($days);
        $sumX = array_sum($days);
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $days[$i] * $values[$i];
            $sumXX += $days[$i] * $days[$i];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        $lastDay = max($days);
        for ($i = 1; $i <= $forecastDays; $i++) {
            $futureDay = $lastDay + $i;
            $prediction = max(0, round($slope * $futureDay + $intercept));
            $predictions[date('Y-m-d', strtotime("+$i days"))] = $prediction;
        }

        return [
            'historical_data' => $historicalData,
            'predictions' => $predictions,
            'trend' => $slope > 0.1 ? 'increasing' : ($slope < -0.1 ? 'decreasing' : 'stable'),
            'confidence' => 'low'
        ];
    }

    private function getHistoricalTrendData($category, $location, $days) {
        $whereConditions = ["created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)"];
        $params = ['days' => $days];

        if ($category) {
            $whereConditions[] = "category = :category";
            $params['category'] = $category;
        }

        if ($location) {
            $whereConditions[] = "(location_county = :location OR location_constituency = :location)";
            $params['location'] = $location;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as case_count
                FROM cases 
                WHERE $whereClause
                GROUP BY DATE(created_at)
                ORDER BY DATE(created_at)";

        $results = $this->db->fetchAll($sql, $params);

        $data = [];
        foreach ($results as $result) {
            $dayNumber = (strtotime($result['date']) - strtotime("-{$days} days")) / (24 * 60 * 60);
            $data[$dayNumber] = (int)$result['case_count'];
        }

        return $data;
    }
}

if (!function_exists('array_sort')) {
    function array_sort($array, $callback) {
        uasort($array, $callback);
        return $array;
    }
}
?>
