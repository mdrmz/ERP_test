<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("baglan.php");
include("helper_functions.php");

// Güvenlik
if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}

// Modül bazlı yetki kontrolü
sayfaErisimKontrol($baglanti);

// POST işlemleri (Ekleme/Düzenleme/Silme vb.) varsa, arka planda yazma yetkisini kontrol et
$yazma_yetkisi = yazmaYetkisiVar($baglanti, 'Hammadde Yönetimi');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$yazma_yetkisi) {
    die("Bu işlem için yazma yetkiniz bulunmamaktadır.");
}

$mesaj = "";
$hata = "";
$aktif_user_id = (int) ($_SESSION['user_id'] ?? 0);
$is_patron = (($_SESSION['rol_adi'] ?? '') === 'Patron') || ((int) ($_SESSION['rol_id'] ?? 0) === 1);

if (!function_exists('hammaddeIzlenebilirlikDurum')) {
    function hammaddeIzlenebilirlikDurum($row)
    {
        $asama = trim((string) ($row['olay_asamasi'] ?? ''));
        $onay_durum = trim((string) ($row['onay_durum_akis'] ?? ($row['onay_durum'] ?? '')));
        $silo_dagitim_var = ((int) ($row['silo_dagitim_var'] ?? 0)) > 0;
        $silo_kalan_kg = (float) ($row['silo_kalan_kg_toplam'] ?? 0);

        $durum_kodu = 'onay_merkezinde';
        $durum_metin = 'Onay Merkezinde';
        $badge_class = 'bg-secondary';
        $icon = 'fa-hourglass-half';

        if (in_array($onay_durum, ['reddedildi', 'satinalma_red'], true) || $asama === 'reddedildi') {
            $durum_kodu = 'reddedildi';
            $durum_metin = 'Reddedildi';
            $badge_class = 'bg-danger';
            $icon = 'fa-ban';
        } elseif ($silo_dagitim_var) {
            if ($silo_kalan_kg > 0.01) {
                $durum_kodu = 'siloda';
                $durum_metin = 'Siloda';
                $badge_class = 'bg-success';
                $icon = 'fa-warehouse';
            } else {
                $durum_kodu = 'tamamlandi';
                $durum_metin = 'Tamamlandı';
                $badge_class = 'bg-dark';
                $icon = 'fa-check-double';
            }
        } elseif ($asama === 'tamamlandi' && $onay_durum === 'onaylandi') {
            $durum_kodu = 'silo_aktarma_bekleniyor';
            $durum_metin = 'Silo Aktarma Bekleniyor';
            $badge_class = 'bg-warning text-dark';
            $icon = 'fa-right-left';
        } elseif (in_array($asama, ['satina_bekliyor', 'onaylandi'], true)) {
            $durum_kodu = 'satin_alimda';
            $durum_metin = 'Satın Alımda';
            $badge_class = 'bg-primary';
            $icon = 'fa-cart-shopping';
        }

        return [
            'kod' => $durum_kodu,
            'etiket' => $durum_metin,
            'badge_class' => $badge_class,
            'icon' => $icon,
            'can_transfer' => ($durum_kodu === 'silo_aktarma_bekleniyor'),
            'is_locked' => in_array($durum_kodu, ['siloda', 'tamamlandi'], true)
        ];
    }
}

if (!function_exists('hammaddeYogunlukHesapla')) {
    function hammaddeYogunlukHesapla($hektolitre, $lab_hektolitre, $varsayilan_yogunluk)
    {
        $hektolitre = (float) $hektolitre;
        $lab_hektolitre = (float) $lab_hektolitre;
        $varsayilan_yogunluk = (float) $varsayilan_yogunluk;

        if ($hektolitre > 0) {
            return $hektolitre * 10;
        }
        if ($lab_hektolitre > 0) {
            return $lab_hektolitre * 10;
        }
        if ($varsayilan_yogunluk > 0) {
            return $varsayilan_yogunluk;
        }
        return 780.0;
    }
}

$duzeltme_tablo_var = false;
$duzeltme_tablo_kontrol = @$baglanti->query("SHOW TABLES LIKE 'silo_duzeltme_talepleri'");
if ($duzeltme_tablo_kontrol && $duzeltme_tablo_kontrol->num_rows > 0) {
    $duzeltme_tablo_var = true;
}

// YENİ HAMMADDE EKLEME
if (isset($_POST["hammadde_ekle"])) {
    $kod = strtoupper(trim($_POST["yeni_hammadde_kodu"]));
    $ad = trim($_POST["yeni_hammadde_ad"]);
    $yogunluk = $_POST["yeni_yogunluk"] ?: 780;
    $aciklama = $_POST["yeni_aciklama"];

    // Duplicate kontrolü
    $kontrol = $baglanti->query("SELECT id FROM hammaddeler WHERE hammadde_kodu = '$kod'");
    if ($kontrol && $kontrol->num_rows > 0) {
        $hata = "❌ Bu hammadde kodu zaten mevcut: $kod";
    } else {
        $sql = "INSERT INTO hammaddeler (hammadde_kodu, ad, yogunluk_kg_m3, aciklama, aktif) 
                VALUES ('$kod', '$ad', $yogunluk, '$aciklama', 1)";
        if ($baglanti->query($sql)) {
            // Redirect to prevent double submission
            header("Location: hammadde.php?msg=ok&kod=" . urlencode($kod));
            exit;
        } else {
            $hata = "Hata: " . $baglanti->error;
        }
    }
}

// Success message from redirect
if (isset($_GET['msg']) && $_GET['msg'] == 'ok') {
    $mesaj = "✅ Yeni hammadde başarıyla eklendi: " . htmlspecialchars($_GET['kod'] ?? '');
}
if (isset($_GET['giris']) && $_GET['giris'] == 'ok') {
    $mesaj = "✅ Başarılı! " . htmlspecialchars($_GET['plaka'] ?? '') . " plakalı araç kaydedildi.";
}
if (isset($_GET['kantar']) && $_GET['kantar'] == 'ok') {
    $mesaj = "✅ Kantar bilgisi güncellendi: " . htmlspecialchars($_GET['kg'] ?? '') . " KG";
}

// KANTAR GÜNCELLEMESİ (Düzenleme Modalından)
// SILO DUZELTME TALEBI - OLUSTUR
if (isset($_POST['silo_duzeltme_talep_olustur'])) {
    $giris_id = (int) ($_POST['giris_id'] ?? 0);
    $talep_nedeni = trim((string) ($_POST['talep_nedeni'] ?? ''));

    if (!$duzeltme_tablo_var) {
        $hata = "Silo duzeltme talep tablosu bulunamadi. SQL guncellemesi gerekli.";
    } elseif ($giris_id <= 0) {
        $hata = "Gecersiz hammadde girisi.";
    } elseif ($talep_nedeni === '') {
        $hata = "Talep nedeni bos olamaz.";
    } else {
        $detay_sql = "
            SELECT hg.id, hg.parti_no, hg.arac_plaka, h.hammadde_kodu,
                   COALESCE(ssd_ozet.kayit_sayisi, 0) AS silo_kayit_sayisi
            FROM hammadde_girisleri hg
            LEFT JOIN hammaddeler h ON h.id = hg.hammadde_id
            LEFT JOIN (
                SELECT parti_kodu, COUNT(*) AS kayit_sayisi
                FROM silo_stok_detay
                GROUP BY parti_kodu
            ) ssd_ozet ON ssd_ozet.parti_kodu = hg.parti_no
            WHERE hg.id = $giris_id
            LIMIT 1
        ";
        $detay_res = $baglanti->query($detay_sql);
        $detay = $detay_res ? $detay_res->fetch_assoc() : null;

        if (!$detay) {
            $hata = "Kayit bulunamadi.";
        } elseif ((int) ($detay['silo_kayit_sayisi'] ?? 0) <= 0) {
            $hata = "Bu kayıt için silo aktarımı tamamlanmamış. Talep açılamaz.";
        } else {
            $acik_kontrol = $baglanti->query("SELECT id FROM silo_duzeltme_talepleri WHERE hammadde_giris_id = $giris_id AND durum IN ('bekliyor', 'onaylandi') LIMIT 1");
            if ($acik_kontrol && $acik_kontrol->num_rows > 0) {
                $hata = "Bu kayit icin acik bir duzeltme talebi zaten var.";
            } else {
                $parti_no_esc = $baglanti->real_escape_string((string) ($detay['parti_no'] ?? ''));
                $talep_nedeni_esc = $baglanti->real_escape_string($talep_nedeni);
                $insert_sql = "INSERT INTO silo_duzeltme_talepleri (hammadde_giris_id, parti_no, talep_nedeni, talep_eden_user_id, durum, created_at, updated_at) 
                               VALUES ($giris_id, '$parti_no_esc', '$talep_nedeni_esc', $aktif_user_id, 'bekliyor', NOW(), NOW())";
                if ($baglanti->query($insert_sql)) {
                    $talep_id = (int) $baglanti->insert_id;
                    $plaka = (string) ($detay['arac_plaka'] ?? '-');
                    $hammadde_kodu = (string) ($detay['hammadde_kodu'] ?? '-');

                    if (function_exists('bildirimOlustur')) {
                        $baslik = "Silo Duzeltme Talebi: {$parti_no_esc}";
                        $aciklama = "Parti: {$parti_no_esc} | Plaka: {$plaka} | Hammadde: {$hammadde_kodu} | Talep Eden User ID: {$aktif_user_id}";
                        bildirimOlustur(
                            $baglanti,
                            'silo_duzeltme_talebi',
                            $baslik,
                            $aciklama,
                            1,
                            null,
                            'silo_duzeltme_talepleri',
                            $talep_id,
                            'hammadde.php?tab=duzeltme_talepleri'
                        );
                    }

                    $mesaj = "Silo düzeltme talebi oluşturuldu.";
                } else {
                    $hata = "Talep kaydedilemedi: " . $baglanti->error;
                }
            }
        }
    }
}

// SILO DUZELTME TALEBI - KARAR (PATRON)
if (isset($_POST['silo_duzeltme_talep_karar'])) {
    $talep_id = (int) ($_POST['talep_id'] ?? 0);
    $karar = trim((string) ($_POST['karar'] ?? ''));
    $karar_notu = trim((string) ($_POST['karar_notu'] ?? ''));

    if (!$duzeltme_tablo_var) {
        $hata = "Silo duzeltme talep tablosu bulunamadi.";
    } elseif (!$is_patron) {
        $hata = "Bu islem sadece Patron tarafindan yapilabilir.";
    } elseif ($talep_id <= 0 || !in_array($karar, ['onayla', 'reddet'], true)) {
        $hata = "Gecersiz talep karari.";
    } else {
        $talep_res = $baglanti->query("
            SELECT sdt.*, hg.arac_plaka, h.hammadde_kodu
            FROM silo_duzeltme_talepleri sdt
            LEFT JOIN hammadde_girisleri hg ON hg.id = sdt.hammadde_giris_id
            LEFT JOIN hammaddeler h ON h.id = hg.hammadde_id
            WHERE sdt.id = $talep_id
            LIMIT 1
        ");
        $talep = $talep_res ? $talep_res->fetch_assoc() : null;

        if (!$talep) {
            $hata = "Talep bulunamadi.";
        } elseif (($talep['durum'] ?? '') !== 'bekliyor') {
            $hata = "Bu talep icin karar verilemez. Mevcut durum: " . ($talep['durum'] ?? '-');
        } else {
            $yeni_durum = ($karar === 'onayla') ? 'onaylandi' : 'reddedildi';
            $karar_notu_esc = $baglanti->real_escape_string($karar_notu);
            $update_sql = "UPDATE silo_duzeltme_talepleri 
                           SET durum = '$yeni_durum',
                               karar_veren_user_id = $aktif_user_id,
                               karar_notu = '$karar_notu_esc',
                               onay_tarihi = NOW(),
                               updated_at = NOW()
                           WHERE id = $talep_id";

            if ($baglanti->query($update_sql)) {
                if (function_exists('bildirimOlustur')) {
                    $parti_no = (string) ($talep['parti_no'] ?? '-');
                    $plaka = (string) ($talep['arac_plaka'] ?? '-');
                    $hammadde_kodu = (string) ($talep['hammadde_kodu'] ?? '-');
                    $hedef_user = (int) ($talep['talep_eden_user_id'] ?? 0);
                    $tip = ($yeni_durum === 'onaylandi') ? 'silo_duzeltme_onay' : 'silo_duzeltme_red';
                    $baslik = ($yeni_durum === 'onaylandi')
                        ? "Silo Duzeltme Talebiniz Onaylandi: {$parti_no}"
                        : "Silo Duzeltme Talebiniz Reddedildi: {$parti_no}";
                    $aciklama = "Parti: {$parti_no} | Plaka: {$plaka} | Hammadde: {$hammadde_kodu}" . ($karar_notu !== '' ? " | Not: {$karar_notu}" : '');
                    if ($hedef_user > 0) {
                        bildirimOlustur(
                            $baglanti,
                            $tip,
                            $baslik,
                            $aciklama,
                            null,
                            $hedef_user,
                            'silo_duzeltme_talepleri',
                            $talep_id,
                            'hammadde.php?tab=duzeltme_talepleri'
                        );
                    }
                }

                $mesaj = ($yeni_durum === 'onaylandi') ? "Talep onaylandı." : "Talep reddedildi.";
            } else {
                $hata = "Talep karari kaydedilemedi: " . $baglanti->error;
            }
        }
    }
}

// SILO DUZELTME TALEBI - UYGULA adımı kaldırıldı
if (isset($_POST['silo_duzeltme_talep_uygula'])) {
    $hata = "Bu adım kaldırıldı. Talep onaylandıktan sonra düzeltmeyi İzlenebilirlik sekmesinden yapın.";
}

if (isset($_POST["kantar_guncelle"])) {
    $giris_id = (int) ($_POST["giris_id"] ?? 0);
    $patron_duzeltme = ((int) ($_POST["patron_duzeltme"] ?? 0) === 1) && $is_patron;
    $onayli_duzeltme_talep_id = 0;

    $dagitim_silo_ids = $_POST['dagitim_silo_id'] ?? [];
    $dagitim_kgs = $_POST['dagitim_kg'] ?? [];
    $eski_stoklari_temizle = false;
    $eski_stok_ozet = [];

    if ($giris_id <= 0) {
        $hata = "Geçersiz giriş kaydı.";
    } else {
        $mevcut_sql = "
            SELECT hg.*, h.yogunluk_kg_m3, h.hammadde_kodu, h.ad as hammadde_adi, hka.kantar_net_kg, hka.asama as akis_asama, hka.onay_durum as akis_onay_durum,
                   (SELECT la.hektolitre FROM lab_analizleri la WHERE la.hammadde_giris_id = hg.id ORDER BY la.id DESC LIMIT 1) as lab_hektolitre
            FROM hammadde_girisleri hg
            LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
            LEFT JOIN hammadde_kabul_akisi hka ON hka.hammadde_giris_id = hg.id
            WHERE hg.id = $giris_id
            ORDER BY hka.id DESC
            LIMIT 1
        ";
        $mevcut = $baglanti->query($mevcut_sql);
        $mevcut = $mevcut ? $mevcut->fetch_assoc() : null;

        if (!$mevcut) {
            $hata = "Hammadde girişi bulunamadı.";
        } else {
            $referans_kg = (float) ($mevcut["kantar_net_kg"] ?? 0);
            $hektolitre_degeri = (float) ($mevcut["hektolitre"] ?? 0);
            if ($hektolitre_degeri <= 0) {
                $hektolitre_degeri = (float) ($mevcut["lab_hektolitre"] ?? 0);
            }

            // Hektolitre (kg/hl) -> Yogunluk (kg/m3): 1 hl = 0.1 m3
            if ($hektolitre_degeri > 0) {
                $yogunluk = $hektolitre_degeri * 10;
            } else {
                $yogunluk = (float) ($mevcut["yogunluk_kg_m3"] ?? 780);
            }

            $hammadde_kodu = trim((string) ($mevcut["hammadde_kodu"] ?? ''));
            $hammadde_adi = $mevcut["hammadde_adi"] ?? '';

            if ($yogunluk <= 0) {
                $yogunluk = 780;
            }

            if ($referans_kg <= 0) {
                $hata = "Satınalma kantar değeri bulunamadı. Önce satınalma tarafında net KG onaylanmalıdır.";
            }

        }
    }

    if (empty($hata)) {
        $dagitimlar = [];
        $toplam_dagitim_kg = 0.0;
        $ilk_silo_id = 0;
        $max_satir = max(count($dagitim_silo_ids), count($dagitim_kgs));

        for ($i = 0; $i < $max_satir; $i++) {
            $silo_raw = trim((string) ($dagitim_silo_ids[$i] ?? ''));
            $kg_raw = trim((string) ($dagitim_kgs[$i] ?? ''));

            if ($silo_raw === '' && $kg_raw === '') {
                continue;
            }

            if ($silo_raw === '' || $kg_raw === '') {
                $hata = "Her dağıtım satırında hem silo hem miktar girilmelidir.";
                break;
            }

            $silo_id = (int) $silo_raw;
            $miktar_kg = (float) $kg_raw;
            if ($silo_id <= 0 || $miktar_kg <= 0) {
                $hata = "Dağıtım satırlarındaki silo ve KG değerleri geçerli olmalıdır.";
                break;
            }

            if ($ilk_silo_id === 0) {
                $ilk_silo_id = $silo_id;
            }

            if (!isset($dagitimlar[$silo_id])) {
                $dagitimlar[$silo_id] = 0.0;
            }
            $dagitimlar[$silo_id] += $miktar_kg;
            $toplam_dagitim_kg += $miktar_kg;
        }

        if (empty($hata) && count($dagitimlar) === 0) {
            $hata = "En az bir silo dağıtımı girmelisiniz.";
        }

        if (empty($hata) && abs($toplam_dagitim_kg - $referans_kg) > 0.01) {
            $hata = "Toplam dağıtım (" . number_format((float)$toplam_dagitim_kg, 2, ',', '.') . " KG), satınalma kantar değeriyle (" . number_format((float)$referans_kg, 2, ',', '.') . " KG) birebir aynı olmalıdır.";
        }
    }

    if (empty($hata)) {
        $parti_kodu_esc = $baglanti->real_escape_string($mevcut['parti_no']);
        $kilit_kontrol = $baglanti->query("SELECT COUNT(*) AS adet FROM silo_stok_detay WHERE parti_kodu = '$parti_kodu_esc'");
        $kilit_adet = 0;
        if ($kilit_kontrol && ($kilit_row = $kilit_kontrol->fetch_assoc())) {
            $kilit_adet = (int) ($kilit_row['adet'] ?? 0);
        }

        if ($kilit_adet > 0) {
            $duzeltme_yetkili = false;
            if ($patron_duzeltme) {
                $duzeltme_yetkili = true;
            } elseif ($duzeltme_tablo_var) {
                $talep_kontrol_sql = "SELECT id FROM silo_duzeltme_talepleri 
                                      WHERE hammadde_giris_id = $giris_id 
                                        AND talep_eden_user_id = $aktif_user_id 
                                        AND durum = 'onaylandi'
                                      ORDER BY id DESC LIMIT 1";
                $talep_kontrol_res = $baglanti->query($talep_kontrol_sql);
                if ($talep_kontrol_res && ($talep_row = $talep_kontrol_res->fetch_assoc())) {
                    $onayli_duzeltme_talep_id = (int) ($talep_row['id'] ?? 0);
                    if ($onayli_duzeltme_talep_id > 0) {
                        $duzeltme_yetkili = true;
                    }
                }
            }

            if (!$duzeltme_yetkili) {
                $hata = "Bu parti için daha önce silo dağıtımı yapılmış. Aynı parti ikinci kez dağıtılamaz.";
            } else {
                $cikis_kontrol = $baglanti->query("SELECT COUNT(*) AS adet FROM uretim_silo_cikis_log WHERE kaynak_parti_kodu = '$parti_kodu_esc'");
                $cikis_adet = 0;
                if ($cikis_kontrol && ($cikis_row = $cikis_kontrol->fetch_assoc())) {
                    $cikis_adet = (int) ($cikis_row['adet'] ?? 0);
                }

                if ($cikis_adet > 0) {
                    $hata = "Bu parti için üretim çıkışı başladığı için düzeltme yapılamaz.";
                } else {
                    $eski_stok_res = $baglanti->query("SELECT silo_id, SUM(giren_miktar_kg) AS toplam_giren_kg FROM silo_stok_detay WHERE parti_kodu = '$parti_kodu_esc' GROUP BY silo_id");
                    if ($eski_stok_res) {
                        while ($eski = $eski_stok_res->fetch_assoc()) {
                            $eski_stok_ozet[] = [
                                'silo_id' => (int) ($eski['silo_id'] ?? 0),
                                'toplam_giren_kg' => (float) ($eski['toplam_giren_kg'] ?? 0),
                            ];
                        }
                    }
                    $eski_stoklari_temizle = true;
                }
            }
        } else {
            if ($patron_duzeltme) {
                $hata = "Patron düzeltmesi için mevcut bir silo dağıtımı bulunmalıdır.";
            } else {
                $akis_asama = strtolower(trim((string) ($mevcut['akis_asama'] ?? '')));
                $akis_onay = strtolower(trim((string) ($mevcut['akis_onay_durum'] ?? '')));
                if ($akis_asama !== 'tamamlandi' || $akis_onay !== 'onaylandi') {
                    $hata = "Bu kayıt henüz silo aktarma bekleniyor aşamasında değil.";
                }
            }
        }
    }

    if (empty($hata)) {
        $silo_ids = array_map('intval', array_keys($dagitimlar));
        $silo_id_list = implode(',', $silo_ids);
        $silo_kayitlari = $baglanti->query("SELECT id, silo_adi, kapasite_m3, doluluk_m3, izin_verilen_hammadde_kodlari FROM silolar WHERE id IN ($silo_id_list)");

        if (!$silo_kayitlari) {
            $hata = "Silo kayıtları alınamadı: " . $baglanti->error;
        } else {
            $silo_map = [];
            while ($s = $silo_kayitlari->fetch_assoc()) {
                $silo_map[(int) $s['id']] = $s;
            }

            foreach ($dagitimlar as $silo_id => $miktar_kg) {
                if (!isset($silo_map[$silo_id])) {
                    $hata = "Seçilen silo bulunamadı (ID: $silo_id).";
                    break;
                }

                $silo = $silo_map[$silo_id];
                $kapasite_m3 = (float) ($silo['kapasite_m3'] ?? 0);
                $doluluk_m3 = (float) ($silo['doluluk_m3'] ?? 0);
                $bos_m3 = max(0, $kapasite_m3 - $doluluk_m3);
                $max_kg = $bos_m3 * $yogunluk;

                if (($miktar_kg - $max_kg) > 0.01) {
                    $hata = "{$silo['silo_adi']} silosunda yeterli boşluk yok. Maksimum " . number_format((float)$max_kg, 2, ',', '.') . " KG girebilirsiniz.";
                    break;
                }

                $izinli_raw = $silo['izin_verilen_hammadde_kodlari'];
                if (!empty($izinli_raw)) {
                    $izinli_list = json_decode($izinli_raw, true);
                    if (is_array($izinli_list) && count($izinli_list) > 0 && !in_array($hammadde_kodu, $izinli_list, true)) {
                        $hata = "{$silo['silo_adi']} silosuna {$hammadde_kodu} kodlu hammadde girişi izinli değil.";
                        break;
                    }
                }
            }
        }
    }

    if (empty($hata)) {
        $guncel_m3 = $referans_kg / $yogunluk;
        $parti_kodu = $baglanti->real_escape_string($mevcut['parti_no']);
        $hammadde_turu = $baglanti->real_escape_string($hammadde_adi);
        $islem_hatasi = "";

        $baglanti->begin_transaction();

        $sql_update = "UPDATE hammadde_girisleri SET miktar_kg = $referans_kg, giris_m3 = $guncel_m3, silo_id = $ilk_silo_id";
        if ($hektolitre_degeri > 0) {
            $sql_update .= ", hektolitre = $hektolitre_degeri";
        }
        $sql_update .= " WHERE id = $giris_id";
        if (!$baglanti->query($sql_update)) {
            $islem_hatasi = "Hammadde girişi güncellenemedi: " . $baglanti->error;
        }

        if (empty($islem_hatasi) && $eski_stoklari_temizle) {
            foreach ($eski_stok_ozet as $eski_stok) {
                $eski_silo_id = (int) ($eski_stok['silo_id'] ?? 0);
                $eski_kg = (float) ($eski_stok['toplam_giren_kg'] ?? 0);
                if ($eski_silo_id <= 0 || $eski_kg <= 0) {
                    continue;
                }

                $azaltim_m3 = $eski_kg / $yogunluk;
                $sql_eski_doluluk = "UPDATE silolar SET doluluk_m3 = GREATEST(0, doluluk_m3 - $azaltim_m3) WHERE id = $eski_silo_id";
                if (!$baglanti->query($sql_eski_doluluk)) {
                    $islem_hatasi = "Eski silo doluluğu geri alınamadı: " . $baglanti->error;
                    break;
                }
            }

            if (empty($islem_hatasi) && !$baglanti->query("DELETE FROM silo_stok_detay WHERE parti_kodu = '$parti_kodu'")) {
                $islem_hatasi = "Eski silo stok detayları temizlenemedi: " . $baglanti->error;
            }
        }

        if (empty($islem_hatasi)) {
            foreach ($dagitimlar as $s_id => $a_kg) {
                $fifo_sql = "INSERT INTO silo_stok_detay (silo_id, parti_kodu, hammadde_turu, giren_miktar_kg, kalan_miktar_kg, giris_tarihi, durum) VALUES ($s_id, '$parti_kodu', '$hammadde_turu', $a_kg, $a_kg, NOW(), 'aktif')";
                if (!$baglanti->query($fifo_sql)) {
                    $islem_hatasi = "FIFO kaydı oluşturulamadı: " . $baglanti->error;
                    break;
                }

                $aktarilan_m3 = $a_kg / $yogunluk;
                if (!$baglanti->query("UPDATE silolar SET doluluk_m3 = doluluk_m3 + $aktarilan_m3 WHERE id = $s_id")) {
                    $islem_hatasi = "Silo doluluğu güncellenemedi: " . $baglanti->error;
                    break;
                }
            }
        }

        if (empty($islem_hatasi) && $onayli_duzeltme_talep_id > 0 && $duzeltme_tablo_var) {
            $talep_kapat_sql = "UPDATE silo_duzeltme_talepleri 
                                SET durum = 'uygulandi', uygulama_tarihi = NOW(), updated_at = NOW()
                                WHERE id = $onayli_duzeltme_talep_id";
            if (!$baglanti->query($talep_kapat_sql)) {
                $islem_hatasi = "Düzeltme talebi durumu güncellenemedi: " . $baglanti->error;
            }
        }

        if (empty($islem_hatasi)) {
            $baglanti->commit();
            header("Location: hammadde.php?kantar=ok&kg=" . number_format((float)$referans_kg, 0, ',', '.'));
            exit;
        }

        $baglanti->rollback();
        $hata = $islem_hatasi;
    }
}

// FORM GÖNDERİLDİĞİNDE (KAMYON GİRİŞİ)
if (isset($_POST["giris_yap"])) {
    $hammadde_id = $_POST["hammadde_id"];
    $plaka = mysqli_real_escape_string($baglanti, $_POST["plaka"]);
    $tedarikci = mysqli_real_escape_string($baglanti, $_POST["tedarikci"]); // YENİ
    $parti_no = mysqli_real_escape_string($baglanti, $_POST["parti_no"]); // YENİ
    $kg = !empty($_POST["kg"]) ? (float) $_POST["kg"] : 0;

    // Lab Verileri - Varsayılan olarak NULL (Lab Analizleri sayfasından girilecek)
    $nem = null;
    $protein = null;
    $nisasta = null;
    $sertlik = null;
    $hektolitre = null;

    // 1. Hammadde Bilgilerini Çek
    $urun_bilgi_res = $baglanti->query("SELECT * FROM hammaddeler WHERE id=$hammadde_id");
    $urun_bilgi = $urun_bilgi_res ? $urun_bilgi_res->fetch_assoc() : null;

    if (!$urun_bilgi) {
        $hata = "HATA: Ürün bilgisi bulunamadı.";
    } else {
        // DUPLICATE KONTROL: Parti numarası zaten var mı?
        $parti_kontrol = $baglanti->query("SELECT id FROM hammadde_girisleri WHERE parti_no = '$parti_no'");
        if ($parti_kontrol && $parti_kontrol->num_rows > 0) {
            $hata = "❌ Bu parti numarası zaten kullanılmış: <strong>$parti_no</strong><br>Lütfen farklı bir parti numarası girin.";
        } else {
            // HESAPLAMA: kg ve Varsayılan Yoğunluk'tan m3 bulma
            // Hektolitre değeri lab analizinden sonra girilecek, şimdilik hammadde tablosundaki varsayılan yoğunluk kullanılıyor
            $yogunluk_kg_m3 = !empty($urun_bilgi["yogunluk_kg_m3"]) ? (float) $urun_bilgi["yogunluk_kg_m3"] : 780;
            if ($yogunluk_kg_m3 <= 0)
                $yogunluk_kg_m3 = 780; // Sıfıra bölünme hatasını (Division by zero) engellemek için
            $girilen_m3 = ($kg > 0) ? ($kg / $yogunluk_kg_m3) : 0;

            // 4. KAYIT (Silo bilgisi daha sonra kantar/planlama aşamasında girilecek)
            // analiz_yapildi = 0 (beklemede), 1 (tamamlandı)
            $sql_kayit = "INSERT INTO hammadde_girisleri (silo_id, hammadde_id, arac_plaka, parti_no, tedarikci, miktar_kg, hektolitre, giris_m3, nem, protein, nisasta, sertlik, personel, analiz_yapildi) 
                          VALUES (NULL, $hammadde_id, '$plaka', '$parti_no', '$tedarikci', $kg, NULL, $girilen_m3, 0, 0, 0, 0, '{$_SESSION["kadi"]}', 0)";

            // Try-catch ile hataları yakala
            try {
                // Önce INSERT yap ve ID al
                if ($baglanti->query($sql_kayit)) {
                    $yeni_giris_id = $baglanti->insert_id;

                    // === BİLDİRİM SİSTEMİ ENTEGRASYONU ===
                    // Akış kaydı oluştur (tablo varsa)
                    if (function_exists('akisOlustur') && $yeni_giris_id > 0) {
                        // Tablo var mı kontrol et
                        $tablo_kontrol = @$baglanti->query("SHOW TABLES LIKE 'hammadde_kabul_akisi'");
                        if ($tablo_kontrol && $tablo_kontrol->num_rows > 0) {
                            $akis_id = akisOlustur($baglanti, $yeni_giris_id);
                        }
                    }

                    // Lab Sorumlusuna bildirim gönder (rol_id = 5 Lab Sorumlusu varsayımı)
                    if (function_exists('bildirimOlustur') && $yeni_giris_id > 0) {
                        $tablo_kontrol2 = @$baglanti->query("SHOW TABLES LIKE 'bildirimler'");
                        if ($tablo_kontrol2 && $tablo_kontrol2->num_rows > 0) {
                            $lab_rol_id = 5; // Lab Sorumlusu rol ID - ayarlanabilir
                            bildirimOlustur(
                                $baglanti,
                                'arac_geldi',
                                "Yeni Araç Geldi: $plaka",
                                "Tedarikçi: $tedarikci | Hammadde: {$urun_bilgi['ad']} | " . number_format((float)$kg, 0, ',', '.') . " kg",
                                $lab_rol_id,
                                null,
                                'hammadde_girisleri',
                                $yeni_giris_id,
                                'lab_analizleri.php'
                            );

                            // Patron'a da bildirim gönder (rol_id = 1)
                            bildirimOlustur(
                                $baglanti,
                                'arac_geldi',
                                "Yeni Araç Geldi: $plaka",
                                "Tedarikçi: $tedarikci | Hammadde: {$urun_bilgi['ad']} | " . number_format((float)$kg, 0, ',', '.') . " kg",
                                1, // Patron rol_id
                                null,
                                'hammadde_girisleri',
                                $yeni_giris_id,
                                'hammadde.php'
                            );
                        }
                    }
                    // === BİLDİRİM SİSTEMİ SONU ===

                    // === SYSTEM LOG KAYDI ===
                    systemLogKaydet(
                        $baglanti,
                        'INSERT',
                        'Hammadde Kabul',
                        "Yeni araç girişi: $plaka | Tedarikçi: $tedarikci | Hammadde: {$urun_bilgi['ad']} | " . number_format((float)$kg, 0, ',', '.') . " kg"
                    );

                    // Redirect to prevent double submission
                    header("Location: hammadde.php?giris=ok&plaka=" . urlencode($plaka) . "&m3=" . number_format((float)$girilen_m3, 2));
                    exit;
                } else {
                    $hata = "Kayıt hatası: " . $baglanti->error;
                }
            } catch (mysqli_sql_exception $e) {
                // Duplicate key hatası mı?
                if ($e->getCode() == 1062) {
                    $hata = "❌ Bu parti numarası zaten kullanılmış: <strong>$parti_no</strong><br>Lütfen farklı bir parti numarası girin.";
                } else {
                    $hata = "❌ Veritabanı hatası: " . $e->getMessage();
                }
            }
        }
    }
}



// LİSTELERİ ÇEK
$silolar = $baglanti->query("SELECT * FROM silolar");
$hammaddeler = $baglanti->query("SELECT * FROM hammaddeler");
$silo_option_html = "";
if ($silolar) {
    $silolar->data_seek(0);
    while ($s = $silolar->fetch_assoc()) {
        $s_id = (int) $s['id'];
        $silo_adi = htmlspecialchars($s['silo_adi'] ?? '', ENT_QUOTES, 'UTF-8');
        $kapasite_m3 = (float) ($s['kapasite_m3'] ?? 0);
        $doluluk_m3 = (float) ($s['doluluk_m3'] ?? 0);
        $bos_m3 = max(0, $kapasite_m3 - $doluluk_m3);
        $bos_m3_text = number_format((float)$bos_m3, 2, ',', '.');
        $izinli_attr = htmlspecialchars((string) ($s['izin_verilen_hammadde_kodlari'] ?? ''), ENT_QUOTES, 'UTF-8');
        $disabled = $bos_m3 <= 0 ? "disabled" : "";
        $base_label = $silo_adi;
        $label = $base_label . " (Boş: {$bos_m3_text} m³)";

        $silo_option_html .= "<option value='{$s_id}' data-bos-m3='{$bos_m3}' data-izinli='{$izinli_attr}' data-base-label='{$base_label}' {$disabled}>{$label}</option>";
    }
    $silolar->data_seek(0);
}

// Filtre Değişkenleri
$filtre_baslangic = $_GET['f_baslangic'] ?? '';
$filtre_bitis = $_GET['f_bitis'] ?? '';
$filtre_hammadde = $_GET['f_hammadde'] ?? '';
$filtre_tedarikci = $_GET['f_tedarikci'] ?? '';
$filtre_plaka = $_GET['f_plaka'] ?? '';
$aktif_tab = $_GET['tab'] ?? 'izlenebilirlik';

// Filtre SQL eklentileri
$sql_filtre = "WHERE 1=1";

if (!empty($filtre_baslangic)) {
    $baslangic_date = $baglanti->real_escape_string($filtre_baslangic) . ' 00:00:00';
    $sql_filtre .= " AND hg.tarih >= '$baslangic_date'";
}
if (!empty($filtre_bitis)) {
    $bitis_date = $baglanti->real_escape_string($filtre_bitis) . ' 23:59:59';
    $sql_filtre .= " AND hg.tarih <= '$bitis_date'";
}
if (!empty($filtre_hammadde)) {
    $sql_filtre .= " AND hg.hammadde_id = " . (int) $filtre_hammadde;
}
if (!empty($filtre_tedarikci)) {
    $sql_filtre .= " AND hg.tedarikci LIKE '%" . $baglanti->real_escape_string($filtre_tedarikci) . "%'";
}
if (!empty($filtre_plaka)) {
    $sql_filtre .= " AND hg.arac_plaka LIKE '%" . $baglanti->real_escape_string($filtre_plaka) . "%'";
}

$duzeltme_select_gecmis = ", 0 AS duzeltme_onayli_talep_id";
$duzeltme_join_gecmis = "";
if ($duzeltme_tablo_var) {
    $duzeltme_select_gecmis = ", COALESCE(sdt_ozet.onayli_talep_id_bana, 0) AS duzeltme_onayli_talep_id";
    $duzeltme_join_gecmis = "LEFT JOIN (
                    SELECT hammadde_giris_id,
                           MAX(CASE WHEN durum = 'onaylandi' AND talep_eden_user_id = $aktif_user_id THEN id ELSE 0 END) AS onayli_talep_id_bana
                    FROM silo_duzeltme_talepleri
                    GROUP BY hammadde_giris_id
               ) sdt_ozet ON sdt_ozet.hammadde_giris_id = hg.id";
}

$sql_gecmis = "SELECT 
                hg.*,
                s.silo_adi,
                h.ad as urun_adi,
                h.hammadde_kodu,
                h.yogunluk_kg_m3 as hammadde_yogunluk,
                hg.giris_m3 as hesaplanan_m3,
                la.hektolitre as lab_hektolitre,
                la.nem as lab_nem,
                la.protein as lab_protein,
                la.nisasta as lab_nisasta,
                la.sertlik as lab_sertlik,
                hka.asama as olay_asamasi,
                hka.onay_durum as onay_durum_akis,
                hka.kantar_net_kg as referans_kantar_kg,
                COALESCE(ssd_ozet.dagitim_kayit_sayisi, 0) as silo_dagitim_var,
                COALESCE(ssd_ozet.toplam_kalan_kg, 0) as silo_kalan_kg_toplam,
                COALESCE(usc_ozet.cikis_log_sayisi, 0) as uretim_cikis_sayisi
                $duzeltme_select_gecmis
               FROM hammadde_girisleri hg 
               LEFT JOIN silolar s ON hg.silo_id = s.id 
               LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id 
               LEFT JOIN (
                    SELECT la1.*
                    FROM lab_analizleri la1
                    INNER JOIN (
                        SELECT hammadde_giris_id, MAX(id) AS max_id
                        FROM lab_analizleri
                        GROUP BY hammadde_giris_id
                    ) la_max ON la_max.max_id = la1.id
               ) la ON la.hammadde_giris_id = hg.id
               LEFT JOIN (
                    SELECT hka1.*
                    FROM hammadde_kabul_akisi hka1
                    INNER JOIN (
                        SELECT hammadde_giris_id, MAX(id) AS max_id
                        FROM hammadde_kabul_akisi
                        GROUP BY hammadde_giris_id
                    ) hka_max ON hka_max.max_id = hka1.id
               ) hka ON hka.hammadde_giris_id = hg.id
               LEFT JOIN (
                    SELECT parti_kodu,
                           COUNT(*) AS dagitim_kayit_sayisi,
                           SUM(kalan_miktar_kg) AS toplam_kalan_kg
                    FROM silo_stok_detay
                    GROUP BY parti_kodu
               ) ssd_ozet ON ssd_ozet.parti_kodu = hg.parti_no
               LEFT JOIN (
                    SELECT kaynak_parti_kodu AS parti_kodu,
                           COUNT(*) AS cikis_log_sayisi
                    FROM uretim_silo_cikis_log
                    GROUP BY kaynak_parti_kodu
               ) usc_ozet ON usc_ozet.parti_kodu = hg.parti_no
               $duzeltme_join_gecmis
               $sql_filtre
               ORDER BY hg.tarih DESC LIMIT 500";

$gecmis = $baglanti->query($sql_gecmis);

// Hammadde listesini filtre dropdown için tekrar başa al
$hammadde_options = "";
$hammaddeler->data_seek(0);
while ($h = $hammaddeler->fetch_assoc()) {
    $selected = ($filtre_hammadde == $h['id']) ? 'selected' : '';
    $hammadde_options .= "<option value='{$h['id']}' $selected>{$h['hammadde_kodu']} - {$h['ad']}</option>";
}
// selectler için geri sar
$hammaddeler->data_seek(0);

$duzeltme_talepleri = null;
$duzeltme_talepleri_bekleyen_adet = 0;
if ($duzeltme_tablo_var) {
    $talep_where = $is_patron ? "1=1" : "sdt.talep_eden_user_id = $aktif_user_id";
    $duzeltme_sql = "SELECT 
                        sdt.*,
                        hg.arac_plaka,
                        hg.tedarikci,
                        h.hammadde_kodu,
                        h.ad AS hammadde_adi,
                        ue.kadi AS talep_eden_kadi,
                        uk.kadi AS karar_veren_kadi
                     FROM silo_duzeltme_talepleri sdt
                     LEFT JOIN hammadde_girisleri hg ON hg.id = sdt.hammadde_giris_id
                     LEFT JOIN hammaddeler h ON h.id = hg.hammadde_id
                     LEFT JOIN users ue ON ue.id = sdt.talep_eden_user_id
                     LEFT JOIN users uk ON uk.id = sdt.karar_veren_user_id
                     WHERE $talep_where
                     ORDER BY sdt.created_at DESC
                     LIMIT 300";
    $duzeltme_talepleri = $baglanti->query($duzeltme_sql);

    $bekleyen_sql = "SELECT COUNT(*) AS adet FROM silo_duzeltme_talepleri sdt WHERE sdt.durum = 'bekliyor'" . ($is_patron ? "" : " AND sdt.talep_eden_user_id = $aktif_user_id");
    $bekleyen_res = $baglanti->query($bekleyen_sql);
    if ($bekleyen_res && ($bekleyen_row = $bekleyen_res->fetch_assoc())) {
        $duzeltme_talepleri_bekleyen_adet = (int) ($bekleyen_row['adet'] ?? 0);
    }
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hammadde Kabul - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --ozbal-primary: #0f172a;
            --ozbal-accent: #f59e0b;
            --ozbal-success: #10b981;
            --ozbal-danger: #ef4444;
            --ozbal-bg: #f8fafc;
            --ozbal-card-bg: #ffffff;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--ozbal-bg);
            color: #1e293b;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
            margin-bottom: 1.5rem;
            background: var(--ozbal-card-bg);
        }

        .card-header {
            border-radius: 12px 12px 0 0 !important;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }

        .bg-primary {
            background-color: var(--ozbal-primary) !important;
        }

        .bg-dark {
            background-color: #1e293b !important;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .form-control,
        .form-select {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.625rem 0.875rem;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--ozbal-accent);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .btn-success {
            background-color: var(--ozbal-success);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-success:hover {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .table-responsive {
            -webkit-overflow-scrolling: touch;
            padding: 0.5rem;
        }

        .table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            border-bottom: 2px solid #e2e8f0;
            padding: 1rem 2rem 1rem 1rem !important;
            vertical-align: middle;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }

        .badge-soft {
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.75rem;
            border: 1px solid transparent;
        }

        .badge-soft-hl {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        .badge-soft-nem {
            background: #dbeafe;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .badge-soft-prot {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .badge-soft-nis {
            background: #f3e8ff;
            color: #6b21a8;
            border-color: #e9d5ff;
        }

        .badge-soft-sert {
            background: #ffedd5;
            color: #9a3412;
            border-color: #fed7aa;
        }

        /* DataTables Spacing Fixes */
        .dataTables_length select {
            margin: 0 0.5rem;
            padding-right: 2.5rem !important;
            min-width: 80px;
        }

        .dataTables_filter input {
            margin-left: 0.5rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.4rem 0.8rem;
        }

        .dataTables_info,
        .dataTables_paginate {
            margin-top: 1rem;
            font-size: 0.875rem;
        }

        @media (max-width: 992px) {
            .border-end {
                border-end: none !important;
                border-bottom: 1px solid #e2e8f0;
                margin-bottom: 1.5rem;
                padding-bottom: 1rem;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Zorunlu alan göstergesi */
        .required-field::after {
            content: " *";
            color: #ef4444;
            font-weight: bold;
        }

        /* Bekleyen kantar badge */
        .badge-kantar-bekliyor {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
    </style>
</head>

<body class="bg-light">
    <?php include("navbar.php"); ?>
    <div class="container py-4">



        <div class="row">
            <!-- Üst Kısım: Giriş Formu (Yatay Tasarım) -->
            <div class="col-12 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-truck-loading me-2"></i>Yeni Araç Girişi</h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-light fw-bold" data-bs-toggle="modal"
                                data-bs-target="#yeniHammaddeModal">
                                <i class="fas fa-plus-circle me-1"></i>Yeni Hammadde Tanımla
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="row">
                                <!-- Araç Giriş Bilgileri -->
                                <div class="col-12">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label required-field">Gelen Hammadde Cinsi</label>
                                            <select name="hammadde_id" class="form-select" required>
                                                <option value="">Seçiniz...</option>
                                                <?php while ($h = $hammaddeler->fetch_assoc()) {
                                                    $selected = (isset($_POST['hammadde_id']) && $_POST['hammadde_id'] == $h['id']) ? 'selected' : '';
                                                    ?>
                                                    <option value="<?php echo $h["id"]; ?>" <?php echo $selected; ?>>
                                                        <?php echo $h["hammadde_kodu"] . " - " . $h["ad"]; ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label required-field">Parti No</label>
                                            <input type="text" name="parti_no" class="form-control"
                                                placeholder="Otomatik Oluşturulur"
                                                value="<?php echo htmlspecialchars($_POST['parti_no'] ?? ''); ?>"
                                                required>
                                            <small class="text-muted">Otomatik oluşur (değiştirilebilir)</small>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label required-field">Tedarikçi Firma</label>
                                            <input type="text" name="tedarikci" id="alan_tedarikci" class="form-control"
                                                placeholder="Firma Adı"
                                                value="<?php echo htmlspecialchars($_POST['tedarikci'] ?? ''); ?>"
                                                required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label required-field">Araç Plaka</label>
                                            <div class="input-group">
                                                <input type="text" name="plaka" id="alan_plaka" class="form-control"
                                                    placeholder="27 ABC 123"
                                                    value="<?php echo htmlspecialchars($_POST['plaka'] ?? ''); ?>"
                                                    required oninput="this.value = this.value.toUpperCase()"
                                                    style="text-transform: uppercase;">
                                                <span class="input-group-text bg-warning text-dark"
                                                    id="kantar_plaka_info" style="display:none;font-size:0.75rem;"><i
                                                        class="fas fa-check-circle"></i></span>
                                            </div>
                                        </div>
                                        <!-- Net Ağırlık (KG) input removed - will be entered from Kantar Modal after approval -->
                                        <div class="col-md-4 mb-3 d-flex align-items-end">
                                            <button type="submit" name="giris_yap"
                                                class="btn btn-success w-100 fw-bold">
                                                <i class="fas fa-save me-2"></i>Kaydet
                                            </button>
                                        </div>
                                    </div>
                                    <div class="alert alert-info mt-2 mb-0">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <small>
                                            <strong>*</strong> işaretli alanlar zorunludur.<br>
                                            Net ağırlık (kantar) işlemi Lab Analizi ve Satınalma Onayı adımlarından
                                            sonra kantar penceresinden yapılacaktır.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Alt Kısım: İzlenebilirlik Tablosu (Tam Genişlik) -->
            <div class="col-12">
                <div class="card shadow-sm mb-3">
                    <div class="card-body py-2">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <a href="hammadde.php?tab=izlenebilirlik"
                                class="btn btn-sm <?php echo $aktif_tab === 'izlenebilirlik' ? 'btn-dark' : 'btn-outline-dark'; ?>">
                                <i class="fas fa-stream me-1"></i> İzlenebilirlik
                            </a>
                            <?php if ($duzeltme_tablo_var): ?>
                                <a href="hammadde.php?tab=duzeltme_talepleri"
                                    class="btn btn-sm <?php echo $aktif_tab === 'duzeltme_talepleri' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                                    <i class="fas fa-rotate-left me-1"></i> Düzeltme Talepleri
                                    <?php if ($duzeltme_talepleri_bekleyen_adet > 0): ?>
                                        <span class="badge bg-dark ms-1"><?php echo $duzeltme_talepleri_bekleyen_adet; ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($aktif_tab !== 'duzeltme_talepleri'): ?>
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Kayıtları Filtrele</h5>
                            <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse"
                                data-bs-target="#filterCollapse"
                                aria-expanded="<?php echo (!empty($_GET) ? 'true' : 'false'); ?>"
                                aria-controls="filterCollapse">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        <div class="collapse <?php echo (!empty($_GET['f_baslangic']) || !empty($_GET['f_hammadde']) || !empty($_GET['f_plaka']) || !empty($_GET['f_tedarikci']) ? 'show' : ''); ?>"
                            id="filterCollapse">
                            <div class="card-body bg-light border-bottom">
                                <form method="GET" action="hammadde.php" class="row g-3">
                                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($aktif_tab); ?>">
                                    <div class="col-md-3">
                                        <label class="form-label text-muted small fw-bold">Başlangıç Tarihi</label>
                                        <input type="date" name="f_baslangic" class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars($filtre_baslangic); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-muted small fw-bold">Bitiş Tarihi</label>
                                        <input type="date" name="f_bitis" class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars($filtre_bitis); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label text-muted small fw-bold">Plaka</label>
                                        <input type="text" name="f_plaka" class="form-control form-control-sm"
                                            placeholder="Arama..." value="<?php echo htmlspecialchars($filtre_plaka); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label text-muted small fw-bold">Tedarikçi</label>
                                        <input type="text" name="f_tedarikci" class="form-control form-control-sm"
                                            placeholder="Arama..."
                                            value="<?php echo htmlspecialchars($filtre_tedarikci); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label text-muted small fw-bold">Hammadde</label>
                                        <select name="f_hammadde" class="form-select form-select-sm">
                                            <option value="">Tümü</option>
                                            <?php echo $hammadde_options; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 mt-3 text-end">
                                        <a href="hammadde.php" class="btn btn-sm btn-secondary me-2"><i
                                                class="fas fa-times me-1"></i>Temizle</a>
                                        <button type="submit" class="btn btn-sm btn-primary"><i
                                                class="fas fa-search me-1"></i>Filtrele</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Son Hammadde Girişleri (İzlenebilirlik)</h5>
                        </div>
                        <div class="table-responsive p-3">
                            <table id="gecmisTablo" class="table table-hover mb-0 table-striped align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Plaka</th>
                                        <th>Tedarikçi</th>
                                        <th>Ürün</th>
                                        <th>Silo</th>
                                        <th>Miktar (KG / M³)</th>
                                        <th>Analiz Değerleri</th>
                                        <th class="text-center">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($gecmis && $gecmis->num_rows > 0) {
                                        while ($row = $gecmis->fetch_assoc()) { ?>
                                            <tr>
                                                <td data-order="<?php echo $row["tarih"]; ?>">
                                                    <small><?php echo date("d.m.Y H:i", strtotime($row["tarih"])); ?></small>
                                                </td>
                                                <td><span class="badge bg-secondary"><?php echo $row["arac_plaka"]; ?></span></td>
                                                <td><small><?php echo htmlspecialchars($row["tedarikci"] ?? '-'); ?></small></td>
                                                <td>
                                                    <?php $durum_bilgi = hammaddeIzlenebilirlikDurum($row); ?>
                                                    <strong class="text-primary"><?php echo $row["urun_adi"]; ?></strong>
                                                    <?php if (!empty($row["parti_no"])) { ?>
                                                        <div class="small text-muted"><i
                                                                class="fas fa-barcode me-1"></i><?php echo $row["parti_no"]; ?></div>
                                                    <?php } ?>
                                                    <div class="mt-1">
                                                        <span class="badge <?php echo $durum_bilgi['badge_class']; ?>">
                                                            <i
                                                                class="fas <?php echo $durum_bilgi['icon']; ?> me-1"></i><?php echo $durum_bilgi['etiket']; ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td><span
                                                        class="badge bg-light text-dark border"><?php echo $row["silo_adi"] ?: '-'; ?></span>
                                                </td>
                                                <td>
                                                    <div class="fw-bold">
                                                        <?php echo number_format((float)$row["miktar_kg"], 0, ',', '.'); ?> kg
                                                    </div>
                                                    <div class="small text-muted">
                                                        <?php echo number_format((float)$row["hesaplanan_m3"], 2); ?> m³
                                                    </div>

                                                </td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-1 analysis-badges">
                                                        <?php
                                                        // Determine if lab analysis exists
                                                        $has_lab = isset($row["lab_hektolitre"]) || isset($row["lab_nem"]) || isset($row["lab_protein"]);

                                                        if ($has_lab) { ?>
                                                            <span class="badge badge-soft badge-soft-hl" title="Hektolitre">HL:
                                                                <?php echo $row["lab_hektolitre"] ?: '-'; ?></span>
                                                            <span class="badge badge-soft badge-soft-nem" title="Nem">N:
                                                                <?php echo $row["lab_nem"] ?: '-'; ?> %</span>
                                                            <span class="badge badge-soft badge-soft-prot" title="Protein">P:
                                                                <?php echo $row["lab_protein"] ?: '-'; ?> %</span>
                                                            <span class="badge badge-soft badge-soft-nis" title="Nişasta">Niş:
                                                                <?php echo $row["lab_nisasta"] ?: '-'; ?> %</span>
                                                            <span class="badge badge-soft badge-soft-sert" title="Sertlik">S:
                                                                <?php echo $row["lab_sertlik"] ?: '-'; ?></span>
                                                        <?php } else { ?>
                                                            <a href="lab_analizleri.php"
                                                                class="text-decoration-none small text-warning"><i
                                                                    class="fas fa-exclamation-circle me-1"></i>Analiz Bekliyor</a>
                                                        <?php } ?>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php
                                                    $can_transfer = (bool) ($durum_bilgi['can_transfer'] ?? false);
                                                    $is_locked = (bool) ($durum_bilgi['is_locked'] ?? false);
                                                    $durum_kodu = (string) ($durum_bilgi['kod'] ?? '');
                                                    $onayli_talep_ile_duzeltme = ($is_locked && !$is_patron && ((int) ($row['duzeltme_onayli_talep_id'] ?? 0) > 0));
                                                    $patron_direkt_duzeltme = ($is_locked && $is_patron);
                                                    $modal_aksiyon_aktif = ($can_transfer || $patron_direkt_duzeltme || $onayli_talep_ile_duzeltme);

                                                    if ($can_transfer) {
                                                        $buton_sinifi = 'btn-warning';
                                                        $buton_title = 'Silo Aktarma';
                                                        $buton_icon = 'fa-right-left';
                                                        $buton_metin = 'Aktar';
                                                    } elseif ($onayli_talep_ile_duzeltme) {
                                                        $buton_sinifi = 'btn-outline-warning';
                                                        $buton_title = 'Onaylı Düzeltme';
                                                        $buton_icon = 'fa-screwdriver-wrench';
                                                        $buton_metin = 'Düzelt';
                                                    } elseif ($patron_direkt_duzeltme) {
                                                        $buton_sinifi = 'btn-outline-primary';
                                                        $buton_title = 'Patron Düzeltmesi';
                                                        $buton_icon = 'fa-pen-to-square';
                                                        $buton_metin = 'Düzelt';
                                                    } elseif ($is_locked) {
                                                        $buton_sinifi = 'btn-secondary disabled';
                                                        $buton_title = 'Silo aktarimi tamamlandi, kayit kilitli.';
                                                        $buton_icon = 'fa-lock';
                                                        $buton_metin = '';
                                                    } elseif ($durum_kodu === 'reddedildi') {
                                                        $buton_sinifi = 'btn-secondary disabled';
                                                        $buton_title = 'Reddedilen kayitta silo aktarimi yok.';
                                                        $buton_icon = 'fa-ban';
                                                        $buton_metin = '';
                                                    } else {
                                                        $buton_sinifi = 'btn-secondary disabled';
                                                        $buton_title = 'Silo Aktarma Bekleniyor durumuna gelmesi gerekiyor.';
                                                        $buton_icon = 'fa-clock';
                                                        $buton_metin = '';
                                                    }
                                                    ?>
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <button type="button" class="btn btn-sm <?php echo $buton_sinifi; ?>" <?php echo $modal_aksiyon_aktif ? 'data-bs-toggle="modal" data-bs-target="#kantarModal"' : ''; ?>
                                                            data-id="<?php echo $row['id']; ?>"
                                                            data-plaka="<?php echo htmlspecialchars($row['arac_plaka']); ?>"
                                                            data-tedarikci="<?php echo htmlspecialchars($row['tedarikci'] ?? ''); ?>"
                                                            data-referans-kg="<?php echo (float) ($row['referans_kantar_kg'] ?? 0); ?>"
                                                            data-hammadde-kodu="<?php echo htmlspecialchars($row['hammadde_kodu'] ?? '', ENT_QUOTES); ?>"
                                                            data-yogunluk="<?php echo (float) ($row['hammadde_yogunluk'] ?? 780); ?>"
                                                            data-patron-duzeltme="<?php echo $patron_direkt_duzeltme ? '1' : '0'; ?>"
                                                            title="<?php echo $buton_title; ?>">
                                                            <i class="fas <?php echo $buton_icon; ?>"></i>
                                                            <?php if ($buton_metin !== ''): ?>
                                                                <small class="ms-1"><?php echo $buton_metin; ?></small>
                                                            <?php endif; ?>
                                                        </button>

                                                        <?php if ($is_locked && !$is_patron && !$onayli_talep_ile_duzeltme && $duzeltme_tablo_var): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                                data-bs-toggle="modal" data-bs-target="#duzeltmeTalepModal"
                                                                data-giris-id="<?php echo (int) $row['id']; ?>"
                                                                data-parti-no="<?php echo htmlspecialchars($row['parti_no'] ?? '', ENT_QUOTES); ?>"
                                                                data-plaka="<?php echo htmlspecialchars($row['arac_plaka'] ?? '', ENT_QUOTES); ?>"
                                                                data-hammadde-kodu="<?php echo htmlspecialchars($row['hammadde_kodu'] ?? '', ENT_QUOTES); ?>"
                                                                title="Silo düzeltme talebi aç">
                                                                <i class="fas fa-flag"></i>
                                                            </button>
                                                        <?php elseif ($is_locked && !$is_patron && !$duzeltme_tablo_var): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                                onclick="Swal.fire({icon:'info',title:'Düzeltme talebi pasif',text:'Önce veritabanında silo_duzeltme_talepleri tablosunu oluşturmanız gerekiyor.'});"
                                                                title="Düzeltme talebi için SQL güncellemesi gerekli">
                                                                <i class="fas fa-flag"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php }
                                    } else {
                                        echo "<tr><td colspan='8' class='text-center p-4'>Henüz hammadde girişi bulunmuyor.</td></tr>";
                                    } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($duzeltme_tablo_var && $aktif_tab === 'duzeltme_talepleri'): ?>
                    <div class="card shadow-sm mt-3" id="duzeltme_talepleri_kart">
                        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-rotate-left me-2"></i>Silo Düzeltme Talepleri</h5>
                            <span class="badge bg-dark"><?php echo (int) $duzeltme_talepleri_bekleyen_adet; ?>
                                Bekleyen</span>
                        </div>
                        <div class="table-responsive p-3">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Parti / Hammadde</th>
                                        <th>Talep Eden</th>
                                        <th>Neden</th>
                                        <th>Durum</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($duzeltme_talepleri && $duzeltme_talepleri->num_rows > 0): ?>
                                        <?php while ($talep = $duzeltme_talepleri->fetch_assoc()): ?>
                                            <?php
                                            $talep_durum = (string) ($talep['durum'] ?? 'bekliyor');
                                            $talep_badge = 'bg-secondary';
                                            if ($talep_durum === 'bekliyor')
                                                $talep_badge = 'bg-warning text-dark';
                                            elseif ($talep_durum === 'onaylandi')
                                                $talep_badge = 'bg-primary';
                                            elseif ($talep_durum === 'reddedildi')
                                                $talep_badge = 'bg-danger';
                                            elseif ($talep_durum === 'uygulandi')
                                                $talep_badge = 'bg-success';
                                            ?>
                                            <tr>
                                                <td><small><?php echo date("d.m.Y H:i", strtotime((string) ($talep['created_at'] ?? 'now'))); ?></small>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars((string) ($talep['parti_no'] ?? '-')); ?></strong>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <?php echo htmlspecialchars((string) ($talep['hammadde_kodu'] ?? '-')); ?> /
                                                        <?php echo htmlspecialchars((string) ($talep['arac_plaka'] ?? '-')); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <?php echo htmlspecialchars((string) ($talep['talep_eden_kadi'] ?? '-')); ?>
                                                    </div>
                                                    <?php if (!empty($talep['karar_veren_kadi'])): ?>
                                                        <div class="small text-muted">Karar:
                                                            <?php echo htmlspecialchars((string) $talep['karar_veren_kadi']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="min-width:260px;">
                                                    <div class="small">
                                                        <?php echo nl2br(htmlspecialchars((string) ($talep['talep_nedeni'] ?? ''))); ?>
                                                    </div>
                                                    <?php if (!empty($talep['karar_notu'])): ?>
                                                        <div class="small text-muted mt-1"><strong>Not:</strong>
                                                            <?php echo nl2br(htmlspecialchars((string) $talep['karar_notu'])); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span
                                                        class="badge <?php echo $talep_badge; ?>"><?php echo htmlspecialchars(ucfirst($talep_durum)); ?></span>
                                                </td>
                                                <td style="min-width:240px;">
                                                    <?php if ($is_patron && $talep_durum === 'bekliyor'): ?>
                                                        <form method="post" class="d-flex flex-column gap-1">
                                                            <input type="hidden" name="talep_id"
                                                                value="<?php echo (int) $talep['id']; ?>">
                                                            <input type="text" name="karar_notu" class="form-control form-control-sm"
                                                                placeholder="Karar notu (opsiyonel)">
                                                            <div class="d-flex gap-1">
                                                                <button type="submit" name="silo_duzeltme_talep_karar" value="1"
                                                                    class="btn btn-sm btn-success w-100"
                                                                    onclick="this.form.karar.value='onayla';">
                                                                    <i class="fas fa-check me-1"></i>Onayla
                                                                </button>
                                                                <button type="submit" name="silo_duzeltme_talep_karar" value="1"
                                                                    class="btn btn-sm btn-danger w-100"
                                                                    onclick="this.form.karar.value='reddet';">
                                                                    <i class="fas fa-times me-1"></i>Reddet
                                                                </button>
                                                            </div>
                                                            <input type="hidden" name="karar" value="onayla">
                                                        </form>
                                                    <?php elseif ($talep_durum === 'onaylandi'): ?>
                                                        <span class="text-muted small">Onaylandı. Düzeltme, İzlenebilirlik ekranından
                                                            yapılır.</span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">İşlem yok</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-3">Gosterilecek duzeltme talebi
                                                bulunmuyor.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SİLO AKTARIM MODAL -->
    <div class="modal fade" id="kantarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header text-white" style="background:#8b5cf6;">
                    <h5 class="modal-title fw-bold"><i class="fas fa-random me-2"></i>Silo Aktarım ve Dağılım İşlemi
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 small">
                        <i class="fas fa-info-circle me-1"></i> Satınalma tarafından onaylanan kantar ağırlığı aşağıdaki
                        gibidir. Lütfen malzemenin döküleceği siloyu seçiniz.
                    </div>
                    <form method="post" id="kantarDagitimForm">
                        <input type="hidden" name="giris_id" id="modal_giris_id">
                        <input type="hidden" name="patron_duzeltme" id="modal_patron_duzeltme" value="0">
                        <input type="hidden" id="modal_hammadde_yogunluk" value="780">
                        <input type="hidden" id="modal_referans_kg_raw" value="0">

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Plaka</label>
                                <input type="text" id="modal_plaka" class="form-control form-control-sm" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Tedarikçi</label>
                                <input type="text" id="modal_tedarikci" class="form-control form-control-sm" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Hammadde Kodu</label>
                                <input type="text" id="modal_hammadde_kodu" class="form-control form-control-sm"
                                    readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Referans Kantar (KG)</label>
                                <input type="text" id="modal_referans_kg"
                                    class="form-control form-control-sm fw-bold text-primary" readonly>
                            </div>
                        </div>

                        <p class="small text-muted mb-2">Toplam silo dağıtımı referans kantar değeriyle birebir aynı
                            olmalıdır.</p>

                        <div id="silo_dagitim_alani">
                            <div class="row g-2 mb-2 silo-satir">
                                <div class="col-md-7">
                                    <select name="dagitim_silo_id[]" class="form-select dagitim-silo-select">
                                        <option value="">Silo Seç...</option>
                                        <?php echo $silo_option_html; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <div class="input-group">
                                        <input type="number" name="dagitim_kg[]" class="form-control dagitim-kg-input"
                                            step="0.01" min="0" placeholder="Miktar">
                                        <span class="input-group-text">KG</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2"
                            onclick="yeniSiloSatiriEkle()"><i class="fas fa-plus"></i> Silo Ekle</button>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="kantar_guncelle" class="btn btn-success btn-lg">
                                <i class="fas fa-save me-2"></i>Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($duzeltme_tablo_var): ?>
        <div class="modal fade" id="duzeltmeTalepModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="post" class="modal-content border-0 shadow">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-flag me-2"></i>Silo Düzeltme Talebi</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="giris_id" id="duzeltme_giris_id">
                        <div class="small text-muted mb-2">Parti: <strong id="duzeltme_parti_no">-</strong></div>
                        <div class="small text-muted mb-3">Plaka/Hammadde: <span id="duzeltme_plaka_hammadde">-</span></div>
                        <label class="form-label">Talep Nedeni</label>
                        <textarea name="talep_nedeni" class="form-control" rows="4"
                            placeholder="Hatalı silo aktarımı veya miktar problemi detayını yazın..." required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="silo_duzeltme_talep_olustur" class="btn btn-danger">
                            <i class="fas fa-paper-plane me-1"></i>Talep Gönder
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        const siloOptionHtml = <?php echo json_encode($silo_option_html, JSON_UNESCAPED_UNICODE); ?>;

        function parseSafeFloat(value) {
            if (typeof value !== 'string') {
                value = String(value ?? '');
            }
            value = value.replace(',', '.').trim();
            const parsed = parseFloat(value);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function yeniSiloSatiriEkle() {
            var satirHTML = `
                <div class="row g-2 mb-2 silo-satir mt-2 border-top pt-2">
                    <div class="col-md-7">
                        <select name="dagitim_silo_id[]" class="form-select dagitim-silo-select">
                            <option value="">Silo Seç...</option>
                            ${siloOptionHtml}
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="number" name="dagitim_kg[]" class="form-control dagitim-kg-input" step="0.01" min="0" placeholder="Miktar">
                            <span class="input-group-text">KG</span>
                        </div>
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.silo-satir').remove()"><i class="fas fa-times"></i></button>
                    </div>
                </div>`;
            document.getElementById('silo_dagitim_alani').insertAdjacentHTML('beforeend', satirHTML);
            guncelleSiloSecenekleri();
        }

        function dagitimSatirlariniSifirla() {
            const alan = document.getElementById('silo_dagitim_alani');
            if (!alan) {
                return;
            }

            const satirlar = alan.querySelectorAll('.silo-satir');
            satirlar.forEach((satir, index) => {
                if (index === 0) {
                    const select = satir.querySelector('.dagitim-silo-select');
                    const kgInput = satir.querySelector('.dagitim-kg-input');
                    if (select) {
                        select.selectedIndex = 0;
                    }
                    if (kgInput) {
                        kgInput.value = '';
                    }
                } else {
                    satir.remove();
                }
            });
        }

        function guncelleSiloSecenekleri() {
            const hammaddeKodu = (document.getElementById('modal_hammadde_kodu')?.value || '').trim();
            const yogunluk = parseSafeFloat(document.getElementById('modal_hammadde_yogunluk')?.value || '780') || 780;
            const secenekler = document.querySelectorAll('.dagitim-silo-select option');

            secenekler.forEach((option) => {
                if (!option.value) {
                    return;
                }

                const bosM3 = parseSafeFloat(option.getAttribute('data-bos-m3'));
                const izinliRaw = option.getAttribute('data-izinli') || '';
                const baseLabel = option.getAttribute('data-base-label') || option.textContent;
                const maxKg = Math.max(0, bosM3 * yogunluk);
                let izinli = true;

                if (izinliRaw.trim() !== '') {
                    try {
                        const izinliList = JSON.parse(izinliRaw);
                        if (Array.isArray(izinliList) && izinliList.length > 0) {
                            izinli = izinliList.includes(hammaddeKodu);
                        }
                    } catch (e) {
                        izinli = true;
                    }
                }

                const kapasiteVar = bosM3 > 0.0001;
                const uygun = (izinli && kapasiteVar);
                option.disabled = !uygun;
                option.hidden = !uygun;

                if (!uygun && option.selected) {
                    const select = option.parentElement;
                    if (select) {
                        select.value = '';
                    }
                }

                option.textContent = baseLabel + ' (Boş: ' + bosM3.toFixed(2) + ' m³ / Max: ' + Math.floor(maxKg) + ' KG)';
            });
        }

        function dagitimFormKontrol(event) {
            const referansKg = parseSafeFloat(document.getElementById('modal_referans_kg_raw')?.value || '0');
            const satirlar = document.querySelectorAll('#silo_dagitim_alani .silo-satir');
            let toplam = 0;
            let doluSatir = 0;

            for (const satir of satirlar) {
                const silo = satir.querySelector('.dagitim-silo-select')?.value || '';
                const kgVal = satir.querySelector('.dagitim-kg-input')?.value || '';
                const kg = parseSafeFloat(kgVal);

                if (silo === '' && kgVal.trim() === '') {
                    continue;
                }

                if (silo === '' || kg <= 0) {
                    event.preventDefault();
                    Swal.fire({ icon: 'warning', title: 'Eksik Dağıtım Satırı', text: 'Her satırda silo ve pozitif KG girilmelidir.' });
                    return false;
                }

                toplam += kg;
                doluSatir++;
            }

            if (doluSatir === 0) {
                event.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Dağıtım Yok', text: 'En az bir silo dağıtımı girmelisiniz.' });
                return false;
            }

            if (Math.abs(toplam - referansKg) > 0.01) {
                event.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Toplam Uyuşmuyor',
                    text: `Toplam dağıtım ${toplam.toFixed(2)} KG. Referans kantar ${referansKg.toFixed(2)} KG olmalıdır.`
                });
                return false;
            }

            return true;
        }
    </script>

    <!-- Yeni Hammadde Modal -->
    <div class="modal fade" id="yeniHammaddeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Yeni Hammadde Tanımla</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Hammadde Kodu *</label>
                            <input type="text" name="yeni_hammadde_kodu" class="form-control"
                                placeholder="Örn: BG-PREMIUM" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hammadde Adı *</label>
                            <input type="text" name="yeni_hammadde_ad" class="form-control"
                                placeholder="Örn: Premium Ekmeklik Buğday" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Yoğunluk (kg/m³)</label>
                            <input type="number" step="0.01" name="yeni_yogunluk" class="form-control" value="780"
                                placeholder="Örn: 780">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea name="yeni_aciklama" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="hammadde_ekle" class="btn btn-success">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
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
                    title: '<?php echo addslashes(str_replace(["✅ ", "✓ "], "", $mesaj)); ?>',
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
                    text: '<?php echo addslashes(str_replace(["❌ ", "✖ ", "HATA: "], "", $hata)); ?>',
                    confirmButtonColor: '#0f172a'
                });
            <?php endif; ?>

            if ($('#gecmisTablo').length) {
                $('#gecmisTablo').DataTable({
                    "order": [[0, "desc"]],
                    "pageLength": 10,
                    "language": {
                        "emptyTable": "Tabloda herhangi bir veri mevcut değil",
                        "info": "_TOTAL_ kayıttan _START_ - _END_ arasındaki kayıtlar gösteriliyor",
                        "infoEmpty": "Kayıt yok",
                        "infoFiltered": "(_MAX_ kayıt içerisinden bulunan)",
                        "infoPostFix": "",
                        "thousands": ".",
                        "lengthMenu": "Sayfada _MENU_ kayıt göster",
                        "loadingRecords": "Yükleniyor...",
                        "processing": "İşleniyor...",
                        "search": "Ara:",
                        "zeroRecords": "Eşleşen kayıt bulunamadı",
                        "paginate": {
                            "first": "İlk",
                            "last": "Son",
                            "next": "Sonraki",
                            "previous": "Önceki"
                        },
                        "aria": {
                            "sortAscending": ": artan sütun sıralamasını aktifleştir",
                            "sortDescending": ": azalan sütun sıralamasını aktifleştir"
                        }
                    },
                    "dom": '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
                });
            }

            // SİLO MODALI
            var kantarModal = document.getElementById('kantarModal');
            if (kantarModal) {
                kantarModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    if (!button) {
                        return;
                    }
                    var id = button.getAttribute('data-id');
                    var plaka = button.getAttribute('data-plaka');
                    var tedarikci = button.getAttribute('data-tedarikci');
                    var referansKg = parseSafeFloat(button.getAttribute('data-referans-kg') || '0');
                    var hammaddeKodu = button.getAttribute('data-hammadde-kodu') || '';
                    var yogunluk = parseSafeFloat(button.getAttribute('data-yogunluk') || '780');
                    var patronDuzeltme = button.getAttribute('data-patron-duzeltme') === '1' ? '1' : '0';

                    document.getElementById('modal_giris_id').value = id;
                    document.getElementById('modal_patron_duzeltme').value = patronDuzeltme;
                    document.getElementById('modal_plaka').value = plaka;
                    document.getElementById('modal_tedarikci').value = tedarikci || '-';
                    document.getElementById('modal_hammadde_kodu').value = hammaddeKodu;
                    document.getElementById('modal_hammadde_yogunluk').value = yogunluk > 0 ? yogunluk : 780;
                    document.getElementById('modal_referans_kg_raw').value = referansKg;
                    document.getElementById('modal_referans_kg').value = referansKg > 0
                        ? referansKg.toLocaleString('tr-TR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
                        : '-';

                    dagitimSatirlariniSifirla();
                    guncelleSiloSecenekleri();
                });
            }

            var duzeltmeTalepModal = document.getElementById('duzeltmeTalepModal');
            if (duzeltmeTalepModal) {
                duzeltmeTalepModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    if (!button) {
                        return;
                    }

                    var girisId = button.getAttribute('data-giris-id') || '';
                    var partiNo = button.getAttribute('data-parti-no') || '-';
                    var plaka = button.getAttribute('data-plaka') || '-';
                    var hammaddeKodu = button.getAttribute('data-hammadde-kodu') || '-';

                    var idInput = document.getElementById('duzeltme_giris_id');
                    var partiText = document.getElementById('duzeltme_parti_no');
                    var plakaHammaddeText = document.getElementById('duzeltme_plaka_hammadde');
                    var nedenInput = duzeltmeTalepModal.querySelector('textarea[name=\"talep_nedeni\"]');

                    if (idInput) {
                        idInput.value = girisId;
                    }
                    if (partiText) {
                        partiText.textContent = partiNo;
                    }
                    if (plakaHammaddeText) {
                        plakaHammaddeText.textContent = plaka + ' / ' + hammaddeKodu;
                    }
                    if (nedenInput) {
                        nedenInput.value = '';
                    }
                });
            }

            var dagitimForm = document.getElementById('kantarDagitimForm');
            if (dagitimForm) {
                dagitimForm.addEventListener('submit', dagitimFormKontrol);
            }

            // Hammadde cinsine göre otomatik seri/parti numarası getirme
            $('select[name="hammadde_id"]').on('change', function () {
                var hammaddeId = $(this).val();
                if (hammaddeId) {
                    $.ajax({
                        url: 'ajax/ajax_get_parti_no.php',
                        type: 'GET',
                        data: { hammadde_id: hammaddeId },
                        success: function (response) {
                            if (response.trim() !== '') {
                                $('input[name="parti_no"]').val(response.trim());
                            }
                        }
                    });
                } else {
                    $('input[name="parti_no"]').val('');
                }
            });
        });
    </script>

    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>