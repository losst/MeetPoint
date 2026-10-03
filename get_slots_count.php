
<?php // Nancy
// returns days in a given month that have slots as json obj

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["redirect" => "index.php"]);
    exit();
}

if (!isset($_GET['source'], $_GET['year'], $_GET['month'], $_GET['filter'])) {
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
    echo json_encode(["redirect" => "login.php"]);
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
$next_month = $month + 1;

$start_time = sprintf('%04d-%02d-01', $year, $month);

if ($month == 12) {
    $end_time = sprintf('%04d-01-01', $year + 1);
} else {
    $end_time = sprintf('%04d-%02d-01', $year, $month + 1);
}

if ($source === 'slots') {
    // display dot if slot exists on a given day, regardless of if a filter is selected
    
    // non-owners should not see page
    if ($role !== 'owner') {
        echo json_encode(["redirect" => "appointments.php"]); 
        $conn->close();
        exit();
    }

    // type 1 and 3
    $sql_bs = "
        SELECT DAY(start_time) as day, COUNT(*) as count
        FROM booking_slots 
        WHERE owner_id = ? 
            AND start_time >= ?
            AND end_time < ?
        GROUP BY DAY(start_time);
    ";

    $stmt_bs = $conn->prepare($sql_bs);
    $stmt_bs->bind_param("iss", $user_id, $start_time, $end_time);
    $stmt_bs->execute();
    $result_bs = $stmt_bs->get_result();

    $days = [];
    while ($row_bs = $result_bs->fetch_assoc()) {
        $days[$row_bs['day']] = (int) $row_bs['count'];
    }

    // type 2
    $sql_gm = "
        SELECT DAY(gm.start_time) as day, COUNT(*) as count
        FROM group_meetings gm
        JOIN groups g ON gm.group_id = g.group_id
        WHERE g.owner_id = ?
            AND start_time >= ?
            AND end_time < ?
            AND num_vote = -1
        GROUP BY DAY(start_time);
    ";

    $stmt_gm = $conn->prepare($sql_gm);
    $stmt_gm->bind_param("iss", $user_id, $start_time, $end_time);
    $stmt_gm->execute();
    $result_gm = $stmt_gm->get_result();

    while ($row_gm = $result_gm->fetch_assoc()) {
        $days[$row_gm['day']] += (int) $row_gm['count'];
    }
    echo json_encode(["data" => $days]);

    $conn->close();
    exit();

} else if ($source === 'group_meetings') {
    
    // filter is the group id
    $group_id = (int) $_GET['filter'];

    $sql = "
        SELECT DAY(start_time) as day, COUNT(*) as count
        FROM group_meetings 
        WHERE group_id = ? 
            AND start_time >= ?
            AND end_time < ?
            AND num_vote != -1
        GROUP BY DAY(start_time);
    ";

    $statement = $conn->prepare($sql);
    $statement->bind_param("iss", $group_id, $start_time, $end_time);
    $statement->execute();
    $result = $statement->get_result();

    $days = [];
    while ($row = $result->fetch_assoc()) {
        $days[$row['day']] = (int) $row['count'];
    }
    echo json_encode(["data" => $days]);

    $conn->close();
    exit();
   
} else if ($source === 'slots_owner') { // (Emily)
    
    $owner_id = isset($_GET['owner_id']) ? (int) $_GET['owner_id'] : null;

    if (!$owner_id) {
        echo json_encode(["data" => []]);
        exit();
    }

    // only count slots that are 'available' (not yet booked/pending)
    $sql = "
        SELECT DAY(bs.start_time) AS day, COUNT(*) AS count
        FROM booking_slots bs
        LEFT JOIN appointments a 
            ON bs.slot_id = a.slot_id
        WHERE bs.owner_id = ?
            AND bs.is_active = 1
            AND bs.start_time >= ?
            AND bs.end_time < ?
            AND a.slot_id IS NULL
        GROUP BY DAY(bs.start_time);
    ";

    $statement = $conn->prepare($sql);
    $statement->bind_param("iss", $owner_id, $start_time, $end_time);
    $statement->execute();
    $result = $statement->get_result();

    $days = [];
    while ($row = $result->fetch_assoc()) {
        $days[$row['day']] = (int) $row['count'];
    }
    echo json_encode(["data" => $days]);

    $conn->close();
    exit();

} else {
    // default empty map for other occasions
    echo json_encode(["data" => []]);

    $conn->close();
    exit();
}

?>