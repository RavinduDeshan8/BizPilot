
<?php

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/ai_agent/ActionExecutor.php";

/**
 * Send a JSON response and stop execution.
 */
function sendExecutionJson(
    array $data,
    int $statusCode = 200
): void {
    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/**
 * Allow POST requests only.
 */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendExecutionJson([
        "success" => false,
        "message" => "POST requests only."
    ], 405);
}

/**
 * Require an authenticated administrator.
 */
requireAdmin();

if (
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    sendExecutionJson([
        "success" => false,
        "message" => "Admin access required."
    ], 403);
}

/**
 * Validate the CSRF token.
 */
$sessionToken = (string)(
    $_SESSION["ai_agent_execute_csrf_token"] ?? ""
);

$postedToken = (string)(
    $_SERVER["HTTP_X_CSRF_TOKEN"] ?? ""
);

if (
    $sessionToken === "" ||
    $postedToken === "" ||
    !hash_equals($sessionToken, $postedToken)
) {
    sendExecutionJson([
        "success" => false,
        "message" => "Invalid security token. Refresh the page and try again."
    ], 403);
}

/**
 * Require application/json.
 */
$contentType = strtolower(
    trim(
        explode(
            ";",
            $_SERVER["CONTENT_TYPE"] ?? ""
        )[0]
    )
);

if ($contentType !== "application/json") {
    sendExecutionJson([
        "success" => false,
        "message" => "Content-Type must be application/json."
    ], 415);
}

/**
 * Read and validate the request body.
 */
$rawInput = file_get_contents("php://input");

if (
    $rawInput === false ||
    strlen($rawInput) > 1000
) {
    sendExecutionJson([
        "success" => false,
        "message" => "Invalid or oversized request."
    ], 400);
}

$input = json_decode($rawInput, true);

if (
    !is_array($input) ||
    !isset($input["action_id"]) ||
    !is_scalar($input["action_id"]) ||
    filter_var(
        $input["action_id"],
        FILTER_VALIDATE_INT
    ) === false ||
    (int)$input["action_id"] <= 0
) {
    sendExecutionJson([
        "success" => false,
        "message" => "A valid action_id is required."
    ], 400);
}

/**
 * Identify the logged-in administrator.
 */
$adminId = (int)(
    $_SESSION["user_id"] ?? 0
);

if ($adminId <= 0) {
    sendExecutionJson([
        "success" => false,
        "message" => "Could not identify the logged-in admin."
    ], 403);
}

/**
 * Execute the approved AI Agent action.
 */
try {

    // IMPORTANT: Pass the existing PDO database connection.
    $executor = new ActionExecutor($pdo);

    $result = $executor->execute(
        (int)$input["action_id"],
        $adminId
    );

    if (!is_array($result)) {
        sendExecutionJson([
            "success" => false,
            "message" => "The executor returned an unexpected response."
        ], 500);
    }

    $success = !empty($result["success"]);

    sendExecutionJson(
        $result,
        $success ? 200 : 409
    );

} catch (Throwable $e) {

    error_log(
        "AI Agent execution endpoint: " .
        $e->getMessage()
    );

    sendExecutionJson([
        "success" => false,
        "message" => "Execution failed. Check the PHP error log."
    ], 500);
}