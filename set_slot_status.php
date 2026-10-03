
<?php // Nancy
// execute slot action from slots.php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["redirect" => "index.php"]);
    exit();
}

if (!isset($_POST['id'], $_POST['status'], $_POST['action'])) {
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

$statement = $conn->prepare("SELECT role FROM users WHERE user_id = ?;");
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

// non-owners should not see this page
if ($role !== 'owner') {
    echo json_encode(["redirect" => "appointments.php"]); 
    $conn->close();
    exit();
}

$id = explode('_', $_POST['id']);
$group_id = null;
$slot_id = null;
if (count($id) > 1) { // group meeting
    $group_id = (int) $id[0];
    $meeting_id = (int) $id[1];
} else {
    $slot_id = (int) $id[0];
}

$status = $_POST['status'];
$action = $_POST['action'];

if ($action === 'positive') {
    // status will always be private or pending, which will never be type 2

    // extra check
    if (!$slot_id) {
        echo json_encode(["error" => "Error: Slot does not exist"]); 
        $conn->close();
        exit();
    }

    if ($status === 'private') {
        // makes public all recurring slots if recurring
        $sql_bs = "
            UPDATE booking_slots SET is_active = 1 
            WHERE 
                (
                    recurring_id IS NOT NULL AND
                    recurring_id = (
                        SELECT recurring_id 
                        FROM booking_slots 
                        WHERE slot_id = ?
                    )
                )
                OR (
                    recurring_id IS NULL AND slot_id = ?
                );
        ";
        $stmt_bs = $conn->prepare($sql_bs);
        $stmt_bs->bind_param('ii', $slot_id, $slot_id);
        $stmt_bs->execute();

        echo json_encode(["message" => "Made public"]);
        $conn->close();
        exit();

    } else if ($status === 'pending') {
        // set as booked and return student email
        $sql_a = "UPDATE appointments SET status = 'booked' WHERE slot_id = ?;";
        $stmt_a = $conn->prepare($sql_a);
        $stmt_a->bind_param('i', $slot_id);
        $stmt_a->execute();

        $sql_e = "
            SELECT students.email
            FROM appointments a 
            JOIN users students ON a.user_id = students.user_id
            WHERE a.slot_id = ?
        ";
        $stmt_e = $conn->prepare($sql_e);
        $stmt_e->bind_param('i', $slot_id);
        $stmt_e->execute();
        $result_e = $stmt_e->get_result();
        $row_e = $result_e->fetch_assoc();

        if ($row_e) $email = $row_e['email'];
        else $email = '!Error';

        echo json_encode(["email" => $email]);
        $conn->close();
        exit();

    } else {
        echo json_encode(["error" => "Error: Invalid status"]);
        $conn->close();
        exit();
    }

} else if ($action === 'negative') {
    if ($group_id) { 
        // type 2
        $sql_gp = "
            SELECT 
                students.email
            FROM group_participants gp 
            JOIN users students ON gp.user_id = students.user_id
            WHERE gp.group_id = ?
        ";

        $stmt_gp = $conn->prepare($sql_gp);
        $stmt_gp->bind_param('i', $group_id);
        $stmt_gp->execute();
        $result_gp = $stmt_gp->get_result();

        $emails = [];
        while ($row = $result_gp->fetch_assoc()) {
            $emails[] = $row['email'];
        }

        // remove single group meeting
        $stmt_g_rm = $conn->prepare("DELETE FROM group_meetings WHERE meeting_id = ?;");
        $stmt_g_rm->bind_param("i", $meeting_id);
        $stmt_g_rm->execute();

        $email = implode(',', $emails);
        echo json_encode(["email" => $email]);
        $conn->close();
        exit();

    } else if ($slot_id) { 
        // type 1 and 3
        if ($status === 'private' || $status === 'active') {
            // remove, no emails
            $sql_bs_rm = "DELETE FROM booking_slots WHERE slot_id = ?;";
            $stmt_bs_rm = $conn->prepare($sql_bs_rm);
            $stmt_bs_rm->bind_param('i', $slot_id);
            $stmt_bs_rm->execute();

            echo json_encode(["message" => "Deleted"]);
            $conn->close();
            exit();

        } else {
            // remove and return emails
            $sql_a = "
                SELECT students.email
                FROM appointments a 
                JOIN users students ON a.user_id = students.user_id
                WHERE a.slot_id = ?
            ";
            $stmt_a = $conn->prepare($sql_a);
            $stmt_a->bind_param('i', $slot_id);
            $stmt_a->execute();
            $result_a = $stmt_a->get_result();
            $row_a = $result_a->fetch_assoc();

            if ($row_a) $email = $row_a['email'];
            else $email = '!Error';

            // remove single slot
            $stmt_rm = $conn->prepare("DELETE FROM booking_slots WHERE slot_id = ?;");
            $stmt_rm->bind_param("i", $slot_id);
            $stmt_rm->execute();

            echo json_encode(["email" => $email]);
            $conn->close();
            exit();
        }
    } else {
        echo json_encode(["error" => "Error: Bad ID"]);
        $conn->close();
        exit();
    }

} else {
    echo json_encode(["error" => "Error: Invalid action"]);
    $conn->close();
    exit();
}

?>