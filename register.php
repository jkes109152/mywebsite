<?php
session_start();
$conn = new mysqli('db', 'root', '1024', 'cloud_drive');
$conn->set_charset("utf8");

$recaptcha_secret = '6LeaGXYrAAAAALUVfPQUxNN1mMvWPE7K7LRqkrDB';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    if (empty($recaptcha_response)) {
        $error = '請完成驗證';
    } else {
        $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}");
        $response_data = json_decode($verify);

        if (!$response_data->success) {
            $error = '驗證失敗，請再試一次';
        } elseif ($username === '' || $password === '') {
            $error = '帳號和密碼不可為空';
        } elseif ($password !== $password_confirm) {
            $error = '兩次密碼輸入不一致';
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = '帳號已存在，請使用其他帳號';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_insert = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt_insert->bind_param("ss", $username, $hashed_password);

                if ($stmt_insert->execute()) {
                    $success = '✅ 註冊成功，3 秒後跳轉至登入頁';
                    header("refresh:3; url=login.php");
                } else {
                    $error = '註冊失敗，請稍後再試';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>註冊雲端帳號</title>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <style>
    body {
      background-color: #f2f2f7;
      font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
    }
    .container {
      background: white;
      padding: 40px 32px;
      border-radius: 16px;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      margin-bottom: 24px;
      color: #1d1d1f;
    }
    .input-group {
      margin-bottom: 16px;
    }
    .input-group label {
      display: block;
      font-size: 14px;
      margin-bottom: 6px;
      color: #333;
    }
    .input-group input {
      width: 100%;
      padding: 10px;
      font-size: 15px;
      border: 1px solid #ccc;
      border-radius: 8px;
    }
    .btn {
      width: 100%;
      padding: 12px;
      font-size: 16px;
      background-color: #007aff;
      color: white;
      border: none;
      border-radius: 10px;
      cursor: pointer;
    }
    .btn:hover {
      background-color: #005ecb;
    }
    .message {
      font-size: 14px;
      margin-bottom: 12px;
      text-align: center;
    }
    .message.error {
      color: red;
    }
    .message.success {
      color: green;
    }
    .bottom-link {
      text-align: center;
      margin-top: 16px;
      font-size: 14px;
    }
    .bottom-link a {
      color: #007aff;
      text-decoration: none;
    }
    .bottom-link a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>註冊雲端帳號</h2>

    <?php if ($error): ?>
      <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php">
      <div class="input-group">
        <label for="username">帳號</label>
        <input type="text" id="username" name="username" required>
      </div>

      <div class="input-group">
        <label for="password">密碼</label>
        <input type="password" id="password" name="password" required>
      </div>

      <div class="input-group">
        <label for="password_confirm">確認密碼</label>
        <input type="password" id="password_confirm" name="password_confirm" required>
      </div>

      <div class="g-recaptcha" data-sitekey="6LeaGXYrAAAAAB9kehskeqvJE4osiy00T-6ouOp_"></div><br>

      <button type="submit" class="btn">立即註冊</button>
    </form>

    <div class="bottom-link">
      已經有帳號了嗎？<a href="login.php">登入</a>
    </div>
  </div>
</body>
</html>
