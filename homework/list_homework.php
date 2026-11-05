<?php
// homework/list_homework.php - List homework assignments with role and batch ownership scoping

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

// Require valid authentication
$authUser = authenticate();

$batchId = isset($_GET["batch_id"]) ? (int)$_GET["batch_id"] : null;
$studentId = isset($_GET["student_id"]) ? (int)$_GET["student_id"] : null;
$instructorId = isset($_GET["instructor_id"]) ? (int)$_GET["instructor_id"] : null;

// Enforce ownership checks
if ($authUser["role"] === "instructor") {
    if ($instructorId && $instructorId !== (int)$authUser["id"]) {
        respond(false, "Access denied. You can only view assignments for your own instruction.", 403);
    }
    $instructorId = (int)$authUser["id"];

    if ($batchId) {
        requireBatchOwnership($conn, $batchId, $authUser);
    }
} elseif ($authUser["role"] === "student") {
    if ($studentId && $studentId !== (int)$authUser["id"]) {
        respond(false, "Access denied. Students can only view their own assigned homework.", 403);
    }
    $studentId = (int)$authUser["id"];

    if ($batchId) {
        requireBatchOwnership($conn, $batchId, $authUser);
    } else {
        // Find enrolled batches for this student
        $studentBatchIds = getStudentBatchIds($conn, $studentId);
    }
}

$query = "
    SELECT h.*, b.batch_name, b.dance_style, b.level,
           u.full_name as instructor_name, u.staff_role as instructor_role
    FROM homework h
    JOIN batches b ON h.batch_id = b.id
    LEFT JOIN users u ON h.instructor_id = u.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($batchId) {
    $query .= " AND h.batch_id = ?";
    $params[] = $batchId;
    $types .= "i";
}

if ($instructorId) {
    $query .= " AND h.instructor_id = ?";
    $params[] = $instructorId;
    $types .= "i";
}

if ($authUser["role"] === "student" && !$batchId) {
    if (!empty($studentBatchIds)) {
        $inList = implode(',', $studentBatchIds);
        $query .= " AND h.batch_id IN ($inList)";
    } else {
        $query .= " AND 1=0"; // Student not enrolled in any batch
    }
}

$query .= " ORDER BY h.id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $row["reference_media_full_url"] = formatMediaUrl($row["reference_media_url"]);
    
    // Check if authenticated student has submitted
    if ($authUser["role"] === "student") {
        $subStmt = $conn->prepare("SELECT id, status, video_url, submitted_at FROM homework_submissions WHERE homework_id = ? AND student_id = ? LIMIT 1");
        $subStmt->bind_param("ii", $row["id"], $authUser["id"]);
        $subStmt->execute();
        $sub = $subStmt->get_result()->fetch_assoc();
        $subStmt->close();

        $row["my_submission"] = $sub;
        $row["is_submitted"] = (bool)$sub;
    }

    $items[] = $row;
}
$stmt->close();

respond(true, "Homework assignments fetched successfully.", 200, [
    "count" => count($items),
    "homework" => $items
]);
?>
