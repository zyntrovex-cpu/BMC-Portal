/* =================================================================
   BMC Public Website — Main JavaScript
   ================================================================= */

/* ── Loader ──────────────────────────────────────────────────── */
window.addEventListener('load', () => {
  setTimeout(() => {
    document.getElementById('site-loader')?.classList.add('loaded');
  }, 800);
});

/* ── AOS ─────────────────────────────────────────────────────── */
AOS.init({ duration: 600, easing: 'ease-out-cubic', once: true, offset: 50 });

/* ── Navbar scroll ───────────────────────────────────────────── */
const nav = document.getElementById('siteNav');
window.addEventListener('scroll', () => {
  const scrolled = window.scrollY > 60;
  nav?.classList.toggle('scrolled', scrolled);
  document.getElementById('backToTop')?.classList.toggle('visible', window.scrollY > 400);
}, { passive: true });

/* ── Back to top ─────────────────────────────────────────────── */
document.getElementById('backToTop')?.addEventListener('click', () => {
  window.scrollTo({ top: 0, behavior: 'smooth' });
});

/* ── Hamburger / Mobile nav ──────────────────────────────────── */
const ham     = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobileMenu');

ham?.addEventListener('click', () => {
  const isOpen = ham.classList.toggle('open');
  mobileMenu?.classList.toggle('open', isOpen);
  document.body.style.overflow = isOpen ? 'hidden' : '';
});

/* Mobile mega/dropdown accordion */
document.querySelectorAll('.mobile-menu .has-mega > a, .mobile-menu .has-dropdown > a').forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    link.closest('li').classList.toggle('open');
  });
});

/* Close mobile nav on inner link click */
document.querySelectorAll('.mobile-menu a:not(.has-mega > a):not(.has-dropdown > a)').forEach(a => {
  a.addEventListener('click', () => {
    ham?.classList.remove('open');
    mobileMenu?.classList.remove('open');
    document.body.style.overflow = '';
  });
});

/* ── Search overlay ──────────────────────────────────────────── */
const overlay   = document.getElementById('searchOverlay');
const searchBtn = document.getElementById('searchToggle');
const closeBtn  = document.getElementById('searchClose');
const searchIn  = document.getElementById('searchInput');

const openSearch = () => {
  overlay?.classList.add('active');
  setTimeout(() => searchIn?.focus(), 80);
};
const closeSearch = () => overlay?.classList.remove('active');

searchBtn?.addEventListener('click', e => { e.preventDefault(); openSearch(); });
closeBtn?.addEventListener('click', closeSearch);
overlay?.addEventListener('click', e => { if (e.target === overlay) closeSearch(); });
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeSearch();
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); overlay?.classList.toggle('active'); setTimeout(() => searchIn?.focus(), 80); }
});

/* ── Dark / Light mode ───────────────────────────────────────── */
const themeToggle = document.getElementById('themeToggle');
const themeIcon   = document.getElementById('themeIcon');
const root        = document.documentElement;

const applyTheme = theme => {
  root.dataset.theme = theme;
  if (themeIcon) themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
  localStorage.setItem('bmc-theme', theme);
};

const savedTheme = localStorage.getItem('bmc-theme') ||
  (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
applyTheme(savedTheme);

themeToggle?.addEventListener('click', () => {
  applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
});

/* ── Hero Swiper ─────────────────────────────────────────────── */
if (document.querySelector('.hero-swiper')) {
  new Swiper('.hero-swiper', {
    loop: true,
    speed: 800,
    autoplay: { delay: 5000, disableOnInteraction: false },
    effect: 'fade',
    fadeEffect: { crossFade: true },
    pagination: { el: '.swiper-pagination', clickable: true },
    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
  });
}

/* ── Testimonials Swiper ─────────────────────────────────────── */
if (document.querySelector('.testimonials-swiper')) {
  new Swiper('.testimonials-swiper', {
    loop: true,
    speed: 700,
    autoplay: { delay: 4500, disableOnInteraction: false },
    slidesPerView: 1,
    spaceBetween: 20,
    pagination: { el: '.testimonials-pagination', clickable: true },
    breakpoints: {
      768:  { slidesPerView: 2 },
      1024: { slidesPerView: 3 },
    },
  });
}

/* ── Partners Swiper ─────────────────────────────────────────── */
if (document.querySelector('.partners-swiper')) {
  new Swiper('.partners-swiper', {
    loop: true,
    speed: 800,
    autoplay: { delay: 2000, disableOnInteraction: false },
    slidesPerView: 2,
    spaceBetween: 16,
    breakpoints: {
      480:  { slidesPerView: 3 },
      768:  { slidesPerView: 4 },
      1024: { slidesPerView: 6 },
    },
  });
}

/* ── Animated Counters ───────────────────────────────────────── */
const animateCounter = el => {
  const target   = parseInt(el.dataset.target || el.textContent, 10);
  const duration = 2000;
  const start    = performance.now();
  const update   = now => {
    const progress = Math.min((now - start) / duration, 1);
    const eased    = 1 - Math.pow(1 - progress, 4); // ease-out-quart
    el.textContent = Math.floor(eased * target).toLocaleString();
    if (progress < 1) requestAnimationFrame(update);
    else el.textContent = target.toLocaleString();
  };
  requestAnimationFrame(update);
};

new IntersectionObserver((entries, obs) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      animateCounter(entry.target);
      obs.unobserve(entry.target);
    }
  });
}, { threshold: 0.4 })
.observe
? (() => {
  const obs = new IntersectionObserver((entries, o) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) { animateCounter(entry.target); o.unobserve(entry.target); }
    });
  }, { threshold: 0.4 });
  document.querySelectorAll('.counter-num[data-target]').forEach(el => obs.observe(el));
})()
: null;

/* ── GLightbox ───────────────────────────────────────────────── */
if (typeof GLightbox !== 'undefined') {
  GLightbox({ selector: '.glightbox', touchNavigation: true, loop: true, autoplayVideos: true });
}

/* ── Gallery filter ──────────────────────────────────────────── */
document.querySelectorAll('.gallery-filter-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.gallery-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const filter = btn.dataset.filter;
    document.querySelectorAll('.gallery-item[data-album]').forEach(item => {
      item.style.display = (filter === 'all' || item.dataset.album === filter) ? '' : 'none';
    });
  });
});

/* ── Notice board live search ────────────────────────────────── */
document.getElementById('noticeSearch')?.addEventListener('input', function () {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.notice-row').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

/* ── Admin image preview ─────────────────────────────────────── */
document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
  input.addEventListener('change', () => {
    const preview = document.getElementById(input.dataset.preview);
    const file    = input.files[0];
    if (file && preview) {
      const reader = new FileReader();
      reader.onload = e => { preview.src = e.target.result; };
      reader.readAsDataURL(file);
    }
  });
});

/* ── Alert auto-dismiss ──────────────────────────────────────── */
setTimeout(() => {
  document.querySelectorAll('.auto-dismiss').forEach(el => {
    el.style.transition = 'opacity 0.4s';
    el.style.opacity    = '0';
    setTimeout(() => el.remove(), 400);
  });
}, 4000);

/* ── FAQ Accordion ───────────────────────────────────────────── */
document.querySelectorAll('.faq-question').forEach(q => {
  q.addEventListener('click', () => {
    const item   = q.closest('.faq-item');
    const isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
    if (!isOpen) item.classList.add('open');
  });
});

/* ── Bootstrap tab sync with URL ─────────────────────────────── */
document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
  tab.addEventListener('shown.bs.tab', e => {
    const target = e.target.getAttribute('data-bs-target') || e.target.getAttribute('href');
    if (target) history.replaceState(null, '', target.replace('#', '?tab=').replace('tab-', ''));
  });
});
const urlTab = new URLSearchParams(location.search).get('tab');
if (urlTab) {
  const targetTab = document.querySelector(`[data-tab-id="${urlTab}"]`);
  if (targetTab) new bootstrap.Tab(targetTab).show();
}

/* ── Smooth anchor scroll ────────────────────────────────────── */
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (target) {
      e.preventDefault();
      window.scrollTo({ top: target.offsetTop - 120, behavior: 'smooth' });
    }
  });
});
