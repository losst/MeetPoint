
<?php // Nancy
// returns slots html string stored in json obj

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["redirect" => "login.php"]);
    exit();
}

if (!isset($_GET['source'], $_GET['year'], $_GET['month'], $_GET['day'], $_GET['filter'])) {
    echo json_encode(["redirect" => "index.php"]);
    exit();
}

session_start();

$source = $_GET['source'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;

$requires_login = ($source !== 'slots_owner');

// session required but doesn't exist
if ($requires_login && !$user_id && !$role) {
    echo json_encode(["redirect" => "register.php"]);
    exit();
}

require_once __DIR__ . '/connect_db.php';
$user_id = (int) ($user_id ?? 0);

$statement = $conn->prepare("SELECT email FROM users WHERE user_id = ?;");
$statement->bind_param('i', $user_id);
$statement->execute();
$result = $statement->get_result();

// session exists but user doesn't
if ($requires_login) {
    if ($result->num_rows == 0) { 
        echo json_encode(["redirect" => "logout.php"]);
        $conn->close();
        exit();
    }
}

$year = (int) $_GET['year'];
$month = (int) $_GET['month'];
$day = (int) $_GET['day'];
$filter = $_GET['filter'];

// construct time string
$date = sprintf("%04d-%02d-%02d", $year, $month, $day);
$start_time = $date . " 00:00:00";
$end_time = $date . " 23:59:59";

if ($source === 'slots') {

    // non-owners should not see this page
    if ($role !== 'owner') { 
        echo json_encode(["redirect" => "appointments.php"]); 
        $conn->close();
        exit();
    }

    // query type 1 and 3 slots
    $where = ["owners.user_id = ? AND bs.start_time >= ? AND bs.end_time <= ?"];
    $params = [$user_id, $start_time, $end_time];
    $types = "iss"; 

    if ($filter === 'active' || $filter === 'private') {
        $where[] = "bs.is_active = ?";
        if ($filter === 'active') { $params[] = 1; }
        else { $params[] = 0; }
        $types .= "i";
    } elseif ($filter === 'pending' || $filter === 'booked') {
        $where[] = "a.status = ?";
        $params[] = $filter;
        $types .= "s";
    }

    $whereSql = "WHERE " . implode(" AND ", $where);

    $sql_bs = "
        SELECT 
            bs.slot_id,
            bs.start_time,
            bs.end_time,
            bs.is_active,
            bs.is_recurring,
            a.status,
            students.email
        FROM booking_slots bs
        JOIN users owners ON bs.owner_id = owners.user_id
        LEFT JOIN appointments a ON bs.slot_id = a.slot_id
        LEFT JOIN users students ON a.user_id = students.user_id
        $whereSql
        ORDER BY bs.start_time ASC;
    ";

    $stmt_bs = $conn->prepare($sql_bs);
    $stmt_bs->bind_param($types, ...$params);
    $stmt_bs->execute();
    $result_bs = $stmt_bs->get_result();

    // query type 2 slots (confirmed meetings only)
    if ($filter !== 'pending' && $filter !== 'private') {
        $sql_gm = "
            SELECT 
                gm.meeting_id,
                gm.group_id,
                gm.start_time,
                gm.end_time,
                gm.is_recurring,
                g.title,
                gp.user_id,
                students.email
            FROM group_meetings gm
            JOIN groups g ON gm.group_id = g.group_id
            LEFT JOIN group_participants gp ON gm.group_id = gp.group_id
            LEFT JOIN users students ON gp.user_id = students.user_id
            WHERE g.owner_id = ? AND gm.start_time >= ? AND gm.end_time <= ? AND gm.num_vote = -1
        ";

        $stmt_gm = $conn->prepare($sql_gm);
        $stmt_gm->bind_param('iss', $user_id, $start_time, $end_time);
        $stmt_gm->execute();
        $result_gm = $stmt_gm->get_result();
    }

    // no slots
    if ($result_bs->num_rows == 0 && (!$result_gm || $result_gm ->num_rows == 0)) {
        if ($filter !== '') $filter = $filter . " ";
        echo json_encode(["message" => "There are no " . $filter . "slots on " . $month . "." . $day . "."]);
        $conn->close();
        exit();
    }

    $slots = [];
    // type 1 and 3 slots
    while ($row_bs = $result_bs->fetch_assoc()) {
        $id = $row_bs['slot_id']; // slot_id is unique in appointments
        $slots[$id] = [
            'start_time' => $row_bs['start_time'],
            'end_time' => $row_bs['end_time'],
            'is_active' => $row_bs['is_active'],
            'is_recurring' => $row_bs['is_recurring'],
            'status' => $row_bs['status'],
            'students' => [$row_bs['email']] // one student per slot
        ];
    }
    // type 2 slots
    if ($result_gm) {
        while ($row_gm = $result_gm->fetch_assoc()) {
            $id = $row_gm['group_id'] . '_' . $row_gm['meeting_id'] . '_' . $row_gm['title']; // allows differentiation between type 1 and 3 vs 2
            if (!isset($slots[$id])) {
                $slots[$id] = [
                    'start_time' => $row_gm['start_time'],
                    'end_time' => $row_gm['end_time'],
                    'is_active' => 1,
                    'is_recurring' => $row_gm['is_recurring'],
                    'status' => 'booked', // type 2 will never be private or pending
                    'students' => [$row_gm['email']]
                ];
            } else {
                // add student if slot is already initialized
                $slots[$id]['students'][] = $row_gm['email'];
            }
        }
    }

    $string = [];
    foreach ($slots as $id => $slot) {
        $status = $slot['status'];
        $is_active = $slot['is_active'];
        $status_css = '';
        if ($status === 'pending') { $status_css = 'text-shadow: 0 0 8px #ffb347;'; }
        elseif ($status === 'booked') { $status_css = 'text-shadow: 0 0 8px #00ffcc;'; }
        elseif ($is_active) {
            $status_css = 'text-shadow: 0 0 8px #3399ff;'; 
            $status = 'active';
        }
        else { 
            $status_css = 'text-shadow: 0 0 8px #cccccc;'; 
            $status = 'private';
        }

        $string[] = '<div class="event">';

        $string[] = '<span class="event-id" style="display: none">' . $id . '</span>'; 
        $string[] = '<span class="event-status" style="' . $status_css . ';">' . ucfirst($status) . '</span>';
        
        $string[] = '<span class="event-time">';
        $string[] = date("g:i A", strtotime($slot['start_time'])) . ' - ' . date("g:i A", strtotime($slot['end_time']));
        $string[] = '</span>';

        if ($slot['is_recurring']) $is_recurring = 'Recurring';
        else $is_recurring = 'One-Time';
        $string[] = '<span class="event-recur">' . $is_recurring . '</span>';
        
        $lst = explode('_', $id);
        if (count($lst) > 1) {
            $u = $lst[2]; // get group title
            $email = implode(',', $slot['students']);
        } else {
            $email = $slot['students'][0];
            $name = explode('@', $email)[0]; // firstname.lastname
            $u = ucwords(str_replace('.', ' ', $name)); // firstname lastname
        }
        $string[] = '<span class="event-user">' . $u . '</span>';

        // action items
        if ($status === 'pending') {
            $string[] = '<span class="event-action">';
            $string[] = '<span class="positive-action">ACCEPT</span>';
            $string[] = '<span class="divider">|</span>';
            $string[] = '<span class="negative-action">DECLINE</span></span>';

        } else if ($status === 'private') {
            $string[] = '<span class="event-action">';
            $string[] = '<span class="positive-action">MAKE PUBLIC</span>';
            $string[] = '<span class="divider">|</span>';
            $string[] = '<span class="negative-action">DELETE SLOT</span></span>';

        } else if ($status === 'booked') {
            $string[] = '<span class="event-action">';
            $string[] = '<span class="positive-action">';
            $string[] = '<a href="mailto:' . htmlspecialchars($email) . '">EMAIL</a></span>';
            $string[] = '<span class="divider">|</span><span class="negative-action">DELETE SLOT</span></span>';
        
        } else { // no positive action for slots that are active but not reserved
            $string[] = '<span class="event-action">';
            $string[] = '<span class="positive-action"></span>';
            $string[] = '<span class="negative-action">DELETE SLOT</span></span>';
        }

        $string[] = '</div>';
    }

    $msg = implode('', $string);
    echo json_encode(["message" => $msg]);
    
    $conn->close();
    exit();

} else if ($source === 'group_meetings') {

    // filter is the group id, only query if set (i.e. if groups exist)
    if ($filter === '') {
        echo json_encode(["message" => "No groups"]);
        $conn->close();
        exit();
    }

    $group_id = (int) $filter;

    // check if the account is the owner of the group being queried
    $sql = "SELECT owner_id FROM groups WHERE group_id = ? AND owner_id = ?;";
    $statement = $conn->prepare($sql);
    $statement->bind_param("ii", $group_id, $user_id);
    $statement->execute();
    $result = $statement->get_result();

    $is_owner = false;

    // at this point, recurring meetings are not all set yet
    // only the first recurring meeting is set
    
    if ($result->num_rows > 0) {
        // they are the owner of the selected group
        $is_owner = true;
        $sql2 = "
            SELECT meeting_id, start_time, end_time, is_recurring, num_vote
            FROM group_meetings
            WHERE group_id = ? 
                AND start_time >= ?
                AND end_time <= ?
                AND num_vote != -1
            ORDER BY start_time ASC;
        ";
        $types2 = 'iss';
        $params2 = [$group_id, $start_time, $end_time];

    } else {
        // they are a participant of the selected group
        $sql2 = "
            SELECT 
                gm.meeting_id, 
                gm.start_time, 
                gm.end_time, 
                gm.is_recurring, 
                gm.num_vote,
                CASE 
                    WHEN mv.user_id IS NULL THEN 0
                    ELSE 1
                END AS has_voted
            FROM group_meetings gm
            LEFT JOIN meeting_votes mv 
                ON gm.meeting_id = mv.meeting_id 
                AND mv.user_id = ?
            WHERE gm.group_id = ? 
                AND gm.start_time >= ?
                AND gm.end_time <= ?
                AND gm.num_vote != -1
            ORDER BY gm.start_time ASC;
        ";
        $types2 = 'iiss';
        $params2 = [$user_id, $group_id, $start_time, $end_time];

    }

    $statement2 = $conn->prepare($sql2);
    $statement2->bind_param($types2, ...$params2);
    $statement2->execute();
    $result2 = $statement2->get_result();

    // no slots
    if ($result2->num_rows == 0) {
        echo json_encode(["message" => "There are no slots on " . $month . "." . $day . "."]);
        $conn->close();
        exit();
    }

    $has_recurring = false;
    $string = [];

    while ($row2 = $result2->fetch_assoc()) {
        if ($is_owner) $string[] = '<div class="event" title="Select this slot">';
        else {
            // prevent double voting
            if ($row2['has_voted']) $string[] = '<div class="event voted" title="Vote for this slot">';
            else $string[] = '<div class="event" title="Vote for this slot">';
        }
        
        $string[] = '<span class="event_id" style="display: none">';
        $string[] = $row2['meeting_id'] . '</span><span class="event-time">';
        $string[] = date("g:i A", strtotime($row2['start_time'])) . ' - ' . date("g:i A", strtotime($row2['end_time']));
        
        if ($row2['is_recurring']) {
            $is_recurring = 'Recurring'; 
            $has_recurring = true;
        } else {
            $is_recurring = 'One-time';
        }
        $string[] = '</span><span class="event-recur">' . $is_recurring . '</span>';

        if ($is_owner) {
            $string[] = '<span class="event-status"> Votes: ' . $row2['num_vote'] . '</span>';
        }
        else {
            if ($row2['has_voted']) $string[] = '<span class="voted-status">Voted ✓</span>';
        }
        $string[] = '</div>';
    }

    // if owner and recurring, prepare form to enter recurrence count
    if ($is_owner && $has_recurring) {
        $string[] = '<div class="set-recurrence-form">';
        $string[] = '<span class="close">&times;</span>';
        $string[] = '<form class="form-main">';
        $string[] = '<input type="hidden" name="group-id" id="group-id">';
        $string[] = '<input type="hidden" name="meeting-id" id="meeting-id">';
        $string[] = '<input type="hidden" name="meeting-time" id="meeting-time">';
        $string[] = '<div class="form-content">';
        $string[] = '<label class="form-title" for="repeat-weeks">REPEAT FOR </label>';
        $string[] = '<input type="number" id="repeat-weeks" name="repeat-weeks" min="1" max="16" value="8" style="text-align: center;">';
        $string[] = '<span class="inline-label"> WEEKS</span>';
        $string[] = '</div>';
        $string[] = '<button class="confirm-btn">Confirm</button>';
        $string[] = '</form></div>';          
    }

    $msg = implode('', $string);
    echo json_encode(["message" => $msg]);

    $conn->close();
    exit();

} else if ($source === 'slots_owner') { // (Emily)
    
    if ($filter === '') {
        echo json_encode(["message" => "No owner selected."]);
        $conn->close();
        exit();
    }

    $owner_id = (int)$filter;

    // Fetch only slots that are 'available' for this owner on this specific day
    $sql = "
        SELECT 
            bs.slot_id,
            bs.start_time,
            bs.end_time,
            bs.is_recurring
        FROM booking_slots bs
        LEFT JOIN appointments a 
            ON bs.slot_id = a.slot_id
        WHERE bs.owner_id = ?
            AND bs.is_active = 1
            AND bs.start_time >= ?
            AND bs.end_time < ?
            AND a.slot_id IS NULL
        ORDER BY bs.start_time ASC;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $owner_id, $start_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo json_encode(["message" => "No available slots"]);
        $conn->close();
        exit();
    }

    
    $string = [];
    while ($row = $result->fetch_assoc()) {
        $start = date("g:i A", strtotime($row['start_time']));
        $end = date("g:i A", strtotime($row['end_time']));
        $recur_text = $row['is_recurring'] ? 'Recurring' : 'One-Time';

        $string[] = '<div class="event">';

        $string[] = '<span class="event-id" style="display:none">' . $row['slot_id'] . '</span>';

        // TIME
        $string[] = '<span class="event-time">' . $start . ' - ' . $end . '</span>';

        // RECURR
        $string[] = '<span class="event-status">' . $recur_text . '</span>';

        if ($owner_id !== $user_id) { // if user is not the owner of these slots, allow booking option
            // BUTTON
            $string[] = '<span class="event-action">';
            $string[] = '<button class="book-slot-btn">
                            <img src="assets/add_icon.png" class="book-icon-btn">
                            BOOK
                            </button>';
            $string[] = '</span>';

            
        }
        $string[] = '</div>';
    }

    echo json_encode(["message" => implode('', $string)]);
    $conn->close();
    exit();

} else {
    // default empty message for other occasions
    echo json_encode(["message" => ""]);

    $conn->close();
    exit();
}

?>