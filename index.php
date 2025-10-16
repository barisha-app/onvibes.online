<?php
session_start();
require_once 'config.php';

// Kullanıcı giriş kontrolü
$is_logged_in = isset($_SESSION['user_id']);
$user = null;
if ($is_logged_in) {
    $stmt = $db->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

// Herkese açık dosyaları getir
try {
    $public_files = $db->query("
        SELECT f.*, u.username 
        FROM files f 
        LEFT JOIN users u ON f.user_id = u.id 
        WHERE f.is_public = 1 
        ORDER BY f.created_at DESC 
        LIMIT 12
    ")->fetchAll();
} catch (PDOException $e) {
    $public_files = [];
}

// Toplam istatistikler
try {
    $total_public_files = $db->query("SELECT COUNT(*) FROM files WHERE is_public = 1")->fetchColumn();
} catch (PDOException $e) {
    $total_public_files = 0;
}

try {
    $total_downloads = $db->query("SELECT SUM(download_count) FROM files")->fetchColumn();
} catch (PDOException $e) {
    $total_downloads = 0;
}

try {
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (PDOException $e) {
    $total_users = 0;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onvibes Drive - Dosya Paylaşım Platformu</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #8B5CF6;
            --secondary: #06B6D4;
            --success: #10B981;
            --dark: #0F172A;
            --light: #f8f9fa;
        }
        
        body {
            background: var(--dark);
            color: white;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding-top: 70px;
        }
        
        /* Header */
        .navbar {
            background: rgba(15, 23, 42, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            height: 70px;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            background: linear-gradient(45deg, var(--success), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(15, 23, 42, 0.95));
            padding: 100px 0 80px;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, white, #e0e0e0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .hero p {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            max-width: 600px;
            margin: 0 auto 2rem;
        }
        
        /* Butonlar */
        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.4);
        }
        
        /* İstatistikler */
        .stats {
            padding: 60px 0;
            background: rgba(255, 255, 255, 0.02);
        }
        
        .stat-card {
            text-align: center;
            padding: 30px 20px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--success);
            margin-bottom: 0.5rem;
        }
        
        .stat-text {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        /* Dosya Grid */
        .files-section {
            padding: 80px 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-title h2 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: white;
        }
        
        .section-title p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
        }
        
        .file-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .file-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .file-preview {
            height: 160px;
            background: rgba(255, 255, 255, 0.02);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .file-info {
            padding: 20px;
        }
        
        .file-name {
            font-weight: 600;
            color: white;
            margin-bottom: 10px;
            font-size: 1rem;
            line-height: 1.4;
        }
        
        .file-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .file-uploader {
            color: var(--success);
            font-weight: 500;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
            flex: 1;
            text-align: center;
        }
        
        .btn-download {
            background: var(--primary);
            color: white;
            border: none;
        }
        
        .btn-download:hover {
            background: var(--secondary);
            color: white;
        }
        
        .btn-view {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-view:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        /* Özellikler */
        .features {
            padding: 80px 0;
            background: rgba(255, 255, 255, 0.02);
        }
        
        .feature-item {
            text-align: center;
            padding: 30px 20px;
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .feature-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: white;
        }
        
        .feature-desc {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
        }
        
        /* Footer */
        footer {
            background: rgba(15, 23, 42, 0.95);
            padding: 50px 0 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .footer-links h5 {
            color: white;
            margin-bottom: 20px;
            font-size: 1.1rem;
        }
        
        .footer-links ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-links li {
            margin-bottom: 8px;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: var(--success);
        }
        
        .copyright {
            text-align: center;
            padding-top: 30px;
            margin-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
        }
        
        /* Resim görüntüleyici modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
        }
        
        .image-modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            margin-top: 2%;
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        
        /* Mobil Uyumluluk */
        @media (max-width: 768px) {
            body {
                padding-top: 60px;
            }
            
            .navbar {
                height: 60px;
            }
            
            .hero {
                padding: 60px 0 40px;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .section-title h2 {
                font-size: 1.8rem;
            }
            
            .file-preview {
                height: 140px;
            }
            
            .file-actions {
                flex-direction: column;
            }
            
            .stats, .files-section, .features {
                padding: 40px 0;
            }
        }
        
        @media (max-width: 576px) {
            .hero h1 {
                font-size: 1.8rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .file-card {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-cloud-arrow-up-fill me-2"></i>
                ONVIBES DRIVE
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="files.php"><i class="bi bi-files me-2"></i>Dosyalarım</a></li>
                                <?php if ($user['role'] == 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin.php"><i class="bi bi-shield-lock me-2"></i>Admin</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Çıkış Yap</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn btn-outline-light btn-sm me-2" href="login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Giriş Yap
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary btn-sm" href="register.php">
                                <i class="bi bi-person-plus me-1"></i>Kayıt Ol
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1>Dosya Paylaşımı Artık Çok Kolay</h1>
                    <p>Onvibes Drive ile dosyalarınızı güvenle saklayın, kolayca paylaşın. Hızlı, güvenli ve sınırsız dosya transferi.</p>
                    
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <?php if ($is_logged_in): ?>
                            <a href="files.php" class="btn btn-primary">
                                <i class="bi bi-cloud-arrow-up me-2"></i>Dosya Yükle
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-light">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                        <?php else: ?>
                            <a href="register.php" class="btn btn-primary">
                                <i class="bi bi-person-plus me-2"></i>Kayıt Ol ve Hemen Başla
                            </a>
                            <a href="login.php" class="btn btn-outline-light">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Giriş Yap
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- İstatistikler -->
    <section class="stats">
        <div class="container">
            <div class="row">
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_public_files; ?></div>
                        <div class="stat-text">Paylaşılan Dosya</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_downloads ?: '0'; ?></div>
                        <div class="stat-text">Toplam İndirme</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_users; ?></div>
                        <div class="stat-text">Kayıtlı Kullanıcı</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number">%100</div>
                        <div class="stat-text">Güvenli</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Herkese Açık Dosyalar -->
    <section class="files-section">
        <div class="container">
            <div class="section-title">
                <h2>Son Paylaşılan Dosyalar</h2>
                <p>Topluluk tarafından paylaşılan en yeni dosyalar</p>
            </div>
            
            <?php if (!empty($public_files)): ?>
                <div class="row">
                    <?php foreach ($public_files as $file): ?>
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="file-card">
                                <div class="file-preview">
                                    <?php echo getFilePreview($file['file_path'], $file['file_type']); ?>
                                </div>
                                <div class="file-info">
                                    <div class="file-name"><?php echo htmlspecialchars($file['filename']); ?></div>
                                    <div class="file-meta">
                                        <span class="file-uploader">@<?php echo htmlspecialchars($file['username']); ?></span>
                                        <span><?php echo formatFileSize($file['file_size']); ?></span>
                                    </div>
                                    <div class="file-actions">
                                        <a href="download.php?id=<?php echo $file['id']; ?>" class="btn-sm btn-download">
                                            <i class="bi bi-download me-1"></i>İndir
                                        </a>
                                        <a href="view.php?id=<?php echo $file['id']; ?>" class="btn-sm btn-view">
                                            <i class="bi bi-play-circle me-1"></i>Görüntüle
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center mt-4">
                    <a href="browse.php" class="btn btn-outline-light">
                        <i class="bi bi-grid me-2"></i>Tüm Dosyaları Gör
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-cloud-slash display-1 text-muted mb-3"></i>
                    <h4 class="text-muted">Henüz paylaşılan dosya yok</h4>
                    <p class="text-muted">İlk dosyayı paylaşan siz olun!</p>
                    <?php if ($is_logged_in): ?>
                        <a href="files.php" class="btn btn-primary mt-3">
                            <i class="bi bi-cloud-arrow-up me-2"></i>Dosya Yükle
                        </a>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-primary mt-3">
                            <i class="bi bi-person-plus me-2"></i>Kayıt Ol ve Başla
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Özellikler -->
    <section class="features">
        <div class="container">
            <div class="section-title">
                <h2>Neden Onvibes Drive?</h2>
                <p>Size özel geliştirilmiş premium özellikler</p>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h4 class="feature-title">Güvenli Saklama</h4>
                        <p class="feature-desc">Dosyalarınız SSL şifreleme ile güvende. Maximum güvenlik standartları.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="bi bi-lightning-charge"></i>
                        </div>
                        <h4 class="feature-title">Hızlı Transfer</h4>
                        <p class="feature-desc">Yüksek hızda dosya yükleme ve indirme. Zaman kaybı yok.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="bi bi-phone"></i>
                        </div>
                        <h4 class="feature-title">Mobil Uyumlu</h4>
                        <p class="feature-desc">Tüm cihazlardan erişim. Telefon, tablet ve bilgisayar dostu.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="footer-links">ONVIBES DRIVE</h5>
                    <p style="color: rgba(255, 255, 255, 0.7);">Premium dosya paylaşım platformu. Hızlı, güvenli ve sınırsız dosya transferi.</p>
                </div>
                <div class="col-lg-2 col-6 mb-4">
                    <h5 class="footer-links">Bağlantılar</h5>
                    <ul>
                        <li><a href="index.php">Ana Sayfa</a></li>
                        <li><a href="browse.php">Dosyalar</a></li>
                        <li><a href="help.php">Yardım</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-6 mb-4">
                    <h5 class="footer-links">Destek</h5>
                    <ul>
                        <li><a href="help.php">Yardım</a></li>
                        <li><a href="contact.php">İletişim</a></li>
                        <li><a href="terms.php">Şartlar</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5 class="footer-links">İletişim</h5>
                    <ul style="color: rgba(255, 255, 255, 0.7);">
                        <li><i class="bi bi-envelope me-2"></i> info@onvibes.online</li>
                        <li><i class="bi bi-globe me-2"></i> onvibes.online</li>
                        <li><i class="bi bi-shield-check me-2"></i> %100 Güvenli</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2025 Onvibes Drive. Tüm hakları saklıdır.</p>
            </div>
        </div>
    </footer>

    <!-- Resim Görüntüleyici Modal -->
    <div id="imageModal" class="image-modal">
        <span class="close-modal" onclick="closeImageViewer()">&times;</span>
        <img class="image-modal-content" id="modalImage">
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Resim görüntüleyici fonksiyonları
        function openImageViewer(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').style.display = 'block';
        }
        
        function closeImageViewer() {
            document.getElementById('imageModal').style.display = 'none';
        }
        
        // Modal dışına tıklayınca kapat
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageViewer();
            }
        });
        
        // ESC tuşu ile kapat
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageViewer();
            }
        });
    </script>
</body>
</html>
