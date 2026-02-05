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
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- THEME DETECTION ---
$theme_mode = $_COOKIE['theme'] ?? 'light';

// --- ACTION HANDLER: APPROVE / REJECT ---
$msg = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $action = $_POST['action'];

        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE users SET status='active' WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $msg = "User account has been approved and activated.";
                $msg_type = "success";
            }
            $stmt->close();
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $msg = "Registration request has been rejected.";
                $msg_type = "warning";
            }
            $stmt->close();
        }
    }
}

// --- FETCH PENDING USERS ---
$sql = "SELECT * FROM users WHERE status = 'pending' ORDER BY id DESC";
$result = $conn->query($sql);
$pending_count = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="<?php echo htmlspecialchars($theme_mode); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Approvals - SAPD</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        @import url("https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap");
        @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");

        :root {
            --bg-body: #f4f7fe;
            --bg-card: #ffffff;
            --text-main: #2b3674;
            --text-muted: #a3aed0;
            --border-color: #e0e5f2;
            --sidebar-bg: #ffffff;
            --input-bg: #f8f9fa;
            --navbar-bg: #ffffff;
            --primary-color: #4318ff;
            --sidebar-width: 260px;
            --navbar-height: 70px;
        }

        [data-bs-theme="dark"] {
            --bg-body: #0a1128;
            --bg-card: #13203c;
            --text-main: #ffffff;
            --text-muted: #8f9bba;
            --border-color: #2c3e50;
            --sidebar-bg: #13203c;
            --input-bg: #1f2f4e;
            --navbar-bg: #13203c;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
            transition: background-color 0.3s, color 0.3s;
        }

        /* --- LAYOUT --- */
        .navbar-custom {
            height: var(--navbar-height);
            background: var(--navbar-bg) !important;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 0 20px;
            transition: background-color 0.3s;
        }

        .navbar-brand {
            font-family: "Bebas Neue", sans-serif;
            font-size: 1.8rem;
            letter-spacing: 1px;
            color: var(--text-main) !important;
        }

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 900;
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            padding-top: var(--navbar-height);
            overflow-y: auto;
            transition: 0.3s;
        }

        .sidebar-content {
            padding: 20px 15px;
        }

        .sidebar .nav-link {
            color: var(--text-muted);
            font-weight: 500;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: var(--bg-body);
            color: var(--primary-color);
            font-weight: 600;
        }

        .sidebar-heading {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
            padding: 20px 20px 10px;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px 30px;
            padding-top: calc(var(--navbar-height) + 30px);
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
            transition: 0.3s;
        }

        /* --- TABLE & CARD STYLES --- */
        .card-custom {
            background: var(--bg-card);
            border-radius: 20px;
            border: none;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .table-custom th {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .table-custom td {
            background: var(--bg-card);
            padding: 15px;
            vertical-align: middle;
            color: var(--text-main);
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .table-custom tr td:first-child {
            border-left: 1px solid var(--border-color);
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
        }

        .table-custom tr td:last-child {
            border-right: 1px solid var(--border-color);
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }

       .avatar-initial {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);

            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
            margin-right: 15px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(246, 194, 62, 0.1);
            color: #f6c23e;
        }

     .btn-icon {
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;

        }

        .btn-approve {
            background: rgba(28, 200, 138, 0.1);
            color: #1cc88a;
        }

        .btn-approve:hover {
            background: #1cc88a;
            color: white;
        }

        .btn-reject {
            background: rgba(231, 74, 59, 0.1);
            color: #e74a3b;
        }

        .btn-reject:hover {
            background: #e74a3b;
            color: white;
        }

        .btn-theme-nav {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 991px) {
            .sidebar {
                left: -100%;
                z-index: 1100;
            }

            .sidebar.show {
                left: 0;
                box-shadow: 5px 0 15px rgba(0, 0, 0, 0.2);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-custom">
        <div class="d-flex justify-content-between align-items-center w-100 px-3">
            <div class="d-flex align-items-center">
                <button class="btn text-secondary d-lg-none me-3" id="sidebarToggle"><i
                        class="fas fa-bars fa-lg"></i></button>
                <a class="navbar-brand d-flex align-items-center" href="#">
                    <img src="background.png" alt="Logo" width="35" height="35" onerror="this.style.display='none'"
                        class="me-2"> SAPD SYSTEM
                </a>
            </div>

            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-theme-nav rounded-circle" id="themeToggle"><i class="fas fa-moon"></i></button>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle fw-bold" href="#" role="button" data-bs-toggle="dropdown"
                        style="color: var(--text-main);">
                        <i class="fas fa-user-circle fa-lg me-2"></i> <?php echo $_SESSION['name']; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item text-danger" href="logout.php">Log Out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-th-large me-3"></i>
                        Dashboard</a></li>

                <h6 class="sidebar-heading">Admin</h6>
                <li class="nav-item">
                    <a class="nav-link active" href="admin_approval.php">
                        <i class="fas fa-user-check me-3"></i> Approvals
                        <?php if ($pending_count > 0): ?>
                            <span class="badge rounded-pill bg-danger ms-auto"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item"><a class="nav-link" href="active_users.php"><i class="fas fa-users me-3"></i>
                        Active Users</a></li>

                <h6 class="sidebar-heading">Forms Management</h6>
                <li class="nav-item"><a class="nav-link" href="violator_report.php"><i
                            class="fas fa-file-contract me-3"></i> Violator Report</a></li>
                <li class="nav-item"><a class="nav-link" href="guidance_referral.php"><i
                            class="fas fa-hands-helping me-3"></i> Guidance Referral</a></li>
                <li class="nav-item"><a class="nav-link" href="incident_report.php"><i
                            class="fas fa-exclamation-triangle me-3"></i> Incident Report</a></li>
                <li class="nav-item"><a class="nav-link" href="vaping_incident.php"><i
                            class="fas fa-smoking-ban me-3"></i> Vaping Incident</a></li>
                <li class="nav-item"><a class="nav-link" href="view_details.php?view=parking"><i
                            class="fas fa-car-crash me-3"></i> Parking Form</a></li>
                <li class="nav-item"><a class="nav-link" href="cctv_review_form.php"><i class="fas fa-video me-3"></i>
                        CCTV Review</a></li>
                <li class="nav-item"><a class="nav-link" href="facilities_and_inspection.php"><i
                            class="fas fa-tools me-3"></i> Facilities Insp.</a></li>

                <h6 class="sidebar-heading">Other Permits</h6>
                <li class="nav-item"><a class="nav-link" href="employee_permit.php"><i class="fas fa-id-badge me-3"></i>
                        Employee Permit</a></li>
                <li class="nav-item"><a class="nav-link" href="student_permit.php"><i
                            class="fas fa-user-graduate me-3"></i> Student License</a></li>
                <li class="nav-item"><a class="nav-link" href="non_permit.php"><i class="fas fa-address-card me-3"></i>
                        Non-Pro License</a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Pending Approvals</h2>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show mb-4" role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold m-0 text-warning">Waiting for Action</h5>
                <input type="text" id="userSearch" class="form-control"
                    style="width: 250px; background: var(--input-bg); border-color: var(--border-color); color: var(--text-main);"
                    placeholder="Filter names...">
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>User Details</th>

                            <th>Role Requested</th>
                            <th>Date Requested</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()):

                                $name_parts = explode(" ", $row['name']);
                                $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initial"><?php echo $initials; ?></div>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($row['email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="text-capitalize"><?php echo htmlspecialchars($row['role']); ?></td>
                                    <td><?php echo date("M d, Y", strtotime($row['created_at'] ?? 'now')); ?></td>
                                    <td><span class="status-badge status-pending">Pending</span></td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <form method="POST" onsubmit="return confirm('Approve this account?');">
                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn-icon btn-approve" title="Approve"><i
                                                        class="fas fa-check"></i></button>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('Reject and delete this request?');">
                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn-icon btn-reject" title="Reject"><i
                                                        class="fas fa-times"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="fas fa-check-circle fa-3x text-muted mb-3 opacity-50"></i>
                                    <p class="text-muted fw-bold">No pending registration requests found.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        const sidebar = document.getElementById('sidebar');
        const toggleBtnMobile = document.getElementById('sidebarToggle');
        if (toggleBtnMobile) toggleBtnMobile.addEventListener('click', () => sidebar.classList.toggle('show'));
        
        const toggleBtn = document.getElementById('themeToggle');

        const icon = toggleBtn.querySelector('i');
        const html = document.documentElement;

        function updateIcon(theme) { icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon'; }
        updateIcon(html.getAttribute('data-bs-theme'));

        toggleBtn.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', newTheme);
            updateIcon(newTheme);
            document.cookie = "theme=" + newTheme + "; path=/; max-age=31536000";
            localStorage.setItem('appTheme', newTheme);
        });

        document.addEventListener('DOMContentLoaded', function () {
            const storedTheme = localStorage.getItem('appTheme');
            if (storedTheme && storedTheme !== html.getAttribute('data-bs-theme')) {
                html.setAttribute('data-bs-theme', storedTheme);
                updateIcon(storedTheme);
            }
        });

        document.getElementById('userSearch').addEventListener('keyup', function () {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#userTableBody tr');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>

</html>