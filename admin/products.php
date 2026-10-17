<?php

require_once "../config/admin_auth.php";
requireAdmin();

require_once "../config/db.php";



/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");



/*
|--------------------------------------------------------------------------
| Get Products
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $stmt = $pdo->prepare("
        SELECT
            products.*,
            categories.name AS category_name
        FROM products
        LEFT JOIN categories
            ON products.category_id = categories.id
        WHERE
            products.name LIKE ?
            OR products.sku LIKE ?
            OR categories.name LIKE ?
        ORDER BY products.id DESC
    ");

    $keyword = "%" . $search . "%";

    $stmt->execute([
        $keyword,
        $keyword,
        $keyword
    ]);

} else {

    $stmt = $pdo->query("
        SELECT
            products.*,
            categories.name AS category_name
        FROM products
        LEFT JOIN categories
            ON products.category_id = categories.id
        ORDER BY products.id DESC
    ");
}



$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Products - BizPilot AI</title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!-- Main CSS -->

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
                <?= htmlspecialchars($_SESSION["user_name"] ?? "Admin") ?>

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
     MAIN CONTAINER
========================================================= -->

<div class="container py-5">


    <!-- =====================================================
         BACK BUTTON
    ====================================================== -->

    <?php require_once "../config/back-button.php"; ?>


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div
        class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

        <div>

            <h1 class="fw-bold mb-1">

                Product Management

            </h1>


            <p class="text-muted mb-0">

                Manage products, prices and inventory.

            </p>

        </div>


        <div>

            <a
                href="index.php"
                class="btn btn-outline-secondary me-2">

                Dashboard

            </a>


            <a
                href="add-product.php"
                class="btn btn-success">

                + Add Product

            </a>

        </div>

    </div>



    <!-- =====================================================
         SUCCESS / ERROR MESSAGES
    ====================================================== -->

    <?php if (isset($_GET["success"])): ?>

        <?php if ($_GET["success"] === "added"): ?>

            <div class="alert alert-success">

                ✅ Product added successfully.

            </div>

        <?php elseif ($_GET["success"] === "updated"): ?>

            <div class="alert alert-success">

                ✅ Product updated successfully.

            </div>

        <?php elseif ($_GET["success"] === "deleted"): ?>

            <div class="alert alert-success">

                ✅ Product deleted successfully.

            </div>

        <?php endif; ?>

    <?php endif; ?>


    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-danger">

            ❌
            <?= htmlspecialchars($_GET["error"]) ?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         SEARCH
    ====================================================== -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form
                method="GET"
                class="row g-2">

                <div class="col-md-10">

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search product, SKU or category..."
                        value="<?= htmlspecialchars($search) ?>">

                </div>


                <div class="col-md-2">

                    <button
                        type="submit"
                        class="btn btn-dark w-100">

                        🔍 Search

                    </button>

                </div>

            </form>

        </div>

    </div>



    <!-- =====================================================
         PRODUCT TABLE
    ====================================================== -->

    <div class="card border-0 shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table
                    class="table table-hover align-middle">


                    <!-- TABLE HEADER -->

                    <thead class="table-dark">

                        <tr>

                            <th>#</th>

                            <th>Product</th>

                            <th>Category</th>

                            <th>SKU</th>

                            <th>Cost</th>

                            <th>Price</th>

                            <th>Stock</th>

                            <th>Status</th>

                            <th>Actions</th>

                        </tr>

                    </thead>


                    <!-- TABLE BODY -->

                    <tbody>

                    <?php if (count($products) > 0): ?>

                        <?php foreach ($products as $product): ?>

                            <?php

                            /*
                            |--------------------------------------------------------------------------
                            | Safe Product Values
                            |--------------------------------------------------------------------------
                            */

                            $stock = (int) ($product["stock"] ?? 0);

                            $lowLevel = (int) (
                                $product["low_stock_level"] ?? 10
                            );

                            $costPrice = (float) (
                                $product["cost_price"] ?? 0
                            );

                            $sellingPrice = (float) (
                                $product["price"] ?? 0
                            );

                            $status = $product["status"] ?? "active";

                            ?>


                            <tr>


                                <!-- ID -->

                                <td>

                                    <?= (int) $product["id"] ?>

                                </td>


                                <!-- PRODUCT -->

                                <td>

                                    <div
                                        class="d-flex align-items-center gap-2">

                                        <?php if (!empty($product["image"])): ?>

                                            <img
                                                src="../assets/images/<?= htmlspecialchars($product["image"]) ?>"
                                                width="50"
                                                height="50"
                                                alt="<?= htmlspecialchars($product["name"]) ?>"
                                                style="object-fit:cover;"
                                                class="rounded border">

                                        <?php else: ?>

                                            <div
                                                class="bg-light rounded border d-flex align-items-center justify-content-center"
                                                style="width:50px;height:50px;">

                                                📦

                                            </div>

                                        <?php endif; ?>


                                        <div>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $product["name"]
                                                    ?? "Unnamed Product"
                                                ) ?>

                                            </strong>

                                        </div>

                                    </div>

                                </td>


                                <!-- CATEGORY -->

                                <td>

                                    <?= htmlspecialchars(
                                        $product["category_name"]
                                        ?? "Uncategorized"
                                    ) ?>

                                </td>


                                <!-- SKU -->

                                <td>

                                    <?= htmlspecialchars(
                                        $product["sku"] ?? "-"
                                    ) ?>

                                </td>


                                <!-- COST -->

                                <td>

                                    Rs.
                                    <?= number_format(
                                        $costPrice,
                                        2
                                    ) ?>

                                </td>


                                <!-- SELLING PRICE -->

                                <td>

                                    Rs.
                                    <?= number_format(
                                        $sellingPrice,
                                        2
                                    ) ?>

                                </td>


                                <!-- STOCK -->

                                <td>

                                    <?php if ($stock <= 0): ?>

                                        <span
                                            class="badge bg-danger">

                                            Out of Stock

                                        </span>

                                    <?php elseif ($stock <= $lowLevel): ?>

                                        <span
                                            class="badge bg-warning text-dark">

                                            <?= $stock ?>

                                            Low

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge bg-success">

                                            <?= $stock ?>

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php if ($status === "active"): ?>

                                        <span
                                            class="badge bg-success">

                                            Active

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge bg-secondary">

                                            Inactive

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div
                                        class="d-flex gap-1">

                                        <!-- EDIT -->

                                        <a
                                            href="edit-product.php?id=<?= (int) $product["id"] ?>"
                                            class="btn btn-sm btn-primary">

                                            Edit

                                        </a>


                                        <!-- DELETE -->

                                        <a
                                            href="delete-product.php?id=<?= (int) $product["id"] ?>"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('Are you sure you want to deactivate this product?');">

                                            Delete

                                        </a>

                                    </div>

                                </td>


                            </tr>

                        <?php endforeach; ?>


                    <?php else: ?>

                        <!-- NO PRODUCTS -->

                        <tr>

                            <td
                                colspan="9"
                                class="text-center py-5">

                                <div class="fs-1">

                                    📦

                                </div>


                                <h5>

                                    No products found

                                </h5>


                                <p class="text-muted">

                                    Try another search or add a new product.

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