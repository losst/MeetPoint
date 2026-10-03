
<?php // Nancy
// checks if email and password combination has a corresponding user
// if yes, store user_id and role as session data
// if not, return error message

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["redirect" => "login.php"]);
    exit();
}

if (!isset($_POST['email'], $_POST['password'])) {
    echo json_encode(["redirect" => "login.php"]);
    exit();
}

session_start();

// redirect parameters (Emily)
$redirect = $_POST['redirect'] ?? 'appointments.php';
$baseRedirect = explode('?', $redirect)[0];

// prevent malicious redirects (Emily)
if (
    !str_starts_with($baseRedirect, "slots_owner.php") &&
    !str_starts_with($baseRedirect, "appointments.php")
) {
    $redirect = "appointments.php";
}

// session exists
if (isset($_SESSION['user_id'], $_SESSION['role'])) { 
    echo json_encode(["redirect" => $redirect]); 
    exit();
}

require_once __DIR__ . '/connect_db.php';

$email = $_POST['email'];
$raw_password = $_POST['password'];

$statement = $conn->prepare("SELECT user_id, role, password FROM users WHERE email = ?;");
$statement->bind_param('s', $email);
$statement->execute();
$result = $statement->get_result();

$user = null;
if ($result->num_rows != 0) {
    $user = $result->fetch_assoc();
}

if ($user) {
    // user exists
    $hashed_password = $user['password'];

    // check password
    if (password_verify($raw_password, $hashed_password)) { 

        // update hash if needed
        if (password_needs_rehash($hashed_password, PASSWORD_DEFAULT)) {
            $new_password = password_hash($raw_password, PASSWORD_DEFAULT);

            $stmt_u = $conn->prepare("UPDATE users SET password = ? WHERE email = ?;");
            $stmt_u->bind_param('ss', $new_password, $email);
            $stmt_u->execute();
        }

        // store session data
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];

        // log in, redirect to slots_owner/appointments (Emily)
        echo json_encode(["redirect" => $redirect]);
        $conn->close();
        exit();

    } else {
        // wrong password
        echo json_encode(["message" => "Invalid password"]);
        $conn->close();
        exit();
    }

} else {
    // user doesn't exist
    echo json_encode(["message" => "Invalid email"]);
    $conn->close();
    exit();
}

?>

