@ -1,203 +0,0 @@
<!-- Irene  -->

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
</head>

<body>
    <div class="slot-page">
        <aside class="sidebar">
            <button class="sidebar-btn logo sidebar-lock-link" type="button" title="Appointments">
                <img src="assets/logo.png" alt="Appointments" />
            </button>

            <button class="sidebar-btn sidebar-lock-link" type="button" title="Home">
                <img src="assets/home_icon.png" alt="Home" />
            </button>

            <button class="sidebar-btn sidebar-lock-link" type="button" title="Messages">
                <img src="assets/chat_icon.png" alt="Messages" />
            </button>

            <button class="sidebar-btn sidebar-lock-link active" type="button" title="Group Meetings">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-people-fill" viewBox="0 0 16 16">
                    <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5.784 6A2.24 2.24 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.3 6.3 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/>
                </svg>
            </button>

            <button class="sidebar-btn sidebar-lock-link" type="button" title="Slots">
                <img src="assets/calendar_icon.png" alt="Slots" />
            </button>
        </aside>

        <div id="sidebarWarning" class="sidebar-warning" aria-live="polite">
            Please finish or cancel this session before leaving the page.
        </div>

        <div class="group-main group-options-main">
			<header class="group-header">
				<h1>GROUP MEETING OPTIONS</h1>
				<button class="logout-btn" type="button">
					<img src="assets/profile.png" alt="Profile" class="profile-icon" />
					<span>LOG OUT</span>
				</button>
			</header>

			<div class="content group-options-content">
				<section class="group-options-left">
					<div class="option-form-card">
						<label class="form-title">SELECTED DATE</label>
						<div class="selected-date-card">
							<span class="selected-date-text">APR 29, 2026</span>
						</div>
						<p class="booking-note">Click a date on the calendar, then add a time option below.</p>

						<div class="time-range-row option-time-row">
							<div class="time-field">
								<label class="mini-label" for="optionStart">START TIME</label>
								<select id="optionStart" name="optionStart" class="time-select">
									<option value="">Select time</option>
									<option value="08:00">8:00 AM</option>
									<option value="08:30">8:30 AM</option>
									<option value="09:00">9:00 AM</option>
									<option value="09:30">9:30 AM</option>
									<option value="10:00">10:00 AM</option>
									<option value="10:30">10:30 AM</option>
									<option value="11:00">11:00 AM</option>
									<option value="11:30">11:30 AM</option>
									<option value="12:00">12:00 PM</option>
									<option value="12:30">12:30 PM</option>
									<option value="13:00">1:00 PM</option>
									<option value="13:30">1:30 PM</option>
									<option value="14:00">2:00 PM</option>
									<option value="14:30">2:30 PM</option>
									<option value="15:00">3:00 PM</option>
									<option value="15:30">3:30 PM</option>
									<option value="16:00">4:00 PM</option>
									<option value="16:30">4:30 PM</option>
									<option value="17:00">5:00 PM</option>
									<option value="17:30">5:30 PM</option>
								</select>
							</div>

							<div class="time-field">
								<label class="mini-label" for="optionEnd">END TIME</label>
								<select id="optionEnd" name="optionEnd" class="time-select">
									<option value="">Select time</option>
									<option value="08:30">8:30 AM</option>
									<option value="09:00">9:00 AM</option>
									<option value="09:30">9:30 AM</option>
									<option value="10:00">10:00 AM</option>
									<option value="10:30">10:30 AM</option>
									<option value="11:00">11:00 AM</option>
									<option value="11:30">11:30 AM</option>
									<option value="12:00">12:00 PM</option>
									<option value="12:30">12:30 PM</option>
									<option value="13:00">1:00 PM</option>
									<option value="13:30">1:30 PM</option>
									<option value="14:00">2:00 PM</option>
									<option value="14:30">2:30 PM</option>
									<option value="15:00">3:00 PM</option>
									<option value="15:30">3:30 PM</option>
									<option value="16:00">4:00 PM</option>
									<option value="16:30">4:30 PM</option>
									<option value="17:00">5:00 PM</option>
									<option value="17:30">5:30 PM</option>
									<option value="18:00">6:00 PM</option>
								</select>
							</div>
						</div>

						<button type="button" class="add-option-btn">+ ADD OPTION</button>
					</div>

					<div class="option-list-card">
						<label class="form-title">CURRENT OPTIONS</label>

						<div class="created-option-list">
							<div class="created-option-row">
								<div class="created-option-text">
									<span class="created-option-date">APR 29, 2026</span>
									<span class="created-option-time">2:00 PM – 3:00 PM</span>
								</div>
								<button type="button" class="option-remove-btn">×</button>
							</div>

							<div class="created-option-row">
								<div class="created-option-text">
									<span class="created-option-date">MAY 1, 2026</span>
									<span class="created-option-time">4:30 PM – 5:30 PM</span>
								</div>
								<button type="button" class="option-remove-btn">×</button>
							</div>
						</div>
					</div>

					<div class="slot-actions group-options-actions">
						<a href="group_meetings.html" class="slot-action-btn">CANCEL</a>
						<a href="create_group_meeting.html" class="slot-action-btn">NEXT</a>
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
								<tbody class="tbody"></tbody>
							</table>
						</div>
					</div>
				</section>
			</div>
		</div>

        <div class="right-pinkbar" aria-hidden="true"></div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>