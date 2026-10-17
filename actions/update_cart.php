<?php

require_once "../config/db.php";
require_once "../config/auth.php";

requireLogin();

$cartItemId = (int) ($_POST["cart_item_id"] ?? 0);

$quantity = (int) ($_POST["quantity"] ?? 1);

if ($cartItemId <= 0) {
    header("Location: ../cart.php");
    exit;
}

if ($quantity < 1) {
    $quantity = 1;
}

/*
|--------------------------------------------------------------------------
| Get Cart Item + Current Stock
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        cart_items.id,
        products.stock
    FROM cart_items
    INNER JOIN products
        ON products.id = cart_items.product_id
    INNER JOIN cart
        ON cart.id = cart_items.cart_id
    WHERE cart_items.id = ?
      AND cart.user_id = ?
    LIMIT 1
");

$stmt->execute([
    $cartItemId,
    $_SESSION["user_id"]
]);

$item = $stmt->fetch();

if (!$item) {

    header("Location: ../cart.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Stock Validation
|--------------------------------------------------------------------------
*/

$stock = (int) $item["stock"];

if ($stock <= 0) {

    header("Location: ../cart.php");
    exit;
}

if ($quantity > $stock) {
    $quantity = $stock;
}

/*
|--------------------------------------------------------------------------
| Update Cart Quantity
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE cart_items
    SET quantity = ?
    WHERE id = ?
");

$stmt->execute([
    $quantity,
    $cartItemId
]);

header("Location: ../cart.php");
exit;