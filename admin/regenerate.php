<?php
/**
 * Kawasaki Greentech — Regenerate halaman detail & sitemap dari database ERP.
 * Dipanggil dari dashboard admin (setelah edit/upload) atau langsung dengan session admin.
 */
declare(strict_types=1);

function kwgr_cfg(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $file = null;
    foreach ([dirname(__DIR__, 2) . '/web-config/web-db.php', __DIR__ . '/../config/web-db.php'] as $c) {
        if (is_file($c)) { $file = $c; break; }
    }
    if ($file === null) { throw new RuntimeException('config tidak tersedia'); }
    $cfg = require $file;
    return $cfg;
}

function kwgr_erp(): PDO {
    $c = kwgr_cfg();
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $c['erp']['host'] ?? '127.0.0.1', (int)($c['erp']['port'] ?? 3306), $c['erp']['dbname'] ?? 'greentech_prod'),
        $c['erp']['user'] ?? 'web_admin', $c['erp']['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

function kwgr_slug(string $k): string {
    $s = strtolower($k);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

function kwgr_esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function kwgr_rp($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

function kwgr_wa(string $nama): string {
    return 'https://wa.me/6281277755006?text=' . rawurlencode('Halo Kawasaki Greentech, saya tertarik dengan ' . $nama . '. Apakah masih tersedia?');
}

function regenerate_site(): array {
    $pdo = kwgr_erp();
    $BASE = 'https://kawasakigreentech.co.id';
    $webroot = __DIR__ . '/..';

    $rows = $pdo->query("
        SELECT pm.id, pm.nama, pm.kode_motor AS kode, pm.harga,
               COALESCE(pm.foto,'') AS foto, COALESCE(pm.foto2,'') AS foto2,
               COALESCE(pm.kategori,'Lainnya') AS kategori, COALESCE(pm.deskripsi,'') AS deskripsi,
               COUNT(md.id) AS unit
        FROM product_motor pm
        JOIN motor_detail md ON md.id_motor = pm.id AND md.deleted_at IS NULL AND md.status_motor = 'TERSEDIA'
        WHERE pm.deleted_at IS NULL
        GROUP BY pm.id, pm.nama, pm.kode_motor, pm.harga, pm.foto, pm.foto2, pm.kategori, pm.deskripsi
        ORDER BY COUNT(md.id) DESC, pm.nama ASC
    ")->fetchAll();

    $warnaBy = [];
    foreach ($pdo->query("SELECT DISTINCT id_motor AS id, warna FROM motor_detail WHERE deleted_at IS NULL AND status_motor='TERSEDIA'")->fetchAll() as $w) {
        if (!isset($warnaBy[$w['id']])) $warnaBy[$w['id']] = [];
        if (count($warnaBy[$w['id']]) < 6) $warnaBy[$w['id']][] = $w['warna'];
    }

    @mkdir($webroot . '/motor', 0755, true);
    $n = 0;
    $urls = ['<url><loc>' . $BASE . '/</loc><changefreq>daily</changefreq><priority>1.0</priority></url>'];

    foreach ($rows as $m) {
        if ($m['foto'] === '') continue;
        $slug = kwgr_slug($m['kode']);
        $nama = $m['nama'];
        $warnaList = $warnaBy[$m['id']] ?? [];
        $harga = kwgr_rp($m['harga']);
        $hargaNum = (int)$m['harga'];
        $descMeta = $nama . ' (' . $m['kode'] . ') tersedia ' . $m['unit'] . ' unit di dealer resmi Kawasaki Greentech, Riau. Harga ' . $harga . ' OTR*.';
        $title = $nama . ' — Harga & Stok ' . $harga . ' | Kawasaki Greentech';

        $foto2block = $m['foto2'] !== ''
            ? '<img src="../assets/img/' . kwgr_esc($m['foto2']) . '" alt="Motor Kawasaki ' . kwgr_esc($nama) . ' tampak lain" loading="lazy" width="800" height="450">'
            : '';

        $specsHtml = '';
        foreach ([
            'Kode Motor' => $m['kode'],
            'Kategori' => $m['kategori'],
            'Unit Tersedia' => $m['unit'] . ' unit',
            'Warna' => $warnaList ? implode(', ', $warnaList) : '—',
        ] as $k => $v) {
            $specsHtml .= '<li><span>' . kwgr_esc((string)$k) . '</span><b>' . kwgr_esc((string)$v) . '</b></li>';
        }

        $deskripsiBlock = $m['deskripsi'] !== ''
            ? '<div class="spec-detail"><h2>Spesifikasi Teknis</h2><p>' . kwgr_esc($m['deskripsi']) . '</p></div>'
            : '';

        $ldProduct = [
            '@context' => 'https://schema.org', '@type' => 'Product',
            'name' => $nama,
            'image' => $BASE . '/assets/img/' . $m['foto'],
            'description' => $descMeta,
            'brand' => ['@type' => 'Brand', 'name' => 'Kawasaki'],
            'sku' => $m['kode'],
            'offers' => [
                '@type' => 'Offer', 'priceCurrency' => 'IDR', 'price' => (string)$m['harga'],
                'availability' => 'https://schema.org/InStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@type' => 'AutoDealer', 'name' => 'Kawasaki Greentech'],
            ],
        ];
        $ldBreadcrumb = [
            '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => $BASE . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Galeri Motor', 'item' => $BASE . '/#galeri'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $nama, 'item' => $BASE . '/motor/' . $slug . '.html'],
            ],
        ];
        $ldFaq = [
            '@context' => 'https://schema.org', '@type' => 'FAQPage',
            'mainEntity' => [
                ['@type' => 'Question', 'name' => 'Apakah harga di website sudah final?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Harga belum termasuk biaya administrasi, pajak, dan asuransi. Hubungi tim sales kami untuk penawaran resmi.']],
                ['@type' => 'Question', 'name' => 'Apakah unit yang tampil benar-benar tersedia?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Ya, hanya unit berstatus TERSEDIA di sistem dealer yang ditampilkan dan diperbarui secara berkala.']],
                ['@type' => 'Question', 'name' => 'Bagaimana cara pengajuan kredit?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Hubungi WhatsApp 0812-7775-5006, tim kami akan membantu simulasi dan pengajuan ke leasing resmi.']],
                ['@type' => 'Question', 'name' => 'Apakah warna yang tampil sesuai dengan unit di toko?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Gambar hanya ilustrasi; ketersediaan warna mengikuti stok yang tersedia di toko.']],
            ],
        ];

        $ldJson = json_encode($ldProduct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ldBcJson = json_encode($ldBreadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ldFaqJson = json_encode($ldFaq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $wa = kwgr_wa($nama);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
<meta name="description" content="{$descMeta}">
<meta name="robots" content="index, follow">
<link rel="canonical" href="{$BASE}/motor/{$slug}.html">
<link rel="icon" type="image/png" href="../assets/favicon.png">
<meta property="og:type" content="product">
<meta property="og:title" content="{$title}">
<meta property="og:description" content="{$descMeta}">
<meta property="og:image" content="{$BASE}/assets/img/{$m['foto']}">
<meta property="og:url" content="{$BASE}/motor/{$slug}.html">
<script type="application/ld+json">{$ldJson}</script>
<script type="application/ld+json">{$ldBcJson}</script>
<script type="application/ld+json">{$ldFaqJson}</script>
<link rel="stylesheet" href="../css/style.css?v=5">
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a href="../index.html" class="brand" aria-label="Kawasaki Greentech — Beranda">
      <img src="../assets/img/logo-inv.png" alt="Logo Kawasaki Greentech" height="40">
    </a>
    <nav class="site-nav" aria-label="Navigasi">
      <a href="../index.html#galeri">Galeri Motor</a>
      <a href="../index.html#tentang">Tentang Kami</a>
      <a href="../index.html#kontak">Kontak</a>
      <a class="btn-wa" href="{$wa}" target="_blank" rel="noopener">Hubungi Kami</a>
    </nav>
  </div>
</header>
<main>
<section class="section">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="../index.html">Beranda</a> <span>/</span> <a href="../index.html#galeri">Galeri</a> <span>/</span> <span>{$nama}</span>
    </nav>
    <div class="detail-layout">
      <div class="detail-photos">
        <div class="photo-wrap">
          <img src="../assets/img/{$m['foto']}" alt="Motor Kawasaki {$nama}" width="800" height="450">
          <p class="img-caption">Gambar hanya ilustrasi, warna sesuai yang tersedia di toko.</p>
        </div>
        {$foto2block}
      </div>
      <div class="detail-info">
        <p class="modal-kode">{$m['kode']}</p>
        <h1>{$nama}</h1>
        <p class="modal-cat">Kategori: {$m['kategori']}</p>
        <p class="modal-price">{$harga} <small>OTR*</small></p>
        <p class="modal-desc">Harga belum termasuk biaya administrasi, pajak, dan asuransi. Hubungi tim sales kami untuk penawaran resmi dan simulasi kredit.</p>
        <ul class="modal-specs">{$specsHtml}</ul>
        <div class="modal-actions">
          <a class="btn btn-primary" href="{$wa}" target="_blank" rel="noopener">💬 Tanya &amp; Cek Ketersediaan</a>
          <a class="btn btn-ghost" href="../index.html#kontak">Kunjungi Showroom</a>
        </div>
      </div>
    </div>
    {$deskripsiBlock}
    <div class="kredit-card">
      <h2>Simulasi Kredit</h2>
      <p class="modal-desc">Estimasi kasar — bunga &amp; tenor final ditentukan lembaga pembiayaan (leasing). Bukan penawaran resmi.</p>
      <div class="kredit-grid">
        <label>Uang Muka (DP) <span id="dp-val">0%</span>
          <input type="range" id="dp" min="0" max="50" step="5" value="20" data-harga="{$hargaNum}">
        </label>
        <label>Tenor <span id="tenor-val">36 bulan</span>
          <input type="range" id="tenor" min="12" max="48" step="12" value="36">
        </label>
        <label>Bunga flat/bulan <span id="bunga-val">1.5%</span>
          <input type="range" id="bunga" min="0.8" max="2.5" step="0.1" value="1.5">
        </label>
      </div>
      <div class="kredit-hasil">
        <div><span>Cicilan / bulan</span><strong id="kredit-cicilan">—</strong></div>
        <div><span>Total pinjaman</span><strong id="kredit-pokok">—</strong></div>
        <div><span>Estimasi total bayar</span><strong id="kredit-total">—</strong></div>
      </div>
    </div>
    <p class="back-link"><a href="../index.html#galeri">← Kembali ke galeri motor</a></p>
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
      <a href="../index.html#galeri">Galeri Motor</a>
      <a href="../index.html#tentang">Tentang Kami</a>
      <a href="../index.html#kontak">Kontak</a>
    </nav>
  </div>
  <div class="container footer-bottom">
    <p>© <span id="year"></span> Kawasaki Greentech. Foto © PT Kawasaki Motor Indonesia.</p>
  </div>
</footer>
<script src="../js/kredit.js?v=3" defer></script>
<script>document.getElementById("year").textContent = new Date().getFullYear();</script>
</body>
</html>
HTML;
        file_put_contents($webroot . '/motor/' . $slug . '.html', $html);
        $urls[] = '<url><loc>' . $BASE . '/motor/' . $slug . '.html</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>';
        $n++;
    }

    $sitemap = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n  " .
        implode("\n  ", $urls) . "\n</urlset>\n";
    file_put_contents($webroot . '/sitemap.xml', $sitemap);

    return ['detail' => $n, 'sitemap' => count($urls)];
}

// Jika dipanggil langsung — jalankan (CLI tanpa session, atau HTTP dengan session admin)
if (php_sapi_name() === 'cli' || basename($_SERVER['SCRIPT_NAME'] ?? '') === 'regenerate.php') {
    if (php_sapi_name() !== 'cli') {
        session_start();
        if (empty($_SESSION['admin'])) { http_response_code(403); echo 'Akses ditolak'; exit; }
    }
    try {
        $r = regenerate_site();
        echo 'OK — ' . $r['detail'] . ' halaman detail, ' . $r['sitemap'] . ' URL sitemap' . "\n";
    } catch (Throwable $e) {
        if (php_sapi_name() === 'cli') { fwrite(STDERR, 'Gagal: ' . $e->getMessage() . "\n"); exit(1); }
        http_response_code(500);
        echo 'Gagal: ' . htmlspecialchars($e->getMessage());
    }
}
