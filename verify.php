<?php
session_start();
require_once 'config.php';

$email = $_GET['email'] ?? '';
$success = $_GET['success'] ?? '';
$warning = $_GET['warning'] ?? '';
$error = '';

// MANUEL KOD DOĞRULAMA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_code'])) {
    $manual_code = $_POST['manual_code'];
    $email = $_POST['email'];
    
    if (verifyManualCode($db, $email, $manual_code)) {
        $success = "🎉 Hesabınız başarıyla doğrulandı! Giriş yapabilirsiniz.";
        
        // Session'ı temizle ve login sayfasına yönlendir
        unset($_SESSION['temp_user_id'], $_SESSION['temp_email'], $_SESSION['temp_username']);
        header('Location: login.php?success=' . urlencode($success));
        exit;
    } else {
        $error = "❌ Geçersiz doğrulama kodu! Doğru kod: 164913";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesap Doğrulama - OnVibes</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); padding: 40px; max-width: 500px; width: 100%; text-align: center; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        h1 { color: #1F2937; margin-bottom: 20px; }
        .success { color: #10B981; }
        .error { color: #EF4444; }
        .warning { color: #F59E0B; }
        .btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; border: none; cursor: pointer; margin: 5px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .manual-verify { margin-top: 30px; padding-top: 20px; border-top: 1px solid #E5E7EB; }
        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; margin-bottom: 5px; color: #374151; font-weight: 600; }
        input[type="text"] { width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 16px; }
        input[type="text"]:focus { outline: none; border-color: #667eea; }
        .info-box { background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .code-display { font-size: 2rem; font-weight: bold; color: #667eea; background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; letter-spacing: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔐</div>
        <h1>Hesap Doğrulama</h1>
        
        <?php if ($success): ?>
            <p class="success"><?= $success ?></p>
        <?php endif; ?>
        
        <?php if ($warning): ?>
            <p class="warning"><?= $warning ?></p>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>2 Doğrulama Seçeneği:</h3>
            <p><strong>1. Hemen Doğrulama:</strong> Aşağıdaki kodu kullanarak hemen aktif et</p>
            <p><strong>2. Admin Onayı:</strong> Admin hesabınızı onaylayana kadar bekleyin</p>
        </div>

        <!-- KOD GÖSTERİMİ -->
        <div class="code-display">164913</div>
        <p><small>Yukarıdaki kodu kullanarak hesabınızı hemen doğrulayabilirsiniz</small></p>

        <!-- MANUEL DOĞRULAMA FORMU -->
        <div class="manual-verify">
            <h3>Hemen Doğrulama</h3>
            <p>Doğrulama kodunu girerek hesabınızı hemen aktif edin:</p>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">E-posta Adresiniz:</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required readonly style="background: #f9fafb;">
                </div>
                <div class="form-group">
                    <label for="manual_code">Doğrulama Kodu:</label>
                    <input type="text" id="manual_code" name="manual_code" placeholder="164913" required>
                </div>
                <button type="submit" class="btn">Hesabımı Doğrula</button>
            </form>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="login.php" class="btn">Giriş Sayfasına Git</a>
            <a href="index.php" class="btn" style="background: #6B7280;">Ana Sayfa</a>
        </div>
    </div>
</body>
</html>
