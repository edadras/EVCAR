/* ساخت QR واقعی (qrcode-generator) برای نمایش و چاپ برچسب کانکتور. */
(function () {
  function build(text) {
    var q = qrcode(0, 'M');
    q.addData(text);
    q.make();
    return q;
  }

  window.renderQr = function (text, box) {
    if (!box) return null;
    if (!text) { box.innerHTML = ''; return null; }
    var q = build(text);
    box.innerHTML = q.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
    var svg = box.querySelector('svg');
    if (svg) { svg.style.width = '100%'; svg.style.height = '100%'; }
    return svg;
  };

  /** برچسب PNG با حاشیهٔ سفید کافی (quiet zone) و عنوان زیر کد. */
  window.downloadQrLabel = function (text, title, subtitle, filename) {
    var q = build(text);
    var n = q.getModuleCount(), cell = 12, quiet = 4 * cell;
    var size = n * cell + quiet * 2, textH = 110;
    var canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size + textH;
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#000';
    for (var r = 0; r < n; r++) {
      for (var c = 0; c < n; c++) {
        if (q.isDark(r, c)) ctx.fillRect(quiet + c * cell, quiet + r * cell, cell, cell);
      }
    }
    ctx.textAlign = 'center';
    ctx.direction = 'rtl';
    ctx.font = 'bold 34px Vazirmatn, Tahoma, sans-serif';
    ctx.fillText(title || '', size / 2, size + 40);
    ctx.font = '24px Vazirmatn, Tahoma, sans-serif';
    ctx.fillStyle = '#444';
    ctx.fillText(subtitle || '', size / 2, size + 82);
    var a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = filename || 'qr.png';
    a.click();
  };
})();
