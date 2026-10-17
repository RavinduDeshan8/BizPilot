<?php

require_once "../config/db.php";
require_once "../config/admin_auth.php";

requireAdmin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../admin/products.php");
    exit;
}


/* =========================
   GET FORM DATA
========================= */

$id = isset($_POST["id"]) ? (int) $_POST["id"] : 0;

$name = trim($_POST["name"] ?? "");

$categoryId = isset($_POST["category_id"])
    ? (int) $_POST["category_id"]
    : 0;

$sku = trim($_POST["sku"] ?? "");

$costPrice = isset($_POST["cost_price"])
    ? (float) $_POST["cost_price"]
    : 0;

$price = isset($_POST["price"])
    ? (float) $_POST["price"]
    : 0;

$stock = isset($_POST["stock"])
    ? (int) $_POST["stock"]
    : 0;

$lowStockLevel = isset($_POST["low_stock_level"])
    ? (int) $_POST["low_stock_level"]
    : 10;

$status = $_POST["status"] ?? "active";

$description = trim($_POST["description"] ?? "");


/* =========================
   BASIC VALIDATION
========================= */

if ($id <= 0) {
    header("Location: ../admin/products.php?error=Invalid product");
    exit;
}

if ($name === "") {
    header("Location: ../admin/edit-product.php?id=$id&error=Product name is required");
    exit;
}

if ($categoryId <= 0) {
    header("Location: ../admin/edit-product.php?id=$id&error=Please select a category");
    exit;
}

if ($costPrice < 0 || $price < 0) {
    header("Location: ../admin/edit-product.php?id=$id&error=Price cannot be negative");
    exit;
}

if ($stock < 0) {
    header("Location: ../admin/edit-product.php?id=$id&error=Stock cannot be negative");
    exit;
}

if ($lowStockLevel < 0) {
    header("Location: ../admin/edit-product.php?id=$id&error=Low stock level cannot be negative");
    exit;
}

if (!in_array($status, ["active", "inactive"])) {
    $status = "active";
}


/* =========================
   CHECK PRODUCT
========================= */

$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: ../admin/products.php?error=Product not found");
    exit;
}


/* =========================
   CHECK CATEGORY
========================= */

$stmt = $pdo->prepare("
    SELECT id
    FROM categories
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$categoryId]);

if (!$stmt->fetch()) {
    header("Location: ../admin/edit-product.php?id=$id&error=Invalid category");
    exit;
}


/* =========================
   CHECK SKU
========================= */

if ($sku !== "") {

    $stmt = $pdo->prepare("
        SELECT id
        FROM products
        WHERE sku = ?
        AND id != ?
        LIMIT 1
    ");

    $stmt->execute([
        $sku,
        $id
    ]);

    if ($stmt->fetch()) {

        header(
            "Location: ../admin/edit-product.php?id=$id&error=SKU already exists"
        );

        exit;
    }
}


/* =========================
   IMAGE HANDLING
========================= */

$oldImage = $product["image"] ?? "";
$newImage = $oldImage;

$uploadedNewImage = false;


/*
   If user selected a new image
*/

if (
    isset($_FILES["image"]) &&
    $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
) {

    if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {

        header(
            "Location: ../admin/edit-product.php?id=$id&error=Image upload failed"
        );

        exit;
    }


    /* Maximum 5MB */

    if ($_FILES["image"]["size"] > 5 * 1024 * 1024) {

        header(
            "Location: ../admin/edit-product.php?id=$id&error=Image must be less than 5MB"
        );

        exit;
    }


    /* Detect MIME type */

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mimeType = $finfo->file($_FILES["image"]["tmp_name"]);


    $allowedTypes = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp"
    ];


    if (!isset($allowedTypes[$mimeType])) {

        header(
            "Location: ../admin/edit-product.php?id=$id&error=Only JPG, PNG and WEBP images are allowed"
        );

        exit;
    }


    $extension = $allowedTypes[$mimeType];

    $newImage = uniqid("product_", true) . "." . $extension;

    $uploadDirectory = __DIR__ . "/../assets/images/";


    if (!is_dir($uploadDirectory)) {
        mkdir($uploadDirectory, 0755, true);
    }


    $destination = $uploadDirectory . $newImage;


    if (
        !move_uploaded_file(
            $_FILES["image"]["tmp_name"],
            $destination
        )
    ) {

        header(
            "Location: ../admin/edit-product.php?id=$id&error=Could not save image"
        );

        exit;
    }

    $uploadedNewImage = true;
}


/* =========================
   UPDATE DATABASE
========================= */

try {

    $stmt = $pdo->prepare("
        UPDATE products
        SET
            name = ?,
            category_id = ?,
            sku = ?,
            cost_price = ?,
            price = ?,
            stock = ?,
            low_stock_level = ?,
            description = ?,
            image = ?,
            status = ?
        WHERE id = ?
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
        $newImage,
        $status,
        $id
    ]);


    /* =========================
       DELETE OLD IMAGE
    ========================= */

    if (
        $uploadedNewImage &&
        !empty($oldImage) &&
        $oldImage !== $newImage
    ) {

        $oldImagePath =
            __DIR__ . "/../assets/images/" . $oldImage;

        if (file_exists($oldImagePath)) {
            unlink($oldImagePath);
        }
    }


    header(
        "Location: ../admin/products.php?success=updated"
    );

    exit;


} catch (Exception $e) {

    /*
       If database update fails,
       remove newly uploaded image.
    */

    if (
        $uploadedNewImage &&
        !empty($newImage)
    ) {

        $newImagePath =
            __DIR__ . "/../assets/images/" . $newImage;

        if (file_exists($newImagePath)) {
            unlink($newImagePath);
        }
    }


    header(
        "Location: ../admin/edit-product.php?id=$id&error=Could not update product"
    );

    exit;
}