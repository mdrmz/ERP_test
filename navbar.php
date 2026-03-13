<?php
// Oturum başlatılmamışsa başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Giriş yapılmamışsa login sayfasına at
if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}

$sayfa = basename($_SERVER['PHP_SELF']);
$rol_adi = isset($_SESSION["rol_adi"]) ? $_SESSION["rol_adi"] : '';
$rol_id = isset($_SESSION["rol_id"]) ? (int) $_SESSION["rol_id"] : 0;

// Bildirim sayısını al
$bildirim_sayisi = 0;
if (function_exists('bildirimSayisi')) {
    $bildirim_sayisi = bildirimSayisi($baglanti);
}

// Navbar için modül görünürlük kontrolü
if (!function_exists('navbarModulGoster')) {
    function navbarModulGoster($baglanti, $modul_adi)
    {
        global $rol_adi, $rol_id;
        $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

        // Patron her şeyi görebilir
        if ($rol_adi === 'Patron') {
            return true;
        }

        if ($rol_id <= 0 || $user_id <= 0) {
            return false;
        }

        $modul_adi_esc = $baglanti->real_escape_string($modul_adi);

        // Bireysel override kontrolü
        $sql_bireysel = "SELECT okuma FROM kullanici_modul_yetkileri WHERE user_id = $user_id AND modul_adi = '$modul_adi_esc'";
        $result_bireysel = $baglanti->query($sql_bireysel);
        if ($result_bireysel && $result_bireysel->num_rows > 0) {
            $yetki = $result_bireysel->fetch_assoc();
            return (bool) $yetki['okuma'];
        }

        // Rol bazlı kontrol
        $sql = "SELECT okuma FROM modul_yetkileri 
                WHERE rol_id = $rol_id AND modul_adi = '$modul_adi_esc' AND okuma = 1";
        $result = $baglanti->query($sql);
        return ($result && $result->num_rows > 0);
    }
}
?>

<style>
    :root {
        --sidebar-w: 260px;
        --dark-bg: #1a1d23;
        --darker-bg: #12151a;
        --gold: #f5a623;
        --gold-dim: rgba(245, 166, 35, 0.15);
        --text: #e2e8f0;
        --text-muted: #64748b;
    }

    * {
        box-sizing: border-box;
    }

    /* === MASAÜSTÜ === */
    @media (min-width: 992px) {
        body {
            margin: 0;
            padding-left: var(--sidebar-w) !important;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: var(--dark-bg);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            overflow: hidden;
        }

        .logo-box {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            text-align: center;
        }

        .logo-box h3 {
            margin: 0;
            color: #fff;
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .logo-box h3 i {
            color: var(--gold);
        }

        .logo-box .role {
            display: inline-block;
            margin-top: 10px;
            padding: 4px 14px;
            background: var(--gold);
            color: #000;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 20px;
            letter-spacing: 1px;
        }

        .nav-area {
            flex: 1;
            overflow-y: auto;
            padding: 16px 12px;
        }

        .nav-area::-webkit-scrollbar {
            width: 4px;
        }

        .nav-area::-webkit-scrollbar-thumb {
            background: var(--gold);
            border-radius: 4px;
        }

        .nav-group {
            margin-bottom: 20px;
        }

        .nav-group-title {
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            padding: 0 12px 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 0.95rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.04);
            color: var(--text);
        }

        .nav-link.active {
            background: var(--gold-dim);
            color: var(--gold);
        }

        .admin-box {
            margin-top: 10px;
            padding: 12px;
            background: rgba(139, 92, 246, 0.08);
            border-radius: 10px;
            border: 1px solid rgba(139, 92, 246, 0.15);
        }

        .admin-box .nav-group-title {
            color: #a78bfa;
        }

        .user-footer {
            padding: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            background: var(--darker-bg);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--gold), #d97706);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #000;
            font-size: 1rem;
        }

        .user-name {
            color: #fff;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-status {
            color: #22c55e;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .user-status::before {
            content: '';
            width: 6px;
            height: 6px;
            background: #22c55e;
            border-radius: 50%;
        }

        .logout-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            border-radius: 8px;
            color: #ef4444;
            text-decoration: none;
            text-align: center;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        /* MOBİL ELEMANLARI GİZLE */
        .mobile-header,
        .mobile-nav,
        .menu-overlay,
        .slide-menu {
            display: none !important;
        }
    }

    /* === MOBİL === */
    @media (max-width: 991px) {
        body {
            padding-top: 60px !important;
            padding-bottom: 70px !important;
            padding-left: 0 !important;
        }

        .sidebar {
            display: none;
        }

        .mobile-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 56px;
            background: var(--dark-bg);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .mobile-header .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
        }

        .mobile-header .brand i {
            color: var(--gold);
            font-size: 1.2rem;
        }

        .mobile-header .m-avatar {
            width: 34px;
            height: 34px;
            background: linear-gradient(135deg, var(--gold), #d97706);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #000;
            font-size: 0.85rem;
        }

        .mobile-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 65px;
            background: var(--dark-bg);
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1000;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: env(safe-area-inset-bottom);
        }

        .m-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.6rem;
            font-weight: 500;
            padding: 6px 10px;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .m-link i {
            font-size: 1.2rem;
            margin-bottom: 3px;
        }

        .m-link.active,
        .m-link:hover {
            color: var(--gold);
            background: var(--gold-dim);
        }

        .m-link.danger {
            color: #ef4444;
        }

        .m-link.danger:hover {
            background: rgba(239, 68, 68, 0.15);
        }

        .menu-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 0.6rem;
            font-weight: 500;
            padding: 6px 10px;
            border-radius: 10px;
            cursor: pointer;
        }

        .menu-btn i {
            font-size: 1.2rem;
            margin-bottom: 3px;
        }

        .menu-btn:hover {
            color: var(--gold);
            background: var(--gold-dim);
        }

        .menu-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1100;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .menu-overlay.open {
            opacity: 1;
            visibility: visible;
        }

        .slide-menu {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            max-height: 70vh;
            background: var(--dark-bg);
            border-radius: 20px 20px 0 0;
            z-index: 1110;
            transform: translateY(100%);
            transition: transform 0.35s ease;
            overflow-y: auto;
            padding: 16px 16px 30px;
        }

        .slide-menu.open {
            transform: translateY(0);
        }

        .menu-handle {
            width: 40px;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            margin: 0 auto 16px;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .menu-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px 8px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.65rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .menu-item i {
            font-size: 1.3rem;
            margin-bottom: 6px;
        }

        .menu-item:hover,
        .menu-item.active {
            background: var(--gold-dim);
            border-color: rgba(245, 166, 35, 0.3);
            color: var(--gold);
        }
    }

    /* === BİLDİRİM DROPDOWN STİLLERİ === */
    #bildirimContainer {
        position: relative;
    }

    .bildirim-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        width: 320px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        z-index: 9999;
        display: none;
        margin-top: 10px;
        overflow: hidden;
    }

    .bildirim-dropdown.show {
        display: block;
    }

    .bildirim-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 16px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        color: #1e293b;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .bildirim-header a {
        color: #64748b;
        text-decoration: none;
        font-size: 0.75rem;
    }

    .bildirim-header a:hover {
        color: #f5a623;
    }

    .bildirim-list {
        max-height: 300px;
        overflow-y: auto;
    }

    .bildirim-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        transition: background 0.2s;
        text-decoration: none;
        color: inherit;
    }

    .bildirim-item:hover {
        background: #f8fafc;
    }

    .bildirim-item.okunmamis {
        background: #fffbeb;
        border-left: 3px solid #f5a623;
    }

    .bildirim-item-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.9rem;
    }

    .bildirim-item-content {
        flex: 1;
        min-width: 0;
    }

    .bildirim-item-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .bildirim-item-desc {
        font-size: 0.75rem;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .bildirim-item-time {
        font-size: 0.65rem;
        color: #94a3b8;
        white-space: nowrap;
    }

    .bildirim-footer {
        padding: 12px 16px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        text-align: center;
    }

    .bildirim-footer a {
        color: #f5a623;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .bildirim-footer a:hover {
        text-decoration: underline;
    }

    /* Bildirim Popup */
    .bildirim-popup {
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #1e293b, #334155);
        color: #fff;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        z-index: 99999;
        animation: slideIn 0.3s ease;
        max-width: 320px;
    }

    .bildirim-popup i {
        color: #f5a623;
        margin-right: 10px;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
</style>


<!-- MASAÜSTÜ SIDEBAR -->
<nav class="sidebar">
    <div class="logo-box">
        <h3 class="mb-2"><i class="fas fa-wheat-awn"></i>  ÖZBAL UN</h3>
        <span class="role">
            <?php echo $rol_adi; ?>
        </span>
    </div>


    <div class="nav-area">
        <div class="nav-group">
            <div class="nav-group-title">Ana Panel</div>
            <a href="panel.php" class="nav-link <?php if ($sayfa == 'panel.php')
                echo 'active'; ?>">
                <i class="fas fa-th-large"></i> Genel Bakış
            </a>

            <a href="bildirimler.php" class="nav-link <?php if ($sayfa == 'bildirimler.php')
                echo 'active'; ?>">
                <div class="d-flex justify-content-between w-100 align-items-center">
                    <span><i class="fas fa-bell"></i> Bildirimler</span>
                    <span id="bildirimBadge" class="badge bg-danger rounded-pill"
                        style="<?php echo $bildirim_sayisi > 0 ? '' : 'display: none;'; ?>">
                        <?php echo $bildirim_sayisi; ?>
                    </span>
                </div>
            </a>
        </div>

        <?php if (navbarModulGoster($baglanti, 'Hammadde Yönetimi') || navbarModulGoster($baglanti, 'Planlama & Takvim') || navbarModulGoster($baglanti, 'Üretim Paneli')) { ?>
            <div class="nav-group">
                <div class="nav-group-title">Üretim</div>
                <?php if (navbarModulGoster($baglanti, 'Hammadde Yönetimi')) { ?>
                    <a href="hammadde.php" class="nav-link <?php if ($sayfa == 'hammadde.php')
                        echo 'active'; ?>">
                        <i class="fas fa-truck-loading"></i> Hammadde
                    </a>
                <?php } ?>
                <?php if (navbarModulGoster($baglanti, 'Planlama & Takvim')) { ?>
                    <a href="planlama.php" class="nav-link <?php if ($sayfa == 'planlama.php')
                        echo 'active'; ?>">
                        <i class="fas fa-calendar-alt"></i> Planlama
                    </a>
                <?php } ?>
                <?php if (navbarModulGoster($baglanti, 'Üretim Paneli')) { ?>
                    <a href="uretim.php" class="nav-link <?php if ($sayfa == 'uretim.php')
                        echo 'active'; ?>">
                        <i class="fas fa-industry"></i> Üretim
                    </a>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if (navbarModulGoster($baglanti, 'Satış & Siparişler') || navbarModulGoster($baglanti, 'Pazarlama') || navbarModulGoster($baglanti, 'Müşteriler')) { ?>
            <div class="nav-group">
                <div class="nav-group-title">Satış & Pazarlama</div>
                <?php if (navbarModulGoster($baglanti, 'Müşteriler')) { ?>
                    <a href="musteriler.php" class="nav-link <?php if ($sayfa == 'musteriler.php')
                        echo 'active'; ?>">
                        <i class="fas fa-users"></i> Müşteriler
                    </a>
                    <a href="sikayetler.php" class="nav-link <?php if ($sayfa == 'sikayetler.php')
                        echo 'active'; ?>">
                        <i class="fas fa-comment-dots"></i> Şikayetler & DÖF
                    </a>
                <?php } ?>
                <?php if (navbarModulGoster($baglanti, 'Pazarlama')) { ?>
                    <a href="pazarlama.php" class="nav-link <?php if ($sayfa == 'pazarlama.php')
                        echo 'active'; ?>">
                        <i class="fas fa-bullhorn"></i> Pazarlama
                    </a>
                <?php } ?>
                <?php if (navbarModulGoster($baglanti, 'Satış & Siparişler')) { ?>
                    <a href="siparisler.php" class="nav-link <?php if ($sayfa == 'siparisler.php')
                        echo 'active'; ?>">
                        <i class="fas fa-shopping-bag"></i> Siparişler (İdari)
                    </a>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if (navbarModulGoster($baglanti, 'Satın Alma') || navbarModulGoster($baglanti, 'Sevkiyat & Lojistik') || navbarModulGoster($baglanti, 'Stok Takibi')) { ?>
            <div class="nav-group">
                <div class="nav-group-title">Lojistik</div>
                <?php if (navbarModulGoster($baglanti, 'Satın Alma')) { ?>
                    <a href="satin_alma.php" class="nav-link <?php if ($sayfa == 'satin_alma.php')
                        echo 'active'; ?>">
                        <i class="fas fa-shopping-cart"></i> Satın Alma
                    </a>
                <?php } ?>
                <?php if (navbarModulGoster($baglanti, 'Sevkiyat & Lojistik')) { ?>
                    <a href="depo_sevkiyat.php" class="nav-link <?php if ($sayfa == 'depo_sevkiyat.php')
                        echo 'active'; ?>">
                        <i class="fas fa-boxes-stacked"></i> Depo & Sevk
                    </a>
                <?php } ?>
                <?php if (navbarModulGoster($baglanti, 'Stok Takibi')) { ?>
                    <a href="malzeme_stok.php" class="nav-link <?php if ($sayfa == 'malzeme_stok.php')
                        echo 'active'; ?>">
                        <i class=" fas fa-cubes"></i> Malzeme Stok
                    </a>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if (navbarModulGoster($baglanti, 'İzlenebilirlik') || navbarModulGoster($baglanti, 'Lab Analizleri')) { ?>
            <div class="nav-group">
                <div class="nav-group-title">Kalite</div>
                <?php if (navbarModulGoster($baglanti, 'İzlenebilirlik')) { ?>
                    <a href="izlenebilirlik.php" class="nav-link <?php if ($sayfa == 'izlenebilirlik.php')
                        echo 'active'; ?>">
                        <i class="fas fa-barcode"></i> İzlenebilirlik
                    </a>
                <?php } ?>
                <?php if (navbarModulGoster($baglanti, 'Lab Analizleri')) { ?>
                    <a href="lab_analizleri.php" class="nav-link <?php if ($sayfa == 'lab_analizleri.php')
                        echo 'active'; ?>">
                        <i class="fas fa-flask"></i> Lab Analiz
                    </a>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if (navbarModulGoster($baglanti, 'Bakım & Arıza')) { ?>
            <div class="nav-group">
                <div class="nav-group-title">Bakım</div>
                <a href="bakim.php" class="nav-link <?php if ($sayfa == 'bakim.php')
                    echo 'active'; ?>">
                    <i class="fas fa-tools"></i> Makine Bakım
                </a>
            </div>
        <?php } ?>

        <?php if (navbarModulGoster($baglanti, 'Silo Yönetimi') || navbarModulGoster($baglanti, 'Hammadde Kodlama')) { ?>
            <div class="nav-group">
                <div class="nav-group-title">Tanımlar</div>
                <?php if (navbarModulGoster($baglanti, 'Silo Yönetimi')) { ?>
                    <a href="silo_yonetimi.php" class="nav-link <?php if ($sayfa == 'silo_yonetimi.php')
                        echo 'active'; ?>">
                        <i class="fas fa-database"></i> Silo Yönetimi
                    </a>
                <?php } ?>
                <?php if (navbarModulGoster($baglanti, 'Hammadde Kodlama')) { ?>
                    <a href="hammadde_kodlama.php" class="nav-link <?php if ($sayfa == 'hammadde_kodlama.php')
                        echo 'active'; ?>">
                        <i class="fas fa-tags"></i> Hammadde Kod
                    </a>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($rol_adi === 'Patron') {
            // Bekleyen onay sayısını sidebar için çek (onay_bekleyenler tablosundan)
            $pending_count_sidebar = 0;
            $pending_result_s = @$baglanti->query("SELECT COUNT(*) as cnt FROM onay_bekleyenler WHERE onay_durum = 'bekliyor'");
            if ($pending_result_s) {
                $row_data = $pending_result_s->fetch_assoc();
                $pending_count_sidebar = $row_data['cnt'] ?? 0;
            }
            ?>
            <div class="admin-box">
                <div class="nav-group-title"><i class="fas fa-shield-halved"></i> Yönetici</div>
                <a href="onay_merkezi.php" class="nav-link <?php if ($sayfa == 'onay_merkezi.php')
                    echo 'active'; ?>">
                    <div class="d-flex justify-content-between w-100 align-items-center">
                        <span><i class="fas fa-check-double"></i> Onay Merkezi</span>
                        <?php if ($pending_count_sidebar > 0): ?>
                            <span class="badge bg-danger rounded-pill">
                                <?php echo $pending_count_sidebar; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </a>
                <a href="islem_gecmisi.php" class="nav-link <?php if ($sayfa == 'islem_gecmisi.php')
                    echo 'active'; ?>">
                    <i class="fas fa-history"></i> İşlem Geçmişi
                </a>
                <a href="kullanici_yonetimi.php" class="nav-link <?php if ($sayfa == 'kullanici_yonetimi.php')
                    echo 'active'; ?>">
                    <i class="fas fa-users-gear"></i> Kullanıcılar
                </a>

            </div>

        <?php } ?>
    </div>

    <div class="user-footer">
        <div class="user-info">
            <div class="avatar">
                <?php echo strtoupper(substr($_SESSION["kadi"], 0, 1)); ?>
            </div>
            <div>
                <div class="user-name">
                    <?php echo $_SESSION["kadi"]; ?>
                </div>
                <div class="user-status">Çevrimiçi</div>
            </div>
        </div>
        <a href="cikis.php" class="logout-btn"><i class="fas fa-right-from-bracket"></i> Çıkış Yap</a>
    </div>
</nav>

<!-- MOBİL ÜST -->
<div class="mobile-header">
    <div class="brand"><i class="fas fa-wheat-awn"></i> ÖZBAL UN</div>
    <div class="m-avatar">
        <?php echo strtoupper(substr($_SESSION["kadi"], 0, 1)); ?>
    </div>
</div>

<!-- MOBİL ALT NAV -->
<nav class="mobile-nav">
    <a href="panel.php" class="m-link <?php if ($sayfa == 'panel.php')
        echo 'active'; ?>">
        <i class="fas fa-home"></i> Panel
    </a>
    <?php if (navbarModulGoster($baglanti, 'Üretim Paneli')) { ?>
        <a href="uretim.php" class="m-link <?php if ($sayfa == 'uretim.php')
            echo 'active'; ?>">
            <i class="fas fa-industry"></i> Üretim
        </a>
    <?php } ?>
    <?php if (navbarModulGoster($baglanti, 'Sevkiyat & Lojistik')) { ?>
        <a href="depo_sevkiyat.php" class="m-link <?php if ($sayfa == 'depo_sevkiyat.php')
            echo 'active'; ?>">
            <i class="fas fa-truck"></i> Sevk
        </a>
    <?php } ?>
    <button class="menu-btn" onclick="toggleMenu()">
        <i class="fas fa-bars"></i> Menü
    </button>
    <a href="cikis.php" class="m-link danger">
        <i class="fas fa-sign-out-alt"></i> Çıkış
    </a>
</nav>

<!-- SLIDE MENU -->
<div class="menu-overlay" id="menuOverlay" onclick="toggleMenu()"></div>
<div class="slide-menu" id="slideMenu">
    <div class="menu-handle"></div>
    <div class="menu-grid">
        <a href="panel.php" class="menu-item <?php if ($sayfa == 'panel.php')
            echo 'active'; ?>">
            <i class="fas fa-th-large"></i> Panel
        </a>
        <a href="yikama_uretim.php" class="menu-item <?php if ($sayfa == 'yikama_uretim.php')
            echo 'active'; ?>">
            <i class="fas fa-water"></i> Yıkama
        </a>
        <?php if (navbarModulGoster($baglanti, 'Hammadde Yönetimi')) { ?>
            <a href="hammadde.php" class="menu-item <?php if ($sayfa == 'hammadde.php')
                echo 'active'; ?>">
                <i class="fas fa-truck-loading"></i> Hammadde
            </a>
        <?php } ?>
        <?php if (navbarModulGoster($baglanti, 'Planlama & Takvim')) { ?>
            <a href="planlama.php" class="menu-item <?php if ($sayfa == 'planlama.php')
                echo 'active'; ?>">
                <i class="fas fa-calendar-alt"></i> Planlama
            </a>
        <?php } ?>
        <?php if (navbarModulGoster($baglanti, 'Üretim Paneli')) { ?>
            <a href="uretim.php" class="menu-item <?php if ($sayfa == 'uretim.php')
                echo 'active'; ?>">
                <i class="fas fa-industry"></i> Üretim
            </a>
        <?php } ?>
        <?php if (navbarModulGoster($baglanti, 'Müşteriler')) { ?>
            <a href="sikayetler.php" class="menu-item <?php if ($sayfa == 'sikayetler.php')
                echo 'active'; ?>">
                <i class="fas fa-comment-dots"></i> Şikayetler
            </a>
        <?php } ?>
        <?php if (navbarModulGoster($baglanti, 'Satın Alma')) { ?>
            <a href="satin_alma.php" class="menu-item <?php if ($sayfa == 'satin_alma.php')
                echo 'active'; ?>">
                <i class="fas fa-shopping-cart"></i> Satın Alma
            </a>
        <?php } ?>
        <?php if (navbarModulGoster($baglanti, 'Sevkiyat & Lojistik')) { ?>
            <a href="depo_sevkiyat.php" class="menu-item <?php if ($sayfa == 'depo_sevkiyat.php')
                echo 'active'; ?>">
                <i class="fas fa-boxes-stacked"></i> Depo
            </a>
        <?php } ?>
        <?php if (navbarModulGoster($baglanti, 'Stok Takibi')) { ?>
            <a href="malzeme_stok.php" class="menu-item <?php if ($sayfa == 'malzeme_stok.php')
                echo 'active'; ?>">
                <i class="fas fa-cubes"></i> Malzeme Stok
            </a>
        <?php } ?>
        <?php if (navbarModulGoster($baglanti, 'İzlenebilirlik')) { ?>
            <a href="izlenebilirlik.php" class="menu-item <?php if ($sayfa == 'izlenebilirlik.php')
                echo 'active'; ?>">
                <i class="fas fa-barcode"></i> İzlenebilirlik
            </a>
        <?php } ?>
        <?php if (navbarModulGoster($baglanti, 'Lab Analizleri')) { ?>
            <a href="lab_analizleri.php" class="menu-item <?php if ($sayfa == 'lab_analizleri.php')
                echo 'active'; ?>">
                <i class="fas fa-flask"></i> Lab
            </a>
        <?php } ?>
        <?php if (navbarModulGoster($baglanti, 'Silo Yönetimi')) { ?>
            <a href="silo_yonetimi.php" class="menu-item <?php if ($sayfa == 'silo_yonetimi.php')
                echo 'active'; ?>">
                <i class="fas fa-database"></i> Silo
            </a>
        <?php } ?>
        <?php if (navbarModulGoster($baglanti, 'Hammadde Kodlama')) { ?>
            <a href="hammadde_kodlama.php" class="menu-item <?php if ($sayfa == 'hammadde_kodlama.php')
                echo 'active'; ?>">
                <i class="fas fa-tags"></i> Kodlar
            </a>
        <?php } ?>

        <?php if ($rol_adi === 'Patron') {
            // Bekleyen onay sayısını çek (onay_bekleyenler tablosundan)
            $pending_count = 0;
            $pending_result = @$baglanti->query("SELECT COUNT(*) as cnt FROM onay_bekleyenler WHERE onay_durum = 'bekliyor'");
            if ($pending_result) {
                $pending_count = $pending_result->fetch_assoc()['cnt'];
            }
            ?>
            <a href="onay_merkezi.php" class="menu-item <?php if ($sayfa == 'onay_merkezi.php')
                echo 'active'; ?>" style="position: relative;">
                <i class="fas fa-check-double"></i> Onay
                <?php if ($pending_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                        style="font-size: 0.5rem; padding: 0.25em 0.4em;">
                        <?php echo $pending_count; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="islem_gecmisi.php" class="menu-item <?php if ($sayfa == 'islem_gecmisi.php')
                echo 'active'; ?>">
                <i class="fas fa-history"></i> Geçmiş
            </a>
            <a href="kullanici_yonetimi.php" class="menu-item <?php if ($sayfa == 'kullanici_yonetimi.php')
                echo 'active'; ?>">
                <i class="fas fa-users"></i> Kullanıcı
            </a>

        <?php } ?>
    </div>
</div>

<script>
    function toggleMenu() {
        document.getElementById('menuOverlay').classList.toggle('open');
        document.getElementById('slideMenu').classList.toggle('open');
    }

    // === BİLDİRİM SİSTEMİ ===
    let bildirimAcik = false;
    let sonBildirimSayisi = <?php echo $bildirim_sayisi; ?>;

    function toggleBildirimDropdown() {
        const dropdown = document.getElementById('bildirimDropdown');
        bildirimAcik = !bildirimAcik;
        dropdown.style.display = bildirimAcik ? 'block' : 'none';

        if (bildirimAcik) {
            bildirimleriYukle();
        }
    }

    // Dışarı tıklandığında kapat
    document.addEventListener('click', function (e) {
        const container = document.getElementById('bildirimContainer');
        if (container && !container.contains(e.target) && bildirimAcik) {
            document.getElementById('bildirimDropdown').style.display = 'none';
            bildirimAcik = false;
        }
    });

    // Bildirimleri API'den yükle
    function bildirimleriYukle() {
        fetch('ajax/bildirimler_api.php?action=list&limit=5')
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('bildirimList');
                if (data.bildirimler && data.bildirimler.length > 0) {
                    let html = '';
                    data.bildirimler.forEach(b => {
                        const okunduClass = b.okundu ? 'bildirim-okundu' : 'bildirim-yeni';
                        html += `
                            <a href="${b.link || 'bildirimler.php'}" class="bildirim-item ${okunduClass}" onclick="bildirimOkundu(${b.id})">
                                <div class="bildirim-ikon">
                                    <i class="fas ${b.ikon}"></i>
                                </div>
                                <div class="bildirim-icerik">
                                    <div class="bildirim-baslik">${b.baslik}</div>
                                    <div class="bildirim-zaman">${b.tarih_ago}</div>
                                </div>
                            </a>
                        `;
                    });
                    list.innerHTML = html;
                } else {
                    list.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-bell-slash fa-2x mb-2"></i><br>Bildirim yok</div>';
                }
            })
            .catch(err => {
                console.error('Bildirim yükleme hatası:', err);
            });
    }

    // Tek bildirimi okundu işaretle
    function bildirimOkundu(id) {
        fetch('ajax/bildirimler_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=read&id=' + id
        });
    }

    // Tümünü okundu yap
    function tumunuOkundu() {
        fetch('ajax/bildirimler_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=read_all'
        }).then(() => {
            document.getElementById('bildirimBadge').style.display = 'none';
            document.getElementById('bildirimBadge').innerText = '0';
            bildirimleriYukle();
        });
    }

    // Her 30 saniyede bildirim kontrolü
    function bildirimKontrol() {
        fetch('ajax/bildirimler_api.php?action=count')
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('bildirimBadge');
                if (badge) {
                    if (data.count > 0) {
                        badge.style.display = 'inline';
                        badge.innerText = data.count;

                        // Yeni bildirim geldi - popup göster
                        if (data.count > sonBildirimSayisi) {
                            yeniBildirimPopup(data.count - sonBildirimSayisi);
                        }
                        sonBildirimSayisi = data.count;
                    } else {
                        badge.style.display = 'none';
                        sonBildirimSayisi = 0;
                    }
                }
            })
            .catch(err => console.log('Bildirim kontrol hatası'));
    }

    // Yeni bildirim popup'ı
    function yeniBildirimPopup(adet) {
        // Mevcut popup varsa kaldır
        const mevcut = document.getElementById('bildirimPopup');
        if (mevcut) mevcut.remove();

        const popup = document.createElement('div');
        popup.id = 'bildirimPopup';
        popup.innerHTML = `
            <div class="bildirim-popup">
                <i class="fas fa-bell fa-bounce text-warning me-2"></i>
                <span>${adet} yeni bildiriminiz var!</span>
                <button onclick="this.parentElement.parentElement.remove()" class="btn btn-sm btn-link text-white p-0 ms-3">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        document.body.appendChild(popup);

        // 5 saniye sonra otomatik kapat
        setTimeout(() => {
            const p = document.getElementById('bildirimPopup');
            if (p) p.remove();
        }, 5000);
    }

    // Sayfa yüklendiğinde ve her 30 sn'de kontrol et
    document.addEventListener('DOMContentLoaded', function () {
        bildirimKontrol();
        setInterval(bildirimKontrol, 30000);
    });
</script>

<style>
    /* Bildirim Dropdown Stilleri */
    .bildirim-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        width: 320px;
        background: #1e2129;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
        z-index: 9999;
        overflow: hidden;
        margin-top: 10px;
    }

    .bildirim-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: rgba(255, 255, 255, 0.03);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        color: #fff;
        font-weight: 600;
    }

    .bildirim-header a {
        font-size: 0.75rem;
    }

    .bildirim-list {
        max-height: 300px;
        overflow-y: auto;
    }

    .bildirim-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        text-decoration: none;
        color: #e2e8f0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: background 0.2s;
    }

    .bildirim-item:hover {
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
    }

    .bildirim-yeni {
        background: rgba(245, 166, 35, 0.08);
        border-left: 3px solid var(--gold);
    }

    .bildirim-okundu {
        opacity: 0.7;
    }

    .bildirim-ikon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .bildirim-icerik {
        flex: 1;
        min-width: 0;
    }

    .bildirim-baslik {
        font-size: 0.85rem;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .bildirim-zaman {
        font-size: 0.7rem;
        color: #64748b;
        margin-top: 2px;
    }

    .bildirim-footer {
        padding: 10px 16px;
        background: rgba(255, 255, 255, 0.03);
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        text-align: center;
    }

    .bildirim-footer a {
        color: var(--gold);
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .bildirim-footer a:hover {
        text-decoration: underline;
    }

    /* Popup Stili */
    #bildirimPopup {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 99999;
        animation: slideIn 0.3s ease;
    }

    .bildirim-popup {
        background: linear-gradient(135deg, #1a1d23, #2d3748);
        color: #fff;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(245, 166, 35, 0.3);
        display: flex;
        align-items: center;
        font-weight: 500;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Mobil için bildirim dropdown konumu */
    @media (max-width: 991px) {
        .bildirim-dropdown {
            position: fixed;
            top: 60px;
            right: 10px;
            left: 10px;
            width: auto;
        }
    }
</style>
