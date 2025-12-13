<?php
session_start();

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli('db', 'root', '1024', 'cloud_drive');
$conn->set_charset("utf8");

// 取得欲刪除的帳號 ID
$user_id = (int)($_GET['id'] ?? 0);

if ($user_id > 0) {
    // 刪除檔案實體
    $stmt = $conn->prepare("SELECT filepath FROM files WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($file = $result->fetch_assoc()) {
        if (file_exists($file['filepath'])) {
            unlink($file['filepath']);
        }
    }

    // 刪除檔案資料
    $del_files = $conn->prepare("DELETE FROM files WHERE user_id = ?");
    $del_files->bind_param("i", $user_id);
    $del_files->execute();

    // 刪除使用者
    $del_user = $conn->prepare("DELETE FROM users WHERE id = ?");
    $del_user->bind_param("i", $user_id);
    $del_user->execute();
}

// 返回上一頁
header("Location: admin.php");
exit;
?>
