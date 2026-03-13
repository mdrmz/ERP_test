<?php
session_start();
include("baglan.php");
include("helper_functions.php");

oturumKontrol();

// Sadece Patron bu sayfaya erişebilir
if (kullaniciRolu($baglanti) != 'Patron') {
    die(yetkisizErisim());
}

$mesaj = "";
$hata = "";

// Kullanıcı rolü güncelleme
if (isset($_POST['rol_ata'])) {
    $user_id = $_POST['user_id'];
    $rol_id = $_POST['rol_id'];

    $sql = "UPDATE users SET rol_id = $rol_id WHERE id = $user_id";
    if ($baglanti->query($sql)) {
        $mesaj = "✅ Kullanıcı rolü güncellendi!";
        logKaydet($baglanti, 'rol_guncelleme', 'users', $user_id, "Rol ID: $rol_id olarak güncellendi");
    } else {
        $hata = "❌ Hata: " . $baglanti->error;
    }
}

// Yeni kullanıcı ekleme
if (isset($_POST['kullanici_ekle'])) {
    $kadi = trim($_POST['kadi']);
    $sifre = md5($_POST['sifre']);
    $tam_ad = trim($_POST['tam_ad']);
    $email = trim($_POST['email']);
    $telefon = preg_replace('/[^0-9]/', '', $_POST['telefon']); // Sadece rakamlar
    $rol_id = intval($_POST['rol_id']);

    // Validasyonlar
    if (empty($kadi) || strlen($kadi) < 3) {
        $hata = "❌ Kullanıcı adı en az 3 karakter olmalıdır!";
    } elseif (empty($telefon)) {
        $hata = "❌ Telefon numarası zorunludur!";
    } elseif (!preg_match('/^[0-9]{10,11}$/', $telefon)) {
        $hata = "❌ Telefon numarası 10-11 haneli olmalıdır!";
    } elseif (empty($rol_id)) {
        $hata = "❌ Yetki rolü seçmelisiniz!";
    } else {
        $stmt = $baglanti->prepare("INSERT INTO users (kadi, sifre, tam_ad, email, telefon, rol_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $kadi, $sifre, $tam_ad, $email, $telefon, $rol_id);

        if ($stmt->execute()) {
            $mesaj = "✅ Yeni kullanıcı eklendi!";
            logKaydet($baglanti, 'kullanici_ekleme', 'users', $baglanti->insert_id, "Kullanıcı: $kadi eklendi");
        } else {
            $hata = "❌ Hata: " . $baglanti->error;
        }
        $stmt->close();
    }
}

// Kullanıcı aktivasyon toggle
if (isset($_POST['toggle_aktif'])) {
    $user_id = intval($_POST['user_id']);
    $aktif = $_POST['aktif'] == '1' ? 0 : 1;
    $aktif_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

    // Kendi kendini pasif yapamaz
    if ($user_id === $aktif_user_id && $aktif === 0) {
        $hata = "❌ Kendi hesabınızı pasif yapamazsınız!";
    }
    // Ana yönetici pasif yapılamaz
    elseif ($user_id === 1) {
        $hata = "❌ Ana yönetici hesabının durumu değiştirilemez!";
    } else {
        $stmt = $baglanti->prepare("UPDATE users SET aktif = ? WHERE id = ?");
        $stmt->bind_param("ii", $aktif, $user_id);
        if ($stmt->execute()) {
            $durum = $aktif ? 'aktif' : 'pasif';
            $mesaj = "✅ Kullanıcı $durum hale getirildi!";
            logKaydet($baglanti, 'kullanici_durum', 'users', $user_id, "Durum: $durum");
        }
        $stmt->close();
    }
}

// --- ŞİFRE DEĞİŞTİRME ---
if (isset($_POST['sifre_degistir'])) {
    $user_id = intval($_POST['user_id']);
    $yeni_sifre = $_POST['yeni_sifre'];
    $yeni_sifre_tekrar = $_POST['yeni_sifre_tekrar'];
    $aktif_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

    if (strlen($yeni_sifre) < 4) {
        $hata = "❌ Şifre en az 4 karakter olmalıdır!";
    } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
        $hata = "❌ Şifreler eşleşmiyor!";
    } else {
        // MD5 kullanılıyor (mevcut sistem uyumu için)
        $sifre_hash = md5($yeni_sifre);
        $stmt = $baglanti->prepare("UPDATE users SET sifre = ? WHERE id = ?");
        $stmt->bind_param("si", $sifre_hash, $user_id);
        if ($stmt->execute()) {
            $mesaj = "✅ Şifre başarıyla güncellendi!";
            logKaydet($baglanti, 'sifre_degisikligi', 'users', $user_id, "Şifre güncellendi");
        } else {
            $hata = "❌ Şifre güncellenirken hata oluştu!";
        }
        $stmt->close();
    }
}

// --- KULLANICI SİLME (Foreign Key Kontrolü ile) ---
function kullaniciBagliKayitKontrol($baglanti, $user_id)
{
    $bagli_tablolar = [
        'islem_loglari' => 'user_id',
        'is_emirleri' => 'onaylayan_user_id',
        'onay_bekleyenler' => 'olusturan_user_id',
        'satin_alma_talepleri' => 'talep_eden_user_id',
        'sevkiyatlar' => 'sevk_eden_user_id',
        'sevkiyat_randevulari' => 'onaylayan_user_id',
        'stok_hareketleri' => 'user_id',
        'system_logs' => 'user_id'
    ];

    $bagli_kayitlar = [];

    foreach ($bagli_tablolar as $tablo => $sutun) {
        $stmt = $baglanti->prepare("SELECT COUNT(*) as sayi FROM $tablo WHERE $sutun = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            if ($row['sayi'] > 0) {
                $bagli_kayitlar[$tablo] = $row['sayi'];
            }
            $stmt->close();
        }
    }

    return $bagli_kayitlar;
}

if (isset($_POST['kullanici_sil'])) {
    $user_id = intval($_POST['user_id']);
    $aktif_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

    // Kendi kendini silemez
    if ($user_id === $aktif_user_id) {
        $hata = "❌ Kendi hesabınızı silemezsiniz!";
    }
    // Ana yönetici silinemez
    elseif ($user_id === 1) {
        $hata = "❌ Ana yönetici hesabı silinemez!";
    } else {
        // Bağlı kayıt kontrolü
        $bagli_kayitlar = kullaniciBagliKayitKontrol($baglanti, $user_id);

        if (!empty($bagli_kayitlar)) {
            $detay = "";
            foreach ($bagli_kayitlar as $tablo => $sayi) {
                $detay .= "$tablo: $sayi kayıt, ";
            }
            $hata = "❌ Bu kullanıcı silinemez! İlişkili kayıtlar var: " . rtrim($detay, ", ") . ". Önce kullanıcıyı pasif yapın.";
        } else {
            $stmt = $baglanti->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $mesaj = "✅ Kullanıcı başarıyla silindi!";
                logKaydet($baglanti, 'kullanici_silme', 'users', $user_id, "Kullanıcı silindi");
            } else {
                $hata = "❌ Kullanıcı silinirken hata oluştu: " . $baglanti->error;
            }
            $stmt->close();
        }
    }
}

// --- KULLANICI DÜZENLEME ---
if (isset($_POST['kullanici_guncelle'])) {
    $user_id = intval($_POST['user_id']);
    $kadi = trim($_POST['kadi']);
    $tam_ad = $_POST['tam_ad'];
    $email = $_POST['email'];
    $telefon = preg_replace('/[^0-9]/', '', $_POST['telefon']); // Sadece rakamlar
    $rol_id = intval($_POST['rol_id']);

    if (empty($telefon)) {
        $hata = "❌ Telefon numarası zorunludur!";
    } elseif (!preg_match('/^[0-9]{10,11}$/', $telefon)) {
        $hata = "❌ Telefon numarası 10-11 haneli olmalıdır!";
    } else {
        $stmt = $baglanti->prepare("UPDATE users SET kadi = ?, tam_ad = ?, email = ?, telefon = ?, rol_id = ? WHERE id = ?");
        $stmt->bind_param("ssssii", $kadi, $tam_ad, $email, $telefon, $rol_id, $user_id);

        if ($stmt->execute()) {
            $mesaj = "✅ Kullanıcı bilgileri güncellendi!";
            logKaydet($baglanti, 'kullanici_guncelleme', 'users', $user_id, "Kullanıcı bilgileri güncellendi");
        } else {
            $hata = "❌ Güncelleme hatası: " . $baglanti->error;
        }
        $stmt->close();
    }
}

// --- KULLANICI BİREYSEL YETKİ GÜNCELLEME ---
if (isset($_POST['kullanici_bireysel_yetki_guncelle'])) {
    $hedef_user_id = intval($_POST['hedef_user_id']);

    $gecerli_moduller = [
        'Hammadde Yönetimi',
        'Planlama & Takvim',
        'Üretim Paneli',
        'Satış & Siparişler',
        'Pazarlama',
        'Müşteriler',
        'Satın Alma',
        'Sevkiyat & Lojistik',
        'Stok Takibi',
        'İzlenebilirlik',
        'Lab Analizleri',
        'Bakım & Arıza',
        'Silo Yönetimi',
        'Hammadde Kodlama',
        'Sistem Ayarları'
    ];

    $gelen_overrides = $_POST['bireysel_yetkiler'] ?? [];

    $baglanti->begin_transaction();
    try {
        foreach ($gecerli_moduller as $modul) {
            $modul_esc = $baglanti->real_escape_string($modul);

            if (isset($gelen_overrides[$modul]['aktif'])) {
                // Override aktif → kayıt ekle/güncelle
                $okuma = isset($gelen_overrides[$modul]['okuma']) ? 1 : 0;
                $yazma = isset($gelen_overrides[$modul]['yazma']) ? 1 : 0;
                $onaylama = isset($gelen_overrides[$modul]['onaylama']) ? 1 : 0;

                $stmt = $baglanti->prepare(
                    "INSERT INTO kullanici_modul_yetkileri (user_id, modul_adi, okuma, yazma, onaylama)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE okuma=VALUES(okuma), yazma=VALUES(yazma), onaylama=VALUES(onaylama)"
                );
                $stmt->bind_param('isiii', $hedef_user_id, $modul, $okuma, $yazma, $onaylama);
                $stmt->execute();
                $stmt->close();
            } else {
                // Override pasif → kaydı sil (rol yetkisi devreye girer)
                $stmt = $baglanti->prepare(
                    "DELETE FROM kullanici_modul_yetkileri WHERE user_id = ? AND modul_adi = ?"
                );
                $stmt->bind_param('is', $hedef_user_id, $modul);
                $stmt->execute();
                $stmt->close();
            }
        }
        $baglanti->commit();
        $mesaj = "✅ Kullanıcı bireysel yetkileri güncellendi!";
        logKaydet($baglanti, 'bireysel_yetki_guncelleme', 'kullanici_modul_yetkileri', $hedef_user_id, "User ID: $hedef_user_id için bireysel yetkiler güncellendi");
    } catch (Exception $e) {
        $baglanti->rollback();
        $hata = "❌ Güncelleme sırasında hata oluştu: " . $e->getMessage();
    }
}

// Modül Yetkilerini Toplu Güncelleme
if (isset($_POST['yetki_toplu_guncelle'])) {
    $rol_id = $_POST['rol_id'];
    $gelen_yetkiler = $_POST['yetkiler'] ?? [];

    // Güvenlik için tüm modülleri tanımlıyoruz (navbar.php sırasıyla uyumlu)
    $gecerli_moduller = [
        'Hammadde Yönetimi',      // Üretim grubu
        'Planlama & Takvim',      // Üretim grubu  
        'Üretim Paneli',          // Üretim grubu
        'Satış & Siparişler',     // Satış grubu
        'Pazarlama',              // Satış grubu
        'Müşteriler',             // Satış grubu
        'Satın Alma',             // Lojistik grubu
        'Sevkiyat & Lojistik',    // Lojistik grubu
        'Stok Takibi',            // Lojistik grubu
        'İzlenebilirlik',         // Kalite grubu
        'Lab Analizleri',         // Kalite grubu
        'Bakım & Arıza',          // Bakım grubu
        'Silo Yönetimi',          // Tanımlar grubu
        'Hammadde Kodlama',       // Tanımlar grubu
        'Sistem Ayarları'         // Yönetici grubu
    ];

    $baglanti->begin_transaction();
    try {
        foreach ($gecerli_moduller as $modul) {
            $okuma = isset($gelen_yetkiler[$modul]['okuma']) ? 1 : 0;
            $yazma = isset($gelen_yetkiler[$modul]['yazma']) ? 1 : 0;
            $onaylama = isset($gelen_yetkiler[$modul]['onaylama']) ? 1 : 0;

            $kontrol = $baglanti->query("SELECT id FROM modul_yetkileri WHERE rol_id = $rol_id AND modul_adi = '$modul'");
            if ($kontrol->num_rows > 0) {
                $sql = "UPDATE modul_yetkileri SET okuma=$okuma, yazma=$yazma, onaylama=$onaylama WHERE rol_id = $rol_id AND modul_adi = '$modul'";
            } else {
                $sql = "INSERT INTO modul_yetkileri (rol_id, modul_adi, okuma, yazma, onaylama) VALUES ($rol_id, '$modul', $okuma, $yazma, $onaylama)";
            }
            $baglanti->query($sql);
        }
        $baglanti->commit();
        $mesaj = "✅ Rol yetkileri başarıyla güncellendi!";
        logKaydet($baglanti, 'yetki_toplu_guncelleme', 'modul_yetkileri', $rol_id, "Rol ID: $rol_id için yetkiler toplu güncellendi");
    } catch (Exception $e) {
        $baglanti->rollback();
        $hata = "❌ Güncelleme sırasında hata oluştu: " . $e->getMessage();
    }
}

// Kullanıcıları çek
$kullanicilar = $baglanti->query("SELECT u.*, r.rol_adi
                                  FROM users u
                                  LEFT JOIN kullanici_rolleri r ON u.rol_id = r.id
                                  ORDER BY u.id DESC");

// Rolleri çek
$roller = $baglanti->query("SELECT * FROM kullanici_rolleri ORDER BY id");
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root {
            --ozbal-bg: #f8fafc;
            --ozbal-primary: #0f172a;
            --ozbal-accent: #3b82f6;
        }

        body {
            background-color: var(--ozbal-bg);
            font-family: 'Inter', sans-serif;
            color: #1e293b;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        .table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        .table td {
            padding: 16px;
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .badge-soft {
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .badge-soft-primary {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-soft-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-soft-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-soft-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-soft-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .form-select-sm {
            font-size: 0.8rem;
            border-radius: 8px;
        }

        .modal-content {
            border: none;
            border-radius: 16px;
        }

        .modal-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
        }

        .table-premium {
            background: transparent;
            border-collapse: separate;
            border-spacing: 0 10px;
            width: 100%;
        }

        .table-premium tbody tr {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            transition: all 0.2s;
        }

        .table-premium tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .table-premium td,
        .table-premium th {
            padding: 1rem;
            border: none;
        }

        .table-premium td:first-child {
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }

        .table-premium td:last-child {
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .table-premium thead th {
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.1em;
            font-weight: 700;
            padding-bottom: 0.5rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #e0e7ff;
            color: #4338ca;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-weight: 700;
        }

        .permission-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-right: 4px;
            margin-bottom: 4px;
        }

        .perm-read {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #d1fae5;
        }

        .perm-write {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #dbeafe;
        }

        .perm-approve {
            background: #fff7ed;
            color: #d97706;
            border: 1px solid #ffedd5;
        }

        /* Switch Styling */
        .form-check-input:checked {
            background-color: var(--ozbal-accent);
            border-color: var(--ozbal-accent);
        }

        /* Tab Styling */
        .nav-pills-custom .nav-link {
            color: #64748b;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s;
            margin-right: 8px;
            margin-bottom: 8px;
        }

        .nav-pills-custom .nav-link.active {
            background: var(--ozbal-accent);
            color: white;
            border-color: var(--ozbal-accent);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
        }

        .module-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }

        .module-card:hover {
            border-color: var(--ozbal-accent);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        /* Modül grupları için stiller */
        .module-group {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .module-group-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #64748b;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
        }

        .module-group-title i {
            margin-right: 8px;
            color: #f5a623;
        }

        /* Bireysel yetki kartları */
        .bireysel-modul-card {
            transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
            cursor: default;
        }

        .bireysel-modul-card:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }
    </style>
</head>

<body class="bg-light">

    <?php include("navbar.php"); ?>

    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0"><i class="fas fa-users text-primary"></i> Kullanıcı Yönetimi</h2>
                <p class="text-muted mb-0">Kullanıcı rolleri ve yetkilendirme</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#yeniKullaniciModal">
                <i class="fas fa-user-plus"></i> Yeni Kullanıcı
            </button>
        </div>



        <div class="table-responsive">
            <table id="userTable" class="table table-premium align-middle mb-0">
                <thead>
                    <tr>
                        <th>KULLANICI</th>
                        <th>İLETİŞİM / ID</th>
                        <th>ROL ATAMASI</th>
                        <th>DURUM</th>
                        <th class="text-end">İŞLEMLER</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $kullanicilar->data_seek(0);
                    while ($user = $kullanicilar->fetch_assoc()) {
                        $statusClass = $user['aktif'] ? 'badge-soft-success' : 'badge-soft-secondary';
                        $statusText = $user['aktif'] ? 'AKTİF' : 'PASİF';
                        $initials = strtoupper(substr($user['kadi'], 0, 2));
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo $user['tam_ad'] ?: $user['kadi']; ?></div>
                                        <div class="text-muted smaller">@<?php echo $user['kadi']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark small fw-medium"><?php echo $user['email'] ?: '-'; ?></div>
                                <div class="text-muted smaller">ID: #<?php echo $user['id']; ?></div>
                            </td>
                            <td>
                                <form method="post" class="d-flex gap-2">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <select name="rol_id" class="form-select form-select-sm border-0 bg-light"
                                        style="width: 140px;">
                                        <?php
                                        $roller->data_seek(0);
                                        while ($rol = $roller->fetch_assoc()) {
                                            $selected = ($user['rol_id'] == $rol['id']) ? 'selected' : '';
                                            echo "<option value='{$rol['id']}' $selected>{$rol['rol_adi']}</option>";
                                        }
                                        ?>
                                    </select>
                                    <button type="submit" name="rol_ata" class="btn btn-sm btn-primary btn-action">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="aktif" value="<?php echo $user['aktif']; ?>">
                                    <button type="submit" name="toggle_aktif" class="border-0 bg-transparent p-0">
                                        <span class="badge-soft <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </button>
                                </form>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <!-- Düzenle -->
                                    <button class="btn btn-sm btn-outline-primary btn-action" data-bs-toggle="modal"
                                        data-bs-target="#duzenleModal" data-user-id="<?php echo $user['id']; ?>"
                                        data-user-kadi="<?php echo htmlspecialchars($user['kadi']); ?>"
                                        data-user-name="<?php echo htmlspecialchars($user['tam_ad'] ?? ''); ?>"
                                        data-user-email="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                        data-user-telefon="<?php echo htmlspecialchars($user['telefon'] ?? ''); ?>"
                                        data-user-rol="<?php echo $user['rol_id']; ?>" title="Düzenle">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <!-- Bireysel Yetki -->
                                    <button class="btn btn-sm btn-outline-info btn-action" data-bs-toggle="modal"
                                        data-bs-target="#bireyselYetkiModal" data-user-id="<?php echo $user['id']; ?>"
                                        data-user-name="<?php echo htmlspecialchars($user['tam_ad'] ?: $user['kadi']); ?>"
                                        data-user-rol="<?php echo htmlspecialchars($user['rol_adi'] ?? ''); ?>"
                                        title="Bireysel Yetki Ayarla">
                                        <i class="fas fa-user-shield"></i>
                                    </button>

                                    <!-- Şifre Değiştir -->
                                    <button class="btn btn-sm btn-outline-warning btn-action" data-bs-toggle="modal"
                                        data-bs-target="#sifreDegistirModal" data-user-id="<?php echo $user['id']; ?>"
                                        data-user-name="<?php echo $user['tam_ad'] ?: $user['kadi']; ?>"
                                        title="Şifre Değiştir">
                                        <i class="fas fa-key"></i>
                                    </button>

                                    <!-- Log Görüntüle -->
                                    <button class="btn btn-sm btn-outline-secondary btn-action"
                                        onclick="logGoruntule(<?php echo $user['id']; ?>)" title="Geçmiş">
                                        <i class="fas fa-history"></i>
                                    </button>

                                    <!-- Sil (admin ve kendi hesabı hariç) -->
                                    <?php
                                    $aktif_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
                                    if ($user['id'] != 1 && $user['id'] != $aktif_user_id): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-action" title="Sil"
                                            data-bs-toggle="modal" data-bs-target="#silOnayModal"
                                            data-user-id="<?php echo $user['id']; ?>"
                                            data-user-name="<?php echo $user['tam_ad'] ?: $user['kadi']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="mt-5 pt-4 border-top">
            <div class="row align-items-center mb-4">
                <div class="col">
                    <h5 class="fw-bold mb-1"><i class="fas fa-user-shield text-primary me-2"></i>Yetki ve Modül Yönetimi
                    </h5>
                    <p class="text-muted small mb-0">Düzenlemek istediğiniz rolü seçerek yetkileri anlık
                        güncelleyebilirsiniz.</p>
                </div>
            </div>

            <!-- Rol Seçimi (Tablar) -->
            <ul class="nav nav-pills nav-pills-custom mb-4" id="roleTabs" role="tablist">
                <?php
                $roller->data_seek(0);
                $first = true;
                while ($rol = $roller->fetch_assoc()) {
                    $activeClass = $first ? 'active' : '';
                    echo "<li class='nav-item' role='presentation'>
                            <button class='nav-link $activeClass' id='tab-{$rol['id']}' data-bs-toggle='pill' data-bs-target='#role-content-{$rol['id']}' type='button' role='tab'>
                                <i class='fas fa-user-tag me-1 opacity-50'></i>{$rol['rol_adi']}
                            </button>
                          </li>";
                    $first = false;
                }
                ?>
            </ul>

            <!-- Yetki İçerikleri -->
            <div class="tab-content" id="roleTabsContent">
                <?php
                $roller->data_seek(0);
                $first = true;
                while ($rol = $roller->fetch_assoc()) {
                    $activeClass = $first ? 'show active' : '';
                    ?>
                    <div class="tab-pane fade <?php echo $activeClass; ?>" id="role-content-<?php echo $rol['id']; ?>"
                        role="tabpanel">
                        <form method="post">
                            <input type="hidden" name="rol_id" value="<?php echo $rol['id']; ?>">
                            <input type="hidden" name="yetki_toplu_guncelle" value="1">

                            <?php
                            // Modül grupları (navbar.php sırasıyla uyumlu)
                            // Her modül: 'db_adi' (veritabanı için), 'gorunen_adi' (UI için), 'ikon'
                            $modul_gruplari = [
                                'Üretim' => [
                                    'ikon' => 'fa-industry',
                                    'moduller' => [
                                        ['db_adi' => 'Hammadde Yönetimi', 'gorunen_adi' => 'Hammadde', 'ikon' => 'fa-truck-loading'],
                                        ['db_adi' => 'Planlama & Takvim', 'gorunen_adi' => 'Planlama', 'ikon' => 'fa-calendar-alt'],
                                        ['db_adi' => 'Üretim Paneli', 'gorunen_adi' => 'Üretim', 'ikon' => 'fa-industry']
                                    ]
                                ],
                                'Satış & Sipariş' => [
                                    'ikon' => 'fa-shopping-bag',
                                    'moduller' => [
                                        ['db_adi' => 'Satış & Siparişler', 'gorunen_adi' => 'Siparişler', 'ikon' => 'fa-shopping-bag'],
                                        ['db_adi' => 'Pazarlama', 'gorunen_adi' => 'Pazarlama', 'ikon' => 'fa-bullhorn'],
                                        ['db_adi' => 'Müşteriler', 'gorunen_adi' => 'Müşteriler', 'ikon' => 'fa-users']
                                    ]
                                ],
                                'Lojistik' => [
                                    'ikon' => 'fa-boxes-stacked',
                                    'moduller' => [
                                        ['db_adi' => 'Satın Alma', 'gorunen_adi' => 'Satın Alma', 'ikon' => 'fa-shopping-cart'],
                                        ['db_adi' => 'Sevkiyat & Lojistik', 'gorunen_adi' => 'Depo & Sevk', 'ikon' => 'fa-boxes-stacked'],
                                        ['db_adi' => 'Stok Takibi', 'gorunen_adi' => 'Malzeme Stok', 'ikon' => 'fa-cubes']
                                    ]
                                ],
                                'Kalite' => [
                                    'ikon' => 'fa-flask',
                                    'moduller' => [
                                        ['db_adi' => 'İzlenebilirlik', 'gorunen_adi' => 'İzlenebilirlik', 'ikon' => 'fa-barcode'],
                                        ['db_adi' => 'Lab Analizleri', 'gorunen_adi' => 'Lab Analiz', 'ikon' => 'fa-flask']
                                    ]
                                ],
                                'Bakım' => [
                                    'ikon' => 'fa-tools',
                                    'moduller' => [
                                        ['db_adi' => 'Bakım & Arıza', 'gorunen_adi' => 'Makine Bakım', 'ikon' => 'fa-tools']
                                    ]
                                ],
                                'Tanımlar' => [
                                    'ikon' => 'fa-tags',
                                    'moduller' => [
                                        ['db_adi' => 'Silo Yönetimi', 'gorunen_adi' => 'Silo Yönetimi', 'ikon' => 'fa-database'],
                                        ['db_adi' => 'Hammadde Kodlama', 'gorunen_adi' => 'Hammadde Kod', 'ikon' => 'fa-tags']
                                    ]
                                ],
                                'Yönetici' => [
                                    'ikon' => 'fa-shield-halved',
                                    'moduller' => [
                                        ['db_adi' => 'Sistem Ayarları', 'gorunen_adi' => 'Kullanıcılar', 'ikon' => 'fa-users-gear']
                                    ]
                                ]
                            ];

                            foreach ($modul_gruplari as $grup_adi => $grup_detay) {
                                ?>
                                <div class="module-group">
                                    <div class="module-group-title">
                                        <i class="fas <?php echo $grup_detay['ikon']; ?>"></i>
                                        <?php echo $grup_adi; ?>
                                    </div>
                                    <div class="row g-3">
                                        <?php
                                        foreach ($grup_detay['moduller'] as $modul_bilgi) {
                                            $db_adi = $modul_bilgi['db_adi'];
                                            $gorunen_adi = $modul_bilgi['gorunen_adi'];
                                            $ikon = $modul_bilgi['ikon'];
                                            $yetki_sorgu = $baglanti->query("SELECT * FROM modul_yetkileri WHERE rol_id = {$rol['id']} AND modul_adi = '$db_adi'");
                                            $yetki = $yetki_sorgu->fetch_assoc();
                                            ?>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="module-card h-100 mb-0">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div class="bg-light p-2 rounded-3 me-3 text-primary">
                                                            <i class="fas <?php echo $ikon; ?>"></i>
                                                        </div>
                                                        <div class="fw-bold text-dark small"><?php echo $gorunen_adi; ?></div>
                                                    </div>

                                                    <div class="space-y-2">
                                                        <div
                                                            class="form-check form-switch d-flex justify-content-between align-items-center ps-0 mb-2">
                                                            <label class="form-check-label smaller text-muted">Görüntüleme
                                                                (Oku)</label>
                                                            <input class="form-check-input ms-0" type="checkbox"
                                                                name="yetkiler[<?php echo $db_adi; ?>][okuma]" value="1" <?php echo ($yetki['okuma'] ?? 0) ? 'checked' : ''; ?>>
                                                        </div>
                                                        <div
                                                            class="form-check form-switch d-flex justify-content-between align-items-center ps-0 mb-2">
                                                            <label class="form-check-label smaller text-muted">İşlem Yapma
                                                                (Yaz)</label>
                                                            <input class="form-check-input ms-0" type="checkbox"
                                                                name="yetkiler[<?php echo $db_adi; ?>][yazma]" value="1" <?php echo ($yetki['yazma'] ?? 0) ? 'checked' : ''; ?>>
                                                        </div>
                                                        <div
                                                            class="form-check form-switch d-flex justify-content-between align-items-center ps-0 mb-0">
                                                            <label class="form-check-label smaller text-muted">Onay Yetkisi</label>
                                                            <input class="form-check-input ms-0" type="checkbox"
                                                                name="yetkiler[<?php echo $db_adi; ?>][onaylama]" value="1" <?php echo ($yetki['onaylama'] ?? 0) ? 'checked' : ''; ?>>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>

                            <div class="mt-4 p-3 bg-white border rounded-4 d-flex justify-content-end gap-2 shadow-sm">
                                <button type="reset" class="btn btn-light px-4 fw-semibold text-muted">
                                    <i class="fas fa-undo me-2"></i>Sıfırla
                                </button>
                                <button type="submit" class="btn btn-primary px-5 fw-bold">
                                    <i class="fas fa-save me-2"></i>Değişiklikleri Kaydet
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php
                    $first = false;
                }
                ?>
            </div>
        </div>

    </div>

    <div class="modal fade" id="yeniKullaniciModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0" style="overflow: hidden;">
                <div class="modal-header border-0"
                    style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">
                    <h5 class="modal-title fw-bold text-white">
                        <i class="fas fa-user-plus me-2 text-warning"></i> Yeni Kullanıcı Ekle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="yeniKullaniciForm">
                    <div class="modal-body p-4" style="background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);">
                        <div class="row g-3">
                            <!-- Kullanıcı Adı -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fas fa-at text-primary me-1"></i> Kullanıcı Adı *
                                </label>
                                <input type="text" name="kadi" class="form-control border-0 shadow-sm"
                                    placeholder="orn: emre123" required minlength="3" autocomplete="off"
                                    style="background: #fff;">
                                <div class="form-text text-muted">Giriş için kullanılacak</div>
                            </div>

                            <!-- Şifre -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fas fa-lock text-primary me-1"></i> Şifre *
                                </label>
                                <div class="input-group shadow-sm">
                                    <input type="password" name="sifre" id="yeni_kullanici_sifre"
                                        class="form-control border-0" style="background: #fff;"
                                        placeholder="En az 4 karakter" required minlength="4">
                                    <button type="button" class="btn btn-light border-0"
                                        onclick="togglePassword('yeni_kullanici_sifre')">
                                        <i class="fas fa-eye text-secondary"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Tam Ad -->
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fas fa-user text-success me-1"></i> Tam Ad Soyad
                                </label>
                                <input type="text" name="tam_ad" class="form-control border-0 shadow-sm"
                                    placeholder="Örn: Emre Yılmaz" style="background: #fff;">
                            </div>

                            <!-- E-posta -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fas fa-envelope text-info me-1"></i> E-Posta
                                </label>
                                <input type="email" name="email" class="form-control border-0 shadow-sm"
                                    placeholder="eposta@ozbalun.com" style="background: #fff;">
                            </div>

                            <!-- Telefon -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fas fa-phone text-danger me-1"></i> Telefon *
                                </label>
                                <input type="tel" name="telefon" id="yeni_kullanici_telefon"
                                    class="form-control border-0 shadow-sm" placeholder="05XXXXXXXXX"
                                    style="background: #fff;" required maxlength="11" minlength="10"
                                    pattern="[0-9]{10,11}" title="10-11 haneli telefon numarası"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>

                            <!-- Rol -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fas fa-user-tag text-warning me-1"></i> Yetki Rolü *
                                </label>
                                <select name="rol_id" class="form-select border-0 shadow-sm" required
                                    style="background: #fff url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 16 16%27%3e%3cpath fill=%27none%27 stroke=%27%23343a40%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%272%27 d=%27m2 5 6 6 6-6%27/%3e%3c/svg%3e') no-repeat right 0.75rem center/12px 12px;">
                                    <option value="">Bir rol seçin...</option>
                                    <?php
                                    $roller->data_seek(0);
                                    while ($rol = $roller->fetch_assoc()) {
                                        echo "<option value='{$rol['id']}'>{$rol['rol_adi']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="alert alert-dark small mt-4 mb-0 border-0"
                            style="background: rgba(30, 41, 59, 0.1);">
                            <i class="fas fa-info-circle me-1 text-primary"></i>
                            Kullanıcı, belirlediğiniz kullanıcı adı ve şifre ile sisteme giriş yapabilecektir.
                        </div>
                    </div>
                    <div class="modal-footer border-0" style="background: #e2e8f0;">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> İptal
                        </button>
                        <button type="submit" name="kullanici_ekle" class="btn px-4 fw-bold text-white"
                            style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">
                            <i class="fas fa-check me-1"></i> Kullanıcıyı Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Şifre Değiştirme Modal -->
    <div class="modal fade" id="sifreDegistirModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title fw-bold"><i class="fas fa-key me-2"></i> Şifre Değiştir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="sifreDegistirForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="user_id" id="sifre_user_id">

                        <div class="alert alert-secondary">
                            <i class="fas fa-user me-2"></i>
                            <strong id="sifre_user_name_display">Kullanıcı</strong> için yeni şifre belirleyin
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Yeni Şifre</label>
                            <div class="input-group">
                                <input type="password" name="yeni_sifre" id="yeni_sifre" class="form-control"
                                    placeholder="En az 4 karakter" required minlength="4">
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePassword('yeni_sifre')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Yeni Şifre (Tekrar)</label>
                            <div class="input-group">
                                <input type="password" name="yeni_sifre_tekrar" id="yeni_sifre_tekrar"
                                    class="form-control" placeholder="Şifreyi tekrar girin" required minlength="4">
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePassword('yeni_sifre_tekrar')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="sifre_uyari" class="form-text text-danger" style="display:none;">
                                <i class="fas fa-exclamation-triangle"></i> Şifreler eşleşmiyor!
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-link text-muted text-decoration-none"
                            data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="sifre_degistir" class="btn btn-warning px-4 fw-bold"
                            id="sifreDegistirBtn">
                            <i class="fas fa-save me-2"></i> Şifreyi Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="silOnayModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content shadow-lg border-0" style="overflow: hidden;">
                <div class="modal-header border-0"
                    style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">
                    <h5 class="modal-title fw-bold text-white">
                        <i class="fas fa-exclamation-triangle me-2"></i> Silme Onayı
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-user-times fa-3x text-danger"></i>
                    </div>
                    <h5 class="fw-bold" id="sil_user_name_display">Kullanıcı</h5>
                    <p class="text-muted mb-0">Bu kullanıcıyı silmek istediğinize emin misiniz?</p>
                    <p class="text-danger small"><i class="fas fa-warning me-1"></i> Bu işlem geri alınamaz!</p>
                </div>
                <div class="modal-footer border-0 bg-light justify-content-center">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Vazgeç
                    </button>
                    <form method="post" id="silForm" class="d-inline">
                        <input type="hidden" name="user_id" id="sil_user_id">
                        <button type="submit" name="kullanici_sil" class="btn btn-danger px-4 fw-bold">
                            <i class="fas fa-trash me-1"></i> Evet, Sil
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Kullanıcı Düzenleme Modal -->
    <div class="modal fade" id="duzenleModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0" style="overflow: hidden;">
                <div class="modal-header border-0"
                    style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                    <h5 class="modal-title fw-bold text-white">
                        <i class="fas fa-user-edit me-2"></i> Kullanıcı Düzenle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="duzenleForm">
                    <div class="modal-body p-4" style="background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);">
                        <input type="hidden" name="user_id" id="duzenle_user_id">

                        <div class="row g-3">
                            <!-- Kullanıcı Adı -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fas fa-at text-primary me-1"></i> Kullanıcı Adı *
                                </label>
                                <input type="text" name="kadi" id="duzenle_kadi" class="form-control border-0 shadow-sm"
                                    placeholder="Kullanıcı adı" style="background: #fff;" required minlength="3">
                            </div>

                            <!-- Tam Ad -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fas fa-user text-success me-1"></i> Tam Ad Soyad
                                </label>
                                <input type="text" name="tam_ad" id="duzenle_tam_ad"
                                    class="form-control border-0 shadow-sm" placeholder="Ad Soyad"
                                    style="background: #fff;">
                            </div>

                            <!-- E-posta -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fas fa-envelope text-info me-1"></i> E-Posta
                                </label>
                                <input type="email" name="email" id="duzenle_email"
                                    class="form-control border-0 shadow-sm" placeholder="eposta@ozbalun.com"
                                    style="background: #fff;">
                            </div>

                            <!-- Telefon -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fas fa-phone text-danger me-1"></i> Telefon *
                                </label>
                                <input type="tel" name="telefon" id="duzenle_telefon"
                                    class="form-control border-0 shadow-sm" placeholder="05XXXXXXXXX"
                                    style="background: #fff;" required maxlength="11" minlength="10"
                                    pattern="[0-9]{10,11}" title="10-11 haneli telefon numarası"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>

                            <!-- Rol -->
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fas fa-user-tag text-warning me-1"></i> Yetki Rolü *
                                </label>
                                <select name="rol_id" id="duzenle_rol_id" class="form-select border-0 shadow-sm"
                                    required
                                    style="background: #fff url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 16 16%27%3e%3cpath fill=%27none%27 stroke=%27%23343a40%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%272%27 d=%27m2 5 6 6 6-6%27/%3e%3c/svg%3e') no-repeat right 0.75rem center/12px 12px;">
                                    <?php
                                    $roller->data_seek(0);
                                    while ($rol = $roller->fetch_assoc()) {
                                        echo "<option value='{$rol['id']}'>{$rol['rol_adi']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0" style="background: #e2e8f0;">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> İptal
                        </button>
                        <button type="submit" name="kullanici_guncelle" class="btn btn-primary px-4 fw-bold">
                            <i class="fas fa-save me-1"></i> Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- BİREYSEL YETKİ MODAL -->
    <!-- ============================================================ -->
    <div class="modal fade" id="bireyselYetkiModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content shadow-lg border-0" style="overflow: hidden;">
                <div class="modal-header border-0"
                    style="background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);">
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">
                            <i class="fas fa-user-shield me-2"></i>
                            Bireysel Yetki Ayarları
                        </h5>
                        <small class="text-white opacity-75" id="bireysel_user_subtitle">Kullanıcı</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <form method="post" id="bireyselYetkiForm">
                    <input type="hidden" name="hedef_user_id" id="bireysel_hedef_user_id">
                    <input type="hidden" name="kullanici_bireysel_yetki_guncelle" value="1">

                    <div class="modal-body p-0">
                        <!-- Bilgi Bandı -->
                        <div class="px-4 py-3" style="background: #f0f9ff; border-bottom: 1px solid #bae6fd;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center"
                                    style="width:42px;height:42px;background:#0891b2;color:white;font-weight:700;font-size:1rem;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark" id="bireysel_user_name_display">-</div>
                                    <div class="text-muted small">
                                        <i class="fas fa-user-tag me-1"></i>
                                        Rol: <strong id="bireysel_user_rol_display">-</strong>
                                        <span class="ms-3 text-info">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Override açık modüller rol yetkisini geçersiz kılar
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Yükleniyor göstergesi -->
                        <div id="bireysel_loading" class="text-center py-5">
                            <div class="spinner-border text-info" role="status"></div>
                            <div class="text-muted mt-2">Yükleniyor...</div>
                        </div>

                        <!-- Modül Listesi -->
                        <div id="bireysel_modul_listesi" class="p-4"
                            style="display:none; max-height:60vh; overflow-y:auto;">
                            <?php
                            $bireysel_modul_gruplari = [
                                'Üretim' => [
                                    'ikon' => 'fa-industry',
                                    'renk' => '#6366f1',
                                    'moduller' => [
                                        ['db_adi' => 'Hammadde Yönetimi', 'gorunen_adi' => 'Hammadde', 'ikon' => 'fa-truck-loading'],
                                        ['db_adi' => 'Planlama & Takvim', 'gorunen_adi' => 'Planlama', 'ikon' => 'fa-calendar-alt'],
                                        ['db_adi' => 'Üretim Paneli', 'gorunen_adi' => 'Üretim', 'ikon' => 'fa-industry'],
                                    ]
                                ],
                                'Satış & Sipariş' => [
                                    'ikon' => 'fa-shopping-bag',
                                    'renk' => '#f59e0b',
                                    'moduller' => [
                                        ['db_adi' => 'Satış & Siparişler', 'gorunen_adi' => 'Siparişler', 'ikon' => 'fa-shopping-bag'],
                                        ['db_adi' => 'Pazarlama', 'gorunen_adi' => 'Pazarlama', 'ikon' => 'fa-bullhorn'],
                                        ['db_adi' => 'Müşteriler', 'gorunen_adi' => 'Müşteriler', 'ikon' => 'fa-users']
                                    ]
                                ],
                                'Lojistik' => [
                                    'ikon' => 'fa-boxes-stacked',
                                    'renk' => '#10b981',
                                    'moduller' => [
                                        ['db_adi' => 'Satın Alma', 'gorunen_adi' => 'Satın Alma', 'ikon' => 'fa-shopping-cart'],
                                        ['db_adi' => 'Sevkiyat & Lojistik', 'gorunen_adi' => 'Depo & Sevk', 'ikon' => 'fa-boxes-stacked'],
                                        ['db_adi' => 'Stok Takibi', 'gorunen_adi' => 'Malzeme Stok', 'ikon' => 'fa-cubes'],
                                    ]
                                ],
                                'Kalite' => [
                                    'ikon' => 'fa-flask',
                                    'renk' => '#8b5cf6',
                                    'moduller' => [
                                        ['db_adi' => 'İzlenebilirlik', 'gorunen_adi' => 'İzlenebilirlik', 'ikon' => 'fa-barcode'],
                                        ['db_adi' => 'Lab Analizleri', 'gorunen_adi' => 'Lab Analiz', 'ikon' => 'fa-flask'],
                                    ]
                                ],
                                'Bakım' => [
                                    'ikon' => 'fa-tools',
                                    'renk' => '#ef4444',
                                    'moduller' => [
                                        ['db_adi' => 'Bakım & Arıza', 'gorunen_adi' => 'Makine Bakım', 'ikon' => 'fa-tools'],
                                    ]
                                ],
                                'Tanımlar' => [
                                    'ikon' => 'fa-tags',
                                    'renk' => '#f97316',
                                    'moduller' => [
                                        ['db_adi' => 'Silo Yönetimi', 'gorunen_adi' => 'Silo Yönetimi', 'ikon' => 'fa-database'],
                                        ['db_adi' => 'Hammadde Kodlama', 'gorunen_adi' => 'Hammadde Kod', 'ikon' => 'fa-tags'],
                                    ]
                                ],
                                'Yönetici' => [
                                    'ikon' => 'fa-shield-halved',
                                    'renk' => '#0891b2',
                                    'moduller' => [
                                        ['db_adi' => 'Sistem Ayarları', 'gorunen_adi' => 'Kullanıcılar', 'ikon' => 'fa-users-gear'],
                                    ]
                                ],
                            ];

                            foreach ($bireysel_modul_gruplari as $grup_adi => $grup_detay) {
                                echo "<div class='mb-4'>";
                                echo "<div class='d-flex align-items-center mb-2 pb-1' style='border-bottom:2px solid #e2e8f0;'>";
                                echo "<i class='fas {$grup_detay['ikon']} me-2' style='color:{$grup_detay['renk']};'></i>";
                                echo "<span class='fw-bold text-uppercase small text-muted' style='letter-spacing:1px;'>$grup_adi</span>";
                                echo "</div>";
                                echo "<div class='row g-2'>";

                                foreach ($grup_detay['moduller'] as $m) {
                                    $db_adi = $m['db_adi'];
                                    $gorunen = $m['gorunen_adi'];
                                    $ikon = $m['ikon'];
                                    // UTF-8 güvenli safe_key: Türkçe karakterler (ş,ı,ğ vb.) tek _ olur
                                    $safe_key = '';
                                    foreach (mb_str_split($db_adi) as $ch) {
                                        $safe_key .= preg_match('/[a-zA-Z0-9]/', $ch) ? $ch : '_';
                                    }
                                    echo "
                                    <div class='col-md-6 col-lg-4'>
                                        <div class='bireysel-modul-card p-3 rounded-3 border' style='background:#f8fafc;' data-modul='" . htmlspecialchars($db_adi) . "'>
                                            <div class='d-flex align-items-center justify-content-between mb-2'>
                                                <div class='d-flex align-items-center gap-2'>
                                                    <i class='fas $ikon text-muted small'></i>
                                                    <span class='fw-semibold small text-dark'>$gorunen</span>
                                                </div>
                                                <div class='form-check form-switch mb-0'>
                                                    <input class='form-check-input bireysel-aktif-toggle' type='checkbox'
                                                        name='bireysel_yetkiler[$db_adi][aktif]' value='1'
                                                        id='aktif_{$safe_key}'
                                                        data-modul-adi='" . htmlspecialchars($db_adi, ENT_QUOTES) . "'
                                                        onchange=\"toggleBireyselModul(this, '$safe_key')\">
                                                    <label class='form-check-label small text-muted' for='aktif_{$safe_key}'>Override</label>
                                                </div>
                                            </div>
                                            <div class='bireysel-yetki-detay' id='detay_{$safe_key}' style='display:none;'>
                                                <div class='d-flex gap-3 mt-1 ps-1'>
                                                    <div class='form-check'>
                                                        <input class='form-check-input' type='checkbox'
                                                            name='bireysel_yetkiler[$db_adi][okuma]' value='1'
                                                            id='okuma_{$safe_key}'>
                                                        <label class='form-check-label small text-success' for='okuma_{$safe_key}'>
                                                            <i class='fas fa-eye me-1'></i>Görüntüle
                                                        </label>
                                                    </div>
                                                    <div class='form-check'>
                                                        <input class='form-check-input' type='checkbox'
                                                            name='bireysel_yetkiler[$db_adi][yazma]' value='1'
                                                            id='yazma_{$safe_key}'>
                                                        <label class='form-check-label small text-primary' for='yazma_{$safe_key}'>
                                                            <i class='fas fa-pen me-1'></i>İşlem
                                                        </label>
                                                    </div>
                                                    <div class='form-check'>
                                                        <input class='form-check-input' type='checkbox'
                                                            name='bireysel_yetkiler[$db_adi][onaylama]' value='1'
                                                            id='onaylama_{$safe_key}'>
                                                        <label class='form-check-label small text-warning' for='onaylama_{$safe_key}'>
                                                            <i class='fas fa-check me-1'></i>Onayla
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class='bireysel-rol-bilgi small text-muted mt-1 ps-1' id='rolbilgi_{$safe_key}'>
                                                <i class='fas fa-info-circle me-1 text-secondary'></i>
                                                <span class='rol-yetki-text'>Rol yetkisi geçerli</span>
                                            </div>
                                        </div>
                                    </div>";
                                }

                                echo "</div></div>";
                            }
                            ?>
                        </div>
                    </div>

                    <div class="modal-footer border-0" style="background: #f0f9ff;">
                        <div class="me-auto small text-muted">
                            <i class="fas fa-lightbulb text-warning me-1"></i>
                            Override kapalı modüllerde rol yetkisi geçerlidir
                        </div>
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> İptal
                        </button>
                        <button type="submit" class="btn btn-info text-white px-5 fw-bold">
                            <i class="fas fa-save me-2"></i>Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sunucu Bilgi Kartı -->
    <div class="container py-4 border-top mt-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-server me-2 text-danger"></i> Sunucu (Raspberry Pi) Durumu</h5>
            </div>
            <div class="card-body bg-light">
                <div class="row text-center">
                    <div class="col-md-3 border-end">
                        <h5 class="text-info"><?php echo $_SERVER['SERVER_ADDR'] ?? 'localhost'; ?></h5>
                        <small class="text-muted">Sunucu IP</small>
                    </div>
                    <div class="col-md-3 border-end">
                        <h5 class="text-success">PHP <?php echo phpversion(); ?></h5>
                        <small class="text-muted">PHP Sürümü</small>
                    </div>
                    <div class="col-md-3 border-end">
                        <h5 class="text-warning">Apache 2</h5>
                        <small class="text-muted">Web Sunucusu</small>
                    </div>
                    <div class="col-md-3">
                        <h5 class="text-danger">MariaDB</h5>
                        <small class="text-muted">Veritabanı</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

    <script>
        $(document).ready(function () {
            // SweetAlert2 Alerts
            <?php if (!empty($mesaj)): ?>
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: '<?php echo addslashes(str_replace(["✅ ", "✓ "], "", strip_tags($mesaj))); ?>',
                    showConfirmButton: false,
                    showCloseButton: true,
                    timer: 5000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });
            <?php endif; ?>

            <?php if (!empty($hata)): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Hata!',
                    text: '<?php echo addslashes(str_replace(["❌ ", "✖ ", "HATA: "], "", strip_tags($hata))); ?>',
                    confirmButtonColor: '#0f172a'
                });
            <?php endif; ?>

            $('#userTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json"
                },
                "pageLength": 10,
                "order": [[0, "desc"]],
                "columnDefs": [
                    { "orderable": false, "targets": [2, 3, 4] }
                ]
            });
        });

        function logGoruntule(user_id) {
            window.location.href = 'islem_gecmisi.php?user_id=' + user_id;
        }

        // ============================================================
        // BİREYSEL YETKİ MODAL
        // ============================================================
        function toggleBireyselModul(checkbox, safeKey) {
            const detayDiv = document.getElementById('detay_' + safeKey);
            const rolBilgi = document.getElementById('rolbilgi_' + safeKey);
            const card = checkbox.closest('.bireysel-modul-card');

            if (checkbox.checked) {
                detayDiv.style.display = 'block';
                rolBilgi.style.display = 'none';
                card.style.background = '#eff6ff';
                card.style.borderColor = '#3b82f6';
            } else {
                detayDiv.style.display = 'none';
                rolBilgi.style.display = 'block';
                card.style.background = '#f8fafc';
                card.style.borderColor = '#e2e8f0';
                // Checkbox'ları temizle
                ['okuma_', 'yazma_', 'onaylama_'].forEach(function (p) {
                    const el = document.getElementById(p + safeKey);
                    if (el) el.checked = false;
                });
            }
        }

        document.getElementById('bireyselYetkiModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const userName = button.getAttribute('data-user-name');
            const userRol = button.getAttribute('data-user-rol');

            document.getElementById('bireysel_hedef_user_id').value = userId;
            document.getElementById('bireysel_user_name_display').textContent = userName;
            document.getElementById('bireysel_user_subtitle').textContent = 'Kullanıcı ID: #' + userId;
            document.getElementById('bireysel_user_rol_display').textContent = userRol;

            // Tüm override'ları sıfırla
            document.querySelectorAll('.bireysel-aktif-toggle').forEach(function (cb) {
                cb.checked = false;
                const safeKey = cb.id.replace('aktif_', '');
                const detayDiv = document.getElementById('detay_' + safeKey);
                const rolBilgi = document.getElementById('rolbilgi_' + safeKey);
                const card = cb.closest('.bireysel-modul-card');
                if (detayDiv) detayDiv.style.display = 'none';
                if (rolBilgi) rolBilgi.style.display = 'block';
                if (card) { card.style.background = '#f8fafc'; card.style.borderColor = '#e2e8f0'; }
                ['okuma_', 'yazma_', 'onaylama_'].forEach(function (p) {
                    const el = document.getElementById(p + safeKey);
                    if (el) el.checked = false;
                });
            });

            // Yükleniyor göster
            document.getElementById('bireysel_loading').style.display = 'block';
            document.getElementById('bireysel_modul_listesi').style.display = 'none';

            // AJAX ile mevcut bireysel yetkileri ve rol yetkilerini çek
            fetch('get_bireysel_yetkiler.php?user_id=' + userId)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    document.getElementById('bireysel_loading').style.display = 'none';
                    document.getElementById('bireysel_modul_listesi').style.display = 'block';

                    const overrides = data.overrides || {};
                    const rolYetkileri = data.rol_yetkileri || {};

                    // Her modül kartının "rol yetkisi" göstergesini güncelle
                    document.querySelectorAll('.bireysel-aktif-toggle').forEach(function (cb) {
                        const modulAdi = cb.getAttribute('data-modul-adi');
                        const safeKey = cb.id.replace('aktif_', '');
                        const rolBilgiEl = document.getElementById('rolbilgi_' + safeKey);

                        if (rolBilgiEl && rolYetkileri[modulAdi]) {
                            const r = rolYetkileri[modulAdi];
                            const okBadge = r.okuma == 1 ? '<span class="badge bg-success me-1"><i class="fas fa-eye me-1"></i>Okuma</span>' : '<span class="badge bg-secondary me-1 opacity-50"><i class="fas fa-eye-slash me-1"></i>Okuma</span>';
                            const yzBadge = r.yazma == 1 ? '<span class="badge bg-primary me-1"><i class="fas fa-pen me-1"></i>Yazma</span>' : '<span class="badge bg-secondary me-1 opacity-50"><i class="fas fa-pen me-1"></i>Yazma</span>';
                            const onBadge = r.onaylama == 1 ? '<span class="badge bg-warning text-dark"><i class="fas fa-check me-1"></i>Onay</span>' : '<span class="badge bg-secondary opacity-50"><i class="fas fa-times me-1"></i>Onay</span>';
                            rolBilgiEl.innerHTML = '<small class="text-muted d-block mb-1" style="font-size:0.7rem;">Rol yetkisi:</small>' + okBadge + yzBadge + onBadge;
                        } else if (rolBilgiEl) {
                            rolBilgiEl.innerHTML = '<i class="fas fa-ban me-1 text-secondary"></i><span class="text-muted small">Rol yetkisi yok</span>';
                        }
                    });

                    // Override kayıtlarını uygula
                    Object.keys(overrides).forEach(function (modulAdi) {
                        const row = overrides[modulAdi];
                        var aktifCb = null;
                        document.querySelectorAll('.bireysel-aktif-toggle').forEach(function (cb) {
                            if (cb.getAttribute('data-modul-adi') === modulAdi) aktifCb = cb;
                        });
                        if (aktifCb) {
                            const safeKey = aktifCb.id.replace('aktif_', '');
                            aktifCb.checked = true;
                            toggleBireyselModul(aktifCb, safeKey);
                            if (row.okuma == 1) { const el = document.getElementById('okuma_' + safeKey); if (el) el.checked = true; }
                            if (row.yazma == 1) { const el = document.getElementById('yazma_' + safeKey); if (el) el.checked = true; }
                            if (row.onaylama == 1) { const el = document.getElementById('onaylama_' + safeKey); if (el) el.checked = true; }
                        }
                    });
                })
                .catch(function () {
                    document.getElementById('bireysel_loading').style.display = 'none';
                    document.getElementById('bireysel_modul_listesi').style.display = 'block';
                });
        });

        // Şifre değiştirme modalı açıldığında kullanıcı bilgilerini doldur
        document.getElementById('sifreDegistirModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const userName = button.getAttribute('data-user-name');

            document.getElementById('sifre_user_id').value = userId;
            document.getElementById('sifre_user_name_display').textContent = userName;

            // Formu temizle
            document.getElementById('yeni_sifre').value = '';
            document.getElementById('yeni_sifre_tekrar').value = '';
            document.getElementById('sifre_uyari').style.display = 'none';
            document.getElementById('sifreDegistirBtn').disabled = false;
        });

        // Silme onay modalı açıldığında kullanıcı bilgilerini doldur
        document.getElementById('silOnayModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const userName = button.getAttribute('data-user-name');

            document.getElementById('sil_user_id').value = userId;
            document.getElementById('sil_user_name_display').textContent = userName;
        });

        // Düzenleme modalı açıldığında kullanıcı bilgilerini doldur
        document.getElementById('duzenleModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            document.getElementById('duzenle_user_id').value = button.getAttribute('data-user-id');
            document.getElementById('duzenle_kadi').value = button.getAttribute('data-user-kadi');
            document.getElementById('duzenle_tam_ad').value = button.getAttribute('data-user-name');
            document.getElementById('duzenle_email').value = button.getAttribute('data-user-email');
            document.getElementById('duzenle_telefon').value = button.getAttribute('data-user-telefon');
            document.getElementById('duzenle_rol_id').value = button.getAttribute('data-user-rol');
        });

        // Şifre eşleşme kontrolü
        document.getElementById('yeni_sifre_tekrar').addEventListener('input', function () {
            const sifre1 = document.getElementById('yeni_sifre').value;
            const sifre2 = this.value;
            const uyari = document.getElementById('sifre_uyari');
            const btn = document.getElementById('sifreDegistirBtn');

            if (sifre2.length > 0 && sifre1 !== sifre2) {
                uyari.style.display = 'block';
                btn.disabled = true;
            } else {
                uyari.style.display = 'none';
                btn.disabled = false;
            }
        });

        // Şifre göster/gizle toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const btn = field.nextElementSibling || field.parentElement.querySelector('button');
            const icon = btn.querySelector('i');

            if (field.getAttribute('type') === 'password') {
                field.setAttribute('type', 'text');
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.setAttribute('type', 'password');
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>
