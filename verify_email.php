<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include "db_conn.php";

$msg = "";
$msg_type = "";

// 1. Get Email (Restrict Access)
if (isset($_GET['email'])) {
    $email = mysqli_real_escape_string($conn, $_GET['email']);
} else {
    header("Location: signup.php");
    exit();
}

// 2. Verify Logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = mysqli_real_escape_string($conn, $_POST['otp_code']);
    
    // Check against Database
    $sql = "SELECT * FROM users WHERE email='$email' AND verification_code='$code'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        // Success: Update status to Pending
        $conn->query("UPDATE users SET status='pending', verification_code=0 WHERE email='$email'");
        
        $msg = "Success! Email Verified. Please wait for Admin Approval.";
        $msg_type = "success";
        header("refresh:3;url=index.php"); // Redirect to Login
    } else {
        $msg = "Invalid Verification Code.";
        $msg_type = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Email - SAPD</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        @import url("https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap");
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #a691ab; }
        .container { width: 450px; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 15px 30px rgba(0,0,0,0.15); text-align: center; }
        .brand-title { font-family: "Bebas Neue", sans-serif; font-size: 2rem; margin-bottom: 20px; }
        .input-otp { width: 100%; padding: 15px; text-align: center; font-size: 1.5rem; letter-spacing: 8px; margin: 20px 0; border: 2px solid #ddd; border-radius: 8px; outline: none; }
        .btn-submit { width: 100%; padding: 12px; background: #000; color: #fff; border: none; border-radius: 25px; cursor: pointer; font-weight: 600; transition:0.3s; }
        .btn-submit:hover { background: #333; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; font-size: 0.9rem; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .alert-success { background: #d1e7dd; color: #0f5132; }
        .info-text { color: #555; font-size: 0.95rem; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="brand-title">Check Your Email</h1>
        <p class="info-text">
            We sent a verification code to <br><strong><?php echo htmlspecialchars($email); ?></strong>
        </p>

        <?php if($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="otp_code" class="input-otp" maxlength="6" placeholder="000000" required>
            <button type="submit" class="btn-submit">Verify Code</button>
        </form>
        
        <p style="margin-top:20px; font-size:0.85rem;">
            Didn't get the email? <a href="signup.php" style="color:#000; font-weight:bold;">Try Again</a>
        </p>
    </div>
</body>
</html>