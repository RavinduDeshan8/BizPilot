
<?php

require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/db.php";

/*
|--------------------------------------------------------------------------
| Admin authorization
|--------------------------------------------------------------------------
*/

requireAdmin();

/*
|--------------------------------------------------------------------------
| CSRF token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["ai_agent_csrf_token"])) {
    $_SESSION["ai_agent_csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["ai_agent_csrf_token"];

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

if (!function_exists("h")) {
    function h($value): string
    {
        return htmlspecialchars(
            (string)($value ?? ""),
            ENT_QUOTES,
            "UTF-8"
        );
    }
}

/*
|--------------------------------------------------------------------------
| Review result message
|--------------------------------------------------------------------------
*/

$notice = "";
$noticeType = "success";

$reviewStatus = (string)($_GET["review"] ?? "");

if ($reviewStatus === "approved") {
    $notice = "AI action approved. No business records were changed.";
} elseif ($reviewStatus === "rejected") {
    $notice = "AI action rejected. The decision has been recorded.";
} elseif (isset($_GET["error"])) {
    $notice = "The decision could not be recorded. Please try again.";
    $noticeType = "danger";
}

/*
|--------------------------------------------------------------------------
| Load pending AI actions
|--------------------------------------------------------------------------
*/

$error = "";
$actions = [];

try {
    $stmt = $pdo->prepare(
        "SELECT
            a.id,
            a.action_type,
            a.action_title,
            a.action_description,
            a.action_payload,
            a.status,
            a.created_at,
            u.full_name AS requester_name
         FROM ai_agent_actions AS a
         LEFT JOIN users AS u ON u.id = a.requested_by
         WHERE a.status = 'pending'
         ORDER BY a.created_at DESC
         LIMIT 100"
    );

    $stmt->execute();
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log("BizPilot AI approval page error: " . $e->getMessage());

    $error = "Could not load AI approval requests. Please check the PHP error log.";
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

    <title>AI Agent Approvals | BizPilot</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f5f7fb;
            color: #212529;
        }

        .page-header {
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }

        .action-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 18px;
            overflow-wrap: anywhere;
        }

        .payload {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            padding: 12px;
            border-radius: 8px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .action-description {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .page-footer {
            margin-top: 24px;
            margin-bottom: 30px;
        }
    </style>
</head>

<body>

<div class="container py-4">

    <!-- Page header -->
    <div class="page-header">
        <h1 class="h3 mb-2">
            BizPilot AI Agent Approvals
        </h1>

        <p class="text-secondary mb-0">
            Review AI-proposed actions before any business changes are allowed.
        </p>
    </div>

    <!-- Result notification -->
    <?php if ($notice !== ""): ?>
        <div
            class="alert alert-<?= h($noticeType) ?> alert-dismissible fade show"
            role="alert"
        >
            <?= h($notice) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>
        </div>
    <?php endif; ?>

    <!-- Database error -->
    <?php if ($error !== ""): ?>

        <div class="alert alert-danger" role="alert">
            <?= h($error) ?>
        </div>

    <?php else: ?>

        <!-- Pending count -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">
                Pending requests
            </h2>

            <span class="badge text-bg-warning">
                <?= count($actions) ?> pending
            </span>
        </div>

        <!-- Empty state -->
        <?php if (count($actions) === 0): ?>

            <div class="alert alert-info">
                No pending AI actions were found.
            </div>

        <?php endif; ?>

        <!-- AI action cards -->
        <?php foreach ($actions as $action): ?>

            <div class="action-card">

                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">

                    <h3 class="h5 mb-0">
                        #<?= (int)$action["id"] ?>
                        — <?= h($action["action_title"]) ?>
                    </h3>

                    <span class="badge text-bg-warning">
                        <?= h($action["status"]) ?>
                    </span>

                </div>

                <p class="mb-2">
                    <strong>Action type:</strong>
                    <?= h($action["action_type"]) ?>
                </p>

                <p class="mb-2">
                    <strong>Requested by:</strong>
                    <?= h($action["requester_name"] ?? "Unknown") ?>
                </p>

                <p class="mb-2">
                    <strong>Created:</strong>
                    <?= h($action["created_at"]) ?>
                </p>

                <div class="action-description mb-3"><?= h(
                    $action["action_description"] ?? ""
                ) ?></div>

                <!-- Proposed payload -->
                <?php if (!empty($action["action_payload"])): ?>

                    <details class="mb-3">

                        <summary class="fw-semibold">
                            View proposed action data
                        </summary>

                        <div class="payload mt-2"><?= h(
                            $action["action_payload"]
                        ) ?></div>

                    </details>

                <?php endif; ?>

                <!-- Approve / Reject form -->
                <form
                    method="POST"
                    action="ai_agent_action_review.php"
                    class="d-flex flex-wrap gap-2 mt-3"
                    onsubmit="return confirm('Are you sure you want to record this decision?');"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h($csrfToken) ?>"
                    >

                    <input
                        type="hidden"
                        name="action_id"
                        value="<?= (int)$action["id"] ?>"
                    >

                    <button
                        type="submit"
                        name="decision"
                        value="approve"
                        class="btn btn-success"
                    >
                        Approve
                    </button>

                    <button
                        type="submit"
                        name="decision"
                        value="reject"
                        class="btn btn-outline-danger"
                    >
                        Reject
                    </button>

                </form>

                <p class="text-secondary small mt-3 mb-0">
                    This records the decision only. It does not change
                    stock, orders, or other business records.
                </p>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

    <!-- Back navigation -->
    <div class="page-footer">
        <a
            href="index.php"
            class="btn btn-outline-secondary"
        >
            Back to Admin Dashboard
        </a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
