<?php
session_start();
include "db_conn.php";

// 1. Security Check
if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

// 2. Helper Function to Prevent Crashes
function get_cnt($conn, $sql) {
    if (!$conn) return 0;
    $res = @$conn->query($sql); // The '@' suppresses immediate fatal errors
    return ($res && $res->num_rows > 0) ? ($res->fetch_assoc()['c'] ?? 0) : 0;
}

// 3. Initialize Stats
$stats = [
    'users' => 0, 'total_permits' => 0, 'pending' => 0,
    'comm_letter' => 0, 'division' => 0, 'incident' => 0, 'vaping' => 0, 'parking_form' => 0,
    'emp_permit' => 0, 'student_permit' => 0, 'non_pro_permit' => 0
];

if ($conn) {
    // Core Stats
    $stats['users'] = get_cnt($conn, "SELECT COUNT(*) as c FROM users WHERE role='user' AND status='active'");
    $stats['total_permits'] = get_cnt($conn, "SELECT COUNT(*) as c FROM permits");
    $stats['pending'] = get_cnt($conn, "SELECT COUNT(*) as c FROM users WHERE status='pending'");

    // Form Counts
    $stats['comm_letter'] = get_cnt($conn, "SELECT COUNT(*) as c FROM form_submissions WHERE form_type='letter'");
    $stats['division'] = get_cnt($conn, "SELECT COUNT(*) as c FROM form_submissions WHERE form_type='division'");
    $stats['incident'] = get_cnt($conn, "SELECT COUNT(*) as c FROM form_submissions WHERE form_type='incident'");
    $stats['vaping'] = get_cnt($conn, "SELECT COUNT(*) as c FROM form_submissions WHERE form_type='vaping'");
    $stats['parking_form'] = get_cnt($conn, "SELECT COUNT(*) as c FROM form_submissions WHERE form_type='parking'");

    // Permit Breakdown
    $stats['emp_permit'] = get_cnt($conn, "SELECT COUNT(*) as c FROM permits WHERE type='EMPLOYEES'");
    $stats['student_permit'] = get_cnt($conn, "SELECT COUNT(*) as c FROM permits WHERE type='STUDENT LICENSE'");
    $stats['non_pro_permit'] = get_cnt($conn, "SELECT COUNT(*) as c FROM permits WHERE type='STUDENT NON-PRO'");
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

        /* --- 1. THEME VARIABLES --- */
        :root {
            --bg-body: #f4f7fe; --bg-card: #ffffff; --text-main: #2b3674; --text-muted: #a3aed0;
            --border-color: #e0e5f2; --sidebar-bg: #ffffff; --input-bg: #f8f9fa; --navbar-bg: #2c2c2c;
            --primary-color: #4318ff; --sidebar-width: 260px; --navbar-height: 70px;
        }
        [data-bs-theme="dark"] {
            --bg-body: #0b1437; --bg-card: #111c44; --text-main: #ffffff; --text-muted: #8f9bba;
            --border-color: #1b254b; --sidebar-bg: #111c44; --input-bg: #0b1437; --navbar-bg: #0b1437;
        }

        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Poppins', sans-serif; overflow-x: hidden; }

        /* --- 2. NAVBAR --- */
        .navbar-custom { height: var(--navbar-height); background: var(--navbar-bg) !important; box-shadow: 0 4px 12px rgba(0,0,0,0.1); position: fixed; top: 0; left: 0; right: 0; z-index: 1000; padding: 0 20px; }
        .navbar-brand { font-family: "Bebas Neue", sans-serif; font-size: 1.8rem; letter-spacing: 1px; color: #fff !important; }

        /* --- 3. SIDEBAR --- */
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; top: 0; left: 0; z-index: 900; background-color: var(--sidebar-bg); border-right: 1px solid var(--border-color); padding-top: var(--navbar-height); overflow-y: auto; transition: 0.3s; }
        .sidebar-content { padding: 20px 15px; }
        .sidebar .nav-link { color: var(--text-muted); font-weight: 500; padding: 12px 20px; border-radius: 10px; margin-bottom: 5px; display: flex; align-items: center; transition: all 0.3s; }
        .sidebar .nav-link:hover { background: var(--bg-body); color: var(--primary-color); }
        .sidebar .nav-link.active { background: var(--bg-body); color: var(--primary-color); font-weight: 600; }
        .sidebar-heading { font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); padding: 20px 20px 10px; }

        /* --- 4. MAIN CONTENT --- */
        .main-content { margin-left: var(--sidebar-width); padding: 20px 30px; padding-top: calc(var(--navbar-height) + 30px); min-height: 100vh; width: calc(100% - var(--sidebar-width)); transition: 0.3s; }

        /* --- 5. CARDS & LINKS --- */
        /* Link Wrapper Style - Makes the whole card clickable */
        .card-link { text-decoration: none; color: inherit; display: block; height: 100%; transition: transform 0.3s; }
        .card-link:hover { transform: translateY(-5px); }

        .solid-stat-card { border-radius: 15px; padding: 30px 25px; color: #fff; display: flex; flex-direction: column; justify-content: center; height: 100%; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        
        .bg-primary-blue { background: #0d6efd; }
        .bg-success-green { background: #198754; }
        .bg-warning-orange { background: #ffc107; color: #333 !important; }
        .bg-warning-orange .stat-value, .bg-warning-orange .stat-label { color: #333 !important; }

        .mini-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 15px; padding: 25px; text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
        .mini-card:hover { border-color: var(--primary-color); } 
        .mini-value { font-size: 2rem; font-weight: 700; color: var(--text-main); margin-bottom: 5px; }
        .mini-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }

        .stat-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 15px; padding: 25px; height: 100%; display: flex; align-items: center; justify-content: space-between; }
        .stat-card:hover { box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .stat-label-modern { font-size: 0.85rem; color: var(--text-muted); display: block; margin-bottom: 5px; }
        .stat-value-modern { font-size: 1.8rem; font-weight: 700; color: var(--text-main); }
        .stat-icon-wrapper { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        
        .icon-blue { background: #e7f1ff; color: #33c1ff; } .icon-green { background: #e6fffa; color: #05cd99; } .icon-orange { background: #fff7e6; color: #ffb547; }
        [data-bs-theme="dark"] .icon-blue { background: rgba(51, 193, 255, 0.15); }
        [data-bs-theme="dark"] .icon-green { background: rgba(5, 205, 153, 0.15); }
        [data-bs-theme="dark"] .icon-orange { background: rgba(255, 181, 71, 0.15); }

        .border-l-primary { border-left: 5px solid var(--primary-color) !important; }
        .border-l-success { border-left: 5px solid #198754 !important; }
        .border-l-warning { border-left: 5px solid #ffc107 !important; }

        .calendar-card { background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); padding: 20px; color: var(--text-main); }
        .fc-button { background-color: var(--primary-color) !important; border: none !important; }

        /* Search */
        .search-container { position: relative; width: 300px; }
        .search-input { border-radius: 20px; padding-left: 40px; border: 1px solid #444; background: #3a3a3a; color: #fff; height: 38px; }
        .search-input:focus { background: #444; color: #fff; border-color: var(--primary-color); outline: none; }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }

        /* Toast */
        .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }

        @media (max-width: 991px) { .sidebar { left: -100%; z-index: 1100; } .sidebar.show { left: 0; box-shadow: 5px 0 15px rgba(0,0,0,0.2); } .main-content { margin-left: 0; width: 100%; } }
    </style>
    
    <script>
        const savedTheme = localStorage.getItem('appTheme') || 'light';
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
                <button class="btn text-white d-lg-none me-3" id="sidebarToggle"><i class="fas fa-bars fa-lg"></i></button>
                <a class="navbar-brand d-flex align-items-center" href="#">
                    <img src="background.png" alt="Logo" width="35" height="35" class="me-2 rounded-circle bg-white p-1" onerror="this.style.display='none'">
                    SAPD SYSTEM
                </a>
            </div>
            <form class="d-none d-md-block search-container" id="searchForm">
                <i class="fas fa-search search-icon"></i>
                <input class="form-control search-input" type="search" id="searchInput" placeholder="Search forms, permits...">
            </form>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light btn-sm rounded-circle" id="themeToggle"><i class="fas fa-moon"></i></button>
                <div class="dropdown">
                    <a class="nav-link text-white dropdown-toggle fw-bold" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle fa-lg me-2"></i> <?php echo $_SESSION['name']; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item text-danger" href="logout.php" onclick="localStorage.removeItem('appTheme')">Log Out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="fas fa-th-large me-3" style="width:20px;"></i> Dashboard</a></li>
                <h6 class="sidebar-heading">Admin Controls</h6>
                <li class="nav-item"><a class="nav-link" href="admin_approval.php"><i class="fas fa-user-check me-3" style="width:20px;"></i> Approvals <?php if($stats['pending'] > 0): ?><span class="badge bg-danger rounded-pill ms-auto"><?php echo $stats['pending']; ?></span><?php endif; ?></a></li>
                <h6 class="sidebar-heading">Forms Management</h6>
                <li class="nav-item"><a class="nav-link" href="view_details.php?view=letter"><i class="fas fa-envelope-open-text me-3"></i> Comm. Letter</a></li>
                <li class="nav-item"><a class="nav-link" href="view_details.php?view=division"><i class="fas fa-file-alt me-3"></i> Division Form</a></li>
                <li class="nav-item"><a class="nav-link" href="view_details.php?view=incident"><i class="fas fa-exclamation-triangle me-3"></i> Incident Report</a></li>
                <li class="nav-item"><a class="nav-link" href="view_details.php?view=vaping"><i class="fas fa-smoking-ban me-3"></i> Vaping Incident</a></li>
                <li class="nav-item"><a class="nav-link" href="view_details.php?view=parking"><i class="fas fa-car-crash me-3"></i> Parking Form</a></li>
                <h6 class="sidebar-heading">Permits</h6>
                <li class="nav-item"><a class="nav-link" href="view_details.php?view=EMPLOYEES"><i class="fas fa-id-badge me-3"></i> Employee Permit</a></li>
                <li class="nav-item"><a class="nav-link" href="view_details.php?view=STUDENT LICENSE"><i class="fas fa-user-graduate me-3"></i> Student License</a></li>
                <li class="nav-item"><a class="nav-link" href="view_details.php?view=STUDENT NON-PRO"><i class="fas fa-address-card me-3"></i> Non-Pro License</a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <h2 class="mb-4 fw-bold">Dashboard Overview</h2>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <a href="view_details.php?view=users" class="card-link">
                    <div class="solid-stat-card bg-primary-blue">
                        <span class="stat-value"><?php echo $stats['users']; ?></span><span class="stat-label">Active Users</span>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="view_details.php?view=all_permits" class="card-link">
                    <div class="solid-stat-card bg-success-green">
                        <span class="stat-value"><?php echo $stats['total_permits']; ?></span><span class="stat-label">Total Permits Issued</span>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="admin_approval.php" class="card-link">
                    <div class="solid-stat-card bg-warning-orange">
                        <span class="stat-value"><?php echo $stats['pending']; ?></span><span class="stat-label">Pending Requests</span>
                    </div>
                </a>
            </div>
        </div>

        <h5 class="fw-bold mb-3 text-secondary">Submitted Forms</h5>
        <div class="row row-cols-2 row-cols-lg-5 g-3 mb-4">
            <div class="col">
                <a href="view_details.php?view=letter" class="card-link">
                    <div class="mini-card"><div class="mini-value"><?php echo $stats['comm_letter']; ?></div><div class="mini-label">Comm. Letter</div></div>
                </a>
            </div>
            <div class="col">
                <a href="view_details.php?view=division" class="card-link">
                    <div class="mini-card"><div class="mini-value"><?php echo $stats['division']; ?></div><div class="mini-label">Division</div></div>
                </a>
            </div>
            <div class="col">
                <a href="view_details.php?view=incident" class="card-link">
                    <div class="mini-card"><div class="mini-value"><?php echo $stats['incident']; ?></div><div class="mini-label">Incident</div></div>
                </a>
            </div>
            <div class="col">
                <a href="view_details.php?view=vaping" class="card-link">
                    <div class="mini-card"><div class="mini-value"><?php echo $stats['vaping']; ?></div><div class="mini-label">Vaping</div></div>
                </a>
            </div>
            <div class="col">
                <a href="view_details.php?view=parking" class="card-link">
                    <div class="mini-card"><div class="mini-value"><?php echo $stats['parking_form']; ?></div><div class="mini-label">Parking</div></div>
                </a>
            </div>
        </div>

        <h5 class="fw-bold mb-3 text-secondary">Permit Breakdown</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <a href="view_details.php?view=EMPLOYEES" class="card-link">
                    <div class="stat-card border-l-primary">
                        <div class="stat-content"><span class="stat-label-modern">Employee Permits</span><span class="stat-value-modern"><?php echo $stats['emp_permit']; ?></span></div>
                        <div class="stat-icon-wrapper icon-blue"><i class="fas fa-id-badge"></i></div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="view_details.php?view=STUDENT LICENSE" class="card-link">
                    <div class="stat-card border-l-success">
                        <div class="stat-content"><span class="stat-label-modern">Student License</span><span class="stat-value-modern"><?php echo $stats['student_permit']; ?></span></div>
                        <div class="stat-icon-wrapper icon-green"><i class="fas fa-user-graduate"></i></div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="view_details.php?view=STUDENT NON-PRO" class="card-link">
                    <div class="stat-card border-l-warning">
                        <div class="stat-content"><span class="stat-label-modern">Non-Pro License</span><span class="stat-value-modern"><?php echo $stats['non_pro_permit']; ?></span></div>
                        <div class="stat-icon-wrapper icon-orange"><i class="fas fa-address-card"></i></div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="calendar-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">Event Schedule</h5>
                        <small class="text-muted">Click date to add • Click event to edit</small>
                    </div>
                    <div id="calendar"></div>
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
                        <div class="mb-3"><label class="form-label">Event Title</label><input type="text" class="form-control" id="eventTitle" required></div>
                        <div class="row">
                            <div class="col-6 mb-3"><label class="form-label">Start Time</label><input type="datetime-local" class="form-control" id="eventStart" required></div>
                            <div class="col-6 mb-3"><label class="form-label">End Time</label><input type="datetime-local" class="form-control" id="eventEnd" required></div>
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
        updateIcon(localStorage.getItem('appTheme') || 'light');
        toggleBtn.addEventListener('click', () => {
            const newTheme = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('appTheme', newTheme);
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
            else if(val.includes('employee')) window.location.href = 'view_details.php?view=EMPLOYEES';
            else if(val.includes('student')) window.location.href = 'view_details.php?view=STUDENT LICENSE';
            else if(val.includes('non pro')) window.location.href = 'view_details.php?view=STUDENT NON-PRO';
            else if(val.includes('admin') || val.includes('approv')) window.location.href = 'admin_approval.php';
            else alert("Page not found: " + val);
        });

        document.addEventListener('DOMContentLoaded', function() {
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
            document.getElementById('deleteEventBtn').addEventListener('click', function() { if(confirm('Delete?')) { let formData = new FormData(); formData.append('id', document.getElementById('eventId').value); fetch('event_handler.php?action=delete', { method: 'POST', body: formData }).then(r => r.json()).then(data => { if(data.success) { calendar.refetchEvents(); modal.hide(); } }); } });
            function updateEvent(event) { let formData = new FormData(); formData.append('id', event.id); formData.append('title', event.title); let start = new Date(event.start.getTime() - (event.start.getTimezoneOffset() * 60000)).toISOString().slice(0, 19).replace('T', ' '); let end = event.end ? new Date(event.end.getTime() - (event.end.getTimezoneOffset() * 60000)).toISOString().slice(0, 19).replace('T', ' ') : start; formData.append('start', start); formData.append('end', end); formData.append('color', event.backgroundColor); fetch('event_handler.php?action=update', { method: 'POST', body: formData }); }
            setInterval(() => { fetch('event_handler.php?action=check_notification').then(r => r.json()).then(data => { if(data.length > 0) { sound.play().catch(e => console.log("Audio block")); data.forEach(ev => { document.getElementById('toastMessage').innerText = "New Event: " + ev.title; toast.show(); }); calendar.refetchEvents(); } }).catch(e => console.error(e)); }, 5000);
        });
    </script>
</body>
</html>