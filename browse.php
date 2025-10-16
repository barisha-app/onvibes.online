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

// Arama parametresi
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sayfalama
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Toplam dosya sayısını al
if ($search) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM files f 
                         LEFT JOIN users u ON f.user_id = u.id 
                         WHERE f.is_public = 1 AND (f.filename LIKE ? OR f.description LIKE ?)");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $db->query("SELECT COUNT(*) FROM files WHERE is_public = 1");
}
$total_files = $stmt->fetchColumn();
$total_pages = ceil($total_files / $limit);

// Dosyaları getir - INTEGER değerler için bindParam kullan
if ($search) {
    $stmt = $db->prepare("SELECT f.*, u.username 
                         FROM files f 
                         LEFT JOIN users u ON f.user_id = u.id 
                         WHERE f.is_public = 1 AND (f.filename LIKE ? OR f.description LIKE ?)
                         ORDER BY f.created_at DESC 
                         LIMIT ? OFFSET ?");
    
    $stmt->bindValue(1, "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(2, "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->bindValue(4, $offset, PDO::PARAM_INT);
    $stmt->execute();
} else {
    $stmt = $db->prepare("SELECT f.*, u.username 
                         FROM files f 
                         LEFT JOIN users u ON f.user_id = u.id 
                         WHERE f.is_public = 1 
                         ORDER BY f.created_at DESC 
                         LIMIT ? OFFSET ?");
    
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
}
$files = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tüm Dosyalar - Onvibes Drive</title>
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
        
        .search-box {
            max-width: 400px;
        }
        
        .search-box input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 25px;
            padding: 10px 20px;
        }
        
        .search-box input:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(139, 92, 246, 0.25);
            color: white;
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
        
        .page-link {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
        }
        
        .page-link:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .page-item.active .page-link {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
        }
        
        .file-type-badge {
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .user-badge {
            background: var(--success);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .user-badge:hover {
            background: #0d966b;
            color: white;
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 70px;
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
            
            .search-box {
                margin: 10px 0;
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
                <!-- Arama Kutusu -->
                <form class="d-flex search-box mx-auto" method="GET" action="browse.php">
                    <input class="form-control" type="search" name="search" placeholder="Dosya ara..." 
                           value="<?php echo htmlspecialchars($search); ?>" aria-label="Search">
                    <button class="btn btn-outline-light ms-2" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
                
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

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <!-- Başlık ve Bilgiler -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1><i class="bi bi-grid me-2"></i>Tüm Dosyalar</h1>
                        <p class="text-muted mb-0">
                            <?php if ($search): ?>
                                "<?php echo htmlspecialchars($search); ?>" araması için <?php echo $total_files; ?> sonuç bulundu
                            <?php else: ?>
                                Toplam <?php echo $total_files; ?> herkese açık dosya
                            <?php endif; ?>
                        </p>
                    </div>
                    <a href="index.php" class="btn btn-outline-light">
                        <i class="bi bi-arrow-left me-2"></i>Ana Sayfa
                    </a>
                </div>

                <!-- Dosya Listesi -->
                <?php if (!empty($files)): ?>
                    <div class="file-list">
                        <?php foreach ($files as $file): ?>
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
                                        
                                        <!-- Kullanıcı profil linki -->
                                        <div class="file-detail">
                                            <i class="bi bi-person"></i>
                                            <a href="profile.php?id=<?php echo $file['user_id']; ?>" class="user-badge" onclick="event.stopPropagation()">
                                                @<?php echo htmlspecialchars($file['username']); ?>
                                            </a>
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

                    <!-- Sayfalama -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Sayfalama" class="mt-5">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Sonuç Yok -->
                    <div class="no-results">
                        <i class="bi bi-search display-1 text-muted mb-3"></i>
                        <h3 class="text-muted">
                            <?php if ($search): ?>
                                "<?php echo htmlspecialchars($search); ?>" için dosya bulunamadı
                            <?php else: ?>
                                Henüz herkese açık dosya yok
                            <?php endif; ?>
                        </h3>
                        <p class="text-muted mb-4">
                            <?php if ($search): ?>
                                Farklı bir anahtar kelime deneyin veya tüm dosyaları görüntüleyin
                            <?php else: ?>
                                İlk dosyayı paylaşan siz olun!
                            <?php endif; ?>
                        </p>
                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                            <?php if ($search): ?>
                                <a href="browse.php" class="btn btn-primary">Tüm Dosyaları Gör</a>
                            <?php endif; ?>
                            <?php if ($is_logged_in): ?>
                                <a href="files.php" class="btn btn-outline-light">Dosya Yükle</a>
                            <?php else: ?>
                                <a href="register.php" class="btn btn-outline-light">Kayıt Ol</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
