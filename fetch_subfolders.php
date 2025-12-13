<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => '未經授權']);
    exit;
}

$conn = get_db_connection();
$user_id = $_SESSION['user_id'];
$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;

if ($parent_id === 0) {
    echo json_encode(['error' => '無效的父資料夾 ID']);
    $conn->close();
    exit;
}

$subfolders = [];
$stmt = $conn->prepare("SELECT id, folder_name FROM folders WHERE user_id = ? AND parent_id = ? ORDER BY folder_name");
$stmt->bind_param("ii", $user_id, $parent_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $subfolders[] = ['id' => $row['id'], 'name' => $row['folder_name']];
}

$stmt->close();
$conn->close();

echo json_encode($subfolders);
?>
