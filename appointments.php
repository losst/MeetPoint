<?php
//irene
session_start();

// session doesn't exist
if (!isset($_SESSION['user_id'], $_SESSION['role'])) { 
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/connect_db.php';
$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];

$filter = $_GET['filter'] ?? 'all';

$where = [];
$params = [];
$types = "";

// only REAL appointments show as cards
$where[] = "a.status = 'booked'";

if ($role === 'student') {
    $where[] = "a.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
} else {
    $where[] = "bs.owner_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

if ($filter === 'today') {
    $where[] = "DATE(bs.start_time) = CURDATE()";
} elseif ($filter === 'week') {
    $where[] = "YEARWEEK(bs.start_time, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter === 'month') {
    $where[] = "MONTH(bs.start_time) = MONTH(CURDATE()) AND YEAR(bs.start_time) = YEAR(CURDATE())";
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT
        a.appointment_id,
        a.status,
        bs.slot_id,
        bs.start_time,
        bs.end_time,
        bs.is_recurring,
        owner.email AS owner_email,
        student.email AS student_email
    FROM appointments a
    JOIN booking_slots bs ON a.slot_id = bs.slot_id
    JOIN users owner ON bs.owner_id = owner.user_id
    JOIN users student ON a.user_id = student.user_id
    $whereSql
    ORDER BY bs.start_time ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$appointments = [];
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}

$stmt->close();

// group meetings also show as appointment cards
$groupWhere = [];
$groupParams = [];
$groupTypes = "";

// only confirmed group meetings show
$groupWhere[] = "gm.num_vote = -1";

if ($role === 'student') {
    $groupWhere[] = "gp.user_id = ?";
    $groupParams[] = $user_id;
    $groupTypes .= "i";
} else {
    $groupWhere[] = "g.owner_id = ?";
    $groupParams[] = $user_id;
    $groupTypes .= "i";
}

if ($filter === 'today') {
    $groupWhere[] = "DATE(gm.start_time) = CURDATE()";
} elseif ($filter === 'week') {
    $groupWhere[] = "YEARWEEK(gm.start_time, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter === 'month') {
    $groupWhere[] = "MONTH(gm.start_time) = MONTH(CURDATE()) AND YEAR(gm.start_time) = YEAR(CURDATE())";
}

$groupWhereSql = $groupWhere ? "WHERE " . implode(" AND ", $groupWhere) : "";

$groupSql = "
    SELECT DISTINCT
        gm.meeting_id AS appointment_id,
        'group' AS card_type,
        'booked' AS status,
        gm.start_time,
        gm.end_time,
        gm.is_recurring,
        g.owner_id,
        owner.email AS owner_email,
        g.title AS student_email
    FROM group_meetings gm
    JOIN groups g ON gm.group_id = g.group_id
    JOIN users owner ON g.owner_id = owner.user_id
    LEFT JOIN group_participants gp ON g.group_id = gp.group_id
    $groupWhereSql
";

$groupStmt = $conn->prepare($groupSql);
if (!$groupStmt) {
    die("Prepare failed for group meetings: " . $conn->error);
}

if (!empty($groupParams)) {
    $groupStmt->bind_param($groupTypes, ...$groupParams);
}

$groupStmt->execute();
$groupResult = $groupStmt->get_result();

while ($row = $groupResult->fetch_assoc()) {
    $appointments[] = $row;
}

$groupStmt->close();

usort($appointments, function ($a, $b) {
    return strtotime($a['start_time']) <=> strtotime($b['start_time']);
});
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Appointments</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="css/appointments_style.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="assets/logo.png" type="image/png">
</head>
<body>
<div class="appointments-page">
    <aside class="sidebar">
        <a href="appointments.php">
            <button class="sidebar-btn logo active" title="Appointments">
                <img src="assets/logo.png" alt="Appointments" />
            </button>
        </a>
		<a href="slots_owner.php">
			<button class="sidebar-btn" title="Home" type="button">
				<img src="assets/home_icon.png" alt="Home" />
			</button>
		</a>

        <a href="notifications.php">
            <button class="sidebar-btn" title="Messages" type="button">
                <img src="assets/chat_icon.png" alt="Messages" />
            </button>
        </a>

        <a href="group_meetings.php">
            <button class="sidebar-btn" title="Group Meetings">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-people-fill" viewBox="0 0 16 16">
                    <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5.784 6A2.24 2.24 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.3 6.3 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/>
                </svg>
            </button>
        </a>

        <?php if ($role === 'owner'): ?>
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
    <main class="appointments-main">
        <header class="appointments-header">
            <h1>MY APPOINTMENTS</h1>

            <form class="logout-form" action="logout.php" method="post">
                <button class="logout-btn" type="submit">
                    <img src="assets/profile.png" alt="Profile" class="profile-icon" />
                    <span>LOG OUT</span>
                </button>
            </form>
        </header>

        <section class="appointments-hero">
            <div class="filter-row">
                <label class="filter-label" for="eventFilter">
                    <img src="assets/filter.png" alt="Filter" />
                    <span>FILTER</span>
                </label>

                <form method="GET">
                    <select class="event-filter" id="eventFilter" name="filter" onchange="this.form.submit()">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Appointments</option>
                        <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= $filter === 'week' ? 'selected' : '' ?>>This Week</option>
                        <option value="month" <?= $filter === 'month' ? 'selected' : '' ?>>This Month</option>
                    </select>
                </form>
            </div>

            <section class="appointments-content">
				<div class="appointment-list">
					<?php if (empty($appointments)): ?>
						<div class="appointment-card">
							<div class="appointment-owner">NO APPOINTMENTS FOUND</div>
							<div class="appointment-datetime">There are no appointments for this filter.</div>
						</div>
					<?php else: ?>
						<?php foreach ($appointments as $appt): ?>
							<?php
								$isGroup = isset($appt['card_type']) && $appt['card_type'] === 'group';

                                if ($isGroup) {
                                    $displayName = "GROUP: " . $appt['student_email'];
                                    $displayEmail = $appt['owner_email'];
                                } else {
                                    $displayName = ($role === 'student') ? $appt['owner_email'] : $appt['student_email'];
                                    $displayEmail = $displayName;
                                }
							?>
							<div class="appointment-card">
								<div class="appointment-owner">
									<?= htmlspecialchars($displayName) ?><br>
									<small><?= $appt['is_recurring'] ? 'Recurring' : 'One-time' ?></small>
								</div>

								<div class="appointment-datetime">
									<?= date("M j, Y g:i A", strtotime($appt['start_time'])) ?>
									-
									<?= date("g:i A", strtotime($appt['end_time'])) ?><br>
									<small>Status: <?= $isGroup ? 'Group Meeting' : htmlspecialchars($appt['status']) ?></small>
								</div>

								<div class="appointment-actions">
									<a class="icon-btn" href="mailto:<?= htmlspecialchars($displayEmail) ?>">
										<img src="assets/mailto_icon.png" alt="Email" />
									</a>
									<?php if (!$isGroup): ?>
                                        <form action="cancel_appointment.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="appointment_id" value="<?= (int)$appt['appointment_id'] ?>">
                                            <button class="icon-btn" type="submit">
                                                <img src="assets/cancel_icon.png" alt="Cancel" />
                                            </button>
                                        </form>

                                    <?php elseif ((int)$appt['owner_id'] === $user_id): ?>
                                        <form action="cancel_group_meeting.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="meeting_id" value="<?= (int)$appt['appointment_id'] ?>">
                                            <button class="icon-btn" type="submit">
                                                <img src="assets/cancel_icon.png" alt="Cancel" />
                                            </button>
                                        </form>
                                    <?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</section>

			<div class="appointments-footer">
				<?php if ($role === 'student'): ?>
					<a href="slots_owner.php" class="add-booking-btn">
						<img src="assets/add_icon.png" alt="Add booking" class="add-icon" />
						<span>ADD BOOKING</span>
					</a>
				<?php else: ?>
					<a href="add_new_slots.php" class="add-booking-btn">
						<img src="assets/add_icon.png" alt="Add slot" class="add-icon" />
						<span>ADD SLOT</span>
					</a>
				<?php endif; ?>
			</div>
        </section>
    </main>

    <div class="right-pinkbar" aria-hidden="true"></div>
</div>
<script src="js/script.js"></script>
<?php if (!empty($_GET['mailto'])): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const mailtoUrl = <?= json_encode($_GET['mailto']) ?>;

    if (mailtoUrl) {
        window.location.href = mailtoUrl;
    }

    setTimeout(function () {
        window.history.replaceState({}, document.title, window.location.pathname);
    }, 500);
});
</script>
<?php endif; ?>
</body>
</html>