<?php
// users/update_user.php - Update user details

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

$authUser = authenticate();

$data = getRequestData();

$userId = !empty($data["id"]) ? (int)$data["id"] : (!empty($data["user_id"]) ? (int)$data["user_id"] : null);

if (!$userId || $userId < 1) {
    respond(false, "A valid user ID is required.", 400);
}

// Ownership check: only Admin or user updating their own profile
if ($authUser["role"] !== "admin" && (int)$authUser["id"] !== $userId) {
    respond(false, "Access denied. You do not have permission to modify this user account.", 403);
}

// Fetch existing user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    respond(false, "User not found.", 404);
}

$fullName = isset($data["full_name"]) ? trim($data["full_name"]) : $existing["full_name"];
$email = isset($data["email"]) ? strtolower(trim($data["email"])) : $existing["email"];
$mobile = isset($data["mobile"]) ? trim($data["mobile"]) : $existing["mobile"];
$role = isset($data["role"]) ? strtolower(trim($data["role"])) : $existing["role"];
$staffRole = isset($data["staff_role"]) ? trim($data["staff_role"]) : $existing["staff_role"];
$monthlySalary = isset($data["monthly_salary"]) ? trim($data["monthly_salary"]) : $existing["monthly_salary"];
$batchId = array_key_exists("batch_id", $data) ? ($data["batch_id"] !== "" ? (int)$data["batch_id"] : null) : $existing["batch_id"];
$bio = isset($data["bio"]) ? trim($data["bio"]) : $existing["bio"];
$status = isset($data["status"]) ? strtolower(trim($data["status"])) : $existing["status"];

// Check email uniqueness if email changed
if ($email !== $existing["email"]) {
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $checkStmt->bind_param("si", $email, $userId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->fetch_assoc()) {
        $checkStmt->close();
        respond(false, "This email is already in use by another user.", 409);
    }
    $checkStmt->close();
}

// Password update if provided
$passwordHash = $existing["password"];
if (!empty($data["password"])) {
    $passwordHash = password_hash($data["password"], PASSWORD_DEFAULT);
}

// Profile image update if file uploaded
$imagePath = $existing["profile_image"];
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

$stmt = $conn->prepare(
    "UPDATE users 
     SET full_name = ?, email = ?, mobile = ?, role = ?, staff_role = ?, 
         monthly_salary = ?, batch_id = ?, bio = ?, status = ?, password = ?, profile_image = ?
     WHERE id = ?"
);

$stmt->bind_param("ssssssissssi", $fullName, $email, $mobile, $role, $staffRole, $monthlySalary, $batchId, $bio, $status, $passwordHash, $imagePath, $userId);

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    respond(false, "Could not update user: " . $err, 500);
}
$stmt->close();

// If student and batchId changed, sync batch_students
if ($role === "student" && $batchId !== null) {
    $sync = $conn->prepare("INSERT IGNORE INTO batch_students (batch_id, student_id, status) VALUES (?, ?, 'Active')");
    $sync->bind_param("ii", $batchId, $userId);
    $sync->execute();
    $sync->close();
}

respond(true, "User updated successfully.", 200, [
    "user" => [
        "id" => $userId,
        "full_name" => $fullName,
        "email" => $email,
        "mobile" => $mobile,
        "role" => $role,
        "staff_role" => $staffRole,
        "monthly_salary" => $monthlySalary,
        "batch_id" => $batchId,
        "bio" => $bio,
        "status" => $status,
        "profile_image_url" => formatMediaUrl($imagePath)
    ]
]);
?>

