<?php
// homework/review_submission.php - Instructor publishes feedback review & evaluation checklist

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Authorized roles: Admin and Instructor
$authUser = requireRole(['admin', 'instructor']);

$data = getRequestData();

$submissionId = !empty($data["submission_id"]) ? (int)$data["submission_id"] : null;
$feedback = trim($data["feedback"] ?? "");
$nextStep = trim($data["next_step"] ?? "");
$status = ucfirst(strtolower(trim($data["status"] ?? "Published")));
$checklist = $data["checklist"] ?? null;

if (!$submissionId) {
    respond(false, "submission_id is required.", 400);
}

// Check that submission exists and retrieve batch_id
$checkSubStmt = $conn->prepare("SELECT id, batch_id FROM homework_submissions WHERE id = ? LIMIT 1");
$checkSubStmt->bind_param("i", $submissionId);
$checkSubStmt->execute();
$subRow = $checkSubStmt->get_result()->fetch_assoc();
$checkSubStmt->close();

if (!$subRow) {
    respond(false, "Submission not found.", 404);
}

// Enforce batch ownership for instructors
if ($authUser["role"] === "instructor") {
    requireBatchOwnership($conn, (int)$subRow["batch_id"], $authUser);
    $instructorId = (int)$authUser["id"];
} else {
    // Admin can specify instructor_id or default to themselves
    $instructorId = !empty($data["instructor_id"]) ? (int)$data["instructor_id"] : (int)$authUser["id"];
}

// Convert checklist to JSON if array
if (is_array($checklist)) {
    $checklistJson = json_encode($checklist, JSON_UNESCAPED_UNICODE);
} elseif (is_string($checklist)) {
    $checklistJson = $checklist;
} else {
    $checklistJson = null;
}

// Check for linked AI movement analysis
$aiAnalysisId = null;
$aiCheckStmt = $conn->prepare("SELECT id FROM ai_movement_analyses WHERE submission_id = ? LIMIT 1");
$aiCheckStmt->bind_param("i", $submissionId);
$aiCheckStmt->execute();
$aiCheckRow = $aiCheckStmt->get_result()->fetch_assoc();
$aiCheckStmt->close();
if ($aiCheckRow) {
    $aiAnalysisId = (int)$aiCheckRow["id"];
}
$isAiValidated = ($aiAnalysisId !== null && $status === 'Published') ? 1 : 0;

// Upsert review record
$stmt = $conn->prepare(
    "INSERT INTO homework_reviews (submission_id, instructor_id, feedback, next_step, checklist, status, ai_analysis_id, is_ai_validated)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE 
       instructor_id = VALUES(instructor_id),
       feedback = VALUES(feedback),
       next_step = VALUES(next_step),
       checklist = COALESCE(VALUES(checklist), checklist),
       status = VALUES(status),
       ai_analysis_id = VALUES(ai_analysis_id),
       is_ai_validated = VALUES(is_ai_validated)"
);

if (!$stmt) {
    respond(false, "Database error: " . $conn->error, 500);
}

$stmt->bind_param("iissssii", $submissionId, $instructorId, $feedback, $nextStep, $checklistJson, $status, $aiAnalysisId, $isAiValidated);

if ($stmt->execute()) {
    $reviewId = $conn->insert_id ?: $submissionId;
    $stmt->close();

    // If published, update submission status to 'Reviewed'
    if ($status === "Published") {
        $upStmt = $conn->prepare("UPDATE homework_submissions SET status = 'Reviewed' WHERE id = ?");
        $upStmt->bind_param("i", $submissionId);
        $upStmt->execute();
        $upStmt->close();
    }

    respond(true, "Instructor review " . strtolower($status) . " successfully.", 200, [
        "review" => [
            "id" => $reviewId,
            "submission_id" => $submissionId,
            "instructor_id" => $instructorId,
            "feedback" => $feedback,
            "next_step" => $nextStep,
            "status" => $status,
            "reviewed_at" => date("Y-m-d H:i:s")
        ]
    ]);
}

$err = $stmt->error;
$stmt->close();
respond(false, "Could not save review: " . $err, 500);
?>
