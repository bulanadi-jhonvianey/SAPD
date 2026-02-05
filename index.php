<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "db_conn.php";

if (isset($_SESSION['id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = mysqli_real_escape_string($conn, $_POST['email_or_user']);
    $pass = $_POST['password'];

    if (empty($user) || empty($pass)) {
        $error = "Please fill in all fields.";
    } else {
        $sql = "SELECT * FROM users WHERE username='$user' OR email='$user' LIMIT 1";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);

            if (password_verify($pass, $row['password'])) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SAPD Portal</title>
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
            /* Background set to BG.jpg */
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

            /* UPDATED: Align items side-by-side (Row) */
            display: flex;
            flex-direction: row;
            align-items: center;
            /* Center vertically */
            justify-content: flex-start;
        }

        .logo-img {
            width: 100px;
            height: auto;
            /* Margin right instead of bottom to space it from text */
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

        /* --- Right Side (Login Card) --- */
        .login-card {
            background: #fff;
            width: 420px;
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

        .input-group input::placeholder {
            color: #aaa;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
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
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
        }

        .forgot-pass {
            color: #4285f4;
            text-decoration: none;
            font-weight: 500;
        }

        .signup-link {
            color: #666;
        }

        .signup-link a {
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
                /* Stack them back on mobile */
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
                <h1>SAPD <span>PORTAL</span></h1>
                <p>Safety and Protection Division Office</p>
            </div>
        </div>

        <div class="login-card">
            <div class="login-header">
                <h2>Sign In</h2>
                <p>Verify Identity to continue to SAPD Office</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <input type="text" name="email_or_user" placeholder="Email or Username" required>
                </div>

                <div class="input-group">
                    <input type="password" name="password" id="loginPass" placeholder="Password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePass()"></i>
                </div>

                <button type="submit" class="btn-submit">Sign In</button>

                <div class="form-footer">
                    <a href="forgot_password.php" class="forgot-pass">Forgot Password?</a>
                    <p class="signup-link">No account? <a href="signup.php">Sign Up</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePass() {
            var x = document.getElementById("loginPass");
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