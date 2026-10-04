/* ==========================================================================
   UniWallet — client-side behaviour.

   Deliberately small: every form is validated again in PHP, so nothing here
   is load-bearing for correctness. It just saves a round trip and makes the
   destructive buttons ask first.
   ========================================================================== */

(function () {
  'use strict';

  // ---------------------------------------------------------- validation

  var RULES = {
    required: function (value) {
      return value.trim() !== '' || 'This field is required.';
    },
    amount: function (value) {
      if (value.trim() === '') return true; // `required` covers empties
      var n = parseFloat(value.replace(/,/g, ''));
      if (isNaN(n)) return 'Enter a number.';
      if (n <= 0) return 'Enter an amount greater than zero.';
      return true;
    },
    email: function (value) {
      if (value.trim() === '') return true;
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim()) || 'Enter a valid email address.';
    },
    minlength: function (value, arg) {
      var min = parseInt(arg, 10);
      if (value === '') return true;
      return value.length >= min || 'Use at least ' + min + ' characters.';
    },
    matches: function (value, arg, form) {
      var other = form.querySelector('[name="' + arg + '"]');
      if (!other) return true;
      return value === other.value || 'The two entries do not match.';
    }
  };

  function showError(input, message) {
    clearError(input);
    input.classList.add('is-invalid');
    var note = document.createElement('div');
    note.className = 'field-error';
    note.dataset.generated = 'true';
    note.textContent = message;
    anchorOf(input).insertAdjacentElement('afterend', note);
  }

  // Password fields are wrapped (for the show/hide button); errors go after the wrapper.
  function anchorOf(input) {
    var p = input.parentElement;
    return p && p.classList.contains('input-wrap') ? p : input;
  }

  function clearError(input) {
    input.classList.remove('is-invalid');
    var next = anchorOf(input).nextElementSibling;
    if (next && next.dataset && next.dataset.generated === 'true') {
      next.remove();
    }
  }

  // Reads rules off `data-validate="required amount"`, args off
  // `data-validate-minlength="8"`.
  function validateInput(input, form) {
    var spec = input.getAttribute('data-validate');
    if (!spec) return true;

    var names = spec.split(/\s+/).filter(Boolean);
    for (var i = 0; i < names.length; i++) {
      var rule = RULES[names[i]];
      if (!rule) continue;
      var arg = input.getAttribute('data-validate-' + names[i]);
      var result = rule(input.value, arg, form);
      if (result !== true) {
        showError(input, result);
        return false;
      }
    }
    clearError(input);
    return true;
  }

  document.querySelectorAll('form[data-validated]').forEach(function (form) {
    var inputs = form.querySelectorAll('[data-validate]');

    inputs.forEach(function (input) {
      // Re-check on blur, but only clear errors while typing so the user
      // isn't scolded mid-word.
      input.addEventListener('blur', function () { validateInput(input, form); });
      input.addEventListener('input', function () {
        if (input.classList.contains('is-invalid')) clearError(input);
      });
    });

    form.addEventListener('submit', function (event) {
      var firstBad = null;
      inputs.forEach(function (input) {
        if (!validateInput(input, form) && !firstBad) firstBad = input;
      });
      if (firstBad) {
        event.preventDefault();
        firstBad.focus();
      }
    });
  });

  // ----------------------------------------- password show/hide + strength

  document.querySelectorAll('input[data-toggle-password]').forEach(function (input) {
    var wrap = document.createElement('div');
    wrap.className = 'input-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle';
    btn.textContent = 'Show';
    btn.setAttribute('aria-label', 'Show password');
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
    wrap.appendChild(btn);

    if (input.hasAttribute('data-strength')) {
      var meter = document.createElement('div');
      meter.className = 'strength';
      meter.innerHTML = '<span class="strength-bars"><i></i><i></i><i></i><i></i></span>' +
        '<span class="strength-text">Use at least 8 characters.</span>';
      wrap.insertAdjacentElement('afterend', meter);

      // Guidance only — the server enforces the real rule (8+ characters).
      input.addEventListener('input', function () {
        var v = input.value, score = 0;
        if (v.length >= 8) score++;
        if (v.length >= 12) score++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
        if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) score++;
        if (v.length > 0 && v.length < 8) score = 0;
        var labels = ['Too short', 'Weak', 'Okay', 'Good', 'Strong'];
        var level = v.length === 0 ? -1 : score;
        meter.setAttribute('data-level', String(Math.max(level, 0)));
        meter.querySelector('.strength-text').textContent =
          v.length === 0 ? 'Use at least 8 characters.' : labels[score];
        meter.classList.toggle('is-active', v.length > 0);
      });
    }
  });

  // ------------------------------------------------------ quick deposit chips

  document.querySelectorAll('[data-quick-amount]').forEach(function (chip) {
    chip.addEventListener('click', function () {
      var form = chip.closest('form');
      var input = form && form.querySelector('input[name="amount"]');
      if (!input) return;
      input.value = chip.getAttribute('data-quick-amount');
      input.focus();
    });
  });

  // ------------------------------------------------------- emoji picker

  document.querySelectorAll('[data-emoji]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById('icon');
      if (input) { input.value = btn.getAttribute('data-emoji'); input.focus(); }
    });
  });

  // ------------------------------------------------------ confirm deletes

  document.addEventListener('submit', function (event) {
    var form = event.target;
    var message = form.getAttribute('data-confirm') ||
      (event.submitter && event.submitter.getAttribute('data-confirm'));
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  // ------------------------------------------------- auto-submit filters

  // Month pickers and filter dropdowns marked this way submit on change, so
  // there is no extra "Go" button to click.
  document.querySelectorAll('[data-autosubmit]').forEach(function (control) {
    control.addEventListener('change', function () {
      if (control.form) control.form.submit();
    });
  });

  // --------------------------------------------------------- niceties

  // Focus the first empty text field on forms that ask for it.
  var focusTarget = document.querySelector('[data-autofocus]');
  if (focusTarget) focusTarget.focus();

}());
