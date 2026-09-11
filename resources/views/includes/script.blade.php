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

            // Use config() (not env()) so Echo still boots correctly after config:cache.
            const broadcaster = @json(config('broadcasting.default') === 'reverb' ? 'reverb' : 'pusher');
            const key = broadcaster === 'reverb'
                ? @json(config('broadcasting.connections.reverb.key'))
                : @json(config('broadcasting.connections.pusher.key'));
            // Browser clients must connect to the same host serving the app (LAN/IP),
            // not server-only loopback values like 127.0.0.1 from REVERB_HOST.
            const configuredReverbHost = @json((string) config('broadcasting.connections.reverb.options.host'));
            const reverbHostIsLoopback = !configuredReverbHost
                || configuredReverbHost === '127.0.0.1'
                || configuredReverbHost === 'localhost'
                || configuredReverbHost === '0.0.0.0';
            const wsHost = broadcaster === 'reverb'
                ? (reverbHostIsLoopback ? window.location.hostname : configuredReverbHost)
                : @json((string) config('broadcasting.connections.pusher.options.host'));
            const wsPort = broadcaster === 'reverb'
                ? {{ (int) config('broadcasting.connections.reverb.options.port', 8080) }}
                : {{ (int) config('broadcasting.connections.pusher.options.port', 443) }};
            const forceTLS = broadcaster === 'reverb'
                ? @json(config('broadcasting.connections.reverb.options.scheme') === 'https')
                : @json(config('broadcasting.connections.pusher.options.scheme') === 'https');

            window.Echo = new EchoConstructor({
                broadcaster: broadcaster,
                key: key,
                wsHost: wsHost,
                wsPort: wsPort,
                wssPort: wsPort,
                forceTLS: forceTLS,
                enabledTransports: ['ws', 'wss'],
                cluster: @json(config('broadcasting.connections.pusher.options.cluster', 'mt1')),
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
    <script src="{{ url('assets/scripts/modules/screen-messages.js') }}?v={{ @filemtime(public_path('assets/scripts/modules/screen-messages.js')) ?: time() }}"></script>
    <script src="{{ url('assets/scripts/modules/chat-widget.js') }}?v={{ @filemtime(public_path('assets/scripts/modules/chat-widget.js')) ?: time() }}"></script>

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
