<?php

require_once "../config/admin_auth.php";
requireAdmin();

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| DASHBOARD DATA
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| TOTAL PRODUCTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM products
    WHERE status = 'active'
");

$totalProducts = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TOTAL CUSTOMERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'customer'
");

$totalCustomers = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| COMPLETED ONLINE ORDERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE order_status = 'completed'
");

$totalOnlineOrders = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| COMPLETED POS SALES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM pos_sales
    WHERE sale_status = 'completed'
");

$totalPosSales = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TOTAL TRANSACTIONS
|--------------------------------------------------------------------------
*/

$totalTransactions =
    $totalOnlineOrders +
    $totalPosSales;


/*
|--------------------------------------------------------------------------
| ONLINE REVENUE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COALESCE(SUM(total), 0)
    FROM orders
    WHERE order_status = 'completed'
");

$onlineRevenue = (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| POS REVENUE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COALESCE(SUM(total), 0)
    FROM pos_sales
    WHERE sale_status = 'completed'
");

$posRevenue = (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TOTAL REVENUE
|--------------------------------------------------------------------------
*/

$totalRevenue =
    $onlineRevenue +
    $posRevenue;


/*
|--------------------------------------------------------------------------
| ONLINE PRODUCT COST
|--------------------------------------------------------------------------
|
| order_items does not currently store historical cost_price.
| Therefore current products.cost_price is used.
|
*/

$stmt = $pdo->query("
    SELECT COALESCE(
        SUM(
            oi.quantity * COALESCE(p.cost_price, 0)
        ),
        0
    )
    FROM order_items oi
    INNER JOIN orders o
        ON oi.order_id = o.id
    LEFT JOIN products p
        ON oi.product_id = p.id
    WHERE o.order_status = 'completed'
");

$onlineCost = (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| POS PRODUCT COST
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COALESCE(
        SUM(
            psi.quantity * COALESCE(psi.cost_price, 0)
        ),
        0
    )
    FROM pos_sale_items psi
    INNER JOIN pos_sales ps
        ON psi.sale_id = ps.id
    WHERE ps.sale_status = 'completed'
");

$posCost = (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TOTAL PRODUCT COST
|--------------------------------------------------------------------------
*/

$totalProductCost =
    $onlineCost +
    $posCost;


/*
|--------------------------------------------------------------------------
| GROSS PROFIT
|--------------------------------------------------------------------------
*/

$grossProfit =
    $totalRevenue -
    $totalProductCost;


/*
|--------------------------------------------------------------------------
| TOTAL EXPENSES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM expenses
");

$totalExpenses = (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| NET PROFIT
|--------------------------------------------------------------------------
*/

$netProfit =
    $grossProfit -
    $totalExpenses;


/*
|--------------------------------------------------------------------------
| PROFIT MARGIN
|--------------------------------------------------------------------------
*/

$profitMargin =
    $totalRevenue > 0
        ? ($netProfit / $totalRevenue) * 100
        : 0;


/*
|--------------------------------------------------------------------------
| PENDING / ACTIVE ORDERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE order_status IN (
        'pending',
        'confirmed',
        'preparing'
    )
");

$pendingOrders = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| LOW STOCK PRODUCTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        name,
        stock,
        low_stock_level
    FROM products
    WHERE status = 'active'
      AND stock <= low_stock_level
    ORDER BY stock ASC
");

$lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| TODAY REVENUE
|--------------------------------------------------------------------------
*/

$today = date("Y-m-d");


$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total), 0)
    FROM orders
    WHERE order_status = 'completed'
      AND DATE(created_at) = ?
");

$stmt->execute([$today]);

$todayOnline = (float) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total), 0)
    FROM pos_sales
    WHERE sale_status = 'completed'
      AND DATE(created_at) = ?
");

$stmt->execute([$today]);

$todayPos = (float) $stmt->fetchColumn();


$todayRevenue =
    $todayOnline +
    $todayPos;


/*
|--------------------------------------------------------------------------
| THIS MONTH REVENUE
|--------------------------------------------------------------------------
*/

$monthStart = date("Y-m-01");
$monthEnd   = date("Y-m-t");


$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total), 0)
    FROM orders
    WHERE order_status = 'completed'
      AND DATE(created_at) BETWEEN ? AND ?
");

$stmt->execute([
    $monthStart,
    $monthEnd
]);

$monthOnline = (float) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total), 0)
    FROM pos_sales
    WHERE sale_status = 'completed'
      AND DATE(created_at) BETWEEN ? AND ?
");

$stmt->execute([
    $monthStart,
    $monthEnd
]);

$monthPos = (float) $stmt->fetchColumn();


$monthRevenue =
    $monthOnline +
    $monthPos;


/*
|--------------------------------------------------------------------------
| THIS MONTH TRANSACTIONS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM orders
    WHERE order_status = 'completed'
      AND DATE(created_at) BETWEEN ? AND ?
");

$stmt->execute([
    $monthStart,
    $monthEnd
]);

$monthOnlineTransactions = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM pos_sales
    WHERE sale_status = 'completed'
      AND DATE(created_at) BETWEEN ? AND ?
");

$stmt->execute([
    $monthStart,
    $monthEnd
]);

$monthPosTransactions = (int) $stmt->fetchColumn();


$monthTransactions =
    $monthOnlineTransactions +
    $monthPosTransactions;


/*
|--------------------------------------------------------------------------
| LAST 6 MONTHS SALES
|--------------------------------------------------------------------------
*/

$chartLabels = [];
$chartData   = [];
$chartOnline = [];
$chartPos    = [];


for ($i = 5; $i >= 0; $i--) {

    $timestamp = strtotime("-$i months");

    $startDate = date(
        "Y-m-01",
        $timestamp
    );

    $endDate = date(
        "Y-m-t",
        $timestamp
    );

    $label = date(
        "M",
        $timestamp
    );


    /*
    |--------------------------------------------------------------------------
    | ONLINE SALES
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0)
        FROM orders
        WHERE order_status = 'completed'
          AND DATE(created_at) BETWEEN ? AND ?
    ");

    $stmt->execute([
        $startDate,
        $endDate
    ]);

    $onlineMonth = (float) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | POS SALES
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0)
        FROM pos_sales
        WHERE sale_status = 'completed'
          AND DATE(created_at) BETWEEN ? AND ?
    ");

    $stmt->execute([
        $startDate,
        $endDate
    ]);

    $posMonth = (float) $stmt->fetchColumn();


    $chartLabels[] = $label;
    $chartOnline[] = $onlineMonth;
    $chartPos[]    = $posMonth;

    $chartData[] =
        $onlineMonth +
        $posMonth;
}


/*
|--------------------------------------------------------------------------
| TOP PRODUCTS - ONLINE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        oi.product_id,
        oi.product_name,
        SUM(oi.quantity) AS quantity_sold,
        SUM(oi.subtotal) AS sales_amount
    FROM order_items oi
    INNER JOIN orders o
        ON oi.order_id = o.id
    WHERE o.order_status = 'completed'
    GROUP BY
        oi.product_id,
        oi.product_name
    ORDER BY
        quantity_sold DESC
    LIMIT 10
");

$onlineTopProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| TOP PRODUCTS - POS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        psi.product_id,
        psi.product_name,
        SUM(psi.quantity) AS quantity_sold,
        SUM(psi.subtotal) AS sales_amount
    FROM pos_sale_items psi
    INNER JOIN pos_sales ps
        ON psi.sale_id = ps.id
    WHERE ps.sale_status = 'completed'
    GROUP BY
        psi.product_id,
        psi.product_name
    ORDER BY
        quantity_sold DESC
    LIMIT 10
");

$posTopProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| COMBINE TOP PRODUCTS
|--------------------------------------------------------------------------
*/

$combinedProducts = [];


/*
|--------------------------------------------------------------------------
| ADD ONLINE PRODUCTS
|--------------------------------------------------------------------------
*/

foreach ($onlineTopProducts as $product) {

    $productId = (int) $product["product_id"];

    if (!isset($combinedProducts[$productId])) {

        $combinedProducts[$productId] = [
            "product_id"    => $productId,
            "product_name"  => $product["product_name"],
            "quantity_sold" => 0,
            "sales_amount"  => 0
        ];
    }

    $combinedProducts[$productId]["quantity_sold"] +=
        (int) $product["quantity_sold"];

    $combinedProducts[$productId]["sales_amount"] +=
        (float) $product["sales_amount"];
}


/*
|--------------------------------------------------------------------------
| ADD POS PRODUCTS
|--------------------------------------------------------------------------
*/

foreach ($posTopProducts as $product) {

    $productId = (int) $product["product_id"];

    if (!isset($combinedProducts[$productId])) {

        $combinedProducts[$productId] = [
            "product_id"    => $productId,
            "product_name"  => $product["product_name"],
            "quantity_sold" => 0,
            "sales_amount"  => 0
        ];
    }

    $combinedProducts[$productId]["quantity_sold"] +=
        (int) $product["quantity_sold"];

    $combinedProducts[$productId]["sales_amount"] +=
        (float) $product["sales_amount"];
}


/*
|--------------------------------------------------------------------------
| SORT COMBINED PRODUCTS
|--------------------------------------------------------------------------
*/

usort(
    $combinedProducts,
    function ($a, $b) {

        return
            $b["quantity_sold"]
            <=>
            $a["quantity_sold"];
    }
);


/*
|--------------------------------------------------------------------------
| KEEP TOP 5
|--------------------------------------------------------------------------
*/

$topProducts = array_slice(
    $combinedProducts,
    0,
    5
);


/*
|--------------------------------------------------------------------------
| RECENT ONLINE ORDERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        o.id,
        o.order_number,
        o.total,
        o.order_status,
        o.fulfillment_type,
        o.created_at,
        u.full_name AS customer_name
    FROM orders o
    LEFT JOIN users u
        ON o.user_id = u.id
    ORDER BY
        o.created_at DESC
    LIMIT 8
");

$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| RECENT POS SALES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        ps.id,
        ps.invoice_number,
        ps.total,
        ps.payment_method,
        ps.created_at,
        u.full_name AS staff_name
    FROM pos_sales ps
    LEFT JOIN users u
        ON ps.staff_id = u.id
    WHERE ps.sale_status = 'completed'
    ORDER BY
        ps.created_at DESC
    LIMIT 8
");

$recentPosSales = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| RECENT BUSINESS ACTIVITY
|--------------------------------------------------------------------------
*/

$recentActivity = [];


foreach ($recentOrders as $order) {

    $recentActivity[] = [

        "type" =>
            "Online Order",

        "reference" =>
            $order["order_number"],

        "person" =>
            $order["customer_name"] ?? "Customer",

        "amount" =>
            (float) $order["total"],

        "status" =>
            $order["order_status"],

        "date" =>
            $order["created_at"],

        "sort_date" =>
            strtotime($order["created_at"])
    ];
}


foreach ($recentPosSales as $sale) {

    $recentActivity[] = [

        "type" =>
            "POS Sale",

        "reference" =>
            $sale["invoice_number"],

        "person" =>
            $sale["staff_name"] ?? "Staff",

        "amount" =>
            (float) $sale["total"],

        "status" =>
            "completed",

        "date" =>
            $sale["created_at"],

        "sort_date" =>
            strtotime($sale["created_at"])
    ];
}


usort(
    $recentActivity,
    function ($a, $b) {

        return
            $b["sort_date"]
            <=>
            $a["sort_date"];
    }
);


$recentActivity = array_slice(
    $recentActivity,
    0,
    10
);


/*
|--------------------------------------------------------------------------
| FORMAT MONEY
|--------------------------------------------------------------------------
*/

function money($amount)
{
    return "Rs. " . number_format(
        (float) $amount,
        2
    );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Admin Dashboard - BizPilot AI
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <script
        src="https://cdn.jsdelivr.net/npm/chart.js"
    ></script>

    <style>

        body {
            background: #f5f7fb;
        }

        .dashboard-title {
            font-weight: 700;
        }

        .stat-card {
            border: none;
            border-radius: 18px;
            box-shadow:
                0 5px 20px
                rgba(0, 0, 0, .06);
            height: 100%;
            transition: .2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }

        .stat-number {
            font-size: 27px;
            font-weight: 700;
            margin-top: 6px;
        }

        .dashboard-card {
            border: none;
            border-radius: 18px;
            box-shadow:
                0 5px 20px
                rgba(0, 0, 0, .06);
        }

        .quick-btn {
            border-radius: 12px;
            padding: 15px;
            font-weight: 600;
        }

        .low-stock-item {
            border-bottom: 1px solid #eee;
            padding: 12px 0;
        }

        .low-stock-item:last-child {
            border-bottom: none;
        }

        .stock-danger {
            color: #dc3545;
            font-weight: 700;
        }

        .stock-warning {
            color: #fd7e14;
            font-weight: 700;
        }

        .profit-positive {
            color: #198754;
        }

        .profit-negative {
            color: #dc3545;
        }

        .badge-status {
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 12px;
        }

        .metric-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .online-icon {
            background: #e7f1ff;
        }

        .pos-icon {
            background: #e8f7ef;
        }

    </style>

</head>

<body>

<div class="container-fluid py-4">

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div
        class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"
    >

        <div>

            <h2 class="dashboard-title mb-1">
                Admin Dashboard
            </h2>

            <p class="text-muted mb-0">
                Welcome to BizPilot AI management system
            </p>

        </div>

        <div>

            <a
                href="../index.php"
                class="btn btn-outline-dark me-2"
            >
                Store
            </a>

            <a
                href="../logout.php"
                class="btn btn-dark"
            >
                Logout
            </a>

        </div>

    </div>

<!-- BIZPILOT AI AGENT -->
<div class="card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="fw-bold mb-1">BizPilot AI Agent</h5>
            <p class="text-muted mb-0">
                Ask about sales, inventory, and business performance.
            </p>
        </div>
        <a href="ai_agent_approvals.php" class="btn btn-outline-primary btn-sm">
            Review AI Proposals
        </a>
    </div>

    <form id="bizPilotAgentForm">
        <label for="bizPilotAgentQuestion" class="form-label">
            Your question
        </label>

        <textarea
            id="bizPilotAgentQuestion"
            class="form-control mb-3"
            rows="3"
            maxlength="4000"
            placeholder="Recommend ways to reduce low-stock problems."
            required
        ></textarea>

        <button
            id="bizPilotAgentAskButton"
            type="submit"
            class="btn btn-primary"
        >
            Ask BizPilot AI Agent
        </button>
    </form>

    <div
        id="bizPilotAgentResponse"
        class="mt-3 p-3 rounded border"
        role="status"
        aria-live="polite"
        style="display:none; white-space:pre-wrap; overflow-wrap:anywhere;"
    ></div>
</div>
<!-- END BIZPILOT AI AGENT -->


    <!-- =====================================================
         MAIN STATS
    ====================================================== -->

    <div class="row g-4 mb-4">

        <div class="col-md-6 col-xl-3">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Total Revenue
                </div>

                <div class="stat-number text-primary">

                    <?= money($totalRevenue) ?>

                </div>

                <small class="text-muted">
                    Online + POS
                </small>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Net Profit
                </div>

                <div
                    class="stat-number
                    <?= $netProfit >= 0
                        ? 'profit-positive'
                        : 'profit-negative' ?>"
                >

                    <?= money($netProfit) ?>

                </div>

                <small class="text-muted">

                    Margin:
                    <?= number_format(
                        $profitMargin,
                        1
                    ) ?>%

                </small>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Online Orders
                </div>

                <div class="stat-number">

                    <?= $totalOnlineOrders ?>

                </div>

                <small class="text-muted">
                    Completed orders
                </small>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    POS Sales
                </div>

                <div class="stat-number">

                    <?= $totalPosSales ?>

                </div>

                <small class="text-muted">
                    Completed sales
                </small>

            </div>

        </div>

    </div>


    <!-- =====================================================
         SECOND STATS
    ====================================================== -->

    <div class="row g-4 mb-4">

        <div class="col-md-6 col-xl-3">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Products
                </div>

                <div class="stat-number">

                    <?= $totalProducts ?>

                </div>

                <small class="text-muted">
                    Active products
                </small>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Customers
                </div>

                <div class="stat-number">

                    <?= $totalCustomers ?>

                </div>

                <small class="text-muted">
                    Registered customers
                </small>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Pending Orders
                </div>

                <div class="stat-number text-warning">

                    <?= $pendingOrders ?>

                </div>

                <small class="text-muted">
                    Need attention
                </small>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Low Stock
                </div>

                <div class="stat-number text-danger">

                    <?= count($lowStockProducts) ?>

                </div>

                <small class="text-muted">
                    Products need restocking
                </small>

            </div>

        </div>

    </div>


    <!-- =====================================================
         PROFIT BREAKDOWN
    ====================================================== -->

    <div class="card dashboard-card p-4 mb-4">

        <h5 class="fw-bold mb-3">
            Business Financial Overview
        </h5>

        <div class="row g-3">

            <div class="col-md-6 col-lg-3">

                <div class="metric-box">

                    <small class="text-muted">
                        Online Revenue
                    </small>

                    <h5 class="fw-bold mt-1 mb-0">

                        <?= money($onlineRevenue) ?>

                    </h5>

                </div>

            </div>


            <div class="col-md-6 col-lg-3">

                <div class="metric-box">

                    <small class="text-muted">
                        POS Revenue
                    </small>

                    <h5 class="fw-bold mt-1 mb-0">

                        <?= money($posRevenue) ?>

                    </h5>

                </div>

            </div>


            <div class="col-md-6 col-lg-3">

                <div class="metric-box">

                    <small class="text-muted">
                        Product Cost
                    </small>

                    <h5 class="fw-bold mt-1 mb-0">

                        <?= money($totalProductCost) ?>

                    </h5>

                </div>

            </div>


            <div class="col-md-6 col-lg-3">

                <div class="metric-box">

                    <small class="text-muted">
                        Expenses
                    </small>

                    <h5 class="fw-bold mt-1 mb-0 text-danger">

                        <?= money($totalExpenses) ?>

                    </h5>

                </div>

            </div>

        </div>


        <div class="row g-3 mt-1">

            <div class="col-md-6">

                <div class="metric-box">

                    <small class="text-muted">
                        Gross Profit
                    </small>

                    <h5 class="fw-bold mt-1 mb-0">

                        <?= money($grossProfit) ?>

                    </h5>

                </div>

            </div>


            <div class="col-md-6">

                <div class="metric-box">

                    <small class="text-muted">
                        Net Profit
                    </small>

                    <h5
                        class="fw-bold mt-1 mb-0
                        <?= $netProfit >= 0
                            ? 'profit-positive'
                            : 'profit-negative' ?>"
                    >

                        <?= money($netProfit) ?>

                    </h5>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         QUICK ACTIONS
    ====================================================== -->

    <div class="card dashboard-card p-4 mb-4">

        <h5 class="fw-bold mb-3">
            Quick Actions
        </h5>

        <div class="row g-3">

            <div class="col-md-3">

                <a
                    href="add-product.php"
                    class="btn btn-primary w-100 quick-btn"
                >
                    + Add Product
                </a>

            </div>


            <div class="col-md-3">

                <a
                    href="products.php"
                    class="btn btn-outline-primary w-100 quick-btn"
                >
                    Manage Products
                </a>

            </div>


            <div class="col-md-3">

                <a
                    href="orders.php"
                    class="btn btn-outline-dark w-100 quick-btn"
                >
                    Manage Orders
                </a>

            </div>


            <div class="col-md-3">

                <a
                    href="expenses.php"
                    class="btn btn-outline-danger w-100 quick-btn"
                >
                    Manage Expenses
                </a>

            </div>

        </div>

    </div>


    <!-- =====================================================
         SALES OVERVIEW
    ====================================================== -->

    <div class="row g-4 mb-4">

        <div class="col-lg-8">

            <div class="card dashboard-card p-4">

                <div
                    class="d-flex justify-content-between align-items-center mb-3"
                >

                    <div>

                        <h5 class="fw-bold mb-1">
                            Sales Overview
                        </h5>

                        <small class="text-muted">
                            Online + POS • Last 6 months
                        </small>

                    </div>

                </div>

                <div style="height:330px;">

                    <canvas id="salesChart"></canvas>

                </div>

            </div>

        </div>


        <!-- TODAY / MONTH -->

        <div class="col-lg-4">

            <div class="card dashboard-card p-4 mb-4">

                <h5 class="fw-bold mb-3">
                    Today's Sales
                </h5>

                <div class="d-flex justify-content-between mb-3">

                    <span>
                        Online
                    </span>

                    <strong>
                        <?= money($todayOnline) ?>
                    </strong>

                </div>

                <div class="d-flex justify-content-between mb-3">

                    <span>
                        POS
                    </span>

                    <strong>
                        <?= money($todayPos) ?>
                    </strong>

                </div>

                <hr>

                <div class="d-flex justify-content-between">

                    <strong>
                        Total
                    </strong>

                    <strong class="text-primary">

                        <?= money($todayRevenue) ?>

                    </strong>

                </div>

            </div>


            <div class="card dashboard-card p-4">

                <h5 class="fw-bold mb-3">
                    This Month
                </h5>

                <div class="d-flex justify-content-between">

                    <span>
                        Revenue
                    </span>

                    <strong>

                        <?= money($monthRevenue) ?>

                    </strong>

                </div>

                <div class="d-flex justify-content-between mt-2">

                    <span>
                        Transactions
                    </span>

                    <strong>

                        <?= $monthTransactions ?>

                    </strong>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         LOW STOCK + TOP PRODUCTS
    ====================================================== -->

    <div class="row g-4 mb-4">

        <!-- LOW STOCK -->

        <div class="col-lg-5">

            <div class="card dashboard-card p-4">

                <div
                    class="d-flex justify-content-between align-items-center mb-3"
                >

                    <h5 class="fw-bold mb-0">
                        Low Stock Alerts
                    </h5>

                    <a
                        href="products.php"
                        class="btn btn-sm btn-outline-danger"
                    >
                        View All
                    </a>

                </div>


                <?php if (!empty($lowStockProducts)): ?>

                    <?php foreach (
                        array_slice(
                            $lowStockProducts,
                            0,
                            6
                        ) as $product
                    ): ?>

                        <div class="low-stock-item">

                            <div
                                class="d-flex justify-content-between"
                            >

                                <strong>

                                    <?= htmlspecialchars(
                                        $product["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </strong>

                                <span
                                    class="<?= (int) $product["stock"] <= 0
                                        ? 'stock-danger'
                                        : 'stock-warning' ?>"
                                >

                                    <?= (int) $product["stock"] ?>

                                    left

                                </span>

                            </div>

                            <small class="text-muted">

                                Low stock level:

                                <?= (int) $product["low_stock_level"] ?>

                            </small>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="text-center py-4">

                        <div class="text-success fw-bold">
                            ✓
                        </div>

                        <div class="text-muted">

                            All products have healthy stock levels.

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- TOP PRODUCTS -->

        <div class="col-lg-7">

            <div class="card dashboard-card p-4">

                <h5 class="fw-bold mb-3">
                    Top Selling Products
                </h5>

                <small class="text-muted d-block mb-3">
                    Online + POS sales
                </small>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>

                                <th>
                                    Product
                                </th>

                                <th>
                                    Sold
                                </th>

                                <th>
                                    Sales
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (!empty($topProducts)): ?>

                            <?php foreach (
                                $topProducts as $product
                            ): ?>

                                <tr>

                                    <td class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $product["product_name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= (int)
                                            $product["quantity_sold"] ?>

                                    </td>

                                    <td>

                                        <?= money(
                                            $product["sales_amount"]
                                        ) ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="3"
                                    class="text-center text-muted py-4"
                                >

                                    No completed sales yet.

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         RECENT ACTIVITY
    ====================================================== -->

    <div class="card dashboard-card p-4 mb-4">

        <div
            class="d-flex justify-content-between align-items-center mb-3"
        >

            <h5 class="fw-bold mb-0">
                Recent Business Activity
            </h5>

            <div>

                <a
                    href="orders.php"
                    class="btn btn-sm btn-outline-dark"
                >
                    Orders
                </a>

                <a
                    href="../staff/pos.php"
                    class="btn btn-sm btn-outline-success"
                >
                    POS
                </a>

            </div>

        </div>


        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

                    <tr>

                        <th>
                            Type
                        </th>

                        <th>
                            Reference
                        </th>

                        <th>
                            Customer / Staff
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Date
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($recentActivity)): ?>

                    <?php foreach (
                        $recentActivity as $activity
                    ): ?>

                        <tr>

                            <td>

                                <?php if (
                                    $activity["type"] === "Online Order"
                                ): ?>

                                    <span
                                        class="badge bg-primary"
                                    >
                                        🛒 Online
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="badge bg-success"
                                    >
                                        🧾 POS
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td class="fw-semibold">

                                <?= htmlspecialchars(
                                    $activity["reference"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $activity["person"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>


                            <td>

                                <?= money(
                                    $activity["amount"]
                                ) ?>

                            </td>


                            <td>

                                <?php

                                $activityStatus =
                                    $activity["status"];

                                $activityBadge =
                                    "bg-secondary";


                                if (
                                    $activityStatus === "completed"
                                ) {

                                    $activityBadge =
                                        "bg-success";

                                } elseif (
                                    $activityStatus === "cancelled"
                                ) {

                                    $activityBadge =
                                        "bg-danger";

                                } elseif (
                                    $activityStatus === "preparing"
                                ) {

                                    $activityBadge =
                                        "bg-warning text-dark";

                                } elseif (
                                    $activityStatus === "ready"
                                ) {

                                    $activityBadge =
                                        "bg-info text-dark";

                                } elseif (
                                    $activityStatus === "confirmed"
                                ) {

                                    $activityBadge =
                                        "bg-primary";

                                } elseif (
                                    $activityStatus === "pending"
                                ) {

                                    $activityBadge =
                                        "bg-warning text-dark";

                                } elseif (
                                    $activityStatus === "out_for_delivery"
                                ) {

                                    $activityBadge =
                                        "bg-info text-dark";
                                }

                                ?>

                                <span
                                    class="badge <?= $activityBadge ?>"
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            str_replace(
                                                "_",
                                                " ",
                                                $activityStatus
                                            )
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= date(
                                    "d M Y H:i",
                                    strtotime(
                                        $activity["date"]
                                    )
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="6"
                            class="text-center text-muted py-4"
                        >

                            No business activity found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <div class="text-center text-muted py-3">

        BizPilot AI
        • Intelligent Omnichannel Retail Management

    </div>


</div>


<script>
    
(function () {
    "use strict";

    const form = document.getElementById("bizPilotAgentForm");
    const question = document.getElementById("bizPilotAgentQuestion");
    const button = document.getElementById("bizPilotAgentAskButton");
    const output = document.getElementById("bizPilotAgentResponse");

    if (!form || !question || !button || !output) {
        console.error("BizPilot AI Agent form was not found.");
        return;
    }

    question.addEventListener("keydown", function (event) {
        if (
            event.key === "Enter" &&
            !event.shiftKey &&
            !event.isComposing
        ) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        const text = question.value.trim();

        if (!text || button.disabled) return;

        button.disabled = true;
        button.textContent = "Thinking...";
        output.style.display = "block";
        output.classList.remove("border-danger");
        output.textContent = "BizPilot AI Agent is processing...";

        try {
            const response = await fetch("../actions/ai_agent_test.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                credentials: "same-origin",
                cache: "no-store",
                body: JSON.stringify({ question: text })
            });

            const raw = await response.text();
            let data;

            try {
                data = JSON.parse(raw);
            } catch (_) {
                throw new Error(
                    "Invalid server response (HTTP " +
                    response.status +
                    "). Check the PHP error log."
                );
            }

            let message = data && typeof data.message === "string"
                ? data.message
                : "The server returned no answer.";

            if (data.proposal_id) {
                message += "\n\nProposal saved for review. ID: " +
                    data.proposal_id;
            } else if (data.proposal_created === false) {
                message += "\n\n" +
                    (data.proposal_message || "Proposal creation failed.");
            }

            if (!response.ok || data.success === false) {
                output.classList.add("border-danger");
                message = "Request failed (HTTP " +
                    response.status + ").\n\n" + message;
            }

            output.textContent = message;

        } catch (error) {
            console.error("BizPilot AI Agent error:", error);

            output.classList.add("border-danger");
            output.textContent =
                "AI Agent error: " + error.message +
                "\n\nYour question is still in the input box. Please check the server error.";

        } finally {
            button.disabled = false;
            button.textContent = "Ask BizPilot AI Agent";
        }
    });
})();


const chartLabels =
    <?= json_encode($chartLabels) ?>;

const chartOnline =
    <?= json_encode($chartOnline) ?>;

const chartPos =
    <?= json_encode($chartPos) ?>;

const chartData =
    <?= json_encode($chartData) ?>;


new Chart(
    document.getElementById("salesChart"),
    {
        type: "line",

        data: {

            labels: chartLabels,

            datasets: [

                {
                    label: "Total Sales",
                    data: chartData,
                    tension: 0.35,
                    fill: true
                },

                {
                    label: "Online",
                    data: chartOnline,
                    tension: 0.35,
                    fill: false
                },

                {
                    label: "POS",
                    data: chartPos,
                    tension: 0.35,
                    fill: false
                }

            ]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {

                legend: {
                    display: true
                }

            },

            scales: {

                y: {

                    beginAtZero: true,

                    ticks: {

                        callback: function(value) {

                            return "Rs. " +
                                Number(value)
                                .toLocaleString();

                        }

                    }

                }

            }

        }

    }
);

</script>


</body>

</html>