
<?php
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Use POST method.", [], 405);
}

$data = request_data();

$name = trim($data["full_name"] ?? "");
$mobile = trim($data["mobile"] ?? "");
$email = strtolower(trim($data["email"] ?? ""));
$password = $data["password"] ?? "";

if ($name === "" || $mobile === "" ||
    $email === "" || $password === "") {
    respond(false, "All fields are required.", [], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, "Enter a valid email address.", [], 400);
}

if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $mobile)) {
    respond(false, "Enter a valid mobile number.", [], 400);
}

if (strlen($password) < 8) {
    respond(false, "Password must be at least 8 characters.", [], 400);
}

// Check whether email or mobile is already registered.
$stmt = $conn->prepare(
    "SELECT id FROM users WHERE email = ? OR mobile = ? LIMIT 1"
);
$stmt->bind_param("ss", $email, $mobile);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    respond(false, "Email or mobile number is already registered.", [], 409);
}
$stmt->close();

// Generate a username based on the full name.
$base = strtolower($name);
$base = preg_replace('/[^a-z0-9]/', '', $base);

if ($base === "") {
    $base = "user";
}

$base = substr($base, 0, 35);
$username = $base . random_int(1000, 9999);

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO users
    (full_name, username, mobile, email, password)
    VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param(
    "sssss",
    $name,
    $username,
    $mobile,
    $email,
    $hashedPassword
);

if ($stmt->execute()) {
    $stmt->close();
    respond(true, "Account created successfully.", [
        "username" => $username
    ], 201);
}

$stmt->close();
respond(false, "Could not create account. Please try again.", [], 500);
?>
