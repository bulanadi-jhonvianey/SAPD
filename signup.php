<?php
// 1. Start Session & Connect
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
    // Graceful error if missing (optional, but good for debugging)
    $error = "Error: PHPMailer folder is missing.";
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
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'sapdstaff@gmail.com';     // <--- UPDATE THIS
                $mail->Password = 'xsmgnyaodmvezuhk';        // <--- PASTE 16-CHAR APP PASSWORD
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                // Email Settings
                $mail->setFrom('no-reply@sapd.com', 'SAPD System');
                $mail->addAddress($email); // Send to user

                // Email Content
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your SAPD Account';
                $mail->Body = "
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - SAPD Portal</title>
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

            /* Align items side-by-side (Row) like Login */
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

        /* --- Right Side (Signup Card) --- */
        .login-card {
            background: #fff;
            width: 450px;
            /* Slightly wider for Signup form */
            padding: 40px 40px;
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
            margin-bottom: 15px;
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
            padding: 12px 15px;
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

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 38px;
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
            box-shadow: 0 4px 10px rgba(66, 133, 244, 0.3);
        }

        .btn-submit:hover {
            background: #3367d6;
        }

        /* --- Footer Links --- */
        .form-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 0.9rem;
            color: #666;
        }

        .form-footer a {
            color: #4285f4;
            text-decoration: none;
            font-weight: 600;
        }

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

        @media (max-width: 900px) {
            .main-wrapper {
                flex-direction: column;
                justify-content: center;
                text-align: center;
            }

            .brand-section {
                flex-direction: column;
                text-align: center;
                margin-bottom: 30px;
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
                <h2>Create Account</h2>
                <p>Register your details to access the portal</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" required>
                </div>

                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required>
                </div>

                <div class="input-group">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" id="signupPass" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePass()"></i>
                </div>

                <button type="submit" class="btn-submit">Sign Up</button>

                <div class="form-footer">
                    <p>Already have an account? <a href="index.php">Log In</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePass() {
            var x = document.getElementById("signupPass");
            var icon = document.querySelector(".toggle-password");
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