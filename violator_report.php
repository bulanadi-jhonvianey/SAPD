<?php
// --- 1. SETUP & CONFIGURATION ---
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database Credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sapd_db";

// Create Connection
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize Database & Table
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
$conn->select_db($dbname);

// Table Setup - VIOLATOR LOGS
$table_sql = "CREATE TABLE IF NOT EXISTS violator_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL,
    student_name VARCHAR(255) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    violation VARCHAR(255) DEFAULT NULL,
    report_time TIME NOT NULL,
    officer_name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($table_sql);

// Session Queue - Now using multi-page system
if (!isset($_SESSION['log_print_queue'])) {
    $_SESSION['log_print_queue'] = [[]]; // Start with one page
    $_SESSION['current_page'] = 0;
}
if (!isset($_SESSION['current_page'])) {
    $_SESSION['current_page'] = 0;
}

// Session Officer Name
if (!isset($_SESSION['current_officer'])) {
    $_SESSION['current_officer'] = '';
}

// --- CONSTANTS ---
$MAX_LOG_ROWS = 16; // Max rows per page
$current_page = $_SESSION['current_page'];

// Ensure current page exists
if (!isset($_SESSION['log_print_queue'][$current_page])) {
    $_SESSION['log_print_queue'][$current_page] = [];
}

// --- 2. FORM HANDLERS ---
$success_msg = "";
$error_msg = "";

// HANDLE: ADD TO LOG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_log'])) {
    $date = $conn->real_escape_string($_POST['report_date']);
    $student = $conn->real_escape_string($_POST['student_name']);
    $location = $conn->real_escape_string($_POST['location']);
    $violation = $conn->real_escape_string($_POST['violation']);
    $time = $conn->real_escape_string($_POST['report_time']);
    $officer = $conn->real_escape_string($_POST['officer_name']);

    $_SESSION['current_officer'] = $officer;

    $stmt = $conn->prepare("INSERT INTO violator_logs (report_date, student_name, location, violation, report_time, officer_name) VALUES (?, ?, ?, ?, ?, ?)");

    if ($stmt) {
        $stmt->bind_param("ssssss", $date, $student, $location, $violation, $time, $officer);
        if ($stmt->execute()) {
            // Add to current page
            $_SESSION['log_print_queue'][$current_page][] = [
                'date' => $date,
                'student' => $student,
                'location' => $location,
                'violation' => $violation,
                'time' => $time
            ];
            
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit();
        } else {
            $error_msg = "Save Failed: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_msg = "Database Error: " . $conn->error;
    }
}

// HANDLE: NEW SHEET
if (isset($_POST['new_sheet'])) {
    $_SESSION['log_print_queue'][] = []; // Add new page
    $_SESSION['current_page'] = count($_SESSION['log_print_queue']) - 1; // Set to new page
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// HANDLE: SWITCH PAGE
if (isset($_GET['page'])) {
    $page_num = intval($_GET['page']);
    if ($page_num >= 0 && $page_num < count($_SESSION['log_print_queue'])) {
        $_SESSION['current_page'] = $page_num;
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// HANDLE: CLEAR QUEUE (CURRENT PAGE ONLY)
if (isset($_POST['clear_page'])) {
    $_SESSION['log_print_queue'][$current_page] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// HANDLE: CLEAR ALL QUEUES
if (isset($_POST['clear_all_queues'])) {
    $_SESSION['log_print_queue'] = [[]]; // Reset to single empty page
    $_SESSION['current_page'] = 0;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// HANDLE: DELETE ITEM
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM violator_logs WHERE id = $del_id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['success']))
    $success_msg = "Entry added to log!";

$recent_logs = $conn->query("SELECT * FROM violator_logs ORDER BY id DESC LIMIT 10");
$total_count = $conn->query("SELECT COUNT(*) as total FROM violator_logs")->fetch_assoc()['total'];

// Calculate queue stats
$current_page_count = count($_SESSION['log_print_queue'][$current_page]);
$total_pages = count($_SESSION['log_print_queue']);
$is_page_full = $current_page_count >= $MAX_LOG_ROWS;

// Calculate total items across all pages
$total_queue_items = 0;
foreach ($_SESSION['log_print_queue'] as $page) {
    $total_queue_items += count($page);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAPD Violator Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        /* --- OLD ENGLISH TEXT MT FONT --- */
        @font-face {
            font-family: "Old English Text MT";
            src: url("https://db.onlinewebfonts.com/t/f3258385782c4c96aa24fe8b5d5f9782.eot");
            src: url("https://db.onlinewebfonts.com/t/f3258385782c4c96aa24fe8b5d5f9782.eot?#iefix") format("embedded-opentype"),
                 url("https://db.onlinewebfonts.com/t/f3258385782c4c96aa24fe8b5d5f9782.woff2") format("woff2"),
                 url("https://db.onlinewebfonts.com/t/f3258385782c4c96aa24fe8b5d5f9782.woff") format("woff"),
                 url("https://db.onlinewebfonts.com/t/f3258385782c4c96aa24fe8b5d5f9782.ttf") format("truetype"),
                 url("https://db.onlinewebfonts.com/t/f3258385782c4c96aa24fe8b5d5f9782.svg#Old English Text MT") format("svg");
            font-weight: normal;
            font-style: normal;
        }

        /* --- THEME VARIABLES --- */
        :root {
            --bg-body: #0a1128;
            --panel-bg: #13203c;
            --input-bg: #1f2f4e;
            --text-main: #ffffff;
            --accent: #007bff;
            --border: #2c3e50;
        }

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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background-color 0.3s, color 0.3s;
            padding-bottom: 50px;
        }

        /* --- NAVBAR --- */
        .navbar {
            background: var(--panel-bg);
            border-bottom: 1px solid var(--border);
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* --- BUTTONS --- */
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

        .btn-primary {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #858796 0%, #60616f 100%);
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

        .btn-info {
            background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
            color: white;
        }

        .btn-purple {
            background: linear-gradient(135deg, #6f42c1 0%, #4e2a8c 100%);
            color: white;
        }

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

        /* --- LAYOUT --- */
        .main-container {
            display: flex;
            gap: 20px;
            padding: 0 20px;
            align-items: stretch;
        }

        .left-panel,
        .right-panel,
        .bottom-panel {
            background: var(--panel-bg);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

        .left-panel {
            flex: 1;
            max-width: 450px;
            display: flex;
            flex-direction: column;
        }

        .right-panel {
            flex: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            position: relative;
            background-color: var(--panel-bg);
            overflow: hidden;
        }

        .bottom-panel {
            margin: 20px;
        }

        .form-control,
        .form-select {
            background-color: var(--input-bg);
            border: 1px solid var(--border);
            color: var(--text-main);
            margin-bottom: 10px;
            padding: 12px;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: var(--input-bg);
            border-color: var(--accent);
            color: var(--text-main);
            box-shadow: none;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .panel-title {
            color: #0d6efd;
            font-weight: 900;
            text-transform: uppercase;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge-queue {
            background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
        }

        .badge-page {
            background: linear-gradient(135deg, #6f42c1 0%, #4e2a8c 100%);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .page-indicator {
            background: var(--input-bg);
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--border);
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .page-tab {
            padding: 8px 15px;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .page-tab:hover {
            background: var(--accent);
            color: white;
        }

        .page-tab.active {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            border-color: #224abe;
        }

        /* --- PRINT AREA CONTAINERS --- */
        .print-area-container {
            width: 11in;
            height: 8.5in;
            background: white;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            padding: 0.5in;
            position: relative;
            transform: scale(0.75);
            transform-origin: top center;
            display: flex;
            flex-direction: column;
            color: black;
            box-sizing: border-box;
            overflow: hidden;
            margin: 0 auto;
        }

        #print-area,
        #print-blank-area {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        #print-blank-area {
            display: none;
        }

        /* --- NEW HEADER LAYOUT (FROM GUIDANCE FORM) --- */
        .new-header-wrapper {
            position: relative;
            width: calc(100% + 1in);
            margin-left: -0.5in;
            margin-right: -0.5in;
            margin-top: -0.4in;
            padding-top: 5px;
            margin-bottom: 5px;
        }

        .new-header-logo {
            position: fixed;
            left: 5px;
            top: -1px;
            width: 180px;
            height: auto;
            z-index: 10;
        }

        .new-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-left: 1.6in;
            padding-right: 0.5in;
            padding-bottom: 5px;
            min-height: 45px;
        }

        .new-header-title {
            font-family: "Old English Text MT", serif;
            font-size: 26pt;
            color: #002060;
            margin: 0;
            line-height: 0.9;
            white-space: nowrap;
        }

        .new-header-address {
            font-family: "Century Gothic", Arial, sans-serif;
            font-size: 8pt;
            color: #002060;
            margin: 0;
            padding-bottom: 2px;
            white-space: nowrap;
        }

        .new-header-bar {
            background-color: #FFB800;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 0.5in;
            width: 100%;
            position: relative;
            z-index: 1;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .new-header-url {
            font-family: "Century Gothic", Arial, sans-serif;
            font-size: 9pt;
            font-weight: bold;
            color: #002060;
            margin: 0;
        }

        /* Form Sub-Header (SAPD) - Keep existing */
        .division-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            position: relative;
            z-index: 60;
        }

        .sapd-logo {
            width: 45px;
            height: auto;
            object-fit: contain;
        }

        .division-title {
            text-align: center;
            margin-top: 5px;
        }

        .division-title h2 {
            font-family: "Bookman Old Style", "Times New Roman", serif;
            font-weight: 900;
            font-size: 18px;
            margin: 0;
            text-transform: uppercase;
        }

        .division-title h3 {
            font-family: "Arial", sans-serif;
            font-weight: bold;
            text-decoration: underline;
            font-size: 14px;
            margin: 2px 0 0 0;
            text-transform: uppercase;
        }

        /* --- TABLE --- */
        .log-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid black;
            table-layout: fixed;
            margin-top: 10px;
        }

        .log-table th,
        .log-table td {
            border: 1px solid black;
            padding: 4px 8px;
            font-size: 10pt;
            height: 28px;
            overflow: hidden;
            white-space: nowrap;
        }

        .log-table th {
            font-weight: bold;
            text-align: center;
            background: white;
            border-bottom: 2px solid black;
            font-family: Arial, sans-serif;
        }

        .col-date {
            width: 12%;
            text-align: center;
        }

        .col-name {
            width: 30%;
        }

        .col-loc {
            width: 20%;
            text-align: center;
        }

        .col-viol {
            width: 25%;
        }

        .col-time {
            width: 13%;
            text-align: center;
        }

        .sheet-footer {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 10px;
            font-weight: bold;
            font-size: 10pt;
        }

        .officer-line {
            border-bottom: 1px solid black;
            min-width: 300px;
            display: inline-block;
        }

        /* --- PRINT MEDIA --- */
        @media print {
            @page {
                size: 11in 8.5in landscape;
                margin: 0;
            }

            .navbar,
            .left-panel,
            .bottom-panel,
            .panel-header,
            .btn,
            .d-print-none,
            #resetBtn,
            form,
            .page-tabs,
            .page-indicator {
                display: none !important;
            }

            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                overflow: visible !important;
            }

            .main-container {
                display: block !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                height: auto !important;
            }

            .right-panel {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                background: white !important;
                flex: none !important;
                overflow: visible !important;
            }

            .print-area-container {
                transform: none !important;
                width: 100% !important;
                height: 100vh !important;
                box-shadow: none !important;
                padding: 0.5in !important;
                margin: 0 !important;
                border: none !important;
                display: block !important;
                page-break-after: always;
            }

            .print-area-container:last-child {
                page-break-after: avoid;
            }

            /* New header adjustments for print */
            .new-header-wrapper {
                margin-top: -0.6in !important;
                margin-left: -0.5in !important;
                margin-right: -0.5in !important;
                padding-top: 0.4in !important;
            }

            .new-header-logo {
                position: absolute !important;
                top: 0.2in !important;
                left: 0.1in !important;
                width: 180px !important;
            }

            .new-header-title,
            .new-header-address,
            .new-header-url {
                color: #002060 !important;
            }
        }

        /* TABLES (Dashboard) */
        .table-custom {
            color: var(--text-main);
            --bs-table-bg: transparent;
            --bs-table-striped-bg: rgba(255, 255, 255, 0.03);
            --bs-table-hover-bg: var(--input-bg);
        }

        body.light-mode .table-custom {
            --bs-table-striped-bg: rgba(0, 0, 0, 0.02);
        }

        .table-custom th {
            background-color: var(--input-bg);
            color: var(--accent);
            border-color: var(--border);
        }

        .table-custom td {
            color: #ffffff !important;
            border-color: var(--border);
        }

        body.light-mode .table-custom td {
            color: #212529 !important;
        }

        .table-custom tbody tr:hover {
            background-color: var(--input-bg);
        }

        .text-center.py-4 {
            color: var(--text-main);
            opacity: 0.7;
        }

        .badge.bg-dark {
            background-color: var(--input-bg) !important;
            color: var(--text-main);
            border: 1px solid var(--border);
        }

        input[type="date"],
        input[type="time"] {
            position: relative;
            z-index: 1;
        }

        .time-date-label {
            color: #000 !important;
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
        }

        .form-control::placeholder {
            color: #000 !important;
            opacity: 1;
        }

        body:not(.light-mode) .form-control::placeholder {
            color: #fff !important;
        }

        body:not(.light-mode) .time-date-label {
            color: #fff !important;
        }

        .time-input,
        .date-input {
            background-color: var(--input-bg) !important;
            color: #000 !important;
            border: 1px solid var(--border) !important;
            padding: 12px !important;
            margin-bottom: 10px !important;
        }

        body:not(.light-mode) .time-input,
        body:not(.light-mode) .date-input {
            color: #fff !important;
        }

        body:not(.light-mode) .time-input::placeholder,
        body:not(.light-mode) .date-input::placeholder {
            color: #ccc !important;
        }

        .btn-new-sheet {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(111, 66, 193, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(111, 66, 193, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(111, 66, 193, 0);
            }
        }
    </style>
</head>

<body>

    <div class="navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-secondary fw-bold"><i class="fa fa-arrow-left me-2"></i> Back</a>
            <h4 class="m-0 fw-bold text-white">SAPD Violator Log</h4>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-theme rounded-circle" onclick="toggleTheme()" id="themeBtn">
                <i class="fa fa-moon"></i>
            </button>
        </div>
    </div>

    <div class="main-container">

        <div class="left-panel">
            <div class="panel-header">
                <div class="panel-title"><i class="fa fa-pen-to-square"></i> ENTRY DETAILS</div>
                <div class="d-flex gap-2">
                    <div class="badge-queue">QUEUE: <?php echo $current_page_count; ?>/<?php echo $MAX_LOG_ROWS; ?></div>
                    <div class="badge-page">PAGE: <?php echo $current_page + 1; ?>/<?php echo $total_pages; ?></div>
                </div>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show"><i class="fa fa-check-circle me-2"></i>
                    <?php echo $success_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-danger alert-dismissible fade show"><i class="fa fa-exclamation-circle me-2"></i>
                    <?php echo $error_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <!-- Page Tabs Navigation -->
            <div class="page-tabs">
                <?php for ($i = 0; $i < $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="page-tab <?php echo $i == $current_page ? 'active' : ''; ?>">
                        Page <?php echo $i + 1; ?> (<?php echo count($_SESSION['log_print_queue'][$i]); ?>)
                    </a>
                <?php endfor; ?>
            </div>

            <form method="POST" id="logForm">
                <input type="text" name="student_name" id="in_student" class="form-control"
                    placeholder="Name of Violator" required oninput="updatePreview()" <?php echo $is_page_full ? 'disabled' : ''; ?>>

                <input type="text" name="location" id="in_location" class="form-control" placeholder="Location" required
                    oninput="updatePreview()" <?php echo $is_page_full ? 'disabled' : ''; ?>>

                <input type="text" name="violation" id="in_violation" class="form-control" placeholder="Violation"
                    required oninput="updatePreview()" <?php echo $is_page_full ? 'disabled' : ''; ?>>

                <div class="row">
                    <div class="col-6">
                        <label class="time-date-label">Time</label>
                        <input type="time" name="report_time" id="in_time" class="form-control time-input" required
                            onchange="updatePreview()" oninput="updatePreview()" style="color: #000;" <?php echo $is_page_full ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-6">
                        <label class="time-date-label">Date</label>
                        <input type="date" name="report_date" id="in_date" class="form-control date-input" required
                            onchange="updatePreview()" oninput="updatePreview()" style="color: #000;" <?php echo $is_page_full ? 'disabled' : ''; ?>>
                    </div>
                </div>

                <input type="text" name="officer_name" id="in_officer" class="form-control"
                    placeholder="Safety Officer (Signatory)" required oninput="updatePreview()">

                <?php if ($is_page_full): ?>
                    <div class="alert alert-warning text-center mb-3">
                        <i class="fa fa-exclamation-triangle me-2"></i>
                        <strong>Page Full!</strong> Create a new sheet to add more entries.
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <button type="submit" name="add_to_log" class="btn btn-primary flex-grow-1 fw-bold py-3 mt-2"
                        <?php echo $is_page_full ? 'disabled' : ''; ?>>
                        <i class="fa fa-plus-circle me-2"></i> ADD TO LOG SHEET
                    </button>
                    <button type="button" onclick="resetForm()" class="btn btn-warning fw-bold py-3 mt-2" id="resetBtn"
                        title="Clear form to start new">
                        <i class="fa fa-rotate-right"></i>
                    </button>
                </div>
            </form>

            <hr class="border-secondary my-4">

            <div class="row g-2">
                <!-- NEW SHEET BUTTON (Only shows when current page is full) -->
                <?php if ($is_page_full): ?>
                    <div class="col-12 mb-2">
                        <form method="POST" class="m-0">
                            <button type="submit" name="new_sheet" class="btn btn-purple w-100 fw-bold py-3 btn-new-sheet">
                                <i class="fa fa-plus me-2"></i> NEW SHEET (Page <?php echo $total_pages + 1; ?>)
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Print Queue button - SHOW COUNT ONLY WHEN > 0 -->
                <div class="col-6">
                    <button onclick="printQueue()" class="btn btn-success w-100 fw-bold py-3" <?php echo $total_queue_items == 0 ? 'disabled' : ''; ?>>
                        <i class="fa fa-print me-2"></i> Print Queue 
                        <?php if ($total_queue_items > 0): ?>
                            (<?php echo $total_queue_items; ?>)
                        <?php endif; ?>
                    </button>
                </div>
                
                <div class="col-6">
                    <button onclick="printBlank()" class="btn btn-secondary w-100 fw-bold py-3 text-white">
                        <i class="fa fa-file me-2"></i> Blank Form
                    </button>
                </div>
                
                <?php if ($total_queue_items > 0): ?>
                    <div class="col-12 mt-2">
                        <form method="POST" class="m-0">
                            <button type="submit" name="clear_all_queues" class="btn btn-danger w-100 fw-bold py-3"
                                onclick="return confirm('Clear ALL pages? This cannot be undone!')">
                                <i class="fa fa-trash me-2"></i> Clear Queue
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="right-panel">
            <div class="panel-header w-100 border-bottom pb-3 mb-4" style="border-color: var(--border)!important;">
                <div class="panel-title"><i class="fa fa-eye"></i> DOCUMENT PREVIEW</div>
                <div class="badge-page">Page <?php echo $current_page + 1; ?></div>
            </div>

            <!-- Print Queue Preview -->
            <div class="print-area-container" id="print-area">
                <!-- NEW HEADER (replaces old header-layout) -->
                <div class="new-header-wrapper">
                    <img src="background-hcc-logo.png" alt="HCC Logo" class="new-header-logo">
                    
                    <div class="new-header-top">
                        <div class="new-header-title">Holy Cross College</div>
                        <div class="new-header-address">Holy Cross College Sta. Lucia, Sta. Ana, Pampanga, Philippines 2022</div>
                    </div>
                    
                    <div class="new-header-bar">
                        <div class="new-header-url">www.holycrosscollegepampanga.com</div>
                    </div>
                </div>

                <div class="division-header">
                    <img src="background.png" class="sapd-logo" alt="SAPD Logo">
                    <div class="division-title">
                        <h2>SAFETY AND PROTECTION DIVISION</h2>
                        <h3>VIOLATOR'S REPORT</h3>
                    </div>
                </div>

                <table class="log-table">
                    <thead>
                        <tr>
                            <th class="col-date">DATE</th>
                            <th class="col-name">NAME OF VIOLATOR</th>
                            <th class="col-loc">LOCATION</th>
                            <th class="col-viol">VIOLATION</th>
                            <th class="col-time">TIME</th>
                        </tr>
                    </thead>
                    <tbody id="log-table-body">
                        <?php
                        $current_page_items = $_SESSION['log_print_queue'][$current_page];
                        $items_count = count($current_page_items);
                        foreach ($current_page_items as $item):
                            $timeDisplay = date("h:i A", strtotime($item['time']));
                            ?>
                            <tr>
                                <td class="col-date"><?php echo $item['date']; ?></td>
                                <td class="col-name" style="text-align: left; padding-left: 10px;">
                                    <?php echo strtoupper($item['student']); ?>
                                </td>
                                <td class="col-loc"><?php echo strtoupper($item['location']); ?></td>
                                <td class="col-viol" style="text-align: left; padding-left: 10px;">
                                    <?php echo strtoupper($item['violation']); ?>
                                </td>
                                <td class="col-time"><?php echo $timeDisplay; ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <tr id="preview-row" style="color: #000000; display: none;">
                            <td class="col-date" id="p_date"></td>
                            <td class="col-name" id="p_name"></td>
                            <td class="col-loc" id="p_loc"></td>
                            <td class="col-viol" id="p_viol"></td>
                            <td class="col-time" id="p_time"></td>
                        </tr>

                        <?php
                        // Dynamically fill remaining rows
                        for ($i = 0; $i < ($MAX_LOG_ROWS - $items_count); $i++):
                            ?>
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>

                <div class="sheet-footer">
                    <div class="left">
                        SAFETY OFFICER: <span class="officer-line" style="text-align: center; padding: 0 10px;">
                            <span id="p_officer"><?php echo strtoupper($_SESSION['current_officer']); ?></span>
                        </span>
                    </div>
                    <div class="right"></div>
                </div>
            </div>

            <!-- Blank Form Preview (Hidden by default) -->
            <div class="print-area-container" id="print-blank-area">
                <!-- NEW HEADER (same as above) -->
                <div class="new-header-wrapper">
                    <img src="background-hcc-logo.png" alt="HCC Logo" class="new-header-logo">
                    
                    <div class="new-header-top">
                        <div class="new-header-title">Holy Cross College</div>
                        <div class="new-header-address">Holy Cross College Sta. Lucia, Sta. Ana, Pampanga, Philippines 2022</div>
                    </div>
                    
                    <div class="new-header-bar">
                        <div class="new-header-url">www.holycrosscollegepampanga.com</div>
                    </div>
                </div>

                <div class="division-header">
                    <img src="background.png" class="sapd-logo" alt="SAPD Logo">
                    <div class="division-title">
                        <h2>SAFETY AND PROTECTION DIVISION</h2>
                        <h3>VIOLATOR'S REPORT</h3>
                    </div>
                </div>

                <table class="log-table">
                    <thead>
                        <tr>
                            <th class="col-date">DATE</th>
                            <th class="col-name">NAME OF VIOLATOR</th>
                            <th class="col-loc">LOCATION</th>
                            <th class="col-viol">VIOLATION</th>
                            <th class="col-time">TIME</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < $MAX_LOG_ROWS; $i++): ?>
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>

                <div class="sheet-footer">
                    <div class="left">
                        SAFETY OFFICER: <span class="officer-line" style="text-align: center; padding: 0 10px;">
                            &nbsp;
                        </span>
                    </div>
                    <div class="right"></div>
                </div>
            </div>

        </div>
    </div>

    <div class="bottom-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold m-0"><i class="fa fa-database me-2"></i> RECENT LOGS</h5>
            <span class="badge bg-dark">Total: <?php echo $total_count; ?></span>
        </div>

        <div class="table-responsive">
            <table class="table table-custom table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student Name</th>
                        <th>Violation</th>
                        <th>Location</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_logs && $recent_logs->num_rows > 0): ?>
                        <?php while ($row = $recent_logs->fetch_assoc()): ?>
                            <?php
                            $preview_data = [
                                'student' => $row['student_name'],
                                'location' => $row['location'],
                                'violation' => $row['violation'],
                                'date' => $row['report_date'],
                                'time' => $row['report_time'],
                                'officer' => $row['officer_name']
                            ];
                            $preview_json = htmlspecialchars(json_encode($preview_data), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['violation']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><?php echo $row['report_date']; ?></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-sm btn-info text-white"
                                            onclick="loadToPreview(<?php echo $preview_json; ?>)" title="Load into Preview">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Delete this record?')"><i class="fa fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4"><i class="fa fa-database fa-2x mb-3"></i><br>No records
                                found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Toggle Theme Script
        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            const isLight = document.body.classList.contains('light-mode');
            document.getElementById('themeBtn').innerHTML = isLight ? '<i class="fa fa-sun"></i>' : '<i class="fa fa-moon"></i>';
            const themeValue = isLight ? 'light' : 'dark';
            localStorage.setItem('appTheme', themeValue);
            document.cookie = "theme=" + themeValue + "; path=/; max-age=31536000";
            updateInputColors();
        }

        const savedTheme = localStorage.getItem('appTheme') || 'dark';
        if (savedTheme === 'light') {
            document.body.classList.add('light-mode');
            document.getElementById('themeBtn').innerHTML = '<i class="fa fa-sun"></i>';
        }

        function updateInputColors() {
            const isLight = document.body.classList.contains('light-mode');
            const timeInput = document.getElementById('in_time');
            const dateInput = document.getElementById('in_date');

            if (isLight) {
                timeInput.style.color = '#000';
                dateInput.style.color = '#000';
            } else {
                timeInput.style.color = '#fff';
                dateInput.style.color = '#fff';
            }
        }

        function printQueue() {
            document.getElementById('print-blank-area').style.display = 'none';
            document.getElementById('print-area').style.display = 'flex';

            setTimeout(() => {
                window.print();
                setTimeout(() => {
                    document.getElementById('print-area').style.display = 'flex';
                    document.getElementById('print-blank-area').style.display = 'none';
                }, 500);
            }, 100);
        }

        function printBlank() {
            document.getElementById('print-area').style.display = 'none';
            document.getElementById('print-blank-area').style.display = 'flex';

            setTimeout(() => {
                window.print();
                setTimeout(() => {
                    document.getElementById('print-area').style.display = 'flex';
                    document.getElementById('print-blank-area').style.display = 'none';
                }, 500);
            }, 100);
        }

        function resetForm() {
            document.getElementById('logForm').reset();
            updatePreview();
            document.getElementById('in_student').focus();
        }

        function loadToPreview(data) {
            document.getElementById('in_student').value = data.student;
            document.getElementById('in_location').value = data.location;
            document.getElementById('in_violation').value = data.violation;
            document.getElementById('in_date').value = data.date;
            document.getElementById('in_time').value = data.time;
            document.getElementById('in_officer').value = data.officer;
            updatePreview();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updatePreview() {
            const row = document.getElementById('preview-row');
            const dateVal = document.getElementById('in_date').value;
            const nameVal = document.getElementById('in_student').value;
            const locVal = document.getElementById('in_location').value;
            const violVal = document.getElementById('in_violation').value;
            const timeVal = document.getElementById('in_time').value;
            const officerVal = document.getElementById('in_officer').value;

            if (nameVal || locVal || violVal || dateVal || timeVal) {
                row.style.display = 'table-row';
                document.getElementById('p_date').innerText = dateVal;
                document.getElementById('p_name').innerText = nameVal.toUpperCase();
                document.getElementById('p_loc').innerText = locVal.toUpperCase();
                document.getElementById('p_viol').innerText = violVal.toUpperCase();

                if (timeVal) {
                    let [h, m] = timeVal.split(':');
                    let ampm = 'AM';
                    if (h >= 12) {
                        ampm = 'PM';
                        if (h > 12) h = h - 12;
                    }
                    if (h == 0) h = 12;
                    h = parseInt(h);
                    document.getElementById('p_time').innerText = `${h}:${m} ${ampm}`;
                } else {
                    document.getElementById('p_time').innerText = '';
                }
            } else {
                row.style.display = 'none';
            }

            document.getElementById('p_officer').innerText = officerVal.toUpperCase();
        }

        document.addEventListener('DOMContentLoaded', function () {
            updatePreview();
            updateInputColors();

            const dateInput = document.getElementById('in_date');
            const timeInput = document.getElementById('in_time');

            dateInput.addEventListener('change', updatePreview);
            dateInput.addEventListener('input', updatePreview);
            timeInput.addEventListener('change', updatePreview);
            timeInput.addEventListener('input', updatePreview);

            document.getElementById('in_student').addEventListener('input', updatePreview);
            document.getElementById('in_location').addEventListener('input', updatePreview);
            document.getElementById('in_violation').addEventListener('input', updatePreview);
            document.getElementById('in_officer').addEventListener('input', updatePreview);

            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    new bootstrap.Alert(alert).close();
                });
            }, 5000);
        });
    </script>

</body>

</html>