<?php
ob_start(); // Prevents header errors
session_start();
include "db_conn.php";

// 1. Security Check
if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

// 2. Handle Actions
$msg = "";
$msg_type = "";

if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    if ($_GET['action'] == 'approve') {
        $conn->query("UPDATE users SET status='active' WHERE id=$id");
        header("Location: admin_approval.php?status=approved");
        exit();
    } elseif ($_GET['action'] == 'reject') {
        $conn->query("DELETE FROM users WHERE id=$id");
        header("Location: admin_approval.php?status=rejected");
        exit();
    }
}

// Check for status messages
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'approved') {
        $msg = "User successfully approved!";
        $msg_type = "success";
    } elseif ($_GET['status'] == 'rejected') {
        $msg = "User rejected.";
        $msg_type = "warning";
    }
}

$pending = $conn->query("SELECT * FROM users WHERE status='pending'");
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Admin Approval - SAPD</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap");

        /* --- THEME VARIABLES (Same as Dashboard) --- */
        :root {
            --bg-body: #f4f7fe;
            --bg-card: #ffffff;
            --text-main: #2b3674;
            --text-muted: #a3aed0;
            --border-color: #e0e5f2;
        }
        
        [data-bs-theme="dark"] {
            --bg-body: #0b1437;
            --bg-card: #111c44;
            --text-main: #ffffff;
            --text-muted: #8f9bba;
            --border-color: #1b254b;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Poppins', sans-serif;
            transition: 0.3s;
            padding: 30px;
        }

        /* Card Styling */
        .approval-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 25px;
        }

        /* Table Styling */
        .table { --bs-table-bg: transparent; color: var(--text-main); vertical-align: middle; }
        .table thead th { border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600; }
        .table tbody td { border-bottom: 1px solid var(--border-color); padding: 15px 10px; }

        /* Buttons */
        .btn-theme { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); }
        .btn-theme:hover { background: var(--border-color); }
    </style>

    <script>
        const savedTheme = localStorage.getItem('appTheme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold m-0">User Approvals</h2>
            
            <div class="d-flex gap-3">
                <button class="btn btn-theme rounded-circle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                
                <a href="dashboard.php" class="btn btn-secondary rounded-pill px-3 d-flex align-items-center">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="approval-card">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($pending->num_rows > 0): ?>
                            <?php while($row = $pending->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td class="text-center"><span class="badge bg-warning text-dark rounded-pill">Pending</span></td>
                                <td class="text-end">
                                    <a href="?action=approve&id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm me-1 rounded-pill px-3">
                                        <i class="fas fa-check me-1"></i> Approve
                                    </a>
                                    <a href="?action=reject&id=<?php echo $row['id']; ?>" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('Are you sure you want to reject this user?');">
                                        <i class="fas fa-times me-1"></i> Reject
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-2x mb-3 d-block"></i>
                                    No pending approvals found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const toggleBtn = document.getElementById('themeToggle');
        const icon = toggleBtn.querySelector('i');
        const html = document.documentElement;

        // Function to set icon based on theme
        function updateIcon(theme) {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Initialize icon on load
        updateIcon(localStorage.getItem('appTheme') || 'light');

        // Click Handler
        toggleBtn.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('appTheme', newTheme);
            updateIcon(newTheme);
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>