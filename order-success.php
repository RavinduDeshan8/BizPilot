<?php

require_once "config/db.php";
require_once "config/auth.php";

requireLogin();

$orderNumber =
    $_GET["order"] ?? "";

$stmt = $pdo->prepare("
    SELECT *
    FROM orders
    WHERE order_number = ?
    AND user_id = ?
");

$stmt->execute([
    $orderNumber,
    $_SESSION["user_id"]
]);

$order = $stmt->fetch();


if (!$order) {

    die("Order not found.");

}

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Order Confirmed | BizPilot AI</title>

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

<link
rel="stylesheet"
href="assets/css/style.css">

</head>

<body class="bg-light">

<div
class="container"
style="max-width:700px; padding-top:80px;">

<div class="bg-white p-5 rounded-4 shadow-sm text-center">

<div style="font-size:70px;">
✅
</div>

<h1 class="mt-3">
Order Confirmed!
</h1>

<p class="text-muted">
Thank you for shopping with BizPilot AI.
</p>


<div class="alert alert-success">

<strong>
Order Number
</strong>

<br>

<span style="font-size:25px;">

<?= htmlspecialchars(
    $order["order_number"]
) ?>

</span>

</div>


<div class="text-start mt-4">

<p>

<strong>Order Type:</strong>

<?php if (
    $order["fulfillment_type"]
    === "pickup"
): ?>

🏪 Store Pickup

<?php else: ?>

🚚 Home Delivery

<?php endif; ?>

</p>


<?php if (
    $order["fulfillment_type"]
    === "pickup"
): ?>

<p>

<strong>Pickup Date:</strong>

<?= htmlspecialchars(
    $order["pickup_date"]
) ?>

</p>

<p>

<strong>Pickup Time:</strong>

<?= htmlspecialchars(
    $order["pickup_time"]
) ?>

</p>

<div class="alert alert-warning text-center">

<strong>
Pickup Code
</strong>

<br>

<span style="font-size:28px;">

<?= htmlspecialchars(
    $order["pickup_code"]
) ?>

</span>

<br>

<small>
Show this code when collecting your order.
</small>

</div>

<?php else: ?>

<p>

<strong>Delivery Address:</strong>

<br>

<?= nl2br(
    htmlspecialchars(
        $order["delivery_address"]
    )
) ?>

</p>

<?php endif; ?>


<p>

<strong>Total:</strong>

<span class="text-success">

Rs.
<?= number_format(
    $order["total"],
    2
) ?>

</span>

</p>


<p>

<strong>Status:</strong>

<span class="badge bg-warning text-dark">

<?= htmlspecialchars(
    ucfirst($order["order_status"])
) ?>

</span>

</p>

</div>


<div class="d-flex gap-2 mt-4">

<a
href="products.php"
class="btn btn-dark flex-fill">

Continue Shopping

</a>

<a
href="orders.php"
class="btn btn-success flex-fill">

My Orders

</a>

</div>

</div>

</div>

</body>

</html>