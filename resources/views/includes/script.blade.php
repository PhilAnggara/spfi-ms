    @stack('prepend-script')

    <script src="{{ url('assets/static/js/components/dark.js') }}"></script>
    <script src="{{ url('assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ url('assets/compiled/js/app.js') }}"></script>
    <script src="{{ url('assets/vendors/sweetalert/sweetalert2.js') }}"></script>
    <script src="{{ url('assets/vendors/aos/aos.js') }}"></script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
    <script>
        (function () {
            if (window.__spfiEchoBooted) {
                return;
            }

            const EchoConstructor = window.Echo;
            if (typeof EchoConstructor !== 'function') {
                return;
            }

            window.__spfiEchoBooted = true;
            window.Pusher = window.Pusher || window.pusher;

            const broadcaster = '{{ env('BROADCAST_CONNECTION', 'pusher') }}' === 'reverb' ? 'reverb' : 'pusher';
            const key = broadcaster === 'reverb' ? '{{ env('REVERB_APP_KEY') }}' : '{{ env('PUSHER_APP_KEY') }}';
            const wsHost = broadcaster === 'reverb'
                ? '{{ env('REVERB_HOST', request()->getHost()) }}'
                : '{{ env('PUSHER_HOST', 'ws-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com') }}';
            const wsPort = broadcaster === 'reverb'
                ? {{ (int) env('REVERB_PORT', 8080) }}
                : {{ (int) env('PUSHER_PORT', 443) }};
            const forceTLS = broadcaster === 'reverb'
                ? ('{{ env('REVERB_SCHEME', 'http') }}' === 'https')
                : ('{{ env('PUSHER_SCHEME', 'https') }}' === 'https');

            window.Echo = new EchoConstructor({
                broadcaster: broadcaster,
                key: key,
                wsHost: wsHost,
                wsPort: wsPort,
                wssPort: wsPort,
                forceTLS: forceTLS,
                enabledTransports: ['ws', 'wss'],
                cluster: '{{ env('PUSHER_APP_CLUSTER', 'mt1') }}',
                authEndpoint: '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                },
            });
        })();
    </script>

    <script src="{{ url('assets/scripts/main.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/realtime-notifications.js') }}"></script>

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
            title: @json(session('success')),
            icon: "success",
            timer: 5000,
        });
    </script>
    @endif
