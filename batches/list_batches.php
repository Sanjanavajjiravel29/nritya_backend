<?php
// batches/list_batches.php - List batches with instructor assignment & enrollment scoping

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

// Require valid authentication
$authUser = authenticate();

$instructorId = isset($_GET["instructor_id"]) ? (int)$_GET["instructor_id"] : null;
$studentId = isset($_GET["student_id"]) ? (int)$_GET["student_id"] : null;
$status = isset($_GET["status"]) ? trim($_GET["status"]) : null;

// Enforce role-based batch visibility
if ($authUser["role"] === "instructor") {
    // An instructor can ONLY see batches assigned to themselves
    if ($instructorId && $instructorId !== (int)$authUser["id"]) {
        respond(false, "Access denied. Instructors can only view their own assigned batches.", 403);
    }
    $instructorId = (int)$authUser["id"];
} elseif ($authUser["role"] === "student") {
    // A student can ONLY see batches they are actively enrolled in
    if ($studentId && $studentId !== (int)$authUser["id"]) {
        respond(false, "Access denied. Students can only view their enrolled batches.", 403);
    }
    $studentId = (int)$authUser["id"];
}

$query = "
    SELECT b.id, b.batch_name, b.dance_style, b.level, b.batch_type, b.instructor_id, 
           b.capacity, b.studio, b.days, b.start_time, b.end_time, 
           b.monthly_tuition, b.status, b.created_at,
           u.full_name as instructor_name, u.staff_role as instructor_role,
           (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id AND bs.status = 'Active') as enrolled_count
    FROM batches b
    LEFT JOIN users u ON b.instructor_id = u.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($instructorId) {
    $query .= " AND b.instructor_id = ?";
    $params[] = $instructorId;
    $types .= "i";
}

if ($studentId) {
    $query .= " AND EXISTS(SELECT 1 FROM batch_students bs WHERE bs.batch_id = b.id AND bs.student_id = ? AND bs.status = 'Active')";
    $params[] = $studentId;
    $types .= "i";
}

if ($status) {
    $query .= " AND b.status = ?";
    $params[] = $status;
    $types .= "s";
}

$query .= " ORDER BY b.id ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$batches = [];
while ($row = $result->fetch_assoc()) {
    $enrolled = (int)$row["enrolled_count"];
    $capacity = (int)$row["capacity"];
    $spaces = max(0, $capacity - $enrolled);

    $row["enrolled_count"] = $enrolled;
    $row["spaces_left"] = $spaces;
    $row["enrollment_badge"] = "{$enrolled} enrolled · {$spaces} spaces";
    $row["schedule"] = "{$row['days']} · {$row['start_time']}–{$row['end_time']}";
    $row["studio_badge"] = $row["studio"];
    $row["student_count_badge"] = "{$enrolled} students";

    // Split days into array
    $row["days_list"] = array_map('trim', explode(',', $row["days"]));

    $batches[] = $row;
}
$stmt->close();

respond(true, "Batches fetched successfully.", 200, [
    "count" => count($batches),
    "batches" => $batches
]);
?>
