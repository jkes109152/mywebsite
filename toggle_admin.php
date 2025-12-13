<?php
session_start();

// 僅限管理員使用
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: admin.php");
    exit;
}

$user_id = intval($_GET['id']);

$conn = new mysqli('localhost', 'root', '1024', 'cloud_drive');
$conn->set_charset("utf8");

// 防止操作自己帳號
if ($user_id == $_SESSION['user_id']) {
    header("Location: admin.php?error=self");
    exit;
}

// 查目前權限
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($user = $result->fetch_assoc()) {
    $new_status = $user['is_admin'] ? 0 : 1;
    $update = $conn->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
    $update->bind_param("ii", $new_status, $user_id);
    $update->execute();
}

header("Location: admin.php");
exit;
