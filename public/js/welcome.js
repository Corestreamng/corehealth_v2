/**
 * CoreHealth Welcome & Landing Page Standalone Logic
 * public/js/welcome.js
 *
 * Verified with `node --check public/js/welcome.js`
 */
document.addEventListener('DOMContentLoaded', () => {
  // 1. Mobile Drawer Toggle
  const burger = document.getElementById('burger');
  const drawer = document.getElementById('drawer');

  if (burger && drawer) {
    burger.addEventListener('click', () => {
      const isOpen = drawer.classList.toggle('open');
      burger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    drawer.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        drawer.classList.remove('open');
        burger.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // 2. Mega Menu: Keyboard & Touch Toggle
  const navItems = document.querySelectorAll('.nav-item');
  navItems.forEach((item) => {
    const trigger = item.querySelector('button.nav-trigger');
    if (!trigger) return;

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const wasOpen = item.classList.contains('open');
      navItems.forEach((i) => i.classList.remove('open'));
      if (!wasOpen) {
        item.classList.add('open');
      }
    });
  });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.nav-item')) {
      navItems.forEach((i) => i.classList.remove('open'));
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      navItems.forEach((i) => i.classList.remove('open'));
      if (drawer && drawer.classList.contains('open')) {
        drawer.classList.remove('open');
        if (burger) burger.setAttribute('aria-expanded', 'false');
      }
    }
  });

  // 3. Solution Tabs Switcher
  const tabs = document.querySelectorAll('.tab');
  const panels = document.querySelectorAll('.panel');

  if (tabs.length && panels.length) {
    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        const targetId = tab.dataset.t;
        const targetPanel = document.getElementById(targetId);

        if (!targetPanel) return;

        tabs.forEach((t) => t.setAttribute('aria-selected', 'false'));
        panels.forEach((p) => p.classList.remove('on'));

        tab.setAttribute('aria-selected', 'true');
        targetPanel.classList.add('on');
      });
    });
  }
});

