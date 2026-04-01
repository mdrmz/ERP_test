"""
PLC Modbus TCP Reader
=====================
Tüm PLC cihazlarından Modbus TCP ile veri okuyup MySQL'e kaydeder.
Cron/Scheduled Task ile dakikada 1 çalıştırılmalıdır.

Gereksinimler:
    pip install pyModbusTCP mysql-connector-python

Kullanım:
    python plc_reader.py           # Normal çalıştırma
    python plc_reader.py --test    # Bağlantı testi (veri kaydetmez)
    python plc_reader.py --once    # Tek sefer çalıştır
"""

import sys
import struct
import json
import logging
from datetime import datetime

try:
    from pyModbusTCP.client import ModbusClient
except ImportError:
    print("HATA: pyModbusTCP kurulu değil. Kurmak için: pip install pyModbusTCP")
    sys.exit(1)

try:
    import mysql.connector
except ImportError:
    print("HATA: mysql-connector-python kurulu değil. Kurmak için: pip install mysql-connector-python")
    sys.exit(1)

# ============================================================
# AYARLAR
# ============================================================
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'yonetim_paneli',
    'charset': 'utf8mb4'
}

MODBUS_TIMEOUT = 3  # saniye
MODBUS_PORT = 502

# Logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler('plc_reader.log', encoding='utf-8'),
        logging.StreamHandler()
    ]
)
log = logging.getLogger(__name__)


# ============================================================
# MODBUS YARDIMCI FONKSİYONLAR
# ============================================================

def registers_to_float(regs):
    """2 adet 16-bit register'dan 32-bit IEEE 754 float değeri çıkarır."""
    if regs is None or len(regs) < 2:
        return None
    try:
        raw = struct.pack('>HH', regs[0], regs[1])
        return struct.unpack('>f', raw)[0]
    except Exception:
        return None


def registers_to_double(regs):
    """4 adet 16-bit register'dan 64-bit double değeri çıkarır."""
    if regs is None or len(regs) < 4:
        return None
    try:
        raw = struct.pack('>HHHH', regs[0], regs[1], regs[2], regs[3])
        return struct.unpack('>d', raw)[0]
    except Exception:
        return None


def registers_to_int(regs):
    """Tek 16-bit register'dan signed integer çıkarır."""
    if regs is None or len(regs) < 1:
        return None
    val = regs[0]
    if val >= 32768:
        val -= 65536
    return val


def read_tag_value(client, adres, veri_tipi, register_sayisi):
    """
    Bir Modbus etiketi okur ve Python değerine çevirir.
    """
    try:
        if veri_tipi in ('FLOAT', 'REAL'):
            regs = client.read_holding_registers(adres, 2)
            return registers_to_float(regs)
        elif veri_tipi == 'DOUBLE':
            regs = client.read_holding_registers(adres, 4)
            return registers_to_double(regs)
        elif veri_tipi in ('INT',):
            regs = client.read_holding_registers(adres, 1)
            return registers_to_int(regs)
        elif veri_tipi in ('BIT', 'BOOL'):
            regs = client.read_holding_registers(adres, 1)
            if regs and len(regs) > 0:
                return int(regs[0] & 1)
            return None
        else:
            regs = client.read_holding_registers(adres, register_sayisi)
            if regs:
                return regs[0] if len(regs) == 1 else list(regs)
            return None
    except Exception as e:
        log.warning(f"Register okuma hatası (adres={adres}, tip={veri_tipi}): {e}")
        return None


# ============================================================
# ANA OKUMA FONKSİYONU
# ============================================================

def oku_ve_kaydet(test_mode=False):
    """
    Tüm aktif PLC cihazlarını okur ve veritabanına kaydeder.
    """
    try:
        db = mysql.connector.connect(**DB_CONFIG)
        cursor = db.cursor(dictionary=True)
    except Exception as e:
        log.error(f"Veritabanı bağlantı hatası: {e}")
        return

    # Aktif cihazları çek
    cursor.execute("SELECT * FROM plc_cihazlari WHERE aktif = 1 ORDER BY id")
    cihazlar = cursor.fetchall()

    log.info(f"Toplam {len(cihazlar)} aktif cihaz bulundu.")

    for cihaz in cihazlar:
        cihaz_id = cihaz['id']
        cihaz_adi = cihaz['cihaz_adi']
        ip = cihaz['ip_adresi']
        port = cihaz['port'] or MODBUS_PORT

        # Bu cihazın etiketlerini çek
        cursor.execute(
            "SELECT * FROM plc_etiketleri WHERE cihaz_id = %s ORDER BY modbus_adres",
            (cihaz_id,)
        )
        etiketler = cursor.fetchall()

        if not etiketler:
            log.warning(f"[{cihaz_adi}] ({ip}) - Etiket tanımı yok, atlanıyor.")
            continue

        # Modbus bağlantısı
        client = ModbusClient(host=ip, port=port, timeout=MODBUS_TIMEOUT, auto_open=True)

        if not client.open():
            log.warning(f"[{cihaz_adi}] ({ip}:{port}) - Bağlantı kurulamadı!")
            # Son bağlantı durumunu güncelle
            cursor.execute(
                "UPDATE plc_cihazlari SET son_baglanti = NULL WHERE id = %s",
                (cihaz_id,)
            )
            db.commit()
            continue

        log.info(f"[{cihaz_adi}] ({ip}:{port}) - Bağlantı başarılı, {len(etiketler)} etiket okunuyor...")

        veriler = {}
        basarili = 0
        hatali = 0

        for etiket in etiketler:
            etiket_adi = etiket['etiket_adi']
            adres = etiket['modbus_adres']
            veri_tipi = etiket['veri_tipi']
            reg_say = etiket['register_sayisi']
            carpan = float(etiket['carpan'] or 1)

            deger = read_tag_value(client, adres, veri_tipi, reg_say)

            if deger is not None:
                # Çarpanı uygula
                if isinstance(deger, (int, float)) and carpan != 1.0:
                    deger = round(deger * carpan, 4)

                # Float değerleri makul hassasiyette yuvarla
                if isinstance(deger, float):
                    deger = round(deger, 4)

                veriler[etiket_adi] = deger
                basarili += 1
            else:
                hatali += 1

        client.close()

        if test_mode:
            log.info(f"[{cihaz_adi}] TEST MODU - {basarili} ok, {hatali} hata")
            if veriler:
                for k, v in veriler.items():
                    log.info(f"  {k}: {v}")
            continue

        # Veritabanına kaydet
        if veriler:
            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            json_str = json.dumps(veriler, ensure_ascii=False)

            cursor.execute(
                "INSERT INTO plc_okumalari (cihaz_id, okuma_zamani, veriler) VALUES (%s, %s, %s)",
                (cihaz_id, now, json_str)
            )

            # Son bağlantı zamanını güncelle
            cursor.execute(
                "UPDATE plc_cihazlari SET son_baglanti = %s WHERE id = %s",
                (now, cihaz_id)
            )

            db.commit()
            log.info(f"[{cihaz_adi}] ✅ {basarili} değer kaydedildi ({hatali} hatalı)")
        else:
            log.warning(f"[{cihaz_adi}] ⚠️ Hiç veri okunamadı!")

    cursor.close()
    db.close()
    log.info("Okuma döngüsü tamamlandı.")


# ============================================================
# ENTRY POINT
# ============================================================

if __name__ == '__main__':
    test_mode = '--test' in sys.argv

    if test_mode:
        log.info("=" * 50)
        log.info("TEST MODU - Veriler kaydedilmeyecek")
        log.info("=" * 50)

    oku_ve_kaydet(test_mode=test_mode)
