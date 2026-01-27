function setFontSize(percent) {
  document.documentElement.style.fontSize = percent + '%';
}
// console.log('Window width:', window.innerWidth);
// jika resolusi monitor kurang dari sama dengan 1600px, set font size ke 90% dan jika kurang dari sama dengan 1440px set font size ke 85%
if (window.innerWidth <= 1600) {
    setFontSize(90);
}
if (window.innerWidth <= 1440) {
    setFontSize(85);
}

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
// ...existing code...
