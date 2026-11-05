<?php
// batches/update_batch.php - Admin updates batch information

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Strictly restricted to authenticated Admin accounts only
$adminUser = requireRole('admin');

$data = getRequestData();

$batchId = !empty($data["id"]) ? (int)$data["id"] : (!empty($data["batch_id"]) ? (int)$data["batch_id"] : null);

if (!$batchId || $batchId < 1) {
    respond(false, "A valid batch ID is required.", 400);
}

// Fetch existing batch
$stmt = $conn->prepare("SELECT * FROM batches WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $batchId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    respond(false, "Batch not found.", 404);
}

$batchName = isset($data["batch_name"]) ? trim($data["batch_name"]) : $existing["batch_name"];
$danceStyle = isset($data["dance_style"]) ? trim($data["dance_style"]) : $existing["dance_style"];
$level = isset($data["level"]) ? trim($data["level"]) : $existing["level"];
$instructorId = array_key_exists("instructor_id", $data) ? ($data["instructor_id"] !== "" && $data["instructor_id"] !== null ? (int)$data["instructor_id"] : null) : $existing["instructor_id"];
$instructorName = isset($data["instructor_name"]) ? trim($data["instructor_name"]) : "";

if ($instructorId === null && $instructorName !== "") {
    $instStmt = $conn->prepare("SELECT id FROM users WHERE full_name LIKE ? AND role IN ('instructor', 'admin') LIMIT 1");
    $like = "%" . $instructorName . "%";
    $instStmt->bind_param("s", $like);
    $instStmt->execute();
    $instRow = $instStmt->get_result()->fetch_assoc();
    $instStmt->close();
    if ($instRow) {
        $instructorId = (int)$instRow["id"];
    }
}

$capacity = isset($data["capacity"]) ? (int)preg_replace('/[^0-9]/', '', (string)$data["capacity"]) : (int)$existing["capacity"];
if ($capacity <= 0) $capacity = 24;

$studio = isset($data["studio"]) ? trim($data["studio"]) : $existing["studio"];
$days = isset($data["days"]) ? (is_array($data["days"]) ? implode(", ", $data["days"]) : trim($data["days"])) : $existing["days"];
$startTime = isset($data["start_time"]) ? trim($data["start_time"]) : $existing["start_time"];
$endTime = isset($data["end_time"]) ? trim($data["end_time"]) : $existing["end_time"];
$monthlyTuition = isset($data["monthly_tuition"]) ? trim($data["monthly_tuition"]) : $existing["monthly_tuition"];
$status = isset($data["status"]) ? trim($data["status"]) : $existing["status"];

$stmt = $conn->prepare(
    "UPDATE batches 
     SET batch_name = ?, dance_style = ?, level = ?, instructor_id = ?, capacity = ?, 
         studio = ?, days = ?, start_time = ?, end_time = ?, monthly_tuition = ?, status = ?
     WHERE id = ?"
);

$stmt->bind_param("sssiissssssi", $batchName, $danceStyle, $level, $instructorId, $capacity, $studio, $days, $startTime, $endTime, $monthlyTuition, $status, $batchId);

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    respond(false, "Could not update batch: " . $err, 500);
}
$stmt->close();

respond(true, "Batch updated successfully.", 200, [
    "batch" => [
        "id" => $batchId,
        "batch_name" => $batchName,
        "dance_style" => $danceStyle,
        "level" => $level,
        "instructor_id" => $instructorId,
        "capacity" => $capacity,
        "studio" => $studio,
        "days" => $days,
        "start_time" => $startTime,
        "end_time" => $endTime,
        "monthly_tuition" => $monthlyTuition,
        "status" => $status
    ]
]);
?>
