<?php

require_once "../config/admin_auth.php";
requireAdmin();

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| SUMMARY - ONLINE + POS
|--------------------------------------------------------------------------
*/

/*
|---------------------------------------------------------------------------
| ONLINE REVENUE
|--------------------------------------------------------------------------
*/

$onlineRevenue = (float) $pdo->query("
    SELECT COALESCE(SUM(total), 0)
    FROM orders
    WHERE order_status = 'completed'
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| POS REVENUE
|--------------------------------------------------------------------------
*/

$posRevenue = (float) $pdo->query("
    SELECT COALESCE(SUM(total), 0)
    FROM pos_sales
    WHERE sale_status = 'completed'
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| TOTAL BUSINESS REVENUE
|--------------------------------------------------------------------------
*/

$totalRevenue = $onlineRevenue + $posRevenue;

/*
|--------------------------------------------------------------------------
| ONLINE COMPLETED ORDERS
|--------------------------------------------------------------------------
*/

$onlineOrders = (int) $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE order_status = 'completed'
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| POS COMPLETED SALES
|--------------------------------------------------------------------------
*/

$posOrders = (int) $pdo->query("
    SELECT COUNT(*)
    FROM pos_sales
    WHERE sale_status = 'completed'
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| TOTAL COMPLETED TRANSACTIONS
|--------------------------------------------------------------------------
*/

$totalOrders = $onlineOrders + $posOrders;

/*
|--------------------------------------------------------------------------
| ONLINE PRODUCTS SOLD
|--------------------------------------------------------------------------
*/

$onlineProductsSold = (int) $pdo->query("
    SELECT COALESCE(SUM(oi.quantity), 0)
    FROM order_items oi
    INNER JOIN orders o
        ON oi.order_id = o.id
    WHERE o.order_status = 'completed'
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| POS PRODUCTS SOLD
|--------------------------------------------------------------------------
*/

$posProductsSold = (int) $pdo->query("
    SELECT COALESCE(SUM(quantity), 0)
    FROM pos_sale_items psi
    INNER JOIN pos_sales ps
        ON psi.sale_id = ps.id
    WHERE ps.sale_status = 'completed'
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| TOTAL PRODUCTS SOLD
|--------------------------------------------------------------------------
*/

$totalProductsSold = $onlineProductsSold + $posProductsSold;

/*
|--------------------------------------------------------------------------
| TODAY'S ONLINE REVENUE
|--------------------------------------------------------------------------
*/

$todayOnlineRevenue = (float) $pdo->query("
    SELECT COALESCE(SUM(total), 0)
    FROM orders
    WHERE order_status = 'completed'
    AND DATE(created_at) = CURDATE()
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| TODAY'S POS REVENUE
|--------------------------------------------------------------------------
*/

$todayPosRevenue = (float) $pdo->query("
    SELECT COALESCE(SUM(total), 0)
    FROM pos_sales
    WHERE sale_status = 'completed'
    AND DATE(created_at) = CURDATE()
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| TODAY'S TOTAL REVENUE
|--------------------------------------------------------------------------
*/

$todayRevenue = $todayOnlineRevenue + $todayPosRevenue;

/*
|--------------------------------------------------------------------------
| THIS MONTH ONLINE REVENUE
|--------------------------------------------------------------------------
*/

$monthOnlineRevenue = (float) $pdo->query("
    SELECT COALESCE(SUM(total), 0)
    FROM orders
    WHERE order_status = 'completed'
    AND YEAR(created_at) = YEAR(CURDATE())
    AND MONTH(created_at) = MONTH(CURDATE())
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| THIS MONTH POS REVENUE
|--------------------------------------------------------------------------
*/

$monthPosRevenue = (float) $pdo->query("
    SELECT COALESCE(SUM(total), 0)
    FROM pos_sales
    WHERE sale_status = 'completed'
    AND YEAR(created_at) = YEAR(CURDATE())
    AND MONTH(created_at) = MONTH(CURDATE())
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| THIS MONTH TOTAL REVENUE
|--------------------------------------------------------------------------
*/

$monthRevenue = $monthOnlineRevenue + $monthPosRevenue;

/*
|--------------------------------------------------------------------------
| LOW STOCK
|--------------------------------------------------------------------------
*/

$lowStockCount = (int) $pdo->query("
    SELECT COUNT(*)
    FROM products
    WHERE status = 'active'
    AND stock <= low_stock_level
")->fetchColumn();


/*
|--------------------------------------------------------------------------
| TODAY'S TRANSACTIONS
|--------------------------------------------------------------------------
*/

$todayOnlineOrders = (int) $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE order_status = 'completed'
    AND DATE(created_at) = CURDATE()
")->fetchColumn();

$todayPosOrders = (int) $pdo->query("
    SELECT COUNT(*)
    FROM pos_sales
    WHERE sale_status = 'completed'
    AND DATE(created_at) = CURDATE()
")->fetchColumn();

$todayOrders = $todayOnlineOrders + $todayPosOrders;


/*
|--------------------------------------------------------------------------
| MONTHLY SALES - LAST 6 MONTHS
|--------------------------------------------------------------------------
|
| Combine ONLINE orders and POS sales by month.
|
*/

$monthlyStmt = $pdo->query("
    SELECT
        month,
        SUM(revenue) AS revenue
    FROM
    (
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            COALESCE(SUM(total), 0) AS revenue
        FROM orders
        WHERE
            order_status = 'completed'
            AND created_at >= DATE_SUB(
                DATE_FORMAT(CURDATE(), '%Y-%m-01'),
                INTERVAL 5 MONTH
            )
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')

        UNION ALL

        SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            COALESCE(SUM(total), 0) AS revenue
        FROM pos_sales
        WHERE
            sale_status = 'completed'
            AND created_at >= DATE_SUB(
                DATE_FORMAT(CURDATE(), '%Y-%m-01'),
                INTERVAL 5 MONTH
            )
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ) AS combined_sales
    GROUP BY month
    ORDER BY month ASC
");

$monthlySales = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| TOP SELLING PRODUCTS
|--------------------------------------------------------------------------
|
| Combine online order items and POS sale items.
|
*/

$topProductsStmt = $pdo->query("
    SELECT
        product_name,
        SUM(total_quantity) AS total_quantity,
        SUM(total_sales) AS total_sales
    FROM
    (
        SELECT
            oi.product_id,
            oi.product_name,
            SUM(oi.quantity) AS total_quantity,
            SUM(oi.subtotal) AS total_sales
        FROM order_items oi
        INNER JOIN orders o
            ON oi.order_id = o.id
        WHERE o.order_status = 'completed'
        GROUP BY
            oi.product_id,
            oi.product_name

        UNION ALL

        SELECT
            psi.product_id,
            psi.product_name,
            SUM(psi.quantity) AS total_quantity,
            SUM(psi.subtotal) AS total_sales
        FROM pos_sale_items psi
        INNER JOIN pos_sales ps
            ON psi.sale_id = ps.id
        WHERE ps.sale_status = 'completed'
        GROUP BY
            psi.product_id,
            psi.product_name
    ) AS combined_products
    GROUP BY product_name
    ORDER BY total_quantity DESC
    LIMIT 10
");

$topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| RECENT COMPLETED SALES
|--------------------------------------------------------------------------
|
| Combine online orders and POS sales.
|
*/

$recentStmt = $pdo->query("
    SELECT
        sale_reference,
        customer_name,
        sale_type,
        total,
        created_at
    FROM
    (
        SELECT
            o.order_number AS sale_reference,
            u.full_name AS customer_name,
            CASE
                WHEN o.fulfillment_type = 'pickup'
                THEN 'Store Pickup'
                ELSE 'Home Delivery'
            END AS sale_type,
            o.total,
            o.created_at
        FROM orders o
        INNER JOIN users u
            ON o.user_id = u.id
        WHERE o.order_status = 'completed'

        UNION ALL

        SELECT
            ps.invoice_number AS sale_reference,
            CONCAT('POS - ', u.full_name) AS customer_name,
            'POS Sale' AS sale_type,
            ps.total,
            ps.created_at
        FROM pos_sales ps
        INNER JOIN users u
            ON ps.staff_id = u.id
        WHERE ps.sale_status = 'completed'
    ) AS combined_sales
    ORDER BY created_at DESC
    LIMIT 10
");

$recentOrders = $recentStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| PAYMENT SUMMARY
|--------------------------------------------------------------------------
|
| Online payment methods and POS payment methods are different.
| Normalize them into a common report.
|
*/

$paymentStmt = $pdo->query("
    SELECT
        payment_method,
        SUM(order_count) AS order_count,
        SUM(total_amount) AS total_amount
    FROM
    (
        SELECT
            CASE
                WHEN payment_method = 'cash_on_delivery'
                    THEN 'Cash on Delivery'
                WHEN payment_method = 'pay_at_store'
                    THEN 'Pay at Store'
                ELSE payment_method
            END AS payment_method,
            COUNT(*) AS order_count,
            COALESCE(SUM(total), 0) AS total_amount
        FROM orders
        WHERE order_status = 'completed'
        GROUP BY payment_method

        UNION ALL

        SELECT
            CASE
                WHEN payment_method = 'cash'
                    THEN 'Cash'
                WHEN payment_method = 'card'
                    THEN 'Card'
                WHEN payment_method = 'other'
                    THEN 'Other'
                ELSE payment_method
            END AS payment_method,
            COUNT(*) AS order_count,
            COALESCE(SUM(total), 0) AS total_amount
        FROM pos_sales
        WHERE sale_status = 'completed'
        GROUP BY payment_method
    ) AS combined_payments
    GROUP BY payment_method
    ORDER BY total_amount DESC
");

$paymentSummary = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Reports - BizPilot AI</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body class="bg-light">


<!-- NAVBAR -->

<nav class="navbar navbar-expand-lg bg-dark navbar-dark">

    <div class="container-fluid">

        <a
            class="navbar-brand fw-bold"
            href="index.php"
        >
            BizPilot AI
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#adminNav"
        >

            <span class="navbar-toggler-icon"></span>

        </button>

        <div
            class="collapse navbar-collapse"
            id="adminNav"
        >

            <ul class="navbar-nav ms-auto">

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="index.php"
                    >
                        Dashboard
                    </a>

                </li>

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="products.php"
                    >
                        Products
                    </a>

                </li>

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="categories.php"
                    >
                        Categories
                    </a>

                </li>

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="orders.php"
                    >
                        Orders
                    </a>

                </li>

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="customers.php"
                    >
                        Customers
                    </a>

                </li>

                <li class="nav-item">

                    <a
                        class="nav-link active"
                        href="reports.php"
                    >
                        Reports
                    </a>

                </li>

            </ul>

        </div>

    </div>

</nav>


<div class="container-fluid py-4">

    <?php require_once "../config/back-button.php"; ?>


    <!-- HEADER -->

    <div
        class="d-flex justify-content-between align-items-center mb-4"
    >

        <div>

            <h2 class="fw-bold mb-1">
                Business Reports
            </h2>

            <p class="text-muted mb-0">
                Monitor sales and business performance
            </p>

        </div>

        <a
            href="index.php"
            class="btn btn-outline-dark"
        >
            ← Dashboard
        </a>

    </div>


    <!-- SUMMARY CARDS -->

    <div class="row g-4 mb-4">


        <!-- TOTAL REVENUE -->

        <div class="col-md-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <p class="text-muted mb-2">
                        Total Revenue
                    </p>

                    <h3 class="fw-bold mb-0">

                        Rs.
                        <?= number_format(
                            $totalRevenue,
                            2
                        ) ?>

                    </h3>

                    <small class="text-muted">
                        Online + POS
                    </small>

                </div>

            </div>

        </div>


        <!-- COMPLETED TRANSACTIONS -->

        <div class="col-md-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <p class="text-muted mb-2">
                        Completed Transactions
                    </p>

                    <h3 class="fw-bold mb-0">

                        <?= number_format(
                            $totalOrders
                        ) ?>

                    </h3>

                    <small class="text-muted">
                        Online + POS
                    </small>

                </div>

            </div>

        </div>


        <!-- PRODUCTS SOLD -->

        <div class="col-md-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <p class="text-muted mb-2">
                        Products Sold
                    </p>

                    <h3 class="fw-bold mb-0">

                        <?= number_format(
                            $totalProductsSold
                        ) ?>

                    </h3>

                    <small class="text-muted">
                        Online + POS
                    </small>

                </div>

            </div>

        </div>


        <!-- LOW STOCK -->

        <div class="col-md-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <p class="text-muted mb-2">
                        Low Stock Items
                    </p>

                    <h3 class="fw-bold mb-0 text-danger">

                        <?= number_format(
                            $lowStockCount
                        ) ?>

                    </h3>

                    <small class="text-muted">
                        Active products
                    </small>

                </div>

            </div>

        </div>

    </div>


    <!-- SECOND ROW -->

    <div class="row g-4 mb-4">


        <!-- TODAY'S REVENUE -->

        <div class="col-md-4">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <p class="text-muted mb-2">
                        Today's Revenue
                    </p>

                    <h4 class="fw-bold">

                        Rs.
                        <?= number_format(
                            $todayRevenue,
                            2
                        ) ?>

                    </h4>

                    <small class="text-muted">

                        <?= number_format($todayOrders) ?>
                        transaction(s) today

                    </small>

                </div>

            </div>

        </div>


        <!-- MONTH REVENUE -->

        <div class="col-md-4">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <p class="text-muted mb-2">
                        This Month Revenue
                    </p>

                    <h4 class="fw-bold">

                        Rs.
                        <?= number_format(
                            $monthRevenue,
                            2
                        ) ?>

                    </h4>

                    <small class="text-muted">
                        Online + POS
                    </small>

                </div>

            </div>

        </div>


        <!-- AVERAGE TRANSACTION -->

        <div class="col-md-4">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <p class="text-muted mb-2">
                        Average Transaction Value
                    </p>

                    <h4 class="fw-bold">

                        Rs.
                        <?= number_format(
                            $totalOrders > 0
                                ? $totalRevenue / $totalOrders
                                : 0,
                            2
                        ) ?>

                    </h4>

                    <small class="text-muted">
                        Based on completed transactions
                    </small>

                </div>

            </div>

        </div>

    </div>


    <!-- SALES CHART -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <h5 class="fw-bold mb-4">
                Sales - Last 6 Months
            </h5>

            <div style="height: 320px;">

                <canvas id="salesChart"></canvas>

            </div>

        </div>

    </div>


    <div class="row g-4">


        <!-- TOP PRODUCTS -->

        <div class="col-lg-7">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

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
                                        Quantity
                                    </th>

                                    <th>
                                        Sales
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php if (empty($topProducts)): ?>

                                <tr>

                                    <td
                                        colspan="3"
                                        class="text-center text-muted py-4"
                                    >
                                        No completed sales yet.
                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($topProducts as $product): ?>

                                    <tr>

                                        <td>

                                            <?= htmlspecialchars(
                                                $product["product_name"]
                                            ) ?>

                                        </td>

                                        <td>

                                            <span class="badge bg-primary">

                                                <?= (int)
                                                    $product["total_quantity"] ?>

                                            </span>

                                        </td>

                                        <td>

                                            Rs.
                                            <?= number_format(
                                                (float)
                                                $product["total_sales"],
                                                2
                                            ) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>


        <!-- PAYMENT SUMMARY -->

        <div class="col-lg-5">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <h5 class="fw-bold mb-3">
                        Payment Summary
                    </h5>

                    <small class="text-muted d-block mb-2">
                        Online + POS completed sales
                    </small>

                    <?php if (empty($paymentSummary)): ?>

                        <p class="text-muted">
                            No completed payments yet.
                        </p>

                    <?php else: ?>

                        <?php foreach ($paymentSummary as $payment): ?>

                            <div
                                class="d-flex justify-content-between border-bottom py-3"
                            >

                                <div>

                                    <div class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $payment["payment_method"]
                                        ) ?>

                                    </div>

                                    <small class="text-muted">

                                        <?= (int)
                                            $payment["order_count"] ?>

                                        transaction(s)

                                    </small>

                                </div>

                                <strong>

                                    Rs.
                                    <?= number_format(
                                        (float)
                                        $payment["total_amount"],
                                        2
                                    ) ?>

                                </strong>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


    <!-- RECENT SALES -->

    <div class="card border-0 shadow-sm mt-4">

        <div class="card-body">

            <h5 class="fw-bold mb-3">
                Recent Completed Sales
            </h5>

            <small class="text-muted d-block mb-3">
                Online orders and POS sales
            </small>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>
                                Reference
                            </th>

                            <th>
                                Customer / Staff
                            </th>

                            <th>
                                Type
                            </th>

                            <th>
                                Total
                            </th>

                            <th>
                                Date
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($recentOrders)): ?>

                        <tr>

                            <td
                                colspan="5"
                                class="text-center text-muted py-4"
                            >
                                No completed sales yet.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($recentOrders as $sale): ?>

                            <tr>

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $sale["sale_reference"]
                                        ) ?>

                                    </strong>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $sale["customer_name"]
                                    ) ?>

                                </td>

                                <td>

                                    <?php if (
                                        $sale["sale_type"] === "POS Sale"
                                    ): ?>

                                        <span class="badge bg-dark">
                                            POS Sale
                                        </span>

                                    <?php elseif (
                                        $sale["sale_type"] ===
                                        "Store Pickup"
                                    ): ?>

                                        <span class="badge bg-primary">
                                            Store Pickup
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-success">
                                            Home Delivery
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <strong>

                                        Rs.
                                        <?= number_format(
                                            (float) $sale["total"],
                                            2
                                        ) ?>

                                    </strong>

                                </td>

                                <td>

                                    <?= date(
                                        "Y-m-d H:i",
                                        strtotime(
                                            $sale["created_at"]
                                        )
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<script>

const monthlyData = <?= json_encode(
    $monthlySales,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_AMP |
    JSON_HEX_QUOT
) ?>;

const labels = monthlyData.map(
    item => item.month
);

const revenue = monthlyData.map(
    item => Number(item.revenue)
);

new Chart(
    document.getElementById("salesChart"),
    {
        type: "line",

        data: {

            labels: labels,

            datasets: [
                {
                    label: "Revenue (Rs.)",

                    data: revenue,

                    tension: 0.3,

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

                    beginAtZero: true

                }

            }

        }

    }
);

</script>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>