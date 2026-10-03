// Nancy

// only show one of slots-btns or logout-btn on smaller screens
function toggleSlotBtns() {
    document.querySelector('.logout-btn').classList.toggle('visible');
    document.querySelector('.slots-btns').classList.toggle('visible');
}

// make click trigger status filter
const slots_btns = document.getElementsByClassName('slots-btn');
for (let j = 0; j < slots_btns.length; j++) {
    slots_btns[j].addEventListener("click", select_filter);
}

// status filter
function select_filter(event) {
    const filter_e = document.querySelector('.filter');

    if (filter_e) {
        // filter is already being applied
        filter_e.classList.remove('filter');
    }

    if (filter_e !== event.currentTarget) {
        // existing filter is not the selected filter
        event.currentTarget.className += ' filter';
    }

    const year = document.getElementById('label').innerHTML;
    const month = document.querySelector('.active-month').innerHTML;
    const day = document.querySelector('.active-date').innerHTML;

    // get events on selected date for selected filter
    get_events(year, month, day);
}

// set private slots to active
// accept or decline pending slot requests
// delete slots
function set_slot_action() {
    const slots = document.getElementsByClassName('event');

    for (let i = 0; i < slots.length; i++) {
        const slot = slots[i];
        const id = slot.children[0].innerHTML;
        const status_element = slot.children[1];
        const status = status_element.innerHTML.toLowerCase(); // pending, booked, active, private

        // get meeting details
        const meeting_time = slot.children[2].innerHTML;
        const [start_time, end_time] = meeting_time.split(' - ');
        const year = document.getElementById('label').innerHTML;
        const month = document.querySelector('.active-month').innerHTML;
        const day = document.querySelector('.active-date').innerHTML;

        const action = slot.lastElementChild;
        const positive_action = action.children[0];
        const negative_action = action.lastElementChild;

        if (status === 'private' || status === 'pending') {
            
            positive_action.addEventListener('click', (event) => {

                // confirmation pop-up
                let confirm_message = "";
                if (status === 'private') {
                    confirm_message = "Do you want to make this slot public?";
                } else {
                    confirm_message = "Do you want to accept this request?";
                }

                const positive_confirm = confirm(confirm_message);
                if (!positive_confirm) {
                    return; // cancel action
                } 

                // call php script to update booking_slot.is_active or set appointments.status = booked
                // pass status and slot id as info, differentiate pending vs private in php
                const xhttp = new XMLHttpRequest();
                xhttp.onreadystatechange = function() {

                    if (this.readyState == 4 && this.status == 200) {
                        const response = JSON.parse(this.responseText);

                        if (response.redirect) {
                            window.location.href = response.redirect;

                        } else if (response.error) {
                            slot.innerHTML = response.error;

                        } else {

                            if (response.email) { // status === pending
                                const email = response.email;
                                let subject = encodeURIComponent('Meeting Accepted');
                                let body = encodeURIComponent(`Hello,\nYour meeting request has been accepted. Our meeting will be from ${start_time} to ${end_time} on ${month}. ${day}, ${year}. \nSee you then!`);

                                const mailto = `mailto:${email}?subject=${subject}&body=${body}`;
                                window.open(mailto);
                            }

                            // reset calendar
                            init_calendar(new Date(year, months_map[month]-1, day));
                        }
                    }
                }

                xhttp.open('POST', 'set_slot_status.php', true);
                xhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhttp.send(
                    'id=' + encodeURIComponent(id) + 
                    '&status=' + encodeURIComponent(status) +
                    '&action=positive'
                );

            })
        }

        negative_action.addEventListener('click', (event) => {

            // confirmation pop-up
            let confirm_message = "Do you want to delete this slot?";
            if (status === 'pending') {
                confirm_message = "Do you want to decline this request?";
            }

            const negative_confirm = confirm(confirm_message);
            if (!negative_confirm) {
                return; // cancel action
            } 

            // remove records from tables, notify users
            const xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {

                if (this.readyState == 4 && this.status == 200) {
                    const response = JSON.parse(this.responseText);

                    if (response.redirect) {
                        window.location.href = response.redirect;

                    } else if (response.email) {
                        const email = response.email;
                        let subject = encodeURIComponent('Meeting Cancelled');
                        let body = encodeURIComponent(`Hello,\nOur meeting from ${start_time} to ${end_time} on ${month}. ${day}, ${year} is cancelled. \nApologies for the inconvenience.`);

                        if (status === 'pending') {
                            subject = encodeURIComponent('Meeting Request Declined');
                            body = encodeURIComponent(`Hello,\nI am unavailable for a meeting from ${start_time} to ${end_time} on ${month}. ${day}, ${year}. \nPlease try a different time.`);
                        }

                        const mailto = `mailto:${email}?subject=${subject}&body=${body}`;
                        window.open(mailto);
                    }

                    // reset calendar
                    init_calendar(new Date(year, months_map[month]-1, day));
                }
            }

            xhttp.open('POST', 'set_slot_status.php', true);
            xhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhttp.send(
                'id=' + encodeURIComponent(id) + 
                '&status=' + encodeURIComponent(status) +
                '&action=negative'
            );

        })
        
    }
}