DROP TABLE appointments;
DROP TABLE booking_slots;
DROP TABLE group_participants;
DROP TABLE meeting_votes;
DROP TABLE group_meetings;
DROP TABLE recurrences;
DROP TABLE groups;
DROP TABLE users;



INSERT INTO users (user_id, email, password, role) VALUES
(1, 'owner1@mcgill.ca', 'pass', 'owner'),
(2, 'owner2@mcgill.ca', 'pass', 'owner'),

(3, 'student1@mail.mcgill.ca', 'pass', 'student'),
(4, 'student2@mail.mcgill.ca', 'pass', 'student'),
(5, 'student3@mail.mcgill.ca', 'pass', 'student'),
(6, 'student4@mail.mcgill.ca', 'pass', 'student'),
(7, 'student5@mail.mcgill.ca', 'pass', 'student');

INSERT INTO groups (group_id, owner_id, title) VALUES
(1, 1, 'COMP307 Study Group'),
(2, 1, 'Final Exam Prep'),
(3, 2, 'Research Discussion'),
(4, 2, 'Weekly Sync');

INSERT INTO group_participants (group_id, user_id) VALUES
-- Groups owned by owner1
(1, 3),
(1, 4),
(1, 2), -- owner2 participates

(2, 5),
(2, 6),
(2, 2),

-- Groups owned by owner2
(3, 3),
(3, 7),
(3, 1), -- owner1 participates

(4, 4),
(4, 5),
(4, 1);

INSERT INTO group_meetings (meeting_id, group_id, start_time, end_time, is_recurring, recurring_id, num_vote) VALUES
-- Group 1
(1, 1, '2026-04-21 10:00:00', '2026-04-21 11:00:00', 0, NULL, 0),
(2, 1, '2026-04-22 14:00:00', '2026-04-22 15:00:00', 1, NULL, 0),

-- Group 2
(3, 2, '2026-04-23 09:00:00', '2026-04-23 10:00:00', 1, NULL, 0),
(4, 2, '2026-04-24 13:00:00', '2026-04-24 14:00:00', 0, NULL, 0),

-- Group 3
(5, 3, '2026-04-21 16:00:00', '2026-04-21 17:00:00', 1, NULL, 0),
(6, 3, '2026-04-25 11:00:00', '2026-04-25 12:00:00', 0, NULL, 0),

-- Group 4
(7, 4, '2026-04-22 10:00:00', '2026-04-22 11:00:00', 0, NULL, 0),
(8, 4, '2026-04-23 15:00:00', '2026-04-23 16:00:00', 1, NULL, 0);

INSERT INTO booking_slots (slot_id, owner_id, start_time, end_time, is_active, is_recurring, recurring_id) VALUES
-- Owner 1 slots
(1, 1, '2026-04-21 09:00:00', '2026-04-21 10:00:00', 0, 0, NULL),
(2, 1, '2026-04-21 10:00:00', '2026-04-21 11:00:00', 1, 0, NULL),
(3, 1, '2026-04-22 11:00:00', '2026-04-22 12:00:00', 1, 1, NULL),
(4, 1, '2026-04-23 14:00:00', '2026-04-23 15:00:00', 0, 1, NULL),

-- Owner 2 slots
(5, 2, '2026-04-21 13:00:00', '2026-04-21 14:00:00', 1, 0, NULL),
(6, 2, '2026-04-22 09:00:00', '2026-04-22 10:00:00', 0, 0, NULL),
(7, 2, '2026-04-23 10:00:00', '2026-04-23 11:00:00', 1, 1, NULL),

-- Active with NO appointment
(8, 1, '2026-04-24 09:00:00', '2026-04-24 10:00:00', 1, 0, NULL),
(9, 1, '2026-04-24 10:00:00', '2026-04-24 11:00:00', 1, 1, NULL),

-- Active with appointments (to balance pending/booked)
(10, 1, '2026-04-25 11:00:00', '2026-04-25 12:00:00', 1, 0, NULL),
(11, 1, '2026-04-25 13:00:00', '2026-04-25 14:00:00', 1, 1, NULL);

INSERT INTO appointments (appointment_id, slot_id, user_id, status) VALUES
(1, 2, 3, 'pending'),
(2, 3, 4, 'booked'),
(3, 5, 5, 'pending'),
(4, 7, 6, 'booked'),
(5, 10, 5, 'pending'),
(6, 11, 6, 'booked');
