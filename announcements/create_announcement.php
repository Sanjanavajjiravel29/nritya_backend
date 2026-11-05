<?php
// announcements/create_announcement.php - Admin publishes academy announcement or event notice

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Strictly restricted to authenticated Admin accounts only
$adminUser = requireRole('admin');
$createdBy = (int)$adminUser["id"];

$data = getRequestData();

$title = trim($data["title"] ?? "");
$body = trim($data["body"] ?? "");
$eventDate = trim($data["event_date"] ?? date("d M Y", strtotime("+14 days")));

if ($title === "" || $body === "") {
    respond(false, "Title and announcement body are required.", 400);
}

$stmt = $conn->prepare(
    "INSERT INTO announcements (title, body, event_date, created_by)
     VALUES (?, ?, ?, ?)"
);

if (!$stmt) {
    respond(false, "Database error: " . $conn->error, 500);
}

$stmt->bind_param("sssi", $title, $body, $eventDate, $createdBy);

if ($stmt->execute()) {
    $annId = $conn->insert_id;
    $stmt->close();

    respond(true, "Announcement published successfully.", 201, [
        "announcement" => [
            "id" => $annId,
            "title" => $title,
            "body" => $body,
            "event_date" => $eventDate,
            "created_by" => $createdBy,
            "created_at" => date("Y-m-d H:i:s")
        ]
    ]);
}

$err = $stmt->error;
$stmt->close();
respond(false, "Could not publish announcement: " . $err, 500);
?>
