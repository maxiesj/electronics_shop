(function () {
    'use strict';

    var root = document.documentElement;
    var progressTimer = null;
    var progressValue = 12;
    var dialog = null;

    root.setAttribute('data-page-progress-loading', 'true');

    var earlyStyle = document.createElement('style');
    earlyStyle.textContent = '' +
        'html[data-page-progress-loading]::before{content:"Loading page…";position:fixed;inset:0;z-index:2147483647;display:grid;place-items:center;background:transparent;color:#0f172a;font:700 14px/1.4 system-ui,-apple-system,Segoe UI,sans-serif;letter-spacing:.02em}' +
        'html[data-page-progress-loading]::after{content:"";position:fixed;z-index:2147483647;top:calc(50% + 24px);left:calc(50% - 120px);width:240px;height:5px;border-radius:999px;background:linear-gradient(90deg,#f97316 0 42%,#fdba74 42% 62%,#f97316 62% 100%);background-size:200% 100%;animation:adonakEarlyProgress 1.1s linear infinite}' +
        '@keyframes adonakEarlyProgress{to{background-position:200% 0}}';
    (document.head || root).appendChild(earlyStyle);

    function mount() {
        if (dialog) return;
        dialog = document.createElement('div');
        dialog.id = 'page-progress-dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-live', 'polite');
        dialog.innerHTML = '<div class="page-progress-dialog__card"><div class="page-progress-dialog__title">Loading page</div><div class="page-progress-dialog__message">Please wait while we prepare your workspace.</div><div class="page-progress-dialog__track" role="progressbar" aria-label="Page loading progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="12"><span></span></div><div class="page-progress-dialog__percent">12%</div></div>';
        document.body.appendChild(dialog);

        var style = document.createElement('style');
        style.textContent = '#page-progress-dialog{position:fixed;inset:0;z-index:2147483647;display:flex;align-items:center;justify-content:center;padding:20px;background:transparent;opacity:0;visibility:hidden;transition:opacity .18s ease,visibility .18s ease}#page-progress-dialog.is-visible{opacity:1;visibility:visible}.page-progress-dialog__card{width:min(390px,100%);padding:25px;border:1px solid rgba(255,255,255,.16);border-radius:16px;background:#fff;box-shadow:0 25px 70px rgba(2,6,23,.36);text-align:center}.page-progress-dialog__title{color:#0f172a;font:800 19px/1.2 system-ui,-apple-system,Segoe UI,sans-serif}.page-progress-dialog__message{margin:8px 0 18px;color:#64748b;font:500 13px/1.5 system-ui,-apple-system,Segoe UI,sans-serif}.page-progress-dialog__track{height:8px;overflow:hidden;border-radius:999px;background:#e2e8f0}.page-progress-dialog__track span{display:block;width:12%;height:100%;border-radius:inherit;background:linear-gradient(90deg,#ea580c,#f97316);transition:width .35s ease}.page-progress-dialog__percent{margin-top:9px;color:#ea580c;font:800 12px/1 system-ui,-apple-system,Segoe UI,sans-serif}';
        document.head.appendChild(style);
    }

    function update(value) {
        progressValue = Math.max(0, Math.min(100, value));
        if (!dialog) return;
        var bar = dialog.querySelector('[role="progressbar"]');
        bar.setAttribute('aria-valuenow', progressValue);
        bar.firstElementChild.style.width = progressValue + '%';
        dialog.querySelector('.page-progress-dialog__percent').textContent = progressValue + '%';
    }

    function show(message) {
        mount();
        root.setAttribute('data-page-progress-loading', 'true');
        dialog.classList.add('is-visible');
        if (message) dialog.querySelector('.page-progress-dialog__message').textContent = message;
        update(12);
        clearInterval(progressTimer);
        progressTimer = setInterval(function () {
            if (progressValue < 88) update(progressValue + (progressValue < 55 ? 9 : 3));
        }, 260);
    }

    function hide() {
        clearInterval(progressTimer);
        update(100);
        root.removeAttribute('data-page-progress-loading');
        if (dialog) {
            setTimeout(function () { dialog.classList.remove('is-visible'); }, 100);
        }
    }

    window.ADONAKPageProgress = { show: show, hide: hide };

    document.addEventListener('DOMContentLoaded', function () {
        mount();
        show();
    });
    window.addEventListener('load', hide);
    window.addEventListener('pageshow', hide);

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');
        if (!link || link.classList.contains('ajax-link') || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || /^javascript:/i.test(href)) return;
        var destination;
        try { destination = new URL(link.href, window.location.href); } catch (error) { return; }
        if (destination.origin !== window.location.origin || (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash)) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        show('Please wait while we open the next page.');
        setTimeout(function () { window.location.assign(destination.href); }, 120);
    }, true);
}());
