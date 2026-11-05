<?php
// users/get_user.php - Get user details by ID

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

// Require valid authentication
$authUser = authenticate();

$userId = isset($_GET["id"]) ? (int)$_GET["id"] : (isset($_GET["user_id"]) ? (int)$_GET["user_id"] : null);

if (!$userId || $userId < 1) {
    respond(false, "A valid user ID is required.", 400);
}

// Resource ownership checks
if ($authUser["role"] === "student") {
    if ($userId !== (int)$authUser["id"]) {
        respond(false, "Access denied. Students can only view their own user profile.", 403);
    }
} elseif ($authUser["role"] === "instructor") {
    if ($userId !== (int)$authUser["id"]) {
        $instBatches = getInstructorBatchIds($conn, $authUser["id"]);
        $isAuthorized = false;
        if (!empty($instBatches)) {
            $inList = implode(',', $instBatches);
            $checkStmt = $conn->prepare(
                "SELECT id FROM users 
                 WHERE id = ? AND role = 'student' 
                   AND (batch_id IN ($inList) OR EXISTS(SELECT 1 FROM batch_students bs WHERE bs.batch_id IN ($inList) AND bs.student_id = ?))
                 LIMIT 1"
            );
            $checkStmt->bind_param("ii", $userId, $userId);
            $checkStmt->execute();
            $isAuthorized = (bool)$checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();
        }

        if (!$isAuthorized) {
            respond(false, "Access denied. You can only view details of students enrolled in your assigned batches.", 403);
        }
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

$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    respond(false, "User not found.", 404);
}

// Mask sensitive salary data unless admin or viewing own record
if ($authUser["role"] !== "admin" && (int)$user["id"] !== (int)$authUser["id"]) {
    $user["monthly_salary"] = null;
}

$user["profile_image_url"] = formatMediaUrl($user["profile_image"]);

// Compute initials
$nameParts = explode(" ", trim($user["full_name"]));
$user["initials"] = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ""));

// Extra stats based on role
if ($user["role"] === "student") {
    // Attendance stats
    $attStmt = $conn->prepare(
        "SELECT 
            COUNT(*) as total_classes,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as attended,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'Excused' THEN 1 ELSE 0 END) as excused
         FROM attendance WHERE student_id = ?"
    );
    $attStmt->bind_param("i", $userId);
    $attStmt->execute();
    $att = $attStmt->get_result()->fetch_assoc();
    $attStmt->close();

    $total = (int)($att["total_classes"] ?? 0);
    $attended = (int)($att["attended"] ?? 0);
    $pct = $total > 0 ? round(($attended / $total) * 100) : 0;

    $user["attendance_pct"] = $pct;
    $user["attendance_classes_text"] = "{$attended}/{$total} classes";

    // Homework stats
    $hwStmt = $conn->prepare("SELECT COUNT(*) as submitted_count FROM homework_submissions WHERE student_id = ?");
    $hwStmt->bind_param("i", $userId);
    $hwStmt->execute();
    $hwCount = (int)$hwStmt->get_result()->fetch_assoc()["submitted_count"];
    $hwStmt->close();

    $user["submissions_count"] = $hwCount;
}

respond(true, "User details fetched successfully.", 200, [
    "user" => $user
]);
?>
