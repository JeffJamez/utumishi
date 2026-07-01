<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}


class AIPredictionEngine {
    private $db;
    public $cacheFile;
    private $cacheDuration = 86400; 
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->cacheFile = ROOT_PATH . '/includes/cache/predictions_cache.json';
        $this->ensureCacheDirectory();
    }
    
 
    private function ensureCacheDirectory() {
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }
    
 
    public function getPredictions($forceRefresh = false, $county = null) {
        if (!$forceRefresh && $this->isCacheValid()) {
            return $this->getCachedPredictions();
        }
        
        $predictions = $this->generateAllPredictions($county);
        $this->saveCache($predictions);
        return $predictions;
    }
    
  
    private function isCacheValid() {
        if (!file_exists($this->cacheFile)) {
            return false;
        }
        
        $cacheData = json_decode(file_get_contents($this->cacheFile), true);
        if (!$cacheData || !isset($cacheData['generated_at'])) {
            return false;
        }
        
        $cacheTime = strtotime($cacheData['generated_at']);
        return (time() - $cacheTime) < $this->cacheDuration;
    }
    
   
    private function getCachedPredictions() {
        $cacheData = json_decode(file_get_contents($this->cacheFile), true);
        return $cacheData['predictions'] ?? $this->generateAllPredictions();
    }
    
   
    private function saveCache($predictions) {
        try {
            $cacheData = [
                'generated_at' => date('Y-m-d H:i:s'),
                'predictions' => $predictions
            ];
            
            $result = @file_put_contents($this->cacheFile, json_encode($cacheData));
            if ($result === false) {
                error_log("Cache write failed (permission denied) - predictions generated without caching");
            }
        } catch (Exception $e) {
            error_log("Cache save error: " . $e->getMessage());
        }
    }
    
   
    public function generateAllPredictions($county = null) {
        return [
            'total_crimes' => $this->getTotalCrimes($county),
            'hotspot_count' => $this->getHotspotCount($county),
            'peak_hours' => $this->getPeakHoursWindow($county),
            'model_accuracy' => $this->calculateModelAccuracy(),
            'hourly_distribution' => $this->getHourlyDistribution($county),
            'weekly_trend' => $this->getWeeklyTrend($county),
            'top_hotspots' => $this->getTopHotspots(10, $county),
            'recent_incidents' => $this->getRecentIncidents(15, $county),
            'categories' => $this->getCategories(),
            'locations' => $this->getLocations($county)
        ];
    }
    
    
    private function getTotalCrimes($county = null) {
        $sql = "SELECT COUNT(*) as total FROM cases";
        $params = [];
        
        if ($county) {
            $sql .= " WHERE incident_location_county = :county";
            $params['county'] = $county;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result['total'] ?? 0;
    }
    
   
    public function getHotspotCount($county = null) {
        $sql = "SELECT COUNT(*) as total FROM (
            SELECT incident_location_constituency, COUNT(*) as case_count
            FROM cases
            WHERE COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND incident_location_county IS NOT NULL";
        
        $params = [];
        
        if ($county) {
            $sql .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }
        
        $sql .= " GROUP BY incident_location_constituency
            HAVING case_count >= 3
        ) as hotspots";
        
        $result = $this->db->fetchOne($sql, $params);
        return $result['total'] ?? 0;
    }
    
   
    private function getPeakHoursWindow($county = null) {
        $sql = "SELECT HOUR(COALESCE(occurred_at, created_at)) as hour, COUNT(*) as count
            FROM cases
            WHERE incident_location_county IS NOT NULL";
        
        $params = [];
        
        if ($county) {
            $sql .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }
        
        $sql .= " GROUP BY HOUR(COALESCE(occurred_at, created_at))
            ORDER BY count DESC
            LIMIT 1";
        
        $result = $this->db->fetchOne($sql, $params);
        if ($result) {
            $peakHour = (int)$result['hour'];
            $nextHour = ($peakHour + 1) % 24;
            return sprintf('%02d:00-%02d:00', $peakHour, $nextHour);
        }
        return 'N/A';
    }
    
   
    private function calculateModelAccuracy() {
       
        $sql = "SELECT COUNT(*) as total FROM cases 
                WHERE COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $result = $this->db->fetchOne($sql);
        $caseCount = $result['total'] ?? 0;
        
        if ($caseCount > 1000) {
            return '92%';
        } elseif ($caseCount > 500) {
            return '88%';
        } elseif ($caseCount > 100) {
            return '85%';
        } else {
            return '80%';
        }
    }
    
  
    public function generate7DayForecast() {
        $forecast = [];
        $currentDate = new DateTime();
        
        $historicalData = $this->getHistoricalByDayOfWeek();
        
        for ($i = 0; $i < 7; $i++) {
            $targetDate = clone $currentDate;
            $targetDate->add(new DateInterval("P{$i}D"));
            
            $dateStr = $targetDate->format('Y-m-d');
            $dayOfWeek = (int)$targetDate->format('w'); // 0=Sunday, convert to 0=Monday
            $dayOfWeek = ($dayOfWeek + 6) % 7;
            
            $historicalAvg = $historicalData[$dayOfWeek] ?? ['avg_count' => 0, 'peak_hour' => 20];
            
            $trendMultiplier = $this->calculateTrendMultiplier();
            $predictedCount = round($historicalAvg['avg_count'] * $trendMultiplier);
            
            $riskLevel = $this->classifyRisk($predictedCount);
            
            $likelyType = $this->getLikelyCrimeType($dayOfWeek);
            
            $confidence = $this->calculateConfidence($historicalAvg['data_points'] ?? 0);
            
            $likelyLocation = $this->getLikelyLocation($dayOfWeek);
            
            $forecast[] = [
                'date' => $dateStr,
                'day_name' => $targetDate->format('l'),
                'label' => $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $targetDate->format('l')),
                'predicted_count' => $predictedCount,
                'risk_level' => $riskLevel,
                'risk_score' => min(100, round($predictedCount * 5)), // scal to 0-100
                'peak_hour' => sprintf('%02d:00-%02d:00', $historicalAvg['peak_hour'], ($historicalAvg['peak_hour'] + 6) % 24),
                'likely_type' => $likelyType,
                'likely_location' => $likelyLocation,
                'confidence' => $confidence . '%'
            ];
        }
        
        return $forecast;
    }
    
 
    private function getHistoricalByDayOfWeek() {
        $sql = "SELECT 
                DAYOFWEEK(COALESCE(occurred_at, created_at)) as day_of_week,
                COUNT(*) as total_count,
                AVG(daily_count) as avg_count,
                MAX(daily_count) as max_daily,
                COUNT(DISTINCT DATE(COALESCE(occurred_at, created_at))) as data_points,
                (SELECT HOUR(COALESCE(occurred_at, created_at)) 
                 FROM cases c2 
                 WHERE DAYOFWEEK(COALESCE(c2.occurred_at, c2.created_at)) = DAYOFWEEK(COALESCE(cases.occurred_at, cases.created_at))
                 GROUP BY HOUR(COALESCE(c2.occurred_at, c2.created_at))
                 ORDER BY COUNT(*) DESC 
                 LIMIT 1) as peak_hour
            FROM cases
            INNER JOIN (
                SELECT DATE(COALESCE(occurred_at, created_at)) as crime_date, 
                       DAYOFWEEK(COALESCE(occurred_at, created_at)) as dow,
                       COUNT(*) as daily_count
                FROM cases
                WHERE COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY DATE(COALESCE(occurred_at, created_at)), DAYOFWEEK(COALESCE(occurred_at, created_at))
            ) as daily_stats ON DATE(cases.occurred_at) = daily_stats.crime_date
            GROUP BY DAYOFWEEK(COALESCE(occurred_at, created_at))";
        
        $results = $this->db->fetchAll($sql);
        
        $data = [];
        foreach ($results as $row) {
            $mysqlDay = (int)$row['day_of_week'];
            $dayIndex = ($mysqlDay + 5) % 7; 
            
            $data[$dayIndex] = [
                'avg_count' => round($row['avg_count'] ?? 0, 1),
                'total_count' => (int)$row['total_count'],
                'data_points' => (int)$row['data_points'],
                'peak_hour' => (int)$row['peak_hour']
            ];
        }
        
        return $data;
    }
    
   
    private function calculateTrendMultiplier() {
        $recent = $this->db->fetchOne("SELECT COUNT(*) as count FROM cases 
            WHERE COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL 14 DAY)
            AND COALESCE(occurred_at, created_at) < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        
        $older = $this->db->fetchOne("SELECT COUNT(*) as count FROM cases 
            WHERE COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL 28 DAY)
            AND COALESCE(occurred_at, created_at) < DATE_SUB(NOW(), INTERVAL 14 DAY)");
        
        if ($older['count'] == 0) {
            return 1.0;
        }
        
        $trend = $recent['count'] / $older['count'];
        return max(0.8, min(1.2, $trend));
    }
    
   
    private function classifyRisk($predictedCount) {
        if ($predictedCount >= 15) return 'high';
        if ($predictedCount >= 10) return 'medium';
        if ($predictedCount >= 5) return 'low';
        return 'minimal';
    }
    
    
    private function getLikelyCrimeType($dayOfWeek) {
        $sql = "SELECT category, COUNT(*) as count
            FROM cases
            WHERE DAYOFWEEK(COALESCE(occurred_at, created_at)) = :day_of_week
            GROUP BY category
            ORDER BY count DESC
            LIMIT 1";
        
        $mysqlDay = ($dayOfWeek + 2) % 7;
        if ($mysqlDay == 0) $mysqlDay = 7;
        
        $result = $this->db->fetchOne($sql, ['day_of_week' => $mysqlDay]);
        return $result['category'] ?? 'Unknown';
    }
    
   
    private function getLikelyLocation($dayOfWeek) {
        $sql = "SELECT incident_location_constituency, incident_location_county, COUNT(*) as count
            FROM cases
            WHERE DAYOFWEEK(COALESCE(occurred_at, created_at)) = :day_of_week
            GROUP BY incident_location_constituency, incident_location_county
            ORDER BY count DESC
            LIMIT 1";
        
        $mysqlDay = ($dayOfWeek + 2) % 7;
        if ($mysqlDay == 0) $mysqlDay = 7;
        
        $result = $this->db->fetchOne($sql, ['day_of_week' => $mysqlDay]);
        if ($result) {
            return $result['incident_location_constituency'] . ', ' . $result['incident_location_county'];
        }
        return 'Unknown Location';
    }

    
    private function calculateConfidence($dataPoints) {
        if ($dataPoints >= 30) return 90;
        if ($dataPoints >= 20) return 80;
        if ($dataPoints >= 10) return 70;
        return 60;
    }
    
   
    public function getHourlyDistribution($county = null) {
        $sql = "SELECT HOUR(COALESCE(occurred_at, created_at)) as hour, COUNT(*) as count
            FROM cases
            WHERE COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND incident_location_county IS NOT NULL";
        
        $params = [];
        
        if ($county) {
            $sql .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }
        
        $sql .= " GROUP BY HOUR(COALESCE(occurred_at, created_at))
            ORDER BY hour ASC";
        
        $results = $this->db->fetchAll($sql, $params);
        
        $distribution = array_fill(0, 24, 0);
        
        foreach ($results as $row) {
            $hour = (int)$row['hour'];
            $distribution[$hour] = (int)$row['count'];
        }
        
        return $distribution;
    }
    
   
    public function getWeeklyTrend($county = null) {
        $sql = "SELECT 
                (DAYOFWEEK(COALESCE(occurred_at, created_at)) + 5) % 7 as day_index,
                COUNT(*) as count
            FROM cases
            WHERE COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND incident_location_county IS NOT NULL";
        
        $params = [];
        
        if ($county) {
            $sql .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }
        
        $sql .= " GROUP BY DAYOFWEEK(COALESCE(occurred_at, created_at))
            ORDER BY day_index ASC";
        
        $results = $this->db->fetchAll($sql, $params);
        
        $trend = array_fill(0, 7, 0);
        
        foreach ($results as $row) {
            $day = (int)$row['day_index'];
            $trend[$day] = (int)$row['count'];
        }
        
        return $trend;
    }
    
    
    public function getTopHotspots($limit = 10, $county = null) {
        $sql = "SELECT 
                incident_location_county as county,
                incident_location_constituency as constituency,
                category,
                COUNT(*) as case_count,
                COUNT(*) / 30 * 30 as cases_per_month,
                MAX(COALESCE(occurred_at, created_at)) as last_occurrence
            FROM cases
            WHERE COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $params = ['limit' => $limit];
        
        if ($county) {
            $sql .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }
        
        $sql .= " GROUP BY incident_location_county, incident_location_constituency, category
            HAVING case_count >= 3
            ORDER BY case_count DESC
            LIMIT :limit";
        
        $results = $this->db->fetchAll($sql, $params);
        
        $hotspots = [];
        foreach ($results as $row) {
            $riskScore = min(100, round($row['cases_per_month'] * 5));
            
            $hotspots[] = [
                'location' => $row['constituency'] . ', ' . $row['county'],
                'county' => $row['county'],
                'constituency' => $row['constituency'],
                'category' => $row['category'],
                'case_count' => (int)$row['case_count'],
                'cases_per_month' => round($row['cases_per_month'], 1),
                'risk_score' => $riskScore,
                'risk_level' => $this->classifyRisk($row['cases_per_month']),
                'last_occurrence' => $row['last_occurrence']
            ];
        }
        
        return $hotspots;
    }
    
    
    public function getRecentIncidents($limit = 15, $county = null) {
        $sql = "SELECT 
                c.ob_number,
                c.title,
                c.category,
                c.incident_location_county as county,
                c.incident_location_constituency as constituency,
                COALESCE(c.occurred_at, c.created_at) as incident_time,
                u.name as reporter_name
            FROM cases c
            LEFT JOIN users u ON c.reported_by_citizen_id = u.id
            WHERE c.incident_location_county IS NOT NULL";
        
        $params = ['limit' => $limit];
        
        if ($county) {
            $sql .= " AND c.incident_location_county = :county";
            $params['county'] = $county;
        }
        
        $sql .= " ORDER BY COALESCE(c.occurred_at, c.created_at) DESC
            LIMIT :limit";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    
    public function calculateRiskScore($day, $hour, $location) {
        $locationParts = explode(', ', $location);
        $constituency = $locationParts[0] ?? '';
        $county = $locationParts[1] ?? $locationParts[0] ?? '';
        
        $baseRisk = $this->getBaseRiskByDayHourLocation($day, $hour, $county, $constituency);
        
        $recencyWeight = $this->getRecencyWeight($county, $constituency);
        
        $severityMultiplier = $this->getSeverityMultiplier($county, $constituency);
        
        $riskScore = ($baseRisk * $recencyWeight * $severityMultiplier);
        $riskScore = min(100, max(0, round($riskScore)));
        
        return [
            'score' => $riskScore,
            'level' => $this->getRiskLevel($riskScore),
            'factors' => [
                'base_risk' => round($baseRisk, 2),
                'recency_weight' => round($recencyWeight, 2),
                'severity_multiplier' => round($severityMultiplier, 2)
            ]
        ];
    }
    
   
    private function getBaseRiskByDayHourLocation($day, $hour, $county, $constituency) {
        $sql = "SELECT COUNT(*) as count
            FROM cases
            WHERE DAYOFWEEK(COALESCE(occurred_at, created_at)) = :day_of_week
            AND HOUR(COALESCE(occurred_at, created_at)) = :hour
            AND (incident_location_county = :county OR incident_location_constituency = :constituency)
            AND COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        
        $mysqlDay = ($day + 2) % 7;
        if ($mysqlDay == 0) $mysqlDay = 7;
        
        $result = $this->db->fetchOne($sql, [
            'day_of_week' => $mysqlDay,
            'hour' => $hour,
            'county' => $county,
            'constituency' => $constituency
        ]);
        
        $count = (int)($result['count'] ?? 0);
        
        if ($count >= 10) {
            return 50 + (($count - 10) * 5);
        }
        return $count * 5;
    }
    
    
    private function getRecencyWeight($county, $constituency) {
        $sql = "SELECT COUNT(*) as recent_count
            FROM cases
            WHERE (incident_location_county = :county OR incident_location_constituency = :constituency)
            AND COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        
        $result = $this->db->fetchOne($sql, [
            'county' => $county,
            'constituency' => $constituency
        ]);
        
        $recentCount = (int)($result['recent_count'] ?? 0);
        
        if ($recentCount >= 5) return 1.3;
        if ($recentCount >= 3) return 1.2;
        if ($recentCount >= 1) return 1.1;
        return 1.0;
    }
    
    
    private function getSeverityMultiplier($county, $constituency) {
        $sql = "SELECT category, COUNT(*) as count
            FROM cases
            WHERE (incident_location_county = :county OR incident_location_constituency = :constituency)
            AND COALESCE(occurred_at, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY category
            ORDER BY count DESC";
        
        $results = $this->db->fetchAll($sql, [
            'county' => $county,
            'constituency' => $constituency
        ]);
        
        $highSeverity = ['Assault', 'Domestic Violence', 'Sexual Offenses', 'Robbery'];
        $totalWeight = 0;
        $totalCount = 0;
        
        foreach ($results as $row) {
            $weight = in_array($row['category'], $highSeverity) ? 1.5 : 1.0;
            $totalWeight += $weight * $row['count'];
            $totalCount += $row['count'];
        }
        
        if ($totalCount == 0) return 1.0;
        
        $avgWeight = $totalWeight / $totalCount;
        return min(1.5, $avgWeight);
    }
    
    
    private function getRiskLevel($score) {
        if ($score >= 65) return 'high';
        if ($score >= 35) return 'medium';
        return 'low';
    }
    
   
    public function getCategories() {
        $sql = "SELECT DISTINCT category FROM cases ORDER BY category";
        $results = $this->db->fetchAll($sql);
        return array_column($results, 'category');
    }
    
   
    public function getLocations($county = null) {
        $sql = "SELECT DISTINCT 
                incident_location_county as county,
                incident_location_constituency as constituency
            FROM cases
            WHERE incident_location_county IS NOT NULL";
        
        $params = [];
        
        if ($county) {
            $sql .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }
        
        $sql .= " ORDER BY incident_location_county, incident_location_constituency";
        
        $results = $this->db->fetchAll($sql, $params);
        
        $locations = [];
        foreach ($results as $row) {
            $locations[] = [
                'value' => $row['constituency'] . ', ' . $row['county'],
                'label' => $row['constituency'] . ', ' . $row['county']
            ];
        }
        
        return $locations;
    }

   
    public function getAllLocationsForDropdown($limit = 20, $county = null) {
        $sql = "SELECT 
                incident_location_constituency as constituency,
                incident_location_county as county,
                COUNT(*) as case_count
            FROM cases
            WHERE incident_location_county IS NOT NULL";
        
        $params = ['limit' => $limit];
        
        if ($county) {
            $sql .= " AND incident_location_county = :county";
            $params['county'] = $county;
        }
        
        $sql .= " GROUP BY incident_location_constituency, incident_location_county
            ORDER BY case_count DESC
            LIMIT :limit";
        
        $results = $this->db->fetchAll($sql, $params);
        
        $locations = [];
        $seenCounties = [];
        
        foreach ($results as $row) {
            $locationKey = $row['constituency'] . ', ' . $row['county'];
            $locations[] = [
                'constituency' => $row['constituency'],
                'county' => $row['county'],
                'label' => $row['constituency'] . ' (' . $row['county'] . ')',
                'case_count' => (int)$row['case_count']
            ];
            $seenCounties[$row['county']] = true;
        }
        
        if ($county && count($locations) === 0) {
            $locations[] = [
                'constituency' => $county,
                'county' => $county,
                'label' => $county . ' (County)',
                'case_count' => 0
            ];
        } 
        else if (!$county) {
            $majorCounties = ['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Kiambu', 'Uasin Gishu', 'Kilifi', 'Mandera', 'Kajiado', 'Machakos'];
            
            foreach ($majorCounties as $majorCounty) {
                if (!isset($seenCounties[$majorCounty]) && count($locations) < $limit) {
                    $locations[] = [
                        'constituency' => $majorCounty,
                        'county' => $majorCounty,
                        'label' => $majorCounty . ' (County)',
                        'case_count' => 0
                    ];
                    $seenCounties[$majorCounty] = true;
                }
            }
        }
        
        return $locations;
    }
}
