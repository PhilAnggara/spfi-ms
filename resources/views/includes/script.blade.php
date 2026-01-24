    @stack('prepend-script')

    <script src="{{ url('assets/static/js/components/dark.js') }}"></script>
    <script src="{{ url('assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ url('assets/compiled/js/app.js') }}"></script>
    <script src="{{ url('assets/vendors/sweetalert/sweetalert2.js') }}"></script>
    <script src="{{ url('assets/vendors/aos/aos.js') }}"></script>

    <script src="{{ url('assets/scripts/main.js') }}"></script>

    @stack('addon-script')

    @if (session('success'))
    <script>
        // const Toast = Swal.mixin({
        //     toast: true,
        //     position: "top",
        //     showConfirmButton: false,
        //     timer: 5000,
        //     timerProgressBar: true,
        //     didOpen: (toast) => {
        //         toast.onmouseenter = Swal.stopTimer;
        //         toast.onmouseleave = Swal.resumeTimer;
        //     }
        // });
        // Toast.fire({
        //     icon: "success",
        //     title: "{{ session('success') }}"
        // });
        Swal.fire({
            // title: "Success!",
            // text: "{{ session('success') }}",
            title: "{{ session('success') }}",
            icon: "success",
            timer: 5000,
        });
    </script>
    @endif
