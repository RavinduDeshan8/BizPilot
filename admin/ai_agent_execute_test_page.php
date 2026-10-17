
<?php

require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/db.php";

requireAdmin();

if (
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    http_response_code(403);
    exit("Access denied.");
}

/*
|--------------------------------------------------------------------------
| Create a CSRF token for the execution endpoint
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["ai_agent_execute_csrf_token"])) {
    $_SESSION["ai_agent_execute_csrf_token"] =
        bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["ai_agent_execute_csrf_token"];

/*
|--------------------------------------------------------------------------
| Load approved harmless test actions only
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare(
        "SELECT id, action_title, action_description, created_at
         FROM ai_agent_actions
         WHERE status = 'approved'
           AND action_type = 'test_notification'
         ORDER BY id DESC
         LIMIT 50"
    );

    $stmt->execute();
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log("AI test action page error: " . $e->getMessage());
    $actions = [];
    $loadError = true;
}

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>BizPilot AI - Test Execution</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>BizPilot AI Agent</h2>
        <a href="index.php" class="btn btn-outline-secondary">
            Back to Dashboard
        </a>
    </div>

    <div class="alert alert-warning">
        <strong>Safe test mode</strong><br>
        Only approved <code>test_notification</code> actions are shown.
        Executing a test records its result and audit event.
        It does not change products, stock, orders, or other business data.
    </div>

    <?php if (!empty($loadError)): ?>
        <div class="alert alert-danger">
            Could not load approved test actions. Check the PHP error log.
        </div>
    <?php endif; ?>

    <div id="executionMessage" class="alert d-none" role="status"></div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <strong>Approved test actions</strong>
        </div>

        <div class="card-body">

            <?php if (empty($actions)): ?>

                <p class="text-muted mb-0">
                    No approved test actions are available.
                    Create a harmless test action and approve it first.
                </p>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($actions as $action): ?>
                            <tr>
                                <td><?= (int)$action["id"] ?></td>

                                <td><?= e($action["action_title"]) ?></td>

                                <td>
                                    <?= e($action["action_description"] ?? "") ?>
                                </td>

                                <td><?= e($action["created_at"] ?? "") ?></td>

                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm execute-test-btn"
                                        data-action-id="<?= (int)$action["id"] ?>"
                                    >
                                        Execute Test
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </div>
    </div>

</div>

<script>
"use strict";

const csrfToken = <?= json_encode($csrfToken) ?>;
const executionMessage = document.getElementById("executionMessage");

document.querySelectorAll(".execute-test-btn").forEach((button) => {
    button.addEventListener("click", async () => {
        const actionId = Number(button.dataset.actionId);

        if (!Number.isSafeInteger(actionId) || actionId < 1) {
            return;
        }

        const confirmed = window.confirm(
            "Execute this harmless test action? No business records should change."
        );

        if (!confirmed) {
            return;
        }

        button.disabled = true;
        button.textContent = "Executing...";

        executionMessage.className = "alert d-none";
        executionMessage.textContent = "";

        try {
            const response = await fetch("ai_agent_execute_test.php", {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify({
                    action_id: actionId
                })
            });

            const data = await response.json();

            executionMessage.className =
                "alert " + (response.ok && data.success
                    ? "alert-success"
                    : "alert-danger");

            executionMessage.textContent =
                data.message || "The server did not provide a result.";

            if (response.ok && data.success) {
                button.textContent = "Executed";
                window.setTimeout(() => window.location.reload(), 1200);
            } else {
                button.disabled = false;
                button.textContent = "Execute Test";
            }

        } catch (error) {
            executionMessage.className = "alert alert-danger";
            executionMessage.textContent =
                "Could not contact the server. Check the endpoint and PHP error log.";

            button.disabled = false;
            button.textContent = "Execute Test";
        }
    });
});
</script>

</body>
</html>