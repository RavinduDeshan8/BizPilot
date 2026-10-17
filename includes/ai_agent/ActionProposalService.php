<?php
class ActionProposalService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Save an AI recommendation for admin review.
     *
     * This method does not execute business actions or modify
     * products, stock, orders, customers, or expenses.
     */
    public function createRecommendation(
        string $title,
        string $description,
        array $details = [],
        ?int $requestedBy = null
    ): array {
        $title = trim($title);
        $description = trim($description);

        if ($title === '' || $description === '') {
            return [
                "success" => false,
                "message" => "Proposal title and description are required."
            ];
        }

        if (mb_strlen($title) > 255) {
            $title = mb_substr($title, 0, 255);
        }

        // This proposal is informational only.
        // It is NOT an executable business action.
        $payload = [
            "proposal_kind" => "recommendation_only",
            "business_data_change" => false,
            "execution_allowed" => false,
            "details" => $details
        ];

        try {
            $payloadJson = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );

            $this->pdo->beginTransaction();

            $insertAction = $this->pdo->prepare("
                INSERT INTO ai_agent_actions (
                    action_type,
                    action_title,
                    action_description,
                    action_payload,
                    status,
                    requested_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :action_type,
                    :action_title,
                    :action_description,
                    :action_payload,
                    'pending',
                    :requested_by,
                    NOW(),
                    NOW()
                )
            ");

            $insertAction->bindValue(
                ":action_type",
                "recommendation_only",
                PDO::PARAM_STR
            );

            $insertAction->bindValue(
                ":action_title",
                $title,
                PDO::PARAM_STR
            );

            $insertAction->bindValue(
                ":action_description",
                $description,
                PDO::PARAM_STR
            );

            $insertAction->bindValue(
                ":action_payload",
                $payloadJson,
                PDO::PARAM_STR
            );

            $insertAction->bindValue(
                ":requested_by",
                $requestedBy,
                $requestedBy === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_INT
            );

            $insertAction->execute();

            $actionId = (int) $this->pdo->lastInsertId();

            $auditDetails = json_encode(
                [
                    "action_type" => "recommendation_only",
                    "status" => "pending",
                    "business_data_change" => false,
                    "message" => "AI recommendation submitted for admin review."
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );

            $insertAudit = $this->pdo->prepare("
                INSERT INTO ai_agent_audit_logs (
                    action_id,
                    actor_user_id,
                    event_type,
                    event_details,
                    created_at
                ) VALUES (
                    :action_id,
                    :actor_user_id,
                    'action_proposed',
                    :event_details,
                    NOW()
                )
            ");

            $insertAudit->bindValue(
                ":action_id",
                $actionId,
                PDO::PARAM_INT
            );

            $insertAudit->bindValue(
                ":actor_user_id",
                $requestedBy,
                $requestedBy === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_INT
            );

            $insertAudit->bindValue(
                ":event_details",
                $auditDetails,
                PDO::PARAM_STR
            );

            $insertAudit->execute();

            $this->pdo->commit();

            return [
                "success" => true,
                "action_id" => $actionId,
                "status" => "pending",
                "message" => "Recommendation saved for admin review."
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log(
                "ActionProposalService error: " . $e->getMessage()
            );

            return [
                "success" => false,
                "message" => "The recommendation could not be saved."
            ];
        }
    }
}
