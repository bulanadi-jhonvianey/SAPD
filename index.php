<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include "db_conn.php";

if (isset($_SESSION['id'])) { header("Location: dashboard.php"); exit(); }

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = mysqli_real_escape_string($conn, $_POST['email_or_user']);
    $pass = $_POST['password']; // Do not escape password before verify

    if (empty($user) || empty($pass)) {
        $error = "Please fill in all fields.";
    } else {
        // Allow login by Username OR Email
        $sql = "SELECT * FROM users WHERE username='$user' OR email='$user' LIMIT 1";
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            
            // VERIFY PASSWORD
            if (password_verify($pass, $row['password'])) {
                
                // CHECK STATUS
                if ($row['status'] === 'active') {
                    $_SESSION['id'] = $row['id'];
                    $_SESSION['name'] = $row['name'];
                    $_SESSION['role'] = $row['role'];
                    
                    header("Location: dashboard.php");
                    exit();
                } elseif ($row['status'] === 'unverified') {
                    header("Location: verify_email.php?email=" . $row['email']);
                    exit();
                } else {
                    $error = "Account is pending Admin Approval.";
                }
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "Account not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - SAPD</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        .btn-submit { width: 100%; padding: 12px; margin-top: 10px; background: #000; color: #fff; border: none; border-radius: 30px; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { background: #333; }
        .links { margin-top: 15px; text-align: center; font-size: 0.85rem; color: #666; }
        .links a { color: #000; font-weight: 600; text-decoration: none; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 6px; font-size: 0.85rem; text-align: center; }
        .alert-danger { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <img src="background.png" alt="Logo" class="logo-img" onerror="this.style.display='none'">
            <h1 class="brand-title">SAPD SYSTEM</h1>
        </div>
        <div class="right-panel">
            <h2 class="header-text">Welcome Back</h2>
            <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <div class="input-group"><label>Username</label><input type="text" name="email_or_user" required></div>
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" id="loginPass" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePass()"></i>
                </div>
                <button type="submit" class="btn-submit">Log In</button>
                <div class="links">
                    <p style="margin-top: 10px;">Don't have an account? <a href="signup.php">Sign Up</a></p>
                </div>
            </form>
        </div>
    </div>
    <script>
        function togglePass() {
            var x = document.getElementById("loginPass");
            x.type = (x.type === "password") ? "text" : "password";
        }
    </script>
</body>
</html>