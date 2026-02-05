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
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($table_sql);

// 2. AUTO-REPAIR: Check for ALL required columns and add them if missing
// This fixes the "bind_param" error by ensuring the table matches the code
$required_columns = [
    'requestor_name' => 'VARCHAR(255) NOT NULL',
    'level_section' => 'VARCHAR(255)',
    'incident_date' => 'DATE NOT NULL',
    'incident_time' => 'TIME',
    'location' => 'VARCHAR(255) NOT NULL',
    'reason' => 'TEXT NOT NULL',
    'evaluation' => 'TEXT'
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

    // Prepare SQL Statement
    $stmt = $conn->prepare("INSERT INTO cctv_requests (requestor_name, level_section, incident_date, incident_time, location, reason, evaluation) VALUES (?, ?, ?, ?, ?, ?, ?)");

    // SAFETY CHECK: If prepare() fails, $stmt is FALSE. We must check this to avoid the Fatal Error.
    if ($stmt === false) {
        $error_msg = "<strong>Database Error:</strong> The system could not prepare the database. <br>Details: " . $conn->error;
    } else {
        $stmt->bind_param("sssssss", $req_name, $lvl, $date, $time, $loc, $reason, $eval);

        if ($stmt->execute()) {
            // Success: Add to Session Queue
            $_SESSION['cctv_print_queue'][] = [
                'name' => $req_name,
                'lvl' => strtoupper($lvl),
                'date' => $date,
                'time' => $time,
                'loc' => strtoupper($loc),
                'reason' => $reason,
                'eval' => $eval
            ];

            // Redirect cleanly
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit();
        } else {
            $error_msg = "<strong>Save Failed:</strong> " . $stmt->error;
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
if (isset($_GET['success']))
    $success_msg = "Record saved successfully!";
if (isset($_GET['error']))
    $error_msg = "An error occurred.";

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
            padding: 0.5in;
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
            margin-bottom: 5px;
            margin-top: 10px;
            /* Added to move header lower */
        }

        /* --- LOGO STYLE (FIXED) --- */
        .logo-left {
            width: 170px !important;
            position: fixed !important;
            left: 5px !important;
            top: 35px !important;
            /* Changed from 35px to 45px to move logo lower */
            z-index: 50 !important;
        }

        /* --- HEADER BANNER: STRETCH TO FULL WIDTH AND POSITION LOWER --- */
        .header-banner {
            width: 100% !important;
            display: block;
            margin-left: -0.5in;
            /* Compensate for padding */
            margin-right: -0.5in;
            /* Compensate for padding */
            margin-top: 15px !important;
            /* Changed from -0.5in to 15px to move header lower */
            width: calc(100% + 1in) !important;
            /* Stretch beyond padding */
            max-width: none !important;
        }

        /* MODIFIED FORM TITLE FOR LOGO */
        .form-title {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            /* Space between logo and text */
            margin: 20px 0;
            color: black;
        }

        .form-title-text {
            text-align: center;
        }

        /* CHANGED FONT TO BOOKMAN OLD STYLE FOR SAFETY AND PROTECTION DIVISION */
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

        /* --- PRINT STYLES - FIXED FOR 1 PAGE --- */
        #print-area,
        #print-blank-area {
            display: none;
        }

        @media print {

            /* REMOVE PRINT HEADERS/FOOTERS FOR ALL PAPER SIZES */
            @page {
                margin: 0 !important;
                size: auto;
            }

            /* CHROME/SAFARI/EDGE SPECIFIC - REMOVE HEADER/FOOTER */
            @page :first {
                margin-top: 0 !important;
            }

            @page :left {
                margin-left: 0 !important;
            }

            @page :right {
                margin-right: 0 !important;
            }

            /* FIREFOX SPECIFIC - REMOVE HEADER/FOOTER */
            @page {
                margin: 0;
                margin-top: 0;
                margin-bottom: 0;
                margin-left: 0;
                margin-right: 0;

                /* Remove browser header/footer */
                marks: none;
                prince-shrink-to-fit: none;

                /* Set page size to auto to fit content */
                size: auto;
            }

            body {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* HIDE EVERYTHING EXCEPT PRINT AREA */
            .navbar,
            .main-container,
            .bottom-panel,
            .btn,
            .alert,
            .d-print-none {
                display: none !important;
            }

            /* SHOW PRINT AREA */
            #print-area {
                display: block !important;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* PRINT FORM STYLING */
            #print-area .hcc-form {
                transform: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0.5in !important;
                width: 8.5in !important;
                height: 11in !important;
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
                overflow: hidden !important;
            }

            /* FOR BLANK FORM PRINTING */
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
                margin: 0 !important;
                padding: 0 !important;
            }

            .print-blank #print-blank-area .hcc-form {
                transform: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0.5in !important;
                width: 8.5in !important;
                height: 11in !important;
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
                overflow: hidden !important;
            }

            /* STRETCH HEADER IN PRINT - POSITION LOWER */
            #print-area .header-banner,
            #print-blank-area .header-banner {
                width: calc(100% + 1in) !important;
                margin-left: -0.5in !important;
                margin-right: -0.5in !important;
                margin-top: 15px !important;
                /* Changed to match preview */
                max-width: none !important;
            }

            /* ADJUST LOGO POSITION IN PRINT */
            #print-area .logo-left,
            #print-blank-area .logo-left {
                top: 45px !important;
                /* Changed to match preview */
            }

            /* BOOKMAN OLD STYLE FONT IN PRINT */
            #print-area .form-title h2,
            #print-blank-area .form-title h2 {
                font-family: "Bookman Old Style", "Bookman", "URW Bookman L", serif !important;
                font-weight: 900 !important;
                font-size: 20px !important;
            }

            /* ENSURE SINGLE PAGE */
            .hcc-form {
                break-inside: avoid !important;
                break-after: avoid !important;
            }

            /* Remove print dialog header/footer */
            body * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                box-sizing: border-box !important;
            }

            /* Additional CSS to remove browser print headers/footers */
            html,
            body {
                height: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Remove URL, page numbers, date from print */
            @page {
                @top-left {
                    content: none !important;
                }

                @top-center {
                    content: none !important;
                }

                @top-right {
                    content: none !important;
                }

                @bottom-left {
                    content: none !important;
                }

                @bottom-center {
                    content: none !important;
                }

                @bottom-right {
                    content: none !important;
                }
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
                <div class="panel-title">
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

            <form method="POST">
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

                <button type="submit" name="submit_request" class="btn btn-primary w-100 fw-bold py-3 mt-2">
                    <i class="fa fa-plus-circle me-2"></i> ADD TO QUEUE
                </button>
            </form>

            <hr class="border-secondary my-4">

            <div class="d-flex gap-2 flex-wrap">
                <button onclick="printQueue()" class="btn btn-success flex-grow-1 fw-bold" <?php echo count($_SESSION['cctv_print_queue']) == 0 ? 'disabled' : ''; ?>>
                    <i class="fa fa-print me-2"></i> Print Queue (<?php echo count($_SESSION['cctv_print_queue']); ?>)
                </button>
                <button onclick="printBlank()" class="btn btn-info fw-bold text-white">
                    <i class="fa fa-file me-2"></i> Blank Form
                </button>
                <?php if (count($_SESSION['cctv_print_queue']) > 0): ?>
                    <form method="POST" class="m-0">
                        <button type="submit" name="clear_queue" class="btn btn-danger fw-bold"
                            onclick="return confirm('Clear all items from print queue?')">
                            <i class="fa fa-trash"></i>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="right-panel">
            <div class="panel-header w-100 border-bottom pb-3 mb-4" style="border-color: var(--border)!important;">
                <div class="panel-title"><i class="fa fa-eye"></i> DOCUMENT PREVIEW</div>
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
                            <span class="footer-line" style="margin-top: 30px;"></span>
                            <div style="text-align:center; font-size:10pt;">Safety & Protection Officer</div>
                        </td>
                        <td>
                            <span class="footer-label">CCTV Reviewed by:</span>
                            <span class="footer-line" style="margin-top: 30px;"></span>
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
                        <th>Requestor</th>
                        <th>Level/Section</th>
                        <th>Location</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Created</th>
                        <th>Action</th>
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
                                    <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger btn-delete"
                                        onclick="return confirm('Delete this record?')">
                                        <i class="fa fa-trash"></i>
                                    </a>
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
                            <td><span class="footer-label">Assisted by:</span><span class="footer-line"
                                    style="margin-top:30px;"></span>
                                <div style="text-align:center;">Safety & Protection Officer</div>
                            </td>
                            <td><span class="footer-label">CCTV Reviewed by:</span><span class="footer-line"
                                    style="margin-top:30px;"></span>
                                <div style="text-align:center;">Safety & Protection Officer</div>
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
                    <td><span class="footer-label">Assisted by:</span><span class="footer-line"
                            style="margin-top:30px;"></span>
                        <div style="text-align:center;">Safety & Protection Officer</div>
                    </td>
                    <td><span class="footer-label">CCTV Reviewed by:</span><span class="footer-line"
                            style="margin-top:30px;"></span>
                        <div style="text-align:center;">Safety & Protection Officer</div>
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
        // --- UPDATED THEME TOGGLE LOGIC ---
        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            const isLight = document.body.classList.contains('light-mode');
            document.getElementById('themeBtn').innerHTML = isLight ? '<i class="fa fa-sun"></i>' : '<i class="fa fa-moon"></i>';

            const themeValue = isLight ? 'light' : 'dark';
            localStorage.setItem('appTheme', themeValue);
            document.cookie = "theme=" + themeValue + "; path=/; max-age=31536000";
        }

        // Check saved theme on load
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

        function updatePreview() {
            document.getElementById('out_name').innerText = document.getElementById('in_name').value;
            document.getElementById('out_lvl').innerText = document.getElementById('in_lvl').value.toUpperCase();
            document.getElementById('out_loc').innerText = document.getElementById('in_loc').value.toUpperCase();

            // Update date - show blank if empty
            const dateVal = document.getElementById('in_date').value;
            document.getElementById('out_date').innerText = dateVal || '';

            // Update time - show blank if empty
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
        }

        // Auto-update preview on page load
        document.addEventListener('DOMContentLoaded', function () {
            updatePreview();

            // Auto-dismiss alerts after 5 seconds
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