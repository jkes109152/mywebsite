<?php
session_start();

// --- 0. 資料庫連線配置 (請務必在生產環境中保護這些憑證) ---
$db_host = 'localhost';
$db_user = 'root'; 
$db_pass = '1024'; 
$db_name = 'cloud_drive';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
// 檢查連線
if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error); 
}
$conn->set_charset("utf8");

// --- 1. 權限檢查與變數初始化 ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
// 判斷是否為管理員 (假設 $_SESSION['is_admin'] == 1 為管理員)
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

$change_pwd_msg = '';
$delete_msg = '';

// --- 2. 變更密碼功能 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (trim($new_password) === '' || trim($confirm_password) === '') {
        $change_pwd_msg = "❌ 新密碼不可為空";
    } elseif ($new_password !== $confirm_password) {
        $change_pwd_msg = "❌ 兩次密碼不一致";
    } else {
        // 使用安全的密碼雜湊
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $hashed, $user_id);
            if ($stmt->execute()) {
                $change_pwd_msg = "✅ 密碼更新成功";
            } else {
                $change_pwd_msg = "❌ 更新失敗: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $change_pwd_msg = "❌ 資料庫準備失敗: " . $conn->error;
        }
    }
}

// --- 3. 刪除帳號功能 (新增管理員檢查與事務處理) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    
    // 💥 核心修正：管理員帳號無法自行刪除
    if ($is_admin) {
        $delete_msg = "🚫 **管理員帳號無法自行刪除！** 請聯繫其他管理員或資料庫管理員進行操作。";
    } else {
        // 開始事務處理，確保刪除資料的一致性
        $conn->begin_transaction();
        
        try {
            // 步驟 A: 獲取並刪除該用戶的所有實體檔案
            $stmt_files = $conn->prepare("SELECT filepath FROM files WHERE user_id = ?");
            $stmt_files->bind_param("i", $user_id);
            $stmt_files->execute();
            $res_files = $stmt_files->get_result();

            while ($row = $res_files->fetch_assoc()) {
                if (file_exists($row['filepath'])) {
                    // 如果刪除實體檔案失敗，拋出異常並回滾整個事務
                    if (!unlink($row['filepath'])) {
                        throw new Exception("無法刪除檔案實體: " . $row['filepath']);
                    }
                }
            }
            $stmt_files->close();

            // 步驟 B: 刪除 files 表中的記錄
            $stmt_del_files = $conn->prepare("DELETE FROM files WHERE user_id = ?");
            $stmt_del_files->bind_param("i", $user_id);
            if (!$stmt_del_files->execute()) {
                throw new Exception("無法刪除檔案記錄");
            }
            $stmt_del_files->close();

            // 步驟 C: 刪除使用者帳號
            $stmt_del_user = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt_del_user->bind_param("i", $user_id);
            if (!$stmt_del_user->execute()) {
                throw new Exception("無法刪除使用者帳號");
            }
            $stmt_del_user->close();

            // 步驟 D: 所有操作成功，提交事務
            $conn->commit();
            
            // 銷毀會話並重定向
            session_destroy();
            header("Location: login.php");
            exit;

        } catch (Exception $e) {
            // 任何失敗，回滾資料庫操作
            $conn->rollback();
            $delete_msg = "❌ 刪除帳號失敗: " . $e->getMessage();
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8" />
    <title>管理我的帳戶</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body style="background: #f0f2f5;">
<div class="container py-5" style="max-width: 600px;">

    <h2 class="mb-4 text-center">管理我的帳戶 - <?= htmlspecialchars($username) ?></h2>

    <div class="mb-4 d-flex justify-content-center gap-3">
        <a href="upload.php" class="btn btn-success">⬆️ 上傳檔案</a>
        <a href="my_files.php" class="btn btn-primary">📁 我的檔案</a>
        
        <?php 
        if ($is_admin) {
            echo '<a href="admin.php" class="btn btn-warning">👑 管理員頁面</a>';
        }
        ?>

        <a href="logout.php" class="btn btn-outline-danger">🚪 登出</a>
    </div>

    <div class="card mb-5 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">修改密碼</h5>
        </div>
        <div class="card-body">
            <?php if ($change_pwd_msg): ?>
                <div class="alert <?= strpos($change_pwd_msg, '成功') !== false ? 'alert-success' : 'alert-danger' ?>" role="alert">
                    <?= htmlspecialchars($change_pwd_msg) ?>
                </div>
            <?php endif; ?>
            <form method="post" novalidate>
                <input type="hidden" name="change_password" value="1" />
                <div class="mb-3">
                    <label for="new_password" class="form-label">新密碼</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required />
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">確認密碼</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required />
                </div>
                <button type="submit" class="btn btn-primary w-100">更新密碼</button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">刪除帳號</h5>
        </div>
        <div class="card-body">
            <?php if ($delete_msg): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($delete_msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($is_admin): ?>
                <div class="alert alert-warning" role="alert">
                    **您是管理員。** 為了系統穩定性，管理員帳號無法透過此頁面自行刪除。
                </div>
                <button type="button" class="btn btn-secondary w-100" disabled>管理員禁止刪除</button>
            <?php else: ?>
                <form method="post" onsubmit="return confirm('⚠️ 確定要刪除帳號？此動作將永久刪除您的所有檔案且無法復原！');">
                    <input type="hidden" name="delete_account" value="1" />
                    <button type="submit" class="btn btn-danger w-100">永久刪除帳號</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

</div>
</body>
</html>