<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "expense_tracker_v2";

$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $database
);

if (!$conn) {
    die("Database connection failed.");
}

mysqli_set_charset($conn, "utf8mb4");
