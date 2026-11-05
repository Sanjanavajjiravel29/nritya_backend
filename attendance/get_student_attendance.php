<?php
// attendance/get_student_attendance.php - Get attendance stats and records for a student

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

// Authenticate caller
$authUser = authenticate();

$studentId = isset($_GET["student_id"]) ? (int)$_GET["student_id"] : (isset($_GET["id"]) ? (int)$_GET["id"] : null);

// Resource ownership: students can ONLY access their own attendance records
if ($authUser["role"] === "student") {
    if (!$studentId) {
        $studentId = (int)$authUser["id"];
    } elseif ($studentId !== (int)$authUser["id"]) {
        respond(false, "Access denied. Students can only view their own attendance records.", 403);
    }
} elseif ($authUser["role"] === "instructor") {
    if (!$studentId) {
        respond(false, "A valid student_id is required.", 400);
    }
    $instBatches = getInstructorBatchIds($conn, (int)$authUser["id"]);
    if (empty($instBatches)) {
        respond(false, "Access denied. You do not teach any batches.", 403);
    }
    $inClause = implode(',', $instBatches);
    $checkStmt = $conn->prepare("SELECT 1 FROM batch_students WHERE student_id = ? AND batch_id IN ($inClause) LIMIT 1");
    $checkStmt->bind_param("i", $studentId);
    $checkStmt->execute();
    $isEnrolled = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$isEnrolled) {
        $checkStmt2 = $conn->prepare("SELECT 1 FROM users WHERE id = ? AND batch_id IN ($inClause) LIMIT 1");
        $checkStmt2->bind_param("i", $studentId);
        $checkStmt2->execute();
        $isEnrolled = $checkStmt2->get_result()->fetch_assoc();
        $checkStmt2->close();
    }

    if (!$isEnrolled) {
        respond(false, "Access denied. Student is not enrolled in your assigned batches.", 403);
    }
}

if (!$studentId || $studentId < 1) {
    respond(false, "A valid student_id is required.", 400);
}

// Student & batch details
$sStmt = $conn->prepare(
    "SELECT u.full_name, u.batch_id, b.batch_name, b.studio, b.start_time, b.end_time,
            inst.full_name as instructor_name
     FROM users u
     LEFT JOIN batches b ON u.batch_id = b.id
     LEFT JOIN users inst ON b.instructor_id = inst.id
     WHERE u.id = ?
     LIMIT 1"
);
$sStmt->bind_param("i", $studentId);
$sStmt->execute();
$student = $sStmt->get_result()->fetch_assoc();
$sStmt->close();

if (!$student) {
    respond(false, "Student not found.", 404);
}

// Attendance records
$stmt = $conn->prepare(
    "SELECT a.id, a.attendance_date, a.status, a.notes,
            b.batch_name, b.studio, b.start_time, b.end_time,
            inst.full_name as instructor_name
     FROM attendance a
     JOIN batches b ON a.batch_id = b.id
     LEFT JOIN users inst ON a.marked_by = inst.id
     WHERE a.student_id = ?
     ORDER BY a.attendance_date DESC"
);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();

$records = [];
$presentCount = 0;
$absentCount = 0;
$excusedCount = 0;

while ($row = $result->fetch_assoc()) {
    $status = ucfirst(strtolower($row["status"]));
    if ($status === "Present") $presentCount++;
    elseif ($status === "Absent") $absentCount++;
    elseif ($status === "Excused") $excusedCount++;

    $records[] = [
        "id" => (int)$row["id"],
        "attendance_date" => $row["attendance_date"],
        "status" => $status,
        "notes" => $row["notes"] ?? "",
        "batch_name" => $row["batch_name"],
        "studio" => $row["studio"] ?? "Studio 1",
        "timing" => trim(($row["start_time"] ?? "") . " - " . ($row["end_time"] ?? "")),
        "instructor" => $row["instructor_name"] ?? "Academy Instructor"
    ];
}
$stmt->close();

$total = count($records);
$pct = $total > 0 ? round(($presentCount / $total) * 100) : 100;

respond(true, "Student attendance records retrieved successfully.", 200, [
    "student_name" => $student["full_name"],
    "batch_name" => $student["batch_name"] ?? "Not Enrolled",
    "studio" => $student["studio"] ?? "Studio 1",
    "timing" => trim(($student["start_time"] ?? "6:00 PM") . " - " . ($student["end_time"] ?? "7:00 PM")),
    "instructor" => $student["instructor_name"] ?? "Academy Instructor",
    "percentage" => $pct,
    "percentage_text" => "{$pct}%",
    "present_classes" => $presentCount,
    "absent_classes" => $absentCount,
    "excused_classes" => $excusedCount,
    "total_classes" => $total,
    "records" => $records
]);
?>
