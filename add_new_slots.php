<?php
// Irene
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['role'])) { 
    header("Location: index.php");
    exit();
}
require_once __DIR__ . '/connect_db.php';

if ($_SESSION['role'] !== 'owner') {
    header("Location: home.php");
    exit;
}
$ownerId = (int) $_SESSION['user_id'];
$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $days = $_POST['days'] ?? [];
    $startTime = $_POST['startTime'] ?? '';
    $endTime = $_POST['endTime'] ?? '';
	
    $repeatWeeks = isset($_POST['repeatWeeks']) ? (int) $_POST['repeatWeeks'] : 0;

    $dayMap = [
		'mon' => 1,
		'tue' => 2,
		'wed' => 3,
		'thu' => 4,
		'fri' => 5,
		'sat' => 6,
		'sun' => 7
	];

	$createdCount = 0;

    if (empty($days) || $startTime === '' || $endTime === '' || $repeatWeeks === '') {
        $error = "A required field is missing.";
    } elseif (strtotime($endTime) <= strtotime($startTime)) {
        $error = "End time must be later than start time.";
    } else {
        if ($repeatWeeks > 1) {
            // create one recurrence record for this whole series
            $recurrenceStmt = $conn->prepare("
                INSERT INTO recurrences (total_recurrences)
                VALUES (?)
            ");

            if (!$recurrenceStmt) {
                die("Prepare failed: " . $conn->error);
            }

            $recurrenceStmt->bind_param("i", $repeatWeeks);
            $recurrenceStmt->execute();
            $recurringId = $conn->insert_id;
            $recurrenceStmt->close();

            $stmt = $conn->prepare("
                INSERT INTO booking_slots (owner_id, start_time, end_time, is_active, is_recurring, recurring_id)
                VALUES (?, ?, ?, 0, 1, ?)
            ");

        } else {
            $recurringId = NULL;
            $stmt = $conn->prepare("
                INSERT INTO booking_slots (owner_id, start_time, end_time, is_active, is_recurring, recurring_id)
                VALUES (?, ?, ?, 0, 0, ?)
            ");
        }

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        foreach ($days as $day) {
            if (!isset($dayMap[$day])) {
                continue;
            }

            $targetDay = $dayMap[$day];

            for ($week = 0; $week < $repeatWeeks; $week++) {
                $slotDate = new DateTime();

                $currentWeekday = (int) $slotDate->format('N');
                $diff = $targetDay - $currentWeekday;

                if ($diff < 0) {
                    $diff += 7;
                }

                $slotDate->modify("+$diff days");

                if ($week > 0) {
                    $slotDate->modify("+" . ($week * 7) . " days");
                }

                $dateStr = $slotDate->format('Y-m-d');
                $startDateTime = $dateStr . ' ' . $startTime . ':00';
                $endDateTime = $dateStr . ' ' . $endTime . ':00';

                $conflictStmt = $conn->prepare("
                    SELECT slot_id
                    FROM booking_slots
                    WHERE owner_id = ?
                    AND start_time < ?
                    AND end_time > ?
                    LIMIT 1
                ");

                if (!$conflictStmt) {
                    die("Prepare failed: " . $conn->error);
                }

                $conflictStmt->bind_param("iss", $ownerId, $endDateTime, $startDateTime);
                $conflictStmt->execute();
                $conflictResult = $conflictStmt->get_result();
                $hasConflict = $conflictResult->num_rows > 0;
                $conflictStmt->close();

                if ($hasConflict) {
                    $error = "You already have a slot during this time.";
                    break 2;
                }

                $stmt->bind_param("issi", $ownerId, $startDateTime, $endDateTime, $recurringId);

                if ($stmt->execute()) {
                    $createdCount++;
                }
            }
        }

        $stmt->close();
        if ($createdCount > 0) {
            header("Location: slots.php");
            exit;
        } else {
            $error = "No slots were created.";
        }
    }

    
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add New Slot</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/add_new_slot_style.css">
    <link rel="icon" href="assets/logo.png" type="image/png">
</head>

<body>
    <div class="slot-page">
        <aside class="sidebar sidebar-static" aria-hidden="true"></aside>

        <main class="slot-main">
            <header class="slot-header">
                <h1>ADD NEW SLOT</h1>

                <form class="logout-form" action="logout.php" method="post">
                    <button class="logout-btn" type="submit">
                        <img src="assets/profile.png" alt="Profile" class="profile-icon" />
                        <span style="font-family: var(--font-heading)">LOG OUT</span>
                    </button>
                </form>
            </header>

            <section class="slot-form-panel">
                <form class="slot-form" method="POST" action="add_new_slots.php">
                    <?php if ($success): ?>
                        <div class="form-message success-message"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-title">DAYS OF WEEK</label>
                        <div class="day-selector">
                            <label class="day-pill">
                                <input type="checkbox" name="days[]" value="mon">
                                <span>MON</span>
                            </label>
                            <label class="day-pill">
                                <input type="checkbox" name="days[]" value="tue">
                                <span>TUES</span>
                            </label>
                            <label class="day-pill">
                                <input type="checkbox" name="days[]" value="wed">
                                <span>WED</span>
                            </label>
                            <label class="day-pill">
                                <input type="checkbox" name="days[]" value="thu">
                                <span>THU</span>
                            </label>
                            <label class="day-pill">
                                <input type="checkbox" name="days[]" value="fri">
                                <span>FRI</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-title">TIME</label>

                        <?php if (!empty($error)): ?>
                            <div class="form-message error-message">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <div class="time-range-row">
                            <div class="time-field">
                                <label class="mini-label" for="startTime">START TIME</label>
                                <input type="time" id="startTime" name="startTime" class="time-input" required>
                            </div>

                            <div class="time-field">
                                <label class="mini-label" for="endTime">END TIME</label>
                                <input type="time" id="endTime" name="endTime" class="time-input" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-title" for="repeatWeeks">REPEAT FOR</label>
                        <div class="input-row repeat-row">
                            <input
                                type="number"
                                id="repeatWeeks"
                                name="repeatWeeks"
                                min="1"
                                max="16"
                                value="8"
                                class="text-input small-input"
                                required
                            >
                            <span class="inline-label">WEEKS</span>
                        </div>
                    </div>

                    <div class="slot-actions">
                        <button type="submit" class="slot-action-btn">SAVE</button>
                        <a href="slots.php" class="slot-action-btn">CANCEL</a>
                    </div>
                </form>
            </section>
        </main>
        <div class="right-pinkbar" aria-hidden="true"></div>
    </div>
<!-- <script src="js/add_new_slot.js"></script> -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector(".slot-form");
    const warningBox = document.getElementById("sidebarWarning");
    let warningTimer;

    function showWarning(message) {
        if (warningBox) {
            warningBox.textContent = message;
            warningBox.classList.add("show");

            clearTimeout(warningTimer);
            warningTimer = setTimeout(function () {
                warningBox.classList.remove("show");
            }, 2200);
        } else {
            alert(message);
        }
    }

    if (form) {
        form.addEventListener("submit", function (e) {
            const selectedDays = document.querySelectorAll('input[name="days[]"]:checked');
            const startTime = document.getElementById("startTime");
            const endTime = document.getElementById("endTime");
            const repeatWeeks = document.getElementById("repeatWeeks");

            const missingRequiredField =
                selectedDays.length === 0 ||
                !startTime || startTime.value.trim() === "" ||
                !endTime || endTime.value.trim() === "" ||
                !repeatWeeks || repeatWeeks.value.trim() === "";

            if (missingRequiredField) {
                e.preventDefault();
                showWarning("A required field is missing.");
                return;
            }

            if (startTime.value >= endTime.value) {
                e.preventDefault();
                showWarning("End time must be later than start time.");
                return;
            }
        });
    }
});
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
</script>
<<script src="js/script.js"></script>
</body>
</html>