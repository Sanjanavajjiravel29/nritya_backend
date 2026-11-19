<?php
// references/get_reference.php - Retrieve a single reference video with role and batch security

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

$authUser = authenticate();

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($id <= 0) {
    respond(false, "A valid reference video ID is required.", 400);
}

$stmt = $conn->prepare("
    SELECT r.*,
           b.batch_name, b.dance_style as batch_dance_style, b.level as batch_level, b.batch_type,
           u.full_name as creator_name, u.role as creator_role
    FROM reference_videos r
    LEFT JOIN batches b ON r.batch_id = b.id
    LEFT JOIN users u ON r.created_by = u.id
    WHERE r.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$ref = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ref) {
    respond(false, "Reference video not found.", 404);
}

// Security & access control check
if ($ref["reference_type"] === "INSTRUCTOR_BATCH") {
    if ($authUser["role"] === "instructor") {
        if (!isBatchAssignedToInstructor($conn, $ref["batch_id"], $authUser["id"])) {
            respond(false, "Access denied. You can only access reference videos for batches you teach.", 403);
        }
    } elseif ($authUser["role"] === "student") {
        if (!isStudentEnrolledInBatch($conn, $ref["batch_id"], $authUser["id"])) {
            respond(false, "Access denied. You do not belong to the batch associated with this reference video.", 403);
        }
    }
}

if ($authUser["role"] === "student" && $ref["status"] !== "Active") {
    respond(false, "Reference video is currently inactive.", 403);
}

$ref["video_full_url"] = formatMediaUrl($ref["video_path"]);
$ref["file_size_formatted"] = $ref["file_size"] > 0 ? round($ref["file_size"] / (1024 * 1024), 1) . " MB" : "";

respond(true, "Reference video retrieved successfully.", 200, [
    "reference" => $ref
]);
?>
