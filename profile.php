<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Profil bilgilerini getir
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Profil güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? $user['username'];
    $email = $_POST['email'] ?? $user['email'];
    
    // Profil fotoğrafı yükleme
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/profiles/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        // Dosya türü kontrolü
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array(strtolower($file_extension), $allowed_types)) {
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
                // Eski profil fotoğrafını sil
                if ($user['profile_picture'] && file_exists($user['profile_picture'])) {
                    unlink($user['profile_picture']);
                }
                
                // Veritabanını güncelle
                $stmt = $db->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $stmt->execute([$file_path, $user_id]);
                $_SESSION['profile_picture'] = $file_path;
                $success = "Profil fotoğrafı başarıyla güncellendi!";
            } else {
                $error = "Profil fotoğrafı yüklenirken hata oluştu!";
            }
        } else {
            $error = "Sadece JPG, PNG, GIF ve WebP dosyaları yükleyebilirsiniz!";
        }
    }
    
    // Kullanıcı bilgilerini güncelle
    if (empty($error)) {
        $stmt = $db->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        $stmt->execute([$username, $email, $user_id]);
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        
        if (empty($success)) {
            $success = "Profil bilgileri başarıyla güncellendi!";
        }
    }
    
    // Sayfayı yenile
    header("Location: profile.php?success=" . urlencode($success));
    exit;
}

// Başarı mesajını kontrol et
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>

<?php include 'header.php'; ?>

<div style="max-width: 800px; margin: 0 auto; padding: 30px 20px; min-height: 80vh;">
    <!-- Başlık -->
    <div style="background: linear-gradient(135deg, #1a1a1a, #262626); padding: 30px; border-radius: 16px; border: 1px solid #3f3f46; margin-bottom: 30px; text-align: center;">
        <h1 style="background: linear-gradient(135deg, #8B5CF6, #ec4899); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 2.5rem; margin-bottom: 10px;">
            <i class="fas fa-user-cog"></i>
            Profil Ayarları
        </h1>
        <p style="color: #a1a1aa; font-size: 1.1rem;">
            Profil bilgilerinizi ve fotoğrafınızı güncelleyin
        </p>
    </div>

    <!-- Hata/Success Mesajları -->
    <?php if ($error): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
        <!-- Sol: Profil Fotoğrafı -->
        <div style="background: #1a1a1a; border-radius: 16px; border: 1px solid #3f3f46; padding: 30px; text-align: center;">
            <div style="position: relative; display: inline-block;">
                <div style="width: 150px; height: 150px; border-radius: 50%; background: linear-gradient(135deg, #8B5CF6, #7C3AED); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; font-weight: bold; margin: 0 auto 20px; overflow: hidden;">
                    <?php
                    $profile_pic = $user['profile_picture'] ?? '';
                    if ($profile_pic && file_exists($profile_pic)) {
                        echo "<img src='$profile_pic' style='width: 100%; height: 100%; object-fit: cover;' alt='Profil'>";
                    } else {
                        echo strtoupper(substr($user['username'], 0, 1));
                    }
                    ?>
                </div>
            </div>
            
            <h3 style="color: white; margin-bottom: 10px;"><?php echo htmlspecialchars($user['username']); ?></h3>
            <p style="color: #a1a1aa; margin-bottom: 20px;"><?php echo htmlspecialchars($user['email']); ?></p>
            
            <div style="background: rgba(139, 92, 246, 0.1); padding: 15px; border-radius: 8px; border-left: 3px solid #8B5CF6;">
                <p style="color: #a1a1aa; margin: 0; font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i>
                    Maksimum 5MB<br>
                    JPG, PNG, GIF, WebP
                </p>
            </div>
        </div>

        <!-- Sağ: Form -->
        <div style="background: #1a1a1a; border-radius: 16px; border: 1px solid #3f3f46; padding: 30px;">
            <form method="POST" enctype="multipart/form-data">
                <!-- Profil Fotoğrafı Yükleme -->
                <div style="margin-bottom: 25px;">
                    <label style="display: block; color: white; margin-bottom: 10px; font-weight: 600;">
                        <i class="fas fa-camera"></i>
                        Profil Fotoğrafı
                    </label>
                    <input type="file" name="profile_picture" accept=".jpg,.jpeg,.png,.gif,.webp" 
                           style="width: 100%; padding: 10px; border: 1px solid #3f3f46; border-radius: 8px; background: #0a0a0a; color: white;">
                </div>

                <!-- Kullanıcı Adı -->
                <div style="margin-bottom: 25px;">
                    <label style="display: block; color: white; margin-bottom: 10px; font-weight: 600;">
                        <i class="fas fa-user"></i>
                        Kullanıcı Adı
                    </label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" 
                           style="width: 100%; padding: 12px; border: 1px solid #3f3f46; border-radius: 8px; background: #0a0a0a; color: white;">
                </div>

                <!-- Email -->
                <div style="margin-bottom: 25px;">
                    <label style="display: block; color: white; margin-bottom: 10px; font-weight: 600;">
                        <i class="fas fa-envelope"></i>
                        Email
                    </label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                           style="width: 100%; padding: 12px; border: 1px solid #3f3f46; border-radius: 8px; background: #0a0a0a; color: white;">
                </div>

                <!-- İstatistikler -->
                <div style="background: rgba(139, 92, 246, 0.1); padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                    <h4 style="color: #8B5CF6; margin-bottom: 15px;">
                        <i class="fas fa-chart-bar"></i>
                        İstatistikler
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <?php
                        $file_count = $db->prepare("SELECT COUNT(*) FROM files WHERE user_id = ?");
                        $file_count->execute([$user_id]);
                        $total_files = $file_count->fetchColumn();
                        
                        $comment_count = $db->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
                        $comment_count->execute([$user_id]);
                        $total_comments = $comment_count->fetchColumn();
                        ?>
                        <div style="text-align: center;">
                            <div style="font-size: 1.5rem; color: #8B5CF6; font-weight: bold;"><?php echo $total_files; ?></div>
                            <div style="color: #a1a1aa; font-size: 0.8rem;">Toplam Dosya</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 1.5rem; color: #10b981; font-weight: bold;"><?php echo $total_comments; ?></div>
                            <div style="color: #a1a1aa; font-size: 0.8rem;">Toplam Yorum</div>
                        </div>
                    </div>
                </div>

                <!-- Butonlar -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <button type="submit" 
                            style="background: linear-gradient(135deg, #8B5CF6, #7C3AED); color: white; padding: 15px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                        <i class="fas fa-save"></i>
                        Profili Güncelle
                    </button>
                    <a href="dosyalar.php" 
                       style="background: #3f3f46; color: white; padding: 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fas fa-arrow-left"></i>
                        Geri Dön
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Profil fotoğrafı önizleme
    document.querySelector('input[name="profile_picture"]').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const profileImage = document.querySelector('.profile-image');
                profileImage.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;" alt="Profil">`;
            }
            reader.readAsDataURL(file);
        }
    });
</script>

<?php include 'footer.php'; ?>
