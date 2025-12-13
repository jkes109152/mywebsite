<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: account.php");
    exit;
}

$conn = new mysqli('db', 'root', '1024', 'cloud_drive');
$conn->set_charset("utf8");

$recaptcha_secret = '6LeaGXYrAAAAALUVfPQUxNN1mMvWPE7K7LRqkrDB';
$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    if (empty($recaptcha_response)) {
        $login_error = "請完成驗證";
    } else {
        $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}");
        $response_data = json_decode($verify);

        if (!$response_data->success) {
            $login_error = "驗證失敗，請再試一次";
        } elseif ($username === '' || $password === '') {
            $login_error = "請輸入帳號與密碼";
        } else {
            $stmt = $conn->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['is_admin'] = ($user['is_admin'] == 1);
                    header("Location: account.php");
                    exit;
                } else {
                    $login_error = "❌ 密碼錯誤";
                }
            } else {
                $login_error = "❌ 帳號不存在";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>登入雲端帳號</title>
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
      color: red;
      font-size: 14px;
      margin-bottom: 12px;
      text-align: center;
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
    <h2>登入雲端帳號</h2>

    <?php if ($login_error): ?>
      <div class="message"><?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="input-group">
        <label for="username">帳號</label>
        <input type="text" id="username" name="username" required>
      </div>

      <div class="input-group">
        <label for="password">密碼</label>
        <input type="password" id="password" name="password" required>
      </div>

      <div class="g-recaptcha" data-sitekey="6LeaGXYrAAAAAB9kehskeqvJE4osiy00T-6ouOp_"></div><br>

      <button type="submit" class="btn">登入</button>
    </form>

    <div class="bottom-link">
      還沒有帳號嗎？<a href="register.php">註冊</a>
    </div>
  </div>
</body>
</html>
