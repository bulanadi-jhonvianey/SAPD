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
            // Simulate Email Sending by showing link directly (For demo purposes)
            $message = "Reset link generated! <br><a href='reset_password.php?token=$token' style='font-weight:bold; text-decoration:underline;'>Click here to reset password</a>";
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
    <title>Forgot Password - SAPD Portal</title>
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
            box-shadow: 0 4px 10px rgba(66, 133, 244, 0.3);
        }

        .btn-submit:hover {
            background: #3367d6;
        }

        /* --- Footer Links --- */
        .form-footer {
            margin-top: 25px;
            text-align: center;
            font-size: 0.9rem;
            color: #666;
        }

        .form-footer a {
            color: #4285f4;
            text-decoration: none;
            font-weight: 600;
        }

        /* --- Alerts --- */
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 0.9rem;
            text-align: center;
            word-wrap: break-word;
            /* Prevent long links from breaking layout */
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

        .alert-success a {
            color: #1b5e20;
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
            <div class="login-header">
                <h2>Account Recovery</h2>
                <p>Enter your email to search for your account</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <form action="forgot_password.php" method="POST">
                <div class="input-group">
                    <label>Enter Registered Email</label>
                    <input type="email" name="email" placeholder="name@example.com" required>
                </div>

                <button type="submit" class="btn-submit">Search Account</button>

                <div class="form-footer">
                    <p>Remembered your password? <a href="index.php">Log In</a></p>
                </div>
            </form>
        </div>
    </div>
</body>

</html>