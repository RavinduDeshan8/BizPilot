<?php

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/ai_agent/AgentOrchestrator.php";
require_once __DIR__ . "/../includes/ai_agent/ActionProposalService.php";

/**
 * Send a consistent JSON response and terminate execution.
 */
function sendAgentJson(
    bool $success,
    string $message,
    int $status = 200,
    array $extra = []
): void {
    http_response_code($status);

    $response = array_merge(
        [
            "success" => $success,
            "message" => $message
        ],
        $extra
    );

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| 1. Request method validation
|--------------------------------------------------------------------------
*/

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    header("Allow: POST");

    sendAgentJson(
        false,
        "POST requests only.",
        405
    );
}

/*
|--------------------------------------------------------------------------
| 2. Admin authentication
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {
    sendAgentJson(
        false,
        "Please log in first.",
        401
    );
}

if (
    !isset($_SESSION["user_role"])
    || $_SESSION["user_role"] !== "admin"
) {
    sendAgentJson(
        false,
        "Administrator access is required.",
        403
    );
}

/*
|--------------------------------------------------------------------------
| 3. Content-Type validation
|--------------------------------------------------------------------------
*/

$contentType = $_SERVER["CONTENT_TYPE"] ?? "";

if (
    stripos($contentType, "application/json") === false
) {
    sendAgentJson(
        false,
        "Content-Type must be application/json.",
        415
    );
}

/*
|--------------------------------------------------------------------------
| 4. Input validation
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents("php://input");

if ($rawInput === false) {
    sendAgentJson(
        false,
        "Unable to read request body.",
        400
    );
}

if (strlen($rawInput) > 5000) {
    sendAgentJson(
        false,
        "Request is too large.",
        413
    );
}

$input = json_decode($rawInput, true);

if (
    json_last_error() !== JSON_ERROR_NONE
    || !is_array($input)
) {
    sendAgentJson(
        false,
        "Invalid JSON request.",
        400
    );
}

$question = trim(
    (string) ($input["question"] ?? "")
);

if ($question === "") {
    sendAgentJson(
        false,
        "Please enter a question.",
        400
    );
}

if (mb_strlen($question, "UTF-8") > 4000) {
    sendAgentJson(
        false,
        "Question is too long.",
        400
    );
}

/*
|--------------------------------------------------------------------------
| 5. Run the AI Agent
|--------------------------------------------------------------------------
*/

try {
    $agent = new AgentOrchestrator($pdo);

    $result = $agent->handle($question);

    if (!is_array($result)) {
        error_log(
            "BizPilot AI Agent returned an invalid result."
        );

        sendAgentJson(
            false,
            "The AI agent returned an invalid response.",
            502
        );
    }

    $success = (bool) ($result["success"] ?? false);

    $message = (string) (
        $result["message"] ?? "No response received."
    );

    /*
    |--------------------------------------------------------------------------
    | 6. Create a proposal when explicitly requested
    |--------------------------------------------------------------------------
    |
    | This is a basic English-keyword detector.
    | Sinhala and Singlish intent detection can be added later.
    |
    */

    $extra = [];

    $proposalIntent = preg_match(
        '/\b(recommend|recommendation|suggest|suggestion|propose|proposal)\b/i',
        $question
    );

    if (
        $success
        && trim($message) !== ""
        && $proposalIntent === 1
    ) {
        try {
            /*
             * Use the logged-in admin's ID when available.
             */
            $requestedBy = isset($_SESSION["user_id"])
                ? (int) $_SESSION["user_id"]
                : null;

            $proposalService = new ActionProposalService($pdo);

            $proposalResult = $proposalService->createRecommendation(
                "AI Business Recommendation",
                $message,
                [
                    "source" => "ai_agent",
                    "original_question" => $question
                ],
                $requestedBy
            );

            if (
                is_array($proposalResult)
                && !empty($proposalResult["success"])
                && isset($proposalResult["action_id"])
                && is_numeric($proposalResult["action_id"])
            ) {
                $extra["proposal_id"] =
                    (int) $proposalResult["action_id"];

                /*
                 * Use the service's returned status if available.
                 * Otherwise, pending is the expected initial status.
                 */
                $extra["proposal_status"] =
                    $proposalResult["status"] ?? "pending";
            } else {
                /*
                 * Preserve the AI answer if proposal creation fails.
                 */
                error_log(
                    "BizPilot AI proposal creation failed: "
                    . json_encode(
                        $proposalResult,
                        JSON_UNESCAPED_UNICODE
                        | JSON_INVALID_UTF8_SUBSTITUTE
                    )
                );

                $extra["proposal_created"] = false;
                $extra["proposal_message"] =
                    "The AI response was generated, but the proposal could not be confirmed.";
            }
        } catch (Throwable $proposalError) {
            /*
             * Proposal failure should not discard a successful
             * AI response.
             */
            error_log(
                "BizPilot AI proposal error: "
                . $proposalError->getMessage()
            );

            $extra["proposal_created"] = false;
            $extra["proposal_message"] =
                "The AI response was generated, but proposal creation failed.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Return the response to the frontend
    |--------------------------------------------------------------------------
    */

    sendAgentJson(
        $success,
        $message,
        $success ? 200 : 502,
        $extra
    );

} catch (Throwable $e) {
    error_log(
        "BizPilot AI Agent error: "
        . $e->getMessage()
    );

    sendAgentJson(
        false,
        "The AI agent encountered an error. Please check the PHP error log.",
        500
    );
}
