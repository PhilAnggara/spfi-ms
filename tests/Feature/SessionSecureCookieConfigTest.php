<?php

it('defaults session secure cookies to false for dual http and https lan access', function () {
    expect(config('session.secure'))->toBeFalse();
});
