<?php
// Start a session so we can securely log the user in
session_start();
// Include the database connection file
include 'db.php';

// If already logged in, send them straight to the dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error_message = '';

// Check if the user clicked the Login button (submitting the POST request)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Grab username and password from the form
    $raw_username = $_POST['username'];
    $raw_password = $_POST['password'];
    
    // Secure them from SQL injections
    $safe_username = $conn->real_escape_string($raw_username);
    $safe_password = $conn->real_escape_string($raw_password);
    
    // Ask the database if this user exists
    $login_sql = "SELECT UserID, FullName, Role FROM Users WHERE Username = '$safe_username' AND UserPassword = '$safe_password'";
    $login_result = $conn->query($login_sql);
    
    if ($login_result) {
        // If the database found a match
        if ($login_result->num_rows > 0) {
            // Fetch the user's data
            $user_data = $login_result->fetch_assoc();
            
            // Save their details into the session memory
            $_SESSION['user_id'] = $user_data['UserID'];
            $_SESSION['full_name'] = $user_data['FullName'];
            $_SESSION['role'] = $user_data['Role'];
            
            // Send them to the dashboard
            header("Location: index.php");
            exit;
        } else {
            // No match found
            $error_message = "Invalid credentials. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sign In - KompyuTek</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body class="login-body">
    <div class="login-wrapper">
        <div class="login-card">
            <h1>Welcome Back</h1>
            <p class="subtitle">Please enter your details to sign in.</p>
            <?php 
            // Display error message if it exists
            if ($error_message != '') {
                echo '<div class="error-msg">' . htmlspecialchars($error_message) . '</div>';
            }
            ?>
            <form method="POST" class="login-form">
                <div class="form-group w-100">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="e.g. admin_kurt" required>
                </div>
                <div class="form-group w-100">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="login-btn">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>
