<?php
include "db_conn.php";
header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// 1. Fetch Events
if ($action == 'fetch') {
    $data = [];
    $result = $conn->query("SELECT id, title, start_event as start, end_event as end, color as backgroundColor, color as borderColor FROM events");
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
    exit;
}

// 2. Add Event
if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $conn->real_escape_string($_POST['title']);
    $start = $conn->real_escape_string($_POST['start']);
    $end = $conn->real_escape_string($_POST['end']);
    $color = $conn->real_escape_string($_POST['color']);
    // created_at is automatic in MySQL
    $sql = "INSERT INTO events (title, start_event, end_event, color) VALUES ('$title', '$start', '$end', '$color')";
    echo json_encode(['success' => $conn->query($sql)]);
    exit;
}

// 3. Update Event
if ($action == 'update' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $title = $conn->real_escape_string($_POST['title']);
    $start = $conn->real_escape_string($_POST['start']);
    $end = $conn->real_escape_string($_POST['end']);
    $color = $conn->real_escape_string($_POST['color']);
    $sql = "UPDATE events SET title='$title', start_event='$start', end_event='$end', color='$color' WHERE id=$id";
    echo json_encode(['success' => $conn->query($sql)]);
    exit;
}

// 4. Delete Event
if ($action == 'delete' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $sql = "DELETE FROM events WHERE id=$id";
    echo json_encode(['success' => $conn->query($sql)]);
    exit;
}

// 5. CHECK FOR NEW NOTIFICATIONS (The New Feature)
// This checks if any event was created in the last 5 seconds
if ($action == 'check_notification') {
    $sql = "SELECT title FROM events WHERE created_at >= NOW() - INTERVAL 5 SECOND";
    $result = $conn->query($sql);
    
    $events = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
    echo json_encode($events);
    exit;
}
?>