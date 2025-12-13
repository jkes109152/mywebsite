<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['file_id'])) {
    header("Location: my_files.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$file_id = (int)$_POST['file_id'];

$conn = new mysqli('localhost', 'root', '1024', 'cloud_drive');
$conn->set_charset("utf8");

// 查詢檔案是否屬於該使用者
$stmt = $conn->prepare("SELECT filepath FROM files WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $file_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['delete_error'] = "❌ 找不到該檔案或無刪除權限";
    header("Location: my_files.php");
    exit;
}

$row = $result->fetch_assoc();
$filepath = $row['filepath'];

// 刪除實體檔案
if (file_exists($filepath)) {
    unlink($filepath);
}

// 刪除資料庫記錄
$stmt_del = $conn->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
$stmt_del->bind_param("ii", $file_id, $user_id);
$stmt_del->execute();

$_SESSION['delete_success'] = "✅ 檔案已刪除";

header("Location: my_files.php");
exit;
