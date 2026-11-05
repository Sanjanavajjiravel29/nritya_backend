<?php
// batches/get_batch.php - Get single batch details and enrolled student roster

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

// Require valid authentication
$authUser = authenticate();

$batchId = isset($_GET["id"]) ? (int)$_GET["id"] : (isset($_GET["batch_id"]) ? (int)$_GET["batch_id"] : null);

if (!$batchId || $batchId < 1) {
    respond(false, "A valid batch ID is required.", 400);
}

// Enforce batch authorization:
// - Admin: Allowed for all batches
// - Instructor: Allowed ONLY if assigned to this batch (403 otherwise)
// - Student: Allowed ONLY if actively enrolled in this batch (403 otherwise)
requireBatchOwnership($conn, $batchId, $authUser);

// Batch info
$stmt = $conn->prepare(
    "SELECT b.*, u.full_name as instructor_name, u.email as instructor_email, u.staff_role as instructor_role
     FROM batches b
     LEFT JOIN users u ON b.instructor_id = u.id
     WHERE b.id = ?
     LIMIT 1"
);
$stmt->bind_param("i", $batchId);
$stmt->execute();
$batch = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$batch) {
    respond(false, "Batch not found.", 404);
}

// Enrolled students
$studStmt = $conn->prepare(
    "SELECT u.id, u.full_name, u.username, u.email, u.mobile, u.profile_image, bs.enrolled_at,
            (SELECT COUNT(*) FROM attendance a WHERE a.batch_id = ? AND a.student_id = u.id AND a.status = 'Present') as attended_count,
            (SELECT COUNT(*) FROM attendance a WHERE a.batch_id = ? AND a.student_id = u.id) as total_classes
     FROM batch_students bs
     JOIN users u ON bs.student_id = u.id
     WHERE bs.batch_id = ? AND bs.status = 'Active'
     ORDER BY u.full_name ASC"
);
$studStmt->bind_param("iii", $batchId, $batchId, $batchId);
$studStmt->execute();
$studResult = $studStmt->get_result();

$students = [];
while ($s = $studResult->fetch_assoc()) {
    $nameParts = explode(" ", trim($s["full_name"]));
    $s["initials"] = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ""));
    $s["profile_image_url"] = formatMediaUrl($s["profile_image"]);
    
    $total = (int)$s["total_classes"];
    $att = (int)$s["attended_count"];
    $pct = $total > 0 ? round(($att / $total) * 100) : 0;
    $s["attendance_pct"] = $pct;
    $s["attendance_summary"] = "{$pct}% · {$att}/{$total} classes";
    $s["level_and_attendance"] = "{$batch['level']} · {$pct}% · {$att}/{$total} classes";
    $s["login_status"] = "Login Active";

    $students[] = $s;
}
$studStmt->close();

respond(true, "Batch details retrieved successfully.", 200, [
    "batch" => $batch,
    "enrolled_count" => count($students),
    "students" => $students
]);
?>
