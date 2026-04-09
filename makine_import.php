<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include("baglan.php");

// Veritabanı karakter setine uyum sağlamak için
$baglanti->set_charset("utf8mb4");

if (!isset($_SESSION["oturum"])) {
    die("Lütfen önce giriş yapın.");
}

$makineler = [
    // Ön Temizleme Ünitesi
    ['ONT-1', 'Kopresör', 'Ön Temizleme Ünitesi', 'Zemin Kat', ''],
    ['ONT-2', 'Kopresör', 'Ön Temizleme Ünitesi', 'Zemin Kat', ''],
    ['ONT-3', 'Jet Filitre Hava Kilidi', 'Ön Temizleme Ünitesi', '2. Kat', ''],
    ['ONT-4', 'Jet Filitre Vibro', 'Ön Temizleme Ünitesi', '2. Kat', ''],
    ['ONT-5', 'Aspiratör', 'Ön Temizleme Ünitesi', '3. Kat', ''],
    ['ONT-6', 'Blower', 'Ön Temizleme Ünitesi', '3. Kat', ''],
    ['ONT-7', 'Zincirli Konveyör', 'Ön Temizleme Ünitesi', '5. Kat', ''],
    ['ONT-8', 'Zincirli Konveyör', 'Ön Temizleme Ünitesi', '5. Kat', ''],
    ['ONT-9', 'Zincirli Konveyör', 'Ön Temizleme Ünitesi', '5. Kat', ''],
    ['ONT-10', 'Zincirli Konveyör', 'Ön Temizleme Ünitesi', '5. Kat', ''],
    ['ONT-11', 'Elevatör', 'Ön Temizleme Ünitesi', '', ''],
    ['ONT-12', 'Çöp Sasörü', 'Ön Temizleme Ünitesi', '2. Kat', ''],
    ['ONT-17', 'Elevatör', 'Ön Temizleme Ünitesi', '', ''],
    ['ONT-18', 'Zincirli Konveyör', 'Ön Temizleme Ünitesi', 'Yer Altı', ''],

    // Temizleme Ünitesi
    ['TMZ-19', 'Jet Filitre Hava Kilidi', 'Temizleme Ünitesi', '4. Kat', ''],
    ['TMZ-20', 'Jet Filitre Vibro', 'Temizleme Ünitesi', '4. Kat', ''],
    ['TMZ-21', 'Aspiratör', 'Temizleme Ünitesi', '5. Kat', ''],
    ['TMZ-22', 'Blower', 'Temizleme Ünitesi', 'Zemin Kat', 'Blower Odasında'],
    ['TMZ-23', 'Helezon Vida', 'Temizleme Ünitesi', '5. Kat', ''],
    ['TMZ-24', 'Cebri Tav', 'Temizleme Ünitesi', '5. Kat', ''],
    ['TMZ-25', 'Elevatör', 'Temizleme Ünitesi', '', ''],
    ['TMZ-26', 'Elevatör', 'Temizleme Ünitesi', '', ''],
    ['TMZ-28', 'Kabuk Soyucu', 'Temizleme Ünitesi', '3. Kat', ''],
    ['TMZ-29', 'Tarar Aspiratör', 'Temizleme Ünitesi', '3. Kat', ''],
    ['TMZ-30', 'Tarar Rediktör', 'Temizleme Ünitesi', '3. Kat', ''],
    ['TMZ-31.1', 'Tarar Vibro', 'Temizleme Ünitesi', '3. Kat', ''],
    ['TMZ-31.2', 'Tarar Vibro', 'Temizleme Ünitesi', '3. Kat', ''],
    ['TMZ-32', 'Triyör', 'Temizleme Ünitesi', '4. Kat', ''],
    ['TMZ-33', 'Triyör', 'Temizleme Ünitesi', '4. Kat', ''],
    ['TMZ-34', 'Elevatör', 'Temizleme Ünitesi', '', ''],
    ['TMZ-35', 'Aspiratör', 'Temizleme Ünitesi', '3. Kat', ''],
    ['TMZ-35.1', 'Triyör', 'Temizleme Ünitesi', '2. Kat', ''],
    ['TMZ-35.2', 'Triyör', 'Temizleme Ünitesi', '2. Kat', ''],
    ['TMZ-36.1', 'Taş Ayırıcı (Kombinatör)', 'Temizleme Ünitesi', '3. Kat', ''],
    ['TMZ-36.2', 'Taş Ayırıcı (Kombinatör)', 'Temizleme Ünitesi', '3. Kat', ''],
    ['TMZ-37', 'Tarar Aspiratör', 'Temizleme Ünitesi', '4. Kat', ''],
    ['TMZ-38', 'Tarar Rediktör', 'Temizleme Ünitesi', '4. Kat', ''],
    ['TMZ-39.1', 'Çöp Sasör', 'Temizleme Ünitesi', '4. Kat', ''],
    ['TMZ-39.2', 'Çöp Sasör', 'Temizleme Ünitesi', '4. Kat', ''],
    ['TMZ-40', 'Elevatör', 'Temizleme Ünitesi', '', ''],
    ['TMZ-41', 'Helezon Vida', 'Temizleme Ünitesi', 'Zemin Kat', ''],
    ['TMZ-42', 'Helezon Vida', 'Temizleme Ünitesi', 'Zemin Kat', ''],
    ['TMZ-43', 'Helezon Vida', 'Temizleme Ünitesi', 'Zemin Kat', ''],
    ['TMZ-44', 'Helezon Vida', 'Temizleme Ünitesi', 'Zemin Kat', ''],

    // Aktarma Ünitesi
    ['AKT-45', 'Helezon Vida', 'Aktarma Ünitesi', '5. Kat', 'H.B-K.S'],
    ['AKT-46', 'Cebri Tav (24 DC Besleme)', 'Aktarma Ünitesi', '5. Kat', ''],
    ['AKT-47', 'Elevatör', 'Aktarma Ünitesi', '', 'H.B-D.B-K.K'],
    ['AKT-48', 'Kabuk Soyucu', 'Aktarma Ünitesi', '2. Kat', ''],
    ['AKT-49', 'Tarar Aspiratör', 'Aktarma Ünitesi', '2. Kat', ''],
    ['AKT-50', 'Tarar Rediktör', 'Aktarma Ünitesi', '2. Kat', ''],
    ['AKT-51.1', 'Tarar Vibro', 'Aktarma Ünitesi', '2. Kat', ''],
    ['AKT-51.2', 'Tarar Vibro', 'Aktarma Ünitesi', '2. Kat', ''],
    ['AKT-52', 'Elevatör', 'Aktarma Ünitesi', '', 'H.B-D.B-K.K'],
    ['AKT-53', 'Helezon Vida', 'Aktarma Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['AKT-54', 'Helezon Vida', 'Aktarma Ünitesi', 'Zemin Kat', 'H.B-K.S'],

    // Hazırlık Ünitesi
    ['HZR-55', 'Jet Filitre Hava Kilidi', 'Hazırlık Ünitesi', '3. Kat', ''],
    ['HZR-56', 'Jet Filitre Vibro', 'Hazırlık Ünitesi', '3. Kat', ''],
    ['HZR-57', 'Aspiratör', 'Hazırlık Ünitesi', '5. Kat', ''],
    ['HZR-58', 'Helezon Vida', 'Hazırlık Ünitesi', '3. Kat', 'H.B-K.S'],
    ['HZR-59', 'Kabuk Soyucu', 'Hazırlık Ünitesi', '4. Kat', ''],
    ['HZR-60', 'Tarar Aspiratör', 'Hazırlık Ünitesi', '4. Kat', ''],
    ['HZR-61', 'Tarar Rediktör', 'Hazırlık Ünitesi', '4. Kat', ''],
    ['HZR-62.1', 'Tarar Vibro', 'Hazırlık Ünitesi', '4. Kat', ''],
    ['HZR-62.2', 'Tarar Vibro', 'Hazırlık Ünitesi', '4. Kat', ''],
    ['HZR-63', 'Elevatör', 'Hazırlık Ünitesi', '', 'H.B-D.B-K.K'],
    ['HZR-64', 'Cebri Tav (24 DC Besleme)', 'Hazırlık Ünitesi', '5. Kat', ''],
    ['HZR-65', 'Elevatör', 'Hazırlık Ünitesi', '', 'H.B-D.B-K.K'],
    ['HZR-65.1', 'Helezon Vida', 'Hazırlık Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['HZR-66', 'Helezon Vida', 'Hazırlık Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['HZR-67', 'Helezon Vida', 'Hazırlık Ünitesi', 'Zemin Kat', 'H.B-K.S'],

    // Atık Ünitesi
    ['ATK-68', 'Elevatör', 'Atık Ünitesi', '', 'H.B-D.B-K.K'],
    ['ATK-69', 'Helezon Vida', 'Atık Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['ATK-70', 'Aspiratör (Açık-Kapalı Bilgisi)', 'Atık Ünitesi', '5. Kat', ''],
    ['ATK-71', 'Siklon Hava Kilidi', 'Atık Ünitesi', '5. Kat', ''],
    ['ATK-72', 'Çekiçli Değirmen', 'Atık Ünitesi', 'Zemin Kat', ''],
    ['ATK-73', 'Tüp Vida', 'Atık Ünitesi', 'Zemin Kat', ''],

    // Öğütme Ünitesi
    ['OGT-74', 'Jet Filtre Hava Kilidi', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-75', 'Jet Filtre Rediktör', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-76', 'Aspiratör', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-77', 'Jet Filtre Hava Kilidi', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-78', 'Jet Filtre Rediktör', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-79', 'Aspiratör', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-80', 'Blower (220AC Elektronik Beyin)', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-81', 'Kontrol Eleği (Zaman Rölesi)', 'Öğütme Ünitesi', '2. Kat', ''],
    ['OGT-81.1', 'Hava Siklon', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-81.2', 'Hava Siklon', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-81.3', 'Hava Siklon', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-81.4', 'Hava Siklon', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-81.5', 'Hava Siklon', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-82', 'Blower', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-83', 'Eklüs', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-85', 'Helezon Vida', 'Öğütme Ünitesi', '3. Kat', 'H.B-K.S'],
    ['OGT-87', 'Helezon Vida', 'Öğütme Ünitesi', '2. Kat', 'H.B-K.S'],
    ['OGT-88', 'Blower', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-89', 'Eklüs', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-91', 'Helezon Vida', 'Öğütme Ünitesi', '3. Kat', 'H.B-K.S'],
    ['OGT-93', 'Helezon Vida', 'Öğütme Ünitesi', '2. Kat', ''],
    ['OGT-94', 'Blower', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-95', 'Eklüs', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-95.1', 'Tüp Vida', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-97', 'Helezon Vida', 'Öğütme Ünitesi', '2. Kat', ''],
    ['OGT-98', 'Vibro Kepek Fırçası', 'Öğütme Ünitesi', '4. Kat', ''],
    ['OGT-99', 'Hava Kilidi 1', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-100', 'Hava Kilidi 2', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-101', 'Hava Kilidi 3', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-102', 'Hava Kilidi 4', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-103', 'Hava Kilidi 5', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-104', 'Hava Kilidi 6 (H.B)', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-105', 'Hava Kilidi 7 (H.B)', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-106', 'Hava Kilidi 8 (H.B)', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-107', 'Hava Kilidi 9 (H.B)', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-108', 'Jet Filtre Hava Kilidi', 'Öğütme Ünitesi', '4. Kat', ''],
    ['OGT-109', 'Jet Filtre Vibro', 'Öğütme Ünitesi', '4. Kat', ''],
    ['OGT-110', 'Pnomatik Aspiratör', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-111', 'Blower', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-112', 'Vibro Besleyici', 'Öğütme Ünitesi', '4. Kat', ''],
    ['OGT-113', 'Jet Filtre Hava Kilidi', 'Öğütme Ünitesi', '4. Kat', ''],
    ['OGT-114', 'Jet Filtre Vibro', 'Öğütme Ünitesi', '4. Kat', ''],
    ['OGT-115', 'İrmik Aspiratör', 'Öğütme Ünitesi', '5. Kat', ''],
    ['OGT-116', 'Blower', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-117', 'Vibro Besleyici', 'Öğütme Ünitesi', '4. Kat', ''],
    ['OGT-118.1', 'İrmik Sasörü 1', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-118.2', 'İrmik Sasörü 1', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-119.1', 'İrmik Sasörü 2', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-119.2', 'İrmik Sasörü 2', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-120.1', 'İrmik Sasörü 3', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-120.2', 'İrmik Sasörü 3', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-121.1', 'İrmik Sasörü 4', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-121.2', 'İrmik Sasörü 4', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-122.1', 'İrmik Sasörü 5', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-122.2', 'İrmik Sasörü 5', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-123', 'Kepek Fırçası 1', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-124', 'Kepek Fırçası 2', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-125', 'Kepek Fırçası 3', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-126', 'Kepek Fırçası 4', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-127', 'Kepek Fırçası 5', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-128', 'Kepek Fırçası 6', 'Öğütme Ünitesi', '3. Kat', ''],
    ['OGT-129', 'Dedaşör 1', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-130', 'Dedaşör 2', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-131', 'Dedaşör 3', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-132', 'Dedaşör 4', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-132.1', 'Dedaşör 5', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-133', 'İrmik Kırıcı 1', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-134', 'İrmik Kırıcı 2', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-135', 'İrmik Kırıcı 3', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-135.1', 'İrmik Kırıcı 4', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-135.2', 'İrmik Kırıcı 5', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-135.3', 'İrmik Kırıcı 6', 'Öğütme Ünitesi', 'Zemin Kat', ''],
    ['OGT-136', 'Vals 1', 'Öğütme Ünitesi', '1. Kat', '401 - C5'],
    ['OGT-137', 'Vals 2', 'Öğütme Ünitesi', '1. Kat', '401 - C6'],
    ['OGT-138', 'Vals 3', 'Öğütme Ünitesi', '1. Kat', '401 - C4'],
    ['OGT-139', 'Vals 4', 'Öğütme Ünitesi', '1. Kat', '401 - CL4'],
    ['OGT-140', 'Vals 5', 'Öğütme Ünitesi', '1. Kat', '401 - C2'],
    ['OGT-141', 'Vals 6', 'Öğütme Ünitesi', '1. Kat', '401 - C2'],
    ['OGT-142', 'Vals 7', 'Öğütme Ünitesi', '1. Kat', '401 - C1'],
    ['OGT-143', 'Vals 8', 'Öğütme Ünitesi', '1. Kat', '401 - C1'],
    ['OGT-144', 'Vals 9', 'Öğütme Ünitesi', '1. Kat', '401 - B5'],
    ['OGT-145', 'Vals 10', 'Öğütme Ünitesi', '1. Kat', '401 - C3'],
    ['OGT-146', 'Vals 11', 'Öğütme Ünitesi', '1. Kat', '401 - CL3'],
    ['OGT-147', 'Vals 12', 'Öğütme Ünitesi', '1. Kat', '401 - CL2'],
    ['OGT-148', 'Vals 13', 'Öğütme Ünitesi', '1. Kat', '401 - CL1C'],
    ['OGT-149', 'Vals 14', 'Öğütme Ünitesi', '1. Kat', '401 - CL1C'],
    ['OGT-150', 'Vals 15', 'Öğütme Ünitesi', '1. Kat', '401 - CL1F'],
    ['OGT-151', 'Vals 16', 'Öğütme Ünitesi', '1. Kat', '401 - CL1F'],
    ['OGT-152', 'Vals 17', 'Öğütme Ünitesi', '1. Kat', '401 - B4F'],
    ['OGT-153', 'Vals 18', 'Öğütme Ünitesi', '1. Kat', '401 - B4C'],
    ['OGT-154', 'Vals 19', 'Öğütme Ünitesi', '1. Kat', '401 - B3F'],
    ['OGT-155', 'Vals 20', 'Öğütme Ünitesi', '1. Kat', '401 - B3C'],
    ['OGT-156', 'Vals 21', 'Öğütme Ünitesi', '1. Kat', '401 - B2'],
    ['OGT-157', 'Vals 22', 'Öğütme Ünitesi', '1. Kat', '401 - B2'],
    ['OGT-158', 'Vals 23', 'Öğütme Ünitesi', '1. Kat', '401 - B1'],
    ['OGT-159', 'Vals 24', 'Öğütme Ünitesi', '1. Kat', '401 - B1'],
    ['OGT-160', 'Elek', 'Öğütme Ünitesi', '4. Kat', ''],
    ['OGT-161', 'Elek', 'Öğütme Ünitesi', '4. Kat', ''],

    // Un Ünitesi
    ['UN-163', 'Otomatik Paketleme', 'Un Ünitesi', '1. Kat', 'PAK 1. Kat'],
    ['UN-164', 'Otomatik Paketleme', 'Un Ünitesi', '1. Kat', 'PAK 1. Kat'],
    ['UN-165', 'Tüp Vida', 'Un Ünitesi', '2. Kat', 'PAK 2. Kat'],
    ['UN-166', 'Kontrol Eleği', 'Un Ünitesi', '4. Kat', 'PAK 4. Kat'],
    ['UN-167', 'Dağıtıcı', 'Un Ünitesi', '4. Kat', 'PAK 4. Kat'],
    ['UN-168', 'Elevatör', 'Un Ünitesi', '', 'H.B-D.B-K.K'],
    ['UN-169', 'Helezon Vida', 'Un Ünitesi', '5. Kat', 'H.B-K.S'],
    ['UN-170', 'Helezon Vida', 'Un Ünitesi', '5. Kat', 'H.B-K.S'],
    ['UN-171', 'Helezon Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-172', 'Tüp Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-173.1', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-173.2', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-174', 'Tüp Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-175.1', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-175.2', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-176', 'Tüp Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-177.1', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-177.2', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-178', 'Tüp Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-179.1', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-179.2', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-180', 'Tüp Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-181.1', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-181.2', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-182', 'Tüp Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-183.1', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-183.2', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-184', 'Tüp Vida', 'Un Ünitesi', '2. Kat', 'PAK 2. Kat'],
    ['UN-185', 'Kontrol Eleği (Zaman Rölesi)', 'Un Ünitesi', '4. Kat', 'PAK 4. Kat'],
    ['UN-186', 'Dağıtıcı', 'Un Ünitesi', '4. Kat', 'PAK 4. Kat'],
    ['UN-187', 'Elevatör', 'Un Ünitesi', '', 'H.B-D.B-K.K'],
    ['UN-188', 'Helezon Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-189', 'Helezon Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-190', 'Tüp Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-191.1', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-191.2', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-192', 'Tüp Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-193.1', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-193.2', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-194', 'Tüp Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-195.1', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-195.2', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-196', 'Tüp Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-197.1', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-197.2', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-198', 'Tüp Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-199.1', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-199.2', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-200', 'Tüp Vida', 'Un Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['UN-201.1', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],
    ['UN-201.2', 'Rtoflow', 'Un Ünitesi', 'Zemin Kat', ''],

    // Kepek Ünitesi
    ['KPK-202', 'Otomatik Paketleme', 'Kepek Ünitesi', '1. Kat', 'PAK 1. Kat'],
    ['KPK-203', 'Yükleme Körüğü Fan', 'Kepek Ünitesi', '1. Kat', 'PAK 1. Kat'],
    ['KPK-204', 'Yükleme Körüğü Rediktör', 'Kepek Ünitesi', '1. Kat', 'PAK 1. Kat'],
    ['KPK-205', 'Helezon Vida', 'Kepek Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['KPK-206', 'Elevatör', 'Kepek Ünitesi', '', 'H.B-D.B-K.K'],
    ['KPK-207', 'Helezon Vida', 'Kepek Ünitesi', '5. Kat', 'H.B-K.S'],
    ['KPK-208', 'Helezon Vida', 'Kepek Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['KPK-209', 'Tüp Vida', 'Kepek Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['KPK-210.1', 'Rtoflow', 'Kepek Ünitesi', 'Zemin Kat', ''],
    ['KPK-210.2', 'Rtoflow', 'Kepek Ünitesi', 'Zemin Kat', ''],
    ['KPK-211', 'Tüp Vida', 'Kepek Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['KPK-212.1', 'Rtoflow', 'Kepek Ünitesi', 'Zemin Kat', ''],
    ['KPK-212.2', 'Rtoflow', 'Kepek Ünitesi', 'Zemin Kat', ''],
    ['KPK-213', 'Tüp Vida', 'Kepek Ünitesi', 'Zemin Kat', 'H.B-K.S'],
    ['KPK-214.1', 'Rtoflow', 'Kepek Ünitesi', 'Zemin Kat', ''],
    ['KPK-214.2', 'Rtoflow', 'Kepek Ünitesi', 'Zemin Kat', ''],
];

$eklenen = 0;
$m_kodlari = [];

foreach ($makineler as $m) {
    $m_kodu = $m[0];
    $m_adi = $m[1];
    $m_unite = $m[2];
    $m_kat = $m[3];
    $m_lok = $m[4];
    $m_periyot = 30; // Varsayılan 30 gün bakım periyodu

    // Önce bu kodla kayıt var mı kontrol edelim
    $check = $baglanti->prepare("SELECT id FROM makineler WHERE makine_kodu = ?");
    $check->bind_param("s", $m_kodu);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows == 0) {
        $stmt = $baglanti->prepare("INSERT INTO makineler (makine_kodu, makine_adi, unite_adi, kat_bilgisi, lokasyon, bakim_periyodu) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $m_kodu, $m_adi, $m_unite, $m_kat, $m_lok, $m_periyot);
        $stmt->execute();
        $eklenen++;
        $m_kodlari[] = $m_kodu;
    }
}

echo "<h2>Import İşlemi Tamamlandı!</h2>";
echo "<p>Toplam <strong>$eklenen</strong> adet makine sisteme eklendi.</p>";

if (count($m_kodlari) > 0) {
    echo "<h3>Eklenen Makineler:</h3>";
    echo "<ul>";
    foreach ($m_kodlari as $kod) {
        echo "<li>$kod</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Test veya tekrarlı çalıştırma yapıldığı için yeni makine eklenmedi. (Tümü zaten sistemde var)</p>";
}

echo "<br><br><a href='bakim.php' style='padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px;'>Bakım Paneline Dön</a>";

$baglanti->close();
?>
