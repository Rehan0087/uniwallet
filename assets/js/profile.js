/**
 * Profile page niceties: ask for the current password only when the email
 * changes, show the warning percentage while dragging, and reveal the
 * delete-account form on demand.
 */
(function () {
  'use strict';

  var email = document.getElementById('email');
  var confirmBox = document.getElementById('email-confirm');
  if (email && confirmBox) {
    var original = email.value.trim().toLowerCase();
    email.addEventListener('input', function () {
      confirmBox.hidden = email.value.trim().toLowerCase() === original;
    });
  }

  var range = document.getElementById('warn_pct');
  var out = document.getElementById('warn-out');
  if (range && out) {
    var okBar = document.getElementById('ap-ok');
    var warnBar = document.getElementById('ap-warn');
    var overBar = document.getElementById('ap-over');
    var paint = function () {
      out.textContent = range.value + '%';
      range.style.setProperty('--fill', ((range.value - range.min) / (range.max - range.min) * 100) + '%');
      // Preview strip: green up to the threshold, amber until 100%, then red.
      if (okBar) {
        var w = Number(range.value);
        okBar.style.flexBasis = w + '%';
        warnBar.style.flexBasis = (100 - w) + '%';
        overBar.style.flexBasis = '14%';
      }
    };
    range.addEventListener('input', paint);
    paint();
  }

  var toggle = document.getElementById('danger-toggle');
  var form = document.getElementById('danger-form');
  if (toggle && form) {
    toggle.addEventListener('click', function () {
      form.hidden = !form.hidden;
      toggle.setAttribute('aria-expanded', String(!form.hidden));
      toggle.textContent = form.hidden ? 'Delete my account…' : 'Cancel';
      if (!form.hidden) { document.getElementById('confirm_text').focus(); }
    });
    if (location.hash === '#danger' && document.querySelector('.flash-error')) {
      toggle.click();   // come back to the open form after a failed attempt
    }
  }

  // ---- theme: apply instantly, save in the background
  var themeForm = document.getElementById('theme-form');
  var state = document.getElementById('theme-state');
  if (themeForm && window.UWTheme) {
    themeForm.addEventListener('change', function (e) {
      if (e.target.name !== 'theme') { return; }
      window.UWTheme.apply(e.target.value);
      window.UWTheme.save(e.target.value);
      if (state) {
        state.textContent = 'Saved';
        clearTimeout(state._t);
        state._t = setTimeout(function () { state.textContent = ''; }, 1800);
      }
    });
  }

  // ---- section nav: highlight the section being read
  var links = Array.prototype.slice.call(document.querySelectorAll('.settings-nav a'));
  if (links.length && 'IntersectionObserver' in window) {
    var byId = {};
    links.forEach(function (a) { byId[a.getAttribute('href').slice(1)] = a; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          links.forEach(function (a) { a.classList.remove('is-active'); });
          byId[en.target.id].classList.add('is-active');
        }
      });
    }, { rootMargin: '-20% 0px -65% 0px' });
    Object.keys(byId).forEach(function (id) { var el = document.getElementById(id); if (el) { io.observe(el); } });
  }
})();
