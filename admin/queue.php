
<?php


require_once __DIR__ . "/../config/admin_auth.php";
requireAdmin();

require_once __DIR__ . "/../config/db.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION["queue_csrf_token"]) ||
    !is_string($_SESSION["queue_csrf_token"])
) {
    $_SESSION["queue_csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["queue_csrf_token"];

$queueTypes = [
    "cashier" => "Cashier",
    "preparation" => "Order Preparation",
    "pickup" => "Store Pickup",
    "delivery" => "Delivery"
];

$statusLabels = [
    "waiting" => "Waiting",
    "in_progress" => "In Progress",
    "ready" => "Ready",
    "completed" => "Completed",
    "cancelled" => "Cancelled"
];

$statusClasses = [
    "waiting" => "warning",
    "in_progress" => "primary",
    "ready" => "info",
    "completed" => "success",
    "cancelled" => "secondary"
];

/*
|--------------------------------------------------------------------------
| Queue summary
|--------------------------------------------------------------------------
*/

$summary = [];

foreach ($queueTypes as $type => $label) {
    $summary[$type] = [
        "waiting" => 0,
        "in_progress" => 0,
        "ready" => 0,
        "completed" => 0,
        "cancelled" => 0
    ];
}

$stmt = $pdo->query("
    SELECT queue_type, status, COUNT(*) AS total
    FROM queue_entries
    GROUP BY queue_type, status
");

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (
        isset($summary[$row["queue_type"]]) &&
        isset($summary[$row["queue_type"]][$row["status"]])
    ) {
        $summary[$row["queue_type"]][$row["status"]] =
            (int) $row["total"];
    }
}

/*
|--------------------------------------------------------------------------
| Filter
|--------------------------------------------------------------------------
*/

$selectedType = $_GET["type"] ?? "all";

if (
    !is_string($selectedType) ||
    ($selectedType !== "all" && !isset($queueTypes[$selectedType]))
) {
    $selectedType = "all";
}

/*
|--------------------------------------------------------------------------
| Queue entries
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        queue_type,
        reference_number,
        customer_name,
        status,
        priority,
        created_at,
        started_at,
        completed_at,
        notes,
        TIMESTAMPDIFF(
            MINUTE,
            created_at,
            COALESCE(started_at, NOW())
        ) AS waiting_minutes
    FROM queue_entries
";

$params = [];

if ($selectedType !== "all") {
    $sql .= " WHERE queue_type = ? ";
    $params[] = $selectedType;
}

$sql .= "
    ORDER BY
        CASE WHEN status = 'waiting' THEN 0 ELSE 1 END,
        priority DESC,
        created_at ASC,
        id ASC
    LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

function queueEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function queueDuration($minutes): string
{
    $minutes = max(0, (int) $minutes);

    if ($minutes < 60) {
        return $minutes . " min";
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    return $hours . " h " . $remainingMinutes . " min";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Queue Management - BizPilot AI</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            BizPilot AI
        </a>

        <div class="d-flex gap-2 align-items-center">
            <a href="orders.php" class="btn btn-outline-light btn-sm">
                Orders
            </a>

            <a href="index.php" class="btn btn-outline-light btn-sm">
                Dashboard
            </a>
        </div>
    </div>
</nav>

<div class="container py-4 py-lg-5">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="fw-bold mb-1">Queue Management</h1>
            <p class="text-muted mb-0">
                Manage cashier service and order fulfilment queues.
            </p>
        </div>

        <button
            type="button"
            class="btn btn-success"
            data-bs-toggle="collapse"
            data-bs-target="#addQueueForm"
            aria-expanded="false"
        >
            + Add Queue Entry
        </button>
    </div>

    <?php if (isset($_GET["success"])): ?>
        <div class="alert alert-success">
            <?php if ($_GET["success"] === "added"): ?>
                Queue entry added successfully.
            <?php else: ?>
                Queue status updated successfully.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>
        <div class="alert alert-danger">
            <?php
            $errorMessages = [
                "security" => "Security validation failed. Please try again.",
                "invalid" => "Invalid input. Please check the details.",
                "save" => "Could not save the queue entry.",
                "update" => "Could not update the queue status.",
                "notfound" => "Queue entry was not found.",
                "closed" => "Completed or cancelled entries cannot be reopened."
            ];

            echo queueEscape(
                $errorMessages[$_GET["error"]] ??
                "An unexpected error occurred."
            );
            ?>
        </div>
    <?php endif; ?>

    <div class="collapse mb-4" id="addQueueForm">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Add a Queue Entry</h5>

                <form action="../actions/add_queue_entry.php" method="POST">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= queueEscape($csrfToken) ?>"
                    >

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="queue_type">
                                Queue Type
                            </label>

                            <select
                                class="form-select"
                                id="queue_type"
                                name="queue_type"
                                required
                            >
                                <?php foreach ($queueTypes as $value => $label): ?>
                                    <option value="<?= queueEscape($value) ?>">
                                        <?= queueEscape($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="reference_number">
                                Reference / Token Number
                            </label>

                            <input
                                class="form-control"
                                id="reference_number"
                                name="reference_number"
                                type="text"
                                maxlength="50"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="customer_name">
                                Customer Name (optional)
                            </label>

                            <input
                                class="form-control"
                                id="customer_name"
                                name="customer_name"
                                type="text"
                                maxlength="150"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="notes">
                                Notes (optional)
                            </label>

                            <input
                                class="form-control"
                                id="notes"
                                name="notes"
                                type="text"
                                maxlength="500"
                            >
                        </div>

                        <div class="col-12">
                            <button class="btn btn-success" type="submit">
                                Add to Queue
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php foreach ($queueTypes as $type => $label): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-2">
                            <?= queueEscape($label) ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-end">
                            <div>
                                <div class="small text-muted">Waiting</div>
                                <div class="fs-3 fw-bold">
                                    <?= (int) $summary[$type]["waiting"] ?>
                                </div>
                            </div>

                            <div class="text-end">
                                <div class="small text-muted">In Progress</div>
                                <div class="fs-5 fw-semibold">
                                    <?= (int) $summary[$type]["in_progress"] ?>
                                </div>
                            </div>
                        </div>

                        <div class="small text-muted mt-2">
                            Ready:
                            <?= (int) $summary[$type]["ready"] ?>
                            · Completed:
                            <?= (int) $summary[$type]["completed"] ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <h5 class="fw-bold mb-0">Queue Entries</h5>

                <form method="GET" class="d-flex gap-2">
                    <select
                        name="type"
                        class="form-select"
                        aria-label="Filter queue type"
                    >
                        <option value="all" <?= $selectedType === "all" ? "selected" : "" ?>>
                            All Queues
                        </option>

                        <?php foreach ($queueTypes as $value => $label): ?>
                            <option
                                value="<?= queueEscape($value) ?>"
                                <?= $selectedType === $value ? "selected" : "" ?>
                            >
                                <?= queueEscape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button class="btn btn-outline-success" type="submit">
                        Filter
                    </button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Token</th>
                            <th>Queue</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Waiting Time</th>
                            <th>Notes</th>
                            <th>Update Status</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (!$entries): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No queue entries found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td class="fw-semibold">
                                    <?= queueEscape($entry["reference_number"]) ?>
                                </td>

                                <td>
                                    <?= queueEscape(
                                        $queueTypes[$entry["queue_type"]] ??
                                        $entry["queue_type"]
                                    ) ?>
                                </td>

                                <td>
                                    <?= queueEscape(
                                        $entry["customer_name"] ?: "—"
                                    ) ?>
                                </td>

                                <td>
                                    <span class="badge text-bg-<?=
                                        queueEscape(
                                            $statusClasses[$entry["status"]] ??
                                            "secondary"
                                        )
                                    ?>">
                                        <?= queueEscape(
                                            $statusLabels[$entry["status"]] ??
                                            $entry["status"]
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($entry["status"] === "waiting"): ?>
                                        <?= queueEscape(
                                            queueDuration($entry["waiting_minutes"])
                                        ) ?>
                                    <?php elseif ($entry["started_at"]): ?>
                                        <span class="text-muted">
                                            Started
                                        </span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= queueEscape($entry["notes"] ?: "—") ?>
                                </td>

                                <td>
                                    <?php if (
                                        !in_array(
                                            $entry["status"],
                                            ["completed", "cancelled"],
                                            true
                                        )
                                    ): ?>
                                        <form
                                            action="../actions/update_queue_status.php"
                                            method="POST"
                                            class="d-flex gap-2"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= queueEscape($csrfToken) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="entry_id"
                                                value="<?= (int) $entry["id"] ?>"
                                            >

                                            <select
                                                name="status"
                                                class="form-select form-select-sm"
                                                aria-label="Update status"
                                                required
                                            >
                                                <?php foreach ($statusLabels as $value => $label): ?>
                                                    <option
                                                        value="<?= queueEscape($value) ?>"
                                                        <?= $entry["status"] === $value ? "selected" : "" ?>
                                                    >
                                                        <?= queueEscape($label) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                Save
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">
                                            Closed
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="text-muted small mb-0">
                Showing up to 200 entries. Waiting time is measured from
                queue creation until work starts.
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
