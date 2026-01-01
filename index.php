<?php 
session_start();
require_once 'config.php'; 

// --- 1. GİRİŞ / KAYIT MANTIĞI ---
$hata = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['islem'])) {
    if ($_POST['islem'] == 'giris') {
        $email = $_POST['email'];
        $pass = $_POST['password'];
        $sorgu = $db->prepare("SELECT * FROM users WHERE email = ? AND password = ?");
        $sorgu->execute([$email, $pass]);
        $user_data = $sorgu->fetch();
        if ($user_data) {
            $_SESSION['user_id'] = $user_data['id'];
            header("Location: index.php");
            exit();
        } else { $hata = "Hatalı e-posta veya şifre!"; }
    }
    
    if ($_POST['islem'] == 'kayit') {
        $name = $_POST['username'];
        $email = $_POST['email'];
        $pass = $_POST['password'];
        $gender = $_POST['gender'];
        $ekle = $db->prepare("INSERT INTO users (username, email, password, gender) VALUES (?, ?, ?, ?)");
        if($ekle->execute([$name, $email, $pass, $gender])) {
            $hata = "Kayıt başarılı, şimdi giriş yapın.";
        }
    }
}

// --- 2. VERİ HAZIRLIĞI ---
$oturum_acik = isset($_SESSION['user_id']);
$user_id = $oturum_acik ? $_SESSION['user_id'] : null;

if ($oturum_acik) {
    $user_query = $db->prepare("SELECT u.*, ud.favorite_genre, ud.watched_count, ud.about_me FROM users u LEFT JOIN user_details ud ON u.id = ud.user_id WHERE u.id = ?");
    $user_query->execute([$user_id]);
    $user = $user_query->fetch(PDO::FETCH_ASSOC);

    $friends = $db->prepare("SELECT u.username, u.profile_pic FROM friendships f JOIN users u ON (f.friend_id = u.id OR f.user_id = u.id) WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted' AND u.id != ?");
    $friends->execute([$user_id, $user_id, $user_id]);
    $friend_list = $friends->fetchAll(PDO::FETCH_ASSOC);

    $match_user = null;
    if ($user['current_match_id']) {
        $match_q = $db->prepare("SELECT username, gender FROM users WHERE id = ?");
        $match_q->execute([$user['current_match_id']]);
        $match_user = $match_q->fetch(PDO::FETCH_ASSOC);
    }
}

$sayfa = isset($_GET['sayfa']) ? $_GET['sayfa'] : 'anasayfa';

// --- 3. ARAMA VE ÇOKLU KATEGORİ MANTIĞI ---
$search_query = isset($_GET['q']) ? $_GET['q'] : '';
$selected_cats = isset($_GET['cats']) ? $_GET['cats'] : []; 

$movies_sql = "SELECT DISTINCT m.* FROM movies m";
$params = [];

if (!empty($selected_cats)) {
    // Pivot tablo (movie_category_map) üzerinden JOIN yapıyoruz
    $movies_sql .= " JOIN movie_category_map mcm ON m.id = mcm.movie_id WHERE mcm.category_id IN (" . implode(',', array_fill(0, count($selected_cats), '?')) . ")";
    $params = array_merge($params, $selected_cats);
}

if (!empty($search_query)) {
    $movies_sql .= (empty($selected_cats) ? " WHERE" : " AND") . " (m.title LIKE ? OR m.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$filtered_movies = $db->prepare($movies_sql);
$filtered_movies->execute($params);
$search_results = $filtered_movies->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FilmDünyası | <?php echo $oturum_acik ? ucfirst($sayfa) : 'Giriş Yap'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { display: flex; flex-direction: column; min-height: 100vh; background-color: #f8f9fa; }
        .main-content { flex: 1 0 auto; }
        footer { flex-shrink: 0; }
        .auth-card { max-width: 450px; margin: 80px auto; border-radius: 20px; }
        .banner { height: 250px; background: #1a1a1a; color: white; display: flex; align-items: center; justify-content: center; text-align: center; border-bottom: 3px solid #ffc107; }
        .profile-btn { position: fixed; bottom: 30px; right: 30px; z-index: 1000; width: 60px; height: 60px; border-radius: 50%; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .movie-card { cursor: pointer; transition: transform 0.3s; border: none; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .movie-card:hover { transform: translateY(-10px); }
        .section-title { border-left: 5px solid #ffc107; padding-left: 15px; margin-bottom: 25px; font-weight: bold; }
        .cat-badge-input { display: none; }
        .cat-label { cursor: pointer; padding: 8px 20px; border-radius: 25px; border: 1px solid #ddd; transition: 0.3s; font-size: 0.85rem; font-weight: 500; }
        .cat-badge-input:checked + .cat-label { background-color: #ffc107; border-color: #ffc107; color: #000; box-shadow: 0 4px 8px rgba(255,193,7,0.3); }
    </style>
</head>
<body>

<?php if (!$oturum_acik): ?>
    <div class="container main-content">
        <div class="auth-card card shadow-lg border-0 overflow-hidden">
            <div class="card-header bg-dark text-white text-center py-4">
                <h3 class="fw-bold mb-0">Film<span class="text-warning">Dünyası</span></h3>
            </div>
            <div class="card-body p-5">
                <?php if($hata) echo "<div class='alert alert-danger py-2 small text-center'>$hata</div>"; ?>
                <ul class="nav nav-pills nav-justified mb-4">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pills-login">Giriş</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-register">Kayıt</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pills-login">
                        <form method="POST"><input type="hidden" name="islem" value="giris"><div class="mb-3"><input type="email" name="email" class="form-control" placeholder="E-posta" required></div><div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Şifre" required></div><button class="btn btn-warning w-100 fw-bold" type="submit">Giriş Yap</button></form>
                    </div>
                    <div class="tab-pane fade" id="pills-register">
                        <form method="POST"><input type="hidden" name="islem" value="kayit"><div class="mb-3"><input type="text" name="username" class="form-control" placeholder="Kullanıcı Adı" required></div><div class="mb-3"><input type="email" name="email" class="form-control" placeholder="E-posta" required></div><div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Şifre" required></div><div class="mb-3"><select name="gender" class="form-select" required><option value="" disabled selected>Cinsiyet Seçin</option><option value="erkek">Erkek</option><option value="kadin">Kadın</option></select></div><button class="btn btn-success w-100 fw-bold" type="submit">Kayıt Ol</button></form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow">
        <div class="container">
            <button class="btn btn-warning me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#socialSidebar"><i class="bi bi-people-fill"></i></button>
            <a class="navbar-brand fw-bold" href="index.php">Film<span class="text-warning">Dünyası</span></a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link <?php echo ($sayfa == 'anasayfa') ? 'active' : ''; ?>" href="index.php">Ana Sayfa</a></li>
                </ul>
                <div class="d-flex align-items-center text-white-50 small">
                    <span class="me-3">Hoş geldin, <?php echo $user['username']; ?></span>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-power"></i></a>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-content">
    <?php switch($sayfa) {
        case 'profil': ?>
            <div class="container py-5">
                <div class="row">
                    <div class="col-lg-4 text-center">
                        <div class="card shadow-sm border-0 p-4 mb-4">
                            <img src="resim/<?php echo $user['profile_pic'] ?? 'default.jpg'; ?>" class="rounded-circle mx-auto img-thumbnail" width="140">
                            <h4 class="mt-3"><?php echo $user['username']; ?></h4>
                            <p class="badge bg-primary"><?php echo ucfirst($user['gender']); ?></p>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="card shadow-sm border-0 p-4">
                            <h5>Hesap Bilgileri</h5><hr>
                            <p><strong>E-posta:</strong> <?php echo $user['email']; ?></p>
                            <p><strong>Hakkımda:</strong> <?php echo $user['about_me'] ?? 'Biyografi eklenmemiş.'; ?></p>
                            <a href="logout.php" class="btn btn-danger btn-sm">Çıkış Yap</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php break;
        default: ?>
            <header class="banner mb-4">
                <div class="container text-center">
                    <h1 class="display-5 fw-bold">Film <span class="text-warning">&</span> Dizi Keşfet</h1>
                </div>
            </header>

            <div class="container mb-5">
                <div class="card border-0 shadow-sm p-4 mb-5 bg-white" style="border-radius: 15px; margin-top: -50px; position: relative; z-index: 10;">
                    <form action="index.php" method="GET" id="filterForm">
                        <div class="input-group input-group-lg mb-4">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="İçerik ara..." value="<?= htmlspecialchars($search_query) ?>">
                            <button class="btn btn-warning px-5 fw-bold" type="submit">ARA</button>
                        </div>
                        
                        <div class="d-flex align-items-center mb-2">
                            <h6 class="mb-0 me-3 fw-bold text-muted small text-uppercase"><i class="bi bi-filter"></i> Kategoriler:</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php 
                                $all_cats = $db->query("SELECT * FROM categories")->fetchAll();
                                foreach($all_cats as $cat): 
                                    $checked = in_array($cat['id'], $selected_cats) ? 'checked' : '';
                                ?>
                                    <input type="checkbox" name="cats[]" value="<?= $cat['id'] ?>" id="cat_<?= $cat['id'] ?>" class="cat-badge-input" onchange="this.form.submit()" <?= $checked ?>>
                                    <label for="cat_<?= $cat['id'] ?>" class="cat-label bg-light"><?= $cat['category_name'] ?></label>
                                <?php endforeach; ?>
                                <?php if(!empty($selected_cats) || !empty($search_query)): ?>
                                    <a href="index.php" class="btn btn-link btn-sm text-danger text-decoration-none">Temizle</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <main>
                    <?php if (!empty($search_query) || !empty($selected_cats)): ?>
                        <h3 class="section-title">Arama Sonuçları (<?= count($search_results) ?>)</h3>
                        <div class="row">
                            <?php foreach($search_results as $film): ?>
                                <div class="col-md-3 mb-4">
                                    <div class="card h-100 movie-card overflow-hidden text-center" data-bs-toggle="modal" data-bs-target="#movieModal" data-title="<?= $film['title'] ?>" data-desc="<?= $film['description'] ?>" data-rating="<?= $film['rating'] ?>" data-img="resim/<?= $film['image_url'] ?>">
                                        <img src="resim/<?= $film['image_url'] ?>" class="card-img-top" height="320" style="object-fit: cover;">
                                        <div class="card-body">
                                            <h6 class="fw-bold text-truncate"><?= $film['title'] ?></h6>
                                            <small class="text-warning"><i class="bi bi-star-fill"></i> <?= $film['rating'] ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if(empty($search_results)): ?>
                                <div class="col-12 text-center py-5 text-muted"><p>Sonuç bulunamadı.</p></div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php 
                        $sections = [
                            "En Çok Beğenilenler" => "SELECT * FROM movies WHERE rating >= 8.5 LIMIT 4",
                            "Yeni Çıkanlar" => "SELECT * FROM movies ORDER BY id DESC LIMIT 4",
                            "Popüler Diziler" => "SELECT * FROM movies WHERE type='dizi' LIMIT 4",
                        ];
                        foreach($sections as $title => $sql): ?>
                            <h3 class="section-title"><?php echo $title; ?></h3>
                            <div class="row mb-5">
                                <?php $res = $db->query($sql)->fetchAll(); foreach($res as $film): ?>
                                <div class="col-md-3 mb-4">
                                    <div class="card h-100 movie-card overflow-hidden text-center" data-bs-toggle="modal" data-bs-target="#movieModal" data-title="<?= $film['title'] ?>" data-desc="<?= $film['description'] ?>" data-rating="<?= $film['rating'] ?>" data-img="resim/<?= $film['image_url'] ?>">
                                        <img src="resim/<?= $film['image_url'] ?>" class="card-img-top" height="320" style="object-fit: cover;">
                                        <div class="card-body">
                                            <h6 class="fw-bold text-truncate"><?= $film['title'] ?></h6>
                                            <small class="text-warning"><i class="bi bi-star-fill"></i> <?= $film['rating'] ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </main>
            </div>
        <?php break;
    } ?>
    </div>

    <div class="modal fade" id="movieModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <div class="col-md-5"><img src="" id="modalImg" class="img-fluid w-100 h-100" style="object-fit: cover;"></div>
                        <div class="col-md-7 p-4">
                            <button type="button" class="btn-close float-end" data-bs-dismiss="modal"></button>
                            <h2 class="fw-bold mb-1" id="modalTitle"></h2>
                            <div class="text-warning mb-3"><i class="bi bi-star-fill"></i> <span id="modalRating"></span></div>
                            <p class="text-muted" id="modalDesc"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-start shadow-lg" tabindex="-1" id="socialSidebar">
        <div class="offcanvas-header bg-dark text-white">
            <h5 class="offcanvas-title">Sosyal & Eşleşme</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="card border-warning mb-4 shadow-sm">
                <div class="card-body text-center p-3">
                    <h6 class="fw-bold text-danger mb-3"><i class="bi bi-heart-fill"></i> HAFTALIK EŞLEŞME</h6>
                    <?php if($match_user): ?>
                        <div class="p-3 bg-light border rounded">
                            <small class="text-muted d-block">Partnerin:</small>
                            <strong class="text-primary fs-5"><?php echo $match_user['username']; ?></strong>
                            <?php 
                            $match_time = strtotime($user['match_date']);
                            $seven_days_later = $match_time + (7 * 24 * 60 * 60);
                            if (time() > $seven_days_later): ?>
                                <p class="text-success small fw-bold mt-2">Süreniz doldu!</p>
                                <div class="btn-group w-100 mt-2"><button class="btn btn-sm btn-dark">Devam</button><button class="btn btn-sm btn-danger">Yeni</button></div>
                            <?php else: ?>
                                <span class="badge bg-info d-block mt-2">Kalan Süre: <?php echo ceil(($seven_days_later - time()) / 86400); ?> Gün</span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form action="match_engine.php" method="POST"><button type="submit" name="eslesme_yap" class="btn btn-primary w-100 fw-bold">BENİ EŞLEŞTİR</button></form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-person-plus-fill"></i> Arkadaş Ekle</h6>
                <form action="match_engine.php" method="POST">
                    <div class="input-group input-group-sm">
                        <input type="text" name="friend_username" class="form-control" placeholder="Kullanıcı adı..." required>
                        <button class="btn btn-dark" type="submit" name="arkadas_ekle">Ekle</button>
                    </div>
                </form>
                <?php if(isset($_GET['hata'])): ?>
                    <small class="text-danger mt-1 d-block"><?= htmlspecialchars($_GET['hata']) ?></small>
                <?php endif; ?>
                <?php if(isset($_GET['mesaj'])): ?>
                    <small class="text-success mt-1 d-block"><?= htmlspecialchars($_GET['mesaj']) ?></small>
                <?php endif; ?>
            </div>

            <h6><i class="bi bi-people-fill"></i> Arkadaşlarım (<?php echo count($friend_list); ?>)</h6>
            <div class="list-group list-group-flush mt-2">
                <?php foreach($friend_list as $f): ?>
                <div class="list-group-item d-flex align-items-center px-0 bg-transparent border-0">
                    <img src="resim/<?php echo $f['profile_pic'] ?? 'default.jpg'; ?>" class="rounded-circle me-2" width="30">
                    <span class="small fw-bold"><?php echo $f['username']; ?></span>
                    <i class="bi bi-dot text-success ms-auto"></i>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <a href="index.php?sayfa=profil" class="btn btn-warning profile-btn d-flex align-items-center justify-content-center border border-white border-3 shadow-lg">
        <i class="bi bi-person-fill fs-3 text-dark"></i>
    </a>
<?php endif; ?>

    <footer class="bg-dark text-white-50 text-center py-4 mt-auto">
        <div class="container small"><p class="mb-0">&copy; 2025 FilmDünyası Platformu. Tüm Hakları Saklıdır.</p></div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const movieModal = document.getElementById('movieModal');
        movieModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            document.getElementById('modalTitle').innerText = button.getAttribute('data-title');
            document.getElementById('modalDesc').innerText = button.getAttribute('data-desc');
            document.getElementById('modalRating').innerText = button.getAttribute('data-rating');
            document.getElementById('modalImg').src = button.getAttribute('data-img');
        });
    </script>
</body>
</html>