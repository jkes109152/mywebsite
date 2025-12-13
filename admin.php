<?php
session_start();

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli('db', 'root', '1024', 'cloud_drive');
if ($conn->connect_error) {
    die("資料庫連線失敗：" . $conn->connect_error);
}
$conn->set_charset("utf8");

$users = $conn->query("SELECT id, username, is_admin FROM users");
if (!$users) {
    die("查詢 users 失敗：" . $conn->error);
}

$files = $conn->query("SELECT files.id, files.filename, files.filepath, users.username AS owner FROM files JOIN users ON files.user_id = users.id");
if (!$files) {
    die("查詢 files 失敗：" . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>管理員控制台</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
body {
  background-color: #f7f9fc;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  color: #2d3748;
}

.card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: none;
}

.btn-primary, .btn-success, .btn-warning, .btn-secondary, .btn-danger {
  border-radius: 8px;
  font-weight: 500;
}

.btn-primary {
  background-color: #4a90e2;
  border-color: #4a90e2;
}

.btn-primary:hover {
  background-color: #357ab7;
}

.btn-success {
  background-color: #48bb78;
  border-color: #48bb78;
}

.btn-success:hover {
  background-color: #38a169;
}

.btn-warning {
  background-color: #ed8936;
  border-color: #ed8936;
}

.btn-warning:hover {
  background-color: #dd6b20;
}

.btn-secondary {
  background-color: #a0aec0;
  border-color: #a0aec0;
}

.btn-secondary:hover {
  background-color: #718096;
}

.btn-danger {
  background-color: #f56565;
  border-color: #f56565;
}

.btn-danger:hover {
  background-color: #e53e3e;
}

.btn-outline-primary {
  border-radius: 8px;
  border-color: #4a90e2;
  color: #4a90e2;
}

.btn-outline-primary:hover {
  background-color: #4a90e2;
  color: #fff;
}

.card-title {
  font-size: 1.1rem;
  font-weight: 600;
}

.card-text {
  font-size: 0.9rem;
  color: #4a5568;
}

h2, h4 {
  font-weight: 600;
  color: #2d3748;
}

.container {
  max-width: 720px;
}

.btn {
  padding: 0.4rem 0.75rem;
}

  </style>
</head>
<body>
<div class="container py-4">
  <h2 class="text-center mb-4">👑 管理員控制台</h2>

  <div class="mb-5">
    <h4 class="mb-3">👥 使用者管理</h4>
    <?php if ($users->num_rows > 0): ?>
      <?php while ($user = $users->fetch_assoc()): ?>
        <div class="card mb-3 p-3">
          <h5 class="card-title mb-2"><?= htmlspecialchars($user['username']) ?> (ID: <?= $user['id'] ?>)</h5>
          <p class="card-text mb-3">角色：<?= $user['is_admin'] ? '管理員' : '一般使用者' ?></p>
          <div class="d-flex flex-wrap gap-2">
            <a href="toggle_admin.php?id=<?= $user['id'] ?>" class="btn btn-sm <?= $user['is_admin'] ? 'btn-warning' : 'btn-success' ?>">
              <?= $user['is_admin'] ? '降級為使用者' : '升級為管理員' ?>
            </a>
            <a href="reset_password.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-secondary">重設密碼</a>
            <a href="delete_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('確定刪除此使用者？')">刪除</a>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="text-muted">目前沒有使用者資料。</p>
    <?php endif; ?>
  </div>

  <div>
    <h4 class="mb-3">📁 所有檔案</h4>
    <?php if ($files->num_rows > 0): ?>
      <?php while ($file = $files->fetch_assoc()): ?>
        <div class="card mb-3 p-3">
          <h5 class="card-title mb-2"><?= htmlspecialchars($file['filename']) ?></h5>
          <p class="card-text mb-3">擁有者：<?= htmlspecialchars($file['owner']) ?></p>
          <div class="d-flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars($file['filepath']) ?>" class="btn btn-sm btn-primary" target="_blank">預覽</a>
            <a href="<?= htmlspecialchars($file['filepath']) ?>" class="btn btn-sm btn-success" download>下載</a>
            <a href="admin_delete_file.php?id=<?= $file['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('確定刪除此檔案？')">刪除</a>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="text-muted">目前沒有檔案資料。</p>
    <?php endif; ?>
  </div>

  <div class="text-center mt-5">
    <a href="account.php" class="btn btn-outline-primary">回到帳戶管理</a>
  </div>
</div>
</body>
</html>
