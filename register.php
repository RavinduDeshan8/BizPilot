<?php

require_once "config/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $password = $_POST["password"];

    if (
        empty($name) ||
        empty($email) ||
        empty($password)
    ) {

        $error = "Please fill all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Invalid email address.";

    } elseif (strlen($password) < 6) {

        $error = "Password must contain at least 6 characters.";

    } else {

        $check = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
        ");

        $check->execute([$email]);

        if ($check->fetch()) {

            $error = "Email already exists.";

        } else {

            $hashedPassword =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            $stmt = $pdo->prepare("
                INSERT INTO users
                (
                    full_name,
                    email,
                    phone,
                    address,
                    password
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $name,
                $email,
                $phone,
                $address,
                $hashedPassword
            ]);

            header("Location: login.php?registered=1");
            exit;
        }
    }
}

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Create Account | BizPilot AI</title>

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

<link
rel="stylesheet"
href="assets/css/style.css">

</head>

<body class="auth-page">

<div class="auth-container">

<div class="auth-card">

<div class="text-center mb-4">

<h2>
BizPilot <span class="text-success">AI</span>
</h2>

<p class="text-muted">
Create your customer account
</p>

</div>


<?php if ($error): ?>

<div class="alert alert-danger">
<?= htmlspecialchars($error) ?>
</div>

<?php endif; ?>


<form method="POST">

<div class="mb-3">

<label>Full Name *</label>

<input
type="text"
name="full_name"
class="form-control"
required>

</div>


<div class="mb-3">

<label>Email *</label>

<input
type="email"
name="email"
class="form-control"
required>

</div>


<div class="mb-3">

<label>Phone</label>

<input
type="text"
name="phone"
class="form-control">

</div>


<div class="mb-3">

<label>Address</label>

<textarea
name="address"
class="form-control"></textarea>

</div>


<div class="mb-3">

<label>Password *</label>

<input
type="password"
name="password"
class="form-control"
required>

</div>


<button
class="btn btn-success w-100 btn-lg">

Create Account

</button>

</form>


<div class="text-center mt-4">

Already have an account?

<a href="login.php">
Login
</a>

</div>

</div>

</div>

</body>

</html>