<?php
$sname = "localhost";
$uname = "root";
$password = "";
$dbname = "sapd_db"; // Your Database Name

// 1. Connect to MySQL
$conn = new mysqli($sname, $uname, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Create Database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "Database '$dbname' checked...<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// 3. Select the Database
$conn->select_db($dbname);

// 4. Create Users Table
$users_table = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'user',
    status VARCHAR(20) DEFAULT 'pending',
    verification_code INT(6) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($users_table) === TRUE) {
    echo "Users table checked...<br>";
}

// 5. Create Permits Table
$permits_table = "CREATE TABLE IF NOT EXISTS permits (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    permit_number VARCHAR(50),
    name VARCHAR(255),
    type VARCHAR(50)
)";
if ($conn->query($permits_table) === TRUE) {
    echo "Permits table checked...<br>";
}

// 6. Create Form Submissions Table
$forms_table = "CREATE TABLE IF NOT EXISTS form_submissions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11),
    form_type VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending'
)";
if ($conn->query($forms_table) === TRUE) {
    echo "Forms table checked...<br>";
}

// 7. Create Default Admin
$admin_pass = password_hash("admin123", PASSWORD_DEFAULT);
$check_admin = $conn->query("SELECT * FROM users WHERE username='admin'");
if ($check_admin->num_rows == 0) {
    $sql = "INSERT INTO users (name, email, username, password, role, status) 
            VALUES ('System Admin', 'admin@sapd.com', 'admin', '$admin_pass', 'admin', 'active')";
    if ($conn->query($sql) === TRUE) {
        echo "<h3 style='color:green'>Admin Created! (User: admin / Pass: admin123)</h3>";
    }
} else {
    echo "Admin account already exists.<br>";
}

echo "<br><a href='index.php'>Go to Login</a>";
?>