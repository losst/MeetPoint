
<?php // Nancy
// if owner, officially creates group meeting (and recurring meetings if applicable)
// or if student, casts vote

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["redirect" => "index.php"]);
    exit();
}

if (!isset($_POST['group_id'], $_POST['meeting_id'])) {
    echo json_encode(["redirect" => "index.php"]);
    exit();
}

session_start();

// session doesn't exist
if (!isset($_SESSION['user_id'], $_SESSION['role'])) { 
    echo json_encode(["redirect" => "index.php"]);
    exit();
}

require_once __DIR__ . '/connect_db.php';
$user_id = (int) $_SESSION['user_id'];

$statement = $conn->prepare("SELECT email FROM users WHERE user_id = ?;");
$statement->bind_param('i', $user_id);
$statement->execute();
$result = $statement->get_result();

// session exists but user doesn't
if ($result->num_rows == 0) { 
    echo json_encode(["redirect" => "logout.php"]);
    $conn->close();
    exit();
}

$role = $_SESSION['role'];
$group_id = (int) $_POST['group_id'];
$meeting_id = (int) $_POST['meeting_id'];

// check if the account is the owner of the group being queried
$sql_g = "SELECT owner_id FROM groups WHERE group_id = ? AND owner_id = ?;";
$stmt_g = $conn->prepare($sql_g);
$stmt_g->bind_param("ii", $group_id, $user_id);
$stmt_g->execute();
$result_g = $stmt_g->get_result();

// check role
if ($result_g->num_rows > 0) {
    // owner

    // set num_vote to -1 to indicate this meeting time has been selected for that group
    $sql_gm = "UPDATE group_meetings SET num_vote = -1 WHERE meeting_id = ?;";
    $stmt_gm = $conn->prepare($sql_gm);
    $stmt_gm->bind_param('i', $meeting_id);
    $stmt_gm->execute();

    // remove other meeting slots for that group
    $sql_gm_rm = "DELETE FROM group_meetings WHERE group_id = ? AND meeting_id != ?;";
    $stmt_gm_rm = $conn->prepare($sql_gm_rm);
    $stmt_gm_rm->bind_param('ii', $group_id, $meeting_id);
    $stmt_gm_rm->execute();

    // if it is recurring, create additional group meetings based on recurring weeks
    if (isset($_POST['total_recurrences'])) {
        $total_recurrences = (int) $_POST['total_recurrences'];

        $conn->begin_transaction();
        try {
            // set recurring_id
            $sql_r = "INSERT INTO recurrences (total_recurrences) VALUES (?);";
            $stmt_r = $conn->prepare($sql_r);
            $stmt_r->bind_param('i', $total_recurrences);
            $stmt_r->execute();
            $recurring_id = $conn->insert_id;

            // update original meeting recurring_id
            $sql_gm_r = "UPDATE group_meetings SET recurring_id = ? WHERE meeting_id = ?;";
            $stmt_gm_r = $conn->prepare($sql_gm_r);
            $stmt_gm_r->bind_param('ii', $recurring_id, $meeting_id);
            $stmt_gm_r->execute();

            // get original meeting times
            $sql_gm_t = "SELECT start_time, end_time FROM group_meetings WHERE meeting_id = ?;";
            $stmt_gm_t = $conn->prepare($sql_gm_t);
            $stmt_gm_t->bind_param('i', $meeting_id);
            $stmt_gm_t->execute();
            $result_gm_t = $stmt_gm_t->get_result();
            $row_gm_t = $result_gm_t->fetch_assoc();

            $start_time = $row_gm_t['start_time'];
            $end_time = $row_gm_t['end_time'];

            // insert recurring group meetings
            $sql_insert = "
                INSERT INTO group_meetings (group_id, start_time, end_time, is_recurring, recurring_id, num_vote)
                VALUES (?, ?, ?, 1, ?, -1);
            ";
            $stmt_insert = $conn->prepare($sql_insert);

            for ($i = 1; $i < $total_recurrences; $i++) {
                $new_start = date("Y-m-d H:i:s", strtotime($start_time . "+ $i week"));
                $new_end = date("Y-m-d H:i:s", strtotime($end_time . "+ $i week"));

                $stmt_insert->bind_param('issi', $group_id, $new_start, $new_end, $recurring_id);
                $stmt_insert->execute();
            }

            $conn->commit();

        } catch (Exception $e) {
            $conn->rollback();
        }
    }
    
    // extract users
    $sql_gp = "
        SELECT students.email
        FROM group_participants gp
        JOIN users students ON gp.user_id = students.user_id
        WHERE gp.group_id = ?;
    ";
    $stmt_gp = $conn->prepare($sql_gp);
    $stmt_gp->bind_param('i', $group_id);
    $stmt_gp->execute();
    $result_gp = $stmt_gp->get_result();
    
    // get owner email from previous query (as per instructions)
    $owner_email = $result->fetch_assoc();

    $emails = [$owner_email['email']];
    while ($row_gp = $result_gp->fetch_assoc()) {
        $emails[] = $row_gp['email'];
    }

    $email = implode(',', $emails);
    echo json_encode(["email" => $email]);

    $conn->close();
    exit();

} else {
    // user 

    // add to num_vote of group meeting
    $sql_gm = "UPDATE group_meetings SET num_vote = num_vote + 1 WHERE meeting_id = ?;";
    $stmt_gm = $conn->prepare($sql_gm);
    $stmt_gm->bind_param('i', $meeting_id);
    $stmt_gm->execute();

    // record user vote to prevent double voting
    $sql_mv = "INSERT INTO meeting_votes (meeting_id, user_id) VALUES (?, ?);";
    $stmt_mv = $conn->prepare($sql_mv);
    $stmt_mv->bind_param('ii', $meeting_id, $user_id);
    $stmt_mv->execute();

    echo json_encode(["vote" => "voted"]);

    $conn->close();
    exit();
} 

?>