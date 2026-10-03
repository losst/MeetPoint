
<?php // Nancy

try {
    $conn = new mysqli('127.0.0.1', 'cs307-user', 'EHlWHRBOENtw2EuQLPrJ', 'comp-307-db');
} catch (mysqli_sql_exception $e) {
    die('Database connection failed: ' . $conn->connect_error);
}

?>