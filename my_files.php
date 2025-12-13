<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// === 資料庫連線設定 ===
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '1024';
$db_name = 'cloud_drive';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}
$conn->set_charset("utf8");

$username = $_SESSION['username'] ?? '';
$user_id  = $_SESSION['user_id'];

// === 目前資料夾 ===
$current_folder_id   = (int)($_GET['folder_id'] ?? 0);
$current_folder_name = '根目錄';

// 從 session 把訊息拿出來
$delete_success        = $_SESSION['delete_success']        ?? '';
$delete_error          = $_SESSION['delete_error']          ?? '';
$create_folder_success = $_SESSION['create_folder_success'] ?? '';
$create_folder_error   = $_SESSION['create_folder_error']   ?? '';
unset($_SESSION['delete_success'], $_SESSION['delete_error'],
      $_SESSION['create_folder_success'], $_SESSION['create_folder_error']);

// === 麵包屑與第一層判斷 ===
$breadcrumbs = [
    ['name' => '根目錄', 'id' => 0],
];
$is_first_level_folder = false;

if ($current_folder_id > 0) {
    $q = $conn->prepare("
        SELECT id, folder_name, parent_id
        FROM folders
        WHERE user_id = ? AND id = ?
    ");
    $q->bind_param("ii", $user_id, $current_folder_id);
    $q->execute();
    $cur = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$cur) {
        header("Location: my_files.php");
        exit;
    }

    $current_folder_name = $cur['folder_name'];

    if ($cur['parent_id'] === null) {
        $is_first_level_folder = true;
    }

    // 如果有父資料夾，加進麵包屑
    if ($cur['parent_id'] !== null) {
        $parent_id = (int)$cur['parent_id'];
        $pq = $conn->prepare("
            SELECT id, folder_name
            FROM folders
            WHERE user_id = ? AND id = ?
        ");
        $pq->bind_param("ii", $user_id, $parent_id);
        $pq->execute();
        $parent = $pq->get_result()->fetch_assoc();
        $pq->close();

        if ($parent) {
            $breadcrumbs[] = [
                'name' => htmlspecialchars($parent['folder_name']),
                'id'   => $parent['id'],
            ];
        }
    }

    $breadcrumbs[] = [
        'name' => htmlspecialchars($current_folder_name),
        'id'   => $current_folder_id,
    ];
}

// === 查詢子資料夾 ===
if ($current_folder_id === 0) {
    $sf = $conn->prepare("
        SELECT id, folder_name
        FROM folders
        WHERE user_id = ? AND parent_id IS NULL
        ORDER BY folder_name ASC
    ");
    $sf->bind_param("i", $user_id);
} else {
    $sf = $conn->prepare("
        SELECT id, folder_name
        FROM folders
        WHERE user_id = ? AND parent_id = ?
        ORDER BY folder_name ASC
    ");
    $sf->bind_param("ii", $user_id, $current_folder_id);
}
$sf->execute();
$result_folders = $sf->get_result();
$sf->close();

// === 查詢檔案 ===
if ($current_folder_id === 0) {
    $sf2 = $conn->prepare("
        SELECT id, filename, filepath
        FROM files
        WHERE user_id = ? AND folder_id = 0
        ORDER BY filename ASC
    ");
    $sf2->bind_param("i", $user_id);
} else {
    $sf2 = $conn->prepare("
        SELECT id, filename, filepath
        FROM files
        WHERE user_id = ? AND folder_id = ?
        ORDER BY filename ASC
    ");
    $sf2->bind_param("ii", $user_id, $current_folder_id);
}
$sf2->execute();
$result_files = $sf2->get_result();
$sf2->close();

$total_items = $result_folders->num_rows + $result_files->num_rows;
$conn->close();

$show_create_folder_block = ($current_folder_id === 0) || $is_first_level_folder;
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>我的雲端硬碟 - <?= htmlspecialchars($current_folder_name) ?></title>
    <style>
        * {
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        body {
            margin: 0;
            padding: 40px 0;
            background: linear-gradient(135deg, #00c6ff, #0072ff);
        }
        .page-wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .card {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 18px 40px rgba(0,0,0,0.12);
            padding: 24px 28px 30px;
        }
        .top-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .nav-btn {
            border-radius: 999px;
            border: none;
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .nav-btn-primary {
            background: #1f8efa;
            color: #fff;
        }
        .nav-btn-secondary {
            background: #e5e9f0;
            color: #333;
        }
        .nav-btn a {
            color: inherit;
            text-decoration: none;
        }

        .title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .title-row h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
        }
        .title-row small {
            color: #777;
            font-size: 13px;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .btn {
            border-radius: 999px;
            border: none;
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-blue {
            background: #1f8efa;
            color: #fff;
        }
        .btn-gray {
            background: #f1f3f6;
            color: #333;
        }
        .btn-red-outline {
            border-radius: 999px;
            border: 1px solid #e74c3c;
            background: #fff;
            color: #e74c3c;
            padding: 4px 10px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-small-primary {
            border-radius: 999px;
            background: #1f8efa;
            color: #fff;
            border: none;
            padding: 4px 12px;
            font-size: 13px;
            cursor: pointer;
        }

        .section-header {
            background: #ffc400;
            color: #222;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .breadcrumb {
            font-size: 13px;
            color: #888;
            margin-bottom: 10px;
        }
        .breadcrumb a {
            color: #1f8efa;
            text-decoration: none;
        }
        .breadcrumb span.sep {
            margin: 0 4px;
        }

        .alert {
            padding: 8px 12px;
            border-radius: 10px;
            margin-bottom: 10px;
            font-size: 13px;
        }
        .alert-success {
            background: #e8f8f0;
            color: #2d8a4f;
        }
        .alert-error {
            background: #fdecea;
            color: #c0392b;
        }

        .new-folder-block {
            background: #f7f9fc;
            border-radius: 10px;
            padding: 10px 12px 12px;
            margin-bottom: 14px;
        }
        .new-folder-block label {
            display: block;
            font-size: 13px;
            margin-bottom: 6px;
        }
        .new-folder-input-row {
            display: flex;
            gap: 8px;
        }
        .new-folder-input-row input[type="text"] {
            flex: 1;
            padding: 6px 8px;
            border-radius: 8px;
            border: 1px solid #d0d7e2;
            font-size: 14px;
        }

        .item-list {
            border-radius: 12px;
            border: 1px solid #edf0f5;
            background: #fff;
            overflow: hidden;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1px solid #f1f3f6;
            font-size: 14px;
        }
        .item-row:last-child {
            border-bottom: none;
        }
        .item-left {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }
        .item-left a {
            color: #1f8efa;
            text-decoration: none;
            word-break: break-all;
        }
        .item-icon {
            font-size: 18px;
        }
        .item-right {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .empty-msg {
            font-size: 14px;
            color: #777;
            padding: 10px 0 4px;
        }

        @media (max-width: 640px) {
            .title-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            body {
                padding: 20px 0;
            }
        }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="card">

        <!-- 上方導覽按鈕：與「檔案上傳中心」同風格 -->
        <div class="top-nav">
            <button class="nav-btn nav-btn-primary">
                📁 <a href="my_files.php">我的雲端硬碟</a>
            </button>
            <button class="nav-btn nav-btn-secondary">
                👤 <a href="account.php">管理我的帳戶</a>
            </button>
        </div>

        <!-- 標題列 -->
        <div class="title-row">
            <div>
                <h1>📂 我的檔案中心</h1>
                <small>歡迎，<?= htmlspecialchars($username) ?> 👋</small>
            </div>
            <div class="toolbar">
                <a href="upload.php" class="btn btn-blue">⬆️ 上傳檔案</a>
                <a href="logout.php" class="btn btn-gray">🚪 登出</a>
            </div>
        </div>

        <!-- 訊息區 -->
        <?php if ($delete_success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($delete_success) ?></div>
        <?php endif; ?>
        <?php if ($delete_error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($delete_error) ?></div>
        <?php endif; ?>
        <?php if ($create_folder_success): ?>
            <div class="alert alert-success">📁 <?= htmlspecialchars($create_folder_success) ?></div>
        <?php endif; ?>
        <?php if ($create_folder_error): ?>
            <div class="alert alert-error">📁 <?= htmlspecialchars($create_folder_error) ?></div>
        <?php endif; ?>

        <!-- 當前資料夾標題 -->
        <div class="section-header">
            <span>📁 當前資料夾：<?= htmlspecialchars($current_folder_name) ?></span>
            <span style="font-size:12px;color:#555;">共 <?= $total_items ?> 個項目</span>
        </div>

        <!-- 麵包屑 -->
        <div class="breadcrumb">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php if ($index > 0): ?><span class="sep">›</span><?php endif; ?>
                <?php if ($index === count($breadcrumbs) - 1): ?>
                    <span><?= $crumb['name'] ?></span>
                <?php else: ?>
                    <a href="my_files.php?folder_id=<?= $crumb['id'] ?>"><?= $crumb['name'] ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- 新增資料夾（根目錄 & 第一層顯示，風格跟上傳頁類似） -->
        <?php if ($show_create_folder_block): ?>
            <div class="new-folder-block">
                <label>建立新資料夾（此層）</label>
                <form action="create_folder.php" method="post">
                    <div class="new-folder-input-row">
                        <input type="text" name="folder_name" placeholder="輸入資料夾名稱" required>
                        <input type="hidden" name="parent_id" value="<?= $current_folder_id ?>">
                        <button type="submit" class="btn-small-primary">建立</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- 檔案 / 資料夾列表 -->
        <?php if ($total_items === 0): ?>
            <div class="empty-msg">這個資料夾目前沒有任何檔案或子資料夾。</div>
        <?php else: ?>
            <div class="item-list">

                <!-- 資料夾們 -->
                <?php while ($folder = $result_folders->fetch_assoc()): ?>
                    <div class="item-row">
                        <div class="item-left">
                            <span class="item-icon">📁</span>
                            <a href="my_files.php?folder_id=<?= $folder['id'] ?>">
                                <?= htmlspecialchars($folder['folder_name']) ?>
                            </a>
                        </div>
                        <div class="item-right">
                            <a href="delete_item.php?type=folder&id=<?= $folder['id'] ?>"
                               class="btn-red-outline"
                               onclick="return confirm('確定要刪除這個資料夾？（其中檔案也會被一起刪除）');">
                                刪除
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>

                <!-- 檔案們 -->
                <?php while ($file = $result_files->fetch_assoc()): ?>
                    <div class="item-row">
                        <div class="item-left">
                            <span class="item-icon">📄</span>
                            <span style="word-break: break-all;">
                                <?= htmlspecialchars($file['filename']) ?>
                            </span>
                        </div>
                        <div class="item-right">
                            <a href="<?= htmlspecialchars($file['filepath']) ?>" download
                               class="btn-red-outline"
                               style="border-color:#1f8efa;color:#1f8efa;"
                            >下載</a>
                            <a href="delete_item.php?type=file&id=<?= $file['id'] ?>"
                               class="btn-red-outline"
                               onclick="return confirm('確定要刪除這個檔案？');">
                                刪除
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>

            </div>
        <?php endif; ?>

    </div>
</div>
</body>
</html>
