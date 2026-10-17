
<?php


require_once __DIR__ . "/../config/admin_auth.php";
requireAdmin();

require_once __DIR__ . "/../config/db.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../admin/queue.php");
    exit;
}

$submittedToken = $_POST["csrf_token"] ?? "";
$sessionToken = $_SESSION["queue_csrf_token"] ?? "";

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === "" ||
    !hash_equals($sessionToken, $submittedToken)
) {
    header("Location: ../admin/queue.php?error=security");
    exit;
}

$entryId = filter_input(INPUT_POST, "entry_id", FILTER_VALIDATE_INT);
$newStatus = $_POST["status"] ?? "";

$allowedStatuses = [
    "waiting",
    "in_progress",
    "ready",
    "completed",
    "cancelled"
];

if (
    !$entryId ||
    !is_string($newStatus) ||
    !in_array($newStatus, $allowedStatuses, true)
) {
    header("Location: ../admin/queue.php?error=invalid");
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, status
        FROM queue_entries
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$entryId]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entry) {
        header("Location: ../admin/queue.php?error=notfound");
        exit;
    }

    $oldStatus = $entry["status"];

    /*
    | Completed/cancelled entries are terminal in this first version.
    | Prevent accidental reopening.
    */

    if (
        in_array($oldStatus, ["completed", "cancelled"], true) &&
        $newStatus !== $oldStatus
    ) {
        header("Location: ../admin/queue.php?error=closed");
        exit;
    }

    $startedAt = null;
    $completedAt = null;

    if ($newStatus === "in_progress" && $oldStatus === "waiting") {
        $startedAt = date("Y-m-d H:i:s");
    }

    if ($newStatus === "completed") {
        $completedAt = date("Y-m-d H:i:s");
    }

    if ($newStatus === "in_progress") {
        $stmt = $pdo->prepare("
            UPDATE queue_entries
            SET status = ?,
                started_at = COALESCE(started_at, NOW())
            WHERE id = ?
        ");

        $stmt->execute([$newStatus, $entryId]);

    } elseif ($newStatus === "completed") {
        $stmt = $pdo->prepare("
            UPDATE queue_entries
            SET status = ?,
                completed_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$newStatus, $entryId]);

    } else {
        $stmt = $pdo->prepare("
            UPDATE queue_entries
            SET status = ?
            WHERE id = ?
        ");

        $stmt->execute([$newStatus, $entryId]);
    }

    header("Location: ../admin/queue.php?success=updated");
    exit;

} catch (Throwable $e) {
    error_log("BizPilot queue update error: " . $e->getMessage());

    header("Location: ../admin/queue.php?error=update");
    exit;
}
