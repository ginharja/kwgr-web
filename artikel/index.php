<?php
declare(strict_types=1);
/**
 * Daftar artikel — menampilkan artikel yang sudah tayang (tanggal <= hari ini).
 * Artikel dengan tanggal di masa depan otomatis tersembunyi (jadwal tayang).
 */
$BASE = 'https://kawasakigreentech.co.id';
$raw = @file_get_contents(__DIR__ . '/data/artikel.json');
$artikel = json_decode((string)$raw, true);
if (!is_array($artikel)) { $artikel = []; }

$today = date('Y-m-d');
$tayang = array_values(array_filter($artikel, function ($a) use ($today) {
    return isset($a['date']) && $a['date'] <= $today;
}));
usort($tayang, function ($a, $b) { return strcmp($b['date'], $a['date']); });

$BULAN = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
function bulan_id(string $ymd): string {
    global $BULAN;
    $p = explode('-', $ymd);
    return (int)$p[2] . ' ' . $BULAN[(int)$p[1]] . ' ' . $p[0];
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$kickerFirst = true;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="google-site-verification" content="9gxcSuHEoebysxcoBnpU1rqRlmcLU-N_QscUfbv83fk" />
<title>Artikel AI Digital Marketing Denpasar | Kawasaki Greentech</title>
<meta name="description" content="Kumpulan artikel tentang jasa privat AI digital marketing di Denpasar, Bali: materi, biaya Rp 5 juta untuk 8x pertemuan, tips, dan panduan untuk UMKM.">
<meta name="robots" content="index, follow">
<meta name="theme-color" content="#6fa82c">
<link rel="canonical" href="https://kawasakigreentech.co.id/artikel/">
<link rel="icon" type="image/png" href="/assets/favicon.png">
<meta property="og:type" content="website">
<meta property="og:title" content="Artikel AI Digital Marketing Denpasar | Kawasaki Greentech">
<meta property="og:description" content="Panduan dan materi jasa privat AI digital marketing di Denpasar, Bali.">
<meta property="og:url" content="https://kawasakigreentech.co.id/artikel/">
<link rel="stylesheet" href="/css/style.css?v=11">
</head>
<body>
<header class="site-header" id="top">
  <div class="container header-inner">
    <a href="/" class="brand" aria-label="Kawasaki Greentech — Beranda">
      <img src="/assets/img/logo-inv.png" alt="Logo Kawasaki Greentech" height="40">
    </a>
    <nav class="site-nav" aria-label="Navigasi utama">
      <a href="/#galeri">Galeri Motor</a>
      <a href="/artikel/">Artikel</a>
      <a href="/#kontak">Kontak</a>
      <a class="btn-wa" href="https://wa.me/6281277755006" target="_blank" rel="noopener">Hubungi Kami</a>
    </nav>
    <button class="nav-toggle" aria-label="Buka menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>
<main>
<section class="section artikel-listing">
  <div class="container">
    <div class="section-head">
      <p class="section-kicker">Artikel</p>
      <h1>Jasa Privat AI Digital Marketing Denpasar</h1>
      <p>Belajar AI digital marketing secara privat: 8x pertemuan, biaya Rp 5 juta, lokasi di Denpasar, Bali. Baca panduan dan materi berikut.</p>
    </div>
    <div class="artikel-grid">
<?php foreach ($tayang as $a): ?>
      <a class="artikel-card" href="/artikel/<?= e($a['slug']) ?>/">
        <p class="artikel-card-kicker"><?= e($a['kicker']) ?></p>
        <h2><?= e($a['title']) ?></h2>
        <p class="artikel-card-excerpt"><?= e($a['excerpt']) ?></p>
        <p class="artikel-card-date"><time datetime="<?= e($a['date']) ?>"><?= bulan_id($a['date']) ?></time></p>
      </a>
<?php endforeach; ?>
<?php if (count($tayang) === 0): ?>
      <p class="artikel-empty">Belum ada artikel yang tayang. Silakan kembali lagi nanti.</p>
<?php endif; ?>
    </div>
  </div>
</section>
</main>
<footer class="site-footer">
  <div class="container footer-inner">
    <div>
      <p class="footer-brand">KAWASAKI <span>Greentech</span></p>
      <p>PT Greentech Cakrawala Motorindo — dealer resmi motor Kawasaki. Jl. Soekarno-Hatta No.1, Pekanbaru, Riau 28292. WA 0812-7775-5006.</p>
    </div>
    <nav aria-label="Navigasi footer">
      <a href="/#galeri">Galeri Motor</a>
      <a href="/artikel/">Artikel</a>
      <a href="/#kontak">Kontak</a>
    </nav>
  </div>
  <div class="container footer-bottom">
    <p>© <span id="year"></span> Kawasaki Greentech. Hak cipta dilindungi.</p>
  </div>
</footer>
<script>document.getElementById("year").textContent = new Date().getFullYear();</script>
</body>
</html>
