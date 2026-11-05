<?php
// fees/list_fees.php - Admin / Student list fees & staff salary payouts

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

$authUser = authenticate();

$recordType = isset($_GET["type"]) ? trim($_GET["type"]) : (isset($_GET["record_type"]) ? trim($_GET["record_type"]) : null);
$userId = isset($_GET["user_id"]) ? (int)$_GET["user_id"] : null;
$status = isset($_GET["status"]) ? trim($_GET["status"]) : null;

// Enforce ownership and role security
if ($authUser["role"] === "student") {
    if ($userId && $userId !== (int)$authUser["id"]) {
        respond(false, "Access denied. Students can only view their own fee records.", 403);
    }
    if ($recordType === "staff_salary") {
        respond(false, "Access denied. Students cannot view staff salary information.", 403);
    }
    $userId = (int)$authUser["id"];
    $recordType = "student_fee";
} elseif ($authUser["role"] === "instructor") {
    respond(false, "Access denied. Instructors are not authorized to access fee or salary records.", 403);
} elseif ($authUser["role"] !== "admin") {
    respond(false, "Access denied. Unauthorized role.", 403);
}

$query = "
    SELECT f.*, 
           u.full_name as user_name, u.username, u.email, u.role as user_role, u.staff_role,
           b.batch_name, b.dance_style, b.level
    FROM fees_records f
    JOIN users u ON f.user_id = u.id
    LEFT JOIN batches b ON f.batch_id = b.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($recordType) {
    $query .= " AND f.record_type = ?";
    $params[] = $recordType;
    $types .= "s";
}

if ($userId) {
    $query .= " AND f.user_id = ?";
    $params[] = $userId;
    $types .= "i";
}

if ($status) {
    $query .= " AND f.status = ?";
    $params[] = ucfirst(strtolower($status));
    $types .= "s";
}

$query .= " ORDER BY f.id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$studentFeeRecords = [];
$staffSalaryRecords = [];
$allRecords = [];

while ($row = $result->fetch_assoc()) {
    $isPaid = (strtolower($row["status"]) === "paid");
    $item = [
        "id" => (int)$row["id"],
        "record_type" => $row["record_type"],
        "user_id" => (int)$row["user_id"],
        "user_name" => $row["user_name"],
        "user_role" => $row["user_role"],
        "staff_role" => $row["staff_role"],
        "batch_id" => $row["batch_id"] ? (int)$row["batch_id"] : null,
        "batch_name" => $row["batch_name"],
        "title" => $row["title"],
        "amount" => $row["amount"],
        "due_date" => $row["due_date"] ?? "Due upon receipt",
        "status" => ucfirst(strtolower($row["status"])),
        "is_paid" => $isPaid,
        "created_at" => $row["created_at"]
    ];

    if ($row["record_type"] === "student_fee") {
        $studentFeeRecords[] = $item;
    } else {
        $staffSalaryRecords[] = $item;
    }
    $allRecords[] = $item;
}
$stmt->close();

respond(true, "Fee records retrieved successfully.", 200, [
    "total_records" => count($allRecords),
    "student_fees" => $studentFeeRecords,
    "staff_salaries" => $staffSalaryRecords,
    "records" => $allRecords
]);
?>
