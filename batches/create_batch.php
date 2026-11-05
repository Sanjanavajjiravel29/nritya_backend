<?php
// batches/create_batch.php - Admin creates a new dance batch

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Strictly restricted to authenticated Admin accounts only
$adminUser = requireRole('admin');

$data = getRequestData();

$batchName = trim($data["batch_name"] ?? "");
$danceStyle = trim($data["dance_style"] ?? "Bharatanatyam");
$level = trim($data["level"] ?? "Foundation");
$instructorId = !empty($data["instructor_id"]) ? (int)$data["instructor_id"] : null;
$instructorName = trim($data["instructor_name"] ?? "");
$capacity = !empty($data["capacity"]) ? (int)preg_replace('/[^0-9]/', '', (string)$data["capacity"]) : 24;
if ($capacity <= 0) $capacity = 24;
$studio = trim($data["studio"] ?? "Studio 1");
$days = $data["days"] ?? "Tue, Thu, Sat";
$startTime = trim($data["start_time"] ?? "6:00 PM");
$endTime = trim($data["end_time"] ?? "7:00 PM");
$monthlyTuition = trim($data["monthly_tuition"] ?? "₹2,400");
$status = trim($data["status"] ?? "Active");

if ($batchName === "") {
    respond(false, "Batch name is required.", 400);
}

// Convert days array if provided
if (is_array($days)) {
    $days = implode(", ", $days);
}

// Resolve instructor ID from name if needed
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

$stmt = $conn->prepare(
    "INSERT INTO batches (batch_name, dance_style, level, instructor_id, capacity, studio, days, start_time, end_time, monthly_tuition, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    respond(false, "Database error: " . $conn->error, 500);
}

$stmt->bind_param("sssiissssss", $batchName, $danceStyle, $level, $instructorId, $capacity, $studio, $days, $startTime, $endTime, $monthlyTuition, $status);

if ($stmt->execute()) {
    $batchId = $conn->insert_id;
    $stmt->close();

    respond(true, "Batch created successfully.", 201, [
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
}

$err = $stmt->error;
$stmt->close();
respond(false, "Could not create batch: " . $err, 500);
?>
