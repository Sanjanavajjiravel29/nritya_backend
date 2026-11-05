<?php
// homework/create_homework.php - Instructor or Admin creates practice assignment

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Authorized roles: Admin and Instructor
$authUser = requireRole(['admin', 'instructor']);
$instructorId = (int)$authUser["id"];

$data = getRequestData();

$batchId = !empty($data["batch_id"]) ? (int)$data["batch_id"] : null;
$title = trim($data["title"] ?? "");
$topic = trim($data["topic"] ?? $title);
$description = trim($data["description"] ?? "");
$dueDate = !empty($data["due_date"]) ? trim($data["due_date"]) : null;
$referenceMediaUrl = trim($data["reference_media_url"] ?? "");
$referenceType = trim($data["reference_type"] ?? "video");

if (!$batchId || $title === "") {
    respond(false, "Batch ID and Assignment Title are required.", 400);
}

// Enforce instructor batch ownership
requireBatchOwnership($conn, $batchId, $authUser);

if ($dueDate) {
    $time = strtotime($dueDate);
    $dueDate = ($time !== false) ? date("Y-m-d", $time) : null;
}

$stmt = $conn->prepare(
    "INSERT INTO homework (batch_id, instructor_id, title, topic, description, due_date, reference_media_url, reference_type)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    respond(false, "Database preparation error: " . $conn->error, 500);
}

$stmt->bind_param("iissssss", $batchId, $instructorId, $title, $topic, $description, $dueDate, $referenceMediaUrl, $referenceType);

if ($stmt->execute()) {
    $hwId = $conn->insert_id;
    $stmt->close();

    respond(true, "Homework assignment created successfully.", 201, [
        "homework" => [
            "id" => $hwId,
            "batch_id" => $batchId,
            "instructor_id" => $instructorId,
            "title" => $title,
            "topic" => $topic,
            "description" => $description,
            "due_date" => $dueDate,
            "reference_media_url" => $referenceMediaUrl,
            "reference_type" => $referenceType,
            "created_at" => date("Y-m-d H:i:s")
        ]
    ]);
}

$err = $stmt->error;
$stmt->close();
respond(false, "Could not create homework assignment: " . $err, 500);
?>
