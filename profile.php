<?php
session_start();
require_once 'config.php';

// Profil sahibi kullanıcı ID'sini al
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$profile_user_id = $_GET['id'];

// Profil sahibi kullanıcı bilgilerini al
try {
    $stmt = $db->prepare("SELECT id, username, created_at FROM users WHERE id = ?");
    $stmt->execute([$profile_user_id]);
    $profile_user = $stmt->fetch();
    
    if (!$profile_user) {
        throw new Exception("Kullanıcı bulunamadı!");
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: index.php');
    exit;
}

// Giriş yapmış kullanıcı bilgileri
$is_logged_in = isset($_SESSION['user_id']);
$current_user = null;
if ($is_logged_in) {
    $stmt = $db->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
}

// Kullanıcının herkese açık dosyalarını getir
try {
    $stmt = $db->prepare("SELECT f.*, u.username 
                         FROM files f 
                         LEFT JOIN users u ON f.user_id = u.id 
                         WHERE f.user_id = ? AND f.is_public = 1 
                         ORDER BY f.created_at DESC");
    $stmt->execute([$profile_user_id]);
    $public_files = $stmt->fetchAll();
} catch (PDOException $e) {
    $public_files = [];
}

// Kullanıcının istatistiklerini al
try {
    $stmt = $db->prepare("SELECT 
        COUNT(*) as total_public_files,
        COALESCE(SUM(download_count), 0) as total_downloads,
        COALESCE(SUM(file_size), 0) as total_storage
        FROM files WHERE user_id = ? AND is_public = 1");
    $stmt->execute([$profile_user_id]);
    $user_stats = $stmt->fetch();
} catch (PDOException $e) {
    $user_stats = [
        'total_public_files' => 0,
        'total_downloads' => 0,
        'total_storage' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profile_user['username']); ?> - Profil - Onvibes Drive</title>
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
        
        .profile-header {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .user-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            margin: 0 auto 20px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-text {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        /* Liste Görünümü */
        .file-list-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-list-item:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary);
            transform: translateX(5px);
        }
        
        .file-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 20px;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: white;
        }
        
        .file-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        
        .file-details {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .file-detail {
            display: flex;
            align-items: center;
            gap: 5px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.85rem;
        }
        
        .file-detail i {
            color: var(--primary);
        }
        
        .file-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-download {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .btn-download:hover {
            background: var(--secondary);
            color: white;
            transform: translateY(-2px);
        }
        
        .file-type-badge {
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .no-files {
            text-align: center;
            padding: 60px 20px;
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }
            
            .profile-header {
                padding: 30px 20px;
            }
            
            .file-list-item {
                padding: 15px;
            }
            
            .file-details {
                gap: 10px;
            }
            
            .file-detail {
                font-size: 0.8rem;
            }
            
            .file-actions {
                margin-top: 10px;
                justify-content: flex-start;
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
        <div class="row">
            <div class="col-12">
                <!-- Profil Header -->
                <div class="profile-header">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($profile_user['username'], 0, 2)); ?>
                    </div>
                    <h1>@<?php echo htmlspecialchars($profile_user['username']); ?></h1>
                    <p class="text-muted">
                        <i class="bi bi-calendar me-1"></i>
                        <?php echo date('d.m.Y', strtotime($profile_user['created_at'])); ?> tarihinden beri üye
                    </p>
                    
                    <!-- Kullanıcı İstatistikleri -->
                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $user_stats['total_public_files']; ?></div>
                                <div class="stat-text">Paylaşılan Dosya</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $user_stats['total_downloads']; ?></div>
                                <div class="stat-text">Toplam İndirme</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo formatFileSize($user_stats['total_storage']); ?></div>
                                <div class="stat-text">Toplam Depolama</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kullanıcının Dosyaları -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-files me-2"></i>Paylaşılan Dosyalar</h2>
                    <a href="browse.php" class="btn btn-outline-light">
                        <i class="bi bi-arrow-left me-2"></i>Tüm Dosyalara Dön
                    </a>
                </div>

                <?php if (!empty($public_files)): ?>
                    <div class="file-list">
                        <?php foreach ($public_files as $file): ?>
                            <?php
                            $extension = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
                            $file_type = '';
                            $file_icon = '';
                            
                            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                $file_type = 'Resim';
                                $file_icon = 'bi-image';
                            } elseif (in_array($extension, ['mp4', 'avi', 'mov', 'webm'])) {
                                $file_type = 'Video';
                                $file_icon = 'bi-play-btn';
                            } elseif (in_array($extension, ['mp3', 'wav', 'ogg'])) {
                                $file_type = 'Ses';
                                $file_icon = 'bi-music-note-beamed';
                            } elseif ($extension == 'pdf') {
                                $file_type = 'PDF';
                                $file_icon = 'bi-file-pdf';
                            } elseif (in_array($extension, ['doc', 'docx'])) {
                                $file_type = 'Word';
                                $file_icon = 'bi-file-word';
                            } elseif (in_array($extension, ['xls', 'xlsx'])) {
                                $file_type = 'Excel';
                                $file_icon = 'bi-file-excel';
                            } elseif (in_array($extension, ['zip', 'rar', '7z'])) {
                                $file_type = 'Arşiv';
                                $file_icon = 'bi-file-zip';
                            } else {
                                $file_type = strtoupper($extension);
                                $file_icon = 'bi-file-earmark';
                            }
                            ?>
                            
                            <div class="file-list-item d-flex align-items-center" onclick="window.location.href='view.php?id=<?php echo $file['id']; ?>'">
                                <div class="file-icon">
                                    <i class="bi <?php echo $file_icon; ?>"></i>
                                </div>
                                
                                <div class="file-info">
                                    <div class="file-name"><?php echo htmlspecialchars($file['filename']); ?></div>
                                    
                                    <?php if (!empty($file['description'])): ?>
                                        <div class="file-description"><?php echo htmlspecialchars($file['description']); ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="file-details">
                                        <div class="file-detail">
                                            <i class="bi bi-tag"></i>
                                            <span class="file-type-badge"><?php echo $file_type; ?></span>
                                        </div>
                                        
                                        <div class="file-detail">
                                            <i class="bi bi-calendar"></i>
                                            <span><?php echo date('d.m.Y H:i', strtotime($file['created_at'])); ?></span>
                                        </div>
                                        
                                        <div class="file-detail">
                                            <i class="bi bi-hdd"></i>
                                            <span><?php echo formatFileSize($file['file_size']); ?></span>
                                        </div>
                                        
                                        <div class="file-detail">
                                            <i class="bi bi-download"></i>
                                            <span><?php echo $file['download_count'] ?? 0; ?> indirme</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="file-actions">
                                    <a href="download.php?id=<?php echo $file['id']; ?>" class="btn-download" onclick="event.stopPropagation()">
                                        <i class="bi bi-download me-1"></i>İndir
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-files">
                        <i class="bi bi-folder-x display-1 text-muted mb-3"></i>
                        <h3 class="text-muted">Henüz paylaşılan dosya yok</h3>
                        <p class="text-muted">Bu kullanıcı henüz herkese açık dosya paylaşmamış.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
