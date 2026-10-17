
<?php

require_once "config/db.php";
require_once "config/auth.php";

/*
|--------------------------------------------------------------------------
| POPULAR PRODUCTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        products.*,
        categories.name AS category_name
    FROM products
    INNER JOIN categories
        ON products.category_id = categories.id
    WHERE products.status = 'active'
    ORDER BY products.created_at DESC
    LIMIT 8
");

$products = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY name
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>BizPilot AI | Smart Retail Store</title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <link
        rel="stylesheet"
        href="assets/css/style.css">

</head>

<body>

<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">

    <div class="container">

        <a
            class="navbar-brand fw-bold"
            href="index.php">

            BizPilot <span>AI</span>

        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarMenu"
            aria-controls="navbarMenu"
            aria-expanded="false"
            aria-label="Toggle navigation">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div
            class="collapse navbar-collapse"
            id="navbarMenu">

            <ul class="navbar-nav ms-auto align-items-center">

                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="products.php">Shop</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="cart.php">🛒 Cart</a>
                </li>

                <?php if (isLoggedIn()): ?>

                    <?php if (
                        isset($_SESSION["user_role"]) &&
                        $_SESSION["user_role"] === "admin"
                    ): ?>

                        <li class="nav-item">
                            <a class="nav-link" href="admin/index.php">
                                📊 Admin Dashboard
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="admin/products.php">
                                📦 Products
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="admin/categories.php">
                                🗂️ Categories
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="admin/orders.php">
                                🛒 Orders
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="admin/customers.php">
                                👥 Customers
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="admin/reports.php">
                                📈 Reports
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="admin/expenses.php">
                                💰 Expenses
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="admin/profit-loss.php">
                                📊 Profit &amp; Loss
                            </a>
                        </li>

                    <?php elseif (
                        isset($_SESSION["user_role"]) &&
                        $_SESSION["user_role"] === "staff"
                    ): ?>

                        <li class="nav-item">
                            <a class="nav-link" href="staff/index.php">
                                🏠 Staff Dashboard
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="staff/pos.php">
                                🛒 POS
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="staff/orders.php">
                                📦 Orders
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                🏪 Store Website
                            </a>
                        </li>

                    <?php else: ?>

                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                📦 Orders
                            </a>
                        </li>

                    <?php endif; ?>

                    <li class="nav-item">
                        <span class="nav-link">
                            👤
                            <?= htmlspecialchars(
                                $_SESSION["user_name"] ?? "User",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </span>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link text-danger" href="logout.php">
                            Logout
                        </a>
                    </li>

                <?php else: ?>

                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>

                    <li class="nav-item">
                        <a class="btn btn-success ms-2" href="register.php">
                            Register
                        </a>
                    </li>

                <?php endif; ?>

            </ul>

        </div>

    </div>

</nav>

<!-- =========================================================
     HERO
========================================================= -->

<section class="hero">

    <div class="container">

        <div class="row align-items-center">

            <div class="col-lg-7">

                <div class="hero-badge">
                    SMART RETAIL EXPERIENCE
                </div>

                <h1>
                    Everything you need,
                    <br>
                    <strong>all in one place.</strong>
                </h1>

                <p>
                    Shop groceries, fruits, vegetables, personal care,
                    household products, beverages and more.
                </p>

                <div class="hero-buttons">

                    <a
                        href="products.php"
                        class="btn btn-success btn-lg">
                        Shop Now
                    </a>

                    <a
                        href="products.php"
                        class="btn btn-outline-dark btn-lg">
                        Browse Categories
                    </a>

                </div>

            </div>

            <div class="col-lg-5">

                <div class="hero-card">

                    <div class="hero-icon">🛒</div>

                    <h3>Shop Your Way</h3>

                    <p>
                        Order online and choose
                        store pickup or home delivery.
                    </p>

                    <div class="mini-feature">
                        🏪 Click &amp; Collect
                    </div>

                    <div class="mini-feature">
                        🚚 Home Delivery
                    </div>

                    <div class="mini-feature">
                        🤖 AI Shopping Assistant
                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<!-- =========================================================
     CATEGORIES
========================================================= -->

<section class="container py-5">

    <div class="section-title">

        <h2>Shop by Category</h2>

        <p>Find everything you need</p>

    </div>

    <div class="row g-4">

        <?php foreach ($categories as $category): ?>

            <div class="col-6 col-md-4 col-lg-3">

                <a
                    href="products.php?category=<?= (int) $category["id"] ?>"
                    class="category-card">

                    <div class="category-icon">

                        <?php

                        $icons = [
                            "Fruits" => '<img src="assets/images/fruits.jpg" alt="Product Image" style="width: 238.87px; height: 67.5px; object-fit: cover;">',
                            "Vegetables" => "🥕",
                            "Groceries" => "🛒",
                            "Biscuits & Snacks" => "🍪",
                            "Beverages" => "🥤",
                            "Personal Care" => "🧼",
                            "Household" => "🧹",
                            "Beauty & Fragrance" => "🌸",
                            "Baby Care" => "👶",
                            "Other" => "📦"
                        ];

                        echo $icons[$category["name"]] ?? "📦";

                        ?>

                    </div>

                    <h5>
                        <?= htmlspecialchars(
                            $category["name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </h5>

                </a>

            </div>

        <?php endforeach; ?>

    </div>

</section>

<!-- =========================================================
     POPULAR PRODUCTS
========================================================= -->

<section class="container py-5">

    <div class="section-title">

        <h2>Popular Products</h2>

        <p>Fresh and useful products for everyday life</p>

    </div>

    <div class="row g-4">

        <?php foreach ($products as $product): ?>

            <div class="col-6 col-md-4 col-lg-3">

                <div class="product-card">

                    <div class="product-image">

                        <?php if (!empty($product["image"])): ?>

                            <img
                                src="assets/images/<?= htmlspecialchars(
                                    $product["image"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                alt="<?= htmlspecialchars(
                                    $product["name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>">

                        <?php else: ?>

                            <span>🛍️</span>

                        <?php endif; ?>

                    </div>

                    <div class="product-info">

                        <small>
                            <?= htmlspecialchars(
                                $product["category_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </small>

                        <h5>
                            <?= htmlspecialchars(
                                $product["name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </h5>

                        <div class="product-bottom">

                            <strong>
                                Rs.
                                <?= number_format(
                                    (float) $product["price"],
                                    2
                                ) ?>
                            </strong>

                            <a
                                href="product.php?id=<?= (int) $product["id"] ?>"
                                class="btn btn-success btn-sm">
                                View
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <div class="text-center mt-5">

        <a
            href="products.php"
            class="btn btn-dark btn-lg">
            View All Products
        </a>

    </div>

</section>

<!-- =========================================================
     AI SHOPPING ASSISTANT
========================================================= -->

<section class="ai-section" id="ai-shopping-assistant">

    <div class="container">

        <div class="row align-items-center">

            <div class="col-lg-7">

                <div class="ai-label">
                    🤖 BIZPILOT AI
                </div>

                <h2>
                    Your intelligent shopping assistant.
                </h2>

                <p>
                    Get smart product recommendations and discover
                    products that match your needs.
                </p>

            </div>

            <div class="col-lg-5">

                <div class="ai-box">

                    <h4>What are you looking for?</h4>

                    <form id="aiAssistantForm">

                        <div class="input-group">

                            <input
                                type="text"
                                id="aiMessage"
                                name="message"
                                class="form-control"
                                placeholder="e.g. breakfast for 4 people"
                                maxlength="1000"
                                autocomplete="off"
                                aria-label="Ask the AI shopping assistant"
                                required>

                            <button
                                type="submit"
                                id="aiAskButton"
                                class="btn btn-success">
                                Ask AI
                            </button>

                        </div>

                    </form>

                    <div
                        id="aiResponse"
                        class="mt-3"
                        role="status"
                        aria-live="polite"
                        style="white-space: pre-wrap; overflow-wrap: anywhere;">
                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <div class="container text-center">

        <h5>BizPilot AI</h5>

        <p>
            Intelligent Omnichannel Retail Management Platform
        </p>

        <p class="mb-0">
            © 2026 BizPilot AI
        </p>

    </div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/app.js"></script>

<script src="assets/js/ai-assistant.js"></script>

</body>
</html>
