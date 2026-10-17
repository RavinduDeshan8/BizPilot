<?php

require_once "../config/admin_auth.php";
requireAdmin();

require_once "../config/db.php";

$message = "";
$error = "";


/*
|--------------------------------------------------------------------------
| ADD EXPENSE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $expenseTitle = trim($_POST["expense_title"] ?? "");
    $amount = (float) ($_POST["amount"] ?? 0);
    $expenseDate = $_POST["expense_date"] ?? "";
    $description = trim($_POST["description"] ?? "");

    if ($expenseTitle === "") {

        $error = "Expense title is required.";

    } elseif ($amount <= 0) {

        $error = "Amount must be greater than 0.";

    } elseif ($expenseDate === "") {

        $error = "Expense date is required.";

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO expenses
                (
                    title,
                    amount,
                    expense_date,
                    description,
                    created_by
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $expenseTitle,
                $amount,
                $expenseDate,
                $description !== "" ? $description : null,
                $_SESSION["user_id"] ?? null
            ]);

            $message = "Expense added successfully.";

        } catch (PDOException $e) {

            $error = "Failed to add expense: " . $e->getMessage();

        }
    }
}


/*
|--------------------------------------------------------------------------
| DELETE EXPENSE
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete"])) {

    $deleteId = (int) $_GET["delete"];

    if ($deleteId > 0) {

        try {

            $stmt = $pdo->prepare("
                DELETE FROM expenses
                WHERE id = ?
            ");

            $stmt->execute([$deleteId]);

            $message = "Expense deleted successfully.";

        } catch (PDOException $e) {

            $error = "Failed to delete expense.";

        }
    }
}


/*
|--------------------------------------------------------------------------
| TOTAL EXPENSE
|--------------------------------------------------------------------------
*/

$totalExpense = (float) $pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM expenses
")->fetchColumn();


/*
|--------------------------------------------------------------------------
| THIS MONTH EXPENSE
|--------------------------------------------------------------------------
*/

$monthExpense = (float) $pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM expenses
    WHERE
        YEAR(expense_date) = YEAR(CURDATE())
        AND MONTH(expense_date) = MONTH(CURDATE())
")->fetchColumn();


/*
|--------------------------------------------------------------------------
| TODAY EXPENSE
|--------------------------------------------------------------------------
*/

$todayExpense = (float) $pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM expenses
    WHERE expense_date = CURDATE()
")->fetchColumn();


/*
|--------------------------------------------------------------------------
| EXPENSE COUNT
|--------------------------------------------------------------------------
*/

$expenseCount = (int) $pdo->query("
    SELECT COUNT(*)
    FROM expenses
")->fetchColumn();


/*
|--------------------------------------------------------------------------
| EXPENSE LIST
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        e.*,
        u.full_name AS created_by_name
    FROM expenses e
    LEFT JOIN users u
        ON e.created_by = u.id
    ORDER BY e.expense_date DESC, e.id DESC
");

$expenses = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Expenses - BizPilot AI</title>

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


<!-- NAVBAR -->

<nav class="navbar navbar-expand-lg bg-dark navbar-dark">

    <div class="container-fluid">

        <a
            class="navbar-brand fw-bold"
            href="index.php"
        >
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
                    <a
                        class="nav-link"
                        href="index.php"
                    >
                        Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        class="nav-link"
                        href="products.php"
                    >
                        Products
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        class="nav-link"
                        href="categories.php"
                    >
                        Categories
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        class="nav-link"
                        href="orders.php"
                    >
                        Orders
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        class="nav-link"
                        href="customers.php"
                    >
                        Customers
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        class="nav-link"
                        href="reports.php"
                    >
                        Reports
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        class="nav-link active"
                        href="expenses.php"
                    >
                        Expenses
                    </a>
                </li>

            </ul>

        </div>

    </div>

</nav>


<div class="container-fluid py-4">

    <?php require_once "../config/back-button.php"; ?>


    <!-- HEADER -->

    <div
        class="d-flex justify-content-between align-items-center mb-4"
    >

        <div>

            <h2 class="fw-bold mb-1">
                Expenses
            </h2>

            <p class="text-muted mb-0">
                Manage business expenses
            </p>

        </div>


        <a
            href="index.php"
            class="btn btn-outline-dark"
        >
            ← Dashboard
        </a>

    </div>


    <!-- ALERTS -->

    <?php if ($message !== ""): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- SUMMARY -->

    <div class="row g-4 mb-4">

        <div class="col-md-6 col-xl-3">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <p class="text-muted mb-2">
                        Total Expenses
                    </p>

                    <h3 class="fw-bold">

                        Rs.
                        <?= number_format(
                            $totalExpense,
                            2
                        ) ?>

                    </h3>

                </div>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <p class="text-muted mb-2">
                        This Month
                    </p>

                    <h3 class="fw-bold">

                        Rs.
                        <?= number_format(
                            $monthExpense,
                            2
                        ) ?>

                    </h3>

                </div>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <p class="text-muted mb-2">
                        Today
                    </p>

                    <h3 class="fw-bold">

                        Rs.
                        <?= number_format(
                            $todayExpense,
                            2
                        ) ?>

                    </h3>

                </div>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <p class="text-muted mb-2">
                        Expense Records
                    </p>

                    <h3 class="fw-bold">

                        <?= number_format(
                            $expenseCount
                        ) ?>

                    </h3>

                </div>

            </div>

        </div>

    </div>


    <div class="row g-4">


        <!-- ADD EXPENSE -->

        <div class="col-lg-4">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <h5 class="fw-bold mb-4">
                        Add Expense
                    </h5>


                    <form method="POST">

                        <div class="mb-3">

                            <label class="form-label">
                                Expense Title
                            </label>

                            <input
                                type="text"
                                name="expense_title"
                                class="form-control"
                                placeholder="e.g. Electricity Bill"
                                required
                            >

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Amount (Rs.)
                            </label>

                            <input
                                type="number"
                                name="amount"
                                class="form-control"
                                min="0.01"
                                step="0.01"
                                placeholder="0.00"
                                required
                            >

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Date
                            </label>

                            <input
                                type="date"
                                name="expense_date"
                                class="form-control"
                                value="<?= date("Y-m-d") ?>"
                                required
                            >

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Description
                            </label>

                            <textarea
                                name="description"
                                class="form-control"
                                rows="3"
                                placeholder="Optional details..."
                            ></textarea>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-dark w-100"
                        >
                            + Add Expense
                        </button>

                    </form>

                </div>

            </div>

        </div>


        <!-- EXPENSE LIST -->

        <div class="col-lg-8">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <h5 class="fw-bold mb-3">
                        Expense History
                    </h5>


                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                            <thead class="table-dark">

                                <tr>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Expense
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                    <th>
                                        Added By
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                            <?php if (empty($expenses)): ?>

                                <tr>

                                    <td
                                        colspan="5"
                                        class="text-center text-muted py-5"
                                    >
                                        No expenses recorded yet.
                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($expenses as $expense): ?>

                                    <tr>

                                        <td>

                                            <?= htmlspecialchars(
                                                $expense["expense_date"]
                                            ) ?>

                                        </td>


                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $expense["title"]
                                                ) ?>

                                            </strong>

                                            <?php if (
                                                !empty(
                                                    $expense["description"]
                                                )
                                            ): ?>

                                                <br>

                                                <small class="text-muted">

                                                    <?= htmlspecialchars(
                                                        $expense["description"]
                                                    ) ?>

                                                </small>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <strong>

                                                Rs.
                                                <?= number_format(
                                                    (float)
                                                    $expense["amount"],
                                                    2
                                                ) ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $expense[
                                                    "created_by_name"
                                                ] ?? "-"
                                            ) ?>

                                        </td>


                                        <td>

                                            <a
                                                href="?delete=<?= (int)
                                                    $expense["id"] ?>"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm(
                                                    'Delete this expense?'
                                                );"
                                            >
                                                Delete
                                            </a>

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

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>