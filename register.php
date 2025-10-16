<?php
session_start();
require_once 'config.php';

// Eğer zaten giriş yapılmışsa yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$success = '';
$error = '';

// Kayıt işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validasyon
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Lütfen tüm alanları doldurun!';
    } elseif ($password !== $confirm_password) {
        $error = 'Şifreler eşleşmiyor!';
    } elseif (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır!';
    } else {
        try {
            // Kullanıcı adı ve email kontrolü
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $error = 'Bu kullanıcı adı veya e-posta zaten kullanımda!';
            } else {
                // Yeni registerUser fonksiyonunu kullan
                $user_id = registerUser($db, $username, $email, $password);
                
                // Session'a kaydet
                $_SESSION['temp_user_id'] = $user_id;
                $_SESSION['temp_email'] = $email;
                $_SESSION['temp_username'] = $username;
                
                // Başarılı kayıt - manual doğrulama sayfasına yönlendir
                header('Location: verify.php?email=' . urlencode($email) . '&success=Kayıt+başarılı!+Hesabınızı+doğrulamak+için+164913+kodunu+kullanın.+Veya+admin+onayı+bekleyin.');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Kayıt sırasında bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - Onvibes Drive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #8B5CF6;
            --secondary: #06B6D4;
            --accent: #10B981;
            --dark: #0F172A;
        }
        
        body {
            background: var(--dark);
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .register-container {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(10px);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 3rem;
            background: linear-gradient(45deg, var(--accent), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--primary);
            color: white;
            box-shadow: 0 0 0 0.2rem rgba(139, 92, 246, 0.25);
        }
        
        .btn {
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--accent);
            color: var(--accent);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid #f59e0b;
            color: #f59e0b;
        }
        
        .links {
            text-align: center;
            margin-top: 20px;
        }
        
        .links a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            height: 5px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }
        
        .verification-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid #3b82f6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .verification-info i {
            color: #3b82f6;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="logo">
                <i class="fas fa-cloud-upload-alt"></i>
                <h2 class="mt-3">Hesap Oluştur</h2>
            </div>
            
            <!-- Doğrulama Bilgilendirmesi -->
            <div class="verification-info">
                <i class="fas fa-shield-alt"></i>
                <p class="mb-0"><strong>2 Aşamalı Doğrulama</strong><br>
                <small>1. <strong>164913</strong> kodu ile hemen doğrulama<br>2. Admin onayı ile doğrulama</small></p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <input type="text" name="username" class="form-control" placeholder="Kullanıcı adı" required value="<?php echo htmlspecialchars($username ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <input type="email" name="email" class="form-control" placeholder="E-posta adresi" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Şifre" required id="password">
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <input type="password" name="confirm_password" class="form-control" placeholder="Şifre tekrar" required id="confirmPassword">
                    <div id="passwordMatch" class="small mt-1"></div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Kayıt Ol
                </button>
            </form>
            
            <div class="links">
                <p>Zaten hesabınız var mı? <a href="login.php">Giriş Yapın</a></p>
                <p><a href="index.php"><i class="fas fa-home"></i> Ana Sayfaya Dön</a></p>
            </div>
        </div>
    </div>

    <script>
        // Şifre güçlülük kontrolü
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            
            let strength = 0;
            
            if (password.length >= 6) strength += 1;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
            if (password.match(/\d/)) strength += 1;
            if (password.match(/[^a-zA-Z\d]/)) strength += 1;
            
            strengthBar.className = 'strength-bar';
            if (password.length === 0) {
                strengthBar.style.width = '0%';
            } else if (strength <= 1) {
                strengthBar.className += ' strength-weak';
            } else if (strength <= 2) {
                strengthBar.className += ' strength-medium';
            } else {
                strengthBar.className += ' strength-strong';
            }
        });
        
        // Şifre eşleşme kontrolü
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchText.textContent = '';
                matchText.style.color = '';
            } else if (password === confirmPassword) {
                matchText.textContent = '✓ Şifreler eşleşiyor';
                matchText.style.color = '#10b981';
            } else {
                matchText.textContent = '✗ Şifreler eşleşmiyor';
                matchText.style.color = '#ef4444';
            }
        });
    </script>
</body>
</html>
