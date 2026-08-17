# Kawasaki Greentech — Website Galeri Motor (E-Commerce)

Website galeri motor Kawasaki yang terhubung langsung ke database ERP
(`greentech_prod`) — menampilkan **hanya unit dengan status `TERSEDIA`**
dari tabel `product_motor` + `motor_detail`.

## Arsitektur

```
kawasakigreentech.co.id/
├── index.html          → Galeri (SSR-light, SEO-friendly)
├── css/style.css       → Tema Kawasaki (lime green)
├── js/app.js           → Render grid + modal + filter
├── api/motor.php       → API live (PDO prepared statements)
├── data/motor.json     → Snapshot (fallback preview / offline)
├── assets/img/*.webp   → Foto resmi Kawasaki (terkompresi, q85)
├── config/web-db.php   → Kredensial DB (DI LUAR WEBROOT, jangan di-commit)
├── robots.txt, sitemap.xml, .htaccess
```

- **Live di server**: `api/motor.php` membaca DB ERP via user MySQL
  `web_galeri` (SELECT-only). FE memakai API ini.
- **Fallback**: `data/motor.json` (snapshot) — dipakai kalau API tidak
  tersedia (preview statis / offline).

## Instalasi di Server (aaPanel / Apache + PHP 7.4)

1. **Buat user MySQL SELECT-only** (sekali saja, di server prod):
   ```sql
   CREATE USER 'web_galeri'@'localhost' IDENTIFIED BY '<password-kuat>';
   GRANT SELECT ON greentech_prod.product_motor  TO 'web_galeri'@'localhost';
   GRANT SELECT ON greentech_prod.motor_detail   TO 'web_galeri'@'localhost';
   FLUSH PRIVILEGES;
   ```
2. **Salin website** ke webroot (mis. `/www/wwwroot/kawasakigreentech.co.id/`).
3. **Buat konfigurasi di luar webroot**:
   ```bash
   mkdir -p /www/wwwroot/kawasakigreentech.co.id/config
   cp config/web-db.example.php /www/wwwroot/kawasakigreentech.co.id/config/web-db.php
   nano /www/wwwroot/kawasakigreentech.co.id/config/web-db.php
   chmod 600 /www/wwwroot/kawasakigreentech.co.id/config/web-db.php
   ```
   Isi `pass` dengan password user `web_galeri`, dan `cors` dengan domain publik.
4. **Pastikan** `.htaccess` aktif (aaPanel: `AllowOverride All`).
5. **Isi nomor WhatsApp** di `js/app.js` (variabel `CONFIG.WA`) atau ganti
   langsung di HTML.
6. Test: `https://domain/api/motor.php` → JSON motor tersedia.

## Update Data

- API live: otomatis (setiap request membaca DB, cache 5 menit).
- Snapshot `data/motor.json`: perbarui manual dari prod:
  ```sql
  SELECT pm.id, pm.nama, pm.kode_motor, pm.harga, COUNT(md.id), MIN(md.warna),
         GROUP_CONCAT(DISTINCT md.warna ORDER BY md.warna SEPARATOR '|')
  FROM product_motor pm
  JOIN motor_detail md ON md.id_motor=pm.id AND md.deleted_at IS NULL
       AND md.status_motor='TERSEDIA'
  WHERE pm.deleted_at IS NULL
  GROUP BY pm.id, pm.nama, pm.kode_motor, pm.harga
  ORDER BY COUNT(md.id) DESC;
  ```

## Sumber Foto

Foto produk diunduh dari situs resmi Kawasaki Indonesia
(`kawasaki-motor.co.id` — CDN `kawasaki-global-admin.com`) dan dikompres
ke WebP (quality 85, dimensi asli) tanpa menurunkan resolusi sumber.
© PT Kawasaki Motor Indonesia — digunakan untuk promosi dealer resmi.

## Keamanan

- User DB minimal (SELECT saja, tabel dibatasi).
- PDO prepared statements — tanpa injeksi SQL.
- Kredensial di luar webroot, dilarang oleh `.htaccess` & `robots.txt`.
- Security headers (CSP, HSTS, X-Frame-Options, nosniff).
- Hotlink protection + `Options -Indexes`.
- Tidak ada input user → permukaan serangan minimal.
