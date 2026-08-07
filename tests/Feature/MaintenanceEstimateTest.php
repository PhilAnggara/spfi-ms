<?php

it('shows estimate and countdown markup when retryAfter is provided', function () {
    $this->freezeTime();

    $retryAfter = 1800;
    $expectedEndsAt = now()->getTimestamp() + $retryAfter;
    $html = view('maintenance', ['retryAfter' => $retryAfter])->render();

    expect($html)
        ->toContain('id="maintenance-estimate"')
        ->toContain('data-retry-after="1800"')
        ->toContain('data-ends-at="'.$expectedEndsAt.'"')
        ->toContain('id="maintenance-countdown"')
        ->toContain('Estimated downtime: about 30 minutes.')
        ->toContain('Time remaining')
        ->toContain('font-monospace')
        ->toContain('dataset.endsAt');
});

it('formats multi-hour estimates with remaining minutes', function () {
    $html = view('maintenance', ['retryAfter' => 9000])->render();

    expect($html)
        ->toContain('Estimated downtime: about 2 hours 30 minutes.')
        ->toContain('data-retry-after="9000"')
        ->toContain('data-ends-at="');
});

it('hides estimate and countdown when retryAfter is not provided', function () {
    $html = view('maintenance')->render();

    expect($html)
        ->toContain('We are currently performing maintenance on the system. Please try again shortly.')
        ->not->toContain('id="maintenance-estimate"')
        ->not->toContain('id="maintenance-countdown"')
        ->not->toContain('Estimated downtime:')
        ->not->toContain('Time remaining')
        ->not->toContain('data-ends-at="');
});

it('uses host-relative asset paths so prerendered styles work on other machines', function () {
    $html = view('maintenance')->render();

    expect($html)
        ->toContain('href="'.parse_url(url('assets/styles/spfi-tokens.css'), PHP_URL_PATH).'"')
        ->toContain('href="'.parse_url(url('assets/styles/spfi-scale.css'), PHP_URL_PATH).'"')
        ->toContain('href="'.parse_url(url('assets/compiled/css/app.css'), PHP_URL_PATH).'"')
        ->toContain('href="'.parse_url(url('assets/compiled/css/error.css'), PHP_URL_PATH).'"')
        ->toContain('href="'.parse_url(url('assets/styles/spfi-error.css'), PHP_URL_PATH).'"')
        ->toContain('src="'.parse_url(url('assets/compiled/svg/maintenance-3.svg'), PHP_URL_PATH).'"')
        ->not->toContain('href="http://')
        ->not->toContain('src="http://');
});
