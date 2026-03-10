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

// Table Setup - GUIDANCE REFERRALS
$table_sql = "CREATE TABLE IF NOT EXISTS guidance_referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_name VARCHAR(255) DEFAULT NULL,
    grade_section VARCHAR(255) DEFAULT NULL,
    referrer VARCHAR(255) DEFAULT NULL,
    referral_date DATE NOT NULL,
    referral_time TIME NOT NULL,
    reasons TEXT DEFAULT NULL,
    other_reason VARCHAR(255) DEFAULT NULL,
    description TEXT NOT NULL,
    image_paths TEXT DEFAULT NULL, 
    image_size TEXT DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'Recorded',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($table_sql);

// Auto-Repair Columns (Upgrade image_size if it was missing)
$check = $conn->query("SHOW COLUMNS FROM guidance_referrals LIKE 'image_size'");
if ($check && $check->num_rows == 0) {
    $conn->query("ALTER TABLE guidance_referrals ADD image_size TEXT DEFAULT NULL");
}

// Session Queue
if (!isset($_SESSION['guidance_print_queue'])) {
    $_SESSION['guidance_print_queue'] = [];
}

// Upload Directory
$upload_dir = "uploads/guidance/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// --- 2. FORM HANDLERS ---
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $student = $conn->real_escape_string($_POST['student_name']);
    $grade = $conn->real_escape_string($_POST['grade_section']);
    $referrer = $conn->real_escape_string($_POST['referrer']);
    $date = $conn->real_escape_string($_POST['referral_date']);
    $time = $conn->real_escape_string($_POST['referral_time']);
    $desc = $conn->real_escape_string($_POST['description']);
    $other = $conn->real_escape_string($_POST['other_reason']);
    $img_size = isset($_POST['image_size']) && !empty($_POST['image_size']) ? $conn->real_escape_string($_POST['image_size']) : '[]';
    
    $reasons = isset($_POST['reasons']) ? json_encode($_POST['reasons']) : json_encode([]);

    $image_paths_json = null;
    $uploaded_files = [];
    $upload_errors = [];

    if (isset($_FILES['incident_images']) && !empty($_FILES['incident_images']['name'][0])) {
        $total_files = count($_FILES['incident_images']['name']);
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['incident_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['incident_images']['tmp_name'][$i];
                $file_name = basename($_FILES['incident_images']['name'][$i]);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (in_array($file_ext, $allowed_exts)) {
                    $new_file_name = uniqid('guide_') . '_' . $i . '.' . $file_ext;
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

        $stmt = $conn->prepare("INSERT INTO guidance_referrals (student_name, grade_section, referrer, referral_date, referral_time, reasons, other_reason, description, image_paths, image_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt === false) {
            $error_msg = "<strong>Database Error:</strong> " . $conn->error;
        } else {
            $stmt->bind_param("ssssssssss", $student, $grade, $referrer, $date, $time, $reasons, $other, $desc, $image_paths_json, $img_size);

            if ($stmt->execute()) {
                $_SESSION['guidance_print_queue'][] = [
                    'student' => $student,
                    'grade' => $grade,
                    'referrer' => $referrer,
                    'date' => $date,
                    'time' => $time,
                    'reasons' => json_decode($reasons, true),
                    'other' => $other,
                    'desc' => $desc,
                    'image_paths' => $uploaded_files,
                    'image_size' => $img_size
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

if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $res = $conn->query("SELECT image_paths FROM guidance_referrals WHERE id = $del_id");
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
    $conn->query("DELETE FROM guidance_referrals WHERE id = $del_id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// HANDLE: REPRINT (add to queue)
if (isset($_GET['reprint_id'])) {
    $reprint_id = intval($_GET['reprint_id']);
    $result = $conn->query("SELECT * FROM guidance_referrals WHERE id = $reprint_id");
    if ($result && $row = $result->fetch_assoc()) {
        $reprint_item = [
            'student' => $row['student_name'],
            'grade' => $row['grade_section'],
            'referrer' => $row['referrer'],
            'date' => $row['referral_date'],
            'time' => $row['referral_time'],
            'reasons' => json_decode($row['reasons'], true),
            'other' => $row['other_reason'],
            'desc' => $row['description'],
            'image_paths' => json_decode($row['image_paths'], true) ?: [],
            'image_size' => $row['image_size']
        ];
        $_SESSION['guidance_print_queue'][] = $reprint_item;
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . "?reprint_success=1");
        exit();
    } else {
        $error_msg = "Referral not found.";
    }
}

if (isset($_POST['clear_queue'])) {
    $_SESSION['guidance_print_queue'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['success']))
    $success_msg = "Referral recorded successfully!";
if (isset($_GET['reprint_success']))
    $success_msg = "Referral added to print queue.";
if (isset($_GET['error']))
    $error_msg = "An error occurred.";

$search_term = "";
$where_clause = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = $conn->real_escape_string($_GET['search']);
    $where_clause = "WHERE student_name LIKE '%$search_term%' OR referrer LIKE '%$search_term%'";
}

$recent_reports = $conn->query("SELECT * FROM guidance_referrals $where_clause ORDER BY id DESC LIMIT 10");
$total_count = $conn->query("SELECT COUNT(*) as total FROM guidance_referrals")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guidance Referral Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        /* --- EXACT OLD ENGLISH TEXT MT FONT --- */
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

        .btn-primary { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; }
        .btn-secondary { background: linear-gradient(135deg, #858796 0%, #60616f 100%); color: white; }
        .btn-success { background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); color: white; }
        .btn-danger { background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%); color: white; }
        .btn-warning { background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%); color: white; }

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

        .left-panel, .right-panel, .bottom-panel {
            background: var(--panel-bg);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

        .left-panel { flex: 1; max-width: 450px; display: flex; flex-direction: column; }
        .right-panel {
            flex: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            position: relative;
            background-color: var(--panel-bg);
            overflow: hidden;
            padding: 10px; 
        }
        .bottom-panel { margin: 20px; }

        .form-control, .form-select {
            background-color: var(--input-bg);
            border: 1px solid var(--border);
            color: var(--text-main);
            margin-bottom: 10px;
            padding: 12px;
        }

        .form-control:focus, .form-select:focus {
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

        /* --- CHECKBOX STYLING --- */
        .form-check-input {
            background-color: var(--input-bg);
            border-color: var(--border);
        }
        .form-check-input:checked {
            background-color: var(--accent);
            border-color: var(--accent);
        }
        .form-check-label {
            color: var(--text-main);
            font-size: 0.9rem;
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
            padding: 0;
        }

        /* --- PAPER FORM DESIGN (Guidance Style) --- */
        .hcc-form {
            width: 8.5in;
            height: 14in; 
            background: white;
            color: black;
            padding: 0.6in 0.3in 0.3in 0.3in;
            font-family: Arial, sans-serif;
            position: relative;
            box-sizing: border-box;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            transform: scale(0.65);
            transform-origin: top center;
            margin-bottom: -4.9in; 
            margin-top: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden; 
        }

        /* --- HEADER LAYOUT (EDGE-TO-EDGE) --- */
        .new-header-wrapper {
            position: relative;
            margin-left: -0.3in; 
            margin-right: -0.3in; 
            margin-top: -0.6in;
            padding-top: 0.4in;
            margin-bottom: 5px;        
        }

        .new-header-logo {
            position: fixed; 
            left: -6px;                
            top: 0.2in;
            width: 184px;                
            height: auto;
            z-index: 10;
        }

        .new-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-left: 1.5in; 
            padding-right: 0.5in;
            padding-bottom: 5px;
            min-height: 40px;
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

        /* Form Sub-Header (SAPD) */
        .division-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
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
            font-weight: bold;
            font-size: 14pt;
            margin: 0;
            text-transform: uppercase;
        }

        .division-title h3 {
            font-family: "Calibri", "Gill Sans", sans-serif;
            font-weight: bold;
            font-size: 13pt;
            margin: 2px 0 0 0;
            text-transform: uppercase;
            text-decoration: none;
        }

        /* Form Table Section */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid black; 
            margin-bottom: 10px; 
            table-layout: fixed; 
        }

        .info-table td {
            border: 1px solid black; 
            padding: 4px 8px; 
            font-size: 11pt;
            font-family: Arial, sans-serif;
            color: black;
            vertical-align: middle; 
            height: 30px; 
        }

        .info-table .label-col {
            width: 30%; 
            font-weight: bold;
            white-space: nowrap;
        }

        .info-table .input-cell {
            font-family: "Calibri", "Gill Sans", sans-serif;
            font-weight: bold; 
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Checkbox Section */
        .referral-reasons {
            margin-bottom: 5px; 
            line-height: 1.1; 
            font-size: 11pt;
            color: black;
        }

        .reason-line {
            display: flex;
            align-items: center;
            margin-bottom: 0; 
        }

        .check-line-print {
            display: inline-flex;
            width: 30px;
            border-bottom: 1px solid black;
            margin-right: 10px;
            align-items: flex-end;
            justify-content: center;
            font-weight: bold;
            height: 16px;
            position: relative;
        }
        
        .check-mark {
            position: absolute;
            bottom: 0px;
            font-size: 14px;
        }

        .specify-line {
            border-bottom: 1px solid black;
            flex-grow: 1;
            margin-left: 5px;
            height: 20px;
            padding-left: 10px;
            font-family: "Calibri", "Gill Sans", sans-serif;
            font-weight: bold;
        }

        /* Incident Description Section */
        .incident-box {
            border: 2px solid black;
            width: 100%;
            flex-grow: 1;
            min-height: 400px;
            margin-bottom: 15px; 
            position: relative;
            padding: 0;
            display: flex;
            flex-direction: column;
            color: black;
        }

        .incident-header {
            display: flex;
            border-bottom: 2px solid black;
        }

        .incident-title {
            padding: 5px 10px;
            font-weight: bold;
            border-right: 2px solid black;
            white-space: nowrap;
            font-size: 11pt;
        }

        .incident-subtitle {
            padding: 5px 10px;
            font-style: italic;
            font-size: 10pt;
            flex-grow: 1;
        }

        .incident-content {
            padding: 5px 8px; 
            font-family: Arial, sans-serif;
            font-size: 11pt;
            flex-grow: 1;
            overflow: hidden;
            word-wrap: break-word;
            overflow-wrap: break-word;
            position: relative;
            text-align: left;
            display: flex;
            flex-direction: column; 
        }

        .desc-text {
            white-space: pre-wrap; 
            display: block;
            width: 100%;
        }

        /* --- DRAG-TO-RESIZE (PAPER PREVIEW) --- */
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

        /* Interactive Drag Handles */
        .resize-handle {
            position: absolute;
            background: #007bff;
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 10;
        }

        .resize-wrapper:hover .resize-handle, 
        .resize-wrapper:active .resize-handle {
            opacity: 1;
        }

        /* Corners */
        .resizer-nw { top: -6px; left: -6px; width: 12px; height: 12px; cursor: nwse-resize; }
        .resizer-ne { top: -6px; right: -6px; width: 12px; height: 12px; cursor: nesw-resize; }
        .resizer-sw { bottom: -6px; left: -6px; width: 12px; height: 12px; cursor: nesw-resize; }
        .resizer-se { bottom: -6px; right: -6px; width: 12px; height: 12px; cursor: nwse-resize; }
        
        /* Edges */
        .resizer-n { top: -6px; left: 50%; transform: translateX(-50%); width: 12px; height: 12px; cursor: ns-resize; }
        .resizer-s { bottom: -6px; left: 50%; transform: translateX(-50%); width: 12px; height: 12px; cursor: ns-resize; }
        .resizer-e { top: 50%; right: -6px; transform: translateY(-50%); width: 12px; height: 12px; cursor: ew-resize; }
        .resizer-w { top: 50%; left: -6px; transform: translateY(-50%); width: 12px; height: 12px; cursor: ew-resize; }

        /* Footer Section */
        .footer {
            margin-top: 0; 
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 11pt;
            color: black;
            padding-top: 10px;
        }

        .footer-left { width: 60%; }
        .footer-right { text-align: right; width: 35%; }
        
        .signature-line {
            display: inline-block;
            border-bottom: 1px solid black;
            width: 250px;
            height: 20px;
        }

        .date-line {
            display: inline-block;
            border-bottom: 1px solid black;
            width: 150px;
            margin-left: 5px;
            text-align: center;
            font-family: "Calibri", "Gill Sans", sans-serif;
            font-weight: bold;
        }

        /* --- PRINT MEDIA QUERIES --- */
        @page { 
            size: 8.5in 14in; 
            margin: 0; 
        } 
        
        #print-area, #print-blank-area { display: none; }

        @media print {
            html, body {
                background-color: white !important;
                color: black !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                height: auto !important;
            }

            * { text-shadow: none !important; }

            .new-header-title,
            .new-header-address,
            .new-header-url {
                color: #002060 !important;
            }

            .navbar, .main-container, .bottom-panel, .btn, .d-print-none { 
                display: none !important; 
            }

            body.printing-mode-queue #print-area,
            body.printing-mode-blank #print-blank-area {
                display: block !important;
                position: relative !important; 
                width: 100%;
                z-index: 9999;
            }

            .hcc-form {
                transform: none !important;
                box-shadow: none !important;
                margin: 0 auto !important;
                width: 100% !important; 
                height: 13.9in !important; 
                max-height: 13.9in !important; 
                page-break-after: always;
                page-break-inside: avoid;
                display: flex !important;
                flex-direction: column;
                visibility: visible !important;
                border: none !important;
                padding: 0.6in 0.3in 0.3in 0.3in !important;
                box-sizing: border-box !important;
            }
            
            .new-header-wrapper {
                margin-top: -0.6in !important;
                margin-left: -0.3in !important; 
                margin-right: -0.3in !important; 
                padding-top: 0.4in !important; 
            }

            .new-header-logo {
                position: absolute !important;
                top: 0.2in !important;
                left: -5px !important;
                width: 180px !important;
            }
            
            .hcc-form:last-of-type { 
                page-break-after: auto !important; 
            }

            .image-section { display: flex !important; }

            /* Disable resize elements on print */
            .resize-wrapper { border: none !important; }
            .resize-handle { display: none !important; }
        }

        /* THEME TABLES */
        .table-custom {
            color: var(--text-main);
            --bs-table-bg: transparent;
            --bs-table-striped-bg: rgba(255, 255, 255, 0.03);
            --bs-table-hover-bg: var(--input-bg);
        }
        body.light-mode .table-custom { --bs-table-striped-bg: rgba(0, 0, 0, 0.02); }
        .table-custom th { background-color: var(--input-bg); color: var(--accent); border-color: var(--border); }
        .table-custom td { color: var(--text-main) !important; border-color: var(--border); vertical-align: middle; }
        body.light-mode .table-custom td { color: #212529 !important; }
        .table-img-preview { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border); }
    </style>
</head>

<body>

    <div class="navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-secondary fw-bold"><i class="fa fa-arrow-left me-2"></i> Back</a>
            <h4 class="m-0 fw-bold text-white">Guidance Referral Form</h4>
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
                <div class="panel-title"><i class="fa fa-pen-to-square"></i> REFERRAL DETAILS</div>
                <div class="badge-queue">QUEUE: <?php echo count($_SESSION['guidance_print_queue']); ?></div>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show"><i class="fa fa-check-circle me-2"></i> <?php echo $success_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show"><i class="fa fa-exclamation-circle me-2"></i> <?php echo $error_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="referralForm">
                <div class="row g-2 mb-2">
                    <div class="col-12">
                        <input type="text" name="student_name" id="in_student" class="form-control" placeholder="Student's/Pupil's Name" required oninput="updateTextPreview()">
                    </div>
                    <div class="col-12">
                        <input type="text" name="grade_section" id="in_grade" class="form-control" placeholder="Grade/Year/Course/Section" required oninput="updateTextPreview()">
                    </div>
                    <div class="col-12">
                        <input type="text" name="referrer" id="in_referrer" class="form-control" placeholder="Person making referral" required oninput="updateTextPreview()">
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="small text-secondary mb-1">Date</label>
                        <input type="date" name="referral_date" id="in_date" class="form-control" required oninput="updateTextPreview()">
                    </div>
                    <div class="col-6">
                        <label class="small text-secondary mb-1">Time</label>
                        <input type="time" name="referral_time" id="in_time" class="form-control" required oninput="updateTextPreview()">
                    </div>
                </div>

                <div class="mb-3 p-3" style="background: var(--input-bg); border-radius: 8px; border: 1px solid var(--border);">
                    <label class="fw-bold mb-2">Reason/s for referral:</label>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Victim of bullying" id="chk_bully" onchange="updateTextPreview()">
                        <label class="form-check-label" for="chk_bully">Victim of bullying</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Suspected victim of abuse" id="chk_abuse" onchange="updateTextPreview()">
                        <label class="form-check-label" for="chk_abuse">Suspected victim of abuse</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Family issues/conflict" id="chk_family" onchange="updateTextPreview()">
                        <label class="form-check-label" for="chk_family">Family issues/conflict</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Brawling Incident" id="chk_brawl" onchange="updateTextPreview()">
                        <label class="form-check-label" for="chk_brawl">Brawling Incident</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Damage to School Property" id="chk_damage" onchange="updateTextPreview()">
                        <label class="form-check-label" for="chk_damage">Damage to School Property</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Possession of Deadly Weapon" id="chk_weapon" onchange="updateTextPreview()">
                        <label class="form-check-label" for="chk_weapon">Possession of Deadly Weapon</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Possession of Prohibited Drugs" id="chk_drugs" onchange="updateTextPreview()">
                        <label class="form-check-label" for="chk_drugs">Possession of Prohibited Drugs</label>
                    </div>
                    
                    <div class="mt-2">
                        <label class="small text-secondary">Others (Specify):</label>
                        <input type="text" name="other_reason" id="in_other" class="form-control form-control-sm" oninput="updateTextPreview()">
                    </div>
                </div>

                <textarea name="description" id="in_desc" class="form-control" rows="10" placeholder="Description of Incident (What happened, persons involved, dates)..." required oninput="updateTextPreview()"></textarea>

                <input type="hidden" name="image_size" id="in_img_size" value="[]">

                <div class="mb-3 mt-3">
                    <label class="small text-secondary mb-2 d-block">
                        <i class="fa fa-images me-1"></i> Attach Images (Optional)
                        <br>
                        <span class="text-primary fw-bold" style="font-size: 11px;"><i class="fa fa-lightbulb"></i> Tip: Drag any edge or corner of the image in the Preview Panel to resize it.</span>
                    </label>
                    <input type="file" name="incident_images[]" id="in_images" class="d-none" accept="image/png, image/gif, image/jpeg" multiple>
                    <button type="button" class="btn btn-outline-primary w-100 dashed-border" onclick="document.getElementById('in_images').click()">
                        <i class="fa fa-plus-circle me-1"></i> Add Images
                    </button>
                    <div id="form-image-previews" class="mt-3 d-flex flex-wrap gap-2"></div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" name="submit_report" class="btn btn-primary flex-grow-1 fw-bold py-3 mt-2"><i class="fa fa-plus-circle me-2"></i> ADD TO QUEUE</button>
                    <button type="button" onclick="resetForm()" class="btn btn-warning fw-bold py-3 mt-2"><i class="fa fa-rotate-right"></i></button>
                </div>
            </form>

            <hr class="border-secondary my-4">

            <div class="row g-2">
                <div class="col-6"><button onclick="printQueue()" class="btn btn-success w-100 fw-bold h-100" <?php echo count($_SESSION['guidance_print_queue']) == 0 ? 'disabled' : ''; ?>><i class="fa fa-print me-2"></i> Print Queue</button></div>
                <div class="col-6"><button onclick="printBlank()" class="btn btn-secondary w-100 fw-bold text-white h-100"><i class="fa fa-file me-2"></i> Blank Form</button></div>
                <?php if (count($_SESSION['guidance_print_queue']) > 0): ?>
                    <div class="col-12"><form method="POST" class="m-0"><button type="submit" name="clear_queue" class="btn btn-danger w-100 fw-bold" onclick="return confirm('Clear queue?')"><i class="fa fa-trash me-2"></i> Clear Queue</button></form></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="right-panel">
            <div class="panel-header w-100 border-bottom pb-3 mb-4" style="border-color: var(--border)!important;">
                <div class="panel-title"><i class="fa fa-eye"></i> DOCUMENT PREVIEW</div>
            </div>

            <div class="hcc-form" id="paper-preview">
                
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
                        <h2>Safety and Protection Division</h2>
                        <h3>Guidance Referral Form</h3>
                    </div>
                </div>

                <table class="info-table">
                    <tr>
                        <td class="label-col">Student's/ Pupil's Name</td>
                        <td class="input-cell" id="out_student"></td>
                    </tr>
                    <tr>
                        <td class="label-col">Grade/Year/Course/Section</td>
                        <td class="input-cell" id="out_grade"></td>
                    </tr>
                    <tr>
                        <td class="label-col">Person making referral</td>
                        <td class="input-cell" id="out_referrer"></td>
                    </tr>
                    <tr>
                        <td class="label-col">Time:</td>
                        <td class="input-cell" id="out_time"></td>
                    </tr>
                    <tr>
                        <td class="label-col">Date:</td>
                        <td class="input-cell" id="out_date"></td>
                    </tr>
                </table>

                <div class="referral-reasons">
                    <div>Reason/s for referral (Please check all that apply):</div>
                    
                    <div class="reason-line"><span class="check-line-print" id="p_bully"></span> Victim of bullying</div>
                    <div class="reason-line"><span class="check-line-print" id="p_abuse"></span> Suspected victim of abuse (physical, verbal, and/or sexual)</div>
                    <div class="reason-line"><span class="check-line-print" id="p_family"></span> Family issues/conflict</div>
                    <div class="reason-line"><span class="check-line-print" id="p_brawl"></span> Brawling Incident</div>
                    <div class="reason-line"><span class="check-line-print" id="p_damage"></span> Damage to School Property</div>
                    <div class="reason-line"><span class="check-line-print" id="p_weapon"></span> Possession of Deadly Weapon</div>
                    <div class="reason-line"><span class="check-line-print" id="p_drugs"></span> Possession of Prohibited Drugs</div>
                    
                    <div class="reason-line" style="margin-top: 5px;">
                        Others:
                    </div>
                    <div class="reason-line">
                        Please specify: <div class="specify-line" id="out_other"></div>
                    </div>
                </div>

                <div class="incident-box">
                    <div class="incident-header">
                        <div class="incident-title">Description of Incident</div>
                        <div class="incident-subtitle">What happened, persons involved, specific dates/events</div>
                    </div>
                    <div class="incident-content">
                        <span id="out_desc" class="desc-text"></span>
                        <div class="image-section" id="out_images_container"></div>
                    </div>
                </div>

                <div class="footer">
                    <div class="footer-left">
                        <div>Received by:</div>
                        <div style="margin-top: 20px;">
                            Intake Counsellor: <span class="signature-line"></span>
                        </div>
                    </div>
                    <div class="footer-right">
                        <div style="margin-top: 35px;">
                            Date: <span class="date-line" id="out_date_footer"></span>
                        </div>
                        </div>
                </div>

            </div>
        </div>
    </div>

    <div id="print-area">
        <?php if (count($_SESSION['guidance_print_queue']) > 0):
            foreach ($_SESSION['guidance_print_queue'] as $p):
                $t = strtotime($p['time']);
                $print_time = date("h:i A", $t); 
                $reasons = $p['reasons'] ?? [];

                // Parse the array of sizes, fallback if needed
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
                    <h2>Safety and Protection Division</h2>
                    <h3>Guidance Referral Form</h3>
                </div>
            </div>
            <table class="info-table">
                <tr><td class="label-col">Student's/ Pupil's Name</td><td class="input-cell"><?php echo htmlspecialchars($p['student']); ?></td></tr>
                <tr><td class="label-col">Grade/Year/Course/Section</td><td class="input-cell"><?php echo htmlspecialchars($p['grade']); ?></td></tr>
                <tr><td class="label-col">Person making referral</td><td class="input-cell"><?php echo htmlspecialchars($p['referrer']); ?></td></tr>
                <tr><td class="label-col">Time:</td><td class="input-cell"><?php echo $print_time; ?></td></tr>
                <tr><td class="label-col">Date:</td><td class="input-cell"><?php echo $p['date']; ?></td></tr>
            </table>
            <div class="referral-reasons">
                <div>Reason/s for referral (Please check all that apply):</div>
                <div class="reason-line"><span class="check-line-print"><?php echo in_array('Victim of bullying', $reasons) ? '<span class="check-mark">✓</span>' : ''; ?></span> Victim of bullying</div>
                <div class="reason-line"><span class="check-line-print"><?php echo in_array('Suspected victim of abuse', $reasons) ? '<span class="check-mark">✓</span>' : ''; ?></span> Suspected victim of abuse</div>
                <div class="reason-line"><span class="check-line-print"><?php echo in_array('Family issues/conflict', $reasons) ? '<span class="check-mark">✓</span>' : ''; ?></span> Family issues/conflict</div>
                <div class="reason-line"><span class="check-line-print"><?php echo in_array('Brawling Incident', $reasons) ? '<span class="check-mark">✓</span>' : ''; ?></span> Brawling Incident</div>
                <div class="reason-line"><span class="check-line-print"><?php echo in_array('Damage to School Property', $reasons) ? '<span class="check-mark">✓</span>' : ''; ?></span> Damage to School Property</div>
                <div class="reason-line"><span class="check-line-print"><?php echo in_array('Possession of Deadly Weapon', $reasons) ? '<span class="check-mark">✓</span>' : ''; ?></span> Possession of Deadly Weapon</div>
                <div class="reason-line"><span class="check-line-print"><?php echo in_array('Possession of Prohibited Drugs', $reasons) ? '<span class="check-mark">✓</span>' : ''; ?></span> Possession of Prohibited Drugs</div>
                <div class="reason-line" style="margin-top: 5px;">Others:</div>
                <div class="reason-line">Please specify: <div class="specify-line"><?php echo htmlspecialchars($p['other']); ?></div></div>
            </div>
            <div class="incident-box">
                <div class="incident-header"><div class="incident-title">Description of Incident</div><div class="incident-subtitle">What happened, persons involved, specific dates/events</div></div>
                <div class="incident-content">
                    <span class="desc-text"><?php echo htmlspecialchars($p['desc']); ?></span>
                    <?php if (!empty($p['image_paths']) && is_array($p['image_paths'])): ?>
                        <div class="image-section" style="display:flex!important;">
                            <?php foreach ($p['image_paths'] as $idx => $path): ?>
                                <?php 
                                $current_size = isset($print_sizes[$idx]) ? $print_sizes[$idx] : 50;
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
            </div>
            <div class="footer">
                <div class="footer-left"><div>Received by:</div><div style="margin-top: 20px;">Intake Counsellor: <span class="signature-line"></span></div></div>
                <div class="footer-right"><div style="margin-top: 35px;">Date: <span class="date-line"><?php echo $p['date']; ?></span></div></div>
            </div>
        </div>
        <?php endforeach; else: ?><div class="hcc-form"><h2>No items in queue</h2></div><?php endif; ?>
    </div>

    <div id="print-blank-area">
        <div class="hcc-form">
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
                    <h2>Safety and Protection Division</h2>
                    <h3>Guidance Referral Form</h3>
                </div>
            </div>
            <table class="info-table">
                <tr><td class="label-col">Student's/ Pupil's Name</td><td class="input-cell"></td></tr>
                <tr><td class="label-col">Grade/Year/Course/Section</td><td class="input-cell"></td></tr>
                <tr><td class="label-col">Person making referral</td><td class="input-cell"></td></tr>
                <tr><td class="label-col">Time:</td><td class="input-cell"></td></tr>
                <tr><td class="label-col">Date:</td><td class="input-cell"></td></tr>
            </table>
            <div class="referral-reasons">
                <div>Reason/s for referral (Please check all that apply):</div>
                <div class="reason-line"><span class="check-line-print"></span> Victim of bullying</div>
                <div class="reason-line"><span class="check-line-print"></span> Suspected victim of abuse</div>
                <div class="reason-line"><span class="check-line-print"></span> Family issues/conflict</div>
                <div class="reason-line"><span class="check-line-print"></span> Brawling Incident</div>
                <div class="reason-line"><span class="check-line-print"></span> Damage to School Property</div>
                <div class="reason-line"><span class="check-line-print"></span> Possession of Deadly Weapon</div>
                <div class="reason-line"><span class="check-line-print"></span> Possession of Prohibited Drugs</div>
                <div class="reason-line" style="margin-top: 5px;">Others:</div>
                <div class="reason-line">Please specify: <div class="specify-line"></div></div>
            </div>
            <div class="incident-box">
                <div class="incident-header"><div class="incident-title">Description of Incident</div><div class="incident-subtitle">What happened, persons involved, specific dates/events</div></div>
                <div class="incident-content"><span class="desc-text"></span></div>
            </div>
            <div class="footer">
                <div class="footer-left"><div>Received by:</div><div style="margin-top: 20px;">Intake Counsellor: <span class="signature-line"></span></div></div>
                <div class="footer-right"><div style="margin-top: 35px;">Date: <span class="date-line"></span></div></div>
            </div>
        </div>
    </div>

    <div class="bottom-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold m-0"><i class="fa fa-database me-2"></i> RECENT REFERRALS</h5>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-dark">Total: <?php echo $total_count; ?></span>
                <form method="GET" class="d-flex gap-0" style="width: 300px;">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search Student/Referrer..." value="<?php echo htmlspecialchars($search_term); ?>" style="margin-bottom: 0;">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i></button>
                        <?php if ($search_term): ?><a href="?" class="btn btn-secondary"><i class="fa fa-times"></i></a><?php endif; ?>
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
                        <th>Level/Section</th>
                        <th>Referrer</th>
                        <th>Date</th>
                        <th>Images</th>
                        <th>Reasons</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_reports && $recent_reports->num_rows > 0): ?>
                        <?php while ($row = $recent_reports->fetch_assoc()): ?>
                            <?php
                            $preview_data = [
                                'student' => $row['student_name'],
                                'grade' => $row['grade_section'],
                                'referrer' => $row['referrer'],
                                'date' => $row['referral_date'],
                                'time' => $row['referral_time'],
                                'desc' => $row['description'],
                                'other' => $row['other_reason'],
                                'reasons' => json_decode($row['reasons'], true),
                                'images' => json_decode($row['image_paths'], true),
                                'image_size' => $row['image_size']
                            ];
                            $preview_json = htmlspecialchars(json_encode($preview_data), ENT_QUOTES, 'UTF-8');
                            
                            $reasons_arr = json_decode($row['reasons'], true);
                            $reason_display = !empty($reasons_arr) ? implode(", ", $reasons_arr) : '-';
                            if($row['other_reason']) $reason_display .= " (" . $row['other_reason'] . ")";
                            
                            $images_arr = json_decode($row['image_paths'], true);
                            $img_html = '<span class="text-muted small">None</span>';
                            
                            if (!empty($images_arr) && is_array($images_arr)) {
                                $img_html = '<div class="d-flex align-items-center gap-1 flex-wrap">';
                                $has_valid_img = false;
                                
                                foreach ($images_arr as $img_path) {
                                    if (file_exists($img_path)) {
                                        $safe_img = htmlspecialchars($img_path);
                                        $img_html .= '<img src="' . $safe_img . '" class="table-img-preview" alt="img" title="Attached Image">';
                                        $has_valid_img = true;
                                    }
                                }
                                $img_html .= '</div>';
                                
                                if (!$has_valid_img) {
                                    $img_html = '<span class="badge bg-secondary">Not found</span>';
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['grade_section']); ?></td>
                                <td><?php echo htmlspecialchars($row['referrer']); ?></td>
                                <td><?php echo $row['referral_date']; ?></td>
                                <td><?php echo $img_html; ?></td>
                                <td><small><?php echo mb_strimwidth($reason_display, 0, 40, "..."); ?></small></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <a href="?reprint_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success" title="Add to print queue"><i class="fa fa-print"></i></a>
                                        <button type="button" class="btn btn-sm btn-info text-white" onclick="loadToPreview(<?php echo $preview_json; ?>)"><i class="fa fa-eye"></i></button>
                                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this record?')"><i class="fa fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center py-4"><i class="fa fa-database fa-2x mb-3"></i><br>No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // --- TEXT AUTO-SHRINK FUNCTION ---
        function autoFitAllTexts() {
            const containers = document.querySelectorAll('.incident-content');
            
            containers.forEach(container => {
                const textEl = container.querySelector('.desc-text');
                const imgEl = container.querySelector('.image-section');
                
                if (!textEl) return;
                
                // Reset to default font size to measure accurately
                textEl.style.fontSize = '11pt';
                
                const availableHeight = container.clientHeight;
                if (availableHeight === 0) return; // If hidden, we can't measure
                
                let imgHeight = 0;
                if (imgEl && window.getComputedStyle(imgEl).display !== 'none') {
                    imgHeight = imgEl.offsetHeight;
                }
                
                let currentSize = 11;
                const minSize = 6; // Prevents the text from becoming microscopic 
                
                // Keep shrinking by 0.5pt as long as text height + image height overflows the box
                while ((textEl.offsetHeight + imgHeight + 10) > availableHeight && currentSize > minSize) {
                    currentSize -= 0.5;
                    textEl.style.fontSize = currentSize + 'pt';
                }
            });
        }

        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            const isLight = document.body.classList.contains('light-mode');
            document.getElementById('themeBtn').innerHTML = isLight ? '<i class="fa fa-sun"></i>' : '<i class="fa fa-moon"></i>';
            localStorage.setItem('appTheme', isLight ? 'light' : 'dark');
        }

        const savedTheme = localStorage.getItem('appTheme') || 'dark';
        if (savedTheme === 'light') { document.body.classList.add('light-mode'); document.getElementById('themeBtn').innerHTML = '<i class="fa fa-sun"></i>'; }

        function printQueue() { 
            document.body.classList.add('printing-mode-queue'); 
            
            // Allow display block to apply, then auto-shrink before sending to printer
            setTimeout(() => {
                autoFitAllTexts();
                setTimeout(() => {
                    window.print();
                }, 100);
            }, 50); 
        }
        
        function printBlank() { 
            document.body.classList.add('printing-mode-blank'); 
            setTimeout(() => {
                window.print();
            }, 200); 
        }

        // --- Handle cleanup safely after the print dialog closes ---
        window.addEventListener('afterprint', () => {
            document.body.classList.remove('printing-mode-queue');
            document.body.classList.remove('printing-mode-blank');
        });

        let loadedImages = [];
        let isLoadedMode = false;

        function updateTextPreview() {
            document.getElementById('out_student').innerText = document.getElementById('in_student').value;
            document.getElementById('out_grade').innerText = document.getElementById('in_grade').value;
            document.getElementById('out_referrer').innerText = document.getElementById('in_referrer').value;
            document.getElementById('out_date').innerText = document.getElementById('in_date').value || '';
            document.getElementById('out_date_footer').innerText = document.getElementById('in_date').value || '';
            
            let timeVal = document.getElementById('in_time').value;
            if (timeVal) {
                let [h, m] = timeVal.split(':');
                let ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12; h = h ? h : 12;
                document.getElementById('out_time').innerText = `${h}:${m} ${ampm}`;
            } else { document.getElementById('out_time').innerText = ''; }

            const map = {
                'chk_bully': 'p_bully', 'chk_abuse': 'p_abuse', 'chk_family': 'p_family',
                'chk_brawl': 'p_brawl', 'chk_damage': 'p_damage', 'chk_weapon': 'p_weapon', 'chk_drugs': 'p_drugs'
            };
            for (let id in map) {
                document.getElementById(map[id]).innerHTML = document.getElementById(id).checked ? '<span class="check-mark">✓</span>' : '';
            }

            document.getElementById('out_other').innerText = document.getElementById('in_other').value;
            document.getElementById('out_desc').innerText = document.getElementById('in_desc').value;
            
            // Adjust size dynamically as user types
            autoFitAllTexts();
        }

        // --- UPDATED: IMAGE DRAG-TO-RESIZE LOGIC ---
        function updatePaperImages() {
            const paperImageContainer = document.getElementById('out_images_container');
            const fileInput = document.getElementById('in_images');
            
            // Fetch current Array of Saved Sizes
            let savedSizesVal = document.getElementById('in_img_size').value;
            let sizeArray = [];
            try {
                sizeArray = JSON.parse(savedSizesVal);
                if (!Array.isArray(sizeArray)) sizeArray = [sizeArray];
            } catch(e) {
                sizeArray = [parseInt(savedSizesVal) || 50];
            }
            
            paperImageContainer.innerHTML = ''; 

            let imagesToLoad = 0;
            let imagesLoaded = 0;

            function checkAllLoaded() {
                if (imagesLoaded === imagesToLoad) {
                    autoFitAllTexts();
                }
            }

            function appendResizableImage(src, index) {
                let wrapper = document.createElement('div');
                wrapper.className = 'resize-wrapper';
                
                let initialSize = sizeArray[index] !== undefined ? sizeArray[index] : 50;
                wrapper.style.width = initialSize + '%';
                
                let img = document.createElement('img');
                img.src = src;
                img.className = 'paper-preview-img';
                img.onload = function() {
                    imagesLoaded++;
                    checkAllLoaded();
                };
                
                wrapper.appendChild(img);

                // Inject 8 interaction handles
                const handles = ['n', 's', 'e', 'w', 'ne', 'nw', 'se', 'sw'];
                handles.forEach(dir => {
                    let handle = document.createElement('div');
                    handle.className = `resize-handle resizer-${dir}`;
                    wrapper.appendChild(handle);
                });

                paperImageContainer.appendChild(wrapper);

                const resizers = wrapper.querySelectorAll('.resize-handle');
                let original_width = 0;
                let original_mouse_x = 0;
                let original_mouse_y = 0;

                resizers.forEach(function(resizer) {
                    resizer.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        original_width = parseFloat(getComputedStyle(wrapper, null).getPropertyValue('width').replace('px', ''));
                        original_mouse_x = e.pageX;
                        original_mouse_y = e.pageY;
                        
                        function resize(e) {
                            let width = original_width;
                            if (resizer.classList.contains('resizer-e') || resizer.classList.contains('resizer-se') || resizer.classList.contains('resizer-ne')) {
                                width = original_width + (e.pageX - original_mouse_x);
                            } else if (resizer.classList.contains('resizer-w') || resizer.classList.contains('resizer-sw') || resizer.classList.contains('resizer-nw')) {
                                width = original_width - (e.pageX - original_mouse_x);
                            } else if (resizer.classList.contains('resizer-s')) {
                                width = original_width + (e.pageY - original_mouse_y);
                            } else if (resizer.classList.contains('resizer-n')) {
                                width = original_width - (e.pageY - original_mouse_y);
                            }
                            
                            let percent = (width / paperImageContainer.clientWidth) * 100;
                            if(percent > 100) percent = 100;
                            if(percent < 10) percent = 10;
                            wrapper.style.width = percent + '%';
                        }
                        
                        function stopResize() {
                            window.removeEventListener('mousemove', resize);
                            window.removeEventListener('mouseup', stopResize);
                            
                            let percent = Math.round((wrapper.offsetWidth / paperImageContainer.clientWidth) * 100);
                            if(percent > 100) percent = 100;
                            if(percent < 10) percent = 10;
                            wrapper.style.width = percent + '%';
                            
                            // Save individual sizes back to JSON Array
                            let updatedSizes = [];
                            document.querySelectorAll('#out_images_container .resize-wrapper').forEach(w => {
                                updatedSizes.push(parseFloat(w.style.width) || 50);
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
                imagesToLoad = fileInput.files.length;
                
                [...fileInput.files].forEach((file, index) => {
                    let reader = new FileReader();
                    reader.onload = function (e) { 
                        appendResizableImage(e.target.result, index);
                    }
                    reader.readAsDataURL(file);
                });
            } else if (loadedImages.length > 0) {
                paperImageContainer.style.display = 'flex'; 
                imagesToLoad = loadedImages.length;
                
                loadedImages.forEach((src, index) => { 
                    appendResizableImage(src, index);
                });
            } else { 
                paperImageContainer.style.display = 'none'; 
                autoFitAllTexts();
            }
        }

        function loadToPreview(data) {
            document.getElementById('in_student').value = data.student || '';
            document.getElementById('in_grade').value = data.grade || '';
            document.getElementById('in_referrer').value = data.referrer || '';
            document.getElementById('in_date').value = data.date || '';
            document.getElementById('in_time').value = data.time || '';
            document.getElementById('in_desc').value = data.desc || '';
            document.getElementById('in_other').value = data.other || '';
            
            document.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false);
            if (data.reasons && Array.isArray(data.reasons)) {
                data.reasons.forEach(r => {
                    let cb = document.querySelector(`input[value="${r}"]`);
                    if(cb) cb.checked = true;
                });
            }

            // Apply Saved Image Sizes
            let savedSize = data.image_size || '[]';
            if (typeof savedSize === 'number') savedSize = JSON.stringify([savedSize]);
            document.getElementById('in_img_size').value = savedSize;

            loadedImages = data.images || [];
            isLoadedMode = true;
            document.getElementById('in_images').value = "";
            document.getElementById('form-image-previews').innerHTML = "";

            if (loadedImages.length > 0) {
                loadedImages.forEach((src) => {
                    let item = document.createElement('div'); item.className = 'form-preview-item';
                    item.innerHTML = `<img src="${src}"><div style="position:absolute;bottom:0;width:100%;background:rgba(0,0,0,0.5);color:white;font-size:10px;text-align:center;">Saved</div>`;
                    document.getElementById('form-image-previews').appendChild(item);
                });
            }
            updateTextPreview();
            updatePaperImages();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('referralForm').reset();
            document.getElementById('in_images').value = "";
            document.getElementById('in_img_size').value = "[]";
            loadedImages = []; isLoadedMode = false;
            document.getElementById('form-image-previews').innerHTML = "";
            updateTextPreview();
            updatePaperImages();
        }

        const fileInput = document.getElementById('in_images');
        const formPreviewContainer = document.getElementById('form-image-previews');
        let dt = new DataTransfer();

        fileInput.addEventListener('change', function () {
            if (isLoadedMode) { loadedImages = []; isLoadedMode = false; formPreviewContainer.innerHTML = ''; }
            for (let file of this.files) { dt.items.add(file); }
            this.files = dt.files; 
            
            // Re-sync size array to match newly added items
            let currentSizes = [];
            try { currentSizes = JSON.parse(document.getElementById('in_img_size').value); } catch(e){}
            while(currentSizes.length < this.files.length) currentSizes.push(50);
            document.getElementById('in_img_size').value = JSON.stringify(currentSizes);

            renderFormPreviews(); 
            updatePaperImages();
        });

        function renderFormPreviews() {
            formPreviewContainer.innerHTML = '';
            [...dt.files].forEach((file, index) => {
                let reader = new FileReader();
                reader.onload = function (e) {
                    let item = document.createElement('div'); item.className = 'form-preview-item';
                    item.innerHTML = `<img src="${e.target.result}"><button type="button" class="btn-delete-img" onclick="removeFile(${index})"><i class="fa fa-times"></i></button>`;
                    formPreviewContainer.appendChild(item);
                }
                reader.readAsDataURL(file);
            });
        }
        
        function removeFile(index) { 
            dt.items.remove(index); 
            fileInput.files = dt.files; 
            
            // Remove the associated size from the JSON array
            try {
                let sizeArray = JSON.parse(document.getElementById('in_img_size').value);
                if (Array.isArray(sizeArray)) {
                    sizeArray.splice(index, 1);
                    document.getElementById('in_img_size').value = JSON.stringify(sizeArray);
                }
            } catch(e) {}

            renderFormPreviews(); 
            updatePaperImages();
        }
        
        document.addEventListener('DOMContentLoaded', function () { updateTextPreview(); setTimeout(() => { document.querySelectorAll('.alert').forEach(a => new bootstrap.Alert(a).close()); }, 5000); });
    </script>
</body>
</html>