<?php
// profile/edit_profile.php - Edit user profile information

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

$authUser = authenticate();

$data = getRequestData();

$userId = !empty($data["user_id"]) ? (int)$data["user_id"] : (!empty($data["id"]) ? (int)$data["id"] : null);
$fullName = trim($data["full_name"] ?? "");
$email = strtolower(trim($data["email"] ?? ""));
$mobile = trim($data["mobile"] ?? "");
$bio = trim($data["bio"] ?? "");

if (!$userId) {
    $userId = (int)$authUser["id"];
} elseif ($authUser["role"] !== "admin" && (int)$authUser["id"] !== $userId) {
    respond(false, "Access denied. You can only edit your own profile.", 403);
}

// Fetch current user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    respond(false, "User not found.", 404);
}

if ($fullName === "") $fullName = $user["full_name"];
if ($email === "") $email = $user["email"];
if ($mobile === "") $mobile = $user["mobile"];
if ($bio === "") $bio = $user["bio"];

// Check email conflict
if ($email !== $user["email"]) {
    $eStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $eStmt->bind_param("si", $email, $userId);
    $eStmt->execute();
    if ($eStmt->get_result()->fetch_assoc()) {
        $eStmt->close();
        respond(false, "This email is already taken by another account.", 409);
    }
    $eStmt->close();
}

// Handle profile photo upload
$imagePath = $user["profile_image"];
if (isset($_FILES["profile_image"]) && $_FILES["profile_image"]["error"] === UPLOAD_ERR_OK) {
    $allowedTypes = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
    $imageInfo = getimagesize($_FILES["profile_image"]["tmp_name"]);
    if ($imageInfo && isset($allowedTypes[$imageInfo["mime"]])) {
        $uploadDir = __DIR__ . '/../uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filename = bin2hex(random_bytes(16)) . "." . $allowedTypes[$imageInfo["mime"]];
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $uploadDir . $filename)) {
            $imagePath = "uploads/profiles/" . $filename;
        }
    }
}

$stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, mobile = ?, bio = ?, profile_image = ? WHERE id = ?");
$stmt->bind_param("sssssi", $fullName, $email, $mobile, $bio, $imagePath, $userId);

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    respond(false, "Could not update profile: " . $err, 500);
}
$stmt->close();

respond(true, "Profile updated successfully.", 200, [
    "profile" => [
        "id" => $userId,
        "full_name" => $fullName,
        "email" => $email,
        "mobile" => $mobile,
        "bio" => $bio,
        "profile_image_url" => formatMediaUrl($imagePath)
    ]
]);
?>
