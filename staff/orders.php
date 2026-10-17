<?php

require_once "../config/admin_auth.php";
requireStaff();

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| Update Order Status
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $order_id = (int) ($_POST["order_id"] ?? 0);
    $new_status = $_POST["order_status"] ?? "";

    $allowed_statuses = [
        "pending",
        "confirmed",
        "preparing",
        "ready",
        "out_for_delivery",
        "completed",
        "cancelled"
    ];

    if ($order_id <= 0 || !in_array($new_status, $allowed_statuses, true)) {
        header("Location: orders.php?error=invalid");
        exit;
    }

    try {

        $stmt = $pdo->prepare("
            UPDATE orders
            SET order_status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $new_status,
            $order_id
        ]);

        header("Location: orders.php?success=status_updated");
        exit;

    } catch (PDOException $e) {

        header("Location: orders.php?error=update_failed");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Get Orders
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        orders.*,
        users.full_name,
        users.email,
        users.phone
    FROM orders
    INNER JOIN users
        ON orders.user_id = users.id
    ORDER BY orders.created_at DESC
");

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function getStatusBadge($status)
{
    switch ($status) {

        case "pending":
            return '<span class="badge bg-warning text-dark">Pending</span>';

        case "confirmed":
            return '<span class="badge bg-primary">Confirmed</span>';

        case "preparing":
            return '<span class="badge bg-info text-dark">Preparing</span>';

        case "ready":
            return '<span class="badge bg-success">Ready</span>';

        case "out_for_delivery":
            return '<span class="badge bg-dark">Out for Delivery</span>';

        case "completed":
            return '<span class="badge bg-success">Completed</span>';

        case "cancelled":
            return '<span class="badge bg-danger">Cancelled</span>';

        default:
            return '<span class="badge bg-secondary">'
                . htmlspecialchars($status)
                . '</span>';
    }
}

function getFulfillmentBadge($type)
{
    if ($type === "pickup") {
        return '<span class="badge bg-success">Store Pickup</span>';
    }

    return '<span class="badge bg-primary">Home Delivery</span>';
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

    <title>Manage Orders - BizPilot AI</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f5f7f6;
        }

        .page-header {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .orders-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table th {
            white-space: nowrap;
            background: #f8faf9;
        }

        .table td {
            vertical-align: middle;
        }

        .order-number {
            font-weight: 600;
            color: #198754;
        }

        .customer-name {
            font-weight: 600;
        }

        .customer-contact {
            font-size: 13px;
            color: #777;
        }

        .empty-box {
            padding: 60px 20px;
            text-align: center;
            color: #777;
        }

        .detail-label {
            font-size: 13px;
            color: #777;
            margin-bottom: 4px;
        }

        .detail-value {
            font-weight: 600;
            margin-bottom: 15px;
        }

        .order-total {
            font-size: 20px;
            font-weight: 700;
            color: #198754;
        }

    </style>

</head>

<body>


<div class="container-fluid py-4 px-4">

    <?php require_once "../config/back-button.php"; ?>


    <!-- PAGE HEADER -->

    <div class="page-header">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

            <div>

                <h2 class="mb-1">
                    📦 Manage Orders
                </h2>

                <p class="text-muted mb-0">
                    View and manage customer orders.
                </p>

            </div>

            <div>

                <a
                    href="index.php"
                    class="btn btn-outline-secondary"
                >
                    ← Dashboard
                </a>

            </div>

        </div>

    </div>


    <!-- ALERTS -->

    <?php if (isset($_GET["success"])): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <?php if ($_GET["success"] === "status_updated"): ?>

                Order status updated successfully.

            <?php else: ?>

                Operation completed successfully.

            <?php endif; ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            Something went wrong. Please try again.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- ORDERS -->

    <div class="orders-card">

        <div class="p-4 border-bottom">

            <h5 class="mb-1">
                Customer Orders
            </h5>

            <small class="text-muted">
                <?= count($orders) ?> order(s) found
            </small>

        </div>


        <?php if (empty($orders)): ?>

            <div class="empty-box">

                <div style="font-size: 50px;">
                    📦
                </div>

                <h5 class="mt-3">
                    No Orders Found
                </h5>

                <p>
                    Customer orders will appear here.
                </p>

            </div>

        <?php else: ?>


            <div class="table-responsive">

                <table class="table table-hover mb-0">

                    <thead>

                        <tr>

                            <th>#</th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Fulfillment</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($orders as $order): ?>

                        <tr>

                            <td>
                                <?= (int) $order["id"] ?>
                            </td>


                            <td>

                                <div class="order-number">

                                    <?= htmlspecialchars(
                                        $order["order_number"]
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <div class="customer-name">

                                    <?= htmlspecialchars(
                                        $order["full_name"]
                                    ) ?>

                                </div>

                                <div class="customer-contact">

                                    <?= htmlspecialchars(
                                        $order["phone"] ?? ""
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <?= getFulfillmentBadge(
                                    $order["fulfillment_type"]
                                ) ?>

                            </td>


                            <td>

                                <strong>
                                    Rs.
                                    <?= number_format(
                                        (float) $order["total"],
                                        2
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <?php if (
                                    $order["payment_status"] === "paid"
                                ): ?>

                                    <span class="badge bg-success">
                                        Paid
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-warning text-dark">
                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $order["payment_status"]
                                            )
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                                <div class="small text-muted mt-1">

                                    <?= htmlspecialchars(
                                        ucwords(
                                            str_replace(
                                                "_",
                                                " ",
                                                $order["payment_method"]
                                            )
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <?= getStatusBadge(
                                    $order["order_status"]
                                ) ?>

                            </td>


                            <td>

                                <small>

                                    <?= date(
                                        "Y-m-d",
                                        strtotime(
                                            $order["created_at"]
                                        )
                                    ) ?>

                                    <br>

                                    <?= date(
                                        "h:i A",
                                        strtotime(
                                            $order["created_at"]
                                        )
                                    ) ?>

                                </small>

                            </td>


                            <td>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-success"
                                    data-bs-toggle="modal"
                                    data-bs-target="#orderModal<?= (int) $order["id"] ?>"
                                >
                                    View
                                </button>

                            </td>

                        </tr>


                        <!-- ORDER DETAILS MODAL -->

                        <div
                            class="modal fade"
                            id="orderModal<?= (int) $order["id"] ?>"
                            tabindex="-1"
                        >

                            <div class="modal-dialog modal-lg modal-dialog-centered">

                                <div class="modal-content">


                                    <div class="modal-header">

                                        <div>

                                            <h5 class="modal-title mb-1">

                                                Order
                                                <?= htmlspecialchars(
                                                    $order["order_number"]
                                                ) ?>

                                            </h5>

                                            <small class="text-muted">

                                                <?= date(
                                                    "Y-m-d h:i A",
                                                    strtotime(
                                                        $order["created_at"]
                                                    )
                                                ) ?>

                                            </small>

                                        </div>

                                        <button
                                            type="button"
                                            class="btn-close"
                                            data-bs-dismiss="modal"
                                        ></button>

                                    </div>


                                    <div class="modal-body">


                                        <!-- CUSTOMER -->

                                        <h6 class="border-bottom pb-2 mb-3">
                                            Customer Information
                                        </h6>

                                        <div class="row">

                                            <div class="col-md-4">

                                                <div class="detail-label">
                                                    Name
                                                </div>

                                                <div class="detail-value">

                                                    <?= htmlspecialchars(
                                                        $order["full_name"]
                                                    ) ?>

                                                </div>

                                            </div>


                                            <div class="col-md-4">

                                                <div class="detail-label">
                                                    Email
                                                </div>

                                                <div class="detail-value">

                                                    <?= htmlspecialchars(
                                                        $order["email"]
                                                    ) ?>

                                                </div>

                                            </div>


                                            <div class="col-md-4">

                                                <div class="detail-label">
                                                    Phone
                                                </div>

                                                <div class="detail-value">

                                                    <?= htmlspecialchars(
                                                        $order["phone"] ?? "-"
                                                    ) ?>

                                                </div>

                                            </div>

                                        </div>


                                        <!-- FULFILLMENT -->

                                        <h6 class="border-bottom pb-2 mb-3 mt-3">
                                            Fulfillment Information
                                        </h6>

                                        <div class="row">

                                            <div class="col-md-4">

                                                <div class="detail-label">
                                                    Type
                                                </div>

                                                <div class="detail-value">

                                                    <?= getFulfillmentBadge(
                                                        $order["fulfillment_type"]
                                                    ) ?>

                                                </div>

                                            </div>


                                            <?php if (
                                                $order["fulfillment_type"]
                                                === "pickup"
                                            ): ?>


                                                <div class="col-md-4">

                                                    <div class="detail-label">
                                                        Pickup Date
                                                    </div>

                                                    <div class="detail-value">

                                                        <?= htmlspecialchars(
                                                            $order["pickup_date"]
                                                            ?? "-"
                                                        ) ?>

                                                    </div>

                                                </div>


                                                <div class="col-md-4">

                                                    <div class="detail-label">
                                                        Pickup Time
                                                    </div>

                                                    <div class="detail-value">

                                                        <?= htmlspecialchars(
                                                            $order["pickup_time"]
                                                            ?? "-"
                                                        ) ?>

                                                    </div>

                                                </div>


                                                <div class="col-md-4">

                                                    <div class="detail-label">
                                                        Pickup Code
                                                    </div>

                                                    <div class="detail-value">

                                                        <span
                                                            class="badge bg-dark fs-6"
                                                        >

                                                            <?= htmlspecialchars(
                                                                $order["pickup_code"]
                                                                ?? "-"
                                                            ) ?>

                                                        </span>

                                                    </div>

                                                </div>


                                            <?php else: ?>


                                                <div class="col-md-8">

                                                    <div class="detail-label">
                                                        Delivery Address
                                                    </div>

                                                    <div class="detail-value">

                                                        <?= nl2br(
                                                            htmlspecialchars(
                                                                $order["delivery_address"]
                                                                ?? "-"
                                                            )
                                                        ) ?>

                                                    </div>

                                                </div>


                                            <?php endif; ?>

                                        </div>


                                        <!-- NOTES -->

                                        <?php if (
                                            !empty($order["notes"])
                                        ): ?>

                                            <h6 class="border-bottom pb-2 mb-3 mt-3">
                                                Customer Notes
                                            </h6>

                                            <div class="alert alert-light">

                                                <?= nl2br(
                                                    htmlspecialchars(
                                                        $order["notes"]
                                                    )
                                                ) ?>

                                            </div>

                                        <?php endif; ?>


                                        <!-- PAYMENT / TOTAL -->

                                        <h6 class="border-bottom pb-2 mb-3 mt-3">
                                            Order Summary
                                        </h6>

                                        <div class="row">

                                            <div class="col-md-4">

                                                <div class="detail-label">
                                                    Subtotal
                                                </div>

                                                <div class="detail-value">

                                                    Rs.
                                                    <?= number_format(
                                                        (float) $order["subtotal"],
                                                        2
                                                    ) ?>

                                                </div>

                                            </div>


                                            <div class="col-md-4">

                                                <div class="detail-label">
                                                    Delivery Fee
                                                </div>

                                                <div class="detail-value">

                                                    Rs.
                                                    <?= number_format(
                                                        (float) $order["delivery_fee"],
                                                        2
                                                    ) ?>

                                                </div>

                                            </div>


                                            <div class="col-md-4">

                                                <div class="detail-label">
                                                    Total
                                                </div>

                                                <div class="order-total">

                                                    Rs.
                                                    <?= number_format(
                                                        (float) $order["total"],
                                                        2
                                                    ) ?>

                                                </div>

                                            </div>

                                        </div>


                                        <!-- STATUS UPDATE -->

                                        <h6 class="border-bottom pb-2 mb-3 mt-3">
                                            Update Order Status
                                        </h6>

                                        <form
                                            method="POST"
                                            action="orders.php"
                                        >

                                            <input
                                                type="hidden"
                                                name="order_id"
                                                value="<?= (int) $order["id"] ?>"
                                            >


                                            <div class="row align-items-end">

                                                <div class="col-md-8">

                                                    <label
                                                        class="form-label"
                                                    >
                                                        Order Status
                                                    </label>

                                                    <select
                                                        name="order_status"
                                                        class="form-select"
                                                        required
                                                    >

                                                        <option
                                                            value="pending"
                                                            <?= $order["order_status"] === "pending"
                                                                ? "selected"
                                                                : "" ?>
                                                        >
                                                            Pending
                                                        </option>

                                                        <option
                                                            value="confirmed"
                                                            <?= $order["order_status"] === "confirmed"
                                                                ? "selected"
                                                                : "" ?>
                                                        >
                                                            Confirmed
                                                        </option>

                                                        <option
                                                            value="preparing"
                                                            <?= $order["order_status"] === "preparing"
                                                                ? "selected"
                                                                : "" ?>
                                                        >
                                                            Preparing
                                                        </option>

                                                        <option
                                                            value="ready"
                                                            <?= $order["order_status"] === "ready"
                                                                ? "selected"
                                                                : "" ?>
                                                        >
                                                            Ready
                                                        </option>

                                                        <option
                                                            value="out_for_delivery"
                                                            <?= $order["order_status"] === "out_for_delivery"
                                                                ? "selected"
                                                                : "" ?>
                                                        >
                                                            Out for Delivery
                                                        </option>

                                                        <option
                                                            value="completed"
                                                            <?= $order["order_status"] === "completed"
                                                                ? "selected"
                                                                : "" ?>
                                                        >
                                                            Completed
                                                        </option>

                                                        <option
                                                            value="cancelled"
                                                            <?= $order["order_status"] === "cancelled"
                                                                ? "selected"
                                                                : "" ?>
                                                        >
                                                            Cancelled
                                                        </option>

                                                    </select>

                                                </div>


                                                <div class="col-md-4">

                                                    <button
                                                        type="submit"
                                                        class="btn btn-success w-100"
                                                    >
                                                        Update Status
                                                    </button>

                                                </div>

                                            </div>

                                        </form>

                                    </div>


                                    <div class="modal-footer">

                                        <button
                                            type="button"
                                            class="btn btn-secondary"
                                            data-bs-dismiss="modal"
                                        >
                                            Close
                                        </button>

                                    </div>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>