
<?php


/**
 * BizPilot AI Agent - Secure Action Executor
 *
 * Currently supports only the harmless test_notification action.
 *
 * Security controls:
 * - Requires administrator approval.
 * - Uses a strict action-type allowlist.
 * - Validates the action payload.
 * - Locks the action row during execution.
 * - Prevents repeated execution through status validation.
 * - Saves execution results and audit logs transactionally.
 *
 * This class does not modify stock, products, orders,
 * customers, expenses, or other business records.
 */

class ActionExecutor
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Execute one approved, supported AI action.
     *
     * @return array{success: bool, message: string}
     */
    public function execute(int $actionId, int $adminId): array
    {
        if ($actionId <= 0 || $adminId <= 0) {
            return [
                "success" => false,
                "message" => "Invalid action ID or administrator ID."
            ];
        }

        /*
         * This executor owns its transaction.
         * Do not silently commit or roll back a caller's transaction.
         */
        if ($this->pdo->inTransaction()) {
            return [
                "success" => false,
                "message" =>
                    "Execution cannot start inside an existing transaction."
            ];
        }

        try {
            $this->pdo->beginTransaction();

            /*
             * Lock the requested action to prevent concurrent execution.
             */
            $stmt = $this->pdo->prepare(
                "SELECT
                    id,
                    action_type,
                    action_title,
                    action_payload,
                    status
                 FROM ai_agent_actions
                 WHERE id = ?
                 FOR UPDATE"
            );

            $stmt->execute([$actionId]);

            $action = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$action) {
                $this->pdo->rollBack();

                return [
                    "success" => false,
                    "message" => "AI action was not found."
                ];
            }

            /*
             * An action must be approved and not already executed.
             */
            if ($action["status"] !== "approved") {
                $this->pdo->rollBack();

                return [
                    "success" => false,
                    "message" =>
                        "Only approved actions can be executed. " .
                        "This action may already have been processed."
                ];
            }

            /*
             * Strict allowlist.
             * No stock, order, product, or other action is enabled.
             */
            if ($action["action_type"] !== "test_notification") {
                $this->pdo->rollBack();

                return [
                    "success" => false,
                    "message" =>
                        "This action type is not enabled for execution."
                ];
            }

            /*
             * Validate the harmless test payload.
             */
            $payload = json_decode(
                (string) ($action["action_payload"] ?? ""),
                true
            );

            if (
                !is_array($payload) ||
                ($payload["test"] ?? null) !== true ||
                ($payload["business_data_change"] ?? null) !== false
            ) {
                $this->pdo->rollBack();

                return [
                    "success" => false,
                    "message" =>
                        "The test action payload is invalid."
                ];
            }

            /*
             * This is a simulated test execution.
             * It performs no external or business-data operation.
             */
            $result = json_encode(
                [
                    "success" => true,
                    "action_type" => "test_notification",
                    "business_data_changed" => false,
                    "message" =>
                        "Test action completed. No business records were changed."
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if ($result === false) {
                throw new RuntimeException(
                    "Could not encode the execution result."
                );
            }

            /*
             * Update only the AI action tracking record.
             */
            $stmt = $this->pdo->prepare(
                "UPDATE ai_agent_actions
                 SET status = 'executed',
                     executed_at = NOW(),
                     execution_result = ?
                 WHERE id = ?
                   AND status = 'approved'"
            );

            $stmt->execute([
                $result,
                $actionId
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    "The action status could not be updated."
                );
            }

            /*
             * Prepare the audit event.
             */
            $details = json_encode(
                [
                    "action_type" => "test_notification",
                    "action_title" => (string) $action["action_title"],
                    "result" => "success",
                    "business_data_changed" => false
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if ($details === false) {
                throw new RuntimeException(
                    "Could not encode the audit details."
                );
            }

            /*
             * Save the audit event in the same transaction.
             */
            $stmt = $this->pdo->prepare(
                "INSERT INTO ai_agent_audit_logs
                    (
                        action_id,
                        actor_user_id,
                        event_type,
                        event_details
                    )
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->execute([
                $actionId,
                $adminId,
                "action_executed",
                $details
            ]);

            /*
             * Commit the action update and audit event together.
             */
            $this->pdo->commit();

            return [
                "success" => true,
                "message" =>
                    "Test action executed successfully. " .
                    "No business records were changed."
            ];

        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {
                try {
                    $this->pdo->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log(
                        "BizPilot ActionExecutor rollback error: " .
                        $rollbackError->getMessage()
                    );
                }
            }

            error_log(
                "BizPilot ActionExecutor error: " .
                $e->getMessage()
            );

            return [
                "success" => false,
                "message" =>
                    "Execution failed. Please check the PHP error log."
            ];
        }
    }
}
