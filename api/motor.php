<?php
/**
 * Kawasaki Greentech — API Galeri Motor (live dari ERP)
 * ------------------------------------------------
 * Membaca tabel product_motor + motor_detail (hanya status TERSEDIA)
 * dari database ERP dengan user MySQL khusus SELECT-only (web_galeri).
 *
 * KEAMANAN:
 *  - PDO prepared statements (anti SQL injection)
 *  - User DB minimal: hanya SELECT pada tabel yang dibutuhkan
 *  - CORS dibatasi ke domain sendiri
 *  - Tanpa kredensial di kode ini (dibaca dari file config di luar webroot)
 *  - Cache singkat untuk mengurangi beban DB
 */

declare(strict_types=1);

// ---- Konfigurasi: kredensial DB TIDAK di repo — file config di luar webroot ----
// Lokasi dicari berurutan: (1) di luar webroot, (2) folder config (deny by .htaccess)
$__cfgCandidates = [
    dirname(__DIR__, 2) . '/web-config/web-db.php',   // /www/wwwroot/web-config/
    dirname(__DIR__, 1) . '/../web-config/web-db.php',
    __DIR__ . '/../config/web-db.php',                // fallback dalam webroot (dilindungi .htaccess)
];
$__cfgFile = null;
foreach ($__cfgCandidates as $__c) {
    if (is_file($__c)) { $__cfgFile = $__c; break; }
}
if ($__cfgFile === null) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'konfigurasi server belum tersedia']);
    exit;
}
$__cfg = require $__cfgFile;
if (!is_array($__cfg)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'konfigurasi server tidak valid']);
    exit;
}

// ---- CORS terbatas (domain sendiri; sesuaikan kalau subdomain dipakai) ----
$__allowed = isset($__cfg['cors']) && is_array($__cfg['cors']) ? $__cfg['cors'] : [];
$__origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($__origin !== '' && in_array($__origin, $__allowed, true)) {
    header('Access-Control-Allow-Origin: ' . $__origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // 5 menit

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'metode tidak diizinkan']);
    exit;
}

// ---- Koneksi DB ----
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $__cfg['host'] ?? '127.0.0.1',
            (int)($__cfg['port'] ?? 3306),
            $__cfg['dbname'] ?? 'greentech_prod'
        ),
        $__cfg['user'] ?? 'web_galeri',
        $__cfg['pass'] ?? '',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'koneksi database gagal']);
    exit;
}

// ---- Query utama (prepared, tanpa input user → tanpa risiko injeksi) ----
$sql = "
    SELECT pm.id,
           pm.nama,
           pm.kode_motor      AS kode,
           pm.harga,
           COUNT(md.id)       AS unit
    FROM product_motor pm
    JOIN motor_detail md
      ON md.id_motor = pm.id
     AND md.deleted_at IS NULL
     AND md.status_motor = 'TERSEDIA'
    WHERE pm.deleted_at IS NULL
    GROUP BY pm.id, pm.nama, pm.kode_motor, pm.harga
    ORDER BY unit DESC, pm.nama ASC
";
$sqlWarna = "
    SELECT DISTINCT md.id_motor AS id, md.warna
    FROM motor_detail md
    WHERE md.deleted_at IS NULL
      AND md.status_motor = 'TERSEDIA'
      AND md.id_motor IN (SELECT id FROM product_motor WHERE deleted_at IS NULL)
";

try {
    $rows = $pdo->query($sql)->fetchAll();

    // Kumpulkan warna per motor (1 query, batasi 6 warna per motor)
    $warnaByMotor = [];
    foreach ($pdo->query($sqlWarna)->fetchAll() as $w) {
        if (!isset($warnaByMotor[$w['id']])) {
            $warnaByMotor[$w['id']] = [];
        }
        if (count($warnaByMotor[$w['id']]) < 6) {
            $warnaByMotor[$w['id']][] = $w['warna'];
        }
    }

    // ---- Peta foto (slug → file). Jaga agar nama file aman (whitelist). ----
    $fotoMap = [
        'klx150se', 'klx150', 'klx150s', 'klx150sm', 'klx140', 'klx110r',
        'klx230', 'klx230r', 'klx230sherpa', 'klx230df', 'klx250s',
        'kle500', 'versysx250', 'ninjah2', 'ninjax10r', 'ninjax6r',
        'ninjax25r', 'ninja250', 'z900', 'w175', 'w230', 'meguros1',
        'kx85', 'kx65', 'kx112', 'kx250', 'kx450', 'bruteforce450',
        'bruteforce300', 'kfx90', 'vulcans', 'eliminator450',
    ];

    function slugMotor(string $kode, string $nama): ?string
    {
        $k = strtoupper($kode); $n = strtoupper($nama);
        $map = [
            'LX150K' => (strpos($n, 'SE') !== false) ? 'klx150se' : 'klx150',
            'LX150M' => 'klx150sm',
            'LX150L' => 'klx150s',
            'LX140'  => 'klx140',
            'LX110'  => 'klx110r',
            'LE500'  => 'kle500',
            'ZXT02J' => 'ninjah2',
            'ZXT02L' => 'ninjax10r',
            'ZX636'  => 'ninjax6r',
            'ZX250'  => 'ninjax25r',
            'BJ175'  => 'w175',
            'BJ230B' => 'meguros1',
            'BJ230A' => 'w230',
            'ZR900T' => 'z900',
            'KX085'  => 'kx85',
            'KX065'  => 'kx65',
            'KX112'  => 'kx112',
            'KX450'  => 'kx450',
            'KX252'  => 'kx250',
            'LX232P' => 'klx230r',
            'LX232S' => 'klx230sherpa',
            'LX232Y' => 'klx230df',
            'LX230B' => 'klx230',
            'LX250S' => 'klx250s',
            'VF450'  => 'bruteforce450',
            'VF300'  => 'bruteforce300',
            'SF090'  => 'kfx90',
            'LE250F' => 'versysx250',
            'EN650'  => 'vulcans',
            'EL450'  => 'eliminator450',
            'KR150'  => 'ninja250',
        ];
        foreach ($map as $prefix => $slug) {
            if (strpos($k, $prefix) === 0) {
                return $slug;
            }
        }
        return null;
    }

    $out = [];
    foreach ($rows as $r) {
        $slug = slugMotor((string)$r['kode'], (string)$r['nama']);
        if ($slug === null || !in_array($slug, $fotoMap, true)) {
            continue; // model tanpa foto resmi tidak ditampilkan
        }
        $out[] = [
            'id'       => (int)$r['id'],
            'nama'     => $r['nama'],
            'kode'     => $r['kode'],
            'harga'    => (int)$r['harga'],
            'unit'     => (int)$r['unit'],
            'warna'    => $warnaByMotor[$r['id']] ?? [],
            'foto'     => $slug . '.webp',
            'foto2'    => is_file(__DIR__ . '/../assets/img/' . $slug . '-2.webp') ? $slug . '-2.webp' : null,
            'kategori' => $__cfg['kategori'][$slug] ?? 'Lainnya',
        ];
    }

    $total = array_sum(array_column($out, 'unit'));

    echo json_encode([
        'sumber'     => 'ERP Kawasaki Greentech (live)',
        'diperbarui' => date('Y-m-d H:i'),
        'catatan'    => 'Hanya unit berstatus TERSEDIA. Harga belum termasuk pajak & biaya.',
        'total_unit' => $total,
        'motor'      => $out,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'gagal membaca data']);
    exit;
}
