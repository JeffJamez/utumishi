<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class User {
    protected $db;
    protected $id;
    protected $data;

    public function __construct($userId = null) {
        $this->db = Database::getInstance();

        if ($userId) {
            $this->id = $userId;
            $this->loadUserData();
        }
    }

    protected function loadUserData() {
        $sql = "SELECT u.id, u.national_id, u.name, u.email, u.phone, u.password, u.role, 
                       u.county_in_charge, u.created_at, u.last_login, u.is_active, u.id_document_path,
                       o.station_id, s.name as station_name, s.county, s.constituency 
                FROM users u 
                LEFT JOIN officers o ON u.id = o.user_id
                LEFT JOIN stations s ON o.station_id = s.id 
                WHERE u.id = :id AND u.is_active = 1";

        $this->data = $this->db->fetchOne($sql, ['id' => $this->id]);

        if (!$this->data) {
            throw new Exception("User not found or inactive");
        }
    }

    public function __get($property) {
        return $this->data[$property] ?? null;
    }

    public function getData() {
        return $this->data;
    }

    public function updateProfile($data) {
        try {

            $allowedFields = ['name', 'email', 'phone'];
            $updateData = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    switch ($field) {
                        case 'name':
                            $updateData[$field] = sanitizeName($data[$field]);
                            break;
                        case 'email':
                            $updateData[$field] = sanitizeEmail($data[$field]);
                            break;
                        case 'phone':
                            $updateData[$field] = sanitizePhone($data[$field]);
                            break;
                    }
                }
            }

            if (empty($updateData)) {
                return ['success' => false, 'message' => 'No valid fields to update'];
            }

            $errors = [];

            if (isset($updateData['name'])) {
                $nameValidation = validateName($updateData['name']);
                if (!$nameValidation['valid']) {
                    $errors[] = $nameValidation['message'];
                }
            }

            if (isset($updateData['email']) && !empty($updateData['email'])) {
                $emailValidation = validateEmail($updateData['email']);
                if (!$emailValidation['valid']) {
                    $errors[] = $emailValidation['message'];
                }
            }

            if (isset($updateData['phone'])) {
                $phoneValidation = validatePhone($updateData['phone']);
                if (!$phoneValidation['valid']) {
                    $errors[] = $phoneValidation['message'];
                }
            }

            if (!empty($errors)) {
                return ['success' => false, 'message' => implode(', ', $errors)];
            }

            $updated = $this->db->update('users', $updateData, 'id = :id', ['id' => $this->id]);

            if ($updated > 0) {

                $this->loadUserData();
                return ['success' => true, 'message' => 'Profile updated successfully'];
            } else {
                return ['success' => false, 'message' => 'No changes made'];
            }

        } catch (Exception $e) {
            error_log("Update Profile Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update profile'];
        }
    }

    public function changePassword($currentPassword, $newPassword) {
        try {

            if (!password_verify($currentPassword, $this->data['password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }

            $passwordValidation = validatePassword($newPassword);
            if (!$passwordValidation['valid']) {
                return $passwordValidation;
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updated = $this->db->update('users', 
                ['password' => $hashedPassword], 
                'id = :id', 
                ['id' => $this->id]
            );

            if ($updated > 0) {
                return ['success' => true, 'message' => 'Password changed successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to change password'];
            }

        } catch (Exception $e) {
            error_log("Change Password Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to change password'];
        }
    }

    public function getRecentActivity($limit = 10) {

        if ($this->data['role'] === ROLE_CITIZEN) {
            return $this->getRecentCasesAsReporter($limit);
        } elseif ($this->data['role'] === ROLE_OFFICER) {
            return $this->getRecentCasesAsOfficer($limit);
        }

        return [];
    }

    private function getRecentCasesAsReporter($limit) {
        $sql = "SELECT c.ob_number, c.title, c.category, c.status, c.occurred_at, c.created_at,
                       CONCAT(u.name, ' (', o.badge_number, ')') as assigned_officer
                FROM cases c
                LEFT JOIN officers o ON c.assigned_officer_id = o.id
                LEFT JOIN users u ON o.user_id = u.id
                WHERE c.reported_by_citizen_id = :user_id
                ORDER BY COALESCE(c.occurred_at, c.created_at) DESC
                LIMIT :limit";

        return $this->db->fetchAll($sql, ['user_id' => $this->id, 'limit' => $limit]);
    }

    private function getRecentCasesAsOfficer($limit) {
        $sql = "SELECT c.ob_number, c.title, c.category, c.status, c.occurred_at, c.created_at,
                       ur.name as reporter_name
                FROM cases c
                JOIN officers o ON c.assigned_officer_id = o.id
                JOIN users ur ON c.reported_by_citizen_id = ur.id
                WHERE o.user_id = :user_id
                ORDER BY COALESCE(c.occurred_at, c.created_at) DESC
                LIMIT :limit";

        return $this->db->fetchAll($sql, ['user_id' => $this->id, 'limit' => $limit]);
    }

    public function getStatistics() {
        $stats = [];

        switch ($this->data['role']) {
            case ROLE_CITIZEN:
                $stats = $this->getCitizenStatistics();
                break;
            case ROLE_OFFICER:
                $stats = $this->getOfficerStatistics();
                break;
            case ROLE_OCS:
                $stats = $this->getOCSStatistics();
                break;
            case ROLE_ADMIN:
                $stats = $this->getAdminStatistics();
                break;
        }

        return $stats;
    }

    private function getCitizenStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_cases_reported,
                    COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_cases,
                    COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_cases,
                    COUNT(CASE WHEN status IN ('reported', 'assigned', 'in_progress') THEN 1 END) as active_cases
                FROM cases 
                WHERE reported_by_citizen_id = :user_id";

        return $this->db->fetchOne($sql, ['user_id' => $this->id]);
    }

    private function getOfficerStatistics() {
        $sql = "SELECT 
                    o.current_case_load,
                    o.total_cases_resolved,
                    o.avg_resolution_time_hours,
                    COUNT(c.id) as total_cases_assigned,
                    COUNT(CASE WHEN c.status = 'resolved' THEN 1 END) as resolved_this_period,
                    COUNT(CASE WHEN c.status = 'closed' THEN 1 END) as closed_this_period
                FROM officers o
                LEFT JOIN cases c ON o.id = c.assigned_officer_id AND COALESCE(c.occurred_at, c.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                WHERE o.user_id = :user_id
                GROUP BY o.id";

        return $this->db->fetchOne($sql, ['user_id' => $this->id]);
    }

    private function getOCSStatistics() {
        $officer = $this->db->fetchOne("SELECT station_id FROM officers WHERE user_id = :user_id", ['user_id' => $this->id]);
        $stationId = $officer['station_id'] ?? null;
        
        if (!$stationId) {
            return null;
        }
        
        $sql = "SELECT 
                    COUNT(c.id) as total_station_cases,
                    COUNT(CASE WHEN c.status = 'resolved' THEN 1 END) as resolved_cases,
                    COUNT(CASE WHEN c.status = 'closed' THEN 1 END) as closed_cases,
                    COUNT(CASE WHEN c.status IN ('reported', 'assigned', 'in_progress') THEN 1 END) as active_cases,
                    COUNT(DISTINCT o.id) as total_officers
                FROM cases c
                LEFT JOIN officers o ON c.station_id = :station_id
                WHERE c.station_id = :station_id
                AND COALESCE(c.occurred_at, c.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

        return $this->db->fetchOne($sql, ['station_id' => $stationId]);
    }

    private function getAdminStatistics() {
        $sql = "SELECT 
                    COUNT(c.id) as total_national_cases,
                    COUNT(CASE WHEN c.status = 'resolved' THEN 1 END) as resolved_cases,
                    COUNT(CASE WHEN c.status = 'closed' THEN 1 END) as closed_cases,
                    COUNT(CASE WHEN c.status IN ('reported', 'assigned', 'in_progress') THEN 1 END) as active_cases,
                    COUNT(DISTINCT s.id) as total_stations,
                    COUNT(DISTINCT o.id) as total_officers
                FROM cases c
                LEFT JOIN stations s ON 1=1
                LEFT JOIN officers o ON 1=1
                WHERE COALESCE(c.occurred_at, c.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

        return $this->db->fetchOne($sql);
    }

    public function canPerform($action, $targetId = null) {
        switch ($this->data['role']) {
            case ROLE_ADMIN:
                return true;

            case ROLE_OCS:
                return $this->canOCSPerform($action, $targetId);

            case ROLE_OFFICER:
                return $this->canOfficerPerform($action, $targetId);

            case ROLE_CITIZEN:
                return $this->canCitizenPerform($action, $targetId);

            default:
                return false;
        }
    }

    private function canOCSPerform($action, $targetId) {
        $allowedActions = [
            'view_station_cases',
            'assign_officer',
            'view_officer_performance',
            'create_event',
            'view_station_stats'
        ];

        return in_array($action, $allowedActions);
    }

    private function canOfficerPerform($action, $targetId) {
        $allowedActions = [
            'record_case',
            'update_assigned_case',
            'upload_evidence',
            'view_own_cases'
        ];

        if (!in_array($action, $allowedActions)) {
            return false;
        }

        if ($action === 'update_assigned_case' && $targetId) {
            return $this->isAssignedToCase($targetId);
        }

        return true;
    }

    private function canCitizenPerform($action, $targetId) {
        $allowedActions = [
            'view_own_cases',
            'track_case',
            'view_public_stats'
        ];

        if (!in_array($action, $allowedActions)) {
            return false;
        }

        if ($action === 'track_case' && $targetId) {
            return $this->isReporterOfCase($targetId);
        }

        return true;
    }

    private function isAssignedToCase($caseId) {
        $sql = "SELECT 1 FROM cases c 
                JOIN officers o ON c.assigned_officer_id = o.id 
                WHERE c.id = :case_id AND o.user_id = :user_id";

        $result = $this->db->fetchOne($sql, [
            'case_id' => $caseId,
            'user_id' => $this->id
        ]);

        return $result !== false;
    }

    private function isReporterOfCase($caseId) {
        $sql = "SELECT 1 FROM cases WHERE id = :case_id AND reported_by_citizen_id = :user_id";

        $result = $this->db->fetchOne($sql, [
            'case_id' => $caseId,
            'user_id' => $this->id
        ]);

        return $result !== false;
    }

    public function getDashboardData() {
        $dashboardData = [
            'user_info' => $this->data,
            'statistics' => $this->getStatistics(),
            'recent_activity' => $this->getRecentActivity(5)
        ];

        switch ($this->data['role']) {
            case ROLE_CITIZEN:
                $dashboardData['active_cases'] = $this->getActiveCases();
                $dashboardData['public_stats'] = $this->getPublicStatistics();
                break;

            case ROLE_OFFICER:
                $dashboardData['assigned_cases'] = $this->getAssignedCases();
                $dashboardData['pending_tasks'] = $this->getPendingTasks();
                break;

            case ROLE_OCS:
                $dashboardData['station_overview'] = $this->getStationOverview();
                $dashboardData['officer_workload'] = $this->getOfficerWorkloadSummary();
                break;

            case ROLE_ADMIN:
                $dashboardData['national_overview'] = $this->getNationalOverview();
                $dashboardData['station_performance'] = $this->getStationPerformanceSummary();
                break;
        }

        return $dashboardData;
    }

    private function getActiveCases() {
        $sql = "SELECT ob_number, title, category, status, created_at
                FROM cases 
                WHERE reported_by_citizen_id = :user_id 
                AND status NOT IN ('closed')
                ORDER BY created_at DESC
                LIMIT 10";

        return $this->db->fetchAll($sql, ['user_id' => $this->id]);
    }

    private function getAssignedCases() {
        $sql = "SELECT c.ob_number, c.title, c.category, c.status, c.created_at,
                       TIMESTAMPDIFF(HOUR, c.created_at, NOW()) as hours_since_reported,
                       c.estimated_resolution_hours
                FROM cases c
                JOIN officers o ON c.assigned_officer_id = o.id
                WHERE o.user_id = :user_id 
                AND c.status NOT IN ('closed')
                ORDER BY c.created_at ASC
                LIMIT 10";

        return $this->db->fetchAll($sql, ['user_id' => $this->id]);
    }

    private function getPublicStatistics() {
    $sql = "SELECT
                incident_location_county as county,
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_cases,
                ROUND(COUNT(CASE WHEN status = 'resolved' THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate
            FROM cases
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY incident_location_county
            ORDER BY total_cases DESC
            LIMIT 5";
    return $this->db->fetchAll($sql);
}

    public function deactivate() {
        try {
            $updated = $this->db->update('users', 
                ['is_active' => 0], 
                'id = :id', 
                ['id' => $this->id]
            );

            return $updated > 0;

        } catch (Exception $e) {
            error_log("Deactivate User Error: " . $e->getMessage());
            return false;
        }
    }

    public static function create($userData) {
        try {
            $db = Database::getInstance();

            $required = ['national_id', 'name', 'phone', 'password', 'role'];
            foreach ($required as $field) {
                if (empty($userData[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            if ($db->exists('users', 'national_id = :national_id', ['national_id' => $userData['national_id']])) {
                throw new Exception("National ID already registered");
            }

            $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            $userData['created_at'] = date('Y-m-d H:i:s');
            $userData['is_active'] = 1;

            $userId = $db->insert('users', $userData);

            return new self($userId);

        } catch (Exception $e) {
            error_log("Create User Error: " . $e->getMessage());
            throw $e;
        }
    }

    public static function findByNationalId($nationalId) {
        try {
            $db = Database::getInstance();

            $user = $db->fetchOne(
                "SELECT id FROM users WHERE national_id = :national_id AND is_active = 1",
                ['national_id' => $nationalId]
            );

            if ($user) {
                return new self($user['id']);
            }

            return null;

        } catch (Exception $e) {
            error_log("Find User Error: " . $e->getMessage());
            return null;
        }
    }

    public static function findOrCreateCitizen($nationalId, $details) {
        try {
            $db = Database::getInstance();

            $existingUser = $db->fetchOne(
                "SELECT id, role FROM users WHERE national_id = :national_id",
                ['national_id' => $nationalId]
            );

            if ($existingUser) {
                $userId = $existingUser['id'];

                $updateData = [
                    'name' => sanitizeName($details['name']),
                    'phone' => sanitizePhone($details['phone'])
                ];

                if (!empty($details['id_document_path'])) {
                    $updateData['id_document_path'] = $details['id_document_path'];
                }

                if (!empty($details['email'])) {
                    $updateData['email'] = sanitizeEmail($details['email']);
                }

                if (!empty($details['gender'])) {
                    $updateData['gender'] = $details['gender'];
                }

                if (isset($details['is_minor'])) {
                    $updateData['is_minor'] = $details['is_minor'];
                }

                $db->update('users', $updateData, 'id = :id', ['id' => $userId]);

                return [
                    'exists' => true,
                    'user_id' => $userId,
                    'message' => 'Existing citizen updated with new details'
                ];
            } else {
                $citizenData = [
                    'national_id' => $nationalId,
                    'name' => sanitizeName($details['name']),
                    'phone' => sanitizePhone($details['phone']),
                    'id_document_path' => $details['id_document_path'] ?? null,
                    'email' => !empty($details['email']) ? sanitizeEmail($details['email']) : null,
                    'gender' => $details['gender'] ?? null,
                    'is_minor' => $details['is_minor'] ?? 0,
                    'password' => password_hash($nationalId, PASSWORD_DEFAULT),
                    'role' => ROLE_CITIZEN,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $userId = $db->insert('users', $citizenData);

                return [
                    'exists' => false,
                    'user_id' => $userId,
                    'message' => 'New citizen created'
                ];
            }

        } catch (Exception $e) {
            error_log("FindOrCreate Citizen Error: " . $e->getMessage());
            throw new Exception('Failed to process citizen record: ' . $e->getMessage());
        }
    }
}
?>
