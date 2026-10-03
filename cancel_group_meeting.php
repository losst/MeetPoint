<?php //irene
session_start();
require_once __DIR__ . '/connect_db.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];
$meetingId = isset($_POST['meeting_id']) ? (int) $_POST['meeting_id'] : 0;

if ($meetingId <= 0) {
    header("Location: appointments.php");
    exit();
}

$checkStmt = $conn->prepare("
    SELECT gm.meeting_id
    FROM group_meetings gm
    JOIN `groups` g ON gm.group_id = g.group_id
    WHERE gm.meeting_id = ?
      AND g.owner_id = ?
    LIMIT 1
");

if (!$checkStmt) {
    die("Prepare failed for ownership check: " . $conn->error);
}

$checkStmt->bind_param("ii", $meetingId, $userId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$canCancel = $checkResult->num_rows > 0;
$checkStmt->close();

if (!$canCancel) {
    header("Location: appointments.php");
    exit();
}

$deleteStmt = $conn->prepare("
    DELETE FROM group_meetings
    WHERE meeting_id = ?
");

if (!$deleteStmt) {
    die("Prepare failed for group meeting delete: " . $conn->error);
}

$deleteStmt->bind_param("i", $meetingId);
$deleteStmt->execute();
$deleteStmt->close();

header("Location: appointments.php");
exit();
?>