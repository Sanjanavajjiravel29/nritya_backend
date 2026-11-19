<?php
// references/upload_reference.php - Admin and Instructor reference video upload

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Only Admin and Instructor are authorized
$authUser = requireRole(['admin', 'instructor']);
$userId = (int)$authUser["id"];
$userRole = $authUser["role"];

$data = getRequestData();

$title = trim($data["title"] ?? "");
$category = trim($data["category"] ?? "");
$description = trim($data["description"] ?? "");
$sollukattu = trim($data["sollukattu"] ?? "");
$difficultyLevel = trim($data["difficulty_level"] ?? "Beginner");
$batchId = !empty($data["batch_id"]) ? (int)$data["batch_id"] : null;

if ($title === "" || $category === "") {
    respond(false, "Title and Category are required fields.", 400);
}

// Access & Type Rules
if ($userRole === "instructor") {
    // Instructors MUST assign reference to a specific batch they teach
    if (!$batchId) {
        respond(false, "Instructors must specify a batch for the reference video.", 400);
    }
    requireBatchOwnership($conn, $batchId, $authUser);
    $referenceType = "INSTRUCTOR_BATCH";
} else {
    // Admin
    if ($batchId) {
        $referenceType = "INSTRUCTOR_BATCH";
    } else {
        $referenceType = "ADMIN_COMMON";
    }
}

$videoPath = "";
$videoFilename = "";
$fileSize = 0;
$mimeType = "video/mp4";

// Handle physical file upload if provided
if (isset($_FILES["video_file"]) && $_FILES["video_file"]["error"] === UPLOAD_ERR_OK) {
    $file = $_FILES["video_file"];

    // Size limit: 100 MB
    $maxBytes = 100 * 1024 * 1024;
    if ($file["size"] > $maxBytes) {
        respond(false, "Uploaded video exceeds the maximum allowed size of 100 MB.", 400);
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
        respond(false, "Failed to save uploaded video file to server storage.", 500);
    }
} else {
    // Support existing media path if re-linking or JSON request
    $existingPath = trim($data["video_path"] ?? "");
    if ($existingPath !== "") {
        // Prevent path traversal
        if (strpos($existingPath, '..') !== false) {
            respond(false, "Invalid file path provided.", 400);
        }
        $videoPath = $existingPath;
        $videoFilename = trim($data["video_filename"] ?? basename($existingPath));
        $fileSize = !empty($data["file_size"]) ? (int)$data["file_size"] : 0;
        $mimeType = trim($data["mime_type"] ?? "video/mp4");
    } else {
        respond(false, "Please upload a demonstration video file for the reference library.", 400);
    }
}

$stmt = $conn->prepare("
    INSERT INTO reference_videos 
    (title, category, description, sollukattu, difficulty_level, video_path, video_filename, file_size, mime_type, reference_type, batch_id, created_by, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
");

if (!$stmt) {
    respond(false, "Database error: " . $conn->error, 500);
}

$stmt->bind_param(
    "sssssssissii",
    $title,
    $category,
    $description,
    $sollukattu,
    $difficultyLevel,
    $videoPath,
    $videoFilename,
    $fileSize,
    $mimeType,
    $referenceType,
    $batchId,
    $userId
);

if ($stmt->execute()) {
    $newId = $conn->insert_id;
    $stmt->close();

    respond(true, "Reference video uploaded to library successfully.", 201, [
        "reference" => [
            "id" => $newId,
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
            "reference_type" => $referenceType,
            "batch_id" => $batchId,
            "created_by" => $userId,
            "status" => "Active",
            "created_at" => date("Y-m-d H:i:s")
        ]
    ]);
}

$err = $stmt->error;
$stmt->close();
respond(false, "Failed to create reference entry: " . $err, 500);
?>
