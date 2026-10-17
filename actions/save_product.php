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
| Get form data
|--------------------------------------------------------------------------
*/

$name = trim($_POST["name"] ?? "");

$categoryId = (int) ($_POST["category_id"] ?? 0);

$sku = trim($_POST["sku"] ?? "");

$costPrice = (float) ($_POST["cost_price"] ?? 0);

$price = (float) ($_POST["price"] ?? 0);

$stock = (int) ($_POST["stock"] ?? 0);

$lowStockLevel = (int) ($_POST["low_stock_level"] ?? 5);

$description = trim($_POST["description"] ?? "");


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($name === "") {

    die("Product name is required.");

}

if ($categoryId <= 0) {

    die("Please select a category.");

}

if ($price < 0 || $costPrice < 0) {

    die("Price cannot be negative.");

}

if ($stock < 0) {

    die("Stock cannot be negative.");

}

if ($lowStockLevel < 0) {

    die("Low stock level cannot be negative.");

}


/*
|--------------------------------------------------------------------------
| Check category
|--------------------------------------------------------------------------
*/

$categoryStmt = $pdo->prepare("
    SELECT id
    FROM categories
    WHERE id = ?
");

$categoryStmt->execute([
    $categoryId
]);

if (!$categoryStmt->fetch()) {

    die("Invalid category.");

}


/*
|--------------------------------------------------------------------------
| Check SKU
|--------------------------------------------------------------------------
*/

if ($sku !== "") {

    $skuStmt = $pdo->prepare("
        SELECT id
        FROM products
        WHERE sku = ?
        LIMIT 1
    ");

    $skuStmt->execute([
        $sku
    ]);

    if ($skuStmt->fetch()) {

        die("SKU already exists.");

    }

}


/*
|--------------------------------------------------------------------------
| Handle image
|--------------------------------------------------------------------------
*/

$imageName = null;

if (
    isset($_FILES["image"]) &&
    $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
) {

    if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {

        die("Image upload failed.");

    }


    $allowedTypes = [
        "image/jpeg",
        "image/png",
        "image/webp"
    ];

    $fileType = mime_content_type(
        $_FILES["image"]["tmp_name"]
    );

    if (!in_array($fileType, $allowedTypes, true)) {

        die("Only JPG, PNG and WEBP images are allowed.");

    }


    if ($_FILES["image"]["size"] > 5 * 1024 * 1024) {

        die("Image must be smaller than 5MB.");

    }


    $extension = strtolower(
        pathinfo(
            $_FILES["image"]["name"],
            PATHINFO_EXTENSION
        )
    );


    $imageName =
        uniqid("product_", true)
        . "."
        . $extension;


    $uploadDirectory =
        dirname(__DIR__)
        . "/assets/images/";


    if (!is_dir($uploadDirectory)) {

        mkdir(
            $uploadDirectory,
            0755,
            true
        );

    }


    $uploadPath =
        $uploadDirectory
        . $imageName;


    if (
        !move_uploaded_file(
            $_FILES["image"]["tmp_name"],
            $uploadPath
        )
    ) {

        die("Unable to save product image.");

    }

}


/*
|--------------------------------------------------------------------------
| Insert product
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        INSERT INTO products
        (
            name,
            category_id,
            sku,
            cost_price,
            price,
            stock,
            low_stock_level,
            description,
            image,
            status
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
            ?,
            'active'
        )
    ");


    $stmt->execute([
        $name,
        $categoryId,
        $sku !== "" ? $sku : null,
        $costPrice,
        $price,
        $stock,
        $lowStockLevel,
        $description,
        $imageName
    ]);


    header(
        "Location: ../admin/products.php?success=added"
    );

    exit;


} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | Delete uploaded image if DB insert fails
    |--------------------------------------------------------------------------
    */

    if ($imageName !== null) {

        $imagePath =
            dirname(__DIR__)
            . "/assets/images/"
            . $imageName;

        if (file_exists($imagePath)) {

            unlink($imagePath);

        }

    }


    die(
        "Unable to save product: "
        . htmlspecialchars($e->getMessage())
    );

}