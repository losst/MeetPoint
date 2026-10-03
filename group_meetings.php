<?php // Nancy

session_start();

// session doesn't exist
if (!isset($_SESSION['user_id'], $_SESSION['role'])) { 
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/connect_db.php';
$user_id = (int) $_SESSION['user_id'];

$statement = $conn->prepare("SELECT role FROM users WHERE user_id = ?;");
$statement->bind_param('i', $user_id);
$statement->execute();
$result = $statement->get_result();

// session exists but user doesn't
if ($result->num_rows == 0) { 
    header("Location: logout.php");
    $conn->close();
    exit();
}

$role = $_SESSION['role'];

// only select groups that are still pending time decision
$sql = "
    SELECT DISTINCT g.group_id, g.title,
    CASE 
        WHEN g.owner_id = ? THEN 1
        ELSE 0
    END AS is_owner
    FROM groups g
    JOIN group_meetings gm ON g.group_id = gm.group_id
    LEFT JOIN group_participants gp ON g.group_id = gp.group_id
    WHERE (g.owner_id = ? OR gp.user_id = ?) AND gm.num_vote != -1
    ORDER BY g.group_id ASC;
";

$statement = $conn->prepare($sql);
$statement->bind_param("iii", $user_id, $user_id, $user_id);
$statement->execute();
$result = $statement->get_result();

$groups = [];
while ($row = $result->fetch_assoc()) {
    $groups[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html>

<head>
    <title>Group Meetings</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/group_meetings_style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="icon" href="assets/logo.png" type="image/png">
</head>


<body>
<div class="group-page">

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
            <button class="sidebar-btn" title="Notifications">
                <img src="assets/chat_icon.png" alt="notifications" />
            </button>
        </a>

        <a href="group_meetings.php">
            <button class="sidebar-btn active" title="Group Meetings">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-people-fill" viewBox="0 0 16 16">
                    <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5.784 6A2.24 2.24 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.3 6.3 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/>
                </svg>
            </button>
        </a>

        <!-- only show if is owner -->
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

    <div class="group-main">
        <!-- title + logout -->
        <header class="group-header">
            <h1>GROUP MEETINGS</h1>
			<form class="logout-form" action="logout.php" method="post">
				<button class="logout-btn" type="submit">
					<img src="assets/profile.png" alt="Profile" class="profile-icon" />
					<span>LOG OUT</span>
				</button>
			</form>
        </header>

        <div class="content">
        <!-- left side -->
            <div class="groups">
                <section class="group-list">
                    <!-- display groups -->
                    <?php if (empty($groups)): ?>
                        <div class="group-name">NO GROUPS FOUND</div>

                    <?php else: ?>
                        <?php $is_first = true; ?>
                        <?php foreach ($groups as $group): ?>
                            
                            <!-- set filter on first group -->
                            <div class="group-row <?= $is_first ? 'filter' : '' ?>">
                                <?php $is_first = false; ?>

                                <span class="group_id" style="display: none;">
                                    <?= $group['group_id'] ?>
                                </span>

                                <span class="group-name">
                                    <?= htmlspecialchars($group['title']) ?>
                                    <span style="font-family: var(--font-body)">
                                        <?php if ($group['is_owner']): ?> (owner)<?php endif; ?>
                                    </span>
                                </span>

                            </div>

                        <?php endforeach; ?>
                    <?php endif; ?>

                </section>

                <!-- only show if is owner -->
                <?php if ($role === 'owner'): ?>
					<div class="group-actions-footer">
						<a href="group_meeting_options.php?new=1" class="add-group-meeting-btn">
							<img src="assets/add_icon.png" alt="Add group meeting" class="add-icon" />
							<span>ADD GROUP MEETING</span>
						</a>
					</div>
				<?php endif; ?>
        
            </div>
            
            <!-- right side -->
            <!-- Calendar, referenced Bootstrap -->
            <section class="calendar-container">
                <div class="calendar">
                    <div class="year-header">
                        <span class="left-button fa fa-chevron-left" id="prev""></span>
                        <span class="year" id="label"></span>
                        <span class="right-button fa fa-chevron-right" id="next"> </span>
                    </div>
                    <table class="months-table wide">
                        <tbody>
                            <tr class="months-row">
                                <td class="month">Jan</td>
                                <td class="month">Feb</td>
                                <td class="month">Mar</td>
                                <td class="month">Apr</td>
                                <td class="month">May</td>
                                <td class="month">Jun</td>
                                <td class="month">Jul</td>
                                <td class="month">Aug</td>
                                <td class="month">Sep</td>
                                <td class="month">Oct</td>
                                <td class="month">Nov</td>
                                <td class="month">Dec</td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="days-table wide">
                        <td class="day">SUN</td>
                        <td class="day">MON</td>
                        <td class="day">TUE</td>
                        <td class="day">WED</td>
                        <td class="day">THU</td>
                        <td class="day">FRI</td>
                        <td class="day">SAT</td>
                    </table>
                    <div class="frame">
                        <table class="dates-table wide" cellspacing="0">
                            <tbody class="tbody">
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="events">
                    <!-- events inserted by calendar.js > get_slots.php -->
                    <div class="events-container"></div>
                </div>
                
            </section>
        </div>
    </div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/calendar.js"></script>
<script src="js/group_meetings.js"></script>
<script src="js/script.js"></script>

</body>
</html>