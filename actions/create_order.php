<?php

require_once "../config/db.php";
require_once "../config/auth.php";

requireLogin();

$userId = (int) ($_SESSION["user_id"] ?? 0);

if ($userId <= 0) {
    die("Invalid user session.");
}


/*
|--------------------------------------------------------------------------
| Get Form Data
|--------------------------------------------------------------------------
*/

$fulfillmentType =
    $_POST["fulfillment_type"] ?? "pickup";

$paymentMethod =
    $_POST["payment_method"] ?? "pay_at_store";

$deliveryAddress =
    trim($_POST["delivery_address"] ?? "");

$pickupDate =
    $_POST["pickup_date"] ?? null;

$pickupTime =
    $_POST["pickup_time"] ?? null;

$notes =
    trim($_POST["notes"] ?? "");

$deliveryNotes =
    trim($_POST["delivery_notes"] ?? "");


/*
|--------------------------------------------------------------------------
| Validate Fulfillment Type
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $fulfillmentType,
        ["pickup", "delivery"],
        true
    )
) {
    die("Invalid order type.");
}


/*
|--------------------------------------------------------------------------
| Validate Payment Method
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $paymentMethod,
        [
            "pay_at_store",
            "cash_on_delivery"
        ],
        true
    )
) {
    die("Invalid payment method.");
}


/*
|--------------------------------------------------------------------------
| Validate Delivery
|--------------------------------------------------------------------------
*/

if (
    $fulfillmentType === "delivery"
    && empty($deliveryAddress)
) {
    die("Delivery address is required.");
}


/*
|--------------------------------------------------------------------------
| Validate Pickup
|--------------------------------------------------------------------------
*/

if (
    $fulfillmentType === "pickup"
    && empty($pickupDate)
) {
    die("Pickup date is required.");
}


/*
|--------------------------------------------------------------------------
| Delivery Notes
|--------------------------------------------------------------------------
*/

if ($fulfillmentType === "delivery") {
    $notes = $deliveryNotes;
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
    | Get Customer Cart
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id
        FROM cart
        WHERE user_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $userId
    ]);

    $cart = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$cart) {
        throw new Exception(
            "Shopping cart not found."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get Cart Items + Lock Products
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            ci.id AS cart_item_id,
            ci.product_id,
            ci.quantity,

            p.name,
            p.price,
            p.cost_price,
            p.stock,
            p.status

        FROM cart_items ci

        INNER JOIN products p
            ON p.id = ci.product_id

        WHERE ci.cart_id = ?

        FOR UPDATE
    ");

    $stmt->execute([
        $cart["id"]
    ]);

    $items =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Check Empty Cart
    |--------------------------------------------------------------------------
    */

    if (empty($items)) {

        throw new Exception(
            "Your cart is empty."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Items + Calculate Subtotal
    |--------------------------------------------------------------------------
    */

    $subtotal = 0;


    foreach ($items as $item) {

        $quantity =
            (int) $item["quantity"];

        $stock =
            (int) $item["stock"];


        /*
        | Validate quantity
        */

        if ($quantity <= 0) {

            throw new Exception(
                "Invalid quantity for " .
                $item["name"]
            );

        }


        /*
        | Validate product status
        */

        if ($item["status"] !== "active") {

            throw new Exception(
                $item["name"] .
                " is no longer available."
            );

        }


        /*
        | Validate stock
        */

        if ($quantity > $stock) {

            throw new Exception(
                "Not enough stock for " .
                $item["name"] .
                ". Available: " .
                $stock
            );

        }


        /*
        | Calculate subtotal
        */

        $subtotal +=
            (float) $item["price"]
            * $quantity;

    }


    /*
    |--------------------------------------------------------------------------
    | Delivery Fee
    |--------------------------------------------------------------------------
    */

    $deliveryFee = 0;


    if (
        $fulfillmentType === "delivery"
    ) {

        $deliveryFee = 300;

    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Total
    |--------------------------------------------------------------------------
    */

    $total =
        $subtotal +
        $deliveryFee;


    /*
    |--------------------------------------------------------------------------
    | Generate Unique Order Number
    |--------------------------------------------------------------------------
    */

    do {

        $orderNumber =
            "BP" .
            date("YmdHis") .
            rand(100, 999);


        $checkStmt = $pdo->prepare("
            SELECT id
            FROM orders
            WHERE order_number = ?
            LIMIT 1
        ");

        $checkStmt->execute([
            $orderNumber
        ]);

        $existingOrder =
            $checkStmt->fetchColumn();

    } while ($existingOrder);


    /*
    |--------------------------------------------------------------------------
    | Generate Pickup Code
    |--------------------------------------------------------------------------
    */

    $pickupCode = null;


    if (
        $fulfillmentType === "pickup"
    ) {

        $pickupCode =
            strtoupper(
                substr(
                    bin2hex(
                        random_bytes(8)
                    ),
                    0,
                    8
                )
            );

    }


    /*
    |--------------------------------------------------------------------------
    | Payment Status
    |--------------------------------------------------------------------------
    */

    $paymentStatus =
        "pending";


    /*
    |--------------------------------------------------------------------------
    | Insert Order
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO orders
        (
            user_id,
            order_number,
            fulfillment_type,
            delivery_address,
            pickup_date,
            pickup_time,
            payment_method,
            payment_status,
            order_status,
            subtotal,
            delivery_fee,
            total,
            pickup_code,
            notes
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'pending',
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");


    $stmt->execute([

        $userId,

        $orderNumber,

        $fulfillmentType,

        $fulfillmentType === "delivery"
            ? $deliveryAddress
            : null,

        $fulfillmentType === "pickup"
            ? $pickupDate
            : null,

        $fulfillmentType === "pickup"
            ? $pickupTime
            : null,

        $paymentMethod,

        $paymentStatus,

        $subtotal,

        $deliveryFee,

        $total,

        $pickupCode,

        $notes

    ]);


    $orderId =
        (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Prepare Statements
    |--------------------------------------------------------------------------
    */

    $itemStmt = $pdo->prepare("
        INSERT INTO order_items
        (
            order_id,
            product_id,
            product_name,
            unit_price,
            quantity,
            subtotal
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");


    /*
    | IMPORTANT:
    | Update stock only if enough stock still exists.
    */

    $stockStmt = $pdo->prepare("
        UPDATE products

        SET stock = stock - ?

        WHERE id = ?

        AND stock >= ?
    ");


    /*
    |--------------------------------------------------------------------------
    | Stock Movement Statement
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
            'sale',
            ?,
            'order',
            ?,
            ?
        )
    ");


    /*
    |--------------------------------------------------------------------------
    | Insert Items + Reduce Stock
    |--------------------------------------------------------------------------
    */

    foreach ($items as $item) {

        $quantity =
            (int) $item["quantity"];


        $unitPrice =
            (float) $item["price"];


        $itemSubtotal =
            $unitPrice *
            $quantity;


        /*
        | Insert order item
        */

        $itemStmt->execute([

            $orderId,

            (int) $item["product_id"],

            $item["name"],

            $unitPrice,

            $quantity,

            $itemSubtotal

        ]);


        /*
        | Reduce stock
        */

        $stockStmt->execute([

            $quantity,

            (int) $item["product_id"],

            $quantity

        ]);


        /*
        | Make sure stock was actually updated
        */

        if ($stockStmt->rowCount() !== 1) {

            throw new Exception(
                "Stock update failed for " .
                $item["name"]
            );

        }


        /*
        | Record stock movement
        */

        $movementStmt->execute([

            (int) $item["product_id"],

            $quantity,

            $orderId,

            "Online order " .
            $orderNumber

        ]);

    }


    /*
    |--------------------------------------------------------------------------
    | Empty Cart
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        DELETE FROM cart_items
        WHERE cart_id = ?
    ");

    $stmt->execute([
        $cart["id"]
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        "Location: ../order-success.php?order=" .
        urlencode($orderNumber)
    );

    exit;


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    /*
    |--------------------------------------------------------------------------
    | Show Error
    |--------------------------------------------------------------------------
    */

    die(
        "Order could not be created: " .
        htmlspecialchars(
            $e->getMessage()
        )
    );

}