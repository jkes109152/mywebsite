<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("參數錯誤");
}

$conn = new mysqli('localhost', 'root', '1024', 'cloud_drive');
if ($conn->connect_error) {
    die("資料庫連線錯誤：" . $conn->connect_error);
}
$conn->set_charset("utf8");

$file_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// 撈出檔案資訊，確保是該使用者的檔案
$stmt = $conn->prepare("SELECT filename, filepath FROM files WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $file_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("找不到檔案或沒有權限");
}
$file = $result->fetch_assoc();

$fullPath = __DIR__ . '/' . $file['filepath'];
if (!file_exists($fullPath)) {
    die("檔案不存在");
}

// 強制下載
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
