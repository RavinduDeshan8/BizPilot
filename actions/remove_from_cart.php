<?php

require_once "../config/db.php";
require_once "../config/auth.php";

requireLogin();

$id = intval($_GET["id"] ?? 0);


$stmt = $pdo->prepare("
DELETE cart_items
FROM cart_items
INNER JOIN cart
ON cart.id = cart_items.cart_id
WHERE cart_items.id = ?
AND cart.user_id = ?
");

$stmt->execute([
    $id,
    $_SESSION["user_id"]
]);


header("Location: ../cart.php");

exit;

?>