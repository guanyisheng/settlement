/**
 * 活动大转盘
 * drawUrl: POST 返回 {ok, prize_id, prize_name, points_awarded, index, chances, points}
 */
(function () {
  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    var root = document.getElementById('h5Lottery');
    if (!root) return;

    var prizes = [];
    try {
      prizes = JSON.parse(root.getAttribute('data-prizes') || '[]');
    } catch (e) {
      prizes = [];
    }
    var drawUrl = root.getAttribute('data-draw-url') || '';
    var activityId = root.getAttribute('data-activity-id') || '';
    var canDraw = root.getAttribute('data-can-draw') === '1';
    var spinning = false;

    var canvas = document.getElementById('wheelCanvas');
    var disk = document.getElementById('wheelDisk');
    var goBtn = document.getElementById('wheelGo');
    var chanceEl = document.getElementById('chipChances');
    var pointsEl = document.getElementById('chipPoints');
    var modal = document.getElementById('h5ResultModal');
    var modalPrize = document.getElementById('h5ResultPrize');
    var modalSub = document.getElementById('h5ResultSub');
    var modalClose = document.getElementById('h5ResultClose');

    if (!canvas || !disk || !goBtn || prizes.length === 0) {
      if (goBtn) goBtn.disabled = true;
      return;
    }

    var colors = ['#fff4d6', '#ffd0d6', '#ffe9a8', '#ffc9b8', '#fff0c2', '#ffd6e7', '#ffe2b0', '#ffccc0'];
    var n = prizes.length;
    var slice = (Math.PI * 2) / n;
    var currentRot = 0;

    function drawWheel() {
      var size = canvas.clientWidth || 300;
      var dpr = window.devicePixelRatio || 1;
      canvas.width = size * dpr;
      canvas.height = size * dpr;
      var ctx = canvas.getContext('2d');
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      var cx = size / 2;
      var cy = size / 2;
      var r = size / 2 - 2;

      for (var i = 0; i < n; i++) {
        var start = -Math.PI / 2 + i * slice;
        var end = start + slice;
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, r, start, end);
        ctx.closePath();
        ctx.fillStyle = colors[i % colors.length];
        ctx.fill();
        ctx.strokeStyle = 'rgba(200, 80, 40, 0.25)';
        ctx.lineWidth = 1;
        ctx.stroke();

        ctx.save();
        ctx.translate(cx, cy);
        ctx.rotate(start + slice / 2);
        ctx.textAlign = 'right';
        ctx.fillStyle = '#6b2a12';
        ctx.font = 'bold ' + Math.max(11, Math.floor(size / 22)) + 'px sans-serif';
        var label = String(prizes[i].name || '');
        if (label.length > 6) label = label.slice(0, 6) + '…';
        ctx.fillText(label, r - 14, 4);
        ctx.restore();
      }

      ctx.beginPath();
      ctx.arc(cx, cy, 28, 0, Math.PI * 2);
      ctx.fillStyle = '#fff';
      ctx.fill();
    }

    drawWheel();
    window.addEventListener('resize', drawWheel);

    function showModal(name, sub) {
      if (!modal) return;
      if (modalPrize) modalPrize.textContent = name || '';
      if (modalSub) modalSub.textContent = sub || '';
      modal.classList.add('show');
    }
    function hideModal() {
      if (modal) modal.classList.remove('show');
    }
    if (modalClose) modalClose.addEventListener('click', hideModal);
    if (modal) {
      modal.addEventListener('click', function (e) {
        if (e.target === modal) hideModal();
      });
    }

    function indexOfPrize(prizeId) {
      for (var i = 0; i < n; i++) {
        if (Number(prizes[i].id) === Number(prizeId)) return i;
      }
      return 0;
    }

    /** 指针在顶部：扇区 i 中心对准顶部 */
    function rotationForIndex(index) {
      var degPer = 360 / n;
      var target = 360 - (index * degPer + degPer / 2);
      var extra = 360 * 6;
      return currentRot + extra + ((target - (currentRot % 360)) + 360) % 360;
    }

    goBtn.addEventListener('click', function () {
      if (spinning || !canDraw) return;
      spinning = true;
      goBtn.disabled = true;

      var fd = new FormData();
      fd.append('action', 'draw');
      fd.append('ajax', '1');
      if (activityId) fd.append('activity_id', activityId);

      fetch(drawUrl || location.href, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      })
        .then(function (r) {
          return r.json().then(function (data) {
            return { okHttp: r.ok, data: data };
          });
        })
        .then(function (res) {
          var data = res.data || {};
          if (!data.ok) {
            throw new Error(data.message || '抽奖失败');
          }
          var idx = typeof data.index === 'number' ? data.index : indexOfPrize(data.prize_id);
          var next = rotationForIndex(idx);
          disk.style.transition = 'transform 4.2s cubic-bezier(0.12, 0.75, 0.12, 1)';
          disk.style.transform = 'rotate(' + next + 'deg)';
          currentRot = next;

          setTimeout(function () {
            var sub = '';
            if (data.points_awarded > 0) {
              sub = '已自动到账 +' + data.points_awarded + ' 积分';
            } else if (data.prize_type === 'empty') {
              sub = '谢谢参与，再接再厉';
            } else {
              sub = '请留意客服发放';
            }
            showModal(data.prize_name || '恭喜', sub);
            if (chanceEl && typeof data.chances === 'number') {
              chanceEl.innerHTML = '抽奖 <strong>' + data.chances + '</strong> 次';
            }
            if (pointsEl && typeof data.points === 'number') {
              pointsEl.innerHTML = '积分 <strong>' + data.points + '</strong>';
            }
            canDraw = data.chances > 0;
            goBtn.disabled = !canDraw;
            spinning = false;
          }, 4300);
        })
        .catch(function (err) {
          spinning = false;
          goBtn.disabled = !canDraw;
          alert(err.message || '抽奖失败');
        });
    });
  });
})();
