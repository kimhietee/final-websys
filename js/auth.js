/* ============================================================
   KIM INVENTORIES — auth.js
   Utility functions for the PHP + MySQL version.
   Authentication is now handled server-side by PHP sessions.
   ============================================================ */

// ─── Toast Utility ───────────────────────────────────────────────────────

/** Show a brief toast notification at bottom-right of screen. */
function showToast(message, duration) {
  duration = duration || 2200;
  var el = document.getElementById('toastNotif');
  if (!el) {
    el = document.createElement('div');
    el.id = 'toastNotif';
    el.className = 'toast-notif';
    document.body.appendChild(el);
  }
  el.textContent = message;
  el.classList.add('show');
  clearTimeout(el._timer);
  el._timer = setTimeout(function() { el.classList.remove('show'); }, duration);
}
