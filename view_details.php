<?php
session_start();
include "db_conn.php";

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$view = isset($_GET['view']) ? $_GET['view'] : 'users';
$title = "Details";
$result = null;

// Logic to determine what data to fetch
if ($view == 'users') {
    $title = "Active Users";
    $result = $conn->query("SELECT name, email, username FROM users WHERE status='active' AND role='user'");
} elseif ($view == 'all_permits') {
    $title = "All Issued Permits";
    $result = $conn->query("SELECT * FROM permits");
} elseif ($view == 'letter' || $view == 'division' || $view == 'incident' || $view == 'vaping' || $view == 'parking') {
    $title = ucfirst($view) . " Submissions";
    $result = $conn->query("SELECT f.id, u.name, f.status FROM form_submissions f LEFT JOIN users u ON f.user_id = u.id WHERE f.form_type='$view'");
} elseif ($view == 'EMPLOYEES' || $view == 'STUDENT LICENSE' || $view == 'STUDENT NON-PRO') {
    $title = "$view Permits";
    $result = $conn->query("SELECT * FROM permits WHERE type='$view'");
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?> - SAPD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap");

        /* --- THEME VARIABLES (Same as Dashboard) --- */
        :root {
            --bg-body: #f4f7fe;
            --bg-card: #ffffff;
            --text-main: #2b3674;
            --text-muted: #a3aed0;
            --border-color: #e0e5f2;
        }

        [data-bs-theme="dark"] {
            --bg-body: #0b1437;
            --bg-card: #111c44;
            --text-main: #ffffff;
            --text-muted: #8f9bba;
            --border-color: #1b254b;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Poppins', sans-serif;
            transition: 0.3s;
            padding: 30px;
        }

        /* Card Styling */
        .details-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }

        /* Table Styling */
        .table {
            --bs-table-bg: transparent;
            color: var(--text-main);
        }

        .table thead th {
            border-bottom: 2px solid var(--border-color);
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        .table tbody td {
            border-bottom: 1px solid var(--border-color);
            padding: 15px 10px;
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(67, 24, 255, 0.05);
            /* Slight blue tint on hover */
        }

        /* Buttons */
        .btn-theme {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-main);
        }

        .btn-theme:hover {
            background: var(--border-color);
        }
    </style>

    <script>
        const savedTheme = localStorage.getItem('appTheme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold m-0"><?php echo $title; ?></h2>
                <small class="text-muted">Viewing data from database</small>
            </div>

            <div class="d-flex gap-3">
                <button class="btn btn-theme rounded-circle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="dashboard.php" class="btn btn-secondary rounded-pill px-4">
                    <i class="fas fa-arrow-left me-2"></i> Back
                </a>
            </div>
        </div>

        <div class="details-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <?php if ($view == 'users'): ?>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Username</th>
                            <?php elseif (strpos($view, 'ermit') !== false || $view == 'all_permits' || $view == 'EMPLOYEES' || $view == 'STUDENT LICENSE' || $view == 'STUDENT NON-PRO'): ?>
                                <th>Permit #</th>
                                <th>Name</th>
                                <th>Type</th>
                            <?php else: ?>
                                <th>ID</th>
                                <th>User Name</th>
                                <th>Status</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <?php if ($view == 'users'): ?>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>

                                    <?php elseif (strpos($view, 'ermit') !== false || $view == 'all_permits' || $view == 'EMPLOYEES' || $view == 'STUDENT LICENSE' || $view == 'STUDENT NON-PRO'): ?>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['permit_number'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><span
                                                class="badge bg-primary bg-opacity-10 text-primary border border-primary"><?php echo htmlspecialchars($row['type']); ?></span>
                                        </td>

                                    <?php else: ?>
                                        <td>#<?php echo $row['id']; ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['name'] ?? 'Unknown User'); ?></td>
                                        <td>
                                            <?php
                                            $status = strtolower($row['status']);
                                            $badgeClass = $status == 'approved' ? 'success' : ($status == 'pending' ? 'warning' : 'secondary');
                                            ?>
                                            <span
                                                class="badge bg-<?php echo $badgeClass; ?>"><?php echo ucfirst($row['status']); ?></span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-2x mb-3 d-block"></i>
                                    No records found for this category.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const toggleBtn = document.getElementById('themeToggle');
        const icon = toggleBtn.querySelector('i');
        const html = document.documentElement;

        function updateIcon(theme) {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Initialize Icon
        updateIcon(localStorage.getItem('appTheme') || 'light');

        // Click Handler
        toggleBtn.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            html.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('appTheme', newTheme);
            updateIcon(newTheme);
        });
    </script>
</body>
</html>