<?php
session_start();

// 確認是管理員
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit;
}

// 資料庫連線
$conn = new mysqli('localhost', 'root', '1024', 'cloud_drive');
$conn->set_charset("utf8");

// 確認有帶入檔案 ID
$file_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($file_id > 0) {
    // 找出檔案路徑
    $stmt = $conn->prepare("SELECT filepath FROM files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $filepath = $row['filepath'];
        if (file_exists($filepath)) {
            unlink($filepath); // 刪除實體檔案
        }
    }

    // 刪除資料庫紀錄
    $delete = $conn->prepare("DELETE FROM files WHERE id = ?");
    $delete->bind_param("i", $file_id);
    $delete->execute();
}

// 刪除完成後導回
header("Location: admin.php");
exit;
?>
