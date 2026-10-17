
<?php

require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/ai_agent/SalesAnalyticsTool.php";

requireAdmin();

header("Content-Type: text/plain; charset=UTF-8");
header("Cache-Control: no-store");

try {
    $salesTool = new SalesAnalyticsTool($pdo);

    echo "BizPilot Sales Analytics Test\n";
    echo "=============================\n\n";

    $summary = $salesTool->getSalesSummary(7);

    echo "Last 7 days summary:\n";
    echo json_encode(
        $summary,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
    echo "\n\n";

    $dailySales = $salesTool->getDailySales(7);

    echo "Daily sales:\n";
    echo json_encode(
        $dailySales,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
    echo "\n\n";

    echo "TEST COMPLETED\n";

} catch (Throwable $e) {
    error_log(
        "BizPilot sales test error: " . $e->getMessage()
    );

    http_response_code(500);
    echo "Test failed. Check the PHP error log.";
}
