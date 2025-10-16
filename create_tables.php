<?php
require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Tablo Oluşturma - Onvibes Drive</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0a0a0a; color: white; padding: 20px; }
        .success { color: #10B981; padding: 10px; background: rgba(16, 185, 129, 0.1); margin: 5px 0; border-radius: 5px; }
        .error { color: #EF4444; padding: 10px; background: rgba(239, 68, 68, 0.1); margin: 5px 0; border-radius: 5px; }
        .info { color: #8B5CF6; padding: 10px; background: rgba(139, 92, 246, 0.1); margin: 5px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>📊 Onvibes Drive - Tablo Oluşturma</h1>";

// Tabloları oluştur
$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        folder VARCHAR(100) NOT NULL,
        role ENUM('user', 'admin') DEFAULT 'user',
        is_verified BOOLEAN DEFAULT 1,
        verification_token VARCHAR(100),
        profile_picture VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS files (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        filename VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size BIGINT,
        file_type VARCHAR(50),
        is_approved BOOLEAN DEFAULT 1,
        is_public BOOLEAN DEFAULT 0,
        share_token VARCHAR(100) DEFAULT NULL,
        description TEXT,
        download_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($tables as $table) {
    try {
        $db->exec($table);
        echo "<div class='success'>✅ Tablo başarıyla oluşturuldu/mevcut</div>";
    } catch(PDOException $e) {
        echo "<div class='error'>❌ Tablo hatası: " . $e->getMessage() . "</div>";
    }
}

// Eksik sütunları kontrol et ve ekle
$columns_to_check = [
    'files' => [
        'download_count' => "ALTER TABLE files ADD COLUMN download_count INT DEFAULT 0",
        'description' => "ALTER TABLE files ADD COLUMN description TEXT",
        'share_token' => "ALTER TABLE files ADD COLUMN share_token VARCHAR(100) DEFAULT NULL",
        'is_public' => "ALTER TABLE files ADD COLUMN is_public BOOLEAN DEFAULT 0"
    ]
];

foreach ($columns_to_check as $table => $columns) {
    foreach ($columns as $column_name => $alter_sql) {
        try {
            // Sütunun var olup olmadığını kontrol et
            $check = $db->query("SHOW COLUMNS FROM $table LIKE '$column_name'")->fetch();
            if (!$check) {
                $db->exec($alter_sql);
                echo "<div class='info'>📝 $table tablosuna $column_name sütunu eklendi</div>";
            } else {
                echo "<div class='success'>✅ $table.$column_name sütunu mevcut</div>";
            }
        } catch(PDOException $e) {
            echo "<div class='error'>❌ $column_name eklenirken hata: " . $e->getMessage() . "</div>";
        }
    }
}

echo "<div class='info' style='margin-top: 20px;'>
    <h3>🎉 Kurulum Tamamlandı!</h3>
    <p><a href='index.php' style='color: #8B5CF6;'>Ana Sayfaya Git</a></p>
</div>";

echo "</body></html>";
?>
