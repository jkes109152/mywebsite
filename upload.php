<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db_host = 'localhost';
$db_user = 'root'; 
$db_pass = '1024'; 
$db_name = 'cloud_drive';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error); 
}
$conn->set_charset("utf8");

$user_id = $_SESSION['user_id'];
$upload_error = '';
$upload_success = false;
$new_folder_name = ''; // 用於回顯新創建的資料夾名稱

// --- 取得所有第一層資料夾 ---
$parent_folders = [];
$stmt_parents = $conn->prepare("SELECT id, folder_name FROM folders WHERE user_id = ? AND parent_id IS NULL ORDER BY folder_name");
$stmt_parents->bind_param("i", $user_id);
$stmt_parents->execute();
$result_parents = $stmt_parents->get_result();
while ($row = $result_parents->fetch_assoc()) {
    $parent_folders[$row['id']] = $row['folder_name'];
}
$stmt_parents->close();


// --- 處理資料夾建立 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_parent_folder']) && !empty($_POST['new_parent_folder'])) {
    $new_folder_name = trim($_POST['new_parent_folder']);
    
    $stmt_insert = $conn->prepare("INSERT INTO folders (user_id, parent_id, folder_name) VALUES (?, NULL, ?)");
    $stmt_insert->bind_param("is", $user_id, $new_folder_name);
    
    if ($stmt_insert->execute()) {
        // 重定向以避免重複提交，並刷新資料夾列表
        header("Location: upload.php?folder_created=" . urlencode($new_folder_name));
        exit;
    } else {
        $upload_error = "❌ 資料夾建立失敗：" . $stmt_insert->error;
    }
    $stmt_insert->close();
}


// --- 處理檔案上傳 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && isset($_POST['target_folder_id'])) {
    $file = $_FILES['file'];
    $target_folder_id = (int)$_POST['target_folder_id']; // 檔案要儲存的目標資料夾 ID

    if ($file['error'] === UPLOAD_ERR_OK) {
        
        // 1. 取得目標資料夾路徑資訊
        $folderPath = 'uploads/'; // 根目錄
        $targetFolderIdForDB = null; // 預設為 NULL (根目錄)
        
        if ($target_folder_id > 0) {
            $stmt_folder = $conn->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ?");
            $stmt_folder->bind_param("ii", $target_folder_id, $user_id);
            $stmt_folder->execute();
            if ($stmt_folder->fetch()) {
                $targetFolderIdForDB = $target_folder_id;
                $folderPath .= $target_folder_id . '/'; // 使用資料夾 ID 作為實體路徑的一部分
            }
            $stmt_folder->close();
        }

        $uploadDir = __DIR__ . '/' . $folderPath;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // 避免檔名衝突，檔名前加上時間戳
$filename = basename($file['name']);
// 不需要 $safe_filename 過濾，直接使用原始檔名，但加上時間戳前綴以確保唯一性
$prefix = time() . '_'; 

$targetPath = $uploadDir . $prefix . $filename;
$relativePath = $folderPath . $prefix . $filename; // 相對路徑存入資料庫
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            
            // 存入資料庫：folder_id 替換了原本的 filepath 欄位
            $stmt = $conn->prepare("INSERT INTO files (user_id, folder_id, filename, filepath) VALUES (?, ?, ?, ?)");
            // 注意這裡 folder_id 是 INT/NULL，要使用 "i" 或 "s" 根據您的 PHP 版本和 MySQLi 設定來決定
            // 這裡假設 MySQLi 能處理 NULL，我們使用 bind_param("iiss", ...)
            
            if ($targetFolderIdForDB === null) {
                // 如果目標是根目錄 (folder_id=NULL)
                $stmt = $conn->prepare("INSERT INTO files (user_id, folder_id, filename, filepath) VALUES (?, NULL, ?, ?)");
                $stmt->bind_param("iss", $user_id, $filename, $relativePath);
            } else {
                // 如果目標是特定資料夾 (folder_id > 0)
                $stmt = $conn->prepare("INSERT INTO files (user_id, folder_id, filename, filepath) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $user_id, $targetFolderIdForDB, $filename, $relativePath);
            }


            if ($stmt->execute()) {
                $upload_success = true;
            } else {
                $upload_error = "資料庫儲存失敗: " . $stmt->error;
                unlink($targetPath);
            }
        } else {
            $upload_error = "檔案移動失敗";
        }
    } else {
        $upload_error = "檔案上傳錯誤，錯誤代碼：" . $file['error'];
    }
}

// 檢查是否有資料夾建立成功的訊息 (來自重定向)
if (isset($_GET['folder_created'])) {
    $new_folder_name = htmlspecialchars($_GET['folder_created']);
    $upload_success = true;
    $upload_success_msg = "✅ 資料夾 **{$new_folder_name}** 建立成功！";
}

?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8" />
  <title>上傳中心與資料夾管理</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    body {
      background: linear-gradient(to right, #43cea2, #185a9d);
      min-height: 100vh;
      padding: 40px;
      color: #fff;
    }
    .container {
      background: #fff;
      border-radius: 12px;
      padding: 30px;
      color: #333;
      box-shadow: 0 10px 20px rgba(0,0,0,0.15);
    }
    h2 {
      font-weight: 700;
    }
    #progressWrapper {
      display: none;
      margin-top: 10px;
    }
  </style>
</head>
<body>
<div class="container">

  <div class="mb-4 d-flex gap-2">
    <a href="my_files.php" class="btn btn-info">📁 我的雲端硬碟</a>
    <a href="account.php" class="btn btn-secondary">👤 管理我的帳戶</a>
  </div>

  <h2>🚀 檔案上傳中心</h2>

  <?php if ($upload_error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($upload_error) ?></div>
  <?php elseif ($upload_success): ?>
    <div class="alert alert-success">
        <?= isset($upload_success_msg) ? $upload_success_msg : '✅ 檔案上傳成功！' ?>
    </div>
  <?php endif; ?>

  <div class="card mb-4 shadow-sm">
    <div class="card-header bg-warning text-dark">
      <h5 class="mb-0">📂 建立新資料夾 (第一層)</h5>
    </div>
    <div class="card-body">
      <form method="POST">
        <div class="input-group">
          <input type="text" name="new_parent_folder" class="form-control" placeholder="輸入資料夾名稱" required>
          <button type="submit" class="btn btn-warning">建立</button>
        </div>
      </form>
    </div>
  </div>


  <form id="uploadForm" action="upload.php" method="POST" enctype="multipart/form-data" class="border rounded p-4 bg-light mb-5">
    <h5 class="mb-3">上傳檔案至指定位置</h5>
    
    <div class="mb-3 row">
        <div class="col-md-6">
            <label for="parent_folder" class="form-label">選擇第一層資料夾</label>
            <select name="parent_folder" id="parent_folder" class="form-select">
                <option value="0">--- 根目錄 ---</option>
                <?php foreach ($parent_folders as $id => $name): ?>
                    <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="target_folder_id" class="form-label">選擇第二層資料夾 (或留空)</label>
            <select name="target_folder_id" id="target_folder_id" class="form-select">
                <option value="0">--- 根目錄 ---</option>
            </select>
        </div>
    </div>
    
    <div class="mb-3">
      <label for="file" class="form-label">選擇要上傳的檔案 (不限制大小和類型)</label>
      <input type="file" name="file" id="file" class="form-control" required />
    </div>
    
    <div id="progressWrapper">
      <div class="progress">
        <div id="progressBar" class="progress-bar progress-bar-striped bg-success" role="progressbar" style="width: 0%;">0%</div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary mt-3">上傳檔案</button>
  </form>

</div>

<script>
$(document).ready(function() {
    const parentFolderSelect = $('#parent_folder');
    const targetFolderSelect = $('#target_folder_id');

    // 依據選擇的第一層資料夾載入第二層資料夾
    function loadSubFolders(parentId) {
        if (parentId === '0') {
            // 如果選中根目錄，目標資料夾只有根目錄
            targetFolderSelect.html('<option value="0">--- 根目錄 ---</option>');
            return;
        }

        $.ajax({
            url: 'fetch_subfolders.php', // *** 注意: 需要新增這個檔案 ***
            type: 'GET',
            data: { parent_id: parentId },
            dataType: 'json',
            success: function(data) {
                let options = '<option value="' + parentId + '">-- [' + parentFolderSelect.find('option:selected').text() + '] 本層 --</option>'; // 允許上傳到第一層
                options += '<option value="0">--- 根目錄 ---</option>'; // 允許選回根目錄
                
                data.forEach(function(folder) {
                    options += '<option value="' + folder.id + '"> ' + folder.folder_name + ' (第二層)</option>';
                });
                targetFolderSelect.html(options);
            },
            error: function() {
                alert('載入子資料夾失敗');
                targetFolderSelect.html('<option value="0">--- 根目錄 ---</option>');
            }
        });
    }

    // 綁定事件：當第一層資料夾改變時
    parentFolderSelect.on('change', function() {
        loadSubFolders($(this).val());
    });
    
    // 頁面載入時先載入一次 (如果需要預選)
    loadSubFolders(parentFolderSelect.val());


    // 檔案上傳進度條邏輯
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const form = e.target;
      const formData = new FormData(form);
      const xhr = new XMLHttpRequest();
      
      // 確保將目標資料夾 ID 傳入
      // 如果 parent_folder != 0 但 target_folder_id 也選了 0，則上傳到根目錄
      // 如果 target_folder_id 選擇了某個 ID > 0，則上傳到該 ID
      
      // 我們只提交 target_folder_id，因為它是最終目的地
      formData.append('target_folder_id', targetFolderSelect.val()); 

      const progressWrapper = document.getElementById('progressWrapper');
      const progressBar = document.getElementById('progressBar');

      progressWrapper.style.display = 'block';
      progressBar.style.width = '0%';
      progressBar.innerText = '0%';

      // 檢查檔案是否已選
      if (!document.getElementById('file').files.length) {
          alert('請選擇檔案');
          progressWrapper.style.display = 'none';
          return;
      }

      xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
          const percent = Math.round((e.loaded / e.total) * 100);
          progressBar.style.width = percent + '%';
          progressBar.innerText = percent + '%';
        }
      });

      xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
          if (xhr.status === 200) {
            // 上傳成功後重新載入頁面
            window.location.reload();
          } else {
            alert('上傳失敗，請檢查伺服器響應。');
            progressWrapper.style.display = 'none';
          }
        }
      };

      xhr.open('POST', form.action);
      xhr.send(formData);
    });
});
</script>
</body>
</html>