<?php
// index.php - API Entegrasyonlu ONVIBES
session_start();
include 'config.php';

$database = new Database();
$db = $database->getConnection();

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Kategori filtresi
$current_category = isset($_GET['category']) ? $_GET['category'] : 'all';
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Gelişmiş API veri çekme fonksiyonu
function getAPIData($url) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/xml, */*',
            'Accept-Language: tr-TR,tr;q=0.9,en;q=0.8'
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        return $response;
    }
    
    return null;
}

// API'lerden veri çekme
$doviz_data = null;
$puan_durumu = null;
$fikstur_data = null;
$hava_durumu = null;

// Döviz verilerini çek
try {
    $json_data = getAPIData('https://api.tavcan.com/json/piyasalar');
    if ($json_data) {
        $doviz_data = json_decode($json_data, true);
    }
} catch (Exception $e) {
    $doviz_data = null;
}

// Süper Lig puan durumu
try {
    $xml_data = getAPIData('https://api.tavcan.com/xml/superlig.xml');
    if ($xml_data) {
        $puan_durumu = simplexml_load_string($xml_data);
    }
} catch (Exception $e) {
    $puan_durumu = null;
}

// Fikstür verisi
try {
    $fikstur_json = getAPIData('https://api.tavcan.com/json/fikstur');
    if ($fikstur_json) {
        $fikstur_data = json_decode($fikstur_json, true);
    }
} catch (Exception $e) {
    $fikstur_data = null;
}

// Hava durumu verisi
try {
    $hava_json = getAPIData('https://api.tavcan.com/json/havadurumu');
    if ($hava_json) {
        $hava_durumu = json_decode($hava_json, true);
    }
} catch (Exception $e) {
    $hava_durumu = null;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> HABER | ONVIBES - Son Dakika Haberler, Güncel Haberler</title>
    <meta name="description" content="Haberler ve güncel gelişmeler, gündemden ekonomiye son dakika haberler Türkiye'nin en çok takip edilen flaş haber sitesi ONVIBES'te.">
    
    <!-- AdSense -->
    <meta name="google-adsense-account" content="ca-pub-2853730635148966">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2853730635148966" crossorigin="anonymous"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --red: #d2232a;
            --dark: #2c3e50;
            --light: #ffffff;
            --border: #e0e0e0;
            --surface: #f8f9fa;
            --text: #333333;
            --gray: #666666;
            --green: #0a8c2f;
            --blue: #3498db;
            --orange: #e67e22;
        }

        .dark-mode {
            --red: #d2232a;
            --dark: #1a1a1a;
            --light: #2d2d2d;
            --border: #404040;
            --surface: #1a1a1a;
            --text: #e0e0e0;
            --gray: #a0a0a0;
            --green: #2ecc71;
            --blue: #3498db;
            --orange: #e67e22;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            background-attachment: fixed;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Header - Premium Upgrade */
        .main-menu {
            background: var(--light);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08), 0 1px 3px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
        }

        .top-bar {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 6px 0;
            position: relative;
            overflow: hidden;
        }

        .top-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .top-bar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .logo {
            font-size: 0;
            flex-shrink: 0;
        }

        .logo a {
            text-decoration: none;
        }

        .logo-text {
            color: white;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            background: linear-gradient(45deg, #ffffff, #ff6b6b, #ffffff);
            background-size: 200% 200%;
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer-text 3s ease-in-out infinite;
            display: inline-block;
            position: relative;
        }
        
        .logo-text::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
            transform: translateX(-100%);
            animation: shine 2s ease-in-out infinite;
        }
        
        @keyframes shimmer-text {
            0%, 100% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
        }
        
        @keyframes shine {
            0% {
                transform: translateX(-100%);
            }
            100% {
                transform: translateX(100%);
            }
        }

        /* Header Linkler - Enhanced */
        .header-links {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            justify-content: center;
            flex-wrap: wrap;
        }

        .header-link {
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 25px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }

        .header-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .header-link:hover::before {
            left: 100%;
        }

        .header-link:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .header-link i {
            margin-right: 6px;
            font-size: 12px;
        }

        .right-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .top-links {
            display: flex;
            gap: 8px;
        }

        .top-links button,
        .top-links a {
            background: none;
            border: none;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 20px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }

        .top-links button::before,
        .top-links a::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .top-links button:hover::before,
        .top-links a:hover::before {
            width: 100%;
            height: 100%;
        }

        .top-links button:hover,
        .top-links a:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }

        /* Mobil Arama - Enhanced */
        .mobile-search-container {
            display: none;
            padding: 12px 15px;
            background: var(--light);
            border-bottom: 1px solid var(--border);
        }

        .mobile-search-box {
            display: flex;
            background: var(--surface);
            border: 2px solid transparent;
            border-radius: 25px;
            overflow: hidden;
            transition: border-color 0.3s;
        }

        .mobile-search-box:focus-within {
            border-color: var(--red);
        }

        .mobile-search-input {
            flex: 1;
            border: none;
            padding: 12px 18px;
            background: transparent;
            color: var(--text);
            outline: none;
            font-size: 14px;
        }

        .mobile-search-button {
            background: var(--red);
            border: none;
            color: white;
            padding: 0 18px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .mobile-search-button:hover {
            background: #b81d24;
            transform: scale(1.05);
        }

        /* Navigation - Premium */
        #mainnav {
            background: linear-gradient(135deg, var(--dark) 0%, #34495e 100%);
            position: relative;
            overflow: hidden;
        }

        #mainnav::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(255,255,255,0.05) 0%, transparent 70%);
        }

        #mainnav .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .nav-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }
        
        .current-time {
            background: rgba(255,255,255,0.1);
            padding: 6px 12px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            animation: pulse-glow 2s ease-in-out infinite;
        }
        
        .weather-info {
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.1);
            padding: 6px 12px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .weather-icon {
            font-size: 14px;
        }
        
        .temperature {
            font-weight: 700;
            color: #ffd700;
        }
        
        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 0 5px rgba(255,255,255,0.2);
            }
            50% {
                box-shadow: 0 0 15px rgba(255,255,255,0.4);
            }
        }

        .nav-links {
            display: flex;
            list-style: none;
            flex: 1;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .nav-links::-webkit-scrollbar {
            display: none;
        }

        .nav-links li {
            position: relative;
            flex-shrink: 0;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 12px 16px;
            display: block;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }

        .nav-links a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: var(--red);
            transition: all 0.3s;
            transform: translateX(-50%);
        }

        .nav-links a:hover::before,
        .nav-links a.active::before {
            width: 80%;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: var(--red);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(210, 35, 42, 0.4);
        }

        /* Mobil Menü Butonu */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 10px;
        }

        /* Döviz Bar - Premium */
        .doviz-bar {
            background: var(--light);
            border-bottom: 1px solid var(--border);
            padding: 4px 0;
            overflow: hidden;
            position: relative;
        }

        .doviz-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
        }

        .currency-bar ul {
            display: flex;
            list-style: none;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;
            overflow-x: auto;
            gap: 10px;
            padding: 0 5px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .currency-bar ul::-webkit-scrollbar {
            display: none;
        }

        .currency-bar li {
            display: flex;
            align-items: center;
            gap: 3px;
            font-size: 10px;
            font-weight: 800;
            padding: 3px 8px;
            white-space: nowrap;
            flex-shrink: 0;
            background: rgba(0,0,0,0.02);
            border-radius: 15px;
            transition: all 0.3s;
            border: 1px solid transparent;
            min-width: 50px;
            justify-content: center;
        }

        .currency-bar li:hover {
            background: rgba(210, 35, 42, 0.05);
            border-color: rgba(210, 35, 42, 0.1);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .currency-bar .currency-symbol {
            font-size: 12px;
            font-weight: 800;
            color: var(--red);
            min-width: 15px;
            text-align: center;
        }

        .currency-bar .amount {
            font-weight: 800;
            color: var(--dark);
            font-size: 10px;
            min-width: 40px;
            text-align: right;
            letter-spacing: 0.3px;
        }

        .currency-bar .change {
            font-size: 8px;
            padding: 1px 4px;
            border-radius: 8px;
            min-width: 35px;
            text-align: center;
            font-weight: 600;
        }

        .currency-bar .up {
            color: var(--green);
            background: rgba(10, 140, 47, 0.1);
        }

        .currency-bar .down {
            color: var(--red);
            background: rgba(210, 35, 42, 0.1);
        }

        .currency-bar .up::before {
            content: '▲';
            font-size: 8px;
            margin-right: 2px;
        }

        .currency-bar .down::before {
            content: '▼';
            font-size: 8px;
            margin-right: 2px;
        }

        /* Ana İçerik Düzeni - Enhanced */
        .main-content {
            display: grid;
            grid-template-columns: 250px 1fr 300px;
            gap: 25px;
            margin: 25px 0;
        }

        /* Sol Sidebar - Premium */
        .sidebar-left {
            background: var(--light);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .sidebar-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--red), #ff6b6b, var(--red));
            border-radius: 16px 16px 0 0;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--red);
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
        }

        .sidebar-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--orange);
            border-radius: 2px;
        }

        /* Köşe Yazıları Slider - Premium */
        .kose-yazilari-slider {
            position: relative;
            height: 400px;
            overflow: hidden;
            margin-bottom: 25px;
            border-radius: 12px;
            background: var(--surface);
        }

        .kose-yazisi-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: all 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
            display: flex;
            flex-direction: column;
            transform: translateX(30px);
        }

        .kose-yazisi-slide.active {
            opacity: 1;
            transform: translateX(0);
        }

        .kose-yazisi-content {
            flex: 1;
            background: linear-gradient(135deg, var(--surface) 0%, rgba(255,255,255,0.5) 100%);
            padding: 18px;
            border-radius: 12px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .kose-yazisi-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--red), var(--orange));
        }

        .kose-yazisi-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--dark);
            line-height: 1.4;
            letter-spacing: 0.3px;
        }

        .kose-yazisi-excerpt {
            font-size: 13px;
            color: var(--gray);
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .kose-yazisi-author {
            font-size: 12px;
            color: var(--red);
            font-weight: 700;
        }

        .kose-yazisi-date {
            font-size: 11px;
            color: var(--gray);
            font-weight: 500;
        }

        .kose-yazisi-nav {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
            gap: 8px;
        }

        .kose-yazisi-nav button {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .kose-yazisi-nav button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .kose-yazisi-nav button:hover::before {
            left: 100%;
        }

        .kose-yazisi-nav button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(210, 35, 42, 0.4);
        }

        /* Reklam Panosu - Premium */
        .sidebar-ad {
            background: linear-gradient(135deg, var(--light) 0%, rgba(255,255,255,0.8) 100%);
            border: 2px solid var(--red);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .sidebar-ad::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(210, 35, 42, 0.03), transparent);
            animation: rotate 6s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .ad-label {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 10px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .ad-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            position: relative;
            z-index: 1;
        }

        /* Hava Durumu - Premium */
        .hava-durumu {
            background: var(--light);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .hava-durumu::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--blue), #74b9ff);
            border-radius: 16px 16px 0 0;
        }

        .hava-bilgisi {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .hava-icon {
            font-size: 28px;
            color: var(--blue);
            filter: drop-shadow(0 2px 4px rgba(52, 152, 219, 0.3));
        }

        .hava-sicaklik {
            font-size: 24px;
            font-weight: 800;
            color: var(--dark);
            letter-spacing: 1px;
        }

        .hava-durum-text {
            font-size: 14px;
            color: var(--gray);
            text-align: center;
            font-weight: 500;
        }

        .hava-sehir {
            font-size: 12px;
            color: var(--red);
            font-weight: 700;
            text-align: center;
            margin-top: 8px;
            letter-spacing: 0.5px;
        }

        /* Süper Lig Tablosu - Premium */
        .lig-tablosu {
            background: var(--light);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .lig-tablosu::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--green), #00b894);
            border-radius: 16px 16px 0 0;
        }

        .takim-siralamasi {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .takim-siralamasi th {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 8px 4px;
            text-align: center;
            font-weight: 700;
            border-radius: 8px 8px 0 0;
        }

        .takim-siralamasi td {
            padding: 6px 4px;
            text-align: center;
            border-bottom: 1px solid var(--border);
            transition: background 0.3s;
        }

        .takim-siralamasi tr:hover {
            background: var(--surface);
            transform: scale(1.02);
        }

        .takim-adi {
            text-align: left !important;
            font-weight: 700;
            font-size: 10px;
        }

        .siralama-1 { 
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            box-shadow: inset 3px 0 0 #28a745;
        }
        .siralama-2 { 
            background: linear-gradient(135deg, #f0f8ff 0%, #d1ecf1 100%);
            box-shadow: inset 3px 0 0 #17a2b8;
        }
        .siralama-3 { 
            background: linear-gradient(135deg, #fff8e1 0%, #ffeaa7 100%);
            box-shadow: inset 3px 0 0 #ffc107;
        }
        .siralama-4 { 
            background: linear-gradient(135deg, #fff0f0 0%, #f8d7da 100%);
            box-shadow: inset 3px 0 0 #dc3545;
        }

        .dark-mode .siralama-1 { 
            background: linear-gradient(135deg, #1a331a 0%, #2d5a2d 100%);
            box-shadow: inset 3px 0 0 #28a745;
        }
        .dark-mode .siralama-2 { 
            background: linear-gradient(135deg, #1a1f33 0%, #2d4059 100%);
            box-shadow: inset 3px 0 0 #17a2b8;
        }
        .dark-mode .siralama-3 { 
            background: linear-gradient(135deg, #332b1a 0%, #5a4d3a 100%);
            box-shadow: inset 3px 0 0 #ffc107;
        }
        .dark-mode .siralama-4 { 
            background: linear-gradient(135deg, #331a1a 0%, #5a2d2d 100%);
            box-shadow: inset 3px 0 0 #dc3545;
        }

        .dark-mode .currency-bar .currency-symbol {
            color: #ff6b6b;
        }

        /* Fikstür - Premium */
        .fikstur {
            background: var(--light);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .fikstur::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--green) 0%, #00b894);
            border-radius: 16px 16px 0 0;
        }

        .mac-karti {
            background: linear-gradient(135deg, var(--surface) 0%, rgba(255,255,255,0.7) 100%);
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 12px;
            border: 1px solid var(--border);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .mac-karti::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .mac-karti:hover::before {
            left: 100%;
        }

        .mac-karti:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .mac-tarih {
            font-size: 10px;
            color: var(--gray);
            margin-bottom: 6px;
            text-align: center;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .takimlar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
        }

        .ev-sahibi, .deplasman {
            flex: 1;
            transition: color 0.3s;
        }

        .deplasman {
            text-align: right;
        }

        .vs {
            margin: 0 12px;
            color: var(--red);
            font-weight: 800;
            font-size: 12px;
            letter-spacing: 1px;
        }

        /* Orta Bölüm - Ana Slider - Premium */
        .main-middle {
            flex: 1;
        }

        .slider-container {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0,0,0,0.15), 0 6px 20px rgba(0,0,0,0.1);
        }

        .slider-wrapper {
            position: relative;
            height: 380px;
        }

        .slider-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: all 0.8s cubic-bezier(0.25, 0.8, 0.25, 1);
            background-size: cover;
            background-position: center;
            background-color: #e0e0e0;
            transform: scale(1.1);
        }

        .slider-slide.active {
            opacity: 1;
            transform: scale(1);
        }

        .slider-link {
            text-decoration: none;
            color: white;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: flex-end;
        }

        .slider-overlay {
            background: linear-gradient(transparent, rgba(0,0,0,0.9));
            padding: 25px;
            width: 100%;
            position: relative;
        }

        .slider-overlay::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(210, 35, 42, 0.1) 0%, transparent 50%, rgba(0,0,0,0.2) 100%);
            pointer-events: none;
        }

        .slider-category {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 1;
            box-shadow: 0 2px 10px rgba(210, 35, 42, 0.3);
        }

        .slider-title {
            font-size: 20px;
            font-weight: 800;
            line-height: 1.4;
            text-shadow: 0 2px 4px rgba(0,0,0,0.8);
            position: relative;
            z-index: 1;
            letter-spacing: 0.5px;
        }

        /* Slider Kontrolleri - Premium */
        .slider-controls {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            transform: translateY(-50%);
            display: flex;
            justify-content: space-between;
            padding: 0 15px;
            pointer-events: none;
        }

        .slider-controls button {
            background: rgba(0,0,0,0.6);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s;
            pointer-events: all;
            backdrop-filter: blur(10px);
        }

        .slider-controls button:hover {
            background: rgba(0,0,0,0.9);
            transform: scale(1.1);
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .slider-indicators {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
        }

        .slider-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0;
            position: relative;
        }

        .slider-indicator::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: white;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.3s, height 0.3s;
        }

        .slider-indicator.active::before,
        .slider-indicator:hover::before {
            width: 100%;
            height: 100%;
        }

        .slider-counter {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            backdrop-filter: blur(10px);
            letter-spacing: 0.5px;
        }

        /* Sağ Sidebar - Premium */
        .sidebar-right {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .sidebar-section {
            background: var(--light);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .sidebar-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--orange) 0%, #fdcb6e);
            border-radius: 16px 16px 0 0;
        }

        .user-news-item,
        .popular-news-item {
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .user-news-item::before,
        .popular-news-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .user-news-item:hover::before,
        .popular-news-item:hover::before {
            left: 100%;
        }

        .user-news-item:hover,
        .popular-news-item:hover {
            background: var(--surface);
            transform: translateX(5px);
            padding-left: 10px;
        }

        .user-news-item:last-child,
        .popular-news-item:last-child {
            border-bottom: none;
        }

        .user-news-item a,
        .popular-news-item a {
            text-decoration: none;
            color: var(--text);
            display: block;
        }

        .user-news-title,
        .popular-news-title {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.4;
            margin-bottom: 6px;
            color: var(--dark);
        }

        .user-news-meta,
        .popular-news-meta {
            font-size: 11px;
            color: var(--gray);
            display: flex;
            justify-content: space-between;
            font-weight: 500;
        }

        /* Arama Çubuğu - Premium */
        .search-container {
            position: relative;
            margin: 8px 0;
            display: block;
        }

        .search-box {
            display: flex;
            background: var(--light);
            border: 2px solid transparent;
            border-radius: 25px;
            overflow: hidden;
            min-width: 250px;
            transition: border-color 0.3s;
        }

        .search-box:focus-within {
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(210, 35, 42, 0.1);
        }

        .search-input {
            flex: 1;
            border: none;
            padding: 10px 18px;
            background: transparent;
            color: var(--text);
            outline: none;
            font-size: 14px;
        }

        .search-input::placeholder {
            color: var(--gray);
            font-weight: 500;
        }

        .search-button {
            background: var(--red);
            border: none;
            color: white;
            padding: 0 18px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .search-button:hover {
            background: #b81d24;
            transform: scale(1.05);
        }

        /* Grid News - Premium */
        .grid-news {
            margin: 30px 0;
        }

        .section-title {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--red);
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--orange);
            border-radius: 2px;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .news-card {
            background: var(--light);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            cursor: pointer;
            position: relative;
            border: 1px solid transparent;
        }

        .news-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--red), var(--orange), var(--red));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .news-card:hover::before {
            opacity: 1;
        }

        .news-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 10px 30px rgba(0,0,0,0.1);
            border-color: rgba(210, 35, 42, 0.2);
        }

        .news-image {
            height: 180px;
            background-size: cover;
            background-position: center;
            background-color: #e0e0e0;
            position: relative;
            overflow: hidden;
        }

        .news-image::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }

        .news-card:hover .news-image::after {
            transform: translateX(100%);
        }

        .news-content {
            padding: 18px;
        }

        .news-category {
            display: inline-block;
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(210, 35, 42, 0.3);
        }

        .news-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 10px;
            line-height: 1.4;
            color: var(--dark);
            letter-spacing: 0.3px;
        }

        .news-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--gray);
            font-weight: 500;
        }

        /* Footer - Premium */
        footer {
            background: linear-gradient(135deg, var(--dark) 0%, #34495e 100%);
            color: white;
            padding: 40px 0 20px;
            margin-top: 50px;
            position: relative;
            overflow: hidden;
        }

        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--red), var(--orange), var(--red));
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-bottom: 30px;
        }

        .footer-column h3 {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 8px;
        }

        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
            position: relative;
            padding-left: 15px;
        }

        .footer-links a::before {
            content: '→';
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            transform: translateX(-5px);
            transition: all 0.3s;
            color: var(--red);
        }

        .footer-links a:hover {
            color: white;
            transform: translateX(5px);
        }

        .footer-links a:hover::before {
            opacity: 1;
            transform: translateX(0);
        }

        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 14px;
            color: rgba(255,255,255,0.6);
            font-weight: 500;
        }

        /* Advertisement Container */
        .ad-container {
            margin: 25px 0;
            text-align: center;
        }

        .ad-slot {
            background: var(--light);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        /* Responsive - Enhanced */
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar-left,
            .sidebar-right {
                display: none;
            }

            .header-links {
                gap: 10px;
            }

            .header-link {
                font-size: 12px;
                padding: 4px 8px;
            }
        }

        @media (max-width: 768px) {
            .top-bar .container {
                flex-direction: column;
                gap: 10px;
            }

            .header-links {
                order: 3;
                width: 100%;
                justify-content: center;
                margin-top: 8px;
            }

            .search-container {
                display: none;
            }

            .mobile-search-container {
                display: block;
            }

            .nav-links {
                padding: 0 10px;
            }

            .nav-links a {
                padding: 12px 15px;
                font-size: 12px;
            }

            .nav-info {
                display: none;
            }

            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .currency-bar ul {
                justify-content: flex-start;
            }

            .slider-wrapper {
                height: 280px;
            }
            
            .slider-title {
                font-size: 18px;
            }

            .slider-overlay {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .news-grid {
                grid-template-columns: 1fr;
            }
            
            .slider-wrapper {
                height: 220px;
            }
            
            .slider-title {
                font-size: 16px;
            }

            .header-links {
                gap: 5px;
            }

            .header-link {
                font-size: 11px;
                padding: 3px 6px;
            }

            .header-link span {
                display: none;
            }

            .header-link i {
                margin-right: 0;
                font-size: 14px;
            }

            .top-links button span,
            .top-links a span {
                display: none;
            }

            .logo-text {
                font-size: 20px;
            }

            .nav-links a {
                padding: 10px 12px;
                font-size: 11px;
            }
        }

        @media (max-width: 360px) {
            .header-links {
                gap: 3px;
            }

            .header-link {
                padding: 2px;
            }

            .header-link i {
                font-size: 12px;
            }

            .nav-links a {
                padding: 8px 10px;
                font-size: 10px;
            }

            .slider-wrapper {
                height: 200px;
            }
        }

        /* Loading Animation */
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .loading {
            animation: pulse 1.5s ease-in-out infinite;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--surface);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--red), #b81d24);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #b81d24, #9e1a20);
        }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <!-- Header -->
    <header id="header">
        <div class="main-menu">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="container">
                    <h1 class="logo">
                        <a href="index.php" title="ONVIBES">
                            <span class="logo-text">HABER|Onvibes</span>
                        </a>
                    </h1>

                    <!-- Header Linkler - Enhanced -->
                    <div class="header-links">
                        <a href="ilan.php" class="header-link">
                            <i class="fas fa-ad"></i>
                            <span>İlan</span>
                        </a>
                        <a href="yazarlar.php" class="header-link">
                            <i class="fas fa-pen"></i>
                            <span>Yazarlar</span>
                        </a>
                        <a href="piyasalar.php" class="header-link">
                            <i class="fas fa-chart-line"></i>
                            <span>Piyasalar</span>
                        </a>
                        <a href="superlig.php" class="header-link">
                            <i class="fas fa-futbol"></i>
                            <span>Süper Lig</span>
                        </a>
                    </div>

                    <!-- Right Nav -->
                    <div class="right-nav">
                        <!-- Masaüstü Arama Çubuğu -->
                        <div class="search-container">
                            <div class="search-box">
                                <input type="text" class="search-input" placeholder="Haber ara...">
                                <button class="search-button">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Top Links -->
                        <div class="top-links">
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="toggle_theme">
                                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                                    <span><?php echo $dark_mode ? 'Açık' : 'Koyu'; ?></span>
                                </button>
                            </form>
                            <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                                <a href="profil.php" class="user-avatar">
                                    <i class="fas fa-user"></i>
                                    <span>Profil</span>
                                </a>
                            <?php else: ?>
                                <a href="login.php">
                                    <i class="fas fa-user"></i>
                                    <span>Üyelik</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobil Arama Çubuğu -->
            <div class="mobile-search-container">
                <div class="mobile-search-box">
                    <input type="text" class="mobile-search-input" placeholder="Haber ara...">
                    <button class="mobile-search-button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>

            <!-- Navigation -->
            <nav id="mainnav">
                <div class="container">
                    <ul class="nav-links">
                        <li><a href="index.php" class="<?php echo $current_category == 'all' ? 'active' : ''; ?>">Ana Sayfa</a></li>
                        <li><a href="index.php?category=1" class="<?php echo $current_category == '1' ? 'active' : ''; ?>">Gündem</a></li>
                        <li><a href="index.php?category=2" class="<?php echo $current_category == '2' ? 'active' : ''; ?>">Spor</a></li>
                        <li><a href="index.php?category=3" class="<?php echo $current_category == '3' ? 'active' : ''; ?>">Magazin</a></li>
                        <li><a href="index.php?category=4" class="<?php echo $current_category == '4' ? 'active' : ''; ?>">Teknoloji</a></li>
                        <li><a href="index.php?category=5" class="<?php echo $current_category == '5' ? 'active' : ''; ?>">Ekonomi</a></li>
                        <li><a href="index.php?category=6" class="<?php echo $current_category == '6' ? 'active' : ''; ?>">Sağlık</a></li>
                        <li><a href="index.php?category=7" class="<?php echo $current_category == '7' ? 'active' : ''; ?>">Dünya</a></li>
                    </ul>
                    
                    <!-- Nav Info -->
                    <div class="nav-info">
                        <div class="current-time">
                            <i class="fas fa-clock"></i>
                            <span id="current-time"></span>
                        </div>
                        <?php if ($hava_durumu && isset($hava_durumu['sehirler'][0])): 
                            $sehir = $hava_durumu['sehirler'][0];
                            $hava_durum = strtolower($sehir['durum']);
                            $hava_icon = 'fa-sun';
                            
                            if (strpos($hava_durum, 'yağmur') !== false) {
                                $hava_icon = 'fa-cloud-rain';
                            } elseif (strpos($hava_durum, 'kar') !== false) {
                                $hava_icon = 'fa-snowflake';
                            } elseif (strpos($hava_durum, 'bulut') !== false) {
                                $hava_icon = 'fa-cloud';
                            } elseif (strpos($hava_durum, 'güneş') !== false) {
                                $hava_icon = 'fa-sun';
                            } elseif (strpos($hava_durum, 'sis') !== false) {
                                $hava_icon = 'fa-smog';
                            }
                        ?>
                        <div class="weather-info">
                            <i class="fas <?php echo $hava_icon; ?> weather-icon"></i>
                            <span class="temperature"><?php echo $sehir['sicaklik']; ?>°C</span>
                        </div>
                        <?php else: ?>
                        <div class="weather-info">
                            <i class="fas fa-exclamation-triangle weather-icon"></i>
                            <span class="temperature">0°C</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Döviz Bar -->
    <div class="doviz-bar">
        <div class="container">
            <div class="currency-bar">
                <ul>
                    <?php
                    // Dolar
                    if ($doviz_data && isset($doviz_data['piyasalar']['dolar'])) {
                        $dolar = $doviz_data['piyasalar']['dolar'];
                        $dolar_yon = $dolar['degisim'] >= 0 ? 'up' : 'down';
                        $dolar_degisim = $dolar['degisim'] >= 0 ? '+'.$dolar['degisim'] : $dolar['degisim'];
                        echo "<li><span class='currency-symbol'>$</span> <span class='{$dolar_yon}'></span><span class='amount'>{$dolar['alis']}</span><span class='change'>%{$dolar_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>$</span> <span class='down'></span><span class='amount'>0.00</span><span class='change'>%0.00</span></li>";
                    }
                    
                    // Euro
                    if ($doviz_data && isset($doviz_data['piyasalar']['euro'])) {
                        $euro = $doviz_data['piyasalar']['euro'];
                        $euro_yon = $euro['degisim'] >= 0 ? 'up' : 'down';
                        $euro_degisim = $euro['degisim'] >= 0 ? '+'.$euro['degisim'] : $euro['degisim'];
                        echo "<li><span class='currency-symbol'>€</span> <span class='{$euro_yon}'></span><span class='amount'>{$euro['alis']}</span><span class='change'>%{$euro_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>€</span> <span class='down'></span><span class='amount'>0.00</span><span class='change'>%0.00</span></li>";
                    }
                    
                    // Sterlin
                    if ($doviz_data && isset($doviz_data['piyasalar']['sterlin'])) {
                        $sterlin = $doviz_data['piyasalar']['sterlin'];
                        $sterlin_yon = $sterlin['degisim'] >= 0 ? 'up' : 'down';
                        $sterlin_degisim = $sterlin['degisim'] >= 0 ? '+'.$sterlin['degisim'] : $sterlin['degisim'];
                        echo "<li><span class='currency-symbol'>£</span> <span class='{$sterlin_yon}'></span><span class='amount'>{$sterlin['alis']}</span><span class='change'>%{$sterlin_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>£</span> <span class='down'></span><span class='amount'>0.00</span><span class='change'>%0.00</span></li>";
                    }
                    
                    // Altın
                    if ($doviz_data && isset($doviz_data['piyasalar']['altin'])) {
                        $altin = $doviz_data['piyasalar']['altin'];
                        $altin_yon = $altin['degisim'] >= 0 ? 'up' : 'down';
                        $altin_degisim = $altin['degisim'] >= 0 ? '+'.$altin['degisim'] : $altin['degisim'];
                        echo "<li><span class='currency-symbol'>🪙</span> <span class='{$altin_yon}'></span><span class='amount'>{$altin['alis']}</span><span class='change'>%{$altin_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>🪙</span> <span class='down'></span><span class='amount'>0.00</span><span class='change'>%0.00</span></li>";
                    }
                    
                    // Gümüş
                    if ($doviz_data && isset($doviz_data['piyasalar']['gumus'])) {
                        $gumus = $doviz_data['piyasalar']['gumus'];
                        $gumus_yon = $gumus['degisim'] >= 0 ? 'up' : 'down';
                        $gumus_degisim = $gumus['degisim'] >= 0 ? '+'.$gumus['degisim'] : $gumus['degisim'];
                        echo "<li><span class='currency-symbol'>⚪</span> <span class='{$gumus_yon}'></span><span class='amount'>{$gumus['alis']}</span><span class='change'>%{$gumus_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>⚪</span> <span class='down'></span><span class='amount'>0.00</span><span class='change'>%0.00</span></li>";
                    }
                    
                    // BIST 100
                    if ($doviz_data && isset($doviz_data['piyasalar']['bist'])) {
                        $bist = $doviz_data['piyasalar']['bist'];
                        $bist_yon = $bist['degisim'] >= 0 ? 'up' : 'down';
                        $bist_degisim = $bist['degisim'] >= 0 ? '+'.$bist['degisim'] : $bist['degisim'];
                        echo "<li><span class='currency-symbol'>📈</span> <span class='{$bist_yon}'></span><span class='amount'>{$bist['deger']}</span><span class='change'>%{$bist_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>📈</span> <span class='down'></span><span class='amount'>0.00</span><span class='change'>%0.00</span></li>";
                    }
                    
                    // Bitcoin
                    if ($doviz_data && isset($doviz_data['piyasalar']['bitcoin'])) {
                        $bitcoin = $doviz_data['piyasalar']['bitcoin'];
                        $bitcoin_yon = $bitcoin['degisim'] >= 0 ? 'up' : 'down';
                        $bitcoin_degisim = $bitcoin['degisim'] >= 0 ? '+'.$bitcoin['degisim'] : $bitcoin['degisim'];
                        $bitcoin_fiyat = number_format($bitcoin['deger'], 0, ',', '.');
                        echo "<li><span class='currency-symbol'>₿</span> <span class='{$bitcoin_yon}'></span><span class='amount'>{$bitcoin_fiyat}</span><span class='change'>%{$bitcoin_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>₿</span> <span class='down'></span><span class='amount'>0</span><span class='change'>%0.00</span></li>";
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Ana İçerik -->
    <div class="container">
        <div class="main-content">
            <!-- Sol Sidebar - Köşe Yazıları ve Ekstralar -->
            <aside class="sidebar-left">
                <!-- Köşe Yazıları -->
                <h3 class="sidebar-title">Köşe Yazıları</h3>
                <div class="kose-yazilari-slider">
                    <?php
                    if($db) {
                        try {
                            $query = "SELECT ky.id, ky.title, ky.content, ky.created_at, 
                                     ky.author_name, ky.author_avatar, ky.summary
                                     FROM kose_yazisi ky 
                                     WHERE ky.status='approved' 
                                     ORDER BY ky.created_at DESC LIMIT 10";
                            $stmt = $db->prepare($query);
                            $stmt->execute();
                            
                            $slide_index = 0;
                            if($stmt->rowCount() > 0) {
                                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $excerpt = strlen($row['content']) > 120 ? substr($row['content'], 0, 120) . '...' : $row['content'];
                                    $date = date('d.m.Y', strtotime($row['created_at']));
                                    $active_class = $slide_index === 0 ? 'active' : '';
                                    
                                    echo "
                                    <div class='kose-yazisi-slide {$active_class}' data-index='{$slide_index}'>
                                        <div class='kose-yazisi-content'>
                                            <h4 class='kose-yazisi-title'>" . htmlspecialchars($row['title']) . "</h4>
                                            <p class='kose-yazisi-excerpt'>" . htmlspecialchars($excerpt) . "</p>
                                            <div class='kose-yazisi-author'>" . htmlspecialchars($row['author_name']) . "</div>
                                            <div class='kose-yazisi-date'>{$date}</div>
                                        </div>
                                        <div class='kose-yazisi-nav'>
                                            <button class='prev-yazi' onclick='prevKoseYazisi()'><i class='fas fa-chevron-left'></i> Önceki</button>
                                            <button class='next-yazi' onclick='nextKoseYazisi()'>Sonraki <i class='fas fa-chevron-right'></i></button>
                                        </div>
                                    </div>";
                                    $slide_index++;
                                }
                            } else {
                                echo "
                                <div class='kose-yazisi-slide active'>
                                    <div class='kose-yazisi-content'>
                                        <h4 class='kose-yazisi-title'>Henüz Köşe Yazısı Yok</h4>
                                        <p class='kose-yazisi-excerpt'>Köşe yazıları sistemi aktif değil.</p>
                                        <div class='kose-yazisi-author'>Sistem</div>
                                        <div class='kose-yazisi-date'>" . date('d.m.Y') . "</div>
                                    </div>
                                </div>";
                            }
                        } catch (PDOException $e) {
                            echo "
                            <div class='kose-yazisi-slide active'>
                                <div class='kose-yazisi-content'>
                                    <h4 class='kose-yazisi-title'>Veritabanı Hatası</h4>
                                    <p class='kose-yazisi-excerpt'>Köşe yazıları yüklenemedi.</p>
                                    <div class='kose-yazisi-author'>Hata</div>
                                    <div class='kose-yazisi-date'>" . date('d.m.Y') . "</div>
                                </div>
                            </div>";
                        }
                    }
                    ?>
                </div>

                <!-- Reklam Panosu -->
                <div class="sidebar-ad">
                    <div class="ad-label">Reklam</div>
                    <div class="ad-content">
                        <i class="fas fa-ad"></i><br>
                        REKLAM ALANI<br>
                        <small>300x250</small>
                    </div>
                </div>

                <!-- Hava Durumu -->
                <div class="hava-durumu">
                    <h3 class="sidebar-title">Hava Durumu</h3>
                    <?php
                    if ($hava_durumu && isset($hava_durumu['sehirler'][0])) {
                        $sehir = $hava_durumu['sehirler'][0];
                        $hava_durum = strtolower($sehir['durum']);
                        $hava_icon = 'fa-sun';
                        
                        if (strpos($hava_durum, 'yağmur') !== false) {
                            $hava_icon = 'fa-cloud-rain';
                        } elseif (strpos($hava_durum, 'kar') !== false) {
                            $hava_icon = 'fa-snowflake';
                        } elseif (strpos($hava_durum, 'bulut') !== false) {
                            $hava_icon = 'fa-cloud';
                        } elseif (strpos($hava_durum, 'güneş') !== false) {
                            $hava_icon = 'fa-sun';
                        } elseif (strpos($hava_durum, 'sis') !== false) {
                            $hava_icon = 'fa-smog';
                        }
                        
                        echo "
                        <div class='hava-bilgisi'>
                            <div class='hava-icon'><i class='fas {$hava_icon}'></i></div>
                            <div class='hava-sicaklik'>{$sehir['sicaklik']}°C</div>
                        </div>
                        <div class='hava-durum-text'>{$sehir['durum']}</div>
                        <div class='hava-sehir'>{$sehir['isim']}</div>";
                    } else {
                        echo "
                        <div class='hava-bilgisi'>
                            <div class='hava-icon'><i class='fas fa-exclamation-triangle'></i></div>
                            <div class='hava-sicaklik'>0°C</div>
                        </div>
                        <div class='hava-durum-text'>API Çalışmıyor</div>
                        <div class='hava-sehir'>Veri Yok</div>";
                    }
                    ?>
                </div>

                <!-- Süper Lig Puan Durumu -->
                <div class="lig-tablosu">
                    <h3 class="sidebar-title">Süper Lig Puan Durumu</h3>
                    <table class="takim-siralamasi">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Takım</th>
                                <th>P</th>
                                <th>O</th>
                                <th>G</th>
                                <th>B</th>
                                <th>M</th>
                                <th>A</th>
                                <th>Y</th>
                                <th>Av</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($puan_durumu && isset($puan_durumu->takim)) {
                                $counter = 0;
                                foreach ($puan_durumu->takim as $takim) {
                                    $counter++;
                                    $siralama_class = '';
                                    if ($counter == 1) $siralama_class = 'siralama-1';
                                    elseif ($counter == 2) $siralama_class = 'siralama-2';
                                    elseif ($counter == 3) $siralama_class = 'siralama-3';
                                    elseif ($counter == 4) $siralama_class = 'siralama-4';
                                    
                                    echo "
                                    <tr class='{$siralama_class}'>
                                        <td>{$counter}</td>
                                        <td class='takim-adi'>{$takim->isim}</td>
                                        <td><strong>{$takim->puan}</strong></td>
                                        <td>{$takim->oynanan}</td>
                                        <td>{$takim->galibiyet}</td>
                                        <td>{$takim->beraberlik}</td>
                                        <td>{$takim->maglubiyet}</td>
                                        <td>{$takim->atilan}</td>
                                        <td>{$takim->yenilen}</td>
                                        <td>{$takim->averaj}</td>
                                    </tr>";
                                    if ($counter >= 5) break;
                                }
                            } else {
                                echo "
                                <tr>
                                    <td colspan='10' style='text-align: center; padding: 20px; color: var(--gray);'>
                                        <i class='fas fa-exclamation-triangle'></i><br>
                                        Puan durumu yüklenemedi
                                    </td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Fikstür -->
                <div class="fikstur">
                    <h3 class="sidebar-title">Sonraki Maçlar</h3>
                    <?php
                    if ($fikstur_data && isset($fikstur_data['maclar'])) {
                        $mac_sayisi = 0;
                        foreach ($fikstur_data['maclar'] as $mac) {
                            if ($mac_sayisi >= 3) break;
                            
                            $tarih = date('d.m.Y | H:i', strtotime($mac['tarih']));
                            echo "
                            <div class='mac-karti'>
                                <div class='mac-tarih'>{$tarih}</div>
                                <div class='takimlar'>
                                    <span class='ev-sahibi'>{$mac['evSahibi']}</span>
                                    <span class='vs'>VS</span>
                                    <span class='deplasman'>{$mac['deplasman']}</span>
                                </div>
                            </div>";
                            $mac_sayisi++;
                        }
                    } else {
                        echo "
                        <div class='mac-karti'>
                            <div class='mac-tarih'>--.--.---- | --:--</div>
                            <div class='takimlar'>
                                <span class='ev-sahibi'>API Çalışmıyor</span>
                                <span class='vs'>VS</span>
                                <span class='deplasman'>Veri Yok</span>
                            </div>
                        </div>";
                    }
                    ?>
                </div>
            </aside>

            <!-- Orta Bölüm - Ana İçerik -->
            <main class="main-middle">
                <!-- Ana Slider -->
                <section class="slider-container">
                    <div class="slider-wrapper" id="main-slider">
                        <?php
                        if($db) {
                            try {
                                $one_week_ago = date('Y-m-d H:i:s', strtotime('-1 week'));
                                $query = "SELECT n.id, n.title, n.image, c.name as category_name 
                                         FROM news n 
                                         LEFT JOIN categories c ON n.category_id = c.id 
                                         WHERE n.status='approved' AND n.created_at >= :one_week_ago
                                         ORDER BY n.created_at DESC LIMIT 10";
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(':one_week_ago', $one_week_ago);
                                $stmt->execute();
                                
                                $slide_index = 0;
                                if($stmt->rowCount() > 0) {
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $active_class = $slide_index === 0 ? 'active' : '';
                                        $image_url = $row['image'] ?: 'https://picsum.photos/800/400?random=' . $row['id'];
                                        echo "
                                        <div class='slider-slide {$active_class}' data-index='{$slide_index}' style='background-image: url(\"{$image_url}\")'>
                                            <a href='haber-detay.php?id={$row['id']}' class='slider-link'>
                                                <div class='slider-overlay'>
                                                    <span class='slider-category'>" . htmlspecialchars($row['category_name']) . "</span>
                                                    <h2 class='slider-title'>" . htmlspecialchars($row['title']) . "</h2>
                                                </div>
                                            </a>
                                        </div>";
                                        $slide_index++;
                                    }
                                } else {
                                    echo "
                                    <div class='slider-slide active' style='background-image: url(https://picsum.photos/800/400?random=1)'>
                                        <div class='slider-overlay'>
                                            <span class='slider-category'>Bilgi</span>
                                            <h2 class='slider-title'>Henüz haber bulunmamaktadır</h2>
                                        </div>
                                    </div>";
                                }
                            } catch (PDOException $e) {
                                echo "
                                <div class='slider-slide active' style='background-image: url(https://picsum.photos/800/400?random=1)'>
                                    <div class='slider-overlay'>
                                        <span class='slider-category'>Hata</span>
                                        <h2 class='slider-title'>Haberler yüklenemedi</h2>
                                    </div>
                                </div>";
                            }
                        }
                        ?>
                    </div>
                    
                    <!-- Slider Kontrolleri -->
                    <div class="slider-controls">
                        <button class="slider-prev" onclick="prevSlide()">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="slider-next" onclick="nextSlide()">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    
                    <div class="slider-indicators" id="slider-indicators">
                        <!-- Slider göstergeleri JavaScript ile eklenecek -->
                    </div>
                    
                    <div class="slider-counter" id="slider-counter">
                        <!-- Slider sayacı JavaScript ile eklenecek -->
                    </div>
                </section>

                <!-- Kategori Haberleri -->
                <section class="grid-news" id="category-news">
                    <h2 class="section-title" id="category-title">
                        <?php
                        $category_titles = [
                            'all' => 'Öne Çıkan Haberler',
                            '1' => 'Gündem Haberleri',
                            '2' => 'Spor Haberleri', 
                            '3' => 'Magazin Haberleri',
                            '4' => 'Teknoloji Haberleri',
                            '5' => 'Ekonomi Haberleri',
                            '6' => 'Sağlık Haberleri',
                            '7' => 'Dünya Haberleri'
                        ];
                        echo $category_titles[$current_category] ?? 'Haberler';
                        ?>
                    </h2>
                    
                    <!-- Advertisement -->
                    <div class="ad-container">
                        <div class="ad-slot">
                            <ins class="adsbygoogle"
                                 style="display:block"
                                 data-ad-client="ca-pub-2853730635148966"
                                 data-ad-slot="1234567890"
                                 data-ad-format="auto"
                                 data-full-width-responsive="true"></ins>
                            <script>
                                 (adsbygoogle = window.adsbygoogle || []).push({});
                            </script>
                        </div>
                    </div>

                    <div class="news-grid" id="news-container">
                        <?php
                        if($db) {
                            try {
                                $query = "SELECT n.id, n.title, n.summary, n.image, c.name as category_name, 
                                         n.view_count, n.created_at 
                                         FROM news n 
                                         LEFT JOIN categories c ON n.category_id = c.id 
                                         WHERE n.status='approved'";
                                
                                if ($current_category != 'all') {
                                    $query .= " AND n.category_id = :category_id";
                                }
                                
                                $query .= " ORDER BY n.created_at DESC LIMIT 12";
                                
                                $stmt = $db->prepare($query);
                                
                                if ($current_category != 'all') {
                                    $stmt->bindParam(':category_id', $current_category);
                                }
                                
                                $stmt->execute();
                                
                                if($stmt->rowCount() > 0) {
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $time_ago = time_elapsed_string($row['created_at']);
                                        $image_url = $row['image'] ?: 'https://picsum.photos/400/200?random=' . $row['id'];
                                        echo "
                                        <div class='news-card' onclick=\"window.location.href='haber-detay.php?id={$row['id']}'\">
                                            <div class='news-image' style='background-image: url(\"{$image_url}\")'></div>
                                            <div class='news-content'>
                                                <span class='news-category'>" . htmlspecialchars($row['category_name']) . "</span>
                                                <h3 class='news-title'>" . htmlspecialchars($row['title']) . "</h3>
                                                <div class='news-meta'>
                                                    <span><i class='far fa-clock'></i> {$time_ago}</span>
                                                    <span><i class='far fa-eye'></i> " . number_format($row['view_count']) . "</span>
                                                </div>
                                            </div>
                                        </div>";
                                    }
                                } else {
                                    echo "<p style='text-align: center; padding: 40px; color: var(--gray);'>Bu kategoride henüz haber bulunmamaktadır.</p>";
                                }
                            } catch (PDOException $e) {
                                echo "<p style='text-align: center; padding: 40px; color: var(--red);'>Haberler yüklenirken bir hata oluştu.</p>";
                            }
                        }
                        ?>
                    </div>
                </section>
            </main>

            <!-- Sağ Sidebar -->
            <aside class="sidebar-right">
                <!-- Kullanıcı Haberleri -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">Kullanıcı Haberleri</h3>
                    <div class="user-news-list">
                        <?php
                        if($db) {
                            try {
                                $query = "SELECT n.id, n.title, n.created_at, u.username, n.author_name 
                                         FROM news n 
                                         LEFT JOIN users u ON n.author_id = u.id 
                                         WHERE n.status='approved' AND n.author_id IS NOT NULL
                                         ORDER BY n.created_at DESC LIMIT 5";
                                $stmt = $db->prepare($query);
                                $stmt->execute();
                                
                                if($stmt->rowCount() > 0) {
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $time_ago = time_elapsed_string($row['created_at']);
                                        $author = $row['username'] ?: ($row['author_name'] ?: 'Anonim');
                                        echo "
                                        <div class='user-news-item'>
                                            <a href='haber-detay.php?id={$row['id']}'>
                                                <div class='user-news-title'>" . htmlspecialchars($row['title']) . "</div>
                                                <div class='user-news-meta'>
                                                    <span>@" . htmlspecialchars($author) . "</span>
                                                    <span>{$time_ago}</span>
                                                </div>
                                            </a>
                                        </div>";
                                    }
                                } else {
                                    echo "<p style='text-align: center; color: var(--gray);'>Henüz kullanıcı haberi yok</p>";
                                }
                            } catch (PDOException $e) {
                                echo "<p style='text-align: center; color: var(--red);'>Kullanıcı haberleri yüklenemedi</p>";
                            }
                        }
                        ?>
                    </div>
                </div>

                <!-- Önerilen Haberler -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">Çok Okunanlar</h3>
                    <div class="popular-news-list">
                        <?php
                        if($db) {
                            try {
                                $one_week_ago = date('Y-m-d H:i:s', strtotime('-1 week'));
                                $query = "SELECT n.id, n.title, n.view_count, n.created_at 
                                         FROM news n 
                                         WHERE n.status='approved' AND n.created_at >= :one_week_ago
                                         ORDER BY n.view_count DESC LIMIT 5";
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(':one_week_ago', $one_week_ago);
                                $stmt->execute();
                                
                                if($stmt->rowCount() > 0) {
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $time_ago = time_elapsed_string($row['created_at']);
                                        echo "
                                        <div class='popular-news-item'>
                                            <a href='haber-detay.php?id={$row['id']}'>
                                                <div class='popular-news-title'>" . htmlspecialchars($row['title']) . "</div>
                                                <div class='popular-news-meta'>
                                                    <span>" . number_format($row['view_count']) . " okunma</span>
                                                    <span>{$time_ago}</span>
                                                </div>
                                            </a>
                                        </div>";
                                    }
                                } else {
                                    echo "<p style='text-align: center; color: var(--gray);'>Henüz çok okunan haber yok</p>";
                                }
                            } catch (PDOException $e) {
                                echo "<p style='text-align: center; color: var(--red);'>Çok okunanlar yüklenemedi</p>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>ONVIBES</h3>
                    <ul class="footer-links">
                        <li><a href="hakkimizda.php">Hakkımızda</a></li>
                        <li><a href="iletisim.php">İletişim</a></li>
                        <li><a href="kariyer.php">Kariyer</a></li>
                        <li><a href="reklam.php">Reklam</a></li>
                        <li><a href="kunye.php">Künye</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Kategoriler</h3>
                    <ul class="footer-links">
                        <li><a href="index.php?category=1">Gündem</a></li>
                        <li><a href="index.php?category=2">Spor</a></li>
                        <li><a href="index.php?category=3">Magazin</a></li>
                        <li><a href="index.php?category=4">Teknoloji</a></li>
                        <li><a href="index.php?category=5">Ekonomi</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Yardım</h3>
                    <ul class="footer-links">
                        <li><a href="sss.php">SSS</a></li>
                        <li><a href="kullanim.php">Kullanım Koşulları</a></li>
                        <li><a href="gizlilik.php">Gizlilik Politikası</a></li>
                        <li><a href="cerez.php">Çerez Politikası</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Bizi Takip Edin</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                        <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                        <li><a href="#"><i class="fab fa-youtube"></i> YouTube</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; 2024 ONVIBES - Tüm hakları saklıdır.
            </div>
        </div>
    </footer>

    <script>
        // Enhanced JavaScript kodları
        let currentSlide = 0;
        let slideInterval;
        const slides = document.querySelectorAll('.slider-slide');
        const indicatorsContainer = document.getElementById('slider-indicators');
        const counterContainer = document.getElementById('slider-counter');

        function createSliderUI() {
            if (!indicatorsContainer) return;
            
            indicatorsContainer.innerHTML = '';
            slides.forEach((_, index) => {
                const indicator = document.createElement('div');
                indicator.className = `slider-indicator ${index === 0 ? 'active' : ''}`;
                indicator.setAttribute('data-index', index);
                indicator.addEventListener('click', () => goToSlide(index));
                indicator.addEventListener('mouseenter', () => pauseSlider());
                indicator.addEventListener('mouseleave', () => startSlider());
                indicatorsContainer.appendChild(indicator);
            });
            
            updateCounter();
        }

        function updateCounter() {
            if (counterContainer) {
                counterContainer.textContent = `${currentSlide + 1} / ${slides.length}`;
            }
        }

        function goToSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            document.querySelectorAll('.slider-indicator').forEach(ind => ind.classList.remove('active'));
            
            if (slides[index]) {
                slides[index].classList.add('active');
            }
            const indicator = document.querySelector(`.slider-indicator[data-index="${index}"]`);
            if (indicator) {
                indicator.classList.add('active');
            }
            currentSlide = index;
            updateCounter();
        }

        function prevSlide() {
            currentSlide = (currentSlide - 1 + slides.length) % slides.length;
            goToSlide(currentSlide);
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            goToSlide(currentSlide);
        }

        function startSlider() {
            if (slides.length > 1) {
                slideInterval = setInterval(nextSlide, 5000);
            }
        }

        function pauseSlider() {
            clearInterval(slideInterval);
        }

        let currentKoseYazisi = 0;
        const koseYazilari = document.querySelectorAll('.kose-yazisi-slide');

        function goToKoseYazisi(index) {
            koseYazilari.forEach(slide => slide.classList.remove('active'));
            if (koseYazilari[index]) {
                koseYazilari[index].classList.add('active');
            }
            currentKoseYazisi = index;
        }

        function nextKoseYazisi() {
            if (koseYazilari.length > 0) {
                currentKoseYazisi = (currentKoseYazisi + 1) % koseYazilari.length;
                goToKoseYazisi(currentKoseYazisi);
            }
        }

        function prevKoseYazisi() {
            if (koseYazilari.length > 0) {
                currentKoseYazisi = (currentKoseYazisi - 1 + koseYazilari.length) % koseYazilari.length;
                goToKoseYazisi(currentKoseYazisi);
            }
        }

        // Arama fonksiyonları
        function performSearch() {
            const searchTerm = document.querySelector('.search-input')?.value.trim() || 
                             document.querySelector('.mobile-search-input')?.value.trim();
            if (searchTerm) {
                window.location.href = `arama.php?q=${encodeURIComponent(searchTerm)}`;
            }
        }

        // Event listener'lar
        document.addEventListener('DOMContentLoaded', function() {
            createSliderUI();
            startSlider();
            
            const slider = document.querySelector('.slider-wrapper');
            if (slider) {
                slider.addEventListener('mouseenter', pauseSlider);
                slider.addEventListener('mouseleave', startSlider);
            }

            // Masaüstü arama
            const desktopSearchBtn = document.querySelector('.search-button');
            const desktopSearchInput = document.querySelector('.search-input');
            
            if (desktopSearchBtn) {
                desktopSearchBtn.addEventListener('click', performSearch);
            }
            
            if (desktopSearchInput) {
                desktopSearchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        performSearch();
                    }
                });
            }

            // Mobil arama
            const mobileSearchBtn = document.querySelector('.mobile-search-button');
            const mobileSearchInput = document.querySelector('.mobile-search-input');
            
            if (mobileSearchBtn) {
                mobileSearchBtn.addEventListener('click', performSearch);
            }
            
            if (mobileSearchInput) {
                mobileSearchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        performSearch();
                    }
                });
            }

            // Smooth scroll to top button (optional enhancement)
            const scrollToTop = () => {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            };

            // Add scroll to top functionality
            if (document.body.scrollHeight > window.innerHeight) {
                const scrollBtn = document.createElement('button');
                scrollBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
                scrollBtn.style.cssText = `
                    position: fixed;
                    bottom: 30px;
                    right: 30px;
                    width: 50px;
                    height: 50px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
                    color: white;
                    border: none;
                    cursor: pointer;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                    z-index: 1000;
                    opacity: 0;
                    transform: translateY(100px);
                    transition: all 0.3s;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 18px;
                `;
                
                scrollBtn.addEventListener('click', scrollToTop);
                document.body.appendChild(scrollBtn);

                window.addEventListener('scroll', () => {
                    if (window.pageYOffset > 300) {
                        scrollBtn.style.opacity = '1';
                        scrollBtn.style.transform = 'translateY(0)';
                    } else {
                        scrollBtn.style.opacity = '0';
                        scrollBtn.style.transform = 'translateY(100px)';
                    }
                });
            }

            // Current time update
            function updateTime() {
                const timeElement = document.getElementById('current-time');
                if (timeElement) {
                    const now = new Date();
                    const timeString = now.toLocaleTimeString('tr-TR', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    timeElement.textContent = timeString;
                }
            }
            
            // Update time immediately and then every second
            updateTime();
            setInterval(updateTime, 1000);
        });
    </script>
</body>
</html>

<?php
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'yıl',
        'm' => 'ay',
        'w' => 'hafta',
        'd' => 'gün',
        'h' => 'saat',
        'i' => 'dakika',
        's' => 'saniye',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' önce' : 'şimdi';
}
?>
