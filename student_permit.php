<?php
    // --- 1. SETUP & CONFIGURATION ---
    ob_start();
    session_start();
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // Database Credentials
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "sapd_db"; 

    // Create Connection
    $conn = new mysqli($servername, $username, $password);
    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

    // Initialize Database & Table
    $conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
    $conn->select_db($dbname);

    $table_sql = "CREATE TABLE IF NOT EXISTS student_permits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        department VARCHAR(255) NOT NULL,
        plate_number VARCHAR(50) NOT NULL,
        fb_link TEXT,
        permit_number INT NOT NULL, 
        school_year VARCHAR(50) NOT NULL,
        valid_until VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($table_sql);

    // --- AUTOMATIC DATA REPAIR ---
    $conn->query("UPDATE student_permits SET permit_number = id WHERE permit_number = 0 OR permit_number IS NULL");

    // Initialize Session Queue
    if (!isset($_SESSION['student_print_queue'])) { $_SESSION['student_print_queue'] = []; }

    // Initialize Layout Settings in Session
    if (!isset($_SESSION['student_layout_settings'])) {
        $_SESSION['student_layout_settings'] = [
            'school_year' => 'Enter AY',
            'card_w' => 350, 'card_h' => 240,
            'name_size' => 12, 'name_x' => 11, 'name_y' => 110,
            'dept_size' => 11, 'dept_x' => 0, 'dept_y' => 129,
            'plate_size' => 11, 'plate_x' => 6, 'plate_y' => 180, 
            'valid_until_size' => 9, 'valid_until_x' => 8, 'valid_until_y' => 197,
            'qr_size' => 60, 'qr_x' => 5, 'qr_y' => 15,
            'count_size' => 20, 'count_x' => 0, 'count_y' => -25,
            'sy_size' => 11, 'sy_x' => 0, 'sy_y' => 58
        ];
    }

    // --- FIX FOR POSITION BUG (AUTO-CORRECT) ---
    if (isset($_SESSION['student_layout_settings']['plate_y']) && $_SESSION['student_layout_settings']['plate_y'] < 100) {
        $_SESSION['student_layout_settings']['plate_y'] = 180; 
        $_SESSION['student_layout_settings']['plate_x'] = 6;   
    }

    // --- 2. FORM HANDLERS ---

    // HANDLE: UPDATE LAYOUT SETTINGS
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_layout'])) {
        $_SESSION['student_layout_settings']['school_year'] = $_POST['school_year'] ?? 'Enter AY';
        $_SESSION['student_layout_settings']['card_w'] = intval($_POST['card_w'] ?? 350);
        $_SESSION['student_layout_settings']['card_h'] = intval($_POST['card_h'] ?? 240);
        $_SESSION['student_layout_settings']['name_size'] = intval($_POST['name_size'] ?? 12);
        $_SESSION['student_layout_settings']['name_x'] = intval($_POST['name_x'] ?? 0);
        $_SESSION['student_layout_settings']['name_y'] = intval($_POST['name_y'] ?? 120);
        $_SESSION['student_layout_settings']['dept_size'] = intval($_POST['dept_size'] ?? 11);
        $_SESSION['student_layout_settings']['dept_x'] = intval($_POST['dept_x'] ?? 0);
        $_SESSION['student_layout_settings']['dept_y'] = intval($_POST['dept_y'] ?? 139);
        $_SESSION['student_layout_settings']['plate_size'] = intval($_POST['plate_size'] ?? 11);
        $_SESSION['student_layout_settings']['plate_x'] = intval($_POST['plate_x'] ?? 45);
        $_SESSION['student_layout_settings']['plate_y'] = intval($_POST['plate_y'] ?? 35);
        $_SESSION['student_layout_settings']['valid_until_size'] = intval($_POST['valid_until_size'] ?? 9);
        $_SESSION['student_layout_settings']['valid_until_x'] = intval($_POST['valid_until_x'] ?? 8);
        $_SESSION['student_layout_settings']['valid_until_y'] = intval($_POST['valid_until_y'] ?? 197);
        $_SESSION['student_layout_settings']['qr_size'] = intval($_POST['qr_size'] ?? 60);
        $_SESSION['student_layout_settings']['qr_x'] = intval($_POST['qr_x'] ?? 5);
        $_SESSION['student_layout_settings']['qr_y'] = intval($_POST['qr_y'] ?? 15);
        $_SESSION['student_layout_settings']['count_size'] = intval($_POST['count_size'] ?? 20);
        $_SESSION['student_layout_settings']['count_x'] = intval($_POST['count_x'] ?? 0);
        $_SESSION['student_layout_settings']['count_y'] = intval($_POST['count_y'] ?? -25);
        $_SESSION['student_layout_settings']['sy_size'] = intval($_POST['sy_size'] ?? 11);
        $_SESSION['student_layout_settings']['sy_x'] = intval($_POST['sy_x'] ?? 0);
        $_SESSION['student_layout_settings']['sy_y'] = intval($_POST['sy_y'] ?? 58);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }   

    // HANDLE: ADD STUDENT PERMIT
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_permit'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $dept = $conn->real_escape_string($_POST['dept']);
        $plate = $conn->real_escape_string($_POST['plate']);
        $fb_link = $conn->real_escape_string($_POST['fb_link']);
        $sy = $_SESSION['student_layout_settings']['school_year']; 
        $valid_until = $conn->real_escape_string($_POST['valid_until']);

        $insert_sql = "INSERT INTO student_permits (name, department, plate_number, fb_link, permit_number, school_year, valid_until) 
                       VALUES ('$name', '$dept', '$plate', '$fb_link', 0, '$sy', '$valid_until')";
        
        if ($conn->query($insert_sql)) {
            $new_id = $conn->insert_id;
            $conn->query("UPDATE student_permits SET permit_number = $new_id WHERE id = $new_id");

            $_SESSION['student_print_queue'][] = [
                'name' => strtoupper($name),
                'dept' => strtoupper($dept),
                'plate' => strtoupper($plate),
                'permit_no' => $new_id, 
                'qr_data' => $fb_link ? $fb_link : "NoData",
                'sy' => $sy,
                'valid_until' => $valid_until,
                'cw' => $_SESSION['student_layout_settings']['card_w'], 
                'ch' => $_SESSION['student_layout_settings']['card_h'],
                'ns' => $_SESSION['student_layout_settings']['name_size'], 
                'nx' => $_SESSION['student_layout_settings']['name_x'], 
                'ny' => $_SESSION['student_layout_settings']['name_y'],
                'ds' => $_SESSION['student_layout_settings']['dept_size'], 
                'dx' => $_SESSION['student_layout_settings']['dept_x'], 
                'dy' => $_SESSION['student_layout_settings']['dept_y'],
                'ps' => $_SESSION['student_layout_settings']['plate_size'], 
                'px' => $_SESSION['student_layout_settings']['plate_x'], 
                'py' => $_SESSION['student_layout_settings']['plate_y'],
                'vs' => $_SESSION['student_layout_settings']['valid_until_size'], 
                'vx' => $_SESSION['student_layout_settings']['valid_until_x'], 
                'vy' => $_SESSION['student_layout_settings']['valid_until_y'],
                'qs' => $_SESSION['student_layout_settings']['qr_size'], 
                'qx' => $_SESSION['student_layout_settings']['qr_x'], 
                'qy' => $_SESSION['student_layout_settings']['qr_y'],
                'cs' => $_SESSION['student_layout_settings']['count_size'], 
                'cx' => $_SESSION['student_layout_settings']['count_x'], 
                'cy' => $_SESSION['student_layout_settings']['count_y'],
                'ss' => $_SESSION['student_layout_settings']['sy_size'], 
                'sx' => $_SESSION['student_layout_settings']['sy_x'], 
                'sy_pos' => $_SESSION['student_layout_settings']['sy_y']
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    // HANDLE: UPDATE STUDENT PERMIT
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permit'])) {
        $permit_id = intval($_POST['permit_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $dept = $conn->real_escape_string($_POST['dept']);
        $plate = $conn->real_escape_string($_POST['plate']);
        $fb_link = $conn->real_escape_string($_POST['fb_link']);
        $sy = $conn->real_escape_string($_POST['school_year']);
        $valid_until = $conn->real_escape_string($_POST['valid_until']);

        $update_sql = "UPDATE student_permits SET 
                        name = '$name',
                        department = '$dept',
                        plate_number = '$plate',
                        fb_link = '$fb_link',
                        school_year = '$sy',
                        valid_until = '$valid_until'
                      WHERE id = $permit_id";
        
        if ($conn->query($update_sql)) {
            echo "<script>alert('Student permit updated successfully!'); window.location.href = '" . $_SERVER['PHP_SELF'] . "';</script>";
            exit;
        } else {
            echo "<script>alert('Error updating student permit: " . $conn->error . "');</script>";
        }
    }

    // HANDLE: REPRINT STUDENT PERMIT
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reprint_permit'])) {
        $permit_id = intval($_POST['permit_id']);
        
        $sql = "SELECT * FROM student_permits WHERE id = $permit_id";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $permit = $result->fetch_assoc();
            $_SESSION['student_print_queue'][] = [
                'name' => strtoupper($permit['name']),
                'dept' => strtoupper($permit['department']),
                'plate' => strtoupper($permit['plate_number']),
                'permit_no' => $permit['permit_number'], 
                'qr_data' => $permit['fb_link'] ? $permit['fb_link'] : "NoData",
                'sy' => $permit['school_year'],
                'valid_until' => $permit['valid_until'],
                'cw' => $_SESSION['student_layout_settings']['card_w'], 
                'ch' => $_SESSION['student_layout_settings']['card_h'],
                'ns' => $_SESSION['student_layout_settings']['name_size'], 
                'nx' => $_SESSION['student_layout_settings']['name_x'], 
                'ny' => $_SESSION['student_layout_settings']['name_y'],
                'ds' => $_SESSION['student_layout_settings']['dept_size'], 
                'dx' => $_SESSION['student_layout_settings']['dept_x'], 
                'dy' => $_SESSION['student_layout_settings']['dept_y'],
                'ps' => $_SESSION['student_layout_settings']['plate_size'], 
                'px' => $_SESSION['student_layout_settings']['plate_x'], 
                'py' => $_SESSION['student_layout_settings']['plate_y'],
                'vs' => $_SESSION['student_layout_settings']['valid_until_size'], 
                'vx' => $_SESSION['student_layout_settings']['valid_until_x'], 
                'vy' => $_SESSION['student_layout_settings']['valid_until_y'],
                'qs' => $_SESSION['student_layout_settings']['qr_size'], 
                'qx' => $_SESSION['student_layout_settings']['qr_x'], 
                'qy' => $_SESSION['student_layout_settings']['qr_y'],
                'cs' => $_SESSION['student_layout_settings']['count_size'], 
                'cx' => $_SESSION['student_layout_settings']['count_x'], 
                'cy' => $_SESSION['student_layout_settings']['count_y'],
                'ss' => $_SESSION['student_layout_settings']['sy_size'], 
                'sx' => $_SESSION['student_layout_settings']['sy_x'], 
                'sy_pos' => $_SESSION['student_layout_settings']['sy_y']
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    // HANDLE: DELETE STUDENT PERMIT
    if (isset($_GET['delete_id'])) {
        $delete_id = intval($_GET['delete_id']);
        $delete_sql = "DELETE FROM student_permits WHERE id = $delete_id";
        
        if ($conn->query($delete_sql)) {
            echo "<script>alert('Student permit deleted successfully!'); window.location.href = '" . $_SERVER['PHP_SELF'] . "';</script>";
            exit;
        } else {
            echo "<script>alert('Error deleting student permit: " . $conn->error . "');</script>";
        }
    }

    // HANDLE: CLEAR QUEUE
    if (isset($_POST['clear_queue'])) {
        $_SESSION['student_print_queue'] = [];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // HANDLE: RESET DATABASE
    if (isset($_POST['reset_db'])) {
        $conn->query("TRUNCATE TABLE student_permits");
        $_SESSION['student_print_queue'] = [];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Check if editing existing permit
    $editing_permit = null;
    if (isset($_GET['edit_id'])) {
        $edit_id = intval($_GET['edit_id']);
        $edit_query = $conn->query("SELECT * FROM student_permits WHERE id = $edit_id");
        if ($edit_query->num_rows > 0) {
            $editing_permit = $edit_query->fetch_assoc();
        }
    }

    $res = $conn->query("SELECT MAX(id) as max_id FROM student_permits");
    $row = $res->fetch_assoc();
    $next_display_id = ($row['max_id'] !== null) ? $row['max_id'] + 1 : 1;

    // --- SEARCH LOGIC ---
    $search_query = "";
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = $conn->real_escape_string($_GET['search']);
        // Search Name, Department, or Plate
        $sql = "SELECT * FROM student_permits WHERE name LIKE '%$search%' OR department LIKE '%$search%' OR plate_number LIKE '%$search%' ORDER BY id DESC LIMIT 50";
    } else {
        $sql = "SELECT * FROM student_permits ORDER BY id DESC LIMIT 5";
    }
    $recent_permits = $conn->query($sql);
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Student Permit System</title>
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
                --card-w: 350px;
                --card-h: 240px;
            }

            /* Light Mode Override */
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

            .navbar { background: var(--panel-bg); border-bottom: 1px solid var(--border); padding: 15px 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .btn-theme { background: transparent; border: 1px solid var(--border); color: var(--text-main); }
            
            .main-container { display: flex; gap: 20px; padding: 0 20px; }
            .left-panel, .right-panel, .bottom-panel { background: var(--panel-bg); padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid var(--border); }
            .left-panel { flex: 1; max-width: 450px; display: flex; flex-direction: column;}
            .right-panel { flex: 2; display: flex; flex-direction: column; align-items: center; justify-content: center; }
            .bottom-panel { margin: 20px; }

            .form-control { background-color: var(--input-bg); border: 1px solid var(--border); color: var(--text-main); margin-bottom: 10px; padding: 12px;}
            .form-control:focus { background-color: var(--input-bg); border-color: var(--accent); color: var(--text-main); box-shadow: none; }
            .form-label { font-size: 0.9rem; font-weight: 600; margin-bottom: 5px; opacity: 0.8; }
            
            .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            .panel-title { color: #0d6efd; font-weight: 900; text-transform: uppercase; font-size: 1.1rem; display: flex; align-items: center; gap: 10px;}
            .badge-next { background-color: #0d6efd; color: white; padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 0.8rem; }
            .badge-queue { background-color: #0dcaf0; color: black; padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 0.8rem; }

            .control-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 5px; margin-bottom: 8px; }
            .control-label-sm { font-size: 0.65rem; opacity: 0.7; margin-bottom: 1px; display: block; white-space: nowrap; }

            /* Layout Settings Panel */
            .layout-panel {
                background: var(--panel-bg);
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 15px;
                margin-top: 15px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }

            /* Admin Tools Row */
            .admin-tools-row {
                background: var(--input-bg);
                padding: 10px;
                border-radius: 6px;
                margin-top: 10px;
                border: 1px solid var(--border);
            }

            /* --- PERMIT CARD DESIGN --- */
            .permit-card {
                width: var(--card-w);
                height: var(--card-h);
                background-image: url('background_student.png'); 
                background-size: 100% 100%; 
                background-position: center;
                position: relative;
                box-shadow: 0 0 20px rgba(0,0,0,0.3);
                background-color: white; color: black;
                overflow: hidden;
                flex-shrink: 0; 
            }

            .card-data { position: absolute; z-index: 10; }
            
            /* HEADER LOGOS */
            .logo-header { position: absolute; object-fit: contain; z-index: 20; }
            .logo-left { left: 20px; top: 10px; width: 55px; height: 55px; } 
            .logo-right { right: 20px; top: 10px; width: 55px; height: 55px; }

            /* PHOTO STYLING */
            .photo-img {
                position: absolute;
                top: 51%; left: 6px; transform: translateY(-50%);
                width: 85px; height: 85px;
                object-fit: cover; border: 2px solid #fff;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                z-index: 5; background: #ccc;
            }

            /* NAME & DEPT - CENTERED */
            .text-name { 
                font-weight: 900; text-transform: uppercase; line-height: 1; display: block; color: #000; 
                position: absolute; width: 100%; text-align: center; white-space: nowrap;
            }
            .text-dept { 
                font-weight: 700; text-transform: uppercase; display: block; color: #333; 
                position: absolute; width: 100%; text-align: center; white-space: nowrap;
            }
            
            /* PLATE POSITIONING: Left aligned */
            .plate-info {
                position: absolute; 
                font-weight: 800; 
                color: #000; 
                text-transform: uppercase;
                font-size: 11px; 
                z-index: 15; 
                letter-spacing: 0.5px;
                left: 6px; 
                text-align: left; 
            }

            /* VALID UNTIL POSITIONING: Left aligned under plate */
            .valid-until-info {
                position: absolute; 
                font-weight: 600; 
                color: #ff6600; 
                text-transform: uppercase;
                font-size: 9px; 
                z-index: 15; 
                letter-spacing: 0.5px;
                left: 6px; 
                text-align: left; 
            }

            /* QR AREA */
            .qr-area { position: absolute; bottom: 15px; right: 5px; text-align: center; }
            .qr-img { width: 60px; height: 60px; border: 1px solid #ddd; background: white; }
            
            .control-no { 
                position: absolute; top: -20px; right: 0; width: 100%; text-align: center; 
                font-size: 20px; font-weight: 900; color: #cc0000; 
            }
            .sy-text { 
                font-size: 11px; font-weight: 800; color: #007bff; display: block; margin-top: 2px;
                line-height: 1; position: relative; 
            }

            /* STUDENTS TEXT STYLING */
            .stu-label {
                position: absolute;
                left: 6px; 
                top: 165px; 
                text-align: left;
                z-index: 25;
                font-weight: 900;
                color: #000000; 
                text-transform: uppercase;
                line-height: 1;
            }
            
            .table-custom { color: var(--text-main); }
            .table-custom th { background-color: var(--input-bg); color: var(--accent); border-color: var(--border); }
            .table-custom td { border-color: var(--border); background-color: transparent; color: var(--text-main); }

            /* Reprint button in table */
            .btn-reprint { 
                padding: 2px 8px; 
                font-size: 0.8rem; 
                margin: 0;
            }

            /* Edit and Delete buttons */
            .btn-edit { 
                padding: 2px 8px; 
                font-size: 0.8rem; 
                margin: 0 2px;
            }
            
            .btn-delete { 
                padding: 2px 8px; 
                font-size: 0.8rem; 
                margin: 0 2px;
            }

            #print-area { display: none; }
            @media print {
                body { background: white; margin: 0; }
                .navbar, .main-container, .bottom-panel { display: none; } 
                #print-area { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 10px; }
                .permit-card {
                    box-shadow: none; border: 1px solid #ccc; margin-bottom: 10px; page-break-inside: avoid;
                    -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
                }
            }
        </style>
    </head>
    <body>

    <div class="navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-secondary fw-bold"><i class="fa fa-arrow-left me-2"></i> Back</a>
            <h4 class="m-0 fw-bold text-white">Student Permit</h4>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-warning fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#layoutSettingsPanel" id="layoutToggleBtn">
                <i class="fa fa-cogs me-2"></i> <span id="layoutToggleText">ON</span>
            </button>
            
            <button class="btn btn-theme rounded-circle" onclick="toggleTheme()" id="themeBtn">
                <i class="fa fa-moon"></i>
            </button>
        </div>
    </div>

    <div class="collapse" id="layoutSettingsPanel">
        <div class="layout-panel mx-4">
            <form method="POST" id="layoutForm">
                <div class="admin-tools-row">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-white mb-2">ACADEMIC YEAR</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark text-white border-secondary fw-bold">AY</span>
                                <input type="text" name="school_year" id="in_sy" class="form-control text-center fw-bold" value="<?php echo htmlspecialchars($_SESSION['student_layout_settings']['school_year']); ?>" oninput="updatePreview()">
                            </div>
                        </div>
                        
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="save_layout" class="btn btn-success fw-bold w-100">
                                <i class="fas fa-save me-2"></i> Save Layout
                            </button>
                        </div>
                        
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-info w-100 fw-bold" onclick="resetLayout()">
                                <i class="fa fa-undo me-2"></i> Reset Layout
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-white mb-2">CARD DIMENSIONS</label>
                        <div class="control-grid" style="grid-template-columns: 1fr 1fr;">
                            <div><span class="control-label-sm">Card Width</span><input type="number" name="card_w" id="card_w" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['card_w']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">Card Height</span><input type="number" name="card_h" id="card_h" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['card_h']; ?>" oninput="updatePreview()"></div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-white mb-2">NAME SETTINGS</label>
                        <div class="control-grid">
                            <div><span class="control-label-sm">Font Size</span><input type="number" name="name_size" id="name_size" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['name_size']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">X Position</span><input type="number" name="name_x" id="name_x" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['name_x']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">Y Position</span><input type="number" name="name_y" id="name_y" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['name_y']; ?>" oninput="updatePreview()"></div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-white mb-2">DEPARTMENT SETTINGS</label>
                        <div class="control-grid">
                            <div><span class="control-label-sm">Font Size</span><input type="number" name="dept_size" id="dept_size" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['dept_size']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">X Position</span><input type="number" name="dept_x" id="dept_x" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['dept_x']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">Y Position</span><input type="number" name="dept_y" id="dept_y" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['dept_y']; ?>" oninput="updatePreview()"></div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-white mb-2">PLATE SETTINGS</label>
                        <div class="control-grid">
                            <div><span class="control-label-sm">Font Size</span><input type="number" name="plate_size" id="plate_size" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['plate_size']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">X Position</span><input type="number" name="plate_x" id="plate_x" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['plate_x']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">Top Pos</span><input type="number" name="plate_y" id="plate_y" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['plate_y']; ?>" oninput="updatePreview()"></div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-white mb-2">VALID UNTIL SETTINGS</label>
                        <div class="control-grid">
                            <div><span class="control-label-sm">Font Size</span><input type="number" name="valid_until_size" id="valid_until_size" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['valid_until_size']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">X Position</span><input type="number" name="valid_until_x" id="valid_until_x" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['valid_until_x']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">Top Pos</span><input type="number" name="valid_until_y" id="valid_until_y" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['valid_until_y']; ?>" oninput="updatePreview()"></div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-white mb-2">QR SETTINGS</label>
                        <div class="control-grid">
                            <div><span class="control-label-sm">QR Size</span><input type="number" name="qr_size" id="qr_size" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['qr_size']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">Right Pos</span><input type="number" name="qr_x" id="qr_x" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['qr_x']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">Bottom Pos</span><input type="number" name="qr_y" id="qr_y" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['qr_y']; ?>" oninput="updatePreview()"></div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-white mb-2">AY SETTINGS</label>
                        <div class="control-grid">
                            <div><span class="control-label-sm">Font Size</span><input type="number" name="sy_size" id="sy_size" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['sy_size']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">Right Pos</span><input type="number" name="sy_x" id="sy_x" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['sy_x']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">Top Pos</span><input type="number" name="sy_y" id="sy_y" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['sy_y']; ?>" oninput="updatePreview()"></div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-white mb-2">COUNT SETTINGS</label>
                        <div class="control-grid">
                            <div><span class="control-label-sm">Font Size</span><input type="number" name="count_size" id="count_size" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['count_size']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">Right Pos</span><input type="number" name="count_x" id="count_x" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['count_x']; ?>" oninput="updatePreview()"></div>
                            <div><span class="control-label-sm">Top Pos</span><input type="number" name="count_y" id="count_y" class="form-control form-control-sm" value="<?php echo $_SESSION['student_layout_settings']['count_y']; ?>" oninput="updatePreview()"></div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-4 d-flex align-items-end">
                        <form method="POST" onsubmit="return confirm('WARNING: This will delete ALL student permits and reset counter to 1.');" class="w-100">
                            <button type="submit" name="reset_db" class="btn btn-danger fw-bold w-100">
                                <i class="fas fa-redo me-2"></i> Reset Database
                            </button>
                        </form>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="main-container">
        <div class="left-panel">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fa <?php echo $editing_permit ? 'fa-edit' : 'fa-user-plus'; ?>"></i> 
                    <?php echo $editing_permit ? 'EDIT STUDENT PERMIT ENTRY' : 'NEW STUDENT PERMIT ENTRY'; ?>
                </div>
                <div class="badge-next">NEXT: <?php echo $next_display_id; ?></div>
            </div>

            <form method="POST" action="" id="permitForm">
                <?php if ($editing_permit): ?>
                    <input type="hidden" name="permit_id" value="<?php echo $editing_permit['id']; ?>">
                <?php endif; ?>
                
                <input type="hidden" name="school_year" id="hidden_sy" value="<?php echo htmlspecialchars($_SESSION['student_layout_settings']['school_year']); ?>">
                <input type="hidden" name="card_w" id="hidden_card_w" value="<?php echo $_SESSION['student_layout_settings']['card_w']; ?>">
                <input type="hidden" name="card_h" id="hidden_card_h" value="<?php echo $_SESSION['student_layout_settings']['card_h']; ?>">
                <input type="hidden" name="name_size" id="hidden_name_size" value="<?php echo $_SESSION['student_layout_settings']['name_size']; ?>">
                <input type="hidden" name="name_x" id="hidden_name_x" value="<?php echo $_SESSION['student_layout_settings']['name_x']; ?>">
                <input type="hidden" name="name_y" id="hidden_name_y" value="<?php echo $_SESSION['student_layout_settings']['name_y']; ?>">
                <input type="hidden" name="dept_size" id="hidden_dept_size" value="<?php echo $_SESSION['student_layout_settings']['dept_size']; ?>">
                <input type="hidden" name="dept_x" id="hidden_dept_x" value="<?php echo $_SESSION['student_layout_settings']['dept_x']; ?>">
                <input type="hidden" name="dept_y" id="hidden_dept_y" value="<?php echo $_SESSION['student_layout_settings']['dept_y']; ?>">
                <input type="hidden" name="plate_size" id="hidden_plate_size" value="<?php echo $_SESSION['student_layout_settings']['plate_size']; ?>">
                <input type="hidden" name="plate_x" id="hidden_plate_x" value="<?php echo $_SESSION['student_layout_settings']['plate_x']; ?>">
                <input type="hidden" name="plate_y" id="hidden_plate_y" value="<?php echo $_SESSION['student_layout_settings']['plate_y']; ?>">
                <input type="hidden" name="valid_until_size" id="hidden_valid_until_size" value="<?php echo $_SESSION['student_layout_settings']['valid_until_size']; ?>">
                <input type="hidden" name="valid_until_x" id="hidden_valid_until_x" value="<?php echo $_SESSION['student_layout_settings']['valid_until_x']; ?>">
                <input type="hidden" name="valid_until_y" id="hidden_valid_until_y" value="<?php echo $_SESSION['student_layout_settings']['valid_until_y']; ?>">
                <input type="hidden" name="qr_size" id="hidden_qr_size" value="<?php echo $_SESSION['student_layout_settings']['qr_size']; ?>">
                <input type="hidden" name="qr_x" id="hidden_qr_x" value="<?php echo $_SESSION['student_layout_settings']['qr_x']; ?>">
                <input type="hidden" name="qr_y" id="hidden_qr_y" value="<?php echo $_SESSION['student_layout_settings']['qr_y']; ?>">
                <input type="hidden" name="count_size" id="hidden_count_size" value="<?php echo $_SESSION['student_layout_settings']['count_size']; ?>">
                <input type="hidden" name="count_x" id="hidden_count_x" value="<?php echo $_SESSION['student_layout_settings']['count_x']; ?>">
                <input type="hidden" name="count_y" id="hidden_count_y" value="<?php echo $_SESSION['student_layout_settings']['count_y']; ?>">
                <input type="hidden" name="sy_size" id="hidden_sy_size" value="<?php echo $_SESSION['student_layout_settings']['sy_size']; ?>">
                <input type="hidden" name="sy_x" id="hidden_sy_x" value="<?php echo $_SESSION['student_layout_settings']['sy_x']; ?>">
                <input type="hidden" name="sy_y" id="hidden_sy_y" value="<?php echo $_SESSION['student_layout_settings']['sy_y']; ?>">
                
                <input type="text" name="name" id="in_name" class="form-control" 
                       placeholder="Full Name" required oninput="updatePreview()"
                       value="<?php echo $editing_permit ? htmlspecialchars($editing_permit['name']) : ''; ?>">

                <input type="text" name="dept" id="in_dept" class="form-control" 
                       placeholder="Department / Course & Year" required oninput="updatePreview()"
                       value="<?php echo $editing_permit ? htmlspecialchars($editing_permit['department']) : ''; ?>">

                <input type="text" name="plate" id="in_plate" class="form-control" 
                       placeholder="Plate Number" required oninput="updatePreview()"
                       value="<?php echo $editing_permit ? htmlspecialchars($editing_permit['plate_number']) : ''; ?>">

                <input type="text" name="valid_until" id="in_valid_until" class="form-control" 
                       placeholder="Valid Until (e.g., May 31, 2026)" required oninput="updatePreview()"
                       value="<?php echo $editing_permit ? htmlspecialchars($editing_permit['valid_until']) : ''; ?>">

                <input type="text" name="fb_link" id="in_link" class="form-control" 
                       placeholder="Facebook Link (QR Data)" oninput="updatePreview()"
                       value="<?php echo $editing_permit ? htmlspecialchars($editing_permit['fb_link']) : ''; ?>">

                <input type="text" name="school_year" id="in_sy" class="form-control" 
                       placeholder="School Year (e.g., 2025-2026)" required oninput="updatePreview()"
                       value="<?php echo $editing_permit ? htmlspecialchars($editing_permit['school_year']) : htmlspecialchars($_SESSION['student_layout_settings']['school_year']); ?>">

                <?php if ($editing_permit): ?>
                    <div class="d-flex gap-2">
                        <button type="submit" name="update_permit" class="btn btn-primary w-100 fw-bold py-3">
                            <i class="fa fa-save me-2"></i> Update Student Permit
                        </button>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary fw-bold py-3">
                            <i class="fa fa-times me-2"></i> Cancel
                        </a>
                    </div>
                <?php else: ?>
                    <button type="submit" name="add_permit" class="btn btn-success w-100 fw-bold py-3">
                        <i class="fa fa-plus-circle me-2"></i> Add to Print
                    </button>
                <?php endif; ?>
            </form>

            <hr class="border-secondary my-4">

            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small opacity-75">Queue Management</span>
                <span class="badge-queue">QUEUE: <?php echo count($_SESSION['student_print_queue']); ?> PERMITS</span>
            </div>
            
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-primary flex-grow-1 fw-bold" <?php echo count($_SESSION['student_print_queue']) == 0 ? 'disabled' : ''; ?>>
                    <i class="fa fa-print me-2"></i> Print Queue
                </button>
                <?php if(count($_SESSION['student_print_queue']) > 0): ?>
                    <form method="POST"><button type="submit" name="clear_queue" class="btn btn-outline-danger fw-bold"><i class="fa fa-trash"></i></button></form>
                <?php endif; ?>
            </div>
        
        </div>

        <div class="right-panel">
            <div class="panel-header w-100 border-bottom pb-3 mb-4" style="border-color: var(--border)!important;">
                <div class="panel-title"><i class="fa fa-eye"></i> STUDENT PERMIT PREVIEW</div>
            </div>
            
            <div class="permit-card" id="preview-card">
                
                <img src="HCC.png" class="logo-header logo-left" alt="HCC Logo">
                <img src="background.png" class="logo-header logo-right" alt="Shield Logo">

                <div class="stu-label">
                    <span style="font-size: 14px; color: yellow;">S</span><span style="font-size: 10px;">TUDENT LICENSE</span>
                </div>

                <img src="https://placehold.co/100x100/e0e0e0/888888?text=PHOTO" class="photo-img" alt="Student Photo">

                <span class="text-name" id="out_name">
                    <?php echo $editing_permit ? strtoupper(htmlspecialchars($editing_permit['name'])) : 'NAME'; ?>
                </span>
                <span class="text-dept" id="out_dept">
                    <?php echo $editing_permit ? strtoupper(htmlspecialchars($editing_permit['department'])) : 'DEPARTMENT'; ?>
                </span>
                
                <div class="plate-info" id="plate_container">
                    PLATE#: <span id="out_plate">
                        <?php echo $editing_permit ? strtoupper(htmlspecialchars($editing_permit['plate_number'])) : '-------'; ?>
                    </span>
                </div>

                <div class="valid-until-info" id="valid_until_container">
                    Valid Until: <span id="out_valid_until">
                        <?php echo $editing_permit ? htmlspecialchars($editing_permit['valid_until']) : 'Enter Date'; ?>
                    </span>
                </div>

                <div class="qr-area" id="qr_container">
                    <div class="control-no" id="out_ctrl">
                        <?php echo $editing_permit ? $editing_permit['permit_number'] : $next_display_id; ?>
                    </div>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo $editing_permit ? urlencode($editing_permit['fb_link']) : 'Empty'; ?>" class="qr-img" id="out_qr">
                    <span class="sy-text" id="out_sy">
                        <?php echo $editing_permit ? htmlspecialchars($editing_permit['school_year']) : htmlspecialchars($_SESSION['student_layout_settings']['school_year']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold m-0"><i class="fa fa-database me-2"></i> RECENT STUDENT PERMIT ENTRIES</h5>
            <span class="badge bg-dark">Total: <?php echo $conn->query("SELECT COUNT(*) as total FROM student_permits")->fetch_assoc()['total']; ?></span>
        </div>
        
        <form method="GET" class="mb-3 d-flex gap-2">
            <input type="text" name="search" class="form-control mb-0" placeholder="Search by Name, Dept, or Plate..." 
                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                   style="max-width: 300px;">
            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i></button>
            <?php if(isset($_GET['search'])): ?>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary"><i class="fa fa-times"></i></a>
            <?php endif; ?>
        </form>

        <div class="table-responsive">
            <table class="table table-custom table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Permit #</th>
                        <th>Name</th>
                        <th>Department/Course</th>
                        <th>Plate #</th>
                        <th>AY</th>
                        <th>Valid Until</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_permits->num_rows > 0): ?>
                        <?php while($row = $recent_permits->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><span class="badge bg-warning text-dark"><?php echo $row['permit_number']; ?></span></td>
                            <td><?php echo strtoupper($row['name']); ?></td>
                            <td><?php echo strtoupper($row['department']); ?></td>
                            <td><?php echo strtoupper($row['plate_number']); ?></td>
                            <td><?php echo strtoupper($row['school_year']); ?></td>
                            <td><?php echo htmlspecialchars($row['valid_until']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div class="d-flex">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="permit_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="reprint_permit" class="btn btn-sm btn-info btn-reprint" title="Reprint this permit">
                                            <i class="fa fa-print"></i>
                                        </button>
                                    </form>
                                    <a href="?edit_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary btn-edit" title="Edit this permit">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger btn-delete" 
                                       onclick="return confirm('Are you sure you want to delete this student permit?')" title="Delete this permit">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center opacity-50">No student records.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="print-area">
        <?php 
        foreach($_SESSION['student_print_queue'] as $item): 
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($item['qr_data']);
            
            // Load Settings
            $cw = $item['cw']; $ch = $item['ch'];
            $ns = $item['ns']; $nx = $item['nx']; $ny = $item['ny'];
            $ds = $item['ds']; $dx = $item['dx']; $dy = $item['dy'];
            $ps = $item['ps']; $px = $item['px']; $py = $item['py'];
            $vs = $item['vs']; $vx = $item['vx']; $vy = $item['vy'];
            $qs = $item['qs']; $qx = $item['qx']; $qy = $item['qy'];
            $cs = $item['cs']; $cx = $item['cx']; $cy = $item['cy'];
            // Year Settings
            $ss = $item['ss']; $sx = $item['sx']; $sy_pos = $item['sy_pos'];
        ?>
        <div class="permit-card" style="width: <?php echo $cw; ?>px; height: <?php echo $ch; ?>px;">
            <img src="HCC.png" class="logo-header logo-left" alt="HCC Logo">
            <img src="background.png" class="logo-header logo-right" alt="Shield Logo">

            <div class="stu-label">
                <span style="font-size: 14px; color: yellow;">S</span><span style="font-size: 10px;">TUDENT LICENSE</span>
            </div>

            <img src="https://placehold.co/100x100/e0e0e0/888888?text=PHOTO" class="photo-img">
            <span class="text-name" style="font-size: <?php echo $ns; ?>px; left: <?php echo $nx; ?>px; top: <?php echo $ny; ?>px; width: 100%; text-align: center;"><?php echo $item['name']; ?></span>
            <span class="text-dept" style="font-size: <?php echo $ds; ?>px; left: <?php echo $dx; ?>px; top: <?php echo $dy; ?>px; width: 100%; text-align: center;"><?php echo $item['dept']; ?></span>
            
            <div class="plate-info" style="font-size: <?php echo $ps; ?>px; top: <?php echo $py; ?>px; left: <?php echo $px; ?>px;">PLATE#: <span><?php echo $item['plate']; ?></span></div>
            
            <div class="valid-until-info" style="font-size: <?php echo $vs; ?>px; top: <?php echo $vy; ?>px; left: <?php echo $vx; ?>px;">Valid Until: <span><?php echo htmlspecialchars($item['valid_until']); ?></span></div>
            
            <div class="qr-area" style="right: <?php echo $qx; ?>px; bottom: <?php echo $qy; ?>px;">
                <div class="control-no" style="font-size: <?php echo $cs; ?>px; right: <?php echo $cx; ?>px; top: <?php echo $cy; ?>px;"><?php echo $item['permit_no']; ?></div>
                <img src="<?php echo $qr_url; ?>" class="qr-img" style="width: <?php echo $qs; ?>px; height: <?php echo $qs; ?>px;">
                <span class="sy-text" style="font-size: <?php echo $ss; ?>px; position:absolute; right: <?php echo $sx; ?>px; top: <?php echo $sy_pos; ?>px; width:100%; white-space:nowrap;"><?php echo $item['sy']; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            const isLight = document.body.classList.contains('light-mode');
            document.getElementById('themeBtn').innerHTML = isLight ? '<i class="fa fa-sun"></i>' : '<i class="fa fa-moon"></i>';
            
            // Sync with Dashboard logic (appTheme)
            const themeValue = isLight ? 'light' : 'dark';
            localStorage.setItem('appTheme', themeValue);
            document.cookie = "theme=" + themeValue + "; path=/; max-age=31536000";
        }
        
        // Check saved theme
        const savedTheme = localStorage.getItem('appTheme') || 'light';
        if (savedTheme === 'light') {
            document.body.classList.add('light-mode');
            document.getElementById('themeBtn').innerHTML = '<i class="fa fa-sun"></i>';
        } else {
            document.body.classList.remove('light-mode');
            document.getElementById('themeBtn').innerHTML = '<i class="fa fa-moon"></i>';
        }

        // Layout Toggle State Management
        let layoutVisible = false;
        
        // Initialize layout panel state from localStorage
        const savedLayoutState = localStorage.getItem('layoutVisible');
        if (savedLayoutState === 'true') {
            layoutVisible = true;
            document.getElementById('layoutSettingsPanel').classList.add('show');
            document.getElementById('layoutToggleText').innerText = 'OFF';
            document.getElementById('layoutToggleBtn').classList.add('btn-success');
            document.getElementById('layoutToggleBtn').classList.remove('btn-warning');
        } else {
            layoutVisible = false;
            document.getElementById('layoutSettingsPanel').classList.remove('show');
            document.getElementById('layoutToggleText').innerText = 'ON';
            document.getElementById('layoutToggleBtn').classList.remove('btn-success');
            document.getElementById('layoutToggleBtn').classList.add('btn-warning');
        }

        function syncHiddenFields() {
            document.getElementById('hidden_sy').value = document.getElementById('in_sy').value;
            document.getElementById('hidden_card_w').value = document.getElementById('card_w').value;
            document.getElementById('hidden_card_h').value = document.getElementById('card_h').value;
            document.getElementById('hidden_name_size').value = document.getElementById('name_size').value;
            document.getElementById('hidden_name_x').value = document.getElementById('name_x').value;
            document.getElementById('hidden_name_y').value = document.getElementById('name_y').value;
            document.getElementById('hidden_dept_size').value = document.getElementById('dept_size').value;
            document.getElementById('hidden_dept_x').value = document.getElementById('dept_x').value;
            document.getElementById('hidden_dept_y').value = document.getElementById('dept_y').value;
            document.getElementById('hidden_plate_size').value = document.getElementById('plate_size').value;
            document.getElementById('hidden_plate_x').value = document.getElementById('plate_x').value;
            document.getElementById('hidden_plate_y').value = document.getElementById('plate_y').value;
            document.getElementById('hidden_valid_until_size').value = document.getElementById('valid_until_size').value;
            document.getElementById('hidden_valid_until_x').value = document.getElementById('valid_until_x').value;
            document.getElementById('hidden_valid_until_y').value = document.getElementById('valid_until_y').value;
            document.getElementById('hidden_qr_size').value = document.getElementById('qr_size').value;
            document.getElementById('hidden_qr_x').value = document.getElementById('qr_x').value;
            document.getElementById('hidden_qr_y').value = document.getElementById('qr_y').value;
            document.getElementById('hidden_count_size').value = document.getElementById('count_size').value;
            document.getElementById('hidden_count_x').value = document.getElementById('count_x').value;
            document.getElementById('hidden_count_y').value = document.getElementById('count_y').value;
            document.getElementById('hidden_sy_size').value = document.getElementById('sy_size').value;
            document.getElementById('hidden_sy_x').value = document.getElementById('sy_x').value;
            document.getElementById('hidden_sy_y').value = document.getElementById('sy_y').value;
        }

        function updatePreview() {
            // Sync hidden fields
            syncHiddenFields();
            
            // --- 1. GET DATA ---
            let displayName = document.getElementById('in_name').value.toUpperCase() || "NAME";
            let displayAy = document.getElementById('in_sy').value || "Enter AY";
            let validUntil = document.getElementById('in_valid_until').value || "Enter Date";
            
            // Apply Text
            document.getElementById('out_name').innerText = displayName;
            document.getElementById('out_dept').innerText = document.getElementById('in_dept').value.toUpperCase() || "DEPARTMENT";
            document.getElementById('out_plate').innerText = document.getElementById('in_plate').value.toUpperCase() || "-------";
            document.getElementById('out_valid_until').innerText = validUntil;
            document.getElementById('out_sy').innerText = displayAy;
            
            let link = document.getElementById('in_link').value;
            let qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" + (link ? encodeURIComponent(link) : "Empty");
            document.getElementById('out_qr').src = qrUrl;

            // Update count if not editing
            <?php if (!$editing_permit): ?>
            document.getElementById('out_ctrl').innerText = <?php echo $next_display_id; ?>;
            <?php endif; ?>

            // --- 3. LAYOUT UPDATES ---
            let cW = document.getElementById('card_w').value;
            let cH = document.getElementById('card_h').value;
            let card = document.getElementById('preview-card');
            card.style.width = cW + 'px';
            card.style.height = cH + 'px';

            let elName = document.getElementById('out_name');
            elName.style.fontSize = document.getElementById('name_size').value + 'px';
            elName.style.left = document.getElementById('name_x').value + 'px';
            elName.style.top = document.getElementById('name_y').value + 'px';
            elName.style.width = cW + 'px';

            let elDept = document.getElementById('out_dept');
            elDept.style.fontSize = document.getElementById('dept_size').value + 'px';
            elDept.style.left = document.getElementById('dept_x').value + 'px';
            elDept.style.top = document.getElementById('dept_y').value + 'px';
            elDept.style.width = cW + 'px';

            let elPlate = document.getElementById('plate_container');
            elPlate.style.fontSize = document.getElementById('plate_size').value + 'px';
            elPlate.style.left = document.getElementById('plate_x').value + 'px';
            elPlate.style.top = document.getElementById('plate_y').value + 'px';

            let elValidUntil = document.getElementById('valid_until_container');
            elValidUntil.style.fontSize = document.getElementById('valid_until_size').value + 'px';
            elValidUntil.style.left = document.getElementById('valid_until_x').value + 'px';
            elValidUntil.style.top = document.getElementById('valid_until_y').value + 'px';

            let qrBox = document.getElementById('qr_container');
            let qrImg = document.getElementById('out_qr');
            let qrSize = document.getElementById('qr_size').value;
            qrImg.style.width = qrSize + 'px';
            qrImg.style.height = qrSize + 'px';
            qrBox.style.right = document.getElementById('qr_x').value + 'px';
            qrBox.style.bottom = document.getElementById('qr_y').value + 'px';

            let elAy = document.getElementById('out_sy');
            elAy.style.position = 'absolute'; 
            elAy.style.fontSize = document.getElementById('sy_size').value + 'px';
            elAy.style.right = document.getElementById('sy_x').value + 'px';
            elAy.style.top = document.getElementById('sy_y').value + 'px';
            elAy.style.width = '100%'; 

            let countEl = document.getElementById('out_ctrl');
            countEl.style.fontSize = document.getElementById('count_size').value + 'px';
            countEl.style.right = document.getElementById('count_x').value + 'px';
            countEl.style.top = document.getElementById('count_y').value + 'px';
        }

        function resetLayout() {
            document.getElementById('card_w').value = 350;
            document.getElementById('card_h').value = 240;
            document.getElementById('name_size').value = 12;
            document.getElementById('name_x').value = 11;
            document.getElementById('name_y').value = 110;
            document.getElementById('dept_size').value = 11;
            document.getElementById('dept_x').value = 0;
            document.getElementById('dept_y').value = 129;
            document.getElementById('plate_size').value = 11;
            document.getElementById('plate_x').value = 6;
            document.getElementById('plate_y').value = 180;
            document.getElementById('valid_until_size').value = 9;
            document.getElementById('valid_until_x').value = 8;
            document.getElementById('valid_until_y').value = 197;
            document.getElementById('qr_size').value = 60;
            document.getElementById('qr_x').value = 5;
            document.getElementById('qr_y').value = 15;
            document.getElementById('count_size').value = 20;
            document.getElementById('count_x').value = 0;
            document.getElementById('count_y').value = -25;
            document.getElementById('sy_size').value = 11;
            document.getElementById('sy_x').value = 0;
            document.getElementById('sy_y').value = 58;
            document.getElementById('in_sy').value = "Enter AY";
            
            syncHiddenFields();
            updatePreview();
            
            alert('Layout and AY settings have been reset to default values!');
        }

        document.getElementById('layoutToggleBtn').addEventListener('click', function() {
            layoutVisible = !layoutVisible;
            
            if (layoutVisible) {
                document.getElementById('layoutToggleText').innerText = 'OFF';
                this.classList.remove('btn-warning');
                this.classList.add('btn-success');
            } else {
                document.getElementById('layoutToggleText').innerText = 'ON';
                this.classList.remove('btn-success');
                this.classList.add('btn-warning');
            }
            localStorage.setItem('layoutVisible', layoutVisible);
        });

        document.addEventListener('DOMContentLoaded', function() {
            updatePreview();
            syncHiddenFields();
            document.getElementById('in_sy').addEventListener('input', function() {
                document.getElementById('hidden_sy').value = this.value;
            });
            <?php if ($editing_permit): ?>
            updatePreview();
            <?php endif; ?>
        });
    </script>

    </body>
    </html>