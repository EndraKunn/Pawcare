/* PawCare - JS ringan tanpa dependensi */
(function () {
  // Konfirmasi sebelum aksi berbahaya: <form data-confirm="Pesan?">
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });


  // Pratinjau foto yang dipilih: <input type="file" data-preview="idGambar">
  document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input || input.type !== 'file' || !input.dataset.preview) return;
    var img = document.getElementById(input.dataset.preview);
    var file = input.files && input.files[0];
    if (img && file && file.type.indexOf('image/') === 0) {
      img.onerror = null;
      img.src = URL.createObjectURL(file);
    }
  });

  // Pratinjau total booking (HANYA tampilan; server menghitung ulang total sebenarnya).
  var box = document.getElementById('bk');
  if (box) {
    var rp = function (n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); };
    var rows = box.querySelectorAll('.svc');
    var list = document.getElementById('sumList');
    var totalEl = document.getElementById('sumTotal');
    var update = function () {
      var html = '', total = 0;
      rows.forEach(function (row) {
        var cb = row.querySelector('input[type=checkbox]');
        var q = row.querySelector('input[type=number]');
        row.classList.toggle('on', cb.checked);
        if (cb.checked) {
          var qty = Math.max(1, parseInt(q.value || '1', 10));
          var price = parseFloat(row.dataset.price);
          total += price * qty;
          html += '<div class="sum-row"><span></span><b>' + rp(price * qty) + '</b></div>';
          var span = document.createElement('span');
          span.textContent = row.dataset.name + ' × ' + qty;
          html = html.replace('<span></span>', span.outerHTML);
        }
      });
      list.innerHTML = html || '<p class="sm mut">Belum ada layanan dipilih.</p>';
      totalEl.textContent = rp(total);
    };
    box.addEventListener('input', update);
    box.addEventListener('change', update);
    update();
  }
})();
