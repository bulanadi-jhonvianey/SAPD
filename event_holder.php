<?php
include "db_conn.php";

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// 1. FETCH EVENTS
if ($action == 'fetch') {
    $data = [];
    $result = $conn->query("SELECT id, title, start_event as start, end_event as end, color FROM events");
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
    exit;
}

// 2. ADD EVENT
if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $conn->real_escape_string($_POST['title']);
    $start = $conn->real_escape_string($_POST['start']);
    $end = $conn->real_escape_string($_POST['end']);
    $color = $conn->real_escape_string($_POST['color']);

    $sql = "INSERT INTO events (title, start_event, end_event, color) VALUES ('$title', '$start', '$end', '$color')";
    echo json_encode(['success' => $conn->query($sql)]);
    exit;
}

// 3. UPDATE EVENT (Drag & Drop or Edit)
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

// 4. DELETE EVENT
if ($action == 'delete' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $sql = "DELETE FROM events WHERE id=$id";
    echo json_encode(['success' => $conn->query($sql)]);
    exit;
}
?>