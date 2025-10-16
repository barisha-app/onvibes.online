<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// PLESK VERİTABANI BİLGİLERİ
$host = "localhost";
$dbname = "onvibes_online_barisha_drive";
$username = "onvib_barisha";
$password = "9mgycTZQ0ne3&s?";

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Veritabanı Hatası - Onvibes Barisha</title>
        <style>
            body { font-family: Arial, sans-serif; background: #0a0a0a; color: white; padding: 50px; }
            .error-container { max-width: 600px; margin: 0 auto; background: #1a1a1a; padding: 30px; border-radius: 10px; border: 1px solid #ef4444; }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <h2>❌ Veritabanı Bağlantı Hatası</h2>
            <p><strong>Hata:</strong> " . $e->getMessage() . "</p>
            <p><a href='create_tables.php' style='color: #8B5CF6;'>Tablo oluşturma scriptini çalıştır</a></p>
        </div>
    </body>
    </html>
    ");
}

// Site URL
define('SITE_URL', 'https://onvibes.online');

// Maksimum dosya boyutu (100MB)
define('MAX_FILE_SIZE', 100 * 1024 * 1024);

// Dosya önizleme fonksiyonu - GÜNCELLENDİ
function getFilePreview($file_path, $file_type, $file_id = null, $auto_play = false) {
    if (!file_exists($file_path)) {
        return "<div style='text-align: center; padding: 30px; background: #1a1a1a; border-radius: 8px;'>
                <i class='fas fa-file' style='font-size: 48px; color: #6b7280;'></i>
                <p style='margin-top: 10px; color: #a1a1aa;'>Dosya bulunamadı</p>
                </div>";
    }
    
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    // Video dosyaları - Oynatıcı ile
    if (in_array($extension, ['mp4', 'avi', 'mov', 'webm'])) {
        $autoplay = $auto_play ? 'autoplay' : '';
        $controls = $auto_play ? '' : 'controls';
        return "<div style='text-align: center; background: #1a1a1a; border-radius: 8px; overflow: hidden;'>
                <video style='max-width: 100%; max-height: 200px; border-radius: 8px;' $controls $autoplay muted loop>
                    <source src='$file_path' type='video/mp4'>
                    Tarayıcınız video oynatmayı desteklemiyor.
                </video>
                <div style='padding: 10px; background: rgba(0,0,0,0.5);'>
                    <small style='color: #a1a1aa;'>Video Dosyası</small>
                </div>
                </div>";
    }
    
    // Audio dosyaları - Oynatıcı ile
    elseif (in_array($extension, ['mp3', 'wav', 'ogg'])) {
        $autoplay = $auto_play ? 'autoplay' : '';
        $controls = $auto_play ? '' : 'controls';
        return "<div style='text-align: center; padding: 20px; background: #1a1a1a; border-radius: 8px;'>
                <audio style='width: 100%;' $controls $autoplay>
                    <source src='$file_path' type='audio/mpeg'>
                    Tarayıcınız audio oynatmayı desteklemiyor.
                </audio>
                <div style='margin-top: 10px;'>
                    <small style='color: #a1a1aa;'>Ses Dosyası</small>
                </div>
                </div>";
    }
    
    // Resim dosyaları - Görüntüleyici ile
    elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        return "<div style='text-align: center; cursor: pointer;' onclick='openImageViewer(\"$file_path\")'>
                <img src='$file_path' style='max-width: 100%; height: 150px; object-fit: cover; border-radius: 8px;' alt='Önizleme'>
                <div style='padding: 5px;'>
                    <small style='color: #a1a1aa;'><i class='bi bi-zoom-in'></i> Görüntüle</small>
                </div>
                </div>";
    }
    
    // PDF dosyaları
    elseif ($extension == 'pdf') {
        return "<div style='text-align: center; padding: 20px; background: #1a1a1a; border-radius: 8px; height: 150px; display: flex; flex-direction: column; justify-content: center;'>
                <i class='fas fa-file-pdf' style='font-size: 48px; color: #ef4444;'></i>
                <p style='margin-top: 10px; color: #a1a1aa;'>PDF Dosyası</p>
                </div>";
    }
    
    // Diğer dosyalar
    else {
        return "<div style='text-align: center; padding: 20px; background: #1a1a1a; border-radius: 8px; height: 150px; display: flex; flex-direction: column; justify-content: center;'>
                <i class='fas fa-file' style='font-size: 48px; color: #6b7280;'></i>
                <p style='margin-top: 10px; color: #a1a1aa;'>" . strtoupper($extension) . " Dosyası</p>
                </div>";
    }
}

// Paylaşım linki oluşturma fonksiyonu
function generateShareLink($file_id, $db) {
    try {
        // Benzersiz paylaşım tokeni oluştur
        $share_token = bin2hex(random_bytes(16));
        
        // Dosyayı güncelle
        $stmt = $db->prepare("UPDATE files SET share_token = ? WHERE id = ?");
        $stmt->execute([$share_token, $file_id]);
        
        // Paylaşım linkini oluştur
        return SITE_URL . "/shared.php?token=" . $share_token;
        
    } catch(PDOException $e) {
        throw new Exception("Paylaşım oluşturulamadı: " . $e->getMessage());
    }
}

// Dosya boyutu formatlama
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $base = 1024;
    $class = min((int)log($bytes, $base), count($units) - 1);
    
    return sprintf('%1.2f', $bytes / pow($base, $class)) . ' ' . $units[$class];
}

// Güvenlik fonksiyonu - XSS koruması
function safe_html($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Token oluşturma
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Otomatik olarak gerekli klasörleri oluştur
function createUserFolder($folder_name) {
    $upload_dir = "uploads/{$folder_name}/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    return $upload_dir;
}

// Hata yönetimi
function handleError($message, $redirect_url = null) {
    error_log("Onvibes Barisha Error: " . $message);
    
    if ($redirect_url) {
        header("Location: $redirect_url?error=" . urlencode($message));
        exit;
    } else {
        die("Hata: " . $message);
    }
}

// Başarı mesajı yönetimi
function handleSuccess($message, $redirect_url) {
    header("Location: $redirect_url?success=" . urlencode($message));
    exit;
}

// Login kontrolü
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Admin kontrolü
function requireAdmin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: login.php');
        exit;
    }
}

// CSRF token oluşturma
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF token doğrulama
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Dosya türü kontrolü
function isAllowedFileType($filename) {
    $allowed_types = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf',
        'doc', 'docx',
        'xls', 'xlsx',
        'mp4', 'avi', 'mov', 'webm',
        'mp3', 'wav', 'ogg',
        'zip', 'rar', '7z',
        'txt', 'md'
    ];
    
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowed_types);
}

// Dosya yükleme fonksiyonu
function uploadFile($file, $user_folder) {
    // Dosya boyutu kontrolü
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception("Dosya boyutu 100MB'tan büyük olamaz!");
    }
    
    // Dosya türü kontrolü
    if (!isAllowedFileType($file['name'])) {
        throw new Exception("Bu dosya türü desteklenmiyor!");
    }
    
    // Klasörü oluştur
    $upload_dir = createUserFolder($user_folder);
    
    // Benzersiz dosya adı oluştur
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $unique_filename;
    
    // Dosyayı yükle
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception("Dosya yüklenirken hata oluştu!");
    }
    
    return [
        'filename' => $file['name'],
        'file_path' => $file_path,
        'file_size' => $file['size'],
        'file_type' => $file['type']
    ];
}
?>
