<?php

require_once "config/db.php";
require_once "config/auth.php";

if (isLoggedIn()) {

    header("Location: index.php");
    exit;

}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE email = ?
    ");

    $stmt->execute([$email]);

    $user = $stmt->fetch();

    if (
        $user &&
        password_verify($password, $user["password"])
    ) {

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["full_name"];
        $_SESSION["user_role"] = $user["role"];
        $_SESSION["user"] = $user;

        header("Location: index.php");
        exit;

    } else {

        $error = "Incorrect email or password.";

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

<title>Login | BizPilot AI</title>

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
Login to your account
</p>

</div>


<?php if ($error): ?>

<div class="alert alert-danger">
<?= htmlspecialchars($error) ?>
</div>

<?php endif; ?>


<?php if (isset($_GET["registered"])): ?>

<div class="alert alert-success">
Account created successfully. Please login.
</div>

<?php endif; ?>


<form method="POST">

<div class="mb-3">

<label>Email</label>

<input
type="email"
name="email"
class="form-control"
required>

</div>


<div class="mb-3">

<label>Password</label>

<input
type="password"
name="password"
class="form-control"
required>

</div>


<button
class="btn btn-success w-100 btn-lg">

Login

</button>

</form>


<div class="text-center mt-4">

Don't have an account?

<a href="register.php">
Create Account
</a>

</div>

</div>

</div>

</body>

</html>