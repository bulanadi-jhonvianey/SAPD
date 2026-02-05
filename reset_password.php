<?php
session_start();
include "db_conn.php";

$token = $_GET['token'] ?? "";
$error = "";
$success = "";
$valid_token = true;

if (empty($token)) {
    $valid_token = false;
    $error = "Invalid request. Token missing.";
} else {
    $token_hash = hash("sha256", $token);

    // Find user with this token and ensure it hasn't expired
    $sql = "SELECT * FROM users WHERE reset_token_hash = '$token_hash' AND reset_token_expires_at > NOW()";
    $result = $conn->query($sql);
    $user = $result->fetch_assoc();

    if (!$user) {
        $valid_token = false;
        $error = "This password reset link has expired or is invalid.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];

    if ($pass1 !== $pass2) {
        $error = "Passwords do not match.";
    } elseif (strlen($pass1) < 4) {
        $error = "Password must be at least 4 characters.";
    } else {
        // IMPORTANT: Hash the password so it works with the Login page's password_verify()
        $new_hash = password_hash($pass1, PASSWORD_DEFAULT);

        $sql = "UPDATE users SET password = '$new_hash', reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = {$user['id']}";
        if ($conn->query($sql)) {
            $success = "Password updated successfully!";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - SAPD Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            /* Full screen background image BG.jpg */
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('BG.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .main-wrapper {
            display: flex;
            width: 100%;
            max-width: 1200px;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        /* --- Left Side (Branding) --- */
        .brand-section {
            flex: 1;
            color: white;
            padding: 20px;
            min-width: 300px;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
        }

        .logo-img {
            width: 100px;
            height: auto;
            margin-right: 25px;
            margin-bottom: 0;
        }

        .brand-text h1 {
            font-size: 3.8rem;
            font-weight: 800;
            line-height: 1;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .brand-text h1 span {
            color: #ffc107;
            /* Gold Color */
        }

        .brand-text p {
            font-size: 1.1rem;
            font-weight: 400;
            letter-spacing: 1px;
            opacity: 0.9;
        }

        /* --- Right Side (Card) --- */
        .login-card {
            background: #fff;
            width: 450px;
            padding: 50px 40px;
            border-radius: 6px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .login-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #222;
            margin-bottom: 5px;
        }

        .login-header p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 25px;
        }

        /* --- Form Styles --- */
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }

        .input-group input {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid #e1e1e1;
            border-radius: 5px;
            font-size: 0.95rem;
            background: #fff;
            color: #333;
            outline: none;
            transition: 0.3s;
        }

        .input-group input:focus {
            border-color: #4285f4;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
        }

        /* Eye Icon styling */
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 42px;
            /* Adjusted for label height */
            cursor: pointer;
            color: #999;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #4285f4;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: background 0.3s;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: #3367d6;
        }

        .btn-secondary {
            background: #666;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: #444;
        }

        /* --- Alerts --- */
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 0.9rem;
            text-align: center;
        }

        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        /* --- Responsive Design --- */
        @media (max-width: 900px) {
            .main-wrapper {
                flex-direction: column;
                justify-content: center;
                text-align: center;
            }

            .brand-section {
                flex-direction: column;
                text-align: center;
                margin-bottom: 40px;
            }

            .logo-img {
                margin-right: 0;
                margin-bottom: 15px;
            }

            .login-card {
                width: 100%;
                max-width: 400px;
            }
        }
    </style>
</head>

<body>

    <div class="main-wrapper">
        <div class="brand-section">
            <img src="background.png" alt="SAPD Logo" class="logo-img" onerror="this.style.display='none'">
            <div class="brand-text">
                <h1>SAPD <span>Portal</span></h1>
                <p>Safety and Protection Division Office</p>
            </div>
        </div>

        <div class="login-card">

            <?php if (!$valid_token): ?>
                <div class="login-header">
                    <h2>Link Expired</h2>
                </div>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <a href="index.php"><button class="btn-submit btn-secondary">Back to Login</button></a>

            <?php elseif ($success): ?>
                <div class="login-header">
                    <h2>Success!</h2>
                </div>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
                <a href="index.php"><button class="btn-submit">Go to Login</button></a>

            <?php else: ?>
                <div class="login-header">
                    <h2>Reset Password</h2>
                    <p>Enter a new password for your account</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group">
                        <label>New Password</label>
                        <input type="password" name="pass1" id="pass1" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePass('pass1', this)"></i>
                    </div>

                    <div class="input-group">
                        <label>Confirm Password</label>
                        <input type="password" name="pass2" id="pass2" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePass('pass2', this)"></i>
                    </div>

                    <button type="submit" class="btn-submit">Change Password</button>
                </form>

            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePass(inputId, icon) {
            var x = document.getElementById(inputId);
            if (x.type === "password") {
                x.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                x.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>
</body>

</html>