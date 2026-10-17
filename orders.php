<?php

require_once "config/db.php";
require_once "config/auth.php";

requireLogin();

$stmt = $pdo->prepare("
    SELECT *
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([
    $_SESSION["user_id"]
]);

$orders = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>My Orders | BizPilot AI</title>

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

Shop

</a>

</div>

</nav>


<div class="container py-5">

<h1>
My Orders
</h1>

<p class="text-muted">
View and track your previous orders.
</p>


<?php if (empty($orders)): ?>

<div class="alert alert-info mt-4">

You haven't placed any orders yet.

<a href="products.php">
Start Shopping
</a>

</div>

<?php else: ?>


<div class="table-responsive mt-4">

<table class="table table-hover align-middle">

<thead class="table-dark">

<tr>

<th>Order</th>

<th>Type</th>

<th>Total</th>

<th>Status</th>

<th>Date</th>

</tr>

</thead>


<tbody>

<?php foreach ($orders as $order): ?>

<tr>

<td>

<strong>

<?= htmlspecialchars(
$order["order_number"]
) ?>

</strong>

</td>


<td>

<?php if (
$order["fulfillment_type"]
=== "pickup"
): ?>

🏪 Pickup

<?php else: ?>

🚚 Delivery

<?php endif; ?>

</td>


<td>

Rs.
<?= number_format(
$order["total"],
2
) ?>

</td>


<td>

<?php

$statusClass = "bg-warning text-dark";

if ($order["order_status"] === "completed") {
    $statusClass = "bg-success";
}

if ($order["order_status"] === "cancelled") {
    $statusClass = "bg-danger";
}

?>

<span
class="badge <?= $statusClass ?>">

<?= htmlspecialchars(
ucfirst(
$order["order_status"]
)
) ?>

</span>

</td>


<td>

<?= date(
"d M Y",
strtotime(
$order["created_at"]
)
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</body>

</html>