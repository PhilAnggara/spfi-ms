<?php

it('serves the apple touch icon file at the public root', function () {
    expect(file_exists(public_path('apple-touch-icon.png')))->toBeTrue();

    $size = getimagesize(public_path('apple-touch-icon.png'));

    expect($size[0])->toBe(180)
        ->and($size[1])->toBe(180)
        ->and($size['mime'])->toBe('image/png');
});

it('includes apple touch icon tags on the login page', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('rel="apple-touch-icon"', false)
        ->assertSee('apple-touch-icon.png', false)
        ->assertSee('apple-mobile-web-app-title', false)
        ->assertSee('name="theme-color"', false);
});
