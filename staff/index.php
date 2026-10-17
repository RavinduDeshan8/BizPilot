<?php

require_once "../config/admin_auth.php";

requireStaff();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Staff Dashboard - BizPilot AI</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="../assets/css/style.css">

</head>

<body>


<nav class="navbar navbar-dark bg-dark">

    <div class="container">

        <a
            href="../index.php"
            class="navbar-brand fw-bold">

            🛍️ BizPilot AI

        </a>

        <div>

            <span class="text-white me-3">

                👤
                <?= htmlspecialchars($_SESSION["user_name"]) ?>

            </span>

            <a
                href="../logout.php"
                class="btn btn-outline-light btn-sm">

                Logout

            </a>

        </div>

    </div>

</nav>


<div class="container py-5">

    <?php require_once "../config/back-button.php"; ?>


    <h1 class="fw-bold">
        Staff Dashboard
    </h1>

    <p class="text-muted">
        Manage physical sales and customer orders.
    </p>


    <div class="row g-4 mt-3">


        <div class="col-md-4">

            <a
                href="pos.php"
                class="text-decoration-none">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body">

                        <div class="fs-1">
                            🛒
                        </div>

                        <h3 class="fw-bold mt-3">
                            POS
                        </h3>

                        <p class="text-muted">

                            Process physical store sales.

                        </p>

                    </div>

                </div>

            </a>

        </div>


        <div class="col-md-4">

            <a
                href="orders.php"
                class="text-decoration-none">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body">

                        <div class="fs-1">
                            📦
                        </div>

                        <h3 class="fw-bold mt-3">
                            Orders
                        </h3>

                        <p class="text-muted">

                            Process online orders.

                        </p>

                    </div>

                </div>

            </a>

        </div>


        <div class="col-md-4">

            <a
                href="../index.php"
                class="text-decoration-none">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body">

                        <div class="fs-1">
                            🏪
                        </div>

                        <h3 class="fw-bold mt-3">
                            Store Website
                        </h3>

                        <p class="text-muted">

                            View the customer website.

                        </p>

                    </div>

                </div>

            </a>

        </div>


    </div>

</div>


</body>

</html>