<?php
// --- 1. SETUP & CONFIGURATION ---
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting to debug issues
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

// 1. Create Table if it doesn't exist
$table_sql = "CREATE TABLE IF NOT EXISTS cctv_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requestor_name VARCHAR(255) NOT NULL,
    level_section VARCHAR(255),
    incident_date DATE NOT NULL,
    incident_time TIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    reason TEXT NOT NULL,
    evaluation TEXT,
    assisted_by VARCHAR(255),
    reviewed_by VARCHAR(255),
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($table_sql);

// 2. AUTO-REPAIR: Check for ALL required columns and add them if missing
$required_columns = [
    'requestor_name' => 'VARCHAR(255) NOT NULL',
    'level_section' => 'VARCHAR(255)',
    'incident_date' => 'DATE NOT NULL',
    'incident_time' => 'TIME',
    'location' => 'VARCHAR(255) NOT NULL',
    'reason' => 'TEXT NOT NULL',
    'evaluation' => 'TEXT',
    'assisted_by' => 'VARCHAR(255)',
    'reviewed_by' => 'VARCHAR(255)'
];

foreach ($required_columns as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM cctv_requests LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE cctv_requests ADD $col $def");
    }
}

// Initialize Session Queue
if (!isset($_SESSION['cctv_print_queue'])) {
    $_SESSION['cctv_print_queue'] = [];
}

// --- 2. FORM HANDLERS ---
$success_msg = "";
$error_msg = "";

// HANDLE: ADD REQUEST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $req_name = $conn->real_escape_string($_POST['requestor_name']);
    $lvl = $conn->real_escape_string($_POST['level_section']);
    $date = $conn->real_escape_string($_POST['incident_date']);
    $time = $conn->real_escape_string($_POST['incident_time']);
    $loc = $conn->real_escape_string($_POST['location']);
    $reason = $conn->real_escape_string($_POST['reason']);
    $eval = $conn->real_escape_string($_POST['evaluation']);
    $assisted = $conn->real_escape_string($_POST['assisted_by']);
    $reviewed = $conn->real_escape_string($_POST['reviewed_by']);

    // Prepare SQL Statement
    $stmt = $conn->prepare("INSERT INTO cctv_requests (requestor_name, level_section, incident_date, incident_time, location, reason, evaluation, assisted_by, reviewed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        $error_msg = "<strong>Database Error:</strong> The system could not prepare the database. <br>Details: " . $conn->error;
    } else {
        $stmt->bind_param("sssssssss", $req_name, $lvl, $date, $time, $loc, $reason, $eval, $assisted, $reviewed);

        if ($stmt->execute()) {
            // Success: Add to Session Queue
            $_SESSION['cctv_print_queue'][] = [
                'name' => $req_name,
                'lvl' => strtoupper($lvl),
                'date' => $date,
                'time' => $time,
                'loc' => strtoupper($loc),
                'reason' => $reason,
                'eval' => $eval,
                'assisted' => strtoupper($assisted),
                'reviewed' => strtoupper($reviewed)
            ];

            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit();
        } else {
            $error_msg = "<strong>Save Failed:</strong> " . $stmt->error;
        }
        $stmt->close();
    }
}

// HANDLE: UPDATE REQUEST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
    $edit_id = intval($_POST['edit_id']);
    $req_name = $conn->real_escape_string($_POST['requestor_name']);
    $lvl = $conn->real_escape_string($_POST['level_section']);
    $date = $conn->real_escape_string($_POST['incident_date']);
    $time = $conn->real_escape_string($_POST['incident_time']);
    $loc = $conn->real_escape_string($_POST['location']);
    $reason = $conn->real_escape_string($_POST['reason']);
    $eval = $conn->real_escape_string($_POST['evaluation']);
    $assisted = $conn->real_escape_string($_POST['assisted_by']);
    $reviewed = $conn->real_escape_string($_POST['reviewed_by']);
    $stmt = $conn->prepare("UPDATE cctv_requests SET requestor_name=?, level_section=?, incident_date=?, incident_time=?, location=?, reason=?, evaluation=?, assisted_by=?, reviewed_by=? WHERE id=?");
    if ($stmt === false) {
        $error_msg = "<strong>Database Error:</strong> " . $conn->error;
    } else {
        $stmt->bind_param("sssssssssi", $req_name, $lvl, $date, $time, $loc, $reason, $eval, $assisted, $reviewed, $edit_id);
        if ($stmt->execute()) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=2");
            exit();
        } else {
            $error_msg = "<strong>Update Failed:</strong> " . $stmt->error;
        }
        $stmt->close();
    }
}

// HANDLE: DELETE LOG
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM cctv_requests WHERE id = $del_id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// HANDLE: CLEAR QUEUE
if (isset($_POST['clear_queue'])) {
    $_SESSION['cctv_print_queue'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Parse URL Messages
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1)
        $success_msg = "Record added to queue successfully!";
    if ($_GET['success'] == 2)
        $success_msg = "Record updated successfully!";
}
if (isset($_GET['error'])) {
    $error_msg = "An error occurred.";
}

// --- SEARCH LOGIC ---
$search_term = "";
$where_clause = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = $conn->real_escape_string($_GET['search']);
    $where_clause = "WHERE requestor_name LIKE '%$search_term%' OR location LIKE '%$search_term%'";
}

// Fetch Records
$recent_requests = $conn->query("SELECT * FROM cctv_requests $where_clause ORDER BY id DESC LIMIT 10");
$total_count = $conn->query("SELECT COUNT(*) as total FROM cctv_requests")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCTV Review</title>
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

        /* --- NAVBAR STYLE --- */
        .navbar {
            background: var(--panel-bg);
            border-bottom: 1px solid var(--border);
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            position: relative;
            z-index: 1;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(0, 0, 0, 0.2);
            filter: brightness(110%);
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-primary { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; }
        .btn-success { background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); color: white; }
        .btn-danger { background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%); color: white; }
        .btn-warning { background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%); color: white; }
        .btn-secondary { background: linear-gradient(135deg, #858796 0%, #60616f 100%); color: white; }
        .btn-info { background: linear-gradient(135deg, #36b9cc 0%, #258391 100%); color: white; }

        /* Theme Toggle */
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

        /* --- LAYOUT STRUCTURE --- */
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

        /* --- CCTV FORM PAPER DESIGN --- */
        .hcc-form {
            width: 8.5in;
            height: 11in;
            background: white;
            color: black;
            padding: 0.25in 0.5in 0.5in 0.5in; 
            font-family: Arial, sans-serif;
            position: relative;
            box-sizing: border-box;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            transform: scale(0.65);
            transform-origin: top center;
            margin-bottom: -3.8in;
            margin-top: 10px;
        }

        /* --- NEW HEADER LAYOUT (Fading Bar Integration) --- */
        .new-header-wrapper {
            position: relative;
            width: calc(100% + 1in);
            margin-left: -0.5in;
            margin-right: -0.5in;
            margin-top: -0.25in;
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

        /* --- DIVISION TITLE DESIGN --- */
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
            font-size: 16pt;
            margin: 0;
            text-transform: uppercase;
        }

        .division-title h3 {
            font-family: "Arial", sans-serif;
            font-weight: bold;
            text-decoration: underline;
            font-size: 13pt;
            margin: 2px 0 0 0;
            text-transform: uppercase;
        }

        .form-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid black;
            margin-bottom: 15px;
        }

        .form-table td {
            border: 2px solid black;
            padding: 8px;
            vertical-align: middle;
            font-size: 11pt;
            color: black;
        }

        .label-cell {
            font-weight: bold;
            width: 30%;
            background-color: white;
            text-transform: uppercase;
            color: black;
        }

        .eval-section {
            margin-top: 5px;
            color: black;
        }

        .eval-box {
            border: 2px solid black;
            margin-top: 5px;
            min-height: 180px;
            width: 100%;
            background-image: linear-gradient(black 1px, transparent 1px);
            background-size: 100% 2em;
            background-position: 0 1.9em;
            padding: 0 5px;
            line-height: 2em;
            font-size: 11pt;
            font-family: Arial, sans-serif;
            white-space: pre-wrap;
            overflow-wrap: break-word;
            word-break: break-all;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid black;
            margin-top: 20px;
            color: black;
        }

        .footer-table td {
            border: 2px solid black;
            padding: 5px 10px;
            width: 50%;
            vertical-align: top;
            height: 80px;
        }

        .footer-line {
            border-top: 1px solid black;
            width: 100%;
            margin: 0 auto;
            display: block;
        }

        .chief-name {
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
        }

        /* --- PRINT MEDIA QUERIES (Zero margin, full page) --- */
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
                padding: 0.25in 0.5in 0.5in 0.5in !important;
                page-break-after: always !important;
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
                margin-left: -0.5in !important;
                margin-right: -0.5in !important;
                padding-top: 0 !important;
            }

            .fading-bar, .divider-line {
                print-color-adjust: exact !important;
                -webkit-print-color-adjust: exact !important;
            }
        }

        .table-custom {
            color: var(--text-main);
        }

        .table-custom th {
            background-color: var(--input-bg);
            color: var(--accent);
            border-color: var(--border);
        }

        .table-custom td {
            border-color: var(--border);
            background-color: transparent;
            color: var(--text-main);
            vertical-align: middle;
        }
    </style>
</head>

<body>

    <div class="navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-secondary fw-bold"><i class="fa fa-arrow-left me-2"></i> Back</a>
            <h4 class="m-0 fw-bold text-white">CCTV Review</h4>
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
                <div class="panel-title" id="form-panel-title">
                    <i class="fa fa-pen-to-square"></i> NEW REQUEST
                </div>
                <div class="badge-queue">QUEUE: <?php echo count($_SESSION['cctv_print_queue']); ?></div>
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

            <form method="POST" id="requestForm">
                <input type="hidden" name="edit_id" id="in_edit_id" value="">

                <input type="text" name="requestor_name" id="in_name" class="form-control" placeholder="Requestor Name"
                    required oninput="updatePreview()">
                <input type="text" name="level_section" id="in_lvl" class="form-control" placeholder="Level / Section"
                    required oninput="updatePreview()">

                <div class="row">
                    <div class="col-6">
                        <label class="small text-secondary mb-1">Incident Date</label>
                        <input type="date" name="incident_date" id="in_date" class="form-control" required
                            oninput="updatePreview()">
                    </div>
                    <div class="col-6">
                        <label class="small text-secondary mb-1">Incident Time</label>
                        <input type="time" name="incident_time" id="in_time" class="form-control" required
                            oninput="updatePreview()">
                    </div>
                </div>

                <input type="text" name="location" id="in_loc" class="form-control" placeholder="Location of Incident"
                    required oninput="updatePreview()">
                <textarea name="reason" id="in_reason" class="form-control" rows="3" placeholder="Reason for Review"
                    required oninput="updatePreview()"></textarea>
                <textarea name="evaluation" id="in_eval" class="form-control" rows="3"
                    placeholder="Evaluation (Optional)" oninput="updatePreview()"></textarea>

                <div class="row mt-2">
                    <div class="col-6">
                        <input type="text" name="assisted_by" id="in_assisted" class="form-control"
                            placeholder="Assisted By" required oninput="updatePreview()">
                    </div>
                    <div class="col-6">
                        <input type="text" name="reviewed_by" id="in_reviewed" class="form-control"
                            placeholder="Reviewed By" required oninput="updatePreview()">
                    </div>
                </div>

                <div id="add_btn_group">
                    <button type="submit" name="submit_request" class="btn btn-primary w-100 fw-bold py-3 mt-2">
                        <i class="fa fa-plus-circle me-2"></i> ADD TO QUEUE
                    </button>
                </div>

                <div id="edit_btn_group" style="display: none;" class="mt-2">
                    <button type="submit" name="update_request"
                        class="btn btn-warning w-100 fw-bold py-3 mb-2 text-white">
                        <i class="fa fa-save me-2"></i> UPDATE RECORD
                    </button>
                    <button type="button" class="btn btn-secondary w-100 fw-bold py-2" onclick="cancelEdit()">
                        <i class="fa fa-times me-2"></i> CANCEL EDIT
                    </button>
                </div>
            </form>

            <hr class="border-secondary my-4">

            <div class="d-flex gap-2 flex-wrap mb-2">
                <button onclick="printQueue()" class="btn btn-success flex-grow-1 fw-bold" <?php echo count($_SESSION['cctv_print_queue']) == 0 ? 'disabled' : ''; ?>>
                    <i class="fa fa-print me-2"></i> Print Queue (<?php echo count($_SESSION['cctv_print_queue']); ?>)
                </button>
                <button onclick="printBlank()" class="btn btn-info fw-bold text-white flex-grow-1">
                    <i class="fa fa-file me-2"></i> Blank Form
                </button>
            </div>

            <?php if (count($_SESSION['cctv_print_queue']) > 0): ?>
                <form method="POST" class="m-0 w-100 mt-2">
                    <button type="submit" name="clear_queue" class="btn btn-danger fw-bold w-100 py-2"
                        onclick="return confirm('Clear all items from print queue?')">
                        <i class="fa fa-trash me-2"></i> Clear Queue
                    </button>
                </form>
            <?php endif; ?>

        </div>

        <div class="right-panel">
            <div class="panel-header w-100 border-bottom pb-3 mb-4" id="preview-header"
                style="border-color: var(--border)!important;">
                <div class="panel-title w-100 d-flex justify-content-between align-items-center">
                    <span><i class="fa fa-eye"></i> DOCUMENT PREVIEW</span>
                </div>
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

                <div class="division-header">
                    <img src="background.png" alt="SAPD Logo" class="sapd-logo">
                    <div class="division-title">
                        <h2>SAFETY AND PROTECTION DIVISION</h2>
                        <h3>CCTV REVIEW FORM</h3>
                    </div>
                </div>

                <table class="form-table">
                    <tr>
                        <td class="label-cell">NAME</td>
                        <td class="input-cell" id="out_name"></td>
                    </tr>
                    <tr>
                        <td class="label-cell">SIGNATURE</td>
                        <td class="input-cell"></td>
                    </tr>
                    <tr>
                        <td class="label-cell">LEVEL/ SECTION</td>
                        <td class="input-cell" id="out_lvl"></td>
                    </tr>
                    <tr>
                        <td class="label-cell">DATE OF INCIDENT</td>
                        <td class="input-cell" id="out_date"></td>
                    </tr>
                    <tr>
                        <td class="label-cell">TIME OF INCIDENT</td>
                        <td class="input-cell" id="out_time"></td>
                    </tr>
                    <tr>
                        <td class="label-cell">LOCATION OF INCIDENT</td>
                        <td class="input-cell" id="out_loc"></td>
                    </tr>
                    <tr>
                        <td class="label-cell">REASON FOR CCTV REVIEW</td>
                        <td class="input-cell" id="out_reason" style="height: 60px; vertical-align: top;"></td>
                    </tr>
                </table>

                <div class="eval-section">
                    <strong>EVALUATION:</strong>
                    <div class="eval-box" id="out_eval"></div>
                </div>

                <table class="footer-table">
                    <tr>
                        <td>
                            <span class="footer-label">Assisted by:</span>
                            <div id="out_assisted"
                                style="text-align:center; font-weight:bold; margin-top: 15px; text-transform:uppercase; min-height: 20px;">
                            </div>
                            <span class="footer-line" style="margin-top: 5px;"></span>
                            <div style="text-align:center; font-size:10pt;">Safety & Protection Officer</div>
                        </td>
                        <td>
                            <span class="footer-label">CCTV Reviewed by:</span>
                            <div id="out_reviewed"
                                style="text-align:center; font-weight:bold; margin-top: 15px; text-transform:uppercase; min-height: 20px;">
                            </div>
                            <span class="footer-line" style="margin-top: 5px;"></span>
                            <div style="text-align:center; font-size:10pt;">Safety & Protection Officer</div>
                        </td>
                    </tr>
                </table>

                <div class="approval-section" style="margin-top: 30px;">
                    <i>Approved by:</i><br><br>
                    <span class="chief-name">PAUL JEFFREY T. LANSANGAN, SO3</span><br>
                    <span>CHIEF, Safety and Protection</span>
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
                        <input type="text" name="search" class="form-control" placeholder="Search Name/Location..." value="<?php echo htmlspecialchars($search_term); ?>" style="margin-bottom: 0;">
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
                        <th>Requestor</th>
                        <th>Level/Section</th>
                        <th>Location</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Created</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_requests && $recent_requests->num_rows > 0): ?>
                        <?php while ($row = $recent_requests->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['requestor_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['level_section']); ?></td>
                                <td><?php echo strtoupper($row['location']); ?></td>
                                <td><?php echo $row['incident_date']; ?></td>
                                <td><?php echo date('h:i A', strtotime($row['incident_time'])); ?></td>
                                <td><?php echo date('m/d/Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-center">
                                        <!-- VIEW BUTTON (copied from vaping incident) -->
                                        <button type="button" class="btn btn-sm btn-info text-white" title="View"
                                            onclick="viewRecord(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i class="fa fa-eye"></i>
                                        </button>

                                        <button type="button" class="btn btn-sm btn-warning text-white" title="Update"
                                            onclick="editRecord(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i class="fa fa-edit"></i>
                                        </button>

                                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger btn-delete"
                                            onclick="return confirm('Delete this record?')" title="Delete">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center opacity-50 py-4">
                                <i class="fa fa-database fa-2x mb-3"></i><br>
                                No records found.
                                <?php echo $search_term ? 'Try a different search.' : 'Submit a form to see records here.'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="print-area">
        <?php
        if (count($_SESSION['cctv_print_queue']) > 0):
            foreach ($_SESSION['cctv_print_queue'] as $index => $p):
                $t = strtotime($p['time']);
                $print_time = date("h:i A", $t);
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
                            <h3>CCTV REVIEW FORM</h3>
                        </div>
                    </div>
                    <table class="form-table">
                        <tr>
                            <td class="label-cell">NAME</td>
                            <td class="input-cell"><?php echo $p['name']; ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">SIGNATURE</td>
                            <td class="input-cell"></td>
                        </tr>
                        <tr>
                            <td class="label-cell">LEVEL/ SECTION</td>
                            <td class="input-cell"><?php echo $p['lvl']; ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">DATE OF INCIDENT</td>
                            <td class="input-cell"><?php echo $p['date']; ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">TIME OF INCIDENT</td>
                            <td class="input-cell"><?php echo $print_time; ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">LOCATION OF INCIDENT</td>
                            <td class="input-cell"><?php echo $p['loc']; ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">REASON FOR CCTV REVIEW</td>
                            <td class="input-cell" style="height: 60px; vertical-align: top;"><?php echo $p['reason']; ?></td>
                        </tr>
                    </table>
                    <div class="eval-section">
                        <strong>EVALUATION:</strong>
                        <div class="eval-box"><?php echo nl2br($p['eval']); ?></div>
                    </div>
                    <table class="footer-table">
                        <tr>
                            <td>
                                <span class="footer-label">Assisted by:</span>
                                <div
                                    style="text-align:center; font-weight:bold; margin-top: 15px; text-transform:uppercase; min-height: 20px;">
                                    <?php echo $p['assisted']; ?>
                                </div>
                                <span class="footer-line" style="margin-top: 5px;"></span>
                                <div style="text-align:center; font-size:10pt;">Safety & Protection Officer</div>
                            </td>
                            <td>
                                <span class="footer-label">CCTV Reviewed by:</span>
                                <div
                                    style="text-align:center; font-weight:bold; margin-top: 15px; text-transform:uppercase; min-height: 20px;">
                                    <?php echo $p['reviewed']; ?>
                                </div>
                                <span class="footer-line" style="margin-top: 5px;"></span>
                                <div style="text-align:center; font-size:10pt;">Safety & Protection Officer</div>
                            </td>
                        </tr>
                    </table>
                    <div class="approval-section" style="margin-top: 30px;">
                        <i>Approved by:</i><br><br>
                        <span class="chief-name">PAUL JEFFREY T. LANSANGAN, SO3</span><br>
                        <span>CHIEF, Safety and Protection</span>
                    </div>
                </div>
                <?php
            endforeach;
        else: ?>
            <div class="hcc-form">
                <div class="form-title" style="margin-top: 50px;">
                    <div class="form-title-text">
                        <h2>NO ITEMS IN PRINT QUEUE</h2>
                        <h3>Add forms to queue first</h3>
                    </div>
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
                    <h3>CCTV REVIEW FORM</h3>
                </div>
            </div>
            <table class="form-table">
                <tr>
                    <td class="label-cell">NAME</td>
                    <td class="input-cell">&nbsp;</td>
                </tr>
                <tr>
                    <td class="label-cell">SIGNATURE</td>
                    <td class="input-cell">&nbsp;</td>
                </tr>
                <tr>
                    <td class="label-cell">LEVEL/ SECTION</td>
                    <td class="input-cell">&nbsp;</td>
                </tr>
                <tr>
                    <td class="label-cell">DATE OF INCIDENT</td>
                    <td class="input-cell">&nbsp;</td>
                </tr>
                <tr>
                    <td class="label-cell">TIME OF INCIDENT</td>
                    <td class="input-cell">&nbsp;</td>
                </tr>
                <tr>
                    <td class="label-cell">LOCATION OF INCIDENT</td>
                    <td class="input-cell">&nbsp;</td>
                </tr>
                <tr>
                    <td class="label-cell">REASON FOR CCTV REVIEW</td>
                    <td class="input-cell" style="height: 60px;">&nbsp;</td>
                </tr>
            </table>
            <div class="eval-section">
                <strong>EVALUATION:</strong>
                <div class="eval-box"></div>
            </div>
            <table class="footer-table">
                <tr>
                    <td>
                        <span class="footer-label">Assisted by:</span>
                        <div
                            style="text-align:center; font-weight:bold; margin-top: 15px; text-transform:uppercase; min-height: 20px;">
                        </div>
                        <span class="footer-line" style="margin-top: 5px;"></span>
                        <div style="text-align:center; font-size:10pt;">Safety & Protection Officer</div>
                    </td>
                    <td>
                        <span class="footer-label">CCTV Reviewed by:</span>
                        <div
                            style="text-align:center; font-weight:bold; margin-top: 15px; text-transform:uppercase; min-height: 20px;">
                        </div>
                        <span class="footer-line" style="margin-top: 5px;"></span>
                        <div style="text-align:center; font-size:10pt;">Safety & Protection Officer</div>
                    </td>
                </tr>
            </table>
            <div class="approval-section" style="margin-top: 30px;">
                <i>Approved by:</i><br><br>
                <span class="chief-name">PAUL JEFFREY T. LANSANGAN, SO3</span><br>
                <span>CHIEF, Safety and Protection</span>
            </div>
        </div>
    </div>

    <script>
        // --- THEME TOGGLE LOGIC ---
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

        function printQueue() {
            document.body.classList.remove('print-blank');
            window.print();
        }

        function printBlank() {
            document.body.classList.add('print-blank');
            window.print();
        }

        // --- PREVIEW LOGIC ---
        function updatePreview() {
            document.getElementById('out_name').innerText = document.getElementById('in_name').value;
            document.getElementById('out_lvl').innerText = document.getElementById('in_lvl').value.toUpperCase();
            document.getElementById('out_loc').innerText = document.getElementById('in_loc').value.toUpperCase();

            const dateVal = document.getElementById('in_date').value;
            document.getElementById('out_date').innerText = dateVal || '';

            let timeVal = document.getElementById('in_time').value;
            if (timeVal) {
                let [h, m] = timeVal.split(':');
                let ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12;
                h = h ? h : 12;
                document.getElementById('out_time').innerText = `${h}:${m} ${ampm}`;
            } else {
                document.getElementById('out_time').innerText = '';
            }

            document.getElementById('out_reason').innerText = document.getElementById('in_reason').value;
            document.getElementById('out_eval').innerText = document.getElementById('in_eval').value;
            document.getElementById('out_assisted').innerText = document.getElementById('in_assisted').value;
            document.getElementById('out_reviewed').innerText = document.getElementById('in_reviewed').value;
        }

        // --- VIEW RECORD (copied from vaping incident) ---
        function viewRecord(data) {
            // Fill form inputs with record data
            document.getElementById('in_name').value = data.requestor_name;
            document.getElementById('in_lvl').value = data.level_section;
            document.getElementById('in_date').value = data.incident_date;
            document.getElementById('in_time').value = data.incident_time;
            document.getElementById('in_loc').value = data.location;
            document.getElementById('in_reason').value = data.reason;
            document.getElementById('in_eval').value = data.evaluation;
            document.getElementById('in_assisted').value = data.assisted_by;
            document.getElementById('in_reviewed').value = data.reviewed_by;

            // Clear edit_id and ensure add mode
            document.getElementById('in_edit_id').value = '';
            document.getElementById('add_btn_group').style.display = 'block';
            document.getElementById('edit_btn_group').style.display = 'none';
            document.getElementById('form-panel-title').innerHTML = `<i class="fa fa-pen-to-square"></i> NEW REQUEST`;

            // Update preview
            updatePreview();

            // Scroll to preview
            document.querySelector('.right-panel').scrollIntoView({ behavior: 'smooth' });
        }

        // --- EDIT RECORD LOGIC ---
        function editRecord(data) {
            // Scroll to form
            document.querySelector('.left-panel').scrollIntoView({ behavior: 'smooth' });

            // Change Form Panel Title
            document.getElementById('form-panel-title').innerHTML = `<i class="fa fa-edit"></i> EDIT REQUEST #${data.id}`;

            // Populate Form Fields
            document.getElementById('in_edit_id').value = data.id;
            document.getElementById('in_name').value = data.requestor_name;
            document.getElementById('in_lvl').value = data.level_section;
            document.getElementById('in_date').value = data.incident_date;
            document.getElementById('in_time').value = data.incident_time;
            document.getElementById('in_loc').value = data.location;
            document.getElementById('in_reason').value = data.reason;
            document.getElementById('in_eval').value = data.evaluation;
            document.getElementById('in_assisted').value = data.assisted_by;
            document.getElementById('in_reviewed').value = data.reviewed_by;

            // Swap Buttons
            document.getElementById('add_btn_group').style.display = 'none';
            document.getElementById('edit_btn_group').style.display = 'block';

            // Update preview
            updatePreview();
        }

        function cancelEdit() {
            // Reset Form Panel Title
            document.getElementById('form-panel-title').innerHTML = `<i class="fa fa-pen-to-square"></i> NEW REQUEST`;

            // Clear Form
            document.getElementById('requestForm').reset();
            document.getElementById('in_edit_id').value = '';

            // Swap Buttons Back
            document.getElementById('add_btn_group').style.display = 'block';
            document.getElementById('edit_btn_group').style.display = 'none';

            // Clear Preview
            updatePreview();
        }

        document.addEventListener('DOMContentLoaded', function () {
            updatePreview();
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>

</body>

</html>