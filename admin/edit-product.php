<?php

require_once "../config/db.php";
require_once "../config/admin_auth.php";

requireAdmin();

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: products.php");
    exit;
}

$productId = (int) $_GET["id"];


/* Get product */

$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$productId]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php?error=notfound");
    exit;
}


/* Get categories */

$stmt = $pdo->query("
    SELECT id, name
    FROM categories
    ORDER BY name ASC
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Edit Product - BizPilot AI</title>

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

<nav class="navbar navbar-expand-lg bg-dark navbar-dark">

    <div class="container">

        <a
            class="navbar-brand fw-bold"
            href="index.php">

            BizPilot AI

        </a>


        <div class="d-flex align-items-center">

            <a
                href="index.php"
                class="btn btn-outline-light btn-sm me-2">

                Dashboard

            </a>


            <a
                href="products.php"
                class="btn btn-success btn-sm">

                Products

            </a>

        </div>

    </div>

</nav>



<!-- =========================================================
     MAIN
========================================================= -->

<div class="container py-5">


    <!-- PREVIOUS PAGE BUTTON -->

    <?php require_once "../config/back-button.php"; ?>


    <!-- HEADER -->

    <div
        class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

        <div>

            <h2 class="fw-bold mb-1">

                Edit Product

            </h2>

            <p class="text-muted mb-0">

                Update product information

            </p>

        </div>


        <a
            href="products.php"
            class="btn btn-secondary">

            ← Back to Products

        </a>

    </div>



    <!-- ERROR MESSAGE -->

    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars($_GET["error"]) ?>

        </div>

    <?php endif; ?>



    <!-- PRODUCT FORM -->

    <div class="card shadow-sm border-0">

        <div class="card-body p-4">

            <form
                action="../actions/update_product.php"
                method="POST"
                enctype="multipart/form-data">


                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $product["id"] ?>">


                <div class="row g-4">


                    <!-- PRODUCT NAME -->

                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            Product Name

                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            required
                            value="<?= htmlspecialchars(
                                $product["name"] ?? ""
                            ) ?>">

                    </div>



                    <!-- CATEGORY -->

                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            Category

                        </label>

                        <select
                            name="category_id"
                            class="form-select"
                            required>

                            <option value="">

                                Select Category

                            </option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= (int) $category["id"] ?>"
                                    <?= (
                                        $product["category_id"]
                                        == $category["id"]
                                    )
                                        ? "selected"
                                        : "" ?>>

                                    <?= htmlspecialchars(
                                        $category["name"]
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>



                    <!-- SKU -->

                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            SKU

                        </label>

                        <input
                            type="text"
                            name="sku"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $product["sku"] ?? ""
                            ) ?>">

                        <small class="text-muted">

                            Example: SOAP-001

                        </small>

                    </div>



                    <!-- COST PRICE -->

                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            Cost Price (Rs.)

                        </label>

                        <input
                            type="number"
                            name="cost_price"
                            class="form-control"
                            min="0"
                            step="0.01"
                            required
                            value="<?= htmlspecialchars(
                                $product["cost_price"] ?? "0"
                            ) ?>">

                    </div>



                    <!-- SELLING PRICE -->

                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            Selling Price (Rs.)

                        </label>

                        <input
                            type="number"
                            name="price"
                            class="form-control"
                            min="0"
                            step="0.01"
                            required
                            value="<?= htmlspecialchars(
                                $product["price"] ?? "0"
                            ) ?>">

                    </div>



                    <!-- STOCK -->

                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            Current Stock

                        </label>

                        <input
                            type="number"
                            name="stock"
                            class="form-control"
                            min="0"
                            required
                            value="<?= htmlspecialchars(
                                $product["stock"] ?? "0"
                            ) ?>">

                    </div>



                    <!-- LOW STOCK LEVEL -->

                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            Low Stock Alert Level

                        </label>

                        <input
                            type="number"
                            name="low_stock_level"
                            class="form-control"
                            min="0"
                            required
                            value="<?= htmlspecialchars(
                                $product["low_stock_level"] ?? "10"
                            ) ?>">

                    </div>



                    <!-- STATUS -->

                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            Product Status

                        </label>

                        <select
                            name="status"
                            class="form-select"
                            required>

                            <option
                                value="active"
                                <?= (
                                    ($product["status"] ?? "active")
                                    === "active"
                                )
                                    ? "selected"
                                    : "" ?>>

                                Active

                            </option>

                            <option
                                value="inactive"
                                <?= (
                                    ($product["status"] ?? "")
                                    === "inactive"
                                )
                                    ? "selected"
                                    : "" ?>>

                                Inactive

                            </option>

                        </select>

                    </div>



                    <!-- DESCRIPTION -->

                    <div class="col-12">

                        <label class="form-label fw-semibold">

                            Description

                        </label>

                        <textarea
                            name="description"
                            class="form-control"
                            rows="5"><?= htmlspecialchars(
                                $product["description"] ?? ""
                            ) ?></textarea>

                    </div>



                    <!-- CURRENT IMAGE -->

                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            Current Image

                        </label>

                        <?php if (!empty($product["image"])): ?>

                            <div class="mb-2">

                                <img
                                    src="../assets/images/<?= htmlspecialchars(
                                        $product["image"]
                                    ) ?>"
                                    alt="Product Image"
                                    style="
                                        width: 150px;
                                        height: 150px;
                                        object-fit: cover;
                                        border-radius: 10px;
                                    "
                                    class="border">

                            </div>

                        <?php else: ?>

                            <p class="text-muted">

                                No image uploaded.

                            </p>

                        <?php endif; ?>

                    </div>



                    <!-- NEW IMAGE -->

                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            Change Product Image

                        </label>

                        <input
                            type="file"
                            name="image"
                            class="form-control"
                            accept=".jpg,.jpeg,.png,.webp">

                        <small class="text-muted">

                            JPG, PNG or WEBP. Maximum 5MB.

                        </small>

                    </div>



                    <!-- BUTTONS -->

                    <div class="col-12">

                        <hr>

                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-success px-4">

                                💾 Update Product

                            </button>


                            <a
                                href="products.php"
                                class="btn btn-secondary px-4">

                                Cancel

                            </a>

                        </div>

                    </div>


                </div>

            </form>

        </div>

    </div>


</div>



<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>