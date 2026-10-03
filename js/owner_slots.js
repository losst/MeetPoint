// !! now in slots_owner.js
// Emily

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

// Clear button
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

function openOwnerCalendar(ownerId) {
    calendarContainer.dataset.ownerId = ownerId;
    calendarContainer.classList.add("active");

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