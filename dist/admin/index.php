<?php
/**
 * Kawasaki Greentech — Dashboard Admin (prospek & funneling)
 * Akses: /admin/  (login dengan password admin dari config web-db.php)
 */
declare(strict_types=1);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store');

// ---- Config ----
$cfgFile = null;
foreach ([dirname(__DIR__, 2) . '/web-config/web-db.php', __DIR__ . '/../config/web-db.php'] as $c) {
    if (is_file($c)) { $cfgFile = $c; break; }
}
if ($cfgFile === null) { http_response_code(500); echo 'Konfigurasi server belum tersedia'; exit; }
$cfg = require $cfgFile;

$adminPass = $cfg['web']['admin_pass'] ?? null;

// ---- PDO ----
function db(): PDO {
    global $cfg;
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['web']['host'] ?? '127.0.0.1', (int)($cfg['web']['port'] ?? 3306), $cfg['web']['dbname'] ?? 'web_kwgr'),
        $cfg['web']['user'] ?? 'web_admin', $cfg['web']['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ---- Logout ----
if (($_GET['logout'] ?? '') === '1') {
    session_destroy();
    header('Location: index.php'); exit;
}

// ---- Login ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'login') {
    $pw = (string)($_POST['password'] ?? '');
    if ($adminPass && password_verify($pw, $adminPass)) {
        $_SESSION['admin'] = true;
        header('Location: index.php'); exit;
    }
    $loginErr = 'Password salah.';
}

if (empty($_SESSION['admin'])) {
    // Halaman login
    ?><!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login Admin — Kawasaki Greentech</title>
<style>
body{font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f3f6f8;display:grid;place-items:center;min-height:100vh;margin:0}
.card{background:#fff;padding:34px;border-radius:14px;box-shadow:0 12px 34px rgba(15,23,32,.10);width:min(360px,92%)}
h1{font-size:20px;margin:0 0 6px;color:#10161d}
p{color:#5b6673;font-size:14px;margin:0 0 18px}
input{width:100%;padding:12px 14px;border:1px solid rgba(15,23,32,.12);border-radius:10px;font-size:15px;box-sizing:border-box}
button{width:100%;margin-top:12px;padding:12px;border:0;border-radius:10px;background:#6fa82c;color:#fff;font-weight:700;font-size:15px;cursor:pointer}
.err{color:#d93a3a;font-size:13px;margin-top:10px}
</style></head>
<body><form class="card" method="post">
<h1>Dashboard Admin</h1><p>Kawasaki Greentech — kelola prospek &amp; funneling</p>
<input type="password" name="password" placeholder="Password admin" autofocus>
<button type="submit">Masuk</button>
<input type="hidden" name="aksi" value="login">
<?php if (!empty($loginErr)): ?><p class="err"><?= e($loginErr) ?></p><?php endif; ?>
</form></body></html><?php
    exit;
}

// ---- Update status ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'], $_POST['id'], $_POST['status'])) {
    $id = (int)$_POST['id'];
    $status = (int)$_POST['status'];
    if (in_array($status, [0, 1, 2, 3], true)) {
        db()->prepare('UPDATE prospek SET status = ? WHERE id = ?')->execute([$status, $id]);
    }
    header('Location: index.php'); exit;
}

// ---- Data ----
$pdo = db();
$total    = (int)$pdo->query('SELECT COUNT(*) FROM prospek')->fetchColumn();
$today    = (int)$pdo->query('SELECT COUNT(*) FROM prospek WHERE DATE(created_at) = CURDATE()')->fetchColumn();
$week     = (int)$pdo->query('SELECT COUNT(*) FROM prospek WHERE created_at >= (NOW() - INTERVAL 7 DAY)')->fetchColumn();
$deal     = (int)$pdo->query('SELECT COUNT(*) FROM prospek WHERE status = 2')->fetchColumn();

$funnel = $pdo->query(
    "SELECT status, COUNT(*) c FROM prospek GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$funnel = array_map('intval', $funnel) + [0 => 0, 1 => 0, 2 => 0, 3 => 0];

$topMotor = $pdo->query(
    "SELECT COALESCE(nama_motor, '(tanpa pilihan motor)') AS nm, COUNT(*) c
     FROM prospek GROUP BY nama_motor ORDER BY c DESC, nm ASC LIMIT 10"
)->fetchAll();

$list = $pdo->query(
    "SELECT id, nama, no_wa, nama_motor, status, created_at, ip
     FROM prospek ORDER BY id DESC LIMIT 50"
)->fetchAll();

$statusLabel = [0 => 'Baru', 1 => 'Dihubungi', 2 => 'Deal', 3 => 'Batal'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard Prospek — Kawasaki Greentech</title>
<style>
body{font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f3f6f8;color:#10161d;margin:0;line-height:1.5}
header{background:#fff;border-bottom:1px solid rgba(15,23,32,.08);padding:0 4vw;height:60px;display:flex;align-items:center;justify-content:space-between}
header b{color:#6fa82c}
.wrap{max-width:1080px;margin:24px auto;padding:0 4vw}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px}
.stat{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:0 2px 8px rgba(15,23,32,.05)}
.stat .n{font-size:28px;font-weight:800;color:#588a17}
.stat .l{font-size:12.5px;color:#5b6673;text-transform:uppercase;letter-spacing:.05em}
.cols{display:grid;grid-template-columns:1fr 1.4fr;gap:18px}
@media(max-width:820px){.cols{grid-template-columns:1fr}}
.panel{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(15,23,32,.05);margin-bottom:18px}
.panel h2{font-size:16px;margin:0 0 14px}
table{width:100%;border-collapse:collapse;font-size:13.5px}
th,td{padding:9px 8px;border-bottom:1px solid rgba(15,23,32,.06);text-align:left;vertical-align:top}
th{color:#5b6673;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
.bar-row{display:flex;align-items:center;gap:10px;margin:6px 0}
.bar-row .nm{flex:0 0 46%;font-size:13px}
.bar{height:18px;border-radius:4px;background:#6fa82c;min-width:2px}
.bar-row .cnt{font-size:12.5px;color:#5b6673}
.pill{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:700}
.p0{background:#eef4ff;color:#2b6cb0}.p1{background:#fff4e5;color:#b7791f}
.p2{background:#e9f7e9;color:#2f855a}.p3{background:#fdeaea;color:#c53030}
select{font-size:12.5px;padding:4px 6px;border:1px solid rgba(15,23,32,.15);border-radius:6px}
.logout{color:#5b6673;font-size:13.5px;text-decoration:none}
.logout:hover{color:#d93a3a}
.muted{color:#8a95a1}
</style>
</head>
<body>
<header><div><b>Kawasaki Greentech</b> · Dashboard Prospek</div><a class="logout" href="?logout=1">Keluar</a></header>
<div class="wrap">

<div class="stats">
  <div class="stat"><div class="n"><?= $total ?></div><div class="l">Total Prospek</div></div>
  <div class="stat"><div class="n"><?= $today ?></div><div class="l">Hari Ini</div></div>
  <div class="stat"><div class="n"><?= $week ?></div><div class="l">7 Hari Terakhir</div></div>
  <div class="stat"><div class="n"><?= $deal ?></div><div class="l">Deal (Closing)</div></div>
</div>

<div class="cols">
  <div>
    <div class="panel">
      <h2>Funnel Status</h2>
      <div class="bar-row"><span class="nm">Baru</span><div class="bar" style="width:<?= min(100, $funnel[0] * 4) ?>%"></div><span class="cnt"><?= $funnel[0] ?></span></div>
      <div class="bar-row"><span class="nm">Dihubungi</span><div class="bar" style="width:<?= min(100, $funnel[1] * 4) ?>%"></div><span class="cnt"><?= $funnel[1] ?></span></div>
      <div class="bar-row"><span class="nm">Deal</span><div class="bar" style="width:<?= min(100, $funnel[2] * 4) ?>%"></div><span class="cnt"><?= $funnel[2] ?></span></div>
      <div class="bar-row"><span class="nm">Batal</span><div class="bar" style="width:<?= min(100, $funnel[3] * 4) ?>%"></div><span class="cnt"><?= $funnel[3] ?></span></div>
    </div>

    <div class="panel">
      <h2>Motor Paling Banyak Ditanyakan</h2>
      <?php if (!$topMotor): ?><p class="muted">Belum ada data.</p><?php endif; ?>
      <?php $max = $topMotor ? max(array_column($topMotor, 'c')) : 1; ?>
      <?php foreach ($topMotor as $m): ?>
        <div class="bar-row">
          <span class="nm"><?= e((string)$m['nm']) ?></span>
          <div class="bar" style="width:<?= round($m['c'] / $max * 100) ?>%"></div>
          <span class="cnt"><?= $m['c'] ?> prospek</span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <h2>Daftar Prospek Terbaru</h2>
    <table>
      <thead><tr><th>#</th><th>WA</th><th>Nama</th><th>Motor</th><th>Status</th><th>Waktu</th></tr></thead>
      <tbody>
      <?php foreach ($list as $r): ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= e((string)$r['no_wa']) ?></td>
          <td><?= e((string)($r['nama'] ?? '—')) ?></td>
          <td><?= e((string)($r['nama_motor'] ?? '—')) ?></td>
          <td>
            <form method="post" style="margin:0">
              <input type="hidden" name="aksi" value="status">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <select name="status" onchange="this.form.submit()">
                <?php foreach ($statusLabel as $k => $lbl): ?>
                  <option value="<?= $k ?>" <?= (int)$r['status'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td class="muted"><?= e(date('d/m H:i', strtotime((string)$r['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="6" class="muted">Belum ada prospek.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div>
</body>
</html>
