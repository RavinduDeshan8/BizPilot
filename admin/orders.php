<?php

require_once "../config/admin_auth.php";
requireAdmin();

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| Update Order Status
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $orderId = isset($_POST["order_id"])
        ? (int) $_POST["order_id"]
        : 0;

    $newStatus = $_POST["order_status"] ?? "";

    $allowedStatuses = [
        "pending",
        "confirmed",
        "preparing",
        "ready",
        "out_for_delivery",
        "completed",
        "cancelled"
    ];

    if (
        $orderId <= 0 ||
        !in_array($newStatus, $allowedStatuses, true)
    ) {
        header(
            "Location: orders.php?error=" .
            urlencode("Invalid order status.")
        );
        exit;
    }

    try {

        /*
        |--------------------------------------------------------------------------
        | Start Transaction
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Get Current Order
        |--------------------------------------------------------------------------
        */

$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.order_number,
        o.order_status,
        o.fulfillment_type,
        o.user_id,
        u.full_name AS customer_name
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.id = ?
    FOR UPDATE
");

$stmt->execute([$orderId]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    throw new Exception("Order not found.");
}

$oldStatus = $order["order_status"];


        /*
        |--------------------------------------------------------------------------
        | Prevent Reopening Cancelled Order
        |--------------------------------------------------------------------------
        */

        if (
            $oldStatus === "cancelled" &&
            $newStatus !== "cancelled"
        ) {
            throw new Exception(
                "A cancelled order cannot be reopened."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Nothing To Change
        |--------------------------------------------------------------------------
        */

        if ($oldStatus === $newStatus) {

            $pdo->commit();

            header(
                "Location: orders.php?success=status_updated"
            );

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Cancel Order
        |--------------------------------------------------------------------------
        */

        if (
            $newStatus === "cancelled" &&
            $oldStatus !== "cancelled"
        ) {

            /*
            |--------------------------------------------------------------------------
            | Get Order Items
            |--------------------------------------------------------------------------
            */

            $itemStmt = $pdo->prepare("
                SELECT
                    id,
                    product_id,
                    product_name,
                    quantity
                FROM order_items
                WHERE order_id = ?
                FOR UPDATE
            ");

            $itemStmt->execute([$orderId]);

            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) {
                throw new Exception(
                    "No order items found for this order."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Restore Stock
            |--------------------------------------------------------------------------
            */

            foreach ($items as $item) {

                $productId = (int) $item["product_id"];
                $quantity = (int) $item["quantity"];

                if (
                    $productId <= 0 ||
                    $quantity <= 0
                ) {
                    throw new Exception(
                        "Invalid order item found."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Lock Product
                |--------------------------------------------------------------------------
                */

                $productStmt = $pdo->prepare("
                    SELECT
                        id,
                        name,
                        stock
                    FROM products
                    WHERE id = ?
                    FOR UPDATE
                ");

                $productStmt->execute([$productId]);

                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new Exception(
                        "Product not found: " .
                        $item["product_name"]
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Restore Product Stock
                |--------------------------------------------------------------------------
                */

                $stockStmt = $pdo->prepare("
                    UPDATE products
                    SET stock = stock + ?
                    WHERE id = ?
                ");

                $stockStmt->execute([
                    $quantity,
                    $productId
                ]);

                /*
                |--------------------------------------------------------------------------
                | Record Stock Movement
                |--------------------------------------------------------------------------
                */

                $movementStmt = $pdo->prepare("
                    INSERT INTO stock_movements
                    (
                        product_id,
                        movement_type,
                        quantity,
                        reference_type,
                        reference_id,
                        notes
                    )
                    VALUES
                    (
                        ?,
                        'return',
                        ?,
                        'order',
                        ?,
                        ?
                    )
                ");

                $movementStmt->execute([
                    $productId,
                    $quantity,
                    $orderId,
                    "Cancelled order " . $order["order_number"]
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Update Order Status
        |--------------------------------------------------------------------------
        */

        $updateStmt = $pdo->prepare("
            UPDATE orders
            SET order_status = ?
            WHERE id = ?
        ");

        
/*
|--------------------------------------------------------------------------
| Synchronize Order Status With Queue
|--------------------------------------------------------------------------
*/
$updateStmt->execute([
    $newStatus,
    $orderId
]);

if ($newStatus === "cancelled") {

    // Cancel all active queue entries for this order.
    $queueStmt = $pdo->prepare("
        UPDATE queue_entries
        SET
            status = 'cancelled',
            completed_at = COALESCE(completed_at, NOW())
        WHERE order_id = ?
          AND status NOT IN ('completed', 'cancelled')
    ");

    $queueStmt->execute([$orderId]);

} elseif ($newStatus === "completed") {

    // Complete all active queue entries for this order.
    $queueStmt = $pdo->prepare("
        UPDATE queue_entries
        SET
            status = 'completed',
            completed_at = COALESCE(completed_at, NOW())
        WHERE order_id = ?
          AND status NOT IN ('completed', 'cancelled')
    ");

    $queueStmt->execute([$orderId]);

} else {

    $queueType = null;
    $queueStatus = "waiting";

    if (in_array($newStatus, ["confirmed", "preparing"], true)) {

        $queueType = "preparation";

        if ($newStatus === "preparing") {
            $queueStatus = "in_progress";
        }

    } elseif ($newStatus === "ready") {

        $queueType =
            ($order["fulfillment_type"] ?? "") === "pickup"
                ? "pickup"
                : "delivery";

        $queueStatus = "ready";

    } elseif ($newStatus === "out_for_delivery") {

        $queueType = "delivery";
        $queueStatus = "in_progress";
    }

    if ($queueType !== null) {

        // Finish the previous active stage when the order moves on.
        $finishStmt = $pdo->prepare("
            UPDATE queue_entries
            SET
                status = 'completed',
                completed_at = COALESCE(completed_at, NOW())
            WHERE order_id = ?
              AND queue_type <> ?
              AND status NOT IN ('completed', 'cancelled')
        ");

        $finishStmt->execute([$orderId, $queueType]);

        // Reuse an active entry instead of creating a duplicate.
        $findQueueStmt = $pdo->prepare("
            SELECT id
            FROM queue_entries
            WHERE order_id = ?
              AND queue_type = ?
              AND status NOT IN ('completed', 'cancelled')
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");

        $findQueueStmt->execute([$orderId, $queueType]);

        $queueEntry = $findQueueStmt->fetch(PDO::FETCH_ASSOC);

        if ($queueEntry) {

            $updateQueueStmt = $pdo->prepare("
                UPDATE queue_entries
                SET
                    status = ?,
                    started_at = CASE
                        WHEN ? = 'in_progress'
                        THEN COALESCE(started_at, NOW())
                        ELSE started_at
                    END
                WHERE id = ?
            ");

            $updateQueueStmt->execute([
                $queueStatus,
                $queueStatus,
                (int) $queueEntry["id"]
            ]);

        } else {

            $startedAt =
                $queueStatus === "in_progress"
                    ? date("Y-m-d H:i:s")
                    : null;

            $insertQueueStmt = $pdo->prepare("
                INSERT INTO queue_entries (
                    queue_type,
                    reference_number,
                    customer_name,
                    order_id,
                    status,
                    started_at,
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $insertQueueStmt->execute([
                $queueType,
                $order["order_number"],
                $order["customer_name"] ?? null,
                $orderId,
                $queueStatus,
                $startedAt,
                "Automatically created from order status"
            ]);
        }
    }
}


        /*
        |--------------------------------------------------------------------------
        | Commit Transaction
        |--------------------------------------------------------------------------
        */

        $pdo->commit();

        if ($newStatus === "cancelled") {

            header(
                "Location: orders.php?success=order_cancelled"
            );

        } else {

            header(
                "Location: orders.php?success=status_updated"
            );
        }

        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        header(
            "Location: orders.php?error=" .
            urlencode($e->getMessage())
        );

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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Orders - BizPilot AI</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="../assets/css/style.css">

</head>

<body class="bg-light">

<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar navbar-dark bg-dark">

    <div class="container">

        <a
            href="index.php"
            class="navbar-brand fw-bold">

            🛍️ BizPilot AI

        </a>

        <div>

            <span class="text-white me-3">

                👤

                <?= htmlspecialchars(
                    $_SESSION["user_name"] ?? "Admin"
                ) ?>

            </span>

            <a
                href="../logout.php"
                class="btn btn-outline-light btn-sm">

                Logout

            </a>

        </div>

    </div>

</nav>

<!-- =========================================================
     MAIN
========================================================= -->

<div class="container-fluid py-5 px-4">

    <!-- Previous Page -->

    <?php require_once "../config/back-button.php"; ?>

    <!-- HEADER -->

    <div
        class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

        <div>

            <h1 class="fw-bold mb-1">
                Order Management
            </h1>

            <p class="text-muted mb-0">
                View and manage customer orders.
            </p>

        </div>

        <div>

            <a
                href="index.php"
                class="btn btn-outline-secondary">

                ← Dashboard

            </a>

        </div>

    </div>

    <!-- =====================================================
         ALERTS
    ====================================================== -->

    <?php if (
        isset($_GET["success"]) &&
        $_GET["success"] === "status_updated"
    ): ?>

        <div class="alert alert-success">
            ✅ Order status updated successfully.
        </div>

    <?php endif; ?>

    <?php if (
        isset($_GET["success"]) &&
        $_GET["success"] === "order_cancelled"
    ): ?>

        <div class="alert alert-success">

            ✅ Order cancelled successfully.
            Product stock has been restored.

        </div>

    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-danger">

            ❌

            <?= htmlspecialchars(
                $_GET["error"]
            ) ?>

        </div>

    <?php endif; ?>

    <!-- =====================================================
         ORDERS TABLE
    ====================================================== -->

    <div class="card border-0 shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table
                    class="table table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>#</th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($orders) > 0): ?>

                        <?php foreach ($orders as $order): ?>

                            <?php

                            $status =
                                $order["order_status"]
                                ?? "pending";

                            $statusClass = match ($status) {

                                "pending" =>
                                    "bg-secondary",

                                "confirmed" =>
                                    "bg-primary",

                                "preparing" =>
                                    "bg-warning text-dark",

                                "ready" =>
                                    "bg-info text-dark",

                                "out_for_delivery" =>
                                    "bg-primary",

                                "completed" =>
                                    "bg-success",

                                "cancelled" =>
                                    "bg-danger",

                                default =>
                                    "bg-secondary"
                            };

                            $typeLabel =
                                (
                                    $order["fulfillment_type"]
                                    ?? ""
                                ) === "pickup"

                                ? "Store Pickup"

                                : "Home Delivery";

                            ?>

                            <tr>

                                <!-- ID -->

                                <td>
                                    <?= (int) $order["id"] ?>
                                </td>

                                <!-- ORDER NUMBER -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $order["order_number"]
                                        ) ?>

                                    </strong>

                                    <?php if (
                                        (
                                            $order["fulfillment_type"]
                                            ?? ""
                                        ) === "pickup"
                                    ): ?>

                                        <br>

                                        <small class="text-muted">

                                            Pickup Code:

                                            <?= htmlspecialchars(
                                                $order["pickup_code"]
                                                ?? "-"
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </td>

                                <!-- CUSTOMER -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $order["full_name"]
                                        ) ?>

                                    </strong>

                                    <br>

                                    <small class="text-muted">

                                        <?= htmlspecialchars(
                                            $order["email"]
                                        ) ?>

                                    </small>

                                    <?php if (
                                        !empty($order["phone"])
                                    ): ?>

                                        <br>

                                        <small class="text-muted">

                                            📞

                                            <?= htmlspecialchars(
                                                $order["phone"]
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </td>

                                <!-- TYPE -->

                                <td>

                                    <?php if (
                                        (
                                            $order["fulfillment_type"]
                                            ?? ""
                                        ) === "pickup"
                                    ): ?>

                                        <span
                                            class="badge bg-info text-dark">

                                            🏪 Store Pickup

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge bg-primary">

                                            🚚 Delivery

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- TOTAL -->

                                <td>

                                    <strong>

                                        Rs.

                                        <?= number_format(
                                            (float) $order["total"],
                                            2
                                        ) ?>

                                    </strong>

                                </td>

                                <!-- PAYMENT -->

                                <td>

                                    <?php

                                    $paymentMethod =
                                        $order["payment_method"]
                                        ?? "";

                                    ?>

                                    <?php if (
                                        $paymentMethod ===
                                        "pay_at_store"
                                    ): ?>

                                        <span
                                            class="badge bg-warning text-dark">

                                            Pay at Store

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge bg-secondary">

                                            Cash on Delivery

                                        </span>

                                    <?php endif; ?>

                                    <br>

                                    <small>

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $order["payment_status"]
                                                ?? "pending"
                                            )
                                        ) ?>

                                    </small>

                                </td>

                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="badge <?= $statusClass ?>">

                                        <?= htmlspecialchars(
                                            ucwords(
                                                str_replace(
                                                    "_",
                                                    " ",
                                                    $status
                                                )
                                            )
                                        ) ?>

                                    </span>

                                </td>

                                <!-- DATE -->

                                <td>

                                    <?= date(
                                        "Y-m-d",
                                        strtotime(
                                            $order["created_at"]
                                        )
                                    ) ?>

                                    <br>

                                    <small
                                        class="text-muted">

                                        <?= date(
                                            "h:i A",
                                            strtotime(
                                                $order["created_at"]
                                            )
                                        ) ?>

                                    </small>

                                </td>

                                <!-- ACTION -->

                                <td>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#orderModal<?= (int) $order["id"] ?>">

                                        View

                                    </button>

                                </td>

                            </tr>

                            <!-- =================================================
                                 ORDER DETAILS MODAL
                            ================================================== -->

                            <div
                                class="modal fade"
                                id="orderModal<?= (int) $order["id"] ?>"
                                tabindex="-1">

                                <div class="modal-dialog modal-lg">

                                    <div class="modal-content">

                                        <div class="modal-header">

                                            <h5 class="modal-title">

                                                Order Details -

                                                <?= htmlspecialchars(
                                                    $order["order_number"]
                                                ) ?>

                                            </h5>

                                            <button
                                                type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal">
                                            </button>

                                        </div>

                                        <div class="modal-body">

                                            <!-- CUSTOMER -->

                                            <h6 class="fw-bold">
                                                Customer Information
                                            </h6>

                                            <div class="mb-4">

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $order["full_name"]
                                                    ) ?>

                                                </strong>

                                                <br>

                                                <?= htmlspecialchars(
                                                    $order["email"]
                                                ) ?>

                                                <?php if (
                                                    !empty(
                                                        $order["phone"]
                                                    )
                                                ): ?>

                                                    <br>

                                                    <?= htmlspecialchars(
                                                        $order["phone"]
                                                    ) ?>

                                                <?php endif; ?>

                                            </div>

                                            <!-- FULFILLMENT -->

                                            <h6 class="fw-bold">
                                                Fulfillment
                                            </h6>

                                            <div class="mb-4">

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $typeLabel
                                                    ) ?>

                                                </strong>

                                                <?php if (
                                                    (
                                                        $order[
                                                            "fulfillment_type"
                                                        ]
                                                        ?? ""
                                                    ) === "pickup"
                                                ): ?>

                                                    <br>

                                                    Pickup Date:

                                                    <?= htmlspecialchars(
                                                        $order["pickup_date"]
                                                        ?? "-"
                                                    ) ?>

                                                    <br>

                                                    Pickup Time:

                                                    <?= htmlspecialchars(
                                                        $order["pickup_time"]
                                                        ?? "-"
                                                    ) ?>

                                                    <br>

                                                    Pickup Code:

                                                    <strong>

                                                        <?= htmlspecialchars(
                                                            $order[
                                                                "pickup_code"
                                                            ]
                                                            ?? "-"
                                                        ) ?>

                                                    </strong>

                                                <?php else: ?>

                                                    <br>

                                                    Delivery Address:

                                                    <br>

                                                    <?= nl2br(
                                                        htmlspecialchars(
                                                            $order[
                                                                "delivery_address"
                                                            ]
                                                            ?? "-"
                                                        )
                                                    ) ?>

                                                <?php endif; ?>

                                            </div>

                                            <!-- NOTES -->

                                            <?php if (
                                                !empty(
                                                    $order["notes"]
                                                )
                                            ): ?>

                                                <h6 class="fw-bold">
                                                    Customer Notes
                                                </h6>

                                                <p>

                                                    <?= nl2br(
                                                        htmlspecialchars(
                                                            $order["notes"]
                                                        )
                                                    ) ?>

                                                </p>

                                            <?php endif; ?>

                                            <!-- TOTAL -->

                                            <div
                                                class="border-top pt-3 mt-3">

                                                <div
                                                    class="d-flex justify-content-between">

                                                    <span>
                                                        Subtotal
                                                    </span>

                                                    <strong>

                                                        Rs.

                                                        <?= number_format(
                                                            (float)
                                                            $order[
                                                                "subtotal"
                                                            ],
                                                            2
                                                        ) ?>

                                                    </strong>

                                                </div>

                                                <div
                                                    class="d-flex justify-content-between">

                                                    <span>
                                                        Delivery Fee
                                                    </span>

                                                    <strong>

                                                        Rs.

                                                        <?= number_format(
                                                            (float)
                                                            $order[
                                                                "delivery_fee"
                                                            ],
                                                            2
                                                        ) ?>

                                                    </strong>

                                                </div>

                                                <div
                                                    class="d-flex justify-content-between fs-5 mt-2">

                                                    <strong>
                                                        Total
                                                    </strong>

                                                    <strong>

                                                        Rs.

                                                        <?= number_format(
                                                            (float)
                                                            $order[
                                                                "total"
                                                            ],
                                                            2
                                                        ) ?>

                                                    </strong>

                                                </div>

                                            </div>

                                            <!-- STATUS UPDATE -->

                                            <div
                                                class="border-top mt-4 pt-4">

                                                <h6 class="fw-bold">
                                                    Update Order Status
                                                </h6>

                                                <?php if (
                                                    $status === "cancelled"
                                                ): ?>

                                                    <div
                                                        class="alert alert-danger mb-0">

                                                        ⚠️ This order is
                                                        cancelled and cannot
                                                        be reopened.

                                                        Product stock has
                                                        already been restored.

                                                    </div>

                                                <?php else: ?>

                                                    <form
                                                        method="POST"
                                                        class="row g-2">

                                                        <input
                                                            type="hidden"
                                                            name="order_id"
                                                            value="<?= (int) $order["id"] ?>">

                                                        <div
                                                            class="col-md-8">

                                                            <select
                                                                name="order_status"
                                                                class="form-select"
                                                                required>

                                                                <?php

                                                                $statusOptions = [

                                                                    "pending" =>
                                                                        "Pending",

                                                                    "confirmed" =>
                                                                        "Confirmed",

                                                                    "preparing" =>
                                                                        "Preparing",

                                                                    "ready" =>
                                                                        "Ready",

                                                                    "out_for_delivery" =>
                                                                        "Out for Delivery",

                                                                    "completed" =>
                                                                        "Completed",

                                                                    "cancelled" =>
                                                                        "Cancelled"

                                                                ];

                                                                ?>

                                                                <?php foreach (
                                                                    $statusOptions
                                                                    as $value =>
                                                                    $label
                                                                ): ?>

                                                                    <option
                                                                        value="<?= $value ?>"
                                                                        <?= $status === $value
                                                                            ? "selected"
                                                                            : "" ?>>

                                                                        <?= $label ?>

                                                                    </option>

                                                                <?php endforeach; ?>

                                                            </select>

                                                        </div>

                                                        <div
                                                            class="col-md-4">

                                                            <button
                                                                type="submit"
                                                                class="btn btn-success w-100">

                                                                Update Status

                                                            </button>

                                                        </div>

                                                    </form>

                                                <?php endif; ?>

                                            </div>

                                        </div>

                                        <div class="modal-footer">

                                            <button
                                                type="button"
                                                class="btn btn-secondary"
                                                data-bs-dismiss="modal">

                                                Close

                                            </button>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="9"
                                class="text-center py-5">

                                <div class="fs-1">
                                    📦
                                </div>

                                <h5>
                                    No Orders Found
                                </h5>

                                <p class="text-muted">
                                    Customer orders will appear here.
                                </p>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>