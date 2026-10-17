
<?php

require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/db.php";

requireAdmin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ai_agent_approvals.php");
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sessionToken = (string)($_SESSION["ai_agent_csrf_token"] ?? "");
$postedToken = (string)($_POST["csrf_token"] ?? "");

if (
    $sessionToken === "" ||
    $postedToken === "" ||
    !hash_equals($sessionToken, $postedToken)
) {
    http_response_code(403);
    exit("Security token validation failed. Please refresh the approvals page.");
}

$actionId = filter_var(
    $_POST["action_id"] ?? null,
    FILTER_VALIDATE_INT
);

$decision = (string)($_POST["decision"] ?? "");

if (!$actionId || $actionId < 1) {
    http_response_code(400);
    exit("Invalid action ID.");
}

if (!in_array($decision, ["approve", "reject"], true)) {
    http_response_code(400);
    exit("Invalid decision.");
}

$adminId = (int)($_SESSION["user_id"] ?? 0);

if ($adminId < 1) {
    http_response_code(401);
    exit("Please log in again.");
}

$newStatus = $decision === "approve" ? "approved" : "rejected";

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id, status, action_type, action_title
         FROM ai_agent_actions
         WHERE id = ?
         FOR UPDATE"
    );
    $stmt->execute([$actionId]);
    $action = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$action) {
        $pdo->rollBack();
        http_response_code(404);
        exit("AI action not found.");
    }

    if ($action["status"] !== "pending") {
        $pdo->rollBack();
        http_response_code(409);
        exit("This action has already been reviewed.");
    }

    $stmt = $pdo->prepare(
        "UPDATE ai_agent_actions
         SET status = ?, reviewed_by = ?, reviewed_at = NOW()
         WHERE id = ? AND status = 'pending'"
    );
    $stmt->execute([$newStatus, $adminId, $actionId]);

    if ($stmt->rowCount() !== 1) {
        $pdo->rollBack();
        http_response_code(409);
        exit("The action status changed. Please refresh the page.");
    }

    $eventType = $decision === "approve"
        ? "action_approved"
        : "action_rejected";

    $details = json_encode([
        "action_type" => $action["action_type"],
        "action_title" => $action["action_title"],
        "new_status" => $newStatus
    ], JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare(
        "INSERT INTO ai_agent_audit_logs
            (action_id, actor_user_id, event_type, event_details)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([
        $actionId,
        $adminId,
        $eventType,
        $details
    ]);

    $pdo->commit();

    header("Location: ai_agent_approvals.php?review=" . $newStatus);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("BizPilot AI action review error: " . $e->getMessage());

    http_response_code(500);
    exit("Could not record the decision. Please check the PHP error log.");
}
