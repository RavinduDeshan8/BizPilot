<?php

require_once __DIR__ . "/auth.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Require Admin
|--------------------------------------------------------------------------
*/

function requireAdmin()
{
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }

    if (
        !isset($_SESSION["user_role"]) ||
        $_SESSION["user_role"] !== "admin"
    ) {
        header("Location: ../index.php");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Require Staff or Admin
|--------------------------------------------------------------------------
*/

function requireStaff()
{
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }

    if (
        !isset($_SESSION["user_role"]) ||
        !in_array($_SESSION["user_role"], ["staff", "admin"])
    ) {
        header("Location: ../index.php");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Require Management Access
|--------------------------------------------------------------------------
*/

function requireManagementAccess()
{
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }

    if (
        !isset($_SESSION["user_role"]) ||
        !in_array($_SESSION["user_role"], ["admin", "staff"])
    ) {
        header("Location: ../index.php");
        exit;
    }
}