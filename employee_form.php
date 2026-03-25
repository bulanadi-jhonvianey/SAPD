<?php
// --- employee_form.php ---
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Enable MySQLi exceptions for all errors
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database Credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sapd_db";

try {
    // 1. Create Connection
    $conn = new mysqli($servername, $username, $password);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // 2. Create Database
    $conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
    $conn->select_db($dbname);

    // Table Setup
    $table_sql = "CREATE TABLE IF NOT EXISTS employee_applications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                applicant_type VARCHAR(50) DEFAULT 'EMPLOYEE',
                applicant_name VARCHAR(255) DEFAULT '', 
                department VARCHAR(100) DEFAULT '', 
                address TEXT,
                contact_number VARCHAR(50) DEFAULT '', 
                license_no VARCHAR(50) DEFAULT '', 
                email VARCHAR(100) DEFAULT '', 
                fb_account VARCHAR(100) DEFAULT '',
                vehicle_type VARCHAR(50) DEFAULT '', 
                vehicle_brand VARCHAR(50) DEFAULT '', 
                vehicle_color VARCHAR(50) DEFAULT '', 
                or_no VARCHAR(50) DEFAULT '', 
                cr_no VARCHAR(50) DEFAULT '',
                emerg_name VARCHAR(255) DEFAULT '', 
                emerg_address TEXT, 
                emerg_relation VARCHAR(100) DEFAULT '', 
                emerg_contact VARCHAR(50) DEFAULT '',
                checklist_data TEXT DEFAULT NULL,
                secondary_vehicles TEXT DEFAULT NULL,
                violation_data TEXT DEFAULT NULL,
                image_paths TEXT DEFAULT NULL, 
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";

    if (!$conn->query($table_sql)) {
        throw new Exception("Table creation failed: " . $conn->error);
    }

    // Auto-Repair Columns
    $cols = ['applicant_type', 'applicant_name', 'department', 'address', 'contact_number', 'license_no', 'email', 'fb_account', 'vehicle_type', 'vehicle_brand', 'vehicle_color', 'or_no', 'cr_no', 'emerg_name', 'emerg_address', 'emerg_relation', 'emerg_contact', 'image_paths', 'checklist_data', 'secondary_vehicles', 'violation_data'];
    foreach ($cols as $c) {
        $check_col = $conn->query("SHOW COLUMNS FROM employee_applications LIKE '$c'");
        if ($check_col && $check_col->num_rows == 0) {
            $conn->query("ALTER TABLE employee_applications ADD `$c` TEXT DEFAULT NULL");
        }
    }

    // Session Queue
    if (!isset($_SESSION['employee_print_queue'])) {
        $_SESSION['employee_print_queue'] = [];
    }

    // Upload Directory
    $upload_dir = "uploads/employee/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Helper to print arrays safely
    $getVal = function ($arr, $key) {
        return isset($arr[$key]) ? htmlspecialchars($arr[$key]) : '';
    };

    // --- FORM HANDLERS ---
    $success_msg = "";
    $error_msg = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {

        // Capture all standard fields
        $d = [];
        $standard_fields = ['applicant_type', 'applicant_name', 'department', 'address', 'contact_number', 'license_no', 'email', 'fb_account', 'vehicle_type', 'vehicle_brand', 'vehicle_color', 'or_no', 'cr_no', 'emerg_name', 'emerg_address', 'emerg_relation', 'emerg_contact'];

        foreach ($standard_fields as $field) {
            $d[$field] = trim($_POST[$field] ?? '');
        }

        // Capture Checklist Data (JSON)
        $checklist = [];
        foreach ($_POST as $key => $val) {
            if (strpos($key, 'chk_') === 0) {
                $checklist[$key] = $val;
            }
        }
        $checklist_json = json_encode($checklist);

        // Secondary vehicles and violations are not in the form, store empty JSON
        $sec_vehicles_json = "[]";
        $violation_json = "[]";

        // Handle Images (optional)
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
        if (!empty($uploaded_files)) {
            $image_paths_json = json_encode($uploaded_files);
        } else {
            $image_paths_json = "[]";
        }

        // Insert using prepared statement
        $stmt = $conn->prepare("INSERT INTO employee_applications 
                (applicant_type, applicant_name, department, address, contact_number, license_no, email, fb_account, vehicle_type, vehicle_brand, vehicle_color, or_no, cr_no, emerg_name, emerg_address, emerg_relation, emerg_contact, checklist_data, secondary_vehicles, violation_data, image_paths) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception("Database Prepare Failed: " . $conn->error);
        }

        $stmt->bind_param(
            "sssssssssssssssssssss",
            $d['applicant_type'],
            $d['applicant_name'],
            $d['department'],
            $d['address'],
            $d['contact_number'],
            $d['license_no'],
            $d['email'],
            $d['fb_account'],
            $d['vehicle_type'],
            $d['vehicle_brand'],
            $d['vehicle_color'],
            $d['or_no'],
            $d['cr_no'],
            $d['emerg_name'],
            $d['emerg_address'],
            $d['emerg_relation'],
            $d['emerg_contact'],
            $checklist_json,
            $sec_vehicles_json,
            $violation_json,
            $image_paths_json
        );

        if ($stmt->execute()) {
            $last_id = $stmt->insert_id;
            error_log("Inserted employee application with ID: $last_id");

            // Add to print queue
            $_SESSION['employee_print_queue'][] = array_merge($d, $checklist, [
                'secondary_vehicles' => $sec_vehicles_json,
                'violation_data' => $violation_json,
                'image_paths' => $image_paths_json
            ]);

            $stmt->close();

            // Force session write before redirect
            session_write_close();

            header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . "?success=1");
            exit();
        } else {
            throw new Exception("Database Failed to Save: " . $stmt->error);
        }
    }

    // Handle reprint: add to queue and set flag to print
    if (isset($_GET['reprint_id'])) {
        $id = intval($_GET['reprint_id']);
        $res = $conn->query("SELECT * FROM employee_applications WHERE id = $id");
        if ($res && $res->num_rows > 0) {
            $row_data = $res->fetch_assoc();
            $checks = json_decode($row_data['checklist_data'] ?? '{}', true);
            if (is_array($checks)) {
                $row_data = array_merge($row_data, $checks);
            }
            $_SESSION['employee_print_queue'][] = $row_data;
            $_SESSION['auto_print'] = true;
            session_write_close();
        }
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit();
    }

    // Handle delete
    if (isset($_GET['delete_id'])) {
        $conn->query("DELETE FROM employee_applications WHERE id = " . intval($_GET['delete_id']));
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit();
    }

    // Handle clear queue
    if (isset($_POST['clear_queue'])) {
        $_SESSION['employee_print_queue'] = [];
        session_write_close();
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit();
    }

    // --- SEARCH LOGIC ---
    $search_term = "";
    $where_clause = "";
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_term = $conn->real_escape_string($_GET['search']);
        $where_clause = "WHERE applicant_name LIKE '%$search_term%' OR department LIKE '%$search_term%'";
    }

    $recent_reports = $conn->query("SELECT * FROM employee_applications $where_clause ORDER BY id DESC LIMIT 10");
    $total_count = $conn->query("SELECT COUNT(*) as total FROM employee_applications")->fetch_assoc()['total'] ?? 0;

} catch (Exception $e) {
    $error_msg = $e->getMessage();
    error_log("Employee application error: " . $error_msg);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Parking</title>
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

        /* --- LEFT PANEL FORCED WHITE TEXT --- */
        .left-panel {
            background-color: #13203c !important;
            color: #ffffff !important;
        }

        .left-panel .panel-title,
        .left-panel label,
        .left-panel .form-check-label,
        .left-panel small,
        .left-panel span {
            color: #ffffff !important;
        }

        .left-panel .form-control,
        .left-panel .form-select {
            background-color: #1f2f4e !important;
            color: #ffffff !important;
            border-color: #2c3e50 !important;
        }

        .left-panel .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7) !important;
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
            height: 14in;
            position: relative;
            background: transparent;
            transform: scale(0.6);
            transform-origin: top left;
            margin-right: -3in;
            margin-bottom: -5.6in;
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

        input[readonly].form-control {
            background-color: var(--input-bg);
            color: var(--text-main);
            opacity: 0.8;
            cursor: not-allowed;
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

        /* --- FORM DESIGN (SCREEN & PRINT SHARED) --- */
        .hcc-form {
            width: 8.5in;
            height: 14in;
            background: white;
            color: black;
            padding: 0.35in 0.5in;
            font-family: Arial, sans-serif;
            position: relative;
            box-sizing: border-box;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }

        /* --- NEW HEADER LAYOUT (Fading Bar Integration) --- */
        .new-header-wrapper {
            position: relative;
            width: calc(100% + 1in);
            margin-left: -0.5in;
            margin-right: -0.5in;
            margin-top: -0.25in;
            height: 1.4in;
            margin-bottom: -10px;
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
            width: 140px;
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
            background: linear-gradient(to right,
                    #002b7f 0%,
                    #002b7f 18%,
                    rgba(0, 43, 127, 0.25) 24%,
                    rgba(0, 43, 127, 0.25) 75%,
                    #002b7f 80%,
                    #002b7f 100%);
            width: 100%;
            margin-top: 2px;
            margin-bottom: 4px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .details {
            text-align: center;
            margin-left: 220px;
            color: #000000;
            font-size: 9pt;
            line-height: 1.2;
            font-family: Arial, sans-serif;
        }

        /* --- DIVISION TITLE DESIGN --- */
        .division-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 10px;
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
            font-size: 14pt;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .division-title h3 {
            font-family: "Arial", sans-serif;
            font-weight: bold;
            font-size: 11pt;
            margin: 2px 0 0 0;
            text-transform: uppercase;
        }

        .employee-title {
            font-family: Arial, sans-serif;
            font-weight: 900;
            font-size: 18pt;
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
            margin-bottom: 8px;
            font-weight: bold;
            font-family: Arial, sans-serif;
        }

        .data-grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            border: 1px solid black;
            margin-top: 5px;
        }

        .data-grid td {
            border: 1px solid black;
            padding: 8px 5px;
            vertical-align: middle;
            height: 32px;
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

        .value {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-weight: bold;
            color: #000;
            font-size: 11pt;
            width: 32%;
            text-transform: none;
        }

        .emerg-header {
            background-color: #d9d9d9;
            font-weight: bold;
            text-align: center;
            border: 1px solid black;
            border-bottom: none;
            font-size: 9pt;
            padding: 4px;
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
            padding: 10px 5px;
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

        .emerg-val {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-weight: bold;
            border-bottom: 1px solid black;
            display: inline-block;
            width: calc(100% - 70px);
            text-transform: none;
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
            padding: 6px;
            text-transform: uppercase;
            vertical-align: middle;
            line-height: 1.2;
            height: 30px;
            color: black;
            font-family: Arial, sans-serif;
        }

        .mv-table td {
            border: 1px solid black;
            height: 30px;
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
            padding-bottom: 2px;
        }

        .checklist {
            font-size: 9pt;
            line-height: 1.4;
        }

        .mb-1 {
            margin-bottom: 4px;
        }

        .id-cell {
            width: 2.2in;
            text-align: right;
            vertical-align: top;
            padding-right: 5px;
        }

        .id-box {
            width: 2in;
            height: 2in;
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
            padding-bottom: 0px;
        }

        /* VIOLATION TABLE (BACK) */
        .violation-table th,
        .violation-table td {
            border: 1px solid black;
            padding: 6px;
            text-align: center;
            font-family: Arial, sans-serif;
            font-size: 9pt;
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
            height: 25px;
        }

        /* WAIVER SPACING ADJUSTMENTS */
        .waiver-text {
            font-size: 17px;
            text-align: justify;
            margin-top: 5px;
            line-height: 1.1;
        }

        .waiver-text ol {
            padding-left: 25px;
            margin-top: 0px;
            margin-bottom: 0px;
        }

        .waiver-text li {
            margin-bottom: 0px;
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
                size: legal;
                margin: 0;
            }

            body {
                background: white !important;
                color: black !important;
                margin: 0 !important;
                padding: 0 !important;
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
                height: auto !important;
                min-height: 100% !important;
                margin: 0 auto !important;
                padding: 0.25in 0.4in !important;
                box-shadow: none !important;
                transform: none !important;
                page-break-after: always !important;
                page-break-inside: avoid !important;
                overflow: hidden !important;
                display: block !important;
                position: relative !important;
                left: 0 !important;
                top: 0 !important;
            }

            .hcc-form:last-child {
                page-break-after: auto !important;
            }

            .new-header-wrapper {
                margin-top: -0.25in !important;
                margin-bottom: -15px !important;
                margin-left: -0.4in !important;
                margin-right: -0.4in !important;
                padding-top: 0 !important;
            }

            /* --- OVERRIDE FONT SIZES FOR PRINTOUT --- */
            .data-grid td,
            .label,
            .value,
            .emerg-table td,
            .emerg-label,
            .emerg-val,
            .mv-table th,
            .mv-table td,
            .docs-table td,
            .checklist,
            .sig-table td,
            .sig-table div,
            .violation-table th,
            .violation-table td,
            .details,
            .status-checkboxes,
            .file-info {
                font-size: 11pt !important;
            }

            .waiver-text,
            .waiver-text li,
            .waiver-text p {
                font-size: 17px !important;
                line-height: 1.1 !important;
                margin-bottom: 0 !important;
            }

            .new-header-title {
                font-size: 28pt !important;
            }

            .division-title h2 {
                font-size: 14pt !important;
            }

            .division-title h3 {
                font-size: 11pt !important;
            }

            .employee-title {
                font-size: 16pt !important;
            }

            .division-header {
                margin-bottom: 5px !important;
            }

            .violation-table {
                margin-top: 10px !important;
            }

            .data-grid td {
                height: 25px !important;
                padding: 4px 5px !important;
            }

            .violation-table td {
                height: 20px !important;
                padding: 4px !important;
            }

            .emerg-header {
                margin-top: 5px !important;
                padding: 2px !important;
            }

            .mv-table {
                margin-top: 5px !important;
            }

            .docs-table {
                margin-top: 5px !important;
            }

            .sig-table {
                margin-top: 10px !important;
            }

            .sig-table td {
                padding-bottom: 0px !important;
            }

            .waiver-text {
                margin-top: 5px !important;
            }

            .fading-bar,
            .divider-line {
                print-color-adjust: exact !important;
                -webkit-print-color-adjust: exact !important;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        /* ========== TOP ALIGNMENT FIX ========== */
        .data-grid td,
        .data-grid th {
            vertical-align: top !important;
            padding-top: 4px !important;
            line-height: 1.3 !important;
        }

        .data-grid td *,
        .data-grid th * {
            line-height: inherit !important;
        }

        .sig-table td {
            vertical-align: top !important;
            line-height: 1.3 !important;
        }
    </style>
</head>

<body>

    <div class="navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-secondary fw-bold"><i class="fa fa-arrow-left me-2"></i> Back</a>
            <h4 class="m-0 fw-bold text-white">Employee Parking</h4>
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
                <div class="badge-queue">QUEUE: <?php echo count($_SESSION['employee_print_queue']); ?></div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class='alert alert-success alert-dismissible fade show'>
                    <i class="fa fa-check-circle me-2"></i>Application saved and added to queue!
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class='alert alert-danger alert-dismissible fade show'
                    style="background-color: #ffcccc; color: #cc0000; border: 2px solid #cc0000;">
                    <strong><i class="fa fa-exclamation-triangle me-2"></i> CRITICAL ERROR DETECTED:</strong><br>
                    <?php echo $error_msg; ?>
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="appForm">
                <input type="hidden" name="submit_application" value="1">

                <label class="small opacity-75 fw-bold mb-1">APPLICATION STATUS</label>
                <div class="d-flex gap-3 mb-2">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="chk_approved"
                            id="in_chk_approved" value="1" onchange="syncStatus('approved')"><label
                            class="form-check-label" for="in_chk_approved">Approved</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="chk_disapproved"
                            id="in_chk_disapproved" value="1" onchange="syncStatus('disapproved')"><label
                            class="form-check-label" for="in_chk_disapproved">Disapproved</label></div>
                </div>

                <label class="small opacity-75 fw-bold mb-1">APPLICATION TYPE</label>
                <input type="text" name="applicant_type" id="in_type" class="form-control fw-bold" value="EMPLOYEE"
                    readonly>

                <label class="small opacity-75 fw-bold mb-1">APPLICANT DETAILS</label>
                <input type="text" name="applicant_name" id="in_name" class="form-control"
                    placeholder="Name (Last, First, MI)" oninput="updatePreview()">
                <div class="row g-2">
                    <div class="col-6"><input type="text" name="department" id="in_dept" class="form-control"
                            placeholder="Department" oninput="updatePreview()"></div>
                    <div class="col-6"><input type="text" name="contact_number" id="in_cel" class="form-control"
                            placeholder="Cel No." oninput="updatePreview()"></div>
                </div>
                <input type="text" name="address" id="in_address" class="form-control" placeholder="Address"
                    oninput="updatePreview()">

                <div class="row g-2">
                    <div class="col-6"><input type="text" name="license_no" id="in_license" class="form-control"
                            placeholder="License #" oninput="updatePreview()"></div>
                    <div class="col-6"><input type="email" name="email" id="in_email" class="form-control"
                            placeholder="Email" oninput="updatePreview()"></div>
                </div>

                <label class="small opacity-75 fw-bold mb-1 mt-2">VEHICLE INFO (Main)</label>
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

                <label class="small opacity-75 fw-bold mb-1 mt-2">EMERGENCY CONTACT</label>
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

                <label class="small opacity-75 fw-bold mb-1 mt-2">DOCUMENTS SUBMITTED</label>
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

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary flex-grow-1 fw-bold py-3 mt-2"><i
                            class="fa fa-plus-circle me-2"></i> ADD
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
                <?php if (count($_SESSION['employee_print_queue']) > 0): ?>
                    <div class="col-12">
                        <form method="POST" class="m-0">
                            <input type="hidden" name="clear_queue" value="1">
                            <button type="submit" class="btn btn-danger w-100 fw-bold"
                                onclick="return confirm('Clear queue?')"><i class="fa fa-trash me-2"></i> Clear
                                Queue</button>
                        </form>
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
                            <img src="background.png" alt="SAPD Logo" class="sapd-logo">
                            <div class="division-title">
                                <h2>SAFETY AND PROTECTION DIVISION</h2>
                                <h3>APPLICATION FOR EMPLOYEES VEHICLE PARKING SPACE (SY 2026-2027)</h3>
                                <h1 class="employee-title" id="out_type_preview">EMPLOYEE</h1>
                                <div class="status-checkboxes"><span class="checkbox-box" id="view_chk_approved"></span>
                                    Approved &nbsp;&nbsp;&nbsp; <span class="checkbox-box"
                                        id="view_chk_disapproved"></span>
                                    Disapproved</div>
                            </div>
                        </div>

                        <div class="file-info"><span>File Application # ____________</span><span>Date:
                                ___________</span></div>
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
                                    <div style="width: 300px; margin-left: 0;">
                                        <div style="text-align: center; font-weight: bold; margin-bottom: 5px;"
                                            id="out_sig_name"></div>
                                        <div style="border-top: 1px solid black; margin-bottom: 5px;"></div>
                                        <div style="margin-bottom: 15px; font-size: 10pt; text-align: center;">Signature
                                            over printed name of <span id="out_sig_preview">employee</span></div>
                                    </div>
                                    <div style="margin-bottom: 10px; font-weight: bold;">Approved by:</div>
                                    <div style="font-weight:bold; font-size: 11pt;">PAUL JEFFREY T. LANSANGAN, SO3</div>
                                    <div style="font-size: 10pt;">CHIEF, Safety and Protection</div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="form-slide">
                    <div class="hcc-form">

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

                        <table class="violation-table"
                            style="width:100%; border-collapse:collapse; margin-top:10px; font-size:9pt;">
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
                        <div style="text-align:center; margin-top:10px; font-family:Arial, sans-serif;">
                            <h4 style="margin:0; font-weight:bold; text-decoration:underline; font-size: 11pt;">Mga
                                Patakaran ng Parking sa Holy Cross College, Sta. Ana, Pampanga</h4>
                            <h5 style="margin:5px 0 0 0; font-weight:normal; font-size: 10pt;">SY 2026-2027</h5>
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
                        <div style="margin-top:45px; font-size:10pt; font-family:Arial, sans-serif;">
                            <p style="margin-left: 20px; margin-bottom: 5px;">Ako ay sumasang-ayon sa mga patakaran ng
                                parking sa Holy Cross College.</p>
                            <div
                                style="margin-top:60px; margin-left: 20px; width:300px; border-top:1px solid black; text-align:center; padding-top: 5px;">
                                Pangalan at lagda ng <span id="out_sig_fil_preview">Empleyado</span>
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
                        <?php if (!empty($search_term)): ?><a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>"
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
                        <th class="text-center">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_reports && $recent_reports->num_rows > 0): ?>
                        <?php while ($row = $recent_reports->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td><span
                                        class="badge bg-info text-white"><?php echo htmlspecialchars($row['applicant_type'] ?? 'EMPLOYEE'); ?></span>
                                </td>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['applicant_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['department'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(($row['vehicle_brand'] ?? '') . ' ' . ($row['vehicle_color'] ?? '')); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['contact_number'] ?? ''); ?></td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button onclick='showViewModal(<?php echo json_encode($row); ?>)'
                                            class="btn btn-sm btn-info text-white" title="View">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        <button onclick='loadData(<?php echo json_encode($row); ?>)'
                                            class="btn btn-sm btn-warning text-white" title="Edit">
                                            <i class="fa fa-pencil-alt"></i>
                                        </button>
                                        <a href="?reprint_id=<?php echo $row['id']; ?>"
                                            class="btn btn-sm btn-success text-white" title="Reprint">
                                            <i class="fa fa-print"></i>
                                        </a>
                                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger"
                                            title="Delete" onclick="return confirm('Delete this record?')">
                                            <i class="fa fa-trash"></i>
                                        </a>
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

    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background-color: var(--panel-bg); color: var(--text-main);">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalLabel">Application Details (Read-Only)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Name:</div>
                        <div class="col-md-9" id="view_name"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Department:</div>
                        <div class="col-md-9" id="view_dept"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Address:</div>
                        <div class="col-md-9" id="view_address"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Contact No.:</div>
                        <div class="col-md-9" id="view_contact"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">License No.:</div>
                        <div class="col-md-9" id="view_license"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Email:</div>
                        <div class="col-md-9" id="view_email"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">FB Account:</div>
                        <div class="col-md-9" id="view_fb"></div>
                    </div>
                    <hr>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Vehicle Type:</div>
                        <div class="col-md-9" id="view_vtype"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Vehicle Brand:</div>
                        <div class="col-md-9" id="view_vbrand"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Vehicle Color:</div>
                        <div class="col-md-9" id="view_vcolor"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">OR No.:</div>
                        <div class="col-md-9" id="view_or"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">CR No.:</div>
                        <div class="col-md-9" id="view_cr"></div>
                    </div>
                    <hr>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Emergency Name:</div>
                        <div class="col-md-9" id="view_ename"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Emergency Address:</div>
                        <div class="col-md-9" id="view_eaddress"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Emergency Relation:</div>
                        <div class="col-md-9" id="view_erelation"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Emergency Contact:</div>
                        <div class="col-md-9" id="view_econtact"></div>
                    </div>
                    <hr>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Documents Submitted:</div>
                        <div class="col-md-9" id="view_docs"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Secondary Vehicles:</div>
                        <div class="col-md-9" id="view_sec_vehicles"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 fw-bold">Violation History:</div>
                        <div class="col-md-9" id="view_violations"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="print-area">
        <?php if (count($_SESSION['employee_print_queue']) > 0): ?>
            <?php foreach ($_SESSION['employee_print_queue'] as $p): ?>

                <div class="hcc-form">

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
                        <img src="background.png" alt="SAPD Logo" class="sapd-logo">
                        <div class="division-title">
                            <h2>SAFETY AND PROTECTION DIVISION</h2>
                            <h3>APPLICATION FOR EMPLOYEES VEHICLE PARKING SPACE (SY 2026-2027)</h3>
                            <h1 class="employee-title"><?php echo strtoupper($getVal($p, 'applicant_type')); ?></h1>
                            <div class="status-checkboxes">
                                <span
                                    class="checkbox-box <?php echo $getVal($p, 'chk_approved') == '1' ? 'checked' : ''; ?>"></span>
                                Approved &nbsp;&nbsp;&nbsp;
                                <span
                                    class="checkbox-box <?php echo $getVal($p, 'chk_disapproved') == '1' ? 'checked' : ''; ?>"></span>
                                Disapproved
                            </div>
                        </div>
                    </div>

                    <div class="file-info"><span>File Application # ____________</span><span>Date: ___________</span></div>

                    <table class="data-grid">
                        <tr>
                            <td class="label">NAME <br><span style="font-size:7pt; font-weight:normal">(Last, First, MI)</span>
                            </td>
                            <td class="value" colspan="2"><?php echo $getVal($p, 'applicant_name'); ?></td>
                            <td class="label">DEPARTMENT</td>
                            <td class="value"><?php echo $getVal($p, 'department'); ?></td>
                        </tr>
                        <tr>
                            <td class="label">ADDRESS</td>
                            <td class="value" colspan="2"><?php echo $getVal($p, 'address'); ?></td>
                            <td class="label">MOTORIZED VEHICLE TYPE</td>
                            <td class="value"><?php echo $getVal($p, 'vehicle_type'); ?></td>
                        </tr>
                        <tr>
                            <td class="label">CEL. NO.</td>
                            <td class="value" colspan="2"><?php echo $getVal($p, 'contact_number'); ?></td>
                            <td class="label">MOTORIZED VEHICLE BRAND</td>
                            <td class="value"><?php echo $getVal($p, 'vehicle_brand'); ?></td>
                        </tr>
                        <tr>
                            <td class="label">LICENSE #</td>
                            <td class="value" colspan="2"><?php echo $getVal($p, 'license_no'); ?></td>
                            <td class="label">MOTORIZED VEHICLE COLOR</td>
                            <td class="value"><?php echo $getVal($p, 'vehicle_color'); ?></td>
                        </tr>
                        <tr>
                            <td class="label">OR #</td>
                            <td class="value" colspan="2"><?php echo $getVal($p, 'or_no'); ?></td>
                            <td class="label">CR #</td>
                            <td class="value"><?php echo $getVal($p, 'cr_no'); ?></td>
                        </tr>
                        <tr>
                            <td class="label">E-MAIL</td>
                            <td class="value" colspan="2"><?php echo $getVal($p, 'email'); ?></td>
                            <td class="label" style="font-size: 7pt;">VALID/WORKING FACEBOOK ACCOUNT</td>
                            <td class="value"><?php echo $getVal($p, 'fb_account'); ?></td>
                        </tr>
                    </table>

                    <div class="emerg-header">PERSON TO NOTIFY IN CASE OF EMERGENCY</div>
                    <table class="emerg-table">
                        <tr>
                            <td style="width: 60%; padding-left: 10px;">
                                <div style="margin-bottom: 5px;"><span class="emerg-label">Name:</span> <span
                                        class="emerg-val"><?php echo $getVal($p, 'emerg_name'); ?></span></div>
                                <div style="margin-bottom: 5px;"><span class="emerg-label">Address:</span> <span
                                        class="emerg-val"><?php echo $getVal($p, 'emerg_address'); ?></span></div>
                                <div><span class="emerg-label">Relation:</span> <span
                                        class="emerg-val"><?php echo $getVal($p, 'emerg_relation'); ?></span></div>
                            </td>
                            <td style="width: 40%; vertical-align: top;">
                                <div style="font-weight:bold; font-size:8pt; margin-bottom:5px;">Contact number(s):</div>
                                <div
                                    style="font-family:'Calibri', 'Arial', sans-serif; font-weight:bold; font-size:12pt; text-align:center; padding-top:15px;">
                                    <?php echo $getVal($p, 'emerg_contact'); ?>
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
                            echo "<td>" . htmlspecialchars($v ? ($v['type'] ?? '') : '') . "</td>";
                            echo "<td>" . htmlspecialchars($v ? ($v['brand'] ?? '') : '') . "</td>";
                            echo "<td>" . htmlspecialchars($v ? ($v['color'] ?? '') : '') . "</td>";
                            echo "<td>" . htmlspecialchars($v ? ($v['or'] ?? '') : '') . "</td>";
                            echo "<td>" . htmlspecialchars($v ? ($v['cr'] ?? '') : '') . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </table>

                    <table class="docs-table">
                        <tr>
                            <td class="checklist">
                                <div style="margin-bottom: 5px;"><strong>Documents Submitted:</strong></div>
                                <div class="mb-1"><span
                                        class="checkbox-box <?php echo $getVal($p, 'chk_cr') == '1' ? 'checked' : ''; ?>"></span>
                                    Certificate of Registration (CR)</div>
                                <div class="mb-1"><span
                                        class="checkbox-box <?php echo $getVal($p, 'chk_or') == '1' ? 'checked' : ''; ?>"></span>
                                    Official Receipt (OR)</div>
                                <div style="margin-top: 10px; margin-bottom: 5px;"><strong>Updated/registered drivers
                                        License:</strong></div>
                                <div class="mb-1"><span
                                        class="checkbox-box <?php echo $getVal($p, 'chk_student_lic') == '1' ? 'checked' : ''; ?>"></span>
                                    Student Drivers License</div>
                                <div class="mb-1"><span
                                        class="checkbox-box <?php echo $getVal($p, 'chk_nonpro_lic') == '1' ? 'checked' : ''; ?>"></span>
                                    Non-Pro Drivers License</div>
                                <div class="mb-1"><span
                                        class="checkbox-box <?php echo $getVal($p, 'chk_pro_lic') == '1' ? 'checked' : ''; ?>"></span>
                                    Professional Drivers License</div>
                                <div style="margin-top: 10px; margin-bottom: 2px;"><span
                                        class="checkbox-box <?php echo $getVal($p, 'chk_id_2x2') == '1' ? 'checked' : ''; ?>"></span>
                                    Updated 1 2"x2" colored ID picture (White background)</div>
                                <div class="mb-1"><span
                                        class="checkbox-box <?php echo $getVal($p, 'chk_id_1x1') == '1' ? 'checked' : ''; ?>"></span>
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
                                <div style="width: 300px; margin-left: 0;">
                                    <div style="text-align: center; font-weight: bold; margin-bottom: 5px;">
                                        <?php echo $getVal($p, 'applicant_name'); ?></div>
                                    <div style="border-top: 1px solid black; margin-bottom: 5px;"></div>
                                    <div style="margin-bottom: 15px; font-size: 10pt; text-align: center;">Signature over
                                        printed name of <?php echo strtolower($getVal($p, 'applicant_type')); ?></div>
                                </div>
                                <div style="margin-bottom: 10px; font-weight: bold;">Approved by:</div>
                                <div style="font-weight:bold; font-size: 11pt;">PAUL JEFFREY T. LANSANGAN, SO3</div>
                                <div style="font-size: 10pt;">CHIEF, Safety and Protection</div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="hcc-form">

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

                    <table class="violation-table"
                        style="width:100%; border-collapse:collapse; margin-top:10px; font-size:9pt;">
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
                                echo "<td>" . htmlspecialchars($v ? ($v['date'] ?? '') : '') . "</td>";
                                echo "<td>" . htmlspecialchars($v ? ($v['time'] ?? '') : '') . "</td>";
                                echo "<td>" . htmlspecialchars($v ? ($v['loc'] ?? '') : '') . "</td>";
                                echo "<td>" . htmlspecialchars($v ? ($v['desc'] ?? '') : '') . "</td>";
                                echo "<td>" . htmlspecialchars($v ? ($v['action'] ?? '') : '') . "</td>";
                                echo "<td>" . htmlspecialchars($v ? ($v['officer'] ?? '') : '') . "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <div style="text-align:center; margin-top:10px; font-family:Arial, sans-serif;">
                        <h4 style="margin:0; font-weight:bold; text-decoration:underline; font-size: 11pt;">Mga Patakaran ng
                            Parking sa Holy Cross College, Sta. Ana, Pampanga</h4>
                        <h5 style="margin:5px 0 0 0; font-weight:normal; font-size: 10pt;">SY 2026-2027</h5>
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
                    <div style="margin-top:45px; font-size:10pt; font-family:Arial, sans-serif;">
                        <p style="margin-left: 20px; margin-bottom: 5px;">Ako ay sumasang-ayon sa mga patakaran ng parking sa
                            Holy Cross College.</p>
                        <div
                            style="margin-top:60px; margin-left: 20px; width:300px; border-top:1px solid black; text-align:center; padding-top: 5px;">
                            Pangalan at lagda ng <span id="out_sig_fil_blank">Empleyado</span>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="print-blank-area">

        <div class="hcc-form">

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
                <img src="background.png" alt="SAPD Logo" class="sapd-logo">
                <div class="division-title">
                    <h2>SAFETY AND PROTECTION DIVISION</h2>
                    <h3>APPLICATION FOR EMPLOYEES VEHICLE PARKING SPACE (SY 2026-2027)</h3>
                    <h1 class="employee-title" id="out_type_blank">EMPLOYEE</h1>
                    <div class="status-checkboxes"><span class="checkbox-box"></span> Approved &nbsp;&nbsp;&nbsp; <span
                            class="checkbox-box"></span> Disapproved</div>
                </div>
            </div>

            <div class="file-info"><span>File Application # ____________</span><span>Date: ___________</span></div>
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
                        <div style="width: 300px; margin-left: 0;">
                            <div style="text-align: center; font-weight: bold; margin-bottom: 5px;"></div>
                            <div style="border-top: 1px solid black; margin-bottom: 5px;"></div>
                            <div style="margin-bottom: 15px; font-size: 10pt; text-align: center;">Signature over
                                printed name of <span id="out_sig_blank">employee</span></div>
                        </div>
                        <div style="margin-bottom: 10px; font-weight: bold;">Approved by:</div>
                        <div style="font-weight:bold; font-size: 11pt;">PAUL JEFFREY T. LANSANGAN, SO3</div>
                        <div style="font-size: 10pt;">CHIEF, Safety and Protection</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="hcc-form">

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

            <table class="violation-table"
                style="width:100%; border-collapse:collapse; margin-top:10px; font-size:9pt;">
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
                        <td></td>
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
                        <td></td>
                    </tr>
                    <tr>
                        <td></td>
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
                        <td></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
            <div style="text-align:center; margin-top:10px; font-family:Arial, sans-serif;">
                <h4 style="margin:0; font-weight:bold; text-decoration:underline; font-size: 11pt;">Mga Patakaran ng
                    Parking sa Holy Cross College, Sta. Ana, Pampanga</h4>
                <h5 style="margin:5px 0 0 0; font-weight:normal; font-size: 10pt;">SY 2026-2027</h5>
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
            <div style="margin-top:45px; font-size:10pt; font-family:Arial, sans-serif;">
                <p style="margin-left: 20px; margin-bottom: 5px;">Ako ay sumasang-ayon sa mga patakaran ng parking sa
                    Holy Cross College.</p>
                <div
                    style="margin-top:60px; margin-left: 20px; width:300px; border-top:1px solid black; text-align:center; padding-top: 5px;">
                    Pangalan at lagda ng <span id="out_sig_fil_blank">Empleyado</span>
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

            // Secondary Vehicles (no inputs now)
            for (let i = 0; i < 2; i++) {
                ['type', 'brand', 'color', 'or', 'cr'].forEach(type => {
                    const output = document.getElementById(`out_sec_${type}_${i}`);
                    if (output) output.innerText = '';
                });
            }

            // Violation History (no inputs now)
            for (let i = 0; i < 3; i++) {
                ['date', 'time', 'loc', 'desc', 'action', 'officer'].forEach(type => {
                    const output = document.getElementById(`out_vio_${type}_${i}`);
                    if (output) output.innerText = '';
                });
            }

            const nameInput = document.getElementById('in_name');
            const sigNameOut = document.getElementById('out_sig_name');
            if (nameInput && sigNameOut) sigNameOut.innerText = nameInput.value;

            // Locked to EMPLOYEE type
            const type = 'EMPLOYEE';
            const previewHeader = document.getElementById('out_type_preview');
            const blankHeader = document.getElementById('out_type_blank');
            if (previewHeader) previewHeader.innerText = type;
            if (blankHeader) blankHeader.innerText = type;

            const sigType = type.toLowerCase();
            const sigPreview = document.getElementById('out_sig_preview');
            const sigBlank = document.getElementById('out_sig_blank');
            if (sigPreview) sigPreview.innerText = sigType;
            if (sigBlank) sigBlank.innerText = sigType;

            const filipinoType = 'Empleyado';
            const sigFilPreview = document.getElementById('out_sig_fil_preview');
            const sigFilBlank = document.getElementById('out_sig_fil_blank');
            if (sigFilPreview) sigFilPreview.innerText = filipinoType;
            if (sigFilBlank) sigFilBlank.innerText = filipinoType;
        }

        function loadData(data) {
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

            // Secondary vehicles and violations are not in the form anymore, so skip loading them
            updatePreview();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // View Modal (Read-Only)
        function showViewModal(data) {
            // Populate modal fields
            document.getElementById('view_name').innerText = data.applicant_name || '';
            document.getElementById('view_dept').innerText = data.department || '';
            document.getElementById('view_address').innerText = data.address || '';
            document.getElementById('view_contact').innerText = data.contact_number || '';
            document.getElementById('view_license').innerText = data.license_no || '';
            document.getElementById('view_email').innerText = data.email || '';
            document.getElementById('view_fb').innerText = data.fb_account || '';
            document.getElementById('view_vtype').innerText = data.vehicle_type || '';
            document.getElementById('view_vbrand').innerText = data.vehicle_brand || '';
            document.getElementById('view_vcolor').innerText = data.vehicle_color || '';
            document.getElementById('view_or').innerText = data.or_no || '';
            document.getElementById('view_cr').innerText = data.cr_no || '';
            document.getElementById('view_ename').innerText = data.emerg_name || '';
            document.getElementById('view_eaddress').innerText = data.emerg_address || '';
            document.getElementById('view_erelation').innerText = data.emerg_relation || '';
            document.getElementById('view_econtact').innerText = data.emerg_contact || '';

            // Documents
            let docsHtml = '';
            const checkMap = ['chk_cr', 'chk_or', 'chk_student_lic', 'chk_nonpro_lic', 'chk_pro_lic', 'chk_id_2x2', 'chk_id_1x1'];
            if (data.checklist_data) {
                try {
                    const checks = JSON.parse(data.checklist_data);
                    docsHtml = '<ul>';
                    for (let key of checkMap) {
                        if (checks[key] == '1') {
                            let label = key.replace('chk_', '').toUpperCase().replace('_', ' ');
                            docsHtml += `<li>${label}</li>`;
                        }
                    }
                    docsHtml += '</ul>';
                } catch (e) { docsHtml = 'N/A'; }
            } else {
                docsHtml = 'N/A';
            }
            document.getElementById('view_docs').innerHTML = docsHtml;

            // Secondary Vehicles
            let secHtml = '';
            if (data.secondary_vehicles) {
                try {
                    const sec = JSON.parse(data.secondary_vehicles);
                    if (sec.length > 0) {
                        secHtml = '<ul>';
                        sec.forEach(v => {
                            secHtml += `<li>${v.type || ''} ${v.brand || ''} ${v.color || ''} OR:${v.or || ''} CR:${v.cr || ''}</li>`;
                        });
                        secHtml += '</ul>';
                    } else {
                        secHtml = 'None';
                    }
                } catch (e) { secHtml = 'N/A'; }
            } else {
                secHtml = 'None';
            }
            document.getElementById('view_sec_vehicles').innerHTML = secHtml;

            // Violations
            let vioHtml = '';
            if (data.violation_data) {
                try {
                    const vio = JSON.parse(data.violation_data);
                    if (vio.length > 0) {
                        vioHtml = '<table class="table table-sm table-bordered"><thead><tr><th>Date</th><th>Time</th><th>Location</th><th>Violation</th><th>Action</th><th>Officer</th></tr></thead><tbody>';
                        vio.forEach(v => {
                            vioHtml += `<tr><td>${v.date || ''}</td><td>${v.time || ''}</td><td>${v.loc || ''}</td><td>${v.desc || ''}</td><td>${v.action || ''}</td><td>${v.officer || ''}</td></tr>`;
                        });
                        vioHtml += '</tbody></table>';
                    } else {
                        vioHtml = 'None';
                    }
                } catch (e) { vioHtml = 'N/A'; }
            } else {
                vioHtml = 'None';
            }
            document.getElementById('view_violations').innerHTML = vioHtml;

            // Show modal
            var modal = new bootstrap.Modal(document.getElementById('viewModal'));
            modal.show();
        }

        function resetForm() {
            document.getElementById('appForm').reset();
            document.getElementById('in_type').value = 'EMPLOYEE';
            document.querySelectorAll('.checkbox-box.checked').forEach(el => el.classList.remove('checked'));
            updatePreview();
        }

        function updatePrintButton() {
            const queueCount = <?php echo count($_SESSION['employee_print_queue']); ?>;
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

        // Auto print if reprint was triggered
        <?php if (isset($_SESSION['auto_print']) && $_SESSION['auto_print'] === true): ?>
            window.addEventListener('load', function () {
                printQueue();
            });
            <?php unset($_SESSION['auto_print']); ?>
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', function () {
            updatePreview();
            updatePrintButton();
            setTimeout(() => { document.querySelectorAll('.alert').forEach(a => new bootstrap.Alert(a).close()); }, 5000);
        });
    </script>
</body>

</html>