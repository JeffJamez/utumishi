<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class AdminManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all officers with detailed information
     */
    public function getAllOfficers($filters = []) {
        $whereConditions = ["u.role = 'officer'"];
        $params = [];

        if (!empty($filters['station_id'])) {
            $whereConditions[] = "u.station_id = :station_id";
            $params['station_id'] = $filters['station_id'];
        }

        if (!empty($filters['county'])) {
            $whereConditions[] = "s.county = :county";
            $params['county'] = $filters['county'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $whereConditions[] = "u.is_active = 1";
            } else {
                $whereConditions[] = "u.is_active = 0";
            }
        }

        $whereClause = implode(' AND ', $whereConditions);

        error_log("AdminManager::getAllOfficers - Filters: " . json_encode($filters) . " | WHERE: $whereClause | Params: " . json_encode($params));

        return $this->db->fetchAll("
            SELECT
                u.id,
                o.id as officer_id,
                u.name,
                u.email,
                u.phone,
                u.is_active,
                u.created_at,
                u.last_login,
                o.badge_number,
                o.current_case_load,
                o.total_cases_resolved,
                o.avg_resolution_time_hours,
                o.expertise_categories,
                o.joined_date,
                s.name as station_name,
                s.county,
                s.constituency
            FROM users u
            JOIN officers o ON u.id = o.user_id
            LEFT JOIN stations s ON u.station_id = s.id
            WHERE $whereClause
            ORDER BY s.name ASC, u.name ASC
        ", $params);
    }

    /**
     * Create new officer
     */
    public function createOfficer($userData, $officerData, $currentCounty = null) {
        try {
            $this->db->beginTransaction();

            // Create user account
            $userData['role'] = ROLE_OFFICER;
            $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            $userData['is_active'] = 1;

            $userId = $this->db->insert('users', $userData);

            // Validate station county
            if ($currentCounty) {
                $station = $this->db->fetchOne("SELECT county FROM stations WHERE id = :id", ['id' => $userData['station_id']]);
                if (!$station || $station['county'] !== $currentCounty) {
                    return ['success' => false, 'message' => 'Cannot assign officer to station outside your county.'];
                }
            }

            // Create officer record
            $officerData['user_id'] = $userId;
            $officerData['joined_date'] = $officerData['joined_date'] ?? date('Y-m-d');
            $officerData['current_case_load'] = 0;
            $officerData['total_cases_resolved'] = 0;
            $officerData['avg_resolution_time_hours'] = 0;

            // Auto-generate badge number if not provided
            if (empty($officerData['badge_number'])) {
                $officerData['badge_number'] = 'KEN' . str_pad($userId, 4, '0', STR_PAD_LEFT);
            }

            if (isset($officerData['expertise_categories']) && is_array($officerData['expertise_categories'])) {
                $officerData['expertise_categories'] = json_encode($officerData['expertise_categories']);
            }

            $officerId = $this->db->insert('officers', $officerData);

            $this->db->commit();
            return ['success' => true, 'message' => 'Officer created successfully', 'officer_id' => $officerId];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Create Officer Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create officer: ' . $e->getMessage()];
        }
    }

    /**
     * Update officer information
     */
    public function updateOfficer($officerId, $userData, $officerData, $currentCounty = null) {
        try {
            $this->db->beginTransaction();

            // Get user ID
            $officer = $this->db->fetchOne("SELECT user_id FROM officers WHERE id = :id", ['id' => $officerId]);
            if (!$officer) {
                throw new Exception("Officer not found");
            }

            // Validate station county if changing station
            if ($currentCounty && isset($userData['station_id'])) {
                $station = $this->db->fetchOne("SELECT county FROM stations WHERE id = :id", ['id' => $userData['station_id']]);
                if (!$station || $station['county'] !== $currentCounty) {
                    return ['success' => false, 'message' => 'Cannot assign officer to station outside your county.'];
                }
            }

            // Update user data
            if (!empty($userData)) {
                $this->db->update('users', $userData, 'id = :id', ['id' => $officer['user_id']]);
            }

            // Update officer data
            if (!empty($officerData)) {
                if (isset($officerData['expertise_categories']) && is_array($officerData['expertise_categories'])) {
                    $officerData['expertise_categories'] = json_encode($officerData['expertise_categories']);
                }
                $this->db->update('officers', $officerData, 'id = :id', ['id' => $officerId]);
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Officer updated successfully'];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Update Officer Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update officer: ' . $e->getMessage()];
        }
    }

    /**
     * Transfer officer to different station
     */
    public function transferOfficer($officerId, $newStationId, $transferReason = '', $currentCounty = null) {
        try {
            $this->db->beginTransaction();

            $officer = $this->db->fetchOne("
                SELECT o.user_id, u.station_id as old_station_id, u.name 
                FROM officers o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = :id
            ", ['id' => $officerId]);

            if (!$officer) {
                throw new Exception("Officer not found");
            }

            // Validate new station county
            if ($currentCounty) {
                $station = $this->db->fetchOne("SELECT county FROM stations WHERE id = :id", ['id' => $newStationId]);
                if (!$station || $station['county'] !== $currentCounty) {
                    return ['success' => false, 'message' => 'Cannot transfer officer to station outside your county.'];
                }
            }

            // Update station assignment
            $this->db->update('users', 
                ['station_id' => $newStationId], 
                'id = :id', 
                ['id' => $officer['user_id']]
            );

            // Log the transfer (you could create a transfers table for audit)
            // For now, we'll just log it

            $this->db->commit();
            return ['success' => true, 'message' => 'Officer transferred successfully'];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Transfer Officer Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to transfer officer: ' . $e->getMessage()];
        }
    }

    /**
     * Deactivate officer
     */
    public function deactivateOfficer($officerId, $reason = '') {
        try {
            $this->db->beginTransaction();

            $officer = $this->db->fetchOne("SELECT user_id FROM officers WHERE id = :id", ['id' => $officerId]);
            if (!$officer) {
                throw new Exception("Officer not found");
            }

            // Check for active cases
            $activeCases = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM cases 
                WHERE assigned_officer_id = :officer_id 
                AND status NOT IN ('resolved', 'closed')
            ", ['officer_id' => $officerId])['count'];

            if ($activeCases > 0) {
                throw new Exception("Cannot deactivate officer with {$activeCases} active cases. Please reassign cases first.");
            }

            $this->db->update('users', 
                ['is_active' => 0], 
                'id = :id', 
                ['id' => $officer['user_id']]
            );

            $this->db->commit();
            return ['success' => true, 'message' => 'Officer deactivated successfully'];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get all stations with details
     */
    public function getAllStations($county = null) {
        $where = "1=1";
        $params = [];

        if ($county) {
            $where .= " AND s.county = :county";
            $params['county'] = $county;
        }

        return $this->db->fetchAll("
            SELECT
                s.*,
                u.name as commander_name,
                COUNT(DISTINCT users.id) as officer_count,
                COUNT(DISTINCT cases.id) as total_cases
            FROM stations s
            LEFT JOIN users u ON s.commander_id = u.id
            LEFT JOIN users ON s.id = users.station_id AND users.role = 'officer' AND users.is_active = 1
            LEFT JOIN cases ON s.id = cases.station_id
            WHERE $where
            GROUP BY s.id
            ORDER BY s.county ASC, s.name ASC
        ", $params);
    }

    /**
     * Create new station
     */
    public function createStation($stationData) {
        try {
            $stationId = $this->db->insert('stations', $stationData);
            return ['success' => true, 'message' => 'Station created successfully', 'station_id' => $stationId];

        } catch (Exception $e) {
            error_log("Create Station Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create station: ' . $e->getMessage()];
        }
    }

    /**
     * Update station information
     */
    public function updateStation($stationId, $stationData) {
        try {
            $this->db->update('stations', $stationData, 'id = :id', ['id' => $stationId]);
            return ['success' => true, 'message' => 'Station updated successfully'];

        } catch (Exception $e) {
            error_log("Update Station Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update station: ' . $e->getMessage()];
        }
    }

    /**
     * Get national statistics
     */
    public function getNationalStatistics($timeframe = 30) {
        return $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_cases,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_cases,
                COUNT(CASE WHEN status = 'reported' THEN 1 END) as pending_cases,
                COUNT(CASE WHEN status IN ('assigned', 'in_progress') THEN 1 END) as active_cases,
                ROUND(COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*), 1) as resolution_rate,
                AVG(CASE WHEN actual_resolution_hours IS NOT NULL THEN actual_resolution_hours END) as avg_resolution_time,
                COUNT(DISTINCT station_id) as active_stations,
                (SELECT COUNT(*) FROM users WHERE role = 'officer' AND is_active = 1) as total_officers
            FROM cases c
            WHERE COALESCE(c.occurred_at, c.created_at) >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)
        ", ['timeframe' => $timeframe]);
    }

    /**
     * Get system users summary
     */
    public function getUsersSummary() {
        return $this->db->fetchOne("
            SELECT
                COUNT(*) as total_users,
                COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
                COUNT(CASE WHEN role = 'ocs' THEN 1 END) as ocs_count,
                COUNT(CASE WHEN role = 'officer' THEN 1 END) as officer_count,
                COUNT(CASE WHEN role = 'citizen' THEN 1 END) as citizen_count,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_users,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_users
            FROM users
        ");
    }

    /**
     * Get officer by ID
     */
    public function getOfficerById($officerId) {
        $officer = $this->db->fetchOne("
            SELECT
                u.id,
                u.name,
                u.email,
                u.phone,
                u.is_active,
                u.created_at,
                u.last_login,
                o.badge_number,
                o.current_case_load,
                o.total_cases_resolved,
                o.avg_resolution_time_hours,
                o.expertise_categories,
                o.joined_date,
                s.name as station_name,
                s.county,
                s.constituency
            FROM officers o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN stations s ON u.station_id = s.id
            WHERE o.id = :id
        ", ['id' => $officerId]);

        if ($officer) {
            // Calculate resolution rate
            $totalCases = $officer['total_cases_resolved'] + $officer['current_case_load'];
            $officer['resolution_rate'] = $totalCases > 0 ? round(($officer['total_cases_resolved'] / $totalCases) * 100, 1) : 0;
        }

        return $officer;
    }

    /**
     * Get officer's cases
     */
    public function getOfficerCases($officerId) {
        $current = $this->db->fetchAll("
            SELECT
                c.id,
                c.ob_number,
                c.title,
                c.category,
                c.status,
                c.assigned_at,
                c.created_at
            FROM cases c
            WHERE c.assigned_officer_id = :officer_id
            AND c.status NOT IN ('resolved', 'closed')
            ORDER BY c.assigned_at DESC
        ", ['officer_id' => $officerId]);

        $resolved = $this->db->fetchAll("
            SELECT
                c.id,
                c.ob_number,
                c.title,
                c.category,
                c.actual_resolution_hours,
                c.closed_at
            FROM cases c
            WHERE c.assigned_officer_id = :officer_id
            AND c.status IN ('resolved', 'closed')
            ORDER BY c.closed_at DESC
            LIMIT 50
        ", ['officer_id' => $officerId]);

        return [
            'current' => $current,
            'resolved' => $resolved
        ];
    }
}
?>