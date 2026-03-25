<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class WorkloadManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get comprehensive workload data for a station
     */
    public function getStationWorkloadData($stationId) {
        $officers = $this->getOfficersWorkload($stationId);
        $unassignedCases = $this->getUnassignedCases($stationId);
        $workloadStats = $this->getWorkloadStatistics($stationId);
        
        return [
            'officers' => $officers,
            'unassigned_cases' => $unassignedCases,
            'workload_stats' => $workloadStats
        ];
    }

    /**
     * Get officers with their current workload details
     */
    public function getOfficersWorkload($stationId) {
        return $this->db->fetchAll("
            SELECT 
                o.id as officer_id,
                u.id as user_id,
                u.name,
                u.email,
                u.phone,
                o.badge_number,
                o.current_case_load,
                o.total_cases_resolved,
                o.avg_resolution_time_hours,
                o.expertise_categories,
                COUNT(c.id) as active_cases,
                ROUND(COUNT(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 END) * 100.0 / 
                      NULLIF(COUNT(c.id), 0), 1) as resolution_rate,
                AVG(CASE WHEN c.actual_resolution_hours IS NOT NULL THEN c.actual_resolution_hours END) as current_avg_time,
                CASE 
                    WHEN o.current_case_load > 15 THEN 'overloaded'
                    WHEN o.current_case_load > 10 THEN 'high'
                    WHEN o.current_case_load > 5 THEN 'normal'
                    WHEN o.current_case_load > 0 THEN 'light'
                    ELSE 'available'
                END as workload_status
            FROM officers o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN cases c ON o.id = c.assigned_officer_id AND c.status NOT IN ('closed')
            WHERE o.station_id = :station_id AND u.is_active = 1
            GROUP BY o.id, u.id, u.name, u.email, u.phone, o.badge_number, 
                     o.current_case_load, o.total_cases_resolved, o.avg_resolution_time_hours, o.expertise_categories
            ORDER BY o.current_case_load ASC, u.name ASC
        ", ['station_id' => $stationId]);
    }

    /**
     * Get unassigned cases for a station
     */
    public function getUnassignedCases($stationId) {
        return $this->db->fetchAll("
            SELECT 
                c.id,
                c.ob_number,
                c.title,
                c.category,
                c.created_at,
                c.estimated_resolution_hours,
                u.name as reporter_name,
                TIMESTAMPDIFF(HOUR, c.created_at, NOW()) as hours_pending,
                CASE 
                    WHEN TIMESTAMPDIFF(HOUR, c.created_at, NOW()) > 48 THEN 'critical'
                    WHEN TIMESTAMPDIFF(HOUR, c.created_at, NOW()) > 24 THEN 'high'
                    WHEN TIMESTAMPDIFF(HOUR, c.created_at, NOW()) > 12 THEN 'medium'
                    ELSE 'normal'
                END as urgency_level
            FROM cases c
            JOIN users u ON c.reported_by_citizen_id = u.id
            WHERE c.station_id = :station_id 
            AND c.assigned_officer_id IS NULL 
            AND c.status = 'reported'
            ORDER BY c.created_at ASC
        ", ['station_id' => $stationId]);
    }

    /**
     * Get workload statistics for a station
     */
    public function getWorkloadStatistics($stationId) {
        $result = $this->db->fetchOne("
            SELECT
                COUNT(DISTINCT o.id) as total_officers,
                AVG(o.current_case_load) as avg_case_load,
                MAX(o.current_case_load) as max_case_load,
                MIN(o.current_case_load) as min_case_load,
                COUNT(CASE WHEN o.current_case_load > 15 THEN 1 END) as overloaded_officers,
                COUNT(CASE WHEN o.current_case_load = 0 THEN 1 END) as idle_officers,
                COUNT(CASE WHEN o.current_case_load BETWEEN 1 AND 5 THEN 1 END) as light_load_officers,
                COUNT(CASE WHEN o.current_case_load BETWEEN 6 AND 10 THEN 1 END) as normal_load_officers,
                COUNT(CASE WHEN o.current_case_load BETWEEN 11 AND 15 THEN 1 END) as heavy_load_officers,
                SUM(o.current_case_load) as total_active_cases
            FROM officers o
            JOIN users u ON o.user_id = u.id
            WHERE o.station_id = :station_id AND u.is_active = 1
        ", ['station_id' => $stationId]);

        return $result ?: [
            'total_officers' => 0,
            'avg_case_load' => 0,
            'max_case_load' => 0,
            'min_case_load' => 0,
            'overloaded_officers' => 0,
            'idle_officers' => 0,
            'light_load_officers' => 0,
            'normal_load_officers' => 0,
            'heavy_load_officers' => 0,
            'total_active_cases' => 0
        ];
    }

    /**
     * Assign case to officer
     */
    public function assignCase($caseId, $officerId, $assignedBy) {
        try {
            $this->db->beginTransaction();
            
            // Verify case exists and is unassigned
            $case = $this->db->fetchOne("
                SELECT id, status, assigned_officer_id, station_id 
                FROM cases 
                WHERE id = :case_id AND status = 'reported' AND assigned_officer_id IS NULL
            ", ['case_id' => $caseId]);
            
            if (!$case) {
                throw new Exception("Case not found or already assigned");
            }
            
            // Verify officer exists and get their station
            $officer = $this->db->fetchOne("
                SELECT o.id, o.station_id 
                FROM officers o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = :officer_id AND u.is_active = 1
            ", ['officer_id' => $officerId]);
            
            if (!$officer) {
                throw new Exception("Officer not found or inactive");
            }
            
            // Verify case and officer are in the same station
            if ($case['station_id'] != $officer['station_id']) {
                throw new Exception("Case and officer must be in the same station");
            }
            
            // Update case assignment
            $this->db->update('cases', 
                ['assigned_officer_id' => $officerId, 'status' => 'assigned'], 
                'id = :case_id', 
                ['case_id' => $caseId]
            );
            
            // Update officer case load
            $this->db->query("UPDATE officers SET current_case_load = current_case_load + 1 WHERE id = :officer_id", 
                           ['officer_id' => $officerId]);
            
            // Log the assignment
            $this->db->insert('case_updates', [
                'case_id' => $caseId,
                'officer_id' => $assignedBy,
                'update_text' => 'Case assigned to officer',
                'status_before' => 'reported',
                'status_after' => 'assigned'
            ]);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Case assigned successfully'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Case Assignment Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to assign case: ' . $e->getMessage()];
        }
    }

    /**
     * Reassign case from one officer to another
     */
    public function reassignCase($caseId, $fromOfficerId, $toOfficerId, $reassignedBy, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // Verify case assignment
            $case = $this->db->fetchOne("
                SELECT id, assigned_officer_id, station_id 
                FROM cases 
                WHERE id = :case_id AND assigned_officer_id = :from_officer
            ", ['case_id' => $caseId, 'from_officer' => $fromOfficerId]);
            
            if (!$case) {
                throw new Exception("Case not found or not assigned to specified officer");
            }
            
            // Verify both officers exist and get their stations
            $fromOfficer = $this->db->fetchOne("
                SELECT o.id, o.station_id 
                FROM officers o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = :officer_id
            ", ['officer_id' => $fromOfficerId]);
            
            $toOfficer = $this->db->fetchOne("
                SELECT o.id, o.station_id 
                FROM officers o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = :officer_id AND u.is_active = 1
            ", ['officer_id' => $toOfficerId]);
            
            if (!$fromOfficer || !$toOfficer) {
                throw new Exception("Officer not found");
            }
            
            // Verify all officers and case are in the same station
            if ($case['station_id'] != $fromOfficer['station_id'] || 
                $case['station_id'] != $toOfficer['station_id']) {
                throw new Exception("All officers and case must be in the same station");
            }
            
            // Update case assignment
            $this->db->update('cases', 
                ['assigned_officer_id' => $toOfficerId], 
                'id = :case_id', 
                ['case_id' => $caseId]
            );
            
            // Update officer case loads
            $this->db->query("UPDATE officers SET current_case_load = current_case_load - 1 WHERE id = :officer_id", 
                           ['officer_id' => $fromOfficerId]);
            
            $this->db->query("UPDATE officers SET current_case_load = current_case_load + 1 WHERE id = :officer_id", 
                           ['officer_id' => $toOfficerId]);
            
            // Log the reassignment
            $updateText = 'Case reassigned to different officer';
            if ($reason) {
                $updateText .= '. Reason: ' . $reason;
            }
            
            $this->db->insert('case_updates', [
                'case_id' => $caseId,
                'officer_id' => $reassignedBy,
                'update_text' => $updateText,
                'status_before' => 'assigned',
                'status_after' => 'assigned'
            ]);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Case reassigned successfully'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Case Reassignment Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to reassign case: ' . $e->getMessage()];
        }
    }

    /**
     * Get workload balancing recommendations
     */
    public function getWorkloadRecommendations($stationId) {
        $officers = $this->getOfficersWorkload($stationId);
        $unassignedCount = count($this->getUnassignedCases($stationId));
        $recommendations = [];
        
        // Find overloaded officers
        $overloaded = array_filter($officers, function($officer) {
            return $officer['current_case_load'] > 15;
        });
        
        // Find available officers
        $available = array_filter($officers, function($officer) {
            return $officer['current_case_load'] < 10;
        });
        
        // Recommendation for overloaded officers
        foreach ($overloaded as $officer) {
            if (!empty($available)) {
                $recommendations[] = [
                    'type' => 'redistribute',
                    'priority' => 'high',
                    'from_officer' => $officer['name'],
                    'from_officer_id' => $officer['officer_id'],
                    'to_officers' => array_slice($available, 0, 3),
                    'message' => "Officer {$officer['name']} has {$officer['current_case_load']} cases. Consider redistributing some cases to reduce workload.",
                    'impact' => 'Reduces officer burnout and improves case resolution quality'
                ];
            }
        }
        
        // Recommendation for unassigned cases
        if ($unassignedCount > 0) {
            if (!empty($available)) {
                $recommendations[] = [
                    'type' => 'assign_pending',
                    'priority' => $unassignedCount > 5 ? 'high' : 'medium',
                    'message' => "{$unassignedCount} cases are pending assignment. Assign to available officers immediately.",
                    'available_officers' => array_slice($available, 0, 5),
                    'impact' => 'Prevents case backlogs and ensures timely response to citizens'
                ];
            } else {
                $recommendations[] = [
                    'type' => 'resource_shortage',
                    'priority' => 'critical',
                    'message' => "{$unassignedCount} cases pending but no officers available. Consider requesting additional resources.",
                    'impact' => 'Station may be understaffed for current case volume'
                ];
            }
        }
        
        // Check for expertise mismatch
        $this->addExpertiseRecommendations($stationId, $recommendations);
        
        return $recommendations;
    }

    /**
     * Add expertise-based recommendations
     */
    private function addExpertiseRecommendations($stationId, &$recommendations) {
        $specializedCases = $this->db->fetchAll("
            SELECT category, COUNT(*) as count
            FROM cases 
            WHERE station_id = :station_id 
            AND assigned_officer_id IS NULL 
            AND category IN ('Cybercrime', 'Domestic Violence', 'Drug Offenses')
            GROUP BY category
            HAVING count > 0
        ", ['station_id' => $stationId]);
        
        foreach ($specializedCases as $caseType) {
            $experts = $this->getOfficersByExpertise($stationId, $caseType['category']);
            
            if (empty($experts)) {
                $recommendations[] = [
                    'type' => 'expertise_gap',
                    'priority' => 'medium',
                    'message' => "{$caseType['count']} {$caseType['category']} cases pending but no officers with relevant expertise available.",
                    'impact' => 'Specialized cases may require additional training or expert consultation'
                ];
            }
        }
    }

    /**
     * Get officers by expertise category
     */
    public function getOfficersByExpertise($stationId, $category) {
        return $this->db->fetchAll("
            SELECT 
                o.id,
                u.name,
                o.badge_number,
                o.current_case_load,
                o.expertise_categories
            FROM officers o
            JOIN users u ON o.user_id = u.id
            WHERE o.station_id = :station_id 
            AND u.is_active = 1
            AND (
                JSON_CONTAINS(o.expertise_categories, :category_json)
                OR o.expertise_categories LIKE :category_like
            )
            ORDER BY o.current_case_load ASC
        ", [
            'station_id' => $stationId,
            'category_json' => json_encode($category),
            'category_like' => "%{$category}%"
        ]);
    }

    /**
     * Get optimal officer for case assignment based on workload and expertise
     */
    public function getOptimalOfficerForCase($stationId, $caseCategory) {
        // First try to find officer with relevant expertise and reasonable workload
        $expertOfficers = $this->getOfficersByExpertise($stationId, $caseCategory);
        
        foreach ($expertOfficers as $officer) {
            if ($officer['current_case_load'] < 12) {
                return $officer;
            }
        }
        
        // If no expert available, get officer with lowest workload
        $availableOfficers = $this->db->fetchAll("
            SELECT 
                o.id,
                u.name,
                o.badge_number,
                o.current_case_load
            FROM officers o
            JOIN users u ON o.user_id = u.id
            WHERE o.station_id = :station_id 
            AND u.is_active = 1
            ORDER BY o.current_case_load ASC, u.name ASC
            LIMIT 1
        ", ['station_id' => $stationId]);
        
        return $availableOfficers[0] ?? null;
    }

    /**
     * Generate workload report
     */
    public function generateWorkloadReport($stationId) {
        $workloadData = $this->getStationWorkloadData($stationId);
        $recommendations = $this->getWorkloadRecommendations($stationId);
        
        return [
            'type' => 'Workload Analysis Report',
            'station_id' => $stationId,
            'summary' => $workloadData['workload_stats'],
            'officers' => $workloadData['officers'],
            'unassigned_cases' => count($workloadData['unassigned_cases']),
            'recommendations' => $recommendations,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Auto-assign cases based on workload and expertise
     */
    public function autoAssignCases($stationId, $assignedBy, $maxCasesPerOfficer = 12) {
        $unassignedCases = $this->getUnassignedCases($stationId);
        $assignments = [];
        $errors = [];
        
        foreach ($unassignedCases as $case) {
            $optimalOfficer = $this->getOptimalOfficerForCase($stationId, $case['category']);
            
            if ($optimalOfficer && $optimalOfficer['current_case_load'] < $maxCasesPerOfficer) {
                $result = $this->assignCase($case['id'], $optimalOfficer['id'], $assignedBy);
                
                if ($result['success']) {
                    $assignments[] = [
                        'case_id' => $case['id'],
                        'ob_number' => $case['ob_number'],
                        'officer_name' => $optimalOfficer['name']
                    ];
                } else {
                    $errors[] = $result['message'];
                }
            }
        }
        
        return [
            'success' => true,
            'assignments' => $assignments,
            'errors' => $errors,
            'message' => count($assignments) . ' cases auto-assigned successfully'
        ];
    }
}
?>