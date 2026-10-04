/**
 * Landing page: the "how far does your allowance go?" calculator.
 * Pure arithmetic on the slider value — nothing is sent anywhere.
 */
(function () {
  'use strict';

  var slider = document.getElementById('allowance');
  if (!slider) { return; }

  // A starting split for a student living near campus (shares add up to 100).
  var SPLIT = [
    { name: 'Rent & Mess',       icon: '🏠', share: 40 },
    { name: 'Food & Cafeteria',  icon: '🍛', share: 28 },
    { name: 'Transport',         icon: '🛺', share: 14 },
    { name: 'Hangout & Fun',     icon: '🎬', share: 8 },
    { name: 'Books & Printing',  icon: '📚', share: 6 },
    { name: 'Mobile & Internet', icon: '📱', share: 4 }
  ];

  var out   = document.getElementById('allowance-out');
  var day   = document.getElementById('calc-day');
  var week  = document.getElementById('calc-week');
  var split = document.getElementById('calc-split');

  // en-IN gives Bangladeshi lakh/crore grouping, e.g. 1,20,000.
  function taka(n) {
    return '৳' + Math.round(n).toLocaleString('en-IN');
  }

  function render() {
    var total = Number(slider.value);
    out.textContent  = taka(total);
    day.textContent  = taka(total / 30);
    week.textContent = taka(total / 30 * 7);

    var html = '';
    SPLIT.forEach(function (row) {
      html += '<div class="progress-row">' +
        '<div class="progress-meta"><span class="name">' + row.icon + ' ' + row.name + '</span>' +
        '<span class="figures">' + taka(total * row.share / 100) + ' · ' + row.share + '%</span></div>' +
        '<div class="progress"><div class="progress-bar" style="width:' + row.share * 2 + '%"></div></div>' +
        '</div>';
    });
    split.innerHTML = html;

    var pct = (total - slider.min) / (slider.max - slider.min) * 100;
    slider.style.setProperty('--fill', pct + '%');
  }

  slider.addEventListener('input', render);
  render();
})();
