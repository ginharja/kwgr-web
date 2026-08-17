<?php
/**
 * KONFIGURASI DATABASE — WEB GALERI MOTOR
 * ========================================
 * File ini DI LUAR webroot (jangan pernah diletakkan di folder publik).
 * Diba-ca oleh api/motor.php. Kredensial ini TIDAK masuk repository.
 *
 * Cara pakai:
 *  1. Buat user MySQL khusus SELECT-only (lihat README):
 *     CREATE USER 'web_galeri'@'localhost' IDENTIFIED BY '<password-kuat>';
 *     GRANT SELECT ON greentech_prod.product_motor TO 'web_galeri'@'localhost';
 *     GRANT SELECT ON greentech_prod.motor_detail TO 'web_galeri'@'localhost';
 *     FLUSH PRIVILEGES;
 *  2. Simpan file ini di /www/wwwroot/kawasakigreentech.co.id/config/web-db.php
 *     (di luar folder web publik, contoh: /www/wwwroot/../web-config/ atau
 *      cukup satu folder di atas webroot), lalu chmod 600.
 */
return [
    'host'   => '127.0.0.1',
    'port'   => 3306,
    'dbname' => 'greentech_prod',
    'user'   => 'web_galeri',
    'pass'   => 'GANTI_DENGAN_PASSWORD_KUAT',

    // Domain yang diizinkan akses CORS (isi domain publik website)
    'cors' => [
        'https://kawasakigreentech.co.id',
        'http://kawasakigreentech.co.id',
        'https://web.kawasakigreentech.co.id',
    ],

    // Kategori tampilan per slug foto (utk konsistensi tanpa query tambahan)
    'kategori' => [
        'klx150se' => 'Trail', 'klx150' => 'Trail', 'klx150s' => 'Trail', 'klx140' => 'Trail',
        'klx110r' => 'Trail', 'klx230' => 'Trail', 'klx230sherpa' => 'Trail', 'klx250s' => 'Trail',
        'klx230r' => 'Off-Road', 'klx230df' => 'Off-Road', 'kx85' => 'Off-Road', 'kx65' => 'Off-Road',
        'kx112' => 'Off-Road', 'kx250' => 'Off-Road', 'kx450' => 'Off-Road',
        'klx150sm' => 'Supermoto', 'ninjah2' => 'Hypersport', 'ninjax10r' => 'Superbike',
        'ninjax6r' => 'Sport', 'ninjax25r' => 'Sport', 'ninja250' => 'Sport',
        'z900' => 'Naked', 'w175' => 'Retro', 'w230' => 'Retro', 'meguros1' => 'Retro',
        'kle500' => 'Adventure', 'versysx250' => 'Adventure',
        'bruteforce450' => 'ATV', 'bruteforce300' => 'ATV', 'kfx90' => 'ATV',
        'vulcans' => 'Cruiser', 'eliminator450' => 'Cruiser',
    ],
];
