<?php
// Irene 
session_start();
require_once __DIR__ . '/connect_db.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: index.php");
    exit();
}
$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];
$error = "";
$success = "";

// values used to render the page
$ownerId = 0;
$selectedDate = "";
$ownerName = "";

// GET: landing from calendar.js (nancy)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ownerId = isset($_GET['owner_id']) ? (int) $_GET['owner_id'] : 0;
    $selectedDate = trim($_GET['selected_date'] ?? '');
    $ownerName = trim($_GET['professor_name'] ?? '');

    if ($ownerId <= 0 || $selectedDate === '') {
        header("Location: slots_owner.php");
        exit();
    }
}

// POST: submit booking request 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ownerId = isset($_POST['owner_id']) ? (int) $_POST['owner_id'] : 0;
    $selectedDate = trim($_POST['selected_date'] ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $startTime = trim($_POST['startTime'] ?? '');

    if ($ownerId <= 0 || $selectedDate === '' || $startTime === '') {
        $error = "Missing required request information.";
    } else {
        // validate owner exists and is actually an owner
        $ownerStmt = $conn->prepare("SELECT user_id, email, role FROM users WHERE user_id = ? LIMIT 1");
        if (!$ownerStmt) {
            die("Prepare failed: " . $conn->error);
        }

        $ownerStmt->bind_param("i", $ownerId);
        $ownerStmt->execute();
        $ownerResult = $ownerStmt->get_result();
        $ownerRow = $ownerResult->fetch_assoc();
        $ownerStmt->close();

        if (!$ownerRow || $ownerRow['role'] !== 'owner') {
            $error = "Selected owner is invalid.";
        } elseif ($ownerId === $userId) {
            $error = "You cannot request a meeting with yourself.";
        } else {
            if ($ownerName === "") {
                $ownerName = $ownerRow['email'];
            }

            $startDateTime = $selectedDate . ' ' . $startTime . ':00';

            $end = DateTime::createFromFormat('Y-m-d H:i:s', $startDateTime);
            if (!$end) {
                $error = "Invalid date or time.";
            } else {
                // default request length = 30 minutes
                $end->modify('+30 minutes');
                $endDateTime = $end->format('Y-m-d H:i:s');

                // prevent duplicate pending/booked request for same owner/date/time by same user
                $dupeStmt = $conn->prepare("
                    SELECT a.appointment_id
                    FROM appointments a
                    JOIN booking_slots bs ON a.slot_id = bs.slot_id
                    WHERE bs.owner_id = ?
                      AND a.user_id = ?
                      AND bs.start_time = ?
                    LIMIT 1
                ");
                if (!$dupeStmt) {
                    die("Prepare failed: " . $conn->error);
                }

                $dupeStmt->bind_param("iis", $ownerId, $userId, $startDateTime);
                $dupeStmt->execute();
                $dupeResult = $dupeStmt->get_result();
                $duplicateExists = $dupeResult->num_rows > 0;
                $dupeStmt->close();

                if ($duplicateExists) {
                    $error = "You already requested or booked this time slot.";
                } else {
                    $conn->begin_transaction();

                    try {
                        // create requested slot
                        $slotStmt = $conn->prepare("
                            INSERT INTO booking_slots (owner_id, start_time, end_time, is_active, is_recurring)
                            VALUES (?, ?, ?, 1, 0)
                        ");
                        if (!$slotStmt) {
                            throw new Exception("Prepare failed for booking_slots: " . $conn->error);
                        }

                        $slotStmt->bind_param("iss", $ownerId, $startDateTime, $endDateTime);
                        if (!$slotStmt->execute()) {
                            throw new Exception("Execute failed for booking_slots: " . $slotStmt->error);
                        }

                        $slotId = $conn->insert_id;
                        $slotStmt->close();

                        // create pending appointment request
                        $apptStmt = $conn->prepare("
                            INSERT INTO appointments (slot_id, user_id, status)
                            VALUES (?, ?, 'pending')
                        ");
                        if (!$apptStmt) {
                            throw new Exception("Prepare failed for appointments: " . $conn->error);
                        }

                        $apptStmt->bind_param("ii", $slotId, $userId);
                        if (!$apptStmt->execute()) {
                            throw new Exception("Execute failed for appointments: " . $apptStmt->error);
                        }

                        $apptStmt->close();

                        $conn->commit();
                        header("Location: notifications.php");
                        exit();

                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Failed to send booking request.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Request Booking</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/add_new_slot_style.css">
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
            <button class="sidebar-btn" type="button" title="Group Meetings">
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
    </aside>
    <div id="sidebarWarning" class="sidebar-warning"></div>

    <main class="slot-main">
        <header class="slot-header">
            <h1>REQUEST BOOKING</h1>

            <form class="logout-form" action="logout.php" method="post">
                <button class="logout-btn" type="submit">
                    <img src="assets/profile.png" alt="Profile" class="profile-icon" />
                    <span>LOG OUT</span>
                </button>
            </form>
        </header>

        <section class="slot-form-panel">
            <form class="slot-form" method="post">
                <input type="hidden" name="owner_id" value="<?= htmlspecialchars((string)$ownerId) ?>">
                <input type="hidden" name="selected_date" value="<?= htmlspecialchars($selectedDate) ?>">
                <input type="hidden" name="owner_name" value="<?= htmlspecialchars($ownerName) ?>">

                <div class="form-group">
                    <label class="form-title">OWNER</label>
                    <div class="selected-date-card">
                        <span class="professor-name"><?= htmlspecialchars($ownerName !== "" ? strtoupper($ownerName) : ("OWNER #" . $ownerId)) ?></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-title">SELECTED DATE</label>
                    <div class="selected-date-card">
                        <span class="selected-date-text"><?= htmlspecialchars(strtoupper(date("M j, Y", strtotime($selectedDate)))) ?></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-title">START TIME</label>
                    <div class="time-range-row">
                        <div class="time-field">
                            <label class="mini-label" for="startTime">REQUEST TIME</label>
                            <select id="startTime" name="startTime" class="time-select" required>
                                <option value="">Select time</option>
                                <?php
                                $times = [
                                    "08:00","08:30","09:00","09:30","10:00","10:30","11:00","11:30",
                                    "12:00","12:30","13:00","13:30","14:00","14:30","15:00","15:30",
                                    "16:00","16:30","17:00","17:30"
                                ];
                                foreach ($times as $t):
                                ?>
                                    <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars(date("g:i A", strtotime($t))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <p class="booking-note">Requests are created as 30-minute pending appointments.</p>
                </div>

                <div class="slot-actions">
                    <a href="slots_owner.php" class="slot-action-btn">BACK</a>
                    <button type="submit" class="slot-action-btn">SEND REQUEST</button>
                </div>
            </form>
        </section>
    </main>

    <div class="right-pinkbar" aria-hidden="true"></div>
</div>
<script src="js/script.js"></script>
<script src="js/add_new_slot.js"></script>
<script> //irene
document.addEventListener("DOMContentLoaded", function () {
    const params = new URLSearchParams(window.location.search);

    const ownerId = params.get("owner_id");
    const selectedDate = params.get("selected_date");
    const professorName = params.get("professor_name");

    const ownerInput = document.querySelector('input[name="owner_id"]');
    const dateInput = document.querySelector('input[name="selected_date"]');
    const professorText = document.querySelector(".professor-name");
    const dateText = document.querySelector(".selected-date-text");

    if (ownerId && ownerInput) {
        ownerInput.value = ownerId;
    }

    if (selectedDate && dateInput) {
        dateInput.value = selectedDate;
    }

    if (professorName && professorText) {
        professorText.textContent = professorName.toUpperCase();
    }

    if (selectedDate && dateText) {
        const d = new Date(selectedDate + "T00:00:00");
        dateText.textContent = d.toLocaleDateString("en-US", {
            year: "numeric",
            month: "long",
            day: "numeric"
        }).toUpperCase();
    }
});
</script>
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