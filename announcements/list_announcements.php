<?php
// announcements/list_announcements.php - List academy events & announcements

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(false, "Only GET requests are allowed.", 405);
}

// Authentication required for academy announcements
$authUser = authenticate();

$stmt = $conn->prepare(
    "SELECT a.*, u.full_name as author_name, u.staff_role as author_role
     FROM announcements a
     LEFT JOIN users u ON a.created_by = u.id
     ORDER BY a.id DESC"
);

$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

respond(true, "Announcements fetched successfully.", 200, [
    "count" => count($items),
    "announcements" => $items
]);
?>
