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

function confirmCancelPo(formId) {
  Swal.fire({
    title: 'Cancel Purchase Order?',
    text: 'Items will return to Draft PO, and the PO number will be released so the paper form can be reused.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    confirmButtonText: 'Yes, cancel PO',
    cancelButtonText: 'Keep PO'
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById(formId).submit();
    }
  })
}

function confirmWithdrawPo(formId) {
  Swal.fire({
    title: 'Withdraw to Draft?',
    text: 'This purchase order will return to draft so you can edit it again.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#ffc107',
    confirmButtonText: 'Yes, withdraw',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById(formId).submit();
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
const SIDEBAR_WIDTH = 'var(--spfi-sidebar-width)';
const SIDEBAR_OFFSCREEN = 'var(--spfi-sidebar-offscreen)';

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

  // Force with inline styles to override any component defaults.
  // Use CSS tokens so margin stays in sync with scaled sidebar width.
  if (shouldShow) {
    wrapper.style.left = '0';
    if (main) main.style.marginLeft = SIDEBAR_WIDTH;
  } else {
    wrapper.style.left = SIDEBAR_OFFSCREEN;
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

      // Force inline styles using CSS tokens (keeps margin aligned with scaled sidebar)
      if (isVisible) {
        wrapper.style.left = '0';
        if (main) main.style.marginLeft = SIDEBAR_WIDTH;
      } else {
        wrapper.style.left = SIDEBAR_OFFSCREEN;
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

  /**
   * Prevent href="#" submenu toggles from navigating/hash-jumping (which can
   * look like a page reset on AJAX list pages). Also stop nested leaf clicks
   * from bubbling to parent .has-sub handlers.
   */
  const initSidebarSubmenuGuards = () => {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) {
      return;
    }

    sidebar.querySelectorAll(
      '.sidebar-item.has-sub > .sidebar-link[href="#"], .submenu-item.has-sub > .submenu-link[href="#"]'
    ).forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
      });
    });

    sidebar.querySelectorAll('.submenu-item.has-sub .submenu .submenu-link').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.stopPropagation();
      });
    });
  };

  const initSidebarEnhancements = () => {
    initSidebarToggle();
    initSidebarSubmenuGuards();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarEnhancements);
  } else {
    initSidebarEnhancements();
  }
})();
