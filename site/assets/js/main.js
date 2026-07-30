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
AOS.init({ duration: 700, easing: 'ease-out-cubic', once: true, offset: 60 });

/* ── Navbar scroll behaviour ─────────────────────────────────── */
const nav = document.getElementById('siteNav');
window.addEventListener('scroll', () => {
  if (window.scrollY > 60) nav?.classList.add('scrolled');
  else nav?.classList.remove('scrolled');
  // back-to-top
  const btn = document.getElementById('backToTop');
  if (btn) btn.classList.toggle('visible', window.scrollY > 400);
}, { passive: true });

/* ── Back to top ─────────────────────────────────────────────── */
document.getElementById('backToTop')?.addEventListener('click', () => {
  window.scrollTo({ top: 0, behavior: 'smooth' });
});

/* ── Mobile hamburger ────────────────────────────────────────── */
const ham = document.getElementById('hamburger');
const navLinks = document.getElementById('navLinks');
ham?.addEventListener('click', () => {
  ham.classList.toggle('open');
  navLinks?.classList.toggle('open');
  document.body.classList.toggle('overflow-hidden');
});

/* Mobile mega/dropdown toggle */
document.querySelectorAll('.has-mega > a, .has-dropdown > a').forEach(link => {
  link.addEventListener('click', e => {
    if (window.innerWidth <= 1100) {
      e.preventDefault();
      link.closest('li').classList.toggle('open');
    }
  });
});

/* Close nav when a non-toggle link is clicked */
document.querySelectorAll('.nav-links a:not(.has-mega > a):not(.has-dropdown > a)').forEach(a => {
  a.addEventListener('click', () => {
    navLinks?.classList.remove('open');
    ham?.classList.remove('open');
    document.body.classList.remove('overflow-hidden');
  });
});

/* ── Search overlay ──────────────────────────────────────────── */
const overlay   = document.getElementById('searchOverlay');
const searchBtn = document.getElementById('searchToggle');
const closeBtn  = document.getElementById('searchClose');
const searchIn  = document.getElementById('searchInput');

searchBtn?.addEventListener('click', e => {
  e.preventDefault();
  overlay?.classList.add('active');
  setTimeout(() => searchIn?.focus(), 100);
});
closeBtn?.addEventListener('click', () => overlay?.classList.remove('active'));
overlay?.addEventListener('click', e => {
  if (e.target === overlay) overlay.classList.remove('active');
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') overlay?.classList.remove('active');
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault();
    overlay?.classList.toggle('active');
    setTimeout(() => searchIn?.focus(), 100);
  }
});

/* ── Dark / Light mode ───────────────────────────────────────── */
const themeToggle = document.getElementById('themeToggle');
const themeIcon   = document.getElementById('themeIcon');
const root        = document.documentElement;

const applyTheme = theme => {
  root.dataset.theme = theme;
  if (themeIcon) {
    themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
  }
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
    speed: 900,
    autoplay: { delay: 5500, disableOnInteraction: false },
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
    spaceBetween: 24,
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
    spaceBetween: 20,
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
  const duration = 2200;
  const start    = performance.now();
  const update   = now => {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 4); // ease-out-quart
    el.textContent = Math.floor(eased * target).toLocaleString();
    if (progress < 1) requestAnimationFrame(update);
    else el.textContent = target.toLocaleString();
  };
  requestAnimationFrame(update);
};

const counterObserver = new IntersectionObserver((entries, obs) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      animateCounter(entry.target);
      obs.unobserve(entry.target);
    }
  });
}, { threshold: 0.4 });

document.querySelectorAll('.counter-num').forEach(el => counterObserver.observe(el));

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
      const show = filter === 'all' || item.dataset.album === filter;
      item.style.display = show ? '' : 'none';
    });
  });
});

/* ── FAQ Accordion ───────────────────────────────────────────── */
document.querySelectorAll('.faq-question').forEach(q => {
  q.addEventListener('click', () => {
    const item  = q.closest('.faq-item');
    const isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
    if (!isOpen) item.classList.add('open');
  });
});

/* ── Tabs (about, academics) ─────────────────────────────────── */
// Sync URL hash with Bootstrap tabs
const tabEls = document.querySelectorAll('[data-bs-toggle="tab"]');
tabEls.forEach(tab => {
  tab.addEventListener('shown.bs.tab', e => {
    const target = e.target.getAttribute('data-bs-target') || e.target.getAttribute('href');
    if (target) history.replaceState(null, '', target.replace('#', '?tab=').replace('tab-', ''));
  });
});

/* Activate tab from URL param */
const urlTab = new URLSearchParams(location.search).get('tab');
if (urlTab) {
  const targetTab = document.querySelector(`[data-tab-id="${urlTab}"]`);
  if (targetTab) {
    const bsTab = new bootstrap.Tab(targetTab);
    bsTab.show();
  }
}

/* ── Smooth scroll for anchor links ──────────────────────────── */
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (target) {
      e.preventDefault();
      const offset = 120;
      window.scrollTo({ top: target.offsetTop - offset, behavior: 'smooth' });
    }
  });
});

/* ── Parallax on scroll ──────────────────────────────────────── */
const parallaxEls = document.querySelectorAll('[data-parallax]');
if (parallaxEls.length) {
  window.addEventListener('scroll', () => {
    parallaxEls.forEach(el => {
      const speed = parseFloat(el.dataset.parallax) || 0.3;
      const rect  = el.getBoundingClientRect();
      const offset = (rect.top + window.scrollY) * speed;
      el.style.transform = `translateY(${-offset * 0.15}px)`;
    });
  }, { passive: true });
}

/* ── Notice board live search ────────────────────────────────── */
const noticeSearch = document.getElementById('noticeSearch');
noticeSearch?.addEventListener('input', () => {
  const q = noticeSearch.value.toLowerCase();
  document.querySelectorAll('.notice-row').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

/* ── Admin image preview ─────────────────────────────────────── */
document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
  input.addEventListener('change', () => {
    const preview = document.getElementById(input.dataset.preview);
    const file = input.files[0];
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
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 400);
  });
}, 4000);
