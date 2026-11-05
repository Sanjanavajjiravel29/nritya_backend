<?php
// fees/create_fee.php - Admin records student fee structure or staff salary payout

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Strictly restricted to authenticated Admin accounts only
$adminUser = requireRole('admin');

$data = getRequestData();

$recordType = trim($data["record_type"] ?? "student_fee");
if (!in_array($recordType, ["student_fee", "staff_salary"], true)) {
    respond(false, "Invalid record type. Allowed values: student_fee, staff_salary.", 400);
}

$userId = !empty($data["user_id"]) ? (int)$data["user_id"] : (!empty($data["student_id"]) ? (int)$data["student_id"] : (!empty($data["staff_id"]) ? (int)$data["staff_id"] : null));
$userIdentifier = trim($data["user_identifier"] ?? (!empty($data["student_identifier"]) ? $data["student_identifier"] : ""));
$userName = trim($data["user_name"] ?? (!empty($data["student_name"]) ? $data["student_name"] : ""));
$batchId = !empty($data["batch_id"]) ? (int)$data["batch_id"] : null;
$title = trim($data["title"] ?? ($recordType === "staff_salary" ? "Monthly Instructor Payout" : "Academy Tuition Fee"));
$amount = trim($data["amount"] ?? "₹2,400");
$dueDate = trim($data["due_date"] ?? date("d M Y", strtotime("+15 days")));
$status = ucfirst(strtolower(trim($data["status"] ?? ($recordType === "staff_salary" ? "Calculated" : "Pending"))));

$validStatuses = ["Paid", "Pending", "Overdue", "Calculated"];
if (!in_array($status, $validStatuses, true)) {
    respond(false, "Invalid status. Allowed values: Paid, Pending, Overdue, Calculated.", 400);
}

$cleanAmount = preg_replace('/[^0-9]/', '', $amount);
if (empty($amount) || $cleanAmount === "" || (int)$cleanAmount <= 0) {
    respond(false, "A valid positive fee amount is required.", 400);
}
if (!str_starts_with($amount, '₹')) {
    $amount = '₹' . number_format((int)$cleanAmount);
}

// If userId is missing, try looking up via identifier or name
if (!$userId && $userIdentifier !== "") {
    $uStmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
    $uStmt->bind_param("ss", $userIdentifier, $userIdentifier);
    $uStmt->execute();
    $found = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    if ($found) {
        $userId = (int)$found["id"];
    }
}

if (!$userId && $userName !== "") {
    $uStmt = $conn->prepare("SELECT id FROM users WHERE full_name LIKE ? LIMIT 1");
    $like = "%" . $userName . "%";
    $uStmt->bind_param("s", $like);
    $uStmt->execute();
    $found = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    if ($found) {
        $userId = (int)$found["id"];
    }
}

if (!$userId) {
    respond(false, "A valid user ID or username/email is required.", 400);
}

// Fetch user name and batch if not set
$uStmt = $conn->prepare("SELECT full_name, batch_id FROM users WHERE id = ? LIMIT 1");
$uStmt->bind_param("i", $userId);
$uStmt->execute();
$uRow = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if (!$uRow) {
    respond(false, "Target user not found.", 404);
}

if (!$batchId && !empty($uRow["batch_id"])) {
    $batchId = (int)$uRow["batch_id"];
}

$stmt = $conn->prepare(
    "INSERT INTO fees_records (record_type, user_id, batch_id, title, amount, due_date, status)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    respond(false, "Database error: " . $conn->error, 500);
}

$stmt->bind_param("siissss", $recordType, $userId, $batchId, $title, $amount, $dueDate, $status);

if ($stmt->execute()) {
    $feeId = $conn->insert_id;
    $stmt->close();

    respond(true, "Fee record created successfully.", 201, [
        "fee_record" => [
            "id" => $feeId,
            "record_type" => $recordType,
            "user_id" => $userId,
            "user_name" => $uRow["full_name"],
            "batch_id" => $batchId,
            "title" => $title,
            "amount" => $amount,
            "due_date" => $dueDate,
            "status" => $status
        ]
    ]);
}

$err = $stmt->error;
$stmt->close();
respond(false, "Could not create fee record: " . $err, 500);
?>

