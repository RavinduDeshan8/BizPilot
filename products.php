<?php

require_once "config/db.php";

$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "
SELECT products.*, categories.name AS category_name
FROM products
INNER JOIN categories
ON products.category_id = categories.id
WHERE products.status = 'active'
";

$params = [];

if ($category !== '') {

    $sql .= " AND products.category_id = ?";
    $params[] = $category;

}

if ($search !== '') {

    $sql .= "
    AND (
        products.name LIKE ?
        OR products.description LIKE ?
    )
    ";

    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY products.name ASC";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$products = $stmt->fetchAll();

$categories = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY name
")->fetchAll();

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Shop | BizPilot AI</title>

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

<link
rel="stylesheet"
href="assets/css/style.css">

</head>

<body>

<nav class="navbar navbar-dark bg-dark">

<div class="container">

<a class="navbar-brand fw-bold" href="index.php">
BizPilot AI
</a>

<a class="btn btn-outline-light" href="cart.php">
🛒 Cart
</a>

</div>

</nav>


<div class="container py-5">

<div class="row mb-4">

<div class="col-lg-8">

<h1>Shop Products</h1>

<p class="text-muted">
Find everything you need for your home.
</p>

</div>

<div class="col-lg-4">

<form method="GET">

<div class="input-group">

<input
type="text"
name="search"
class="form-control"
placeholder="Search products..."
value="<?= htmlspecialchars($search) ?>">

<button class="btn btn-success">
Search
</button>

</div>

</form>

</div>

</div>


<div class="category-filter mb-5">

<a
href="products.php"
class="btn btn-dark btn-sm">

All

</a>

<?php foreach ($categories as $cat): ?>

<a
href="products.php?category=<?= $cat['id'] ?>"
class="btn btn-outline-dark btn-sm">

<?= htmlspecialchars($cat['name']) ?>

</a>

<?php endforeach; ?>

</div>


<div class="row g-4">

<?php if (count($products) === 0): ?>

<div class="col-12">

<div class="alert alert-warning">
No products found.
</div>

</div>

<?php endif; ?>


<?php foreach ($products as $product): ?>

<div class="col-6 col-md-4 col-lg-3">

<div class="product-card h-100">

<div class="product-image">

<?php if (!empty($product['image'])): ?>

<img
src="assets/images/<?= htmlspecialchars($product['image']) ?>"
alt="Product">

<?php else: ?>

<span>🛍️</span>

<?php endif; ?>

</div>

<div class="product-info">

<span class="product-category">
<?= htmlspecialchars($product['category_name']) ?>
</span>

<h5>
<?= htmlspecialchars($product['name']) ?>
</h5>

<p class="small text-muted">

<?= htmlspecialchars(
    substr($product['description'] ?? '', 0, 70)
) ?>

</p>

<strong class="price">

Rs. <?= number_format($product['price'], 2) ?>

</strong>

<p class="stock">

<?php if ((int)$product['stock'] > 0): ?>

<span class="text-success">
✓ <?= (int)$product['stock'] ?> available
</span>

<?php else: ?>

<span class="text-danger">
✕ Out of stock
</span>

<?php endif; ?>

</p>

<a
href="product.php?id=<?= $product['id'] ?>"
class="btn btn-success w-100">

View Product

</a>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

</body>

</html>