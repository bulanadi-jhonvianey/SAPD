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

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Create DB if not exists (Safety check)
    $conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
    $conn->select_db($dbname);

} catch (Exception $e) {
 die("Database Error: " . $e->getMessage());
}

// --- THEME DETECTION ---
$theme_mode = $_COOKIE['theme'] ?? 'light';

// 2. Helper Function to Get Counts Safely
function get_cnt($conn, $sql)
{
    if (!$conn)
        return 0;
    try {
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            return $row['c'] ?? 0;
        }
    } catch (Exception $e) {
        return 0; // Return 0 if table doesn't exist yet
    }
    return 0;
}

// 3. Initialize Stats Array
$stats = [
    'users' => 0,
    'total_permits' => 0,
    'pending' => 0,
    'violator' => 0,
    'guidance' => 0,
    'incident' => 0,
    'vaping' => 0,
    'parking_form' => 0,
    'emp_permit' => 0,
    'student_permit' => 0,
    'non_pro_permit' => 0,
    'cctv_req' => 0,
    'facilities' => 0
];

if ($conn) {
    // --- CORE STATS ---
    $stats['users'] = get_cnt($conn, "SELECT COUNT(*) as c FROM users WHERE role='user' AND status='active'");
    $stats['pending'] = get_cnt($conn, "SELECT COUNT(*) as c FROM users WHERE status='pending'");

    // --- PERMIT COUNTS ---
    $stats['emp_permit'] = get_cnt($conn, "SELECT COUNT(*) as c FROM permits");
    $stats['student_permit'] = get_cnt($conn, "SELECT COUNT(*) as c FROM student_permits");
    $stats['non_pro_permit'] = get_cnt($conn, "SELECT COUNT(*) as c FROM non_pro_permits");

    $stats['total_permits'] = $stats['emp_permit'] + $stats['student_permit'] + $stats['non_pro_permit'];

    // --- FORM COUNTS ---
    $stats['violator'] = get_cnt($conn, "SELECT COUNT(*) as c FROM violator_reports");
    $stats['guidance'] = get_cnt($conn, "SELECT COUNT(*) as c FROM guidance_referrals");
    $stats['facilities'] = get_cnt($conn, "SELECT COUNT(*) as c FROM facility_inspections");
    $stats['incident'] = get_cnt($conn, "SELECT COUNT(*) as c FROM incident_reports");
    $stats['vaping'] = get_cnt($conn, "SELECT COUNT(*) as c FROM vaping_reports");

    $stats['parking_form'] = get_cnt($conn, "SELECT COUNT(*) as c FROM form_submissions WHERE form_type='parking'");

    $stats['cctv_req'] = get_cnt($conn, "SELECT COUNT(*) as c FROM cctv_requests");

    // --- FETCH DATA FOR MODALS ---
    // A. Active Users (NEW)
    $recent_active_users = [];
    try {
        $res = $conn->query("SELECT * FROM users WHERE role='user' AND status='active' ORDER BY id DESC LIMIT 10");
        if ($res)
            while ($row = $res->fetch_assoc())
                $recent_active_users[] = $row;
    } catch (Exception $e) {
    }

    // B. Pending Requests (NEW)
    $recent_pending_requests = [];
    try {
        $res = $conn->query("SELECT * FROM users WHERE status='pending' ORDER BY id DESC LIMIT 10");
        if ($res)
            while ($row = $res->fetch_assoc())
                $recent_pending_requests[] = $row;
    } catch (Exception $e) {
    }

    // 1. Employee Permits
    $recent_emp_permits = [];
    try {
        $res = $conn->query("SELECT * FROM permits ORDER BY name ASC LIMIT 10");
        if ($res)
            while ($row = $res->fetch_assoc())
                $recent_emp_permits[] = $row;
    } catch (Exception $e) {
    }

    // 2. Student Permits
    $recent_student_permits = [];
    try {
        $res = $conn->query("SELECT * FROM student_permits ORDER BY name ASC LIMIT 10");
        if ($res)
            while ($row = $res->fetch_assoc())
                $recent_student_permits[] = $row;
    } catch (Exception $e) {
    }

    // 3. Non-Pro Permits
    $recent_non_pro_permits = [];
    try {
        $res = $conn->query("SELECT * FROM non_pro_permits ORDER BY name ASC LIMIT 10");
        if ($res)
            while ($row = $res->fetch_assoc())
                $recent_non_pro_permits[] = $row;
    } catch (Exception $e) {
    }

    // 4. CCTV Requests
    $recent_cctv_requests = [];
    try {
        $res = $conn->query("SELECT * FROM cctv_requests ORDER BY requestor_name ASC LIMIT 10");
        if ($res)
            while ($row = $res->fetch_assoc())
                $recent_cctv_requests[] = $row;
    } catch (Exception $e) {
    }

    // 5. Incident Reports
    $recent_incidents = [];
    try {
        $res = $conn->query("SELECT * FROM incident_reports ORDER BY case_title ASC LIMIT 10");
        if ($res)
            while ($row = $res->fetch_assoc())
                $recent_incidents[] = $row;
    } catch (Exception $e) {
    }

    // 6. Vaping Reports
    $recent_vaping = [];
    try {
        $res = $conn->query("SELECT * FROM vaping_reports ORDER BY case_title ASC LIMIT 10");
        if ($res)
            while ($row = $res->fetch_assoc())
                $recent_vaping[] = $row;
    } catch (Exception $e) {
    }

    // 7. Facilities Inspections
    $recent_facilities = [];
    try {
        $res = $conn->query("SELECT * FROM facility_inspections ORDER BY title ASC LIMIT 10");
        if ($res)
            while ($row = $res->fetch_assoc())
                $recent_facilities[] = $row;
    } catch (Exception $e) {
    }

    // 8. Violator Reports
    $recent_violators = [];
    try {
        $res = $conn->query("SELECT * FROM violator_reports ORDER BY violator_name ASC LIMIT 10");
        if ($res)
            while ($row = $res->fetch_assoc())
                $recent_violators[] = $row;
    } catch (Exception $e) {
    }

    // 9. Guidance Referrals
    $recent_guidance = [];
    try {
        $res = $conn->query("SELECT * FROM guidance_referrals ORDER BY id DESC LIMIT 10");
        if ($res)
            while ($row = $res->fetch_assoc())
                $recent_guidance[] = $row;
    } catch (Exception $e) {
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?php echo htmlspecialchars($theme_mode); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SAPD</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>

    <style>
        @import url("https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap");
        @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");

        /* --- THEME VARIABLES --- */
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

        /* --- BUTTON STYLES --- */
        .btn {
            border-radius: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 20px;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(0, 0, 0, 0.2);
            filter: brightness(110%);
        }

        /* Bootstrap Overrides */
        .btn-primary {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #858796 0%, #60616f 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
            color: white;
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

        .sidebar .nav-link:hover {
            background: var(--bg-body);
            color: var(--primary-color);
        }

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

        /* --- UI ELEMENTS --- */
        a.card-link {
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
            transition: transform 0.3s;
        }

        a.card-link:hover {
            transform: translateY(-5px);
        }

        .cursor-pointer {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .cursor-pointer:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        /* Top Big Stats */
        .solid-stat-card {
            border-radius: 15px;
            padding: 25px;
            color: #fff;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .solid-stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-text-wrapper {
            display: flex;
            flex-direction: column;
            z-index: 2;
        }

        .stat-icon-large {
            font-size: 3.5rem;
            opacity: 0.4;
            transform: rotate(-10deg);
            margin-right: -10px;
        }

        .bg-primary-blue {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        }

        .bg-success-green {
            background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
        }

        .bg-warning-orange {
            background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
            color: #fff !important;
        }

        /* Mini Scrollable Cards */
        .scrolling-wrapper {
            overflow-x: auto;
            flex-wrap: nowrap;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 10px;
        }

        .scrolling-wrapper::-webkit-scrollbar {
            height: 6px;
        }

        .scrolling-wrapper::-webkit-scrollbar-track {
            background: transparent;
        }

        .scrolling-wrapper::-webkit-scrollbar-thumb {
            background-color: var(--border-color);
            border-radius: 10px;
        }

        .mini-card-col {
            flex: 0 0 auto;
            width: 180px;
        }

        .mini-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 20px 15px;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
            position: relative;
        }

        .mini-card:hover {
            border-color: var(--primary-color);
        }

        .mini-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 5px;
        }

        .mini-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mini-icon {
            font-size: 1.4rem;
            margin-bottom: 8px;
            opacity: 0.85;
            transition: transform 0.3s;
        }

        .mini-card:hover .mini-icon {
            transform: scale(1.1);
        }

        /* Permit Stat Cards */
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 25px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-card:hover {
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }

        .stat-label-modern {
            font-size: 0.85rem;
            color: var(--text-muted);
            display: block;
            margin-bottom: 5px;
        }

        .stat-value-modern {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .stat-icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

     .icon-blue {
            background: #e7f1ff;
            color: #33c1ff;
        }

        .icon-green {
            background: #e6fffa;
            color: #05cd99;
        }

        .icon-orange {
            background: #fff7e6;
            color: #ffb547;
        }

        .icon-info {
            background: #e6f7ff;
            color: #0dcaf0;
        }

        .icon-purple {
            background: #f3e5f5;
            color: #9c27b0;
        }

        .icon-red {
            background: #ffe6e6;
            color: #dc3545;
        }

        [data-bs-theme="dark"] .icon-blue {
            background: rgba(51, 193, 255, 0.15);
        }

        [data-bs-theme="dark"] .icon-green {
            background: rgba(5, 205, 153, 0.15);
        }

        [data-bs-theme="dark"] .icon-orange {
            background: rgba(255, 181, 71, 0.15);
        }

        [data-bs-theme="dark"] .icon-info {
            background: rgba(13, 202, 240, 0.15);
        }

        [data-bs-theme="dark"] .icon-purple {
            background: rgba(156, 39, 176, 0.15);
        }

        [data-bs-theme="dark"] .icon-red {
            background: rgba(220, 53, 69, 0.15);
        }

        .border-l-primary {
            border-left: 5px solid var(--primary-color) !important;
        }

        .border-l-success {
            border-left: 5px solid #198754 !important;
        }

        .border-l-warning {
            border-left: 5px solid #ffc107 !important;
        }

        /* Calendar */
        .calendar-card {
            background: var(--bg-card);
            border-radius: 20px;
            border: none;
            padding: 25px;
            color: var(--text-main);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .fc-button {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%) !important;
            border: none !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Modern List Styles */
        .modal-content {
            background-color: var(--bg-card);
            color: var(--text-main);
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-body {
            background: var(--bg-body);
            padding: 20px;
        }

        .modern-list-item {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
        }
        .modern-list-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            border-color: var(--primary-color);
        }

        .list-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            margin-right: 15px;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            color: white;
        }

        .list-avatar.success {
            background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
        }

        .list-avatar.warning {
            background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
            color: #fff;
        }

        .list-avatar.info {
            background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
        }

        .list-avatar.danger {
            background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);
            color: #fff;
        }

        .list-avatar.purple {
            background: linear-gradient(135deg, #ab47bc 0%, #8e24aa 100%);
            color: #fff;
        }

        .list-avatar.primary {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        }

        .list-info {
            flex-grow: 1;
        }

        .list-title {
            font-weight: 700;
            color: var(--text-main);
            font-size: 0.95rem;
            margin-bottom: 2px;
        }

        .list-subtitle {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modern-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-soft-primary {
            background: rgba(78, 115, 223, 0.1);
            color: #4e73df;
        }

        .badge-soft-success {
            background: rgba(28, 200, 138, 0.1);
            color: #1cc88a;
        }

        .badge-soft-warning {
            background: rgba(246, 194, 62, 0.1);
            color: #f6c23e;
        }

        .badge-soft-info {
            background: rgba(54, 185, 204, 0.1);
            color: #36b9cc;
        }

        .badge-soft-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .badge-soft-purple {
            background: rgba(171, 71, 188, 0.1);
            color: #ab47bc;
        }

        .modal-search-container {
            background-color: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            padding: 15px 25px;
        }

        .form-control-themed {
            background-color: var(--input-bg);
            border-color: var(--border-color);
            color: var(--text-main);
        }

        .input-group-text-themed {
            background-color: var(--input-bg);
            border-color: var(--border-color);
            color: var(--text-muted);
            border-right: none;
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

    <audio id="notifSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3"
        preload="auto"></audio>
    <div class="toast-container">
        <div id="liveToast" class="toast" role="alert">
            <div class="toast-body" id="toastMessage"></div>
        </div>
    </div>

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
            <form class="d-none d-md-block" id="searchForm" style="width: 300px;">
                <div class="input-group">
                    <input class="form-control" type="search" id="searchInput" placeholder="Search forms, permits..."
                        style="border-radius: 0;">
                    <button type="submit" class="btn btn-primary" style="border-radius: 0;"><i
                            class="fas fa-search"></i></button>
                </div>
            </form>
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
                <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i
                            class="fas fa-th-large me-3"></i> Dashboard</a></li>
                <h6 class="sidebar-heading">Admin</h6>

                <li class="nav-item">
                    <a class="nav-link" href="admin_approval.php"><i class="fas fa-user-check me-3"></i> Approvals
                        <?php if ($stats['pending'] > 0): ?><span
                                class="badge bg-danger rounded-pill ms-auto"><?php echo $stats['pending']; ?></span><?php endif; ?>
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
        <h2 class="mb-4 fw-bold">Dashboard Overview</h2>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="solid-stat-card bg-primary-blue cursor-pointer" data-bs-toggle="modal"
                    data-bs-target="#activeUsersModal">
                    <div class="stat-text-wrapper">
                        <span class="stat-value"><?php echo $stats['users']; ?></span>
                        <span class="stat-label">Active Users</span>
                    </div>
                    <i class="fas fa-users stat-icon-large"></i>
                </div>
            </div>
            <div class="col-md-4">
                <div class="solid-stat-card bg-success-green">
                    <div class="stat-text-wrapper">
                        <span class="stat-value"><?php echo $stats['total_permits']; ?></span>
                        <span class="stat-label">Total Permits</span>
                    </div>
                    <i class="fas fa-clipboard-check stat-icon-large"></i>
                </div>
            </div>
            <div class="col-md-4">
                <div class="solid-stat-card bg-warning-orange cursor-pointer" data-bs-toggle="modal"
                    data-bs-target="#pendingRequestsModal">
                    <div class="stat-text-wrapper">
                        <span class="stat-value"><?php echo $stats['pending']; ?></span>
                        <span class="stat-label">Pending Requests</span>
                    </div>
                    <i class="fas fa-hourglass-half stat-icon-large"></i>
                </div>
            </div>
        </div>

        <h5 class="fw-bold mb-3 text-secondary">Submitted Forms</h5>
        <div class="row flex-nowrap scrolling-wrapper g-3 mb-4">
            <div class="col mini-card-col">
                <div class="mini-card cursor-pointer" data-bs-toggle="modal" data-bs-target="#violatorModal">
                    <div class="mini-icon text-primary"><i class="fas fa-file-contract"></i></div>
                    <div class="mini-value"><?php echo $stats['violator']; ?></div>
                    <div class="mini-label">Violator Report</div>
                </div>
            </div>
            <div class="col mini-card-col">
                <div class="mini-card cursor-pointer" data-bs-toggle="modal" data-bs-target="#guidanceModal">
                    <div class="mini-icon text-info"><i class="fas fa-hands-helping"></i></div>
                    <div class="mini-value"><?php echo $stats['guidance']; ?></div>
                    <div class="mini-label">Guidance Referral</div>
                </div>
            </div>
            <div class="col mini-card-col">
                <div class="mini-card cursor-pointer" data-bs-toggle="modal" data-bs-target="#incidentModal">
                    <div class="mini-icon text-warning"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="mini-value"><?php echo $stats['incident']; ?></div>
                    <div class="mini-label">Incident</div>
                </div>
            </div>
            <div class="col mini-card-col">
                <div class="mini-card cursor-pointer" data-bs-toggle="modal" data-bs-target="#vapingModal">
                    <div class="mini-icon text-danger"><i class="fas fa-smoking-ban"></i></div>
                    <div class="mini-value"><?php echo $stats['vaping']; ?></div>
                    <div class="mini-label">Vaping</div>
                </div>
            </div>
            <div class="col mini-card-col">
                <a href="view_details.php?view=parking" class="card-link">
                    <div class="mini-card">
                        <div class="mini-icon text-success"><i class="fas fa-car"></i></div>
                        <div class="mini-value"><?php echo $stats['parking_form']; ?></div>
                        <div class="mini-label">Parking</div>
                    </div>
                </a>
            </div>
            <div class="col mini-card-col">
                <div class="mini-card cursor-pointer" data-bs-toggle="modal" data-bs-target="#cctvRequestsModal">
                    <div class="mini-icon text-secondary"><i class="fas fa-video"></i></div>
                    <div class="mini-value"><?php echo $stats['cctv_req']; ?></div>
                    <div class="mini-label">CCTV Req</div>
                </div>
            </div>
            <div class="col mini-card-col">
                <div class="mini-card cursor-pointer" data-bs-toggle="modal" data-bs-target="#facilitiesModal">
                    <div class="mini-icon text-dark"><i class="fas fa-tools"></i></div>
                    <div class="mini-value"><?php echo $stats['facilities']; ?></div>
                    <div class="mini-label">Facilities & Equip.</div>
                </div>
            </div>
        </div>

        <h5 class="fw-bold mb-3 text-secondary">Permit Breakdown</h5>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="stat-card border-l-primary cursor-pointer" data-bs-toggle="modal"
                    data-bs-target="#employeePermitsModal">
                    <div class="stat-content"><span class="stat-label-modern">Employee Permits</span><span
                            class="stat-value-modern"><?php echo $stats['emp_permit']; ?></span><small
                            class="text-primary d-block mt-1">Search & View</small></div>
                    <div class="stat-icon-wrapper icon-blue"><i class="fas fa-id-badge"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card border-l-success cursor-pointer" data-bs-toggle="modal"
                    data-bs-target="#studentPermitsModal">
                    <div class="stat-content"><span class="stat-label-modern">Student License</span><span
                            class="stat-value-modern"><?php echo $stats['student_permit']; ?></span><small
                            class="text-success d-block mt-1">Search & View</small></div>
                    <div class="stat-icon-wrapper icon-green"><i class="fas fa-user-graduate"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card border-l-warning cursor-pointer" data-bs-toggle="modal"
                    data-bs-target="#nonProPermitsModal">
                    <div class="stat-content"><span class="stat-label-modern">Non-Pro License</span><span
                            class="stat-value-modern"><?php echo $stats['non_pro_permit']; ?></span><small
                            class="text-warning d-block mt-1">Search & View</small></div>
                    <div class="stat-icon-wrapper icon-orange"><i class="fas fa-address-card"></i></div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="calendar-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">Event Schedule</h5>
                        <div class="bg-primary text-white px-4 py-2 rounded-pill shadow-sm"
                            style="background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);">
                            <i class="far fa-calendar-alt me-2"></i><span id="currentDateDisplay"
                                class="fw-bold">Loading...</span>
                        </div>
                    </div>
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="activeUsersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-primary"><i class="fas fa-users me-2"></i>Recent Active Users
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group">
                        <span class="input-group-text input-group-text-themed"><i class="fas fa-search"></i></span>
                        <input type="text" id="activeUserSearch" class="form-control form-control-themed border-start-0"
                            placeholder="Filter by Name, Email...">
                    </div>
                </div>
                <div class="modal-body" id="activeUsersContainer">
                    <?php if (!empty($recent_active_users)): ?>
                        <?php foreach ($recent_active_users as $u): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar primary"><i class="fas fa-user"></i></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($u['name']); ?></div>
                                        <div class="list-subtitle"><span><i
                                                    class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($u['email']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="modern-badge badge-soft-success">Active</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5"><i class="fas fa-users-slash fa-3x text-muted mb-3 opacity-50"></i>
                            <p class="text-muted fw-bold">No active users found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <a href="active_users.php" class="btn btn-primary rounded-pill px-4">Full System</a>
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pendingRequestsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-warning"><i class="fas fa-hourglass-half me-2"></i>Pending
                        Requests</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group">
                        <span class="input-group-text input-group-text-themed"><i class="fas fa-search"></i></span>
                        <input type="text" id="pendingSearch" class="form-control form-control-themed border-start-0"
                            placeholder="Filter by Name...">
                    </div>
                </div>
                <div class="modal-body" id="pendingRequestsContainer">
                    <?php if (!empty($recent_pending_requests)): ?>
                        <?php foreach ($recent_pending_requests as $p): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar warning"><i class="fas fa-user-clock"></i></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <div class="list-subtitle"><span><i
                                                    class="fas fa-id-badge me-1"></i><?php echo htmlspecialchars($p['role']); ?>
                                                Request</span></div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="modern-badge badge-soft-warning">Pending</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5"><i class="fas fa-check-circle fa-3x text-muted mb-3 opacity-50"></i>
                            <p class="text-muted fw-bold">No pending requests.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <a href="admin_approval.php" class="btn btn-warning text-white rounded-pill px-4">Full System</a>
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="violatorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-primary"><i class="fas fa-file-contract me-2"></i>Recent
                        Violator Reports</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group">
                        <span class="input-group-text input-group-text-themed"><i class="fas fa-search"></i></span>
                        <input type="text" id="violatorSearch" class="form-control form-control-themed border-start-0"
                            placeholder="Filter by Name, Violation...">
                    </div>
                </div>
                <div class="modal-body" id="violatorContainer">
                    <?php if (!empty($recent_violators)): ?>
                        <?php foreach ($recent_violators as $v): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar primary"><i class="fas fa-user-times"></i></div>
                                    <div class="list-info">
                                        <div class="list-title">
                                            <?php echo htmlspecialchars($v['violator_name'] ?? 'Unknown'); ?>
                                        </div>
                                        <div class="list-subtitle"><span><i
                                                    class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($v['violation_type'] ?? 'Violation'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small mb-1"><?php echo $v['report_date'] ?? date('Y-m-d'); ?></div>
                                    <span
                                        class="modern-badge badge-soft-primary"><?php echo htmlspecialchars($v['status'] ?? 'Reported'); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5"><i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                            <p class="text-muted fw-bold">No recent records found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer"><a href="violator_report.php" class="btn btn-primary rounded-pill px-4">Full
                        System</a><button type="button" class="btn btn-secondary rounded-pill"
                        data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="guidanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-info"><i class="fas fa-hands-helping me-2"></i>Recent Guidance
                        Referrals</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group">
                        <span class="input-group-text input-group-text-themed"><i class="fas fa-search"></i></span>
                        <input type="text" id="guidanceSearch" class="form-control form-control-themed border-start-0"
                            placeholder="Filter by Student, Reason...">
                    </div>
                </div>
                <div class="modal-body" id="guidanceContainer">
                    <?php if (!empty($recent_guidance)): ?>
                        <?php foreach ($recent_guidance as $g): ?>
                            <?php
                            // Logic to get Reason from JSON
                            $reasons_arr = json_decode($g['reasons'] ?? '[]', true);
                            $display_reason = !empty($reasons_arr) ? $reasons_arr[0] : ($g['other_reason'] ?: 'Referral');
                            if (is_array($reasons_arr) && count($reasons_arr) > 1) {
                                $display_reason .= " (+more)";
                            }
                            ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar info"><i class="fas fa-user-friends"></i></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($g['student_name'] ?? 'Unknown'); ?>
                                        </div>
                                        <div class="list-subtitle"><span><i
                                                    class="fas fa-comment-dots me-1"></i><?php echo htmlspecialchars($display_reason); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small mb-1"><?php echo $g['referral_date'] ?? date('Y-m-d'); ?></div>
                                    <span
                                        class="modern-badge badge-soft-info"><?php echo htmlspecialchars($g['status'] ?? 'Pending'); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5"><i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                            <p class="text-muted fw-bold">No recent records found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <a href="guidance_referral.php" class="btn btn-info text-white rounded-pill px-4">Full System</a>
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="incidentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Recent
                        Incident Reports</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group"><span class="input-group-text input-group-text-themed"><i
                                class="fas fa-search"></i></span><input type="text" id="incidentSearch"
                            class="form-control form-control-themed border-start-0"
                            placeholder="Filter by Case, Location..."></div>
                </div>
                <div class="modal-body" id="incidentContainer">
                    <?php if (!empty($recent_incidents)): ?>
                        <?php foreach ($recent_incidents as $inc): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar warning"><i class="fas fa-exclamation-triangle"></i></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($inc['case_title']); ?></div>
                                        <div class="list-subtitle"><span><i
                                                    class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($inc['location']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small mb-1"><?php echo $inc['incident_date']; ?></div><span
                                        class="modern-badge badge-soft-warning"><?php echo htmlspecialchars($inc['status'] ?? 'Recorded'); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5"><i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                            <p class="text-muted fw-bold">No incidents found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer"><a href="incident_report.php"
                        class="btn btn-warning text-white rounded-pill px-4">Full System</a><button type="button"
                        class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vapingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-danger"><i class="fas fa-smoking-ban me-2"></i>Recent Vaping
                        Incidents</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group"><span class="input-group-text input-group-text-themed"><i
                                class="fas fa-search"></i></span><input type="text" id="vapingSearch"
                            class="form-control form-control-themed border-start-0"
                            placeholder="Filter by Case, Location..."></div>
                </div>
                <div class="modal-body" id="vapingContainer">
                    <?php if (!empty($recent_vaping)): ?>
                        <?php foreach ($recent_vaping as $vape): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar danger"><i class="fas fa-smoking-ban"></i></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($vape['case_title']); ?></div>
                                        <div class="list-subtitle"><span><i
                                                    class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($vape['location']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small mb-1"><?php echo $vape['incident_date']; ?></div><span
                                        class="modern-badge badge-soft-danger"><?php echo htmlspecialchars($vape['status'] ?? 'Recorded'); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5"><i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                            <p class="text-muted fw-bold">No vaping incidents found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer"><a href="vaping_incident.php"
                        class="btn btn-danger text-white rounded-pill px-4">Full System</a><button type="button"
                        class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cctvRequestsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-info"><i class="fas fa-video me-2"></i>Recent CCTV Requests</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group"><span class="input-group-text input-group-text-themed"><i
                                class="fas fa-search"></i></span><input type="text" id="cctvSearch"
                            class="form-control form-control-themed border-start-0"
                            placeholder="Filter by Name, Location..."></div>
                </div>
                <div class="modal-body" id="cctvRequestsContainer">
                    <?php if (!empty($recent_cctv_requests)): ?>
                        <?php foreach ($recent_cctv_requests as $req): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar info"><i class="fas fa-video"></i></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($req['requestor_name'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="list-subtitle"><span><i
                                                    class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($req['location'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small mb-1">
                                        <?php echo htmlspecialchars($req['incident_date'] ?? ''); ?>
                                    </div><span class="modern-badge badge-soft-info">Pending</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5"><i class="fas fa-video-slash fa-3x text-muted mb-3 opacity-50"></i>
                            <p class="text-muted fw-bold">No recent CCTV records found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer"><a href="cctv_review_form.php"
                        class="btn btn-info text-white rounded-pill px-4">Full System</a><button type="button"
                        class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="facilitiesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" style="color: #9c27b0;"><i class="fas fa-tools me-2"></i>Recent
                        Facilities Inspections</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group"><span class="input-group-text input-group-text-themed"><i
                                class="fas fa-search"></i></span><input type="text" id="facilitiesSearch"
                            class="form-control form-control-themed border-start-0"
                            placeholder="Filter by Item, Location..."></div>
                </div>
                <div class="modal-body" id="facilitiesContainer">
                    <?php if (!empty($recent_facilities)): ?>
                        <?php foreach ($recent_facilities as $fac): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar purple"><i class="fas fa-tools"></i></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($fac['title'] ?? 'Inspection'); ?>
                                        </div>
                                        <div class="list-subtitle"><span><i
                                                    class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($fac['location'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small mb-1"><?php echo $fac['inspection_date'] ?? date('Y-m-d'); ?>
                                    </div><span
                                        class="modern-badge badge-soft-purple"><?php echo htmlspecialchars($fac['status'] ?? 'Checked'); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5"><i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                            <p class="text-muted fw-bold">No inspection records found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer"><a href="facilities_and_inspection.php"
                        class="btn text-white rounded-pill px-4" style="background-color: #9c27b0;">Full
                        System</a><button type="button" class="btn btn-secondary rounded-pill"
                        data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="employeePermitsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-primary"><i class="fas fa-id-badge me-2"></i>Employee Permits
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group">
                        <span class="input-group-text input-group-text-themed"><i class="fas fa-search"></i></span>
                        <input type="text" id="empPermitSearch" class="form-control form-control-themed border-start-0"
                            placeholder="Filter by Name...">
                    </div>
                </div>
                <div class="modal-body" id="employeePermitsContainer">
                    <?php if (!empty($recent_emp_permits)): ?>
                        <?php foreach ($recent_emp_permits as $p): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar primary"><i class="fas fa-id-badge"></i></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <div class="list-subtitle"><span><i class="fas fa-id-card me-1"></i>Employee</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small mb-1"><?php echo $p['created_at'] ?? date('Y-m-d'); ?></div>
                                    <span class="modern-badge badge-soft-primary">Active</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5"><i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                            <p class="text-muted fw-bold">No employee permits found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer"><a href="employee_permit.php" class="btn btn-primary rounded-pill px-4">Full
                        System</a><button type="button" class="btn btn-secondary rounded-pill"
                        data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="studentPermitsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-success"><i class="fas fa-user-graduate me-2"></i>Student
                        Permits</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group">
                        <span class="input-group-text input-group-text-themed"><i class="fas fa-search"></i></span>
                        <input type="text" id="stuPermitSearch" class="form-control form-control-themed border-start-0"
                            placeholder="Filter by Name...">
                    </div>
                </div>
                <div class="modal-body" id="studentPermitsContainer">
                    <?php if (!empty($recent_student_permits)): ?>
                        <?php foreach ($recent_student_permits as $p): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar success"><i class="fas fa-user-graduate"></i></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <div class="list-subtitle"><span><i
                                                    class="fas fa-graduation-cap me-1"></i>Student</span></div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small mb-1"><?php echo $p['created_at'] ?? date('Y-m-d'); ?></div>
                                    <span class="modern-badge badge-soft-success">Active</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5"><i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                            <p class="text-muted fw-bold">No student permits found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer"><a href="student_permit.php" class="btn btn-success rounded-pill px-4">Full
                        System</a><button type="button" class="btn btn-secondary rounded-pill"
                        data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="nonProPermitsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-warning"><i class="fas fa-address-card me-2"></i>Non-Pro Permits
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group">
                        <span class="input-group-text input-group-text-themed"><i class="fas fa-search"></i></span>
                        <input type="text" id="nonProPermitSearch"
                            class="form-control form-control-themed border-start-0" placeholder="Filter by Name...">
                    </div>
                </div>
                <div class="modal-body" id="nonProPermitsContainer">
                    <?php if (!empty($recent_non_pro_permits)): ?>
                        <?php foreach ($recent_non_pro_permits as $p): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar warning"><i class="fas fa-address-card"></i></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <div class="list-subtitle"><span><i class="fas fa-car me-1"></i>Non-Professional</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small mb-1"><?php echo $p['created_at'] ?? date('Y-m-d'); ?></div>
                                    <span class="modern-badge badge-soft-warning">Active</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5"><i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                            <p class="text-muted fw-bold">No non-pro permits found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer"><a href="non_permit.php"
                        class="btn btn-warning rounded-pill px-4 text-white">Full System</a><button type="button"
                        class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Event</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="eventForm"><input type="hidden" id="eventId"><input type="text" class="form-control mb-3"
                            id="eventTitle" placeholder="Title"><input type="datetime-local" class="form-control mb-3"
                            id="eventStart"><input type="datetime-local" class="form-control mb-3" id="eventEnd"><input
                            type="color" class="form-control" id="eventColor"></form>
                </div>
                <div class="modal-footer"><button id="deleteEventBtn"
                        class="btn btn-danger me-auto">Delete</button><button id="saveEventBtn"
                        class="btn btn-primary">Save</button></div>
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

        // Set initial icon based on what PHP rendered
        function updateIcon(theme) { icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon'; }
        updateIcon(html.getAttribute('data-bs-theme'));
        // Handle Toggle Click
        toggleBtn.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            // 1. Update HTML immediately
            html.setAttribute('data-bs-theme', newTheme);

            // 2. Update Icon
            updateIcon(newTheme);

            // 3. Save to Cookie (This is key for PHP on next reload) - Path is root /
            document.cookie = "theme=" + newTheme + "; path=/; max-age=31536000";

            // 4. Save to LocalStorage (for other pages that might only check LS)
            localStorage.setItem('appTheme', newTheme);
        });

        // --- SYNC SCRIPT: Ensure Theme Consistency on Load ---
        // This fixes the issue where navigating from a page using LocalStorage would result in mismatch
        document.addEventListener('DOMContentLoaded', function () {
            const serverTheme = html.getAttribute('data-bs-theme');
            const storedTheme = localStorage.getItem('appTheme');

            // If localStorage exists and is different from server render (cookie), sync them
            if (storedTheme && storedTheme !== serverTheme) {
                // Apply the stored theme immediately
                html.setAttribute('data-bs-theme', storedTheme);
                updateIcon(storedTheme);
                // Update the cookie so PHP gets it right next time
                document.cookie = "theme=" + storedTheme + "; path=/; max-age=31536000";
            }
        });

        // Search redirection logic
        document.getElementById('searchForm').addEventListener('submit', function (e) {
            e.preventDefault();
            let val = document.getElementById('searchInput').value.toLowerCase().trim();
            if (val.includes('violator')) window.location.href = 'violator_report.php';
            else if (val.includes('guidance')) window.location.href = 'guidance_referral.php';
            else if (val.includes('incident')) window.location.href = 'incident_report.php';
            else if (val.includes('vaping')) window.location.href = 'vaping_incident.php';
            else if (val.includes('parking')) window.location.href = 'view_details.php?view=parking';
            else if (val.includes('cctv')) window.location.href = 'cctv_review_form.php';
            else if (val.includes('facilities')) window.location.href = 'facilities_and_inspection.php';
            else window.location.href = 'dashboard.php';
        });
        function attachSearch(inputId, containerId) {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('keyup', function () {
                    let filter = this.value.toLowerCase();
                    let items = document.querySelectorAll('#' + containerId + ' .modern-list-item');
                    items.forEach(item => {
                        let text = item.innerText.toLowerCase();
                        item.style.display = text.includes(filter) ? 'flex' : 'none';
                    });
                });
            }
        }

        attachSearch('empPermitSearch', 'employeePermitsContainer');
        attachSearch('stuPermitSearch', 'studentPermitsContainer');
        attachSearch('nonProPermitSearch', 'nonProPermitsContainer');
        attachSearch('cctvSearch', 'cctvRequestsContainer');
        attachSearch('incidentSearch', 'incidentContainer');
        attachSearch('vapingSearch', 'vapingContainer');
        attachSearch('facilitiesSearch', 'facilitiesContainer');
        attachSearch('violatorSearch', 'violatorContainer');
        attachSearch('guidanceSearch', 'guidanceContainer');
        // New Search attachments
        attachSearch('activeUserSearch', 'activeUsersContainer');
        attachSearch('pendingSearch', 'pendingRequestsContainer');
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('currentDateDisplay').textContent = new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

            var calendarEl = document.getElementById('calendar');
            var modal = new bootstrap.Modal(document.getElementById('eventModal'));

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth', height: 500, headerToolbar: { left: 'prev,next', center: 'title', right: 'dayGridMonth,timeGridWeek' },
                events: 'event_handler.php?action=fetch',
                selectable: true,
                select: function (info) { document.getElementById('eventForm').reset(); document.getElementById('eventStart').value = info.startStr + "T09:00"; document.getElementById('eventEnd').value = info.startStr + "T10:00"; modal.show(); }
            });

            calendar.render();
        });
    </script>
</body>
</html>