
<?php

/**
 * BizPilot Advanced AI Agent Core
 *
 * - Validates AI requests.
 * - Sends questions and verified context to OpenRouter.
 * - Handles API and network errors safely.
 * - Rejects empty or irrelevant safety-only responses.
 * - Does not execute database-changing actions.
 */

class AgentCore
{
    private string $apiKey;
    private string $model;
    private string $apiUrl;

    public function __construct()
    {
        require __DIR__ . "/../../config/ai.php";

        $this->apiKey = trim((string) ($apiKey ?? ""));
        $this->model = trim(
            (string) ($openRouterModel ?? "openrouter/free")
        );
        $this->apiUrl = trim(
            (string) (
                $openRouterApiUrl
                ?? "https://openrouter.ai/api/v1/chat/completions"
            )
        );
    }

    public function ask(string $question): array
    {
        $question = trim($question);

        if ($question === "") {
            return [
                "success" => false,
                "message" => "Please enter a question."
            ];
        }

        if (strlen($question) > 20000) {
            return [
                "success" => false,
                "message" => "The request context is too long."
            ];
        }

        if ($this->apiKey === "") {
            return [
                "success" => false,
                "message" => "AI provider is not configured."
            ];
        }

        if (!function_exists("curl_init")) {
            return [
                "success" => false,
                "message" => "The PHP cURL extension is not enabled."
            ];
        }

        if (
            $this->model === "" ||
            !filter_var($this->apiUrl, FILTER_VALIDATE_URL) ||
            stripos($this->apiUrl, "https://") !== 0
        ) {
            return [
                "success" => false,
                "message" => "AI provider configuration is invalid."
            ];
        }

        /*
         * AI system instructions.
         */
        $systemPrompt =
            "You are BizPilot's retail business AI analyst. "

            . "Answer the user's actual question directly. "

            . "The user message may include verified database data "
            . "under 'Verified BizPilot database context'. "

            . "Use supplied verified data when answering business questions. "

            . "For low-stock questions, identify products at or below "
            . "the threshold specified in the supplied context. "

            . "For sales questions, respect the reporting period selected "
            . "by the application. The period may be a date-limited report "
            . "or an all-time report. "

            . "Report the supplied order statuses separately: pending, "
            . "confirmed, preparing, ready, out_for_delivery, completed, "
            . "and cancelled. "

            . "Report sales revenue from completed orders only. "
            . "Do not treat pending, confirmed, preparing, ready, or "
            . "out_for_delivery orders as completed sales. "

            . "When verified sales summary or daily sales data is supplied, "
            . "use those values instead of inventing or recalculating "
            . "business records. Clearly state the reporting period. "

            . "All monetary values are in LKR. "

            . "Do not invent products, stock quantities, sales, order counts, "
            . "or business records. "

            . "If required data is missing, say so clearly. "

            . "Do not claim to have changed the database or executed actions. "

            . "For changes to business data, provide recommendations only "
            . "and wait for an authorized approval workflow. "

            . "Answer in the same language as the user's question. "

            . "Do not return unrelated safety labels or classifications.";

        /*
         * OpenRouter request payload.
         */
        $payload = [
            "model" => $this->model,
            "messages" => [
                [
                    "role" => "system",
                    "content" => $systemPrompt
                ],
                [
                    "role" => "user",
                    "content" => $question
                ]
            ],
            "temperature" => 0.2,
            "max_tokens" => 1200
        ];

        $encodedPayload = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
        );

        if ($encodedPayload === false) {
            return [
                "success" => false,
                "message" => "The AI request could not be prepared."
            ];
        }

        /*
         * Send request to OpenRouter.
         */
        $ch = curl_init($this->apiUrl);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->apiKey,
                "Content-Type: application/json",
                "HTTP-Referer: http://localhost",
                "X-OpenRouter-Title: BizPilot AI Agent"
            ],
            CURLOPT_POSTFIELDS => $encodedPayload,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 40
        ]);

        $response = curl_exec($ch);

        $httpCode = (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        $curlError = curl_error($ch);

        curl_close($ch);

        /*
         * Handle network errors.
         */
        if ($response === false || $curlError !== "") {
            error_log(
                "BizPilot Agent network request failed."
            );

            return [
                "success" => false,
                "message" => "The AI service could not be reached. Please try again."
            ];
        }

        /*
         * Decode and validate provider response.
         */
        $data = json_decode($response, true);

        if (
            $httpCode < 200 ||
            $httpCode >= 300 ||
            !is_array($data)
        ) {
            error_log(
                "BizPilot Agent provider error. HTTP status: "
                . $httpCode
            );

            return [
                "success" => false,
                "message" => "The AI service returned an error. Please try again."
            ];
        }

        $answer = $data["choices"][0]["message"]["content"] ?? "";

        /*
         * Some providers return content as an array of text blocks.
         */
        if (is_array($answer)) {
            $parts = [];

            foreach ($answer as $part) {
                if (
                    is_array($part) &&
                    isset($part["text"]) &&
                    is_string($part["text"])
                ) {
                    $parts[] = $part["text"];
                }
            }

            $answer = implode("\n", $parts);
        }

        if (!is_string($answer)) {
            $answer = "";
        }

        $answer = trim($answer);

        /*
         * Reject empty responses.
         */
        if ($answer === "") {
            error_log(
                "BizPilot Agent provider returned empty content."
            );

            return [
                "success" => false,
                "message" => "The AI service returned an empty response. Please try again."
            ];
        }

        /*
         * Detect irrelevant safety-only responses.
         */
        if (
            preg_match(
                '/^\s*(?:user\s+safety\s*:\s*safe|safety\s*:\s*safe)\s*[.!]?\s*$/i',
                $answer
            )
        ) {
            error_log(
                "BizPilot Agent provider returned an irrelevant safety-only response."
            );

            return [
                "success" => false,
                "message" => "The AI provider returned an irrelevant response. Please try again."
            ];
        }

        return [
            "success" => true,
            "message" => $answer
        ];
    }
}
