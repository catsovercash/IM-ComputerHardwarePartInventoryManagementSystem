<?php
// Start the session, destroy it, and redirect to the login page
session_start();
session_destroy();
header("Location: login.php");
exit;
?>