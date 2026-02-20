<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class OBGenerator {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function generateOBNumber($stationId, $crimeCategory = null) {
        try {
            $station = $this->db->fetchOne(
                "SELECT station_code FROM stations WHERE id = :id",
                ['id' => $stationId]
            );

            if (!$station) {
                throw new Exception("Station not found with ID: $stationId");
            }

            $stationCode = $station['station_code'];
            $year = date('Y');

            $sequenceNumber = $this->getNextSequenceNumber($stationId, $year);

            $maxAttempts = 10;
            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $obNumber = sprintf("OB-%s-%s-%05d", $stationCode, $year, $sequenceNumber);

                if (!$this->obNumberExists($obNumber)) {
                    return $obNumber;
                }

                $sequenceNumber++;
            }

            throw new Exception("Failed to generate unique OB number after {$maxAttempts} attempts");

        } catch (Exception $e) {
            error_log("OB Generation Error: " . $e->getMessage());
            throw new Exception("Failed to generate OB number: " . $e->getMessage());
        }
    }

    private function getNextSequenceNumber($stationId, $year) {
        $sql = "SELECT COUNT(*) + 1 as next_sequence 
                FROM cases 
                WHERE station_id = :station_id 
                AND YEAR(created_at) = :year";

        $result = $this->db->fetchOne($sql, [
            'station_id' => $stationId,
            'year' => $year
        ]);

        return (int)($result['next_sequence'] ?? 1);
    }

    public function validateOBNumber($obNumber) {
        $pattern = '/^OB-[A-Z0-9]+-\d{4}-\d{5}$/';
        return preg_match($pattern, $obNumber);
    }

    public function parseOBNumber($obNumber) {
        if (!$this->validateOBNumber($obNumber)) {
            return false;
        }

        $parts = explode('-', $obNumber);

        return [
            'prefix' => $parts[0],
            'station_code' => $parts[1],
            'year' => $parts[2],
            'sequence' => $parts[3]
        ];
    }

    public function getStationFromOBNumber($obNumber) {
        $parsed = $this->parseOBNumber($obNumber);

        if (!$parsed) {
            return null;
        }

        $sql = "SELECT * FROM stations WHERE station_code = :code";
        return $this->db->fetchOne($sql, ['code' => $parsed['station_code']]);
    }

    public function obNumberExists($obNumber) {
        return $this->db->exists('cases', 'ob_number = :ob_number', ['ob_number' => $obNumber]);
    }

    public function generateBatchOBNumbers($stationId, $count = 1) {
        $obNumbers = [];

        for ($i = 0; $i < $count; $i++) {
            $obNumbers[] = $this->generateOBNumber($stationId);
        }

        return $obNumbers;
    }

    public function getOBStatistics($stationId, $year = null) {
        $year = $year ?: date('Y');

        $sql = "SELECT 
                    COUNT(*) as total_cases,
                    COUNT(CASE WHEN status = 'reported' THEN 1 END) as reported_cases,
                    COUNT(CASE WHEN status = 'assigned' THEN 1 END) as assigned_cases,
                    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_cases,
                    COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_cases,
                    COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_cases
                FROM cases 
                WHERE station_id = :station_id 
                AND YEAR(created_at) = :year";

        return $this->db->fetchOne($sql, [
            'station_id' => $stationId,
            'year' => $year
        ]);
    }

    public function getMonthlyOBCount($stationId, $year = null) {
        $year = $year ?: date('Y');

        $sql = "SELECT 
                    MONTH(created_at) as month,
                    COUNT(*) as count
                FROM cases 
                WHERE station_id = :station_id 
                AND YEAR(created_at) = :year
                GROUP BY MONTH(created_at)
                ORDER BY MONTH(created_at)";

        $results = $this->db->fetchAll($sql, [
            'station_id' => $stationId,
            'year' => $year
        ]);

        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[$i] = 0;
        }

        foreach ($results as $result) {
            $monthlyData[(int)$result['month']] = (int)$result['count'];
        }

        return $monthlyData;
    }

    public function getRecentOBNumbers($stationId, $limit = 10) {
        $sql = "SELECT ob_number, title, category, status, created_at 
                FROM cases 
                WHERE station_id = :station_id 
                ORDER BY created_at DESC 
                LIMIT :limit";

        return $this->db->fetchAll($sql, [
            'station_id' => $stationId,
            'limit' => $limit
        ]);
    }

    public function searchOBNumbers($pattern, $limit = 50) {
        $sql = "SELECT c.ob_number, c.title, c.category, c.status, c.created_at, s.name as station_name
                FROM cases c
                JOIN stations s ON c.station_id = s.id
                WHERE c.ob_number LIKE :pattern
                ORDER BY c.created_at DESC
                LIMIT :limit";

        return $this->db->fetchAll($sql, [
            'pattern' => '%' . $pattern . '%',
            'limit' => $limit
        ]);
    }
}

?>
