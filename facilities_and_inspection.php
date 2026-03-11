<?php
    // --- facilities_and_inspection.php ---
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

    // 1. Create Connection (Connect ONLY to server first, not the DB)
    $conn = new mysqli($servername, $username, $password);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // 2. Create Database IF NOT EXISTS (Robust Check)
    $db_create_sql = "CREATE DATABASE IF NOT EXISTS $dbname";
    if ($conn->query($db_create_sql) === FALSE) {
        die("Error creating database '$dbname': " . $conn->error);
    }

    // 3. Select the Database
    if (!$conn->select_db($dbname)) {
        die("Error selecting database '$dbname': " . $conn->error);
    }

    // Table Setup
    $table_sql = "CREATE TABLE IF NOT EXISTS facility_inspections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        location VARCHAR(255) NOT NULL,
        inspection_date DATE NOT NULL,
        inspection_time TIME NOT NULL,
        description TEXT NOT NULL,
        image_paths TEXT DEFAULT NULL, 
        image_size TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($table_sql) === FALSE) {
        die("Error creating table: " . $conn->error);
    }

    // Auto-Repair Columns
    $required_columns = [
        'title' => 'VARCHAR(255) NOT NULL',
        'location' => 'VARCHAR(255) NOT NULL',
        'inspection_date' => 'DATE NOT NULL',
        'inspection_time' => 'TIME NOT NULL',
        'description' => 'TEXT NOT NULL',
        'image_paths' => 'TEXT DEFAULT NULL',
        'image_size' => 'TEXT DEFAULT NULL'
    ];

    foreach ($required_columns as $col => $def) {
        $check = $conn->query("SHOW COLUMNS FROM facility_inspections LIKE '$col'");
        if ($check && $check->num_rows == 0) {
            $conn->query("ALTER TABLE facility_inspections ADD $col $def");
        } else if ($check && $col === 'image_size') {
            $row = $check->fetch_assoc();
            if (strpos(strtolower($row['Type']), 'int') !== false) {
                // Upgrade existing INT column to TEXT for JSON storage
                $conn->query("ALTER TABLE facility_inspections CHANGE $col $col TEXT DEFAULT NULL");
            }
        }
    }

    // Session Queue
    if (!isset($_SESSION['facility_print_queue'])) {
        $_SESSION['facility_print_queue'] = [];
    }

    // Upload Directory
    $upload_dir = "uploads/facilities/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // --- 2. FORM HANDLERS ---
    $success_msg = "";
    $error_msg = "";

    // HANDLE: ADD REQUEST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
        $title = $conn->real_escape_string($_POST['title']);
        $loc = $conn->real_escape_string($_POST['location']);
        $date = $conn->real_escape_string($_POST['inspection_date']);
        $time = $conn->real_escape_string($_POST['inspection_time']);
        $desc = $conn->real_escape_string($_POST['description']);
        $img_size = isset($_POST['image_size']) && !empty($_POST['image_size']) ? $conn->real_escape_string($_POST['image_size']) : '[]';

        $image_paths_json = null;
        $uploaded_files = [];
        $upload_errors = [];

        // Handle Multiple Image Uploads
        if (isset($_FILES['inspection_images']) && !empty($_FILES['inspection_images']['name'][0])) {
            $total_files = count($_FILES['inspection_images']['name']);
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES['inspection_images']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['inspection_images']['tmp_name'][$i];
                    $file_name = basename($_FILES['inspection_images']['name'][$i]);
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if (in_array($file_ext, $allowed_exts)) {
                        $new_file_name = uniqid('fac_') . '_' . $i . '.' . $file_ext;
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

            $stmt = $conn->prepare("INSERT INTO facility_inspections (title, location, inspection_date, inspection_time, description, image_paths, image_size) VALUES (?, ?, ?, ?, ?, ?, ?)");

            if ($stmt === false) {
                $error_msg = "<strong>Database Error:</strong> " . $conn->error;
            } else {
                $stmt->bind_param("sssssss", $title, $loc, $date, $time, $desc, $image_paths_json, $img_size);

                if ($stmt->execute()) {
                    $_SESSION['facility_print_queue'][] = [
                        'title' => $title, // Kept mixed case
                        'loc' => $loc,     // Kept mixed case
                        'date' => $date,
                        'time' => $time,
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

    // HANDLE: DELETE LOG
    if (isset($_GET['delete_id'])) {
        $del_id = intval($_GET['delete_id']);
        $res = $conn->query("SELECT image_paths FROM facility_inspections WHERE id = $del_id");
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
        $conn->query("DELETE FROM facility_inspections WHERE id = $del_id");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // HANDLE: CLEAR QUEUE
    if (isset($_POST['clear_queue'])) {
        $_SESSION['facility_print_queue'] = [];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if (isset($_GET['success']))
        $success_msg = "Inspection recorded successfully!";
    if (isset($_GET['error']))
        $error_msg = "An error occurred.";

    // --- SEARCH LOGIC ---
    $search_term = "";
    $where_clause = "";
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_term = $conn->real_escape_string($_GET['search']);
        $where_clause = "WHERE title LIKE '%$search_term%' OR location LIKE '%$search_term%'";
    }

    $recent_reports = $conn->query("SELECT * FROM facility_inspections $where_clause ORDER BY id DESC LIMIT 10");
    $total_count = $conn->query("SELECT COUNT(*) as total FROM facility_inspections")->fetch_assoc()['total'];
    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Facilities & Equipment Inspection</title>
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

            .btn-primary { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; }
            .btn-secondary { background: linear-gradient(135deg, #858796 0%, #60616f 100%); color: white; }
            .btn-success { background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); color: white; }
            .btn-danger { background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%); color: white; }
            .btn-info { background: linear-gradient(135deg, #36b9cc 0%, #258391 100%); color: white; }
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
                color: var(--text-main);
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

            /* --- PAPER FORM DESIGN (SCREEN PREVIEW) --- */
            .hcc-form {
                width: 8.5in;
                height: 14in;
                background: white;
                color: black;
                padding: 0; 
                margin: 0 auto;
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

            .form-inner {
                padding: 0.75in 0.25in 0.25in 0.25in;
                display: flex;
                flex-direction: column;
                flex-grow: 1;
                height: 100%;
                box-sizing: border-box;
            }

            /* --- NEW HEADER LAYOUT (Fading Bar Integration) --- */
            .new-header-wrapper {
                position: relative;
                width: calc(100% + 0.5in);
                margin-left: -0.25in;
                margin-right: -0.25in;
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
                    /* 1. Dark gold lower line (5px) - stays solid almost completely to the edge (95%) */
                    linear-gradient(to right, #c99800 0%, #c99800 95%, #ffffff 100%) left bottom / 100% 5px no-repeat,
                    /* 2. Main yellow bar - moved fade to the left (30% -> 55%) so it doesn't hit the text */
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
                width: 165px;
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
                /* FADE LOGIC APPLIED HERE */
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
                color: #000000;
                font-size: 10pt;
                line-height: 1.5;
                font-family: Arial, sans-serif;
                margin-left: 220px;
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
                margin-bottom: 0; 
                table-layout: fixed;
            }

            .form-table td {
                border: 2px solid black;
                padding: 6px 8px;
                vertical-align: middle;
                font-size: 10pt;
                color: black;
            }

            .label-cell {
                font-weight: bold;
                width: 38%; 
                background-color: white;
                text-transform: uppercase;
                color: black;
            }

            /* --- STABLE LAYOUT FOR DESCRIPTION --- */
            .desc-section {
                margin-top: 0; 
                color: black;
                display: flex;
                flex-direction: column;
                flex-grow: 1; 
            }

            .desc-box {
                border: 2px solid black;
                border-top: none; 
                margin-top: 0; 
                width: 100%;
                padding: 10px; 
                line-height: 1.5; 
                font-size: 11pt;
                font-family: Arial, sans-serif;
                white-space: pre-wrap;
                overflow-wrap: break-word;
                word-break: break-all;
                flex-grow: 1; 
                min-height: 450px;
                display: flex;
                flex-direction: column;
                box-sizing: border-box;
            }

            .desc-text {
                white-space: pre-wrap;
                display: block;
                width: 100%;
            }

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

            /* --- CUSTOM DRAG-TO-RESIZE (ALL 4 SIDES) --- */
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

            /* --- INTERACTIVE DRAG HANDLES --- */
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
                height: 50px;
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

            /* --- PRINT MEDIA QUERIES (PERFECT 0 MARGIN INTEGRATION) --- */
            @page {
                size: auto;
                margin: 0; /* Forces 0 margin to remove default browser headers/footers */
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
                }

                /* Unscales the form and fills the page */
                #print-area .hcc-form,
                .print-blank #print-blank-area .hcc-form {
                    transform: none !important;
                    box-shadow: none !important;
                    margin: 0 auto !important;
                    width: 100% !important;
                    height: 100% !important;
                    min-height: 100vh !important;
                    padding: 0 !important; 
                    page-break-after: always !important; 
                }

                /* Toggling Logic for Print Views */
                .print-blank #print-area {
                    display: none !important;
                }

                .print-blank #print-blank-area {
                    display: block !important;
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                }

                /* Fix Header Margins and Logo Position for Print */
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
                    page-break-inside: avoid !important;
                }

                /* Disable resize elements on print */
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
                color: var(--text-main); 
                border-color: var(--border);
            }

            .table-custom td {
                color: #ffffff !important;
                border-color: var(--border);
            }

            body.light-mode .table-custom td {
                color: #212529 !important;
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
                <h4 class="m-0 fw-bold text-white">Facilities & Equipment Inspection</h4>
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
                        <i class="fa fa-clipboard-check"></i> INSPECTION DETAILS
                    </div>
                    <div class="badge-queue">QUEUE: <?php echo count($_SESSION['facility_print_queue']); ?></div>
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
                    <input type="text" name="title" id="in_title" class="form-control" placeholder="Facility / Item Name"
                        required oninput="updateTextPreview()">
                    <input type="text" name="location" id="in_loc" class="form-control" placeholder="Location" required
                        oninput="updateTextPreview()">

                    <div class="row">
                        <div class="col-6">
                            <label class="small mb-1" style="color: var(--text-main);">Inspection Date</label>
                            <input type="date" name="inspection_date" id="in_date" class="form-control" required
                                oninput="updateTextPreview()">
                        </div>
                        <div class="col-6">
                            <label class="small mb-1" style="color: var(--text-main);">Inspection Time</label>
                            <div class="input-group mb-2">
                                <input type="time" name="inspection_time" id="in_time" class="form-control mb-0" required
                                    oninput="updateTextPreview()"
                                    style="border-top-right-radius: 0; border-bottom-right-radius: 0;">
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="document.getElementById('in_time').value=''; updateTextPreview();">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <textarea name="description" id="in_desc" class="form-control" rows="8"
                        placeholder="Description of Findings..." required oninput="updateTextPreview()"></textarea>

                    <input type="hidden" name="image_size" id="in_img_size" value="[]">

                    <div class="mb-3 mt-3">
                        <label class="small mb-2 d-block" style="color: var(--text-main);">
                            <i class="fa fa-images me-1"></i> Attach Images (Optional, JPG/PNG/GIF)
                            <br>
                            <span class="fw-bold" style="font-size: 11px; color: var(--text-main);"><i class="fa fa-lightbulb"></i> Tip: Drag any edge or corner of the image in the Preview Panel to resize it.</span>
                        </label>

                        <input type="file" name="inspection_images[]" id="in_images" class="d-none"
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
                        <button type="button" onclick="resetForm()" class="btn btn-warning fw-bold py-3 mt-2"
                            title="Clear form to start new">
                            <i class="fa fa-rotate-right"></i>
                        </button>
                    </div>
                </form>

                <hr class="border-secondary my-4">

                <div class="row g-2">
                    <div class="col-6">
                        <button onclick="printQueue()" class="btn btn-success w-100 fw-bold h-100" <?php echo count($_SESSION['facility_print_queue']) == 0 ? 'disabled' : ''; ?>>
                            <i class="fa fa-print me-2"></i> Print Queue
                            (<?php echo count($_SESSION['facility_print_queue']); ?>)
                        </button>
                    </div>
                    <div class="col-6">
                        <button onclick="printBlank()" class="btn btn-secondary w-100 fw-bold text-white h-100">
                            <i class="fa fa-file me-2"></i> Blank Form
                        </button>
                    </div>
                    <?php if (count($_SESSION['facility_print_queue']) > 0): ?>
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
                    <div class="form-inner">
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
                                <h3>FACILITIES AND EQUIPMENT INSPECTION REPORT</h3>
                            </div>
                        </div>

                        <table class="form-table">
                            <tr>
                                <td class="label-cell">NAME OF FACILITY/EQUIPMENT/ITEM</td>
                                <td class="input-cell" id="out_title"></td>
                            </tr>
                            <tr>
                                <td class="label-cell">LOCATION</td>
                                <td class="input-cell" id="out_loc"></td>
                            </tr>
                            <tr>
                                <td class="label-cell">DATE OF INSPECTION</td>
                                <td class="input-cell" id="out_date"></td>
                            </tr>
                            <tr>
                                <td class="label-cell">TIME OF INSPECTION</td>
                                <td class="input-cell" id="out_time"></td>
                            </tr>
                        </table>

                        <div class="desc-section">
                            <div class="desc-box">
                                <strong style="margin-bottom: 8px; display: block;">DESCRIPTION OF FINDINGS / INSPECTION:</strong>
                                <span id="out_desc" class="desc-text"></span>
                                <div class="image-section" id="out_images_container"></div>
                            </div>
                        </div>

                        <div class="form-footer">
                            <div style="font-size: 8pt; font-weight: bold; font-style: italic; margin-top: 5px;">Copy furnished
                                to the office of:</div>
                            <table class="copy-furnished-table">
                                <tr>
                                    <td>Principal/Dean</td>
                                    <td style="text-align: center; vertical-align: bottom; padding-bottom: 5px;">
                                        <div
                                            style="border-bottom: 1px solid black; width: 90%; margin: 0 auto 3px auto; height: 15px;">
                                        </div>
                                        <div style="font-weight: bold; font-size: 8pt; text-transform: uppercase;">JORGE C.
                                            LUMBANG, LPT</div>
                                        <div style="font-size: 6.5pt; line-height: 1.1;">HRD Officer / Acting Manager
                                            for<br>Administrative Office</div>
                                    </td>
                                    <td>Others (Specify)</td>
                                </tr>
                            </table>

                            <div class="officer-section">
                                <div class="officer-title" style="margin-bottom: 25px;">Officer in charge of the inspection:
                                </div>
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
                                <div style="display: flex; justify-content: space-between; width: 100%;">
                                    <div style="text-align: center;">
                                        <div style="border-top: 1px solid black; width: 250px; padding-top: 5px;">
                                            <div class="officer-name-line">PAUL JEFFREY T. LANSANGAN, SO3</div>
                                            <div class="officer-position">CHIEF, Safety and Protection</div>
                                        </div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="border-top: 1px solid black; width: 250px; padding-top: 5px;">
                                            <div class="officer-name-line">EDWIN GUEVARRA</div>
                                            <div class="officer-position">Supervisor</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="print-area">
            <?php if (count($_SESSION['facility_print_queue']) > 0):
                foreach ($_SESSION['facility_print_queue'] as $p):
                    $t = strtotime($p['time']);
                    $print_time = date("h:i A", $t); 
                    
                    // Parse the array of sizes
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
                        <div class="form-inner">
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
                                <img src="background-logo-hcc.jpg" alt="SAPD Logo" class="sapd-logo">
                                <div class="division-title">
                                    <h2>SAFETY AND PROTECTION DIVISION</h2>
                                    <h3>FACILITIES AND EQUIPMENT INSPECTION REPORT</h3>
                                </div>
                            </div>

                            <table class="form-table">
                                <tr>
                                    <td class="label-cell">NAME OF FACILITY/EQUIPMENT/ITEM</td>
                                    <td class="input-cell"><?php echo htmlspecialchars($p['title']); ?></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">LOCATION</td>
                                    <td class="input-cell"><?php echo htmlspecialchars($p['loc']); ?></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">DATE OF INSPECTION</td>
                                    <td class="input-cell"><?php echo $p['date']; ?></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">TIME OF INSPECTION</td>
                                    <td class="input-cell"><?php echo $print_time; ?></td>
                                </tr>
                            </table>

                            <div class="desc-section">
                                <div class="desc-box">
                                    <strong style="margin-bottom: 8px; display: block;">DESCRIPTION OF FINDINGS / INSPECTION:</strong>
                                    <span class="desc-text"><?php echo nl2br(htmlspecialchars($p['desc'])); ?></span>
                                    <?php if (!empty($p['image_paths']) && is_array($p['image_paths'])): ?>
                                        <div class="image-section" style="display:flex!important;">
                                            <?php foreach ($p['image_paths'] as $idx => $path): ?>
                                                <?php 
                                                // Changed default size from 50 to 48
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
                            </div>

                            <div class="form-footer">
                                <div style="font-size: 8pt; font-weight: bold; font-style: italic; margin-top: 5px;">Copy furnished to
                                    the office of:</div>
                                <table class="copy-furnished-table">
                                    <tr>
                                        <td>Principal/Dean</td>
                                        <td style="text-align: center; vertical-align: bottom; padding-bottom: 5px;">
                                            <div
                                                style="border-bottom: 1px solid black; width: 90%; margin: 0 auto 3px auto; height: 15px;">
                                            </div>
                                            <div style="font-weight: bold; font-size: 8pt; text-transform: uppercase;">JORGE C. LUMBANG,
                                                LPT</div>
                                            <div style="font-size: 6.5pt; line-height: 1.1;">HRD Officer / Acting Manager
                                                for<br>Administrative Office</div>
                                        </td>
                                        <td>Others (Specify)</td>
                                    </tr>
                                </table>
                                <div class="officer-section">
                                    <div class="officer-title" style="margin-bottom: 25px;">Officer in charge of the inspection:</div>
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
                                    <div style="display: flex; justify-content: space-between; width: 100%;">
                                        <div style="text-align: center;">
                                            <div style="border-top: 1px solid black; width: 250px; padding-top: 5px;">
                                                <div class="officer-name-line">PAUL JEFFREY T. LANSANGAN, SO3</div>
                                                <div class="officer-position">CHIEF, Safety and Protection</div>
                                            </div>
                                        </div>
                                        <div style="text-align: center;">
                                            <div style="border-top: 1px solid black; width: 250px; padding-top: 5px;">
                                                <div class="officer-name-line">EDWIN GUEVARRA</div>
                                                <div class="officer-position">Supervisor</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                <div class="hcc-form">
                    <div class="form-inner">
                        <h2>NO ITEMS IN QUEUE</h2>
                    </div>
                </div><?php endif; ?>
        </div>

        <div id="print-blank-area">
            <div class="hcc-form">
                <div class="form-inner">
                    <div class="new-header-wrapper">
                        <div class="fading-bar"></div>
                        <div class="header-content">
                            <img src="Logo-hcc.png" alt="Hcc Logo" class="new-header-logo">
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
                            <h3>FACILITIES AND EQUIPMENT INSPECTION REPORT</h3>
                        </div>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <td class="label-cell">NAME OF FACILITY/EQUIPMENT/ITEM</td>
                            <td class="input-cell">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="label-cell">LOCATION</td>
                            <td class="input-cell">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="label-cell">DATE OF INSPECTION</td>
                            <td class="input-cell">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="label-cell">TIME OF INSPECTION</td>
                            <td class="input-cell">&nbsp;</td>
                        </tr>
                    </table>
                    
                    <div class="desc-section">
                        <div class="desc-box">
                            <strong style="margin-bottom: 8px; display: block;">DESCRIPTION OF FINDINGS / INSPECTION:</strong>
                        </div>
                    </div>

                    <div class="form-footer">
                        <div style="font-size: 8pt; font-weight: bold; font-style: italic; margin-top: 5px;">Copy furnished to
                            the office of:</div>
                        <table class="copy-furnished-table">
                            <tr>
                                <td>Principal/Dean</td>
                                <td style="text-align: center; vertical-align: bottom; padding-bottom: 5px;">
                                    <div
                                        style="border-bottom: 1px solid black; width: 90%; margin: 0 auto 3px auto; height: 15px;">
                                    </div>
                                    <div style="font-weight: bold; font-size: 8pt; text-transform: uppercase;">JORGE C. LUMBANG,
                                        LPT</div>
                                    <div style="font-size: 6.5pt; line-height: 1.1;">HRD Officer / Acting Manager
                                        for<br>Administrative Office</div>
                                </td>
                                <td>Others (Specify)</td>
                            </tr>
                        </table>
                        <div class="officer-section">
                            <div class="officer-title" style="margin-bottom: 25px;">Officer in charge of the inspection:</div>
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
                            <div style="display: flex; justify-content: space-between; width: 100%;">
                                <div style="text-align: center;">
                                    <div style="border-top: 1px solid black; width: 250px; padding-top: 5px;">
                                        <div class="officer-name-line">PAUL JEFFREY T. LANSANGAN, SO3</div>
                                        <div class="officer-position">CHIEF, Safety and Protection</div>
                                    </div>
                                </div>
                                <div style="text-align: center;">
                                    <div style="border-top: 1px solid black; width: 250px; padding-top: 5px;">
                                        <div class="officer-name-line">EDWIN GUEVARRA</div>
                                        <div class="officer-position">Supervisor</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bottom-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold m-0"><i class="fa fa-database me-2"></i> RECENT INSPECTIONS</h5>
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
                            <th>Facility / Item</th>
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

                                $preview_data = [
                                    'title' => $row['title'],
                                    'loc' => $row['location'],
                                    'date' => $row['inspection_date'],
                                    'time' => $row['inspection_time'],
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
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td><?php echo htmlspecialchars($row['location']); ?></td>
                                    <td><?php echo $row['inspection_date']; ?></td>
                                    <td><?php echo date('h:i A', strtotime($row['inspection_time'])); ?></td>
                                    <td class="text-end">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <button type="button" class="btn btn-sm btn-info text-white"
                                                onclick='loadToPreview(<?php echo $preview_json; ?>)'
                                                title="Load into Display/Form">
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
                                    <i class="fa fa-clipboard-list fa-2x mb-3"></i><br>
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

            function printQueue() {
                document.body.classList.remove('print-blank');
                window.print();
            }

            function printBlank() {
                document.body.classList.add('print-blank');
                window.print();
            }

            // --- TEXT AUTO-SHRINK FUNCTION ---
            function autoFitAllTexts() {
                const containers = document.querySelectorAll('.desc-box');
                
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
                    const minSize = 7;
                    
                    while ((textEl.offsetHeight + imgHeight + 10) > availableHeight && currentSize > minSize) {
                        currentSize -= 0.5;
                        textEl.style.fontSize = currentSize + 'pt';
                    }
                });
            }

            let loadedImages = [];
            let isLoadedMode = false;

            // --- SPLIT LOGIC: TEXT UPDATE ONLY ---
            function updateTextPreview() {
                // Removed .toUpperCase() to allow mixed-case formatting
                document.getElementById('out_title').innerText = document.getElementById('in_title').value;
                document.getElementById('out_loc').innerText = document.getElementById('in_loc').value;
                document.getElementById('out_date').innerText = document.getElementById('in_date').value || '';

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
                document.getElementById('out_desc').innerText = document.getElementById('in_desc').value;

                autoFitAllTexts();
            }

            // --- SPLIT LOGIC: IMAGE UPDATE ONLY ---
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
                
                // Re-sync size array to match newly added items (Changed default from 50 to 48)
                let currentSizes = [];
                try { currentSizes = JSON.parse(document.getElementById('in_img_size').value); } catch(e){}
                while(currentSizes.length < this.files.length) currentSizes.push(48);
                document.getElementById('in_img_size').value = JSON.stringify(currentSizes);

                renderFormPreviews();
                updateImagePreview(); 
            });

            function updateImagePreview() {
                paperImageContainer.innerHTML = '';

                // --- Fetch current Array of Saved Sizes (Changed default from 50 to 48) ---
                let savedSizesVal = document.getElementById('in_img_size').value;
                let sizeArray = [];
                try {
                    sizeArray = JSON.parse(savedSizesVal);
                    if (!Array.isArray(sizeArray)) sizeArray = [sizeArray];
                } catch(e) {
                    sizeArray = [parseInt(savedSizesVal) || 48];
                }

                // --- HELPER FUNCTION TO APPEND MS-WORD STYLE RESIZABLE IMAGES ---
                function appendImage(src, index) {
                    let wrapper = document.createElement('div');
                    wrapper.className = 'resize-wrapper';
                    
                    // Changed default from 50 to 48
                    let initialSize = sizeArray[index] !== undefined ? sizeArray[index] : 48;
                    wrapper.style.width = initialSize + '%';
                    
                    let img = document.createElement('img');
                    img.src = src;
                    img.className = 'paper-preview-img';
                    img.onload = function() { autoFitAllTexts(); };
                    
                    wrapper.appendChild(img);

                    // Inject 8 interaction handles (4 edges, 4 corners)
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
                                
                                let updatedSizes = [];
                                document.querySelectorAll('#out_images_container .resize-wrapper').forEach(w => {
                                    // Changed fallback from 50 to 48
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

            // --- Load Data to Preview ---
            function loadToPreview(data) {
                document.getElementById('in_title').value = data.title;
                document.getElementById('in_loc').value = data.loc;
                document.getElementById('in_date').value = data.date;
                document.getElementById('in_time').value = data.time;
                document.getElementById('in_desc').value = data.desc;

                // Apply Saved Image Sizes (JSON string array from DB)
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

                updateTextPreview();
                updateImagePreview();
                setTimeout(autoFitAllTexts, 200);

                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }

            // --- Reset Form ---
            function resetForm() {
                document.getElementById('reportForm').reset();
                document.getElementById('in_images').value = "";
                document.getElementById('in_img_size').value = "[]";
                dt = new DataTransfer();
                loadedImages = [];
                isLoadedMode = false;
                document.getElementById('form-image-previews').innerHTML = "";

                updateTextPreview();
                updateImagePreview();
                document.querySelectorAll('.desc-text').forEach(el => el.style.fontSize = '11pt');
            }

            document.addEventListener('DOMContentLoaded', function () {
                updateTextPreview();
                updateImagePreview();
                setTimeout(() => {
                    const alerts = document.querySelectorAll('.alert');
                    alerts.forEach(alert => {
                        new bootstrap.Alert(alert).close();
                    });
                }, 5000);
            });
        </script>

    </body>

    </html>