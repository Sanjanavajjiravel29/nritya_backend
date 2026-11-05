<?php
// attendance/get_batch_attendance.php - Get attendance for a batch on a date

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

// Strictly restricted to Admin and Instructor
$authUser = requireRole(['admin', 'instructor']);

$batchId = isset($_GET["batch_id"]) ? (int)$_GET["batch_id"] : null;
$date = isset($_GET["date"]) ? trim($_GET["date"]) : date("Y-m-d");

if (!$batchId || $batchId < 1) {
    respond(false, "A valid batch_id is required.", 400);
}

// Enforce instructor batch ownership
requireBatchOwnership($conn, $batchId, $authUser);

$time = strtotime($date);
$attendanceDate = ($time !== false) ? date("Y-m-d", $time) : date("Y-m-d");

// Get batch info
$bStmt = $conn->prepare("SELECT batch_name, studio, start_time, end_time, days FROM batches WHERE id = ? LIMIT 1");
$bStmt->bind_param("i", $batchId);
$bStmt->execute();
$batch = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

if (!$batch) {
    respond(false, "Batch not found.", 404);
}

// Fetch all enrolled students and their attendance status on this date
$query = "
    SELECT u.id as student_id, u.full_name, u.username, u.profile_image,
           a.id as attendance_id, a.status, a.notes, a.marked_by, a.created_at as marked_at
    FROM batch_students bs
    JOIN users u ON bs.student_id = u.id
    LEFT JOIN attendance a ON a.student_id = u.id AND a.batch_id = ? AND a.attendance_date = ?
    WHERE bs.batch_id = ? AND bs.status = 'Active'
    ORDER BY u.full_name ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("isi", $batchId, $attendanceDate, $batchId);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
$isSaved = false;
$presentCount = 0;
$absentCount = 0;
$excusedCount = 0;

while ($row = $result->fetch_assoc()) {
    $hasRecord = !empty($row["attendance_id"]);
    if ($hasRecord) {
        $isSaved = true;
    }

    $status = $row["status"] ? ucfirst($row["status"]) : "Present";
    if ($status === "Present") $presentCount++;
    elseif ($status === "Absent") $absentCount++;
    elseif ($status === "Excused") $excusedCount++;

    $nameParts = explode(" ", trim($row["full_name"]));
    $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ""));

    $students[] = [
        "student_id" => (int)$row["student_id"],
        "name" => $row["full_name"],
        "initials" => $initials,
        "avatar_url" => formatMediaUrl($row["profile_image"]),
        "status" => $status,
        "notes" => $row["notes"] ?? "",
        "attendance_id" => $row["attendance_id"] ? (int)$row["attendance_id"] : null
    ];
}
$stmt->close();

respond(true, "Batch attendance records fetched successfully.", 200, [
    "batch_id" => $batchId,
    "batch_name" => $batch["batch_name"],
    "date" => $attendanceDate,
    "is_saved" => $isSaved,
    "stats" => [
        "present" => $presentCount,
        "absent" => $absentCount,
        "excused" => $excusedCount,
        "total" => count($students)
    ],
    "students" => $students
]);
?>
