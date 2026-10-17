<?php

require_once "../config/admin_auth.php";
requireStaff();

require_once "../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../staff/pos.php");
    exit;
}

$cart_json = $_POST["cart"] ?? "";
$payment_method = $_POST["payment_method"] ?? "";
$cash_received = (float) ($_POST["cash_received"] ?? 0);

$allowed_payment_methods = [
    "cash",
    "card"
];

if (
    empty($cart_json) ||
    !in_array($payment_method, $allowed_payment_methods, true)
) {
    header("Location: ../staff/pos.php?error=invalid_sale");
    exit;
}

$cart = json_decode($cart_json, true);

if (!is_array($cart) || empty($cart)) {
    header("Location: ../staff/pos.php?error=empty_cart");
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
    | Validate Products + Calculate Total
    |--------------------------------------------------------------------------
    */

    $sale_items = [];

    $subtotal = 0;


    foreach ($cart as $item) {

        $product_id = (int) ($item["id"] ?? 0);
        $quantity = (int) ($item["quantity"] ?? 0);


        if ($product_id <= 0 || $quantity <= 0) {
            throw new Exception(
                "Invalid product or quantity."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Lock Product Row
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                sku,
                price,
                cost_price,
                stock,
                status
            FROM products
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $product_id
        ]);

        $product = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$product) {
            throw new Exception(
                "Product not found."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check Product Status
        |--------------------------------------------------------------------------
        */

        if ($product["status"] !== "active") {

            throw new Exception(
                $product["name"] .
                " is not active."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check Stock
        |--------------------------------------------------------------------------
        */

        $available_stock =
            (int) $product["stock"];


        if ($quantity > $available_stock) {

            throw new Exception(
                "Insufficient stock for " .
                $product["name"] .
                ". Available: " .
                $available_stock
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Calculate Item Subtotal
        |--------------------------------------------------------------------------
        */

        $unit_price =
            (float) $product["price"];

        $cost_price =
            (float) ($product["cost_price"] ?? 0);

        $item_subtotal =
            $unit_price * $quantity;


        $subtotal +=
            $item_subtotal;


        /*
        |--------------------------------------------------------------------------
        | Store Validated Item
        |--------------------------------------------------------------------------
        */

        $sale_items[] = [

            "product_id" =>
                $product_id,

            "product_name" =>
                $product["name"],

            "unit_price" =>
                $unit_price,

            "cost_price" =>
                $cost_price,

            "quantity" =>
                $quantity,

            "subtotal" =>
                $item_subtotal

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | POS Discount
    |--------------------------------------------------------------------------
    |
    | Current POS system does not send a discount value.
    | Therefore discount = 0.
    |
    */

    $discount = 0;

    $total =
        $subtotal - $discount;


    /*
    |--------------------------------------------------------------------------
    | Payment Validation
    |--------------------------------------------------------------------------
    */

    if ($payment_method === "cash") {

        if ($cash_received < $total) {

            throw new Exception(
                "Cash received is less than the total."
            );
        }

    } else {

        /*
        | Card payment does not need cash amount.
        */

        $cash_received = 0;
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Unique Invoice Number
    |--------------------------------------------------------------------------
    */

    do {

        $invoice_number =
            "POS" .
            date("YmdHis") .
            rand(100, 999);


        $check_stmt = $pdo->prepare("
            SELECT id
            FROM pos_sales
            WHERE invoice_number = ?
            LIMIT 1
        ");

        $check_stmt->execute([
            $invoice_number
        ]);

        $existing_invoice =
            $check_stmt->fetchColumn();

    } while ($existing_invoice);


    /*
    |--------------------------------------------------------------------------
    | Staff ID
    |--------------------------------------------------------------------------
    */

    $staff_id =
        (int) ($_SESSION["user_id"] ?? 0);


    if ($staff_id <= 0) {

        throw new Exception(
            "Invalid staff session."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Insert POS Sale
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO pos_sales
        (
            invoice_number,
            staff_id,
            subtotal,
            discount,
            total,
            payment_method,
            sale_status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'completed'
        )
    ");


    $stmt->execute([

        $invoice_number,

        $staff_id,

        $subtotal,

        $discount,

        $total,

        $payment_method

    ]);


    $sale_id =
        (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Insert POS Sale Items
    |--------------------------------------------------------------------------
    */

    $item_stmt = $pdo->prepare("
        INSERT INTO pos_sale_items
        (
            sale_id,
            product_id,
            product_name,
            unit_price,
            cost_price,
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
            ?,
            ?
        )
    ");


    /*
    |--------------------------------------------------------------------------
    | Decrease Product Stock
    |--------------------------------------------------------------------------
    */

    $stock_stmt = $pdo->prepare("
        UPDATE products
        SET stock = stock - ?
        WHERE id = ?
        AND stock >= ?
    ");


    /*
    |--------------------------------------------------------------------------
    | Stock Movement
    |--------------------------------------------------------------------------
    */

    $movement_stmt = $pdo->prepare("
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
            'pos_sale',
            ?,
            ?
        )
    ");


    /*
    |--------------------------------------------------------------------------
    | Process Each Sale Item
    |--------------------------------------------------------------------------
    */

    foreach ($sale_items as $item) {


        /*
        | Insert sale item
        */

        $item_stmt->execute([

            $sale_id,

            $item["product_id"],

            $item["product_name"],

            $item["unit_price"],

            $item["cost_price"],

            $item["quantity"],

            $item["subtotal"]

        ]);


        /*
        | Decrease stock
        */

        $stock_stmt->execute([

            $item["quantity"],

            $item["product_id"],

            $item["quantity"]

        ]);


        /*
        | Verify stock update
        */

        if ($stock_stmt->rowCount() !== 1) {

            throw new Exception(
                "Stock update failed for " .
                $item["product_name"]
            );
        }


        /*
        | Record stock movement
        */

        $movement_stmt->execute([

            $item["product_id"],

            $item["quantity"],

            $sale_id,

            "POS sale " .
            $invoice_number

        ]);

    }


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Calculate Change
    |--------------------------------------------------------------------------
    */

    $change =
        max(
            0,
            $cash_received - $total
        );


    /*
    |--------------------------------------------------------------------------
    | Redirect to Receipt
    |--------------------------------------------------------------------------
    */

    header(
        "Location: ../staff/pos-receipt.php" .
        "?sale=" .
        urlencode($invoice_number) .
        "&cash=" .
        urlencode(
            number_format(
                $cash_received,
                2,
                ".",
                ""
            )
        ) .
        "&change=" .
        urlencode(
            number_format(
                $change,
                2,
                ".",
                ""
            )
        )
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
    | Redirect with Error
    |--------------------------------------------------------------------------
    */

    $message =
        urlencode(
            $e->getMessage()
        );


    header(
        "Location: ../staff/pos.php?error=" .
        $message
    );

    exit;
}