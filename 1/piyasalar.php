<?php
// piyasalar.php - Piyasa Verileri Sayfası
session_start();

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Config dosyasını include et
include 'config.php';

// Dark mode kontrolü
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Hava durumu simülasyonu (API olmadığı için)
$hava_durumu = [
    'sicaklik' => 22,
    'durum' => 'Güneşli',
    'sehir' => 'İstanbul'
];

// Hava durumu ikonu belirleme
$hava_icon = 'fa-sun';
if (strpos($hava_durumu['durum'], 'yağmur') !== false) {
    $hava_icon = 'fa-cloud-rain';
} elseif (strpos($hava_durumu['durum'], 'kar') !== false) {
    $hava_icon = 'fa-snowflake';
} elseif (strpos($hava_durumu['durum'], 'bulut') !== false) {
    $hava_icon = 'fa-cloud';
} elseif (strpos($hava_durumu['durum'], 'güneş') !== false) {
    $hava_icon = 'fa-sun';
} elseif (strpos($hava_durumu['durum'], 'sis') !== false) {
    $hava_icon = 'fa-smog';
}

// Piyasa Verileri Sınıfı
class PiyasaVerileriSystem {
    private $base_url = "https://query1.finance.yahoo.com/v8/finance/chart/";
    private $spread_oran = 0.0025; // %0.25 alış-satış farkı

    public function __construct() {
        // Database bağlantısı gerekmiyor
    }

    private function haftaSonuKontrol() {
        $gun = date('w');
        return ($gun == 0 || $gun == 6);
    }

    private function mesaiSaatleriKontrol() {
        $baslangic = date('Y-m-d 09:00:00');
        $bitis = date('Y-m-d 18:00:00');
        $simdi = date('Y-m-d H:i:s');
        return ($simdi >= $baslangic && $simdi <= $bitis && !$this->haftaSonuKontrol());
    }

    private function getAPIData($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($http_code === 200 && $response) ? $response : null;
    }

    private function getPreviousClose($symbol) {
        // Bu fonksiyon artık kullanılmıyor, previousClose doğrudan getPiyasaVerisi'den gelir
        return null;
    }

    private function sembolKontrol($symbol) {
        $hisseSenedi = in_array($symbol, ['XU100.IS']);
        $doviz = in_array($symbol, ['TRY=X', 'EURTRY=X', 'GBPTRY=X']);
        
        return [
            'hisse' => $hisseSenedi,
            'doviz' => $doviz,
            'emtia' => in_array($symbol, ['GC=F', 'SI=F', 'BZ=F']),
            'kripto' => in_array($symbol, ['BTC-USD'])
        ];
    }

    private function hesaplaAlisSatis($fiyat) {
        $spread = $fiyat * $this->spread_oran;
        return [
            'alis' => $fiyat - ($spread / 2),
            'satis' => $fiyat + ($spread / 2)
        ];
    }

    public function getPiyasaVerisi($symbol) {
        $url = $this->base_url . $symbol;
        $data = $this->getAPIData($url);
        
        if(!$data) {
            return ['error' => 'Veri alınamadı', 'symbol' => $symbol];
        }
        
        $result = json_decode($data, true);
        
        if(isset($result['chart']['result'][0])) {
            $chart = $result['chart']['result'][0];
            $meta = $chart['meta'];
            
            $price = $meta['regularMarketPrice'] ?? ($meta['previousClose'] ?? 0);
            $previousClose = $meta['previousClose'] ?? $price;
            $currency = $meta['currency'] ?? '';
            
            // Piyasa durumu kontrol et
            $sembolTip = $this->sembolKontrol($symbol);
            $haftaSonu = $this->haftaSonuKontrol();
            $mesaiDisi = !$this->mesaiSaatleriKontrol();
            
            // Durum belirle
            $isCurrentPrice = true;
            $status = 'success';
            $statusText = 'Aktif';
            
            if($haftaSonu && $sembolTip['hisse']) {
                $status = 'closed';
                $statusText = 'Hafta sonu';
                $isCurrentPrice = false;
            } elseif($mesaiDisi && $sembolTip['hisse']) {
                $status = 'after_hours';
                $statusText = 'Mesai dışı';
                $isCurrentPrice = false;
            } elseif($haftaSonu && !$sembolTip['kripto']) {
                $status = 'closed';
                $statusText = 'Hafta sonu';
                $isCurrentPrice = false;
            }
            
            // Önceki kapanış fiyatı ile değişim hesapla
            $comparisonPrice = $previousClose;
            
            // Gerçek değişimi hesapla
            $change = $price - $comparisonPrice;
            $change_percent = $comparisonPrice != 0 ? ($change / $comparisonPrice * 100) : 0;
            
            // Alış-Satış hesapla
            $alisSatis = $this->hesaplaAlisSatis($price);
            
            return [
                'symbol' => $meta['symbol'] ?? $symbol,
                'price' => $price,
                'alis' => $alisSatis['alis'],
                'satis' => $alisSatis['satis'],
                'previous_close' => $previousClose,
                'change' => $change,
                'change_percent' => $change_percent,
                'currency' => $currency,
                'last_update' => date('Y-m-d H:i:s'),
                'status' => $status,
                'status_text' => $statusText,
                'is_current_price' => $isCurrentPrice,
                'comparison_price' => $comparisonPrice
            ];
        }
        
        return ['error' => 'Hatalı veri formatı', 'symbol' => $symbol];
    }

    private function altinHesapla($onsAltin, $onsPreviousClose, $usdtry, $usdPreviousClose, $gumusOns = 0) {
        $ons_gram = 31.1035; // 1 ONS = 31.1035 gram
        $gram24 = ($onsAltin * $usdtry) / $ons_gram; // 1 gram 24 ayar altın fiyatı (TL)
        
        // Önceki gram altın fiyatını hesapla
        $previousGram24 = ($onsPreviousClose * $usdPreviousClose) / $ons_gram;
        
        // Ons altın için değişim hesapla
        $onsChangePercent = 0;
        if($onsPreviousClose && $onsPreviousClose != 0) {
            $onsChangePercent = (($onsAltin - $onsPreviousClose) / $onsPreviousClose) * 100;
        }
        
        // Gram altın için değişim hesapla
        $gramChangePercent = 0;
        if($previousGram24 && $previousGram24 != 0) {
            $gramChangePercent = (($gram24 - $previousGram24) / $previousGram24) * 100;
        }
        
        $altinlar = [
            'HAS_ALTIN' => [
                'isim' => 'Has Altın',
                'alis' => $gram24 - ($gram24 * $this->spread_oran / 2),
                'satis' => $gram24 + ($gram24 * $this->spread_oran / 2),
                'birim' => '₺',
                'change_percent' => $gramChangePercent
            ],
            'ONS' => [
                'isim' => 'Ons Altın',
                'alis' => $onsAltin - ($onsAltin * $this->spread_oran / 2),
                'satis' => $onsAltin + ($onsAltin * $this->spread_oran / 2),
                'birim' => '$',
                'change_percent' => $onsChangePercent
            ],
            'GRAM_22' => [
                'isim' => '22 Ayar Gram',
                'alis' => ($gram24 * (22/24)) - (($gram24 * (22/24)) * $this->spread_oran / 2),
                'satis' => ($gram24 * (22/24)) + (($gram24 * (22/24)) * $this->spread_oran / 2),
                'birim' => '₺',
                'change_percent' => $gramChangePercent
            ],
            'GRAM_24' => [
                'isim' => '24 Ayar Gram',
                'alis' => $gram24 - ($gram24 * $this->spread_oran / 2),
                'satis' => $gram24 + ($gram24 * $this->spread_oran / 2),
                'birim' => '₺',
                'change_percent' => $gramChangePercent
            ],
            'CEYREK' => [
                'isim' => 'Çeyrek Altın',
                'alis' => ($gram24 * 1.75) - (($gram24 * 1.75) * $this->spread_oran / 2),
                'satis' => ($gram24 * 1.75) + (($gram24 * 1.75) * $this->spread_oran / 2),
                'birim' => '₺',
                'change_percent' => $gramChangePercent
            ],
            'YARIM' => [
                'isim' => 'Yarım Altın',
                'alis' => ($gram24 * 3.5) - (($gram24 * 3.5) * $this->spread_oran / 2),
                'satis' => ($gram24 * 3.5) + (($gram24 * 3.5) * $this->spread_oran / 2),
                'birim' => '₺',
                'change_percent' => $gramChangePercent
            ],
            'TAM' => [
                'isim' => 'Tam Altın',
                'alis' => ($gram24 * 7) - (($gram24 * 7) * $this->spread_oran / 2),
                'satis' => ($gram24 * 7) + (($gram24 * 7) * $this->spread_oran / 2),
                'birim' => '₺',
                'change_percent' => $gramChangePercent
            ],
            'ATA' => [
                'isim' => 'Ata Altın',
                'alis' => ($gram24 * 7.2) - (($gram24 * 7.2) * $this->spread_oran / 2),
                'satis' => ($gram24 * 7.2) + (($gram24 * 7.2) * $this->spread_oran / 2),
                'birim' => '₺',
                'change_percent' => $gramChangePercent
            ],
            'ATA5' => [
                'isim' => 'Ata 5li',
                'alis' => ($gram24 * 7.2 * 5) - (($gram24 * 7.2 * 5) * $this->spread_oran / 2),
                'satis' => ($gram24 * 7.2 * 5) + (($gram24 * 7.2 * 5) * $this->spread_oran / 2),
                'birim' => '₺',
                'change_percent' => $gramChangePercent
            ],
            'GREMSE' => [
                'isim' => 'Gremse Altın',
                'alis' => ($gram24 * 7.2) - (($gram24 * 7.2) * $this->spread_oran / 2),
                'satis' => ($gram24 * 7.2) + (($gram24 * 7.2) * $this->spread_oran / 2),
                'birim' => '₺',
                'change_percent' => $gramChangePercent
            ],
            'GRAM_14' => [
                'isim' => '14 Ayar Gram',
                'alis' => ($gram24 * (14/24)) - (($gram24 * (14/24)) * $this->spread_oran / 2),
                'satis' => ($gram24 * (14/24)) + (($gram24 * (14/24)) * $this->spread_oran / 2),
                'birim' => '₺',
                'change_percent' => $gramChangePercent
            ],
            'GUMUS_GRAM' => [
                'isim' => 'Gümüş Gram',
                'alis' => (($gumusOns * $usdtry) / $ons_gram) - ((($gumusOns * $usdtry) / $ons_gram) * $this->spread_oran / 2),
                'satis' => (($gumusOns * $usdtry) / $ons_gram) + ((($gumusOns * $usdtry) / $ons_gram) * $this->spread_oran / 2),
                'birim' => '₺',
                'change_percent' => $gramChangePercent
            ]
        ];
        
        return $altinlar;
    }

    public function tumPiyasaVerileri() {
        // Önce ana sembolleri çek
        $usdtry = $this->getPiyasaVerisi('TRY=X');
        $onsAltin = $this->getPiyasaVerisi('GC=F');
        $gumusOns = $this->getPiyasaVerisi('SI=F');
        
        // Altın hesaplamaları
        $altinVerileri = [];
        if(!isset($usdtry['error']) && !isset($onsAltin['error'])) {
            $gumusPrice = isset($gumusOns['error']) ? 0 : $gumusOns['price'];
            $altinVerileri = $this->altinHesapla(
                $onsAltin['price'], 
                $onsAltin['previous_close'],
                $usdtry['price'], 
                $usdtry['previous_close'],
                $gumusPrice
            );
        }
        
        // Borsa-Döviz sembolleri
        $borsaDoviz = [
            'USD' => $this->getPiyasaVerisi('TRY=X'),
            'EURO' => $this->getPiyasaVerisi('EURTRY=X'),
            'GBP' => $this->getPiyasaVerisi('GBPTRY=X'),
            'BTC' => $this->getPiyasaVerisi('BTC-USD'),
            'BIST100' => $this->getPiyasaVerisi('XU100.IS'),
            'BRENT' => $this->getPiyasaVerisi('BZ=F')
        ];
        
        // API rate limit için küçük bekleme
        usleep(100000);
        
        return [
            'altin' => $altinVerileri,
            'borsa_doviz' => $borsaDoviz
        ];
    }
}

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Piyasa verilerini çek
$piyasa_verileri = [];
try {
    $piyasa = new PiyasaVerileriSystem();
    $piyasa_verileri = $piyasa->tumPiyasaVerileri();
} catch (Exception $e) {
    // Hata durumunda boş veri
    $piyasa_verileri = [
        'altin' => [],
        'borsa_doviz' => []
    ];
}

// Veritabanı bağlantısı ve haber çekme
function getHaberleri() {
    $database = new Database();
    $db = $database->getConnection();
    
    $haberler = [];
    
    try {
        // Düz üyelerin son haberlerini çek (kullanıcılara özel)
        $query = "SELECT h.id, h.baslik, h.tarih, h.Kategori, h.view_count 
                  FROM haberler h
                  WHERE h.yazar = 'kullanici' OR h.yazar IS NULL 
                  ORDER BY h.tarih DESC LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $haberler['kullanici_haberleri'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ekonomi haberlerini çek (haberler.php'den)
        $query = "SELECT h.id, h.baslik, h.tarih, h.Kategori, h.view_count 
                  FROM haberler h
                  WHERE h.kategori_id = 5 
                  ORDER BY h.tarih DESC LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $haberler['ekonomi_haberleri'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        $haberler = [
            'kullanici_haberleri' => [],
            'ekonomi_haberleri' => []
        ];
    }
    
    return $haberler;
}

// Haberleri al
$sidebar_haberler = getHaberleri();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Piyasalar | ONVIBES - Canlı Döviz, Altın, Borsa Verileri</title>
    <meta name="description" content="Canlı döviz kurları, altın fiyatları, BIST 100 endeksi ve kripto para piyasaları. Anlık piyasa verileri ONVIBES'te.">
    
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
            --gold: #ffd700;
            --white: #ffffff;
            --card-bg: #ffffff;
            --text-color: #333333;
        }

        /* Arama Modal Stilleri */
        .search-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
        }

        .search-modal-content {
            position: relative;
            background: var(--light);
            margin: 10% auto;
            padding: 0;
            width: 90%;
            max-width: 600px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        .search-header {
            padding: 25px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            border-radius: 15px 15px 0 0;
        }

        .search-input-container {
            position: relative;
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.1);
            border-radius: 25px;
            padding: 5px;
        }

        .search-input {
            flex: 1;
            border: none;
            background: transparent;
            color: white;
            padding: 12px 20px;
            font-size: 16px;
            outline: none;
        }

        .search-input::placeholder {
            color: rgba(255,255,255,0.7);
        }

        .search-close {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .search-close:hover {
            background: rgba(255,255,255,0.2);
        }

        .search-results {
            max-height: 400px;
            overflow-y: auto;
        }

        .search-item {
            padding: 15px 25px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.3s;
        }

        .search-item:hover {
            background: var(--surface);
        }

        .search-item:last-child {
            border-bottom: none;
        }

        .search-item-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .search-item-content {
            font-size: 14px;
            color: var(--gray);
        }

        .search-item-category {
            display: inline-block;
            background: var(--red);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin-top: 5px;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: var(--gray);
        }

        .loading-results {
            text-align: center;
            padding: 40px;
            color: var(--gray);
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
            --gold: #ffd700;
            --white: #ffffff;
            --card-bg: #2d2d2d;
            --text-color: #e0e0e0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

        /* Ana Layout */
        .main-content {
            display: grid;
            grid-template-columns: 280px 1fr 280px;
            gap: 25px;
            margin: 25px 0;
            align-items: start;
        }

        .sidebar {
            position: sticky;
            top: 140px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .main-content-area {
            /* Ana içerik alanı */
        }

        /* Sidebar Bölümleri */
        .sidebar-section {
            background: var(--light);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .sidebar-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--red);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Haber Listeleri */
        .haber-list {
            list-style: none;
        }

        .haber-item {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .haber-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .haber-link {
            color: var(--text);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.4;
            display: block;
            transition: all 0.3s;
        }

        .haber-link:hover {
            color: var(--red);
            padding-left: 5px;
        }

        .haber-tarih {
            color: var(--gray);
            font-size: 0.75rem;
            margin-top: 5px;
        }

        /* Reklam Alanı */
        .reklam-alan {
            text-align: center;
            padding: 20px;
            background: var(--surface);
            border-radius: 8px;
            border: 2px dashed var(--border);
        }

        .reklam-alan img {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
        }

        .reklam-text {
            color: var(--gray);
            font-size: 0.8rem;
            margin-top: 5px;
        }

        /* Header Styles */
        .main-menu {
            background: var(--light);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .top-bar {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 6px 0;
        }

        .top-bar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
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

        .header-link:hover, .header-link.active {
            background: rgba(255,255,255,0.15);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .header-link i {
            margin-right: 6px;
            font-size: 12px;
        }
        
        /* Canlı Saat Stili */
        .live-clock {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--white);
            font-size: 14px;
            font-weight: 600;
            padding: 8px 12px;
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            animation: pulseGlow 2s infinite;
        }
        
        /* Arama Butonu Stili */
        .search-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease-out 0.3s both;
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* Tema Toggle Stili */
        .theme-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: rgba(255,255,255,0.1);
            color: var(--white);
            border: none;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease-out 0.4s both;
        }
        
        .theme-toggle:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        .user-link {
            animation: fadeInUp 0.6s ease-out 0.5s both;
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

        .top-links button, .top-links a {
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

        .top-links button::before, .top-links a::before {
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

        .top-links button:hover::before, .top-links a:hover::before {
            width: 100%;
            height: 100%;
        }

        .top-links button:hover, .top-links a:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }

        /* Navigation */
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

        /* Piyasalar İçerik */
        .piyasalar-header {
            text-align: center;
            margin: 40px 0 30px;
        }

        .piyasalar-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 10px;
        }

        /* Kategori Başlıkları */
        .kategori-baslik {
            background: linear-gradient(135deg, var(--dark) 0%, #34495e 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin: 30px 0 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .kategori-baslik.altin {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }

        .kategori-baslik h2 {
            font-size: 1.8rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Piyasa Grid */
        .piyasalar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin: 20px 0 40px;
        }

        .piyasa-kart {
            background: var(--light);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .piyasa-kart:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.15);
            border-color: var(--red);
        }

        .piyasa-baslik {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }

        .piyasa-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }

        .piyasa-icon.doviz { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .piyasa-icon.borsa { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .piyasa-icon.kripto { background: linear-gradient(135deg, #f39c12, #d35400); }

        .piyasa-bilgi h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        /* Alış-Satış Grid */
        .alis-satis-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .fiyat-item {
            text-align: center;
            padding: 15px 12px;
            border-radius: 10px;
            background: var(--surface);
            transition: all 0.3s;
        }

        .fiyat-item:hover {
            background: rgba(210, 35, 42, 0.05);
        }

        .fiyat-item.alis {
            background: rgba(46, 204, 113, 0.08);
        }

        .fiyat-item.satis {
            background: rgba(231, 76, 60, 0.08);
        }

        .fiyat-baslik {
            font-size: 0.75rem;
            color: var(--gray);
            margin-bottom: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .fiyat-deger {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .fiyat-birim {
            font-size: 0.8rem;
            color: var(--gray);
        }

        .piyasa-durum {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .durum-badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .durum-aktif { background: rgba(46, 204, 113, 0.1); color: var(--green); }
        .durum-kapali { background: rgba(149, 165, 166, 0.1); color: var(--gray); }

        .guncelleme-zamani {
            font-size: 0.75rem;
            color: var(--gray);
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
            display: block;
            padding: 4px 0;
        }

        .footer-links a:hover {
            color: white;
            padding-left: 8px;
        }

        .footer-links a i {
            margin-right: 8px;
            width: 16px;
        }

        /* Sidebar Sticky */
        .sidebar {
            position: sticky;
            top: 20px;
            height: fit-content;
        }

        .left-sidebar {
            position: sticky;
            top: 20px;
            height: fit-content;
        }

        /* Piyasa Verileri Liste Yapısı */
        .piyasa-kategorileri {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .kategori-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .kategori-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .kategori-btn.active {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4);
        }

        .piyasa-listesi {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .piyasa-liste-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            transition: all 0.3s ease;
            background: var(--card-bg);
        }

        .piyasa-liste-item:hover {
            background: rgba(255,255,255,0.05);
            transform: translateX(5px);
        }

        .piyasa-liste-item:last-child {
            border-bottom: none;
        }

        /* Piyasa Tablosu Stilleri */
        .piyasa-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            margin-top: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .piyasa-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .piyasa-table td {
            padding: 15px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-size: 14px;
        }

        .piyasa-table-row {
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease-out;
            animation-delay: calc(var(--row-index) * 0.1s);
        }

        .piyasa-table-row:hover {
            background: rgba(255,255,255,0.05);
            transform: scale(1.01);
        }

        .piyasa-table-row:last-child td {
            border-bottom: none;
        }

        .piyasa-adi-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        .fiyat-cell {
            font-weight: 600;
            text-align: right;
        }

        .degisim-cell {
            font-weight: 700;
            text-align: center;
        }

        .degisim-cell.pozitif {
            color: #4CAF50;
        }

        .degisim-cell.negatif {
            color: #f44336;
        }

        .durum-cell {
            text-align: center;
        }

        .durum-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .durum-aktif {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }

        .piyasa-adi {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .piyasa-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
        }

        .piyasa-icon.altin {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
        }

        .piyasa-icon.doviz {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .piyasa-icon.borsa {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .piyasa-icon.kripto {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .piyasa-isim {
            font-weight: 600;
            font-size: 16px;
            color: var(--text-color);
        }

        .fiyat-bilgi {
            text-align: center;
        }

        .fiyat-label {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 5px;
            font-weight: 500;
        }

        .fiyat-deger {
            font-weight: 700;
            font-size: 14px;
            color: var(--text-color);
        }

        .alis-fiyat {
            color: #10b981;
        }

        .satis-fiyat {
            color: #ef4444;
        }

        .degisim-bilgi {
            text-align: center;
        }

        .degisim-yuzde {
            font-weight: 600;
            font-size: 16px;
            padding: 6px 12px;
            border-radius: 20px;
            display: inline-block;
        }

        .degisim-yuzde.pozitif {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .degisim-yuzde.negatif {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .durum-bilgi {
            text-align: center;
            font-size: 12px;
            color: var(--gray);
        }

        .durum-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .durum-aktif {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .durum-kapali {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .kategori-baslik {
            display: flex;
        }

        .piyasalar-grid {
            display: grid;
        }

        .kategori-section {
            margin-bottom: 40px;
        }

        .kategori-section.kategori-hidden {
            display: none !important;
        }

        .kategori-hidden {
            display: none !important;
        }

        @media (max-width: 768px) {
            .piyasa-liste-item {
                grid-template-columns: 1fr;
                gap: 10px;
                text-align: center;
            }

            .piyasa-adi {
                justify-content: center;
            }

            .fiyat-bilgi, .degisim-bilgi, .durum-bilgi {
                padding: 5px 0;
            }

            .piyasa-kategorileri {
                justify-content: center;
            }
            
            .header-links {
                display: none;
            }
            
            .mobile-search-container {
                display: block;
            }
            
            .nav-info {
                display: none;
            }
        }

        /* Premium Animasyonlar - Anasayfa'dan Alınmış */
        .piyasa-liste-item {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .kategori-btn {
            position: relative;
            overflow: hidden;
        }

        .kategori-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .kategori-btn:hover::before {
            left: 100%;
        }

        .piyasa-liste-item:hover {
            animation: pulseGlow 2s ease-in-out infinite;
        }

        @keyframes pulseGlow {
            0%, 100% {
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            50% {
                box-shadow: 0 8px 30px rgba(0,0,0,0.2), 0 0 20px rgba(210, 35, 42, 0.1);
            }
        }

        .fiyat-deger {
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .fiyat-deger:hover {
            transform: scale(1.05);
        }

        .degisim-yuzde {
            position: relative;
            overflow: hidden;
        }

        .degisim-yuzde::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .degisim-yuzde:hover::before {
            left: 100%;
        }

        .sidebar {
            position: sticky;
            top: 20px;
            height: fit-content;
            animation: slideInRight 0.8s ease-out;
        }

        .left-sidebar {
            position: sticky;
            top: 20px;
            height: fit-content;
            animation: slideInLeft 0.8s ease-out;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .piyasa-kategorileri {
            animation: fadeInDown 0.6s ease-out;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.6);
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .sidebar {
                position: static;
                order: 1;
            }

            .main-content-area {
                order: 2;
            }

            .piyasalar-grid {
                grid-template-columns: 1fr;
            }
            
            .piyasalar-header h1 {
                font-size: 2rem;
            }
            
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
        }
        
        /* Nav Info - Premium */
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


    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <!-- Header -->
    <header id="header">
        <div class="main-menu">
            <div class="top-bar">
                <div class="container">
                    <h1 class="logo">
                        <a href="index.php" title="ONVIBES">
                            <span class="logo-text">BORSA|Onvibes</span>
                        </a>
                    </h1>

                    <div class="header-links">
                        <a href="ilan.php" class="header-link">
                            <i class="fas fa-ad"></i>
                            <span>İlan</span>
                        </a>
                        <a href="yazarlar.php" class="header-link">
                            <i class="fas fa-pen"></i>
                            <span>Yazarlar</span>
                        </a>
                        <a href="piyasalar.php" class="header-link active">
                            <i class="fas fa-chart-line"></i>
                            <span>Piyasalar</span>
                        </a>
                        <a href="superlig.php" class="header-link">
                            <i class="fas fa-futbol"></i>
                            <span>Süper Lig</span>
                        </a>
                    </div>

                    <div class="right-nav">
                        <!-- Arama Butonu -->
                        <button id="search-btn" class="search-btn" onclick="openSearchModal()">
                            <i class="fas fa-search"></i>
                            <span>Arama</span>
                        </button>
                        
                        <div class="top-links">
                            <button type="submit" name="toggle_theme" class="theme-toggle">
                                <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                                <span><?php echo $dark_mode ? 'Açık' : 'Koyu'; ?></span>
                            </button>
                            <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                                <a href="profil.php" class="user-link">
                                    <i class="fas fa-user"></i>
                                    <span>Profil</span>
                                </a>
                            <?php else: ?>
                                <a href="login.php" class="user-link">
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

            <nav id="mainnav">
                <div class="container">
                    <ul class="nav-links">
                        <li><a href="index.php">Ana Sayfa</a></li>
                        <li><a href="index.php?category=1">Gündem</a></li>
                        <li><a href="index.php?category=2">Spor</a></li>
                        <li><a href="index.php?category=3">Magazin</a></li>
                        <li><a href="index.php?category=4">Teknoloji</a></li>
                        <li><a href="index.php?category=5">Ekonomi</a></li>
                        <li><a href="index.php?category=6">Sağlık</a></li>
                        <li><a href="index.php?category=7">Dünya</a></li>
                    </ul>
                    
                    <!-- Nav Info -->
                    <div class="nav-info">
                        <div class="current-time">
                            <i class="fas fa-clock"></i>
                            <span id="current-time">--:--:--</span>
                        </div>
                        <div class="weather-info">
                            <i class="fas <?php echo $hava_icon; ?> weather-icon"></i>
                            <span class="temperature"><?php echo $hava_durumu['sicaklik']; ?>°C</span>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Ana İçerik -->
    <div class="container">
        <div class="main-content">
            <!-- Sol Sidebar -->
            <aside class="sidebar">
                <!-- Sol Sidebar - Üst: Ekonomi Köşe Yazıları -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">
                        <i class="fas fa-pen-nib"></i>
                        Ekonomi Köşe Yazıları
                    </h3>
                    <ul class="haber-list">
                        <?php
                        try {
                            $database = new Database();
                            $db = $database->getConnection();
                            
                            $kose_query = "SELECT ky.id, ky.title, ky.content, ky.created_at, 
                                          ky.author_name, ky.author_avatar, ky.summary
                                          FROM kose_yazisi ky 
                                          WHERE ky.status='approved' AND ky.category_id=5
                                          ORDER BY ky.created_at DESC LIMIT 5";
                            $kose_stmt = $db->prepare($kose_query);
                            $kose_stmt->execute();
                            
                            if($kose_stmt->rowCount() > 0) {
                                while($kose_row = $kose_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $excerpt = strlen($kose_row['content']) > 80 ? substr($kose_row['content'], 0, 80) . '...' : $kose_row['content'];
                                    $date = date('d.m.Y', strtotime($kose_row['created_at']));
                        ?>
                        <li class="haber-item">
                            <a href="kose-yazisi.php?id=<?php echo $kose_row['id']; ?>" class="haber-link">
                                <strong><?php echo htmlspecialchars($kose_row['title']); ?></strong>
                                <p style="font-size: 12px; color: var(--gray); margin: 5px 0;"><?php echo htmlspecialchars($excerpt); ?></p>
                            </a>
                            <div class="haber-tarih">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($kose_row['author_name']); ?> | <?php echo $date; ?>
                            </div>
                        </li>
                        <?php 
                                }
                            } else {
                                echo '<li class="haber-item"><p style="color: var(--gray); text-align: center;">Köşe yazısı bulunamadı</p></li>';
                            }
                        } catch (PDOException $e) {
                            echo '<li class="haber-item"><p style="color: var(--gray); text-align: center;">Köşe yazıları yüklenemedi</p></li>';
                        }
                        ?>
                    </ul>
                </div>

                <!-- Sol Sidebar - Alt: Ekonomi Haberleri -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">
                        <i class="fas fa-chart-line"></i>
                        Ekonomi Haberleri
                    </h3>
                    <ul class="haber-list">
                        <?php if(isset($sidebar_haberler['ekonomi_haberleri']) && count($sidebar_haberler['ekonomi_haberleri']) > 0): ?>
                            <?php foreach($sidebar_haberler['ekonomi_haberleri'] as $haber): ?>
                            <li class="haber-item">
                                <a href="haber-detay.php?id=<?php echo $haber['id']; ?>" class="haber-link">
                                    <strong><?php echo htmlspecialchars($haber['baslik']); ?></strong>
                                </a>
                                <div class="haber-tarih">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($haber['Kategori'] ?? 'Ekonomi'); ?> | 
                                    <i class="fas fa-eye"></i> <?php echo $haber['view_count'] ?? 0; ?> okunma | 
                                    <?php echo date('d.m.Y H:i', strtotime($haber['tarih'])); ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="haber-item">
                                <p style="color: var(--gray); text-align: center;">Ekonomi haberi bulunamadı</p>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </aside>

            <!-- Ana İçerik - Piyasa Verileri -->
            <main class="main-content-area">
                <div class="piyasalar-header">
                </div>

                <!-- Kategori Butonları -->
                <div class="piyasa-kategorileri">
                    <button class="kategori-btn active" data-kategori="tumunu">#TÜMÜ</button>
                    <button class="kategori-btn" data-kategori="altin">#ALTIN</button>
                    <button class="kategori-btn" data-kategori="borsa-doviz">#BORSA-DÖVİZ</button>
                </div>

                <!-- ALTIN KATEGORİSİ -->
                <div class="kategori-section altin-section" data-kategori="altin">
                    <div class="kategori-baslik altin">
                        <h2><i class="fas fa-coins"></i> ALTIN FİYATLARI</h2>
                    </div>

                    <div class="piyasa-listesi">
                        <?php 
                        if(isset($piyasa_verileri['altin']) && count($piyasa_verileri['altin']) > 0):
                            foreach($piyasa_verileri['altin'] as $key => $altin): 
                                // Gerçek değişim oranını kullan
                                $degisim = isset($altin['change_percent']) ? $altin['change_percent'] : 0;
                                $degisim_class = $degisim >= 0 ? 'pozitif' : 'negatif';
                                $degisim_text = $degisim >= 0 ? '+' . number_format($degisim, 2) . '%' : number_format($degisim, 2) . '%';
                        ?>
                        <div class="piyasa-liste-item" data-kategori="altin">
                            <div class="piyasa-adi">
                                <div class="piyasa-icon altin">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <div class="piyasa-isim"><?php echo $altin['isim']; ?></div>
                            </div>
                            
                            <div class="fiyat-bilgi">
                                <div class="fiyat-label">ALIŞ</div>
                                <div class="fiyat-deger alis-fiyat"><?php echo number_format($altin['alis'], 2, ',', '.'); ?> <?php echo $altin['birim']; ?></div>
                            </div>
                            
                            <div class="fiyat-bilgi">
                                <div class="fiyat-label">SATIŞ</div>
                                <div class="fiyat-deger satis-fiyat"><?php echo number_format($altin['satis'], 2, ',', '.'); ?> <?php echo $altin['birim']; ?></div>
                            </div>
                            
                            <div class="degisim-bilgi">
                                <div class="fiyat-label">DEĞİŞİM</div>
                                <div class="degisim-yuzde <?php echo $degisim_class; ?>" style="font-size: 12px; padding: 4px 8px;"><?php echo $degisim_text; ?></div>
                            </div>
                            
                            <div class="durum-bilgi">
                                <div class="fiyat-label">DURUM</div>
                                <span class="durum-badge durum-aktif">Canlı</span>
                            </div>
                        </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                        <div class="piyasa-liste-item">
                            <p style="text-align: center; color: var(--gray); grid-column: 1 / -1;">Altın verileri yüklenemedi</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- BORSA - DÖVİZ KATEGORİSİ -->
                <div class="kategori-section borsa-doviz-section" data-kategori="borsa-doviz">
                    <div class="kategori-baslik">
                        <h2><i class="fas fa-chart-line"></i> BORSA - DÖVİZ</h2>
                    </div>

                    <div class="piyasa-listesi">
                        <?php 
                        if(isset($piyasa_verileri['borsa_doviz']) && count($piyasa_verileri['borsa_doviz']) > 0):
                            $isimler = [
                                'USD' => ['isim' => 'Dolar / TL', 'icon' => 'fa-dollar-sign', 'class' => 'doviz'],
                                'EURO' => ['isim' => 'Euro / TL', 'icon' => 'fa-euro-sign', 'class' => 'doviz'],
                                'GBP' => ['isim' => 'Sterlin / TL', 'icon' => 'fa-pound-sign', 'class' => 'doviz'],
                                'BTC' => ['isim' => 'Bitcoin', 'icon' => 'fa-bitcoin', 'class' => 'kripto'],
                                'BIST100' => ['isim' => 'BIST 100', 'icon' => 'fa-chart-bar', 'class' => 'borsa'],
                                'BRENT' => ['isim' => 'Brent Petrol', 'icon' => 'fa-oil-can', 'class' => 'doviz']
                            ];
                            
                            foreach($piyasa_verileri['borsa_doviz'] as $key => $veri):
                                if(isset($veri['error'])) continue;
                                $info = $isimler[$key] ?? ['isim' => $key, 'icon' => 'fa-chart-line', 'class' => 'doviz'];
                                
                                // Gerçek değişim oranını kullan
                                $degisim = isset($veri['change_percent']) ? $veri['change_percent'] : 0;
                                $degisim_class = $degisim >= 0 ? 'pozitif' : 'negatif';
                                $degisim_text = $degisim >= 0 ? '+' . number_format($degisim, 2) . '%' : number_format($degisim, 2) . '%';
                                
                                // BIST100 için özel format (sadece puan)
                                if($key === 'BIST100') {
                                    $alis_fiyat = number_format($veri['price'], 0, ',', '.');
                                    $satis_fiyat = '';
                                } else {
                                    $alis_fiyat = number_format($veri['alis'], 2, ',', '.');
                                    $satis_fiyat = number_format($veri['satis'], 2, ',', '.');
                                }
                        ?>
                        <div class="piyasa-liste-item" data-kategori="borsa-doviz">
                            <div class="piyasa-adi">
                                <div class="piyasa-icon <?php echo $info['class']; ?>">
                                    <i class="fas <?php echo $info['icon']; ?>"></i>
                                </div>
                                <div class="piyasa-isim"><?php echo $info['isim']; ?></div>
                            </div>
                            
                            <div class="fiyat-bilgi">
                                <div class="fiyat-label"><?php echo $key === 'BIST100' ? 'PUAN' : 'ALIŞ'; ?></div>
                                <div class="fiyat-deger alis-fiyat" style="font-size: 14px;"><?php echo $alis_fiyat; ?> <?php echo $key === 'BIST100' ? '' : $veri['currency']; ?></div>
                            </div>
                            
                            <div class="fiyat-bilgi" style="<?php echo $key === 'BIST100' ? 'display: none;' : ''; ?>">
                                <div class="fiyat-label">SATIŞ</div>
                                <div class="fiyat-deger satis-fiyat" style="font-size: 14px;"><?php echo $satis_fiyat; ?> <?php echo $veri['currency']; ?></div>
                            </div>
                            
                            <div class="degisim-bilgi">
                                <div class="fiyat-label">DEĞİŞİM</div>
                                <div class="degisim-yuzde <?php echo $degisim_class; ?>" style="font-size: 12px; padding: 4px 8px;"><?php echo $degisim_text; ?></div>
                            </div>
                            
                            <div class="durum-bilgi">
                                <div class="fiyat-label">DURUM</div>
                                <span class="durum-badge <?php echo $veri['status'] == 'success' ? 'durum-aktif' : 'durum-kapali'; ?>">
                                    <?php echo $veri['status_text']; ?>
                                </span>
                            </div>
                        </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                        <div class="piyasa-liste-item">
                            <p style="text-align: center; color: var(--gray); grid-column: 1 / -1;">Borsa-Döviz verileri yüklenemedi</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>

            <!-- Sağ Sidebar -->
            <aside class="sidebar">
                <!-- Sağ Sidebar - Üst: Kullanıcı Haberleri -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">
                        <i class="fas fa-user"></i>
                        Kullanıcı Haberleri
                    </h3>
                    <ul class="haber-list">
                        <?php
                        try {
                            $database = new Database();
                            $db = $database->getConnection();
                            
                            $user_news_query = "SELECT h.id, h.baslik, h.tarih, h.Kategori, h.view_count 
                                              FROM haberler h
                                              WHERE (h.yazar = 'kullanici' OR h.yazar IS NULL) 
                                              ORDER BY h.tarih DESC LIMIT 5";
                            $user_news_stmt = $db->prepare($user_news_query);
                            $user_news_stmt->execute();
                            
                            if($user_news_stmt->rowCount() > 0) {
                                while($user_news_row = $user_news_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $author = 'Kullanıcı';
                                    $date = date('d.m.Y H:i', strtotime($user_news_row['tarih']));
                        ?>
                        <li class="haber-item">
                            <a href="haber-detay.php?id=<?php echo $user_news_row['id']; ?>" class="haber-link">
                                <strong><?php echo htmlspecialchars($user_news_row['baslik']); ?></strong>
                            </a>
                            <div class="haber-tarih">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($author); ?> | 
                                <i class="fas fa-eye"></i> <?php echo $user_news_row['view_count'] ?? 0; ?> okunma | 
                                <?php echo $date; ?>
                            </div>
                        </li>
                        <?php 
                                }
                            } else {
                                echo '<li class="haber-item"><p style="color: var(--gray); text-align: center;">Kullanıcı haberi bulunamadı</p></li>';
                            }
                        } catch (PDOException $e) {
                            echo '<li class="haber-item"><p style="color: var(--gray); text-align: center;">Kullanıcı haberleri yüklenemedi</p></li>';
                        }
                        ?>
                    </ul>
                </div>

                <!-- Sağ Sidebar - Alt: Site Haberleri -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">
                        <i class="fas fa-newspaper"></i>
                        Site Haberleri
                    </h3>
                    <ul class="haber-list">
                        <?php
                        try {
                            $database = new Database();
                            $db = $database->getConnection();
                            
                            $site_news_query = "SELECT h.id, h.baslik, h.tarih, h.Kategori, h.view_count 
                                               FROM haberler h
                                               WHERE h.tarih >= DATE_SUB(NOW(), INTERVAL 3 DAY) 
                                               ORDER BY h.view_count DESC, h.tarih DESC LIMIT 5";
                            $site_news_stmt = $db->prepare($site_news_query);
                            $site_news_stmt->execute();
                            
                            if($site_news_stmt->rowCount() > 0) {
                                while($site_news_row = $site_news_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $date = date('d.m.Y H:i', strtotime($site_news_row['tarih']));
                                    $author = 'Site Yöneticisi';
                        ?>
                        <li class="haber-item">
                            <a href="haber-detay.php?id=<?php echo $site_news_row['id']; ?>" class="haber-link">
                                <?php echo htmlspecialchars($site_news_row['baslik']); ?>
                                <p style="font-size: 12px; color: var(--gray); margin: 5px 0;"><?php echo htmlspecialchars($site_news_row['Kategori'] ?? 'Haber detayı bulunmamaktadır.'); ?></p>
                            </a>
                            <div class="haber-tarih">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($site_news_row['Kategori'] ?? 'Kategori'); ?> | 
                                <i class="fas fa-eye"></i> <?php echo $site_news_row['view_count'] ?? 0; ?> okunma | 
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($author); ?> | <?php echo $date; ?>
                            </div>
                        </li>
                        <?php 
                                }
                            } else {
                                echo '<li class="haber-item"><p style="color: var(--gray); text-align: center;">Site haberi bulunamadı</p></li>';
                            }
                        } catch (PDOException $e) {
                            echo '<li class="haber-item"><p style="color: var(--gray); text-align: center;">Site haberleri yüklenemedi</p></li>';
                        }
                        ?>
                    </ul>
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
        // Piyasalar sayfası JavaScript kodları
        document.addEventListener('DOMContentLoaded', function() {
            // Canlı Saat Güncelleme
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('tr-TR', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    second: '2-digit'
                });
                const timeElement = document.getElementById('current-time');
                if (timeElement) {
                    timeElement.textContent = timeString;
                }
            }
            
            // Nav bar saat güncelleme
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
            
            // Saati hemen güncelle ve her saniye güncelle
            updateTime();
            setInterval(updateTime, 1000);
            setInterval(() => {
                updateTime();
                updateNavTime();
            }, 1000);
            
            // Arama Butonu Fonksiyonu
            const searchBtn = document.getElementById('search-btn');
            if (searchBtn) {
                searchBtn.addEventListener('click', function() {
                    openSearchModal();
                });
            }
            
            // Sayfa yüklendiğinde mevcut saati göster
            function updateCurrentTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('tr-TR', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                
                // Tüm güncelleme zamanı elementlerini güncelle
                const timeElements = document.querySelectorAll('.guncelleme-zamani');
                timeElements.forEach(element => {
                    element.textContent = timeString;
                });
            }
            
            // İlk güncelleme
            updateCurrentTime();
            
            // Scroll to top butonu ekle
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
                
                scrollBtn.addEventListener('click', function() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
                
                document.body.appendChild(scrollBtn);

                window.addEventListener('scroll', function() {
                    if (window.pageYOffset > 300) {
                        scrollBtn.style.opacity = '1';
                        scrollBtn.style.transform = 'translateY(0)';
                    } else {
                        scrollBtn.style.opacity = '0';
                        scrollBtn.style.transform = 'translateY(100px)';
                    }
                });
            }
            
            // Kategori filtreleme sistemi
            const kategoriBtnler = document.querySelectorAll('.kategori-btn');
            const kategoriSectionlar = document.querySelectorAll('.kategori-section');
            
            console.log('DOM yüklendi! Buton sayısı:', kategoriBtnler.length);
            console.log('Section sayısı:', kategoriSectionlar.length);
            
            // Butonlara index ekle (animasyon için)
            kategoriBtnler.forEach((btn, index) => {
                btn.style.setProperty('--index', index);
            });
            
            // Tümü butonu tıklandığında
            function tumunuGoster() {
                console.log('Tümünü göster fonksiyonu çağrıldı');
                kategoriSectionlar.forEach(section => {
                    section.classList.remove('kategori-hidden');
                    console.log('Section gösterildi:', section.getAttribute('data-kategori'));
                });
            }
            
            // Belirli kategori göster
            function kategoriGoster(kategori) {
                console.log('Kategori göster fonksiyonu çağrıldı:', kategori);
                kategoriSectionlar.forEach(section => {
                    const sectionKategori = section.getAttribute('data-kategori');
                    console.log('Kontrol edilen section:', sectionKategori);
                    
                    if (sectionKategori === kategori) {
                        section.classList.remove('kategori-hidden');
                    } else {
                        section.classList.add('kategori-hidden');
                    }
                });
            }
            
            // Buton tıklama eventi
            kategoriBtnler.forEach(btn => {
                btn.addEventListener('click', function() {
                    console.log('Buton tıklandı:', this.getAttribute('data-kategori'));
                    
                    // Aktif butonu güncelle
                    kategoriBtnler.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Kategoriye göre filtrele
                    const kategori = this.getAttribute('data-kategori');
                    console.log('Filtrelenecek kategori:', kategori);
                    
                    if (kategori === 'tumunu') {
                        tumunuGoster();
                    } else if (kategori === 'altin') {
                        kategoriGoster('altin');
                    } else if (kategori === 'borsa-doviz') {
                        kategoriGoster('borsa-doviz');
                    }
                });
            });
            
            // Sayfa yüklendiğinde tümünü göster
            tumunuGoster();
        });
    </script>
    
    <!-- Arama Modal -->
    <div id="searchModal" class="search-modal">
        <div class="search-modal-content">
            <div class="search-header">
                <div class="search-input-container">
                    <i class="fas fa-search" style="color: white; margin-left: 15px; z-index: 1;"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Site içinde arama yapın...">
                    <button class="search-close" onclick="closeSearchModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div id="searchResults" class="search-results">
                <div class="no-results">
                    <i class="fas fa-search" style="font-size: 48px; margin-bottom: 15px; color: var(--gray);"></i>
                    <p>Aramak istediğiniz kelimeyi yazın</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Arama Modal JavaScript -->
    <script>
        let searchTimeout;
        
        function openSearchModal() {
            document.getElementById('searchModal').style.display = 'block';
            setTimeout(() => {
                document.getElementById('searchInput').focus();
            }, 100);
        }
        
        function closeSearchModal() {
            document.getElementById('searchModal').style.display = 'none';
            document.getElementById('searchResults').innerHTML = `
                <div class="no-results">
                    <i class="fas fa-search" style="font-size: 48px; margin-bottom: 15px; color: var(--gray);"></i>
                    <p>Aramak istediğiniz kelimeyi yazın</p>
                </div>
            `;
        }
        
        // Modal dışına tıklandığında kapat
        document.getElementById('searchModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSearchModal();
            }
        });
        
        // ESC tuşu ile kapat
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSearchModal();
            }
        });
        
        // Arama fonksiyonu
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                document.getElementById('searchResults').innerHTML = `
                    <div class="no-results">
                        <i class="fas fa-search" style="font-size: 48px; margin-bottom: 15px; color: var(--gray);"></i>
                        <p>Aramak istediğiniz kelimeyi yazın</p>
                    </div>
                `;
                return;
            }
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });
        
        function performSearch(query) {
            const resultsContainer = document.getElementById('searchResults');
            resultsContainer.innerHTML = `
                <div class="loading-results">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: var(--red);"></i>
                    <p>Aranıyor...</p>
                </div>
            `;
            
            // Simüle edilmiş arama sonuçları (gerçek projede AJAX ile API'ye istek atılır)
            setTimeout(() => {
                const mockResults = [
                    {
                        title: "Ekonomi Haberleri",
                        content: "Son ekonomi haberleri ve analizler",
                        category: "Ekonomi",
                        url: "index.php?category=5"
                    },
                    {
                        title: "Döviz Kurları",
                        content: "Canlı USD, EUR, GBP kurları",
                        category: "Piyasalar",
                        url: "piyasalar.php"
                    },
                    {
                        title: "Altın Fiyatları",
                        content: "Güncel altın fiyatları ve analiz",
                        category: "Piyasalar",
                        url: "piyasalar.php"
                    },
                    {
                        title: "Borsa Haberleri",
                        content: "BIST 100 ve hisse senedi piyasası",
                        category: "Ekonomi",
                        url: "index.php?category=5"
                    },
                    {
                        title: "Kripto Para",
                        content: "Bitcoin ve kripto para haberleri",
                        category: "Teknoloji",
                        url: "index.php?category=4"
                    }
                ];
                
                const filteredResults = mockResults.filter(result => 
                    result.title.toLowerCase().includes(query.toLowerCase()) ||
                    result.content.toLowerCase().includes(query.toLowerCase())
                );
                
                if (filteredResults.length === 0) {
                    resultsContainer.innerHTML = `
                        <div class="no-results">
                            <i class="fas fa-search" style="font-size: 48px; margin-bottom: 15px; color: var(--gray);"></i>
                            <p>"${query}" için sonuç bulunamadı</p>
                        </div>
                    `;
                } else {
                    const resultsHTML = filteredResults.map(result => `
                        <div class="search-item" onclick="goToResult('${result.url}')">
                            <div class="search-item-title">${result.title}</div>
                            <div class="search-item-content">${result.content}</div>
                            <span class="search-item-category">${result.category}</span>
                        </div>
                    `).join('');
                    
                    resultsContainer.innerHTML = resultsHTML;
                }
            }, 500);
        }
        
        function goToResult(url) {
            closeSearchModal();
            window.location.href = url;
        }
        
        // Ana nav bar için saat güncelleme (modal içinde de kullanılır)
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
        }
    </script>
    
</body>
</html>
