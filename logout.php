<?php // Nancy
// destroy session data and redirect to index page

session_start();
session_unset();
session_destroy();
header("Location: index.php");
exit();

?>