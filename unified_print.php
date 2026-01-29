<?php
// --- 1. SETUP & CONFIGURATION ---
ob_start();
session_start();

// ENABLE STRICT ERROR REPORTING
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- HANDLE CLEAR ALL QUEUES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all'])) {
    $_SESSION['print_queue'] = [];          // Clear Employee
    $_SESSION['np_print_queue'] = [];       // Clear Non-Pro
    $_SESSION['student_print_queue'] = [];  // Clear Student
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- COLLECT ALL PERMITS INTO ONE ARRAY ---
$all_permits = [];

// 1. Add Employee Permits
if (isset($_SESSION['print_queue']) && !empty($_SESSION['print_queue'])) {
    foreach ($_SESSION['print_queue'] as $item) {
        $item['type'] = 'EMPLOYEE';
        $item['bg_img'] = 'background_employee.png';
        $all_permits[] = $item;
    }
}

// 2. Add Non-Pro Permits
if (isset($_SESSION['np_print_queue']) && !empty($_SESSION['np_print_queue'])) {
    foreach ($_SESSION['np_print_queue'] as $item) {
        // Fix key mismatch for Non-Pro (uses 'course' instead of 'dept')
        if (!isset($item['dept']) && isset($item['course'])) { $item['dept'] = $item['course']; }
        $item['type'] = 'NON-PRO';
        $item['bg_img'] = 'background_non_pro.png';
        $all_permits[] = $item;
    }
}

// 3. Add Student Permits
if (isset($_SESSION['student_print_queue']) && !empty($_SESSION['student_print_queue'])) {
    foreach ($_SESSION['student_print_queue'] as $item) {
        $item['type'] = 'STUDENT';
        $item['bg_img'] = 'background_student.png';
        $all_permits[] = $item;
    }
}

// --- SORT BY PERMIT NUMBER (NUMERICAL ORDER) ---
usort($all_permits, function($a, $b) {
    return $a['permit_no'] - $b['permit_no'];
});

// Calculate Totals
$total_emp = isset($_SESSION['print_queue']) ? count($_SESSION['print_queue']) : 0;
$total_np = isset($_SESSION['np_print_queue']) ? count($_SESSION['np_print_queue']) : 0;
$total_stu = isset($_SESSION['student_print_queue']) ? count($_SESSION['student_print_queue']) : 0;
$grand_total = count($all_permits);
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
        /* --- COPYING EXACT CSS FROM ADMIN APPROVAL / PERMIT MODULES --- */
        @import url("https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700;900&display=swap");

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
            font-family: 'Segoe UI', sans-serif;
            transition: background-color 0.3s, color 0.3s;
            padding-bottom: 50px;
        }

        /* --- HEADER & NAVBAR --- */
        .navbar { 
            background: var(--panel-bg); 
            border-bottom: 1px solid var(--border); 
            padding: 15px 20px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
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
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(0,0,0,0.2);
            filter: brightness(110%);
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }

        .btn-primary { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; }
        .btn-danger { background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%); color: white; }
        .btn-secondary { background: linear-gradient(135deg, #858796 0%, #60616f 100%); color: white; }
        
        /* THEME TOGGLE BUTTON */
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
        .btn-theme:hover { background: var(--accent); color: white; border-color: var(--accent); }

        /* --- PERMIT CARD DESIGN --- */
        .permit-card {
            width: var(--card-w);
            height: var(--card-h);
            position: relative;
            background-color: white; 
            color: black;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
            margin: 10px;
            display: inline-block; /* Flow layout for printing */
            page-break-inside: avoid;
        }

        /* Card Elements */
        .logo-header { position: absolute; object-fit: contain; z-index: 20; }
        .logo-left { left: 20px; top: 10px; width: 55px; height: 55px; } 
        .logo-right { right: 20px; top: 10px; width: 55px; height: 55px; }

        .photo-img {
            position: absolute; top: 51%; left: 6px; transform: translateY(-50%);
            width: 85px; height: 85px; object-fit: cover; border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 5; background: #ccc;
        }

        .text-name, .text-dept { font-weight: 900; text-transform: uppercase; line-height: 1; display: block; position: absolute; width: 100%; text-align: center; white-space: nowrap; }
        .text-name { color: #000; }
        .text-dept { color: #333; font-weight: 700; }

        .plate-info { position: absolute; font-weight: 800; color: #000; text-transform: uppercase; font-size: 11px; z-index: 15; letter-spacing: 0.5px; left: 6px; text-align: left; }
        .valid-until-info { position: absolute; font-weight: 600; color: #ff6600; text-transform: uppercase; font-size: 9px; z-index: 15; letter-spacing: 0.5px; left: 6px; text-align: left; }
        
        .qr-area { position: absolute; bottom: 15px; right: 5px; text-align: center; }
        .qr-img { width: 60px; height: 60px; border: 1px solid #ddd; background: white; }
        .control-no { position: absolute; width: 100%; text-align: center; font-weight: 900; color: #cc0000; }
        .sy-text { display: block; margin-top: 2px; line-height: 1; position: relative; font-weight: 800; color: #007bff; white-space: nowrap; }

        /* Specific Labels */
        .type-label { position: absolute; left: 6px; top: 165px; text-align: left; z-index: 25; font-weight: 900; color: #000; text-transform: uppercase; line-height: 1; }

        .empty-state { text-align: center; padding: 60px; background: var(--panel-bg); border-radius: 15px; border: 1px solid var(--border); margin-top: 20px; color: var(--text-main); }

        /* --- PRINT STYLES --- */
        @media print {
            .navbar, .no-print, #themeBtn { display: none !important; }
            body { background: white; margin: 0; padding: 0; color: black; }
            .print-container { display: flex; flex-wrap: wrap; justify-content: flex-start; gap: 10px; padding: 10px; }
            .permit-card { border: 1px solid #ccc; box-shadow: none; margin: 0 10px 10px 0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

    <div class="navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-secondary fw-bold"><i class="fa fa-arrow-left me-2"></i> Dashboard</a>
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

    <div class="print-container mt-4 text-center">
        
        <?php if ($grand_total == 0): ?>
            <div class="container">
                <div class="empty-state">
                    <i class="fa fa-print fa-4x mb-3 text-secondary"></i>
                    <h4>Print Queue Empty</h4>
                    <p class="opacity-75">Add permits from the Employee, Non-Pro, or Student modules.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php 
        // LOOP THROUGH SORTED PERMITS
        foreach ($all_permits as $item) {
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($item['qr_data']);
            renderCard($item, $item['type'], $item['bg_img'], $qr_url);
        }

        // --- HELPER FUNCTION TO RENDER HTML ---
        function renderCard($item, $type, $bg_img, $qr_url) {
            // Extract layout variables (Fallback defaults match admin defaults)
            $cw = $item['cw'] ?? 350; $ch = $item['ch'] ?? 240;
            $ns = $item['ns'] ?? 12; $nx = $item['nx'] ?? 11; $ny = $item['ny'] ?? 110;
            
            // Handle varying keys for department size
            $ds = $item['ds'] ?? $item['cs'] ?? 11; 
            
            // Handle varying keys for department Position
            $dx = $item['dx'] ?? $item['cx'] ?? 0;
            $dy = $item['dy'] ?? $item['cy'] ?? 129;
            
            $ps = $item['ps'] ?? 11; $px = $item['px'] ?? 6; $py = $item['py'] ?? 180;
            $qs = $item['qs'] ?? 60; $qx = $item['qx'] ?? 5; $qy = $item['qy'] ?? 15;
            
            // Count / Permit # Variables (Prioritize 'cts' etc, fall back to 'cs')
            $cs = $item['cts'] ?? $item['cs'] ?? 20; 
            $cx = $item['ctx'] ?? $item['cx'] ?? 0;
            $cy = $item['cty'] ?? $item['cy'] ?? -25;
            
            $ss = $item['ss'] ?? 11; $sx = $item['sx'] ?? 0; $sy_pos = $item['sy_pos'] ?? 58;
            
            // Handle Valid Until (Student specific)
            $valid_html = '';
            if (isset($item['valid_until'])) {
                $vs = $item['vs'] ?? 9; $vx = $item['vx'] ?? 8; $vy = $item['vy'] ?? 197;
                $valid_html = "<div class='valid-until-info' style='font-size: {$vs}px; top: {$vy}px; left: {$vx}px;'>Valid Until: <span>{$item['valid_until']}</span></div>";
            }

            // Handle Type Label Styling
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
        
        // Check saved theme
        const savedTheme = localStorage.getItem('appTheme') || 'light';
        if (savedTheme === 'light') {
            document.body.classList.add('light-mode');
            document.getElementById('themeBtn').innerHTML = '<i class="fa fa-sun"></i>';
        } else {
            document.body.classList.remove('light-mode');
            document.getElementById('themeBtn').innerHTML = '<i class="fa fa-moon"></i>';
        }
    </script>

</body>
</html>