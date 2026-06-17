/**
 * ServiceHub — Nav Hambúrguer com Drawer Lateral
 * Mobile-first: drawer desliza da esquerda com overlay
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {

    /* ── Injeta o drawer e overlay se não existirem ─────── */
    if (!document.getElementById('sh-drawer')) {
      const overlay = document.createElement('div');
      overlay.id = 'sh-overlay';
      overlay.className = 'nav-overlay';
      overlay.setAttribute('aria-hidden', 'true');

      const drawer = document.createElement('nav');
      drawer.id = 'sh-drawer';
      drawer.className = 'nav-drawer';
      drawer.setAttribute('role', 'navigation');
      drawer.setAttribute('aria-label', 'Menu principal');

      /* Header do drawer */
      const drawerHeader = document.createElement('div');
      drawerHeader.className = 'nav-drawer-header';
      drawerHeader.innerHTML = `
        <div class="logo">
          <h1>Service<span>Hub</span></h1>
        </div>
        <button class="nav-drawer-close" id="sh-drawer-close" aria-label="Fechar menu">
          &#x2715;
        </button>`;

      /* Links — copia da .nav-items existente */
      const drawerLinks = document.createElement('div');
      drawerLinks.className = 'nav-drawer-links';

      const navItems = document.querySelector('.nav-items');
      if (navItems) {
        navItems.querySelectorAll('a').forEach(function (a) {
          const clone = a.cloneNode(true);
          clone.addEventListener('click', closeDrawer);
          drawerLinks.appendChild(clone);
        });
      }

      /* Footer — logout */
      const drawerFooter = document.createElement('div');
      drawerFooter.className = 'nav-drawer-footer';
      const logoutLink = navItems ? navItems.querySelector('a[href*="logout"]') : null;
      if (logoutLink) {
        const lClone = logoutLink.cloneNode(true);
        lClone.className = 'btn btn-ghost btn-block';
        drawerFooter.appendChild(lClone);
      }

      drawer.appendChild(drawerHeader);
      drawer.appendChild(drawerLinks);
      if (logoutLink) drawer.appendChild(drawerFooter);

      document.body.prepend(overlay);
      document.body.prepend(drawer);

      document.getElementById('sh-drawer-close').addEventListener('click', closeDrawer);
      overlay.addEventListener('click', closeDrawer);
    }

    /* ── Hamburgers ─────────────────────────────────────── */
    document.querySelectorAll('.hamburger').forEach(function (btn) {
      btn.addEventListener('click', openDrawer);
    });

    function openDrawer() {
      const drawer  = document.getElementById('sh-drawer');
      const overlay = document.getElementById('sh-overlay');
      if (!drawer || !overlay) return;
      drawer.classList.add('open');
      overlay.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
      const drawer  = document.getElementById('sh-drawer');
      const overlay = document.getElementById('sh-overlay');
      if (!drawer || !overlay) return;
      drawer.classList.remove('open');
      overlay.classList.remove('open');
      document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeDrawer();
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth >= 768) closeDrawer();
    });

    /* Link ativo */
    const current = window.location.pathname.split('/').pop() || '';
    document.querySelectorAll('.nav-items a, .nav-drawer-links a').forEach(function (a) {
      const href = a.getAttribute('href') || '';
      if (current && href.includes(current)) a.classList.add('active');
    });

  });
})();
