<?php
// homework/list_submissions.php - Instructor or Admin lists student homework submissions

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

// Require valid authentication
$authUser = authenticate();

$instructorId = isset($_GET["instructor_id"]) ? (int)$_GET["instructor_id"] : null;
$batchId = isset($_GET["batch_id"]) ? (int)$_GET["batch_id"] : null;
$studentId = isset($_GET["student_id"]) ? (int)$_GET["student_id"] : null;
$status = isset($_GET["status"]) ? trim($_GET["status"]) : null;

// Enforce role and resource ownership
if ($authUser["role"] === "instructor") {
    if ($instructorId && $instructorId !== (int)$authUser["id"]) {
        respond(false, "Access denied. You can only view submissions for your assigned batches.", 403);
    }
    $instructorId = (int)$authUser["id"];

    if ($batchId) {
        requireBatchOwnership($conn, $batchId, $authUser);
    }
} elseif ($authUser["role"] === "student") {
    // A student can ONLY view their own submissions
    if ($studentId && $studentId !== (int)$authUser["id"]) {
        respond(false, "Access denied. Students can only view their own submissions.", 403);
    }
    $studentId = (int)$authUser["id"];
}

$query = "
    SELECT s.id, s.homework_id, s.batch_id, s.student_id, s.topic, s.video_url, 
           s.file_name, s.file_details, s.student_note, s.status, s.submitted_at,
           u.full_name as student_name, u.email as student_email, u.profile_image as student_image,
           b.batch_name, b.level as batch_level,
           r.id as review_id, r.feedback, r.next_step, r.reviewed_at
    FROM homework_submissions s
    JOIN users u ON s.student_id = u.id
    LEFT JOIN batches b ON s.batch_id = b.id
    LEFT JOIN homework_reviews r ON r.submission_id = s.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($instructorId) {
    $query .= " AND (b.instructor_id = ? OR EXISTS(SELECT 1 FROM homework h WHERE h.id = s.homework_id AND h.instructor_id = ?))";
    $params[] = $instructorId;
    $params[] = $instructorId;
    $types .= "ii";
}

if ($batchId) {
    $query .= " AND s.batch_id = ?";
    $params[] = $batchId;
    $types .= "i";
}

if ($studentId) {
    $query .= " AND s.student_id = ?";
    $params[] = $studentId;
    $types .= "i";
}

if ($status && strtolower($status) !== "all") {
    $query .= " AND s.status = ?";
    $params[] = ucfirst(strtolower($status));
    $types .= "s";
}

$query .= " ORDER BY s.id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$submissions = [];
while ($row = $result->fetch_assoc()) {
    $row["video_full_url"] = formatMediaUrl($row["video_url"]);
    $row["student_image_url"] = formatMediaUrl($row["student_image"]);

    $nameParts = explode(" ", trim($row["student_name"]));
    $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ""));
    $row["student_initials"] = $initials;
    $row["student_and_level"] = "{$row['student_name']} · {$row['batch_level']}";

    // Time ago formatted string
    $timeAgo = date("j M, g:i A", strtotime($row["submitted_at"]));
    $row["submitted_time_formatted"] = $timeAgo;

    $submissions[] = $row;
}
$stmt->close();

respond(true, "Submissions fetched successfully.", 200, [
    "count" => count($submissions),
    "submissions" => $submissions
]);
?>
