<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Hata ve başarı mesajlarını kontrol et
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Cookie'den kullanıcı bilgilerini al
$remembered_email = '';
$remembered_password = '';
$is_remembered = false;

if (isset($_COOKIE['remembered_email']) && isset($_COOKIE['remembered_password'])) {
    $remembered_email = $_COOKIE['remembered_email'];
    $remembered_password = $_COOKIE['remembered_password'];
    $is_remembered = true;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - OnVibes Drive</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-light: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --success-color: #10b981;
        }
        
        body {
            background: var(--dark-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-container {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
        }
        
        .login-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }
        
        .login-header {
            background: var(--primary-gradient);
            border-radius: 16px 16px 0 0;
            padding: 2rem;
            text-align: center;
            color: white;
        }
        
        .brand-logo {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-light);
            padding: 12px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: #667eea;
            color: var(--text-light);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .form-control::placeholder {
            color: var(--text-muted);
        }
        
        .btn-login {
            background: var(--primary-gradient);
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: var(--text-muted);
            margin: 1.5rem 0;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border-color);
        }
        
        .divider::before {
            margin-right: .5em;
        }
        
        .divider::after {
            margin-left: .5em;
        }
        
        .alert-custom {
            border-radius: 8px;
            border: none;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border-left: 4px solid #ef4444;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #86efac;
            border-left: 4px solid #22c55e;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-muted);
        }
        
        .login-footer a {
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .login-footer a:hover {
            color: #764ba2;
        }
        
        .input-group-icon {
            position: relative;
        }
        
        .input-group-icon .form-control {
            padding-left: 45px;
            padding-right: 45px;
        }
        
        .input-group-icon .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            z-index: 5;
            transition: color 0.3s ease;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            z-index: 5;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .password-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-light);
        }
        
        .password-toggle.active {
            background: var(--primary-gradient);
            color: white;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .form-check-label {
            color: var(--text-muted);
            cursor: pointer;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container fade-in">
            <!-- Hata Mesajları -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-custom mb-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Başarı Mesajları -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-custom mb-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <div><?php echo htmlspecialchars($success); ?></div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="login-card">
                <!-- Header -->
                <div class="login-header">
                    <div class="brand-logo">
                        <i class="fas fa-cloud-music"></i>
                    </div>
                    <h2 class="mb-0">OnVibes Drive</h2>
                    <p class="mb-0 opacity-75">Dosyalarınız güvende</p>
                </div>
                
                <!-- Login Form -->
                <div class="card-body p-4">
                    <form method="POST" action="auth.php" id="loginForm">
                        <!-- E-posta -->
                        <div class="mb-3 input-group-icon">
                            <i class="bi bi-envelope-fill input-icon"></i>
                            <input type="email" 
                                   class="form-control" 
                                   name="email" 
                                   placeholder="E-posta adresiniz" 
                                   required
                                   value="<?php echo htmlspecialchars($remembered_email ?: (isset($_GET['email']) ? $_GET['email'] : '')); ?>"
                                   id="email">
                        </div>
                        
                        <!-- Şifre -->
                        <div class="mb-3 input-group-icon">
                            <i class="bi bi-lock-fill input-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   id="password"
                                   name="password" 
                                   placeholder="Şifreniz" 
                                   required
                                   value="<?php echo htmlspecialchars($remembered_password); ?>"
                                   autocomplete="current-password">
                            <button type="button" class="password-toggle" id="togglePassword" title="Şifreyi göster">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        
                        <!-- Beni Hatırla ve Şifremi Unuttum -->
                        <div class="mb-3 remember-me">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember" <?php echo $is_remembered ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="remember">
                                    Beni hatırla
                                </label>
                            </div>
                            <a href="forgot_password.php" class="text-muted small">
                                Şifremi unuttum?
                            </a>
                        </div>
                        
                        <!-- Giriş Butonu -->
                        <button type="submit" class="btn btn-login w-100 text-white mb-3">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            <span class="btn-text">Giriş Yap</span>
                        </button>
                        
                        <div class="divider">
                            <span class="small">HESABINIZ YOK MU?</span>
                        </div>
                        
                        <!-- Kayıt Ol Butonu -->
                        <a href="register.php" class="btn btn-outline-light w-100">
                            <i class="bi bi-person-plus me-2"></i>
                            Yeni Hesap Oluştur
                        </a>
                    </form>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="login-footer">
                <p class="small mb-0">
                    © 2024 OnVibes Drive. Tüm hakları saklıdır.
                    <br>
                    <a href="<?php echo SITE_URL; ?>" class="small">Ana Sayfa</a> • 
                    <a href="#" class="small">Yardım</a> • 
                    <a href="#" class="small">Gizlilik</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Şifre göster/gizle - GÜNCELLENMİŞ DAHA ŞIK VERSİYON
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const isPassword = passwordInput.type === 'password';
            
            // Input tipini değiştir
            passwordInput.type = isPassword ? 'text' : 'password';
            
            // İkonu değiştir
            const icon = this.querySelector('i');
            if (isPassword) {
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
                this.classList.add('active');
                this.title = 'Şifreyi gizle';
            } else {
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
                this.classList.remove('active');
                this.title = 'Şifreyi göster';
            }
            
            // Hover efekti
            this.style.transform = 'translateY(-50%) scale(1.1)';
            setTimeout(() => {
                this.style.transform = 'translateY(-50%) scale(1)';
            }, 150);
        });

        // Input focus efektleri
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            const icon = input.parentElement.querySelector('.input-icon');
            
            input.addEventListener('focus', function() {
                icon.style.color = '#667eea';
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                icon.style.color = 'var(--text-muted)';
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
            
            // Sayfa yüklendiğinde değer varsa focused class ekle
            if (input.value) {
                input.parentElement.classList.add('focused');
            }
        });

        // Form gönderim animasyonu
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const btnText = submitBtn.querySelector('.btn-text');
            const originalText = btnText.textContent;
            
            btnText.textContent = 'Giriş Yapılıyor...';
            submitBtn.disabled = true;
            
            // İkonu değiştir
            const icon = submitBtn.querySelector('i');
            icon.className = 'bi bi-arrow-repeat spinner me-2';
        });

        // Sayfa yüklendiğinde hatırlanan bilgiler varsa inputları işaretle
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($is_remembered): ?>
                // Hatırlanan bilgiler varsa inputlara focused class ekle
                document.getElementById('email').parentElement.classList.add('focused');
                document.getElementById('password').parentElement.classList.add('focused');
                
                // Kullanıcıya bilgi göster
                console.log('Hatırlanan bilgiler yüklendi');
            <?php endif; ?>
            
            // Input değişikliklerini dinle
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.value) {
                        this.parentElement.classList.add('focused');
                    } else {
                        this.parentElement.classList.remove('focused');
                    }
                });
            });
        });

        // Enter tuşu ile submit
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const focused = document.activeElement;
                if (focused && focused.form && focused.form.id === 'loginForm') {
                    document.getElementById('loginForm').dispatchEvent(new Event('submit'));
                }
            }
        });
    </script>
</body>
</html>
