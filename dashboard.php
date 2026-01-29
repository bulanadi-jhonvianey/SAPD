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

// 2. Helper Function to Get Counts Safely
function get_cnt($conn, $sql) {
    if (!$conn) return 0;
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

// Helper to get initials
function get_initials($name) {
    return strtoupper(substr($name, 0, 1));
}

// 3. Initialize Stats Array
$stats = [
    'users' => 0, 'total_permits' => 0, 'pending' => 0,
    'comm_letter' => 0, 'division' => 0, 'incident' => 0, 'vaping' => 0, 'parking_form' => 0,
    'emp_permit' => 0, 'student_permit' => 0, 'non_pro_permit' => 0, 'cctv_req' => 0
];

if ($conn) {
    // --- CORE STATS ---
    $stats['users'] = get_cnt($conn, "SELECT COUNT(*) as c FROM users WHERE role='user' AND status='active'");
    $stats['pending'] = get_cnt($conn, "SELECT COUNT(*) as c FROM users WHERE status='pending'");
    
    // --- PERMIT COUNTS ---
    $stats['emp_permit'] = get_cnt($conn, "SELECT COUNT(*) as c FROM permits");
    $stats['student_permit'] = get_cnt($conn, "SELECT COUNT(*) as c FROM student_permits");
    $stats['non_pro_permit'] = get_cnt($conn, "SELECT COUNT(*) as c FROM non_pro_permits");
    // Total Calculation
    $stats['total_permits'] = $stats['emp_permit'] + $stats['student_permit'] + $stats['non_pro_permit'];
    // --- FORM COUNTS ---
    $stats['comm_letter'] = get_cnt($conn, "SELECT COUNT(*) as c FROM form_submissions WHERE form_type='letter'");
    $stats['division'] = get_cnt($conn, "SELECT COUNT(*) as c FROM form_submissions WHERE form_type='division'");
    $stats['incident'] = get_cnt($conn, "SELECT COUNT(*) as c FROM form_submissions WHERE form_type='incident'");
    $stats['vaping'] = get_cnt($conn, "SELECT COUNT(*) as c FROM form_submissions WHERE form_type='vaping'");
    $stats['parking_form'] = get_cnt($conn, "SELECT COUNT(*) as c FROM form_submissions WHERE form_type='parking'");
    
    // --- CCTV DATA INTEGRATION ---
    $stats['cctv_req'] = get_cnt($conn, "SELECT COUNT(*) as c FROM cctv_requests");

    // --- FETCH DATA FOR MODALS ---
    
    // 1. Employee Permits
    $recent_emp_permits = [];
    try {
        $res = $conn->query("SELECT * FROM permits ORDER BY created_at DESC LIMIT 10");
        if ($res) while ($row = $res->fetch_assoc()) $recent_emp_permits[] = $row;
    } catch (Exception $e) {}

    // 2. Student Permits
    $recent_student_permits = [];
    try {
        $res = $conn->query("SELECT * FROM student_permits ORDER BY created_at DESC LIMIT 10");
        if ($res) while ($row = $res->fetch_assoc()) $recent_student_permits[] = $row;
    } catch (Exception $e) {}

    // 3. Non-Pro Permits
    $recent_non_pro_permits = [];
    try {
        $res = $conn->query("SELECT * FROM non_pro_permits ORDER BY created_at DESC LIMIT 10");
        if ($res) while ($row = $res->fetch_assoc()) $recent_non_pro_permits[] = $row;
    } catch (Exception $e) {}

    // 4. CCTV Requests
    $recent_cctv_requests = [];
    try {
        $res = $conn->query("SELECT * FROM cctv_requests ORDER BY created_at DESC LIMIT 10");
        if ($res) while ($row = $res->fetch_assoc()) $recent_cctv_requests[] = $row;
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
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
            border-radius: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
            padding: 10px 20px; border: none; transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 7px 14px rgba(0,0,0,0.2); filter: brightness(110%); }
        
        .btn-primary { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; }
        .btn-success { background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); color: white; }
        .btn-danger { background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%); color: white; }
        .btn-warning { background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%); color: white; }
        .btn-secondary { background: linear-gradient(135deg, #858796 0%, #60616f 100%); color: white; }
        .btn-info { background: linear-gradient(135deg, #36b9cc 0%, #258391 100%); color: white; }

        /* --- LAYOUT --- */
        .navbar-custom { 
            height: var(--navbar-height); 
            background: var(--navbar-bg) !important; 
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000; padding: 0 20px; 
            transition: background-color 0.3s;
        }
        .navbar-brand { 
            font-family: "Bebas Neue", sans-serif; 
            font-size: 1.8rem; letter-spacing: 1px; 
            color: var(--text-main) !important;
        }

        .sidebar { 
            width: var(--sidebar-width); height: 100vh; position: fixed; top: 0; left: 0; z-index: 900; 
            background-color: var(--sidebar-bg); border-right: 1px solid var(--border-color); 
            padding-top: var(--navbar-height); overflow-y: auto; transition: 0.3s; 
        }
        .sidebar-content { padding: 20px 15px; }
        .sidebar .nav-link { 
            color: var(--text-muted); font-weight: 500; padding: 12px 20px; border-radius: 10px; 
            margin-bottom: 5px; display: flex; align-items: center; transition: all 0.3s; 
        }
        .sidebar .nav-link:hover { background: var(--bg-body); color: var(--primary-color); }
        .sidebar .nav-link.active { background: var(--bg-body); color: var(--primary-color); font-weight: 600; }
        .sidebar-heading { font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); padding: 20px 20px 10px; }

        .main-content { margin-left: var(--sidebar-width); padding: 20px 30px; padding-top: calc(var(--navbar-height) + 30px); min-height: 100vh; width: calc(100% - var(--sidebar-width)); transition: 0.3s; }

        /* --- CARDS & UI ELEMENTS --- */
        a.card-link { text-decoration: none; color: inherit; display: block; height: 100%; transition: transform 0.3s; }
        a.card-link:hover { transform: translateY(-5px); }
        
        .cursor-pointer { cursor: pointer; transition: all 0.2s ease; }
        .cursor-pointer:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }

        /* MODIFIED: Top Stats Card with Icons */
        .solid-stat-card { 
            border-radius: 15px; 
            padding: 25px; 
            color: #fff; 
            display: flex; 
            flex-direction: row; /* Changed to row to side-by-side icon */
            align-items: center; 
            justify-content: space-between;
            height: 100%; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            overflow: hidden;
        }
        /* Wrapper for the text inside solid card */
        .stat-text-wrapper {
            display: flex;
            flex-direction: column;
            z-index: 2;
        }
        /* Style for the large faded icon */
        .stat-icon-large {
            font-size: 3.5rem;
            opacity: 0.4;
            transform: rotate(-10deg);
            margin-right: -10px;
        }

        .bg-primary-blue { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); }
        .bg-success-green { background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); }
        .bg-warning-orange { background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%); color: #fff !important; }

        .mini-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 15px; padding: 25px; text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.03); position: relative; }
        .mini-card:hover { border-color: var(--primary-color); } 
        .mini-value { font-size: 2rem; font-weight: 700; color: var(--text-main); margin-bottom: 5px; }
        .mini-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
        
        .mini-icon { font-size: 1.5rem; margin-bottom: 8px; opacity: 0.85; transition: transform 0.3s; }
        .mini-card:hover .mini-icon { transform: scale(1.1); }

        .stat-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 15px; padding: 25px; height: 100%; display: flex; align-items: center; justify-content: space-between; }
        .stat-card:hover { box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .stat-label-modern { font-size: 0.85rem; color: var(--text-muted); display: block; margin-bottom: 5px; }
        .stat-value-modern { font-size: 1.8rem; font-weight: 700; color: var(--text-main); }
        .stat-icon-wrapper { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        
        .icon-blue { background: #e7f1ff; color: #33c1ff; } .icon-green { background: #e6fffa; color: #05cd99; } .icon-orange { background: #fff7e6; color: #ffb547; } .icon-info { background: #e6f7ff; color: #0dcaf0; }
        [data-bs-theme="dark"] .icon-blue { background: rgba(51, 193, 255, 0.15); }
        [data-bs-theme="dark"] .icon-green { background: rgba(5, 205, 153, 0.15); }
        [data-bs-theme="dark"] .icon-orange { background: rgba(255, 181, 71, 0.15); }
        [data-bs-theme="dark"] .icon-info { background: rgba(13, 202, 240, 0.15); }

        .border-l-primary { border-left: 5px solid var(--primary-color) !important; }
        .border-l-success { border-left: 5px solid #198754 !important; }
        .border-l-warning { border-left: 5px solid #ffc107 !important; }
        .border-l-info { border-left: 5px solid #0dcaf0 !important; }

        /* --- UPDATED CALENDAR CARD STYLE --- */
        .calendar-card { 
            background: var(--bg-card); 
            border-radius: 20px; 
            border: none; 
            padding: 25px; 
            color: var(--text-main); 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .fc .fc-toolbar-title { font-size: 1.5rem; font-weight: 700; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; }
        .fc-button { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%) !important; border: none !important; border-radius: 8px !important; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-transform: capitalize; font-weight: 500; }
        .fc-theme-standard td, .fc-theme-standard th { border-color: var(--border-color); }
        .fc .fc-col-header-cell-cushion { color: var(--text-muted); text-transform: uppercase; font-size: 0.85rem; font-weight: 600; padding-bottom: 10px; }
        .fc .fc-daygrid-day-number { color: var(--text-main); font-weight: 500; text-decoration: none; padding: 8px 12px; }
        .fc .fc-day-today { background-color: rgba(67, 24, 255, 0.03) !important; }
        .fc .fc-day-today .fc-daygrid-day-number { background-color: var(--primary-color); color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; margin: 4px; box-shadow: 0 4px 10px rgba(67, 24, 255, 0.3); }
        .fc-event { border-radius: 6px; border: none; padding: 3px 8px; font-size: 0.85rem; font-weight: 500; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 2px; cursor: pointer; transition: transform 0.2s; }
        .fc-event:hover { transform: scale(1.02); }

        /* Main Search */
        .search-container { position: relative; width: 300px; }
        .search-input { border-radius: 20px; padding-left: 40px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main); height: 38px; }
        .search-input:focus { background: var(--input-bg); color: var(--text-main); border-color: var(--primary-color); outline: none; }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }

        /* Toast */
        .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }

        /* --- NEW MODERN MODAL LIST STYLES --- */
        .modal-content { background-color: var(--bg-card); color: var(--text-main); border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
        .modal-header { padding: 25px 30px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; }
        .modal-body { background: var(--bg-body); padding: 20px; }
        
        /* Modern List Item (Card-like Row) */
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            position: relative;
        }
        .modern-list-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            border-color: var(--primary-color);
            z-index: 2;
        }
        
        /* Avatar */
        .list-avatar {
            width: 45px; height: 45px; border-radius: 50%;
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1.1rem; margin-right: 15px; flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .list-avatar.success { background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); }
        .list-avatar.warning { background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%); color: #fff; }
        .list-avatar.info { background: linear-gradient(135deg, #36b9cc 0%, #258391 100%); }

        /* Text Styles */
        .list-info { flex-grow: 1; }
        .list-title { font-weight: 700; color: var(--text-main); font-size: 0.95rem; margin-bottom: 2px; }
        .list-subtitle { font-size: 0.8rem; color: var(--text-muted); font-weight: 500; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        
        /* Badges */
        .modern-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; }
        .badge-soft-primary { background: rgba(78, 115, 223, 0.1); color: #4e73df; }
        .badge-soft-success { background: rgba(28, 200, 138, 0.1); color: #1cc88a; }
        .badge-soft-warning { background: rgba(246, 194, 62, 0.1); color: #f6c23e; }
        .badge-soft-info { background: rgba(54, 185, 204, 0.1); color: #36b9cc; }
        .badge-soft-dark { background: rgba(133, 135, 150, 0.1); color: var(--text-muted); }

        .btn-close { filter: invert(var(--bs-theme-invert, 0)); }
        
        /* Custom Search Styling for Modal */
        .modal-search-container { background-color: var(--bg-card); border-bottom: 1px solid var(--border-color); padding: 15px 25px !important; }
        .form-control-themed { background-color: var(--input-bg); border-color: var(--border-color); color: var(--text-main); }
        .form-control-themed:focus { background-color: var(--input-bg); border-color: var(--primary-color); color: var(--text-main); box-shadow: none; }
        .input-group-text-themed { background-color: var(--input-bg); border-color: var(--border-color); color: var(--text-muted); border-right: none; }
        
        /* Theme Toggle Button */
        .btn-theme-nav { background: var(--input-bg); border: 1px solid var(--border-color); color: var(--text-main); width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center; }
        .btn-theme-nav:hover { background: var(--primary-color); color: white; border-color: var(--primary-color); }
        @media (max-width: 991px) { .sidebar { left: -100%; z-index: 1100; } .sidebar.show { left: 0; box-shadow: 5px 0 15px rgba(0,0,0,0.2); } .main-content { margin-left: 0; width: 100%; } }
    </style>

    <script>
        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
        }
        
        const cookieTheme = getCookie('theme');
        const localTheme = localStorage.getItem('appTheme');
        const savedTheme = cookieTheme || localTheme || 'light';
        
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
</head>
<body>

    <audio id="notifSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>
    <div class="toast-container">
        <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-primary text-white">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body text-dark" id="toastMessage">
                New Event Added!
            </div>
        </div>
    </div>

    <nav class="navbar navbar-custom">
        <div class="d-flex justify-content-between align-items-center w-100 px-3">
            <div class="d-flex align-items-center">
                <button class="btn text-secondary d-lg-none me-3" id="sidebarToggle"><i class="fas fa-bars fa-lg"></i></button>
                <a class="navbar-brand d-flex align-items-center" href="#">
                    <img src="background.png" alt="Logo" width="35" height="35" onerror="this.style.display='none'" class="me-2">
                    SAPD SYSTEM
                </a>
            </div>
            <form class="d-none d-md-block search-container" id="searchForm">
                <i class="fas fa-search search-icon"></i>
                <input class="form-control search-input" type="search" id="searchInput" placeholder="Search forms, permits...">
            </form>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-theme-nav rounded-circle" id="themeToggle"><i class="fas fa-moon"></i></button>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle fw-bold" href="#" role="button" data-bs-toggle="dropdown" style="color: var(--text-main);">
                        <i class="fas fa-user-circle fa-lg me-2"></i> <?php echo $_SESSION['name']; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item text-danger" href="logout.php" onclick="localStorage.removeItem('appTheme'); document.cookie = 'theme=; Max-Age=0; path=/;';">Log Out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="fas fa-th-large me-3"></i> Dashboard</a></li>
                <h6 class="sidebar-heading">Admin</h6>
                
                <li class="nav-item">
                    <a class="nav-link" href="admin_approval.php">
                        <i class="fas fa-user-check me-3"></i> Approvals
                        <?php if($stats['pending'] > 0): ?><span class="badge bg-danger rounded-pill ms-auto"><?php echo $stats['pending']; ?></span><?php endif; ?>
                    </a>
                </li>

                <h6 class="sidebar-heading">Forms Management</h6>
                <li class="nav-item"><a class="nav-link" href="view_details.php?view=letter"><i class="fas fa-envelope-open-text me-3"></i> Comm. Letter</a></li>
                <li class="nav-item"><a class="nav-link" href="view_details.php?view=division"><i class="fas fa-file-alt me-3"></i> Division Form</a></li>
                <li class="nav-item"><a class="nav-link" href="incident_report.php"><i class="fas fa-exclamation-triangle me-3"></i> Incident Report</a></li>
                <li class="nav-item"><a class="nav-link" href="view_details.php?view=vaping"><i class="fas fa-smoking-ban me-3"></i> Vaping Incident</a></li>
                <li class="nav-item"><a class="nav-link" href="view_details.php?view=parking"><i class="fas fa-car-crash me-3"></i> Parking Form</a></li>
                <li class="nav-item"><a class="nav-link" href="cctv_review_form.php"><i class="fas fa-video me-3"></i> CCTV Review Form</a></li>

                <h6 class="sidebar-heading">Other Permits</h6>

                <li class="nav-item"><a class="nav-link" href="employee_permit.php"><i class="fas fa-id-badge me-3"></i> Employee Permit</a></li>
                
                <li class="nav-item"><a class="nav-link" href="student_permit.php"><i class="fas fa-user-graduate me-3"></i> Student License</a></li>
                
                <li class="nav-item"><a class="nav-link" href="non_permit.php"><i class="fas fa-address-card me-3"></i> Non-Pro License</a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <h2 class="mb-4 fw-bold">Dashboard Overview</h2>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <a href="view_details.php?view=users" class="card-link">
                    <div class="solid-stat-card bg-primary-blue">
                        <div class="stat-text-wrapper">
                            <span class="stat-value"><?php echo $stats['users']; ?></span>
                            <span class="stat-label">Active Users</span>
                        </div>
                        <i class="fas fa-users stat-icon-large"></i>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <div class="solid-stat-card bg-success-green">
                    <div class="stat-text-wrapper">
                        <span class="stat-value"><?php echo $stats['total_permits']; ?></span>
                        <span class="stat-label">Total Permits Issued</span>
                    </div>
                    <i class="fas fa-clipboard-check stat-icon-large"></i>
                </div>
            </div>
            <div class="col-md-4">
                <a href="admin_approval.php" class="card-link">
                    <div class="solid-stat-card bg-warning-orange">
                        <div class="stat-text-wrapper">
                            <span class="stat-value"><?php echo $stats['pending']; ?></span>
                            <span class="stat-label">Pending Requests</span>
                        </div>
                        <i class="fas fa-hourglass-half stat-icon-large"></i>
                    </div>
                </a>
            </div>
        </div>

        <h5 class="fw-bold mb-3 text-secondary">Submitted Forms</h5>
        <div class="row row-cols-2 row-cols-lg-6 g-3 mb-4">
            <div class="col">
                <a href="view_details.php?view=letter" class="card-link">
                    <div class="mini-card">
                        <div class="mini-icon text-primary"><i class="fas fa-envelope-open-text"></i></div>
                        <div class="mini-value"><?php echo $stats['comm_letter']; ?></div>
                        <div class="mini-label">Comm. Letter</div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="view_details.php?view=division" class="card-link">
                    <div class="mini-card">
                        <div class="mini-icon text-info"><i class="fas fa-file-alt"></i></div>
                        <div class="mini-value"><?php echo $stats['division']; ?></div>
                        <div class="mini-label">Division</div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="view_details.php?view=incident" class="card-link">
                    <div class="mini-card">
                        <div class="mini-icon text-warning"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="mini-value"><?php echo $stats['incident']; ?></div>
                        <div class="mini-label">Incident</div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="view_details.php?view=vaping" class="card-link">
                    <div class="mini-card">
                        <div class="mini-icon text-danger"><i class="fas fa-smoking-ban"></i></div>
                        <div class="mini-value"><?php echo $stats['vaping']; ?></div>
                        <div class="mini-label">Vaping</div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="view_details.php?view=parking" class="card-link">
                    <div class="mini-card">
                        <div class="mini-icon text-success"><i class="fas fa-car"></i></div>
                        <div class="mini-value"><?php echo $stats['parking_form']; ?></div>
                        <div class="mini-label">Parking</div>
                    </div>
                </a>
            </div>
            <div class="col">
                <div class="mini-card cursor-pointer" data-bs-toggle="modal" data-bs-target="#cctvRequestsModal">
                    <div class="mini-icon text-secondary"><i class="fas fa-video"></i></div>
                    <div class="mini-value"><?php echo $stats['cctv_req']; ?></div>
                    <div class="mini-label">CCTV Req</div>
                </div>
            </div>
        </div>

        <h5 class="fw-bold mb-3 text-secondary">Permit Breakdown</h5>
        
        <div class="row g-3">
            <div class="col-md-4">
                <div class="stat-card border-l-primary cursor-pointer" data-bs-toggle="modal" data-bs-target="#employeePermitsModal" title="Click to view recent permits">
                    <div class="stat-content">
                        <span class="stat-label-modern">Employee Permits</span>
                        <span class="stat-value-modern"><?php echo $stats['emp_permit']; ?></span>
                        <small class="text-primary d-block mt-1" style="font-size: 0.75rem;"><i class="fas fa-search me-1"></i> Search & View</small>
                    </div>
                    <div class="stat-icon-wrapper icon-blue">
                        <i class="fas fa-id-badge"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card border-l-success cursor-pointer" data-bs-toggle="modal" data-bs-target="#studentPermitsModal" title="Click to view recent permits">
                    <div class="stat-content">
                        <span class="stat-label-modern">Student License</span>
                        <span class="stat-value-modern"><?php echo $stats['student_permit']; ?></span>
                        <small class="text-success d-block mt-1" style="font-size: 0.75rem;"><i class="fas fa-search me-1"></i> Search & View</small>
                    </div>
                    <div class="stat-icon-wrapper icon-green">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card border-l-warning cursor-pointer" data-bs-toggle="modal" data-bs-target="#nonProPermitsModal" title="Click to view recent permits">
                    <div class="stat-content">
                        <span class="stat-label-modern">Non-Pro License</span>
                        <span class="stat-value-modern"><?php echo $stats['non_pro_permit']; ?></span>
                        <small class="text-warning d-block mt-1" style="font-size: 0.75rem;"><i class="fas fa-search me-1"></i> Search & View</small>
                    </div>
                    <div class="stat-icon-wrapper icon-orange">
                        <i class="fas fa-address-card"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="calendar-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex flex-column">
                            <h5 class="fw-bold mb-0">Event Schedule</h5>
                            <small class="text-muted">Click date to add • Click event to edit</small>
                        </div>
                        <div class="bg-primary text-white px-4 py-2 rounded-pill shadow-sm" style="background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);">
                            <i class="far fa-calendar-alt me-2"></i>
                            <span id="currentDateDisplay" class="fw-bold">Loading...</span>
                        </div>
                    </div>
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="employeePermitsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-primary"><i class="fas fa-id-badge me-2"></i>Recent Employee Permits</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group">
                        <span class="input-group-text input-group-text-themed"><i class="fas fa-search"></i></span>
                        <input type="text" id="empPermitSearch" class="form-control form-control-themed border-start-0" placeholder="Filter by Name, Dept, Plate...">
                    </div>
                </div>

                <div class="modal-body" id="employeePermitsContainer">
                    <?php if (!empty($recent_emp_permits)): ?>
                        <?php foreach ($recent_emp_permits as $permit): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar"><?php echo get_initials($permit['name']); ?></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($permit['name']); ?></div>
                                        <div class="list-subtitle">
                                            <span class="text-primary fw-bold"><i class="fas fa-hashtag me-1"></i><?php echo $permit['permit_number']; ?></span>
                                            <span class="mx-2 opacity-25">|</span>
                                            <span><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($permit['department']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-dark mb-1"><i class="fas fa-car me-1 text-muted"></i><?php echo htmlspecialchars($permit['plate_number']); ?></div>
                                    <span class="modern-badge badge-soft-primary"><?php echo htmlspecialchars($permit['school_year']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                        <p class="text-muted fw-bold">No recent records found.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <a href="employee_permit.php" class="btn btn-primary rounded-pill px-4">Full System</a>
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="studentPermitsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-success"><i class="fas fa-user-graduate me-2"></i>Recent Student Permits</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div> 
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group">
                        <span class="input-group-text input-group-text-themed"><i class="fas fa-search"></i></span>
                        <input type="text" id="stuPermitSearch" class="form-control form-control-themed border-start-0" placeholder="Filter by Name, Dept, Plate...">
                    </div>
                </div>
                <div class="modal-body" id="studentPermitsContainer">
                    <?php if (!empty($recent_student_permits)): ?>
                        <?php foreach ($recent_student_permits as $permit): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar success"><?php echo get_initials($permit['name']); ?></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($permit['name']); ?></div>
                                        <div class="list-subtitle">
                                            <span class="text-success fw-bold"><i class="fas fa-hashtag me-1"></i><?php echo $permit['permit_number']; ?></span>
                                            <span class="mx-2 opacity-25">|</span>
                                            <span><i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($permit['department']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-dark mb-1"><i class="fas fa-car me-1 text-muted"></i><?php echo htmlspecialchars($permit['plate_number']); ?></div>
                                    <span class="modern-badge badge-soft-success"><?php echo htmlspecialchars($permit['school_year']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                        <p class="text-muted fw-bold">No recent records found.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <a href="student_permit.php" class="btn btn-success rounded-pill px-4">Full System</a>
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="nonProPermitsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-warning"><i class="fas fa-address-card me-2"></i>Recent Non-Pro Permits</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group">
                        <span class="input-group-text input-group-text-themed"><i class="fas fa-search"></i></span>
                        <input type="text" id="nonProPermitSearch" class="form-control form-control-themed border-start-0" placeholder="Filter by Name, Course, Plate...">
                    </div>
                </div>
                <div class="modal-body" id="nonProPermitsContainer">
                    <?php if (!empty($recent_non_pro_permits)): ?>
                        <?php foreach ($recent_non_pro_permits as $permit): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar warning"><?php echo get_initials($permit['name']); ?></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($permit['name']); ?></div>
                                        <div class="list-subtitle">
                                            <span class="text-warning fw-bold"><i class="fas fa-hashtag me-1"></i><?php echo $permit['permit_number']; ?></span>
                                            <span class="mx-2 opacity-25">|</span>
                                            <span><i class="fas fa-book-open me-1"></i><?php echo htmlspecialchars($permit['course']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-dark mb-1"><i class="fas fa-car me-1 text-muted"></i><?php echo htmlspecialchars($permit['plate_number']); ?></div>
                                    <span class="modern-badge badge-soft-warning"><?php echo htmlspecialchars($permit['school_year']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                        <p class="text-muted fw-bold">No recent records found.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <a href="non_permit.php" class="btn btn-warning rounded-pill px-4">Full System</a>
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cctvRequestsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-info"><i class="fas fa-video me-2"></i>Recent CCTV Review Requests</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-3 sticky-top modal-search-container">
                    <div class="input-group">
                        <span class="input-group-text input-group-text-themed"><i class="fas fa-search"></i></span>
                        <input type="text" id="cctvSearch" class="form-control form-control-themed border-start-0" placeholder="Filter by Name, Location, Date...">
                    </div>
                </div>
                <div class="modal-body" id="cctvRequestsContainer">
                    <?php if (!empty($recent_cctv_requests)): ?>
                        <?php foreach ($recent_cctv_requests as $req): ?>
                            <div class="modern-list-item">
                                <div class="d-flex align-items-center">
                                    <div class="list-avatar info"><i class="fas fa-video"></i></div>
                                    <div class="list-info">
                                        <div class="list-title"><?php echo htmlspecialchars($req['requestor_name'] ?? 'N/A'); ?></div>
                                        <div class="list-subtitle">
                                            <span><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($req['location'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small mb-1"><?php echo htmlspecialchars($req['incident_date'] ?? ''); ?></div>
                                    <span class="modern-badge badge-soft-info">Pending</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-video-slash fa-3x text-muted mb-3 opacity-50"></i>
                        <p class="text-muted fw-bold">No recent CCTV records found.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <a href="cctv_review_form.php" class="btn btn-info text-white rounded-pill px-4">Full System</a>
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Manage Event</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="eventForm">
                        <input type="hidden" id="eventId">
                        <div class="mb-3"><label class="form-label">Event Title</label><input type="text" class="form-control form-control-themed" id="eventTitle" required></div>
                        <div class="row">
                            <div class="col-6 mb-3"><label class="form-label">Start Time</label><input type="datetime-local" class="form-control form-control-themed" id="eventStart" required></div>
                            <div class="col-6 mb-3"><label class="form-label">End Time</label><input type="datetime-local" class="form-control form-control-themed" id="eventEnd" required></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Color</label><input type="color" class="form-control form-control-color w-100" id="eventColor" value="#4318ff"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger me-auto" id="deleteEventBtn" style="display:none;">Delete</button>
                    <button type="button" class="btn btn-primary" id="saveEventBtn">Save Event</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const toggleBtnMobile = document.getElementById('sidebarToggle');
        if(toggleBtnMobile) toggleBtnMobile.addEventListener('click', () => sidebar.classList.toggle('show'));

        const toggleBtn = document.getElementById('themeToggle');
        const icon = toggleBtn.querySelector('i');
        const html = document.documentElement;
        
        function updateIcon(theme) { icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon'; }

        const currentTheme = html.getAttribute('data-bs-theme');
        updateIcon(currentTheme);

        toggleBtn.addEventListener('click', () => {
            const newTheme = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('appTheme', newTheme);
            document.cookie = "theme=" + newTheme + "; path=/; max-age=31536000";
            updateIcon(newTheme);
        });

        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            let val = document.getElementById('searchInput').value.toLowerCase().trim();
            if(val.includes('letter')) window.location.href = 'view_details.php?view=letter';
            else if(val.includes('division')) window.location.href = 'view_details.php?view=division';
            else if(val.includes('incident')) window.location.href = 'view_details.php?view=incident';
            else if(val.includes('vaping')) window.location.href = 'view_details.php?view=vaping';
            else if(val.includes('parking')) window.location.href = 'view_details.php?view=parking';
            else if(val.includes('cctv')) window.location.href = 'cctv_review_form.php'; 
            else if(val.includes('employee')) window.location.href = 'employee_permit.php';
            else if(val.includes('student')) window.location.href = 'student_permit.php';
            else if(val.includes('non pro')) window.location.href = 'non_permit.php';
            else if(val.includes('admin') || val.includes('approv')) window.location.href = 'admin_approval.php';
            else alert("Page not found: " + val);
        });

        // --- UPDATED SEARCH FUNCTION FOR MODERN LISTS ---
        function attachSearch(inputId, containerId) {
            document.getElementById(inputId).addEventListener('keyup', function() {
                let filter = this.value.toLowerCase();
                let items = document.querySelectorAll('#' + containerId + ' .modern-list-item');
                items.forEach(item => {
                    let text = item.innerText.toLowerCase();
                    item.style.display = text.includes(filter) ? 'flex' : 'none';
                });
            });
        }
        
        // Updated container IDs
        attachSearch('empPermitSearch', 'employeePermitsContainer');
        attachSearch('stuPermitSearch', 'studentPermitsContainer');
        attachSearch('nonProPermitSearch', 'nonProPermitsContainer');
        attachSearch('cctvSearch', 'cctvRequestsContainer');

        document.addEventListener('DOMContentLoaded', function() {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDateDisplay').textContent = new Date().toLocaleDateString('en-US', options);

            var calendarEl = document.getElementById('calendar');
            var modal = new bootstrap.Modal(document.getElementById('eventModal'));
            var toastEl = document.getElementById('liveToast');
            var toast = new bootstrap.Toast(toastEl);
            var sound = document.getElementById('notifSound');

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
                height: 500, editable: true, selectable: true, events: 'event_handler.php?action=fetch',
                
                select: function(info) { document.getElementById('eventForm').reset(); document.getElementById('eventId').value = ''; document.getElementById('eventStart').value = info.startStr + "T09:00"; document.getElementById('eventEnd').value = info.startStr + "T10:00"; document.getElementById('deleteEventBtn').style.display = 'none'; modal.show(); },
                
                eventClick: function(info) { document.getElementById('eventId').value = info.event.id; document.getElementById('eventTitle').value = info.event.title; let start = info.event.start ? new Date(info.event.start.getTime() - (info.event.start.getTimezoneOffset() * 60000)).toISOString().slice(0,16) : ''; let end = info.event.end ? new Date(info.event.end.getTime() - (info.event.end.getTimezoneOffset() * 60000)).toISOString().slice(0,16) : start; document.getElementById('eventStart').value = start; document.getElementById('eventEnd').value = end; document.getElementById('eventColor').value = info.event.backgroundColor; document.getElementById('deleteEventBtn').style.display = 'block'; modal.show(); },
                
                eventDrop: function(info) { updateEvent(info.event); }, eventResize: function(info) { updateEvent(info.event); }
            });
            calendar.render();

            document.getElementById('saveEventBtn').addEventListener('click', function() { let id = document.getElementById('eventId').value; let title = document.getElementById('eventTitle').value; let start = document.getElementById('eventStart').value; let end = document.getElementById('eventEnd').value; let color = document.getElementById('eventColor').value; if(title && start && end) { let formData = new FormData(); formData.append('title', title); formData.append('start', start); formData.append('end', end); formData.append('color', color); if(id) formData.append('id', id); fetch('event_handler.php?action=' + (id ? 'update' : 'add'), { method: 'POST', body: formData }).then(r => r.json()).then(data => { if(data.success) { calendar.refetchEvents(); modal.hide(); } }); } });
            document.getElementById('deleteEventBtn').addEventListener('click', function() { if(confirm('Delete this event?')) { let formData = new FormData(); formData.append('id', document.getElementById('eventId').value); fetch('event_handler.php?action=delete', { method: 'POST', body: formData }).then(r => r.json()).then(data => { if(data.success) { calendar.refetchEvents(); modal.hide(); } }); } });
            function updateEvent(event) { let formData = new FormData(); formData.append('id', event.id); formData.append('title', event.title); let start = new Date(event.start.getTime() - (event.start.getTimezoneOffset() * 60000)).toISOString().slice(0, 19).replace('T', ' '); let end = event.end ? new Date(event.end.getTime() - (event.end.getTimezoneOffset() * 60000)).toISOString().slice(0, 19).replace('T', ' ') : start; formData.append('start', start); formData.append('end', end); formData.append('color', event.backgroundColor); fetch('event_handler.php?action=update', { method: 'POST', body: formData }); }
            setInterval(() => { fetch('event_handler.php?action=check_notification').then(r => r.json()).then(data => { if(data.length > 0) { sound.play().catch(e => console.log("Audio requires interaction")); data.forEach(ev => { document.getElementById('toastMessage').innerText = "New Event: " + ev.title; toast.show(); }); calendar.refetchEvents(); } }).catch(e => console.error(e)); }, 5000);
        });
    </script>
</body>
</html>