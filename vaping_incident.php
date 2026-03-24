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

// Table Setup - ADDED image_size COLUMN
$table_sql = "CREATE TABLE IF NOT EXISTS vaping_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_title VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    incident_date DATE NOT NULL,
    incident_time TIME NOT NULL,
    description TEXT NOT NULL,
    image_paths TEXT DEFAULT NULL,
    image_size TEXT DEFAULT NULL,   // New column for resizing
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
    'image_paths' => 'TEXT DEFAULT NULL',
    'image_size' => 'TEXT DEFAULT NULL'
];

foreach ($required_columns as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM vaping_reports LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE vaping_reports ADD $col $def");
    } else if ($check && $col === 'image_paths') {
        $row = $check->fetch_assoc();
        if (strpos(strtolower($row['Type']), 'varchar') !== false) {
            $conn->query("ALTER TABLE vaping_reports CHANGE $col $col $def");
        }
    } else if ($check && $col === 'image_size') {
        $row = $check->fetch_assoc();
        if (strpos(strtolower($row['Type']), 'int') !== false) {
            // Upgrade existing INT column to TEXT for JSON storage
            $conn->query("ALTER TABLE vaping_reports CHANGE $col $col TEXT DEFAULT NULL");
        }
    }
}

// Session Queue
if (!isset($_SESSION['vaping_print_queue'])) {
    $_SESSION['vaping_print_queue'] = [];
}

// Upload Directory
$upload_dir = "uploads/vaping/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// --- 2. FORM HANDLERS ---
$success_msg = "";
$error_msg = "";

// HANDLE: ADD OR EDIT REQUEST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    $case = $conn->real_escape_string($_POST['case_title']);
    $loc = $conn->real_escape_string($_POST['location']);
    $date = $conn->real_escape_string($_POST['incident_date']);
    $time = $conn->real_escape_string($_POST['incident_time']);
    $desc = $conn->real_escape_string($_POST['description']);
    $img_size = isset($_POST['image_size']) && !empty($_POST['image_size']) ? $conn->real_escape_string($_POST['image_size']) : '[]';

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
                    $new_file_name = uniqid('vape_') . '_' . $i . '.' . $file_ext;
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
        } else {
            // If editing and no new images are uploaded, retain the old ones
            if ($edit_id > 0) {
                $res = $conn->query("SELECT image_paths FROM vaping_reports WHERE id = $edit_id");
                if ($row = $res->fetch_assoc()) {
                    $image_paths_json = $row['image_paths'];
                    $uploaded_files = json_decode($image_paths_json, true) ?: [];
                }
            }
        }

        if ($edit_id > 0) {
            // UPDATE LOGIC
            $stmt = $conn->prepare("UPDATE vaping_reports SET case_title=?, location=?, incident_date=?, incident_time=?, description=?, image_paths=?, image_size=? WHERE id=?");
            if ($stmt === false) {
                $error_msg = "<strong>Database Error:</strong> " . $conn->error;
            } else {
                $stmt->bind_param("sssssssi", $case, $loc, $date, $time, $desc, $image_paths_json, $img_size, $edit_id);
                if ($stmt->execute()) {
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=edit");
                    exit();
                } else {
                    $error_msg = "<strong>Update Failed:</strong> " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            // INSERT LOGIC
            $stmt = $conn->prepare("INSERT INTO vaping_reports (case_title, location, incident_date, incident_time, description, image_paths, image_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt === false) {
                $error_msg = "<strong>Database Error:</strong> " . $conn->error;
            } else {
                $stmt->bind_param("sssssss", $case, $loc, $date, $time, $desc, $image_paths_json, $img_size);

                if ($stmt->execute()) {
                    $_SESSION['vaping_print_queue'][] = [
                        'case' => strtoupper($case),
                        'loc' => strtoupper($loc),
                        'date' => $date,
                        'time' => $time,
                        'desc' => $desc,
                        'image_paths' => $uploaded_files,
                        'image_size' => $img_size
                    ];
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=add");
                    exit();
                } else {
                    $error_msg = "<strong>Save Failed:</strong> " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// HANDLE: REPRINT LOG
if (isset($_GET['reprint_id'])) {
    $rep_id = intval($_GET['reprint_id']);
    $res = $conn->query("SELECT * FROM vaping_reports WHERE id = $rep_id");
    if ($row = $res->fetch_assoc()) {
        $_SESSION['vaping_print_queue'][] = [
            'case' => strtoupper($row['case_title']),
            'loc' => strtoupper($row['location']),
            'date' => $row['incident_date'],
            'time' => $row['incident_time'],
            'desc' => $row['description'],
            'image_paths' => json_decode($row['image_paths'], true),
            'image_size' => $row['image_size']
        ];
        $_SESSION['auto_print'] = true;
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// HANDLE: DELETE LOG
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $res = $conn->query("SELECT image_paths FROM vaping_reports WHERE id = $del_id");
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
    $conn->query("DELETE FROM vaping_reports WHERE id = $del_id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// HANDLE: CLEAR QUEUE
if (isset($_POST['clear_queue'])) {
    $_SESSION['vaping_print_queue'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'edit') {
        $success_msg = "Vaping incident updated successfully!";
    } else {
        $success_msg = "Vaping incident recorded and queued successfully!";
    }
}

if (isset($_GET['error']))
    $error_msg = "An error occurred.";

// --- SEARCH LOGIC ---
$search_term = "";
$where_clause = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = $conn->real_escape_string($_GET['search']);
    $where_clause = "WHERE case_title LIKE '%$search_term%' OR location LIKE '%$search_term%'";
}

$recent_reports = $conn->query("SELECT * FROM vaping_reports $where_clause ORDER BY id DESC LIMIT 10");
$total_count = $conn->query("SELECT COUNT(*) as total FROM vaping_reports")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaping Incident Report</title>
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

        .left-panel label {
            color: #ffffff !important;
        }

        .left-panel .text-secondary {
            color: #ffffff !important;
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

        .form-control::placeholder {
            color: var(--text-main);
            opacity: 1;
        }
        .form-control:-ms-input-placeholder {
            color: var(--text-main);
        }
        .form-control::-ms-input-placeholder {
            color: var(--text-main);
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

        /* --- IMAGE PREVIEWS (FORM SIDE) --- */
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

        /* ========== NEW HEADER STYLES ========== */
        .new-header-wrapper {
            position: relative;
            width: calc(100% + 0.5in);
            margin-left: -0.25in;
            margin-right: -0.25in;
            margin-top: -0.25in;
            height: 1.5in;
            margin-bottom: 0px; 
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
            padding: 0 0.25in; 
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
                #002b7f 100%
            );
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

        /* --- PAPER FORM DESIGN (SCREEN PREVIEW) --- */
        .hcc-form {
            width: 8.5in;
            height: 14in;
            background: white;
            color: black;
            padding: 0.3in 0.25in 0.25in 0.25in;
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

        .division-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: -5px; 
            margin-bottom: 5px;
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
            padding: 0; 
            vertical-align: top;
            height: 500px; 
            position: relative; 
        }

        .desc-content {
            position: absolute; 
            top: 0; bottom: 0; left: 0; right: 0;
            padding: 6px 8px; 
            font-size: 10pt;
            color: black;
            overflow: hidden; 
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        .desc-box {
            border: none;
            margin-top: 5px;
            width: 100%;
            background: transparent;
            padding: 0px;
            font-family: Arial, sans-serif;
            white-space: pre-wrap;
            word-wrap: break-word;
            word-break: break-all;
            flex-grow: 1;
            box-sizing: border-box;
        }

        /* --- RESIZABLE IMAGE STYLES --- */
        .image-section {
            display: none;
            width: 100%;
            margin-top: auto; 
            padding: 5px 0;
            box-sizing: border-box;
            justify-content: center;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 15px;
            z-index: 10;
        }

        .resize-wrapper {
            position: relative;
            display: inline-block;
            border: 2px dashed transparent;
            max-width: 100%;
            min-width: 10%;
            margin: 0;
            padding: 0;
            transition: border-color 0.2s;
            user-select: none; 
            box-sizing: border-box;
        }
        
        .resize-wrapper:hover, .resize-wrapper:active {
            border-color: rgba(0, 123, 255, 0.7);
        }

        .paper-preview-img {
            width: 100%;
            height: auto;
            display: block;
            pointer-events: none;
            object-fit: contain;
        }

        .resize-handle {
            position: absolute;
            background: #007bff;
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 20;
            width: 16px;
            height: 16px;
        }

        .resize-wrapper:hover .resize-handle, 
        .resize-wrapper:active .resize-handle {
            opacity: 1;
        }

        .resizer-nw { top: -8px; left: -8px; cursor: nwse-resize; }
        .resizer-ne { top: -8px; right: -8px; cursor: nesw-resize; }
        .resizer-sw { bottom: -8px; left: -8px; cursor: nesw-resize; }
        .resizer-se { bottom: -8px; right: -8px; cursor: nwse-resize; }
        .resizer-n { top: -8px; left: 50%; transform: translateX(-50%); cursor: ns-resize; }
        .resizer-s { bottom: -8px; left: 50%; transform: translateX(-50%); cursor: ns-resize; }
        .resizer-e { top: 50%; right: -8px; transform: translateY(-50%); cursor: ew-resize; }
        .resizer-w { top: 50%; left: -8px; transform: translateY(-50%); cursor: ew-resize; }

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

        /* --- PRINT MEDIA QUERIES (0 margin) --- */
        @page {
            size: auto;
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
                print-color-adjust: exact !important;
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

            #print-area .hcc-form,
            .print-blank #print-blank-area .hcc-form {
                transform: none !important;
                box-shadow: none !important;
                margin: 0 auto !important;
                width: 100% !important;
                height: 100% !important;
                min-height: 100vh !important;
                padding: 0.3in 0.25in 0.25in 0.25in !important;
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

            .new-header-wrapper {
                margin-top: -0.25in !important;
                margin-left: -0.25in !important;
                margin-right: -0.25in !important;
                padding-top: 0 !important;
            }

            .fading-bar, .divider-line {
                print-color-adjust: exact !important;
                -webkit-print-color-adjust: exact !important;
            }

            .image-section {
                display: flex !important;
            }

            .resize-wrapper {
                border: none !important;
            }
            .resize-handle {
                display: none !important;
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
            color: var(--accent);
            border-color: var(--border);
        }

        .table-custom td {
            color: #ffffff !important;
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
            <h4 class="m-0 fw-bold text-white">Vaping Incident</h4>
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
                <div class="badge-queue">QUEUE: <?php echo count($_SESSION['vaping_print_queue']); ?></div>
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
                <input type="hidden" name="edit_id" id="edit_id" value="">
                
                <input type="text" name="case_title" id="in_case" class="form-control"
                    placeholder="Case (e.g., Possession of Vape)" required oninput="updatePreview()">
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
                                oninput="updatePreview()"
                                style="border-top-right-radius: 0; border-bottom-right-radius: 0;">
                            <button type="button" class="btn btn-outline-secondary"
                                onclick="document.getElementById('in_time').value=''; updatePreview();">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <textarea name="description" id="in_desc" class="form-control" rows="8"
                    placeholder="Description of Incident..." required oninput="updatePreview()"></textarea>

                <input type="hidden" name="image_size" id="in_img_size" value="[]">

                <div class="mb-3 mt-3">
                    <label class="small text-secondary mb-2 d-block">
                        <i class="fa fa-images me-1"></i> Attach Images (Optional, JPG/PNG/GIF)
                        <br>
                        <span class="text-primary fw-bold" style="font-size: 11px;"><i class="fa fa-lightbulb"></i> Tip: Drag any edge or corner of the image in the Preview Panel to resize it.</span>
                    </label>

                    <input type="file" name="incident_images[]" id="in_images" class="d-none"
                        accept="image/png, image/gif, image/jpeg" multiple>

                    <button type="button" class="btn btn-outline-primary w-100 dashed-border"
                        onclick="document.getElementById('in_images').click()">
                        <i class="fa fa-plus-circle me-1"></i> Add Images
                    </button>

                    <div id="form-image-previews" class="mt-3 d-flex flex-wrap gap-2"></div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" name="submit_report" id="submit_btn" class="btn btn-primary flex-grow-1 fw-bold py-3 mt-2">
                        <i class="fa fa-plus-circle me-2"></i> ADD TO QUEUE
                    </button>
                    <button type="button" id="cancel_edit_btn" onclick="cancelEdit()" class="btn btn-danger fw-bold py-3 mt-2 d-none" title="Cancel Edit">
                        <i class="fa fa-times"></i>
                    </button>
                    <button type="button" onclick="resetForm()" class="btn btn-warning fw-bold py-3 mt-2"
                        title="Clear form to start new">
                        <i class="fa fa-rotate-right"></i>
                    </button>
                </div>
            </form>

            <hr class="border-secondary my-4">

            <div class="d-flex gap-2 flex-wrap mb-2">
                <button onclick="printQueue()" class="btn btn-success flex-grow-1 fw-bold" <?php echo count($_SESSION['vaping_print_queue']) == 0 ? 'disabled' : ''; ?>>
                    <i class="fa fa-print me-2"></i> Print Queue (<?php echo count($_SESSION['vaping_print_queue']); ?>)
                </button>
                <button onclick="printBlank()" class="btn btn-info fw-bold text-white flex-grow-1">
                    <i class="fa fa-file me-2"></i> Blank Form
                </button>
            </div>

            <?php if (count($_SESSION['vaping_print_queue']) > 0): ?>
                <form method="POST" class="m-0 w-100 mt-2">
                    <button type="submit" name="clear_queue" class="btn btn-danger fw-bold w-100 py-2"
                        onclick="return confirm('Clear all items from print queue?')">
                        <i class="fa fa-trash me-2"></i> Clear Queue
                    </button>
                </form>
            <?php endif; ?>

        </div>

        <div class="right-panel">
            <div class="panel-header w-100 border-bottom pb-3 mb-4" style="border-color: var(--border)!important;">
                <div class="panel-title"><i class="fa fa-eye"></i> DOCUMENT PREVIEW</div>
            </div>

            <div class="hcc-form" id="paper-preview">
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
                        <h3>VAPING INCIDENT REPORT</h3>
                    </div>
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
                                <div style="border-bottom: 1px solid black; padding-bottom: 4px; margin-bottom: 5px; width: 100%;">
                                    <strong>DESCRIPTION OF INCIDENT:</strong>
                                    <span style="font-size: 8pt; font-style: italic; margin-left: 5px;">(What happened, person involved, specific dates/events)</span>
                                </div>
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
                                <div class="officer-name-line">JERRY R. MULDONG, SO2</div>
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
        if (count($_SESSION['vaping_print_queue']) > 0):
            foreach ($_SESSION['vaping_print_queue'] as $p):
                $t = strtotime($p['time']);
                $print_time = date("h:i A", $t);
                $print_sizes = [];
                if (!empty($p['image_size'])) {
                    $decoded_sizes = json_decode($p['image_size'], true);
                    if (is_array($decoded_sizes)) {
                        $print_sizes = $decoded_sizes;
                    } else {
                        $print_sizes = array_fill(0, max(1, count((array)$p['image_paths'])), intval($p['image_size']));
                    }
                }
                ?>
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
                            <h3>VAPING INCIDENT REPORT</h3>
                        </div>
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
                                    <div style="border-bottom: 1px solid black; padding-bottom: 4px; margin-bottom: 5px; width: 100%;">
                                        <strong>DESCRIPTION OF INCIDENT:</strong>
                                        <span style="font-size: 8pt; font-style: italic; margin-left: 5px;">(What happened, person involved, specific dates/events)</span>
                                    </div>
                                    <div class="desc-box"><?php echo nl2br($p['desc']); ?></div>
                                    <?php if (!empty($p['image_paths']) && is_array($p['image_paths'])): ?>
                                        <div class="image-section" style="display:flex!important;">
                                            <?php foreach ($p['image_paths'] as $idx => $path): ?>
                                                <?php 
                                                $current_size = isset($print_sizes[$idx]) ? $print_sizes[$idx] : 48;
                                                if (file_exists($path)): 
                                                ?>
                                                    <div class="resize-wrapper" style="width: <?php echo $current_size; ?>%; border: none; resize: none;">
                                                        <img src="<?php echo $path; ?>" class="paper-preview-img" alt="Evidence">
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
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
                                    <div class="officer-name-line">JERRY R. MULDONG, SO2</div>
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
                    <h3>VAPING INCIDENT REPORT</h3>
                </div>
            </div>

            <table class="form-table">
                <tr>
                    <td class="label-cell">CASE</td>
                    <td class="input-cell">&nbsp;</td>
                </tr>
                <tr>
                    <td class="label-cell">LOCATION</td>
                    <td class="input-cell">&nbsp;</td>
                </tr>
                <tr>
                    <td class="label-cell">DATE</td>
                    <td class="input-cell">&nbsp;</td>
                </tr>
                <tr>
                    <td class="label-cell">TIME</td>
                    <td class="input-cell">&nbsp;</td>
                </tr>
            </table>

            <table class="desc-table">
                <tr>
                    <td>
                        <div class="desc-content">
                            <div style="border-bottom: 1px solid black; padding-bottom: 4px; margin-bottom: 5px; width: 100%;">
                                <strong>DESCRIPTION OF INCIDENT:</strong>
                                <span style="font-size: 8pt; font-style: italic; margin-left: 5px;">(What happened, person involved, specific dates/events)</span>
                            </div>
                            <div class="desc-box"></div>
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
                        <td style="text-align: center;">Prefect of Discipline<div
                                style="font-size: 7pt; margin-top: 10px; font-weight: bold; text-align: center;">Charles
                                Daniel E. Dela Cruz<br>CHIEF, Prefect of Discipline</div>
                        </td>
                        <td>Others (Specify)</td>
                    </tr>
                </table>

                <div class="officer-section">
                    <div class="officer-title" style="margin-bottom: 25px;">Officer in charge of the incident:</div>
                    <div class="officer-container">
                        <div class="officer-box">
                            <div class="officer-name-line">JERRY R. MULDONG, SO2</div>
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
    </div>

    <div class="bottom-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold m-0"><i class="fa fa-database me-2"></i> RECENT DATABASE ENTRIES</h5>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-dark">Total: <?php echo $total_count; ?></span>
                <form method="GET" class="d-flex gap-0" style="width: 300px;">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search..."
                            value="<?php echo htmlspecialchars($search_term); ?>" style="margin-bottom: 0;">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i></button>
                        <?php if ($search_term): ?>
                            <a href="?" class="btn btn-secondary"><i class="fa fa-times"></i></a>
                        <?php endif; ?>
                    </div>
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
                        <th class="text-center">Action</th>
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
                                'images' => json_decode($row['image_paths'], true),
                                'image_size' => $row['image_size']
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
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-sm btn-info text-white"
                                            onclick='viewOnly(<?php echo $preview_json; ?>)'
                                            title="View Record Only">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary"
                                            onclick='editRecord(<?php echo $preview_json; ?>, <?php echo $row['id']; ?>)'
                                            title="Edit Record">
                                            <i class="fa fa-pen"></i>
                                        </button>
                                        <a href="?reprint_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-secondary" title="Reprint">
                                            <i class="fa fa-print"></i>
                                        </a>
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

        // --- TEXT AUTO-SHRINK FUNCTION ---
        function autoFitAllTexts() {
            const containers = document.querySelectorAll('.desc-content');
            
            containers.forEach(container => {
                const textEl = container.querySelector('.desc-box');
                const imgEl = container.querySelector('.image-section');
                
                if (!textEl) return;
                
                // Reset to default font size to measure accurately
                textEl.style.fontSize = '10pt';
                
                const availableHeight = container.clientHeight; 
                if (availableHeight === 0) return;
                
                let imgHeight = 0;
                if (imgEl && window.getComputedStyle(imgEl).display !== 'none') {
                    imgHeight = imgEl.offsetHeight;
                }
                
                let currentSize = 10;
                const minSize = 7;
                
                while ((textEl.offsetHeight + imgHeight + 10) > availableHeight && currentSize > minSize) {
                    currentSize -= 0.5;
                    textEl.style.fontSize = currentSize + 'pt';
                }
            });
        }

        // --- TEXT UPDATE ONLY ---
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

            autoFitAllTexts();
        }

        // --- GLOBAL VARIABLES ---
        let loadedImages = [];
        let isLoadedMode = false;

        // --- IMAGE UPDATE & RESIZE LOGIC ---
        const fileInput = document.getElementById('in_images');
        const paperImageContainer = document.getElementById('out_images_container');
        const formPreviewContainer = document.getElementById('form-image-previews');
        let dt = new DataTransfer();

        fileInput.addEventListener('change', function () {
            if (isLoadedMode) {
                loadedImages = [];
                isLoadedMode = false;
                formPreviewContainer.innerHTML = '';
            }
            for (let file of this.files) {
                dt.items.add(file);
            }
            this.files = dt.files;
            
            // Re-sync size array to match newly added items (default 48)
            let currentSizes = [];
            try { currentSizes = JSON.parse(document.getElementById('in_img_size').value); } catch(e){}
            while(currentSizes.length < this.files.length) currentSizes.push(48);
            document.getElementById('in_img_size').value = JSON.stringify(currentSizes);

            renderFormPreviews();
            updateImagePreview(); 
        });

        function updateImagePreview() {
            paperImageContainer.innerHTML = '';

            // Fetch current Array of Saved Sizes
            let savedSizesVal = document.getElementById('in_img_size').value;
            let sizeArray = [];
            try {
                sizeArray = JSON.parse(savedSizesVal);
                if (!Array.isArray(sizeArray)) sizeArray = [sizeArray];
            } catch(e) {
                sizeArray = [parseInt(savedSizesVal) || 48];
            }

            function appendImage(src, index) {
                let wrapper = document.createElement('div');
                wrapper.className = 'resize-wrapper';
                
                let initialSize = sizeArray[index] !== undefined ? sizeArray[index] : 48;
                wrapper.style.width = initialSize + '%';
                
                let img = document.createElement('img');
                img.src = src;
                img.className = 'paper-preview-img';
                img.onload = function() { autoFitAllTexts(); };
                
                wrapper.appendChild(img);

                // Inject 8 interaction handles
                const handles = ['n', 's', 'e', 'w', 'ne', 'nw', 'se', 'sw'];
                handles.forEach(dir => {
                    let handle = document.createElement('div');
                    handle.className = `resize-handle resizer-${dir}`;
                    wrapper.appendChild(handle);
                });

                paperImageContainer.appendChild(wrapper);

                // Custom JavaScript Drag & Resize Logic
                const resizers = wrapper.querySelectorAll('.resize-handle');
                let original_width = 0;
                let original_height = 0;
                let original_mouse_x = 0;
                let original_mouse_y = 0;

                resizers.forEach(function(resizer) {
                    resizer.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        original_width = parseFloat(getComputedStyle(wrapper, null).getPropertyValue('width').replace('px', ''));
                        original_height = parseFloat(getComputedStyle(wrapper, null).getPropertyValue('height').replace('px', ''));
                        original_mouse_x = e.pageX;
                        original_mouse_y = e.pageY;
                        
                        function resize(e) {
                            let width = original_width;
                            let height = original_height;
                            let ratio = original_width / original_height;

                            if (resizer.classList.contains('resizer-e')) {
                                width = original_width + (e.pageX - original_mouse_x);
                            } else if (resizer.classList.contains('resizer-w')) {
                                width = original_width - (e.pageX - original_mouse_x);
                            } else if (resizer.classList.contains('resizer-s')) {
                                height = original_height + (e.pageY - original_mouse_y);
                                width = height * ratio;
                            } else if (resizer.classList.contains('resizer-n')) {
                                height = original_height - (e.pageY - original_mouse_y);
                                width = height * ratio;
                            } else if (resizer.classList.contains('resizer-se')) {
                                width = original_width + (e.pageX - original_mouse_x);
                            } else if (resizer.classList.contains('resizer-sw')) {
                                width = original_width - (e.pageX - original_mouse_x);
                            } else if (resizer.classList.contains('resizer-ne')) {
                                width = original_width + (e.pageX - original_mouse_x);
                            } else if (resizer.classList.contains('resizer-nw')) {
                                width = original_width - (e.pageX - original_mouse_x);
                            }

                            let percentWidth = (width / paperImageContainer.clientWidth) * 100;

                            if(percentWidth > 100) percentWidth = 100;
                            if(percentWidth < 10) percentWidth = 10;

                            wrapper.style.width = percentWidth + '%';
                        }
                        
                        function stopResize() {
                            window.removeEventListener('mousemove', resize);
                            window.removeEventListener('mouseup', stopResize);
                            
                            let percent = Math.round((wrapper.offsetWidth / paperImageContainer.clientWidth) * 100);
                            if(percent > 100) percent = 100;
                            if(percent < 10) percent = 10;
                            wrapper.style.width = percent + '%';
                            
                            let updatedSizes = [];
                            document.querySelectorAll('#out_images_container .resize-wrapper').forEach(w => {
                                updatedSizes.push(parseFloat(w.style.width) || 48);
                            });
                            document.getElementById('in_img_size').value = JSON.stringify(updatedSizes);
                            
                            autoFitAllTexts();
                        }
                        
                        window.addEventListener('mousemove', resize);
                        window.addEventListener('mouseup', stopResize);
                    });
                });
            }

            if (fileInput.files.length > 0) {
                paperImageContainer.style.display = 'flex';
                [...fileInput.files].forEach((file, index) => {
                    let reader = new FileReader();
                    reader.onload = function (e) {
                        appendImage(e.target.result, index);
                    }
                    reader.readAsDataURL(file);
                });
            } else if (loadedImages.length > 0) {
                paperImageContainer.style.display = 'flex';
                loadedImages.forEach((src, index) => {
                    appendImage(src, index);
                });
            } else {
                paperImageContainer.style.display = 'none';
            }
        }

        function renderFormPreviews() {
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

            try {
                let sizeArray = JSON.parse(document.getElementById('in_img_size').value);
                if (Array.isArray(sizeArray)) {
                    sizeArray.splice(index, 1);
                    document.getElementById('in_img_size').value = JSON.stringify(sizeArray);
                }
            } catch(e) {}

            renderFormPreviews();
            updateImagePreview();
        }

        // --- NEW VIEW ONLY LOGIC ---
        function viewOnly(data) {
            document.getElementById('out_case').innerText = data.case.toUpperCase();
            document.getElementById('out_loc').innerText = data.loc.toUpperCase();
            document.getElementById('out_date').innerText = data.date;
            
            let timeVal = data.time;
            if (timeVal) {
                let [h, m] = timeVal.split(':');
                let ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12; h = h ? h : 12;
                document.getElementById('out_time').innerText = `${h}:${m} ${ampm}`;
            } else {
                document.getElementById('out_time').innerText = '';
            }
            document.getElementById('out_desc').innerText = data.desc;

            const paperImageContainer = document.getElementById('out_images_container');
            paperImageContainer.innerHTML = '';
            
            let savedSize = data.image_size || '[]';
            let sizeArray = [];
            try {
                sizeArray = JSON.parse(savedSize);
                if (!Array.isArray(sizeArray)) sizeArray = [sizeArray];
            } catch(e) {
                sizeArray = [parseInt(savedSize) || 48];
            }
            
            let images = data.images || [];
            if (images.length > 0) {
                paperImageContainer.style.display = 'flex';
                images.forEach((src, index) => {
                    let wrapper = document.createElement('div');
                    wrapper.className = 'resize-wrapper';
                    wrapper.style.border = 'none'; // No resize outlines in View Only mode
                    let initialSize = sizeArray[index] !== undefined ? sizeArray[index] : 48;
                    wrapper.style.width = initialSize + '%';
                    
                    let img = document.createElement('img');
                    img.src = src;
                    img.className = 'paper-preview-img';
                    img.onload = function() { autoFitAllTexts(); };
                    
                    wrapper.appendChild(img);
                    paperImageContainer.appendChild(wrapper);
                });
            } else {
                paperImageContainer.style.display = 'none';
            }
            
            autoFitAllTexts();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // --- EDIT RECORD LOGIC ---
        function editRecord(data, id) {
            document.getElementById('edit_id').value = id;
            document.getElementById('submit_btn').innerHTML = '<i class="fa fa-save me-2"></i> UPDATE RECORD';
            document.getElementById('submit_btn').classList.replace('btn-primary', 'btn-success');
            document.getElementById('cancel_edit_btn').classList.remove('d-none');
            
            loadToPreview(data); 
        }

        function cancelEdit() {
            document.getElementById('edit_id').value = '';
            document.getElementById('submit_btn').innerHTML = '<i class="fa fa-plus-circle me-2"></i> ADD TO QUEUE';
            document.getElementById('submit_btn').classList.replace('btn-success', 'btn-primary');
            document.getElementById('cancel_edit_btn').classList.add('d-none');
            resetForm();
        }

        // --- Load Data to Preview (Used for Edit Mode) ---
        function loadToPreview(data) {
            document.getElementById('in_case').value = data.case;
            document.getElementById('in_loc').value = data.loc;
            document.getElementById('in_date').value = data.date;
            document.getElementById('in_time').value = data.time;
            document.getElementById('in_desc').value = data.desc;

            // Apply Saved Image Sizes
            let savedSize = data.image_size || '[]';
            if (typeof savedSize === 'number') savedSize = JSON.stringify([savedSize]);
            document.getElementById('in_img_size').value = savedSize;

            loadedImages = data.images || [];
            isLoadedMode = true;

            document.getElementById('in_images').value = "";
            document.getElementById('form-image-previews').innerHTML = "";

            const formPreviewContainer = document.getElementById('form-image-previews');
            if (loadedImages.length > 0) {
                loadedImages.forEach((src, index) => {
                    let item = document.createElement('div');
                    item.className = 'form-preview-item';
                    item.innerHTML = `<img src="${src}"><div style="position:absolute;bottom:0;width:100%;background:rgba(0,0,0,0.5);color:white;font-size:10px;text-align:center;">Saved</div>`;
                    formPreviewContainer.appendChild(item);
                });
            }

            updatePreview();
            updateImagePreview();

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // --- Reset Form ---
        function resetForm() {
            document.getElementById('reportForm').reset();
            document.getElementById('in_images').value = "";
            document.getElementById('in_img_size').value = "[]";
            document.getElementById('edit_id').value = "";
            dt = new DataTransfer();
            loadedImages = [];
            isLoadedMode = false;
            document.getElementById('form-image-previews').innerHTML = "";

            updatePreview();
            updateImagePreview();
            document.querySelectorAll('.desc-box').forEach(el => el.style.fontSize = '10pt');
        }

        // Auto print logic based on PHP session flag triggered by reprint button
        <?php if (isset($_SESSION['auto_print']) && $_SESSION['auto_print'] === true): ?>
            window.addEventListener('load', function() {
                printQueue();
            });
            <?php unset($_SESSION['auto_print']); ?>
        <?php endif; ?>

        // Initial update on page load
        document.addEventListener('DOMContentLoaded', function () {
            updatePreview();
            updateImagePreview();
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => { new bootstrap.Alert(alert).close(); });
            }, 5000);
        });
    </script>

</body>

</html>