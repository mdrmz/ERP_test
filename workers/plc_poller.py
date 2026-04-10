import time
import sys
import logging
import os

# Sisteme kök dizini ekle
sys.path.append('/var/www/html')
from plc_reader import oku_ve_kaydet

# Logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler('/var/www/html/plc_reader.log', encoding='utf-8'),
        logging.StreamHandler()
    ]
)
log = logging.getLogger(__name__)

if __name__ == '__main__':
    log.info("PLC Modbus Poller başlatılıyor... (30 saniyede bir okuyacak)")
    while True:
        try:
            oku_ve_kaydet(test_mode=False)
        except Exception as e:
            log.error(f"Okuma döngüsünde hata: {e}")
        
        # 30 saniye bekle
        time.sleep(30)
