<?php
//Irene
session_start();
require_once __DIR__ . '/connect_db.php';

// session doesn't exist
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// validate input origin
if (!isset($_GET['id'], $_GET['action'])) {
    header("Location: notifications.php");
    exit();
}

$id = $_GET['id'];
$action = $_GET['action'];

// validate actions
if ($action === 'accept') {
    // only owner can accept
    $query = "
        UPDATE appointments a
        JOIN booking_slots bs ON a.slot_id = bs.slot_id
        SET a.status = 'booked'
        WHERE a.appointment_id = ? AND bs.owner_id = ?
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ii", $id, $_SESSION['user_id']);
    $stmt->execute();// update database
    $stmt->close();

} elseif ($action === 'decline') {
    // delete appointment if owner declines
    $query = "
        DELETE a
        FROM appointments a
        JOIN booking_slots bs ON a.slot_id = bs.slot_id
        WHERE a.appointment_id = ? AND bs.owner_id = ?
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ii", $id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();

} else {
    header("Location: notifications.php");
    exit();
}

header("Location: notifications.php");
exit();
?>