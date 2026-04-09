# Kantar Poller - systemd Kurulum

## 1) Migration
```bash
php /var/www/html/ERP_test/migrations/kantar_migrate.php
```

## 2) Servisi kur (otomatik baslat + reboot sonrasi devam)
```bash
sudo bash /var/www/html/ERP_test/workers/systemd/install_kantar_poller_service.sh \
  /var/www/html/ERP_test \
  /usr/bin/php \
  www-data \
  20
```

## 3) Durum ve log
```bash
sudo systemctl status kantar-poller.service --no-pager
sudo journalctl -u kantar-poller.service -f
```

## 4) Servis komutlari
```bash
sudo systemctl restart kantar-poller.service
sudo systemctl stop kantar-poller.service
sudo systemctl start kantar-poller.service
sudo systemctl disable kantar-poller.service
```
