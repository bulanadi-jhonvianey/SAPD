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

        .btn-primary {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
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

        .btn-secondary {
            background: linear-gradient(135deg, #858796 0%, #60616f 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
            color: white;
        }

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

        .header-layout {
            position: relative;
            width: 100%;
            margin-bottom: 0px;
            margin-top: 0px; 
            min-height: 100px;  /* Increased to accommodate banner margin */
        }

        .logo-left {
            width: 185px !important;
            position: fixed !important; 
            left: -5px !important;
            top: 25px !important; 
            z-index: 50 !important;
        }

        .header-banner {
            width: calc(100% + 1in) !important;
            height: 65px !important;
            object-fit: fill;
            display: block;
            margin-left: -0.5in;
            margin-right: -0.5in;
            margin-top: 30px !important;   /* Increased from 0px to 30px */
            max-width: none !important;
        }

        .form-title {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: -5px 0 15px 0; 
            color: black;
        }

        .form-title-text {
            text-align: center;
        }

        .form-title h2 {
            font-family: "Bookman Old Style", "Bookman", "URW Bookman L", serif !important;
            font-weight: 900 !important;
            font-size: 20px !important;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-title h3 {
            font-family: Arial, sans-serif;
            font-weight: bold;
            font-size: 14px;
            margin: 5px 0 0 0;
            text-decoration: underline;
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

        /* Base print hide classes */
        #print-area,
        #print-blank-area {
            display: none;
        }

        @media print {

            @page {

                size: auto;
                margin: 0mm !important;
            }

            body {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* HIDE EVERYTHING EXCEPT THE FORM ITSELF */
            .navbar,
            .main-container,
            .bottom-panel,
            .btn,
            .alert,
            .d-print-none {
                display: none !important;
            }

            /* DISPLAY ACTIVE PRINT AREA AS NORMAL DOCUMENT FLOW */
            #print-area {
                display: block !important;

                width: 100%;

                margin: 0 !important;
                padding: 0 !important;
            }

            .print-blank #print-area {
                display: none !important;
            }

            #print-blank-area {
                display: none !important;
            }

            .print-blank #print-blank-area {
                display: block !important;

                width: 100%;

                margin: 0 !important;
                padding: 0 !important;
            }

            /* PRINT FORM CONTAINER */
            #print-area .hcc-form,
            #print-blank-area .hcc-form {
                transform: none !important;
                box-shadow: none !important;
                margin: 0 auto !important;
                padding: 0.25in 0.5in 0.5in 0.5in !important;
                width: 100% !important;
                max-width: 8.5in !important;
                height: auto !important;
                min-height: 10in !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
                box-sizing: border-box !important;
                position: relative !important;
            }

            #print-area .header-layout,
            #print-blank-area .header-layout {
                margin-top: 0 !important;
            }

            #print-area .form-title,
            #print-blank-area .form-title {
                margin-top: -5px !important;
                margin-bottom: 10px !important;
            }

            #print-area .hcc-form:last-of-type,
            #print-blank-area .hcc-form:last-of-type {
                page-break-after: auto !important;
                break-after: auto !important;
            }

            /* Banner top margin increased to match screen */
            #print-area .header-banner,
            #print-blank-area .header-banner {
                width: calc(100% + 1in) !important;
                height: 65px !important;
                object-fit: fill;
                margin-left: -0.5in !important;
                margin-right: -0.5in !important;
                margin-top: 30px !important;   /* Increased from 0px to 30px */
                max-width: none !important;
            }

            /* Logo remains fixed at top-left corner */
            #print-area .logo-left,
            #print-blank-area .logo-left {
                width: 180px !important;
                position: fixed !important;
                left: -5px !important;
                top: 25px !important;
            }

            #print-area .form-title h2,
            #print-blank-area .form-title h2 {
                font-family: "Bookman Old Style", "Bookman", "URW Bookman L", serif !important;
                font-weight: 900 !important;
                font-size: 20px !important;
            }

            @page {
                @top-left { content: none !important; }
                @top-center { content: none !important; }
                @top-right { content: none !important; }
                @bottom-left { content: none !important; }
                @bottom-center { content: none !important; }
                @bottom-right { content: none !important; }
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
                    required oninput="updatePreview(true)">
                <input type="text" name="level_section" id="in_lvl" class="form-control" placeholder="Level / Section"
                    required oninput="updatePreview(true)">

                <div class="row">
                    <div class="col-6">
                        <label class="small text-secondary mb-1">Incident Date</label>
                        <input type="date" name="incident_date" id="in_date" class="form-control" required
                            oninput="updatePreview(true)">
                    </div>
                    <div class="col-6">
                        <label class="small text-secondary mb-1">Incident Time</label>
                        <input type="time" name="incident_time" id="in_time" class="form-control" required
                            oninput="updatePreview(true)">
                    </div>
                </div>

                <input type="text" name="location" id="in_loc" class="form-control" placeholder="Location of Incident"
                    required oninput="updatePreview(true)">
                <textarea name="reason" id="in_reason" class="form-control" rows="3" placeholder="Reason for Review"
                    required oninput="updatePreview(true)"></textarea>
                <textarea name="evaluation" id="in_eval" class="form-control" rows="3"
                    placeholder="Evaluation (Optional)" oninput="updatePreview(true)"></textarea>

                <div class="row mt-2">
                    <div class="col-6">
                        <input type="text" name="assisted_by" id="in_assisted" class="form-control"
                            placeholder="Assisted By" required oninput="updatePreview(true)">
                    </div>
                    <div class="col-6">
                        <input type="text" name="reviewed_by" id="in_reviewed" class="form-control"
                            placeholder="Reviewed By" required oninput="updatePreview(true)">
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
                <div class="header-layout">
                    <img src="background-hcc-logo.png" alt="Logo" class="logo-left">
                    <img src="header_hcc.png" alt="Header" class="header-banner">
                </div>

                <div class="form-title">
                    <img src="background.png" alt="SAPD Logo" style="width: 45px; height: auto;">
                    <div class="form-title-text">
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
                    <div class="header-layout">
                        <img src="background-hcc-logo.png" alt="Logo" class="logo-left">
                        <img src="header_hcc.png" alt="Header" class="header-banner">
                    </div>
                    <div class="form-title">
                        <img src="background.png" alt="SAPD Logo" style="width: 45px; height: auto;">
                        <div class="form-title-text">
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
                <div class="form-title">
                    <h2>NO ITEMS IN PRINT QUEUE</h2>
                    <h3>Add forms to queue first</h3>
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
                <img src="background.png" alt="SAPD Logo" style="width: 45px; height: auto;">
                <div class="form-title-text">
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
        let isViewing = false;

        function updatePreview(fromInput = false) {
            // If the user starts typing in the new request form, clear out the saved view.
            if (fromInput && isViewing) {
                clearView();
            }

            if (isViewing) return; // Prevent live-typing updates while viewing a saved record

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

        // --- VIEW RECORD LOGIC ---
        function viewRecord(data) {
            isViewing = true;

            // Update Panel Header 
            document.getElementById('preview-header').innerHTML = `
                <div class="panel-title w-100 d-flex justify-content-between align-items-center text-info">
                    <span><i class="fa fa-eye"></i> VIEWING RECORD #${data.id}</span>
                    <button class="btn btn-sm btn-danger" onclick="clearView()">Close View</button>
                </div>
            `;

            // Inject Database Data into Preview
            document.getElementById('out_name').innerText = data.requestor_name;
            document.getElementById('out_lvl').innerText = data.level_section.toUpperCase();
            document.getElementById('out_loc').innerText = data.location.toUpperCase();
            document.getElementById('out_date').innerText = data.incident_date;

            let timeVal = data.incident_time;
            if (timeVal) {
                let [h, m] = timeVal.split(':');
                let ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12;
                h = h ? h : 12;
                document.getElementById('out_time').innerText = `${h}:${m} ${ampm}`;
            } else {
                document.getElementById('out_time').innerText = '';
            }

            document.getElementById('out_reason').innerText = data.reason;
            document.getElementById('out_eval').innerText = data.evaluation;
            document.getElementById('out_assisted').innerText = data.assisted_by.toUpperCase();
            document.getElementById('out_reviewed').innerText = data.reviewed_by.toUpperCase();

            // Scroll up to view smoothly
            document.querySelector('.right-panel').scrollIntoView({ behavior: 'smooth' });
        }

        function clearView() {
            isViewing = false;

            // Reset Panel Header
            document.getElementById('preview-header').innerHTML = `
                <div class="panel-title w-100 d-flex justify-content-between align-items-center">
                    <span><i class="fa fa-eye"></i> DOCUMENT PREVIEW</span>
                </div>
            `;

            // Immediately run updatePreview to re-fetch whatever is typed in the inputs
            updatePreview();
        }

        // --- NEW EDIT RECORD LOGIC ---
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

            // Stop 'View' mode if it's currently on, and force the preview to match the newly filled form
            clearView();
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