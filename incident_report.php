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

// Table Setup
$table_sql = "CREATE TABLE IF NOT EXISTS incident_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_title VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    incident_date DATE NOT NULL,
    incident_time TIME NOT NULL,
    description TEXT NOT NULL,
    image_paths TEXT DEFAULT NULL, 
    status VARCHAR(50) DEFAULT 'Recorded',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($table_sql);

// Auto-Repair Columns
$required_columns = [
    'case_title' => 'VARCHAR(255) NOT NULL',
    'location' => 'VARCHAR(255) NOT NULL',
    'incident_date' => 'DATE NOT NULL',
    'incident_time' => 'TIME NOT NULL',
    'description' => 'TEXT NOT NULL',
    'image_paths' => 'TEXT DEFAULT NULL'
];

foreach ($required_columns as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM incident_reports LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE incident_reports ADD $col $def");
    } else if ($check && $col === 'image_paths') {
        $row = $check->fetch_assoc();
        if (strpos(strtolower($row['Type']), 'varchar') !== false) {
            $conn->query("ALTER TABLE incident_reports CHANGE $col $col $def");
        }
    }
}

// Session Queue
if (!isset($_SESSION['incident_print_queue'])) {
    $_SESSION['incident_print_queue'] = [];
}

// Upload Directory
$upload_dir = "uploads/incidents/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// --- 2. FORM HANDLERS ---
$success_msg = "";
$error_msg = "";

// HANDLE: ADD REQUEST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $case = $conn->real_escape_string($_POST['case_title']);
    $loc = $conn->real_escape_string($_POST['location']);
    $date = $conn->real_escape_string($_POST['incident_date']);
    $time = $conn->real_escape_string($_POST['incident_time']);
    $desc = $conn->real_escape_string($_POST['description']);

    $image_paths_json = null;
    $uploaded_files = [];
    $upload_errors = [];

    // Handle Multiple Image Uploads
    if (isset($_FILES['incident_images']) && !empty($_FILES['incident_images']['name'][0])) {
        $total_files = count($_FILES['incident_images']['name']);
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['incident_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['incident_images']['tmp_name'][$i];
                $file_name = basename($_FILES['incident_images']['name'][$i]);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (in_array($file_ext, $allowed_exts)) {
                    $new_file_name = uniqid('inc_') . '_' . $i . '.' . $file_ext;
                    $target_path = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp, $target_path)) {
                        $uploaded_files[] = $target_path;
                    } else {
                        $upload_errors[] = "Failed to move file: $file_name";
                    }
                } else {
                    $upload_errors[] = "Invalid file type: $file_name";
                }
            }
        }
    }

    if (!empty($upload_errors)) {
        $error_msg = "<strong>Upload Errors:</strong><br>" . implode("<br>", $upload_errors);
    }

    if (empty($error_msg)) {
        if (!empty($uploaded_files)) {
            $image_paths_json = json_encode($uploaded_files);
        }

        $stmt = $conn->prepare("INSERT INTO incident_reports (case_title, location, incident_date, incident_time, description, image_paths) VALUES (?, ?, ?, ?, ?, ?)");

        if ($stmt === false) {
            $error_msg = "<strong>Database Error:</strong> " . $conn->error;
        } else {
            $stmt->bind_param("ssssss", $case, $loc, $date, $time, $desc, $image_paths_json);

            if ($stmt->execute()) {
                $_SESSION['incident_print_queue'][] = [
                    'case' => strtoupper($case),
                    'loc' => strtoupper($loc),
                    'date' => $date,
                    'time' => $time,
                    'desc' => $desc,
                    'image_paths' => $uploaded_files
                ];
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
            } else {
                $error_msg = "<strong>Save Failed:</strong> " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// HANDLE: DELETE LOG
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $res = $conn->query("SELECT image_paths FROM incident_reports WHERE id = $del_id");
    if ($row = $res->fetch_assoc()) {
        $paths = json_decode($row['image_paths'], true);
        if (is_array($paths)) {
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }
    $conn->query("DELETE FROM incident_reports WHERE id = $del_id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// HANDLE: CLEAR QUEUE
if (isset($_POST['clear_queue'])) {
    $_SESSION['incident_print_queue'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['success']))
    $success_msg = "Incident recorded successfully!";
if (isset($_GET['error']))
    $error_msg = "An error occurred.";

// --- SEARCH LOGIC ---
$search_term = "";
$where_clause = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = $conn->real_escape_string($_GET['search']);
    $where_clause = "WHERE case_title LIKE '%$search_term%' OR location LIKE '%$search_term%'";
}

$recent_reports = $conn->query("SELECT * FROM incident_reports $where_clause ORDER BY id DESC LIMIT 10");
$total_count = $conn->query("SELECT COUNT(*) as total FROM incident_reports")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Report</title>
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

        .btn-info {
            background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
            color: white;
        }
        
        .btn-warning {
             background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
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

        .form-control {
            background-color: var(--input-bg);
            border: 1px solid var(--border);
            color: var(--text-main);
            margin-bottom: 10px;
            padding: 12px;
        }

        .form-control:focus {
            background-color: var(--input-bg);
            border-color: var(--accent);
            color: var(--text-main);
            box-shadow: none;
        }

        .input-group .btn-outline-secondary {
            background-color: var(--input-bg);
            color: var(--text-main);
            border-color: var(--border);
            border-left: none;
        }

        .input-group .btn-outline-secondary:hover {
            background-color: var(--accent);
            color: white;
            border-color: var(--accent);
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

        /* --- IMAGE PREVIEWS --- */
        .form-preview-item {
            position: relative;
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .form-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .form-preview-item .btn-delete-img {
            position: absolute;
            top: 2px;
            right: 2px;
            background: rgba(220, 53, 69, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            padding: 0;
        }

        .form-preview-item .btn-delete-img:hover {
            background: rgba(220, 53, 69, 1);
        }

        /* --- PAPER FORM DESIGN (SCREEN PREVIEW) --- */
        .hcc-form {
            width: 8.5in;
            height: 14in;
            background: white;
            color: black;
            padding: 0.25in 0.5in 0.25in 0.5in;
            font-family: Arial, sans-serif;
            position: relative;
            box-sizing: border-box;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            transform: scale(0.65);
            transform-origin: top center;
            margin-bottom: -5in;
            margin-top: 10px;
            display: flex;
            flex-direction: column;
        }

        .header-layout {
            position: relative;
            width: 100%;
            margin: 0;
        }

        /* --- LOGO POSITION (EDIT HERE TO MOVE LOGO IN BOTH PREVIEW & PRINT) --- */
        .logo-left {
            width: 185px !important;
            position: fixed !important;
            left: -3px !important;
            top: 15px !important;
            z-index: 50 !important;
        }

        .header-banner {
            width: calc(100% + 1in) !important;
            display: block;
            margin-left: -0.5in;
            margin-right: -0.5in;
            margin-top: 0px !important;
            max-width: none !important;
        }

        .form-title {
            text-align: center;
            margin: 10px 0 10px 0;
            color: black;
        }

        .form-title h2 {
            font-family: "Bookman Old Style", "Bookman", serif !important;
            font-weight: 900 !important;
            font-size: 18px !important;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-title h3 {
            font-family: Arial, sans-serif;
            font-weight: bold;
            font-size: 13px;
            margin: 2px 0 0 0;
            text-decoration: underline;
            text-transform: uppercase;
        }

        /* --- TABLE STYLES --- */
        .form-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid black;
            border-bottom: none;
            margin-bottom: 0px;
            table-layout: fixed;
        }

        .form-table td {
            border: 2px solid black;
            padding: 4px 6px;
            vertical-align: middle;
            font-size: 10pt;
            color: black;
        }

        .label-cell {
            font-weight: bold;
            width: 25%;
            background-color: white;
            text-transform: uppercase;
            color: black;
        }

        .desc-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid black;
            border-top: none;
            border-bottom: none;
            margin: 0;
            table-layout: fixed;
        }

        .desc-table td {
            border-left: 2px solid black;
            border-right: 2px solid black;
            border-top: 1px solid black;
            border-bottom: 1px solid black;
            padding: 4px 6px;
            vertical-align: top;
            height: 500px;
            position: relative;
        }

        .desc-content {
            font-size: 10pt;
            color: black;
            height: 100%;
            overflow: hidden;
        }

        /* FIXED: Text Overflow Issue */
        .desc-box {
            border: none;
            margin-top: 5px;
            width: 100%;
            background: transparent;
            padding: 0px;
            font-family: Arial, sans-serif;
            white-space: pre-wrap;
            word-wrap: break-word; /* Wrap long words */
            word-break: break-all; /* Break extremely long non-spaced strings */
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid black;
            border-top: none;
            margin-top: 0px;
            color: black;
            table-layout: fixed;
        }

        .signatures-table td {
            border: 2px solid black;
            width: 50%;
            vertical-align: bottom;
            font-size: 9pt;
            font-weight: bold;
            text-align: center;
            height: auto;
            padding-top: 40px;
            padding-bottom: 8px;
            padding-left: 5px;
            padding-right: 5px;
        }

        .sig-line {
            border-bottom: 1px solid black;
            width: 90%;
            margin: 0 auto 5px auto;
        }

        /* UPDATED IMAGE STYLING */
        .image-section {
            display: none;
            text-align: center;
            page-break-inside: avoid;
            position: absolute;
            bottom: 5px;
            left: 5px;
            right: 5px;
            pointer-events: none;
        }

        .paper-preview-img {
            max-width: 48%;
            max-height: 300px;
            border: 1px solid #ccc;
            margin: 5px;
            display: inline-block;
            vertical-align: top;
            object-fit: contain;
        }

        .form-footer {
            margin-top: 15px;
            padding-bottom: 10px;
            background-color: white;
        }

        .copy-furnished-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid black;
            margin-top: 5px;
            color: black;
        }

        .copy-furnished-table td {
            border: 2px solid black;
            padding: 4px;
            width: 33%;
            vertical-align: top;
            height: 40px;
            font-size: 8pt;
        }

        .officer-section {
            margin-top: 15px;
            font-size: 9pt;
            color: black;
        }

        .officer-title {
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-size: 8.5pt;
        }

        /* FIXED: Officer Alignment */
        .officer-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            width: 100%;
            height: 50px;
            position: relative;
        }

        .officer-box {
            width: 220px;
            text-align: center;
            border-top: 1px solid black;
            padding-top: 5px;
        }

        .officer-name-line {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9pt;
        }

        .officer-position {
            font-size: 8.5pt;
        }

        .noted-section {
            margin-top: 30px;
            font-size: 9pt;
            color: black;
        }

        .noted-title {
            font-weight: bold;
            margin-bottom: 30px;
            font-size: 8.5pt;
        }

        /* --- PRINT MEDIA QUERIES --- */
        @page {
            size: 8.5in 14in;
            margin: 0;
        }

        #print-area,
        #print-blank-area {
            display: none;
        }

        @media print {
            body {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                -webkit-print-color-adjust: exact !important;
            }

            .navbar,
            .main-container,
            .bottom-panel,
            .btn,
            .alert,
            .d-print-none {
                display: none !important;
            }

            #print-area {
                display: block !important;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
            }

            #print-area .hcc-form {
                transform: none !important;
                box-shadow: none !important;
                margin: 0 auto !important;
                width: 8.5in !important;
                height: 14in !important;
            }

            .print-blank #print-area {
                display: none !important;
            }

            .print-blank #print-blank-area {
                display: block !important;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
            }

            .print-blank #print-blank-area .hcc-form {
                transform: none !important;
                box-shadow: none !important;
                margin: 0 auto !important;
                width: 8.5in !important;
                height: 14in !important;
            }

            .header-banner {
                width: calc(100% + 1in) !important;
                margin-left: -0.5in !important;
                margin-right: -0.5in !important;
                margin-top: 0px !important;
            }

            .image-section {
                display: block !important;
            }

        }

        /* THEME TABLES */
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
            color: var(--accent); /* Reverted to Theme Blue */
            border-color: var(--border);
        }
        
        .table-custom td {
            color: #ffffff !important; /* Kept White */
            border-color: var(--border);
        }

        .table-custom tbody tr:hover {
            background-color: var(--input-bg);
        }

        .table-img-preview {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid var(--border);
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
    </style>
</head>

<body>

    <div class="navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-secondary fw-bold"><i class="fa fa-arrow-left me-2"></i> Back</a>
            <h4 class="m-0 fw-bold text-white">Incident Report</h4>
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
                <div class="panel-title">
                    <i class="fa fa-pen-to-square"></i> REPORT DETAILS
                </div>
                <div class="badge-queue">QUEUE: <?php echo count($_SESSION['incident_print_queue']); ?></div>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa fa-check-circle me-2"></i> <?php echo $success_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-circle me-2"></i> <?php echo $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="reportForm">
                <input type="text" name="case_title" id="in_case" class="form-control"
                    placeholder="Case (e.g., Bullying, Theft)" required oninput="updatePreview()">
                <input type="text" name="location" id="in_loc" class="form-control" placeholder="Location" required
                    oninput="updatePreview()">

                <div class="row">
                    <div class="col-6">
                        <label class="small text-secondary mb-1">Incident Date</label>
                        <input type="date" name="incident_date" id="in_date" class="form-control" required
                            oninput="updatePreview()">
                    </div>
                    <div class="col-6">
                        <label class="small text-secondary mb-1">Incident Time</label>
                        <div class="input-group mb-2">
                            <input type="time" name="incident_time" id="in_time" class="form-control mb-0" required
                                oninput="updatePreview()" style="border-top-right-radius: 0; border-bottom-right-radius: 0;">
                            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('in_time').value=''; updatePreview();">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <textarea name="description" id="in_desc" class="form-control" rows="8"
                    placeholder="Description of Incident..." required oninput="updatePreview()"></textarea>

                <div class="mb-3 mt-3">
                    <label class="small text-secondary mb-2 d-block"><i class="fa fa-images me-1"></i> Attach Images
                        (Optional, JPG/PNG/GIF)</label>

                    <input type="file" name="incident_images[]" id="in_images" class="d-none"
                        accept="image/png, image/gif, image/jpeg" multiple>

                    <button type="button" class="btn btn-outline-primary w-100 dashed-border"
                        onclick="document.getElementById('in_images').click()">
                        <i class="fa fa-plus-circle me-1"></i> Add Images
                    </button>

                    <div id="form-image-previews" class="mt-3 d-flex flex-wrap gap-2"></div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" name="submit_report" class="btn btn-primary flex-grow-1 fw-bold py-3 mt-2">
                        <i class="fa fa-plus-circle me-2"></i> ADD TO QUEUE
                    </button>
                    <button type="button" onclick="resetForm()" class="btn btn-warning fw-bold py-3 mt-2" title="Clear form to start new">
                         <i class="fa fa-rotate-right"></i>
                    </button>
                </div>
            </form>

            <hr class="border-secondary my-4">

            <div class="row g-2">
                <div class="col-6">
                    <button onclick="printQueue()" class="btn btn-success w-100 fw-bold h-100" <?php echo count($_SESSION['incident_print_queue']) == 0 ? 'disabled' : ''; ?>>
                        <i class="fa fa-print me-2"></i> Print Queue (<?php echo count($_SESSION['incident_print_queue']); ?>)
                    </button>
                </div>
                <div class="col-6">
                    <button onclick="printBlank()" class="btn btn-secondary w-100 fw-bold text-white h-100">
                        <i class="fa fa-file me-2"></i> Blank Form
                    </button>
                </div>
                <?php if (count($_SESSION['incident_print_queue']) > 0): ?>
                <div class="col-12">
                    <form method="POST" class="m-0">
                        <button type="submit" name="clear_queue" class="btn btn-danger w-100 fw-bold"
                            onclick="return confirm('Clear all items from print queue?')">
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
            </div>

            <div class="hcc-form" id="paper-preview">
                <div class="header-layout">
                    <img src="background-hcc-logo.png" alt="Logo" class="logo-left">
                    <img src="header_hcc.png" alt="Header" class="header-banner">
                </div>

                <div class="form-title">
                    <h2>SAFETY AND PROTECTION DIVISION</h2>
                    <h3>INCIDENT REPORT</h3>
                </div>

                <table class="form-table">
                    <tr>
                        <td class="label-cell">CASE</td>
                        <td class="input-cell" id="out_case"></td>
                    </tr>
                    <tr>
                        <td class="label-cell">LOCATION</td>
                        <td class="input-cell" id="out_loc"></td>
                    </tr>
                    <tr>
                        <td class="label-cell">DATE</td>
                        <td class="input-cell" id="out_date"></td>
                    </tr>
                    <tr>
                        <td class="label-cell">TIME</td>
                        <td class="input-cell" id="out_time"></td>
                    </tr>
                </table>

                <table class="desc-table">
                    <tr>
                        <td>
                            <div class="desc-content">
                                <strong>DESCRIPTION OF INCIDENT:</strong> <span
                                    style="font-size: 8pt; font-style: italic;">(What happened, person involved,
                                    specific dates/events)</span>
                                <div class="desc-box"><span id="out_desc"></span></div>
                                <div class="image-section" id="out_images_container"></div>
                            </div>
                        </td>
                    </tr>
                </table>

                <table class="signatures-table">
                    <tr>
                        <td>
                            <div class="sig-line"></div>
                            Student's Name/ Signature
                        </td>
                        <td>
                            <div class="sig-line"></div>
                            Parent's Name/Signature/ Contact Number
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="sig-line"></div>
                            Level/ Section
                        </td>
                        <td>
                            <div class="sig-line"></div>
                            Adviser
                        </td>
                    </tr>
                </table>

                <div class="form-footer">
                    <div style="font-size: 8pt; font-weight: bold; font-style: italic; margin-top: 5px;">Copy furnished
                        to the office of:</div>
                    <table class="copy-furnished-table">
                        <tr>
                            <td>Principal/Dean</td>
                            <td style="text-align: center;">
                                Prefect of Discipline
                                <div style="font-size: 7pt; margin-top: 10px; font-weight: bold; text-align: center;">
                                    Charles Daniel E. Dela Cruz<br>CHIEF, Prefect of Discipline</div>
                            </td>
                            <td>Others (Specify)</td>
                        </tr>
                    </table>

                    <div class="officer-section">
                        <div class="officer-title" style="margin-bottom: 25px;">Officer in charge of the incident:</div>
                        <div class="officer-container">
                            <div class="officer-box">
                                <div class="officer-name-line">JERRY R. MULDONG, SO1</div>
                                <div class="officer-position">Safety and Protection Officer</div>
                            </div>

                            <div class="officer-box">
                                <div class="officer-name-line">LESTER P. LUMBANG, SO2</div>
                                <div class="officer-position">Safety and Protection Officer</div>
                            </div>
                        </div>
                    </div>

                    <div class="noted-section">
                        <div class="noted-title">Noted by:</div>
                        <div style="text-align: left;">
                            <div
                                style="border-top: 1px solid black; width: 250px; padding-top: 5px; display: inline-block; text-align: center;">
                                <div class="officer-name-line">PAUL JEFFREY T. LANSANGAN, SO3</div>
                                <div class="officer-position">CHIEF, Safety and Protection</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div id="print-area">
        <?php
        if (count($_SESSION['incident_print_queue']) > 0):
            foreach ($_SESSION['incident_print_queue'] as $p):
                $t = strtotime($p['time']);
                $print_time = date("h:i A", $t);
                ?>
                <div class="hcc-form">
                    <div class="header-layout">
                        <img src="background-hcc-logo.png" alt="Logo" class="logo-left">
                        <img src="header_hcc.png" alt="Header" class="header-banner">
                    </div>
                    <div class="form-title">
                        <h2>SAFETY AND PROTECTION DIVISION</h2>
                        <h3>INCIDENT REPORT</h3>
                    </div>
                    <table class="form-table">
                        <tr>
                            <td class="label-cell">CASE</td>
                            <td class="input-cell"><?php echo $p['case']; ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">LOCATION</td>
                            <td class="input-cell"><?php echo $p['loc']; ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">DATE</td>
                            <td class="input-cell"><?php echo $p['date']; ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">TIME</td>
                            <td class="input-cell"><?php echo $print_time; ?></td>
                        </tr>
                    </table>
                    <table class="desc-table">
                        <tr>
                            <td>
                                <div class="desc-content">
                                    <strong>DESCRIPTION OF INCIDENT:</strong>
                                    <div class="desc-box"><?php echo nl2br($p['desc']); ?></div>
                                    <?php if (!empty($p['image_paths']) && is_array($p['image_paths'])): ?>
                                        <div class="image-section" style="display:block;">
                                            <?php foreach ($p['image_paths'] as $path): ?>                 <?php if (file_exists($path)): ?><img
                                                            src="<?php echo $path; ?>" class="paper-preview-img"
                                                            alt="Evidence"><?php endif; ?><?php endforeach; ?>
                                        </div><?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <table class="signatures-table">
                        <tr>
                            <td>
                                <div class="sig-line"></div>Student's Name/ Signature
                            </td>
                            <td>
                                <div class="sig-line"></div>Parent's Name/Signature/ Contact Number
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="sig-line"></div>Level/ Section
                            </td>
                            <td>
                                <div class="sig-line"></div>Adviser
                            </td>
                        </tr>
                    </table>

                    <div class="form-footer">
                        <div style="font-size: 8pt; font-weight: bold; font-style: italic; margin-top: 5px;">Copy furnished to
                            the office of:</div>
                        <table class="copy-furnished-table">
                            <tr>
                                <td>Principal/Dean</td>
                                <td style="text-align: center;">
                                    Prefect of Discipline
                                    <div style="font-size: 7pt; margin-top: 10px; font-weight: bold; text-align: center;">
                                        Charles Daniel E. Dela Cruz<br>CHIEF, Prefect of Discipline</div>
                                </td>
                                <td>Others (Specify)</td>
                            </tr>
                        </table>

                        <div class="officer-section">
                            <div class="officer-title" style="margin-bottom: 25px;">Officer in charge of the incident:</div>
                            <div class="officer-container">
                                <div class="officer-box">
                                    <div class="officer-name-line">JERRY R. MULDONG, SO1</div>
                                    <div class="officer-position">Safety and Protection Officer</div>
                                </div>
                                <div class="officer-box">
                                    <div class="officer-name-line">LESTER P. LUMBANG, SO2</div>
                                    <div class="officer-position">Safety and Protection Officer</div>
                                </div>
                            </div>
                        </div>

                        <div class="noted-section" style="margin-top: 30px;">
                            <div class="noted-title" style="margin-bottom: 30px;">Noted by:</div>
                            <div style="text-align: left;">
                                <div
                                    style="border-top: 1px solid black; width: 250px; padding-top: 5px; display: inline-block; text-align: center;">
                                    <div class="officer-name-line">PAUL JEFFREY T. LANSANGAN, SO3</div>
                                    <div class="officer-position">CHIEF, Safety and Protection</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?>
            <div class="hcc-form">
                <div class="form-title">
                    <h2>NO ITEMS IN QUEUE</h2>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div id="print-blank-area">
        <div class="hcc-form">
            <div class="header-layout">
                <img src="background-hcc-logo.png" alt="Logo" class="logo-left">
                <img src="header_hcc.png" alt="Header" class="header-banner">
            </div>
            <div class="form-title">
                <h2>SAFETY AND PROTECTION DIVISION</h2>
                <h3>INCIDENT REPORT</h3>
            </div>
            <table class="form-table">
                <tr><td class="label-cell">CASE</td><td class="input-cell">&nbsp;</td></tr>
                <tr><td class="label-cell">LOCATION</td><td class="input-cell">&nbsp;</td></tr>
                <tr><td class="label-cell">DATE</td><td class="input-cell">&nbsp;</td></tr>
                <tr><td class="label-cell">TIME</td><td class="input-cell">&nbsp;</td></tr>
            </table>
            <table class="desc-table">
                <tr><td>
                    <div class="desc-content">
                        <strong>DESCRIPTION OF INCIDENT:</strong> <span style="font-size: 8pt; font-style: italic;">(What happened, person involved, specific dates/events)</span>
                        <div class="desc-box"></div>
                    </div>
                </td></tr>
            </table>
            <table class="signatures-table">
                <tr><td><div class="sig-line"></div>Student's Name/ Signature</td><td><div class="sig-line"></div>Parent's Name/Signature/ Contact Number</td></tr>
                <tr><td><div class="sig-line"></div>Level/ Section</td><td><div class="sig-line"></div>Adviser</td></tr>
            </table>
            <div class="form-footer">
                <div style="font-size: 8pt; font-weight: bold; font-style: italic; margin-top: 5px;">Copy furnished to the office of:</div>
                <table class="copy-furnished-table">
                    <tr><td>Principal/Dean</td><td style="text-align: center;">Prefect of Discipline<div style="font-size: 7pt; margin-top: 10px; font-weight: bold; text-align: center;">Charles Daniel E. Dela Cruz<br>CHIEF, Prefect of Discipline</div></td><td>Others (Specify)</td></tr>
                </table>
                <div class="officer-section">
                    <div class="officer-title" style="margin-bottom: 25px;">Officer in charge of the incident:</div>
                    <div class="officer-container">
                        <div class="officer-box"><div class="officer-name-line">JERRY R. MULDONG, SO1</div><div class="officer-position">Safety and Protection Officer</div></div>
                        <div class="officer-box"><div class="officer-name-line">LESTER P. LUMBANG, SO2</div><div class="officer-position">Safety and Protection Officer</div></div>
                    </div>
                </div>
                <div class="noted-section" style="margin-top: 30px;">
                    <div class="noted-title" style="margin-bottom: 30px;">Noted by:</div>
                    <div style="text-align: left;"><div style="border-top: 1px solid black; width: 250px; padding-top: 5px; display: inline-block; text-align: center;"><div class="officer-name-line">PAUL JEFFREY T. LANSANGAN, SO3</div><div class="officer-position">CHIEF, Safety and Protection</div></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold m-0"><i class="fa fa-database me-2"></i> RECENT DATABASE ENTRIES</h5>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-dark">Total: <?php echo $total_count; ?></span>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..."
                        value="<?php echo htmlspecialchars($search_term); ?>">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-search"></i></button>
                    <?php if ($search_term): ?>
                        <a href="?" class="btn btn-sm btn-secondary"><i class="fa fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Images</th>
                        <th>Case Title</th>
                        <th>Location</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_reports && $recent_reports->num_rows > 0): ?>
                        <?php while ($row = $recent_reports->fetch_assoc()): ?>
                            <?php 
                                // Prepare data for loading into preview
                                $preview_data = [
                                    'case' => $row['case_title'],
                                    'loc' => $row['location'],
                                    'date' => $row['incident_date'],
                                    'time' => $row['incident_time'],
                                    'desc' => $row['description'],
                                    'images' => json_decode($row['image_paths'], true)
                                ];
                                $preview_json = htmlspecialchars(json_encode($preview_data), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td>
                                    <?php
                                    $db_images = json_decode($row['image_paths'], true);
                                    if (!empty($db_images) && is_array($db_images)):
                                        $first_img = $db_images[0];
                                        $count = count($db_images);
                                        if (file_exists($first_img)): ?>
                                            <div class="d-flex align-items-center">
                                                <a href="<?php echo $first_img; ?>" target="_blank">
                                                    <img src="<?php echo $first_img; ?>" class="table-img-preview" alt="Img">
                                                </a>
                                                <?php if ($count > 1): ?>
                                                    <span class="badge bg-secondary ms-2">+<?php echo $count - 1; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['case_title']); ?></td>
                                <td><?php echo strtoupper($row['location']); ?></td>
                                <td><?php echo $row['incident_date']; ?></td>
                                <td><?php echo date('h:i A', strtotime($row['incident_time'])); ?></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-sm btn-info text-white" 
                                            onclick="loadToPreview(<?php echo $preview_json; ?>)" title="Load into Display/Form">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Delete this record?')" title="Delete">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fa fa-database fa-2x mb-3"></i><br>
                                No records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
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
        } else {
            document.body.classList.remove('light-mode');
            document.getElementById('themeBtn').innerHTML = '<i class="fa fa-moon"></i>';
        }

        function printQueue() { document.body.classList.remove('print-blank'); window.print(); }
        function printBlank() { document.body.classList.add('print-blank'); window.print(); }

        // --- GLOBAL VARIABLES ---
        let loadedImages = []; // Stores images from DB load
        let isLoadedMode = false; // Flag to check if we are viewing a DB record

        function updatePreview() {
            document.getElementById('out_case').innerText = document.getElementById('in_case').value.toUpperCase();
            document.getElementById('out_loc').innerText = document.getElementById('in_loc').value.toUpperCase();
            const dateVal = document.getElementById('in_date').value;
            document.getElementById('out_date').innerText = dateVal || '';
            let timeVal = document.getElementById('in_time').value;
            if (timeVal) {
                let [h, m] = timeVal.split(':');
                let ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12; h = h ? h : 12;
                document.getElementById('out_time').innerText = `${h}:${m} ${ampm}`;
            } else {
                document.getElementById('out_time').innerText = '';
            }
            document.getElementById('out_desc').innerText = document.getElementById('in_desc').value;

            // Image Preview Logic (Only update from file input if NOT in Loaded Mode, or if user added files)
            // But simplify: If file input has files, show them. Else if loadedImages has items, show them.
            const paperImageContainer = document.getElementById('out_images_container');
            const fileInput = document.getElementById('in_images');
            
            if (fileInput.files.length > 0) {
                // User uploaded new files, show those
                paperImageContainer.innerHTML = '';
                paperImageContainer.style.display = 'block';
                [...fileInput.files].forEach(file => {
                    let reader = new FileReader();
                    reader.onload = function (e) {
                        let img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'paper-preview-img';
                        paperImageContainer.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                });
            } else if (loadedImages.length > 0) {
                // Show loaded images from DB
                paperImageContainer.innerHTML = '';
                paperImageContainer.style.display = 'block';
                loadedImages.forEach(src => {
                     let img = document.createElement('img');
                     img.src = src;
                     img.className = 'paper-preview-img';
                     paperImageContainer.appendChild(img);
                });
            } else {
                // No images
                paperImageContainer.innerHTML = '';
                paperImageContainer.style.display = 'none';
            }
        }

        // --- NEW: Load Data to Preview ---
        function loadToPreview(data) {
            // Fill inputs
            document.getElementById('in_case').value = data.case;
            document.getElementById('in_loc').value = data.loc;
            document.getElementById('in_date').value = data.date;
            document.getElementById('in_time').value = data.time;
            document.getElementById('in_desc').value = data.desc;

            // Handle Images
            loadedImages = data.images || [];
            isLoadedMode = true;
            
            // Clear current file input since we loaded from DB
            document.getElementById('in_images').value = "";
            document.getElementById('form-image-previews').innerHTML = ""; // Clear mini previews

            // Render mini previews for Loaded Images (Optional, for visual feedback on left)
            const formPreviewContainer = document.getElementById('form-image-previews');
            if(loadedImages.length > 0) {
                 loadedImages.forEach((src, index) => {
                    let item = document.createElement('div');
                    item.className = 'form-preview-item';
                    item.innerHTML = `<img src="${src}"><div style="position:absolute;bottom:0;width:100%;background:rgba(0,0,0,0.5);color:white;font-size:10px;text-align:center;">Saved</div>`;
                    formPreviewContainer.appendChild(item);
                 });
            }

            updatePreview();
            
            // Scroll to top to see details
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // --- NEW: Reset Form ---
        function resetForm() {
            document.getElementById('reportForm').reset();
            document.getElementById('in_images').value = "";
            dt = new DataTransfer(); // Reset file list
            loadedImages = [];
            isLoadedMode = false;
            document.getElementById('form-image-previews').innerHTML = "";
            updatePreview();
        }

        // --- MULTIPLE IMAGE UPLOAD & PREVIEW LOGIC ---
        const fileInput = document.getElementById('in_images');
        const formPreviewContainer = document.getElementById('form-image-previews');
        let dt = new DataTransfer();

        fileInput.addEventListener('change', function () {
            // If user adds files, we clear loaded images to avoid confusion or mix
            if(isLoadedMode) {
                loadedImages = [];
                isLoadedMode = false;
                formPreviewContainer.innerHTML = ''; 
            }

            for (let file of this.files) {
                dt.items.add(file);
            }
            this.files = dt.files;
            renderFormPreviews();
            updatePreview(); // Update paper preview
        });

        function renderFormPreviews() {
            // Form Previews (Mini with Delete)
            formPreviewContainer.innerHTML = '';
            [...dt.files].forEach((file, index) => {
                let reader = new FileReader();
                reader.onload = function (e) {
                    let item = document.createElement('div');
                    item.className = 'form-preview-item';
                    item.innerHTML = `<img src="${e.target.result}"><button type="button" class="btn-delete-img" onclick="removeFile(${index})"><i class="fa fa-times"></i></button>`;
                    formPreviewContainer.appendChild(item);
                }
                reader.readAsDataURL(file);
            });
        }

        function removeFile(index) {
            dt.items.remove(index);
            fileInput.files = dt.files;
            renderFormPreviews();
            updatePreview();
        }

        document.addEventListener('DOMContentLoaded', function () {
            updatePreview();
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => { new bootstrap.Alert(alert).close(); });
            }, 5000);
        });
    </script>

</body>
</html>