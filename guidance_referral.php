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
    reasons TEXT DEFAULT NULL, -- Stores JSON of selected checkboxes
    other_reason VARCHAR(255) DEFAULT NULL,
    description TEXT NOT NULL,
    image_paths TEXT DEFAULT NULL, 
    status VARCHAR(50) DEFAULT 'Recorded',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($table_sql);

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

// HANDLE: ADD REQUEST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $student = $conn->real_escape_string($_POST['student_name']);
    $grade = $conn->real_escape_string($_POST['grade_section']);
    $referrer = $conn->real_escape_string($_POST['referrer']);
    $date = $conn->real_escape_string($_POST['referral_date']);
    $time = $conn->real_escape_string($_POST['referral_time']);
    $desc = $conn->real_escape_string($_POST['description']);
    $other = $conn->real_escape_string($_POST['other_reason']);
    
    // Handle Checkboxes
    $reasons = isset($_POST['reasons']) ? json_encode($_POST['reasons']) : json_encode([]);

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

        $stmt = $conn->prepare("INSERT INTO guidance_referrals (student_name, grade_section, referrer, referral_date, referral_time, reasons, other_reason, description, image_paths) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt === false) {
            $error_msg = "<strong>Database Error:</strong> " . $conn->error;
        } else {
            $stmt->bind_param("sssssssss", $student, $grade, $referrer, $date, $time, $reasons, $other, $desc, $image_paths_json);

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

// HANDLE: CLEAR QUEUE
if (isset($_POST['clear_queue'])) {
    $_SESSION['guidance_print_queue'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['success']))
    $success_msg = "Referral recorded successfully!";
if (isset($_GET['error']))
    $error_msg = "An error occurred.";

// --- SEARCH LOGIC ---
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
            padding: 0;
        }

        /* --- PAPER FORM DESIGN (Guidance Style) --- */
        .hcc-form {
            width: 8.5in;
            height: 14in;
            background: white;
            color: black;
            padding: 0.5in;
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

        /* HEADER LAYOUT - REVISED FOR LOGO ON LEFT */
        .header-layout {
            position: relative;
            width: 100%;
            margin-bottom: 10px;
        }

        .logo-left {
            width: 185px !important;
            position: fixed !important;
            left: -3px !important; /* Adjusted to match Incident Report */
            top: 35px !important; /* Adjusted to match Incident Report */
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

        .division-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            position: relative;
            z-index: 60;
        }
        
        /* NEW SAPD LOGO STYLE */
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

        /* Form Table Section */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid black;
            margin-bottom: 15px;
            table-layout: fixed; /* FIXED: Keeps table from expanding beyond width */
        }

        .info-table td {
            border: 2px solid black;
            padding: 5px 8px;
            font-weight: bold;
            font-size: 11pt;
            font-family: Arial, sans-serif;
            color: black;
            vertical-align: top;
        }

        .info-table .label-col {
            width: 35%;
            white-space: nowrap;
        }

        .info-table .input-cell {
            font-family: "Calibri", "Gill Sans", sans-serif; /* CHANGED: Calibri Font */
            text-transform: uppercase;
            word-wrap: break-word; /* FIXED: Wraps text */
            overflow-wrap: break-word;
        }

        /* Checkbox Section - REVISED FOR LINES */
        .referral-reasons {
            margin-bottom: 10px;
            line-height: 1.6;
            font-size: 11pt;
            color: black;
        }

        .reason-line {
            display: flex;
            align-items: center;
            margin-bottom: 3px;
        }

        .check-line-print {
            display: inline-flex;
            width: 30px;
            border-bottom: 1px solid black;
            margin-right: 10px;
            align-items: flex-end;
            justify-content: center;
            font-weight: bold;
            height: 18px;
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
            font-family: "Calibri", "Gill Sans", sans-serif; /* CHANGED: Calibri Font */
            font-weight: bold;
        }

        /* Incident Description Section */
        .incident-box {
            border: 2px solid black;
            height: 500px;
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
            padding: 10px;
            font-family: Arial, sans-serif;
            font-size: 11pt;
            white-space: pre-wrap;
            flex-grow: 1;
            overflow: hidden;
            word-wrap: break-word; /* FIXED: Wraps text inside box */
            overflow-wrap: break-word;
        }

        /* Footer Section */
        .footer {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 11pt;
            color: black;
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
            font-family: "Calibri", "Gill Sans", sans-serif; /* CHANGED: Calibri Font */
            font-weight: bold;
        }

        /* Removed .form-code style as the element is deleted */

        .image-section {
            display: none;
            position: absolute;
            bottom: 5px;
            left: 5px;
            right: 5px;
        }

        .paper-preview-img {
            max-width: 48%;
            max-height: 200px;
            border: 1px solid #ccc;
            margin: 5px;
            display: inline-block;
        }

        /* --- PRINT MEDIA QUERIES --- */
        @page { size: 8.5in 14in; margin: 0; }
        #print-area, #print-blank-area { display: none; }

        @media print {
            body { margin: 0 !important; padding: 0 !important; background: white !important; -webkit-print-color-adjust: exact !important; }
            .navbar, .main-container, .bottom-panel, .btn, .d-print-none { display: none !important; }
            
            #print-area {
                display: block !important;
                position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            }
            #print-area .hcc-form {
                transform: none !important;
                box-shadow: none !important;
                margin: 0 auto !important;
                width: 8.5in !important;
                height: 14in !important;
            }
            
            .print-blank #print-area { display: none !important; }
            .print-blank #print-blank-area { display: block !important; position: absolute; top: 0; left: 0; }
            .print-blank #print-blank-area .hcc-form { transform: none !important; box-shadow: none !important; margin: 0 auto !important; }
            
            .image-section { display: block !important; }

            .header-banner {
                width: calc(100% + 1in) !important;
                margin-left: -0.5in !important;
                margin-right: -0.5in !important;
            }
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
        .table-custom td { color: var(--text-main) !important; border-color: var(--border); }
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
                        <input type="text" name="student_name" id="in_student" class="form-control" placeholder="Student's/Pupil's Name" required oninput="updatePreview()">
                    </div>
                    <div class="col-12">
                        <input type="text" name="grade_section" id="in_grade" class="form-control" placeholder="Grade/Year/Course/Section" required oninput="updatePreview()">
                    </div>
                    <div class="col-12">
                        <input type="text" name="referrer" id="in_referrer" class="form-control" placeholder="Person making referral" required oninput="updatePreview()">
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="small text-secondary mb-1">Date</label>
                        <input type="date" name="referral_date" id="in_date" class="form-control" required oninput="updatePreview()">
                    </div>
                    <div class="col-6">
                        <label class="small text-secondary mb-1">Time</label>
                        <input type="time" name="referral_time" id="in_time" class="form-control" required oninput="updatePreview()">
                    </div>
                </div>

                <div class="mb-3 p-3" style="background: var(--input-bg); border-radius: 8px; border: 1px solid var(--border);">
                    <label class="fw-bold mb-2">Reason/s for referral:</label>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Victim of bullying" id="chk_bully" onchange="updatePreview()">
                        <label class="form-check-label" for="chk_bully">Victim of bullying</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Suspected victim of abuse" id="chk_abuse" onchange="updatePreview()">
                        <label class="form-check-label" for="chk_abuse">Suspected victim of abuse</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Family issues/conflict" id="chk_family" onchange="updatePreview()">
                        <label class="form-check-label" for="chk_family">Family issues/conflict</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Brawling Incident" id="chk_brawl" onchange="updatePreview()">
                        <label class="form-check-label" for="chk_brawl">Brawling Incident</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Damage to School Property" id="chk_damage" onchange="updatePreview()">
                        <label class="form-check-label" for="chk_damage">Damage to School Property</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Possession of Deadly Weapon" id="chk_weapon" onchange="updatePreview()">
                        <label class="form-check-label" for="chk_weapon">Possession of Deadly Weapon</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reasons[]" value="Possession of Prohibited Drugs" id="chk_drugs" onchange="updatePreview()">
                        <label class="form-check-label" for="chk_drugs">Possession of Prohibited Drugs</label>
                    </div>
                    
                    <div class="mt-2">
                        <label class="small text-secondary">Others (Specify):</label>
                        <input type="text" name="other_reason" id="in_other" class="form-control form-control-sm" oninput="updatePreview()">
                    </div>
                </div>

                <textarea name="description" id="in_desc" class="form-control" rows="6" placeholder="Description of Incident (What happened, persons involved, dates)..." required oninput="updatePreview()"></textarea>

                <div class="mb-3 mt-3">
                    <label class="small text-secondary mb-2 d-block"><i class="fa fa-images me-1"></i> Attach Images (Optional)</label>
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
                <div class="header-layout">
                    <img src="background-hcc-logo.png" alt="Logo" class="logo-left">
                    <img src="header_hcc.png" alt="Header" class="header-banner">
                </div>

                <div class="division-header">
                    <img src="background.png" class="sapd-logo" alt="SAPD Logo">
                    <div class="division-title">
                        <h2>SAFETY AND PROTECTION DIVISION</h2>
                        <h3>GUIDANCE REFERRAL FORM</h3>
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
                        <td class="label-col">TIME:</td>
                        <td class="input-cell" id="out_time"></td>
                    </tr>
                    <tr>
                        <td class="label-col">DATE:</td>
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
                        <div class="incident-title">DESCRIPTION OF INCIDENT</div>
                        <div class="incident-subtitle">What happened, persons involved, specific dates/events</div>
                    </div>
                    <div class="incident-content">
                        <span id="out_desc"></span>
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
        ?>
        <div class="hcc-form">
            <div class="header-layout">
                <img src="background-hcc-logo.png" alt="Logo" class="logo-left">
                <img src="header_hcc.png" alt="Header" class="header-banner">
            </div>
            <div class="division-header">
                <img src="background.png" class="sapd-logo" alt="SAPD Logo">
                <div class="division-title">
                    <h2>SAFETY AND PROTECTION DIVISION</h2>
                    <h3>GUIDANCE REFERRAL FORM</h3>
                </div>
            </div>
            <table class="info-table">
                <tr><td class="label-col">Student's/ Pupil's Name</td><td class="input-cell"><?php echo strtoupper($p['student']); ?></td></tr>
                <tr><td class="label-col">Grade/Year/Course/Section</td><td class="input-cell"><?php echo strtoupper($p['grade']); ?></td></tr>
                <tr><td class="label-col">Person making referral</td><td class="input-cell"><?php echo strtoupper($p['referrer']); ?></td></tr>
                <tr><td class="label-col">TIME:</td><td class="input-cell"><?php echo $print_time; ?></td></tr>
                <tr><td class="label-col">DATE:</td><td class="input-cell"><?php echo $p['date']; ?></td></tr>
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
                <div class="reason-line">Please specify: <div class="specify-line"><?php echo strtoupper($p['other']); ?></div></div>
            </div>
            <div class="incident-box">
                <div class="incident-header"><div class="incident-title">DESCRIPTION OF INCIDENT</div><div class="incident-subtitle">What happened, persons involved, specific dates/events</div></div>
                <div class="incident-content"><?php echo nl2br($p['desc']); ?>
                    <?php if (!empty($p['image_paths']) && is_array($p['image_paths'])): ?>
                        <div class="image-section" style="display:block;">
                            <?php foreach ($p['image_paths'] as $path): if (file_exists($path)): ?><img src="<?php echo $path; ?>" class="paper-preview-img"><?php endif; endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="footer">
                <div class="footer-left"><div>Received by:</div><div style="margin-top: 20px;">Intake Counsellor: <span class="signature-line"></span></div></div>
                <div class="footer-right"><div style="margin-top: 35px;">Date: <span class="date-line"><?php echo $p['date']; ?></span></div></div>
            </div>
        </div>
        <?php endforeach; else: ?><div class="hcc-form"><h2>NO ITEMS IN QUEUE</h2></div><?php endif; ?>
    </div>

    <div id="print-blank-area">
        <div class="hcc-form">
            <div class="header-layout">
                <img src="background-hcc-logo.png" alt="Logo" class="logo-left">
                <img src="header_hcc.png" alt="Header" class="header-banner">
            </div>
            <div class="division-header">
                <img src="background.png" class="sapd-logo" alt="SAPD Logo">
                <div class="division-title">
                    <h2>SAFETY AND PROTECTION DIVISION</h2>
                    <h3>GUIDANCE REFERRAL FORM</h3>
                </div>
            </div>
            <table class="info-table">
                <tr><td class="label-col">Student's/ Pupil's Name</td><td class="input-cell"></td></tr>
                <tr><td class="label-col">Grade/Year/Course/Section</td><td class="input-cell"></td></tr>
                <tr><td class="label-col">Person making referral</td><td class="input-cell"></td></tr>
                <tr><td class="label-col">TIME:</td><td class="input-cell"></td></tr>
                <tr><td class="label-col">DATE:</td><td class="input-cell"></td></tr>
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
                <div class="incident-header"><div class="incident-title">DESCRIPTION OF INCIDENT</div><div class="incident-subtitle">What happened, persons involved, specific dates/events</div></div>
                <div class="incident-content"></div>
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
                                'images' => json_decode($row['image_paths'], true)
                            ];
                            $preview_json = htmlspecialchars(json_encode($preview_data), ENT_QUOTES, 'UTF-8');
                            
                            $reasons_arr = json_decode($row['reasons'], true);
                            $reason_display = !empty($reasons_arr) ? implode(", ", $reasons_arr) : '-';
                            if($row['other_reason']) $reason_display .= " (" . $row['other_reason'] . ")";
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['grade_section']); ?></td>
                                <td><?php echo htmlspecialchars($row['referrer']); ?></td>
                                <td><?php echo $row['referral_date']; ?></td>
                                <td><small><?php echo mb_strimwidth($reason_display, 0, 40, "..."); ?></small></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-sm btn-info text-white" onclick="loadToPreview(<?php echo $preview_json; ?>)"><i class="fa fa-eye"></i></button>
                                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this record?')"><i class="fa fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4"><i class="fa fa-database fa-2x mb-3"></i><br>No records found.</td></tr>
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
            localStorage.setItem('appTheme', isLight ? 'light' : 'dark');
        }

        const savedTheme = localStorage.getItem('appTheme') || 'dark';
        if (savedTheme === 'light') { document.body.classList.add('light-mode'); document.getElementById('themeBtn').innerHTML = '<i class="fa fa-sun"></i>'; }

        function printQueue() { document.body.classList.remove('print-blank'); window.print(); }
        function printBlank() { document.body.classList.add('print-blank'); window.print(); }

        let loadedImages = [];
        let isLoadedMode = false;

        function updatePreview() {
            document.getElementById('out_student').innerText = document.getElementById('in_student').value.toUpperCase();
            document.getElementById('out_grade').innerText = document.getElementById('in_grade').value.toUpperCase();
            document.getElementById('out_referrer').innerText = document.getElementById('in_referrer').value.toUpperCase();
            document.getElementById('out_date').innerText = document.getElementById('in_date').value || '';
            document.getElementById('out_date_footer').innerText = document.getElementById('in_date').value || '';
            
            let timeVal = document.getElementById('in_time').value;
            if (timeVal) {
                let [h, m] = timeVal.split(':');
                let ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12; h = h ? h : 12;
                document.getElementById('out_time').innerText = `${h}:${m} ${ampm}`;
            } else { document.getElementById('out_time').innerText = ''; }

            // Checkbox Mapping with Checkmarks
            const map = {
                'chk_bully': 'p_bully', 'chk_abuse': 'p_abuse', 'chk_family': 'p_family',
                'chk_brawl': 'p_brawl', 'chk_damage': 'p_damage', 'chk_weapon': 'p_weapon', 'chk_drugs': 'p_drugs'
            };
            for (let id in map) {
                // If checked, inject checkmark span, else empty
                document.getElementById(map[id]).innerHTML = document.getElementById(id).checked ? '<span class="check-mark">✓</span>' : '';
            }

            document.getElementById('out_other').innerText = document.getElementById('in_other').value.toUpperCase();
            document.getElementById('out_desc').innerText = document.getElementById('in_desc').value;

            // Image Preview
            const paperImageContainer = document.getElementById('out_images_container');
            const fileInput = document.getElementById('in_images');

            if (fileInput.files.length > 0) {
                paperImageContainer.innerHTML = ''; paperImageContainer.style.display = 'block';
                [...fileInput.files].forEach(file => {
                    let reader = new FileReader();
                    reader.onload = function (e) { let img = document.createElement('img'); img.src = e.target.result; img.className = 'paper-preview-img'; paperImageContainer.appendChild(img); }
                    reader.readAsDataURL(file);
                });
            } else if (loadedImages.length > 0) {
                paperImageContainer.innerHTML = ''; paperImageContainer.style.display = 'block';
                loadedImages.forEach(src => { let img = document.createElement('img'); img.src = src; img.className = 'paper-preview-img'; paperImageContainer.appendChild(img); });
            } else { paperImageContainer.innerHTML = ''; paperImageContainer.style.display = 'none'; }
        }

        function loadToPreview(data) {
            document.getElementById('in_student').value = data.student || '';
            document.getElementById('in_grade').value = data.grade || '';
            document.getElementById('in_referrer').value = data.referrer || '';
            document.getElementById('in_date').value = data.date || '';
            document.getElementById('in_time').value = data.time || '';
            document.getElementById('in_desc').value = data.desc || '';
            document.getElementById('in_other').value = data.other || '';

            // Reset checkboxes then check if in array
            document.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false);
            if (data.reasons && Array.isArray(data.reasons)) {
                data.reasons.forEach(r => {
                    let cb = document.querySelector(`input[value="${r}"]`);
                    if(cb) cb.checked = true;
                });
            }

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
            updatePreview();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('referralForm').reset();
            document.getElementById('in_images').value = "";
            loadedImages = []; isLoadedMode = false;
            document.getElementById('form-image-previews').innerHTML = "";
            updatePreview();
        }

        const fileInput = document.getElementById('in_images');
        const formPreviewContainer = document.getElementById('form-image-previews');
        let dt = new DataTransfer();

        fileInput.addEventListener('change', function () {
            if (isLoadedMode) { loadedImages = []; isLoadedMode = false; formPreviewContainer.innerHTML = ''; }
            for (let file of this.files) { dt.items.add(file); }
            this.files = dt.files; renderFormPreviews(); updatePreview();
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
        function removeFile(index) { dt.items.remove(index); fileInput.files = dt.files; renderFormPreviews(); updatePreview(); }
        document.addEventListener('DOMContentLoaded', function () { updatePreview(); setTimeout(() => { document.querySelectorAll('.alert').forEach(a => new bootstrap.Alert(a).close()); }, 5000); });
    </script>
</body>
</html>