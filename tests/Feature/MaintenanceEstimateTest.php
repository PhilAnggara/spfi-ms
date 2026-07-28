<?php

it('shows estimate and countdown markup when retryAfter is provided', function () {
    $html = view('maintenance', ['retryAfter' => 1800])->render();

    expect($html)
        ->toContain('id="maintenance-estimate"')
        ->toContain('data-retry-after="1800"')
        ->toContain('id="maintenance-countdown"')
        ->toContain('Estimated downtime: about 30 minutes.')
        ->toContain('Time remaining')
        ->toContain('font-monospace');
});

it('formats multi-hour estimates with remaining minutes', function () {
    $html = view('maintenance', ['retryAfter' => 9000])->render();

    expect($html)
        ->toContain('Estimated downtime: about 2 hours 30 minutes.')
        ->toContain('data-retry-after="9000"');
});

it('hides estimate and countdown when retryAfter is not provided', function () {
    $html = view('maintenance')->render();

    expect($html)
        ->toContain('We are currently performing maintenance on the system. Please try again shortly.')
        ->not->toContain('id="maintenance-estimate"')
        ->not->toContain('id="maintenance-countdown"')
        ->not->toContain('Estimated downtime:')
        ->not->toContain('Time remaining');
});
