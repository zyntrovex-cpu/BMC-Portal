/**
 * BMC Portal — AJAX form handling + toast notifications
 *
 * How it works:
 *   Every POST form is intercepted automatically.
 *   PHP's redirect() returns JSON when X-Requested-With is set.
 *   JSON contains {ok, url, flash:{type,msg}}.
 *   On success, we show a toast and either soft-swap the page content
 *   (same pathname) or do a hard navigate (different page).
 *   Forms with data-no-ajax or on .no-ajax-form bypass this entirely.
 */
(function () {
  'use strict';

  /* ── Toast styles ─────────────────────────────────────────────────────────── */
  (function () {
    var s = document.createElement('style');
    s.textContent = [
      '#portal-toast-container{',
        'position:fixed;top:16px;right:16px;z-index:99990;',
        'display:flex;flex-direction:column;gap:8px;pointer-events:none;',
        'max-width:400px;width:calc(100% - 32px)',
      '}',
      '.portal-toast{',
        'pointer-events:all;background:#fff;border-radius:10px;',
        'box-shadow:0 4px 18px rgba(0,0,0,.18);',
        'padding:12px 14px;font-size:.875rem;',
        'display:flex;align-items:flex-start;gap:10px;',
        'animation:_pt-in .28s cubic-bezier(.4,0,.2,1) both;',
        'transition:opacity .25s,transform .25s',
      '}',
      '.portal-toast-icon{font-size:1rem;flex-shrink:0;margin-top:1px}',
      '.portal-toast-body{flex:1;line-height:1.45;word-break:break-word}',
      '.portal-toast-close{',
        'background:none;border:none;cursor:pointer;',
        'padding:0 0 0 8px;color:#9ca3af;font-size:1.2rem;line-height:1;',
        'flex-shrink:0;align-self:flex-start',
      '}',
      '.portal-toast-close:hover{color:#374151}',
      '@keyframes _pt-in{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}',
      /* Loading overlay */
      '.page-content.portal-loading{position:relative;min-height:120px;opacity:.55;pointer-events:none;transition:opacity .15s}',
      '.page-content.portal-loading::after{',
        'content:"";position:absolute;top:50%;left:50%;',
        'transform:translate(-50%,-50%);',
        'width:36px;height:36px;',
        'border:3px solid #e5e7eb;border-top-color:var(--accent,#1c3054);',
        'border-radius:50%;animation:_pt-spin .7s linear infinite',
      '}',
      '@keyframes _pt-spin{to{transform:translate(-50%,-50%) rotate(360deg)}}',
    ].join('');
    document.head.appendChild(s);
  }());

  /* ── Toast colours ────────────────────────────────────────────────────────── */
  var TOAST = {
    success : { color: '#198754', icon: 'fa-check-circle'         },
    danger  : { color: '#dc3545', icon: 'fa-times-circle'         },
    error   : { color: '#dc3545', icon: 'fa-times-circle'         },
    warning : { color: '#d97706', icon: 'fa-exclamation-triangle' },
    info    : { color: '#0284c7', icon: 'fa-info-circle'          },
  };

  /* ── Public: showPortalToast(type, message [, ms]) ────────────────────────── */
  window.showPortalToast = function (type, msg, ms) {
    var c = document.getElementById('portal-toast-container');
    if (!c || !msg) return;
    ms = ms || 5000;
    var cfg   = TOAST[type] || TOAST.info;
    var wrap  = document.createElement('div');
    wrap.className = 'portal-toast';
    wrap.style.borderLeft = '4px solid ' + cfg.color;
    wrap.innerHTML =
        '<i class="fas ' + cfg.icon + ' portal-toast-icon" style="color:' + cfg.color + '"></i>'
      + '<span class="portal-toast-body">'
      + msg.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      + '</span>'
      + '<button class="portal-toast-close" aria-label="Dismiss"'
      + ' onclick="this.closest(\'.portal-toast\').remove()">&#x2715;</button>';
    c.appendChild(wrap);

    var timer = setTimeout(function () {
      wrap.style.opacity  = '0';
      wrap.style.transform = 'translateX(110%)';
      setTimeout(function () { if (wrap.parentNode) wrap.remove(); }, 260);
    }, ms);

    wrap.addEventListener('mouseenter', function () { clearTimeout(timer); });
    wrap.addEventListener('mouseleave', function () {
      timer = setTimeout(function () {
        wrap.style.opacity  = '0';
        wrap.style.transform = 'translateX(110%)';
        setTimeout(function () { if (wrap.parentNode) wrap.remove(); }, 260);
      }, 2000);
    });
  };

  /* ── Consume data-portal-flash elements → toast ───────────────────────────── */
  function flushFlash() {
    document.querySelectorAll('[data-portal-flash]').forEach(function (el) {
      window.showPortalToast(el.dataset.portalFlash, el.dataset.portalMsg);
      el.remove();
    });
  }
  document.addEventListener('DOMContentLoaded', flushFlash);

  /* ── Loading overlay ──────────────────────────────────────────────────────── */
  function setLoading(on) {
    var c = document.querySelector('.page-content');
    if (c) c.classList.toggle('portal-loading', on);
  }

  /* ── Submit-button spinner ────────────────────────────────────────────────── */
  function spinBtn(btn, on) {
    if (!btn) return;
    if (on) {
      btn._ph = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML =
        '<span style="display:inline-block;width:.8em;height:.8em;vertical-align:-.1em;'
        + 'border:2px solid rgba(255,255,255,.5);border-top-color:#fff;'
        + 'border-radius:50%;animation:_pt-spin .7s linear infinite;margin-right:5px"></span>'
        + btn.innerHTML;
    } else {
      btn.disabled = false;
      if (btn._ph !== undefined) { btn.innerHTML = btn._ph; delete btn._ph; }
    }
  }

  /* ── Content swap ─────────────────────────────────────────────────────────── */
  function swapContent(url) {
    var scrollY = document.documentElement.scrollTop || document.body.scrollTop || 0;
    setLoading(true);

    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc     = (new DOMParser()).parseFromString(html, 'text/html');
        var fresh   = doc.querySelector('.page-content');
        var current = document.querySelector('.page-content');

        if (fresh && current) {
          current.innerHTML = fresh.innerHTML;

          /* Re-run inline body scripts (page-specific event handlers) */
          doc.querySelectorAll('body script:not([src])').forEach(function (os) {
            try {
              var ns = document.createElement('script');
              ns.textContent = os.textContent;
              document.body.appendChild(ns);
              document.body.removeChild(ns);
            } catch (ignore) {}
          });

          flushFlash();
        }

        history.replaceState({}, doc.title || document.title, url);
        if (doc.title) document.title = doc.title;
        setLoading(false);

        /* Restore scroll */
        window.scrollTo(0, scrollY);
      })
      .catch(function () { setLoading(false); });
  }

  /* ── AJAX form interceptor ────────────────────────────────────────────────── */
  document.addEventListener('submit', function (e) {
    var form = e.target;

    /* Only intercept POST forms */
    if (!form || form.tagName !== 'FORM') return;
    if ((form.method || '').toUpperCase() !== 'POST') return;

    /* Opt-out: data-no-ajax on form or any ancestor */
    if (form.dataset.noAjax !== undefined) return;
    if (form.closest('[data-no-ajax]'))    return;

    /* Must be same-origin action */
    var actionHref = form.action || location.href;
    try {
      var aUrl = new URL(actionHref, location.href);
      if (aUrl.origin !== location.origin) return;
    } catch (_) { return; }

    e.preventDefault();

    var btn = form.querySelector('[type=submit]');
    spinBtn(btn, true);

    var fd = new FormData(form);

    fetch(actionHref, {
      method      : 'POST',
      body        : fd,
      credentials : 'same-origin',
      headers     : { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (r) {
        /* If PHP returned HTML (uncaught exception, fatal error etc.) show error toast */
        var ct = r.headers.get('Content-Type') || '';
        if (!ct.includes('application/json')) {
          spinBtn(btn, false);
          window.showPortalToast('danger', 'An unexpected server error occurred. Please try again.');
          return Promise.reject('fallback');
        }
        return r.json();
      })
      .then(function (data) {
        spinBtn(btn, false);

        /* Flash message → toast */
        var f = data.flash;
        if (f && f.msg) {
          window.showPortalToast(f.type || 'info', f.msg);
        } else if (data.ok === false || data.success === false) {
          window.showPortalToast('danger', data.error || data.message || 'An error occurred.');
        }

        /* On danger/error flash: keep form as-is so user can fix and resubmit */
        var isErrorFlash = f && (f.type === 'danger' || f.type === 'error');
        if (isErrorFlash || data.ok === false || data.success === false) return;

        var targetUrl = data.url || data.redirect || location.href;
        var tUrl = new URL(targetUrl, location.href);
        var cUrl = new URL(location.href);

        /* Redirect to login (session expired) → hard navigate */
        if (tUrl.pathname.replace(/\/$/, '').match(/\/index\.php$/)) {
          window.location.href = targetUrl;
          return;
        }

        /* Different page → hard navigate */
        if (tUrl.pathname !== cUrl.pathname) {
          window.location.href = targetUrl;
          return;
        }

        /* Same page → soft content swap */
        swapContent(targetUrl);
      })
      .catch(function (err) {
        if (err === 'fallback') return;
        spinBtn(btn, false);
        window.showPortalToast('danger', 'Connection error. Please try again.');
      });
  });

}());
