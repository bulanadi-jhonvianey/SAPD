<?php
// --- 1. SETUP & CONFIGURATION ---
ob_start();
session_start();

// ENABLE STRICT ERROR REPORTING
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sapd_db";

// Create connection
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->select_db($dbname);

// Ensure the global sequence table exists (for consistent permit numbers)
$conn->query("CREATE TABLE IF NOT EXISTS global_permit_sequence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Ensure permit tables exist
$conn->query("CREATE TABLE IF NOT EXISTS permits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    department VARCHAR(255) NOT NULL,
    plate_number VARCHAR(50) NOT NULL,
    fb_link TEXT,
    permit_number INT NOT NULL,
    school_year VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS non_pro_permits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    course VARCHAR(255) NOT NULL,
    plate_number VARCHAR(50) NOT NULL,
    fb_link TEXT,
    permit_number INT NOT NULL,
    school_year VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS student_permits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    department VARCHAR(255) NOT NULL,
    plate_number VARCHAR(50) NOT NULL,
    fb_link TEXT,
    permit_number INT NOT NULL,
    school_year VARCHAR(50) NOT NULL,
    valid_until VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// --- HANDLE CLEAR ALL QUEUES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all'])) {
    $_SESSION['print_queue'] = [];          // Clear Employee
    $_SESSION['np_print_queue'] = [];       // Clear Non-Pro
    $_SESSION['student_print_queue'] = [];  // Clear Student
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- HANDLE ADD SELECTED PERMITS TO QUEUE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_selected'])) {
    $selected = $_POST['selected'] ?? [];
    foreach ($selected as $key => $type) {
        $id = intval($key);
        $type = $conn->real_escape_string($type);

        if ($type == 'employee') {
            $sql = "SELECT * FROM permits WHERE id = $id";
            $result = $conn->query($sql);
            if ($row = $result->fetch_assoc()) {
                $item = [
                    'id' => $row['id'],
                    'name' => strtoupper($row['name']),
                    'dept' => strtoupper($row['department']),
                    'plate' => strtoupper($row['plate_number']),
                    'permit_no' => $row['permit_number'],
                    'qr_data' => $row['fb_link'] ?: "NoData",
                    'sy' => $row['school_year'],
                    'cw' => 350, 'ch' => 240,
                    'ns' => 12, 'nx' => 11, 'ny' => 110,
                    'ds' => 11, 'dx' => 0, 'dy' => 129,
                    'ps' => 11, 'px' => 6, 'py' => 180,
                    'qs' => 60, 'qx' => 5, 'qy' => 15,
                    'cs' => 20, 'cx' => 0, 'cy' => -25,
                    'ss' => 11, 'sx' => 0, 'sy_pos' => 58
                ];
                // Add to employee queue if not already present
                if (!isset($_SESSION['print_queue'])) $_SESSION['print_queue'] = [];
                $exists = false;
                foreach ($_SESSION['print_queue'] as $existing) {
                    if ($existing['permit_no'] == $item['permit_no']) { $exists = true; break; }
                }
                if (!$exists) $_SESSION['print_queue'][] = $item;
            }
        } elseif ($type == 'non_pro') {
            $sql = "SELECT * FROM non_pro_permits WHERE id = $id";
            $result = $conn->query($sql);
            if ($row = $result->fetch_assoc()) {
                $item = [
                    'id' => $row['id'],
                    'name' => strtoupper($row['name']),
                    'course' => strtoupper($row['course']),
                    'dept' => strtoupper($row['course']), // unified uses 'dept'
                    'plate' => strtoupper($row['plate_number']),
                    'permit_no' => $row['permit_number'],
                    'qr_data' => $row['fb_link'] ?: "NoData",
                    'sy' => $row['school_year'],
                    'cw' => 350, 'ch' => 240,
                    'ns' => 12, 'nx' => 11, 'ny' => 110,
                    'cs' => 11, 'cx' => 0, 'cy' => 129,
                    'ps' => 11, 'px' => 6, 'py' => 180,
                    'qs' => 60, 'qx' => 5, 'qy' => 15,
                    'cts' => 20, 'ctx' => 0, 'cty' => -25,
                    'ss' => 11, 'sx' => 0, 'sy_pos' => 58
                ];
                if (!isset($_SESSION['np_print_queue'])) $_SESSION['np_print_queue'] = [];
                $exists = false;
                foreach ($_SESSION['np_print_queue'] as $existing) {
                    if ($existing['permit_no'] == $item['permit_no']) { $exists = true; break; }
                }
                if (!$exists) $_SESSION['np_print_queue'][] = $item;
            }
        } elseif ($type == 'student') {
            $sql = "SELECT * FROM student_permits WHERE id = $id";
            $result = $conn->query($sql);
            if ($row = $result->fetch_assoc()) {
                $item = [
                    'id' => $row['id'],
                    'name' => strtoupper($row['name']),
                    'dept' => strtoupper($row['department']),
                    'plate' => strtoupper($row['plate_number']),
                    'permit_no' => $row['permit_number'],
                    'qr_data' => $row['fb_link'] ?: "NoData",
                    'sy' => $row['school_year'],
                    'valid_until' => $row['valid_until'],
                    'cw' => 350, 'ch' => 240,
                    'ns' => 12, 'nx' => 11, 'ny' => 110,
                    'ds' => 11, 'dx' => 0, 'dy' => 129,
                    'ps' => 11, 'px' => 6, 'py' => 180,
                    'vs' => 9, 'vx' => 8, 'vy' => 197,
                    'qs' => 60, 'qx' => 5, 'qy' => 15,
                    'cs' => 20, 'cx' => 0, 'cy' => -25,
                    'ss' => 11, 'sx' => 0, 'sy_pos' => 58
                ];
                if (!isset($_SESSION['student_print_queue'])) $_SESSION['student_print_queue'] = [];
                $exists = false;
                foreach ($_SESSION['student_print_queue'] as $existing) {
                    if ($existing['permit_no'] == $item['permit_no']) { $exists = true; break; }
                }
                if (!$exists) $_SESSION['student_print_queue'][] = $item;
            }
        }
    }
    // Refresh after adding
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- COLLECT ALL PERMITS FROM SESSION QUEUES ---
$all_permits = [];

// 1. Employee
if (isset($_SESSION['print_queue']) && !empty($_SESSION['print_queue'])) {
    foreach ($_SESSION['print_queue'] as $item) {
        $item['type'] = 'EMPLOYEE';
        $item['bg_img'] = 'background_employee.png';
        $all_permits[] = $item;
    }
}

// 2. Non-Pro
if (isset($_SESSION['np_print_queue']) && !empty($_SESSION['np_print_queue'])) {
    foreach ($_SESSION['np_print_queue'] as $item) {
        if (!isset($item['dept']) && isset($item['course'])) {
            $item['dept'] = $item['course'];
        }
        $item['type'] = 'NON-PRO';
        $item['bg_img'] = 'background_non_pro.png';
        $all_permits[] = $item;
    }
}

// 3. Student
if (isset($_SESSION['student_print_queue']) && !empty($_SESSION['student_print_queue'])) {
    foreach ($_SESSION['student_print_queue'] as $item) {
        $item['type'] = 'STUDENT';
        $item['bg_img'] = 'background_student.png';
        $all_permits[] = $item;
    }
}

// Sort by permit number
usort($all_permits, function ($a, $b) {
    return $a['permit_no'] - $b['permit_no'];
});

$total_emp = isset($_SESSION['print_queue']) ? count($_SESSION['print_queue']) : 0;
$total_np = isset($_SESSION['np_print_queue']) ? count($_SESSION['np_print_queue']) : 0;
$total_stu = isset($_SESSION['student_print_queue']) ? count($_SESSION['student_print_queue']) : 0;
$grand_total = count($all_permits);

// --- SEARCH FOR OLD PERMITS TO REPRINT ---
$search_term = '';
$search_results = [];
if (isset($_GET['search_reprint']) && !empty($_GET['search_reprint'])) {
    $search_term = $conn->real_escape_string($_GET['search_reprint']);
    $search_like = "%$search_term%";

    // Union all three tables, selecting appropriate columns
    $sql = "
        SELECT 'employee' AS type, id, name, department AS dept, plate_number, permit_number, school_year, NULL AS valid_until, created_at
        FROM permits
        WHERE id LIKE '$search_like' OR name LIKE '$search_like'
        UNION ALL
        SELECT 'non_pro' AS type, id, name, course AS dept, plate_number, permit_number, school_year, NULL AS valid_until, created_at
        FROM non_pro_permits
        WHERE id LIKE '$search_like' OR name LIKE '$search_like'
        UNION ALL
        SELECT 'student' AS type, id, name, department AS dept, plate_number, permit_number, school_year, valid_until, created_at
        FROM student_permits
        WHERE id LIKE '$search_like' OR name LIKE '$search_like'
        ORDER BY created_at DESC LIMIT 50
    ";
    $search_results = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unified Print - SAPD</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* --- EXACT CSS FROM THE ORIGINAL UNIFIED PRINT --- */
        @import url("https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700;900&display=swap");

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
            font-family: 'Segoe UI', sans-serif;
            transition: background-color 0.3s, color 0.3s;
            padding-bottom: 50px;
        }

        .navbar {
            background: var(--panel-bg);
            border-bottom: 1px solid var(--border);
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

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

        .btn-danger {
            background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #858796 0%, #60616f 100%);
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

        .permit-card {
            width: var(--card-w);
            height: var(--card-h);
            position: relative;
            background-color: white;
            color: black;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            margin: 10px;
            display: inline-block;
            page-break-inside: avoid;
        }

        .logo-header {
            position: absolute;
            object-fit: contain;
            z-index: 20;
        }

        .logo-left {
            left: 20px;
            top: 10px;
            width: 55px;
            height: 55px;
        }

        .logo-right {
            right: 20px;
            top: 10px;
            width: 55px;
            height: 55px;
        }

        .photo-img {
            position: absolute;
            top: 51%;
            left: 6px;
            transform: translateY(-50%);
            width: 85px;
            height: 85px;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 5;
            background: #ccc;
        }

        .text-name,
        .text-dept {
            font-weight: 900;
            text-transform: uppercase;
            line-height: 1;
            display: block;
            position: absolute;
            width: 100%;
            text-align: center;
            white-space: nowrap;
        }

        .text-name {
            color: #000;
        }

        .text-dept {
            color: #333;
            font-weight: 700;
        }

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

        .qr-area {
            position: absolute;
            bottom: 15px;
            right: 5px;
            text-align: center;
        }

        .qr-img {
            width: 60px;
            height: 60px;
            border: 1px solid #ddd;
            background: white;
        }

        .control-no {
            position: absolute;
            width: 100%;
            text-align: center;
            font-weight: 900;
            color: #cc0000;
        }

        .sy-text {
            display: block;
            margin-top: 2px;
            line-height: 1;
            position: relative;
            font-weight: 800;
            color: #007bff;
            white-space: nowrap;
        }

        .type-label {
            position: absolute;
            left: 6px;
            top: 165px;
            text-align: left;
            z-index: 25;
            font-weight: 900;
            color: #000;
            text-transform: uppercase;
            line-height: 1;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            background: var(--panel-bg);
            border-radius: 15px;
            border: 1px solid var(--border);
            margin-top: 20px;
            color: var(--text-main);
        }

        /* Table styles for search results (matching bottom panel in permit modules) */
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
        .badge.bg-dark {
            background-color: var(--input-bg) !important;
            color: var(--text-main);
            border: 1px solid var(--border);
        }

        @media print {
            .navbar,
            .no-print,
            #themeBtn {
                display: none !important;
            }
            body {
                background: white;
                margin: 0;
                padding: 0;
                color: black;
            }
            .print-container {
                display: flex;
                flex-wrap: wrap;
                justify-content: flex-start;
                gap: 10px;
                padding: 10px;
            }
            .permit-card {
                border: 1px solid #ccc;
                box-shadow: none;
                margin: 0 10px 10px 0;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>

<body>

    <div class="navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-secondary fw-bold"><i class="fa fa-arrow-left me-2"></i> Back</a>
            <h4 class="m-0 fw-bold" style="color: var(--text-main);">Unified Print System</h4>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="employee_permit.php" class="btn btn-theme px-3 w-auto" style="width: auto;" title="Employee Permits">
                <i class="fa fa-id-badge me-2"></i> <?php echo $total_emp; ?>
            </a>
            <a href="non_permit.php" class="btn btn-theme px-3 w-auto" style="width: auto;" title="Non-Pro Permits">
                <i class="fa fa-car me-2"></i> <?php echo $total_np; ?>
            </a>
            <a href="student_permit.php" class="btn btn-theme px-3 w-auto" style="width: auto;" title="Student Permits">
                <i class="fa fa-graduation-cap me-2"></i> <?php echo $total_stu; ?>
            </a>
            <button class="btn btn-theme rounded-circle ms-2" onclick="toggleTheme()" id="themeBtn">
                <i class="fa fa-moon"></i>
            </button>
        </div>
    </div>

    <!-- Queue summary -->
    <div class="container-fluid mt-4 no-print">
        <div class="d-flex justify-content-between align-items-center p-4 rounded shadow-sm" style="background: var(--panel-bg); border: 1px solid var(--border);">
            <div>
                <h5 class="fw-bold m-0" style="color: var(--text-main);">
                    <i class="fa fa-layer-group me-2 text-warning"></i> TOTAL QUEUE:
                    <span class="badge bg-primary fs-5 mx-2"><?php echo $grand_total; ?></span>
                </h5>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-primary fw-bold px-4" <?php echo $grand_total == 0 ? 'disabled' : ''; ?>>
                    <i class="fa fa-print me-2"></i> PRINT ALL
                </button>
                <form method="POST" onsubmit="return confirm('Are you sure you want to clear ALL queues (Employee, Non-Pro, and Student)?');">
                    <button type="submit" name="clear_all" class="btn btn-danger fw-bold px-4" <?php echo $grand_total == 0 ? 'disabled' : ''; ?>>
                        <i class="fa fa-trash me-2"></i> CLEAR ALL
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- NEW: Reprint Old Permits Panel (styled like bottom panel in permit modules) -->
    <div class="bottom-panel no-print" style="margin: 20px; background: var(--panel-bg); padding: 25px; border-radius: 10px; border: 1px solid var(--border);">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-3">
                <h5 class="fw-bold m-0" style="color: var(--text-main);"><i class="fa fa-history me-2"></i> REPRINT OLD PERMITS</h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <form method="GET" class="d-flex gap-0" style="width: 300px;">
                    <div class="input-group">
                        <input type="text" name="search_reprint" class="form-control" placeholder="Search by ID or name..." value="<?php echo htmlspecialchars($search_term); ?>" style="margin-bottom: 0;">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i></button>
                        <?php if ($search_term): ?>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary"><i class="fa fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($search_term && $search_results && $search_results->num_rows > 0): ?>
            <form method="POST" onsubmit="return confirm('Add selected permits to print queue?');">
                <div class="table-responsive">
                    <table class="table table-custom table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Department / Course</th>
                                <th>Permit #</th>
                                <th>Plate #</th>
                                <th>School Year</th>
                                <th>Valid Until</th>
                            </thead>
                        <tbody>
                            <?php while ($row = $search_results->fetch_assoc()): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected[<?php echo $row['id']; ?>]" value="<?php echo $row['type']; ?>" class="permit-checkbox"></td>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $row['type'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['dept']); ?></td>
                                    <td><?php echo $row['permit_number']; ?></td>
                                    <td><?php echo htmlspecialchars($row['plate_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['school_year']); ?></td>
                                    <td><?php echo $row['valid_until'] ? htmlspecialchars($row['valid_until']) : '-'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <button type="submit" name="add_selected" class="btn btn-success">
                        <i class="fa fa-plus-circle me-2"></i> Add Selected to Queue
                    </button>
                </div>
            </form>
        <?php elseif ($search_term): ?>
            <div class="alert alert-warning mt-3 mb-0">
                <i class="fa fa-exclamation-triangle me-2"></i> No permits found matching your search.
            </div>
        <?php endif; ?>
    </div>

    <!-- Print container (unchanged) -->
    <div class="print-container mt-4 text-center">
        <?php if ($grand_total == 0): ?>
            <div class="container">
                <div class="empty-state">
                    <i class="fa fa-print fa-4x mb-3 text-secondary"></i>
                    <h4>Print Queue Empty</h4>
                    <p class="opacity-75">Add permits from the Employee, Non-Pro, or Student modules, or reprint old permits above.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php
        foreach ($all_permits as $item) {
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($item['qr_data']);
            renderCard($item, $item['type'], $item['bg_img'], $qr_url);
        }

        function renderCard($item, $type, $bg_img, $qr_url) {
            $cw = $item['cw'] ?? 350;
            $ch = $item['ch'] ?? 240;
            $ns = $item['ns'] ?? 12;
            $nx = $item['nx'] ?? 11;
            $ny = $item['ny'] ?? 110;
            $ds = $item['ds'] ?? $item['cs'] ?? 11;
            $dx = $item['dx'] ?? $item['cx'] ?? 0;
            $dy = $item['dy'] ?? $item['cy'] ?? 129;
            $ps = $item['ps'] ?? 11;
            $px = $item['px'] ?? 6;
            $py = $item['py'] ?? 180;
            $qs = $item['qs'] ?? 60;
            $qx = $item['qx'] ?? 5;
            $qy = $item['qy'] ?? 15;
            $cs = $item['cts'] ?? $item['cs'] ?? 20;
            $cx = $item['ctx'] ?? $item['cx'] ?? 0;
            $cy = $item['cty'] ?? $item['cy'] ?? -25;
            $ss = $item['ss'] ?? 11;
            $sx = $item['sx'] ?? 0;
            $sy_pos = $item['sy_pos'] ?? 58;
            $valid_html = '';
            if (isset($item['valid_until'])) {
                $vs = $item['vs'] ?? 9;
                $vx = $item['vx'] ?? 8;
                $vy = $item['vy'] ?? 197;
                $valid_html = "<div class='valid-until-info' style='font-size: {$vs}px; top: {$vy}px; left: {$vx}px;'>Valid Until: <span>{$item['valid_until']}</span></div>";
            }
            $label_html = "";
            if ($type === 'EMPLOYEE') {
                $label_html = "<div class='type-label'><span style='font-size: 14px; color: red;'>E</span><span style='font-size: 10px;'>MPLOYEE</span></div>";
            } elseif ($type === 'NON-PRO') {
                $label_html = "<div class='type-label'><span style='font-size: 14px; color: yellow;'>S</span><span style='font-size: 10px;'>TUDENT (NON-PRO)</span></div>";
            } elseif ($type === 'STUDENT') {
                $label_html = "<div class='type-label'><span style='font-size: 14px; color: yellow;'>S</span><span style='font-size: 10px;'>TUDENT LICENSE</span></div>";
            }

            echo "
            <div class='permit-card' style='width: {$cw}px; height: {$ch}px; background-image: url(\"$bg_img\"); background-size: 100% 100%;'>
                <img src='HCC.png' class='logo-header logo-left'>
                <img src='background.png' class='logo-header logo-right'>
                $label_html
                <img src='https://placehold.co/100x100/e0e0e0/888888?text=PHOTO' class='photo-img'>
                <span class='text-name' style='font-size: {$ns}px; left: {$nx}px; top: {$ny}px;'>{$item['name']}</span>
                <span class='text-dept' style='font-size: {$ds}px; left: {$dx}px; top: {$dy}px;'>{$item['dept']}</span>
                <div class='plate-info' style='font-size: {$ps}px; top: {$py}px; left: {$px}px;'>PLATE#: <span>{$item['plate']}</span></div>
                $valid_html
                <div class='qr-area' style='right: {$qx}px; bottom: {$qy}px;'>
                    <div class='control-no' style='font-size: {$cs}px; right: {$cx}px; top: {$cy}px;'>{$item['permit_no']}</div>
                    <img src='$qr_url' class='qr-img' style='width: {$qs}px; height: {$qs}px;'>
                    <span class='sy-text' style='font-size: {$ss}px; right: {$sx}px; top: {$sy_pos}px; width: 100%;'>{$item['sy']}</span>
                </div>
            </div>
            ";
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            const isLight = document.body.classList.contains('light-mode');
            document.getElementById('themeBtn').innerHTML = isLight ? '<i class="fa fa-sun"></i>' : '<i class="fa fa-moon"></i>';
            const themeValue = isLight ? 'light' : 'dark';
            localStorage.setItem('appTheme', themeValue);
            document.cookie = "theme=" + themeValue + "; path=/; max-age=31536000";
        }

        const savedTheme = localStorage.getItem('appTheme') || 'light';
        if (savedTheme === 'light') {
            document.body.classList.add('light-mode');
            document.getElementById('themeBtn').innerHTML = '<i class="fa fa-sun"></i>';
        } else {
            document.body.classList.remove('light-mode');
            document.getElementById('themeBtn').innerHTML = '<i class="fa fa-moon"></i>';
        }

        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('.permit-checkbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
        }
    </script>

</body>
</html>