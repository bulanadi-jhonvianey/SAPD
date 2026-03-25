<?php
// --- violator_log.php ---
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

// Session Queue - Using multi-page system
if (!isset($_SESSION['log_print_queue'])) {
    $_SESSION['log_print_queue'] = [[]]; // Start with one empty page
    $_SESSION['current_page'] = 0;
}
if (!isset($_SESSION['current_page'])) {
    $_SESSION['current_page'] = 0;
}

// Session Officer Name (default empty)
if (!isset($_SESSION['current_officer'])) {
    $_SESSION['current_officer'] = '';
}

// --- CONSTANTS ---
$MAX_LOG_ROWS = 37;
$current_page = $_SESSION['current_page'];

// Ensure current page exists
if (!isset($_SESSION['log_print_queue'][$current_page])) {
    $_SESSION['log_print_queue'][$current_page] = [];
}

// --- 2. FORM HANDLERS ---
$success_msg = "";
$error_msg = "";

// HANDLE: ADD TO LOG (New entry)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_log'])) {
    $date = $conn->real_escape_string($_POST['report_date']);
    $student = $conn->real_escape_string($_POST['student_name']);
    $location = $conn->real_escape_string($_POST['location']);
    $violation = $conn->real_escape_string($_POST['violation']);
    $time = $conn->real_escape_string($_POST['report_time']);
    $officer = $conn->real_escape_string($_POST['officer_name']);

    // Determine which page to add to
    $target_page = $current_page;
    if (count($_SESSION['log_print_queue'][$current_page]) >= $MAX_LOG_ROWS) {
        // Current page is full – create a new page and switch to it
        $_SESSION['log_print_queue'][] = [];
        $target_page = count($_SESSION['log_print_queue']) - 1;
        $_SESSION['current_page'] = $target_page;
        $current_page = $target_page; // update local variable
        // Set the officer name for the new page (from form)
        $_SESSION['current_officer'] = $officer;
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO violator_logs (report_date, student_name, location, violation, report_time, officer_name) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssss", $date, $student, $location, $violation, $time, $officer);
        if ($stmt->execute()) {
            // Add to queue on the determined page
            $_SESSION['log_print_queue'][$target_page][] = [
                'date' => $date,
                'student' => $student,
                'location' => $location,
                'violation' => $violation,
                'time' => $time
            ];

            // AUTO-PRINT TRIGGER: If this entry just filled the page, trigger the print popup automatically
            if (count($_SESSION['log_print_queue'][$target_page]) >= $MAX_LOG_ROWS) {
                $_SESSION['auto_print'] = true;
            }

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

// HANDLE: REPRINT (Add existing record to queue)
if (isset($_GET['reprint_id'])) {
    $id = intval($_GET['reprint_id']);
    $res = $conn->query("SELECT * FROM violator_logs WHERE id = $id");
    if ($row = $res->fetch_assoc()) {
        // Prepare queue entry
        $entry = [
            'date' => $row['report_date'],
            'student' => $row['student_name'],
            'location' => $row['location'],
            'violation' => $row['violation'],
            'time' => $row['report_time']
        ];

        // Determine which page to add to (current page if not full, else new page)
        $page_to_add = $current_page;
        if (count($_SESSION['log_print_queue'][$current_page]) >= $MAX_LOG_ROWS) {
            // Current page is full, create a new page
            $_SESSION['log_print_queue'][] = [];
            $page_to_add = count($_SESSION['log_print_queue']) - 1;
            $_SESSION['current_page'] = $page_to_add; // Switch to the new page
            $current_page = $page_to_add; // update local variable
        }
        $_SESSION['log_print_queue'][$page_to_add][] = $entry;

        // AUTO-PRINT TRIGGER: If reprint filled the page
        if (count($_SESSION['log_print_queue'][$page_to_add]) >= $MAX_LOG_ROWS) {
            $_SESSION['auto_print'] = true; 
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// HANDLE: EDIT (load data into form) - done via JavaScript, no server action needed

// HANDLE: NEW SHEET (manual)
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
        $current_page = $page_num;
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

// --- SEARCH LOGIC ---
$search_term = "";
$where_clause = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = $conn->real_escape_string($_GET['search']);
    $where_clause = "WHERE student_name LIKE '%$search_term%' OR location LIKE '%$search_term%' OR violation LIKE '%$search_term%'";
}

// Fetch Records with Search Integration
$recent_logs = $conn->query("SELECT * FROM violator_logs $where_clause ORDER BY id DESC LIMIT 10");
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
            overflow: visible;
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
            text-decoration: none;
            color: var(--text-main);
        }

        .page-tab:hover {
            background: var(--accent);
            color: white;
            text-decoration: none;
        }

        .page-tab.active {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            border-color: #224abe;
        }

        /* ========== HEADER STYLES ========== */
        .new-header-wrapper {
            position: relative;
            width: calc(100% + 1in);
            margin-left: -0.5in;
            margin-right: -0.5in;
            margin-top: 0.2in;
            height: 1.5in;
            margin-bottom: 10px;
        }

        .fading-bar {
            position: absolute;
            bottom: 20px;
            left: 0;
            width: 100%;
            height: 40px;
            background:
                linear-gradient(to right, #c99800 0%, #c99800 95%, #ffffff 100%) left bottom / 100% 5px no-repeat,
                linear-gradient(to right, #fbc600 0%, #fbc600 30%, #ffffff 55%) left top / 100% calc(100% - 5px) no-repeat;
            z-index: 1;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .header-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            height: 100%;
            padding: 0 0.5in;
        }

        .new-header-logo {
            width: 165px;
            height: auto;
            margin-right: 5px;
            flex-shrink: 0;
            object-fit: contain;
        }

        .text-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            height: 100px;
            padding-bottom: 5px;
        }

        .new-header-title {
            color: #002b7f;
            font-family: "Old English Text MT", "Engravers Old English", "UnifrakturMaguntia", serif;
            font-size: 32pt;
            letter-spacing: 0px;
            margin: 0;
            line-height: 1;
        }

        .divider-line {
            height: 2px;
            background: linear-gradient(to right, #002b7f 0%, #002b7f 18%, rgba(0, 43, 127, 0.25) 24%, rgba(0, 43, 127, 0.25) 75%, #002b7f 80%, #002b7f 100%);
            width: 100%;
            margin-top: 2px;
            margin-bottom: 4px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .details {
            text-align: center;
            margin-left: 210px;
            color: #000000;
            font-size: 9pt;
            line-height: 1.2;
            font-family: Arial, sans-serif;
            
        }

        /* Form Sub-Header (SAPD) */
        .division-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
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

        /* Sheet Top Row for Safety Officer */
        .sheet-top-row {
            display: flex;
            justify-content: flex-start;
            padding-bottom: 5px;
            font-weight: bold;
            font-size: 10pt;
            z-index: 60;
            position: relative;
        }

        /* --- TABLE (PORTRAIT) --- */
        .log-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid black;
            table-layout: fixed;
            margin-top: 5px;
        }

        .log-table th,
        .log-table td {
            border: 1px solid black;
            padding: 4px 6px;
            font-size: 9pt;
            height: 24px;
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
            width: 28%;
        }

        .col-loc {
            width: 18%;
            text-align: center;
        }

        .col-viol {
            width: 28%;
        }

        .col-time {
            width: 14%;
            text-align: center;
        }

        /* --- PRINT AREA CONTAINER (PORTRAIT LEGAL) --- */
        .print-area-container {
            width: 8.5in !important;
            height: 14in !important;
            min-height: 14in !important;
            flex-shrink: 0 !important;
            background: white;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            padding: 0.3in 0.5in 0.3in 0.5in;
            position: relative;
            transform: scale(0.60);
            transform-origin: top center;
            display: flex;
            flex-direction: column;
            color: black;
            box-sizing: border-box;
            overflow: hidden;
            margin: 0 auto;
            margin-bottom: -5.5in;
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

        .sheet-footer {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 10px;
            padding-bottom: 10px;
            font-size: 10pt;
        }

        .officer-line {
            border-bottom: 1px solid black;
            min-width: 250px;
            display: inline-block;
        }

        /* --- PRINT MEDIA (PORTRAIT LEGAL) --- */
        @media print {
            @page {
                size: 8.5in 14in portrait;
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
                height: 14in !important;
                min-height: 14in !important;
                box-shadow: none !important;
                padding: 0.3in 0.5in 0.3in 0.5in !important;
                margin: 0 !important;
                border: none !important;
                display: block !important;
                page-break-after: always;
            }

            .print-area-container:last-child {
                page-break-after: avoid;
            }

            .new-header-wrapper {
                margin-top: 0.2in !important;
                margin-left: -0.5in !important;
                margin-right: -0.5in !important;
                padding-top: 0 !important;
            }

            .fading-bar,
            .divider-line {
                print-color-adjust: exact !important;
                -webkit-print-color-adjust: exact !important;
            }

            .new-header-logo {
                width: 165px;
            }

            .new-header-title,
            .divider-line,
            .details {
                color: black !important;
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

        /* Left panel input overrides to match employee form white text */
        .left-panel .form-control,
        .left-panel .time-input,
        .left-panel .date-input {
            background-color: #1f2f4e !important;
            color: #ffffff !important;
            border-color: #2c3e50 !important;
        }

        .left-panel .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7) !important;
        }

        .left-panel .time-date-label {
            color: #ffffff !important;
        }

        .time-input,
        .date-input {
            background-color: var(--input-bg) !important;
            border: 1px solid var(--border) !important;
            padding: 12px !important;
            margin-bottom: 10px !important;
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
                    <div class="badge-queue">QUEUE: <?php echo $current_page_count; ?>/<?php echo $MAX_LOG_ROWS; ?>
                    </div>
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

            <div class="page-tabs">
                <?php for ($i = 0; $i < $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="page-tab <?php echo $i == $current_page ? 'active' : ''; ?>">
                        Page <?php echo $i + 1; ?> (<?php echo count($_SESSION['log_print_queue'][$i]); ?>)
                    </a>
                <?php endfor; ?>
            </div>

            <form method="POST" id="logForm">
                <input type="text" name="student_name" id="in_student" class="form-control"
                    placeholder="Name of Violator" required oninput="updatePreview()">

                <input type="text" name="location" id="in_location" class="form-control" placeholder="Location" required
                    oninput="updatePreview()">

                <input type="text" name="violation" id="in_violation" class="form-control" placeholder="Violation"
                    required oninput="updatePreview()">

                <div class="row">
                    <div class="col-6">
                        <label class="time-date-label">Time</label>
                        <input type="time" name="report_time" id="in_time" class="form-control time-input" required
                            onchange="updatePreview()" oninput="updatePreview()">
                    </div>
                    <div class="col-6">
                        <label class="time-date-label">Date</label>
                        <input type="date" name="report_date" id="in_date" class="form-control date-input" required
                            onchange="updatePreview()" oninput="updatePreview()">
                    </div>
                </div>

                <input type="text" name="officer_name" id="in_officer" class="form-control"
                    placeholder="Safety Officer (Signatory)" required oninput="updatePreview()">

                <div class="d-flex gap-2">
                    <button type="submit" name="add_to_log" class="btn btn-primary flex-grow-1 fw-bold py-3 mt-2">
                        <i class="fa fa-plus-circle me-2"></i> ADD TO LOG SHEET
                    </button>
                    <button type="button" onclick="resetForm()" class="btn btn-warning fw-bold py-3 mt-2" id="resetBtn"
                        title="Clear form to start new">
                        <i class="fa fa-rotate-right"></i>
                    </button>
                </div>
            </form>

            <hr class="border-secondary my-4">

            <div class="mb-2">
                <form method="POST" class="m-0">
                    <button type="submit" name="new_sheet"
                        class="btn btn-purple w-100 fw-bold py-2 btn-new-sheet d-flex align-items-center justify-content-center">
                        <i class="fa fa-plus me-2"></i> NEW SHEET (PAGE <?php echo $total_pages + 1; ?>)
                    </button>
                </form>
            </div>

            <div class="d-flex gap-2 mb-2">
                <button type="button" onclick="printQueue()"
                    class="btn btn-success flex-grow-1 fw-bold py-2 d-flex align-items-center justify-content-center"
                    <?php echo !$is_page_full ? 'disabled title="Sheet must be full (37 items) to print"' : ''; ?>>
                    <i class="fa fa-print me-2"></i> PRINT QUEUE
                    <?php echo $total_queue_items > 0 ? "({$total_queue_items}/{$MAX_LOG_ROWS})" : ""; ?>
                </button>

                <button type="button" onclick="printBlank()"
                    class="btn btn-secondary flex-grow-1 fw-bold py-2 d-flex align-items-center justify-content-center text-white">
                    <i class="fa fa-file me-2"></i> BLANK FORM
                </button>
            </div>

            <?php if ($total_queue_items > 0): ?>
                <form method="POST" class="m-0">
                    <button type="submit" name="clear_all_queues"
                        class="btn btn-danger w-100 fw-bold py-2 d-flex align-items-center justify-content-center"
                        onclick="return confirm('Clear ALL pages? This cannot be undone!')">
                        <i class="fa fa-trash me-2"></i> CLEAR QUEUE
                    </button>
                </form>
            <?php endif; ?>

        </div>

        <div class="right-panel">
            <div class="panel-header w-100 border-bottom pb-3 mb-4" style="border-color: var(--border)!important;">
                <div class="panel-title"><i class="fa fa-eye"></i> PORTRAIT LEGAL PREVIEW (8.5" x 14")</div>
                <div class="badge-page">Page <?php echo $current_page + 1; ?></div>
            </div>

            <div class="print-area-container" id="print-area">
                <div class="new-header-wrapper">
                    <div class="fading-bar"></div>
                    <div class="header-content">
                        <img src="Logo-hcc.png" alt="HCC Logo" class="new-header-logo">
                        <div class="text-content">
                            <div class="new-header-title">Holy Cross Colleges, Inc.</div>
                            <div class="divider-line"></div>
                            <div class="details">
                                Holy Cross Colleges, Inc. Sta. Lucia, Sta. Ana, Pampanga 2022<br>
                                www.holycrosscollegesinc.com
                            </div>
                        </div>
                    </div>
                </div>

                <div class="division-header">
                    <img src="background.png" class="sapd-logo" alt="SAPD Logo">
                    <div class="division-title">
                        <h2>SAFETY AND PROTECTION DIVISION</h2>
                        <h3>VIOLATOR'S REPORT</h3>
                    </div>
                </div>

                <div class="sheet-top-row">
                    SAFETY OFFICER: <span class="officer-line" style="text-align: center; padding: 0 10px;">
                        <span id="p_officer"><?php echo strtoupper($_SESSION['current_officer']); ?></span>
                    </span>
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
                            <td class="col-name" id="p_name" style="text-align: left; padding-left: 10px;"></td>
                            <td class="col-loc" id="p_loc"></td>
                            <td class="col-viol" id="p_viol" style="text-align: left; padding-left: 10px;"></td>
                            <td class="col-time" id="p_time"></td>
                        </tr>

                        <?php
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
                    <div class="left" style="margin-top: 15px; font-weight: bold;">
                        PAUL JEFFREY T. LANSANGAN, SO3
                    </div>
                    <div class="right"></div>
                </div>
            </div>

            <div class="print-area-container" id="print-blank-area">
                <div class="new-header-wrapper">
                    <div class="fading-bar"></div>
                    <div class="header-content">
                        <img src="Logo-hcc.png" alt="HCC Logo" class="new-header-logo">
                        <div class="text-content">
                            <div class="new-header-title">Holy Cross Colleges, Inc.</div>
                            <div class="divider-line"></div>
                            <div class="details">
                                Holy Cross Colleges, Inc. Sta. Lucia, Sta. Ana, Pampanga 2022<br>
                                www.holycrosscollegesinc.com
                            </div>
                        </div>
                    </div>
                </div>

                <div class="division-header">
                    <img src="background.png" class="sapd-logo" alt="SAPD Logo">
                    <div class="division-title">
                        <h2>SAFETY AND PROTECTION DIVISION</h2>
                        <h3>VIOLATOR'S REPORT</h3>
                    </div>
                </div>

                <div class="sheet-top-row">
                    SAFETY OFFICER: <span class="officer-line" style="width: 250px;">
                        &nbsp;
                    </span>
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
                    <div class="left" style="margin-top: 15px; font-weight: bold;">
                        PAUL JEFFREY T. LANSANGAN, SO3
                    </div>
                    <div class="right"></div>
                </div>
            </div>

        </div>
    </div>

    <div class="bottom-panel mx-4 mt-2">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold m-0"><i class="fa fa-database me-2"></i> RECENT LOGS</h5>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-dark">Total: <?php echo $total_count; ?></span>

                <form method="GET" class="d-flex gap-0" style="width: 300px;">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search Name/Location..."
                            value="<?php echo htmlspecialchars($search_term); ?>" style="margin-bottom: 0;">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i></button>
                        <?php if ($search_term): ?><a href="?" class="btn btn-secondary"><i
                                    class="fa fa-times"></i></a><?php endif; ?>
                    </div>
                </form>
            </div>
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
                        <th class="text-center">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_logs && $recent_logs->num_rows > 0): ?>
                        <?php while ($row = $recent_logs->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['violation']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><?php echo $row['report_date']; ?></td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-sm btn-info text-white"
                                            onclick='showViewModal(<?php echo json_encode($row); ?>)' title="View">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning text-white"
                                            onclick='editRecord(<?php echo json_encode($row); ?>)' title="Edit">
                                            <i class="fa fa-pencil-alt"></i>
                                        </button>
                                        <a href="?reprint_id=<?php echo $row['id']; ?>"
                                            class="btn btn-sm btn-primary text-white" title="Reprint">
                                            <i class="fa fa-print"></i>
                                        </a>
                                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Delete this record?')"><i class="fa fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="fa fa-database fa-2x mb-3"></i><br>
                                No records found.
                                <?php echo $search_term ? 'Try a different search.' : ''; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background-color: var(--panel-bg); color: var(--text-main);">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalLabel">Violator Log Details (Read-Only)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Student Name:</div>
                        <div class="col-md-9" id="view_student"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Location:</div>
                        <div class="col-md-9" id="view_location"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Violation:</div>
                        <div class="col-md-9" id="view_violation"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Date:</div>
                        <div class="col-md-9" id="view_date"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Time:</div>
                        <div class="col-md-9" id="view_time"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Safety Officer:</div>
                        <div class="col-md-9" id="view_officer"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
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
        }

        const savedTheme = localStorage.getItem('appTheme') || 'dark';
        if (savedTheme === 'light') {
            document.body.classList.add('light-mode');
            document.getElementById('themeBtn').innerHTML = '<i class="fa fa-sun"></i>';
        }

        // View Modal (Read-Only)
        function showViewModal(data) {
            document.getElementById('view_student').innerText = data.student_name || '';
            document.getElementById('view_location').innerText = data.location || '';
            document.getElementById('view_violation').innerText = data.violation || '';
            document.getElementById('view_date').innerText = data.report_date || '';
            let timeVal = data.report_time;
            if (timeVal) {
                let [h, m] = timeVal.split(':');
                let ampm = 'AM';
                if (h >= 12) { ampm = 'PM'; if (h > 12) h = h - 12; }
                if (h == 0) h = 12;
                timeVal = `${h}:${m} ${ampm}`;
            }
            document.getElementById('view_time').innerText = timeVal || '';
            document.getElementById('view_officer').innerText = data.officer_name || '';
            var modal = new bootstrap.Modal(document.getElementById('viewModal'));
            modal.show();
        }

        // Edit: load record into the form for modification
        function editRecord(data) {
            document.getElementById('in_student').value = data.student_name || '';
            document.getElementById('in_location').value = data.location || '';
            document.getElementById('in_violation').value = data.violation || '';
            document.getElementById('in_date').value = data.report_date || '';
            document.getElementById('in_time').value = data.report_time || '';
            document.getElementById('in_officer').value = data.officer_name || '';
            updatePreview();
            window.scrollTo({ top: 0, behavior: 'smooth' });
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
                    if (h >= 12) { ampm = 'PM'; if (h > 12) h = h - 12; }
                    if (h == 0) h = 12;
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

        // Auto print if reprint was triggered OR if auto-print flag is set from the server
        <?php if (isset($_SESSION['auto_print']) && $_SESSION['auto_print'] === true): ?>
            window.addEventListener('load', function () {
                printQueue();
            });
            <?php unset($_SESSION['auto_print']); ?>
        <?php endif; ?>
    </script>

</body>

</html>