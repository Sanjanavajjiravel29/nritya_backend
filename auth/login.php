<?php
// auth/login.php - Authenticate user across Admin, Instructor, Student roles & issue secure Bearer token

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

$data = getRequestData();

$identifier = trim($data["identifier"] ?? "");
$password = $data["password"] ?? "";

if ($identifier === "" || $password === "") {
    respond(false, "Email/username and password are required.", 400);
}

// Fetch user with batch details if enrolled
$stmt = $conn->prepare(
    "SELECT u.id, u.full_name, u.username, u.mobile, u.email, u.password, 
            u.role, u.staff_role, u.monthly_salary, u.batch_id, u.bio, 
            u.status, u.profile_image, u.created_at,
            b.batch_name, b.dance_style, b.level, b.studio, b.days, b.start_time, b.end_time
     FROM users u
     LEFT JOIN batches b ON u.batch_id = b.id
     WHERE (u.email = ? OR u.username = ?)
     LIMIT 1"
);

if (!$stmt) {
    respond(false, "Database query error: " . $conn->error, 500);
}

$stmt->bind_param("ss", $identifier, $identifier);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user["password"])) {
    respond(false, "Invalid email/username or password.", 401);
}

if ($user["status"] === "inactive") {
    respond(false, "This account is inactive. Please contact your academy administrator.", 403);
}

// Generate cryptographically secure token and store its SHA-256 hash
$tokenData = generateAuthToken($conn, $user["id"]);

// Remove password hash from response
unset($user["password"]);

$user["profile_image_url"] = formatMediaUrl($user["profile_image"]);

respond(true, "Login successful.", 200, [
    "user" => $user,
    "role" => $user["role"],
    "token" => $tokenData["token"],
    "expires_at" => $tokenData["expires_at"]
]);
?>
