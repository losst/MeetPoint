
<?php // Nancy
// if not already in db, add user

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["redirect" => "register.php"]);
    exit();
}

if (!isset($_POST['email'], $_POST['password'])) {
    echo json_encode(["redirect" => "register.php"]);
    exit();
}

session_start();

// session exists
if (isset($_SESSION['user_id'], $_SESSION['role'])) { 
    echo json_encode(["redirect" => "appointments.php"]); // to be modified
    exit();
}

require_once __DIR__ . '/connect_db.php';

$email = $_POST['email'];
$raw_password = $_POST['password'];

// check if already registered
$statement = $conn->prepare("SELECT * FROM users WHERE email = ?;");
$statement->bind_param('s', $email);
$statement->execute();
$result = $statement->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["message" => "An account already exists under this email."]);
    $conn->close();
    exit();
}

// hash password
$hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

// determine role
$at_pos = strpos($email, '@');
$role = 'owner';
if (substr($email, $at_pos + 1, 4) == 'mail') {
    $role = 'student';
}

$statement = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
$statement->bind_param('sss', $email, $hashed_password, $role);
$statement->execute();

echo json_encode(["message" => "Registered Successfully"]);
$conn->close();
exit();

?>