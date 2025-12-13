<?php
session_start();

// 1. 執行安全性檢查：確保用戶已登入
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    die("您尚未登入，無法預覽檔案。");
}

// --- 資料庫連線配置 ---
$db_host = 'db';
$db_user = 'root'; 
$db_pass = '1024'; 
$db_name = 'cloud_drive';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    die("資料庫連線失敗。");
}
$conn->set_charset("utf8");

$user_id = $_SESSION['user_id'];
$file_id = (int)($_GET['file_id'] ?? 0);
// 檢查是否為管理員 (假設 session 變數已設置)
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

if ($file_id <= 0) {
    http_response_code(404);
    die("檔案 ID 無效。");
}

// --- 2. 權限驗證與檔案資訊獲取 ---
if ($is_admin) {
    // 管理員：無條件允許存取
    $stmt = $conn->prepare("SELECT filepath, filename FROM files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    
} else {
    // 一般用戶：必須驗證檔案擁有者
    $stmt = $conn->prepare("SELECT filepath, filename FROM files WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $file_id, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$file_info = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$file_info) {
    http_response_code(404);
    die("找不到該檔案或您無權存取。");
}

// 設置檔案的絕對路徑
$file_path = __DIR__ . '/' . $file_info['filepath'];
$file_name = $file_info['filename'];

// 3. 檢查檔案實體是否存在且可讀取
if (!file_exists($file_path) || !is_readable($file_path)) {
    http_response_code(404);
    // 提示權限或路徑錯誤
    die("實體檔案不存在或伺服器無權讀取。請檢查檔案路徑和權限設定。");
}


// --- 4. 實作自定義預覽邏輯 (核心輸出部分) ---

// 嘗試獲取 MIME 類型 (優先使用 finfo，因為它更可靠)
$mime_type = 'application/octet-stream'; // 預設為通用二進制流
$extension = pathinfo($file_name, PATHINFO_EXTENSION);
$file_size = filesize($file_path);

if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file_path);
} elseif (function_exists('mime_content_type')) {
    $mime_type = mime_content_type($file_path);
}

// 設置 Content-Length 標頭
header("Content-Length: " . $file_size);

// A. 純文本文件 (.txt, .log, .json, .csv 等)
if (strpos($mime_type, 'text/') === 0 || in_array($extension, ['log', 'json', 'csv', 'md'])) {
    header("Content-Type: text/plain; charset=utf-8");
    header("Content-Disposition: inline; filename=\"".$file_name."\""); 
    readfile($file_path);
    exit;

} 
// B. 瀏覽器原生支援格式 (圖片、音頻、視頻、PDF)
// 關鍵：這裡必須確保所有圖片類型，包括 image/jpeg 等，都落在這個區塊
elseif (strpos($mime_type, 'image/') === 0 || strpos($mime_type, 'audio/') === 0 || strpos($mime_type, 'video/') === 0 || $mime_type === 'application/pdf') {
    
    // 💥 解決持續下載的關鍵：設定 Content-Disposition: inline
    header("Content-Type: " . $mime_type);
    header("Content-Disposition: inline; filename=\"".$file_name."\""); 
    
    // 避免緩衝區問題：清除不必要的輸出緩衝
    if (ob_get_level()) { ob_end_clean(); }
    
    readfile($file_path);
    exit;
    
} 
// C. Office 檔案 (引導下載或提示)
elseif (in_array($extension, ['docx', 'xlsx', 'pptx'])) {
    // 輸出 HTML 提示
    ?>
    <!DOCTYPE html>
    <html lang="zh-Hant"><head><title>預覽失敗</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>
    <div class="container py-5 text-center">
        <h1 class="text-danger">無法在瀏覽器中預覽 Office 檔案 (.<?= $extension ?>)</h1>
        <p class="lead">請下載後用 Office 軟體開啟。</p>
        <a href="download.php?id=<?= $file_id ?>" class="btn btn-success btn-lg mt-3">點擊下載檔案</a>
    </div></body></html>
    <?php
    exit;
} 
// D. 其他所有無法預覽的格式，強制轉為下載
else {
    header("Content-Type: " . $mime_type); // 使用偵測到的 MIME 或預設值
    header("Content-Transfer-Encoding: Binary");
    // 設置 Content-Disposition: attachment 強制下載
    header("Content-Disposition: attachment; filename=\"".$file_name."\"");
    
    if (ob_get_level()) { ob_end_clean(); }
    
    readfile($file_path);
    exit;
}
?>