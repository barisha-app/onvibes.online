<?php
session_start();
require_once 'config.php';

// Dosya ID kontrolü
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$file_id = $_GET['id'];
$is_logged_in = isset($_SESSION['user_id']);

// Dosya bilgilerini al
try {
    $stmt = $db->prepare("SELECT f.*, u.username 
                         FROM files f 
                         LEFT JOIN users u ON f.user_id = u.id 
                         WHERE f.id = ? AND (f.is_public = 1 OR f.user_id = ?)");
    $stmt->execute([$file_id, $_SESSION['user_id'] ?? 0]);
    $file = $stmt->fetch();
    
    if (!$file || !file_exists($file['file_path'])) {
        throw new Exception("Dosya bulunamadı veya erişim izniniz yok!");
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: index.php');
    exit;
}

// İndirme sayısını güncelle
$stmt = $db->prepare("UPDATE files SET download_count = download_count + 1 WHERE id = ?");
$stmt->execute([$file_id]);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($file['filename']); ?> - Onvibes Drive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #8B5CF6;
            --secondary: #06B6D4;
            --success: #10B981;
            --dark: #0F172A;
        }
        
        body {
            background: var(--dark);
            color: white;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding-top: 80px;
        }
        
        .navbar {
            background: rgba(15, 23, 42, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            background: linear-gradient(45deg, var(--success), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .file-viewer {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .file-info {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
        }
        
        /* Medya oynatıcı stilleri */
        .media-player {
            max-width: 100%;
            border-radius: 12px;
            background: #1a1a1a;
        }
        
        .video-player {
            width: 100%;
            max-height: 500px;
            border-radius: 12px;
        }
        
        .audio-player {
            width: 100%;
            border-radius: 8px;
        }
        
        .image-viewer {
            max-width: 100%;
            max-height: 500px;
            border-radius: 12px;
            cursor: pointer;
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
        
        .user-link {
            color: var(--success);
            text-decoration: none;
            font-weight: 500;
        }
        
        .user-link:hover {
            color: #0d966b;
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }
            
            .file-viewer {
                padding: 20px;
            }
            
            .video-player {
                max-height: 300px;
            }
            
            .image-viewer {
                max-height: 300px;
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
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
                <a class="nav-link" href="browse.php"><i class="bi bi-grid me-1"></i>Tüm Dosyalar</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Dosya Görüntüleyici -->
                <div class="file-viewer">
                    <div class="text-center mb-4">
                        <h2><?php echo htmlspecialchars($file['filename']); ?></h2>
                        <p class="text-muted">
                            <?php echo getUserProfileLink($file['user_id'], $file['username']); ?> tarafından yüklendi
                        </p>
                    </div>
                    
                    <!-- Dosya İçeriği -->
                    <div class="text-center media-player">
                        <?php 
                        $extension = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
                        
                        // Video dosyaları
                        if (in_array($extension, ['mp4', 'avi', 'mov', 'webm'])): ?>
                            <video class="video-player" controls autoplay>
                                <source src="<?php echo $file['file_path']; ?>" type="video/mp4">
                                Tarayıcınız video oynatmayı desteklemiyor.
                            </video>
                            
                        <!-- Audio dosyaları -->
                        <?php elseif (in_array($extension, ['mp3', 'wav', 'ogg'])): ?>
                            <div class="p-4">
                                <audio class="audio-player" controls autoplay>
                                    <source src="<?php echo $file['file_path']; ?>" type="audio/mpeg">
                                    Tarayıcınız audio oynatmayı desteklemiyor.
                                </audio>
                            </div>
                            
                        <!-- Resim dosyaları -->
                        <?php elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                            <img src="<?php echo $file['file_path']; ?>" 
                                 class="image-viewer" 
                                 alt="<?php echo htmlspecialchars($file['filename']); ?>"
                                 onclick="openImageViewer('<?php echo $file['file_path']; ?>')">
                            <div class="mt-2">
                                <small class="text-muted"><i class="bi bi-zoom-in"></i> Resme tıklayarak büyütebilirsiniz</small>
                            </div>
                            
                        <!-- Diğer dosyalar -->
                        <?php else: ?>
                            <div class="p-5">
                                <i class="bi bi-file-earmark display-1 text-muted"></i>
                                <p class="mt-3 text-muted">Bu dosya türü tarayıcıda görüntülenemiyor</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Dosya Bilgileri -->
                    <div class="file-info">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Dosya Adı:</strong> <?php echo htmlspecialchars($file['filename']); ?></p>
                                <p><strong>Boyut:</strong> <?php echo formatFileSize($file['file_size']); ?></p>
                                <p><strong>Yükleyen:</strong> 
                                    <?php echo getUserProfileLink($file['user_id'], $file['username']); ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Yüklenme Tarihi:</strong> <?php echo date('d.m.Y H:i', strtotime($file['created_at'])); ?></p>
                                <p><strong>İndirme Sayısı:</strong> <?php echo $file['download_count'] + 1; ?></p>
                                <p><strong>Durum:</strong> 
                                    <span class="badge <?php echo $file['is_public'] ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $file['is_public'] ? 'Herkese Açık' : 'Özel'; ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <?php if (!empty($file['description'])): ?>
                            <div class="mt-3">
                                <strong>Açıklama:</strong>
                                <p class="mb-0"><?php echo htmlspecialchars($file['description']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- İşlem Butonları -->
                    <div class="text-center mt-4">
                        <a href="download.php?id=<?php echo $file['id']; ?>" class="btn btn-primary me-2">
                            <i class="bi bi-download me-2"></i>Dosyayı İndir
                        </a>
                        <a href="browse.php" class="btn btn-outline-light">
                            <i class="bi bi-arrow-left me-2"></i>Geri Dön
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
