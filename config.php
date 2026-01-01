<?php
// Hata ayarları
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "sql103.infinityfree.com";
$kullanici = "if0_40347894";
$sifre = "uWpOPhGs6LFn";
$veritabani = "if0_40347894_bitirme1";

try {
    // PDO bağlantısı oluşturuyoruz (Değişken adını $db yaptık)
    $db = new PDO("mysql:host=$host;dbname=$veritabani;charset=utf8", $kullanici, $sifre);
    
    // Hata modunu aktifleştiriyoruz
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
?>