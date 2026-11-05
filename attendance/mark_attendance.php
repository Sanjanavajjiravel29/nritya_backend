<?php
// attendance/mark_attendance.php - Instructor or Admin records attendance for a batch

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Authorized roles: Admin and Instructor
$authUser = requireRole(['admin', 'instructor']);
$markedBy = (int)$authUser['id'];

$data = getRequestData();

$batchId = !empty($data["batch_id"]) ? (int)$data["batch_id"] : null;
$attendanceDate = !empty($data["attendance_date"]) ? trim($data["attendance_date"]) : (!empty($data["date"]) ? trim($data["date"]) : date("Y-m-d"));

if (!$batchId || $batchId < 1) {
    respond(false, "A valid batch_id is required.", 400);
}

// Enforce instructor batch ownership
requireBatchOwnership($conn, $batchId, $authUser);

// Format date if needed (e.g. YYYY-MM-DD)
$time = strtotime($attendanceDate);
if ($time === false) {
    $attendanceDate = date("Y-m-d");
} else {
    $attendanceDate = date("Y-m-d", $time);
}

// Students list can be an array of objects or single student
$studentsList = [];

if (isset($data["students"]) && is_array($data["students"])) {
    $studentsList = $data["students"];
} elseif (!empty($data["student_id"])) {
    $studentsList[] = [
        "student_id" => (int)$data["student_id"],
        "status" => trim($data["status"] ?? "Present"),
        "notes" => trim($data["notes"] ?? "")
    ];
}

if (empty($studentsList)) {
    respond(false, "No students specified for marking attendance.", 400);
}

$successCount = 0;
$stmt = $conn->prepare(
    "INSERT INTO attendance (batch_id, student_id, attendance_date, status, marked_by, notes)
     VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE 
        status = VALUES(status),
        marked_by = VALUES(marked_by),
        notes = VALUES(notes),
        created_at = CURRENT_TIMESTAMP"
);

if (!$stmt) {
    respond(false, "Database preparation error: " . $conn->error, 500);
}

foreach ($studentsList as $entry) {
    $sId = (int)($entry["student_id"] ?? ($entry["id"] ?? 0));
    $status = ucfirst(strtolower(trim($entry["status"] ?? "Present")));
    $notes = trim($entry["notes"] ?? "");

    if ($sId <= 0) continue;

    $validStatuses = ["Present", "Absent", "Excused"];
    if (!in_array($status, $validStatuses, true)) {
        $status = "Present";
    }

    $stmt->bind_param("iissis", $batchId, $sId, $attendanceDate, $status, $markedBy, $notes);
    if ($stmt->execute()) {
        $successCount++;
    }
}
$stmt->close();

respond(true, "Attendance successfully recorded for {$successCount} student(s).", 200, [
    "batch_id" => $batchId,
    "date" => $attendanceDate,
    "marked_count" => $successCount
]);
?>
