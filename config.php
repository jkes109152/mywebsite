<?php
// config.php
$db_host = 'localhost';
$db_user = 'root'; 
$db_pass = '1024'; 
$db_name = 'cloud_drive';

// 建立連線的函數
function get_db_connection() {
    global $db_host, $db_user, $db_pass, $db_name;
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        die("連線失敗: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4"); // 使用 utf8mb4 支援更廣泛的 Unicode 字符
    return $conn;
}
?>
