<?php

require_once "../config/admin_auth.php";
requireAdmin();

require_once "../config/db.php";

$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.created_at,
            COUNT(DISTINCT o.id) AS order_count,
            COALESCE(
                SUM(
                    CASE 
                        WHEN o.order_status = 'completed'
                        THEN o.total
                        ELSE 0
                    END
                ), 0
            ) AS total_spent
        FROM users u
        LEFT JOIN orders o
            ON u.id = o.user_id
        WHERE 
            u.role = 'customer'
            AND (
                u.full_name LIKE ?
                OR u.email LIKE ?
                OR u.phone LIKE ?
            )
        GROUP BY 
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.created_at
        ORDER BY u.id DESC
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
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.created_at,
            COUNT(DISTINCT o.id) AS order_count,
            COALESCE(
                SUM(
                    CASE 
                        WHEN o.order_status = 'completed'
                        THEN o.total
                        ELSE 0
                    END
                ), 0
            ) AS total_spent
        FROM users u
        LEFT JOIN orders o
            ON u.id = o.user_id
        WHERE u.role = 'customer'
        GROUP BY 
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.created_at
        ORDER BY u.id DESC
    ");
}

$customers = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customers - BizPilot AI</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>

<body class="bg-light">

<nav class="navbar navbar-expand-lg bg-dark navbar-dark">

    <div class="container-fluid">

        <a class="navbar-brand fw-bold" href="index.php">
            BizPilot AI
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#adminNav"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div
            class="collapse navbar-collapse"
            id="adminNav"
        >

            <ul class="navbar-nav ms-auto">

                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="products.php">
                        Products
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="categories.php">
                        Categories
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="orders.php">
                        Orders
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link active" href="customers.php">
                        Customers
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        Reports
                    </a>
                </li>

            </ul>

        </div>

    </div>

</nav>


<div class="container-fluid py-4">

    <?php require_once "../config/back-button.php"; ?>


    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Customers
            </h2>

            <p class="text-muted mb-0">
                Manage and view customer information
            </p>

        </div>

        <a
            href="index.php"
            class="btn btn-outline-dark"
        >
            ← Dashboard
        </a>

    </div>


    <!-- Search -->

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-body">

            <form method="GET">

                <div class="row g-2">

                    <div class="col-md-10">

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Search by name, email or phone..."
                            value="<?= htmlspecialchars($search) ?>"
                        >

                    </div>

                    <div class="col-md-2">

                        <button
                            type="submit"
                            class="btn btn-dark w-100"
                        >
                            Search
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- Customer count -->

    <div class="mb-3">

        <strong>
            <?= count($customers) ?>
        </strong>

        customer(s) found.

    </div>


    <!-- Customers table -->

    <div class="card shadow-sm border-0">

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-dark">

                        <tr>

                            <th>ID</th>

                            <th>Customer</th>

                            <th>Email</th>

                            <th>Phone</th>

                            <th>Orders</th>

                            <th>Total Spent</th>

                            <th>Registered</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($customers)): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="text-center py-5 text-muted"
                            >
                                No customers found.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($customers as $customer): ?>

                            <tr>

                                <td>
                                    #<?= (int) $customer["id"] ?>
                                </td>

                                <td>

                                    <div class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $customer["full_name"]
                                        ) ?>

                                    </div>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $customer["email"]
                                    ) ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $customer["phone"] ?? "-"
                                    ) ?>

                                </td>

                                <td>

                                    <span class="badge bg-primary">

                                        <?= (int) $customer["order_count"] ?>

                                    </span>

                                </td>

                                <td>

                                    <strong>

                                        Rs.
                                        <?= number_format(
                                            (float) $customer["total_spent"],
                                            2
                                        ) ?>

                                    </strong>

                                </td>

                                <td>

                                    <?= date(
                                        "Y-m-d",
                                        strtotime(
                                            $customer["created_at"]
                                        )
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>