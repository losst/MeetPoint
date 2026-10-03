<?php
// Emily

session_start();
require_once __DIR__ . '/connect_db.php';

// session doesn't exist
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => false,
        "error" => "LOGIN_REQUIRED"
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$slot_id = $_POST['slot_id'];

// 1. Check if the slot is still available
// 2. Insert into appointments table
$stmt = $conn->prepare("
    INSERT INTO appointments (slot_id, user_id, status)
    VALUES (?, ?, 'pending')
");
$stmt->bind_param("ii", $slot_id, $user_id);


if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Booking request sent"]);
} else {
    echo json_encode(["success" => false, "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>