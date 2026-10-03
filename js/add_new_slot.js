//irene
//this page is used to gen error msg for multiple forms

document.addEventListener("DOMContentLoaded", function () {
    const lockedLinks = document.querySelectorAll(".sidebar-lock-link");
    const warningBox = document.getElementById("sidebarWarning");
	const form = document.querySelector(".slot-form");
    let warningTimer;

    if (!warningBox) {
        console.error("sidebarWarning element not found");
        return;
    }
	
	function showWarning(message){
		warningBox.textContent = message;
        warningBox.classList.add("show");

        clearTimeout(warningTimer);
        warningTimer = setTimeout(() => {
            warningBox.classList.remove("show");
        }, 2200);
	}
	// sidebar nav warning
    lockedLinks.forEach(item => {
        item.addEventListener("click", function (e) {
            e.preventDefault();

            warningBox.classList.add("show");

            clearTimeout(warningTimer);
            warningTimer = setTimeout(() => {
                warningBox.classList.remove("show");
            }, 2200);
        });
    });

	
	// required field missing warning
	if (form) {
        form.addEventListener("submit", function (e) {
            const selectedDays = document.querySelectorAll('input[name="days[]"]:checked');
            const startTime = document.getElementById("startTime");
            const endTime = document.getElementById("endTime");
            const repeatWeeks = document.getElementById("repeatWeeks");
			
			// fields that only exist on group_meeting_options.php
            const optionDate = document.querySelector('input[name="option_date"]');
            const addOptionButton = document.querySelector('button[value="add_option"]');

            // generate error messages on multiple pages, so check which page it is first
            const isGroupMeetingOptionsPage = optionDate !== null || addOptionButton !== null;
            const isAddSlotPage = !isGroupMeetingOptionsPage && repeatWeeks !== null;
            const isRequestBookingPage = !isAddSlotPage && !isGroupMeetingOptionsPage;
			
			if(isAddSlotPage){
				
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
			}

            if (isGroupMeetingOptionsPage) {
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
            }
			if (isRequestBookingPage) {
                const missingRequiredField =
                    !startTime || startTime.value.trim() === "";

                if (missingRequiredField) {
                    e.preventDefault();
                    showWarning("A required field is missing.");
                    return;
                }
            }
        });
    }
});
