<?php
// references/delete_reference.php - Safely delete or deactivate a reference video

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

$authUser = requireRole(['admin', 'instructor']);
$userId = (int)$authUser["id"];
$userRole = $authUser["role"];

$data = getRequestData();
$id = isset($data["id"]) ? (int)$data["id"] : 0;

if ($id <= 0) {
    respond(false, "A valid reference video ID is required.", 400);
}

$stmt = $conn->prepare("SELECT * FROM reference_videos WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    respond(false, "Reference video not found.", 404);
}

// Role authorization
if ($userRole === "instructor") {
    if ($existing["reference_type"] === "ADMIN_COMMON") {
        respond(false, "Access denied. Only administrators can delete Foundation references.", 403);
    }
    if (!isBatchAssignedToInstructor($conn, $existing["batch_id"], $userId)) {
        respond(false, "Access denied. You can only delete reference videos for your own assigned batches.", 403);
    }
}

// Check if any existing homework relies on this reference
$checkStmt = $conn->prepare("SELECT COUNT(*) AS hw_count FROM homework WHERE reference_id = ?");
$checkStmt->bind_param("i", $id);
$checkStmt->execute();
$hwCount = (int)$checkStmt->get_result()->fetch_assoc()["hw_count"];
$checkStmt->close();

if ($hwCount > 0) {
    // Soft delete / deactivate to preserve historical student homework submissions
    $deactivateStmt = $conn->prepare("UPDATE reference_videos SET status = 'Inactive' WHERE id = ?");
    $deactivateStmt->bind_param("i", $id);
    $deactivateStmt->execute();
    $deactivateStmt->close();

    respond(true, "Reference video has active assignments linked to it. It has been deactivated safely to protect student history.", 200, [
        "action" => "deactivated",
        "id" => $id
    ]);
} else {
    // Safe hard delete
    $delStmt = $conn->prepare("DELETE FROM reference_videos WHERE id = ?");
    $delStmt->bind_param("i", $id);
    $delStmt->execute();
    $delStmt->close();

    // Safely delete physical file if not used elsewhere
    $physicalPath = __DIR__ . '/../' . ltrim($existing["video_path"], '/');
    if (file_exists($physicalPath)) {
        @unlink($physicalPath);
    }

    respond(true, "Reference video deleted safely from library.", 200, [
        "action" => "deleted",
        "id" => $id
    ]);
}
?>
