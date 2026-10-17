<?php

/**
 * BizPilot AI Agent - Business Data Tools
 *
 * Read-only access to verified retail business data.
 * No database-changing operations are allowed here.
 */

class BusinessDataTools
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Search active products using the real database.
     */
    public function searchProducts(
        string $keyword = "",
        int $limit = 10
    ): array {
        $keyword = trim($keyword);
        $limit = max(1, min($limit, 20));

        $sql = "
            SELECT
                p.id,
                p.name,
                p.price,
                p.stock,
                p.description,
                c.name AS category_name
            FROM products p
            LEFT JOIN categories c
                ON c.id = p.category_id
            WHERE p.status = 'active'
        ";

        $params = [];

        if ($keyword !== "") {
            $sql .= "
                AND (
                    p.name LIKE ?
                    OR p.description LIKE ?
                    OR c.name LIKE ?
                )
            ";

            $searchTerm = "%" . $keyword . "%";

            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY p.name ASC LIMIT " . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            static function (array $product): array {
                return [
                    "id" => (int) $product["id"],
                    "name" => $product["name"],
                    "category" =>
                        $product["category_name"] ?? "Uncategorized",
                    "price" => (float) $product["price"],
                    "stock" => (int) $product["stock"],
                    "availability" =>
                        (int) $product["stock"] > 0
                            ? "In Stock"
                            : "Out of Stock",
                    "description" =>
                        $product["description"] ?? ""
                ];
            },
            $products
        );
    }

    /**
     * Get products that need stock attention.
     *
     * This is a basic low-stock rule, not an ML prediction.
     */
    public function getLowStockProducts(
        int $threshold = 5
    ): array {
        $threshold = max(0, min($threshold, 1000));

        $stmt = $this->pdo->prepare("
            SELECT
                p.id,
                p.name,
                p.price,
                p.stock,
                c.name AS category_name
            FROM products p
            LEFT JOIN categories c
                ON c.id = p.category_id
            WHERE p.status = 'active'
              AND p.stock <= ?
            ORDER BY p.stock ASC, p.name ASC
            LIMIT 50
        ");

        $stmt->execute([$threshold]);

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            static function (array $product): array {
                return [
                    "id" => (int) $product["id"],
                    "name" => $product["name"],
                    "category" =>
                        $product["category_name"] ?? "Uncategorized",
                    "price" => (float) $product["price"],
                    "stock" => (int) $product["stock"]
                ];
            },
            $products
        );
    }
}
