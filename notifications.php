<?php
// Emily

session_start();

// session doesn't exist
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// get user id
require_once __DIR__ . '/connect_db.php';
$user_id = (int) $_SESSION['user_id'];

// get current user role
$user_query = "SELECT role FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$role = $user['role'];


// fetch notifications
$sql = "
SELECT 
    a.appointment_id,
    a.status,
    a.created_at,

    bs.start_time,
    bs.end_time,
    bs.owner_id,

    student.email AS student_email,
    owner.email AS owner_email

FROM appointments a
JOIN booking_slots bs ON a.slot_id = bs.slot_id
JOIN users student ON a.user_id = student.user_id
JOIN users owner ON bs.owner_id = owner.user_id

WHERE a.user_id = ? OR bs.owner_id = ?
ORDER BY a.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

// fetch group additions (notifications)
$group_sql = "
SELECT 
    gp.group_id,
    gp.created_at,
    g.title AS group_title,
    u.email AS owner_email

FROM group_participants gp
JOIN groups g ON gp.group_id = g.group_id
JOIN users u ON g.owner_id = u.user_id

WHERE gp.user_id = ?
AND gp.created_at >= NOW() - INTERVAL 2 DAY
ORDER BY gp.created_at DESC
";

$stmt2 = $conn->prepare($group_sql);
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$group_result = $stmt2->get_result();

?>

<!DOCTYPE html>
<html>

<head>
    <title>Notifications</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/notifications_style.css">
    <link rel="icon" href="assets/logo.png" type="image/png">
</head>

<body>
<div class="notifications-page">

    <!-- side bar-->
    <aside class="sidebar">
        <a href="appointments.php">
            <button class="sidebar-btn logo" title="Appointments">
                <img src="assets/logo.png" alt="Appointments" />
            </button>
        </a>

        <a href="slots_owner.php">
            <button class="sidebar-btn" title="Home">
                <img src="assets/home_icon.png" alt="Home" />
            </button>
        </a>

        <a href="notifications.php">
            <button class="sidebar-btn active" title="Notifications">
                <img src="assets/chat_icon.png" alt="notifications" />
            </button>
        </a>

        <a href="group_meetings.php">
            <button class="sidebar-btn" title="Group Meetings">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-people-fill" viewBox="0 0 16 16">
                    <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5.784 6A2.24 2.24 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.3 6.3 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/>
                </svg>
            </button>
        </a>

        <?php if ($_SESSION['role'] === 'owner'): ?>
            <a href="slots.php">
                <button class="sidebar-btn" title="Slots">
                    <img src="assets/calendar_icon.png" alt="Slots" />
                </button>
            </a>
        <?php endif; ?>

        <!-- for smaller screens -->
        <a href="javascript:void(0);" class="bar-icon" id="bar-icon" onclick="openSidebar()">
            <!-- bootstrap svg -->
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-three-dots" viewBox="0 0 16 16">
                <path d="M3 9.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3m5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3m5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3"/>
            </svg>
        </a>
    </aside>

    <!-- main container-->
    <div class="notifications-main">

        <!-- main header -->
        <header class="notifications-header">
            <h1>NOTIFICATIONS</h1>
            <form class="logout-form" action="logout.php" method="post">
                <button class="logout-btn" type="submit">
                    <img src="assets/profile.png" alt="Profile" class="profile-icon" />
                    <span>LOG OUT</span>
                </button>
            </form>
        </header>

        <!-- Notifications -->
        <div class="notifications-list">
        <?php $hasNotifications = false; ?>
        <?php while ($row = $result->fetch_assoc()): ?>

            <?php
            $isOwner = ($row['owner_id'] == $user_id);
            $time = date("M d, H:i", strtotime($row['start_time']));
            $timestamp = date("M d, H:i", strtotime($row['created_at']));
            ?>

            <?php if (!$isOwner): ?>
                <?php $hasNotifications = true; ?>
                <!-- YOU SENT REQUEST -->
                <div class="notification-card">
                    YOUR REQUEST FOR MEETING WITH 
                    <strong><?= htmlspecialchars($row['owner_email']) ?></strong>
                    @ <?= $time ?> HAS BEEN SENT!
                    <div class="timestamp"><?= $timestamp ?></div>
                </div>

            <?php elseif ($row['status'] == 'pending'): ?>
                <?php $hasNotifications = true; ?>
                <!-- OWNER RECEIVES REQUEST -->
                <div class="card card-row">
                    <div>
                        <strong><?= htmlspecialchars($row['student_email']) ?></strong>
                        HAS REQUESTED A MEETING @ <?= $time ?>
                        <div class="timestamp"><?= $timestamp ?></div>
                    </div>

                    <div class="actions">
                        <a href="update_appointment.php?id=<?= $row['appointment_id'] ?>&action=accept" class="btn accept">ACCEPT</a>
                        <a href="update_appointment.php?id=<?= $row['appointment_id'] ?>&action=decline" class="btn decline">DECLINE</a>
                    </div>
                </div>

            <?php endif; ?>

        <?php endwhile; ?>

        <?php while ($row = $group_result->fetch_assoc()): ?>
            <?php $hasNotifications = true; ?>

            <?php
            $timestamp = date("M d, H:i", strtotime($row['created_at']));
            ?>

            <div class="notification-card">
                YOU WERE ADDED TO GROUP 
                <strong><?= htmlspecialchars($row['group_title']) ?></strong>
                BY <strong><?= htmlspecialchars($row['owner_email']) ?></strong>

                <div class="timestamp"><?= $timestamp ?></div>
            </div>

        <?php endwhile; ?>

        <?php if (!$hasNotifications): ?>
            <div class="notification-card">
                NO NOTIFICATIONS
            </div>
        <?php endif; ?>
        </div>
    </div>

</div>

<script src="js/script.js"></script>

</body>
</html>
