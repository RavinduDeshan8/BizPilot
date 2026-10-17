<?php

require_once "../config/admin_auth.php";
requireAdmin();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| Only POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: ../admin/products.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Product ID
|--------------------------------------------------------------------------
*/

$productId = isset($_POST["id"])
    ? (int) $_POST["id"]
    : 0;


if ($productId <= 0) {

    header(
        "Location: ../admin/products.php?error=Invalid product ID"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Check Product
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name, image, status
    FROM products
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$productId]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$product) {

    header(
        "Location: ../admin/products.php?error=Product not found"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Deactivate Product
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        UPDATE products
        SET status = 'inactive'
        WHERE id = ?
    ");

    $stmt->execute([$productId]);


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        "Location: ../admin/products.php?success=deleted"
    );

    exit;


} catch (PDOException $e) {

    header(
        "Location: ../admin/products.php?error=Could not deactivate product"
    );

    exit;
}