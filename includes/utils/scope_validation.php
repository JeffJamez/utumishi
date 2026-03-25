<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

/**
 * Scope Validation Helper
 * Ensures users can only access data within their jurisdiction
 */
class ScopeValidation {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Validate if current user can access a specific case
     */
    public function canAccessCase($caseId, $currentUser) {
        $case = $this->db->fetchOne("
            SELECT c.id, c.station_id, s.county, s.id as station_id
            FROM cases c
            JOIN stations s ON c.station_id = s.id
            WHERE c.id = :case_id
        ", ['case_id' => $caseId]);

        if (!$case) {
            return false;
        }

        return $this->validateStationAccess($case['station_id'], $case['county'], $currentUser);
    }

    /**
     * Validate if current user can access a specific officer
     */
    public function canAccessOfficer($officerId, $currentUser) {
        $officer = $this->db->fetchOne("
            SELECT o.id, o.station_id, s.county
            FROM officers o
            JOIN stations s ON o.station_id = s.id
            WHERE o.id = :officer_id
        ", ['officer_id' => $officerId]);

        if (!$officer) {
            return false;
        }

        return $this->validateStationAccess($officer['station_id'], $officer['county'], $currentUser);
    }

    /**
     * Validate if current user can access a specific station
     */
    public function canAccessStation($stationId, $currentUser) {
        $station = $this->db->fetchOne("
            SELECT s.id, s.county
            FROM stations s
            WHERE s.id = :station_id
        ", ['station_id' => $stationId]);

        if (!$station) {
            return false;
        }

        return $this->validateStationAccess($stationId, $station['county'], $currentUser);
    }

    /**
     * Core validation logic for station/county access
     */
    private function validateStationAccess($stationId, $county, $currentUser) {
        $userRole = $currentUser['role'];
        $userStationId = $currentUser['station_id'];

        // County Commander: Can access any station/county case within their county
        if ($userRole === ROLE_ADMIN || $userRole === ROLE_COUNTY_COMMANDER) {
            // Get user's county from database
            $userCounty = $this->db->fetchOne("
                SELECT county_in_charge FROM users WHERE id = :id
            ", ['id' => $currentUser['id']]);

            return $userCounty && $userCounty['county_in_charge'] === $county;
        }

        // OCS: Can only access cases/officers within their station
        if ($userRole === ROLE_OCS) {
            return $stationId == $userStationId;
        }

        // Officer: Can only access their own cases (handled separately)
        if ($userRole === ROLE_OFFICER) {
            return $stationId == $userStationId;
        }

        return false;
    }

    /**
     * Get WHERE clause for case queries based on user scope
     */
    public function getCaseWhereClause($currentUser, $tablePrefix = 'c.') {
        $userRole = $currentUser['role'];
        $userStationId = $currentUser['station_id'];

        if ($userRole === ROLE_ADMIN || $userRole === ROLE_COUNTY_COMMANDER) {
            // County Commander: Filter by county
            $userCounty = $this->db->fetchOne("
                SELECT county_in_charge FROM users WHERE id = :id
            ", ['id' => $currentUser['id']]);

            if ($userCounty && $userCounty['county_in_charge']) {
                return [
                    'where' => "{$tablePrefix}station_id IN (SELECT id FROM stations WHERE county = :user_county)",
                    'params' => ['user_county' => $userCounty['county_in_charge']]
                ];
            }
        }

        // OCS and Officer: Filter by station
        return [
            'where' => "{$tablePrefix}station_id = :user_station",
            'params' => ['user_station' => $userStationId]
        ];
    }

    /**
     * Get WHERE clause for officer queries based on user scope
     */
    public function getOfficerWhereClause($currentUser, $tablePrefix = 'o.') {
        $userRole = $currentUser['role'];
        $userStationId = $currentUser['station_id'];

        if ($userRole === ROLE_ADMIN || $userRole === ROLE_COUNTY_COMMANDER) {
            // County Commander: Filter officers by county
            $userCounty = $this->db->fetchOne("
                SELECT county_in_charge FROM users WHERE id = :id
            ", ['id' => $currentUser['id']]);

            if ($userCounty && $userCounty['county_in_charge']) {
                return [
                    'where' => "o.station_id IN (SELECT id FROM stations WHERE county = :user_county)",
                    'params' => ['user_county' => $userCounty['county_in_charge']]
                ];
            }
        }

        // OCS and Officer: Filter by station
        return [
            'where' => "o.station_id = :user_station",
            'params' => ['user_station' => $userStationId]
        ];
    }

    /**
     * Get WHERE clause for station queries based on user scope
     */
    public function getStationWhereClause($currentUser, $tablePrefix = 's.') {
        $userRole = $currentUser['role'];

        if ($userRole === ROLE_ADMIN || $userRole === ROLE_COUNTY_COMMANDER) {
            // County Commander: Filter stations by county
            $userCounty = $this->db->fetchOne("
                SELECT county_in_charge FROM users WHERE id = :id
            ", ['id' => $currentUser['id']]);

            if ($userCounty && $userCounty['county_in_charge']) {
                return [
                    'where' => "{$tablePrefix}county = :user_county",
                    'params' => ['user_county' => $userCounty['county_in_charge']]
                ];
            }
        }

        // OCS and Officer: Can only access their own station
        return [
            'where' => "{$tablePrefix}id = :user_station",
            'params' => ['user_station' => $currentUser['station_id']]
        ];
    }

    /**
     * Validate assignment scope (OCS can only assign within their station)
     */
    public function validateAssignmentScope($caseId, $officerId, $currentUser) {
        $userRole = $currentUser['role'];

        // County Commander has no restriction on assignment
        if ($userRole === ROLE_ADMIN || $userRole === ROLE_COUNTY_COMMANDER) {
            return true;
        }

        // OCS and Officer: Must be within same station
        $case = $this->db->fetchOne("
            SELECT station_id FROM cases WHERE id = :case_id
        ", ['case_id' => $caseId]);

        $officer = $this->db->fetchOne("
            SELECT station_id
            FROM officers 
            WHERE id = :officer_id
        ", ['officer_id' => $officerId]);

        if (!$case || !$officer) {
            return false;
        }

        return $case['station_id'] == $currentUser['station_id'] && 
               $officer['station_id'] == $currentUser['station_id'];
    }
}

/**
 * Helper function to get scope validation instance
 */
function getScopeValidation() {
    static $instance = null;
    if ($instance === null) {
        $instance = new ScopeValidation();
    }
    return $instance;
}

/**
 * Quick access check for cases
 */
function canAccessCase($caseId, $currentUser = null) {
    if ($currentUser === null) {
        $currentUser = getCurrentUser();
    }
    return getScopeValidation()->canAccessCase($caseId, $currentUser);
}

/**
 * Quick access check for officers
 */
function canAccessOfficer($officerId, $currentUser = null) {
    if ($currentUser === null) {
        $currentUser = getCurrentUser();
    }
    return getScopeValidation()->canAccessOfficer($officerId, $currentUser);
}

/**
 * Quick access check for stations
 */
function canAccessStation($stationId, $currentUser = null) {
    if ($currentUser === null) {
        $currentUser = getCurrentUser();
    }
    return getScopeValidation()->canAccessStation($stationId, $currentUser);
}

/**
 * Validate assignment permissions
 */
function validateAssignmentScope($caseId, $officerId, $currentUser = null) {
    if ($currentUser === null) {
        $currentUser = getCurrentUser();
    }
    return getScopeValidation()->validateAssignmentScope($caseId, $officerId, $currentUser);
}
