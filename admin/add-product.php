<?php

require_once "../config/admin_auth.php";
requireAdmin();

require_once "../config/db.php";

$categories = $pdo->query("
    SELECT id, name
    FROM categories
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Add Product - BizPilot AI</title>

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

        <a
            href="../logout.php"
            class="btn btn-outline-light btn-sm">

            Logout

        </a>

    </div>

</nav>


<!-- =========================================================
     MAIN CONTAINER
========================================================= -->

<div class="container py-5">

    <!-- BACK BUTTON -->

    <?php require_once "../config/back-button.php"; ?>


    <div class="row justify-content-center">

        <div class="col-lg-8">

            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">


                    <!-- HEADER -->

                    <h2 class="fw-bold mb-1">

                        Add New Product

                    </h2>

                    <p class="text-muted mb-4">

                        Add a new product to the store inventory.

                    </p>


                    <!-- =================================================
                         FORM
                    ================================================== -->

                    <form
                        action="../actions/save_product.php"
                        method="POST"
                        enctype="multipart/form-data">


                        <!-- PRODUCT NAME -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">

                                Product Name

                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                required>

                        </div>


                        <!-- CATEGORY -->

                        <div class="mb-3">

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
                                        value="<?= (int) $category["id"] ?>">

                                        <?= htmlspecialchars(
                                            $category["name"]
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- SKU -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">

                                SKU

                            </label>

                            <input
                                type="text"
                                name="sku"
                                class="form-control"
                                placeholder="Example: SOAP-001">

                        </div>


                        <!-- PRICE ROW -->

                        <div class="row">


                            <!-- COST -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label fw-semibold">

                                    Cost Price

                                </label>

                                <input
                                    type="number"
                                    name="cost_price"
                                    class="form-control"
                                    min="0"
                                    step="0.01"
                                    value="0"
                                    required>

                            </div>


                            <!-- SELLING PRICE -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label fw-semibold">

                                    Selling Price

                                </label>

                                <input
                                    type="number"
                                    name="price"
                                    class="form-control"
                                    min="0"
                                    step="0.01"
                                    required>

                            </div>

                        </div>


                        <!-- STOCK ROW -->

                        <div class="row">


                            <!-- STOCK -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label fw-semibold">

                                    Initial Stock

                                </label>

                                <input
                                    type="number"
                                    name="stock"
                                    class="form-control"
                                    min="0"
                                    value="0"
                                    required>

                            </div>


                            <!-- LOW STOCK -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label fw-semibold">

                                    Low Stock Alert Level

                                </label>

                                <input
                                    type="number"
                                    name="low_stock_level"
                                    class="form-control"
                                    min="0"
                                    value="5"
                                    required>

                            </div>

                        </div>


                        <!-- DESCRIPTION -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">

                                Description

                            </label>

                            <textarea
                                name="description"
                                class="form-control"
                                rows="4"></textarea>

                        </div>


                        <!-- IMAGE -->

                        <div class="mb-4">

                            <label class="form-label fw-semibold">

                                Product Image

                            </label>

                            <input
                                type="file"
                                name="image"
                                class="form-control"
                                accept="image/jpeg,image/png,image/webp">

                            <small class="text-muted">

                                JPG, PNG or WEBP

                            </small>

                        </div>


                        <!-- BUTTONS -->

                        <div class="d-flex gap-2">

                            <a
                                href="products.php"
                                class="btn btn-outline-secondary">

                                Cancel

                            </a>

                            <button
                                type="submit"
                                class="btn btn-success">

                                Save Product

                            </button>

                        </div>


                    </form>

                </div>

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