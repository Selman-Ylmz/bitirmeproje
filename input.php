<?php
session_start();
require_once 'config.php';

// Güvenlik Notu: Buraya ileride admin oturum kontrolü ekleyebilirsin.
$mesaj = "";

// --- 1. FİLM / DİZİ EKLEME MANTIĞI ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['film_ekle'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $category_id = $_POST['category_id'];
    $type = $_POST['type'];
    $rating = $_POST['rating'];
    $is_weekly_best = isset($_POST['is_weekly_best']) ? 1 : 0;

    // Resim yükleme işlemi
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $resim_adi = time() . "_" . $_FILES['image']['name']; // Çakışmayı önlemek için zaman damgası ekledik
        $hedef = "resim/" . basename($resim_adi);

        if (move_uploaded_file($_FILES['image']['tmp_name'], $hedef)) {
            $sql = "INSERT INTO movies (title, description, category_id, type, image_url, rating, is_weekly_best) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            if ($stmt->execute([$title, $description, $category_id, $type, $resim_adi, $rating, $is_weekly_best])) {
                $mesaj = "<div class='alert alert-success shadow-sm'><i class='bi bi-check-circle-fill'></i> İçerik başarıyla eklendi!</div>";
            } else {
                $mesaj = "<div class='alert alert-danger shadow-sm'>Veritabanına kayıt yapılırken bir hata oluştu.</div>";
            }
        } else {
            $mesaj = "<div class='alert alert-danger shadow-sm'>Resim klasöre yüklenemedi. 'resim' klasörünü kontrol edin.</div>";
        }
    } else {
        $mesaj = "<div class='alert alert-warning shadow-sm'>Lütfen geçerli bir afiş resmi seçin.</div>";
    }
}

// --- 2. KATEGORİ EKLEME MANTIĞI ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kategori_ekle'])) {
    $cat_name = $_POST['cat_name'];
    if (!empty($cat_name)) {
        $ekle = $db->prepare("INSERT INTO categories (category_name) VALUES (?)");
        if ($ekle->execute([$cat_name])) {
            $mesaj = "<div class='alert alert-info shadow-sm'><i class='bi bi-info-circle-fill'></i> Yeni kategori eklendi.</div>";
        }
    }
}

// --- 3. İÇERİK SİLME MANTIĞI ---
if (isset($_GET['sil'])) {
    $id = $_GET['sil'];
    // Önce resim dosyasını klasörden silelim (İsteğe bağlı ama temizlik iyidir)
    $resim_sorgu = $db->prepare("SELECT image_url FROM movies WHERE id = ?");
    $resim_sorgu->execute([$id]);
    $resim = $resim_sorgu->fetch();
    if ($resim && file_exists("resim/" . $resim['image_url'])) {
        unlink("resim/" . $resim['image_url']);
    }

    $sil = $db->prepare("DELETE FROM movies WHERE id = ?");
    if ($sil->execute([$id])) {
        header("Location: input.php?mesaj=silindi");
        exit();
    }
}

// Bilgi mesajlarını URL'den yakala
if (isset($_GET['mesaj']) && $_GET['mesaj'] == 'silindi') {
    $mesaj = "<div class='alert alert-warning shadow-sm'>İçerik başarıyla silindi.</div>";
}

// Gerekli Verileri Çek
$categories = $db->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$movies = $db->query("SELECT m.*, c.category_name FROM movies m LEFT JOIN categories c ON m.category_id = c.id ORDER BY m.id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | FilmDünyası</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .admin-card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); transition: 0.3s; }
        .header-section { background: #212529; color: white; padding: 25px 0; margin-bottom: 30px; border-bottom: 4px solid #ffc107; }
        .btn-warning { font-weight: 600; }
        .table img { border-radius: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="header-section shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
        <h2 class="mb-0 fw-bold"><i class="bi bi-shield-lock-fill text-warning me-2"></i> İÇERİK YÖNETİMİ</h2>
        <a href="index.php" class="btn btn-outline-warning btn-sm"><i class="bi bi-house-door-fill me-1"></i> Siteye Dön</a>
    </div>
</div>

<div class="container">
    <?= $mesaj ?>
    
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card admin-card p-4 mb-4">
                <h5 class="fw-bold mb-4 border-bottom pb-2 text-dark"><i class="bi bi-plus-square-fill me-2 text-primary"></i>Yeni İçerik Ekle</h5>
                <form action="input.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="film_ekle" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Başlık</label>
                        <input type="text" name="title" class="form-control" placeholder="Film veya Dizi adı" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">İçerik Türü</label>
                            <select name="type" class="form-select">
                                <option value="film">🎞️ Film</option>
                                <option value="dizi">📺 Dizi</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">Kategori</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Seçiniz...</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= $cat['category_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Açıklama / Konu</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Kısa bir özet yazın..." required></textarea>
                    </div>

                    <div class="row align-items-end">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">IMDB Puanı</label>
                            <input type="number" step="0.1" name="rating" class="form-control" placeholder="Örn: 8.5">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">Afiş Yükle</label>
                            <input type="file" name="image" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-check mb-4 mt-2">
                        <input class="form-check-input" type="checkbox" name="is_weekly_best" id="is_weekly">
                        <label class="form-check-label small" for="is_weekly text-muted">Haftanın Enleri Listesinde Göster</label>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 shadow-sm py-2">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i> İÇERİĞİ SİSTEME EKLE
                    </button>
                </form>
            </div>

            <div class="card admin-card p-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-tags-fill me-2 text-info"></i>Kategori Yönetimi</h5>
                <form action="input.php" method="POST" class="d-flex gap-2">
                    <input type="hidden" name="kategori_ekle" value="1">
                    <input type="text" name="cat_name" class="form-control" placeholder="Yeni Kategori Adı" required>
                    <button type="submit" class="btn btn-dark">Ekle</button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card admin-card h-100">
                <div class="card-header bg-white p-3 fw-bold border-0 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-stars me-2 text-warning"></i>Kayıtlı İçerikler</span>
                    <span class="badge bg-dark rounded-pill"><?= count($movies) ?> Kayıt</span>
                </div>
                <div class="table-responsive p-3">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr class="small text-uppercase">
                                <th>Afiş</th>
                                <th>Başlık</th>
                                <th>Tür/Kategori</th>
                                <th>Puan</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($movies as $m): ?>
                            <tr>
                                <td>
                                    <img src="resim/<?= htmlspecialchars($m['image_url']) ?>" width="45" height="65" style="object-fit: cover;">
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($m['title']) ?></div>
                                    <small class="text-muted"><?= $m['is_weekly_best'] ? '<span class="text-warning"><i class="bi bi-award-fill"></i> Haftanın Eni</span>' : '' ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border small"><?= strtoupper($m['type']) ?></span><br>
                                    <small class="text-muted"><?= htmlspecialchars($m['category_name'] ?? 'Yok') ?></small>
                                </td>
                                <td><strong class="text-primary"><?= $m['rating'] ?></strong></td>
                                <td>
                                    <a href="input.php?sil=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bu içeriği silmek istediğinize emin misiniz?')">
                                        <i class="bi bi-trash3-fill"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($movies)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Henüz hiç içerik eklenmemiş.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="text-center text-muted py-5 small">
    &copy; 2025 FilmDünyası Admin Kontrol Paneli
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>