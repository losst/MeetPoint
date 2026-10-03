// Irene

document.addEventListener("DOMContentLoaded", function () {
    const ownerRows = document.querySelectorAll(".owner-row");
	const guide = document.getElementById("requestGuide");

    ownerRows.forEach(row => {
        const ownerId = row.dataset.ownerId;
        const ownerName = row.dataset.ownerName;

        const requestBtn = row.querySelector(".chat-btn");
        const calendarBtn = row.querySelector(".calendar-btn");
		
		// when the chat-cion is clicked, enter "request mode" so the next
		// calendar date click redirects to request_booking page
        if (requestBtn) {
            requestBtn.addEventListener("click", function (e) {
                e.stopPropagation();

                sessionStorage.setItem("requestOwnerId", ownerId);
                sessionStorage.setItem("requestOwnerName", ownerName);
                sessionStorage.setItem("requestMode", "true");
				
				document.querySelectorAll(".owner-row").forEach(r => {
                    r.classList.remove("request-selected");
                });
                document.querySelectorAll(".chat-btn").forEach(btn => {
                    btn.classList.remove("request-active");
                });

                row.classList.add("request-selected");
                requestBtn.classList.add("request-active");
				// show a guide text next to the calendar
                if (guide) {
                    guide.textContent = `Now select a date on the calendar for ${ownerName}.`;
                    guide.classList.add("active");
                }
            });
        }
		
		//when calendar icon clicked: exit request mode & remove any request-related msg
        if (calendarBtn) {
            calendarBtn.addEventListener("click", function (e) {
                e.stopPropagation();

                sessionStorage.setItem("requestMode", "false");
                document.querySelectorAll(".owner-row").forEach(r => {
                    r.classList.remove("request-selected");
                });
                document.querySelectorAll(".request-owner-btn").forEach(btn => {
                    btn.classList.remove("request-active");
                });
				//hide text
				if (guide) {
                    guide.textContent = "";
                    guide.classList.remove("active");
                }
            });
        }
    });
});

// Emily ------------------------

/* --- search for owner --- */
const searchInput = document.getElementById("searchInput");
const clearBtn = document.getElementById("clearBtn");

searchInput.addEventListener("keyup", function () {
    const filter = searchInput.value.toLowerCase();
    const rows = document.querySelectorAll(".owner-row");

   let visibleCount = 0;
    rows.forEach(row => {
        const name = row.querySelector(".owner-name").textContent.toLowerCase();

        if (name.includes(filter)) {
            row.style.display = "flex";
            visibleCount++;
        } else {
            row.style.display = "none";
        }
    });

    document.getElementById("noResults").style.display = visibleCount === 0 ? "block" : "none";
});

/* --- Clear button --- */
clearBtn.addEventListener("click", function () {
    searchInput.value = "";
    searchInput.dispatchEvent(new Event("keyup"));
});


/* --- show calender on click --- */
const calendarContainer = document.querySelector(".calendar-container");
const calendarButtons = document.querySelectorAll(".calendar-btn");

calendarButtons.forEach(btn => {
    btn.addEventListener("click", function (e) {
        e.stopPropagation();

        const row = this.closest(".owner-row");
        const ownerId = row.dataset.ownerId;
        const ownerName = row.dataset.ownerName;

        sessionStorage.setItem("requestOwnerId", ownerId);
        sessionStorage.setItem("requestOwnerName", ownerName);

        openOwnerCalendar(ownerId);
    });
});

/* --- open the calendar of specific owner --- */
async function openOwnerCalendar(ownerId) {
    const calendarContainer = document.querySelector(".calendar-container");

    // store selected owner
    calendarContainer.dataset.ownerId = ownerId;
    window.selectedOwnerId = ownerId;
    sessionStorage.setItem("requestOwnerId", ownerId);

    // show calendar
    calendarContainer.classList.add("active");

    // highlight selected owner
    document.querySelectorAll(".owner-row").forEach(row => {
        row.classList.toggle("active-owner",
            row.dataset.ownerId == ownerId);
    });

    // update URL without reload
    const url = new URL(window.location);
    url.searchParams.set("owner_id", ownerId);
    window.history.pushState({}, "", url);

    calendarInitialized = false;

    // force reload calendar for new owner
    if (typeof init_calendar === "function") {
        init_calendar(new Date());
    }
}


/* --- hide calender when click outside ---  */
document.addEventListener("click", function (e) {
    if (!e.target.closest(".calendar-container") &&
        !e.target.closest(".calendar-btn")) {
        calendarContainer.classList.remove("active");
        sessionStorage.removeItem("selectedOwnerId");
    }
});

/* --- copy url ---*/
document.querySelectorAll(".copy-link-btn").forEach(btn => {
    btn.addEventListener("click", async function (e) {
        e.stopPropagation();

        const ownerId = this.dataset.owner;

        const url = `${window.location.origin}${window.location.pathname}?owner_id=${ownerId}`;

        try {
            await navigator.clipboard.writeText(url);
            alert("Link copied!");
        } catch (err) {
            console.error("Copy failed:", err);
        }
    });
});


/* --- open calendar from url --- */
window.addEventListener('load', () => {
    const params = new URLSearchParams(window.location.search);
    const ownerId = params.get("owner_id");

    if (ownerId) {
        openOwnerCalendar(ownerId);
    } else {
        init_calendar(new Date());
    }
});