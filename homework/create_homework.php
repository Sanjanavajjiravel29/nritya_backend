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
$referenceId = !empty($data["reference_id"]) ? (int)$data["reference_id"] : null;
$isAiEvaluable = !empty($data["is_ai_evaluable"]) ? 1 : 0;

if (!$batchId || $title === "") {
    respond(false, "Batch ID and Assignment Title are required.", 400);
}

// Enforce instructor batch ownership
requireBatchOwnership($conn, $batchId, $authUser);

// Verify batch type
$bStmt = $conn->prepare("SELECT batch_name, batch_type FROM batches WHERE id = ? LIMIT 1");
$bStmt->bind_param("i", $batchId);
$bStmt->execute();
$batchInfo = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

$reference = null;
if ($isAiEvaluable && !$referenceId) {
    respond(false, "An AI-evaluable assignment must have a valid selected reference video.", 400);
}

if ($referenceId) {
    $refStmt = $conn->prepare("SELECT * FROM reference_videos WHERE id = ? AND status = 'Active' LIMIT 1");
    $refStmt->bind_param("i", $referenceId);
    $refStmt->execute();
    $reference = $refStmt->get_result()->fetch_assoc();
    $refStmt->close();

    if (!$reference) {
        respond(false, "The selected reference video is either inactive, deleted, or does not exist.", 400);
    }

    if ($batchInfo["batch_type"] === "FOUNDATION") {
        if ($reference["reference_type"] !== "ADMIN_COMMON") {
            respond(false, "Foundation batches can only select Admin-managed Foundation reference videos.", 403);
        }
    } elseif ($batchInfo["batch_type"] === "DANCE_DYNAMOS") {
        if ($reference["reference_type"] !== "INSTRUCTOR_BATCH" || (int)$reference["batch_id"] !== $batchId) {
            respond(false, "Dance Dynamos assignments must select a reference video belonging strictly to this batch.", 403);
        }
    }

    if (empty($referenceMediaUrl)) {
        $referenceMediaUrl = $reference["video_path"];
    }
}

if ($dueDate) {
    $time = strtotime($dueDate);
    $dueDate = ($time !== false) ? date("Y-m-d", $time) : null;
}

$stmt = $conn->prepare(
    "INSERT INTO homework (batch_id, instructor_id, title, topic, description, due_date, reference_media_url, reference_type, reference_id, is_ai_evaluable)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    respond(false, "Database preparation error: " . $conn->error, 500);
}

$stmt->bind_param("iissssssii", $batchId, $instructorId, $title, $topic, $description, $dueDate, $referenceMediaUrl, $referenceType, $referenceId, $isAiEvaluable);

if ($stmt->execute()) {
    $hwId = $conn->insert_id;
    $stmt->close();

    respond(true, "Homework assignment created successfully.", 201, [
        "homework" => [
            "id" => $hwId,
            "batch_id" => $batchId,
            "batch_name" => $batchInfo["batch_name"],
            "batch_type" => $batchInfo["batch_type"],
            "instructor_id" => $instructorId,
            "title" => $title,
            "topic" => $topic,
            "description" => $description,
            "due_date" => $dueDate,
            "reference_media_url" => $referenceMediaUrl,
            "reference_media_full_url" => formatMediaUrl($referenceMediaUrl),
            "reference_type" => $referenceType,
            "reference_id" => $referenceId,
            "is_ai_evaluable" => (bool)$isAiEvaluable,
            "reference" => $reference ? [
                "id" => $reference["id"],
                "title" => $reference["title"],
                "category" => $reference["category"],
                "description" => $reference["description"],
                "sollukattu" => $reference["sollukattu"],
                "video_full_url" => formatMediaUrl($reference["video_path"])
            ] : null,
            "created_at" => date("Y-m-d H:i:s")
        ]
    ]);
}

$err = $stmt->error;
$stmt->close();
respond(false, "Could not create homework assignment: " . $err, 500);
?>
