<?php
session_start();
include "db_conn.php";

$token = $_GET['token'] ?? "";
$error = "";
$success = "";

if (empty($token)) {
    die("Invalid request.");
}

$token_hash = hash("sha256", $token);

// Find user with this token and ensure it hasn't expired
$sql = "SELECT * FROM users WHERE reset_token_hash = '$token_hash' AND reset_token_expires_at > NOW()";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

if (!$user) {
    die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'><h3>Link expired or invalid.</h3><a href='index.php'>Go to Login</a></div>");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];

    if ($pass1 !== $pass2) {
        $error = "Passwords do not match.";
    } elseif (strlen($pass1) < 4) {
        $error = "Password must be at least 4 characters.";
    } else {
        // Update Password (Plain text per your current system, use password_hash in production)
        $new_pass = mysqli_real_escape_string($conn, $pass1);
        
        $sql = "UPDATE users SET password = '$new_pass', reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = {$user['id']}";
        if ($conn->query($sql)) {
            $success = "Password updated! You can now login.";
        } else {
            $error = "Database error.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="fb-body">

    <div class="card shadow p-4" style="max-width: 400px; width: 100%;">
        <h3 class="fw-bold mb-3">Choose a New Password</h3>
        
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <br><a href="index.php" class="fw-bold">Go to Login</a>
            </div>
        <?php else: ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-bold">New Password</label>
                <input type="password" name="pass1" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Confirm Password</label>
                <input type="password" name="pass2" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold" style="background-color: #1877f2;">Change Password</button>
        </form>
        <?php endif; ?>
    </div>

</body>
</html>