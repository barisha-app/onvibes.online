<?php
session_start();
require_once 'config.php';

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $user_id = $_SESSION['user_id'];
        $filename = basename($_FILES['file']['name']);
        $file_size = $_FILES['file']['size'];
        $file_type = $_FILES['file']['type'];
        $description = $_POST['description'] ?? '';
        
        // Maksimum dosya boyutu: 100MB
        $max_file_size = 100 * 1024 * 1024;
        
        if ($file_size > $max_file_size) {
            $error = "Dosya boyutu 100MB'tan büyük olamaz!";
        } else {
            // Kullanıcı klasörü
            $user_folder = $_SESSION['folder'] ?? 'user_' . $user_id;
            $upload_dir = "uploads/{$user_folder}/";
            
            // Klasör yoksa oluştur
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Benzersiz dosya adı
            $file_extension = pathinfo($filename, PATHINFO_EXTENSION);
            $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $unique_filename;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
                // Veritabanına kaydet (admin onayı bekliyor)
                $stmt = $db->prepare("INSERT INTO files (user_id, filename, file_path, file_size, file_type, description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $filename, $file_path, $file_size, $file_type, $description]);
                
                $success = "Dosya başarıyla yüklendi! Admin onayından sonra yayınlanacak.";
            } else {
                $error = "Dosya yüklenirken hata oluştu!";
            }
        }
    } else {
        $error = "Lütfen bir dosya seçin veya dosya boyutu çok büyük!";
    }
}
?>

<?php include 'header.php'; ?>

<div style="
    max-width: 800px;
    margin: 0 auto;
    padding: 30px 20px;
    min-height: 80vh;
">
    <!-- Başlık -->
    <div style="
        background: linear-gradient(135deg, #1a1a1a, #262626);
        padding: 30px;
        border-radius: 16px;
        border: 1px solid #3f3f46;
        margin-bottom: 30px;
        text-align: center;
    ">
        <h1 style="
            background: linear-gradient(135deg, #8B5CF6, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.5rem;
            margin-bottom: 10px;
        ">
            <i class="fas fa-cloud-upload-alt"></i>
            Dosya Yükle
        </h1>
        <p style="color: #a1a1aa; font-size: 1.1rem;">
            Dosyanızı yükleyin, admin onayından sonra toplulukla paylaşılsın.
        </p>
    </div>

    <!-- Hata/Success Mesajları -->
    <?php if ($error): ?>
        <div style="
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        ">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid #10b981;
            color: #10b981;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        ">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Yükleme Formu -->
    <div style="
        background: #1a1a1a;
        border-radius: 16px;
        border: 1px solid #3f3f46;
        padding: 30px;
    ">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <!-- Dosya Seçimi -->
            <div style="margin-bottom: 25px;">
                <label style="
                    display: block;
                    color: white;
                    margin-bottom: 10px;
                    font-weight: 600;
                ">
                    <i class="fas fa-file"></i>
                    Dosya Seçin
                </label>
                
                <div style="
                    border: 2px dashed #3f3f46;
                    border-radius: 12px;
                    padding: 40px;
                    text-align: center;
                    transition: all 0.3s ease;
                    cursor: pointer;
                    position: relative;
                " id="dropZone" 
                   onmouseover="this.style.borderColor='#8B5CF6'; this.style.backgroundColor='rgba(139, 92, 246, 0.05)';" 
                   onmouseout="this.style.borderColor='#3f3f46'; this.style.backgroundColor='transparent';">
                    <input type="file" name="file" id="fileInput" 
                           style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer;" 
                           onchange="updateFileName()" required>
                    
                    <div style="font-size: 3rem; color: #8B5CF6; margin-bottom: 15px;">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Dosyanızı sürükleyin veya tıklayın</h3>
                    <p style="color: #a1a1aa; margin-bottom: 5px;">Maksimum dosya boyutu: 100MB</p>
                    <p style="color: #6b7280; font-size: 0.9rem;">PDF, DOC, JPG, PNG, MP4, MP3 desteklenir</p>
                    
                    <div id="fileName" style="
                        margin-top: 15px;
                        padding: 10px;
                        background: rgba(139, 92, 246, 0.1);
                        border-radius: 6px;
                        color: #8B5CF6;
                        font-weight: 600;
                        display: none;
                    "></div>
                </div>
            </div>

            <!-- Açıklama -->
            <div style="margin-bottom: 25px;">
                <label style="
                    display: block;
                    color: white;
                    margin-bottom: 10px;
                    font-weight: 600;
                ">
                    <i class="fas fa-comment"></i>
                    Dosya Açıklaması (Opsiyonel)
                </label>
                <textarea name="description" 
                          style="
                            width: 100%;
                            padding: 15px;
                            border: 1px solid #3f3f46;
                            border-radius: 8px;
                            background: #0a0a0a;
                            color: white;
                            font-family: inherit;
                            resize: vertical;
                            min-height: 100px;
                          " 
                          placeholder="Dosyanız hakkında kısa bir açıklama..."></textarea>
            </div>

            <!-- Yükleme Butonu -->
            <button type="submit" 
                    style="
                        width: 100%;
                        background: linear-gradient(135deg, #8B5CF6, #7C3AED);
                        color: white;
                        padding: 15px;
                        border: none;
                        border-radius: 8px;
                        font-size: 1.1rem;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 10px;
                    " 
                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(139, 92, 246, 0.4)';" 
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                <i class="fas fa-upload"></i>
                Dosyayı Yükle
            </button>
        </form>
    </div>

    <!-- Bilgi Kartları -->
    <div style="
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 40px;
    ">
        <div style="
            background: #1a1a1a;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #3f3f46;
            text-align: center;
        ">
            <div style="font-size: 2rem; color: #8B5CF6; margin-bottom: 15px;">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h4 style="color: white; margin-bottom: 10px;">Güvenli Yükleme</h4>
            <p style="color: #a1a1aa; font-size: 0.9rem;">
                Dosyalarınız şifrelenmiş sunucularda güvende tutulur.
            </p>
        </div>
        
        <div style="
            background: #1a1a1a;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #3f3f46;
            text-align: center;
        ">
            <div style="font-size: 2rem; color: #10b981; margin-bottom: 15px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h4 style="color: white; margin-bottom: 10px;">Admin Onayı</h4>
            <p style="color: #a1a1aa; font-size: 0.9rem;">
                Tüm dosyalar admin onayından sonra yayınlanır.
            </p>
        </div>
        
        <div style="
            background: #1a1a1a;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #3f3f46;
            text-align: center;
        ">
            <div style="font-size: 2rem; color: #f59e0b; margin-bottom: 15px;">
                <i class="fas fa-comments"></i>
            </div>
            <h4 style="color: white; margin-bottom: 10px;">Topluluk Etkileşimi</h4>
            <p style="color: #a1a1aa; font-size: 0.9rem;">
                Onaylanan dosyalar topluluk tarafından yorumlanabilir.
            </p>
        </div>
    </div>
</div>

<script>
    function updateFileName() {
        const fileInput = document.getElementById('fileInput');
        const fileNameDiv = document.getElementById('fileName');
        
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            fileNameDiv.textContent = `Seçilen dosya: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
            fileNameDiv.style.display = 'block';
        } else {
            fileNameDiv.style.display = 'none';
        }
    }

    // Sürükle bırak fonksiyonları
    const dropZone = document.getElementById('dropZone');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        dropZone.style.borderColor = '#8B5CF6';
        dropZone.style.backgroundColor = 'rgba(139, 92, 246, 0.1)';
    }
    
    function unhighlight() {
        dropZone.style.borderColor = '#3f3f46';
        dropZone.style.backgroundColor = 'transparent';
    }
    
    dropZone.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        document.getElementById('fileInput').files = files;
        updateFileName();
    }

    // Form gönderim kontrolü
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        const fileInput = document.getElementById('fileInput');
        if (!fileInput.files.length) {
            e.preventDefault();
            alert('Lütfen bir dosya seçin!');
            return false;
        }
        
        const file = fileInput.files[0];
        const maxSize = 100 * 1024 * 1024; // 100MB
        
        if (file.size > maxSize) {
            e.preventDefault();
            alert('Dosya boyutu 100MB\'tan büyük olamaz!');
            return false;
        }
        
        // Yükleme butonunu devre dışı bırak
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yükleniyor...';
    });
</script>

<?php include 'footer.php'; ?>
