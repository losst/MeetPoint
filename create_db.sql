-- Emily
CREATE TABLE users ( 
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'owner') NOT NULL
);

-- keep track of recurring meetings
CREATE TABLE recurrences (
    recurring_id INT AUTO_INCREMENT PRIMARY KEY,
    total_recurrences INT NOT NULL -- weeks of recurrence
);

CREATE TABLE booking_slots (
    slot_id INT PRIMARY KEY AUTO_INCREMENT,
    owner_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT FALSE, -- set true if appointment exists (regardless booking/pending)
    is_recurring BOOLEAN DEFAULT FALSE,
    recurring_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (owner_id) REFERENCES users(user_id),
    FOREIGN KEY (recurring_id) REFERENCES recurrences(recurring_id)
    ON DELETE SET NULL
);

CREATE TABLE appointments (
    appointment_id INT PRIMARY KEY AUTO_INCREMENT,
    slot_id INT UNIQUE NOT NULL, -- one user per slot
    user_id INT NOT NULL,
    status ENUM('booked','pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (slot_id) REFERENCES booking_slots(slot_id)
    ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE groups (
    group_id INT PRIMARY KEY AUTO_INCREMENT,
    owner_id INT NOT NULL,
    title VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (owner_id) REFERENCES users(user_id)
);

CREATE TABLE group_meetings (
    meeting_id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    is_recurring BOOLEAN,
    recurring_id INT NULL,
    num_vote INT DEFAULT 0, -- -1 for meetings that have been finalized
    
    FOREIGN KEY (group_id) REFERENCES groups(group_id)
    ON DELETE CASCADE,
    FOREIGN KEY (recurring_id) REFERENCES recurrences(recurring_id)
    ON DELETE SET NULL
);

CREATE TABLE group_participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    UNIQUE(group_id, user_id), -- same user can only join once
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (group_id) REFERENCES groups(group_id)
    ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);


-- keep track of who voted what times
CREATE TABLE meeting_votes (
    vote_id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    user_id INT NOT NULL,

    UNIQUE(meeting_id, user_id), -- prevents double voting

    FOREIGN KEY (meeting_id) REFERENCES group_meetings(meeting_id)
    ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);


