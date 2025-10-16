<?php
session_start();
require_once 'config.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: index.php');
    exit;
}

// Token ile dosyayı getir
$file = getFileByShareToken($db, $token);

if (!$file) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Dosya Bulunamadı - OnVibes</title>
        <style>
            body { font-family: Arial, sans-serif; background: #0a0a0a; color: white; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
            .error-container { text-align: center; padding: 40px; }
            .error-icon { font-size: 4rem; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">❌</div>
            <h2>Dosya Bulunamadı</h2>
            <p>Bu dosya artık mevcut değil veya paylaşım süresi dolmuş.</p>
            <a href="index.php" style="color: #8B5CF6;">Ana Sayfaya Dön</a>
        </div>
    </body>
    </html>
    ');
}

// İndirme sayısını artır
try {
    $stmt = $db->prepare("UPDATE files SET download_count = download_count + 1 WHERE id = ?");
    $stmt->execute([$file['id']]);
} catch(PDOException $e) {
    error_log("İndirme sayacı hatası: " . $e->getMessage());
}

// Sosyal medya paylaşım linkleri
$file_url = SITE_URL . '/shared.php?token=' . $token;
$social_links = getSocialShareLinks($file_url, $file['filename'], $file['description'] ?? '');
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($file['filename']); ?> - OnVibes Drive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #8B5CF6;
            --secondary: #06B6D4;
            --success: #10B981;
            --dark: #0F172A;
            --darker: #020617;
        }
        
        body {
            background: var(--darker);
            color: white;
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .share-container {
            background: var(--dark);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .file-preview {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .file-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: white;
        }
        
        .file-info {
            margin-bottom: 30px;
        }
        
        .file-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: white;
        }
        
        .file-description {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .file-meta {
            display: flex;
            justify-content: center;
            gap: 20px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }
        
        .file-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .btn-download {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            flex: 1;
            justify-content: center;
        }
        
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
            color: white;
        }
        
        .social-share {
            margin-bottom: 30px;
        }
        
        .social-title {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .social-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .social-btn {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .social-btn:hover {
            transform: translateY(-2px);
        }
        
        .whatsapp { background: #25D366; color: white; }
        .telegram { background: #0088cc; color: white; }
        .twitter { background: #1DA1F2; color: white; }
        .facebook { background: #1877F2; color: white; }
        .linkedin { background: #0A66C2; color: white; }
        .reddit { background: #FF4500; color: white; }
        
        .share-link {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .share-link-input {
            background: transparent;
            border: none;
            color: white;
            width: 100%;
            font-size: 0.9rem;
        }
        
        .share-link-input:focus {
            outline: none;
        }
        
        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: rgba(255, 255, 255, 0.7);
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .footer-info {
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.8rem;
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .share-container {
                padding: 30px 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .social-buttons {
                gap: 8px;
            }
            
            .social-btn {
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="share-container">
        <!-- Dosya Önizleme -->
        <div class="file-preview">
            <div class="file-icon">
                <?php
                $extension = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
                $file_icon = '';
                
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $file_icon = 'bi-image';
                } elseif (in_array($extension, ['mp4', 'avi', 'mov', 'webm'])) {
                    $file_icon = 'bi-play-btn';
                } elseif (in_array($extension, ['mp3', 'wav', 'ogg'])) {
                    $file_icon = 'bi-music-note-beamed';
                } elseif ($extension == 'pdf') {
                    $file_icon = 'bi-file-pdf';
                } elseif (in_array($extension, ['doc', 'docx'])) {
                    $file_icon = 'bi-file-word';
                } elseif (in_array($extension, ['xls', 'xlsx'])) {
                    $file_icon = 'bi-file-excel';
                } elseif (in_array($extension, ['zip', 'rar', '7z'])) {
                    $file_icon = 'bi-file-zip';
                } else {
                    $file_icon = 'bi-file-earmark';
                }
                ?>
                <i class="bi <?php echo $file_icon; ?>"></i>
            </div>
        </div>

        <!-- Dosya Bilgileri -->
        <div class="file-info">
            <div class="file-name"><?php echo htmlspecialchars($file['filename']); ?></div>
            
            <?php if (!empty($file['description'])): ?>
                <div class="file-description"><?php echo htmlspecialchars($file['description']); ?></div>
            <?php endif; ?>
            
            <div class="file-meta">
                <div class="file-meta-item">
                    <i class="bi bi-person"></i>
                    <?php echo htmlspecialchars($file['username']); ?>
                </div>
                <div class="file-meta-item">
                    <i class="bi bi-hdd"></i>
                    <?php echo formatFileSize($file['file_size']); ?>
                </div>
                <div class="file-meta-item">
                    <i class="bi bi-download"></i>
                    <?php echo $file['download_count'] ?? 0; ?> indirme
                </div>
            </div>
        </div>

        <!-- İndirme Butonu -->
        <div class="action-buttons">
            <a href="download.php?id=<?php echo $file['id']; ?>&token=<?php echo $token; ?>" class="btn-download">
                <i class="bi bi-download"></i>
                Dosyayı İndir
            </a>
        </div>

        <!-- Paylaşım Linki -->
        <div class="share-link">
            <div class="d-flex gap-2">
                <input type="text" class="share-link-input" value="<?php echo $file_url; ?>" readonly id="shareLink">
                <button type="button" class="copy-btn" onclick="copyShareLink()">
                    <i class="bi bi-clipboard"></i> Kopyala
                </button>
            </div>
        </div>

        <!-- Sosyal Medya Paylaşım -->
        <div class="social-share">
            <div class="social-title">Sosyal Medyada Paylaş</div>
            <div class="social-buttons">
                <a href="<?php echo $social_links['whatsapp']; ?>" class="social-btn whatsapp" target="_blank" title="WhatsApp'ta Paylaş">
                    <i class="bi bi-whatsapp"></i>
                </a>
                <a href="<?php echo $social_links['telegram']; ?>" class="social-btn telegram" target="_blank" title="Telegram'da Paylaş">
                    <i class="bi bi-telegram"></i>
                </a>
                <a href="<?php echo $social_links['twitter']; ?>" class="social-btn twitter" target="_blank" title="Twitter'da Paylaş">
                    <i class="bi bi-twitter"></i>
                </a>
                <a href="<?php echo $social_links['facebook']; ?>" class="social-btn facebook" target="_blank" title="Facebook'ta Paylaş">
                    <i class="bi bi-facebook"></i>
                </a>
                <a href="<?php echo $social_links['linkedin']; ?>" class="social-btn linkedin" target="_blank" title="LinkedIn'de Paylaş">
                    <i class="bi bi-linkedin"></i>
                </a>
                <a href="<?php echo $social_links['reddit']; ?>" class="social-btn reddit" target="_blank" title="Reddit'te Paylaş">
                    <i class="bi bi-reddit"></i>
                </a>
            </div>
        </div>

        <div class="footer-info">
            <i class="bi bi-shield-check"></i> Güvenli dosya paylaşımı • OnVibes Drive
        </div>
    </div>

    <script>
        // Paylaşım linkini kopyalama
        function copyShareLink() {
            const shareLink = document.getElementById('shareLink');
            shareLink.select();
            shareLink.setSelectionRange(0, 99999);
            
            navigator.clipboard.writeText(shareLink.value).then(function() {
                const btn = event.target;
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check"></i> Kopyalandı!';
                btn.style.background = '#10B981';
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.style.background = '';
                }, 2000);
            }).catch(function() {
                alert('Link kopyalanamadı. Lütfen manuel kopyalayın.');
            });
        }
        
        // Sosyal medya butonları için yeni sekme
        document.querySelectorAll('.social-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                window.open(this.href, '_blank', 'width=600,height=400');
            });
        });
    </script>
</body>
</html>
