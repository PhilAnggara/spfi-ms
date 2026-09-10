<?php

use App\Support\ScreenMessageHtml;

it('keeps plain text unchanged', function () {
    expect(ScreenMessageHtml::sanitize("Hello\nWorld"))->toBe("Hello\nWorld");
});

it('allows bold italic underline and lists', function () {
    $html = '<p>Hello <strong>bold</strong> <em>italic</em> <u>under</u></p><ul><li>One</li><li>Two</li></ul>';

    $clean = ScreenMessageHtml::sanitize($html);

    expect($clean)
        ->toContain('<strong>bold</strong>')
        ->toContain('<em>italic</em>')
        ->toContain('<u>under</u>')
        ->toContain('<ul>')
        ->toContain('<li>One</li>');
});

it('strips scripts and event handlers', function () {
    $html = '<p onclick="alert(1)">Hi<script>alert(2)</script></p><img src=x onerror="alert(3)">';

    $clean = ScreenMessageHtml::sanitize($html);

    expect($clean)
        ->not->toContain('script')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('<img')
        ->toContain('Hi');
});

it('strips disallowed attributes from allowed tags', function () {
    $clean = ScreenMessageHtml::sanitize('<p style="color:red" class="x"><strong onclick="x">Go</strong></p>');

    expect($clean)
        ->toContain('<strong>Go</strong>')
        ->not->toContain('style=')
        ->not->toContain('class=')
        ->not->toContain('onclick');
});

it('treats empty markup as empty', function () {
    expect(ScreenMessageHtml::isEmpty('<p><br></p>'))->toBeTrue()
        ->and(ScreenMessageHtml::isEmpty('<p>&nbsp;</p>'))->toBeTrue()
        ->and(ScreenMessageHtml::isEmpty('<p>Hi</p>'))->toBeFalse();
});

it('renders plain text with newlines for display', function () {
    expect(ScreenMessageHtml::forDisplay("A\nB"))->toBe("A<br>\nB");
});

it('renders sanitized html for display', function () {
    $html = ScreenMessageHtml::forDisplay('<p>Safe<strong>x</strong><script>bad</script></p>');

    expect($html)
        ->toContain('<strong>x</strong>')
        ->not->toContain('script');
});
