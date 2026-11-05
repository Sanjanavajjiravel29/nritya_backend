<?php
// homework/get_submission.php - Get single submission details for Review Practice screen

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

// Authenticate caller
$authUser = authenticate();

$submissionId = isset($_GET["id"]) ? (int)$_GET["id"] : (isset($_GET["submission_id"]) ? (int)$_GET["submission_id"] : null);

if (!$submissionId || $submissionId < 1) {
    respond(false, "A valid submission ID is required.", 400);
}

$stmt = $conn->prepare(
    "SELECT s.*, 
            u.full_name as student_name, u.email as student_email, u.profile_image as student_image,
            b.batch_name, b.level as batch_level,
            r.id as review_id, r.instructor_id, r.feedback, r.next_step, r.checklist, r.status as review_status, r.reviewed_at,
            inst.full_name as reviewer_name
     FROM homework_submissions s
     JOIN users u ON s.student_id = u.id
     LEFT JOIN batches b ON s.batch_id = b.id
     LEFT JOIN homework_reviews r ON r.submission_id = s.id
     LEFT JOIN users inst ON r.instructor_id = inst.id
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

// Resource ownership and role-based authorization
if ($authUser["role"] === "student") {
    if ((int)$sub["student_id"] !== (int)$authUser["id"]) {
        respond(false, "Access denied. You can only view your own submissions.", 403);
    }
} elseif ($authUser["role"] === "instructor") {
    $batchId = (int)$sub["batch_id"];
    if (!isBatchAssignedToInstructor($conn, $batchId, (int)$authUser["id"])) {
        respond(false, "Access denied. You are not assigned to the batch for this submission.", 403);
    }
}

$sub["video_full_url"] = formatMediaUrl($sub["video_url"]);
$sub["student_image_url"] = formatMediaUrl($sub["student_image"]);

$nameParts = explode(" ", trim($sub["student_name"]));
$initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ""));
$sub["student_initials"] = $initials;
$sub["student_name_and_batch"] = "{$sub['student_name']} · {$sub['batch_name']}";

// Format submission details string matching UI
$dateTimeStr = date("j M, g:i A", strtotime($sub["submitted_at"]));
$sub["submission_details_formatted"] = "{$sub['topic']} · Submitted to instructor on {$dateTimeStr} · {$sub['file_details']}";

// Evaluation checklist default templates
$defaultChecklist = [
    ["id" => "1", "title" => "Anga Shuddham", "description" => "Body posture and clarity of form", "isChecked" => true],
    ["id" => "2", "title" => "Aramandi", "description" => "Proper half-sitting stance maintenance", "isChecked" => true],
    ["id" => "3", "title" => "Mudras Clarity", "description" => "Hand gesture accuracy and precision", "isChecked" => true],
    ["id" => "4", "title" => "Bhavam", "description" => "Expressive facial and emotional engagement", "isChecked" => false],
    ["id" => "5", "title" => "Steps", "description" => "Correct execution of adavu steps", "isChecked" => true],
    ["id" => "6", "title" => "Tala", "description" => "Rhythm and time cycle synchronization", "isChecked" => true],
    ["id" => "7", "title" => "Leg Posture", "description" => "Foot placement and leg positioning", "isChecked" => true],
    ["id" => "8", "title" => "Neatness", "description" => "Cleanliness of movements and execution", "isChecked" => true],
    ["id" => "9", "title" => "Body Stiffness", "description" => "Flexibility and absence of unwanted tension", "isChecked" => false],
    ["id" => "10", "title" => "Back Position", "description" => "Straight spine and torso alignment", "isChecked" => true]
];

if (!empty($sub["checklist"])) {
    $decoded = json_decode($sub["checklist"], true);
    $sub["checklist_items"] = is_array($decoded) ? $decoded : $defaultChecklist;
} else {
    $sub["checklist_items"] = $defaultChecklist;
}

$sub["has_review"] = !empty($sub["review_id"]);

respond(true, "Submission details fetched successfully.", 200, [
    "submission" => $sub
]);
?>
