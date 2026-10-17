
<?php

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/db.php";

function reviewResponse(bool $success, string $message, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(
        ["success" => $success, "message" => $message],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    reviewResponse(false, "POST requests only.", 405);
}

if (
    !isLoggedIn() ||
    ($_SESSION["user_role"] ?? "") !== "admin"
) {
    reviewResponse(false, "Administrator access is required.", 403);
}

$contentType = $_SERVER["CONTENT_TYPE"] ?? "";

if (stripos($contentType, "application/json") === false) {
    reviewResponse(false, "Content-Type must be application/json.", 415);
}

$rawInput = file_get_contents("php://input");

if ($rawInput === false || strlen($rawInput) > 5000) {
    reviewResponse(false, "Invalid or oversized request.", 413);
}

$input = json_decode($rawInput, true);

if (!is_array($input)) {
    reviewResponse(false, "Invalid JSON request.", 400);
}

$actionId = filter_var(
    $input["action_id"] ?? null,
    FILTER_VALIDATE_INT
);

$decision = $input["decision"] ?? "";
$csrfToken = (string)($input["csrf_token"] ?? "");
$sessionToken = (string)($_SESSION["ai_agent_csrf_token"] ?? "");

if (!$actionId || $actionId < 1) {
    reviewResponse(false, "Invalid action ID.", 400);
}

if (!in_array($decision, ["approve", "reject"], true)) {
    reviewResponse(false, "Decision must be approve or reject.", 400);
}

if (
    $sessionToken === "" ||
    $csrfToken === "" ||
    !hash_equals($sessionToken, $csrfToken)
) {
    reviewResponse(false, "Security token validation failed. Refresh the page and try again.", 403);
}

$adminId = (int)($_SESSION["user_id"] ?? 0);

if ($adminId < 1) {
    reviewResponse(false, "Your login session is invalid. Please log in again.", 401);
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
        reviewResponse(false, "AI action was not found.", 404);
    }

    if ($action["status"] !== "pending") {
        $pdo->rollBack();
        reviewResponse(false, "This action has already been reviewed.", 409);
    }

    $stmt = $pdo->prepare(
        "UPDATE ai_agent_actions
         SET status = ?, reviewed_by = ?, reviewed_at = NOW()
         WHERE id = ? AND status = 'pending'"
    );
    $stmt->execute([$newStatus, $adminId, $actionId]);

    if ($stmt->rowCount() !== 1) {
        $pdo->rollBack();
        reviewResponse(false, "The action status changed. Refresh and try again.", 409);
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

    reviewResponse(
        true,
        $decision === "approve"
            ? "Action approved. No business data has been changed."
            : "Action rejected. The decision has been recorded."
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("BizPilot AI review error: " . $e->getMessage());
    reviewResponse(false, "Could not record the decision. Check the PHP error log.", 500);
}
