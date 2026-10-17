<?php

require_once "config/db.php";

$id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT 
        products.*,
        categories.name AS category_name
    FROM products
    INNER JOIN categories
        ON products.category_id = categories.id
    WHERE products.id = ?
    AND products.status = 'active'
");

$stmt->execute([$id]);

$product = $stmt->fetch();

if (!$product) {
    die("Product not found.");
}

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>
<?= htmlspecialchars($product['name']) ?> | BizPilot AI
</title>

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

<a
href="index.php"
class="navbar-brand fw-bold">

BizPilot AI

</a>

<div>

<a
href="products.php"
class="btn btn-outline-light">

Shop

</a>

<a
href="cart.php"
class="btn btn-success">

🛒 Cart

</a>

</div>

</div>

</nav>


<div class="container py-5">

<div class="row align-items-center">

<div class="col-md-6">

<div class="product-detail-image">

<?php if (!empty($product['image'])): ?>

<img
src="assets/images/<?= htmlspecialchars($product['image']) ?>"
alt="<?= htmlspecialchars($product['name']) ?>">

<?php else: ?>

<span>🛍️</span>

<?php endif; ?>

</div>

</div>


<div class="col-md-6">

<span class="badge bg-secondary">

<?= htmlspecialchars($product['category_name']) ?>

</span>

<h1 class="mt-3">

<?= htmlspecialchars($product['name']) ?>

</h1>

<p class="lead">

<?= htmlspecialchars($product['description'] ?? '') ?>

</p>

<h2 class="text-success">

Rs. <?= number_format($product['price'], 2) ?>

</h2>

<?php if (isset($product['unit'])): ?>

<p>

Unit:
<strong>
<?= htmlspecialchars($product['unit']) ?>
</strong>

</p>

<?php endif; ?>


<p>

Available:
<strong>
<?= (int)$product['stock'] ?>
</strong>

</p>


<?php if ((int)$product['stock'] > 0): ?>

<form
action="actions/add_to_cart.php"
method="POST">

<input
type="hidden"
name="product_id"
value="<?= $product['id'] ?>">

<div
class="input-group mb-3"
style="max-width:220px">

<button
type="button"
class="btn btn-outline-secondary"
onclick="decreaseQty()">

−

</button>

<input
id="quantity"
type="number"
name="quantity"
value="1"
min="1"
max="<?= (int)$product['stock'] ?>"
class="form-control text-center">

<button
type="button"
class="btn btn-outline-secondary"
onclick="increaseQty()">

+

</button>

</div>

<button
type="submit"
class="btn btn-success btn-lg">

🛒 Add to Cart

</button>

</form>

<?php else: ?>

<button
class="btn btn-secondary btn-lg"
disabled>

Out of Stock

</button>

<?php endif; ?>

</div>

</div>

</div>


<script>

function increaseQty() {

    const input = document.getElementById("quantity");

    let value = parseInt(input.value) || 1;

    const max = parseInt(input.max);

    if (value < max) {
        input.value = value + 1;
    }

}

function decreaseQty() {

    const input = document.getElementById("quantity");

    let value = parseInt(input.value) || 1;

    if (value > 1) {
        input.value = value - 1;
    }

}

</script>

</body>

</html>