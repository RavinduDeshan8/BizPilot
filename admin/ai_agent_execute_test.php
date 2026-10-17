
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
| CSRF Token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["ai_agent_execute_csrf_token"])) {
    $_SESSION["ai_agent_execute_csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["ai_agent_execute_csrf_token"];

/*
|--------------------------------------------------------------------------
| Page Data
|--------------------------------------------------------------------------
*/

$actions = [];
$loadError = "";

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| Load Approved Test Actions - PDO
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare(
        "SELECT
            id,
            action_title,
            action_description,
            created_at
         FROM ai_agent_actions
         WHERE status = :status
           AND action_type = :action_type
         ORDER BY id DESC
         LIMIT 50"
    );

    $stmt->execute([
        "status" => "approved",
        "action_type" => "test_notification"
    ]);

    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = null;

} catch (Throwable $e) {
    error_log(
        "AI execution test page error: " . $e->getMessage()
    );

    $loadError = "Could not load approved test actions. Check the PHP error log.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>BizPilot AI - Safe Execution Test</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            max-width: 1100px;
            margin: 35px auto;
            padding: 20px;
            color: #222;
            background: #f8fafc;
        }

        h1 {
            margin-bottom: 20px;
        }

        .notice {
            background: #fff8db;
            border: 1px solid #ead58a;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .back-link {
            display: inline-block;
            margin: 10px 0 20px;
            color: #1d4ed8;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .table-container {
            overflow-x: auto;
            background: #fff;
            border-radius: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #eef2f7;
        }

        button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            background: #2563eb;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        button:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        #message {
            display: none;
            margin: 15px 0;
            padding: 12px;
            border-radius: 5px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .empty-message {
            padding: 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
    </style>
</head>

<body>

    <h1>BizPilot AI — Safe Execution Test</h1>

    <div class="notice">
        <strong>Safety Notice</strong><br>
        This page lists approved
        <code>test_notification</code> actions only.
        The test executor must not change stock, orders,
        products, or other business records.
    </div>

    <a
        class="back-link"
        href="ai_agent_approvals.php"
    >
        &larr; Back to AI Agent Approvals
    </a>

    <div
        id="message"
        role="status"
        aria-live="polite"
    ></div>

    <?php if ($loadError !== ""): ?>

        <div class="empty-message">
            <?= escapeHtml($loadError) ?>
        </div>

    <?php elseif (count($actions) === 0): ?>

        <div class="empty-message">
            <strong>No approved test actions found.</strong>

            <p>
                Check phpMyAdmin to confirm that an action has:
            </p>

            <ul>
                <li><code>action_type = test_notification</code></li>
                <li><code>status = approved</code></li>
            </ul>
        </div>

    <?php else: ?>

        <div class="table-container">
            <table>
                <thead>
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
                            <td>
                                <?= (int) $action["id"] ?>
                            </td>

                            <td>
                                <?= escapeHtml($action["action_title"]) ?>
                            </td>

                            <td>
                                <?= escapeHtml($action["action_description"]) ?>
                            </td>

                            <td>
                                <?= escapeHtml($action["created_at"]) ?>
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="execute-button"
                                    data-action-id="<?= (int) $action["id"] ?>"
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

    <script>
        "use strict";

        const csrfToken = <?= json_encode(
            $csrfToken,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>;

        const messageBox = document.getElementById("message");

        function showMessage(message, success = false) {
            messageBox.style.display = "block";
            messageBox.textContent = message;
            messageBox.style.background = success
                ? "#dcfce7"
                : "#fee2e2";
            messageBox.style.color = success
                ? "#166534"
                : "#991b1b";
        }

        document.querySelectorAll(".execute-button").forEach(button => {

            button.addEventListener("click", async () => {

                const actionId = Number(button.dataset.actionId);

                if (!Number.isSafeInteger(actionId) || actionId <= 0) {
                    showMessage("Invalid action ID.");
                    return;
                }

                const confirmed = window.confirm(
                    "Execute this approved test action?\n\n" +
                    "Only the harmless test_notification action is allowed."
                );

                if (!confirmed) {
                    return;
                }

                button.disabled = true;
                button.textContent = "Executing...";

                showMessage("Executing test action...");

                try {
                    const response = await fetch(
                        "ai_agent_execute.php",
                        {
                            method: "POST",
                            credentials: "same-origin",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-Token": csrfToken
                            },
                            body: JSON.stringify({
                                action_id: actionId
                            })
                        }
                    );

                    const responseText = await response.text();

                    let data;

                    try {
                        data = JSON.parse(responseText);
                    } catch (parseError) {
                        throw new Error(
                            "The server returned a non-JSON response. " +
                            "Check PHP errors and ai_agent_execute.php."
                        );
                    }

                    if (!response.ok || !data.success) {
                        showMessage(
                            data.message ||
                            "Execution failed. HTTP status: " +
                            response.status
                        );

                        button.disabled = false;
                        button.textContent = "Execute Test";
                        return;
                    }

                    showMessage(
                        data.message ||
                        "Test action executed successfully.",
                        true
                    );

                    window.setTimeout(() => {
                        window.location.reload();
                    }, 1200);

                } catch (error) {
                    showMessage(
                        error.message ||
                        "Request failed. Check the browser console and PHP error log."
                    );

                    button.disabled = false;
                    button.textContent = "Execute Test";
                }
            });

        });
    </script>

</body>
</html>
