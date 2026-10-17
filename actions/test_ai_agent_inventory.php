
<?php

require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/ai_agent/BusinessDataTools.php";

requireAdmin();

header("Content-Type: text/plain; charset=UTF-8");
header("Cache-Control: no-store");

try {
    $tools = new BusinessDataTools($pdo);

    echo "BizPilot Inventory Tools Test\n";
    echo "=============================\n\n";

    $products = $tools->searchProducts("", 10);

    echo "ACTIVE PRODUCTS (Maximum 10):\n";
    echo json_encode(
        $products,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
    echo "\n\n";

    $lowStock = $tools->getLowStockProducts(5);

    echo "LOW STOCK PRODUCTS (Stock <= 5):\n";
    echo json_encode(
        $lowStock,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
    echo "\n\n";

    echo "INVENTORY TEST COMPLETED\n";

} catch (Throwable $e) {
    error_log(
        "BizPilot inventory test error: " . $e->getMessage()
    );

    http_response_code(500);
    echo "Test failed. Check the PHP error log.";
}
