<?php
// auth/resetpasswd.php - Admin-only password reset endpoint

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Strictly restricted to authenticated Admin
$authUser = requireRole('admin');

$data = getRequestData();

$email = strtolower(trim($data["email"] ?? ""));
$userId = !empty($data["user_id"]) ? (int)$data["user_id"] : null;
$newPassword = $data["new_password"] ?? "";

if (!$email && !$userId) {
    respond(false, "Either email or user_id is required.", 400);
}

if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, "Enter a valid email address.", 400);
}

if (strlen($newPassword) < 8) {
    respond(false, "Password must be at least 8 characters.", 400);
}

// Resolve target user
$targetUserId = null;
if ($userId) {
    $targetUserId = $userId;
    $chkStmt = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $chkStmt->bind_param("i", $targetUserId);
    $chkStmt->execute();
    $found = $chkStmt->get_result()->fetch_assoc();
    $chkStmt->close();
    if (!$found) {
        respond(false, "User not found.", 404);
    }
} else {
    $chkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $chkStmt->bind_param("s", $email);
    $chkStmt->execute();
    $found = $chkStmt->get_result()->fetch_assoc();
    $chkStmt->close();
    if (!$found) {
        respond(false, "User with this email not found.", 404);
    }
    $targetUserId = (int)$found["id"];
}

$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hashedPassword, $targetUserId);
$stmt->execute();
$stmt->close();

// Revoke all existing authentication tokens for the affected user
revokeAllUserTokens($conn, $targetUserId);

respond(true, "User password has been successfully reset by administrator and active sessions revoked.", 200, [
    "user_id" => $targetUserId
]);
?>
