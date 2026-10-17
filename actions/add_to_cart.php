<?php

require_once "../config/db.php";
require_once "../config/auth.php";

if (!isLoggedIn()) {

    header("Location: ../login.php");
    exit;
}

$productId = intval($_POST["product_id"] ?? 0);
$quantity = intval($_POST["quantity"] ?? 1);

if ($productId <= 0) {
    die("Invalid product.");
}

if ($quantity < 1) {
    $quantity = 1;
}


/*
|--------------------------------------------------------------------------
| GET PRODUCT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        price,
        stock,
        status
    FROM products
    WHERE id = ?
    AND status = 'active'
    LIMIT 1
");

$stmt->execute([$productId]);

$product = $stmt->fetch();

if (!$product) {
    die("Product not found.");
}


/*
|--------------------------------------------------------------------------
| CHECK STOCK
|--------------------------------------------------------------------------
*/

$currentStock = (int)$product["stock"];

if ($currentStock <= 0) {
    die("This product is currently out of stock.");
}

if ($quantity > $currentStock) {
    die(
        "Not enough stock available. " .
        "Available: " . $currentStock
    );
}


/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

$userId = (int)$_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| GET OR CREATE CART
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM cart
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$cart = $stmt->fetch();


if (!$cart) {

    $stmt = $pdo->prepare("
        INSERT INTO cart (user_id)
        VALUES (?)
    ");

    $stmt->execute([$userId]);

    $cartId = (int)$pdo->lastInsertId();

} else {

    $cartId = (int)$cart["id"];

}


/*
|--------------------------------------------------------------------------
| CHECK EXISTING CART ITEM
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        quantity
    FROM cart_items
    WHERE cart_id = ?
    AND product_id = ?
    LIMIT 1
");

$stmt->execute([
    $cartId,
    $productId
]);

$existing = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| UPDATE EXISTING ITEM
|--------------------------------------------------------------------------
*/

if ($existing) {

    $newQuantity =
        (int)$existing["quantity"] + $quantity;

    if ($newQuantity > $currentStock) {

        die(
            "Cannot add more than available stock. " .
            "Available: " . $currentStock
        );

    }

    $stmt = $pdo->prepare("
        UPDATE cart_items
        SET quantity = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $newQuantity,
        $existing["id"]
    ]);

}


/*
|--------------------------------------------------------------------------
| ADD NEW ITEM
|--------------------------------------------------------------------------
*/

else {

    $stmt = $pdo->prepare("
        INSERT INTO cart_items
        (
            cart_id,
            product_id,
            quantity
        )
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $cartId,
        $productId,
        $quantity
    ]);

}


/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

header("Location: ../cart.php");
exit;

?>