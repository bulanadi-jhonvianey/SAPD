<?php
// --- student_and_employee_form.php ---
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

// 1. Create Connection
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Create Database
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
$conn->select_db($dbname);

// Table Setup
$table_sql = "CREATE TABLE IF NOT EXISTS parking_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            applicant_type VARCHAR(50) DEFAULT 'EMPLOYEE',
            applicant_name VARCHAR(255), department VARCHAR(100), address TEXT,
            contact_number VARCHAR(50), license_no VARCHAR(50), email VARCHAR(100), fb_account VARCHAR(100),
            vehicle_type VARCHAR(50), vehicle_brand VARCHAR(50), vehicle_color VARCHAR(50), or_no VARCHAR(50), cr_no VARCHAR(50),
            emerg_name VARCHAR(255), emerg_address TEXT, emerg_relation VARCHAR(100), emerg_contact VARCHAR(50),
            checklist_data TEXT DEFAULT NULL,
            secondary_vehicles TEXT DEFAULT NULL,
            violation_data TEXT DEFAULT NULL,
            image_paths TEXT DEFAULT NULL, 
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
$conn->query($table_sql);

// Auto-Repair Columns
$cols = ['applicant_type', 'applicant_name', 'department', 'address', 'contact_number', 'license_no', 'email', 'fb_account', 'vehicle_type', 'vehicle_brand', 'vehicle_color', 'or_no', 'cr_no', 'emerg_name', 'emerg_address', 'emerg_relation', 'emerg_contact', 'image_paths', 'checklist_data', 'secondary_vehicles', 'violation_data'];
foreach ($cols as $c) {
    $check_col = $conn->query("SHOW COLUMNS FROM parking_applications LIKE '$c'");
    if ($check_col->num_rows == 0) {
        $conn->query("ALTER TABLE parking_applications ADD $c VARCHAR(255) DEFAULT ''");
    }
}

// Session Queue
if (!isset($_SESSION['parking_print_queue'])) {
    $_SESSION['parking_print_queue'] = [];
}

// Upload Directory
$upload_dir = "uploads/parking/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// --- FORM HANDLERS ---
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $d = [];
    $standard_fields = ['applicant_type', 'applicant_name', 'department', 'address', 'contact_number', 'license_no', 'email', 'fb_account', 'vehicle_type', 'vehicle_brand', 'vehicle_color', 'or_no', 'cr_no', 'emerg_name', 'emerg_address', 'emerg_relation', 'emerg_contact'];

    foreach ($standard_fields as $field) {
        $d[$field] = isset($_POST[$field]) ? $conn->real_escape_string($_POST[$field]) : '';
    }

    // Capture Checklist Data (JSON)
    $checklist = [];
    foreach ($_POST as $key => $val) {
        if (strpos($key, 'chk_') === 0) {
            $checklist[$key] = $val;
        }
    }
    $checklist_json = json_encode($checklist);

    // Capture Secondary Vehicles (JSON)
    $sec_vehicles = [];
    if (isset($_POST['sec_v_type'])) {
        for ($i = 0; $i < count($_POST['sec_v_type']); $i++) {
            if (!empty($_POST['sec_v_type'][$i])) {
                $sec_vehicles[] = [
                    'type' => $_POST['sec_v_type'][$i],
                    'brand' => $_POST['sec_v_brand'][$i],
                    'color' => $_POST['sec_v_color'][$i],
                    'or' => $_POST['sec_v_or'][$i],
                    'cr' => $_POST['sec_v_cr'][$i]
                ];
            }
        }
    }
    $sec_vehicles_json = json_encode($sec_vehicles);

    // Capture Violation Data (JSON)
    $violations = [];
    if (isset($_POST['vio_date'])) {
        for ($i = 0; $i < count($_POST['vio_date']); $i++) {
            if (!empty($_POST['vio_date'][$i]) || !empty($_POST['vio_desc'][$i])) {
                $violations[] = [
                    'date' => $_POST['vio_date'][$i],
                    'time' => $_POST['vio_time'][$i],
                    'loc' => $_POST['vio_loc'][$i],
                    'desc' => $_POST['vio_desc'][$i],
                    'action' => $_POST['vio_action'][$i],
                    'officer' => $_POST['vio_officer'][$i]
                ];
            }
        }
    }
    $violation_json = json_encode($violations);

    // Handle Images
    $image_paths_json = null;
    $uploaded_files = [];
    if (isset($_FILES['vehicle_images']) && !empty($_FILES['vehicle_images']['name'][0])) {
        $total = count($_FILES['vehicle_images']['name']);
        for ($i = 0; $i < $total; $i++) {
            if ($_FILES['vehicle_images']['error'][$i] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['vehicle_images']['name'][$i], PATHINFO_EXTENSION);
                $new_name = uniqid('park_') . "_$i." . $ext;
                if (move_uploaded_file($_FILES['vehicle_images']['tmp_name'][$i], $upload_dir . $new_name)) {
                    $uploaded_files[] = $upload_dir . $new_name;
                }
            }
        }
    }
    if (!empty($uploaded_files))
        $image_paths_json = json_encode($uploaded_files);

    $sql = "INSERT INTO parking_applications (applicant_type, applicant_name, department, address, contact_number, license_no, email, fb_account, vehicle_type, vehicle_brand, vehicle_color, or_no, cr_no, emerg_name, emerg_address, emerg_relation, emerg_contact, checklist_data, secondary_vehicles, violation_data, image_paths) VALUES ('{$d['applicant_type']}', '{$d['applicant_name']}', '{$d['department']}', '{$d['address']}', '{$d['contact_number']}', '{$d['license_no']}', '{$d['email']}', '{$d['fb_account']}', '{$d['vehicle_type']}', '{$d['vehicle_brand']}', '{$d['vehicle_color']}', '{$d['or_no']}', '{$d['cr_no']}', '{$d['emerg_name']}', '{$d['emerg_address']}', '{$d['emerg_relation']}', '{$d['emerg_contact']}', '$checklist_json', '$sec_vehicles_json', '$violation_json', '$image_paths_json')";

    if ($conn->query($sql)) {
        $_SESSION['parking_print_queue'][] = array_merge($d, $checklist, ['secondary_vehicles' => $sec_vehicles_json, 'violation_data' => $violation_json, 'image_paths' => $uploaded_files]);
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit();
    } else {
        $error_msg = "Error: " . $conn->error;
    }
}

if (isset($_GET['delete_id'])) {
    $conn->query("DELETE FROM parking_applications WHERE id = " . intval($_GET['delete_id']));
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
if (isset($_POST['clear_queue'])) {
    $_SESSION['parking_print_queue'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- SEARCH LOGIC ---
$search_term = "";
$where_clause = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = $conn->real_escape_string($_GET['search']);
    $where_clause = "WHERE applicant_name LIKE '%$search_term%' OR department LIKE '%$search_term%'";
}

$recent_reports = $conn->query("SELECT * FROM parking_applications $where_clause ORDER BY id DESC LIMIT 10");
$total_count = $conn->query("SELECT COUNT(*) as total FROM parking_applications")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee & Student Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
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

        /* Dropdown Button Custom */
        .btn-collapse {
            background-color: var(--input-bg);
            color: var(--text-main);
            border: 1px solid var(--border);
            text-align: left;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 12px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .btn-collapse:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .btn-collapse[aria-expanded="true"] {
            border-color: var(--accent);
            background-color: rgba(0, 123, 255, 0.1);
        }

        .btn-collapse i.chevron {
            transition: transform 0.3s ease;
        }

        .btn-collapse[aria-expanded="true"] i.chevron {
            transform: rotate(180deg);
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

        .preview-track {
            display: flex;
            flex-direction: row;
            width: 100%;
            height: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            gap: 20px;
            padding-bottom: 20px;
            scroll-behavior: smooth;
        }

        .preview-track::-webkit-scrollbar {
            height: 12px;
        }

        .preview-track::-webkit-scrollbar-track {
            background: var(--input-bg);
            border-radius: 6px;
        }

        .preview-track::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 6px;
        }

        .form-slide {
            flex: 0 0 8.5in;
            height: 13in;
            position: relative;
            background: transparent;
            /* Scale down for preview only */
            transform: scale(0.6);
            transform-origin: top left;
            margin-right: -3in;
            /* Compensate for scale gap */
            margin-bottom: -5in;
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

        .form-control::placeholder {
            color: rgba(128, 128, 128, 0.7);
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

        .sec-vehicle-box {
            background-color: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 8px;
        }

        body.light-mode .sec-vehicle-box {
            background-color: rgba(0, 0, 0, 0.05);
        }

        /* --- FORM DESIGN (SCREEN & PRINT SHARED) --- */
        .hcc-form {
            width: 8.5in;
            height: 13in;
            /* Legal/Folio size */
            background: white;
            color: black;
            padding: 0.3in 0.5in;
            font-family: Arial, sans-serif;
            position: relative;
            box-sizing: border-box;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }

        /* HEADER LAYOUT FIX */
        .header-layout {
            position: relative;
            width: 100%;
            height: auto;
            margin-bottom: 10px;
            display: block;
        }

        .header-banner {
            width: 100%;
            height: auto;
            display: block;
            position: relative;
            z-index: 1;
        }

        .logo-left {
            width: 140px;
            position: absolute;
            top: 5px;
            left: 10px;
            z-index: 10;
        }

        .form-title {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 5px 0 5px 0;
            color: black;
            text-align: center;
        }

        .form-title h2 {
            font-family: "Times New Roman", Times, serif;
            font-weight: 900;
            font-size: 14pt;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-title h3 {
            font-family: Arial, sans-serif;
            font-weight: bold;
            font-size: 10pt;
            margin: 2px 0 0 0;
            text-transform: uppercase;
        }

        .employee-title {
            font-family: Arial, sans-serif;
            font-weight: 900;
            font-size: 20pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 5px 0 0 0;
        }

        .status-checkboxes {
            font-size: 10pt;
            margin-top: 2px;
        }

        .checkbox-box {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 1px solid black;
            margin-right: 5px;
            vertical-align: text-bottom;
            position: relative;
            top: 1px;
            text-align: center;
            line-height: 12px;
            font-size: 12px;
            font-weight: bold;
            color: black;
        }

        .checkbox-box.checked::after {
            content: "✔";
            position: absolute;
            left: 1px;
            top: -1px;
        }

        .file-info {
            font-size: 10pt;
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            margin-bottom: 2px;
            font-weight: bold;
            font-family: Arial, sans-serif;
        }

        .data-grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            border: 1px solid black;
            /* CHANGED: Added margin-top to lower table slightly */
            margin-top: 10px;
        }

        .data-grid td {
            border: 1px solid black;
            padding: 6px 5px;
            vertical-align: middle;
            height: 20px;
            color: black;
            font-family: Arial, sans-serif;
        }

        .label {
            font-weight: bold;
            width: 18%;
            background-color: transparent;
            text-transform: uppercase;
            font-size: 8pt;
            color: black;
        }

        /* Font fixed: Calibri + Mix Case */
        .value {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-weight: bold;
            color: #000;
            font-size: 11pt;
            width: 32%;
            text-transform: none;
            /* Mix Case */
        }

        .emerg-header {
            background-color: #d9d9d9;
            font-weight: bold;
            text-align: center;
            border: 1px solid black;
            border-bottom: none;
            font-size: 9pt;
            padding: 2px;
            margin-top: 5px;
            font-family: Arial, sans-serif;
        }

        .emerg-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            border: 1px solid black;
            border-top: none;
        }

        .emerg-table td {
            border: 1px solid black;
            padding: 6px 5px;
            vertical-align: top;
            color: black;
            font-family: Arial, sans-serif;
        }

        .emerg-label {
            font-size: 9pt;
            font-weight: bold;
            display: inline-block;
            width: 60px;
        }

        /* Font fixed: Calibri + Mix Case */
        .emerg-val {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-weight: bold;
            border-bottom: 1px solid black;
            display: inline-block;
            width: calc(100% - 70px);
            text-transform: none;
            /* Mix Case */
            font-size: 11pt;
        }

        .mv-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid black;
            margin-top: 5px;
            font-size: 8pt;
            text-align: center;
        }

        .mv-table th {
            border: 1px solid black;
            background: white;
            font-weight: bold;
            padding: 4px;
            text-transform: uppercase;
            vertical-align: middle;
            line-height: 1.1;
            height: 25px;
            color: black;
            font-family: Arial, sans-serif;
        }

        .mv-table td {
            border: 1px solid black;
            height: 22px;
            color: black;
            font-family: "Courier New", monospace;
            font-weight: bold;
            text-transform: uppercase;
        }

        .docs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .docs-table td {
            vertical-align: top;
            color: black;
            font-family: Arial, sans-serif;
        }

        .checklist {
            font-size: 9pt;
            line-height: 1.3;
        }

        .mb-1 {
            margin-bottom: 2px;
        }

        .id-cell {
            width: 2.1in;
            text-align: right;
            vertical-align: top;
            padding-right: 5px;
        }

        .id-box {
            width: 1.8in;
            height: 1.8in;
            border: 1px solid black;
            display: flex;
            align-items: center;
            justify-content: center;
            float: right;
            margin-left: auto;
            background: white;
        }

        .sig-table {
            width: 100%;
            margin-top: 10px;
            font-size: 10pt;
            font-family: Arial, sans-serif;
            border-collapse: collapse;
        }

        .sig-table td {
            vertical-align: top;
            padding-bottom: 5px;
        }

        /* VIOLATION TABLE (BACK) */
        .violation-table th,
        .violation-table td {
            border: 1px solid black;
            padding: 4px;
            text-align: center;
            font-family: Arial, sans-serif;
            font-size: 8pt;
        }

        .violation-table th {
            background-color: white;
            font-weight: bold;
            vertical-align: middle;
        }

        .violation-table td {
            font-family: "Courier New", monospace;
            font-weight: bold;
            color: black;
            height: 20px;
        }

        .waiver-text {
            font-size: 9pt;
            text-align: justify;
            margin-top: 10px;
            line-height: 1.2;
        }

        .waiver-text ol {
            padding-left: 20px;
            margin-top: 5px;
        }

        .waiver-text li {
            margin-bottom: 2px;
        }

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
            color: var(--text-main) !important;
            border-color: var(--border);
        }

        body.light-mode .table-custom td {
            color: #212529 !important;
        }

        @media screen {

            #print-area,
            #print-blank-area {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                overflow: hidden !important;
            }
        }

        /* --- PRINT SETTINGS --- */
        @media print {
            @page {
                size: 8.5in 13in;
                margin: 0;
            }

            body {
                background: white !important;
                color: black !important;
                margin: 0 !important;
                padding: 0 !important;
                zoom: 100%;
            }

            .navbar,
            .main-container,
            .bottom-panel,
            .btn,
            .d-print-none,
            .alert {
                display: none !important;
            }

            #print-area,
            #print-blank-area {
                display: none !important;
                width: 100% !important;
                height: auto !important;
                overflow: visible !important;
            }

            body.printing-mode-queue #print-area {
                display: block !important;
            }

            body.printing-mode-blank #print-blank-area {
                display: block !important;
            }

            .hcc-form {
                width: 100% !important;
                height: 100vh !important;
                margin: 0 !important;
                padding: 0.4in !important;
                box-shadow: none !important;
                transform: none !important;
                page-break-after: always !important;
                overflow: hidden !important;
                display: block !important;
                position: relative !important;
                left: 0 !important;
                top: 0 !important;
            }

            .hcc-form:last-child {
                page-break-after: auto !important;
            }

            .header-layout {
                margin-top: 0 !important;
                margin-bottom: 10px !important;
            }

            .header-banner {
                width: 100% !important;
            }

            .logo-left {
                width: 120px !important;
                top: 5px !important;
                left: 0 !important;
            }

            .form-title h2 {
                font-size: 13pt !important;
            }

            .employee-title {
                font-size: 18pt !important;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>

<body>

    <div class="navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-secondary fw-bold"><i class="fa fa-arrow-left me-2"></i> Back</a>
            <h4 class="m-0 fw-bold text-white">Student & Employee Parking</h4>
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
                <div class="panel-title"><i class="fa fa-pencil-alt"></i> FILL APPLICATION</div>
                <div class="badge-queue">QUEUE: <?php echo count($_SESSION['parking_print_queue']); ?></div>
            </div>

            <?php if (!empty($success_msg))
                echo "<div class='alert alert-success alert-dismissible fade show'>$success_msg<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>"; ?>
            <?php if (!empty($error_msg))
                echo "<div class='alert alert-danger alert-dismissible fade show'>$error_msg<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>"; ?>

            <form method="POST" enctype="multipart/form-data" id="appForm">
                <label class="small text-secondary fw-bold mb-1">APPLICATION STATUS</label>
                <div class="d-flex gap-3 mb-2">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="chk_approved"
                            id="in_chk_approved" value="1" onchange="syncStatus('approved')"><label
                            class="form-check-label" for="in_chk_approved">Approved</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="chk_disapproved"
                            id="in_chk_disapproved" value="1" onchange="syncStatus('disapproved')"><label
                            class="form-check-label" for="in_chk_disapproved">Disapproved</label></div>
                </div>

                <label class="small text-secondary fw-bold mb-1">APPLICATION TYPE</label>
                <select name="applicant_type" id="in_type" class="form-select" onchange="updatePreview()">
                    <option value="EMPLOYEE">EMPLOYEE</option>
                    <option value="STUDENT">STUDENT</option>
                </select>

                <label class="small text-secondary fw-bold mb-1">APPLICANT DETAILS</label>
                <input type="text" name="applicant_name" id="in_name" class="form-control"
                    placeholder="Name (Last, First, MI)" required oninput="updatePreview()">
                <div class="row g-2">
                    <div class="col-6"><input type="text" name="department" id="in_dept" class="form-control"
                            placeholder="Department" required oninput="updatePreview()"></div>
                    <div class="col-6"><input type="text" name="contact_number" id="in_cel" class="form-control"
                            placeholder="Cel No." required oninput="updatePreview()"></div>
                </div>
                <input type="text" name="address" id="in_address" class="form-control" placeholder="Address" required
                    oninput="updatePreview()">

                <div class="row g-2">
                    <div class="col-6"><input type="text" name="license_no" id="in_license" class="form-control"
                            placeholder="License #" oninput="updatePreview()"></div>
                    <div class="col-6"><input type="email" name="email" id="in_email" class="form-control"
                            placeholder="Email" oninput="updatePreview()"></div>
                </div>

                <label class="small text-secondary fw-bold mb-1 mt-2">VEHICLE INFO (Main)</label>
                <div class="row g-2">
                    <div class="col-6"><input type="text" name="vehicle_type" id="in_vtype" class="form-control"
                            placeholder="Type (Car/Motor)" oninput="updatePreview()"></div>
                    <div class="col-6"><input type="text" name="vehicle_brand" id="in_vbrand" class="form-control"
                            placeholder="Brand" oninput="updatePreview()"></div>
                </div>
                <div class="row g-2">
                    <div class="col-6"><input type="text" name="vehicle_color" id="in_vcolor" class="form-control"
                            placeholder="Color" oninput="updatePreview()"></div>
                    <div class="col-6"><input type="text" name="or_no" id="in_or" class="form-control"
                            placeholder="OR #" oninput="updatePreview()"></div>
                </div>
                <div class="row g-2">
                    <div class="col-6"><input type="text" name="cr_no" id="in_cr" class="form-control"
                            placeholder="CR #" oninput="updatePreview()"></div>
                    <div class="col-6"><input type="text" name="fb_account" id="in_fb" class="form-control"
                            placeholder="FB Account" oninput="updatePreview()"></div>
                </div>

                <div class="mt-3 mb-2">
                    <button class="btn btn-collapse" type="button" data-bs-toggle="collapse"
                        data-bs-target="#secVehiclesCollapse" aria-expanded="false" aria-controls="secVehiclesCollapse">
                        <span><i class="fa fa-car me-2"></i> Additional Vehicles (Optional)</span>
                        <i class="fa fa-chevron-down chevron"></i>
                    </button>

                    <div class="collapse mt-2" id="secVehiclesCollapse">
                        <div id="sec_vehicle_container">
                            <div class="sec-vehicle-box">
                                <small class="text-secondary d-block mb-1">Secondary Vehicle 1</small>
                                <div class="row g-2 mb-2">
                                    <div class="col-4"><input type="text" name="sec_v_type[]" id="in_sec_type_0"
                                            class="form-control form-control-sm" placeholder="Type"
                                            oninput="updatePreview()"></div>
                                    <div class="col-4"><input type="text" name="sec_v_brand[]" id="in_sec_brand_0"
                                            class="form-control form-control-sm" placeholder="Brand"
                                            oninput="updatePreview()"></div>
                                    <div class="col-4"><input type="text" name="sec_v_color[]" id="in_sec_color_0"
                                            class="form-control form-control-sm" placeholder="Color"
                                            oninput="updatePreview()"></div>
                                    <div class="col-6"><input type="text" name="sec_v_or[]" id="in_sec_or_0"
                                            class="form-control form-control-sm" placeholder="OR #"
                                            oninput="updatePreview()"></div>
                                    <div class="col-6"><input type="text" name="sec_v_cr[]" id="in_sec_cr_0"
                                            class="form-control form-control-sm" placeholder="CR #"
                                            oninput="updatePreview()"></div>
                                </div>
                            </div>
                            <div class="sec-vehicle-box">
                                <small class="text-secondary d-block mb-1">Secondary Vehicle 2</small>
                                <div class="row g-2 mb-2">
                                    <div class="col-4"><input type="text" name="sec_v_type[]" id="in_sec_type_1"
                                            class="form-control form-control-sm" placeholder="Type"
                                            oninput="updatePreview()"></div>
                                    <div class="col-4"><input type="text" name="sec_v_brand[]" id="in_sec_brand_1"
                                            class="form-control form-control-sm" placeholder="Brand"
                                            oninput="updatePreview()"></div>
                                    <div class="col-4"><input type="text" name="sec_v_color[]" id="in_sec_color_1"
                                            class="form-control form-control-sm" placeholder="Color"
                                            oninput="updatePreview()"></div>
                                    <div class="col-6"><input type="text" name="sec_v_or[]" id="in_sec_or_1"
                                            class="form-control form-control-sm" placeholder="OR #"
                                            oninput="updatePreview()"></div>
                                    <div class="col-6"><input type="text" name="sec_v_cr[]" id="in_sec_cr_1"
                                            class="form-control form-control-sm" placeholder="CR #"
                                            oninput="updatePreview()"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <label class="small text-secondary fw-bold mb-1 mt-2">EMERGENCY CONTACT</label>
                <input type="text" name="emerg_name" id="in_ename" class="form-control" placeholder="Name"
                    oninput="updatePreview()">
                <input type="text" name="emerg_address" id="in_eaddress" class="form-control" placeholder="Address"
                    oninput="updatePreview()">
                <div class="row g-2">
                    <div class="col-6"><input type="text" name="emerg_relation" id="in_erelation" class="form-control"
                            placeholder="Relation" oninput="updatePreview()"></div>
                    <div class="col-6"><input type="text" name="emerg_contact" id="in_econtact" class="form-control"
                            placeholder="Contact #" oninput="updatePreview()"></div>
                </div>

                <label class="small text-secondary fw-bold mb-1 mt-2">DOCUMENTS SUBMITTED</label>
                <div class="card p-2 mb-3" style="background-color: var(--input-bg); border: 1px solid var(--border);">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="chk_cr"
                                    id="in_chk_cr" value="1"
                                    onchange="syncPreviewCheck('in_chk_cr', 'view_chk_cr')"><label
                                    class="form-check-label small" for="in_chk_cr">Reg (CR)</label></div>
                        </div>
                        <div class="col-6">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="chk_or"
                                    id="in_chk_or" value="1"
                                    onchange="syncPreviewCheck('in_chk_or', 'view_chk_or')"><label
                                    class="form-check-label small" for="in_chk_or">Receipt (OR)</label></div>
                        </div>
                        <div class="col-12">
                            <div class="form-check"><input class="form-check-input" type="checkbox"
                                    name="chk_student_lic" id="in_chk_student_lic" value="1"
                                    onchange="syncPreviewCheck('in_chk_student_lic', 'view_chk_student_lic')"><label
                                    class="form-check-label small" for="in_chk_student_lic">Student Lic</label></div>
                        </div>
                        <div class="col-6">
                            <div class="form-check"><input class="form-check-input" type="checkbox"
                                    name="chk_nonpro_lic" id="in_chk_nonpro_lic" value="1"
                                    onchange="syncPreviewCheck('in_chk_nonpro_lic', 'view_chk_nonpro_lic')"><label
                                    class="form-check-label small" for="in_chk_nonpro_lic">Non-Pro</label></div>
                        </div>
                        <div class="col-6">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="chk_pro_lic"
                                    id="in_chk_pro_lic" value="1"
                                    onchange="syncPreviewCheck('in_chk_pro_lic', 'view_chk_pro_lic')"><label
                                    class="form-check-label small" for="in_chk_pro_lic">Professional</label></div>
                        </div>
                        <div class="col-6">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="chk_id_2x2"
                                    id="in_chk_id_2x2" value="1"
                                    onchange="syncPreviewCheck('in_chk_id_2x2', 'view_chk_id_2x2')"><label
                                    class="form-check-label small" for="in_chk_id_2x2">2x2 ID</label></div>
                        </div>
                        <div class="col-6">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="chk_id_1x1"
                                    id="in_chk_id_1x1" value="1"
                                    onchange="syncPreviewCheck('in_chk_id_1x1', 'view_chk_id_1x1')"><label
                                    class="form-check-label small" for="in_chk_id_1x1">1x1 ID</label></div>
                        </div>
                    </div>
                </div>

                <div class="mt-2 mb-3">
                    <button class="btn btn-collapse" type="button" data-bs-toggle="collapse"
                        data-bs-target="#violationCollapse" aria-expanded="false" aria-controls="violationCollapse">
                        <span><i class="fa fa-gavel me-2"></i> Violation History (Admin Only)</span>
                        <i class="fa fa-chevron-down chevron"></i>
                    </button>

                    <div class="collapse mt-2" id="violationCollapse">
                        <div class="sec-vehicle-box">
                            <small class="text-secondary d-block mb-2">Enter recent violations to appear on the back of
                                the form.</small>
                            <?php for ($i = 0; $i < 3; $i++): ?>
                                <div class="border-bottom border-secondary pb-2 mb-2">
                                    <div class="row g-1 mb-1">
                                        <div class="col-4"><input type="text" name="vio_date[]"
                                                id="in_vio_date_<?php echo $i; ?>" class="form-control form-control-sm"
                                                placeholder="Date" oninput="updatePreview()"></div>
                                        <div class="col-4"><input type="text" name="vio_time[]"
                                                id="in_vio_time_<?php echo $i; ?>" class="form-control form-control-sm"
                                                placeholder="Time" oninput="updatePreview()"></div>
                                        <div class="col-4"><input type="text" name="vio_loc[]"
                                                id="in_vio_loc_<?php echo $i; ?>" class="form-control form-control-sm"
                                                placeholder="Location" oninput="updatePreview()"></div>
                                    </div>
                                    <div class="row g-1">
                                        <div class="col-4"><input type="text" name="vio_desc[]"
                                                id="in_vio_desc_<?php echo $i; ?>" class="form-control form-control-sm"
                                                placeholder="Violation" oninput="updatePreview()"></div>
                                        <div class="col-4"><input type="text" name="vio_action[]"
                                                id="in_vio_action_<?php echo $i; ?>" class="form-control form-control-sm"
                                                placeholder="Action" oninput="updatePreview()"></div>
                                        <div class="col-4"><input type="text" name="vio_officer[]"
                                                id="in_vio_officer_<?php echo $i; ?>" class="form-control form-control-sm"
                                                placeholder="Officer" oninput="updatePreview()"></div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" name="submit_application"
                        class="btn btn-primary flex-grow-1 fw-bold py-3 mt-2"><i class="fa fa-plus-circle me-2"></i> ADD
                        TO QUEUE</button>
                    <button type="button" onclick="resetForm()" class="btn btn-warning fw-bold py-3 mt-2"><i
                            class="fa fa-rotate-right"></i></button>
                </div>
            </form>

            <hr class="border-secondary my-4">

            <div class="row g-2">
                <div class="col-6"><button onclick="printQueue()" class="btn btn-success w-100 fw-bold h-100"
                        id="printQueueBtn"><i class="fa fa-print me-2"></i> Print Queue</button></div>
                <div class="col-6"><button onclick="printBlank()"
                        class="btn btn-secondary w-100 fw-bold text-white h-100"><i class="fa fa-file me-2"></i> Blank
                        Form</button></div>
                <?php if (count($_SESSION['parking_print_queue']) > 0): ?>
                    <div class="col-12">
                        <form method="POST" class="m-0"><button type="submit" name="clear_queue"
                                class="btn btn-danger w-100 fw-bold" onclick="return confirm('Clear queue?')"><i
                                    class="fa fa-trash me-2"></i> Clear Queue</button></form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="right-panel">
            <div class="panel-header w-100 border-bottom pb-3 mb-4" style="border-color: var(--border)!important;">
                <div class="panel-title"><i class="fa fa-eye"></i> DOCUMENT PREVIEW</div>
            </div>

            <div class="preview-track">
                <div class="form-slide">
                    <div class="hcc-form">
                        <div class="header-layout"><img src="background-hcc-logo.png" alt="Logo" class="logo-left"><img
                                src="header_hcc.png" alt="Header" class="header-banner"></div>
                        <div class="form-title">
                            <h2>SAFETY AND PROTECTION DIVISION</h2>
                            <h3>APPLICATION FOR EMPLOYEES VEHICLE PARKING SPACE (SY 2024-2025)</h3>
                            <h1 class="employee-title" id="out_type_preview">EMPLOYEE</h1>
                            <div class="status-checkboxes"><span class="checkbox-box" id="view_chk_approved"></span>
                                Approved &nbsp;&nbsp;&nbsp; <span class="checkbox-box" id="view_chk_disapproved"></span>
                                Disapproved</div>
                        </div>
                        <div class="file-info"><span>File Application # ____________</span><span>Date: <span
                                    id="out_date"
                                    style="text-decoration: underline;"><?php echo date('m/d/Y'); ?></span></span></div>
                        <table class="data-grid">
                            <tr>
                                <td class="label">NAME <br><span style="font-size:7pt; font-weight:normal">(Last, First,
                                        MI)</span></td>
                                <td class="value" colspan="2" id="out_name"></td>
                                <td class="label">DEPARTMENT</td>
                                <td class="value" id="out_dept"></td>
                            </tr>
                            <tr>
                                <td class="label">ADDRESS</td>
                                <td class="value" colspan="2" id="out_address"></td>
                                <td class="label">MOTORIZED VEHICLE TYPE</td>
                                <td class="value" id="out_vtype"></td>
                            </tr>
                            <tr>
                                <td class="label">CEL. NO.</td>
                                <td class="value" colspan="2" id="out_cel"></td>
                                <td class="label">MOTORIZED VEHICLE BRAND</td>
                                <td class="value" id="out_vbrand"></td>
                            </tr>
                            <tr>
                                <td class="label">LICENSE #</td>
                                <td class="value" colspan="2" id="out_license"></td>
                                <td class="label">MOTORIZED VEHICLE COLOR</td>
                                <td class="value" id="out_vcolor"></td>
                            </tr>
                            <tr>
                                <td class="label">OR #</td>
                                <td class="value" colspan="2" id="out_or"></td>
                                <td class="label">CR #</td>
                                <td class="value" id="out_cr"></td>
                            </tr>
                            <tr>
                                <td class="label">E-MAIL</td>
                                <td class="value" colspan="2" id="out_email"></td>
                                <td class="label" style="font-size: 7pt;">VALID/WORKING FACEBOOK ACCOUNT</td>
                                <td class="value" id="out_fb"></td>
                            </tr>
                        </table>
                        <div class="emerg-header">PERSON TO NOTIFY IN CASE OF EMERGENCY</div>
                        <table class="emerg-table">
                            <tr>
                                <td style="width: 60%; padding-left: 10px;">
                                    <div style="margin-bottom: 5px;"><span class="emerg-label">Name:</span> <span
                                            class="emerg-val" id="out_ename"></span></div>
                                    <div style="margin-bottom: 5px;"><span class="emerg-label">Address:</span> <span
                                            class="emerg-val" id="out_eaddress"></span></div>
                                    <div><span class="emerg-label">Relation:</span> <span class="emerg-val"
                                            id="out_erelation"></span></div>
                                </td>
                                <td style="width: 40%; vertical-align: top;">
                                    <div style="font-weight:bold; font-size:8pt; margin-bottom:5px;">Contact number(s):
                                    </div>
                                    <div id="out_econtact"
                                        style="font-family:'Calibri', 'Arial', sans-serif; font-weight:bold; font-size:12pt; text-align:center; padding-top:15px;">
                                    </div>
                                </td>
                            </tr>
                        </table>
                        <div style="font-size:9pt; margin-top:10px; font-weight:bold; font-family: Arial, sans-serif;">
                            Fill up the table below if you are using more than one vehicle:</div>
                        <table class="mv-table">
                            <tr>
                                <th style="width: 20%;">MOTORIZED<br>VEHICLE TYPE</th>
                                <th style="width: 25%;">MOTORIZED<br>VEHICLE BRAND</th>
                                <th style="width: 25%;">MOTORIZED<br>VEHICLE COLOR</th>
                                <th style="width: 15%;">OR #</th>
                                <th style="width: 15%;">CR #</th>
                            </tr>
                            <tr>
                                <td id="out_sec_type_0"></td>
                                <td id="out_sec_brand_0"></td>
                                <td id="out_sec_color_0"></td>
                                <td id="out_sec_or_0"></td>
                                <td id="out_sec_cr_0"></td>
                            </tr>
                            <tr>
                                <td id="out_sec_type_1"></td>
                                <td id="out_sec_brand_1"></td>
                                <td id="out_sec_color_1"></td>
                                <td id="out_sec_or_1"></td>
                                <td id="out_sec_cr_1"></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </table>
                        <table class="docs-table">
                            <tr>
                                <td class="checklist">
                                    <div style="margin-bottom: 5px;"><strong>Documents Submitted:</strong></div>
                                    <div class="mb-1"><span class="checkbox-box" id="view_chk_cr"></span> Certificate of
                                        Registration (CR)</div>
                                    <div class="mb-1"><span class="checkbox-box" id="view_chk_or"></span> Official
                                        Receipt (OR)</div>
                                    <div style="margin-top: 10px; margin-bottom: 5px;"><strong>Updated/registered
                                            drivers License:</strong></div>
                                    <div class="mb-1"><span class="checkbox-box" id="view_chk_student_lic"></span>
                                        Student Drivers License</div>
                                    <div class="mb-1"><span class="checkbox-box" id="view_chk_nonpro_lic"></span>
                                        Non-Pro Drivers License</div>
                                    <div class="mb-1"><span class="checkbox-box" id="view_chk_pro_lic"></span>
                                        Professional Drivers License</div>
                                    <div style="margin-top: 10px; margin-bottom: 2px;"><span class="checkbox-box"
                                            id="view_chk_id_2x2"></span> Updated 1 2"x2" colored ID picture (White
                                        background)</div>
                                    <div class="mb-1"><span class="checkbox-box" id="view_chk_id_1x1"></span> Updated 1
                                        1"x1" colored ID picture (White background)</div>
                                </td>
                                <td class="id-cell">
                                    <div class="id-box"></div>
                                </td>
                            </tr>
                        </table>
                        <table class="sig-table">
                            <tr>
                                <td style="text-align: left;">
                                    <div
                                        style="border-top: 1px solid black; width: 300px; margin-bottom: 5px; margin-left: 0; margin-top: 20px;">
                                        <div style="text-align:center; font-weight:bold; padding-top:2px;"
                                            id="out_sig_name"></div>
                                    </div>
                                    <div style="margin-bottom: 30px; font-size: 10pt;">Signature over printed name of
                                        <span id="out_sig_preview">employee</span>
                                    </div>
                                    <div style="margin-bottom: 15px; font-weight: bold;">Approved by:</div>
                                    <div style="font-weight:bold; text-decoration: underline; font-size: 11pt;">PAUL
                                        JEFFREY T. LANSANGAN, SO3</div>
                                    <div style="font-size: 10pt;">CHIEF, Safety and Protection</div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="form-slide">
                    <div class="hcc-form">
                        <div class="header-layout"><img src="background-hcc-logo.png" alt="Logo" class="logo-left"><img
                                src="header_hcc.png" alt="Header" class="header-banner"></div>
                        <table class="violation-table"
                            style="width:100%; border-collapse:collapse; margin-top:50px; font-size:9pt;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Location</th>
                                    <th>Violation</th>
                                    <th>Action Taken</th>
                                    <th>Apprehending<br>Safety Officer<br>/Security Officer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <tr>
                                        <td id="out_vio_date_<?php echo $i; ?>"></td>
                                        <td id="out_vio_time_<?php echo $i; ?>"></td>
                                        <td id="out_vio_loc_<?php echo $i; ?>"></td>
                                        <td id="out_vio_desc_<?php echo $i; ?>"></td>
                                        <td id="out_vio_action_<?php echo $i; ?>"></td>
                                        <td id="out_vio_officer_<?php echo $i; ?>"></td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                        <div style="text-align:center; margin-top:20px; font-family:Arial, sans-serif;">
                            <h4 style="margin:0; font-weight:bold; text-decoration:underline; font-size: 11pt;">Mga
                                Patakaran ng Parking sa Holy Cross College, Sta. Ana, Pampanga</h4>
                            <h5 style="margin:5px 0 0 0; font-weight:normal; font-size: 10pt;">SY 2025-2026</h5>
                        </div>
                        <div class="waiver-text">
                            <ol>
                                <li>Ang Gate 2 ay para sa entrance at Gate 1 ay para sa exit.</li>
                                <li>Kailangan gamitin ang signal lights tuwing lumiliko (left and right signal lights)
                                </li>
                                <li>Bawal ipahiram ng empleyado ang kanilang sasakyan sa mga estudyante o kapwa
                                    empleyado na walang parking permit.</li>
                                <li>Ang pagpark ay pinapahintulutan lang habang kayo ay nasa eskwelahan, ibig sabihin ay
                                    nagkatapos ng trabaho ay dapat wala ang sasakyan sa parking. Hindi pwedeng iwanan
                                    ang sasakyan sa eskwelahan kung wala nang trabaho.</li>
                                <li>Wag makipag unahan pagpasok ng eskwelahan. Siguraduhin paupuin ang mga tumatawid sa
                                    daanan.</li>
                                <li>Siguraduhin magpark sa designated parking slots para sa mga empleyado.</li>
                                <li>Ang mga sasakyan na naka-open muffler ay di pwedeng mag-ingay sa loob ng eskwelahan.
                                </li>
                                <li>Para sa mga 4-wheels, ang parking permit ay dapat nakadikit sa kaliwang bahagi ng
                                    windshield. Samantalang sa mga single na motorsiklo at may sidecar ay nakalagay sa
                                    company ID. Ang walang parking permit ay di makakapasok sa parking ng eskwelahan.
                                </li>
                                <li>Ang mga motorsiklo ay dapat may side mirror (left and right)</li>
                                <li>Sundin ang 15-20 kph speed limit sa loob ng eskwelahan.</li>
                                <li>Ang paggamit ng busina ay ipinagbabawal sa loob ng paaralan. Sa panahon ng emergency
                                    lang maaring gamitin.</li>
                                <li>Ang headlight, flashers, stoplight ay dapat gumagana.</li>
                                <li>Ang empleyado na walang driver's license ay di maaring magpark sa loob ng
                                    eskwelahan. Ang empleyado na student lang ang lisensya ay bibigyan ng dalawang buwan
                                    para makakuha ng non-pro/professional license. Kung hindi makakakuha ay matatangalan
                                    ng pribilehiyo na magpark.</li>
                                <li>Ang eskwelahan ay walang pananagutan sa mga sasakyan kaya siguraduhin wag mag iwan
                                    ng mga mahahalagang bagay at laging i-lock ang mga sasakyan pag ito ay iiwanan sa
                                    parking.</li>
                                <li>Para sa may mga single na motorsiklo, laging isuot ang helmet pag papasok at
                                    paglabas ng eskwelahan. Kung meron backride na kasama, dapat ang backride ay meron
                                    ding suot na helmet. Ang may ari ng motor ang mabibigyan ng violation kung hahayaan
                                    nya na walang helmet ang naka-angkas sa kanya.</li>
                                <li>1st come, first serve ang parking space. Nangangahulugan na pag wala nang parking
                                    space sa loob ng eskwelahan ay sa labas na ng school magpapark.</li>
                                <li>Ang di susunod ng tatlong (3) beses sa ating mga patakaran ay matatangalan ng
                                    pribilehiyo na magpark sa loob ng eskwelahan. Bibigyan din ng kopya ng inyong
                                    violation ang HR. (With accordance to Admin and Faculty Handbook Chapter 8
                                    Violations and Sanctions Section D. 4.)</li>
                                <li>Ang mga empleyado na ma-aapprove ang parking application ay isasali sa GC(Group Chat
                                    ng employees parking)</li>
                                <li>Ang mga safety officers at school guards ang mag momonitor sa mga di susunod sa
                                    patakaran ng parking.</li>
                            </ol>
                        </div>
                        <div style="margin-top:30px; font-size:10pt; font-family:Arial, sans-serif;">
                            <p style="margin-left: 20px;">Ako ay sumasang-ayon sa mga patakaran ng parking sa Holy Cross
                                College.</p>
                            <div
                                style="margin-top:40px; margin-left: 20px; width:300px; border-top:1px solid black; text-align:center; padding-top: 5px;">
                                <span id="out_sig_fil_preview">Empleyado</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold m-0"><i class="fa fa-history me-2"></i> RECENT APPLICATIONS</h5>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-dark">Total: <?php echo $total_count; ?></span>
                <form method="GET" class="d-flex gap-0" style="width: 300px;">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search name or dept..."
                            value="<?php echo htmlspecialchars($search_term); ?>" style="margin-bottom: 0;">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i></button>
                        <?php if (!empty($search_term)): ?><a href="<?php echo $_SERVER['PHP_SELF']; ?>"
                                class="btn btn-secondary"><i class="fa fa-times"></i></a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-custom table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>TYPE</th>
                        <th>NAME</th>
                        <th>DEPARTMENT</th>
                        <th>VEHICLE</th>
                        <th>CONTACT</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_reports->num_rows > 0): ?>
                        <?php while ($row = $recent_reports->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td><span class="badge bg-info text-white"><?php echo $row['applicant_type']; ?></span></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['applicant_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><?php echo htmlspecialchars($row['vehicle_brand'] . ' ' . $row['vehicle_color']); ?></td>
                                <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-center"><button
                                            onclick='loadData(<?php echo json_encode($row); ?>)'
                                            class="btn btn-sm btn-info text-white"><i class="fa fa-eye"></i></button><a
                                            href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Delete this record?')"><i class="fa fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted"><i
                                    class="fa fa-database fa-2x mb-3"></i><br>No records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="print-area">
        <?php if (count($_SESSION['parking_print_queue']) > 0): ?>
            <?php foreach ($_SESSION['parking_print_queue'] as $p): ?>

                <div class="hcc-form">
                    <div class="header-layout"><img src="background-hcc-logo.png" class="logo-left"><img src="header_hcc.png"
                            class="header-banner"></div>
                    <div class="form-title">
                        <h2>SAFETY AND PROTECTION DIVISION</h2>
                        <h3>APPLICATION FOR EMPLOYEES VEHICLE PARKING SPACE (SY 2024-2025)</h3>
                        <h1 class="employee-title"><?php echo strtoupper($p['applicant_type']); ?></h1>
                        <div class="status-checkboxes">
                            <span
                                class="checkbox-box <?php echo isset($p['chk_approved']) && $p['chk_approved'] == '1' ? 'checked' : ''; ?>"></span>
                            Approved &nbsp;&nbsp;&nbsp;
                            <span
                                class="checkbox-box <?php echo isset($p['chk_disapproved']) && $p['chk_disapproved'] == '1' ? 'checked' : ''; ?>"></span>
                            Disapproved
                        </div>
                    </div>
                    <div class="file-info"><span>File Application # ____________</span><span>Date: <span
                                style="text-decoration: underline;"><?php echo date('m/d/Y'); ?></span></span></div>

                    <table class="data-grid">
                        <tr>
                            <td class="label">NAME <br><span style="font-size:7pt; font-weight:normal">(Last, First, MI)</span>
                            </td>
                            <td class="value" colspan="2"><?php echo $p['applicant_name']; ?></td>
                            <td class="label">DEPARTMENT</td>
                            <td class="value"><?php echo $p['department']; ?></td>
                        </tr>
                        <tr>
                            <td class="label">ADDRESS</td>
                            <td class="value" colspan="2"><?php echo $p['address']; ?></td>
                            <td class="label">MOTORIZED VEHICLE TYPE</td>
                            <td class="value"><?php echo $p['vehicle_type']; ?></td>
                        </tr>
                        <tr>
                            <td class="label">CEL. NO.</td>
                            <td class="value" colspan="2"><?php echo $p['contact_number']; ?></td>
                            <td class="label">MOTORIZED VEHICLE BRAND</td>
                            <td class="value"><?php echo $p['vehicle_brand']; ?></td>
                        </tr>
                        <tr>
                            <td class="label">LICENSE #</td>
                            <td class="value" colspan="2"><?php echo $p['license_no']; ?></td>
                            <td class="label">MOTORIZED VEHICLE COLOR</td>
                            <td class="value"><?php echo $p['vehicle_color']; ?></td>
                        </tr>
                        <tr>
                            <td class="label">OR #</td>
                            <td class="value" colspan="2"><?php echo $p['or_no']; ?></td>
                            <td class="label">CR #</td>
                            <td class="value"><?php echo $p['cr_no']; ?></td>
                        </tr>
                        <tr>
                            <td class="label">E-MAIL</td>
                            <td class="value" colspan="2"><?php echo $p['email']; ?></td>
                            <td class="label" style="font-size: 7pt;">VALID/WORKING FACEBOOK ACCOUNT</td>
                            <td class="value"><?php echo $p['fb_account']; ?></td>
                        </tr>
                    </table>

                    <div class="emerg-header">PERSON TO NOTIFY IN CASE OF EMERGENCY</div>
                    <table class="emerg-table">
                        <tr>
                            <td style="width: 60%; padding-left: 10px;">
                                <div style="margin-bottom: 5px;"><span class="emerg-label">Name:</span> <span
                                        class="emerg-val"><?php echo $p['emerg_name']; ?></span></div>
                                <div style="margin-bottom: 5px;"><span class="emerg-label">Address:</span> <span
                                        class="emerg-val"><?php echo $p['emerg_address']; ?></span></div>
                                <div><span class="emerg-label">Relation:</span> <span
                                        class="emerg-val"><?php echo $p['emerg_relation']; ?></span></div>
                            </td>
                            <td style="width: 40%; vertical-align: top;">
                                <div style="font-weight:bold; font-size:8pt; margin-bottom:5px;">Contact number(s):</div>
                                <div
                                    style="font-family:'Calibri', 'Arial', sans-serif; font-weight:bold; font-size:12pt; text-align:center; padding-top:15px;">
                                    <?php echo $p['emerg_contact']; ?>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <div style="font-size:9pt; margin-top:10px; font-weight:bold; font-family: Arial, sans-serif;">Fill up the
                        table below if you are using more than one vehicle:</div>
                    <table class="mv-table">
                        <tr>
                            <th style="width: 20%;">MOTORIZED<br>VEHICLE TYPE</th>
                            <th style="width: 25%;">MOTORIZED<br>VEHICLE BRAND</th>
                            <th style="width: 25%;">MOTORIZED<br>VEHICLE COLOR</th>
                            <th style="width: 15%;">OR #</th>
                            <th style="width: 15%;">CR #</th>
                        </tr>
                        <?php
                        $sec_v = isset($p['secondary_vehicles']) ? json_decode($p['secondary_vehicles'], true) : [];
                        for ($i = 0; $i < 4; $i++) {
                            $v = isset($sec_v[$i]) ? $sec_v[$i] : null;
                            echo "<tr>";
                            echo "<td>" . ($v ? $v['type'] : '') . "</td>";
                            echo "<td>" . ($v ? $v['brand'] : '') . "</td>";
                            echo "<td>" . ($v ? $v['color'] : '') . "</td>";
                            echo "<td>" . ($v ? $v['or'] : '') . "</td>";
                            echo "<td>" . ($v ? $v['cr'] : '') . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </table>

                    <table class="docs-table">
                        <tr>
                            <td class="checklist">
                                <div style="margin-bottom: 5px;"><strong>Documents Submitted:</strong></div>
                                <div class="mb-1"><span
                                        class="checkbox-box <?php echo isset($p['chk_cr']) && $p['chk_cr'] == '1' ? 'checked' : ''; ?>"></span>
                                    Certificate of Registration (CR)</div>
                                <div class="mb-1"><span
                                        class="checkbox-box <?php echo isset($p['chk_or']) && $p['chk_or'] == '1' ? 'checked' : ''; ?>"></span>
                                    Official Receipt (OR)</div>
                                <div style="margin-top: 10px; margin-bottom: 5px;"><strong>Updated/registered drivers
                                        License:</strong></div>
                                <div class="mb-1"><span
                                        class="checkbox-box <?php echo isset($p['chk_student_lic']) && $p['chk_student_lic'] == '1' ? 'checked' : ''; ?>"></span>
                                    Student Drivers License</div>
                                <div class="mb-1"><span
                                        class="checkbox-box <?php echo isset($p['chk_nonpro_lic']) && $p['chk_nonpro_lic'] == '1' ? 'checked' : ''; ?>"></span>
                                    Non-Pro Drivers License</div>
                                <div class="mb-1"><span
                                        class="checkbox-box <?php echo isset($p['chk_pro_lic']) && $p['chk_pro_lic'] == '1' ? 'checked' : ''; ?>"></span>
                                    Professional Drivers License</div>
                                <div style="margin-top: 10px; margin-bottom: 2px;"><span
                                        class="checkbox-box <?php echo isset($p['chk_id_2x2']) && $p['chk_id_2x2'] == '1' ? 'checked' : ''; ?>"></span>
                                    Updated 1 2"x2" colored ID picture (White background)</div>
                                <div class="mb-1"><span
                                        class="checkbox-box <?php echo isset($p['chk_id_1x1']) && $p['chk_id_1x1'] == '1' ? 'checked' : ''; ?>"></span>
                                    Updated 1 1"x1" colored ID picture (White background)</div>
                            </td>
                            <td class="id-cell">
                                <div class="id-box"></div>
                            </td>
                        </tr>
                    </table>

                    <table class="sig-table">
                        <tr>
                            <td style="text-align: left;">
                                <div
                                    style="border-top: 1px solid black; width: 300px; margin-bottom: 5px; margin-left: 0; margin-top: 20px;">
                                    <div style="text-align:center; font-weight:bold; padding-top:2px;">
                                        <?php echo $p['applicant_name']; ?>
                                    </div>
                                </div>
                                <div style="margin-bottom: 30px; font-size: 10pt;">Signature over printed name of
                                    <?php echo strtolower($p['applicant_type']); ?>
                                </div>
                                <div style="margin-bottom: 15px; font-weight: bold;">Approved by:</div>
                                <div style="font-weight:bold; text-decoration: underline; font-size: 11pt;">PAUL JEFFREY T.
                                    LANSANGAN, SO3</div>
                                <div style="font-size: 10pt;">CHIEF, Safety and Protection</div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="hcc-form">
                    <div class="header-layout"><img src="background-hcc-logo.png" class="logo-left"><img src="header_hcc.png"
                            class="header-banner"></div>
                    <table class="violation-table"
                        style="width:100%; border-collapse:collapse; margin-top:50px; font-size:9pt;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Location</th>
                                <th>Violation</th>
                                <th>Action Taken</th>
                                <th>Apprehending<br>Safety Officer<br>/Security Officer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $vio_data = isset($p['violation_data']) ? json_decode($p['violation_data'], true) : [];
                            for ($i = 0; $i < 5; $i++) {
                                $v = isset($vio_data[$i]) ? $vio_data[$i] : null;
                                echo "<tr>";
                                echo "<td>" . ($v ? $v['date'] : '') . "</td><td>" . ($v ? $v['time'] : '') . "</td><td>" . ($v ? $v['loc'] : '') . "</td>";
                                echo "<td>" . ($v ? $v['desc'] : '') . "</td><td>" . ($v ? $v['action'] : '') . "</td><td>" . ($v ? $v['officer'] : '') . "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <div style="text-align:center; margin-top:20px; font-family:Arial, sans-serif;">
                        <h4 style="margin:0; font-weight:bold; text-decoration:underline; font-size: 11pt;">Mga Patakaran ng
                            Parking sa Holy Cross College, Sta. Ana, Pampanga</h4>
                        <h5 style="margin:5px 0 0 0; font-weight:normal; font-size: 10pt;">SY 2025-2026</h5>
                    </div>
                    <div class="waiver-text">
                        <ol>
                            <li>Ang Gate 2 ay para sa entrance at Gate 1 ay para sa exit.</li>
                            <li>Kailangan gamitin ang signal lights tuwing lumiliko (left and right signal lights)</li>
                            <li>Bawal ipahiram ng empleyado ang kanilang sasakyan sa mga estudyante o kapwa empleyado na walang
                                parking permit.</li>
                            <li>Ang pagpark ay pinapahintulutan lang habang kayo ay nasa eskwelahan, ibig sabihin ay nagkatapos
                                ng trabaho ay dapat wala ang sasakyan sa parking. Hindi pwedeng iwanan ang sasakyan sa
                                eskwelahan kung wala nang trabaho.</li>
                            <li>Wag makipag unahan pagpasok ng eskwelahan. Siguraduhin paupuin ang mga tumatawid sa daanan.</li>
                            <li>Siguraduhin magpark sa designated parking slots para sa mga empleyado.</li>
                            <li>Ang mga sasakyan na naka-open muffler ay di pwedeng mag-ingay sa loob ng eskwelahan.</li>
                            <li>Para sa mga 4-wheels, ang parking permit ay dapat nakadikit sa kaliwang bahagi ng windshield.
                                Samantalang sa mga single na motorsiklo at may sidecar ay nakalagay sa company ID. Ang walang
                                parking permit ay di makakapasok sa parking ng eskwelahan.</li>
                            <li>Ang mga motorsiklo ay dapat may side mirror (left and right)</li>
                            <li>Sundin ang 15-20 kph speed limit sa loob ng eskwelahan.</li>
                            <li>Ang paggamit ng busina ay ipinagbabawal sa loob ng paaralan. Sa panahon ng emergency lang
                                maaring gamitin.</li>
                            <li>Ang headlight, flashers, stoplight ay dapat gumagana.</li>
                            <li>Ang empleyado na walang driver's license ay di maaring magpark sa loob ng eskwelahan. Ang
                                empleyado na student lang ang lisensya ay bibigyan ng dalawang buwan para makakuha ng
                                non-pro/professional license. Kung hindi makakakuha ay matatangalan ng pribilehiyo na magpark.
                            </li>
                            <li>Ang eskwelahan ay walang pananagutan sa mga sasakyan kaya siguraduhin wag mag iwan ng mga
                                mahahalagang bagay at laging i-lock ang mga sasakyan pag ito ay iiwanan sa parking.</li>
                            <li>Para sa may mga single na motorsiklo, laging isuot ang helmet pag papasok at paglabas ng
                                eskwelahan. Kung meron backride na kasama, dapat ang backride ay meron ding suot na helmet. Ang
                                may ari ng motor ang mabibigyan ng violation kung hahayaan nya na walang helmet ang naka-angkas
                                sa kanya.</li>
                            <li>1st come, first serve ang parking space. Nangangahulugan na pag wala nang parking space sa loob
                                ng eskwelahan ay sa labas na ng school magpapark.</li>
                            <li>Ang di susunod ng tatlong (3) beses sa ating mga patakaran ay matatangalan ng pribilehiyo na
                                magpark sa loob ng eskwelahan. Bibigyan din ng kopya ng inyong violation ang HR. (With
                                accordance to Admin and Faculty Handbook Chapter 8 Violations and Sanctions Section D. 4.)</li>
                            <li>Ang mga empleyado na ma-aapprove ang parking application ay isasali sa GC(Group Chat ng
                                employees parking)</li>
                            <li>Ang mga safety officers at school guards ang mag momonitor sa mga di susunod sa patakaran ng
                                parking.</li>
                        </ol>
                    </div>
                    <div style="margin-top:30px; font-size:10pt; font-family:Arial, sans-serif;">
                        <p style="margin-left: 20px;">Ako ay sumasang-ayon sa mga patakaran ng parking sa Holy Cross College.
                        </p>
                        <div
                            style="margin-top:40px; margin-left: 20px; width:300px; border-top:1px solid black; text-align:center; padding-top: 5px;">
                            <span
                                id="out_sig_fil_preview"><?php echo ($p['applicant_type'] == 'STUDENT') ? 'Estudyante' : 'Empleyado'; ?></span>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="print-blank-area">
        <div class="hcc-form">
            <div class="header-layout"><img src="background-hcc-logo.png" class="logo-left"><img src="header_hcc.png"
                    class="header-banner"></div>
            <div class="form-title">
                <h2>SAFETY AND PROTECTION DIVISION</h2>
                <h3>APPLICATION FOR EMPLOYEES VEHICLE PARKING SPACE (SY 2024-2025)</h3>
                <h1 class="employee-title" id="out_type_blank">EMPLOYEE</h1>
                <div class="status-checkboxes"><span class="checkbox-box"></span> Approved &nbsp;&nbsp;&nbsp; <span
                        class="checkbox-box"></span> Disapproved</div>
            </div>
            <div class="file-info"><span>File Application # ____________</span><span>Date: <span
                        style="text-decoration: underline;"><?php echo date('m/d/Y'); ?></span></span></div>
            <table class="data-grid">
                <tr>
                    <td class="label">NAME <br><span style="font-size:7pt; font-weight:normal">(Last, First, MI)</span>
                    </td>
                    <td class="value" colspan="2"></td>
                    <td class="label">DEPARTMENT</td>
                    <td class="value"></td>
                </tr>
                <tr>
                    <td class="label">ADDRESS</td>
                    <td class="value" colspan="2"></td>
                    <td class="label">MOTORIZED VEHICLE TYPE</td>
                    <td class="value"></td>
                </tr>
                <tr>
                    <td class="label">CEL. NO.</td>
                    <td class="value" colspan="2"></td>
                    <td class="label">MOTORIZED VEHICLE BRAND</td>
                    <td class="value"></td>
                </tr>
                <tr>
                    <td class="label">LICENSE #</td>
                    <td class="value" colspan="2"></td>
                    <td class="label">MOTORIZED VEHICLE COLOR</td>
                    <td class="value"></td>
                </tr>
                <tr>
                    <td class="label">OR #</td>
                    <td class="value" colspan="2"></td>
                    <td class="label">CR #</td>
                    <td class="value"></td>
                </tr>
                <tr>
                    <td class="label">E-MAIL</td>
                    <td class="value" colspan="2"></td>
                    <td class="label" style="font-size: 7pt;">VALID/WORKING FACEBOOK ACCOUNT</td>
                    <td class="value"></td>
                </tr>
            </table>
            <div class="emerg-header">PERSON TO NOTIFY IN CASE OF EMERGENCY</div>
            <table class="emerg-table">
                <tr>
                    <td style="width: 60%; padding-left: 10px;">
                        <div style="margin-bottom: 5px;"><span class="emerg-label">Name:</span> <span
                                class="emerg-val"></span></div>
                        <div style="margin-bottom: 5px;"><span class="emerg-label">Address:</span> <span
                                class="emerg-val"></span></div>
                        <div><span class="emerg-label">Relation:</span> <span class="emerg-val"></span></div>
                    </td>
                    <td style="width: 40%; vertical-align: top;">
                        <div style="font-weight:bold; font-size:8pt; margin-bottom:5px;">Contact number(s):</div>
                        <div
                            style="font-family:'Calibri', 'Arial', sans-serif; font-weight:bold; font-size:12pt; text-align:center; padding-top:15px;">
                        </div>
                    </td>
                </tr>
            </table>
            <div style="font-size:9pt; margin-top:10px; font-weight:bold; font-family: Arial, sans-serif;">Fill up the
                table below if you are using more than one vehicle:</div>
            <table class="mv-table">
                <tr>
                    <th style="width: 20%;">MOTORIZED<br>VEHICLE TYPE</th>
                    <th style="width: 25%;">MOTORIZED<br>VEHICLE BRAND</th>
                    <th style="width: 25%;">MOTORIZED<br>VEHICLE COLOR</th>
                    <th style="width: 15%;">OR #</th>
                    <th style="width: 15%;">CR #</th>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </table>
            <table class="docs-table">
                <tr>
                    <td class="checklist">
                        <div style="margin-bottom: 5px;"><strong>Documents Submitted:</strong></div>
                        <div class="mb-1"><span class="checkbox-box"></span> Certificate of Registration (CR)</div>
                        <div class="mb-1"><span class="checkbox-box"></span> Official Receipt (OR)</div>
                        <div style="margin-top: 10px; margin-bottom: 5px;"><strong>Updated/registered drivers
                                License:</strong></div>
                        <div class="mb-1"><span class="checkbox-box"></span> Student Drivers License</div>
                        <div class="mb-1"><span class="checkbox-box"></span> Non-Pro Drivers License</div>
                        <div class="mb-1"><span class="checkbox-box"></span> Professional Drivers License</div>
                        <div style="margin-top: 10px; margin-bottom: 2px;"><span class="checkbox-box"></span> Updated 1
                            2"x2" colored ID picture (White background)</div>
                        <div class="mb-1"><span class="checkbox-box"></span> Updated 1 1"x1" colored ID picture (White
                            background)</div>
                    </td>
                    <td class="id-cell">
                        <div class="id-box"></div>
                    </td>
                </tr>
            </table>
            <table class="sig-table">
                <tr>
                    <td style="text-align: left;">
                        <div
                            style="border-top: 1px solid black; width: 300px; margin-bottom: 5px; margin-left: 0; margin-top: 20px;">
                        </div>
                        <div style="margin-bottom: 30px; font-size: 10pt;">Signature over printed name of <span
                                id="out_sig_blank">employee</span></div>
                        <div style="margin-bottom: 15px; font-weight: bold;">Approved by:</div>
                        <div style="font-weight:bold; text-decoration: underline; font-size: 11pt;">PAUL JEFFREY T.
                            LANSANGAN, SO3</div>
                        <div style="font-size: 10pt;">CHIEF, Safety and Protection</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="hcc-form">
            <div class="header-layout"><img src="background-hcc-logo.png" class="logo-left"><img src="header_hcc.png"
                    class="header-banner"></div>
            <table class="violation-table"
                style="width:100%; border-collapse:collapse; margin-top:50px; font-size:9pt;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Location</th>
                        <th>Violation</th>
                        <th>Action Taken</th>
                        <th>Apprehending<br>Safety Officer<br>/Security Officer</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="height:20px;"></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td style="height:20px;"></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td style="height:20px;"></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td style="height:20px;"></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td style="height:20px;"></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
            <div style="text-align:center; margin-top:20px; font-family:Arial, sans-serif;">
                <h4 style="margin:0; font-weight:bold; text-decoration:underline; font-size: 11pt;">Mga Patakaran ng
                    Parking sa Holy Cross College, Sta. Ana, Pampanga</h4>
                <h5 style="margin:5px 0 0 0; font-weight:normal; font-size: 10pt;">SY 2025-2026</h5>
            </div>
            <div class="waiver-text">
                <ol>
                    <li>Ang Gate 2 ay para sa entrance at Gate 1 ay para sa exit.</li>
                    <li>Kailangan gamitin ang signal lights tuwing lumiliko (left and right signal lights)</li>
                    <li>Bawal ipahiram ng empleyado ang kanilang sasakyan sa mga estudyante o kapwa empleyado na walang
                        parking permit.</li>
                    <li>Ang pagpark ay pinapahintulutan lang habang kayo ay nasa eskwelahan, ibig sabihin ay nagkatapos
                        ng trabaho ay dapat wala ang sasakyan sa parking. Hindi pwedeng iwanan ang sasakyan sa
                        eskwelahan kung wala nang trabaho.</li>
                    <li>Wag makipag unahan pagpasok ng eskwelahan. Siguraduhin paupuin ang mga tumatawid sa daanan.</li>
                    <li>Siguraduhin magpark sa designated parking slots para sa mga empleyado.</li>
                    <li>Ang mga sasakyan na naka-open muffler ay di pwedeng mag-ingay sa loob ng eskwelahan.</li>
                    <li>Para sa mga 4-wheels, ang parking permit ay dapat nakadikit sa kaliwang bahagi ng windshield.
                        Samantalang sa mga single na motorsiklo at may sidecar ay nakalagay sa company ID. Ang walang
                        parking permit ay di makakapasok sa parking ng eskwelahan.</li>
                    <li>Ang mga motorsiklo ay dapat may side mirror (left and right)</li>
                    <li>Sundin ang 15-20 kph speed limit sa loob ng eskwelahan.</li>
                    <li>Ang paggamit ng busina ay ipinagbabawal sa loob ng paaralan. Sa panahon ng emergency lang
                        maaring gamitin.</li>
                    <li>Ang headlight, flashers, stoplight ay dapat gumagana.</li>
                    <li>Ang empleyado na walang driver's license ay di maaring magpark sa loob ng eskwelahan. Ang
                        empleyado na student lang ang lisensya ay bibigyan ng dalawang buwan para makakuha ng
                        non-pro/professional license. Kung hindi makakakuha ay matatangalan ng pribilehiyo na magpark.
                    </li>
                    <li>Ang eskwelahan ay walang pananagutan sa mga sasakyan kaya siguraduhin wag mag iwan ng mga
                        mahahalagang bagay at laging i-lock ang mga sasakyan pag ito ay iiwanan sa parking.</li>
                    <li>Para sa may mga single na motorsiklo, laging isuot ang helmet pag papasok at paglabas ng
                        eskwelahan. Kung meron backride na kasama, dapat ang backride ay meron ding suot na helmet. Ang
                        may ari ng motor ang mabibigyan ng violation kung hahayaan nya na walang helmet ang naka-angkas
                        sa kanya.</li>
                    <li>1st come, first serve ang parking space. Nangangahulugan na pag wala nang parking space sa loob
                        ng eskwelahan ay sa labas na ng school magpapark.</li>
                    <li>Ang di susunod ng tatlong (3) beses sa ating mga patakaran ay matatangalan ng pribilehiyo na
                        magpark sa loob ng eskwelahan. Bibigyan din ng kopya ng inyong violation ang HR. (With
                        accordance to Admin and Faculty Handbook Chapter 8 Violations and Sanctions Section D. 4.)</li>
                    <li>Ang mga empleyado na ma-aapprove ang parking application ay isasali sa GC(Group Chat ng
                        employees parking)</li>
                    <li>Ang mga safety officers at school guards ang mag momonitor sa mga di susunod sa patakaran ng
                        parking.</li>
                </ol>
            </div>
            <div style="margin-top:30px; font-size:10pt; font-family:Arial, sans-serif;">
                <p style="margin-left: 20px;">Ako ay sumasang-ayon sa mga patakaran ng parking sa Holy Cross College.
                </p>
                <div
                    style="margin-top:40px; margin-left: 20px; width:300px; border-top:1px solid black; text-align:center; padding-top: 5px;">
                    <span id="out_sig_fil_blank">Empleyado</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            const isLight = document.body.classList.contains('light-mode');
            document.getElementById('themeBtn').innerHTML = isLight ? '<i class="fa fa-sun"></i>' : '<i class="fa fa-moon"></i>';
            localStorage.setItem('appTheme', isLight ? 'light' : 'dark');
        }

        const savedTheme = localStorage.getItem('appTheme') || 'dark';
        if (savedTheme === 'light') { document.body.classList.add('light-mode'); document.getElementById('themeBtn').innerHTML = '<i class="fa fa-sun"></i>'; }

        // --- Sync Logic ---
        function syncPreviewCheck(inputId, viewId) {
            const input = document.getElementById(inputId);
            const view = document.getElementById(viewId);
            if (input && view) {
                if (input.checked) view.classList.add('checked');
                else view.classList.remove('checked');
            }
        }

        function syncStatus(type) {
            const isChecked = document.getElementById('in_chk_' + type).checked;
            const view = document.getElementById('view_chk_' + type);
            if (view) {
                if (isChecked) view.classList.add('checked');
                else view.classList.remove('checked');
            }
            if (isChecked) {
                const other = type === 'approved' ? 'disapproved' : 'approved';
                document.getElementById('in_chk_' + other).checked = false;
                const viewOther = document.getElementById('view_chk_' + other);
                if (viewOther) viewOther.classList.remove('checked');
            }
        }

        function updatePreview() {
            const ids = ['in_name', 'in_dept', 'in_address', 'in_cel', 'in_license', 'in_email', 'in_vtype', 'in_vbrand', 'in_vcolor', 'in_or', 'in_cr', 'in_fb', 'in_ename', 'in_eaddress', 'in_erelation', 'in_econtact'];
            const outs = ['out_name', 'out_dept', 'out_address', 'out_cel', 'out_license', 'out_email', 'out_vtype', 'out_vbrand', 'out_vcolor', 'out_or', 'out_cr', 'out_fb', 'out_ename', 'out_eaddress', 'out_erelation', 'out_econtact'];

            for (let i = 0; i < ids.length; i++) {
                let el = document.getElementById(ids[i]);
                let out = document.getElementById(outs[i]);
                if (el && out) out.innerText = el.value;
            }

            // Secondary Vehicles
            for (let i = 0; i < 2; i++) {
                ['type', 'brand', 'color', 'or', 'cr'].forEach(type => {
                    const input = document.getElementById(`in_sec_${type}_${i}`);
                    const output = document.getElementById(`out_sec_${type}_${i}`);
                    if (input && output) output.innerText = input.value;
                });
            }

            // Violation History
            for (let i = 0; i < 3; i++) {
                ['date', 'time', 'loc', 'desc', 'action', 'officer'].forEach(type => {
                    const input = document.getElementById(`in_vio_${type}_${i}`);
                    const output = document.getElementById(`out_vio_${type}_${i}`);
                    if (input && output) output.innerText = input.value;
                });
            }

            const nameInput = document.getElementById('in_name');
            const sigNameOut = document.getElementById('out_sig_name');
            if (nameInput && sigNameOut) sigNameOut.innerText = nameInput.value;

            const type = document.getElementById('in_type').value;
            const previewHeader = document.getElementById('out_type_preview');
            const blankHeader = document.getElementById('out_type_blank');
            if (previewHeader) previewHeader.innerText = type;
            if (blankHeader) blankHeader.innerText = type;

            const sigType = type.toLowerCase();
            const sigPreview = document.getElementById('out_sig_preview');
            const sigBlank = document.getElementById('out_sig_blank');
            if (sigPreview) sigPreview.innerText = sigType;
            if (sigBlank) sigBlank.innerText = sigType;

            const filipinoType = (type === 'STUDENT') ? 'Estudyante' : 'Empleyado';
            const sigFilPreview = document.getElementById('out_sig_fil_preview');
            const sigFilBlank = document.getElementById('out_sig_fil_blank');
            if (sigFilPreview) sigFilPreview.innerText = filipinoType;
            if (sigFilBlank) sigFilBlank.innerText = filipinoType;
        }

        function loadData(data) {
            document.getElementById('in_type').value = data.applicant_type || 'EMPLOYEE';
            document.getElementById('in_name').value = data.applicant_name;
            document.getElementById('in_dept').value = data.department;
            document.getElementById('in_cel').value = data.contact_number;
            document.getElementById('in_address').value = data.address;
            document.getElementById('in_license').value = data.license_no;
            document.getElementById('in_email').value = data.email;
            document.getElementById('in_vtype').value = data.vehicle_type;
            document.getElementById('in_vbrand').value = data.vehicle_brand;
            document.getElementById('in_vcolor').value = data.vehicle_color;
            document.getElementById('in_or').value = data.or_no;
            document.getElementById('in_cr').value = data.cr_no;
            document.getElementById('in_fb').value = data.fb_account;
            document.getElementById('in_ename').value = data.emerg_name;
            document.getElementById('in_eaddress').value = data.emerg_address;
            document.getElementById('in_erelation').value = data.emerg_relation;
            document.getElementById('in_econtact').value = data.emerg_contact;

            // Load Checklist
            const checkMap = {
                'chk_cr': 'view_chk_cr', 'chk_or': 'view_chk_or',
                'chk_student_lic': 'view_chk_student_lic', 'chk_nonpro_lic': 'view_chk_nonpro_lic',
                'chk_pro_lic': 'view_chk_pro_lic', 'chk_id_2x2': 'view_chk_id_2x2', 'chk_id_1x1': 'view_chk_id_1x1',
                'chk_approved': 'view_chk_approved', 'chk_disapproved': 'view_chk_disapproved'
            };

            for (let k in checkMap) {
                const el = document.getElementById('in_' + k);
                if (el) { el.checked = false; if (k.includes('approved')) syncStatus(k.replace('chk_', '')); else syncPreviewCheck('in_' + k, checkMap[k]); }
            }

            if (data.checklist_data) {
                try {
                    const checks = JSON.parse(data.checklist_data);
                    for (let key in checks) {
                        if (checks[key] == '1' && checkMap[key]) {
                            const el = document.getElementById('in_' + key);
                            if (el) {
                                el.checked = true;
                                if (key.includes('approved')) syncStatus(key.replace('chk_', ''));
                                else syncPreviewCheck('in_' + key, checkMap[key]);
                            }
                        }
                    }
                } catch (e) { console.log(e); }
            }

            // Load Secondary Vehicles
            for (let i = 0; i < 2; i++) {
                ['type', 'brand', 'color', 'or', 'cr'].forEach(k => {
                    const el = document.getElementById(`in_sec_${k}_${i}`);
                    if (el) el.value = '';
                });
            }
            if (data.secondary_vehicles) {
                try {
                    const sec = JSON.parse(data.secondary_vehicles);
                    if (sec.length > 0) new bootstrap.Collapse(document.getElementById('secVehiclesCollapse'), { show: true });
                    for (let i = 0; i < sec.length && i < 2; i++) {
                        document.getElementById(`in_sec_type_${i}`).value = sec[i].type || '';
                        document.getElementById(`in_sec_brand_${i}`).value = sec[i].brand || '';
                        document.getElementById(`in_sec_color_${i}`).value = sec[i].color || '';
                        document.getElementById(`in_sec_or_${i}`).value = sec[i].or || '';
                        document.getElementById(`in_sec_cr_${i}`).value = sec[i].cr || '';
                    }
                } catch (e) { console.log(e); }
            }

            // Load Violation Data
            for (let i = 0; i < 3; i++) {
                ['date', 'time', 'loc', 'desc', 'action', 'officer'].forEach(k => {
                    const el = document.getElementById(`in_vio_${k}_${i}`);
                    if (el) el.value = '';
                });
            }
            if (data.violation_data) {
                try {
                    const vio = JSON.parse(data.violation_data);
                    if (vio.length > 0) new bootstrap.Collapse(document.getElementById('violationCollapse'), { show: true });
                    for (let i = 0; i < vio.length && i < 3; i++) {
                        document.getElementById(`in_vio_date_${i}`).value = vio[i].date || '';
                        document.getElementById(`in_vio_time_${i}`).value = vio[i].time || '';
                        document.getElementById(`in_vio_loc_${i}`).value = vio[i].loc || '';
                        document.getElementById(`in_vio_desc_${i}`).value = vio[i].desc || '';
                        document.getElementById(`in_vio_action_${i}`).value = vio[i].action || '';
                        document.getElementById(`in_vio_officer_${i}`).value = vio[i].officer || '';
                    }
                } catch (e) { console.log(e); }
            }

            updatePreview();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('appForm').reset();
            document.querySelectorAll('.checkbox-box.checked').forEach(el => el.classList.remove('checked'));
            new bootstrap.Collapse(document.getElementById('secVehiclesCollapse'), { toggle: false }).hide();
            new bootstrap.Collapse(document.getElementById('violationCollapse'), { toggle: false }).hide();
            updatePreview();
        }

        function updatePrintButton() {
            const queueCount = <?php echo count($_SESSION['parking_print_queue']); ?>;
            const btn = document.getElementById('printQueueBtn');
            if (btn) btn.disabled = queueCount === 0;
        }

        function printQueue() {
            document.body.classList.add('printing-mode-queue');
            setTimeout(() => { window.print(); document.body.classList.remove('printing-mode-queue'); }, 200);
        }

        function printBlank() {
            document.body.classList.add('printing-mode-blank');
            setTimeout(() => { window.print(); document.body.classList.remove('printing-mode-blank'); }, 200);
        }

        document.addEventListener('DOMContentLoaded', function () {
            updatePreview();
            updatePrintButton();
            setTimeout(() => { document.querySelectorAll('.alert').forEach(a => new bootstrap.Alert(a).close()); }, 5000);
        });
    </script>
</body>

</html>