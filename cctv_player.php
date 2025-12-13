<?php
// cctv_player.php
session_start();

// ===== 1. 登入檢查 =====
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ===== 2. 管理員權限檢查 =====
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    http_response_code(403);
    echo "<!DOCTYPE html>
<html lang='zh-Hant'>
<head>
  <meta charset='UTF-8'>
  <title>監視器 - 權限不足</title>
  <style>
    body { font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
           background:#0f172a; color:#e5e7eb; display:flex;
           align-items:center; justify-content:center; height:100vh; margin:0; }
    .card { background:#020617; border:1px solid #1f2937; border-radius:16px;
            padding:32px; max-width:420px; text-align:center;
            box-shadow:0 20px 40px rgba(0,0,0,0.6); }
    h1 { margin-top:0; font-size:1.5rem; }
    a { color:#38bdf8; text-decoration:none; }
    a:hover { text-decoration:underline; }
  </style>
</head>
<body>
  <div class='card'>
    <h1>權限不足</h1>
    <p>此頁面僅限 <strong>管理員</strong> 存取。</p>
    <p><a href='index.php'>回首頁</a></p>
  </div>
</body>
</html>";
    exit();
}

// ===== 3. 判斷是否為內網 IP =====
function is_private_ip($ip) {
    // IPv4 only 簡單判斷
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }

    $long = ip2long($ip);

    $private_ranges = [
        ['0.0.0.0',       '0.255.255.255'],     // 某些設備會給 0.x
        ['10.0.0.0',      '10.255.255.255'],    // 10.0.0.0/8
        ['172.16.0.0',    '172.31.255.255'],    // 172.16.0.0/12
        ['192.168.0.0',   '192.168.255.255'],   // 192.168.0.0/16
        ['127.0.0.0',     '127.255.255.255'],   // loopback
    ];

    foreach ($private_ranges as $range) {
        $min = ip2long($range[0]);
        $max = ip2long($range[1]);
        if ($long >= $min && $long <= $max) {
            return true;
        }
    }
    return false;
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$isLan    = is_private_ip($clientIp);

// 內網 → 用 WebRTC (經由 Apache 反向代理 /cam1 到 127.0.0.1:8889/cam1)
// 外網 → 用 HLS (經由 /cam1-hls 到 127.0.0.1:8888/cam1/)
$webrtcUrl = "/cam1/";                 // 內網 WebRTC 播放頁
$hlsUrl    = "/cam1-hls/index.m3u8";   // 外網 HLS m3u8
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>家用監視器 - 管理員專用</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    :root {
      color-scheme: dark;
    }
    * { box-sizing:border-box; }
    body {
      margin:0;
      font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
      background:#020617;
      color:#e5e7eb;
      min-height:100vh;
      display:flex;
      flex-direction:column;
    }
    header {
      padding:16px 24px;
      border-bottom:1px solid #1f2937;
      display:flex;
      align-items:center;
      justify-content:space-between;
      backdrop-filter:blur(12px);
      background:rgba(15,23,42,0.9);
      position:sticky;
      top:0;
      z-index:10;
    }
    header .title {
      font-size:1.1rem;
      font-weight:600;
    }
    header .badge {
      font-size:0.8rem;
      padding:4px 10px;
      border-radius:999px;
      border:1px solid #22c55e33;
      color:#22c55e;
      background:rgba(22,163,74,0.15);
    }
    main {
      flex:1;
      display:flex;
      justify-content:center;
      align-items:center;
      padding:16px;
    }
    .player-card {
      width:100%;
      max-width:960px;
      background:radial-gradient(circle at top, #1f2937 0, #020617 55%, #000 100%);
      border-radius:24px;
      border:1px solid #1f2937;
      padding:16px;
      box-shadow:0 30px 80px rgba(0,0,0,0.8);
    }
    .player-header {
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:10px;
      padding:0 4px;
    }
    .player-title {
      font-size:0.95rem;
      font-weight:500;
      display:flex;
      gap:8px;
      align-items:center;
    }
    .status-dot {
      width:8px;
      height:8px;
      border-radius:50%;
      background:#22c55e;
      box-shadow:0 0 12px #22c55e;
    }
    .cam-name {
      opacity:0.8;
      font-size:0.8rem;
    }
    .tag {
      font-size:0.75rem;
      padding:2px 8px;
      border-radius:999px;
      border:1px solid #38bdf8;
      color:#38bdf8;
    }
    .player-frame {
      position:relative;
      border-radius:18px;
      overflow:hidden;
      border:1px solid #111827;
      background:#020617;
      aspect-ratio:16/9;
    }
    iframe, video {
      width:100%;
      height:100%;
      border:none;
      outline:none;
      background:black;
    }
    .player-footer {
      margin-top:10px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      font-size:0.8rem;
      opacity:0.7;
    }
    .pill {
      padding:2px 10px;
      border-radius:999px;
      border:1px solid #4b5563;
    }
    @media (max-width: 600px) {
      header { padding:12px 16px; }
      .player-card { border-radius:16px; padding:12px; }
    }
  </style>
<?php if (!$isLan): ?>
  <!-- 外網要用 HLS 播放器 -->
  <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<?php endif; ?>
</head>
<body>
  <header>
    <div class="title">家用監視器 · 管理員專用</div>
    <div class="badge">
      已登入管理員 ·
      <?php echo $isLan ? '內網 WebRTC 模式' : '外網 HLS 模式'; ?>
    </div>
  </header>

  <main>
    <div class="player-card">
      <div class="player-header">
        <div class="player-title">
          <span class="status-dot"></span>
          <span>即時影像</span>
          <span class="cam-name">(cam1)</span>
        </div>
        <div class="tag">
          Client IP：<?php echo htmlspecialchars($clientIp, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      </div>

      <div class="player-frame">
        <?php if ($isLan): ?>
          <!-- 內網：直接 iframe 內網 WebRTC 頁面（/cam1/ 反向代理到 MediaMTX WebRTC） -->
          <iframe
            src="<?php echo htmlspecialchars($webrtcUrl, ENT_QUOTES, 'UTF-8'); ?>"
            allow="camera; microphone; autoplay; fullscreen"
          ></iframe>
        <?php else: ?>
          <!-- 外網：用 HLS -->
          <video id="camPlayer" controls autoplay muted playsinline></video>
        <?php endif; ?>
      </div>

      <div class="player-footer">
        <div class="pill">解析度：1920×1080 · 約 20fps</div>
        <div>此畫面僅限管理員帳號觀看</div>
      </div>
    </div>
  </main>

<?php if (!$isLan): ?>
  <script>
    (function () {
      const video = document.getElementById('camPlayer');
      const src   = <?php echo json_encode($hlsUrl); ?>;

      if (!video) return;

      if (window.Hls && Hls.isSupported()) {
        const hls = new Hls();
        hls.loadSource(src);
        hls.attachMedia(video);
      } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        // Safari / iOS
        video.src = src;
      } else {
        video.controls = false;
        video.insertAdjacentHTML('afterend',
          "<div style='padding:12px 4px;font-size:0.85rem;color:#f97373;'>"+
          "此瀏覽器不支援 HLS 播放，請改用較新的 Chrome / Edge / Safari。"+
          "</div>"
        );
      }
    })();
  </script>
<?php endif; ?>
</body>
</html>
