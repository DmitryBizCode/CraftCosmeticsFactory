<?php

namespace App\Services\Order;

use PDO;
use App\Services\Database\SQLService;

class OrderService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Отримати всі транзакції з детальною інформацією та пов'язаними замовленнями
     */
    public function getAllOrders(?string $statusFilter = null, ?string $searchQuery = null): array
    {
        // Отримуємо всі транзакції як основні записи
        $sql = "
            SELECT 
                t.id as transaction_id,
                t.value as transaction_value,
                t.currency as transaction_currency,
                t.date as transaction_date,
                t.status as transaction_status,
                t.description as transaction_description,
                t.client_id,
                t.staff_id,
                c.first_name as client_first_name,
                c.last_name as client_last_name,
                c.email as client_email,
                s.first_name as staff_first_name,
                s.last_name as staff_last_name,
                s.access_layer as staff_access_layer
            FROM transactions t
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN staff s ON t.staff_id = s.id
            WHERE 1=1
        ";

        $params = [];

        // Фільтр по статусу транзакції
        if ($statusFilter && $statusFilter !== 'All Statuses') {
            $sql .= " AND t.status = :status";
            $params[':status'] = $statusFilter;
        }

        // Пошук по ID транзакції, ID клієнта або імені клієнта
        if ($searchQuery) {
            $sql .= " AND (t.id LIKE :search OR t.client_id LIKE :search OR c.first_name LIKE :search OR c.last_name LIKE :search OR c.email LIKE :search)";
            $params[':search'] = '%' . $searchQuery . '%';
        }

        $sql .= " ORDER BY t.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Для кожної транзакції отримуємо пов'язані замовлення через таблицю transaction_order
        foreach ($transactions as &$transaction) {
            $ordersSql = "
                SELECT 
                    o.id as order_id,
                    o.quantity,
                    o.recipe_id,
                    r.name as recipe_name,
                    r.price as recipe_price
                FROM transaction_order to_rel
                INNER JOIN orders o ON to_rel.order_id = o.id
                LEFT JOIN recipes r ON o.recipe_id = r.id
                WHERE to_rel.transaction_id = :transaction_id
                ORDER BY o.id ASC
            ";
            
            $ordersStmt = $this->pdo->prepare($ordersSql);
            $ordersStmt->execute([':transaction_id' => $transaction['transaction_id']]);
            $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Додаємо замовлення до транзакції
            $transaction['orders'] = $orders;
            $transaction['orders_count'] = count($orders);
            $transaction['total_quantity'] = array_sum(array_column($orders, 'quantity'));
        }

        return $transactions;
    }

    /**
     * Отримати замовлення по ID
     */
    public function getOrderById(int $orderId): ?array
    {
        $sql = "
            SELECT 
                o.id as order_id,
                o.quantity,
                o.client_id,
                o.recipe_id,
                c.first_name as client_first_name,
                c.last_name as client_last_name,
                c.email as client_email,
                r.name as recipe_name,
                r.price as recipe_price,
                t.id as transaction_id,
                t.status as transaction_status,
                t.value as transaction_value,
                t.currency as transaction_currency,
                t.date as transaction_date,
                t.description as transaction_description,
                s.first_name as staff_first_name,
                s.last_name as staff_last_name,
                s.access_layer as staff_access_layer
            FROM orders o
            LEFT JOIN clients c ON o.client_id = c.id
            LEFT JOIN recipes r ON o.recipe_id = r.id
            LEFT JOIN transactions t ON t.client_id = o.client_id 
                AND DATE(t.date) = (SELECT DATE(MAX(t2.date)) FROM transactions t2 WHERE t2.client_id = o.client_id)
            LEFT JOIN staff s ON t.staff_id = s.id
            WHERE o.id = :order_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Оновити статус транзакції
     */
    public function updateTransactionStatus(int $transactionId, string $newStatus, ?int $staffId = null): bool
    {
        $allowedStatuses = ['Pending', 'Complete', 'Reject', 'Cancel'];
        if (!in_array($newStatus, $allowedStatuses)) {
            return false;
        }

        if ($staffId !== null) {
            $sql = "UPDATE transactions SET status = :status, staff_id = :staff_id WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':status' => $newStatus,
                ':staff_id' => $staffId,
                ':id' => $transactionId
            ]);
        } else {
            $sql = "UPDATE transactions SET status = :status WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':status' => $newStatus,
                ':id' => $transactionId
            ]);
        }
    }

    /**
     * Оновити тільки staff_id транзакції (без зміни статусу)
     */
    public function updateTransactionStaff(int $transactionId, int $staffId): bool
    {
        $sql = "UPDATE transactions SET staff_id = :staff_id WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':staff_id' => $staffId,
            ':id' => $transactionId
        ]);
    }

    /**
     * Отримати транзакцію по ID
     */
    public function getTransactionById(int $transactionId): ?array
    {
        $sql = "
            SELECT 
                t.*,
                c.first_name as client_first_name,
                c.last_name as client_last_name,
                c.email as client_email,
                s.first_name as staff_first_name,
                s.last_name as staff_last_name,
                s.access_layer as staff_access_layer
            FROM transactions t
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN staff s ON t.staff_id = s.id
            WHERE t.id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $transactionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Отримати всі транзакції для замовлення (якщо є кілька)
     */
    public function getTransactionsForOrder(int $orderId): array
    {
        $order = $this->getOrderById($orderId);
        if (!$order || !$order['client_id']) {
            return [];
        }

        $sql = "
            SELECT 
                t.*,
                s.first_name as staff_first_name,
                s.last_name as staff_last_name
            FROM transactions t
            LEFT JOIN staff s ON t.staff_id = s.id
            WHERE t.client_id = :client_id
            ORDER BY t.date DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':client_id' => $order['client_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати статистику продажів за сьогодні (виключаємо Reject та Cancel)
     */
    public function getTodayStats(): array
    {
        $sql = "
            SELECT 
                COALESCE(SUM(value), 0) as total_value,
                COUNT(*) as transaction_count
            FROM transactions
            WHERE DATE(date) = CURDATE()
            AND status NOT IN ('Reject', 'Cancel', 'reject', 'cancel', 'Rejected', 'Cancelled')
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_value' => (float)($result['total_value'] ?? 0),
            'transaction_count' => (int)($result['transaction_count'] ?? 0)
        ];
    }

    /**
     * Отримати статистику pending транзакцій
     */
    public function getPendingStats(): array
    {
        $sql = "
            SELECT 
                COALESCE(SUM(value), 0) as total_value,
                COUNT(*) as transaction_count
            FROM transactions
            WHERE status = 'Pending'
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_value' => (float)($result['total_value'] ?? 0),
            'transaction_count' => (int)($result['transaction_count'] ?? 0)
        ];
    }

    /**
     * Отримати статистику approved (Complete) транзакцій за останні 7 днів
     */
    public function getApprovedLast7Days(): array
    {
        $sql = "
            SELECT 
                COALESCE(SUM(value), 0) as total_value,
                COUNT(*) as transaction_count
            FROM transactions
            WHERE status = 'Complete'
            AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_value' => (float)($result['total_value'] ?? 0),
            'transaction_count' => (int)($result['transaction_count'] ?? 0)
        ];
    }

    /**
     * Отримати дані для графіка продажів по днях (останні 30 днів)
     */
    public function getSalesByDay(int $days = 30): array
    {
        $sql = "
            SELECT 
                DATE(date) as sale_date,
                COALESCE(SUM(value), 0) as total_value,
                COUNT(*) as transaction_count
            FROM transactions
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(date)
            ORDER BY sale_date ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати дані для графіка продажів по місяцях (останні 12 місяців)
     */
    public function getSalesByMonth(int $months = 12): array
    {
        $sql = "
            SELECT 
                DATE_FORMAT(date, '%Y-%m') as sale_month,
                COALESCE(SUM(value), 0) as total_value,
                COUNT(*) as transaction_count
            FROM transactions
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
            GROUP BY DATE_FORMAT(date, '%Y-%m')
            ORDER BY sale_month ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати всі транзакції для таблиці (з пагінацією)
     */
    public function getAllTransactionsForStats(int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT 
                t.id,
                t.date,
                t.value,
                t.currency,
                t.status,
                t.description,
                t.client_id,
                t.staff_id,
                c.first_name as client_first_name,
                c.last_name as client_last_name,
                s.first_name as staff_first_name,
                s.last_name as staff_last_name
            FROM transactions t
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN staff s ON t.staff_id = s.id
            ORDER BY t.date DESC, t.id DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати фінансову статистику за період
     */
    public function getFinancialStatsByPeriod(string $period = 'day'): array
    {
        $dateCondition = "1=1";
        switch($period) {
            case 'day':
                $dateCondition = "DATE(date) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = "date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $dateCondition = "date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
                break;
            case 'all':
                $dateCondition = "1=1";
                break;
            default:
                $dateCondition = "DATE(date) = CURDATE()";
        }

        $sql = "
            SELECT 
                COALESCE(SUM(CASE WHEN status NOT IN ('Reject', 'Cancel', 'reject', 'cancel', 'Rejected', 'Cancelled') THEN value ELSE 0 END), 0) as total_value,
                COUNT(CASE WHEN status NOT IN ('Reject', 'Cancel', 'reject', 'cancel', 'Rejected', 'Cancelled') THEN 1 END) as transaction_count,
                SUM(CASE WHEN status = 'Complete' THEN value ELSE 0 END) as complete_value,
                SUM(CASE WHEN status = 'Pending' THEN value ELSE 0 END) as pending_value,
                SUM(CASE WHEN status = 'Reject' OR status = 'Cancel' THEN value ELSE 0 END) as rejected_value,
                COUNT(CASE WHEN status = 'Complete' THEN 1 END) as complete_count,
                COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'Reject' OR status = 'Cancel' THEN 1 END) as rejected_count
            FROM transactions
            WHERE {$dateCondition}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати статистику по статусах
     */
    public function getStatusStats(): array
    {
        $sql = "
            SELECT 
                status,
                COUNT(*) as count,
                COALESCE(SUM(value), 0) as total_value
            FROM transactions
            GROUP BY status
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати статистику клієнтів
     */
    public function getClientStats(): array
    {
        // Загальна кількість клієнтів
        $totalClientsSql = "SELECT COUNT(*) as total FROM clients";
        $totalStmt = $this->pdo->query($totalClientsSql);
        $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
        $totalClients = (int)($totalResult['total'] ?? 0);

        // Активні клієнти за останні 30 днів
        $activeClientsSql = "
            SELECT COUNT(DISTINCT client_id) as active_count
            FROM transactions
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ";
        $activeStmt = $this->pdo->query($activeClientsSql);
        $activeResult = $activeStmt->fetch(PDO::FETCH_ASSOC);
        $activeClients = (int)($activeResult['active_count'] ?? 0);

        // Середні значення
        $avgSql = "
            SELECT 
                AVG(avg_value) as avg_client_value,
                AVG(transaction_count) as avg_transactions_per_client
            FROM (
                SELECT 
                    client_id,
                    AVG(value) as avg_value,
                    COUNT(*) as transaction_count
                FROM transactions
                GROUP BY client_id
            ) client_stats
        ";
        $avgStmt = $this->pdo->query($avgSql);
        $avgResult = $avgStmt->fetch(PDO::FETCH_ASSOC);
        
        // Топ клієнти
        $topClientsSql = "
            SELECT 
                c.id,
                c.first_name,
                c.last_name,
                c.email,
                COUNT(t.id) as transaction_count,
                COALESCE(SUM(t.value), 0) as total_spent
            FROM clients c
            LEFT JOIN transactions t ON c.id = t.client_id
            GROUP BY c.id, c.first_name, c.last_name, c.email
            HAVING transaction_count > 0
            ORDER BY total_spent DESC
            LIMIT 5
        ";
        $topClientsStmt = $this->pdo->query($topClientsSql);
        $topClients = $topClientsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_clients' => $totalClients,
            'active_clients_30d' => $activeClients,
            'avg_client_value' => (float)($avgResult['avg_client_value'] ?? 0),
            'avg_transactions_per_client' => (float)($avgResult['avg_transactions_per_client'] ?? 0),
            'top_clients' => $topClients
        ];
    }

    /**
     * Отримати статистику рецептів (найпопулярніші)
     */
    public function getRecipeStats(): array
    {
        $sql = "
            SELECT 
                r.id,
                r.name,
                r.price,
                COUNT(DISTINCT o.id) as order_count,
                SUM(o.quantity) as total_quantity_sold,
                COALESCE(SUM(o.quantity * r.price), 0) as total_revenue
            FROM recipes r
            LEFT JOIN orders o ON r.id = o.recipe_id
            LEFT JOIN transaction_order to_rel ON o.id = to_rel.order_id
            LEFT JOIN transactions t ON to_rel.transaction_id = t.id AND t.status = 'Complete'
            GROUP BY r.id, r.name, r.price
            HAVING order_count > 0
            ORDER BY total_revenue DESC
            LIMIT 10
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати дані для графіка продажів по тижнях
     * На графіках показуємо всі транзакції, включаючи Reject та Cancel
     */
    public function getSalesByWeek(int $weeks = 12): array
    {
        $sql = "
            SELECT 
                YEARWEEK(date, 1) as week_number,
                DATE_FORMAT(MIN(date), '%Y-%m-%d') as week_start,
                COALESCE(SUM(value), 0) as total_value,
                COUNT(*) as transaction_count,
                COALESCE(SUM(CASE WHEN status NOT IN ('Reject', 'Cancel', 'reject', 'cancel', 'Rejected', 'Cancelled') THEN value ELSE 0 END), 0) as active_value
            FROM transactions
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL :weeks WEEK)
            GROUP BY YEARWEEK(date, 1)
            ORDER BY week_number ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':weeks', $weeks, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати загальну статистику компанії (виключаємо Reject та Cancel з підрахунків сум)
     */
    public function getCompanyOverview(): array
    {
        $sql = "
            SELECT 
                COUNT(DISTINCT CASE WHEN t.status NOT IN ('Reject', 'Cancel', 'reject', 'cancel', 'Rejected', 'Cancelled') THEN t.id END) as total_transactions,
                COALESCE(SUM(CASE WHEN t.status NOT IN ('Reject', 'Cancel', 'reject', 'cancel', 'Rejected', 'Cancelled') THEN t.value ELSE 0 END), 0) as total_revenue,
                COUNT(DISTINCT t.client_id) as unique_clients,
                COUNT(DISTINCT t.staff_id) as active_staff,
                AVG(CASE WHEN t.status NOT IN ('Reject', 'Cancel', 'reject', 'cancel', 'Rejected', 'Cancelled') THEN t.value END) as avg_transaction_value,
                COUNT(CASE WHEN t.status = 'Complete' THEN 1 END) as completed_transactions,
                COUNT(CASE WHEN t.status = 'Pending' THEN 1 END) as pending_transactions,
                COUNT(CASE WHEN t.status = 'Reject' OR t.status = 'Cancel' THEN 1 END) as rejected_transactions
            FROM transactions t
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати загальну кількість замовлень
     */
    public function getTotalOrders(): int
    {
        $sql = "SELECT COUNT(*) as total FROM orders";
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Отримати дані для графіка транзакцій по статусах (за останні 30 днів)
     */
    public function getStatusTrendsByDay(int $days = 30): array
    {
        $sql = "
            SELECT 
                DATE(date) as sale_date,
                COUNT(CASE WHEN status = 'Complete' THEN 1 END) as complete_count,
                COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'Reject' OR status = 'Cancel' THEN 1 END) as rejected_count
            FROM transactions
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(date)
            ORDER BY sale_date ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати дані для графіка топ рецептів
     */
    public function getTopRecipesChart(int $limit = 5): array
    {
        $sql = "
            SELECT 
                r.name,
                COUNT(DISTINCT o.id) as order_count,
                COALESCE(SUM(o.quantity * r.price), 0) as total_revenue
            FROM recipes r
            LEFT JOIN orders o ON r.id = o.recipe_id
            LEFT JOIN transaction_order to_rel ON o.id = to_rel.order_id
            LEFT JOIN transactions t ON to_rel.transaction_id = t.id AND t.status = 'Complete'
            GROUP BY r.id, r.name
            HAVING order_count > 0
            ORDER BY total_revenue DESC
            LIMIT :limit
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати дані для графіка по співробітниках (топ менеджери)
     */
    public function getTopStaffChart(int $limit = 5): array
    {
        $sql = "
            SELECT 
                s.first_name,
                s.last_name,
                COUNT(t.id) as transaction_count,
                COALESCE(SUM(t.value), 0) as total_revenue
            FROM staff s
            LEFT JOIN transactions t ON s.id = t.staff_id
            WHERE t.id IS NOT NULL
            GROUP BY s.id, s.first_name, s.last_name
            ORDER BY total_revenue DESC
            LIMIT :limit
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати дані для графіка розподілу транзакцій по днях тижня
     */
    public function getTransactionsByWeekday(): array
    {
        $sql = "
            SELECT 
                DAYNAME(date) as weekday,
                DAYOFWEEK(date) as day_number,
                COUNT(*) as transaction_count,
                COALESCE(SUM(value), 0) as total_value,
                AVG(value) as avg_value
            FROM transactions
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY DAYNAME(date), DAYOFWEEK(date)
            ORDER BY day_number ASC
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

