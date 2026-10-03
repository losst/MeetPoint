<?php

//irene
session_start();

// session doesn't exist
if (!isset($_SESSION['user_id'], $_SESSION['role'])) { 
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/connect_db.php';

if ($_SESSION['role'] !== 'owner') {
    header("Location: group_meetings.php");
    exit();
}

if (!isset($_SESSION['group_meeting_draft'])) {
    $_SESSION['group_meeting_draft'] = [
        'options' => [],
        'title' => '',
        'invitees' => []
    ];
}

// when page is opened fresh from dashboard, reset title/invitees
if (isset($_GET['new']) && $_GET['new'] === '1') {
    $_SESSION['group_meeting_draft'] = [
        'options' => [],
        'title' => '',
        'invitees' => []
    ];
}

$draft =& $_SESSION['group_meeting_draft'];
$error = "";

//sort options by date/time before display
if (!empty($draft['options'])) {
    usort($draft['options'], function ($a, $b) {
        return strcmp($a['date'] . ' ' . $a['start'], $b['date'] . ' ' . $b['start']);
    });
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_option') {
		$optionDate = trim($_POST['option_date'] ?? '');
		$optionStart = trim($_POST['option_start'] ?? '');
		$optionEnd = trim($_POST['option_end'] ?? '');

        if (empty($optionDate) || $optionStart === '' || $optionEnd === '') {
            $error = "A required field is missing.";
        } elseif (strtotime($optionEnd) <= strtotime($optionStart)) {
            $error = "End time must be later than start time.";
        } else {
            if ($optionDate !== '' && $optionStart !== '' && $optionEnd !== '' && $optionStart < $optionEnd) {
                $newOption = [
                    'date' => $optionDate,
                    'start' => $optionStart,
                    'end' => $optionEnd,
                ];

                $isDuplicate = false;
                foreach ($draft['options'] as $existing) {
                    if (
                        $existing['date'] === $newOption['date'] &&
                        $existing['start'] === $newOption['start'] &&
                        $existing['end'] === $newOption['end']
                    ) {
                        $isDuplicate = true;
                        break;
                    }
                }
                if (!$isDuplicate) {
                    $draft['options'][] = $newOption;
                }
            }
        }
        if ($error === "") {
            header("Location: group_meeting_options.php");
            exit();
        }
	}

    if ($action === 'remove_option') {
        $index = isset($_POST['option_index']) ? (int) $_POST['option_index'] : -1;
        if (isset($draft['options'][$index])) {
            array_splice($draft['options'], $index, 1);
        }
        header("Location: group_meeting_options.php");
        exit();
    }

    if ($action === 'next') {
        header("Location: create_group_meeting.php");
        exit();
    }

    if ($action === 'cancel') {
        unset($_SESSION['group_meeting_draft']);
        header("Location: group_meetings.php");
        exit();
    }
}

function formatTimeLabel(string $time24): string {
    return date("g:i A", strtotime($time24));
}

function formatDateLabel(string $date): string {
    return date("M j, Y", strtotime($date));
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Group Meeting Options</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link rel="stylesheet" href="css/add_new_slot_style.css">
    <link rel="stylesheet" href="css/group_meetings_style.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">

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

    <div class="group-main group-options-main">
        <div id="sidebarWarning" class="sidebar-warning"></div>
        <header class="group-header">
            <h1>GROUP MEETING OPTIONS</h1>

            <form class="logout-form" action="logout.php" method="post">
                <button class="logout-btn" type="submit">
                    <img src="assets/profile.png" alt="Profile" class="profile-icon" />
                    <span>LOG OUT</span>
                </button>
            </form>
        </header>

        <div class="content group-options-content">
            <section class="group-options-left">
                <div class="option-form-card">
                    <form class="slot-form" method="post">
                        <input type="hidden" name="action" value="add_option">

                        <label class="form-title">SELECTED DATE</label>
                        <div class="input-row" style="margin-top: 10px;">
							<input type="hidden" name="option_date" id="option_date" required>

							<div class="selected-date-card" id="selectedOptionDateCard">
								<span class="selected-date-text" id="selectedOptionDateText">Select a date from the calendar</span>
							</div>
						</div>

                        <p class="booking-note">Choose one date and one time range, then add it as an option.</p>


                        <div class="time-range-row option-time-row">
                            <div class="time-field">
                                <label class="mini-label" for="startTime">START TIME</label>
                                <select id="startTime" name="option_start" class="time-select" required>
                                    <option value="">Select time</option>
                                    <?php
                                    $times = [
                                        "08:00","08:30","09:00","09:30","10:00","10:30","11:00","11:30",
                                        "12:00","12:30","13:00","13:30","14:00","14:30","15:00","15:30",
                                        "16:00","16:30","17:00","17:30"
                                    ];
                                    foreach ($times as $t):
                                    ?>
                                        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars(formatTimeLabel($t)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="time-field">
                                <label class="mini-label" for="endTime">END TIME</label>
                                <select id="endTime" name="option_end" class="time-select" required>
                                    <option value="">Select time</option>
                                    <?php
                                    $endTimes = [
                                        "08:30","09:00","09:30","10:00","10:30","11:00","11:30",
                                        "12:00","12:30","13:00","13:30","14:00","14:30","15:00","15:30",
                                        "16:00","16:30","17:00","17:30","18:00"
                                    ];
                                    foreach ($endTimes as $t):
                                    ?>
                                        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars(formatTimeLabel($t)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="add-option-btn">+ ADD OPTION</button>
                    </form>
                </div>

                <div class="option-list-card">
                    <label class="form-title">CURRENT OPTIONS</label>

                    <div class="created-option-list">
                        <?php if (empty($draft['options'])): ?>
                            <div class="created-option-row">
                                <div class="created-option-text">
                                    <span class="created-option-date">NO OPTIONS YET</span>
                                    <span class="created-option-time">Add at least one date/time option.</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($draft['options'] as $i => $option): ?>
                                <div class="created-option-row">
                                    <div class="created-option-text">
                                        <span class="created-option-date"><?= htmlspecialchars(formatDateLabel($option['date'])) ?></span>
                                        <span class="created-option-time">
                                            <?= htmlspecialchars(formatTimeLabel($option['start'])) ?> – <?= htmlspecialchars(formatTimeLabel($option['end'])) ?>
                                        </span>
                                    </div>

                                    <form method="post" style="margin:0;">
                                        <input type="hidden" name="action" value="remove_option">
                                        <input type="hidden" name="option_index" value="<?= $i ?>">
                                        <button type="submit" class="option-remove-btn">✕</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="slot-actions group-options-actions">
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="slot-action-btn">CANCEL</button>
                    </form>

                    <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="next">
                        <button type="submit" class="slot-action-btn" <?= empty($draft['options']) ? 'disabled' : '' ?>>NEXT</button>
                    </form>
                </div>
            </section>

            <section class="calendar-container">
                <div class="calendar">
                    <div class="year-header">
                        <span class="left-button fa fa-chevron-left" id="prev"></span>
                        <span class="year" id="label"></span>
                        <span class="right-button fa fa-chevron-right" id="next"></span>
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
                        <tr>
                            <td class="day">SUN</td>
                            <td class="day">MON</td>
                            <td class="day">TUE</td>
                            <td class="day">WED</td>
                            <td class="day">THU</td>
                            <td class="day">FRI</td>
                            <td class="day">SAT</td>
                        </tr>
                    </table>

                    <div class="frame">
                        <table class="dates-table wide" cellspacing="0">
                            <tbody class="tbody"></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>

</div>

<script src="js/jquery.min.js"></script>
<script src="js/calendar.js"></script>
<script src="js/script.js"></script>
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
            const optionDate = document.querySelector('input[name="option_date"]');
            const startTime = document.getElementById("startTime");
            const endTime = document.getElementById("endTime");

            const missingRequiredField =
                !optionDate || optionDate.value.trim() === "" ||
                !startTime || startTime.value.trim() === "" ||
                !endTime || endTime.value.trim() === "";

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
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const hiddenDateInput = document.getElementById("option_date");
    const selectedDateText = document.getElementById("selectedOptionDateText");

    function formatDisplayDate(dateString) {
        const date = new Date(dateString + "T00:00:00");
        if (isNaN(date)) return "Select a date from the calendar";
        return date.toLocaleDateString("en-CA", {
            month: "short",
            day: "numeric",
            year: "numeric"
        });
    }
    //preload from sessionStorage if calendar.js already set one
    const savedDate = sessionStorage.getItem("selectedBookingDate");
    if (savedDate && hiddenDateInput && selectedDateText) {
        hiddenDateInput.value = savedDate;
        selectedDateText.textContent = formatDisplayDate(savedDate);
    }
    //hook into calendar clicks by listening for clicks on date cells
    document.addEventListener("click", function (e) {
        const dateCell = e.target.closest(".table-date");
        if (!dateCell || dateCell.classList.contains("nil")) return;
        setTimeout(function () {
            const pickedDate = sessionStorage.getItem("selectedBookingDate");
            if (pickedDate && hiddenDateInput && selectedDateText) {
                hiddenDateInput.value = pickedDate;
                selectedDateText.textContent = formatDisplayDate(pickedDate);
            }
        }, 50);
    });
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