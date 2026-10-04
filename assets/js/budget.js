/**
 * Budget page helpers: live "allocated" meter and the suggested split.
 * Both only fill in form fields; nothing is saved until "Save budget".
 */
(function () {
  'use strict';

  var form = document.getElementById('budget-form');
  if (!form) { return; }

  var total  = document.getElementById('total_limit');
  var fields = Array.prototype.slice.call(form.querySelectorAll('input[data-cat]'));
  var text   = document.getElementById('alloc-text');
  var bar    = document.getElementById('alloc-bar');
  var cur    = document.getElementById('alloc').getAttribute('data-currency');

  function taka(n) { return cur + Math.round(n).toLocaleString('en-IN'); }
  function num(el) { var v = parseFloat(el.value); return isNaN(v) ? 0 : v; }

  function update() {
    var allocated = fields.reduce(function (sum, f) { return sum + num(f); }, 0);
    var cap = num(total);

    bar.classList.remove('is-warn', 'is-over', 'is-ok');
    if (cap > 0) {
      var pct = allocated / cap * 100;
      bar.style.width = Math.min(100, pct) + '%';
      if (allocated > cap) {
        bar.classList.add('is-over');
        text.textContent = taka(allocated) + ' set aside for categories, ' + taka(allocated - cap) + ' more than the whole-month limit.';
      } else {
        bar.classList.add('is-ok');
        text.textContent = taka(allocated) + ' of ' + taka(cap) + ' set aside for categories. ' + taka(cap - allocated) + ' unplanned.';
      }
    } else {
      bar.style.width = '0';
      text.textContent = allocated > 0
        ? taka(allocated) + ' set aside for categories. Add a whole-month limit to compare.'
        : 'Category limits are optional.';
    }
  }

  // Starting shares for a student living near campus (same split as the landing page).
  var SHARES = {
    'rent & mess': 40, 'food & cafeteria': 28, 'transport': 14,
    'hangout & fun': 8, 'books & printing': 6, 'mobile & internet': 4
  };

  document.getElementById('suggest-split').addEventListener('click', function () {
    var cap = num(total);
    if (cap <= 0) {
      total.focus();
      text.textContent = 'Enter a whole-month limit first, then suggest a split.';
      return;
    }
    var cats = JSON.parse(form.getAttribute('data-categories') || '[]');
    cats.forEach(function (c) {
      var share = SHARES[c.name.toLowerCase()];
      var field = form.querySelector('input[data-cat="' + c.id + '"]');
      // Only fill empty fields so a limit you typed is never overwritten.
      if (share && field && field.value === '') {
        field.value = Math.round(cap * share / 100 / 10) * 10;
      }
    });
    update();
  });

  form.addEventListener('input', update);
  update();
})();
