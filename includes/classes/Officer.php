<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/User.php';

class Officer extends User {
    private $officerData;

    public function __construct($userId = null) {
        parent::__construct($userId);

        if (!$this->data || !is_array($this->data)) {

            return;
        }

        if ($userId && $this->data['role'] === ROLE_OFFICER) {
            $this->loadOfficerData();
        }
    }

    private function loadOfficerData() {
        $sql = "SELECT o.* FROM officers o WHERE o.user_id = :user_id";

        $result = $this->db->fetchOne($sql, ['user_id' => $this->id]);

        $this->officerData = is_array($result) ? $result : null;
    }

    public function getOfficerData($property = null) {
        if ($property) {
            return $this->officerData[$property] ?? null;
        }
        return $this->officerData;
    }

    public function getWorkload() {
    $sql = "SELECT
                o.current_case_load,
                COUNT(c.id) as active_cases,
                COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, COALESCE(c.occurred_at, c.created_at), NOW()) > c.estimated_resolution_hours THEN 1 END) as overdue_cases,
                COUNT(CASE WHEN c.status = 'in_progress' THEN 1 END) as in_progress_cases
            FROM officers o
            LEFT JOIN cases c ON o.id = c.assigned_officer_id AND c.status NOT IN ('closed')
            WHERE o.user_id = :user_id
            GROUP BY o.id";

    $result = $this->db->fetchOne($sql, ['user_id' => $this->id]);

    return is_array($result) ? $result : [];
}

public function getPerformance($periodDays = 30) {
    $periodDays = max(1, min((int)$periodDays, 365));

    $sql = "
        SELECT
            o.total_cases_resolved,
            o.avg_resolution_time_hours,
            COUNT(c.id) as cases_this_period,
            COUNT(CASE WHEN c.status = 'resolved' THEN 1 END) as resolved_this_period,
            COUNT(CASE WHEN c.status = 'closed' THEN 1 END) as closed_this_period,
            AVG(CASE WHEN c.actual_resolution_hours IS NOT NULL THEN c.actual_resolution_hours END) as avg_time_this_period,
            COUNT(CASE WHEN COALESCE(c.occurred_at, c.created_at) >= DATE_SUB(NOW(), INTERVAL :period DAY)
                      AND TIMESTAMPDIFF(HOUR, COALESCE(c.occurred_at, c.created_at), COALESCE(c.closed_at, NOW())) <= c.estimated_resolution_hours
                      THEN 1 END) as on_time_resolutions
        FROM officers o
        LEFT JOIN cases c ON o.id = c.assigned_officer_id
            AND COALESCE(c.occurred_at, c.created_at) >= DATE_SUB(NOW(), INTERVAL :period2 DAY)
        WHERE o.user_id = :user_id
        GROUP BY o.id
    ";

    $user_id = $this->id;

    $result = $this->db->fetchOne($sql, [
        'user_id' => $user_id,
        'period' => $periodDays,
        'period2' => $periodDays 
    ]);

    return [
        'total_cases_resolved' => (int)($result['total_cases_resolved'] ?? 0),
        'total_cases_assigned' => (int)($result['cases_this_period'] ?? 0),
        'avg_resolution_time_hours' => (float)($result['avg_resolution_time_hours'] ?? 0),
        'on_time_rate' => $result['cases_this_period'] > 0 ? round((($result['on_time_resolutions'] ?? 0) / $result['cases_this_period']) * 100, 2) : 0,
        'resolution_rate' => $result['cases_this_period'] > 0 ? round((($result['resolved_this_period'] ?? 0) / $result['cases_this_period']) * 100, 2) : 0
    ];
}

public function getPendingTasks($limit = 5) {
    $cases = $this->getCasesRequiringAttention();
    return array_slice($cases, 0, $limit);
}

    public function getAssignedCases($status = null, $limit = null) {
        $caseManager = new CaseManager();
        return $caseManager->getCasesForOfficer($this->id, $status);
    }

    public function getRecordedCases($status = null) {
        $sql = "
            SELECT c.*,
                   TIMESTAMPDIFF(HOUR, COALESCE(c.occurred_at, c.created_at), NOW()) as hours_since_reported,
                   u.name as reporter_name,
                   s.name as station_name,
                   'recorded' as case_type
            FROM cases c
            LEFT JOIN users u ON c.reported_by_citizen_id = u.id
            LEFT JOIN stations s ON c.station_id = s.id
            WHERE c.recorded_by_officer_id = :officer_id
        ";

        $params = ['officer_id' => $this->id];

        if ($status && $status !== 'all') {
            $sql .= " AND c.status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY COALESCE(c.occurred_at, c.created_at) DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function getMyCases($filter = 'all') {
        $assignedCases = $this->getAssignedCases();
        $recordedCases = $this->getRecordedCases();



        // Mark types and combine
        foreach ($assignedCases as &$case) {
            $case['case_type'] = 'assigned';
        }

        $allCases = array_merge($assignedCases, $recordedCases);

        // Remove duplicates (if a case is both assigned and recorded by same officer)
        $uniqueCases = [];
        foreach ($allCases as $case) {
            $key = $case['id'];
            if (!isset($uniqueCases[$key])) {
                $uniqueCases[$key] = $case;
            } else {
                // If duplicate, prefer 'assigned' type
                if ($case['case_type'] === 'assigned') {
                    $uniqueCases[$key] = $case;
                }
            }
        }

        $cases = array_values($uniqueCases);



        // Sort by most recent (occurred_at or created_at DESC)
        usort($cases, fn($a, $b) => strtotime($b['occurred_at'] ?? $b['created_at']) - strtotime($a['occurred_at'] ?? $a['created_at']));

        // Apply filter
        if ($filter === 'assigned') {
            $cases = array_filter($cases, fn($c) => $c['case_type'] === 'assigned');
        } elseif ($filter === 'recorded') {
            $cases = array_filter($cases, fn($c) => $c['case_type'] === 'recorded');
        } elseif ($filter === 'in_progress') {
            $cases = array_filter($cases, fn($c) => $c['status'] === 'in_progress');
        }

        return $cases;
    }

    public function getUrgentCases() {
        $sql = "
        SELECT c.*,
               TIMESTAMPDIFF(HOUR, COALESCE(c.occurred_at, c.created_at), NOW()) as hours_since_reported,
               'urgent' as attention_level
        FROM cases c
        JOIN officers o ON c.assigned_officer_id = o.id
        WHERE o.user_id = :user_id
        AND c.status IN ('assigned', 'in_progress')
        AND TIMESTAMPDIFF(HOUR, COALESCE(c.occurred_at, c.created_at), NOW()) > 48
        ORDER BY COALESCE(c.occurred_at, c.created_at) ASC
    ";

    return $this->db->fetchAll($sql, ['user_id' => $this->id]);
    }

    public function updateOfficerProfile($data) {
        try {
            $this->db->beginTransaction();

            $userFields = ['name', 'email', 'phone'];
            $userData = [];
            foreach ($userFields as $field) {
                if (isset($data[$field])) {
                    $userData[$field] = sanitizeText($data[$field]);
                }
            }

            if (!empty($userData)) {
                $this->db->update('users', $userData, 'id = :id', ['id' => $this->id]);
            }

            $officerFields = ['expertise_categories'];
            $officerData = [];
            foreach ($officerFields as $field) {
                if (isset($data[$field])) {
                    if ($field === 'expertise_categories' && is_array($data[$field])) {
                        $officerData[$field] = json_encode($data[$field]);
                    } else {
                        $officerData[$field] = sanitizeText($data[$field]);
                    }
                }
            }

            if (!empty($officerData)) {
                $this->db->update('officers', $officerData, 'user_id = :id', ['id' => $this->id]);
            }

            $this->db->commit();

            $this->loadUserData();
            $this->loadOfficerData();

            return ['success' => true, 'message' => 'Profile updated successfully'];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Update Officer Profile Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update profile'];
        }
    }

    public function getExpertiseCategories() {
        $categories = $this->officerData['expertise_categories'] ?? '';

        if (empty($categories)) {
            return [];
        }

        $decoded = json_decode($categories, true);
        if ($decoded) {
            return $decoded;
        }

        return array_map('trim', explode(',', $categories));
    }

    public function hasExpertise($category) {
        $expertise = $this->getExpertiseCategories();
        return in_array($category, $expertise) || in_array('Other', $expertise);
    }

public function getWorkloadStatus() {
    $workload = $this->getWorkload();

    if (!$workload || !is_array($workload)) {
        return ['status' => 'Unknown', 'color' => 'secondary', 'level' => 0];
    }

    $currentLoad = $workload['current_case_load'] ?? 0;

    if ($currentLoad == 0) {
        return ['status' => 'Available', 'color' => 'success', 'level' => 0];
    } elseif ($currentLoad <= 5) {
        return ['status' => 'Light Load', 'color' => 'success', 'level' => 1];
    } elseif ($currentLoad <= 10) {
        return ['status' => 'Normal Load', 'color' => 'warning', 'level' => 2];
    } elseif ($currentLoad <= MAX_CASE_LOAD_PER_OFFICER) {
        return ['status' => 'Heavy Load', 'color' => 'danger', 'level' => 3];
    } else {
        return ['status' => 'Overloaded', 'color' => 'danger', 'level' => 4];
    }
}

    public function getMonthlyStats($year = null, $month = null) {
        $year = $year ?? date('Y');
        $month = $month ?? date('m');

        $sql = "SELECT 
                    COUNT(*) as total_cases,
                    COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_cases,
                    COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_cases,
                    COUNT(CASE WHEN status IN ('reported', 'assigned', 'in_progress') THEN 1 END) as active_cases,
                    AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time,
                    category,
                    COUNT(*) as category_count
                FROM cases c
                JOIN officers o ON c.assigned_officer_id = o.id
                WHERE o.user_id = :user_id 
                AND YEAR(COALESCE(c.occurred_at, c.created_at)) = :year
                AND MONTH(COALESCE(c.occurred_at, c.created_at)) = :month
                GROUP BY category
                ORDER BY category_count DESC";

        return $this->db->fetchAll($sql, [
            'user_id' => $this->id,
            'year' => $year,
            'month' => $month
        ]);
    }

    public function getRecentActivity($limit = 10) {
       $limit = max(1, min((int)$limit, 100));

    $sql = "
        SELECT 'case_assigned' as activity_type, c.ob_number, c.title, c.category,
               c.created_at as activity_date, u.name as reporter_name
        FROM cases c
        JOIN officers o ON c.assigned_officer_id = o.id
        JOIN users u ON c.reported_by_citizen_id = u.id
        WHERE o.user_id = :user_id_1

        UNION ALL

        SELECT 'case_updated' as activity_type, c.ob_number, cu.update_text as title,
               c.category, cu.created_at as activity_date, '' as reporter_name
        FROM case_updates cu
        JOIN cases c ON cu.case_id = c.id
        WHERE cu.officer_id = :user_id_2

        ORDER BY activity_date DESC
        LIMIT :limit_val
    ";

    return $this->db->fetchAll($sql, [
        'user_id_1' => $this->id,
        'user_id_2' => $this->id,
        'limit_val' => $limit
    ]);
    }

    public function getOfficerDashboardData() {
        $officerId = $this->officerData['id'] ?? null;
        if (!$officerId) {
            return [
                'total_assigned' => 0,
                'total_recorded' => 0,
                'total_closed' => 0,
                'total_open' => 0
            ];
        }

        $sql = "
            SELECT
                COUNT(CASE WHEN c.assigned_officer_id = ? AND c.status NOT IN ('closed') THEN 1 END) as total_assigned,
                COUNT(CASE WHEN c.recorded_by_officer_id = ? AND c.assigned_officer_id != ? THEN 1 END) as total_recorded,
                COUNT(CASE WHEN c.assigned_officer_id = ? AND c.status = 'closed' THEN 1 END) as total_closed,
                COUNT(CASE WHEN c.assigned_officer_id = ? AND c.status IN ('assigned', 'in_progress', 'resolved') THEN 1 END) as total_open
            FROM cases c
        ";

        $result = $this->db->fetchOne($sql, [$officerId, $this->id, $officerId, $officerId, $officerId]);



        return [
            'total_assigned' => (int)($result['total_assigned'] ?? 0),
            'total_recorded' => (int)($result['total_recorded'] ?? 0),
            'total_closed' => (int)($result['total_closed'] ?? 0),
            'total_open' => (int)($result['total_open'] ?? 0)
        ];
    }

    public function canPerformAction($action, $targetId = null) {
        switch ($action) {
            case 'record_case':
                return true;

            case 'update_case':
                if ($targetId) {
                    return $this->canUpdateCase($targetId);
                }
                return false;

            case 'upload_evidence':
                if ($targetId) {
                    return $this->canUploadEvidence($targetId);
                }
                return false;

            case 'view_case':
                if ($targetId) {
                    return $this->canViewCase($targetId);
                }
                return false;

            default:
                return parent::canPerform($action, $targetId);
        }
    }

    private function canUpdateCase($caseId) {
    $sql = "SELECT c.*, o.user_id as assigned_user_id
            FROM cases c
            LEFT JOIN officers o ON c.assigned_officer_id = o.id
            WHERE c.id = :case_id
            AND (
                c.recorded_by_officer_id = :user_id_1
                OR o.user_id = :user_id_2
                OR c.station_id = (SELECT station_id FROM users WHERE id = :user_id_3)
            )";

    $result = $this->db->fetchOne($sql, [
        'case_id' => $caseId,
        'user_id_1' => $this->id,
        'user_id_2' => $this->id,
        'user_id_3' => $this->id
    ]);

    return $result !== false;
}

    private function canUploadEvidence($caseId) {
        return $this->canUpdateCase($caseId);
    }

    private function canViewCase($caseId) {

        $sql = "SELECT 1 FROM cases c 
                WHERE c.id = :case_id 
                AND c.station_id = (SELECT station_id FROM users WHERE id = :user_id)";

        $result = $this->db->fetchOne($sql, [
            'case_id' => $caseId,
            'user_id' => $this->id
        ]);

        return $result !== false;
    }

public function getCasesRequiringAttention() {
    $sql = "
        SELECT c.*,
               u.name as reporter_name,
               TIMESTAMPDIFF(HOUR, COALESCE(c.occurred_at, c.created_at), NOW()) as hours_since_reported,
               CASE
                   WHEN TIMESTAMPDIFF(HOUR, COALESCE(c.occurred_at, c.created_at), NOW()) > 72 THEN 'overdue'
                   WHEN TIMESTAMPDIFF(HOUR, COALESCE(c.occurred_at, c.created_at), NOW()) > 48 THEN 'high_priority'
                   WHEN TIMESTAMPDIFF(HOUR, COALESCE(c.occurred_at, c.created_at), NOW()) > 24 THEN 'due_soon'
                   ELSE 'normal'
               END as attention_level
        FROM cases c
        JOIN officers o ON c.assigned_officer_id = o.id
        LEFT JOIN users u ON c.reported_by_citizen_id = u.id
        WHERE o.user_id = :user_id
        AND c.status IN ('assigned', 'in_progress')
        AND TIMESTAMPDIFF(HOUR, COALESCE(c.occurred_at, c.created_at), NOW()) > 24
        ORDER BY COALESCE(c.occurred_at, c.created_at) ASC
    ";

    return $this->db->fetchAll($sql, ['user_id' => $this->id]);
}

    public static function createOfficer($userData, $officerData) {
        try {
            $db = Database::getInstance();
            $db->beginTransaction();

            $userData['role'] = ROLE_OFFICER;
            $user = parent::create($userData);

            $officerData['user_id'] = $user->id;
            $officerData['joined_date'] = $officerData['joined_date'] ?? date('Y-m-d');
            $officerData['current_case_load'] = 0;
            $officerData['total_cases_resolved'] = 0;
            $officerData['avg_resolution_time_hours'] = 0;

            if (isset($officerData['expertise_categories']) && is_array($officerData['expertise_categories'])) {
                $officerData['expertise_categories'] = json_encode($officerData['expertise_categories']);
            }

            $officerId = $db->insert('officers', $officerData);

            if (!$officerId) {
                throw new Exception("Failed to create officer record");
            }

            $db->commit();

            return new self($user->id);

        } catch (Exception $e) {
            $db->rollback();
            error_log("Create Officer Error: " . $e->getMessage());
            throw $e;
        }
    }

    public static function getOfficersForStation($stationId) {
        $db = Database::getInstance();

        $sql = "SELECT u.id, u.name, u.phone, u.email, 
                       o.badge_number, o.expertise_categories, o.current_case_load,
                       o.total_cases_resolved, o.avg_resolution_time_hours,
                       CASE 
                           WHEN o.current_case_load = 0 THEN 'Available'
                           WHEN o.current_case_load <= 5 THEN 'Light Load'
                           WHEN o.current_case_load <= 10 THEN 'Normal Load'
                           WHEN o.current_case_load <= 15 THEN 'Heavy Load'
                           ELSE 'Overloaded'
                       END as workload_status
                FROM users u
                JOIN officers o ON u.id = o.user_id
                WHERE u.station_id = :station_id 
                AND u.is_active = 1 
                AND u.role = 'officer'
                ORDER BY u.name";

        return $db->fetchAll($sql, ['station_id' => $stationId]);
    }

    public static function getPerformanceRanking($stationId, $period = 30) {
        $db = Database::getInstance();

        $sql = "SELECT u.id, u.name, o.badge_number,
                       COUNT(c.id) as cases_handled,
                       COUNT(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 END) as cases_resolved,
                       AVG(CASE WHEN c.actual_resolution_hours IS NOT NULL THEN c.actual_resolution_hours END) as avg_resolution_time,
                       ROUND(COUNT(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 END) * 100.0 / NULLIF(COUNT(c.id), 0), 1) as resolution_rate
                FROM users u
                JOIN officers o ON u.id = o.user_id
                LEFT JOIN cases c ON o.id = c.assigned_officer_id
                    AND COALESCE(c.occurred_at, c.created_at) >= DATE_SUB(NOW(), INTERVAL :period DAY)
                WHERE u.station_id = :station_id
                AND u.is_active = 1
                AND u.role = 'officer'
                GROUP BY u.id, u.name, o.badge_number
                ORDER BY resolution_rate DESC, cases_resolved DESC";

        return $db->fetchAll($sql, [
            'station_id' => $stationId,
            'period' => $period
        ]);
    }

    public function transferToStation($newStationId, $transferredBy) {
        try {
            $this->db->beginTransaction();

            $oldStationId = $this->data['station_id'];

            $this->db->update('users', 
                ['station_id' => $newStationId], 
                'id = :id', 
                ['id' => $this->id]
            );

            $this->db->commit();

            $this->loadUserData();
            $this->loadOfficerData();

            return [
                'success' => true, 
                'message' => 'Officer transferred successfully',
                'old_station' => $oldStationId,
                'new_station' => $newStationId
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Transfer Officer Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to transfer officer'];
        }
    }
}
?>
