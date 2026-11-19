<?php
// references/update_reference.php - Edit reference details, replace video, or toggle active status

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

$authUser = requireRole(['admin', 'instructor']);
$userId = (int)$authUser["id"];
$userRole = $authUser["role"];

$data = getRequestData();

$id = isset($data["id"]) ? (int)$data["id"] : 0;
if ($id <= 0) {
    respond(false, "A valid reference video ID is required.", 400);
}

// Fetch existing reference
$stmt = $conn->prepare("SELECT * FROM reference_videos WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    respond(false, "Reference video not found.", 404);
}

// Role authorization
if ($userRole === "instructor") {
    // Instructors cannot edit Admin Common Foundation references
    if ($existing["reference_type"] === "ADMIN_COMMON") {
        respond(false, "Access denied. Only academy administrators can modify Foundation reference library items.", 403);
    }
    // Instructors can only edit their own batch references
    if (!isBatchAssignedToInstructor($conn, $existing["batch_id"], $userId)) {
        respond(false, "Access denied. You can only edit reference videos for your own assigned batches.", 403);
    }
}

// Prepare update fields
$title = isset($data["title"]) ? trim($data["title"]) : $existing["title"];
$category = isset($data["category"]) ? trim($data["category"]) : $existing["category"];
$description = isset($data["description"]) ? trim($data["description"]) : $existing["description"];
$sollukattu = isset($data["sollukattu"]) ? trim($data["sollukattu"]) : $existing["sollukattu"];
$difficultyLevel = isset($data["difficulty_level"]) ? trim($data["difficulty_level"]) : $existing["difficulty_level"];
$status = isset($data["status"]) ? ucfirst(strtolower(trim($data["status"]))) : $existing["status"];

if (!in_array($status, ["Active", "Inactive"], true)) {
    $status = $existing["status"];
}

$videoPath = $existing["video_path"];
$videoFilename = $existing["video_filename"];
$fileSize = $existing["file_size"];
$mimeType = $existing["mime_type"];

// Check if a replacement video file is uploaded
if (isset($_FILES["video_file"]) && $_FILES["video_file"]["error"] === UPLOAD_ERR_OK) {
    $file = $_FILES["video_file"];
    $maxBytes = 100 * 1024 * 1024;
    if ($file["size"] > $maxBytes) {
        respond(false, "Replacement video exceeds maximum limit of 100 MB.", 400);
    }

    $rawExt = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowedExts = ["mp4", "mov", "avi", "mkv", "webm"];
    if (!in_array($rawExt, $allowedExts, true)) {
        respond(false, "Invalid video format. Allowed formats: MP4, MOV, AVI, MKV, WEBM.", 400);
    }

    $uploadDir = __DIR__ . '/../uploads/references/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $safeName = "ref_" . $userId . "_" . bin2hex(random_bytes(8)) . "." . $rawExt;
    $targetPath = $uploadDir . $safeName;

    if (move_uploaded_file($file["tmp_name"], $targetPath)) {
        $videoPath = "uploads/references/" . $safeName;
        $videoFilename = basename($file["name"]);
        $fileSize = (int)$file["size"];
        $mimeType = $file["type"] ?? "video/" . ($rawExt === "mov" ? "quicktime" : $rawExt);
    } else {
        respond(false, "Failed to save replacement video file to server storage.", 500);
    }
}

$updateStmt = $conn->prepare("
    UPDATE reference_videos 
    SET title = ?, category = ?, description = ?, sollukattu = ?, difficulty_level = ?,
        video_path = ?, video_filename = ?, file_size = ?, mime_type = ?, status = ?
    WHERE id = ?
");

if (!$updateStmt) {
    respond(false, "Database error: " . $conn->error, 500);
}

$updateStmt->bind_param(
    "sssssssissi",
    $title,
    $category,
    $description,
    $sollukattu,
    $difficultyLevel,
    $videoPath,
    $videoFilename,
    $fileSize,
    $mimeType,
    $status,
    $id
);

if ($updateStmt->execute()) {
    $updateStmt->close();

    respond(true, "Reference video updated successfully.", 200, [
        "reference" => [
            "id" => $id,
            "title" => $title,
            "category" => $category,
            "description" => $description,
            "sollukattu" => $sollukattu,
            "difficulty_level" => $difficultyLevel,
            "video_path" => $videoPath,
            "video_full_url" => formatMediaUrl($videoPath),
            "video_filename" => $videoFilename,
            "file_size" => $fileSize,
            "file_size_formatted" => $fileSize > 0 ? round($fileSize / (1024 * 1024), 1) . " MB" : "",
            "mime_type" => $mimeType,
            "reference_type" => $existing["reference_type"],
            "batch_id" => $existing["batch_id"],
            "status" => $status
        ]
    ]);
}

$err = $updateStmt->error;
$updateStmt->close();
respond(false, "Failed to update reference: " . $err, 500);
?>
