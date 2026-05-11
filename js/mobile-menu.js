/**
 * Mobile Menu Toggle
 * Handles sidebar visibility on mobile devices
 */

function initMobileMenu() {
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  const menuBtn = document.querySelector('.mobile-menu-btn');
  const menuIcon = menuBtn ? menuBtn.querySelector('i') : null;
  
  if (!sidebar || !overlay || !menuBtn) return;
  
  // Toggle menu on button click
  menuBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const isOpen = sidebar.classList.toggle('active');
    overlay.classList.toggle('active');

    // update ARIA and icon
    menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (menuIcon) {
      menuIcon.classList.toggle('bi-list', !isOpen);
      menuIcon.classList.toggle('bi-x', isOpen);
    }

    if (isOpen) {
      overlay.style.pointerEvents = 'auto';
      document.body.style.overflow = 'hidden';
    } else {
      overlay.style.pointerEvents = 'none';
      document.body.style.overflow = 'auto';
    }
  });
  
  // Close menu when clicking overlay
  overlay.addEventListener('click', (e) => {
    e.stopPropagation();
    closeMobileMenu();
  });
  
  // Close menu when clicking a nav item
  const navItems = document.querySelectorAll('.nav-item');
  navItems.forEach(item => {
    item.addEventListener('click', closeMobileMenu);
  });
  
  // Close menu on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeMobileMenu();
    }
  });
}

function openMobileMenu() {
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  const menuBtn = document.querySelector('.mobile-menu-btn');
  const menuIcon = menuBtn ? menuBtn.querySelector('i') : null;

  if (sidebar) sidebar.classList.add('active');
  if (overlay) {
    overlay.classList.add('active');
    overlay.style.pointerEvents = 'auto';
  }
  if (menuBtn) menuBtn.setAttribute('aria-expanded', 'true');
  if (menuIcon) { menuIcon.classList.remove('bi-list'); menuIcon.classList.add('bi-x'); }
  document.body.style.overflow = 'hidden';
}

function closeMobileMenu() {
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  const menuBtn = document.querySelector('.mobile-menu-btn');
  const menuIcon = menuBtn ? menuBtn.querySelector('i') : null;

  if (sidebar) sidebar.classList.remove('active');
  if (overlay) {
    overlay.classList.remove('active');
    overlay.style.pointerEvents = 'none';
  }
  if (menuBtn) menuBtn.setAttribute('aria-expanded', 'false');
  if (menuIcon) { menuIcon.classList.remove('bi-x'); menuIcon.classList.add('bi-list'); }
  document.body.style.overflow = 'auto';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', initMobileMenu);
