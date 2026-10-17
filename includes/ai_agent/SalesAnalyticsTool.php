<?php

class SalesAnalyticsTool
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Build a consistent reporting period.
     * null = all time
     * 1 = today
     * 7 = today plus previous six calendar days
     */
    private function buildPeriod(
        ?int $days,
        string $dateColumn = "created_at"
    ): array {
        if ($days === null) {
            return [
                "where" => "",
                "period_days" => null,
                "period_label" => "All-Time"
            ];
        }

        $days = max(1, min($days, 90));
        $offset = $days - 1;

        return [
            "where" =>
                " WHERE " . $dateColumn
                . " >= DATE_SUB(CURDATE(), INTERVAL "
                . $offset . " DAY)"
                . " AND " . $dateColumn
                . " < DATE_ADD(CURDATE(), INTERVAL 1 DAY)",

            "period_days" => $days,

            "period_label" => $days === 1
                ? "Today"
                : "Last " . $days
                    . " Calendar Days (Today Included)"
        ];
    }

    /**
     * Existing sales summary.
     * Only completed orders contribute to sales revenue.
     */
    public function getSalesSummary(?int $days = 7): array
    {
        $period = $this->buildPeriod($days);

        $sql = "
            SELECT
                COUNT(*) AS total_orders,

                SUM(CASE WHEN order_status = 'pending'
                    THEN 1 ELSE 0 END) AS pending_orders,

                SUM(CASE WHEN order_status = 'confirmed'
                    THEN 1 ELSE 0 END) AS confirmed_orders,

                SUM(CASE WHEN order_status = 'preparing'
                    THEN 1 ELSE 0 END) AS preparing_orders,

                SUM(CASE WHEN order_status = 'ready'
                    THEN 1 ELSE 0 END) AS ready_orders,

                SUM(CASE WHEN order_status = 'out_for_delivery'
                    THEN 1 ELSE 0 END) AS out_for_delivery_orders,

                SUM(CASE WHEN order_status = 'completed'
                    THEN 1 ELSE 0 END) AS completed_orders,

                SUM(CASE WHEN order_status = 'cancelled'
                    THEN 1 ELSE 0 END) AS cancelled_orders,

                COALESCE(SUM(
                    CASE WHEN order_status = 'completed'
                    THEN total ELSE 0 END
                ), 0) AS total_sales,

                COALESCE(SUM(
                    CASE WHEN order_status = 'completed'
                    THEN subtotal ELSE 0 END
                ), 0) AS completed_subtotal,

                COALESCE(SUM(
                    CASE WHEN order_status = 'completed'
                    THEN delivery_fee ELSE 0 END
                ), 0) AS completed_delivery_fees,

                COALESCE(AVG(
                    CASE WHEN order_status = 'completed'
                    THEN total ELSE NULL END
                ), 0) AS average_order_value

            FROM orders
            " . $period["where"];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            "period_days" => $period["period_days"],
            "period_label" => $period["period_label"],
            "currency" => "LKR",

            "total_orders" => (int) ($row["total_orders"] ?? 0),

            "pending_orders" => (int) ($row["pending_orders"] ?? 0),
            "confirmed_orders" => (int) ($row["confirmed_orders"] ?? 0),
            "preparing_orders" => (int) ($row["preparing_orders"] ?? 0),
            "ready_orders" => (int) ($row["ready_orders"] ?? 0),

            "out_for_delivery_orders" =>
                (int) ($row["out_for_delivery_orders"] ?? 0),

            "completed_orders" =>
                (int) ($row["completed_orders"] ?? 0),

            "cancelled_orders" =>
                (int) ($row["cancelled_orders"] ?? 0),

            "total_sales" => round(
                (float) ($row["total_sales"] ?? 0),
                2
            ),

            "completed_subtotal" => round(
                (float) ($row["completed_subtotal"] ?? 0),
                2
            ),

            "completed_delivery_fees" => round(
                (float) ($row["completed_delivery_fees"] ?? 0),
                2
            ),

            "average_order_value" => round(
                (float) ($row["average_order_value"] ?? 0),
                2
            )
        ];
    }

    /**
     * Existing daily sales report.
     */
    public function getDailySales(?int $days = 7): array
    {
        $period = $this->buildPeriod($days);

        $sql = "
            SELECT
                DATE(created_at) AS sales_date,
                COUNT(*) AS total_orders,

                SUM(CASE WHEN order_status = 'pending'
                    THEN 1 ELSE 0 END) AS pending_orders,

                SUM(CASE WHEN order_status = 'confirmed'
                    THEN 1 ELSE 0 END) AS confirmed_orders,

                SUM(CASE WHEN order_status = 'preparing'
                    THEN 1 ELSE 0 END) AS preparing_orders,

                SUM(CASE WHEN order_status = 'ready'
                    THEN 1 ELSE 0 END) AS ready_orders,

                SUM(CASE WHEN order_status = 'out_for_delivery'
                    THEN 1 ELSE 0 END) AS out_for_delivery_orders,

                SUM(CASE WHEN order_status = 'completed'
                    THEN 1 ELSE 0 END) AS completed_orders,

                SUM(CASE WHEN order_status = 'cancelled'
                    THEN 1 ELSE 0 END) AS cancelled_orders,

                COALESCE(SUM(
                    CASE WHEN order_status = 'completed'
                    THEN total ELSE 0 END
                ), 0) AS total_sales

            FROM orders
            " . $period["where"] . "
            GROUP BY DATE(created_at)
            ORDER BY sales_date ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            static function (array $row): array {
                return [
                    "date" => $row["sales_date"],
                    "total_orders" => (int) $row["total_orders"],

                    "pending_orders" => (int) $row["pending_orders"],
                    "confirmed_orders" => (int) $row["confirmed_orders"],
                    "preparing_orders" => (int) $row["preparing_orders"],
                    "ready_orders" => (int) $row["ready_orders"],

                    "out_for_delivery_orders" =>
                        (int) $row["out_for_delivery_orders"],

                    "completed_orders" =>
                        (int) $row["completed_orders"],

                    "cancelled_orders" =>
                        (int) $row["cancelled_orders"],

                    "total_sales" => round(
                        (float) $row["total_sales"],
                        2
                    )
                ];
            },
            $rows
        );
    }

    /**
     * Shared product-performance query.
     *
     * Quantities are derived from order_items.
     * Revenue is calculated only from completed orders.
     * Cancelled quantities are excluded from sell-through ranking.
     */
    private function queryProductPerformance(
        ?int $days,
        int $limit,
        string $sortBy
    ): array {
        $limit = max(1, min($limit, 100));

        // Only internal, predefined sorting expressions are accepted.
        $orderBy = match ($sortBy) {
            "best_selling" =>
                "non_cancelled_quantity DESC,
                 completed_quantity DESC,
                 product_name ASC",

            "completed_sales" =>
                "completed_sales DESC,
                 completed_quantity DESC,
                 product_name ASC",

            default =>
                "product_name ASC"
        };

        $period = $this->buildPeriod($days, "o.created_at");

        $sql = "
            SELECT
                oi.product_id,
                oi.product_name,

                COALESCE(SUM(oi.quantity), 0)
                    AS total_ordered_quantity,

                COALESCE(SUM(
                    CASE
                        WHEN o.order_status <> 'cancelled'
                        THEN oi.quantity
                        ELSE 0
                    END
                ), 0) AS non_cancelled_quantity,

                COALESCE(SUM(
                    CASE
                        WHEN o.order_status = 'completed'
                        THEN oi.quantity
                        ELSE 0
                    END
                ), 0) AS completed_quantity,

                COALESCE(SUM(
                    CASE
                        WHEN o.order_status = 'completed'
                        THEN oi.subtotal
                        ELSE 0
                    END
                ), 0) AS completed_sales,

                COUNT(DISTINCT o.id)
                    AS order_count,

                COUNT(DISTINCT CASE
                    WHEN o.order_status = 'completed'
                    THEN o.id
                    ELSE NULL
                END) AS completed_order_count

            FROM order_items AS oi

            INNER JOIN orders AS o
                ON o.id = oi.order_id

            " . $period["where"] . "

            GROUP BY
                oi.product_id,
                oi.product_name

            ORDER BY " . $orderBy . "

            LIMIT " . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $products = array_map(
            static function (array $row): array {
                return [
                    "product_id" => (int) $row["product_id"],

                    "product_name" => $row["product_name"],

                    "total_ordered_quantity" =>
                        (int) $row["total_ordered_quantity"],

                    "non_cancelled_quantity" =>
                        (int) $row["non_cancelled_quantity"],

                    "completed_quantity" =>
                        (int) $row["completed_quantity"],

                    "completed_sales" => round(
                        (float) $row["completed_sales"],
                        2
                    ),

                    "order_count" =>
                        (int) $row["order_count"],

                    "completed_order_count" =>
                        (int) $row["completed_order_count"]
                ];
            },
            $rows
        );

        return [
            "period_days" => $period["period_days"],
            "period_label" => $period["period_label"],
            "currency" => "LKR",
            "products" => $products
        ];
    }

    /**
     * Best-selling products ranked by quantity
     * in non-cancelled orders.
     */
    public function getBestSellingProducts(
        ?int $days = 7,
        int $limit = 10
    ): array {
        $result = $this->queryProductPerformance(
            $days,
            $limit,
            "best_selling"
        );

        $result["report_type"] = "best_selling_products";
        $result["ranking_basis"] =
            "Quantity in non-cancelled orders";

        return $result;
    }

    /**
     * Product-wise sales ranked by completed-order revenue.
     */
    public function getProductWiseSales(
        ?int $days = 7,
        int $limit = 20
    ): array {
        $result = $this->queryProductPerformance(
            $days,
            $limit,
            "completed_sales"
        );

        $result["report_type"] = "product_wise_sales";
        $result["ranking_basis"] =
            "Revenue from completed orders only";

        return $result;
    }
}
