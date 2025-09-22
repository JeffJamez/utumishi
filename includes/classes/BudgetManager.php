<?php
if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class BudgetManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get budget overview for all stations
     */
    public function getBudgetOverview($year = null) {
        $year = $year ?: date('Y');
        
        return $this->db->fetchAll("
            SELECT 
                s.id as station_id,
                s.name as station_name,
                s.county,
                s.budget_allocated,
                COALESCE(SUM(b.allocated_amount), 0) as total_allocated,
                COALESCE(SUM(b.spent_amount), 0) as total_spent,
                ROUND((COALESCE(SUM(b.spent_amount), 0) / NULLIF(s.budget_allocated, 0)) * 100, 1) as utilization_rate,
                COUNT(DISTINCT o.id) as officer_count,
                COUNT(DISTINCT c.id) as case_count
            FROM stations s
            LEFT JOIN budgets b ON s.id = b.station_id AND b.year = :year
            LEFT JOIN users u ON s.id = u.station_id AND u.role = 'officer' AND u.is_active = 1
            LEFT JOIN officers o ON u.id = o.user_id
            LEFT JOIN cases c ON s.id = c.station_id AND YEAR(c.created_at) = :year2
            GROUP BY s.id, s.name, s.county, s.budget_allocated
            ORDER BY s.county ASC, s.name ASC
        ", ['year' => $year, 'year2' => $year]);
    }

    /**
     * Get detailed budget breakdown for a station
     */
    public function getStationBudgetDetails($stationId, $year = null) {
        $year = $year ?: date('Y');
        
        $categories = $this->db->fetchAll("
            SELECT 
                category,
                allocated_amount,
                spent_amount,
                ROUND((spent_amount / NULLIF(allocated_amount, 0)) * 100, 1) as utilization_rate,
                updated_at
            FROM budgets
            WHERE station_id = :station_id AND year = :year
            ORDER BY category
        ", ['station_id' => $stationId, 'year' => $year]);

        $station = $this->db->fetchOne("
            SELECT name, county, budget_allocated
            FROM stations
            WHERE id = :station_id
        ", ['station_id' => $stationId]);

        return [
            'station' => $station,
            'categories' => $categories,
            'year' => $year
        ];
    }

    /**
     * Allocate budget to station categories
     */
    public function allocateBudget($stationId, $year, $allocations) {
        try {
            $this->db->beginTransaction();

            foreach ($allocations as $category => $amount) {
                // Check if budget entry exists
                $existing = $this->db->fetchOne("
                    SELECT id FROM budgets 
                    WHERE station_id = :station_id AND year = :year AND category = :category
                ", ['station_id' => $stationId, 'year' => $year, 'category' => $category]);

                if ($existing) {
                    // Update existing allocation
                    $this->db->update('budgets', 
                        ['allocated_amount' => $amount],
                        'id = :id',
                        ['id' => $existing['id']]
                    );
                } else {
                    // Create new allocation
                    $this->db->insert('budgets', [
                        'station_id' => $stationId,
                        'year' => $year,
                        'category' => $category,
                        'allocated_amount' => $amount,
                        'spent_amount' => 0
                    ]);
                }
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Budget allocated successfully'];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Budget Allocation Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to allocate budget: ' . $e->getMessage()];
        }
    }

    /**
     * Record budget expenditure
     */
    public function recordExpenditure($stationId, $year, $category, $amount, $description = '') {
        try {
            $this->db->beginTransaction();

            // Get current budget
            $budget = $this->db->fetchOne("
                SELECT id, allocated_amount, spent_amount
                FROM budgets
                WHERE station_id = :station_id AND year = :year AND category = :category
            ", ['station_id' => $stationId, 'year' => $year, 'category' => $category]);

            if (!$budget) {
                throw new Exception("No budget allocation found for this category");
            }

            $newSpentAmount = $budget['spent_amount'] + $amount;

            if ($newSpentAmount > $budget['allocated_amount']) {
                throw new Exception("Expenditure exceeds allocated budget for {$category}");
            }

            // Update spent amount
            $this->db->update('budgets',
                ['spent_amount' => $newSpentAmount],
                'id = :id',
                ['id' => $budget['id']]
            );

            $this->db->commit();
            return ['success' => true, 'message' => 'Expenditure recorded successfully'];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get budget utilization analytics
     */
    public function getBudgetAnalytics($year = null) {
        $year = $year ?: date('Y');

        $categoryTotals = $this->db->fetchAll("
            SELECT 
                category,
                SUM(allocated_amount) as total_allocated,
                SUM(spent_amount) as total_spent,
                ROUND((SUM(spent_amount) / NULLIF(SUM(allocated_amount), 0)) * 100, 1) as utilization_rate,
                COUNT(DISTINCT station_id) as station_count
            FROM budgets
            WHERE year = :year
            GROUP BY category
            ORDER BY total_allocated DESC
        ", ['year' => $year]);

        $countyTotals = $this->db->fetchAll("
            SELECT 
                s.county,
                SUM(b.allocated_amount) as total_allocated,
                SUM(b.spent_amount) as total_spent,
                ROUND((SUM(b.spent_amount) / NULLIF(SUM(b.allocated_amount), 0)) * 100, 1) as utilization_rate,
                COUNT(DISTINCT s.id) as station_count
            FROM budgets b
            JOIN stations s ON b.station_id = s.id
            WHERE b.year = :year
            GROUP BY s.county
            ORDER BY total_allocated DESC
        ", ['year' => $year]);

        $summary = $this->db->fetchOne("
            SELECT 
                SUM(allocated_amount) as total_allocated,
                SUM(spent_amount) as total_spent,
                ROUND((SUM(spent_amount) / NULLIF(SUM(allocated_amount), 0)) * 100, 1) as overall_utilization,
                COUNT(DISTINCT station_id) as stations_with_budget
            FROM budgets
            WHERE year = :year
        ", ['year' => $year]);

        return [
            'summary' => $summary,
            'by_category' => $categoryTotals,
            'by_county' => $countyTotals,
            'year' => $year
        ];
    }

    /**
     * Generate budget recommendations
     */
    public function generateBudgetRecommendations($year = null) {
        $year = $year ?: date('Y');
        $recommendations = [];

        // Find underutilized budgets
        $underutilized = $this->db->fetchAll("
            SELECT 
                s.name as station_name,
                b.category,
                b.allocated_amount,
                b.spent_amount,
                ROUND((b.spent_amount / NULLIF(b.allocated_amount, 0)) * 100, 1) as utilization_rate
            FROM budgets b
            JOIN stations s ON b.station_id = s.id
            WHERE b.year = :year 
            AND b.allocated_amount > 0
            AND (b.spent_amount / b.allocated_amount) < 0.5
            AND b.allocated_amount > 50000
            ORDER BY utilization_rate ASC
        ", ['year' => $year]);

        foreach ($underutilized as $budget) {
            $recommendations[] = [
                'type' => 'underutilized',
                'priority' => 'medium',
                'message' => "{$budget['station_name']} has low utilization ({$budget['utilization_rate']}%) in {$budget['category']} category. Consider reallocating KES " . number_format($budget['allocated_amount'] - $budget['spent_amount']) . " to other categories or stations.",
                'station' => $budget['station_name'],
                'category' => $budget['category'],
                'amount' => $budget['allocated_amount'] - $budget['spent_amount']
            ];
        }

        // Find overutilized budgets
        $overutilized = $this->db->fetchAll("
            SELECT 
                s.name as station_name,
                b.category,
                b.allocated_amount,
                b.spent_amount
            FROM budgets b
            JOIN stations s ON b.station_id = s.id
            WHERE b.year = :year 
            AND b.spent_amount > b.allocated_amount * 0.9
            ORDER BY (b.spent_amount / b.allocated_amount) DESC
        ", ['year' => $year]);

        foreach ($overutilized as $budget) {
            $recommendations[] = [
                'type' => 'near_limit',
                'priority' => 'high',
                'message' => "{$budget['station_name']} is approaching budget limit in {$budget['category']} category. Consider increasing allocation or monitoring expenditure closely.",
                'station' => $budget['station_name'],
                'category' => $budget['category']
            ];
        }

        return $recommendations;
    }

    /**
     * Get available budget categories
     */
    public function getBudgetCategories() {
        return [
            'salaries' => 'Officer Salaries & Benefits',
            'equipment' => 'Equipment & Technology',
            'transport' => 'Transportation & Fuel',
            'events' => 'Community Events & Outreach',
            'maintenance' => 'Facility Maintenance'
        ];
    }

    /**
     * Transfer budget between categories
     */
    public function transferBudget($fromStationId, $fromCategory, $toStationId, $toCategory, $amount, $year, $reason = '') {
        try {
            $this->db->beginTransaction();

            // Check source budget
            $sourceBudget = $this->db->fetchOne("
                SELECT id, allocated_amount, spent_amount
                FROM budgets
                WHERE station_id = :station_id AND year = :year AND category = :category
            ", ['station_id' => $fromStationId, 'year' => $year, 'category' => $fromCategory]);

            if (!$sourceBudget) {
                throw new Exception("Source budget not found");
            }

            $availableAmount = $sourceBudget['allocated_amount'] - $sourceBudget['spent_amount'];
            if ($amount > $availableAmount) {
                throw new Exception("Transfer amount exceeds available budget");
            }

            // Reduce source budget
            $this->db->update('budgets',
                ['allocated_amount' => $sourceBudget['allocated_amount'] - $amount],
                'id = :id',
                ['id' => $sourceBudget['id']]
            );

            // Check/create destination budget
            $destBudget = $this->db->fetchOne("
                SELECT id, allocated_amount
                FROM budgets
                WHERE station_id = :station_id AND year = :year AND category = :category
            ", ['station_id' => $toStationId, 'year' => $year, 'category' => $toCategory]);

            if ($destBudget) {
                // Update existing budget
                $this->db->update('budgets',
                    ['allocated_amount' => $destBudget['allocated_amount'] + $amount],
                    'id = :id',
                    ['id' => $destBudget['id']]
                );
            } else {
                // Create new budget entry
                $this->db->insert('budgets', [
                    'station_id' => $toStationId,
                    'year' => $year,
                    'category' => $toCategory,
                    'allocated_amount' => $amount,
                    'spent_amount' => 0
                ]);
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Budget transferred successfully'];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Budget Transfer Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>