<?php
// --- 1. SETUP & CONFIGURATION ---
session_start();

// Security Check
if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

// Database Credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sapd_db"; 

// Create Connection
try {
    $conn = new mysqli($servername, $username, $password);
    $conn->select_db($dbname);
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// --- 2. HANDLE ACTIONS ---
$msg = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $action = $_POST['action'];

        if ($action === 'approve') {
            $conn->query("UPDATE users SET status='active' WHERE id = $user_id");
            $msg = "User approved successfully.";
            $msg_type = "success";
        } elseif ($action === 'reject') {
            $conn->query("DELETE FROM users WHERE id = $user_id");
            $msg = "User rejected.";
            $msg_type = "warning";
        }
    }
}

// --- 3. FETCH PENDING USERS ---
$pending_users = [];
$result = $conn->query("SELECT * FROM users WHERE status = 'pending' ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pending_users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Approval</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- COPYING EXACT CSS FROM STUDENT PERMIT --- */
        @import url("https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700;900&display=swap");

        /* --- THEME VARIABLES --- */
        :root {
            --bg-body: #0a1128;
            --panel-bg: #13203c;
            --input-bg: #1f2f4e;
            --text-main: #ffffff;
            --accent: #007bff;
            --border: #2c3e50;
            --card-w: 350px;
            --card-h: 240px;
        }

        /* Light Mode Override */
        body.light-mode {
            --bg-body: #f4f6f9;
            --panel-bg: #ffffff;
            --input-bg: #f8f9fa;
            --text-main: #212529;
            --border: #dee2e6;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Segoe UI', sans-serif;
            transition: background-color 0.3s, color 0.3s;
            padding-bottom: 50px;
        }

        /* --- HEADER & NAVBAR (EXACT REPLICA) --- */
        .navbar { 
            background: var(--panel-bg); 
            border-bottom: 1px solid var(--border); 
            padding: 15px 20px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }

        /* --- BUTTON STYLES (EXACT REPLICA) --- */
        .btn {
            border-radius: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 20px;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(0,0,0,0.2);
            filter: brightness(110%);
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }

        /* BTN VARIANTS */
        .btn-secondary { background: linear-gradient(135deg, #858796 0%, #60616f 100%); color: white; }
        .btn-success { background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); color: white; }
        .btn-danger { background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%); color: white; }
        .btn-primary { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; }

        /* THEME TOGGLE BUTTON */
        .btn-theme { 
            background: var(--input-bg); 
            border: 1px solid var(--border); 
            color: var(--text-main); 
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-theme:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        /* --- PERMIT CARD DESIGN (EXACT DIMENSIONS & POSITIONS) --- */
        .permit-card-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
        }

        .permit-card {
            width: var(--card-w);
            height: var(--card-h);
            background-image: url('background_employee.png'); 
            background-size: 100% 100%;
            position: relative;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
            background-color: white; 
            color: black;
            overflow: hidden;
            flex-shrink: 0;
        }

        /* Logos */
        .logo-header { position: absolute; object-fit: contain; z-index: 20; }
        .logo-left { left: 20px; top: 10px; width: 55px; height: 55px; } 
        .logo-right { right: 20px; top: 10px; width: 55px; height: 55px; }

        /* Photo */
        .photo-img {
            position: absolute;
            top: 51%; left: 6px; transform: translateY(-50%);
            width: 85px; height: 85px;
            object-fit: cover; border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 5; background: #ccc;
        }

        /* Text Elements (Using Student Permit Default Coords) */
        .text-name { 
            font-weight: 900; text-transform: uppercase; line-height: 1; display: block; color: #000; 
            position: absolute; width: 100%; text-align: center; white-space: nowrap;
            font-size: 12px; left: 11px; top: 110px;
        }
        .text-dept { 
            font-weight: 700; text-transform: uppercase; display: block; color: #333; 
            position: absolute; width: 100%; text-align: center; white-space: nowrap;
            font-size: 11px; left: 0px; top: 129px;
        }
        .plate-info {
            position: absolute; font-weight: 800; color: #000; text-transform: uppercase;
            z-index: 15; letter-spacing: 0.5px; text-align: left; 
            font-size: 11px; left: 6px; top: 180px;
        }
        .valid-until-info {
            position: absolute; font-weight: 600; color: #ff6600; text-transform: uppercase;
            z-index: 15; letter-spacing: 0.5px; text-align: left; 
            font-size: 9px; left: 8px; top: 197px;
        }
        .stu-label {
            position: absolute; text-align: left; z-index: 25;
            font-weight: 900; color: #000000; text-transform: uppercase; line-height: 1;
            left: 6px; top: 165px;
        }
        .qr-area { position: absolute; text-align: center; bottom: 15px; right: 5px; }
        .qr-img { border: 1px solid #ddd; background: white; width: 60px; height: 60px; }
        .control-no { 
            position: absolute; width: 100%; text-align: center; 
            font-weight: 900; color: #cc0000; 
            font-size: 20px; right: 0px; top: -25px;
        }
        .sy-text { 
            font-weight: 800; color: #007bff; display: block; margin-top: 2px;
            line-height: 1; position: absolute; width: 100%; white-space: nowrap;
            font-size: 11px; right: 0px; top: 58px;
        }

        /* ACTION BUTTONS */
        .action-buttons { width: 350px; display: flex; gap: 10px; margin-top: 10px; }
        .btn-action { flex: 1; }
        .empty-state { text-align: center; padding: 60px; background: var(--panel-bg); border-radius: 15px; border: 1px solid var(--border); margin-top: 20px; color: var(--text-main); }
    </style>
</head>
<body>

    <div class="navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-secondary fw-bold"><i class="fa fa-arrow-left me-2"></i> Back</a>
            <h4 class="m-0 fw-bold" style="color: var(--text-main);">Admin Approval</h4>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <div class="fw-bold me-3 d-none d-md-block" style="color: var(--text-main);">
                <i class="fas fa-user-circle me-2"></i> <?php echo $_SESSION['name']; ?>
            </div>
            <button class="btn btn-theme rounded-circle" onclick="toggleTheme()" id="themeBtn">
                <i class="fa fa-moon"></i>
            </button>
        </div>
    </div>

    <div class="container">
        
        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm" role="alert">
                <strong>System:</strong> <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold" style="color: var(--text-main);"><i class="fas fa-user-clock me-2 text-warning"></i> PENDING REQUESTS</h5>
            <span class="badge bg-primary fs-6"><?php echo count($pending_users); ?> Pending</span>
        </div>

        <div class="row justify-content-center">
            <?php if (empty($pending_users)): ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-check-circle fa-4x mb-3 text-success"></i>
                        <h4>All caught up!</h4>
                        <p class="opacity-75">No pending user requests.</p>
                    </div>
                </div>
            <?php else: ?>

                <?php foreach ($pending_users as $user): 
                    $qr_data = "User:" . $user['username'] . "|ID:" . $user['id'];
                    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($qr_data);
                    $req_date = date("M d, Y", strtotime($user['created_at'] ?? 'now'));
                ?>
                <div class="col-auto permit-card-wrapper">
                    
                    <div class="permit-card">
                        <img src="HCC.png" class="logo-header logo-left">
                        <img src="background.png" class="logo-header logo-right">

                        <div class="stu-label">
                            <span style="font-size: 14px; color: yellow;">U</span><span style="font-size: 10px;">SER REQUEST</span>
                        </div>

                        <img src="https://placehold.co/100x100/e0e0e0/888888?text=USER" class="photo-img">

                        <span class="text-name"><?php echo strtoupper($user['name']); ?></span>
                        <span class="text-dept"><?php echo strtoupper($user['role']); ?> (PENDING)</span>

                        <div class="plate-info">
                            USERNAME: <span style="color: #007bff;"><?php echo $user['username']; ?></span>
                        </div>

                        <div class="valid-until-info">
                            REQ DATE: <span><?php echo $req_date; ?></span>
                        </div>

                        <div class="qr-area">
                            <div class="control-no"><?php echo $user['id']; ?></div>
                            <img src="<?php echo $qr_url; ?>" class="qr-img">
                            <span class="sy-text">PENDING</span>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <form method="POST" class="flex-grow-1">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-success btn-action w-100">
                                <i class="fas fa-check me-2"></i> Approve
                            </button>
                        </form>
                        
                        <form method="POST" class="flex-grow-1">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-danger btn-action w-100" onclick="return confirm('Reject this user?');">
                                <i class="fas fa-trash me-2"></i> Reject
                            </button>
                        </form>
                    </div>

                </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            const isLight = document.body.classList.contains('light-mode');
            document.getElementById('themeBtn').innerHTML = isLight ? '<i class="fa fa-sun"></i>' : '<i class="fa fa-moon"></i>';
            
            const themeValue = isLight ? 'light' : 'dark';
            localStorage.setItem('appTheme', themeValue);
            document.cookie = "theme=" + themeValue + "; path=/; max-age=31536000";
        }
        
        // Check saved theme
        const savedTheme = localStorage.getItem('appTheme') || 'light';
        if (savedTheme === 'light') {
            document.body.classList.add('light-mode');
            document.getElementById('themeBtn').innerHTML = '<i class="fa fa-sun"></i>';
        } else {
            document.body.classList.remove('light-mode');
            document.getElementById('themeBtn').innerHTML = '<i class="fa fa-moon"></i>';
        }
    </script>
</body>
</html>