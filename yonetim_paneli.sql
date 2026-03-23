-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 23 Mar 2026, 11:05:52
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `yonetim_paneli`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `aktarma_kayitlari`
--

CREATE TABLE `aktarma_kayitlari` (
  `id` int(11) NOT NULL,
  `parti_no` varchar(50) NOT NULL,
  `yikama_id` int(11) DEFAULT NULL COMMENT 'FK → yikama_kayitlari',
  `aktarma_tarihi` datetime DEFAULT current_timestamp(),
  `nem` decimal(5,2) DEFAULT NULL,
  `verilen_su_litre` decimal(10,2) DEFAULT NULL,
  `personel` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ambalajlar`
--

CREATE TABLE `ambalajlar` (
  `id` int(11) NOT NULL,
  `ambalaj_adi` varchar(100) NOT NULL,
  `ambalaj_kodu` varchar(50) DEFAULT NULL,
  `ambalaj_turu` varchar(50) DEFAULT NULL,
  `kapasite` varchar(50) DEFAULT NULL,
  `birim` varchar(20) DEFAULT 'adet',
  `aktif` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ambalaj_partileri`
--

CREATE TABLE `ambalaj_partileri` (
  `id` int(11) NOT NULL,
  `ambalaj_id` int(11) NOT NULL,
  `parti_no` varchar(50) NOT NULL,
  `tedarikci` varchar(100) DEFAULT NULL,
  `giris_tarihi` date DEFAULT NULL,
  `giris_miktari` int(11) DEFAULT NULL,
  `kalan_miktar` int(11) DEFAULT NULL,
  `birim_fiyat` decimal(10,2) DEFAULT NULL,
  `kayit_tarihi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ambalaj_testleri`
--

CREATE TABLE `ambalaj_testleri` (
  `id` int(11) NOT NULL,
  `ambalaj_parti_id` int(11) NOT NULL,
  `test_turu` varchar(100) DEFAULT NULL COMMENT 'gidaya_uygunluk, migrasyon, mukavemet',
  `test_tarihi` date DEFAULT NULL,
  `sonuc` varchar(20) DEFAULT NULL COMMENT 'uygun, uygun_degil',
  `test_rapor_path` varchar(255) DEFAULT NULL,
  `aciklama` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `b1_degirmen_kayitlari`
--

CREATE TABLE `b1_degirmen_kayitlari` (
  `id` int(11) NOT NULL,
  `parti_no` varchar(50) NOT NULL,
  `aktarma_id` int(11) DEFAULT NULL COMMENT 'FK → aktarma_kayitlari',
  `uretim_tarihi` datetime DEFAULT current_timestamp(),
  `hektolitre` decimal(6,2) DEFAULT NULL,
  `nem` decimal(5,2) DEFAULT NULL,
  `protein` decimal(5,2) DEFAULT NULL,
  `sertlik` decimal(5,2) DEFAULT NULL,
  `nisasta` decimal(5,2) DEFAULT NULL,
  `verilen_su_litre` decimal(10,2) DEFAULT NULL,
  `personel` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `bakim_kayitlari`
--

CREATE TABLE `bakim_kayitlari` (
  `id` int(11) NOT NULL,
  `makine_id` int(11) NOT NULL,
  `bakim_tarihi` date NOT NULL,
  `bakim_turu` varchar(50) DEFAULT NULL,
  `yapilan_islem` text DEFAULT NULL,
  `sonraki_bakim` date DEFAULT NULL,
  `teknisyen` varchar(50) DEFAULT NULL,
  `notlar` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `bakim_lab_malzemeler`
--

CREATE TABLE `bakim_lab_malzemeler` (
  `id` int(11) NOT NULL,
  `malzeme_adi` varchar(255) NOT NULL,
  `miktar` decimal(10,2) DEFAULT 0.00,
  `birim` varchar(50) DEFAULT NULL,
  `kullanim_alani` varchar(255) DEFAULT NULL,
  `kritik_seviye` decimal(10,2) DEFAULT 0.00,
  `tarih` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `bildirimler`
--

CREATE TABLE `bildirimler` (
  `id` int(11) NOT NULL,
  `bildirim_tipi` varchar(50) NOT NULL COMMENT 'arac_geldi, numune_alindi, analiz_tamamlandi, onay_bekleniyor, onaylandi, reddedildi, kantar_bekleniyor',
  `referans_tablo` varchar(50) DEFAULT NULL,
  `referans_id` int(11) DEFAULT NULL,
  `hedef_rol_id` int(11) DEFAULT NULL COMMENT 'Hangi role gidecek (NULL = herkese)',
  `hedef_user_id` int(11) DEFAULT NULL COMMENT 'Spesifik kullanıcı (NULL = role göre)',
  `baslik` varchar(200) NOT NULL,
  `aciklama` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL COMMENT 'Tıklandığında gidilecek sayfa',
  `okundu` tinyint(1) DEFAULT 0,
  `olusturan_user_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `okunma_tarihi` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `bildirimler`
--

INSERT INTO `bildirimler` (`id`, `bildirim_tipi`, `referans_tablo`, `referans_id`, `hedef_rol_id`, `hedef_user_id`, `baslik`, `aciklama`, `link`, `okundu`, `olusturan_user_id`, `olusturma_tarihi`, `okunma_tarihi`) VALUES
(1, 'arac_geldi', 'hammadde_girisleri', 1, 5, NULL, 'Yeni Araç Geldi: 272TEST27', 'Tedarikçi: X FİRMASI | Hammadde: ZENİT | 0 kg', 'lab_analizleri.php', 0, 1, '2026-02-24 15:03:19', NULL),
(2, 'arac_geldi', 'hammadde_girisleri', 1, 1, NULL, 'Yeni Araç Geldi: 272TEST27', 'Tedarikçi: X FİRMASI | Hammadde: ZENİT | 0 kg', 'hammadde.php', 1, 1, '2026-02-24 15:03:19', '2026-02-24 15:04:24'),
(3, 'analiz_tamamlandi', 'lab_analizleri', 1, 1, NULL, 'Lab Analizi Tamamlandi: D6-00001', 'Protein: 12.5% | Gluten: 23% | Kul: 0.2% | Laborant: admin', 'lab_analizleri.php', 1, 1, '2026-02-24 15:04:14', '2026-02-24 15:04:23'),
(4, 'arac_geldi', 'hammadde_girisleri', 2, 5, NULL, 'Yeni Araç Geldi: 34EA2525', 'Tedarikçi: SADAS | Hammadde: VBURGAZ | 0 kg', 'lab_analizleri.php', 0, 1, '2026-02-24 15:43:12', NULL),
(5, 'arac_geldi', 'hammadde_girisleri', 2, 1, NULL, 'Yeni Araç Geldi: 34EA2525', 'Tedarikçi: SADAS | Hammadde: VBURGAZ | 0 kg', 'hammadde.php', 1, 1, '2026-02-24 15:43:12', '2026-02-24 15:43:50'),
(6, 'analiz_tamamlandi', 'lab_analizleri', 2, 1, NULL, 'Lab Analizi Tamamlandi: D6-0002', 'Protein: 21% | Gluten: 2% | Kul: 0.1% | Laborant: admin', 'lab_analizleri.php', 1, 1, '2026-02-24 15:43:36', '2026-02-24 15:43:50'),
(7, 'arac_geldi', 'hammadde_girisleri', 3, 5, NULL, 'Yeni Araç Geldi: 232', 'Tedarikçi: ddfs | Hammadde: PANDAS | 0 kg', 'lab_analizleri.php', 0, 1, '2026-02-24 16:15:53', NULL),
(8, 'arac_geldi', 'hammadde_girisleri', 3, 1, NULL, 'Yeni Araç Geldi: 232', 'Tedarikçi: ddfs | Hammadde: PANDAS | 0 kg', 'hammadde.php', 1, 1, '2026-02-24 16:15:53', '2026-02-24 16:17:01'),
(9, 'arac_geldi', 'hammadde_girisleri', 4, 5, NULL, 'Yeni Araç Geldi: SDASD', 'Tedarikçi: 23 | Hammadde: PANDAS | 0 kg', 'lab_analizleri.php', 0, 1, '2026-02-24 16:16:20', NULL),
(10, 'arac_geldi', 'hammadde_girisleri', 4, 1, NULL, 'Yeni Araç Geldi: SDASD', 'Tedarikçi: 23 | Hammadde: PANDAS | 0 kg', 'hammadde.php', 1, 1, '2026-02-24 16:16:20', '2026-02-24 16:17:01'),
(11, 'yeni_sikayet', 'sikayetler', 1, 1, NULL, 'Yeni Şikayet / DÖF: DOF-2026-001', 'Müşteri: TEST | Konu: asddsa', 'sikayetler.php', 1, 1, '2026-02-26 12:18:06', '2026-02-26 12:18:14'),
(12, 'yeni_sikayet', 'sikayetler', 2, 1, NULL, 'Yeni Şikayet / DÖF: DOF-2026-002', 'Müşteri: asdas | Konu: saddas', 'sikayetler.php', 1, 1, '2026-02-26 13:45:51', '2026-02-26 13:46:02'),
(13, 'genel', NULL, NULL, NULL, 2, 'deneme', 'saddsasda', NULL, 0, 1, '2026-02-27 14:30:02', NULL),
(14, 'analiz_tamamlandi', 'lab_analizleri', 3, 1, NULL, 'Lab Analizi Tamamlandi: K2-0003', 'Protein: -% | Gluten: -% | Laborant: admin', 'lab_analizleri.php', 0, 1, '2026-03-12 11:53:31', NULL),
(15, 'analiz_tamamlandi', 'lab_analizleri', 4, 1, NULL, 'Lab Analizi Tamamlandi: K2-0001', 'Protein: -% | Gluten: -% | Laborant: admin', 'lab_analizleri.php', 0, 1, '2026-03-12 11:59:44', NULL),
(16, 'arac_geldi', 'hammadde_girisleri', 5, 5, NULL, 'Yeni Araç Geldi: ASDASDSAD', 'Tedarikçi: sadsaads | Hammadde: İTHAL | 0 kg', 'lab_analizleri.php', 0, 1, '2026-03-12 12:00:56', NULL),
(17, 'arac_geldi', 'hammadde_girisleri', 5, 1, NULL, 'Yeni Araç Geldi: ASDASDSAD', 'Tedarikçi: sadsaads | Hammadde: İTHAL | 0 kg', 'hammadde.php', 0, 1, '2026-03-12 12:00:56', NULL),
(18, 'analiz_tamamlandi', 'lab_analizleri', 5, 1, NULL, 'Lab Analizi Tamamlandi: D10-0001', 'Protein: -% | Gluten: -% | Laborant: admin', 'lab_analizleri.php', 0, 1, '2026-03-12 12:01:09', NULL),
(19, 'arac_geldi', 'hammadde_girisleri', 6, 5, NULL, 'Yeni Araç Geldi: ASDDSA', 'Tedarikçi: testtttt | Hammadde: SEGOTORİA | 0 kg', 'lab_analizleri.php', 0, 1, '2026-03-12 12:55:24', NULL),
(20, 'arac_geldi', 'hammadde_girisleri', 6, 1, NULL, 'Yeni Araç Geldi: ASDDSA', 'Tedarikçi: testtttt | Hammadde: SEGOTORİA | 0 kg', 'hammadde.php', 0, 1, '2026-03-12 12:55:24', NULL),
(21, 'analiz_tamamlandi', 'lab_analizleri', 6, 1, NULL, 'Lab Analizi Tamamlandi: K1-0001', 'Protein: -% | Gluten: -% | Laborant: admin', 'lab_analizleri.php', 0, 1, '2026-03-12 12:55:35', NULL),
(22, 'arac_geldi', 'hammadde_girisleri', 7, 5, NULL, 'Yeni Araç Geldi: 27 TEST 27', 'Tedarikçi: Deneme | Hammadde: VBURGAZ | 0 kg', 'lab_analizleri.php', 0, 1, '2026-03-12 12:58:43', NULL),
(23, 'arac_geldi', 'hammadde_girisleri', 7, 1, NULL, 'Yeni Araç Geldi: 27 TEST 27', 'Tedarikçi: Deneme | Hammadde: VBURGAZ | 0 kg', 'hammadde.php', 0, 1, '2026-03-12 12:58:43', NULL),
(24, 'analiz_tamamlandi', 'lab_analizleri', 7, 1, NULL, 'Lab Analizi Tamamlandi: D1-0001', 'Protein: -% | Gluten: -% | Laborant: admin', 'lab_analizleri.php', 0, 1, '2026-03-12 12:58:59', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ccp_kayitlari`
--

CREATE TABLE `ccp_kayitlari` (
  `id` int(11) NOT NULL,
  `parti_no` varchar(50) DEFAULT NULL,
  `ccp_no` int(11) NOT NULL COMMENT '1, 2, 3...',
  `ccp_adi` varchar(100) NOT NULL,
  `kontrol_parametre` varchar(50) DEFAULT NULL,
  `deger` decimal(10,2) DEFAULT NULL,
  `kritik_limit` decimal(10,2) DEFAULT NULL,
  `sonuc` varchar(20) DEFAULT NULL COMMENT 'gecti, gecmedi',
  `duzeltici_faaliyet` text DEFAULT NULL,
  `kontrol_zamani` datetime DEFAULT current_timestamp(),
  `kontrol_eden` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `depolar`
--

CREATE TABLE `depolar` (
  `id` int(11) NOT NULL,
  `depo_adi` varchar(100) NOT NULL,
  `depo_kodu` varchar(50) DEFAULT NULL,
  `kapasite_ton` decimal(10,2) DEFAULT NULL,
  `min_sicaklik` decimal(5,2) DEFAULT NULL,
  `max_sicaklik` decimal(5,2) DEFAULT NULL,
  `min_nem` decimal(5,2) DEFAULT NULL,
  `max_nem` decimal(5,2) DEFAULT NULL,
  `aktif` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `depo_sicaklik_nem`
--

CREATE TABLE `depo_sicaklik_nem` (
  `id` int(11) NOT NULL,
  `depo_id` int(11) NOT NULL,
  `olcum_tarihi` datetime DEFAULT current_timestamp(),
  `sicaklik` decimal(5,2) DEFAULT NULL,
  `nem` decimal(5,2) DEFAULT NULL,
  `olcum_yapan` varchar(50) DEFAULT NULL,
  `uyari` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `depo_stok`
--

CREATE TABLE `depo_stok` (
  `id` int(11) NOT NULL,
  `urun_id` int(11) DEFAULT NULL,
  `depo_id` int(11) DEFAULT NULL,
  `miktar` decimal(10,2) DEFAULT NULL,
  `son_guncelleme` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `gunluk_kontroller`
--

CREATE TABLE `gunluk_kontroller` (
  `id` int(11) NOT NULL,
  `tarih` date DEFAULT NULL,
  `kontrol_turu` varchar(50) DEFAULT NULL,
  `sonuc` varchar(20) DEFAULT NULL,
  `kontrol_eden` varchar(50) DEFAULT NULL,
  `notlar` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `hammaddeler`
--

CREATE TABLE `hammaddeler` (
  `id` int(11) NOT NULL,
  `ad` varchar(100) NOT NULL,
  `hammadde_kodu` varchar(50) DEFAULT NULL,
  `yogunluk_kg_m3` decimal(6,2) DEFAULT 780.00,
  `aciklama` text DEFAULT NULL,
  `aktif` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `hammaddeler`
--

INSERT INTO `hammaddeler` (`id`, `ad`, `hammadde_kodu`, `yogunluk_kg_m3`, `aciklama`, `aktif`) VALUES
(1, 'VBURGAZ', 'D1', 0.00, '', 1),
(2, 'ZIVEGO', 'D2', 0.00, '', 1),
(3, 'OVIDIO', 'D3', 780.00, '', 1),
(4, 'SEZAR', 'D4', 780.00, '', 1),
(5, 'MAESTRAL', 'D5', 780.00, '', 1),
(6, 'ZENİT', 'D6', 0.00, '', 1),
(7, 'KUNDURU', 'D7', 0.00, '', 1),
(8, 'HATAY 85', 'D8', 780.00, '', 1),
(9, 'D.SERT', 'D9', 0.00, '', 1),
(10, 'İTHAL', 'D10', 0.00, '', 1),
(11, 'KARIŞIK', 'D', 780.00, '', 1),
(12, 'TİREX', 'D11', 780.00, '', 1),
(13, 'SEGOTORİA', 'K1', 0.00, '', 1),
(14, 'PANDAS', 'K2', 780.00, '', 1),
(15, 'EXPERİA', 'K3', 0.00, '', 1),
(16, 'BEZOSTA', 'K4', 0.00, '', 1),
(17, 'PEHLİVAN', 'K5', 0.00, '', 1),
(18, 'MASSACİO', 'K6', 0.00, '', 1),
(19, 'ADELE', 'K7', 0.00, '', 1),
(20, 'GOLİA', 'K8', 0.00, '', 1),
(21, 'İTHAL', 'K9', 0.00, '', 1),
(22, 'GOFRETLİK-KARIŞIK', 'K10', 0.00, '', 1),
(23, 'ODESKA', 'K11', 0.00, '', 1),
(24, 'LUCİLLA', 'K12', 0.00, '', 1),
(25, 'ATLANTİS', 'K13', 0.00, '', 1),
(26, 'QUALİTİY', 'K14', 0.00, '', 1),
(27, 'HALİS', 'K15', 0.00, '', 1),
(28, 'ADANA 99', 'B1', 0.00, '', 1),
(29, 'CEYHAN 99', 'B2', 0.00, '', 1),
(30, 'DARİEL', 'B3', 0.00, '', 1),
(31, 'META', 'B4', 0.00, '', 1),
(32, 'ZERUN', 'B5', 0.00, '', 1),
(33, 'TOSUNBEY', 'B6', 0.00, '', 1),
(34, 'BAYRAKTAR', 'B7', 0.00, '', 1),
(35, 'NİZAR', 'B8', 0.00, '', 1),
(36, 'AKÇA', 'B9', 0.00, '', 1),
(37, 'SHİRO', 'B10', 0.00, '', 1),
(38, 'TOROS', 'B11', 0.00, '', 1),
(39, 'CENDERE', 'B12', 0.00, '', 1),
(40, 'KARIŞIK', 'B', 0.00, '', 1),
(41, 'sadffda', NULL, 780.00, 'fdasfsd', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `hammadde_girisleri`
--

CREATE TABLE `hammadde_girisleri` (
  `id` int(11) NOT NULL,
  `tarih` datetime DEFAULT current_timestamp(),
  `hammadde_id` int(11) DEFAULT NULL,
  `silo_id` int(11) DEFAULT NULL,
  `miktar_kg` decimal(10,2) DEFAULT NULL,
  `giris_m3` decimal(10,3) DEFAULT NULL,
  `hektolitre` decimal(5,2) DEFAULT NULL,
  `elenmis_hektolitre` decimal(5,2) DEFAULT NULL,
  `parti_no` varchar(50) NOT NULL,
  `tedarikci` varchar(100) DEFAULT NULL,
  `irsaliye_no` varchar(50) DEFAULT NULL,
  `arac_plaka` varchar(20) DEFAULT NULL,
  `personel` varchar(50) DEFAULT NULL,
  `analiz_yapildi` tinyint(4) DEFAULT 0,
  `onay_durum` varchar(20) DEFAULT 'bekliyor',
  `hesaplanan_m3` decimal(10,3) DEFAULT 0.000,
  `gelen_kg` decimal(12,2) DEFAULT 0.00,
  `islem_yapan` varchar(50) DEFAULT NULL,
  `nem` decimal(10,2) DEFAULT 0.00,
  `protein` decimal(10,2) DEFAULT 0.00,
  `nisasta` decimal(10,2) DEFAULT 0.00,
  `sertlik` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `hammadde_girisleri`
--

INSERT INTO `hammadde_girisleri` (`id`, `tarih`, `hammadde_id`, `silo_id`, `miktar_kg`, `giris_m3`, `hektolitre`, `elenmis_hektolitre`, `parti_no`, `tedarikci`, `irsaliye_no`, `arac_plaka`, `personel`, `analiz_yapildi`, `onay_durum`, `hesaplanan_m3`, `gelen_kg`, `islem_yapan`, `nem`, `protein`, `nisasta`, `sertlik`) VALUES
(1, '2026-02-24 15:03:19', 6, NULL, 0.00, 0.000, NULL, NULL, 'D6-00001', 'X FİRMASI', NULL, '272TEST27', 'admin', 0, 'bekliyor', 0.000, 0.00, NULL, 0.00, 0.00, 0.00, 0.00),
(2, '2026-02-24 15:43:12', 1, NULL, 0.00, 0.000, NULL, NULL, 'D6-0002', 'SADAS', NULL, '34EA2525', 'admin', 0, 'bekliyor', 0.000, 0.00, NULL, 0.00, 0.00, 0.00, 0.00),
(3, '2026-02-24 16:15:53', 14, NULL, 0.00, 0.000, NULL, NULL, 'K2-0001', 'ddfs', NULL, '232', 'admin', 0, 'bekliyor', 0.000, 0.00, NULL, 0.00, 0.00, 0.00, 0.00),
(4, '2026-02-24 16:16:20', 14, NULL, 0.00, 0.000, NULL, NULL, 'K2-0003', '23', NULL, 'SDASD', 'admin', 0, 'bekliyor', 0.000, 0.00, NULL, 0.00, 0.00, 0.00, 0.00),
(5, '2026-03-12 12:00:56', 10, NULL, 5000.00, 0.000, NULL, NULL, 'D10-0001', 'sadsaads', NULL, 'ASDASDSAD', 'admin', 0, 'bekliyor', 0.000, 0.00, NULL, 0.00, 0.00, 0.00, 0.00),
(6, '2026-03-12 12:55:24', 13, NULL, 0.00, 0.000, NULL, NULL, 'K1-0001', 'testtttt', NULL, 'ASDDSA', 'admin', 0, 'bekliyor', 0.000, 0.00, NULL, 0.00, 0.00, 0.00, 0.00),
(7, '2026-03-12 12:58:43', 1, NULL, 0.00, 0.000, NULL, NULL, 'D1-0001', 'Deneme', NULL, '27 TEST 27', 'admin', 0, 'bekliyor', 0.000, 0.00, NULL, 0.00, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `hammadde_kabul_akisi`
--

CREATE TABLE `hammadde_kabul_akisi` (
  `id` int(11) NOT NULL,
  `hammadde_giris_id` int(11) NOT NULL COMMENT 'hammadde_girisleri ile bağlantı',
  `asama` varchar(50) DEFAULT 'bekliyor' COMMENT 'bekliyor, numune_alindi, analiz_yapildi, onay_bekleniyor, onaylandi, reddedildi, kantar, tamamlandi',
  `lab_analiz_id` int(11) DEFAULT NULL COMMENT 'lab_analizleri ile bağlantı',
  `onay_durum` varchar(20) DEFAULT 'bekliyor' COMMENT 'bekliyor, onaylandi, reddedildi',
  `onaylayan_user_id` int(11) DEFAULT NULL,
  `onay_tarihi` datetime DEFAULT NULL,
  `kantar_net_kg` decimal(12,2) DEFAULT NULL COMMENT 'Net ağırlık (brüt - dara)',
  `kantar_tarihi` datetime DEFAULT NULL,
  `red_aciklama` text DEFAULT NULL,
  `notlar` text DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `birim_fiyat` decimal(12,4) DEFAULT NULL,
  `odeme_tarihi` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `hammadde_kabul_akisi`
--

INSERT INTO `hammadde_kabul_akisi` (`id`, `hammadde_giris_id`, `asama`, `lab_analiz_id`, `onay_durum`, `onaylayan_user_id`, `onay_tarihi`, `kantar_net_kg`, `kantar_tarihi`, `red_aciklama`, `notlar`, `olusturma_tarihi`, `guncelleme_tarihi`, `birim_fiyat`, `odeme_tarihi`) VALUES
(1, 1, 'tamamlandi', NULL, 'reddedildi', 1, '2026-03-12 11:59:02', NULL, NULL, 'ret', NULL, '2026-02-24 15:03:19', '2026-03-12 11:59:05', NULL, NULL),
(2, 2, 'tamamlandi', NULL, 'reddedildi', 1, '2026-03-12 11:59:12', NULL, NULL, 'sda', NULL, '2026-02-24 15:43:12', '2026-03-12 11:59:22', NULL, NULL),
(3, 3, 'tamamlandi', NULL, 'reddedildi', 1, '2026-03-12 11:59:15', NULL, NULL, 'sda', NULL, '2026-02-24 16:15:53', '2026-03-12 11:59:25', NULL, NULL),
(4, 4, 'tamamlandi', NULL, 'reddedildi', 1, '2026-03-12 12:55:57', NULL, NULL, 'dsasda', NULL, '2026-02-24 16:16:20', '2026-03-12 12:56:01', 200.0000, '2026-03-13'),
(5, 5, 'tamamlandi', NULL, 'onaylandi', 1, '2026-03-12 12:02:13', 5000.00, NULL, NULL, NULL, '2026-03-12 12:00:56', '2026-03-12 12:54:01', 100.0000, '2026-03-23'),
(6, 6, 'tamamlandi', NULL, 'satinalma_red', 1, '2026-03-12 12:56:32', NULL, NULL, 'öyle', NULL, '2026-03-12 12:55:24', '2026-03-12 12:57:46', 200.0000, '2026-03-29'),
(7, 7, 'bekliyor', NULL, 'bekliyor', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-12 12:58:43', '2026-03-12 12:58:43', NULL, NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `hammadde_kabul_gecmisi`
--

CREATE TABLE `hammadde_kabul_gecmisi` (
  `id` int(11) NOT NULL,
  `akis_id` int(11) NOT NULL,
  `onceki_asama` varchar(50) DEFAULT NULL,
  `yeni_asama` varchar(50) NOT NULL,
  `islem_yapan_user_id` int(11) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `tarih` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `hammadde_kabul_gecmisi`
--

INSERT INTO `hammadde_kabul_gecmisi` (`id`, `akis_id`, `onceki_asama`, `yeni_asama`, `islem_yapan_user_id`, `aciklama`, `tarih`) VALUES
(1, 1, NULL, 'bekliyor', 1, 'Araç geldi, kayıt oluşturuldu', '2026-02-24 15:03:19'),
(2, 2, NULL, 'bekliyor', 1, 'Araç geldi, kayıt oluşturuldu', '2026-02-24 15:43:12'),
(3, 3, NULL, 'bekliyor', 1, 'Araç geldi, kayıt oluşturuldu', '2026-02-24 16:15:53'),
(4, 4, NULL, 'bekliyor', 1, 'Araç geldi, kayıt oluşturuldu', '2026-02-24 16:16:20'),
(5, 1, 'bekliyor', 'reddedildi', 1, 'Red sebebi: ret', '2026-03-12 11:59:02'),
(6, 1, 'reddedildi', 'tamamlandi', 1, 'Reddedilen hammadde tamamlandı/arşivlendi.', '2026-03-12 11:59:05'),
(7, 2, 'bekliyor', 'reddedildi', 1, 'Red sebebi: sda', '2026-03-12 11:59:12'),
(8, 3, 'bekliyor', 'reddedildi', 1, 'Red sebebi: sda', '2026-03-12 11:59:15'),
(9, 2, 'reddedildi', 'tamamlandi', 1, 'Reddedilen hammadde tamamlandı/arşivlendi.', '2026-03-12 11:59:22'),
(10, 3, 'reddedildi', 'tamamlandi', 1, 'Reddedilen hammadde tamamlandı/arşivlendi.', '2026-03-12 11:59:25'),
(11, 5, NULL, 'bekliyor', 1, 'Araç geldi, kayıt oluşturuldu', '2026-03-12 12:00:56'),
(12, 5, 'bekliyor', 'satina_bekliyor', 1, 'Onay Merkezi üzerinden onaylandi. Satınalma işlemleri bekleniyor.', '2026-03-12 12:02:13'),
(13, 5, 'duzenleme', 'duzenleme', 1, 'Kantar/fiyat/ödeme tarihi güncellendi. Brüt: 0, Dara: 0, Birim Fiyat: 100, Ödeme Tarihi: 2026-03-22', '2026-03-12 12:13:34'),
(14, 5, 'satina_bekliyor', 'tamamlandi', 1, 'Satın alma Kantar ile onaylandı. Miktar: 5000 kg', '2026-03-12 12:14:08'),
(15, 5, 'duzenleme', 'duzenleme', 1, 'Fiyat/ödeme tarihi güncellendi. Birim Fiyat: 100, Ödeme Tarihi: 2026-03-23', '2026-03-12 12:54:01'),
(16, 6, NULL, 'bekliyor', 1, 'Araç geldi, kayıt oluşturuldu', '2026-03-12 12:55:24'),
(17, 4, 'bekliyor', 'reddedildi', 1, 'Red sebebi: dsasda', '2026-03-12 12:55:57'),
(18, 4, 'reddedildi', 'tamamlandi', 1, 'Reddedilen hammadde tamamlandı/arşivlendi.', '2026-03-12 12:56:01'),
(19, 6, 'bekliyor', 'satina_bekliyor', 1, 'Onay Merkezi üzerinden onaylandi. Satınalma işlemleri bekleniyor.', '2026-03-12 12:56:32'),
(20, 6, 'satina_bekliyor', 'tamamlandi', 1, 'Satın alma reddetti: öyle', '2026-03-12 12:57:46'),
(21, 7, NULL, 'bekliyor', 1, 'Araç geldi, kayıt oluşturuldu', '2026-03-12 12:58:43');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `helal_kayitlari`
--

CREATE TABLE `helal_kayitlari` (
  `id` int(11) NOT NULL,
  `parti_no` varchar(50) DEFAULT NULL,
  `nokta_no` int(11) DEFAULT NULL COMMENT 'Helal Noktası 1, 2, ...',
  `nokta_adi` varchar(100) NOT NULL,
  `kontrol_kriteri` varchar(200) DEFAULT NULL,
  `sonuc` varchar(20) DEFAULT NULL COMMENT 'uygun, uygun_degil',
  `aciklama` text DEFAULT NULL,
  `kontrol_zamani` datetime DEFAULT current_timestamp(),
  `kontrol_eden` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `islem_loglari`
--

CREATE TABLE `islem_loglari` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `islem_tipi` varchar(50) NOT NULL,
  `islem_tablosu` varchar(50) DEFAULT NULL,
  `islem_id` int(11) DEFAULT NULL,
  `islem_aciklama` text DEFAULT NULL,
  `islem_detay` text DEFAULT NULL,
  `ip_adresi` varchar(45) DEFAULT NULL,
  `tarayici` varchar(255) DEFAULT NULL,
  `islem_zamani` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `islem_loglari`
--

INSERT INTO `islem_loglari` (`id`, `user_id`, `islem_tipi`, `islem_tablosu`, `islem_id`, `islem_aciklama`, `islem_detay`, `ip_adresi`, `tarayici`, `islem_zamani`) VALUES
(1, 1, 'yetki_toplu_guncelleme', 'modul_yetkileri', 1, 'Rol ID: 1 için yetkiler toplu güncellendi', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 12:31:25'),
(2, 1, 'hammadde_yeni', 'hammaddeler', 1, 'Yeni hammadde: VBURGAZ', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:50:33'),
(3, 1, 'hammadde_yeni', 'hammaddeler', 2, 'Yeni hammadde: ZIVEGO', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:50:48'),
(4, 1, 'hammadde_yeni', 'hammaddeler', 3, 'Yeni hammadde: OVIDIO', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:51:02'),
(5, 1, 'hammadde_yeni', 'hammaddeler', 4, 'Yeni hammadde: SEZAR', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:51:13'),
(6, 1, 'hammadde_yeni', 'hammaddeler', 5, 'Yeni hammadde: MAESTRAL', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:51:26'),
(7, 1, 'hammadde_yeni', 'hammaddeler', 6, 'Yeni hammadde: ZENİT', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:51:48'),
(8, 1, 'hammadde_yeni', 'hammaddeler', 7, 'Yeni hammadde: KUNDURU', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:52:40'),
(9, 1, 'hammadde_yeni', 'hammaddeler', 8, 'Yeni hammadde: HATAY 85', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:52:53'),
(10, 1, 'hammadde_yeni', 'hammaddeler', 9, 'Yeni hammadde: D.SERT', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:53:08'),
(11, 1, 'hammadde_yeni', 'hammaddeler', 10, 'Yeni hammadde: İTHAL', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:53:25'),
(12, 1, 'hammadde_yeni', 'hammaddeler', 11, 'Yeni hammadde: KARIŞIK', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:53:59'),
(13, 1, 'hammadde_yeni', 'hammaddeler', 12, 'Yeni hammadde: TİREX', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:54:07'),
(14, 1, 'hammadde_yeni', 'hammaddeler', 13, 'Yeni hammadde: SEGOTORİA', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:54:38'),
(15, 1, 'hammadde_yeni', 'hammaddeler', 14, 'Yeni hammadde: PANDAS', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:54:49'),
(16, 1, 'hammadde_yeni', 'hammaddeler', 15, 'Yeni hammadde: EXPERİA', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:55:07'),
(17, 1, 'hammadde_yeni', 'hammaddeler', 16, 'Yeni hammadde: BEZOSTA', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:55:24'),
(18, 1, 'hammadde_yeni', 'hammaddeler', 17, 'Yeni hammadde: PEHLİVAN', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:56:03'),
(19, 1, 'hammadde_yeni', 'hammaddeler', 18, 'Yeni hammadde: MASSACİO', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:56:22'),
(20, 1, 'hammadde_yeni', 'hammaddeler', 19, 'Yeni hammadde: ADELE', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:56:44'),
(21, 1, 'hammadde_yeni', 'hammaddeler', 20, 'Yeni hammadde: GOLİA', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:57:02'),
(22, 1, 'hammadde_yeni', 'hammaddeler', 21, 'Yeni hammadde: İTHAL', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:57:16'),
(23, 1, 'hammadde_yeni', 'hammaddeler', 22, 'Yeni hammadde: GOFRETLİK-KARIŞIK', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:57:48'),
(24, 1, 'hammadde_yeni', 'hammaddeler', 23, 'Yeni hammadde: ODESKA', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:58:01'),
(25, 1, 'hammadde_yeni', 'hammaddeler', 24, 'Yeni hammadde: LUCİLLA', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:58:16'),
(26, 1, 'hammadde_yeni', 'hammaddeler', 25, 'Yeni hammadde: ATLANTİS', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:58:37'),
(27, 1, 'hammadde_yeni', 'hammaddeler', 26, 'Yeni hammadde: QUALİTİY', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:59:10'),
(28, 1, 'hammadde_yeni', 'hammaddeler', 27, 'Yeni hammadde: HALİS', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:59:22'),
(29, 1, 'hammadde_yeni', 'hammaddeler', 28, 'Yeni hammadde: ADANA 99', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:59:49'),
(30, 1, 'hammadde_yeni', 'hammaddeler', 29, 'Yeni hammadde: CEYHAN 99', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 15:00:03'),
(31, 1, 'hammadde_yeni', 'hammaddeler', 30, 'Yeni hammadde: DARİEL', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 15:00:17'),
(32, 1, 'hammadde_yeni', 'hammaddeler', 31, 'Yeni hammadde: META', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 15:00:28'),
(33, 1, 'hammadde_yeni', 'hammaddeler', 32, 'Yeni hammadde: ZERUN', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 15:00:43'),
(34, 1, 'hammadde_yeni', 'hammaddeler', 33, 'Yeni hammadde: TOSUNBEY', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 15:00:56'),
(35, 1, 'hammadde_yeni', 'hammaddeler', 34, 'Yeni hammadde: BAYRAKTAR', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 15:01:07'),
(36, 1, 'hammadde_yeni', 'hammaddeler', 35, 'Yeni hammadde: NİZAR', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 15:01:27'),
(37, 1, 'hammadde_yeni', 'hammaddeler', 36, 'Yeni hammadde: AKÇA', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 15:01:39'),
(38, 1, 'hammadde_yeni', 'hammaddeler', 37, 'Yeni hammadde: SHİRO', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 15:01:55'),
(39, 1, 'hammadde_yeni', 'hammaddeler', 38, 'Yeni hammadde: TOROS', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 15:02:09'),
(40, 1, 'hammadde_yeni', 'hammaddeler', 39, 'Yeni hammadde: CENDERE', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 15:02:19'),
(41, 1, 'hammadde_yeni', 'hammaddeler', 40, 'Yeni hammadde: KARIŞIK', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 15:02:30'),
(42, 1, 'hammadde_kod', 'hammaddeler', 37, 'Kod: B10', '', '192.168.1.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 12:17:54'),
(43, 1, 'hammadde_yeni', 'hammaddeler', 41, 'Yeni hammadde: sadffda', '', '192.168.1.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 15:37:18'),
(44, 1, 'hammadde_kod_kaldir', 'hammaddeler', 41, 'Kod kaldırıldı', '', '192.168.1.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 15:37:31'),
(45, 1, 'hammadde_kod_kaldir', 'hammaddeler', 41, 'Kod kaldırıldı', '', '192.168.1.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 15:37:43'),
(46, 1, 'yetki_toplu_guncelleme', 'modul_yetkileri', 1, 'Rol ID: 1 için yetkiler toplu güncellendi', '', '192.168.1.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 11:09:02'),
(47, 1, 'kullanici_ekleme', 'users', 2, 'Kullanıcı: test eklendi', '', '192.168.1.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 14:29:28'),
(48, 1, 'yetki_toplu_guncelleme', 'modul_yetkileri', 5, 'Rol ID: 5 için yetkiler toplu güncellendi', '', '192.168.1.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 14:29:41'),
(49, 1, 'kullanici_guncelleme', 'users', 2, 'Kullanıcı bilgileri güncellendi', '', '192.168.1.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 10:37:25');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `is_emirleri`
--

CREATE TABLE `is_emirleri` (
  `id` int(11) NOT NULL,
  `is_kodu` varchar(50) DEFAULT NULL,
  `recete_id` int(11) DEFAULT NULL,
  `hedef_miktar_ton` decimal(10,2) DEFAULT NULL,
  `termin_tarihi` date DEFAULT NULL,
  `atanan_personel` varchar(100) DEFAULT NULL,
  `durum` varchar(20) DEFAULT 'bekliyor',
  `onay_durum` varchar(20) DEFAULT 'bekliyor',
  `onaylayan_user_id` int(11) DEFAULT NULL,
  `onay_tarihi` datetime DEFAULT NULL,
  `mail_gonderildi` tinyint(4) DEFAULT 0,
  `baslangic_tarihi` datetime DEFAULT current_timestamp(),
  `yikama_parti_no` varchar(50) DEFAULT NULL COMMENT 'Hangi yıkama partisinden üretim yapılacak'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `is_emri_silo_karisimlari`
--

CREATE TABLE `is_emri_silo_karisimlari` (
  `id` int(11) NOT NULL,
  `is_emri_id` int(11) NOT NULL,
  `silo_id` int(11) NOT NULL,
  `yuzde` decimal(5,2) NOT NULL COMMENT 'Yuzde orani (0-100)',
  `olusturma_tarihi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kkn_kayitlari`
--

CREATE TABLE `kkn_kayitlari` (
  `id` int(11) NOT NULL,
  `parti_no` varchar(50) DEFAULT NULL,
  `kkn_adi` varchar(100) NOT NULL,
  `kontrol_zamani` datetime DEFAULT current_timestamp(),
  `deger` varchar(100) DEFAULT NULL,
  `sonuc` varchar(20) DEFAULT NULL COMMENT 'uygun, uygunsuzluk',
  `duzeltici_faaliyet` text DEFAULT NULL,
  `kontrol_eden` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kullanici_bildirim_durumlari`
--

CREATE TABLE `kullanici_bildirim_durumlari` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bildirim_id` int(11) NOT NULL,
  `okundu` tinyint(1) DEFAULT 0,
  `silindi` tinyint(1) DEFAULT 0,
  `islem_tarihi` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `kullanici_bildirim_durumlari`
--

INSERT INTO `kullanici_bildirim_durumlari` (`id`, `user_id`, `bildirim_id`, `okundu`, `silindi`, `islem_tarihi`) VALUES
(1, 1, 2, 1, 1, '2026-03-12 09:56:12'),
(2, 1, 3, 1, 1, '2026-03-12 09:56:12'),
(3, 1, 5, 1, 1, '2026-03-12 09:56:12'),
(4, 1, 6, 1, 1, '2026-03-12 09:56:12'),
(5, 1, 8, 1, 1, '2026-03-12 09:56:12'),
(6, 1, 10, 1, 1, '2026-03-12 09:56:12'),
(7, 1, 11, 1, 1, '2026-03-12 09:56:12'),
(8, 1, 12, 1, 1, '2026-03-12 09:56:12'),
(9, 1, 14, 1, 1, '2026-03-12 09:56:12'),
(10, 1, 15, 1, 1, '2026-03-12 09:56:12'),
(11, 1, 18, 1, 1, '2026-03-12 09:56:12'),
(12, 1, 17, 1, 1, '2026-03-12 09:56:12'),
(13, 1, 20, 1, 1, '2026-03-12 09:56:12'),
(14, 1, 21, 1, 1, '2026-03-12 09:56:12'),
(29, 1, 23, 1, 0, '2026-03-12 10:03:01'),
(30, 1, 24, 1, 0, '2026-03-12 10:03:01');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kullanici_modul_yetkileri`
--

CREATE TABLE `kullanici_modul_yetkileri` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `modul_adi` varchar(100) NOT NULL,
  `okuma` tinyint(1) DEFAULT 0,
  `yazma` tinyint(1) DEFAULT 0,
  `onaylama` tinyint(1) DEFAULT 0,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kullanici_rolleri`
--

CREATE TABLE `kullanici_rolleri` (
  `id` int(11) NOT NULL,
  `rol_adi` varchar(50) NOT NULL,
  `aciklama` text DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `kullanici_rolleri`
--

INSERT INTO `kullanici_rolleri` (`id`, `rol_adi`, `aciklama`, `olusturma_tarihi`) VALUES
(1, 'Patron', 'Üst yönetim, tüm yetkilere sahip, onay veren', '2026-02-24 12:27:56'),
(2, 'İdari Satın Alma', 'Satın alma işlemleri ve onayları', '2026-02-24 12:27:56'),
(3, 'İdari Sevkiyat', 'Sevkiyat, depo ve mali işler', '2026-02-24 12:27:56'),
(4, 'Otomasyon Sorumlusu', 'Üretim, yıkama, silo operasyonları', '2026-02-24 12:27:56'),
(5, 'Lab Sorumlusu', 'Kalite kontrol, hammadde kabul, analizler', '2026-02-24 12:27:56'),
(6, 'Operatör', 'Temel üretim kayıtları', '2026-02-24 12:27:56'),
(7, 'Pazarlama Müdürü', 'Satış stratejileri ve müşteri spekti yönetimi', '2026-02-24 12:27:56'),
(8, 'Saha Plasiyer', 'Müşteri ziyareti ve sipariş toplama', '2026-02-24 12:27:56');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `lab_analizleri`
--

CREATE TABLE `lab_analizleri` (
  `id` int(11) NOT NULL,
  `parti_no` varchar(50) DEFAULT NULL,
  `hammadde_giris_id` int(11) DEFAULT NULL,
  `protein` decimal(5,2) DEFAULT NULL,
  `gluten` decimal(5,2) DEFAULT NULL,
  `index_degeri` int(11) DEFAULT NULL,
  `sedimantasyon` int(11) DEFAULT NULL,
  `gecikmeli_sedimantasyon` int(11) NOT NULL DEFAULT 0,
  `hektolitre` decimal(6,2) NOT NULL DEFAULT 0.00,
  `nem` decimal(5,2) NOT NULL DEFAULT 0.00,
  `fn` int(11) NOT NULL DEFAULT 0,
  `sertlik` decimal(6,2) NOT NULL DEFAULT 0.00,
  `nisasta` decimal(5,2) NOT NULL DEFAULT 0.00,
  `doker_orani` decimal(5,2) NOT NULL DEFAULT 0.00,
  `laborant` varchar(50) DEFAULT NULL,
  `tarih` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `lab_analizleri`
--

INSERT INTO `lab_analizleri` (`id`, `parti_no`, `hammadde_giris_id`, `protein`, `gluten`, `index_degeri`, `sedimantasyon`, `gecikmeli_sedimantasyon`, `hektolitre`, `nem`, `fn`, `sertlik`, `nisasta`, `doker_orani`, `laborant`, `tarih`) VALUES
(1, 'D6-00001', NULL, 12.50, 23.00, 23, 42, 23, 23.00, 2.00, 3, 23.00, 23.00, 2.00, 'admin', '2026-02-24 15:04:14'),
(2, 'D6-0002', NULL, 21.00, 2.00, 2, 2, 2, 2.00, 2.00, 2, 2.00, 2.00, 2.00, 'admin', '2026-02-24 15:43:36'),
(3, 'K2-0003', NULL, NULL, NULL, NULL, NULL, 0, 90.00, 0.00, 0, 0.00, 0.00, 0.00, 'admin', '2026-03-12 11:53:31'),
(4, 'K2-0001', 3, NULL, NULL, NULL, NULL, 0, 85.00, 0.00, 0, 0.00, 0.00, 0.00, 'admin', '2026-03-12 11:59:44'),
(5, 'D10-0001', 5, NULL, NULL, NULL, NULL, 0, 90.00, 0.00, 0, 0.00, 0.00, 0.00, 'admin', '2026-03-12 12:01:09'),
(6, 'K1-0001', 6, NULL, NULL, NULL, NULL, 0, 100.00, 0.00, 0, 0.00, 0.00, 0.00, 'admin', '2026-03-12 12:55:35'),
(7, 'D1-0001', 7, NULL, NULL, NULL, NULL, 0, 20.00, 0.00, 0, 0.00, 0.00, 0.00, 'admin', '2026-03-12 12:58:59');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `lab_referans_degerleri`
--

CREATE TABLE `lab_referans_degerleri` (
  `id` int(11) NOT NULL DEFAULT 1,
  `protein_min` decimal(5,2) NOT NULL DEFAULT 11.00,
  `protein_max` decimal(5,2) NOT NULL DEFAULT 14.00,
  `gluten_min` decimal(5,2) NOT NULL DEFAULT 26.00,
  `gluten_max` decimal(5,2) NOT NULL DEFAULT 32.00,
  `index_min` int(11) NOT NULL DEFAULT 80,
  `index_max` int(11) NOT NULL DEFAULT 100,
  `sedim_min` int(11) NOT NULL DEFAULT 35,
  `sedim_max` int(11) NOT NULL DEFAULT 100,
  `gsedim_min` int(11) NOT NULL DEFAULT 30,
  `gsedim_max` int(11) NOT NULL DEFAULT 100,
  `hektolitre_min` decimal(5,2) NOT NULL DEFAULT 76.00,
  `hektolitre_max` decimal(5,2) NOT NULL DEFAULT 100.00,
  `nem_min` decimal(5,2) NOT NULL DEFAULT 0.00,
  `nem_max` decimal(5,2) NOT NULL DEFAULT 14.50,
  `fn_min` int(11) NOT NULL DEFAULT 250,
  `fn_max` int(11) NOT NULL DEFAULT 9999,
  `sertlik_min` decimal(5,2) NOT NULL DEFAULT 50.00,
  `sertlik_max` decimal(5,2) NOT NULL DEFAULT 90.00,
  `nisasta_min` decimal(5,2) NOT NULL DEFAULT 60.00,
  `nisasta_max` decimal(5,2) NOT NULL DEFAULT 75.00,
  `doker_min` decimal(5,2) NOT NULL DEFAULT 45.00,
  `doker_max` decimal(5,2) NOT NULL DEFAULT 65.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `lab_referans_degerleri`
--

INSERT INTO `lab_referans_degerleri` (`id`, `protein_min`, `protein_max`, `gluten_min`, `gluten_max`, `index_min`, `index_max`, `sedim_min`, `sedim_max`, `gsedim_min`, `gsedim_max`, `hektolitre_min`, `hektolitre_max`, `nem_min`, `nem_max`, `fn_min`, `fn_max`, `sertlik_min`, `sertlik_max`, `nisasta_min`, `nisasta_max`, `doker_min`, `doker_max`) VALUES
(1, 11.00, 14.00, 26.00, 32.00, 80, 100, 35, 100, 30, 100, 76.00, 100.00, 0.00, 14.50, 1, 10, 50.00, 90.00, 60.00, 75.00, 45.00, 65.00);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `makineler`
--

CREATE TABLE `makineler` (
  `id` int(11) NOT NULL,
  `makine_kodu` varchar(20) NOT NULL,
  `makine_adi` varchar(100) NOT NULL,
  `lokasyon` varchar(50) DEFAULT NULL,
  `son_bakim_tarihi` date DEFAULT NULL,
  `sonraki_bakim_tarihi` date DEFAULT NULL,
  `bakim_periyodu` int(11) DEFAULT 30,
  `aktif` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `malzemeler`
--

CREATE TABLE `malzemeler` (
  `id` int(11) NOT NULL,
  `malzeme_kodu` varchar(50) NOT NULL,
  `malzeme_adi` varchar(100) NOT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `birim` varchar(20) DEFAULT 'adet',
  `min_stok` int(11) DEFAULT 0,
  `aktif` tinyint(1) DEFAULT 1,
  `kapasite_kg` decimal(10,2) DEFAULT NULL,
  `mevcut_stok` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `malzeme_hareketleri`
--

CREATE TABLE `malzeme_hareketleri` (
  `id` int(11) NOT NULL,
  `malzeme_id` int(11) NOT NULL,
  `hareket_tipi` varchar(20) NOT NULL,
  `miktar` decimal(10,2) NOT NULL,
  `uretim_kg` decimal(10,2) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `islem_tarihi` datetime DEFAULT current_timestamp(),
  `kullanici` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `malzeme_stok`
--

CREATE TABLE `malzeme_stok` (
  `id` int(11) NOT NULL,
  `malzeme_id` int(11) NOT NULL,
  `mevcut_stok` decimal(10,2) DEFAULT 0.00,
  `son_guncelleme` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `modul_yetkileri`
--

CREATE TABLE `modul_yetkileri` (
  `id` int(11) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `modul_adi` varchar(50) NOT NULL,
  `okuma` tinyint(4) DEFAULT 0,
  `yazma` tinyint(4) DEFAULT 0,
  `onaylama` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `modul_yetkileri`
--

INSERT INTO `modul_yetkileri` (`id`, `rol_id`, `modul_adi`, `okuma`, `yazma`, `onaylama`) VALUES
(1, 1, 'Hammadde Yönetimi', 1, 1, 1),
(2, 1, 'Planlama & Takvim', 1, 1, 1),
(3, 1, 'Üretim Paneli', 1, 1, 1),
(4, 1, 'Satış & Siparişler', 1, 1, 1),
(5, 1, 'Satın Alma', 1, 1, 1),
(6, 1, 'Sevkiyat & Lojistik', 1, 1, 1),
(7, 1, 'Stok Takibi', 1, 1, 1),
(8, 1, 'İzlenebilirlik', 1, 1, 1),
(9, 1, 'Lab Analizleri', 1, 1, 1),
(10, 1, 'Bakım & Arıza', 1, 1, 1),
(11, 1, 'Silo Yönetimi', 1, 1, 1),
(12, 1, 'Hammadde Kodlama', 1, 1, 1),
(13, 1, 'Sistem Ayarları', 1, 1, 1),
(14, 5, 'Hammadde Yönetimi', 1, 1, 1),
(15, 5, 'Planlama & Takvim', 0, 0, 0),
(16, 5, 'Üretim Paneli', 0, 0, 0),
(17, 5, 'Satış & Siparişler', 1, 1, 1),
(18, 5, 'Satın Alma', 0, 0, 0),
(19, 5, 'Sevkiyat & Lojistik', 0, 0, 0),
(20, 5, 'Stok Takibi', 0, 0, 0),
(21, 5, 'İzlenebilirlik', 1, 1, 1),
(22, 5, 'Lab Analizleri', 1, 1, 1),
(23, 5, 'Bakım & Arıza', 0, 0, 0),
(24, 5, 'Silo Yönetimi', 0, 0, 0),
(25, 5, 'Hammadde Kodlama', 0, 0, 0),
(26, 5, 'Sistem Ayarları', 0, 0, 0);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `musteriler`
--

CREATE TABLE `musteriler` (
  `id` int(11) NOT NULL,
  `cari_kod` varchar(50) NOT NULL,
  `cari_tip` enum('Müşteri','Tedarikçi') NOT NULL,
  `firma_adi` varchar(255) NOT NULL,
  `yetkili_kisi` varchar(100) DEFAULT NULL,
  `telefon` varchar(50) DEFAULT NULL,
  `eposta` varchar(100) DEFAULT NULL,
  `vergi_dairesi` varchar(100) DEFAULT NULL,
  `vergi_no` varchar(50) DEFAULT NULL,
  `il` varchar(50) DEFAULT NULL,
  `ilce` varchar(50) DEFAULT NULL,
  `adres` text DEFAULT NULL,
  `ozel_notlar` text DEFAULT NULL,
  `bakiye` decimal(18,2) DEFAULT 0.00,
  `para_birimi` varchar(10) DEFAULT 'TL',
  `kayit_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `musteriler`
--

INSERT INTO `musteriler` (`id`, `cari_kod`, `cari_tip`, `firma_adi`, `yetkili_kisi`, `telefon`, `eposta`, `vergi_dairesi`, `vergi_no`, `il`, `ilce`, `adres`, `ozel_notlar`, `bakiye`, `para_birimi`, `kayit_tarihi`) VALUES
(1, '120.01.001', 'Müşteri', 'Kardeşler Ekmek Fırını', 'Ahmet Demir', '0532 111 22 33', 'kardesler@mail.com', 'Marmara', '1234567890', 'İstanbul', 'Esenyurt', 'Fatih Mah. 12. Sokak No:5', 'Düzenli un alımı yapar, ödemeler haftalık.', 0.00, 'TL', '2026-03-23 09:09:07'),
(2, '320.01.005', 'Tedarikçi', 'Anadolu Tarım Ürünleri', 'Mehmet Yılmaz', '0544 333 44 55', 'anadolu@tarim.com', 'Konya V.D.', '9876543210', 'Konya', 'Meram', 'Sanayi Sitesi B Blok No:12', 'Buğday tedarikçimiz, protein oranı yüksek ürün verir.', 0.00, 'TL', '2026-03-23 09:09:07');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `musteri_spektleri`
--

CREATE TABLE `musteri_spektleri` (
  `id` int(11) NOT NULL,
  `musteri_adi` varchar(100) NOT NULL,
  `urun_id` int(11) DEFAULT NULL,
  `protein_min` decimal(5,2) DEFAULT NULL,
  `protein_max` decimal(5,2) DEFAULT NULL,
  `gluten_min` decimal(5,2) DEFAULT NULL,
  `kul_max` decimal(5,2) DEFAULT NULL,
  `index_min` int(11) DEFAULT NULL,
  `index_max` int(11) DEFAULT NULL,
  `sedimantasyon_min` int(11) DEFAULT NULL,
  `aktif` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `onay_bekleyenler`
--

CREATE TABLE `onay_bekleyenler` (
  `id` int(11) NOT NULL,
  `islem_tipi` varchar(50) NOT NULL,
  `islem_id` int(11) NOT NULL,
  `islem_aciklama` text DEFAULT NULL,
  `olusturan_user_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `onay_durum` varchar(20) DEFAULT 'bekliyor',
  `onaylayan_user_id` int(11) DEFAULT NULL,
  `onay_tarihi` datetime DEFAULT NULL,
  `red_aciklama` text DEFAULT NULL,
  `oncelik` varchar(20) DEFAULT 'normal'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ortam_olcumu`
--

CREATE TABLE `ortam_olcumu` (
  `id` int(11) NOT NULL,
  `tarih` datetime DEFAULT current_timestamp(),
  `alan` varchar(50) DEFAULT NULL,
  `sicaklik` decimal(5,2) DEFAULT NULL,
  `nem` decimal(5,2) DEFAULT NULL,
  `olcum_yapan` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `paketleme_ambalaj_kullanimi`
--

CREATE TABLE `paketleme_ambalaj_kullanimi` (
  `id` int(11) NOT NULL,
  `paketleme_id` int(11) NOT NULL,
  `ambalaj_parti_id` int(11) NOT NULL,
  `kullanilan_miktar` int(11) DEFAULT NULL,
  `kayit_zamani` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `paketleme_hareketleri`
--

CREATE TABLE `paketleme_hareketleri` (
  `id` int(11) NOT NULL,
  `parti_no` varchar(50) DEFAULT NULL,
  `urun_id` int(11) DEFAULT NULL,
  `uretim_parti_no` varchar(50) DEFAULT NULL COMMENT 'Hangi üretim partisinden paketlendi',
  `miktar` decimal(10,2) DEFAULT NULL,
  `tarih` datetime DEFAULT current_timestamp(),
  `personel` varchar(100) DEFAULT NULL,
  `paketleme_recetesi_id` int(11) DEFAULT NULL,
  `paketleme_sistemi_tonaj` decimal(10,2) DEFAULT NULL COMMENT 'Dış sistemden çekilen',
  `depo_id` int(11) DEFAULT NULL,
  `qr_kod` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `paketleme_recetesi`
--

CREATE TABLE `paketleme_recetesi` (
  `id` int(11) NOT NULL,
  `urun_id` int(11) NOT NULL,
  `recete_adi` varchar(100) NOT NULL,
  `ana_urun_silo_id` int(11) DEFAULT NULL,
  `ambalaj_id` int(11) DEFAULT NULL,
  `etiket_turu` varchar(50) DEFAULT NULL,
  `diger_malzemeler` text DEFAULT NULL COMMENT 'JSON: dikis_ipi, palet, vs.',
  `aktif` tinyint(1) DEFAULT 1,
  `olusturma_tarihi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `proses_parametreleri`
--

CREATE TABLE `proses_parametreleri` (
  `id` int(11) NOT NULL,
  `is_emri_id` int(11) DEFAULT NULL,
  `uretim_id` int(11) DEFAULT NULL,
  `parametre_adi` varchar(50) NOT NULL COMMENT 'sicaklik, nem, basınc, hiz, pH, vs.',
  `deger` decimal(10,4) NOT NULL,
  `birim` varchar(20) DEFAULT NULL,
  `olcum_zamani` datetime DEFAULT current_timestamp(),
  `min_limit` decimal(10,4) DEFAULT NULL,
  `max_limit` decimal(10,4) DEFAULT NULL,
  `uyari` tinyint(1) DEFAULT 0,
  `olcum_yapan` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `receteler`
--

CREATE TABLE `receteler` (
  `id` int(11) NOT NULL,
  `recete_adi` varchar(100) NOT NULL,
  `urun_cikis` varchar(100) DEFAULT NULL,
  `hammadde_1` int(11) DEFAULT NULL,
  `hammadde_2` int(11) DEFAULT NULL,
  `oran_1` decimal(5,2) DEFAULT NULL,
  `oran_2` decimal(5,2) DEFAULT NULL,
  `tavlama_miktar` decimal(10,2) DEFAULT NULL,
  `tavlama_sure` int(11) DEFAULT NULL,
  `tavlama_sicaklik` decimal(5,2) DEFAULT NULL,
  `tavlama_nem` decimal(5,2) DEFAULT NULL,
  `klape_gozleri` varchar(100) DEFAULT NULL,
  `urun_analiz_spektleri` text DEFAULT NULL,
  `ekleyen_kullanici` varchar(50) DEFAULT NULL,
  `tav_miktar` decimal(10,2) DEFAULT 0.00,
  `sure_saat` int(11) DEFAULT 0,
  `sicaklik` decimal(5,2) DEFAULT 0.00,
  `hedef_nem` decimal(5,2) DEFAULT 0.00,
  `aciklama` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `satin_alma_talepleri`
--

CREATE TABLE `satin_alma_talepleri` (
  `id` int(11) NOT NULL,
  `malzeme_adi` varchar(100) DEFAULT NULL,
  `miktar` decimal(10,2) DEFAULT NULL,
  `birim` varchar(20) DEFAULT NULL,
  `talep_tarihi` datetime DEFAULT current_timestamp(),
  `talep_eden_user_id` int(11) DEFAULT NULL,
  `onay_durum` varchar(20) DEFAULT 'bekliyor',
  `onaylayan_user_id` int(11) DEFAULT NULL,
  `onay_tarihi` datetime DEFAULT NULL,
  `aciliyet` varchar(20) DEFAULT 'normal',
  `ilgili_is_emri_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sevkiyatlar`
--

CREATE TABLE `sevkiyatlar` (
  `id` int(11) NOT NULL,
  `parti_no` varchar(50) DEFAULT NULL,
  `musteri_adi` varchar(100) DEFAULT NULL,
  `sevk_tarihi` datetime DEFAULT NULL,
  `sevk_miktari` decimal(10,2) DEFAULT NULL,
  `birim` varchar(20) DEFAULT 'ton',
  `irsaliye_no` varchar(50) DEFAULT NULL,
  `fatura_no` varchar(50) DEFAULT NULL,
  `arac_plaka` varchar(20) DEFAULT NULL,
  `sofor_adi` varchar(100) DEFAULT NULL,
  `sofor_telefon` varchar(20) DEFAULT NULL,
  `kanit_belgesi` varchar(255) DEFAULT NULL COMMENT 'PDF yolu',
  `sevk_eden_user_id` int(11) DEFAULT NULL,
  `onay_durum` varchar(20) DEFAULT 'bekliyor',
  `kayit_tarihi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sevkiyat_detaylari`
--

CREATE TABLE `sevkiyat_detaylari` (
  `id` int(11) NOT NULL,
  `siparis_id` int(11) NOT NULL,
  `urun_adi` varchar(100) DEFAULT NULL,
  `miktar` int(11) NOT NULL,
  `sevk_tarihi` datetime DEFAULT current_timestamp(),
  `plaka` varchar(20) DEFAULT NULL,
  `notlar` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sevkiyat_icerik`
--

CREATE TABLE `sevkiyat_icerik` (
  `id` int(11) NOT NULL,
  `sevkiyat_id` int(11) DEFAULT NULL,
  `urun_id` int(11) DEFAULT NULL,
  `miktar` decimal(10,2) DEFAULT NULL,
  `parti_no` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sevkiyat_randevulari`
--

CREATE TABLE `sevkiyat_randevulari` (
  `id` int(11) NOT NULL,
  `musteri_adi` varchar(100) DEFAULT NULL,
  `randevu_tarihi` datetime DEFAULT NULL,
  `urun_id` int(11) DEFAULT NULL,
  `miktar_ton` decimal(10,2) DEFAULT NULL,
  `arac_plaka` varchar(20) DEFAULT NULL,
  `sofor_adi` varchar(100) DEFAULT NULL,
  `durum` varchar(20) DEFAULT 'bekliyor',
  `onay_durum` varchar(20) DEFAULT 'bekliyor',
  `onaylayan_user_id` int(11) DEFAULT NULL,
  `onay_tarihi` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sikayetler`
--

CREATE TABLE `sikayetler` (
  `id` int(11) NOT NULL,
  `sikayet_no` varchar(50) NOT NULL COMMENT 'DOF-2026-001 formatı',
  `musteri_id` int(11) DEFAULT NULL COMMENT 'FK → musteriler (NULL olabilir - bilinmeyen müşteri)',
  `musteri_adi` varchar(200) DEFAULT NULL COMMENT 'Müşteri seçilmezse veya silinmişse fallback',
  `parti_no` varchar(50) DEFAULT NULL COMMENT 'Hammadde parti no (hammadde_girisleri.parti_no)',
  `sevkiyat_parti_no` varchar(50) DEFAULT NULL COMMENT 'Sevkiyat parti no (varsa otomatik)',
  `sikayet_tarihi` date NOT NULL,
  `bildirim_kanali` varchar(50) DEFAULT NULL COMMENT 'telefon, email, yuz_yuze, yazili',
  `sikayet_tipi` varchar(50) DEFAULT NULL COMMENT 'kalite, ambalaj, lojistik, yabanci_madde, miktar, diger',
  `sikayet_konusu` varchar(200) NOT NULL,
  `sikayet_detay` text DEFAULT NULL,
  `oncelik` varchar(20) DEFAULT 'orta' COMMENT 'dusuk, orta, yuksek, kritik',
  `durum` varchar(20) DEFAULT 'acik' COMMENT 'acik, inceleniyor, dof_acildi, kapandi',
  `kok_neden` text DEFAULT NULL,
  `duzeltici_faaliyet` text DEFAULT NULL,
  `onleyici_faaliyet` text DEFAULT NULL,
  `dof_sorumlu` varchar(100) DEFAULT NULL,
  `hedef_kapanma_tarihi` date DEFAULT NULL,
  `kapanma_tarihi` date DEFAULT NULL,
  `sonuc_dogrulama` text DEFAULT NULL COMMENT 'Faaliyetlerin etkinlik doğrulaması',
  `olusturan` varchar(50) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `sikayetler`
--

INSERT INTO `sikayetler` (`id`, `sikayet_no`, `musteri_id`, `musteri_adi`, `parti_no`, `sevkiyat_parti_no`, `sikayet_tarihi`, `bildirim_kanali`, `sikayet_tipi`, `sikayet_konusu`, `sikayet_detay`, `oncelik`, `durum`, `kok_neden`, `duzeltici_faaliyet`, `onleyici_faaliyet`, `dof_sorumlu`, `hedef_kapanma_tarihi`, `kapanma_tarihi`, `sonuc_dogrulama`, `olusturan`, `olusturma_tarihi`, `guncelleme_tarihi`) VALUES
(1, 'DOF-2026-001', 1, 'TEST', 'PRT-270127-1081', '', '2026-02-26', 'telefon', 'kalite', 'asddsa', 'dsadsa', 'orta', 'kapandi', 'dsadas', 'adasd', 'adsads', 'sadasd', '2026-02-27', '2026-02-26', 'saddsa', 'admin', '2026-02-26 12:18:06', '2026-02-26 12:19:53'),
(2, 'DOF-2026-002', NULL, 'asdas', 'dsadas', '', '2026-02-26', 'telefon', 'ambalaj', 'saddas', 'dasads', 'orta', 'dof_acildi', '', '', '', '', NULL, NULL, NULL, 'admin', '2026-02-26 13:45:51', '2026-02-26 13:48:15');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sikayet_faaliyetleri`
--

CREATE TABLE `sikayet_faaliyetleri` (
  `id` int(11) NOT NULL,
  `sikayet_id` int(11) NOT NULL COMMENT 'FK → sikayetler',
  `faaliyet_tipi` varchar(50) NOT NULL COMMENT 'duzeltici, onleyici, acil_onlem',
  `aciklama` text NOT NULL,
  `sorumlu` varchar(100) DEFAULT NULL,
  `hedef_tarih` date DEFAULT NULL,
  `tamamlanma_tarihi` date DEFAULT NULL,
  `durum` varchar(20) DEFAULT 'bekliyor' COMMENT 'bekliyor, devam_ediyor, tamamlandi',
  `olusturan` varchar(50) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `sikayet_faaliyetleri`
--

INSERT INTO `sikayet_faaliyetleri` (`id`, `sikayet_id`, `faaliyet_tipi`, `aciklama`, `sorumlu`, `hedef_tarih`, `tamamlanma_tarihi`, `durum`, `olusturan`, `olusturma_tarihi`) VALUES
(1, 1, 'duzeltici', 'dsasda', 'asddsa', '2026-02-28', NULL, 'bekliyor', 'admin', '2026-02-26 12:19:30');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `silolar`
--

CREATE TABLE `silolar` (
  `id` int(11) NOT NULL,
  `silo_adi` varchar(100) NOT NULL,
  `tip` varchar(50) DEFAULT NULL,
  `kapasite_m3` decimal(10,2) DEFAULT NULL,
  `doluluk_m3` decimal(10,2) DEFAULT 0.00,
  `aktif_hammadde_kodu` varchar(50) DEFAULT NULL,
  `izin_verilen_hammadde_kodlari` text DEFAULT NULL,
  `min_doluluk_uyari` decimal(10,2) DEFAULT NULL,
  `max_doluluk_uyari` decimal(10,2) DEFAULT NULL,
  `son_temizlik_tarihi` date DEFAULT NULL,
  `durum` varchar(20) DEFAULT 'aktif',
  `sicaklik_sensor_id` varchar(50) DEFAULT NULL COMMENT 'Otomatik sensör entegrasyonu için',
  `yogunluk` decimal(10,3) DEFAULT 0.600
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `silolar`
--

INSERT INTO `silolar` (`id`, `silo_adi`, `tip`, `kapasite_m3`, `doluluk_m3`, `aktif_hammadde_kodu`, `izin_verilen_hammadde_kodlari`, `min_doluluk_uyari`, `max_doluluk_uyari`, `son_temizlik_tarihi`, `durum`, `sicaklik_sensor_id`, `yogunluk`) VALUES
(2, 'S1-B+K10', 'bugday', 304.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600),
(3, 'S2-K2 DİYARBAKIR', 'bugday', 304.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600),
(4, 'S3- K9 13,5', 'bugday', 304.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600),
(5, 'S4 - D1', 'bugday', 304.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600),
(6, 'S6 - B7 DP', 'bugday', 304.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600),
(7, 'S7 - K18+B', 'bugday', 304.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600),
(8, 'S5 - D4', 'bugday', 304.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600),
(9, 'S8 - K9 14', 'bugday', 304.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600),
(10, 'S9 B', 'bugday', 177.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600),
(11, 'S10 - K1', 'bugday', 177.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600),
(12, 'S11 ', 'bugday', 177.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600),
(13, 'S12 - B', 'bugday', 177.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600),
(14, 'S13 - D4', 'bugday', 177.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600),
(15, 'S14 - D1', 'bugday', 177.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, 0.600);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `silo_duzeltme_talepleri`
--

CREATE TABLE `silo_duzeltme_talepleri` (
  `id` int(11) NOT NULL,
  `hammadde_giris_id` int(11) NOT NULL,
  `parti_no` varchar(50) NOT NULL,
  `talep_nedeni` text NOT NULL,
  `talep_eden_user_id` int(11) NOT NULL,
  `durum` enum('bekliyor','onaylandi','reddedildi','uygulandi') NOT NULL DEFAULT 'bekliyor',
  `karar_veren_user_id` int(11) DEFAULT NULL,
  `karar_notu` text DEFAULT NULL,
  `onay_tarihi` datetime DEFAULT NULL,
  `uygulama_tarihi` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `silo_kabul_turleri`
--

CREATE TABLE `silo_kabul_turleri` (
  `id` int(11) NOT NULL,
  `silo_id` int(11) NOT NULL,
  `hammadde_turu` varchar(100) NOT NULL COMMENT 'Örn: Anadolu Kırmızı Sert, İthal Buğday vb.',
  `olusturma_tarihi` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `silo_sicaklik_nem`
--

CREATE TABLE `silo_sicaklik_nem` (
  `id` int(11) NOT NULL,
  `silo_id` int(11) NOT NULL,
  `olcum_tarihi` datetime DEFAULT current_timestamp(),
  `sicaklik` decimal(5,2) DEFAULT NULL,
  `nem` decimal(5,2) DEFAULT NULL,
  `olcum_yapan` varchar(50) DEFAULT NULL,
  `uyari` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `silo_stok_detay`
--

CREATE TABLE `silo_stok_detay` (
  `id` int(11) NOT NULL,
  `silo_id` int(11) NOT NULL,
  `parti_kodu` varchar(50) NOT NULL COMMENT 'Hammadde giriş parti kodu örn: D-6001',
  `hammadde_turu` varchar(100) NOT NULL,
  `giren_miktar_kg` decimal(10,2) NOT NULL COMMENT 'Bu partiden siloya giren ilk miktar',
  `kalan_miktar_kg` decimal(10,2) NOT NULL COMMENT 'Üretim vb. oldukça düşülecek olan güncel miktar',
  `giris_tarihi` datetime NOT NULL,
  `durum` enum('aktif','tükendi') DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `siparisler`
--

CREATE TABLE `siparisler` (
  `id` int(11) NOT NULL,
  `musteri_id` int(11) NOT NULL,
  `alici_adi` varchar(255) DEFAULT NULL,
  `siparis_kodu` varchar(20) DEFAULT NULL,
  `siparis_tarihi` date NOT NULL,
  `teslim_tarihi` date DEFAULT NULL,
  `odeme_tarihi` date DEFAULT NULL,
  `durum` varchar(20) DEFAULT 'Bekliyor',
  `toplam_tutar` decimal(10,2) DEFAULT 0.00,
  `aciklama` text DEFAULT NULL,
  `olusturan_user_id` int(11) DEFAULT NULL,
  `genel_toplam` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `siparis_detaylari`
--

CREATE TABLE `siparis_detaylari` (
  `id` int(11) NOT NULL,
  `siparis_id` int(11) NOT NULL,
  `urun_adi` varchar(100) NOT NULL,
  `miktar` int(11) NOT NULL,
  `birim` varchar(10) DEFAULT 'Adet',
  `birim_fiyat` decimal(10,2) DEFAULT NULL,
  `toplam_fiyat` decimal(10,2) DEFAULT NULL,
  `sevk_edilen_miktar` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `spesifikasyonlar`
--

CREATE TABLE `spesifikasyonlar` (
  `id` int(11) NOT NULL,
  `urun_id` int(11) NOT NULL,
  `musteri_id` int(11) DEFAULT NULL,
  `musteri_adi` varchar(100) DEFAULT NULL,
  `versiyon` varchar(20) DEFAULT NULL,
  `onay_tarihi` date DEFAULT NULL,
  `gecerlilik_tarihi` date DEFAULT NULL,
  `belge_path` varchar(255) DEFAULT NULL COMMENT 'PDF yolu',
  `detaylar` text DEFAULT NULL COMMENT 'JSON formatında tüm spec değerleri',
  `aktif` tinyint(1) DEFAULT 1,
  `olusturma_tarihi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `stok_hareketleri`
--

CREATE TABLE `stok_hareketleri` (
  `id` int(11) NOT NULL,
  `malzeme_tipi` varchar(50) NOT NULL COMMENT 'ambalaj, hammadde, urun, katki, sarf',
  `malzeme_id` int(11) NOT NULL,
  `hareket_tipi` varchar(20) NOT NULL COMMENT 'giris, cikis',
  `miktar` decimal(10,2) NOT NULL,
  `birim` varchar(20) DEFAULT NULL,
  `ilgili_islem` varchar(50) DEFAULT NULL COMMENT 'paketleme, uretim, fire, iade',
  `ilgili_islem_id` int(11) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `islem_tarihi` datetime DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL COMMENT 'INSERT, UPDATE, DELETE, LOGIN, APPROVAL, REJECT',
  `module` varchar(50) NOT NULL COMMENT 'Page or Feature Name',
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action_type`, `module`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'LOGOUT', 'auth', 'Kullanıcı çıkışı: admin', '192.168.1.90', '2026-02-24 09:23:56'),
(2, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.90', '2026-02-24 09:28:22'),
(3, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-24 09:35:50'),
(4, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.90', '2026-02-24 09:49:42'),
(5, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-24 10:06:54'),
(6, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-24 11:55:05'),
(7, 1, 'INSERT', 'Hammadde Kabul', 'Yeni araç girişi: 272TEST27 | Tedarikçi: X FİRMASI | Hammadde: ZENİT | 0 kg', '192.168.1.90', '2026-02-24 12:03:19'),
(8, 1, 'INSERT', 'Lab Analizleri', 'Yeni analiz kaydi: Parti No: D6-00001 | Protein: 12.5% | Gluten: 23%', '192.168.1.90', '2026-02-24 12:04:14'),
(9, 1, 'UPDATE', 'Lab Referans', 'Referans spekt degerleri guncellendi', '192.168.1.149', '2026-02-24 12:04:49'),
(10, 1, 'UPDATE', 'Lab Referans', 'Referans spekt degerleri guncellendi', '192.168.1.149', '2026-02-24 12:04:58'),
(11, 1, 'UPDATE', 'Lab Referans', 'Referans spekt degerleri guncellendi', '192.168.1.149', '2026-02-24 12:05:04'),
(12, 1, 'UPDATE', 'Lab Referans', 'Referans spekt degerleri guncellendi', '192.168.1.149', '2026-02-24 12:13:11'),
(13, 1, 'UPDATE', 'Lab Referans', 'Referans spekt degerleri guncellendi', '192.168.1.149', '2026-02-24 12:33:52'),
(14, 1, 'UPDATE', 'Lab Referans', 'Referans spekt degerleri guncellendi', '192.168.1.149', '2026-02-24 12:34:05'),
(15, 1, 'UPDATE', 'Lab Referans', 'Referans spekt degerleri guncellendi', '192.168.1.149', '2026-02-24 12:34:15'),
(16, 1, 'UPDATE', 'Lab Referans', 'Referans spekt degerleri guncellendi', '192.168.1.149', '2026-02-24 12:34:20'),
(17, 1, 'INSERT', 'Hammadde Kabul', 'Yeni araç girişi: 34EA2525 | Tedarikçi: SADAS | Hammadde: VBURGAZ | 0 kg', '192.168.1.90', '2026-02-24 12:43:12'),
(18, 1, 'INSERT', 'Lab Analizleri', 'Yeni analiz kaydi: Parti No: D6-0002 | Protein: 21% | Gluten: 2%', '192.168.1.90', '2026-02-24 12:43:36'),
(19, 1, 'INSERT', 'Hammadde Kabul', 'Yeni araç girişi: 232 | Tedarikçi: ddfs | Hammadde: PANDAS | 0 kg', '192.168.1.90', '2026-02-24 13:15:53'),
(20, 1, 'INSERT', 'Hammadde Kabul', 'Yeni araç girişi: SDASD | Tedarikçi: 23 | Hammadde: PANDAS | 0 kg', '192.168.1.90', '2026-02-24 13:16:20'),
(21, 1, 'LOGOUT', 'auth', 'Kullanıcı çıkışı: admin', '192.168.1.90', '2026-02-24 13:17:08'),
(22, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.90', '2026-02-26 07:23:25'),
(23, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-26 07:27:30'),
(24, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.90', '2026-02-26 07:33:53'),
(25, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-26 09:14:14'),
(26, 1, 'INSERT', 'Şikayetler', 'Yeni şikayet kaydı eklendi: DOF-2026-001 (asddsa)', '192.168.1.149', '2026-02-26 09:18:06'),
(27, 1, 'UPDATE', 'Şikayetler', 'Şikayet kök neden/DÖF alanı güncellendi. No: DOF-2026-001', '192.168.1.149', '2026-02-26 09:18:53'),
(28, 1, 'UPDATE', 'Şikayetler', 'Şikayet kök neden/DÖF alanı güncellendi. No: DOF-2026-001', '192.168.1.149', '2026-02-26 09:19:06'),
(29, 1, 'UPDATE', 'Şikayetler', 'Şikayet kök neden/DÖF alanı güncellendi. No: DOF-2026-001', '192.168.1.149', '2026-02-26 09:19:39'),
(30, 1, 'UPDATE', 'Şikayetler', 'Şikayet kök neden/DÖF alanı güncellendi. No: DOF-2026-001', '192.168.1.149', '2026-02-26 09:19:48'),
(31, 1, 'UPDATE', 'Şikayetler', 'Şikayet KAPATILDI. No: DOF-2026-001', '192.168.1.149', '2026-02-26 09:19:53'),
(32, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-26 10:44:57'),
(33, 1, 'INSERT', 'Şikayetler', 'Yeni şikayet kaydı eklendi: DOF-2026-002 (saddas)', '192.168.1.149', '2026-02-26 10:45:51'),
(34, 1, 'UPDATE', 'Şikayetler', 'Şikayet kök neden/DÖF alanı güncellendi. No: DOF-2026-002', '192.168.1.149', '2026-02-26 10:46:15'),
(35, 1, 'UPDATE', 'Şikayetler', 'Şikayet kök neden/DÖF alanı güncellendi. No: DOF-2026-002', '192.168.1.149', '2026-02-26 10:48:15'),
(36, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-26 12:23:27'),
(37, 1, 'UPDATE', 'Lab Analizleri', 'Analiz guncellendi: ID: 2 | Parti No: D6-0002 | Not: ', '192.168.1.149', '2026-02-26 12:26:24'),
(38, 1, 'UPDATE', 'Lab Analizleri', 'Analiz guncellendi: ID: 2 | Parti No: D6-0002 | Not: ', '192.168.1.149', '2026-02-26 12:28:20'),
(39, 1, 'LOGOUT', 'auth', 'Kullanıcı çıkışı: admin', '192.168.1.149', '2026-02-26 12:29:17'),
(40, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-26 12:29:29'),
(41, 1, 'LOGOUT', 'auth', 'Kullanıcı çıkışı: admin', '192.168.1.149', '2026-02-26 12:31:21'),
(42, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-26 12:36:56'),
(43, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-27 08:07:51'),
(44, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-27 08:10:53'),
(45, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-27 08:59:36'),
(46, 1, 'LOGOUT', 'auth', 'Kullanıcı çıkışı: admin', '192.168.1.149', '2026-02-27 09:11:12'),
(47, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-27 09:11:16'),
(48, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-27 11:25:53'),
(49, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.90', '2026-02-27 11:36:05'),
(50, 1, 'LOGOUT', 'auth', 'Kullanıcı çıkışı: admin', '192.168.1.90', '2026-02-27 11:36:18'),
(51, 2, 'LOGIN', 'auth', 'Kullanıcı girişi: test', '192.168.1.90', '2026-02-27 11:36:23'),
(52, 2, 'LOGOUT', 'auth', 'Kullanıcı çıkışı: test', '192.168.1.90', '2026-02-27 11:36:38'),
(53, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-02-27 11:43:36'),
(54, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.90', '2026-02-28 13:46:03'),
(55, 1, 'LOGOUT', 'auth', 'Kullanıcı çıkışı: admin', '192.168.1.90', '2026-02-28 13:46:47'),
(56, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '192.168.1.149', '2026-03-02 07:37:12'),
(57, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '::1', '2026-03-12 07:59:19'),
(58, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '::1', '2026-03-12 08:35:58'),
(59, 1, 'INSERT', 'Lab Analizleri', 'Yeni analiz kaydi: Parti No: K2-0003 | Protein: -% | Gluten: -%', '::1', '2026-03-12 08:53:31'),
(60, 1, 'UPDATE', 'Lab Analizleri', 'Analiz guncellendi: ID: 3 | Parti No: K2-0003 | Not: ', '::1', '2026-03-12 08:54:01'),
(61, 1, 'UPDATE', 'Lab Analizleri', 'Analiz guncellendi: ID: 3 | Parti No: K2-0003 | Not: ', '::1', '2026-03-12 08:54:06'),
(62, 1, 'REJECT', 'Hammadde Kabul', 'Hammadde reddedildi | Akis ID: 1 | Sebep: ret', '::1', '2026-03-12 08:59:02'),
(63, 1, 'REJECT', 'Hammadde Kabul', 'Hammadde reddedildi | Akis ID: 2 | Sebep: sda', '::1', '2026-03-12 08:59:12'),
(64, 1, 'REJECT', 'Hammadde Kabul', 'Hammadde reddedildi | Akis ID: 3 | Sebep: sda', '::1', '2026-03-12 08:59:15'),
(65, 1, 'INSERT', 'Lab Analizleri', 'Yeni analiz kaydi: Parti No: K2-0001 | Protein: -% | Gluten: -%', '::1', '2026-03-12 08:59:44'),
(66, 1, 'INSERT', 'Hammadde Kabul', 'Yeni araç girişi: ASDASDSAD | Tedarikçi: sadsaads | Hammadde: İTHAL | 0 kg', '::1', '2026-03-12 09:00:56'),
(67, 1, 'INSERT', 'Lab Analizleri', 'Yeni analiz kaydi: Parti No: D10-0001 | Protein: -% | Gluten: -%', '::1', '2026-03-12 09:01:09'),
(68, 1, 'APPROVAL', 'Hammadde Kabul', 'Hammadde onaylandi | Akis ID: 5', '::1', '2026-03-12 09:02:13'),
(69, 1, 'UPDATE', 'Hammadde Kabul', 'Kantar+Fiyat güncellendi | Akis ID: 5', '::1', '2026-03-12 09:13:34'),
(70, 1, 'PURCHASE', 'Hammadde Alım Onayı (Kantar)', 'Akış ID: 5 onaylandı. Miktar: 5000 kg', '::1', '2026-03-12 09:14:08'),
(71, 1, 'UPDATE', 'Hammadde Kabul', 'Fiyat/Ödeme tarihi güncellendi | Akis ID: 5', '::1', '2026-03-12 09:54:01'),
(72, 1, 'UPDATE', 'Lab Analizleri', 'Analiz guncellendi: ID: 3 | Parti No: K2-0003 | Not: ', '::1', '2026-03-12 09:54:58'),
(73, 1, 'INSERT', 'Hammadde Kabul', 'Yeni araç girişi: ASDDSA | Tedarikçi: testtttt | Hammadde: SEGOTORİA | 0 kg', '::1', '2026-03-12 09:55:24'),
(74, 1, 'INSERT', 'Lab Analizleri', 'Yeni analiz kaydi: Parti No: K1-0001 | Protein: -% | Gluten: -%', '::1', '2026-03-12 09:55:35'),
(75, 1, 'REJECT', 'Hammadde Kabul', 'Hammadde reddedildi | Akis ID: 4 | Sebep: dsasda', '::1', '2026-03-12 09:55:57'),
(76, 1, 'APPROVAL', 'Hammadde Kabul', 'Hammadde onaylandi | Akis ID: 6', '::1', '2026-03-12 09:56:32'),
(77, 1, 'PURCHASE', 'Hammadde Alım Reddi', 'Akış ID: 6 reddedildi. Sebep: öyle', '::1', '2026-03-12 09:57:46'),
(78, 1, 'INSERT', 'Hammadde Kabul', 'Yeni araç girişi: 27 TEST 27 | Tedarikçi: Deneme | Hammadde: VBURGAZ | 0 kg', '::1', '2026-03-12 09:58:43'),
(79, 1, 'INSERT', 'Lab Analizleri', 'Yeni analiz kaydi: Parti No: D1-0001 | Protein: -% | Gluten: -%', '::1', '2026-03-12 09:58:59'),
(80, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '::1', '2026-03-12 11:06:26'),
(81, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '::1', '2026-03-23 08:35:17'),
(82, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '::1', '2026-03-23 08:36:06'),
(83, 1, 'LOGIN', 'auth', 'Kullanıcı girişi: admin', '::1', '2026-03-23 09:49:37');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `temizlik_kayitlari`
--

CREATE TABLE `temizlik_kayitlari` (
  `id` int(11) NOT NULL,
  `tarih` date NOT NULL,
  `vardiya` varchar(20) DEFAULT NULL,
  `alan` varchar(100) NOT NULL COMMENT 'uretim_hatti, depo, silo, vs.',
  `temizlik_turu` varchar(50) DEFAULT NULL COMMENT 'gunluk, ozel_alerjen',
  `alerjen_iceren_urun` tinyint(1) DEFAULT 0,
  `alerjen_detay` varchar(200) DEFAULT NULL,
  `temizlik_yapan` varchar(50) NOT NULL,
  `onaylayan` varchar(50) DEFAULT NULL,
  `onay_zamani` datetime DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `kayit_zamani` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `un_cikis_kayitlari`
--

CREATE TABLE `un_cikis_kayitlari` (
  `id` int(11) NOT NULL,
  `parti_no` varchar(50) NOT NULL,
  `b1_id` int(11) DEFAULT NULL COMMENT 'FK → b1_degirmen_kayitlari',
  `cikis_tarihi` datetime DEFAULT current_timestamp(),
  `protein` decimal(5,2) DEFAULT NULL,
  `personel` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_b1`
--

CREATE TABLE `uretim_b1` (
  `id` int(11) NOT NULL,
  `tavlama_3_id` int(11) NOT NULL,
  `baslama_tarihi` datetime NOT NULL,
  `bitis_tarihi` datetime DEFAULT NULL,
  `su_derecesi` decimal(5,2) DEFAULT NULL,
  `ortam_derecesi` decimal(5,2) DEFAULT NULL,
  `b1_tonaj` decimal(10,2) DEFAULT NULL,
  `karisim_degerleri` varchar(255) DEFAULT NULL,
  `olusturan` varchar(100) DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_b1_detay`
--

CREATE TABLE `uretim_b1_detay` (
  `id` int(11) NOT NULL,
  `b1_id` int(11) NOT NULL,
  `yas_ambar_no` varchar(50) DEFAULT NULL,
  `hedef_nem` decimal(5,2) DEFAULT NULL,
  `nem` decimal(5,2) DEFAULT NULL,
  `gluten` decimal(5,2) DEFAULT NULL,
  `g_index` int(11) DEFAULT NULL,
  `n_sedim` int(11) DEFAULT NULL,
  `g_sedim` int(11) DEFAULT NULL,
  `hektolitre` decimal(5,2) DEFAULT NULL,
  `alveo_p` decimal(5,2) DEFAULT NULL,
  `alveo_g` decimal(5,2) DEFAULT NULL,
  `alveo_pl` decimal(5,2) DEFAULT NULL,
  `alveo_w` int(11) DEFAULT NULL,
  `alveo_ie` decimal(5,2) DEFAULT NULL,
  `fn` int(11) DEFAULT NULL,
  `perten_protein` decimal(5,2) DEFAULT NULL,
  `perten_sertlik` decimal(5,2) DEFAULT NULL,
  `perten_nisasta` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_ciktilari`
--

CREATE TABLE `uretim_ciktilari` (
  `id` int(11) NOT NULL,
  `uretim_id` int(11) NOT NULL,
  `urun_tipi` varchar(10) NOT NULL COMMENT 'ana, yan',
  `urun_id` int(11) NOT NULL,
  `miktar_kg` decimal(10,2) NOT NULL,
  `hedef_silo_id` int(11) DEFAULT NULL,
  `m3_deger` decimal(10,3) DEFAULT NULL COMMENT 'Otomatik hesaplanan',
  `kayit_tarihi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_hareketleri`
--

CREATE TABLE `uretim_hareketleri` (
  `id` int(11) NOT NULL,
  `parti_no` varchar(50) DEFAULT NULL,
  `yikama_parti_no` varchar(50) DEFAULT NULL COMMENT 'Hangi yıkama partisinden üretim yapıldı',
  `is_emri_id` int(11) DEFAULT NULL,
  `tarih` datetime DEFAULT current_timestamp(),
  `uretilen_miktar_kg` decimal(10,2) DEFAULT NULL,
  `kullanilan_kg` decimal(10,2) DEFAULT NULL,
  `personel` varchar(100) DEFAULT NULL,
  `notlar` text DEFAULT NULL,
  `cikis_silo_id` int(11) DEFAULT NULL,
  `giris_silo_id` int(11) DEFAULT NULL,
  `uretilen_kg` decimal(10,2) DEFAULT NULL,
  `randiman` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_pacal`
--

CREATE TABLE `uretim_pacal` (
  `id` int(11) NOT NULL,
  `tarih` date NOT NULL,
  `urun_adi` varchar(100) NOT NULL,
  `parti_no` varchar(100) NOT NULL,
  `toplam_miktar_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notlar` text DEFAULT NULL,
  `durum` varchar(50) NOT NULL DEFAULT 'hazirlaniyor',
  `olusturan` varchar(100) DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_pacal_detay`
--

CREATE TABLE `uretim_pacal_detay` (
  `id` int(11) NOT NULL,
  `pacal_id` int(11) NOT NULL,
  `sira_no` int(11) NOT NULL,
  `hammadde_id` int(11) NOT NULL,
  `hammadde_parti_no` varchar(100) DEFAULT NULL,
  `kod` varchar(100) DEFAULT NULL,
  `yoresi` varchar(100) DEFAULT NULL,
  `miktar_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `oran` decimal(5,2) NOT NULL DEFAULT 0.00,
  `kirli_ambar_no` varchar(50) DEFAULT NULL,
  `yas_ambar_no` varchar(50) DEFAULT NULL,
  `gluten` decimal(10,2) DEFAULT NULL,
  `g_index` int(11) DEFAULT NULL,
  `n_sedim` int(11) DEFAULT NULL,
  `g_sedim` int(11) DEFAULT NULL,
  `hektolitre` decimal(10,2) DEFAULT NULL,
  `nem` decimal(10,2) DEFAULT NULL,
  `alveo_p` decimal(10,2) DEFAULT NULL,
  `alveo_g` decimal(10,2) DEFAULT NULL,
  `alveo_pl` decimal(10,2) DEFAULT NULL,
  `alveo_w` int(11) DEFAULT NULL,
  `alveo_ie` decimal(10,2) DEFAULT NULL,
  `fn` int(11) DEFAULT NULL,
  `perten_protein` decimal(10,2) DEFAULT NULL,
  `perten_sertlik` decimal(10,2) DEFAULT NULL,
  `perten_nisasta` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_silo_cikis_log`
--

CREATE TABLE `uretim_silo_cikis_log` (
  `id` int(11) NOT NULL,
  `uretim_parti_no` varchar(50) NOT NULL COMMENT 'Üretim (TAV vs) fiş numarası',
  `silo_id` int(11) NOT NULL,
  `kaynak_parti_kodu` varchar(50) NOT NULL COMMENT 'Silo içindeki hammadde parti kodu (FIFO düşülen)',
  `cikis_miktari_brut_kg` decimal(10,2) NOT NULL,
  `elek_alti_fire_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cikis_miktari_net_kg` decimal(10,2) GENERATED ALWAYS AS (`cikis_miktari_brut_kg` - `elek_alti_fire_kg`) STORED,
  `islem_tarihi` timestamp NULL DEFAULT current_timestamp(),
  `kullanici` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_tavlama_1`
--

CREATE TABLE `uretim_tavlama_1` (
  `id` int(11) NOT NULL,
  `pacal_id` int(11) NOT NULL,
  `baslama_tarihi` datetime NOT NULL,
  `bitis_tarihi` datetime DEFAULT NULL,
  `su_derecesi` decimal(5,2) DEFAULT NULL,
  `ortam_derecesi` decimal(5,2) DEFAULT NULL,
  `toplam_tonaj` decimal(10,2) DEFAULT NULL,
  `karisim_degerleri` varchar(255) DEFAULT NULL,
  `olusturan` varchar(100) DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_tavlama_1_detay`
--

CREATE TABLE `uretim_tavlama_1_detay` (
  `id` int(11) NOT NULL,
  `tavlama_1_id` int(11) NOT NULL,
  `yas_ambar_no` varchar(50) DEFAULT NULL,
  `hedef_nem` decimal(5,2) DEFAULT NULL,
  `nem` decimal(5,2) DEFAULT NULL,
  `gluten` decimal(5,2) DEFAULT NULL,
  `g_index` int(11) DEFAULT NULL,
  `n_sedim` int(11) DEFAULT NULL,
  `g_sedim` int(11) DEFAULT NULL,
  `hektolitre` decimal(5,2) DEFAULT NULL,
  `alveo_p` decimal(5,2) DEFAULT NULL,
  `alveo_g` decimal(5,2) DEFAULT NULL,
  `alveo_pl` decimal(5,2) DEFAULT NULL,
  `alveo_w` int(11) DEFAULT NULL,
  `alveo_ie` decimal(5,2) DEFAULT NULL,
  `fn` int(11) DEFAULT NULL,
  `perten_protein` decimal(5,2) DEFAULT NULL,
  `perten_sertlik` decimal(5,2) DEFAULT NULL,
  `perten_nisasta` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_tavlama_2`
--

CREATE TABLE `uretim_tavlama_2` (
  `id` int(11) NOT NULL,
  `tavlama_1_id` int(11) NOT NULL,
  `baslama_tarihi` datetime NOT NULL,
  `bitis_tarihi` datetime DEFAULT NULL,
  `su_derecesi` decimal(5,2) DEFAULT NULL,
  `ortam_derecesi` decimal(5,2) DEFAULT NULL,
  `toplam_tonaj` decimal(10,2) DEFAULT NULL,
  `karisim_degerleri` varchar(255) DEFAULT NULL,
  `olusturan` varchar(100) DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_tavlama_2_detay`
--

CREATE TABLE `uretim_tavlama_2_detay` (
  `id` int(11) NOT NULL,
  `tavlama_2_id` int(11) NOT NULL,
  `yas_ambar_no` varchar(50) DEFAULT NULL,
  `hedef_nem` decimal(5,2) DEFAULT NULL,
  `nem` decimal(5,2) DEFAULT NULL,
  `gluten` decimal(5,2) DEFAULT NULL,
  `g_index` int(11) DEFAULT NULL,
  `n_sedim` int(11) DEFAULT NULL,
  `g_sedim` int(11) DEFAULT NULL,
  `hektolitre` decimal(5,2) DEFAULT NULL,
  `alveo_p` decimal(5,2) DEFAULT NULL,
  `alveo_g` decimal(5,2) DEFAULT NULL,
  `alveo_pl` decimal(5,2) DEFAULT NULL,
  `alveo_w` int(11) DEFAULT NULL,
  `alveo_ie` decimal(5,2) DEFAULT NULL,
  `fn` int(11) DEFAULT NULL,
  `perten_protein` decimal(5,2) DEFAULT NULL,
  `perten_sertlik` decimal(5,2) DEFAULT NULL,
  `perten_nisasta` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_tavlama_3`
--

CREATE TABLE `uretim_tavlama_3` (
  `id` int(11) NOT NULL,
  `tavlama_2_id` int(11) NOT NULL,
  `baslama_tarihi` datetime NOT NULL,
  `bitis_tarihi` datetime DEFAULT NULL,
  `su_derecesi` decimal(5,2) DEFAULT NULL,
  `ortam_derecesi` decimal(5,2) DEFAULT NULL,
  `toplam_tonaj` decimal(10,2) DEFAULT NULL,
  `karisim_degerleri` varchar(255) DEFAULT NULL,
  `olusturan` varchar(100) DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_tavlama_3_detay`
--

CREATE TABLE `uretim_tavlama_3_detay` (
  `id` int(11) NOT NULL,
  `tavlama_3_id` int(11) NOT NULL,
  `yas_ambar_no` varchar(50) DEFAULT NULL,
  `hedef_nem` decimal(5,2) DEFAULT NULL,
  `nem` decimal(5,2) DEFAULT NULL,
  `gluten` decimal(5,2) DEFAULT NULL,
  `g_index` int(11) DEFAULT NULL,
  `n_sedim` int(11) DEFAULT NULL,
  `g_sedim` int(11) DEFAULT NULL,
  `hektolitre` decimal(5,2) DEFAULT NULL,
  `alveo_p` decimal(5,2) DEFAULT NULL,
  `alveo_g` decimal(5,2) DEFAULT NULL,
  `alveo_pl` decimal(5,2) DEFAULT NULL,
  `alveo_w` int(11) DEFAULT NULL,
  `alveo_ie` decimal(5,2) DEFAULT NULL,
  `fn` int(11) DEFAULT NULL,
  `perten_protein` decimal(5,2) DEFAULT NULL,
  `perten_sertlik` decimal(5,2) DEFAULT NULL,
  `perten_nisasta` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_un1`
--

CREATE TABLE `uretim_un1` (
  `id` int(11) NOT NULL,
  `b1_id` int(11) NOT NULL,
  `numune_saati` datetime NOT NULL,
  `olusturan` varchar(100) DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uretim_un1_detay`
--

CREATE TABLE `uretim_un1_detay` (
  `id` int(11) NOT NULL,
  `un1_id` int(11) NOT NULL,
  `silo_no` varchar(50) DEFAULT NULL,
  `miktar_kg` decimal(10,2) DEFAULT NULL,
  `gluten` decimal(5,2) DEFAULT NULL,
  `g_index` int(11) DEFAULT NULL,
  `n_sedim` int(11) DEFAULT NULL,
  `g_sedim` int(11) DEFAULT NULL,
  `fn` int(11) DEFAULT NULL,
  `ffn` int(11) DEFAULT NULL,
  `s_d` decimal(5,2) DEFAULT NULL,
  `perten_nem` decimal(5,2) DEFAULT NULL,
  `perten_kul` decimal(5,2) DEFAULT NULL,
  `perten_nisasta` decimal(5,2) DEFAULT NULL,
  `perten_renk_b` decimal(5,2) DEFAULT NULL,
  `perten_renk_l` decimal(5,2) DEFAULT NULL,
  `perten_protein` decimal(5,2) DEFAULT NULL,
  `cons_su_kaldirma` decimal(5,2) DEFAULT NULL,
  `cons_tol` decimal(5,2) DEFAULT NULL,
  `alveo_t` decimal(5,2) DEFAULT NULL,
  `alveo_a` decimal(5,2) DEFAULT NULL,
  `alveo_ta` decimal(5,2) DEFAULT NULL,
  `alveo_w` int(11) DEFAULT NULL,
  `alveo_ie` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `urunler`
--

CREATE TABLE `urunler` (
  `id` int(11) NOT NULL,
  `urun_adi` varchar(100) NOT NULL,
  `birim` varchar(20) DEFAULT 'kg',
  `stok_miktar` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `kadi` varchar(50) NOT NULL,
  `sifre` varchar(255) NOT NULL,
  `yetki` varchar(50) DEFAULT 'personel',
  `tam_ad` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `rol_id` int(11) DEFAULT NULL,
  `aktif` tinyint(4) DEFAULT 1,
  `olusturma_tarihi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `kadi`, `sifre`, `yetki`, `tam_ad`, `email`, `telefon`, `rol_id`, `aktif`, `olusturma_tarihi`) VALUES
(1, 'admin', 'e10adc3949ba59abbe56e057f20f883e', 'admin', 'Sistem Yöneticisi', 'admin@ozbalun.com', '05550000000', 1, 1, '2026-02-01 11:58:29'),
(2, 'test', '81dc9bdb52d04dc20036dbd8313ed055', 'personel', 'test', 'dsadas@gmail.com', '05550000000', 5, 1, '2026-02-27 14:29:28');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `web_yuklemeler`
--

CREATE TABLE `web_yuklemeler` (
  `id` int(11) NOT NULL,
  `musteri_adi` varchar(100) DEFAULT NULL,
  `musteri_id` int(11) DEFAULT NULL,
  `urun_id` int(11) DEFAULT NULL,
  `miktar_ton` decimal(10,2) DEFAULT NULL,
  `teslim_tarihi` date DEFAULT NULL,
  `ozel_talepler` text DEFAULT NULL,
  `api_kayit_tarihi` datetime DEFAULT current_timestamp(),
  `durum` varchar(20) DEFAULT 'yeni'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `yabanci_madde_kontrolleri`
--

CREATE TABLE `yabanci_madde_kontrolleri` (
  `id` int(11) NOT NULL,
  `tarih` date NOT NULL,
  `vardiya` varchar(20) DEFAULT NULL,
  `kontrol_turu` varchar(50) DEFAULT NULL COMMENT 'cam, sert_plastik, metal',
  `alan` varchar(100) NOT NULL,
  `sonuc` varchar(20) DEFAULT NULL COMMENT 'uygun, uygunsuzluk',
  `tespit_detay` text DEFAULT NULL,
  `kontrol_eden` varchar(50) NOT NULL,
  `kontrol_saati` time DEFAULT NULL,
  `kayit_zamani` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `yikama_islemleri`
--

CREATE TABLE `yikama_islemleri` (
  `id` int(11) NOT NULL,
  `parti_no` varchar(50) NOT NULL,
  `is_emri_id` int(11) DEFAULT NULL,
  `yikama_giris_kg` decimal(10,2) NOT NULL COMMENT 'Paçaldan gelen değer',
  `cikis_silo_id` int(11) NOT NULL,
  `cikis_m3` decimal(10,3) DEFAULT NULL COMMENT 'Otomatik hesaplanan',
  `tavlama_su_miktar` decimal(10,2) DEFAULT NULL,
  `tavlama_sure` int(11) DEFAULT NULL COMMENT 'Dakika',
  `islem_tarihi` datetime DEFAULT current_timestamp(),
  `operatorler` varchar(200) DEFAULT NULL,
  `notlar` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `yikama_kayitlari`
--

CREATE TABLE `yikama_kayitlari` (
  `id` int(11) NOT NULL,
  `parti_no` varchar(50) NOT NULL,
  `hammadde_parti_no` varchar(50) DEFAULT NULL COMMENT 'Hangi hammadde partisinden yıkama yapıldı',
  `yikama_tarihi` datetime DEFAULT current_timestamp(),
  `urun_adi` varchar(100) DEFAULT NULL COMMENT 'Buğday cinsi',
  `hektolitre` decimal(6,2) DEFAULT NULL,
  `nem` decimal(5,2) DEFAULT NULL,
  `protein` decimal(5,2) DEFAULT NULL,
  `sertlik` decimal(5,2) DEFAULT NULL,
  `nisasta` decimal(5,2) DEFAULT NULL,
  `tam_bugday_kg` decimal(10,2) DEFAULT NULL,
  `tohum_miktari_kg` decimal(10,2) DEFAULT NULL,
  `su_sicakligi` decimal(5,2) DEFAULT NULL COMMENT '°C',
  `personel` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `aktarma_kayitlari`
--
ALTER TABLE `aktarma_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parti` (`parti_no`),
  ADD KEY `yikama_id` (`yikama_id`);

--
-- Tablo için indeksler `ambalajlar`
--
ALTER TABLE `ambalajlar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ambalaj_kodu` (`ambalaj_kodu`);

--
-- Tablo için indeksler `ambalaj_partileri`
--
ALTER TABLE `ambalaj_partileri`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `parti_no` (`parti_no`),
  ADD KEY `ambalaj_id` (`ambalaj_id`);

--
-- Tablo için indeksler `ambalaj_testleri`
--
ALTER TABLE `ambalaj_testleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ambalaj_parti_id` (`ambalaj_parti_id`);

--
-- Tablo için indeksler `b1_degirmen_kayitlari`
--
ALTER TABLE `b1_degirmen_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parti` (`parti_no`),
  ADD KEY `aktarma_id` (`aktarma_id`);

--
-- Tablo için indeksler `bakim_kayitlari`
--
ALTER TABLE `bakim_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `makine_id` (`makine_id`);

--
-- Tablo için indeksler `bakim_lab_malzemeler`
--
ALTER TABLE `bakim_lab_malzemeler`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `bildirimler`
--
ALTER TABLE `bildirimler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hedef_rol` (`hedef_rol_id`),
  ADD KEY `idx_hedef_user` (`hedef_user_id`),
  ADD KEY `idx_okundu` (`okundu`),
  ADD KEY `idx_tarih` (`olusturma_tarihi`);

--
-- Tablo için indeksler `ccp_kayitlari`
--
ALTER TABLE `ccp_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parti` (`parti_no`);

--
-- Tablo için indeksler `depolar`
--
ALTER TABLE `depolar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `depo_kodu` (`depo_kodu`);

--
-- Tablo için indeksler `depo_sicaklik_nem`
--
ALTER TABLE `depo_sicaklik_nem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `depo_id` (`depo_id`),
  ADD KEY `idx_tarih` (`olcum_tarihi`);

--
-- Tablo için indeksler `depo_stok`
--
ALTER TABLE `depo_stok`
  ADD PRIMARY KEY (`id`),
  ADD KEY `urun_id` (`urun_id`),
  ADD KEY `depo_id` (`depo_id`);

--
-- Tablo için indeksler `gunluk_kontroller`
--
ALTER TABLE `gunluk_kontroller`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `hammaddeler`
--
ALTER TABLE `hammaddeler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hammadde_kodu` (`hammadde_kodu`);

--
-- Tablo için indeksler `hammadde_girisleri`
--
ALTER TABLE `hammadde_girisleri`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_parti_no_hammadde` (`parti_no`),
  ADD KEY `hammadde_id` (`hammadde_id`),
  ADD KEY `silo_id` (`silo_id`);

--
-- Tablo için indeksler `hammadde_kabul_akisi`
--
ALTER TABLE `hammadde_kabul_akisi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hammadde_giris` (`hammadde_giris_id`),
  ADD KEY `idx_asama` (`asama`),
  ADD KEY `idx_onay_durum` (`onay_durum`);

--
-- Tablo için indeksler `hammadde_kabul_gecmisi`
--
ALTER TABLE `hammadde_kabul_gecmisi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_akis` (`akis_id`);

--
-- Tablo için indeksler `helal_kayitlari`
--
ALTER TABLE `helal_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parti` (`parti_no`);

--
-- Tablo için indeksler `islem_loglari`
--
ALTER TABLE `islem_loglari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_tarih` (`islem_zamani`);

--
-- Tablo için indeksler `is_emirleri`
--
ALTER TABLE `is_emirleri`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `is_kodu` (`is_kodu`),
  ADD KEY `recete_id` (`recete_id`),
  ADD KEY `onaylayan_user_id` (`onaylayan_user_id`),
  ADD KEY `idx_yikama_parti` (`yikama_parti_no`);

--
-- Tablo için indeksler `is_emri_silo_karisimlari`
--
ALTER TABLE `is_emri_silo_karisimlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `is_emri_id` (`is_emri_id`),
  ADD KEY `silo_id` (`silo_id`);

--
-- Tablo için indeksler `kkn_kayitlari`
--
ALTER TABLE `kkn_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parti` (`parti_no`);

--
-- Tablo için indeksler `kullanici_bildirim_durumlari`
--
ALTER TABLE `kullanici_bildirim_durumlari`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_bildirim` (`user_id`,`bildirim_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `bildirim_id` (`bildirim_id`);

--
-- Tablo için indeksler `kullanici_modul_yetkileri`
--
ALTER TABLE `kullanici_modul_yetkileri`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_modul` (`user_id`,`modul_adi`);

--
-- Tablo için indeksler `kullanici_rolleri`
--
ALTER TABLE `kullanici_rolleri`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rol_adi` (`rol_adi`);

--
-- Tablo için indeksler `lab_analizleri`
--
ALTER TABLE `lab_analizleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hammadde_giris_id` (`hammadde_giris_id`);

--
-- Tablo için indeksler `lab_referans_degerleri`
--
ALTER TABLE `lab_referans_degerleri`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `makineler`
--
ALTER TABLE `makineler`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `malzemeler`
--
ALTER TABLE `malzemeler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `malzeme_kodu` (`malzeme_kodu`);

--
-- Tablo için indeksler `malzeme_hareketleri`
--
ALTER TABLE `malzeme_hareketleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `malzeme_id` (`malzeme_id`),
  ADD KEY `idx_tarih` (`islem_tarihi`);

--
-- Tablo için indeksler `malzeme_stok`
--
ALTER TABLE `malzeme_stok`
  ADD PRIMARY KEY (`id`),
  ADD KEY `malzeme_id` (`malzeme_id`);

--
-- Tablo için indeksler `modul_yetkileri`
--
ALTER TABLE `modul_yetkileri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rol_id` (`rol_id`);

--
-- Tablo için indeksler `musteriler`
--
ALTER TABLE `musteriler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cari_kod` (`cari_kod`);

--
-- Tablo için indeksler `musteri_spektleri`
--
ALTER TABLE `musteri_spektleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `urun_id` (`urun_id`);

--
-- Tablo için indeksler `onay_bekleyenler`
--
ALTER TABLE `onay_bekleyenler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `olusturan_user_id` (`olusturan_user_id`),
  ADD KEY `onaylayan_user_id` (`onaylayan_user_id`);

--
-- Tablo için indeksler `ortam_olcumu`
--
ALTER TABLE `ortam_olcumu`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `paketleme_ambalaj_kullanimi`
--
ALTER TABLE `paketleme_ambalaj_kullanimi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `paketleme_id` (`paketleme_id`),
  ADD KEY `ambalaj_parti_id` (`ambalaj_parti_id`);

--
-- Tablo için indeksler `paketleme_hareketleri`
--
ALTER TABLE `paketleme_hareketleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `urun_id` (`urun_id`),
  ADD KEY `paketleme_recetesi_id` (`paketleme_recetesi_id`),
  ADD KEY `idx_uretim_parti` (`uretim_parti_no`);

--
-- Tablo için indeksler `paketleme_recetesi`
--
ALTER TABLE `paketleme_recetesi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `urun_id` (`urun_id`),
  ADD KEY `ana_urun_silo_id` (`ana_urun_silo_id`),
  ADD KEY `ambalaj_id` (`ambalaj_id`);

--
-- Tablo için indeksler `proses_parametreleri`
--
ALTER TABLE `proses_parametreleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `is_emri_id` (`is_emri_id`),
  ADD KEY `uretim_id` (`uretim_id`),
  ADD KEY `idx_tarih` (`olcum_zamani`);

--
-- Tablo için indeksler `receteler`
--
ALTER TABLE `receteler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hammadde_1` (`hammadde_1`),
  ADD KEY `hammadde_2` (`hammadde_2`);

--
-- Tablo için indeksler `satin_alma_talepleri`
--
ALTER TABLE `satin_alma_talepleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `talep_eden_user_id` (`talep_eden_user_id`),
  ADD KEY `onaylayan_user_id` (`onaylayan_user_id`),
  ADD KEY `ilgili_is_emri_id` (`ilgili_is_emri_id`);

--
-- Tablo için indeksler `sevkiyatlar`
--
ALTER TABLE `sevkiyatlar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sevk_eden_user_id` (`sevk_eden_user_id`),
  ADD KEY `idx_parti` (`parti_no`),
  ADD KEY `idx_tarih` (`sevk_tarihi`);

--
-- Tablo için indeksler `sevkiyat_detaylari`
--
ALTER TABLE `sevkiyat_detaylari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siparis_id` (`siparis_id`);

--
-- Tablo için indeksler `sevkiyat_icerik`
--
ALTER TABLE `sevkiyat_icerik`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sevkiyat_id` (`sevkiyat_id`),
  ADD KEY `urun_id` (`urun_id`);

--
-- Tablo için indeksler `sevkiyat_randevulari`
--
ALTER TABLE `sevkiyat_randevulari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `urun_id` (`urun_id`),
  ADD KEY `onaylayan_user_id` (`onaylayan_user_id`);

--
-- Tablo için indeksler `sikayetler`
--
ALTER TABLE `sikayetler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sikayet_no` (`sikayet_no`),
  ADD KEY `idx_parti` (`parti_no`),
  ADD KEY `idx_musteri` (`musteri_id`),
  ADD KEY `idx_durum` (`durum`),
  ADD KEY `idx_tarih` (`sikayet_tarihi`);

--
-- Tablo için indeksler `sikayet_faaliyetleri`
--
ALTER TABLE `sikayet_faaliyetleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sikayet` (`sikayet_id`);

--
-- Tablo için indeksler `silolar`
--
ALTER TABLE `silolar`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `silo_duzeltme_talepleri`
--
ALTER TABLE `silo_duzeltme_talepleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sdt_hammadde_giris` (`hammadde_giris_id`),
  ADD KEY `idx_sdt_parti_no` (`parti_no`),
  ADD KEY `idx_sdt_durum` (`durum`),
  ADD KEY `idx_sdt_talep_eden` (`talep_eden_user_id`),
  ADD KEY `idx_sdt_karar_veren` (`karar_veren_user_id`);

--
-- Tablo için indeksler `silo_kabul_turleri`
--
ALTER TABLE `silo_kabul_turleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `silo_id` (`silo_id`);

--
-- Tablo için indeksler `silo_sicaklik_nem`
--
ALTER TABLE `silo_sicaklik_nem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `silo_id` (`silo_id`),
  ADD KEY `idx_tarih` (`olcum_tarihi`);

--
-- Tablo için indeksler `silo_stok_detay`
--
ALTER TABLE `silo_stok_detay`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_silo_kalan` (`silo_id`,`durum`,`giris_tarihi`);

--
-- Tablo için indeksler `siparisler`
--
ALTER TABLE `siparisler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `siparis_kodu` (`siparis_kodu`),
  ADD KEY `musteri_id` (`musteri_id`);

--
-- Tablo için indeksler `siparis_detaylari`
--
ALTER TABLE `siparis_detaylari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siparis_id` (`siparis_id`);

--
-- Tablo için indeksler `spesifikasyonlar`
--
ALTER TABLE `spesifikasyonlar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `urun_id` (`urun_id`);

--
-- Tablo için indeksler `stok_hareketleri`
--
ALTER TABLE `stok_hareketleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_tarih` (`islem_tarihi`),
  ADD KEY `idx_malzeme` (`malzeme_tipi`,`malzeme_id`);

--
-- Tablo için indeksler `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_created` (`created_at`);

--
-- Tablo için indeksler `temizlik_kayitlari`
--
ALTER TABLE `temizlik_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tarih` (`tarih`);

--
-- Tablo için indeksler `un_cikis_kayitlari`
--
ALTER TABLE `un_cikis_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parti` (`parti_no`),
  ADD KEY `b1_id` (`b1_id`);

--
-- Tablo için indeksler `uretim_b1`
--
ALTER TABLE `uretim_b1`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `uretim_b1_detay`
--
ALTER TABLE `uretim_b1_detay`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `uretim_ciktilari`
--
ALTER TABLE `uretim_ciktilari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uretim_id` (`uretim_id`),
  ADD KEY `urun_id` (`urun_id`),
  ADD KEY `hedef_silo_id` (`hedef_silo_id`);

--
-- Tablo için indeksler `uretim_hareketleri`
--
ALTER TABLE `uretim_hareketleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `is_emri_id` (`is_emri_id`),
  ADD KEY `idx_yikama_parti` (`yikama_parti_no`);

--
-- Tablo için indeksler `uretim_pacal`
--
ALTER TABLE `uretim_pacal`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `uretim_pacal_detay`
--
ALTER TABLE `uretim_pacal_detay`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `uretim_silo_cikis_log`
--
ALTER TABLE `uretim_silo_cikis_log`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `uretim_tavlama_1`
--
ALTER TABLE `uretim_tavlama_1`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `uretim_tavlama_1_detay`
--
ALTER TABLE `uretim_tavlama_1_detay`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `uretim_tavlama_2`
--
ALTER TABLE `uretim_tavlama_2`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `uretim_tavlama_2_detay`
--
ALTER TABLE `uretim_tavlama_2_detay`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `uretim_tavlama_3`
--
ALTER TABLE `uretim_tavlama_3`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `uretim_tavlama_3_detay`
--
ALTER TABLE `uretim_tavlama_3_detay`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `uretim_un1`
--
ALTER TABLE `uretim_un1`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `uretim_un1_detay`
--
ALTER TABLE `uretim_un1_detay`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `urunler`
--
ALTER TABLE `urunler`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kadi` (`kadi`),
  ADD KEY `rol_id` (`rol_id`);

--
-- Tablo için indeksler `web_yuklemeler`
--
ALTER TABLE `web_yuklemeler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `urun_id` (`urun_id`);

--
-- Tablo için indeksler `yabanci_madde_kontrolleri`
--
ALTER TABLE `yabanci_madde_kontrolleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tarih` (`tarih`);

--
-- Tablo için indeksler `yikama_islemleri`
--
ALTER TABLE `yikama_islemleri`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `parti_no` (`parti_no`),
  ADD KEY `is_emri_id` (`is_emri_id`),
  ADD KEY `cikis_silo_id` (`cikis_silo_id`);

--
-- Tablo için indeksler `yikama_kayitlari`
--
ALTER TABLE `yikama_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_parti_no_yikama` (`parti_no`),
  ADD KEY `idx_parti` (`parti_no`),
  ADD KEY `idx_hammadde_parti` (`hammadde_parti_no`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `aktarma_kayitlari`
--
ALTER TABLE `aktarma_kayitlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `ambalajlar`
--
ALTER TABLE `ambalajlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `ambalaj_partileri`
--
ALTER TABLE `ambalaj_partileri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `ambalaj_testleri`
--
ALTER TABLE `ambalaj_testleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `b1_degirmen_kayitlari`
--
ALTER TABLE `b1_degirmen_kayitlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `bakim_kayitlari`
--
ALTER TABLE `bakim_kayitlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `bakim_lab_malzemeler`
--
ALTER TABLE `bakim_lab_malzemeler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `bildirimler`
--
ALTER TABLE `bildirimler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Tablo için AUTO_INCREMENT değeri `ccp_kayitlari`
--
ALTER TABLE `ccp_kayitlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `depolar`
--
ALTER TABLE `depolar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `depo_sicaklik_nem`
--
ALTER TABLE `depo_sicaklik_nem`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `depo_stok`
--
ALTER TABLE `depo_stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `gunluk_kontroller`
--
ALTER TABLE `gunluk_kontroller`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `hammaddeler`
--
ALTER TABLE `hammaddeler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- Tablo için AUTO_INCREMENT değeri `hammadde_girisleri`
--
ALTER TABLE `hammadde_girisleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `hammadde_kabul_akisi`
--
ALTER TABLE `hammadde_kabul_akisi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `hammadde_kabul_gecmisi`
--
ALTER TABLE `hammadde_kabul_gecmisi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Tablo için AUTO_INCREMENT değeri `helal_kayitlari`
--
ALTER TABLE `helal_kayitlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `islem_loglari`
--
ALTER TABLE `islem_loglari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- Tablo için AUTO_INCREMENT değeri `is_emirleri`
--
ALTER TABLE `is_emirleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `is_emri_silo_karisimlari`
--
ALTER TABLE `is_emri_silo_karisimlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `kkn_kayitlari`
--
ALTER TABLE `kkn_kayitlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `kullanici_bildirim_durumlari`
--
ALTER TABLE `kullanici_bildirim_durumlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Tablo için AUTO_INCREMENT değeri `kullanici_modul_yetkileri`
--
ALTER TABLE `kullanici_modul_yetkileri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `kullanici_rolleri`
--
ALTER TABLE `kullanici_rolleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `lab_analizleri`
--
ALTER TABLE `lab_analizleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `makineler`
--
ALTER TABLE `makineler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `malzemeler`
--
ALTER TABLE `malzemeler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `malzeme_hareketleri`
--
ALTER TABLE `malzeme_hareketleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `malzeme_stok`
--
ALTER TABLE `malzeme_stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `modul_yetkileri`
--
ALTER TABLE `modul_yetkileri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- Tablo için AUTO_INCREMENT değeri `musteriler`
--
ALTER TABLE `musteriler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `musteri_spektleri`
--
ALTER TABLE `musteri_spektleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `onay_bekleyenler`
--
ALTER TABLE `onay_bekleyenler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `ortam_olcumu`
--
ALTER TABLE `ortam_olcumu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `paketleme_ambalaj_kullanimi`
--
ALTER TABLE `paketleme_ambalaj_kullanimi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `paketleme_hareketleri`
--
ALTER TABLE `paketleme_hareketleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `paketleme_recetesi`
--
ALTER TABLE `paketleme_recetesi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `proses_parametreleri`
--
ALTER TABLE `proses_parametreleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `receteler`
--
ALTER TABLE `receteler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `satin_alma_talepleri`
--
ALTER TABLE `satin_alma_talepleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `sevkiyatlar`
--
ALTER TABLE `sevkiyatlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `sevkiyat_detaylari`
--
ALTER TABLE `sevkiyat_detaylari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `sevkiyat_icerik`
--
ALTER TABLE `sevkiyat_icerik`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `sevkiyat_randevulari`
--
ALTER TABLE `sevkiyat_randevulari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `sikayetler`
--
ALTER TABLE `sikayetler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `sikayet_faaliyetleri`
--
ALTER TABLE `sikayet_faaliyetleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `silolar`
--
ALTER TABLE `silolar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Tablo için AUTO_INCREMENT değeri `silo_duzeltme_talepleri`
--
ALTER TABLE `silo_duzeltme_talepleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `silo_kabul_turleri`
--
ALTER TABLE `silo_kabul_turleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `silo_sicaklik_nem`
--
ALTER TABLE `silo_sicaklik_nem`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `silo_stok_detay`
--
ALTER TABLE `silo_stok_detay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `siparisler`
--
ALTER TABLE `siparisler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `siparis_detaylari`
--
ALTER TABLE `siparis_detaylari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `spesifikasyonlar`
--
ALTER TABLE `spesifikasyonlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `stok_hareketleri`
--
ALTER TABLE `stok_hareketleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- Tablo için AUTO_INCREMENT değeri `temizlik_kayitlari`
--
ALTER TABLE `temizlik_kayitlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `un_cikis_kayitlari`
--
ALTER TABLE `un_cikis_kayitlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_b1`
--
ALTER TABLE `uretim_b1`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_b1_detay`
--
ALTER TABLE `uretim_b1_detay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_ciktilari`
--
ALTER TABLE `uretim_ciktilari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_hareketleri`
--
ALTER TABLE `uretim_hareketleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_pacal`
--
ALTER TABLE `uretim_pacal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_pacal_detay`
--
ALTER TABLE `uretim_pacal_detay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_silo_cikis_log`
--
ALTER TABLE `uretim_silo_cikis_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_tavlama_1`
--
ALTER TABLE `uretim_tavlama_1`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_tavlama_1_detay`
--
ALTER TABLE `uretim_tavlama_1_detay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_tavlama_2`
--
ALTER TABLE `uretim_tavlama_2`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_tavlama_2_detay`
--
ALTER TABLE `uretim_tavlama_2_detay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_tavlama_3`
--
ALTER TABLE `uretim_tavlama_3`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_tavlama_3_detay`
--
ALTER TABLE `uretim_tavlama_3_detay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_un1`
--
ALTER TABLE `uretim_un1`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uretim_un1_detay`
--
ALTER TABLE `uretim_un1_detay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `urunler`
--
ALTER TABLE `urunler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `web_yuklemeler`
--
ALTER TABLE `web_yuklemeler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `yabanci_madde_kontrolleri`
--
ALTER TABLE `yabanci_madde_kontrolleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `yikama_islemleri`
--
ALTER TABLE `yikama_islemleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `yikama_kayitlari`
--
ALTER TABLE `yikama_kayitlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `aktarma_kayitlari`
--
ALTER TABLE `aktarma_kayitlari`
  ADD CONSTRAINT `aktarma_kayitlari_ibfk_1` FOREIGN KEY (`yikama_id`) REFERENCES `yikama_kayitlari` (`id`);

--
-- Tablo kısıtlamaları `ambalaj_partileri`
--
ALTER TABLE `ambalaj_partileri`
  ADD CONSTRAINT `ambalaj_partileri_ibfk_1` FOREIGN KEY (`ambalaj_id`) REFERENCES `ambalajlar` (`id`);

--
-- Tablo kısıtlamaları `ambalaj_testleri`
--
ALTER TABLE `ambalaj_testleri`
  ADD CONSTRAINT `ambalaj_testleri_ibfk_1` FOREIGN KEY (`ambalaj_parti_id`) REFERENCES `ambalaj_partileri` (`id`);

--
-- Tablo kısıtlamaları `b1_degirmen_kayitlari`
--
ALTER TABLE `b1_degirmen_kayitlari`
  ADD CONSTRAINT `b1_degirmen_kayitlari_ibfk_1` FOREIGN KEY (`aktarma_id`) REFERENCES `aktarma_kayitlari` (`id`);

--
-- Tablo kısıtlamaları `bakim_kayitlari`
--
ALTER TABLE `bakim_kayitlari`
  ADD CONSTRAINT `bakim_kayitlari_ibfk_1` FOREIGN KEY (`makine_id`) REFERENCES `makineler` (`id`);

--
-- Tablo kısıtlamaları `depo_sicaklik_nem`
--
ALTER TABLE `depo_sicaklik_nem`
  ADD CONSTRAINT `depo_sicaklik_nem_ibfk_1` FOREIGN KEY (`depo_id`) REFERENCES `depolar` (`id`);

--
-- Tablo kısıtlamaları `depo_stok`
--
ALTER TABLE `depo_stok`
  ADD CONSTRAINT `depo_stok_ibfk_1` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`),
  ADD CONSTRAINT `depo_stok_ibfk_2` FOREIGN KEY (`depo_id`) REFERENCES `depolar` (`id`);

--
-- Tablo kısıtlamaları `hammadde_girisleri`
--
ALTER TABLE `hammadde_girisleri`
  ADD CONSTRAINT `hammadde_girisleri_ibfk_1` FOREIGN KEY (`hammadde_id`) REFERENCES `hammaddeler` (`id`),
  ADD CONSTRAINT `hammadde_girisleri_ibfk_2` FOREIGN KEY (`silo_id`) REFERENCES `silolar` (`id`);

--
-- Tablo kısıtlamaları `hammadde_kabul_akisi`
--
ALTER TABLE `hammadde_kabul_akisi`
  ADD CONSTRAINT `fk_akis_hammadde_giris` FOREIGN KEY (`hammadde_giris_id`) REFERENCES `hammadde_girisleri` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `hammadde_kabul_gecmisi`
--
ALTER TABLE `hammadde_kabul_gecmisi`
  ADD CONSTRAINT `fk_gecmis_akis` FOREIGN KEY (`akis_id`) REFERENCES `hammadde_kabul_akisi` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `islem_loglari`
--
ALTER TABLE `islem_loglari`
  ADD CONSTRAINT `islem_loglari_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `is_emirleri`
--
ALTER TABLE `is_emirleri`
  ADD CONSTRAINT `fk_isemri_yikama` FOREIGN KEY (`yikama_parti_no`) REFERENCES `yikama_kayitlari` (`parti_no`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `is_emirleri_ibfk_1` FOREIGN KEY (`recete_id`) REFERENCES `receteler` (`id`),
  ADD CONSTRAINT `is_emirleri_ibfk_2` FOREIGN KEY (`onaylayan_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `is_emirleri_ibfk_3` FOREIGN KEY (`onaylayan_user_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `is_emri_silo_karisimlari`
--
ALTER TABLE `is_emri_silo_karisimlari`
  ADD CONSTRAINT `fk_is_emri_silo_is_emri` FOREIGN KEY (`is_emri_id`) REFERENCES `is_emirleri` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_is_emri_silo_silo` FOREIGN KEY (`silo_id`) REFERENCES `silolar` (`id`);

--
-- Tablo kısıtlamaları `kullanici_modul_yetkileri`
--
ALTER TABLE `kullanici_modul_yetkileri`
  ADD CONSTRAINT `kullanici_modul_yetkileri_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `lab_analizleri`
--
ALTER TABLE `lab_analizleri`
  ADD CONSTRAINT `lab_analizleri_ibfk_1` FOREIGN KEY (`hammadde_giris_id`) REFERENCES `hammadde_girisleri` (`id`);

--
-- Tablo kısıtlamaları `malzeme_hareketleri`
--
ALTER TABLE `malzeme_hareketleri`
  ADD CONSTRAINT `malzeme_hareketleri_ibfk_1` FOREIGN KEY (`malzeme_id`) REFERENCES `malzemeler` (`id`);

--
-- Tablo kısıtlamaları `malzeme_stok`
--
ALTER TABLE `malzeme_stok`
  ADD CONSTRAINT `malzeme_stok_ibfk_1` FOREIGN KEY (`malzeme_id`) REFERENCES `malzemeler` (`id`);

--
-- Tablo kısıtlamaları `modul_yetkileri`
--
ALTER TABLE `modul_yetkileri`
  ADD CONSTRAINT `modul_yetkileri_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `kullanici_rolleri` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `musteri_spektleri`
--
ALTER TABLE `musteri_spektleri`
  ADD CONSTRAINT `musteri_spektleri_ibfk_1` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`);

--
-- Tablo kısıtlamaları `onay_bekleyenler`
--
ALTER TABLE `onay_bekleyenler`
  ADD CONSTRAINT `onay_bekleyenler_ibfk_1` FOREIGN KEY (`olusturan_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `onay_bekleyenler_ibfk_2` FOREIGN KEY (`onaylayan_user_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `paketleme_ambalaj_kullanimi`
--
ALTER TABLE `paketleme_ambalaj_kullanimi`
  ADD CONSTRAINT `paketleme_ambalaj_kullanimi_ibfk_1` FOREIGN KEY (`paketleme_id`) REFERENCES `paketleme_hareketleri` (`id`),
  ADD CONSTRAINT `paketleme_ambalaj_kullanimi_ibfk_2` FOREIGN KEY (`ambalaj_parti_id`) REFERENCES `ambalaj_partileri` (`id`);

--
-- Tablo kısıtlamaları `paketleme_hareketleri`
--
ALTER TABLE `paketleme_hareketleri`
  ADD CONSTRAINT `paketleme_hareketleri_ibfk_1` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`),
  ADD CONSTRAINT `paketleme_hareketleri_ibfk_2` FOREIGN KEY (`paketleme_recetesi_id`) REFERENCES `paketleme_recetesi` (`id`);

--
-- Tablo kısıtlamaları `paketleme_recetesi`
--
ALTER TABLE `paketleme_recetesi`
  ADD CONSTRAINT `paketleme_recetesi_ibfk_1` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`),
  ADD CONSTRAINT `paketleme_recetesi_ibfk_2` FOREIGN KEY (`ana_urun_silo_id`) REFERENCES `silolar` (`id`),
  ADD CONSTRAINT `paketleme_recetesi_ibfk_3` FOREIGN KEY (`ambalaj_id`) REFERENCES `ambalajlar` (`id`);

--
-- Tablo kısıtlamaları `proses_parametreleri`
--
ALTER TABLE `proses_parametreleri`
  ADD CONSTRAINT `proses_parametreleri_ibfk_1` FOREIGN KEY (`is_emri_id`) REFERENCES `is_emirleri` (`id`),
  ADD CONSTRAINT `proses_parametreleri_ibfk_2` FOREIGN KEY (`uretim_id`) REFERENCES `uretim_hareketleri` (`id`);

--
-- Tablo kısıtlamaları `receteler`
--
ALTER TABLE `receteler`
  ADD CONSTRAINT `receteler_ibfk_1` FOREIGN KEY (`hammadde_1`) REFERENCES `hammaddeler` (`id`),
  ADD CONSTRAINT `receteler_ibfk_2` FOREIGN KEY (`hammadde_2`) REFERENCES `hammaddeler` (`id`);

--
-- Tablo kısıtlamaları `satin_alma_talepleri`
--
ALTER TABLE `satin_alma_talepleri`
  ADD CONSTRAINT `satin_alma_talepleri_ibfk_1` FOREIGN KEY (`talep_eden_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `satin_alma_talepleri_ibfk_2` FOREIGN KEY (`onaylayan_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `satin_alma_talepleri_ibfk_3` FOREIGN KEY (`talep_eden_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `satin_alma_talepleri_ibfk_4` FOREIGN KEY (`onaylayan_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `satin_alma_talepleri_ibfk_5` FOREIGN KEY (`ilgili_is_emri_id`) REFERENCES `is_emirleri` (`id`);

--
-- Tablo kısıtlamaları `sevkiyatlar`
--
ALTER TABLE `sevkiyatlar`
  ADD CONSTRAINT `sevkiyatlar_ibfk_1` FOREIGN KEY (`sevk_eden_user_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `sevkiyat_detaylari`
--
ALTER TABLE `sevkiyat_detaylari`
  ADD CONSTRAINT `sevkiyat_detaylari_ibfk_1` FOREIGN KEY (`siparis_id`) REFERENCES `siparisler` (`id`);

--
-- Tablo kısıtlamaları `sevkiyat_icerik`
--
ALTER TABLE `sevkiyat_icerik`
  ADD CONSTRAINT `sevkiyat_icerik_ibfk_1` FOREIGN KEY (`sevkiyat_id`) REFERENCES `sevkiyat_randevulari` (`id`),
  ADD CONSTRAINT `sevkiyat_icerik_ibfk_2` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`);

--
-- Tablo kısıtlamaları `sevkiyat_randevulari`
--
ALTER TABLE `sevkiyat_randevulari`
  ADD CONSTRAINT `sevkiyat_randevulari_ibfk_1` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`),
  ADD CONSTRAINT `sevkiyat_randevulari_ibfk_2` FOREIGN KEY (`onaylayan_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `sevkiyat_randevulari_ibfk_3` FOREIGN KEY (`onaylayan_user_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `silo_duzeltme_talepleri`
--
ALTER TABLE `silo_duzeltme_talepleri`
  ADD CONSTRAINT `fk_sdt_hammadde_giris` FOREIGN KEY (`hammadde_giris_id`) REFERENCES `hammadde_girisleri` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sdt_karar_veren_user` FOREIGN KEY (`karar_veren_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sdt_talep_eden_user` FOREIGN KEY (`talep_eden_user_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `silo_kabul_turleri`
--
ALTER TABLE `silo_kabul_turleri`
  ADD CONSTRAINT `silo_kabul_turleri_ibfk_1` FOREIGN KEY (`silo_id`) REFERENCES `silolar` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `silo_sicaklik_nem`
--
ALTER TABLE `silo_sicaklik_nem`
  ADD CONSTRAINT `silo_sicaklik_nem_ibfk_1` FOREIGN KEY (`silo_id`) REFERENCES `silolar` (`id`);

--
-- Tablo kısıtlamaları `silo_stok_detay`
--
ALTER TABLE `silo_stok_detay`
  ADD CONSTRAINT `silo_stok_detay_ibfk_1` FOREIGN KEY (`silo_id`) REFERENCES `silolar` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `siparisler`
--
ALTER TABLE `siparisler`
  ADD CONSTRAINT `siparisler_ibfk_1` FOREIGN KEY (`musteri_id`) REFERENCES `musteriler` (`id`);

--
-- Tablo kısıtlamaları `siparis_detaylari`
--
ALTER TABLE `siparis_detaylari`
  ADD CONSTRAINT `siparis_detaylari_ibfk_1` FOREIGN KEY (`siparis_id`) REFERENCES `siparisler` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `spesifikasyonlar`
--
ALTER TABLE `spesifikasyonlar`
  ADD CONSTRAINT `spesifikasyonlar_ibfk_1` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`);

--
-- Tablo kısıtlamaları `stok_hareketleri`
--
ALTER TABLE `stok_hareketleri`
  ADD CONSTRAINT `stok_hareketleri_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `un_cikis_kayitlari`
--
ALTER TABLE `un_cikis_kayitlari`
  ADD CONSTRAINT `un_cikis_kayitlari_ibfk_1` FOREIGN KEY (`b1_id`) REFERENCES `b1_degirmen_kayitlari` (`id`);

--
-- Tablo kısıtlamaları `uretim_ciktilari`
--
ALTER TABLE `uretim_ciktilari`
  ADD CONSTRAINT `uretim_ciktilari_ibfk_1` FOREIGN KEY (`uretim_id`) REFERENCES `uretim_hareketleri` (`id`),
  ADD CONSTRAINT `uretim_ciktilari_ibfk_2` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`),
  ADD CONSTRAINT `uretim_ciktilari_ibfk_3` FOREIGN KEY (`hedef_silo_id`) REFERENCES `silolar` (`id`);

--
-- Tablo kısıtlamaları `uretim_hareketleri`
--
ALTER TABLE `uretim_hareketleri`
  ADD CONSTRAINT `fk_uretim_yikama` FOREIGN KEY (`yikama_parti_no`) REFERENCES `yikama_kayitlari` (`parti_no`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `uretim_hareketleri_ibfk_1` FOREIGN KEY (`is_emri_id`) REFERENCES `is_emirleri` (`id`);

--
-- Tablo kısıtlamaları `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `kullanici_rolleri` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`rol_id`) REFERENCES `kullanici_rolleri` (`id`);

--
-- Tablo kısıtlamaları `web_yuklemeler`
--
ALTER TABLE `web_yuklemeler`
  ADD CONSTRAINT `web_yuklemeler_ibfk_1` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`);

--
-- Tablo kısıtlamaları `yikama_islemleri`
--
ALTER TABLE `yikama_islemleri`
  ADD CONSTRAINT `yikama_islemleri_ibfk_1` FOREIGN KEY (`is_emri_id`) REFERENCES `is_emirleri` (`id`),
  ADD CONSTRAINT `yikama_islemleri_ibfk_2` FOREIGN KEY (`cikis_silo_id`) REFERENCES `silolar` (`id`);

--
-- Tablo kısıtlamaları `yikama_kayitlari`
--
ALTER TABLE `yikama_kayitlari`
  ADD CONSTRAINT `fk_yikama_hammadde` FOREIGN KEY (`hammadde_parti_no`) REFERENCES `hammadde_girisleri` (`parti_no`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
