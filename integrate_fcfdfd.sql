-- FCFDFD Tablosundan musteriler tablosuna veri entegrasyonu
-- Bu script mükerrer kayıtları (cari_kod bazlı) günceller, yeni kayıtları ekler.

INSERT INTO musteriler (
    cari_kod, 
    cari_tip, 
    firma_adi, 
    telefon, 
    eposta, 
    vergi_dairesi, 
    vergi_no, 
    il, 
    ilce, 
    adres
)
SELECT 
    TRIM(KODU) as cari_kod,
    CASE 
        WHEN LEFT(TRIM(KODU), 3) = '120' THEN 'Müşteri' 
        ELSE 'Tedarikçi' 
    END as cari_tip,
    TRIM(CONCAT(IFNULL(ÜNVANI_1,''), ' ', IFNULL(ÜNVANI_2,''))) as firma_adi,
    TRIM(TEL_NO) as telefon,
    TRIM(MAIL) as eposta,
    TRIM(VD_ADI) as vergi_dairesi,
    TRIM(VD) as vergi_no,
    TRIM(Column_9) as il,
    TRIM(Column_10) as ilce,
    TRIM(CONCAT(IFNULL(CADDE,''), ' ', IFNULL(SOKAK,''))) as adres
FROM FCFDFD
ON DUPLICATE KEY UPDATE 
    firma_adi = VALUES(firma_adi),
    telefon = VALUES(telefon),
    eposta = VALUES(eposta),
    vergi_dairesi = VALUES(vergi_dairesi),
    vergi_no = VALUES(vergi_no),
    il = VALUES(il),
    ilce = VALUES(ilce),
    adres = VALUES(adres);
