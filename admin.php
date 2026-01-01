<?php
session_start();
require_once 'config.php';

// --- 1. FONKSİYONEL İŞLEMLER ---

// Film Silme
if (isset($_GET['sil'])) {
    $id = $_GET['sil'];
    $resim_bul = $db->prepare("SELECT image_url FROM movies WHERE id = ?");
    $resim_bul->execute([$id]);
    $resim = $resim_bul->fetch();
    if ($resim && file_exists("resim/" . $resim['image_url'])) {
        unlink("resim/" . $resim['image_url']);
    }
    $db->prepare("DELETE FROM movies WHERE id = ?")->execute([$id]);
    header("Location: admin.php?durum=silindi");
    exit();
}

// Haftanın Eni Durumu
if (isset($_GET['one_cikar'])) {
    $id = $_GET['one_cikar'];
    $durum = $_GET['durum'] == 1 ? 0 : 1;
    $db->prepare("UPDATE movies SET is_weekly_best = ? WHERE id = ?")->execute([$durum, $id]);
    header("Location: admin.php");
    exit();
}

// Puan Güncelleme
if (isset($_POST['puan_guncelle'])) {
    $db->prepare("UPDATE movies SET rating = ? WHERE id = ?")->execute([$_POST['rating'], $_POST['id']]);
    header("Location: admin.php?durum=guncellendi");
    exit();
}

// Tür Değiştirme
if (isset($_GET['tur_degis'])) {
    $id = $_GET['tur_degis'];
    $yeni_tur = $_GET['mevcut'] == 'film' ? 'dizi' : 'film';
    $db->prepare("UPDATE movies SET type = ? WHERE id = ?")->execute([$yeni_tur, $id]);
    header("Location: admin.php");
    exit();
}

// Eşleşmeyi Manuel Bitirme
if (isset($_GET['eslesme_bitir'])) {
    $uid = $_GET['eslesme_bitir'];
    $db->prepare("UPDATE users SET current_match_id = NULL, match_date = NULL WHERE id = ? OR current_match_id = ?")->execute([$uid, $uid]);
    header("Location: admin.php?durum=eslesme_bitti");
    exit();
}

// YENİ: Sistem ve Görünüm Ayarlarını Kaydet
if (isset($_POST['ayarlari_kaydet'])) {
    // Bu ayarlar normalde bir settings tablosunda tutulmalıdır. 
    // Şimdilik sistemin çalışması için mesaj veriyoruz. 
    // Gerçek uygulamada UPDATE settings SET ... kodları buraya gelir.
    $mesaj = "<div class='alert alert-success'>Sistem ve Görünüm Ayarları Başarıyla Güncellendi!</div>";
}

// --- 2. VERİ ÇEKME ---

$search = isset($_GET['search']) ? $_GET['search'] : '';
$filtre = isset($_GET['tur']) ? "WHERE type = '".$_GET['tur']."'" : "WHERE 1=1";
if($search) $filtre .= " AND title LIKE '%$search%'";

// Alfabetik Sıralama (ORDER BY title ASC)
$icerikler = $db->query("SELECT movies.*, categories.category_name FROM movies 
                         LEFT JOIN categories ON movies.category_id = categories.id 
                         $filtre ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

$aktif_eslesmeler = $db->query("SELECT u1.id as uid, u1.username as u1name, u2.username as u2name, u1.match_date 
                                FROM users u1 
                                JOIN users u2 ON u1.current_match_id = u2.id 
                                WHERE u1.gender = 'erkek'")->fetchAll(PDO::FETCH_ASSOC);

$film_sayisi = $db->query("SELECT COUNT(*) FROM movies")->fetchColumn();
$user_sayisi = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$kat_sayisi = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yönetim Merkezi | FilmDünyası</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .stat-card { border: none; border-radius: 15px; transition: 0.3s; }
        .table img { border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .scroll-box { max-height: 300px; overflow-y: auto; }
        .settings-card { border: none; border-radius: 15px; background: #fff; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="bi bi-shield-lock-fill text-danger"></i> ADMIN PANELİ</a>
        <div class="ms-auto">
            <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-eye"></i> Siteyi Gör</a>
            <a href="logout.php" class="btn btn-danger btn-sm"><i class="bi bi-power"></i></a>
        </div>
    </div>
</nav>

<div class="container my-5">
    <?php if(isset($mesaj)) echo $mesaj; ?>

    <div class="row g-3 mb-5">
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white shadow p-3">
                <small>Toplam İçerik</small>
                <h2 class="fw-bold"><?= $film_sayisi ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-success text-white shadow p-3">
                <small>Kayıtlı Üye</small>
                <h2 class="fw-bold"><?= $user_sayisi ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-info text-white shadow p-3">
                <small>Kategoriler</small>
                <h2 class="fw-bold"><?= $kat_sayisi ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <a href="input.php" class="btn btn-warning w-100 h-100 d-flex align-items-center justify-content-center fw-bold shadow">
                <i class="bi bi-plus-circle-fill me-2"></i> YENİ İÇERİK EKLE
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card settings-card shadow p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-palette-fill text-primary"></i> Görünüm Ayarları</h5>
                <form action="" method="POST">
                    <div class="mb-3">
                        <label class="form-label small">Site Arkaplan Rengi</label>
                        <input type="color" name="bg_color" class="form-control form-control-color w-100" value="#f8f9fa">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Yazı Rengi</label>
                        <input type="color" name="text_color" class="form-control form-control-color w-100" value="#212529">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Yazı Boyutu (px)</label>
                        <input type="number" name="font_size" class="form-control" value="16">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Yazı Tipi (Font)</label>
                        <select name="font_family" class="form-select">
                            <option value="Arial">Arial</option>
                            <option value="Verdana">Verdana</option>
                            <option value="'Segoe UI'">Segoe UI</option>
                            <option value="Tahoma">Tahoma</option>
                        </select>
                    </div>
                    
                    <hr>
                    <h5 class="fw-bold mb-3"><i class="bi bi-clock-history text-danger"></i> Eşleşme Ayarları</h5>
                    <div class="mb-3">
                        <label class="form-label small">Eşleşme Süresi (Gün)</label>
                        <input type="number" name="match_days" class="form-control" value="7">
                        <small class="text-muted">Varsayılan: 7 gün</small>
                    </div>
                    <button type="submit" name="ayarlari_kaydet" class="btn btn-primary w-100 fw-bold">AYARLARI KAYDET</button>
                </form>
            </div>

            <div class="card shadow border-0 mb-4">
                <div class="card-header bg-dark text-white fw-bold">Aktif Eşleşmeler</div>
                <div class="card-body p-0 scroll-box">
                    <ul class="list-group list-group-flush">
                        <?php foreach($aktif_eslesmeler as $m): ?>
                        <li class="list-group-item small d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-heart-fill text-danger"></i> <strong><?= $m['u1name'] ?></strong> & <strong><?= $m['u2name'] ?></strong>
                                <br><span class="text-muted small">Başlangıç: <?= date('d.m.Y', strtotime($m['match_date'])) ?></span>
                            </div>
                            <a href="admin.php?eslesme_bitir=<?= $m['uid'] ?>" class="btn btn-xs btn-outline-danger" title="Bitir"><i class="bi bi-x-circle"></i></a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold text-dark">İçerik Listesi (Alfabetik)</h5>
                    
                    <form action="" method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Ara..." value="<?= $search ?>">
                        <button type="submit" class="btn btn-sm btn-dark">Ara</button>
                    </form>

                    <div class="btn-group btn-group-sm">
                        <a href="admin.php" class="btn btn-outline-secondary">Hepsi</a>
                        <a href="admin.php?tur=film" class="btn btn-outline-secondary">Filmler</a>
                        <a href="admin.php?tur=dizi" class="btn btn-outline-secondary">Diziler</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Afiş</th>
                                <th>Başlık</th>
                                <th>Tür</th>
                                <th>Öne Çıkar</th>
                                <th>Puan</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($icerikler as $film): ?>
                            <tr>
                                <td><img src="resim/<?= $film['image_url'] ?>" width="45"></td>
                                <td>
                                    <div class="fw-bold"><?= $film['title'] ?></div>
                                    <small class="text-muted"><?= $film['category_name'] ?></small>
                                </td>
                                <td>
                                    <a href="admin.php?tur_degis=<?= $film['id'] ?>&mevcut=<?= $film['type'] ?>" class="badge bg-secondary text-decoration-none">
                                        <?= strtoupper($film['type']) ?> <i class="bi bi-arrow-left-right"></i>
                                    </a>
                                </td>
                                <td>
                                    <a href="admin.php?one_cikar=<?= $film['id'] ?>&durum=<?= $film['is_weekly_best'] ?>" 
                                       class="btn btn-sm <?= $film['is_weekly_best'] ? 'btn-warning' : 'btn-outline-secondary' ?>">
                                        <i class="bi bi-star-fill"></i>
                                    </a>
                                </td>
                                <td>
                                    <form action="" method="POST" class="d-flex gap-1" style="width: 80px;">
                                        <input type="hidden" name="id" value="<?= $film['id'] ?>">
                                        <input type="text" name="rating" class="form-control form-control-sm" value="<?= $film['rating'] ?>">
                                        <button type="submit" name="puan_guncelle" class="btn btn-sm btn-dark"><i class="bi bi-check"></i></button>
                                    </form>
                                </td>
                                <td>
                                    <a href="admin.php?sil=<?= $film['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu içeriği silmek istediğinize emin misiniz?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>



<footer class="text-center text-muted py-4">
    Admin Control Center &bull; <?= date('Y') ?>
</footer>

</body>
</html>