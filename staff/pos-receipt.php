<?php

require_once "../config/admin_auth.php";
requireStaff();

require_once "../config/db.php";

$invoiceNumber = trim($_GET["sale"] ?? "");

if ($invoiceNumber === "") {
    die("Invalid sale.");
}

/*
|--------------------------------------------------------------------------
| Get POS Sale
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        pos_sales.id,
        pos_sales.invoice_number,
        pos_sales.staff_id,
        pos_sales.subtotal,
        pos_sales.discount,
        pos_sales.total,
        pos_sales.payment_method,
        pos_sales.sale_status,
        pos_sales.created_at,
        users.full_name AS staff_name
    FROM pos_sales
    LEFT JOIN users
        ON pos_sales.staff_id = users.id
    WHERE pos_sales.invoice_number = ?
    LIMIT 1
");

$stmt->execute([$invoiceNumber]);

$sale = $stmt->fetch();

if (!$sale) {
    die("Sale not found.");
}

/*
|--------------------------------------------------------------------------
| Get Sale Items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        product_name,
        unit_price,
        quantity,
        subtotal
    FROM pos_sale_items
    WHERE sale_id = ?
    ORDER BY id ASC
");

$stmt->execute([$sale["id"]]);

$items = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Cash / Change
|--------------------------------------------------------------------------
*/

$cashReceived = isset($_GET["cash"])
    ? (float) $_GET["cash"]
    : 0;

$change = isset($_GET["change"])
    ? (float) $_GET["change"]
    : 0;

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
        Receipt - <?= htmlspecialchars($sale["invoice_number"]) ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #f1f5f9;
            font-family: Arial, sans-serif;
            color: #111827;
        }

        .receipt {
            width: 420px;
            max-width: 100%;
            margin: 0 auto;
            background: #ffffff;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 26px;
        }

        .header p {
            margin: 5px 0;
            color: #64748b;
            font-size: 13px;
        }

        .divider {
            border-top: 1px dashed #94a3b8;
            margin: 15px 0;
        }

        .info {
            font-size: 13px;
            line-height: 1.7;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            padding: 8px 0;
            font-size: 13px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th:last-child,
        td:last-child {
            text-align: right;
        }

        td:nth-child(2),
        th:nth-child(2) {
            text-align: center;
        }

        .summary {
            margin-top: 15px;
            font-size: 14px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }

        .total {
            font-size: 19px;
            font-weight: bold;
            padding-top: 10px;
        }

        .payment-box {
            margin-top: 15px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 14px;
        }

        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .buttons button,
        .buttons a {
            flex: 1;
            border: none;
            padding: 11px;
            border-radius: 7px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .print-btn {
            background: #111827;
            color: white;
        }

        .back-btn {
            background: #e5e7eb;
            color: #111827;
        }

        @media print {

            body {
                background: white;
                padding: 0;
            }

            .receipt {
                width: 100%;
                max-width: 420px;
                box-shadow: none;
                margin: 0 auto;
            }

            .buttons {
                display: none;
            }

        }

    </style>

</head>

<body>

<div class="receipt">

    <!-- Header -->

    <div class="header">

        <h1>BizPilot</h1>

        <p>POS Sales Receipt</p>

    </div>

    <div class="divider"></div>

    <!-- Sale Information -->

    <div class="info">

        <div class="info-row">
            <strong>Invoice:</strong>
            <span>
                <?= htmlspecialchars($sale["invoice_number"]) ?>
            </span>
        </div>

        <div class="info-row">
            <strong>Date:</strong>
            <span>
                <?= htmlspecialchars(
                    date(
                        "Y-m-d H:i",
                        strtotime($sale["created_at"])
                    )
                ) ?>
            </span>
        </div>

        <div class="info-row">
            <strong>Staff:</strong>
            <span>
                <?= htmlspecialchars(
                    $sale["staff_name"] ?? "Staff"
                ) ?>
            </span>
        </div>

        <div class="info-row">
            <strong>Payment:</strong>
            <span>
                <?= htmlspecialchars(
                    ucfirst($sale["payment_method"])
                ) ?>
            </span>
        </div>

    </div>

    <div class="divider"></div>

    <!-- Items -->

    <table>

        <thead>

            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Amount</th>
            </tr>

        </thead>

        <tbody>

        <?php foreach ($items as $item): ?>

            <tr>

                <td>
                    <?= htmlspecialchars($item["product_name"]) ?>
                </td>

                <td>
                    <?= (int) $item["quantity"] ?>
                </td>

                <td>
                    Rs.
                    <?= number_format(
                        (float) $item["subtotal"],
                        2
                    ) ?>
                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

    <!-- Summary -->

    <div class="summary">

        <div class="summary-row">

            <span>Subtotal</span>

            <span>
                Rs.
                <?= number_format(
                    (float) $sale["subtotal"],
                    2
                ) ?>
            </span>

        </div>

        <div class="summary-row">

            <span>Discount</span>

            <span>
                Rs.
                <?= number_format(
                    (float) $sale["discount"],
                    2
                ) ?>
            </span>

        </div>

        <div class="divider"></div>

        <div class="summary-row total">

            <span>Total</span>

            <span>
                Rs.
                <?= number_format(
                    (float) $sale["total"],
                    2
                ) ?>
            </span>

        </div>

    </div>

    <?php if ($sale["payment_method"] === "cash"): ?>

        <div class="payment-box">

            <div class="summary-row">

                <span>Cash Received</span>

                <span>
                    Rs.
                    <?= number_format(
                        $cashReceived,
                        2
                    ) ?>
                </span>

            </div>

            <div class="summary-row">

                <strong>Change</strong>

                <strong>
                    Rs.
                    <?= number_format(
                        $change,
                        2
                    ) ?>
                </strong>

            </div>

        </div>

    <?php endif; ?>

    <div class="divider"></div>

    <div
        style="
            text-align:center;
            font-size:13px;
            color:#64748b;
        "
    >

        Thank you for shopping with BizPilot!

    </div>

    <!-- Buttons -->

    <div class="buttons">

        <button
            class="print-btn"
            onclick="window.print()"
        >
            Print Receipt
        </button>

        <a
            href="pos.php"
            class="back-btn"
        >
            Back to POS
        </a>

    </div>

</div>

</body>

</html>