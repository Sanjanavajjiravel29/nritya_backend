<?php
// users/list_users.php - Admin / Staff lists users with role filtering & search

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

// Require valid authentication
$authUser = authenticate();

// Students are not permitted to list academy user directories
if ($authUser["role"] === "student") {
    respond(false, "Access denied. Students are not authorized to browse academy user directories.", 403);
}

$role = isset($_GET["role"]) ? strtolower(trim($_GET["role"])) : null;
$batchId = isset($_GET["batch_id"]) ? (int)$_GET["batch_id"] : null;
$search = isset($_GET["q"]) ? trim($_GET["q"]) : "";

// If instructor, enforce that they can only access their assigned batches and students
if ($authUser["role"] === "instructor") {
    $instBatchIds = getInstructorBatchIds($conn, $authUser["id"]);

    // If an instructor requests a specific batch_id, verify they teach it
    if ($batchId && !in_array($batchId, $instBatchIds, true)) {
        respond(false, "Access denied. You are not assigned to instruct this batch.", 403);
    }
}

$query = "
    SELECT u.id, u.full_name, u.username, u.mobile, u.email, u.role, 
           u.staff_role, u.monthly_salary, u.batch_id, u.bio, u.status, 
           u.profile_image, u.created_at,
           b.batch_name, b.dance_style, b.level, b.studio, b.days, b.start_time, b.end_time,
           (SELECT COUNT(*) FROM attendance a WHERE a.student_id = u.id AND a.status = 'Present') as attended_count,
           (SELECT COUNT(*) FROM attendance a WHERE a.student_id = u.id) as total_attendance_classes
    FROM users u
    LEFT JOIN batches b ON u.batch_id = b.id
    WHERE 1=1
";

$params = [];
$types = "";

// Role-based scoping for instructors
if ($authUser["role"] === "instructor") {
    if (!empty($instBatchIds)) {
        $inClause = implode(',', $instBatchIds);
        $query .= " AND (u.role IN ('instructor', 'admin') OR (u.role = 'student' AND (u.batch_id IN ($inClause) OR EXISTS(SELECT 1 FROM batch_students bs WHERE bs.batch_id IN ($inClause) AND bs.student_id = u.id))))";
    } else {
        $query .= " AND u.role IN ('instructor', 'admin')";
    }
}

if ($role && in_array($role, ["admin", "instructor", "student"])) {
    $query .= " AND u.role = ?";
    $params[] = $role;
    $types .= "s";
}

if ($batchId) {
    $query .= " AND (u.batch_id = ? OR EXISTS(SELECT 1 FROM batch_students bs WHERE bs.batch_id = ? AND bs.student_id = u.id))";
    $params[] = $batchId;
    $params[] = $batchId;
    $types .= "ii";
}

if ($search !== "") {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$query .= " ORDER BY u.id DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $row["profile_image_url"] = formatMediaUrl($row["profile_image"]);
    
    // Hide confidential salary unless caller is Admin or viewing self
    if ($authUser["role"] !== "admin" && (int)$row["id"] !== (int)$authUser["id"]) {
        $row["monthly_salary"] = null;
    }

    // Compute initials
    $nameParts = explode(" ", trim($row["full_name"]));
    $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ""));
    $row["initials"] = $initials;

    // Attendance percentage for students
    if ($row["role"] === "student" && $row["total_attendance_classes"] > 0) {
        $pct = round(($row["attended_count"] / $row["total_attendance_classes"]) * 100);
        $row["attendance_percentage"] = $pct . "%";
        $row["level_and_attendance"] = ($row["batch_name"] ?? "Foundation") . " · " . $pct . "% · " . $row["attended_count"] . "/" . $row["total_attendance_classes"] . " classes";
    } else {
        $row["attendance_percentage"] = "0%";
        $row["level_and_attendance"] = ($row["batch_name"] ?? "Not Enrolled");
    }

    $users[] = $row;
}
$stmt->close();

respond(true, "Users fetched successfully.", 200, [
    "count" => count($users),
    "users" => $users
]);
?>
