(function () {
  'use strict';
  const limitMs = 60 * 60 * 1000;
  const warningMs = 60 * 1000;
  const expireUrl = window.ADONAK_SESSION_EXPIRE_URL || 'session_expire.php';
  const keepaliveUrl = window.ADONAK_SESSION_KEEPALIVE_URL || 'session_keepalive.php';
  const activityKey = 'adonak:lastActivity';
  let lastActivity = Date.now();
  let lastMarkedAt = 0;
  let lastPingAt = 0;
  let modal;
  let countdown;

  function ensureModal() {
    if (modal) return;
    modal = document.createElement('div');
    modal.id = 'adonak-idle-warning';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'adonak-idle-title');
    modal.innerHTML =
      '<div class="adonak-idle-card">' +
      '<div class="adonak-idle-icon">&#9203;</div>' +
      '<h2 id="adonak-idle-title">Session Expiring Soon</h2>' +
      '<p>For your security, this account will sign out after 60 minutes without activity.</p>' +
      '<strong id="adonak-idle-countdown">60 seconds remaining</strong>' +
      '<button type="button" id="adonak-stay-signed-in">Stay Signed In</button>' +
      '</div>';
    const style = document.createElement('style');
    style.textContent =
      '#adonak-idle-warning{position:fixed;inset:0;z-index:2147483647;display:none;place-items:center;padding:20px;background:rgba(15,23,42,.72);backdrop-filter:blur(3px)}' +
      '#adonak-idle-warning.is-visible{display:grid}' +
      '.adonak-idle-card{width:min(410px,100%);padding:27px;border-radius:14px;background:#fff;border:1px solid #e2e8f0;box-shadow:0 24px 60px rgba(15,23,42,.28);text-align:center;font-family:ui-sans-serif,system-ui,sans-serif}' +
      '.adonak-idle-icon{width:50px;height:50px;margin:auto;display:grid;place-items:center;border-radius:50%;background:#fff7ed;font-size:24px}' +
      '.adonak-idle-card h2{margin:14px 0 7px;color:#0f172a;font-size:20px}.adonak-idle-card p{margin:0;color:#64748b;font-size:12px;line-height:1.6}' +
      '.adonak-idle-card strong{display:block;margin:13px 0;color:#c2410c;font-size:13px}.adonak-idle-card button{width:100%;min-height:42px;border:0;border-radius:7px;background:#2563eb;color:#fff;font-size:11px;font-weight:900;text-transform:uppercase;cursor:pointer}';
    document.head.appendChild(style);
    document.body.appendChild(modal);
    countdown = modal.querySelector('#adonak-idle-countdown');
    modal.querySelector('#adonak-stay-signed-in').addEventListener('click', markActivity);
  }

  function pingServer(force) {
    const now = Date.now();
    if (!force && now - lastPingAt < 55000) return;
    lastPingAt = now;
    fetch(keepaliveUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'activity=1'
    }).then(function (response) {
      if (response.status === 401) window.location.replace(expireUrl);
    }).catch(function () {
      // A temporary network failure should not interrupt local activity tracking.
    });
  }

  function markActivity() {
    const now = Date.now();
    if (now - lastMarkedAt < 3000) return;
    lastMarkedAt = now;
    lastActivity = now;
    try {
      localStorage.setItem(activityKey, String(now));
    } catch (e) {
      // Local storage can be unavailable in hardened/private browser modes.
    }
    if (modal) modal.classList.remove('is-visible');
    pingServer(false);
  }

  function checkIdle() {
    const remaining = limitMs - (Date.now() - lastActivity);
    if (remaining <= 0) {
      window.location.replace(expireUrl);
      return;
    }
    if (remaining <= warningMs) {
      ensureModal();
      modal.classList.add('is-visible');
      const seconds = Math.max(1, Math.ceil(remaining / 1000));
      countdown.textContent = seconds + (seconds === 1 ? ' second remaining' : ' seconds remaining');
    } else if (modal) {
      modal.classList.remove('is-visible');
    }
  }

  ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (eventName) {
    window.addEventListener(eventName, markActivity, {passive: true});
  });
  window.addEventListener('storage', function (event) {
    if (event.key === activityKey && Number(event.newValue || 0) > lastActivity) {
      lastActivity = Number(event.newValue);
      if (modal) modal.classList.remove('is-visible');
    }
  });
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) markActivity();
  });

  markActivity();
  window.setInterval(checkIdle, 1000);
  window.setInterval(function () {
    if (Date.now() - lastActivity < 65000) pingServer(false);
  }, 60000);
})();