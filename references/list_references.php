<?php
// references/list_references.php - Role and batch scoped listing of reference videos

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

// Require valid authentication
$authUser = authenticate();

$type = isset($_GET["type"]) ? trim(strtoupper($_GET["type"])) : "ALL";
$batchId = isset($_GET["batch_id"]) ? (int)$_GET["batch_id"] : null;
$category = isset($_GET["category"]) ? trim($_GET["category"]) : null;
$status = isset($_GET["status"]) ? trim($_GET["status"]) : null;

// Role-based scoping logic
if ($authUser["role"] === "instructor") {
    if ($batchId) {
        requireBatchOwnership($conn, $batchId, $authUser);
    }
} elseif ($authUser["role"] === "student") {
    if ($batchId) {
        requireBatchOwnership($conn, $batchId, $authUser);
    }
    // Students only ever see active references
    $status = "Active";
}

$query = "
    SELECT r.*,
           b.batch_name, b.dance_style as batch_dance_style, b.level as batch_level, b.batch_type,
           u.full_name as creator_name, u.role as creator_role
    FROM reference_videos r
    LEFT JOIN batches b ON r.batch_id = b.id
    LEFT JOIN users u ON r.created_by = u.id
    WHERE 1=1
";

$params = [];
$types = "";

// Status filter
if ($status && strtolower($status) !== "all") {
    $query .= " AND r.status = ?";
    $params[] = ucfirst(strtolower($status));
    $types .= "s";
}

// Category filter
if ($category && strtolower($category) !== "all") {
    $query .= " AND r.category = ?";
    $params[] = $category;
    $types .= "s";
}

// Reference Type and Scoping
if ($authUser["role"] === "admin") {
    if ($type === "ADMIN_COMMON") {
        $query .= " AND r.reference_type = 'ADMIN_COMMON'";
    } elseif ($type === "INSTRUCTOR_BATCH") {
        $query .= " AND r.reference_type = 'INSTRUCTOR_BATCH'";
        if ($batchId) {
            $query .= " AND r.batch_id = ?";
            $params[] = $batchId;
            $types .= "i";
        }
    } else {
        if ($batchId) {
            $query .= " AND (r.batch_id = ? OR r.reference_type = 'ADMIN_COMMON')";
            $params[] = $batchId;
            $types .= "i";
        }
    }
} elseif ($authUser["role"] === "instructor") {
    $instId = (int)$authUser["id"];
    $instBatchIds = getInstructorBatchIds($conn, $instId);
    $inList = !empty($instBatchIds) ? implode(',', $instBatchIds) : "0";

    if ($type === "ADMIN_COMMON") {
        $query .= " AND r.reference_type = 'ADMIN_COMMON'";
    } elseif ($type === "INSTRUCTOR_BATCH") {
        $query .= " AND r.reference_type = 'INSTRUCTOR_BATCH'";
        if ($batchId) {
            $query .= " AND r.batch_id = ?";
            $params[] = $batchId;
            $types .= "i";
        } else {
            $query .= " AND r.batch_id IN ($inList)";
        }
    } else {
        // Can view all ADMIN_COMMON plus INSTRUCTOR_BATCH for their assigned batches
        if ($batchId) {
            $query .= " AND (r.reference_type = 'ADMIN_COMMON' OR r.batch_id = ?)";
            $params[] = $batchId;
            $types .= "i";
        } else {
            $query .= " AND (r.reference_type = 'ADMIN_COMMON' OR r.batch_id IN ($inList))";
        }
    }
} elseif ($authUser["role"] === "student") {
    $studentId = (int)$authUser["id"];
    $studentBatchIds = getStudentBatchIds($conn, $studentId);
    $inList = !empty($studentBatchIds) ? implode(',', $studentBatchIds) : "0";

    if ($type === "ADMIN_COMMON") {
        $query .= " AND r.reference_type = 'ADMIN_COMMON'";
    } elseif ($type === "INSTRUCTOR_BATCH") {
        $query .= " AND r.reference_type = 'INSTRUCTOR_BATCH'";
        if ($batchId) {
            $query .= " AND r.batch_id = ?";
            $params[] = $batchId;
            $types .= "i";
        } else {
            $query .= " AND r.batch_id IN ($inList)";
        }
    } else {
        // Student can view active ADMIN_COMMON references plus INSTRUCTOR_BATCH references for enrolled batches
        if ($batchId) {
            $query .= " AND (r.reference_type = 'ADMIN_COMMON' OR r.batch_id = ?)";
            $params[] = $batchId;
            $types .= "i";
        } else {
            $query .= " AND (r.reference_type = 'ADMIN_COMMON' OR r.batch_id IN ($inList))";
        }
    }
}

$query .= " ORDER BY r.id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $row["video_full_url"] = formatMediaUrl($row["video_path"]);
    $row["file_size_formatted"] = $row["file_size"] > 0 ? round($row["file_size"] / (1024 * 1024), 1) . " MB" : "";
    $items[] = $row;
}
$stmt->close();

respond(true, "Reference library items fetched successfully.", 200, [
    "count" => count($items),
    "references" => $items
]);
?>
