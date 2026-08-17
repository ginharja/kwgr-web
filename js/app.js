/* ============================================================
   Kawasaki Greentech — app.js
   - Muat data motor (live API di server, fallback motor.json)
   - Render grid, filter kategori, modal detail, WhatsApp CTA
   ============================================================ */
(function () {
  "use strict";

  // ---------- Konfigurasi (sesuaikan di server) ----------
  var CONFIG = {
    // Di server Vultr: API live membaca DB ERP (SELECT-only, tanpa kredensial di sini)
    API: "api/motor.php",
    // Nomor WhatsApp sales (format internasional tanpa "+")
    WA: "6281277755006",
    WA_TEXT: "Halo Kawasaki Greentech, saya ingin bertanya tentang motor Kawasaki."
  };

  var state = {
    data: null,
    filter: "semua"
  };

  var els = {
    grid: document.getElementById("motor-grid"),
    gridStatus: document.getElementById("grid-status"),
    modal: document.getElementById("modal"),
    modalBody: document.getElementById("modal-body"),
    loader: document.getElementById("loader"),
    statUnit: document.getElementById("stat-unit"),
    statModel: document.getElementById("stat-model"),
    year: document.getElementById("year")
  };

  // ---------- Util ----------
  function fmtRupiah(n) {
    return "Rp " + Number(n || 0).toLocaleString("id-ID");
  }

  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  function waHref(motor) {
    var base = "https://wa.me/" + (CONFIG.WA || "6285356878391");
    var text = encodeURIComponent(
      "Halo Kawasaki Greentech, saya tertarik dengan " +
      (motor ? motor.nama + " (" + fmtRupiah(motor.harga) + ")" : "motor Kawasaki") +
      ". Apakah masih tersedia?"
    );
    return base + "?text=" + text;
  }

  function slug(k) {
    return String(k || "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
  }

  function detailHref(m) {
    return "motor/" + slug(m.kode) + ".html";
  }

  function setWaLinks() {
    document.querySelectorAll("[data-wa]").forEach(function (a) {
      a.setAttribute("href", waHref(null));
      a.setAttribute("target", "_blank");
      a.setAttribute("rel", "noopener");
    });
  }

  // ---------- Load data ----------
  function loadData() {
    // 1) Coba API live (server) — timeout 4 dtk
    var apiCtrl = null;
    try { apiCtrl = new AbortController(); } catch (e) { apiCtrl = { signal: undefined }; }

    var timer = setTimeout(function () {
      if (apiCtrl && apiCtrl.abort) apiCtrl.abort();
    }, 4000);

    return fetch(CONFIG.API, { signal: apiCtrl.signal })
      .then(function (r) {
        if (!r.ok) throw new Error("API " + r.status);
        return r.json();
      })
      .then(function (json) {
        if (!json || !Array.isArray(json.motor) || !json.motor.length) throw new Error("kosong");
        return json;
      })
      .catch(function () {
        // 2) Fallback: snapshot JSON (preview & offline)
        return fetch("data/motor.json").then(function (r) { return r.json(); });
      })
      .finally(function () { clearTimeout(timer); });
  }

  // ---------- Render ----------
  function cardHTML(m) {
    var unit = m.unit > 0
      ? '<p class="card-unit"><b>' + m.unit + " unit</b> tersedia</p>"
      : '<p class="card-unit">Stok menyusul</p>';
    return (
      '<article class="card" data-id="' + m.id + '" tabindex="0" role="button" aria-label="Lihat detail ' + esc(m.nama) + '">' +
        '<div class="card-photo">' +
          '<span class="card-badge">● Tersedia</span>' +
          '<span class="card-cat">' + esc(m.kategori) + "</span>" +
          '<img src="assets/img/' + esc(m.foto) + '?v=7" alt="Motor Kawasaki ' + esc(m.nama) + '" loading="lazy" width="640" height="400">' +
        "</div>" +
        '<div class="card-body">' +
          '<p class="card-kode">' + esc(m.kode) + "</p>" +
          '<h3 class="card-nama"><a href="' + detailHref(m) + '">' + esc(m.nama) + "</a></h3>" +
          '<p class="card-harga">' + fmtRupiah(m.harga) + " <small>OTR*</small></p>" +
          unit +
          '<div class="card-cta"><span class="btn btn-primary">Lihat Detail</span></div>' +
        "</div>" +
      "</article>"
    );
  }

  function renderGrid() {
    var list = state.data.motor;
    if (state.filter !== "semua") {
      list = list.filter(function (m) { return m.kategori === state.filter; });
    }
    els.grid.innerHTML = list.map(cardHTML).join("");
    els.gridStatus.textContent = list.length + " motor ditampilkan";
    els.grid.querySelectorAll(".card").forEach(function (card) {
      card.addEventListener("click", function () { openModal(Number(card.dataset.id)); });
      card.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openModal(Number(card.dataset.id)); }
      });
    });
  }

  function findMotor(id) {
    return (state.data.motor || []).find(function (m) { return m.id === id; });
  }

  function openModal(id) {
    var m = findMotor(id);
    if (!m) return;
    var warna = (m.warna && m.warna.length)
      ? m.warna.map(function (w) { return "<b>" + esc(w) + "</b>"; }).join(", ")
      : "<b>—</b>";
    var allFotos = [m.foto, m.foto2, m.foto3, m.foto4, m.foto5, m.foto6].filter(function (f) { return f; });
    var slides = "", dots = "";
    allFotos.forEach(function (f, i) {
      slides += '<div class="carousel-slide' + (i === 0 ? " is-active" : "") + '"><img src="assets/img/' + esc(f) + '?v=7" alt="Kawasaki ' + esc(m.nama) + ' ' + esc(m.kode) + ' Pekanbaru — foto ' + (i + 1) + '" loading="' + (i === 0 ? "eager" : "lazy") + '"></div>';
      dots += '<button class="carousel-dot' + (i === 0 ? " is-active" : "") + '" data-i="' + i + '" type="button" aria-label="Foto ' + (i + 1) + '"></button>';
    });
    var controls = allFotos.length > 1
      ? '<button class="carousel-prev" type="button" aria-label="Foto sebelumnya">&#8249;</button><button class="carousel-next" type="button" aria-label="Foto berikutnya">&#8250;</button><div class="carousel-dots">' + dots + '</div>'
      : "";
    var carousel = '<div class="carousel"><div class="carousel-track">' + slides + '</div>' + controls + '</div>';
    var deskripsi = m.deskripsi
      ? '<div class="spec-detail"><h4>Spesifikasi Teknis</h4><p>' + esc(m.deskripsi) + '</p></div>'
      : "";
    els.modalBody.innerHTML =
      carousel +
      '<p class="img-caption">Gambar hanya ilustrasi, warna sesuai yang tersedia di toko.</p>' +
      '<div class="modal-info">' +
        '<p class="modal-kode">' + esc(m.kode) + "</p>" +
        "<h3>" + esc(m.nama) + "</h3>" +
        '<p class="modal-cat">Kategori: ' + esc(m.kategori) + "</p>" +
        '<p class="modal-price">' + fmtRupiah(m.harga) + " <small>OTR*</small></p>" +
        '<p class="modal-desc">Harga belum termasuk biaya administrasi, pajak dan asuransi. Hubungi tim sales kami untuk penawaran resmi dan simulasi kredit.</p>' +
        '<ul class="modal-specs">' +
          "<li><span>Unit Tersedia</span><b>" + m.unit + " unit</b></li>" +
          "<li><span>Warna</span>" + warna + "</li>" +
          "<li><span>Kode Motor</span><b>" + esc(m.kode) + "</b></li>" +
        "</ul>" +
        deskripsi +
        '<div class="modal-actions">' +
          '<a class="btn btn-primary" href="' + waHref(m) + '" target="_blank" rel="noopener">💬 Tanya & Cek Ketersediaan</a>' +
          '<a class="btn btn-ghost" href="' + detailHref(m) + '">Lihat Halaman Detail</a>' +
        "</div>" +
      "</div>";
    els.modal.hidden = false;
    document.body.style.overflow = "hidden";
    initCarousel(els.modalBody.querySelector(".carousel"));
    els.modal.querySelectorAll("[data-close], [data-close-inline]").forEach(function (el) {
      el.addEventListener("click", closeModal);
    });
    els.modalBody.querySelector(".modal-actions .btn-ghost").addEventListener("click", closeModal);
  }

  function closeModal() {
    els.modal.hidden = true;
    document.body.style.overflow = "";
  }

  // ---------- Carousel (slide foto jika > 1) ----------
  function initCarousel(root) {
    if (!root) return;
    var track = root.querySelector(".carousel-track");
    var slides = root.querySelectorAll(".carousel-slide");
    var dots = root.querySelectorAll(".carousel-dot");
    var prev = root.querySelector(".carousel-prev");
    var next = root.querySelector(".carousel-next");
    if (!track || slides.length <= 1) return;
    var idx = 0;
    function go(n) {
      idx = (n + slides.length) % slides.length;
      track.style.transform = "translateX(-" + (idx * 100) + "%)";
      dots.forEach(function (d, i) { d.classList.toggle("is-active", i === idx); });
    }
    if (prev) prev.addEventListener("click", function () { go(idx - 1); });
    if (next) next.addEventListener("click", function () { go(idx + 1); });
    dots.forEach(function (d, i) { d.addEventListener("click", function () { go(i); }); });
  }

  // ---------- Init ----------
  function initFilters() {
    document.querySelectorAll(".filter-chip").forEach(function (chip) {
      chip.addEventListener("click", function () {
        document.querySelectorAll(".filter-chip").forEach(function (c) { c.classList.remove("active"); });
        chip.classList.add("active");
        state.filter = chip.dataset.filter;
        renderGrid();
      });
    });
  }

  function initNav() {
    var toggle = document.querySelector(".nav-toggle");
    var nav = document.querySelector(".site-nav");
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    nav.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        nav.classList.remove("open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  function initModalClose() {
    els.modal.querySelectorAll("[data-close]").forEach(function (el) {
      el.addEventListener("click", closeModal);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !els.modal.hidden) closeModal();
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    els.year.textContent = new Date().getFullYear();
    setWaLinks();
    initNav();
    initFilters();
    initModalClose();

    els.loader.hidden = false;
    loadData()
      .then(function (json) {
        state.data = json;
        els.statUnit.textContent = Number(json.total_unit || 0).toLocaleString("id-ID");
        els.statModel.textContent = (json.motor || []).length;
        renderGrid();
      })
      .catch(function (e) {
        els.grid.innerHTML =
          '<p style="padding:2rem;text-align:center;color:var(--muted)">' +
          "Gagal memuat data motor. Silakan hubungi kami via WhatsApp." +
          "</p>";
        els.gridStatus.textContent = "Data gagal dimuat";
      })
      .finally(function () {
        els.loader.hidden = true;
      });
  });
})();
