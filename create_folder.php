<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$conn = get_db_connection();
$user_id = $_SESSION['user_id'];
$folder_name = trim($_POST['folder_name'] ?? '');
$parent_id = (int)($_POST['parent_id'] ?? 0);

if (empty($folder_name)) {
    $_SESSION['create_folder_error'] = "資料夾名稱不能為空。";
} else {
    if ($parent_id === 0) {
        $stmt = $conn->prepare("INSERT INTO folders (user_id, parent_id, folder_name) VALUES (?, NULL, ?)");
        $stmt->bind_param("is", $user_id, $folder_name);
    } else {
        $stmt = $conn->prepare("INSERT INTO folders (user_id, parent_id, folder_name) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $parent_id, $folder_name);
    }
    
    if ($stmt->execute()) {
        $_SESSION['create_folder_success'] = "資料夾 '{$folder_name}' 建立成功！";
        
        // 備註：如果您需要為每個資料夾創建一個實體目錄 (uploads/ID/)
        // 可以在這裡獲取新插入的 ID 並創建目錄
        // $new_folder_id = $conn->insert_id;
        // $dir_path = __DIR__ . '/uploads/' . $new_folder_id . '/';
        // if (!is_dir($dir_path)) { mkdir($dir_path, 0777, true); }

    } else {
        $_SESSION['create_folder_error'] = "資料夾建立失敗：" . $stmt->error;
    }
    $stmt->close();
}

$conn->close();
header("Location: my_files.php?folder_id=" . $parent_id);
exit;
?>
