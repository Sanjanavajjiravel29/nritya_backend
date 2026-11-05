<?php
// profile/fetch_profile.php - Fetch user profile with role, batch, and stats

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

$authUser = authenticate();

$userId = isset($_GET["user_id"]) ? (int)$_GET["user_id"] : (isset($_GET["id"]) ? (int)$_GET["id"] : null);

if (!$userId) {
    $userId = (int)$authUser["id"];
} elseif ($authUser["role"] === "student" && $userId !== (int)$authUser["id"]) {
    respond(false, "Access denied. Students can only view their own profile.", 403);
} elseif ($authUser["role"] === "instructor" && $userId !== (int)$authUser["id"]) {
    $instBatches = getInstructorBatchIds($conn, (int)$authUser["id"]);
    if (empty($instBatches)) {
        respond(false, "Access denied. You do not teach any batches.", 403);
    }
    $inClause = implode(',', $instBatches);
    $checkStmt = $conn->prepare("SELECT 1 FROM batch_students WHERE student_id = ? AND batch_id IN ($inClause) LIMIT 1");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $isEnrolled = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$isEnrolled) {
        $checkStmt2 = $conn->prepare("SELECT 1 FROM users WHERE id = ? AND batch_id IN ($inClause) LIMIT 1");
        $checkStmt2->bind_param("i", $userId);
        $checkStmt2->execute();
        $isEnrolled = $checkStmt2->get_result()->fetch_assoc();
        $checkStmt2->close();
    }

    if (!$isEnrolled) {
        respond(false, "Access denied. You can only view profiles of students in your assigned batches.", 403);
    }
}

$stmt = $conn->prepare(
    "SELECT u.id, u.full_name, u.username, u.mobile, u.email, u.role, 
            u.staff_role, u.monthly_salary, u.batch_id, u.bio, u.status, 
            u.profile_image, u.created_at,
            b.batch_name, b.dance_style, b.level, b.studio, b.days, b.start_time, b.end_time
     FROM users u
     LEFT JOIN batches b ON u.batch_id = b.id
     WHERE u.id = ?
     LIMIT 1"
);

if (!$stmt) {
    respond(false, "Database query failed.", 500);
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    respond(false, "User not found.", 404);
}

// Mask sensitive financial data
if ($authUser["role"] !== "admin" && (int)$authUser["id"] !== (int)$profile["id"]) {
    unset($profile["monthly_salary"]);
}

$profile["profile_image_url"] = formatMediaUrl($profile["profile_image"]);

$nameParts = explode(" ", trim($profile["full_name"]));
$profile["initials"] = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ""));

respond(true, "Profile fetched successfully.", 200, [
    "profile" => $profile
]);
?>
