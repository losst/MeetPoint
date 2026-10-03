<?php
//Irene
session_start();
require_once __DIR__ . '/connect_db.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['appointment_id'])) {
    header("Location: appointments.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];
$appointmentId = (int) $_POST['appointment_id'];

$infoSql = "
    SELECT 
        a.appointment_id,
        a.user_id,
        bs.owner_id,
        bs.start_time,
        bs.end_time,
        owner.email AS owner_email,
        requester.email AS requester_email
    FROM appointments a
    JOIN booking_slots bs ON a.slot_id = bs.slot_id
    JOIN users owner ON bs.owner_id = owner.user_id
    JOIN users requester ON a.user_id = requester.user_id
    WHERE a.appointment_id = ?
    LIMIT 1
";

$infoStmt = $conn->prepare($infoSql);
if (!$infoStmt) {
    die("Prepare failed: " . $conn->error);
}

$infoStmt->bind_param("i", $appointmentId);
$infoStmt->execute();
$infoResult = $infoStmt->get_result();
$appointmentInfo = $infoResult->fetch_assoc();
$infoStmt->close();

if (!$appointmentInfo) {
    header("Location: appointments.php");
    exit();
}

/*
    Student:
    - can cancel their own appointment (sends notif email to owner)
    Owner:
    - can cancel appointments on their own slots
    Since appointments table references slot_id, deleting the appointment
    makes the slot available again.
*/

// Permission check
if ($role === 'student') {
    $canCancel = ((int)$appointmentInfo['user_id'] === $userId);
} else {
    $canCancel = ((int)$appointmentInfo['owner_id'] === $userId);
}

if (!$canCancel) {
    header("Location: appointments.php");
    exit();
}

if ($role === 'student') {
    $sql = "
        DELETE FROM appointments
        WHERE appointment_id = ? AND user_id = ?
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ii", $appointmentId, $userId);

} else {
    $sql = "
        DELETE a
        FROM appointments a
        JOIN booking_slots bs ON a.slot_id = bs.slot_id
        WHERE a.appointment_id = ? AND bs.owner_id = ?
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ii", $appointmentId, $userId);
}

$stmt->execute();
$deleted = $stmt->affected_rows > 0;
$stmt->close();

// Send notification email to owner only when the student cancels
if ($deleted) {
    $ownerEmail = $appointmentInfo['owner_email'];
    $requesterEmail = $appointmentInfo['requester_email'];

    $startLabel = date("M j, Y g:i A", strtotime($appointmentInfo['start_time']));
    $endLabel = date("g:i A", strtotime($appointmentInfo['end_time']));

    $subject = "Appointment Cancelled";

    if($role == 'student'){
        // Student cancelled, notify owner
        $toEmail = $ownerEmail;
        $message = "Hello,\n\n"
            . "An appointment has been cancelled by user {$requesterEmail}.\n\n"
            . "Time: {$startLabel} - {$endLabel}\n\n"
            . "This is an automated notification.";
    }else {
        // Owner cancelled, notify requester
        $toEmail = $requesterEmail;
        $message = "Hello,\n\n"
            . "Your appointment has been cancelled by owner {$ownerEmail}.\n\n"
            . "Time: {$startLabel} - {$endLabel}\n\n"
            . "This is an automated notification.";
    }

    $headers = "From: no-reply@mcgill-booking.local\r\n";

    $mailtoSubject = rawurlencode($subject);
    $mailtoBody = rawurlencode($message);

    $mailtoUrl = "mailto:" . rawurlencode($ownerEmail)
        . "?subject=" . $mailtoSubject
        . "&body=" . $mailtoBody;

    header("Location: appointments.php?mailto=" . urlencode($mailtoUrl) . "&cancelled=1");
    exit();
}

header("Location: appointments.php");
exit();
?>