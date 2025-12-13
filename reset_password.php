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
$new_password = password_hash("1234", PASSWORD_DEFAULT);

$conn = new mysqli('db', 'root', '1024', 'cloud_drive');
$conn->set_charset("utf8");

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $new_password, $user_id);
$stmt->execute();

header("Location: admin.php?reset=success");
exit;
