<?php

require_once "../config/admin_auth.php";
requireAdmin();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| Validate Product ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    header("Location: products.php?error=Invalid product ID");
    exit;
}

$productId = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| Get Product
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

    header("Location: products.php?error=Product not found");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Delete Product - BizPilot AI</title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <link
        rel="stylesheet"
        href="../assets/css/style.css">

</head>


<body class="bg-light">


<!-- NAVBAR -->

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


<!-- MAIN -->

<div class="container py-5">


    <div class="row justify-content-center">

        <div class="col-md-6">


            <div class="card border-0 shadow-sm">


                <div class="card-body p-5 text-center">


                    <div class="display-1 mb-3">

                        ⚠️

                    </div>


                    <h2 class="fw-bold">

                        Deactivate Product?

                    </h2>


                    <p class="text-muted">

                        You are about to deactivate:

                    </p>


                    <h4 class="mb-4">

                        <?= htmlspecialchars($product["name"]) ?>

                    </h4>


                    <?php if ($product["status"] === "inactive"): ?>

                        <div class="alert alert-warning">

                            This product is already inactive.

                        </div>

                    <?php else: ?>

                        <div class="alert alert-info text-start">

                            <strong>What will happen?</strong>

                            <ul class="mb-0 mt-2">

                                <li>
                                    Product will be marked as inactive.
                                </li>

                                <li>
                                    It will not appear in the customer shop.
                                </li>

                                <li>
                                    Existing order records will remain safe.
                                </li>

                                <li>
                                    You can activate it again later.
                                </li>

                            </ul>

                        </div>

                    <?php endif; ?>


                    <form
                        action="../actions/delete_product.php"
                        method="POST">


                        <input
                            type="hidden"
                            name="id"
                            value="<?= $product["id"] ?>">


                        <div class="d-flex justify-content-center gap-2 mt-4">


                            <a
                                href="products.php"
                                class="btn btn-secondary px-4">

                                Cancel

                            </a>


                            <?php if ($product["status"] !== "inactive"): ?>

                                <button
                                    type="submit"
                                    class="btn btn-danger px-4">

                                    🗑️ Deactivate Product

                                </button>

                            <?php endif; ?>


                        </div>


                    </form>


                </div>

            </div>


        </div>

    </div>


</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>