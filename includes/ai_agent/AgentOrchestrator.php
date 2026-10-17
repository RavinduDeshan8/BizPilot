<?php

require_once __DIR__ . "/AgentCore.php";
require_once __DIR__ . "/BusinessDataTools.php";
require_once __DIR__ . "/SalesAnalyticsTool.php";

class AgentOrchestrator
{
    private AgentCore $agent;
    private BusinessDataTools $businessTools;
    private SalesAnalyticsTool $salesTools;

    public function __construct(PDO $pdo)
    {
        $this->agent = new AgentCore();
        $this->businessTools = new BusinessDataTools($pdo);
        $this->salesTools = new SalesAnalyticsTool($pdo);
    }

    /**
     * Determine the reporting period.
     * null = all time.
     */
    private function resolveReportDays(string $question): ?int
    {
        if (preg_match(
            '/all[\s-]?time|lifetime|ever|overall sales|all orders|all sales|since the beginning|සියලුම කාල|සමස්ත කාලය/u',
            $question
        ) === 1) {
            return null;
        }

        if (preg_match('/\btoday\b|අද/u', $question) === 1) {
            return 1;
        }

        if (preg_match(
            '/\b30\s*days?\b|\bmonth\b|මාසය/u',
            $question
        ) === 1) {
            return 30;
        }

        if (preg_match('/\b90\s*days?\b/u', $question) === 1) {
            return 90;
        }

        // Default: last 7 calendar days, including today.
        return 7;
    }

    public function handle(string $question): array
    {
        $question = trim($question);

        if ($question === "") {
            return [
                "success" => false,
                "message" => "Please enter a question."
            ];
        }

        if (mb_strlen($question) > 4000) {
            return [
                "success" => false,
                "message" => "Your question is too long."
            ];
        }

        $q = mb_strtolower($question);
        $context = [];

        /*
         * Identify question types.
         */
        $asksLowStock = preg_match(
            '/low[\s-]+stock|stock shortage|reorder|restock|තොග අඩු/u',
            $q
        ) === 1;

        $asksBestSelling = preg_match(
            '/best[\s-]?selling|top[\s-]*selling|most[\s-]+sold|top[\s-]+products|popular products|වැඩියෙන්ම විකිණෙන|වැඩිපුරම විකිණෙන/u',
            $q
        ) === 1;

        $asksProductWiseSales = preg_match(
            '/product[\s-]*wise[\s-]*sales|sales[\s-]+by[\s-]+product|product[\s-]+sales|sales[\s-]+per[\s-]+product|භාණ්ඩ අනුව විකුණුම්|භාණ්ඩයක විකුණුම්/u',
            $q
        ) === 1;

        $asksSales = preg_match(
            '/sales|revenue|turnover|completed orders|average order|daily sales|sales performance|all[\s-]?time|lifetime|overall sales|total orders|all orders|all sales|business performance|විකුණුම්|අලෙවිය|ආදායම|සියලුම කාල|සමස්ත කාලය/u',
            $q
        ) === 1;

        $asksProducts = preg_match(
            '/product|products|price|available|availability|recommend|suggest|budget|inventory|භාණ්ඩ|මිල|තොග|නිර්දේශ/u',
            $q
        ) === 1;

        /*
         * 1. Low-stock inventory report.
         */
        if ($asksLowStock) {
            $products = $this->businessTools->getLowStockProducts(5);

            $lines = [
                "LOW-STOCK PRODUCTS",
                "Rule: active products with stock at or below 5 units.",
                ""
            ];

            if (count($products) === 0) {
                $lines[] =
                    "No active products meet the low-stock threshold.";
            } else {
                foreach ($products as $product) {
                    $name = (string) (
                        $product["name"] ?? "Unnamed product"
                    );

                    $stock = (int) ($product["stock"] ?? 0);

                    $category = (string) (
                        $product["category"] ?? "Uncategorized"
                    );

                    $lines[] = "- " . $name
                        . " | Category: " . $category
                        . " | Stock: " . $stock . " units";
                }
            }

            $context["low_stock_products"] = $products;
            $context["low_stock_report"] = implode("\n", $lines);
        }

        /*
         * 2. Product analytics.
         *
         * These reports are returned directly from verified database
         * results. They do not ask the LLM to calculate sales figures.
         */
        if ($asksBestSelling || $asksProductWiseSales) {
            $days = $this->resolveReportDays($q);

            if ($asksBestSelling) {
                $result = $this->salesTools->getBestSellingProducts(
                    $days,
                    10
                );

                $reportTitle = "BEST-SELLING PRODUCTS";
                $rankingBasis =
                    "Ranked by quantity in non-cancelled orders.";
                $contextKey = "best_selling_products";
            } else {
                $result = $this->salesTools->getProductWiseSales(
                    $days,
                    20
                );

                $reportTitle = "PRODUCT-WISE COMPLETED SALES";
                $rankingBasis =
                    "Ranked by revenue from completed orders only.";
                $contextKey = "product_wise_sales";
            }

            $currency = (string) ($result["currency"] ?? "LKR");

            $periodLabel = (string) (
                $result["period_label"] ?? "Last 7 Calendar Days"
            );

            $lines = [
                "BIZPILOT PRODUCT ANALYTICS",
                "Report: " . $reportTitle,
                "Period: " . $periodLabel,
                "Currency: " . $currency,
                $rankingBasis,
                ""
            ];

            $products = $result["products"] ?? [];

            if (count($products) === 0) {
                $lines[] =
                    "No product order records were found for this period.";
            } else {
                $rank = 1;

                foreach ($products as $product) {
                    $name = (string) (
                        $product["product_name"] ?? "Unknown product"
                    );

                    $totalQuantity = (int) (
                        $product["total_ordered_quantity"] ?? 0
                    );

                    $nonCancelledQuantity = (int) (
                        $product["non_cancelled_quantity"] ?? 0
                    );

                    $completedQuantity = (int) (
                        $product["completed_quantity"] ?? 0
                    );

                    $completedSales = (float) (
                        $product["completed_sales"] ?? 0
                    );

                    $orderCount = (int) (
                        $product["order_count"] ?? 0
                    );

                    $completedOrderCount = (int) (
                        $product["completed_order_count"] ?? 0
                    );

                    $lines[] = $rank . ". " . $name;

                    $lines[] =
                        "   Quantity in all orders: "
                        . $totalQuantity;

                    $lines[] =
                        "   Quantity excluding cancelled orders: "
                        . $nonCancelledQuantity;

                    $lines[] =
                        "   Completed quantity: "
                        . $completedQuantity;

                    $lines[] =
                        "   Completed sales: " . $currency . " "
                        . number_format($completedSales, 2);

                    $lines[] =
                        "   Orders containing this product: "
                        . $orderCount;

                    $lines[] =
                        "   Completed orders containing this product: "
                        . $completedOrderCount;

                    $lines[] = "";

                    $rank++;
                }
            }

            $lines[] =
                "Completed sales include completed orders only.";

            $lines[] =
                "Product sales revenue is not the same as net profit.";

            $context[$contextKey] = $result;
            $context["product_analytics_report"] =
                implode("\n", $lines);

            return [
                "success" => true,
                "message" => $context["product_analytics_report"],
                "data" => $context
            ];
        }

        /*
         * 3. Existing sales report.
         * All-Time requests take priority over shorter periods.
         */
        if ($asksSales) {
            $days = $this->resolveReportDays($q);

            $summary = $this->salesTools->getSalesSummary($days);

            $dailySales = $this->salesTools->getDailySales($days);

            $currency = (string) (
                $summary["currency"] ?? "LKR"
            );

            $periodLabel = (string) (
                $summary["period_label"] ?? "Last 7 Calendar Days"
            );

            $lines = [
                "BIZPILOT SALES SUMMARY",
                "Report period: " . $periodLabel,
                "Currency: " . $currency,
                "",
                "ORDER STATUS SUMMARY",

                "Total orders: " .
                    (int) ($summary["total_orders"] ?? 0),

                "Pending orders: " .
                    (int) ($summary["pending_orders"] ?? 0),

                "Confirmed orders: " .
                    (int) ($summary["confirmed_orders"] ?? 0),

                "Preparing orders: " .
                    (int) ($summary["preparing_orders"] ?? 0),

                "Ready orders: " .
                    (int) ($summary["ready_orders"] ?? 0),

                "Out-for-delivery orders: " .
                    (int) ($summary["out_for_delivery_orders"] ?? 0),

                "Completed orders: " .
                    (int) ($summary["completed_orders"] ?? 0),

                "Cancelled orders: " .
                    (int) ($summary["cancelled_orders"] ?? 0),

                "",
                "COMPLETED-ORDER SALES",

                "Sales from completed orders: " . $currency . " "
                    . number_format(
                        (float) ($summary["total_sales"] ?? 0),
                        2
                    ),

                "Completed-order subtotal: " . $currency . " "
                    . number_format(
                        (float) ($summary["completed_subtotal"] ?? 0),
                        2
                    ),

                "Completed-order delivery fees: " . $currency . " "
                    . number_format(
                        (float) (
                            $summary["completed_delivery_fees"] ?? 0
                        ),
                        2
                    ),

                "Average completed order value: " . $currency . " "
                    . number_format(
                        (float) ($summary["average_order_value"] ?? 0),
                        2
                    ),

                "",
                "DAILY SALES"
            ];

            if (count($dailySales) === 0) {
                $lines[] =
                    "No orders were found for this reporting period.";
            } else {
                foreach ($dailySales as $day) {
                    $date = (string) (
                        $day["date"] ?? "Unknown date"
                    );

                    $orders = (int) (
                        $day["total_orders"] ?? 0
                    );

                    $completedOrders = (int) (
                        $day["completed_orders"] ?? 0
                    );

                    $amount = (float) (
                        $day["total_sales"] ?? 0
                    );

                    $lines[] = "- " . $date
                        . " | Orders: " . $orders
                        . " | Completed: " . $completedOrders
                        . " | Completed sales: " . $currency . " "
                        . number_format($amount, 2);
                }
            }

            $lines[] = "";
            $lines[] = "Sales are not the same as net profit.";
            $lines[] =
                "Only completed orders contribute to reported sales.";

            $context["sales_summary"] = $summary;
            $context["daily_sales"] = $dailySales;
            $context["sales_report"] = implode("\n", $lines);
        }

        /*
         * 4. Return verified reports directly.
         */
        if ($asksLowStock || $asksSales) {
            $reports = [];

            if (isset($context["low_stock_report"])) {
                $reports[] = $context["low_stock_report"];
            }

            if (isset($context["sales_report"])) {
                $reports[] = $context["sales_report"];
            }

            return [
                "success" => true,
                "message" => implode("\n\n", $reports),
                "data" => $context
            ];
        }

        /*
         * 5. Product questions: verified database data.
         */
        if ($asksProducts) {
            $products = $this->businessTools->searchProducts("", 20);

            if (count($products) === 0) {
                return [
                    "success" => true,
                    "message" =>
                        "No active products were found in the database."
                ];
            }

            $context["products"] = $products;
        }

        /*
         * 6. General business questions: AI assistance.
         */
        if (empty($context)) {
            return $this->agent->ask(
                "User question:\n" . $question
                . "\n\nUse only verified information available to you. "
                . "Do not invent business records or claim to execute actions."
            );
        }

        $json = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );

        if ($json === false) {
            return [
                "success" => false,
                "message" => "Business data could not be prepared."
            ];
        }

        return $this->agent->ask(
            "User question:\n" . $question
            . "\n\nVerified BizPilot database context:\n" . $json
            . "\n\nAnswer the actual question using only this data. "
            . "All prices are in LKR. Do not invent facts or claim to "
            . "have changed the database."
        );
    }
}
