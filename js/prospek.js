/* Kawasaki Greentech — prospek.js
   Form "Dapatkan Penawaran": validasi nomor WA + kirim ke api/prospek.php */
(function () {
  "use strict";
  var form = document.getElementById("prospek-form");
  var select = document.getElementById("prospek-motor");
  var msg = document.getElementById("prospek-msg");
  if (!form || !select || !msg) return;

  // Isi pilihan motor dari API (fallback ke snapshot JSON)
  fetch("api/motor.php")
    .then(function (r) { return r.json(); })
    .catch(function () { return fetch("data/motor.json").then(function (r) { return r.json(); }); })
    .then(function (d) {
      var list = (d && d.motor) || [];
      list.forEach(function (m) {
        var o = document.createElement("option");
        o.value = m.id;
        o.textContent = m.nama + " — " + "Rp " + Number(m.harga || 0).toLocaleString("id-ID");
        select.appendChild(o);
      });
    })
    .catch(function () { /* biarkan pilihan kosong */ });

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var nama = (form.nama.value || "").trim();
    var noWa = (form.no_wa.value || "").trim();
    var idMotor = form.id_motor.value || "";

    var digits = noWa.replace(/[\s\-().]/g, "");
    if (!/^(\+?62|0)[0-9]{8,14}$/.test(digits)) {
      msg.textContent = "Nomor WhatsApp tidak valid. Gunakan format 08xx atau +62xxx.";
      msg.className = "prospek-msg err";
      return;
    }

    msg.textContent = "Mengirim…";
    msg.className = "prospek-msg";

    var body = new URLSearchParams();
    body.set("nama", nama);
    body.set("no_wa", digits);
    body.set("id_motor", idMotor);

    fetch("api/prospek.php", {
      method: "POST",
      body: body,
      headers: { "Content-Type": "application/x-www-form-urlencoded" }
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.ok) {
          msg.textContent = "Terima kasih! Tim sales kami akan segera menghubungi Anda via WhatsApp.";
          msg.className = "prospek-msg ok";
          form.reset();
        } else {
          msg.textContent = (j && j.error) || "Gagal mengirim. Coba lagi.";
          msg.className = "prospek-msg err";
        }
      })
      .catch(function () {
        msg.textContent = "Gagal terhubung ke server. Coba lagi atau hubungi WhatsApp 0812-7775-5006.";
        msg.className = "prospek-msg err";
      });
  });
})();
