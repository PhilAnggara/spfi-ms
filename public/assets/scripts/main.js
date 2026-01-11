function keluar() {
  Swal.fire({
    title: 'Confirm Logout',
    text: "This action will end your current session.",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#435EBE',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, Log out',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById('logoutForm').submit();
    }
  })
}
