<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class CaseManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function createCase($data) {
        try {
            $this->db->beginTransaction();

            $required = ['title', 'description', 'category', 'occurred_at', 'incident_location_county', 'incident_location_constituency',
                         'reporter_county', 'reporter_constituency',
                         'reported_by_citizen_id', 'recorded_by_officer_id', 'station_id'];

            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            $obGenerator = new OBGenerator();
            $obNumber = $obGenerator->generateOBNumber($data['station_id'], $data['category']);

            $caseData = [
                'ob_number' => $obNumber,
                'title' => sanitizeText($data['title']),
                'description' => sanitizeText($data['description']),
                'category' => sanitizeText($data['category']),
                'occurred_at' => $data['occurred_at'],
                'incident_location_county' => sanitizeText($data['incident_location_county']),
                'incident_location_constituency' => sanitizeText($data['incident_location_constituency']),
                'incident_local_area' => sanitizeText($data['incident_local_area'] ?? ''),
                'reporter_county' => sanitizeText($data['reporter_county']),
                'reporter_constituency' => sanitizeText($data['reporter_constituency']),
                'reporter_local_area' => sanitizeText($data['reporter_local_area'] ?? ''),
                'reported_by_citizen_id' => (int)$data['reported_by_citizen_id'],
                'recorded_by_officer_id' => (int)$data['recorded_by_officer_id'],
                'station_id' => (int)$data['station_id'],
                'status' => CASE_REPORTED,
                'estimated_resolution_hours' => $this->getEstimatedResolutionTime($data['category']),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Add GPS coordinates if provided (from Google Places)
            if (!empty($data['latitude']) && !empty($data['longitude'])) {
                $caseData['latitude'] = (float)$data['latitude'];
                $caseData['longitude'] = (float)$data['longitude'];
            }

            $caseId = $this->db->insert('cases', $caseData);

            if (!$caseId) {
                throw new Exception("Failed to create case");
            }

            $assignedOfficer = $this->autoAssignOfficer($data['category'], $data['station_id']);

            if ($assignedOfficer) {
                $this->assignCase($caseId, $assignedOfficer['id']);

                $this->addCaseUpdate($caseId, $data['recorded_by_officer_id'],
                    "Case automatically assigned to Officer {$assignedOfficer['badge_number']} - {$assignedOfficer['name']}",
                    CASE_REPORTED, CASE_ASSIGNED);
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Case successfully recorded in Digital OB',
                'case_id' => $caseId,
                'ob_number' => $obNumber
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Create Case Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateCase($caseId, $data, $officerId) {
        try {

            if (!$this->canOfficerUpdateCase($caseId, $officerId)) {
                throw new Exception("You do not have permission to update this case");
            }

            $currentCase = $this->getCaseById($caseId);
            if (!$currentCase) {
                throw new Exception("Case not found");
            }

            $this->db->beginTransaction();

            $updateData = [];
            $statusChanged = false;
            $oldStatus = $currentCase['status'];

            $allowedFields = ['title', 'description', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field]) && $data[$field] !== $currentCase[$field]) {
                    if ($field === 'status') {
                        $updateData[$field] = sanitizeText($data[$field]);
                        $statusChanged = true;
                    } else {
                        $updateData[$field] = $field === 'description' ? 
                            sanitizeText($data[$field]) : 
                            sanitizeText($data[$field]);
                    }
                }
            }

            if (!empty($updateData)) {
                $updateData['updated_at'] = date('Y-m-d H:i:s');

                if (isset($updateData['status']) && $updateData['status'] === CASE_CLOSED) {
                    $updateData['closed_at'] = date('Y-m-d H:i:s');
                    $updateData['actual_resolution_hours'] = $this->calculateResolutionTime($currentCase['created_at']);
                }

                $updated = $this->db->update('cases', $updateData, 'id = :id', ['id' => $caseId]);

                if ($updated > 0) {

                    if ($statusChanged) {
                        $this->addCaseUpdate($caseId, $officerId, 
                            $data['update_notes'] ?? 'Case status updated',
                            $oldStatus, $updateData['status']);
                    } else {
                        $this->addCaseUpdate($caseId, $officerId, 
                            $data['update_notes'] ?? 'Case information updated',
                            $oldStatus, $oldStatus);
                    }

                    if (isset($updateData['status']) && $updateData['status'] === CASE_CLOSED) {
                        $this->updateOfficerStats($currentCase['assigned_officer_id']);
                    }
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Case updated successfully',
                'case_id' => $caseId
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Update Case Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function autoAssignOfficer($category, $stationId) {
        $sql = "SELECT o.*, u.name 
                FROM officers o
                JOIN users u ON o.user_id = u.id
                WHERE u.station_id = :station_id 
                AND u.is_active = 1
                AND u.role = 'officer'
                AND (o.expertise_categories LIKE :category OR o.expertise_categories LIKE '%Other%')
                ORDER BY o.current_case_load ASC, o.total_cases_resolved DESC
                LIMIT 1";

        return $this->db->fetchOne($sql, [
            'station_id' => $stationId,
            'category' => "%$category%"
        ]);
    }

    public function assignCase($caseId, $officerId) {
        try {

            $updated = $this->db->update('cases', 
                ['assigned_officer_id' => $officerId, 'status' => CASE_ASSIGNED],
                'id = :id', 
                ['id' => $caseId]
            );

            if ($updated > 0) {

                $this->db->query(
                    "UPDATE officers SET current_case_load = current_case_load + 1 WHERE id = :id",
                    ['id' => $officerId]
                );

                return true;
            }

            return false;

        } catch (Exception $e) {
            error_log("Assign Case Error: " . $e->getMessage());
            return false;
        }
    }

    public function getCaseById($caseId, $userId = null) {

    if (!$userId) {
        $sql = "SELECT c.*,
                       u1.name as reporter_name, u1.phone as reporter_phone,
                       u2.name as recorded_by_name,
                       u3.name as assigned_officer_name, o.badge_number,
                       s.name as station_name, s.county as station_county
                FROM cases c
                JOIN users u1 ON c.reported_by_citizen_id = u1.id
                JOIN users u2 ON c.recorded_by_officer_id = u2.id
                LEFT JOIN officers o ON c.assigned_officer_id = o.id
                LEFT JOIN users u3 ON o.user_id = u3.id
                JOIN stations s ON c.station_id = s.id
                WHERE c.id = :id";

        $result = $this->db->fetchOne($sql, ['id' => $caseId]);
        if ($result) {
            $result['priority'] = $this->calculateCasePriority($result);
        }
        return $result;
    }

    $sql = "
        SELECT c.*,
               u1.name as reporter_name, u1.national_id as reporter_national_id, u1.phone as reporter_phone,
               u2.name as recorded_by_name,
               u3.name as assigned_officer_name, o.badge_number,
               s.name as station_name, s.county as station_county
        FROM cases c
        JOIN users u1 ON c.reported_by_citizen_id = u1.id
        JOIN users u2 ON c.recorded_by_officer_id = u2.id
        LEFT JOIN officers o ON c.assigned_officer_id = o.id
        LEFT JOIN users u3 ON o.user_id = u3.id
        JOIN stations s ON c.station_id = s.id
        WHERE c.id = :case_id
        AND (
            c.recorded_by_officer_id = :user_id_1
            OR o.user_id = :user_id_2
            OR c.station_id = (SELECT station_id FROM users WHERE id = :user_id_3)
        )
    ";

    $params = [
        'case_id' => $caseId,
        'user_id_1' => $userId,
        'user_id_2' => $userId,
        'user_id_3' => $userId
    ];

    $result = $this->db->fetchOne($sql, $params);
    if ($result) {
        $result['priority'] = $this->calculateCasePriority($result);
    }
    return $result;
}

    public function getCaseByOBNumber($obNumber) {
        $sql = "SELECT c.*,
                        u1.name as reporter_name, u1.national_id as reporter_national_id,
                        u3.name as assigned_officer_name, o.badge_number,
                        s.name as station_name, s.county as station_county
                 FROM cases c
                 JOIN users u1 ON c.reported_by_citizen_id = u1.id
                 LEFT JOIN officers o ON c.assigned_officer_id = o.id
                 LEFT JOIN users u3 ON o.user_id = u3.id
                 JOIN stations s ON c.station_id = s.id
                 WHERE c.ob_number = :ob_number";

        return $this->db->fetchOne($sql, ['ob_number' => $obNumber]);
    }

    public function getCasesForCitizen($citizenId, $limit = 10) {
        $sql = "SELECT c.ob_number, c.title, c.category, c.status, c.occurred_at, c.created_at, c.updated_at,
                       CONCAT(u.name, ' (', o.badge_number, ')') as assigned_officer,
                       s.name as station_name
                FROM cases c
                LEFT JOIN officers o ON c.assigned_officer_id = o.id
                LEFT JOIN users u ON o.user_id = u.id
                JOIN stations s ON c.station_id = s.id
                WHERE c.reported_by_citizen_id = :citizen_id
                ORDER BY COALESCE(c.occurred_at, c.created_at) DESC
                LIMIT :limit";

        return $this->db->fetchAll($sql, [
            'citizen_id' => $citizenId,
            'limit' => $limit
        ]);
    }

   public function getCasesForOfficer($officerId, $status = null) {
        $whereClause = "o.user_id = :officer_id";
        $params = ['officer_id' => $officerId];

        if ($status) {
            $whereClause .= " AND c.status = :status";
            $params['status'] = $status;
        } else {
            $whereClause .= " AND c.status NOT IN ('closed')";
        }

        $sql = "SELECT c.*, 
                    u.name as reporter_name,   
                    s.name as station_name,
                    TIMESTAMPDIFF(HOUR, COALESCE(c.occurred_at, c.created_at), NOW()) as hours_since_reported
                FROM cases c
                JOIN officers o ON c.assigned_officer_id = o.id
                LEFT JOIN users u ON c.reported_by_citizen_id = u.id  
                JOIN stations s ON c.station_id = s.id
                 WHERE $whereClause
                 ORDER BY COALESCE(c.occurred_at, c.created_at) ASC";

     return $this->db->fetchAll($sql, $params);
}

    public function getCasesForStation($stationId, $dateFrom = null, $dateTo = null) {
        $whereClause = "c.station_id = :station_id";
        $params = ['station_id' => $stationId];

        if ($dateFrom) {
            $whereClause .= " AND COALESCE(c.occurred_at, c.created_at) >= :date_from";
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo) {
            $whereClause .= " AND COALESCE(c.occurred_at, c.created_at) <= :date_to";
            $params['date_to'] = $dateTo;
        }

        $sql = "SELECT c.*, 
                       u1.name as reporter_name,
                       CONCAT(u2.name, ' (', o.badge_number, ')') as assigned_officer
                FROM cases c
                JOIN users u1 ON c.reported_by_citizen_id = u1.id
                LEFT JOIN officers o ON c.assigned_officer_id = o.id
                LEFT JOIN users u2 ON o.user_id = u2.id
                WHERE $whereClause
                ORDER BY COALESCE(c.occurred_at, c.created_at) DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function getCaseUpdates($caseId) {
        $sql = "SELECT cu.*, u.name as officer_name, o.badge_number
                FROM case_updates cu
                JOIN users u ON cu.officer_id = u.id
                LEFT JOIN officers o ON u.id = o.user_id
                WHERE cu.case_id = :case_id
                ORDER BY cu.created_at ASC";

        return $this->db->fetchAll($sql, ['case_id' => $caseId]);
    }

    public function updateCaseStatus($caseId, $newStatus, $officerId, $updateText) {
        try {
            $this->db->beginTransaction();

            // Get current status
            $case = $this->db->fetchOne("SELECT status FROM cases WHERE id = :id", ['id' => $caseId]);
            if (!$case) {
                throw new Exception("Case not found");
            }
            $oldStatus = $case['status'];

            // Update case
            $updateData = ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')];
            if ($newStatus === CASE_CLOSED) {
                $updateData['closed_at'] = date('Y-m-d H:i:s');
            }

            $this->db->update('cases', $updateData, 'id = :id', ['id' => $caseId]);

            // Add update
            $this->addCaseUpdate($caseId, $officerId, $updateText, $oldStatus, $newStatus);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Update Case Status Error: " . $e->getMessage());
            return false;
        }
    }

    public function addCaseUpdate($caseId, $officerId, $updateText, $statusBefore = null, $statusAfter = null) {
        try {
            $updateData = [
                'case_id' => $caseId,
                'officer_id' => $officerId,
                'update_text' => sanitizeText($updateText),
                'status_before' => $statusBefore,
                'status_after' => $statusAfter,
                'created_at' => date('Y-m-d H:i:s')
            ];

            return $this->db->insert('case_updates', $updateData);

        } catch (Exception $e) {
            error_log("Add Case Update Error: " . $e->getMessage());
            return false;
        }
    }

    private function canOfficerUpdateCase($caseId, $officerId) {

    $sql = "
        SELECT c.*, 
               o.user_id as assigned_user_id, 
               u.station_id as officer_station,
               (SELECT station_id FROM users WHERE id = :user_id_3) as user_station
        FROM cases c
        LEFT JOIN officers o ON c.assigned_officer_id = o.id
        JOIN users u ON u.id = :user_id_1
        WHERE c.id = :case_id
        AND (
            c.recorded_by_officer_id = :user_id_2
            OR o.user_id = :user_id_4
            OR c.station_id = (SELECT station_id FROM users WHERE id = :user_id_5)
        )
    ";

    $result = $this->db->fetchOne($sql, [
        'case_id' => $caseId,
        'user_id_1' => $officerId,
        'user_id_2' => $officerId,
        'user_id_3' => $officerId,
        'user_id_4' => $officerId,
        'user_id_5' => $officerId
    ]);

    if (!$result) return false;

    return ($result['assigned_user_id'] == $officerId) ||
           ($result['recorded_by_officer_id'] == $officerId) ||
           ($result['station_id'] == $result['user_station']);
}

    private function getEstimatedResolutionTime($category) {
        $defaultTimes = DEFAULT_RESOLUTION_HOURS;
        return $defaultTimes[$category] ?? 72;
    }

    private function calculateResolutionTime($createdAt) {
        $created = new DateTime($createdAt);
        $now = new DateTime();
        $diff = $now->diff($created);

        return ($diff->days * 24) + $diff->h + ($diff->i / 60);
    }

    private function updateOfficerStats($officerId) {
        if (!$officerId) return;

        try {

            $sql = "SELECT 
                        COUNT(*) as total_resolved,
                        AVG(actual_resolution_hours) as avg_resolution_time
                    FROM cases c
                    WHERE c.assigned_officer_id = :officer_id 
                    AND c.status = 'closed' 
                    AND c.actual_resolution_hours IS NOT NULL";

            $stats = $this->db->fetchOne($sql, ['officer_id' => $officerId]);

            $this->db->update('officers', [
                'total_cases_resolved' => $stats['total_resolved'] ?? 0,
                'avg_resolution_time_hours' => round($stats['avg_resolution_time'] ?? 0, 2),
                'current_case_load' => max(0, $this->db->count('cases', 
                    'assigned_officer_id = :officer_id AND status NOT IN (:closed)', 
                    ['officer_id' => $officerId, 'closed' => 'closed']))
            ], 'id = :id', ['id' => $officerId]);

        } catch (Exception $e) {
            error_log("Update Officer Stats Error: " . $e->getMessage());
        }
    }

    public function searchCases($searchTerm, $filters = []) {
        $whereConditions = [];
        $params = [];

        if (!empty($searchTerm)) {
            $whereConditions[] = "(c.ob_number LIKE :search OR c.title LIKE :search OR c.description LIKE :search)";
            $params['search'] = "%$searchTerm%";
        }

        if (!empty($filters['status'])) {
            $whereConditions[] = "c.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $whereConditions[] = "c.category = :category";
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['county'])) {
            $whereConditions[] = "c.incident_location_county = :county";
            $params['county'] = $filters['county'];
        }

        if (!empty($filters['station_id'])) {
            $whereConditions[] = "c.station_id = :station_id";
            $params['station_id'] = $filters['station_id'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "c.created_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = "c.created_at <= :date_to";
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "SELECT c.ob_number, c.title, c.category, c.status, c.created_at,
                        c.incident_location_county, c.incident_location_constituency,
                       u.name as reporter_name, s.name as station_name
                FROM cases c
                JOIN users u ON c.reported_by_citizen_id = u.id
                JOIN stations s ON c.station_id = s.id
                $whereClause
                ORDER BY c.created_at DESC
                LIMIT 50";

        return $this->db->fetchAll($sql, $params);
    }

    public function getCaseStatistics($filters = []) {
        $whereConditions = ['1=1'];
        $params = [];

        if (!empty($filters['station_id'])) {
            $whereConditions[] = "station_id = :station_id";
            $params['station_id'] = $filters['station_id'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "created_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = "created_at <= :date_to";
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT 
                    COUNT(*) as total_cases,
                    COUNT(CASE WHEN status = 'reported' THEN 1 END) as reported_cases,
                    COUNT(CASE WHEN status = 'assigned' THEN 1 END) as assigned_cases,
                    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_cases,
                    COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_cases,
                    COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_cases,
                    AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time
                FROM cases 
                WHERE $whereClause";

        return $this->db->fetchOne($sql, $params);
    }

    private function calculateCasePriority($caseData) {
        return ($caseData['estimated_resolution_hours'] <= 24) ? 'urgent' : 'high';
    }
}
?>
