<?php
session_start();

// get user id
require_once __DIR__ . '/connect_db.php';
$user_id = $_SESSION['user_id'] ?? null;
$selected_owner = $_GET['owner_id'] ?? null;

// get current user role
$role = null;
$user_email = null;

if ($user_id !== null) {
    $user_query = "SELECT role, email FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();

    $role = $user['role'];
    $user_email = $user['email'];
}

$query = "
    SELECT DISTINCT u.user_id, u.email
    FROM booking_slots bs
    JOIN users u ON bs.owner_id = u.user_id
    WHERE u.role = 'owner'
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>

<head>
    <title>list of owners</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/group_meetings_style.css">
    <link rel="stylesheet" href="css/slots_owner_style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="icon" href="assets/logo.png" type="image/png">
</head>


<body>
<div class="owner-page">

    <!-- side bar-->
    <aside class="sidebar">
        <a href="appointments.php">
            <button class="sidebar-btn logo" title="Appointments">
                <img src="assets/logo.png" alt="Appointments" />
            </button>
        </a>

        <a href="slots_owner.php">
            <button class="sidebar-btn active" title="Home">
                <img src="assets/home_icon.png" alt="Home" />
            </button>
        </a>

        <a href="notifications.php">
            <button class="sidebar-btn" title="Notifications">
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

        <?php if (($role ?? null) === 'owner'): ?>
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

    <div class="owner-main">
        <!-- title + logout -->
        <header class="owner-header">
            <h1>LIST OF OWNERS</h1>

            <?php if (!empty($user_id)): ?>
                <form class="logout-form" action="logout.php" method="post">
                    <button class="logout-btn" type="submit">
                        <img src="assets/profile.png" class="profile-icon" />
                        <span>LOG OUT</span>
                    </button>
                </form>
            <?php else: ?>
                <a href="login.php?redirect=slots_owner.php" class="logout-link">
                    <div class="logout-btn">
                        <img src="assets/profile.png" class="profile-icon" />
                        <span>LOG IN</span>
                    </div>
                </a>
            <?php endif; ?>
        </header>

        <div class="content">
        <!-- LEFT SIDE -->
        <div class="owner-list">
            <!-- Search Bar -->
            <div class="search-bar">
                <span class="search-icon">🔍</span>
                <input type="text" id="searchInput" placeholder="SEARCH FOR OWNER">
                <span class="clear" id="clearBtn">✕</span>
            </div>

            <!-- Owner Row -->
             <?php while ($row = $result->fetch_assoc()): ?>

                <?php
                $email = $row['email'];
                $namePart = explode('@', $email)[0]; // firstname.lastname
                $displayName = ucwords(str_replace('.', ' ', $namePart)); // firstname lastname
                ?>

                <div class="owner-row"
                    data-owner-id="<?php echo $row['user_id']; ?>"
                    data-owner-name="<?php echo htmlspecialchars($displayName); ?>">

                    <span class="owner-name">
                        <?php echo htmlspecialchars($displayName); ?>
                        <span style="font-family: var(--font-body)">
                            <?php if ($email === $user_email): // user is owner?> (You)<?php endif; ?> <!-- (Nancy) -->
                        </span>
                    </span>

                    <div class="owner-actions">
                        <button class="calendar-btn">
                            <img src="assets/calendar_icon.png" 
                                class="calendar-owner-btn" 
                                alt="View slots" />
                        </button>

                        <?php if ($email === $user_email): ?> <!-- user/owner-dependent display (Nancy) -->
                            <!-- for owner, generate invitation URL -->
                            <button class="copy-link-btn" data-owner="<?php echo $row['user_id']; ?>">
                                <img src="assets/link_icon.png"
                                class = "link-owner-btn"
                                alt = "copy link"/>
                            </button>

                        <?php else: ?>
                            <!-- for user, request booking -->
                            <button class="chat-btn">
                                <img src="assets/chat_icon.png" 
                                    class="request-owner-btn" 
                                    alt="Request meeting"/>
                            </button>

                        <?php endif; ?>

                    </div>
                </div>

            <?php endwhile; ?>

            <p id="noResults" style="display:none;">No owners found</p>
        </div>     

        <!-- RIGHT SIDE -->
        <!-- Calendar, referenced Bootstrap -->
        <section class="calendar-container">
            <div class="calendar">
                <div class="year-header">
                    <span class="left-button fa fa-chevron-left" id="prev"></span>
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
                <div class="events-container">
                    <!-- testing purposes -->
                    <div class="event">
                        <span class="event-name">name</span>
                        <span class="event-time">time</span>
                        <span class="event-user">user or group</span>
                        <span class="event-status">status</span>
                    </div>
                    <div class="event">
                        <span class="event-name">name</span>
                        <span class="event-time">time</span>
                        <span class="event-user">user or group</span>
                        <span class="event-status">status</span>
                    </div>
                </div>
                
            </div>
        </section>
        </div>
    </div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/calendar.js"></script>
<script src="js/script.js"></script>

<script src="js/slots_owner.js"></script>

<script>
    window.selectedOwnerId = <?php echo json_encode($selected_owner); ?>;
</script>

</body>
</html>