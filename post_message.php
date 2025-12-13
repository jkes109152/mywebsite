<?php
// 留言板留言處理程式
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$conn = new mysqli('localhost', 'root', '1024', 'cloud_drive');
$conn->set_charset("utf8");

$sender_id = $_SESSION['user_id'];
$content = trim($_POST['content'] ?? '');

if ($content === '') {
    http_response_code(400);
    exit('Message is empty');
}

// 儲存留言內容
$stmt = $conn->prepare("INSERT INTO messages (sender_id, content, created_at) VALUES (?, ?, NOW())");
$stmt->bind_param("is", $sender_id, $content);
$stmt->execute();
$message_id = $stmt->insert_id;
$stmt->close();

// 解析 @ 用戶
preg_match_all('/@(\w+)/', $content, $matches);
$mentions = array_unique($matches[1]);

$target_user_ids = [];
if (in_array('all', $mentions)) {
    // @all: 取得全部使用者
    $result = $conn->query("SELECT id FROM users");
    while ($row = $result->fetch_assoc()) {
        $target_user_ids[] = $row['id'];
    }
    $result->close();
} elseif (!empty($mentions)) {
    // 指定 @ 人員
    $placeholders = implode(',', array_fill(0, count($mentions), '?'));
    $stmt = $conn->prepare("SELECT id FROM users WHERE username IN ($placeholders)");

    $types = str_repeat('s', count($mentions));
    $stmt->bind_param($types, ...$mentions);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $target_user_ids[] = $row['id'];
    }
    $stmt->close();
}

// 寫入 message_user_visibility
foreach ($target_user_ids as $user_id) {
    $stmt = $conn->prepare("INSERT INTO message_user_visibility (message_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $message_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// 發送推播（範例: 呼叫 send_push.php）
foreach ($target_user_ids as $user_id) {
    // 查 token
    $stmt = $conn->prepare("SELECT fcm_token FROM users WHERE id = ? AND fcm_token IS NOT NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($fcm_token);

    if ($stmt->fetch()) {
        file_get_contents("https://jkesbyebye.com/send_push.php?token=" . urlencode($fcm_token) . "&message=" . urlencode($content));
    }

    $stmt->close();
}

echo "Message sent";
?>
