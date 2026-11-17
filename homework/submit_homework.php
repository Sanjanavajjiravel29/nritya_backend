<?php
// homework/submit_homework.php - Student submits practice video and note

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

$authUser = authenticate();

$data = getRequestData();

$studentId = !empty($data["student_id"]) ? (int)$data["student_id"] : (int)$authUser["id"];

if ($authUser["role"] === "student" && $studentId !== (int)$authUser["id"]) {
    respond(false, "Access denied. You can only submit assignments for your own student account.", 403);
}
$homeworkId = !empty($data["homework_id"]) ? (int)$data["homework_id"] : null;
$batchId = !empty($data["batch_id"]) ? (int)$data["batch_id"] : null;
$topic = trim($data["topic"] ?? "Tatta Adavu Practice");
$studentNote = trim($data["student_note"] ?? "");
$fileDetails = trim($data["file_details"] ?? "01:18 · 24 MB · Upload complete");
$fileName = trim($data["file_name"] ?? "");
$videoUrl = trim($data["video_url"] ?? "");

if (!$studentId) {
    respond(false, "A valid student_id is required.", 400);
}

// Fetch student & primary batch if not provided
$uStmt = $conn->prepare("SELECT batch_id, full_name FROM users WHERE id = ? LIMIT 1");
$uStmt->bind_param("i", $studentId);
$uStmt->execute();
$student = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if (!$student) {
    respond(false, "Student user not found.", 404);
}

if (!$batchId && !empty($student["batch_id"])) {
    $batchId = (int)$student["batch_id"];
}

// Verify batch enrollment if student
if ($authUser["role"] === "student" && $batchId) {
    if (!isStudentEnrolledInBatch($conn, $batchId, (int)$authUser["id"])) {
        respond(false, "Access denied. You are not enrolled in this batch.", 403);
    }
}

// Handle file upload if present
$uploadedFileKey = null;
if (isset($_FILES["video_file"])) {
    $err = $_FILES["video_file"]["error"];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        respond(false, "The uploaded file exceeds the maximum allowed upload limit (128 MB).", 413);
    } elseif ($err === UPLOAD_ERR_PARTIAL) {
        respond(false, "The file was only partially uploaded. Please try again.", 400);
    } elseif ($err === UPLOAD_ERR_OK) {
        $uploadedFileKey = "video_file";
    }
} elseif (isset($_FILES["file"])) {
    $err = $_FILES["file"]["error"];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        respond(false, "The uploaded file exceeds the maximum allowed upload limit (128 MB).", 413);
    } elseif ($err === UPLOAD_ERR_OK) {
        $uploadedFileKey = "file";
    }
}

if ($uploadedFileKey) {
    $file = $_FILES[$uploadedFileKey];
    $uploadDir = __DIR__ . '/../uploads/submissions/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $rawExt = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowedExts = ["mp4", "mov", "avi", "mkv", "webm", "pdf"];
    if (!in_array($rawExt, $allowedExts)) {
        respond(false, "Invalid file format. Allowed formats: MP4, MOV, AVI, MKV, WEBM, PDF.", 400);
    }
    $safeName = "sub_" . $studentId . "_" . bin2hex(random_bytes(8)) . "." . $rawExt;
    if (move_uploaded_file($file["tmp_name"], $uploadDir . $safeName)) {
        $videoUrl = "uploads/submissions/" . $safeName;
        $fileName = $file["name"];
        $sizeMB = round($file["size"] / (1024 * 1024), 1);
        $fileDetails = "Uploaded · {$sizeMB} MB";
    } else {
        respond(false, "Failed to save uploaded file to storage.", 500);
    }
}

if ($fileName === "") {
    $fileName = "submission_" . $studentId . ".mp4";
}
if ($videoUrl === "") {
    $videoUrl = "uploads/submissions/" . $fileName;
}

$stmt = $conn->prepare(
    "INSERT INTO homework_submissions (homework_id, batch_id, student_id, topic, video_url, file_name, file_details, student_note, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')"
);

if (!$stmt) {
    respond(false, "Database error: " . $conn->error, 500);
}

$stmt->bind_param("iiisssss", $homeworkId, $batchId, $studentId, $topic, $videoUrl, $fileName, $fileDetails, $studentNote);

if ($stmt->execute()) {
    $submissionId = $conn->insert_id;
    $stmt->close();

    respond(true, "Homework submitted to instructor successfully.", 201, [
        "submission" => [
            "id" => $submissionId,
            "student_id" => $studentId,
            "student_name" => $student["full_name"],
            "batch_id" => $batchId,
            "topic" => $topic,
            "video_url" => formatMediaUrl($videoUrl),
            "file_name" => $fileName,
            "file_details" => $fileDetails,
            "student_note" => $studentNote,
            "status" => "Pending",
            "submitted_at" => date("Y-m-d H:i:s")
        ]
    ]);
}

$err = $stmt->error;
$stmt->close();
respond(false, "Could not submit homework: " . $err, 500);
?>
