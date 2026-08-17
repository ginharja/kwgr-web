/* Kawasaki Greentech — simulasi kredit sederhana (estimasi, bukan penawaran resmi) */
(function () {
  "use strict";
  function fmt(n) {
    return "Rp " + Math.round(n).toLocaleString("id-ID");
  }
  var harga = 0;
  var elDp = document.getElementById("dp");
  var elTenor = document.getElementById("tenor");
  var elBunga = document.getElementById("bunga");
  if (!elDp) return;
  harga = Number(elDp.dataset.harga || 0);

  function hitung() {
    var dpPct = Number(elDp.value) / 100;
    var tenor = Number(elTenor.value);
    var bunga = Number(elBunga.value) / 100; // flat per bulan
    var dp = harga * dpPct;
    var pokok = harga - dp;
    var total = pokok * (1 + bunga * tenor);
    var cicilan = total / tenor;
    document.getElementById("dp-val").textContent = dpPct * 100 + "% (" + fmt(dp) + ")";
    document.getElementById("tenor-val").textContent = tenor + " bulan";
    document.getElementById("bunga-val").textContent = (bunga * 100).toFixed(1) + "%";
    document.getElementById("kredit-cicilan").textContent = fmt(cicilan);
    document.getElementById("kredit-pokok").textContent = fmt(pokok);
    document.getElementById("kredit-total").textContent = fmt(total);
  }
  [elDp, elTenor, elBunga].forEach(function (el) {
    el.addEventListener("input", hitung);
  });
  hitung();
})();
