<?php

require_once "../config/admin_auth.php";
requireAdmin();

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| DATE SETTINGS
|--------------------------------------------------------------------------
*/

$today = date("Y-m-d");

$currentMonthStart = date("Y-m-01");
$nextMonthStart = date(
    "Y-m-01",
    strtotime("+1 month")
);


/*
|--------------------------------------------------------------------------
| TOTAL ONLINE REVENUE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COALESCE(SUM(o.total), 0)
    FROM orders o
    WHERE o.order_status = 'completed'
");

$onlineRevenue = (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TOTAL POS REVENUE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COALESCE(SUM(ps.total), 0)
    FROM pos_sales ps
    WHERE ps.sale_status = 'completed'
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
| IMPORTANT:
| order_items does not currently store historical cost_price.
| Therefore current products.cost_price is used for online historical
| cost calculation.
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

$onlineProductCost = (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| POS PRODUCT COST
|--------------------------------------------------------------------------
|
| POS sale items already store historical cost_price.
|
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

$posProductCost = (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TOTAL PRODUCT COST
|--------------------------------------------------------------------------
*/

$totalProductCost =
    $onlineProductCost +
    $posProductCost;


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
| TOTAL BUSINESS EXPENSES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COALESCE(SUM(e.amount), 0)
    FROM expenses e
");

$totalExpenses = (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| NET PROFIT / LOSS
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

$profitMargin = 0;

if ($totalRevenue > 0) {

    $profitMargin =
        ($netProfit / $totalRevenue) * 100;
}


/*
|--------------------------------------------------------------------------
| TODAY - ONLINE REVENUE
|--------------------------------------------------------------------------
*/

$tomorrow = date(
    "Y-m-d",
    strtotime("+1 day")
);

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(o.total), 0)
    FROM orders o
    WHERE o.order_status = 'completed'
    AND o.created_at >= ?
    AND o.created_at < ?
");

$stmt->execute([
    $today,
    $tomorrow
]);

$todayOnlineRevenue =
    (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TODAY - POS REVENUE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(ps.total), 0)
    FROM pos_sales ps
    WHERE ps.sale_status = 'completed'
    AND ps.created_at >= ?
    AND ps.created_at < ?
");

$stmt->execute([
    $today,
    $tomorrow
]);

$todayPosRevenue =
    (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TODAY TOTAL REVENUE
|--------------------------------------------------------------------------
*/

$todayRevenue =
    $todayOnlineRevenue +
    $todayPosRevenue;


/*
|--------------------------------------------------------------------------
| THIS MONTH - ONLINE REVENUE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(o.total), 0)
    FROM orders o
    WHERE o.order_status = 'completed'
    AND o.created_at >= ?
    AND o.created_at < ?
");

$stmt->execute([
    $currentMonthStart,
    $nextMonthStart
]);

$monthOnlineRevenue =
    (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| THIS MONTH - POS REVENUE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(ps.total), 0)
    FROM pos_sales ps
    WHERE ps.sale_status = 'completed'
    AND ps.created_at >= ?
    AND ps.created_at < ?
");

$stmt->execute([
    $currentMonthStart,
    $nextMonthStart
]);

$monthPosRevenue =
    (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| THIS MONTH TOTAL REVENUE
|--------------------------------------------------------------------------
*/

$monthRevenue =
    $monthOnlineRevenue +
    $monthPosRevenue;


/*
|--------------------------------------------------------------------------
| THIS MONTH - ONLINE PRODUCT COST
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
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
    AND o.created_at >= ?
    AND o.created_at < ?
");

$stmt->execute([
    $currentMonthStart,
    $nextMonthStart
]);

$monthOnlineProductCost =
    (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| THIS MONTH - POS PRODUCT COST
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
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
    AND ps.created_at >= ?
    AND ps.created_at < ?
");

$stmt->execute([
    $currentMonthStart,
    $nextMonthStart
]);

$monthPosProductCost =
    (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| THIS MONTH TOTAL PRODUCT COST
|--------------------------------------------------------------------------
*/

$monthProductCost =
    $monthOnlineProductCost +
    $monthPosProductCost;


/*
|--------------------------------------------------------------------------
| THIS MONTH EXPENSES
|--------------------------------------------------------------------------
|
| expense_date is DATE, therefore BETWEEN is safe here.
|
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(e.amount), 0)
    FROM expenses e
    WHERE e.expense_date BETWEEN ? AND ?
");

$stmt->execute([
    $currentMonthStart,
    date("Y-m-t")
]);

$monthExpenses =
    (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| THIS MONTH GROSS PROFIT
|--------------------------------------------------------------------------
*/

$monthGrossProfit =
    $monthRevenue -
    $monthProductCost;


/*
|--------------------------------------------------------------------------
| THIS MONTH NET PROFIT
|--------------------------------------------------------------------------
*/

$monthNetProfit =
    $monthGrossProfit -
    $monthExpenses;


/*
|--------------------------------------------------------------------------
| TRANSACTION COUNT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM orders o
    WHERE o.order_status = 'completed'
");

$onlineTransactionCount =
    (int) $stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM pos_sales ps
    WHERE ps.sale_status = 'completed'
");

$posTransactionCount =
    (int) $stmt->fetchColumn();


$totalTransactions =
    $onlineTransactionCount +
    $posTransactionCount;


/*
|--------------------------------------------------------------------------
| AVERAGE TRANSACTION
|--------------------------------------------------------------------------
*/

$averageTransaction = 0;

if ($totalTransactions > 0) {

    $averageTransaction =
        $totalRevenue /
        $totalTransactions;
}


/*
|--------------------------------------------------------------------------
| SIX MONTH CHART
|--------------------------------------------------------------------------
|
| Revenue + Expenses for the last 6 months.
|
*/

$chartLabels = [];
$chartRevenue = [];
$chartExpenses = [];

for ($i = 5; $i >= 0; $i--) {

    $timestamp =
        strtotime("-$i months");

    $startDate =
        date("Y-m-01", $timestamp);

    $nextMonthDate =
        date(
            "Y-m-01",
            strtotime(
                "+1 month",
                $timestamp
            )
        );

    $label =
        date("M Y", $timestamp);


    /*
    |--------------------------------------------------------------------------
    | ONLINE MONTH REVENUE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(o.total), 0)
        FROM orders o
        WHERE o.order_status = 'completed'
        AND o.created_at >= ?
        AND o.created_at < ?
    ");

    $stmt->execute([
        $startDate,
        $nextMonthDate
    ]);

    $onlineMonth =
        (float) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | POS MONTH REVENUE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ps.total), 0)
        FROM pos_sales ps
        WHERE ps.sale_status = 'completed'
        AND ps.created_at >= ?
        AND ps.created_at < ?
    ");

    $stmt->execute([
        $startDate,
        $nextMonthDate
    ]);

    $posMonth =
        (float) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | MONTH EXPENSES
    |--------------------------------------------------------------------------
    */

    $expenseStart =
        $startDate;

    $expenseEnd =
        date(
            "Y-m-t",
            $timestamp
        );

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(e.amount), 0)
        FROM expenses e
        WHERE e.expense_date BETWEEN ? AND ?
    ");

    $stmt->execute([
        $expenseStart,
        $expenseEnd
    ]);

    $expenseMonth =
        (float) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | CHART DATA
    |--------------------------------------------------------------------------
    */

    $chartLabels[] =
        $label;

    $chartRevenue[] =
        $onlineMonth +
        $posMonth;

    $chartExpenses[] =
        $expenseMonth;
}


/*
|--------------------------------------------------------------------------
| TOP SELLING PRODUCTS - ONLINE + POS
|--------------------------------------------------------------------------
|
| Combine both sales channels.
|
*/

$stmt = $pdo->query("
    SELECT
        product_id,
        product_name,
        SUM(total_quantity) AS total_quantity,
        SUM(total_sales) AS total_sales
    FROM
    (
        /*
        |--------------------------------------------------------------------------
        | ONLINE SALES
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | POS SALES
        |--------------------------------------------------------------------------
        */

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

    GROUP BY
        product_id,
        product_name

    ORDER BY
        total_quantity DESC

    LIMIT 10
");

$topProducts =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| RECENT EXPENSES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        e.id,
        e.title,
        e.description,
        e.amount,
        e.expense_date,
        e.created_at
    FROM expenses e
    ORDER BY
        e.expense_date DESC,
        e.id DESC
    LIMIT 10
");

$recentExpenses =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| RECENT POS SALES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        ps.invoice_number,
        ps.subtotal,
        ps.discount,
        ps.total,
        ps.payment_method,
        ps.created_at
    FROM pos_sales ps
    WHERE ps.sale_status = 'completed'
    ORDER BY
        ps.created_at DESC
    LIMIT 10
");

$recentPosSales =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| MONEY FORMAT
|--------------------------------------------------------------------------
*/

function money($amount)
{
    return "Rs. " .
        number_format(
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
        Profit & Loss - BizPilot AI
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

        .page-title {
            font-weight: 700;
        }

        .stat-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,.06);
            height: 100%;
        }

        .stat-label {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 700;
        }

        .profit-positive {
            color: #198754;
        }

        .profit-negative {
            color: #dc3545;
        }

        .section-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,.06);
        }

        .table th {
            white-space: nowrap;
        }

        .small-muted {
            color: #6c757d;
            font-size: 13px;
        }

    </style>

</head>


<body>

<div class="container-fluid py-4">

    <?php require_once "../config/back-button.php"; ?>


    <!-- HEADER -->

    <div
        class="d-flex justify-content-between align-items-center mb-4"
    >

        <div>

            <h2 class="page-title mb-1">
                Profit & Loss
            </h2>

            <p class="text-muted mb-0">
                Complete financial overview of BizPilot AI
            </p>

        </div>


        <a
            href="index.php"
            class="btn btn-dark"
        >
            ← Admin Dashboard
        </a>

    </div>


    <!-- MAIN FINANCIAL CARDS -->

    <div class="row g-4 mb-4">


        <!-- TOTAL REVENUE -->

        <div class="col-md-6 col-xl-3">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Total Revenue
                </div>

                <div class="stat-value text-primary">
                    <?= money($totalRevenue) ?>
                </div>

                <div class="small-muted">
                    Online + POS
                </div>

            </div>

        </div>


        <!-- PRODUCT COST -->

        <div class="col-md-6 col-xl-3">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Product Cost
                </div>

                <div class="stat-value text-warning">
                    <?= money($totalProductCost) ?>
                </div>

                <div class="small-muted">
                    Cost of goods sold
                </div>

            </div>

        </div>


        <!-- GROSS PROFIT -->

        <div class="col-md-6 col-xl-3">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Gross Profit
                </div>

                <div
                    class="stat-value
                    <?= $grossProfit >= 0
                        ? 'profit-positive'
                        : 'profit-negative' ?>"
                >
                    <?= money($grossProfit) ?>
                </div>

                <div class="small-muted">
                    Revenue − Product Cost
                </div>

            </div>

        </div>


        <!-- NET PROFIT -->

        <div class="col-md-6 col-xl-3">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Net Profit / Loss
                </div>

                <div
                    class="stat-value
                    <?= $netProfit >= 0
                        ? 'profit-positive'
                        : 'profit-negative' ?>"
                >
                    <?= money($netProfit) ?>
                </div>

                <div class="small-muted">
                    After product cost and expenses
                </div>

            </div>

        </div>

    </div>


    <!-- SECONDARY CARDS -->

    <div class="row g-4 mb-4">


        <!-- TOTAL EXPENSES -->

        <div class="col-md-4">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Total Expenses
                </div>

                <div class="stat-value text-danger">
                    <?= money($totalExpenses) ?>
                </div>

            </div>

        </div>


        <!-- PROFIT MARGIN -->

        <div class="col-md-4">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Profit Margin
                </div>

                <div
                    class="stat-value
                    <?= $profitMargin >= 0
                        ? 'profit-positive'
                        : 'profit-negative' ?>"
                >
                    <?= number_format(
                        $profitMargin,
                        2
                    ) ?>%
                </div>

                <div class="small-muted">
                    Net Profit ÷ Total Revenue
                </div>

            </div>

        </div>


        <!-- AVERAGE TRANSACTION -->

        <div class="col-md-4">

            <div class="card stat-card p-4">

                <div class="stat-label">
                    Average Transaction
                </div>

                <div class="stat-value text-info">
                    <?= money($averageTransaction) ?>
                </div>

                <div class="small-muted">
                    Online orders + POS sales
                </div>

            </div>

        </div>

    </div>


    <!-- TODAY AND MONTH -->

    <div class="row g-4 mb-4">


        <!-- TODAY -->

        <div class="col-md-6">

            <div class="card section-card p-4">

                <h5 class="fw-bold">
                    Today's Performance
                </h5>

                <hr>


                <div class="d-flex justify-content-between mb-3">

                    <span>
                        Online Revenue
                    </span>

                    <strong>
                        <?= money(
                            $todayOnlineRevenue
                        ) ?>
                    </strong>

                </div>


                <div class="d-flex justify-content-between mb-3">

                    <span>
                        POS Revenue
                    </span>

                    <strong>
                        <?= money(
                            $todayPosRevenue
                        ) ?>
                    </strong>

                </div>


                <div class="d-flex justify-content-between">

                    <strong>
                        Total Today
                    </strong>

                    <strong class="text-primary">
                        <?= money(
                            $todayRevenue
                        ) ?>
                    </strong>

                </div>

            </div>

        </div>


        <!-- THIS MONTH -->

        <div class="col-md-6">

            <div class="card section-card p-4">

                <h5 class="fw-bold">
                    This Month
                </h5>

                <hr>


                <div class="d-flex justify-content-between mb-3">

                    <span>
                        Revenue
                    </span>

                    <strong>
                        <?= money(
                            $monthRevenue
                        ) ?>
                    </strong>

                </div>


                <div class="d-flex justify-content-between mb-3">

                    <span>
                        Product Cost
                    </span>

                    <strong class="text-warning">
                        <?= money(
                            $monthProductCost
                        ) ?>
                    </strong>

                </div>


                <div class="d-flex justify-content-between mb-3">

                    <span>
                        Gross Profit
                    </span>

                    <strong
                        class="<?= $monthGrossProfit >= 0
                            ? 'text-success'
                            : 'text-danger' ?>"
                    >
                        <?= money(
                            $monthGrossProfit
                        ) ?>
                    </strong>

                </div>


                <div class="d-flex justify-content-between mb-3">

                    <span>
                        Business Expenses
                    </span>

                    <strong class="text-danger">
                        <?= money(
                            $monthExpenses
                        ) ?>
                    </strong>

                </div>


                <hr>


                <div class="d-flex justify-content-between">

                    <strong>
                        Monthly Net Profit / Loss
                    </strong>

                    <strong
                        class="<?= $monthNetProfit >= 0
                            ? 'text-success'
                            : 'text-danger' ?>"
                    >
                        <?= money(
                            $monthNetProfit
                        ) ?>
                    </strong>

                </div>

            </div>

        </div>

    </div>


    <!-- CHART -->

    <div class="card section-card p-4 mb-4">

        <h5 class="fw-bold mb-3">
            Revenue vs Expenses - Last 6 Months
        </h5>

        <div style="height:350px;">

            <canvas id="profitChart"></canvas>

        </div>

    </div>


    <!-- TOP PRODUCTS -->

    <div class="card section-card p-4 mb-4">

        <h5 class="fw-bold mb-1">
            Top Selling Products
        </h5>

        <p class="small-muted mb-3">
            Online + POS completed sales
        </p>


        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

                    <tr>

                        <th>
                            Product
                        </th>

                        <th>
                            Quantity Sold
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
                                    $product["product_name"]
                                ) ?>

                            </td>


                            <td>

                                <?= (int)
                                    $product[
                                        "total_quantity"
                                    ] ?>

                            </td>


                            <td>

                                <?= money(
                                    $product["total_sales"]
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


    <!-- RECENT EXPENSES -->

    <div class="card section-card p-4 mb-4">

        <h5 class="fw-bold mb-3">
            Recent Expenses
        </h5>


        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

                    <tr>

                        <th>
                            Title
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Description
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!empty($recentExpenses)): ?>

                    <?php foreach (
                        $recentExpenses as $expense
                    ): ?>

                        <tr>

                            <td class="fw-semibold">

                                <?= htmlspecialchars(
                                    $expense["title"]
                                ) ?>

                            </td>


                            <td class="text-danger fw-semibold">

                                <?= money(
                                    $expense["amount"]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $expense[
                                        "expense_date"
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $expense[
                                        "description"
                                    ] ?? "-"
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="4"
                            class="text-center text-muted py-4"
                        >
                            No expenses recorded yet.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- RECENT POS SALES -->

    <div class="card section-card p-4 mb-4">

        <h5 class="fw-bold mb-3">
            Recent POS Sales
        </h5>


        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

                    <tr>

                        <th>
                            Invoice
                        </th>

                        <th>
                            Subtotal
                        </th>

                        <th>
                            Discount
                        </th>

                        <th>
                            Total
                        </th>

                        <th>
                            Payment
                        </th>

                        <th>
                            Date
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!empty($recentPosSales)): ?>

                    <?php foreach (
                        $recentPosSales as $sale
                    ): ?>

                        <tr>

                            <td class="fw-semibold">

                                <?= htmlspecialchars(
                                    $sale[
                                        "invoice_number"
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <?= money(
                                    $sale["subtotal"]
                                ) ?>

                            </td>


                            <td class="text-danger">

                                <?= money(
                                    $sale["discount"]
                                ) ?>

                            </td>


                            <td class="fw-bold">

                                <?= money(
                                    $sale["total"]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    ucfirst(
                                        $sale[
                                            "payment_method"
                                        ]
                                    )
                                ) ?>

                            </td>


                            <td>

                                <?= date(
                                    "d M Y H:i",
                                    strtotime(
                                        $sale[
                                            "created_at"
                                        ]
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
                            No completed POS sales yet.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<script>

const chartLabels =
    <?= json_encode(
        $chartLabels,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_AMP |
        JSON_HEX_QUOT
    ) ?>;

const chartRevenue =
    <?= json_encode(
        $chartRevenue,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_AMP |
        JSON_HEX_QUOT
    ) ?>;

const chartExpenses =
    <?= json_encode(
        $chartExpenses,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_AMP |
        JSON_HEX_QUOT
    ) ?>;


new Chart(
    document.getElementById(
        "profitChart"
    ),
    {
        type: "bar",

        data: {

            labels: chartLabels,

            datasets: [

                {
                    label: "Revenue",

                    data: chartRevenue
                },

                {
                    label: "Expenses",

                    data: chartExpenses
                }

            ]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {

                legend: {

                    position: "top"

                }

            },

            scales: {

                y: {

                    beginAtZero: true,

                    ticks: {

                        callback: function(value) {

                            return "Rs. " +
                                Number(
                                    value
                                ).toLocaleString();

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