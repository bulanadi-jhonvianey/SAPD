<?php
session_start();
include "db_conn.php";

$message = "";
$msg_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Check if email exists
    $sql = "SELECT id, name FROM users WHERE email='$email' LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        
        // Generate Token
        $token = bin2hex(random_bytes(16));
        $token_hash = hash("sha256", $token);
        $expiry = date("Y-m-d H:i:s", time() + 60 * 30); // 30 minutes expiry

        // Update User Record with Token
        $update = $conn->query("UPDATE users SET reset_token_hash='$token_hash', reset_token_expires_at='$expiry' WHERE email='$email'");

        if ($update) {
            $msg_type = "success";
            // Simulate Email Sending by showing link directly
            $message = "Reset link generated! <br><a href='reset_password.php?token=$token' style='font-weight:bold; color:#0f5132;'>Click here to reset password</a>";
        } else {
            $msg_type = "danger";
            $message = "Something went wrong. Try again.";
        }
    } else {
        $msg_type = "danger";
        $message = "Email address not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SAPD</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        @import url("https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap");

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #a691ab;
        }

        .container {
            display: flex;
            width: 850px;
            height: 500px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            overflow: hidden;
        }

        /* --- LEFT SIDE --- */
        .left-panel {
            width: 40%;
            background: url('background.jpg') no-repeat center center/cover;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #fff;
        }

        .left-panel::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5); z-index: 1;
        }

        .logo-content { z-index: 2; text-align: center; }
        .logo-img { width: 100px; margin-bottom: 10px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); }
        .brand-title { font-family: "Bebas Neue", sans-serif; font-size: 3rem; letter-spacing: 2px; line-height: 1; margin: 0; }

        /* --- RIGHT SIDE --- */
        .right-panel {
            width: 60%;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .header-text { text-align: center; font-size: 1.8rem; font-weight: 600; color: #333; margin-bottom: 25px; }

        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; font-size: 0.85rem; font-weight: 500; color: #555; margin-bottom: 5px; }
        .input-group input {
            width: 100%; padding: 12px 15px; border: none; border-radius: 6px;
            background: #e8e8e8; outline: none; font-size: 0.9rem; transition: 0.3s;
        }
        .input-group input:focus { background: #f0f0f0; box-shadow: 0 0 0 2px #333; }

        .btn-submit {
            width: 100%; padding: 12px; margin-top: 15px;
            background: #000; color: #fff; border: none; border-radius: 25px;
            font-size: 1rem; font-weight: 600; cursor: pointer; transition: 0.3s;
        }
        .btn-submit:hover { background: #333; transform: scale(1.02); }

        .links { margin-top: 20px; text-align: center; font-size: 0.85rem; color: #666; }
        .links a { color: #000; font-weight: 600; text-decoration: none; margin-left: 4px; }
        .links a:hover { text-decoration: underline; }

        .alert { padding: 10px; margin-bottom: 15px; border-radius: 6px; font-size: 0.85rem; text-align: center; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .alert-success { background: #d1e7dd; color: #0f5132; }

        @media (max-width: 768px) {
            .container { flex-direction: column; width: 90%; height: auto; }
            .left-panel { width: 100%; height: 150px; }
            .right-panel { width: 100%; padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="logo-content">
                <img src="background.png" alt="Logo" class="logo-img">
                <h1 class="brand-title">SAPD SYSTEM</h1>
            </div>
        </div>

        <div class="right-panel">
            <h2 class="header-text">Account Recovery</h2>

            <?php if($message): ?>
                <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <form action="forgot_password.php" method="POST">
                <div class="input-group">
                    <label>Enter Registered Email</label>
                    <input type="email" name="email" placeholder="name@example.com" required>
                </div>

                <button type="submit" class="btn-submit">Search Account</button>

                <div class="links">
                    <p>Remembered your password? <a href="index.php">Log In</a></p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>