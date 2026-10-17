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

if (empty($items)) {
    header("Location: cart.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Calculate Subtotal
|--------------------------------------------------------------------------
*/

$subtotal = 0;

foreach ($items as $item) {

    $subtotal +=
        (float) $item["price"] *
        (int) $item["quantity"];

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0">

<title>Checkout | BizPilot AI</title>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

<link
    rel="stylesheet"
    href="assets/css/style.css">

<style>

.checkout-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    padding: 25px;
    margin-bottom: 20px;
}

.delivery-option {
    border: 2px solid #e5e7eb;
    border-radius: 15px;
    padding: 20px;
    cursor: pointer;
    transition: 0.2s;
}

.delivery-option:hover {
    border-color: #198754;
}

.delivery-option input {
    margin-right: 10px;
}

.checkout-summary {
    background: #f8fafc;
    border-radius: 18px;
    padding: 25px;
    position: sticky;
    top: 90px;
}

</style>

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
    href="cart.php"
    class="btn btn-outline-light">

    ← Back to Cart

</a>

</div>

</nav>


<div class="container py-5">

<h1 class="mb-4">
    Checkout
</h1>


<form
    action="actions/create_order.php"
    method="POST"
    id="checkoutForm">


<div class="row">

<div class="col-lg-8">


<!-- ORDER METHOD -->

<div class="checkout-card">

<h4 class="mb-4">
    1. Choose Order Method
</h4>


<div class="row g-3">


<div class="col-md-6">

<label class="delivery-option d-block">

<input
    type="radio"
    name="fulfillment_type"
    value="pickup"
    checked
    onchange="changeOrderType()">

<strong>
    🏪 Store Pickup
</strong>

<br>

<small class="text-muted">

    Order online and collect
    from the store.

</small>

</label>

</div>


<div class="col-md-6">

<label class="delivery-option d-block">

<input
    type="radio"
    name="fulfillment_type"
    value="delivery"
    onchange="changeOrderType()">

<strong>
    🚚 Home Delivery
</strong>

<br>

<small class="text-muted">

    Get your order delivered
    to your home.

</small>

</label>

</div>


</div>

</div>


<!-- PICKUP -->

<div
    class="checkout-card"
    id="pickupSection">

<h4>
    2. Pickup Details
</h4>

<p class="text-muted">

    Choose when you want to collect your order.

</p>


<div class="row">

<div class="col-md-6">

<label class="form-label">
    Pickup Date
</label>

<input
    type="date"
    name="pickup_date"
    id="pickup_date"
    class="form-control">

</div>


<div class="col-md-6">

<label class="form-label">
    Pickup Time
</label>

<select
    name="pickup_time"
    class="form-select">

<option value="Morning">
    Morning - 9:00 AM to 12:00 PM
</option>

<option value="Afternoon">
    Afternoon - 12:00 PM to 4:00 PM
</option>

<option value="Evening">
    Evening - 4:00 PM to 8:00 PM
</option>

</select>

</div>

</div>


<div class="mt-3">

<label class="form-label">
    Pickup Notes
</label>

<textarea
    name="notes"
    class="form-control"
    placeholder="Any special instructions?"></textarea>

</div>

</div>


<!-- DELIVERY -->

<div
    class="checkout-card"
    id="deliverySection"
    style="display:none;">

<h4>
    2. Delivery Details
</h4>

<p class="text-muted">

    Enter the address where you want
    your order delivered.

</p>


<label class="form-label">
    Delivery Address
</label>

<textarea
    name="delivery_address"
    id="delivery_address"
    class="form-control"
    rows="4"
    placeholder="House number, street, city..."></textarea>


<div class="mt-3">

<label class="form-label">
    Delivery Notes
</label>

<textarea
    name="delivery_notes"
    class="form-control"
    placeholder="Special delivery instructions"></textarea>

</div>

</div>


<!-- PAYMENT -->

<div class="checkout-card">

<h4>
    3. Payment Method
</h4>


<div class="form-check mb-3">

<input
    class="form-check-input"
    type="radio"
    name="payment_method"
    value="pay_at_store"
    id="payStore"
    checked>

<label
    class="form-check-label"
    for="payStore">

    🏪 Pay at Store

</label>

</div>


<div class="form-check">

<input
    class="form-check-input"
    type="radio"
    name="payment_method"
    value="cash_on_delivery"
    id="cod">

<label
    class="form-check-label"
    for="cod">

    💵 Cash on Delivery

</label>

</div>

</div>


<!-- CONFIRMATION -->

<div class="checkout-card">

<h4>
    4. Order Confirmation
</h4>

<p class="text-muted">

    Please check your order details
    before placing the order.

</p>


<div class="form-check">

<input
    class="form-check-input"
    type="checkbox"
    id="confirm"
    required>

<label
    class="form-check-label"
    for="confirm">

    I confirm that the order details
    are correct.

</label>

</div>

</div>


<button
    type="submit"
    class="btn btn-success btn-lg w-100">

    🛒 Place Order

</button>


</div>


<!-- SUMMARY -->

<div class="col-lg-4">

<div class="checkout-summary">

<h4>
    Order Summary
</h4>

<hr>


<?php foreach ($items as $item): ?>

<div class="d-flex justify-content-between mb-3">

<div>

<strong>
    <?= htmlspecialchars($item["name"]) ?>
</strong>

<br>

<small class="text-muted">

    <?= (int) $item["quantity"] ?>
    × Rs.
    <?= number_format(
        (float) $item["price"],
        2
    ) ?>

</small>

</div>

<strong>

    Rs.
    <?= number_format(
        (float) $item["price"] *
        (int) $item["quantity"],
        2
    ) ?>

</strong>

</div>

<?php endforeach; ?>


<hr>


<div class="d-flex justify-content-between">

<span>
    Subtotal
</span>

<strong>
    Rs.
    <?= number_format(
        $subtotal,
        2
    ) ?>
</strong>

</div>


<div class="d-flex justify-content-between mt-2">

<span>
    Delivery
</span>

<span id="deliveryFee">
    Free
</span>

</div>


<hr>


<div class="d-flex justify-content-between">

<strong>
    Total
</strong>

<strong
    class="text-success"
    id="totalAmount">

    Rs.
    <?= number_format(
        $subtotal,
        2
    ) ?>

</strong>

</div>


</div>

</div>

</div>

</form>

</div>


<script>

const subtotal =
<?= json_encode($subtotal) ?>;


function changeOrderType() {

    const type =
        document.querySelector(
            'input[name="fulfillment_type"]:checked'
        ).value;

    const pickup =
        document.getElementById(
            "pickupSection"
        );

    const delivery =
        document.getElementById(
            "deliverySection"
        );

    const address =
        document.getElementById(
            "delivery_address"
        );

    const pickupDate =
        document.getElementById(
            "pickup_date"
        );


    if (type === "pickup") {

        pickup.style.display = "block";

        delivery.style.display = "none";

        address.required = false;

        pickupDate.required = true;

        document.getElementById(
            "deliveryFee"
        ).innerText = "Free";

        document.getElementById(
            "totalAmount"
        ).innerText =
            "Rs. " +
            subtotal.toFixed(2);

    } else {

        pickup.style.display = "none";

        delivery.style.display = "block";

        address.required = true;

        pickupDate.required = false;

        document.getElementById(
            "deliveryFee"
        ).innerText = "Rs. 300.00";

        document.getElementById(
            "totalAmount"
        ).innerText =
            "Rs. " +
            (subtotal + 300).toFixed(2);

    }

}


document.addEventListener(
    "DOMContentLoaded",
    function() {

        const today =
            new Date()
                .toISOString()
                .split("T")[0];

        document.getElementById(
            "pickup_date"
        ).setAttribute(
            "min",
            today
        );

        document.getElementById(
            "pickup_date"
        ).value = today;

    }
);

</script>

</body>

</html>