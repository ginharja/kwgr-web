<?php
/**
 * Kawasaki Greentech — API Prospek (submit nomor WA calon pembeli)
 * Menyimpan prospek ke database web (web_kwgr.prospek).
 *
 * Keamanan:
 *  - Hanya menerima POST
 *  - Validasi nomor WhatsApp (format Indonesia)
 *  - Rate limit sederhana per IP (mencegah spam)
 *  - PDO prepared statements
 *  - Kredensial dibaca dari config di luar webroot
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metode tidak diizinkan']);
    exit;
}

// ---- Config ----
$cfgCandidates = [
    dirname(__DIR__, 2) . '/web-config/web-db.php',
    __DIR__ . '/../config/web-db.php',
];
$cfgFile = null;
foreach ($cfgCandidates as $c) {
    if (is_file($c)) { $cfgFile = $c; break; }
}
if ($cfgFile === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Konfigurasi server belum tersedia']);
    exit;
}
$cfg = require $cfgFile;

// ---- Input ----
$nama     = trim((string)($_POST['nama'] ?? ''));
$noWa     = trim((string)($_POST['no_wa'] ?? ''));
$idMotor  = trim((string)($_POST['id_motor'] ?? ''));

$digits = preg_replace('/[\s\-().]/', '', $noWa);
if (!preg_match('/^(\+?62|0)[0-9]{8,14}$/', $digits)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Nomor WhatsApp tidak valid']);
    exit;
}
// Normalisasi ke format 62xxxx
if (strpos($digits, '0') === 0) {
    $digits = '62' . substr($digits, 1);
} elseif (strpos($digits, '+62') === 0) {
    $digits = '62' . substr($digits, 3);
}

if (mb_strlen($nama) > 120) { $nama = mb_substr($nama, 0, 120); }

$idMotorInt = null;
if ($idMotor !== '' && ctype_digit($idMotor)) {
    $idMotorInt = (int)$idMotor;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['web']['host'] ?? '127.0.0.1',
            (int)($cfg['web']['port'] ?? 3306),
            $cfg['web']['dbname'] ?? 'web_kwgr'
        ),
        $cfg['web']['user'] ?? 'web_admin',
        $cfg['web']['pass'] ?? '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Koneksi database gagal']);
    exit;
}

// ---- Rate limit: maks 1 prospek / IP / 30 detik ----
try {
    $st = $pdo->prepare('SELECT COUNT(*) FROM prospek WHERE ip = ? AND created_at > (NOW() - INTERVAL 30 SECOND)');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() > 0) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Terlalu banyak permintaan. Coba lagi sebentar lagi.']);
        exit;
    }
} catch (Throwable $e) {
    // tabel mungkin belum ada — lanjut (insert akan gagal dengan pesan jelas)
}

// ---- Resolve nama motor dari ERP (opsional) ----
$namaMotor = null;
if ($idMotorInt !== null && !empty($cfg['erp'])) {
    try {
        $erp = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $cfg['erp']['host'] ?? '127.0.0.1',
                (int)($cfg['erp']['port'] ?? 3306),
                $cfg['erp']['dbname'] ?? 'greentech_prod'
            ),
            $cfg['erp']['user'] ?? 'web_admin',
            $cfg['erp']['pass'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $st = $erp->prepare('SELECT nama FROM product_motor WHERE id = ? LIMIT 1');
        $st->execute([$idMotorInt]);
        $namaMotor = $st->fetchColumn() ?: null;
    } catch (Throwable $e) {
        $namaMotor = null;
    }
}

try {
    $st = $pdo->prepare(
        'INSERT INTO prospek (nama, no_wa, id_motor, nama_motor, sumber, ip)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $st->execute([$nama !== '' ? $nama : null, $digits, $idMotorInt, $namaMotor, 'website', $ip]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Gagal menyimpan data']);
    exit;
}
