
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

/*
|--------------------------------------------------------------------------
| CSRF validation
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Validate input
|--------------------------------------------------------------------------
*/

$allowedTypes = [
    "cashier",
    "preparation",
    "pickup",
    "delivery"
];

$queueType = $_POST["queue_type"] ?? "";
$referenceNumber = trim($_POST["reference_number"] ?? "");
$customerName = trim($_POST["customer_name"] ?? "");
$notes = trim($_POST["notes"] ?? "");

if (
    !is_string($queueType) ||
    !in_array($queueType, $allowedTypes, true) ||
    !is_string($referenceNumber) ||
    $referenceNumber === "" ||
    strlen($referenceNumber) > 50 ||
    !is_string($customerName) ||
    strlen($customerName) > 150 ||
    !is_string($notes) ||
    strlen($notes) > 500
) {
    header("Location: ../admin/queue.php?error=invalid");
    exit;
}

/*
|--------------------------------------------------------------------------
| Add queue entry
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare("
        INSERT INTO queue_entries (
            queue_type,
            reference_number,
            customer_name,
            status,
            priority,
            notes
        ) VALUES (?, ?, ?, 'waiting', 0, ?)
    ");

    $stmt->execute([
        $queueType,
        $referenceNumber,
        $customerName !== "" ? $customerName : null,
        $notes !== "" ? $notes : null
    ]);

    header("Location: ../admin/queue.php?success=added");
    exit;

} catch (Throwable $e) {
    error_log("BizPilot queue add error: " . $e->getMessage());

    header("Location: ../admin/queue.php?error=save");
    exit;
}
