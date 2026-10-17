<?php

require_once "../config/admin_auth.php";
requireStaff();

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| Load Active Products
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        products.id,
        products.name,
        products.sku,
        products.price,
        products.stock,
        products.image,
        categories.name AS category_name
    FROM products
    LEFT JOIN categories
        ON products.category_id = categories.id
    WHERE products.status = 'active'
    ORDER BY products.name ASC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>POS - BizPilot AI</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f4f7f5;
        }

        .pos-header {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .product-panel,
        .cart-panel {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .product-panel {
            padding: 20px;
        }

        .cart-panel {
            padding: 20px;
            position: sticky;
            top: 20px;
        }

        .product-card {
            border: 1px solid #e7ebe8;
            border-radius: 12px;
            padding: 15px;
            height: 100%;
            transition: 0.2s;
            background: #fff;
        }

        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .product-image {
            width: 100%;
            height: 130px;
            object-fit: contain;
            border-radius: 10px;
            background: #f7f9f8;
            margin-bottom: 12px;
        }

        .no-image {
            width: 100%;
            height: 130px;
            border-radius: 10px;
            background: #f1f4f2;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 45px;
            margin-bottom: 12px;
        }

        .product-name {
            font-weight: 600;
            min-height: 42px;
        }

        .product-price {
            color: #198754;
            font-size: 18px;
            font-weight: 700;
        }

        .stock-text {
            font-size: 13px;
            color: #777;
        }

        .low-stock {
            color: #dc3545;
            font-weight: 600;
        }

        .cart-item {
            border-bottom: 1px solid #eee;
            padding: 14px 0;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-name {
            font-weight: 600;
        }

        .qty-input {
            width: 75px;
        }

        .total-box {
            background: #f3f8f5;
            border-radius: 12px;
            padding: 18px;
        }

        .grand-total {
            font-size: 28px;
            font-weight: 800;
            color: #198754;
        }

        .empty-cart {
            text-align: center;
            padding: 50px 20px;
            color: #777;
        }

        @media (max-width: 991px) {

            .cart-panel {
                position: static;
                margin-top: 20px;
            }

        }

    </style>

</head>

<body>


<div class="container-fluid py-4 px-4">

    <?php require_once "../config/back-button.php"; ?>


    <!-- HEADER -->

    <div class="pos-header">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

            <div>

                <h2 class="mb-1">
                    🛒 Point of Sale
                </h2>

                <p class="text-muted mb-0">
                    Process physical store sales.
                </p>

            </div>


            <div class="d-flex gap-2">

                <a
                    href="index.php"
                    class="btn btn-outline-secondary"
                >
                    ← Dashboard
                </a>

                <a
                    href="orders.php"
                    class="btn btn-outline-success"
                >
                    📦 Orders
                </a>

            </div>

        </div>

    </div>


    <div class="row g-4">


        <!-- PRODUCTS -->

        <div class="col-lg-8">

            <div class="product-panel">

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

                    <div>

                        <h5 class="mb-1">
                            Products
                        </h5>

                        <small class="text-muted">
                            Select a product to add it to the sale.
                        </small>

                    </div>


                    <div style="min-width: 280px;">

                        <input
                            type="text"
                            id="productSearch"
                            class="form-control"
                            placeholder="🔍 Search product or SKU..."
                        >

                    </div>

                </div>


                <div class="row g-3" id="productGrid">


                    <?php foreach ($products as $product): ?>

                        <?php

                        $stock = (int) ($product["stock"] ?? 0);

                        $image = trim($product["image"] ?? "");

                        ?>


                        <div
                            class="col-md-4 col-xl-3 product-wrapper"
                            data-name="<?= htmlspecialchars(
                                strtolower($product["name"])
                            ) ?>"
                            data-sku="<?= htmlspecialchars(
                                strtolower($product["sku"] ?? "")
                            ) ?>"
                        >

                            <div class="product-card">


                                <?php if ($image !== ""): ?>

                                    <img
                                        src="../assets/images/<?= htmlspecialchars($image) ?>"
                                        class="product-image"
                                        alt="<?= htmlspecialchars($product["name"]) ?>"
                                    >

                                <?php else: ?>

                                    <div class="no-image">
                                        🛍️
                                    </div>

                                <?php endif; ?>


                                <div class="product-name">

                                    <?= htmlspecialchars(
                                        $product["name"]
                                    ) ?>

                                </div>


                                <div class="small text-muted mb-1">

                                    <?= htmlspecialchars(
                                        $product["category_name"]
                                        ?? "Uncategorized"
                                    ) ?>

                                </div>


                                <div class="product-price">

                                    Rs.
                                    <?= number_format(
                                        (float) $product["price"],
                                        2
                                    ) ?>

                                </div>


                                <div
                                    class="<?= $stock <= 5
                                        ? "stock-text low-stock"
                                        : "stock-text" ?>"
                                >

                                    Stock:
                                    <?= $stock ?>

                                </div>


                                <?php if ($stock > 0): ?>

                                    <button
                                        type="button"
                                        class="btn btn-success w-100 mt-3 add-product"
                                        data-id="<?= (int) $product["id"] ?>"
                                        data-name="<?= htmlspecialchars(
                                            $product["name"],
                                            ENT_QUOTES
                                        ) ?>"
                                        data-price="<?= (float) $product["price"] ?>"
                                        data-stock="<?= $stock ?>"
                                    >
                                        + Add
                                    </button>

                                <?php else: ?>

                                    <button
                                        type="button"
                                        class="btn btn-secondary w-100 mt-3"
                                        disabled
                                    >
                                        Out of Stock
                                    </button>

                                <?php endif; ?>


                            </div>

                        </div>

                    <?php endforeach; ?>


                </div>


                <?php if (empty($products)): ?>

                    <div class="text-center py-5">

                        <div style="font-size:50px;">
                            📦
                        </div>

                        <h5 class="mt-3">
                            No Active Products
                        </h5>

                        <p class="text-muted">
                            Add active products from the admin panel.
                        </p>

                    </div>

                <?php endif; ?>


            </div>

        </div>


        <!-- CART -->

        <div class="col-lg-4">

            <div class="cart-panel">

                <div class="d-flex justify-content-between align-items-center mb-3">

                    <div>

                        <h5 class="mb-1">
                            Current Sale
                        </h5>

                        <small class="text-muted">
                            Physical store customer
                        </small>

                    </div>


                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger"
                        id="clearCartBtn"
                    >
                        Clear
                    </button>

                </div>


                <!-- CART ITEMS -->

                <div id="cartItems">

                    <div class="empty-cart">

                        <div style="font-size:45px;">
                            🛒
                        </div>

                        <h6 class="mt-3">
                            Cart is Empty
                        </h6>

                        <p class="small">
                            Select products to begin the sale.
                        </p>

                    </div>

                </div>


                <!-- TOTAL -->

                <div
                    class="total-box mt-3"
                    id="totalBox"
                    style="display:none;"
                >

                    <div class="d-flex justify-content-between mb-2">

                        <span>
                            Items
                        </span>

                        <strong id="itemCount">
                            0
                        </strong>

                    </div>


                    <div class="d-flex justify-content-between mb-2">

                        <span>
                            Subtotal
                        </span>

                        <strong id="subtotal">
                            Rs. 0.00
                        </strong>

                    </div>


                    <hr>


                    <div class="d-flex justify-content-between align-items-center">

                        <span>
                            Total
                        </span>

                        <span
                            class="grand-total"
                            id="grandTotal"
                        >
                            Rs. 0.00
                        </span>

                    </div>

                </div>


                <!-- PAYMENT -->

                <div
                    id="paymentSection"
                    style="display:none;"
                >

                    <hr class="my-4">


                    <label class="form-label fw-semibold">
                        Payment Method
                    </label>


                    <select
                        id="paymentMethod"
                        class="form-select mb-3"
                    >

                        <option value="cash">
                            Cash
                        </option>

                        <option value="card">
                            Card
                        </option>

                    </select>


                    <!-- CASH RECEIVED -->

                    <div id="cashSection">

                        <label class="form-label fw-semibold">
                            Cash Received
                        </label>

                        <input
                            type="number"
                            id="cashReceived"
                            class="form-control"
                            min="0"
                            step="0.01"
                            placeholder="Enter cash amount"
                        >


                        <div class="d-flex justify-content-between mt-2">

                            <span class="text-muted">
                                Change
                            </span>

                            <strong
                                class="text-success"
                                id="changeAmount"
                            >
                                Rs. 0.00
                            </strong>

                        </div>

                    </div>


                    <button
                        type="button"
                        class="btn btn-success btn-lg w-100 mt-4"
                        id="completeSaleBtn"
                    >
                        ✓ Complete Sale
                    </button>

                </div>


            </div>

        </div>

    </div>

</div>


<script>

let cart = [];


/*
|--------------------------------------------------------------------------
| Add Product
|--------------------------------------------------------------------------
*/

document.querySelectorAll(".add-product").forEach(button => {

    button.addEventListener("click", function () {

        const id = parseInt(this.dataset.id);

        const name = this.dataset.name;

        const price = parseFloat(this.dataset.price);

        const stock = parseInt(this.dataset.stock);


        const existing = cart.find(item => item.id === id);


        if (existing) {

            if (existing.quantity >= stock) {

                alert("Cannot add more than available stock.");

                return;
            }

            existing.quantity++;

        } else {

            cart.push({
                id: id,
                name: name,
                price: price,
                stock: stock,
                quantity: 1
            });

        }


        renderCart();

    });

});


/*
|--------------------------------------------------------------------------
| Render Cart
|--------------------------------------------------------------------------
*/

function renderCart() {

    const cartItems = document.getElementById("cartItems");

    const totalBox = document.getElementById("totalBox");

    const paymentSection =
        document.getElementById("paymentSection");


    if (cart.length === 0) {

        cartItems.innerHTML = `
            <div class="empty-cart">

                <div style="font-size:45px;">
                    🛒
                </div>

                <h6 class="mt-3">
                    Cart is Empty
                </h6>

                <p class="small">
                    Select products to begin the sale.
                </p>

            </div>
        `;

        totalBox.style.display = "none";

        paymentSection.style.display = "none";

        return;
    }


    let html = "";

    let subtotal = 0;

    let itemCount = 0;


    cart.forEach((item, index) => {

        const itemSubtotal =
            item.price * item.quantity;

        subtotal += itemSubtotal;

        itemCount += item.quantity;


        html += `

            <div class="cart-item">

                <div class="d-flex justify-content-between gap-2">

                    <div>

                        <div class="cart-item-name">
                            ${escapeHtml(item.name)}
                        </div>

                        <small class="text-muted">
                            Rs. ${item.price.toFixed(2)}
                        </small>

                    </div>


                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger"
                        onclick="removeItem(${index})"
                    >
                        ×
                    </button>

                </div>


                <div class="d-flex justify-content-between align-items-center mt-2">

                    <div class="input-group input-group-sm qty-input">

                        <button
                            class="btn btn-outline-secondary"
                            onclick="changeQuantity(${index}, -1)"
                        >
                            −
                        </button>

                        <input
                            type="text"
                            class="form-control text-center"
                            value="${item.quantity}"
                            readonly
                        >

                        <button
                            class="btn btn-outline-secondary"
                            onclick="changeQuantity(${index}, 1)"
                        >
                            +
                        </button>

                    </div>


                    <strong>
                        Rs. ${itemSubtotal.toFixed(2)}
                    </strong>

                </div>

            </div>

        `;

    });


    cartItems.innerHTML = html;


    document.getElementById("itemCount").textContent =
        itemCount;


    document.getElementById("subtotal").textContent =
        "Rs. " + subtotal.toFixed(2);


    document.getElementById("grandTotal").textContent =
        "Rs. " + subtotal.toFixed(2);


    totalBox.style.display = "block";

    paymentSection.style.display = "block";


    calculateChange();

}


/*
|--------------------------------------------------------------------------
| Change Quantity
|--------------------------------------------------------------------------
*/

function changeQuantity(index, amount) {

    const item = cart[index];

    const newQuantity =
        item.quantity + amount;


    if (newQuantity <= 0) {

        cart.splice(index, 1);

    } else if (newQuantity > item.stock) {

        alert("Cannot exceed available stock.");

        return;

    } else {

        item.quantity = newQuantity;

    }


    renderCart();

}


/*
|--------------------------------------------------------------------------
| Remove Item
|--------------------------------------------------------------------------
*/

function removeItem(index) {

    cart.splice(index, 1);

    renderCart();

}


/*
|--------------------------------------------------------------------------
| Clear Cart
|--------------------------------------------------------------------------
*/

document
    .getElementById("clearCartBtn")
    .addEventListener("click", function () {

        if (cart.length === 0) {
            return;
        }

        if (
            confirm(
                "Are you sure you want to clear this sale?"
            )
        ) {

            cart = [];

            renderCart();

        }

    });


/*
|--------------------------------------------------------------------------
| Product Search
|--------------------------------------------------------------------------
*/

document
    .getElementById("productSearch")
    .addEventListener("input", function () {

        const search =
            this.value.toLowerCase().trim();


        document
            .querySelectorAll(".product-wrapper")
            .forEach(wrapper => {

                const name =
                    wrapper.dataset.name;

                const sku =
                    wrapper.dataset.sku;


                if (
                    name.includes(search) ||
                    sku.includes(search)
                ) {

                    wrapper.style.display = "";

                } else {

                    wrapper.style.display = "none";

                }

            });

    });


/*
|--------------------------------------------------------------------------
| Payment Method
|--------------------------------------------------------------------------
*/

document
    .getElementById("paymentMethod")
    .addEventListener("change", function () {

        const cashSection =
            document.getElementById("cashSection");


        if (this.value === "cash") {

            cashSection.style.display = "block";

        } else {

            cashSection.style.display = "none";

        }

    });


/*
|--------------------------------------------------------------------------
| Cash Received
|--------------------------------------------------------------------------
*/

document
    .getElementById("cashReceived")
    .addEventListener("input", function () {

        calculateChange();

    });


function calculateChange() {

    if (cart.length === 0) {
        return;
    }


    let total = 0;

    cart.forEach(item => {

        total +=
            item.price * item.quantity;

    });


    const cash =
        parseFloat(
            document.getElementById("cashReceived").value
        ) || 0;


    const change =
        Math.max(0, cash - total);


    document.getElementById("changeAmount").textContent =
        "Rs. " + change.toFixed(2);

}


/*
|--------------------------------------------------------------------------
| Complete Sale
|--------------------------------------------------------------------------
*/

document
    .getElementById("completeSaleBtn")
    .addEventListener("click", function () {

        if (cart.length === 0) {

            alert("Please add at least one product.");

            return;
        }


        const paymentMethod =
            document.getElementById("paymentMethod").value;


        let total = 0;


        cart.forEach(item => {

            total +=
                item.price * item.quantity;

        });


        if (paymentMethod === "cash") {

            const cash =
                parseFloat(
                    document.getElementById("cashReceived").value
                ) || 0;


            if (cash < total) {

                alert(
                    "Cash received is less than the total amount."
                );

                return;
            }

        }


        /*
        |--------------------------------------------------------------------------
        | Send Sale to PHP
        |--------------------------------------------------------------------------
        */

        const form = document.createElement("form");

        form.method = "POST";

        form.action = "../actions/create_pos_sale.php";


        const cartInput =
            document.createElement("input");

        cartInput.type = "hidden";

        cartInput.name = "cart";

        cartInput.value =
            JSON.stringify(cart);


        form.appendChild(cartInput);


        const paymentInput =
            document.createElement("input");

        paymentInput.type = "hidden";

        paymentInput.name = "payment_method";

        paymentInput.value =
            paymentMethod;


        form.appendChild(paymentInput);


        const cashInput =
            document.createElement("input");

        cashInput.type = "hidden";

        cashInput.name = "cash_received";

        cashInput.value =
            document.getElementById("cashReceived").value;


        form.appendChild(cashInput);


        document.body.appendChild(form);

        form.submit();

    });


/*
|--------------------------------------------------------------------------
| Escape HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

}

</script>

</body>

</html>