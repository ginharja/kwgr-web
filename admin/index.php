<?php
/**
 * Kawasaki Greentech — Dashboard Admin (prospek + kelola data motor)
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

function db(): PDO {
    global $cfg;
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['web']['host'] ?? '127.0.0.1', (int)($cfg['web']['port'] ?? 3306), $cfg['web']['dbname'] ?? 'web_kwgr'),
        $cfg['web']['user'] ?? 'web_admin', $cfg['web']['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}
function erp(): PDO {
    global $cfg;
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['erp']['host'] ?? '127.0.0.1', (int)($cfg['erp']['port'] ?? 3306), $cfg['erp']['dbname'] ?? 'greentech_prod'),
        $cfg['erp']['user'] ?? 'web_admin', $cfg['erp']['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if (($_GET['logout'] ?? '') === '1') { session_destroy(); header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'login') {
    $pw = (string)($_POST['password'] ?? '');
    if ($adminPass && password_verify($pw, $adminPass)) { $_SESSION['admin'] = true; header('Location: index.php'); exit; }
    $loginErr = 'Password salah.';
}

if (empty($_SESSION['admin'])) {
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
<h1>Dashboard Admin</h1><p>Kawasaki Greentech — kelola prospek &amp; data motor</p>
<input type="password" name="password" placeholder="Password admin" autofocus>
<button type="submit">Masuk</button>
<input type="hidden" name="aksi" value="login">
<?php if (!empty($loginErr)): ?><p class="err"><?= e($loginErr) ?></p><?php endif; ?>
</form></body></html><?php
    exit;
}

$view = $_GET['view'] ?? 'prospek';

// ---- Update status prospek ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'status') {
    $id = (int)$_POST['id']; $status = (int)$_POST['status'];
    if (in_array($status, [0, 1, 2, 3], true)) {
        db()->prepare('UPDATE prospek SET status = ? WHERE id = ?')->execute([$status, $id]);
    }
    header('Location: index.php'); exit;
}

// ---- Edit data motor ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit_motor') {
    $id = (int)$_POST['id'];
    $foto = trim((string)($_POST['foto'] ?? ''));
    $foto2 = trim((string)($_POST['foto2'] ?? ''));
    $kategori = trim((string)($_POST['kategori'] ?? ''));
    $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));
    $harga = trim((string)($_POST['harga'] ?? ''));
    $st = erp()->prepare('UPDATE product_motor SET foto=?, foto2=?, kategori=?, deskripsi=?, harga=? WHERE id=?');
    $st->execute([
        $foto !== '' ? $foto : null,
        $foto2 !== '' ? $foto2 : null,
        $kategori !== '' ? $kategori : null,
        $deskripsi !== '' ? $deskripsi : null,
        $harga !== '' ? (int)$harga : null,
        $id,
    ]);
    header('Location: index.php?view=motor&saved=1'); exit;
}

// ---- Upload foto motor ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'upload_foto') {
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!empty($_FILES['foto_file']['name']) && $_FILES['foto_file']['error'] === UPLOAD_ERR_OK && $_FILES['foto_file']['size'] < 3 * 1024 * 1024) {
        $ext = strtolower(pathinfo((string)$_FILES['foto_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed, true)) {
            $name = preg_replace('/[^a-z0-9_.-]+/i', '', (string)($_POST['namafile'] ?? ''));
            if ($name === '') { $name = 'foto-' . time() . '.' . $ext; }
            if (!preg_match('/\.' . $ext . '$/i', $name)) { $name .= '.' . $ext; }
            if (move_uploaded_file($_FILES['foto_file']['tmp_name'], __DIR__ . '/../assets/img/' . $name)) {
                header('Location: index.php?view=motor&uploaded=' . rawurlencode($name)); exit;
            }
        }
    }
    header('Location: index.php?view=motor&uperr=1'); exit;
}

// ---- Data prospek ----
$pdo = db();
$total = (int)$pdo->query('SELECT COUNT(*) FROM prospek')->fetchColumn();
$today = (int)$pdo->query('SELECT COUNT(*) FROM prospek WHERE DATE(created_at) = CURDATE()')->fetchColumn();
$week  = (int)$pdo->query('SELECT COUNT(*) FROM prospek WHERE created_at >= (NOW() - INTERVAL 7 DAY)')->fetchColumn();
$deal  = (int)$pdo->query('SELECT COUNT(*) FROM prospek WHERE status = 2')->fetchColumn();

$funnel = $pdo->query("SELECT status, COUNT(*) c FROM prospek GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
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

// ---- Data motor (kelola) ----
$motorList = [];
if ($view === 'motor') {
    $q = trim((string)($_GET['q'] ?? ''));
    $sql = "SELECT id, kode_motor, nama, harga, foto, foto2, kategori, deskripsi FROM product_motor WHERE deleted_at IS NULL";
    if ($q !== '') { $sql .= " AND (nama LIKE ? OR kode_motor LIKE ?)"; }
    $sql .= " ORDER BY id DESC LIMIT 100";
    $st = erp()->prepare($sql);
    if ($q !== '') { $st->execute(['%' . $q . '%', '%' . $q . '%']); } else { $st->execute(); }
    $motorList = $st->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard Admin — Kawasaki Greentech</title>
<style>
body{font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f3f6f8;color:#10161d;margin:0;line-height:1.5}
header{background:#fff;border-bottom:1px solid rgba(15,23,32,.08);padding:0 4vw;height:60px;display:flex;align-items:center;justify-content:space-between}
header b{color:#6fa82c;margin-right:16px}
.tab{color:#5b6673;font-size:14px;text-decoration:none;margin-right:14px;padding:6px 2px;border-bottom:2px solid transparent}
.tab.on{color:#10161d;font-weight:700;border-bottom-color:#6fa82c}
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
.logout{color:#5b6673;font-size:13.5px;text-decoration:none}
.logout:hover{color:#d93a3a}
.muted{color:#8a95a1}
select,input[type=text],input[type=number],textarea{font-size:13px;padding:6px 8px;border:1px solid rgba(15,23,32,.15);border-radius:6px;font-family:inherit}
.motor-form{display:grid;gap:8px;background:#f7f9fb;border-radius:8px;padding:12px;margin-top:8px}
.motor-form label{font-size:11.5px;color:#5b6673;display:flex;flex-direction:column;gap:3px}
.motor-row{font-size:12px}
.motor-thumb{width:64px;height:40px;object-fit:cover;border-radius:6px;background:#eef2f5}
.save-btn{background:#6fa82c;color:#fff;border:0;border-radius:6px;padding:7px 12px;font-weight:700;cursor:pointer;margin-top:4px}
.upbar{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px}
.notice{background:#e9f7e9;color:#2f855a;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13.5px}
</style>
</head>
<body>
<header><div>
<b>Kawasaki Greentech</b>
<a class="tab <?= $view === 'prospek' ? 'on' : '' ?>" href="?view=prospek">Prospek</a>
<a class="tab <?= $view === 'motor' ? 'on' : '' ?>" href="?view=motor">Data Motor</a>
</div><a class="logout" href="?logout=1">Keluar</a></header>
<div class="wrap">

<?php if ($view === 'prospek'): ?>

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
              <input type="hidden" name="aksi" value="status"><input type="hidden" name="id" value="<?= $r['id'] ?>">
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

<?php else: ?>

<div class="panel">
  <h2>Kelola Data Motor (foto, kategori, deskripsi, harga)</h2>
  <p class="muted">Perubahan langsung tersimpan ke database ERP dan tampil di website (galeri &amp; halaman detail).</p>

  <?php if (($_GET['saved'] ?? '') === '1'): ?><div class="notice">✓ Data motor tersimpan.</div><?php endif; ?>
  <?php if (($_GET['uperr'] ?? '') === '1'): ?><div class="notice" style="background:#fdeaea;color:#c53030">Gagal upload foto (ukuran/format tidak sesuai).</div><?php endif; ?>
  <?php if (!empty($_GET['uploaded'])): ?><div class="notice">✓ Foto terupload: <code><?= e((string)$_GET['uploaded']) ?></code> — pakai nama ini di kolom "Foto".</div><?php endif; ?>

  <form class="upbar" method="get">
    <input type="hidden" name="view" value="motor">
    <input type="text" name="q" placeholder="Cari nama/kode motor…" value="<?= e((string)($_GET['q'] ?? '')) ?>">
    <button class="save-btn" type="submit">Cari</button>
  </form>

  <form class="upbar" method="post" enctype="multipart/form-data">
    <input type="hidden" name="aksi" value="upload_foto">
    <label style="font-size:12px;color:#5b6673">Upload foto (max 3MB, jpg/png/webp)
      <input type="file" name="foto_file" accept=".jpg,.jpeg,.png,.webp" required>
    </label>
    <label style="font-size:12px;color:#5b6673">Nama file (mis. klx150se.webp)
      <input type="text" name="namafile" placeholder="klx150se.webp">
    </label>
    <button class="save-btn" type="submit">Upload ke assets/img</button>
  </form>

  <table>
    <thead><tr><th>Foto</th><th>Kode</th><th>Nama</th><th>Harga</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($motorList as $m): ?>
      <tr class="motor-row">
        <td><?php if ($m['foto']): ?><img class="motor-thumb" src="../assets/img/<?= e((string)$m['foto']) ?>" alt=""><?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td><?= e((string)$m['kode_motor']) ?></td>
        <td><?= e((string)$m['nama']) ?></td>
        <td><?= $m['harga'] ? number_format((int)$m['harga'], 0, ',', '.') : '—' ?></td>
        <td>
          <details>
            <summary style="cursor:pointer;color:#2b6cb0">Edit</summary>
            <form class="motor-form" method="post">
              <input type="hidden" name="aksi" value="edit_motor"><input type="hidden" name="id" value="<?= $m['id'] ?>">
              <label>Foto (nama file di assets/img)
                <input type="text" name="foto" value="<?= e((string)($m['foto'] ?? '')) ?>">
              </label>
              <label>Foto kedua (opsional)
                <input type="text" name="foto2" value="<?= e((string)($m['foto2'] ?? '')) ?>">
              </label>
              <label>Kategori
                <input type="text" name="kategori" value="<?= e((string)($m['kategori'] ?? '')) ?>" placeholder="Trail / Sport / Naked / …">
              </label>
              <label>Harga (angka)
                <input type="number" name="harga" value="<?= (int)$m['harga'] ?>">
              </label>
              <label>Deskripsi / Spesifikasi
                <textarea name="deskripsi" rows="3"><?= e((string)($m['deskripsi'] ?? '')) ?></textarea>
              </label>
              <button class="save-btn" type="submit">Simpan</button>
            </form>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$motorList): ?><tr><td colspan="5" class="muted">Tidak ada hasil.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php endif; ?>

</div>
</body>
</html>
