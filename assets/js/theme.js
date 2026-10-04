/**
 * Theme switching. The first paint is handled by the inline script in <head>
 * (theme_boot_script); this file wires up the toggle buttons and keeps the
 * choice in sync: localStorage for this browser, and — when signed in — the
 * account, so the choice follows you between devices.
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var media = window.matchMedia('(prefers-color-scheme: dark)');

  function resolve(choice) {
    return choice === 'dark' || (choice === 'system' && media.matches) ? 'dark' : 'light';
  }

  function apply(choice) {
    root.setAttribute('data-theme', choice);
    root.setAttribute('data-resolved', resolve(choice));
    try { localStorage.setItem('uw-theme', choice); } catch (e) { /* private mode */ }
    document.dispatchEvent(new CustomEvent('uw-theme', { detail: { choice: choice, resolved: resolve(choice) } }));
  }

  function saveToAccount(choice) {
    var meta = document.querySelector('meta[name="csrf"]');
    if (!meta) { return; }                       // signed out: browser-only
    var body = new URLSearchParams({ action: 'theme', theme: choice, csrf: meta.content });
    fetch('profile.php', { method: 'POST', body: body, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
      .catch(function () { /* the choice still applies on this page */ });
  }

  window.UWTheme = { apply: apply, save: saveToAccount, resolve: resolve };

  // Quick toggle: flips between light and dark.
  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var next = root.getAttribute('data-resolved') === 'dark' ? 'light' : 'dark';
      apply(next);
      saveToAccount(next);
    });
  });

  // Follow the operating system while the choice is "system".
  media.addEventListener('change', function () {
    if ((root.getAttribute('data-theme') || 'system') === 'system') { apply('system'); }
  });
})();
