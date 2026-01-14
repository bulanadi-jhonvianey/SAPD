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

$table_sql = "CREATE TABLE IF NOT EXISTS permits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    department VARCHAR(255) NOT NULL,
    plate_number VARCHAR(50) NOT NULL,
    fb_link TEXT,
    permit_number INT NOT NULL, 
    school_year VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($table_sql);

// --- AUTOMATIC DATA REPAIR ---
$conn->query("UPDATE permits SET permit_number = id WHERE permit_number = 0 OR permit_number IS NULL");

// Initialize Session Queue
if (!isset($_SESSION['print_queue'])) { $_SESSION['print_queue'] = []; }

// --- 2. FORM HANDLERS ---

// HANDLE: ADD PERMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_permit'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $dept = $conn->real_escape_string($_POST['dept']);
    $plate = $conn->real_escape_string($_POST['plate']);
    $fb_link = $conn->real_escape_string($_POST['fb_link']);
    $sy = $conn->real_escape_string($_POST['school_year']); 

    $insert_sql = "INSERT INTO permits (name, department, plate_number, fb_link, permit_number, school_year) 
                   VALUES ('$name', '$dept', '$plate', '$fb_link', 0, '$sy')";
    
    if ($conn->query($insert_sql)) {
        $new_id = $conn->insert_id;
        $conn->query("UPDATE permits SET permit_number = $new_id WHERE id = $new_id");

        // Add to Session Queue
        $_SESSION['print_queue'][] = [
            'name' => strtoupper($name),
            'dept' => strtoupper($dept),
            'plate' => strtoupper($plate),
            'permit_no' => $new_id, 
            'qr_data' => $fb_link ? $fb_link : "NoData",
            'sy' => $sy,
            // Layout Settings
            'cw' => $_POST['card_w'] ?? 350, 'ch' => $_POST['card_h'] ?? 240,
            'ns' => $_POST['name_size'], 'nx' => $_POST['name_x'], 'ny' => $_POST['name_y'],
            'ds' => $_POST['dept_size'], 'dx' => $_POST['dept_x'], 'dy' => $_POST['dept_y'],
            'ps' => $_POST['plate_size'], 'px' => $_POST['plate_x'], 'py' => $_POST['plate_y'],
            'qs' => $_POST['qr_size'], 'qx' => $_POST['qr_x'], 'qy' => $_POST['qr_y'],
            'cs' => $_POST['count_size'], 'cx' => $_POST['count_x'], 'cy' => $_POST['count_y'],
            'ss' => $_POST['sy_size'], 'sx' => $_POST['sy_x'], 'sy_pos' => $_POST['sy_y']
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// HANDLE: CLEAR QUEUE
if (isset($_POST['clear_queue'])) {
    $_SESSION['print_queue'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// HANDLE: RESET DATABASE
if (isset($_POST['reset_db'])) {
    $conn->query("TRUNCATE TABLE permits");
    $_SESSION['print_queue'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$res = $conn->query("SELECT MAX(id) as max_id FROM permits");
$row = $res->fetch_assoc();
$next_display_id = ($row['max_id'] !== null) ? $row['max_id'] + 1 : 1;

$recent_permits = $conn->query("SELECT * FROM permits ORDER BY id DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Permit System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        /* --- THEME VARIABLES --- */
        /* Default is Dark in this file's logic */
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

        /* --- PERMIT CARD DESIGN --- */
        .permit-card {
            width: var(--card-w);
            height: var(--card-h);
            background-image: url('Employee.png'); 
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
        /* HCC Logo - SAME SIZE AS SAPD */
        .logo-left { left: 20px; top: 10px; width: 55px; height: 55px; } 
        /* Shield Logo */
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
        
        .plate-info {
            position: absolute; font-weight: 800; color: #000; text-transform: uppercase;
            font-size: 11px; z-index: 15; letter-spacing: 0.5px;
        }

        /* QR AREA - Moved Right */
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
        
        .table-custom { color: var(--text-main); }
        .table-custom th { background-color: var(--input-bg); color: var(--accent); border-color: var(--border); }
        .table-custom td { border-color: var(--border); background-color: transparent; color: var(--text-main); }

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
        <h4 class="m-0 fw-bold text-white">Employee Permit Generator</h4>
    </div>
    <div class="d-flex gap-2">
         <form method="POST" onsubmit="return confirm('WARNING: This will delete ALL permits and reset counter to 1.');" class="m-0">
            <button type="submit" name="reset_db" class="btn btn-danger fw-bold"><i class="fas fa-redo"></i> Reset DB</button>
        </form>
        <button class="btn btn-theme rounded-circle" onclick="toggleTheme()" id="themeBtn">
            <i class="fa fa-moon"></i>
        </button>
    </div>
</div>

<div class="main-container">
    <div class="left-panel">
        <div class="panel-header">
            <div class="panel-title"><i class="fa fa-user-plus"></i> NEW PERMIT ENTRY</div>
            <div class="badge-next">NEXT: <?php echo $next_display_id; ?></div>
        </div>

        <form method="POST" action="">
            
            <input type="text" name="name" id="in_name" class="form-control" placeholder="Full Name" required oninput="updatePreview()">

            <input type="text" name="dept" id="in_dept" class="form-control" placeholder="Department / Position" required oninput="updatePreview()">

            <input type="text" name="plate" id="in_plate" class="form-control" placeholder="Plate Number" required oninput="updatePreview()">

            <div class="row g-2 mb-3">
                <div class="col-8">
                    <input type="text" name="fb_link" id="in_link" class="form-control mb-0" placeholder="Facebook Link (QR Data)" oninput="updatePreview()">
                </div>
                <div class="col-4">
                    <input type="text" name="school_year" id="in_sy" class="form-control mb-0 text-center fw-bold" value="2025-2026" oninput="updatePreview()">
                </div>
            </div>

            <button class="btn btn-outline-warning w-100 mb-3 btn-sm fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#layoutSettings">
                <i class="fa fa-cogs"></i> TOGGLE LAYOUT SETTINGS
            </button>

            <div class="collapse" id="layoutSettings">
                <div class="p-3 border rounded mb-3" style="background: var(--input-bg); border-color: var(--border)!important;">
                    <label class="form-label fw-bold text-white mb-2" style="font-size: 0.75rem;">CARD & TEXT</label>
                    
                    <div class="control-grid" style="grid-template-columns: 1fr 1fr;">
                        <div><span class="control-label-sm">Card W</span><input type="number" name="card_w" id="card_w" class="form-control form-control-sm" value="350" oninput="updatePreview()"></div>
                        <div><span class="control-label-sm">Card H</span><input type="number" name="card_h" id="card_h" class="form-control form-control-sm" value="240" oninput="updatePreview()"></div>
                    </div>
                    
                    <div class="control-grid">
                        <div><span class="control-label-sm">Name Size</span><input type="number" name="name_size" id="name_size" class="form-control form-control-sm" value="12" oninput="updatePreview()"></div>
                        <div><span class="control-label-sm">Name X</span><input type="number" name="name_x" id="name_x" class="form-control form-control-sm" value="0" oninput="updatePreview()"></div>
                        <div><span class="control-label-sm">Name Y</span><input type="number" name="name_y" id="name_y" class="form-control form-control-sm" value="120" oninput="updatePreview()"></div>
                    </div>

                    <div class="control-grid">
                        <div><span class="control-label-sm">Dept Size</span><input type="number" name="dept_size" id="dept_size" class="form-control form-control-sm" value="11" oninput="updatePreview()"></div>
                        <div><span class="control-label-sm">Dept X</span><input type="number" name="dept_x" id="dept_x" class="form-control form-control-sm" value="0" oninput="updatePreview()"></div>
                        <div><span class="control-label-sm">Dept Y</span><input type="number" name="dept_y" id="dept_y" class="form-control form-control-sm" value="139" oninput="updatePreview()"></div>
                    </div>

                    <div class="control-grid">
                        <div><span class="control-label-sm">Plate Size</span><input type="number" name="plate_size" id="plate_size" class="form-control form-control-sm" value="11" oninput="updatePreview()"></div>
                        <div><span class="control-label-sm">Plate X</span><input type="number" name="plate_x" id="plate_x" class="form-control form-control-sm" value="45" oninput="updatePreview()"></div>
                        <div><span class="control-label-sm">Plate Y</span><input type="number" name="plate_y" id="plate_y" class="form-control form-control-sm" value="35" oninput="updatePreview()"></div>
                    </div>

                    <hr style="border-color:#666">
                    <label class="form-label fw-bold text-white mb-2" style="font-size: 0.75rem;">QR, YEAR & COUNT</label>

                    <div class="control-grid">
                        <div><span class="control-label-sm">QR Size</span><input type="number" name="qr_size" id="qr_size" class="form-control form-control-sm" value="60" oninput="updatePreview()"></div>
                        <div><span class="control-label-sm">QR Right</span><input type="number" name="qr_x" id="qr_x" class="form-control form-control-sm" value="5" oninput="updatePreview()"></div>
                        <div><span class="control-label-sm">QR Bottom</span><input type="number" name="qr_y" id="qr_y" class="form-control form-control-sm" value="15" oninput="updatePreview()"></div>
                    </div>

                    <div class="control-grid">
                        <div><span class="control-label-sm">Year Size</span><input type="number" name="sy_size" id="sy_size" class="form-control form-control-sm" value="11" oninput="updatePreview()"></div>
                        <div><span class="control-label-sm">Year Right</span><input type="number" name="sy_x" id="sy_x" class="form-control form-control-sm" value="0" oninput="updatePreview()"></div>
                        <div><span class="control-label-sm">Year Top</span><input type="number" name="sy_y" id="sy_y" class="form-control form-control-sm" value="58" oninput="updatePreview()"></div>
                    </div>

                    <div class="control-grid">
                        <div><span class="control-label-sm">Count Size</span><input type="number" name="count_size" id="count_size" class="form-control form-control-sm" value="20" oninput="updatePreview()"></div>
                        <div><span class="control-label-sm">Count Right</span><input type="number" name="count_x" id="count_x" class="form-control form-control-sm" value="0" oninput="updatePreview()"></div>
                        <div><span class="control-label-sm">Count Top</span><input type="number" name="count_y" id="count_y" class="form-control form-control-sm" value="-25" oninput="updatePreview()"></div>
                    </div>

                </div>
            </div>

            <button type="submit" name="add_permit" class="btn btn-success w-100 fw-bold py-3">
                <i class="fa fa-plus-circle me-2"></i> Add to Print Queue
            </button>
        </form>

        <hr class="border-secondary my-4">

        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small opacity-75">Queue Management</span>
            <span class="badge-queue">QUEUE: <?php echo count($_SESSION['print_queue']); ?> PERMITS</span>
        </div>
        
        <div class="d-flex gap-2">
             <button onclick="window.print()" class="btn btn-primary flex-grow-1 fw-bold" <?php echo count($_SESSION['print_queue']) == 0 ? 'disabled' : ''; ?>>
                <i class="fa fa-print me-2"></i> Print Queue
            </button>
            <?php if(count($_SESSION['print_queue']) > 0): ?>
                <form method="POST"><button type="submit" name="clear_queue" class="btn btn-outline-danger fw-bold"><i class="fa fa-trash"></i></button></form>
            <?php endif; ?>
        </div>
       
    </div>

    <div class="right-panel">
        <div class="panel-header w-100 border-bottom pb-3 mb-4" style="border-color: var(--border)!important;">
            <div class="panel-title"><i class="fa fa-eye"></i> PERMIT PREVIEW</div>
        </div>
        
        <div class="permit-card" id="preview-card">
            
            <img src="HCC.png" class="logo-header logo-left" alt="HCC Logo">
            <img src="background.png" class="logo-header logo-right" alt="Shield Logo">

            <img src="https://placehold.co/100x100/e0e0e0/888888?text=PHOTO" class="photo-img" alt="Employee Photo">

            <span class="text-name" id="out_name">NAME</span>
            <span class="text-dept" id="out_dept">DEPARTMENT</span>
            
            <div class="plate-info" id="plate_container">
                PLATE#: <span id="out_plate">-------</span>
            </div>

            <div class="qr-area" id="qr_container">
                <div class="control-no" id="out_ctrl"><?php echo $next_display_id; ?></div>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=Empty" class="qr-img" id="out_qr">
                <span class="sy-text" id="out_sy">2025-2026</span>
            </div>
        </div>
        
        <div class="mt-4 text-center opacity-50 small">
            <i class="fa fa-info-circle"></i> Preview updates automatically.
        </div>
    </div>
</div>

<div class="bottom-panel">
    <h5 class="fw-bold mb-3"><i class="fa fa-database me-2"></i> RECENT DATABASE ENTRIES</h5>
    <div class="table-responsive">
        <table class="table table-custom table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Permit #</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Plate #</th>
                    <th>Year</th>
                    <th>Date</th>
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
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center opacity-50">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="print-area">
    <?php 
    foreach($_SESSION['print_queue'] as $item): 
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($item['qr_data']);
        
        // Load Settings
        $cw = $item['cw']; $ch = $item['ch'];
        $ns = $item['ns']; $nx = $item['nx']; $ny = $item['ny'];
        $ds = $item['ds']; $dx = $item['dx']; $dy = $item['dy'];
        $ps = $item['ps']; $px = $item['px']; $py = $item['py'];
        $qs = $item['qs']; $qx = $item['qx']; $qy = $item['qy'];
        $cs = $item['cs']; $cx = $item['cx']; $cy = $item['cy'];
        // Year Settings
        $ss = $item['ss']; $sx = $item['sx']; $sy_pos = $item['sy_pos'];
    ?>
    <div class="permit-card" style="width: <?php echo $cw; ?>px; height: <?php echo $ch; ?>px;">
        <img src="HCC.png" class="logo-header logo-left" alt="HCC Logo">
        <img src="background.png" class="logo-header logo-right" alt="Shield Logo">

        <img src="https://placehold.co/100x100/e0e0e0/888888?text=PHOTO" class="photo-img">
        <span class="text-name" style="font-size: <?php echo $ns; ?>px; left: <?php echo $nx; ?>px; top: <?php echo $ny; ?>px; width: 100%; text-align: center;"><?php echo $item['name']; ?></span>
        <span class="text-dept" style="font-size: <?php echo $ds; ?>px; left: <?php echo $dx; ?>px; top: <?php echo $dy; ?>px; width: 100%; text-align: center;"><?php echo $item['dept']; ?></span>
        <div class="plate-info" style="font-size: <?php echo $ps; ?>px; bottom: <?php echo $py; ?>px; left: <?php echo $px; ?>px;">PLATE#: <span><?php echo $item['plate']; ?></span></div>
        
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
    
    // Check saved theme (default to 'light' to match dashboard behavior if null)
    const savedTheme = localStorage.getItem('appTheme') || 'light';
    if (savedTheme === 'light') {
        document.body.classList.add('light-mode');
        document.getElementById('themeBtn').innerHTML = '<i class="fa fa-sun"></i>';
    } else {
        // Default css is dark, so do nothing to keep it dark
        document.body.classList.remove('light-mode');
        document.getElementById('themeBtn').innerHTML = '<i class="fa fa-moon"></i>';
    }

    function updatePreview() {
        // --- 1. GET DATA ---
        let displayName = document.getElementById('in_name').value.toUpperCase() || "NAME";
        let displaySy = document.getElementById('in_sy').value || "Enter Year";
        
        // Apply Text
        document.getElementById('out_name').innerText = displayName;
        document.getElementById('out_dept').innerText = document.getElementById('in_dept').value.toUpperCase() || "DEPARTMENT";
        document.getElementById('out_plate').innerText = document.getElementById('in_plate').value.toUpperCase() || "-------";
        document.getElementById('out_sy').innerText = displaySy;
        
        let link = document.getElementById('in_link').value;
        let qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" + (link ? encodeURIComponent(link) : "Empty");
        document.getElementById('out_qr').src = qrUrl;

        // --- 3. LAYOUT UPDATES ---
        // Card Dimensions
        let cW = document.getElementById('card_w').value;
        let cH = document.getElementById('card_h').value;
        let card = document.getElementById('preview-card');
        card.style.width = cW + 'px';
        card.style.height = cH + 'px';

        // Name
        let elName = document.getElementById('out_name');
        elName.style.fontSize = document.getElementById('name_size').value + 'px';
        elName.style.left = document.getElementById('name_x').value + 'px';
        elName.style.top = document.getElementById('name_y').value + 'px';
        elName.style.width = cW + 'px';

        // Dept
        let elDept = document.getElementById('out_dept');
        elDept.style.fontSize = document.getElementById('dept_size').value + 'px';
        elDept.style.left = document.getElementById('dept_x').value + 'px';
        elDept.style.top = document.getElementById('dept_y').value + 'px';
        elDept.style.width = cW + 'px';

        // Plate
        let elPlate = document.getElementById('plate_container');
        elPlate.style.fontSize = document.getElementById('plate_size').value + 'px';
        elPlate.style.left = document.getElementById('plate_x').value + 'px';
        elPlate.style.bottom = document.getElementById('plate_y').value + 'px';

        // QR Area
        let qrBox = document.getElementById('qr_container');
        let qrImg = document.getElementById('out_qr');
        let qrSize = document.getElementById('qr_size').value;
        qrImg.style.width = qrSize + 'px';
        qrImg.style.height = qrSize + 'px';
        qrBox.style.right = document.getElementById('qr_x').value + 'px';
        qrBox.style.bottom = document.getElementById('qr_y').value + 'px';

        // Year Layout
        let elSy = document.getElementById('out_sy');
        elSy.style.position = 'absolute'; 
        elSy.style.fontSize = document.getElementById('sy_size').value + 'px';
        elSy.style.right = document.getElementById('sy_x').value + 'px';
        elSy.style.top = document.getElementById('sy_y').value + 'px';
        elSy.style.width = '100%'; 

        // Auto Count
        let countEl = document.getElementById('out_ctrl');
        countEl.style.fontSize = document.getElementById('count_size').value + 'px';
        countEl.style.right = document.getElementById('count_x').value + 'px';
        countEl.style.top = document.getElementById('count_y').value + 'px';
    }

    document.addEventListener('DOMContentLoaded', updatePreview);
</script>

</body>
</html>