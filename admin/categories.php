<?php

require_once "../config/admin_auth.php";
requireAdmin();

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| Add / Update / Delete Category
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | CREATE CATEGORY
    |--------------------------------------------------------------------------
    */

    if ($action === "add") {

        $name = trim($_POST["name"] ?? "");

        if ($name === "") {
            header("Location: categories.php?error=Category name is required");
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE name = ?
            LIMIT 1
        ");

        $stmt->execute([$name]);

        if ($stmt->fetch()) {
            header("Location: categories.php?error=Category already exists");
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO categories (name)
            VALUES (?)
        ");

        $stmt->execute([$name]);

        header("Location: categories.php?success=added");
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE CATEGORY
    |--------------------------------------------------------------------------
    */

    if ($action === "update") {

        $id = isset($_POST["id"])
            ? (int) $_POST["id"]
            : 0;

        $name = trim($_POST["name"] ?? "");

        if ($id <= 0 || $name === "") {

            header(
                "Location: categories.php?error=Invalid category information"
            );

            exit;
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE name = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->execute([
            $name,
            $id
        ]);

        if ($stmt->fetch()) {

            header(
                "Location: categories.php?error=Category name already exists"
            );

            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE categories
            SET name = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $name,
            $id
        ]);

        header("Location: categories.php?success=updated");
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE CATEGORY
    |--------------------------------------------------------------------------
    */

    if ($action === "delete") {

        $id = isset($_POST["id"])
            ? (int) $_POST["id"]
            : 0;

        if ($id <= 0) {

            header(
                "Location: categories.php?error=Invalid category"
            );

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Check whether products use this category
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM products
            WHERE category_id = ?
        ");

        $stmt->execute([$id]);

        $productCount = (int) $stmt->fetchColumn();

        if ($productCount > 0) {

            header(
                "Location: categories.php?error=This category has products. Move or remove those products first."
            );

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Delete category
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            DELETE FROM categories
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        header("Location: categories.php?success=deleted");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Get Categories
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        categories.id,
        categories.name,
        COUNT(products.id) AS product_count
    FROM categories
    LEFT JOIN products
        ON products.category_id = categories.id
    GROUP BY
        categories.id,
        categories.name
    ORDER BY categories.name ASC
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

    <title>Categories - BizPilot AI</title>

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

        <div>

            <span class="text-white me-3">

                👤
                <?= htmlspecialchars(
                    $_SESSION["user_name"] ?? "Admin"
                ) ?>

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
     MAIN
========================================================= -->

<div class="container py-5">

    <!-- Previous Page -->

    <?php require_once "../config/back-button.php"; ?>

    <!-- HEADER -->

    <div
        class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

        <div>

            <h1 class="fw-bold mb-1">
                Category Management
            </h1>

            <p class="text-muted mb-0">
                Manage your store product categories.
            </p>

        </div>

        <div>

            <a
                href="index.php"
                class="btn btn-outline-secondary">

                ← Dashboard

            </a>

        </div>

    </div>

    <!-- =====================================================
         ALERTS
    ====================================================== -->

    <?php if (isset($_GET["success"])): ?>

        <?php if ($_GET["success"] === "added"): ?>

            <div class="alert alert-success">
                ✅ Category added successfully.
            </div>

        <?php elseif ($_GET["success"] === "updated"): ?>

            <div class="alert alert-success">
                ✅ Category updated successfully.
            </div>

        <?php elseif ($_GET["success"] === "deleted"): ?>

            <div class="alert alert-success">
                ✅ Category deleted successfully.
            </div>

        <?php endif; ?>

    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-danger">

            ❌
            <?= htmlspecialchars($_GET["error"]) ?>

        </div>

    <?php endif; ?>

    <div class="row g-4">

        <!-- =================================================
             ADD CATEGORY
        ================================================== -->

        <div class="col-lg-4">

            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">

                    <h4 class="fw-bold mb-3">
                        + Add Category
                    </h4>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="action"
                            value="add">

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Category Name
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                placeholder="Example: Electronics"
                                required>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-success w-100">

                            Add Category

                        </button>

                    </form>

                </div>

            </div>

        </div>

        <!-- =================================================
             CATEGORY LIST
        ================================================== -->

        <div class="col-lg-8">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <h4 class="fw-bold mb-4">
                        All Categories
                    </h4>

                    <div class="table-responsive">

                        <table
                            class="table table-hover align-middle">

                            <thead class="table-dark">

                                <tr>

                                    <th>#</th>
                                    <th>Category</th>
                                    <th>Products</th>
                                    <th>Actions</th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php if (count($categories) > 0): ?>

                                <?php foreach ($categories as $category): ?>

                                    <tr>

                                        <!-- ID -->

                                        <td>
                                            <?= (int) $category["id"] ?>
                                        </td>

                                        <!-- CATEGORY NAME -->

                                        <td>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $category["name"]
                                                ) ?>
                                            </strong>

                                        </td>

                                        <!-- PRODUCT COUNT -->

                                        <td>

                                            <span
                                                class="badge bg-primary">

                                                <?= (int) $category["product_count"] ?>

                                            </span>

                                        </td>

                                        <!-- ACTIONS -->

                                        <td>

                                            <div
                                                class="d-flex gap-1">

                                                <!-- EDIT -->

                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editModal<?= (int) $category["id"] ?>">

                                                    Edit

                                                </button>

                                                <!-- DELETE -->

                                                <?php if (
                                                    (int) $category["product_count"] === 0
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        class="d-inline"
                                                        onsubmit="return confirm('Delete this category?');">

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="delete">

                                                        <input
                                                            type="hidden"
                                                            name="id"
                                                            value="<?= (int) $category["id"] ?>">

                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-danger">

                                                            Delete

                                                        </button>

                                                    </form>

                                                <?php else: ?>

                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-secondary"
                                                        disabled
                                                        title="This category has products">

                                                        Delete

                                                    </button>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>

                                    <!-- =================================================
                                         EDIT MODAL
                                    ================================================== -->

                                    <div
                                        class="modal fade"
                                        id="editModal<?= (int) $category["id"] ?>"
                                        tabindex="-1">

                                        <div class="modal-dialog">

                                            <div class="modal-content">

                                                <form method="POST">

                                                    <div class="modal-header">

                                                        <h5 class="modal-title">
                                                            Edit Category
                                                        </h5>

                                                        <button
                                                            type="button"
                                                            class="btn-close"
                                                            data-bs-dismiss="modal">
                                                        </button>

                                                    </div>

                                                    <div class="modal-body">

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="update">

                                                        <input
                                                            type="hidden"
                                                            name="id"
                                                            value="<?= (int) $category["id"] ?>">

                                                        <label
                                                            class="form-label fw-semibold">

                                                            Category Name

                                                        </label>

                                                        <input
                                                            type="text"
                                                            name="name"
                                                            class="form-control"
                                                            value="<?= htmlspecialchars($category["name"]) ?>"
                                                            required>

                                                    </div>

                                                    <div class="modal-footer">

                                                        <button
                                                            type="button"
                                                            class="btn btn-secondary"
                                                            data-bs-dismiss="modal">

                                                            Cancel

                                                        </button>

                                                        <button
                                                            type="submit"
                                                            class="btn btn-success">

                                                            Save Changes

                                                        </button>

                                                    </div>

                                                </form>

                                            </div>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td
                                        colspan="4"
                                        class="text-center py-5">

                                        <div class="fs-1">
                                            📂
                                        </div>

                                        <h5>
                                            No categories found
                                        </h5>

                                    </td>

                                </tr>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

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