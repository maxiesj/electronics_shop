(function () {
  'use strict';
  document.querySelectorAll('[data-password-form]').forEach(function (form) {
    form.addEventListener('submit', function () {
      const button = form.querySelector('[data-password-submit]');
      if (!button || button.disabled) return;
      button.disabled = true;
      button.classList.add('is-loading');
      button.setAttribute('aria-busy', 'true');
      const label = button.querySelector('.password-button-label');
      if (label) label.textContent = 'Updating Password...';
    });
  });
})();