<?php
// 1. Start Session & Connect
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include "db_conn.php";

// 2. Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure PHPMailer folder is in C:\xampp\htdocs\SAPD\
if (file_exists('PHPMailer/src/Exception.php')) {
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';
} else {
    die("Error: PHPMailer folder is missing. Please put the 'PHPMailer' folder in your SAPD directory.");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // 3. Check for Duplicates
    $check = $conn->query("SELECT id FROM users WHERE username='$username' OR email='$email'");
    if ($check->num_rows > 0) {
        $error = "Username or Email already taken.";
    } else {
        // 4. Create User (Unverified)
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $otp = rand(100000, 999999);
        
        $sql = "INSERT INTO users (name, email, username, password, role, status, verification_code) 
                VALUES ('$fullname', '$email', '$username', '$hashed', 'user', 'unverified', '$otp')";
        
        if ($conn->query($sql)) {
            // 5. Send Email via PHPMailer
            $mail = new PHPMailer(true);
            try {
                // SMTP Configuration
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'sapdstaff@gmail.com';     // <--- UPDATE THIS
                $mail->Password   = 'eyvzdgohltymzyvd';        // <--- PASTE 16-CHAR APP PASSWORD
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                // Email Settings
                $mail->setFrom('no-reply@sapd.com', 'SAPD System');
                $mail->addAddress($email); // Send to user

                // Email Content
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your SAPD Account';
                $mail->Body    = "
                    <div style='font-family: Arial; padding: 20px; border: 1px solid #eee;'>
                        <h2 style='color:#333;'>Welcome to SAPD!</h2>
                        <p>Your verification code is:</p>
                        <h1 style='color: #4318ff; letter-spacing: 5px;'>$otp</h1>
                        <p>Enter this code to complete your registration.</p>
                    </div>
                ";

                $mail->send();
                
                // Redirect to Verification Page
                header("Location: verify_email.php?email=" . urlencode($email));
                exit();

            } catch (Exception $e) {
                $error = "User registered, but email failed. Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "Database Error: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - SAPD</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Shared Styles */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        @import url("https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap");
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #a691ab; }
        .container { display: flex; width: 900px; height: 580px; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 15px 30px rgba(0,0,0,0.15); }
        .left-panel { width: 40%; background-color: #757575; display: flex; flex-direction: column; justify-content: center; align-items: center; color: #fff; }
        .logo-img { width: 130px; margin-bottom: 15px; display: block; margin: 0 auto 15px; }
        .brand-title { font-family: "Bebas Neue", sans-serif; font-size: 3.5rem; letter-spacing: 2px; line-height: 1; margin: 0; }
        .right-panel { width: 60%; padding: 40px; display: flex; flex-direction: column; justify-content: center; }
        .header-text { text-align: center; font-size: 2rem; font-weight: 600; color: #333; margin-bottom: 25px; }
        .input-group { margin-bottom: 15px; position: relative; }
        .input-group label { display: block; font-size: 0.9rem; font-weight: 500; color: #333; margin-bottom: 5px; }
        .input-group input { width: 100%; padding: 12px 15px; border: none; border-radius: 6px; background: #e8e8e8; outline: none; }
        .toggle-password { position: absolute; right: 15px; top: 38px; cursor: pointer; color: #666; }
        .btn-submit { width: 100%; padding: 12px; margin-top: 15px; background: #000; color: #fff; border: none; border-radius: 30px; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { background: #333; }
        .links { margin-top: 15px; text-align: center; font-size: 0.85rem; color: #666; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 6px; font-size: 0.85rem; text-align: center; background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <img src="background.png" alt="Logo" class="logo-img" onerror="this.style.display='none'">
            <h1 class="brand-title">SAPD SYSTEM</h1>
        </div>
        <div class="right-panel">
            <h2 class="header-text">Create Account</h2>
            <?php if($error): ?><div class="alert"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <div class="input-group"><label>Full Name</label><input type="text" name="fullname" required></div>
                <div class="input-group"><label>Email Address</label><input type="email" name="email" required></div>
                <div class="input-group"><label>Username</label><input type="text" name="username" required></div>
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" id="signupPass" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePass()"></i>
                </div>
                <button type="submit" class="btn-submit">Sign Up</button>
                <div class="links"><p>Already have an account? <a href="index.php">Log In</a></p></div>
            </form>
        </div>
    </div>
    <script>
        function togglePass() {
            var x = document.getElementById("signupPass");
            x.type = (x.type === "password") ? "text" : "password";
        }
    </script>
</body>
</html>