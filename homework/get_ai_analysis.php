<?php
// homework/get_ai_analysis.php - Retrieve structured AI movement evaluation for a submission

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

$authUser = authenticate();

$submissionId = isset($_GET["submission_id"]) ? (int)$_GET["submission_id"] : (isset($_GET["id"]) ? (int)$_GET["id"] : null);

if (!$submissionId || $submissionId < 1) {
    respond(false, "A valid submission ID is required.", 400);
}

// Fetch submission and permission context
$stmt = $conn->prepare(
    "SELECT s.id, s.student_id, s.batch_id, s.homework_id, s.topic,
            h.title as homework_title, h.is_ai_evaluable,
            a.id as ai_id, a.reference_id, a.status as ai_status,
            a.overall_score, a.posture_score, a.aramandi_score,
            a.arm_alignment_score, a.stability_score, a.timing_sync_score,
            a.metrics_json, a.detected_strengths, a.areas_for_improvement,
            a.comparison_snapshot_url, a.error_message, a.processing_duration_sec,
            a.completed_at
     FROM homework_submissions s
     JOIN homework h ON s.homework_id = h.id
     LEFT JOIN ai_movement_analyses a ON a.submission_id = s.id
     WHERE s.id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $submissionId);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sub) {
    respond(false, "Submission not found.", 404);
}

// Role-based authorization
if ($authUser["role"] === "student") {
    if ((int)$sub["student_id"] !== (int)$authUser["id"]) {
        respond(false, "Access denied. You can only view your own AI evaluations.", 403);
    }
} elseif ($authUser["role"] === "instructor") {
    if (!isBatchAssignedToInstructor($conn, (int)$sub["batch_id"], (int)$authUser["id"])) {
        respond(false, "Access denied. You do not teach the batch for this submission.", 403);
    }
}

if (empty($sub["ai_id"])) {
    respond(true, "No AI movement analysis requested or available for this submission.", 200, [
        "has_ai_analysis" => false,
        "is_ai_evaluable" => (bool)$sub["is_ai_evaluable"],
        "analysis" => null
    ]);
}

$strengths = [];
if (!empty($sub["detected_strengths"])) {
    $strengths = array_values(array_filter(array_map('trim', explode("\n", $sub["detected_strengths"]))));
}

$improvements = [];
if (!empty($sub["areas_for_improvement"])) {
    $improvements = array_values(array_filter(array_map('trim', explode("\n", $sub["areas_for_improvement"]))));
}

$metrics = null;
if (!empty($sub["metrics_json"])) {
    $metrics = json_decode($sub["metrics_json"], true);
}

$snapshotFullUrl = !empty($sub["comparison_snapshot_url"]) ? formatMediaUrl($sub["comparison_snapshot_url"]) : null;

respond(true, "AI movement analysis retrieved successfully.", 200, [
    "has_ai_analysis" => true,
    "is_ai_evaluable" => (bool)$sub["is_ai_evaluable"],
    "analysis" => [
        "id" => (int)$sub["ai_id"],
        "submission_id" => $submissionId,
        "reference_id" => (int)$sub["reference_id"],
        "status" => $sub["ai_status"],
        "overall_score" => $sub["overall_score"] !== null ? (float)$sub["overall_score"] : null,
        "posture_score" => $sub["posture_score"] !== null ? (float)$sub["posture_score"] : null,
        "aramandi_score" => $sub["aramandi_score"] !== null ? (float)$sub["aramandi_score"] : null,
        "arm_alignment_score" => $sub["arm_alignment_score"] !== null ? (float)$sub["arm_alignment_score"] : null,
        "stability_score" => $sub["stability_score"] !== null ? (float)$sub["stability_score"] : null,
        "timing_sync_score" => $sub["timing_sync_score"] !== null ? (float)$sub["timing_sync_score"] : null,
        "strengths" => $strengths,
        "areas_for_improvement" => $improvements,
        "comparison_snapshot_url" => $sub["comparison_snapshot_url"],
        "comparison_snapshot_full_url" => $snapshotFullUrl,
        "metrics" => $metrics,
        "error_message" => $sub["error_message"],
        "processing_duration_sec" => $sub["processing_duration_sec"] !== null ? (float)$sub["processing_duration_sec"] : null,
        "completed_at" => $sub["completed_at"]
    ]
]);
?>