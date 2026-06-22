/*!
 * Modern UI v2 — JS behaviors, drop-in compatible with Bootstrap 5 data-API.
 * Same data-bs-toggle / data-bs-target / data-bs-dismiss attributes,
 * same component method names (show/hide/toggle). Exposed as both
 * window.bootstrap and window.ModernUI so existing bootstrap.Modal(...)
 * style code keeps working untouched.
 *
 * No dependencies. Pure vanilla JS.
 */
(function (global) {
  'use strict';

  /* ---------- tiny helpers ---------- */
  const qs = (sel, ctx) => (ctx || document).querySelector(sel);
  const qsa = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));
  const instances = new WeakMap();
  function getOrCreate(el, Cls, opts) {
    if (!instances.has(el)) instances.set(el, new Map());
    const map = instances.get(el);
    if (!map.has(Cls)) map.set(Cls, new Cls(el, opts));
    return map.get(Cls);
  }
  function dispatch(el, name, detail) {
    el.dispatchEvent(new CustomEvent(name, { detail, bubbles: true }));
  }

  /* ============================================================
     MODAL
     ============================================================ */
  class Modal {
    constructor(el) {
      this.el = el;
      this.backdrop = null;
      this._onKey = this._onKey.bind(this);
    }
    show() {
      if (this.el.classList.contains('show')) return;
      this.backdrop = document.createElement('div');
      this.backdrop.className = 'modal-backdrop';
      document.body.appendChild(this.backdrop);
      document.body.style.overflow = 'hidden';
      this.el.style.display = 'flex';
      void this.el.offsetWidth;
      requestAnimationFrame(() => {
        this.el.classList.add('show');
        this.backdrop.classList.add('show');
      });
      this.backdrop.addEventListener('click', () => this.hide());
      document.addEventListener('keydown', this._onKey);
      dispatch(this.el, 'shown.bs.modal');
    }
    hide() {
      if (!this.el.classList.contains('show')) return;
      this.el.classList.remove('show');
      if (this.backdrop) this.backdrop.classList.remove('show');
      document.removeEventListener('keydown', this._onKey);
      setTimeout(() => {
        this.el.style.display = 'none';
        if (this.backdrop) this.backdrop.remove();
        document.body.style.overflow = '';
        dispatch(this.el, 'hidden.bs.modal');
      }, 200);
    }
    toggle() { this.el.classList.contains('show') ? this.hide() : this.show(); }
    _onKey(e) { if (e.key === 'Escape') this.hide(); }
    static getInstance(el) { return instances.has(el) && instances.get(el).get(Modal); }
    static getOrCreateInstance(el) { return getOrCreate(el, Modal); }
  }

  /* ============================================================
     DROPDOWN
     ============================================================ */
  class Dropdown {
    constructor(el) {
      this.el = el;
      this.menu = el.nextElementSibling && el.nextElementSibling.classList.contains('dropdown-menu')
        ? el.nextElementSibling
        : el.parentElement.querySelector('.dropdown-menu');
    }
    show() {
      qsa('.dropdown-menu.show').forEach((m) => { if (m !== this.menu) m.classList.remove('show'); });
      this.menu.classList.add('show');
      this.el.setAttribute('aria-expanded', 'true');
      dispatch(this.el, 'shown.bs.dropdown');
    }
    hide() {
      this.menu.classList.remove('show');
      this.el.setAttribute('aria-expanded', 'false');
      dispatch(this.el, 'hidden.bs.dropdown');
    }
    toggle() { this.menu.classList.contains('show') ? this.hide() : this.show(); }
    static getOrCreateInstance(el) { return getOrCreate(el, Dropdown); }
  }
  document.addEventListener('click', (e) => {
    const toggle = e.target.closest('[data-bs-toggle="dropdown"]');
    if (toggle) {
      e.preventDefault();
      Dropdown.getOrCreateInstance(toggle).toggle();
      return;
    }
    if (!e.target.closest('.dropdown-menu')) {
      qsa('.dropdown-menu.show').forEach((m) => {
        m.classList.remove('show');
        const prevToggle = m.previousElementSibling;
        if (prevToggle) prevToggle.setAttribute('aria-expanded', 'false');
      });
    }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') qsa('.dropdown-menu.show').forEach((m) => m.classList.remove('show'));
  });

  /* ============================================================
     COLLAPSE / ACCORDION
     ============================================================ */
  class Collapse {
    constructor(el) { this.el = el; }
    show() {
      this.el.classList.add('collapsing');
      this.el.classList.remove('collapse');
      this.el.style.height = '0px';
      void this.el.offsetHeight;
      const target = this.el.scrollHeight;
      this.el.style.height = target + 'px';
      setTimeout(() => {
        this.el.classList.remove('collapsing');
        this.el.classList.add('collapse', 'show');
        this.el.style.height = '';
        dispatch(this.el, 'shown.bs.collapse');
      }, 230);
    }
    hide() {
      this.el.style.height = this.el.scrollHeight + 'px';
      void this.el.offsetHeight;
      this.el.classList.add('collapsing');
      this.el.classList.remove('collapse', 'show');
      this.el.style.height = '0px';
      setTimeout(() => {
        this.el.classList.remove('collapsing');
        this.el.classList.add('collapse');
        this.el.style.height = '';
        dispatch(this.el, 'hidden.bs.collapse');
      }, 230);
    }
    toggle() { this.el.classList.contains('show') ? this.hide() : this.show(); }
    static getOrCreateInstance(el) { return getOrCreate(el, Collapse); }
  }
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-bs-toggle="collapse"]');
    if (!trigger) return;
    e.preventDefault();
    const sel = trigger.getAttribute('data-bs-target') || trigger.getAttribute('href');
    if (!sel) return;
    qsa(sel).forEach((target) => {
      const inst = Collapse.getOrCreateInstance(target);
      inst.toggle();
      trigger.classList.toggle('collapsed', !target.classList.contains('show'));
      trigger.setAttribute('aria-expanded', target.classList.contains('show') ? 'false' : 'true');
    });
    const parentSel = trigger.getAttribute('data-bs-parent');
    if (parentSel) {
      const parent = qs(parentSel);
      if (parent) {
        qsa('.accordion-collapse.show', parent).forEach((other) => {
          if (sel && !sel.includes(other.id)) {
            Collapse.getOrCreateInstance(other).hide();
            const btn = qs(`[data-bs-target="#${other.id}"]`, parent);
            if (btn) { btn.classList.add('collapsed'); btn.setAttribute('aria-expanded', 'false'); }
          }
        });
      }
    }
  });

  /* ============================================================
     TABS
     ============================================================ */
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-bs-toggle="tab"], [data-bs-toggle="pill"]');
    if (!trigger) return;
    e.preventDefault();
    const sel = trigger.getAttribute('data-bs-target') || trigger.getAttribute('href');
    if (!sel) return;
    const pane = qs(sel);
    if (!pane) return;
    const nav = trigger.closest('.nav') || document;
    qsa('.nav-link', nav).forEach((l) => l.classList.remove('active'));
    trigger.classList.add('active');
    const content = pane.parentElement;
    qsa(':scope > .tab-pane', content).forEach((p) => p.classList.remove('active'));
    pane.classList.add('active');
    dispatch(trigger, 'shown.bs.tab');
  });

  /* ============================================================
     ALERT / GENERIC DISMISS (alert, modal, toast, offcanvas)
     ============================================================ */
  document.addEventListener('click', (e) => {
    const closeBtn = e.target.closest('[data-bs-dismiss]');
    if (!closeBtn) return;
    const type = closeBtn.getAttribute('data-bs-dismiss');
    if (type === 'alert') {
      const alertEl = closeBtn.closest('.alert');
      if (alertEl) {
        alertEl.style.transition = 'opacity 180ms ease, transform 180ms ease';
        alertEl.style.opacity = '0';
        alertEl.style.transform = 'translateY(-4px)';
        setTimeout(() => alertEl.remove(), 180);
      }
    } else if (type === 'modal') {
      const modalEl = closeBtn.closest('.modal');
      if (modalEl) Modal.getOrCreateInstance(modalEl).hide();
    } else if (type === 'toast') {
      const toastEl = closeBtn.closest('.toast');
      if (toastEl) Toast.getOrCreateInstance(toastEl).hide();
    } else if (type === 'offcanvas') {
      const ocEl = closeBtn.closest('.offcanvas');
      if (ocEl) Offcanvas.getOrCreateInstance(ocEl).hide();
    }
  });

  /* ============================================================
     TOAST
     ============================================================ */
  class Toast {
    constructor(el, opts) {
      this.el = el;
      this.delay = (opts && opts.delay) || parseInt(el.getAttribute('data-bs-delay'), 10) || 4000;
      this.autohide = el.getAttribute('data-bs-autohide') !== 'false';
      this._timer = null;
    }
    show() {
      this.el.style.display = 'block';
      void this.el.offsetWidth;
      this.el.classList.add('show');
      dispatch(this.el, 'shown.bs.toast');
      if (this.autohide) {
        clearTimeout(this._timer);
        this._timer = setTimeout(() => this.hide(), this.delay);
      }
    }
    hide() {
      this.el.classList.remove('show');
      clearTimeout(this._timer);
      setTimeout(() => { this.el.style.display = 'none'; dispatch(this.el, 'hidden.bs.toast'); }, 240);
    }
    static getOrCreateInstance(el) { return getOrCreate(el, Toast); }
  }

  /* ============================================================
     TOOLTIP
     ============================================================ */
  class Tooltip {
    constructor(el) {
      this.el = el;
      this.text = el.getAttribute('data-bs-title') || el.getAttribute('title') || '';
      if (el.hasAttribute('title')) el.removeAttribute('title');
      this.tip = null;
      el.addEventListener('mouseenter', () => this.show());
      el.addEventListener('mouseleave', () => this.hide());
      el.addEventListener('focus', () => this.show());
      el.addEventListener('blur', () => this.hide());
    }
    show() {
      if (!this.text) return;
      this.tip = document.createElement('div');
      this.tip.className = 'mb-tooltip';
      this.tip.textContent = this.text;
      document.body.appendChild(this.tip);
      const r = this.el.getBoundingClientRect();
      const placement = this.el.getAttribute('data-bs-placement') || 'top';
      const tr = this.tip.getBoundingClientRect();
      let top, left;
      if (placement === 'bottom') { top = r.bottom + 6; left = r.left + r.width / 2 - tr.width / 2; }
      else if (placement === 'left') { top = r.top + r.height / 2 - tr.height / 2; left = r.left - tr.width - 6; }
      else if (placement === 'right') { top = r.top + r.height / 2 - tr.height / 2; left = r.right + 6; }
      else { top = r.top - tr.height - 6; left = r.left + r.width / 2 - tr.width / 2; }
      this.tip.style.top = `${top + window.scrollY}px`;
      this.tip.style.left = `${left + window.scrollX}px`;
      requestAnimationFrame(() => this.tip && this.tip.classList.add('show'));
    }
    hide() {
      if (!this.tip) return;
      this.tip.classList.remove('show');
      const t = this.tip;
      setTimeout(() => t.remove(), 130);
      this.tip = null;
    }
    static getOrCreateInstance(el) { return getOrCreate(el, Tooltip); }
  }

  /* ============================================================
     POPOVER — click-triggered, richer than tooltip
     ============================================================ */
  class Popover {
    constructor(el) {
      this.el = el;
      this.title = el.getAttribute('data-bs-title') || '';
      this.content = el.getAttribute('data-bs-content') || '';
      if (el.hasAttribute('title')) el.removeAttribute('title');
      this.pop = null;
      el.addEventListener('click', (e) => { e.stopPropagation(); this.toggle(); });
    }
    show() {
      qsa('.mb-popover.show').forEach((p) => p.classList.remove('show'));
      this.pop = document.createElement('div');
      this.pop.className = 'mb-popover';
      this.pop.innerHTML =
        (this.title ? `<div class="mb-popover-header">${this.title}</div>` : '') +
        `<div class="mb-popover-body">${this.content}</div>`;
      document.body.appendChild(this.pop);
      const r = this.el.getBoundingClientRect();
      const placement = this.el.getAttribute('data-bs-placement') || 'top';
      const pr = this.pop.getBoundingClientRect();
      let top, left;
      if (placement === 'bottom') { top = r.bottom + 8; left = r.left + r.width / 2 - pr.width / 2; }
      else { top = r.top - pr.height - 8; left = r.left + r.width / 2 - pr.width / 2; }
      this.pop.style.top = `${top + window.scrollY}px`;
      this.pop.style.left = `${left + window.scrollX}px`;
      requestAnimationFrame(() => this.pop && this.pop.classList.add('show'));
      document.addEventListener('click', this._outside = (e) => {
        if (!this.pop.contains(e.target) && e.target !== this.el) this.hide();
      });
    }
    hide() {
      if (!this.pop) return;
      this.pop.classList.remove('show');
      const p = this.pop;
      setTimeout(() => p.remove(), 130);
      this.pop = null;
      document.removeEventListener('click', this._outside);
    }
    toggle() { this.pop ? this.hide() : this.show(); }
    static getOrCreateInstance(el) { return getOrCreate(el, Popover); }
  }

  /* ============================================================
     OFFCANVAS
     ============================================================ */
  class Offcanvas {
    constructor(el) { this.el = el; this.backdrop = null; this._onKey = this._onKey.bind(this); }
    show() {
      if (this.el.classList.contains('show')) return;
      this.backdrop = document.createElement('div');
      this.backdrop.className = 'modal-backdrop';
      document.body.appendChild(this.backdrop);
      document.body.style.overflow = 'hidden';
      void this.el.offsetWidth;
      requestAnimationFrame(() => {
        this.el.classList.add('show');
        this.backdrop.classList.add('show');
      });
      this.backdrop.addEventListener('click', () => this.hide());
      document.addEventListener('keydown', this._onKey);
      dispatch(this.el, 'shown.bs.offcanvas');
    }
    hide() {
      this.el.classList.remove('show');
      if (this.backdrop) this.backdrop.classList.remove('show');
      document.removeEventListener('keydown', this._onKey);
      setTimeout(() => {
        if (this.backdrop) this.backdrop.remove();
        document.body.style.overflow = '';
        dispatch(this.el, 'hidden.bs.offcanvas');
      }, 260);
    }
    toggle() { this.el.classList.contains('show') ? this.hide() : this.show(); }
    _onKey(e) { if (e.key === 'Escape') this.hide(); }
    static getOrCreateInstance(el) { return getOrCreate(el, Offcanvas); }
  }
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-bs-toggle="offcanvas"]');
    if (!trigger) return;
    e.preventDefault();
    const sel = trigger.getAttribute('data-bs-target') || trigger.getAttribute('href');
    const target = sel && qs(sel);
    if (target) Offcanvas.getOrCreateInstance(target).show();
  });

  /* ============================================================
     CAROUSEL
     ============================================================ */
  class Carousel {
    constructor(el, opts) {
      this.el = el;
      this.items = qsa('.carousel-item', el);
      this.indicators = qsa('.carousel-indicators button', el);
      this.index = this.items.findIndex((i) => i.classList.contains('active'));
      if (this.index < 0) { this.index = 0; if (this.items[0]) this.items[0].classList.add('active'); }
      this.interval = (opts && opts.interval) || parseInt(el.getAttribute('data-bs-interval'), 10) || 5000;
      this._timer = null;
      const prev = qs('.carousel-control-prev', el);
      const next = qs('.carousel-control-next', el);
      if (prev) prev.addEventListener('click', (e) => { e.preventDefault(); this.prev(); });
      if (next) next.addEventListener('click', (e) => { e.preventDefault(); this.next(); });
      this.indicators.forEach((btn, i) => btn.addEventListener('click', () => this.to(i)));
      if (this.interval > 0) this._play();
      el.addEventListener('mouseenter', () => this._pause());
      el.addEventListener('mouseleave', () => this._play());
    }
    to(i) {
      if (i === this.index || !this.items[i]) return;
      this.items[this.index].classList.remove('active');
      this.items[i].classList.add('active');
      this.indicators.forEach((b, bi) => b.classList.toggle('active', bi === i));
      this.index = i;
      dispatch(this.el, 'slid.bs.carousel');
    }
    next() { this.to((this.index + 1) % this.items.length); }
    prev() { this.to((this.index - 1 + this.items.length) % this.items.length); }
    _play() { if (this.interval > 0) { clearInterval(this._timer); this._timer = setInterval(() => this.next(), this.interval); } }
    _pause() { clearInterval(this._timer); }
    static getOrCreateInstance(el, opts) { return getOrCreate(el, Carousel, opts); }
  }

  /* ============================================================
     SCROLLSPY — highlights nav-link matching scroll position
     ============================================================ */
  class ScrollSpy {
    constructor(el, opts) {
      this.root = el; // scrollable container or document.body
      const targetSel = (opts && opts.target) || el.getAttribute('data-bs-target');
      this.nav = targetSel ? qs(targetSel) : null;
      this.sections = qsa('[id]', el).filter((s) => this.nav && qs(`[href="#${s.id}"]`, this.nav));
      if (!this.nav || !this.sections.length) return;
      this._observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            qsa('.nav-link', this.nav).forEach((l) => l.classList.remove('active'));
            const link = qs(`[href="#${entry.target.id}"]`, this.nav);
            if (link) link.classList.add('active');
          }
        });
      }, { rootMargin: '-20% 0px -70% 0px' });
      this.sections.forEach((s) => this._observer.observe(s));
    }
    static getOrCreateInstance(el, opts) { return getOrCreate(el, ScrollSpy, opts); }
  }

  /* ============================================================
     AUTO-INIT on DOM ready
     ============================================================ */
  function init() {
    qsa('[data-bs-toggle="tooltip"]').forEach((el) => Tooltip.getOrCreateInstance(el));
    qsa('[data-bs-toggle="popover"]').forEach((el) => Popover.getOrCreateInstance(el));
    qsa('[data-bs-toggle="modal"]').forEach((trigger) => {
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        const sel = trigger.getAttribute('data-bs-target') || trigger.getAttribute('href');
        const modalEl = sel && qs(sel);
        if (modalEl) Modal.getOrCreateInstance(modalEl).show();
      });
    });
    qsa('.toast').forEach((el) => {
      if (el.getAttribute('data-bs-autoshow') === 'true') Toast.getOrCreateInstance(el).show();
    });
    qsa('.carousel').forEach((el) => Carousel.getOrCreateInstance(el));
    qsa('[data-bs-spy="scroll"]').forEach((el) => ScrollSpy.getOrCreateInstance(el));
    qsa('.navbar-toggler').forEach((btn) => {
      btn.addEventListener('click', () => {
        const sel = btn.getAttribute('data-bs-target');
        const target = sel ? qs(sel) : null;
        if (target) target.classList.toggle('show');
      });
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  /* ============================================================
     EXPORT
     ============================================================ */
  const ModernUI = { Modal, Dropdown, Collapse, Toast, Tooltip, Popover, Offcanvas, Carousel, ScrollSpy };
  global.ModernUI = ModernUI;
  if (!global.bootstrap) global.bootstrap = ModernUI; // drop-in alias
})(window);
