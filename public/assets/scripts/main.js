AOS.init({
  once: true,
  delay: 50,
  // duration: 600
});

document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bstooltip-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
}, false);

function keluar() {
  Swal.fire({
    title: 'Confirm Logout',
    text: "This action will end your current session.",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#435ebe',
    // cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, Log out',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById('logoutForm').submit();
    }
  })
}

function hapusData(id, title, text) {
  Swal.fire({
    title: title,
    text: text,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    // cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete!',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById(`hapus-${id}`).submit();
    }
  })
}

// function copyToClipboard(text) {
//   navigator.clipboard.writeText(text);
//   Swal.fire({
//     toast: true,
//     position: 'top',
//     showConfirmButton: false,
//     timer: 3000,
//     icon: 'success',
//     title: 'Copied to clipboard!',
//   })
// }

// ...existing code...

function copyToClipboard(text) {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text)
      .then(() => {
        Swal.fire({
          toast: true,
          position: 'top',
          showConfirmButton: false,
          timer: 3000,
          icon: 'success',
          title: 'Copied to clipboard!',
        });
      })
      .catch(() => {
        Swal.fire({
          toast: true,
          position: 'top',
          showConfirmButton: false,
          timer: 3000,
          icon: 'error',
          title: 'Failed to copy!',
        });
      });
  } else {
    // Fallback untuk HTTP/non-secure context
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    try {
      document.execCommand('copy');
      Swal.fire({
        toast: true,
        position: 'top',
        showConfirmButton: false,
        timer: 3000,
        icon: 'success',
        title: 'Copied to clipboard!',
      });
    } catch (err) {
      Swal.fire({
        toast: true,
        position: 'top',
        showConfirmButton: false,
        timer: 3000,
        icon: 'error',
        title: 'Failed to copy!',
      });
    }
    document.body.removeChild(textarea);
  }
}

// Sidebar Desktop Toggle State Persistence
const SIDEBAR_STORAGE_KEY = 'desktop-sidebar-state';
const SIDEBAR_BREAKPOINT = '(min-width: 1200px)';

// Apply stored sidebar state early - with inline style to force correct layout
const applySidebarStateEarly = () => {
  if (!window.matchMedia(SIDEBAR_BREAKPOINT).matches) {
    return;
  }

  const sidebar = document.getElementById('sidebar');
  const wrapper = document.querySelector('.sidebar-wrapper');
  const main = document.getElementById('main');

  if (!sidebar || !wrapper) {
    return;
  }

  const storedState = localStorage.getItem(SIDEBAR_STORAGE_KEY);
  const shouldShow = storedState !== 'hidden';

  // Set class
  sidebar.classList.remove('active', 'inactive');
  sidebar.classList.add(shouldShow ? 'active' : 'inactive');

  // Force with inline styles to override any component defaults
  if (shouldShow) {
    wrapper.style.left = '0';
    if (main) main.style.marginLeft = '300px';
  } else {
    wrapper.style.left = '-300px';
    if (main) main.style.marginLeft = '0';
  }
};

// Run early before component sidebar initializes
applySidebarStateEarly();

// Full initialization with event handlers - runs after DOM is ready
(() => {
  const initSidebarToggle = () => {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) {
      return;
    }

    const desktopBreakpoint = window.matchMedia(SIDEBAR_BREAKPOINT);
    const wrapper = document.querySelector('.sidebar-wrapper');
    const main = document.getElementById('main');

    const isDesktop = () => desktopBreakpoint.matches;

    const setDesktopSidebarState = (isVisible) => {
      sidebar.classList.toggle('active', isVisible);
      sidebar.classList.toggle('inactive', !isVisible);

      // Force inline styles
      if (isVisible) {
        wrapper.style.left = '0';
        if (main) main.style.marginLeft = '300px';
      } else {
        wrapper.style.left = '-300px';
        if (main) main.style.marginLeft = '0';
      }

      document.querySelector('.sidebar-backdrop')?.remove();
      document.body.style.overflowY = 'auto';
      localStorage.setItem(SIDEBAR_STORAGE_KEY, isVisible ? 'shown' : 'hidden');
    };

    const syncDesktopSidebarState = () => {
      if (!isDesktop()) {
        return;
      }

      const storedState = localStorage.getItem(SIDEBAR_STORAGE_KEY);
      setDesktopSidebarState(storedState !== 'hidden');
    };

    document.querySelectorAll('.burger-btn, .sidebar-hide').forEach((trigger) => {
      trigger.addEventListener('click', (event) => {
        if (!isDesktop()) {
          return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const isVisible = !sidebar.classList.contains('inactive');
        setDesktopSidebarState(!isVisible);
      }, true);
    });

    // Intercept window resize to maintain localStorage state for desktop
    const originalResize = window.onresize;
    window.addEventListener('resize', (event) => {
      syncDesktopSidebarState();
      if (typeof originalResize === 'function') {
        originalResize.call(window, event);
      }
    });

    desktopBreakpoint.addEventListener('change', syncDesktopSidebarState);
    syncDesktopSidebarState();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarToggle);
  } else {
    initSidebarToggle();
  }
})();
// ...existing code...
