<?php
session_start();
require_once 'config.php';

// Admin giriş kontrolü
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

// Onay bekleyen kullanıcıları getir
$pending_users = $db->query("
    SELECT * FROM users 
    WHERE (is_verified = 0 OR admin_approved = 0) 
    AND status = 'pending' 
    ORDER BY created_at DESC
")->fetchAll();

// Tüm kullanıcıları getir
$all_users = $db->query("
    SELECT u.*, 
           COUNT(f.id) as file_count,
           COUNT(DISTINCT c.id) as comment_count
    FROM users u 
    LEFT JOIN files f ON u.id = f.user_id 
    LEFT JOIN comments c ON u.id = c.user_id 
    GROUP BY u.id 
    ORDER BY u.created_at DESC
")->fetchAll();

// Kullanıcı onaylama
if (isset($_GET['approve'])) {
    $user_id = $_GET['approve'];
    if (approveUser($db, $user_id)) {
        $success_message = "Kullanıcı başarıyla onaylandı!";
    } else {
        $error_message = "Kullanıcı onaylanırken hata oluştu!";
    }
    header('Location: admin_users.php?success=' . urlencode($success_message ?? '') . '&error=' . urlencode($error_message ?? ''));
    exit;
}

// Kullanıcı reddetme
if (isset($_GET['reject'])) {
    $user_id = $_GET['reject'];
    if (rejectUser($db, $user_id)) {
        $success_message = "Kullanıcı başarıyla reddedildi!";
    } else {
        $error_message = "Kullanıcı reddedilirken hata oluştu!";
    }
    header('Location: admin_users.php?success=' . urlencode($success_message ?? '') . '&error=' . urlencode($error_message ?? ''));
    exit;
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --admin-primary: #ef4444;
            --admin-secondary: #dc2626;
            --bg-dark: #0a0a0a;
            --bg-card: #1a1a1a;
            --bg-secondary: #262626;
            --text-light: #ffffff;
            --text-gray: #a1a1aa;
            --border: #3f3f46;
            --success: #10b981;
            --warning: #f59e0b;
            --info: #3b82f6;
            --danger: #ef4444;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-dark);
            color: var(--text-light);
            min-height: 100vh;
        }
        
        .admin-header {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-secondary));
            padding: 20px 0;
            border-bottom: 3px solid var(--admin-secondary);
        }
        
        .admin-nav {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .admin-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-danger {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-danger:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-secondary));
            padding: 30px;
            border-radius: 16px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .welcome-card h1 {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        /* Bölümler */
        .admin-section {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            background: rgba(239, 68, 68, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title {
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-content {
            padding: 20px;
        }
        
        /* Tablolar */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .data-table th {
            background: var(--bg-secondary);
            color: var(--text-light);
            font-weight: 600;
        }
        
        .data-table tr:hover {
            background: rgba(239, 68, 68, 0.05);
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
            border-radius: 4px;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
            text-decoration: none;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
            text-decoration: none;
        }
        
        .btn-info {
            background: var(--info);
            color: white;
            text-decoration: none;
        }
        
        .btn-danger-sm {
            background: var(--danger);
            color: white;
            text-decoration: none;
        }
        
        /* Mesajlar */
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: var(--success);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }
        
        /* İstatistik Kartları */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: var(--text-gray);
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .admin-nav {
                flex-direction: column;
                gap: 15px;
            }
            
            .data-table {
                font-size: 0.9rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Header -->
    <header class="admin-header">
        <nav class="admin-nav">
            <div class="admin-logo">
                <i class="fas fa-users-cog"></i>
                Kullanıcı Yönetimi
            </div>
            <div class="admin-actions">
                <a href="admin.php" class="btn btn-danger">
                    <i class="fas fa-arrow-left"></i> Ana Sayfaya Dön
                </a>
                <a href="admin_logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Çıkış
                </a>
            </div>
        </nav>
    </header>

    <div class="admin-container">
        <!-- Mesajlar -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- İstatistikler -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: var(--info);"><?php echo count($all_users); ?></div>
                <div class="stat-label">Toplam Kullanıcı</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--warning);"><?php echo count($pending_users); ?></div>
                <div class="stat-label">Onay Bekleyen</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--success);">
                    <?php echo count(array_filter($all_users, function($user) { return $user['is_verified'] && $user['admin_approved']; })); ?>
                </div>
                <div class="stat-label">Aktif Kullanıcı</div>
            </div>
        </div>

        <!-- Onay Bekleyen Kullanıcılar -->
        <?php if (!empty($pending_users)): ?>
        <div class="admin-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-clock"></i>
                    Onay Bekleyen Kullanıcılar
                    <span style="background: var(--warning); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;">
                        <?php echo count($pending_users); ?> kullanıcı
                    </span>
                </h2>
            </div>
            <div class="section-content">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı Adı</th>
                            <th>Email</th>
                            <th>Kayıt Tarihi</th>
                            <th>Doğrulama</th>
                            <th>Admin Onay</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['is_verified']): ?>
                                    <span style="color: var(--success);">✅ Doğrulanmış</span>
                                <?php else: ?>
                                    <span style="color: var(--warning);">❌ Doğrulanmamış</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['admin_approved']): ?>
                                    <span style="color: var(--success);">✅ Onaylı</span>
                                <?php else: ?>
                                    <span style="color: var(--warning);">⏳ Bekliyor</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="admin_users.php?approve=<?php echo $user['id']; ?>" class="btn-sm btn-success" 
                                       onclick="return confirm('Bu kullanıcıyı onaylamak istediğinizden emin misiniz?')">
                                        <i class="fas fa-check"></i> Onayla
                                    </a>
                                    <a href="admin_users.php?reject=<?php echo $user['id']; ?>" class="btn-sm btn-danger-sm" 
                                       onclick="return confirm('Bu kullanıcıyı reddetmek istediğinizden emin misiniz?')">
                                        <i class="fas fa-times"></i> Reddet
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tüm Kullanıcılar -->
        <div class="admin-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-list"></i>
                    Tüm Kullanıcılar
                    <span style="background: var(--info); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;">
                        <?php echo count($all_users); ?> kullanıcı
                    </span>
                </h2>
            </div>
            <div class="section-content">
                <?php if (!empty($all_users)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Kullanıcı Adı</th>
                                <th>Email</th>
                                <th>Dosya Sayısı</th>
                                <th>Yorum Sayısı</th>
                                <th>Rol</th>
                                <th>Kayıt Tarihi</th>
                                <th>Doğrulama</th>
                                <th>Admin Onay</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    <?php if ($user['id'] == $_SESSION['admin_id']): ?>
                                        <span style="background: var(--admin-primary); color: white; padding: 2px 6px; border-radius: 8px; font-size: 0.7rem; margin-left: 5px;">SİZ</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo $user['file_count']; ?></td>
                                <td><?php echo $user['comment_count']; ?></td>
                                <td>
                                    <span style="color: <?php echo $user['role'] == 'admin' ? 'var(--admin-primary)' : 'var(--info)'; ?>; font-weight: 600;">
                                        <?php echo $user['role']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['is_verified']): ?>
                                        <span style="color: var(--success);">✅ Doğrulanmış</span>
                                    <?php else: ?>
                                        <span style="color: var(--warning);">❌ Doğrulanmamış</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['admin_approved']): ?>
                                        <span style="color: var(--success);">✅ Onaylı</span>
                                    <?php else: ?>
                                        <span style="color: var(--warning);">❌ Onaysız</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <span style="color: var(--success);">✅ Aktif</span>
                                    <?php elseif ($user['status'] == 'pending'): ?>
                                        <span style="color: var(--warning);">⏳ Bekliyor</span>
                                    <?php elseif ($user['status'] == 'rejected'): ?>
                                        <span style="color: var(--danger);">❌ Reddedildi</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray);"><?php echo $user['status']; ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-gray);">
                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                        <h3>Henüz kullanıcı yok</h3>
                        <p>Kullanıcı kaydı bulunamadı</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Tablo satırlarına tıklanabilirlik
        document.querySelectorAll('.data-table tr').forEach(row => {
            row.addEventListener('click', function(e) {
                if (!e.target.closest('a') && !e.target.closest('button')) {
                    this.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
                }
            });
        });

        // Otomatik mesaj kapatma
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
