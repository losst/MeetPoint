// Nancy

window.addEventListener('load', () => init_calendar(new Date()));
document.getElementById('prev').addEventListener('click', prev_year);
document.getElementById('next').addEventListener('click', next_year);

const months = document.getElementsByClassName('month');
for (let i = 0; i < months.length; i++) {
    months[i].addEventListener("click", select_month);
}

let _calendarRendering = false; // (Emily)render calendar only once
// Bootstrap methods, modified
// Initialize the calendar by appending the HTML dates
async function init_calendar(date) {
    if (window._calendarRendering) return;
    window._calendarRendering = true;

    $(".tbody").empty();    // empty dates
    
    document.querySelector(".tbody").innerHTML = "";
    $(".events-container").empty();
    $(".active-month").removeClass("active-month");
    
    const calendar_days = $(".tbody");
    const month = date.getMonth(); // index of month (starts at 0);
    $(".months-row").children().eq(month).addClass("active-month");
    
    const year = date.getFullYear();
    const day_count = days_in_month(month, year);
    let row = $("<tr class='table-row'></tr>");
    const today = date.getDate();
    // Set date to 1 to find the first day of the month
    date.setDate(1);
    const first_day = date.getDay();

    let event_map = {};
    try {
        event_map = await get_event_counts(year, month + 1);
    } catch (e) {
        console.error("Could not load dots, but drawing calendar anyway:", e);
    }
    
    // 35+firstDay is the number of date elements to be added to the dates table
    // 35 is from (7 days in a week) * (up to 5 rows of dates in a month)
    for(var i=0; i<35+first_day; i++) {
        // Since some of the elements will be blank, 
        // need to calculate actual date from index
        const day = i-first_day+1;
        // If it is a sunday, make a new row
        if(i%7===0) {
            calendar_days.append(row);
            row = $("<tr class='table-row'></tr>");
        }
        // if current index isn't a day in this month, make it blank
        if(i < first_day || day > day_count) {
            const curr_date = $("<td class='table-date nil'>"+"</td>");
            row.append(curr_date);
        }   
        else {
            const curr_date = $("<td class='table-date'>"+day+"</td>");
            
            if(today===day && $(".active-date").length===0) {
                curr_date.addClass("active-date");
                get_events(year, month+1, day);
            }

            // If this date has any events, style it with .event-date
            const count = event_map[day.toString()] ?? 0;
            if (count > 0) {
                curr_date.addClass("event-date");
            }

            // Set onClick handler for clicking a date
            curr_date.click(select_date);
            row.append(curr_date);
        }
    }
    // Append the last row and set the current year
    calendar_days.append(row);
    $(".year").text(year);

    // adjust layout (custom addition)
    const calendar_body = document.querySelector(".tbody");
    const calendar_last_row = calendar_body.lastChild;
    const last_row_dates = calendar_last_row.children;
    let last_row_nil = true;
    for (var i = 0; i < last_row_dates.length; i++) {
        if (last_row_dates[i].className != "table-date nil") {
            last_row_nil = false;
        }
    }
    if (last_row_nil) {
        const calendar_second_last_row = calendar_last_row.previousSibling;
        const second_last_dates = calendar_second_last_row.children;
        for (var j = 0; j < second_last_dates.length; j++) {
            second_last_dates[j].setAttribute("style", "border-bottom: none; padding-bottom: 10px;");
            second_last_dates[j].className += " special-padding";
        }

        for (var k = 0; k < last_row_dates.length; k++) {
            last_row_dates[k].setAttribute("style", "display: none;");
        }
    }
    window._calendarRendering = false;
}

function days_in_month(month, year) {
    const month_start = new Date(year, month, 1);
    const month_end = new Date(year, month + 1, 1);
    return (month_end - month_start) / (1000 * 60 * 60 * 24);    
}
// end of Bootstrap methods, modified

// re-initialize calendar for previous year
function prev_year() {
    const prev_year = parseInt(document.getElementById('label').innerHTML) - 1;
    const curr_month = document.querySelector('.active-month').innerHTML;
    const curr_date = document.querySelector('.active-date').innerHTML;
    init_calendar(new Date(curr_month + ' ' + curr_date + ', ' + prev_year));
}

// re-initialize calendar for next year
function next_year() {
    const next_year = parseInt(document.getElementById('label').innerHTML) + 1;
    const curr_month = document.querySelector('.active-month').innerHTML;
    const curr_date = document.querySelector('.active-date').innerHTML;
    init_calendar(new Date(curr_month + ' ' + curr_date + ', ' + next_year));
}

// re-initialize calendar for selected month
function select_month(event) {
    const month = event.currentTarget.innerHTML;
    const curr_year = document.getElementById('label').innerHTML;
    const curr_date = document.querySelector('.active-date').innerHTML;
    init_calendar(new Date(month + ' ' + curr_date + ', ' + curr_year))
}

// get slots for a given date
function select_date(event) {
    const year = document.getElementById('label').innerHTML;
    const prev_date = document.querySelector('.active-date');
	if (prev_date) {
        prev_date.classList.remove("active-date");
    }
    const date = event.currentTarget;
    date.className += ' active-date';
    const month = document.querySelector('.active-month').innerHTML;
    const day = date.innerHTML;
	
	let month_num = month;
    if (typeof month === "string") {
        month_num = months_map[month]; // set to num
    }
    
    const selectedDate = formatDateString(year, month_num, day);
	sessionStorage.setItem("selectedBookingDate", selectedDate);
	
	// for request booking: need owner info (Irene)
	const requestMode = sessionStorage.getItem("requestMode") === "true";
    const ownerId = sessionStorage.getItem("requestOwnerId");
    const ownerName = sessionStorage.getItem("requestOwnerName");
	
	if (requestMode && ownerId) {
        const url =
            `request_booking.php?owner_id=${encodeURIComponent(ownerId)}` +
            `&selected_date=${encodeURIComponent(selectedDate)}` +
            `&professor_name=${encodeURIComponent(ownerName || "")}`;

        sessionStorage.removeItem("requestMode");
        window.location.href = url;
        return;
    }

    get_events(year, month, day);
	
}

// query database for slots
function get_events(year, month, day) {
    let month_num = month;
    if (typeof month === "string") {
        month_num = months_map[month]; // set to num
    }

    // check filename to determine which type of slots to return
    const filename = window.location.pathname.split('/').pop().split('.')[0];
    
    let filter = "";
    if (filename === "slots_owner") { 
        filter = sessionStorage.getItem("requestOwnerId") || ""; // (Emily)

    } else {
        const element = document.querySelector('.filter');
        if (element) {
            if (filename === "group_meetings") {
                filter = element.children[0].innerHTML; // group id

            } else if (filename === "slots") {
                filter = element.innerHTML.toLowerCase(); // slot status (private, active, pending, booked)

            } 
        }
    }

    const xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if(this.readyState == 4 && this.status == 200) {
            const response = JSON.parse(this.responseText);

            if (response.redirect) {
                window.location.href = response.redirect;

            } else if (response.message) {
                // display slots
                document.querySelector(".events-container").innerHTML = response.message;

                // set additional action based on source file
                if (filename === "group_meetings") {
                    make_events_clickable();
                    set_form_action();

                } else if (filename === "slots") {
                    set_slot_action();

                } else if (filename === "slots_owner") {
                    set_slot_book_action(); // (Emily)
                }
            }
        }
    }

    xhttp.open("GET", 
        "get_slots.php?source=" + encodeURIComponent(filename) + 
        "&year=" + encodeURIComponent(year) +
        "&month=" + encodeURIComponent(month_num) +
        "&day=" + encodeURIComponent(day) +
        "&filter=" + encodeURIComponent(filter), true);
    xhttp.send();
}

// get dates with slots for a month
function get_event_counts(year, month) {
    return new Promise((resolve, reject) => {

        const xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4) {
                if (this.status == 200) {
                    const response = JSON.parse(this.responseText);

                    if (response.redirect) {
                        window.location.href = response.redirect;
                        return;

                    } else if (response.data) {
                        resolve(response.data);
                    }
                        
                } else {
                    reject("Request failed");
                }
            }
        };

        // check filename to determine which type of slots to return
        const filename = window.location.pathname.split('/').pop().split('.')[0];
        let filter = "";
        if (filename === "slots_owner") {
            filter = sessionStorage.getItem("requestOwnerId") || ""; // (Emily)

        } else {
            const element = document.querySelector('.filter');
            if (element) {
                if (filename === "group_meetings") {
                    filter = element.children[0].innerHTML; // group id

                    if (!filter) { // no group selected
                        resolve({});
                    }
                } 
            } 
        }

        xhttp.open("GET", 
            "get_slots_count.php?source=" + encodeURIComponent(filename) + 
            "&year=" + encodeURIComponent(year) + 
            "&month=" + encodeURIComponent(month) + 
            "&filter=" + encodeURIComponent(filter)+
            "&owner_id=" + encodeURIComponent(window.selectedOwnerId), 
            true);
        xhttp.send();
    });
}

const months_map =  {
    "Jan": 1,
    "Feb": 2,
    "Mar": 3,
    "Apr": 4,
    "May": 5,
    "Jun": 6,
    "Jul": 7,
    "Aug": 8,
    "Sep": 9,
    "Oct": 10,
    "Nov": 11,
    "Dec": 12
}

//helper function for request_booking (Irene)
function formatDateString(year, month, day) {
	const m = String(month).padStart(2, "0");
	const d = String(day).padStart(2, "0");
	return `${year}-${m}-${d}`;
}

// helper func (Emily)
function set_slot_book_action() {
    const bookButtons = document.querySelectorAll('.book-slot-btn');
    
    bookButtons.forEach(button => {
        button.onclick = function() {
            // Get the ID from the hidden span in the same .event div
            const slotId = this.closest('.event').querySelector('.event-id').innerText;
            
            if (confirm("Do you want to book this appointment?")) {
                bookSlot(slotId);
            }
        };
    });
}

// helper func (Emily)
function bookSlot(id) {
    const formData = new FormData();
    formData.append('slot_id', id);

    fetch('book_slot.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        console.log("RAW RESPONSE:", text);

        try {
            const data = JSON.parse(text);

            if (!data.success && data.error === "LOGIN_REQUIRED") {
                window.location.href =
                    "login.php?redirect=slots_owner.php";
                return;
            }

            if (data.success) {
                alert("Appointment created successfully!");
                location.reload();
            } else {
                alert("Error: " + data.error);
            }

        } catch (e) {
            console.error("Bad response:", text);
        }
    })
}