//irene
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