<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Maintenance - SPFI-MS</title>
    <link rel="shortcut icon" href="{{ url('assets/images/favicon.png') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/app.css') }}">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/error.css') }}">
</head>
<body>
    <script src="{{ url('assets/static/js/initTheme.js') }}"></script>
    <div id="error">
        <div class="error-page container">
            <div class="col-md-8 col-12 offset-md-2">
                <div class="text-center">
                    <img class="img-error" src="{{ url('assets/compiled/svg/maintenance-3.svg') }}" alt="Service Unavailable">
                    <h1 class="error-title">Under Maintenance</h1>
                    <p class="fs-5 text-gray-600">We are currently performing maintenance on the system. Please try again shortly.</p>

                    @isset($retryAfter)
                        @php
                            $seconds = (int) $retryAfter;
                            $endsAt = now()->getTimestamp() + $seconds;
                            if ($seconds < 60) {
                                $estimateLabel = $seconds.' '.Str::plural('second', $seconds);
                            } elseif ($seconds < 3600) {
                                $minutes = (int) ceil($seconds / 60);
                                $estimateLabel = $minutes.' '.Str::plural('minute', $minutes);
                            } else {
                                $hours = intdiv($seconds, 3600);
                                $minutes = (int) ceil(($seconds % 3600) / 60);
                                $estimateLabel = $hours.' '.Str::plural('hour', $hours);
                                if ($minutes > 0) {
                                    $estimateLabel .= ' '.$minutes.' '.Str::plural('minute', $minutes);
                                }
                            }
                        @endphp
                        <div id="maintenance-estimate" class="mt-3 mb-3" data-retry-after="{{ $seconds }}" data-ends-at="{{ $endsAt }}">
                            <p class="fs-5 text-gray-600 mb-2">Estimated downtime: about {{ $estimateLabel }}.</p>
                            <p class="text-uppercase text-gray-600 small mb-1">Time remaining</p>
                            <p id="maintenance-countdown" class="fs-2 fw-semibold text-primary font-monospace mb-0" aria-live="polite">--:--:--</p>
                        </div>
                    @endisset

                    <p class="fs-5 text-gray-600">For urgent assistance, please contact <strong>SPFI IT Department</strong> at <strong>145</strong> or <strong>127</strong>.</p>
                    {{-- <a href="{{ url('/') }}" class="btn btn-lg btn-outline-primary mt-3">Go Home</a> --}}
                </div>
            </div>
        </div>
    </div>

    @isset($retryAfter)
        <script>
            (function () {
                const estimate = document.getElementById('maintenance-estimate');
                const countdownEl = document.getElementById('maintenance-countdown');
                if (!estimate || !countdownEl) {
                    return;
                }

                const endsAtSeconds = parseInt(estimate.dataset.endsAt, 10);
                if (!Number.isFinite(endsAtSeconds) || endsAtSeconds <= 0) {
                    return;
                }

                const endsAt = endsAtSeconds * 1000;

                function pad(value) {
                    return String(value).padStart(2, '0');
                }

                function formatRemaining(total) {
                    const hours = Math.floor(total / 3600);
                    const minutes = Math.floor((total % 3600) / 60);
                    const seconds = total % 60;

                    return pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
                }

                function tick() {
                    const remaining = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));

                    if (remaining <= 0) {
                        countdownEl.classList.remove('font-monospace', 'fs-2');
                        countdownEl.classList.add('fs-5');
                        countdownEl.textContent = 'Checking if the system is back…';
                        window.location.reload();
                        return;
                    }

                    countdownEl.textContent = formatRemaining(remaining);
                    window.setTimeout(tick, 1000);
                }

                tick();
            })();
        </script>
    @endisset

</body>
</html>
