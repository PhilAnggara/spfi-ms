<?php

it('treats requests behind a reverse proxy as secure when X-Forwarded-Proto is https', function () {
    $this->get('/up', [
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_FOR' => '192.168.11.50',
    ])->assertSuccessful();

    expect(request()->secure())->toBeTrue();
});

it('keeps plain http requests insecure without forwarded proto', function () {
    $this->get('/up')->assertSuccessful();

    expect(request()->secure())->toBeFalse();
});
