<?php

require_once "config/db.php";
require_once "config/auth.php";

requireLogin();

$userId = $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Get Cart Items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        cart_items.id AS cart_item_id,
        cart_items.quantity,
        products.id AS product_id,
        products.name,
        products.price,
        products.stock
    FROM cart
    INNER JOIN cart_items
        ON cart.id = cart_items.cart_id
    INNER JOIN products
        ON products.id = cart_items.product_id
    WHERE cart.user_id = ?
");

$stmt->execute([$userId]);

$items = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Calculate Total
|--------------------------------------------------------------------------
*/

$total = 0;

foreach ($items as $item) {

    $total +=
        (float) $item["price"] *
        (int) $item["quantity"];

}

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0">

<title>Shopping Cart | BizPilot AI</title>

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

<a
    href="products.php"
    class="btn btn-outline-light">

    Continue Shopping

</a>

</div>

</nav>


<div class="container py-5">

<h1 class="mb-4">
    Your Shopping Cart
</h1>


<?php if (empty($items)): ?>

<div class="empty-cart">

    <div class="empty-icon">
        🛒
    </div>

    <h3>Your cart is empty</h3>

    <p>
        Add some products to continue.
    </p>

    <a
        href="products.php"
        class="btn btn-success">

        Start Shopping

    </a>

</div>


<?php else: ?>


<div class="row">

<div class="col-lg-8">

<?php foreach ($items as $item): ?>

<div class="cart-item">

<div>

<h5>
    <?= htmlspecialchars($item["name"]) ?>
</h5>

<p class="text-muted mb-0">

    Rs.
    <?= number_format(
        (float) $item["price"],
        2
    ) ?>

</p>

<p class="small mb-0">

    <?php if ((int) $item["stock"] > 0): ?>

        <span class="text-success">
            <?= (int) $item["stock"] ?> available
        </span>

    <?php else: ?>

        <span class="text-danger">
            Out of stock
        </span>

    <?php endif; ?>

</p>

</div>


<div class="d-flex align-items-center gap-3">

<form
    action="actions/update_cart.php"
    method="POST">

<input
    type="hidden"
    name="cart_item_id"
    value="<?= (int) $item["cart_item_id"] ?>">

<input
    type="number"
    name="quantity"
    value="<?= (int) $item["quantity"] ?>"
    min="1"
    max="<?= (int) $item["stock"] ?>"
    class="form-control"
    style="width:90px">

<button
    type="submit"
    class="btn btn-sm btn-dark mt-2">

    Update

</button>

</form>


<strong>

    Rs.
    <?= number_format(
        (float) $item["price"] *
        (int) $item["quantity"],
        2
    ) ?>

</strong>


<a
    href="actions/remove_from_cart.php?id=<?= (int) $item["cart_item_id"] ?>"
    class="btn btn-outline-danger">

    ×

</a>

</div>

</div>

<?php endforeach; ?>

</div>


<div class="col-lg-4">

<div class="summary-card">

<h4>
    Order Summary
</h4>

<hr>

<div class="d-flex justify-content-between">

<span>Subtotal</span>

<strong>

    Rs.
    <?= number_format(
        $total,
        2
    ) ?>

</strong>

</div>

<div class="d-flex justify-content-between">

<span>Delivery</span>

<span>
    Calculated at checkout
</span>

</div>

<hr>

<div class="d-flex justify-content-between">

<strong>Total</strong>

<strong class="text-success">

    Rs.
    <?= number_format(
        $total,
        2
    ) ?>

</strong>

</div>


<a
    href="checkout.php"
    class="btn btn-success w-100 mt-4 btn-lg">

    Proceed to Checkout

</a>

</div>

</div>

</div>

<?php endif; ?>

</div>

</body>

</html>