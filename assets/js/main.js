/* =================================================================
   BMC Public Website — Main JavaScript (Redesigned)
   ================================================================= */

/* ── Loader ──────────────────────────────────────────────────── */
window.addEventListener('load', () => {
  setTimeout(() => {
    document.getElementById('site-loader')?.classList.add('loaded');
  }, 600);
});

/* ── AOS ─────────────────────────────────────────────────────── */
if (typeof AOS !== 'undefined') {
  AOS.init({ duration: 700, easing: 'ease-out-cubic', once: true, offset: 60 });
}

/* ── Navbar scroll behaviour ─────────────────────────────────── */
const nav = document.getElementById('siteNav');
let lastScroll = 0;
window.addEventListener('scroll', () => {
  const scrollY = window.scrollY;
  const scrolled = scrollY > 60;
  nav?.classList.toggle('scrolled', scrolled);
  document.getElementById('backToTop')?.classList.toggle('visible', scrollY > 400);
  lastScroll = scrollY;
}, { passive: true });

/* ── Back to top ─────────────────────────────────────────────── */
document.getElementById('backToTop')?.addEventListener('click', () => {
  window.scrollTo({ top: 0, behavior: 'smooth' });
});

/* ── Mobile Menu (slide-in drawer) ──────────────────────────── */
const ham          = document.getElementById('hamburger');
const mobileMenu   = document.getElementById('mobileMenu');
const mobileOverlay = document.getElementById('mobileOverlay');
const mobileClose  = document.getElementById('mobileMenuClose');

const openMobileMenu = () => {
  ham?.classList.add('open');
  mobileMenu?.classList.add('open');
  mobileOverlay?.classList.add('open');
  document.body.style.overflow = 'hidden';
};
const closeMobileMenu = () => {
  ham?.classList.remove('open');
  mobileMenu?.classList.remove('open');
  mobileOverlay?.classList.remove('open');
  document.body.style.overflow = '';
};

ham?.addEventListener('click', () => {
  mobileMenu?.classList.contains('open') ? closeMobileMenu() : openMobileMenu();
});
mobileClose?.addEventListener('click', closeMobileMenu);
mobileOverlay?.addEventListener('click', closeMobileMenu);

/* Mobile accordion sub-menus */
document.querySelectorAll('.mobile-nav-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const li = btn.closest('.mobile-has-sub');
    const isOpen = li.classList.contains('open');
    document.querySelectorAll('.mobile-has-sub.open').forEach(other => {
      if (other !== li) other.classList.remove('open');
    });
    li.classList.toggle('open', !isOpen);
  });
});

/* Close mobile menu on any link click */
document.querySelectorAll('.mobile-menu a').forEach(a => {
  a.addEventListener('click', closeMobileMenu);
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
  if (e.key === 'Escape') { closeSearch(); closeMobileMenu(); }
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

/* ── Hero Swiper (legacy — for pages that still use it) ──────── */
if (document.querySelector('.hero-swiper')) {
  new Swiper('.hero-swiper', {
    loop: true, speed: 900,
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
    loop: true, speed: 700,
    autoplay: { delay: 5000, disableOnInteraction: false },
    slidesPerView: 1, spaceBetween: 24,
    pagination: { el: '.testimonials-pagination', clickable: true },
    breakpoints: { 768: { slidesPerView: 2 }, 1024: { slidesPerView: 3 } },
  });
}

/* ── Partners Swiper ─────────────────────────────────────────── */
if (document.querySelector('.partners-swiper')) {
  new Swiper('.partners-swiper', {
    loop: true, speed: 800,
    autoplay: { delay: 2200, disableOnInteraction: false },
    slidesPerView: 2, spaceBetween: 16,
    breakpoints: { 480: { slidesPerView: 3 }, 768: { slidesPerView: 4 }, 1024: { slidesPerView: 6 } },
  });
}

/* ── Animated Counters ───────────────────────────────────────── */
const animateCounter = el => {
  const target   = parseInt(el.dataset.target || el.textContent, 10);
  if (isNaN(target)) return;
  const duration = 2200;
  const start    = performance.now();
  const update   = now => {
    const progress = Math.min((now - start) / duration, 1);
    const eased    = 1 - Math.pow(1 - progress, 4);
    el.textContent = Math.floor(eased * target).toLocaleString();
    if (progress < 1) requestAnimationFrame(update);
    else el.textContent = target.toLocaleString();
  };
  requestAnimationFrame(update);
};

const counterObs = new IntersectionObserver((entries, obs) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      animateCounter(entry.target);
      obs.unobserve(entry.target);
    }
  });
}, { threshold: 0.4 });
document.querySelectorAll('.counter-num[data-target]').forEach(el => counterObs.observe(el));

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
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 400);
  });
}, 4000);

/* ── FAQ Accordion ───────────────────────────────────────────── */
document.querySelectorAll('.faq-question').forEach(q => {
  q.addEventListener('click', () => {
    const item = q.closest('.faq-item');
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
      const offset = document.querySelector('.site-nav')?.offsetHeight || 80;
      window.scrollTo({ top: target.offsetTop - offset, behavior: 'smooth' });
    }
  });
});

/* ── 3D Card Tilt (Why BMC cards) ────────────────────────────── */
if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
  document.querySelectorAll('.why-card, .dept-card').forEach(card => {
    card.addEventListener('mousemove', e => {
      const rect = card.getBoundingClientRect();
      const x = ((e.clientX - rect.left) / rect.width - 0.5) * 10;
      const y = ((e.clientY - rect.top) / rect.height - 0.5) * -10;
      card.style.transform = `translateY(-6px) rotateX(${y}deg) rotateY(${x}deg)`;
    });
    card.addEventListener('mouseleave', () => {
      card.style.transform = '';
    });
  });
}

/* ── Parallax hero ───────────────────────────────────────────── */
const heroSection = document.querySelector('.hero-new');
if (heroSection && window.matchMedia('(hover: hover)').matches) {
  window.addEventListener('scroll', () => {
    const scrollY = window.scrollY;
    if (scrollY < window.innerHeight) {
      heroSection.style.backgroundPositionY = `calc(60% + ${scrollY * 0.3}px)`;
    }
  }, { passive: true });
}

/* ── News filter tabs ────────────────────────────────────────── */
document.querySelectorAll('[data-filter-btn]').forEach(btn => {
  btn.addEventListener('click', () => {
    const filter = btn.dataset.filterBtn;
    document.querySelectorAll('[data-filter-btn]').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('[data-filter-item]').forEach(item => {
      const match = filter === 'all' || item.dataset.filterItem === filter;
      item.style.display = match ? '' : 'none';
      if (match) item.classList.add('fade-in');
    });
  });
});
