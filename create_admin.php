<?php
// 建立資料庫連線
$conn = new mysqli('db', 'root', '1024', 'cloud_drive');
$conn->set_charset("utf8");

// 管理員帳號資料
$username = 'ad';
$password = '1024';
$is_admin = 1;

// 加密密碼
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 寫入資料庫
$stmt = $conn->prepare("INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)");
$stmt->bind_param("ssi", $username, $hashed_password, $is_admin);

if ($stmt->execute()) {
    echo "✅ 管理員帳號建立成功！<br>帳號：ad<br>密碼：1024";
} else {
    echo "❌ 建立失敗：" . $stmt->error;
}

$stmt->close();
$conn->close();
?>
