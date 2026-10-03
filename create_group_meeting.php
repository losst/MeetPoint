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

if ($_SESSION['role'] !== 'owner') {
    header("Location: group_meetings.php");
    exit();
}

if (
    !isset($_SESSION['group_meeting_draft']) ||
    empty($_SESSION['group_meeting_draft']['options'])
) {
    header("Location: group_meeting_options.php");
    exit();
}

$draft =& $_SESSION['group_meeting_draft'];
// Make sure invitees/title always exist and invitees stays as an array.
if (!isset($draft['invitees']) || !is_array($draft['invitees'])) {
    $draft['invitees'] = [];
}
if (!isset($draft['title'])) {
    $draft['title'] = '';
}
if (!isset($draft['is_recurring'])) {
    $draft['is_recurring'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_invitee') {
        $draft['title'] = trim($_POST['meetingTitle'] ?? '');
        $draft['is_recurring'] = isset($_POST['is_recurring']) ? 1 : 0;

        $email = strtolower(trim($_POST['invitee_email'] ?? ''));
        
        if ($email === '') {
            $error = "Please enter an invitee email.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (!in_array($email, $draft['invitees'], true)) {
                $draft['invitees'][] = $email;

                header("Location: create_group_meeting.php");
                exit();
        }
        else{
             $error = "This invitee has already been added.";
        }
        
    }
    if (strpos($action, 'remove_invitee_') === 0) {
        $index = (int) str_replace('remove_invitee_', '', $action);
        if (isset($draft['invitees'][$index])) {
            array_splice($draft['invitees'], $index, 1);
        }
        
        $draft['title'] = trim($_POST['meetingTitle']);
        $draft['is_recurring'] = isset($_POST['is_recurring']) ? 1 : 0;

        header("Location: create_group_meeting.php");
        exit();
    }
    if ($action === 'back') {
        $draft['title'] = trim($_POST['meetingTitle'] ?? '');
        $draft['is_recurring'] = isset($_POST['is_recurring']) ? 1 : 0;

        header("Location: group_meeting_options.php");
        exit();
    }
    if ($action === 'cancel') {
        unset($_SESSION['group_meeting_draft']);

        header("Location: group_meetings.php");
        exit();
    }

    if ($action === 'invite') {
        $title = trim($_POST['meetingTitle'] ?? $draft['title']);
        $draft['title'] = $title;
        $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;
        $draft['is_recurring'] = $isRecurring;

        if ($title === '') {
            $error = "Meeting title is required.";
        } elseif (empty($draft['options'])) {
            $error = "Please add at least one meeting option.";
        } elseif (empty($draft['invitees'])) {
            $error = "Please add at least one invitee.";
        } else {

            $ownerId = (int) $_SESSION['user_id'];
            $conn->begin_transaction();
            
            try {
                // duplicate title check FIRST
                $duplicateTitleStmt = $conn->prepare("
                    SELECT group_id 
                    FROM groups 
                    WHERE owner_id = ? AND LOWER(title) = LOWER(?)
                    LIMIT 1
                ");

                if (!$duplicateTitleStmt) {
                    throw new Exception("Prepare failed for duplicate title check: " . $conn->error);
                }
                $duplicateTitleStmt->bind_param("is", $ownerId, $title);
                $duplicateTitleStmt->execute();
                $duplicateTitleResult = $duplicateTitleStmt->get_result();
                $duplicateTitleExists = $duplicateTitleResult->num_rows > 0;
                $duplicateTitleStmt->close();
                if ($duplicateTitleExists) {
                    throw new Exception("Duplicate group title.");
                }

                // create one row in groups
                $stmt = $conn->prepare("INSERT INTO groups (owner_id, title) VALUES (?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed for groups insert: " . $conn->error);
                }
                $stmt->bind_param("is", $ownerId, $title);
                $stmt->execute();
                $groupId = $stmt->insert_id;
                $stmt->close();

                // insert into group_participants
                $findUserStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
                if (!$findUserStmt) {
                    throw new Exception("Prepare failed for user lookup: " . $conn->error);
                }

                $participantStmt = $conn->prepare("INSERT INTO group_participants (group_id, user_id) VALUES (?, ?)");
                if (!$participantStmt) {
                    throw new Exception("Prepare failed for participants insert: " . $conn->error);
                }
                foreach ($draft['invitees'] as $email) {
                    $findUserStmt->bind_param("s", $email);
                    $findUserStmt->execute();
                    $result = $findUserStmt->get_result();

                    //prevent the owner from being inserted as their own participant
                    if ($row = $result->fetch_assoc()) {
                        $inviteeUserId = (int) $row['user_id'];
                        if ($inviteeUserId !== $ownerId) {
                            $participantStmt->bind_param("ii", $groupId, $inviteeUserId);
                            $participantStmt->execute();
                        }
                    }
                }
                $findUserStmt->close();
                $participantStmt->close();
                    
                // insert each selected option into group_meetings
                $meetingStmt = $conn->prepare("
                    INSERT INTO group_meetings (group_id, start_time, end_time, is_recurring, recurring_id, num_vote)
                    VALUES (?, ?, ?, ?, NULL, 0)
                ");

                if (!$meetingStmt) {
                    throw new Exception("Prepare failed for group_meetings insert: " . $conn->error);
                }

                foreach ($draft['options'] as $option) {
                    $startDateTime = $option['date'] . ' ' . $option['start'] . ':00';
                    $endDateTime = $option['date'] . ' ' . $option['end'] . ':00';
                    $isRecurring = !empty($draft['is_recurring']) ? 1 : 0;

                    $meetingStmt->bind_param("issi", $groupId, $startDateTime, $endDateTime, $isRecurring);
                    if (!$meetingStmt->execute()) {
                        throw new Exception("Execute failed for group_meetings insert: " . $meetingStmt->error);
                    }
                }
                $meetingStmt->close();
                $conn->commit();
                unset($_SESSION['group_meeting_draft']);

                header("Location: group_meetings.php");
                exit();
            }catch (Throwable $e) {
                $conn -> rollback();
                if ($e->getMessage() === "Duplicate group title.") {
                    $error = "You already have a group meeting with this title.";
                } else {
                    $error = "Failed to create group meeting.";
                }
            }
        }
	}
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Group Meeting</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link rel="stylesheet" href="css/add_new_slot_style.css">
    <link rel="stylesheet" href="css/style.css">

    <link rel="icon" href="assets/logo.png" type="image/png">
</head>

<body>
<div class="slot-page">
    <aside class="sidebar">
        <a href="appointments.php">
            <button class="sidebar-btn logo" type="button" title="Appointments">
                <img src="assets/logo.png" alt="Appointments" />
            </button>
        </a>

        <a href="slots_owner.php">
            <button class="sidebar-btn" type="button" title="Home">
                <img src="assets/home_icon.png" alt="Home" />
            </button>
        </a>

        <a href="notifications.php">
            <button class="sidebar-btn" type="button" title="Messages">
                <img src="assets/chat_icon.png" alt="Messages" />
            </button>
        </a>

        <a href="group_meetings.php">
            <button class="sidebar-btn active" type="button" title="Group Meetings">
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

    <div id="sidebarWarning" class="sidebar-warning"></div>

    <main class="slot-main">
        <header class="slot-header">
            <h1>CREATE GROUP MEETING</h1>

            <form class="logout-form" action="logout.php" method="post">
                <button class="logout-btn" type="submit">
                    <img src="assets/profile.png" alt="Profile" class="profile-icon" />
                    <span>LOG OUT</span>
                </button>
            </form>
        </header>

        <section class="slot-form-panel group-meeting-panel">
            <form class="slot-form group-meeting-form" method="post">

                <div class="form-group">
                    <label class="form-title" for="meetingTitle">MEETING TITLE</label>
                    <input
                        type="text"
                        id="meetingTitle"
                        name="meetingTitle"
                        class="text-input long-input"
                        placeholder="e.g. COMP 307 Team 43 demo"
                        value="<?= htmlspecialchars($draft['title']) ?>"
                    />
                </div>

                <div class="form-group">
                    <label class="form-title">RECURRING</label>

                    <label class="mini-label recurring-check">
                        <input 
                            type="checkbox" 
                            name="is_recurring" 
                            value="1"
                            <?= !empty($draft['is_recurring']) ? 'checked' : '' ?>
                        >
                        Repeat this group meeting weekly
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-title">INVITE STUDENTS</label>

                    <div class="invite-search-row">
						<input
							type="email"
							name="invitee_email"
							class="text-input long-input"
							placeholder="Enter McGill email"
						/>
						<button type="submit" name="action" value="add_invitee" class="mini-action-btn" formnovalidate>ADD</button>
					</div>

                    <div class="invite-pill-list">
                        <?php if (empty($draft['invitees'])): ?>
                            <div class="invite-pill">
                                <span>No invitees added yet</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($draft['invitees'] as $i => $invitee): ?>
                                <div class="invite-pill">
                                    <span><?= htmlspecialchars($invitee) ?></span>
                                    <button
                                        type="submit"
                                        name="action"
                                        value="remove_invitee_<?= $i ?>"
                                        class="pill-remove-btn"
                                        formnovalidate
                                    >×</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="slot-actions">
					<button type="submit" name="action" value="back" class="slot-action-btn" formnovalidate>BACK</button>
					<button type="submit" name="action" value="invite" class="slot-action-btn">INVITE</button>
					<button type="submit" name="action" value="cancel" class="slot-action-btn" formnovalidate>CANCEL</button>
				</div>
            </form>

        </section>
    </main>

</div>

<script src="js/script.js"></script>
<?php if (!empty($error)): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const warningBox = document.getElementById("sidebarWarning");

    if (warningBox) {
        warningBox.textContent = <?= json_encode($error) ?>;
        warningBox.classList.add("show");

        setTimeout(function () {
            warningBox.classList.remove("show");
        }, 2200);
    }
});
</script>
<?php endif; ?>

</body>
</html>