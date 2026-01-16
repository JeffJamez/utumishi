<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/CrimeAnalyzer.php';

class PredictiveAnalytics {
    private $db;
    private $crimeAnalyzer;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->crimeAnalyzer = new CrimeAnalyzer();
    }

    /**
     * Generate comprehensive predictive dashboard data
      */
     public function getDashboardPredictions($stationId = null, $days = 7) {
         return [
             'crime_forecast' => $this->generateCrimeForecast($stationId, $days),
             'hotspot_predictions' => $this->predictHotspots($stationId),
             'risk_calendar' => $this->generateRiskCalendar($stationId, $days),
             'early_warnings' => $this->generateEarlyWarnings($stationId),
             'patrol_optimization' => $this->getPatrolOptimization($stationId)
         ];
     }

     /**
      * Generate county-level predictive dashboard data
      */
     public function getCountyDashboardPredictions($county, $days = 7) {
         return [
             'crime_forecast' => $this->generateCrimeForecast(null, $days, $county),
             'hotspot_predictions' => $this->predictHotspots(null, 60, $county),
             'early_warnings' => $this->generateEarlyWarnings(null, $county),
             'patrol_optimization' => $this->getPatrolOptimization(null, $county)
         ];
     }

    /**
     * Generate 7-day crime forecast
     */
     public function generateCrimeForecast($stationId = null, $days = 7, $county = null) {
        $forecast = [];
        $currentDate = new DateTime();

        // Cache trend and seasonal for all days
        $trendMultiplier = $this->calculateTrendMultiplier($stationId, $county);

        // Pre-fetch categories and hours for all days of week
        $categoriesByDay = [];
        $hoursByDay = [];
        for ($d = 0; $d < 7; $d++) {
            $categoriesByDay[$d] = $this->predictCategoryBreakdown($stationId, $d, $county);
            $hoursByDay[$d] = $this->predictPeakHours($stationId, $d, $county);
        }

        for ($i = 1; $i <= $days; $i++) {
            $targetDate = clone $currentDate;
            $targetDate->add(new DateInterval("P{$i}D"));

            $dateStr = $targetDate->format('Y-m-d');
            $dayOfWeek = $targetDate->format('w');

            // Get historical average for this day of week
            $historicalAvg = $this->getHistoricalAverage($stationId, $dayOfWeek, $county);

            // Apply trend and seasonal adjustments
            $seasonalMultiplier = $this->getSeasonalMultiplier($targetDate);

            $predictedCases = round($historicalAvg * $trendMultiplier * $seasonalMultiplier);

            // Generate up to 3 specific predicted cases
            $predictedCaseDetails = [];
            if ($predictedCases > 0) {
                $categories = $categoriesByDay[$dayOfWeek];
                $numCases = min(3, $predictedCases);
                for ($j = 0; $j < $numCases; $j++) {
                    $category = $categories[$j % count($categories)]['category'] ?? 'Unknown';
                    // Simple severity assignment based on category
                    $severity = 'medium';
                    if (in_array($category, ['Murder', 'Rape', 'Assault', 'Domestic Violence'])) {
                        $severity = 'high';
                    } elseif (in_array($category, ['Theft', 'Burglary', 'Robbery'])) {
                        $severity = 'medium';
                    } else {
                        $severity = 'low';
                    }
                    $predictedCaseDetails[] = ['category' => $category, 'severity' => $severity];
                }
            }

            $forecast[] = [
                'date' => $dateStr,
                'day_name' => $targetDate->format('l'),
                'predicted_cases' => $predictedCaseDetails,
                'confidence_level' => $this->calculateConfidence($historicalAvg, $trendMultiplier),
                'risk_level' => $this->classifyDailyRisk($predictedCases),
                'peak_hours' => $hoursByDay[$dayOfWeek]
            ];
        }

        return $forecast;
    }

    /**
     * Predict emerging hotspots
     */
     public function predictHotspots($stationId = null, $confidence_threshold = 60, $county = null) {
        $predictions = [];

        // Analyze crime acceleration patterns
        $accelerationData = $this->analyzeAccelerationPatterns($stationId, $county);
        
        foreach ($accelerationData as $area) {
            $riskFactors = $this->calculateRiskFactors($area);
            $confidenceScore = $this->calculateHotspotConfidence($riskFactors);
            
            if ($confidenceScore >= $confidence_threshold) {
                $predictions[] = [
                    'location' => $area['incident_location_constituency'],
                    'county' => $area['incident_location_county'],
                    'predicted_category' => $area['dominant_category'],
                    'current_trend' => $area['trend_direction'],
                    'acceleration_rate' => $area['acceleration_rate'],
                    'confidence_score' => $confidenceScore,
                    'estimated_timeline' => $this->estimateEmergenceTimeline($area),
                    'recommended_actions' => $this->generateHotspotActions($area),
                    'risk_factors' => $riskFactors
                ];
            }
        }

        // Sort by confidence and acceleration
        usort($predictions, function($a, $b) {
            return ($b['confidence_score'] * $b['acceleration_rate']) - ($a['confidence_score'] * $a['acceleration_rate']);
        });

        return array_slice($predictions, 0, 10); // Already limited in query
    }



    /**
     * Generate risk calendar
     */
    public function generateRiskCalendar($stationId, $days = 30) {
        $calendar = [];
        
        for ($i = 1; $i <= $days; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            $dayOfWeek = date('w', strtotime($date));
            
            // Calculate various risk factors
            $crimeRisk = $this->calculateCrimeRisk($stationId, $dayOfWeek);
            $resourceRisk = $this->calculateResourceRisk($stationId, $date);
            $seasonalRisk = $this->calculateSeasonalRisk($date);
            
            $overallRisk = ($crimeRisk * 0.5) + ($resourceRisk * 0.3) + ($seasonalRisk * 0.2);
            
            $calendar[] = [
                'date' => $date,
                'day_name' => date('l', strtotime($date)),
                'overall_risk' => round($overallRisk, 1),
                'risk_level' => $this->classifyRiskLevel($overallRisk),
                'crime_risk' => $crimeRisk,
                'resource_risk' => $resourceRisk,
                'seasonal_risk' => $seasonalRisk,
                'special_factors' => $this->getSpecialFactors($date)
            ];
        }

        return $calendar;
    }

    /**
     * Generate early warning alerts
     */
    public function generateEarlyWarnings($stationId, $county = null) {
        $warnings = [];

        // Crime spike predictions
        $spikePredictions = $this->predictCrimeSpikes($stationId, $county);
        foreach ($spikePredictions as $spike) {
            $warnings[] = [
                'type' => 'crime_spike_prediction',
                'severity' => $spike['severity'],
                'title' => 'Predicted Crime Spike',
                'message' => "Crime spike predicted in {$spike['location']} for {$spike['category']} - {$spike['probability']}% probability",
                'location' => $spike['location'],
                'category' => $spike['category'],
                'probability' => $spike['probability'],
                'estimated_date' => $spike['estimated_date'],
                'prevention_actions' => $spike['prevention_actions']
            ];
        }

        // Resource shortage predictions
        $resourceWarnings = $this->predictResourceShortages($stationId, $county);
        foreach ($resourceWarnings as $warning) {
            $warnings[] = [
                'type' => 'resource_shortage_prediction',
                'severity' => 'medium',
                'title' => 'Predicted Resource Shortage',
                'message' => "Officer shortage predicted on {$warning['date']} - {$warning['shortage']} officers needed",
                'date' => $warning['date'],
                'shortage' => $warning['shortage'],
                'mitigation_actions' => $warning['mitigation_actions']
            ];
        }

        // Sort by severity and probability
        usort($warnings, function($a, $b) {
            $severityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            return $severityOrder[$b['severity']] - $severityOrder[$a['severity']];
        });

        return $warnings;
    }

    /**
     * Get patrol optimization recommendations
     */
    public function getPatrolOptimization($stationId, $county = null) {
        $recommendations = [];
        
        // Get high-risk areas and times
        $riskAreas = $this->identifyHighRiskAreas($stationId, $county);
        
        foreach ($riskAreas as $area) {
            $recommendations[] = [
                'area' => $area['location'],
                'risk_score' => $area['risk_score'],
                'optimal_patrol_times' => $this->calculateOptimalPatrolTimes($area),
                'recommended_frequency' => $this->calculatePatrolFrequency($area['risk_score']),
                'patrol_type' => $this->recommendPatrolType($area),
                'officer_requirements' => $this->calculatePatrolOfficers($area['risk_score'])
            ];
        }

        return $recommendations;
    }

    /**
     * HELPER METHODS
     */

    private function getHistoricalAverage($stationId, $dayOfWeek, $county = null) {
        $whereConditions = ["DAYOFWEEK(created_at) = :day_of_week"];
        $params = ['day_of_week' => $dayOfWeek];

        if ($stationId) {
            $whereConditions[] = "station_id = :station_id";
            $params['station_id'] = $stationId;
        } elseif ($county) {
            $whereConditions[] = "incident_location_county = :county";
            $params['county'] = $county;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $result = $this->db->fetchOne("
            SELECT AVG(daily_count) as avg_cases
            FROM (
                SELECT DATE(created_at) as date, COUNT(*) as daily_count
                FROM cases
                WHERE $whereClause
                AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY DATE(created_at)
            ) daily_counts
        ", $params);

        return $result['avg_cases'] ?? 0;
    }

     private function calculateTrendMultiplier($stationId, $county = null) {
        // Compare recent 30 days to previous 30 days
        $recentCount = $this->getCaseCount($stationId, 30, 0, $county);
        $previousCount = $this->getCaseCount($stationId, 30, 30, $county);

        if ($previousCount > 0) {
            return $recentCount / $previousCount;
        }

        return 1.0;
    }

    private function getSeasonalMultiplier($date) {
        $month = $date->format('n');
        $dayOfYear = $date->format('z');
        
        // Simple seasonal patterns (could be enhanced with historical data)
        $seasonalFactors = [
            12 => 1.15, // December - holidays
            1 => 1.05,   // January - post-holiday
            11 => 1.10,  // November - pre-holiday
            6 => 0.95,   // June - summer
            7 => 0.90,   // July - summer
            8 => 0.95    // August - summer
        ];

        return $seasonalFactors[$month] ?? 1.0;
    }

    private function getCaseCount($stationId, $days, $offset = 0, $county = null) {
        $whereConditions = [
            "created_at >= DATE_SUB(NOW(), INTERVAL :end_days DAY)",
            "created_at < DATE_SUB(NOW(), INTERVAL :start_days DAY)"
        ];
        $params = [
            'end_days' => $offset + $days,
            'start_days' => $offset
        ];

        if ($stationId) {
            $whereConditions[] = "station_id = :station_id";
            $params['station_id'] = $stationId;
        } elseif ($county) {
            $whereConditions[] = "incident_location_county = :county";
            $params['county'] = $county;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $result = $this->db->fetchOne("
            SELECT COUNT(*) as case_count
            FROM cases
            WHERE $whereClause
        ", $params);

        return $result['case_count'] ?? 0;
    }

    private function classifyRiskLevel($score) {
        if ($score >= 80) return 'critical';
        if ($score >= 60) return 'high';
        if ($score >= 40) return 'medium';
        if ($score >= 20) return 'low';
        return 'minimal';
    }

    private function classifyDailyRisk($predictedCases) {
        if ($predictedCases >= 15) return 'high';
        if ($predictedCases >= 10) return 'medium';
        if ($predictedCases >= 5) return 'low';
        return 'minimal';
    }





    private function calculateConfidence($historicalAvg, $trendMultiplier) {
        $dataPoints = max(1, $historicalAvg);
        $stability = 1 / (1 + abs($trendMultiplier - 1));
        
        if ($dataPoints >= 10) return min(90, 60 + ($stability * 30));
        if ($dataPoints >= 5) return min(75, 50 + ($stability * 25));
        return min(60, 30 + ($stability * 30));
    }

     private function predictPeakHours($stationId, $dayOfWeek, $county = null) {
        $whereConditions = ["DAYOFWEEK(created_at) = :day_of_week"];
        $params = ['day_of_week' => $dayOfWeek];

        if ($stationId) {
            $whereConditions[] = "station_id = :station_id";
            $params['station_id'] = $stationId;
        } elseif ($county) {
            $whereConditions[] = "incident_location_county = :county";
            $params['county'] = $county;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $result = $this->db->fetchAll("
            SELECT HOUR(created_at) as hour, COUNT(*) as count
            FROM cases
            WHERE $whereClause
            AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY HOUR(created_at)
            ORDER BY count DESC
            LIMIT 3
        ", $params);

        return array_map(function($r) { return $r['hour']; }, $result);
    }

     private function predictCategoryBreakdown($stationId, $dayOfWeek, $county = null) {
        $whereConditions = ["DAYOFWEEK(created_at) = :day_of_week"];
        $params = ['day_of_week' => $dayOfWeek];

        if ($stationId) {
            $whereConditions[] = "station_id = :station_id";
            $params['station_id'] = $stationId;
        } elseif ($county) {
            $whereConditions[] = "incident_location_county = :county";
            $params['county'] = $county;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $result = $this->db->fetchAll("
            SELECT category, COUNT(*) as count
            FROM cases
            WHERE $whereClause
            AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY category
            ORDER BY count DESC
            LIMIT 5
        ", $params);

        return $result;
    }

     private function analyzeAccelerationPatterns($stationId, $county = null) {
        $whereClause = "";
        $params = [];

        if ($stationId) {
            $whereClause = "AND station_id = :station_id";
            $params['station_id'] = $stationId;
        } elseif ($county) {
            $whereClause = "AND incident_location_county = :county";
            $params['county'] = $county;
        }

        return $this->db->fetchAll("
            SELECT
                incident_location_constituency,
                incident_location_county,
                category as dominant_category,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_week,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                            AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as previous_week,
                CASE
                    WHEN COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                                AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) > 0
                    THEN (COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) /
                          COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                                AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END)) * 100
                    ELSE 100
                END as acceleration_rate,
                CASE
                    WHEN COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) >
                         COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                                AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END)
                    THEN 'increasing'
                    ELSE 'stable'
                END as trend_direction
            FROM cases
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) $whereClause
            GROUP BY incident_location_constituency, incident_location_county, category
            HAVING recent_week > 0
            ORDER BY acceleration_rate DESC
            LIMIT 50
        ", $params);
    }

    private function calculateRiskFactors($area) {
        return [
            'acceleration' => min(100, $area['acceleration_rate']),
            'volume' => min(100, $area['recent_week'] * 10),
            'trend_strength' => $area['trend_direction'] === 'increasing' ? 80 : 20
        ];
    }

    private function calculateHotspotConfidence($riskFactors) {
        return round(($riskFactors['acceleration'] * 0.4) + 
                    ($riskFactors['volume'] * 0.3) + 
                    ($riskFactors['trend_strength'] * 0.3));
    }

    private function estimateEmergenceTimeline($area) {
        $rate = $area['acceleration_rate'];
        if ($rate > 200) return '3-7 days';
        if ($rate > 150) return '1-2 weeks';
        return '2-4 weeks';
    }

    private function generateHotspotActions($area) {
        $actions = ['Increase patrol frequency'];
        if ($area['acceleration_rate'] > 200) {
            $actions[] = 'Deploy additional officers immediately';
        }
        if (in_array($area['dominant_category'], ['Domestic Violence', 'Assault'])) {
            $actions[] = 'Coordinate with social services';
        }
        return $actions;
    }





    private function calculateOfficerRequirement($expectedCases, $currentWorkload) {
        $casesPerOfficer = 8; // Average cases per officer per day
        return max(1, ceil($expectedCases / $casesPerOfficer));
    }

    private function getProjectedAvailability($stationId, $date) {
        // Simplified - assume 80% of officers available
        $result = $this->db->fetchOne("
            SELECT COUNT(*) as officer_count
            FROM officers o
            JOIN users u ON o.user_id = u.id
            WHERE u.station_id = :station_id AND u.is_active = 1
        ", ['station_id' => $stationId]);

        return round(($result['officer_count'] ?? 5) * 0.8);
    }





    private function calculateCrimeRisk($stationId, $dayOfWeek) {
        return $this->getHistoricalAverage($stationId, $dayOfWeek) * 5; // Scale to 0-100
    }

    private function calculateResourceRisk($stationId, $date) {
        $availability = $this->getProjectedAvailability($stationId, $date);
        $required = $this->calculateOfficerRequirement(10, 5); // Baseline
        return max(0, min(100, (($required - $availability) / $required) * 100));
    }

    private function calculateSeasonalRisk($date) {
        $month = date('n', strtotime($date));
        $seasonalRisks = [12 => 80, 1 => 70, 11 => 60, 6 => 30, 7 => 25, 8 => 30];
        return $seasonalRisks[$month] ?? 50;
    }

    private function getSpecialFactors($date) {
        $factors = [];
        $dayOfWeek = date('w', strtotime($date));
        if ($dayOfWeek == 0 || $dayOfWeek == 6) $factors[] = 'Weekend';
        if (date('d', strtotime($date)) == 1) $factors[] = 'Month start';
        return $factors;
    }

    private function predictCrimeSpikes($stationId) {
        return []; // Placeholder - complex spike prediction logic
    }

    private function predictResourceShortages($stationId) {
        return []; // Placeholder - resource shortage prediction logic
    }

    private function identifyHighRiskAreas($stationId, $county = null) {
        $whereClause = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = [];

        if ($stationId) {
            $whereClause .= " AND station_id = :station_id";
            $params['station_id'] = $stationId;
        } elseif ($county) {
            $whereClause .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }

        return $this->db->fetchAll("
            SELECT
                incident_location_constituency as location,
                COUNT(*) * 10 as risk_score
            FROM cases
            WHERE $whereClause
            GROUP BY incident_location_constituency
            HAVING risk_score > 50
            ORDER BY risk_score DESC
            LIMIT 20
        ", $params);
    }

    private function calculateOptimalPatrolTimes($area) {
        return ['18:00-22:00', '02:00-06:00']; // Placeholder
    }

    private function calculatePatrolFrequency($riskScore) {
        if ($riskScore > 80) return 'Every 30 minutes';
        if ($riskScore > 60) return 'Every hour';
        return 'Every 2 hours';
    }

    private function recommendPatrolType($area) {
        return $area['risk_score'] > 70 ? 'Foot patrol' : 'Vehicle patrol';
    }

    private function calculatePatrolOfficers($riskScore) {
        return $riskScore > 80 ? 3 : ($riskScore > 60 ? 2 : 1);
    }
}
?>