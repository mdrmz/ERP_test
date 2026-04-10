<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("baglan.php");
include("helper_functions.php");

if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}

// Modül bazlı yetki kontrolü
sayfaErisimKontrol($baglanti);

// Bakım bildirimlerini kontrol et
bakimBildirimleriniKontrolEt($baglanti);

$mesaj = "";
$hata = "";
$aktif_tab = "makine";
$izinli_tablar = ["makine", "gecmis", "malzeme", "dosyalar"];
if (isset($_GET["tab"]) && in_array($_GET["tab"], $izinli_tablar, true)) {
    $aktif_tab = $_GET["tab"];
}

$pdf_max_boyut = 10 * 1024 * 1024; // 10 MB
$pdf_upload_rel_klasor = 'uploads/bakim_pdf';
$pdf_upload_abs_klasor = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'bakim_pdf';
$pdf_yazma_yetkisi = yazmaYetkisiVar($baglanti);
$bakim_dokuman_tablosu_var = false;

$tablo_kontrol = $baglanti->query("SHOW TABLES LIKE 'bakim_dokumanlari'");
if ($tablo_kontrol && $tablo_kontrol->num_rows > 0) {
    $bakim_dokuman_tablosu_var = true;
}

function dosyaBoyutuFormatla($bytes)
{
    $bytes = (int) $bytes;
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / (1024 * 1024), 2) . ' MB';
}

// --- 0. AJAX MAKİNE GEÇMİŞİ ÇEKME ---
if (isset($_GET["get_history"]) && isset($_GET["makine_id"])) {
    $m_id = (int) $_GET["makine_id"];
    $query = $baglanti->prepare("SELECT * FROM bakim_kayitlari WHERE makine_id = ? ORDER BY bakim_tarihi DESC");
    $query->bind_param("i", $m_id);
    $query->execute();
    $result = $query->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $row['bakim_tarihi_fmt'] = date('d.m.Y', strtotime($row['bakim_tarihi']));
        $history[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($history);
    exit;
}

// --- 0.1 PDF DOSYA INDIRME ---
if (isset($_GET["pdf_indir"])) {
    $aktif_tab = "dosyalar";
    $pdf_id = (int) $_GET["pdf_indir"];
    $pdf_mode = (isset($_GET["pdf_mode"]) && $_GET["pdf_mode"] === "inline") ? "inline" : "attachment";

    if (!$bakim_dokuman_tablosu_var) {
        $hata = "Hata: Dokuman tablosu bulunamadi. Once kurulum scriptini calistirin.";
    } elseif ($pdf_id <= 0) {
        $hata = "Hata: Gecersiz dosya istegi.";
    } else {
        $stmt_pdf = $baglanti->prepare("SELECT id, orijinal_ad, dosya_yolu, mime_type FROM bakim_dokumanlari WHERE id = ? LIMIT 1");
        $stmt_pdf->bind_param("i", $pdf_id);
        $stmt_pdf->execute();
        $pdf_kayit = $stmt_pdf->get_result()->fetch_assoc();

        if (!$pdf_kayit) {
            $hata = "Hata: Dosya kaydi bulunamadi.";
        } else {
            $goreceli_yol = ltrim((string) $pdf_kayit['dosya_yolu'], '/\\');
            $aday_dosya = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $goreceli_yol);
            $gercek_dosya = realpath($aday_dosya);
            $gercek_klasor = realpath($pdf_upload_abs_klasor);

            if (!$gercek_dosya || !is_file($gercek_dosya) || !$gercek_klasor || strpos($gercek_dosya, $gercek_klasor) !== 0) {
                $hata = "Hata: Dosya fiziksel olarak bulunamadi.";
            } else {
                $indirilecek_ad = basename((string) $pdf_kayit['orijinal_ad']);
                $indirilecek_ad = str_replace(["\r", "\n"], '', $indirilecek_ad);
                if ($indirilecek_ad === '') {
                    $indirilecek_ad = 'dokuman.pdf';
                }

                while (ob_get_level()) {
                    ob_end_clean();
                }

                header('Content-Description: File Transfer');
                header('Content-Type: application/pdf');
                header('Content-Disposition: ' . $pdf_mode . '; filename="' . rawurlencode($indirilecek_ad) . '"');
                header('Content-Length: ' . filesize($gercek_dosya));
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                readfile($gercek_dosya);
                exit;
            }
        }
    }
}

// --- 1. YENİ MAKİNE EKLEME ---
if (isset($_POST["makine_ekle"])) {
    $kod = strtoupper(trim($_POST["makine_kodu"]));
    $ad = trim($_POST["makine_adi"]);
    $unite = trim($_POST["unite_adi"]);
    $kat = trim($_POST["kat_bilgisi"]);
    $lokasyon = trim($_POST["lokasyon"]);
    $periyot = (int) $_POST["bakim_periyodu"];
    $son_bakim = !empty($_POST["son_bakim_tarihi"]) ? $_POST["son_bakim_tarihi"] : NULL;

    // EDGE CASE: Önce aynı makine kodundan var mı kontrol et (Aktif veya pasif fark etmeksizin)
    $stmt_check = $baglanti->prepare("SELECT id FROM makineler WHERE makine_kodu = ?");
    $stmt_check->bind_param("s", $kod);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $hata = "Hata: Cihaz kayıt reddedildi! '$kod' koduna sahip bir makine zaten sistemde mevcut.";
    } else {
        // Sonraki bakım tarihini hesapla (son bakım varsa)
        $sonraki_bakim = NULL;
        if ($son_bakim) {
            $sonraki_bakim = date('Y-m-d', strtotime($son_bakim . " + $periyot days"));
        }

        $stmt = $baglanti->prepare("INSERT INTO makineler (makine_kodu, makine_adi, unite_adi, kat_bilgisi, lokasyon, bakim_periyodu, son_bakim_tarihi, sonraki_bakim_tarihi) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssiss", $kod, $ad, $unite, $kat, $lokasyon, $periyot, $son_bakim, $sonraki_bakim);

        if ($stmt->execute()) {
            $mesaj = "✅ Yeni makine eklendi: $ad";
            systemLogKaydet($baglanti, "INSERT", "Bakım & Arıza", "Yeni makine eklendi: $ad ($kod)");
        } else {
            $hata = "Hata: " . $baglanti->error;
        }
    }
}

// --- 2. MAKİNE GÜNCELLEME ---
if (isset($_POST["makine_guncelle"])) {
    $id = (int) $_POST["makine_id"];
    $kod = strtoupper(trim($_POST["makine_kodu"]));
    $ad = trim($_POST["makine_adi"]);
    $unite = trim($_POST["unite_adi"]);
    $kat = trim($_POST["kat_bilgisi"]);
    $lokasyon = trim($_POST["lokasyon"]);
    $periyot = (int) $_POST["bakim_periyodu"];

    // EDGE CASE: Güncellenmek istenen kod, başka bir makinede (başka ID'de) var mı kontrol et
    $stmt_check = $baglanti->prepare("SELECT id FROM makineler WHERE makine_kodu = ? AND id != ?");
    $stmt_check->bind_param("si", $kod, $id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $hata = "Hata: Güncelleme reddedildi! '$kod' kodu zaten başka bir makine tarafından kullanılıyor.";
    } else {
        // Eski periyot bilgisini ve son bakımı alıp yeni sonraki_bakim_tarihini hesapla
        $stmt_get = $baglanti->prepare("SELECT son_bakim_tarihi FROM makineler WHERE id = ?");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $res = $stmt_get->get_result()->fetch_assoc();

        $sonraki_bakim = NULL;
        if ($res && !empty($res['son_bakim_tarihi'])) {
            $sonraki_bakim = date('Y-m-d', strtotime($res['son_bakim_tarihi'] . " + $periyot days"));
        }

        $stmt = $baglanti->prepare("UPDATE makineler SET makine_kodu = ?, makine_adi = ?, unite_adi = ?, kat_bilgisi = ?, lokasyon = ?, bakim_periyodu = ?, sonraki_bakim_tarihi = ? WHERE id = ?");
        $stmt->bind_param("sssssisi", $kod, $ad, $unite, $kat, $lokasyon, $periyot, $sonraki_bakim, $id);

        if ($stmt->execute()) {
            $mesaj = "✅ Makine bilgileri güncellendi.";
            systemLogKaydet($baglanti, "UPDATE", "Bakım & Arıza", "Makine güncellendi: $ad ($kod)");
        } else {
            $hata = "Hata: " . $baglanti->error;
        }
    }
}

// --- 3. MAKİNE SİLME ---
if (isset($_GET["sil"])) {
    $id = (int) $_GET["sil"];

    // Önce makineye ait bakım geçmişini (foreing key kısıtlaması nedeniyle) sil
    $stmt_bakimlari_sil = $baglanti->prepare("DELETE FROM bakim_kayitlari WHERE makine_id = ?");
    $stmt_bakimlari_sil->bind_param("i", $id);
    $stmt_bakimlari_sil->execute();

    // Sonra makineyi veritabanından tamamen sil
    $stmt = $baglanti->prepare("DELETE FROM makineler WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $mesaj = "✅ Makine ve ona ait tüm bakım geçmişi sistemden silindi.";
        systemLogKaydet($baglanti, "DELETE", "Bakım & Arıza", "Makine silindi (ID: $id)");
    } else {
        $hata = "Hata: " . $baglanti->error;
    }
}

// --- 4. BAKIM KAYDI EKLEME ---
if (isset($_POST["bakim_ekle"])) {
    $makine_id = (int) $_POST["makine_id"];
    $tarih = $_POST["bakim_tarihi"];
    $tur = $_POST["bakim_turu"];
    $islem = $_POST["yapilan_islem"];
    $teknisyen = $_POST["teknisyen"];

    // EDGE CASE: Gelecekteki bir tarih veya çok eski bir tarih (örneğin 2 yıl öncesi) girilmesini engelle
    $bugunun_tarihi = date('Y-m-d');
    $max_gecmis_tarih = date('Y-m-d', strtotime('-1 years'));

    if ($tarih > $bugunun_tarihi) {
        $hata = "Hata: İleri bir tarihe bakım kaydı giremezsiniz.";
    } elseif ($tarih < $max_gecmis_tarih) {
        $hata = "Hata: Çok geçmiş bir tarihe (1 yıldan eski) bakım kaydı girilemez.";
    } else {
        // Makine bilgisini al (periyodu öğrenmek için)
        $stmt_periyot = $baglanti->prepare("SELECT bakim_periyodu, makine_adi FROM makineler WHERE id = ?");
        $stmt_periyot->bind_param("i", $makine_id);
        $stmt_periyot->execute();
        $makine_res = $stmt_periyot->get_result()->fetch_assoc();
        $periyot = $makine_res['bakim_periyodu'];
        $m_adi = $makine_res['makine_adi'];

        // Sonraki bakım tarihini güncelle
        $sonraki_bakim = date('Y-m-d', strtotime($tarih . " + $periyot days"));

        // 1. Bakım kaydını ekle
        $stmt_kayit = $baglanti->prepare("INSERT INTO bakim_kayitlari (makine_id, bakim_tarihi, bakim_turu, yapilan_islem, sonraki_bakim, teknisyen) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_kayit->bind_param("isssss", $makine_id, $tarih, $tur, $islem, $sonraki_bakim, $teknisyen);

        if ($stmt_kayit->execute()) {
            // 2. Makine tablosunu güncelle
            $stmt_upd = $baglanti->prepare("UPDATE makineler SET son_bakim_tarihi = ?, sonraki_bakim_tarihi = ? WHERE id = ?");
            $stmt_upd->bind_param("ssi", $tarih, $sonraki_bakim, $makine_id);
            $stmt_upd->execute();

            $mesaj = "✅ Bakım kaydı eklendi ve sonraki bakım tarihi güncellendi.";
            systemLogKaydet($baglanti, "INSERT", "Bakım & Arıza", "$m_adi için bakım kaydı girildi ($tur)");
        } else {
            $hata = "Hata: " . $baglanti->error;
        }
    }
}

// --- 4.1 BAKIM KAYDI GÜNCELLEME ---
if (isset($_POST["bakim_guncelle"])) {
    $kayit_id = (int) $_POST["kayit_id"];
    $makine_id = (int) $_POST["makine_id"];
    $tarih = $_POST["bakim_tarihi"];
    $tur = $_POST["bakim_turu"];
    $islem = $_POST["yapilan_islem"];
    $teknisyen = $_POST["teknisyen"];

    // Makine periyodunu al
    $q_mach = $baglanti->query("SELECT bakim_periyodu FROM makineler WHERE id = $makine_id")->fetch_assoc();
    $periyot = $q_mach['bakim_periyodu'];
    $sonraki_bakim = date('Y-m-d', strtotime($tarih . " + $periyot days"));

    $stmt = $baglanti->prepare("UPDATE bakim_kayitlari SET bakim_tarihi = ?, bakim_turu = ?, yapilan_islem = ?, teknisyen = ?, sonraki_bakim = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $tarih, $tur, $islem, $teknisyen, $sonraki_bakim, $kayit_id);

    if ($stmt->execute()) {
        // En son bakımı bulup makine tablosunu güncelle (Senkronizasyon)
        $q_latest = $baglanti->query("SELECT bakim_tarihi, sonraki_bakim FROM bakim_kayitlari WHERE makine_id = $makine_id ORDER BY bakim_tarihi DESC LIMIT 1")->fetch_assoc();
        if ($q_latest) {
            $stmt_upd = $baglanti->prepare("UPDATE makineler SET son_bakim_tarihi = ?, sonraki_bakim_tarihi = ? WHERE id = ?");
            $stmt_upd->bind_param("ssi", $q_latest['bakim_tarihi'], $q_latest['sonraki_bakim'], $makine_id);
            $stmt_upd->execute();
        }
        $mesaj = "✅ Bakım kaydı güncellendi.";
        systemLogKaydet($baglanti, "UPDATE", "Bakım & Arıza", "Bakım kaydı güncellendi (Kayıt ID: $kayit_id)");
    } else {
        $hata = "Hata: " . $baglanti->error;
    }
}

// --- 4.2 BAKIM KAYDI SİLME ---
if (isset($_GET["bakim_sil"])) {
    $kayit_id = (int) $_GET["bakim_sil"];
    // Önce makine ID'sini al
    $q_kayit = $baglanti->query("SELECT makine_id FROM bakim_kayitlari WHERE id = $kayit_id")->fetch_assoc();
    if ($q_kayit) {
        $mid = $q_kayit['makine_id'];
        if ($baglanti->query("DELETE FROM bakim_kayitlari WHERE id = $kayit_id")) {
            // Makine tablosunu güncelle (En son kalan bakıma göre veya tamamen temizle)
            $q_prev = $baglanti->query("SELECT bakim_tarihi, sonraki_bakim FROM bakim_kayitlari WHERE makine_id = $mid ORDER BY bakim_tarihi DESC LIMIT 1")->fetch_assoc();
            if ($q_prev) {
                $stmt_upd = $baglanti->prepare("UPDATE makineler SET son_bakim_tarihi = ?, sonraki_bakim_tarihi = ? WHERE id = ?");
                $stmt_upd->bind_param("ssi", $q_prev['bakim_tarihi'], $q_prev['sonraki_bakim'], $mid);
            } else {
                $stmt_upd = $baglanti->prepare("UPDATE makineler SET son_bakim_tarihi = NULL, sonraki_bakim_tarihi = NULL WHERE id = ?");
                $stmt_upd->bind_param("i", $mid);
            }
            $stmt_upd->execute();
            $mesaj = "✅ Bakım kaydı silindi.";
            systemLogKaydet($baglanti, "DELETE", "Bakım & Arıza", "Bakım kaydı silindi (Kayıt ID: $kayit_id)");
        }
    }
}

// --- 5. LAB MALZEME EKLEME ---
if (isset($_POST["lab_malzeme_ekle"])) {
    $ad = $baglanti->real_escape_string($_POST["malzeme_adi"]);
    $miktar = (float) $_POST["miktar"];
    $birim = $baglanti->real_escape_string($_POST["birim"]);
    $alan = $baglanti->real_escape_string($_POST["kullanim_alani"]);
    $kritik = (float) $_POST["kritik_seviye"];

    $sql = "INSERT INTO bakim_lab_malzemeler (malzeme_adi, miktar, birim, kullanim_alani, kritik_seviye) VALUES ('$ad', $miktar, '$birim', '$alan', $kritik)";
    if ($baglanti->query($sql)) {
        $mesaj = "✅ Lab malzemesi eklendi: $ad";
        systemLogKaydet($baglanti, "INSERT", "Bakım & Arıza", "Yeni lab malzemesi eklendi: $ad");
    } else {
        $hata = "Hata: " . $baglanti->error;
    }
}

// --- 6. LAB MALZEME SİLME ---
if (isset($_GET["lab_malzeme_sil"])) {
    $id = (int) $_GET["lab_malzeme_sil"];
    if ($baglanti->query("DELETE FROM bakim_lab_malzemeler WHERE id = $id")) {
        $mesaj = "✅ Malzeme silindi.";
    } else {
        $hata = "Hata: " . $baglanti->error;
    }
}

// İSTATİSTİKLER
$pdf_yukleme_aktif = $bakim_dokuman_tablosu_var;

// --- 7. PDF DOSYA YUKLEME ---
if (isset($_POST["pdf_yukle"])) {
    $aktif_tab = "dosyalar";
    if (!$pdf_yazma_yetkisi) {
        $hata = "Hata: PDF yukleme yetkiniz bulunmuyor.";
    } elseif (!$pdf_yukleme_aktif) {
        $hata = "Hata: Dokuman tablosu bulunamadi. Once kurulum scriptini calistirin.";
    } elseif (!isset($_FILES["pdf_dosya"])) {
        $hata = "Hata: Yuklenecek dosya secilmedi.";
    } else {
        $dosya = $_FILES["pdf_dosya"];

        if (!isset($dosya['error']) || $dosya['error'] !== UPLOAD_ERR_OK) {
            $hata_kodu = isset($dosya['error']) ? (int) $dosya['error'] : -1;
            if ($hata_kodu === UPLOAD_ERR_INI_SIZE || $hata_kodu === UPLOAD_ERR_FORM_SIZE) {
                $hata = "Hata: Dosya boyutu siniri asildi. Maksimum 10 MB yukleyebilirsiniz.";
            } else {
                $hata = "Hata: Dosya yukleme sirasinda bir sorun olustu (Kod: $hata_kodu).";
            }
        } elseif ((int) $dosya['size'] <= 0) {
            $hata = "Hata: Bos dosya yukleyemezsiniz.";
        } elseif ((int) $dosya['size'] > $pdf_max_boyut) {
            $hata = "Hata: Dosya boyutu 10 MB sinirini asiyor.";
        } else {
            $orijinal_ad = basename((string) $dosya['name']);
            $orijinal_ad = str_replace(["\r", "\n"], '', $orijinal_ad);
            $uzanti = strtolower((string) pathinfo($orijinal_ad, PATHINFO_EXTENSION));

            if ($uzanti !== 'pdf') {
                $hata = "Hata: Sadece PDF dosyasi yukleyebilirsiniz.";
            } else {
                $tmp_dosya = (string) $dosya['tmp_name'];
                $mime_type = '';

                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $mime_type = (string) finfo_file($finfo, $tmp_dosya);
                        finfo_close($finfo);
                    }
                } elseif (function_exists('mime_content_type')) {
                    $mime_type = (string) mime_content_type($tmp_dosya);
                }

                if ($mime_type !== 'application/pdf') {
                    $hata = "Hata: Gecersiz dosya turu. MIME tipi application/pdf olmalidir.";
                } else {
                    if (!is_dir($pdf_upload_abs_klasor) && !mkdir($pdf_upload_abs_klasor, 0755, true)) {
                        $hata = "Hata: Yedek klasoru olusturulamadi.";
                    } else {
                        try {
                            $rastgele = bin2hex(random_bytes(6));
                        } catch (Exception $e) {
                            $rastgele = (string) mt_rand(100000, 999999);
                        }

                        $saklanan_ad = 'bakim_' . date('Ymd_His') . '_' . $rastgele . '.pdf';
                        $hedef_abs = $pdf_upload_abs_klasor . DIRECTORY_SEPARATOR . $saklanan_ad;
                        $hedef_rel = $pdf_upload_rel_klasor . '/' . $saklanan_ad;

                        if (!move_uploaded_file($tmp_dosya, $hedef_abs)) {
                            $hata = "Hata: Dosya sunucuya tasinamadi.";
                        } else {
                            $dosya_boyut = (int) $dosya['size'];
                            $yukleyen_id = (int) ($_SESSION['user_id'] ?? 0);

                            $stmt_ekle = $baglanti->prepare("INSERT INTO bakim_dokumanlari (orijinal_ad, saklanan_ad, dosya_yolu, dosya_boyut, mime_type, yukleyen_user_id) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt_ekle->bind_param("sssisi", $orijinal_ad, $saklanan_ad, $hedef_rel, $dosya_boyut, $mime_type, $yukleyen_id);

                            if ($stmt_ekle->execute()) {
                                $mesaj = "PDF dosyasi yuklendi: $orijinal_ad";
                                systemLogKaydet($baglanti, "INSERT", "Bakim & Ariza", "Bakim dokumani yuklendi: $orijinal_ad");
                            } else {
                                if (is_file($hedef_abs)) {
                                    unlink($hedef_abs);
                                }
                                $hata = "Hata: Veritabani kaydi olusturulamadi. " . $baglanti->error;
                            }
                        }
                    }
                }
            }
        }
    }
}

// --- 8. PDF DOSYA SILME ---
if (isset($_GET["pdf_sil"])) {
    $aktif_tab = "dosyalar";
    $pdf_id = (int) $_GET["pdf_sil"];

    if (!$pdf_yazma_yetkisi) {
        $hata = "Hata: PDF silme yetkiniz bulunmuyor.";
    } elseif (!$pdf_yukleme_aktif) {
        $hata = "Hata: Dokuman tablosu bulunamadi. Once kurulum scriptini calistirin.";
    } elseif ($pdf_id <= 0) {
        $hata = "Hata: Gecersiz dosya kaydi.";
    } else {
        $stmt_bul = $baglanti->prepare("SELECT id, orijinal_ad, dosya_yolu FROM bakim_dokumanlari WHERE id = ? LIMIT 1");
        $stmt_bul->bind_param("i", $pdf_id);
        $stmt_bul->execute();
        $pdf_kayit = $stmt_bul->get_result()->fetch_assoc();

        if (!$pdf_kayit) {
            $hata = "Hata: Silinecek dosya kaydi bulunamadi.";
        } else {
            $goreceli_yol = ltrim((string) $pdf_kayit['dosya_yolu'], '/\\');
            $aday_dosya = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $goreceli_yol);
            $gercek_dosya = realpath($aday_dosya);
            $gercek_klasor = realpath($pdf_upload_abs_klasor);

            $silinecek_dosya = null;
            if ($gercek_dosya && $gercek_klasor && strpos($gercek_dosya, $gercek_klasor) === 0) {
                $silinecek_dosya = $gercek_dosya;
            } elseif (is_file($aday_dosya)) {
                $silinecek_dosya = $aday_dosya;
            }

            $stmt_sil = $baglanti->prepare("DELETE FROM bakim_dokumanlari WHERE id = ?");
            $stmt_sil->bind_param("i", $pdf_id);

            if ($stmt_sil->execute()) {
                if ($silinecek_dosya && is_file($silinecek_dosya)) {
                    @unlink($silinecek_dosya);
                }
                $mesaj = "PDF dosyasi silindi.";
                $dokuman_adi_log = (string) $pdf_kayit['orijinal_ad'];
                systemLogKaydet($baglanti, "DELETE", "Bakim & Ariza", "Bakim dokumani silindi: $dokuman_adi_log");
            } else {
                $hata = "Hata: Kayit silinemedi. " . $baglanti->error;
            }
        }
    }
}


// --- 11. YENİ MALZEME STOK EKLEME ---
if (isset($_POST["malzeme_stok_ekle"])) {
    $ismi = trim($_POST["malzeme_ismi"]);
    $alet = trim($_POST["malzeme_alet"]);
    $gelis = $_POST["malzeme_gelis"];
    $kullanim = trim($_POST["malzeme_kullanim"]);

    $stmt = $baglanti->prepare("INSERT INTO bakim_malzeme_stok (ismi, alet, gelis_tarihi, kullanim_miktari) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $ismi, $alet, $gelis, $kullanim);
    
    if ($stmt->execute()) {
        $mesaj = "✅ Yeni malzeme başarıyla eklendi.";
        systemLogKaydet($baglanti, "INSERT", "Bakım & Arıza", "Yeni malzeme eklendi: $ismi");
    } else {
        $hata = "Hata: " . $baglanti->error;
    }
}

// --- 12. MALZEME STOK GÜNCELLEME ---
if (isset($_POST["malzeme_stok_guncelle"])) {
    $id = (int) $_POST["malzeme_id"];
    $ismi = trim($_POST["malzeme_ismi"]);
    $alet = trim($_POST["malzeme_alet"]);
    $gelis = $_POST["malzeme_gelis"];
    $kullanim = trim($_POST["malzeme_kullanim"]);

    $stmt = $baglanti->prepare("UPDATE bakim_malzeme_stok SET ismi=?, alet=?, gelis_tarihi=?, kullanim_miktari=? WHERE id=?");
    $stmt->bind_param("ssssi", $ismi, $alet, $gelis, $kullanim, $id);
    
    if ($stmt->execute()) {
        $mesaj = "✅ Malzeme bilgileri güncellendi.";
        systemLogKaydet($baglanti, "UPDATE", "Bakım & Arıza", "Malzeme güncellendi (ID: $id)");
    } else {
        $hata = "Hata: " . $baglanti->error;
    }
}

// --- 13. MALZEME STOK SİLME ---
if (isset($_GET["malzeme_sil"])) {
    $id = (int) $_GET["malzeme_sil"];
    $stmt = $baglanti->prepare("DELETE FROM bakim_malzeme_stok WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $mesaj = "✅ Malzeme silindi.";
        systemLogKaydet($baglanti, "DELETE", "Bakım & Arıza", "Malzeme silindi (ID: $id)");
    } else {
        $hata = "Silme Hatası: " . $baglanti->error;
    }
    header("Location: bakim.php?tab=malzeme");
    exit;
}


$stats = [
    'gecikmis' => $baglanti->query("SELECT COUNT(*) as sayi FROM makineler WHERE aktif = 1 AND sonraki_bakim_tarihi < CURRENT_DATE")->fetch_assoc()['sayi'],
    'yaklasan' => $baglanti->query("SELECT COUNT(*) as sayi FROM makineler WHERE aktif = 1 AND sonraki_bakim_tarihi BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)")->fetch_assoc()['sayi'],
    'toplam' => $baglanti->query("SELECT COUNT(*) as sayi FROM makineler WHERE aktif = 1")->fetch_assoc()['sayi']
];

// LİSTELERİ ÇEK
$makineler = $baglanti->query("SELECT * FROM makineler WHERE aktif = 1 ORDER BY sonraki_bakim_tarihi ASC");
$malzeme_stoklar = $baglanti->query("SELECT * FROM bakim_malzeme_stok ORDER BY gelis_tarihi DESC");
$gecmis = $baglanti->query("SELECT b.*, m.makine_adi, m.makine_kodu FROM bakim_kayitlari b JOIN makineler m ON b.makine_id = m.id ORDER BY b.bakim_tarihi DESC LIMIT 20");
$lab_malzemeler = $baglanti->query("SELECT * FROM bakim_lab_malzemeler ORDER BY malzeme_adi ASC");
$bakim_dokumanlari = false;
if ($pdf_yukleme_aktif) {
    $bakim_dokumanlari = $baglanti->query("SELECT d.*, u.kadi, u.tam_ad FROM bakim_dokumanlari d LEFT JOIN users u ON d.yukleyen_user_id = u.id ORDER BY d.yukleme_tarihi DESC");
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakım Takip - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Select2 CSS for Searchable Dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    <style>
        :root {
            --maint-bg: #f2f5fb;
            --maint-surface: #ffffff;
            --maint-border: #dbe4f0;
            --maint-text: #122033;
            --maint-muted: #5f6f84;
            --maint-accent: #0284c7;
            --maint-accent-strong: #0369a1;
            --maint-dark: #1e293b;
            --maint-danger: #dc2626;
            --maint-warning: #d97706;
            --maint-success: #16a34a;
        }

        body.bg-light {
            font-family: 'Inter', sans-serif;
            background: 
                radial-gradient(1200px 420px at 100% -40%, rgba(2, 132, 199, 0.12), rgba(2, 132, 199, 0)),
                linear-gradient(180deg, #f7f9fd 0%, var(--maint-bg) 100%) !important;
            color: var(--maint-text);
            min-height: 100vh;
        }

        .page-hero {
            background: linear-gradient(128deg, #0f172a 0%, #1e293b 62%, #0284c7 145%);
            color: #fff;
            border-radius: 1.25rem;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 16px 26px -18px rgba(15, 23, 42, 0.9);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .page-hero::before {
            content: "";
            position: absolute;
            width: 380px;
            height: 380px;
            top: -70%;
            right: -5%;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0));
            pointer-events: none;
        }

        .hero-title {
            margin: 0;
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .hero-subtitle {
            margin: 0.35rem 0 0;
            color: rgba(255, 255, 255, 0.84);
            font-size: 1.05rem;
        }

        .hero-stats-wrapper {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            z-index: 1;
        }
        
        .hero-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .hero-stat-card {
            background: rgba(255, 255, 255, 0.11);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.85rem;
            padding: 0.75rem 1rem;
            backdrop-filter: blur(8px);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            min-width: 120px;
            text-align: center;
        }

        .hero-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -18px rgba(2, 6, 23, 0.95);
        }

        .hero-stat-card .label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .hero-stat-card .value {
            font-size: 1.7rem;
            font-weight: 700;
            color: #ffffff;
            line-height: 1;
        }

        .hero-stat-card.danger-stat {
            background: rgba(220, 38, 38, 0.2);
            border-color: rgba(220, 38, 38, 0.4);
        }
        
        .hero-stat-card.warning-stat {
            background: rgba(217, 119, 6, 0.2);
            border-color: rgba(217, 119, 6, 0.4);
        }

        .hero-actions {
            display: flex;
            gap: 0.75rem;
            flex-direction: column;
            justify-content: center;
        }

        .btn-surface {
            border-radius: 0.75rem;
            font-weight: 600;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Nav Pills Modernization */
        .nav-pills {
            background: var(--maint-surface);
            padding: 0.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 12px -8px rgba(0,0,0,0.1);
            display: inline-flex;
            gap: 0.25rem;
            border: 1px solid var(--maint-border);
        }

        .nav-pills .nav-link {
            border-radius: 0.75rem;
            padding: 0.75rem 1.25rem;
            font-weight: 600;
            color: var(--maint-muted);
            border: none;
            background: transparent;
            margin: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-pills .nav-link.active {
            background: var(--maint-accent);
            color: #fff;
            box-shadow: 0 4px 12px -4px rgba(2, 132, 199, 0.4);
        }

        .nav-pills .nav-link:hover:not(.active) {
            background: rgba(2, 132, 199, 0.08);
            color: var(--maint-accent-strong);
        }

        /* Surface Cards */
        .surface-card {
            background: var(--maint-surface);
            border: 1px solid var(--maint-border) !important;
            border-radius: 1.25rem;
            box-shadow: 0 8px 24px -12px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .surface-header {
            background: transparent;
            border-bottom: 1px solid var(--maint-border);
            padding: 1.25rem 1.5rem;
        }

        /* Makine Karti Modernizasyonu */
        .makine-card {
            background: var(--maint-surface);
            border: 1px solid var(--maint-border);
            border-radius: 1.25rem;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .makine-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -12px rgba(15, 23, 42, 0.15);
            border-color: #cbd5e1;
        }

        .mach-status-line {
            height: 6px;
            width: 100%;
        }

        .mach-status-danger { background-color: var(--maint-danger); }
        .mach-status-warning { background-color: var(--maint-warning); }
        .mach-status-success { background-color: var(--maint-success); }
        .mach-status-unknown { background-color: #94a3b8; }

        .makine-card-header {
            cursor: pointer;
            padding: 1.25rem;
        }

        .card-actions {
            opacity: 0;
            transition: opacity 0.3s;
            background: #f8fafc;
            padding: 0.75rem;
            border-top: 1px solid var(--maint-border);
            display: flex;
            justify-content: space-around;
        }

        .makine-card:hover .card-actions,
        .makine-card:focus-within .card-actions {
            opacity: 1;
        }

        .status-pill {
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .status-normal { background-color: #dcfce7; color: #166534; }
        .status-warning { background-color: #fef9c3; color: #854d0e; }
        .status-danger { background-color: #fee2e2; color: #991b1b; }

        .filter-btn {
            border: 1px solid var(--maint-border);
            background-color: var(--maint-surface);
            color: var(--maint-muted);
            border-radius: 999px;
            padding: 0.5rem 1.25rem;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .filter-btn.active,
        .filter-btn:hover {
            background-color: var(--maint-accent);
            color: #fff;
            border-color: var(--maint-accent);
            box-shadow: 0 4px 12px -4px rgba(2, 132, 199, 0.3);
        }

        /* Form Elemants & Search */
        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input {
            padding-left: 40px;
            border-radius: 999px;
            border: 1px solid var(--maint-border);
            background: #f8fafc;
            padding-top: 0.6rem;
            padding-bottom: 0.6rem;
            transition: all 0.2s;
        }

        .search-box input:focus {
            background: #fff;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
            border-color: #cbd5e1;
        }

        /* Tables Modernization */
        .table-hover tbody tr {
            transition: background-color 0.2s;
        }
        .table-hover tbody tr:hover {
            background-color: #f1f5f9;
        }
        
        #historyTable thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--maint-surface);
            box-shadow: 0 1px 0 var(--maint-border);
            border-bottom: none;
        }
    </style>
</head>

<body class="bg-light">

    <?php include("navbar.php"); ?>

    <div class="container py-4">

        <!-- HERO HEADER -->
        <div class="page-hero">
            <div class="hero-content">
                <h2 class="hero-title"><i class="fas fa-tools me-3 text-info"></i>Makine Bakım Takip</h2>
                <p class="hero-subtitle">
                    Bakım ve Arıza Yönetim Sistemi
                </p>
            </div>
            
            <div class="hero-stats-wrapper flex-wrap">
                <div class="hero-stats-grid">
                    <div class="hero-stat-card danger-stat">
                        <div class="label">Geciken</div>
                        <div class="value"><?php echo $stats['gecikmis']; ?></div>
                    </div>
                    <div class="hero-stat-card warning-stat">
                        <div class="label">7 Gün İçinde</div>
                        <div class="value"><?php echo $stats['yaklasan']; ?></div>
                    </div>
                    <div class="hero-stat-card">
                        <div class="label">Toplam Makine</div>
                        <div class="value"><?php echo $stats['toplam']; ?></div>
                    </div>
                </div>
                
                <div class="hero-actions ms-md-3">
                    <button class="btn btn-light btn-surface px-4 py-2 text-primary" data-bs-toggle="modal" data-bs-target="#yeniMakineModal">
                        <i class="fas fa-plus me-2"></i>Makine Ekle
                    </button>
                    <button class="btn btn-success btn-surface px-4 py-2" data-bs-toggle="modal" data-bs-target="#bakimModal">
                        <i class="fas fa-wrench me-2"></i>Bakım Gir
                    </button>
                </div>
            </div>
        </div>

        <div class="mb-4 text-center text-md-start">
            <ul class="nav nav-pills" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link<?php echo $aktif_tab === 'makine' ? ' active' : ''; ?>" id="pills-makine-tab" data-bs-toggle="pill" data-bs-target="#pills-makine" type="button" role="tab"><i class="fas fa-th-large me-2"></i>Makine Durumları</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link<?php echo $aktif_tab === 'gecmis' ? ' active' : ''; ?>" id="pills-gecmis-tab" data-bs-toggle="pill" data-bs-target="#pills-gecmis" type="button" role="tab"><i class="fas fa-history me-2"></i>Son Bakım İşlemleri & Lab</button>
            </li>
                        <li class="nav-item" role="presentation">
                <button class="nav-link<?php echo $aktif_tab === 'malzeme' ? ' active' : ''; ?>" id="pills-malzeme-tab" data-bs-toggle="pill" data-bs-target="#pills-malzeme" type="button" role="tab"><i class="fas fa-boxes me-2"></i>Malzeme Stok</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link<?php echo $aktif_tab === 'dosyalar' ? ' active' : ''; ?>" id="pills-dosyalar-tab" data-bs-toggle="pill" data-bs-target="#pills-dosyalar" type="button" role="tab"><i class="fas fa-file-pdf me-2"></i>Dosyalarım</button>
            </li>
        </ul>
        </div>

        <div class="tab-content" id="pills-tabContent">
            <!-- TAB 1: MAKİNE DURUMLARI -->
            <div class="tab-pane fade<?php echo $aktif_tab === 'makine' ? ' show active' : ''; ?>" id="pills-makine" role="tabpanel">
                <div class="surface-card mb-4">
                    <div class="surface-header d-flex flex-column gap-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="mb-0 fw-bold">Makine Durumları</h5>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="makineArama" class="form-control form-control-sm"
                                    placeholder="Makine ara...">
                            </div>
                        </div>

                        <!-- Ünite Filtreleri -->
                        <div class="d-flex flex-wrap gap-2" id="uniteFiltreleri">
                            <button class="filter-btn active" data-filter="all">Tümü</button>
                            <button class="filter-btn" data-filter="Ön Temizleme Ünitesi">Ön Temz.</button>
                            <button class="filter-btn" data-filter="Temizleme Ünitesi">Temizleme</button>
                            <button class="filter-btn" data-filter="Aktarma Ünitesi">Aktarma</button>
                            <button class="filter-btn" data-filter="Hazırlık Ünitesi">Hazırlık</button>
                            <button class="filter-btn" data-filter="Atık Ünitesi">Atık</button>
                            <button class="filter-btn" data-filter="Öğütme Ünitesi">Öğütme</button>
                            <button class="filter-btn" data-filter="Un Ünitesi">Un</button>
                            <button class="filter-btn" data-filter="Kepek Ünitesi">Kepek</button>
                            <button class="filter-btn" data-filter="Laboratuvar">Laboratuvar</button>
                        </div>
                    </div>

                    <div class="card-body bg-light p-4">
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4" id="makineGrid">
                            <?php
                            if ($makineler && $makineler->num_rows > 0) {
                                while ($m = $makineler->fetch_assoc()) {
                                    $sonraki = $m['sonraki_bakim_tarihi'];
                                    $bugun = date('Y-m-d');

                                    $durum_class = '';
                                    $durum_badge = 'status-normal';
                                    $durum_text = 'Normal';
                                    $line_class = 'mach-status-success';

                                    if ($sonraki) {
                                        $diff = (strtotime($sonraki) - strtotime($bugun)) / (60 * 60 * 24);

                                        if ($diff < 0) {
                                            $durum_class = 'border-danger';
                                            $durum_badge = 'status-danger';
                                            $durum_text = 'GECİKMİŞ';
                                            $line_class = 'mach-status-danger';
                                        } elseif ($diff <= 7) {
                                            $durum_class = 'border-warning';
                                            $durum_badge = 'status-warning';
                                            $durum_text = 'YAKLAŞIYOR';
                                            $line_class = 'mach-status-warning';
                                        }
                                    } else {
                                        $durum_text = 'Belirsiz';
                                        $durum_badge = 'bg-light text-muted';
                                        $line_class = 'mach-status-unknown';
                                    }

                                    $unite_veri = htmlspecialchars($m['unite_adi'] ?? '');
                                    $ham_arama = $m['makine_adi'] . ' ' . $m['makine_kodu'] . ' ' . $unite_veri . ' ' . ($m['kat_bilgisi'] ?? '') . ' ' . $m['lokasyon'];
                                    $arama_metni = mb_strtolower($ham_arama, 'UTF-8');
                                    ?>
                                    <div class="col makine-kart" data-unite="<?php echo $unite_veri; ?>"
                                        data-search="<?php echo htmlspecialchars($arama_metni); ?>">
                                        <div class="makine-card shadow-sm <?php echo $durum_class; ?>">
                                            <div class="mach-status-line <?php echo $line_class; ?>"></div>
                                            <div class="p-3 flex-grow-1 makine-card-header" onclick="loadMachineHistory(<?php echo $m['id']; ?>, '<?php echo addslashes($m['makine_adi']); ?>')">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-light text-dark border"><i
                                                            class="fas fa-barcode me-1 text-muted"></i><?php echo htmlspecialchars($m['makine_kodu']); ?></span>
                                                    <span
                                                        class="status-pill <?php echo $durum_badge; ?>"><?php echo $durum_text; ?></span>
                                                </div>
                                                <h6 class="fw-bold mb-1 text-truncate"
                                                    title="<?php echo htmlspecialchars($m['makine_adi']); ?>">
                                                    <?php echo htmlspecialchars($m['makine_adi']); ?>
                                                </h6>

                                                <div class="d-flex flex-wrap gap-1 mb-3 mt-2">
                                                    <?php if (!empty($m['unite_adi'])): ?>
                                                        <span
                                                            class="badge bg-primary-subtle text-primary border border-primary-subtle"
                                                            style="font-size:0.7rem; font-weight:500;"><?php echo htmlspecialchars($m['unite_adi']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($m['kat_bilgisi'])): ?>
                                                        <span
                                                            class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"
                                                            style="font-size:0.7rem; font-weight:500;"><?php echo htmlspecialchars($m['kat_bilgisi']); ?></span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="small text-muted mb-1 px-1">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span><i class="far fa-calendar-check me-1"></i>Sonraki:</span>
                                                        <strong
                                                            class="<?php echo ($diff < 0) ? 'text-danger' : (($diff <= 7) ? 'text-warning' : 'text-dark'); ?>">
                                                            <?php echo $sonraki ? date('d.m.Y', strtotime($sonraki)) : '-'; ?>
                                                        </strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span><i class="fas fa-sync-alt me-1"></i>Periyot:</span>
                                                        <span><?php echo $m['bakim_periyodu']; ?> Gün</span>
                                                    </div>
                                                    <div class="text-center mt-2 pt-2 border-top">
                                                        <small class="text-primary"><i class="fas fa-history me-1"></i>Bakım Geçmişini Gör</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="bg-white border-top p-2 d-flex justify-content-around card-actions">
                                                <button class="btn btn-sm btn-outline-success border-0 px-2" title="Bakım Gir"
                                                    onclick="bakimModalAc(<?php echo $m['id']; ?>, '<?php echo addslashes($m['makine_adi']); ?>')">
                                                    <i class="fas fa-wrench"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary border-0 px-2" title="Düzenle"
                                                    onclick="makineDuzenleModalAc(<?php echo htmlspecialchars(json_encode($m)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?sil=<?php echo $m['id']; ?>"
                                                    class="btn btn-sm btn-outline-danger border-0 px-2" title="Makineyi Sil"
                                                    onclick="silmeOnay(event, this.href)">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php }
                            } else {
                                echo "<div class='col-12'><div class='text-center p-5 text-muted w-100'><i class='fas fa-info-circle me-2 mb-3 fs-3 d-block'></i>Henüz makine eklenmemiş.</div></div>";
                            } ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: SON BAKIM İŞLEMLERİ & LAB -->
            <div class="tab-pane fade<?php echo $aktif_tab === 'gecmis' ? ' show active' : ''; ?>" id="pills-gecmis" role="tabpanel">
                <div class="row">
                    <!-- SON BAKIM KAYITLARI -->
                    <div class="col-lg-7">
                        <div class="surface-card mb-4">
                            <div class="surface-header py-3">
                                <h5 class="mb-0 fw-bold">Son Bakım İşlemleri</h5>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php
                                    if ($gecmis && $gecmis->num_rows > 0) {
                                        while ($g = $gecmis->fetch_assoc()) {
                                            ?>
                                            <li class="list-group-item list-group-item-action py-3" style="cursor: pointer;" onclick="loadMachineHistory(<?php echo $g['makine_id']; ?>, '<?php echo addslashes($g['makine_adi']); ?>')">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <strong class="text-dark"><?php echo htmlspecialchars($g['makine_adi']); ?> <small class="text-muted">(<?php echo htmlspecialchars($g['makine_kodu']); ?>)</small></strong>
                                                    <span class="badge bg-soft-info text-info rounded-pill"
                                                        style="font-size: 0.65rem;"><?php echo date('d.m.Y', strtotime($g['bakim_tarihi'])); ?></span>
                                                </div>
                                                <div class="small text-muted mb-2"><?php echo htmlspecialchars($g['yapilan_islem']); ?>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center small">
                                                    <span class="text-primary font-monospace" style="font-size: 0.7rem;"><i
                                                            class="fas fa-user-cog me-1"></i><?php echo htmlspecialchars($g['teknisyen']); ?></span>
                                                    <div class="d-flex gap-1 align-items-center">
                                                        <button class="btn btn-sm btn-light p-1 px-2 border" title="Düzenle" 
                                                            onclick="event.stopPropagation(); bakimKaydiDuzenleModalAc(<?php echo htmlspecialchars(json_encode($g)); ?>)">
                                                            <i class="fas fa-edit text-primary" style="font-size: 0.7rem;"></i>
                                                        </button>
                                                        <span class="badge <?php echo $g['bakim_turu'] == 'Arıza' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'; ?> px-2 py-1"><?php echo $g['bakim_turu']; ?></span>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php }
                                    } else {
                                        echo "<li class='list-group-item text-center p-4 text-muted'>Geçmiş kayıt bulunamadı.</li>";
                                    } ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- LABORATUVAR MALZEMELERİ -->
                    <div class="col-lg-5">
                        <div class="surface-card">
                            <div class="surface-header py-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold"><i class="fas fa-flask text-info me-2"></i>Lab Malzeme Stokları</h5>
                                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#labMalzemeModal">
                                    <i class="fas fa-plus me-1"></i>Ekle
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" style="font-size: 0.85rem;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Malzeme</th>
                                                <th>Stok</th>
                                                <th>Alan</th>
                                                <th class="text-end px-3">İşlem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($lab_malzemeler && $lab_malzemeler->num_rows > 0): ?>
                                                <?php while($lm = $lab_malzemeler->fetch_assoc()): 
                                                    $kritik = $lm['miktar'] <= $lm['kritik_seviye'];
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($lm['malzeme_adi']); ?></strong>
                                                        <?php if($kritik): ?>
                                                            <i class="fas fa-exclamation-circle text-danger ms-1" title="Kritik Seviye!"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="<?php echo $kritik ? 'text-danger fw-bold' : ''; ?>">
                                                        <?php echo $lm['miktar'] . " " . $lm['birim']; ?>
                                                    </td>
                                                    <td class="text-muted"><?php echo htmlspecialchars($lm['kullanim_alani']); ?></td>
                                                    <td class="text-end px-3">
                                                        <a href="?lab_malzeme_sil=<?php echo $lm['id']; ?>" class="text-danger" onclick="return confirm('Silmek istediğinize emin misiniz?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr><td colspan="4" class="text-center py-4 text-muted">Kayıt yok.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: MALZEME STOK -->
            <div class="tab-pane fade<?php echo $aktif_tab === 'malzeme' ? ' show active' : ''; ?>" id="pills-malzeme" role="tabpanel">
                <div class="surface-card">
                    <div class="surface-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-boxes text-info me-2"></i>Malzeme Stok Takibi</h5>
                        <button class="btn btn-primary btn-surface px-4" data-bs-toggle="modal" data-bs-target="#malzemeStokEkleModal">
                            <i class="fas fa-plus me-2"></i>Yeni Malzeme
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="malzemeTable" style="font-size: 0.9rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Malzeme İsmi</th>
                                        <th>Kullanıldığı Alet/Makine</th>
                                        <th>Geliş Tarihi</th>
                                        <th>Kullanım Miktarı</th>
                                        <th class="text-end pe-4">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($malzeme_stoklar && $malzeme_stoklar->num_rows > 0): ?>
                                        <?php while ($m = $malzeme_stoklar->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($m['ismi']); ?></td>
                                                <td class="text-muted"><?php echo htmlspecialchars($m['alet']); ?></td>
                                                <td><?php echo date('d.m.Y', strtotime($m['gelis_tarihi'])); ?></td>
                                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($m['kullanim_miktari']); ?></span></td>
                                                <td class="text-end pe-4">
                                                    <div class="btn-group gap-1">
                                                        <button class="btn btn-sm btn-outline-primary border p-1" title="Düzenle" 
                                                            onclick='malzemeDuzenleModalAc(<?php echo htmlspecialchars(json_encode($m)); ?>)'>
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger border p-1" title="Sil"
                                                            onclick="malzemeStokSil('?malzeme_sil=<?php echo $m['id']; ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">Henüz stok kaydı eklenmemiş.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: DOSYALARIM -->
            <div class="tab-pane fade<?php echo $aktif_tab === 'dosyalar' ? ' show active' : ''; ?>" id="pills-dosyalar" role="tabpanel">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="surface-card">
                            <div class="surface-header py-3">
                                <h5 class="mb-0 fw-bold"><i class="fas fa-upload text-primary me-2"></i>PDF Yukle</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!$pdf_yukleme_aktif): ?>
                                    <div class="alert alert-warning mb-0 small">
                                        <strong>Kurulum Gerekli:</strong> <code>create_bakim_dokumanlari_table.php</code> scriptini bir kez calistirin.
                                    </div>
                                <?php else: ?>
                                    <form method="post" action="?tab=dosyalar" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-muted">PDF Dosyasi</label>
                                            <input type="file" name="pdf_dosya" class="form-control" accept=".pdf,application/pdf" required <?php echo !$pdf_yazma_yetkisi ? 'disabled' : ''; ?>>
                                            <div class="form-text">Maksimum dosya boyutu: 10 MB</div>
                                        </div>
                                        <button type="submit" name="pdf_yukle" class="btn btn-primary w-100" <?php echo !$pdf_yazma_yetkisi ? 'disabled' : ''; ?>>
                                            <i class="fas fa-file-upload me-2"></i>PDF Yukle
                                        </button>
                                        <?php if (!$pdf_yazma_yetkisi): ?>
                                            <div class="text-muted small mt-2">Yukleme icin yazma yetkisi gereklidir.</div>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="surface-card">
                            <div class="surface-header py-3">
                                <h5 class="mb-0 fw-bold"><i class="fas fa-folder-open text-danger me-2"></i>Yuklenen PDF'ler</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table id="pdfDokumanTable" class="table table-hover mb-0" style="font-size: 0.9rem;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>
                                                    <div class="d-inline-flex align-items-center gap-1">
                                                        <span>Dosya Adi</span>
                                                        <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" data-sort-col="name" onclick="sortPdfTable('name')" title="Dosya adina gore sirala">
                                                            <i class="fas fa-sort text-muted pdf-sort-icon"></i>
                                                        </button>
                                                    </div>
                                                </th>
                                                <th>
                                                    <div class="d-inline-flex align-items-center gap-1">
                                                        <span>Boyut</span>
                                                        <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" data-sort-col="size" onclick="sortPdfTable('size')" title="Boyuta gore sirala">
                                                            <i class="fas fa-sort text-muted pdf-sort-icon"></i>
                                                        </button>
                                                    </div>
                                                </th>
                                                <th>
                                                    <div class="d-inline-flex align-items-center gap-1">
                                                        <span>Yukleyen</span>
                                                        <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" data-sort-col="uploader" onclick="sortPdfTable('uploader')" title="Yukleyene gore sirala">
                                                            <i class="fas fa-sort text-muted pdf-sort-icon"></i>
                                                        </button>
                                                    </div>
                                                </th>
                                                <th>
                                                    <div class="d-inline-flex align-items-center gap-1">
                                                        <span>Tarih</span>
                                                        <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" data-sort-col="date" onclick="sortPdfTable('date')" title="Tarihe gore sirala">
                                                            <i class="fas fa-sort text-muted pdf-sort-icon"></i>
                                                        </button>
                                                    </div>
                                                </th>
                                                <th class="text-end pe-3">Islem</th>
                                            </tr>
                                        </thead>
                                        <tbody id="pdfTableBody">
                                            <?php if (!$pdf_yukleme_aktif): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">Dokuman tablosu henuz hazir degil.</td>
                                                </tr>
                                            <?php elseif ($bakim_dokumanlari && $bakim_dokumanlari->num_rows > 0): ?>
                                                <?php while ($pdf = $bakim_dokumanlari->fetch_assoc()):
                                                    $yukleyen = trim((string) ($pdf['tam_ad'] ?: $pdf['kadi']));
                                                    if ($yukleyen === '') {
                                                        $yukleyen = 'Bilinmiyor';
                                                    }
                                                    $sort_name = mb_strtolower((string) $pdf['orijinal_ad'], 'UTF-8');
                                                    $sort_uploader = mb_strtolower((string) $yukleyen, 'UTF-8');
                                                    $sort_size = (int) $pdf['dosya_boyut'];
                                                    $sort_date = strtotime((string) $pdf['yukleme_tarihi']) ?: 0;
                                                ?>
                                                    <tr data-row-type="data"
                                                        data-sort-name="<?php echo htmlspecialchars($sort_name); ?>"
                                                        data-sort-size="<?php echo $sort_size; ?>"
                                                        data-sort-uploader="<?php echo htmlspecialchars($sort_uploader); ?>"
                                                        data-sort-date="<?php echo $sort_date; ?>">
                                                        <td>
                                                            <i class="fas fa-file-pdf text-danger me-2"></i>
                                                            <strong><?php echo htmlspecialchars($pdf['orijinal_ad']); ?></strong>
                                                        </td>
                                                        <td class="text-muted"><?php echo dosyaBoyutuFormatla($pdf['dosya_boyut']); ?></td>
                                                        <td><?php echo htmlspecialchars($yukleyen); ?></td>
                                                        <td class="text-muted"><?php echo date('d.m.Y H:i', strtotime($pdf['yukleme_tarihi'])); ?></td>
                                                        <td class="text-end pe-3">
                                                            <a href="?pdf_indir=<?php echo (int) $pdf['id']; ?>&pdf_mode=inline" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary" title="Yeni sekmede goruntule">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="?pdf_indir=<?php echo (int) $pdf['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-download"></i>
                                                            </a>
                                                            <?php if ($pdf_yazma_yetkisi): ?>
                                                                <a href="?pdf_sil=<?php echo (int) $pdf['id']; ?>&tab=dosyalar" class="btn btn-sm btn-outline-danger" onclick="pdfSilmeOnay(event, this.href)">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">Henuz yuklenmis PDF dosyasi yok.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- YENİ MALZEME STOK EKLE MODAL -->
    <div class="modal fade" id="malzemeStokEkleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
                <div class="modal-header bg-light border-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-box text-primary me-2"></i>Yeni Malzeme Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="?tab=malzeme">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Malzeme İsmi</label>
                            <input type="text" name="malzeme_ismi" class="form-control" placeholder="Örn: Rulman, Kayış..." required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Kullanıldığı Alet / Makine</label>
                            <input type="text" name="malzeme_alet" class="form-control" placeholder="Örn: Piston, Öğütücü..." required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Geliş Tarihi</label>
                            <input type="date" name="malzeme_gelis" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Kullanım Miktarı</label>
                            <input type="text" name="malzeme_kullanim" class="form-control" placeholder="Örn: 5 Adet, 10 Litre" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="malzeme_stok_ekle" class="btn btn-primary px-4 fw-bold"><i class="fas fa-save me-1"></i>Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MALZEME STOK DÜZENLE MODAL -->
    <div class="modal fade" id="malzemeStokDuzenleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
                <div class="modal-header bg-light border-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit text-primary me-2"></i>Malzeme Güncelle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="?tab=malzeme">
                    <div class="modal-body">
                        <input type="hidden" name="malzeme_id" id="edit_malzeme_id">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Malzeme İsmi</label>
                            <input type="text" name="malzeme_ismi" id="edit_malzeme_ismi" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Kullanıldığı Alet / Makine</label>
                            <input type="text" name="malzeme_alet" id="edit_malzeme_alet" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Geliş Tarihi</label>
                            <input type="date" name="malzeme_gelis" id="edit_malzeme_gelis" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Kullanım Miktarı</label>
                            <input type="text" name="malzeme_kullanim" id="edit_malzeme_kullanim" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="malzeme_stok_guncelle" class="btn btn-primary px-4 fw-bold"><i class="fas fa-save me-1"></i>Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- YENİ MAKİNE MODAL -->
    <div class="modal fade" id="yeniMakineModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form method="post">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">Yeni Makine Ekle</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Makine Kodu</label>
                            <input type="text" name="makine_kodu" class="form-control border-0 bg-light"
                                placeholder="Örn: PAK-01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Makine Adı</label>
                            <input type="text" name="makine_adi" class="form-control border-0 bg-light"
                                placeholder="Örn: Paketleme Hattı 1" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Ünite</label>
                                <select name="unite_adi" class="form-select border-0 bg-light" required>
                                    <option value="">Seçiniz...</option>
                                    <option value="Ön Temizleme Ünitesi">Ön Temizleme Ünitesi</option>
                                    <option value="Temizleme Ünitesi">Temizleme Ünitesi</option>
                                    <option value="Aktarma Ünitesi">Aktarma Ünitesi</option>
                                    <option value="Hazırlık Ünitesi">Hazırlık Ünitesi</option>
                                    <option value="Atık Ünitesi">Atık Ünitesi</option>
                                    <option value="Öğütme Ünitesi">Öğütme Ünitesi</option>
                                    <option value="Un Ünitesi">Un Ünitesi</option>
                                    <option value="Kepek Ünitesi">Kepek Ünitesi</option>
                                    <option value="Laboratuvar">Laboratuvar</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Kat Bilgisi</label>
                                <select name="kat_bilgisi" class="form-select border-0 bg-light" required>
                                    <option value="">Seçiniz...</option>
                                    <option value="Yer Altı">Yer Altı</option>
                                    <option value="Zemin">Zemin Kat</option>
                                    <option value="1. Kat">1. Kat</option>
                                    <option value="2. Kat">2. Kat</option>
                                    <option value="3. Kat">3. Kat</option>
                                    <option value="4. Kat">4. Kat</option>
                                    <option value="5. Kat">5. Kat</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Detay Lokasyon <small
                                        class="text-primary">(Opsiyonel)</small></label>
                                <input type="text" name="lokasyon" class="form-control border-0 bg-light"
                                    placeholder="Örn: Blower Odası, Motor Yanı vb.">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Bakım Periyodu (Gün)</label>
                                <input type="number" name="bakim_periyodu" class="form-control border-0 bg-light"
                                    value="30" min="1" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Son Bakım Tarihi <small
                                    class="text-primary">(Opsiyonel)</small></label>
                            <input type="date" name="son_bakim_tarihi" class="form-control border-0 bg-light">
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="makine_ekle" class="btn btn-primary px-4">Makineyi Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MAKİNE DÜZENLE MODAL -->
    <div class="modal fade" id="duzenleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form method="post">
                    <input type="hidden" name="makine_id" id="edit_id">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold">Makineyi Düzenle</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Makine Kodu</label>
                            <input type="text" name="makine_kodu" id="edit_kod" class="form-control border-0 bg-light"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Makine Adı</label>
                            <input type="text" name="makine_adi" id="edit_ad" class="form-control border-0 bg-light"
                                required>
                        </div>
                        <div class="row g-3">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Ünite</label>
                                <select name="unite_adi" id="edit_unite" class="form-select border-0 bg-light" required>
                                    <option value="">Seçiniz...</option>
                                    <option value="Ön Temizleme Ünitesi">Ön Temizleme Ünitesi</option>
                                    <option value="Temizleme Ünitesi">Temizleme Ünitesi</option>
                                    <option value="Aktarma Ünitesi">Aktarma Ünitesi</option>
                                    <option value="Hazırlık Ünitesi">Hazırlık Ünitesi</option>
                                    <option value="Atık Ünitesi">Atık Ünitesi</option>
                                    <option value="Öğütme Ünitesi">Öğütme Ünitesi</option>
                                    <option value="Un Ünitesi">Un Ünitesi</option>
                                    <option value="Kepek Ünitesi">Kepek Ünitesi</option>
                                    <option value="Laboratuvar">Laboratuvar</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Kat Bilgisi</label>
                                <select name="kat_bilgisi" id="edit_kat" class="form-select border-0 bg-light" required>
                                    <option value="">Seçiniz...</option>
                                    <option value="Yer Altı">Yer Altı</option>
                                    <option value="Zemin">Zemin Kat</option>
                                    <option value="1. Kat">1. Kat</option>
                                    <option value="2. Kat">2. Kat</option>
                                    <option value="3. Kat">3. Kat</option>
                                    <option value="4. Kat">4. Kat</option>
                                    <option value="5. Kat">5. Kat</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Detay Lokasyon <small
                                        class="text-primary">(Opsiyonel)</small></label>
                                <input type="text" name="lokasyon" id="edit_lokasyon"
                                    class="form-control border-0 bg-light">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Bakım Periyodu (Gün)</label>
                                <input type="number" name="bakim_periyodu" id="edit_periyot"
                                    class="form-control border-0 bg-light" min="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="makine_guncelle" class="btn btn-dark px-4">Değişiklikleri
                            Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- BAKIM GİRİŞ MODAL -->
    <div class="modal fade" id="bakimModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form method="post">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title fw-bold"><i class="fas fa-wrench me-2"></i>Yeni Bakım Girişi</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Makine Seçimi</label>
                            <!-- eklenti Select2 tarafından class="form-select" yerine kullanılacak şekilde tasarlandı -->
                            <select name="makine_id" id="modalMakineSelect" class="form-select border-0 bg-light"
                                required style="width: 100%;">
                                <option value="">Makine ara ve seç...</option>
                                <?php
                                $makineler->data_seek(0);
                                while ($m = $makineler->fetch_assoc()) {
                                    $unite_kat_metni = !empty($m['unite_adi']) ? " - " . $m['unite_adi'] : "";
                                    echo "<option value='{$m['id']}'>{$m['makine_adi']} ({$m['makine_kodu']}){$unite_kat_metni}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Bakım Tarihi</label>
                                <input type="date" name="bakim_tarihi" class="form-control border-0 bg-light"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Bakım Türü</label>
                                <select name="bakim_turu" class="form-select border-0 bg-light">
                                    <option value="Periyodik">Periyodik Bakım</option>
                                    <option value="Arıza">Arıza Onarım</option>
                                    <option value="Kontrol">Genel Kontrol</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Yapılan İşlemler</label>
                            <textarea name="yapilan_islem" class="form-control border-0 bg-light" rows="3"
                                placeholder="Parça değişimi, yağlama vb." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Sorumlu Teknisyen</label>
                            <input type="text" name="teknisyen" class="form-control border-0 bg-light"
                                value="<?php echo $_SESSION['kadi']; ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="bakim_ekle" class="btn btn-success px-4">Kaydı Tamamla</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- LAB MALZEME EKLEME MODAL -->
    <div class="modal fade" id="labMalzemeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form method="post">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title fw-bold">Lab Malzemesi Ekle</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Malzeme Adı</label>
                            <input type="text" name="malzeme_adi" class="form-control border-0 bg-light"
                                placeholder="Örn: Sülfürik Asit" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Miktar</label>
                                <input type="number" step="0.01" name="miktar" class="form-control border-0 bg-light"
                                    required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Birim</label>
                                <select name="birim" class="form-select border-0 bg-light">
                                    <option value="Litre">Litre</option>
                                    <option value="Kg">Kg</option>
                                    <option value="Gram">Gram</option>
                                    <option value="Adet">Adet</option>
                                    <option value="Paket">Paket</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Kullanım Alanı</label>
                            <input type="text" name="kullanim_alani" class="form-control border-0 bg-light"
                                placeholder="Örn: Protein Analizi">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Kritik Stok Seviyesi</label>
                            <input type="number" step="0.01" name="kritik_seviye" class="form-control border-0 bg-light"
                                value="1.00">
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="lab_malzeme_ekle"
                            class="btn btn-info text-white px-4">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MAKİNE GEÇMİŞİ MODAL -->
    <div class="modal fade" id="machineHistoryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="historyModalTitle">Makine Bakım Geçmişi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="historyLoading" class="text-center p-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Yükleniyor...</span>
                        </div>
                        <p class="mt-2 text-muted">Kayıtlar getiriliyor...</p>
                    </div>
                    <div id="historyEmpty" class="text-center p-5 d-none">
                        <i class="fas fa-info-circle fs-2 text-muted mb-3"></i>
                        <p class="text-muted mb-0">Bu makineye ait henüz bakım kaydı bulunamadı.</p>
                    </div>
                    <div id="historyTableContainer" class="d-none">
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-hover mb-0" id="historyTable">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Tarih</th>
                                        <th>Tür</th>
                                        <th>Yapılan İşlem</th>
                                        <th>Teknisyen</th>
                                        <th class="text-end pe-4">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody id="historyTableBody">
                                    <!-- AJAX ile dolacak -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- BAKIM KAYDI DÜZENLEME MODAL -->
    <div class="modal fade" id="bakimKaydiDuzenleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form method="post">
                    <input type="hidden" name="kayit_id" id="edit_kayit_id">
                    <input type="hidden" name="makine_id" id="edit_kayit_makine_id">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">Bakım Kaydını Düzenle</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Bakım Tarihi</label>
                                <input type="date" name="bakim_tarihi" id="edit_kayit_tarih" class="form-control border-0 bg-light" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Bakım Türü</label>
                                <select name="bakim_turu" id="edit_kayit_turu" class="form-select border-0 bg-light">
                                    <option value="Periyodik">Periyodik Bakım</option>
                                    <option value="Arıza">Arıza Onarım</option>
                                    <option value="Kontrol">Genel Kontrol</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Yapılan İşlemler</label>
                            <textarea name="yapilan_islem" id="edit_kayit_islem" class="form-control border-0 bg-light" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Sorumlu Teknisyen</label>
                            <input type="text" name="teknisyen" id="edit_kayit_teknisyen" class="form-control border-0 bg-light" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-danger px-3" onclick="bakimKaydiSil()">
                            <i class="fas fa-trash-alt me-1"></i>Sil
                        </button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" name="bakim_guncelle" class="btn btn-primary px-4">Kaydet</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
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

            initPdfTableSortUi();
        });

        function silmeOnay(e, url) {
            e.preventDefault();
            Swal.fire({
                title: 'Emin misiniz?',
                text: 'Bu makine ve ona ait TÜM BAKIM GEÇMİŞİ kalıcı olarak silinecektir. Bu işlem geri alınamaz!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Kalıcı Olarak Sil',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        function pdfSilmeOnay(e, url) {
            e.preventDefault();
            Swal.fire({
                title: 'Emin misiniz?',
                text: 'Bu PDF dosyasi kalici olarak silinecektir.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'Iptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        const pdfSortState = {
            col: 'date',
            dir: 'desc'
        };

        function initPdfTableSortUi() {
            const tbody = document.getElementById('pdfTableBody');
            if (!tbody) return;
            updatePdfSortIcons();
        }

        function getPdfSortValue(row, col) {
            if (col === 'size') {
                return parseInt(row.getAttribute('data-sort-size') || '0', 10);
            }
            if (col === 'date') {
                return parseInt(row.getAttribute('data-sort-date') || '0', 10);
            }
            if (col === 'name') {
                return (row.getAttribute('data-sort-name') || '').toLocaleLowerCase('tr-TR');
            }
            if (col === 'uploader') {
                return (row.getAttribute('data-sort-uploader') || '').toLocaleLowerCase('tr-TR');
            }
            return '';
        }

        function sortPdfTable(col) {
            const tbody = document.getElementById('pdfTableBody');
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll('tr[data-row-type="data"]'));
            if (rows.length === 0) return;

            if (pdfSortState.col === col) {
                pdfSortState.dir = pdfSortState.dir === 'asc' ? 'desc' : 'asc';
            } else {
                pdfSortState.col = col;
                pdfSortState.dir = col === 'date' ? 'desc' : 'asc';
            }

            const direction = pdfSortState.dir === 'asc' ? 1 : -1;

            rows.sort((a, b) => {
                const va = getPdfSortValue(a, col);
                const vb = getPdfSortValue(b, col);

                if (typeof va === 'number' && typeof vb === 'number') {
                    return (va - vb) * direction;
                }
                return String(va).localeCompare(String(vb), 'tr', { sensitivity: 'base' }) * direction;
            });

            rows.forEach(row => tbody.appendChild(row));
            updatePdfSortIcons();
        }

        function updatePdfSortIcons() {
            const buttons = document.querySelectorAll('#pdfDokumanTable [data-sort-col]');
            buttons.forEach(btn => {
                const icon = btn.querySelector('.pdf-sort-icon');
                if (!icon) return;
                icon.className = 'fas fa-sort text-muted pdf-sort-icon';
            });

            const active = document.querySelector(`#pdfDokumanTable [data-sort-col="${pdfSortState.col}"] .pdf-sort-icon`);
            if (!active) return;
            active.className = `fas ${pdfSortState.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down'} text-primary pdf-sort-icon`;
        }

        // Makine Arama ve Filtreleme
        const filterBtns = document.querySelectorAll('.filter-btn');
        let aktifUnite = 'all';

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                aktifUnite = this.getAttribute('data-filter');
                filtreleMakineler();
            });
        });

        document.getElementById('makineArama').addEventListener('keyup', filtreleMakineler);

        function filtreleMakineler() {
            let searchVal = document.getElementById('makineArama').value.toLocaleLowerCase('tr-TR');
            let kartlar = document.querySelectorAll('.makine-kart');

            kartlar.forEach(kart => {
                let searchData = kart.getAttribute('data-search');
                // Eğer data-search attribütü javascript ile oluşturulsaydı toLocaleLowerCase uygulardık,
                // ama PHP kodunda mb_strtolower kullandığımız için JS tarafında da Türkçe küçük harf eşleşmesini tam yapıyoruz.
                let uniteData = kart.getAttribute('data-unite');

                let textMatch = searchData.includes(searchVal);
                let uniteMatch = (aktifUnite === 'all') || (uniteData === aktifUnite);

                if (textMatch && uniteMatch) {
                    kart.style.display = '';
                } else {
                    kart.style.display = 'none';
                }
            });
        }

        // Select2 Kurulumu (Modal İçinde Çalışması İçin Özelleştirilmiş)
        $(document).ready(function () {
            $('#modalMakineSelect').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#bakimModal'), // Modalın arkasına düşmemesi veya odağın (focus) kaybolmaması için.
                placeholder: 'Yazarak makine arayın...',
                language: {
                    noResults: function () {
                        return "Makine bulunamadı.";
                    }
                }
            });
        });


        function malzemeDuzenleModalAc(data) {
            document.getElementById('edit_malzeme_id').value = data.id;
            document.getElementById('edit_malzeme_ismi').value = data.ismi;
            document.getElementById('edit_malzeme_alet').value = data.alet;
            document.getElementById('edit_malzeme_gelis').value = data.gelis_tarihi;
            document.getElementById('edit_malzeme_kullanim').value = data.kullanim_miktari;
            const modal = new bootstrap.Modal(document.getElementById('malzemeStokDuzenleModal'));
            modal.show();
        }

        function malzemeStokSil(url) {
            Swal.fire({
                title: 'Emin misiniz?',
                text: 'Bu malzeme kaydı kalıcı olarak silinecektir.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        function bakimModalAc(id, ad) {
            // Dropdown değerini güncelle ve select2'nin algılaması için trigger("change") tetikle.
            $('#modalMakineSelect').val(id).trigger('change');
            const modal = new bootstrap.Modal(document.getElementById('bakimModal'));
            modal.show();
        }

        function makineDuzenleModalAc(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_kod').value = data.makine_kodu;
            document.getElementById('edit_ad').value = data.makine_adi;
            document.getElementById('edit_unite').value = data.unite_adi || '';
            document.getElementById('edit_kat').value = data.kat_bilgisi || '';
            document.getElementById('edit_lokasyon').value = data.lokasyon || '';
            document.getElementById('edit_periyot').value = data.bakim_periyodu;
            const modal = new bootstrap.Modal(document.getElementById('duzenleModal'));
            modal.show();
        }

        function loadMachineHistory(id, name) {
            const modal = new bootstrap.Modal(document.getElementById('machineHistoryModal'));
            document.getElementById('historyModalTitle').innerText = name + " - Bakım Geçmişi";
            
            // UI Reset
            $('#historyLoading').removeClass('d-none');
            $('#historyEmpty').addClass('d-none');
            $('#historyTableContainer').addClass('d-none');
            $('#historyTableBody').empty();

            modal.show();

            $.getJSON('bakim.php', { get_history: 1, makine_id: id }, function(data) {
                $('#historyLoading').addClass('d-none');
                
                if (data.length === 0) {
                    $('#historyEmpty').removeClass('d-none');
                } else {
                    $('#historyTableContainer').removeClass('d-none');
                    data.forEach(function(item) {
                        let row = `<tr>
                            <td class="ps-4"><strong>${item.bakim_tarihi_fmt}</strong></td>
                            <td><span class="badge ${item.bakim_turu === 'Arıza' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'}">${item.bakim_turu}</span></td>
                            <td style="font-size: 0.85rem;">${item.yapilan_islem}</td>
                            <td class="text-muted" style="font-size: 0.8rem;">${item.teknisyen}</td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary border-0 p-1" title="Düzenle" 
                                    onclick='bakimKaydiDuzenleModalAc(${JSON.stringify(item)})'>
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>`;
                        $('#historyTableBody').append(row);
                    });
                }
            }).fail(function() {
                $('#historyLoading').html('<div class="text-danger"><i class="fas fa-exclamation-circle me-2"></i>Veriler alınırken bir hata oluştu.</div>');
            });
        }

        function bakimKaydiDuzenleModalAc(data) {
            document.getElementById('edit_kayit_id').value = data.id;
            document.getElementById('edit_kayit_makine_id').value = data.makine_id;
            document.getElementById('edit_kayit_tarih').value = data.bakim_tarihi;
            document.getElementById('edit_kayit_turu').value = data.bakim_turu;
            document.getElementById('edit_kayit_islem').value = data.yapilan_islem;
            document.getElementById('edit_kayit_teknisyen').value = data.teknisyen;
            
            const modal = new bootstrap.Modal(document.getElementById('bakimKaydiDuzenleModal'));
            modal.show();
        }

        function bakimKaydiSil() {
            const id = document.getElementById('edit_kayit_id').value;
            Swal.fire({
                title: 'Emin misiniz?',
                text: 'Bu bakım kaydı kalıcı olarak silinecektir.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `bakim.php?bakim_sil=${id}`;
                }
            });
        }
    </script>

    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>
