// Nancy

// select group
const groups = document.getElementsByClassName('group-row');
for (let i = 0; i < groups.length; i++) {
    groups[i].addEventListener('click', (event) => {
        const prev = document.querySelector('.filter');
        if (prev) {
            prev.classList.remove('filter');
        }
        const group = event.currentTarget;
        group.className += ' filter';

        init_calendar(new Date());
    })
}

// if no groups, hide calendar
if (document.querySelector('.group-name').innerHTML === "NO GROUPS FOUND") {
    document.querySelector('.calendar-container').setAttribute('style', 'display: none;');
}

// select slot time and create slot
function make_events_clickable() {
    const events = document.getElementsByClassName('event');
    for (let j = 0; j < events.length; j++) {
        events[j].addEventListener('click', (e) => {
            const slot = events[j];

            // if already voted, prevent click
            if (slot.classList.contains('voted')) {
                return; 
            }

            const group = document.querySelector('.filter');
            const group_id = group.children[0].innerHTML.trim();
            const meeting_id = slot.children[0].innerHTML.trim();
            const group_name = group.children[1].childNodes[0].nodeValue.trim();;
            const meeting_time = slot.children[1].innerHTML;

            // if form exists (i.e. is owner and slot is recurring)
            const form = document.querySelector('.form-main');
            if (form) {
                // for later click logic, stop current click from propagating to window
                e.stopPropagation();
                
                // show form
                document.querySelector('.events-container').className += " show-form";
                // store event info for form submission
                document.getElementById('group-id').value = group_id;
                document.getElementById('meeting-id').value = meeting_id;
                document.getElementById('meeting-time').value = meeting_time;

                // stop further action
                return;
            }

            const [start_time, end_time] = meeting_time.split(' - ');
            const year = document.getElementById('label').innerHTML;
            const month = document.querySelector('.active-month').innerHTML;
            const day = document.querySelector('.active-date').innerHTML;

            // confirmation pop-up
            let confirm_message = `Do you want to vote for ${start_time}-${end_time}, ${month}. ${day}, ${year}? Note: this action cannot be undone`;
            if (slot.lastChild.classList.contains('event-status')) { // thus is owner
                confirm_message = `Do you want to select set ${start_time}-${end_time}, ${month}. ${day}, ${year} as the meeting time for ${group_name}?`
            }

            const confirm_result = confirm(confirm_message);
            if (!confirm_result) {
                return; // cancel action
            } 
            
            const xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    const response = JSON.parse(this.responseText);

                    if (response.redirect) {
                        window.location.href = response.redirect;

                    } else if (response.email) { 
                        // if owner

                        // send email
                        const emails = response.email;
                        const subject = encodeURIComponent(`${group_name} Meeting Time`);
                        const body = encodeURIComponent(`Hello everyone,\nOur meeting will be from ${start_time} to ${end_time} on ${month}. ${day}, ${year}. \nSee you then!`);

                        const mailto = `mailto:${emails}?subject=${subject}&body=${body}`;
                        window.open(mailto);

                        // select next group
                        const next_group = group.nextElementSibling;
                        
                        // remove group from list
                        group.className += ' disappear';
                        group.addEventListener('transitionend', () => {
                            group.remove(); 
                        });

                        if (!next_group) { 
                            // group is the last group, navigate to first group
                            
                            const group_list = document.querySelector('.group-list');
                            const first_group = group_list.firstElementChild;

                            if (first_group.classList.contains('disappear')) { 
                                // no groups left
                                const node = document.createElement("div");
                                node.className = "group-name";
                                node.textContent = "NO GROUPS FOUND";
                                document.querySelector('.group-list').appendChild(node);

                                // hide calendar
                                document.querySelector('.calendar-container').setAttribute('style', 'display: none;');
                            
                            } else {
                                // select first group
                                group.classList.remove('filter');
                                first_group.className += ' filter';

                                // re-initialize calendar for new 'filter' group
                                init_calendar(new Date());
                            }
                            
                        } else {
                            // group is not the last group, continue to next group
                            group.classList.remove('filter');
                            next_group.className += ' filter';

                            // re-initialize calendar for new 'filter' group
                            init_calendar(new Date());
                        }

                    } else if (response.vote) { 
                        // if student
                        const overlay = document.createElement("span");
                        overlay.className = "voted-status";
                        overlay.textContent = "Voted ✓";
                        slot.append(overlay);

                        slot.className += " voted";
                    }
                }
            };

            xhttp.open('POST', 'add_group_slot.php', true);
            xhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhttp.send(
                'group_id=' + encodeURIComponent(group_id) + 
                '&meeting_id=' + encodeURIComponent(meeting_id)
            );
        });
    }
}

function set_form_action() {
    // prevent default form submission and trigger http request
    const form = document.querySelector('.form-main');
    if (form) {
        
        const event_container = document.querySelector('.events-container');

        // cross symbol closes form
        document.querySelector('.close').addEventListener('click', (event) => {
            event_container.classList.remove('show-form');
        });

        // if form is open, clicking outside form closes it
        window.addEventListener('click', (event) => {
            const form_container = document.querySelector('.set-recurrence-form');
            if (event_container.classList.contains('show-form') && !form_container.contains(event.target)) {
                event_container.classList.remove("show-form");
            }
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault(); // prevent default submission;

            const form_data = new FormData(form);
            const group_id = form_data.get('group-id');
            const meeting_id = form_data.get('meeting-id');
            const meeting_time = form_data.get('meeting-time');
            const repeat_weeks = form_data.get('repeat-weeks');

            const xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    const response = JSON.parse(this.responseText);

                    if (response.redirect) {
                        window.location.href = response.redirect;

                    } else if (response.email) {
                        // form will only exist if owner (see get_slots.php)

                        const group = document.querySelector('.filter');
                        const group_name = group.children[1].childNodes[0].nodeValue.trim();
                        const [start_time, end_time] = meeting_time.split(' - ');
                        const year = document.getElementById('label').innerHTML;
                        const month = document.querySelector('.active-month').innerHTML;
                        const day = document.querySelector('.active-date').innerHTML;

                        // send email
                        const emails = response.email;
                        const subject = encodeURIComponent(`${group_name} Meeting Time`);
                        const body = encodeURIComponent(`Hello everyone,\nOur meeting will be from ${start_time} to ${end_time}, starting on ${month}. ${day}, ${year} and repeating for ${repeat_weeks} weeks. `);

                        const mailto = `mailto:${emails}?subject=${subject}&body=${body}`;
                        window.open(mailto);

                        // select next group
                        const next_group = group.nextElementSibling;

                        // remove group from list
                        group.className += ' disappear';
                        group.addEventListener('transitionend', () => {
                            group.remove(); 
                        });

                        if (!next_group) { 
                            // group is the last group, navigate to first group
                            
                            const group_list = document.querySelector('.group-list');
                            const first_group = group_list.firstElementChild;

                            if (first_group.classList.contains('disappear')) { 
                                // no groups left
                                const node = document.createElement("div");
                                node.className = "group-name";
                                node.textContent = "NO GROUPS FOUND";
                                document.querySelector('.group-list').appendChild(node);

                                // hide calendar
                                document.querySelector('.calendar-container').setAttribute('style', 'display: none;');
                            
                            } else {
                                // select first group
                                group.classList.remove('filter');
                                first_group.className += ' filter';

                                // re-initialize calendar for new 'filter' group
                                init_calendar(new Date());
                            }
                            
                        } else {
                            // group is not the last group, continue to next group
                            group.classList.remove('filter');
                            next_group.className += ' filter';

                            // re-initialize calendar for new 'filter' group
                            init_calendar(new Date());
                        }

                        // hide form
                        document.querySelector('.events-container').classList.remove('show-form');
                    }
                }
            };

            xhttp.open('POST', 'add_group_slot.php', true);
            xhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhttp.send(
                'group_id=' + encodeURIComponent(group_id) + 
                '&meeting_id=' + encodeURIComponent(meeting_id) + 
                '&total_recurrences=' + encodeURIComponent(repeat_weeks)
            );
        });
    }
}