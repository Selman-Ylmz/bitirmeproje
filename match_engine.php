<?php
session_start();
require_once 'config.php';

/**
 * --- 1. FONKSİYON: HAFTALIK EŞLEŞME ALGORİTMASI ---
 * Bu fonksiyon, kullanıcıyı karşı cinsten boşta olan biriyle rastgele eşleştirir.
 */
function matchUser($user_id, $db) {
    // 1. Mevcut kullanıcı bilgilerini al
    $stmt = $db->prepare("SELECT gender, current_match_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch();

    // Zaten bir eşleşmesi varsa işlem yapma
    if ($me['current_match_id'] != null) {
        return "Zaten aktif bir eşleşmen var!";
    }

    // 2. Karşı cinsi belirle
    $target_gender = ($me['gender'] == 'erkek') ? 'kadin' : 'erkek';

    // 3. Karşı cinsten boşta olan rastgele birini bul
    $find = $db->prepare("SELECT id FROM users WHERE gender = ? AND current_match_id IS NULL AND id != ? ORDER BY RAND() LIMIT 1");
    $find->execute([$target_gender, $user_id]);
    $partner = $find->fetch();

    if ($partner) {
        $partner_id = $partner['id'];
        $now = date('Y-m-d H:i:s');

        // 4. İki tarafı da güncelle (Atomik işlem - Transaction)
        $db->beginTransaction();
        try {
            // Kendimi güncelle
            $up1 = $db->prepare("UPDATE users SET current_match_id = ?, match_date = ? WHERE id = ?");
            $up1->execute([$partner_id, $now, $user_id]);

            // Partneri güncelle
            $up2 = $db->prepare("UPDATE users SET current_match_id = ?, match_date = ? WHERE id = ?");
            $up2->execute([$user_id, $now, $partner_id]);

            $db->commit();
            return "Eşleşme Başarılı!";
        } catch (Exception $e) {
            $db->rollBack();
            return "Bir hata oluştu: " . $e->getMessage();
        }
    } else {
        return "Üzgünüz, şu an uygun eşleşme bulunamadı.";
    }
}

/**
 * --- 2. FORM TETİKLEYİCİLERİ (POST KONTROLLERİ) ---
 */

// A. EŞLEŞME BUTONUNA BASILDIĞINDA
if (isset($_POST['eslesme_yap'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php?hata=Giriş yapmalısın!");
        exit();
    }
    $sonuc = matchUser($_SESSION['user_id'], $db);
    header("Location: index.php?mesaj=" . urlencode($sonuc));
    exit();
}

// B. ARKADAŞ EKLEME BUTONUNA BASILDIĞINDA
if (isset($_POST['arkadas_ekle'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php?hata=Giriş yapmalısın!");
        exit();
    }

    $current_user = $_SESSION['user_id'];
    $target_username = trim($_POST['friend_username']);

    // Kullanıcı adının boş olup olmadığını kontrol et
    if (empty($target_username)) {
        header("Location: index.php?hata=Lütfen bir kullanıcı adı girin!");
        exit();
    }

    // 1. Kullanıcıyı veritabanında ara
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$target_username]);
    $target_user = $stmt->fetch();

    if ($target_user) {
        $target_id = $target_user['id'];

        // Kendi kendini ekleme kontrolü
        if ($target_id == $current_user) {
            header("Location: index.php?hata=Kendini ekleyemezsin!");
            exit();
        }

        // 2. Zaten arkadaş mı kontrol et
        $check = $db->prepare("SELECT id FROM friendships WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
        $check->execute([$current_user, $target_id, $target_id, $current_user]);
        
        if ($check->rowCount() == 0) {
            // 3. Arkadaş olarak ekle (direkt onaylı 'accepted' statüsünde)
            $insert = $db->prepare("INSERT INTO friendships (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
            $insert->execute([$current_user, $target_id]);
            header("Location: index.php?mesaj=Arkadaş eklendi!");
        } else {
            header("Location: index.php?hata=Zaten arkadaşsınız!");
        }
    } else {
        header("Location: index.php?hata=Kullanıcı bulunamadı!");
    }
    exit();
}

/**
 * --- 3. GÜVENLİK ---
 * Eğer bu dosyaya doğrudan URL'den veya yetkisiz erişilirse ana sayfaya yönlendir.
 */
header("Location: index.php");
exit();